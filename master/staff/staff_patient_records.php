<?php
$pageTitle = 'Patient Records';
require_once '../../config/database.php';
require_once '../../includes/discount_helper.php';
require_once '../../includes/patient_list_overflow_helper.php';
ensureDiscountSchema($pdo);
ensurePatientLogColumn($pdo);
runPatientListOverflow($pdo);
require_once '../../includes/staff_layout_start.php';

try {
    // Fetch ALL non-archived patients (client-side search/pagination)
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
        WHERE p.is_archived = 0 AND COALESCE(p.in_patient_log, 0) = 0
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
        WHERE p.is_archived = 0 AND COALESCE(p.in_patient_log, 0) = 0 AND a.status IN ('Scheduled', 'In Queue') AND a.appointment_date >= CURDATE()
    ");
    $inQueue = $inQueueStmt->fetchColumn();
    
    $completedStmt = $pdo->query("
        SELECT COUNT(DISTINCT p.id) FROM patients p
        INNER JOIN appointments a ON p.id = a.patient_id
        WHERE p.is_archived = 0 AND COALESCE(p.in_patient_log, 0) = 0 AND a.status = 'Completed'
    ");
    $completedCount = $completedStmt->fetchColumn();
    
    $newThisMonthStmt = $pdo->query("
        SELECT COUNT(*) FROM patients WHERE is_archived = 0 AND COALESCE(in_patient_log, 0) = 0 AND created_at > DATE_SUB(CURDATE(), INTERVAL 30 DAY)
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
    <div class="summary-card card-total-patients">
        <div class="summary-icon">
            <img src="../../assets/icons/total-patients.png" alt="Total Patients">
        </div>
        <div class="summary-info">
            <h3 id="statTotalPatients"><?php echo $totalPatients; ?></h3>
            <p>Total Patients</p>
        </div>
    </div>
    <div class="summary-card card-pending">
        <div class="summary-icon">
            <img src="../../assets/icons/pending.png" alt="Pending">
        </div>
        <div class="summary-info">
            <h3><?php echo $inQueue; ?></h3>
            <p>Pending</p>
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
    <div class="summary-card card-new-this-month">
        <div class="summary-icon">
            <img src="../../assets/icons/new-this-month.png" alt="New This Month">
        </div>
        <div class="summary-info">
            <h3><?php echo $newThisMonth; ?></h3>
            <p>New This Month</p>
        </div>
    </div>
    <div class="summary-card card-registered">
        <div class="summary-icon">
            <img src="../../assets/icons/registered.png" alt="Registered Patients">
        </div>
        <div class="summary-info">
            <h3 id="statRegisteredPatients"><?php echo $totalPatients; ?></h3>
            <p>Registered Patients</p>
        </div>
    </div>
</div>

<!-- Search Toolbar -->
<div class="search-toolbar" style="background: transparent; padding: 0 0 16px; border: none; border-radius: 0; margin-bottom: 0; display: flex;  gap: 16px; align-items: center; flex-wrap: wrap;">
    <input type="text" id="searchInput" placeholder="Search patient name or phone..." class="search-input" style="width: 1010px; padding: 10px 16px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; outline: none;">
    <select id="statusFilter" style="padding: 10px 16px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; background: white; cursor: pointer;">
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
</div>

<!-- Patient Records Table -->
<div class="section-card" style="padding: 0px; background: white; border-radius: 12px; border: 1px solid #e5e7eb;">
    
    <div style="overflow-x: auto;">
        <table class="data-table" style="width: 100%; border-collapse: collapse; border-radius: 26px;">
            <thead>
                <tr>
                    <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.78rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6; white-space: nowrap;">First Name</th>
                    <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.78rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6; white-space: nowrap;">Middle Name</th>
                    <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.78rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6; white-space: nowrap;">Last Name</th>
                    <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.78rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6; white-space: nowrap;">Age/Gender</th>
                    <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.78rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6; white-space: nowrap;">Contact</th>
                    <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.78rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6; white-space: nowrap;">Current Treatment</th>
                    <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.78rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6; white-space: nowrap;">Queue Status</th>
                    <th style="padding: 10px 12px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.78rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6; white-space: nowrap;">Actions</th>
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
    <div class="fullscreen-modal-content" role="dialog" aria-modal="true" aria-labelledby="staffPatientRecordTitle">
        <div class="fullscreen-modal-header">
            <div style="display:flex;align-items:center;gap:12px;flex:1;">
                <div id="staffModalPatientAvatar" style="width:56px;height:56px;border-radius:50%;overflow:hidden;background:#6366f1;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;font-weight:700;">
                </div>
                <div style="flex:1;">
                    <h2 id="staffPatientRecordTitle" style="font-size:1.25rem;font-weight:600;margin:0;">Patient Record Details</h2>
                    <div class="patient-record-identity" style="display:flex;align-items:center;gap:10px;margin-top:6px;white-space:nowrap;">
                        <p id="staffModalPatientName" style="color:#6b7280;margin:0;font-size:0.95rem;overflow:hidden;text-overflow:ellipsis;max-width:520px;white-space:nowrap;"></p>
                        <div id="staffModalPatientBadge" style="margin-left:6px;flex:0 0 auto;"></div>
                    </div>
                </div>
            </div>
            <button type="button" onclick="closePatientRecordModal()" class="fullscreen-modal-close" aria-label="Close patient details" style="background:none;border:none;font-size:1.6rem;color:#6b7280;cursor:pointer;">&times;</button>
        </div>
        <div class="fullscreen-modal-body" id="patientRecordContent">
        </div>
        
    </div>
</div>

<style>
#patientRecordModal { padding: 24px; box-sizing: border-box; background: rgba(15, 23, 42, 0.42); backdrop-filter: blur(2px); }
#patientRecordModal > .fullscreen-modal-content {
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: 920px;
    max-height: calc(100dvh - 48px);
    padding: 0;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.22);
    box-sizing: border-box;
}
#patientRecordModal .fullscreen-modal-header {
    display: flex;
    align-items: center;
    position: static;
    flex-shrink: 0;
    gap: 16px;
    padding: 22px 28px;
    margin: 0;
    border-bottom: 1px solid #e8edf4;
}
#patientRecordModal .fullscreen-modal-header > div,
#patientRecordModal .fullscreen-modal-header > div > div { min-width: 0; }
#patientRecordModal #staffModalPatientAvatar { width: 48px !important; height: 48px !important; flex-shrink: 0; }
#patientRecordModal #staffModalPatientAvatar img { width: 100% !important; height: 100% !important; }
#patientRecordModal .patient-record-identity { flex-wrap: wrap; row-gap: 6px !important; white-space: normal !important; }
#patientRecordModal #staffModalPatientName { white-space: normal !important; overflow-wrap: anywhere; }
#patientRecordModal #staffModalPatientBadge { margin-left: 0 !important; }
#patientRecordModal #staffModalPatientBadge span { font-size: 12px !important; padding: 4px 9px !important; border-radius: 8px !important; }
#patientRecordModal .fullscreen-modal-close { position: static; width: 36px; height: 36px; padding: 0; flex-shrink: 0; border-radius: 10px; }
#patientRecordModal .fullscreen-modal-close:hover { color: #334155; background: #f1f5f9; }
#patientRecordModal .fullscreen-modal-close:focus-visible { outline: 3px solid #93c5fd; outline-offset: 3px; }
#patientRecordContent {
    min-height: 0;
    max-height: none;
    padding: 24px 28px;
    background: #f8fafc;
    overflow-y: auto;
    overscroll-behavior: contain;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}
