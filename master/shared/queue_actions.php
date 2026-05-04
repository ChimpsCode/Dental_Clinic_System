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
    
    // Don't use ensureQueueManagementSchema as it doesn't add status_id column
    
    // Ensure queue_statuses table has data
    $stmt = $pdo->query("SELECT id, status_name FROM queue_statuses");
    $statuses = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    if (empty($statuses)) {
        // Insert statuses if not exists - use REPLACE to handle existing data
        $pdo->exec("DELETE FROM queue_statuses");
        $pdo->exec("INSERT INTO queue_statuses (id, status_name, color, icon, description, sort_order) VALUES 
            (1, 'Waiting', '#007bff', 'fa-clock', 'Patient is waiting', 1),
            (2, 'Called', '#ffc107', 'fa-bell', 'Patient has been called', 2),
            (3, 'In Procedure', '#17a2b8', 'fa-user-md', 'Currently in treatment', 3),
            (4, 'Pending Payment', '#fd7e14', 'fa-money-bill', 'Awaiting payment', 4),
            (5, 'Completed', '#28a745', 'fa-check', 'All done, paid', 5),
            (6, 'On Hold', '#6c757d', 'fa-pause', 'Temporarily paused', 6),
            (7, 'Cancelled', '#dc3545', 'fa-times', 'Cancelled', 7)
        ");
        $stmt = $pdo->query("SELECT id, status_name FROM queue_statuses");
        $statuses = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    
    // Create map from status_name to id (case-insensitive)
    $statusNameToId = [];
    foreach ($statuses as $id => $name) {
        $statusNameToId[$name] = $id;
        // Also add lowercase version for matching
        $statusNameToId[strtolower($name)] = $id;
    }
    
    // Ensure queue table has status_id column
    $stmt = $pdo->query("SHOW COLUMNS FROM queue LIKE 'status_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE queue ADD COLUMN status_id INT NULL DEFAULT 1 AFTER status");
    }
    
    // Add dentist_id column if not exists (for tracking which dentist performed treatment)
    $stmt = $pdo->query("SHOW COLUMNS FROM queue LIKE 'dentist_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE queue ADD COLUMN dentist_id INT NULL AFTER status_id");
    }
    
    // Check and fix foreign key - remove if exists to avoid issues
    try {
        $pdo->exec("ALTER TABLE queue DROP FOREIGN KEY IF EXISTS queue_ibfk_2");
    } catch (Exception $e) {
        // FK might not exist
    }
    
    // Re-add FK with proper settings (no delete set null)
    try {
        $pdo->exec("ALTER TABLE queue ADD CONSTRAINT fk_queue_status FOREIGN KEY (status_id) REFERENCES queue_statuses(id) ON DELETE RESTRICT ON UPDATE CASCADE");
    } catch (Exception $e) {
        // FK might already exist
    }
    
    // Ensure queue table has status_id column
    $stmt = $pdo->query("SHOW COLUMNS FROM queue LIKE 'status_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE queue ADD COLUMN status_id INT NULL DEFAULT 1 AFTER status");
    }
    
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

            $stmt = $pdo->prepare("UPDATE queue SET status_id = ? WHERE id = ?");
            $stmt->execute([$statusNameToId['Called'], $queueId]);

            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Called patient: ' . $patientName, $queueId, 'queue', 'Waiting', 'Called');

            echo json_encode([
                'success' => true, 
                'message' => 'Calling patient: ' . $patientName,
                'patient_name' => $patientName,
                'phone' => $queueItem['phone']
            ]);
            break;
            
        case 'start_procedure':
            // Get In Procedure status ID directly from database
            $inProcedureStatusId = null;
            if (isset($statusNameToId['In Procedure'])) {
                $inProcedureStatusId = $statusNameToId['In Procedure'];
            } elseif (isset($statusNameToId['in_procedure'])) {
                $inProcedureStatusId = $statusNameToId['in_procedure'];
            }
            
            if ($inProcedureStatusId === null) {
                $stmt = $pdo->query("SELECT id FROM queue_statuses WHERE status_name = 'In Procedure'");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    $inProcedureStatusId = $result['id'];
                } else {
                    $inProcedureStatusId = 3; // Default fallback
                }
            }
            
            // Check if there's already a procedure in progress
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name, p.suffix 
                                   FROM queue q 
                                   LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) 
                                   WHERE q.status_id = ? 
                                   AND DATE(q.created_at) = CURDATE() 
                                   LIMIT 1");
            $stmt->execute([$inProcedureStatusId]);
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
            $oldStatusId = $stmt->fetchColumn() ?: 1;
            
            // Update to In Procedure
            $stmt = $pdo->prepare("UPDATE queue SET status_id = ? WHERE id = ?");
            $result = $stmt->execute([$inProcedureStatusId, $queueId]);
            
            if (!$result || $stmt->rowCount() === 0) {
                echo json_encode(['success' => false, 'message' => 'Failed to start procedure']);
                break;
            }
            
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name, p.suffix FROM queue q 
                                   LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);
            
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Started procedure for patient: ' . $patientName, $queueId, 'queue', $oldStatusId, $inProcedureStatusId);
            
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
            $treatmentType = $queueItem['treatment_od'] ?? 'consultation';
            $stmt = $pdo->prepare("SELECT price FROM services WHERE LOWER(name) = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([strtolower($treatmentType)]);
            $service = $stmt->fetch();
            $calculatedAmount = $service['price'] ?? 500;
            
            // Get completed status ID directly from database
            $completedStatusId = null;
            if (isset($statusNameToId['Completed'])) {
                $completedStatusId = $statusNameToId['Completed'];
            } elseif (isset($statusNameToId['completed'])) {
                $completedStatusId = $statusNameToId['completed'];
            }
            
            if ($completedStatusId === null) {
                $stmt = $pdo->query("SELECT id FROM queue_statuses WHERE status_name = 'Completed'");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    $completedStatusId = $result['id'];
                } else {
                    $completedStatusId = 5; // Default fallback
                }
            }
            
            $pdo->beginTransaction();
            
            // Update to Completed and save dentist_id (current user)
            $stmt = $pdo->prepare("UPDATE queue SET status_id = ?, dentist_id = ?, completed_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$completedStatusId, $user_id, $queueId]);
            
            if (!$result || $stmt->rowCount() === 0) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Failed to complete treatment']);
                break;
            }
            
            // Update appointment status if exists
            $stmt = $pdo->query("DESCRIBE appointments");
            $aptCols = array_column($stmt->fetchAll(), 'Field');
            if (in_array('status_id', $aptCols)) { 
                $stmt = $pdo->prepare("UPDATE appointments SET status_id = 3 WHERE patient_id = ? AND status_id = 2");
                $stmt->execute([$patientId]);
            }
            
            $pdo->commit();
            
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Completed procedure for: ' . $patientName, $queueId, 'queue', '3', '5');
            
            echo json_encode(['success' => true, 'message' => 'Procedure completed. Record moved to billing.', 'patient_id' => $patientId, 'amount' => $calculatedAmount]);
            break;
            
        case 'on_hold':
            // Debug: log what's in statusNameToId
            error_log("statusNameToId: " . print_r($statusNameToId, true));
            
            // Get current status_id before updating
            $stmt = $pdo->prepare("SELECT status_id FROM queue WHERE id = ?");
            $stmt->execute([$queueId]);
            $currentStatusId = $stmt->fetchColumn();
            error_log("Current status_id for queue $queueId: " . $currentStatusId);
            
            // Get old status for audit
            $stmt = $pdo->prepare("SELECT qs.status_name FROM queue q LEFT JOIN queue_statuses qs ON qs.id = q.status_id WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $oldStatusName = $stmt->fetchColumn() ?: 'Waiting';
            
            // Check if 'On Hold' status exists - try different variations
            $onHoldStatusId = null;
            if (isset($statusNameToId['On Hold'])) {
                $onHoldStatusId = $statusNameToId['On Hold'];
            } elseif (isset($statusNameToId['on hold'])) {
                $onHoldStatusId = $statusNameToId['on hold'];
            } elseif (isset($statusNameToId['on_hold'])) {
                $onHoldStatusId = $statusNameToId['on_hold'];
            }
            
            if ($onHoldStatusId === null) {
                // Try to get from database directly
                $stmt = $pdo->query("SELECT id, status_name FROM queue_statuses WHERE status_name LIKE '%Hold%'");
                $holdStatus = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($holdStatus) {
                    $onHoldStatusId = $holdStatus['id'];
                    error_log("Found On Hold status directly: " . $onHoldStatusId);
                } else {
                    echo json_encode(['success' => false, 'message' => 'On Hold status not found in queue_statuses table. Available: ' . implode(', ', $statusNameToId)]);
                    break;
                }
            }
            
            // Update to On Hold
            $stmt = $pdo->prepare("UPDATE queue SET status_id = ? WHERE id = ?");
            $result = $stmt->execute([$onHoldStatusId, $queueId]);
            
            // Get patient name for audit
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name FROM queue q LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);
            
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Patient put on hold: ' . $patientName, $queueId, 'queue', $oldStatusName, 'On Hold');
            
            echo json_encode(['success' => true, 'message' => 'Patient put on hold']);
            break;
            
        case 'cancel':
            // Get old status for audit
            $stmt = $pdo->prepare("SELECT qs.status_name FROM queue q LEFT JOIN queue_statuses qs ON qs.id = q.status_id WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $oldStatusName = $stmt->fetchColumn() ?: 'Waiting';
            
            $stmt = $pdo->prepare("UPDATE queue SET status_id = ? WHERE id = ?");
            $stmt->execute([$statusNameToId['Cancelled'], $queueId]);
            
            // Get patient name for audit
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name FROM queue q LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);
            
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Patient cancelled: ' . $patientName, $queueId, 'queue', $oldStatusName, 'Cancelled');
            
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
            
            // Get current status_id
            $currentStatusId = $queueItem['status_id'];
            
            // Get old status name for audit
            $oldStatusName = 'On Hold';
            if ($currentStatusId) {
                $stmt = $pdo->prepare("SELECT status_name FROM queue_statuses WHERE id = ?");
                $stmt->execute([$currentStatusId]);
                $oldStatusName = $stmt->fetchColumn() ?: 'On Hold';
            }
            
            // Check if 'Waiting' status exists
            $waitingStatusId = null;
            if (isset($statusNameToId['Waiting'])) {
                $waitingStatusId = $statusNameToId['Waiting'];
            } elseif (isset($statusNameToId['waiting'])) {
                $waitingStatusId = $statusNameToId['waiting'];
            }
            
            if ($waitingStatusId === null) {
                // Get directly from database
                $stmt = $pdo->query("SELECT id FROM queue_statuses WHERE status_name = 'Waiting'");
                $waitingResult = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($waitingResult) {
                    $waitingStatusId = $waitingResult['id'];
                } else {
                    $waitingStatusId = 1; // Default fallback
                }
            }
            
            // Set back to Waiting (status_id = 1)
            $stmt = $pdo->prepare("UPDATE queue SET status_id = ? WHERE id = ?");
            $result = $stmt->execute([$waitingStatusId, $queueId]);
            
            if (!$result || $stmt->rowCount() === 0) {
                echo json_encode(['success' => false, 'message' => 'Failed to re-queue patient']);
                break;
            }
            
            // Get patient name for audit
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name FROM queue q LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED) WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);
            
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Patient re-queued: ' . $patientName, $queueId, 'queue', $oldStatusName, 'Waiting');
            
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