<?php
/**
 * Save Vital Signs & Body Measurement for a queue entry.
 * AJAX endpoint shared across staff/dentist/admin.
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/queue_management_helper.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$queueId = (int)($input['queue_id'] ?? $input['id'] ?? 0);
if (!$queueId) {
    echo json_encode(['success' => false, 'message' => 'Missing queue ID']);
    exit;
}

try {
    ensureQueueManagementSchema($pdo);

    $fields = ['blood_pressure', 'heart_rate', 'temperature', 'oxygen_saturation', 'height', 'weight'];
    $updates = [];
    $values = [];

    foreach ($fields as $field) {
        $value = trim((string)($input[$field] ?? ''));
        if ($value !== '') {
            $updates[] = "$field = ?";
            $values[] = $value;
        }
    }

    if (empty($updates)) {
        echo json_encode(['success' => true, 'message' => 'No vital sign data to save']);
        exit;
    }

    $updates[] = 'updated_at = NOW()';
    $values[] = $queueId;

    $stmt = $pdo->prepare("UPDATE queue SET " . implode(', ', $updates) . " WHERE id = ?");
    $stmt->execute($values);

    echo json_encode(['success' => true, 'message' => 'Vital signs saved successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
