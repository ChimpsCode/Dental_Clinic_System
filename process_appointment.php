<?php
header('Content-Type: application/json');

session_start();
require_once 'config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $patient_name = trim($_POST['patient_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $treatment = $_POST['treatment'] ?? 'General Checkup';
    $notes = trim($_POST['notes'] ?? '');

    if (empty($patient_name)) {
        throw new Exception('Patient name is required');
    }

    if (empty($appointment_date)) {
        throw new Exception('Appointment date is required');
    }

    if (empty($appointment_time)) {
        throw new Exception('Appointment time is required');
    }

    $pdo->beginTransaction();

    $patient_id = null;

    $stmt = $pdo->prepare("SELECT id FROM patients WHERE full_name = ? LIMIT 1");
    $stmt->execute([$patient_name]);
    $existing_patient = $stmt->fetch();

    if ($existing_patient) {
        $patient_id = $existing_patient['id'];
    } else {
        $parts = explode(' ', $patient_name, 2);
        $first_name = $parts[0];
        $last_name = $parts[1] ?? '';

        $stmt = $pdo->prepare("INSERT INTO patients (first_name, last_name, full_name, phone, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$first_name, $last_name, $patient_name, $phone]);
        $patient_id = $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare("INSERT INTO appointments (patient_id, appointment_date, appointment_time, treatment, notes, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, 'scheduled', ?, NOW())");
    $stmt->execute([$patient_id, $appointment_date, $appointment_time, $treatment, $notes, $_SESSION['user_id'] ?? 1]);

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
