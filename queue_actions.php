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

if (!$queueId && !$patientId) {
    echo json_encode(['success' => false, 'message' => 'Queue ID or Patient ID is required']);
    exit();
}

try {
    require_once 'config/database.php';
    
    switch ($action) {
        case 'call':
            // Get queue item
            $stmt = $pdo->prepare("SELECT q.*, p.full_name, p.phone FROM queue q 
                                   LEFT JOIN patients p ON q.patient_id = p.id WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Calling patient: ' . $queueItem['full_name'],
                'patient_name' => $queueItem['full_name'],
                'phone' => $queueItem['phone']
            ]);
            break;
            
        case 'start_procedure':
            $stmt = $pdo->prepare("UPDATE queue SET status = 'in_procedure', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$queueId]);
            
            $stmt = $pdo->prepare("SELECT q.*, p.full_name FROM queue q 
                                   LEFT JOIN patients p ON q.patient_id = p.id WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Patient moved to procedure',
                'patient_name' => $queueItem['full_name']
            ]);
            break;
            
        case 'complete':
            $stmt = $pdo->prepare("UPDATE queue SET status = 'completed', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$queueId]);
            
            $stmt = $pdo->prepare("SELECT q.*, p.full_name FROM queue q 
                                   LEFT JOIN patients p ON q.patient_id = p.id WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Treatment completed for ' . $queueItem['full_name'],
                'patient_name' => $queueItem['full_name']
            ]);
            break;
            
        case 'on_hold':
            $stmt = $pdo->prepare("UPDATE queue SET status = 'on_hold', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$queueId]);
            
            $stmt = $pdo->prepare("SELECT q.*, p.full_name FROM queue q 
                                   LEFT JOIN patients p ON q.patient_id = p.id WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Patient put on hold: ' . $queueItem['full_name']
            ]);
            break;
            
        case 'cancel':
            $stmt = $pdo->prepare("UPDATE queue SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$queueId]);
            
            $stmt = $pdo->prepare("SELECT q.*, p.full_name FROM queue q 
                                   LEFT JOIN patients p ON q.patient_id = p.id WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Patient cancelled: ' . $queueItem['full_name']
            ]);
            break;
            
        case 'requeue':
            $stmt = $pdo->prepare("UPDATE queue SET status = 'waiting', queue_time = NOW(), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$queueId]);
            
            $stmt = $pdo->prepare("SELECT q.*, p.full_name FROM queue q 
                                   LEFT JOIN patients p ON q.patient_id = p.id WHERE q.id = ?");
            $stmt->execute([$queueId]);
            $queueItem = $stmt->fetch();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Patient re-queued: ' . $queueItem['full_name']
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
