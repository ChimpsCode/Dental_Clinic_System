<?php
/**
 * Patient Records - Admin page for viewing all patient records
 */

$pageTitle = 'Patient Records';

try {
    require_once __DIR__ . '/../../config/database.php';
    
    // Fetch ALL non-archived patients (no server-side pagination — client-side handles it)
    $stmt = $pdo->prepare("
        SELECT p.*, 
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
               a.status as appointment_status,
               a.appointment_date,
               a.appointment_time,
               svc.name as service_name
        FROM patients p 
        LEFT JOIN (
            SELECT a1.*
            FROM appointments a1
            WHERE a1.id = (
                SELECT MAX(a2.id) FROM appointments a2 WHERE a2.patient_id = a1.patient_id
            )
        ) a ON p.id = a.patient_id
        LEFT JOIN services svc ON a.service_id = svc.id
        WHERE p.is_archived = 0
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalPatients = count($patients);
    
    // Calculate stats from ALL patients
    // Get patients with completed appointments
    $completedStmt = $pdo->query("
        SELECT COUNT(DISTINCT p.id) FROM patients p
        INNER JOIN appointments a ON p.id = a.patient_id
        WHERE p.is_archived = 0 AND a.status = 'Completed'
    ");
    $completedCount = $completedStmt->fetchColumn();
    
    // Get patients with pending appointments (status 1 = Pending, 2 = Confirmed)
    $pendingStmt = $pdo->query("
        SELECT COUNT(DISTINCT p.id) FROM patients p
        INNER JOIN appointments a ON p.id = a.patient_id
        WHERE p.is_archived = 0 AND a.status IN ('Scheduled', 'In Queue') AND a.appointment_date >= CURDATE()
    ");
    $pendingCount = $pendingStmt->fetchColumn();
    
    // Stats for new patients this month
    $newThisMonthStmt = $pdo->query("
        SELECT COUNT(*) FROM patients 
        WHERE is_archived = 0 AND created_at > DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $newThisMonth = $newThisMonthStmt->fetchColumn();
    
    // For backward compatibility
    $inQueue = $pendingCount;
    $scheduledCount = $completedCount;
    
} catch (Exception $e) {
    $patients = [];
    $totalPatients = 0;
    $inQueue = 0;
    $scheduledCount = 0;
    $completedCount = 0;
    $pendingCount = 0;
    $newThisMonth = 0;
}

require_once __DIR__ . '/../../includes/admin_layout_start.php';
?>
            <div class="content-main">
                <!-- Page Header -->
                
                <!-- Summary Stats Cards -->
                <div class="summary-cards" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 20px; margin-bottom: 0px;">
                    <div class="summary-card" style="background: white; border-radius: 12px; padding: 20px; border: 1px solid #e5e7eb; display: flex; align-items: center; gap: 16px;">
                        <div class="summary-icon" style="width: 48px; height: 48px; background: #dbeafe; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">👥</div>
                        <div class="summary-info">
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo $totalPatients; ?></h3>
                            <p style="font-size: 0.875rem; color: #6b7280; margin: 0;">Total Patients</p>
                        </div>
                    </div>
                    <div class="summary-card" style="background: white; border-radius: 12px; padding: 20px; border: 1px solid #e5e7eb; display: flex; align-items: center; gap: 16px;">
                        <div class="summary-icon" style="width: 48px; height: 48px; background: #fef3c7; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">⏰</div>
                        <div class="summary-info">
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo $inQueue; ?></h3>
                            <p style="font-size: 0.875rem; color: #6b7280; margin: 0;">In Queue</p>
                        </div>
                    </div>
                    <div class="summary-card" style="background: white; border-radius: 12px; padding: 20px; border: 1px solid #e5e7eb; display: flex; align-items: center; gap: 16px;">
                        <div class="summary-icon" style="width: 48px; height: 48px; background: #e0e7ff; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">📅</div>
                        <div class="summary-info">
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo $scheduledCount; ?></h3>
                            <p style="font-size: 0.875rem; color: #6b7280; margin: 0;">Scheduled</p>
                        </div>
                    </div>
                    <div class="summary-card" style="background: white; border-radius: 12px; padding: 20px; border: 1px solid #e5e7eb; display: flex; align-items: center; gap: 16px;">
                        <div class="summary-icon" style="width: 48px; height: 48px; background: #d1fae5; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">✓</div>
                        <div class="summary-info">
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo $newThisMonth; ?></h3>
                            <p style="font-size: 0.875rem; color: #6b7280; margin: 0;">New This Month</p>
                        </div>
                    </div>
                    <div class="summary-card" style="background: white; border-radius: 12px; padding: 20px; border: 1px solid #e5e7eb; display: flex; align-items: center; gap: 16px;">
                        <div class="summary-icon" style="width: 48px; height: 48px; background: #fee2e2; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">📋</div>
                        <div class="summary-info">
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo $totalPatients; ?></h3>
                            <p style="font-size: 0.875rem; color: #6b7280; margin: 0;">Registered Patients</p>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="search-filters">
                    <input type="text" class="search-input" placeholder="Search by name or phone..." id="searchInput">
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="Scheduled">Scheduled</option>
                        <option value="In Queue">In Queue</option>
                        <option value="Pending Payment">Pending Payment</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <!-- Patients Table -->
                <div class="table-container" style="height: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>First Name</th>
                                <th>Middle Name</th>
                                <th>Last Name</th>
                                <th>Contact</th>
                                <th>Type</th>
                                <th>Current Treatment</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="patientsTableBody">
                            <tr id="emptyRow"><td colspan="8" style="text-align:center;padding:60px;color:#6b7280;">No patients found</td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination - Below table (rendered by JS) -->
                <div id="pagination" class="pagination"></div>
            </div>

<script>
const allPatients = <?php echo json_encode($patients); ?>;
let currentPage = 1;
let perPage = 7;
let filteredPatients = [...allPatients];

const statusLabels = {'Scheduled':'Scheduled','In Queue':'In Queue','Pending Payment':'Pending Payment','Completed':'Completed','Cancelled':'Cancelled'};
const statusColors = {'Scheduled':'#dbeafe:#1d4ed8','In Queue':'#fef3c7:#92400e','Pending Payment':'#fed7aa:#c2410c','Completed':'#d1fae5:#065f46','Cancelled':'#fee2e2:#dc2626'};

// Portal Pattern: Move modals to body level
(function() {
    var modals = ['adminPatientModal', 'recordPaymentModal', 'paymentHistoryModal', 'setDueDateModal', 'appointmentModal'];
    modals.forEach(function(id) {
        var el = document.getElementById(id);
        if (el) document.body.appendChild(el);
    });
})();

document.getElementById('searchInput').addEventListener('input', applyFilters);
document.getElementById('statusFilter').addEventListener('change', applyFilters);

renderTable();

function applyFilters() {
    currentPage = 1;
    const query = document.getElementById('searchInput').value.trim().toLowerCase();
    const statusVal = document.getElementById('statusFilter').value;

    filteredPatients = allPatients.filter(p => {
        const haystack = ((p.first_name||'') + ' ' + (p.middle_name||'') + ' ' + (p.last_name||'') + ' ' + (p.phone||'')).toLowerCase();
        const matchesSearch = !query || haystack.includes(query);

        let matchesStatus = true;
        if (statusVal) {
            const s = (p.appointment_status || '');
            matchesStatus = (s === statusVal);
        }
        return matchesSearch && matchesStatus;
    });
    renderTable();
}

function renderTable() {
    const tbody = document.getElementById('patientsTableBody');
    const total = filteredPatients.length;
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    if (currentPage > totalPages) currentPage = totalPages;
    const start = (currentPage - 1) * perPage;
    const end = Math.min(start + perPage, total);
    const pageData = filteredPatients.slice(start, end);

    if (pageData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:60px;color:#6b7280;">No patients found</td></tr>';
        document.getElementById('pagination').innerHTML = '';
        return;
    }

    tbody.innerHTML = pageData.map(p => {
        const s = (p.appointment_status || '');
        const label = statusLabels[s] || '--';
        const [bg, text] = (statusColors[s] || '#f3f4f6:#6b7280').split(':');
        const phone = p.phone || 'N/A';
        const email = p.email || '';
        const svc = p.service_name || 'N/A';
        const pid = Number(p.id);
        const fn = escapeHtml(p.first_name || '');
        const mn = escapeHtml(p.middle_name || '');
        const ln = escapeHtml(p.last_name || '');
        const pt = escapeHtml(p.patient_type || 'New Patient');
        const ptColor = (p.patient_type === 'Returning Patient') ? '#0f766e' : '#0891b2';
        const ptBg = (p.patient_type === 'Returning Patient') ? '#f0fdfa' : '#ecfeff';

        return '<tr class="patient-row">' +
            '<td>' + fn + '</td>' +
            '<td>' + mn + '</td>' +
            '<td>' + ln + '</td>' +
            '<td><div class="patient-contact"><div>' + escapeHtml(phone) + '</div><div>' + escapeHtml(email) + '</div></div></td>' +
            '<td><span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:0.78rem;font-weight:700;background:' + ptBg + ';color:' + ptColor + ';">' + pt + '</span></td>' +
            '<td>' + escapeHtml(svc) + '</td>' +
            '<td><span class="status-badge" style="background:' + bg + ';color:' + text + ';">' + label + '</span></td>' +
            '<td><div class="patient-kebab-menu"><button class="patient-kebab-btn" data-patient-id="' + pid + '">' +
            '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">' +
            '<circle cx="12" cy="6" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="18" r="2"/></svg></button></div></td>' +
            '</tr>';
    }).join('');

    renderPagination(total, totalPages);
}

function renderPagination(total, totalPages) {
    const el = document.getElementById('pagination');
    if (total === 0) { el.innerHTML = ''; return; }

    const showingStart = (currentPage - 1) * perPage + 1;
    const showingEnd = Math.min(currentPage * perPage, total);

    let html = '<span class="pagination-info">Showing ' + showingStart + '-' + showingEnd + ' of ' + total + ' patients</span><div class="pagination-buttons">';

    if (currentPage > 1) {
        html += '<a href="javascript:void(0)" onclick="changePage(' + (currentPage - 1) + ')" class="pagination-btn">Previous</a>';
    } else {
        html += '<button class="pagination-btn" disabled>Previous</button>';
    }

    let startP = Math.max(1, currentPage - 2);
    let endP = Math.min(totalPages, startP + 4);
    if (endP - startP + 1 < 5) startP = Math.max(1, endP - 4);

    if (startP > 1) {
        html += '<a href="javascript:void(0)" onclick="changePage(1)" class="pagination-btn">1</a>';
        if (startP > 2) html += '<span class="pagination-ellipsis">...</span>';
    }

    for (let i = startP; i <= endP; i++) {
        if (i === currentPage) {
            html += '<button class="pagination-btn active">' + i + '</button>';
        } else {
            html += '<a href="javascript:void(0)" onclick="changePage(' + i + ')" class="pagination-btn">' + i + '</a>';
        }
    }

    if (endP < totalPages) {
        if (endP < totalPages - 1) html += '<span class="pagination-ellipsis">...</span>';
        html += '<a href="javascript:void(0)" onclick="changePage(' + totalPages + ')" class="pagination-btn">' + totalPages + '</a>';
    }

    if (currentPage < totalPages) {
        html += '<a href="javascript:void(0)" onclick="changePage(' + (currentPage + 1) + ')" class="pagination-btn">Next</a>';
    } else {
        html += '<button class="pagination-btn" disabled>Next</button>';
    }

    html += '</div>';
    el.innerHTML = html;
}

function changePage(page) {
    currentPage = page;
    renderTable();
}

function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Patient Kebab Menu Functions
let patientKebabDropdown = null;
let patientKebabBackdrop = null;
let patientActiveButton = null;

function createPatientKebabDropdown() {
    patientKebabDropdown = document.createElement('div');
    patientKebabDropdown.className = 'patient-kebab-dropdown-portal';
    patientKebabDropdown.id = 'patientKebabDropdownPortal';
    document.body.appendChild(patientKebabDropdown);

    patientKebabBackdrop = document.createElement('div');
    patientKebabBackdrop.className = 'patient-kebab-backdrop';
    patientKebabBackdrop.id = 'patientKebabBackdrop';
    document.body.appendChild(patientKebabBackdrop);

    patientKebabBackdrop.addEventListener('click', closePatientKebabDropdown);
}

function getPatientMenuItems(patientId) {
    return `
        <a href="javascript:void(0)" data-action="view" data-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            View
        </a>
        <a href="javascript:void(0)" data-action="edit" data-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
            Edit Info
        </a>
        <a href="../shared/quick_session.php?patient_id=${patientId}" data-action="session" data-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            New Session
        </a>
        <a href="javascript:void(0)" data-action="appointment" data-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            Add Appointment
        </a>
        <a href="javascript:void(0)" data-action="delete" data-id="${patientId}" style="color: #dc2626;">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20.54 5.23l-1.39-1.68C18.88 3.21 18.47 3 18 3H6c-.47 0-.88.21-1.16.55L3.46 5.23C3.17 5.57 3 6.02 3 6.5V19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6.5c0-.48-.17-.93-.46-1.27zM12 17.5L6.5 12H10v-2h4v2h3.5L12 17.5z"/>
            </svg>
            Archive
        </a>
    `;
}

function positionPatientKebabDropdown(button) {
    if (!patientKebabDropdown || !button) return;

    const rect = button.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    const padding = 15;
    const dropdownWidth = 200;
    
    let left = rect.right + 5;
    let top = rect.bottom + 8;

    if (left + dropdownWidth > viewportWidth - padding) {
        left = rect.left - dropdownWidth - 5;
    }
    
    if (left < padding) {
        left = padding;
    }
    
    if (top + 150 > viewportHeight - padding) {
        top = rect.top - 150 - 8;
    }
    
    if (top < padding) {
        top = padding;
    }

    patientKebabDropdown.style.left = left + 'px';
    patientKebabDropdown.style.top = top + 'px';
}

function openPatientKebabDropdown(button) {
    if (!patientKebabDropdown) {
        createPatientKebabDropdown();
    }

    const patientId = button.dataset.patientId;

    patientKebabDropdown.innerHTML = getPatientMenuItems(patientId);
    positionPatientKebabDropdown(button);

    patientKebabDropdown.classList.add('show');
    patientKebabBackdrop.classList.add('show');
    patientActiveButton = button;
    button.classList.add('active');

    patientKebabDropdown.addEventListener('click', handlePatientKebabClick);
}

function closePatientKebabDropdown() {
    if (patientKebabDropdown) {
        patientKebabDropdown.classList.remove('show');
        patientKebabDropdown.innerHTML = '';
        patientKebabDropdown.style.display = '';
        patientKebabDropdown.style.visibility = '';
        patientKebabDropdown.style.left = '';
        patientKebabDropdown.style.top = '';
        patientKebabDropdown.removeEventListener('click', handlePatientKebabClick);
    }
    if (patientKebabBackdrop) {
        patientKebabBackdrop.classList.remove('show');
    }
    if (patientActiveButton) {
        patientActiveButton.classList.remove('active');
        patientActiveButton = null;
    }
}

function handlePatientKebabClick(e) {
    const link = e.target.closest('a[data-action]');
    if (!link) return;

    e.preventDefault();
    e.stopPropagation();

    const action = link.dataset.action;
    const id = parseInt(link.dataset.id);

    closePatientKebabDropdown();

switch(action) {
        case 'view':
            viewPatientDetails(id);
            break;
        case 'edit':
            editPatientInfo(id);
            break;
        case 'session':
            window.location.href = '../shared/quick_session.php?patient_id=' + id;
            break;
        case 'appointment':
            openAddAppointmentModal(id);
            break;
        case 'delete':
            deletePatient(id);
            break;
    }
}

document.addEventListener('click', function(e) {
    const button = e.target.closest('.patient-kebab-btn');
    if (button) {
        e.preventDefault();
        e.stopPropagation();

        if (patientActiveButton === button && patientKebabDropdown && patientKebabDropdown.classList.contains('show')) {
            closePatientKebabDropdown();
        } else {
            if (patientActiveButton) {
                patientActiveButton.classList.remove('active');
            }
            openPatientKebabDropdown(button);
        }
        return;
    }

    if (!e.target.closest('.patient-kebab-dropdown-portal')) {
        closePatientKebabDropdown();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (patientKebabDropdown && patientKebabDropdown.classList.contains('show')) {
            closePatientKebabDropdown();
        }
    }
});

window.addEventListener('resize', function() {
    if (patientKebabDropdown && patientKebabDropdown.classList.contains('show') && patientActiveButton) {
        positionPatientKebabDropdown(patientActiveButton);
    }
});

// View Patient Details - Modal
function viewPatientDetails(patientId) {
    closePatientKebabDropdown();

    const modalContent = document.getElementById('patientModalContent');
    document.getElementById('adminModalPatientName').innerText = 'Loading...';
    document.getElementById('adminPatientModal').style.display = 'flex';
    modalContent.innerHTML = '<div style="text-align:center;padding:40px;color:#6b7280;">Loading patient records...</div>';

    fetch('../user-patient/patient_record_details.php?id=' + patientId)
    .then(response => {
        if (!response.ok) throw new Error('Network error');
        return response.json();
    })
    .then(data => {
        if (!data.success) {
            modalContent.innerHTML = '<div style="text-align:center;padding:40px;color:#dc2626;"><p>Error: ' + (data.message || 'Unable to load patient details') + '</p><button onclick="closeAdminPatientModal()" style="margin-top:16px;padding:10px 20px;background:#6b7280;color:white;border:none;border-radius:8px;cursor:pointer;">Close</button></div>';
            return;
        }

        const p = data.patient;
        const m = data.medical_history || {};
        const dentalHistory = data.dental_history || [];
        const q = data.queue_item;
        const b = data.billing || {};
        const payments = data.payments || [];

        const allergies = m.allergies || 'None';
        const medications = m.current_medications || 'None';
        const conditions = m.medical_conditions || 'None';

        const billingId = b.id || '0';
        const totalAmount = parseFloat(b.total_amount || 0);
        const paidAmount = parseFloat(b.paid_amount || 0);
        const balance = parseFloat(b.balance || 0);
        const payStatus = (b.payment_status || 'unpaid').toLowerCase();
        const isPaid = payStatus === 'paid';
        const dueDate = b.due_date || null;

        document.getElementById('adminModalPatientName').innerText = (p.first_name || '') + ' ' + (p.last_name || '');

        const hasMedicalAlert = allergies === 'Yes' ||
                               conditions.toLowerCase().includes('diabetes') ||
                               conditions.toLowerCase().includes('heart') ||
                               conditions.toLowerCase().includes('blood pressure') ||
                               conditions.toLowerCase().includes('asthma');

        modalContent.innerHTML = `
            <div class="patient-info-grid">
                <div class="patient-info-item">
                    <div class="patient-info-label">First Name</div>
                    <div class="patient-info-value">${p.first_name || 'N/A'}</div>
                </div>
                <div class="patient-info-item">
                    <div class="patient-info-label">Middle Name</div>
                    <div class="patient-info-value">${p.middle_name || ''}</div>
                </div>
                <div class="patient-info-item">
                    <div class="patient-info-label">Last Name</div>
                    <div class="patient-info-value">${p.last_name || 'N/A'}</div>
                </div>
                <div class="patient-info-item">
                    <div class="patient-info-label">Suffix</div>
                    <div class="patient-info-value">${p.suffix || ''}</div>
                </div>
                <div class="patient-info-item">
                    <div class="patient-info-label">Age</div>
                    <div class="patient-info-value">${p.age || 'N/A'} years</div>
                </div>
                <div class="patient-info-item">
                    <div class="patient-info-label">Gender</div>
                    <div class="patient-info-value">${p.gender || 'N/A'}</div>
                </div>
                <div class="patient-info-item">
                    <div class="patient-info-label">Phone</div>
                    <div class="patient-info-value">${p.phone || 'N/A'}</div>
                </div>
                <div class="patient-info-item">
                    <div class="patient-info-label">Email</div>
                    <div class="patient-info-value">${p.email || 'N/A'}</div>
                </div>
                <div class="patient-info-item" style="grid-column:1/-1;">
                    <div class="patient-info-label">Address</div>
                    <div class="patient-info-value">${p.street_address || 'N/A'} ${p.city ? ', ' + p.city : ''} ${p.province ? ', ' + p.province : ''}</div>
                </div>
            </div>

            ${hasMedicalAlert ? `
            <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:20px;margin-bottom:20px;">
                <div style="color:#dc2626;font-weight:600;margin-bottom:12px;display:flex;align-items:center;gap:8px;">⚠️ Medical Alert - Important for Treatment</div>
                <div class="patient-info-grid">
                    <div class="patient-info-item">
                        <div class="patient-info-label">Allergies</div>
                        <div class="patient-info-value" style="${allergies === 'Yes' ? 'color:#dc2626;' : ''}">${allergies}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Diabetes</div>
                        <div class="patient-info-value" style="${conditions.toLowerCase().includes('diabetes') ? 'color:#dc2626;' : ''}">${conditions.toLowerCase().includes('diabetes') ? 'Yes' : 'No'}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Heart Disease</div>
                        <div class="patient-info-value" style="${conditions.toLowerCase().includes('heart') ? 'color:#dc2626;' : ''}">${conditions.toLowerCase().includes('heart') ? 'Yes' : 'No'}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">High Blood Pressure</div>
                        <div class="patient-info-value" style="${conditions.toLowerCase().includes('blood pressure') ? 'color:#dc2626;' : ''}">${conditions.toLowerCase().includes('blood pressure') ? 'Yes' : 'No'}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Asthma</div>
                        <div class="patient-info-value" style="${conditions.toLowerCase().includes('asthma') ? 'color:#dc2626;' : ''}">${conditions.toLowerCase().includes('asthma') ? 'Yes' : 'No'}</div>
                    </div>
                    <div class="patient-info-item" style="grid-column:1/-1;">
                        <div class="patient-info-label">Current Medications</div>
                        <div class="patient-info-value">${medications}</div>
                    </div>
                </div>
            </div>
            ` : ''}

            ${q ? `
            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:20px;margin-bottom:20px;">
                <h3 style="font-size:1rem;font-weight:600;color:#1e40af;margin-bottom:16px;">📋 Service Requested</h3>
                <div class="patient-info-grid">
                    <div class="patient-info-item">
                        <div class="patient-info-label">Treatment Type</div>
                        <div class="patient-info-value">${q.treatment_type || 'Consultation'}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Selected Teeth</div>
                        <div class="patient-info-value">${q.teeth_numbers || 'None specified'}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Status</div>
                        <div class="patient-info-value">
                            <span style="background:${q.status === 'in_procedure' ? '#dcfce7' : q.status === 'waiting' ? '#fef3c7' : '#f3f4f6'};color:${q.status === 'in_procedure' ? '#15803d' : q.status === 'waiting' ? '#d97706' : '#6b7280'};padding:4px 12px;border-radius:9999px;font-size:0.85rem;">
                                ${q.status ? q.status.replace('_', ' ').toUpperCase() : 'NONE'}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            ` : ''}

            ${billingId !== '0' ? `
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:20px;margin-bottom:20px;">
                <h3 style="font-size:1rem;font-weight:600;color:#166534;margin-bottom:16px;">💰 Billing Summary</h3>
                <div class="patient-info-grid">
                    <div class="patient-info-item">
                        <div class="patient-info-label">Total Amount</div>
                        <div class="patient-info-value">P${totalAmount.toFixed(2)}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Paid Amount</div>
                        <div class="patient-info-value">P${paidAmount.toFixed(2)}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Balance</div>
                        <div class="patient-info-value" style="color:${balance > 0 ? '#dc2626' : '#16a34a'};font-weight:600;">P${balance.toFixed(2)}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Payment Status</div>
                        <div class="patient-info-value">${formatPayStatus(payStatus)}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Due Date</div>
                        <div class="patient-info-value">${dueDate ? formatDueDate(dueDate) : '<span style="color:#9ca3af;">Not set</span>'}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Billing Date</div>
                        <div class="patient-info-value">${b.billing_date || 'N/A'}</div>
                    </div>
                </div>
                <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;">
                    <button onclick="openPaymentHistory('${billingId}')" style="padding:8px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;cursor:pointer;font-size:0.85rem;color:#166534;font-weight:500;display:flex;align-items:center;gap:6px;">
                        📋 Payment History
                    </button>
                    ${!isPaid ? `
                    <button onclick="openRecordPayment(${p.id}, '${billingId}', ${totalAmount}, ${paidAmount}, ${balance})" style="padding:8px 16px;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;cursor:pointer;font-size:0.85rem;color:#92400e;font-weight:500;display:flex;align-items:center;gap:6px;">
                        💵 Record Payment
                    </button>
                    <button onclick="openSetDueDate('${billingId}')" style="padding:8px 16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;cursor:pointer;font-size:0.85rem;color:#1e40af;font-weight:500;display:flex;align-items:center;gap:6px;">
                        📅 Set Due Date
                    </button>
                    ` : ''}
                </div>
            </div>
            ` : `
            <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:20px;margin-bottom:20px;">
                <h3 style="font-size:1rem;font-weight:600;color:#6b7280;margin-bottom:16px;">💰 Billing</h3>
                <p style="color:#9ca3af;text-align:center;padding:12px;">No billing record found for this patient.</p>
            </div>
            `}

            <div style="background:#f9fafb;border-radius:12px;padding:20px;margin-bottom:20px;">
                <h3 style="font-size:1rem;font-weight:600;color:#374151;margin-bottom:16px;">📜 Dental History - Past Treatments</h3>
                ${dentalHistory.length > 0 ? `
                    <div style="display:flex;flex-direction:column;gap:12px;">
                        ${dentalHistory.map(function(tx) {
                            var txDate = tx.completed_at ? new Date(tx.completed_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
                            return '<div style="background:white;border:1px solid #e5e7eb;border-radius:10px;padding:16px;">' +
                                '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">' +
                                '<div><div style="font-weight:600;color:#111827;font-size:1rem;">' + (tx.treatment_type || 'Treatment') + '</div>' +
                                '<div style="font-size:0.85rem;color:#6b7280;margin-top:4px;">🦷 Teeth: ' + (tx.teeth_numbers || 'N/A') + '</div></div>' +
                                '<div style="font-size:0.8rem;color:#6b7280;">' + txDate + '</div></div></div>';
                        }).join('')}
                    </div>
                ` : '<p style="color:#6b7280;text-align:center;padding:20px;background:#f3f4f6;border-radius:8px;">No previous treatment records found.</p>'}
            </div>

            <div style="margin-top:24px;padding-top:20px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;">
                <button onclick="closeAdminPatientModal()" class="btn-cancel" style="padding:10px 24px;background:#f3f4f6;border:none;border-radius:8px;cursor:pointer;font-weight:500;">Close</button>
            </div>
        `;
    })
    .catch(function(error) {
        modalContent.innerHTML = '<div style="text-align:center;padding:40px;color:#dc2626;"><p>Error loading patient details</p><button onclick="closeAdminPatientModal()" style="margin-top:16px;padding:10px 20px;background:#6b7280;color:white;border:none;border-radius:8px;cursor:pointer;">Close</button></div>';
    });
}

function closeAdminPatientModal() {
    document.getElementById('adminPatientModal').style.display = 'none';
}

function formatPayStatus(status) {
    var s = (status || 'unpaid').toLowerCase();
    if (s === 'paid') return '<span style="background:#d1fae5;color:#065f46;padding:4px 12px;border-radius:9999px;font-size:0.8rem;font-weight:600;">Paid</span>';
    if (s === 'partial') return '<span style="background:#fef3c7;color:#92400e;padding:4px 12px;border-radius:9999px;font-size:0.8rem;font-weight:600;">Partial</span>';
    return '<span style="background:#fee2e2;color:#dc2626;padding:4px 12px;border-radius:9999px;font-size:0.8rem;font-weight:600;">Unpaid</span>';
}

function formatDueDate(dateStr) {
    if (!dateStr) return '<span style="color:#9ca3af;">Not set</span>';
    var d = new Date(dateStr);
    var today = new Date();
    today.setHours(0,0,0,0);
    d.setHours(0,0,0,0);
    var diff = Math.ceil((d - today) / (1000*60*60*24));
    var formatted = d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
    if (diff < 0) return '<span style="color:#dc2626;font-weight:600;">' + formatted + ' (Overdue by ' + Math.abs(diff) + 'd)</span>';
    if (diff === 0) return '<span style="color:#d97706;font-weight:600;">' + formatted + ' (Due Today)</span>';
    if (diff <= 7) return '<span style="color:#d97706;">' + formatted + ' (' + diff + 'd left)</span>';
    return formatted;
}

function openRecordPayment(patientId, billingId, totalAmount, paidAmount, balance) {
    var recordModal = document.getElementById('recordPaymentModal');
    document.getElementById('recordPaymentPatientId').value = patientId || 0;
    document.getElementById('recordPaymentBillingId').value = billingId || 0;
    document.getElementById('recordPaymentTotalAmount').textContent = 'P' + parseFloat(totalAmount || 0).toFixed(2);
    document.getElementById('recordPaymentPaidSoFar').textContent = 'P' + parseFloat(paidAmount || 0).toFixed(2);
    document.getElementById('recordPaymentBalance').textContent = 'P' + parseFloat(balance || 0).toFixed(2);
    document.getElementById('recordPaymentAmount').value = balance || 0;
    document.getElementById('recordPaymentMethod').value = 'cash';
    recordModal.style.display = 'flex';
}

function closeRecordPaymentModal() {
    document.getElementById('recordPaymentModal').style.display = 'none';
}

function confirmRecordPayment() {
    var billingId = document.getElementById('recordPaymentBillingId').value;
    var patientId = document.getElementById('recordPaymentPatientId').value;
    var amount = document.getElementById('recordPaymentAmount').value;
    var paymentMethod = document.getElementById('recordPaymentMethod').value;

    if (!amount || parseFloat(amount) <= 0) {
        alert('Please enter a valid payment amount.');
        return;
    }

    fetch('../../master/shared/billing_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'record_payment',
            billing_id: billingId,
            queue_id: 0,
            patient_id: patientId,
            amount: amount,
            payment_method: paymentMethod
        })
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            closeRecordPaymentModal();
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(function(error) {
        alert('Error recording payment. Please try again.');
    });
}

function openPaymentHistory(billingId) {
    if (!billingId || billingId === '0') {
        alert('No billing record found for this patient.');
        return;
    }

    var modal = document.getElementById('paymentHistoryModal');
    var tbody = document.getElementById('paymentHistoryBody');
    var empty = document.getElementById('paymentHistoryEmpty');
    modal.style.display = 'flex';
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:2rem;color:#6b7280;">Loading...</td></tr>';
    empty.style.display = 'none';

    fetch('../../master/shared/billing_actions.php?action=get_payments&billing_id=' + encodeURIComponent(billingId))
        .then(function(response) { return response.json(); })
        .then(function(data) {
            tbody.innerHTML = '';
            if (data.success && data.payments && data.payments.length > 0) {
                empty.style.display = 'none';
                data.payments.forEach(function(p) {
                    var row = document.createElement('tr');
                    var methodLabel = (p.payment_method || 'Cash').charAt(0).toUpperCase() + (p.payment_method || 'Cash').slice(1);
                    row.innerHTML = '<td>' + (p.payment_date || 'N/A') + '</td><td><strong>P' + parseFloat(p.amount || 0).toFixed(2) + '</strong></td><td>' + methodLabel + '</td><td>' + (p.reference_number || '-') + '</td>';
                    tbody.appendChild(row);
                });
            } else {
                empty.style.display = 'block';
            }
        })
        .catch(function(error) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:2rem;color:#dc2626;">Error loading payment history.</td></tr>';
        });
}

function closePaymentHistoryModal() {
    document.getElementById('paymentHistoryModal').style.display = 'none';
}

function openSetDueDate(billingId) {
    if (!billingId || billingId === '0') {
        alert('No billing record found for this patient.');
        return;
    }

    var modal = document.getElementById('setDueDateModal');
    document.getElementById('setDueDateBillingId').value = billingId;
    document.getElementById('setDueDateInput').value = '';

    fetch('../../master/shared/billing_actions.php?action=get_billing&billing_id=' + encodeURIComponent(billingId))
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success && data.billing) {
                var dueDate = data.billing.due_date || '';
                if (dueDate) {
                    document.getElementById('setDueDateInput').value = dueDate;
                } else {
                    var future = new Date();
                    future.setDate(future.getDate() + 30);
                    document.getElementById('setDueDateInput').value = future.toISOString().split('T')[0];
                }
            } else {
                var future = new Date();
                future.setDate(future.getDate() + 30);
                document.getElementById('setDueDateInput').value = future.toISOString().split('T')[0];
            }
        })
        .catch(function() {
            var future = new Date();
            future.setDate(future.getDate() + 30);
            document.getElementById('setDueDateInput').value = future.toISOString().split('T')[0];
        });

    modal.style.display = 'flex';
}

