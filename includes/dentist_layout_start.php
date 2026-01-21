<?php
/**
 * Dentist Layout - Centralized layout wrapper for all dentist pages
 * Ensures strict role-based separation: only dentists can access dentist pages
 * and Dentist Sidebar is always displayed regardless of which dentist page is loaded.
 */

ob_start();
session_start();

// Start session and validate dentist access
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dentist') {
    ob_end_clean();
    header('Location: login.php');
    exit();
}

// Get user info from session
$username = $_SESSION['username'] ?? 'Dentist';
$fullName = $_SESSION['full_name'] ?? 'Dr. Dentist';

// Get page title for header
$pageTitle = $pageTitle ?? 'Dentist Dashboard';

// Helper function to check if current page matches
function isActivePage($page) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    return $currentPage === $page;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - RF Dental Clinic Dentist</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/staff_dashboard.css">
</head>
<body>
    <!-- Left Sidebar - Dentist Navigation -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="assets/images/Logo.png" alt="RF Logo">
            <span>RF Dental Clinic</span>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dentist_dashboard.php" class="nav-item <?php echo isActivePage('dentist_dashboard.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M3 12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1zm0 8a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1zm10 0a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-6a1 1 0 0 0-1 1zm1-17a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1z"/>
                    </svg>
                </span>
                <span>Dashboard</span>
            </a>
            
            <a href="dentist_appointments.php" class="nav-item <?php echo isActivePage('dentist_appointments.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3m1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/>
                    </svg>
                </span>
                <span>Appointments</span>
            </a>
            
             <a href="dentist_patients.php" class="nav-item <?php echo isActivePage('dentist_patients.php') ? 'active' : ''; ?>">
                 <span class="nav-item-icon">
                     <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                         <path fill="currentColor" d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3s1.34 3 3 3m-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5S5 6.34 5 8s1.34 3 3 3m0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5m8 0c-.29 0-.62.02-.97.05c1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5"/>
                     </svg>
                 </span>
                 <span>Patient Records</span>
             </a>
            
            <a href="dentist_treatments.php" class="nav-item <?php echo isActivePage('dentist_treatments.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                </span>
                <span>Treatment Plan</span>
            </a>

            <a href="dentist_queue.php" class="nav-item <?php echo isActivePage('dentist_queue.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M17 3H7q-.425 0-.712.288T6 4v2h11V4q0-.425-.288-.712T17 3m0 4H7q-.425 0-.712.288T6 8v10q0 .425.288.712T7 19h10q.425 0 .713-.288T18 18V8q0-.425-.288-.712T17 7m0 2v10H7V9h10m2-2H5V5h14v2"/>
                    </svg>
                </span>
                <span>Queue</span>
            </a>

            <a href="dentist_settings.php" class="nav-item <?php echo isActivePage('dentist_settings.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" viewBox="0 0 23 23"><g fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" d="m21.318 7.141l-.494-.856c-.373-.648-.56-.972-.878-1.101c-.317-.13-.676-.027-1.395.176l-1.22.344c-.459.106-.94.046-1.358-.17l-.337-.194a2 2 0 0 1-.788-.967l-.334-.998c-.22-.66-.33-.99-.591-1.178c-.261-.19-.609-.19-1.303-.19h-1.115c-.694 0-1.041 0-1.303.19c-.261.188-.37.518-.59 1.178l-.334.998a2 2 0 0 1-.789.967l-.337.195c-.418.215-.9.275-1.358.17l-1.22-.345c-.719-.203-1.078-.305-1.395-.176c-.318.129-.505.453-.878 1.1l-.493.857c-.35.608-.525.911-.491 1.234c.034.324.268.584.736 1.105l1.031 1.153c.252.319.431.875.431 1.375s-.179 1.056-.43 1.375l-1.032 1.152c-.468.521-.702.782-.736 1.105s.14.627.49 1.234l.494.857c.373.647.56.971.878 1.1s.676.028 1.395-.176l1.22-.344a2 2 0 0 1 1.359.17l.336.194c.36.23.636.57.788.968l.334.997c.22.66.33.99.591 1.18c.262.188.609.188 1.303.188h1.115c.694 0 1.042 0 1.303-.189s.371-.519.59-1.179l.335-.997c.152-.399.428-.738.788-.968l.336-.194c.42-.215.9-.276 1.36-.17l1.22.344c.718.204 1.077.306 1.394.177c.318-.13.505-.454.878-1.101l.493-.857c.35-.607.525-.91.491-1.234s-.268-.584-.736-1.105l-1.031-1.152c-.252-.32-.431-.875-.431-1.375s.179-1.056.43-1.375l1.032-1.153c.468-.52.702-.781.736-1.105s-.14-.626-.49-1.234Z"/><path d="M15.52 12a3.5 3.5 0 1 1-7 0a3.5 3.5 0 0 1 7 0Z"/></g></svg>
                </span>
                <span>Settings</span>
            </a>
        </nav>
        
        <div class="sidebar-footer" style="border-top: 1px solid #6b7280; margin-top: 10px; padding-left: 20px;">
            <a href="logout.php" class="nav-item">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M5 21q-.825 0-1.412-.587T3 19V5q0-.825.588-1.412T5 3h6q.425 0 .713.288T12 4t-.288.713T11 5H5v14h6q.425 0 .713.288T12 20t-.288.713T11 21zm12.175-8H10q-.425 0-.712-.288T9 12t.288-.712T10 11h7.175L15.3 9.125q-.275-.275-.275-.675t.275-.7t.7-.313t.725.288L20.3 11.3q.3.3.3.7t-.3.7l-3.575 3.575q-.3.3-.712.288t-.713-.313q-.275-.3-.262-.712t.287-.688z"/>
                    </svg>
                </span>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <div class="header-title">
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <p>Dentist Panel - RF Dental Clinic</p>
                </div>
            </div>
            
            <div class="header-right">
                <div class="user-profile">
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 32 32'%3E%3Ccircle cx='16' cy='16' r='16' fill='%23e5e7eb'/%3E%3Ctext x='16' y='22' font-family='Arial' font-size='18' fill='%236b7280' text-anchor='middle'%3E👨‍⚕️%3C/text%3E%3C/svg%3E" alt="User">
                    <span class="user-name"><?php echo htmlspecialchars($fullName); ?></span>
                    <span class="user-role">Dentist</span>
                </div>
            </div>
        </header>

        <!-- Content Area -->
        <div class="content-area">
            <div class="content-main">