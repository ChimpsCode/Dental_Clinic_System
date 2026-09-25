<?php
$pageTitle = 'Dentist Dashboard';
require_once '../../config/database.php';
require_once '../../includes/queue_management_helper.php';
require_once '../../includes/dentist_layout_start.php';

try {
    ensureQueueManagementSchema($pdo);
    // Daily reset: cancel stale unfinished queue entries from previous days
    archiveStaleQueueEntries($pdo);
    require_once '../../config/database.php';
    
    // Get queue statistics - use status column directly
    $stmt = $pdo->query("
        SELECT q.status, COUNT(*) as count 
        FROM queue q 
        WHERE q.status IN ('waiting', 'in_procedure', 'completed', 'cancelled', 'on_hold') AND q.is_archived = 0
        GROUP BY q.status
    ");
    $statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $waitingCount = 0;
    $inProcedureCount = 0;
    $completedToday = 0;
    $onHoldCount = 0;
    foreach ($statusCounts as $sc) {
        switch ($sc['status'] ?? 'waiting') {
            case 'waiting': $waitingCount = (int)$sc['count']; break;
            case 'in_procedure': $inProcedureCount = (int)$sc['count']; break;
            case 'completed': $completedToday = (int)$sc['count']; break;
            case 'on_hold': $onHoldCount = (int)$sc['count']; break;
        }
    }
    
    // Get today's completed count (includes pending payment after consultation)
    $completedToday = countCompletedQueueToday($pdo);
    
    // Get patients currently in queue
    $stmt = $pdo->query("
        SELECT q.*, TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age, COALESCE(p.first_name, 'Unknown') as first_name, COALESCE(p.middle_name, '') as middle_name, COALESCE(p.last_name, '') as last_name, COALESCE(p.suffix, '') as suffix, COALESCE(p.phone, '') as phone, COALESCE(p.gender, '') as gender, COALESCE(q.status, 'waiting') as status_text
        FROM queue q
        LEFT JOIN patients p ON CAST(q.patient_id AS UNSIGNED) = p.id
        WHERE q.status IN ('waiting', 'in_procedure') AND q.is_archived = 0
        ORDER BY q.priority ASC, q.queue_time ASC
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
    
    // Get new patients for this week and last week
    $stmt = $pdo->query("SELECT COUNT(*) FROM patients WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)");
    $newPatientsThisWeek = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM patients WHERE YEARWEEK(created_at, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)");
    $newPatientsLastWeek = (int)$stmt->fetchColumn();

    $newPatientsTotalForChart = max(1, $newPatientsThisWeek + $newPatientsLastWeek);
    $newPatientsChartFillPercent = (int)round(($newPatientsThisWeek / $newPatientsTotalForChart) * 100);
    $newPatientsChartFillPercent = max(12, min(88, $newPatientsChartFillPercent));
    $newPatientsChartEmptyPercent = 100 - $newPatientsChartFillPercent;

    // Get new patients for today and yesterday
    $stmt = $pdo->query("SELECT COUNT(*) FROM patients WHERE DATE(created_at) = CURDATE()");
    $newPatientsToday = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM patients WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
    $newPatientsYesterday = (int)$stmt->fetchColumn();

    $newPatientsTodayTotalForChart = max(1, $newPatientsToday + $newPatientsYesterday);
    $newPatientsTodayFillPercent = (int)round(($newPatientsToday / $newPatientsTodayTotalForChart) * 100);
    $newPatientsTodayFillPercent = max(12, min(88, $newPatientsTodayFillPercent));
    $newPatientsTodayEmptyPercent = 100 - $newPatientsTodayFillPercent;

    // Get active queue counts for today and yesterday
    $stmt = $pdo->query("SELECT COUNT(*) FROM queue WHERE status IN ('waiting', 'in_procedure') AND is_archived = 0 AND DATE(queue_time) = CURDATE()");
    $activeQueueToday = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM queue WHERE status IN ('waiting', 'in_procedure') AND is_archived = 0 AND DATE(queue_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
    $activeQueueYesterday = (int)$stmt->fetchColumn();

    $queueTotalForChart = max(1, $activeQueueToday + $activeQueueYesterday);
    $queueChartFillPercent = (int)round(($activeQueueToday / $queueTotalForChart) * 100);
    $queueChartFillPercent = max(12, min(88, $queueChartFillPercent));
    $queueChartEmptyPercent = 100 - $queueChartFillPercent;
    
    // Get today's appointments for overview
    $apptStmt = $pdo->query("
        SELECT a.id, a.appointment_date, a.appointment_time, a.status,
               COALESCE(p.first_name, 'Unknown') as first_name, 
               COALESCE(p.middle_name, '') as middle_name, 
               COALESCE(p.last_name, '') as last_name,
               s.name as service_name,
               a.status as status_name
        FROM appointments a
        LEFT JOIN patients p ON CAST(a.patient_id AS UNSIGNED) = p.id
        LEFT JOIN services s ON a.service_id = s.id
        WHERE DATE(a.appointment_date) = CURDATE()
          AND (a.is_archived = 0 OR a.is_archived IS NULL)
        ORDER BY a.appointment_time ASC
        LIMIT 10
    ");
    $todayAppointments = $apptStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $waitingCount = 0;
    $inProcedureCount = 0;
    $completedToday = 0;
    $onHoldCount = 0;
    $queuePatients = [];
    $newPatientsThisWeek = 0;
    $newPatientsLastWeek = 0;
    $newPatientsChartFillPercent = 0;
    $newPatientsChartEmptyPercent = 0;
    $newPatientsToday = 0;
    $newPatientsYesterday = 0;
    $newPatientsTodayFillPercent = 0;
    $newPatientsTodayEmptyPercent = 0;
    $activeQueueToday = 0;
    $activeQueueYesterday = 0;
    $queueChartFillPercent = 0;
    $queueChartEmptyPercent = 0;
    $todayAppointments = [];
}
?>

<div class="two-column">
    <div class="left-column" id="dd-queue-left">
        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card card-waiting">
                <div class="summary-icon">
                    <img src="../../assets/icons/waiting.png" alt="Waiting" width="24" height="24">
                </div>
                <div class="summary-info">
                    <h3 id="dd-waiting"><?php echo $waitingCount; ?></h3>
                    <p>Waiting</p>
                </div>
            </div>
            <div class="summary-card card-in-procedure">
                <div class="summary-icon">
                    <img src="../../assets/icons/in-procedure.png" alt="In Procedure" width="24" height="24">
                </div>
                <div class="summary-info">
                    <h3 id="dd-in-procedure"><?php echo $inProcedureCount; ?></h3>
                    <p>In Procedure</p>
                </div>
            </div>
            <div class="summary-card card-completed">
                <div class="summary-icon">
                    <img src="../../assets/icons/check.png" alt="Completed" width="24" height="24">
                </div>
                <div class="summary-info">
                    <h3 id="dd-completed"><?php echo $completedToday; ?></h3>
                    <p>Completed Today</p>
                </div>
            </div>
            <div class="summary-card card-on-hold">
                <div class="summary-icon">
                    <img src="../../assets/icons/pause.png" alt="On Hold" width="24" height="24">
                </div>
                <div class="summary-info">
                    <h3 id="dd-on-hold"><?php echo $onHoldCount; ?></h3>
                    <p>On Hold</p>
                </div>
            </div>
        </div>

        <div id="dd-queue-content">
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
                        <div style="font-size: 2rem; font-weight: 700; color: #3b82f6;">#<?php echo $inProcedurePatient['queue_number'] ?? '?'; ?></div>
                        <div style="font-size: 0.85rem; color: #6b7280;">in procedure</div>
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
                            <div style="font-size:0.75rem;color:#92400e;text-transform:uppercase;letter-spacing:0.05em;margin-top:0px;margin-bottom:4px;">Elapsed</div>
                            <div id="dentist-procedure-duration" style="font-weight:600;color:#92400e;">00:00:00</div>
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
                        <div style="font-size: 2rem; font-weight: 700; color: #22c55e;">#<?php echo $nextPatient['queue_number'] ?? '1'; ?></div>
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
                        <button onclick="openStartProcedureModal(<?php echo $nextPatient['id']; ?>, '<?php echo htmlspecialchars(addslashes(trim(($nextPatient['first_name'] ?? '') . ' ' . ($nextPatient['last_name'] ?? '')))); ?>', '<?php echo htmlspecialchars(addslashes($nextPatient['treatment_type'] ?? 'General Checkup')); ?>')" class="action-btn" style="background: #22c55e; color: white; flex: 2; padding: 12px; font-size: 1.1rem; font-weight: 600; border: none; border-radius: 8px; cursor: pointer;">
                            <span style="margin-right: 6px;">▶</span> START PROCEDURE
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($upcomingPatients)): ?>
        <!-- IN THE QUEUE -->
        <div class="section-card" style="border: 1px solid #e5e7eb; background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 class="section-title" style="margin: 0; color: #374151;">
                    <span style="font-size: 1.5rem;">📋</span> IN THE QUEUE
                </h2>
                <span id="dd-queue-waiting-badge" style="background: #6b7280; color: white; padding: 6px 16px; border-radius: 20px; font-weight: 700; font-size: 0.9rem;">
                    <?php echo count($upcomingPatients); ?> waiting
                </span>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <?php foreach ($upcomingPatients as $up): ?>
                <div style="background: white; border-radius: 10px; padding: 14px 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); display: flex; justify-content: space-between; align-items: center; border-left: 4px solid #d1d5db;">
                    <div style="display: flex; align-items: center; gap: 14px; flex: 1;">
                        <div style="width: 36px; height: 36px; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #6b7280; font-size: 0.9rem; flex-shrink: 0;">
                            #<?php echo $up['queue_number'] ?? '?'; ?>
                        </div>
                        <div>
                            <div style="font-weight: 600; color: #111827; font-size: 1rem;">
                                <?php 
                                echo htmlspecialchars($up['first_name'] ?? ''); 
                                if (!empty($up['middle_name'])) {
                                    echo ' ' . htmlspecialchars($up['middle_name']);
                                }
                                echo ' ' . htmlspecialchars($up['last_name'] ?? '');
                                ?>
                            </div>
                            <div style="display: flex; gap: 8px; align-items: center; margin-top: 4px;">
                                <span style="background: #dbeafe; color: #1e40af; padding: 2px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">
                                    <?php echo htmlspecialchars($up['gender'] ?? 'Unknown'); ?>
                                </span>
                                <span style="color: #6b7280; font-size: 0.8rem;">
                                    <?php echo htmlspecialchars($up['treatment_type'] ?? 'Consultation'); ?>
                                </span>
                                <span style="color: #9ca3af; font-size: 0.75rem;">
                                    ⏰ <?php echo date('g:i A', strtotime($up['queue_time'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <button onclick="viewPatientDetails(<?php echo $up['patient_id']; ?>)" style="background: #f3f4f6; border: none; border-radius: 8px; padding: 8px 14px; color: #374151; font-weight: 600; font-size: 0.85rem; cursor: pointer; white-space: nowrap; transition: background 0.2s;" onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                        👁 View
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- UPCOMING PATIENTS -->
        <div class="section-card">
            
            
            <!-- EMPTY STATE - When no patients in queue -->
            <?php if (!$inProcedurePatient && !$nextPatient && empty($upcomingPatients)): ?>
            <div class="section-card" style="text-align: center; padding: 60px 40px; background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border: 2px dashed #d1d5db;">
            <div style="font-size: 4rem; margin-bottom: 20px;">🪑</div>
            <h2 style="color: #374151; font-size: 1.5rem; margin-bottom: 12px;">No Patients in Queue</h2>
            <p style="color: #6b7280; font-size: 1rem; margin-bottom: 24px;">
                There are no patients waiting for treatment at the moment.<br>
                Patients will appear here when they are added to the queue by staff.
            </p>
            <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
                <a href="dentist_queue.php" style="background: #2563eb; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                    View Full Queue
                </a>
                <a href="dentist_appointments.php" style="background: #6b7280; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                    View Appointments
                </a>
            </div>
        </div>
        <?php endif; ?>
        </div>
        </div>
    </div>

    <!-- Right Column: Schedule, Quick Stats & Appointments -->
    <?php
    // Mini Calendar - determine displayed month
    $dcCalMonthParam = (isset($_GET['cal']) && preg_match('/^\d{4}-\d{2}$/', $_GET['cal'])) ? $_GET['cal'] : date('Y-m');
    $dcCalYear = (int)substr($dcCalMonthParam, 0, 4);
    $dcCalMonthNum = (int)substr($dcCalMonthParam, 5, 2);
    if ($dcCalMonthNum < 1 || $dcCalMonthNum > 12) {
        $dcCalMonthNum = (int)date('n');
        $dcCalYear = (int)date('Y');
    }
    $dcCalFirstDay = sprintf('%04d-%02d-01', $dcCalYear, $dcCalMonthNum);
    $dcCalLastDay = date('Y-m-t', strtotime($dcCalFirstDay));
    $dcCalPrev = date('Y-m', strtotime($dcCalFirstDay . ' -1 month'));
    $dcCalNext = date('Y-m', strtotime($dcCalFirstDay . ' +1 month'));
    $dcCalMonthLabel = date('F Y', strtotime($dcCalFirstDay));

    $dcCalByDate = [];
    $dcCalCounts = [];
    try {
        $dcCalStmt = $pdo->prepare("
            SELECT a.appointment_date, a.appointment_time, a.status,
                   COALESCE(CONCAT(p.first_name, ' ', COALESCE(p.middle_name, ''), ' ', p.last_name), '') AS patient_name,
                   s.name AS service_name
            FROM appointments a
            LEFT JOIN patients p ON CAST(a.patient_id AS UNSIGNED) = p.id
            LEFT JOIN services s ON a.service_id = s.id
            WHERE a.appointment_date BETWEEN ? AND ?
              AND (a.is_archived = 0 OR a.is_archived IS NULL)
            ORDER BY a.appointment_date ASC, a.appointment_time ASC
        ");
        $dcCalStmt->execute([$dcCalFirstDay, $dcCalLastDay]);
        while ($dcCalRow = $dcCalStmt->fetch(PDO::FETCH_ASSOC)) {
            $d = $dcCalRow['appointment_date'];
            if (!isset($dcCalByDate[$d])) {
                $dcCalByDate[$d] = [];
                $dcCalCounts[$d] = 0;
            }
            $dcCalByDate[$d][] = $dcCalRow;
            $dcCalCounts[$d]++;
        }
    } catch (Exception $e) {
        $dcCalByDate = [];
        $dcCalCounts = [];
    }
    ?>
    <div class="right-column">
        <!-- Schedule Mini Calendar -->
        <div class="notification-box dc-cal-panel">
            

            <div class="mc-header">
                <button type="button" id="dcCalPrev" class="mc-nav" aria-label="Previous month">&#8249;</button>
                <span class="mc-title" id="dcCalMonthLabel"><?php echo $dcCalMonthLabel; ?></span>
                <button type="button" id="dcCalNext" class="mc-nav" aria-label="Next month">&#8250;</button>
            </div>

            <div class="mc-grid" id="dcCalGrid">
                <?php
                $dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                foreach ($dayHeaders as $dh) {
                    echo '<div class="mc-day-head">' . $dh . '</div>';
                }
                $dcFirstDow = (int)date('w', strtotime($dcCalFirstDay));
                for ($i = 0; $i < $dcFirstDow; $i++) {
                    echo '<div class="mc-day mc-empty"></div>';
                }
                $dcCalDaysInMonth = (int)date('t', strtotime($dcCalFirstDay));
                for ($d = 1; $d <= $dcCalDaysInMonth; $d++) {
                    $dateStr = sprintf('%04d-%02d-%02d', $dcCalYear, $dcCalMonthNum, $d);
                    $hasAppts = isset($dcCalCounts[$dateStr]);
                    $isToday = ($dateStr === date('Y-m-d'));
                    $classes = 'mc-day';
                    if ($hasAppts) { $classes .= ' has-appt'; }
                    if ($isToday) { $classes .= ' today'; }
                    ?>
                    <div class="<?php echo $classes; ?>" <?php if ($hasAppts): ?>onclick="dcCalOpenDay('<?php echo $dateStr; ?>')" title="<?php echo $dcCalCounts[$dateStr]; ?> scheduled"<?php endif; ?>>
                        <span class="mc-day-num"><?php echo $d; ?></span>
                        <?php if ($hasAppts): ?>
                            <span class="mc-dot"></span>
                        <?php endif; ?>
                    </div>
                    <?php
                }
                ?>
            </div>

            
        </div>

        <div class="notification-box quick-stats-panel">
            <h3>📊 Quick Stats</h3>
            <div class="quick-stats-grid">
                <div class="quick-stat-card">
                    <div class="quick-stat-label">New <br>Patients (This Week)</div>
                    <div class="quick-stat-value"><?php echo $newPatientsThisWeek; ?></div>
                    <div class="quick-stat-chart">
                        <span class="quick-stat-track"></span>
                        <span class="quick-stat-fill" style="width: <?php echo $newPatientsChartFillPercent; ?>%;">
                            <span class="quick-stat-tooltip">This week: <?php echo $newPatientsThisWeek; ?></span>
                        </span>
                        <span class="quick-stat-empty" style="left: <?php echo $newPatientsChartFillPercent; ?>%; width: <?php echo $newPatientsChartEmptyPercent; ?>%;">
                            <span class="quick-stat-tooltip">Last week: <?php echo $newPatientsLastWeek; ?></span>
                        </span>
                    </div>
                </div>
                <div class="quick-stat-card">
                    <div class="quick-stat-label">New <br>Patients (Today)</div>
                    <div class="quick-stat-value" id="dd-new-patients-today"><?php echo $newPatientsToday; ?></div>
                    <div class="quick-stat-chart">
                        <span class="quick-stat-track"></span>
                        <span class="quick-stat-fill" style="width: <?php echo $newPatientsTodayFillPercent; ?>%;">
                            <span class="quick-stat-tooltip">Today: <?php echo $newPatientsToday; ?></span>
                        </span>
                        <span class="quick-stat-empty" style="left: <?php echo $newPatientsTodayFillPercent; ?>%; width: <?php echo $newPatientsTodayEmptyPercent; ?>%;">
                            <span class="quick-stat-tooltip">Yesterday: <?php echo $newPatientsYesterday; ?></span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        
        
        <!-- Today's Appointments Overview
        <div class="notification-box" style="background: white; border-radius: 12px; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-top: 16px;">
            <h3 style="font-size: 1rem; font-weight: 600; color: #111827; margin-bottom: 12px;">📅 Appointments Overview</h3>
            <div style="max-height: 180px; overflow-y: auto;">
                <?php if (empty($todayAppointments)): ?>
                    <p style="color: #6b7280; text-align: center; padding: 20px;">No appointments today</p>
                <?php else: ?>
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="background: #f9fafb;">
                                <th style="text-align: left; padding: 8px; color: #6b7280; font-weight: 600; font-size: 11px;">Time</th>
                                <th style="text-align: left; padding: 8px; color: #6b7280; font-weight: 600; font-size: 11px;">Patient</th>
                                <th style="text-align: left; padding: 8px; color: #6b7280; font-weight: 600; font-size: 11px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($todayAppointments as $appt): 
                                $apptPatientName = trim(($appt['first_name'] ?? '') . ' ' . ($appt['middle_name'] ?? '') . ' ' . ($appt['last_name'] ?? ''));
                                $statusLabel = $appt['status_name'] ?? 'Scheduled';
                                
                                $badgeStyle = 'background: #dbeafe; color: #1d4ed8;';
                                if (stripos($statusLabel, 'queue') !== false) { $badgeStyle = 'background: #fef3c7; color: #92400e;'; }
                                if (stripos($statusLabel, 'pending') !== false) { $badgeStyle = 'background: #fed7aa; color: #c2410c;'; }
                                if (stripos($statusLabel, 'complete') !== false) { $badgeStyle = 'background: #dcfce7; color: #15803d;'; }
                                if (stripos($statusLabel, 'cancel') !== false) { $badgeStyle = 'background: #fee2e2; color: #dc2626;'; }
                            ?>
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 8px; color: #374151; font-size: 12px;"><?php echo date('h:i A', strtotime($appt['appointment_time'] ?? '')); ?></td>
                                <td style="padding: 8px; color: #374151; font-size: 12px;"><?php echo htmlspecialchars($apptPatientName ?: 'Unknown'); ?></td>
                                <td style="padding: 8px;">
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; <?php echo $badgeStyle; ?>">
                                        <?php echo htmlspecialchars($statusLabel); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <a href="dentist_appointments.php" style="display: block; text-align: center; margin-top: 12px; padding: 8px; background: #f3f4f6; border-radius: 6px; color: #374151; text-decoration: none; font-size: 13px;">View All Appointments</a>
        </div> -->
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

// Start Procedure Modal
function openStartProcedureModal(queueId, patientName, treatment) {
    document.getElementById('startProcedureQueueId').value = queueId;
    document.getElementById('startProcedurePatientName').textContent = patientName || 'Unknown';
    document.getElementById('startProcedureTreatment').textContent = treatment || 'General Checkup';
    document.getElementById('startProcedureModal').style.display = 'flex';
}

function closeStartProcedureModal() {
    document.getElementById('startProcedureModal').style.display = 'none';
    document.getElementById('startProcedureQueueId').value = '';
}

function confirmStartProcedure() {
    var queueId = document.getElementById('startProcedureQueueId').value;
    if (!queueId) return;

    fetch('../shared/queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'start_procedure', queue_id: parseInt(queueId) })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            closeStartProcedureModal();
            window.location.href = 'dentist_consultation.php?queue_id=' + queueId;
        } else {
            alert('Error: ' + (data.message || 'Unable to start procedure'));
        }
    })
    .catch(function() {
        alert('Unable to start procedure');
    });
}

function completeProcedure(queueId) {
    document.getElementById('completeProcedureQueueId').value = queueId;
    document.getElementById('completeProcedureModal').style.display = 'flex';
}

function closeCompleteProcedureModal() {
    document.getElementById('completeProcedureModal').style.display = 'none';
    document.getElementById('completeProcedureQueueId').value = '';
}

function confirmCompleteProcedure() {
    const queueId = document.getElementById('completeProcedureQueueId').value;
    if (!queueId) return;

    fetch('../shared/queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'complete', queue_id: queueId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeCompleteProcedureModal();
            showProcedureCompleteModal(data);
        } else {
            alert('Error: ' + (data.message || 'Unable to complete procedure'));
        }
    })
    .catch(() => {
        alert('Unable to complete procedure');
    });
}

function showProcedureCompleteModal(data) {
    const patientName = data.patient_name || 'Patient';
    const totalAmount = parseFloat(data.total_amount || 0);
    const paidAmount = parseFloat(data.paid_amount || 0);
    const balance = parseFloat(data.balance || 0);
    const paymentStatus = data.payment_status || 'unpaid';

    document.getElementById('pcPatientName').textContent = patientName;
    document.getElementById('pcTotalAmount').textContent = '₱' + totalAmount.toLocaleString('en-PH', {minimumFractionDigits: 2});
    document.getElementById('pcPaidAmount').textContent = '₱' + paidAmount.toLocaleString('en-PH', {minimumFractionDigits: 2});
    document.getElementById('pcBalance').textContent = '₱' + balance.toLocaleString('en-PH', {minimumFractionDigits: 2});

    const statusEl = document.getElementById('pcPaymentStatus');
    statusEl.textContent = paymentStatus.charAt(0).toUpperCase() + paymentStatus.slice(1);
    statusEl.className = 'pc-status-badge pc-status-' + paymentStatus;

    document.getElementById('procedureCompleteModal').style.display = 'flex';
}

function closeProcedureCompleteModal() {
    document.getElementById('procedureCompleteModal').style.display = 'none';
    (window.appCache ? window.appCache.refreshCurrent(['dentist_queue.php', 'dentist_appointments.php']) : location.reload());
}

// Billing functions
let billingAdjEditMode = false;
let currentStandardPrice = 0;
let currentTotalAmount = 0;

function escapeHtml(value) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(value));
    return div.innerHTML;
}

function toggleBillingEdit() {
    const inputContainer = document.getElementById('adjustmentInputContainer');
    const adjustmentDisplay = document.getElementById('detailsAdjustmentAmount');
    const saveBtn = document.getElementById('saveBillingBtn');
    const adjustBtn = document.getElementById('adjustPriceBtn');
    
    billingAdjEditMode = !billingAdjEditMode;
    
    if (billingAdjEditMode) {
        inputContainer.style.display = 'block';
        adjustmentDisplay.style.display = 'none';
        saveBtn.style.display = 'block';
        adjustBtn.textContent = 'Cancel';
        adjustBtn.style.background = '#6b7280';
    } else {
        inputContainer.style.display = 'none';
        adjustmentDisplay.style.display = 'block';
        saveBtn.style.display = 'none';
        adjustBtn.textContent = 'Adjust Price';
        adjustBtn.style.background = '#f59e0b';
    }
}

function updateBillingTotal() {
    const adjustmentInput = document.getElementById('adjustmentInput');
    const adjustmentAmount = parseFloat(adjustmentInput.value) || 0;
    const totalAmount = currentStandardPrice + adjustmentAmount;
    currentTotalAmount = totalAmount;
    
    const sign = adjustmentAmount >= 0 ? '+' : '';
    document.getElementById('detailsAdjustmentAmount').textContent = sign + '₱' + adjustmentAmount.toLocaleString('en-PH', {minimumFractionDigits: 2});
    document.getElementById('detailsTotalAmount').textContent = '₱' + totalAmount.toLocaleString('en-PH', {minimumFractionDigits: 2});
}

function saveBillingAdjustment(patientId, queueId) {
    const newAmount = currentTotalAmount;
    
    fetch('../shared/billing_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'update_amount',
            patient_id: patientId,
            queue_id: queueId,
            new_amount: newAmount,
            reason: 'Dentist adjustment'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Amount updated to ₱' + newAmount.toLocaleString('en-PH', {minimumFractionDigits: 2}));
            toggleBillingEdit();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to save amount');
    });
}

