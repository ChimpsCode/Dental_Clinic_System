<?php
$pageTitle = 'Consultation';
require_once '../../config/database.php';
require_once '../../includes/dentist_layout_start.php';
require_once '../../includes/occlusion_schema.php';
ensureOcclusionSchema($pdo);

$user_id = (int)$_SESSION['user_id'];
$initialQueueId = isset($_GET['queue_id']) ? (int)$_GET['queue_id'] : 0;
$pastConsultId = isset($_GET['past_consultation_id']) ? (int)$_GET['past_consultation_id'] : 0;
$isPastView = false;
$pastConsultData = null;

if ($pastConsultId) {
    $isPastView = true;
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, q.treatment_type AS queue_treatment_type, q.queue_number,
                   p.first_name, p.middle_name, p.last_name, p.suffix,
                   u.first_name AS dentist_first_name, u.last_name AS dentist_last_name
            FROM consultations c
            LEFT JOIN queue q ON q.id = c.queue_id
            LEFT JOIN patients p ON p.id = CAST(c.patient_id AS UNSIGNED)
            LEFT JOIN users u ON u.id = c.dentist_id
            WHERE c.id = ?
        ");
        $stmt->execute([$pastConsultId]);
        $pastConsultData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($pastConsultData) {
            $parts = array_filter([$pastConsultData['first_name'] ?? '', $pastConsultData['middle_name'] ?? '', $pastConsultData['last_name'] ?? '', $pastConsultData['suffix'] ?? '']);
            $pastConsultData['patient_name'] = implode(' ', $parts);
            $pastConsultData['treatment_type'] = $pastConsultData['queue_treatment_type'] ?? '';
            $pastConsultData['dentist_name'] = implode(' ', array_filter([$pastConsultData['dentist_first_name'] ?? '', $pastConsultData['dentist_last_name'] ?? ''])) ?: 'Unknown';
            if (is_string($pastConsultData['teeth_data'] ?? '')) {
                $pastConsultData['teeth_data'] = json_decode($pastConsultData['teeth_data'], true);
            }
            if (is_string($pastConsultData['prescription_data'] ?? '')) {
                $pastConsultData['prescription_data'] = json_decode($pastConsultData['prescription_data'], true);
            }
            $stmt2 = $pdo->prepare("SELECT * FROM medical_history WHERE patient_id = ? ORDER BY id DESC LIMIT 1");
            $stmt2->execute([$pastConsultData['patient_id']]);
            $pastConsultData['medical_history'] = $stmt2->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $pastConsultData = null;
    }
}

$queuePatients = [];
if (!$isPastView) {
    $today = date('Y-m-d');
    try {
        $stmt = $pdo->prepare("
            SELECT q.id as queue_id, q.queue_number, q.status, q.patient_id, q.treatment_type,
                   p.first_name, p.middle_name, p.last_name, p.suffix
            FROM queue q
            LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED)
            WHERE q.status IN ('in_procedure', 'waiting')
              AND COALESCE(q.queue_date, DATE(q.created_at)) = ?
            ORDER BY q.queue_number ASC, q.created_at ASC
        ");
        $stmt->execute([$today]);
        $queuePatients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($queuePatients as &$pt) {
            $parts = array_filter([$pt['first_name'] ?? '', $pt['middle_name'] ?? '', $pt['last_name'] ?? '', $pt['suffix'] ?? '']);
            $pt['full_name'] = implode(' ', $parts);
        }
        unset($pt);
    } catch (Exception $e) {
        $queuePatients = [];
    }
}

$services = [];
try {
    $services = $pdo->query("SELECT id, name, price, mode, duration_minutes FROM services WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $services = [];
}

// Clinic settings for Rx print (mirrors dentist_prescriptions.php)
$clinicName = 'RF Dental Clinic';
$clinicAddress = '';
$clinicPhone = '';
try {
    $clinicSettings = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($clinicRow = $clinicSettings->fetch(PDO::FETCH_ASSOC)) {
        if ($clinicRow['setting_key'] === 'clinic_name') $clinicName = $clinicRow['setting_value'];
        if ($clinicRow['setting_key'] === 'clinic_address') $clinicAddress = $clinicRow['setting_value'];
        if ($clinicRow['setting_key'] === 'clinic_phone') $clinicPhone = $clinicRow['setting_value'];
    }
} catch (Exception $e) {}
require_once '../../includes/medical_certificate_letterhead.php';
$certificateLetterhead = medicalCertificateLetterhead($pdo);
$rxClinicDetails = trim($clinicAddress . ' · ' . $clinicPhone, ' ·');
$rxDoctorName = trim($_SESSION['display_name'] ?? ($_SESSION['username'] ?? 'Doctor'));
?>

<style>
.consult-container {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 70px);
    overflow: hidden;
}
.content-area {
    padding: 0;
}
.consult-header {
    background: #0e8ded;
    color: white;
    padding: 16px 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0; 
}
.consult-header-left h2 {
    font-size: 1.35rem;
    font-weight: 700;
    margin: 0 0 4px 0;
}
.consult-header-left .consult-patient-info {
    font-size: 0.9rem;
    opacity: 0.9;
}
.consult-header-left .consult-patient-info span {
    font-weight: 700;
}
.consult-complete-btn {
    background: white;
    color: #0891b2;
    border: none;
    padding: 10px 22px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.consult-complete-btn:hover {
    background: #f0fdf4;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.consult-queue-bar {
    background: #f1f5f9;
    border-bottom: 1px solid #e2e8f0;
    padding: 10px 28px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
    overflow-x: auto;
}
.consult-queue-label {
    font-size: 0.8rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    white-space: nowrap;
    flex-shrink: 0;
}
.consult-queue-chip {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    padding: 6px 14px;
    font-size: 0.82rem;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 6px;
}
.consult-queue-chip:hover {
    border-color: #0ea5e9;
    color: #0284c7;
}
.consult-queue-chip.active {
    background: #0ea5e9;
    border-color: #0ea5e9;
    color: white;
}
.consult-queue-chip .chip-num {
    background: rgba(0,0,0,0.1);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.75rem;
}
.consult-queue-chip.active .chip-num {
    background: rgba(255,255,255,0.25);
}
.consult-tabs-bar {
    background: white;
    border-bottom: 2px solid #e2e8f0;
    padding: 0 10px;
    display: flex;
    align-items: stretch;
    flex-shrink: 0;
    overflow-x: auto;
}
.consult-tab-btn {
    padding: 14px 20px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #64748b;
    border: none;
    background: none;
    cursor: pointer;
    position: relative;
    transition: color 0.2s;
    white-space: nowrap;
}
.consult-tab-btn:hover {
    color: #0f172a;
}
.consult-tab-btn.active {
    color: #0891b2;
}
.consult-tab-btn.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(135deg, #0891b2, #0f9f53);
    border-radius: 3px 3px 0 0;
}
.consult-tab-content {
    flex: 1;
    overflow-y: auto;
    padding: 24px 20px;
}
.consult-tab-panel {
    display: none;
}
.consult-tab-panel.active {
    display: block;
    animation: fadeIn 0.3s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}
.consult-empty-state {
    text-align: center;
    padding: 80px 20px;
    color: #94a3b8;
}
.consult-empty-state svg {
    width: 64px;
    height: 64px;
    margin-bottom: 16px;
    opacity: 0.4;
}
.consult-empty-state h3 {
    font-size: 1.2rem;
    color: #64748b;
    margin-bottom: 8px;
}

/* Dental Exam Styles */
.dental-exam-grid {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 24px;
}
.dental-exam-form {
    display: flex;
    flex-direction: column;
    gap: 18px;
}
.dental-exam-section {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
}
.dental-exam-section h4 {
    font-size: 0.85rem;
    font-weight: 700;
    color: #334155;
    margin: 0 0 12px 0;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.dental-exam-section select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.9rem;
    background: white;
    color: #1f2937;
    outline: none;
}
.dental-exam-section select:focus {
    border-color: #0ea5e9;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
}
.dental-check-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.dental-check-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.88rem;
    color: #374151;
}
.dental-check-item input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #0891b2;
    cursor: pointer;
}
.dental-check-item label {
    cursor: pointer;
    margin: 0;
}
.others-input {
    display: none;
    margin-top: 6px;
}
.others-input.visible {
    display: block;
}
.others-input input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.85rem;
    outline: none;
}
.others-input input:focus {
    border-color: #0ea5e9;
}

/* Vitals & Body Measurement */
.dental-vitals-section {
    background: linear-gradient(135deg, #f0f9ff, #f8fafc);
    border: 1px solid #bae6fd;
    border-radius: 12px;
    padding: 16px;
}
.dental-vitals-section h4 {
    font-size: 0.85rem;
    font-weight: 700;
    color: #0369a1;
    margin: 0 0 12px 0;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.dental-vitals-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.dental-vitals-item {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 12px;
}
.dental-vitals-item .dv-label {
    font-size: 0.7rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 4px;
}
.dental-vitals-item .dv-value {
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
}
.dental-vitals-empty {
    font-size: 0.82rem;
    color: #94a3b8;
    font-style: italic;
}

/* Tooth Chart */
.tooth-chart-area {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
}
.tooth-chart-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: #334155;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 12px;
}
.odontogram-legend {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 16px;
}
.odontogram-legend h5 {
    font-size: 0.78rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 8px 0;
    text-transform: uppercase;
}
.legend-section { margin-bottom: 6px; }
.legend-section-title {
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    margin-bottom: 3px;
}
.legend-items {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 12px;
}
.legend-item {
    font-size: 0.75rem;
    color: #475569;
    display: flex;
    align-items: center;
    gap: 3px;
}
.legend-color {
    width: 10px;
    height: 10px;
    border-radius: 2px;
    flex-shrink: 0;
}
.legend-color.decayed { background: #ef4444; }
.legend-color.treatment { background: #7dd3fc; }
.legend-color.extraction { background: #f59e0b; }
.legend-color.missing { background: #94a3b8; }
.legend-color.impacted { background: #a78bfa; }
.legend-color.other-cond { background: #6ee7b7; }

.chart-section-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    text-align: center;
    margin: 10px 0 6px;
}
.quadrant-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2px;
}
.quadrant-block {
    display: flex;
    flex-direction: column;
    align-items: center;
}
.quadrant-label {
    font-size: 0.65rem;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 4px;
}
.tooth-row {
    display: flex;
    gap: 2px;
    align-items: flex-start;
}
.tooth-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 48px;
}
.tooth-num-label {
    font-size: 0.6rem;
    font-weight: 700;
    color: #64748b;
    margin-bottom: 2px;
    text-align: center;
}
.tooth-svg-wrap {
    cursor: pointer;
    transition: transform 0.15s;
    position: relative;
}
.tooth-svg-wrap:hover {
    transform: scale(1.1);
    filter: drop-shadow(0 2px 4px rgba(14,165,233,0.25));
}
.tooth-svg-wrap svg {
    display: block;
}
.tooth-findings-below {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 1px;
    margin-top: 1px;
    min-height: 12px;
}
.tooth-finding-badge {
    font-size: 0.5rem;
    font-weight: 700;
    padding: 0 3px;
    border-radius: 2px;
    line-height: 1.3;
}
.tooth-finding-badge.pathology {
    background: #fecaca;
    color: #991b1b;
}
.tooth-finding-badge.treatment {
    background: #bae6fd;
    color: #0369a1;
}
.tooth-finding-badge.condition {
    background: #e2e8f0;
    color: #475569;
}
.tooth-finding-badge.surgery {
    background: #fef3c7;
    color: #92400e;
}

/* Tooth findings summary display */
.tooth-findings-display {
    margin-top: 14px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 14px;
    max-height: 150px;
    overflow-y: auto;
}
.tooth-findings-display h5 {
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    margin: 0 0 8px 0;
}
.finding-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 3px 8px;
    font-size: 0.72rem;
    font-weight: 600;
    color: #475569;
    margin: 2px;
}
.finding-chip .remove-finding {
    background: none;
    border: none;
    color: #ef4444;
    cursor: pointer;
    font-size: 0.8rem;
    padding: 0 2px;
    font-weight: 700;
}

/* Tooth Mini Modal */
.tooth-mini-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0,0,0,0.45);
    z-index: 999999 !important;
    align-items: center;
    justify-content: center;
    margin: 0;
    padding: 20px;
}
.tooth-mini-modal-overlay.active {
    display: flex;
}
.tooth-mini-modal {
    background: white;
    border-radius: 16px;
    padding: 20px;
    width: 280px;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 20px 50px rgba(0,0,0,0.25);
    animation: miniModalPop 0.2s ease;
}
@keyframes miniModalPop {
    from { transform: scale(0.9); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
.tooth-mini-modal h4 {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 12px 0;
    text-align: center;
}
.tooth-mini-modal .modal-choice-btn {
    width: 100%;
    padding: 12px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    background: white;
    font-size: 0.9rem;
    font-weight: 600;
    color: #334155;
    cursor: pointer;
    transition: all 0.15s;
    margin-bottom: 8px;
    text-align: left;
}
.tooth-mini-modal .modal-choice-btn:hover {
    border-color: #0ea5e9;
    color: #0284c7;
    background: #f0f9ff;
}
.tooth-mini-modal .modal-close-btn {
    width: 100%;
    padding: 8px;
    border: none;
    border-radius: 8px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    margin-top: 4px;
}

.surface-btn {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: white;
    font-size: 0.82rem;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
    transition: all 0.15s;
    margin-bottom: 4px;
    text-align: left;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.surface-btn:hover {
    background: #f0f9ff;
    border-color: #0ea5e9;
}
.surface-btn .surface-status {
    font-size: 0.7rem;
    font-weight: 600;
    text-align: right;
    white-space: nowrap;
    margin-left: 8px;
}
.surface-btn .surface-status.not-marked {
    color: #94a3b8;
    font-style: italic;
}
.surface-btn .surface-status.pathology {
    color: #ef4444;
}
.surface-btn .surface-status.treatment {
    color: #0ea5e9;
}
.condition-btn, .pathology-btn, .treat-btn {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: white;
    font-size: 0.82rem;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
    transition: all 0.15s;
    margin-bottom: 4px;
    text-align: left;
}
.condition-btn:hover, .pathology-btn:hover, .treat-btn:hover {
    background: #f0f9ff;
    border-color: #0ea5e9;
}
.finding-section-title {
    font-size: 0.72rem;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    margin: 10px 0 4px;
}

/* Notes */
.notes-textarea {
    width: 100%;
    min-height: 300px;
    border: 1px solid #d1d5db;
    border-radius: 10px;
    padding: 16px;
    font-size: 0.95rem;
    font-family: inherit;
    line-height: 1.6;
    resize: vertical;
    outline: none;
}
.notes-textarea:focus {
    border-color: #0ea5e9;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}
.notes-save-status {
    font-size: 0.8rem;
    color: #16a34a;
    font-weight: 600;
    margin-top: 8px;
}

/* Diagnosis */
.dx-info-banner {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 20px;
    font-size: 0.85rem;
    color: #1e40af;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.dx-label {
    display: block;
    font-size: 0.85rem;
    font-weight: 700;
    color: #334155;
    margin: 0 0 6px 0;
}
.dx-field-group {
    margin-bottom: 18px;
}
.dx-code-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.dx-combobox {
    position: relative;
    flex: 1;
}
.dx-input {
    width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 0.9rem;
    font-family: inherit;
    outline: none;
    background: white;
}
.dx-input:focus {
    border-color: #0ea5e9;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}
.dx-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(15, 23, 42, 0.12);
    max-height: 260px;
    overflow-y: auto;
    z-index: 60;
    display: none;
}
.dx-dropdown.open {
    display: block;
}
.dx-dropdown-item {
    padding: 9px 14px;
    font-size: 0.85rem;
    color: #334155;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
}
.dx-dropdown-item:last-child {
    border-bottom: none;
}
.dx-dropdown-item:hover,
.dx-dropdown-item.highlighted {
    background: #eff6ff;
}
.dx-dropdown-item .dx-dd-code {
    font-weight: 700;
    color: #0369a1;
    margin-right: 8px;
}
.dx-dropdown-empty {
    padding: 10px 14px;
    font-size: 0.82rem;
    color: #94a3b8;
}
.dx-clear-btn {
    background: white;
    border: 1px solid #e2e8f0;
    color: #64748b;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 9px 16px;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.dx-clear-btn:hover {
    background: #fef2f2;
    border-color: #fecaca;
    color: #dc2626;
}
.dx-selected-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #ecfdf5;
    border: 1px solid #bbf7d0;
    color: #047857;
    font-size: 0.82rem;
    font-weight: 600;
    padding: 5px 12px;
    border-radius: 999px;
    margin-top: 8px;
}
.dx-selected-chip .dx-chip-x {
    cursor: pointer;
    font-weight: 700;
    color: #059669;
}
.dx-selected-chip .dx-chip-x:hover {
    color: #dc2626;
}

/* X-Ray */
.xray-upload-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: #0ea5e9;
    color: white;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 9px 18px;
    border-radius: 10px;
    cursor: pointer;
    transition: background 0.15s ease;
}
.xray-upload-btn:hover {
    background: #0284c7;
}
.xray-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 14px;
}
.xray-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    position: relative;
}
.xray-thumb {
    width: 100%;
    height: 120px;
    object-fit: cover;
    display: block;
    background: #f1f5f9;
    cursor: pointer;
}
.xray-card-info {
    padding: 8px 10px;
}
.xray-card-name {
    font-size: 0.72rem;
    font-weight: 600;
    color: #334155;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.xray-card-date {
    font-size: 0.65rem;
    color: #94a3b8;
    margin-top: 2px;
}
.xray-delete-btn {
    position: absolute;
    top: 6px;
    right: 6px;
    width: 24px;
    height: 24px;
    border: none;
    border-radius: 6px;
    background: rgba(15, 23, 42, 0.65);
    color: white;
    font-size: 0.85rem;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s ease;
}
.xray-delete-btn:hover {
    background: #dc2626;
}

/* Medical History */
.history-section {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
}
.history-section h4 {
    font-size: 0.9rem;
    font-weight: 700;
    color: #334155;
    margin: 0 0 12px 0;
}
.history-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
}
.history-item {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 12px;
}
.history-item .h-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: #94a3b8;
    text-transform: uppercase;
}
.history-item .h-value {
    font-size: 0.9rem;
    color: #1f2937;
    font-weight: 500;
}
.history-item .h-value.danger {
    color: #dc2626;
    font-weight: 600;
}

/* Medical History Checkboxes */
.mh-checklist {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 8px;
}
.mh-check-item {
    display: flex;
    align-items: center;
    gap: 10px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 14px;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    user-select: none;
}
.mh-check-item:hover {
    border-color: #93c5fd;
    background: #f0f7ff;
}
.mh-check-item.checked {
    border-color: #3b82f6;
    background: #eff6ff;
}
.mh-check-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #3b82f6;
    cursor: pointer;
    flex-shrink: 0;
}
.mh-check-item .mh-label {
    font-size: 0.85rem;
    font-weight: 500;
    color: #334155;
}
.mh-text-group {
    margin-top: 16px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
}
.mh-text-item label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    margin-bottom: 4px;
}
.mh-text-item input[type="text"] {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.85rem;
    outline: none;
    transition: border-color 0.15s;
}
.mh-text-item input[type="text"]:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}
.mh-save-status {
    font-size: 0.75rem;
    color: #94a3b8;
    margin-top: 8px;
    min-height: 18px;
}

/* Prescription */
.prescription-item-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 12px;
    position: relative;
}
.prescription-item-card .remove-presc-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    background: none;
    border: none;
    color: #ef4444;
    cursor: pointer;
    font-size: 1.2rem;
}
.presc-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.presc-grid .full-width {
    grid-column: 1 / -1;
}
.add-presc-btn {
    background: white;
    border: 2px dashed #d1d5db;
    border-radius: 10px;
    padding: 14px;
    width: 100%;
    font-size: 0.9rem;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s;
}
.add-presc-btn:hover {
    border-color: #0ea5e9;
    color: #0284c7;
    background: #f0f9ff;
}

