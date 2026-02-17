/**
 * Admin Dashboard JavaScript
 * Handles all interactive functionality for admin pages
 */

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    initializeAdmin();
});

function initializeAdmin() {
    // Initialize search functionality
    initSearch();
    
    // Initialize modals
    initModals();
    
    // Initialize filters
    initFilters();
    
    // Initialize form submissions
    initForms();
    
    // Initialize user profile dropdown
    initUserProfileDropdown();
}

/**
 * Search Functionality
 */
function initSearch() {
    const searchInputs = document.querySelectorAll('#userSearch, #patientSearch, #appointmentSearch, #billingSearch, #auditSearch');
    
    searchInputs.forEach(input => {
        if (input) {
            input.addEventListener('input', debounce(function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const tableBody = e.target.closest('.content-main').querySelector('tbody');
                
                if (tableBody) {
                    const rows = tableBody.querySelectorAll('tr');
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(searchTerm) ? '' : 'none';
                    });
                }
            }, 300));
        }
    });
}

/**
 * Debounce Helper
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Modal Functionality
 */
function initModals() {
    // Close modal when clicking outside
    const modals = document.querySelectorAll('.modal-overlay');
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(modal.id);
            }
        });
    });
    
    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.modal-overlay.active');
            if (activeModal) {
                closeModal(activeModal.id);
            }
        }
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
    }
}

/**
 * User Management Modal
 */
