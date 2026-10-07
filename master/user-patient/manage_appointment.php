<?php
require_once __DIR__ . '/../../includes/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $revoked = app_session_revoked_response();
    if ($revoked !== null) { echo json_encode($revoked); exit; }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['user_id'])
        || ($_SESSION['role'] ?? '') !== 'user' || empty($_SESSION['patient_id'])) {
        http_response_code(403);
        throw new InvalidArgumentException('Please sign in to your patient account.');
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!is_string($input['csrf'] ?? null) || empty($_SESSION['patient_appointment_csrf'])
        || !hash_equals($_SESSION['patient_appointment_csrf'], $input['csrf'])) {
        http_response_code(403);
        throw new InvalidArgumentException('Please reload the page and try again.');
    }
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../includes/patient_appointment_management.php';
    require_once __DIR__ . '/../../includes/treatment_plan_workflow.php';
    ensurePatientAppointmentManagementSchema($pdo);
    ensureAppointmentSlotSchema($pdo);
    ensureTreatmentPlanWorkflowSchema($pdo);
    $result = managePatientAppointment($pdo, (int)$_SESSION['patient_id'], (int)$_SESSION['user_id'], $input);
    try {
        require_once __DIR__ . '/../../includes/audit_helper.php';
        logAudit($pdo, $_SESSION['user_id'], $_SESSION['username'] ?? '', 'user', $input['action'] === 'cancel' ? 'cancel' : 'update', 'appointments', $result['message'], $result['appointment_id'], 'appointments');
        require_once __DIR__ . '/../../includes/notification_functions.php';
        $title = $input['action'] === 'edit' ? 'Patient Updated Appointment Request' : 'Patient Cancelled Appointment';
        $message = 'Appointment #' . $result['appointment_id'] . ': ' . $result['message'];
        notifyAdmin($pdo, 'appointment', $title, $message, 'admin_appointments.php');
        notifyStaff($pdo, 'appointment', $title, $message, 'staff_appointments.php');
        notifyDentist($pdo, 'appointment', $title, $message, 'dentist_appointments.php');
    } catch (Throwable $e) { error_log('Patient appointment notification: ' . $e->getMessage()); }
    echo json_encode($result);
} catch (InvalidArgumentException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Patient appointment management: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update this appointment. Please try again.']);
}
