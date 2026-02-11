<?php
$pageTitle = 'Prescriptions';
require_once 'includes/dentist_layout_start.php';

try {
    require_once 'config/database.php';
    
    // Get recent prescriptions for display
    $stmt = $pdo->query("
        SELECT pr.*, 
               CONCAT(p.first_name, ' ', p.last_name) as patient_name,
               p.age,
               u.full_name as doctor_name
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
?>

<style>
.prescriptions-container {
    max-width: 100%;
    padding: 0;
    margin: 0;
}

.prescriptions-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 25px 30px 20px 30px;
    border-bottom: 1px solid #e5e7eb;
    background: #f8fafc;
}

.prescriptions-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 12px;
}


.prescriptions-stats {
    display: flex;
    gap: 25px;
}

.stat-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px 25px;
    min-width: 140px;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.stat-card .number {
    font-size: 2rem;
    font-weight: 700;
    color: #0284c7;
    margin-bottom: 5px;
}

.stat-card .label {
    font-size: 0.9rem;
    color: #6b7280;
    font-weight: 500;
}

.prescriptions-actions {
    display: flex;
    gap: 20px;
    align-items: center;
    padding: 20px 30px;
    background: white;
    border-bottom: 1px solid #e5e7eb;
}

.search-box {
    flex: 1;
    max-width: 400px;
}

.search-box input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.95rem;
    transition: border-color 0.2s;
}

.search-box input:focus {
    outline: none;
    border-color: #0284c7;
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
}

.btn-new-prescription {
    background: #10b981;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
}

.btn-new-prescription:hover {
    background: #059669;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3);
}

.search-box {
    flex: 1;
    max-width: 400px;
}

.search-box input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.95rem;
    transition: border-color 0.2s;
}

.search-box input:focus {
    outline: none;
    border-color: #0284c7;
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
}

.btn-new-prescription {
    background: #10b981;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
}

.btn-new-prescription:hover {
    background: #059669;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3);
}

.prescriptions-table {
    background: white;
    border-radius: 0;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
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
    color: #374151;
    border-bottom: 2px solid #e5e7eb;
    font-size: 0.9rem;
    white-space: nowrap;
}

.prescriptions-table th:first-child {
    border-top-left-radius: 0;
}

.prescriptions-table th:last-child {
    border-top-right-radius: 0;
}

.prescriptions-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: top;
    font-size: 0.9rem;
}

.prescriptions-table tr:hover {
    background: #f9fafb;
}

.patient-info {
    font-weight: 600;
    color: #1f2937;
    font-size: 1rem;
}

.patient-age {
    font-size: 0.85rem;
    color: #6b7280;
    margin-left: 6px;
    font-weight: 400;
}

.medications-cell {
    max-width: 400px;
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    background: #f0fdf4;
    padding: 12px 16px;
    border-radius: 6px;
    border-left: 4px solid #10b981;
    line-height: 1.4;
}

.diagnosis-cell {
    max-width: 200px;
    color: #374151;
    font-weight: 500;
    font-size: 0.95rem;
}

.instructions-cell {
    max-width: 300px;
    color: #6b7280;
    font-size: 0.85rem;
    line-height: 1.4;
}

.date-cell {
    color: #6b7280;
    font-size: 0.9rem;
    font-weight: 500;
}

.doctor-cell {
    color: #1f2937;
    font-size: 0.85rem;
    font-weight: 500;
}

