<?php
/**
 * Universal Dashboard - Auto-redirects based on user role
 * Fallback file for login redirects and role-based routing
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Redirect based on role
$role = $_SESSION['role'] ?? 'admin';

if ($role === 'admin') {
    header('Location: admin_dashboard.php');
} elseif ($role === 'staff') {
    header('Location: staff-dashboard.php');
} elseif ($role === 'dentist') {
    header('Location: dentist_dashboard.php');
} else {
    // Default fallback
    header('Location: admin_dashboard.php');
}
exit();
?>
