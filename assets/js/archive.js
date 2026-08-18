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
    users: 1,
    inquiries: 1
};

// Selected records
let selectedRecords = {
    patients: [],
    users: [],
    inquiries: []
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
                } else if (module === 'users') {
                    loadArchivedUsers(1);
                } else if (module === 'appointments') {
                    loadArchivedAppointments(1);
                } else if (module === 'inquiries') {
                    loadArchivedInquiries(1);
                }
            }
        });
    });

    initArchiveKebabMenu();
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
    } else if (tab === 'users') {
        loadArchivedUsers(currentPage.users);
    } else if (tab === 'inquiries') {
        loadArchivedInquiries(currentPage.inquiries);
    }
}

// Initialize on page load
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
                } else if (module === 'users') {
                    loadArchivedUsers(1);
                } else if (module === 'appointments') {
                    loadArchivedAppointments(1);
                } else if (module === 'inquiries') {
                    loadArchivedInquiries(1);
                }
            }
        });
    });

    initArchiveKebabMenu();
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
    } else if (tab === 'users') {
        loadArchivedUsers(currentPage.users);
    } else if (tab === 'inquiries') {
        loadArchivedInquiries(currentPage.inquiries);
    }
}

/**
 * Load archived users (dentists/staff/employees)
 */
function loadArchivedUsers(page) {
    currentPage.users = page;
    
    const search = document.getElementById('users-search')?.value || '';
    const roleFilter = document.getElementById('users-role-filter')?.value || '';
    
    // Show loading state
    const tbody = document.getElementById('users-table-body');
    tbody.innerHTML = `
        <tr>
            <td colspan="8" style="text-align: center; padding: 60px; color: #6b7280;">
                <div style="display: inline-block; animation: spin 1s linear infinite;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                    </svg>
                </div>
                <p style="margin-top: 10px;">Loading archived users...</p>
            </td>
        </tr>
    `;
    
    // Fetch data
    fetch('../../master/shared/archive_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'get_archived',
            module: 'users',
            page: page,
            search: search,
            role: roleFilter
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderUsersTable(data.records);
            renderUsersPagination(data.current_page, data.pages, data.total);
        } else {
            showError(data.message);
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" style="text-align: center; padding: 60px; color: #ef4444;">
                        <p>Error: ${escapeHtml(data.message)}</p>
                    </td>
                </tr>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('Failed to load archived users');
        tbody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; padding: 60px; color: #ef4444;">
                    <p>Failed to load data. Please try again.</p>
                </td>
            </tr>
        `;
    });
}

/**
 * Render users table
 */
function renderUsersTable(records) {
    const tbody = document.getElementById('users-table-body');
    
    if (!records || records.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; padding: 60px; color: #6b7280;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 20px; opacity: 0.3;">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                    <h3 style="margin-bottom: 10px;">No Archived Users</h3>
                    <p>No users have been archived yet.</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = records.map(user => `
        <tr>
            <td style="text-align: center;">
                <input type="checkbox" class="record-checkbox users-checkbox"
                       value="${user.id}"
                       onchange="updateSelection('users', ${user.id}, this.checked)">
            </td>
            <td>
                <div style="font-weight: 500; color: #111827;">${escapeHtml(user.first_name || 'N/A')}</div>
            </td>
            <td>
                <div style="color: #374151;">${escapeHtml(user.middle_name || '')}</div>
            </td>
            <td>
                <div style="font-weight: 500; color: #111827;">${escapeHtml(user.last_name || 'N/A')}</div>
            </td>
            <td>
                <div style="font-size: 0.85rem; color: #374151;">${escapeHtml(user.email || 'N/A')}</div>
            </td>
            <td>
                <span class="role-badge ${(user.role_name || 'patient').toLowerCase()}">${escapeHtml(user.role_name || 'Patient')}</span>
            </td>
            <td>
                <div style="font-size: 0.85rem; color: #374151;">${formatDate(user.archived_at)}</div>
            </td>
            <td style="text-align: center;">
                <button class="archive-kebab-btn" type="button" data-module="users" data-id="${user.id}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="12" cy="5" r="2"></circle>
                        <circle cx="12" cy="12" r="2"></circle>
                        <circle cx="12" cy="19" r="2"></circle>
                    </svg>
                </button>
            </td>
        </tr>
    `).join('');
    
    // Update select all checkbox
    const selectAll = document.getElementById('select-all-users');
    if (selectAll) {
        selectAll.checked = false;
    }
    selectedRecords.users = [];
    updateBulkButtons('users');
}

/**
 * Render users pagination
 */
function renderUsersPagination(currentPage, totalPages, totalRecords) {
    const container = document.getElementById('users-pagination');
    
    if (!container || totalPages <= 1) {
        if (container) container.innerHTML = '';
        return;
    }
    
    const limit = 7;
    const showingStart = ((currentPage - 1) * limit) + 1;
    const showingEnd = Math.min(currentPage * limit, totalRecords);
    
    let html = `
        <span class="pagination-info">
            Showing ${showingStart}-${showingEnd} of ${totalRecords} archived users
        </span>
        <div class="pagination-buttons">
    `;
    
    // Previous button
    if (currentPage > 1) {
        html += `<a href="#" onclick="loadArchivedUsers(${currentPage - 1}); return false;" class="pagination-btn">Previous</a>`;
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
        html += `<a href="#" onclick="loadArchivedUsers(1); return false;" class="pagination-btn">1</a>`;
        if (startPage > 2) {
            html += `<span class="pagination-ellipsis">...</span>`;
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        if (i === currentPage) {
            html += `<button class="pagination-btn active">${i}</button>`;
        } else {
            html += `<a href="#" onclick="loadArchivedUsers(${i}); return false;" class="pagination-btn">${i}</a>`;
        }
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            html += `<span class="pagination-ellipsis">...</span>`;
        }
        html += `<a href="#" onclick="loadArchivedUsers(${totalPages}); return false;" class="pagination-btn">${totalPages}</a>`;
    }
    
    // Next button
    if (currentPage < totalPages) {
        html += `<a href="#" onclick="loadArchivedUsers(${currentPage + 1}); return false;" class="pagination-btn">Next</a>`;
    } else {
        html += `<button class="pagination-btn" disabled>Next</button>`;
    }
    
    html += '</div>';
    container.innerHTML = html;
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
    fetch('../../master/shared/archive_actions.php', {
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
                       onchange="updateSelection('patients', '${patient.id}', this.checked)">
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
                <button class="archive-kebab-btn" type="button" data-module="patients" data-id="${patient.id}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="12" cy="5" r="2"></circle>
                        <circle cx="12" cy="12" r="2"></circle>
                        <circle cx="12" cy="19" r="2"></circle>
                    </svg>
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
    fetch('../../master/shared/archive_actions.php', {
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
                <button class="archive-kebab-btn" type="button" data-module="appointments" data-id="${apt.id}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="12" cy="5" r="2"></circle>
                        <circle cx="12" cy="12" r="2"></circle>
                        <circle cx="12" cy="19" r="2"></circle>
                    </svg>
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
    fetch('../../master/shared/archive_actions.php', {
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
                <div style="font-size: 0.85rem; color: #374151; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; line-height: 1.4;" title="${escapeHtml(inquiry.message || '')}">
                    ${escapeHtml((inquiry.message || 'N/A').substring(0, 60))}${(inquiry.message || '').length > 60 ? '...' : ''}
                </div>
            </td>
            <td>
                <div style="font-size: 0.85rem; color: #374151;">${formatDateShort(inquiry.submitted_at)}</div>
            </td>
            <td>
                <div style="font-size: 0.85rem; color: #374151;">${formatDate(inquiry.deleted_at)}</div>
            </td>
            <td style="text-align: center;">
                <button class="archive-kebab-btn" type="button" data-module="inquiries" data-id="${inquiry.id}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="12" cy="5" r="2"></circle>
                        <circle cx="12" cy="12" r="2"></circle>
                        <circle cx="12" cy="19" r="2"></circle>
                    </svg>
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

    // Store records for conversation view
    window.inquiryConvMap = window.inquiryConvMap || {};
    records.forEach(inquiry => {
        window.inquiryConvMap[inquiry.id] = inquiry;
    });
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
 * View full inquiry conversation in a read-only modal
 */
function viewInquiryConversation(id) {
    const inquiry = window.inquiryConvMap && window.inquiryConvMap[id];
    if (!inquiry) {
        showError('Inquiry record not found.');
        return;
    }

    const existing = document.getElementById('inquiryConvModal');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.id = 'inquiryConvModal';
    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:99999;';

    const modal = document.createElement('div');
    modal.style.cssText = 'background:#fff;border-radius:16px;width:560px;max-width:94%;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,0.25);overflow:hidden;animation:fadeIn 0.2s ease;';

    const fullName = inquiry.full_name || 'Unknown';
    const concernText = inquiry.concern || inquiry.message || 'No concern provided.';
    const replies = parseInquiryConvNotes(inquiry.notes);

    modal.innerHTML = `
        <div style="padding:18px 22px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;background:#fafbff;">
            <div>
                <div style="font-weight:700;font-size:16px;color:#111827;">${escapeHtml(fullName)}</div>
                <div style="font-size:13px;color:#64748b;">${escapeHtml(inquiry.email || 'No email')}</div>
            </div>
            <button onclick="closeInquiryConvModal()" style="background:none;border:none;cursor:pointer;padding:6px;border-radius:50%;color:#6b7280;font-size:22px;line-height:1;">&times;</button>
        </div>
        <div id="inquiryConvMessages" style="flex:1;overflow-y:auto;padding:18px 22px;display:flex;flex-direction:column;gap:10px;background:#fcfdff;">
            <div class="conv-bubble received" style="max-width:88%;padding:12px 16px;border-radius:18px;line-height:1.55;font-size:14px;word-break:break-word;align-self:flex-start;background:#f0f2f5;color:#111827;border-bottom-left-radius:6px;">
                <div>${escapeHtml(concernText).replace(/\n/g, '<br>')}</div>
                <div style="font-size:11px;opacity:0.65;margin-top:6px;">${escapeHtml(formatDate(inquiry.submitted_at))}</div>
            </div>
        </div>
        <div style="padding:14px 22px;border-top:1px solid #e5e7eb;text-align:right;background:#fff;flex-shrink:0;">
            <button onclick="closeInquiryConvModal()" style="padding:10px 24px;background:#0ea5e9;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;">Close</button>
        </div>
    `;

    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';

    // Append reply bubbles
    const container = document.getElementById('inquiryConvMessages');
    replies.forEach(function(reply) {
        const bubble = document.createElement('div');
        bubble.className = 'conv-bubble sent';
        bubble.style.cssText = 'max-width:88%;padding:12px 16px;border-radius:18px;line-height:1.55;font-size:14px;word-break:break-word;align-self:flex-end;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border-bottom-right-radius:6px;';
        bubble.innerHTML = '<div>' + escapeHtml(reply.message || '(no message)').replace(/\n/g, '<br>') + '</div><div style="font-size:11px;opacity:0.65;margin-top:6px;color:rgba(255,255,255,0.8);">' + escapeHtml(reply.timestamp) + '</div>';
        container.appendChild(bubble);
    });

    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeInquiryConvModal();
    });
}

function closeInquiryConvModal() {
    const modal = document.getElementById('inquiryConvModal');
    if (modal) modal.remove();
    document.body.style.overflow = '';
}

function parseInquiryConvNotes(notes) {
    if (!notes) return [];
    const replies = [];
    const lines = notes.split('\n');
    let current = null;
    for (const line of lines) {
        const staffMatch = line.match(/^\[Staff replied on (.+?)\]/);
        if (staffMatch) {
            if (current) replies.push(current);
            current = { timestamp: staffMatch[1], message: '', type: 'sent' };
        } else if (current) {
            current.message += (current.message ? '\n' : '') + line;
        }
    }
    if (current) replies.push(current);
    return replies;
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
    
    // Use modal for restore
    if (action === 'restore') {
        showBulkRestoreModal(module, ids);
    } else if (action === 'delete_forever') {
        // Show password modal for delete forever
        showVerifyPasswordModal(ids.length, (verified) => {
            if (verified) {
                performAction(module, action, ids);
            }
        });
    }
}

/**
 * Show bulk restore confirmation modal
 */
function showBulkRestoreModal(module, ids) {
    const count = ids.length;
    const moduleName = module === 'patients' ? 'patient record' : (module === 'users' ? 'employee' : 'record');
    const typeName = moduleName === 'patient record' ? 'Patient' : (moduleName === 'employee' ? 'Employee' : 'Record');
    
    // Remove existing modal if any
    const existingModal = document.getElementById('restore-modal');
    if (existingModal) existingModal.remove();
    
    // Create modal
    const overlay = document.createElement('div');
    overlay.id = 'restore-modal';
    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:99999;';
    
    const modal = document.createElement('div');
    modal.style.cssText = 'background:white;border-radius:12px;padding:30px;max-width:400px;width:90%;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,0.2);';
    const label = count === 1 ? typeName : typeName + 's';
    modal.innerHTML = `
        <div style="margin-bottom: 20px;">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" width="48" height="48">
                <path d="M4.52185 7H7C7.55229 7 8 7.44772 8 8C8 8.55229 7.55228 9 7 9H3C1.89543 9 1 8.10457 1 7V3C1 2.44772 1.44772 2 2 2C2.55228 2 3 2.44772 3 3V5.6754C4.26953 3.8688 6.06062 2.47676 8.14852 1.69631C10.6633 0.756291 13.435 0.768419 15.9415 1.73041C18.448 2.69239 20.5161 4.53782 21.7562 6.91897C22.9963 9.30013 23.3228 12.0526 22.6741 14.6578C22.0254 17.263 20.4464 19.541 18.2345 21.0626C16.0226 22.5842 13.3306 23.2444 10.6657 22.9188C8.00083 22.5931 5.54702 21.3041 3.76664 19.2946C2.20818 17.5356 1.25993 15.3309 1.04625 13.0078C0.995657 12.4579 1.45216 12.0088 2.00445 12.0084C2.55673 12.0079 3.00351 12.4566 3.06526 13.0055C3.27138 14.8374 4.03712 16.5706 5.27027 17.9625C6.7255 19.605 8.73118 20.6586 10.9094 20.9247C13.0876 21.1909 15.288 20.6513 17.0959 19.4075C18.9039 18.1638 20.1945 16.3018 20.7247 14.1724C21.2549 12.043 20.9881 9.79319 19.9745 7.8469C18.9608 5.90061 17.2704 4.3922 15.2217 3.6059C13.173 2.8196 10.9074 2.80968 8.8519 3.57803C7.11008 4.22911 5.62099 5.40094 4.57993 6.92229C4.56156 6.94914 4.54217 6.97505 4.52185 7Z" fill="#10b981"/>
            </svg>
        </div>
        <h3 style="margin: 0 0 10px 0; color: #111827; font-size: 20px;">Restore ${count} ${label}?</h3>
        <p style="margin: 0 0 25px 0; color: #6b7280; font-size: 15px;">They will reappear in the main list.</p>
        <div style="display: flex; gap: 12px; justify-content: center;">
            <button onclick="closeRestoreModal()" style="padding: 12px 24px; background: #f3f4f6; color: #374151; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500;">Cancel</button>
            <button onclick="confirmBulkRestore('${module}')" style="padding: 12px 24px; background: #10b981; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500;">Restore</button>
        </div>
    `;
    
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';
}

function confirmBulkRestore(module) {
    closeRestoreModal();
    performAction(module, 'restore', selectedRecords[module]);
}

/**
 * Verify admin password via custom modal
 */
function showVerifyPasswordModal(count, callback) {
    // Remove existing modal if any
    const existingModal = document.getElementById('verify-password-modal');
    if (existingModal) {
        existingModal.remove();
    }

    // Create modal elements
    const overlay = document.createElement('div');
    overlay.id = 'verify-password-modal';
    overlay.className = 'modal-overlay active';
    
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.maxWidth = '400px';
    modal.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; color: #dc2626; font-size: 18px;">⚠️ Confirm Permanent Delete</h3>
            <button onclick="closeVerifyPasswordModal(false)" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280;">&times;</button>
        </div>
        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
            <p style="color: #991b1b; font-size: 14px; margin: 0; font-weight: 600;">
                ⚠️ WARNING: Permanently delete ${count} record(s)?
            </p>
            <p style="color: #b91c1c; font-size: 13px; margin: 8px 0 0 0;">
                This action CANNOT be undone! All patient data will be permanently removed from the system.
            </p>
        </div>
        <p style="color: #374151; margin-bottom: 16px; font-size: 14px;">Enter admin password to confirm:</p>
        <input type="password" id="verify-password-input" 
            placeholder="Enter admin password"
            style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; margin-bottom: 16px; font-size: 14px; box-sizing: border-box;"
            autocomplete="off">
        <div style="display: flex; gap: 12px; justify-content: flex-end;">
            <button onclick="closeVerifyPasswordModal(false)" 
                style="padding: 10px 20px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer; font-size: 14px;">
                Cancel
            </button>
            <button onclick="submitVerifyPassword()" 
                style="padding: 10px 20px; background: #dc2626; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px;">
                Delete Forever
            </button>
        </div>
        <p id="verify-password-error" style="color: #dc2626; font-size: 13px; margin-top: 12px; display: none;"></p>
    `;
    
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    document.body.classList.add('modal-open');

    // Store callback
    window._verifyPasswordCallback = callback;

    // Enable enter key
    const input = document.getElementById('verify-password-input');
    input.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            submitVerifyPassword();
        }
    });
    input.focus();
}

