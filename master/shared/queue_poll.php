<?php
ob_start();
session_start();

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

    $today = date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT q.id, q.patient_id, q.queue_number, q.status, q.treatment_type,
               q.queue_time, q.doctor_id, q.appointment_id, q.teeth_numbers,
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
        LEFT JOIN users u_doc ON q.doctor_id = u_doc.id
        WHERE q.status IN ('waiting', 'in_procedure', 'completed', 'on_hold')
          AND q.is_archived = 0
          AND COALESCE(q.queue_date, DATE(q.created_at)) = ?
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

    $hash = md5(json_encode($items));

    echo json_encode([
        'success' => true,
        'items' => $items,
        'hash' => $hash,
        'counts' => [
            'total' => count($items),
            'waiting' => $waitingCount,
            'in_procedure' => $procedureCount,
            'completed' => $completedToday,
            'on_hold' => $onHoldCount
        ]
    ]);

} catch (Exception $e) {
    error_log("Queue Poll Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
