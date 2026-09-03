<?php
$pageTitle = 'Dashboard';
require_once '../../config/database.php';
require_once '../../includes/queue_management_helper.php';
require_once __DIR__ . '/../../includes/appointment_approval_helper.php';

if (isset($_GET['ajax']) && $_GET['ajax'] === 'dashboard') {
    try {
        ensureQueueManagementSchema($pdo);
        archiveStaleQueueEntries($pdo);
        $stmt = $pdo->query("
            SELECT q.*, COALESCE(p.first_name, 'Unknown') as first_name, COALESCE(p.middle_name, '') as middle_name, COALESCE(p.last_name, '') as last_name, COALESCE(p.suffix, '') as suffix, COALESCE(p.phone, '') as phone, COALESCE(q.status, 'waiting') as status_text
            FROM queue q 
            LEFT JOIN patients p ON CAST(q.patient_id AS UNSIGNED) = p.id 
            WHERE q.status IN ('waiting', 'in_procedure', 'completed', 'cancelled', 'on_hold') AND q.is_archived = 0
            ORDER BY 
                CASE COALESCE(q.status, 'waiting')
                    WHEN 'in_procedure' THEN 1 
                    WHEN 'waiting' THEN 2 
                    WHEN 'completed' THEN 3 
                    ELSE 2
                END,
                q.queue_time ASC
        ");
        $queueItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $waitingItems = array_values(array_filter($queueItems, fn($q) => ($q['status_text'] ?? '') === 'waiting'));
        $cancelledItems = array_values(array_filter($queueItems, fn($q) => ($q['status_text'] ?? '') === 'cancelled'));
        usort($cancelledItems, function ($first, $second) {
            $firstTime = strtotime($first['updated_at'] ?? $first['created_at'] ?? '') ?: 0;
            $secondTime = strtotime($second['updated_at'] ?? $second['created_at'] ?? '') ?: 0;
            return $secondTime <=> $firstTime;
        });
        $inProcedureItem = array_values(array_filter($queueItems, fn($q) => ($q['status_text'] ?? '') === 'in_procedure'));
        $inProcedureItem = !empty($inProcedureItem) ? $inProcedureItem[0] : null;

        ensureAppointmentApprovalSchema($pdo);
        autoDeclineExpiredPendingAppointments($pdo);

        $pendingApprovalItems = $pdo->query("
            SELECT a.id, a.appointment_date, a.appointment_time, a.status,
                   COALESCE(p.first_name, 'Unknown') as first_name,
                   COALESCE(p.middle_name, '') as middle_name,
                   COALESCE(p.last_name, '') as last_name,
                   COALESCE(p.phone, '') as phone,
                   COALESCE(s.name, 'General') as service_name
            FROM appointments a
            LEFT JOIN patients p ON CAST(a.patient_id AS UNSIGNED) = p.id
            LEFT JOIN services s ON a.service_id = s.id
            WHERE LOWER(a.status) = 'pending approval'
              AND (a.is_archived = 0 OR a.is_archived IS NULL)
            ORDER BY a.appointment_date ASC, a.appointment_time ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $upcomingApptItems = $pdo->query("
            SELECT a.id, a.appointment_date, a.appointment_time, a.status,
                   COALESCE(p.first_name, 'Unknown') as first_name,
                   COALESCE(p.middle_name, '') as middle_name,
                   COALESCE(p.last_name, '') as last_name,
                   COALESCE(s.name, 'General') as service_name
            FROM appointments a
            LEFT JOIN patients p ON CAST(a.patient_id AS UNSIGNED) = p.id
            LEFT JOIN services s ON a.service_id = s.id
            WHERE LOWER(a.status) = 'scheduled'
              AND TIMESTAMP(a.appointment_date, a.appointment_time) >= NOW()
              AND TIMESTAMP(a.appointment_date, a.appointment_time) <= NOW() + INTERVAL 24 HOUR
              AND (a.is_archived = 0 OR a.is_archived IS NULL)
            ORDER BY a.appointment_date ASC, a.appointment_time ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $liveQueue = null;
        if ($inProcedureItem) {
            $liveQueue = [
                'id' => (int)($inProcedureItem['id'] ?? 0),
                'patient_name' => trim(($inProcedureItem['first_name'] ?? '') . ' ' . ($inProcedureItem['middle_name'] ?? '') . ' ' . ($inProcedureItem['last_name'] ?? '') . ' ' . ($inProcedureItem['suffix'] ?? '')),
                'queue_time' => $inProcedureItem['queue_time'] ?? '--:--',
                'treatment_type' => $inProcedureItem['treatment_type'] ?? 'Queue Empty',
                'procedure_started_at' => $inProcedureItem['updated_at'] ?? null,
            ];
        }

        $waitingPayload = [];
        foreach ($waitingItems as $item) {
            $waitingPayload[] = [
                'id' => (int)($item['id'] ?? 0),
                'patient_id' => (int)($item['patient_id'] ?? 0),
                'patient_name' => trim(($item['first_name'] ?? '') . ' ' . ($item['middle_name'] ?? '') . ' ' . ($item['last_name'] ?? '') . ' ' . ($item['suffix'] ?? '')),
                'queue_number' => $item['queue_number'] ?? ($item['id'] ?? ''),
                'queue_time' => $item['queue_time'] ?? '',
                'treatment_type' => $item['treatment_type'] ?? '',
            ];
        }

        $cancelledPayload = [];
        foreach ($cancelledItems as $item) {
            $cancelledPayload[] = [
                'id' => (int)($item['id'] ?? 0),
                'patient_id' => (int)($item['patient_id'] ?? 0),
                'patient_name' => trim(($item['first_name'] ?? '') . ' ' . ($item['middle_name'] ?? '') . ' ' . ($item['last_name'] ?? '') . ' ' . ($item['suffix'] ?? '')),
                'treatment_type' => $item['treatment_type'] ?? '',
                'cancelled_at' => $item['updated_at'] ?? $item['created_at'] ?? null
            ];
        }

        $pendingPayload = [];
        foreach ($pendingApprovalItems as $appt) {
            $pendingPayload[] = [
                'id' => (int)($appt['id'] ?? 0),
                'patient_name' => trim(($appt['first_name'] ?? '') . ' ' . ($appt['middle_name'] ?? '') . ' ' . ($appt['last_name'] ?? '')),
                'phone' => $appt['phone'] ?? '',
                'service_name' => $appt['service_name'] ?? 'General',
                'appointment_date' => $appt['appointment_date'] ?? '',
                'appointment_time' => $appt['appointment_time'] ?? '',
            ];
        }

        $upcomingPayload = [];
        foreach ($upcomingApptItems as $appt) {
            $upcomingPayload[] = [
                'id' => (int)($appt['id'] ?? 0),
                'patient_name' => trim(($appt['first_name'] ?? '') . ' ' . ($appt['middle_name'] ?? '') . ' ' . ($appt['last_name'] ?? '')),
                'service_name' => $appt['service_name'] ?? 'General',
                'appointment_date' => $appt['appointment_date'] ?? '',
                'appointment_time' => $appt['appointment_time'] ?? '',
            ];
        }

        // Today's Schedule disabled — removed from dashboard payload per request
        $todayScheduleItems = [];
        $todaySchedulePayload = [];

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'live_queue' => $liveQueue,
            'waiting' => $waitingPayload,
            'cancelled' => $cancelledPayload,
            'pending_approval' => $pendingPayload,
            'upcoming_appointments' => $upcomingPayload,
            //'today_schedule' => $todaySchedulePayload,
            'counts' => [
                'waiting' => count($waitingItems),
                'cancelled' => count($cancelledItems),
                'pending' => count($pendingApprovalItems),
                'upcoming' => count($upcomingApptItems),
                //'today' => count($todayScheduleItems),
            ],
        ], JSON_UNESCAPED_SLASHES);
        exit;
    } catch (Throwable $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => true, 'message' => $e->getMessage(), 'waiting' => [], 'pending_approval' => [], 'upcoming_appointments' => [], 'live_queue' => null], JSON_UNESCAPED_SLASHES);
        exit;
    }
}

require_once '../../includes/staff_layout_start.php';