function submitVerifyPassword() {
    const password = document.getElementById('verify-password-input').value;
    const errorMsg = document.getElementById('verify-password-error');
    
    if (!password) {
        errorMsg.textContent = 'Please enter password';
        errorMsg.style.display = 'block';
        return;
    }

    fetch('../../master/shared/verify_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'password=' + encodeURIComponent(password)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeVerifyPasswordModal(true);
        } else {
            errorMsg.textContent = data.message || 'Incorrect password';
            errorMsg.style.display = 'block';
            document.getElementById('verify-password-input').value = '';
            document.getElementById('verify-password-input').focus();
        }
    })
    .catch(() => {
        errorMsg.textContent = 'Failed to verify password';
        errorMsg.style.display = 'block';
    });
}

function closeVerifyPasswordModal(verified) {
    const modal = document.getElementById('verify-password-modal');
    if (modal) {
        modal.remove();
    }
    document.body.classList.remove('modal-open');
    if (window._verifyPasswordCallback) {
        window._verifyPasswordCallback(verified);
        window._verifyPasswordCallback = null;
    }
}

/**
 * Perform single action
 */
function singleAction(module, id, action) {
    if (action === 'view_conversation') {
        viewInquiryConversation(id);
    } else if (action === 'delete_forever') {
        showVerifyPasswordModal(1, (verified) => {
            if (verified) {
                performAction(module, action, [id]);
            }
        });
    } else if (action === 'restore') {
        showRestoreConfirmModal(module, id);
    }
}