function closeSetDueDateModal() {
    document.getElementById('setDueDateModal').style.display = 'none';
}

function confirmSetDueDate() {
    var billingId = document.getElementById('setDueDateBillingId').value;
    var dueDate = document.getElementById('setDueDateInput').value;

    if (!dueDate) {
        alert('Please select a due date.');
        return;
    }

    fetch('../../master/shared/billing_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'set_due_date',
            billing_id: billingId,
            due_date: dueDate
        })
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            closeSetDueDateModal();
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(function(error) {
        alert('Error setting due date. Please try again.');
    });
}

// Edit Patient Info - Modal
function editPatientInfo(patientId) {
    const patient = allPatients.find(p => p.id == patientId);
    if (!patient) return;
    
    const fullName = `${patient.first_name || ''} ${patient.middle_name || ''} ${patient.last_name || ''} ${patient.suffix || ''}`.trim();
    
    // Remove existing modal
    const existingModal = document.getElementById('edit-patient-modal');
    if (existingModal) existingModal.remove();
    
    const overlay = document.createElement('div');
    overlay.id = 'edit-patient-modal';
    overlay.className = 'modal-overlay active';
    
    overlay.innerHTML = `
        <div class="modal" style="max-width: 550px; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e5e7eb; padding-bottom: 16px;">
                <h3 style="margin: 0; color: #111827; font-size: 18px;">✏️ Edit Patient Information</h3>
                <button onclick="closeEditPatientModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280;">&times;</button>
            </div>
            
            <form id="edit-patient-form">
                <input type="hidden" name="patient_id" value="${patient.id}">
                
                <!-- Name Section -->
                <div style="margin-bottom: 20px;">
                    <h4 style="margin: 0 0 12px 0; color: #374151; font-size: 14px; font-weight: 600;">Personal Information</h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 4px;">First Name *</label>
                            <input type="text" name="first_name" value="${patient.first_name || ''}" required
                                style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 4px;">Middle Name</label>
                            <input type="text" name="middle_name" value="${patient.middle_name || ''}"
                                style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 4px;">Last Name *</label>
                            <input type="text" name="last_name" value="${patient.last_name || ''}" required
                                style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 4px;">Suffix</label>
                            <input type="text" name="suffix" value="${patient.suffix || ''}" placeholder="Jr., Sr., III, etc."
                                style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                        </div>
                    </div>
                </div>
                
                <!-- Contact Section -->
                <div style="margin-bottom: 20px;">
                    <h4 style="margin: 0 0 12px 0; color: #374151; font-size: 14px; font-weight: 600;">Contact Information</h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 4px;">Phone</label>
                            <input type="text" name="phone" value="${patient.phone || ''}" placeholder="09XXXXXXXXX"
                                style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 4px;">Email</label>
                            <input type="email" name="email" value="${patient.email || ''}" placeholder="email@example.com"
                                style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                        </div>
                    </div>
                </div>
                
                <!-- Additional Info -->
                <div style="margin-bottom: 20px;">
                    <h4 style="margin: 0 0 12px 0; color: #374151; font-size: 14px; font-weight: 600;">Additional Information</h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div>
                            <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 4px;">Date of Birth</label>
                            <input type="date" name="date_of_birth" value="${patient.date_of_birth || ''}"
                                style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 4px;">Gender</label>
                            <select name="gender" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                                <option value="">-- Select --</option>
                                <option value="Male" ${patient.gender === 'Male' ? 'selected' : ''}>Male</option>
                                <option value="Female" ${patient.gender === 'Female' ? 'selected' : ''}>Female</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Buttons -->
                <div style="display: flex; gap: 12px; justify-content: flex-end; border-top: 1px solid #e5e7eb; padding-top: 16px; margin-top: 8px;">
                    <button type="button" onclick="closeEditPatientModal()" 
                        style="padding: 10px 20px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer; font-size: 14px;">
                        Cancel
                    </button>
                    <button type="submit" 
                        style="padding: 10px 20px; background: #0ea5e9; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px;">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(overlay);
    document.body.classList.add('modal-open');
    
    // Handle form submit
    document.getElementById('edit-patient-form').addEventListener('submit', function(e) {
        e.preventDefault();
        savePatientChanges();
    });
}

function closeEditPatientModal() {
    const modal = document.getElementById('edit-patient-modal');
    if (modal) modal.remove();
    document.body.classList.remove('modal-open');
}

function savePatientChanges() {
    const form = document.getElementById('edit-patient-form');
    const formData = new FormData(form);
    
    fetch('../../master/shared/update_patient_info.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Patient information updated successfully!');
            closeEditPatientModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Failed to save changes');
    });
}

// Archive Patient (Soft Delete) - with modal confirmation
function deletePatient(patientId) {
    const patient = allPatients.find(p => p.id == patientId);
    if (!patient) return;
    
    const fullName = `${patient.first_name || ''} ${patient.middle_name || ''} ${patient.last_name || ''} ${patient.suffix || ''}`.trim();
    
    // Create modal
    const existingModal = document.getElementById('archive-patient-modal');
    if (existingModal) existingModal.remove();
    
    const overlay = document.createElement('div');
    overlay.id = 'archive-patient-modal';
    overlay.className = 'modal-overlay active';
    overlay.innerHTML = `
        <div class="modal" style="max-width: 400px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #92400e; font-size: 18px;">📦 Archive Patient</h3>
                <button onclick="closeArchivePatientModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280;">&times;</button>
            </div>
            <div style="background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <p style="color: #92400e; font-size: 14px; margin: 0; font-weight: 600;">
                    Archive this patient?
                </p>
                <p style="color: #b45309; font-size: 13px; margin: 8px 0 0 0;">
                    Patient: <strong>${fullName || 'Unknown'}</strong><br>
                    Phone: ${patient.phone || 'N/A'}
                </p>
                <p style="color: #a16207; font-size: 13px; margin: 8px 0 0 0;">
                    You can restore archived patients from the Archive page.
                </p>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button onclick="closeArchivePatientModal()" 
                    style="padding: 10px 20px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer; font-size: 14px;">
                    Cancel
                </button>
                <button onclick="confirmArchivePatient(${patientId})" 
                    style="padding: 10px 20px; background: #92400e; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px;">
                    Archive
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);
    document.body.classList.add('modal-open');
}

