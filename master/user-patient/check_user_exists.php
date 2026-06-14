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

    $stmt = $pdo->prepare("SELECT id, email, is_archived FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$email, $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo json_encode(['exists' => true, 'email' => $user['email'], 'is_archived' => (bool)$user['is_archived']]);
    } else {
        echo json_encode(['exists' => false]);
    }

} catch (Exception $e) {
    error_log("User check error: " . $e->getMessage());
    echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
}
?>