/* Consent */
.consent-form-container {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 28px;
    max-width: 700px;
}
.consent-form-container h4 {
    text-align: center;
    font-size: 1.1rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 4px;
}
.consent-clinic-name {
    text-align: center;
    font-size: 0.85rem;
    color: #64748b;
    margin-bottom: 20px;
}
.consent-field-row {
    display: grid;
    grid-template-columns: 140px 1fr;
    gap: 8px;
    margin-bottom: 12px;
    align-items: center;
}
.consent-field-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #374151;
}
.consent-field-value {
    font-size: 0.9rem;
    color: #1e293b;
    border-bottom: 1px solid #d1d5db;
    padding: 4px 0;
}
.consent-text-block {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px;
    margin: 16px 0;
    font-size: 0.82rem;
    color: #475569;
    line-height: 1.7;
}
.consent-signature-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-top: 24px;
}
.sig-block {
    text-align: center;
}
.sig-line {
    border-bottom: 2px solid #1f2937;
    min-height: 50px;
    margin-bottom: 6px;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    font-size: 0.8rem;
    color: #64748b;
    padding-bottom: 4px;
    cursor: pointer;
    position: relative;
}
.sig-line.signed {
    color: #0891b2;
    font-weight: 700;
    font-size: 1rem;
}
.sig-preview {
    min-height: 80px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    padding: 8px;
    margin-bottom: 8px;
}
.sig-preview img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    border-radius: 8px;
}
.sig-preview.empty {
    color: #94a3b8;
    font-size: 0.82rem;
    text-align: center;
}
.sig-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
}
.sig-name-input {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.8rem;
    text-align: center;
    outline: none;
    margin-top: 4px;
}
.sig-name-input:focus {
    border-color: #0ea5e9;
}
    .signature-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.7);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    }
    .signature-modal-overlay.active {
        display: flex;
    }
    .signature-modal {
        background: white;
        border-radius: 16px;
        width: min(720px, calc(100% - 40px));
        max-width: 720px;
        padding: 24px;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.2);
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .signature-modal h3 {
        margin: 0;
        font-size: 1.1rem;
        color: #0f172a;
    }
    .signature-canvas-wrapper {
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        overflow: hidden;
        background: #f8fafc;
    }
    .signature-canvas {
        width: 100%;
        height: 280px;
        touch-action: none;
        background: white;
    }
    .signature-modal-footer {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        flex-wrap: wrap;
    }
    .signature-modal-footer button {
        border: none;
        padding: 10px 18px;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 700;
    }
    .signature-clear-btn {
        background: #f8fafc;
        color: #0f172a;
        border: 1px solid #cbd5e1;
    }
    .signature-save-btn {
        background: #0ea5e9;
        color: white;
    }
    .signature-cancel-btn {
        background: #e2e8f0;
        color: #1e293b;
    }

    @media print {
        body * { visibility: hidden; }
        .consult-tab-panel { display: none !important; }
        body.print-panel-consent #panel-consent { display: block !important; }
        body.print-panel-prescription #panel-prescription { display: block !important; }
        body.print-panel-consent .consent-form-container, body.print-panel-consent .consent-form-container * { visibility: visible; }
        body.print-panel-prescription #panel-prescription, body.print-panel-prescription #panel-prescription * { visibility: visible; }
        .consent-form-container { border: none !important; box-shadow: none !important; padding: 0 !important; }
        .consult-complete-btn, .signature-save-btn, .signature-clear-btn, .signature-cancel-btn, .signature-modal-overlay, .signature-modal, .sig-line, .dental-check-item, #guardianNameWrapper, .add-presc-btn, .remove-presc-btn { display: none !important; }
        .consent-text-block { border: none !important; background: transparent !important; }
        .sig-preview { background: transparent !important; border: none !important; padding: 0 !important; min-height: auto !important; }
        .sig-preview img { max-height: 140px; object-fit: contain; }
        .sig-name-input, .form-control { border: none !important; background: transparent !important; box-shadow: none !important; outline: none !important; }
        .sig-name-input::-moz-focus-inner { border: 0; }
        .consent-field-row { grid-template-columns: 1fr !important; }
        .consent-field-label { display: block; margin-bottom: 4px; }
        .consent-clinic-name { margin-bottom: 24px; }
        .prescription-item-card { border: none !important; background: transparent !important; padding: 0 !important; }
        .prescription-item-card .presc-grid { grid-template-columns: 1fr !important; gap: 8px !important; }
        .prescription-item-card label { display: block !important; font-weight: 700 !important; color: #111827 !important; margin-bottom: 2px !important; }
        .prescription-item-card input { padding: 0 !important; font-size: 0.95rem !important; color: #111827 !important; }
        .prescription-item-card .remove-presc-btn { display: none !important; }
    }
}
.past-consult-toggle .toggle-arrow {
    transition: transform 0.2s;
    color: #166534;
}
.past-consult-toggle.open .toggle-arrow {
    transform: rotate(180deg);
}
.past-consult-list {
    display: none;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 20px;
}
.past-consult-list.open {
    display: flex;
}
.past-consult-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}
.past-consult-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    cursor: pointer;
    transition: background 0.15s;
}
.past-consult-card-header:hover {
    background: #f8fafc;
}
.past-consult-card-header .card-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.past-consult-card-header .card-date {
    font-size: 0.85rem;
    font-weight: 700;
    color: #1e293b;
}
.past-consult-card-header .card-treatment {
    font-size: 0.8rem;
    color: #0ea5e9;
    font-weight: 600;
    background: #f0f9ff;
    padding: 3px 10px;
    border-radius: 6px;
}
.past-consult-card-header .card-dentist {
    font-size: 0.78rem;
    color: #64748b;
}
.past-consult-card-header .card-arrow {
    color: #94a3b8;
    transition: transform 0.2s;
    font-size: 0.85rem;
}
.past-consult-card.expanded .card-arrow {
    transform: rotate(180deg);
}
.past-consult-card-body {
    display: none;
    padding: 14px 16px;
    border-top: 1px solid #f1f5f9;
    background: #fafbfc;
}
.past-consult-card.expanded .past-consult-card-body {
    display: block;
}
.past-consult-detail-group {
    margin-bottom: 12px;
}
.past-consult-detail-group:last-child {
    margin-bottom: 0;
}
.past-consult-detail-group .detail-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 6px;
}
.past-consult-detail-group .detail-value {
    font-size: 0.85rem;
    color: #334155;
    line-height: 1.5;
}
.past-consult-detail-group .detail-value.empty {
    color: #94a3b8;
    font-style: italic;
}
.past-teeth-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}
.past-teeth-chip {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 5px;
    padding: 2px 8px;
    font-size: 0.72rem;
    font-weight: 600;
    color: #475569;
}
.past-teeth-chip.pathology { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
.past-teeth-chip.treatment { background: #f0f9ff; border-color: #bae6fd; color: #0369a1; }
.past-teeth-chip.condition { background: #f1f5f9; border-color: #e2e8f0; color: #475569; }
.past-teeth-chip.surgery { background: #fffbeb; border-color: #fde68a; color: #92400e; }
.past-prescription-item {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 8px 12px;
    margin-bottom: 6px;
    font-size: 0.82rem;
}
.past-prescription-item:last-child { margin-bottom: 0; }
.past-prescription-item .med-name { font-weight: 700; color: #1e293b; }
.past-prescription-item .med-detail { color: #64748b; margin-top: 2px; }
.past-consult-view-full {
    display: flex;
    justify-content: flex-end;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #e2e8f0;
}
.past-consult-view-full a {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    background: linear-gradient(135deg, #059669, #10b981);
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
    border-radius: 6px;
    text-decoration: none;
    transition: all 0.2s;
}
.past-consult-view-full a:hover {
    background: linear-gradient(135deg, #047857, #059669);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

/* Past View Mode */
.past-view-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.35);
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: white;
}
.past-view-badge svg { opacity: 0.9; }
.past-view-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}
.past-view-back-btn:hover {
    background: rgba(255,255,255,0.25);
}
.consult-queue-chip.past {
    background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
    border: 2px solid #86efac;
    color: #166534;
    cursor: default;
    position: relative;
}
.consult-queue-chip.past:hover {
    border-color: #86efac;
    color: #166534;
    transform: none;
    filter: none;
}
.consult-queue-chip.past .chip-num {
    background: rgba(22, 101, 52, 0.1);
}
.past-chip-close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: rgba(22, 101, 52, 0.15);
    color: #166534;
    border: none;
    cursor: pointer;
    font-size: 0.7rem;
    font-weight: 700;
    margin-left: 6px;
    padding: 0;
    line-height: 1;
    transition: all 0.15s;
    flex-shrink: 0;
}
.past-chip-close:hover {
    background: #dc2626;
    color: white;
}
.past-chip-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #16a34a;
    background: rgba(22, 101, 52, 0.08);
    padding: 2px 6px;
    border-radius: 3px;
    margin-right: 4px;
}
.read-only-overlay-msg {
    display: none;
    text-align: center;
    padding: 10px;
    background: #fefce8;
    border: 1px solid #fde68a;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    color: #92400e;
    margin-bottom: 12px;
}
.is-past-view .read-only-overlay-msg { display: block; }
.is-past-view select { pointer-events: none; }
.is-past-view input:not([type="hidden"]) { pointer-events: none; }
.is-past-view textarea { pointer-events: none; }
.is-past-view .tooth-svg-wrap { pointer-events: none !important; cursor: default !important; }
.is-past-view .tooth-svg-wrap:hover { transform: none; filter: none; }
.is-past-view .add-presc-btn { display: none; }
.is-past-view .consult-complete-btn { display: none !important; }

/* Treatment Plan Tab Styles */
#panel-treatment-plan .sess-btn {
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
#panel-treatment-plan .sess-btn:hover {
    border-color: #3b82f6;
    color: #3b82f6;
    background: #eff6ff;
}
#panel-treatment-plan .sess-btn.active {
    background: #3b82f6;
    border-color: #3b82f6;
    color: #ffffff;
    box-shadow: 0 4px 8px rgba(59,130,246,0.3);
}
#panel-treatment-plan .session-row {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px;
    margin-bottom: 12px;
}
#panel-treatment-plan .session-row-header {
    font-size: 0.85rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 10px;
    letter-spacing: 0.02em;
}
#panel-treatment-plan .session-row-content {
    display: flex;
    gap: 16px;
    align-items: flex-start;
}
#panel-treatment-plan .session-left {
    flex: 1;
    min-width: 0;
}
#panel-treatment-plan .session-left-inner {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
#panel-treatment-plan .session-right {
    flex: 0 0 280px;
    min-width: 250px;
}
#panel-treatment-plan .session-right-inner {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
#panel-treatment-plan .session-right-inner .session-date {
    width: 100%;
}
#panel-treatment-plan .session-right-inner .session-date:disabled {
    background: #f3f4f6;
    color: #9ca3af;
    cursor: not-allowed;
    opacity: 0.7;
}
#panel-treatment-plan .session-selected-time {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 8px 12px;
}
#panel-treatment-plan .session-time-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: #1e40af;
}
#panel-treatment-plan .session-change-time {
    font-size: 0.75rem;
    color: #3b82f6;
    background: none;
    border: none;
    cursor: pointer;
    text-decoration: underline;
    padding: 2px 4px;
}
#panel-treatment-plan .session-change-time:hover {
    color: #1d4ed8;
}
#panel-treatment-plan .session-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
#panel-treatment-plan .session-field-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
#panel-treatment-plan .session-treatment {
    width: 100%;
}
#panel-treatment-plan .session-other-treatment {
    margin-top: 6px;
}
#panel-treatment-plan .session-notes {
    width: 100%;
    resize: vertical;
    min-height: 50px;
}
#panel-treatment-plan .session-time-slots {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(75px, 1fr));
    gap: 4px;
}
#panel-treatment-plan .time-slot-btn {
    padding: 8px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #f3f4f6;
    color: #374151;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s;
}
#panel-treatment-plan .time-slot-btn:hover {
    border-color: #3b82f6;
    background: #eff6ff;
}
#panel-treatment-plan .time-slot-btn.booked {
    background: #fef2f2;
    border-color: #fecaca;
    color: #dc2626;
    cursor: not-allowed;
    opacity: 0.7;
    text-decoration: line-through;
}
#panel-treatment-plan .time-slot-btn.booked:hover {
    background: #fef2f2;
    border-color: #fecaca;
}
#panel-treatment-plan .tp-plan-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 12px;
}
#panel-treatment-plan .tp-plan-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}
#panel-treatment-plan .tp-plan-name {
    font-weight: 600;
    color: #1e293b;
    font-size: 0.9rem;
}
#panel-treatment-plan .tp-plan-status {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 2px 10px;
    border-radius: 12px;
    text-transform: uppercase;
}
#panel-treatment-plan .tp-plan-status.active { background: #dcfce7; color: #166534; }
#panel-treatment-plan .tp-plan-status.completed { background: #dbeafe; color: #1e40af; }
#panel-treatment-plan .tp-plan-status.on-hold { background: #fef3c7; color: #92400e; }
#panel-treatment-plan .tp-plan-status.cancelled { background: #fee2e2; color: #991b1b; }
#panel-treatment-plan .tp-plan-status.in-progress { background: #e0e7ff; color: #3730a3; }
#panel-treatment-plan .tp-plan-meta {
    font-size: 0.8rem;
    color: #6b7280;
    margin-bottom: 4px;
}
#panel-treatment-plan .tp-plan-sessions {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #e5e7eb;
}
#panel-treatment-plan .tp-plan-session-item {
    font-size: 0.8rem;
    margin-bottom: 3px;
}
#panel-treatment-plan .tp-plan-session-item span { font-weight: 500; }
#panel-treatment-plan .tp-plan-treatment-label { color: #059669; }
#panel-treatment-plan .tp-plan-notes { font-size: 0.78rem; color: #6b7280; margin-top: 4px; }

/* Complete Consultation Modal */
.cc-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9999999;
    align-items: center;
    justify-content: center;
}
.cc-overlay.active {
    display: flex;
}
.cc-modal {
    position: relative;
    z-index: 100000;
    background:
        radial-gradient(circle at top right, rgba(14, 165, 233, 0.18), transparent 32%),
        radial-gradient(circle at bottom left, rgba(34, 197, 94, 0.14), transparent 28%),
        linear-gradient(180deg, #ffffff 0%, #f7fcff 100%);
    border-radius: 22px;
    padding: 0;
    width: 90%;
    max-width: 560px;
    overflow: hidden;
    box-shadow: 0 30px 80px rgba(15, 23, 42, 0.24);
    border: 1px solid rgba(14, 165, 233, 0.12);
}
.cc-bg-orb {
    position: absolute;
    border-radius: 999px;
    pointer-events: none;
    filter: blur(8px);
    opacity: 0.55;
}
.cc-bg-orb-a {
    width: 140px; height: 140px;
    right: -30px; top: 88px;
    background: radial-gradient(circle, rgba(14, 165, 233, 0.2) 0%, rgba(14, 165, 233, 0) 72%);
}
.cc-bg-orb-b {
    width: 160px; height: 160px;
    left: -40px; bottom: -30px;
    background: radial-gradient(circle, rgba(34, 197, 94, 0.16) 0%, rgba(34, 197, 94, 0) 74%);
}
.cc-banner {
    display: flex; align-items: center; gap: 16px;
    padding: 24px 26px 18px;
    background: linear-gradient(135deg, rgba(36, 68, 213), rgba(255, 255, 255, 0) 42%),
                linear-gradient(135deg, #2444d5 0%, #412fa3be 48%, #0891b2 90%);
    color: white;
    position: relative;
}
.cc-icon-wrap {
    width: 54px; height: 54px; border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.22);
    backdrop-filter: blur(6px);
    flex-shrink: 0;
}
.cc-icon {
    display: flex; align-items: center; justify-content: center;
}
.cc-icon svg {
    width: 26px; height: 26px; fill: currentColor;
}
.cc-kicker {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    opacity: 0.9;
    font-weight: 700;
}
.cc-close {
    margin-left: auto;
    background: rgba(255, 255, 255, 0.12);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.18);
    width: 40px; height: 40px;
    border-radius: 12px;
    font-size: 1.5rem;
    cursor: pointer;
    backdrop-filter: blur(8px);
    line-height: 1;
}
.cc-body {
    padding: 24px 26px 12px;
    position: relative;
}
.cc-text {
    color: #1f2937;
    font-size: 1.06rem;
    line-height: 1.6;
    margin: 0 0 16px;
    font-weight: 500;
}
.cc-highlight {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 16px; border-radius: 16px;
    background: linear-gradient(135deg, #ecfeff 0%, #f0fdf4 100%);
    border: 1px solid #d9f99d;
    margin-bottom: 14px;
}
.cc-highlight-icon {
    width: 42px; height: 42px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    background: white;
    box-shadow: 0 10px 24px rgba(8, 145, 178, 0.12);
    font-size: 1.25rem;
    flex-shrink: 0;
}
.cc-highlight-label {
    color: #0f766e;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 700;
    margin-bottom: 3px;
}
.cc-highlight-value {
    color: #1f2937;
    font-size: 0.95rem;
    font-weight: 600;
}
.cc-note {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 14px; border-radius: 14px;
    background: #f0fdf4;
    color: #166534;
    font-size: 0.92rem;
    border: 1px solid #dcfce7;
}
.cc-note-dot {
    width: 10px; height: 10px; border-radius: 999px;
    background: #22c55e;
    box-shadow: 0 0 0 5px rgba(34, 197, 94, 0.14);
    flex-shrink: 0;
}
.cc-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 18px 26px 26px;
}
.cc-btn-cancel, .cc-btn-confirm {
    border: none;
    border-radius: 14px;
    padding: 13px 20px;
    cursor: pointer;
    font-weight: 700;
    font-size: 0.97rem;
    transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
}
.cc-btn-cancel {
    background: #eef6fb;
    color: #315266;
    border: 1px solid #d8e9f4;
}
.cc-btn-confirm {
    background: linear-gradient(135deg, #2444d5 0%, #412fa3be 48%, #0891b2 90%);
    color: white;
    box-shadow: 0 14px 28px rgba(8, 145, 178, 0.24);
}
.cc-btn-cancel:hover, .cc-btn-confirm:hover, .cc-close:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
}
@media (max-width: 640px) {
    .cc-banner, .cc-body, .cc-actions {
        padding-left: 18px;
        padding-right: 18px;
    }
    .cc-actions {
        flex-direction: column-reverse;
    }
    .cc-btn-cancel, .cc-btn-confirm {
        width: 100%;
    }
}
</style>

