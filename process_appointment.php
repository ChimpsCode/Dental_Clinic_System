<?php
header('Content-Type: application/json');

session_start();
require_once 'config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $treatment = $_POST['treatment'] ?? 'General Checkup';
    $notes = trim($_POST['notes'] ?? '');

    if (empty($first_name)) {
        throw new Exception('First name is required');
    }

    if (empty($last_name)) {
        throw new Exception('Last name is required');
    }

    if (empty($appointment_date)) {
        throw new Exception('Appointment date is required');
    }

    if (empty($appointment_time)) {
        throw new Exception('Appointment time is required');
    }

    $pdo->beginTransaction();

    $patient_id = null;

    // Search for existing patient by first name and last name
    $stmt = $pdo->prepare("SELECT id FROM patients WHERE first_name = ? AND last_name = ? LIMIT 1");
    $stmt->execute([$first_name, $last_name]);
    $existing_patient = $stmt->fetch();

    if ($existing_patient) {
        // Existing patient - link the appointment
        $patient_id = $existing_patient['id'];
    }
    // Note: For NEW patients, patient_id remains NULL
    // They will only be added to patients table after completing New Admission

    $stmt = $pdo->prepare("INSERT INTO appointments (first_name, middle_name, last_name, patient_id, appointment_date, appointment_time, treatment, notes, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, NOW())");
    $stmt->execute([$first_name, $middle_name, $last_name, $patient_id, $appointment_date, $appointment_time, $treatment, $notes, $_SESSION['user_id'] ?? 1]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Appointment scheduled successfully!'
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Appointment Error: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