#patientRecordContent::-webkit-scrollbar { display: block; width: 6px; }
#patientRecordContent::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }
#patientRecordModal .patient-record-card { padding: 20px !important; margin-bottom: 16px !important; border: 1px solid #e2e8f0; border-radius: 12px !important; background: #fff !important; }
#patientRecordModal .patient-record-card h4 { margin-bottom: 16px !important; color: #334155 !important; }
#patientRecordModal .patient-record-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 16px 28px !important; }
#patientRecordModal .patient-record-grid > div { min-width: 0; overflow-wrap: anywhere; }
#patientRecordModal .patient-record-grid p { line-height: 1.5; }
@media (max-width: 600px) {
    #patientRecordModal { padding: 12px; }
    #patientRecordModal > .fullscreen-modal-content { max-height: calc(100dvh - 24px); border-radius: 16px; }
    #patientRecordModal .fullscreen-modal-header { padding: 18px; }
    #patientRecordContent { padding: 18px; }
    #patientRecordModal .patient-record-card { padding: 16px !important; }
    #patientRecordModal .patient-record-grid { grid-template-columns: 1fr !important; gap: 14px !important; }
    #patientRecordModal #staffPatientRecordTitle { font-size: 18px !important; }
}

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
    position: absolute;
    top: 12px;
    right: 12px;
    z-index: 20;
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
    background-color: #0d92f7;
    color: #ffffff;
    border-color: #0078d3;
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