.prescription-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-action {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 500;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.btn-view {
    background: #3b82f6;
    color: white;
}

.btn-view:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.btn-edit {
    background: #f59e0b;
    color: white;
}

.btn-edit:hover {
    background: #d97706;
    transform: translateY(-1px);
}

.btn-delete {
    background: #ef4444;
    color: white;
}

.btn-delete:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

</style>

<div class="prescriptions-container">
    <div class="prescriptions-header">
        <h1 class="prescriptions-title"><svg fill="#272020" width="34" height="34" viewBox="0 0 256 256" id="Flat" xmlns="http://www.w3.org/2000/svg">
  <path d="M188.9707,188l19.51465-19.51465a12.0001,12.0001,0,0,0-16.9707-16.9707L172,171.0293l-34.01074-34.01062A55.99228,55.99228,0,0,0,120,28H72A12,12,0,0,0,60,40V192a12,12,0,0,0,24,0V140h23.0293l48,48-19.51465,19.51465a12.0001,12.0001,0,0,0,16.9707,16.9707L172,204.9707l19.51465,19.51465a12.0001,12.0001,0,0,0,16.9707-16.9707ZM84,52h36a32,32,0,0,1,0,64H84Z"/>
</svg>
           Prescriptions Management</h1>
        <div class="prescriptions-stats">
            <div class="stat-card">
                <div class="number"><?php echo count($prescriptions); ?></div>
                <div class="label">Total Prescriptions</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $todayCount; ?></div>
                <div class="label">Today</div>
            </div>
        </div>
    </div>
    
    <div class="prescriptions-actions">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search prescriptions by patient name, medications, or diagnosis...">
        </div>
        <button class="btn-new-prescription" onclick="openNewPrescriptionModal()">
            <span><svg fill="#ffffff" width="24" height="24" viewBox="0 0 256 256" id="Flat" xmlns="http://www.w3.org/2000/svg">
  <path d="M188.9707,188l19.51465-19.51465a12.0001,12.0001,0,0,0-16.9707-16.9707L172,171.0293l-34.01074-34.01062A55.99228,55.99228,0,0,0,120,28H72A12,12,0,0,0,60,40V192a12,12,0,0,0,24,0V140h23.0293l48,48-19.51465,19.51465a12.0001,12.0001,0,0,0,16.9707,16.9707L172,204.9707l19.51465,19.51465a12.0001,12.0001,0,0,0,16.9707-16.9707ZM84,52h36a32,32,0,0,1,0,64H84Z"/>
</svg>
           </span>
            <span>New Prescription</span>
        </button>
    </div>



<?php if (empty($prescriptions)): ?>
    <div class="empty-state" style="text-align: center; padding: 80px 40px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5" style="margin: 0 auto 16px; display: block;">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
        <h3 style="color: #111827; font-size: 1.5rem; margin-bottom: 10px;">No Prescriptions Found</h3>
        <p style="color: #6b7280; font-size: 0.9rem;">Prescriptions will appear here once they are created</p>
        <button class="btn-new-prescription" onclick="openNewPrescriptionModal()" style="margin: 24px auto 0;">
            Create Your First Prescription
        </button>
    </div>
<?php else: ?>
    <div style="overflow-x: auto;">
        <table class="data-table" id="prescriptionsTable">
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
            <tbody>
                <?php foreach ($prescriptions as $prescription): ?>
                    <tr class="prescription-row" data-id="<?php echo $prescription['id']; ?>">
                        <td>
                            <div class="patient-info">
                                <?php echo htmlspecialchars($prescription['patient_name']); ?>
                                <?php if ($prescription['age']): ?>
                                    <span class="patient-age">(<?php echo $prescription['age']; ?>)</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="medications-cell">
                                <?php echo nl2br(htmlspecialchars($prescription['medications'])); ?>
                            </div>
                        </td>
                        <td>
                            <div class="diagnosis-cell">
                                <?php echo htmlspecialchars($prescription['diagnosis']); ?>
                            </div>
                        </td>
                        <td>
                            <div class="instructions-cell">
                                <?php echo $prescription['instructions'] ? nl2br(htmlspecialchars($prescription['instructions'])) : '<em>No instructions</em>'; ?>
                            </div>
                        </td>
                        <td>
                            <div class="date-cell">
                                <?php echo date('M j, Y', strtotime($prescription['issue_date'])); ?>
                            </div>
                        </td>
                        <td>
                            <div class="doctor-cell">
                                <?php echo htmlspecialchars($prescription['doctor_name']); ?>
                            </div>
                        </td>
                        <td>
                            <div class="prescription-actions">
                                <button class="btn-action btn-view" onclick="viewPrescription(<?php echo $prescription['id']; ?>)">
                                    View
                                </button>
                                <button class="btn-action btn-edit" onclick="editPrescription(<?php echo $prescription['id']; ?>)">
                                    Edit
                                </button>
                                <button class="btn-action btn-delete" onclick="deletePrescription(<?php echo $prescription['id']; ?>)">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Prescription Modal -->
<div id="prescriptionModal" class="fullscreen-modal-overlay" style="display: none;">
    <div class="fullscreen-modal-content" style="max-width: 800px;">
        <div class="fullscreen-modal-header">
            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <h2 style="font-size: 1.5rem; font-weight: 600; margin: 0;">💊 New Prescription</h2>
            </div>
            <button class="fullscreen-modal-close" onclick="closePrescriptionModal()">&times;</button>
        </div>
        <div class="fullscreen-modal-body" id="prescriptionModalContent">
            <!-- Patient Safety Info -->
            <div id="patientSafetyInfo" class="safety-info" style="display: none;">
                <h3 style="font-size: 1.25rem; font-weight: 600; color: #1f2937; margin-bottom: 20px;">👤 Patient Safety Information</h3>
                <div class="safety-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                    <div class="safety-item">
                        <div class="info-label">Patient Name</div>
                        <div class="info-value" id="patientName"></div>
                    </div>
                    <div class="safety-item">
                        <div class="info-label">Age</div>
                        <div class="info-value" id="patientAge"></div>
                    </div>
                    <div class="safety-item">
                        <div class="info-label">Allergies</div>
                        <div class="info-value warning-text" id="patientAllergies"></div>
                    </div>
                    <div class="safety-item">
                        <div class="info-label">Current Medications</div>
                        <div class="info-value info-text" id="patientMeds"></div>
                    </div>
                </div>
            </div>
            
            <!-- Prescription Form -->
            <form id="prescriptionForm">
                <input type="hidden" id="prescriptionId" name="prescription_id">
                <input type="hidden" id="patientId" name="patient_id">
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px;">
                    <div class="form-group">
                        <label for="patientSelect">Patient:</label>
                        <select id="patientSelect" name="patient_id" required class="form-control">
                            <option value="">Select Patient...</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="issueDate">Issue Date:</label>
                        <input type="date" id="issueDate" name="issue_date" required class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="diagnosis">Diagnosis:</label>
                    <textarea id="diagnosis" name="diagnosis" rows="3" required placeholder="Reason for prescription..." class="form-control"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="medications">Medications:</label>
                    <textarea id="medications" name="medications" rows="4" required placeholder="e.g., Amoxicillin 500mg - 3 times daily for 7 days" class="form-control"></textarea>
                    <small style="color: #6b7280; font-size: 0.85rem; margin-top: 8px; display: block;">Enter medication details including dosage, frequency, and duration</small>
                </div>
                
                <div class="form-group">
                    <label for="instructions">Instructions:</label>
                    <textarea id="instructions" name="instructions" rows="3" placeholder="Additional patient instructions..." class="form-control"></textarea>
                </div>
            </form>
        </div>
        <div class="fullscreen-modal-footer" style="display: flex; justify-content: flex-end; gap: 15px; padding: 20px;">
            <button type="button" class="btn-cancel" onclick="closePrescriptionModal()">Cancel</button>
            <button type="button" class="btn-primary" onclick="savePrescription()">Save Prescription</button>
        </div>
    </div>
</div>

<style>
.safety-info {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 30px;
}

.safety-info h3 {
    margin: 0 0 20px 0;
    color: #15803d;
    font-size: 1.25rem;
}

.safety-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.safety-item {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.info-label {
    font-size: 0.95rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
}

.info-value {
    font-size: 0.95rem;
    color: #1f2937;
    padding: 10px 12px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    min-height: 20px;
}

.warning-text {
    color: #dc2626;
    font-weight: 600;
    background: #fee2e2;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.9rem;
}

.info-text {
    color: #059669;
    font-weight: 500;
    background: #ecfdf5;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.9rem;
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
    font-size: 1rem;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.2s;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: #0284c7;
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
}

.form-control::placeholder {
    color: #9ca3af;
}

.btn-cancel {
    background: #f3f4f6;
    color: #374151;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    font-size: 0.95rem;
    transition: all 0.2s;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.btn-cancel:hover {
    background: #e5e7eb;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}

.btn-primary {
    background: #10b981;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.2s;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
}

.btn-primary:hover {
    background: #059669;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3);
}
</style>

<script>
let currentPrescriptionId = null;

// Load patients for dropdown
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
                option.textContent = `${patient.patient_name} (${patient.age})`;
                select.appendChild(option);
            });
        }
    });
}