try {
    // Ensure queue table has proper schema
    ensureQueueManagementSchema($pdo);
    // Daily reset: cancel stale unfinished queue entries from previous days
    archiveStaleQueueEntries($pdo);
    // Get queue data
    $stmt = $pdo->query("
        SELECT q.*, COALESCE(p.first_name, 'Unknown') as first_name, COALESCE(p.middle_name, '') as middle_name, COALESCE(p.last_name, '') as last_name, COALESCE(p.suffix, '') as suffix, COALESCE(p.phone, '') as phone, COALESCE(q.status, 'waiting') as status_text
        FROM queue q 
        LEFT JOIN patients p ON CAST(q.patient_id AS UNSIGNED) = p.id 
        WHERE q.status IN ('waiting', 'in_procedure', 'completed', 'cancelled', 'on_hold') AND q.is_archived = 0
        ORDER BY 
            CASE COALESCE(q.status, 'waiting')
                WHEN 'in_procedure' THEN 1 
                WHEN 'waiting' THEN 2 
                WHEN 'completed' THEN 3 
                ELSE 2
            END,
            q.queue_time ASC
    ");
    $queueItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $waitingCount = count(array_filter($queueItems, fn($q) => ($q['status_text'] ?? '') === 'waiting'));
    $procedureCount = count(array_filter($queueItems, fn($q) => ($q['status_text'] ?? '') === 'in_procedure'));
    $completedCount = count(array_filter($queueItems, fn($q) => ($q['status_text'] ?? '') === 'completed'));
    $onHoldCount = count(array_filter($queueItems, fn($q) => ($q['status_text'] ?? '') === 'on_hold'));
    $cancelledCount = count(array_filter($queueItems, fn($q) => ($q['status_text'] ?? '') === 'cancelled'));
    
    $inProcedureItem = array_filter($queueItems, fn($q) => ($q['status_text'] ?? '') === 'in_procedure');
    $inProcedureItem = !empty($inProcedureItem) ? reset($inProcedureItem) : null;

    $waitingItems = array_filter($queueItems, fn($q) => ($q['status_text'] ?? '') === 'waiting');

    // Pending Approval appointments
    ensureAppointmentApprovalSchema($pdo);
    autoDeclineExpiredPendingAppointments($pdo);

    $pendingApprovalAll = $pdo->query("
        SELECT a.id, a.appointment_date, a.appointment_time, a.status,
               COALESCE(p.first_name, 'Unknown') as first_name,
               COALESCE(p.middle_name, '') as middle_name,
               COALESCE(p.last_name, '') as last_name,
               COALESCE(p.phone, '') as phone,
               COALESCE(s.name, 'General') as service_name
        FROM appointments a
        LEFT JOIN patients p ON CAST(a.patient_id AS UNSIGNED) = p.id
        LEFT JOIN services s ON a.service_id = s.id
        WHERE LOWER(a.status) = 'pending approval'
          AND (a.is_archived = 0 OR a.is_archived IS NULL)
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $pendingApprovalCount = count($pendingApprovalAll);
    $pendingApprovalItems = array_slice($pendingApprovalAll, 0, 3);

    // Upcoming Appointments (within the next 24 hours)
    $upcomingApptAll = $pdo->query("
        SELECT a.id, a.appointment_date, a.appointment_time, a.status,
               COALESCE(p.first_name, 'Unknown') as first_name,
               COALESCE(p.middle_name, '') as middle_name,
               COALESCE(p.last_name, '') as last_name,
               COALESCE(s.name, 'General') as service_name
        FROM appointments a
        LEFT JOIN patients p ON CAST(a.patient_id AS UNSIGNED) = p.id
        LEFT JOIN services s ON a.service_id = s.id
        WHERE LOWER(a.status) = 'scheduled'
          AND TIMESTAMP(a.appointment_date, a.appointment_time) >= NOW()
          AND TIMESTAMP(a.appointment_date, a.appointment_time) <= NOW() + INTERVAL 24 HOUR
          AND (a.is_archived = 0 OR a.is_archived IS NULL)
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $upcomingApptCount = count($upcomingApptAll);
    $upcomingApptItems = array_slice($upcomingApptAll, 0, 3);

        // Today's Schedule removed from UI — keep placeholders in case of future re-enable
        $todayScheduleItems = [];
        $todayScheduleCount = 0;

    // Helper function to build full name
    function buildPatientName($item) {
        return trim(($item['first_name'] ?? '') . ' ' . ($item['middle_name'] ?? '') . ' ' . ($item['last_name'] ?? '') . ' ' . ($item['suffix'] ?? ''));
    }
    
} catch (Exception $e) {
    $queueItems = [];
    $waitingCount = 0;
    $procedureCount = 0;
    $completedCount = 0;
    $onHoldCount = 0;
    $cancelledCount = 0;
    $inProcedureItem = null;
    $waitingItems = [];
    $pendingApprovalAll = [];
    $pendingApprovalCount = 0;
    $pendingApprovalItems = [];
    $upcomingApptAll = [];
    $upcomingApptCount = 0;
    $upcomingApptItems = [];
    $todayScheduleItems = [];
}

?>

<!-- VIEW 1: DASHBOARD -->
<div id="view-dashboard" class="view-section active">
    <!-- Cancelled Modal -->
    <div id="cancelledModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
        <div class="modal" style="
        background: white; 
        border-radius: 12px; 
        padding: 0; 
        width: 90%; 
        max-width: 700px; 
        max-height: 80vh; 
        overflow-y: auto; 
        position: relative; 
        z-index: 100000;
        padding: 20px;
        ">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 1.25rem; font-weight: 600; margin: 0;">Cancelled Queue</h2>
                <button onclick="closeCancelledModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">×</button>
            </div>
            <div style="display: flex; gap: 12px; margin-bottom: 20px;">
                <button onclick="filterCancelled('today')" id="cancelled-today-btn" style="padding: 8px 24px; border: 1px solid #d1d5db; border-radius: 6px; background: #2563eb; color: white; cursor: pointer;">Today</button>
                <button onclick="filterCancelled('week')" id="cancelled-week-btn" style="padding: 8px 24px; border: 1px solid #d1d5db; border-radius: 6px; background: white; color: #374151; cursor: pointer;">This Week</button>
            </div>
            <div id="cancelled-modal-list">
                <div id="cancelled-today-list">
                    <?php 
                    $cancelledItems = array_filter($queueItems, fn($q) => ($q['status_text'] ?? '') === 'cancelled');
                    usort($cancelledItems, function ($first, $second) {
                        $firstTime = strtotime($first['updated_at'] ?? $first['created_at'] ?? '') ?: 0;
                        $secondTime = strtotime($second['updated_at'] ?? $second['created_at'] ?? '') ?: 0;
                        return $secondTime <=> $firstTime;
                    });
                    if (empty($cancelledItems)): ?>
                        <p style="text-align: center; color: #6b7280; padding: 20px;">No cancelled patients today</p>
                    <?php else: ?>
                <?php foreach ($cancelledItems as $item):
                                $cancelledPatientName = buildPatientName($item);
                        ?>
                        <div class="patient-item" id="cancelled-<?php echo $item['id']; ?>">
                            <div class="patient-info">
                                <div class="patient-name" style="text-decoration: line-through; color: #9ca3af;"><?php echo htmlspecialchars($cancelledPatientName ?: 'Unknown'); ?></div>
                                <div class="patient-details">
                                    <span class="status-badge cancelled">Cancelled</span>
                                    <?php $cancelledAt = $item['updated_at'] ?? $item['created_at'] ?? null; ?>
                                    <?php if ($cancelledAt): ?>
                                        <span style="font-size: 0.78rem; color: #6b7280;">Cancelled: <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($cancelledAt))); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="patient-treatment"><?php echo htmlspecialchars($item['treatment_type'] ?? ''); ?></div>
                            </div>
                            <div class="patient-actions">
                                <button class="action-btn icon view-btn" onclick="viewPatientDashboard(<?php echo (int)$item['patient_id']; ?>)">👁️</button>
                                <button class="action-btn" style="background: #84cc16; color: white; padding: 8px 12px; border-radius: 6px; border: none; font-size: 12px; cursor: pointer;" onclick="requeuePatientDashboard(<?php echo $item['id']; ?>)">Re-queue</button>
                                <button class="action-btn icon delete-btn" type="button" onclick="deleteCancelledPatient(<?php echo (int)$item['id']; ?>)" title="Delete cancelled record">
                                    <svg viewBox="-2.4 -2.4 28.80 28.80" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#000000" width="16" height="16"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" stroke="#CCCCCC" stroke-width="0.144"></g><g id="SVGRepo_iconCarrier"> <path d="M5.82907 6.65808H18.6325V19.2906C18.6325 20.3951 17.7371 21.2906 16.6325 21.2906H7.82907C6.7245 21.2906 5.82907 20.3951 5.82907 19.2906V6.65808Z" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M4 5.74365L20.4615 5.74365" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M14.9134 3H9.54816L8.57266 5.74359H15.8889L14.9134 3Z" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></path> <line x1="12.3163" y1="10.4017" x2="12.3163" y2="17.547" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></line> <line x1="9.57266" y1="10.4017" x2="9.57266" y2="17.547" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></line> <line x1="15.0598" y1="10.4017" x2="15.0598" y2="17.547" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></line> </g></svg>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div id="cancelled-week-list" style="display: none;">
                    <p style="text-align: center; color: #6b7280; padding: 20px;">No cancelled patients this week</p>
                </div>
            </div>
        </div>
    </div>

<!-- Scheduled Patients Modal -->
<div id="scCalModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 14px; width: 92%; max-width: 640px; max-height: 82vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 24px 60px rgba(15,23,42,0.25); position: relative; z-index: 100000; margin: 20px;">
        <div style="display: flex; align-items: center; gap: 12px; padding: 18px 22px; background: linear-gradient(135deg, #2444d5 10%, #412fa3be 48%, #0891b2 90%); color: white;">
            <span style="font-size: 1.3rem;">📅</span>
            <div>
                <div style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.85; font-weight: 700;">Scheduled Patients</div>
                <h2 id="scCalModalTitle" style="font-size: 1.15rem; font-weight: 700; margin: 2px 0 0;">—</h2>
            </div>
            <button onclick="scCalClose()" aria-label="Close" style="margin-left: auto; background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.22); width: 34px; height: 34px; border-radius: 10px; font-size: 1.2rem; line-height: 1; cursor: pointer;">&times;</button>
        </div>
        <div id="scCalModalBody" style="padding: 18px 22px; overflow-y: auto;"></div>
    </div>
</div>

    <div class="two-column">
        <div class="left-column">
            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card card-waiting">
                    <div class="summary-icon yellow">
                        <img src="../../assets/icons/waiting.png" alt="Waiting">
                    </div>
                    <div class="summary-info">
                        <h3 id="count-waiting"><?php echo $waitingCount; ?></h3>
                        <p>Waiting</p>
                    </div>
                </div>
                <div class="summary-card card-completed">
                    <div class="summary-icon green">
                        <img src="../../assets/icons/check.png" alt="Completed">
                    </div>
                    <div class="summary-info">
                        <h3 id="count-completed"><?php echo $completedCount; ?></h3>
                        <p>Completed</p>
                    </div>
                </div>
                <div class="summary-card card-cancelled">
                    <div class="summary-icon red">
                        <img src="../../assets/icons/delete.png" alt="Cancelled">
                    </div>
                    <div class="summary-info">
                        <h3 id="count-cancelled"><?php echo $cancelledCount; ?></h3>
                        <p style="cursor: pointer;" onclick="openCancelledModal()">Cancelled</p>
                    </div>
                </div>
                <div class="summary-card card-on-hold">
                    <div class="summary-icon gray">
                        <img src="../../assets/icons/pause.png" alt="On Hold">
                    </div>
                    <div class="summary-info">
                        <h3 id="count-skipped"><?php echo $onHoldCount; ?></h3>
                        <p>On Hold</p>
                    </div>
                </div>
            </div>

            <!-- Live Queue Controller -->
            <div class="live-queue">
                    <div class="live-queue-header">
                        <div class="live-queue-title">Live Queue Controller</div>
                        <div class="now-serving-badge">
                            <span>
                            
                            </span>
                            <span>In Procedure</span></div>
                    </div>
                    <div class="live-patient">
                        <?php $inProcedureName = $inProcedureItem ? buildPatientName($inProcedureItem) : ''; ?>
                        <div class="patient-name" id="live-patient-name"><?php echo $inProcedureItem ? htmlspecialchars($inProcedureName) : 'No Patient'; ?></div>
                        <div class="patient-details" style="margin-top: 10px;">
                            <span class="status-badge now-serving">Time Started</span>
                            <span class="patient-time" id="live-patient-time"><?php echo $inProcedureItem ? htmlspecialchars($inProcedureItem['queue_time']) : '--:--'; ?></span>
                            <span class="status-badge" style="background:#fef3c7;color:#92400e;">Elapsed</span>
                            <span class="patient-time" id="live-procedure-duration">00:00:00</span>
                        </div>
                        <div class="patient-treatment" id="live-patient-treatment" style="margin-top: 8px;"><?php echo $inProcedureItem ? htmlspecialchars($inProcedureItem['treatment_type']) : 'Queue Empty'; ?></div>
                    </div>
                    <?php if ($inProcedureItem): ?>
                    <button class="complete-btn" id="completeTreatmentBtn" onclick="completeCurrentPatient(<?php echo $inProcedureItem['id']; ?>)">
                        <span>✓</span> <span>Complete Treatment</span>
                    </button>
                    <?php else: ?>
                    <button class="complete-btn" id="completeTreatmentBtn" disabled style="background: #9ca3af;">
                        <span>✓</span> <span>No Patient in Chair</span>
                    </button>
                    <?php endif; ?>
                    <div class="complete-btn-text">Click when patient treatment is finished</div>
                </div>

            <!-- Up Next + On Hold + Pending Approval -->
            <div class="queue-grid">
                <div class="queue-grid-left">

                <div class="section-card compact-queue">
                    <h2 class="section-title">📋 IN THE QUEUE <span class="section-count" id="count-up-next"><?php echo count($waitingItems); ?></span></h2>
                    <div class="patient-list" id="up-next-list">
                        <?php if (empty($waitingItems)): ?>
                            <div style="text-align: center; padding: 0; color: #6b7280;">
                                <p>No patients waiting</p>
                                <a href="staff_new_admission.php" class="btn-primary" style="display: inline-block; margin-top: 12px; text-decoration: none;">Add New Patient</a>
                            </div>
                    <?php else: ?>
                            <?php foreach (array_slice($waitingItems, 0, 5) as $index => $item):
                                $waitingPatientName = buildPatientName($item);
                            ?>
                            <div class="patient-item" id="next-<?php echo $item['id']; ?>">
                                <div class="patient-info">
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div class="queue-avatar">
                                            #<?php echo htmlspecialchars($item['queue_number'] ?? ($item['id'] ?? '')); ?>
                                        </div>
                                        <div style="min-width:0;">
                                            <div class="patient-name"><?php echo htmlspecialchars($waitingPatientName ?: 'Unknown'); ?></div>
                                            <div class="patient-details" style="margin-top:3px;">
                                                <span class="status-badge waiting">Waiting</span>
                                                <span class="patient-time"><?php echo htmlspecialchars($item['queue_time'] ?? ''); ?></span>
                                            </div>
                                            <div class="patient-treatment"><?php echo htmlspecialchars($item['treatment_type'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="patient-actions">
                                    <button class="action-btn text-btn" onclick="viewPatientDashboard(<?php echo $item['patient_id']; ?>)">View</button>
                                    <button class="action-btn text-btn" onclick="moveToOnHoldDashboard(<?php echo $item['id']; ?>)">Hold</button>
                                    <button 
                                        class="action-btn text-btn queue-cancel-btn" 
                                        onclick="cancelPatientDashboard(<?php echo $item['id']; ?>)">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <a href="staff_queue.php" class="see-all-link">View All (<?php echo count($waitingItems); ?>)</a>
                </div>

                <!-- On Hold -->
                <?php
                $onHoldItems = array_filter($queueItems, fn($q) => ($q['status_text'] ?? '') === 'on_hold');
                $onHoldCount = count($onHoldItems);
                ?>
                <div class="section-card compact-queue">
                    <h2 class="section-title">⏸️ On Hold <span class="section-count" id="count-on-hold"><?php echo $onHoldCount; ?></span></h2>
                    <div class="patient-list" id="onhold-list">
                        <?php if (empty($onHoldItems)): ?>
                            <div style="text-align: center; padding: 0; color: #6b7280;">
                                <p>No patients on hold</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($onHoldItems as $item):
                                $onHoldPatientName = buildPatientName($item);
                            ?>
                            <div class="patient-item" id="onhold-<?php echo $item['id']; ?>">
                                <div class="patient-info">
                                    <div class="patient-name"><?php echo htmlspecialchars($onHoldPatientName ?: 'Unknown'); ?></div>
                                    <div class="patient-details" style="margin-top:3px;">
                                        <span class="status-badge cancelled">On Hold</span>
                                    </div>
                                    <div class="patient-treatment"><?php echo htmlspecialchars($item['treatment_type'] ?? ''); ?></div>
                                </div>
                                <div class="patient-actions">
                                    <button class="action-btn icon view-btn" onclick="viewPatientDashboard(<?php echo (int)$item['patient_id']; ?>)">
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" width="16" height="16"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path opacity="0.15" fill-rule="evenodd" clip-rule="evenodd" d="M12 5C5.63636 5 2 12 2 12C2 12 5.63636 19 12 19C18.3636 19 22 12 22 12C22 12 18.3636 5 12 5ZM15 12C15 13.6569 13.6569 15 12 15C10.3431 15 9 13.6569 9 12C9 10.3431 10.3431 9 12 9C13.6569 9 15 10.3431 15 12Z" fill="#000000"></path> <path d="M12 5C5.63636 5 2 12 2 12C2 12 5.63636 19 12 19C18.3636 19 22 12 22 12C22 12 18.3636 5 12 5Z" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15Z" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>
                                    </button>
                                    <button class="action-btn text-btn onhold-requeue-btn" onclick="requeuePatientDashboard(<?php echo $item['id']; ?>)">Re-queue</button>
                                    <button class="action-btn icon delete-btn">
                                        <svg viewBox="-2.4 -2.4 28.80 28.80" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#000000" width="16" height="16"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" stroke="#CCCCCC" stroke-width="0.144"></g><g id="SVGRepo_iconCarrier"> <path d="M5.82907 6.65808H18.6325V19.2906C18.6325 20.3951 17.7371 21.2906 16.6325 21.2906H7.82907C6.7245 21.2906 5.82907 20.3951 5.82907 19.2906V6.65808Z" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M4 5.74365L20.4615 5.74365" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M14.9134 3H9.54816L8.57266 5.74359H15.8889L14.9134 3Z" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></path> <line x1="12.3163" y1="10.4017" x2="12.3163" y2="17.547" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></line> <line x1="9.57266" y1="10.4017" x2="9.57266" y2="17.547" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></line> <line x1="15.0598" y1="10.4017" x2="15.0598" y2="17.547" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></line> </g></svg>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                </div>

                <div class="queue-grid-right">
                <!-- Pending Approval -->
                <div class="section-card compact-queue">
                    <h2 class="section-title">🕓 Pending Approval <span class="section-count pending-count" id="count-pending-approval"><?php echo $pendingApprovalCount; ?></span></h2>
                    <div class="patient-list" id="pending-approval-list">
                        <?php if (empty($pendingApprovalItems)): ?>
                            <div style=" padding: 0; color: #6b7280;">
                                <p>No pending approvals</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pendingApprovalItems as $appt):
                                $paName = trim(($appt['first_name'] ?? '') . ' ' . ($appt['middle_name'] ?? '') . ' ' . ($appt['last_name'] ?? ''));
                                $paDate = !empty($appt['appointment_date']) ? date('M j', strtotime($appt['appointment_date'])) : '';
                                $paTime = !empty($appt['appointment_time']) ? date('h:i A', strtotime($appt['appointment_time'])) : '';
                            ?>
                            <div class="patient-item" id="pending-appt-<?php echo $appt['id']; ?>">
                                <div class="patient-info">
                                    <div class="patient-name"><?php echo htmlspecialchars($paName ?: 'Unknown'); ?></div>
                                    <div class="patient-details" style="margin-top:3px;">
                                        <span class="status-badge pending">Pending</span>
                                        <span class="patient-time"><?php echo htmlspecialchars(trim($paDate . ' · ' . $paTime)); ?></span>
                                    </div>
                                    <div class="patient-treatment"><?php echo htmlspecialchars($appt['service_name'] ?? 'General'); ?></div>
                                </div>
                                <div class="patient-actions">
                                    
                                    <button class="action-btn pa-approve" onclick="approveAppointment(<?php echo (int)$appt['id']; ?>)">✓</button>
                                    <button class="action-btn pa-decline" onclick="declineAppointment(<?php echo (int)$appt['id']; ?>)">✕</button>
                                    <button class="action-btn pa-call" title="View phone number" onclick="openCallModal('<?php echo htmlspecialchars($paName ?: 'Unknown', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($appt['phone'] ?? '', ENT_QUOTES); ?>')">
                                        <img src="../../assets/icons/call_button.png" alt="Call">
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <a href="staff_appointments.php" class="see-all-link" style="<?php echo $pendingApprovalCount <= 3 ? 'display:none;' : ''; ?>">View All (<?php echo $pendingApprovalCount; ?>)</a>
                </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Notifications & Reminders -->
        <?php
        // Mini Calendar - determine displayed month
        $scCalMonthParam = (isset($_GET['cal']) && preg_match('/^\d{4}-\d{2}$/', $_GET['cal'])) ? $_GET['cal'] : date('Y-m');
        $scCalYear = (int)substr($scCalMonthParam, 0, 4);
        $scCalMonthNum = (int)substr($scCalMonthParam, 5, 2);
        if ($scCalMonthNum < 1 || $scCalMonthNum > 12) {
            $scCalMonthNum = (int)date('n');
            $scCalYear = (int)date('Y');
        }
        $scCalFirstDay = sprintf('%04d-%02d-01', $scCalYear, $scCalMonthNum);
        $scCalLastDay = date('Y-m-t', strtotime($scCalFirstDay));
        $scCalPrev = date('Y-m', strtotime($scCalFirstDay . ' -1 month'));
        $scCalNext = date('Y-m', strtotime($scCalFirstDay . ' +1 month'));
        $scCalMonthLabel = date('F Y', strtotime($scCalFirstDay));

        $scCalByDate = [];
        $scCalCounts = [];
        try {
            $scCalStmt = $pdo->prepare("
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
            $scCalStmt->execute([$scCalFirstDay, $scCalLastDay]);
            while ($scCalRow = $scCalStmt->fetch(PDO::FETCH_ASSOC)) {
                $d = $scCalRow['appointment_date'];
                if (!isset($scCalByDate[$d])) {
                    $scCalByDate[$d] = [];
                    $scCalCounts[$d] = 0;
                }
                $scCalByDate[$d][] = $scCalRow;
                $scCalCounts[$d]++;
            }
        } catch (Exception $e) {
            $scCalByDate = [];
            $scCalCounts = [];
        }
        ?>
        <div class="right-column">
            <div class="notification-box">
                

                <div class="mc-header">
                    <button type="button" id="scCalPrev" class="mc-nav" aria-label="Previous month">&#8249;</button>
                    <span class="mc-title" id="scCalMonthLabel"><?php echo $scCalMonthLabel; ?></span>
                    <button type="button" id="scCalNext" class="mc-nav" aria-label="Next month">&#8250;</button>
                </div>

                <div class="mc-grid" id="scCalGrid">
                    <?php
                    $dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                    foreach ($dayHeaders as $dh) {
                        echo '<div class="mc-day-head">' . $dh . '</div>';
                    }
                    $scFirstDow = (int)date('w', strtotime($scCalFirstDay));
                    for ($i = 0; $i < $scFirstDow; $i++) {
                        echo '<div class="mc-day mc-empty"></div>';
                    }
                    $scCalDaysInMonth = (int)date('t', strtotime($scCalFirstDay));
                    for ($d = 1; $d <= $scCalDaysInMonth; $d++) {
                        $dateStr = sprintf('%04d-%02d-%02d', $scCalYear, $scCalMonthNum, $d);
                        $hasAppts = isset($scCalCounts[$dateStr]);
                        $isToday = ($dateStr === date('Y-m-d'));
                        $classes = 'mc-day';
                        if ($hasAppts) { $classes .= ' has-appt'; }
                        if ($isToday) { $classes .= ' today'; }
                        ?>
                        <div class="<?php echo $classes; ?>" <?php if ($hasAppts): ?>onclick="scCalOpenDay('<?php echo $dateStr; ?>')" title="<?php echo $scCalCounts[$dateStr]; ?> scheduled"<?php endif; ?>>
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
            
              
            <!-- Upcoming Appointments (next 24h) -->
            <div class="section-card compact-queue">
                <h2 class="section-title">📅 Upcoming Appointments <span class="section-count upcoming-count" id="count-upcoming-appt"><?php echo $upcomingApptCount; ?></span></h2>
                <div class="patient-list" id="upcoming-appt-list">
                    <?php if (empty($upcomingApptItems)): ?>
                        <div style="text-align: center; padding: 0; color: #6b7280;">
                            <p>No upcoming appointments</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcomingApptItems as $appt):
                            $uaName = trim(($appt['first_name'] ?? '') . ' ' . ($appt['middle_name'] ?? '') . ' ' . ($appt['last_name'] ?? ''));
                            $uaDate = !empty($appt['appointment_date']) ? date('M j', strtotime($appt['appointment_date'])) : '';
                            $uaTime = !empty($appt['appointment_time']) ? date('h:i A', strtotime($appt['appointment_time'])) : '';
                        ?>
                        <div class="patient-item">
                            <div class="patient-info">
                                <div class="patient-name"><?php echo htmlspecialchars($uaName ?: 'Unknown'); ?></div>
                                <div class="patient-details" style="margin-top:3px;">
                                    <span class="status-badge scheduled">Scheduled</span>
                                    <span class="patient-time"><?php echo htmlspecialchars(trim($uaDate . ' · ' . $uaTime)); ?></span>
                                </div>
                                <div class="patient-treatment"><?php echo htmlspecialchars($appt['service_name'] ?? 'General'); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <a href="staff_appointments.php?focus=scheduled" class="see-all-btn">View Upcoming Appointments</a>
            </div>

                <!-- Today's Schedule removed per request -->
        </div>
    </div>

<!-- Patient Record Modal -->
<div id="patientRecordModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div class="modal" style="background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 700px; max-height: 85vh; overflow-y: auto; position: relative; z-index: 100000;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="font-size: 1.25rem; font-weight: 600; margin: 0;">Patient Record</h2>
            <button onclick="closePatientRecordModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">×</button>
        </div>
        <div id="patientRecordContent"></div>
    </div>
</div>

<!-- Complete Treatment Modal -->
<div id="completeTreatmentModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div class="modal complete-treatment-modal" style="position: relative; z-index: 100000;">
        <div class="complete-treatment-bg-orb complete-treatment-bg-orb-a"></div>
        <div class="complete-treatment-bg-orb complete-treatment-bg-orb-b"></div>
        <div class="complete-treatment-banner">
            <div class="complete-treatment-icon-wrap">
                <span class="complete-treatment-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M9.2 16.2 5.5 12.5l-1.4 1.4 5.1 5.1L20 8.2l-1.4-1.4z"></path>
                    </svg>
                </span>
            </div>
            <div>
                <div class="complete-treatment-kicker">Live Queue Controller</div>
                <h2 style="font-size: 1.55rem; font-weight: 700; margin: 6px 0 0;">Complete Treatment</h2>
            </div>
            <button onclick="closeCompleteTreatmentModal()" class="complete-treatment-close">×</button>
        </div>
        <div class="complete-treatment-body">
            <p class="complete-treatment-text">Mark treatment as complete and move this patient to payment?</p>
            <div class="complete-treatment-highlight">
                <div class="complete-treatment-highlight-icon">🦷</div>
                <div>
                    <div class="complete-treatment-highlight-label">Patient Care Flow</div>
                    <div class="complete-treatment-highlight-value">Treatment finishes here, billing continues next.</div>
                </div>
            </div>
            <div class="complete-treatment-note">
                <span class="complete-treatment-note-dot"></span>
                The dashboard and payment list will refresh after confirmation.
            </div>
        </div>
        <input type="hidden" id="completeTreatmentQueueId">
        <div class="complete-treatment-actions">
            <button type="button" onclick="closeCompleteTreatmentModal()" class="complete-treatment-cancel">Keep Open</button>
            <button type="button" onclick="confirmCompleteTreatment()" class="complete-treatment-confirm">Yes, Complete</button>
        </div>
    </div>
</div>

<!-- Dashboard Confirm Modal -->
<div id="dashboardConfirmModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div class="modal" style="background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 460px; position: relative; z-index: 100000;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 id="dashboardConfirmTitle" style="font-size: 1.25rem; font-weight: 600; margin: 0;">Confirm Action</h2>
            <button onclick="closeDashboardConfirmModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">×</button>
        </div>
        <p id="dashboardConfirmMessage" style="color: #374151; margin: 0 0 20px;"></p>
        <div style="display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" onclick="closeDashboardConfirmModal()" style="padding: 10px 20px; background: #f3f4f6; color: #374151; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">Cancel</button>
            <button type="button" id="dashboardConfirmButton" style="padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Confirm</button>
        </div>
    </div>
</div>
<!-- Dashboard Declining Modal -->
<div id="dashboardDeclineModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div class="modal" style="background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 460px; position: relative; z-index: 100000;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 id="dashboardDeclineTitle" style="font-size: 1.25rem; font-weight: 600; margin: 0;">Decline Appointment</h2>
            <button onclick="closeDashboardDeclineModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">×</button>
        </div>
        <p id="dashboardDeclineMessage" style="color: #374151; margin: 0 0 16px;"></p>
        <label for="dashboardDeclineReason" style="display: block; font-size: 0.85rem; font-weight: 600; color: #374151; margin-bottom: 6px;">Reason (optional)</label>
        <textarea id="dashboardDeclineReason" rows="3" placeholder="Enter a reason for declining this request..." style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; resize: vertical; box-sizing: border-box; margin-bottom: 20px;"></textarea>
        <div style="display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" onclick="closeDashboardDeclineModal()" style="padding: 10px 20px; background: #f3f4f6; color: #374151; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">Cancel</button>
            <button type="button" id="dashboardDeclineButton" style="padding: 10px 20px; background: #dc2626; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Decline</button>
        </div>
    </div>
</div>

<!-- Dashboard Message Modal -->
<div id="dashboardMessageModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div class="modal" style="background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 460px; position: relative; z-index: 100000;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 id="dashboardMessageTitle" style="font-size: 1.25rem; font-weight: 600; margin: 0;">Notice</h2>
        </div>
        <p id="dashboardMessageText" style="color: #374151; margin: 0 0 20px;"></p>
        <div style="display: flex; justify-content: flex-end;">
            <button type="button" onclick="closeDashboardMessageModal()" style="padding: 10px 20px; background: #0ea5e9; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">Close</button>
        </div>
    </div>
</div>

<!-- Dashboard Call Modal -->
<div id="dashboardCallModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div class="modal" style="background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 360px; position: relative; z-index: 100000;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 style="font-size: 1.1rem; font-weight: 600; margin: 0;">📞 Patient Contact</h2>
            <button onclick="closeCallModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">×</button>
        </div>
        <div id="callModalBody" style="color: #374151; text-align: center; padding: 8px 0 4px;"></div>
    </div>
</div>

<!-- Inquiry Panel Backdrop -->
<div class="dashboard-inquiry-backdrop" id="dashboardInquiryBackdrop" onclick="toggleInquiryPanel()"></div>

<!-- Floating Inquiry Button -->
<button type="button" id="dashboardInquiryFab" class="dashboard-inquiry-fab" onclick="toggleInquiryPanel()" aria-label="Inquiries" title="Inquiries">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 2 11 13"></path>
        <path d="m22 2-7 20-4-9-9-4Z"></path>
    </svg>
    <span class="dashboard-inquiry-fab-badge" id="dashboardInquiryFabBadge" style="display:none;">0</span>
</button>

<!-- Inquiry Panel -->
<div class="dashboard-inquiry-panel" id="dashboardInquiryPanel">
    <div id="inquiryPanelListView" style="display:flex;flex-direction:column;flex:1;min-height:0;">
        <div class="dashboard-inquiry-panel-header">
            <h3>Inquiries</h3>
            <span class="dashboard-inquiry-panel-count" id="dashboardInquiryCount">0</span>
            <button type="button" class="dashboard-inquiry-panel-close" onclick="toggleInquiryPanel()" aria-label="Close">&times;</button>
        </div>
        <div class="dashboard-inquiry-panel-list" id="dashboardInquiryList">
            <div class="dashboard-inquiry-loading">Loading...</div>
        </div>
    </div>
    <div id="inquiryPanelChatView" style="display:none;flex-direction:column;flex:1;min-height:0;">
        <div class="chat-modal-header">
            <button type="button" class="chat-modal-back" onclick="showInquiryList()" aria-label="Back">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
            </button>
            <div class="chat-modal-avatar" id="inquiryChatAvatar" style="overflow:hidden;">JD</div>
            <div class="chat-modal-user-info">
                <div class="chat-modal-name" id="inquiryChatName"></div>
                <div class="chat-modal-email" id="inquiryChatContact"></div>
            </div>
            <div class="chat-modal-source" id="inquiryChatSource"></div>
            <button type="button" class="chat-info-button" onclick="openInquiryInfoPanel()" aria-label="Conversation information" title="Conversation information">
                <span aria-hidden="true">i</span>
            </button>
        </div>
        <div class="chat-modal-messages" id="inquiryChatMessages"></div>
        <div class="chat-channel-info" id="inquiryChatChannel"></div>
        <form id="inquiryChatReplyForm" class="chat-compose">
            <input type="hidden" id="inquiryChatReplyId">
            <div id="dashReplyAttachmentPreview" class="chat-attachment-preview" style="display:none;"></div>
            <div class="chat-send-row">
                <button type="button" class="chat-attach-btn" id="dashReplyAttachFile" title="Attach file"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg></button>
                <button type="button" class="chat-attach-btn" id="dashReplyAttachImage" title="Attach image"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg></button>
                <input type="file" id="dashReplyFileInput" style="display:none;" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,image/*">
                <textarea id="inquiryChatReplyMsg" placeholder="Type your reply..." rows="1"></textarea>
                <button type="submit" class="chat-send-btn" aria-label="Send">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="m22 2-7 20-4-9-9-4Z"/></svg>
                </button>
            </div>
        </form>
    </div>
    <div class="inquiry-info-panel" id="inquiryInfoPanel" aria-hidden="true">
        <div class="inquiry-info-header">
            <button type="button" class="chat-modal-back" onclick="closeInquiryInfoPanel()" aria-label="Back to conversation">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
            </button>
            <strong>Conversation info</strong>
        </div>
        <div class="inquiry-info-profile">
            <div class="inquiry-info-avatar" id="inquiryInfoAvatar"></div>
            <div class="inquiry-info-name" id="inquiryInfoName"></div>
        </div>
        <button type="button" class="inquiry-media-toggle" id="inquiryMediaToggle" onclick="toggleInquiryMedia()" aria-expanded="false">
            <span>Media &amp; Files</span>
            <svg class="inquiry-media-chevron" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div class="inquiry-media-content" id="inquiryMediaContent">
            <div class="inquiry-media-gallery" id="inquiryMediaGallery"></div>
        </div>
    </div>
</div>

<script>
const queueItems = <?php echo json_encode($queueItems); ?>;
let dashboardConfirmHandler = null;
let dashboardDeclineHandler = null;
let dashboardAjaxTimer = null;
let procedureTimer = null;
let procedureStartedAt = null;

function parseProcedureStart(value) {
    if (!value) return null;
    const normalized = String(value).trim().replace(' ', 'T');
    const parsed = new Date(normalized);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

function updateProcedureDuration() {
    const durationEl = document.getElementById('live-procedure-duration');
    if (!durationEl || !procedureStartedAt) return;
    const elapsedSeconds = Math.max(0, Math.floor((Date.now() - procedureStartedAt.getTime()) / 1000));
    const hours = String(Math.floor(elapsedSeconds / 3600)).padStart(2, '0');
    const minutes = String(Math.floor((elapsedSeconds % 3600) / 60)).padStart(2, '0');
    const seconds = String(elapsedSeconds % 60).padStart(2, '0');
    durationEl.textContent = hours + ':' + minutes + ':' + seconds;
}

function setProcedureTimer(startValue) {
    procedureStartedAt = parseProcedureStart(startValue);
    if (procedureTimer) clearInterval(procedureTimer);
    procedureTimer = null;
    updateProcedureDuration();
    if (procedureStartedAt) procedureTimer = setInterval(updateProcedureDuration, 1000);
}

function escapeDashboardHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function(ch) {
        const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'};
        return map[ch] || ch;
    });
}

function openLightbox(src) {
    let overlay = document.getElementById('imgLightbox');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'imgLightbox';
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:999999;display:flex;align-items:center;justify-content:center;cursor:pointer;';
        overlay.innerHTML = '<img id="lightboxImg" style="max-width:90vw;max-height:90vh;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,0.5);">';
        overlay.addEventListener('click', () => overlay.style.display = 'none');
        document.body.appendChild(overlay);
    }
    document.getElementById('lightboxImg').src = src;
    overlay.style.display = 'flex';
}

function renderDashboardQueue(items) {
    const container = document.getElementById('up-next-list');
    if (!container) return;
    if (!items.length) {
        container.innerHTML = '<div style="text-align: center; padding: 0; color: #6b7280;"><p>No patients waiting</p><a href="staff_new_admission.php" class="btn-primary" style="display: inline-block; margin-top: 12px; text-decoration: none;">Add New Patient</a></div>';
        document.getElementById('count-up-next').textContent = '0';
        return;
    }

    const html = items.slice(0, 5).map(item => `
        <div class="patient-item" id="next-${item.id}">
            <div class="patient-info">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="queue-avatar">#${escapeDashboardHtml(item.queue_number || item.id || '')}</div>
                    <div style="min-width:0;">
                        <div class="patient-name">${escapeDashboardHtml(item.patient_name || 'Unknown')}</div>
                        <div class="patient-details" style="margin-top:3px;">
                            <span class="status-badge waiting">Waiting</span>
                            <span class="patient-time">${escapeDashboardHtml(item.queue_time || '')}</span>
                        </div>
                        <div class="patient-treatment">${escapeDashboardHtml(item.treatment_type || '')}</div>
                    </div>
                </div>
            </div>
            <div class="patient-actions">
                <button class="action-btn text-btn" onclick="viewPatientDashboard(${Number(item.patient_id || 0)})">View</button>
                <button class="action-btn text-btn" onclick="moveToOnHoldDashboard(${Number(item.id || 0)})">Hold</button>
                <button class="action-btn text-btn queue-cancel-btn" onclick="cancelPatientDashboard(${Number(item.id || 0)})">Cancel</button>
            </div>
        </div>
    `).join('');

    container.innerHTML = html;
    document.getElementById('count-up-next').textContent = String(items.length);
}

function normalizeDashboardTime(value) {
    if (!value) return '';

    if (typeof value === 'string') {
        const trimmed = value.trim();
        if (!trimmed) return '';

        const timeMatch = trimmed.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/);
        if (timeMatch) {
            const hours = Number(timeMatch[1]);
            const minutes = Number(timeMatch[2]);
            const seconds = Number(timeMatch[3] || '0');
            const parsed = new Date(2000, 0, 1, hours, minutes, seconds);
            if (!Number.isNaN(parsed.getTime())) {
                return parsed.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
            }
        }

        const strictTime = new Date('2000-01-01T' + trimmed);
        if (!Number.isNaN(strictTime.getTime())) {
            return strictTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        }

        return trimmed;
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return '';
    return parsed.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}

function renderDashboardPending(items) {
    const container = document.getElementById('pending-approval-list');
    if (!container) return;
    if (!items.length) {
        container.innerHTML = '<div style="text-align: center; padding-top: 0; color: #6b7280;"><p>No pending approvals</p></div>';
        document.getElementById('count-pending-approval').textContent = '0';
        return;
    }

    const html = items.map(appt => {
        const date = appt.appointment_date ? new Date(appt.appointment_date + 'T00:00:00') : null;
        const dateLabel = date ? date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : '';
        const timeLabel = normalizeDashboardTime(appt.appointment_time);
        const combined = [dateLabel, timeLabel].filter(Boolean).join(' · ');
        return `
            <div class="patient-item" id="pending-appt-${appt.id}">
                <div class="patient-info">
                    <div class="patient-name">${escapeDashboardHtml(appt.patient_name || 'Unknown')}</div>
                    <div class="patient-details" style="margin-top:3px;">
                        <span class="status-badge pending">Pending</span>
                        <span class="patient-time">${escapeDashboardHtml(combined)}</span>
                    </div>
                    <div class="patient-treatment">${escapeDashboardHtml(appt.service_name || 'General')}</div>
                </div>
                <div class="patient-actions">
                    <button class="action-btn pa-approve" onclick="approveAppointment(${Number(appt.id || 0)})">✓</button>
                    <button class="action-btn pa-decline" onclick="declineAppointment(${Number(appt.id || 0)})">✕</button>
                    <button class="action-btn pa-call" title="View phone number" onclick="openCallModal('${escapeDashboardHtml(appt.patient_name || 'Unknown').replace(/'/g, '&#39;')}', '${escapeDashboardHtml(appt.phone || '').replace(/'/g, '&#39;')}')">
                        <img src="../../assets/icons/call_button.png" alt="Call">
                    </button>
                </div>
            </div>
        `;
    }).join('');

    container.innerHTML = html;
    document.getElementById('count-pending-approval').textContent = String(items.length);
}

function renderDashboardUpcoming(items) {
    const container = document.getElementById('upcoming-appt-list');
    if (!container) return;
    if (!items.length) {
        container.innerHTML = '<div style="text-align: center; padding: 0; color: #6b7280;"><p>No upcoming appointments</p></div>';
        document.getElementById('count-upcoming-appt').textContent = '0';
        return;
    }

    const html = items.map(appt => {
        const date = appt.appointment_date ? new Date(appt.appointment_date + 'T00:00:00') : null;
        const dateLabel = date ? date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : '';
        const timeLabel = normalizeDashboardTime(appt.appointment_time);
        const combined = [dateLabel, timeLabel].filter(Boolean).join(' · ');
        return `
            <div class="patient-item">
                <div class="patient-info">
                    <div class="patient-name">${escapeDashboardHtml(appt.patient_name || 'Unknown')}</div>
                    <div class="patient-details" style="margin-top:3px;">
                        <span class="status-badge scheduled">Scheduled</span>
                        <span class="patient-time">${escapeDashboardHtml(combined)}</span>
                    </div>
                    <div class="patient-treatment">${escapeDashboardHtml(appt.service_name || 'General')}</div>
                </div>
            </div>
        `;
    }).join('');

    container.innerHTML = html;
    document.getElementById('count-upcoming-appt').textContent = String(items.length);
}

// Today's Schedule renderer removed - feature disabled per request

function renderDashboardLive(data) {
    const patientName = document.getElementById('live-patient-name');
    const patientTime = document.getElementById('live-patient-time');
    const patientTreatment = document.getElementById('live-patient-treatment');
    const completeBtn = document.getElementById('completeTreatmentBtn');
    const live = data && data.live_queue ? data.live_queue : null;

    if (!live) {
        setProcedureTimer(null);
        if (patientName) patientName.textContent = 'No Patient';
        if (patientTime) patientTime.textContent = '--:--';
        if (patientTreatment) patientTreatment.textContent = 'Queue Empty';
        if (completeBtn) {
            completeBtn.disabled = true;
            completeBtn.style.background = '#9ca3af';
            completeBtn.innerHTML = '<span>✓</span> <span>No Patient in Chair</span>';
        }
        return;
    }

    setProcedureTimer(live.procedure_started_at);

    if (patientName) patientName.textContent = live.patient_name || 'No Patient';
    if (patientTime) patientTime.textContent = live.queue_time || '--:--';
    if (patientTreatment) patientTreatment.textContent = live.treatment_type || 'Queue Empty';
    if (completeBtn) {
        completeBtn.disabled = false;
        completeBtn.style.background = '';
        completeBtn.setAttribute('onclick', `completeCurrentPatient(${Number(live.id || 0)})`);
        completeBtn.innerHTML = '<span>✓</span> <span>Complete Treatment</span>';
    }
}

function renderDashboardCancelled(items) {
    const list = document.getElementById('cancelled-today-list');
    const count = document.getElementById('count-cancelled');
    if (!list) return;

    if (count) count.textContent = String(items.length);
    if (!items.length) {
        list.innerHTML = '<p style="text-align:center;color:#6b7280;padding:20px;">No cancelled patients today</p>';
        return;
    }

    list.innerHTML = items.map(function(item) {
        const cancelledDate = item.cancelled_at ? new Date(item.cancelled_at).toLocaleString('en-PH', {
            year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'
        }) : '';
        return '<div class="patient-item" id="cancelled-' + Number(item.id || 0) + '">' +
            '<div class="patient-info">' +
                '<div class="patient-name" style="text-decoration:line-through;color:#9ca3af;">' + escapeHtml(item.patient_name || 'Unknown') + '</div>' +
                '<div class="patient-details"><span class="status-badge cancelled">Cancelled</span>' +
                    (cancelledDate ? '<span style="font-size:0.78rem;color:#6b7280;">Cancelled: ' + escapeHtml(cancelledDate) + '</span>' : '') +
                '</div>' +
                '<div class="patient-treatment">' + escapeHtml(item.treatment_type || '') + '</div>' +
            '</div>' +
            '<div class="patient-actions">' +
                '<button class="action-btn icon view-btn" onclick="viewPatientDashboard(' + Number(item.patient_id || 0) + ')">👁️</button>' +
                '<button class="action-btn" style="background:#84cc16;color:white;padding:8px 12px;border-radius:6px;border:none;font-size:12px;cursor:pointer;" onclick="requeuePatientDashboard(' + Number(item.id || 0) + ')">Re-queue</button>' +
                '<button class="action-btn icon delete-btn" type="button" onclick="deleteCancelledPatient(' + Number(item.id || 0) + ')" title="Delete cancelled record">🗑️</button>' +
            '</div>' +
        '</div>';
    }).join('');
}

async function loadDashboardData() {
    try {
        const requestUrl = window.location.pathname + '?ajax=dashboard';
        const response = await fetch(requestUrl, {
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        });

        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            if (response.redirected || response.url.includes('/login.php')) {
                window.location.reload();
                return;
            }
            console.warn('Dashboard AJAX endpoint did not return JSON. Fetching page again.');
            return;
        }

        if (!response.ok) throw new Error('Dashboard refresh failed');
        const data = await response.json();
        renderDashboardLive(data);
        renderDashboardQueue(data.waiting || []);
        renderDashboardCancelled(data.cancelled || []);
        renderDashboardPending(data.pending_approval || []);
        renderDashboardUpcoming(data.upcoming_appointments || []);

        if (window.staffDashboardMiniCalendar && typeof window.staffDashboardMiniCalendar.refresh === 'function') {
            window.staffDashboardMiniCalendar.refresh();
        }
    } catch (error) {
        console.error('Dashboard AJAX error:', error);
    }
}

function startDashboardPolling() {
    if (dashboardAjaxTimer) return;
    loadDashboardData();
    dashboardAjaxTimer = setInterval(loadDashboardData, 5000);
}

// Portal Pattern: Move modals to body level to escape stacking context
// This ensures modals appear above sidebar and all other elements
(function() {
    const cancelledModal = document.getElementById('cancelledModal');
    const patientRecordModal = document.getElementById('patientRecordModal');
    const completeTreatmentModal = document.getElementById('completeTreatmentModal');
    const dashboardConfirmModal = document.getElementById('dashboardConfirmModal');
    const dashboardDeclineModal = document.getElementById('dashboardDeclineModal');
    const dashboardMessageModal = document.getElementById('dashboardMessageModal');
    const dashboardCallModal = document.getElementById('dashboardCallModal');
    const scCalModal = document.getElementById('scCalModal');
    
    if (cancelledModal) {
        document.body.appendChild(cancelledModal);
    }
    if (patientRecordModal) {
        document.body.appendChild(patientRecordModal);
    }
    if (completeTreatmentModal) {
        document.body.appendChild(completeTreatmentModal);
    }
    if (dashboardConfirmModal) {
        document.body.appendChild(dashboardConfirmModal);
    }
    if (dashboardDeclineModal) {
        document.body.appendChild(dashboardDeclineModal);
    }
    if (dashboardMessageModal) {
        document.body.appendChild(dashboardMessageModal);
    }
    if (dashboardCallModal) {
        document.body.appendChild(dashboardCallModal);
    }
    if (scCalModal) {
        document.body.appendChild(scCalModal);
    }
})();

document.addEventListener('DOMContentLoaded', function() {
    startDashboardPolling();
});

function openCallModal(name, phone) {
    const safePhone = phone || 'No phone number on file';
    document.getElementById('callModalBody').innerHTML =
        '<div style="font-size:0.95rem;color:#6b7280;margin-bottom:8px;">' + String(name).replace(/[&<>"']/g, function(c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]; }) + '</div>' +
        '<a href="tel:' + encodeURIComponent(safePhone) + '" style="font-size:1.35rem;font-weight:700;color:#2563eb;text-decoration:none;word-break:break-word;">' + String(safePhone).replace(/[&<>"']/g, function(c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]; }) + '</a>';
    document.getElementById('dashboardCallModal').style.display = 'flex';
}

function closeCallModal() {
    document.getElementById('dashboardCallModal').style.display = 'none';
}

function showDashboardMessage(message, title = 'Notice') {
    document.getElementById('dashboardMessageTitle').textContent = title;
    document.getElementById('dashboardMessageText').textContent = message;
    document.getElementById('dashboardMessageModal').style.display = 'flex';
}

function closeDashboardMessageModal() {
    document.getElementById('dashboardMessageModal').style.display = 'none';
}

function openDashboardConfirmModal(message, onConfirm, title = 'Confirm Action') {
    document.getElementById('dashboardConfirmTitle').textContent = title;
    document.getElementById('dashboardConfirmMessage').textContent = message;
    dashboardConfirmHandler = onConfirm;
    document.getElementById('dashboardConfirmModal').style.display = 'flex';
}

function closeDashboardConfirmModal() {
    document.getElementById('dashboardConfirmModal').style.display = 'none';
    dashboardConfirmHandler = null;
}

function openDashboardDeclineModal(message, onConfirm, title = 'Decline Appointment') {
    document.getElementById('dashboardDeclineTitle').textContent = title;
    document.getElementById('dashboardDeclineMessage').textContent = message;
    document.getElementById('dashboardDeclineReason').value = '';
    dashboardDeclineHandler = onConfirm;
    document.getElementById('dashboardDeclineModal').style.display = 'flex';
}

function closeDashboardDeclineModal() {
    document.getElementById('dashboardDeclineModal').style.display = 'none';
    dashboardDeclineHandler = null;
}

document.getElementById('dashboardConfirmButton').addEventListener('click', function() {
    if (typeof dashboardConfirmHandler === 'function') {
        const handler = dashboardConfirmHandler;
        closeDashboardConfirmModal();
        handler();
    }
});

document.getElementById('dashboardDeclineButton').addEventListener('click', function() {
    if (typeof dashboardDeclineHandler === 'function') {
        const handler = dashboardDeclineHandler;
        const reason = document.getElementById('dashboardDeclineReason').value.trim();
        closeDashboardDeclineModal();
        handler(reason);
    }
});

// Dashboard Queue Actions
function completeCurrentPatient(queueId) {
    document.getElementById('completeTreatmentQueueId').value = queueId;
    document.getElementById('completeTreatmentModal').style.display = 'flex';
}

function closeCompleteTreatmentModal() {
    document.getElementById('completeTreatmentModal').style.display = 'none';
    document.getElementById('completeTreatmentQueueId').value = '';
}

function confirmCompleteTreatment() {
    const queueId = document.getElementById('completeTreatmentQueueId').value;
    if (!queueId) return;

    fetch('../shared/queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'complete', queue_id: queueId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeCompleteTreatmentModal();
            loadDashboardData();
        } else {
            showDashboardMessage(data.message || 'Unable to complete treatment', 'Treatment Status');
        }
    })
    .catch(() => {
        showDashboardMessage('Unable to complete treatment', 'Treatment Status');
    });
}

function moveToOnHoldDashboard(queueId) {
    openDashboardConfirmModal('Put patient on hold?', function() {
        fetch('../shared/queue_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'on_hold', queue_id: queueId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadDashboardData();
            } else {
                showDashboardMessage(data.message || 'Unable to update queue status', 'Queue Status');
            }
        })
        .catch(() => {
            showDashboardMessage('Unable to update queue status', 'Queue Status');
        });
    });
}

function cancelPatientDashboard(queueId) {
    openDashboardConfirmModal('Cancel this patient?', function() {
        fetch('../shared/queue_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'cancel', queue_id: queueId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadDashboardData();
            } else {
                showDashboardMessage(data.message || 'Unable to cancel patient', 'Queue Status');
            }
        })
        .catch(() => {
            showDashboardMessage('Unable to cancel patient', 'Queue Status');
        });
    });
}

function requeuePatientDashboard(queueId) {
    fetch('../shared/queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'requeue', queue_id: queueId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const cancelledItem = document.getElementById('cancelled-' + queueId);
            if (cancelledItem) cancelledItem.remove();

            const cancelledTodayList = document.getElementById('cancelled-today-list');
            if (cancelledTodayList && !cancelledTodayList.querySelector('.patient-item')) {
                cancelledTodayList.innerHTML = '<p style="text-align:center;color:#6b7280;padding:20px;">No cancelled patients today</p>';
            }

            const cancelledCount = document.getElementById('count-cancelled');
            if (cancelledCount) {
                const currentCount = parseInt(cancelledCount.textContent, 10) || 0;
                cancelledCount.textContent = Math.max(0, currentCount - 1);
            }
            loadDashboardData();
        } else {
            showDashboardMessage(data.message || 'Unable to re-queue patient', 'Queue Status');
        }
    })
    .catch(() => {
        showDashboardMessage('Unable to re-queue patient', 'Queue Status');
    });
}

// Pending Approval Actions
function markDashboardArrived(id) {
    if (!id) return;

    fetch('../shared/mark_arrived.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ appt_id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showDashboardMessage(data.message || 'Patient marked as arrived', 'Arrival Status');
            loadDashboardData();
        } else {
            showDashboardMessage(data.message || 'Unable to mark as arrived', 'Arrival Status');
        }
    })
    .catch(() => {
        showDashboardMessage('Unable to mark patient as arrived right now.', 'Arrival Status');
    });
}

function approveAppointment(id) {
    openDashboardConfirmModal('Approve this appointment request?\n\nThe patient will be notified and the slot will be added to the calendar.', function() {
        fetch('../shared/approve_appointment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'approve', appointment_id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showDashboardMessage(data.message || 'Appointment approved', 'Approved');
                loadDashboardData();
            } else {
                showDashboardMessage(data.message || 'Error approving appointment', 'Approval Status');
            }
        })
        .catch(() => {
            showDashboardMessage('Something went wrong. Please try again.', 'Approval Status');
        });
    }, 'Approve Appointment');
}

function declineAppointment(id) {
    openDashboardDeclineModal('Decline this appointment request?\n\nThe patient will be notified.', function(reason) {
        fetch('../shared/approve_appointment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'decline', appointment_id: id, decline_reason: reason })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showDashboardMessage(data.message || 'Appointment request declined', 'Declined');
                loadDashboardData();
            } else {
                showDashboardMessage(data.message || 'Error declining appointment', 'Approval Status');
            }
        })
        .catch(() => {
            showDashboardMessage('Something went wrong. Please try again.', 'Approval Status');
        });
    }, 'Decline Appointment');
}

// Cancelled Modal Functions
function openCancelledModal() {
    document.getElementById('cancelledModal').style.display = 'flex';
}

function closeCancelledModal() {
    document.getElementById('cancelledModal').style.display = 'none';
}

function filterCancelled(filter) {
    const todayBtn = document.getElementById('cancelled-today-btn');
    const weekBtn = document.getElementById('cancelled-week-btn');
    const todayList = document.getElementById('cancelled-today-list');
    const weekList = document.getElementById('cancelled-week-list');
    
    if (filter === 'today') {
        todayBtn.style.background = '#2563eb';
        todayBtn.style.color = 'white';
        weekBtn.style.background = 'white';
        weekBtn.style.color = '#374151';
        todayList.style.display = 'block';
        weekList.style.display = 'none';
    } else {
        weekBtn.style.background = '#2563eb';
        weekBtn.style.color = 'white';
        todayBtn.style.background = 'white';
        todayBtn.style.color = '#374151';
        weekList.style.display = 'block';
        todayList.style.display = 'none';
    }
}

function deleteCancelledPatient(queueId) {
    if (!queueId) return;
    openDashboardConfirmModal('Delete this cancelled record? This action cannot be undone.', function() {
        fetch('../shared/queue_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', queue_id: queueId })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                showDashboardMessage(data.message || 'Unable to delete cancelled record', 'Delete Failed');
                return;
            }

            const item = document.getElementById('cancelled-' + queueId);
            if (item) item.remove();

            const todayList = document.getElementById('cancelled-today-list');
            if (todayList && !todayList.querySelector('.patient-item')) {
                todayList.innerHTML = '<p style="text-align:center;color:#6b7280;padding:20px;">No cancelled patients today</p>';
            }

            const countEl = document.getElementById('count-cancelled');
            if (countEl) {
                const currentCount = parseInt(countEl.textContent, 10) || 0;
                countEl.textContent = Math.max(0, currentCount - 1);
            }
        })
        .catch(() => {
            showDashboardMessage('Unable to delete cancelled record', 'Delete Failed');
        });
    }, 'Delete Cancelled Record');
}

// Patient Record Modal
function viewPatientDashboard(patientId) {
    document.getElementById('patientRecordModal').style.display = 'flex';
    document.getElementById('patientRecordContent').innerHTML = '<div style="text-align:center;padding:40px;color:#6b7280;">Loading patient records...</div>';

    fetch('../user-patient/patient_record_details.php?id=' + encodeURIComponent(patientId))
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const p = data.patient;
            const m = data.medical_history || {};
            const dentalHistory = data.dental_history || [];
            const completedPlans = data.completed_plans || [];
            const q = data.queue_item || {};

            const allergies = m.allergies || 'None';
            const medications = m.current_medications || 'None';
            const medicalConditions = m.medical_conditions || 'None';

            const patientFullName = `${p.first_name || ''} ${p.middle_name || ''} ${p.last_name || ''} ${p.suffix || ''}`.trim() || 'N/A';
            
            // Build dental history HTML
            let dentalHistoryHTML = '';
            if (dentalHistory.length > 0) {
                dentalHistoryHTML = '<div style="display: flex; flex-direction: column; gap: 12px;">';
                dentalHistory.forEach(treatment => {
                    const dentistName = treatment.dentist_first_name && treatment.dentist_last_name 
                        ? `Dr. ${treatment.dentist_first_name} ${treatment.dentist_last_name}` 
                        : 'Unknown Dentist';
                    const treatmentDate = treatment.completed_at 
                        ? new Date(treatment.completed_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })
                        : (treatment.treatment_date || 'N/A');
                    dentalHistoryHTML += `
                        <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                <div>
                                    <div style="font-weight: 600; color: #111827;">${treatment.treatment_type || 'Treatment'}</div>
                                    <div style="font-size: 0.85rem; color: #6b7280;">Teeth: ${getStaffTeethDisplayText(treatment.teeth_numbers || '')}</div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 0.85rem; color: #6b7280;">${treatmentDate}</div>
                                    <div style="font-size: 0.8rem; color: #059669; font-weight: 500;">${dentistName}</div>
                                </div>
                            </div>
                            ${treatment.procedure_notes ? `<div style="font-size: 0.85rem; color: #6b7280; border-top: 1px solid #f3f4f6; padding-top: 8px; margin-top: 8px;">${treatment.procedure_notes}</div>` : ''}
                        </div>
                    `;
                });
                dentalHistoryHTML += '</div>';
            } else if (completedPlans.length > 0) {
                dentalHistoryHTML = '<div style="display: flex; flex-direction: column; gap: 12px;">';
                completedPlans.forEach(plan => {
                    const planDate = new Date(plan.treatment_date).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
                    dentalHistoryHTML += `
                        <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                <div>
                                    <div style="font-weight: 600; color: #111827;">${plan.treatment_name || plan.treatment_type || 'Treatment Plan'}</div>
                                    <div style="font-size: 0.85rem; color: #6b7280;">Teeth: ${plan.teeth_numbers || 'N/A'}</div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 0.85rem; color: #6b7280;">${planDate}</div>
                                    <div style="font-size: 0.8rem; color: #059669; font-weight: 500;">Plan Completed</div>
                                </div>
                            </div>
                            ${plan.notes ? `<div style="font-size: 0.85rem; color: #6b7280; border-top: 1px solid #f3f4f6; padding-top: 8px; margin-top: 8px;">${plan.notes}</div>` : ''}
                        </div>
                    `;
                });
                dentalHistoryHTML += '</div>';
            } else {
                dentalHistoryHTML = '<p style="color: #6b7280; text-align: center; padding: 20px;">No previous treatment records found.</p>';
            }

            document.getElementById('patientRecordContent').innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <h3 style="font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 12px;">Personal Information</h3>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <div><span style="color: #6b7280; font-size: 0.875rem;">Full Name:</span> <span style="font-weight: 500;">${patientFullName}</span></div>
                            <div><span style="color: #6b7280; font-size: 0.875rem;">Age:</span> <span style="font-weight: 500;">${p.age || 'N/A'} years</span></div>
                            <div><span style="color: #6b7280; font-size: 0.875rem;">Phone:</span> <span style="font-weight: 500;">${p.phone || 'N/A'}</span></div>
                        </div>
                    </div>
                    <div>
                        <h3 style="font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 12px;">Service Requested</h3>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <div><span style="color: #6b7280; font-size: 0.875rem;">Treatment:</span> <span style="font-weight: 500;">${q.treatment_type || 'N/A'}</span></div>
                            <div><span style="color: #6b7280; font-size: 0.875rem;">Teeth:</span> <span style="font-weight: 500;">${getStaffTeethDisplayText(q.teeth_numbers || '')}</span></div>
                        </div>
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <h3 style="font-size: 0.875rem; font-weight: 600; color: #dc2626; margin-bottom: 12px;">⚠️ Medical History</h3>
                    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div>
                                <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 4px;">Allergies</div>
                                <div style="font-weight: 500; ${allergies === 'Yes' ? 'color: #dc2626;' : ''}">${allergies}</div>
                            </div>
                            <div>
                                <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 4px;">Conditions</div>
                                <div style="font-weight: 500;">${medicalConditions}</div>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 4px;">Current Medications</div>
                                <div style="font-weight: 500;">${medications}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <h3 style="font-size: 0.875rem; font-weight: 600; color: #059669; margin-bottom: 12px;">📜 Dental History (Previous Treatments)</h3>
                    <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 16px;">
                        ${dentalHistoryHTML}
                    </div>
                </div>
            `;
            
        } else {
            showDashboardMessage(data.message || 'Unable to load patient record', 'Patient Record');
        }
    })
    .catch(() => {
        showDashboardMessage('Unable to load patient record', 'Patient Record');
    });
}

function closePatientRecordModal() {
    document.getElementById('patientRecordModal').style.display = 'none';
}

// Close modals on outside click
window.onclick = function(event) {
    const cancelledModal = document.getElementById('cancelledModal');
    const patientModal = document.getElementById('patientRecordModal');
    const completeTreatmentModal = document.getElementById('completeTreatmentModal');
    const dashboardConfirmModal = document.getElementById('dashboardConfirmModal');
    const closedashboardDeclineModal = document.getElementById('dashboardDeclineModal');
    const dashboardMessageModal = document.getElementById('dashboardMessageModal');
    const dashboardCallModal = document.getElementById('dashboardCallModal');
    const scCalModal = document.getElementById('scCalModal');
    if (event.target == cancelledModal) closeCancelledModal();
    if (event.target == patientModal) closePatientRecordModal();
    if (event.target == completeTreatmentModal) closeCompleteTreatmentModal();
    if (event.target == dashboardConfirmModal) closeDashboardConfirmModal();
    if (event.target == closeDashboardDeclineModal) closeDashboardDeclineModal();
    if (event.target == dashboardMessageModal) closeDashboardMessageModal();
    if (event.target == dashboardCallModal) closeCallModal();
    if (event.target == scCalModal) scCalClose();
}

// Mini Calendar - Scheduled Patients Modal
var staffCalAppointments = <?php echo json_encode($scCalByDate); ?>;

function scCalEscapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function scCalOpenDay(dateStr) {
    var list = staffCalAppointments[dateStr] || [];
    var title = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    document.getElementById('scCalModalTitle').textContent = title;

    var body = document.getElementById('scCalModalBody');
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
                + '<td style="padding:12px; color:#374151;">' + scCalEscapeHtml(name) + '</td>'
                + '<td style="padding:12px; color:#374151;">' + scCalEscapeHtml(a.service_name || 'General') + '</td>'
                + '<td style="padding:12px;"><span style="display:inline-block; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; ' + badge + '">' + scCalEscapeHtml(status) + '</span></td>'
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

    document.getElementById('scCalModal').style.display = 'flex';
    document.body.classList.add('modal-open');
}

function scCalClose() {
    document.getElementById('scCalModal').style.display = 'none';
    document.body.classList.remove('modal-open');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCallModal();
        closeDashboardConfirmModal();
        closeDashboardDeclineModal();
        scCalClose();
    }
});

// Convert teeth numbers to arch labels (matches staff terminology)
function getStaffTeethDisplayText(teethString) {
    if (!teethString || teethString.trim() === '') {
        return 'N/A';
    }
    
    const teeth = teethString.split(',').map(t => parseInt(t.trim())).filter(t => !isNaN(t));
    if (teeth.length === 0) {
        return 'N/A';
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
    
    if (parts.length === 0) {
        return teeth.sort((a, b) => a - b).join(', ');
    }
    
    return parts.join(' + ');
}

// ===== Dashboard Inquiry Panel =====
let dashboardInquiries = [];
let dashboardInquiryMap = {};
let dashboardActiveInquiryId = 0;
let dashboardConversationPollInFlight = false;
let dashboardTypingState = 0;
let dashboardTypingSentAt = 0;
let dashboardTypingTimer = null;
let dashboardConversationReplies = [];

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;',
        '"': '&quot;', "'": '&#39;'
    }[character]));
}

function getInitials(name) {
    const parts = (name || 'P').trim().split(/\s+/);
    const first = parts[0] || '';
    const last = parts.length > 1 ? parts[parts.length - 1] : '';
    return ((first[0] || '') + (last[0] || '')).toUpperCase() || 'P';
}

function toggleInquiryPanel() {
    const panel = document.getElementById('dashboardInquiryPanel');
    const backdrop = document.getElementById('dashboardInquiryBackdrop');
    const isOpen = panel.classList.toggle('open');
    backdrop.classList.toggle('open', isOpen);
    if (isOpen && !dashboardInquiries.length) {
        fetchDashboardInquiries();
    } else if (!isOpen) {
        sendDashboardTypingState(0);
        dashboardActiveInquiryId = 0;
        closeInquiryInfoPanel();
    }
}

function showInquiryList() {
    sendDashboardTypingState(0);
    dashboardActiveInquiryId = 0;
    closeInquiryInfoPanel();
    document.getElementById('inquiryPanelListView').style.display = 'flex';
    document.getElementById('inquiryPanelChatView').style.display = 'none';
}

async function fetchDashboardInquiries() {
    try {
        const url = '../shared/inquiry_actions.php?action=get_inquiries&limit=30';
        const response = await fetch(url);
        const data = await response.json();
        if (!data.success) return;
        dashboardInquiries = data.inquiries;
        dashboardInquiryMap = Object.fromEntries(dashboardInquiries.map((item) => [String(item.id), item]));
        renderDashboardInquiries();
        updateDashboardInquiryBadge(data.unread_count);
    } catch (_err) {}
}

function renderDashboardInquiries() {
    const list = document.getElementById('dashboardInquiryList');
    if (!dashboardInquiries.length) {
        list.innerHTML = '<div class="dashboard-inquiry-empty">No inquiries yet</div>';
        document.getElementById('dashboardInquiryCount').textContent = '0';
        return;
    }
    document.getElementById('dashboardInquiryCount').textContent = dashboardInquiries.length;
    list.innerHTML = dashboardInquiries.map((inquiry) => {
        const fullName = [inquiry.first_name, inquiry.middle_name, inquiry.last_name].filter(Boolean).join(' ') || 'Unknown';
        const hasReply = Number(inquiry.latest_reply_id || 0) > 0;
        const lastReply = inquiry.latest_reply_message || '';
        const hasAttachment = !!inquiry.latest_reply_attachment;
        const lastSender = inquiry.latest_reply_sender_type || '';
        const latestContent = lastReply || (hasAttachment ? 'Sent an attachment' : '');
        const previewSource = hasReply
            ? (lastSender === 'staff' ? 'you: ' + latestContent : latestContent)
            : (inquiry.concern || inquiry.inquiry_message || 'No message');
        const preview = previewSource.slice(0, 80);
        const status = inquiry.status || 'Unread';
        const isUnread = status === 'Unread';
        const initials = getInitials(fullName);
        return `
            <div class="dashboard-inquiry-item${isUnread ? ' is-unread' : ''}">
                <div class="dashboard-inquiry-item-avatar" style="overflow:hidden;">${inquiry.patient_profile_picture ? '<img src="../../' + escapeHtml(inquiry.patient_profile_picture) + '" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">' : escapeHtml(initials)}</div>
                <div class="dashboard-inquiry-item-body">
                    <div class="dashboard-inquiry-item-top">
                        <span class="dashboard-inquiry-item-name">${escapeHtml(fullName)}</span>
                        <span class="dashboard-inquiry-item-status ${escapeHtml(status)}">${escapeHtml(status)}</span>
                    </div>
                    <div class="dashboard-inquiry-item-preview">${escapeHtml(preview)}</div>
                    <button class="dashboard-inquiry-item-reply" onclick="openDashboardInquiryChat(${Number(inquiry.id)})">Reply</button>
                </div>
            </div>
        `;
    }).join('');
}

function updateDashboardInquiryFromReply(inquiryId, reply, status) {
    const key = String(inquiryId);
    const entry = dashboardInquiryMap[key];
    if (!entry || !reply) return;

    entry.latest_reply_id = Number(reply.id || entry.latest_reply_id || 0);
    entry.latest_reply_message = reply.message || '';
    entry.latest_reply_attachment = reply.attachment || null;
    entry.latest_reply_sender_type = reply.sender_type || '';
    entry.latest_reply_created_at = reply.created_at || new Date().toISOString();
    if (status) entry.status = status;

    const index = dashboardInquiries.findIndex((item) => String(item.id) === key);
    if (index > 0) {
        dashboardInquiries.splice(index, 1);
        dashboardInquiries.unshift(entry);
    }
    renderDashboardInquiries();
    updateDashboardInquiryBadge(dashboardInquiries.filter((item) => (item.status || 'Unread') === 'Unread').length);
}

function updateDashboardInquiryBadge(count) {
    const badge = document.getElementById('dashboardInquiryFabBadge');
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.style.display = 'inline-flex';
    } else {
        badge.style.display = 'none';
    }
}

function mergeDashboardConversationReplies(replies) {
    const replyMap = new Map(dashboardConversationReplies.map((reply) => [String(reply.id), reply]));
    (replies || []).forEach((reply) => replyMap.set(String(reply.id), reply));
    dashboardConversationReplies = Array.from(replyMap.values())
        .sort((a, b) => Number(a.id || 0) - Number(b.id || 0));
}

function renderInquiryMediaGallery() {
    const gallery = document.getElementById('inquiryMediaGallery');
    const images = dashboardConversationReplies.filter((reply) =>
        reply.attachment && /\.(jpe?g|png|gif|webp)$/i.test(reply.attachment)
    );
    if (!images.length) {
        gallery.innerHTML = '<div class="inquiry-media-empty">No photos have been shared in this conversation.</div>';
        return;
    }
    gallery.innerHTML = images.map((reply) => {
        const path = '../' + reply.attachment;
        const sender = reply.sender_type === 'staff' ? 'Staff' : 'Patient';
        return '<button type="button" class="inquiry-media-item" data-media-src="' + escapeHtml(path) + '" aria-label="View image sent by ' + sender + '">'
            + '<img src="' + escapeHtml(path) + '" alt="Image sent by ' + sender + '" loading="lazy">'
            + '</button>';
    }).join('');
    gallery.querySelectorAll('[data-media-src]').forEach((item) => {
        item.addEventListener('click', () => openLightbox(item.dataset.mediaSrc));
    });
}

async function openInquiryInfoPanel() {
    if (!dashboardActiveInquiryId) return;
    const panel = document.getElementById('inquiryInfoPanel');
    const sourceAvatar = document.getElementById('inquiryChatAvatar');
    const targetAvatar = document.getElementById('inquiryInfoAvatar');
    targetAvatar.innerHTML = sourceAvatar.innerHTML;
    if (!sourceAvatar.querySelector('img')) targetAvatar.textContent = sourceAvatar.textContent || 'P';
    document.getElementById('inquiryInfoName').textContent = document.getElementById('inquiryChatName').textContent;
    renderInquiryMediaGallery();
    panel.classList.add('open');
    panel.setAttribute('aria-hidden', 'false');

    const formData = new FormData();
    formData.append('action', 'get_conversation');
    formData.append('id', String(dashboardActiveInquiryId));
    try {
        const response = await fetch('../shared/inquiry_actions.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (response.ok && data.success) {
            mergeDashboardConversationReplies(data.replies || []);
            renderInquiryMediaGallery();
        }
    } catch (_error) {}
}

function closeInquiryInfoPanel() {
    const panel = document.getElementById('inquiryInfoPanel');
    if (!panel) return;
    panel.classList.remove('open');
    panel.setAttribute('aria-hidden', 'true');
}

function toggleInquiryMedia() {
    const toggle = document.getElementById('inquiryMediaToggle');
    const content = document.getElementById('inquiryMediaContent');
    const isOpen = content.classList.toggle('open');
    toggle.classList.toggle('open', isOpen);
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

function getDashboardLastReplyId() {
    const ids = Array.from(document.querySelectorAll('#inquiryChatMessages [data-reply-id]'))
        .map((element) => Number(element.dataset.replyId))
        .filter((id) => Number.isFinite(id) && id > 0);
    return ids.length ? Math.max(...ids) : 0;
}

function isDashboardChatNearBottom() {
    const container = document.getElementById('inquiryChatMessages');
    return container.scrollHeight - container.scrollTop - container.clientHeight <= 120;
}

function scrollDashboardChatToBottom(force = false) {
    const container = document.getElementById('inquiryChatMessages');
    if (!force && !isDashboardChatNearBottom()) return;
    const scroll = () => { container.scrollTop = container.scrollHeight; };
    scroll();
    requestAnimationFrame(scroll);
    setTimeout(scroll, 100);
    if (force) {
        container.querySelectorAll('img').forEach((image) => {
            if (!image.complete) image.addEventListener('load', scroll, { once: true });
        });
    }
}

function appendDashboardInquiryReply(reply) {
    const container = document.getElementById('inquiryChatMessages');
    const replyId = Number(reply.id || 0);
    if (replyId > 0 && container.querySelector(`[data-reply-id="${replyId}"]`)) return;
    const shouldAutoScroll = isDashboardChatNearBottom();

    const isPatient = reply.sender_type !== 'staff';
    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble ' + (isPatient ? 'received' : 'sent');
    if (!reply.message && reply.attachment) bubble.classList.add('attachment-only');
    if (replyId > 0) bubble.dataset.replyId = String(replyId);

    const label = isPatient ? (document.getElementById('inquiryChatName').textContent || 'Patient') : 'Staff';
    const messageHtml = reply.message ? escapeHtml(reply.message).replace(/\n/g, '<br>') : '';
    let attachmentHtml = '';
    if (reply.attachment) {
        const isImage = /\.(jpe?g|png|gif|webp)$/i.test(reply.attachment);
        attachmentHtml = isImage
            ? '<img src="../' + escapeHtml(reply.attachment) + '" class="chat-att-img" alt="Attachment" onclick="openLightbox(this.src)" style="cursor:pointer;">'
            : '<a href="../' + escapeHtml(reply.attachment) + '" target="_blank" class="chat-att-file"><i class="fas fa-paperclip"></i> ' + escapeHtml(reply.attachment.split('/').pop()) + '</a>';
    }
    const timestamp = reply.created_at
        ? new Date(String(reply.created_at).replace(' ', 'T')).toLocaleString()
        : new Date().toLocaleString();
    bubble.innerHTML = '<div style="font-size:11px;font-weight:600;color:' + (isPatient ? '#64748b' : 'rgba(255,255,255,0.7)') + ';margin-bottom:3px;">' + escapeHtml(label) + '</div><div>' + messageHtml + attachmentHtml + '</div><div class="chat-meta">' + escapeHtml(timestamp) + '</div>';

    const typingIndicator = document.getElementById('patientInquiryTypingIndicator');
    container.insertBefore(bubble, typingIndicator || null);
    if (shouldAutoScroll) scrollDashboardChatToBottom(true);
}

function showDashboardPatientTyping(visible) {
    const container = document.getElementById('inquiryChatMessages');
    const shouldAutoScroll = isDashboardChatNearBottom();
    let indicator = document.getElementById('patientInquiryTypingIndicator');
    if (!indicator && visible) {
        indicator = document.createElement('div');
        indicator.id = 'patientInquiryTypingIndicator';
        indicator.className = 'inquiry-typing-row';
        const headerAvatar = document.getElementById('inquiryChatAvatar');
        const typingAvatar = document.createElement('div');
        typingAvatar.className = 'inquiry-typing-avatar';
        if (headerAvatar.querySelector('img')) {
            typingAvatar.innerHTML = headerAvatar.innerHTML;
        } else {
            typingAvatar.textContent = headerAvatar.textContent || 'P';
        }
        const typingBubble = document.createElement('div');
        typingBubble.className = 'inquiry-typing-indicator';
        typingBubble.innerHTML = '<span class="inquiry-typing-dots" aria-label="Patient is typing"><span></span><span></span><span></span></span>';
        indicator.appendChild(typingAvatar);
        indicator.appendChild(typingBubble);
        container.appendChild(indicator);
    }
    if (indicator) {
        indicator.style.display = visible ? 'flex' : 'none';
        if (visible && shouldAutoScroll) scrollDashboardChatToBottom(true);
    }
}

async function sendDashboardTypingState(isTyping) {
    if (!dashboardActiveInquiryId) return;
    const now = Date.now();
    if (dashboardTypingState === isTyping && (!isTyping || now - dashboardTypingSentAt < 2500)) return;
    dashboardTypingState = isTyping;
    dashboardTypingSentAt = now;
    const formData = new FormData();
    formData.append('action', 'set_typing');
    formData.append('id', String(dashboardActiveInquiryId));
    formData.append('is_typing', isTyping ? '1' : '0');
    try {
        await fetch('../shared/inquiry_actions.php', { method: 'POST', body: formData });
    } catch (_error) {}
}

async function pollDashboardInquiryConversation() {
    const inquiryId = dashboardActiveInquiryId;
    if (!inquiryId || dashboardConversationPollInFlight || document.hidden) return;
    dashboardConversationPollInFlight = true;
    const formData = new FormData();
    formData.append('action', 'conversation_updates');
    formData.append('id', String(inquiryId));
    formData.append('last_reply_id', String(getDashboardLastReplyId()));
    try {
        const response = await fetch('../shared/inquiry_actions.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (!response.ok || !data.success || inquiryId !== dashboardActiveInquiryId) return;
        mergeDashboardConversationReplies(data.replies || []);
        (data.replies || []).forEach(appendDashboardInquiryReply);
        if (data.replies && data.replies.length) {
            const latestReply = data.replies[data.replies.length - 1];
            updateDashboardInquiryFromReply(
                inquiryId,
                latestReply,
                latestReply.sender_type === 'staff' ? 'Replied' : 'Read'
            );
        }
        showDashboardPatientTyping(!!data.other_is_typing);
    } catch (_error) {
        // Keep the current UI and retry on the next polling cycle.
    } finally {
        dashboardConversationPollInFlight = false;
    }
}

async function openDashboardInquiryChat(id) {
    const inquiry = dashboardInquiryMap[String(id)];
    if (!inquiry) return;
    closeInquiryInfoPanel();
    dashboardConversationReplies = [];

    const fullName = [inquiry.first_name, inquiry.middle_name, inquiry.last_name].filter(Boolean).join(' ') || 'Unknown Patient';
    const contactParts = [inquiry.email, inquiry.phone || inquiry.contact_info].filter(Boolean);
    document.getElementById('inquiryChatName').textContent = fullName;
    document.getElementById('inquiryChatContact').textContent = contactParts.join(' · ') || 'No contact';
    document.getElementById('inquiryChatSource').textContent = inquiry.source || 'Website';
    const avatarEl = document.getElementById('inquiryChatAvatar');
    if (inquiry.patient_profile_picture) {
        avatarEl.innerHTML = '<img src="../../' + escapeHtml(inquiry.patient_profile_picture) + '" alt="" style="width:44px;height:44px;border-radius:50%;object-fit:cover;">';
    } else {
        avatarEl.textContent = getInitials(fullName);
        avatarEl.style.background = 'linear-gradient(135deg,#2563eb,#1d4ed8)';
    }
    document.getElementById('inquiryChatReplyId').value = inquiry.id;

    const concernText = inquiry.concern || inquiry.inquiry_message || 'No concern provided.';
    const container = document.getElementById('inquiryChatMessages');
    container.innerHTML = '';

    const originalBubble = document.createElement('div');
    originalBubble.className = 'chat-bubble received';
    const createdDate = inquiry.created_at ? new Date(String(inquiry.created_at).replace(' ', 'T')).toLocaleString() : '';
    originalBubble.innerHTML = '<div style="font-size:11px;font-weight:600;color:#64748b;margin-bottom:3px;">' + escapeHtml(fullName) + '</div><div>' + escapeHtml(concernText).replace(/\n/g, '<br>') + '</div><div class="chat-meta">' + escapeHtml(createdDate) + '</div>';
    container.appendChild(originalBubble);

    try {
        const formData = new FormData();
        formData.append('action', 'get_conversation');
        formData.append('id', id);
        const response = await fetch('../shared/inquiry_actions.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.success && data.replies) {
            mergeDashboardConversationReplies(data.replies);
            data.replies.forEach((reply) => {
                const bubble = document.createElement('div');
                const isNotStaff = reply.sender_type !== 'staff';
                bubble.className = 'chat-bubble ' + (isNotStaff ? 'received' : 'sent');
                if (!reply.message && reply.attachment) bubble.classList.add('attachment-only');
                if (reply.id) bubble.dataset.replyId = String(reply.id);
                const label = isNotStaff ? fullName : 'Staff';
                const msgText = reply.message ? escapeHtml(reply.message).replace(/\n/g, '<br>') : '';
                let attHtml = '';
                if (reply.attachment) {
                    const isImg = /\.(jpe?g|png|gif|webp)$/i.test(reply.attachment);
                    if (isImg) {
                        attHtml = '<img src="../' + escapeHtml(reply.attachment) + '" class="chat-att-img" alt="Attachment" onclick="openLightbox(this.src)" style="cursor:pointer;">';
                    } else {
                        attHtml = '<a href="../' + escapeHtml(reply.attachment) + '" target="_blank" class="chat-att-file"><i class="fas fa-paperclip"></i> ' + escapeHtml(reply.attachment.split('/').pop()) + '</a>';
                    }
                }
                bubble.innerHTML = '<div style="font-size:11px;font-weight:600;color:' + (isNotStaff ? '#64748b' : 'rgba(255,255,255,0.7)') + ';margin-bottom:3px;">' + escapeHtml(label) + '</div><div>' + msgText + attHtml + '</div><div class="chat-meta">' + escapeHtml(reply.created_at ? new Date(String(reply.created_at).replace(' ', 'T')).toLocaleString() : '') + '</div>';
                container.appendChild(bubble);
            });
        }
    } catch (_err) {
        const replies = parseDashboardStaffReplies(inquiry.notes);
        replies.forEach((reply) => {
            const bubble = document.createElement('div');
            bubble.className = 'chat-bubble sent';
            bubble.innerHTML = '<div style="font-size:11px;font-weight:600;color:rgba(255,255,255,0.7);margin-bottom:3px;">Staff</div><div>' + escapeHtml(reply.message).replace(/\n/g, '<br>') + '</div><div class="chat-meta">' + escapeHtml(reply.timestamp) + '</div>';
            container.appendChild(bubble);
        });
    }

    const channelInfo = document.getElementById('inquiryChatChannel');
    if ((inquiry.source || '') === 'Patient Portal' && inquiry.patient_id) {
        channelInfo.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Reply will appear in patient\'s account messages';
    } else {
        channelInfo.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M22 12v1a5 5 0 0 1-5 5h-1"/></svg> Reply will be sent via email with a conversation link to ' + escapeHtml(inquiry.email || 'the sender');
    }

    document.getElementById('inquiryPanelListView').style.display = 'none';
    document.getElementById('inquiryPanelChatView').style.display = 'flex';
    dashboardActiveInquiryId = Number(inquiry.id);
    dashboardTypingState = 0;
    scrollDashboardChatToBottom(true);

    if ((inquiry.status || 'Unread') === 'Unread') {
        inquiry.status = 'Read';
        fetch('../shared/inquiry_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mark_read&id=' + id
        });
        renderDashboardInquiries();
        updateDashboardInquiryBadge(dashboardInquiries.filter((item) => (item.status || 'Unread') === 'Unread').length);
    }

    document.getElementById('inquiryChatReplyMsg').value = '';
    document.getElementById('inquiryChatReplyMsg').style.height = 'auto';
    setTimeout(() => document.getElementById('inquiryChatReplyMsg').focus(), 200);
}

function parseDashboardStaffReplies(notes) {
    if (!notes) return [];
    const replies = [];
    const lines = notes.split('\n');
    let current = null;
    for (const line of lines) {
        const staffMatch = line.match(/^\[Staff replied on (.+?)\]/);
        if (staffMatch) {
            if (current) replies.push(current);
            current = { timestamp: staffMatch[1], message: '', type: 'sent' };
        } else if (current) {
            current.message += (current.message ? '\n' : '') + line;
        }
    }
    if (current) replies.push(current);
    return replies;
}

document.getElementById('inquiryChatReplyForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const id = document.getElementById('inquiryChatReplyId').value;
    const message = document.getElementById('inquiryChatReplyMsg').value.trim();
    const dashFileInput = document.getElementById('dashReplyFileInput');
    const selectedFile = dashFileInput.files[0] || null;
    if (!id || (!message && !selectedFile)) return;

    const btn = event.currentTarget.querySelector('button[type="submit"]');
    btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('action', 'staff_reply');
        formData.append('id', id);
        formData.append('message', message || '');
        if (selectedFile) {
            formData.append('attachment', selectedFile);
        }
        const response = await fetch('../shared/inquiry_actions.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (!data.success) {
            btn.disabled = false;
            return;
        }
        const now = new Date();
        const ts = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-' + String(now.getDate()).padStart(2,'0') + ' ' + String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0') + ':' + String(now.getSeconds()).padStart(2,'0');

        const sentReply = {
            id: data.reply_id,
            sender_type: 'staff',
            message,
            attachment: data.attachment || null,
            created_at: data.created_at || ts
        };
        mergeDashboardConversationReplies([sentReply]);
        appendDashboardInquiryReply(sentReply);

        const entry = dashboardInquiryMap[String(id)];
        if (entry) {
            entry.notes = (entry.notes || '') + '\n[Staff replied on ' + ts + ']\n' + message;
        }
        updateDashboardInquiryFromReply(id, sentReply, 'Replied');

        document.getElementById('inquiryChatReplyMsg').value = '';
        document.getElementById('inquiryChatReplyMsg').style.height = 'auto';
        dashFileInput.value = '';
        clearDashReplyAttachment();
        sendDashboardTypingState(0);
        btn.disabled = false;
    } catch (_err) {
        btn.disabled = false;
    }
});

const inquiryChatTextarea = document.getElementById('inquiryChatReplyMsg');
if (inquiryChatTextarea) {
    inquiryChatTextarea.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 100) + 'px';
        const hasText = this.value.trim().length > 0;
        if (!hasText) {
            clearTimeout(dashboardTypingTimer);
            sendDashboardTypingState(0);
            return;
        }
        sendDashboardTypingState(1);
        clearTimeout(dashboardTypingTimer);
        dashboardTypingTimer = setTimeout(() => sendDashboardTypingState(0), 3000);
    });
    inquiryChatTextarea.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            document.getElementById('inquiryChatReplyForm').requestSubmit();
        }
    });
    inquiryChatTextarea.addEventListener('blur', function () {
        clearTimeout(dashboardTypingTimer);
        sendDashboardTypingState(0);
    });
}

setInterval(pollDashboardInquiryConversation, 2000);
document.addEventListener('visibilitychange', function () {
    if (!document.hidden) pollDashboardInquiryConversation();
});
window.addEventListener('beforeunload', function () {
    sendDashboardTypingState(0);
});

document.getElementById('dashReplyAttachFile').addEventListener('click', () => {
    const inp = document.getElementById('dashReplyFileInput');
    inp.accept = '.pdf,.doc,.docx,.xls,.xlsx,.txt,image/*';
    inp.click();
});
document.getElementById('dashReplyAttachImage').addEventListener('click', () => {
    const inp = document.getElementById('dashReplyFileInput');
    inp.accept = 'image/*';
    inp.click();
});
document.getElementById('dashReplyFileInput').addEventListener('change', function() { showDashReplyAttachmentPreview(this.files[0]); });

function showDashReplyAttachmentPreview(file) {
    if (!file) return;
    const preview = document.getElementById('dashReplyAttachmentPreview');
    const isImage = file.type.startsWith('image/');
    preview.style.display = 'block';
    preview.innerHTML = '<div class="chat-attachment-preview-item">'
        + (isImage ? '<img src="' + URL.createObjectURL(file) + '" alt="Preview">' : '<i class="fas fa-file" style="font-size:20px;"></i>')
        + '<span class="att-name">' + escapeDashboardHtml(file.name) + '</span>'
        + '<button type="button" class="att-remove" onclick="clearDashReplyAttachment()">&times;</button>'
        + '</div>';
}

function clearDashReplyAttachment() {
    document.getElementById('dashReplyFileInput').value = '';
    const preview = document.getElementById('dashReplyAttachmentPreview');
    preview.style.display = 'none';
    preview.innerHTML = '';
}

// Poll for unread count and reorder list
let dashboardInquiryPollTimer = null;

async function pollDashboardInquiryBadge() {
    try {
        const response = await fetch('../shared/inquiry_actions.php?action=get_inquiries&limit=1');
        const data = await response.json();
        if (data.success) {
            updateDashboardInquiryBadge(data.unread_count);
        }
    } catch (_err) {}
}

async function pollDashboardInquiryList() {
    const panel = document.getElementById('dashboardInquiryPanel');
    if (!panel.classList.contains('open')) return;
    if (!dashboardInquiries.length) {
        fetchDashboardInquiries();
        return;
    }
    try {
        const response = await fetch('../shared/inquiry_actions.php?action=get_inquiries&limit=30');
        const data = await response.json();
        if (!data.success || !data.inquiries) return;
        const oldMap = dashboardInquiryMap;
        let changed = false;
        for (const item of data.inquiries) {
            const old = oldMap[String(item.id)];
            if (!old || old.status !== item.status || old.latest_reply_created_at !== item.latest_reply_created_at) {
                changed = true;
                break;
            }
        }
        if (changed) {
            dashboardInquiries = data.inquiries;
            dashboardInquiryMap = Object.fromEntries(dashboardInquiries.map((item) => [String(item.id), item]));
            renderDashboardInquiries();
            updateDashboardInquiryBadge(data.unread_count);
        }
    } catch (_err) {}
}

setInterval(pollDashboardInquiryBadge, 5000);
pollDashboardInquiryBadge();

if (dashboardInquiryPollTimer) clearInterval(dashboardInquiryPollTimer);
dashboardInquiryPollTimer = setInterval(pollDashboardInquiryList, 3000);

// Queue count auto-polling for dashboard
var sdQueueCountHash = '';

function pollDashboardQueueCounts() {
    fetch('../shared/queue_poll.php')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) return;
            if (data.hash !== sdQueueCountHash) {
                sdQueueCountHash = data.hash;
                var c = data.counts;
                var el = document.getElementById('count-waiting');
                if (el) el.textContent = c.waiting;
                el = document.getElementById('count-up-next');
                if (el) el.textContent = c.waiting;
                el = document.getElementById('count-completed');
                if (el) el.textContent = c.completed;
                el = document.getElementById('count-skipped');
                if (el) el.textContent = c.on_hold;
            }
        })
        .catch(function(e) { console.error('Dashboard queue poll error:', e); });
}

setInterval(pollDashboardQueueCounts, 10000);
pollDashboardQueueCounts();

// Portal fixed elements to body to avoid ancestor transform/scroll issues
(function() {
    const fab = document.getElementById('dashboardInquiryFab');
    if (fab) document.body.appendChild(fab);
    const backdrop = document.getElementById('dashboardInquiryBackdrop');
    if (backdrop) document.body.appendChild(backdrop);
    const panel = document.getElementById('dashboardInquiryPanel');
    if (panel) document.body.appendChild(panel);
})();
</script>

<script src="../../assets/js/calendar_widget.js"></script>
<script>
if (typeof MiniCalendar !== 'undefined' && document.getElementById('scCalPrev')) {
    window.staffDashboardMiniCalendar = MiniCalendar({
        containerId: 'scCalGrid',
        labelId: 'scCalMonthLabel',
        prevBtnId: 'scCalPrev',
        nextBtnId: 'scCalNext',
        endpoint: '../shared/calendar_data.php',
        openDayFn: 'scCalOpenDay',
        appointmentVar: 'staffCalAppointments',
        month: '<?php echo $scCalMonthParam; ?>'
    });
}
</script>



<style>

.complete-treatment-modal {
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

.complete-treatment-bg-orb {
    position: absolute;
    border-radius: 999px;
    pointer-events: none;
    filter: blur(8px);
    opacity: 0.55;
}

.complete-treatment-bg-orb-a {
    width: 140px;
    height: 140px;
    right: -30px;
    top: 88px;
    background: radial-gradient(circle, rgba(14, 165, 233, 0.2) 0%, rgba(14, 165, 233, 0) 72%);
}

.complete-treatment-bg-orb-b {
    width: 160px;
    height: 160px;
    left: -40px;
    bottom: -30px;
    background: radial-gradient(circle, rgba(34, 197, 94, 0.16) 0%, rgba(34, 197, 94, 0) 74%);
}

.complete-treatment-banner {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 24px 26px 18px;
    background:
        linear-gradient(135deg, rgba(255,255,255,0.12), rgba(255,255,255,0) 42%),
        linear-gradient(135deg, #0b7db3 0%, #0891b2 38%, #0f9f53 100%);
    color: white;
    position: relative;
}

.complete-treatment-icon-wrap {
    width: 54px;
    height: 54px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.22);
    backdrop-filter: blur(6px);
    flex-shrink: 0;
}

.complete-treatment-icon {
    display: flex;
    align-items: center;
    justify-content: center;
}

.complete-treatment-icon svg {
    width: 26px;
    height: 26px;
    fill: currentColor;
}

.complete-treatment-kicker {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    opacity: 0.9;
    font-weight: 700;
}

.complete-treatment-close {
    margin-left: auto;
    background: rgba(255, 255, 255, 0.12);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.18);
    width: 40px;
    height: 40px;
    border-radius: 12px;
    font-size: 1.5rem;
    cursor: pointer;
    backdrop-filter: blur(8px);
}

.complete-treatment-body {
    padding: 24px 26px 12px;
    position: relative;
}

.complete-treatment-text {
    color: #1f2937;
    font-size: 1.06rem;
    line-height: 1.6;
    margin: 0 0 16px;
    font-weight: 500;
}

.complete-treatment-highlight {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    border-radius: 16px;
    background: linear-gradient(135deg, #ecfeff 0%, #f0fdf4 100%);
    border: 1px solid #d9f99d;
    margin-bottom: 14px;
}

.complete-treatment-highlight-icon {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    box-shadow: 0 10px 24px rgba(8, 145, 178, 0.12);
    font-size: 1.25rem;
    flex-shrink: 0;
}

.complete-treatment-highlight-label {
    color: #0f766e;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 700;
    margin-bottom: 3px;
}

.complete-treatment-highlight-value {
    color: #1f2937;
    font-size: 0.95rem;
    font-weight: 600;
}

.complete-treatment-note {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 14px;
    background: #f0fdf4;
    color: #166534;
    font-size: 0.92rem;
    border: 1px solid #dcfce7;
}

.complete-treatment-note-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: #22c55e;
    box-shadow: 0 0 0 5px rgba(34, 197, 94, 0.14);
    flex-shrink: 0;
}

.complete-treatment-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 18px 26px 26px;
}

.complete-treatment-cancel,
.complete-treatment-confirm {
    border: none;
    border-radius: 14px;
    padding: 13px 20px;
    cursor: pointer;
    font-weight: 700;
    font-size: 0.97rem;
    transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
}

.complete-treatment-cancel {
    background: #eef6fb;
    color: #315266;
    border: 1px solid #d8e9f4;
}

.complete-treatment-confirm {
    background: linear-gradient(135deg, #0891b2 0%, #0f9f53 100%);
    color: white;
    box-shadow: 0 14px 28px rgba(8, 145, 178, 0.24);
}

.complete-treatment-cancel:hover,
.complete-treatment-confirm:hover,
.complete-treatment-close:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
}

@media (max-width: 640px) {
    .complete-treatment-banner,
    .complete-treatment-body,
    .complete-treatment-actions {
        padding-left: 18px;
        padding-right: 18px;
    }

    .complete-treatment-actions {
        flex-direction: column-reverse;
    }

    .complete-treatment-cancel,
    .complete-treatment-confirm {
        width: 100%;
    }
}

/* Floating Inquiry Button */
.dashboard-inquiry-fab {
    position: fixed;
    right: 24px;
    bottom: 24px;
    width: 58px;
    height: 58px;
    border-radius: 50%;
    border: none;
    background: #0d92f7 !important;
    color: #ffffff !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none !important;
    box-shadow: 0 14px 28px rgba(5, 150, 105, 0.28);
    z-index: 2001;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}
.dashboard-inquiry-fab:hover {
    transform: translateY(-2px);
    box-shadow: 0 18px 32px rgba(5, 150, 105, 0.34);
    background: #0b6cf3 !important;
}
.dashboard-inquiry-fab-badge {
    position: absolute;
    top: -2px;
    right: -2px;
    min-width: 22px;
    height: 22px;
    padding: 0 6px;
    border-radius: 999px;
    background: #dc2626;
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #ffffff;
    line-height: 1;
}

/* Inquiry Panel */
.dashboard-inquiry-panel {
    position: fixed;
    right: 0;
    top: 0;
    bottom: 0;
    width: 400px;
    max-width: 100vw;
    background: #ffffff;
    box-shadow: -8px 0 30px rgba(15, 23, 42, 0.12);
    z-index: 2002;
    display: flex;
    flex-direction: column;
    transform: translateX(100%);
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.28s ease, visibility 0.28s;
}
.dashboard-inquiry-panel.open {
    transform: translateX(0);
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}
.dashboard-inquiry-panel-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 18px 20px;
    border-bottom: 1px solid #e5e7eb;
    background: #fafbff;
}
.dashboard-inquiry-panel-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: #0f172a;
    font-weight: 700;
}
.dashboard-inquiry-panel-count {
    font-size: 12px;
    font-weight: 700;
    background: #e2e8f0;
    color: #64748b;
    padding: 2px 8px;
    border-radius: 999px;
}
.dashboard-inquiry-panel-close {
    margin-left: auto;
    background: none;
    border: none;
    font-size: 1.6rem;
    color: #6b7280;
    cursor: pointer;
    padding: 0 4px;
    line-height: 1;
}
.dashboard-inquiry-panel-close:hover {
    color: #111827;
}
.dashboard-inquiry-panel-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px 0;
}
.dashboard-inquiry-loading {
    text-align: center;
    padding: 40px 20px;
    color: #64748b;
}

