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
    if (($input['action'] ?? '') === 'check_edit') {
        $stmt = $pdo->prepare('SELECT a.*, s.name AS service_name FROM appointments a LEFT JOIN services s ON s.id=a.service_id WHERE a.id=? AND a.patient_id=?');
        $stmt->execute([(int)($input['appointment_id'] ?? 0), (int)$_SESSION['patient_id']]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$appointment) throw new InvalidArgumentException('Appointment not found.');
        if (!patientAppointmentPermissions($appointment, date('Y-m-d'))['edit']) {
            throw new InvalidArgumentException(patientAppointmentEditBlockReason($appointment));
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM queue WHERE appointment_id=? AND status IN ('waiting','on_hold','in_procedure','pending_payment','completed')");
        $stmt->execute([$appointment['id']]);
        if ($stmt->fetchColumn()) throw new InvalidArgumentException('This appointment has already entered the clinic queue. Please contact the clinic.');
        echo json_encode(['success'=>true, 'appointment'=>$appointment]);
        exit;
    }
    ensureAppointmentSlotSchema($pdo);
    ensureTreatmentPlanWorkflowSchema($pdo);
    $result = managePatientAppointment($pdo, (int)$_SESSION['patient_id'], (int)$_SESSION['user_id'], $input);
    try {
        require_once __DIR__ . '/../../includes/audit_helper.php';
        logAudit($pdo, $_SESSION['user_id'], $_SESSION['username'] ?? '', 'user', $input['action'] === 'cancel' ? 'cancel' : 'update', 'appointments', $result['message'], $result['appointment_id'], 'appointments');
        require_once __DIR__ . '/../../includes/notification_functions.php';
        $title = $input['action'] === 'request_cancel' ? 'Patient Requested Appointment Cancellation' : ($input['action'] === 'edit' ? 'Patient Updated Appointment Request' : 'Patient Cancelled Appointment');
        $message = 'Appointment #' . $result['appointment_id'] . ': ' . $result['message'];
        $suffix = !empty($result['request_id']) ? '?cancellation_request=' . $result['request_id'] : '';
        notifyAdmin($pdo, 'appointment', $title, $message, 'admin_appointments.php' . $suffix);
        notifyStaff($pdo, 'appointment', $title, $message, 'staff_appointments.php' . $suffix);
        notifyDentist($pdo, 'appointment', $title, $message, 'dentist_appointments.php' . $suffix);
    } catch (Throwable $e) { error_log('Patient appointment notification: ' . $e->getMessage()); }
    echo json_encode($result);
} catch (InvalidArgumentException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Patient appointment management: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update this appointment. Please try again.']);
}
