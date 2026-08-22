<?php
$pageTitle = 'Patient Records';
require_once '../../config/database.php';
require_once '../../includes/dentist_layout_start.php';

try {
    // Fetch ALL non-archived patients (client-side search/pagination/sort)
    $stmt = $pdo->prepare("
        SELECT p.*, 
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
               a.status as appointment_status,
               a.appointment_date,
               a.appointment_time,
               q.status as queue_status,
               COALESCE(svc.name, q.treatment_type, 'N/A') as service_name
        FROM patients p 
        LEFT JOIN (
            SELECT a1.*
            FROM appointments a1
            WHERE a1.id = (
                SELECT MAX(a2.id) FROM appointments a2 WHERE a2.patient_id = a1.patient_id
            )
        ) a ON p.id = a.patient_id
        LEFT JOIN services svc ON a.service_id = svc.id
        LEFT JOIN (
            SELECT q1.*
            FROM queue q1
            WHERE q1.id = (
                SELECT MAX(q2.id) FROM queue q2 WHERE q2.patient_id = q1.patient_id
            )
        ) q ON p.id = q.patient_id
        WHERE p.is_archived = 0
        GROUP BY p.id
        ORDER BY p.created_at DESC, p.id DESC
    ");
    $stmt->execute();
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalPatients = count($patients);

    // Get stats
    $inQueueStmt = $pdo->query("
        SELECT COUNT(DISTINCT p.id) FROM patients p
        INNER JOIN appointments a ON p.id = a.patient_id
        WHERE p.is_archived = 0 AND a.status IN ('Scheduled', 'In Queue') AND a.appointment_date >= CURDATE()
    ");
    $inQueue = $inQueueStmt->fetchColumn();
    
    $completedStmt = $pdo->query("
        SELECT COUNT(DISTINCT p.id) FROM patients p
        INNER JOIN appointments a ON p.id = a.patient_id
        WHERE p.is_archived = 0 AND a.status = 'Completed'
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

<!-- Summary Stats -->
<div class="summary-cards">
    <div class="summary-card card-total-patients">
        <div class="summary-icon">
            <img src="../../assets/icons/total-patients.png" alt="Total Patients">
        </div>
        <div class="summary-info">
            <h3><?php echo $totalPatients; ?></h3>
            <p>Total Patients</p>
        </div>
    </div>
    <div class="summary-card card-pending">
        <div class="summary-icon">
            <img src="../../assets/icons/pending.png" alt="Pending">
        </div>
        <div class="summary-info">
            <h3><?php echo $inQueue; ?></h3>
            <p>Pending Appointments</p>
        </div>
    </div>
    <div class="summary-card card-new-this-month">
        <div class="summary-icon">
            <img src="../../assets/icons/new-this-month.png" alt="New This Month">
        </div>
        <div class="summary-info">
            <h3><?php echo $newThisMonth; ?></h3>
            <p>New This Month</p>
        </div>
    </div>
    <div class="summary-card card-completed">
        <div class="summary-icon">
            <img src="../../assets/icons/check.png" alt="Completed">
        </div>
        <div class="summary-info">
            <h3><?php echo $completedCount; ?></h3>
            <p>Completed</p>
        </div>
    </div>
</div>

<!-- Search & Filters -->

<div class="search-filter-row">
    <input type="text" id="searchInput" placeholder="Search by name or phone..." class="search-input">
    
    <select id="statusFilter" class="filter-select">
        <option value="">All Status</option>
        <option value="waiting">Waiting</option>
        <option value="in_procedure">In Procedure</option>
        <option value="on_hold">On Hold</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
        <option value="Scheduled">Scheduled</option>
        <option value="In Queue">In Queue</option>
        <option value="Pending Payment">Pending Payment</option>
    </select>
    
    <select id="sortFilter" class="filter-select">
        <option value="newest">Newest First</option>
        <option value="name">Name (A-Z)</option>
    </select>
</div>

<!-- Patient Records Table -->

        <div class="table-container">
            <table class="data-table" id="patientsTable">
                <thead>
                    <tr>
                        <th>First Name</th>
                        <th>Middle Name</th>
                        <th>Last Name</th>
                        <th>Age/Gender</th>
                        <th>Contact</th>
                        <th>Current Treatment</th>
                        <th>Queue Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="patientsTableBody">
                </tbody>
            </table>
        </div>

    <div id="pagination" class="pagination"></div>


<!-- Patient Details Modal - Full Screen -->
<div id="patientModal" class="modal-overlay">
    <div class="modal" style="max-width: 900px;">
        <div class="modal-header">
                <!-- <div>
                    <h2 style="font-size: 1.5rem; font-weight: 600; margin: 0;">Patient Record Details</h2>
                    <p id="dentistModalPatientName" style="color: #6b7280; margin: 4px 0 0 0; font-size: 0.9rem;"></p>
                </div> -->

            <div class="fullscreen-modal-header">
            
                <div style="display:flex;align-items:center;gap:12px;">
                    <div id="dentistModalPatientAvatar" style="width:56px;height:56px;border-radius:50%;overflow:hidden;background:#6366f1;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;font-weight:700;">
                    </div>
                        <div>
                            <h2 style="font-size:1.25rem;font-weight:600;margin:0;">Patient Record Details</h2>
                            <div style="display:flex;align-items:center;gap:10px;margin-top:6px;">
                                <p id="dentistModalPatientName" style="color:#6b7280;margin:0;font-size:0.95rem;"></p>
                                <div id="dentistModalPatientBadge"></div>
                            </div>
                        </div>
                </div>

            
        </div>


            <button onclick="closePatientModal()" class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="patientModalContent">
            <!-- Content loaded dynamically -->
        </div>
    </div>
</div>

<script>
const allPatients = <?php echo json_encode($patients); ?>;
// Baseline for the "new patient" poll: highest active patient id, computed
// identically to dentist_patients_poll.php so the two can never disagree.
const latestPatientId = <?php
    $maxStmt = $pdo->query("SELECT COALESCE(MAX(id), 0) FROM patients WHERE COALESCE(is_archived, 0) = 0");
    echo (int)($maxStmt ? $maxStmt->fetchColumn() : 0);
?>;
let currentPage = 1;
let perPage = 7;
let filteredPatients = [...allPatients];

const statusLabels = {'waiting':'Waiting','in_procedure':'In Procedure','on_hold':'On Hold','completed':'Completed','cancelled':'Cancelled','Scheduled':'Scheduled','In Queue':'In Queue','Pending Payment':'Pending Payment'};
const statusColors = {'waiting':'#fef3c7:#92400e','in_procedure':'#dbeafe:#1e40af','on_hold':'#fed7aa:#9a3412','completed':'#d1fae5:#065f46','cancelled':'#fee2e2:#dc2626','Scheduled':'#dbeafe:#1d4ed8','In Queue':'#fef3c7:#92400e','Pending Payment':'#fed7aa:#c2410c'};

// Portal Pattern: Move modals to body level
document.addEventListener('DOMContentLoaded', function() {
    var modals = ['patientModal','billingModal','appointmentModal','treatmentPlanModal','patientPrescriptionModal','recordPaymentModal','paymentHistoryModal','setDueDateModal'];
    modals.forEach(function(id) {
        var el = document.getElementById(id);
        if (el) document.body.appendChild(el);
    });

    // Set up close handlers
    var pm = document.getElementById('patientModal');
    if (pm) pm.addEventListener('click', function(e) { if (e.target === pm) closePatientModal(); });
    var bm = document.getElementById('billingModal');
    if (bm) bm.addEventListener('click', function(e) { if (e.target === bm) closeBillingModal(); });
    var am = document.getElementById('appointmentModal');
    if (am) am.addEventListener('click', function(e) { if (e.target === am) closeAddAppointmentModal(); });
    var tpm = document.getElementById('treatmentPlanModal');
    if (tpm) tpm.addEventListener('click', function(e) { if (e.target === tpm) closeTreatmentPlanModal(); });
    var ppm = document.getElementById('patientPrescriptionModal');
    if (ppm) ppm.addEventListener('click', function(e) { if (e.target === ppm) closePatientPrescriptionModal(); });
});

document.getElementById('searchInput').addEventListener('input', applyFilters);
document.getElementById('statusFilter').addEventListener('change', applyFilters);
document.getElementById('sortFilter').addEventListener('change', applyFilters);

renderTable();

function applyFilters() {
    currentPage = 1;
    const query = document.getElementById('searchInput').value.trim().toLowerCase();
    const statusVal = document.getElementById('statusFilter').value;
    const sortVal = document.getElementById('sortFilter').value;

    filteredPatients = allPatients.filter(function(p) {
        const haystack = ((p.first_name||'') + ' ' + (p.middle_name||'') + ' ' + (p.last_name||'') + ' ' + (p.phone||'')).toLowerCase();
        const matchesSearch = !query || haystack.includes(query);

        let matchesStatus = true;
        if (statusVal) {
            const s = (p.queue_status || p.appointment_status || '');
            matchesStatus = (s === statusVal);
        }
        return matchesSearch && matchesStatus;
    });

    if (sortVal === 'name') {
        filteredPatients.sort(function(a, b) {
            var nameA = ((a.first_name||'') + ' ' + (a.middle_name||'') + ' ' + (a.last_name||'')).toLowerCase();
            var nameB = ((b.first_name||'') + ' ' + (b.middle_name||'') + ' ' + (b.last_name||'')).toLowerCase();
            return nameA.localeCompare(nameB);
        });
    }

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

    tbody.innerHTML = pageData.map(function(p) {
        const s = (p.queue_status || p.appointment_status || '');
        const label = statusLabels[s] || '--';
        const [bg, text] = (statusColors[s] || '#f3f4f6:#6b7280').split(':');
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
            '<td>' + fn + '</td>' +
            '<td>' + mn + '</td>' +
            '<td>' + ln + '</td>' +
            '<td><div style="font-size:0.9rem;"><span style="font-weight:500;">' + age + ' yrs</span><span style="color:#6b7280;margin-left:8px;">' + escapeHtml(gender.charAt(0).toUpperCase() + gender.slice(1)) + '</span></div></td>' +
            '<td><div style="font-size:0.85rem;color:#6b7280;"><div>' + escapeHtml(phone) + '</div><div>' + escapeHtml(email) + '</div></div></td>' +
            '<td><div style="font-size:0.9rem;font-weight:500;">' + escapeHtml(svc) + '</div></td>' +
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

function renderConsentSignature(signatureData, signerName) {
    if (!signatureData) {
        return '<div style="font-size:0.85rem;color:#6b7280;">No signature available</div>';
    }
    if (typeof signatureData === 'string' && signatureData.indexOf('data:') === 0) {
        return '<div style="border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;background:#ffffff;display:flex;align-items:center;justify-content:center;padding:8px;max-width:100%;min-height:100px;">' +
               '<img src="' + signatureData + '" alt="Consent Signature" style="max-width:100%;max-height:120px;object-fit:contain;">' +
               '</div>';
    }
    return '<div style="font-size:0.85rem;color:#334155;">Signature recorded</div>';
}

function toggleConsultHistoryCard(idx) {
    var body = document.getElementById('consultHistBody_' + idx);
    var arrow = document.getElementById('consultHistArrow_' + idx);
    if (!body) return;
    var isVisible = body.style.display !== 'none';
    body.style.display = isVisible ? 'none' : 'block';
    if (arrow) arrow.style.transform = isVisible ? '' : 'rotate(180deg)';
}

// View patient details - Full screen modal with medical history
function viewPatientDetails(patientId) {
    console.log('View Details clicked for patient:', patientId);
    closePatientKebabDropdown();
    
    const modalContent = document.getElementById('patientModalContent');
    modalContent.innerHTML = '<div style="text-align:center;padding:40px;color:#6b7280;">Loading patient records...</div>';
    document.getElementById('patientModal').style.display = 'flex';
    
    fetch('../user-patient/patient_record_details.php?id=' + patientId)
    .then(response => {
        if (!response.ok) throw new Error('Network error');
        return response.json();
    })
    .then(data => {
        if (!data.success) {
            modalContent.innerHTML = `
                <div style="text-align:center;padding:40px;color:#dc2626;">
                    <p style="font-size:1.1rem;font-weight:600;">Error: ${data.message || 'Unable to load patient details'}</p>
                    <button onclick="closePatientModal()" style="margin-top:16px;padding:10px 20px;background:#6b7280;color:white;border:none;border-radius:8px;cursor:pointer;">Close</button>
                </div>`;
            return;
        }
        
        const p = data.patient;
        const m = data.medical_history || {};
        const dentalHistory = data.dental_history || [];
        const consultationHistory = data.consultation_history || [];
        const completedPlans = data.completed_plans || [];
        const q = data.queue_item;
        const b = data.billing || {};
        const allBillings = data.all_billings || [];
        const payments = data.payments || [];
        const personalInfoUpdate = data.patient_personal_info_update || null;
            
            {
            const allergies = m.allergies || 'None';
            const medications = m.current_medications || 'None';
            const medicalConditions = m.medical_conditions || 'None';
            
            const billingId = b.id || '0';
            window._currentAllBillings = allBillings;
            // Prepare admin-style header HTML to prepend to full modal
            const headerHtml = `
                <div style="width:100%;max-width:820px;margin:0 auto;">
                    

                    <div style="background:#f9fafb;border-radius:8px;padding:16px;margin-bottom:12px;">
                        <h4 style="margin:0 0 12px 0;color:#374151;font-size:14px;font-weight:600;">👤 Account Information</h4>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Username</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.username || '-'}</p></div>
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Email</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.email || '-'}</p></div>
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Phone</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.phone || '-'}</p></div>
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Address</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.street_address || '-'}</p></div>
                        </div>
                    </div>

                    <div style="background:#f9fafb;border-radius:8px;padding:16px;margin-bottom:12px;">
                        <h4 style="margin:0 0 12px 0;color:#374151;font-size:14px;font-weight:600;">📝 Personal Information</h4>
                        ${personalInfoUpdate ? `<div style="font-size:12px;color:#6b7280;margin:-4px 0 12px;">Patient updated: ${new Date(personalInfoUpdate.updated_at).toLocaleString()}${personalInfoUpdate.changed_fields && personalInfoUpdate.changed_fields.length ? ' · Changed: ' + personalInfoUpdate.changed_fields.join(', ') : ''}</div>` : ''}
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">First Name</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.first_name || '-'}</p></div>
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Middle Name</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.middle_name || '-'}</p></div>
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Last Name</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.last_name || '-'}</p></div>
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Gender</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.gender || '-'}</p></div>
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Date of Birth</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.date_of_birth || '-'}</p></div>
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Age</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.age || '-'}</p></div>
                        </div>
                    </div>

                    <div style="background:#f9fafb;border-radius:8px;padding:16px;margin-bottom:12px;">
                        <h4 style="margin:0 0 12px 0;color:#374151;font-size:14px;font-weight:600;">📞 Emergency Contact</h4>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Contact Name</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.emergency_contact_name || '-'}</p></div>
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Contact Phone</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.emergency_contact_phone || '-'}</p></div>
                            <div style="grid-column:1 / -1;"><p style="margin:0;color:#6b7280;font-size:12px;">Relationship</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.emergency_contact_relationship || '-'}</p></div>
                        </div>
                    </div>

                    
                `;
            // Use combined totals from all billings
            let totalAmount = 0, paidAmount = 0, balance = 0;
            if (allBillings.length > 0) {
                allBillings.forEach(function(bl) {
                    totalAmount += parseFloat(bl.total_amount || 0);
                    paidAmount += parseFloat(bl.paid_amount || 0);
                    balance += parseFloat(bl.balance || 0);
                });
            } else {
                totalAmount = parseFloat(b.total_amount || 0);
                paidAmount = parseFloat(b.paid_amount || 0);
                balance = parseFloat(b.balance || 0);
            }
            var payStatus = (b.payment_status || 'unpaid').toLowerCase();
            var overallPaid = true;
            if (allBillings.length > 0) {
                allBillings.forEach(function(bl) {
                    if ((bl.payment_status || 'unpaid').toLowerCase() !== 'paid') overallPaid = false;
                });
                payStatus = overallPaid ? 'paid' : (allBillings.every(function(bl){return (bl.payment_status||'unpaid').toLowerCase()==='paid'||(bl.payment_status||'unpaid').toLowerCase()==='partial';}) ? 'partial' : 'unpaid');
            }
            const isPaid = allBillings.length > 0 ? overallPaid : payStatus === 'paid';
            const isPartial = payStatus === 'partial';
            const dueDate = b.due_date || null;
            
            document.getElementById('dentistModalPatientName').innerText = (p.first_name || '') + ' ' + (p.last_name || '');

            // Populate header avatar and registration badge (mirror staff behavior)
            try {
                const avatarEl = document.getElementById('dentistModalPatientAvatar');
                const badgeEl = document.getElementById('dentistModalPatientBadge');
                const profilePic = p.profile_picture || '';
                const isRegistered = !!p.user_id;
                if (avatarEl) {
                    if (profilePic) {
                        avatarEl.innerHTML = '<img src="../../' + profilePic + '" style="width:56px;height:56px;object-fit:cover;display:block;">';
                    } else {
                        avatarEl.textContent = (p.first_name || 'U').charAt(0).toUpperCase();
                    }
                }
                if (badgeEl) {
                    badgeEl.innerHTML = isRegistered ? '<span style="background:#d1fae5;color:#065f46;padding:6px 10px;border-radius:12px;font-weight:600;display:inline-block;font-size:0.85rem;">✓ Registered user</span>' : '<span style="background:#fee2e2;color:#991b1b;padding:6px 10px;border-radius:12px;font-weight:600;display:inline-block;font-size:0.85rem;">Not registered</span>';
                }
            } catch (e) { /* ignore */ }
            
            // Check for medical alerts
            const hasMedicalAlert = allergies === 'Yes' || 
                                   medicalConditions.toLowerCase().includes('diabetes') ||
                                   medicalConditions.toLowerCase().includes('heart') ||
                                   medicalConditions.toLowerCase().includes('blood pressure') ||
                                   medicalConditions.toLowerCase().includes('asthma');
            
            document.getElementById('patientModalContent').innerHTML = `
                <!-- Patient Basic Info -->
                

                <!-- Medical Alert -->
                ${hasMedicalAlert ? `
                <div class="medical-alert">
                    <div class="medical-alert-title">⚠️ Medical Alert - Important for Treatment</div>
                    <div class="medical-alert-grid">
                        <div class="medical-alert-item">
                            <div class="patient-info-label">Allergies</div>
                            <div class="patient-info-value ${allergies === 'Yes' ? 'danger' : ''}">${allergies}</div>
                        </div>
                        <div class="medical-alert-item">
                            <div class="patient-info-label">Diabetes</div>
                            <div class="patient-info-value ${medicalConditions.toLowerCase().includes('diabetes') ? 'danger' : ''}">${medicalConditions.toLowerCase().includes('diabetes') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="medical-alert-item">
                            <div class="patient-info-label">Heart Disease</div>
                            <div class="patient-info-value ${medicalConditions.toLowerCase().includes('heart') ? 'danger' : ''}">${medicalConditions.toLowerCase().includes('heart') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="medical-alert-item">
                            <div class="patient-info-label">High Blood Pressure</div>
                            <div class="patient-info-value ${medicalConditions.toLowerCase().includes('blood pressure') ? 'danger' : ''}">${medicalConditions.toLowerCase().includes('blood pressure') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="medical-alert-item">
                            <div class="patient-info-label">Asthma</div>
                            <div class="patient-info-value ${medicalConditions.toLowerCase().includes('asthma') ? 'danger' : ''}">${medicalConditions.toLowerCase().includes('asthma') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="medical-alert-item" style="grid-column: 1 / -1;">
                            <div class="patient-info-label">Current Medications</div>
                            <div class="patient-info-value">${medications}</div>
                        </div>
                    </div>
                </div>
                ` : ''}

                <!-- Current Queue Status -->
                ${q ? `
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: #1e40af; margin-bottom: 16px;">📋 Current Queue Status</h3>
                    <div class="patient-info-grid">
                        <div class="patient-info-item">
                            <div class="patient-info-label">Treatment Type</div>
                            <div class="patient-info-value">${q.treatment_type || 'Consultation'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Selected Teeth</div>
                            <div class="patient-info-value">${q.teeth_numbers ? getDentistTeethDisplayText(q.teeth_numbers) : 'None'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Status</div>
                            <div class="patient-info-value">
                                <span style="background: ${q.status === 'in_procedure' ? '#dcfce7' : q.status === 'waiting' ? '#fef3c7' : '#f3f4f6'}; color: ${q.status === 'in_procedure' ? '#15803d' : q.status === 'waiting' ? '#d97706' : '#6b7280'}; padding: 4px 12px; border-radius: 9999px; font-size: 0.85rem;">
                                    ${q.status ? q.status.replace('_', ' ').toUpperCase() : 'N/A'}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                ` : ''}

                ${billingId !== '0' ? `
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: #166534; margin-bottom: 16px;">💰 Billing Summary</h3>
                    <div style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:14px;">
                        <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                            <thead><tr style="background:#f0fdf4;">
                                <th style="padding:10px 12px;text-align:left;color:#166534;font-weight:600;">Service</th>
                                <th style="padding:10px 12px;text-align:right;color:#166534;font-weight:600;">Amount</th>
                                <th style="padding:10px 12px;text-align:right;color:#166534;font-weight:600;">Paid</th>
                                <th style="padding:10px 12px;text-align:right;color:#166534;font-weight:600;">Balance</th>
                                <th style="padding:10px 12px;text-align:center;color:#166534;font-weight:600;">Status</th>
                            </tr></thead><tbody>
                            ${allBillings.map(function(bl) {
                                var st = (bl.payment_status || 'unpaid').toLowerCase();
                                var sBg = '#fee2e2', sClr = '#991b1b', sLbl = 'UNPAID';
                                var rowBg = '#fff5f5';
                                if (st === 'paid') { sBg = '#dcfce7'; sClr = '#15803d'; sLbl = 'PAID'; rowBg = '#f0fdf4'; }
                                else if (st === 'partial') { sBg = '#fef3c7'; sClr = '#92400e'; sLbl = 'PARTIAL'; rowBg = '#fffbeb'; }
                                return '<tr style="border-top:1px solid #f1f5f9;background:' + rowBg + ';">' +
                                    '<td style="padding:10px 12px;color:#374151;font-weight:500;">' + (bl.service_name || 'General Service') + '</td>' +
                                    '<td style="padding:10px 12px;text-align:right;color:#111827;font-weight:600;">P' + parseFloat(bl.total_amount).toFixed(2) + '</td>' +
                                    '<td style="padding:10px 12px;text-align:right;color:#16a34a;font-weight:600;">P' + parseFloat(bl.paid_amount).toFixed(2) + '</td>' +
                                    '<td style="padding:10px 12px;text-align:right;color:' + (parseFloat(bl.balance) > 0 ? '#dc2626' : '#16a34a') + ';font-weight:600;">P' + parseFloat(bl.balance).toFixed(2) + '</td>' +
                                    '<td style="padding:10px 12px;text-align:center;"><span style="background:' + sBg + ';color:' + sClr + ';padding:3px 10px;border-radius:9999px;font-size:0.75rem;font-weight:600;">' + sLbl + '</span></td></tr>';
                            }).join('')}
                            <tr style="border-top:2px solid #d1d5db;background:#f0fdf4;">
                                <td style="padding:10px 12px;color:#166534;font-weight:700;">TOTAL</td>
                                <td style="padding:10px 12px;text-align:right;color:#166534;font-weight:700;">P${totalAmount.toFixed(2)}</td>
                                <td style="padding:10px 12px;text-align:right;color:#16a34a;font-weight:700;">P${paidAmount.toFixed(2)}</td>
                                <td style="padding:10px 12px;text-align:right;color:${balance > 0 ? '#dc2626' : '#16a34a'};font-weight:700;">P${balance.toFixed(2)}</td>
                                <td style="padding:10px 12px;text-align:center;">${formatPayStatus(payStatus)}</td>
                            </tr>
                            </tbody></table></div>
                    <div class="patient-info-grid">
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

                <!-- Consultation History -->
                <div style="background: #f9fafb; border-radius: 12px; padding: 20px; margin-top: 20px;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: #374151; margin-bottom: 16px;">📋 Consultation History</h3>
                    ${consultationHistory.length > 0 ? `
                        <div style="display:flex;flex-direction:column;gap:10px;">
                            ${consultationHistory.map(function(c, cidx) {
                                var cDate = c.created_at ? new Date(c.created_at).toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' }) : 'N/A';
                                var cTime = c.created_at ? new Date(c.created_at).toLocaleTimeString('en-PH', { hour:'2-digit', minute:'2-digit' }) : '';
                                var cDentist = c.dentist_first_name ? 'Dr. ' + c.dentist_first_name + ' ' + c.dentist_last_name : 'Unknown Dentist';
                                var cTreatment = c.queue_treatment_type || c.consent_procedure || 'General Consultation';
                                var cStatus = (c.consultation_status || 'completed').toLowerCase();
                                var statusBg = cStatus === 'completed' ? '#d1fae5' : cStatus === 'in_progress' ? '#dbeafe' : '#f3f4f6';
                                var statusClr = cStatus === 'completed' ? '#065f46' : cStatus === 'in_progress' ? '#1e40af' : '#6b7280';
                                var statusLbl = cStatus.charAt(0).toUpperCase() + cStatus.slice(1).replace('_', ' ');

                                var teethData = c.teeth_data || [];
                                var presc = c.prescription_data || [];

                                var examParts = [];
                                if (c.periodontal_screening) examParts.push('Periodontal: ' + c.periodontal_screening);
                                if (parseInt(c.occlusion_class_molar)) examParts.push('Class Molar');
                                if (parseInt(c.occlusion_overjet)) examParts.push('Overjet');
                                if (parseInt(c.occlusion_overbite)) examParts.push('Overbite');
                                if (parseInt(c.occlusion_midline_deviation)) examParts.push('Midline Dev.');
                                if (parseInt(c.occlusion_crossbite)) examParts.push('Crossbite');
                                if (parseInt(c.appliances_orthodontic)) examParts.push('Orthodontic');
                                if (parseInt(c.appliances_skyplate)) examParts.push('Skyplate');
                                if (parseInt(c.appliances_others)) examParts.push('Appliances: ' + (c.appliances_others_text || 'Other'));
                                if (parseInt(c.tmd_clenching)) examParts.push('TMD: Clenching');
                                if (parseInt(c.tmd_clicking)) examParts.push('TMD: Clicking');
                                if (parseInt(c.tmd_trismus)) examParts.push('TMD: Trismus');
                                if (parseInt(c.tmd_muscle_spasm)) examParts.push('TMD: Muscle Spasm');

                                return '<div style="background:white;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">'+
                                    '<div onclick="toggleConsultHistoryCard('+cidx+')" style="padding:14px 16px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;" onmouseover="this.style.background=\'#f8fafc\'" onmouseout="this.style.background=\'white\'">'+
                                        '<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">'+
                                            '<span style="font-weight:600;color:#111827;font-size:0.95rem;">'+cDate+' <span style="color:#6b7280;font-weight:400;font-size:0.85rem;">'+cTime+'</span></span>'+
                                            '<span style="background:#f0f9ff;color:#0ea5e9;padding:3px 10px;border-radius:6px;font-size:0.78rem;font-weight:600;">'+escapeHtml(cTreatment)+'</span>'+
                                            '<span style="font-size:0.85rem;color:#6b7280;">'+cDentist+'</span>'+
                                            '<span style="background:'+statusBg+';color:'+statusClr+';padding:3px 10px;border-radius:9999px;font-size:0.72rem;font-weight:600;">'+statusLbl+'</span>'+
                                        '</div>'+
                                        '<div style="display:flex;align-items:center;gap:8px;">'+
                                            '<button onclick="event.stopPropagation();window.location.href=\'dentist_consultation.php?past_consultation_id='+c.id+'\'" style="padding:6px 14px;background:linear-gradient(135deg,#059669,#10b981);color:white;border:none;border-radius:8px;font-size:0.78rem;font-weight:600;cursor:pointer;white-space:nowrap;display:flex;align-items:center;gap:5px;box-shadow:0 1px 3px rgba(0,0,0,0.1);transition:all 0.15s;" onmouseover="this.style.boxShadow=\'0 2px 6px rgba(0,0,0,0.15)\';this.style.transform=\'translateY(-1px)\'" onmouseout="this.style.boxShadow=\'0 1px 3px rgba(0,0,0,0.1)\';this.style.transform=\'none\'">'+
                                                '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11 8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'+
                                                'View Full'+
                                            '</button>'+
                                            '<span id="consultHistArrow_'+cidx+'" style="color:#94a3b8;font-size:0.85rem;transition:transform 0.2s;cursor:pointer;" onclick="event.stopPropagation();toggleConsultHistoryCard('+cidx+')">▼</span>'+
                                        '</div>'+
                                    '</div>'+
                                    '<div id="consultHistBody_'+cidx+'" style="display:none;padding:14px 16px;border-top:1px solid #f1f5f9;background:#fafbfc;">';

                                var bodyHtml = '';

                                if (examParts.length > 0) {
                                    bodyHtml += '<div style="margin-bottom:12px;"><div style="font-size:0.72rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:6px;">Dental Examination</div>';
                                    bodyHtml += '<div style="font-size:0.85rem;color:#334155;">'+escapeHtml(examParts.join(', '))+'</div></div>';
                                }

                                if (teethData.length > 0) {
                                    bodyHtml += '<div style="margin-bottom:12px;"><div style="font-size:0.72rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:6px;">Tooth Findings ('+teethData.length+')</div>';
                                    bodyHtml += '<div style="display:flex;flex-wrap:wrap;gap:4px;">';
                                    teethData.forEach(function(f) {
                                        var lbl = '#'+f.tooth;
                                        if (f.surface) lbl += ' ['+f.surface+']';
                                        lbl += ' '+f.code;
                                        var chipBg = f.type==='pathology'?'#fef2f2':f.type==='treatment'?'#f0f9ff':'#f1f5f9';
                                        var chipClr = f.type==='pathology'?'#991b1b':f.type==='treatment'?'#0369a1':'#475569';
                                        var chipBdr = f.type==='pathology'?'#fecaca':f.type==='treatment'?'#bae6fd':'#e2e8f0';
                                        bodyHtml += '<span style="background:'+chipBg+';border:1px solid '+chipBdr+';color:'+chipClr+';padding:2px 8px;border-radius:5px;font-size:0.72rem;font-weight:600;">'+escapeHtml(lbl)+'</span>';
                                    });
                                    bodyHtml += '</div></div>';
                                }

                                if (c.consultation_notes && c.consultation_notes.trim()) {
                                    bodyHtml += '<div style="margin-bottom:12px;"><div style="font-size:0.72rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:6px;">Consultation Notes</div>';
                                    bodyHtml += '<div style="font-size:0.85rem;color:#334155;line-height:1.5;">'+escapeHtml(c.consultation_notes)+'</div></div>';
                                }

                                if (c.dental_medical_history_notes && c.dental_medical_history_notes.trim()) {
                                    bodyHtml += '<div style="margin-bottom:12px;"><div style="font-size:0.72rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:6px;">Dental History Notes</div>';
                                    bodyHtml += '<div style="font-size:0.85rem;color:#334155;line-height:1.5;">'+escapeHtml(c.dental_medical_history_notes)+'</div></div>';
                                }

                                if (presc.length > 0) {
                                    bodyHtml += '<div style="margin-bottom:12px;"><div style="font-size:0.72rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:6px;">Prescription</div>';
                                    presc.forEach(function(p) {
                                        bodyHtml += '<div style="background:white;border:1px solid #e2e8f0;border-radius:6px;padding:8px 12px;margin-bottom:6px;font-size:0.82rem;">';
                                        bodyHtml += '<div style="font-weight:700;color:#1e293b;">'+escapeHtml(p.name||'Unnamed')+'</div>';
                                        var details = [];
                                        if (p.dosage) details.push(p.dosage);
                                        if (p.frequency) details.push(p.frequency);
                                        if (p.duration) details.push(p.duration);
                                        if (details.length) bodyHtml += '<div style="color:#64748b;margin-top:2px;">'+escapeHtml(details.join(' · '))+'</div>';
                                        if (p.instructions) bodyHtml += '<div style="color:#64748b;font-style:italic;margin-top:2px;">'+escapeHtml(p.instructions)+'</div>';
                                        bodyHtml += '</div>';
                                    });
                                    bodyHtml += '</div>';
                                }

                                if (c.consent_procedure || c.consent_date || c.consent_patient_signature || c.consent_doctor_signature || c.consent_witness_signature) {
                                    var cp = [];
                                    if (c.consent_procedure) cp.push('Procedure: '+c.consent_procedure);
                                    if (c.consent_provider) cp.push('Provider: '+c.consent_provider);
                                    if (c.consent_date) cp.push('Date: '+c.consent_date);
                                    if (c.consent_place) cp.push('Place: '+c.consent_place);
                                    bodyHtml += '<div style="margin-bottom:4px;"><div style="font-size:0.72rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:6px;">Consent</div>';
                                    bodyHtml += '<div style="font-size:0.85rem;color:#334155;">'+escapeHtml(cp.join(' · '))+'</div></div>';

                                    if (c.consent_patient_signature || c.consent_doctor_signature || c.consent_witness_signature) {
                                        bodyHtml += '<div style="margin-top:10px;">';
                                        bodyHtml += '<div style="font-size:0.72rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:8px;">Consent Signatures</div>';
                                        if (c.consent_patient_signature) {
                                            bodyHtml += '<div style="margin-bottom:10px;"><div style="font-weight:600;color:#111827;margin-bottom:4px;">Patient / Guardian' + (c.consent_patient_sign_name ? ' — ' + escapeHtml(c.consent_patient_sign_name) : '') + '</div>' + renderConsentSignature(c.consent_patient_signature) + '</div>';
                                        }
                                        if (c.consent_doctor_signature) {
                                            bodyHtml += '<div style="margin-bottom:10px;"><div style="font-weight:600;color:#111827;margin-bottom:4px;">Doctor</div>' + renderConsentSignature(c.consent_doctor_signature) + '</div>';
                                        }
                                        if (c.consent_witness_signature) {
                                            bodyHtml += '<div style="margin-bottom:10px;"><div style="font-weight:600;color:#111827;margin-bottom:4px;">Witness' + (c.consent_witness_sign_name ? ' — ' + escapeHtml(c.consent_witness_sign_name) : '') + '</div>' + renderConsentSignature(c.consent_witness_signature) + '</div>';
                                        }
                                        bodyHtml += '</div>';
                                    }
                                }

                                return bodyHtml + '</div></div>';
                            }).join('')}
                        </div>
                    ` : `
                        <p style="color:#6b7280;text-align:center;padding:20px;background:#f3f4f6;border-radius:8px;">No consultation records found.</p>
                    `}
                </div>

                <!-- Dental History - Past Treatments -->
                <div style="background: #f9fafb; border-radius: 12px; padding: 20px; margin-top: 20px;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: #374151; margin-bottom: 16px;">📜 Dental History - Past Treatments</h3>
                    ${dentalHistory.length > 0 ? `
                        <div style="display:flex;flex-direction:column;gap:12px;">
                            ${dentalHistory.map(treatment => {
                                const dentistName = treatment.dentist_first_name && treatment.dentist_last_name 
                                    ? `Dr. ${treatment.dentist_first_name} ${treatment.dentist_last_name}` 
                                    : 'Unknown Dentist';
                                const completedDate = treatment.completed_at ? new Date(treatment.completed_at) : null;
                                const treatmentDate = completedDate 
                                    ? completedDate.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
                                    : (treatment.treatment_date || 'N/A');
                                const treatmentTime = completedDate 
                                    ? completedDate.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' })
                                    : (treatment.treatment_time || '');
                                return `
                                    <div style="background:white;border:1px solid #e5e7eb;border-radius:10px;padding:16px;">
                                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
                                            <div>
                                                <div style="font-weight:600;color:#111827;font-size:1rem;">${treatment.treatment_type || 'Treatment'}</div>
                                                <div style="font-size:0.85rem;color:#6b7280;margin-top:4px;">🦷 Teeth: ${treatment.teeth_numbers || 'N/A'}</div>
                                            </div>
                                            <div style="text-align:right;">
                                                <div style="font-size:0.8rem;color:#6b7280;">${treatmentDate}</div>
                                                ${treatmentTime ? `<div style="font-size:0.75rem;color:#0ea5e9;font-weight:500;">⏰ ${treatmentTime}</div>` : ''}
                                            </div>
                                        </div>
                                        <div style="font-size:0.85rem;color:#059669;font-weight:500;padding-top:8px;border-top:1px solid #f3f4f6;">👨‍⚕️ ${dentistName}</div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    ` : `
                        <p style="color:#6b7280;text-align:center;padding:20px;background:#f3f4f6;border-radius:8px;">No previous treatment records found.</p>
                    `}
                </div>
                
                <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px;">
                    <button onclick="closePatientModal()" class="btn-cancel">Close</button>
                </div>
`;

            // Prepend headerHtml to the final modal content so header shows above other sections
            modalContent.innerHTML = headerHtml + modalContent.innerHTML;
            document.getElementById('patientModal').style.display = 'flex';
            }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        modalContent.innerHTML = `
            <div style="text-align:center;padding:40px;color:#dc2626;">
                <p style="font-size:1.1rem;font-weight:600;">Error loading patient details</p>
                <p style="color:#6b7280;">Please try again later.</p>
                <button onclick="closePatientModal()" style="margin-top:16px;padding:10px 20px;background:#6b7280;color:white;border:none;border-radius:8px;cursor:pointer;">Close</button>
            </div>`;
    });
}

function closePatientModal() {
    document.getElementById('patientModal').style.display = 'none';
}

function formatPayStatus(status) {
    const s = (status || 'unpaid').toLowerCase();
    if (s === 'paid') return '<span style="background:#d1fae5;color:#065f46;padding:4px 12px;border-radius:9999px;font-size:0.8rem;font-weight:600;">Paid</span>';
    if (s === 'partial') return '<span style="background:#fef3c7;color:#92400e;padding:4px 12px;border-radius:9999px;font-size:0.8rem;font-weight:600;">Partial</span>';
    return '<span style="background:#fee2e2;color:#dc2626;padding:4px 12px;border-radius:9999px;font-size:0.8rem;font-weight:600;">Unpaid</span>';
}

function formatDueDate(dateStr) {
    if (!dateStr) return '<span style="color:#9ca3af;">Not set</span>';
    const d = new Date(dateStr);
    const today = new Date();
    today.setHours(0,0,0,0);
    d.setHours(0,0,0,0);
    const diff = Math.ceil((d - today) / (1000*60*60*24));
    const formatted = d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
    if (diff < 0) return '<span style="color:#dc2626;font-weight:600;">' + formatted + ' (Overdue by ' + Math.abs(diff) + 'd)</span>';
    if (diff === 0) return '<span style="color:#d97706;font-weight:600;">' + formatted + ' (Due Today)</span>';
    if (diff <= 7) return '<span style="color:#d97706;">' + formatted + ' (' + diff + 'd left)</span>';
    return formatted;
}

function openRecordPayment(patientId, billingId, totalAmount, paidAmount, balance) {
    const recordModal = document.getElementById('recordPaymentModal');
    document.getElementById('recordPaymentPatientId').value = patientId || 0;
    document.getElementById('recordPaymentBillingId').value = billingId || 0;
    document.getElementById('recordPaymentTotalAmount').textContent = 'P' + parseFloat(totalAmount || 0).toFixed(2);
    document.getElementById('recordPaymentPaidSoFar').textContent = 'P' + parseFloat(paidAmount || 0).toFixed(2);
    document.getElementById('recordPaymentBalance').textContent = 'P' + parseFloat(balance || 0).toFixed(2);
    document.getElementById('recordPaymentAmount').value = parseFloat(balance || 0).toFixed(2);
    document.getElementById('recordPaymentMethod').value = 'cash';

    var breakdownHtml = '';
    var bills = window._currentAllBillings || [];
    var unpaidBills = bills.filter(function(bl) {
        return (bl.payment_status || 'unpaid').toLowerCase() !== 'paid';
    });
    if (unpaidBills.length > 0) {
        breakdownHtml = '<div style="font-weight:600;color:#374151;margin-bottom:8px;font-size:0.85rem;">Unpaid Services:</div>';
        breakdownHtml += '<table style="width:100%;border-collapse:collapse;font-size:0.82rem;">';
        unpaidBills.forEach(function(bl) {
            var st = (bl.payment_status || 'unpaid').toLowerCase();
            var sBg = '#fee2e2', sClr = '#991b1b', sLbl = 'UNPAID';
            if (st === 'partial') { sBg = '#fef3c7'; sClr = '#92400e'; sLbl = 'PARTIAL'; }
            breakdownHtml += '<tr style="border-top:1px solid #e5e7eb;">' +
                '<td style="padding:6px 0;color:#374151;font-weight:500;">' + (bl.service_name || 'General Service') + '</td>' +
                '<td style="padding:6px 0;text-align:right;color:#dc2626;font-weight:600;">P' + parseFloat(bl.balance).toFixed(2) + '</td>' +
                '<td style="padding:6px 8px;text-align:right;"><span style="background:' + sBg + ';color:' + sClr + ';padding:1px 6px;border-radius:9999px;font-size:0.7rem;font-weight:600;">' + sLbl + '</span></td></tr>';
        });
        breakdownHtml += '</table>';
    } else {
        breakdownHtml = '<div style="color:#16a34a;text-align:center;padding:4px;">All services paid</div>';
    }
    document.getElementById('recordPaymentBreakdown').innerHTML = breakdownHtml;
    recordModal.style.display = 'flex';
}

function closeRecordPaymentModal() {
    document.getElementById('recordPaymentModal').style.display = 'none';
}

function confirmRecordPayment() {
    const billingId = document.getElementById('recordPaymentBillingId').value;
    const patientId = document.getElementById('recordPaymentPatientId').value;
    const amount = document.getElementById('recordPaymentAmount').value;
    const paymentMethod = document.getElementById('recordPaymentMethod').value;

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
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeRecordPaymentModal();
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error recording payment. Please try again.');
    });
}

function openPaymentHistory(billingId) {
    if (!billingId || billingId === '0') {
        alert('No billing record found for this patient.');
        return;
    }

    const modal = document.getElementById('paymentHistoryModal');
    const tbody = document.getElementById('paymentHistoryBody');
    const empty = document.getElementById('paymentHistoryEmpty');
    modal.style.display = 'flex';
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:2rem;color:#6b7280;">Loading...</td></tr>';
    empty.style.display = 'none';

    fetch('../shared/billing_actions.php?action=get_payments&billing_id=' + encodeURIComponent(billingId))
        .then(response => response.json())
        .then(data => {
            tbody.innerHTML = '';
            if (data.success && data.payments && data.payments.length > 0) {
                empty.style.display = 'none';
                data.payments.forEach(p => {
                    const row = document.createElement('tr');
                    const methodLabel = (p.payment_method || 'Cash').charAt(0).toUpperCase() + (p.payment_method || 'Cash').slice(1);
                    row.innerHTML = '<td>' + (p.payment_date || 'N/A') + '</td><td><strong>P' + parseFloat(p.amount || 0).toFixed(2) + '</strong></td><td>' + methodLabel + '</td><td>' + (p.reference_number || '-') + '</td>';
                    tbody.appendChild(row);
                });
            } else {
                empty.style.display = 'block';
            }
        })
        .catch(error => {
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

    const modal = document.getElementById('setDueDateModal');
    document.getElementById('setDueDateBillingId').value = billingId;
    document.getElementById('setDueDateInput').value = '';

    fetch('../shared/billing_actions.php?action=get_billing&billing_id=' + encodeURIComponent(billingId))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.billing) {
                const dueDate = data.billing.due_date || '';
                if (dueDate) {
                    document.getElementById('setDueDateInput').value = dueDate;
                } else {
                    const future = new Date();
                    future.setDate(future.getDate() + 30);
                    document.getElementById('setDueDateInput').value = future.toISOString().split('T')[0];
                }
            } else {
                const future = new Date();
                future.setDate(future.getDate() + 30);
                document.getElementById('setDueDateInput').value = future.toISOString().split('T')[0];
            }
        })
        .catch(() => {
            const future = new Date();
            future.setDate(future.getDate() + 30);
            document.getElementById('setDueDateInput').value = future.toISOString().split('T')[0];
        });

    modal.style.display = 'flex';
}

function closeSetDueDateModal() {
    document.getElementById('setDueDateModal').style.display = 'none';
}

function confirmSetDueDate() {
    const billingId = document.getElementById('setDueDateBillingId').value;
    const dueDate = document.getElementById('setDueDateInput').value;

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
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeSetDueDateModal();
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error setting due date. Please try again.');
    });
}

// ESC key to close modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePatientModal();
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
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            View Details
        </a>
        <a href="javascript:void(0)" data-action="prescriptions" data-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="6" y="4" width="4" height="16"/>
                <rect x="14" y="4" width="4" height="16"/>
            </svg>
            Prescriptions
        </a>
        <a href="javascript:void(0)" data-action="billing" data-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="4" width="20" height="16" rx="2"/>
                <line x1="2" y1="10" x2="22" y2="10"/>
                <line x1="12" y1="4" x2="12" y2="10"/>
            </svg>
            Billing
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
        <a href="javascript:void(0)" data-action="treatment_plan" data-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
                <polyline points="10 9 9 9 8 9"/>
            </svg>
            Treatment Plan
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
        case 'prescriptions':
            openPatientPrescriptionModal(id);
            break;
        case 'billing':
            openBillingModal(id);
            break;
        case 'appointment':
            openAddAppointmentModal(id);
            break;
        case 'session':
            window.location.href = '../shared/quick_session.php?patient_id=' + id;
            break;
        case 'treatment_plan':
            openTreatmentPlanModal(id);
            break;
    }
}

// Open prescription modal for patient
function openPatientPrescriptionModal(patientId) {
    closePatientKebabDropdown();
    document.body.style.overflow = 'hidden';
    
    const modal = document.getElementById('patientPrescriptionModal');
    modal.style.display = 'flex';
    
    // Reset form and populate fields
    document.getElementById('patientPrescriptionForm').reset();
    document.getElementById('patientPrescriptionId').value = '';
    document.getElementById('patientPrescriptionPatientId').value = patientId;
    document.getElementById('patientPrescriptionIssueDate').value = new Date().toISOString().split('T')[0];
    
    // Clear safety info display
    document.getElementById('patientPrescriptionSafetyInfo').style.display = 'none';

    // Load patient safety info
    fetch('prescription_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_patient_info', patient_id: patientId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const patient = data.patient;
            
            document.getElementById('patientPrescriptionName').textContent = patient.patient_name || 'N/A';
            document.getElementById('patientPrescriptionAge').textContent = patient.age || 'N/A';
            document.getElementById('patientPrescriptionAllergies').textContent = patient.allergies || 'None recorded';
            document.getElementById('patientPrescriptionMeds').textContent = patient.current_medications || 'None recorded';
            
            document.getElementById('patientPrescriptionSafetyInfo').style.display = 'block';
        }
    });
}

function closePatientPrescriptionModal() {
    const modal = document.getElementById('patientPrescriptionModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

function savePatientPrescription() {
    const formData = new FormData(document.getElementById('patientPrescriptionForm'));
    const data = Object.fromEntries(formData);
    
    if (!data.medications || !data.diagnosis) {
        alert('Please fill in all required fields');
        return;
    }
    
    fetch('prescription_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'create_prescription', ...data })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closePatientPrescriptionModal();
        } else {
            alert('Error: ' + data.message);
        }
    });
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
        var pmEl = document.getElementById('patientModal');
        if (pmEl && pmEl.style.display === 'flex') {
            closePatientModal();
        }
        var rpEl = document.getElementById('recordPaymentModal');
        if (rpEl && rpEl.style.display === 'flex') {
            closeRecordPaymentModal();
        }
        var phEl = document.getElementById('paymentHistoryModal');
        if (phEl && phEl.style.display === 'flex') {
            closePaymentHistoryModal();
        }
        var sdEl = document.getElementById('setDueDateModal');
        if (sdEl && sdEl.style.display === 'flex') {
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
    closePatientKebabDropdown();
    document.body.style.overflow = 'hidden';
    
    const patient = allPatients.find(p => p.id == patientId);
    
    if (patient) {
        selectedPatientId = patientId;
        
        const fullName = (patient.first_name || '') + ' ' + (patient.middle_name || '' + ' ') + (patient.last_name || '');
        
        document.getElementById('appointmentPatientName').textContent = fullName.trim();
        document.getElementById('appointmentPatientId').value = patientId;
        document.getElementById('appointmentPatientPhone').value = patient.phone || '';
        
        document.getElementById('appointmentModal').style.display = 'flex';
    } else {
        alert('Patient not found');
    }
}

function closeAddAppointmentModal() {
    document.getElementById('appointmentModal').style.display = 'none';
    document.body.style.overflow = '';
    selectedPatientId = null;
    selectedPatientData = null;
}

var appointmentModalEl = document.getElementById('appointmentModal');
if (appointmentModalEl) {
    appointmentModalEl.addEventListener('click', function(e) {
        if (e.target === this) {
            closeAddAppointmentModal();
        }
    });
}

var treatmentPlanModalEl = document.getElementById('treatmentPlanModal');
if (treatmentPlanModalEl) {
    treatmentPlanModalEl.addEventListener('click', function(e) {
        if (e.target === this) {
            closeTreatmentPlanModal();
        }
    });
}

var recordPaymentModalEl = document.getElementById('recordPaymentModal');
if (recordPaymentModalEl) {
    recordPaymentModalEl.addEventListener('click', function(e) {
        if (e.target === this) {
            closeRecordPaymentModal();
        }
    });
}

var paymentHistoryModalEl = document.getElementById('paymentHistoryModal');
if (paymentHistoryModalEl) {
    paymentHistoryModalEl.addEventListener('click', function(e) {
        if (e.target === this) {
            closePaymentHistoryModal();
        }
    });
}

var setDueDateModalEl = document.getElementById('setDueDateModal');
if (setDueDateModalEl) {
    setDueDateModalEl.addEventListener('click', function(e) {
        if (e.target === this) {
            closeSetDueDateModal();
        }
    });
}

var addAppointmentFormEl = document.getElementById('addAppointmentForm');
if (addAppointmentFormEl) {
    addAppointmentFormEl.addEventListener('submit', function(e) {
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

// Convert teeth numbers to arch labels (matches staff terminology)
function getDentistTeethDisplayText(teethString) {
    if (!teethString || teethString.trim() === '') {
        return 'None';
    }
    
    const teeth = teethString.split(',').map(t => parseInt(t.trim())).filter(t => !isNaN(t));
    if (teeth.length === 0) {
        return 'None';
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
    <div class="modal" style="max-width: 480px;">
        <div class="modal-header">
            <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600;">📅 Schedule Appointment</h2>
            <button onclick="closeAddAppointmentModal()" class="modal-close" style="background: none; border: none; font-size: 1.5rem; color: #6b7280; cursor: pointer; padding: 4px; line-height: 1;">&times;</button>
        </div>
        <div class="modal-body">
            
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
                
                <div class="form-group">
                    <label>Treatment *</label>
                    <select name="treatment" id="addAptTreatment" class="form-control" required>
                        <option value="">Select Treatment</option>
                        </select>
                </div>

                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="appointment_date" id="addAptDate" required class="form-control" min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label>Time *</label>
                    <div id="addAptTimePrompt" style="padding: 15px; text-align: center; background: #f9fafb; border-radius: 8px; color: #6b7280; font-size: 0.85rem;">
                        Select a treatment and date to see available times.
                    </div>
                    <div id="addAptTimeSlots" class="time-slots" style="display: none; grid-template-columns: repeat(4, 1fr); gap: 6px;"></div>
                    <input type="hidden" name="appointment_time" id="addAptTime" required>
                </div>
                
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3" class="form-control" placeholder="Additional notes or instructions..."></textarea>
                </div>
                
                <div class="modal-actions" style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                    <button type="button" onclick="closeAddAppointmentModal()" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-primary">Schedule Appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>



<!-- Treatment Plan Modal -->
<div id="treatmentPlanModal" class="modal-overlay">
    <div class="modal modal-billing" style="max-width: 800px;">
        <div class="modal-header">
            <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600;">📋 Treatment Plan</h2>
            <button onclick="closeTreatmentPlanModal()" class="modal-close" style="background: none; border: none; font-size: 1.5rem; color: #6b7280; cursor: pointer; padding: 4px; line-height: 1;">&times;</button>
        </div>
        <div class="modal-body">
            
            <div id="tpPatientInfo" style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 14px 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0369a1" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <div>
                    <div style="font-size: 0.75rem; color: #0369a1; font-weight: 600;">PATIENT</div>
                    <div id="tpPatientName" style="font-weight: 600; color: #0c4a6e;"></div>
                </div>
            </div>
            
            <div id="tpLoading" style="text-align: center; padding: 40px; color: #6b7280;">
                Loading treatment plans...
            </div>
            
            <div id="tpPlansList" style="display: none;">
                <!-- Plans will be loaded here -->
            </div>
            
            <div id="tpNoPlans" style="text-align: center; padding: 40px; color: #6b7280; display: none;">
                No treatment plans found for this patient.
            </div>

            <!-- Treatment History Section -->
            <div id="tpHistorySection" style="margin-top: 24px; display: none;">
                <h3 style="font-size: 0.9rem; font-weight: 600; color: #374151; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Past Treatment Records
                </h3>
                <div id="tpHistoryList">
                    <!-- History will be loaded here -->
                </div>
            </div>
            
            <!-- Create New Treatment Plan Form -->
            <div id="tpCreateForm" style="display: none;">
                <form id="tpNewPlanForm">
                    <input type="hidden" name="patient_id" id="tpFormPatientId">
                    
                    
                    <div class="form-group">
                        <label>Teeth Numbers (click to select)</label>
                        <input type="hidden" name="teeth_numbers" id="tpTeethNumbers" value="">
                        <input type="hidden" id="tp TeethType" value="adult">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <div id="teethTypeToggle" style="display: flex; gap: 8px;">
                                <button type="button" id="btnAdult" onclick="switchTeethType('adult')" class="btn-primary" style="padding: 8px 16px; font-size: 0.8rem; font-weight: 600;">Adult</button>
                                <button type="button" id="btnPrimary" onclick="switchTeethType('primary')" class="btn-cancel" style="padding: 8px 16px; font-size: 0.8rem; font-weight: 600;">Primary</button>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" id="btnSelectUpper" onclick="selectAllUpper()" style="padding: 6px 12px; font-size: 0.75rem; background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%); color: #0369a1; border: 1px solid #7dd3fc; border-radius: 6px; cursor: pointer; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: none;">Upper Arch</button>
                                <button type="button" id="btnSelectLower" onclick="selectAllLower()" style="padding: 6px 12px; font-size: 0.75rem; background: linear-gradient(135deg, #fef3c7 0%, #fcd34d 100%); color: #92400e; border: 1px solid #fde68a; border-radius: 6px; cursor: pointer; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: none;">Lower Arch</button>
                            </div>
                        </div>
                        <div class="tooth-chart-modal" id="toothChartContainer">
                            <!-- Adult Teeth - Upper Arch (Right to Left) -->
                            <div id="adultTeeth">
                            <div style="text-align: center; margin-bottom: 10px;">
                                <span style="font-size: 0.7rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">Upper Arch</span>
                            </div>
                            <div style="display: flex; justify-content: center; gap: 4px; margin-bottom: 16px;">
                                <div id="adultUpperRight" style="display: flex; gap: 4px; justify-content: flex-end;"></div>
                                <div style="width: 24px;"></div>
                                <div id="adultUpperLeft" style="display: flex; gap: 4px; justify-content: flex-start;"></div>
                            </div>
                            <!-- Adult Teeth - Lower Arch (Right to Left) -->
                            <div style="text-align: center; margin-bottom: 10px;">
                                <span style="font-size: 0.7rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">Lower Arch</span>
                            </div>
                            <div style="display: flex; justify-content: center; gap: 4px; margin-bottom: 12px;">
                                <div id="adultLowerRight" style="display: flex; gap: 4px; justify-content: flex-end;"></div>
                                <div style="width: 24px;"></div>
                                <div id="adultLowerLeft" style="display: flex; gap: 4px; justify-content: flex-start;"></div>
                            </div>
                            </div>
                            
                            <!-- Primary Teeth - Upper Arch -->
                            <div id="primaryTeeth" style="display: none;">
                                <div style="text-align: center; margin-bottom: 10px;">
                                    <span style="font-size: 0.7rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">Upper Arch (Primary)</span>
                                </div>
                                <div style="display: flex; justify-content: center; gap: 4px; margin-bottom: 16px;">
                                    <div id="primaryUpperRight" style="display: flex; gap: 4px; justify-content: flex-end;"></div>
                                    <div style="width: 24px;"></div>
                                    <div id="primaryUpperLeft" style="display: flex; gap: 4px; justify-content: flex-start;"></div>
                                </div>
                                <!-- Primary Teeth - Lower Arch -->
                                <div style="text-align: center; margin-bottom: 10px;">
                                    <span style="font-size: 0.7rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">Lower Arch (Primary)</span>
                                </div>
                                <div style="display: flex; justify-content: center; gap: 2px; margin-bottom: 16px;">
                                    <div id="primaryLowerRight" style="display: flex; gap: 2px; justify-content: flex-end;"></div>
                                    <div style="width: 20px;"></div>
                                    <div id="primaryLowerLeft" style="display: flex; gap: 2px; justify-content: flex-start;"></div>
                                </div>
                            </div>
                        </div>
                        <div style="margin-top: 12px; display: flex; justify-content: space-between; align-items: center;">
                            <div style="font-size: 0.85rem; color: #374151;">Selected: <span id="selectedTeethText" style="font-weight: 600;">None</span></div>
                            <button type="button" onclick="clearTeeth()" style="font-size: 0.75rem; color: #6b7280; background: none; border: none; cursor: pointer; text-decoration: underline;">Clear All</button>
</div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom:16px;">
                        <label>Number of Sessions</label>
                        <div id="sessionCountBtns" style="display:flex; gap:6px; flex-wrap:wrap;">
                            <button type="button" class="sess-btn active" data-count="1" onclick="setSessionCount(1)">1</button>
                            <button type="button" class="sess-btn" data-count="2" onclick="setSessionCount(2)">2</button>
                            <button type="button" class="sess-btn" data-count="3" onclick="setSessionCount(3)">3</button>
                            <button type="button" class="sess-btn" data-count="4" onclick="setSessionCount(4)">4</button>
                            <button type="button" class="sess-btn" data-count="5" onclick="setSessionCount(5)">5</button>
                            <button type="button" class="sess-btn" data-count="6" onclick="setSessionCount(6)">6</button>
                            <button type="button" class="sess-btn" data-count="7" onclick="setSessionCount(7)">7</button>
                            <button type="button" class="sess-btn" data-count="8" onclick="setSessionCount(8)">8</button>
                        </div>
                        <input type="hidden" name="total_sessions" id="tpTotalSessions" value="1">
                    </div>

                    <div id="sessionRowsContainer"></div>
                    
                    
                    <div class="form-group">
                        <label>Estimated Cost</label>
                        <input type="number" name="estimated_cost" class="form-control" placeholder="0.00" step="0.01">
                    </div>
                    
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="3" class="form-control" placeholder="Treatment details, procedures, etc."></textarea>
                    </div>
                </form>
            </div>
            
        </div>
        <div class="modal-footer">
            <button type="button" id="tpBtnCancel" onclick="closeTreatmentPlanModal()" class="btn-cancel">Close</button>
            <button type="button" id="tpBtnCreate" onclick="showTpCreateForm()" class="btn-primary" style="display: none;">+ Create New Plan</button>
            <button type="button" id="tpBtnSave" onclick="saveTreatmentPlan()" class="btn-primary" style="display: none;">Save Plan</button>
            <button type="button" id="tpBtnBack" onclick="showTpPlansList()" class="btn-cancel" style="display: none;">Back</button>
        </div>
    </div>
</div>

<!-- Patient Prescription Modal -->
<div id="patientPrescriptionModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h2 id="patientPrescriptionModalTitle" style="margin: 0; font-size: 1.25rem; font-weight: 600;">💊 New Prescription</h2>
            <button class="modal-close" onclick="closePatientPrescriptionModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="patientPrescriptionSafetyInfo" class="safety-info" style="display: none; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; margin-bottom: 24px;">
                <h3 style="font-size: 1rem; font-weight: 600; color: #15803d; margin: 0 0 12px 0;">Patient Safety Information</h3>
                <div class="safety-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                    <div class="safety-item">
                        <div style="font-size: 0.85rem; color: #475569; margin-bottom: 4px;">Patient Name</div>
                        <div id="patientPrescriptionName" style="font-weight: 500; color: #0f172a;"></div>
                    </div>
                    <div class="safety-item">
                        <div style="font-size: 0.85rem; color: #475569; margin-bottom: 4px;">Age</div>
                        <div id="patientPrescriptionAge" style="font-weight: 500; color: #0f172a;"></div>
                    </div>
                    <div class="safety-item">
                        <div style="font-size: 0.85rem; color: #475569; margin-bottom: 4px;">Allergies</div>
                        <div id="patientPrescriptionAllergies" style="color: #dc2626; font-weight: 500;"></div>
                    </div>
                    <div class="safety-item">
                        <div style="font-size: 0.85rem; color: #475569; margin-bottom: 4px;">Medical History</div>
                        <div id="patientPrescriptionMeds" style="color: #059669; font-weight: 500;"></div>
                    </div>
                </div>
            </div>
            
            <form id="patientPrescriptionForm">
                <input type="hidden" id="patientPrescriptionId" name="prescription_id">
                <input type="hidden" id="patientPrescriptionPatientId" name="patient_id">
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label for="patientPrescriptionIssueDate" style="display: block; margin-bottom: 6px; font-weight: 500; color: #334155; font-size: 0.9rem;">Issue Date: *</label>
                        <input type="date" id="patientPrescriptionIssueDate" name="issue_date" required style="width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box;">
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="patientPrescriptionDiagnosis" style="display: block; margin-bottom: 6px; font-weight: 500; color: #334155; font-size: 0.9rem;">Diagnosis: *</label>
                    <textarea id="patientPrescriptionDiagnosis" name="diagnosis" rows="2" required placeholder="Reason for prescription..." style="width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box;"></textarea>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="patientPrescriptionMedications" style="display: block; margin-bottom: 6px; font-weight: 500; color: #334155; font-size: 0.9rem;">Medications: *</label>
                    <textarea id="patientPrescriptionMedications" name="medications" rows="3" required placeholder="e.g., Amoxicillin 500mg - 3 times daily for 7 days" style="width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box;"></textarea>
                </div>
                
                <div class="form-group" style="margin-bottom: 24px;">
                    <label for="patientPrescriptionInstructions" style="display: block; margin-bottom: 6px; font-weight: 500; color: #334155; font-size: 0.9rem;">Instructions:</label>
                    <textarea id="patientPrescriptionInstructions" name="instructions" rows="2" placeholder="Additional patient instructions..." style="width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box;"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
            <button type="button" class="btn-cancel" onclick="closePatientPrescriptionModal()">Cancel</button>
            <button type="button" class="btn-primary" onclick="savePatientPrescription()">Save Prescription</button>
        </div>
    </div>
</div>



<!-- Billing Modal -->
<div id="billingModal" class="modal-overlay">
    <div class="modal modal-billing">
        <div class="modal-header">
            <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600;">💰 Billing Details</h2>
            <button onclick="closeBillingModal()" class="modal-close" style="background: none; border: none; font-size: 1.5rem; color: #6b7280; cursor: pointer; padding: 4px; line-height: 1;">&times;</button>
        </div>
        <div class="modal-body">
            
            <div id="billingLoading" style="text-align: center; padding: 40px; color: #6b7280;">
                Loading billing information...
            </div>
            
            <div id="billingContent" style="display: none;">
                <!-- Patient Info -->
                <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 14px 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0369a1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    <div>
                        <div style="font-size: 0.75rem; color: #0369a1; font-weight: 600;">PATIENT</div>
                        <div id="billingPatientName" style="font-weight: 600; color: #0c4a6e;"></div>
                    </div>
                </div>
                
                <!-- Treatment Info -->
                <div style="background: #f9fafb; border-radius: 8px; padding: 14px 16px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-size: 0.75rem; color: #6b7280; font-weight: 600;">TREATMENT</div>
                            <div id="billingTreatment" style="font-weight: 600; color: #111827; margin-top: 4px;"></div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 0.75rem; color: #6b7280; font-weight: 600;">DATE</div>
                            <div id="billingDate" style="font-weight: 600; color: #111827; margin-top: 4px;"></div>
                        </div>
                    </div>
                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #e5e7eb;">
                        <div style="font-size: 0.75rem; color: #6b7280; font-weight: 600;">TEETH</div>
                        <div id="billingTeeth" style="font-weight: 600; color: #111827; margin-top: 4px;"></div>
                    </div>
                </div>
                
                <!-- Amount Display -->
                <div id="billingAmountDisplay" class="billing-amount-display">
                    <div class="billing-amount-label">Total Amount</div>
                    <div class="billing-amount-value editable" id="billingTotalAmount" onclick="showEditAmountForm()">₱0</div>
                    <div id="billingEstimatedNote" style="font-size: 0.75rem; color: #6b7280; margin-top: 8px;"></div>
                </div>
                
                <!-- Edit Amount Form -->
                <div id="editAmountForm" class="edit-amount-form">
                    <div class="form-group">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 8px;">Enter New Amount (₱)</label>
                        <input type="number" id="newBillingAmount" class="form-control" placeholder="0.00" min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 8px;">Notes (Optional)</label>
                        <input type="text" id="billingNotes" class="form-control" style="font-size: 0.9rem; font-weight: 400; text-align: left;" placeholder="Reason for adjustment...">
                    </div>
                    <div class="billing-actions" style="margin-top: 12px; padding-top: 12px;">
                        <button type="button" onclick="cancelEditAmount()" class="btn-cancel-edit">Cancel</button>
                        <button type="button" onclick="saveNewAmount()" class="btn-save">Save Changes</button>
                    </div>
                </div>
                
                <!-- Payment Details -->
                <div id="billingAllRecordsList"></div>
                <div class="billing-section">
                    <h4>Payment Summary</h4>
                    <div class="billing-details-grid">
                        <div class="billing-detail-item">
                            <div class="billing-detail-label">Total Amount</div>
                            <div class="billing-detail-value" id="billingTotal">₱0</div>
                        </div>
                        <div class="billing-detail-item">
                            <div class="billing-detail-label">Paid</div>
                            <div class="billing-detail-value" id="billingPaid" style="color: #059669;">₱0</div>
                        </div>
                        <div class="billing-detail-item">
                            <div class="billing-detail-label">Balance</div>
                            <div class="billing-detail-value" id="billingBalance" style="color: #dc2626;">₱0</div>
                        </div>
                        <div class="billing-detail-item">
                            <div class="billing-detail-label">Status</div>
                            <div class="billing-detail-value"><span id="billingStatus" class="billing-status-badge billing-status-unpaid">Unpaid</span></div>
                        </div>
                    </div>
                </div>
                
                <div class="billing-actions" style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                    <button type="button" onclick="closeBillingModal()" class="btn-cancel">Close</button>
                    <button type="button" onclick="showEditAmountForm()" class="btn-primary">✏️ Edit Amount</button>
                </div>
        </div>
    </div>
</div>

<script>
// Billing Modal Functions
let billingQueueId = null;
let billingPatientId = null;

function openBillingModal(patientId) {
    console.log('Opening billing for patient ID:', patientId);
    
    // Close kebab dropdown first
    closePatientKebabDropdown();
    document.body.style.overflow = 'hidden';
    
    // Find the patient's queue record
    const patient = allPatients.find(p => p.id == patientId);
    console.log('Found patient:', patient);
    
    // Get the latest queue for this patient
    console.log('Fetching billing for patient_id:', patientId);
    fetch('dentist_billing_actions.php?action=get_billing&patient_id=' + parseInt(patientId))
    .then(response => {
        console.log('Billing response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Billing response data:', data);
        if (data.success) {
            showBillingModal(data.billing, data.all_billings || []);
        } else {
            console.error('Billing API error:', data.message);
            alert('Error: ' + (data.message || 'Could not load billing'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error loading billing information: ' + error.message);
    });
}

function showBillingModal(billing, allBillings) {
    allBillings = allBillings || [];
    billingQueueId = billing.queue_id;
    billingPatientId = billing.patient_id;
    
    document.getElementById('billingPatientName').textContent = billing.patient_name || 'Unknown';
    document.getElementById('billingTreatment').textContent = billing.service_name || billing.treatment_type || 'General Consultation';
    document.getElementById('billingDate').textContent = billing.queue_date || 'N/A';
    document.getElementById('billingTeeth').textContent = billing.teeth_numbers ? getDentistTeethDisplayText(billing.teeth_numbers) : 'None';
    
    // Amount display - use combined totals when multiple billings
    const amountDisplay = document.getElementById('billingTotalAmount');
    const estimatedNote = document.getElementById('billingEstimatedNote');
    const billingTotal = document.getElementById('billingTotal');
    const billingPaid = document.getElementById('billingPaid');
    const billingBalance = document.getElementById('billingBalance');
    const billingStatus = document.getElementById('billingStatus');
    
    let totalAmount, paidAmount, balance;
    if (allBillings.length > 1) {
        totalAmount = 0; paidAmount = 0; balance = 0;
        allBillings.forEach(function(bl) {
            totalAmount += parseFloat(bl.total_amount || 0);
            paidAmount += parseFloat(bl.paid_amount || 0);
            balance += parseFloat(bl.balance || 0);
        });
    } else {
        totalAmount = billing.total_amount || billing.estimated_amount || 0;
        paidAmount = billing.paid_amount || 0;
        balance = billing.balance ?? (totalAmount - paidAmount);
    }
    
    amountDisplay.textContent = '₱' + totalAmount.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    billingTotal.textContent = '₱' + totalAmount.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    billingPaid.textContent = '₱' + paidAmount.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    billingBalance.textContent = '₱' + balance.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // Render all billing records table
    const recordsListEl = document.getElementById('billingAllRecordsList');
    if (recordsListEl) {
        if (allBillings.length > 1) {
            var rHtml = '<div style="margin-bottom:16px;"><h4 style="font-size:0.9rem;font-weight:600;color:#374151;margin:0 0 8px;">All Billing Records</h4>';
            rHtml += '<div style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;"><table style="width:100%;border-collapse:collapse;font-size:0.82rem;">';
            rHtml += '<thead><tr style="background:#f9fafb;"><th style="padding:7px 10px;text-align:left;color:#6b7280;font-weight:600;">Service</th>';
            rHtml += '<th style="padding:7px 10px;text-align:right;color:#6b7280;font-weight:600;">Amount</th>';
            rHtml += '<th style="padding:7px 10px;text-align:right;color:#6b7280;font-weight:600;">Paid</th>';
            rHtml += '<th style="padding:7px 10px;text-align:right;color:#6b7280;font-weight:600;">Balance</th>';
            rHtml += '<th style="padding:7px 10px;text-align:center;color:#6b7280;font-weight:600;">Status</th></tr></thead><tbody>';
            allBillings.forEach(function(bl) {
                var st = (bl.payment_status || 'unpaid').toLowerCase();
                var sBg = '#fee2e2', sClr = '#991b1b', sLbl = 'UNPAID';
                if (st === 'paid') { sBg = '#dcfce7'; sClr = '#15803d'; sLbl = 'PAID'; }
                else if (st === 'partial') { sBg = '#fef3c7'; sClr = '#92400e'; sLbl = 'PARTIAL'; }
                rHtml += '<tr style="border-top:1px solid #f1f5f9;"><td style="padding:7px 10px;color:#374151;font-weight:500;">' + (bl.service_name || 'Service') + '</td>';
                rHtml += '<td style="padding:7px 10px;text-align:right;color:#111827;font-weight:600;">₱' + parseFloat(bl.total_amount).toLocaleString('en-PH', {minimumFractionDigits:2}) + '</td>';
                rHtml += '<td style="padding:7px 10px;text-align:right;color:#16a34a;font-weight:600;">₱' + parseFloat(bl.paid_amount).toLocaleString('en-PH', {minimumFractionDigits:2}) + '</td>';
                rHtml += '<td style="padding:7px 10px;text-align:right;color:#dc2626;font-weight:600;">₱' + parseFloat(bl.balance).toLocaleString('en-PH', {minimumFractionDigits:2}) + '</td>';
                rHtml += '<td style="padding:7px 10px;text-align:center;"><span style="background:' + sBg + ';color:' + sClr + ';padding:2px 8px;border-radius:9999px;font-size:0.72rem;font-weight:600;">' + sLbl + '</span></td></tr>';
            });
            rHtml += '</tbody></table></div></div>';
            recordsListEl.innerHTML = rHtml;
        } else {
            recordsListEl.innerHTML = '';
        }
    }
    
    // Estimated note
    if (billing.estimated_amount && billing.estimated_amount > 0 && billing.total_amount === 0) {
        estimatedNote.textContent = '(Estimated from services: ₱' + billing.estimated_amount.toLocaleString('en-PH') + ')';
    } else if (billing.estimated_amount && billing.estimated_amount !== billing.total_amount) {
        estimatedNote.textContent = '(Default price: ₱' + billing.estimated_amount.toLocaleString('en-PH') + ')';
    } else {
        estimatedNote.textContent = '';
    }
    
    // Status badge
    let statusClass = 'billing-status-unpaid';
    let statusText = 'Unpaid';
    if (billing.payment_status === 'paid') {
        statusClass = 'billing-status-paid';
        statusText = 'Paid';
    } else if (billing.payment_status === 'partial') {
        statusClass = 'billing-status-partial';
        statusText = 'Partial';
    }
    billingStatus.className = 'billing-status-badge ' + statusClass;
    billingStatus.textContent = statusText;
    
    // Reset form
    document.getElementById('newBillingAmount').value = totalAmount > 0 ? totalAmount : '';
    document.getElementById('billingNotes').value = '';
    
    // Show content, hide loading
    document.getElementById('billingLoading').style.display = 'none';
    document.getElementById('billingContent').style.display = 'block';
    document.getElementById('billingAmountDisplay').style.display = 'block';
    document.getElementById('editAmountForm').classList.remove('active');
    
    // Show modal
    document.getElementById('billingModal').style.display = 'flex';
}

function closeBillingModal() {
    document.getElementById('billingModal').style.display = 'none';
    document.body.style.overflow = '';
    billingQueueId = null;
    billingPatientId = null;
}

// Treatment Plan Modal Functions
let treatmentPlanPatientId = null;

function openTreatmentPlanModal(patientId) {
    closePatientKebabDropdown();
    document.body.style.overflow = 'hidden';
    
    treatmentPlanPatientId = patientId;
    const patient = allPatients.find(p => p.id == patientId);
    
    // Update patient name and link
    document.getElementById('tpPatientName').textContent = patient ? 
        ((patient.first_name || '') + ' ' + (patient.middle_name || '') + ' ' + (patient.last_name || '')).trim() : 'Unknown';
    
    // Reset form
    document.getElementById('tpNewPlanForm').reset();
    document.getElementById('tpCreateForm').style.display = 'none';
    document.getElementById('tpPlansList').style.display = 'none';
    document.getElementById('tpNoPlans').style.display = 'none';
    document.getElementById('tpLoading').style.display = 'block';
    
    // Show/hide buttons
    document.getElementById('tpBtnCreate').style.display = 'inline-flex';
    document.getElementById('tpBtnSave').style.display = 'none';
    document.getElementById('tpBtnBack').style.display = 'none';
    document.getElementById('tpBtnCancel').style.display = 'inline-flex';
    
    // Show modal
    document.getElementById('treatmentPlanModal').style.display = 'flex';
    
    // Fetch treatment plans
    fetch('../shared/process_treatment_plan.php?patient_id=' + patientId)
    .then(response => response.json())
    .then(data => {
        document.getElementById('tpLoading').style.display = 'none';
        
        if (data.success) {
            // Render Plans
            if (data.plans && data.plans.length > 0) {
                renderTreatmentPlans(data.plans);
                document.getElementById('tpPlansList').style.display = 'block';
                document.getElementById('tpBtnCreate').textContent = '+ Create New Plan';
            } else {
                let debugInfo = '';
                if (data.debug) {
                    debugInfo = ` (Patient ID: ${data.debug.patient_id})`;
                }
                document.getElementById('tpNoPlans').textContent = 'No treatment plans found for this patient.' + debugInfo;
                document.getElementById('tpNoPlans').style.display = 'block';
                document.getElementById('tpBtnCreate').textContent = '+ Create Treatment Plan';
            }

            // Render History
            if (data.history && data.history.length > 0) {
                renderTreatmentHistory(data.history);
                document.getElementById('tpHistorySection').style.display = 'block';
            } else {
                document.getElementById('tpHistorySection').style.display = 'none';
            }
        } else {
            document.getElementById('tpNoPlans').textContent = data.message || 'Error loading treatment plans';
            document.getElementById('tpNoPlans').style.display = 'block';
            document.getElementById('tpBtnCreate').textContent = '+ Create Treatment Plan';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('tpLoading').textContent = 'Error loading treatment plans';
    });
}

function renderTreatmentPlans(plans) {
    const container = document.getElementById('tpPlansList');
    container.style.display = 'block';
    
    let html = '';
    plans.forEach(plan => {
        const total = plan.total_sessions || 1;
        const completed = plan.completed_sessions || 0;
        const percent = Math.round((completed / total) * 100);
        const statusColor = plan.status === 'completed' ? '#22c55e' : plan.status === 'in_progress' ? '#f59e0b' : '#3b82f6';
        const statusText = plan.status === 'completed' ? 'Completed' : plan.status === 'in_progress' ? 'In Progress' : 'Active';
        
        html += `
            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                    <div>
                        <div style="font-weight: 600; font-size: 1rem;">${plan.treatment_name || plan.treatment_type || 'General'}</div>
                        <div style="font-size: 0.85rem; color: #6b7280;">${plan.treatment_type || ''}</div>
                    </div>
                    <span style="background: ${statusColor}20; color: ${statusColor}; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">
                        ${statusText}
                    </span>
                </div>
                
                <div style="margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span style="font-size: 0.85rem; color: #6b7280;">Progress</span>
                        <span style="font-size: 0.85rem; font-weight: 600;">${completed} / ${total} sessions (${percent}%)</span>
                    </div>
                    <div style="height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                        <div style="width: ${percent}%; height: 100%; background: linear-gradient(90deg, #0ea5e9, #0284c7); border-radius: 4px;"></div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 0.85rem;">
                    <div>
                        <div style="color: #6b7280;">Teeth</div>
                        <div style="font-weight: 500;">${plan.teeth_numbers || 'Not specified'}</div>
                    </div>
                    <div>
                        <div style="color: #6b7280;">Next Session</div>
                        <div style="font-weight: 500;">${plan.next_session_date ? new Date(plan.next_session_date).toLocaleDateString() : 'Not scheduled'}</div>
                    </div>
                </div>
                ${renderSessionsHtml(plan.sessions_data)}
                ${plan.notes ? `
                    <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e5e7eb;">
                        <div style="color: #6b7280; font-size: 0.8rem; margin-bottom: 4px;">Notes</div>
                        <div style="font-size: 0.9rem;">${plan.notes}</div>
                    </div>
                ` : ''}
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function renderTreatmentHistory(history) {
    const container = document.getElementById('tpHistoryList');
    let html = '';
    
    history.forEach(item => {
        const date = new Date(item.treatment_date).toLocaleDateString('en-PH', { 
            year: 'numeric', month: 'short', day: 'numeric' 
        });
        const doctor = item.doctor_first && item.doctor_last ? `Dr. ${item.doctor_first} ${item.doctor_last}` : 'Dentist';
        
        html += `
            <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-weight: 600; font-size: 0.85rem; color: #111827;">${item.service_name || item.treatment_type || 'Treatment'}</div>
                    <div style="font-size: 0.75rem; color: #6b7280; margin-top: 2px;">
                        <span>🦷 ${item.teeth_numbers || 'N/A'}</span>
                        <span style="margin: 0 8px; color: #e5e7eb;">|</span>
                        <span>👨‍⚕️ ${doctor}</span>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.8rem; font-weight: 500; color: #374151;">${date}</div>
                    <div style="font-size: 0.7rem; color: #059669; font-weight: 600; text-transform: uppercase; margin-top: 2px;">Completed</div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function renderSessionsHtml(sessionsData) {
    if (!sessionsData) return '';
    try {
        const sessions = JSON.parse(sessionsData);
        if (!sessions.length) return '';
        let sHtml = '<div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e5e7eb;"><div style="color: #6b7280; font-size: 0.8rem; margin-bottom: 6px;">All Sessions</div><div style="font-size: 0.85rem;">';
        sessions.forEach(function(s, idx) {
            const parts = [];
            if (s.date) {
                const d = new Date(s.date + 'T' + (s.time || '00:00:00'));
                parts.push(d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }));
            }
            if (s.time) {
                const d = new Date('2000-01-01T' + s.time);
                parts.push(d.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' }));
            }
            let sessionStr = '<div style="margin-bottom: 4px;"><span style="font-weight:500;">Session ' + (idx + 1) + ':</span> ' + (parts.length ? parts.join(' @ ') : '<span style="color:#9ca3af;">Not scheduled</span>');
            if (s.treatment) {
                sessionStr += ' <span style="color:#059669;">\u2014 ' + escapeHtml(s.treatment) + '</span>';
            }
            sessionStr += '</div>';
            if (s.notes) {
                sessionStr += '<div style="margin: 0 0 6px 16px; font-size: 0.8rem; color: #6b7280;">\uD83D\uDCDD ' + escapeHtml(s.notes) + '</div>';
            }
            sHtml += sessionStr;
        });
        sHtml += '</div></div>';
        return sHtml;
    } catch(e) {
        return '';
    }
}

function closeTreatmentPlanModal() {
    document.getElementById('treatmentPlanModal').style.display = 'none';
    document.body.style.overflow = '';
    treatmentPlanPatientId = null;
    
    // Reset form state
    document.getElementById('tpNewPlanForm').reset();
    document.getElementById('sessionRowsContainer').innerHTML = '';
    document.querySelectorAll('.sess-btn').forEach(btn => btn.classList.toggle('active', parseInt(btn.dataset.count) === 1));
    document.getElementById('tpTotalSessions').value = '1';
}

function showTpCreateForm() {
    document.getElementById('tpLoading').style.display = 'none';
    document.getElementById('tpPlansList').style.display = 'none';
    document.getElementById('tpNoPlans').style.display = 'none';
    document.getElementById('tpCreateForm').style.display = 'block';
    
    // Reset form fields
    document.querySelector('input[name="estimated_cost"]').value = '';
    
    // Initialize session rows with default count 1
    setSessionCount(1);
    
    // Show arch buttons (always visible since per-session treatments handle modes)
    const btnUpper = document.getElementById('btnSelectUpper');
    const btnLower = document.getElementById('btnSelectLower');
    if (btnUpper) btnUpper.style.display = 'inline-block';
    if (btnLower) btnLower.style.display = 'inline-block';
    
    // Load services from database
    loadTpServices();
    
    // Clear teeth and set default based on patient age
    clearTeeth();
    const patient = allPatients.find(p => p.id == treatmentPlanPatientId);
    if (patient) {
        const age = parseInt(patient.age) || 0;
        // Default to primary teeth for children under 12
        if (age < 12) {
            switchTeethType('primary');
        } else {
            switchTeethType('adult');
        }
    } else {
        switchTeethType('adult');
    }
    
    // Show/hide buttons
    document.getElementById('tpBtnCreate').style.display = 'none';
    document.getElementById('tpBtnSave').style.display = 'inline-flex';
    document.getElementById('tpBtnBack').style.display = 'inline-flex';
    document.getElementById('tpBtnCancel').style.display = 'none';
    
    document.getElementById('tpFormPatientId').value = treatmentPlanPatientId;
}

function showTpPlansList() {
    document.getElementById('tpCreateForm').style.display = 'none';
    document.getElementById('tpPlansList').style.display = 'block';
    
    // Show/hide buttons
    document.getElementById('tpBtnCreate').style.display = 'inline-flex';
    document.getElementById('tpBtnSave').style.display = 'none';
    document.getElementById('tpBtnBack').style.display = 'none';
    document.getElementById('tpBtnCancel').style.display = 'inline-flex';
}

// Auto-populate Treatment Type based on Treatment Name
let selectedTeeth = [];

function toggleToothTp(element) {
    const toothNumber = element.dataset.tooth;
    
    if (selectedTeeth.includes(toothNumber)) {
        selectedTeeth = selectedTeeth.filter(t => t !== toothNumber);
        element.classList.remove('selected');
    } else {
        selectedTeeth.push(toothNumber);
        element.classList.add('selected');
    }
    
    // Sort teeth numerically
    selectedTeeth.sort((a, b) => parseInt(a) - parseInt(b));
    
    // Update hidden input and display
    document.getElementById('tpTeethNumbers').value = selectedTeeth.join(', ');
    document.getElementById('selectedTeethText').textContent = selectedTeeth.length > 0 ? selectedTeeth.join(', ') : 'None';
}

function clearTeeth() {
    selectedTeeth = [];
    document.querySelectorAll('.tooth.selected').forEach(el => el.classList.remove('selected'));
    document.getElementById('tpTeethNumbers').value = '';
    document.getElementById('selectedTeethText').textContent = 'None';
}

// Switch between Adult and Primary teeth
let currentTeethType = 'adult';

function switchTeethType(type) {
    currentTeethType = type;
    clearTeeth();
    
    if (type === 'adult') {
        document.getElementById('adultTeeth').style.display = 'block';
        document.getElementById('primaryTeeth').style.display = 'none';
        document.getElementById('btnAdult').className = 'btn-primary';
        document.getElementById('btnPrimary').className = 'btn-cancel';
    } else {
        document.getElementById('adultTeeth').style.display = 'none';
        document.getElementById('primaryTeeth').style.display = 'block';
        document.getElementById('btnAdult').className = 'btn-cancel';
        document.getElementById('btnPrimary').className = 'btn-primary';
    }
}

// Get tooth size class
function getToothClass(num) {
    const n = parseInt(num);
    const molars = [18,17,16,26,27,28,48,47,46,36,37,38];
    const incisors = [11,12,21,22,41,42,31,32];
    const canines = [13,23,43,33];
    if (molars.includes(n)) return 'molar';
    if (incisors.includes(n)) return 'incisor';
    if (canines.includes(n)) return 'canine';
    return 'premolar';
}

// Initialize teeth on load
document.addEventListener('DOMContentLoaded', function() {
    function renderTooth(n) {
        return `<div class="tooth ${getToothClass(n)}" data-tooth="${n}" onclick="toggleToothTp(this)">${n}</div>`;
    }
    const adultUpperRight = [18,17,16,15,14,13,12,11].map(renderTooth).join('');
    const adultUpperLeft = [21,22,23,24,25,26,27,28].map(renderTooth).join('');
    const adultLowerRight = [48,47,46,45,44,43,42,41].map(renderTooth).join('');
    const adultLowerLeft = [31,32,33,34,35,36,37,38].map(renderTooth).join('');
    
    if (document.getElementById('adultUpperRight')) document.getElementById('adultUpperRight').innerHTML = adultUpperRight;
    if (document.getElementById('adultUpperLeft')) document.getElementById('adultUpperLeft').innerHTML = adultUpperLeft;
    if (document.getElementById('adultLowerRight')) document.getElementById('adultLowerRight').innerHTML = adultLowerRight;
    if (document.getElementById('adultLowerLeft')) document.getElementById('adultLowerLeft').innerHTML = adultLowerLeft;
    
    const primaryUpperRight = [55,54,53,52,51].map(renderTooth).join('');
    const primaryUpperLeft = [61,62,63,64,65].map(renderTooth).join('');
    const primaryLowerRight = [85,84,83,82,81].map(renderTooth).join('');
    const primaryLowerLeft = [71,72,73,74,75].map(renderTooth).join('');
    
    if (document.getElementById('primaryUpperRight')) document.getElementById('primaryUpperRight').innerHTML = primaryUpperRight;
    if (document.getElementById('primaryUpperLeft')) document.getElementById('primaryUpperLeft').innerHTML = primaryUpperLeft;
    if (document.getElementById('primaryLowerRight')) document.getElementById('primaryLowerRight').innerHTML = primaryLowerRight;
    if (document.getElementById('primaryLowerLeft')) document.getElementById('primaryLowerLeft').innerHTML = primaryLowerLeft;
    
});






// 1. Fetch Services for caching and appointment modal
function loadTpServices() {
    fetch('../admin/get_services.php')
    .then(response => response.json())
    .then(data => {
        const selectApt = document.getElementById('addAptTreatment');
        window._cachedServices = data.services || [];
        
        let optionsHTML = '<option value="">Select Treatment</option>';
        
        if (data.services && data.services.length > 0) {
            data.services.forEach(service => {
                const duration = service.duration_minutes || 15;
                optionsHTML += `<option value="${service.id}" data-mode="${service.mode || ''}" data-price="${service.price || ''}" data-duration="${duration}">${service.name} (${duration} min)</option>`;
            });
        }
        
        if (selectApt) selectApt.innerHTML = optionsHTML;

        // Repopulate all existing session treatment dropdowns
        document.querySelectorAll('.session-treatment').forEach(sel => {
            const currentVal = sel.value;
            populateSessionTreatmentDropdown(sel);
            if (currentVal) {
                let matched = false;
                for (let opt of sel.options) {
                    if (opt.value === currentVal) { sel.value = currentVal; matched = true; break; }
                }
                if (!matched && currentVal !== '__other__') {
                    sel.value = '__other__';
                    const row = sel.closest('.session-row');
                    if (row) {
                        const otherInput = row.querySelector('.session-other-treatment input');
                        if (otherInput) { otherInput.value = currentVal; otherInput.closest('.session-other-treatment').style.display = 'block'; }
                    }
                }
            }
        });
    })
    .catch(() => console.error("Failed to load services"));
}

// 2. A Universal Function to fetch and render slots for ANY modal
function loadTimeSlotsGrid(date, serviceId, gridId, hiddenInputId, selectId, promptId) {
    const slotsContainer = document.getElementById(gridId);
    const prompt = document.getElementById(promptId);
    
    if (!date || !serviceId) {
        if (prompt) prompt.style.display = 'block';
        slotsContainer.style.display = 'none';
        return;
    }

    if (prompt) prompt.style.display = 'none';
    slotsContainer.style.display = 'grid';
    slotsContainer.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 10px;"><i class="fas fa-spinner fa-spin"></i> Loading slots...</div>';
    
    fetch(`../user-patient/get_slots.php?date=${encodeURIComponent(date)}&service_id=${encodeURIComponent(serviceId)}`)
    .then(res => res.json())
    .then(data => {
        slotsContainer.innerHTML = '';
        
        if (!data.success || !data.is_open || data.is_past) {
            const msg = data.is_past ? 'Cannot schedule on past dates' : (data.message || 'Unavailable');
            slotsContainer.innerHTML = `<div style="color: #dc2626; grid-column: 1/-1; text-align: center;">${msg}</div>`;
            return;
        }
        
        const available = data.available_slots || [];
        const booked = data.booked_slots || [];
        
        if (available.length === 0) {
            slotsContainer.innerHTML = '<div style="color: #dc2626; grid-column: 1/-1; text-align: center;">No available time slots</div>';
            return;
        }
        
        // Render Available Slots
        available.forEach(slot => {
            const btn = document.createElement('div');
            btn.className = 'time-slot available';
            btn.textContent = formatTime12h(slot); // Using your existing 12h formatter
            btn.dataset.time = slot;
            btn.onclick = function() {
                handleSlotSelection(this, gridId, hiddenInputId, selectId);
            };
            slotsContainer.appendChild(btn);
        });
        
        // Render Booked Slots (Visual cue only)
        booked.forEach(slot => {
            const btn = document.createElement('div');
            btn.className = 'time-slot unavailable';
            btn.textContent = formatTime12h(slot);
            btn.style.cursor = 'not-allowed';
            btn.style.opacity = '0.5';
            slotsContainer.appendChild(btn);
        });
    })
    .catch(() => {
        slotsContainer.innerHTML = '<div style="color: #dc2626; grid-column: 1/-1;">Error loading time slots</div>';
    });
}

// 3. The Highlight Logic (Calculates duration and highlights blocks)
function handleSlotSelection(btn, gridId, hiddenInputId, selectId) {
    const grid = document.getElementById(gridId);
    
    // Clear old selections
    grid.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
    
    // Get service duration
    const select = document.getElementById(selectId);
    const option = select.options[select.selectedIndex];
    const duration = parseInt(option.getAttribute('data-duration')) || 15;
    const blocksNeeded = Math.ceil(duration / 15);
    
    // Highlight consecutive blocks
    const allSlots = Array.from(grid.querySelectorAll('.time-slot'));
    const startIndex = allSlots.indexOf(btn);
    
    if (startIndex !== -1) {
        for (let i = 0; i < blocksNeeded; i++) {
            const currentSlot = allSlots[startIndex + i];
            if (currentSlot && !currentSlot.classList.contains('unavailable')) {
                currentSlot.classList.add('selected');
            }
        }
    }
    
    // Save to hidden input
    document.getElementById(hiddenInputId).value = btn.dataset.time;
}

// --- EVENT LISTENERS FOR THE ADD APPOINTMENT MODAL ---
document.getElementById('addAptDate').addEventListener('change', function() {
    loadTimeSlotsGrid(this.value, document.getElementById('addAptTreatment').value, 'addAptTimeSlots', 'addAptTime', 'addAptTreatment', 'addAptTimePrompt');
});

document.getElementById('addAptTreatment').addEventListener('change', function() {
    loadTimeSlotsGrid(document.getElementById('addAptDate').value, this.value, 'addAptTimeSlots', 'addAptTime', 'addAptTreatment', 'addAptTimePrompt');
});



// Important: When you open the Add Appointment Modal, reset the form and trigger the service load
const originalOpenAptModal = openAddAppointmentModal;
openAddAppointmentModal = function(patientId) {
    originalOpenAptModal(patientId);
    document.getElementById('addAppointmentForm').reset();
    document.getElementById('addAptTimeSlots').style.display = 'none';
    document.getElementById('addAptTimePrompt').style.display = 'block';
    loadTpServices(); // Load services into the dropdown
};



function formatTime12h(time24) {
    const [hours, minutes] = time24.split(':').map(Number);
    const period = hours >= 12 ? 'PM' : 'AM';
    const hour12 = hours % 12 || 12;
    return `${hour12}:${minutes.toString().padStart(2, '0')} ${period}`;
}

function formatTimeRange(startTime, treatmentName) {
    let duration = 30;
    if (treatmentName) {
        const svc = (window._cachedServices || []).find(s => s.name === treatmentName);
        if (svc && svc.duration_minutes) duration = parseInt(svc.duration_minutes);
    }
    const [h, m] = startTime.split(':').map(Number);
    const totalMin = h * 60 + m + duration;
    const endH = Math.floor(totalMin / 60) % 24;
    const endM = totalMin % 60;
    const endStr = String(endH).padStart(2, '0') + ':' + String(endM).padStart(2, '0');
    return formatTime12h(startTime) + ' - ' + formatTime12h(endStr);
}

function updateSessionTimeDisplay(row) {
    const label = row.querySelector('.session-time-label');
    const hiddenInput = row.querySelector('.session-time-hidden');
    if (!label || !hiddenInput || !hiddenInput.value) return;
    const treatSelect = row.querySelector('.session-treatment');
    const treatment = treatSelect ? treatSelect.value : '';
    const otherInput = row.querySelector('.session-other-treatment input');
    const treatmentName = treatment === '__other__' ? (otherInput ? otherInput.value : '') : treatment;
    label.textContent = '\uD83D\uDD50 ' + formatTimeRange(hiddenInput.value, treatmentName);
}

function selectAllUpper() {
    const isAdult = currentTeethType === 'adult';
    let upperTeeth = [];
    if (isAdult) {
        upperTeeth = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
    } else {
        upperTeeth = [55, 54, 53, 52, 51, 61, 62, 63, 64, 65];
    }
    
    upperTeeth.forEach(num => {
        const el = document.querySelector(`.tooth[data-tooth="${num}"]`);
        if (el && !selectedTeeth.includes(num.toString())) {
            selectedTeeth.push(num.toString());
            el.classList.add('selected');
        }
    });
    selectedTeeth.sort((a, b) => parseInt(a) - parseInt(b));
    document.getElementById('tpTeethNumbers').value = selectedTeeth.join(', ');
    document.getElementById('selectedTeethText').textContent = selectedTeeth.length > 0 ? selectedTeeth.join(', ') : 'None';
}

function selectAllLower() {
    const isAdult = currentTeethType === 'adult';
    let lowerTeeth = [];
    if (isAdult) {
        lowerTeeth = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];
    } else {
        lowerTeeth = [85, 84, 83, 82, 81, 71, 72, 73, 74, 75];
    }
    
    lowerTeeth.forEach(num => {
        const el = document.querySelector(`.tooth[data-tooth="${num}"]`);
        if (el && !selectedTeeth.includes(num.toString())) {
            selectedTeeth.push(num.toString());
            el.classList.add('selected');
        }
    });
    selectedTeeth.sort((a, b) => parseInt(a) - parseInt(b));
    document.getElementById('tpTeethNumbers').value = selectedTeeth.join(', ');
    document.getElementById('selectedTeethText').textContent = selectedTeeth.length > 0 ? selectedTeeth.join(', ') : 'None';
}

function setSessionCount(n, existingSessions) {
    document.getElementById('tpTotalSessions').value = n;
    document.querySelectorAll('.sess-btn').forEach(btn => {
        btn.classList.toggle('active', parseInt(btn.dataset.count) === n);
    });

    const container = document.getElementById('sessionRowsContainer');
    container.innerHTML = '';

    for (let i = 1; i <= n; i++) {
        const existing = existingSessions && existingSessions[i - 1];
        const row = document.createElement('div');
        row.className = 'session-row';
        row.dataset.session = i;

        if (existing && existing.date) {
            buildTransitionedRow(row, i, existing);
        } else {
            buildInitialRow(row, i);
        }

        container.appendChild(row);
    }
}

function buildInitialRow(row, i) {
    row.innerHTML = '<div class="session-row-header">Session ' + i + '</div>';

    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'session_time_' + i;
    hiddenInput.id = 'sessionTime_' + i;
    hiddenInput.className = 'session-time-hidden';
    hiddenInput.value = '';

    const content = document.createElement('div');
    content.className = 'session-row-content';

    const leftCol = document.createElement('div');
    leftCol.className = 'session-left';
    const leftInner = document.createElement('div');
    leftInner.className = 'session-left-inner';

    const treatmentField = document.createElement('div');
    treatmentField.className = 'session-field';
    const treatLabel = document.createElement('label');
    treatLabel.className = 'session-field-label';
    treatLabel.textContent = 'Treatment';
    treatmentField.appendChild(treatLabel);
    const treatSelect = document.createElement('select');
    treatSelect.name = 'session_treatment_' + i;
    treatSelect.className = 'form-control session-treatment';
    treatmentField.appendChild(treatSelect);
    const otherDiv = document.createElement('div');
    otherDiv.className = 'session-other-treatment';
    otherDiv.style.display = 'none';
    const otherInput = document.createElement('input');
    otherInput.type = 'text';
    otherInput.name = 'session_treatment_other_' + i;
    otherInput.className = 'form-control';
    otherInput.placeholder = 'Enter custom treatment name';
    otherDiv.appendChild(otherInput);
    treatmentField.appendChild(otherDiv);

    const notesField = document.createElement('div');
    notesField.className = 'session-field';
    const notesLabel = document.createElement('label');
    notesLabel.className = 'session-field-label';
    notesLabel.textContent = 'Notes';
    notesField.appendChild(notesLabel);
    const notesTextarea = document.createElement('textarea');
    notesTextarea.name = 'session_notes_' + i;
    notesTextarea.className = 'form-control session-notes';
    notesTextarea.rows = 2;
    notesTextarea.placeholder = 'Notes for this session...';
    notesField.appendChild(notesTextarea);

    leftInner.appendChild(treatmentField);
    leftInner.appendChild(notesField);
    leftCol.appendChild(leftInner);

    const rightCol = document.createElement('div');
    rightCol.className = 'session-right';
    const rightInner = document.createElement('div');
    rightInner.className = 'session-right-inner';

    const dateInput = document.createElement('input');
    dateInput.type = 'date';
    dateInput.name = 'session_date_' + i;
    dateInput.className = 'form-control session-date';
    dateInput.min = new Date().toISOString().split('T')[0];
    dateInput.disabled = true;
    rightInner.appendChild(dateInput);

    const timeWrapper = document.createElement('div');
    timeWrapper.className = 'session-time-wrapper';
    const slotContainer = document.createElement('div');
    slotContainer.className = 'session-time-slots';
    slotContainer.id = 'sessionSlots_' + i;
    timeWrapper.appendChild(slotContainer);
    rightInner.appendChild(timeWrapper);

    rightCol.appendChild(rightInner);

    content.appendChild(leftCol);
    content.appendChild(rightCol);
    row.appendChild(hiddenInput);
    row.appendChild(content);

    populateSessionTreatmentDropdown(treatSelect);

    treatSelect.addEventListener('change', function() {
        otherDiv.style.display = this.value === '__other__' ? 'block' : 'none';
        dateInput.disabled = !this.value;
        if (!this.value) {
            dateInput.value = '';
            slotContainer.innerHTML = '';
            hiddenInput.value = '';
        }
    });

    dateInput.addEventListener('change', function() {
        const date = this.value;
        if (date) {
            loadSessionTimeSlots(date, i, slotContainer, hiddenInput);
        } else {
            slotContainer.innerHTML = '';
            hiddenInput.value = '';
        }
    });

    dateInput.addEventListener('change', function() {
        const date = this.value;
        if (date) {
            loadSessionTimeSlots(date, i, slotContainer, hiddenInput);
        } else {
            slotContainer.innerHTML = '';
            hiddenInput.value = '';
        }
    });
}

function buildTransitionedRow(row, i, existing) {
    row.innerHTML = '<div class="session-row-header">Session ' + i + '</div>';

    const content = document.createElement('div');
    content.className = 'session-row-content';

    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'session_time_' + i;
    hiddenInput.id = 'sessionTime_' + i;
    hiddenInput.className = 'session-time-hidden';
    hiddenInput.value = existing.time || '';

    const leftCol = document.createElement('div');
    leftCol.className = 'session-left';
    const leftInner = document.createElement('div');
    leftInner.className = 'session-left-inner';

    const treatmentField = document.createElement('div');
    treatmentField.className = 'session-field';
    const treatLabel = document.createElement('label');
    treatLabel.className = 'session-field-label';
    treatLabel.textContent = 'Treatment';
    treatmentField.appendChild(treatLabel);
    const treatSelect = document.createElement('select');
    treatSelect.name = 'session_treatment_' + i;
    treatSelect.className = 'form-control session-treatment';
    treatmentField.appendChild(treatSelect);
    const otherDiv = document.createElement('div');
    otherDiv.className = 'session-other-treatment';
    otherDiv.style.display = 'none';
    const otherInput = document.createElement('input');
    otherInput.type = 'text';
    otherInput.name = 'session_treatment_other_' + i;
    otherInput.className = 'form-control';
    otherInput.placeholder = 'Enter custom treatment name';
    otherDiv.appendChild(otherInput);
    treatmentField.appendChild(otherDiv);

    const notesField = document.createElement('div');
    notesField.className = 'session-field';
    const notesLabel = document.createElement('label');
    notesLabel.className = 'session-field-label';
    notesLabel.textContent = 'Notes';
    notesField.appendChild(notesLabel);
    const notesTextarea = document.createElement('textarea');
    notesTextarea.name = 'session_notes_' + i;
    notesTextarea.className = 'form-control session-notes';
    notesTextarea.rows = 2;
    notesTextarea.placeholder = 'Notes for this session...';
    if (existing.notes) notesTextarea.value = existing.notes;
    notesField.appendChild(notesTextarea);

    leftInner.appendChild(treatmentField);
    leftInner.appendChild(notesField);
    leftCol.appendChild(leftInner);

    const rightCol = document.createElement('div');
    rightCol.className = 'session-right';
    const rightInner = document.createElement('div');
    rightInner.className = 'session-right-inner';

    const dateInput = document.createElement('input');
    dateInput.type = 'date';
    dateInput.name = 'session_date_' + i;
    dateInput.className = 'form-control session-date';
    dateInput.min = new Date().toISOString().split('T')[0];
    dateInput.value = existing.date || '';
    rightInner.appendChild(dateInput);

    const timeDisplay = document.createElement('div');
    timeDisplay.className = 'session-selected-time';
    if (existing.time) {
        const label = document.createElement('span');
        label.className = 'session-time-label';
        label.textContent = '\uD83D\uDD50 ' + formatTimeRange(existing.time, existing.treatment || '');
        timeDisplay.appendChild(label);
    }
    const changeBtn = document.createElement('button');
    changeBtn.type = 'button';
    changeBtn.className = 'session-change-time';
    changeBtn.textContent = 'Change';
    changeBtn.onclick = function() { deselectTimeSlot(row); };
    timeDisplay.appendChild(changeBtn);
    rightInner.appendChild(timeDisplay);
    rightCol.appendChild(rightInner);

    content.appendChild(leftCol);
    content.appendChild(rightCol);
    row.appendChild(hiddenInput);
    row.appendChild(content);

    populateSessionTreatmentDropdown(treatSelect);
    if (existing.treatment) {
        let matched = false;
        for (let opt of treatSelect.options) {
            if (opt.value === existing.treatment) {
                treatSelect.value = existing.treatment;
                matched = true;
                break;
            }
        }
        if (!matched) {
            treatSelect.value = '__other__';
            otherDiv.style.display = 'block';
            otherInput.value = existing.treatment;
        }
    }

    treatSelect.addEventListener('change', function() {
        otherDiv.style.display = this.value === '__other__' ? 'block' : 'none';
        updateSessionTimeDisplay(row);
    });
    otherInput.addEventListener('input', function() {
        if (treatSelect.value === '__other__') {
            updateSessionTimeDisplay(row);
        }
    });

    dateInput.addEventListener('change', function() {
        if (this.value !== existing.date) {
            deselectTimeSlot(row);
        }
    });
}

function selectTimeSlot(row, timeStr) {
    const hiddenInput = row.querySelector('.session-time-hidden');
    const timeWrapper = row.querySelector('.session-time-wrapper');
    if (!timeWrapper) return;

    hiddenInput.value = timeStr;
    timeWrapper.innerHTML = '';

    const timeDisplay = document.createElement('div');
    timeDisplay.className = 'session-selected-time';
    const label = document.createElement('span');
    label.className = 'session-time-label';
    label.textContent = '\uD83D\uDD50 ' + formatTimeRange(timeStr, '');
    timeDisplay.appendChild(label);
    const changeBtn = document.createElement('button');
    changeBtn.type = 'button';
    changeBtn.className = 'session-change-time';
    changeBtn.textContent = 'Change';
    changeBtn.onclick = function() { deselectTimeSlot(row); };
    timeDisplay.appendChild(changeBtn);
    timeWrapper.appendChild(timeDisplay);
}

function deselectTimeSlot(row) {
    const hiddenInput = row.querySelector('.session-time-hidden');
    const timeWrapper = row.querySelector('.session-time-wrapper');
    const dateInput = row.querySelector('.session-date');
    const sessionNum = row.dataset.session;
    if (!timeWrapper) return;

    hiddenInput.value = '';
    timeWrapper.innerHTML = '';

    const slotContainer = document.createElement('div');
    slotContainer.className = 'session-time-slots';
    slotContainer.id = 'sessionSlots_' + sessionNum;
    timeWrapper.appendChild(slotContainer);

    const currentDate = dateInput.value;
    if (currentDate) {
        loadSessionTimeSlots(currentDate, sessionNum, slotContainer, hiddenInput);
    }
}

function populateSessionTreatmentDropdown(selectEl) {
    selectEl.innerHTML = '<option value="">Select Treatment</option>';
    const services = window._cachedServices || [];
    services.forEach(function(s) {
        const opt = document.createElement('option');
        opt.value = s.name;
        opt.textContent = s.name + (s.duration_minutes ? ' (' + s.duration_minutes + ' min)' : '');
        selectEl.appendChild(opt);
    });
    const otherOpt = document.createElement('option');
    otherOpt.value = '__other__';
    otherOpt.textContent = 'Others';
    selectEl.appendChild(otherOpt);
}

function loadSessionTimeSlots(date, sessionNum, container, hiddenInput) {
    let url = '../user-patient/get_slots.php?date=' + encodeURIComponent(date);

    container.innerHTML = '<span style="color:#6b7280;font-size:0.8rem;">Loading...</span>';

    fetch(url)
    .then(res => res.json())
    .then(data => {
        container.innerHTML = '';

        if (!data.success || !data.is_open || data.is_past) {
            container.innerHTML = '<span style="color:#dc2626;font-size:0.8rem;">' + (data.message || 'Unavailable') + '</span>';
            return;
        }

        const available = data.available_slots || [];
        if (available.length === 0) {
            container.innerHTML = '<span style="color:#6b7280;font-size:0.8rem;">No available slots</span>';
            return;
        }

        // Merge available and booked slots in position order
        const bookedSet = new Set(data.booked_slots || []);
        const allSlots = [...(data.available_slots || []), ...(data.booked_slots || [])];
        allSlots.sort();
        const seen = new Set();
        allSlots.forEach(slot => {
            if (seen.has(slot)) return;
            seen.add(slot);
            const isBooked = bookedSet.has(slot);
            const btn = document.createElement('button');
            btn.type = 'button';
            if (isBooked) {
                btn.className = 'time-slot-btn booked';
                btn.disabled = true;
                btn.title = 'Already booked';
            } else {
                btn.className = 'time-slot-btn';
                btn.onclick = function() {
                    selectTimeSlot(container.closest('.session-row'), slot);
                };
            }
            btn.textContent = formatTime12h(slot);
            container.appendChild(btn);
        });
    })
    .catch(() => {
        container.innerHTML = '<span style="color:#dc2626;font-size:0.8rem;">Error loading slots</span>';
    });
}

function saveTreatmentPlan() {
    const form = document.getElementById('tpNewPlanForm');
    const formData = new FormData(form);

    // Validate each session has a treatment if date and time are set
    const totalSessions = parseInt(document.getElementById('tpTotalSessions').value) || 0;
    for (let i = 1; i <= totalSessions; i++) {
        const dateInput = document.querySelector('[name="session_date_' + i + '"]');
        const timeInput = document.getElementById('sessionTime_' + i);
        if (dateInput && dateInput.value && timeInput && timeInput.value) {
            const treatmentSelect = document.querySelector('[name="session_treatment_' + i + '"]');
            const treatmentOther = document.querySelector('[name="session_treatment_other_' + i + '"]');
            const treatment = treatmentSelect ? treatmentSelect.value : '';
            const treatmentOtherVal = treatmentOther ? treatmentOther.value : '';
            if (!treatment && !treatmentOtherVal) {
                alert('Session ' + i + ' has a date/time but no treatment selected. Please choose a treatment for every scheduled session.');
                return;
            }
        }
    }

    // Set first session date/time as next_session_date/time for backward compat
    const firstDate = document.querySelector('[name="session_date_1"]');
    const firstTime = document.getElementById('sessionTime_1');
    if (firstDate && firstDate.value) {
        formData.set('next_session_date', firstDate.value);
        formData.set('next_session_time', firstTime ? firstTime.value : '');
    }

    fetch('../shared/process_treatment_plan.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            let message = 'Treatment plan created successfully!';
            if (result.appointment_id) {
                message += '\n\nAn appointment has been automatically scheduled.\nYou can view it in the Appointments page.';
            }
            alert(message);
            // Close modal and redirect to appointments page to see the scheduled appointment
            closeTreatmentPlanModal();
            window.location.href = 'dentist_appointments.php';
        } else {
            alert(result.message || 'Error saving treatment plan');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving treatment plan');
    });
}

function showEditAmountForm() {
    const currentAmount = parseFloat(document.getElementById('billingTotalAmount').textContent.replace(/[^0-9.-]+/g, '')) || 0;
    document.getElementById('newBillingAmount').value = currentAmount;
    document.getElementById('billingAmountDisplay').style.display = 'none';
    document.getElementById('editAmountForm').classList.add('active');
}

function cancelEditAmount() {
    document.getElementById('editAmountForm').classList.remove('active');
    document.getElementById('billingAmountDisplay').style.display = 'block';
}

function saveNewAmount() {
    const newAmount = parseFloat(document.getElementById('newBillingAmount').value);
    const notes = document.getElementById('billingNotes').value;
    
    if (isNaN(newAmount) || newAmount < 0) {
        alert('Please enter a valid amount');
        return;
    }
    
    fetch('dentist_billing_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'update_amount',
            queue_id: billingQueueId,
            patient_id: billingPatientId,
            total_amount: newAmount,
            notes: notes
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Refresh the modal with new data
            openBillingModal(billingPatientId);
        } else {
            alert(data.message || 'Error updating billing');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating billing amount');
    });
}

// Close modal on outside click
document.getElementById('billingModal').addEventListener('click', function(e) {
    if (e.target === this || e.target.classList.contains('modal-backdrop')) {
        closeBillingModal();
    }
});

// ESC key to close modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeBillingModal();
    }
});

// Auto-refresh when a new patient is added
setInterval(() => {
    fetch('dentist_patients_poll.php')
        .then(response => response.json())
        .then(data => {
            if (data && data.success) {
                const newestId = parseInt(data.latest_id || 0, 10);
                if (newestId > latestPatientId) {
                    // Check if we already showed the alert
                    if (!document.getElementById('new-patient-alert')) {
                        const alertDiv = document.createElement('div');
                        alertDiv.id = 'new-patient-alert';
                        alertDiv.innerHTML = `
                            <div style="background: #eff6ff; border: 1px solid #3b82f6; color: #1e40af; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                                <span>A new patient has been added to the queue.</span>
                                <button onclick="location.reload()" style="background: #3b82f6; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;">Refresh List</button>
                            </div>
                        `;
                        // Insert it right above the search/filter row
                        const searchRow = document.querySelector('.search-filter-row');
                        if (searchRow) {
                            searchRow.parentNode.insertBefore(alertDiv, searchRow);
                        }
                    }
                }
            }
        })
        .catch(() => {});
}, 10000);

</script>






<!-- styles -->

<style>
/* Tooth Chart Styles */
.tooth-chart-modal {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
}
.tooth {
    width: 34px; height: 42px;
    background: linear-gradient(145deg, #ffffff 0%, #f5f5f5 30%, #e8e8e8 70%, #d0d0d0 100%);
    border-radius: 7px 7px 9px 9px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 10px; font-weight: 700; color: #455a64;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2), 0 4px 8px rgba(0,0,0,0.15), inset 0 1px 2px rgba(255,255,255,0.9), inset 0 -1px 2px rgba(0,0,0,0.1);
    border: 1px solid #bdbdbd;
    user-select: none;
}
.tooth::before {
    content: '';
    position: absolute; top: 3px; left: 5px; right: 5px; height: 7px;
    background: linear-gradient(180deg, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0) 100%);
    border-radius: 3px 3px 1px 1px;
}
.tooth:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 8px 16px rgba(37,99,235,0.3), 0 4px 8px rgba(37,99,235,0.2), inset 0 1px 2px rgba(255,255,255,0.9), inset 0 -1px 2px rgba(0,0,0,0.1);
    border-color: #2563eb; z-index: 10;
}
.tooth.selected {
    background: linear-gradient(145deg, #dbeafe 0%, #bfdbfe 30%, #93c5fd 70%, #60a5fa 100%);
    border-color: #2563eb;
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 8px 16px rgba(37,99,235,0.4), 0 4px 8px rgba(37,99,235,0.3), inset 0 2px 4px rgba(255,255,255,0.5), inset 0 -2px 4px rgba(0,0,0,0.1);
    color: #1e40af;
}
.tooth.selected::after {
    content: '✓';
    position: absolute; top: -8px; right: -8px;
    width: 18px; height: 18px;
    background: linear-gradient(145deg, #22c55e 0%, #16a34a 100%);
    color: white; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: bold;
    box-shadow: 0 2px 4px rgba(34,197,94,0.4);
    border: 2px solid white;
}
.tooth.molar { width: 38px; height: 48px; }
.tooth.incisor { width: 26px; height: 36px; }
.tooth.canine { width: 28px; height: 40px; }
.tooth.premolar { width: 30px; height: 42px; }
@keyframes toothPulse {
    0% { transform: scale(1); }
    50% { transform: scale(0.95); }
    100% { transform: scale(1); }
}

/* Session Button Styles */
.sess-btn {
    width: 40px; height: 40px;
    border-radius: 50%;
    border: 2px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.sess-btn:hover {
    border-color: #3b82f6;
    color: #3b82f6;
    background: #eff6ff;
}
.sess-btn.active {
    background: #3b82f6;
    border-color: #3b82f6;
    color: #ffffff;
    box-shadow: 0 4px 8px rgba(59,130,246,0.3);
}
.session-row {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px;
    margin-bottom: 12px;
}
.session-row-header {
    font-size: 0.85rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 10px;
    letter-spacing: 0.02em;
}
.session-row-fields {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}
.session-row-fields .session-date {
    width: 180px;
    flex-shrink: 0;
}
.session-time-wrapper {
    flex: 1;
}
.session-time-slots {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(75px, 1fr));
    gap: 4px;
}
.time-slot-btn {
    padding: 4px 6px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: #f3f4f6;
    color: #374151;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s;
}
.time-slot-btn:hover {
    border-color: #3b82f6;
    background: #eff6ff;
}
.time-slot-btn.booked {
    background: #fef2f2;
    border-color: #fecaca;
    color: #dc2626;
    cursor: not-allowed;
    opacity: 0.7;
    text-decoration: line-through;
}
.time-slot-btn.booked:hover {
    background: #fef2f2;
    border-color: #fecaca;
}

/* Transitioned session row layout */
.session-row-content {
    display: flex;
    gap: 16px;
    align-items: flex-start;
}
.session-left {
    flex: 1;
    min-width: 0;
}
.session-left-inner {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.session-right {
    flex: 0 0 280px;
    min-width: 250px;
}
.session-right-inner {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.session-right-inner .session-date {
    width: 100%;
}
.session-right-inner .session-date:disabled {
    background: #f3f4f6;
    color: #9ca3af;
    cursor: not-allowed;
    opacity: 0.7;
}
.session-selected-time {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 8px 12px;
}
.session-time-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: #1e40af;
}
.session-change-time {
    font-size: 0.75rem;
    color: #3b82f6;
    background: none;
    border: none;
    cursor: pointer;
    text-decoration: underline;
    padding: 2px 4px;
}
.session-change-time:hover {
    color: #1d4ed8;
}
.session-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.session-field-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.session-treatment {
    width: 100%;
}
.session-other-treatment {
    margin-top: 6px;
}
.session-notes {
    width: 100%;
    resize: vertical;
    min-height: 50px;
}

/* Time Slot Styles */
.time-slots {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 6px;
    margin-top: 8px;
}
.time-slot {
    padding: 8px 8px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 500;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #ffffff;
    color: #1e293b;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.time-slot.available:hover {
    border-color: #3b82f6;
    background: #eff6ff;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(59,130,246,0.15);
}
.time-slot.available.selected {
    background: #3b82f6;
    border-color: #3b82f6;
    color: white;
    box-shadow: 0 4px 8px rgba(59,130,246,0.3);
}
.time-slot.unavailable {
    background: #f8fafc;
    color: #94a3b8;
    text-decoration: line-through;
    cursor: not-allowed;
    opacity: 0.6;
}

@media (max-width: 480px) {
    .tooth { width: 28px; height: 36px; font-size: 8px; }
    .tooth.molar { width: 32px; height: 40px; }
    .tooth.incisor { width: 22px; height: 30px; }
    .tooth.canine { width: 24px; height: 34px; }
    .tooth.premolar { width: 26px; height: 36px; }
}

/* Table Styles - Match admin_patients */
.table-container {
    overflow-x: visible;
    overflow-y: visible;
    background: white;
    border-radius: 12px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table thead {
    background: #f9fafb;
}

.data-table th {
    padding: 14px 16px;
    text-align: left;
    font-weight: 600;
    color: #6b7280;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid #e5e7eb;
}

.data-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #f3f4f6;
    color: #374151;
    font-size: 0.875rem;
}

.data-table tbody tr:hover {
    background: #f9fafb;
}

.data-table tbody tr:last-child td {
    border-bottom: none;
}

/* Column Alignment */
.data-table th,
.data-table td {
    vertical-align: middle;
    text-align: left;
}

.data-table th:nth-child(5),
.data-table td:nth-child(5),
.data-table th:nth-child(6),
.data-table td:nth-child(6),
.data-table th:nth-child(7),
.data-table td:nth-child(7),
.data-table th:nth-child(8),
.data-table td:nth-child(8) {
    text-align: center;
}

.data-table th:last-child,
.data-table td:last-child {
    text-align: center;
    width: 60px;
}

/* Patient Name */
.patient-name {
    font-weight: 500;
    font-size: 0.9rem;
}

.patient-contact {
    font-size: 0.85rem;
    color: #6b7280;
}

/* Status Badge */
.status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
    display: inline-block;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 20px;
}

.pagination-info {
    color: #6b7280;
    font-size: 0.875rem;
}

.pagination-buttons {
    display: flex;
    gap: 8px;
    align-items: center;
}

.pagination-btn {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    background: white;
    color: #374151;
    border-radius: 6px;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    min-width: 40px;
    text-align: center;
}

.pagination-btn:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

.pagination-btn.active {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
}

.pagination-btn:disabled {
    background: #f9fafb;
    color: #9ca3af;
    cursor: not-allowed;
    opacity: 0.5;
}

.pagination-ellipsis {
    padding: 8px 4px;
    color: #6b7280;
    font-weight: 500;
}



/* Billing Modal Styles */
.billing-section {
    margin-bottom: 20px;
}

.billing-section h4 {
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 12px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.billing-amount-display {
    background: #f9fafb;
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    margin-bottom: 16px;
}

.billing-amount-label {
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 4px;
}

.billing-amount-value {
    font-size: 2rem;
    font-weight: 700;
    color: #111827;
}

.billing-amount-value.editable {
    cursor: pointer;
    color: #0ea5e9;
    transition: color 0.2s;
}

.billing-amount-value.editable:hover {
    color: #0284c7;
}

.billing-status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.billing-status-paid { background: #d1fae5; color: #065f46; }
.billing-status-partial { background: #fef3c7; color: #92400e; }
.billing-status-unpaid { background: #fee2e2; color: #991b1b; }

.billing-details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-top: 16px;
}

.billing-detail-item {
    background: #f9fafb;
    border-radius: 8px;
    padding: 12px;
}

.billing-detail-label {
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 4px;
}

.billing-detail-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: #111827;
}

.edit-amount-form {
    display: none;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
}

.edit-amount-form.active {
    display: block;
}

.edit-amount-form .form-group {
    margin-bottom: 12px;
}

.edit-amount-form .form-control {
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 1.25rem;
    font-weight: 600;
    text-align: center;
}

.edit-amount-form .form-control:focus {
    outline: none;
    border-color: #0ea5e9;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

.btn-save {
    background: #0ea5e9;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 10px 20px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-save:hover {
    background: #0284c7;
}

.btn-cancel-edit {
    background: white;
    color: #6b7280;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 10px 20px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-cancel-edit:hover {
    background: #f3f4f6;
}

.btn-edit {
    background: white;
    color: #0ea5e9;
    border: 1px solid #0ea5e9;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-edit:hover {
    background: #eff6ff;
}

.billing-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid #e5e7eb;
}
.summary-cards{
    margin-bottom: 0px;
}
/* Summary cards - light blue dashboard design (matches staff dashboard) */
.summary-cards .summary-card {
    background: #ffffff;
    padding: 22px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 16px;
    border: none;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    min-height: 100px;
    position: relative;
    overflow: hidden;
    transition: transform 0.2s ease;
    cursor: default;
}
.summary-cards .summary-card:hover {
    transform: translateY(-2px);
}
.summary-cards .summary-icon,
.summary-cards .summary-info {
    position: relative;
    z-index: 2;
}
.summary-cards .summary-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: #d3e0fe;
}
.summary-cards .summary-icon img {
    width: 24px;
    height: 24px;
    object-fit: contain;
}
.summary-cards .summary-info h3 {
    font-size: 1.8rem;
    font-weight: 500;
    color: #4b5563;
    line-height: 1;
    margin-bottom: 4px;
}
.summary-cards .summary-info p {
    font-size: 0.9rem;
    color: #6b7280;
    margin: 0;
    font-weight: 500;
}
.summary-cards .summary-card::after {
    content: '';
    position: absolute;
    right: -25px;
    bottom: -15px;
    width: 100px;
    height: 100px;
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
    opacity: 0.12;
    z-index: 1;
    pointer-events: none;
}
.summary-cards .summary-card.card-total-patients::after {
    background-image: url('../../assets/icons/total-patients.png');
}
.summary-cards .summary-card.card-pending::after {
    background-image: url('../../assets/icons/pending.png');
}
.summary-cards .summary-card.card-new-this-month::after {
    background-image: url('../../assets/icons/new-this-month.png');
}
.summary-cards .summary-card.card-completed::after {
    background-image: url('../../assets/icons/check.png');
}
/* Container (Keeping this the same so they stay in a row) */
.search-filter-row {
    display: flex;
    align-items: center;
    gap: 15px;
    width: 100%;
    padding-bottom: 25px;
    /* Optional: A light gray background on your page makes the white box pop */
    /* background-color: #f8f9fa; */ 
}

/* The Clean Search Box */
.search-input {
    flex: 1;
    background-color: #ffffff;      /* Solid white background */
    border: 1px solid #d1d5db;      /* Light, clean gray border */
    border-radius: 8px;             /* Smooth rounded corners */
    padding: 12px 16px;             /* Gives the text room to breathe */
    font-size: 15px;
    color: #374151;                 /* Dark gray text for readability */
    outline: none;                  /* Removes the harsh default browser outline */
    transition: border-color 0.2s ease; /* Smooth transition when clicked */
}
.filter-select{
    border-radius: 8px;
    height: 43px;
    border: 1px solid #d1d5db;   
}

/* Styling the placeholder text specifically */
.search-input::placeholder {
    color: #9ca3af;                 /* Matches the soft gray in your image */
}

/* Optional: Slight color change when the user clicks inside */
.search-input:focus {
    border-color: #9ca3af;          /* Slightly darker gray border when active */
}

/* ---------------------- */





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
    z-index: 110001;
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
    z-index: 110000;
}

.patient-kebab-backdrop.show {
    display: block;
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

</style>

<!-- Record Payment Modal -->
<div id="recordPaymentModal" class="modal-overlay" style="display:none;">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <h2>💰 Record Payment</h2>
            <button class="modal-close" onclick="closeRecordPaymentModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="recordPaymentBreakdown" style="background:#f9fafb;border-radius:8px;padding:14px;margin-bottom:14px;border:1px solid #e5e7eb;">
            </div>
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px;margin-bottom:16px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="color:#166534;font-weight:600;font-size:0.95rem;">Total Outstanding Balance</span>
                    <span id="recordPaymentBalance" style="color:#dc2626;font-weight:700;font-size:1.15rem;">P0.00</span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-top:6px;font-size:0.85rem;">
                    <span style="color:#6b7280;">Total: <span id="recordPaymentTotalAmount">P0.00</span></span>
                    <span style="color:#6b7280;">Paid: <span id="recordPaymentPaidSoFar" style="color:#16a34a;">P0.00</span></span>
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

<?php require_once '../../includes/dentist_layout_end.php'; ?>