/**
 * Show restore confirmation modal
 */
function showRestoreConfirmModal(module, id) {
    const moduleNames = module === 'patients' ? 'Patient' : (module === 'users' ? 'Employee' : 'Record');
    const moduleName = module === 'patients' ? 'patient record' : (module === 'users' ? 'employee' : 'record');
    
    // Remove existing modal if any
    const existingModal = document.getElementById('restore-modal');
    if (existingModal) existingModal.remove();
    
    // Create modal
    const overlay = document.createElement('div');
    overlay.id = 'restore-modal';
    overlay.className = 'modal-overlay active';
    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:99999;';
    
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.cssText = 'background:white;border-radius:12px;padding:30px;max-width:400px;width:90%;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,0.2);';
    modal.innerHTML = `
        <div style="margin-bottom: 20px;">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" width="48" height="48">
                <path d="M4.52185 7H7C7.55229 7 8 7.44772 8 8C8 8.55229 7.55228 9 7 9H3C1.89543 9 1 8.10457 1 7V3C1 2.44772 1.44772 2 2 2C2.55228 2 3 2.44772 3 3V5.6754C4.26953 3.8688 6.06062 2.47676 8.14852 1.69631C10.6633 0.756291 13.435 0.768419 15.9415 1.73041C18.448 2.69239 20.5161 4.53782 21.7562 6.91897C22.9963 9.30013 23.3228 12.0526 22.6741 14.6578C22.0254 17.263 20.4464 19.541 18.2345 21.0626C16.0226 22.5842 13.3306 23.2444 10.6657 22.9188C8.00083 22.5931 5.54702 21.3041 3.76664 19.2946C2.20818 17.5356 1.25993 15.3309 1.04625 13.0078C0.995657 12.4579 1.45216 12.0088 2.00445 12.0084C2.55673 12.0079 3.00351 12.4566 3.06526 13.0055C3.27138 14.8374 4.03712 16.5706 5.27027 17.9625C6.7255 19.605 8.73118 20.6586 10.9094 20.9247C13.0876 21.1909 15.288 20.6513 17.0959 19.4075C18.9039 18.1638 20.1945 16.3018 20.7247 14.1724C21.2549 12.043 20.9881 9.79319 19.9745 7.8469C18.9608 5.90061 17.2704 4.3922 15.2217 3.6059C13.173 2.8196 10.9074 2.80968 8.8519 3.57803C7.11008 4.22911 5.62099 5.40094 4.57993 6.92229C4.56156 6.94914 4.54217 6.97505 4.52185 7Z" fill="#10b981"/>
            </svg>
        </div>
        <h3 style="margin: 0 0 10px 0; color: #111827; font-size: 20px;">Restore ${moduleNames}?</h3>
        <p style="margin: 0 0 25px 0; color: #6b7280; font-size: 15px;">It will reappear in the main list.</p>
        <div style="display: flex; gap: 12px; justify-content: center;">
            <button onclick="closeRestoreModal()" style="padding: 12px 24px; background: #f3f4f6; color: #374151; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500;">Cancel</button>
            <button onclick="confirmRestoreById('${module}', '${id}')" style="padding: 12px 24px; background: #10b981; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500;">Restore</button>
        </div>
    `;
    
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';
}

