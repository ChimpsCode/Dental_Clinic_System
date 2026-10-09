<?php
$pageTitle = 'Prescriptions';
require_once '../../config/database.php';
require_once '../../includes/dentist_layout_start.php';

function ensurePrescriptionTableColumnsForListing(PDO $pdo): void {
    static $checked = false;
    if ($checked) {
        return;
    }
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

try {
    require_once '../../config/database.php';
    ensurePrescriptionTableColumnsForListing($pdo);
    
    // Get recent prescriptions for display
    $stmt = $pdo->query("
        SELECT pr.*, 
               CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) AS age,
               CONCAT(u.first_name, ' ', u.last_name) AS doctor_name
        FROM prescriptions pr
        LEFT JOIN patients p ON pr.patient_id = p.id
        LEFT JOIN users u ON pr.doctor_id = u.id
        ORDER BY pr.issue_date DESC, pr.created_at DESC
        LIMIT 20
    ");
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get today's prescription count
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


/* Header & Breadcrumbs */
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

/* Stats Cards Area */
.stats-grid {
    display: flex;
    gap: 20px;
    margin-bottom: 0px;
    padding-bottom: 25px;
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
.summary-cards .summary-card.card-prescriptions::after {
    background-image: url('../../assets/icons/prescription.png');
    opacity: 0.2;
}
.summary-cards .summary-card.card-today::after {
    background-image: url('../../assets/icons/today.png');
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

/* Action Bar (Search & Button) */
.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 0px;
    padding-bottom: 25px;
    
    /* ADD THESE TWO LINES TO FIX UNCLICKABLE BUTTONS */
    position: relative; 
    z-index: 10;
}

.search-input-container {
    position: relative;
    flex: 1;
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

.btn-primary {
    background: #2563eb; /* Forest green matching your design */
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.95rem;
    cursor: pointer;
    transition: background-color 0.2s;
    white-space: nowrap;
}

.btn-primary:hover {
    background: #0284c7;
}

/* Empty State Card */
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

/* Table Styling Overrides for the new card look */
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

/* Rest of your existing modal and specific cell styles remain untouched below */
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
    min-width: 200px;
    max-width: 220px;
    width: auto;
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
.kebab-menu .danger { color: #dc2626; }
.btn-print { background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; padding: 10px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; }
.btn-print:hover { background: #e0e7ff; }

/* Modal */
#prescriptionModal {
    padding: 24px;
    background: rgba(15, 23, 42, 0.48) !important;
    backdrop-filter: blur(3px);
}
#prescriptionModal > .modal {
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: 680px;
    max-height: calc(100dvh - 48px);
    padding: 0 !important;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    box-shadow: 0 24px 70px rgba(15, 23, 42, 0.24);
}
#prescriptionModal .modal-header {
    position: static;
    flex-shrink: 0;
    gap: 16px;
    margin: 0;
    padding: 22px 28px;
    border-bottom: 1px solid #e8edf4;
}
#prescriptionModal .rx-form-heading { display: flex; align-items: center; gap: 14px; min-width: 0; }
#prescriptionModal .rx-form-icon {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    flex-shrink: 0;
    border-radius: 12px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 26px;
    font-weight: 600;
}
#prescriptionModal .rx-form-heading p { margin: 5px 0 0; font-size: 13px; line-height: 1.5; color: #64748b; }
#prescriptionModal .modal-close { width: 36px; height: 36px; padding: 0; flex-shrink: 0; border-radius: 10px; }
#prescriptionModal .modal-close:hover { background: #f1f5f9; color: #334155; }
#prescriptionModal .modal-body {
    min-height: 0;
    padding: 24px 28px;
    overflow-y: auto;
    overscroll-behavior: contain;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}
#prescriptionModal #prescriptionForm { display: grid; gap: 18px; margin: 0; padding: 0; }
#prescriptionModal .rx-form-row { display: grid; grid-template-columns: 1.4fr 1fr; gap: 18px; }
#prescriptionModal .form-group { margin-bottom: 0 !important; min-width: 0; }
#prescriptionModal .form-group label { margin-bottom: 8px !important; font-size: 13px !important; font-weight: 600 !important; }
#prescriptionModal .form-group input,
#prescriptionModal .form-group textarea {
    display: block;
    width: 100%;
    padding: 11px 13px !important;
    border: 1px solid #dbe3ee !important;
    border-radius: 9px !important;
    background: #fff;
    font: inherit;
    font-size: 14px;
    line-height: 1.5;
    color: #0f172a;
    box-sizing: border-box;
    outline: none;
}
#prescriptionModal .form-group textarea { min-height: 76px; resize: vertical; }
#prescriptionModal #medications { min-height: 100px; }
#prescriptionModal .form-group input:focus,
#prescriptionModal .form-group textarea:focus { border-color: #3b82f6 !important; box-shadow: 0 0 0 3px #eff6ff; }
#prescriptionModal .form-group input::placeholder,
#prescriptionModal .form-group textarea::placeholder { color: #94a3b8; }
#prescriptionModal .modal-footer {
    flex-shrink: 0;
    flex-wrap: wrap;
    margin: 0;
    padding: 16px 28px !important;
    background: #f8fafc;
    border-top: 1px solid #e8edf4 !important;
}
#prescriptionModal .modal-footer button { min-height: 42px; border-radius: 9px !important; font-size: 14px; font-weight: 600; }
#prescriptionModal button:focus-visible { outline: 3px solid #93c5fd; outline-offset: 3px; }
@media (max-width: 540px) {
    #prescriptionModal { padding: 12px; }
    #prescriptionModal > .modal { max-height: calc(100dvh - 24px); border-radius: 16px; }
    #prescriptionModal .modal-header { padding: 18px; }
    #prescriptionModal .modal-body { padding: 20px 18px; }
    #prescriptionModal .modal-footer { padding: 14px 18px !important; gap: 8px !important; }
    #prescriptionModal .rx-form-row { grid-template-columns: 1fr; }
    #prescriptionModal .safety-grid { grid-template-columns: 1fr !important; }
    #prescriptionModal .rx-form-heading p { font-size: 12px; }
}