<div class="consult-container <?php echo $isPastView ? 'is-past-view' : ''; ?>">
    <!-- Header -->
    <div class="consult-header">
        <div class="consult-header-left">
            <h2><?php echo $isPastView ? 'Past Consultation' : 'Consultation in Progress'; ?></h2>
            <div class="consult-patient-info">
                <?php if ($isPastView && $pastConsultData): ?>
                    <span class="past-view-badge">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
                        READ ONLY
                    </span>
                    &nbsp;&nbsp;
                <?php endif; ?>
                Patient: <span id="consultPatientName"><?php echo $isPastView ? htmlspecialchars($pastConsultData['patient_name'] ?? 'Unknown') : 'Select a patient from the queue below'; ?></span>
                &nbsp;&nbsp;|&nbsp;&nbsp;
                Queue No: <span id="consultQueueNo"><?php echo $isPastView ? '#' . htmlspecialchars($pastConsultData['queue_number'] ?? '-') : '-'; ?></span>
                &nbsp;&nbsp;|&nbsp;&nbsp;
                Treatment: <span id="consultTreatment"><?php echo $isPastView ? htmlspecialchars($pastConsultData['treatment_type'] ?? '-') : '-'; ?></span>
            </div>
        </div>
        <?php if ($isPastView): ?>
            <a href="dentist_patients.php" class="past-view-back-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                Back to Patient Records
            </a>
        <?php else: ?>
            <button class="consult-complete-btn" onclick="completeConsultation()" id="consultCompleteBtn" style="display:none;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                Complete Consultation
            </button>
        <?php endif; ?>
    </div>

    <!-- Queue Bar -->
    <div class="consult-queue-bar" id="consultQueueBar">
        <span class="consult-queue-label"><?php echo $isPastView ? 'Viewing:' : 'Consulting Queue:'; ?></span>
        <?php if ($isPastView && $pastConsultData): ?>
            <div class="consult-queue-chip past">
                <span class="past-chip-label">Past</span>
                <span class="chip-num">#<?php echo htmlspecialchars($pastConsultData['queue_number'] ?? '-'); ?></span>
                <?php echo htmlspecialchars($pastConsultData['patient_name'] ?? 'Unknown'); ?>
                <button class="past-chip-close" onclick="window.location.href='dentist_patients.php'" title="Close">&times;</button>
            </div>
        <?php else: ?>
            <?php foreach ($queuePatients as $pt): ?>
                <div class="consult-queue-chip <?php echo ($initialQueueId == $pt['queue_id']) ? 'active' : ''; ?>"
                     data-queue-id="<?php echo $pt['queue_id']; ?>"
                     data-patient-name="<?php echo htmlspecialchars($pt['full_name']); ?>"
                     data-queue-number="<?php echo $pt['queue_number']; ?>"
                     data-treatment="<?php echo htmlspecialchars($pt['treatment_type'] ?? ''); ?>"
                     onclick="selectConsultPatient(this)">
                    <span class="chip-num">#<?php echo $pt['queue_number'] ?? $pt['queue_id']; ?></span>
                    <?php echo htmlspecialchars($pt['full_name']); ?>
                </div>
            <?php endforeach; ?>
            <?php if (empty($queuePatients)): ?>
                <span style="font-size:0.82rem;color:#94a3b8;">No patients in queue</span>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Sub-tabs -->
    <div class="consult-tabs-bar">
        <button class="consult-tab-btn" data-tab="consent" onclick="switchConsultTab(this, 'consent')">Informed Consent</button>
        <button class="consult-tab-btn active" data-tab="dental-exam" onclick="switchConsultTab(this, 'dental-exam')">Dental Examination</button>
        <button class="consult-tab-btn" data-tab="xray" onclick="switchConsultTab(this, 'xray')">X-Ray</button>
        <button class="consult-tab-btn" data-tab="notes" onclick="switchConsultTab(this, 'notes')">Notes</button>
        <button class="consult-tab-btn" data-tab="history" onclick="switchConsultTab(this, 'history')">Dental & Medical History</button>
        <button class="consult-tab-btn" data-tab="diagnosis" onclick="switchConsultTab(this, 'diagnosis')">Diagnosis</button>
        <button class="consult-tab-btn" data-tab="treatment-plan" onclick="switchConsultTab(this, 'treatment-plan')">Treatment Plan</button>
        <button class="consult-tab-btn" data-tab="prescription" onclick="switchConsultTab(this, 'prescription')">Prescription</button>
        <button class="consult-tab-btn" data-tab="certificate" onclick="switchConsultTab(this, 'certificate')">Medical Certificate</button>
    </div>

    <!-- Tab Content -->
    <div class="consult-tab-content">
        <!-- Empty state -->
        <div class="consult-empty-state" id="consultEmptyState">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 3c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm7 13H5v-.23c0-.62.28-1.2.76-1.58C7.47 15.82 9.64 15 12 15s4.53.82 6.24 2.19c.48.38.76.97.76 1.58V19z"/></svg>
            <h3>No Patient Selected</h3>
            <p>Click a patient from the consulting queue above to begin consultation.</p>
        </div>

        <!-- Past Consultations (for returning patients) -->
        <div id="pastConsultationsSection" style="display:none;">
            <div class="past-consult-toggle" id="pastConsultToggle" onclick="togglePastConsultations()">
                <h4>Past Consultations <span class="toggle-count" id="pastConsultCount">0</span></h4>
                <span class="toggle-arrow">▼</span>
            </div>
            <div class="past-consult-list" id="pastConsultList"></div>
        </div>

        <!-- Panels -->
        <div id="consultPanels" style="display:none;">
            <!-- Dental Examination -->
            <div class="consult-tab-panel active" id="panel-dental-exam">
                <?php if ($isPastView): ?><div class="read-only-overlay-msg">This is a completed past consultation. All data is read-only.</div><?php endif; ?>
                <div class="dental-exam-grid">
                    <div class="dental-exam-form">
                        <!-- Vital Signs & Body Measurement (from staff queue) -->
                        <div class="dental-exam-section dental-vitals-section">
                            <h4>Vital Signs &amp; Body Measurement</h4>
                            <div class="dental-vitals-grid" id="dentalVitalsGrid">
                                <div class="dental-vitals-item"><div class="dv-label">Blood Pressure</div><div class="dv-value" id="dvBloodPressure">&mdash;</div></div>
                                <div class="dental-vitals-item"><div class="dv-label">Heart Rate</div><div class="dv-value" id="dvHeartRate">&mdash;</div></div>
                                <div class="dental-vitals-item"><div class="dv-label">Temperature</div><div class="dv-value" id="dvTemperature">&mdash;</div></div>
                                <div class="dental-vitals-item"><div class="dv-label">Oxygen Saturation</div><div class="dv-value" id="dvOxygenSaturation">&mdash;</div></div>
                                <div class="dental-vitals-item"><div class="dv-label">Height</div><div class="dv-value" id="dvHeight">&mdash;</div></div>
                                <div class="dental-vitals-item"><div class="dv-label">Weight</div><div class="dv-value" id="dvWeight">&mdash;</div></div>
                                <div class="dental-vitals-item"><div class="dv-label">BMI</div><div class="dv-value" id="dvBmi">&mdash;</div></div>
                                <div class="dental-vitals-empty" id="dentalVitalsEmpty" style="grid-column: 1 / -1; display: none;">No vital signs recorded.</div>
                            </div>
                        </div>
                        <!-- Periodontal -->
                        <div class="dental-exam-section">
                            <h4>Periodontal Screening</h4>
                            <select id="periodontalScreening" onchange="saveDentalExam()">
                                <option value="">-- Select --</option>
                                <option value="Gingivitis">Gingivitis</option>
                                <option value="Early Periodontitis">Early Periodontitis</option>
                                <option value="Moderate Periodontitis">Moderate Periodontitis</option>
                                <option value="Advanced Periodontitis">Advanced Periodontitis</option>
                            </select>
                        </div>

                        <!-- Occlusion -->
                        <div class="dental-exam-section">
                            <h4>Occlusion</h4>
                            <label for="occClassMolar">Molar Classification</label>
                            <select id="occClassMolar" onchange="saveDentalExam()">
                                <option value="">Not assessed</option>
                                <option value="Class I">Class I</option>
                                <option value="Class II">Class II</option>
                                <option value="Class III">Class III</option>
                                <option value="legacy_unspecified" disabled>Previously checked; class unspecified</option>
                            </select>
                            <p style="font-size:0.75rem;color:#64748b;margin:8px 0;">Check findings observed. Unchecked items do not confirm a normal bite.</p>
                            <div class="dental-check-group">
                                <div class="dental-check-item">
                                    <input type="checkbox" id="occOverjet" onchange="saveDentalExam()">
                                    <label for="occOverjet">Increased Overjet</label>
                                </div>
                                <div class="dental-check-item">
                                    <input type="checkbox" id="occOverbite" onchange="saveDentalExam()">
                                    <label for="occOverbite">Deep Overbite</label>
                                </div>
                                <div class="dental-check-item">
                                    <input type="checkbox" id="occMidline" onchange="saveDentalExam()">
                                    <label for="occMidline">Midline Deviation</label>
                                </div>
                                <div class="dental-check-item">
                                    <input type="checkbox" id="occCrossbite" onchange="saveDentalExam()">
                                    <label for="occCrossbite">Crossbite</label>
                                </div>
                            </div>
                            <label for="occNotes" style="display:block;margin-top:10px;">Occlusion Notes (optional)</label>
                            <textarea id="occNotes" class="notes-textarea" rows="3" style="min-height:75px;" placeholder="Measurements, affected teeth, or other bite findings" onchange="saveDentalExam()"></textarea>
                        </div>

                        <!-- Appliances -->
                        <div class="dental-exam-section">
                            <h4>Appliances</h4>
                            <div class="dental-check-group">
                                <div class="dental-check-item">
                                    <input type="checkbox" id="appOrthodontic" onchange="saveDentalExam()">
                                    <label for="appOrthodontic">Orthodontic</label>
                                </div>
                                <div class="dental-check-item">
                                    <input type="checkbox" id="appSkyplate" onchange="saveDentalExam()">
                                    <label for="appSkyplate">Skyplate</label>
                                </div>
                                <div class="dental-check-item">
                                    <input type="checkbox" id="appOthers" onchange="toggleOthersInput(); saveDentalExam();">
                                    <label for="appOthers">Others</label>
                                </div>
                                <div class="others-input" id="othersInputWrapper">
                                    <input type="text" id="appOthersText" placeholder="Specify..." onchange="saveDentalExam()">
                                </div>
                            </div>
                        </div>

                        <!-- TMD -->
                        <div class="dental-exam-section">
                            <h4>TMD</h4>
                            <div class="dental-check-group">
                                <div class="dental-check-item">
                                    <input type="checkbox" id="tmdClenching" onchange="saveDentalExam()">
                                    <label for="tmdClenching">Clenching</label>
                                </div>
                                <div class="dental-check-item">
                                    <input type="checkbox" id="tmdClicking" onchange="saveDentalExam()">
                                    <label for="tmdClicking">Clicking</label>
                                </div>
                                <div class="dental-check-item">
                                    <input type="checkbox" id="tmdTrismus" onchange="saveDentalExam()">
                                    <label for="tmdTrismus">Trismus</label>
                                </div>
                                <div class="dental-check-item">
                                    <input type="checkbox" id="tmdMuscleSpasm" onchange="saveDentalExam()">
                                    <label for="tmdMuscleSpasm">Muscle Spasm</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tooth Chart Area -->
                    <div class="tooth-chart-area">
                        <div class="tooth-chart-title">Odontogram</div>

                        <!-- Legend -->
                        <div class="odontogram-legend">
                            <h5>Surface Map: O (Center) | B (Top) | P (Bottom) | D (Left) | M (Right)</h5>
                            <div class="legend-section">
                                <div class="legend-section-title">Pathology</div>
                                <div class="legend-items">
                                    <div class="legend-item"><span class="legend-color decayed"></span> D - Decayed</div>
                                </div>
                            </div>
                            <div class="legend-section">
                                <div class="legend-section-title">Treatment</div>
                                <div class="legend-items">
                                    <div class="legend-item"><span class="legend-color treatment"></span> Am - Amalgam</div>
                                    <div class="legend-item"><span class="legend-color treatment"></span> Co - Composite</div>
                                    <div class="legend-item"><span class="legend-color treatment"></span> JC - Jacket Crown</div>
                                    <div class="legend-item"><span class="legend-color treatment"></span> Ab - Abutment</div>
                                    <div class="legend-item"><span class="legend-color treatment"></span> Att - Attachment</div>
                                    <div class="legend-item"><span class="legend-color treatment"></span> P - Pontic</div>
                                    <div class="legend-item"><span class="legend-color treatment"></span> In - Inlay</div>
                                    <div class="legend-item"><span class="legend-color treatment"></span> Imp - Implant</div>
                                    <div class="legend-item"><span class="legend-color treatment"></span> S - Sealants</div>
                                    <div class="legend-item"><span class="legend-color treatment"></span> Rm - Removable Denture</div>
                                </div>
                            </div>
                            <div class="legend-section">
                                <div class="legend-section-title">Condition / Surgery</div>
                                <div class="legend-items">
                                    <div class="legend-item"><span class="legend-color missing"></span> M - Missing (caries)</div>
                                    <div class="legend-item"><span class="legend-color missing"></span> MO - Missing (other)</div>
                                    <div class="legend-item"><span class="legend-color impacted"></span> Im - Impacted</div>
                                    <div class="legend-item"><span class="legend-color other-cond"></span> Sp - Supernumerary</div>
                                    <div class="legend-item"><span class="legend-color other-cond"></span> Rf - Root Fragment</div>
                                    <div class="legend-item"><span class="legend-color other-cond"></span> Un - Unerupted</div>
                                    <div class="legend-item"><span class="legend-color extraction"></span> X - Extraction (caries)</div>
                                    <div class="legend-item"><span class="legend-color extraction"></span> X - Extraction (other)</div>
                                </div>
                            </div>
                        </div>

                        <!-- Adult Teeth -->
                        <div class="chart-section-label">Adult Teeth</div>
                        <div id="adultTeethChart"></div>

                        <!-- Pediatric Teeth -->
                        <div class="chart-section-label" style="margin-top:16px;">Pediatric Teeth</div>
                        <div id="pedTeethChart"></div>

                        <!-- Findings Display -->
                        <div class="tooth-findings-display" id="toothFindingsDisplay">
                            <h5>Tooth Findings</h5>
                            <div id="findingsChips"><span style="color:#94a3b8;font-size:0.78rem;">No findings yet. Click a tooth surface to begin.</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- X-Ray -->
            <div class="consult-tab-panel" id="panel-xray">
                <?php if ($isPastView): ?><div class="read-only-overlay-msg">This is a completed past consultation. All data is read-only.</div><?php endif; ?>
                <div style="max-width:900px;">
                    <h3 style="font-size:1rem;font-weight:700;color:#1e293b;margin:0 0 6px;">X-Ray</h3>
                    <p style="font-size:0.85rem;color:#64748b;margin:0 0 14px;">Upload x-ray images for this consultation (JPG, PNG, GIF, WebP &mdash; max 10MB each).</p>
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                        <label class="xray-upload-btn" id="xrayUploadBtn">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Upload X-Ray
                            <input type="file" id="xrayFileInput" accept="image/jpeg,image/png,image/gif,image/webp" multiple style="display:none;" onchange="uploadXrays(this)">
                        </label>
                        <span class="notes-save-status" id="xraySaveStatus"></span>
                    </div>
                    <div class="xray-grid" id="xrayGrid"></div>
                </div>
            </div>

            <!-- Notes -->
            <div class="consult-tab-panel" id="panel-notes">
                <?php if ($isPastView): ?><div class="read-only-overlay-msg">This is a completed past consultation. All data is read-only.</div><?php endif; ?>
                <div style="max-width:700px;">
                    <h3 style="font-size:1rem;font-weight:700;color:#1e293b;margin:0 0 12px;">Consultation Notes</h3>
                    <textarea class="notes-textarea" id="consultNotes" placeholder="Enter consultation notes here..." oninput="debounceNotesSave()"></textarea>
                    <div class="notes-save-status" id="notesSaveStatus"></div>
                </div>
            </div>

            <!-- Dental & Medical History -->
            <div class="consult-tab-panel" id="panel-history">
                <?php if ($isPastView): ?><div class="read-only-overlay-msg">This is a completed past consultation. All data is read-only.</div><?php endif; ?>
                <div id="medicalHistoryDisplay">
                    <div class="history-section">
                        <h4>Existing Medical History</h4>
                        <div id="existingMedicalHistory"><p style="color:#94a3b8;">Loading...</p></div>
                    </div>
                </div>
                <div class="history-section" style="margin-top:16px;">
                    <h4>Dental History Notes</h4>
                    <textarea class="notes-textarea" id="dentalHistoryNotes" style="min-height:150px;" placeholder="Add dental history observations..." oninput="debounceHistorySave()"></textarea>
                    <div class="notes-save-status" id="historySaveStatus"></div>
                </div>
            </div>

            <!-- Diagnosis -->
            <div class="consult-tab-panel" id="panel-diagnosis">
                <?php if ($isPastView): ?><div class="read-only-overlay-msg">This is a completed past consultation. All data is read-only.</div><?php endif; ?>
                <div style="max-width:700px;">
                    <h3 style="font-size:1rem;font-weight:700;color:#1e293b;margin:0 0 12px;">Diagnosis</h3>
                    <div class="dx-info-banner">
                        <svg style="flex-shrink:0;margin-top:1px;" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1e40af" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span>Search and select ICD-10 codes (Philippines/WHO) for billing and reporting. Use the narrative below for free-text clinical wording when needed.</span>
                    </div>

                    <!-- Primary ICD-10 -->
                    <div class="dx-field-group">
                        <label class="dx-label">Primary ICD-10</label>
                        <div class="dx-code-row">
                            <div class="dx-combobox">
                                <input type="text" class="dx-input" id="dxPrimaryInput" placeholder="Search code or description (e.g. K02.9, hypertension)..." autocomplete="off" oninput="dxSearch('primary')" onkeydown="dxInputKeydown(event, 'primary')" onfocus="if(this.value.trim()) dxSearch('primary')">
                                <div class="dx-dropdown" id="dxPrimaryDropdown"></div>
                            </div>
                            <button type="button" class="dx-clear-btn" onclick="clearIcdCode('primary')">Clear</button>
                        </div>
                        <div id="dxPrimarySelected"></div>
                    </div>

                    <!-- Secondary ICD-10 -->
                    <div class="dx-field-group">
                        <label class="dx-label">Secondary ICD-10</label>
                        <div class="dx-code-row">
                            <div class="dx-combobox">
                                <input type="text" class="dx-input" id="dxSecondaryInput" placeholder="Search code or description (e.g. I10, asthma)..." autocomplete="off" oninput="dxSearch('secondary')" onkeydown="dxInputKeydown(event, 'secondary')" onfocus="if(this.value.trim()) dxSearch('secondary')">
                                <div class="dx-dropdown" id="dxSecondaryDropdown"></div>
                            </div>
                            <button type="button" class="dx-clear-btn" onclick="clearIcdCode('secondary')">Clear</button>
                        </div>
                        <div id="dxSecondarySelected"></div>
                    </div>

                    <!-- Narrative -->
                    <div class="dx-field-group">
                        <label class="dx-label">Diagnosis (Narrative)</label>
                        <textarea class="notes-textarea" id="diagnosisNarrative" style="min-height:150px;" placeholder="e.g. upper respiratory tract infection, hypertension, controlled" oninput="debounceDiagnosisSave()"></textarea>
                        <div class="notes-save-status" id="diagnosisSaveStatus"></div>
                    </div>
                </div>
            </div>

            <!-- Treatment Plan -->
            <div class="consult-tab-panel" id="panel-treatment-plan">
                <?php if ($isPastView): ?><div class="read-only-overlay-msg">This is a completed past consultation. All data is read-only.</div><?php endif; ?>

                <!-- Existing Plans -->
                <div id="tpExistingPlansSection" style="margin-bottom:24px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                        <h3 style="font-size:1rem;font-weight:700;color:#1e293b;margin:0;">Existing Treatment Plans</h3>
                        <span class="tp-plan-count" id="tpPlanCount" style="display:none;background:#e0f2fe;color:#0369a1;padding:2px 10px;border-radius:12px;font-size:0.75rem;font-weight:600;"></span>
                    </div>
                    <div id="tpExistingPlansContainer">
                        <p style="color:#94a3b8;font-size:0.875rem;">Loading...</p>
                    </div>
                </div>

                <!-- New Treatment Plan Form -->
                <div id="tpNewPlanSection">
                    <h3 style="font-size:1rem;font-weight:700;color:#1e293b;margin:0 0 16px;">New Treatment Plan</h3>
                    <form id="consultTreatmentPlanForm" onsubmit="saveConsultTreatmentPlan(event)">
                        <input type="hidden" name="plan_id" id="tp_plan_id" value="">
                        <input type="hidden" name="patient_id" id="tp_patient_id" value="">

                        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <span style="font-size:0.875rem;color:#15803d;font-weight:500;" id="tpPatientLabel">—</span>
                        </div>

                        <div class="form-group" style="margin-bottom:16px;">
                            <label style="font-weight:600;color:#374151;margin-bottom:6px;display:block;">Number of Sessions</label>
                            <div id="tpSessionCountBtns" style="display:flex;gap:6px;flex-wrap:wrap;">
                                <button type="button" class="sess-btn active" data-count="1" onclick="tpSetSessionCount(1)">1</button>
                                <button type="button" class="sess-btn" data-count="2" onclick="tpSetSessionCount(2)">2</button>
                                <button type="button" class="sess-btn" data-count="3" onclick="tpSetSessionCount(3)">3</button>
                                <button type="button" class="sess-btn" data-count="4" onclick="tpSetSessionCount(4)">4</button>
                                <button type="button" class="sess-btn" data-count="5" onclick="tpSetSessionCount(5)">5</button>
                                <button type="button" class="sess-btn" data-count="6" onclick="tpSetSessionCount(6)">6</button>
                                <button type="button" class="sess-btn" data-count="7" onclick="tpSetSessionCount(7)">7</button>
                                <button type="button" class="sess-btn" data-count="8" onclick="tpSetSessionCount(8)">8</button>
                            </div>
                            <input type="hidden" name="total_sessions" id="tp_total_sessions" value="1">
                        </div>

                        <div id="tpSessionRowsContainer"></div>

                        <div class="form-group" style="margin-bottom:16px;">
                            <label style="font-weight:600;color:#374151;margin-bottom:6px;display:block;">Teeth Numbers</label>
                            <input type="text" name="teeth_numbers" id="tp_teeth_numbers" class="form-control" placeholder="e.g., 11, 12, 13" style="width:100%;padding:10px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:0.875rem;">
                            <small style="color:#6b7280;">Enter tooth numbers separated by commas</small>
                        </div>

                        <div class="form-group" style="margin-bottom:16px;">
                            <label style="font-weight:600;color:#374151;margin-bottom:6px;display:block;">Notes</label>
                            <textarea name="notes" id="tp_notes" rows="3" class="form-control" placeholder="Additional treatment notes..." style="width:100%;padding:10px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:0.875rem;resize:vertical;"></textarea>
                        </div>

                        <div style="display:flex;gap:16px;margin-bottom:16px;">
                            <div class="form-group" style="flex:1;">
                                <label style="font-weight:600;color:#374151;margin-bottom:6px;display:block;">Estimated Cost (₱)</label>
                                <div id="tpCostBreakdown" style="margin-bottom:10px;"></div>
                                <label for="tp_estimated_cost" style="display:block;font-weight:600;margin-bottom:6px;">Total (₱)</label>
                                <input type="number" name="estimated_cost" id="tp_estimated_cost" class="form-control" placeholder="0.00" step="0.01" min="0" oninput="tpShowCostAdjustment()" style="width:100%;padding:10px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:0.875rem;">
                                <div id="tpCostAdjustment" style="font-size:0.8rem;color:#64748b;margin-top:6px;" aria-live="polite"></div>
                                <small style="color:#6b7280;">Edit treatment amounts to update the total, or adjust the total directly.</small>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label style="font-weight:600;color:#374151;margin-bottom:6px;display:block;">Status</label>
                                <input type="text" id="tp_status" class="form-control" value="Active" readonly style="width:100%;padding:10px 14px;border:1px solid #d1d5db;border-radius:8px;background:#f8fafc;">
                                <small style="color:#6b7280;">Status updates automatically when sessions start or finish.</small>
                            </div>
                        </div>

                        <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:16px;border-top:1px solid #e5e7eb;">
                            <button type="button" class="consult-complete-btn" style="background:#6b7280;color:white;" onclick="resetConsultTpForm()">Reset</button>
                            <button type="submit" class="consult-complete-btn" style="background:#0ea5e9;color:white;" id="tpSaveBtn">Save Treatment Plan</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Prescription -->
            <div class="consult-tab-panel" id="panel-prescription">
                <?php if ($isPastView): ?><div class="read-only-overlay-msg">This is a completed past consultation. All data is read-only.</div><?php endif; ?>
                <div style="max-width:700px;">
                    <h3 style="font-size:1rem;font-weight:700;color:#1e293b;margin:0 0 16px;">Prescription</h3>
                    <div id="prescriptionItems"></div>
                    <button class="add-presc-btn" onclick="addPrescriptionItem()">+ Add Medication</button>
                    <div style="margin-top:12px;display:flex;gap:12px;flex-wrap:wrap;justify-content:flex-end;">
                        <button class="consult-complete-btn" style="background:#0ea5e9;color:white;" onclick="savePrescription()">Save Prescription</button>
                        <button class="consult-complete-btn" style="background:#0f172a;color:white;" onclick="printPrescription()">Print Prescription</button>
                    </div>
                </div>
            </div>

            <!-- Medical Certificate -->
            <div class="consult-tab-panel" id="panel-certificate">
                <?php if ($isPastView): ?><div class="read-only-overlay-msg">This is a completed past consultation. All data is read-only.</div><?php endif; ?>
                <div style="max-width:720px;">
                    <h3 style="font-size:1rem;font-weight:700;color:#1e293b;margin:0 0 6px;">Medical Certificate</h3>
                    <p id="certPatientLine" style="margin:0 0 16px;color:#64748b;font-size:0.88rem;"></p>
                    <div id="certSignatureWarning" style="display:none;margin-bottom:14px;padding:10px 12px;background:#fff7ed;border:1px solid #fdba74;border-radius:8px;color:#9a3412;font-size:0.85rem;">
                        Save your signature in <a href="dentist_profile.php" style="color:#9a3412;font-weight:700;">Profile &amp; Settings</a> before issuing this certificate.
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <label style="display:block;font-size:0.82rem;font-weight:600;color:#334155;margin-bottom:4px;">Purpose</label>
                            <input type="text" class="form-control" id="certPurpose" value="Employment">
                        </div>
                        <div>
                            <label style="display:block;font-size:0.82rem;font-weight:600;color:#334155;margin-bottom:4px;">Date issued</label>
                            <input type="date" class="form-control" id="certIssuedAt">
                        </div>
                        <div style="grid-column:1 / -1;">
                            <label style="display:block;font-size:0.82rem;font-weight:600;color:#334155;margin-bottom:4px;">Findings</label>
                            <textarea class="form-control" id="certFindings" rows="3" placeholder="Examination findings"></textarea>
                        </div>
                        <div style="grid-column:1 / -1;">
                            <label style="display:block;font-size:0.82rem;font-weight:600;color:#334155;margin-bottom:4px;">Recommendation</label>
                            <textarea class="form-control" id="certRecommendation" rows="2" placeholder="Fit to work, or advised rest"></textarea>
                        </div>
                        <div>
                            <label style="display:block;font-size:0.82rem;font-weight:600;color:#334155;margin-bottom:4px;">Rest from</label>
                            <input type="date" class="form-control" id="certRestFrom">
                        </div>
                        <div>
                            <label style="display:block;font-size:0.82rem;font-weight:600;color:#334155;margin-bottom:4px;">Rest to</label>
                            <input type="date" class="form-control" id="certRestTo">
                        </div>
                        <div style="grid-column:1 / -1;">
                            <label style="display:block;font-size:0.82rem;font-weight:600;color:#334155;margin-bottom:4px;">Remarks</label>
                            <textarea class="form-control" id="certRemarks" rows="2" placeholder="Optional"></textarea>
                        </div>
                    </div>
                    <div style="margin-top:18px;text-align:right;">
                        <img id="certSignaturePreview" alt="Dentist signature" style="display:none;height:72px;width:200px;margin-left:auto;margin-right:12px;object-fit:cover;object-position:left center;">
                        <div id="certDentistName" style="font-weight:700;color:#0f172a;margin-top:4px;"></div>
                        <div style="font-size:0.78rem;color:#64748b;">Dentist</div>
                    </div>
                    <div style="margin-top:12px;display:flex;gap:12px;flex-wrap:wrap;justify-content:flex-end;">
                        <small style="color:#64748b;font-size:0.78rem;align-self:center;max-width:260px;">Click Save Certificate so the patient can view, download, and print it from their Dental History.</small>
                        <button type="button" class="consult-complete-btn" id="certSaveBtn" style="background:#0ea5e9;color:white;" onclick="saveCertificate()">Save Certificate</button>
                        <button type="button" class="consult-complete-btn" id="certPrintBtn" style="background:#0f172a;color:white;" onclick="printCertificate()">Print Certificate</button>
                    </div>
                    <p id="certSaveStatus" style="text-align:right;font-size:0.82rem;color:#059669;min-height:1.2em;margin:8px 0 0;"></p>
                </div>
            </div>

            <!-- Informed Consent -->
            <div class="consult-tab-panel" id="panel-consent">
                <?php if ($isPastView): ?><div class="read-only-overlay-msg">This is a completed past consultation. All data is read-only.</div><?php endif; ?>
                <div class="consent-form-container">
                    <h4>INFORMED CONSENT FOR DENTAL PROCEDURE</h4>
                    <p class="consent-clinic-name" id="consentClinicName">RF Dental Clinic</p>

                    <div class="consent-field-row">
                        <span class="consent-field-label">Patient Name:</span>
                        <input type="text" class="sig-name-input" id="consentPatientNameInput" style="text-align:left;border-bottom:1px solid #d1d5db;border-top:none;border-left:none;border-right:none;border-radius:0;">
                    </div>
                    <div class="consent-field-row">
                        <span class="consent-field-label">Provider:</span>
                        <input type="text" class="sig-name-input" id="consentProviderInput" style="text-align:left;border-bottom:1px solid #d1d5db;border-top:none;border-left:none;border-right:none;border-radius:0;">
                    </div>
                    <div class="consent-field-row">
                        <span class="consent-field-label">Address:</span>
                        <input type="text" class="sig-name-input" id="consentAddressInput" style="text-align:left;border-bottom:1px solid #d1d5db;border-top:none;border-left:none;border-right:none;border-radius:0;">
                    </div>
                    <div class="consent-field-row">
                        <span class="consent-field-label">Procedure/Operation:</span>
                        <input type="text" class="sig-name-input" id="consentProcedureInput" style="text-align:left;border-bottom:1px solid #d1d5db;border-top:none;border-left:none;border-right:none;border-radius:0;">
                    </div>

                    <div class="dental-check-item" style="margin:12px 0;">
                        <input type="checkbox" id="consentGuardianCheck" onchange="toggleGuardianName()">
                        <label for="consentGuardianCheck">Consent is being given by a guardian/representative</label>
                    </div>
                    <div id="guardianNameWrapper" style="display:none;margin-bottom:12px;">
                        <input type="text" class="sig-name-input" id="consentGuardianNameInput" placeholder="Guardian/Representative Name" style="text-align:left;border-bottom:1px solid #d1d5db;border-top:none;border-left:none;border-right:none;border-radius:0;width:100%;">
                    </div>

                    <div class="consent-text-block">
                        <strong>Consent Statement:</strong><br><br>
                        I hereby authorize the dentist and their staff to perform the above-mentioned dental procedure(s) on the patient named above. I understand that:
                        <ol style="margin:8px 0 0 16px;padding:0;font-size:0.8rem;color:#475569;line-height:1.8;">
                            <li>The nature of the procedure has been explained to me, including the benefits, risks, and alternatives.</li>
                            <li>I have had the opportunity to ask questions and all my questions have been answered to my satisfaction.</li>
                            <li>I understand that no guarantee has been made to me regarding the outcome of the procedure.</li>
                            <li>I consent to the administration of local or general anesthesia as deemed necessary by the dentist.</li>
                            <li>I understand that complications may arise despite the exercise of reasonable care and skill.</li>
                            <li>I authorize the release of any information necessary to process insurance claims related to this treatment.</li>
                        </ol>
                    </div>

                    <div class="consent-field-row">
                        <span class="consent-field-label">Date:</span>
                        <input type="date" class="sig-name-input" id="consentDateInput" style="text-align:left;border-bottom:1px solid #d1d5db;border-top:none;border-left:none;border-right:none;border-radius:0;">
                    </div>
                    <div class="consent-field-row">
                        <span class="consent-field-label">Place:</span>
                        <input type="text" class="sig-name-input" id="consentPlaceInput" value="RF Dental Clinic" style="text-align:left;border-bottom:1px solid #d1d5db;border-top:none;border-left:none;border-right:none;border-radius:0;">
                    </div>

                    <div class="consent-signature-grid">
                        <div class="sig-block">
                            <div class="sig-line" id="patientSigLine" onclick="openConsentSignatureModal('patient')" title="Tap to sign">Tap to sign</div>
                            <div class="sig-preview" id="patientSigPreview"></div>
                            <div class="sig-label">Patient / Guardian</div>
                            <input type="text" class="sig-name-input" id="consentPatientSignName" placeholder="Printed Name">
                        </div>
                        <div class="sig-block">
                            <div class="sig-line signed" id="doctorSigLine" onclick="openConsentSignatureModal('doctor')" title="Doctor signature"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Doctor'); ?></div>
                            <div class="sig-preview" id="doctorSigPreview"></div>
                            <div class="sig-label">Doctor</div>
                            <input type="text" class="sig-name-input" id="consentDoctorSignName" value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>" readonly style="background:#f1f5f9;">
                            <div id="doctorSigSavedNote" style="font-size:0.75rem;color:#64748b;margin-top:6px;"></div>
                        </div>
                        <div class="sig-block">
                            <div class="sig-line" id="witnessSigLine" onclick="openConsentSignatureModal('witness')" title="Tap to sign">Tap to sign</div>
                            <div class="sig-preview" id="witnessSigPreview"></div>
                            <div class="sig-label">Witness</div>
                            <input type="text" class="sig-name-input" id="consentWitnessSignName" placeholder="Printed Name">
                        </div>
                    </div>

                    <div style="margin-top:20px;text-align:right;display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap;">
                        <button class="consult-complete-btn" style="background:#0ea5e9;color:white;" onclick="saveConsent()">Save Consent</button>
                        <button class="consult-complete-btn" style="background:#0f172a;color:white;" onclick="printConsentForm()">Print Consent</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tooth Modal -->