function togglePaymentInput() {
    const container = document.getElementById('paymentInputContainer');
    const btn = document.getElementById('recordPaymentBtn');
    if (container.style.display === 'none' || !container.style.display) {
        container.style.display = 'block';
        btn.textContent = 'Cancel';
        btn.style.background = '#6b7280';
    } else {
        container.style.display = 'none';
        btn.textContent = 'Record Payment';
        btn.style.background = '#0891b2';
    }
}

function recordPayment(patientId, queueId) {
    const amountInput = document.getElementById('paymentAmountInput');
    const methodInput = document.getElementById('paymentMethodInput');
    const amount = parseFloat(amountInput.value) || 0;
    const method = methodInput.value;

    if (amount <= 0) {
        alert('Please enter a valid payment amount.');
        return;
    }
    if (!confirm('Record payment of ₱' + amount.toLocaleString('en-PH', {minimumFractionDigits: 2}) + ' via ' + method + '?')) return;
    
    fetch('../shared/billing_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'record_payment',
            patient_id: patientId,
            queue_id: queueId,
            amount: amount,
            payment_method: method
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Payment of ₱' + amount.toLocaleString('en-PH', {minimumFractionDigits: 2}) + ' recorded.');
            togglePaymentInput();
            loadBillingInfo(patientId);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to record payment');
    });
}