/* Patient Search Dropdown */
.search-results-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #e2e8f0;
    border-top: none;
    border-radius: 0 0 8px 8px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    max-height: 200px;
    overflow-y: auto;
    display: none;
}

.search-result-item {
    padding: 10px 12px;
    cursor: pointer;
    transition: background 0.15s;
    border-bottom: 1px solid #f1f5f9;
}

.search-result-item:last-child {
    border-bottom: none;
}

.search-result-item:hover {
    background: #f8fafc;
}

.search-result-item .name {
    font-weight: 500;
    color: #0f172a;
    font-size: 0.9rem;
}

.search-result-item .details {
    font-size: 0.75rem;
    color: #64748b;
}

</style>


    

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
            <input type="text" id="searchInput" placeholder="Search prescriptions by patient name, medications...">
        </div>
        <button class="btn-primary" onclick="openNewPrescriptionModal()">
            New Prescription
        </button>
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
            <p class="empty-subtitle">Prescriptions will appear here once they are created</p>
            <div style="text-align: center; width: 100%;">
                <button class="btn-primary" onclick="openNewPrescriptionModal()" style="display: inline-block;">
                    Create Your First Prescription
                </button>
            </div>
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
                            <th>Instructions</th>
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
                                    <div style="color: #64748b; font-size: 0.85rem;">
                                        <?php echo $prescription['instructions'] ? nl2br(htmlspecialchars($prescription['instructions'])) : '<em>None</em>'; ?>
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
                                            <button type="button" onclick="editPrescription(<?php echo $prescription['id']; ?>)">Edit</button>
                                            <button type="button" onclick="printPrescription(<?php echo $prescription['id']; ?>)">Print</button>
                                            <button type="button" class="danger" onclick="deletePrescription(<?php echo $prescription['id']; ?>)">Delete</button>
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


