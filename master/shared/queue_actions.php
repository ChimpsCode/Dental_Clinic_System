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

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data) {
    $data = $_POST;
}
$action = $data['action'] ?? '';
$queueId = $data['queue_id'] ?? ($data['id'] ?? null);
$patientId = $data['patient_id'] ?? null;

$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'unknown';
$user_role = $_SESSION['role'] ?? 'unknown';

if (!$queueId && !$patientId) {
    echo json_encode(['success' => false, 'message' => 'Queue ID or Patient ID is required']);
    exit();
}

try {
    require_once '../../config/database.php';
    require_once '../../includes/audit_helper.php';
    
    // Helper function to build full name
    function buildFullName($item) {
        return trim(($item['first_name'] ?? '') . ' ' . ($item['middle_name'] ?? '') . ' ' . ($item['last_name'] ?? '') . ' ' . ($item['suffix'] ?? ''));
    }

    switch ($action) {
        case 'call':
            $stmt = $pdo->prepare("SELECT q.*, COALESCE(p.first_name, 'Unknown') as first_name, COALESCE(p.middle_name, '') as middle_name, COALESCE(p.last_name, '') as last_name, COALESCE(p.suffix, '') as suffix, COALESCE(p.phone, '') as phone FROM queue q 
                                   LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);

            $stmt = $pdo->prepare("UPDATE queue SET status = 'in_procedure' WHERE id = ?");
            $stmt->execute([$queueId]);

            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Called patient: ' . $patientName, $queueId, 'queue', 'waiting', 'in_procedure');

            echo json_encode([
                'success' => true, 
                'message' => 'Calling patient: ' . $patientName,
                'patient_name' => $patientName,
                'phone' => $queueItem['phone']
            ]);
            break;
            
        case 'start_procedure':
            // Check if there's already a procedure in progress
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name, p.suffix 
                                   FROM queue q 
                                   LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) 
                                   WHERE q.status = 'in_procedure'
                                   AND DATE(q.created_at) = CURDATE() 
                                   LIMIT 1");
            $stmt->execute();
            $existingProcedure = $stmt->fetch();
            
            if ($existingProcedure) {
                $existingPatientName = buildFullName($existingProcedure);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Cannot start procedure: ' . $existingPatientName . ' is currently in procedure. Please complete their procedure first.'
                ]);
                break;
            }
            
            // Get old status for audit
            $stmt = $pdo->prepare("SELECT status FROM queue WHERE id = ?");
            $stmt->execute([$queueId]);
            $oldStatus = $stmt->fetchColumn() ?: 'waiting';
            
            // Update to In Procedure
            $stmt = $pdo->prepare("UPDATE queue SET status = 'in_procedure' WHERE id = ?");
            $result = $stmt->execute([$queueId]);
            
            if (!$result || $stmt->rowCount() === 0) {
                echo json_encode(['success' => false, 'message' => 'Failed to start procedure']);
                break;
            }
            
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name, p.suffix FROM queue q 
                                   LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);
            
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Started procedure for patient: ' . $patientName, $queueId, 'queue', $oldStatus, 'in_procedure');
            
            echo json_encode(['success' => true, 'message' => 'Patient moved to procedure', 'patient_name' => $patientName]);
            break;
            
        case 'complete':
            // First check if queue item exists
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.last_name FROM queue q LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            
            if (!$queueItem) {
                echo json_encode(['success' => false, 'message' => 'Queue item not found']);
                break;
            }
            
            $patientId = $queueItem['patient_id'];
            $patientName = buildFullName($queueItem);
            $treatmentType = $queueItem['treatment_type'] ?? 'consultation';
            $stmt = $pdo->prepare("SELECT price FROM services WHERE LOWER(name) = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([strtolower($treatmentType)]);
            $service = $stmt->fetch();
            $calculatedAmount = $service['price'] ?? 500;
            
            $pdo->beginTransaction();
            
            // Update to Completed
            $stmt = $pdo->prepare("UPDATE queue SET status = 'completed', completed_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$queueId]);
            
            if (!$result || $stmt->rowCount() === 0) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Failed to complete treatment']);
                break;
            }
            
            // Update appointment status to completed
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'Completed' WHERE patient_id = ? AND status IN ('Scheduled', 'In Queue')");
            $stmt->execute([$patientId]);
            
            $pdo->commit();
            
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Completed procedure for: ' . $patientName, $queueId, 'queue', 'in_procedure', 'completed');
            
            echo json_encode(['success' => true, 'message' => 'Procedure completed. Record moved to billing.', 'patient_id' => $patientId, 'amount' => $calculatedAmount]);
            break;
            
        case 'on_hold':
            // Get old status for audit
            $stmt = $pdo->prepare("SELECT q.status FROM queue q WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $oldStatusName = $stmt->fetchColumn() ?: 'waiting';
            
            // Update to On Hold
            $stmt = $pdo->prepare("UPDATE queue SET status = 'on_hold' WHERE id = ?");
            $result = $stmt->execute([$queueId]);
            
            // Get patient name for audit
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name FROM queue q LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);
            
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Patient put on hold: ' . $patientName, $queueId, 'queue', $oldStatusName, 'on_hold');
            
            echo json_encode(['success' => true, 'message' => 'Patient put on hold']);
            break;
            
        case 'cancel':
            // Get old status for audit
            $stmt = $pdo->prepare("SELECT q.status FROM queue q WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $oldStatusName = $stmt->fetchColumn() ?: 'waiting';
            
            $stmt = $pdo->prepare("UPDATE queue SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$queueId]);
            
            // Get patient name for audit
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name FROM queue q LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);
            
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Patient cancelled: ' . $patientName, $queueId, 'queue', $oldStatusName, 'cancelled');
            
            echo json_encode(['success' => true, 'message' => 'Patient cancelled']);
            break;
            
        case 'requeue':
            // First check if queue item exists
            $stmt = $pdo->prepare("SELECT * FROM queue WHERE id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            
            if (!$queueItem) {
                echo json_encode(['success' => false, 'message' => 'Queue item not found']);
                break;
            }
            
            // Get old status name for audit
            $oldStatusName = $queueItem['status'] ?? 'on_hold';
            
            // Set back to waiting
            $stmt = $pdo->prepare("UPDATE queue SET status = 'waiting' WHERE id = ?");
            $result = $stmt->execute([$queueId]);
            
            if (!$result || $stmt->rowCount() === 0) {
                echo json_encode(['success' => false, 'message' => 'Failed to re-queue patient']);
                break;
            }
            
            // Get patient name for audit
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name FROM queue q LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);
            
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Patient re-queued: ' . $patientName, $queueId, 'queue', $oldStatusName, 'waiting');
            
            echo json_encode(['success' => true, 'message' => 'Patient re-queued']);
            break;
            
        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM queue WHERE id = ?");
            $stmt->execute([$queueId]);
            echo json_encode(['success' => true, 'message' => 'Record deleted']);
            break;
            
        case 'get_patient_record':
            $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
            $stmt->execute([$patientId]);
            $patient = $stmt->fetch();
            
            echo json_encode(['success' => true, 'patient' => $patient]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("Queue Action Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>