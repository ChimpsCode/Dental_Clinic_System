<?php
$pageTitle = 'Prescriptions';
require_once '../../config/database.php';
require_once '../../includes/staff_layout_start.php';

function ensurePrescriptionTableColumnsForListing(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $columns = [];
        $stmt = $pdo->query('SHOW COLUMNS FROM prescriptions');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['Field'];
        }

        $alterStatements = [];
        if (!in_array('doctor_id', $columns, true)) {
            $alterStatements[] = 'ALTER TABLE prescriptions ADD COLUMN doctor_id INT(11) NULL';
        }
        if (!in_array('medications', $columns, true)) {
            $alterStatements[] = 'ALTER TABLE prescriptions ADD COLUMN medications TEXT NULL';
        }
        if (!in_array('instructions', $columns, true)) {
            $alterStatements[] = 'ALTER TABLE prescriptions ADD COLUMN instructions TEXT NULL';
        }
        if (!in_array('created_at', $columns, true)) {
            $alterStatements[] = 'ALTER TABLE prescriptions ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';
        }
        if (!in_array('updated_at', $columns, true)) {
            $alterStatements[] = 'ALTER TABLE prescriptions ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
        }

        foreach ($alterStatements as $sql) {
            $pdo->exec($sql);
        }
    } catch (Exception $e) {
        error_log('Failed to ensure prescriptions listing columns: ' . $e->getMessage());
    }
}

$filterPatientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$filterPatientName = '';

try {
    require_once '../../config/database.php';
    ensurePrescriptionTableColumnsForListing($pdo);

    if ($filterPatientId) {
        $nameStmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM patients WHERE id = ?");
        $nameStmt->execute([$filterPatientId]);
        $filterPatientName = $nameStmt->fetchColumn();
    }

    $whereClause = $filterPatientId ? "WHERE pr.patient_id = " . (int)$filterPatientId : "";
    $stmt = $pdo->query("
        SELECT pr.*, 
               CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) AS age,
               CONCAT(u.first_name, ' ', u.last_name) AS doctor_name
        FROM prescriptions pr
        LEFT JOIN patients p ON pr.patient_id = p.id
        LEFT JOIN users u ON pr.doctor_id = u.id
        $whereClause
        ORDER BY pr.issue_date DESC, pr.created_at DESC
        LIMIT 20
    ");
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $todayStmt = $pdo->query("SELECT COUNT(*) FROM prescriptions WHERE DATE(created_at) = CURDATE()");
    $todayCount = $todayStmt->fetchColumn();

} catch (Exception $e) {
    $prescriptions = [];
    $todayCount = 0;
}

// Fetch clinic settings for Rx print
$clinicName = 'RF Dental Clinic';
$clinicAddress = '';
$clinicPhone = '';
try {
    $settingsResult = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $settingsResult->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'clinic_name') $clinicName = $row['setting_value'];
        if ($row['setting_key'] === 'clinic_address') $clinicAddress = $row['setting_value'];
        if ($row['setting_key'] === 'clinic_phone') $clinicPhone = $row['setting_value'];
    }
} catch (Exception $e) {}
?>