// Open new prescription modal
function openNewPrescriptionModal() {
    currentPrescriptionId = null;
    document.getElementById('modalTitle').textContent = 'New Prescription';
    document.getElementById('prescriptionForm').reset();
    document.getElementById('prescriptionId').value = '';
    document.getElementById('issueDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('patientSafetyInfo').style.display = 'none';
    loadPatients();
    document.getElementById('prescriptionModal').style.display = 'flex';
}

// Open modal for specific patient
function openPatientPrescriptionModal(patientId) {
    currentPrescriptionId = null;
    document.getElementById('modalTitle').textContent = 'New Prescription';
    document.getElementById('prescriptionForm').reset();
    document.getElementById('prescriptionId').value = '';
    document.getElementById('patientId').value = patientId;
    document.getElementById('patientSelect').value = patientId;
    document.getElementById('issueDate').value = new Date().toISOString().split('T')[0];
    
    // Load patient safety info
    loadPatientSafetyInfo(patientId);
    
    document.getElementById('prescriptionModal').style.display = 'flex';
}

// Load patient safety information
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
            
            // Update safety info display
            document.getElementById('patientName').textContent = patient.patient_name || 'N/A';
            document.getElementById('patientAge').textContent = patient.age || 'N/A';
            document.getElementById('patientAllergies').textContent = patient.allergies || 'None recorded';
            document.getElementById('patientMeds').textContent = patient.current_medications || 'None recorded';
            
            document.getElementById('patientSafetyInfo').style.display = 'block';
        }
    });
}

