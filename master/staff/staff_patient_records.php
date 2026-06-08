<?php
$pageTitle = 'Patient Records';
require_once '../../config/database.php';
require_once '../../includes/staff_layout_start.php';

try {
    // Fetch ALL non-archived patients (client-side search/pagination)
    $stmt = $pdo->prepare("
        SELECT p.*, 
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
               a.status_id as appointment_status_id,
               a.status_name as appointment_status,
               a.appointment_date,
               a.appointment_time,
               svc.name as service_name
        FROM patients p 
        LEFT JOIN (
            SELECT a1.*, s.status_name
            FROM appointments a1
            LEFT JOIN appointment_statuses s ON a1.status_id = s.id
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
    
    // Get stats
    $inQueueStmt = $pdo->query("
        SELECT COUNT(DISTINCT p.id) FROM patients p
        INNER JOIN appointments a ON p.id = a.patient_id
        WHERE p.is_archived = 0 AND a.status_id IN (1, 2) AND a.appointment_date >= CURDATE()
    ");
    $inQueue = $inQueueStmt->fetchColumn();
    
    $completedStmt = $pdo->query("
        SELECT COUNT(DISTINCT p.id) FROM patients p
        INNER JOIN appointments a ON p.id = a.patient_id
        WHERE p.is_archived = 0 AND a.status_id = 3
    ");
    $completedCount = $completedStmt->fetchColumn();
    
    $newThisMonthStmt = $pdo->query("
        SELECT COUNT(*) FROM patients WHERE is_archived = 0 AND created_at > DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $newThisMonth = $newThisMonthStmt->fetchColumn();
    
} catch (Exception $e) {
    $patients = [];
    $totalPatients = 0;
    $inQueue = 0;
    $completedCount = 0;
    $newThisMonth = 0;
}
?>

<!-- Summary Stats Cards -->
<div class="summary-cards" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 20px; margin-bottom: 24px;">
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
            <p style="font-size: 0.875rem; color: #6b7280; margin: 0;">Pending</p>
        </div>
    </div>
    <div class="summary-card" style="background: white; border-radius: 12px; padding: 20px; border: 1px solid #e5e7eb; display: flex; align-items: center; gap: 16px;">
        <div class="summary-icon" style="width: 48px; height: 48px; background: #e0e7ff; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">📅</div>
        <div class="summary-info">
            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo $completedCount; ?></h3>
            <p style="font-size: 0.875rem; color: #6b7280; margin: 0;">Completed</p>
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

<!-- Search Toolbar -->
<div class="search-toolbar" style="background: white; padding: 16px 24px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e5e7eb; display: flex; gap: 16px; align-items: center;">
    <div class="search-input-container" style="position: relative; flex: 1;">
        <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #6b7280;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="m19.6 21l-6.3-6.3q-.75.6-1.725.95T9.5 16q-2.725 0-4.612-1.888T3 9.5t1.888-4.612T9.5 3t4.613 1.888T16 9.5q0 1.1-.35 2.075T14.7 13.3l6.3 6.3zM9.5 14q1.875 0 3.188-1.312T14 9.5t-1.312-3.187T9.5 5T6.313 6.313T5 9.5t1.313 3.188T9.5 14"/></svg>
        </span>
        <input type="text" id="searchInput" placeholder="Search patient name or phone..." class="search-input" style="width: 100%; padding: 12px 16px 12px 44px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; outline: none;">
    </div>
    <select id="statusFilter" style="padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; background: white; cursor: pointer;">
        <option value="">All Status</option>
        <option value="waiting">Waiting</option>
        <option value="in_procedure">In Procedure</option>
        <option value="completed">Completed</option>
        <option value="scheduled">Scheduled</option>
        <option value="none">No Queue</option>
    </select>
</div>

<!-- Patient Records Table -->
<div class="section-card" style="background: white; border-radius: 12px; border: 1px solid #e5e7eb;">
    
    <div style="overflow-x: auto;">
        <table class="data-table" style="width: 100%; border-collapse: collapse; border-radius: 26px;">
            <thead>
                <tr>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">First Name</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">Middle Name</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">Last Name</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">Age/Gender</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">Contact</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">Current Treatment</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">Queue Status</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">Actions</th>
                </tr>
            </thead>
            <tbody id="patientsTableBody">
            </tbody>
        </table>
    </div>
</div>

<div id="pagination" class="pagination"></div>

<!-- Full Screen Patient Record Modal -->
<div id="patientRecordModal" class="fullscreen-modal-overlay">
    <div class="fullscreen-modal-content">
        <div class="fullscreen-modal-header">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 600; margin: 0;">Patient Record Details</h2>
                <p id="staffModalPatientName" style="color: #6b7280; margin: 4px 0 0 0; font-size: 0.9rem;"></p>
            </div>
        </div>
        <div class="fullscreen-modal-body" id="patientRecordContent">
        </div>
    </div>
</div>

<style>
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
    border-radius: 8px;
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

/* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 99999;
}

.modal-overlay.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-backdrop {
    display: none;
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
    pointer-events: none;
}

.modal-overlay.active .modal-container {
    pointer-events: auto;
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

/* Pagination Styles - Matching Admin Style */
.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    margin-top: 12px;
}

.pagination-info {
    color: #6b7280;
    font-size: 0.875rem;
}

.pagination-buttons {
    display: flex;
    gap: 8px;
    align-items: center;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
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

.pagination-btn:hover:not(.active):not(:disabled) {
    background-color: #f7fafc;
    border-color: #cbd5e0;
}

.pagination-btn.active {
    background-color: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
}

.pagination-btn:disabled {
    color: #a0aec0;
    background-color: #fff;
    cursor: not-allowed;
    border-color: #edf2f7;
}

.pagination-ellipsis {
    color: #a0aec0;
    padding: 0 4px;
}
</style>

<script>
const allPatients = <?php echo json_encode($patients); ?>;
let currentPage = 1;
let perPage = 10;
let filteredPatients = [...allPatients];

const statusLabels = {0:'--',1:'Scheduled',2:'In Queue',3:'Unpaid',4:'Done',5:'Cancelled'};
const statusColors = {0:'#f3f4f6:#6b7280',1:'#dbeafe:#1d4ed8',2:'#fef3c7:#92400e',3:'#fed7aa:#c2410c',4:'#d1fae5:#065f46',5:'#fee2e2:#dc2626'};

// Portal Pattern: Move modals to body level
(function() {
    var modals = ['patientRecordModal','appointmentModal','recordPaymentModal','paymentHistoryModal','setDueDateModal'];
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

    const statusMap = {
        'waiting': [2],
        'in_procedure': [1],
        'completed': [4],
        'scheduled': [1],
        'none': [0]
    };

    filteredPatients = allPatients.filter(function(p) {
        const haystack = ((p.first_name||'') + ' ' + (p.middle_name||'') + ' ' + (p.last_name||'') + ' ' + (p.phone||'')).toLowerCase();
        const matchesSearch = !query || haystack.includes(query);

        let matchesStatus = true;
        if (statusVal) {
            const sId = Number(p.appointment_status_id || 0);
            matchesStatus = (statusMap[statusVal] || []).includes(sId);
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
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:60px;color:#6b7280;">No patient records found</td></tr>';
        document.getElementById('pagination').innerHTML = '';
        return;
    }

    tbody.innerHTML = pageData.map(function(p) {
        const sId = Number(p.appointment_status_id || 0);
        const label = statusLabels[sId] || '--';
        const [bg, text] = (statusColors[sId] || '#f3f4f6:#6b7280').split(':');
        const age = p.age || 'N/A';
        const gender = p.gender || 'N/A';
        const phone = p.phone || 'N/A';
        const email = p.email || '';
        const svc = p.service_name || 'N/A';
        const pid = Number(p.id);
        const fn = escapeHtml(p.first_name || '');
        const mn = escapeHtml(p.middle_name || '');
        const ln = escapeHtml(p.last_name || '');

        return '<tr class="patient-row">' +
            '<td style="padding:12px 16px;border-bottom:1px solid #f3f4f6;">' + fn + '</td>' +
            '<td style="padding:12px 16px;border-bottom:1px solid #f3f4f6;">' + mn + '</td>' +
            '<td style="padding:12px 16px;border-bottom:1px solid #f3f4f6;">' + ln + '</td>' +
            '<td style="padding:12px 16px;border-bottom:1px solid #f3f4f6;"><div style="font-size:0.9rem;"><span style="font-weight:500;">' + age + ' yrs</span><span style="color:#6b7280;margin-left:8px;">' + escapeHtml(gender.charAt(0).toUpperCase() + gender.slice(1)) + '</span></div></td>' +
            '<td style="padding:12px 16px;border-bottom:1px solid #f3f4f6;"><div style="font-size:0.85rem;color:#6b7280;"><div>' + escapeHtml(phone) + '</div><div>' + escapeHtml(email) + '</div></div></td>' +
            '<td style="padding:12px 16px;border-bottom:1px solid #f3f4f6;"><span style="font-weight:500;color:#111827;">' + escapeHtml(svc) + '</span></td>' +
            '<td style="padding:12px 16px;border-bottom:1px solid #f3f4f6;"><span style="background:' + bg + ';color:' + text + ';padding:4px 12px;border-radius:9999px;font-size:0.75rem;font-weight:600;">' + label + '</span></td>' +
            '<td style="padding:12px 16px;border-bottom:1px solid #f3f4f6;"><div class="patient-kebab-menu"><button class="patient-kebab-btn" data-patient-id="' + pid + '">' +
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

function viewPatientRecord(patientId) {
    fetch('../user-patient/patient_record_details.php?id=' + encodeURIComponent(patientId))
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const p = data.patient;
            const m = data.medical_history || {};
            const d = data.dental_history || {};
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
            
            document.getElementById('staffModalPatientName').innerText = (p.first_name || '') + ' ' + (p.last_name || '');
            
            const hasMedicalAlert = allergies === 'Yes' ||
                                   conditions.toLowerCase().includes('diabetes') ||
                                   conditions.toLowerCase().includes('heart') ||
                                   conditions.toLowerCase().includes('blood pressure') ||
                                   conditions.toLowerCase().includes('asthma');
            
            document.getElementById('patientRecordContent').innerHTML = `
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
                    <div class="patient-info-item" style="grid-column: 1 / -1;">
                        <div class="patient-info-label">Address</div>
                        <div class="patient-info-value">${p.address || 'N/A'} ${p.city ? ', ' + p.city : ''} ${p.province ? ', ' + p.province : ''}</div>
                    </div>
                </div>

                ${hasMedicalAlert ? `
                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <div style="color: #dc2626; font-weight: 600; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">⚠️ Medical Alert - Important for Treatment</div>
                    <div class="medical-alert-grid">
                        <div class="patient-info-item">
                            <div class="patient-info-label">Allergies</div>
                            <div class="patient-info-value" style="${allergies === 'Yes' ? 'color: #dc2626;' : ''}">${allergies}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Diabetes</div>
                            <div class="patient-info-value" style="${conditions.toLowerCase().includes('diabetes') ? 'color: #dc2626;' : ''}">${conditions.toLowerCase().includes('diabetes') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Heart Disease</div>
                            <div class="patient-info-value" style="${conditions.toLowerCase().includes('heart') ? 'color: #dc2626;' : ''}">${conditions.toLowerCase().includes('heart') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">High Blood Pressure</div>
                            <div class="patient-info-value" style="${conditions.toLowerCase().includes('blood pressure') ? 'color: #dc2626;' : ''}">${conditions.toLowerCase().includes('blood pressure') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Asthma</div>
                            <div class="patient-info-value" style="${conditions.toLowerCase().includes('asthma') ? 'color: #dc2626;' : ''}">${conditions.toLowerCase().includes('asthma') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="patient-info-item" style="grid-column: 1 / -1;">
                            <div class="patient-info-label">Current Medications</div>
                            <div class="patient-info-value">${medications}</div>
                        </div>
                    </div>
                </div>
                ` : ''}

                ${q ? `
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: #1e40af; margin-bottom: 16px;">📋 Service Requested</h3>
                    <div class="patient-info-grid">
                        <div class="patient-info-item">
                            <div class="patient-info-label">Treatment Type</div>
                            <div class="patient-info-value">${q.treatment_type || 'Consultation'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Selected Teeth</div>
                            <div class="patient-info-value">${q.teeth_numbers ? getStaffPatientTeethDisplayText(q.teeth_numbers) : 'None specified'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Status</div>
                            <div class="patient-info-value">
                                <span style="background: ${q.status === 'in_procedure' ? '#dcfce7' : q.status === 'waiting' ? '#fef3c7' : '#f3f4f6'}; color: ${q.status === 'in_procedure' ? '#15803d' : q.status === 'waiting' ? '#d97706' : '#6b7280'}; padding: 4px 12px; border-radius: 9999px; font-size: 0.85rem;">
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

                <div style="background: #f9fafb; border-radius: 12px; padding: 20px;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: #374151; margin-bottom: 16px;">📜 Dental History</h3>
                    <div class="patient-info-grid">
                        <div class="patient-info-item">
                            <div class="patient-info-label">Previous Dentist</div>
                            <div class="patient-info-value">${d.previous_dentist || 'N/A'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Last Visit</div>
                            <div class="patient-info-value">${d.last_visit_date || 'N/A'}</div>
                        </div>
                        <div class="patient-info-item" style="grid-column: 1 / -1;">
                            <div class="patient-info-label">Current Complaints</div>
                            <div class="patient-info-value">${d.current_complaints || 'None'}</div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end;">
                    <button onclick="closePatientRecordModal()" class="btn-cancel" style="padding: 10px 24px; background: #f3f4f6; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">Close</button>
                </div>
            `;
            document.getElementById('patientRecordModal').classList.add('active');
        }
    });
}

function closePatientRecordModal() {
    document.getElementById('patientRecordModal').classList.remove('active');
}

document.getElementById('patientRecordModal').addEventListener('click', function(e) {
    if (e.target === this) closePatientRecordModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePatientRecordModal();
});

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
    `;
}

function positionPatientKebabDropdown(button) {
    if (!patientKebabDropdown || !button) return;

    const rect = button.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    const padding = 12;

    // Measure dropdown size by temporarily showing it invisibly
    patientKebabDropdown.style.display = 'block';
    patientKebabDropdown.style.visibility = 'hidden';
    const dropdownRect = patientKebabDropdown.getBoundingClientRect();
    const dropdownWidth = dropdownRect.width || 200;
    const dropdownHeight = dropdownRect.height || 120;

    // Prefer left side of the button, vertically centered
    let left = rect.left - dropdownWidth - 8;
    let top = rect.top + (rect.height / 2) - (dropdownHeight / 2);

    // If it overflows on the left, place it on the right
    if (left < padding) {
        left = rect.right + 8;
    }

    // Clamp vertical position
    if (top + dropdownHeight > viewportHeight - padding) {
        top = viewportHeight - padding - dropdownHeight;
    }
    if (top < padding) {
        top = padding;
    }

    patientKebabDropdown.style.left = left + 'px';
    patientKebabDropdown.style.top = top + 'px';
    patientKebabDropdown.style.visibility = 'visible';
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
            viewPatientRecord(id);
            break;
        case 'appointment':
            openAddAppointmentModal(id);
            break;
        case 'session':
            window.location.href = '../shared/quick_session.php?patient_id=' + id;
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

window.addEventListener('resize', function() {
    if (patientKebabDropdown && patientKebabDropdown.classList.contains('show') && patientActiveButton) {
        positionPatientKebabDropdown(patientActiveButton);
    }
});

// Add Appointment Modal Functions
let selectedPatientId = null;

function openAddAppointmentModal(patientId) {
    // Find patient data from the existing patients array
    const patient = allPatients.find(p => p.id == patientId);
    
    if (patient) {
        selectedPatientId = patientId;
        
        // Build full name from patient data
        const fullName = [patient.first_name || '', patient.middle_name || '', patient.last_name || ''].filter(Boolean).join(' ');
        
        // Update modal content
        document.getElementById('appointmentPatientName').textContent = fullName.trim();
        document.getElementById('appointmentPatientId').value = patientId;
        document.getElementById('appointmentPatientPhone').value = patient.phone || '';
        
        // Show modal
        document.getElementById('appointmentModal').classList.add('active');
    } else {
        alert('Patient not found');
    }
}

function closeAddAppointmentModal() {
    document.getElementById('appointmentModal').classList.remove('active');
    selectedPatientId = null;
}

var aptModalEl = document.getElementById('appointmentModal');
if (aptModalEl) {
    aptModalEl.addEventListener('click', function(e) {
        if (e.target === this || e.target.classList.contains('modal-backdrop') || e.target.closest('.modal-container') === e.target) {
            closeAddAppointmentModal();
        }
    });
}

var aptFormEl = document.getElementById('addAppointmentForm');
if (aptFormEl) {
    aptFormEl.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('../user-patient/process_patient_appointment.php', {
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
    }
});

// Billing Helper Functions
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
    document.getElementById('recordPaymentPatientId').value = patientId || 0;
    document.getElementById('recordPaymentBillingId').value = billingId || 0;
    document.getElementById('recordPaymentTotalAmount').textContent = 'P' + parseFloat(totalAmount || 0).toFixed(2);
    document.getElementById('recordPaymentPaidSoFar').textContent = 'P' + parseFloat(paidAmount || 0).toFixed(2);
    document.getElementById('recordPaymentBalance').textContent = 'P' + parseFloat(balance || 0).toFixed(2);
    document.getElementById('recordPaymentAmount').value = balance || 0;
    document.getElementById('recordPaymentMethod').value = 'cash';
    document.getElementById('recordPaymentModal').style.display = 'flex';
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

    fetch('../shared/billing_actions.php', {
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

    fetch('../shared/billing_actions.php?action=get_payments&billing_id=' + encodeURIComponent(billingId))
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

    fetch('../shared/billing_actions.php?action=get_billing&billing_id=' + encodeURIComponent(billingId))
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

    fetch('../shared/billing_actions.php', {
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

// Record Payment Modal click handlers
var rpModalEl = document.getElementById('recordPaymentModal');
if (rpModalEl) {
    rpModalEl.addEventListener('click', function(e) {
        if (e.target === this) closeRecordPaymentModal();
    });
}

var phModalEl = document.getElementById('paymentHistoryModal');
if (phModalEl) {
    phModalEl.addEventListener('click', function(e) {
        if (e.target === this) closePaymentHistoryModal();
    });
}

var sdModalEl = document.getElementById('setDueDateModal');
if (sdModalEl) {
    sdModalEl.addEventListener('click', function(e) {
        if (e.target === this) closeSetDueDateModal();
    });
}

// Convert teeth numbers to arch labels
function getStaffPatientTeethDisplayText(teethString) {
    if (!teethString || teethString.trim() === '') {
        return 'None specified';
    }
    
    const teeth = teethString.split(',').map(t => parseInt(t.trim())).filter(t => !isNaN(t));
    if (teeth.length === 0) {
        return 'None specified';
    }
    
    // Staff's arch definitions - exactly 16 teeth per arch
    const upperArch = [11, 12, 13, 14, 15, 16, 17, 18, 21, 22, 23, 24, 25, 26, 27, 28];
    const lowerArch = [31, 32, 33, 34, 35, 36, 37, 38, 41, 42, 43, 44, 45, 46, 47, 48];
    
    // Check for exact arch matches
    const hasUpperArch = teeth.length === 16 && upperArch.every(t => teeth.includes(t));
    const hasLowerArch = teeth.length === 16 && lowerArch.every(t => teeth.includes(t));
    
    const parts = [];
    
    if (hasUpperArch) {
        parts.push('Upper Arch');
    }
    
    if (hasLowerArch) {
        parts.push('Lower Arch');
    }
    
    if (parts.length === 0) {
        return teeth.sort((a, b) => a - b).join(', ');
    }
    
    return parts.join(' + ');
}
</script>

<!-- Add Appointment Modal -->
<div id="appointmentModal" class="modal-overlay">
    <div class="modal-backdrop"></div>
    <div class="modal-container">
        <div class="modal" style="max-width: 480px;">
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

<!-- Record Payment Modal -->
<div id="recordPaymentModal" class="modal-overlay">
    <div class="modal-backdrop"></div>
    <div class="modal-container">
        <div class="modal" style="max-width:440px;">
            <h2 style="margin:0 0 20px;font-size:1.25rem;font-weight:600;">Record Payment</h2>
            <input type="hidden" id="recordPaymentPatientId">
            <input type="hidden" id="recordPaymentBillingId">
            <div style="background:#f9fafb;border-radius:8px;padding:16px;margin-bottom:20px;">
                <div class="patient-info-grid" style="margin-bottom:0;">
                    <div class="patient-info-item">
                        <div class="patient-info-label">Total Amount</div>
                        <div class="patient-info-value" id="recordPaymentTotalAmount">P0.00</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Paid So Far</div>
                        <div class="patient-info-value" id="recordPaymentPaidSoFar" style="color:#059669;">P0.00</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Balance</div>
                        <div class="patient-info-value" id="recordPaymentBalance" style="color:#dc2626;font-weight:600;">P0.00</div>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Payment Amount</label>
                <input type="number" id="recordPaymentAmount" class="form-control" step="0.01" min="0">
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <select id="recordPaymentMethod" class="form-control">
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="gcash">GCash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                </select>
            </div>
            <div class="modal-actions">
                <button onclick="closeRecordPaymentModal()" class="btn-cancel">Cancel</button>
                <button onclick="confirmRecordPayment()" class="btn-primary">Confirm Payment</button>
            </div>
        </div>
    </div>
</div>

<!-- Payment History Modal -->
<div id="paymentHistoryModal" class="modal-overlay">
    <div class="modal-backdrop"></div>
    <div class="modal-container">
        <div class="modal" style="max-width:600px;">
            <h2 style="margin:0 0 20px;font-size:1.25rem;font-weight:600;">Payment History</h2>
            <table class="data-table" style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="padding:10px 12px;text-align:left;font-weight:600;color:#6b7280;font-size:0.85rem;text-transform:uppercase;background:#f9fafb;border-bottom:1px solid #f3f4f6;">Date</th>
                        <th style="padding:10px 12px;text-align:left;font-weight:600;color:#6b7280;font-size:0.85rem;text-transform:uppercase;background:#f9fafb;border-bottom:1px solid #f3f4f6;">Amount</th>
                        <th style="padding:10px 12px;text-align:left;font-weight:600;color:#6b7280;font-size:0.85rem;text-transform:uppercase;background:#f9fafb;border-bottom:1px solid #f3f4f6;">Method</th>
                        <th style="padding:10px 12px;text-align:left;font-weight:600;color:#6b7280;font-size:0.85rem;text-transform:uppercase;background:#f9fafb;border-bottom:1px solid #f3f4f6;">Reference</th>
                    </tr>
                </thead>
                <tbody id="paymentHistoryBody">
                </tbody>
            </table>
            <div id="paymentHistoryEmpty" style="display:none;text-align:center;padding:40px;color:#6b7280;">No payment records found.</div>
            <div class="modal-actions">
                <button onclick="closePaymentHistoryModal()" class="btn-cancel">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Set Due Date Modal -->
<div id="setDueDateModal" class="modal-overlay">
    <div class="modal-backdrop"></div>
    <div class="modal-container">
        <div class="modal" style="max-width:400px;">
            <h2 style="margin:0 0 20px;font-size:1.25rem;font-weight:600;">Set Due Date</h2>
            <input type="hidden" id="setDueDateBillingId">
            <div class="form-group">
                <label>Due Date</label>
                <input type="date" id="setDueDateInput" class="form-control">
            </div>
            <div class="modal-actions">
                <button onclick="closeSetDueDateModal()" class="btn-cancel">Cancel</button>
                <button onclick="confirmSetDueDate()" class="btn-primary">Save</button>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/staff_layout_end.php'; ?>
