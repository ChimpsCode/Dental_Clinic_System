<?php
$pageTitle = 'Dentist Dashboard';
require_once '../../config/database.php';
require_once '../../includes/dentist_layout_start.php';

try {
    require_once '../../config/database.php';
    
    // Get queue statistics - use only status_id column
    $stmt = $pdo->query("
        SELECT qs.status_name, COUNT(*) as count 
        FROM queue q 
        LEFT JOIN queue_statuses qs ON q.status_id = qs.id 
        WHERE q.status_id IN (1, 2, 3, 4, 5, 6) AND q.is_archived = 0
        GROUP BY q.status_id
    ");
    $statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $waitingCount = 0;
    $inProcedureCount = 0;
    $completedToday = 0;
    $onHoldCount = 0;
    foreach ($statusCounts as $sc) {
        switch ($sc['status_name'] ?? 'waiting') {
            case 'waiting': $waitingCount = (int)$sc['count']; break;
            case 'in_procedure': $inProcedureCount = (int)$sc['count']; break;
            case 'completed': $completedToday = (int)$sc['count']; break;
            case 'on_hold': $onHoldCount = (int)$sc['count']; break;
        }
    }
    
    // Get today's completed count
    $stmt = $pdo->query("SELECT COUNT(*) FROM queue WHERE status_id = 3 AND DATE(completed_at) = CURDATE()");
    $completedToday = $stmt->fetchColumn();
    
    // Get patients currently in queue
    $stmt = $pdo->query("
        SELECT q.*, COALESCE(p.first_name, 'Unknown') as first_name, COALESCE(p.middle_name, '') as middle_name, COALESCE(p.last_name, '') as last_name, COALESCE(p.suffix, '') as suffix, COALESCE(p.phone, '') as phone, COALESCE(p.gender, '') as gender, COALESCE(qs.status_name, 'waiting') as status_text
        FROM queue q
        LEFT JOIN patients p ON CAST(q.patient_id AS UNSIGNED) = p.id
        LEFT JOIN queue_statuses qs ON q.status_id = qs.id
        WHERE q.status_id IN (1, 2) AND q.is_archived = 0
        ORDER BY q.priority ASC, q.queue_time DESC
        LIMIT 10
    ");
    $queuePatients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build full_name for each patient from separate fields
    foreach ($queuePatients as &$patient) {
        $parts = array_filter([
            $patient['first_name'] ?? '',
            $patient['middle_name'] ?? '',
            $patient['last_name'] ?? '',
            $patient['suffix'] ?? ''
        ]);
        $patient['full_name'] = implode(' ', $parts);
    }
    unset($patient); // Break reference
    
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
        <?php 
        // Find patient currently in procedure
        $inProcedurePatient = null;
        $nextPatient = null;
        $upcomingPatients = [];
        foreach ($queuePatients as $index => $patient) {
if (($patient['status_text'] ?? '') === 'in_procedure') {
            $inProcedurePatient = $patient;
        } elseif (($patient['status_text'] ?? '') === 'waiting' && !$nextPatient) {
            $nextPatient = $patient;
        } elseif (($patient['status_text'] ?? '') === 'waiting') {
                $upcomingPatients[] = $patient;
            }
        }
        ?>
        
        <?php if ($inProcedurePatient): ?>
        <!-- IN PROCEDURE PATIENT - Priority Display -->
        <div class="section-card" style="border: 2px solid #3b82f6; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 class="section-title" style="margin: 0; color: #1e40af;">
                    <span style="font-size: 1.5rem;">⚕️</span> IN PROCEDURE
                </h2>
                <span style="background: #3b82f6; color: white; padding: 6px 16px; border-radius: 20px; font-weight: 700; font-size: 0.9rem; animation: pulse 2s infinite;">
                    ACTIVE NOW
                </span>
            </div>
            
            <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 700; color: #111827; margin-bottom: 8px;">
                            <?php 
                            echo htmlspecialchars($inProcedurePatient['first_name'] ?? ''); 
                            if (!empty($inProcedurePatient['middle_name'])) {
                                echo '<span style="font-weight: 400; color: #6b7280;"> ' . htmlspecialchars($inProcedurePatient['middle_name']) . '</span>';
                            }
                            echo ' ' . htmlspecialchars($inProcedurePatient['last_name'] ?? '');
                            ?>
                        </div>
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <span style="background: #dbeafe; color: #1e40af; padding: 4px 12px; border-radius: 16px; font-size: 0.85rem; font-weight: 600;">
                                <?php echo htmlspecialchars($inProcedurePatient['gender'] ?? 'Unknown'); ?>
                            </span>
                            <span style="color: #6b7280; font-size: 0.9rem;">
                                <?php echo htmlspecialchars($inProcedurePatient['age'] ?? '--'); ?> years old
                            </span>
                            <span style="color: #6b7280; font-size: 0.9rem;">
                                📞 <?php echo htmlspecialchars($inProcedurePatient['phone'] ?? 'No phone'); ?>
                            </span>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 2rem; font-weight: 700; color: #3b82f6;">NOW</div>
                        <div style="font-size: 0.85rem; color: #6b7280;">in progress</div>
                    </div>
                </div>
                
                <div style="background: #f9fafb; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                        <div>
                            <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Treatment</div>
                            <div style="font-weight: 600; color: #111827;"><?php echo htmlspecialchars($inProcedurePatient['treatment_type'] ?? 'Consultation'); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Teeth</div>
                            <div style="font-weight: 600; color: #111827;" id="activePatientTeethDisplay">
                                <?php 
                                    $teethStr = $inProcedurePatient['teeth_numbers'] ?? '';
                                    echo !empty($teethStr) ? htmlspecialchars($teethStr) : 'Not specified';
                                ?>
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Started At</div>
                            <div style="font-weight: 600; color: #111827;"><?php echo date('g:i A', strtotime($inProcedurePatient['updated_at'] ?? $inProcedurePatient['queue_time'])); ?></div>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px;">
                    <button onclick="viewPatientDetails(<?php echo $inProcedurePatient['patient_id']; ?>)" class="action-btn" style="background: #6b7280; color: white; flex: 1; padding: 12px; font-size: 1rem; border: none; border-radius: 8px; cursor: pointer;">
                        <span style="margin-right: 6px;">👁</span> View Details
                    </button>
                    <button onclick="completeProcedure(<?php echo $inProcedurePatient['id']; ?>)" class="action-btn" style="background: #2563eb; color: white; flex: 2; padding: 12px; font-size: 1.1rem; font-weight: 600; border: none; border-radius: 8px; cursor: pointer;">
                        <span style="margin-right: 6px;">✓</span> COMPLETE PROCEDURE
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($nextPatient): ?>
        <!-- NEXT PATIENT CARD -->
        <div class="section-card" style="border: 2px solid #22c55e; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 class="section-title" style="margin: 0; color: #15803d;">
                    <span style="font-size: 1.5rem;">🎯</span> NEXT PATIENT
                </h2>
                <span style="background: #22c55e; color: white; padding: 6px 16px; border-radius: 20px; font-weight: 700; font-size: 0.9rem;">
                    UP NOW
                </span>
            </div>
            
            <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 700; color: #111827; margin-bottom: 8px;">
                            <?php 
                            echo htmlspecialchars($nextPatient['first_name'] ?? ''); 
                            if (!empty($nextPatient['middle_name'])) {
                                echo '<span style="font-weight: 400; color: #6b7280;"> ' . htmlspecialchars($nextPatient['middle_name']) . '</span>';
                            }
                            echo ' ' . htmlspecialchars($nextPatient['last_name'] ?? '');
                            ?>
                        </div>
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <span style="background: #dbeafe; color: #1e40af; padding: 4px 12px; border-radius: 16px; font-size: 0.85rem; font-weight: 600;">
                                <?php echo htmlspecialchars($nextPatient['gender'] ?? 'Unknown'); ?>
                            </span>
                            <span style="color: #6b7280; font-size: 0.9rem;">
                                <?php echo htmlspecialchars($nextPatient['age'] ?? '--'); ?> years old
                            </span>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 2rem; font-weight: 700; color: #22c55e;">#1</div>
                        <div style="font-size: 0.85rem; color: #6b7280;">in queue</div>
                    </div>
                </div>
                
                <div style="background: #f9fafb; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                        <div>
                            <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Treatment</div>
                            <div style="font-weight: 600; color: #111827;"><?php echo htmlspecialchars($nextPatient['treatment_type'] ?? 'Consultation'); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Queued At</div>
                            <div style="font-weight: 600; color: #111827;"><?php echo date('g:i A', strtotime($nextPatient['queue_time'])); ?></div>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px;">
                    <button onclick="viewPatientDetails(<?php echo $nextPatient['patient_id']; ?>)" class="action-btn" style="background: #6b7280; color: white; flex: 1; padding: 12px; font-size: 1rem; border: none; border-radius: 8px; cursor: pointer;">
                        <span style="margin-right: 6px;">👁</span> View Details
                    </button>
                    <?php if ($inProcedurePatient): ?>
                        <button disabled class="action-btn" style="background: #9ca3af; color: white; flex: 2; padding: 12px; font-size: 1.1rem; font-weight: 600; cursor: not-allowed; opacity: 0.6;" title="Complete current procedure first">
                            <span style="margin-right: 6px;">🔒</span> WAITING - COMPLETE CURRENT FIRST
                        </button>
                    <?php else: ?>
                        <button onclick="startProcedure(<?php echo $nextPatient['id']; ?>)" class="action-btn" style="background: #22c55e; color: white; flex: 2; padding: 12px; font-size: 1.1rem; font-weight: 600; border: none; border-radius: 8px; cursor: pointer;">
                            <span style="margin-right: 6px;">▶</span> START PROCEDURE
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- UPCOMING PATIENTS -->
        <?php if (!empty($upcomingPatients) || (!empty($queuePatients) && !$nextPatient)): ?>
        <div class="section-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 class="section-title" style="margin: 0;">
                    ⏰ Upcoming Patients
                </h2>
                <a href="dentist_queue.php" style="color: #2563eb; font-size: 0.9rem; text-decoration: none; font-weight: 500;">
                    View Full Queue →
                </a>
            </div>
            
            <div class="patient-list">
                <?php foreach (array_slice($upcomingPatients, 0, 5) as $index => $patient): ?>
                    <div class="patient-item" style="display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid #f3f4f6;">
                        <div style="display: flex; align-items: center; gap: 16px; flex: 1;">
                            <div style="width: 36px; height: 36px; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #6b7280;">
                                <?php echo $index + 2; ?>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: #111827;"><?php echo htmlspecialchars($patient['full_name']); ?></div>
                                <div style="font-size: 0.85rem; color: #6b7280;"><?php echo htmlspecialchars($patient['treatment_type']); ?></div>
                            </div>
                        </div>
                        <button onclick="viewPatientDetails(<?php echo $patient['patient_id']; ?>)" style="background: #f3f4f6; color: #4b5563; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.85rem;">View</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Notifications & Quick Actions -->
    <div class="right-column">
        <div class="notification-box" style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
            <h3 style="font-size: 1rem; font-weight: 600; color: #111827; margin-bottom: 16px;">📊 Quick Stats</h3>
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
        
        <div class="notification-box" style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="font-size: 1rem; font-weight: 600; color: #111827; margin-bottom: 16px;">🚀 Quick Actions</h3>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <a href="dentist_patients.php" class="btn-primary" style="width: 100%; text-align: center; text-decoration: none; padding: 10px; display: block;">View All Patients</a>
                <a href="dentist_appointments.php" class="btn-primary" style="width: 100%; text-align: center; text-decoration: none; padding: 10px; display: block;">View Appointments</a>
            </div>
        </div>
    </div>
</div>

<script>
// Global variables
let currentTeethSelection = [];
let isEditingTeeth = false;
let currentQueueId = null;
let billingPatientId = null;
let billingQueueId = null;
let notesSaveTimeout;

// Queue actions
function startProcedure(queueId) {
    if (!confirm('Start procedure for this patient?')) return;
    
    fetch('../shared/queue_actions.php', {
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
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to start procedure');
    });
}

function completeProcedure(queueId) {
    if (!confirm('Mark procedure as complete?')) return;
    
    fetch('../shared/queue_actions.php', {
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

// View patient details
function viewPatientDetails(patientId) {
    console.log('Loading patient details for ID:', patientId);
    
    const modalContent = document.getElementById('patientModalContent');
    modalContent.innerHTML = '<div style="text-align:center;padding:40px;color:#6b7280;">Loading patient records...</div>';
    document.getElementById('patientModal').classList.add('active');
    document.body.style.overflow = 'hidden'; // Lock scroll
    
    fetch('../user-patient/patient_record_details.php?id=' + patientId)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const p = data.patient;
            const m = data.medical_history || {};
            const q = data.queue_item || {};
            
            billingPatientId = p.id;
            billingQueueId = q.id;
            
            document.getElementById('dentistModalPatientName').innerText = (p.first_name || '') + ' ' + (p.last_name || '');
            
            const allergies = m.allergies || 'None';
            const medications = m.current_medications || 'None';
            const medicalConditions = m.medical_conditions || 'None';
            
            const hasMedicalAlert = allergies === 'Yes' || 
                                   medicalConditions.toLowerCase().includes('diabetes') ||
                                   medicalConditions.toLowerCase().includes('heart') ||
                                   medicalConditions.toLowerCase().includes('blood pressure') ||
                                   medicalConditions.toLowerCase().includes('asthma');
            
            let medicalAlertsHtml = '';
            if (hasMedicalAlert) {
                medicalAlertsHtml = `
                    <div class="medical-alert">
                        <div class="medical-alert-title">⚠️ Medical Alert</div>
                        <div class="medical-alert-grid">
                            <div class="medical-alert-item"><div class="patient-info-label">Allergies</div><div class="patient-info-value ${allergies === 'Yes' ? 'danger' : ''}">${allergies}</div></div>
                            <div class="medical-alert-item"><div class="patient-info-label">Medications</div><div class="patient-info-value">${medications}</div></div>
                            <div class="medical-alert-item"><div class="patient-info-label">Conditions</div><div class="patient-info-value danger">${medicalConditions}</div></div>
                        </div>
                    </div>`;
            }

            modalContent.innerHTML = `
                <div class="patient-info-grid">
                    <div class="patient-info-item"><div class="patient-info-label">Full Name</div><div class="patient-info-value">${p.first_name} ${p.middle_name || ''} ${p.last_name}</div></div>
                    <div class="patient-info-item"><div class="patient-info-label">Age / Gender</div><div class="patient-info-value">${p.age || 'N/A'} / ${p.gender || 'N/A'}</div></div>
                    <div class="patient-info-item"><div class="patient-info-label">Phone</div><div class="patient-info-value">${p.phone || 'N/A'}</div></div>
                    <div class="patient-info-item"><div class="patient-info-label">Email</div><div class="patient-info-value">${p.email || 'N/A'}</div></div>
                </div>

                ${medicalAlertsHtml}

                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:20px;margin-bottom:20px;">
                    <h3 style="font-size:1rem;font-weight:600;color:#1e40af;margin-bottom:12px;">📋 Active Treatment</h3>
                    <div class="patient-info-label">Procedure</div>
                    <div class="patient-info-value" id="modalProcedureName" style="font-size:1.1rem;font-weight:600;">Loading...</div>
                    <div style="margin-top:8px;">
                        <span class="patient-info-label">Price:</span>
                        <span id="modalProcedurePrice" style="font-size:1rem;font-weight:600;color:#059669;margin-left:8px;">₱0.00</span>
                    </div>
                </div>

                <!-- Billing Section -->
                <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:12px;padding:20px;margin-bottom:20px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                        <h3 style="font-size:1rem;font-weight:600;color:#92400e;margin:0;">💰 Billing Management</h3>
                        <button onclick="openBillingEdit(${p.id}, ${q.id})" class="btn-primary" style="padding:6px 12px;font-size:0.8rem;background:#f59e0b;">Adjust Price</button>
                    </div>
                    <div class="patient-info-grid">
                        <div class="patient-info-item"><div class="patient-info-label">Standard Price (Services)</div><div id="detailsEstimatedAmount" class="patient-info-value" style="color:#92400e;">₱0.00</div></div>
                        <div class="patient-info-item"><div class="patient-info-label">Additional Adjustment</div><div id="detailsAdjustmentAmount" class="patient-info-value" style="color:#0ea5e9;font-weight:700;">+₱0.00</div></div>
                        <div class="patient-info-item"><div class="patient-info-label">Final Total Amount</div><div id="detailsTotalAmount" class="patient-info-value" style="font-weight:800;color:#111827;font-size:1.1rem;">₱0.00</div></div>
                        <div class="patient-info-item"><div class="patient-info-label">Payment Status</div><div id="detailsStatus" class="patient-info-value">Loading...</div></div>
                    </div>
                </div>

                <!-- Teeth Section -->
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:20px;margin-bottom:20px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                        <h3 style="font-size:1rem;font-weight:600;color:#15803d;margin:0;">🦷 Teeth Selection</h3>
                        <button id="editTeethBtn" onclick="toggleTeethEditMode(${q.id})" class="btn-primary" style="padding:6px 12px;font-size:0.8rem;">Edit Teeth</button>
                    </div>
                    <div id="toothChartContainer">${generateToothChartHTML(q.teeth_numbers || '')}</div>
                    <div id="selectedTeethDisplay" style="font-size:0.85rem;color:#374151;margin-top:10px;font-weight:500;">Selected: ${q.teeth_numbers || 'None'}</div>
                    <div id="teethEditActions" style="display:none;margin-top:12px;gap:8px;">
                         <button onclick="saveTeethChanges(${q.id})" class="btn-primary" style="padding:8px 16px;">Save Changes</button>
                         <button onclick="cancelTeethEdit()" class="btn-cancel" style="padding:8px 16px;">Cancel</button>
                    </div>
                </div>

                <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:12px;padding:20px;margin-bottom:20px;">
                    <h3 style="font-size:1rem;font-weight:600;color:#d97706;margin-bottom:12px;">📝 Procedure Notes</h3>
                    <textarea id="procedureNotes" rows="4" style="width:100%;padding:12px;border:1px solid #e5e7eb;border-radius:8px;font-size:0.9rem;" placeholder="Start typing notes...">${q.procedure_notes || ''}</textarea>
                    <div style="margin-top:8px;display:flex;justify-content:space-between;align-items:center;">
                        <span id="notesSaveStatus" style="font-size:0.8rem;color:#6b7280;"></span>
                        <button onclick="saveProcedureNotes(${q.id})" class="btn-primary" style="padding:6px 12px;font-size:0.85rem;">Save Notes</button>
                    </div>
                </div>

                <div style="margin-top:24px;display:flex;justify-content:flex-end;gap:12px;">
                    <button onclick="closePatientModal()" class="btn-cancel">Close</button>
                </div>
            `;
            
            if (q.id) {
                setupNotesAutoSave(q.id);
                loadBillingInfo(p.id);
            }
        }
    });
}

// Tooth Chart Helpers
function generateToothChartHTML(selectedTeeth) {
    currentTeethSelection = selectedTeeth ? selectedTeeth.split(',').map(t => t.trim()).filter(t => t) : [];
    
    const upperRight = [18, 17, 16, 15, 14, 13, 12, 11];
    const upperLeft = [21, 22, 23, 24, 25, 26, 27, 28];
    const lowerLeft = [31, 32, 33, 34, 35, 36, 37, 38];
    const lowerRight = [48, 47, 46, 45, 44, 43, 42, 41];
    
    function generateTeethHTML(teethArray) {
        return teethArray.map(num => {
            const isSelected = currentTeethSelection.includes(num.toString());
            return `
                <div class="tooth-wrapper ${isSelected ? 'selected' : ''}" data-tooth="${num}" onclick="toggleToothSelection(this)">
                    <div class="tooth-face">${num}</div>
                </div>
            `;
        }).join('');
    }
    
    return `
        <div class="tooth-chart-container">
            <div class="tooth-arch-row">
                <div style="width: 50%;">
                    <div class="tooth-arch-label">Upper Right</div>
                    <div class="tooth-arch">${generateTeethHTML(upperRight)}</div>
                </div>
                <div style="width: 50%;">
                    <div class="tooth-arch-label">Upper Left</div>
                    <div class="tooth-arch">${generateTeethHTML(upperLeft)}</div>
                </div>
            </div>
            <div class="tooth-arch-row">
                <div style="width: 50%;">
                    <div class="tooth-arch-label">Lower Right</div>
                    <div class="tooth-arch">${generateTeethHTML(lowerRight)}</div>
                </div>
                <div style="width: 50%;">
                    <div class="tooth-arch-label">Lower Left</div>
                    <div class="tooth-arch">${generateTeethHTML(lowerLeft)}</div>
                </div>
            </div>
        </div>
    `;
}

function toggleToothSelection(element) {
    if (!isEditingTeeth) return;
    const toothNum = element.getAttribute('data-tooth');
    const index = currentTeethSelection.indexOf(toothNum);
    if (index > -1) {
        currentTeethSelection.splice(index, 1);
        element.classList.remove('selected');
    } else {
        currentTeethSelection.push(toothNum);
        element.classList.add('selected');
    }
    document.getElementById('selectedTeethDisplay').textContent = 'Selected: ' + currentTeethSelection.join(', ');
}

function toggleTeethEditMode(queueId) {
    isEditingTeeth = !isEditingTeeth;
    const btn = document.getElementById('editTeethBtn');
    const actions = document.getElementById('teethEditActions');
    if (isEditingTeeth) {
        btn.textContent = 'Cancel Edit';
        actions.style.display = 'flex';
        document.querySelectorAll('.tooth-wrapper').forEach(w => w.classList.add('editing'));
    } else {
        cancelTeethEdit();
    }
}

function saveTeethChanges(queueId) {
    const teethString = currentTeethSelection.join(', ');
    fetch('../shared/update_queue_teeth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ queue_id: queueId, teeth_numbers: teethString })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            isEditingTeeth = false;
            document.getElementById('editTeethBtn').textContent = 'Edit Teeth';
            document.getElementById('teethEditActions').style.display = 'none';
            alert('Teeth updated!');
            location.reload();
        }
    });
}

function cancelTeethEdit() {
    isEditingTeeth = false;
    document.getElementById('editTeethBtn').textContent = 'Edit Teeth';
    document.getElementById('teethEditActions').style.display = 'none';
}

// Billing Helpers
function loadBillingInfo(patientId) {
    fetch('dentist_billing_actions.php?action=get_billing&patient_id=' + patientId)
    .then(r => r.json())
    .then(data => {
        console.log('Billing data:', JSON.stringify(data, null, 2));
        if (data.success && data.billing) {
            const b = data.billing;
            const standardPrice = parseFloat(b.estimated_amount || 0);
            const finalTotal = parseFloat(b.total_amount || standardPrice);
            const adjustment = finalTotal - standardPrice;
            
            // Display procedure name from database
            const procNameEl = document.getElementById('modalProcedureName');
            if (procNameEl) {
                procNameEl.innerText = b.service_name || b.treatment_type || 'Consultation';
            }
            
            // Display procedure price
            const procPriceEl = document.getElementById('modalProcedurePrice');
            if (procPriceEl) {
                procPriceEl.innerText = '₱' + standardPrice.toLocaleString('en-PH', {minimumFractionDigits: 2});
            }
            
            document.getElementById('detailsEstimatedAmount').innerText = '₱' + standardPrice.toLocaleString('en-PH', {minimumFractionDigits: 2});
            document.getElementById('detailsTotalAmount').innerText = '₱' + finalTotal.toLocaleString('en-PH', {minimumFractionDigits: 2});
            
            const adjustmentEl = document.getElementById('detailsAdjustmentAmount');
            const sign = adjustment >= 0 ? '+' : '-';
            adjustmentEl.innerText = sign + '₱' + Math.abs(adjustment).toLocaleString('en-PH', {minimumFractionDigits: 2});
            adjustmentEl.style.color = adjustment >= 0 ? '#0ea5e9' : '#dc2626';

            document.getElementById('detailsStatus').innerHTML = `<span style="background:${b.payment_status === 'paid' ? '#dcfce7' : '#fee2e2'};color:${b.payment_status === 'paid' ? '#15803d' : '#991b1b'};padding:4px 12px;border-radius:9999px;font-size:0.85rem;">${b.payment_status.toUpperCase()}</span>`;
        } else if (data.message) {
            console.error('Billing error:', data.message);
        }
    })
    .catch(err => console.error('Fetch error:', err));
}

function openBillingEdit(patientId, queueId) {
    const standardStr = document.getElementById('detailsEstimatedAmount').innerText.replace('₱', '').replace(/,/g, '');
    const currentTotalStr = document.getElementById('detailsTotalAmount').innerText.replace('₱', '').replace(/,/g, '');
    
    const standardPrice = parseFloat(standardStr) || 0;
    const currentTotal = parseFloat(currentTotalStr) || standardPrice;
    const currentAdjustment = currentTotal - standardPrice;

    const adjustmentPrompt = prompt('Add extra cost (pesos):\n\nStandard Price: ₱' + standardPrice.toLocaleString() + '\nCurrent Total: ₱' + currentTotal.toLocaleString() + '\n\nEnter adjustment (e.g., 50 for +₱50, -50 for discount):', currentAdjustment);
    
    if (adjustmentPrompt !== null) {
        const adjustment = parseFloat(adjustmentPrompt) || 0;
        const newTotal = standardPrice + adjustment;
        
        fetch('dentist_billing_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'update_amount', 
                patient_id: patientId, 
                queue_id: queueId, 
                total_amount: newTotal,
                notes: 'Manual adjustment: ' + (adjustment >= 0 ? '+' : '-') + '₱' + Math.abs(adjustment)
            })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                alert('Billing updated!\nStandard: ₱' + standardPrice + '\nAdjustment: ' + (adjustment >= 0 ? '+' : '-') + '₱' + Math.abs(adjustment) + '\nNew Total: ₱' + newTotal);
                loadBillingInfo(patientId);
            } else {
                alert('Error updating price: ' + res.message);
            }
        });
    }
}

