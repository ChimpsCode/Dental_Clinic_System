<?php
/**
 * Patient Actions API
 * Handles delete operations for patients (admin only)
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
        case 'delete':
            // Begin transaction for safe deletion
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
                    echo json_encode(['success' => true, 'message' => 'Patient deleted successfully']);
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