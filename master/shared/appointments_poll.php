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
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    require_once '../../config/database.php';

    $stmt = $pdo->prepare("
        SELECT a.id, a.patient_id, a.status, a.appointment_date, a.appointment_time,
               a.check_in_time, a.service_id,
               COALESCE(p.first_name, 'Unknown') as first_name,
               COALESCE(p.last_name, 'Unknown') as last_name
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        WHERE (a.is_archived = 0 OR a.is_archived IS NULL)
        ORDER BY a.appointment_time ASC
    ");
        $stmt->execute();
    $items = $stmt->fetchAll();

    $hash = md5(json_encode($items));

    echo json_encode([
        'success' => true,
        'appointments' => $items,
        'hash' => $hash,
        'count' => count($items)
    ]);

} catch (Exception $e) {
    error_log("Appointments Poll Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