<div id="prescriptionModal" class="modal-overlay" style="display: none; z-index: 999999 !important;">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-header">
            <div class="rx-form-heading">
                <span class="rx-form-icon" aria-hidden="true">&#8478;</span>
                <div>
                    <h2 id="modalTitle" style="font-size: 1.25rem; font-weight: 600; margin: 0; color: #0f172a;">New Prescription</h2>
                    <p>Add medication details and patient instructions.</p>
                </div>
            </div>
            <button type="button" class="modal-close" aria-label="Close prescription" onclick="closePrescriptionModal()">&times;</button>
        </div>
        <div class="modal-body" id="prescriptionModalContent">
            <div id="patientSafetyInfo" class="safety-info" style="display: none; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; margin-bottom: 24px;">
                <h3 style="font-size: 1rem; font-weight: 600; color: #15803d; margin: 0 0 12px 0;">Patient Safety Information</h3>
                <div class="safety-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                    <div class="safety-item">
                        <div style="font-size: 0.85rem; color: #475569; margin-bottom: 4px;">Patient Name</div>
                        <div id="patientName" style="font-weight: 500; color: #0f172a;"></div>
                    </div>
                    <div class="safety-item">
                        <div style="font-size: 0.85rem; color: #475569; margin-bottom: 4px;">Age</div>
                        <div id="patientAge" style="font-weight: 500; color: #0f172a;"></div>
                    </div>
                    <div class="safety-item">
                        <div style="font-size: 0.85rem; color: #475569; margin-bottom: 4px;">Allergies</div>
                        <div id="patientAllergies" style="color: #dc2626; font-weight: 500;"></div>
                    </div>
                    <div class="safety-item">
                        <div style="font-size: 0.85rem; color: #475569; margin-bottom: 4px;">Medical History</div>
                        <div id="patientMeds" style="color: #059669; font-weight: 500;"></div>
                    </div>
                </div>
            </div>
            
            <form id="prescriptionForm">
                <input type="hidden" id="prescriptionId" name="prescription_id">
                <input type="hidden" id="patientId" name="patient_id">
                
                <div class="rx-form-row">
                    <div class="form-group" style="position: relative;">
                        <label for="patientSearch" style="display: block; margin-bottom: 6px; font-weight: 500; color: #334155; font-size: 0.9rem;">Search Patient: *</label>
                        <input type="text" id="patientSearch" placeholder="Type patient name..." autocomplete="off" style="width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box;">
                        <div id="patientSearchResults" class="search-results-dropdown"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="issueDate" style="display: block; margin-bottom: 6px; font-weight: 500; color: #334155; font-size: 0.9rem;">Issue Date: *</label>
                        <input type="date" id="issueDate" name="issue_date" required style="width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box;">
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="diagnosis" style="display: block; margin-bottom: 6px; font-weight: 500; color: #334155; font-size: 0.9rem;">Diagnosis:</label>
                    <textarea id="diagnosis" name="diagnosis" rows="2" required placeholder="Reason for prescription..." style="width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box;"></textarea>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="medications" style="display: block; margin-bottom: 6px; font-weight: 500; color: #334155; font-size: 0.9rem;">Medications:</label>
                    <textarea id="medications" name="medications" rows="3" required placeholder="e.g., Amoxicillin 500mg - 3 times daily for 7 days" style="width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box;"></textarea>
                </div>
                
                <div class="form-group" style="margin-bottom: 24px;">
                    <label for="instructions" style="display: block; margin-bottom: 6px; font-weight: 500; color: #334155; font-size: 0.9rem;">Instructions:</label>
                    <textarea id="instructions" name="instructions" rows="2" placeholder="Additional patient instructions..." style="width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box;"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
            <button type="button" id="printPrescriptionBtn" class="btn-secondary" onclick="printPrescription()" style="padding: 10px 16px; border: 1px solid #e2e8f0; background: #f8fafc; border-radius: 6px; cursor: pointer; color: #334155; display: none;">Print Prescription</button>
            <button type="button" class="btn-cancel" onclick="closePrescriptionModal()" style="padding: 10px 16px; border: 1px solid #e2e8f0; background: white; border-radius: 6px; cursor: pointer; color: #334155;">Cancel</button>
            <button type="button" class="btn-primary" onclick="savePrescription()" style="padding: 10px 16px;">Save Prescription</button>
        </div>
    </div>
    