function markAsPaidComplete(patientId, queueId) {
    const paidEl = document.getElementById('detailsPaidAmount');
    const paidText = paidEl ? paidEl.innerText.replace('₱', '').replace(/,/g, '') : '0';
    const paidAmount = parseFloat(paidText) || 0;
    const remaining = Math.max(0, currentTotalAmount - paidAmount);
    const amount = remaining > 0 ? remaining : currentTotalAmount;
    if (amount <= 0) { alert('This bill is already fully paid.'); return; }
    if (!confirm('Mark as paid for ₱' + amount.toLocaleString('en-PH', {minimumFractionDigits: 2}) + '?')) return;
    
    fetch('../shared/billing_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'mark_paid',
            patient_id: patientId,
            queue_id: queueId,
            amount: amount,
            treatment: 'Dental Procedure'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Payment marked as paid!');
            (window.appCache ? window.appCache.refreshCurrent(['dentist_queue.php', 'dentist_appointments.php']) : location.reload());
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to mark as paid');
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
                    <div class="patient-info-value" id="modalProcedureName" style="font-size:1.1rem;font-weight:600;">
                        ${q.service_name || q.treatment_type || 'Consultation'}
                    </div>
                    <div style="margin-top:8px;">
                        <span class="patient-info-label">Price:</span>
                        <span id="modalProcedurePrice" style="font-size:1rem;font-weight:600;color:#059669;margin-left:8px;">
                            ₱${q.service_price ? parseFloat(q.service_price).toLocaleString('en-PH', {minimumFractionDigits: 2}) : '0.00'}
                        </span>
                    </div>
                </div>

                <!-- Billing Section -->
                <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:12px;padding:20px;margin-bottom:20px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                        <h3 style="font-size:1rem;font-weight:600;color:#92400e;margin:0;">💰 Billing Management</h3>
                        <button onclick="toggleBillingEdit()" class="btn-primary" id="adjustPriceBtn" style="padding:6px 12px;font-size:0.8rem;background:#f59e0b;">Adjust Price</button>
                    </div>
                    <!-- Billing Records List -->
                    <div id="billingRecordsList" style="margin-bottom:16px;"></div>
                    <div class="patient-info-grid">
                        <div class="patient-info-item">
                            <div class="patient-info-label">Standard Price (Services)</div>
                            <div id="detailsEstimatedAmount" class="patient-info-value" style="color:#92400e;">₱0.00</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Additional Adjustment</div>
                            <div id="adjustmentInputContainer" style="display:none;">
                                <input type="number" id="adjustmentInput" class="patient-info-value" style="color:#0ea5e9;font-weight:700;padding:8px;border:2px solid #0ea5e9;border-radius:8px;width:150px;" placeholder="Enter adjustment amount" value="0" onchange="updateBillingTotal()">
                            </div>
                            <div id="detailsAdjustmentAmount" class="patient-info-value" style="color:#0ea5e8;font-weight:700;">+₱0.00</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Final Total Amount</div>
                            <div id="detailsTotalAmount" class="patient-info-value" style="font-weight:800;color:#111827;font-size:1.1rem;">₱0.00</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Paid Amount</div>
                            <div id="detailsPaidAmount" class="patient-info-value" style="color:#16a34a;">₱0.00</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Remaining Balance</div>
                            <div id="detailsBalance" class="patient-info-value" style="color:#dc2626;">₱0.00</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Payment Status</div>
                            <div id="detailsStatus" class="patient-info-value">
                                <span style="background:#fef3c7;color:#92400e;padding:4px 12px;border-radius:9999px;font-size:0.85rem;font-weight:600;">PENDING</span>
                            </div>
                        </div>
                    </div>
                    <div style="margin-top:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                        <button onclick="saveBillingAdjustment(${p.id}, ${q.id})" id="saveBillingBtn" class="btn-primary" style="padding:8px 16px;display:none;">Save Amount</button>
                        <button onclick="togglePaymentInput()" id="recordPaymentBtn" class="btn-primary" style="padding:8px 16px;background:#0891b2;">Record Payment</button>
                        <button onclick="markAsPaidComplete(${p.id}, ${q.id})" id="markPaidBtn" class="btn-primary" style="padding:8px 16px;background:#16a34a;">Mark as Fully Paid</button>
                    </div>
                    <!-- Inline Payment Form -->
                    <div id="paymentInputContainer" style="display:none;margin-top:12px;padding:12px;background:#f0fdf4;border-radius:8px;border:1px solid #bbf7d0;">
                        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                            <div>
                                <div style="font-size:0.75rem;color:#6b7280;margin-bottom:4px;">Payment Amount</div>
                                <input type="number" id="paymentAmountInput" class="patient-info-value" style="color:#111827;font-weight:700;padding:8px;border:2px solid #16a34a;border-radius:8px;width:150px;" placeholder="Enter amount" value="0">
                            </div>
                            <div>
                                <div style="font-size:0.75rem;color:#6b7280;margin-bottom:4px;">Payment Method</div>
                                <select id="paymentMethodInput" style="padding:8px;border:2px solid #d1d5db;border-radius:8px;font-size:0.9rem;font-weight:600;">
                                    <option value="cash">Cash</option>
                                    <option value="gcash">GCash</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="installment">Installment</option>
                                </select>
                            </div>
                            <button onclick="recordPayment(${p.id}, ${q.id})" class="btn-primary" style="padding:8px 20px;background:#16a34a;margin-top:16px;">Confirm Payment</button>
                            <button onclick="togglePaymentInput()" class="btn-cancel" style="padding:8px 20px;margin-top:16px;">Cancel</button>
                        </div>
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
            (window.appCache ? window.appCache.refreshCurrent(['dentist_queue.php', 'dentist_appointments.php']) : location.reload());
        } else {
            alert('Failed to update teeth: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        alert('Error saving teeth: ' + error.message);
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
            const paidAmount = parseFloat(b.paid_amount || 0);
            const balance = parseFloat(b.balance || finalTotal);
            const adjustment = finalTotal - standardPrice;
            
            // Sync global variables with actual data
            currentStandardPrice = standardPrice;
            currentTotalAmount = finalTotal;
            const adjInput = document.getElementById('adjustmentInput');
            if (adjInput) adjInput.value = 0;
            
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
            
            // Render billing records list
            const listEl = document.getElementById('billingRecordsList');
            if (listEl) {
                const allBillings = b.all_billings || [];
                if (allBillings.length > 1) {
                    let html = '<div style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:12px;">';
                    html += '<table style="width:100%;border-collapse:collapse;font-size:0.85rem;">';
                    html += '<thead><tr style="background:#fef3c7;">';
                    html += '<th style="padding:8px 10px;text-align:left;color:#92400e;font-weight:600;">Service</th>';
                    html += '<th style="padding:8px 10px;text-align:right;color:#92400e;font-weight:600;">Amount</th>';
                    html += '<th style="padding:8px 10px;text-align:right;color:#92400e;font-weight:600;">Paid</th>';
                    html += '<th style="padding:8px 10px;text-align:right;color:#92400e;font-weight:600;">Balance</th>';
                    html += '<th style="padding:8px 10px;text-align:center;color:#92400e;font-weight:600;">Status</th>';
                    html += '</tr></thead><tbody>';
                    allBillings.forEach(function(bl) {
                        const st = (bl.payment_status || 'unpaid').toLowerCase();
                        let sBg = '#fee2e2', sClr = '#991b1b', sLbl = 'UNPAID';
                        if (st === 'paid') { sBg = '#dcfce7'; sClr = '#15803d'; sLbl = 'PAID'; }
                        else if (st === 'partial') { sBg = '#fef3c7'; sClr = '#92400e'; sLbl = 'PARTIAL'; }
                        else if (st === 'overdue') { sBg = '#fee2e2'; sClr = '#991b1b'; sLbl = 'OVERDUE'; }
                        html += '<tr style="border-top:1px solid #f1f5f9;">';
                        html += '<td style="padding:8px 10px;color:#374151;font-weight:500;">' + escapeHtml(bl.service_name || 'Service') + '</td>';
                        html += '<td style="padding:8px 10px;text-align:right;color:#111827;font-weight:600;">₱' + bl.total_amount.toLocaleString('en-PH', {minimumFractionDigits: 2}) + '</td>';
                        html += '<td style="padding:8px 10px;text-align:right;color:#16a34a;font-weight:600;">₱' + bl.paid_amount.toLocaleString('en-PH', {minimumFractionDigits: 2}) + '</td>';
                        html += '<td style="padding:8px 10px;text-align:right;color:#dc2626;font-weight:600;">₱' + bl.balance.toLocaleString('en-PH', {minimumFractionDigits: 2}) + '</td>';
                        html += '<td style="padding:8px 10px;text-align:center;"><span style="background:' + sBg + ';color:' + sClr + ';padding:2px 8px;border-radius:9999px;font-size:0.75rem;font-weight:600;">' + sLbl + '</span></td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                    listEl.innerHTML = html;
                } else {
                    listEl.innerHTML = '';
                }
            }
            
            document.getElementById('detailsEstimatedAmount').innerText = '₱' + standardPrice.toLocaleString('en-PH', {minimumFractionDigits: 2});
            document.getElementById('detailsTotalAmount').innerText = '₱' + finalTotal.toLocaleString('en-PH', {minimumFractionDigits: 2});
            document.getElementById('detailsPaidAmount').innerText = '₱' + paidAmount.toLocaleString('en-PH', {minimumFractionDigits: 2});
            document.getElementById('detailsBalance').innerText = '₱' + balance.toLocaleString('en-PH', {minimumFractionDigits: 2});
            
            const adjustmentEl = document.getElementById('detailsAdjustmentAmount');
            const sign = adjustment >= 0 ? '+' : '-';
            adjustmentEl.innerText = sign + '₱' + Math.abs(adjustment).toLocaleString('en-PH', {minimumFractionDigits: 2});
            adjustmentEl.style.color = adjustment >= 0 ? '#0ea5e9' : '#dc2626';

            const status = (b.payment_status || 'unpaid').toLowerCase();
            let statusBg = '#fee2e2';
            let statusColor = '#991b1b';
            let statusLabel = status.toUpperCase();
            if (status === 'paid') { statusBg = '#dcfce7'; statusColor = '#15803d'; }
            else if (status === 'partial') { statusBg = '#fef3c7'; statusColor = '#92400e'; statusLabel = 'PARTIAL'; }
            document.getElementById('detailsStatus').innerHTML = `<span style="background:${statusBg};color:${statusColor};padding:4px 12px;border-radius:9999px;font-size:0.85rem;font-weight:600;">${statusLabel}</span>`;
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
    const notesEl = document.getElementById('procedureNotes');
    if (!notesEl) return;
    const notes = notesEl.value;
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
    if (!textarea) return;
    textarea.addEventListener('input', () => {
        clearTimeout(notesSaveTimeout);
        notesSaveTimeout = setTimeout(() => saveProcedureNotes(queueId), 2000);
    });
}

function closePatientModal() {
    document.getElementById('patientModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Mini Calendar - Scheduled Patients Modal
var dentistCalAppointments = <?php echo json_encode($dcCalByDate); ?>;

function dcCalEscapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function dcCalOpenDay(dateStr) {
    var list = dentistCalAppointments[dateStr] || [];
    var title = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    document.getElementById('dcCalModalTitle').textContent = title;

    var body = document.getElementById('dcCalModalBody');
    if (!list.length) {
        body.innerHTML = '<p style="text-align:center; color:#6b7280; padding:30px 10px;">No scheduled patients on this date.</p>';
    } else {
        var rows = list.map(function(a) {
            var name = (a.patient_name || '').trim();
            if (!name) name = 'Guest';
            var t = '--';
            if (a.appointment_time) {
                t = new Date('1970-01-01T' + a.appointment_time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
            }
            var status = a.status || 'Scheduled';
            var badge = 'background:#dbeafe; color:#1d4ed8;';
            if (status.toLowerCase().indexOf('queue') !== -1) badge = 'background:#fef3c7; color:#92400e;';
            if (status.toLowerCase().indexOf('pending') !== -1) badge = 'background:#fed7aa; color:#c2410c;';
            if (status.toLowerCase().indexOf('complete') !== -1) badge = 'background:#dcfce7; color:#15803d;';
            if (status.toLowerCase().indexOf('cancel') !== -1) badge = 'background:#fee2e2; color:#dc2626;';
            return '<tr style="border-bottom:1px solid #f3f4f6;">'
                + '<td style="padding:12px; color:#374151; white-space:nowrap;">' + t + '</td>'
                + '<td style="padding:12px; color:#374151;">' + dcCalEscapeHtml(name) + '</td>'
                + '<td style="padding:12px; color:#374151;">' + dcCalEscapeHtml(a.service_name || 'General') + '</td>'
                + '<td style="padding:12px;"><span style="display:inline-block; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; ' + badge + '">' + dcCalEscapeHtml(status) + '</span></td>'
                + '</tr>';
        }).join('');
        body.innerHTML = '<table style="width:100%; border-collapse:collapse; font-size:13px;">'
            + '<thead><tr style="background:#f9fafb;">'
            + '<th style="text-align:left; padding:10px 12px; color:#6b7280; font-weight:600; font-size:12px;">Time</th>'
            + '<th style="text-align:left; padding:10px 12px; color:#6b7280; font-weight:600; font-size:12px;">Patient</th>'
            + '<th style="text-align:left; padding:10px 12px; color:#6b7280; font-weight:600; font-size:12px;">Service</th>'
            + '<th style="text-align:left; padding:10px 12px; color:#6b7280; font-weight:600; font-size:12px;">Status</th>'
            + '</tr></thead>'
            + '<tbody>' + rows + '</tbody></table>';
    }

    document.getElementById('dcCalModal').style.display = 'flex';
}

function dcCalClose() {
    document.getElementById('dcCalModal').style.display = 'none';
}

// Move modal to body for total screen coverage
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('patientModal');
    if (modal) {
        document.body.appendChild(modal);
    }
    const cpModal = document.getElementById('completeProcedureModal');
    if (cpModal) {
        document.body.appendChild(cpModal);
    }
    const spModal = document.getElementById('startProcedureModal');
    if (spModal) {
        document.body.appendChild(spModal);
    }
    const pcModal = document.getElementById('procedureCompleteModal');
    if (pcModal) {
        document.body.appendChild(pcModal);
    }
    const dcCalEl = document.getElementById('dcCalModal');
    if (dcCalEl) {
        document.body.appendChild(dcCalEl);
    }
});

// Close modals on outside click
window.onclick = function(event) {
    const cpModal = document.getElementById('completeProcedureModal');
    const pcModal = document.getElementById('procedureCompleteModal');
    const spModal = document.getElementById('startProcedureModal');
    const dcCalModal = document.getElementById('dcCalModal');
    if (event.target === cpModal) closeCompleteProcedureModal();
    if (event.target === pcModal) closeProcedureCompleteModal();
    if (event.target === spModal) closeStartProcedureModal();
    if (event.target === dcCalModal) dcCalClose();
};

// Close modals on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const spModal = document.getElementById('startProcedureModal');
        const cpModal = document.getElementById('completeProcedureModal');
        const pcModal = document.getElementById('procedureCompleteModal');
        const dcCalModal = document.getElementById('dcCalModal');
        if (spModal && spModal.style.display === 'flex') {
            closeStartProcedureModal();
        } else if (cpModal && cpModal.style.display === 'flex') {
            closeCompleteProcedureModal();
        } else if (pcModal && pcModal.style.display === 'flex') {
            closeProcedureCompleteModal();
        } else if (dcCalModal && dcCalModal.style.display === 'flex') {
            dcCalClose();
        }
    }
});

// Queue auto-polling for dashboard (counts + queue list)
var ddQueueHash = '';
var dentistProcedureTimer = null;
var dentistProcedureStartedAt = null;

function parseDentistProcedureStart(value) {
    if (!value) return null;
    var parsed = new Date(String(value).trim().replace(' ', 'T'));
    return isNaN(parsed.getTime()) ? null : parsed;
}

function updateDentistProcedureDuration() {
    var durationEl = document.getElementById('dentist-procedure-duration');
    if (!durationEl || !dentistProcedureStartedAt) return;
    var elapsedSeconds = Math.max(0, Math.floor((Date.now() - dentistProcedureStartedAt.getTime()) / 1000));
    var hours = String(Math.floor(elapsedSeconds / 3600)).padStart(2, '0');
    var minutes = String(Math.floor((elapsedSeconds % 3600) / 60)).padStart(2, '0');
    var seconds = String(elapsedSeconds % 60).padStart(2, '0');
    durationEl.textContent = hours + ':' + minutes + ':' + seconds;
}

function setDentistProcedureTimer(startValue) {
    dentistProcedureStartedAt = parseDentistProcedureStart(startValue);
    if (dentistProcedureTimer) clearInterval(dentistProcedureTimer);
    dentistProcedureTimer = null;
    updateDentistProcedureDuration();
    if (dentistProcedureStartedAt) dentistProcedureTimer = setInterval(updateDentistProcedureDuration, 1000);
}

function pollDashboardQueue() {
    fetch('../shared/queue_poll.php')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) return;
            if (data.hash !== ddQueueHash) {
                ddQueueHash = data.hash;
                refreshDashboardQueue(data);
            }
        })
        .catch(function(e) { console.error('Dashboard queue poll error:', e); });
}

