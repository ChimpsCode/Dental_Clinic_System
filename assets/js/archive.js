/**
 * Archive Management JavaScript
 * 
 * Handles all client-side functionality for the archive system
 * 
 * @package Dental_Clinic_System
 * @version 1.0
 * @date 2026-02-04
 */

// Current state
let currentModule = 'patients';
let currentPage = {
    patients: 1,
    appointments: 1,
    queue: 1,
    treatment_plans: 1,
    services: 1,
    inquiries: 1,
    users: 1
};

// Selected records
let selectedRecords = {
    patients: [],
    appointments: [],
    queue: [],
    treatment_plans: [],
    services: [],
    inquiries: [],
    users: []
};

/**
 * Initialize on page load
 */
document.addEventListener('DOMContentLoaded', function() {
    // Load initial data for patients tab
    loadArchivedPatients(1);
    
    // Add enter key support for search
    document.querySelectorAll('.search-input').forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const module = this.id.replace('-search', '');
                if (module === 'patients') {
                    loadArchivedPatients(1);
                } else if (module === 'appointments') {
                    loadArchivedAppointments(1);
                } else if (module === 'inquiries') {
                    loadArchivedInquiries(1);
                }
            }
        });
    });
});

/**
 * Switch between tabs
 */
function switchTab(tab) {
    currentModule = tab;
    
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
    
    // Update tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(`${tab}-content`).classList.add('active');
    
    // Load data for active tab
    if (tab === 'patients') {
        loadArchivedPatients(currentPage.patients);
    } else if (tab === 'appointments') {
        loadArchivedAppointments(currentPage.appointments);
    } else if (tab === 'inquiries') {
        loadArchivedInquiries(currentPage.inquiries);
    }
}

/**
 * Load archived patients
 */
function loadArchivedPatients(page) {
    currentPage.patients = page;
    
    const search = document.getElementById('patients-search')?.value || '';
    const dateFrom = document.getElementById('patients-dateFrom')?.value || '';
    const dateTo = document.getElementById('patients-dateTo')?.value || '';
    
    // Show loading state
    const tbody = document.getElementById('patients-table-body');
    tbody.innerHTML = `
        <tr>
            <td colspan="6" style="text-align: center; padding: 60px; color: #6b7280;">
                <div style="display: inline-block; animation: spin 1s linear infinite;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                    </svg>
                </div>
                <p style="margin-top: 10px;">Loading archived patients...</p>
            </td>
        </tr>
    `;
    
    // Fetch data
    fetch('archive_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'get_archived',
            module: 'patients',
            page: page,
            search: search,
            dateFrom: dateFrom,
            dateTo: dateTo
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderPatientTable(data.records);
            renderPagination('patients', data.current_page, data.pages, data.total);
        } else {
            showError(data.message);
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" style="text-align: center; padding: 60px; color: #ef4444;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 10px;">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <p>Error: ${escapeHtml(data.message)}</p>
                    </td>
                </tr>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('Failed to load archived patients');
        tbody.innerHTML = `
            <tr>
                <td colspan="9" style="text-align: center; padding: 60px; color: #ef4444;">
                    <p>Failed to load data. Please try again.</p>
                </td>
            </tr>
        `;
    });
}

/**
 * Render patient table
 */
