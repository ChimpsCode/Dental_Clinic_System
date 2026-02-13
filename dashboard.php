<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$role = $_SESSION['role'] ?? 'admin';

if ($role === 'admin') {
    header('Location: admin_dashboard.php');
} elseif ($role === 'staff') {
    header('Location: staff-dashboard.php');
} elseif ($role === 'dentist') {
    header('Location: dentist_dashboard.php');
} else {
    header('Location: admin_dashboard.php');
}
exit();
?>
