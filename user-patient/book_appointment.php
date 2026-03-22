<?php
header('Content-Type: application/json');

session_start();
require_once '../config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $patient_id = $_SESSION['patient_id'] ?? null;
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$patient_id) {
        throw new Exception('Patient not logged in');
    }

    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : null;
    $notes = trim($_POST['notes'] ?? '');

    if (empty($appointment_date)) {
        throw new Exception('Appointment date is required');
    }

    if (empty($appointment_time)) {
        throw new Exception('Appointment time is required');
    }

    $dateObj = DateTime::createFromFormat('Y-m-d', $appointment_date);
    if (!$dateObj) {
        throw new Exception('Invalid date format');
    }

    $dayOfWeek = (int)$dateObj->format('w');
    if ($dayOfWeek === 0) {
        throw new Exception('Clinic is closed on Sundays');
    }

    if ($dateObj < new DateTime('today')) {
        throw new Exception('Cannot book appointments in the past');
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM appointments 
        WHERE appointment_date = ? 
        AND appointment_time = ?
        AND status IN ('scheduled', 'pending', 'confirmed')
    ");
    $stmt->execute([$appointment_date, $appointment_time]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('This time slot is already booked. Please select another time.');
    }

    $treatment = 'General Checkup';
    $duration = 30;
    
    if ($service_id) {
        $stmt = $pdo->prepare("SELECT name, duration_minutes FROM services WHERE id = ? AND is_active = 1");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($service) {
            $treatment = $service['name'];
            $duration = $service['duration_minutes'] ?? 30;
        }
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO appointments (
            patient_id, 
            appointment_date, 
            appointment_time, 
            treatment, 
            notes, 
            status, 
            created_by, 
            created_at
        ) VALUES (?, ?, ?, ?, ?, 'scheduled', ?, NOW())
    ");
    $stmt->execute([
        $patient_id,
        $appointment_date,
        $appointment_time,
        $treatment,
        $notes,
        $user_id ?? 1
    ]);
    
    $appointment_id = $pdo->lastInsertId();

    $pdo->commit();

    $formattedDate = date('F j, Y', strtotime($appointment_date));
    $formattedTime = date('g:i A', strtotime($appointment_time));

    echo json_encode([
        'success' => true,
        'message' => "Appointment booked successfully for {$formattedDate} at {$formattedTime}!",
        'appointment_id' => $appointment_id
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Book Appointment Error: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
