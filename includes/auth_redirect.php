<?php
/**
 * Send an already-logged-in user to their role dashboard.
 * Paths are relative to master/welcome-page/.
 */

function app_authenticated_dashboard_url(): ?string
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $role = strtolower((string)($_SESSION['role'] ?? ''));
    if ($role === 'administrator') {
        $role = 'admin';
    }
    if ($role === 'patient') {
        $role = 'user';
    }

    $map = [
        'admin' => '../admin/admin_dashboard.php',
        'staff' => '../staff/staff-dashboard.php',
        'dentist' => '../dentist/dentist_dashboard.php',
        'user' => '../user-patient/patient-dashboard.php',
    ];

    return $map[$role] ?? '../staff/staff-dashboard.php';
}

function redirect_if_authenticated(): void
{
    $url = app_authenticated_dashboard_url();
    if ($url === null) {
        return;
    }
    header('Location: ' . $url);
    exit();
}

function app_guest_bfcache_reload_script(): void
{
    echo '<script>window.addEventListener("pageshow",function(e){if(e.persisted)location.reload();});</script>' . "\n";
}
