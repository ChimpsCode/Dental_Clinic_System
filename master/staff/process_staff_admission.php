<?php
session_start();

// Clean any previous output
ob_clean();

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

require_once __DIR__ . '/../../config/database.php';

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
        
        // Get column names from database dynamically
        $cols = $pdo->query("DESCRIBE patients");
        $availableColumns = array_column($cols->fetchAll(), 'Field');
        
        $updateParts = [];
        $updateValues = [];
        
        $updateMap = [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'suffix' => $suffix,
            'date_of_birth' => $birthdate,
            'gender' => $gender,
            'phone' => $phone,
            'email' => $email,
        ];
        
        foreach ($updateMap as $col => $val) {
            if (in_array($col, $availableColumns)) {
                $updateParts[] = "$col = ?";
                $updateValues[] = $val;
            }
        }
        
        if (in_array('updated_at', $availableColumns)) {
            $updateParts[] = 'updated_at = NOW()';
        }
        
        $updateValues[] = $patientId;
        $stmt = $pdo->prepare("UPDATE patients SET " . implode(', ', $updateParts) . " WHERE id = ?");
        $stmt->execute($updateValues);
    } else {
        $source = 'walk-in';
        $appointmentId = !empty($_POST['appointmentId']) ? intval($_POST['appointmentId']) : null;
        $inquiryId = !empty($_POST['inquiryId']) ? intval($_POST['inquiryId']) : null;
        
        // Get column names from database dynamically
        $cols = $pdo->query("DESCRIBE patients");
        $availableColumns = array_column($cols->fetchAll(), 'Field');
        
        // Build column list based on available columns
        $colList = [];
        $values = [];
        
        $fieldMap = [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'suffix' => $suffix,
            'date_of_birth' => $birthdate,
            'gender' => $gender,
            'phone' => $phone,
            'email' => $email,
        ];
        
        foreach ($fieldMap as $col => $val) {
            if (in_array($col, $availableColumns)) {
                $colList[] = $col;
                $values[] = $val;
            }
        }
        
        if (in_array('registration_source_id', $availableColumns)) {
            // Check if inquiry_sources table exists and has data
            try {
                $sourceCount = $pdo->query("SELECT COUNT(*) FROM inquiry_sources")->fetchColumn();
                if ($sourceCount > 0) {
                    $sourceStmt = $pdo->query("SELECT id FROM inquiry_sources ORDER BY id LIMIT 1");
                    $validSourceId = $sourceStmt->fetchColumn();
                    if ($validSourceId) {
                        $colList[] = 'registration_source_id';
                        $values[] = $validSourceId;
                    }
                }
            } catch (Exception $srcErr) {
                // inquiry_sources table might not exist or have data, skip this field
            }
        }
        
        $columns = implode(', ', $colList);
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        
        $stmt = $pdo->prepare("INSERT INTO patients ($columns) VALUES ($placeholders)");
        $stmt->execute($values);
        $patientId = $pdo->lastInsertId();
    }
    
    // Link from appointment if applicable
    if ($appointmentId) {
        $apptCols = $pdo->query("DESCRIBE appointments");
        $apptAvailable = array_column($apptCols->fetchAll(), 'Field');
        
        $apptUpdates = ['patient_id = ?'];
        $apptValues = [$patientId];
        
        if (in_array('status', $apptAvailable)) {
            $apptUpdates[] = "status = 'In Queue'";
        }
        if (in_array('status_id', $apptAvailable)) {
            $apptUpdates[] = 'status_id = 2';
        }
        if (in_array('updated_at', $apptAvailable)) {
            $apptUpdates[] = 'updated_at = NOW()';
        }
        
        $apptValues[] = $appointmentId;
        $stmt = $pdo->prepare("UPDATE appointments SET " . implode(', ', $apptUpdates) . " WHERE id = ?");
        $stmt->execute($apptValues);
    }
    
    // Link from inquiry if applicable
    if ($inquiryId) {
        $inqCols = $pdo->query("DESCRIBE inquiries");
        $inqAvailable = array_column($inqCols->fetchAll(), 'Field');
        
        $inqUpdates = ['converted_patient_id = ?'];
        $inqValues = [$patientId];
        
        if (in_array('updated_at', $inqAvailable)) {
            $inqUpdates[] = 'updated_at = NOW()';
        }
        
        $inqValues[] = $inquiryId;
        $stmt = $pdo->prepare("UPDATE inquiries SET " . implode(', ', $inqUpdates) . " WHERE id = ?");
        $stmt->execute($inqValues);
    }
    
    // Dental History - use INSERT IGNORE to handle duplicate constraint
    if (!empty($_POST['prevDentist']) || !empty($_POST['lastVisitDate']) || !empty($_POST['reasonLastVisit']) || !empty($_POST['prevTreatments']) || !empty($_POST['complaints'])) {
        try {
            $dhCols = $pdo->query("DESCRIBE dental_history");
            $dhAvailable = array_column($dhCols->fetchAll(), 'Field');
            
            $dhColList = ['patient_id'];
            $dhValues = [$patientId];
            
            if (in_array('previous_dentist', $dhAvailable)) { $dhColList[] = 'previous_dentist'; $dhValues[] = $_POST['prevDentist'] ?? ''; }
            if (in_array('last_visit_date', $dhAvailable)) { $dhColList[] = 'last_visit_date'; $dhValues[] = $_POST['lastVisitDate'] ?? ''; }
            if (in_array('reason_last_visit', $dhAvailable)) { $dhColList[] = 'reason_last_visit'; $dhValues[] = $_POST['reasonLastVisit'] ?? ''; }
            if (in_array('previous_treatments', $dhAvailable)) { $dhColList[] = 'previous_treatments'; $dhValues[] = $_POST['prevTreatments'] ?? ''; }
            if (in_array('chief_complaint', $dhAvailable)) { $dhColList[] = 'chief_complaint'; $dhValues[] = $_POST['complaints'] ?? ''; }
            
            $dhColsStr = implode(', ', $dhColList);
            $dhPlaceholders = implode(', ', array_fill(0, count($dhValues), '?'));
            $stmt = $pdo->prepare("INSERT IGNORE INTO dental_history ($dhColsStr) VALUES ($dhPlaceholders)");
            $stmt->execute($dhValues);
        } catch (Exception $dhErr) {
            // Skip if duplicate - dental history might already exist
            error_log("Dental history skipped: " . $dhErr->getMessage());
        }
    }
    
    // Medical History - use INSERT IGNORE
    $medicalConditions = $_POST['medicalConditions'] ?? [];
    $medications = $_POST['medications'] ?? '';
    if (!empty($medications) || !empty($medicalConditions)) {
        try {
            $mhCols = $pdo->query("DESCRIBE medical_history");
            $mhAvailable = array_column($mhCols->fetchAll(), 'Field');
            
            $mhColList = ['patient_id'];
            $mhValues = [$patientId];
            
            if (in_array('medical_conditions', $mhAvailable)) { $mhColList[] = 'medical_conditions'; $mhValues[] = is_array($medicalConditions) ? implode(', ', $medicalConditions) : $medicalConditions; }
            if (in_array('current_medications', $mhAvailable)) { $mhColList[] = 'current_medications'; $mhValues[] = $medications; }
            
            $mhColsStr = implode(', ', $mhColList);
            $mhPlaceholders = implode(', ', array_fill(0, count($mhValues), '?'));
            $stmt = $pdo->prepare("INSERT IGNORE INTO medical_history ($mhColsStr) VALUES ($mhPlaceholders)");
            $stmt->execute($mhValues);
        } catch (Exception $mhErr) {
            // Skip if duplicate - medical history might already exist
            error_log("Medical history skipped: " . $mhErr->getMessage());
        }
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
    
    // Add to Queue - moved AFTER billing commit
    $queueId = 0;
    try {
        // Check if patient is already in an active queue
        $checkQueue = $pdo->prepare("SELECT id FROM queue WHERE patient_id = ? AND status_id = 1 AND is_archived = 0 LIMIT 1");
        $checkQueue->execute([$patientId]);
        $existingQueue = $checkQueue->fetch();
        
        if ($existingQueue) {
            $queueId = $existingQueue['id'];
            $stmt = $pdo->prepare("UPDATE queue SET treatment_type = ?, queue_time = NOW() WHERE id = ?");
            $stmt->execute([$treatmentType, $queueId]);
        } else {
            // Insert new queue entry
            $stmt = $pdo->prepare("INSERT INTO queue (patient_id, status_id, priority, treatment_type, queue_time, created_at) VALUES (?, 1, 5, ?, NOW(), NOW())");
            $stmt->execute([$patientId, $treatmentType]);
            $queueId = (int)$pdo->lastInsertId();
        }
        
        error_log("Queue insert: patientId=$patientId, treatmentType=$treatmentType, queueId=$queueId");
    } catch (Exception $qErr) {
        error_log("Queue insert ERROR: " . $qErr->getMessage());
        $queueId = 0;
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
        $bCols = $pdo->query("DESCRIBE billing");
        $bAvailable = array_column($bCols->fetchAll(), 'Field');
        
        $bColList = [];
        $bValues = [];
        $bSetClauses = [];
        
        $bFieldMap = [
            'patient_id' => $patientId,
            'appointment_id' => $appointmentId,
            'total_amount' => $totalAmount,
            'paid_amount' => 0,
            'balance' => $totalAmount,
            'payment_status' => 'unpaid',
            'notes' => $billingNotes,
        ];
        
        foreach ($bFieldMap as $col => $val) {
            if (in_array($col, $bAvailable)) {
                $bColList[] = $col;
                $bValues[] = $val;
            }
        }
        
        $bColsStr = implode(', ', $bColList);
        $bPlaceholders = implode(', ', array_fill(0, count($bValues), '?'));
        
        $billingSql = "INSERT INTO billing ($bColsStr, billing_date, due_date) VALUES ($bPlaceholders, CURDATE(), CURDATE())";
        
        $stmt = $pdo->prepare($billingSql);
        $stmt->execute($bValues);
        $billingId = $pdo->lastInsertId();
    } catch (Exception $bErr) {
        $billingId = 0;
    }
    
    $pdo->commit();
    
    // Try to send notification (don't break if it fails)
    try {
        require_once __DIR__ . '/../../includes/notification_functions.php';
        if (function_exists('notifyNewPatient')) {
            $patientName = trim($firstName . ' ' . $lastName);
            notifyNewPatient($pdo, $patientName, $patientId);
        }
    } catch (Exception $notifErr) {
        // Silent fail
    }
    
    // Debug: verify queue was added
    $debugQueueCheck = null;
    try {
        $debugStmt = $pdo->query("SELECT id FROM queue WHERE patient_id = " . (int)$patientId . " ORDER BY id DESC LIMIT 1");
        $debugQueueCheck = $debugStmt->fetch();
    } catch (Exception $e) {}
    
    echo json_encode([
        'success' => true, 
        'message' => 'Patient admitted and added to queue successfully!',
        'patient_id' => $patientId,
        'queue_id' => $queueId,
        'billing_id' => $billingId,
        'total_amount' => $totalAmount,
        'debug_queue_check' => $debugQueueCheck,
        'debug_treatment' => $treatmentType
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $errorMsg = $e->getMessage();
    error_log("Process admission error: " . $errorMsg);
    
    // Clean error message - remove any extra text
    $cleanMsg = trim($errorMsg);
    if (strpos($cleanMsg, 'Error:') !== false) {
        $cleanMsg = substr($cleanMsg, strpos($cleanMsg, 'Error:') + 6);
    }
    
    echo json_encode(['success' => false, 'message' => trim($cleanMsg)]);
}