<div class="tooth-mini-modal-overlay" id="toothModalOverlay" onclick="closeToothModal(event)">
    <div class="tooth-mini-modal" onclick="event.stopPropagation()">

        <!-- Step 1: Per Surface / Whole Teeth -->
        <div id="toothModalStep1">
            <h4 id="toothModalTitle">Tooth #28</h4>
            <button class="modal-choice-btn" onclick="showSurfaceSelection()">Per Surface</button>
            <button class="modal-choice-btn" onclick="showWholeToothFindings()">Whole Teeth</button>
            <button class="modal-close-btn" onclick="closeToothModalDirect()">Close</button>
        </div>

        <!-- Step 2: Surface Selection (O, M, D, B, L) -->
        <div id="surfaceSelectionStep" style="display:none;">
            <h4 id="surfaceSelectionTitle">Select Surface - Tooth #28</h4>
            <div id="surfaceSelectionList"></div>
            <button class="modal-close-btn" onclick="backToToothModalStep1()" style="margin-top:8px;">Back</button>
        </div>

        <!-- Step 3: Pathology + Treatment -->
        <div id="perSurfaceStep" style="display:none;">
            <h4 id="perSurfaceTitle">Occlusal - Tooth #28</h4>
            <div class="finding-section-title">Pathology</div>
            <button class="pathology-btn" onclick="applyFinding('pathology', 'D', 'Decayed')">D - Decayed</button>
            <div class="finding-section-title">Treatment</div>
            <button class="treat-btn" onclick="applyFinding('treatment', 'Am', 'Amalgam Filling')">Am - Amalgam Filling</button>
            <button class="treat-btn" onclick="applyFinding('treatment', 'Co', 'Composite Filling')">Co - Composite Filling</button>
            <button class="treat-btn" onclick="applyFinding('treatment', 'JC', 'Jacket Crown')">JC - Jacket Crown</button>
            <button class="treat-btn" onclick="applyFinding('treatment', 'Ab', 'Abutment')">Ab - Abutment</button>
            <button class="treat-btn" onclick="applyFinding('treatment', 'Att', 'Attachment')">Att - Attachment</button>
            <button class="treat-btn" onclick="applyFinding('treatment', 'P', 'Pontic')">P - Pontic</button>
            <button class="treat-btn" onclick="applyFinding('treatment', 'In', 'Inlay')">In - Inlay</button>
            <button class="treat-btn" onclick="applyFinding('treatment', 'Imp', 'Implant')">Imp - Implant</button>
            <button class="treat-btn" onclick="applyFinding('treatment', 'S', 'Sealants')">S - Sealants</button>
            <button class="treat-btn" onclick="applyFinding('treatment', 'Rm', 'Removable Denture')">Rm - Removable Denture</button>
            <button class="modal-close-btn" onclick="backToSurfaceSelection()" style="margin-top:8px;">Back</button>
        </div>

        <!-- Step 2b: Whole Teeth → Condition/Surgery + Treatment -->
        <div id="wholeToothStep" style="display:none;">
            <h4 id="wholeToothTitle">Whole Tooth - #28</h4>
            <div class="finding-section-title">Condition / Surgery</div>
            <button class="condition-btn" onclick="applyFinding('condition', 'M', 'Missing due to caries')">M - Missing (caries)</button>
            <button class="condition-btn" onclick="applyFinding('condition', 'MO', 'Missing other causes')">MO - Missing (other)</button>
            <button class="condition-btn" onclick="applyFinding('condition', 'Im', 'Impacted Tooth')">Im - Impacted Tooth</button>
            <button class="condition-btn" onclick="applyFinding('condition', 'Sp', 'Supernumerary')">Sp - Supernumerary</button>
            <button class="condition-btn" onclick="applyFinding('condition', 'Rf', 'Root Fragment')">Rf - Root Fragment</button>
            <button class="condition-btn" onclick="applyFinding('condition', 'Un', 'Unerupted')">Un - Unerupted</button>
            <button class="condition-btn" onclick="applyFinding('surgery', 'Xc', 'Extraction due to caries')">X - Extraction (caries)</button>
            <button class="condition-btn" onclick="applyFinding('surgery', 'Xo', 'Extraction other cause')">X - Extraction (other)</button>
            <div class="finding-section-title">Treatment</div>
            <button class="treat-btn" onclick="applyFinding('treatment', 'Am', 'Amalgam Filling')">Am - Amalgam Filling</button>
            <button class="treat-btn" onclick="applyFinding('treatment', 'Co', 'Composite Filling')">Co - Composite Filling</button>
            <button class="treat-btn" onclick="applyFinding('treatment', 'JC', 'Jacket Crown')">JC - Jacket Crown</button>
            <button class="treat-btn" onclick="applyFinding('treatment', 'Ab', 'Abutment')">Ab - Abutment</button>
            <button class="treat-btn" onclick="applyFinding('treatment', 'Imp', 'Implant')">Imp - Implant</button>
            <button class="treat-btn" onclick="applyFinding('treatment', 'Rm', 'Removable Denture')">Rm - Removable Denture</button>
            <button class="modal-close-btn" onclick="backToToothModalStep1()" style="margin-top:8px;">Back</button>
        </div>

    </div>
</div>

<!-- Complete Consultation Modal -->
<div class="cc-overlay" id="completeConsultModal" onclick="if(event.target===this)closeCompleteConsultModal()">
    <div class="cc-modal" onclick="event.stopPropagation()">
        <div class="cc-bg-orb cc-bg-orb-a"></div>
        <div class="cc-bg-orb cc-bg-orb-b"></div>
        <div class="cc-banner">
            <div class="cc-icon-wrap">
                <span class="cc-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M9.2 16.2 5.5 12.5l-1.4 1.4 5.1 5.1L20 8.2l-1.4-1.4z"></path>
                    </svg>
                </span>
            </div>
            <div>
                <div class="cc-kicker">Consultation</div>
                <h2 style="font-size:1.55rem;font-weight:700;margin:6px 0 0;">Complete Consultation</h2>
            </div>
            <button onclick="closeCompleteConsultModal()" class="cc-close">&times;</button>
        </div>
        <div class="cc-body">
            <p class="cc-text">Mark this consultation as complete and move this patient to billing?</p>
            <div class="cc-highlight">
                <div class="cc-highlight-icon">&#9878;</div>
                <div>
                    <div class="cc-highlight-label">Patient Care Flow</div>
                    <div class="cc-highlight-value">Consultation finishes here, billing continues next.</div>
                </div>
            </div>
            <div class="cc-note">
                <span class="cc-note-dot"></span>
                The dashboard and billing list will refresh after confirmation.
            </div>
        </div>
        <div class="cc-actions">
            <button type="button" onclick="closeCompleteConsultModal()" class="cc-btn-cancel">Cancel</button>
            <button type="button" onclick="confirmCompleteConsult()" class="cc-btn-confirm">Yes, Complete</button>
        </div>
    </div>
</div>

<!-- Consultation Completed Modal -->
<div class="cc-overlay" id="consultationCompletedModal" onclick="if(event.target===this)closeConsultationCompletedModal()">
    <div class="cc-modal" onclick="event.stopPropagation()">
        <div class="cc-bg-orb cc-bg-orb-a"></div>
        <div class="cc-bg-orb cc-bg-orb-b"></div>
        <div class="cc-banner">
            <div class="cc-icon-wrap">
                <span class="cc-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M9.2 16.2 5.5 12.5l-1.4 1.4 5.1 5.1L20 8.2l-1.4-1.4z"></path>
                    </svg>
                </span>
            </div>
            <div>
                <div class="cc-kicker">Consultation Completed</div>
                <h2 style="font-size:1.55rem;font-weight:700;margin:6px 0 0;">Consultation completed!</h2>
            </div>
            <button onclick="closeConsultationCompletedModal()" class="cc-close">&times;</button>
        </div>
        <div class="cc-body">
            <p class="cc-text">Patient has been moved to billing.</p>
            <div style="margin-top:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:0.78rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;">Total Amount</span>
                    <span style="font-size:1.05rem;font-weight:700;color:#1e293b;" id="ccTotalAmount">&#8369;0.00</span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:0.78rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;">Remaining Balance</span>
                    <span style="font-size:1.05rem;font-weight:700;color:#c2410c;" id="ccBalance">&#8369;0.00</span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:0.78rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;">Payment Status</span>
                    <span id="ccPaymentStatus" style="background:#fef3c7;color:#92400e;padding:2px 12px;border-radius:12px;font-size:0.75rem;font-weight:700;">Unpaid</span>
                </div>
            </div>
            <div class="cc-note">
                <span class="cc-note-dot"></span>
                You can manage payment from the Payment page.
            </div>
        </div>
        <div class="cc-actions">
            <button type="button" onclick="closeConsultationCompletedModal()" class="cc-btn-confirm">OK</button>
        </div>
    </div>
</div>

<!-- Consent Saved Modal -->
<div class="cc-overlay" id="consentSavedModal" onclick="if(event.target===this)closeConsentSavedModal()">
    <div class="cc-modal" onclick="event.stopPropagation()">
        <div class="cc-bg-orb cc-bg-orb-a"></div>
        <div class="cc-bg-orb cc-bg-orb-b"></div>
        <div class="cc-banner">
            <div class="cc-icon-wrap">
                <span class="cc-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M9.2 16.2 5.5 12.5l-1.4 1.4 5.1 5.1L20 8.2l-1.4-1.4z"></path>
                    </svg>
                </span>
            </div>
            <div>
                <div class="cc-kicker">Informed Consent</div>
                <h2 style="font-size:1.55rem;font-weight:700;margin:6px 0 0;">Consent Saved</h2>
            </div>
            <button onclick="closeConsentSavedModal()" class="cc-close">&times;</button>
        </div>
        <div class="cc-body">
            <p class="cc-text">The informed consent form has been saved for this consultation.</p>
            <div class="cc-note">
                <span class="cc-note-dot"></span>
                You can print the consent form anytime from the Informed Consent tab.
            </div>
        </div>
        <div class="cc-actions">
            <button type="button" onclick="closeConsentSavedModal()" class="cc-btn-confirm">OK</button>
        </div>
    </div>
</div>

<!-- Treatment Plan Saved Modal -->
<div class="cc-overlay" id="treatmentPlanSavedModal" onclick="if(event.target===this)closeTreatmentPlanSavedModal()">
    <div class="cc-modal" onclick="event.stopPropagation()">
        <div class="cc-bg-orb cc-bg-orb-a"></div>
        <div class="cc-bg-orb cc-bg-orb-b"></div>
        <div class="cc-banner">
            <div class="cc-icon-wrap">
                <span class="cc-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M9.2 16.2 5.5 12.5l-1.4 1.4 5.1 5.1L20 8.2l-1.4-1.4z"></path>
                    </svg>
                </span>
            </div>
            <div>
                <div class="cc-kicker">Treatment Plan</div>
                <h2 id="tpSavedModalTitle" style="font-size:1.55rem;font-weight:700;margin:6px 0 0;">Treatment Plan Created</h2>
            </div>
            <button onclick="closeTreatmentPlanSavedModal()" class="cc-close">&times;</button>
        </div>
        <div class="cc-body">
            <p class="cc-text" id="tpSavedModalMessage">Treatment plan created.</p>
            <div id="tpSavedApptList" style="display:none;margin-top:4px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px;"></div>
            <div class="cc-note" id="tpSavedApptNote" style="margin-top:14px;display:none;">
                <span class="cc-note-dot"></span>
                You can view these appointments from the Appointments page.
            </div>
        </div>
        <div class="cc-actions">
            <button type="button" onclick="closeTreatmentPlanSavedModal()" class="cc-btn-confirm">OK</button>
        </div>
    </div>
</div>

<div class="signature-modal-overlay" id="consentSignatureModal" onclick="if(event.target === this) closeConsentSignatureModal()">
    <div class="signature-modal" onclick="event.stopPropagation()">
        <h3 id="consentSignatureModalTitle">Draw Signature</h3>
        <div class="signature-canvas-wrapper">
            <canvas id="consentSignatureCanvas" class="signature-canvas"></canvas>
        </div>
        <div class="signature-modal-footer">
            <button type="button" class="signature-clear-btn" onclick="clearConsentSignatureCanvas()">Clear</button>
            <button type="button" class="signature-cancel-btn" onclick="closeConsentSignatureModal()">Cancel</button>
            <button type="button" class="signature-save-btn" onclick="saveConsentSignature()">Save Signature</button>
        </div>
    </div>
</div>

<script src="../../assets/js/medical-certificate-template.js"></script>
<script src="../../assets/js/signature_pad.min.js"></script>
<script>
// ===================== STATE =====================
var currentConsultation = null;
var currentQueueId = <?php echo $initialQueueId; ?>;
var isPastView = <?php echo $isPastView ? 'true' : 'false'; ?>;
var pastConsultId = <?php echo $pastConsultId; ?>;
var teethFindings = [];
var currentToothNumber = null;
var signaturePadInstance = null;
var currentConsentSignatureType = null;
var currentConsentSignatureData = {
    patient: null,
    doctor: null,
    witness: null
};
var currentToothSurface = null;
    var notesSaveTimeout = null;

    var historySaveTimeout = null;

    var diagnosisSaveTimeout = null;
// ===================== QUEUE SELECTION =====================
function selectConsultPatient(chip) {
    document.querySelectorAll('.consult-queue-chip').forEach(function(c) { c.classList.remove('active'); });
    chip.classList.add('active');
    currentQueueId = parseInt(chip.getAttribute('data-queue-id'));
    loadConsultation(currentQueueId);
}

function loadConsultation(queueId) {
    fetch('consultation_actions.php?action=get_consultation&queue_id=' + queueId)
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success && data.consultation) {
            currentConsultation = data.consultation;
            if (!Array.isArray(currentConsultation.prescription_data)) {
                currentConsultation.prescription_data = [];
            }
            teethFindings = data.consultation.teeth_data || [];
            document.getElementById('consultEmptyState').style.display = 'none';
            document.getElementById('consultPanels').style.display = 'block';
            document.getElementById('consultPatientName').textContent = data.consultation.patient_name;
            document.getElementById('consultQueueNo').textContent = '#' + (data.consultation.queue_number || data.consultation.queue_id);
            document.getElementById('consultTreatment').textContent = data.consultation.treatment_type || '-';
            document.getElementById('consultCompleteBtn').style.display = 'flex';
            populateDentalExam();
            populateVitals();
            populateNotes();
            populateHistory();
            populateDiagnosis();
            populateXrays();
            populatePrescription();
            populateConsent();
            populateCertificate();
            renderToothFindings();
            renderToothButtons();
            loadPastConsultations(data.consultation.patient_id, data.consultation.id);
            var tpLabel = document.getElementById('tpPatientLabel');
            if (tpLabel) tpLabel.textContent = data.consultation.patient_name;
            resetConsultTpForm();
            loadConsultTreatmentPlans();
        }
    });
}

// ===================== TAB SWITCHING =====================
function switchConsultTab(btn, tabId) {
    document.querySelectorAll('.consult-tab-btn').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    document.querySelectorAll('.consult-tab-panel').forEach(function(p) { p.classList.remove('active'); });
    document.getElementById('panel-' + tabId).classList.add('active');
}

// ===================== DENTAL EXAM =====================
function populateDentalExam() {
    var c = currentConsultation;
    document.getElementById('periodontalScreening').value = c.periodontal_screening || '';
    document.getElementById('occClassMolar').value = c.occlusion_molar_class || (parseInt(c.occlusion_class_molar) ? 'legacy_unspecified' : '');
    document.getElementById('occNotes').value = c.occlusion_notes || '';
    document.getElementById('occOverjet').checked = !!parseInt(c.occlusion_overjet);
    document.getElementById('occOverbite').checked = !!parseInt(c.occlusion_overbite);
    document.getElementById('occMidline').checked = !!parseInt(c.occlusion_midline_deviation);
    document.getElementById('occCrossbite').checked = !!parseInt(c.occlusion_crossbite);
    document.getElementById('appOrthodontic').checked = !!parseInt(c.appliances_orthodontic);
    document.getElementById('appSkyplate').checked = !!parseInt(c.appliances_skyplate);
    document.getElementById('appOthers').checked = !!parseInt(c.appliances_others);
    document.getElementById('appOthersText').value = c.appliances_others_text || '';
    toggleOthersInput();
    document.getElementById('tmdClenching').checked = !!parseInt(c.tmd_clenching);
    document.getElementById('tmdClicking').checked = !!parseInt(c.tmd_clicking);
    document.getElementById('tmdTrismus').checked = !!parseInt(c.tmd_trismus);
    document.getElementById('tmdMuscleSpasm').checked = !!parseInt(c.tmd_muscle_spasm);
}

function toggleOthersInput() {
    var el = document.getElementById('othersInputWrapper');
    if (document.getElementById('appOthers').checked) {
        el.classList.add('visible');
    } else {
        el.classList.remove('visible');
    }
}

// ===================== VITALS & BODY MEASUREMENT =====================
function computeBmi(heightCm, weightKg) {
    var h = parseFloat(heightCm);
    var w = parseFloat(weightKg);
    if (!h || !w || h <= 0) return null;
    var m = h / 100;
    return (w / (m * m)).toFixed(1);
}

function populateVitals() {
    var v = (currentConsultation && currentConsultation.vitals) || {};
    var ids = {
        blood_pressure: 'dvBloodPressure',
        heart_rate: 'dvHeartRate',
        temperature: 'dvTemperature',
        oxygen_saturation: 'dvOxygenSaturation',
        height: 'dvHeight',
        weight: 'dvWeight'
    };
    var hasAny = false;
    Object.keys(ids).forEach(function(key) {
        var el = document.getElementById(ids[key]);
        if (!el) return;
        var val = (v[key] || '').toString().trim();
        if (val) {
            var suffix = '';
            if (key === 'blood_pressure') suffix = ' mmHg';
            else if (key === 'heart_rate') suffix = ' bpm';
            else if (key === 'temperature') suffix = ' °C';
            else if (key === 'oxygen_saturation') suffix = ' %';
            else if (key === 'height') suffix = ' cm';
            else if (key === 'weight') suffix = ' kg';
            el.textContent = val + suffix;
            hasAny = true;
        } else {
            el.textContent = '\u2014';
        }
    });
    var bmi = computeBmi(v.height, v.weight);
    var bmiEl = document.getElementById('dvBmi');
    if (bmiEl) bmiEl.textContent = bmi !== null ? bmi + ' kg/m\u00b2' : '\u2014';
    var emptyEl = document.getElementById('dentalVitalsEmpty');
    if (emptyEl) emptyEl.style.display = hasAny ? 'none' : 'block';
}

function saveDentalExam() {
    if (isPastView || !currentConsultation) return;
    var payload = {
        consultation_id: currentConsultation.id,
        periodontal_screening: document.getElementById('periodontalScreening').value,
        occlusion_class_molar: document.getElementById('occClassMolar').value ? 1 : 0,
        occlusion_molar_class: document.getElementById('occClassMolar').value === 'legacy_unspecified' ? null : document.getElementById('occClassMolar').value,
        occlusion_notes: document.getElementById('occNotes').value,
        occlusion_overjet: document.getElementById('occOverjet').checked ? 1 : 0,
        occlusion_overbite: document.getElementById('occOverbite').checked ? 1 : 0,
        occlusion_midline_deviation: document.getElementById('occMidline').checked ? 1 : 0,
        occlusion_crossbite: document.getElementById('occCrossbite').checked ? 1 : 0,
        appliances_orthodontic: document.getElementById('appOrthodontic').checked ? 1 : 0,
        appliances_skyplate: document.getElementById('appSkyplate').checked ? 1 : 0,
        appliances_others: document.getElementById('appOthers').checked ? 1 : 0,
        appliances_others_text: document.getElementById('appOthersText').value,
        tmd_clenching: document.getElementById('tmdClenching').checked ? 1 : 0,
        tmd_clicking: document.getElementById('tmdClicking').checked ? 1 : 0,
        tmd_trismus: document.getElementById('tmdTrismus').checked ? 1 : 0,
        tmd_muscle_spasm: document.getElementById('tmdMuscleSpasm').checked ? 1 : 0,
        teeth_data: teethFindings
    };
    fetch('consultation_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(Object.assign({ action: 'save_dental_exam' }, payload))
    }).then(function(r) { return r.json(); });
}

// ===================== TOOTH CHART (Geometric SVG) =====================
var adultUpperRight = [18,17,16,15,14,13,12,11];
var adultUpperLeft = [21,22,23,24,25,26,27,28];
var adultLowerRight = [48,47,46,45,44,43,42,41];
var adultLowerLeft = [31,32,33,34,35,36,37,38];
var pedUpperRight = [55,54,53,52,51];
var pedUpperLeft = [61,62,63,64,65];
var pedLowerRight = [85,84,83,82,81];
var pedLowerLeft = [71,72,73,74,75];

var TOOTH_SVG_SIZE = 44;
var TOOTH_CX = 22;
var TOOTH_CY = 22;
var TOOTH_R = 10;

function getSurfaceColor(toothNum, surface) {
    var f = teethFindings.find(function(x) { return x.tooth === toothNum && x.surface === surface; });
    if (!f) return null;
    if (f.type === 'pathology') return '#ef4444';
    if (f.type === 'treatment') return '#7dd3fc';
    return null;
}

function getWholeToothColor(toothNum) {
    var f = teethFindings.find(function(x) { return x.tooth === toothNum && !x.surface; });
    if (!f) return null;
    if (f.type === 'pathology') return '#ef4444';
    if (f.type === 'treatment') return '#7dd3fc';
    if (f.type === 'surgery') return '#ef4444';
    if (f.type === 'condition') return '#ef4444';
    return null;
}

function createToothSVG(toothNum) {
    var S = TOOTH_SVG_SIZE, C = TOOTH_CX, CY = TOOTH_CY, R = TOOTH_R;
    var d = 'M0,0 L' + C + ',' + CY;
    var wholeFill = getWholeToothColor(toothNum);

    var labelColor = '#94a3b8';

    var svg = '<svg width="' + S + '" height="' + S + '" viewBox="0 0 ' + S + ' ' + S + '" xmlns="http://www.w3.org/2000/svg">';

    svg += '<rect x="1" y="1" width="' + (S-2) + '" height="' + (S-2) + '" fill="' + (wholeFill || 'white') + '" stroke="#94a3b8" stroke-width="1.5" rx="2"/>';

    var bFill = getSurfaceColor(toothNum, 'B') || wholeFill || 'white';
    var pFill = getSurfaceColor(toothNum, 'P') || wholeFill || 'white';
    var leftFill = getSurfaceColor(toothNum, 'D') || wholeFill || 'white';
    var rightFill = getSurfaceColor(toothNum, 'M') || wholeFill || 'white';
    var oFill = getSurfaceColor(toothNum, 'O') || wholeFill || 'white';

    svg += '<polygon points="1,1 ' + C + ',' + CY + ' ' + (S-1) + ',1" fill="' + bFill + '" stroke="#94a3b8" stroke-width="0.8" style="cursor:pointer" data-surface="B" onclick="clickSurface(' + toothNum + ',\'B\')"/>';
    svg += '<polygon points="1,' + (S-1) + ' ' + C + ',' + CY + ' ' + (S-1) + ',' + (S-1) + '" fill="' + pFill + '" stroke="#94a3b8" stroke-width="0.8" style="cursor:pointer" data-surface="P" onclick="clickSurface(' + toothNum + ',\'P\')"/>';
    svg += '<polygon points="1,1 ' + C + ',' + CY + ' 1,' + (S-1) + '" fill="' + leftFill + '" stroke="#94a3b8" stroke-width="0.8" style="cursor:pointer" data-surface="D" onclick="clickSurface(' + toothNum + ',\'D\')"/>';
    svg += '<polygon points="' + (S-1) + ',1 ' + C + ',' + CY + ' ' + (S-1) + ',' + (S-1) + '" fill="' + rightFill + '" stroke="#94a3b8" stroke-width="0.8" style="cursor:pointer" data-surface="M" onclick="clickSurface(' + toothNum + ',\'M\')"/>';

    svg += '<circle cx="' + C + '" cy="' + CY + '" r="' + R + '" fill="' + oFill + '" stroke="#94a3b8" stroke-width="1" style="cursor:pointer" data-surface="O" onclick="clickSurface(' + toothNum + ',\'O\')"/>';

    svg += '<text x="' + C + '" y="' + (CY + 3) + '" text-anchor="middle" font-size="7" font-weight="700" fill="' + labelColor + '" pointer-events="none">O</text>';
    svg += '<text x="' + C + '" y="9" text-anchor="middle" font-size="5" font-weight="700" fill="' + labelColor + '" pointer-events="none">B</text>';
    svg += '<text x="' + C + '" y="' + (S - 3) + '" text-anchor="middle" font-size="5" font-weight="700" fill="' + labelColor + '" pointer-events="none">P</text>';
    svg += '<text x="5" y="' + (CY + 2) + '" text-anchor="middle" font-size="5" font-weight="700" fill="' + labelColor + '" pointer-events="none">D</text>';
    svg += '<text x="' + (S - 5) + '" y="' + (CY + 2) + '" text-anchor="middle" font-size="5" font-weight="700" fill="' + labelColor + '" pointer-events="none">M</text>';

    svg += '</svg>';
    return svg;
}

