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
        SELECT q.*, p.first_name, p.middle_name, p.last_name, p.suffix, p.phone, p.gender, p.age
        FROM queue q
        JOIN patients p ON q.patient_id = p.id
        WHERE q.status IN ('waiting', 'in_procedure')
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
            if ($patient['status'] === 'in_procedure') {
                $inProcedurePatient = $patient;
            } elseif ($patient['status'] === 'waiting' && !$nextPatient) {
                $nextPatient = $patient;
            } elseif ($patient['status'] === 'waiting') {
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
                            <?php echo htmlspecialchars($inProcedurePatient['full_name']); ?>
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
                            <div style="font-weight: 600; color: #111827;"><?php echo htmlspecialchars($inProcedurePatient['treatment_type'] ?? 'General Checkup'); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Teeth</div>
                            <div style="font-weight: 600; color: #111827;"><?php 
                                $teethDisplay = '';
                                $teethStr = $inProcedurePatient['teeth_numbers'] ?? '';
                                if (!empty($teethStr)) {
                                    $teeth = array_map('trim', explode(',', $teethStr));
                                    $teeth = array_filter($teeth, function($t) { return !empty($t); });
                                    $teeth = array_map('intval', $teeth);
                                    
                                    // Staff's arch definitions match
                                    $upperArch = [11, 12, 13, 14, 15, 16, 17, 18, 21, 22, 23, 24, 25, 26, 27, 28]; // 16 teeth
                                    $lowerArch = [31, 32, 33, 34, 35, 36, 37, 38, 41, 42, 43, 44, 45, 46, 47, 48]; // 16 teeth
                                    
                                    // Check for exact arch matches
                                    $hasUpperArch = count($teeth) === 16 && empty(array_diff($upperArch, $teeth)) && empty(array_diff($teeth, $upperArch));
                                    $hasLowerArch = count($teeth) === 16 && empty(array_diff($lowerArch, $teeth)) && empty(array_diff($teeth, $lowerArch));
                                    
                                    $parts = [];
                                    
                                    if ($hasUpperArch) {
                                        $parts[] = 'Upper Arch';
                                    }
                                    
                                    if ($hasLowerArch) {
                                        $parts[] = 'Lower Arch';
                                    }
                                    
                                    // If no full arch matches, show individual teeth
                                    if (empty($parts)) {
                                        sort($teeth);
                                        $teethDisplay = implode(', ', $teeth);
                                    } else {
                                        $teethDisplay = implode(' + ', $parts);
                                    }
                                } else {
                                    $teethDisplay = 'Not specified';
                                }
                                echo htmlspecialchars($teethDisplay);
                            ?></div>
                        </div>
                        <div>
                            <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Started At</div>
                            <div style="font-weight: 600; color: #111827;"><?php echo date('g:i A', strtotime($inProcedurePatient['updated_at'] ?? $inProcedurePatient['queue_time'])); ?></div>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px;">
                    <button onclick="viewPatientDetails(<?php echo $inProcedurePatient['patient_id']; ?>)" class="action-btn" style="background: #6b7280; color: white; flex: 1; padding: 12px; font-size: 1rem;">
                        <span style="margin-right: 6px;">👁</span> View Details
                    </button>
                    <button onclick="completeProcedure(<?php echo $inProcedurePatient['id']; ?>)" class="action-btn" style="background: #2563eb; color: white; flex: 2; padding: 12px; font-size: 1.1rem; font-weight: 600;">
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
                            <?php echo htmlspecialchars($nextPatient['full_name']); ?>
                        </div>
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <span style="background: #dbeafe; color: #1e40af; padding: 4px 12px; border-radius: 16px; font-size: 0.85rem; font-weight: 600;">
                                <?php echo htmlspecialchars($nextPatient['gender'] ?? 'Unknown'); ?>
                            </span>
                            <span style="color: #6b7280; font-size: 0.9rem;">
                                <?php echo htmlspecialchars($nextPatient['age'] ?? '--'); ?> years old
                            </span>
                            <span style="color: #6b7280; font-size: 0.9rem;">
                                📞 <?php echo htmlspecialchars($nextPatient['phone'] ?? 'No phone'); ?>
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
                            <div style="font-weight: 600; color: #111827;"><?php echo htmlspecialchars($nextPatient['treatment_type'] ?? 'General Checkup'); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Teeth</div>
                            <div style="font-weight: 600; color: #111827;"><?php 
                                $teethDisplay = '';
                                $teethStr = $nextPatient['teeth_numbers'] ?? '';
                                if (!empty($teethStr)) {
                                    $teeth = array_map('trim', explode(',', $teethStr));
                                    $teeth = array_filter($teeth, function($t) { return !empty($t); });
                                    $teeth = array_map('intval', $teeth);
                                    
                                    // Staff's arch definitions match
                                    $upperArch = [11, 12, 13, 14, 15, 16, 17, 18, 21, 22, 23, 24, 25, 26, 27, 28]; // 16 teeth
                                    $lowerArch = [31, 32, 33, 34, 35, 36, 37, 38, 41, 42, 43, 44, 45, 46, 47, 48]; // 16 teeth
                                    
                                    // Check for exact arch matches
                                    $hasUpperArch = count($teeth) === 16 && empty(array_diff($upperArch, $teeth)) && empty(array_diff($teeth, $upperArch));
                                    $hasLowerArch = count($teeth) === 16 && empty(array_diff($lowerArch, $teeth)) && empty(array_diff($teeth, $lowerArch));
                                    
                                    $parts = [];
                                    
                                    if ($hasUpperArch) {
                                        $parts[] = 'Upper Arch';
                                    }
                                    
                                    if ($hasLowerArch) {
                                        $parts[] = 'Lower Arch';
                                    }
                                    
                                    // If no full arch matches, show individual teeth
                                    if (empty($parts)) {
                                        sort($teeth);
                                        $teethDisplay = implode(', ', $teeth);
                                    } else {
                                        $teethDisplay = implode(' + ', $parts);
                                    }
                                } else {
                                    $teethDisplay = 'Not specified';
                                }
                                echo htmlspecialchars($teethDisplay);
                            ?></div>
                        </div>
                        <div>
                            <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Queued At</div>
                            <div style="font-weight: 600; color: #111827;"><?php echo date('g:i A', strtotime($nextPatient['queue_time'])); ?></div>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px;">
                    <button onclick="viewPatientDetails(<?php echo $nextPatient['patient_id']; ?>)" class="action-btn" style="background: #6b7280; color: white; flex: 1; padding: 12px; font-size: 1rem;">
                        <span style="margin-right: 6px;">👁</span> View Details
                    </button>
                    <?php if ($inProcedurePatient): ?>
                        <button disabled class="action-btn" style="background: #9ca3af; color: white; flex: 2; padding: 12px; font-size: 1.1rem; font-weight: 600; cursor: not-allowed; opacity: 0.6;" title="Complete current procedure first">
                            <span style="margin-right: 6px;">🔒</span> WAITING - COMPLETE CURRENT FIRST
                        </button>
                    <?php else: ?>
                        <button onclick="startProcedure(<?php echo $nextPatient['id']; ?>)" class="action-btn" style="background: #22c55e; color: white; flex: 2; padding: 12px; font-size: 1.1rem; font-weight: 600;">
                            <span style="margin-right: 6px;">▶</span> START PROCEDURE
                        </button>
                    <?php endif; ?>
                </div>
                <?php if ($inProcedurePatient): ?>
                <div style="background: #fef3c7; border: 1px solid #fbbf24; border-radius: 8px; padding: 12px; margin-top: 12px; text-align: center;">
                    <span style="color: #92400e; font-size: 0.9rem; font-weight: 500;">
                        ⚠️ You must complete <?php echo htmlspecialchars($inProcedurePatient['full_name']); ?>'s procedure before starting the next patient.
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- UPCOMING PATIENTS -->
        <?php if (!empty($upcomingPatients) || (!empty($queuePatients) && !$nextPatient)): ?>
        <div class="section-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 class="section-title" style="margin: 0;">
                    ⏰ Upcoming Patients
                    <?php if (count($upcomingPatients) > 0): ?>
                        <span style="background: #f3f4f6; color: #6b7280; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; margin-left: 10px;">
                            <?php echo count($upcomingPatients); ?> waiting
                        </span>
                    <?php endif; ?>
                </h2>
                <a href="dentist_queue.php" style="color: #2563eb; font-size: 0.9rem; text-decoration: none; font-weight: 500;">
                    View Full Queue →
                </a>
            </div>
            
            <?php if (empty($upcomingPatients) && !empty($queuePatients)): ?>
                <!-- Show in-procedure patient if no waiting patients -->
                <?php foreach ($queuePatients as $patient): ?>
                    <?php if ($patient['status'] === 'in_procedure'): ?>
                    <div class="patient-item" style="border: 2px solid #3b82f6; background: #eff6ff;">
                        <div class="patient-info" style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                <span style="background: #3b82f6; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: 600;">IN PROGRESS</span>
                                <div class="patient-name"><?php echo htmlspecialchars($patient['full_name']); ?></div>
                            </div>
                            <div class="patient-details">
                                <span class="patient-time"><?php echo htmlspecialchars($patient['treatment_type'] ?? 'General'); ?></span>
                                <span style="color: #6b7280;">• Teeth: <?php echo htmlspecialchars($patient['teeth_numbers'] ?? 'All'); ?></span>
                            </div>
                        </div>
                        <div class="patient-actions">
                            <button onclick="completeProcedure(<?php echo $patient['id']; ?>)" class="action-btn" style="background: #2563eb; color: white;">Complete</button>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Show upcoming waiting patients -->
                <div class="patient-list">
                    <?php foreach (array_slice($upcomingPatients, 0, 5) as $index => $patient): ?>
                        <div class="patient-item">
                            <div style="display: flex; align-items: center; gap: 16px; flex: 1;">
                                <div style="width: 36px; height: 36px; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #6b7280;">
                                    <?php echo $index + 2; ?>
                                </div>
                                <div class="patient-info" style="flex: 1;">
                                    <div class="patient-name"><?php echo htmlspecialchars($patient['full_name']); ?></div>
                                    <div class="patient-details">
                                        <span class="patient-time"><?php echo htmlspecialchars($patient['treatment_type'] ?? 'General'); ?></span>
                                        <span style="color: #6b7280;">• <?php 
                                            $teethStr = $patient['teeth_numbers'] ?? '';
                                            if (!empty($teethStr)) {
                                                $teeth = array_map('trim', explode(',', $teethStr));
                                                $teeth = array_filter($teeth, function($t) { return !empty($t); });
                                                $teeth = array_map('intval', $teeth);
                                                
                                                $upperArch = [11, 12, 13, 14, 15, 16, 17, 18, 21, 22, 23, 24, 25, 26, 27, 28];
                                                $lowerArch = [31, 32, 33, 34, 35, 36, 37, 38, 41, 42, 43, 44, 45, 46, 47, 48];
                                                
                                                $hasUpperArch = count($teeth) === 16 && empty(array_diff($upperArch, $teeth)) && empty(array_diff($teeth, $upperArch));
                                                $hasLowerArch = count($teeth) === 16 && empty(array_diff($lowerArch, $teeth)) && empty(array_diff($teeth, $lowerArch));
                                                
                                                $parts = [];
                                                if ($hasUpperArch) $parts[] = 'Upper';
                                                if ($hasLowerArch) $parts[] = 'Lower';
                                                
                                                if (!empty($parts)) {
                                                    echo 'Teeth: ' . implode(' + ', $parts) . ' Arch';
                                                } else {
                                                    sort($teeth);
                                                    echo 'Teeth: ' . implode(', ', $teeth);
                                                }
                                            } else {
                                                echo 'All teeth';
                                            }
                                        ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="patient-actions">
                                <button onclick="viewPatientDetails(<?php echo $patient['patient_id']; ?>)" class="action-btn" style="background: #6b7280; color: white;">View</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (count($upcomingPatients) > 5): ?>
                        <div style="text-align: center; padding: 16px; color: #6b7280; font-size: 0.9rem; border-top: 1px solid #e5e7eb;">
                            +<?php echo count($upcomingPatients) - 5; ?> more patients in queue
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
 <?php elseif (empty($queuePatients)): ?>
            <!-- Treatment Queue (Empty) -->
            <div class="section-card">
                <h2 class="section-title">⚕️ Treatment Queue</h2>
                <div style="text-align: center; padding: 60px 20px; color: #6b7280;">
                    <div style="font-size: 4rem; margin-bottom: 16px;">😊</div>
                    <h3 style="font-size: 1.25rem; font-weight: 600; color: #374151; margin-bottom: 8px;">No patients in queue</h3>
                    <p style="color: #9ca3af;">The queue is empty. Time for a break!</p>
                </div>
            </div>        <?php endif; ?>
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
            // Check if we should show prescription prompt
            if (data.show_prescription_prompt) {
                if (confirm('Treatment completed! Add prescription for ' + data.patient_name + '?')) {
                    // Open prescription modal
                    const queueItem = queueItems.find(item => item.id === queueId);
                    if (queueItem) {
                        openPatientPrescriptionModal(queueItem.patient_id);
                    }
                }
            }
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

// Open prescription modal for patient
function openPatientPrescriptionModal(patientId) {
    // Create prescription modal
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.style.display = 'flex';
    modal.innerHTML = `
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h2>💊 New Prescription</h2>
                <button class="modal-close" onclick="closePatientPrescriptionModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="dashPatientSafetyInfo" class="safety-info" style="display: none;">
                    <h3>👤 Patient Information</h3>
                    <div class="safety-grid">
                        <div class="safety-item">
                            <strong>Name:</strong>
                            <span id="dashPatientName"></span>
                        </div>
                        <div class="safety-item">
                            <strong>Age:</strong>
                            <span id="dashPatientAge"></span>
                        </div>
                        <div class="safety-item">
                            <strong>Allergies:</strong>
                            <span id="dashPatientAllergies" class="warning-text"></span>
                        </div>
                        <div class="safety-item">
                            <strong>Current Medications:</strong>
                            <span id="dashPatientMeds" class="info-text"></span>
                        </div>
                    </div>
                </div>
                
                <form id="dashPrescriptionForm">
                    <input type="hidden" id="dashPatientId" name="patient_id" value="${patientId}">
                    
                    <div class="form-group">
                        <label>Issue Date:</label>
                        <input type="date" name="issue_date" value="${new Date().toISOString().split('T')[0]}" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Diagnosis:</label>
                        <textarea name="diagnosis" rows="3" required placeholder="Reason for prescription..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Medications:</label>
                        <textarea name="medications" rows="4" required placeholder="e.g., Amoxicillin 500mg - 3 times daily for 7 days"></textarea>
                        <small>Enter medication details including dosage, frequency, and duration</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Instructions:</label>
                        <textarea name="instructions" rows="3" placeholder="Additional patient instructions..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closePatientPrescriptionModal()">Cancel</button>
                <button type="button" class="btn-primary" onclick="saveDashPrescription()">Save Prescription</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
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
            
            document.getElementById('dashPatientName').textContent = patient.patient_name || 'N/A';
            document.getElementById('dashPatientAge').textContent = patient.age || 'N/A';
            document.getElementById('dashPatientAllergies').textContent = patient.allergies || 'None recorded';
            document.getElementById('dashPatientMeds').textContent = patient.current_medications || 'None recorded';
            
            document.getElementById('dashPatientSafetyInfo').style.display = 'block';
        }
    });
}

function closePatientPrescriptionModal() {
    const modal = document.querySelector('.modal-overlay');
    if (modal) {
        modal.remove();
    }
}

function saveDashPrescription() {
    const formData = new FormData(document.getElementById('dashPrescriptionForm'));
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
            
            // Build medical alerts HTML
            let medicalAlertsHtml = '';
            if (hasMedicalAlert) {
                medicalAlertsHtml = '<div class="medical-alert">' +
                    '<div class="medical-alert-title">⚠️ Medical Alert - Important for Treatment</div>' +
                    '<div class="medical-alert-grid">' +
                    '<div class="medical-alert-item"><div class="patient-info-label">Allergies</div><div class="patient-info-value ' + (allergies === 'Yes' ? 'danger' : '') + '">' + allergies + '</div></div>' +
                    '<div class="medical-alert-item"><div class="patient-info-label">Diabetes</div><div class="patient-info-value ' + (medicalConditions.toLowerCase().includes('diabetes') ? 'danger' : '') + '">' + (medicalConditions.toLowerCase().includes('diabetes') ? 'Yes' : 'No') + '</div></div>' +
                    '<div class="medical-alert-item"><div class="patient-info-label">Heart Disease</div><div class="patient-info-value ' + (medicalConditions.toLowerCase().includes('heart') ? 'danger' : '') + '">' + (medicalConditions.toLowerCase().includes('heart') ? 'Yes' : 'No') + '</div></div>' +
                    '<div class="medical-alert-item"><div class="patient-info-label">High Blood Pressure</div><div class="patient-info-value ' + (medicalConditions.toLowerCase().includes('blood pressure') ? 'danger' : '') + '">' + (medicalConditions.toLowerCase().includes('blood pressure') ? 'Yes' : 'No') + '</div></div>' +
                    '<div class="medical-alert-item"><div class="patient-info-label">Asthma</div><div class="patient-info-value ' + (medicalConditions.toLowerCase().includes('asthma') ? 'danger' : '') + '">' + (medicalConditions.toLowerCase().includes('asthma') ? 'Yes' : 'No') + '</div></div>' +
                    '<div class="medical-alert-item" style="grid-column: 1 / -1;"><div class="patient-info-label">Current Medications</div><div class="patient-info-value">' + medications + '</div></div>' +
                    '</div></div>';
            }
            
            // Build status badge
            let statusBadge = '<span style="background: #f3f4f6; color: #6b7280; padding: 4px 12px; border-radius: 9999px; font-size: 0.85rem;">N/A</span>';
            if (q.status === 'in_procedure') {
                statusBadge = '<span style="background: #dcfce7; color: #15803d; padding: 4px 12px; border-radius: 9999px; font-size: 0.85rem;">IN PROCEDURE</span>';
            } else if (q.status === 'waiting') {
                statusBadge = '<span style="background: #fef3c7; color: #d97706; padding: 4px 12px; border-radius: 9999px; font-size: 0.85rem;">WAITING</span>';
            }
            
            // Generate teeth display
            let teethDisplay = 'None';
            if (q.teeth_numbers) {
                const teeth = q.teeth_numbers.split(',').map(t => parseInt(t.trim())).filter(t => !isNaN(t));
                if (teeth.length === 16) {
                    const upperArch = [11,12,13,14,15,16,17,18,21,22,23,24,25,26,27,28];
                    const lowerArch = [31,32,33,34,35,36,37,38,41,42,43,44,45,46,47,48];
                    const hasUpper = upperArch.every(t => teeth.includes(t));
                    const hasLower = lowerArch.every(t => teeth.includes(t));
                    const parts = [];
                    if (hasUpper) parts.push('Upper Arch');
                    if (hasLower) parts.push('Lower Arch');
                    teethDisplay = parts.length > 0 ? parts.join(' + ') : teeth.sort((a,b) => a-b).join(', ');
                } else {
                    teethDisplay = teeth.sort((a,b) => a-b).join(', ');
                }
            }
            
            // Build dental history
            const dentalHistoryHtml = '<div style="background: #f9fafb; border-radius: 12px; padding: 20px;">' +
                '<h3 style="font-size: 1rem; font-weight: 600; color: #374151; margin-bottom: 16px;">📜 Dental History</h3>' +
                '<div class="patient-info-grid">' +
                '<div class="patient-info-item"><div class="patient-info-label">Previous Dentist</div><div class="patient-info-value">' + (d.previous_dentist || 'N/A') + '</div></div>' +
                '<div class="patient-info-item"><div class="patient-info-label">Last Visit</div><div class="patient-info-value">' + (d.last_visit_date || 'N/A') + '</div></div>' +
                '<div class="patient-info-item" style="grid-column: 1 / -1;"><div class="patient-info-label">Current Complaints</div><div class="patient-info-value">' + (d.current_complaints || 'None') + '</div></div>' +
                '<div class="patient-info-item" style="grid-column: 1 / -1;"><div class="patient-info-label">Previous Treatments</div><div class="patient-info-value">' + (d.previous_treatments || 'None') + '</div></div>' +
                '</div></div>';
            
            // Build treatment history
            let treatmentHistoryHtml = '';
            if (data.treatment_history && data.treatment_history.length > 0) {
                treatmentHistoryHtml = '<div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; padding: 20px; margin-top: 20px;">' +
                    '<h3 style="font-size: 1rem; font-weight: 600; color: #059669; margin-bottom: 16px;">🏥 Treatment History</h3>';
                data.treatment_history.forEach(function(t) {
                    treatmentHistoryHtml += '<div style="background: white; border-radius: 8px; padding: 16px; border: 1px solid #e5e7eb; margin-bottom: 12px;">' +
                        '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">' +
                        '<div><div style="font-weight: 600; color: #111827; font-size: 0.95rem;">' + (t.procedure_name || 'Treatment') + '</div>' +
                        '<div style="font-size: 0.85rem; color: #6b7280;">' + new Date(t.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + '</div></div>' +
                        '<div style="background: #d1fae5; color: #059669; padding: 4px 12px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">' + (t.status || 'Completed') + '</div></div>' +
                        (t.tooth_number ? '<div style="font-size: 0.9rem; color: #374151; margin-bottom: 8px;">🦷 Teeth: ' + t.tooth_number + '</div>' : '') +
                        (t.description || t.notes ? '<div style="font-size: 0.85rem; color: #6b7280; font-style: italic;">' + (t.description || t.notes) + '</div>' : '') +
                        '</div>';
                });
                treatmentHistoryHtml += '</div>';
            } else {
                treatmentHistoryHtml = '<div style="background: #f9fafb; border-radius: 12px; padding: 20px; margin-top: 20px;">' +
                    '<div style="text-align: center; color: #9ca3af; padding: 20px;">' +
                    '<div style="font-size: 2rem; margin-bottom: 8px;">📋</div>' +
                    '<div style="font-weight: 500; color: #6b7280;">No treatment history yet</div>' +
                    '<div style="font-size: 0.85rem;">Completed treatments will appear here</div>' +
                    '</div></div>';
            }
            
            // Build modal content
            const modalContent = 
                '<div class="patient-info-grid">' +
                '<div class="patient-info-item"><div class="patient-info-label">Full Name</div><div class="patient-info-value">' + (p.full_name || 'N/A') + '</div></div>' +
                '<div class="patient-info-item"><div class="patient-info-label">Age</div><div class="patient-info-value">' + (p.age || 'N/A') + ' years</div></div>' +
                '<div class="patient-info-item"><div class="patient-info-label">Gender</div><div class="patient-info-value">' + (p.gender || 'N/A') + '</div></div>' +
                '<div class="patient-info-item"><div class="patient-info-label">Date of Birth</div><div class="patient-info-value">' + (p.date_of_birth || 'N/A') + '</div></div>' +
                '<div class="patient-info-item"><div class="patient-info-label">Phone</div><div class="patient-info-value">' + (p.phone || 'N/A') + '</div></div>' +
                '<div class="patient-info-item"><div class="patient-info-label">Email</div><div class="patient-info-value">' + (p.email || 'N/A') + '</div></div>' +
                '<div class="patient-info-item" style="grid-column: 1 / -1;"><div class="patient-info-label">Address</div><div class="patient-info-value">' + (p.address || 'N/A') + (p.city ? ', ' + p.city : '') + (p.province ? ', ' + p.province : '') + '</div></div>' +
                '</div>' +
                
                (hasMedicalAlert ? medicalAlertsHtml : '') +
                
                (q ? '<div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 20px; margin-bottom: 20px;">' +
                '<h3 style="font-size: 1rem; font-weight: 600; color: #1e40af; margin-bottom: 16px;">📋 Current Queue Status</h3>' +
                '<div class="patient-info-grid">' +
                '<div class="patient-info-item"><div class="patient-info-label">Treatment Type</div><div class="patient-info-value">' + (q.treatment_type || 'Consultation') + '</div></div>' +
                '<div class="patient-info-item"><div class="patient-info-label">Status</div><div class="patient-info-value">' + statusBadge + '</div></div>' +
                '</div></div>' : '') +
                
                '<div style="background: #fffbeb; border: 1px solid #fcd34d; border-radius: 12px; padding: 20px; margin-bottom: 20px;">' +
                '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">' +
                '<h3 style="font-size: 1rem; font-weight: 600; color: #92400e; margin: 0;">💰 Billing</h3>' +
                '<button onclick="openBillingFromDetails(' + p.id + ')" style="background: #f59e0b; color: white; border: none; border-radius: 6px; padding: 6px 12px; font-size: 0.8rem; cursor: pointer;">Edit Amount</button>' +
                '</div>' +
                '<div class="patient-info-grid">' +
                '<div class="patient-info-item"><div class="patient-info-label">Default Price (Services)</div><div class="patient-info-value" id="detailsEstimatedAmount" style="color: #6b7280;">Loading...</div></div>' +
                '<div class="patient-info-item"><div class="patient-info-label">Total Amount</div><div class="patient-info-value" id="detailsTotalAmount" style="font-weight: 700; font-size: 1.1rem;">Loading...</div></div>' +
                '<div class="patient-info-item"><div class="patient-info-label">Balance</div><div class="patient-info-value" id="detailsBalance" style="color: #dc2626; font-weight: 600;">Loading...</div></div>' +
                '<div class="patient-info-item"><div class="patient-info-label">Status</div><div class="patient-info-value" id="detailsStatus">Loading...</div></div>' +
                '</div></div>' +
                
                (q ? '<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 20px; margin-bottom: 20px;">' +
                '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">' +
                '<h3 style="font-size: 1rem; font-weight: 600; color: #15803d; margin: 0;">🦷 Selected Teeth</h3>' +
                '<button onclick="toggleTeethEditMode(' + (q.id || 0) + ')" id="editTeethBtn" class="btn-primary" style="padding: 6px 12px; font-size: 0.875rem;">Edit Teeth</button>' +
                '</div><div id="toothChartContainer" style="margin-bottom: 12px;">' + generateToothChartHTML(q.teeth_numbers || '') + '</div>' +
                '<div id="selectedTeethDisplay" style="font-size: 0.9rem; color: #374151; font-weight: 500;">Selected: ' + teethDisplay + '</div>' +
                '<div id="teethEditActions" style="display: none; margin-top: 12px; gap: 8px;">' +
                '<button onclick="saveTeethChanges(' + (q.id || 0) + ')" class="btn-primary" style="padding: 8px 16px;">Save Changes</button>' +
                '<button onclick="cancelTeethEdit()" class="btn-cancel" style="padding: 8px 16px;">Cancel</button>' +
                '</div></div>' : '') +
                
                (q ? '<div style="background: #fef3c7; border: 1px solid #fcd34d; border-radius: 12px; padding: 20px; margin-bottom: 20px;">' +
                '<h3 style="font-size: 1rem; font-weight: 600; color: #d97706; margin-bottom: 16px;">📝 Procedure Notes</h3>' +
                '<textarea id="procedureNotes" rows="4" style="width: 100%; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 0.9rem; resize: vertical;" placeholder="Add notes about the procedure...">' + (q.procedure_notes || '') + '</textarea>' +
                '<div style="margin-top: 8px; display: flex; justify-content: space-between; align-items: center;">' +
                '<span id="notesSaveStatus" style="font-size: 0.8rem; color: #6b7280;"></span>' +
                '<button onclick="saveProcedureNotes(' + (q.id || 0) + ')" class="btn-primary" style="padding: 8px 16px; font-size: 0.875rem;">Save Notes</button>' +
                '</div></div>' : '') +
                
                dentalHistoryHtml + treatmentHistoryHtml +
                
                '<div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px;">' +
                '<button onclick="closePatientModal()" class="btn-cancel">Close</button>' +
                '</div>';
            
            document.getElementById('patientModalContent').innerHTML = modalContent;
            document.getElementById('patientModal').classList.add('active');
            
            // Setup auto-save for notes
            if (q && q.id) {
                setupNotesAutoSave(q.id);
            }
            
            // Load billing info
            loadBillingInfo(patientId);
        } else {
            alert('Error loading patient details: ' + data.message);
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        alert('Failed to load patient details');
    });
}

function closePatientModal() {
    var modal = document.getElementById('patientModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    var patientModal = document.getElementById('patientModal');
    if (patientModal) {
        patientModal.addEventListener('click', function(e) {
            if (e.target.id === 'patientModal') {
                closePatientModal();
            }
        });
    }
    
    // Portal Pattern: Move modal to body level
    var modal = document.getElementById('patientModal');
    if (modal) {
        document.body.appendChild(modal);
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
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 99999;
    align-items: center;
    justify-content: center;
}

.fullscreen-overlay[style*="display: flex"],
.fullscreen-modal-overlay.active {
    display: flex !important;
}

.fullscreen-modal {
    background: white;
    border-radius: 16px;
    max-width: 800px;
    max-height: 90vh;
    width: 90%;
    overflow-y: auto;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    position: relative;
    z-index: 100000;
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

/* Pulse animation for ACTIVE NOW badge */
@keyframes pulse {
    0% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7);
    }
    70% {
        transform: scale(1.05);
        box-shadow: 0 0 0 10px rgba(59, 130, 246, 0);
    }
    100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(59, 130, 246, 0);
    }
}

/* 3D Tooth Chart Styles */
.tooth-chart-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
    padding: 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.tooth-arch {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 4px;
    max-width: 400px;
}

.tooth-arch-row {
    display: flex;
    justify-content: center;
    gap: 4px;
    width: 100%;
}

/* Upper Arch Layout */
.tooth-arch-row:first-child .tooth-arch {
    justify-content: flex-end;
    padding-right: 20px;
}

.tooth-arch-row:first-child .tooth-arch:last-child {
    justify-content: flex-start;
    padding-left: 20px;
}

/* Lower Arch Layout */
.tooth-arch-row:last-child .tooth-arch {
    justify-content: flex-end;
    padding-right: 20px;
}

.tooth-arch-row:last-child .tooth-arch:last-child {
    justify-content: flex-start;
    padding-left: 20px;
}

.tooth-arch-label {
    text-align: center;
    font-size: 0.75rem;
    color: #9ca3af;
    font-weight: 600;
    margin-bottom: 8px;
    min-width: 80px;
}

.tooth-wrapper {
    width: 32px;
    height: 40px;
    position: relative;
    cursor: pointer;
    transition: all 0.3s ease;
}

.tooth-wrapper:hover {
    transform: translateY(-2px);
}

.tooth-face {
    width: 100%;
    height: 100%;
    background: linear-gradient(145deg, #f3f4f6 0%, #e5e7eb 100%);
    border-radius: 8px 8px 4px 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 600;
    color: #6b7280;
    box-shadow: 
        inset 0 -3px 6px rgba(0,0,0,0.1),
        0 2px 4px rgba(0,0,0,0.15),
        0 4px 8px rgba(0,0,0,0.1);
    position: relative;
    transition: all 0.3s ease;
}

.tooth-face::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(180deg, rgba(255,255,255,0.4) 0%, rgba(255,255,255,0) 50%);
    border-radius: 8px 8px 4px 4px;
    pointer-events: none;
}

.tooth-wrapper.selected .tooth-face {
    background: linear-gradient(145deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 
        inset 0 -3px 6px rgba(0,0,0,0.2),
        0 2px 4px rgba(37,99,235,0.3),
        0 4px 12px rgba(37,99,235,0.4);
    transform: translateY(-3px);
}

.tooth-wrapper.selected .tooth-face::after {
    background: linear-gradient(180deg, rgba(255,255,255,0.3) 0%, rgba(255,255,255,0) 50%);
}

.tooth-wrapper.editing .tooth-face {
    cursor: pointer;
    animation: toothPulse 1.5s infinite;
}

@keyframes toothPulse {
    0%, 100% { box-shadow: 0 2px 4px rgba(0,0,0,0.15), 0 4px 8px rgba(0,0,0,0.1); }
    50% { box-shadow: 0 2px 8px rgba(59,130,246,0.4), 0 4px 16px rgba(59,130,246,0.3); }
}

.tooth-arch-label {
    text-align: center;
    font-size: 0.75rem;
    color: #9ca3af;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 8px;
}
.summary-cards {
    margin-bottom: 0px;
}
</style>

<script>
// Global variables for teeth editing
let currentTeethSelection = [];
let isEditingTeeth = false;
let currentQueueId = null;

// Convert teeth numbers to arch labels (matches staff terminology)
function getTeethDisplayText(teethString) {
    if (!teethString || teethString.trim() === '') {
        return 'Not specified';
    }
    
    const teeth = teethString.split(',').map(t => parseInt(t.trim())).filter(t => !isNaN(t));
    if (teeth.length === 0) {
        return 'Not specified';
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
    
    // If no full arch matches, show individual teeth
    if (parts.length === 0) {
        return teeth.sort((a, b) => a - b).join(', ');
    }
    
    return parts.join(' + ');
}

// Generate 3D Tooth Chart HTML
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

// Toggle tooth selection (for editing mode)
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
    
    // Sort numerically
    currentTeethSelection.sort((a, b) => parseInt(a) - parseInt(b));
    
    // Update display
    document.getElementById('selectedTeethDisplay').textContent = 
        'Selected: ' + (currentTeethSelection.length > 0 ? currentTeethSelection.join(', ') : 'None');
}

// Toggle edit mode for teeth
function toggleTeethEditMode(queueId) {
    isEditingTeeth = !isEditingTeeth;
    currentQueueId = queueId;
    
    const btn = document.getElementById('editTeethBtn');
    const actions = document.getElementById('teethEditActions');
    const wrappers = document.querySelectorAll('.tooth-wrapper');
    
    if (isEditingTeeth) {
        btn.textContent = 'Cancel Edit';
        btn.style.background = '#6b7280';
        actions.style.display = 'flex';
        wrappers.forEach(w => w.classList.add('editing'));
    } else {
        cancelTeethEdit();
    }
}

// Save teeth changes
function saveTeethChanges(queueId) {
    const teethString = currentTeethSelection.join(', ');
    
    fetch('update_queue_teeth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            queue_id: queueId,
            teeth_numbers: teethString
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the chart display
            document.getElementById('toothChartContainer').innerHTML = generateToothChartHTML(teethString);
            document.getElementById('selectedTeethDisplay').textContent = 'Selected: ' + (teethString || 'None');
            
            // Reset edit mode
            isEditingTeeth = false;
            document.getElementById('editTeethBtn').textContent = 'Edit Teeth';
            document.getElementById('editTeethBtn').style.background = '';
            document.getElementById('teethEditActions').style.display = 'none';
            document.querySelectorAll('.tooth-wrapper').forEach(w => w.classList.remove('editing'));
            
            alert('Teeth updated successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving teeth changes');
    });
}

// Cancel teeth editing
function cancelTeethEdit() {
    isEditingTeeth = false;
    
    const btn = document.getElementById('editTeethBtn');
    if (btn) {
        btn.textContent = 'Edit Teeth';
        btn.style.background = '';
    }
    
    const actions = document.getElementById('teethEditActions');
    if (actions) actions.style.display = 'none';
    
    // Refresh the chart to original state
    const originalTeeth = document.getElementById('selectedTeethDisplay').textContent.replace('Selected: ', '');
    if (originalTeeth !== 'None') {
        document.getElementById('toothChartContainer').innerHTML = generateToothChartHTML(originalTeeth);
        currentTeethSelection = originalTeeth.split(',').map(t => t.trim()).filter(t => t);
    } else {
        document.getElementById('toothChartContainer').innerHTML = generateToothChartHTML('');
        currentTeethSelection = [];
    }
    
    document.querySelectorAll('.tooth-wrapper').forEach(w => w.classList.remove('editing'));
}

// Save procedure notes
let notesSaveTimeout;
function saveProcedureNotes(queueId) {
    const notes = document.getElementById('procedureNotes').value;
    const statusEl = document.getElementById('notesSaveStatus');
    
    statusEl.textContent = 'Saving...';
    
    fetch('save_procedure_notes.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            queue_id: queueId,
            procedure_notes: notes
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusEl.textContent = 'Saved!';
            statusEl.style.color = '#10b981';
            setTimeout(() => {
                statusEl.textContent = '';
                statusEl.style.color = '#6b7280';
            }, 2000);
        } else {
            statusEl.textContent = 'Error saving';
            statusEl.style.color = '#ef4444';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statusEl.textContent = 'Error saving';
        statusEl.style.color = '#ef4444';
    });
}

// Auto-save notes after typing stops
function setupNotesAutoSave(queueId) {
    const notesTextarea = document.getElementById('procedureNotes');
    if (!notesTextarea) return;
    
    notesTextarea.addEventListener('input', function() {
        clearTimeout(notesSaveTimeout);
        document.getElementById('notesSaveStatus').textContent = 'Typing...';
        
        notesSaveTimeout = setTimeout(() => {
            saveProcedureNotes(queueId);
        }, 2000); // Auto-save after 2 seconds of no typing
    });
}

// Billing functions
var servicesData = {};

// Load services data for billing calculations
fetch('patient_record_details.php?id=0')
.catch(function() {})
.then(function() {})
.then(function() {
    // Services will be loaded on demand
});

function loadBillingInfo(patientId) {
    billingPatientId = patientId;
    
    // Initialize with loading state
    updateBillingDisplay({
        estimated_amount: null,
        total_amount: 0,
        paid_amount: 0,
        balance: 0,
        payment_status: 'unpaid'
    });
    
    // Add timeout to handle slow responses
    const timeoutMs = 15000; // 15 seconds
    
    const fetchPromise = fetch('dentist_billing_actions.php?action=get_billing&patient_id=' + patientId);
    const timeoutPromise = new Promise(function(_, reject) {
        setTimeout(function() {
            reject(new Error('Request timeout'));
        }, timeoutMs);
    });
    
    Promise.race([fetchPromise, timeoutPromise])
    .then(function(response) {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(function(data) {
        if (data.success && data.billing) {
            updateBillingDisplay(data.billing);
        } else {
            // No billing found, try to calculate from services
            calculateBillingFromServices(patientId);
        }
    })
    .catch(function(error) {
        console.error('Error loading billing:', error);
        calculateBillingFromServices(patientId);
    });
}

function calculateBillingFromServices(patientId) {
    // Get services from the queue for this patient
    fetch('patient_record_details.php?id=' + patientId)
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success && data.queue_item) {
            const treatmentType = data.queue_item.treatment_type || '';
            let estimatedAmount = 0;
            
            // Parse services from treatment_type
            if (treatmentType) {
                // Fetch services list
                fetch('patient_record_details.php?id=0')
                .then(function(r) { return r.json(); })
                .then(function() {})
                .catch(function() {});
                
                // Get services from PHP - use a simple approach
                var treatments = treatmentType.split(',');
                treatments.forEach(function(treatment) {
                    treatment = treatment.trim();
                    // Try to match with common services
                    var commonServices = {
                        'Teeth Cleaning': 800,
                        'Tooth Extraction': 800,
                        'Root Canal': 5000,
                        'Tooth Filling': 800,
                        'Dental X-Ray': 500,
                        'Consultation': 500,
                        'Teeth Whitening': 5000,
                        'Dental Crown': 2000,
                        'Braces': 35000,
                        'Denture': 5000
                    };
                    
                    for (var name in commonServices) {
                        if (treatment.toLowerCase().includes(name.toLowerCase())) {
                            estimatedAmount += commonServices[name];
                            break;
                        }
                    }
                });
            }
            
            updateBillingDisplay({
                estimated_amount: estimatedAmount,
                total_amount: estimatedAmount,
                paid_amount: 0,
                balance: estimatedAmount,
                payment_status: 'unpaid'
            });
        } else {
            updateBillingDisplay({
                estimated_amount: 0,
                total_amount: 0,
                paid_amount: 0,
                balance: 0,
                payment_status: 'unpaid'
            });
        }
    })
    .catch(function(error) {
        console.error('Error calculating billing:', error);
        updateBillingDisplay({
            estimated_amount: 0,
            total_amount: 0,
            paid_amount: 0,
            balance: 0,
            payment_status: 'unpaid'
        });
    });
}

function updateBillingDisplay(billing) {
    var estimatedEl = document.getElementById('detailsEstimatedAmount');
    var totalEl = document.getElementById('detailsTotalAmount');
    var balanceEl = document.getElementById('detailsBalance');
    var statusEl = document.getElementById('detailsStatus');
    
    if (!estimatedEl || !totalEl || !balanceEl || !statusEl) {
        return; // Modal not open
    }
    
    var estimatedAmount = billing.estimated_amount || 0;
    var totalAmount = billing.total_amount || 0;
    var paidAmount = billing.paid_amount || 0;
    var balance = billing.balance || 0;
    
    // Format as Philippine Pesos
    var formatter = new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2
    });
    
    if (estimatedAmount > 0) {
        estimatedEl.textContent = formatter.format(estimatedAmount);
    } else {
        estimatedEl.textContent = '₱0';
    }
    
    totalEl.textContent = formatter.format(totalAmount);
    balanceEl.textContent = formatter.format(balance);
    
    var statusHtml = '';
    if (billing.payment_status === 'paid') {
        statusHtml = '<span style="background:#d1fae5;color:#065f46;padding:4px 12px;border-radius:9999px;font-size:0.85rem;">PAID</span>';
    } else if (billing.payment_status === 'partial') {
        statusHtml = '<span style="background:#fef3c7;color:#92400e;padding:4px 12px;border-radius:9999px;font-size:0.85rem;">PARTIAL</span>';
    } else {
        statusHtml = '<span style="background:#fee2e2;color:#991b1b;padding:4px 12px;border-radius:9999px;font-size:0.85rem;">UNPAID</span>';
    }
    statusEl.innerHTML = statusHtml;
}

function openBillingFromDetails(patientId) {
    billingPatientId = patientId;
    
    fetch('dentist_billing_actions.php?action=get_billing&patient_id=' + patientId)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.billing) {
            showBillingEditModal(data.billing);
        } else {
            // Create a new billing record
            showBillingEditModal({
                billing_id: null,
                patient_id: patientId,
                patient_name: document.querySelector('.patient-name')?.textContent || 'Patient',
                treatment_type: document.querySelector('[data-queue-id]')?.textContent || 'Treatment',
                total_amount: 0,
                estimated_amount: 0,
                paid_amount: 0,
                balance: 0,
                payment_status: 'unpaid'
            });
        }
    });
}

function showBillingEditModal(billing) {
    // Create modal if doesn't exist
    let modal = document.getElementById('billingEditModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'billingEditModal';
        modal.style.cssText = 'display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;';
        modal.innerHTML = `
            <div style="background:white;border-radius:16px;padding:24px;max-width:480px;width:90%;position:relative;z-index:100000;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h2 style="margin:0;font-size:1.25rem;font-weight:600;">💰 Edit Billing Amount</h2>
                    <button onclick="closeBillingEditModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#6b7280;">×</button>
                </div>
                <div id="billingEditForm">
                    <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:14px;margin-bottom:20px;display:flex;align-items:center;gap:12px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0369a1" stroke-width="2" style="color:#0369a1;">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                        <div>
                            <div style="font-size:0.75rem;color:#0369a1;font-weight:600;">PATIENT</div>
                            <div id="billingEditPatientName" style="font-weight:600;color:#0c4a6e;"></div>
                        </div>
                    </div>
                    
                    <!-- Price Comparison -->
                    <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:14px;margin-bottom:16px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                            <span style="font-size:0.875rem;color:#92400e;font-weight:500;">Default Price (from Services)</span>
                            <span id="billingEditDefaultPrice" style="font-size:1rem;font-weight:600;color:#92400e;">₱0</span>
                        </div>
                        <div style="border-top:1px dashed #d97706;margin:8px 0;"></div>
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-size:0.875rem;color:#111827;font-weight:600;">Final Amount to Charge</span>
                            <input type="number" id="billingEditAmount" style="width:140px;padding:8px;border:2px solid #0ea5e9;border-radius:6px;font-size:1rem;text-align:right;font-weight:700;" min="0" step="0.01">
                        </div>
                        <div id="billingEditEstimated" style="font-size:0.75rem;color:#6b7280;margin-top:8px;text-align:right;"></div>
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-size:0.875rem;font-weight:500;color:#374151;margin-bottom:8px;">Reason for Adjustment (Optional)</label>
                        <input type="text" id="billingEditNotes" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;font-size:0.9rem;" placeholder="e.g., Additional procedure, discount, complex case...">
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:12px;">
                        <button onclick="closeBillingEditModal()" style="padding:10px 20px;background:white;border:1px solid #d1d5db;border-radius:8px;cursor:pointer;">Cancel</button>
                        <button onclick="saveBillingEdit()" style="padding:10px 20px;background:#0ea5e9;color:white;border:none;border-radius:8px;cursor:pointer;font-weight:500;">Save Changes</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Close on backdrop click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeBillingEditModal();
        });
    }
    
    billingQueueId = billing.queue_id;
    
    document.getElementById('billingEditPatientName').textContent = billing.patient_name || 'Unknown';
    document.getElementById('billingEditDefaultPrice').textContent = '₱' + (billing.estimated_amount || 0).toLocaleString('en-PH', {minimumFractionDigits: 2});
    document.getElementById('billingEditAmount').value = billing.total_amount || billing.estimated_amount || 0;
    document.getElementById('billingEditNotes').value = '';
    
    if (billing.total_amount !== billing.estimated_amount) {
        const difference = (billing.total_amount - billing.estimated_amount);
        const sign = difference > 0 ? '+' : '';
        document.getElementById('billingEditEstimated').textContent = '(Modified from default: ' + sign + '₱' + Math.abs(difference).toLocaleString('en-PH') + ')';
    } else {
        document.getElementById('billingEditEstimated').textContent = '(Same as default price)';
    }
    
    modal.style.display = 'flex';
}

function closeBillingEditModal() {
    const modal = document.getElementById('billingEditModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function saveBillingEdit() {
    const newAmount = parseFloat(document.getElementById('billingEditAmount').value);
    const notes = document.getElementById('billingEditNotes').value;
    
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
            closeBillingEditModal();
            // Refresh billing info
            loadBillingInfo(billingPatientId);
            alert('Billing amount updated successfully!');
        } else {
            alert(data.message || 'Error updating billing');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating billing amount');
    });
}
</script>

<?php require_once 'includes/dentist_layout_end.php'; ?>
