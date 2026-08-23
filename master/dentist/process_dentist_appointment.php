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
    require_once '../../includes/appointment_approval_helper.php';
    
    // Check if dentist_id column exists
    $checkCol = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'dentist_id'");
    $hasDentistCol = $checkCol->rowCount() > 0;

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
        $treatment = $_POST['treatment'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        $status = trim($_POST['status'] ?? '');
        $service_id = isset($_POST['service_id']) && $_POST['service_id'] !== '' ? (int)$_POST['service_id'] : null;
        
        // Validate required fields
        if (empty($first_name) || empty($last_name) || empty($phone) || empty($appointment_date) || empty($appointment_time)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
            exit();
        }

        // Fetch existing appointment (patient ownership + current status)
        $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ? LIMIT 1");
        $stmt->execute([$appointment_id]);
        $existingAppointment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingAppointment) {
            echo json_encode(['success' => false, 'message' => 'Appointment not found']);
            exit();
        }

        // Normalize status against the table enum values
        $allowedStatuses = ['Scheduled', 'Completed', 'Cancelled'];
        if ($status === '') {
            $status = $existingAppointment['status'];
        } else {
            $candidate = ucfirst(strtolower($status));
            $found = false;
            foreach ($allowedStatuses as $allowed) {
                if (strcasecmp($status, $allowed) === 0) { $status = $allowed; $found = true; break; }
            }
            if (!$found) $status = $existingAppointment['status'];
        }

        // Resolve service_id from POST or treatment name
        if (!$service_id && $treatment !== '') {
            try {
                $svcStmt = $pdo->prepare("SELECT id FROM services WHERE name = ? LIMIT 1");
                $svcStmt->execute([$treatment]);
                $service_id = $svcStmt->fetchColumn() ?: null;
            } catch (Exception $e) {}
        }

        try {
            $pdo->beginTransaction();

            // Patient identity lives in the patients table, not appointments
            if (!empty($existingAppointment['patient_id'])) {
                $updPatient = $pdo->prepare("UPDATE patients SET 
                    first_name = ?, 
                    middle_name = ?, 
                    last_name = ?, 
                    email = COALESCE(NULLIF(?, ''), email),
                    phone = COALESCE(NULLIF(?, ''), phone),
                    updated_at = NOW() 
                    WHERE id = ?");
                $updPatient->execute([$first_name, $middle_name, $last_name, $email, $phone, (int)$existingAppointment['patient_id']]);
            }

            // Duration of the new booking (for overlap validation)
            $newDuration = 30;
            if ($service_id) {
                $dStmt = $pdo->prepare("SELECT duration_minutes FROM services WHERE id = ?");
                $dStmt->execute([$service_id]);
                $dRow = $dStmt->fetch(PDO::FETCH_ASSOC);
                if ($dRow && (int)$dRow['duration_minutes'] > 0) {
                    $newDuration = (int)$dRow['duration_minutes'];
                }
            }

            // Server-side double-booking guard: reject slots that would overlap
            // another active appointment on the same date
            $timeParts = explode(':', $appointment_time);
            $newStart = (int)$timeParts[0] * 60 + (int)$timeParts[1];
            $newEnd = $newStart + $newDuration;

            $conflictStmt = $pdo->prepare("
                SELECT a.id, TIME_FORMAT(a.appointment_time, '%H:%i') AS start_hm,
                       COALESCE(a.duration_minutes, s.duration_minutes, 30) AS duration_minutes
                FROM appointments a
                LEFT JOIN services s ON a.service_id = s.id
                WHERE a.appointment_date = ?
                  AND a.id <> ?
                  AND COALESCE(a.is_archived, 0) = 0
                  AND LOWER(COALESCE(a.status, 'scheduled')) NOT IN ('cancelled', 'completed', 'no_show', 'pending approval')
            ");
            $conflictStmt->execute([$appointment_date, $appointment_id]);
            foreach ($conflictStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $p = explode(':', $row['start_hm']);
                $bStart = (int)$p[0] * 60 + (int)$p[1];
                $bEnd = $bStart + (int)$row['duration_minutes'];
                if ($newStart < $bEnd && $newEnd > $bStart) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Selected time conflicts with another appointment at ' . date('g:i A', strtotime($row['start_hm'])) . ' on ' . $appointment_date . '. Please choose a different slot.']);
                    exit();
                }
            }

            // Update appointment
            $updateCols = [
                'appointment_date = ?',
                'appointment_time = ?',
                'service_id = ?',
                'notes = ?',
                'status = ?'
            ];
            $updateParams = [
                $appointment_date,
                $appointment_time,
                $service_id,
                $notes,
                $status
            ];

            // updated_at only when the column exists
            try {
                $hasUpdatedAt = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'updated_at'")->rowCount() > 0;
                if ($hasUpdatedAt) {
                    $updateCols[] = 'updated_at = NOW()';
                }
            } catch (Exception $e) {}

            // Only change the assigned dentist when explicitly requested;
            // otherwise preserve the current one.
            if ($hasDentistCol && isset($_POST['dentist_id']) && $_POST['dentist_id'] !== '') {
                $updateCols[] = 'dentist_id = ?';
                $updateParams[] = (int)$_POST['dentist_id'];
            }

            $updateParams[] = $appointment_id;
            $stmt = $pdo->prepare("UPDATE appointments SET " . implode(', ', $updateCols) . " WHERE id = ?");
            $stmt->execute($updateParams);

            $pdo->commit();
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Dentist Appointment Edit Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error updating appointment: ' . $e->getMessage()]);
            exit();
        }
        
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
        if (patientHasActiveAppointmentOnDate($pdo, (int)$patient_id, $appointment_date)) {
            echo json_encode(['success' => false, 'message' => 'This patient already has an active appointment scheduled for ' . $appointment_date . '.']);
            exit();
        }

        // CHECK: Patient can only have one pending approval request at a time
        if (patientHasPendingAppointment($pdo, (int)$patient_id)) {
            echo json_encode(['success' => false, 'message' => 'This patient already has a booking request pending staff approval.']);
            exit();
        }
        
        // Also check if patient is in queue
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM queue WHERE patient_id = ? AND status IN ('waiting', 'in_procedure')");
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
    
    // Resolve service_id
    $service_id = null;
    try {
        $svcStmt = $pdo->prepare("SELECT id FROM services WHERE name = ? LIMIT 1");
        $svcStmt->execute([$treatment]);
        $service_id = $svcStmt->fetchColumn() ?: null;
    } catch (Exception $e) {}

    // Build INSERT dynamically
    $columns = ['patient_id', 'appointment_date', 'appointment_time', 'service_id', 'notes', 'status', 'created_by', 'reference_code', 'created_at'];
    $placeholders = ['?', '?', '?', '?', '?', "'Scheduled'", '?', '?', 'NOW()'];
    $values = [$patient_id, $appointment_date, $appointment_time, $service_id, $notes, $_SESSION['user_id'] ?? null, $refCode];

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

    echo json_encode([
        'success' => true, 
        'message' => 'Appointment scheduled successfully.',
        'appointment_id' => $appointment_id
    ]);
    
} catch (Exception $e) {
    error_log("Dentist Appointment Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>