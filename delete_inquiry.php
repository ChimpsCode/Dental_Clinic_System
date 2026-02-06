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

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Inquiry ID is required']);
    exit();
}

try {
    require_once 'config/database.php';
    
    // Check if is_archived column exists
    $checkCol = $pdo->query("SHOW COLUMNS FROM inquiries LIKE 'is_archived'");
    $hasArchiveColumn = $checkCol->rowCount() > 0;
    
    if ($hasArchiveColumn) {
        // Archive the inquiry (soft delete)
        $stmt = $pdo->prepare("UPDATE inquiries SET is_archived = 1, deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Inquiry archived successfully. You can restore it from the Archive page.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Inquiry not found']);
        }
    } else {
        // Fallback to hard delete if archive columns don't exist
        $stmt = $pdo->prepare("DELETE FROM inquiries WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Inquiry deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Inquiry not found']);
        }
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>