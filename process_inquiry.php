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
    
    // Debug: Log received data (remove in production)
    error_log("Received POST data: " . print_r($_POST, true));
    
    // Check if using new separate fields or old combined name field
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $name = trim($_POST['name'] ?? '');
    
    error_log("First name: '$first_name', Last name: '$last_name', Name: '$name'");
    
    // If name is provided but not split into parts, try to split it
    if (!empty($name) && (empty($first_name) || empty($last_name))) {
        $parts = explode(' ', trim($name), 3);
        $first_name = $parts[0] ?? '';
        $middle_name = $parts[1] ?? '';
        $last_name = $parts[2] ?? '';
        error_log("Split name - First: '$first_name', Middle: '$middle_name', Last: '$last_name'");
    }
    
    $contact_info = trim($_POST['contact_info'] ?? '');
    $source = $_POST['source'] ?? '';
    $topic = trim($_POST['topic'] ?? '');
    $inquiry_message = trim($_POST['inquiry_message'] ?? '');
    $status = 'Pending'; // Auto-set to Pending
    
    error_log("Final values - First: '$first_name', Last: '$last_name', Source: '$source'");
    
    if (empty($first_name) || empty($last_name) || empty($source)) {
        echo json_encode(['success' => false, 'message' => 'First name, last name, and source are required']);
        exit();
    }
    
    // Try to insert with new structure first, fallback to old structure
    try {
        $stmt = $pdo->prepare("INSERT INTO inquiries (first_name, middle_name, last_name, contact_info, source, topic, inquiry_message, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$first_name, $middle_name, $last_name, $contact_info, $source, $topic, $inquiry_message, $status]);
    } catch (Exception $e) {
        // If new structure fails, try old structure
        $full_name = trim("$first_name $middle_name $last_name");
        $stmt = $pdo->prepare("INSERT INTO inquiries (name, contact_info, source, topic, inquiry_message, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$full_name, $contact_info, $source, $topic, $inquiry_message, $status]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Inquiry added successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>