function closeArchivePatientModal() {
    const modal = document.getElementById('archive-patient-modal');
    if (modal) modal.remove();
    document.body.classList.remove('modal-open');
}

function confirmArchivePatient(patientId) {
    closeArchivePatientModal();
    
    fetch('../../master/user-patient/patient_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=archive&patient_id=' + patientId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Patient archived successfully');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to archive patient');
    });
}

// Add Appointment Modal Functions
let selectedPatientId = null;

function openAddAppointmentModal(patientId) {
    const patient = allPatients.find(p => p.id == patientId);
    
    if (patient) {
        selectedPatientId = patientId;
        
        const fullName = ((patient.first_name || '') + ' ' + (patient.middle_name || '') + ' ' + (patient.last_name || '')).trim();
        
        document.getElementById('appointmentPatientName').textContent = fullName.trim();
        document.getElementById('appointmentPatientId').value = patientId;
        document.getElementById('appointmentPatientPhone').value = patient.phone || '';
        
        document.getElementById('appointmentModal').classList.add('active');
    } else {
        alert('Patient not found');
    }
}

function closeAddAppointmentModal() {
    document.getElementById('appointmentModal').classList.remove('active');
}

var appointmentModal_el = document.getElementById('appointmentModal');
if (appointmentModal_el) {
    appointmentModal_el.addEventListener('click', function(e) {
        if (e.target === this || e.target.classList.contains('modal-backdrop') || e.target.closest('.modal-container') === e.target) {
            closeAddAppointmentModal();
        }
    });
}