/* Inquiry Panel Items */
.dashboard-inquiry-item {
    display: flex;
    gap: 12px;
    padding: 14px 20px;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s;
    cursor: default;
}
.dashboard-inquiry-item:hover {
    background: #f8fafc;
}
.dashboard-inquiry-item.is-unread {
    background: #eff6ff;
    border-left: 4px solid #2563eb;
    padding-left: 16px;
}
.dashboard-inquiry-item.is-unread:hover {
    background: #dbeafe;
}
.dashboard-inquiry-item.is-unread .dashboard-inquiry-item-name,
.dashboard-inquiry-item.is-unread .dashboard-inquiry-item-preview {
    color: #0f172a;
    font-weight: 700;
}
.dashboard-inquiry-item-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
}
.dashboard-inquiry-item-body {
    flex: 1;
    min-width: 0;
}
.dashboard-inquiry-item-top {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}
.dashboard-inquiry-item-name {
    font-weight: 600;
    color: #0f172a;
    font-size: 14px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dashboard-inquiry-item-status {
    font-size: 10px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    white-space: nowrap;
}
.dashboard-inquiry-item-status.Unread {
    background: #fef3c7;
    color: #92400e;
}
.dashboard-inquiry-item-status.Read {
    background: #f0fdf4;
    color: #16a34a;
}
.dashboard-inquiry-item-status.Replied {
    background: #dbeafe;
    color: #1d4ed8;
}
.dashboard-inquiry-item-preview {
    font-size: 13px;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 6px;
}
.dashboard-inquiry-item-reply {
    font-size: 12px;
    color: #2563eb;
    font-weight: 600;
    background: none;
    border: none;
    padding: 4px 0;
    cursor: pointer;
}
.dashboard-inquiry-item-reply:hover {
    color: #1d4ed8;
    text-decoration: underline;
}
.dashboard-inquiry-empty {
    text-align: center;
    padding: 60px 20px;
    color: #64748b;
}

/* Inquiry Chat View — matches staff_inquiries.php chat modal classes */
.chat-modal-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px 20px;
    border-bottom: 1px solid #e5e7eb;
    background: #fafbff;
}
.chat-modal-back {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: none;
    background: #eef2ff;
    color: #1d4ed8;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background 0.15s;
}
.chat-modal-back:hover { background: #dbeafe; }
.chat-modal-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 15px;
    flex-shrink: 0;
}
.chat-modal-user-info {
    flex: 1;
    min-width: 0;
}
.chat-modal-name {
    font-weight: 700;
    color: #0f172a;
    font-size: 15px;
}
.chat-modal-email {
    font-size: 12px;
    color: #64748b;
    margin-top: 2px;
}
.chat-modal-source {
    display: none;
}
.chat-modal-email {
    display: none;
}
.chat-info-button {
    width: 36px;
    height: 36px;
    flex: 0 0 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 50%;
    background: #eff6ff;
    color: #2563eb;
    font: 700 18px/1 Georgia, serif;
    cursor: pointer;
    transition: background 0.2s, transform 0.2s;
}
.chat-info-button:hover {
    background: #dbeafe;
    transform: scale(1.04);
}
.inquiry-info-panel {
    position: absolute;
    inset: 0;
    z-index: 30;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    background: #fff;
    transform: translateX(100%);
    visibility: hidden;
    transition: transform 0.25s ease, visibility 0.25s;
}
.inquiry-info-panel.open {
    transform: translateX(0);
    visibility: visible;
}
.inquiry-info-header {
    min-height: 72px;
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 20px;
    border-bottom: 1px solid #e5e7eb;
    color: #0f172a;
}
.inquiry-info-profile {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 28px 20px;
    border-bottom: 1px solid #eef2f7;
}
.inquiry-info-avatar {
    width: 76px;
    height: 76px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 50%;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    font-size: 24px;
    font-weight: 700;
}
.inquiry-info-avatar img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover;
    border-radius: 50%;
}
.inquiry-info-name {
    color: #0f172a;
    font-size: 17px;
    font-weight: 700;
    text-align: center;
}
.inquiry-media-toggle {
    width: 100%;
    min-height: 58px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border: none;
    border-bottom: 1px solid #eef2f7;
    background: #fff;
    color: #0f172a;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
}
.inquiry-media-toggle:hover {
    background: #f8fafc;
}
.inquiry-media-chevron {
    transition: transform 0.2s;
}
.inquiry-media-toggle.open .inquiry-media-chevron {
    transform: rotate(180deg);
}
.inquiry-media-content {
    display: none;
    padding: 12px;
}
.inquiry-media-content.open {
    display: block;
}
.inquiry-media-gallery {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 6px;
}
.inquiry-media-item {
    position: relative;
    aspect-ratio: 1;
    overflow: hidden;
    padding: 0;
    border: none;
    border-radius: 8px;
    background: #e2e8f0;
    cursor: pointer;
}
.inquiry-media-item img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
    transition: transform 0.2s;
}
.inquiry-media-item:hover img {
    transform: scale(1.05);
}
.inquiry-media-empty {
    grid-column: 1 / -1;
    padding: 36px 16px;
    color: #64748b;
    font-size: 13px;
    text-align: center;
}
.chat-modal-messages {
    flex: 1;
    overflow-y: auto;
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #fcfdff;
}
.chat-bubble {
    max-width: 88%;
    padding: 12px 16px;
    border-radius: 18px;
    line-height: 1.55;
    font-size: 14px;
    word-break: break-word;
}
.chat-bubble.received {
    align-self: flex-start;
    background: #f0f2f5;
    color: #111827;
    border-bottom-left-radius: 6px;
}
.chat-bubble.sent {
    align-self: flex-end;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    border-bottom-right-radius: 6px;
}
.chat-bubble .chat-meta {
    font-size: 11px;
    opacity: 0.65;
    margin-top: 6px;
}
.chat-bubble.sent .chat-meta {
    color: rgba(255,255,255,0.8);
}
.chat-bubble.sent.attachment-only {
    padding: 0;
    background: transparent;
    color: #64748b;
    border-radius: 8px;
}
.chat-bubble.sent.attachment-only > div:first-child {
    color: #64748b !important;
    margin-left: 2px;
}
.chat-bubble.sent.attachment-only .chat-meta {
    color: #64748b;
    margin-left: 2px;
}
.chat-bubble.sent.attachment-only .chat-att-img {
    margin-top: 4px;
    border: 1px solid #e5e7eb;
}
.inquiry-typing-row {
    align-self: flex-start;
    display: flex;
    align-items: center;
    gap: 8px;
}
.inquiry-typing-avatar {
    width: 32px;
    height: 32px;
    flex: 0 0 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 50%;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
}
.inquiry-typing-avatar img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover;
    border-radius: 50%;
}
.inquiry-typing-indicator {
    display: flex;
    align-items: center;
    min-width: 52px;
    min-height: 36px;
    padding: 10px 14px;
    border-radius: 18px;
    border-bottom-left-radius: 6px;
    background: #eef1f5;
    color: #64748b;
}
.inquiry-typing-dots {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.inquiry-typing-dots span {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #94a3b8;
    animation: inquiryTypingWave 1.1s infinite ease-in-out;
}
.inquiry-typing-dots span:nth-child(2) { animation-delay: .15s; }
.inquiry-typing-dots span:nth-child(3) { animation-delay: .3s; }
@keyframes inquiryTypingWave {
    0%, 60%, 100% { transform: translateY(0); opacity: .45; }
    30% { transform: translateY(-3px); opacity: 1; }
}
.chat-channel-info {
    padding: 10px 20px;
    font-size: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    border-top: 1px solid #e5e7eb;
    background: #fafbff;
    color: #475569;
}
.chat-compose {
    padding: 14px 20px 18px;
    border-top: 1px solid #e5e7eb;
    background: #fff;
}
.chat-send-row {
    display: flex;
    gap: 6px;
    align-items: center;
    width: 100%;
    min-height: 58px;
    padding: 6px;
    border: 1px solid #dbe3ee;
    border-radius: 999px;
    background: #f8fafc;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
}
.chat-send-row:focus-within {
    border-color: #2563eb;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}
.chat-send-row textarea {
    flex: 1;
    min-width: 0;
    border: none;
    border-radius: 0;
    padding: 12px 8px;
    font: inherit;
    font-size: 14px;
    resize: none;
    min-height: 44px;
    max-height: 120px;
    outline: none;
    background: transparent;
}
.chat-send-row textarea:focus {
    border: none;
    box-shadow: none;
    background: transparent;
}
.chat-send-btn {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    border: none;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: transform 0.15s, box-shadow 0.15s;
    box-shadow: 0 4px 12px rgba(37,99,235,0.25);
}
.chat-send-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 16px rgba(37,99,235,0.35);
}
.chat-send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}
.chat-attach-btn {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    border: none;
    background: transparent;
    color: #64748b;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background 0.2s, color 0.2s;
}
.chat-attach-btn:hover {
    background: #e8eef7;
    color: #2563eb;
}
.chat-attachment-preview {
    padding: 8px 0 4px;
}
.chat-attachment-preview-item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    background: #f0f4ff;
    border: 1px solid #c7d2fe;
    border-radius: 8px;
    font-size: 13px;
    color: #3730a3;
    max-width: 250px;
}
.chat-attachment-preview-item img {
    width: 48px;
    height: 48px;
    object-fit: cover;
    border-radius: 6px;
}
.chat-attachment-preview-item .att-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
}
.chat-attachment-preview-item .att-remove {
    background: none;
    border: none;
    color: #ef4444;
    cursor: pointer;
    font-size: 16px;
    padding: 0 2px;
    line-height: 1;
}
.chat-bubble .chat-att-img {
    max-width: 260px;
    max-height: 260px;
    border-radius: 8px;
    margin-top: 6px;
    cursor: pointer;
    display: block;
}
.chat-bubble .chat-att-file {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.25);
    border-radius: 8px;
    margin-top: 6px;
    color: inherit;
    text-decoration: none;
    font-size: 13px;
}
.chat-bubble.received .chat-att-file {
    background: rgba(0,0,0,0.05);
    border-color: rgba(0,0,0,0.1);
    color: #3730a3;
}

