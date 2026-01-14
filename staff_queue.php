<?php
$pageTitle = 'Queue Management';
require_once 'includes/staff_layout_start.php';

try {
    require_once 'config/database.php';
    
    // Get queue/patients data
    $stmt = $pdo->query("SELECT * FROM patients WHERE status IN ('active', 'waiting', 'in_procedure') ORDER BY created_at DESC");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get completed for today
    $stmtCompleted = $pdo->query("SELECT * FROM patients WHERE last_visit_date = CURDATE() AND status = 'completed'");
    $completedToday = $stmtCompleted->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $patients = [];
    $completedToday = [];
}
?>

<!-- Queue Stats -->
<div class="summary-cards">
    <div class="summary-card">
        <div class="summary-icon yellow">⏰</div>
        <div class="summary-info">
            <h3><?php echo count(array_filter($patients, fn($p) => $p['status'] === 'waiting')); ?></h3>
            <p>Currently Waiting</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon blue" style="background: #fef3c7; color: #d97706;">⚕️</div>
        <div class="summary-info">
            <h3><?php echo count(array_filter($patients, fn($p) => $p['status'] === 'in_procedure')); ?></h3>
            <p>In Procedure</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon green">✓</div>
        <div class="summary-info">
            <h3><?php echo count($completedToday); ?></h3>
            <p>Completed Today</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon red" style="background: #fee2e2; color: #dc2626;">⚠️</div>
        <div class="summary-info">
            <h3><?php echo count(array_filter($patients, fn($p) => $p['status'] === 'cancelled')); ?></h3>
            <p>Cancelled Today</p>
        </div>
    </div>
</div>

<!-- Search & Filters -->
<div class="search-filters">
    <input type="text" id="searchInput" placeholder="Search by name, code, or phone..." class="search-input" style="flex: 1;">
    <select id="statusFilter" class="filter-select">
        <option value="">All Status</option>
        <option value="waiting">Waiting</option>
        <option value="in_procedure">In Procedure</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
    </select>
    <select id="serviceFilter" class="filter-select">
        <option value="">All Services</option>
        <option value="Oral Prophylaxis">Oral Prophylaxis</option>
        <option value="Tooth Extraction">Tooth Extraction</option>
        <option value="Root Canal">Root Canal</option>
        <option value="Dental Filling">Dental Filling</option>
        <option value="Denture">Denture</option>
        <option value="Braces">Braces</option>
        <option value="Consultation">Consultation</option>
    </select>
    <button class="btn-primary" onclick="openAddToQueueModal()">+ Add to Queue</button>
</div>

