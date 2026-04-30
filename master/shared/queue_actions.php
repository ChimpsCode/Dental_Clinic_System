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

// Get user info for audit logging
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

// Helper to update queue status safely (only status_id column)
    function updateQueueStatus($pdo, $id, $statusId, $statusName) {
        $stmt = $pdo->prepare("UPDATE queue SET status_id = ? WHERE id = ?");
        return $stmt->execute([$statusId, $id]);
    }

    switch ($action) {
        case 'call':
            // Get queue item
            $stmt = $pdo->prepare("SELECT q.*, COALESCE(p.first_name, 'Unknown') as first_name, COALESCE(p.middle_name, '') as middle_name, COALESCE(p.last_name, '') as last_name, COALESCE(p.suffix, '') as suffix, COALESCE(p.phone, '') as phone FROM queue q 
                                   LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);

            // Update to "called" status (assuming status_id 6 or similar, for now just update text)
            updateQueueStatus($pdo, $queueId, 1, 'waiting'); // Keep as waiting but could add "called" state

            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Called patient: ' . $patientName, $queueId, 'queue', 'waiting', 'called');

            echo json_encode([
                'success' => true, 
                'message' => 'Calling patient: ' . $patientName,
                'patient_name' => $patientName,
                'phone' => $queueItem['phone']
            ]);
            break;
            
        case 'start_procedure':
            // Check if there's already a procedure in progress (status_id 2)
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name, p.suffix 
                                   FROM queue q 
                                   LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) 
                                   WHERE q.status_id = 2 
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
            $stmt = $pdo->prepare("SELECT status_id FROM queue WHERE id = ?");
            $stmt->execute([$queueId]);
            $oldStatusId = $stmt->fetchColumn();
            
            // Update to status_id 2 (in_procedure)
            updateQueueStatus($pdo, $queueId, 2, 'in_procedure');
            
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name, p.suffix FROM queue q 
                                   LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);
            
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Started procedure for patient: ' . $patientName, $queueId, 'queue', $oldStatusId, '2');
            
            echo json_encode(['success' => true, 'message' => 'Patient moved to procedure', 'patient_name' => $patientName]);
            break;
            
        case 'complete':
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.last_name FROM queue q LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            
            if (!$queueItem) {
                echo json_encode(['success' => false, 'message' => 'Queue item not found']);
                break;
            }
            
            $patientId = $queueItem['patient_id'];
            $patientName = buildFullName($queueItem);
            $treatmentType = $queueItem['treatment_od'] ?? 'consultation';
            $stmt = $pdo->prepare("SELECT price FROM services WHERE LOWER(name) = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([strtolower($treatmentType)]);
            $service = $stmt->fetch();
            $calculatedAmount = $service['price'] ?? 500;
            
            $pdo->beginTransaction();
            
            // Update to status_id 3 (completed)
            updateQueueStatus($pdo, $queueId, 3, 'completed');
            
// Update appointment status if exists
            $stmt = $pdo->query("DESCRIBE appointments");
            $aptCols = array_column($stmt->fetchAll(), 'Field');
            $aptUpdates = [];
            if (in_array('status_id', $aptCols)) { 
                $aptUpdates[] = "status_id = 3"; // Mark appointment as completed
            }
            if (in_array('updated_at', $aptCols)) { 
                $aptUpdates[] = "updated_at = NOW()"; 
            }
            if (!empty($aptUpdates)) {
                $stmt = $pdo->prepare("UPDATE appointments SET " . implode(', ', $aptUpdates) . " WHERE patient_id = ? AND status_id = 2");
                $stmt->execute([$patientId]);
            }
            
            $pdo->commit();
            
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Completed procedure for: ' . $patientName, $queueId, 'queue', '2', '3');
            
            echo json_encode(['success' => true, 'message' => 'Procedure completed. Record moved to billing.', 'patient_id' => $patientId, 'amount' => $calculatedAmount]);
            break;
            
        case 'on_hold':
            // Set back to waiting but add note for on hold
            $stmt = $pdo->prepare("UPDATE queue SET status_id = 1, notes = CONCAT(COALESCE(notes, ''), '\n[On Hold] Patient put on hold at: ', NOW()) WHERE id = ?");
            $stmt->execute([$queueId]);
            echo json_encode(['success' => true, 'message' => 'Patient put on hold']);
            break;
            
        case 'cancel':
            updateQueueStatus($pdo, $queueId, 4, 'cancelled');
            echo json_encode(['success' => true, 'message' => 'Patient cancelled']);
            break;
            
        case 'requeue':
            updateQueueStatus($pdo, $queueId, 1, 'waiting');
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