function renderPatientTable(records) {
    const tbody = document.getElementById('patients-table-body');
    
    if (!records || records.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" style="text-align: center; padding: 60px; color: #6b7280;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 20px; opacity: 0.3;">
                        <path d="M20.54 5.23l-1.39-1.68C18.88 3.21 18.47 3 18 3H6c-.47 0-.88.21-1.16.55L3.46 5.23C3.17 5.57 3 6.02 3 6.5V19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6.5c0-.48-.17-.93-.46-1.27zM12 17.5L6.5 12H10v-2h4v2h3.5L12 17.5z"/>
                    </svg>
                    <h3 style="margin-bottom: 10px;">No Archived Patients</h3>
                    <p>No patients have been archived yet.</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = records.map(patient => `
        <tr>
            <td style="text-align: center;">
                <input type="checkbox" class="record-checkbox patients-checkbox"
                       value="${patient.id}"
                       onchange="updateSelection('patients', ${patient.id}, this.checked)">
            </td>
            <td>
                <div style="font-weight: 500; color: #111827;">${escapeHtml(patient.first_name || 'N/A')}</div>
            </td>
            <td>
                <div style="color: #374151;">${escapeHtml(patient.middle_name || '')}</div>
            </td>
            <td>
                <div style="font-weight: 500; color: #111827;">${escapeHtml(patient.last_name || 'N/A')}</div>
            </td>
            <td>
                <div style="color: #6b7280; font-size: 0.875rem;">${escapeHtml(patient.suffix || '')}</div>
            </td>
            <td>
                <div style="font-size: 0.85rem; color: #374151;">
                    ${patient.phone ? `<div>📞 ${escapeHtml(patient.phone)}</div>` : ''}
                    ${patient.email ? `<div style="color: #6b7280;">✉️ ${escapeHtml(patient.email)}</div>` : ''}
                </div>
            </td>
            <td style="color: #374151;">
                ${patient.date_of_birth || 'N/A'}
                <div style="font-size: 0.75rem; color: #6b7280;">${calculateAge(patient.date_of_birth)} yrs, ${patient.gender || 'N/A'}</div>
            </td>
            <td>
                <div style="font-size: 0.85rem; color: #374151;">
                    ${formatDate(patient.deleted_at)}
                </div>
            </td>
            <td style="text-align: center;">
                <button class="btn-restore btn-sm" onclick="singleAction('patients', ${patient.id}, 'restore')">
                    Restore
                </button>
                <button class="btn-delete-forever btn-sm" onclick="singleAction('patients', ${patient.id}, 'delete_forever')">
                    Delete Forever
                </button>
            </td>
        </tr>
    `).join('');
    
    // Update select all checkbox
    const selectAll = document.getElementById('select-all-patients');
    if (selectAll) {
        selectAll.checked = false;
    }
    selectedRecords.patients = [];
    updateBulkButtons('patients');
}

/**
 * Load archived appointments
 */
function loadArchivedAppointments(page) {
    currentPage.appointments = page;
    
    const search = document.getElementById('appointments-search')?.value || '';
    const dateFrom = document.getElementById('appointments-dateFrom')?.value || '';
    const dateTo = document.getElementById('appointments-dateTo')?.value || '';
    
    // Show loading state
    const tbody = document.getElementById('appointments-table-body');
    tbody.innerHTML = `
        <tr>
            <td colspan="7" style="text-align: center; padding: 60px; color: #6b7280;">
                <div style="display: inline-block; animation: spin 1s linear infinite;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                    </svg>
                </div>
                <p style="margin-top: 10px;">Loading archived appointments...</p>
            </td>
        </tr>
    `;
    
    // Fetch data
    fetch('archive_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'get_archived',
            module: 'appointments',
            page: page,
            search: search,
            dateFrom: dateFrom,
            dateTo: dateTo
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderAppointmentsTable(data.records);
            renderAppointmentsPagination(data.current_page, data.pages, data.total);
        } else {
            showError(data.message);
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; padding: 60px; color: #ef4444;">
                        <p>Error: ${escapeHtml(data.message)}</p>
                    </td>
                </tr>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('Failed to load archived appointments');
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 60px; color: #ef4444;">
                    <p>Failed to load data. Please try again.</p>
                </td>
            </tr>
        `;
    });
}

/**
 * Render appointments table
 */
function renderAppointmentsTable(records) {
    const tbody = document.getElementById('appointments-table-body');
    
    if (!records || records.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 60px; color: #6b7280;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 20px; opacity: 0.3;">
                        <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10z"/>
                    </svg>
                    <h3 style="margin-bottom: 10px;">No Archived Appointments</h3>
                    <p>No appointments have been archived yet.</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = records.map(apt => `
        <tr>
            <td style="text-align: center;">
                <input type="checkbox" class="record-checkbox appointments-checkbox" 
                       value="${apt.id}" 
                       onchange="updateSelection('appointments', ${apt.id}, this.checked)">
            </td>
            <td>
                <div style="font-weight: 500; color: #111827;">${escapeHtml(apt.patient_name || 'Unknown')}</div>
                ${apt.patient_phone ? `<div style="font-size: 0.8rem; color: #6b7280;">📞 ${escapeHtml(apt.patient_phone)}</div>` : ''}
            </td>
            <td>
                <div style="font-weight: 500; color: #374151;">${formatDateShort(apt.appointment_date)}</div>
                <div style="font-size: 0.85rem; color: #6b7280;">${apt.appointment_time || 'N/A'}</div>
            </td>
            <td>
                <div style="color: #374151;">${escapeHtml(apt.treatment || 'General Checkup')}</div>
            </td>
            <td>
                <span class="status-badge status-${apt.status}">${escapeHtml(apt.status || 'scheduled')}</span>
            </td>
            <td>
                <div style="font-size: 0.85rem; color: #374151;">${formatDate(apt.deleted_at)}</div>
            </td>
            <td style="text-align: center;">
                <button class="btn-restore btn-sm" onclick="singleAction('appointments', ${apt.id}, 'restore')">
                    Restore
                </button>
                <button class="btn-delete-forever btn-sm" onclick="singleAction('appointments', ${apt.id}, 'delete_forever')">
                    Delete Forever
                </button>
            </td>
        </tr>
    `).join('');
    
    // Update select all checkbox
    const selectAll = document.getElementById('select-all-appointments');
    if (selectAll) {
        selectAll.checked = false;
    }
    selectedRecords.appointments = [];
    updateBulkButtons('appointments');
}

/**
 * Render appointments pagination
 */
function renderAppointmentsPagination(currentPage, totalPages, totalRecords) {
    const container = document.getElementById('appointments-pagination');
    
    if (!container || totalPages <= 1) {
        if (container) container.innerHTML = '';
        return;
    }
    
    const limit = 7;
    const showingStart = ((currentPage - 1) * limit) + 1;
    const showingEnd = Math.min(currentPage * limit, totalRecords);
    
    let html = `
        <span class="pagination-info">
            Showing ${showingStart}-${showingEnd} of ${totalRecords} archived appointments
        </span>
        <div class="pagination-buttons">
    `;
    
    // Previous button
    if (currentPage > 1) {
        html += `<a href="#" onclick="loadArchivedAppointments(${currentPage - 1}); return false;" class="pagination-btn">Previous</a>`;
    } else {
        html += `<button class="pagination-btn" disabled>Previous</button>`;
    }
    
    // Page numbers (smart display)
    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
    
    if (endPage - startPage + 1 < maxVisible) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }
    
    if (startPage > 1) {
        html += `<a href="#" onclick="loadArchivedAppointments(1); return false;" class="pagination-btn">1</a>`;
        if (startPage > 2) {
            html += `<span class="pagination-ellipsis">...</span>`;
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        if (i === currentPage) {
            html += `<button class="pagination-btn active">${i}</button>`;
        } else {
            html += `<a href="#" onclick="loadArchivedAppointments(${i}); return false;" class="pagination-btn">${i}</a>`;
        }
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            html += `<span class="pagination-ellipsis">...</span>`;
        }
        html += `<a href="#" onclick="loadArchivedAppointments(${totalPages}); return false;" class="pagination-btn">${totalPages}</a>`;
    }
    
    // Next button
    if (currentPage < totalPages) {
        html += `<a href="#" onclick="loadArchivedAppointments(${currentPage + 1}); return false;" class="pagination-btn">Next</a>`;
    } else {
        html += `<button class="pagination-btn" disabled>Next</button>`;
    }
    
    html += '</div>';
    container.innerHTML = html;
}

/**
 * Load archived inquiries
 */
function loadArchivedInquiries(page) {
    currentPage.inquiries = page;

    const search = document.getElementById('inquiries-search')?.value || '';
    const dateFrom = document.getElementById('inquiries-dateFrom')?.value || '';
    const dateTo = document.getElementById('inquiries-dateTo')?.value || '';

    // Show loading state
    const tbody = document.getElementById('inquiries-table-body');
    tbody.innerHTML = `
        <tr>
            <td colspan="7" style="text-align: center; padding: 60px; color: #6b7280;">
                <div style="display: inline-block; animation: spin 1s linear infinite;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                    </svg>
                </div>
                <p style="margin-top: 10px;">Loading archived inquiries...</p>
            </td>
        </tr>
    `;

    // Fetch data
    fetch('archive_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'get_archived',
            module: 'inquiries',
            page: page,
            search: search,
            dateFrom: dateFrom,
            dateTo: dateTo
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderInquiriesTable(data.records);
            renderInquiriesPagination(data.current_page, data.pages, data.total);
        } else {
            showError(data.message);
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; padding: 60px; color: #ef4444;">
                        <p>Error: ${escapeHtml(data.message)}</p>
                    </td>
                </tr>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('Failed to load archived inquiries');
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 60px; color: #ef4444;">
                    <p>Failed to load data. Please try again.</p>
                </td>
            </tr>
        `;
    });
}

