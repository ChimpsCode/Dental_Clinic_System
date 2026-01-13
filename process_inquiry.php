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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    require_once 'config/database.php';
    
    $name = trim($_POST['name'] ?? '');
    $contact_info = trim($_POST['contact_info'] ?? '');
    $source = $_POST['source'] ?? '';
    $topic = trim($_POST['topic'] ?? '');
    $inquiry_message = trim($_POST['inquiry_message'] ?? '');
    $status = $_POST['status'] ?? 'Pending';
    
    if (empty($name) || empty($source)) {
        echo json_encode(['success' => false, 'message' => 'Name and source are required']);
        exit();
    }
    
    $stmt = $pdo->prepare("INSERT INTO inquiries (name, contact_info, source, topic, inquiry_message, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$name, $contact_info, $source, $topic, $inquiry_message, $status]);
    
    echo json_encode(['success' => true, 'message' => 'Inquiry added successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>