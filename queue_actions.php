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
    require_once 'config/database.php';
    require_once 'includes/audit_helper.php';
    
    // Helper function to build full name
    function buildFullName($item) {
        return trim(($item['first_name'] ?? '') . ' ' . ($item['middle_name'] ?? '') . ' ' . ($item['last_name'] ?? '') . ' ' . ($item['suffix'] ?? ''));
    }

    switch ($action) {
        case 'call':
            // Get queue item
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name, p.suffix, p.phone FROM queue q 
                                   LEFT JOIN patients p ON q.patient_id = p.id WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);

            // Log audit
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Called patient: ' . $patientName . ' (Q-' . str_pad($queueId, 4, '0', STR_PAD_LEFT) . ')', $queueId, 'queue', 'waiting', 'called');

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
                                   LEFT JOIN patients p ON q.patient_id = p.id 
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
            
            // Get current status before update
            $stmt = $pdo->prepare("SELECT status FROM queue WHERE id = ?");
            $stmt->execute([$queueId]);
            $oldStatus = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("UPDATE queue SET status = 'in_procedure', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$queueId]);
            
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name, p.suffix FROM queue q 
                                   LEFT JOIN patients p ON q.patient_id = p.id WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);
            
            // Log audit
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Started procedure for patient: ' . $patientName . ' (Q-' . str_pad($queueId, 4, '0', STR_PAD_LEFT) . ')', $queueId, 'queue', $oldStatus, 'in_procedure');
            
            echo json_encode([
                'success' => true, 
                'message' => 'Patient moved to procedure',
                'patient_name' => $patientName
            ]);
            break;
            
        case 'complete':
            // First, get the queue item details BEFORE updating
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name, p.suffix 
                                   FROM queue q 
                                   LEFT JOIN patients p ON q.patient_id = p.id 
                                   WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            
            if (!$queueItem) {
                echo json_encode(['success' => false, 'message' => 'Queue item not found']);
                break;
            }
            
            // Get current status before update
            $oldStatus = $queueItem['status'];
            $patientName = buildFullName($queueItem);
            
            // Check if already processed
            $isProcessed = $queueItem['is_processed'] ?? 0;
            
            // Auto-create treatment record if not already processed
            if (!$isProcessed) {
                // Get user info for doctor_id
                $doctorId = $_SESSION['user_id'] ?? 1;
                
                // Insert into treatments table (matching actual column names)
                $insertStmt = $pdo->prepare("INSERT INTO treatments (
                    patient_id, 
                    treatment_date, 
                    procedure_name, 
                    tooth_number, 
                    description, 
                    status, 
                    doctor_id,
                    notes,
                    created_at
                ) VALUES (?, CURDATE(), ?, ?, ?, 'completed', ?, ?, NOW())");
                
                $insertStmt->execute([
                    $queueItem['patient_id'],
                    $queueItem['treatment_type'],
                    $queueItem['teeth_numbers'],
                    $queueItem['procedure_notes'] ?? 'Treatment completed from queue',
                    $doctorId,
                    $queueItem['procedure_notes'] ?? ''
                ]);
                
                $treatmentId = $pdo->lastInsertId();
                
                // Mark queue item as treatment finished, pending payment
                $stmt = $pdo->prepare("UPDATE queue SET status = 'pending_payment', completed_at = NOW(), is_processed = 1, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$queueId]);
                
                // Log audit for treatment creation
                logAudit($pdo, $user_id, $username, $user_role, 'create', 'treatments', 'Created treatment record for patient: ' . $patientName . ' - ' . ($queueItem['treatment_type'] ?? 'General Checkup'), $treatmentId, 'treatments', null, $queueItem['treatment_type']);
                
                // Log audit for queue completion - pending payment
                logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Treatment finished, pending payment for patient: ' . $patientName . ' (Q-' . str_pad($queueId, 4, '0', STR_PAD_LEFT) . ')', $queueId, 'queue', $oldStatus, 'pending_payment');
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Treatment finished and recorded for ' . $patientName . '. Please proceed to payment.',
                    'patient_name' => $patientName,
                    'treatment_id' => $treatmentId
                ]);
            } else {
                // Already processed, just update status
                $stmt = $pdo->prepare("UPDATE queue SET status = 'pending_payment', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$queueId]);
                
                // Log audit
                logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Marked treatment as pending payment for patient: ' . $patientName . ' (Q-' . str_pad($queueId, 4, '0', STR_PAD_LEFT) . ')', $queueId, 'queue', $oldStatus, 'pending_payment');
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Treatment finished for ' . $patientName . '. Please proceed to payment.',
                    'patient_name' => $patientName
                ]);
            }
            break;
            
        case 'on_hold':
            // Get current status before update
            $stmt = $pdo->prepare("SELECT status FROM queue WHERE id = ?");
            $stmt->execute([$queueId]);
            $oldStatus = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("UPDATE queue SET status = 'on_hold', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$queueId]);
            
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name, p.suffix FROM queue q 
                                   LEFT JOIN patients p ON q.patient_id = p.id WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);
            
            // Log audit
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Put patient on hold: ' . $patientName . ' (Q-' . str_pad($queueId, 4, '0', STR_PAD_LEFT) . ')', $queueId, 'queue', $oldStatus, 'on_hold');
            
            echo json_encode([
                'success' => true, 
                'message' => 'Patient put on hold: ' . $patientName
            ]);
            break;
            
        case 'cancel':
            // Get current status before update
            $stmt = $pdo->prepare("SELECT status FROM queue WHERE id = ?");
            $stmt->execute([$queueId]);
            $oldStatus = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("UPDATE queue SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$queueId]);
            
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name, p.suffix FROM queue q 
                                   LEFT JOIN patients p ON q.patient_id = p.id WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);
            
            // Log audit
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Cancelled patient: ' . $patientName + ' (Q-' . str_pad($queueId, 4, '0', STR_PAD_LEFT) . ')', $queueId, 'queue', $oldStatus, 'cancelled');
            
            echo json_encode([
                'success' => true, 
                'message' => 'Patient cancelled: ' . $patientName
            ]);
            break;
            
        case 'requeue':
            // Get current status before update
            $stmt = $pdo->prepare("SELECT status FROM queue WHERE id = ?");
            $stmt->execute([$queueId]);
            $oldStatus = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("UPDATE queue SET status = 'waiting', queue_time = NOW(), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$queueId]);
            
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name, p.suffix FROM queue q 
                                   LEFT JOIN patients p ON q.patient_id = p.id WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);
            
            // Log audit
            logAudit($pdo, $user_id, $username, $user_role, 'status_change', 'queue', 'Re-queued patient: ' . $patientName . ' (Q-' . str_pad($queueId, 4, '0', STR_PAD_LEFT) . ')', $queueId, 'queue', $oldStatus, 'waiting');
            
            echo json_encode([
                'success' => true, 
                'message' => 'Patient re-queued: ' . $patientName
            ]);
            break;
            
        case 'delete':
            $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.middle_name, p.last_name, p.suffix FROM queue q 
                                   LEFT JOIN patients p ON q.patient_id = p.id WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            $patientName = buildFullName($queueItem);
            
            // Log before delete
            logAudit($pdo, $user_id, $username, $user_role, 'delete', 'queue', 'Deleted queue entry for patient: ' . ($patientName ?: 'Unknown') . ' (Q-' . str_pad($queueId, 4, '0', STR_PAD_LEFT) . ')', $queueId, 'queue', null, null);
            
            $stmt = $pdo->prepare("DELETE FROM queue WHERE id = ?");
            $stmt->execute([$queueId]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Patient record deleted: ' . ($patientName ?: 'Unknown')
            ]);
            break;
            
        case 'get_patient_record':
            // Get patient details
            $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
            $stmt->execute([$patientId]);
            $patient = $stmt->fetch();
            
            // Get medical history
            $stmt = $pdo->prepare("SELECT * FROM medical_history WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$patientId]);
            $medicalHistory = $stmt->fetch();
            
            // Get dental history
            $stmt = $pdo->prepare("SELECT * FROM dental_history WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$patientId]);
            $dentalHistory = $stmt->fetch();
            
            // Get queue item
            $stmt = $pdo->prepare("SELECT * FROM queue WHERE patient_id = ? AND status IN ('waiting', 'in_procedure') ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$patientId]);
            $queueItem = $stmt->fetch();
            
            // Log audit for viewing patient record
            if ($patient) {
                $patientFullName = buildFullName($patient);
                logAudit($pdo, $user_id, $username, $user_role, 'read', 'patients', 'Viewed patient record: ' . $patientFullName, $patientId, 'patients', null, null);
            }
            
            echo json_encode([
                'success' => true,
                'patient' => $patient,
                'medical_history' => $medicalHistory,
                'dental_history' => $dentalHistory,
                'queue_item' => $queueItem
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Queue Action Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