// Notes Helpers
function saveProcedureNotes(queueId) {
    const notes = document.getElementById('procedureNotes').value;
    const statusEl = document.getElementById('notesSaveStatus');
    statusEl.textContent = 'Saving...';
    fetch('../shared/save_procedure_notes.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ queue_id: queueId, procedure_notes: notes })
    })
    .then(r => r.json())
    .then(data => { if (data.success) { statusEl.textContent = 'Saved!'; setTimeout(() => statusEl.textContent = '', 2000); } });
}

function setupNotesAutoSave(queueId) {
    const textarea = document.getElementById('procedureNotes');
    textarea.addEventListener('input', () => {
        clearTimeout(notesSaveTimeout);
        notesSaveTimeout = setTimeout(() => saveProcedureNotes(queueId), 2000);
    });
}

function closePatientModal() {
    document.getElementById('patientModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Move modal to body for total screen coverage
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('patientModal');
    if (modal) {
        document.body.appendChild(modal);
    }
});
</script>

<!-- Modal Structure (Moved to root level via JS) -->
<div id="patientModal" class="fullscreen-modal-overlay">
    <div class="fullscreen-modal">
        <div class="fullscreen-modal-header">
            <h3 id="dentistModalPatientName" style="margin:0;font-size:1.25rem;font-weight:700;">Patient Details</h3>
            <button onclick="closePatientModal()" style="background:none;border:none;font-size:1.8rem;cursor:pointer;color:#64748b;line-height:1;">&times;</button>
        </div>
        <div class="fullscreen-modal-body" id="patientModalContent"></div>
    </div>