function confirmRestoreById(module, id) {
    closeRestoreModal();
    performAction(module, 'restore', [id]);
}

function closeRestoreModal() {
    const modal = document.getElementById('restore-modal');
    if (modal) {
        modal.remove();
    }
    document.body.style.overflow = '';
}

function confirmRestore(module, id) {
    closeRestoreModal();
    performAction(module, 'restore', [id]);
}

/**
 * Archive meatball menu
 */
let archiveKebabDropdown = null;
let archiveKebabBackdrop = null;
let activeArchiveKebabButton = null;

function initArchiveKebabMenu() {
    archiveKebabDropdown = document.getElementById('archiveKebabDropdown');
    archiveKebabBackdrop = document.getElementById('archiveKebabBackdrop');

    if (!archiveKebabDropdown || !archiveKebabBackdrop) return;

    document.addEventListener('click', handleArchiveKebabToggle);
    archiveKebabDropdown.addEventListener('click', handleArchiveKebabAction);
    archiveKebabBackdrop.addEventListener('click', closeArchiveKebabDropdown);
    window.addEventListener('resize', closeArchiveKebabDropdown);
    window.addEventListener('scroll', closeArchiveKebabDropdown, true);
}

function handleArchiveKebabToggle(e) {
    const button = e.target.closest('.archive-kebab-btn');
    const isKebabButton = Boolean(button);

    if (!isKebabButton) {
        if (!e.target.closest('.archive-kebab-dropdown')) {
            closeArchiveKebabDropdown();
        }
        return;
    }

    e.preventDefault();
    e.stopPropagation();

    if (activeArchiveKebabButton === button) {
        closeArchiveKebabDropdown();
        return;
    }

    openArchiveKebabDropdown(button);
}

