<?php
ob_start();
require_once __DIR__ . '/../../includes/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['staff', 'dentist', 'admin'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    require_once '../../config/database.php';

    // Daily reset: cancel stale unfinished queue entries from previous days
    try {
        require_once '../../includes/queue_management_helper.php';
        archiveStaleQueueEntries($pdo);
    } catch (Exception $cleanupEx) {
        error_log("Stale queue cleanup skipped: " . $cleanupEx->getMessage());
    }

    $today = date('Y-m-d');
    $queueColumns = array_flip($pdo->query('SHOW COLUMNS FROM queue')->fetchAll(PDO::FETCH_COLUMN));
    $queueDateExpression = isset($queueColumns['queue_date'])
        ? 'COALESCE(q.queue_date, DATE(q.created_at))'
        : 'DATE(q.created_at)';
    $queueNumberSelect = isset($queueColumns['queue_number']) ? 'q.queue_number' : 'q.id AS queue_number';
    $doctorIdSelect = isset($queueColumns['doctor_id']) ? 'q.doctor_id' : 'NULL AS doctor_id';
    $appointmentIdSelect = isset($queueColumns['appointment_id']) ? 'q.appointment_id' : 'NULL AS appointment_id';
    $doctorJoin = isset($queueColumns['doctor_id']) ? 'LEFT JOIN users u_doc ON q.doctor_id = u_doc.id' : '';

    $stmt = $pdo->prepare("
         SELECT q.id, q.patient_id, {$queueNumberSelect}, q.status, q.treatment_type,
             q.queue_time, q.updated_at AS procedure_started_at, {$doctorIdSelect}, {$appointmentIdSelect}, q.teeth_numbers,
               COALESCE(p.first_name, 'Unknown') as first_name,
               COALESCE(p.middle_name, '') as middle_name,
               COALESCE(p.last_name, '') as last_name,
               COALESCE(p.suffix, '') as suffix,
               COALESCE(p.phone, '') as phone,
               COALESCE(p.gender, '') as gender,
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
               COALESCE(CONCAT(u_doc.first_name, ' ', u_doc.last_name), 'Unassigned') as assigned_doctor
        FROM queue q
        LEFT JOIN patients p ON CAST(q.patient_id AS UNSIGNED) = p.id
        {$doctorJoin}
        WHERE q.status IN ('waiting', 'in_procedure', 'completed', 'cancelled', 'on_hold')
          AND q.is_archived = 0
          AND {$queueDateExpression} = ?
        ORDER BY
            CASE COALESCE(q.status, 'waiting')
                WHEN 'in_procedure' THEN 1
                WHEN 'waiting' THEN 2
                WHEN 'completed' THEN 3
                ELSE 2
            END,
            q.queue_time ASC
    ");
    $stmt->execute([$today]);
    $items = $stmt->fetchAll();

    $waitingCount = count(array_filter($items, fn($q) => ($q['status'] ?? '') === 'waiting'));
    $procedureCount = count(array_filter($items, fn($q) => ($q['status'] ?? '') === 'in_procedure'));
    $completedToday = count(array_filter($items, fn($q) => ($q['status'] ?? '') === 'completed'));
    $onHoldCount = count(array_filter($items, fn($q) => ($q['status'] ?? '') === 'on_hold'));
    $cancelledCount = count(array_filter($items, fn($q) => ($q['status'] ?? '') === 'cancelled'));
    $completedTodayCard = function_exists('countCompletedQueueToday')
        ? countCompletedQueueToday($pdo)
        : $completedToday;

    $hash = md5(json_encode($items) . '|ct:' . $completedTodayCard);

    echo json_encode([
        'success' => true,
        'items' => $items,
        'hash' => $hash,
        'counts' => [
            'total' => count($items),
            'waiting' => $waitingCount,
            'in_procedure' => $procedureCount,
            'completed' => $completedToday,
            'completed_today' => $completedTodayCard,
            'on_hold' => $onHoldCount
            ,'cancelled' => $cancelledCount
        ]
    ]);

} catch (Exception $e) {
    error_log("Queue Poll Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