function refreshDashboardQueue(data) {
    var c = data.counts;
    var items = data.items;

    // Update summary card counts
    var el;
    el = document.getElementById('dd-waiting');
    if (el) el.textContent = c.waiting;
    el = document.getElementById('dd-in-procedure');
    if (el) el.textContent = c.in_procedure;
    el = document.getElementById('dd-completed');
    if (el) el.textContent = c.completed_today ?? c.completed;
    el = document.getElementById('dd-on-hold');
    if (el) el.textContent = c.on_hold;
    el = document.getElementById('dd-active-queue');
    if (el) el.textContent = c.waiting + c.in_procedure + c.on_hold;

    // Update the queue sections (in_procedure, next, queue list, empty state)
    var queueContent = document.getElementById('dd-queue-content');
    if (!queueContent) return;

    // Separate items by status, preserving order
    var inProcedure = null;
    var waitingItems = [];
    for (var i = 0; i < items.length; i++) {
        if (items[i].status === 'in_procedure') {
            inProcedure = items[i];
        } else if (items[i].status === 'waiting') {
            waitingItems.push(items[i]);
        }
    }

    setDentistProcedureTimer(inProcedure ? inProcedure.procedure_started_at : null);

    var nextPatient = waitingItems.length > 0 ? waitingItems[0] : null;
    var upcomingPatients = waitingItems.slice(1);
    var hasQueueContent = inProcedure || nextPatient || upcomingPatients.length > 0;

    // Build the left column HTML
    var html = '';

    // IN PROCEDURE section
    if (inProcedure) {
        html += buildInProcedureHTML(inProcedure);
    }

    // NEXT PATIENT section
    if (nextPatient) {
        html += buildNextPatientHTML(nextPatient, inProcedure);
    }

    // IN THE QUEUE section
    if (upcomingPatients.length > 0) {
        html += buildInQueueHTML(upcomingPatients);
    }

    // Empty state (only when nothing in queue)
    if (!hasQueueContent) {
        html += buildEmptyQueueHTML();
    }

    // Replace only the queue content, preserving the summary cards above it.
    queueContent.innerHTML = html;

    // Update the waiting badge in the queue section too
    el = document.getElementById('dd-queue-waiting-badge');
    if (el) el.textContent = c.waiting + ' waiting';
}

