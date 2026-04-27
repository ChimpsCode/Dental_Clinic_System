<?php
/**
 * Verify Admin Password API
 * Verifies admin password before destructive actions
 */

session_start();
header('Content-Type: application/json');

$password = $_POST['password'] ?? '';

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'No password provided']);
    exit;
}

require_once '../../config/database.php';

// Get admin user
$stmt = $pdo->prepare("SELECT id, password FROM users WHERE role_id = 1 LIMIT 1");
$stmt->execute();
$admin = $stmt->fetch();

if (!$admin) {
    echo json_encode(['success' => false, 'message' => 'No admin found']);
    exit;
}

// Verify password
if (password_verify($password, $admin['password'])) {
    echo json_encode(['success' => true, 'message' => 'Password verified']);
} else {
    echo json_encode(['success' => false, 'message' => 'Incorrect password']);
}