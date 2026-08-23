<?php
require_once __DIR__ . '/includes/session_bootstrap.php';
// Log logout before destroying session
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'unknown';
    $role = $_SESSION['role'] ?? 'unknown';
    
    // Log logout
    require_once 'config/database.php';
    require_once 'includes/audit_helper.php';
    logAudit($pdo, $user_id, $username, $role, 'logout', 'users', 'User logged out');
    
    // Update only the logout-tracking columns that exist in this database schema.
    try {
        $columnsStmt = $pdo->query("SHOW COLUMNS FROM users");
        $userColumns = array_column($columnsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
        $updates = [];

        if (in_array('last_logout', $userColumns, true)) {
            $updates[] = "last_logout = UTC_TIMESTAMP()";
        }
        if (in_array('is_logged_in', $userColumns, true)) {
            $updates[] = "is_logged_in = 0";
        }

        if (!empty($updates)) {
            $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?");
            $stmt->execute([$user_id]);
        }
    } catch (Exception $e) {
        // Don't block logout if optional tracking columns are missing.
    }
}

session_destroy();
header('Location: master/welcome-page/login.php');
exit();