function escapeHtml(str) {
    if (!str) return '';
    var d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

function formatQueueTime(timeStr) {
    if (!timeStr) return 'N/A';
    var parts = timeStr.split(':');
    if (parts.length < 2) return timeStr;
    var h = parseInt(parts[0], 10);
    var m = parseInt(parts[1], 10);
    var ampm = h >= 12 ? 'PM' : 'AM';
    if (h === 0) h = 12;
    else if (h > 12) h = h - 12;
    return h + ':' + (m < 10 ? '0' + m : m) + ' ' + ampm;
}

function buildInProcedureHTML(p) {
    var name = escapeHtml(p.first_name || '');
    var middle = p.middle_name ? '<span style="font-weight:400;color:#6b7280;"> ' + escapeHtml(p.middle_name) + '</span>' : '';
    var lastName = escapeHtml(p.last_name || '');
    var gender = escapeHtml(p.gender || 'Unknown');
    var age = p.age || '--';
    var phone = escapeHtml(p.phone || 'No phone');
    var treatment = escapeHtml(p.treatment_type || 'Consultation');
    var teeth = p.teeth_numbers ? escapeHtml(p.teeth_numbers) : 'Not specified';
    var startedAt = formatQueueTime(p.queue_time);
    var pid = p.patient_id;
    var qid = p.id;

    return '<div class="section-card" style="border:2px solid #3b82f6;background:linear-gradient(135deg,#eff6ff 0%,#dbeafe 100%);">' +
        '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">' +
        '<h2 class="section-title" style="margin:0;color:#1e40af;"><span style="font-size:1.5rem;">⚕️</span> IN PROCEDURE</h2>' +
        '<span style="background:#3b82f6;color:white;padding:6px 16px;border-radius:20px;font-weight:700;font-size:0.9rem;animation:pulse 2s infinite;">ACTIVE NOW</span>' +
        '</div>' +
        '<div style="background:white;border-radius:12px;padding:24px;box-shadow:0 4px 15px rgba(0,0,0,0.08);">' +
        '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;">' +
        '<div><div style="font-size:1.75rem;font-weight:700;color:#111827;margin-bottom:8px;">' + name + middle + ' ' + lastName + '</div>' +
        '<div style="display:flex;gap:12px;align-items:center;">' +
        '<span style="background:#dbeafe;color:#1e40af;padding:4px 12px;border-radius:16px;font-size:0.85rem;font-weight:600;">' + gender + '</span>' +
        '<span style="color:#6b7280;font-size:0.9rem;">' + age + ' years old</span>' +
        '<span style="color:#6b7280;font-size:0.9rem;">📞 ' + phone + '</span>' +
        '</div></div>' +
        '<div style="text-align:right;"><div style="font-size:2rem;font-weight:700;color:#3b82f6;">#' + (p.queue_number || '?') + '</div><div style="font-size:0.85rem;color:#6b7280;">in procedure</div></div>' +
        '</div>' +
        '<div style="background:#f9fafb;border-radius:8px;padding:16px;margin-bottom:20px;">' +
        '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">' +
        '<div><div style="font-size:0.75rem;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Treatment</div><div style="font-weight:600;color:#111827;">' + treatment + '</div></div>' +
        '<div><div style="font-size:0.75rem;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Started At</div><div style="font-weight:600;color:#111827;">' + startedAt + '</div></div>' +
        '<div><div style="font-size:0.75rem;color:#92400e;text-transform:uppercase;letter-spacing:0.05em;margin-top:8px;margin-bottom:4px;">Elapsed</div><div id="dentist-procedure-duration" style="font-weight:600;color:#92400e;">00:00:00</div></div>' +
        '</div></div>' +
        '<div style="display:flex;gap:12px;">' +
        '<button onclick="viewPatientDetails(' + pid + ')" class="action-btn" style="background:#6b7280;color:white;flex:1;padding:12px;font-size:1rem;border:none;border-radius:8px;cursor:pointer;"><span style="margin-right:6px;">👁</span> View Details</button>' +
        '<button onclick="completeProcedure(' + qid + ')" class="action-btn" style="background:#2563eb;color:white;flex:2;padding:12px;font-size:1.1rem;font-weight:600;border:none;border-radius:8px;cursor:pointer;"><span style="margin-right:6px;">✓</span> COMPLETE PROCEDURE</button>' +
        '</div></div></div>';
}

function buildNextPatientHTML(p, inProc) {
    var name = escapeHtml(p.first_name || '');
    var middle = p.middle_name ? '<span style="font-weight:400;color:#6b7280;"> ' + escapeHtml(p.middle_name) + '</span>' : '';
    var lastName = escapeHtml(p.last_name || '');
    var gender = escapeHtml(p.gender || 'Unknown');
    var age = p.age || '--';
    var treatment = escapeHtml(p.treatment_type || 'Consultation');
    var queuedAt = formatQueueTime(p.queue_time);
    var pid = p.patient_id;
    var qid = p.id;
    var patientName = escapeHtml((p.first_name || '') + ' ' + (p.last_name || ''));

    return '<div class="section-card" style="border:2px solid #22c55e;background:linear-gradient(135deg,#f0fdf4 0%,#dcfce7 100%);">' +
        '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">' +
        '<h2 class="section-title" style="margin:0;color:#15803d;"><span style="font-size:1.5rem;">🎯</span> NEXT PATIENT</h2>' +
        '<span style="background:#22c55e;color:white;padding:6px 16px;border-radius:20px;font-weight:700;font-size:0.9rem;">UP NOW</span>' +
        '</div>' +
        '<div style="background:white;border-radius:12px;padding:24px;box-shadow:0 4px 15px rgba(0,0,0,0.08);">' +
        '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;">' +
        '<div><div style="font-size:1.75rem;font-weight:700;color:#111827;margin-bottom:8px;">' + name + middle + ' ' + lastName + '</div>' +
        '<div style="display:flex;gap:12px;align-items:center;">' +
        '<span style="background:#dbeafe;color:#1e40af;padding:4px 12px;border-radius:16px;font-size:0.85rem;font-weight:600;">' + gender + '</span>' +
        '<span style="color:#6b7280;font-size:0.9rem;">' + age + ' years old</span>' +
        '</div></div>' +
        '<div style="text-align:right;"><div style="font-size:2rem;font-weight:700;color:#22c55e;">#' + (p.queue_number || '1') + '</div><div style="font-size:0.85rem;color:#6b7280;">in queue</div></div>' +
        '</div>' +
        '<div style="background:#f9fafb;border-radius:8px;padding:16px;margin-bottom:20px;">' +
        '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">' +
        '<div><div style="font-size:0.75rem;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Treatment</div><div style="font-weight:600;color:#111827;">' + treatment + '</div></div>' +
        '<div><div style="font-size:0.75rem;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Queued At</div><div style="font-weight:600;color:#111827;">' + queuedAt + '</div></div>' +
        '</div></div>' +
        '<div style="display:flex;gap:12px;">' +
        '<button onclick="viewPatientDetails(' + pid + ')" class="action-btn" style="background:#6b7280;color:white;flex:1;padding:12px;font-size:1rem;border:none;border-radius:8px;cursor:pointer;"><span style="margin-right:6px;">👁</span> View Details</button>' +
        (inProc
            ? '<button disabled class="action-btn" style="background:#9ca3af;color:white;flex:2;padding:12px;font-size:1.1rem;font-weight:600;cursor:not-allowed;opacity:0.6;" title="Complete current procedure first"><span style="margin-right:6px;">🔒</span> WAITING - COMPLETE CURRENT FIRST</button>'
            : '<button onclick="openStartProcedureModal(' + qid + ',\'' + patientName.replace(/'/g, "\\'") + '\',\'' + treatment.replace(/'/g, "\\'") + '\')" class="action-btn" style="background:#22c55e;color:white;flex:2;padding:12px;font-size:1.1rem;font-weight:600;border:none;border-radius:8px;cursor:pointer;"><span style="margin-right:6px;">▶</span> START PROCEDURE</button>'
        ) +
        '</div></div></div>';
}

function buildInQueueHTML(upcoming) {
    var items = '';
    for (var i = 0; i < upcoming.length; i++) {
        var p = upcoming[i];
        var name = escapeHtml(p.first_name || '');
        var middle = p.middle_name ? ' ' + escapeHtml(p.middle_name) : '';
        var lastName = escapeHtml(p.last_name || '');
        var gender = escapeHtml(p.gender || 'Unknown');
        var treatment = escapeHtml(p.treatment_type || 'Consultation');
        var time = formatQueueTime(p.queue_time);
        var pid = p.patient_id;

        items += '<div style="background:white;border-radius:10px;padding:14px 18px;box-shadow:0 1px 3px rgba(0,0,0,0.06);display:flex;justify-content:space-between;align-items:center;border-left:4px solid #d1d5db;">' +
            '<div style="display:flex;align-items:center;gap:14px;flex:1;">' +
            '<div style="width:36px;height:36px;background:#f3f4f6;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:#6b7280;font-size:0.9rem;flex-shrink:0;">#' + (p.queue_number || '?') + '</div>' +
            '<div>' +
            '<div style="font-weight:600;color:#111827;font-size:1rem;">' + name + middle + ' ' + lastName + '</div>' +
            '<div style="display:flex;gap:8px;align-items:center;margin-top:4px;">' +
            '<span style="background:#dbeafe;color:#1e40af;padding:2px 10px;border-radius:12px;font-size:0.75rem;font-weight:600;">' + gender + '</span>' +
            '<span style="color:#6b7280;font-size:0.8rem;">' + treatment + '</span>' +
            '<span style="color:#9ca3af;font-size:0.75rem;">⏰ ' + time + '</span>' +
            '</div></div></div>' +
            '<button onclick="viewPatientDetails(' + pid + ')" style="background:#f3f4f6;border:none;border-radius:8px;padding:8px 14px;color:#374151;font-weight:600;font-size:0.85rem;cursor:pointer;white-space:nowrap;transition:background 0.2s;" onmouseover="this.style.background=\'#e5e7eb\'" onmouseout="this.style.background=\'#f3f4f6\'">👁 View</button>' +
            '</div>';
    }

    var totalWaiting = upcoming.length + 1;
    return '<div class="section-card" style="border:1px solid #e5e7eb;background:linear-gradient(135deg,#f9fafb 0%,#f3f4f6 100%);">' +
        '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">' +
        '<h2 class="section-title" style="margin:0;color:#374151;"><span style="font-size:1.5rem;">📋</span> IN THE QUEUE</h2>' +
        '<span id="dd-queue-waiting-badge" style="background:#6b7280;color:white;padding:6px 16px;border-radius:20px;font-weight:700;font-size:0.9rem;">' + totalWaiting + ' waiting</span>' +
        '</div>' +
        '<div style="display:flex;flex-direction:column;gap:8px;">' + items + '</div></div>';
}

function buildEmptyQueueHTML() {
    return '<div class="section-card" style="text-align:center;padding:60px 40px;background:linear-gradient(135deg,#f9fafb 0%,#f3f4f6 100%);border:2px dashed #d1d5db;">' +
        '<div style="font-size:4rem;margin-bottom:20px;">🪑</div>' +
        '<h2 style="color:#374151;font-size:1.5rem;margin-bottom:12px;">No Patients in Queue</h2>' +
        '<p style="color:#6b7280;font-size:1rem;margin-bottom:24px;">There are no patients waiting for treatment at the moment.<br>Patients will appear here when they are added to the queue by staff.</p>' +
        '<div style="display:flex;justify-content:center;gap:12px;flex-wrap:wrap;">' +
        '<a href="dentist_queue.php" style="background:#0d92f7;color:white;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;">View Full Queue</a>' +
        '<a href="dentist_appointments.php" style="background:#6b7280;color:white;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;">View Appointments</a>' +
        '</div></div>';
}

setInterval(pollDashboardQueue, 10000);
pollDashboardQueue();
</script>

<script src="../../assets/js/calendar_widget.js"></script>
<script>
if (typeof MiniCalendar !== 'undefined' && document.getElementById('dcCalPrev')) {
    MiniCalendar({
        containerId: 'dcCalGrid',
        labelId: 'dcCalMonthLabel',
        prevBtnId: 'dcCalPrev',
        nextBtnId: 'dcCalNext',
        endpoint: '../shared/calendar_data.php',
        openDayFn: 'dcCalOpenDay',
        appointmentVar: 'dentistCalAppointments',
        month: '<?php echo $dcCalMonthParam; ?>'
    });
}
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

<!-- Complete Procedure Modal -->
<div id="completeProcedureModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999999; align-items: center; justify-content: center;">
    <div class="complete-procedure-modal" style="position: relative; z-index: 100000;">
        <div class="cp-bg-orb cp-bg-orb-a"></div>
        <div class="cp-bg-orb cp-bg-orb-b"></div>
        <div class="cp-banner">
            <div class="cp-icon-wrap">
                <span class="cp-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M9.2 16.2 5.5 12.5l-1.4 1.4 5.1 5.1L20 8.2l-1.4-1.4z"></path>
                    </svg>
                </span>
            </div>
            <div>
                <div class="cp-kicker">Dentist Queue</div>
                <h2 style="font-size: 1.55rem; font-weight: 700; margin: 6px 0 0;">Complete Procedure</h2>
            </div>
            <button onclick="closeCompleteProcedureModal()" class="cp-close">&times;</button>
        </div>
        <div class="cp-body">
            <p class="cp-text">Mark procedure as complete and move this patient to billing?</p>
            <div class="cp-highlight">
                <div class="cp-highlight-icon">&#129463;</div>
                <div>
                    <div class="cp-highlight-label">Patient Care Flow</div>
                    <div class="cp-highlight-value">Procedure finishes here, billing continues next.</div>
                </div>
            </div>
            <div class="cp-note">
                <span class="cp-note-dot"></span>
                The dashboard and payment list will refresh after confirmation.
            </div>
        </div>
        <input type="hidden" id="completeProcedureQueueId">
        <div class="cp-actions">
            <button type="button" onclick="closeCompleteProcedureModal()" class="cp-btn-cancel">Keep Open</button>
            <button type="button" onclick="confirmCompleteProcedure()" class="cp-btn-confirm">Yes, Complete</button>
        </div>
    </div>
</div>

<!-- Start Procedure Modal -->
<div id="startProcedureModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999999; align-items: center; justify-content: center;">
    <div class="start-procedure-modal" style="position: relative; z-index: 100000;">
        <div class="sp-bg-orb sp-bg-orb-a"></div>
        <div class="sp-bg-orb sp-bg-orb-b"></div>
        <div class="sp-banner">
            <div class="sp-icon-wrap">
                <span class="sp-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </span>
            </div>
            <div>
                <div class="sp-kicker">Dentist Dashboard</div>
                <h2 style="font-size: 1.55rem; font-weight: 700; margin: 6px 0 0;">Start Procedure</h2>
            </div>
            <button onclick="closeStartProcedureModal()" class="sp-close">&times;</button>
        </div>
        <div class="sp-body">
            <p class="sp-text">You are about to start the procedure for this patient.</p>
            <div class="sp-highlight">
                <div class="sp-highlight-icon">&#129463;</div>
                <div>
                    <div class="sp-highlight-label">Patient</div>
                    <div class="sp-highlight-value" id="startProcedurePatientName">-</div>
                </div>
            </div>
            <div class="sp-highlight">
                <div class="sp-highlight-icon">&#128137;</div>
                <div>
                    <div class="sp-highlight-label">Treatment</div>
                    <div class="sp-highlight-value" id="startProcedureTreatment">-</div>
                </div>
            </div>
            <div class="sp-note">
                <span class="sp-note-dot"></span>
                This will move the patient to "In Procedure" status.
            </div>
        </div>
        <input type="hidden" id="startProcedureQueueId">
        <div class="sp-actions">
            <button type="button" onclick="closeStartProcedureModal()" class="sp-btn-cancel">Cancel</button>
            <button type="button" onclick="confirmStartProcedure()" class="sp-btn-confirm">Start Procedure</button>
        </div>
    </div>
</div>

<!-- Procedure Complete Success Modal -->
<div id="procedureCompleteModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999999; align-items: center; justify-content: center;">
    <div class="pc-modal" style="position: relative; z-index: 100000;">
        <div class="pc-success-ring">
            <div class="pc-success-check">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
        </div>
        <h2 class="pc-title">Procedure Complete!</h2>
        <p class="pc-subtitle">Treatment for <strong id="pcPatientName">Patient</strong> has been completed successfully.</p>

        <div class="pc-billing-card">
            <div class="pc-billing-header">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;color:#0891b2;">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                    <line x1="1" y1="10" x2="23" y2="10"></line>
                </svg>
                <span>Billing Summary</span>
            </div>
            <div class="pc-billing-rows">
                <div class="pc-billing-row">
                    <span class="pc-billing-label">Total Amount</span>
                    <span class="pc-billing-value" id="pcTotalAmount">₱0.00</span>
                </div>
                <div class="pc-billing-row">
                    <span class="pc-billing-label">Amount Paid</span>
                    <span class="pc-billing-value pc-paid" id="pcPaidAmount">₱0.00</span>
                </div>
                <div class="pc-billing-divider"></div>
                <div class="pc-billing-row pc-balance-row">
                    <span class="pc-billing-label">Remaining Balance</span>
                    <span class="pc-billing-value pc-balance" id="pcBalance">₱0.00</span>
                </div>
            </div>
            <div class="pc-status-row">
                <span class="pc-billing-label">Payment Status</span>
                <span class="pc-status-badge" id="pcPaymentStatus">Unpaid</span>
            </div>
        </div>

        <div class="pc-info-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;flex-shrink:0;color:#0891b2;">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <span>Patient has been moved to billing. You can manage payment from the billing page.</span>
        </div>

        <button type="button" onclick="closeProcedureCompleteModal()" class="pc-btn-done">Done</button>
    </div>
</div>

<!-- Schedule Mini Calendar - Scheduled Patients Modal -->
<div id="dcCalModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 14px; width: 92%; max-width: 640px; max-height: 82vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 24px 60px rgba(15,23,42,0.25); position: relative; z-index: 100000; margin: 20px;">
        <div style="display: flex; align-items: center; gap: 12px; padding: 18px 22px; background: linear-gradient(135deg, #2444d5 10%, #412fa3be 48%, #0891b2 90%); color: white;">
            <span style="font-size: 1.3rem;">📅</span>
            <div>
                <div style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.85; font-weight: 700;">Scheduled Patients</div>
                <h2 id="dcCalModalTitle" style="font-size: 1.15rem; font-weight: 700; margin: 2px 0 0;">—</h2>
            </div>
            <button onclick="dcCalClose()" aria-label="Close" style="margin-left: auto; background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.22); width: 34px; height: 34px; border-radius: 10px; font-size: 1.2rem; line-height: 1; cursor: pointer;">&times;</button>
        </div>
        <div id="dcCalModalBody" style="padding: 18px 22px; overflow-y: auto;"></div>
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
    padding: 40px;
    box-sizing: border-box;
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

/* Complete Procedure Modal */
.complete-procedure-modal {
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
.cp-bg-orb { position: absolute; border-radius: 999px; pointer-events: none; filter: blur(8px); opacity: 0.55; }
.cp-bg-orb-a { width: 140px; height: 140px; right: -30px; top: 88px; background: radial-gradient(circle, rgba(14, 165, 233, 0.2) 0%, rgba(14, 165, 233, 0) 72%); }
.cp-bg-orb-b { width: 160px; height: 160px; left: -40px; bottom: -30px; background: radial-gradient(circle, rgba(34, 197, 94, 0.16) 0%, rgba(34, 197, 94, 0) 74%); }
.cp-banner {
    display: flex; align-items: center; gap: 16px; padding: 24px 26px 18px;
    background: linear-gradient(135deg, rgba(36, 68, 213), rgba(255, 255, 255, 0) 42%), linear-gradient(135deg, #2444d5 0%, #412fa3be 48%, #0891b2 90%);
    color: white; position: relative;
}
.cp-icon-wrap {
    width: 54px; height: 54px; border-radius: 16px; display: flex; align-items: center; justify-content: center;
    background: rgba(255, 255, 255, 0.16); border: 1px solid rgba(255, 255, 255, 0.22); backdrop-filter: blur(6px); flex-shrink: 0;
}
.cp-icon { display: flex; align-items: center; justify-content: center; }
.cp-icon svg { width: 26px; height: 26px; fill: currentColor; }
.cp-kicker { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.12em; opacity: 0.9; font-weight: 700; }
.cp-close {
    margin-left: auto; background: rgba(255, 255, 255, 0.12); color: white;
    border: 1px solid rgba(255, 255, 255, 0.18); width: 40px; height: 40px; border-radius: 12px;
    font-size: 1.5rem; cursor: pointer; backdrop-filter: blur(8px);
}
.cp-body { padding: 24px 26px 12px; position: relative; }
.cp-text { color: #1f2937; font-size: 1.06rem; line-height: 1.6; margin: 0 0 16px; font-weight: 500; }
.cp-highlight {
    display: flex; align-items: center; gap: 14px; padding: 14px 16px; border-radius: 16px;
    background: linear-gradient(135deg, #ecfeff 0%, #f0fdf4 100%); border: 1px solid #d9f99d; margin-bottom: 14px;
}
.cp-highlight-icon {
    width: 42px; height: 42px; border-radius: 14px; display: flex; align-items: center; justify-content: center;
    background: white; box-shadow: 0 10px 24px rgba(8, 145, 178, 0.12); font-size: 1.25rem; flex-shrink: 0;
}
.cp-highlight-label { color: #0f766e; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; margin-bottom: 3px; }
.cp-highlight-value { color: #1f2937; font-size: 0.95rem; font-weight: 600; }
.cp-note {
    display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 14px;
    background: #f0fdf4; color: #166534; font-size: 0.92rem; border: 1px solid #dcfce7;
}
.cp-note-dot { width: 10px; height: 10px; border-radius: 999px; background: #22c55e; box-shadow: 0 0 0 5px rgba(34, 197, 94, 0.14); flex-shrink: 0; }
.cp-actions { display: flex; justify-content: flex-end; gap: 12px; padding: 18px 26px 26px; }
.cp-btn-cancel, .cp-btn-confirm {
    border: none; border-radius: 14px; padding: 13px 20px; cursor: pointer; font-weight: 700; font-size: 0.97rem;
    transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
}
.cp-btn-cancel { background: #eef6fb; color: #315266; border: 1px solid #d8e9f4; }
.cp-btn-confirm { background: linear-gradient(135deg, #2444d5 0%, #412fa3be 48%, #0891b2 90%); color: white; box-shadow: 0 14px 28px rgba(8, 145, 178, 0.24); }
.cp-btn-cancel:hover, .cp-btn-confirm:hover, .cp-close:hover { transform: translateY(-1px); box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08); }
@media (max-width: 640px) {
    .cp-banner, .cp-body, .cp-actions { padding-left: 18px; padding-right: 18px; }
    .cp-actions { flex-direction: column-reverse; }
    .cp-btn-cancel, .cp-btn-confirm { width: 100%; }
}
.start-procedure-modal {
    position: relative;
    background:
        radial-gradient(circle at top right, rgba(14, 165, 233, 0.12), transparent 32%),
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
.sp-bg-orb { position: absolute; border-radius: 999px; pointer-events: none; filter: blur(8px); opacity: 0.55; }
.sp-bg-orb-a { width: 140px; height: 140px; right: -30px; top: 88px; background: radial-gradient(circle, rgba(14, 165, 233, 0.2) 0%, rgba(14, 165, 233, 0) 72%); }
.sp-bg-orb-b { width: 160px; height: 160px; left: -40px; bottom: -30px; background: radial-gradient(circle, rgba(34, 197, 94, 0.16) 0%, rgba(34, 197, 94, 0) 74%); }
.sp-banner {
    display: flex; align-items: center; gap: 16px; padding: 24px 26px 18px;
    background: linear-gradient(135deg, rgba(255,255,255,0.12), rgba(255,255,255,0) 42%), linear-gradient(135deg, #2444d5 0%, #412fa3be 48%, #0891b2 90%);
    color: white; position: relative;
}
.sp-icon-wrap {
    width: 54px; height: 54px; border-radius: 16px; display: flex; align-items: center; justify-content: center;
    background: rgba(255, 255, 255, 0.16); border: 1px solid rgba(255, 255, 255, 0.22); backdrop-filter: blur(6px); flex-shrink: 0;
}
.sp-icon { display: flex; align-items: center; justify-content: center; }
.sp-icon svg { width: 26px; height: 26px; fill: currentColor; }
.sp-kicker { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.12em; opacity: 0.9; font-weight: 700; }
.sp-close {
    margin-left: auto; background: rgba(255, 255, 255, 0.12); color: white;
    border: 1px solid rgba(255, 255, 255, 0.18); width: 40px; height: 40px; border-radius: 12px;
    font-size: 1.5rem; cursor: pointer; backdrop-filter: blur(8px);
}
.sp-body { padding: 24px 26px 12px; position: relative; }
.sp-text { color: #1f2937; font-size: 1.06rem; line-height: 1.6; margin: 0 0 16px; font-weight: 500; }
.sp-highlight {
    display: flex; align-items: center; gap: 14px; padding: 14px 16px; border-radius: 16px;
    background: linear-gradient(135deg, #ecfeff 0%, #f0fdf4 100%); border: 1px solid #d9f99d; margin-bottom: 14px;
}
.sp-highlight-icon {
    width: 42px; height: 42px; border-radius: 14px; display: flex; align-items: center; justify-content: center;
    background: white; box-shadow: 0 10px 24px rgba(8, 145, 178, 0.12); font-size: 1.25rem; flex-shrink: 0;
}
.sp-highlight-label { color: #0f766e; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; margin-bottom: 3px; }
.sp-highlight-value { color: #1f2937; font-size: 0.95rem; font-weight: 600; }
.sp-note {
    display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 14px;
    background: #f0fdf4; color: #166534; font-size: 0.92rem; border: 1px solid #dcfce7;
}
.sp-note-dot { width: 10px; height: 10px; border-radius: 999px; background: #22c55e; box-shadow: 0 0 0 5px rgba(34, 197, 94, 0.14); flex-shrink: 0; }
.sp-actions { display: flex; justify-content: flex-end; gap: 12px; padding: 18px 26px 26px; }
.sp-btn-cancel, .sp-btn-confirm {
    border: none; border-radius: 14px; padding: 13px 20px; cursor: pointer; font-weight: 700; font-size: 0.97rem;
    transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
}
.sp-btn-cancel { background: #eef6fb; color: #315266; border: 1px solid #d8e9f4; }
.sp-btn-confirm { background: linear-gradient(135deg, #2444d5 0%, #412fa3be 48%, #0891b2 90%); color: white; box-shadow: 0 14px 28px rgba(8, 145, 178, 0.24); }
.sp-btn-cancel:hover, .sp-btn-confirm:hover, .sp-close:hover { transform: translateY(-1px); box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08); }
@media (max-width: 640px) {
    .sp-banner, .sp-body, .sp-actions { padding-left: 18px; padding-right: 18px; }
    .sp-actions { flex-direction: column-reverse; }
    .sp-btn-cancel, .sp-btn-confirm { width: 100%; }
}

/* Procedure Complete Success Modal */
.pc-modal {
    background: white;
    border-radius: 22px;
    padding: 36px 32px 32px;
    width: 90%;
    max-width: 460px;
    text-align: center;
    position: relative;
    z-index: 100000;
    box-shadow: 0 30px 80px rgba(15, 23, 42, 0.24);
    animation: pcModalPop 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes pcModalPop {
    0% { transform: scale(0.85); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}
.pc-success-ring {
    width: 80px; height: 80px; margin: 0 auto 20px;
    border-radius: 50%;
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    display: flex; align-items: center; justify-content: center;
    animation: pcRingPulse 2s ease-in-out infinite;
}
@keyframes pcRingPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.3); }
    50% { box-shadow: 0 0 0 14px rgba(34, 197, 94, 0); }
}
.pc-success-check {
    width: 48px; height: 48px; border-radius: 50%;
    background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
    display: flex; align-items: center; justify-content: center;
    color: white;
}
.pc-success-check svg { width: 26px; height: 26px; }
.pc-title {
    font-size: 1.4rem; font-weight: 800; color: #1e293b; margin: 0 0 6px;
}
.pc-subtitle {
    font-size: 0.95rem; color: #64748b; margin: 0 0 24px; line-height: 1.5;
}
.pc-subtitle strong { color: #1e293b; font-weight: 700; }
.pc-billing-card {
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px;
    padding: 18px 20px; margin-bottom: 18px; text-align: left;
}
.pc-billing-header {
    display: flex; align-items: center; gap: 8px; margin-bottom: 14px;
    font-size: 0.82rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.06em; color: #0891b2;
}
.pc-billing-rows { display: flex; flex-direction: column; gap: 10px; }
.pc-billing-row { display: flex; justify-content: space-between; align-items: center; }
.pc-billing-label { font-size: 0.88rem; color: #64748b; font-weight: 500; }
.pc-billing-value { font-size: 1rem; font-weight: 700; color: #1e293b; }
.pc-billing-value.pc-paid { color: #16a34a; }
.pc-billing-divider { height: 1px; background: #e2e8f0; margin: 4px 0; }
.pc-balance-row .pc-billing-label { font-weight: 700; color: #1e293b; }
.pc-billing-value.pc-balance {
    font-size: 1.15rem; font-weight: 800;
    background: linear-gradient(135deg, #0891b2, #0f766e);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
}
.pc-status-row {
    display: flex; justify-content: space-between; align-items: center;
    margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0;
}
.pc-status-badge {
    padding: 4px 12px; border-radius: 20px; font-size: 0.78rem;
    font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
}
.pc-status-unpaid { background: #fef2f2; color: #dc2626; }
.pc-status-partial { background: #fffbeb; color: #d97706; }
.pc-status-paid { background: #f0fdf4; color: #16a34a; }
.pc-status-pending { background: #eff6ff; color: #2563eb; }
.pc-info-note {
    display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px;
    border-radius: 12px; background: #eff6ff; border: 1px solid #bfdbfe;
    font-size: 0.85rem; color: #1e40af; line-height: 1.5; text-align: left;
    margin-bottom: 22px;
}
.pc-btn-done {
    width: 100%; border: none; border-radius: 14px; padding: 14px 24px;
    background: linear-gradient(135deg, #0891b2 0%, #0f9f53 100%);
    color: white; font-weight: 700; font-size: 1rem; cursor: pointer;
    box-shadow: 0 14px 28px rgba(8, 145, 178, 0.24);
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.pc-btn-done:hover {
    transform: translateY(-1px);
    box-shadow: 0 18px 36px rgba(8, 145, 178, 0.32);
}

/* Dentist calendar overrides */
.mc-day { height: 30px; }
.mc-day-head { padding: 3px 0; }

/* Right column fits 100% view height (no page scroll needed) */
@media (min-width: 1025px) {
    .right-column {
        position: sticky;
        top: 0.1px;
        align-self: flex-start;
        max-height: calc(100vh - 130px);
        overflow-y: auto;
    }
}
.dc-cal-panel {
    padding: 14px;
    margin-bottom: 16px;
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
.summary-cards .summary-card.card-waiting::after {
    background-image: url('../../assets/icons/waiting.png');
}
.summary-cards .summary-card.card-in-procedure::after {
    background-image: url('../../assets/icons/in-procedure.png');
}
.summary-cards .summary-card.card-completed::after {
    background-image: url('../../assets/icons/check.png');
}
.summary-cards .summary-card.card-on-hold::after {
    background-image: url('../../assets/icons/pause.png');
}

@media (max-width: 1200px) {
    .summary-cards {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 12px;
    }
}

</style>

<?php require_once '../../includes/dentist_layout_end.php'; ?>
