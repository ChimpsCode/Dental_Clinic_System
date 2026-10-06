<?php
require_once __DIR__ . '/../../includes/session_bootstrap.php';error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['staff', 'dentist', 'admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/treatment_plan_workflow.php';
ensureTreatmentPlanWorkflowSchema($pdo);
require_once __DIR__ . '/../../includes/sms_functions.php';
require_once __DIR__ . '/../../includes/appointment_cancellation_notifier.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $appointment_id = (int)($input['id'] ?? 0);
    $cancel_reason = trim($input['cancel_reason'] ?? '');

    if (!$appointment_id) {
        throw new Exception('Appointment ID is required');
    }

    // Check if cancel_reason column exists, add if not
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'cancel_reason'");
        if ($checkCol->rowCount() == 0) {
            $pdo->exec("ALTER TABLE appointments ADD cancel_reason TEXT DEFAULT NULL");
        }
    } catch (Exception $e) {}

    // Also add cancelled_by and cancelled_at columns
    try {
        $pdo->exec("ALTER TABLE appointments ADD cancelled_by INT DEFAULT NULL");
        $pdo->exec("ALTER TABLE appointments ADD cancelled_at DATETIME DEFAULT NULL");
    } catch (Exception $e) {}

    syncTreatmentPlanForAppointment($pdo, $appointment_id);
    assertTreatmentPlanAppointmentEditable($pdo, $appointment_id);
    $effectiveReason = $cancel_reason !== '' ? $cancel_reason : 'No reason provided';

    // Update appointment status to cancelled
    $stmt = $pdo->prepare("UPDATE appointments SET
        status = 'Cancelled',
        cancel_reason = ?,
        cancelled_by = ?,
        cancelled_at = NOW()
        WHERE id = ?");

    $stmt->execute([
        $effectiveReason,
        $_SESSION['user_id'],
        $appointment_id
    ]);

    syncTreatmentPlanForAppointment($pdo, $appointment_id);

    // Log the cancellation
    try {
        require_once __DIR__ . '/../../includes/audit_helper.php';
        logAudit($pdo, $_SESSION['user_id'], $_SESSION['username'], $role, 'cancel', 'appointments', 'Appointment cancelled: APT-' . str_pad($appointment_id, 4, '0', STR_PAD_LEFT) . ($cancel_reason ? ' - Reason: ' . $cancel_reason : ''), $appointment_id, 'appointments', null, 'cancelled');
    } catch (Exception $e) {}

    // Tell the patient. Previously this endpoint was silent, so a cancelled
    // appointment produced no SMS and no in-app notice at all.
    $message = 'Appointment cancelled successfully';
    try {
        $notice = notifyPatientOfAppointmentCancellation($pdo, $appointment_id, $effectiveReason, (int)$_SESSION['user_id']);
        if ($notice['sms_sent']) {
            $message .= '. The patient was notified by SMS.';
        } elseif ($notice['sms_reason'] !== '') {
            $message .= '. The patient was notified in the portal, but no SMS was sent because ' . $notice['sms_reason'] . '.';
        } else {
            $message .= '. The patient was notified in the portal.';
        }
    } catch (Throwable $e) {
        error_log("Cancel notification failed for appointment $appointment_id: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>