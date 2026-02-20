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

    // Initialize password visibility toggles
    initPasswordToggles();

    // Initialize meatball menus in user tables
    initUserKebabMenu();
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
            const editOnlyFields = modal.querySelectorAll('[data-edit-only="true"]');
            editOnlyFields.forEach(el => el.style.display = '');
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
                    document.getElementById('firstName').value = user.first_name || '';
                    document.getElementById('middleName').value = user.middle_name || '';
                    document.getElementById('lastName').value = user.last_name || '';
                    document.getElementById('email').value = user.email || '';
                    document.getElementById('role').value = user.role || 'staff';
                    const statusSelect = document.getElementById('status');
                    if (statusSelect) statusSelect.value = user.status || 'active';
                    const passwordInput = document.getElementById('password');
                    const confirmPasswordInput = document.getElementById('confirmPassword');
                    if (passwordInput) passwordInput.value = '';
                    if (confirmPasswordInput) confirmPasswordInput.value = '';
                    if (passwordInput) passwordInput.dispatchEvent(new Event('input'));
                    if (confirmPasswordInput) confirmPasswordInput.dispatchEvent(new Event('input'));
                    modal.classList.add('active');
                })
                .catch(() => {
                    showToast('Failed to load user.', 'error');
                });
        } else {
            title.textContent = 'Add New User';
            form.reset();
            if (userIdInput) userIdInput.value = '';
            document.getElementById('password').placeholder = 'Password';
            const editOnlyFields = modal.querySelectorAll('[data-edit-only="true"]');
            editOnlyFields.forEach(el => el.style.display = 'none');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            if (passwordInput) passwordInput.dispatchEvent(new Event('input'));
            if (confirmPasswordInput) confirmPasswordInput.dispatchEvent(new Event('input'));
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

function initPasswordToggles() {
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const togglePassword = document.getElementById('togglePassword');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');

    function bindToggle(input, toggleBtn) {
        if (!input || !toggleBtn) return;

        function updateVisibility() {
            toggleBtn.style.display = input.value.length > 0 ? 'flex' : 'none';
        }

        input.addEventListener('input', updateVisibility);
        input.addEventListener('paste', updateVisibility);
        input.addEventListener('keyup', updateVisibility);
        updateVisibility();

        toggleBtn.addEventListener('click', function() {
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);

            const eyeOpen = toggleBtn.querySelector('.eye-open');
            const eyeClosed = toggleBtn.querySelector('.eye-closed');
            if (type === 'password') {
                if (eyeOpen) eyeOpen.style.display = 'block';
                if (eyeClosed) eyeClosed.style.display = 'none';
            } else {
                if (eyeOpen) eyeOpen.style.display = 'none';
                if (eyeClosed) eyeClosed.style.display = 'block';
            }
        });
    }

    bindToggle(passwordInput, togglePassword);
    bindToggle(confirmPasswordInput, toggleConfirmPassword);
}

function saveUser() {
    // Get form data
    const userId = document.getElementById('userId').value;
    const userData = new FormData();
    userData.append('action', userId ? 'update' : 'create');
    if (userId) userData.append('id', userId);
    userData.append('username', document.getElementById('username').value);
    const firstName = document.getElementById('firstName').value.trim();
    const middleName = document.getElementById('middleName').value.trim();
    const lastName = document.getElementById('lastName').value.trim();
    userData.append('firstName', firstName);
    userData.append('middleName', middleName);
    userData.append('lastName', lastName);
    userData.append('email', document.getElementById('email').value);
    userData.append('role', document.getElementById('role').value);
    const statusSelect = document.getElementById('status');
    if (statusSelect) userData.append('status', statusSelect.value);
    userData.append('password', document.getElementById('password').value);
    userData.append('confirmPassword', document.getElementById('confirmPassword').value);
    
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

function toggleUserStatus(userId, currentStatus) {
    if (!userId) return;
    const nextStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const actionText = nextStatus === 'active' ? 'Activate' : 'Deactivate';
    if (!confirm(`${actionText} this user?`)) return;

    const formData = new FormData();
    formData.append('action', 'toggle_status');
    formData.append('id', userId);
    formData.append('status', nextStatus);

    fetch('admin_users_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            showToast(data.message || 'Failed to update status.', 'error');
            return;
        }
        showToast(data.message || 'Status updated.', 'success');
        setTimeout(() => window.location.reload(), 500);
    })
    .catch(() => {
        showToast('Failed to update status.', 'error');
    });
}

