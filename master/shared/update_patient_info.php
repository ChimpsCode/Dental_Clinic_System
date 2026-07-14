<?php
/**
 * Update Patient Info API
 * Allows admin to edit patient information
 * Notifies patient if their info was changed
 */

session_start();
header('Content-Type: application/json');

require_once '../../config/database.php';

$patient_id = $_POST['patient_id'] ?? null;
$first_name = $_POST['first_name'] ?? '';
$middle_name = $_POST['middle_name'] ?? '';
$last_name = $_POST['last_name'] ?? '';
$suffix = $_POST['suffix'] ?? '';
$phone = $_POST['phone'] ?? '';
$email = $_POST['email'] ?? '';
$date_of_birth = $_POST['date_of_birth'] ?? null;
$gender = $_POST['gender'] ?? '';

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
        $patient_id
    ]);
    
    if ($result) {
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