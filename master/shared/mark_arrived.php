<?php
/**
 * Mark Appointment as Arrived (check_in)
 * AJAX endpoint shared across all roles.
 *
 * When an appointment patient arrives:
 *   1. Mark the appointment as 'arrived'
 *   2. Automatically create a queue entry so they skip New Admission
 *   3. Return the queue number for display
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
$apptId = (int)($input['appt_id'] ?? $_POST['appt_id'] ?? 0);

if (!$apptId) {
    echo json_encode(['success' => false, 'message' => 'Missing appointment ID']);
    exit;
}

try {
    ensureQueueManagementSchema($pdo);

    $stmt = $pdo->prepare("
        SELECT a.*, p.id AS pid, p.first_name, p.last_name, s.name AS service_name
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN services s ON a.service_id = s.id
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->execute([$apptId]);
    $appt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appt) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit;
    }

    $patientId = (int)($appt['patient_id'] ?? $appt['pid'] ?? 0);

    if (!$patientId) {
        echo json_encode(['success' => false, 'message' => 'This appointment has no linked patient. Please use New Admission.']);
        exit;
    }

    $today = date('Y-m-d');

    $existingQueue = fetchQueueEntryByAppointment($pdo, $apptId, $today);
    if ($existingQueue) {
        $stmtUpd = $pdo->prepare("UPDATE appointments SET check_in_time = COALESCE(check_in_time, NOW()), status = 'arrived' WHERE id = ?");
        $stmtUpd->execute([$apptId]);
        echo json_encode([
            'success' => true,
            'message' => 'Patient already in queue as ' . ($existingQueue['queue_label'] ?? buildQueueLabel((int)($existingQueue['queue_number'] ?? 0))) . '.',
            'queue_id' => (int)($existingQueue['id'] ?? 0),
            'queue_number' => (int)($existingQueue['queue_number'] ?? 0),
            'queue_label' => $existingQueue['queue_label'] ?? buildQueueLabel((int)($existingQueue['queue_number'] ?? 0))
        ]);
        exit;
    }

    $treatmentType = trim($appt['service_name'] ?? $appt['treatment'] ?? 'General Checkup');
    if (empty($treatmentType)) {
        $treatmentType = 'General Checkup';
    }

    $pdo->beginTransaction();

    $queueEntry = createQueueEntry($pdo, [
        'patient_id'      => $patientId,
        'appointment_id'  => $apptId,
        'queue_date'      => $today,
        'treatment_type'  => $treatmentType,
        'procedure_notes' => trim($appt['notes'] ?? ''),
        'queue_time'      => date('H:i:s'),
        'status'          => 'waiting',
        'priority'        => 5,
    ]);

    $queueNumber = (int)($queueEntry['queue_number'] ?? 0);
    $queueLabel  = $queueEntry['queue_label'] ?? buildQueueLabel($queueNumber);

    $stmtUpd = $pdo->prepare("UPDATE appointments SET check_in_time = NOW(), status = 'arrived' WHERE id = ?");
    $stmtUpd->execute([$apptId]);

    $pdo->commit();

    echo json_encode([
        'success'      => true,
        'message'      => 'Patient marked as arrived and added to queue as ' . $queueLabel,
        'queue_id'     => (int)($queueEntry['id'] ?? 0),
        'queue_number' => $queueNumber,
        'queue_label'  => $queueLabel,
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
