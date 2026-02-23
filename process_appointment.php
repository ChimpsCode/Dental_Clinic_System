<?php
session_start();

error_reporting(0);
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

require_once __DIR__ . '/config/database.php';

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

    // Check if is_archived column exists, if not create it
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'is_archived'");
        if ($checkCol->rowCount() == 0) {
            $pdo->exec("ALTER TABLE appointments ADD COLUMN is_archived TINYINT(1) DEFAULT 0");
        }
    } catch (Exception $e) {
        // Silent fail - column might already exist
    }
    
    $stmt = $pdo->prepare("INSERT INTO appointments (first_name, middle_name, last_name, patient_id, appointment_date, appointment_time, treatment, notes, status, created_by, created_at, is_archived) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, NOW(), 0)");
    $result = $stmt->execute([$first_name, $middle_name, $last_name, $patient_id, $appointment_date, $appointment_time, $treatment, $notes, $_SESSION['user_id'] ?? 1]);
    
    if (!$result) {
        throw new Exception("Failed to insert appointment. PDO Error: " . implode(", ", $stmt->errorInfo()));
    }
    
    $appointment_id = $pdo->lastInsertId();
    
    if (!$appointment_id) {
        throw new Exception("Failed to get appointment ID");
    }

    $pdo->commit();
    
    // Send notifications for new appointment
    try {
        require_once __DIR__ . '/includes/notification_functions.php';
        $patientName = trim($first_name . ' ' . $last_name);
        $formattedDate = date('F j, Y', strtotime($appointment_date));
        notifyNewAppointment($pdo, $patientName, $formattedDate);
    } catch (Exception $notifErr) {
        // Silent fail - don't break appointment creation
    } catch (Error $notifErr) {
        // Silent fail - don't break appointment creation
    }

    echo json_encode([
        'success' => true,
        'message' => 'Appointment scheduled successfully!'
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $errorMsg = $e->getMessage();
    error_log("Appointment Error: " . $errorMsg);

    echo json_encode([
        'success' => false,
        'message' => $errorMsg
    ]);
}