<!-- Queue Sections -->
<div class="two-column">
    <div class="left-column">
        <!-- Waiting Queue -->
        <div class="section-card">
            <h2 class="section-title">⏳ Waiting Queue</h2>
            <div class="patient-list" id="waitingList">
                <?php 
                $waitingPatients = array_filter($patients, fn($p) => $p['status'] === 'waiting');
                if (empty($waitingPatients)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: #6b7280;">
                        <p>No patients waiting</p>
                    </div>
                <?php else: 
                    foreach ($waitingPatients as $patient): ?>
                    <div class="patient-item" 
                         data-name="<?php echo strtolower($patient['full_name']); ?>" 
                         data-code="<?php echo strtolower($patient['patient_code'] ?? ''); ?>">
                        <div class="patient-info">
                            <div class="patient-name"><?php echo htmlspecialchars($patient['full_name']); ?></div>
                            <div class="patient-details">
                                <span class="status-badge" style="background: #fef3c7; color: #d97706; font-size: 0.75rem;">Waiting</span>
                                <span style="color: #6b7280; margin-left: 8px;">
                                    <?php echo htmlspecialchars($patient['patient_code'] ?? ''); ?> • 
                                    <?php echo htmlspecialchars($patient['treatment_type'] ?? 'Consultation'); ?>
                                </span>
                            </div>
                            <?php if (!empty($patient['teeth_numbers'])): ?>
                                <div class="patient-treatment" style="font-size: 0.85rem; color: #6b7280;">
                                    Teeth: <?php echo htmlspecialchars($patient['teeth_numbers']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="patient-actions">
                            <button onclick="callPatient(<?php echo $patient['id']; ?>)" class="action-btn icon" title="Call">📞</button>
                            <button onclick="moveToProcedure(<?php echo $patient['id']; ?>)" class="action-btn icon" title="Start Procedure">▶️</button>
                            <button onclick="viewPatient(<?php echo $patient['id']; ?>)" class="action-btn icon" title="View Record">👁️</button>
                            <button onclick="skipPatient(<?php echo $patient['id']; ?>)" class="action-btn icon" title="Skip">⏭️</button>
                        </div>
                    </div>
                    <?php endforeach; 
                endif; ?>
            </div>
        </div>

        <!-- In Procedure -->
        <div class="section-card">
            <h2 class="section-title">⚕️ In Procedure</h2>
            <div class="patient-list" id="procedureList">
                <?php 
                $procedurePatients = array_filter($patients, fn($p) => $p['status'] === 'in_procedure');
                if (empty($procedurePatients)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: #6b7280;">
                        <p>No patients in procedure</p>
                    </div>
                <?php else: 
                    foreach ($procedurePatients as $patient): ?>
                    <div class="patient-item" 
                         data-name="<?php echo strtolower($patient['full_name']); ?>" 
                         style="border-left: 4px solid #0ea5e9;">
                        <div class="patient-info">
                            <div class="patient-name"><?php echo htmlspecialchars($patient['full_name']); ?></div>
                            <div class="patient-details">
                                <span class="status-badge" style="background: #dcfce7; color: #15803d; font-size: 0.75rem;">In Chair</span>
                                <span style="color: #6b7280; margin-left: 8px;">
                                    <?php echo htmlspecialchars($patient['treatment_type'] ?? ''); ?>
                                </span>
                            </div>
                            <?php if (!empty($patient['teeth_numbers'])): ?>
                                <div class="patient-treatment" style="font-size: 0.85rem; color: #6b7280;">
                                    Teeth: <?php echo htmlspecialchars($patient['teeth_numbers']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="patient-actions">
                            <button onclick="completeTreatment(<?php echo $patient['id']; ?>)" class="action-btn" style="background: #22c55e; color: white; padding: 6px 12px;" title="Complete">✓ Complete</button>
                            <button onclick="viewPatient(<?php echo $patient['id']; ?>)" class="action-btn icon" title="View Record">👁️</button>
                        </div>
                    </div>
                    <?php endforeach; 
                endif; ?>
            </div>
        </div>

        <!-- Completed Today -->
        <div class="section-card">
            <h2 class="section-title">✅ Completed Today</h2>
            <div class="patient-list" id="completedList">
                <?php 
                $completedFiltered = array_filter($completedToday, fn($p) => $p['status'] === 'completed');
                if (empty($completedFiltered)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: #6b7280;">
                        <p>No patients completed today</p>
                    </div>
                <?php else: 
                    foreach ($completedFiltered as $patient): ?>
                    <div class="patient-item" style="opacity: 0.7;">
                        <div class="patient-info">
                            <div class="patient-name"><?php echo htmlspecialchars($patient['full_name']); ?></div>
                            <div class="patient-details">
                                <span class="status-badge" style="background: #e5e7eb; color: #6b7280; font-size: 0.75rem;">Completed</span>
                                <span style="color: #6b7280; margin-left: 8px;">
                                    <?php echo htmlspecialchars($patient['treatment_type'] ?? ''); ?>
                                </span>
                            </div>
                        </div>
                        <div class="patient-actions">
                            <button onclick="viewPatient(<?php echo $patient['id']; ?>)" class="action-btn icon" title="View Record">👁️</button>
                        </div>
                    </div>
                    <?php endforeach; 
                endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Queue History -->
    <div class="right-column">
        <div class="notification-box">
            <h3>📋 Quick Actions</h3>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <button class="btn-primary" style="width: 100%;" onclick="openAddToQueueModal()">+ Add to Queue</button>
                <button class="btn-primary" style="width: 100%;" onclick="viewAllPatients()">View All Patients</button>
                <button class="btn-primary" style="width: 100%;" onclick="exportQueue()">Export Queue</button>
            </div>
        </div>
        
        <div class="notification-box">
            <h3>📊 Queue History</h3>
            <div style="max-height: 300px; overflow-y: auto;">
                <?php 
                $cancelledPatients = array_filter($patients, fn($p) => $p['status'] === 'cancelled');
                if (empty($cancelledPatients)): ?>
                    <p style="color: #6b7280; text-align: center;">No cancelled patients today</p>
                <?php else: ?>
                    <?php foreach ($cancelledPatients as $patient): ?>
                        <div class="notification-item" style="border-left: 3px solid #dc2626;">
                            <div class="notification-title"><?php echo htmlspecialchars($patient['full_name']); ?></div>
                            <div class="notification-detail">
                                <?php echo htmlspecialchars($patient['treatment_type'] ?? 'Cancelled'); ?>
                            </div>
                            <button onclick="requeuePatient(<?php echo $patient['id']; ?>)" 
                                    style="margin-top: 8px; padding: 4px 8px; background: #84cc16; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">
                                Re-queue
                            </button>
                        </div>
                    <?php endforeach; 
                endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add to Queue Modal -->
<div id="addToQueueModal" class="modal-overlay">
    <div class="modal" style="max-width: 600px;">
        <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Add Patient to Queue</h2>
        <form id="addToQueueForm">
            <div class="form-group">
                <label>Patient *</label>
                <input type="text" name="full_name" required class="form-control" placeholder="Enter patient name">
                <small style="color: #6b7280;">Type to search existing patients or enter new name</small>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" class="form-control" placeholder="09XX-XXX-XXXX">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Age</label>
                    <input type="number" name="age" class="form-control" placeholder="Age" min="1" max="120">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Gender</label>
                    <select name="gender" class="form-control">
                        <option value="">Select</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Treatment Type *</label>
                    <select name="treatment_type" required class="form-control">
                        <option value="">Select Service</option>
                        <option value="Oral Prophylaxis">Oral Prophylaxis (Cleaning)</option>
                        <option value="Tooth Extraction">Tooth Extraction</option>
                        <option value="Root Canal">Root Canal Treatment</option>
                        <option value="Dental Filling">Dental Filling</option>
                        <option value="Denture">Denture (Complete/Partial)</option>
                        <option value="Braces">Braces/Orthodontics</option>
                        <option value="Teeth Whitening">Teeth Whitening</option>
                        <option value="Crowns & Bridges">Crowns & Bridges</option>
                        <option value="Dental Implant">Dental Implant</option>
                        <option value="Consultation">Consultation/Checkup</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Teeth Numbers (if applicable)</label>
                <input type="text" name="teeth_numbers" class="form-control" placeholder="e.g., 14, 16, 18">
                <small style="color: #6b7280;">For extractions, fillings, root canals, etc.</small>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Medical History</label>
                    <textarea name="medical_history" rows="2" class="form-control" placeholder="Any allergies, medical conditions..."></textarea>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Notes</label>
                    <textarea name="notes" rows="2" class="form-control" placeholder="Additional notes..."></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeAddToQueueModal()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-primary">Add to Queue</button>
            </div>
        </form>
    </div>
</div>

<!-- Patient Record Modal -->
<div id="patientRecordModal" class="modal-overlay">
    <div class="modal" style="max-width: 700px;">
        <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Patient Record</h2>
        <div id="patientRecordContent"></div>
        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px;">
            <button onclick="closePatientRecordModal()" class="btn-cancel">Close</button>
            <button onclick="editPatient()" class="btn-primary">Edit Patient</button>
        </div>
    </div>
</div>

<script>
const patients = <?php echo json_encode($patients); ?>;

// Search and filter functionality
document.getElementById('searchInput').addEventListener('input', filterQueue);
document.getElementById('statusFilter').addEventListener('change', filterQueue);
document.getElementById('serviceFilter').addEventListener('change', filterQueue);

function filterQueue() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    const service = document.getElementById('serviceFilter').value;
    
    document.querySelectorAll('.patient-item').forEach(item => {
        const matchSearch = !search || 
            item.dataset.name.includes(search) || 
            (item.dataset.code && item.dataset.code.includes(search));
        const matchStatus = !status || item.textContent.includes(status === 'in_procedure' ? 'In Chair' : 
                                                                       status === 'waiting' ? 'Waiting' : 
                                                                       status === 'completed' ? 'Completed' : '');
        const matchService = !service || item.textContent.includes(service);
        
        item.style.display = (matchSearch && matchStatus && matchService) ? 'flex' : 'none';
    });
}

// Add to Queue Modal
function openAddToQueueModal() {
    document.getElementById('addToQueueModal').style.display = 'flex';
}

function closeAddToQueueModal() {
    document.getElementById('addToQueueModal').style.display = 'none';
    document.getElementById('addToQueueForm').reset();
}

// Add to Queue form submission
document.getElementById('addToQueueForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    alert('Patient added to queue!\n\n' + 
          'Name: ' + data.full_name + '\n' + 
          'Service: ' + data.treatment_type + '\n' + 
          'Teeth: ' + (data.teeth_numbers || 'N/A'));
    
    closeAddToQueueModal();
    
    // In production, this would save to database
    // Example fetch call:
    // fetch('add_to_queue.php', { method: 'POST', body: formData })
});

// Queue Actions
function callPatient(patientId) {
    alert('Calling patient ID: ' + patientId + '\n\n(This will open calling interface)');
}

function moveToProcedure(patientId) {
    if (confirm('Move this patient to "In Procedure"?')) {
        alert('Patient moved to procedure!\n\nDentist will be notified.');
    }
}

function skipPatient(patientId) {
    if (confirm('Skip this patient? They will be moved to bottom of queue.')) {
        alert('Patient skipped!\n\nThey will be re-added to end of waiting list.');
    }
}

function completeTreatment(patientId) {
    if (confirm('Mark treatment as complete?\n\nPatient will be moved to "Completed" and removed from queue.')) {
        alert('Treatment completed!\n\nPatient removed from queue.');
    }
}

function requeuePatient(patientId) {
    if (confirm('Re-queue this patient?')) {
        alert('Patient re-queued!\n\nThey will be added to waiting list.');
    }
}

// Patient Record Modal
function viewPatient(patientId) {
    const patient = patients.find(p => p.id === patientId);
    if (!patient) return;
    
    document.getElementById('patientRecordContent').innerHTML = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <h3 style="font-size: 1.1rem; margin-bottom: 16px;">Personal Information</h3>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Patient Code:</span> <span style="font-weight: 500;">${patient.patient_code || 'N/A'}</span></div>
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Full Name:</span> <span style="font-weight: 500;">${patient.full_name}</span></div>
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Age:</span> <span style="font-weight: 500;">${patient.age} years</span></div>
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Gender:</span> <span style="font-weight: 500;">${patient.gender || 'N/A'}</span></div>
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Phone:</span> <span style="font-weight: 500;">${patient.phone || 'N/A'}</span></div>
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Status:</span> 
                        <span class="status-badge" style="background: ${patient.status === 'active' ? '#d1fae5' : '#f3f4f6'}; color: ${patient.status === 'active' ? '#065f46' : '#6b7280'};">
                            ${patient.status}
                        </span>
                    </div>
                </div>
            </div>
            <div>
                <h3 style="font-size: 1.1rem; margin-bottom: 16px;">Treatment Information</h3>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Treatment Type:</span> <span style="font-weight: 500;">${patient.treatment_type || 'N/A'}</span></div>
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Teeth Numbers:</span> <span style="font-weight: 500;">${patient.teeth_numbers || 'N/A'}</span></div>
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Last Visit:</span> <span style="font-weight: 500;">${patient.last_visit_date || 'N/A'}</span></div>
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Medical History:</span></div>
                    <div style="background: #f9fafb; padding: 12px; border-radius: 8px; font-size: 0.9rem;">
                        ${patient.medical_history || 'No medical history on file'}
                    </div>
                    ${patient.notes ? `<div><span style="color: #6b7280; font-size: 0.9rem;">Notes:</span></div>
                        <div style="background: #fefce8; padding: 12px; border-radius: 8px; font-size: 0.9rem;">
                            ${patient.notes}
                        </div>` : ''}
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('patientRecordModal').style.display = 'flex';
}

function closePatientRecordModal() {
    document.getElementById('patientRecordModal').style.display = 'none';
}

function editPatient() {
    closePatientRecordModal();
    alert('Opening patient edit form...\n\n(This will redirect to New Admission)');
}

// Quick Actions
function viewAllPatients() {
    window.location.href = 'patient-records.php';
}

function exportQueue() {
    alert('Export queue as CSV/Excel...\n\n(Feature coming soon!)');
}

// Close modals on outside click
document.getElementById('addToQueueModal').addEventListener('click', function(e) {
    if (e.target === this) closeAddToQueueModal();
});

document.getElementById('patientRecordModal').addEventListener('click', function(e) {
    if (e.target === this) closePatientRecordModal();
});
</script>

<?php require_once 'includes/staff_layout_end.php'; ?>