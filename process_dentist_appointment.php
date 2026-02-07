<?php
ob_start();
session_start();

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['dentist', 'admin'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    require_once 'config/database.php';
    
    // Get form data
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $treatment = $_POST['treatment'] ?? 'General Checkup';
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($phone) || empty($appointment_date) || empty($appointment_time)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        exit();
    }
    
    // Check if patient already exists by phone
    $stmt = $pdo->prepare("SELECT id FROM patients WHERE phone = ? LIMIT 1");
    $stmt->execute([$phone]);
    $existingPatient = $stmt->fetch();
    
    if ($existingPatient) {
        $patient_id = $existingPatient['id'];
        
        // Update patient info if needed
        $stmt = $pdo->prepare("UPDATE patients SET 
            first_name = ?, 
            middle_name = ?, 
            last_name = ?, 
            email = COALESCE(NULLIF(?, ''), email),
            updated_at = NOW() 
            WHERE id = ?");
        $stmt->execute([$first_name, $middle_name, $last_name, $email, $patient_id]);
    } else {
        // Create new patient
        $stmt = $pdo->prepare("INSERT INTO patients (first_name, middle_name, last_name, phone, email, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$first_name, $middle_name, $last_name, $phone, $email]);
        $patient_id = $pdo->lastInsertId();
    }
    
    // Create appointment
    $stmt = $pdo->prepare("INSERT INTO appointments 
        (patient_id, first_name, middle_name, last_name, appointment_date, appointment_time, treatment, notes, status, created_by, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, NOW())");
    $stmt->execute([
        $patient_id,
        $first_name,
        $middle_name,
        $last_name,
        $appointment_date,
        $appointment_time,
        $treatment,
        $notes,
        $_SESSION['user_id']
    ]);
    
    $appointment_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Appointment scheduled successfully',
        'appointment_id' => $appointment_id
    ]);
    
} catch (Exception $e) {
    error_log("Dentist Appointment Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>