/**
 * Render inquiries table
 */
function renderInquiriesTable(records) {
    const tbody = document.getElementById('inquiries-table-body');

    if (!records || records.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 60px; color: #6b7280;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 20px; opacity: 0.3;">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <h3 style="margin-bottom: 10px;">No Archived Inquiries</h3>
                    <p>No inquiries have been archived yet.</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = records.map(inquiry => `
        <tr>
            <td style="text-align: center;">
                <input type="checkbox" class="record-checkbox inquiries-checkbox"
                       value="${inquiry.id}"
                       onchange="updateSelection('inquiries', ${inquiry.id}, this.checked)">
            </td>
            <td>
                <div style="font-weight: 500; color: #111827;">${escapeHtml(inquiry.full_name || 'Unknown')}</div>
            </td>
            <td>
                <div style="font-size: 0.85rem; color: #374151;">
                    ${inquiry.email ? `<div>✉️ ${escapeHtml(inquiry.email)}</div>` : ''}
                    ${inquiry.phone ? `<div style="color: #6b7280;">📞 ${escapeHtml(inquiry.phone)}</div>` : ''}
                </div>
            </td>
            <td>
                <div style="color: #374151;">${escapeHtml(inquiry.subject || 'N/A')}</div>
                <div style="font-size: 0.8rem; color: #6b7280; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(inquiry.message || '')}">
                    ${escapeHtml((inquiry.message || '').substring(0, 50))}${(inquiry.message || '').length > 50 ? '...' : ''}
                </div>
            </td>
            <td>
                <div style="font-size: 0.85rem; color: #374151;">${formatDateShort(inquiry.submitted_at)}</div>
            </td>
            <td>
                <div style="font-size: 0.85rem; color: #374151;">${formatDate(inquiry.deleted_at)}</div>
            </td>
            <td style="text-align: center;">
                <button class="btn-restore btn-sm" onclick="singleAction('inquiries', ${inquiry.id}, 'restore')">
                    Restore
                </button>
                <button class="btn-delete-forever btn-sm" onclick="singleAction('inquiries', ${inquiry.id}, 'delete_forever')">
                    Delete Forever
                </button>
            </td>
        </tr>
    `).join('');

    // Update select all checkbox
    const selectAll = document.getElementById('select-all-inquiries');
    if (selectAll) {
        selectAll.checked = false;
    }
    selectedRecords.inquiries = [];
    updateBulkButtons('inquiries');
}

/**
 * Render inquiries pagination
 */
function renderInquiriesPagination(currentPage, totalPages, totalRecords) {
    const container = document.getElementById('inquiries-pagination');

    if (!container || totalPages <= 1) {
        if (container) container.innerHTML = '';
        return;
    }

    const limit = 7;
    const showingStart = ((currentPage - 1) * limit) + 1;
    const showingEnd = Math.min(currentPage * limit, totalRecords);

    let html = `
        <span class="pagination-info">
            Showing ${showingStart}-${showingEnd} of ${totalRecords} archived inquiries
        </span>
        <div class="pagination-buttons">
    `;

    // Previous button
    if (currentPage > 1) {
        html += `<a href="#" onclick="loadArchivedInquiries(${currentPage - 1}); return false;" class="pagination-btn">Previous</a>`;
    } else {
        html += `<button class="pagination-btn" disabled>Previous</button>`;
    }

    // Page numbers (smart display)
    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);

    if (endPage - startPage + 1 < maxVisible) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }

    if (startPage > 1) {
        html += `<a href="#" onclick="loadArchivedInquiries(1); return false;" class="pagination-btn">1</a>`;
        if (startPage > 2) {
            html += `<span class="pagination-ellipsis">...</span>`;
        }
    }

    for (let i = startPage; i <= endPage; i++) {
        if (i === currentPage) {
            html += `<button class="pagination-btn active">${i}</button>`;
        } else {
            html += `<a href="#" onclick="loadArchivedInquiries(${i}); return false;" class="pagination-btn">${i}</a>`;
        }
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            html += `<span class="pagination-ellipsis">...</span>`;
        }
        html += `<a href="#" onclick="loadArchivedInquiries(${totalPages}); return false;" class="pagination-btn">${totalPages}</a>`;
    }

    // Next button
    if (currentPage < totalPages) {
        html += `<a href="#" onclick="loadArchivedInquiries(${currentPage + 1}); return false;" class="pagination-btn">Next</a>`;
    } else {
        html += `<button class="pagination-btn" disabled>Next</button>`;
    }

    html += '</div>';
    container.innerHTML = html;
}

