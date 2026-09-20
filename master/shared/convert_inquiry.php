<?php
ob_start();
require_once __DIR__ . '/../../includes/session_bootstrap.php';
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
    require_once '../../config/database.php';
    require_once '../../includes/appointment_approval_helper.php';
    
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
    $createdNewPatient = false;
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
        $createdNewPatient = true;
    }

    // CHECK: Patient can only have one pending approval request at a time
    if (patientHasPendingAppointment($pdo, (int)$patient_id)) {
        throw new Exception('This patient already has a booking request pending staff approval.');
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
    
    // Resolve service_id
    $service_id = null;
    try {
        $svcStmt = $pdo->prepare("SELECT id FROM services WHERE name = ? LIMIT 1");
        $svcStmt->execute([$treatment]);
        $service_id = $svcStmt->fetchColumn() ?: null;
    } catch (Exception $e) {}
    
    // Check if dentist_id column exists
    $checkCol = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'dentist_id'");
    $hasDentistCol = $checkCol->rowCount() > 0;

    // Build INSERT dynamically
    $columns = ['patient_id', 'appointment_date', 'appointment_time', 'service_id', 'notes', 'status', 'created_by', 'created_at'];
    $placeholders = ['?', '?', '?', '?', '?', "'Scheduled'", '?', 'NOW()'];
    $values = [$patient_id, $today, $defaultTime, $service_id, $notes, $_SESSION['user_id'] ?? 1];

    if ($hasDentistCol) {
        $columns[] = 'dentist_id';
        $placeholders[] = '?';
        $values[] = (int)$_SESSION['user_id'];
    }

    $stmt = $pdo->prepare("INSERT INTO appointments (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")");
    $stmt->execute($values);
    
    $appointment_id = $pdo->lastInsertId();
    
    // Check if patient should transition from New Patient to Returning Patient
    try {
        require_once __DIR__ . '/../../includes/patient_type_helper.php';
        maybeTransitionToReturningPatient($pdo, $patient_id);
    } catch (Exception $e) {
        // Silent fail - non-critical
    }
    
    // Update inquiry status to Booked
    $stmt = $pdo->prepare("UPDATE inquiries SET status = 'Booked' WHERE id = ?");
    $stmt->execute([$id]);
    
    $pdo->commit();

    if (!empty($createdNewPatient)) {
        try {
            require_once __DIR__ . '/../../includes/patient_list_overflow_helper.php';
            runPatientListOverflow($pdo);
        } catch (Exception $overflowErr) {
        }
    }
    
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