var adminPatientModal_el = document.getElementById('adminPatientModal');
if (adminPatientModal_el) {
    adminPatientModal_el.addEventListener('click', function(e) {
        if (e.target === this) closeAdminPatientModal();
    });
}

var recordPaymentModal_el = document.getElementById('recordPaymentModal');
if (recordPaymentModal_el) {
    recordPaymentModal_el.addEventListener('click', function(e) {
        if (e.target === this) closeRecordPaymentModal();
    });
}

var paymentHistoryModal_el = document.getElementById('paymentHistoryModal');
if (paymentHistoryModal_el) {
    paymentHistoryModal_el.addEventListener('click', function(e) {
        if (e.target === this) closePaymentHistoryModal();
    });
}

var setDueDateModal_el = document.getElementById('setDueDateModal');
if (setDueDateModal_el) {
    setDueDateModal_el.addEventListener('click', function(e) {
        if (e.target === this) closeSetDueDateModal();
    });
}

var addAppointmentForm_el = document.getElementById('addAppointmentForm');
if (addAppointmentForm_el) {
    addAppointmentForm_el.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('../../master/user-patient/process_patient_appointment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Appointment scheduled successfully!');
            closeAddAppointmentModal();
            location.reload();
        } else {
            alert(data.message || 'Error scheduling appointment');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error scheduling appointment');
    });
});
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var aptM = document.getElementById('appointmentModal');
        if (aptM && aptM.classList.contains('active')) {
            closeAddAppointmentModal();
        }
        var admM = document.getElementById('adminPatientModal');
        if (admM && admM.style.display === 'flex') {
            closeAdminPatientModal();
        }
        var rpM = document.getElementById('recordPaymentModal');
        if (rpM && rpM.style.display === 'flex') {
            closeRecordPaymentModal();
        }
        var phM = document.getElementById('paymentHistoryModal');
        if (phM && phM.style.display === 'flex') {
            closePaymentHistoryModal();
        }
        var sdM = document.getElementById('setDueDateModal');
        if (sdM && sdM.style.display === 'flex') {
            closeSetDueDateModal();
        }
    }
});
</script>