/**
 * Format date (short version for appointments)
 */
function formatDateShort(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

/**
 * Render pagination
 */
function renderPagination(module, currentPage, totalPages, totalRecords) {
    const container = document.getElementById(`${module}-pagination`);
    
    if (!container || totalPages <= 1) {
        if (container) container.innerHTML = '';
        return;
    }
    
    const limit = 7;
    const showingStart = ((currentPage - 1) * limit) + 1;
    const showingEnd = Math.min(currentPage * limit, totalRecords);
    
    let html = `
        <span class="pagination-info">
            Showing ${showingStart}-${showingEnd} of ${totalRecords} archived ${module}
        </span>
        <div class="pagination-buttons">
    `;
    
    // Previous button
    if (currentPage > 1) {
        html += `<a href="#" onclick="loadArchivedPatients(${currentPage - 1}); return false;" class="pagination-btn">Previous</a>`;
    } else {
        html += `<button class="pagination-btn" disabled>Previous</button>`;
    }
    
    // Page numbers (smart display)
    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
    
    if (endPage - startPage + 1 < maxVisible) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }
    
    if (startPage > 1) {
        html += `<a href="#" onclick="loadArchivedPatients(1); return false;" class="pagination-btn">1</a>`;
        if (startPage > 2) {
            html += `<span class="pagination-ellipsis">...</span>`;
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        if (i === currentPage) {
            html += `<button class="pagination-btn active">${i}</button>`;
        } else {
            html += `<a href="#" onclick="loadArchivedPatients(${i}); return false;" class="pagination-btn">${i}</a>`;
        }
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            html += `<span class="pagination-ellipsis">...</span>`;
        }
        html += `<a href="#" onclick="loadArchivedPatients(${totalPages}); return false;" class="pagination-btn">${totalPages}</a>`;
    }
    
    // Next button
    if (currentPage < totalPages) {
        html += `<a href="#" onclick="loadArchivedPatients(${currentPage + 1}); return false;" class="pagination-btn">Next</a>`;
    } else {
        html += `<button class="pagination-btn" disabled>Next</button>`;
    }
    
    html += '</div>';
    container.innerHTML = html;
}

