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
if (!in_array($role, ['dentist', 'admin'])) {
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
    
    // Get inquiry details
    $stmt = $pdo->prepare("SELECT * FROM inquiries WHERE id = ?");
    $stmt->execute([$id]);
    $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$inquiry) {
        echo json_encode(['success' => false, 'message' => 'Inquiry not found']);
        exit();
    }
    
    // Update inquiry status to Booked
    $stmt = $pdo->prepare("UPDATE inquiries SET status = 'Booked' WHERE id = ?");
    $stmt->execute([$id]);
    
    // Optionally create an appointment record (if you have an appointments table)
    // You can add this logic here if needed
    
    echo json_encode(['success' => true, 'message' => 'Inquiry converted to appointment successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>