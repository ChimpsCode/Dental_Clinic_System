<?php
session_start();

// Log logout before destroying session
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'unknown';
    $role = $_SESSION['role'] ?? 'unknown';
    
    // Log logout
    require_once 'config/database.php';
    require_once 'includes/audit_helper.php';
    logAudit($pdo, $user_id, $username, $role, 'logout', 'users', 'User logged out');
}

session_destroy();
header('Location: login.php');
exit();
?>

