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

$action = $_POST['action'] ?? 'create';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    require_once '../../config/database.php';
    
    if ($action === 'edit') {
        // EDIT EXISTING APPOINTMENT
        $appointment_id = $_POST['id'] ?? $_POST['appointment_id'] ?? null;
        
        if (!$appointment_id) {
            echo json_encode(['success' => false, 'message' => 'Appointment ID is required']);
            exit();
        }
        
        // Get form data
        $first_name = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $appointment_date = $_POST['appointment_date'] ?? '';
        $appointment_time = $_POST['appointment_time'] ?? '';
        $treatment = $_POST['treatment'] ?? 'Consultation';
        $notes = trim($_POST['notes'] ?? '');
        $status = $_POST['status'] ?? 'scheduled';
        
        // Validate required fields
        if (empty($first_name) || empty($last_name) || empty($phone) || empty($appointment_date) || empty($appointment_time)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
            exit();
        }
        
        // Update appointment
        $stmt = $pdo->prepare("UPDATE appointments SET 
            first_name = ?, 
            middle_name = ?, 
            last_name = ?, 
            phone = ?,
            email = COALESCE(NULLIF(?, ''), email),
            appointment_date = ?, 
            appointment_time = ?, 
            treatment = ?, 
            notes = ?, 
            status = ?,
            updated_at = NOW() 
            WHERE id = ?");
        $stmt->execute([
            $first_name,
            $middle_name,
            $last_name,
            $phone,
            $email,
            $appointment_date,
            $appointment_time,
            $treatment,
            $notes,
            $status,
            $appointment_id
        ]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Appointment updated successfully',
            'appointment_id' => $appointment_id
        ]);
        exit();
    }
    
    // CREATE NEW APPOINTMENT (existing code)
    // Get form data
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $treatment = $_POST['treatment'] ?? 'Consultation';
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
        
        // CHECK: Prevent double booking - patient can't have appointment same day
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM appointments 
            WHERE patient_id = ? 
            AND DATE(appointment_date) = ?
            AND status_id IN (1, 2)
            AND (status = 'scheduled' OR status = 'in_queue' OR status IS NULL)
        ");
        $checkStmt->execute([$patient_id, $appointment_date]);
        $activeCount = $checkStmt->fetchColumn();
        
        if ($activeCount > 0) {
            echo json_encode(['success' => false, 'message' => 'This patient already has an active appointment scheduled for ' . $appointment_date . '.']);
            exit();
        }
        
        // Also check if patient is in queue
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM queue WHERE patient_id = ? AND status_id IN (1, 2)");
        $checkStmt->execute([$patient_id]);
        $inQueueCount = $checkStmt->fetchColumn();
        
        if ($inQueueCount > 0) {
            echo json_encode(['success' => false, 'message' => 'This patient is already in the dental queue.']);
            exit();
        }
        
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
    
    // Always generate unique reference_code 
    $refCode = 'DENT-' . strtoupper(substr(uniqid(), -6));
    
    // Create appointment
    $stmt = $pdo->prepare("INSERT INTO appointments 
        (patient_id, appointment_date, appointment_time, service_id, notes, status, status_id, created_by, reference_code, created_at) 
        VALUES (?, ?, ?, ?, ?, 'scheduled', 1, ?, ?, NOW())");
    
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
        $_SESSION['user_id'] ?? null,
        $refCode
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