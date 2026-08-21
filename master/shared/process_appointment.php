<?php
ob_start();

$__sessionLifetime = 600;
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => $__sessionLifetime,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    ini_set('session.gc_maxlifetime', $__sessionLifetime);
    session_start();
}

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

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/appointment_approval_helper.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $patient_id = isset($_POST['patient_id']) && $_POST['patient_id'] !== '' ? (int)$_POST['patient_id'] : null;
    $appointment_id = isset($_POST['appointment_id']) && $_POST['appointment_id'] !== '' ? (int)$_POST['appointment_id'] : null;
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $treatment = $_POST['treatment'] ?? 'General Checkup';
    $notes = trim($_POST['notes'] ?? '');
    $dentist_id = isset($_POST['dentist_id']) && $_POST['dentist_id'] !== '' ? (int)$_POST['dentist_id'] : null;
    $service_id = isset($_POST['service_id']) && $_POST['service_id'] !== '' ? (int)$_POST['service_id'] : null;

    if (!$patient_id && empty($first_name)) {
        throw new Exception('First name is required');
    }

    if (!$patient_id && empty($last_name)) {
        throw new Exception('Last name is required');
    }

    if (empty($appointment_date)) {
        throw new Exception('Appointment date is required');
    }

    if (empty($appointment_time)) {
        throw new Exception('Appointment time is required');
    }
    
    $pdo->beginTransaction();

    $existingAppointment = null;

    if ($appointment_id) {
        $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ? LIMIT 1");
        $stmt->execute([$appointment_id]);
        $existingAppointment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingAppointment) {
            throw new Exception('Appointment not found');
        }

        if (!$patient_id) {
            $patient_id = (int)$existingAppointment['patient_id'];
        }

        // Editing an appointment must never switch it to another patient because
        // the staff member changed the phone number or name.
        $patient_id = (int)$existingAppointment['patient_id'];
    }

    // Search for existing patient by phone (more reliable than name)
    if (!$appointment_id && $phone) {
        $stmt = $pdo->prepare("SELECT id FROM patients WHERE phone = ? LIMIT 1");
        $stmt->execute([$phone]);
        $existing_patient = $stmt->fetch();
        
        if ($existing_patient) {
            $patient_id = $existing_patient['id'];
        }
    }
    
    // If not found by phone, search by name
    if (!$appointment_id && !$patient_id) {
        $stmt = $pdo->prepare("SELECT id FROM patients WHERE first_name = ? AND last_name = ? LIMIT 1");
        $stmt->execute([$first_name, $last_name]);
        $existing_patient = $stmt->fetch();

        if ($existing_patient) {
            $patient_id = $existing_patient['id'];
        }
    }
    
    // If still not found, check by name partial match
    if (!$appointment_id && !$patient_id) {
        $stmt = $pdo->prepare("SELECT id FROM patients WHERE first_name LIKE ? AND last_name LIKE ? LIMIT 1");
        $stmt->execute([$first_name . '%', $last_name . '%']);
        $existing_patient = $stmt->fetch();
        
        if ($existing_patient) {
            $patient_id = $existing_patient['id'];
        }
    }

    if ($patient_id) {
        // CHECK: Prevent double booking - patient can't be in queue today
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM appointments 
            WHERE patient_id = ? 
            AND DATE(appointment_date) = ?
            AND status IN ('Scheduled', 'In Queue', 'Pending Approval')
            AND id <> ?
        ");
        $checkStmt->execute([$patient_id, $appointment_date, $appointment_id ?: 0]);
        $activeCount = $checkStmt->fetchColumn();
        
        if ($activeCount > 0) {
            throw new Exception('This patient already has an active appointment scheduled for ' . $appointment_date . '. A patient cannot have multiple appointments on the same day.');
        }

        // CHECK: Patient can only have one pending approval request at a time
        if (!$appointment_id && patientHasPendingAppointment($pdo, (int)$patient_id)) {
            throw new Exception('This patient already has a booking request pending staff approval.');
        }
        
        // Also check if patient is currently in queue
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM queue 
            WHERE patient_id = ? 
            AND status IN ('waiting', 'in_procedure')
        ");
        $checkStmt->execute([$patient_id]);
        $inQueueCount = $checkStmt->fetchColumn();
        
        if ($inQueueCount > 0) {
            throw new Exception('This patient is already in the dental queue. A patient cannot have multiple queue tickets.');
        }
        
        // Update patient info if exists
        $stmt = $pdo->prepare("UPDATE patients SET 
            first_name = ?, 
            middle_name = ?, 
            last_name = ?, 
            email = COALESCE(NULLIF(?, ''), email),
            phone = COALESCE(NULLIF(?, ''), phone),
            updated_at = NOW() 
            WHERE id = ?");
        $stmt->execute([$first_name, $middle_name, $last_name, $email, $phone, $patient_id]);
    } else {
        // NEW PATIENT: Create patient record first
        $stmt = $pdo->prepare("INSERT INTO patients (first_name, middle_name, last_name, phone, email, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $emailToSave = $email ?: null;
        $stmt->execute([$first_name, $middle_name, $last_name, $phone, $emailToSave]);
        $patient_id = $pdo->lastInsertId();
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
    
    // Check if booked_by_id column exists
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'booked_by_id'");
        if ($checkCol->rowCount() == 0) {
            $pdo->exec("ALTER TABLE appointments ADD COLUMN booked_by_id INT DEFAULT NULL");
        }
    } catch (Exception $e) {}
    
    // Determine who booked: staff = user_id, patient self = special value
    $bookedById = $_SESSION['user_id'] ?? null;
    $bookingType = $bookedById ? 'staff' : 'self';
    
    // Check if booking_type column exists
    $bookingTypeExists = false;
    $hasRefCode = false;
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'booking_type'");
        $bookingTypeExists = $checkCol->rowCount() > 0;
        
        $checkRef = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'reference_code'");
        $hasRefCode = $checkRef->rowCount() > 0;
    } catch (Exception $e) {
        $bookingTypeExists = false;
        $hasRefCode = false;
    }
    
    // Resolve service_id from POST or treatment name
    if (!$service_id) {
        try {
            $svcStmt = $pdo->prepare("SELECT id FROM services WHERE name = ? LIMIT 1");
            $svcStmt->execute([$treatment]);
            $service_id = $svcStmt->fetchColumn() ?: null;
        } catch (Exception $e) {}
    }

    // Get available columns for dynamic queries
    $apptColsStmt = $pdo->query("SHOW COLUMNS FROM appointments");
    $appointmentColumns = array_column($apptColsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    if ($appointment_id) {
        $updates = [
            'appointment_date = ?',
            'appointment_time = ?',
            'service_id = ?',
            'notes = ?'
        ];
        $params = [$appointment_date, $appointment_time, $service_id, $notes];

        if (in_array('dentist_id', $appointmentColumns)) {
            $updates[] = 'dentist_id = ?';
            $params[] = $dentist_id;
        }

        if (in_array('updated_at', $appointmentColumns, true)) {
            $updates[] = 'updated_at = NOW()';
        }

        $params[] = $appointment_id;
        $stmt = $pdo->prepare("UPDATE appointments SET " . implode(', ', $updates) . " WHERE id = ?");
        $result = $stmt->execute($params);

        if (!$result) {
            throw new Exception("Failed to update appointment. PDO Error: " . implode(", ", $stmt->errorInfo()));
        }
    } else {
        // Build INSERT query dynamically based on existing columns
        $columns = ['patient_id', 'appointment_date', 'appointment_time', 'service_id', 'notes', 'status', 'created_by', 'created_at', 'is_archived'];
        $placeholders = ['?', '?', '?', '?', '?', "'Scheduled'", '?', 'NOW()', '0'];
        $params = [$patient_id, $appointment_date, $appointment_time, $service_id, $notes, $_SESSION['user_id'] ?? 1];
        
        if (in_array('dentist_id', $appointmentColumns)) {
            $columns[] = 'dentist_id';
            $placeholders[] = '?';
            $params[] = $dentist_id;
        }
        
        if ($bookingTypeExists) {
            $columns[] = 'booked_by_id';
            $placeholders[] = '?';
            $params[] = $bookedById;
            
            $columns[] = 'booking_type';
            $placeholders[] = '?';
            $params[] = $bookingType;
        }

        if ($hasRefCode) {
            $columns[] = 'reference_code';
            $placeholders[] = '?';
            $params[] = 'APT' . date('ymdHis') . mt_rand(10, 99);
        }
        
        $stmt = $pdo->prepare("INSERT INTO appointments (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")");
        $result = $stmt->execute($params);

        if (!$result) {
            throw new Exception("Failed to insert appointment. PDO Error: " . implode(", ", $stmt->errorInfo()));
        }

        $appointment_id = $pdo->lastInsertId();

        if (!$appointment_id) {
            throw new Exception("Failed to get appointment ID");
        }
    }

    $pdo->commit();

        // Check if patient should transition from New Patient to Returning Patient
        try {
            require_once __DIR__ . '/../../includes/patient_type_helper.php';
            maybeTransitionToReturningPatient($pdo, $patient_id);
        } catch (Exception $e) {
            // Silent fail - non-critical
        }

        // Send notifications for new appointment
        try {
            require_once __DIR__ . '/../../includes/notification_functions.php';
            $patientName = trim($first_name . ' ' . ($middle_name ?? '') . ' ' . $last_name);
            $formattedDate = date('F j, Y', strtotime($appointment_date));
            notifyNewAppointment($pdo, $patientName, $formattedDate);
        } catch (Exception $notifErr) {
        // Silent fail - don't break appointment creation
    } catch (Error $notifErr) {
        // Silent fail - don't break appointment creation
    }

    echo json_encode([
        'success' => true,
        'message' => $existingAppointment ? 'Appointment updated successfully!' : 'Appointment scheduled successfully!'
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
