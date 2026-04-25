<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: master/welcome-page/login.php');
    exit();
}

$role = strtolower($_SESSION['role'] ?? 'admin');
if ($role === 'administrator') { $role = 'admin'; }

if ($role === 'admin') {
    header('Location: master/admin/admin_dashboard.php');
} elseif ($role === 'staff') {
    header('Location: master/staff/staff-dashboard.php');
} elseif ($role === 'dentist') {
    header('Location: master/dentist/dentist_dashboard.php');
} else {
    header('Location: master/admin/admin_dashboard.php');
}
exit();
?>