function renderToothColumn(toothNum) {
    var findings = teethFindings.filter(function(f) { return f.tooth === toothNum; });
    var badgesHtml = '';
    findings.forEach(function(f) {
        var txt = f.surface ? f.surface + ':' + f.code : f.code;
        var cls = 'tooth-finding-badge ' + f.type;
        if (f.type === 'surgery') cls = 'tooth-finding-badge surgery';
        if (f.type === 'condition') cls = 'tooth-finding-badge condition';
        badgesHtml += '<span class="' + cls + '">' + txt + '</span>';
    });

    return '<div class="tooth-col">' +
        '<div class="tooth-num-label">' + toothNum + '</div>' +
        '<div class="tooth-svg-wrap" id="toothWrap_' + toothNum + '">' + createToothSVG(toothNum) + '</div>' +
        '<div class="tooth-findings-below" id="findingsBelow_' + toothNum + '">' + badgesHtml + '</div>' +
    '</div>';
}

function renderQuadrant(containerId, teeth, label, mirror) {
    var container = document.getElementById(containerId);
    var html = '<div class="quadrant-block">';
    html += '<div class="quadrant-label">' + label + '</div>';
    html += '<div class="tooth-row">';
    var arr = mirror ? teeth.slice().reverse() : teeth;
    arr.forEach(function(t) { html += renderToothColumn(t); });
    html += '</div></div>';
    container.innerHTML = html;
}

function renderToothChart() {
    var upperRow = document.getElementById('adultTeethChart');
    var lowerRow = document.getElementById('pedTeethChart');

    var html = '';
    html += '<div class="quadrant-row">';
    html += '<div id="adultUR" style="display:flex;"></div>';
    html += '<div id="adultUL" style="display:flex;"></div>';
    html += '</div>';
    html += '<div style="border-top:2px dashed #d1d5db;margin:4px 20px;"></div>';
    html += '<div class="quadrant-row">';
    html += '<div id="adultLR" style="display:flex;"></div>';
    html += '<div id="adultLL" style="display:flex;"></div>';
    html += '</div>';
    upperRow.innerHTML = html;

    var phtml = '';
    phtml += '<div class="quadrant-row">';
    phtml += '<div id="pedUR" style="display:flex;"></div>';
    phtml += '<div id="pedUL" style="display:flex;"></div>';
    phtml += '</div>';
    phtml += '<div style="border-top:2px dashed #d1d5db;margin:4px 20px;"></div>';
    phtml += '<div class="quadrant-row">';
    phtml += '<div id="pedLR" style="display:flex;"></div>';
    phtml += '<div id="pedLL" style="display:flex;"></div>';
    phtml += '</div>';
    lowerRow.innerHTML = phtml;

    renderQuadrant('adultUR', adultUpperRight, 'Upper Right', false);
    renderQuadrant('adultUL', adultUpperLeft, 'Upper Left', false);
    renderQuadrant('adultLR', adultLowerRight, 'Lower Right', false);
    renderQuadrant('adultLL', adultLowerLeft, 'Lower Left', false);
    renderQuadrant('pedUR', pedUpperRight, 'Upper Right', false);
    renderQuadrant('pedUL', pedUpperLeft, 'Upper Left', false);
    renderQuadrant('pedLR', pedLowerRight, 'Lower Right', false);
    renderQuadrant('pedLL', pedLowerLeft, 'Lower Left', false);
}

function renderToothFindings() {
    var container = document.getElementById('findingsChips');
    if (teethFindings.length === 0) {
        container.innerHTML = '<span style="color:#94a3b8;font-size:0.78rem;">No findings yet. Click a tooth surface to begin.</span>';
        return;
    }
    var html = '';
    teethFindings.forEach(function(f, idx) {
        var label = '#' + f.tooth;
        if (f.surface) label += ' [' + f.surface + ']';
        label += ' - ' + f.code + ' ' + f.label;
        html += '<span class="finding-chip">' + label + ' <button class="remove-finding" onclick="removeFinding(' + idx + ')">&times;</button></span>';
    });
    container.innerHTML = html;
}

function removeFinding(idx) {
    teethFindings.splice(idx, 1);
    saveDentalExam();
    renderToothChart();
    renderToothFindings();
}

var surfaceNames = { 'O': 'Occlusal (Center)', 'M': 'Mesial (Right)', 'D': 'Distal (Left)', 'B': 'Buccal / Facial (Top)', 'L': 'Lingual / Palatal (Bottom)' };

function clickSurface(toothNum, surface) {
    openToothModal(toothNum);
}

function renderToothButtons() {
    renderToothChart();
}

// ===================== TOOTH MODAL =====================
function openToothModal(toothNum) {
    currentToothNumber = toothNum;
    currentToothSurface = null;
    document.getElementById('toothModalTitle').textContent = 'Tooth #' + toothNum;
    document.getElementById('toothModalStep1').style.display = 'block';
    document.getElementById('surfaceSelectionStep').style.display = 'none';
    document.getElementById('perSurfaceStep').style.display = 'none';
    document.getElementById('wholeToothStep').style.display = 'none';
    document.getElementById('toothModalOverlay').classList.add('active');
}

function closeToothModal(e) {
    if (e.target === document.getElementById('toothModalOverlay')) {
        closeToothModalDirect();
    }
}
function closeToothModalDirect() {
    document.getElementById('toothModalOverlay').classList.remove('active');
    resetModalSteps();
}

function resetModalSteps() {
    document.getElementById('toothModalStep1').style.display = 'block';
    document.getElementById('surfaceSelectionStep').style.display = 'none';
    document.getElementById('perSurfaceStep').style.display = 'none';
    document.getElementById('wholeToothStep').style.display = 'none';
}

function showSurfaceSelection() {
    document.getElementById('toothModalStep1').style.display = 'none';
    document.getElementById('surfaceSelectionStep').style.display = 'block';
    document.getElementById('perSurfaceStep').style.display = 'none';
    document.getElementById('wholeToothStep').style.display = 'none';
    document.getElementById('surfaceSelectionTitle').textContent = 'Select Surface - Tooth #' + currentToothNumber;

    var surfaces = [
        { code: 'O', label: 'Occlusal (Center)' },
        { code: 'M', label: 'Mesial (Right)' },
        { code: 'D', label: 'Distal (Left)' },
        { code: 'B', label: 'Buccal / Facial (Top)' },
        { code: 'L', label: 'Lingual / Palatal (Bottom)' }
    ];
    var html = '';
    surfaces.forEach(function(s) {
        var f = teethFindings.find(function(x) { return x.tooth === currentToothNumber && x.surface === s.code; });
        var statusHtml = '';
        if (f) {
            var cls = f.type === 'pathology' ? 'pathology' : 'treatment';
            statusHtml = '<span class="surface-status ' + cls + '">' + f.label + '</span>';
        } else {
            statusHtml = '<span class="surface-status not-marked">not marked</span>';
        }
        html += '<button class="surface-btn" onclick="selectSurfaceAndShowFindings(\'' + s.code + '\')">' +
            '<span>' + s.code + ' - ' + s.label + '</span>' +
            statusHtml +
        '</button>';
    });
    document.getElementById('surfaceSelectionList').innerHTML = html;
}

function selectSurfaceAndShowFindings(surface) {
    currentToothSurface = surface;
    document.getElementById('toothModalStep1').style.display = 'none';
    document.getElementById('surfaceSelectionStep').style.display = 'none';
    document.getElementById('perSurfaceStep').style.display = 'block';
    document.getElementById('wholeToothStep').style.display = 'none';
    document.getElementById('perSurfaceTitle').textContent = (surfaceNames[surface] || surface) + ' - Tooth #' + currentToothNumber;
}

function backToSurfaceSelection() {
    document.getElementById('perSurfaceStep').style.display = 'none';
    showSurfaceSelection();
}

function showWholeToothFindings() {
    document.getElementById('toothModalStep1').style.display = 'none';
    document.getElementById('surfaceSelectionStep').style.display = 'none';
    document.getElementById('perSurfaceStep').style.display = 'none';
    document.getElementById('wholeToothStep').style.display = 'block';
    document.getElementById('wholeToothTitle').textContent = 'Whole Tooth - #' + currentToothNumber;
}

function backToToothModalStep1() {
    document.getElementById('toothModalStep1').style.display = 'block';
    document.getElementById('surfaceSelectionStep').style.display = 'none';
    document.getElementById('perSurfaceStep').style.display = 'none';
    document.getElementById('wholeToothStep').style.display = 'none';
}

function applyFinding(type, code, label) {
    var finding = { tooth: currentToothNumber, surface: currentToothSurface, type: type, code: code, label: label };
    teethFindings.push(finding);
    saveDentalExam();
    closeToothModalDirect();
    renderToothChart();
    renderToothFindings();
}

// ===================== NOTES =====================
function populateNotes() {
    document.getElementById('consultNotes').value = currentConsultation.consultation_notes || '';
}

function debounceNotesSave() {
    if (isPastView) return;
    clearTimeout(notesSaveTimeout);
    notesSaveTimeout = setTimeout(saveNotes, 2000);
    document.getElementById('notesSaveStatus').textContent = 'Unsaved changes...';
}

function saveNotes() {
    if (isPastView || !currentConsultation) return;
    document.getElementById('notesSaveStatus').textContent = 'Saving...';
    fetch('consultation_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_notes', consultation_id: currentConsultation.id, consultation_notes: document.getElementById('consultNotes').value })
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            document.getElementById('notesSaveStatus').textContent = 'Saved!';
            setTimeout(function() { document.getElementById('notesSaveStatus').textContent = ''; }, 2000);
        }
    });
}

// ===================== MEDICAL HISTORY =====================
function populateHistory() {
    var mh = currentConsultation.medical_history || {};
    var container = document.getElementById('existingMedicalHistory');
    document.getElementById('dentalHistoryNotes').value = currentConsultation.dental_medical_history_notes || '';

    var checkboxFields = [
        { key: 'heart_condition', label: 'Heart Conditions' },
        { key: 'diabetes', label: 'Diabetes' },
        { key: 'bleeding_disorder', label: 'Bleeding Disorder' },
        { key: 'pregnancy', label: 'Pregnancy' },
        { key: 'blood_pressure', label: 'Blood Pressure Issues' },
        { key: 'hiv_aids', label: 'HIV/AIDS' },
        { key: 'hepatitis', label: 'Hepatitis' },
        { key: 'tuberculosis', label: 'Tuberculosis' },
        { key: 'asthma', label: 'Asthma' },
        { key: 'epilepsy', label: 'Epilepsy' },
        { key: 'std', label: 'STD' },
        { key: 'stomach_ulcer', label: 'Stomach Ulcer' },
        { key: 'fainting_seizure', label: 'Fainting / Seizure' },
        { key: 'rapid_weight_loss', label: 'Rapid Weight Loss' },
        { key: 'rheumatic_fever', label: 'Rheumatic Fever' },
        { key: 'hay_fever', label: 'Hay Fever / Allergies' },
        { key: 'respiratory_problems', label: 'Respiratory Problems' },
        { key: 'swollen_ankles', label: 'Swollen Ankles' },
        { key: 'kidney_disease', label: 'Kidney Disease' },
        { key: 'chest_pain', label: 'Chest Pain' },
        { key: 'stroke', label: 'Stroke' }
    ];

    var textFields = [
        { key: 'allergies', label: 'Allergies' },
        { key: 'current_medications', label: 'Current Medications' },
        { key: 'medical_conditions', label: 'Medical Conditions' },
        { key: 'past_surgeries', label: 'Past Surgeries' },
        { key: 'others', label: 'Others' }
    ];

    var html = '<div class="mh-checklist">';
    checkboxFields.forEach(function(field) {
        var checked = (mh[field.key] == 1 || mh[field.key] === '1' || mh[field.key] === 'Yes' || mh[field.key] === 'yes');
        var cls = checked ? 'mh-check-item checked' : 'mh-check-item';
        html += '<label class="' + cls + '">';
        html += '<input type="checkbox" data-mh-field="' + field.key + '" ' + (checked ? 'checked' : '') + ' onchange="onMhCheckboxChange(this)">';
        html += '<span class="mh-label">' + field.label + '</span>';
        html += '</label>';
    });
    html += '</div>';

    html += '<div class="mh-text-group">';
    textFields.forEach(function(field) {
        var val = mh[field.key] || '';
        html += '<div class="mh-text-item">';
        html += '<label>' + field.label + '</label>';
        html += '<input type="text" data-mh-field="' + field.key + '" value="' + val.replace(/"/g, '&quot;') + '" placeholder="None" oninput="onMhTextInput(this)">';
        html += '</div>';
    });
    html += '</div>';

    html += '<div class="mh-save-status" id="mhSaveStatus"></div>';
    container.innerHTML = html;
}

function onMhCheckboxChange(el) {
    if (isPastView) return;
    var label = el.closest('.mh-check-item');
    if (el.checked) {
        label.classList.add('checked');
    } else {
        label.classList.remove('checked');
    }
    saveMedicalHistory();
}

function onMhTextInput(el) {
    if (isPastView) return;
    clearTimeout(window._mhSaveTimeout);
    window._mhSaveTimeout = setTimeout(saveMedicalHistory, 1000);
}

function saveMedicalHistory() {
    if (isPastView || !currentConsultation || !currentConsultation.patient_id) return;
    var statusEl = document.getElementById('mhSaveStatus');
    if (statusEl) statusEl.textContent = 'Saving...';

    var data = {};
    document.querySelectorAll('#existingMedicalHistory [data-mh-field]').forEach(function(el) {
        var field = el.getAttribute('data-mh-field');
        if (el.type === 'checkbox') {
            data[field] = el.checked ? 1 : 0;
        } else {
            data[field] = el.value;
        }
    });

    fetch('consultation_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'save_medical_history',
            patient_id: currentConsultation.patient_id,
            medical_data: data
        })
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            if (statusEl) {
                statusEl.textContent = 'Saved!';
                setTimeout(function() { statusEl.textContent = ''; }, 2000);
            }
        } else {
            if (statusEl) statusEl.textContent = 'Error: ' + (d.message || 'Unknown');
        }
    }).catch(function() {
        if (statusEl) statusEl.textContent = 'Save failed';
    });
}

function debounceHistorySave() {
    if (isPastView) return;
    clearTimeout(historySaveTimeout);
    historySaveTimeout = setTimeout(saveHistory, 2000);
    document.getElementById('historySaveStatus').textContent = 'Unsaved changes...';
}

function saveHistory() {
    if (isPastView || !currentConsultation) return;
    document.getElementById('historySaveStatus').textContent = 'Saving...';
    fetch('consultation_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_history', consultation_id: currentConsultation.id, dental_medical_history_notes: document.getElementById('dentalHistoryNotes').value })
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            document.getElementById('historySaveStatus').textContent = 'Saved!';
            setTimeout(function() { document.getElementById('historySaveStatus').textContent = ''; }, 2000);
        }
    });
}

// ===================== DIAGNOSIS =====================
var dxState = {
    primary:   { code: null, desc: null, timer: null, results: [], highlighted: -1 },
    secondary: { code: null, desc: null, timer: null, results: [], highlighted: -1 }
};

function populateDiagnosis() {
    var c = currentConsultation || {};
    dxState.primary.code = c.primary_icd10 || null;
    dxState.primary.desc = c.primary_icd10_desc || null;
    dxState.secondary.code = c.secondary_icd10 || null;
    dxState.secondary.desc = c.secondary_icd10_desc || null;
    document.getElementById('dxPrimaryInput').value = '';
    document.getElementById('dxSecondaryInput').value = '';
    closeDxDropdown('primary');
    closeDxDropdown('secondary');
    renderIcdChip('primary');
    renderIcdChip('secondary');
    document.getElementById('diagnosisNarrative').value = c.diagnosis_narrative || '';
    document.getElementById('diagnosisSaveStatus').textContent = '';
}

function dxInputId(type) {
    return type === 'primary' ? 'dxPrimaryInput' : 'dxSecondaryInput';
}

function dxDropdownId(type) {
    return type === 'primary' ? 'dxPrimaryDropdown' : 'dxSecondaryDropdown';
}

function renderIcdChip(type) {
    var container = document.getElementById(type === 'primary' ? 'dxPrimarySelected' : 'dxSecondarySelected');
    var s = dxState[type];
    if (!s.code) {
        container.innerHTML = '';
        return;
    }
    var label = escapeHtml(s.code) + (s.desc ? ' &mdash; ' + escapeHtml(s.desc) : '');
    container.innerHTML = '<span class="dx-selected-chip">' + label
        + '<span class="dx-chip-x" title="Remove" onclick="clearIcdCode(\'' + type + '\')">&times;</span></span>';
}

function clearIcdCode(type) {
    if (isPastView) return;
    var s = dxState[type];
    s.code = null;
    s.desc = null;
    document.getElementById(dxInputId(type)).value = '';
    closeDxDropdown(type);
    renderIcdChip(type);
    saveDiagnosis();
}

function dxSearch(type) {
    var q = document.getElementById(dxInputId(type)).value.trim();
    clearTimeout(dxState[type].timer);
    if (!q) {
        closeDxDropdown(type);
        return;
    }
    dxState[type].timer = setTimeout(function() {
        fetch('consultation_actions.php?action=search_icd10&q=' + encodeURIComponent(q))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.success) return;
            dxState[type].results = d.results || [];
            dxState[type].highlighted = -1;
            renderDxDropdown(type);
        });
    }, 300);
}

function renderDxDropdown(type) {
    var dropdown = document.getElementById(dxDropdownId(type));
    var results = dxState[type].results;
    if (!results.length) {
        dropdown.innerHTML = '<div class="dx-dropdown-empty">No matching ICD-10 codes found</div>';
    } else {
        var html = '';
        results.forEach(function(r, idx) {
            html += '<div class="dx-dropdown-item' + (idx === dxState[type].highlighted ? ' highlighted' : '') + '"'
                + ' onclick="selectIcdCode(\'' + type + '\', ' + idx + ')">'
                + '<span class="dx-dd-code">' + escapeHtml(r.code) + '</span>'
                + escapeHtml(r.description)
                + '</div>';
        });
        dropdown.innerHTML = html;
    }
    dropdown.classList.add('open');
}

function closeDxDropdown(type) {
    var dropdown = document.getElementById(dxDropdownId(type));
    if (dropdown) dropdown.classList.remove('open');
}

function selectIcdCode(type, idx) {
    if (isPastView) return;
    var r = dxState[type].results[idx];
    if (!r) return;
    dxState[type].code = r.code;
    dxState[type].desc = r.description;
    document.getElementById(dxInputId(type)).value = '';
    closeDxDropdown(type);
    renderIcdChip(type);
    saveDiagnosis();
}

function dxInputKeydown(event, type) {
    var dropdown = document.getElementById(dxDropdownId(type));
    if (!dropdown.classList.contains('open')) return;
    var items = dropdown.querySelectorAll('.dx-dropdown-item');
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        if (!items.length) return;
        dxState[type].highlighted += (event.key === 'ArrowDown') ? 1 : -1;
        if (dxState[type].highlighted < 0) dxState[type].highlighted = items.length - 1;
        if (dxState[type].highlighted >= items.length) dxState[type].highlighted = 0;
        items.forEach(function(el, i) { el.classList.toggle('highlighted', i === dxState[type].highlighted); });
    } else if (event.key === 'Enter') {
        event.preventDefault();
        if (dxState[type].highlighted >= 0) selectIcdCode(type, dxState[type].highlighted);
        else if (items.length === 1) selectIcdCode(type, 0);
    } else if (event.key === 'Escape') {
        closeDxDropdown(type);
    }
}

function debounceDiagnosisSave() {
    if (isPastView) return;
    clearTimeout(diagnosisSaveTimeout);
    diagnosisSaveTimeout = setTimeout(saveDiagnosis, 2000);
    document.getElementById('diagnosisSaveStatus').textContent = 'Unsaved changes...';
}

function saveDiagnosis() {
    if (isPastView || !currentConsultation) return;
    var statusEl = document.getElementById('diagnosisSaveStatus');
    statusEl.textContent = 'Saving...';
    fetch('consultation_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'save_diagnosis',
            consultation_id: currentConsultation.id,
            primary_icd10: dxState.primary.code || '',
            secondary_icd10: dxState.secondary.code || '',
            diagnosis_narrative: document.getElementById('diagnosisNarrative').value
        })
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success && d.diagnosis) {
            currentConsultation.primary_icd10 = d.diagnosis.primary_icd10;
            currentConsultation.primary_icd10_desc = d.diagnosis.primary_icd10_desc;
            currentConsultation.secondary_icd10 = d.diagnosis.secondary_icd10;
            currentConsultation.secondary_icd10_desc = d.diagnosis.secondary_icd10_desc;
            currentConsultation.diagnosis_narrative = d.diagnosis.diagnosis_narrative;
            statusEl.textContent = 'Saved!';
            setTimeout(function() { statusEl.textContent = ''; }, 2000);
        } else if (!d.success) {
            statusEl.textContent = d.message || 'Save failed';
        }
    }).catch(function() {
        statusEl.textContent = 'Save failed';
    });
}

document.addEventListener('click', function(e) {
    ['primary', 'secondary'].forEach(function(type) {
        var combo = document.getElementById(dxInputId(type));
        var dropdown = document.getElementById(dxDropdownId(type));
        if (combo && dropdown && !combo.contains(e.target) && !dropdown.contains(e.target)) {
            closeDxDropdown(type);
        }
    });
});

// ===================== X-RAY =====================
var xraysCache = [];

function populateXrays() {
    var uploadBtn = document.getElementById('xrayUploadBtn');
    if (uploadBtn) uploadBtn.style.display = isPastView ? 'none' : 'inline-flex';
    var statusEl = document.getElementById('xraySaveStatus');
    if (statusEl) statusEl.textContent = '';
    if (!currentConsultation || !currentConsultation.id) {
        xraysCache = [];
        renderXrays();
        return;
    }
    var grid = document.getElementById('xrayGrid');
    grid.innerHTML = '<span style="color:#94a3b8;font-size:0.82rem;">Loading x-rays...</span>';
    fetch('../shared/xray_actions.php?action=get&consultation_id=' + currentConsultation.id)
    .then(function(r) { return r.json(); })
    .then(function(d) {
        xraysCache = (d.success && d.xrays) ? d.xrays : [];
        renderXrays();
    })
    .catch(function() {
        showXrayError('Error loading x-rays.');
    });
}

