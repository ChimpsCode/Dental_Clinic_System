<?php
/**
 * Mark Appointment as Arrived (check_in)
 * AJAX endpoint shared across all roles
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$apptId = (int)($input['appt_id'] ?? $_POST['appt_id'] ?? 0);

if (!$apptId) {
    echo json_encode(['success' => false, 'message' => 'Missing appointment ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE appointments SET check_in_time = NOW(), status = 'arrived' WHERE id = ?");
    $stmt->execute([$apptId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Patient marked as arrived']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
