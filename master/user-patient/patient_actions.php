<?php
/**
 * Patient Actions API
 * Handles archive and delete operations for patients (admin only)
 */

ob_start();
session_start();

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$role = $_SESSION['role'] ?? '';
$action = $_POST['action'] ?? '';

// Handle patient self-deletion (no admin role check needed)
if ($action === 'self_delete') {
    ob_end_clean();
    header('Content-Type: application/json');

    $userId = $_SESSION['user_id'] ?? null;
    $patientId = $_SESSION['patient_id'] ?? null;
    $password = $_POST['password'] ?? '';

    if (!$userId || !$patientId) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit();
    }

    try {
require_once '../../config/database.php';
require_once '../../includes/patient_user_sync_helper.php';

        // Verify password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? AND is_archived = 0");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
            exit();
        }

        // Archive patients record and the linked user account as one unit
        archivePatientWithUser($pdo, (int)$patientId, true);

        // Log audit
        require_once '../../includes/audit_helper.php';
        logAudit($pdo, $userId, $_SESSION['username'] ?? 'unknown', 'user', 'delete', 'users', 'Patient self-deleted account');

        // Destroy session
        session_destroy();
        ob_clean();

        echo json_encode([
            'success' => true,
            'redirect' => '../../master/welcome-page/login.php?deleted=1'
        ]);
        exit();
    } catch (Exception $e) {
        error_log("Self-deletion error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
        exit();
    }
}

// Accept Admin or admin from the roles table
if (strtolower($role) !== 'admin' && strtolower($role) !== 'dentist') {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$patientId = $_POST['patient_id'] ?? null;

if (!$patientId || !is_numeric($patientId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
    exit();
}

try {
    require_once '../../config/database.php';
    require_once '../../includes/patient_user_sync_helper.php';

    switch ($action) {
        case 'archive':
            // Check if archive column exists
            $checkColumn = $pdo->query("SHOW COLUMNS FROM patients LIKE 'is_archived'");
            if ($checkColumn->rowCount() == 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Archive system not enabled. Please run database migration.'
                ]);
                break;
            }
            
            // Soft delete - archive the patient together with its linked user account
            $stmt = $pdo->prepare("SELECT 1 FROM patients WHERE id = ? AND is_archived = 0");
            $stmt->execute([$patientId]);
            if ($stmt->fetchColumn()) {
                archivePatientWithUser($pdo, (int)$patientId, true);
                echo json_encode(['success' => true, 'message' => 'Patient archived successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to archive patient']);
            }
            break;
            
        case 'delete':
            // Hard delete - permanently remove from database
            $pdo->beginTransaction();
            
            try {
                // Delete related medical history first
                $stmt = $pdo->prepare("DELETE FROM medical_history WHERE patient_id = ?");
                $stmt->execute([$patientId]);
                
                // Delete related dental history
                $stmt = $pdo->prepare("DELETE FROM dental_history WHERE patient_id = ?");
                $stmt->execute([$patientId]);
                
                // Delete related queue records
                $stmt = $pdo->prepare("DELETE FROM queue WHERE patient_id = ?");
                $stmt->execute([$patientId]);

                // Delete the linked login account (if any). If other records
                // still reference it, archive it instead so nothing breaks.
                $linkedUserId = getLinkedUserId($pdo, (int)$patientId);
                if ($linkedUserId) {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$linkedUserId]);
                    } catch (Exception $e) {
                        $pdo->prepare("UPDATE users SET is_archived = 1 WHERE id = ?")->execute([$linkedUserId]);
                    }
                }

                // Finally delete the patient
                $stmt = $pdo->prepare("DELETE FROM patients WHERE id = ?");
                $result = $stmt->execute([$patientId]);
                
                $pdo->commit();
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Patient deleted permanently']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete patient']);
                }
            } catch (Exception $e) {
                $pdo->rollback();
                throw $e;
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Patient Actions Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
