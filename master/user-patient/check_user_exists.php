<?php
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        throw new Exception('Email is required');
    }

    // Check if user exists by email
    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo json_encode(['exists' => true, 'email' => $user['email']]);
    } else {
        echo json_encode(['exists' => false]);
    }

} catch (Exception $e) {
    error_log("User check error: " . $e->getMessage());
    echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
}
?>