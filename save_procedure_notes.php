<?php
/**
 * Save Procedure Notes API
 * Allows dentist to save procedure notes for a queue item
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
if (!in_array($role, ['dentist', 'staff', 'admin'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$queueId = $data['queue_id'] ?? null;
$notes = $data['procedure_notes'] ?? '';

if (!$queueId) {
    echo json_encode(['success' => false, 'message' => 'Queue ID is required']);
    exit();
}

try {
    require_once 'config/database.php';
    
    // Verify queue item exists
    $stmt = $pdo->prepare("SELECT q.*, p.first_name, p.last_name 
                          FROM queue q 
                          JOIN patients p ON q.patient_id = p.id 
                          WHERE q.id = ?");
    $stmt->execute([$queueId]);
    $queueItem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$queueItem) {
        echo json_encode(['success' => false, 'message' => 'Queue item not found']);
        exit();
    }
    
    // Update procedure notes
    $stmt = $pdo->prepare("UPDATE queue SET procedure_notes = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$notes, $queueId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Notes saved successfully',
        'patient_name' => $queueItem['first_name'] . ' ' . $queueItem['last_name'],
        'procedure_notes' => $notes
    ]);
    
} catch (Exception $e) {
    error_log("Save Notes Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error saving notes: ' . $e->getMessage()]);
}
?>