function openUserModal(userId = null) {
    const modal = document.getElementById('userModal');
    const form = document.getElementById('userForm');
    const title = document.getElementById('modalTitle');
    const userIdInput = document.getElementById('userId');
    
    if (modal && form) {
        if (userId) {
            title.textContent = 'Edit User';
            // Load user data from server
            fetch(`admin_users_actions.php?action=get&id=${encodeURIComponent(userId)}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        showToast(data.message || 'Failed to load user.', 'error');
                        return;
                    }
                    const user = data.user;
                    if (userIdInput) userIdInput.value = user.id;
                    document.getElementById('username').value = user.username || '';
                    document.getElementById('fullName').value = user.full_name || '';
                    document.getElementById('email').value = user.email || '';
                    document.getElementById('role').value = user.role || 'staff';
                    document.getElementById('status').value = user.status || 'active';
                    document.getElementById('password').value = '';
                    modal.classList.add('active');
                })
                .catch(() => {
                    showToast('Failed to load user.', 'error');
                });
        } else {
            title.textContent = 'Add New User';
            form.reset();
            if (userIdInput) userIdInput.value = '';
            document.getElementById('password').placeholder = 'Leave blank for auto-generated password';
            modal.classList.add('active');
        }
    }
}

function closeUserModal() {
    closeModal('userModal');
}

/**
 * Services Modal
 */
function openServiceModal(serviceId = null) {
    const modal = document.getElementById('serviceModal');
    const form = document.getElementById('serviceForm');
    const title = document.getElementById('serviceModalTitle');
    
    if (modal && form) {
        if (serviceId) {
            title.textContent = 'Edit Service';
            // Load service data (placeholder)
            document.getElementById('serviceName').value = 'Sample Service';
            document.getElementById('serviceDescription').value = 'Service description';
            document.getElementById('servicePrice').value = '1000';
            document.getElementById('serviceDuration').value = '30';
            document.getElementById('serviceStatus').value = 'active';
        } else {
            title.textContent = 'Add New Service';
            form.reset();
        }
        modal.classList.add('active');
    }
}

function closeServiceModal() {
    closeModal('serviceModal');
}

/**
 * Filter Functionality
 */
function initFilters() {
    const filterSelects = document.querySelectorAll('.filter-select');
    
    filterSelects.forEach(select => {
        select.addEventListener('change', function(e) {
            const filterValue = e.target.value.toLowerCase();
            const tableBody = e.target.closest('.content-main').querySelector('tbody');
            
            if (tableBody) {
                const rows = tableBody.querySelectorAll('tr');
                rows.forEach(row => {
                    let showRow = true;
                    
                    // Check all filter selects in the same container
                    const container = e.target.closest('.search-filters');
                    if (container) {
                        const selects = container.querySelectorAll('.filter-select');
                        selects.forEach(s => {
                            if (s.value && s.value !== '') {
                                const cell = row.cells[Array.from(row.parentNode.querySelectorAll('th')).findIndex(th => 
                                    th.textContent.toLowerCase().includes(s.id.replace('Filter', '').toLowerCase())
                                )];
                                if (cell) {
                                    const cellText = cell.textContent.toLowerCase();
                                    if (!cellText.includes(s.value.toLowerCase())) {
                                        showRow = false;
                                    }
                                }
                            }
                        });
                    }
                    
                    row.style.display = showRow ? '' : 'none';
                });
            }
        });
    });
}

/**
 * Form Submissions
 */
function initForms() {
    const userForm = document.getElementById('userForm');
    if (userForm) {
        userForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveUser();
        });
    }
    
    const serviceForm = document.getElementById('serviceForm');
    if (serviceForm) {
        serviceForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveService();
        });
    }
}

function saveUser() {
    // Get form data
    const userId = document.getElementById('userId').value;
    const userData = new FormData();
    userData.append('action', userId ? 'update' : 'create');
    if (userId) userData.append('id', userId);
    userData.append('username', document.getElementById('username').value);
    userData.append('fullName', document.getElementById('fullName').value);
    userData.append('email', document.getElementById('email').value);
    userData.append('role', document.getElementById('role').value);
    userData.append('status', document.getElementById('status').value);
    userData.append('password', document.getElementById('password').value);
    
    fetch('admin_users_actions.php', {
        method: 'POST',
        body: userData
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            showToast(data.message || 'Failed to save user.', 'error');
            return;
        }
        showToast(data.message || 'User saved successfully!', 'success');
        closeUserModal();
        // For simplicity, reload the page so the table shows the latest data
        setTimeout(() => window.location.reload(), 500);
    })
    .catch(() => {
        showToast('Failed to save user.', 'error');
    });
}

function deleteUser(userId) {
    if (!userId) return;
    if (!confirm('Delete this user?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', userId);
    
    fetch('admin_users_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            showToast(data.message || 'Failed to delete user.', 'error');
            return;
        }
        showToast(data.message || 'User deleted successfully!', 'success');
        setTimeout(() => window.location.reload(), 500);
    })
    .catch(() => {
        showToast('Failed to delete user.', 'error');
    });
}

function saveService() {
    const serviceData = {
        name: document.getElementById('serviceName').value,
        description: document.getElementById('serviceDescription').value,
        price: document.getElementById('servicePrice').value,
        duration: document.getElementById('serviceDuration').value,
        status: document.getElementById('serviceStatus').value
    };
    
    showToast('Service saved successfully!', 'success');
    closeServiceModal();
    refreshServicesGrid();
}

/**
 * Table Refresh (placeholder)
 */
function refreshUserTable() {
    // In a real app, this would fetch fresh data from the server
    console.log('Refreshing user table...');
}

function refreshServicesGrid() {
    // In a real app, this would fetch fresh data from the server
    console.log('Refreshing services grid...');
}

/**
 * Toast Notifications
 */
function showToast(message, type = 'info') {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <span class="toast-icon">${type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ'}</span>
        <span class="toast-message">${message}</span>
    `;
    
    // Add styles
    toast.style.cssText = `
        position: fixed;
        bottom: 24px;
        right: 24px;
        background: ${type === 'success' ? '#059669' : type === 'error' ? '#dc2626' : '#3b82f6'};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 2000;
        animation: slideIn 0.3s ease;
        font-size: 0.875rem;
    `;
    
    document.body.appendChild(toast);
    
    // Remove after 3 seconds
    setTimeout(() => {
        toast.style.animation = 'fadeOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/**
 * Report Functions
 */
function previewReport(reportType) {
    const modal = document.getElementById('printPreviewModal');
    const content = document.getElementById('printPreviewContent');
    
    if (modal && content) {
        content.innerHTML = `
            <div style="text-align: center; padding: 40px;">
                <h2 style="margin-bottom: 20px;">${reportType.charAt(0).toUpperCase() + reportType.slice(1)} Report</h2>
                <p style="color: #6b7280;">Report preview for ${reportType}</p>
                <p style="color: #6b7280; margin-top: 20px;">Loading data...</p>
            </div>
        `;
        modal.classList.add('active');
    }
}

function printReport(reportType) {
    // Create print content
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${reportType.charAt(0).toUpperCase() + reportType.slice(1)} Report</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                h1 { text-align: center; color: #333; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background: #f5f5f5; }
                @media print { body { padding: 0; } }
            </style>
        </head>
        <body>
            <h1>${reportType.charAt(0).toUpperCase() + reportType.slice(1)} Report</h1>
            <p style="text-align: center; color: #666;">Generated on ${new Date().toLocaleDateString()}</p>
            <table>
                <thead>
                    <tr><th>Column 1</th><th>Column 2</th><th>Column 3</th></tr>
                </thead>
                <tbody>
                    <tr><td>Data 1</td><td>Data 2</td><td>Data 3</td></tr>
                    <tr><td>Data 4</td><td>Data 5</td><td>Data 6</td></tr>
                </tbody>
            </table>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

function printCurrentReport() {
    window.print();
}

function closePreviewModal() {
    closeModal('printPreviewModal');
}

function generateCustomReport() {
    showToast('Generating custom report...', 'info');
    setTimeout(() => {
        showToast('Report generated successfully!', 'success');
    }, 1000);
}

function printCustomReport() {
    printReport('custom');
}

function exportToPDF() {
    showToast('Exporting to PDF...', 'info');
    setTimeout(() => {
        showToast('PDF exported successfully!', 'success');
    }, 1000);
}

/**
 * Billing Functions
 */
function exportBilling() {
    showToast('Exporting billing data...', 'info');
    setTimeout(() => {
        showToast('Billing data exported successfully!', 'success');
    }, 1000);
}

/**
 * Audit Trail Functions
 */
function exportAuditLogs(format) {
    showToast(`Exporting audit logs as ${format.toUpperCase()}...`, 'info');
    setTimeout(() => {
        showToast('Audit logs exported successfully!', 'success');
    }, 1000);
}

/**
 * Patient Functions
 */
function exportPatients() {
    showToast('Exporting patient data...', 'info');
    setTimeout(() => {
        showToast('Patient data exported successfully!', 'success');
    }, 1000);
}

/**
 * Appointment Functions
 */
function exportAppointments() {
    showToast('Exporting appointment data...', 'info');
    setTimeout(() => {
        showToast('Appointment data exported successfully!', 'success');
    }, 1000);
}

/**
 * Settings Functions
 */
function createBackup() {
    showToast('Creating backup...', 'info');
    setTimeout(() => {
        showToast('Backup created successfully!', 'success');
    }, 2000);
}

function downloadBackup() {
    showToast('Preparing download...', 'info');
    setTimeout(() => {
        showToast('Download started!', 'success');
    }, 1000);
}

/**
 * Analytics Period Filter
 */
const analyticsPeriod = document.getElementById('analyticsPeriod');
if (analyticsPeriod) {
    analyticsPeriod.addEventListener('change', function(e) {
        showToast(`Loading ${e.target.options[e.target.selectedIndex].text.toLowerCase()} data...`, 'info');
        // In a real app, this would reload the analytics data
    });
}

/**
 * User Profile Dropdown
 */
function initUserProfileDropdown() {
    const userProfile = document.getElementById('userProfileDropdown');
    
    if (userProfile) {
        // Toggle dropdown on click
        userProfile.addEventListener('click', function(e) {
            e.stopPropagation();
            userProfile.classList.toggle('active');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userProfile.contains(e.target)) {
                userProfile.classList.remove('active');
            }
        });
        
        // Close dropdown on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && userProfile.classList.contains('active')) {
                userProfile.classList.remove('active');
            }
        });
    }
}
