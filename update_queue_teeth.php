<?php
/**
 * Update Queue Teeth API
 * Allows dentist to update teeth selection for a queue item
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
if (!in_array($role, ['dentist', 'admin'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Dentist access only']);
    exit();
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$queueId = $data['queue_id'] ?? null;
$teethNumbers = $data['teeth_numbers'] ?? '';

if (!$queueId) {
    echo json_encode(['success' => false, 'message' => 'Queue ID is required']);
    exit();
}

try {
    require_once 'config/database.php';
    
    // Verify queue item exists and belongs to a valid patient
    $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.last_name 
                          FROM queue q 
                          JOIN patients p ON q.patient_id = p.id 
                          WHERE q.id = ? AND q.status IN ('waiting', 'in_procedure')");
    $stmt->execute([$queueId]);
    $queueItem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$queueItem) {
        echo json_encode(['success' => false, 'message' => 'Queue item not found or not active']);
        exit();
    }
    
    // Update teeth numbers
    $stmt = $pdo->prepare("UPDATE queue SET teeth_numbers = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$teethNumbers, $queueId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Teeth updated successfully',
        'patient_name' => $queueItem['first_name'] . ' ' . $queueItem['last_name'],
        'teeth_numbers' => $teethNumbers
    ]);
    
} catch (Exception $e) {
    error_log("Update Teeth Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error updating teeth: ' . $e->getMessage()]);
}
?>
