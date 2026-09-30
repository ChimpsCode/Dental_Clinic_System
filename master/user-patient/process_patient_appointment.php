<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/session_bootstrap.php';
require_once '../../config/database.php';
require_once '../../includes/appointment_approval_helper.php';

$revoked = app_session_revoked_response();
if ($revoked !== null) {
    echo json_encode($revoked);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $sessionRole = strtolower((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? ''));
    $patient_id = (int)($_SESSION['patient_id'] ?? 0);
    if (empty($_SESSION['user_id']) || $patient_id <= 0 || !in_array($sessionRole, ['user', 'patient'], true)) {
        throw new Exception('Unauthorized access');
    }

    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $treatment = $_POST['treatment'] ?? 'General Checkup';
    $notes = trim($_POST['notes'] ?? '');

    if (empty($appointment_date)) {
        throw new Exception('Appointment date is required');
    }

    if (empty($appointment_time)) {
        throw new Exception('Appointment time is required');
    }

    $pdo->beginTransaction();
    
    // CHECK: Patient can only have one pending approval request at a time
    if (patientHasPendingAppointment($pdo, (int)$patient_id)) {
        throw new Exception('This patient already has a booking request pending staff approval.');
    }

    // CHECK: Prevent double booking - patient can't have appointment same day
    if (patientHasActiveAppointmentOnDate($pdo, (int)$patient_id, $appointment_date)) {
        throw new Exception('You already have an appointment scheduled for ' . $appointment_date . '. Please choose a different date.');
    }

    // Create the appointment
    $stmt = $pdo->prepare("INSERT INTO appointments 
        (patient_id, appointment_date, appointment_time, service_id, notes, status, created_by, created_at) 
        VALUES (?, ?, ?, ?, ?, 'Pending Approval', ?, NOW())");
    
    // Resolve service_id
    $service_id = null;
    try {
        $svcStmt = $pdo->prepare("SELECT id FROM services WHERE name = ? LIMIT 1");
        $svcStmt->execute([$treatment]);
        $service_id = $svcStmt->fetchColumn() ?: null;
    } catch (Exception $e) {}

    $stmt->execute([
        $patient_id, 
        $appointment_date, 
        $appointment_time, 
        $service_id,
        $notes, 
        $_SESSION['user_id'] ?? 1
    ]);
    
    $appointment_id = $pdo->lastInsertId();

    // Check if patient should transition from New Patient to Returning Patient
    try {
        require_once __DIR__ . '/../../includes/patient_type_helper.php';
        maybeTransitionToReturningPatient($pdo, $patient_id);
    } catch (Exception $e) {
        // Silent fail - non-critical
    }

    $pdo->commit();

    // Notify staff/admin that a new request is waiting for approval
    try {
        require_once __DIR__ . '/../../includes/notification_functions.php';
        $patStmt = $pdo->prepare("SELECT first_name, last_name FROM patients WHERE id = ? LIMIT 1");
        $patStmt->execute([$patient_id]);
        $pat = $patStmt->fetch(PDO::FETCH_ASSOC);
        $patientName = $pat ? trim($pat['first_name'] . ' ' . $pat['last_name']) : 'Patient';
        $formattedDate = date('F j, Y', strtotime($appointment_date));
        $formattedTime = date('g:i A', strtotime($appointment_time));
        notifyAppointmentPendingApproval($pdo, $patientName, $formattedDate, $formattedTime, $treatment);
    } catch (Exception $notifErr) {
        // Silent fail - don't break appointment creation
    }

    echo json_encode([
        'success' => true,
        'message' => 'Appointment request submitted. It will be confirmed once approved by staff.',
        'appointment_id' => $appointment_id
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Patient Appointment Error: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
