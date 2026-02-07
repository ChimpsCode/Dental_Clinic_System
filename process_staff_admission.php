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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    require_once 'config/database.php';
    
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
    $email = trim($_POST['emailAddress'] ?? '');
    
    // Insurance
    $dentalInsurance = trim($_POST['dentalInsurance'] ?? '');
    $insuranceEffectiveDate = $_POST['effectiveDate'] ?? null;
    
    if (empty($fullName)) {
        throw new Exception('Patient name is required');
    }
    
    // Check if patient already exists (by first_name, last_name, and phone)
    $stmt = $pdo->prepare("SELECT id FROM patients WHERE first_name = ? AND last_name = ? AND phone = ? LIMIT 1");
    $stmt->execute([$firstName, $lastName, $phone]);
    $existingPatient = $stmt->fetch();
    
    if ($existingPatient) {
        $patientId = $existingPatient['id'];
        // Update existing patient
        $stmt = $pdo->prepare("UPDATE patients SET 
            first_name = ?, middle_name = ?, last_name = ?, suffix = ?,
            date_of_birth = ?, age = ?, gender = ?, religion = ?,
            address = ?, city = ?, province = ?, zip_code = ?,
            phone = ?, email = ?, dental_insurance = ?, insurance_effective_date = ?,
            updated_at = NOW()
            WHERE id = ?");
        $stmt->execute([$firstName, $middleName, $lastName, $suffix, $birthdate, $age, $gender, $religion,
            $address, $city, $province, $zipCode, $phone, $email, $dentalInsurance, $insuranceEffectiveDate, $patientId]);
    } else {
        // Check if registration_source column exists
        $checkCol = $pdo->query("SHOW COLUMNS FROM patients LIKE 'registration_source'");
        $hasSourceColumn = $checkCol->rowCount() > 0;
        
        // Check if this came from an appointment
        $appointmentId = $_POST['appointment_id'] ?? null;
        $inquiryId = $_POST['inquiry_id'] ?? null;
        $source = 'direct';
        
        if ($appointmentId || (isset($_POST['source']) && $_POST['source'] === 'appointment')) {
            $source = 'appointment_converted';
        }
        
        // Insert new patient with source tracking
        if ($hasSourceColumn) {
            $stmt = $pdo->prepare("INSERT INTO patients (
                first_name, middle_name, last_name, suffix,
                date_of_birth, age, gender, religion,
                address, city, province, zip_code, phone, email,
                dental_insurance, insurance_effective_date, status, registration_source, source_appointment_id, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, NOW())");
            $stmt->execute([$firstName, $middleName, $lastName, $suffix,
                $birthdate, $age, $gender, $religion,
                $address, $city, $province, $zipCode, $phone, $email,
                $dentalInsurance, $insuranceEffectiveDate, $source, $appointmentId]);
        } else {
            // Fallback for older database without source column
            $stmt = $pdo->prepare("INSERT INTO patients (
                first_name, middle_name, last_name, suffix,
                date_of_birth, age, gender, religion,
                address, city, province, zip_code, phone, email,
                dental_insurance, insurance_effective_date, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
            $stmt->execute([$firstName, $middleName, $lastName, $suffix,
                $birthdate, $age, $gender, $religion,
                $address, $city, $province, $zipCode, $phone, $email,
                $dentalInsurance, $insuranceEffectiveDate]);
        }
        $patientId = $pdo->lastInsertId();
        
        // If this came from an appointment, link it to the new patient
        if ($appointmentId) {
            // Update appointment with the new patient_id
            $stmt = $pdo->prepare("UPDATE appointments SET 
                patient_id = ?,
                updated_at = NOW()
                WHERE id = ?");
            $stmt->execute([$patientId, $appointmentId]);
            
            // Check if appointment tracking columns exist
            $checkApptCol = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'is_converted_to_patient'");
            if ($checkApptCol->rowCount() > 0) {
                $stmt = $pdo->prepare("UPDATE appointments SET 
                    is_converted_to_patient = 1, 
                    converted_patient_id = ?
                    WHERE id = ?");
                $stmt->execute([$patientId, $appointmentId]);
            }
        }
        
        // If this came from an inquiry, link it to the new patient
        if ($inquiryId) {
            // Check if converted_patient_id column exists in inquiries
            $checkInquiryCol = $pdo->query("SHOW COLUMNS FROM inquiries LIKE 'converted_patient_id'");
            if ($checkInquiryCol->rowCount() > 0) {
                $stmt = $pdo->prepare("UPDATE inquiries SET 
                    converted_patient_id = ?,
                    status = 'New Admission',
                    updated_at = NOW()
                    WHERE id = ?");
                $stmt->execute([$patientId, $inquiryId]);
            }
        }
    }
    
    // Dental History
    $prevDentist = trim($_POST['prevDentist'] ?? '');
    $lastVisitDate = $_POST['lastVisitDate'] ?? null;
    $reasonLastVisit = trim($_POST['reasonLastVisit'] ?? '');
    $prevTreatments = trim($_POST['prevTreatments'] ?? '');
    $complaints = trim($_POST['complaints'] ?? '');
    
    if (!empty($prevDentist) || !empty($lastVisitDate) || !empty($reasonLastVisit) || !empty($prevTreatments) || !empty($complaints)) {
        $stmt = $pdo->prepare("INSERT INTO dental_history (
            patient_id, previous_dentist, last_visit_date, reason_last_visit,
            previous_treatments, current_complaints, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$patientId, $prevDentist, $lastVisitDate, $reasonLastVisit, $prevTreatments, $complaints]);
    }
    
    // Medical History
    $medications = trim($_POST['medications'] ?? '');
    $medicalConditions = $_POST['medicalConditions'] ?? [];
    // Ensure medicalConditions is always an array
    if (!is_array($medicalConditions)) {
        $medicalConditions = !empty($medicalConditions) ? [$medicalConditions] : [];
    }
    $allergies = in_array('allergies', $medicalConditions) ? 'Yes' : 'No';
    $diabetes = in_array('diabetes', $medicalConditions) ? 'Yes' : 'No';
    $heartDisease = in_array('heart_disease', $medicalConditions) ? 'Yes' : 'No';
    $highBP = in_array('high_bp', $medicalConditions) ? 'Yes' : 'No';
    $asthma = in_array('asthma', $medicalConditions) ? 'Yes' : 'No';
    $surgery = in_array('surgery', $medicalConditions) ? 'Yes' : 'No';
    
    if (!empty($medications) || !empty($medicalConditions)) {
        $stmt = $pdo->prepare("INSERT INTO medical_history (
            patient_id, allergies, current_medications, medical_conditions,
            created_at
        ) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$patientId, $allergies, $medications, implode(', ', $medicalConditions)]);
    }
    
    // Services/Treatment - Services are now stored in database, values sent as service names
    $services = $_POST['services'] ?? [];
    $serviceNames = [];
    foreach ($services as $service) {
        $serviceNames[] = trim($service);
    }
    $treatmentType = !empty($serviceNames) ? implode(', ', $serviceNames) : 'Consultation';
    
    // Selected Teeth
    $selectedTeeth = $_POST['selectedTeeth'] ?? [];
    $teethNumbers = !empty($selectedTeeth) ? implode(', ', $selectedTeeth) : '';
    
    // Add to Queue (waiting status)
    $stmt = $pdo->prepare("INSERT INTO queue (
        patient_id, treatment_type, teeth_numbers, status, priority, queue_time, created_at
    ) VALUES (?, ?, ?, 'waiting', 5, NOW(), NOW())");
    $stmt->execute([$patientId, $treatmentType, $teethNumbers]);
    $queueId = $pdo->lastInsertId();
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Patient admitted and added to queue successfully!',
        'patient_id' => $patientId,
        'queue_id' => $queueId
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Admission Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
