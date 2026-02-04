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
if ($role !== 'admin') {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$patientId = $_POST['patient_id'] ?? null;

if (!$patientId || !is_numeric($patientId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
    exit();
}

try {
    require_once 'config/database.php';
    
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
            
            // Soft delete - archive the patient
            $stmt = $pdo->prepare("UPDATE patients SET is_archived = 1, deleted_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$patientId]);
            
            if ($result) {
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
