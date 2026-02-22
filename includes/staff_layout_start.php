<?php
ob_start();

// Set 10-minute session lifetime
$__sessionLifetime = 600;
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => $__sessionLifetime,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    ini_set('session.gc_maxlifetime', $__sessionLifetime);
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    ob_end_clean();
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'] ?? 'Staff';
$displayName = $_SESSION['display_name'] ?? 'Staff';
// Derive first name fallback for header display
$firstNameOnly = trim(explode(' ', $displayName)[0] ?? $displayName);
if ($firstNameOnly === '') {
    $firstNameOnly = $username;
}

// Header notifications (lightweight counts)
$newAppointmentsToday = 0;
$pendingPaymentsCount = 0;
try {
    if (isset($pdo)) {
        $newAppointmentsToday = (int)($pdo->query("
            SELECT COUNT(*)
            FROM appointments
            WHERE appointment_date = CURDATE()
        ")->fetchColumn() ?? 0);

        $pendingPaymentsCount = (int)($pdo->query("
            SELECT COUNT(*)
            FROM billing
            WHERE payment_status IN ('pending', 'unpaid', 'partial')
               OR (balance IS NOT NULL AND balance > 0)
        ")->fetchColumn() ?? 0);
    }
} catch (Exception $e) {
    $newAppointmentsToday = 0;
    $pendingPaymentsCount = 0;
}

$notificationTotal = $newAppointmentsToday + $pendingPaymentsCount;

$notificationTotal = $newAppointmentsToday + $pendingPaymentsCount;

$pageTitle = $pageTitle ?? 'Staff Dashboard';

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
    <title><?php echo htmlspecialchars($pageTitle); ?> - RF Dental Clinic Staff</title>
    <link rel="icon" type="image/png" href="assets/images/Logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/staff_dashboard.css">
    <style>
        /* Smooth Page Transitions */
        .content-main {
            animation: fadeInUp 0.3s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Prevent layout shifts during page loads */d0
        .content-area {
            contain: content;
        }

        /* Smooth sidebar navigation */
        .nav-item {
            transition: all 0.2s ease;
        }

        .nav-item:hover {
            transform: translateX(4px);
        }

        .nav-item.active {
            transform: scale(1.02);
        }

        /* Stable header styling */
        
        /* Notifications */
        .header-notifications {
            position: relative;
        }
        .notification-bell {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: all 0.2s;
        }
        .notification-bell:hover,
        .header-notifications:focus-within .notification-bell {
            background: #f3f4f6;
            color: #111827;
        }
        .notification-badge {
            position: absolute;
            top: 2px;
            right: 2px;
            background: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: 600;
            min-width: 16px;
            height: 16px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
        }
        .notification-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            min-width: 280px;
            z-index: 1000;
            overflow: hidden;
        }
        .header-notifications.active .notification-dropdown,
        .header-notifications:focus-within .notification-dropdown {
            display: block;
            animation: notificationFadeIn 0.2s ease;
        }
        @keyframes notificationFadeIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            color: #111827;
        }
        .notification-count {
            background: #fee2e2;
            color: #dc2626;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 10px;
        }
        .notification-list {
            max-height: 300px;
            overflow-y: auto;
        }
        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid #f3f4f6;
            transition: background 0.15s;
        }
        .notification-item:hover {
            background: #f9fafb;
        }
        .notification-icon {
            font-size: 18px;
            flex-shrink: 0;
        }
        .notification-text {
            font-size: 13px;
            color: #374151;
            line-height: 1.4;
        }
        .see-all-btn {
            width: 100%;
            padding: 12px;
            background: #f9fafb;
            border: none;
            color: #2563eb;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            text-align: center;
            transition: background 0.15s;
        }
        .see-all-btn:hover {
            background: #eff6ff;
        }
        
        /* Header right spacing */
        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .top-header {
            position: sticky;
            top: 0;
            z-index: 50;
        }

        /* Page transition loader */
        .page-loader {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #0ea5e9, #0284c7);
            z-index: 9999;
            animation: loading 1s ease-in-out infinite;
        }

        @keyframes loading {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
    </style>
</head>
<body data-user-id="<?php echo (int)($_SESSION['user_id'] ?? 0); ?>">
    <div class="page-loader"></div>

    <!-- Left Sidebar - Staff Navigation -->
    <aside class="sidebar" id="staffSidebar">
        <div class="sidebar-logo">
            <img src="assets/images/Logo.png" alt="RF Logo">
            <span>RF Dental Clinic</span>
        </div>
        
        <nav class="sidebar-nav">
            <a href="staff-dashboard.php" class="nav-item <?php echo isActivePage('staff-dashboard.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M3 12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1zm0 8a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1zm10 0a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-6a1 1 0 0 0-1 1zm1-17a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1z"/>
                    </svg>
                </span>
                <span>Dashboard</span>
            </a>

            <a href="staff_new_admission.php" class="nav-item <?php echo isActivePage('staff_new_admission.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M15.25 18.75q.3 0 .525-.225T16 18t-.225-.525t-.525-.225t-.525.225T14.5 18t.225.525t.525.225m2.75 0q.3 0 .525-.225T18.75 18t-.225-.525T18 17.25t-.525.225t-.225.525t.525.225M5 21q-.825 0-1.412-.587T3 19V5q0-.825.588-1.412T5 3h14q.825 0 1.413.588T21 5v6.7q-.475-.225-.975-.387T19 11.075V5H5v14h6.05q.075.55.238 1.05t.387.95zm0-3v1V5v6.075V11zm2-1h4.075q.075-.525.238-1.025t.362-.975H7zm0-4h6.1q.8-.75 1.788-1.25T17 11.075V11H7zm0-4h10V7H7zm11 14q-2.075 0-3.537-1.463T13 18t1.463-3.537T18 13t3.538 1.463T23 18t-1.463 3.538T18 23"/>
                    </svg>
                </span>
                <span>New Admission</span>
            </a>
            
            <a href="staff_inquiries.php" class="nav-item <?php echo isActivePage('staff_inquiries.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                </span>
                <span>Inquiries</span>
            </a>
            
            <a href="staff_queue.php" class="nav-item <?php echo isActivePage('staff_queue.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M4 6H2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm6 12v-2H8v2h2zm2-2h2v2h-2v-2zm0-3a1 1 0 0 0 1-1H7a1 1 0 0 0-1 1v3h10V7a1 1 0 0 0-1-1h-5a1 1 0 0 0-1 1z"/>
                    </svg>
                </span>
                <span>Queue Management</span>
            </a>
            
            <a href="staff_patient_records.php" class="nav-item <?php echo isActivePage('staff_patient_records.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3s1.34 3 3 3m-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5S5 6.34 5 8s1.34 3 3 3m0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5m8 0c-.29 0-.62.02-.97.05c1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5"/>
                    </svg>
                </span>
                <span>Patient Records</span>
            </a>
            
            <a href="staff_appointments.php" class="nav-item <?php echo isActivePage('staff_appointments.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3m1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/>
                    </svg>
                </span>
                <span>Appointments</span>
            </a>
            
            <a href="staff_payment.php" class="nav-item <?php echo isActivePage('staff_payment.php') ? 'active' : ''; ?>">
                <span class="nav-item-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/>
                    </svg>
                </span>
                <span>Payment</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Sidebar Overlay for mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle" type="button" aria-label="Toggle sidebar" aria-controls="staffSidebar">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="header-title">
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <p>Staff Panel - RF Dental Clinic</p>
                </div>
            </div>
            <div class="header-right">
                <div class="header-notifications" id="notificationDropdown">
                    <button class="notification-bell" type="button" aria-label="Notifications">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 22a2 2 0 0 0 2-2h-4a2 2 0 0 0 2 2zm6-6V11a6 6 0 1 0-12 0v5L4 18v1h16v-1z"/>
                        </svg>
                        <?php if ($notificationTotal > 0): ?>
                            <span class="notification-badge"><?php echo $notificationTotal; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-dropdown">
                        <div class="notification-header">
                            <span>Notifications</span>
                            <span class="notification-count"><?php echo $notificationTotal; ?></span>
                        </div>
                        <div class="notification-list">
                            <div class="notification-item">
                                <div class="notification-icon">📅</div>
                                <div class="notification-text"><?php echo $newAppointmentsToday; ?> new appointments today</div>
                            </div>
                            <div class="notification-item">
                                <div class="notification-icon">⚠️</div>
                                <div class="notification-text"><?php echo $pendingPaymentsCount; ?> pending payments require attention</div>
                            </div>
                        </div>
                        <button class="see-all-btn" type="button">See all notifications</button>
                    </div>
                </div>
                <div class="header-user-summary">
                    <div class="header-user-name"><?php echo htmlspecialchars($firstNameOnly); ?></div>
                    <div class="header-user-role"><?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? 'staff')); ?></div>
                </div>
                <div class="user-profile" id="userProfileDropdown">
                    <div class="user-profile-info">
                        <div class="user-avatar">
                            <img src="assets/images/profile.png" alt="Profile" />
                        </div>
                    </div>
                    <div class="user-profile-dropdown">
                        <div class="dropdown-divider"></div>
                        <a href="settings.php" class="dropdown-item settings">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.64l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.49-.41h-3.84c-.25 0-.45.17-.49.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.09-.47 0-.59.22L2.74 8.87c-.12.22-.07.5.12.64l2.03 1.58c-.05.3-.07.62-.07.94 0 .33.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.64l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.49.41h3.84c.25 0 .45-.17.49-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.09.47 0 .59-.22l1.92-3.32c.12-.22.07-.5-.12-.64l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
                            </svg>
                            <span>Settings</span>
                        </a>
                        <a href="logout.php" class="dropdown-item logout">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content Area with Smooth Transitions -->
        <div class="content-area">
            <div class="content-main">
                <!-- Page content will be loaded here -->
