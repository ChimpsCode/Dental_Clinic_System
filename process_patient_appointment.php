<?php
header('Content-Type: application/json');

session_start();
require_once 'config/database.php';

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

    // Create the appointment
    $stmt = $pdo->prepare("INSERT INTO appointments (patient_id, appointment_date, appointment_time, treatment, notes, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, 'scheduled', ?, NOW())");
    $stmt->execute([$patient_id, $appointment_date, $appointment_time, $treatment, $notes, $_SESSION['user_id'] ?? 1]);
    
    $appointment_id = $pdo->lastInsertId();

    // Update or create queue entry with 'scheduled' status
    $stmt = $pdo->prepare("SELECT id FROM queue WHERE patient_id = ? AND status IN ('waiting', 'in_procedure') ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$patient_id]);
    $existingQueue = $stmt->fetch();

    if ($existingQueue) {
        // Update existing queue entry
        $stmt = $pdo->prepare("UPDATE queue SET status = 'scheduled', treatment_type = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$treatment, $existingQueue['id']]);
    } else {
        // Create new queue entry with 'scheduled' status
        $stmt = $pdo->prepare("INSERT INTO queue (patient_id, treatment_type, status, created_by, created_at) VALUES (?, ?, 'scheduled', ?, NOW())");
        $stmt->execute([$patient_id, $treatment, $_SESSION['user_id'] ?? 1]);
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
