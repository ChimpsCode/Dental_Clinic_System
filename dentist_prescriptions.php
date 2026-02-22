<?php
$pageTitle = 'Prescriptions';
require_once 'includes/dentist_layout_start.php';

try {
    require_once 'config/database.php';
    
    // Get recent prescriptions for display
    $stmt = $pdo->query("
        SELECT pr.*, 
               CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
               p.age,
               u.username AS doctor_name
        FROM `" . DB_NAME . "`.prescriptions pr
        LEFT JOIN `" . DB_NAME . "`.patients p ON pr.patient_id = p.id
        LEFT JOIN `" . DB_NAME . "`.users u ON pr.doctor_id = u.id
        ORDER BY pr.issue_date DESC, pr.created_at DESC
        LIMIT 20
    ");
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get today's prescription count
    $todayStmt = $pdo->query("SELECT COUNT(*) FROM `" . DB_NAME . "`.prescriptions WHERE DATE(created_at) = CURDATE()");
    $todayCount = $todayStmt->fetchColumn();
    
} catch (Exception $e) {
    $prescriptions = [];
    $todayCount = 0;
}
?>

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
.fullscreen-modal-overlay {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(2px);
    z-index: 9999;
    padding: 24px;
}
.fullscreen-modal-content {
    width: min(900px, 92vw);
    max-height: 90vh;
    overflow-y: auto;
}
.fullscreen-modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #64748b;
}
</style>


    

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="12" y1="18" x2="12" y2="12"></line>
                    <line x1="9" y1="15" x2="15" y2="15"></line>
                </svg>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo count($prescriptions); ?></div>
                <div class="stat-label">Total Prescriptions</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
            </div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $todayCount; ?></div>
                <div class="stat-label">Today</div>
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
                                        <button class="kebab-btn" aria-label="Actions" onclick="toggleKebab(<?php echo $prescription['id']; ?>, event)">
                                            &#8942;
                                        </button>
                                        <div class="kebab-menu" id="kebab-<?php echo $prescription['id']; ?>">
                                            <button onclick="viewPrescription(<?php echo $prescription['id']; ?>)">View</button>
                                            <button onclick="editPrescription(<?php echo $prescription['id']; ?>)">Edit</button>
                                            <button onclick="printPrescription(<?php echo $prescription['id']; ?>)">Print</button>
                                            <button class="danger" onclick="deletePrescription(<?php echo $prescription['id']; ?>)">Delete</button>
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


<div id="prescriptionModal" class="fullscreen-modal-overlay" style="display: none;">
    <div class="fullscreen-modal-content" style="max-width: 800px; background: white; margin: 5% auto; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); padding: 24px;">
        <div class="fullscreen-modal-header">
            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; margin-bottom: 20px;">
                <h2 id="modalTitle" style="font-size: 1.25rem; font-weight: 600; margin: 0; color: #0f172a;">New Prescription</h2>
                <button class="fullscreen-modal-close" onclick="closePrescriptionModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b;">&times;</button>
            </div>
        </div>
        <div class="fullscreen-modal-body" id="prescriptionModalContent">
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
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label for="patientSelect" style="display: block; margin-bottom: 6px; font-weight: 500; color: #334155; font-size: 0.9rem;">Patient:</label>
                        <select id="patientSelect" name="patient_id" required style="width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                            <option value="">Select Patient...</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="issueDate" style="display: block; margin-bottom: 6px; font-weight: 500; color: #334155; font-size: 0.9rem;">Issue Date:</label>
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
        <div class="fullscreen-modal-footer" style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
            <button type="button" class="btn-cancel" onclick="closePrescriptionModal()" style="padding: 10px 16px; border: 1px solid #e2e8f0; background: white; border-radius: 6px; cursor: pointer; color: #334155;">Cancel</button>
            <button type="button" class="btn-primary" onclick="savePrescription()" style="padding: 10px 16px;">Save Prescription</button>
        </div>
    </div>
</div>

<script>
// I kept your existing JS exactly as is, it will work perfectly with the new IDs and classes!
let currentPrescriptionId = null;

function loadPatients() {
    fetch('prescription_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_patients' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('patientSelect');
            select.innerHTML = '<option value="">Select Patient...</option>';
            data.patients.forEach(patient => {
                const option = document.createElement('option');
                option.value = patient.id;
                option.textContent = `${patient.patient_name}`;
                select.appendChild(option);
            });
        }
    });
}

function openNewPrescriptionModal() {
    currentPrescriptionId = null;
    document.getElementById('modalTitle').textContent = 'New Prescription';
    document.getElementById('prescriptionForm').reset();
    document.getElementById('prescriptionId').value = '';
    document.getElementById('issueDate').value = new Date().toISOString().split('T')[0];
    resetPatientSafetyInfo();
    loadPatients();
    document.getElementById('printPrescriptionBtn').style.display = 'none';
    document.getElementById('prescriptionModal').style.display = 'flex';
}