/**
 * User Kebab (meatball) menu
 */
let userKebabDropdown = null;
let userKebabBackdrop = null;
let activeUserKebabButton = null;

function initUserKebabMenu() {
    userKebabDropdown = document.getElementById('userKebabDropdown');
    userKebabBackdrop = document.getElementById('userKebabBackdrop');

    if (!userKebabDropdown || !userKebabBackdrop) return;

    document.addEventListener('click', handleUserKebabToggle);
    userKebabDropdown.addEventListener('click', handleUserKebabAction);
    userKebabBackdrop.addEventListener('click', closeUserKebabDropdown);
    window.addEventListener('resize', closeUserKebabDropdown);
    window.addEventListener('scroll', closeUserKebabDropdown, true);
}

function handleUserKebabToggle(e) {
    const button = e.target.closest('.user-kebab-btn');
    const isKebabButton = Boolean(button);

    if (!isKebabButton) {
        if (!e.target.closest('.user-kebab-dropdown')) {
            closeUserKebabDropdown();
        }
        return;
    }

    e.preventDefault();
    e.stopPropagation();

    if (activeUserKebabButton === button) {
        closeUserKebabDropdown();
        return;
    }

    openUserKebabDropdown(button);
}

function openUserKebabDropdown(button) {
    if (!userKebabDropdown || !userKebabBackdrop) return;

    const userId = button.dataset.userId;
    const status = button.dataset.userStatus || 'active';

    userKebabDropdown.innerHTML = getUserKebabMarkup(userId, status);
    positionUserKebabDropdown(button);

    userKebabDropdown.classList.add('show');
    userKebabBackdrop.classList.add('show');
    button.classList.add('active');
    activeUserKebabButton = button;
}

function closeUserKebabDropdown() {
    if (userKebabDropdown) {
        userKebabDropdown.classList.remove('show');
        userKebabDropdown.innerHTML = '';
    }
    if (userKebabBackdrop) {
        userKebabBackdrop.classList.remove('show');
    }
    if (activeUserKebabButton) {
        activeUserKebabButton.classList.remove('active');
        activeUserKebabButton = null;
    }
}

function positionUserKebabDropdown(button) {
    const rect = button.getBoundingClientRect();
    const dropdownWidth = 190;
    const dropdownHeight = 160;
    const padding = 12;

    let left = rect.right + 6;
    let top = rect.top;

    if (left + dropdownWidth > window.innerWidth - padding) {
        left = rect.left - dropdownWidth - 6;
    }
    if (left < padding) left = padding;

    if (top + dropdownHeight > window.innerHeight - padding) {
        top = rect.bottom - dropdownHeight;
    }
    if (top < padding) top = padding;

    userKebabDropdown.style.left = `${left}px`;
    userKebabDropdown.style.top = `${top}px`;
}

function getUserKebabMarkup(userId, status) {
    const isActive = status === 'active';
    const toggleLabel = isActive ? 'Deactivate' : 'Activate';
    const toggleIcon = isActive
        ? '<path d=\"M5 12h14\"/>'
        : '<path d=\"M5 12h14\"/><path d=\"M12 5v14\"/>';

    return `
        <a href="#" data-action="edit" data-user-id="${userId}">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
            Edit
        </a>
        <a href="#" data-action="toggle" data-user-id="${userId}" data-user-status="${status}">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${toggleIcon}</svg>
            ${toggleLabel}
        </a>
        <a href="#" class="danger" data-action="delete" data-user-id="${userId}">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
            Delete
        </a>
    `;
}

function handleUserKebabAction(e) {
    const link = e.target.closest('a[data-action]');
    if (!link) return;

    e.preventDefault();
    const action = link.dataset.action;
    const userId = parseInt(link.dataset.userId, 10);
    const status = link.dataset.userStatus;

    closeUserKebabDropdown();

    if (!userId) return;

    switch (action) {
        case 'edit':
            openUserModal(userId);
            break;
        case 'toggle':
            toggleUserStatus(userId, status || 'active');
            break;
        case 'delete':
            deleteUser(userId);
            break;
        default:
            break;
    }
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
