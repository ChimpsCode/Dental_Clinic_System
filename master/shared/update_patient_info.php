<?php
/**
 * Update Patient Info API
 * Allows admin to edit patient information
 * Notifies patient if their info was changed
 */

require_once __DIR__ . '/../../includes/session_bootstrap.php';
header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Administrator access required']);
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/audit_helper.php';
require_once '../../includes/patient_user_sync_helper.php';
require_once __DIR__ . '/../../includes/input_sanitize.php';

$patient_id = $_POST['patient_id'] ?? null;
$firstSan = sanitize_name($_POST['first_name'] ?? '', true, 80, 'First name');
$middleSan = sanitize_name($_POST['middle_name'] ?? '', false, 80, 'Middle name');
$lastSan = sanitize_name($_POST['last_name'] ?? '', true, 80, 'Last name');
if (!$firstSan['ok']) {
    echo json_encode(['success' => false, 'message' => $firstSan['error']]);
    exit;
}
if (!$middleSan['ok']) {
    echo json_encode(['success' => false, 'message' => $middleSan['error']]);
    exit;
}
if (!$lastSan['ok']) {
    echo json_encode(['success' => false, 'message' => $lastSan['error']]);
    exit;
}
$first_name = $firstSan['value'];
$middle_name = $middleSan['value'];
$last_name = $lastSan['value'];
$suffix = $_POST['suffix'] ?? '';
$phone = $_POST['phone'] ?? '';
$email = $_POST['email'] ?? '';
$date_of_birth = trim((string)($_POST['date_of_birth'] ?? ''));
$date_of_birth = $date_of_birth === '' ? null : substr($date_of_birth, 0, 10);
$gender = $_POST['gender'] ?? '';

require_once __DIR__ . '/../../includes/discount_helper.php';
ensureDiscountSchema($pdo);

$seniorIdSan = sanitize_id_code($_POST['senior_citizen_id'] ?? '', false, 30, 'Senior ID number');
if (!$seniorIdSan['ok']) {
    echo json_encode(['success' => false, 'message' => $seniorIdSan['error']]);
    exit;
}
$pwdIdSan = sanitize_id_code($_POST['pwd_id'] ?? '', false, 30, 'PWD ID number');
if (!$pwdIdSan['ok']) {
    echo json_encode(['success' => false, 'message' => $pwdIdSan['error']]);
    exit;
}

list($senior_id_type, $senior_citizen_id) = normalizeSeniorIdFields(
    $_POST['senior_id_type'] ?? '',
    $seniorIdSan['value']
);
$pwd_id = normalizePwdIdField(!empty($_POST['is_pwd']), $pwdIdSan['value']);

if (!$patient_id) {
    echo json_encode(['success' => false, 'message' => 'Patient ID required']);
    exit;
}

if (empty($first_name) || empty($last_name)) {
    echo json_encode(['success' => false, 'message' => 'First name and last name are required']);
    exit;
}

try {
    // Get original patient data first
    $getStmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $getStmt->execute([$patient_id]);
    $oldPatient = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$oldPatient) {
        echo json_encode(['success' => false, 'message' => 'Patient not found']);
        exit;
    }
    
    // Update patient
    $stmt = $pdo->prepare("
        UPDATE patients SET 
            first_name = ?,
            middle_name = ?,
            last_name = ?,
            suffix = ?,
            phone = ?,
            email = ?,
            date_of_birth = ?,
            gender = ?,
            senior_citizen_id = ?,
            senior_id_type = ?,
            pwd_id = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $result = $stmt->execute([
        $first_name,
        $middle_name,
        $last_name,
        $suffix,
        $phone,
        $email,
        $date_of_birth,
        $gender,
        $senior_citizen_id,
        $senior_id_type,
        $pwd_id,
        $patient_id
    ]);
    
    if ($result) {
        $newPatient = $oldPatient;
        $newPatient['first_name'] = $first_name;
        $newPatient['middle_name'] = $middle_name;
        $newPatient['last_name'] = $last_name;
        $newPatient['suffix'] = $suffix;
        $newPatient['phone'] = $phone;
        $newPatient['email'] = $email;
        $newPatient['date_of_birth'] = $date_of_birth;
        $newPatient['gender'] = $gender;
        $newPatient['senior_citizen_id'] = $senior_citizen_id;
        $newPatient['senior_id_type'] = $senior_id_type;
        $newPatient['pwd_id'] = $pwd_id;
        logRecordChange($pdo, $_SESSION['user_id'], $_SESSION['username'] ?? 'unknown', 'admin', 'update', 'patients', 'patients', (int)$patient_id, 'patient record #' . $patient_id, $oldPatient, $newPatient);

        try {
            syncPatientToUser($pdo, (int)$patient_id);
        } catch (Exception $e) {
            // Identity sync is best-effort; the patient row was already saved.
        }

        // Build change notification
        $changes = [];
        if ($oldPatient['first_name'] !== $first_name) $changes[] = "Name: {$oldPatient['first_name']} → {$first_name}";
        if ($oldPatient['last_name'] !== $last_name) $changes[] = "Last Name: {$oldPatient['last_name']} → {$last_name}";
        if ($oldPatient['phone'] !== $phone) $changes[] = "Phone: {$oldPatient['phone']} → {$phone}";
        if ($oldPatient['email'] !== $email) $changes[] = "Email: {$oldPatient['email']} → {$email}";
        
        // Find user account for this patient and send notification
        // Check if patient has email and find matching user (patient web account)
        if (!empty($email)) {
            // Try to find user by matching name OR email
            $userStmt = $pdo->prepare("SELECT id FROM users WHERE (email = ? AND role = 'Patient') OR (first_name = ? AND last_name = ? AND role = 'Patient') LIMIT 1");
            $userStmt->execute([$email, $first_name, $last_name]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $changeList = !empty($changes) ? implode(', ', $changes) : 'Your profile was updated by the admin';
                
                // Insert notification for patient
                $notifStmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, title, message, action_url, created_at) 
                    VALUES (?, 'patient', 'Profile Updated', ?, NULL, NOW())
                ");
                $notifStmt->execute([$user['id'], $changeList]);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Patient updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update patient']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}