</div>

<style>
.fullscreen-modal-overlay {
    display: none; 
    position: fixed; 
    top: 0; 
    left: 0; 
    width: 100vw; 
    height: 100vh;
    background: rgba(0,0,0,0.75); 
    z-index: 9999999; /* Exceptionally high z-index to cover sidebar */
    align-items: center; 
    justify-content: center;
    backdrop-filter: blur(4px); /* Modern blur effect for background */
}
.fullscreen-modal-overlay.active { display: flex !important; }
.fullscreen-modal {
    background: white; 
    border-radius: 20px; 
    max-width: 900px; 
    max-height: 85vh; 
    width: 95%;
    overflow-y: auto; 
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
    position: relative;
    animation: modalSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes modalSlideUp {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.fullscreen-modal-header { 
    padding: 20px 28px; 
    border-bottom: 1px solid #f1f5f9; 
    display: flex; 
    justify-content: space-between; 
    align-items: center;
    position: sticky;
    top: 0;
    background: white;
    z-index: 100;
}
.fullscreen-modal-body { padding: 28px; }
.patient-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 28px; }
.patient-info-label { font-size: 0.7rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
.patient-info-value { font-size: 1rem; color: #1e293b; font-weight: 600; }
.medical-alert { background: #fff1f2; border: 1px solid #fecdd3; border-radius: 14px; padding: 20px; margin-bottom: 28px; }
.medical-alert-title { color: #e11d48; font-weight: 800; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
.medical-alert-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; }
.danger { color: #e11d48 !important; }
.btn-cancel { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; padding: 10px 20px; border-radius: 10px; cursor: pointer; font-weight: 600; transition: all 0.2s; }
.btn-cancel:hover { background: #e2e8f0; }

/* 3D Tooth Chart Styles */
.tooth-chart-container { display: flex; flex-direction: column; align-items: center; gap: 20px; padding: 24px; background: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0; }
.tooth-arch-row { display: flex; justify-content: center; gap: 12px; width: 100%; }
.tooth-arch { display: flex; gap: 6px; justify-content: center; }
.tooth-arch-label { text-align: center; font-size: 0.75rem; color: #64748b; font-weight: 800; text-transform: uppercase; margin-bottom: 10px; }
.tooth-wrapper { width: 34px; height: 44px; cursor: pointer; transition: all 0.2s ease-out; }
.tooth-face {
    width: 100%; height: 100%; background: white; border: 1px solid #cbd5e1; border-radius: 8px;
    display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; color: #64748b;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), inset 0 -2px 0 rgba(0,0,0,0.05);
    position: relative;
}
.tooth-wrapper:hover .tooth-face { transform: translateY(-2px); border-color: #94a3b8; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
.tooth-wrapper.selected .tooth-face { 
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); 
    color: white; 
    border-color: #1d4ed8; 
    transform: translateY(-3px); 
    box-shadow: 0 12px 20px -5px rgba(37,99,235,0.4); 
}
.tooth-wrapper.editing .tooth-face { border-color: #3b82f6; border-width: 2px; animation: toothPulse 1.5s infinite; }
@keyframes toothPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(59,130,246,0.5); } 50% { box-shadow: 0 0 0 8px rgba(59,130,246,0); } }
</style>

<?php require_once '../../includes/dentist_layout_end.php'; ?>