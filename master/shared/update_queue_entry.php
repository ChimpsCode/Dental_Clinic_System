<?php
/**
 * Update a queue entry's patient info, assigned dentist, and vital signs.
 * AJAX endpoint shared across staff/dentist/admin.
 *
 * Updates:
 *   - patients  (first_name, middle_name, last_name, phone, email)
 *   - queue     (doctor_id, blood_pressure, heart_rate, temperature,
 *                oxygen_saturation, height, weight)
 *   - appointments (dentist_id) when the queue entry is linked to an appointment
 */

require_once __DIR__ . '/../../includes/session_bootstrap.php';header('Content-Type: application/json');

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

$queueId = (int)($input['queue_id'] ?? 0);
if (!$queueId) {
    echo json_encode(['success' => false, 'message' => 'Missing queue ID']);
    exit;
}

try {
    ensureQueueManagementSchema($pdo);

    $stmt = $pdo->prepare("SELECT * FROM queue WHERE id = ?");
    $stmt->execute([$queueId]);
    $queue = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$queue) {
        echo json_encode(['success' => false, 'message' => 'Queue entry not found']);
        exit;
    }

    $patientId = (int)$queue['patient_id'];

    if ($patientId) {
        $firstName = trim((string)($input['first_name'] ?? ''));
        $middleName = trim((string)($input['middle_name'] ?? ''));
        $lastName = trim((string)($input['last_name'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));

        $pUpdates = [];
        $pValues = [];

        if ($firstName !== '') { $pUpdates[] = 'first_name = ?'; $pValues[] = $firstName; }
        if ($middleName !== '') { $pUpdates[] = 'middle_name = ?'; $pValues[] = $middleName; }
        if ($lastName !== '') { $pUpdates[] = 'last_name = ?'; $pValues[] = $lastName; }
        if ($phone !== '') { $pUpdates[] = 'phone = ?'; $pValues[] = $phone; }
        if ($email !== '') { $pUpdates[] = 'email = ?'; $pValues[] = $email; }

        if (!empty($pUpdates)) {
            $pValues[] = $patientId;
            $pdo->prepare("UPDATE patients SET " . implode(', ', $pUpdates) . " WHERE id = ?")
                ->execute($pValues);
        }
    }

    $qUpdates = [];
    $qValues = [];

    $dentistId = isset($input['dentist_id']) && $input['dentist_id'] !== '' && $input['dentist_id'] !== null
        ? (int)$input['dentist_id']
        : null;
    if (array_key_exists('dentist_id', $input)) {
        $qUpdates[] = 'doctor_id = ?';
        $qValues[] = $dentistId;
    }

    foreach (['blood_pressure', 'heart_rate', 'temperature', 'oxygen_saturation', 'height', 'weight'] as $field) {
        $value = trim((string)($input[$field] ?? ''));
        $qUpdates[] = "$field = ?";
        $qValues[] = $value !== '' ? $value : null;
    }

    $qUpdates[] = 'updated_at = NOW()';
    $qValues[] = $queueId;

    $pdo->prepare("UPDATE queue SET " . implode(', ', $qUpdates) . " WHERE id = ?")
        ->execute($qValues);

    if ($queue['appointment_id']) {
        $apptUpdates = [];
        $apptValues = [];
        if (array_key_exists('dentist_id', $input)) {
            $apptUpdates[] = 'dentist_id = ?';
            $apptValues[] = $dentistId;
        }
        if (!empty($apptUpdates)) {
            $apptValues[] = (int)$queue['appointment_id'];
            $pdo->prepare("UPDATE appointments SET " . implode(', ', $apptUpdates) . " WHERE id = ?")
                ->execute($apptValues);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Queue entry updated successfully',
        'queue_id' => $queueId,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