<style>
/* Table Column Alignment */
.data-table th,
.data-table td {
    vertical-align: middle;
    text-align: left;
}
.data-table th:first-child,
.data-table td:first-child {
    text-align: left;
}
.data-table th:nth-child(2),
.data-table td:nth-child(2),
.data-table th:nth-child(3),
.data-table td:nth-child(3),
.data-table th:nth-child(4),
.data-table td:nth-child(4),
.data-table th:nth-child(5),
.data-table td:nth-child(5),
.data-table th:nth-child(6),
.data-table td:nth-child(6) {
    text-align: center;
}
.data-table th:last-child,
.data-table td:last-child {
    text-align: center;
    width: 60px;
}

/* Patient Contact */
.patient-contact {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

/* Status Badge Styles */
.status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}

.status-badge.waiting {
    background: #fef3c7;
    color: #92400e;
}

.status-badge.in-procedure {
    background: #dbeafe;
    color: #1e40af;
}

.status-badge.completed {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.on-hold {
    background: #fef3c7;
    color: #92400e;
}

.status-badge.scheduled {
    background: #e0e7ff;
    color: #3730a3;
}

/* Patient Cell Styles */
.patient-info-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.patient-avatar {
    width: 40px;
    height: 40px;
    background: #e5e7eb;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6b7280;
    font-weight: 600;
}

.patient-name {
    font-weight: 500;
    font-size: 0.9rem;
}

.patient-age-gender {
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: 2px;
}

.patient-contact {
    font-size: 0.85rem;
    color: #6b7280;
}

/* Time Display */
.time-display {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
}

.time-12hr {
    font-weight: 500;
    font-size: 0.875rem;
    color: #374151;
}

.time-24hr {
    font-size: 0.75rem;
    color: #9ca3af;
}

/* Pagination Styles */
.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0px;
    padding-bottom: 20px;
    margin-top: 0px;
}

