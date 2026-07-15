<?php
header('Content-Type: application/json');

session_start();
require_once '../../config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $patient_id = $_POST['patient_id'] ?? null;
    $patient_phone = $_POST['patient_phone'] ?? '';
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $treatment = $_POST['treatment'] ?? 'General Checkup';
    $notes = trim($_POST['notes'] ?? '');

    if (empty($patient_id)) {
        throw new Exception('Patient ID is required');
    }

    if (empty($appointment_date)) {
        throw new Exception('Appointment date is required');
    }

    if (empty($appointment_time)) {
        throw new Exception('Appointment time is required');
    }

    $pdo->beginTransaction();
    
    // CHECK: Prevent double booking - patient can't have appointment same day
    $checkStmt = $pdo->prepare("
        SELECT COUNT(*) FROM appointments 
        WHERE patient_id = ? 
        AND DATE(appointment_date) = ?
        AND status IN ('Scheduled', 'In Queue')
    ");
    $checkStmt->execute([$patient_id, $appointment_date]);
    $activeCount = $checkStmt->fetchColumn();
    
    if ($activeCount > 0) {
        throw new Exception('You already have an appointment scheduled for ' . $appointment_date . '. Please choose a different date.');
    }

    // Create the appointment
    $stmt = $pdo->prepare("INSERT INTO appointments 
        (patient_id, appointment_date, appointment_time, service_id, notes, status, created_by, created_at) 
        VALUES (?, ?, ?, ?, ?, 'Scheduled', ?, NOW())");
    
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

    echo json_encode([
        'success' => true,
        'message' => 'Appointment scheduled successfully!',
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