</div>

<!-- Rx View Modal -->
<div id="rxViewModal" class="modal-overlay" style="display:none;z-index:999999!important;backdrop-filter:blur(3px);-webkit-backdrop-filter:blur(3px);" onclick="if(event.target===this)closeRxModal()">
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
            <button type="button" class="btn-print" onclick="printRxPrescription(currentRxPrescriptionId)" style="background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;padding:10px 16px;border-radius:6px;cursor:pointer;font-weight:600;">Print Prescription</button>
        </div>
    </div>
</div>

<script src="../../assets/js/rx_pad.js"></script>
<script>
    // Fix: Add the missing function to clear the safety info box
function resetPatientSafetyInfo() {
    const safetyBox = document.getElementById('patientSafetyInfo');
    if (safetyBox) {
        safetyBox.style.display = 'none';
    }
    document.getElementById('patientName').textContent = '';
    document.getElementById('patientAge').textContent = '';
    document.getElementById('patientAllergies').textContent = '';
    document.getElementById('patientMeds').innerHTML = '';
}

// Fix: Add the missing function to close the modal
function closePrescriptionModal() {
    const modal = document.getElementById('prescriptionModal');
    if (modal) {
        modal.style.display = 'none';
    }
}


    
// I kept your existing JS exactly as is, it will work perfectly with the new IDs and classes!
let currentPrescriptionId = null;
let currentRxPrescriptionId = null;
let searchTimeout = null;

function searchPatients(query) {
    if (query.length < 2) {
        document.getElementById('patientSearchResults').style.display = 'none';
        return;
    }

    fetch('prescription_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'search_patients', query: query })
    })
    .then(response => response.json())
    .then(data => {
        const resultsDiv = document.getElementById('patientSearchResults');
        resultsDiv.innerHTML = '';
        
        if (data.success && data.patients.length > 0) {
            data.patients.forEach(patient => {
                const item = document.createElement('div');
                item.className = 'search-result-item';
                item.innerHTML = `
                    <div class="name">${patient.patient_name}</div>
                    <div class="details">Age: ${patient.age || 'N/A'}</div>
                `;
                item.onclick = () => selectPatient(patient);
                resultsDiv.appendChild(item);
            });
            resultsDiv.style.display = 'block';
        } else {
            resultsDiv.innerHTML = '<div style="padding: 10px; color: #64748b; font-size: 0.85rem;">No patients found</div>';
            resultsDiv.style.display = 'block';
        }
    });
}

function selectPatient(patient) {
    document.getElementById('patientId').value = patient.id;
    document.getElementById('patientSearch').value = patient.patient_name;
    document.getElementById('patientSearchResults').style.display = 'none';
    loadPatientSafetyInfo(patient.id);
}

function toggleKebabMenu(event, btn) {
    event.stopPropagation();
    var menu = btn.nextElementSibling;
    if (!menu) return;
    var isOpen = menu.classList.contains('show');

    document.querySelectorAll('.kebab-menu.show').forEach(function(m) {
        m.classList.remove('show');
    });
    document.querySelectorAll('.kebab-btn.active').forEach(function(b) {
        b.classList.remove('active');
    });

    if (!isOpen) {
        var rect = btn.getBoundingClientRect();
        menu.style.top = Math.min(rect.bottom + 4, window.innerHeight - 8) + 'px';
        menu.style.left = Math.max(8, rect.right - 200) + 'px';
        menu.classList.add('show');
        btn.classList.add('active');
    }
}

if (window.__dentistRxKebabCloser) {
    document.removeEventListener('click', window.__dentistRxKebabCloser);
}
window.__dentistRxKebabCloser = function() {
    document.querySelectorAll('.kebab-menu.show').forEach(function(m) {
        m.classList.remove('show');
    });
    document.querySelectorAll('.kebab-btn.active').forEach(function(b) {
        b.classList.remove('active');
    });
};
document.addEventListener('click', window.__dentistRxKebabCloser);