.pagination-info {
    font-size: 0.875rem;
    color: #6b7280;
}

.pagination-buttons {
    display: flex;
    gap: 8px;
    align-items: center;
}

.pagination-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px 16px;
    background-color: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    text-decoration: none;
    color: #4a5568;
    font-size: 14px;
    transition: all 0.2s ease;
    min-width: 32px;
    cursor: pointer;
}

.pagination-btn:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

.pagination-btn.active {
    background: #3f75ea;
    color: white;
    border-color: #2563eb;
}



.pagination-btn.disabled {
    background: #f9fafb;
    color: #9ca3af;
    cursor: not-allowed;
    opacity: 0.5;
}

.pagination-ellipsis {
    color: #a0aec0;
    padding: 0 4px;
}

/* Patient Kebab Menu Styles */
.patient-kebab-menu {
    position: relative;
    display: inline-block;
}

.patient-kebab-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    border-radius: 50%;
    color: #6b7280;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.patient-kebab-btn:hover {
    background-color: #f3f4f6;
    color: #374151;
}

.patient-kebab-btn.active {
    background-color: #e5e7eb;
    color: #111827;
}

.patient-kebab-dropdown-portal {
    display: none;
    position: fixed;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    min-width: 200px;
    max-width: 220px;
    width: auto;
    z-index: 99999;
    overflow: hidden;
}

