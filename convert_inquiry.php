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
if (!in_array($role, ['staff', 'dentist', 'admin'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Inquiry ID is required']);
    exit();
}

try {
    require_once 'config/database.php';
    
    // Get inquiry details
    $stmt = $pdo->prepare("SELECT * FROM inquiries WHERE id = ?");
    $stmt->execute([$id]);
    $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$inquiry) {
        echo json_encode(['success' => false, 'message' => 'Inquiry not found']);
        exit();
    }
    
    $pdo->beginTransaction();
    
    // Build full name from separate fields
    $firstName = $inquiry['first_name'] ?? '';
    $middleName = $inquiry['middle_name'] ?? '';
    $lastName = $inquiry['last_name'] ?? '';
    $fullName = trim($firstName . ' ' . $middleName . ' ' . $lastName);
    $contactInfo = $inquiry['contact_info'] ?? '';
    
    // Extract phone number from contact_info (assuming format: name, phone or just phone)
    $phone = '';
    if (!empty($contactInfo)) {
        // Try to extract phone number - assume last part or everything with numbers
        if (preg_match('/[\d]{10,}/', $contactInfo, $matches)) {
            $phone = $matches[0];
        } else {
            $phone = $contactInfo;
        }
    }
    
    // Find or create patient (check by first_name + last_name + phone)
    $stmt = $pdo->prepare("SELECT id FROM patients WHERE first_name = ? AND last_name = ? AND phone = ? LIMIT 1");
    $stmt->execute([$firstName, $lastName, $phone]);
    $existing_patient = $stmt->fetch();
    
    if ($existing_patient) {
        $patient_id = $existing_patient['id'];
    } else {
        // Create new patient
        $stmt = $pdo->prepare("INSERT INTO patients (first_name, middle_name, last_name, phone, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$firstName, $middleName, $lastName, $phone]);
        $patient_id = $pdo->lastInsertId();
    }
    
    // Get service name from topic (which is now a service_id foreign key)
    $serviceName = 'General Checkup';
    if (!empty($inquiry['topic'])) {
        $stmt = $pdo->prepare("SELECT name FROM services WHERE id = ? LIMIT 1");
        $stmt->execute([$inquiry['topic']]);
        $service = $stmt->fetch();
        if ($service) {
            $serviceName = $service['name'];
        }
    }
    
    // Create appointment with today's date and default time
    $today = date('Y-m-d');
    $defaultTime = '09:00:00';
    $treatment = $serviceName;
    $notes = 'Created from inquiry. Source: ' . ($inquiry['source'] ?? 'Unknown');
    
    // Include patient name fields in the appointment
    $stmt = $pdo->prepare("INSERT INTO appointments (patient_id, first_name, middle_name, last_name, appointment_date, appointment_time, treatment, notes, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, NOW())");
    $stmt->execute([$patient_id, $firstName, $middleName, $lastName, $today, $defaultTime, $treatment, $notes, $_SESSION['user_id'] ?? 1]);
    
    $appointment_id = $pdo->lastInsertId();
    
    // Update inquiry status to Booked
    $stmt = $pdo->prepare("UPDATE inquiries SET status = 'Booked' WHERE id = ?");
    $stmt->execute([$id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Successfully converted to appointment!',
        'appointment_id' => $appointment_id
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Convert Inquiry Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>