.dashboard-inquiry-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.4);
    z-index: 2000;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.28s ease, visibility 0.28s;
}
.dashboard-inquiry-backdrop.open {
    opacity: 1;
    visibility: visible;
}

@media (max-width: 640px) {
    .dashboard-inquiry-fab {
        right: 16px;
        bottom: 24px;
        width: 52px;
        height: 52px;
    }
    .dashboard-inquiry-panel {
        width: 100vw;
    }
    .chat-compose {
        padding: 10px;
    }
    .chat-send-row {
        gap: 3px;
        min-height: 54px;
        padding: 5px;
    }
    .chat-attach-btn {
        width: 38px;
        height: 38px;
    }
    .chat-send-btn {
        width: 42px;
        height: 42px;
    }
    .chat-send-row textarea {
        padding-left: 5px;
        padding-right: 5px;
    }
}

/* Summary card colors (blue design) */
.summary-cards .summary-card {
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border-radius: 12px;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}
.summary-cards .summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.07);
}
.summary-cards .card-waiting,
.summary-cards .card-completed,
.summary-cards .card-cancelled,
.summary-cards .card-on-hold {
   
}


.summary-cards .summary-info h3 { color: #27427e; }
.summary-cards .summary-info p { color: #5b6ea3; }

</style>
<?php require_once '../../includes/staff_layout_end.php'; ?>
