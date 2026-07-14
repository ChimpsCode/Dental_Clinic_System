<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['dentist'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $appointment_id = $input['id'] ?? 0;
    $cancel_reason = trim($input['cancel_reason'] ?? '');

    if (!$appointment_id) {
        throw new Exception('Appointment ID is required');
    }

    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'cancel_reason'");
        if ($checkCol->rowCount() == 0) {
            $pdo->exec("ALTER TABLE appointments ADD cancel_reason TEXT DEFAULT NULL");
        }
    } catch (Exception $e) {}

    try {
        $pdo->exec("ALTER TABLE appointments ADD cancelled_by INT DEFAULT NULL");
        $pdo->exec("ALTER TABLE appointments ADD cancelled_at DATETIME DEFAULT NULL");
    } catch (Exception $e) {}

    $stmt = $pdo->prepare("UPDATE appointments SET 
        status = 'Cancelled',
        cancel_reason = ?,
        cancelled_by = ?,
        cancelled_at = NOW()
        WHERE id = ?");
    
    $stmt->execute([
        $cancel_reason ?: 'No reason provided',
        $_SESSION['user_id'],
        $appointment_id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Appointment cancelled successfully'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>