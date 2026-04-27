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
    
    // Set last_logout timestamp to track when userlogged out, mark as logged out
    $stmt = $pdo->prepare("UPDATE users SET last_logout = UTC_TIMESTAMP(), is_logged_in = 0 WHERE id = ?");
    $stmt->execute([$user_id]);
}

session_destroy();
header('Location: master/welcome-page/login.php');
exit();