<link rel="stylesheet" href="../../assets/css/rx_pad.css?v=2">
<style>
.page-header {
    margin-bottom: 24px;
}
.page-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #0f172a;
    margin: 0 0 4px 0;
}
.breadcrumbs {
    font-size: 0.875rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 6px;
}
.stats-grid {
    display: flex;
    gap: 20px;
    margin-bottom: 0px;
    padding-bottom: 25px;
}
.stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    flex: 1;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.stat-icon {
    width: 56px;
    height: 56px;
    background: #f0f9ff;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #475569;
}
.stat-info {
    display: flex;
    flex-direction: column;
}
.stat-number {
    font-size: 2rem;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.1;
}
.stat-label {
    font-size: 0.875rem;
    color: #64748b;
    font-weight: 400;
    margin-top: 4px;
}
.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 0px;
    padding-bottom: 25px;
    position: relative;
    z-index: 10;
}
.search-input-container {
    position: relative;
    flex: 1;
    max-width: 480px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    display: flex;
    align-items: center;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.search-icon {
    position: absolute;
    left: 16px;
    color: #94a3b8;
    display: flex;
}
.search-input-container input {
    width: 100%;
    padding: 12px 16px 12px 44px;
    border: none;
    font-size: 0.95rem;
    color: #334155;
    outline: none;
    background: transparent;
}
.search-input-container input::placeholder {
    color: #94a3b8;
}
.content-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 60px 40px;
    text-align: center;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.empty-illustration {
    margin-bottom: 24px;
    display: flex;
    justify-content: center;
}
.empty-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 8px;
}
.empty-subtitle {
    font-size: 0.95rem;
    color: #64748b;
    margin-bottom: 24px;
}
.table-card-container {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.prescriptions-table table {
    width: 100%;
    border-collapse: collapse;
}
.prescriptions-table th {
    background: #f8fafc;
    padding: 16px 20px;
    text-align: left;
    font-weight: 600;
    color: #475569;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.prescriptions-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: top;
    font-size: 0.9rem;
}
.prescriptions-table tr:last-child td {
    border-bottom: none;
}
.medications-cell {
    max-width: 300px;
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    background: #f0fdf4;
    padding: 8px 12px;
    border-radius: 6px;
    border-left: 3px solid #10b981;
    line-height: 1.4;
}
.prescription-actions { position: relative; }
.kebab-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 10px;
    border-radius: 50%;
    color: #6b7280;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    font-size: 20px;
    line-height: 1;
    width: 40px;
    height: 40px;
}
.kebab-btn:hover { background-color: #f3f4f6; color: #374151; }
.kebab-btn.active { background-color: #e5e7eb; color: #111827; }
.kebab-menu {
    display: none;
    position: fixed;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    min-width: 180px;
    z-index: 99999;
    overflow: hidden;
    animation: kebabFadeIn 0.15s ease;
}
.kebab-menu.show { display: block; }
@keyframes kebabFadeIn {
    from { opacity: 0; transform: scale(0.95) translateY(-4px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
.kebab-menu button {
    width: 100%;
    text-align: left;
    padding: 10px 16px;
    border: none;
    background: none;
    font-size: 0.9rem;
    color: #374151;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.15s ease;
    white-space: nowrap;
}
.kebab-menu button:hover { background-color: #f9fafb; color: #111827; }
.kebab-menu button:first-child { border-radius: 8px 8px 0 0; }
.kebab-menu button:last-child { border-radius: 0 0 8px 8px; }

/* View Modal */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6) !important;
    justify-content: center;
    align-items: center;
    z-index: 999999 !important;
    box-sizing: border-box;
    padding: 20px;
}
.modal-overlay.active {
    display: flex;
}
.modal {
    background: white;
    width: 100%;
    max-width: 600px;
    padding: 24px;
    border-radius: 16px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    z-index: 1000000;
    margin: auto;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e5e7eb;
}
.modal-header h2 {
    margin: 0;
    font-size: 1.25rem;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
}
.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #6b7280;
    cursor: pointer;
    padding: 4px 8px;
    line-height: 1;
    border-radius: 4px;
    transition: background 0.2s, color 0.2s;
}
.modal-close:hover {
    background: #f3f4f6;
    color: #ef4444;
}
.modal-body {
    padding: 0;
}
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
    padding-top: 16px;
    border-top: 1px solid #e5e7eb;
}
.view-field {
    margin-bottom: 20px;
}
.view-field label {
    display: block;
    font-weight: 600;
    color: #475569;
    font-size: 0.85rem;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.view-field .value {
    padding: 10px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #f8fafc;
    color: #0f172a;
    font-size: 0.95rem;
    line-height: 1.5;
    white-space: pre-wrap;
}
.view-field .value.medications-value {
    font-family: 'Courier New', monospace;
    background: #f0fdf4;
    border-left: 3px solid #10b981;
}
.view-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.btn-secondary {
    padding: 10px 16px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 6px;
    cursor: pointer;
    color: #334155;
    font-weight: 500;
    font-size: 0.9rem;
    transition: background 0.2s;
}
.btn-secondary:hover {
    background: #eef2f6;
}
.btn-cancel {
    padding: 10px 16px;
    border: 1px solid #e2e8f0;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    color: #334155;
    font-weight: 500;
    font-size: 0.9rem;
}
.btn-cancel:hover {
    background: #f9fafb;
}
.btn-print {
    background: #eef2ff;
    color: #4338ca;
    border: 1px solid #c7d2fe;
    padding: 10px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.9rem;
    transition: background 0.2s;
}
.btn-print:hover {
    background: #e0e7ff;
}

/* Rx Paper styles */
.rx-paper {
    max-width: 680px;
    margin: 0 auto;
    background: #fff;
    border: 2px solid #1e293b;
    padding: 36px 32px 28px;
    font-family: 'Georgia', 'Times New Roman', serif;
    position: relative;
}
.rx-paper .rx-header {
    text-align: center;
    border-bottom: 2px solid #1e293b;
    padding-bottom: 14px;
    margin-bottom: 22px;
}
.rx-paper .rx-symbol {
    font-size: 52px;
    font-weight: 700;
    color: #1e293b;
    text-align: center;
    letter-spacing: -4px;
    margin-bottom: 6px;
    line-height: 1;
}
.rx-paper .rx-clinic-name {
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.rx-paper .rx-clinic-details {
    font-size: 11px;
    color: #64748b;
    margin-top: 4px;
}
.rx-paper .rx-divider {
    border: none;
    border-top: 1px dashed #94a3b8;
    margin: 16px 0;
}
.rx-paper .rx-info-row {
    display: flex;
    margin-bottom: 10px;
    font-size: 14px;
}
.rx-paper .rx-info-label {
    width: 100px;
    font-weight: 600;
    color: #475569;
    flex-shrink: 0;
}
.rx-paper .rx-info-value {
    flex: 1;
    color: #0f172a;
}
.rx-paper .rx-medications {
    margin: 14px 0;
}
.rx-paper .rx-med-item {
    font-family: 'Courier New', monospace;
    font-size: 14px;
    padding: 4px 0 4px 24px;
    position: relative;
    line-height: 1.5;
}
.rx-paper .rx-med-item::before {
    content: '\211E';
    position: absolute;
    left: 0;
    font-size: 16px;
    font-weight: 700;
}
.rx-paper .rx-instructions-box {
    margin: 12px 0;
    padding: 12px 16px;
    background: #f8fafc;
    border-left: 3px solid #94a3b8;
    font-size: 13px;
    color: #475569;
}
.rx-paper .rx-footer {
    margin-top: 28px;
    border-top: 2px solid #e2e8f0;
    padding-top: 18px;
}
.rx-paper .rx-signature-row {
    margin-top: 20px;
    display: flex;
    align-items: center;
}
.rx-paper .rx-signature-line {
    display: inline-block;
    width: 260px;
    border-bottom: 1px solid #0f172a;
    margin-left: 12px;
}
.rx-paper .rx-bottom-bar {
    text-align: center;
    font-size: 10px;
    color: #94a3b8;
    margin-top: 22px;
    padding-top: 10px;
    border-top: 1px solid #e2e8f0;
}
.modal-rx {
    max-width: 800px !important;
}
@media print {
    .rx-paper { border: none; padding: 0; }
    .rx-paper .rx-header { border-bottom-color: #000; }
    .rx-paper .rx-divider { border-top-color: #000; }
    .rx-paper .rx-footer { border-top-color: #000; }
    .rx-paper .rx-bottom-bar { border-top-color: #000; }
}

@media (max-width: 768px) {
    .view-grid {
        grid-template-columns: 1fr;
    }
    .stats-grid {
        flex-direction: column;
    }
}
</style>

<div class="page-header">
    
    <?php if ($filterPatientId): ?>
        <div style="margin-top: 12px;">
            <a href="staff_prescriptions.php" style="color: #2563eb; text-decoration: none; font-size: 0.9rem;">&larr; Show all prescriptions</a>
        </div>
    <?php endif; ?>
</div>

<div class="summary-cards" style="grid-template-columns: repeat(2, 1fr);">
    <div class="summary-card card-prescriptions">
        <div class="summary-icon">
            <img src="../../assets/icons/prescription.png" alt="Total Prescriptions">
        </div>
        <div class="summary-info">
            <h3><?php echo count($prescriptions); ?></h3>
            <p>Total Prescriptions</p>
        </div>
    </div>
    <div class="summary-card card-today">
        <div class="summary-icon">
            <img src="../../assets/icons/today.png" alt="Today">
        </div>
        <div class="summary-info">
            <h3><?php echo $todayCount; ?></h3>
            <p>Today</p>
        </div>
    </div>
</div>

<div class="action-bar">
    <div class="search-input-container">
        <span class="search-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        </span>
        <input type="text" id="searchInput"  placeholder="Search prescriptions by patient name, medications...">
    </div>
</div>

<?php if (empty($prescriptions)): ?>
    <div class="content-card">
        <div class="empty-illustration">
            <svg width="120" height="120" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="40" y="20" width="50" height="70" rx="4" fill="#f8fafc" stroke="#cbd5e1" stroke-width="3"/>
                <line x1="50" y1="40" x2="80" y2="40" stroke="#cbd5e1" stroke-width="3" stroke-linecap="round"/>
                <line x1="50" y1="55" x2="80" y2="55" stroke="#cbd5e1" stroke-width="3" stroke-linecap="round"/>
                <line x1="50" y1="70" x2="65" y2="70" stroke="#cbd5e1" stroke-width="3" stroke-linecap="round"/>
                <path d="M65 30 L75 30 L75 45" stroke="#3b82f6" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M75 30 L85 40" stroke="#3b82f6" stroke-width="3" stroke-linecap="round"/>
                <rect x="25" y="50" width="35" height="45" rx="6" fill="#ecfdf5" stroke="#368f63" stroke-width="3"/>
                <rect x="20" y="40" width="45" height="15" rx="3" fill="#368f63"/>
                <path d="M35 65 L45 65 L45 80" stroke="#368f63" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M45 65 L50 72" stroke="#368f63" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>
        <h3 class="empty-title">No Prescriptions Found</h3>
        <p class="empty-subtitle">Prescriptions will appear here once the dentist creates them</p>
    </div>
<?php else: ?>
    <div class="table-card-container">
        <div style="overflow-x: auto;" class="prescriptions-table">
            <table id="prescriptionsTable">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Medications</th>
                        <th>Diagnosis</th>
                        <th>Issue Date</th>
                        <th>Doctor</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="prescriptionsTableBody">
                    <?php foreach ($prescriptions as $prescription): ?>
                        <tr class="prescription-row" data-id="<?php echo $prescription['id']; ?>">
                            <td>
                                <div style="font-weight: 500; color: #0f172a;">
                                    <?php echo htmlspecialchars($prescription['patient_name']); ?>
                                    <?php if ($prescription['age']): ?>
                                        <span style="color: #64748b; font-size: 0.85em;">(<?php echo $prescription['age']; ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="medications-cell">
                                    <?php echo nl2br(htmlspecialchars($prescription['medications'])); ?>
                                </div>
                            </td>
                            <td>
                                <div style="color: #334155; font-weight: 500;">
                                    <?php echo htmlspecialchars($prescription['diagnosis']); ?>
                                </div>
                            </td>
                            <td>
                                <div style="color: #475569; font-size: 0.9rem;">
                                    <?php echo date('M j, Y', strtotime($prescription['issue_date'])); ?>
                                </div>
                            </td>
                            <td>
                                <div style="color: #0f172a; font-size: 0.85rem;">
                                    <?php echo htmlspecialchars($prescription['doctor_name']); ?>
                                </div>
                            </td>
                            <td>
                                <div class="prescription-actions">
                                    <button type="button" class="kebab-btn" aria-label="Actions" onclick="toggleKebabMenu(event, this)">
                                        &#8942;
                                    </button>
                                    <div class="kebab-menu" id="kebab-<?php echo $prescription['id']; ?>">
                                        <button type="button" onclick="viewPrescription(<?php echo $prescription['id']; ?>)">View</button>
                                        <button type="button" onclick="staffPrintPrescription(<?php echo $prescription['id']; ?>)">Print</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>


<!-- Rx View Modal -->
<div id="rxViewModal" class="modal-overlay" style="display:none;z-index:999999!important;" onclick="if(event.target===this)closeRxModal()">
    <div class="modal modal-rx" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h2>Prescription</h2>
            <button class="modal-close" onclick="closeRxModal()">&times;</button>
        </div>
        <div class="modal-body">
            <?php require __DIR__ . '/../../includes/rx_pad_paper.php'; ?>
        </div>
        <div class="modal-footer" style="display:flex;justify-content:flex-end;gap:12px;border-top:1px solid #e2e8f0;padding-top:20px;">
            <button type="button" class="btn-cancel" onclick="closeRxModal()" style="padding:10px 16px;border:1px solid #e2e8f0;background:white;border-radius:6px;cursor:pointer;color:#334155;">Close</button>
            <button type="button" class="btn-print" onclick="staffPrintPrescription(currentRxPrescriptionId)" style="background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;padding:10px 16px;border-radius:6px;cursor:pointer;font-weight:600;">Print Prescription</button>
        </div>
    </div>
</div>

<div id="rxNoticeModal" class="modal-overlay" style="display:none;z-index:1000000!important;" onclick="if(event.target===this)closeRxNotice()">
    <div class="modal" style="max-width:420px;" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h2>Prescription</h2>
            <button class="modal-close" onclick="closeRxNotice()">&times;</button>
        </div>
        <div class="modal-body">
            <p id="rxNoticeMessage" style="margin:0;color:#334155;line-height:1.5;"></p>
        </div>
        <div class="modal-footer" style="display:flex;justify-content:flex-end;border-top:1px solid #e2e8f0;padding-top:16px;">
            <button type="button" onclick="closeRxNotice()" style="padding:10px 16px;border:1px solid #e2e8f0;background:#2563eb;color:#fff;border-radius:6px;cursor:pointer;font-weight:600;">OK</button>
        </div>
    </div>
</div>

<script src="../../assets/js/rx_pad.js"></script>
<script>
let currentRxPrescriptionId = null;

function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function closeRxModal() {
    document.getElementById('rxViewModal').style.display = 'none';
}

function closeRxNotice() {
    document.getElementById('rxNoticeModal').style.display = 'none';
}

function showRxNotice(message) {
    document.getElementById('rxNoticeMessage').textContent = message;
    document.getElementById('rxNoticeModal').style.display = 'flex';
}

function viewPrescription(prescriptionId) {
    currentRxPrescriptionId = prescriptionId;
    fetch('staff_prescription_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_prescription', prescription_id: prescriptionId })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            if (typeof fillRxPad !== 'function') {
                showRxNotice('Prescription pad failed to load. Please refresh the page.');
                return;
            }
            fillRxPad(data.prescription);
            document.getElementById('rxViewModal').style.display = 'flex';
        } else {
            showRxNotice('Failed to load prescription: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(function() {
        showRxNotice('An error occurred while loading the prescription.');
    });
}

function staffPrintPrescription(prescriptionId) {
    if (!prescriptionId) {
        showRxNotice('Please select a prescription to print.');
        return;
    }

    fetch('staff_prescription_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_prescription', prescription_id: prescriptionId })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success) {
            showRxNotice('Failed to load prescription: ' + (data.message || 'Unknown error'));
            return;
        }
        if (typeof printRxPad !== 'function') {
            showRxNotice('Prescription pad failed to load. Please refresh the page.');
            return;
        }
        printRxPad(data.prescription);
    })
    .catch(function() {
        showRxNotice('An error occurred while loading the prescription.');
    });
}

function toggleKebabMenu(event, btn) {
    event.stopPropagation();
    var menu = btn.nextElementSibling;
    var isOpen = menu.classList.contains('show');

    document.querySelectorAll('.kebab-menu.show').forEach(function(m) {
        m.classList.remove('show');
    });
    document.querySelectorAll('.kebab-btn.active').forEach(function(b) {
        b.classList.remove('active');
    });

    if (!isOpen) {
        menu.classList.add('show');
        btn.classList.add('active');
    }
}

document.addEventListener('click', function() {
    document.querySelectorAll('.kebab-menu.show').forEach(function(m) {
        m.classList.remove('show');
    });
    document.querySelectorAll('.kebab-btn.active').forEach(function(b) {
        b.classList.remove('active');
    });
});

document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var searchTerm = this.value.toLowerCase();
            var rows = document.querySelectorAll('#prescriptionsTableBody tr');
            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }

    var modal = document.getElementById('rxViewModal');
    if (modal) {
        document.body.appendChild(modal);
    }
    var notice = document.getElementById('rxNoticeModal');
    if (notice) {
        document.body.appendChild(notice);
    }
});
</script>

<?php require_once '../../includes/staff_layout_end.php'; ?>