/**
 * Toggle select all checkboxes
 */
function toggleSelectAll(module, checked) {
    const checkboxes = document.querySelectorAll(`.${module}-checkbox`);
    selectedRecords[module] = [];
    
    checkboxes.forEach(cb => {
        cb.checked = checked;
        if (checked) {
            selectedRecords[module].push(cb.value);
        }
    });
    
    updateBulkButtons(module);
}

/**
 * Update selection for a single record
 */
function updateSelection(module, id, checked) {
    if (checked) {
        if (!selectedRecords[module].includes(id.toString())) {
            selectedRecords[module].push(id.toString());
        }
    } else {
        selectedRecords[module] = selectedRecords[module].filter(recordId => recordId !== id.toString());
    }
    
    updateBulkButtons(module);
    
    // Update select all checkbox
    const allCheckboxes = document.querySelectorAll(`.${module}-checkbox`);
    const checkedCheckboxes = document.querySelectorAll(`.${module}-checkbox:checked`);
    const selectAll = document.getElementById(`select-all-${module}`);
    if (selectAll) {
        selectAll.checked = allCheckboxes.length === checkedCheckboxes.length && allCheckboxes.length > 0;
    }
}

/**
 * Update bulk action buttons
 */
function updateBulkButtons(module) {
    const count = selectedRecords[module].length;
    
    const restoreBtn = document.getElementById(`bulk-restore-${module}`);
    const deleteBtn = document.getElementById(`bulk-delete-${module}`);
    
    if (restoreBtn && deleteBtn) {
        if (count > 0) {
            restoreBtn.disabled = false;
            deleteBtn.disabled = false;
            restoreBtn.textContent = `Restore Selected (${count})`;
            deleteBtn.textContent = `Delete Forever (${count})`;
        } else {
            restoreBtn.disabled = true;
            deleteBtn.disabled = true;
            restoreBtn.textContent = 'Restore Selected';
            deleteBtn.textContent = 'Delete Forever';
        }
    }
}