function openArchiveKebabDropdown(button) {
    if (!archiveKebabDropdown || !archiveKebabBackdrop) return;

    const module = button.dataset.module;
    const id = button.dataset.id;

    archiveKebabDropdown.innerHTML = getArchiveKebabMarkup(module, id);
    positionArchiveKebabDropdown(button);

    archiveKebabDropdown.classList.add('show');
    archiveKebabBackdrop.classList.add('show');
    button.classList.add('active');
    activeArchiveKebabButton = button;
}

function closeArchiveKebabDropdown() {
    if (archiveKebabDropdown) {
        archiveKebabDropdown.classList.remove('show');
        archiveKebabDropdown.innerHTML = '';
    }
    if (archiveKebabBackdrop) {
        archiveKebabBackdrop.classList.remove('show');
    }
    if (activeArchiveKebabButton) {
        activeArchiveKebabButton.classList.remove('active');
        activeArchiveKebabButton = null;
    }
}

function positionArchiveKebabDropdown(button) {
    const rect = button.getBoundingClientRect();
    const dropdownWidth = 180;
    const dropdownHeight = 140;
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

    archiveKebabDropdown.style.left = `${left}px`;
    archiveKebabDropdown.style.top = `${top}px`;
}

function getArchiveKebabMarkup(module, id) {
    let extra = '';
    if (module === 'inquiries') {
        extra = `
        <a href="#" data-action="view_conversation" data-module="${module}" data-id="${id}">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            View Conversation
        </a>`;
    }
    return extra + `
        <a href="#" data-action="restore" data-module="${module}" data-id="${id}">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>
            Restore
        </a>
        <a href="#" class="danger" data-action="delete_forever" data-module="${module}" data-id="${id}">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
            Delete Forever
        </a>
    `;
}

function handleArchiveKebabAction(e) {
    const link = e.target.closest('a[data-action]');
    if (!link) return;

    e.preventDefault();
    const action = link.dataset.action;
    const module = link.dataset.module;
    const id = link.dataset.id;

    closeArchiveKebabDropdown();

    if (!module || !id) return;

    singleAction(module, id, action);
}

/**
 * Perform action (restore or delete)
 */
function performAction(module, action, ids) {
    const formData = new URLSearchParams();
    formData.append('action', action);
    formData.append('module', module);
    ids.forEach(id => formData.append('ids[]', id));
    
    fetch('../../master/shared/archive_actions.php', {
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
            } else if (module === 'users') {
                loadArchivedUsers(currentPage.users);
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