function renderXrays() {
    var grid = document.getElementById('xrayGrid');
    if (!xraysCache.length) {
        grid.innerHTML = '<span style="color:#94a3b8;font-size:0.82rem;">No x-ray images uploaded yet.</span>';
        return;
    }
    var html = '';
    xraysCache.forEach(function(x) {
        var name = x.original_filename || x.file_path.split('/').pop();
        var dateStr = x.created_at ? new Date(x.created_at.replace(' ', 'T')).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' }) : '';
        html += '<div class="xray-card">'
            + (!isPastView ? '<button type="button" class="xray-delete-btn" title="Delete" onclick="deleteXrayFile(' + x.id + ')">&times;</button>' : '')
            + '<img class="xray-thumb" src="../../' + escapeHtml(x.file_path) + '" alt="' + escapeHtml(name) + '" onclick="window.open(this.src, \'_blank\')">'
            + '<div class="xray-card-info"><div class="xray-card-name" title="' + escapeHtml(name) + '">' + escapeHtml(name) + '</div>'
            + '<div class="xray-card-date">' + dateStr + '</div></div>'
            + '</div>';
    });
    grid.innerHTML = html;
}

function showXrayError(msg) {
    var grid = document.getElementById('xrayGrid');
    if (grid) grid.innerHTML = '<div style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;font-size:0.82rem;padding:10px 14px;border-radius:8px;grid-column:1/-1;">' + escapeHtml(msg) + '</div>';
}

function uploadXrays(inputEl) {
    if (isPastView || !currentConsultation || !inputEl.files || !inputEl.files.length) return;
    var statusEl = document.getElementById('xraySaveStatus');
    var files = Array.prototype.slice.call(inputEl.files);
    inputEl.value = '';
    var done = 0, failed = 0, succeeded = 0;

    if (files.length) {
        var grid = document.getElementById('xrayGrid');
        grid.innerHTML = '<span style="color:#94a3b8;font-size:0.82rem;">Uploading ' + files.length + ' file(s)...</span>';
    }

    files.forEach(function(file) {
        statusEl.textContent = 'Uploading ' + file.name + '...';
        var fd = new FormData();
        fd.append('action', 'upload');
        fd.append('consultation_id', currentConsultation.id);
        fd.append('xray_file', file);
        fetch('../shared/xray_actions.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.success) {
                failed++;
                showXrayError(d.message || 'Upload failed');
                statusEl.textContent = '';
                return d;
            }
            succeeded++;
            return d;
        })
        .catch(function() { failed++; })
        .then(function() {
            if (++done === files.length) {
                statusEl.textContent = '';
                if (succeeded > 0) {
                    populateXrays();
                } else if (!failed) {
                    renderXrays();
                }
            }
        });
    });
}

function deleteXrayFile(id) {
    if (isPastView) return;
    if (!confirm('Delete this x-ray image?')) return;
    var fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch('../shared/xray_actions.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) populateXrays();
        else alert(d.message || 'Delete failed');
    });
}

// ===================== PRESCRIPTION =====================
function populatePrescription() {
    var container = document.getElementById('prescriptionItems');
    var items = currentConsultation.prescription_data || [];
    if (items.length === 0) {
        container.innerHTML = '<p style="color:#94a3b8;font-size:0.88rem;margin-bottom:12px;">No medications added yet.</p>';
        return;
    }
    var html = '';
    items.forEach(function(item, idx) {
        html += '<div class="prescription-item-card">' +
            '<button class="remove-presc-btn" onclick="removePrescriptionItem(' + idx + ')">&times;</button>' +
            '<div class="presc-grid">' +
                '<div class="full-width"><label>Medication Name</label><input type="text" class="form-control" value="' + escapeHtml(item.name || '') + '" onchange="updatePrescItem(' + idx + ', \'name\', this.value)"></div>' +
                '<div><label>Dosage</label><input type="text" class="form-control" value="' + escapeHtml(item.dosage || '') + '" onchange="updatePrescItem(' + idx + ', \'dosage\', this.value)"></div>' +
                '<div><label>Frequency</label><input type="text" class="form-control" value="' + escapeHtml(item.frequency || '') + '" onchange="updatePrescItem(' + idx + ', \'frequency\', this.value)"></div>' +
                '<div><label>Duration</label><input type="text" class="form-control" value="' + escapeHtml(item.duration || '') + '" onchange="updatePrescItem(' + idx + ', \'duration\', this.value)"></div>' +
                '<div><label>Instructions</label><input type="text" class="form-control" value="' + escapeHtml(item.instructions || '') + '" onchange="updatePrescItem(' + idx + ', \'instructions\', this.value)"></div>' +
            '</div>' +
        '</div>';
    });
    container.innerHTML = html;
}

function addPrescriptionItem() {
    if (isPastView || !currentConsultation) return;
    if (!currentConsultation.prescription_data) currentConsultation.prescription_data = [];
    currentConsultation.prescription_data.push({ name: '', dosage: '', frequency: '', duration: '', instructions: '' });
    populatePrescription();
}

function removePrescriptionItem(idx) {
    currentConsultation.prescription_data.splice(idx, 1);
    populatePrescription();
}

function updatePrescItem(idx, field, value) {
    currentConsultation.prescription_data[idx][field] = value;
}

function savePrescription() {
    if (isPastView || !currentConsultation) return Promise.resolve({ success: false, message: 'Cannot save prescription' });
    if (!Array.isArray(currentConsultation.prescription_data)) {
        currentConsultation.prescription_data = [];
    }
    return fetch('consultation_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_prescription', consultation_id: currentConsultation.id, prescription_data: currentConsultation.prescription_data })
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            populatePrescription();
        } else {
            alert(d.message || 'Failed to save prescription');
        }
        return d;
    });
}

// ===================== MEDICAL CERTIFICATE =====================
function certificateSignatureSource() {
    var cert = (currentConsultation && currentConsultation.medical_certificate) || null;
    if (cert && typeof cert.dentist_signature === 'string' && cert.dentist_signature.indexOf('data:') === 0) {
        return cert.dentist_signature;
    }
    if (!isPastView && currentConsultation && typeof currentConsultation.dentist_signature === 'string' && currentConsultation.dentist_signature.indexOf('data:') === 0) {
        return currentConsultation.dentist_signature;
    }
    return '';
}

function populateCertificate() {
    if (!currentConsultation) return;
    var cert = currentConsultation.medical_certificate || {};
    var age = currentConsultation.patient_age ? (currentConsultation.patient_age + ' years old') : '';
    var bits = [currentConsultation.patient_name || '', age, currentConsultation.patient_gender || '', currentConsultation.patient_address || ''].filter(function(part) { return part; });
    var line = document.getElementById('certPatientLine');
    if (line) line.textContent = bits.join(' · ');
    document.getElementById('certPurpose').value = cert.purpose || 'Employment';
    document.getElementById('certIssuedAt').value = cert.issued_at || new Date().toISOString().split('T')[0];
    document.getElementById('certFindings').value = cert.findings || '';
    document.getElementById('certRecommendation').value = cert.recommendation || '';
    document.getElementById('certRestFrom').value = cert.rest_from || '';
    document.getElementById('certRestTo').value = cert.rest_to || '';
    document.getElementById('certRemarks').value = cert.remarks || '';
    var sig = certificateSignatureSource();
    var preview = document.getElementById('certSignaturePreview');
    if (sig) {
        preview.src = sig;
        preview.style.display = 'block';
    } else {
        preview.removeAttribute('src');
        preview.style.display = 'none';
    }
    document.getElementById('certDentistName').textContent = (cert.dentist_name || currentConsultation.dentist_name || rxDoctorName || '');
    var warning = document.getElementById('certSignatureWarning');
    if (warning) warning.style.display = sig ? 'none' : 'block';
    var status = document.getElementById('certSaveStatus');
    if (status) status.textContent = '';
}

function saveCertificate() {
    if (isPastView || !currentConsultation) return;
    var status = document.getElementById('certSaveStatus');
    if (status) {
        status.style.color = '#059669';
        status.textContent = 'Saving...';
    }
    fetch('consultation_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'save_certificate',
            consultation_id: currentConsultation.id,
            purpose: document.getElementById('certPurpose').value,
            findings: document.getElementById('certFindings').value,
            recommendation: document.getElementById('certRecommendation').value,
            rest_from: document.getElementById('certRestFrom').value,
            rest_to: document.getElementById('certRestTo').value,
            remarks: document.getElementById('certRemarks').value,
            issued_at: document.getElementById('certIssuedAt').value
        })
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d && d.success) {
            currentConsultation.medical_certificate = d.certificate || currentConsultation.medical_certificate;
            populateCertificate();
            if (status) status.textContent = 'Certificate saved.';
        } else {
            if (status) {
                status.style.color = '#b91c1c';
                status.textContent = (d && d.message) || 'Unable to save certificate.';
            }
        }
    }).catch(function() {
        if (status) {
            status.style.color = '#b91c1c';
            status.textContent = 'Unable to save certificate.';
        }
    });
}

function formatCertDate(value) {
    if (!value) return '';
    var parts = String(value).split('-');
    if (parts.length !== 3) return value;
    var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    if (isNaN(d.getTime())) return value;
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}

function printCertificate() {
    if (!currentConsultation) return;
    var cert = currentConsultation.medical_certificate || {};
    var sig = certificateSignatureSource();
    if (!sig) {
        alert('Save your signature in Profile & Settings before printing this certificate.');
        return;
    }
    var purpose = (document.getElementById('certPurpose').value || cert.purpose || 'Employment').trim();
    var findings = (document.getElementById('certFindings').value || cert.findings || '').trim();
    var recommendation = (document.getElementById('certRecommendation').value || cert.recommendation || '').trim();
    var remarks = (document.getElementById('certRemarks').value || cert.remarks || '').trim();
    var html = window.MedicalCertificateTemplate.documentHtml({
        purpose: purpose,
        findings: findings,
        recommendation: recommendation,
        remarks: remarks,
        issued_at: document.getElementById('certIssuedAt').value || cert.issued_at,
        rest_from: document.getElementById('certRestFrom').value || cert.rest_from,
        rest_to: document.getElementById('certRestTo').value || cert.rest_to,
        dentist_name: cert.dentist_name || currentConsultation.dentist_name || rxDoctorName || 'Dentist',
        dentist_signature: sig
    }, {
        name: currentConsultation.patient_name,
        age: currentConsultation.patient_age,
        gender: currentConsultation.patient_gender,
        address: currentConsultation.patient_address
    }, <?php echo json_encode($certificateLetterhead, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>);
    var printWindow = window.open('', '_blank');
    if (!printWindow) { alert('Please allow popups to print the certificate.'); return; }
    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();
    printWindow.focus();
    if (window.rfLogPrint) window.rfLogPrint('Medical Certificate', 'Medical certificate - ' + (currentConsultation.patient_name || '') + ' (' + purpose + ')', 'consultations', currentConsultation.id || null);
    window.MedicalCertificateTemplate.printWhenReady(printWindow);
}

function prescriptionSignatureHtml() {
    var sig = (!currentConsultation || typeof currentConsultation.dentist_signature !== 'string') ? '' : currentConsultation.dentist_signature;
    if (sig.indexOf('data:image/') !== 0) return '';
    return '<img src="' + sig + '" alt="Signature" style="display:block;max-height:64px;margin-bottom:4px;">';
}

// ===================== CONSENT =====================
function populateConsent() {
    var c = currentConsultation;
    currentConsentSignatureData = {
        patient: null,
        doctor: null,
        witness: null
    };
    document.getElementById('consentPatientNameInput').value = c.patient_name || '';
    document.getElementById('consentProviderInput').value = c.consent_provider || '';
    document.getElementById('consentAddressInput').value = c.consent_patient_address || '';
    document.getElementById('consentProcedureInput').value = c.consent_procedure || c.treatment_type || '';
    document.getElementById('consentGuardianCheck').checked = !!parseInt(c.consent_by_guardian);
    toggleGuardianName();
    document.getElementById('consentGuardianNameInput').value = c.consent_guardian_name || '';
    document.getElementById('consentDateInput').value = c.consent_date || new Date().toISOString().split('T')[0];
    document.getElementById('consentPlaceInput').value = c.consent_place || 'RF Dental Clinic';
    document.getElementById('consentPatientSignName').value = c.consent_patient_sign_name || '';
    document.getElementById('consentWitnessSignName').value = c.consent_witness_sign_name || '';
    if (c.consent_patient_signature) {
        document.getElementById('patientSigLine').textContent = 'Edit signature';
        document.getElementById('patientSigLine').classList.add('signed');
        if (typeof c.consent_patient_signature === 'string' && c.consent_patient_signature.startsWith('data:')) {
            currentConsentSignatureData.patient = c.consent_patient_signature;
            updateConsentSignaturePreview('patient');
        }
    }
    if (c.consent_witness_signature) {
        document.getElementById('witnessSigLine').textContent = 'Edit signature';
        document.getElementById('witnessSigLine').classList.add('signed');
        if (typeof c.consent_witness_signature === 'string' && c.consent_witness_signature.startsWith('data:')) {
            currentConsentSignatureData.witness = c.consent_witness_signature;
            updateConsentSignaturePreview('witness');
        }
    }
    var storedDoctorSig = (typeof c.consent_doctor_signature === 'string' && c.consent_doctor_signature.indexOf('data:') === 0) ? c.consent_doctor_signature : '';
    var savedDoctorSig = (!isPastView && typeof c.dentist_signature === 'string' && c.dentist_signature.indexOf('data:') === 0) ? c.dentist_signature : '';
    var doctorSigNote = document.getElementById('doctorSigSavedNote');
    if (storedDoctorSig) {
        document.getElementById('doctorSigLine').textContent = 'Edit signature';
        document.getElementById('doctorSigLine').classList.add('signed');
        currentConsentSignatureData.doctor = storedDoctorSig;
        if (doctorSigNote) doctorSigNote.textContent = '';
    } else if (savedDoctorSig) {
        document.getElementById('doctorSigLine').textContent = 'Edit signature';
        document.getElementById('doctorSigLine').classList.add('signed');
        currentConsentSignatureData.doctor = savedDoctorSig;
        if (doctorSigNote) doctorSigNote.textContent = 'Using your saved signature. Saving consent keeps this copy on the form.';
    } else {
        document.getElementById('doctorSigLine').textContent = 'Tap to sign';
        document.getElementById('doctorSigLine').classList.remove('signed');
        if (doctorSigNote) doctorSigNote.textContent = 'Set your signature in Profile & Settings so it appears here.';
    }
    if (c.dentist_name) {
        document.getElementById('consentDoctorSignName').value = c.dentist_name;
    }

    updateConsentSignaturePreview('patient');
    updateConsentSignaturePreview('doctor');
    updateConsentSignaturePreview('witness');
}

function toggleGuardianName() {
    document.getElementById('guardianNameWrapper').style.display = document.getElementById('consentGuardianCheck').checked ? 'block' : 'none';
}

function openConsentSignatureModal(type) {
    if (isPastView) return;
    currentConsentSignatureType = type;
    var modal = document.getElementById('consentSignatureModal');
    var title = document.getElementById('consentSignatureModalTitle');
    title.textContent = 'Draw ' + (type === 'patient' ? 'Patient / Guardian' : type === 'doctor' ? 'Doctor' : 'Witness') + ' Signature';
    var canvas = document.getElementById('consentSignatureCanvas');
    modal.classList.add('active');
    if (type === 'patient' || type === 'witness') {
        resizeConsentSignatureCanvas();
    }
    if (!signaturePadInstance) {
        signaturePadInstance = new SignaturePad(canvas, {
            backgroundColor: 'rgb(255,255,255)',
            penColor: 'rgb(15, 23, 42)'
        });
    }
    signaturePadInstance.clear();
    if (currentConsentSignatureData[type]) {
        signaturePadInstance.fromDataURL(currentConsentSignatureData[type]);
    }
}

function closeConsentSignatureModal() {
    var modal = document.getElementById('consentSignatureModal');
    modal.classList.remove('active');
}

function clearConsentSignatureCanvas() {
    if (signaturePadInstance) {
        signaturePadInstance.clear();
    }
    if (currentConsentSignatureType) {
        currentConsentSignatureData[currentConsentSignatureType] = null;
    }
}

function resizeConsentSignatureCanvas() {
    var canvas = document.getElementById('consentSignatureCanvas');
    if (!canvas) return;
    var ratio = Math.max(window.devicePixelRatio || 1, 1);
    var rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * ratio;
    canvas.height = rect.height * ratio;
    var context = canvas.getContext('2d');
    if (context) {
        context.scale(ratio, ratio);
    }
    if (signaturePadInstance && !signaturePadInstance.isEmpty()) {
        var data = signaturePadInstance.toData();
        signaturePadInstance.clear();
        signaturePadInstance.fromData(data);
    }
}

function updateConsentSignaturePreview(type) {
    var preview = document.getElementById(type + 'SigPreview');
    if (!preview) return;
    var data = currentConsentSignatureData[type];
    if (data && typeof data === 'string' && data.indexOf('data:') === 0) {
        preview.innerHTML = '<img src="' + data + '" alt="Signature">';
    } else {
        preview.innerHTML = '<div class="sig-preview empty">No signature drawn yet</div>';
    }
}

function saveConsentSignature() {
    if (!currentConsentSignatureType || !signaturePadInstance) return;
    if (signaturePadInstance.isEmpty()) {
        alert('Please draw your signature before saving.');
        return;
    }

    var signatureData = signaturePadInstance.toDataURL();
    currentConsentSignatureData[currentConsentSignatureType] = signatureData;
    updateConsentSignaturePreview(currentConsentSignatureType);
    var lineId = currentConsentSignatureType === 'patient' ? 'patientSigLine' : currentConsentSignatureType === 'doctor' ? 'doctorSigLine' : 'witnessSigLine';
    var line = document.getElementById(lineId);
    if (line) {
        line.textContent = 'Edit signature';
        line.classList.add('signed');
    }
    closeConsentSignatureModal();
}

function signConsent(type) {
    if (isPastView) return;
    if (type === 'patient') {
        openConsentSignatureModal('patient');
    } else if (type === 'doctor') {
        openConsentSignatureModal('doctor');
    } else if (type === 'witness') {
        openConsentSignatureModal('witness');
    }
}

function saveConsent() {
    if (isPastView || !currentConsultation) return;
    var payload = {
        action: 'save_consent',
        consultation_id: currentConsultation.id,
        consent_provider: document.getElementById('consentProviderInput').value,
        consent_patient_address: document.getElementById('consentAddressInput').value,
        consent_procedure: document.getElementById('consentProcedureInput').value,
        consent_by_guardian: document.getElementById('consentGuardianCheck').checked ? 1 : 0,
        consent_guardian_name: document.getElementById('consentGuardianNameInput').value,
        consent_given: document.getElementById('patientSigLine').classList.contains('signed') ? 1 : 0,
        consent_date: document.getElementById('consentDateInput').value,
        consent_place: document.getElementById('consentPlaceInput').value,
        consent_patient_signature: currentConsentSignatureData.patient || (document.getElementById('patientSigLine').classList.contains('signed') ? 'signed' : null),
        consent_doctor_signature: currentConsentSignatureData.doctor || (document.getElementById('doctorSigLine').classList.contains('signed') ? 'signed' : null),
        consent_witness_signature: currentConsentSignatureData.witness || (document.getElementById('witnessSigLine').classList.contains('signed') ? 'signed' : null),
        consent_patient_sign_name: document.getElementById('consentPatientSignName').value,
        consent_witness_sign_name: document.getElementById('consentWitnessSignName').value,
        consent_patient_signature_data: currentConsentSignatureData.patient,
        consent_doctor_signature_data: currentConsentSignatureData.doctor,
        consent_witness_signature_data: currentConsentSignatureData.witness
    };
    fetch('consultation_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d && d.success) {
            showConsentSavedModal();
        } else {
            alert((d && d.message) || 'Consent saved');
        }
    });
}

function showConsentSavedModal() {
    var modal = document.getElementById('consentSavedModal');
    if (!modal) return;
    document.body.appendChild(modal);
    modal.classList.add('active');
}

function closeConsentSavedModal() {
    var modal = document.getElementById('consentSavedModal');
    if (modal) modal.classList.remove('active');
}

function tpFormatApptDate(dateStr) {
    if (!dateStr) return '';
    var parts = dateStr.split('-');
    var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    return d.toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
}

function showTreatmentPlanSavedModal(message, sessions) {
    var modal = document.getElementById('treatmentPlanSavedModal');
    if (!modal) return;

    var titleEl = document.getElementById('tpSavedModalTitle');
    var messageEl = document.getElementById('tpSavedModalMessage');
    var listEl = document.getElementById('tpSavedApptList');
    var noteEl = document.getElementById('tpSavedApptNote');

    var isUpdate = (message || '').toLowerCase().indexOf('updated') !== -1;
    if (titleEl) titleEl.textContent = isUpdate ? 'Treatment Plan Updated' : 'Treatment Plan Created';
    if (messageEl) messageEl.textContent = message || (isUpdate ? 'Treatment plan updated.' : 'Treatment plan created.');

    if (listEl) {
        var items = sessions || [];
        if (items.length > 0) {
            var html = '';
            items.forEach(function(session, idx) {
                var dateLabel = tpFormatApptDate(session.date);
                var timeLabel = session.time ? tpFormatTime12h(session.time) : '';
                var when = [dateLabel, timeLabel].filter(Boolean).join(' @ ');
                html += '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;' + (idx < items.length - 1 ? 'margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid #e2e8f0;' : '') + '">';
                html += '<div>';
                html += '<div style="font-size:0.78rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:3px;">Session ' + (idx + 1) + '</div>';
                html += '<div style="font-size:0.95rem;font-weight:600;color:#1e293b;">' + escapeHtml(when) + '</div>';
                if (session.treatment) {
                    html += '<div style="font-size:0.8rem;color:#059669;margin-top:2px;">' + escapeHtml(session.treatment) + '</div>';
                }
                html += '</div></div>';
            });
            listEl.innerHTML = html;
            listEl.style.display = 'block';
            if (noteEl) noteEl.style.display = 'flex';
        } else {
            listEl.innerHTML = '';
            listEl.style.display = 'none';
            if (noteEl) noteEl.style.display = 'none';
        }
    }

    document.body.appendChild(modal);
    modal.classList.add('active');
}

function closeTreatmentPlanSavedModal() {
    var modal = document.getElementById('treatmentPlanSavedModal');
    if (modal) modal.classList.remove('active');
}

function printConsentForm() {
    document.body.classList.add('print-panel-consent');
    document.body.classList.remove('print-panel-prescription');
    var consentTabButton = document.querySelector('.consult-tab-btn[data-tab="consent"]');
    if (consentTabButton) {
        consentTabButton.click();
    }
    if (window.rfLogPrint) window.rfLogPrint('Consent Form', 'Consent form - ' + ((typeof currentConsultation !== 'undefined' && currentConsultation && currentConsultation.patient_name) || ''), 'consultations', (typeof currentConsultation !== 'undefined' && currentConsultation && currentConsultation.id) || null);
    setTimeout(function() {
        window.print();
        document.body.classList.remove('print-panel-consent');
    }, 100);
}

var rxClinicName = <?php echo json_encode($clinicName); ?>;
var rxClinicDetails = <?php echo json_encode($rxClinicDetails); ?>;
var rxDoctorName = <?php echo json_encode($rxDoctorName); ?>;

function collectPrescriptionFromForm() {
    var items = [];
    document.querySelectorAll('#prescriptionItems .prescription-item-card').forEach(function(card) {
        var inputs = card.querySelectorAll('input.form-control');
        items.push({
            name: inputs[0] ? inputs[0].value.trim() : '',
            dosage: inputs[1] ? inputs[1].value : '',
            frequency: inputs[2] ? inputs[2].value : '',
            duration: inputs[3] ? inputs[3].value : '',
            instructions: inputs[4] ? inputs[4].value : ''
        });
    });
    if (items.length > 0) {
        currentConsultation.prescription_data = items;
    }
}