.patient-kebab-dropdown-portal.show {
    display: block;
    animation: patientKebabFadeIn 0.15s ease;
}

@keyframes patientKebabFadeIn {
    from { opacity: 0; transform: scale(0.95) translateY(-4px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.patient-kebab-dropdown-portal a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    color: #374151;
    text-decoration: none;
    font-size: 0.875rem;
    transition: all 0.15s ease;
    cursor: pointer;
    white-space: nowrap;
}

.patient-kebab-dropdown-portal a:hover {
    background-color: #f9fafb;
    color: #111827;
}

.patient-kebab-dropdown-portal a svg {
    flex-shrink: 0;
}

.patient-kebab-dropdown-portal a:first-child {
    border-radius: 8px 8px 0 0;
}

.patient-kebab-dropdown-portal a:last-child {
    border-radius: 0 0 8px 8px;
}

.patient-kebab-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 99998;
}

.patient-kebab-backdrop.show {
    display: block;
}

/* Fullscreen Modal Styles */
.fullscreen-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    justify-content: center;
    backdrop-filter: blur(0px);
    z-index: 99999;
}
.fullscreen-modal-overlay.active {
    display: flex;
    align-items: center;
}
.fullscreen-modal-content {
    background: white;
    border-radius: 16px;
    width: 95%;
    max-width: 900px;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    position: relative;
    z-index: 100000;
}
.fullscreen-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px;
    border-bottom: 1px solid #e5e7eb;
    background: white;
    z-index: 10;
}
.fullscreen-modal-close {
    background: none;
    border: none;
    font-size: 2rem;
    cursor: pointer;
    color: #6b7280;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s;
}
.fullscreen-modal-close:hover {
    background: #f3f4f6;
    color: #111827;
}
.fullscreen-modal-body {
    padding: 24px;
    max-height: calc(90vh - 97px);
    overflow-y: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.fullscreen-modal-body::-webkit-scrollbar {
    display: none;
}
.patient-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.patient-info-item {
    background: #f9fafb;
    padding: 12px 16px;
    border-radius: 8px;
}
.patient-info-label {
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 4px;
}
.patient-info-value {
    font-size: 0.9rem;
    color: #111827;
    font-weight: 500;
}

/* Form Styles */
.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
    margin-bottom: 6px;
}

.form-group .form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-group .form-control:focus {
    border-color: #0ea5e9;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.btn-primary {
    padding: 10px 20px;
    background: #0ea5e9;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary:hover {
    background: #0284c7;
}

.btn-cancel {
    padding: 10px 20px;
    background: white;
    color: #374151;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-cancel:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

/* Payment Details */
.payment-details {
    background: #f9fafb;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 16px;
}
.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e5e7eb;
}
.detail-row:last-child {
    border-bottom: none;
}
.detail-label {
    font-size: 0.85rem;
    color: #6b7280;
}
.detail-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: #111827;
}

