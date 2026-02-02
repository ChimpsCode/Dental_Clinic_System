<?php
$pageTitle = 'Dentist Dashboard';
require_once 'includes/dentist_layout_start.php';

try {
    require_once 'config/database.php';
    
    // Get queue statistics
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM queue GROUP BY status");
    $queueStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $waitingCount = $queueStats['waiting'] ?? 0;
    $inProcedureCount = $queueStats['in_procedure'] ?? 0;
    $completedToday = $queueStats['completed'] ?? 0;
    $onHoldCount = $queueStats['on_hold'] ?? 0;
    
    // Get today's completed count
    $stmt = $pdo->query("SELECT COUNT(*) FROM queue WHERE status = 'completed' AND DATE(updated_at) = CURDATE()");
    $completedToday = $stmt->fetchColumn();
    
// Get patients currently in queue - same query logic as queue management
    $stmt = $pdo->query("
        SELECT q.*, p.full_name, p.phone, p.gender, p.age
        FROM queue q
        JOIN patients p ON q.patient_id = p.id
        WHERE q.status IN ('waiting', 'in_procedure')
        AND DATE(q.created_at) = CURDATE()
        ORDER BY q.priority ASC, q.queue_time ASC
        LIMIT 10
    ");
    $queuePatients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent patients (last 30 days)
    $stmt = $pdo->query("SELECT COUNT(*) FROM patients WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $newPatientsMonth = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $waitingCount = 0;
    $inProcedureCount = 0;
    $completedToday = 0;
    $onHoldCount = 0;
    $queuePatients = [];
    $newPatientsMonth = 0;
}
?>

<!-- Summary Cards -->
<div class="summary-cards">
    <div class="summary-card">
        <div class="summary-icon yellow">⏰</div>
        <div class="summary-info">
            <h3><?php echo $waitingCount; ?></h3>
            <p>Waiting</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon blue" style="background: #dbeafe; color: #1d4ed8;">⚙️</div>
        <div class="summary-info">
            <h3><?php echo $inProcedureCount; ?></h3>
            <p>In Procedure</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon green">✓</div>
        <div class="summary-info">
            <h3><?php echo $completedToday; ?></h3>
            <p>Completed Today</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon red" style="background: #fef3c7; color: #b45309;">⏸️</div>
        <div class="summary-info">
            <h3><?php echo $onHoldCount; ?></h3>
            <p>On Hold</p>
        </div>
    </div>
</div>

<div class="two-column">
    <div class="left-column">
        <!-- Treatment Queue -->
        <div class="section-card">
            <h2 class="section-title">⚕️ Treatment Queue</h2>
            <?php if (empty($queuePatients)): ?>
                <div style="text-align: center; padding: 40px 20px; color: #6b7280;">
                    <p>No patients in queue</p>
                </div>
            <?php else: ?>
                <div class="patient-list">
                    <?php foreach ($queuePatients as $patient): ?>
                        <div class="patient-item">
                            <div class="patient-info">
                                <div class="patient-name"><?php echo htmlspecialchars($patient['full_name']); ?></div>
                                <div class="patient-details">
                                    <span class="status-badge" style="background: <?php echo $patient['status'] === 'in_procedure' ? '#dbeafe' : '#fef3c7'; ?>; color: <?php echo $patient['status'] === 'in_procedure' ? '#1d4ed8' : '#92400e'; ?>;">
                                        <?php echo $patient['status'] === 'in_procedure' ? 'In Progress' : 'Waiting'; ?>
                                    </span>
                                    <span class="patient-time"><?php echo htmlspecialchars($patient['treatment_type'] ?? 'General'); ?></span>
                                </div>
                                <div class="patient-treatment"><?php echo htmlspecialchars($patient['teeth_numbers'] ? 'Teeth: ' . $patient['teeth_numbers'] : 'All teeth'); ?></div>
                            </div>
                            <div class="patient-actions">
                                <button onclick="viewPatientDetails(<?php echo $patient['patient_id']; ?>)" class="action-btn" style="background: #6b7280; color: white;">View</button>
                                <?php if ($patient['status'] === 'waiting'): ?>
                                    <button onclick="startProcedure(<?php echo $patient['id']; ?>)" class="action-btn" style="background: #22c55e; color: white;">Start</button>
                                <?php else: ?>
                                    <button onclick="completeProcedure(<?php echo $patient['id']; ?>)" class="action-btn" style="background: #2563eb; color: white;">Complete</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Notifications & Quick Actions -->
    <div class="right-column">
        <div class="notification-box">
            <h3>📊 Quick Stats</h3>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <div style="background: #f3f4f6; padding: 12px; border-radius: 8px;">
                    <div style="font-size: 1.5rem; font-weight: 700; color: #059669;"><?php echo $newPatientsMonth; ?></div>
                    <div style="font-size: 0.85rem; color: #6b7280;">New Patients (30 days)</div>
                </div>
                <div style="background: #f3f4f6; padding: 12px; border-radius: 8px;">
                    <div style="font-size: 1.5rem; font-weight: 700; color: #0891b2;"><?php echo count($queuePatients); ?></div>
                    <div style="font-size: 0.85rem; color: #6b7280;">Active Queue</div>
                </div>
            </div>
        </div>
        
        <div class="notification-box">
            <h3>🚀 Quick Actions</h3>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <a href="dentist_patients.php" class="btn-primary" style="width: 100%; text-align: center; text-decoration: none;">View All Patients</a>
                <a href="dentist_appointments.php" class="btn-primary" style="width: 100%; text-align: center; text-decoration: none;">View Appointments</a>
            </div>
        </div>
    </div>
</div>

<script>
// Queue actions
function startProcedure(queueId) {
    fetch('queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=start_procedure&queue_id=' + queueId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function completeProcedure(queueId) {
    fetch('queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=complete&queue_id=' + queueId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

// View patient details - Same modal as dentist_patients.php
function viewPatientDetails(patientId) {
    fetch('patient_record_details.php?id=' + patientId)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const p = data.patient;
            const m = data.medical_history || {};
            const d = data.dental_history || {};
            const q = data.queue_item || {};
            
            const allergies = m.allergies || 'None';
            const medications = m.current_medications || 'None';
            const medicalConditions = m.medical_conditions || 'None';
            
            document.getElementById('dentistModalPatientName').innerText = p.full_name || 'Unknown';
            
            // Check for medical alerts
            const hasMedicalAlert = allergies === 'Yes' || 
                                   medicalConditions.toLowerCase().includes('diabetes') ||
                                   medicalConditions.toLowerCase().includes('heart') ||
                                   medicalConditions.toLowerCase().includes('blood pressure') ||
                                   medicalConditions.toLowerCase().includes('asthma');
            
            document.getElementById('patientModalContent').innerHTML = `
                <!-- Patient Basic Info -->
                <div class="patient-info-grid">
                    <div class="patient-info-item">
                        <div class="patient-info-label">Full Name</div>
                        <div class="patient-info-value">${p.full_name || 'N/A'}</div>
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
                        <div class="patient-info-label">Date of Birth</div>
                        <div class="patient-info-value">${p.date_of_birth || 'N/A'}</div>
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
                            <div class="patient-info-value">${q.teeth_numbers || 'None'}</div>
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

                <!-- Dental History -->
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
                        <div class="patient-info-item" style="grid-column: 1 / -1;">
                            <div class="patient-info-label">Previous Treatments</div>
                            <div class="patient-info-value">${d.previous_treatments || 'None'}</div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px;">
                    <button onclick="closePatientModal()" class="btn-cancel">Close</button>
                </div>
            `;
            
            document.getElementById('patientModal').classList.add('active');
        } else {
            alert('Error loading patient details: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to load patient details');
    });
}

function closePatientModal() {
    const modal = document.getElementById('patientModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const patientModal = document.getElementById('patientModal');
    if (patientModal) {
        patientModal.addEventListener('click', function(e) {
            if (e.target.id === 'patientModal') {
                closePatientModal();
            }
        });
    }
});
</script>

<!-- Patient Details Modal -->
<div id="patientModal" class="fullscreen-modal-overlay">
    <div class="fullscreen-modal">
        <div class="fullscreen-modal-header">
            <h3 id="dentistModalPatientName" style="margin: 0; font-size: 1.25rem; font-weight: 600;">Patient Details</h3>
            <button onclick="closePatientModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">×</button>
        </div>
        <div class="fullscreen-modal-body" id="patientModalContent">
            <!-- Content loaded dynamically -->
        </div>
    </div>
</div>

<!-- Modal Styles -->
<style>
.fullscreen-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
}

.fullscreen-modal-overlay.active {
    display: flex;
}

.fullscreen-modal {
    background: white;
    border-radius: 16px;
    max-width: 800px;
    max-height: 90vh;
    width: 90%;
    overflow-y: auto;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.fullscreen-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #e5e7eb;
}

.fullscreen-modal-body {
    padding: 24px;
}

.patient-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.patient-info-item {
    display: flex;
    flex-direction: column;
}

.patient-info-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 4px;
}

.patient-info-value {
    font-size: 0.875rem;
    color: #111827;
}

.medical-alert {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.medical-alert-title {
    font-size: 1rem;
    font-weight: 600;
    color: #dc2626;
    margin-bottom: 16px;
}

.medical-alert-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.medical-alert-item {
    display: flex;
    flex-direction: column;
}

.danger {
    color: #dc2626 !important;
    font-weight: 600;
}

.btn-cancel {
    background: #6b7280;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.875rem;
}

.btn-cancel:hover {
    background: #4b5563;
}
</style>

<?php require_once 'includes/dentist_layout_end.php'; ?>