function printPrescription() {
    if (!currentConsultation) return;
    collectPrescriptionFromForm();
    var items = currentConsultation.prescription_data || [];

    var medItems = '';
    var instructions = [];
    var anyMed = false;
    items.forEach(function(item) {
        if (!item || !(item.name || '').trim()) return;
        anyMed = true;
        var line = escapeHtml(item.name.trim());
        var parts = [];
        if ((item.dosage || '').trim()) parts.push(escapeHtml(item.dosage.trim()));
        if ((item.frequency || '').trim()) parts.push(escapeHtml(item.frequency.trim()));
        if ((item.duration || '').trim()) parts.push(escapeHtml(item.duration.trim()));
        if (parts.length) line += ' (' + parts.join(', ') + ')';
        medItems += '<div style="font-family:\'Courier New\',monospace;font-size:14px;padding:4px 0 4px 24px;position:relative;line-height:1.5;">&#8478; ' + line + '</div>';
        if ((item.instructions || '').trim()) instructions.push(escapeHtml(item.instructions.trim()));
    });
    if (!anyMed) {
        medItems = '<div style="padding-left:0;">No medications prescribed</div>';
    }
    var instructionsText = instructions.length ? instructions.join('. ') : 'None';
    var patientName = currentConsultation.patient_name || '';
    var dateText = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    var diagnosis = currentConsultation.treatment_type || '';

    var html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Prescription</title>' +
        '<style>' +
        '@page { margin: 0.5in; }' +
        'body { font-family: Georgia, "Times New Roman", serif; margin: 0; padding: 0; color: #111827; }' +
        '.rx-paper { max-width: 680px; margin: 0 auto; border: 2px solid #1e293b; padding: 36px 32px 28px; position: relative; }' +
        '.rx-header { text-align: center; border-bottom: 2px solid #1e293b; padding-bottom: 14px; margin-bottom: 22px; }' +
        '.rx-symbol { font-size: 52px; font-weight: 700; color: #1e293b; text-align: center; letter-spacing: -4px; margin-bottom: 6px; line-height: 1; }' +
        '.rx-clinic-name { font-size: 18px; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.06em; }' +
        '.rx-clinic-details { font-size: 11px; color: #64748b; margin-top: 4px; }' +
        '.rx-divider { border: none; border-top: 1px dashed #94a3b8; margin: 16px 0; }' +
        '.rx-info-row { display: flex; margin-bottom: 10px; font-size: 14px; }' +
        '.rx-info-label { width: 100px; font-weight: 600; color: #475569; flex-shrink: 0; }' +
        '.rx-info-value { flex: 1; color: #0f172a; }' +
        '.rx-instructions-box { margin: 12px 0; padding: 12px 16px; background: #f8fafc; border-left: 3px solid #94a3b8; font-size: 13px; color: #475569; }' +
        '.rx-footer { margin-top: 28px; border-top: 2px solid #e2e8f0; padding-top: 18px; }' +
        '.rx-signature-row { margin-top: 20px; display: flex; align-items: center; }' +
        '.rx-signature-line { display: inline-block; width: 260px; border-bottom: 1px solid #0f172a; margin-left: 12px; }' +
        '.rx-bottom-bar { text-align: center; font-size: 10px; color: #94a3b8; margin-top: 22px; padding-top: 10px; border-top: 1px solid #e2e8f0; }' +
        '@media print { .rx-paper { border: none; padding: 0; } .rx-paper .rx-header { border-bottom-color: #000; } .rx-paper .rx-divider { border-top-color: #000; } .rx-paper .rx-footer { border-top-color: #000; } .rx-paper .rx-bottom-bar { border-top-color: #000; } }' +
        '</style></head><body>' +
        '<div class="rx-paper">' +
        '<div class="rx-header"><div class="rx-symbol">&#8478;</div><div class="rx-clinic-name">' + escapeHtml(rxClinicName) + '</div><div class="rx-clinic-details">' + escapeHtml(rxClinicDetails) + '</div></div>' +
        '<div class="rx-info-row"><span class="rx-info-label">Patient:</span><span class="rx-info-value">' + escapeHtml(patientName) + '</span></div>' +
        '<div class="rx-info-row"><span class="rx-info-label">Date:</span><span class="rx-info-value">' + escapeHtml(dateText) + '</span></div>' +
        '<div class="rx-info-row"><span class="rx-info-label">Diagnosis:</span><span class="rx-info-value">' + escapeHtml(diagnosis) + '</span></div>' +
        '<hr class="rx-divider">' +
        '<div>' + medItems + '</div>' +
        '<div class="rx-instructions-box"><strong>Instructions:</strong> ' + escapeHtml(instructionsText) + '</div>' +
        '<div class="rx-footer">' +
        '<div class="rx-info-row"><span class="rx-info-label">Prescriber:</span><span class="rx-info-value">' + escapeHtml(rxDoctorName) + '</span></div>' +
        '<div class="rx-signature-row"><span class="rx-info-label">Signature:</span><span>' + prescriptionSignatureHtml() + '<span class="rx-signature-line"></span></span></div>' +
        '</div>' +
        '<div class="rx-bottom-bar">' + escapeHtml(rxClinicName) + ' &middot; ' + escapeHtml(rxClinicDetails) + '</div>' +
        '</div>' +
        '</body></html>';

    var printWindow = window.open('', '_blank');
    if (!printWindow) { alert('Please allow popups to print the prescription.'); return; }
    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();
    printWindow.focus();
    if (window.rfLogPrint) window.rfLogPrint('Prescription', 'Prescription - ' + patientName, 'consultations', currentConsultation.id || null);
    printWindow.print();
}

// ===================== COMPLETE CONSULTATION =====================
function completeConsultation() {
    if (!currentConsultation) return;
    showCompleteConsultModal();
}

function showCompleteConsultModal() {
    var modal = document.getElementById('completeConsultModal');
    document.body.appendChild(modal);
    modal.classList.add('active');
}

function closeCompleteConsultModal() {
    document.getElementById('completeConsultModal').classList.remove('active');
}

function confirmCompleteConsult() {
    closeCompleteConsultModal();
    savePrescription().then(function(result) {
        if (result && result.success) {
            fetch('consultation_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'complete_consultation', consultation_id: currentConsultation.id })
            }).then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showConsultationCompletedModal(data);
                } else {
                    alert('Error: ' + (data.message || 'Failed to complete'));
                }
            });
        } else {
            alert('Please save the prescription before completing the consultation.');
        }
    });
}

function showConsultationCompletedModal(data) {
    var modal = document.getElementById('consultationCompletedModal');
    document.body.appendChild(modal);
    var total = document.getElementById('ccTotalAmount');
    if (total) total.textContent = '\u20B1' + (parseFloat(data.total_amount || 0)).toLocaleString('en-PH', { minimumFractionDigits: 2 });
    var balance = document.getElementById('ccBalance');
    if (balance) balance.textContent = '\u20B1' + (parseFloat(data.balance || 0)).toLocaleString('en-PH', { minimumFractionDigits: 2 });
    var statusEl = document.getElementById('ccPaymentStatus');
    if (statusEl) {
        var s = (data.payment_status || 'Unpaid').toString();
        statusEl.textContent = s.charAt(0).toUpperCase() + s.slice(1).toLowerCase();
        var lower = s.toLowerCase();
        if (lower === 'paid') { statusEl.style.background = '#d1fae5'; statusEl.style.color = '#065f46'; }
        else if (lower === 'partial') { statusEl.style.background = '#fef9c3'; statusEl.style.color = '#854d0e'; }
        else { statusEl.style.background = '#fef3c7'; statusEl.style.color = '#92400e'; }
    }
    modal.classList.add('active');
}

function closeConsultationCompletedModal() {
    var modal = document.getElementById('consultationCompletedModal');
    if (modal) {
        modal.classList.remove('active');
    }
    window.location.href = 'dentist_dashboard.php';
}

// ===================== PAST CONSULTATION VIEW =====================
function loadPastConsultationData(consultId) {
    fetch('consultation_actions.php?action=get_past_consultation&id=' + consultId)
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success && data.consultation) {
            currentConsultation = data.consultation;
            if (!Array.isArray(currentConsultation.prescription_data)) {
                currentConsultation.prescription_data = [];
            }
            teethFindings = data.consultation.teeth_data || [];
            document.getElementById('consultEmptyState').style.display = 'none';
            document.getElementById('consultPanels').style.display = 'block';
            populateDentalExam();
            populateVitals();
            populateNotes();
            populateHistory();
            populateDiagnosis();
            populateXrays();
            populatePrescription();
            populateConsent();
            populateCertificate();
            renderToothFindings();
            renderToothButtons();
            loadPastConsultations(data.consultation.patient_id, data.consultation.id);
            var tpLabel = document.getElementById('tpPatientLabel');
            if (tpLabel) tpLabel.textContent = data.consultation.patient_name;
            resetConsultTpForm();
            loadConsultTreatmentPlans();
            applyReadOnlyMode();
        } else {
            alert('Consultation not found.');
        }
    });
}

function applyReadOnlyMode() {
    document.querySelectorAll('#consultPanels select').forEach(function(el) { el.disabled = true; });
    document.querySelectorAll('#consultPanels input[type="text"]').forEach(function(el) { el.readOnly = true; });
    document.querySelectorAll('#consultPanels input[type="date"]').forEach(function(el) { el.readOnly = true; });
    document.querySelectorAll('#consultPanels textarea').forEach(function(el) { el.readOnly = true; });
    document.querySelectorAll('#consultPanels input[type="checkbox"]').forEach(function(el) { el.disabled = true; });
    var saveBtns = document.querySelectorAll('#panel-prescription .consult-complete-btn, #panel-consent .consult-complete-btn, #panel-treatment-plan .consult-complete-btn, #certSaveBtn');
    saveBtns.forEach(function(b) { b.style.display = 'none'; });
    document.querySelectorAll('#panel-treatment-plan .sess-btn').forEach(function(el) { el.disabled = true; });
    if (document.getElementById('notesSaveStatus')) document.getElementById('notesSaveStatus').textContent = '';
    if (document.getElementById('historySaveStatus')) document.getElementById('historySaveStatus').textContent = '';
    if (document.getElementById('diagnosisSaveStatus')) document.getElementById('diagnosisSaveStatus').textContent = '';
}

// ===================== PAST CONSULTATIONS =====================
function togglePastConsultations() {
    var toggle = document.getElementById('pastConsultToggle');
    var list = document.getElementById('pastConsultList');
    toggle.classList.toggle('open');
    list.classList.toggle('open');
}

function loadPastConsultations(patientId, currentConsultId) {
    if (!patientId) return;
    fetch('consultation_actions.php?action=get_patient_consultation_history&patient_id=' + patientId + '&exclude_consultation_id=' + (currentConsultId || 0))
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var section = document.getElementById('pastConsultationsSection');
        var list = document.getElementById('pastConsultList');
        var countEl = document.getElementById('pastConsultCount');

        if (!data.success || !data.consultations || data.consultations.length === 0) {
            section.style.display = 'none';
            return;
        }

        var consults = data.consultations;
        countEl.textContent = consults.length;
        section.style.display = 'block';

        var html = '';
        consults.forEach(function(c, idx) {
            var date = c.created_at ? new Date(c.created_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
            var time = c.created_at ? new Date(c.created_at).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' }) : '';
            var treatment = c.queue_treatment_type || c.consent_procedure || 'General Consultation';
            var dentist = c.dentist_name || 'Unknown';

            html += '<div class="past-consult-card" id="pastCard_' + idx + '">';
            html += '<div class="past-consult-card-header" onclick="togglePastCard(' + idx + ')">';
            html += '<div class="card-left">';
            html += '<span class="card-date">' + date + ' <span style="color:#64748b;font-weight:400;font-size:0.78rem;">' + time + '</span></span>';
            html += '<span class="card-treatment">' + escapeHtml(treatment) + '</span>';
            html += '<span class="card-dentist">Dr. ' + escapeHtml(dentist) + '</span>';
            html += '</div>';
            html += '<span class="card-arrow">▼</span>';
            html += '</div>';

            html += '<div class="past-consult-card-body">';

            // Dental Exam Summary
            var examFields = [];
            if (c.periodontal_screening) examFields.push('Periodontal: ' + c.periodontal_screening);
            if (c.occlusion_molar_class) examFields.push('Molar: ' + c.occlusion_molar_class);
            else if (parseInt(c.occlusion_class_molar)) examFields.push('Molar: previously checked; class unspecified');
            if (c.occlusion_notes) examFields.push('Occlusion notes: ' + c.occlusion_notes);
            if (parseInt(c.occlusion_overjet)) examFields.push('Overjet');
            if (parseInt(c.occlusion_overbite)) examFields.push('Overbite');
            if (parseInt(c.occlusion_midline_deviation)) examFields.push('Midline Deviation');
            if (parseInt(c.occlusion_crossbite)) examFields.push('Crossbite');
            if (parseInt(c.appliances_orthodontic)) examFields.push('Orthodontic');
            if (parseInt(c.appliances_skyplate)) examFields.push('Skyplate');
            if (parseInt(c.appliances_others)) examFields.push('Appliances: ' + (c.appliances_others_text || 'Other'));
            if (parseInt(c.tmd_clenching)) examFields.push('TMD: Clenching');
            if (parseInt(c.tmd_clicking)) examFields.push('TMD: Clicking');
            if (parseInt(c.tmd_trismus)) examFields.push('TMD: Trismus');
            if (parseInt(c.tmd_muscle_spasm)) examFields.push('TMD: Muscle Spasm');

            if (examFields.length > 0) {
                html += '<div class="past-consult-detail-group">';
                html += '<div class="detail-label">Dental Examination</div>';
                html += '<div class="detail-value">' + escapeHtml(examFields.join(', ')) + '</div>';
                html += '</div>';
            }

            // Teeth Findings
            var teethData = c.teeth_data || [];
            if (teethData.length > 0) {
                html += '<div class="past-consult-detail-group">';
                html += '<div class="detail-label">Tooth Findings (' + teethData.length + ')</div>';
                html += '<div class="past-teeth-chips">';
                teethData.forEach(function(f) {
                    var label = '#' + f.tooth;
                    if (f.surface) label += ' [' + f.surface + ']';
                    label += ' ' + f.code;
                    html += '<span class="past-teeth-chip ' + (f.type || '') + '">' + escapeHtml(label) + '</span>';
                });
                html += '</div></div>';
            }

            // Notes
            if (c.consultation_notes && c.consultation_notes.trim()) {
                html += '<div class="past-consult-detail-group">';
                html += '<div class="detail-label">Consultation Notes</div>';
                html += '<div class="detail-value">' + escapeHtml(c.consultation_notes) + '</div>';
                html += '</div>';
            }

            // Dental History Notes
            if (c.dental_medical_history_notes && c.dental_medical_history_notes.trim()) {
                html += '<div class="past-consult-detail-group">';
                html += '<div class="detail-label">Dental History Notes</div>';
                html += '<div class="detail-value">' + escapeHtml(c.dental_medical_history_notes) + '</div>';
                html += '</div>';
            }

            // Prescription
            var presc = c.prescription_data || [];
            if (presc.length > 0) {
                html += '<div class="past-consult-detail-group">';
                html += '<div class="detail-label">Prescription</div>';
                presc.forEach(function(p) {
                    html += '<div class="past-prescription-item">';
                    html += '<div class="med-name">' + escapeHtml(p.name || 'Unnamed') + '</div>';
                    var details = [];
                    if (p.dosage) details.push(p.dosage);
                    if (p.frequency) details.push(p.frequency);
                    if (p.duration) details.push(p.duration);
                    if (details.length) html += '<div class="med-detail">' + escapeHtml(details.join(' · ')) + '</div>';
                    if (p.instructions) html += '<div class="med-detail" style="font-style:italic;">' + escapeHtml(p.instructions) + '</div>';
                    html += '</div>';
                });
                html += '</div>';
            }

            // Consent
            if (c.consent_procedure || c.consent_date) {
                html += '<div class="past-consult-detail-group">';
                html += '<div class="detail-label">Consent</div>';
                var consentParts = [];
                if (c.consent_procedure) consentParts.push('Procedure: ' + c.consent_procecdure);
                if (c.consent_provider) consentParts.push('Provider: ' + c.consent_provider);
                if (c.consent_date) consentParts.push('Date: ' + c.consent_date);
                if (c.consent_place) consentParts.push('Place: ' + c.consent_place);
                html += '<div class="detail-value">' + escapeHtml(consentParts.join(' · ')) + '</div>';
                html += '</div>';
            }

            html += '<div class="past-consult-view-full"><a href="dentist_consultation.php?past_consultation_id=' + c.id + '" title="Open full read-only view of this consultation">🔍 View Full</a></div>';

            html += '</div></div>';
        });

        list.innerHTML = html;
    });
}

function togglePastCard(idx) {
    var card = document.getElementById('pastCard_' + idx);
    if (card) card.classList.toggle('expanded');
}

// ===================== TREATMENT PLAN =====================
function tpMoney(value) {
    return Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function tpCostCents(value) {
    var amount = Number(value);
    return Number.isFinite(amount) && amount >= 0 ? Math.round(amount * 100) : 0;
}

function tpCostSubtotal() {
    var cents = 0;
    document.querySelectorAll('#tpSessionRowsContainer .session-row').forEach(function(row) {
        if (row.querySelector('.session-treatment').value) cents += tpCostCents(row.dataset.estimatedCost);
    });
    return cents / 100;
}

function tpShowCostAdjustment() {
    var adjustment = (tpCostCents(document.getElementById('tp_estimated_cost').value) - tpCostCents(tpCostSubtotal())) / 100;
    document.getElementById('tpCostAdjustment').textContent = adjustment ? 'Total adjustment: ' + (adjustment < 0 ? '-₱' : '+₱') + tpMoney(Math.abs(adjustment)) : '';
}

function tpUpdateCostTotal() {
    document.getElementById('tp_estimated_cost').value = tpCostSubtotal().toFixed(2);
    tpShowCostAdjustment();
}

function tpRenderCostBreakdown() {
    var container = document.getElementById('tpCostBreakdown');
    container.innerHTML = '';
    document.querySelectorAll('#tpSessionRowsContainer .session-row').forEach(function(row) {
        var select = row.querySelector('.session-treatment');
        if (!select.value) return;
        var custom = row.querySelector('.session-other-treatment input');
        var name = select.value === '__other__' ? (custom.value.trim() || 'Custom treatment') : select.value;
        var line = document.createElement('div');
        line.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:8px;';
        var label = document.createElement('label');
        label.style.cssText = 'flex:1;font-size:0.875rem;';
        label.textContent = name + ' (Session ' + row.dataset.session + ') — ₱';
        var input = document.createElement('input');
        input.type = 'number'; input.min = '0'; input.step = '0.01'; input.required = true;
        input.name = 'session_estimated_cost_' + row.dataset.session;
        input.id = 'tpSessionCost_' + row.dataset.session;
        input.value = (tpCostCents(row.dataset.estimatedCost) / 100).toFixed(2);
        input.disabled = isPastView;
        input.style.cssText = 'width:110px;padding:8px;border:1px solid #d1d5db;border-radius:6px;';
        label.htmlFor = input.id;
        input.addEventListener('input', function() {
            row.dataset.estimatedCost = this.value;
            tpUpdateCostTotal();
        });
        line.appendChild(label); line.appendChild(input); container.appendChild(line);
    });
    if (!container.children.length) container.textContent = 'Select treatments to see the cost breakdown.';
    tpUpdateCostTotal();
}

function tpBindTreatmentCost(row, existing) {
    var select = row.querySelector('.session-treatment');
    var custom = row.querySelector('.session-other-treatment input');
    function defaultCost() {
        var service = (window._cachedServices || []).find(function(s) { return s.name === select.value; });
        row.dataset.estimatedCost = (tpCostCents(service ? service.price : 0) / 100).toFixed(2);
    }
    if (existing && existing.estimated_cost != null) row.dataset.estimatedCost = existing.estimated_cost;
    else defaultCost();
    select.addEventListener('change', function() { defaultCost(); tpRenderCostBreakdown(); });
    custom.addEventListener('input', function() { tpRenderCostBreakdown(); });
}

function loadConsultTreatmentPlans() {
    if (!currentConsultation || !currentConsultation.patient_id) return;
    var container = document.getElementById('tpExistingPlansContainer');
    if (!container) return;
    container.innerHTML = '<p style="color:#94a3b8;font-size:0.875rem;">Loading...</p>';

    fetch('../shared/process_treatment_plan.php?patient_id=' + currentConsultation.patient_id)
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success && data.plans && data.plans.length > 0) {
            renderConsultPlans(data.plans);
            var countEl = document.getElementById('tpPlanCount');
            if (countEl) {
                countEl.textContent = data.plans.length + ' plan' + (data.plans.length !== 1 ? 's' : '');
                countEl.style.display = 'inline';
            }
        } else {
            container.innerHTML = '<p style="color:#94a3b8;font-size:0.875rem;font-style:italic;">No treatment plans yet for this patient.</p>';
            var countEl2 = document.getElementById('tpPlanCount');
            if (countEl2) countEl2.style.display = 'none';
        }
    })
    .catch(function(err) {
        console.error('Error loading treatment plans:', err);
        container.innerHTML = '<p style="color:#dc2626;font-size:0.875rem;">Error loading plans.</p>';
    });
}

function renderConsultPlans(plans) {
    window._consultTreatmentPlans = plans;
    var container = document.getElementById('tpExistingPlansContainer');
    container.innerHTML = '';
    plans.forEach(function(plan) {
        var card = document.createElement('div');
        card.className = 'tp-plan-card';

        var statusClass = (plan.status || 'active').toLowerCase().replace(/[\s_]+/g, '-');
        var statusLabel = statusClass === 'in-progress' ? 'In Progress' : statusClass.charAt(0).toUpperCase() + statusClass.slice(1);

        var sessionsHtml = '';
        if (plan.sessions_data) {
            try {
                var sessions = JSON.parse(plan.sessions_data);
                if (sessions.length > 0) {
                    sessionsHtml = '<div class="tp-plan-sessions">';
                    sessions.forEach(function(s, idx) {
                        var parts = [];
                        if (s.date) {
                            var d = new Date(s.date + 'T' + (s.time || '00:00:00'));
                            parts.push(d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }));
                        }
                        if (s.time) {
                            parts.push(tpFormatTime12h(s.time));
                        }
                        var sessionStatus = s.status === 'Completed' ? 'complete' : (s.status === 'In-Progress' ? 'in progress' : (s.status === 'Waiting' ? 'waiting' : (s.status === 'Cancelled' ? 'cancelled' : 'scheduled')));
                        sessionsHtml += '<div class="tp-plan-session-item"><span>Session ' + (idx + 1) + ' ' + sessionStatus + ':</span> ';
                        sessionsHtml += parts.length ? parts.join(' @ ') : '<span style="color:#9ca3af;">Not scheduled</span>';
                        if (s.treatment) {
                            sessionsHtml += ' <span class="tp-plan-treatment-label">— ' + escapeHtml(s.treatment) + '</span>';
                        }
                        if (s.estimated_cost != null) sessionsHtml += ' — ₱' + tpMoney(s.estimated_cost);
                        if (!isPastView && s.appointment_id && (s.status || 'Scheduled') === 'Scheduled' && statusClass !== 'cancelled') {
                            sessionsHtml += ' <button type="button" onclick="tpOpenReschedule(' + Number(plan.id) + ',' + (idx + 1) + ')" style="margin-left:8px;">Reschedule</button>';
                            sessionsHtml += ' <button type="button" onclick="tpCancelScheduled(' + Number(plan.id) + ',' + (idx + 1) + ')">Cancel session</button>';
                        }
                        sessionsHtml += '</div>';
                        if (s.notes) {
                            sessionsHtml += '<div class="tp-plan-notes">📝 ' + escapeHtml(s.notes) + '</div>';
                        }
                    });
                    sessionsHtml += '</div>';
                }
            } catch(e) {}
        }

        var costHtml = '';
        if (plan.total_estimated_cost && parseFloat(plan.total_estimated_cost) > 0) {
            costHtml = ' | Est. Cost: ₱' + parseFloat(plan.total_estimated_cost).toLocaleString('en-PH', { minimumFractionDigits: 2 });
        }
        if (Array.isArray(sessions) && sessions.length && sessions.every(function(s) { return s.estimated_cost != null; }) && plan.total_estimated_cost != null) {
            var subtotalCents = sessions.reduce(function(sum, s) { return sum + tpCostCents(s.estimated_cost); }, 0);
            var adjustment = (tpCostCents(plan.total_estimated_cost) - subtotalCents) / 100;
            if (adjustment) costHtml += ' | Adjustment: ' + (adjustment < 0 ? '-₱' : '+₱') + tpMoney(Math.abs(adjustment));
        }

        card.innerHTML =
            '<div class="tp-plan-card-header">' +
                '<span class="tp-plan-name">' + escapeHtml(plan.treatment_name || plan.plan_name || 'Treatment Plan') + '</span>' +
                '<span class="tp-plan-status ' + statusClass + '">' + escapeHtml(statusLabel) + '</span>' +
            '</div>' +
            '<div class="tp-plan-meta">' + (plan.total_sessions || 1) + ' session(s)' + costHtml +
            (plan.teeth_numbers ? ' | Teeth: ' + escapeHtml(plan.teeth_numbers) : '') +
            '</div>' +
            (plan.notes ? '<div class="tp-plan-notes">📝 ' + escapeHtml(plan.notes) + '</div>' : '') +
            sessionsHtml +
            (!isPastView && statusClass !== 'completed' && statusClass !== 'cancelled' ? '<div style="margin-top:12px;"><button type="button" onclick="tpCancelScheduled(' + Number(plan.id) + ',0)">Cancel remaining plan sessions</button></div>' : '');

        container.appendChild(card);
    });
}