// Close search results when clicking outside
document.addEventListener('click', (e) => {
    if (!e.target.closest('.form-group')) {
        const results = document.getElementById('patientSearchResults');
        if (results) results.style.display = 'none';
    }
});

function openNewPrescriptionModal() {
    currentPrescriptionId = null;
    document.getElementById('modalTitle').textContent = 'New Prescription';
    document.getElementById('prescriptionForm').reset();
    document.getElementById('prescriptionId').value = '';
    document.getElementById('patientId').value = '';
    document.getElementById('patientSearch').value = '';
    document.getElementById('issueDate').value = new Date().toISOString().split('T')[0];
    resetPatientSafetyInfo();
    setPrescriptionFormMode('edit');
    document.getElementById('prescriptionModal').style.display = 'flex';
}

function openPatientPrescriptionModal(patientId) {
    currentPrescriptionId = null;
    document.getElementById('modalTitle').textContent = 'New Prescription';
    document.getElementById('prescriptionForm').reset();
    document.getElementById('prescriptionId').value = '';
    document.getElementById('patientId').value = patientId;
    
    // Fetch patient name for the search box
    fetch('prescription_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_patient_info', patient_id: patientId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('patientSearch').value = data.patient.patient_name;
            loadPatientSafetyInfo(patientId);
        }
    });

    document.getElementById('issueDate').value = new Date().toISOString().split('T')[0];
    setPrescriptionFormMode('edit');
    document.getElementById('prescriptionModal').style.display = 'flex';
}

function setPrescriptionFormMode(mode) {
    const isReadOnly = mode === 'view';
    const fields = ['patientSearch', 'issueDate', 'diagnosis', 'medications', 'instructions'];
    fields.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.disabled = isReadOnly;
        }
    });

    const saveBtn = document.querySelector('#prescriptionModal .btn-primary');
    if (saveBtn) {
        saveBtn.style.display = isReadOnly ? 'none' : 'inline-block';
    }

    const printBtn = document.getElementById('printPrescriptionBtn');
    if (printBtn) {
        printBtn.style.display = isReadOnly ? 'inline-block' : 'none';
    }
}

function fillPrescriptionForm(prescription) {
    if (!prescription) {
        return;
    }

    document.getElementById('prescriptionId').value = prescription.id || '';
    document.getElementById('patientId').value = prescription.patient_id || '';
    document.getElementById('patientSearch').value = prescription.patient_name || '';
    document.getElementById('issueDate').value = prescription.issue_date || '';
    document.getElementById('diagnosis').value = prescription.diagnosis || '';
    document.getElementById('medications').value = prescription.medications || '';
    document.getElementById('instructions').value = prescription.instructions || '';
    loadPatientSafetyInfo(prescription.patient_id);
}

function getPrescriptionDetails(prescriptionId, callback) {
    fetch('prescription_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_prescription', prescription_id: prescriptionId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            callback(data.prescription);
        } else {
            alert('Unable to load prescription details: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Prescription details error:', error);
        alert('An error occurred while loading the prescription.');
    });
}