/* Odontogram History Styles */
.odontogram-chart-area {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
}
.odontogram-legend-readonly {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 16px;
}
.odontogram-legend-readonly h5 {
    font-size: 0.78rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 8px 0;
    text-transform: uppercase;
}
.odontogram-legend-readonly .legend-section { margin-bottom: 6px; }
.odontogram-legend-readonly .legend-section-title {
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    margin-bottom: 3px;
}
.odontogram-legend-readonly .legend-items {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 12px;
}
.odontogram-legend-readonly .legend-item {
    font-size: 0.75rem;
    color: #475569;
    display: flex;
    align-items: center;
    gap: 3px;
}
.odontogram-legend-readonly .legend-color {
    width: 10px;
    height: 10px;
    border-radius: 2px;
    flex-shrink: 0;
}
.odontogram-legend-readonly .legend-color.decayed { background: #ef4444; }
.odontogram-legend-readonly .legend-color.treatment { background: #7dd3fc; }
.odontogram-legend-readonly .legend-color.extraction { background: #f59e0b; }
.odontogram-legend-readonly .legend-color.missing { background: #94a3b8; }
.odontogram-legend-readonly .legend-color.impacted { background: #a78bfa; }
.odontogram-legend-readonly .legend-color.other-cond { background: #6ee7b7; }
.odontogram-chart-section-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    text-align: center;
    margin: 10px 0 6px;
}
.odontogram-quadrant-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2px;
}
.odontogram-quadrant-block {
    display: flex;
    flex-direction: column;
    align-items: center;
}
.odontogram-quadrant-label {
    font-size: 0.65rem;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 4px;
}
.odontogram-tooth-row {
    display: flex;
    gap: 2px;
    align-items: flex-start;
}
.odontogram-tooth-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 48px;
}
.odontogram-tooth-num-label {
    font-size: 0.6rem;
    font-weight: 700;
    color: #64748b;
    margin-bottom: 2px;
    text-align: center;
}
.odontogram-tooth-svg-wrap { position: relative; }
.odontogram-tooth-svg-wrap svg { display: block; }
.odontogram-tooth-findings-below {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 1px;
    margin-top: 1px;
    min-height: 12px;
}
.odontogram-tooth-finding-badge {
    font-size: 0.5rem;
    font-weight: 700;
    padding: 0 3px;
    border-radius: 2px;
    line-height: 1.3;
}
.odontogram-tooth-finding-badge.pathology { background: #fecaca; color: #991b1b; }
.odontogram-tooth-finding-badge.treatment { background: #bae6fd; color: #0369a1; }
.odontogram-tooth-finding-badge.condition { background: #e2e8f0; color: #475569; }
.odontogram-tooth-finding-badge.surgery { background: #fef3c7; color: #92400e; }

/* Full Consultation Chart Styles */
.odx-section {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 12px;
}
.odx-section h5 {
    font-size: 0.78rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 10px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.odx-vitals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(105px, 1fr));
    gap: 8px;
}
.odx-vital {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 6px 10px;
}
.odx-vital-label {
    font-size: 0.62rem;
    color: #64748b;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.02em;
}
.odx-vital-value {
    font-size: 0.88rem;
    color: #0f172a;
    font-weight: 600;
    margin-top: 1px;
}
.odx-subrow {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
}
.odx-subblock { flex: 1; min-width: 200px; }
.odx-subblock-title {
    font-size: 0.68rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    margin-bottom: 6px;
}
.odx-check-row { display: flex; flex-wrap: wrap; gap: 6px; }
.odx-chip {
    font-size: 0.72rem;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 3px 9px;
    color: #94a3b8;
}
.odx-chip.on {
    background: #ecfdf5;
    border-color: #bbf7d0;
    color: #047857;
    font-weight: 600;
}
.odx-text {
    font-size: 0.85rem;
    color: #374151;
    line-height: 1.55;
    white-space: pre-wrap;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 12px;
}
.odx-empty { color: #94a3b8; font-size: 0.8rem; font-style: italic; }
.odx-rx-item {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 9px 12px;
    margin-bottom: 8px;
}
.odx-rx-name { font-weight: 700; color: #0f172a; font-size: 0.88rem; }
.odx-rx-meta { font-size: 0.78rem; color: #64748b; margin-top: 2px; }
.odx-rx-instr { font-size: 0.78rem; color: #15803d; margin-top: 4px; }
.odx-dx-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: white;
    border: 1px solid #bae6fd;
    color: #0369a1;
    font-size: 0.78rem;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 999px;
    margin-right: 6px;
    margin-bottom: 6px;
}
.odx-tx-plan {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 9px 12px;
    margin-bottom: 8px;
}
.odx-tx-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.odx-tx-name { font-weight: 700; color: #0f172a; font-size: 0.86rem; }
.odx-tx-status {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 2px 9px;
    border-radius: 999px;
    background: #dbeafe;
    color: #1d4ed8;
    text-transform: capitalize;
}
.odx-tx-status.completed { background: #dcfce7; color: #15803d; }
.odx-tx-status.cancelled, .odx-tx-status.discontinued { background: #fee2e2; color: #b91c1c; }
.odx-tx-meta { font-size: 0.76rem; color: #64748b; margin-top: 3px; }
.odx-tx-session { font-size: 0.76rem; color: #475569; margin-top: 4px; }

/* X-Ray section in chart modal */
.odx-xray-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 7px 12px;
    margin-bottom: 6px;
}
.odx-xray-name {
    font-size: 0.8rem;
    color: #334155;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-right: 10px;
}
.odx-eye-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: #94a3b8;
    padding: 2px;
    display: flex;
    align-items: center;
}
.odx-eye-btn:hover { color: #0ea5e9; }
.odontogram-modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 999999;
    align-items: center;
    justify-content: center;
}
.odontogram-modal-overlay.active { display: flex; }
.odontogram-modal-box {
    background: white;
    border-radius: 16px;
    max-width: 900px;
    width: 95%;
    max-height: 90vh;
    overflow-y: auto;
    padding: 24px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.odontogram-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.odontogram-modal-header h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}
.odontogram-modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #64748b;
    padding: 4px 8px;
}
.odontogram-modal-close:hover { color: #0f172a; }
.odontogram-findings-display {
    margin-top: 14px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 14px;
    max-height: 150px;
    overflow-y: auto;
}
.odontogram-findings-display h5 {
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    margin: 0 0 8px 0;
}
.odontogram-finding-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 3px 8px;
    font-size: 0.72rem;
    color: #334155;
    margin: 2px;
}
</style>

<script>
const allPatients = <?php echo json_encode($patients); ?>;
let currentPage = 1;
let perPage = 7;
let filteredPatients = [...allPatients];

const statusLabels = {'waiting':'Waiting','in_procedure':'In Procedure','on_hold':'On Hold','completed':'Completed','cancelled':'Cancelled','pending_payment':'Pending Payment','Scheduled':'Scheduled','In Queue':'In Queue','Pending Payment':'Pending Payment'};
const statusColors = {'waiting':'#fef3c7:#92400e','in_procedure':'#dbeafe:#1e40af','on_hold':'#fed7aa:#9a3412','completed':'#d1fae5:#065f46','cancelled':'#fee2e2:#dc2626','pending_payment':'#fed7aa:#c2410c','Scheduled':'#dbeafe:#1d4ed8','In Queue':'#fef3c7:#92400e','Pending Payment':'#fed7aa:#c2410c'};

// Portal Pattern: Move modals to body level.
// Runs at DOMContentLoaded so modals defined LATER in the markup (recordPaymentModal,
// paymentHistoryModal, setDueDateModal) exist and get portaled too — otherwise they
// stay earlier in the DOM and paint BEHIND the Patient Record Details modal.
document.addEventListener('DOMContentLoaded', function() {
    var modals = ['patientRecordModal','appointmentModal','recordPaymentModal','paymentHistoryModal','setDueDateModal'];
    modals.forEach(function(id) {
        var el = document.getElementById(id);
        if (el) document.body.appendChild(el);
    });
});

document.getElementById('searchInput').addEventListener('input', applyFilters);
document.getElementById('statusFilter').addEventListener('change', applyFilters);

renderTable();

// --- Live patient list updates (AJAX polling) ---
var knownPatientSig = allPatients.map(function(p) { return Number(p.id); }).join(',');
var liveToastTimer = null;
var livePollReady = false;

function patientListUiBusy() {
    // Never re-render underneath an open kebab dropdown or modal
    if (document.querySelector('.patient-kebab-dropdown-portal.show')) return true;
    if (document.body.classList.contains('modal-open')) return true;
    return false;
}

function showPatientListToast(message) {
    var toast = document.getElementById('livePatientToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'livePatientToast';
        document.body.appendChild(toast);
    }
    toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#065f46;color:#ffffff;padding:12px 18px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,0.18);font-size:0.9rem;z-index:99999;display:flex;align-items:center;gap:10px;opacity:0;transform:translateY(8px);transition:opacity .25s ease, transform .25s ease;pointer-events:none;visibility:visible;';
    toast.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6ee7b7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><span>' + message + '</span>';
    requestAnimationFrame(function() {
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
    });
    clearTimeout(liveToastTimer);
    liveToastTimer = setTimeout(function() {
        var el = document.getElementById('livePatientToast');
        if (!el) return;
        el.style.opacity = '0';
        el.style.transform = 'translateY(8px)';
        setTimeout(function() {
            var gone = document.getElementById('livePatientToast');
            if (gone && gone.parentNode) gone.parentNode.removeChild(gone);
        }, 300);
    }, 2000);
}

setInterval(function() {
    if (patientListUiBusy()) return;
    fetch('../shared/patients_list_poll.php')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data || !data.success || !Array.isArray(data.patients)) return;
            var sig = data.patients.map(function(p) { return Number(p.id); }).join(',');
            if (sig === knownPatientSig) {
                livePollReady = true;
                return;
            }

            var prevIds = {};
            if (knownPatientSig) {
                knownPatientSig.split(',').forEach(function(idStr) {
                    if (idStr) prevIds[idStr] = true;
                });
            }
            var hasNew = data.patients.some(function(p) { return !prevIds[String(Number(p.id))]; });

            allPatients.length = 0;
            Array.prototype.push.apply(allPatients, data.patients);
            knownPatientSig = sig;

            applyFilters(true); // keep current page/search context

            var statTotal = document.getElementById('statTotalPatients');
            if (statTotal) statTotal.textContent = data.patients.length;
            var statReg = document.getElementById('statRegisteredPatients');
            if (statReg) statReg.textContent = data.patients.length;

            if (hasNew && livePollReady) showPatientListToast('New patient added — list updated automatically.');
            livePollReady = true;
        })
        .catch(function() {});
}, 8000);

function applyFilters(keepPage) {
    if (!keepPage) currentPage = 1;
    const query = document.getElementById('searchInput').value.trim().toLowerCase();
    const statusVal = document.getElementById('statusFilter').value;

    filteredPatients = allPatients.filter(function(p) {
        const haystack = ((p.first_name||'') + ' ' + (p.middle_name||'') + ' ' + (p.last_name||'') + ' ' + (p.phone||'')).toLowerCase();
        const matchesSearch = !query || haystack.includes(query);

        let matchesStatus = true;
        if (statusVal) {
            const s = (p.queue_status || p.appointment_status || '');
            // Normalize variants ('pending_payment', 'Pending Payment', 'In Queue') so
            // the dropdown values match live queue statuses from the backend
            const norm = v => String(v || '').toLowerCase().replace(/_/g, ' ').trim();
            matchesStatus = (norm(s) === norm(statusVal));
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
            '<td style="padding:10px 12px;border-bottom:1px solid #f3f4f6;font-size:0.85rem;">' + fn + '</td>' +
            '<td style="padding:10px 12px;border-bottom:1px solid #f3f4f6;font-size:0.85rem;">' + mn + '</td>' +
            '<td style="padding:10px 12px;border-bottom:1px solid #f3f4f6;font-size:0.85rem;">' + ln + '</td>' +
            '<td style="padding:10px 12px;border-bottom:1px solid #f3f4f6;"><div style="font-size:0.8rem;"><span style="font-weight:500;">' + age + ' yrs</span><span style="color:#6b7280;margin-left:6px;">' + escapeHtml(gender.charAt(0).toUpperCase() + gender.slice(1)) + '</span></div></td>' +
            '<td style="padding:10px 12px;border-bottom:1px solid #f3f4f6;"><div style="font-size:0.78rem;color:#6b7280;"><div>' + escapeHtml(phone) + '</div><div>' + escapeHtml(email) + '</div></div></td>' +
            '<td style="padding:10px 12px;border-bottom:1px solid #f3f4f6;"><span style="font-weight:500;color:#111827;font-size:0.82rem;white-space:nowrap;">' + escapeHtml(svc) + '</span></td>' +
            '<td style="padding:10px 12px;border-bottom:1px solid #f3f4f6;"><span style="background:' + bg + ';color:' + text + ';padding:3px 10px;border-radius:9999px;font-size:0.72rem;font-weight:600;display:inline-block;white-space:nowrap;">' + label + '</span></td>' +
            '<td style="padding:10px 12px;border-bottom:1px solid #f3f4f6;"><div class="patient-kebab-menu"><button class="patient-kebab-btn" data-patient-id="' + pid + '">' +
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
    document.getElementById('staffModalPatientName').innerText = 'Loading...';
    document.getElementById('patientRecordContent').innerHTML = '<div style="text-align:center;padding:40px;color:#6b7280;">Loading patient records...</div>';
    document.getElementById('patientRecordModal').classList.add('active');

    fetch('../user-patient/patient_record_details.php?id=' + encodeURIComponent(patientId))
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const p = data.patient;
            const m = data.medical_history || {};
            const d = data.dental_history || {};
            const dh = data.intake_dental_history || {};
            const odontogramSessions = data.odontogram_sessions || [];
            const q = data.queue_item;
            const b = data.billing || {};
            const allBillings = data.all_billings || [];
            const payments = data.payments || [];
            const personalInfoUpdate = data.patient_personal_info_update || null;
            
            const allergies = m.allergies || 'None';
            const medications = m.current_medications || 'None';
            const conditions = m.medical_conditions || 'None';
            
            const billingId = b.id || '0';
            window._currentAllBillings = allBillings;
            // Use combined totals from all billings
            var totalAmount = 0, paidAmount = 0, balance = 0;
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
            const dueDate = b.due_date || null;
            
            document.getElementById('staffModalPatientName').innerText = (p.first_name || '') + ' ' + (p.last_name || '');
            window._currentRecordPatientId = p.id;
            window._currentRecordMedicalHistory = m;

            // Populate header avatar and registration badge
            try {
                const avatarEl = document.getElementById('staffModalPatientAvatar');
                const badgeEl = document.getElementById('staffModalPatientBadge');
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
            
            const hasMedicalAlert = allergies === 'Yes' ||
                                   conditions.toLowerCase().includes('diabetes') ||
                                   conditions.toLowerCase().includes('heart') ||
                                   conditions.toLowerCase().includes('blood pressure') ||
                                   conditions.toLowerCase().includes('asthma');
            

                                   
            document.getElementById('patientRecordContent').innerHTML = `
                <div class="patient-record-overview">
                    

                    <div class="patient-record-card" style="background:#f9fafb;border-radius:8px;padding:16px;margin-bottom:12px;">
                        <h4 style="margin:0 0 12px 0;color:#374151;font-size:14px;font-weight:600;">👤 Account Information</h4>
                        <div class="patient-record-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Username</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.username || '-'}</p></div>
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Email</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.email || '-'}</p></div>
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Phone</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.phone || '-'}</p></div>
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Address</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.street_address || '-'}</p></div>
                        </div>
                    </div>

                    <div class="patient-record-card" style="background:#f9fafb;border-radius:8px;padding:16px;margin-bottom:12px;">
                        <h4 style="margin:0 0 12px 0;color:#374151;font-size:14px;font-weight:600;">📝 Personal Information</h4>
                        ${personalInfoUpdate ? `<div style="font-size:12px;color:#6b7280;margin:-4px 0 12px;">Patient updated: ${new Date(personalInfoUpdate.updated_at).toLocaleString()}${personalInfoUpdate.changed_fields && personalInfoUpdate.changed_fields.length ? ' · Changed: ' + personalInfoUpdate.changed_fields.join(', ') : ''}</div>` : ''}
                        <div class="patient-record-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">First Name</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.first_name || '-'}</p></div>
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Middle Name</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.middle_name || '-'}</p></div>
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Last Name</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.last_name || '-'}</p></div>
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Gender</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.gender || '-'}</p></div>
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Date of Birth</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.date_of_birth || '-'}</p></div>
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Age</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.age || '-'}</p></div>
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Senior government ID</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.senior_citizen_id ? ((p.senior_id_type || 'ID') + ' · ' + p.senior_citizen_id) : '-'}</p></div>
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">PWD ID</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.pwd_id || '-'}</p></div>
                        </div>
                    </div>

                    <div class="patient-record-card" style="background:#f9fafb;border-radius:8px;padding:16px;margin-bottom:12px;">
                        <h4 style="margin:0 0 12px 0;color:#374151;font-size:14px;font-weight:600;">📞 Emergency Contact</h4>
                        <div class="patient-record-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Contact Name</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.emergency_contact_name || '-'}</p></div>
                            <div><p style="margin:0;color:#6b7280;font-size:12px;">Contact Phone</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.emergency_contact_phone || '-'}</p></div>
                            <div style="grid-column:1 / -1;"><p style="margin:0;color:#6b7280;font-size:12px;">Relationship</p><p style="margin:4px 0 0 0;color:#111827;font-size:14px;font-weight:500;">${p.emergency_contact_relationship || '-'}</p></div>
                        </div>
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
                                    '<td style="padding:10px 12px;text-align:right;color:#111827;font-weight:600;">P' + parseFloat(bl.total_amount).toFixed(2) + (parseFloat(bl.discount_amount || 0) > 0 ? '<div><span style="text-decoration:line-through;color:#94a3b8;font-size:11px;">P' + parseFloat(bl.subtotal_amount || 0).toFixed(2) + '</span> <span style="background:#ecfdf5;color:#047857;font-size:10px;font-weight:700;padding:1px 7px;border-radius:9999px;border:1px solid #a7f3d0;">' + String(bl.discount_type || '').toUpperCase() + ' 20% &minus;P' + parseFloat(bl.discount_amount).toFixed(2) + '</span></div>' : '') + '</td>' +
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

                <!-- Consultation History (includes Dental History) -->
                <div style="background: #f9fafb; border-radius: 12px; padding: 20px; margin-top: 20px;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: #374151; margin-bottom: 16px;">📋 Consultation History</h3>
                    ${odontogramSessions.length > 0 ? `
                        <div style="display:flex;flex-direction:column;gap:10px;">
                            ${odontogramSessions.map(function(session) {
                                var sessionDate = session.created_at ? new Date(session.created_at).toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' }) : 'N/A';
                                var sessionTime = session.created_at ? new Date(session.created_at).toLocaleTimeString('en-PH', { hour:'2-digit', minute:'2-digit' }) : '';
                                var dentistName = session.dentist_first_name ? 'Dr. ' + session.dentist_first_name + ' ' + session.dentist_last_name : 'Unknown Dentist';
                                var findingsCount = 0;
                                try { var fd = typeof session.teeth_data === 'string' ? JSON.parse(session.teeth_data) : session.teeth_data; findingsCount = fd ? fd.length : 0; } catch(e) { findingsCount = 0; }
                                window._odontogramSessionMap[session.id] = { date: sessionDate, time: sessionTime, dentist: dentistName, teeth_data: session.teeth_data, session: session };
                                return '<div style="background:white;border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px;display:flex;justify-content:space-between;align-items:center;">'+
                                    '<div>'+
                                        '<div style="font-weight:600;color:#111827;font-size:0.95rem;">'+sessionDate+' <span style="color:#6b7280;font-weight:400;font-size:0.85rem;">'+sessionTime+'</span></div>'+
                                        '<div style="font-size:0.85rem;color:#6b7280;margin-top:4px;">'+dentistName+' &middot; <span style="color:#0ea5e9;">'+findingsCount+' finding'+(findingsCount!==1?'s':'')+'</span></div>'+
                                    '</div>'+
                                    '<button onclick="openOdontogramSession('+session.id+')" style="padding:8px 16px;background:#3b82f6;color:white;border:none;border-radius:8px;font-size:0.85rem;font-weight:500;cursor:pointer;white-space:nowrap;">View Chart</button>'+
                                '</div>';
                            }).join('')}
                        </div>
                    ` : `
                        <p style="color:#6b7280;text-align:center;padding:20px;background:#f3f4f6;border-radius:8px;">No odontogram records found.</p>
                    `}
                    <div style="margin-top:16px;padding-top:14px;border-top:1px solid #e5e7eb;">
                        <h4 style="font-size:0.9rem;font-weight:600;color:#374151;margin:0 0 12px;">📜 Dental History</h4>
                        <div class="patient-info-grid">
                            <div class="patient-info-item">
                                <div class="patient-info-label">Previous Dentist</div>
                                <div class="patient-info-value">${dh.previous_dentist || d.previous_dentist || 'N/A'}</div>
                            </div>
                            <div class="patient-info-item">
                                <div class="patient-info-label">Last Visit</div>
                                <div class="patient-info-value">${dh.last_visit_date || d.last_visit_date || 'N/A'}</div>
                            </div>
                            <div class="patient-info-item" style="grid-column: 1 / -1;">
                                <div class="patient-info-label">Current Complaints</div>
                                <div class="patient-info-value">${dh.current_complaints || d.current_complaints || 'None'}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- bottom close removed; using top-right close button -->
            `;
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
        <a href="javascript:void(0)" data-action="prescriptions" data-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="12" y1="18" x2="12" y2="12"></line>
                <line x1="9" y1="15" x2="15" y2="15"></line>
            </svg>
            Prescriptions
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
        case 'prescriptions':
            window.location.href = 'staff_prescriptions.php?patient_id=' + id;
            break;
    }
}

if (window.__staffPatientKebabClickHandler) {
    document.removeEventListener('click', window.__staffPatientKebabClickHandler);
}
window.__staffPatientKebabClickHandler = function(e) {
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
};
document.addEventListener('click', window.__staffPatientKebabClickHandler);

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
            (window.appCache ? window.appCache.refreshCurrent(['staff-dashboard.php']) : location.reload());
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
    var rpModal = document.getElementById('recordPaymentModal');
    rpModal.classList.add('active');
    rpModal.style.display = 'flex';
}

function closeRecordPaymentModal() {
    var rpModal = document.getElementById('recordPaymentModal');
    rpModal.classList.remove('active');
    rpModal.style.display = 'none';
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
            (window.appCache ? window.appCache.refreshCurrent(['staff-dashboard.php']) : location.reload());
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
    modal.classList.add('active');
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
    var phModal = document.getElementById('paymentHistoryModal');
    phModal.classList.remove('active');
    phModal.style.display = 'none';
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

    modal.classList.add('active');
    modal.style.display = 'flex';
}

function closeSetDueDateModal() {
    var sdModal = document.getElementById('setDueDateModal');
    sdModal.classList.remove('active');
    sdModal.style.display = 'none';
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
            (window.appCache ? window.appCache.refreshCurrent(['staff-dashboard.php']) : location.reload());
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
    
}

// ===================== ODONTOGRAM READ-ONLY =====================
var odontogramTeethFindings = [];
window._odontogramSessionMap = {};
var odAdultUpperRight = [18,17,16,15,14,13,12,11];
var odAdultUpperLeft = [21,22,23,24,25,26,27,28];
var odAdultLowerRight = [48,47,46,45,44,43,42,41];
var odAdultLowerLeft = [31,32,33,34,35,36,37,38];
var odPedUpperRight = [55,54,53,52,51];
var odPedUpperLeft = [61,62,63,64,65];
var odPedLowerRight = [85,84,83,82,81];
var odPedLowerLeft = [71,72,73,74,75];
var OD_SVG_SIZE = 44, OD_CX = 22, OD_CY = 22, OD_R = 10;

function odGetSurfaceColor(toothNum, surface) {
    var f = odontogramTeethFindings.find(function(x) { return x.tooth === toothNum && x.surface === surface; });
    if (!f) return null;
    if (f.type === 'pathology') return '#ef4444';
    if (f.type === 'treatment') return '#7dd3fc';
    return null;
}
function odGetWholeToothColor(toothNum) {
    var f = odontogramTeethFindings.find(function(x) { return x.tooth === toothNum && !x.surface; });
    if (!f) return null;
    if (f.type === 'pathology') return '#ef4444';
    if (f.type === 'treatment') return '#7dd3fc';
    if (f.type === 'surgery') return '#ef4444';
    if (f.type === 'condition') return '#ef4444';
    return null;
}
function odCreateToothSVG(toothNum) {
    var S = OD_SVG_SIZE, C = OD_CX, CY = OD_CY, R = OD_R;
    var wholeFill = odGetWholeToothColor(toothNum);
    var labelColor = '#94a3b8';
    var svg = '<svg width="'+S+'" height="'+S+'" viewBox="0 0 '+S+' '+S+'" xmlns="http://www.w3.org/2000/svg">';
    svg += '<rect x="1" y="1" width="'+(S-2)+'" height="'+(S-2)+'" fill="'+(wholeFill || 'white')+'" stroke="#94a3b8" stroke-width="1.5" rx="2"/>';
    var bFill = odGetSurfaceColor(toothNum,'B') || wholeFill || 'white';
    var pFill = odGetSurfaceColor(toothNum,'P') || wholeFill || 'white';
    var leftFill = odGetSurfaceColor(toothNum,'D') || wholeFill || 'white';
    var rightFill = odGetSurfaceColor(toothNum,'M') || wholeFill || 'white';
    var oFill = odGetSurfaceColor(toothNum,'O') || wholeFill || 'white';
    svg += '<polygon points="1,1 '+C+','+CY+' '+(S-1)+',1" fill="'+bFill+'" stroke="#94a3b8" stroke-width="0.8"/>';
    svg += '<polygon points="1,'+(S-1)+' '+C+','+CY+' '+(S-1)+','+(S-1)+'" fill="'+pFill+'" stroke="#94a3b8" stroke-width="0.8"/>';
    svg += '<polygon points="1,1 '+C+','+CY+' 1,'+(S-1)+'" fill="'+leftFill+'" stroke="#94a3b8" stroke-width="0.8"/>';
    svg += '<polygon points="'+(S-1)+',1 '+C+','+CY+' '+(S-1)+','+(S-1)+'" fill="'+rightFill+'" stroke="#94a3b8" stroke-width="0.8"/>';
    svg += '<circle cx="'+C+'" cy="'+CY+'" r="'+R+'" fill="'+oFill+'" stroke="#94a3b8" stroke-width="1"/>';
    svg += '<text x="'+C+'" y="'+(CY+3)+'" text-anchor="middle" font-size="7" font-weight="700" fill="'+labelColor+'" pointer-events="none">O</text>';
    svg += '<text x="'+C+'" y="9" text-anchor="middle" font-size="5" font-weight="700" fill="'+labelColor+'" pointer-events="none">B</text>';
    svg += '<text x="'+C+'" y="'+(S-3)+'" text-anchor="middle" font-size="5" font-weight="700" fill="'+labelColor+'" pointer-events="none">P</text>';
    svg += '<text x="5" y="'+(CY+2)+'" text-anchor="middle" font-size="5" font-weight="700" fill="'+labelColor+'" pointer-events="none">D</text>';
    svg += '<text x="'+(S-5)+'" y="'+(CY+2)+'" text-anchor="middle" font-size="5" font-weight="700" fill="'+labelColor+'" pointer-events="none">M</text>';
    svg += '</svg>';
    return svg;
}
function odRenderToothColumn(toothNum) {
    var findings = odontogramTeethFindings.filter(function(f) { return f.tooth === toothNum; });
    var badgesHtml = '';
    findings.forEach(function(f) {
        var txt = f.surface ? f.surface+':'+f.code : f.code;
        var cls = 'odontogram-tooth-finding-badge ' + f.type;
        badgesHtml += '<span class="'+cls+'">'+txt+'</span>';
    });
    return '<div class="odontogram-tooth-col">'+
        '<div class="odontogram-tooth-num-label">'+toothNum+'</div>'+
        '<div class="odontogram-tooth-svg-wrap">'+odCreateToothSVG(toothNum)+'</div>'+
        '<div class="odontogram-tooth-findings-below">'+badgesHtml+'</div>'+
    '</div>';
}
function odRenderQuadrant(containerId, teeth, label, mirror) {
    var container = document.getElementById(containerId);
    if (!container) return;
    var html = '<div class="odontogram-quadrant-block">';
    html += '<div class="odontogram-quadrant-label">'+label+'</div>';
    html += '<div class="odontogram-tooth-row">';
    var arr = mirror ? teeth.slice().reverse() : teeth;
    arr.forEach(function(t) { html += odRenderToothColumn(t); });
    html += '</div></div>';
    container.innerHTML = html;
}
function odRenderToothChart() {
    var upperRow = document.getElementById('odAdultTeethChart');
    var lowerRow = document.getElementById('odPedTeethChart');
    if (!upperRow || !lowerRow) return;
    var html = '<div class="odontogram-quadrant-row"><div id="odAdultUR" style="display:flex;"></div><div id="odAdultUL" style="display:flex;"></div></div>';
    html += '<div style="border-top:2px dashed #d1d5db;margin:4px 20px;"></div>';
    html += '<div class="odontogram-quadrant-row"><div id="odAdultLR" style="display:flex;"></div><div id="odAdultLL" style="display:flex;"></div></div>';
    upperRow.innerHTML = html;
    var phtml = '<div class="odontogram-quadrant-row"><div id="odPedUR" style="display:flex;"></div><div id="odPedUL" style="display:flex;"></div></div>';
    phtml += '<div style="border-top:2px dashed #d1d5db;margin:4px 20px;"></div>';
    phtml += '<div class="odontogram-quadrant-row"><div id="odPedLR" style="display:flex;"></div><div id="odPedLL" style="display:flex;"></div></div>';
    lowerRow.innerHTML = phtml;
    odRenderQuadrant('odAdultUR', odAdultUpperRight, 'Upper Right', false);
    odRenderQuadrant('odAdultUL', odAdultUpperLeft, 'Upper Left', false);
    odRenderQuadrant('odAdultLR', odAdultLowerRight, 'Lower Right', false);
    odRenderQuadrant('odAdultLL', odAdultLowerLeft, 'Lower Left', false);
    odRenderQuadrant('odPedUR', odPedUpperRight, 'Upper Right', false);
    odRenderQuadrant('odPedUL', odPedUpperLeft, 'Upper Left', false);
    odRenderQuadrant('odPedLR', odPedLowerRight, 'Lower Right', false);
    odRenderQuadrant('odPedLL', odPedLowerLeft, 'Lower Left', false);
}
function odRenderFindings() {
    var container = document.getElementById('odFindingsChips');
    if (!container) return;
    if (odontogramTeethFindings.length === 0) {
        container.innerHTML = '<span style="color:#94a3b8;font-size:0.78rem;">No findings recorded.</span>';
        return;
    }
    var html = '';
    odontogramTeethFindings.forEach(function(f) {
        var label = '#' + f.tooth;
        if (f.surface) label += ' [' + f.surface + ']';
        label += ' - ' + f.code + ' ' + f.label;
        html += '<span class="odontogram-finding-chip">' + label + '</span>';
    });
    container.innerHTML = html;
}
function openOdontogramSession(sessionId) {
    var s = window._odontogramSessionMap[sessionId];
    if (!s) return;
    var dateStr = s.date + (s.time ? ' ' + s.time : '');
    openOdontogramModal(sessionId, dateStr, s.dentist, s.teeth_data, s.session);
}
function odxVitalHtml(label, value, suffix) {
    var v = (value === null || value === undefined) ? '' : String(value).trim();
    return '<div class="odx-vital"><div class="odx-vital-label">' + label + '</div><div class="odx-vital-value">'
        + (v ? escapeHtml(v) + (suffix || '') : '&mdash;') + '</div></div>';
}
function odxChipHtml(label, val) {
    var on = !!parseInt(val);
    return '<span class="odx-chip' + (on ? ' on' : '') + '">' + (on ? '&#10003; ' : '') + label + '</span>';
}
function odxSection(title, innerHtml) {
    return '<div class="odx-section"><h5>' + title + '</h5>' + innerHtml + '</div>';
}
function openOdontogramModal(sessionId, dateStr, dentistName, findingsJson, session) {
    session = session || {};
    try {
        odontogramTeethFindings = typeof findingsJson === 'string' ? JSON.parse(findingsJson) : (findingsJson || []);
    } catch(e) { odontogramTeethFindings = []; }
    document.getElementById('odontogramModalTitle').textContent = 'Odontogram - ' + dateStr + (dentistName ? ' | ' + dentistName : '');

    var content = '<div class="odontogram-chart-area">';
    content += '<div class="odontogram-chart-section-label">Adult Teeth</div>';
    content += '<div id="odAdultTeethChart"></div>';
    content += '<div class="odontogram-chart-section-label" style="margin-top:16px;">Pediatric Teeth</div>';
    content += '<div id="odPedTeethChart"></div>';
    content += '<div class="odontogram-findings-display"><h5>Tooth Findings</h5><div id="odFindingsChips"></div></div>';
    content += '</div>';

    // Compact Dental Examination summary (vitals + exam checkboxes)
    var examHtml = '<div class="odx-subrow" style="margin-bottom:12px;">';
    examHtml += '<div style="flex:1 1 100%;"><div class="odx-subblock-title">Vital Signs &amp; Body Measurement</div><div class="odx-vitals-grid">';
    examHtml += odxVitalHtml('Blood Pressure', session.blood_pressure, '');
    examHtml += odxVitalHtml('Heart Rate', session.heart_rate, ' bpm');
    examHtml += odxVitalHtml('Temperature', session.temperature, ' &deg;C');
    examHtml += odxVitalHtml('O2 Saturation', session.oxygen_saturation, ' %');
    examHtml += odxVitalHtml('Height', session.height, ' cm');
    examHtml += odxVitalHtml('Weight', session.weight, ' kg');
    examHtml += '</div></div></div>';

    var periodontal = (session.periodontal_screening || '').trim();
    var occChips = (session.occlusion_molar_class ? '<span class="odx-chip on">Molar: ' + escapeHtml(session.occlusion_molar_class) + '</span>' : (parseInt(session.occlusion_class_molar) ? '<span class="odx-chip on">Molar: previously checked; class unspecified</span>' : '<span class="odx-empty">Molar class not recorded</span>'))
        + odxChipHtml('Overjet', session.occlusion_overjet)
        + odxChipHtml('Overbite', session.occlusion_overbite)
        + odxChipHtml('Midline Deviation', session.occlusion_midline_deviation)
        + odxChipHtml('Crossbite', session.occlusion_crossbite) + (session.occlusion_notes ? '<span class="odx-chip on">Occlusion notes: ' + escapeHtml(session.occlusion_notes) + '</span>' : '');
    var appChips = odxChipHtml('Orthodontic', session.appliances_orthodontic)
        + odxChipHtml('Skyplate', session.appliances_skyplate)
        + odxChipHtml('Others', session.appliances_others);
    if (parseInt(session.appliances_others) && session.appliances_others_text) {
        appChips += '<span class="odx-chip on">' + escapeHtml(session.appliances_others_text) + '</span>';
    }
    var tmdChips = odxChipHtml('Clenching', session.tmd_clenching)
        + odxChipHtml('Clicking', session.tmd_clicking)
        + odxChipHtml('Trismus', session.tmd_trismus)
        + odxChipHtml('Muscle Spasm', session.tmd_muscle_spasm);

    examHtml += '<div class="odx-subrow">';
    examHtml += '<div class="odx-subblock"><div class="odx-subblock-title">Periodontal Screening</div><div>'
        + (periodontal ? '<span class="odx-chip on">' + escapeHtml(periodontal) + '</span>' : '<span class="odx-empty">Not recorded</span>')
        + '</div></div>';
    examHtml += '<div class="odx-subblock"><div class="odx-subblock-title">Occlusion</div><div class="odx-check-row">' + occChips + '</div></div>';
    examHtml += '</div>';
    examHtml += '<div class="odx-subrow" style="margin-top:10px;">';
    examHtml += '<div class="odx-subblock"><div class="odx-subblock-title">Appliances</div><div class="odx-check-row">' + appChips + '</div></div>';
    examHtml += '<div class="odx-subblock"><div class="odx-subblock-title">TMD</div><div class="odx-check-row">' + tmdChips + '</div></div>';
    examHtml += '</div>';
    content += odxSection('Dental Examination', examHtml);

    // X-Ray images
    content += odxSection('X-Ray', '<div id="odXrayContainer"><span class="odx-empty">Loading x-rays...</span></div>');

    // Consultation Notes
    var notesVal = (session.consultation_notes || '').trim();
    content += odxSection('Consultation Notes', notesVal
        ? '<div class="odx-text">' + escapeHtml(notesVal) + '</div>'
        : '<span class="odx-empty">No consultation notes recorded.</span>');

    // Dental & Medical History
    var mh = window._currentRecordMedicalHistory || {};
    var mhLabels = [
        ['heart_condition','Heart Condition'],['diabetes','Diabetes'],['bleeding_disorder','Bleeding Disorder'],
        ['pregnancy','Pregnancy'],['blood_pressure','Blood Pressure Issues'],['hiv_aids','HIV/AIDS'],
        ['hepatitis','Hepatitis'],['tuberculosis','Tuberculosis'],['asthma','Asthma'],['epilepsy','Epilepsy'],
        ['std','STD'],['stomach_ulcer','Stomach Ulcer'],['fainting_seizure','Fainting / Seizure'],
        ['rapid_weight_loss','Rapid Weight Loss'],['rheumatic_fever','Rheumatic Fever'],['hay_fever','Hay Fever / Allergies'],
        ['respiratory_problems','Respiratory Problems'],['swollen_ankles','Swollen Ankles'],['kidney_disease','Kidney Disease'],
        ['chest_pain','Chest Pain'],['stroke','Stroke']
    ];
    var historyInner = '';
    var mhPositive = '';
    mhLabels.forEach(function(f) {
        if (mh[f[0]] == 1 || mh[f[0]] === '1') {
            mhPositive += '<span class="odx-chip on">' + f[1] + '</span> ';
        }
    });
    historyInner += '<div class="odx-subrow"><div class="odx-subblock"><div class="odx-subblock-title">Medical History</div>';
    historyInner += mhPositive ? '<div class="odx-check-row">' + mhPositive + '</div>' : '<span class="odx-empty">No conditions marked.</span>';
    if (mh.allergies) historyInner += '<div class="odx-tx-meta" style="margin-top:6px;"><strong>Allergies:</strong> ' + escapeHtml(mh.allergies) + '</div>';
    if (mh.current_medications) historyInner += '<div class="odx-tx-meta" style="margin-top:3px;"><strong>Medications:</strong> ' + escapeHtml(mh.current_medications) + '</div>';
    historyInner += '</div>';
    var dhNotes = (session.dental_medical_history_notes || '').trim();
    historyInner += '<div class="odx-subblock"><div class="odx-subblock-title">Dental History Notes</div>'
        + (dhNotes ? '<div class="odx-text">' + escapeHtml(dhNotes) + '</div>' : '<span class="odx-empty">No dental history notes recorded.</span>')
        + '</div></div>';
    content += odxSection('Dental & Medical History', historyInner);

    // Treatment Plan (patient-level, loaded async)
    content += odxSection('Treatment Plan', '<div id="odTxPlansContainer"><span class="odx-empty">Loading treatment plans...</span></div>');

    // Prescription
    var rxItems = [];
    try { rxItems = typeof session.prescription_data === 'string' ? JSON.parse(session.prescription_data) : (session.prescription_data || []); } catch(e) { rxItems = []; }
    var rxHtml = '';
    if (rxItems.length) {
        rxItems.forEach(function(item) {
            if (!item || !item.name) return;
            rxHtml += '<div class="odx-rx-item"><div class="odx-rx-name">' + escapeHtml(item.name) + '</div>';
            var meta = [item.dosage, item.frequency, item.duration].filter(function(x) { return x && String(x).trim(); }).map(escapeHtml).join(' &middot; ');
            if (meta) rxHtml += '<div class="odx-rx-meta">' + meta + '</div>';
            if (item.instructions) rxHtml += '<div class="odx-rx-instr">&#128221; ' + escapeHtml(item.instructions) + '</div>';
            rxHtml += '</div>';
        });
    }
    content += odxSection('Prescription', rxHtml || '<span class="odx-empty">No medications prescribed.</span>');

    // Diagnosis
    var dxHtml = '';
    if (session.primary_icd10) {
        dxHtml += '<span class="odx-dx-chip"><strong>' + escapeHtml(session.primary_icd10) + '</strong> ' + escapeHtml(session.primary_icd10_desc || '') + '</span>';
    }
    if (session.secondary_icd10) {
        dxHtml += '<span class="odx-dx-chip"><strong>' + escapeHtml(session.secondary_icd10) + '</strong> ' + escapeHtml(session.secondary_icd10_desc || '') + '</span>';
    }
    var narrative = (session.diagnosis_narrative || '').trim();
    if (narrative) {
        dxHtml += '<div class="odx-text" style="margin-top:8px;">' + escapeHtml(narrative) + '</div>';
    }
    content += odxSection('Diagnosis', dxHtml || '<span class="odx-empty">No diagnosis recorded.</span>');

    document.getElementById('odontogramModalContent').innerHTML = content;
    document.getElementById('odontogramModalOverlay').classList.add('active');
    odRenderToothChart();
    odRenderFindings();
    loadOdontogramTreatmentPlans(window._currentRecordPatientId);
    loadOdontogramXrays(sessionId);
}
function loadOdontogramXrays(consultationId) {
    var container = document.getElementById('odXrayContainer');
    if (!container) return;
    if (!consultationId) {
        container.innerHTML = '<span class="odx-empty">No x-rays available.</span>';
        return;
    }
    window._odXrayList = [];
    fetch('../shared/xray_actions.php?action=get&consultation_id=' + consultationId)
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (!container.isConnected) return;
        var xrays = (d.success && d.xrays) ? d.xrays : [];
        if (!xrays.length) {
            container.innerHTML = '<span class="odx-empty">No x-ray images uploaded for this consultation.</span>';
            return;
        }
        window._odXrayList = xrays;
        var html = '';
        xrays.forEach(function(x, idx) {
            var name = x.original_filename || x.file_path.split('/').pop();
            html += '<div class="odx-xray-row">'
                + '<span class="odx-xray-name" title="' + escapeHtml(name) + '">' + escapeHtml(name) + '</span>'
                + '<button type="button" class="odx-eye-btn" title="View x-ray" onclick="openOdXrayViewer(' + idx + ')">'
                + '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
                + '</button></div>';
        });
        container.innerHTML = html;
    })
    .catch(function() {
        if (container && container.isConnected) container.innerHTML = '<span class="odx-empty">Error loading x-rays.</span>';
    });
}
function openOdXrayViewer(idx) {
    var x = (window._odXrayList || [])[idx];
    if (!x) return;
    var name = x.original_filename || x.file_path.split('/').pop();
    var src = '../../' + x.file_path;
    document.getElementById('odXrayViewerTitle').textContent = name;
    document.getElementById('odXrayViewerImg').src = src;
    var dl = document.getElementById('odXrayDownloadBtn');
    dl.href = src;
    dl.setAttribute('download', name);
    document.getElementById('odXrayViewerOverlay').style.display = 'flex';
}
function closeOdXrayViewerDirect() {
    document.getElementById('odXrayViewerOverlay').style.display = 'none';
}
function closeOdXrayViewer(e) {
    if (e.target === document.getElementById('odXrayViewerOverlay')) closeOdXrayViewerDirect();
}
function loadOdontogramTreatmentPlans(patientId) {
    var container = document.getElementById('odTxPlansContainer');
    if (!container) return;
    if (!patientId) {
        container.innerHTML = '<span class="odx-empty">No patient linked.</span>';
        return;
    }
    fetch('../shared/process_treatment_plan.php?patient_id=' + encodeURIComponent(patientId))
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!container.isConnected) return;
        if (data.success && data.plans && data.plans.length) {
            var html = '';
            data.plans.forEach(function(plan) {
                var status = (plan.overall_status || 'Active');
                var statusCls = status.toLowerCase().replace(/\s+/g, '-');
                html += '<div class="odx-tx-plan"><div class="odx-tx-head"><span class="odx-tx-name">' + escapeHtml(plan.plan_name || 'Treatment Plan') + '</span>'
                    + '<span class="odx-tx-status ' + statusCls + '">' + escapeHtml(status) + '</span></div>';
                var meta = [(plan.total_sessions || 1) + ' session(s)'];
                if (plan.total_estimated_cost && parseFloat(plan.total_estimated_cost) > 0) {
                    meta.push('Est. Cost: P' + parseFloat(plan.total_estimated_cost).toFixed(2));
                }
                if (plan.teeth_numbers) meta.push('Teeth: ' + plan.teeth_numbers);
                html += '<div class="odx-tx-meta">' + meta.map(escapeHtml).join(' | ') + '</div>';
                if (plan.sessions_data) {
                    try {
                        var sessions = JSON.parse(plan.sessions_data);
                        sessions.forEach(function(s, idx) {
                            var parts = [];
                            if (s.date) {
                                var d = new Date(s.date + 'T' + (s.time || '00:00:00'));
                                parts.push(d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }));
                            }
                            if (s.time) parts.push(s.time);
                            html += '<div class="odx-tx-session">Session ' + (idx + 1) + ': '
                                + (parts.length ? escapeHtml(parts.join(' @ ')) : '<span class="odx-empty">Not scheduled</span>')
                                + (s.treatment ? ' &mdash; ' + escapeHtml(s.treatment) : '')
                                + (s.status ? ' (' + escapeHtml(s.status) + ')' : '')
                                + '</div>';
                        });
                    } catch(e) {}
                }
                if (plan.notes) html += '<div class="odx-tx-session">&#128221; ' + escapeHtml(plan.notes) + '</div>';
                html += '</div>';
            });
            container.innerHTML = html;
        } else {
            container.innerHTML = '<span class="odx-empty">No treatment plans for this patient.</span>';
        }
    })
    .catch(function() {
        if (container && container.isConnected) container.innerHTML = '<span class="odx-empty">Error loading treatment plans.</span>';
    });
}
function closeOdontogramModal(e) {
    if (e.target === document.getElementById('odontogramModalOverlay')) {
        closeOdontogramModalDirect();
    }
}
function closeOdontogramModalDirect() {
    document.getElementById('odontogramModalOverlay').classList.remove('active');
}
</script>

<!-- Odontogram History Modal -->
<div id="odontogramModalOverlay" class="odontogram-modal-overlay" onclick="closeOdontogramModal(event)">
    <div class="odontogram-modal-box" onclick="event.stopPropagation()">
        <div class="odontogram-modal-header">
            <h3 id="odontogramModalTitle">Odontogram - Session</h3>
            <button class="odontogram-modal-close" onclick="closeOdontogramModalDirect()">&times;</button>
        </div>
        <div id="odontogramModalContent"></div>
    </div>
</div>

<!-- X-Ray Viewer Overlay -->
<div id="odXrayViewerOverlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.75);z-index:1100000;align-items:center;justify-content:center;" onclick="closeOdXrayViewer(event)">
    <div style="background:white;border-radius:14px;max-width:640px;width:92%;max-height:88vh;padding:16px;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,0.4);" onclick="event.stopPropagation()">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <div style="font-weight:700;color:#0f172a;font-size:0.95rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-right:12px;" id="odXrayViewerTitle">X-Ray</div>
            <button type="button" onclick="closeOdXrayViewerDirect()" style="background:none;border:none;font-size:1.5rem;line-height:1;cursor:pointer;color:#64748b;">&times;</button>
        </div>
        <div style="overflow:auto;text-align:center;background:#f8fafc;border-radius:8px;padding:8px;">
            <img id="odXrayViewerImg" src="" alt="X-Ray" style="max-width:100%;max-height:60vh;">
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:12px;">
            <a id="odXrayDownloadBtn" href="#" download style="display:inline-flex;align-items:center;gap:6px;background:#0ea5e9;color:white;font-size:0.82rem;font-weight:600;padding:8px 16px;border-radius:8px;text-decoration:none;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download
            </a>
        </div>
    </div>
</div>

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
<div id="recordPaymentModal" class="modal-overlay" style="z-index:120000;">
    <div class="modal-backdrop"></div>
    <div class="modal-container">
        <div class="modal" style="max-width:480px;">
            <h2 style="margin:0 0 16px;font-size:1.25rem;font-weight:600;">💰 Record Payment</h2>
            <input type="hidden" id="recordPaymentPatientId">
            <input type="hidden" id="recordPaymentBillingId">
            <div id="recordPaymentBreakdown" style="background:#f9fafb;border-radius:8px;padding:14px;margin-bottom:16px;border:1px solid #e5e7eb;">
            </div>
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px;margin-bottom:16px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="color:#166534;font-weight:600;font-size:0.95rem;">Total Outstanding Balance</span>
                    <span id="recordPaymentBalance" style="color:#dc2626;font-weight:700;font-size:1.15rem;">P0.00</span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-top:6px;font-size:0.85rem;">
                    <span style="color:#6b7280;">Total Amount: <span id="recordPaymentTotalAmount">P0.00</span></span>
                    <span style="color:#6b7280;">Paid So Far: <span id="recordPaymentPaidSoFar" style="color:#16a34a;">P0.00</span></span>
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
                    <option value="gcash">GCash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="installment">Installment</option>
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
<div id="paymentHistoryModal" class="modal-overlay" style="z-index:120000;">
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
<div id="setDueDateModal" class="modal-overlay" style="z-index:120000;">
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