function tpFormatTime12h(time24) {
    var parts = time24.split(':');
    var hours = parseInt(parts[0]);
    var minutes = parseInt(parts[1]);
    var period = hours >= 12 ? 'PM' : 'AM';
    var hour12 = hours % 12 || 12;
    return hour12 + ':' + (minutes < 10 ? '0' : '') + minutes + ' ' + period;
}

function tpChangeSchedule(payload) {
    return fetch('../shared/treatment_plan_actions.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
    }).then(function(response) { return response.json(); }).then(function(data) {
        if (!data.success) throw new Error(data.message || 'Unable to update the session');
        loadConsultTreatmentPlans();
        return data;
    });
}

function tpCancelScheduled(planId, sessionNumber) {
    if (isPastView || !confirm(sessionNumber ? 'Permanently cancel Session ' + sessionNumber + '?' : 'Permanently cancel all remaining scheduled sessions in this plan? Completed sessions will stay in the record.')) return;
    tpChangeSchedule({ action: sessionNumber ? 'cancel_session' : 'cancel_plan', plan_id: planId, session_number: sessionNumber })
        .catch(function(error) { alert(error.message); });
}

function tpOpenReschedule(planId, sessionNumber) {
    if (isPastView) return;
    var plan = (window._consultTreatmentPlans || []).find(function(p) { return Number(p.id) === planId; });
    if (!plan) return;
    var sessions = JSON.parse(plan.sessions_data || '[]');
    var session = sessions[sessionNumber - 1];
    if (!session) return;
    var dialog = document.createElement('dialog');
    dialog.style.cssText = 'border:0;border-radius:12px;padding:24px;width:420px;max-width:90vw;box-shadow:0 16px 48px #0003;';
    dialog.innerHTML = '<form><h3 style="margin-top:0;">Reschedule Session ' + sessionNumber + '</h3>' +
        '<p>' + escapeHtml(session.treatment || 'Treatment') + '</p>' +
        '<label for="tpRescheduleDate">Date</label><input id="tpRescheduleDate" type="date" required style="display:block;width:100%;margin:8px 0 16px;padding:10px;">' +
        '<label for="tpRescheduleTime">Available time</label><select id="tpRescheduleTime" required style="display:block;width:100%;margin:8px 0 16px;padding:10px;"></select>' +
        '<p id="tpRescheduleMessage" role="status" style="color:#64748b;font-size:0.85rem;"></p>' +
        '<div style="display:flex;justify-content:flex-end;gap:8px;"><button type="button" id="tpRescheduleClose">Close</button><button type="submit" id="tpRescheduleSave">Save schedule</button></div></form>';
    document.body.appendChild(dialog);
    var date = dialog.querySelector('#tpRescheduleDate');
    var time = dialog.querySelector('#tpRescheduleTime');
    var message = dialog.querySelector('#tpRescheduleMessage');
    var save = dialog.querySelector('#tpRescheduleSave');
    date.min = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Shanghai', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
    date.value = session.date;
    function loadSlots() {
        var requestedDate = date.value;
        save.disabled = true; time.innerHTML = '<option value="">Loading...</option>';
        message.textContent = '';
        var url = '../user-patient/get_slots.php?date=' + encodeURIComponent(requestedDate) + '&exclude_appointment_id=' + Number(session.appointment_id);
        if (session.service_id) url += '&service_id=' + Number(session.service_id);
        fetch(url).then(function(r) { return r.json(); }).then(function(data) {
            if (date.value !== requestedDate || !dialog.isConnected) return;
            time.innerHTML = '<option value="">Select time</option>';
            if (!data.success || !data.is_open || data.is_past) { message.textContent = data.message || 'No available times'; return; }
            (data.available_slots || []).forEach(function(slot) {
                var option = document.createElement('option'); option.value = slot; option.textContent = tpFormatTime12h(slot); time.appendChild(option);
            });
            if (requestedDate === session.date) time.value = session.time.slice(0, 5);
            save.disabled = !(data.available_slots || []).length;
            if (save.disabled) message.textContent = 'No available times on this date.';
        }).catch(function() { message.textContent = 'Unable to load available times.'; });
    }
    date.addEventListener('change', loadSlots);
    dialog.querySelector('#tpRescheduleClose').onclick = function() { dialog.close(); };
    dialog.addEventListener('close', function() { dialog.remove(); });
    dialog.querySelector('form').onsubmit = function(event) {
        event.preventDefault(); save.disabled = true; message.textContent = 'Saving...';
        tpChangeSchedule({ action: 'reschedule_session', plan_id: planId, session_number: sessionNumber, date: date.value, time: time.value })
            .then(function() { dialog.close(); }).catch(function(error) { message.textContent = error.message; save.disabled = false; });
    };
    dialog.showModal(); loadSlots();
}

function tpSetSessionCount(n, existingSessions) {
    if (!existingSessions) {
        existingSessions = Array.from(document.querySelectorAll('#tpSessionRowsContainer .session-row')).map(function(row) {
            var select = row.querySelector('.session-treatment');
            return {
                date: row.querySelector('.session-date').value,
                time: row.querySelector('.session-time-hidden').value,
                treatment: select.value === '__other__' ? row.querySelector('.session-other-treatment input').value : select.value,
                notes: row.querySelector('.session-notes').value,
                estimated_cost: row.dataset.estimatedCost
            };
        });
    }
    document.getElementById('tp_total_sessions').value = n;
    document.querySelectorAll('#tpSessionCountBtns .sess-btn').forEach(function(btn) {
        btn.classList.toggle('active', parseInt(btn.getAttribute('data-count')) === n);
    });

    var container = document.getElementById('tpSessionRowsContainer');
    container.innerHTML = '';

    for (var i = 1; i <= n; i++) {
        var existing = existingSessions && existingSessions[i - 1];
        var row = document.createElement('div');
        row.className = 'session-row';
        row.setAttribute('data-session', i);

        if (existing && existing.date && existing.time) {
            tpBuildTransitionedRow(row, i, existing);
        } else {
            tpBuildInitialRow(row, i);
        }

        container.appendChild(row);
        if (existing && (!existing.date || !existing.time)) {
            var select = row.querySelector('.session-treatment');
            if (existing.treatment) {
                var matched = Array.from(select.options).some(function(option) { return option.value === existing.treatment; });
                select.value = matched ? existing.treatment : '__other__';
                row.querySelector('.session-other-treatment').style.display = matched ? 'none' : 'block';
                if (!matched) row.querySelector('.session-other-treatment input').value = existing.treatment;
                row.querySelector('.session-date').disabled = false;
            }
            row.querySelector('.session-notes').value = existing.notes || '';
            row.querySelector('.session-date').value = existing.date || '';
            if (existing.date) tpLoadSessionTimeSlots(existing.date, i, row.querySelector('.session-time-slots'), row.querySelector('.session-time-hidden'));
        }
        tpBindTreatmentCost(row, existing);
    }
    tpRenderCostBreakdown();
}

function tpBuildInitialRow(row, i) {
    row.innerHTML = '<div class="session-row-header">Session ' + i + '</div>';

    var hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'session_time_' + i;
    hiddenInput.id = 'tpSessionTime_' + i;
    hiddenInput.className = 'session-time-hidden';
    hiddenInput.value = '';

    var content = document.createElement('div');
    content.className = 'session-row-content';

    var leftCol = document.createElement('div');
    leftCol.className = 'session-left';
    var leftInner = document.createElement('div');
    leftInner.className = 'session-left-inner';

    var treatmentField = document.createElement('div');
    treatmentField.className = 'session-field';
    var treatLabel = document.createElement('label');
    treatLabel.className = 'session-field-label';
    treatLabel.textContent = 'Treatment';
    treatmentField.appendChild(treatLabel);
    var treatSelect = document.createElement('select');
    treatSelect.name = 'session_treatment_' + i;
    treatSelect.className = 'form-control session-treatment';
    treatmentField.appendChild(treatSelect);
    var otherDiv = document.createElement('div');
    otherDiv.className = 'session-other-treatment';
    otherDiv.style.display = 'none';
    var otherInput = document.createElement('input');
    otherInput.type = 'text';
    otherInput.name = 'session_treatment_other_' + i;
    otherInput.className = 'form-control';
    otherInput.placeholder = 'Enter custom treatment name';
    otherDiv.appendChild(otherInput);
    treatmentField.appendChild(otherDiv);

    var notesField = document.createElement('div');
    notesField.className = 'session-field';
    var notesLabel = document.createElement('label');
    notesLabel.className = 'session-field-label';
    notesLabel.textContent = 'Notes';
    notesField.appendChild(notesLabel);
    var notesTextarea = document.createElement('textarea');
    notesTextarea.name = 'session_notes_' + i;
    notesTextarea.className = 'form-control session-notes';
    notesTextarea.rows = 2;
    notesTextarea.placeholder = 'Notes for this session...';
    notesField.appendChild(notesTextarea);

    leftInner.appendChild(treatmentField);
    leftInner.appendChild(notesField);
    leftCol.appendChild(leftInner);

    var rightCol = document.createElement('div');
    rightCol.className = 'session-right';
    var rightInner = document.createElement('div');
    rightInner.className = 'session-right-inner';

    var dateInput = document.createElement('input');
    dateInput.type = 'date';
    dateInput.name = 'session_date_' + i;
    dateInput.className = 'form-control session-date';
    dateInput.min = new Date().toISOString().split('T')[0];
    dateInput.disabled = true;
    rightInner.appendChild(dateInput);

    var timeWrapper = document.createElement('div');
    timeWrapper.className = 'session-time-wrapper';
    var slotContainer = document.createElement('div');
    slotContainer.className = 'session-time-slots';
    slotContainer.id = 'tpSessionSlots_' + i;
    timeWrapper.appendChild(slotContainer);
    rightInner.appendChild(timeWrapper);

    rightCol.appendChild(rightInner);

    content.appendChild(leftCol);
    content.appendChild(rightCol);
    row.appendChild(hiddenInput);
    row.appendChild(content);

    tpPopulateSessionTreatmentDropdown(treatSelect);

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
        var date = this.value;
        if (date) {
            tpLoadSessionTimeSlots(date, i, slotContainer, hiddenInput);
        } else {
            slotContainer.innerHTML = '';
            hiddenInput.value = '';
        }
    });
}

function tpBuildTransitionedRow(row, i, existing) {
    row.innerHTML = '<div class="session-row-header">Session ' + i + '</div>';

    var content = document.createElement('div');
    content.className = 'session-row-content';

    var hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'session_time_' + i;
    hiddenInput.id = 'tpSessionTime_' + i;
    hiddenInput.className = 'session-time-hidden';
    hiddenInput.value = existing.time || '';

    var leftCol = document.createElement('div');
    leftCol.className = 'session-left';
    var leftInner = document.createElement('div');
    leftInner.className = 'session-left-inner';

    var treatmentField = document.createElement('div');
    treatmentField.className = 'session-field';
    var treatLabel = document.createElement('label');
    treatLabel.className = 'session-field-label';
    treatLabel.textContent = 'Treatment';
    treatmentField.appendChild(treatLabel);

    var treatSelect = document.createElement('select');
    treatSelect.name = 'session_treatment_' + i;
    treatSelect.className = 'form-control session-treatment';
    treatmentField.appendChild(treatSelect);

    var otherDiv = document.createElement('div');
    otherDiv.className = 'session-other-treatment';
    otherDiv.style.display = 'none';
    var otherInput = document.createElement('input');
    otherInput.type = 'text';
    otherInput.name = 'session_treatment_other_' + i;
    otherInput.className = 'form-control';
    otherInput.placeholder = 'Enter custom treatment name';
    otherDiv.appendChild(otherInput);
    treatmentField.appendChild(otherDiv);

    var notesField = document.createElement('div');
    notesField.className = 'session-field';
    var notesLabel = document.createElement('label');
    notesLabel.className = 'session-field-label';
    notesLabel.textContent = 'Notes';
    notesField.appendChild(notesLabel);
    var notesTextarea = document.createElement('textarea');
    notesTextarea.name = 'session_notes_' + i;
    notesTextarea.className = 'form-control session-notes';
    notesTextarea.rows = 2;
    notesTextarea.placeholder = 'Notes for this session...';
    if (existing.notes) notesTextarea.value = existing.notes;
    notesField.appendChild(notesTextarea);

    leftInner.appendChild(treatmentField);
    leftInner.appendChild(notesField);
    leftCol.appendChild(leftInner);

    var rightCol = document.createElement('div');
    rightCol.className = 'session-right';
    var rightInner = document.createElement('div');
    rightInner.className = 'session-right-inner';

    var dateInput = document.createElement('input');
    dateInput.type = 'date';
    dateInput.name = 'session_date_' + i;
    dateInput.className = 'form-control session-date';
    dateInput.min = new Date().toISOString().split('T')[0];
    dateInput.value = existing.date || '';
    rightInner.appendChild(dateInput);

    var timeDisplay = document.createElement('div');
    timeDisplay.className = 'session-selected-time';
    if (existing.time) {
        var label = document.createElement('span');
        label.className = 'session-time-label';
        label.textContent = '\uD83D\uDD50 ' + tpFormatTimeRange(existing.time, existing.treatment || '');
        timeDisplay.appendChild(label);
    }
    var changeBtn = document.createElement('button');
    changeBtn.type = 'button';
    changeBtn.className = 'session-change-time';
    changeBtn.textContent = 'Change';
    changeBtn.onclick = function() { tpDeselectTimeSlot(row); };
    timeDisplay.appendChild(changeBtn);
    var timeWrapper = document.createElement('div');
    timeWrapper.className = 'session-time-wrapper';
    timeWrapper.appendChild(timeDisplay);
    rightInner.appendChild(timeWrapper);
    rightCol.appendChild(rightInner);

    content.appendChild(leftCol);
    content.appendChild(rightCol);
    row.appendChild(hiddenInput);
    row.appendChild(content);

    tpPopulateSessionTreatmentDropdown(treatSelect);
    if (existing.treatment) {
        var matched = false;
        for (var oi = 0; oi < treatSelect.options.length; oi++) {
            if (treatSelect.options[oi].value === existing.treatment) {
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
        tpUpdateTimeDisplay(row);
    });
    otherInput.addEventListener('input', function() {
        if (treatSelect.value === '__other__') {
            tpUpdateTimeDisplay(row);
        }
    });

    dateInput.addEventListener('change', function() {
        if (this.value !== existing.date) {
            tpDeselectTimeSlot(row);
        }
    });
}

function tpSelectTimeSlot(row, timeStr) {
    var hiddenInput = row.querySelector('.session-time-hidden');
    var timeWrapper = row.querySelector('.session-time-wrapper');
    if (!timeWrapper) return;

    hiddenInput.value = timeStr;
    timeWrapper.innerHTML = '';

    var timeDisplay = document.createElement('div');
    timeDisplay.className = 'session-selected-time';
    var label = document.createElement('span');
    label.className = 'session-time-label';
    label.textContent = '\uD83D\uDD50 ' + tpFormatTimeRange(timeStr, '');
    timeDisplay.appendChild(label);
    var changeBtn = document.createElement('button');
    changeBtn.type = 'button';
    changeBtn.className = 'session-change-time';
    changeBtn.textContent = 'Change';
    changeBtn.onclick = function() { tpDeselectTimeSlot(row); };
    timeDisplay.appendChild(changeBtn);
    timeWrapper.appendChild(timeDisplay);
}

function tpDeselectTimeSlot(row) {
    var hiddenInput = row.querySelector('.session-time-hidden');
    var timeWrapper = row.querySelector('.session-time-wrapper');
    var dateInput = row.querySelector('.session-date');
    var sessionNum = row.getAttribute('data-session');
    if (!timeWrapper) return;

    hiddenInput.value = '';
    timeWrapper.innerHTML = '';

    var slotContainer = document.createElement('div');
    slotContainer.className = 'session-time-slots';
    slotContainer.id = 'tpSessionSlots_' + sessionNum;
    timeWrapper.appendChild(slotContainer);

    var currentDate = dateInput.value;
    if (currentDate) {
        tpLoadSessionTimeSlots(currentDate, sessionNum, slotContainer, hiddenInput);
    }
}

function tpPopulateSessionTreatmentDropdown(selectEl) {
    selectEl.innerHTML = '<option value="">Select Treatment</option>';
    var services = window._cachedServices || [];
    services.forEach(function(s) {
        var opt = document.createElement('option');
        opt.value = s.name;
        opt.textContent = s.name + (s.duration_minutes ? ' (' + s.duration_minutes + ' min)' : '');
        selectEl.appendChild(opt);
    });
    var otherOpt = document.createElement('option');
    otherOpt.value = '__other__';
    otherOpt.textContent = 'Others';
    selectEl.appendChild(otherOpt);
}

function tpLoadSessionTimeSlots(date, sessionNum, container, hiddenInput) {
    var url = '../user-patient/get_slots.php?date=' + encodeURIComponent(date);
    container.innerHTML = '<span style="color:#6b7280;font-size:0.8rem;">Loading...</span>';

    fetch(url)
    .then(function(res) { return res.json(); })
    .then(function(data) {
        container.innerHTML = '';

        if (!data.success || !data.is_open || data.is_past) {
            container.innerHTML = '<span style="color:#dc2626;font-size:0.8rem;">' + (data.message || 'Unavailable') + '</span>';
            return;
        }

        var available = data.available_slots || [];
        if (available.length === 0) {
            container.innerHTML = '<span style="color:#6b7280;font-size:0.8rem;">No available slots</span>';
            return;
        }

        var bookedSet = new Set(data.booked_slots || []);
        var allSlots = (data.available_slots || []).concat(data.booked_slots || []);
        allSlots.sort();
        var seen = {};
        allSlots.forEach(function(slot) {
            if (seen[slot]) return;
            seen[slot] = true;
            var isBooked = bookedSet.has(slot);
            var btn = document.createElement('button');
            btn.type = 'button';
            if (isBooked) {
                btn.className = 'time-slot-btn booked';
                btn.disabled = true;
                btn.title = 'Already booked';
            } else {
                btn.className = 'time-slot-btn';
                btn.onclick = (function(s) {
                    return function() {
                        tpSelectTimeSlot(container.closest('.session-row'), s);
                    };
                })(slot);
            }
            btn.textContent = tpFormatTime12h(slot);
            container.appendChild(btn);
        });
    })
    .catch(function() {
        container.innerHTML = '<span style="color:#dc2626;font-size:0.8rem;">Error loading slots</span>';
    });
}

function tpFormatTimeRange(startTime, treatmentName) {
    var duration = 30;
    if (treatmentName) {
        var services = window._cachedServices || [];
        for (var si = 0; si < services.length; si++) {
            if (services[si].name === treatmentName && services[si].duration_minutes) {
                duration = parseInt(services[si].duration_minutes);
                break;
            }
        }
    }
    var timeParts = startTime.split(':');
    var h = parseInt(timeParts[0]);
    var m = parseInt(timeParts[1]);
    var totalMin = h * 60 + m + duration;
    var endH = Math.floor(totalMin / 60) % 24;
    var endM = totalMin % 60;
    var endStr = (endH < 10 ? '0' : '') + endH + ':' + (endM < 10 ? '0' : '') + endM;
    return tpFormatTime12h(startTime) + ' - ' + tpFormatTime12h(endStr);
}

function tpUpdateTimeDisplay(row) {
    var label = row.querySelector('.session-time-label');
    var hiddenInput = row.querySelector('.session-time-hidden');
    if (!label || !hiddenInput || !hiddenInput.value) return;
    var treatSelect = row.querySelector('.session-treatment');
    var treatment = treatSelect ? treatSelect.value : '';
    var otherInput = row.querySelector('.session-other-treatment input');
    var treatmentName = treatment === '__other__' ? (otherInput ? otherInput.value : '') : treatment;
    label.textContent = '\uD83D\uDD50 ' + tpFormatTimeRange(hiddenInput.value, treatmentName);
}

function saveConsultTreatmentPlan(e) {
    e.preventDefault();
    if (isPastView || !currentConsultation) return;

    var form = document.getElementById('consultTreatmentPlanForm');
    var formData = new FormData(form);

    formData.set('patient_id', currentConsultation.patient_id);

    var totalSessions = parseInt(document.getElementById('tp_total_sessions').value) || 0;
    for (var i = 1; i <= totalSessions; i++) {
        var dateInput = document.querySelector('#tpSessionRowsContainer [name="session_date_' + i + '"]');
        var timeInput = document.getElementById('tpSessionTime_' + i);
        var treatmentSelect = document.querySelector('#tpSessionRowsContainer [name="session_treatment_' + i + '"]');
        var treatmentOther = document.querySelector('#tpSessionRowsContainer [name="session_treatment_other_' + i + '"]');
        var treatment = treatmentSelect ? treatmentSelect.value : '';
        var treatmentOtherVal = treatmentOther ? treatmentOther.value.trim() : '';
        var treatmentName = treatment === '__other__' ? treatmentOtherVal : treatment;

        if (!treatmentName) {
            alert('Please choose a treatment for Session ' + i + '.');
            return;
        }
        if (!dateInput || !dateInput.value) {
            alert('Please select a date for Session ' + i + '.');
            return;
        }
        if (!timeInput || !timeInput.value) {
            alert('Please select a time for Session ' + i + '.');
            return;
        }

        formData.set('session_date_' + i, dateInput.value);
        formData.set('session_time_' + i, timeInput.value);
        formData.set('session_treatment_' + i, treatment);
        if (treatment === '__other__') {
            formData.set('session_treatment_other_' + i, treatmentOtherVal);
        }
    }

    var firstDate = document.querySelector('#tpSessionRowsContainer [name="session_date_1"]');
    var firstTime = document.getElementById('tpSessionTime_1');
    if (firstDate && firstDate.value) {
        formData.set('next_session_date', firstDate.value);
        formData.set('next_session_time', firstTime ? firstTime.value : '');
    }

    var saveBtn = document.getElementById('tpSaveBtn');
    if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving...'; }

    fetch('../shared/process_treatment_plan.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Treatment Plan'; }
        if (data.success) {
            showTreatmentPlanSavedModal(data.message, (data.sessions && data.sessions.length) ? data.sessions : (data.appointments || []));
            resetConsultTpForm();
            loadConsultTreatmentPlans();
        } else {
            alert(data.message);
        }
    })
    .catch(function(error) {
        console.error('Error saving treatment plan:', error);
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Treatment Plan'; }
        alert('Error saving treatment plan');
    });
}

function resetConsultTpForm() {
    document.getElementById('tp_plan_id').value = '';
    document.getElementById('tp_teeth_numbers').value = '';
    document.getElementById('tp_notes').value = '';
    document.getElementById('tp_estimated_cost').value = '';
    document.getElementById('tp_status').value = 'Active';
    tpSetSessionCount(1, []);
}

// ===================== HELPERS =====================
function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ===================== INIT =====================
document.addEventListener('DOMContentLoaded', function() {
    var modalOverlay = document.getElementById('toothModalOverlay');
    if (modalOverlay) document.body.appendChild(modalOverlay);
    window._cachedServices = <?php echo json_encode($services); ?>;
    renderToothButtons();
    <?php if ($isPastView && $pastConsultData): ?>
    loadPastConsultationData(<?php echo $pastConsultId; ?>);
    <?php elseif ($initialQueueId): ?>
    var initialChip = document.querySelector('.consult-queue-chip[data-queue-id="<?php echo $initialQueueId; ?>"]');
    if (initialChip) {
        loadConsultation(<?php echo $initialQueueId; ?>);
    }
    <?php endif; ?>
});
</script>

<?php require_once '../../includes/dentist_layout_end.php'; ?>