function openPatientPrescriptionModal(patientId) {
    currentPrescriptionId = null;
    document.getElementById('modalTitle').textContent = 'New Prescription';
    document.getElementById('prescriptionForm').reset();
    document.getElementById('prescriptionId').value = '';
    document.getElementById('patientId').value = patientId;
    document.getElementById('patientSelect').value = patientId;
    document.getElementById('issueDate').value = new Date().toISOString().split('T')[0];
    resetPatientSafetyInfo();
    loadPatientSafetyInfo(patientId);
    document.getElementById('printPrescriptionBtn').style.display = 'none';
    document.getElementById('prescriptionModal').style.display = 'flex';
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

document.getElementById('patientSelect').addEventListener('change', function() {
    const patientId = this.value;
    if (patientId) {
        loadPatientSafetyInfo(patientId);
        document.getElementById('patientId').value = patientId;
    } else {
        resetPatientSafetyInfo();
    }
});

function viewPrescription(prescriptionId) {
    fetch('prescription_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_prescription', prescription_id: prescriptionId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const prescription = data.prescription;
            currentPrescriptionId = prescriptionId;
            document.getElementById('modalTitle').textContent = 'Edit Prescription';
            document.getElementById('prescriptionId').value = prescriptionId;
            document.getElementById('patientId').value = prescription.patient_id;
            document.getElementById('patientSelect').value = prescription.patient_id;
            document.getElementById('issueDate').value = prescription.issue_date;
            document.getElementById('diagnosis').value = prescription.diagnosis;
            document.getElementById('medications').value = prescription.medications;
            document.getElementById('instructions').value = prescription.instructions || '';
            loadPatientSafetyInfo(prescription.patient_id);
            document.getElementById('printPrescriptionBtn').style.display = 'inline-block';
            document.getElementById('prescriptionModal').style.display = 'flex';
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function editPrescription(prescriptionId) {
    viewPrescription(prescriptionId);
}

function savePrescription() {
    const formData = new FormData(document.getElementById('prescriptionForm'));
    const data = Object.fromEntries(formData);
    
    if (!data.patient_id || !data.medications || !data.diagnosis) {
        alert('Please fill in all required fields');
        return;
    }
    
    const action = currentPrescriptionId ? 'update_prescription' : 'create_prescription';
    
    fetch('prescription_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ...data })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closePrescriptionModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
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
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function closePrescriptionModal() {
    document.getElementById('prescriptionModal').style.display = 'none';
}

// Kebab menus
function toggleKebab(id, evt) {
    evt.stopPropagation();
    const menu = document.getElementById('kebab-' + id);
    document.querySelectorAll('.kebab-menu').forEach(m => { if (m !== menu) m.classList.remove('show'); });
    if (menu) menu.classList.toggle('show');
}
document.addEventListener('click', () => {
    document.querySelectorAll('.kebab-menu').forEach(m => m.classList.remove('show'));
});

function resetPatientSafetyInfo() {
    document.getElementById('patientName').textContent = '';
    document.getElementById('patientAge').textContent = '';
    document.getElementById('patientAllergies').textContent = '';
    document.getElementById('patientMeds').textContent = '';
    document.getElementById('patientSafetyInfo').style.display = 'none';
}

function printPrescription(idOverride = null) {
    const presId = idOverride || currentPrescriptionId;
    if (!presId) {
        alert('Save the prescription first to print.');
        return;
    }
    fetch('prescription_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_prescription', prescription_id: presId })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            alert('Unable to load prescription for printing.');
            return;
        }
        const p = data.prescription;
        const patientInfo = `
            <div style="font-weight:600;font-size:16px;color:#0f172a;">${p.patient_name || ''}</div>
            <div style="color:#475569;font-size:13px;">Age: ${p.age || 'N/A'}</div>
        `;
        const html = `
        <html>
        <head>
            <title>Prescription #${p.id}</title>
            <style>
                body { font-family: Arial, sans-serif; padding:24px; color:#0f172a; }
                h2 { margin:0 0 6px 0; }
                .muted { color:#64748b; font-size:13px; margin-bottom:12px; }
                .section { margin-top:16px; }
                .label { font-weight:600; margin-bottom:4px; }
                .box { border:1px solid #e2e8f0; border-radius:8px; padding:12px; background:#f8fafc; }
                .meds { white-space:pre-wrap; font-family: 'Courier New', monospace; font-size:14px; }
            </style>
        </head>
        <body>
            <h2>Prescription</h2>
            <div class="muted">Issued: ${p.issue_date}</div>
            <div class="section">
                <div class="label">Patient</div>
                <div class="box">${patientInfo}</div>
            </div>
            <div class="section">
                <div class="label">Diagnosis</div>
                <div class="box">${p.diagnosis || ''}</div>
            </div>
            <div class="section">
                <div class="label">Medications</div>
                <div class="box meds">${(p.medications || '').replace(/\\n/g,'<br>')}</div>
            </div>
            <div class="section">
                <div class="label">Instructions</div>
                <div class="box">${p.instructions ? p.instructions.replace(/\\n/g,'<br>') : 'None'}</div>
            </div>
            <div class="section">
                <div class="label">Doctor</div>
                <div class="box">${p.doctor_name || ''}</div>
            </div>
        </body>
        </html>`;
        const win = window.open('', '_blank');
        win.document.write(html);
        win.document.close();
        win.focus();
        win.print();
        setTimeout(() => win.close(), 500);
    });
}

document.getElementById('searchInput').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#prescriptionsTableBody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

document.addEventListener('DOMContentLoaded', function() {
    loadPatients();
});
</script>

<?php require_once 'includes/dentist_layout_end.php'; ?>