/**
 * Perform bulk action
 */
function bulkAction(module, action) {
    const ids = selectedRecords[module];
    
    if (ids.length === 0) return;
    
    let message = '';
    if (action === 'restore') {
        message = `Restore ${ids.length} ${module}(s)? They will reappear in the main list.`;
    } else {
        message = `WARNING: Permanently delete ${ids.length} ${module}(s)? This CANNOT be undone!`;
    }
    
    if (!confirm(message)) return;
    
    performAction(module, action, ids);
}

/**
 * Perform single action
 */
function singleAction(module, id, action) {
    let message = '';
    if (action === 'restore') {
        message = 'Restore this record? It will reappear in the main list.';
    } else {
        message = 'WARNING: Permanently delete this record? This CANNOT be undone!';
    }
    
    if (!confirm(message)) return;
    
    performAction(module, action, [id]);
}

/**
 * Perform action (restore or delete)
 */
function performAction(module, action, ids) {
    const formData = new URLSearchParams();
    formData.append('action', action);
    formData.append('module', module);
    ids.forEach(id => formData.append('ids[]', id));
    
    fetch('archive_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message);
            // Reload current page based on module
            if (module === 'patients') {
                loadArchivedPatients(currentPage.patients);
            } else if (module === 'appointments') {
                loadArchivedAppointments(currentPage.appointments);
            } else if (module === 'inquiries') {
                loadArchivedInquiries(currentPage.inquiries);
            }
            // Clear selection
            selectedRecords[module] = [];
            updateBulkButtons(module);
            // Update stats
            updateArchiveStats();
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('Action failed. Please try again.');
    });
}

/**
 * Reset filters
 */
function resetFilters(module) {
    const searchInput = document.getElementById(`${module}-search`);
    const dateFromInput = document.getElementById(`${module}-dateFrom`);
    const dateToInput = document.getElementById(`${module}-dateTo`);
    
    if (searchInput) searchInput.value = '';
    if (dateFromInput) dateFromInput.value = '';
    if (dateToInput) dateToInput.value = '';
    
    if (module === 'patients') {
        loadArchivedPatients(1);
    } else if (module === 'appointments') {
        loadArchivedAppointments(1);
    } else if (module === 'inquiries') {
        loadArchivedInquiries(1);
    }
}

/**
 * Update archive statistics
 */
function updateArchiveStats() {
    // Reload page to get updated stats
    // In a production app, you'd fetch just the stats via AJAX
    location.reload();
}

/**
 * Calculate age from date of birth
 */
function calculateAge(dob) {
    if (!dob) return 'N/A';
    const today = new Date();
    const birthDate = new Date(dob);
    let age = today.getFullYear() - birthDate.getFullYear();
    const m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    return age;
}

/**
 * Format date
 */
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Show success message
 */
function showSuccess(message) {
    // Create a nice toast notification
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #10b981;
        color: white;
        padding: 16px 24px;
        border-radius: 8px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        z-index: 99999;
        font-weight: 500;
        animation: slideIn 0.3s ease;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/**
 * Show error message
 */
function showError(message) {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #ef4444;
        color: white;
        padding: 16px 24px;
        border-radius: 8px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        z-index: 99999;
        font-weight: 500;
        animation: slideIn 0.3s ease;
    `;
    toast.textContent = 'Error: ' + message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);
