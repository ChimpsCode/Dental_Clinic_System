<?php
session_start();

// Turn off all error display
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

require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Personal Details
    $firstName = trim($_POST['firstName'] ?? '');
    $middleName = trim($_POST['middleName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $fullName = trim($firstName . ' ' . $middleName . ' ' . $lastName . ' ' . $suffix);
    $fullName = preg_replace('/\s+/', ' ', $fullName);
    $fullName = trim($fullName);
    
    $birthdate = $_POST['birthdate'] ?? null;
    $age = $_POST['age'] ?? null;
    $gender = $_POST['gender'] ?? '';
    $religion = trim($_POST['religion'] ?? '');
    
    // Contact Information
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $zipCode = trim($_POST['zipCode'] ?? '');
    $phone = trim($_POST['mobileNumber'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Insurance
    $dentalInsurance = trim($_POST['dentalInsurance'] ?? '');
    $insuranceEffectiveDate = $_POST['effectiveDate'] ?? null;
    
    if (empty($fullName)) {
        throw new Exception('Patient name is required');
    }
    
    // Check if patient already exists
    $stmt = $pdo->prepare("SELECT id FROM patients WHERE first_name = ? AND last_name = ? AND phone = ? LIMIT 1");
    $stmt->execute([$firstName, $lastName, $phone]);
    $existingPatient = $stmt->fetch();
    
    if ($existingPatient) {
        $patientId = $existingPatient['id'];
        $stmt = $pdo->prepare("UPDATE patients SET 
            first_name = ?, middle_name = ?, last_name = ?, suffix = ?,
            date_of_birth = ?, age = ?, gender = ?, religion = ?,
            address = ?, city = ?, province = ?, zip_code = ?, phone = ?, email = ?,
            dental_insurance = ?, insurance_effective_date = ?,
            updated_at = NOW() WHERE id = ?");
        $stmt->execute([$firstName, $middleName, $lastName, $suffix,
            $birthdate, $age, $gender, $religion,
            $address, $city, $province, $zipCode, $phone, $email,
            $dentalInsurance, $insuranceEffectiveDate, $patientId]);
    } else {
        $source = 'walk-in';
        $appointmentId = !empty($_POST['appointmentId']) ? intval($_POST['appointmentId']) : null;
        $inquiryId = !empty($_POST['inquiryId']) ? intval($_POST['inquiryId']) : null;
        
        // Get column names
        $columns = "first_name, middle_name, last_name, suffix, date_of_birth, age, gender, religion, address, city, province, zip_code, phone, email, dental_insurance, insurance_effective_date, status, registration_source, created_at";
        $placeholders = "?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW()";
        
        $stmt = $pdo->prepare("INSERT INTO patients ($columns) VALUES ($placeholders)");
        $stmt->execute([$firstName, $middleName, $lastName, $suffix,
            $birthdate, $age, $gender, $religion,
            $address, $city, $province, $zipCode, $phone, $email,
            $dentalInsurance, $insuranceEffectiveDate, $source]);
        $patientId = $pdo->lastInsertId();
    }
    
    // Link from appointment if applicable
    if ($appointmentId) {
        $stmt = $pdo->prepare("UPDATE appointments SET patient_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$patientId, $appointmentId]);
    }
    
    // Link from inquiry if applicable
    if ($inquiryId) {
        $checkCol = $pdo->query("SHOW COLUMNS FROM inquiries LIKE 'converted_patient_id'");
        if ($checkCol->rowCount() > 0) {
            $stmt = $pdo->prepare("UPDATE inquiries SET converted_patient_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$patientId, $inquiryId]);
        }
    }
    
    // Dental History
    if (!empty($_POST['prevDentist']) || !empty($_POST['lastVisitDate']) || !empty($_POST['reasonLastVisit']) || !empty($_POST['prevTreatments']) || !empty($_POST['complaints'])) {
        $stmt = $pdo->prepare("INSERT INTO dental_history (patient_id, previous_dentist, last_visit_date, reason_last_visit, previous_treatments, chief_complaint, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$patientId, $_POST['prevDentist'] ?? '', $_POST['lastVisitDate'] ?? '', $_POST['reasonLastVisit'] ?? '', $_POST['prevTreatments'] ?? '', $_POST['complaints'] ?? '']);
    }
    
    // Medical History (schema uses medical_conditions + current_medications)
    $medicalConditions = $_POST['medicalConditions'] ?? [];
    $medications = $_POST['medications'] ?? '';
    if (!empty($medications) || !empty($medicalConditions)) {
        $stmt = $pdo->prepare("INSERT INTO medical_history (patient_id, medical_conditions, current_medications, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([
            $patientId,
            is_array($medicalConditions) ? implode(', ', $medicalConditions) : $medicalConditions,
            $medications
        ]);
    }
    
    // Services
    $services = $_POST['services'] ?? [];
    if (empty($services)) {
        $services = [];
    }
    if (is_string($services)) {
        $services = [$services];
    }
    $serviceNames = [];
    foreach ($services as $service) {
        $serviceNames[] = trim($service);
    }
    $treatmentType = !empty($serviceNames) ? implode(', ', $serviceNames) : 'Consultation';
    
    // Selected Teeth
    $selectedTeeth = $_POST['selectedTeeth'] ?? [];
    if (is_string($selectedTeeth)) {
        $selectedTeeth = !empty($selectedTeeth) ? [$selectedTeeth] : [];
    }
    if (!is_array($selectedTeeth)) {
        $selectedTeeth = [];
    }
    $teethNumbers = !empty($selectedTeeth) ? implode(', ', $selectedTeeth) : '';
    
    // Add to Queue
    try {
        $stmt = $pdo->prepare("INSERT INTO queue (patient_id, treatment_type, teeth_numbers, status, priority, queue_time, created_at) VALUES (?, ?, ?, 'waiting', 5, NOW(), NOW())");
        $stmt->execute([$patientId, $treatmentType, $teethNumbers]);
        $queueId = $pdo->lastInsertId();
    } catch (Exception $qErr) {
        // Try without teeth_numbers column
        try {
            $stmt = $pdo->prepare("INSERT INTO queue (patient_id, treatment_type, status, priority, queue_time, created_at) VALUES (?, ?, 'waiting', 5, NOW(), NOW())");
            $stmt->execute([$patientId, $treatmentType]);
            $queueId = $pdo->lastInsertId();
        } catch (Exception $q2) {
            throw new Exception("Failed to add to queue: " . $q2->getMessage());
        }
    }
    
    // Create Billing Record
    $totalAmount = 0;
    $billingNotes = '';
    
    if (!empty($serviceNames)) {
        try {
            $servicesStmt = $pdo->query("SELECT name, price FROM services WHERE is_active = 1");
            $servicesData = $servicesStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            foreach ($serviceNames as $serviceName) {
                foreach ($servicesData as $name => $price) {
                    if (strcasecmp(trim($name), trim($serviceName)) === 0) {
                        $totalAmount += floatval($price);
                        break;
                    }
                }
            }
        } catch (Exception $sErr) {
            // Services table might not exist or have no data
            $totalAmount = 0;
        }
    }
    
    $billingNotes = 'Initial billing from admission - Services: ' . $treatmentType;
    
    try {
        $billingStmt = $pdo->prepare("INSERT INTO billing (patient_id, appointment_id, total_amount, paid_amount, balance, payment_status, billing_date, due_date, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'unpaid', CURDATE(), CURDATE(), ?, NOW(), NOW())");
        $billingStmt->execute([$patientId, $appointmentId, $totalAmount, 0, $totalAmount, $billingNotes]);
        $billingId = $pdo->lastInsertId();
    } catch (Exception $bErr) {
        $billingId = 0;
    }
    
    $pdo->commit();
    
    // Try to send notification (don't break if it fails)
    try {
        require_once __DIR__ . '/includes/notification_functions.php';
        if (function_exists('notifyNewPatient')) {
            $patientName = trim($firstName . ' ' . $lastName);
            notifyNewPatient($pdo, $patientName, $patientId);
        }
    } catch (Exception $notifErr) {
        // Silent fail
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Patient admitted and added to queue successfully!',
        'patient_id' => $patientId,
        'queue_id' => $queueId,
        'billing_id' => $billingId,
        'total_amount' => $totalAmount
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