/* Modal Header for Payment Modals */
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 16px;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 16px;
}
.modal-header h2 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}
.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #6b7280;
    cursor: pointer;
    padding: 4px;
    line-height: 1;
}

/* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: 99999;
}

.modal-overlay.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

.modal-container {
    position: relative;
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    padding: 20px;
}

.modal {
    background: white;
    border-radius: 16px;
    padding: 32px;
    max-width: 480px;
    width: 100%;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
}
</style>

<!-- Admin Patient Record Modal -->
<div id="adminPatientModal" class="fullscreen-modal-overlay">
    <div class="fullscreen-modal-content">
        <div class="fullscreen-modal-header">
            <div>
                <h2 style="font-size:1.5rem;font-weight:600;margin:0;">Patient Record Details</h2>
                <p id="adminModalPatientName" style="color:#6b7280;margin:4px 0 0 0;font-size:0.9rem;"></p>
            </div>
            <button onclick="closeAdminPatientModal()" class="fullscreen-modal-close">&times;</button>
        </div>
        <div class="fullscreen-modal-body" id="patientModalContent">
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div id="recordPaymentModal" class="modal-overlay" style="display:none;">
    <div class="modal">
        <div class="modal-header">
            <h2>Record Payment</h2>
            <button class="modal-close" onclick="closeRecordPaymentModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="payment-details">
                <div class="detail-row">
                    <span class="detail-label">Total Amount:</span>
                    <span class="detail-value" id="recordPaymentTotalAmount">P0.00</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Paid So Far:</span>
                    <span class="detail-value" id="recordPaymentPaidSoFar">P0.00</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Balance:</span>
                    <span class="detail-value" id="recordPaymentBalance">P0.00</span>
                </div>
            </div>
            <div style="margin-top:1rem;">
                <label style="display:block;margin-bottom:0.5rem;font-weight:500;color:#374151;">Payment Amount</label>
                <input type="number" id="recordPaymentAmount" step="0.01" min="0.01" style="width:100%;padding:0.5rem;border:1px solid #e5e7eb;border-radius:8px;font-size:0.9rem;box-sizing:border-box;">
            </div>
            <div style="margin-top:1rem;">
                <label style="display:block;margin-bottom:0.5rem;font-weight:500;color:#374151;">Payment Method</label>
                <select id="recordPaymentMethod" style="width:100%;padding:0.5rem;border:1px solid #e5e7eb;border-radius:8px;font-size:0.9rem;background:white;box-sizing:border-box;">
                    <option value="cash">Cash</option>
                    <option value="gcash">GCash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="installment">Installment</option>
                </select>
            </div>
            <input type="hidden" id="recordPaymentPatientId">
            <input type="hidden" id="recordPaymentBillingId">
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-cancel" onclick="closeRecordPaymentModal()">Cancel</button>
            <button type="button" class="btn-primary" onclick="confirmRecordPayment()">Record Payment</button>
        </div>
    </div>
</div>

<!-- Payment History Modal -->
<div id="paymentHistoryModal" class="modal-overlay" style="display:none;">
    <div class="modal" style="max-width:550px;">
        <div class="modal-header">
            <h2>Payment History</h2>
            <button class="modal-close" onclick="closePaymentHistoryModal()">&times;</button>
        </div>
        <div class="modal-body">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:#f9fafb;text-align:left;">
                        <th style="padding:0.5rem;font-size:0.8rem;color:#6b7280;font-weight:600;text-transform:uppercase;">Date</th>
                        <th style="padding:0.5rem;font-size:0.8rem;color:#6b7280;font-weight:600;text-transform:uppercase;">Amount</th>
                        <th style="padding:0.5rem;font-size:0.8rem;color:#6b7280;font-weight:600;text-transform:uppercase;">Method</th>
                        <th style="padding:0.5rem;font-size:0.8rem;color:#6b7280;font-weight:600;text-transform:uppercase;">Reference</th>
                    </tr>
                </thead>
                <tbody id="paymentHistoryBody">
                    <tr><td colspan="4" style="text-align:center;padding:2rem;color:#9ca3af;">Loading...</td></tr>
                </tbody>
            </table>
            <div id="paymentHistoryEmpty" style="display:none;text-align:center;padding:2rem;color:#9ca3af;">
                <p>No payment records found.</p>
            </div>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-cancel" onclick="closePaymentHistoryModal()">Close</button>
        </div>
    </div>
</div>

<!-- Set Due Date Modal -->
<div id="setDueDateModal" class="modal-overlay" style="display:none;">
    <div class="modal" style="max-width:400px;">
        <div class="modal-header">
            <h2>Set Due Date</h2>
            <button class="modal-close" onclick="closeSetDueDateModal()">&times;</button>
        </div>
        <div class="modal-body">
            <label style="display:block;margin-bottom:0.5rem;font-weight:500;color:#374151;">Due Date</label>
            <input type="date" id="setDueDateInput" style="width:100%;padding:0.5rem;border:1px solid #e5e7eb;border-radius:8px;font-size:0.9rem;box-sizing:border-box;">
            <input type="hidden" id="setDueDateBillingId">
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-cancel" onclick="closeSetDueDateModal()">Cancel</button>
            <button type="button" class="btn-primary" onclick="confirmSetDueDate()">Save</button>
        </div>
    </div>
</div>

<!-- Add Appointment Modal -->
<div id="appointmentModal" class="modal-overlay">
    <div class="modal-backdrop"></div>
    <div class="modal-container">
        <div class="modal">
            <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Schedule Appointment</h2>
            
            <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 14px 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #0369a1;">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <div>
                    <div style="font-size: 0.75rem; color: #0369a1; font-weight: 600;">PATIENT</div>
                    <div id="appointmentPatientName" style="font-weight: 600; color: #0c4a6e;"></div>
                </div>
            </div>
            
            <form id="addAppointmentForm">
                <input type="hidden" id="appointmentPatientId" name="patient_id">
                <input type="hidden" id="appointmentPatientPhone" name="patient_phone">
                
                <div style="display: flex; gap: 16px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Date *</label>
                        <input type="date" name="appointment_date" required class="form-control" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Time *</label>
                        <input type="time" name="appointment_time" required class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Treatment</label>
                    <select name="treatment" class="form-control">
                        <option value="General Checkup">General Checkup</option>
                        <option value="Teeth Cleaning">Teeth Cleaning</option>
                        <option value="Root Canal">Root Canal</option>
                        <option value="Tooth Extraction">Tooth Extraction</option>
                        <option value="Dental Fillings">Dental Fillings</option>
                        <option value="Braces Adjustment">Braces Adjustment</option>
                        <option value="Denture Fitting">Denture Fitting</option>
                        <option value="Oral Prophylaxis">Oral Prophylaxis</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3" class="form-control" placeholder="Additional notes or instructions..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" onclick="closeAddAppointmentModal()" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-primary">Schedule Appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/admin_layout_end.php';
?>