// Handle patient selection change
document.getElementById('patientSelect').addEventListener('change', function() {
    const patientId = this.value;
    if (patientId) {
        loadPatientSafetyInfo(patientId);
        document.getElementById('patientId').value = patientId;
    } else {
        document.getElementById('patientSafetyInfo').style.display = 'none';
    }
});

// View prescription
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
            
            // Fill form
            document.getElementById('prescriptionId').value = prescriptionId;
            document.getElementById('patientId').value = prescription.patient_id;
            document.getElementById('patientSelect').value = prescription.patient_id;
            document.getElementById('issueDate').value = prescription.issue_date;
            document.getElementById('diagnosis').value = prescription.diagnosis;
            document.getElementById('medications').value = prescription.medications;
            document.getElementById('instructions').value = prescription.instructions || '';
            
            // Load patient safety info
            loadPatientSafetyInfo(prescription.patient_id);
            
            document.getElementById('prescriptionModal').style.display = 'flex';
        } else {
            alert('Error: ' + data.message);
        }
    });
}

// Edit prescription
function editPrescription(prescriptionId) {
    viewPrescription(prescriptionId);
}

// Save prescription
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

// Delete prescription
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

// Close modal
function closePrescriptionModal() {
    document.getElementById('prescriptionModal').style.display = 'none';
}

// Search functionality
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

// Load patients when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadPatients();
});
</script>

<?php require_once 'includes/dentist_layout_end.php'; ?>