function viewPrescription(prescriptionId) {
    currentRxPrescriptionId = prescriptionId;
    getPrescriptionDetails(prescriptionId, prescription => {
        fillRxPad(prescription);
        document.getElementById('rxViewModal').style.display = 'flex';
    });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function closeRxModal() {
    document.getElementById('rxViewModal').style.display = 'none';
}

function printRxPrescription(prescriptionId) {
    const idToPrint = prescriptionId || currentRxPrescriptionId;
    if (!idToPrint) { alert('Please select a prescription to print.'); return; }

    getPrescriptionDetails(idToPrint, prescription => {
        printRxPad(prescription);
    });
}

function editPrescription(prescriptionId) {
    currentPrescriptionId = prescriptionId;
    getPrescriptionDetails(prescriptionId, prescription => {
        document.getElementById('modalTitle').textContent = 'Edit Prescription';
        fillPrescriptionForm(prescription);
        setPrescriptionFormMode('edit');
        const printBtn = document.getElementById('printPrescriptionBtn');
        if (printBtn) {
            printBtn.style.display = 'none';
        }
        document.getElementById('prescriptionModal').style.display = 'flex';
    });
}

function printPrescription(prescriptionId = null) {
    const idToPrint = prescriptionId || currentRxPrescriptionId;
    if (!idToPrint) {
        alert('Please select a prescription to print.');
        return;
    }
    printRxPrescription(idToPrint);
}

function savePrescription() {
    const prescriptionId = document.getElementById('prescriptionId').value;
    const patientId = document.getElementById('patientId').value;
    const issueDate = document.getElementById('issueDate').value;
    const diagnosis = document.getElementById('diagnosis').value;
    const medications = document.getElementById('medications').value;
    const instructions = document.getElementById('instructions').value;

    if (!patientId) {
        alert('Please search and select a patient.');
        return;
    }
    if (!medications || !diagnosis) {
        alert('Medications and diagnosis are required.');
        return;
    }
    if (!issueDate) {
        alert('Please select an issue date.');
        return;
    }

    const action = prescriptionId ? 'update_prescription' : 'create_prescription';
    const payload = {
        action: action,
        patient_id: patientId,
        medications: medications,
        diagnosis: diagnosis,
        instructions: instructions,
        issue_date: issueDate
    };
    if (prescriptionId) {
        payload.prescription_id = prescriptionId;
    }

    fetch('prescription_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closePrescriptionModal();
            (window.appCache ? window.appCache.refreshCurrent(['dentist_dashboard.php']) : location.reload());
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Save prescription error:', error);
        alert('An error occurred while saving the prescription.');
    });
}

function deletePrescription(prescriptionId) {
    if (!confirm('Are you sure you want to delete this prescription?')) {
        return;
    }

    fetch('prescription_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete_prescription', prescription_id: prescriptionId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const row = document.querySelector(`tr.prescription-row[data-id="${prescriptionId}"]`);
            if (row) {
                row.remove();
            }
            closePrescriptionModal();
            alert(data.message);
        } else {
            alert('Unable to delete prescription: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Delete prescription error:', error);
        alert('An error occurred while deleting the prescription.');
    });
}

function loadPatientSafetyInfo(patientId) {
    fetch('prescription_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_patient_info', patient_id: patientId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const patient = data.patient;
            document.getElementById('patientName').textContent = patient.patient_name || 'N/A';
            document.getElementById('patientAge').textContent = patient.age || 'N/A';
            document.getElementById('patientAllergies').textContent = patient.allergies || 'None recorded';

            const parts = [];
            if (patient.medical_conditions) parts.push(patient.medical_conditions);
            if (patient.current_medications) parts.push('Meds: ' + patient.current_medications);
            if (patient.blood_pressure) parts.push('BP: ' + patient.blood_pressure);
            if (patient.heart_rate) parts.push('HR: ' + patient.heart_rate);
            if (patient.other_notes) parts.push('Notes: ' + patient.other_notes);
            document.getElementById('patientMeds').innerHTML = parts.length
                ? parts.map(p => '• ' + p).join('<br>')
                : 'None recorded';
            document.getElementById('patientSafetyInfo').style.display = 'block';
        }
    });
}

// Event listener for page load
document.addEventListener('DOMContentLoaded', function() {
    // 1. Set up the patient search box inside the modal
    const searchBox = document.getElementById('patientSearch');
    if (searchBox) {
        searchBox.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => searchPatients(e.target.value), 300);
        });
    }

    // 2. Set up the main table search filter
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#prescriptionsTableBody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }
    
    // 3. Portal Pattern: Move modals to body to escape container restrictions
    const modal = document.getElementById('prescriptionModal');
    if (modal) {
        document.body.appendChild(modal);
    }
    const rxModal = document.getElementById('rxViewModal');
    if (rxModal) {
        document.body.appendChild(rxModal);
    }
});
</script>

<?php require_once '../../includes/dentist_layout_end.php'; ?>
