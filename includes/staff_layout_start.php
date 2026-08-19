<?php
ob_start();

// Read session timeout from database settings, default to 600 (10 minutes)
$__sessionLifetime = 600;
if (isset($pdo)) {
    try {
        $toStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'session_timeout' LIMIT 1");
        $toStmt->execute();
        $toVal = $toStmt->fetchColumn();
        if ($toVal !== false && $toVal !== null) {
            $minutes = (int)$toVal;
            if ($minutes > 0) {
                $__sessionLifetime = $minutes * 60;
            }
        }
    } catch (Exception $e) {}
}

// Also store as cookie for JS-based timeout tracking
$__sessionTimeoutMinutes = $__sessionLifetime / 60;

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => $__sessionLifetime,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    ini_set('session.cookie_lifetime', $__sessionLifetime);
    ini_set('session.gc_maxlifetime', $__sessionLifetime);
    session_start();
}

$__sessionNow = time();
$__sessionTimeoutSeconds = max(60, (int)$__sessionLifetime);

if (isset($_SESSION['session_expires_at']) && $__sessionNow > (int)$_SESSION['session_expires_at']) {
    session_unset();
    session_destroy();
    session_write_close();
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php?expired=1');
    exit();
}

if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = $__sessionNow;
}
if (!isset($_SESSION['session_expires_at'])) {
    $_SESSION['session_expires_at'] = $__sessionNow + $__sessionTimeoutSeconds;
}

$_SESSION['last_activity'] = $__sessionNow;
$_SESSION['session_expires_at'] = $__sessionNow + $__sessionTimeoutSeconds;

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php');
    exit();
}

$username = $_SESSION['username'] ?? 'Staff';
$userId = (int)($_SESSION['user_id'] ?? 0);

// Query DB for current first_name so header always shows the latest name
$dbFirstName = '';
if ($userId > 0 && isset($pdo)) {
    try {
        $nameStmt = $pdo->prepare("SELECT first_name FROM users WHERE id = ?");
        $nameStmt->execute([$userId]);
        $dbFirstName = trim($nameStmt->fetchColumn() ?? '');
    } catch (Exception $e) {
        $dbFirstName = '';
    }
}
$displayName = $dbFirstName ?: trim($_SESSION['display_name'] ?? 'Staff');

// Extract honorific if present and clean the name
$honorific = '';
if (preg_match('/^Dr\.?\s+/i', $displayName, $matches)) {
    $honorific = 'Dr. ';
    $cleanName = trim(substr($displayName, strlen($matches[0])));
} else {
    $cleanName = $displayName;
}

// Show only the first name in the profile header.
$nameParts = array_values(array_filter(explode(' ', $cleanName)));

if (count($nameParts) >= 1) {
    $processedName = $nameParts[0];
} else {
    $processedName = $username;
}

$displayHeaderName = $honorific . $processedName;

// Header notifications (lightweight counts)
$newAppointmentsToday = 0;
$pendingPaymentsCount = 0;
$upcomingAppointments = [];
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
        
        // Get upcoming appointments for today and tomorrow
        $upcomingStmt = $pdo->query("
            SELECT 
                a.id,
                a.appointment_date,
                a.appointment_time,
                s.name as service_name,
                CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                a.status
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            LEFT JOIN services s ON a.service_id = s.id
            WHERE a.appointment_date IN (CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY))
            ORDER BY a.appointment_date, a.appointment_time
            LIMIT 10
        ");
        $upcomingAppointments = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $newAppointmentsToday = 0;
    $pendingPaymentsCount = 0;
}

// Header notifications (database-driven)
$notifications = [];
$notificationTotal = 0;
try {
    if (isset($pdo)) {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        
        if ($userId > 0) {
            $notifStmt = $pdo->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $notifStmt->execute([$userId]);
            $notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $countStmt->execute([$userId]);
            $notificationTotal = (int)$countStmt->fetchColumn();
        }
        
        if (empty($notifications)) {
            $notificationTotal = $newAppointmentsToday + $pendingPaymentsCount;
        }
    }
} catch (Exception $e) {
    $notifications = [];
    $notificationTotal = 0;
}

$pageTitle = $pageTitle ?? 'Staff Dashboard';

function isActivePage($page) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    return strpos($page, $currentPage) !== false || $currentPage === basename($page);
}

// Load dynamic logo
try {
    $logoStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'welcome_logo'");
    $logoStmt->execute();
    $logoVal = $logoStmt->fetchColumn();
    $dynamicLogo = $logoVal ? '../../' . $logoVal : '../../assets/images/Logo.png';
} catch (Exception $e) {
    $dynamicLogo = '../../assets/images/Logo.png';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - RF Dental Clinic Staff</title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($dynamicLogo); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/staff_dashboard.css">
    <link rel="stylesheet" href="../../assets/css/appointments.css">
    <link rel="stylesheet" href="../../assets/css/mini_calendar.css">
    <style>
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
            right: -150px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4);
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
        .nav-dropdown {
            border-top: 1px solid #e5e7eb;
            margin-top: 8px;
            padding-top: 0;
        }
        .nav-dropdown:first-child {
            border-top: none;
            margin-top: 0;
        }
        .nav-dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            padding: 10px 20px;
            font-size: 11px;
            font-weight: 700;
            color: rgba(0, 0, 0, 0.4);
            text-transform: uppercase;
            letter-spacing: 1.2px;
            background: none;
            border: none;
            cursor: default;
        }
        .header-title h1 {
    font-size: 20px;
    color: #1f2937;
    font-weight: 600;
    line-height: 1.2;
    margin: 0;
}

.header-title p {
    font-size: 13px;
    color: #6b7280;
    line-height: 1.2;
    margin: 0;
}
        .nav-dropdown-content {
            overflow: hidden;
        }
        .nav-dropdown-content.open {
            max-height: 500px;
        }
        .nav-dropdown-item {
            padding-left: 28px !important;
        }
    </style>
</head>
<body data-user-id="<?php echo (int)($_SESSION['user_id'] ?? 0); ?>">
    <div class="page-loader"></div>

    <aside class="sidebar" id="staffSidebar">
        <div class="sidebar-logo">
            <img src="<?php echo htmlspecialchars($dynamicLogo); ?>" alt="RF Logo">
            <span>RF Dental Clinic</span>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-dropdown">
                <div class="nav-dropdown-toggle">
                    MAIN
                </div>
                <div class="nav-dropdown-content open">
                    <a href="staff-dashboard.php" class="nav-item nav-dropdown-item <?php echo isActivePage('staff-dashboard.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M3 12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1zm0 8a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1zm10 0a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-6a1 1 0 0 0-1 1zm1-17a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1z"/>
                            </svg>
                        </span>
                        <span>Dashboard</span>
                    </a>
                </div>
            </div>

            <div class="nav-dropdown">
                <div class="nav-dropdown-toggle">
                    OPERATIONS
                </div>
                <div class="nav-dropdown-content open">
                    <a href="staff_new_admission.php" class="nav-item nav-dropdown-item <?php echo isActivePage('staff_new_admission.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M15.25 18.75q.3 0 .525-.225T16 18t-.225-.525t-.525-.225t-.525.225T14.5 18t.225.525t.525.225m2.75 0q.3 0 .525-.225T18.75 18t-.225-.525T18 17.25t-.525.225t-.225.525t.525.225M5 21q-.825 0-1.412-.587T3 19V5q0-.825.588-1.412T5 3h14q.825 0 1.413.588T21 5v6.7q-.475-.225-.975-.387T19 11.075V5H5v14h6.05q.075.55.238 1.05t.387.95zm0-3v1V5v6.075V11zm2-1h4.075q.075-.525.238-1.025t.362-.975H7zm0-4h6.1q.8-.75 1.788-1.25T17 11.075V11H7zm0-4h10V7H7zm11 14q-2.075 0-3.537-1.463T13 18t1.463-3.537T18 13t3.538 1.463T23 18t-1.463 3.538T18 23"/>
                            </svg>
                        </span>
                        <span>New Admission</span>
                    </a>
                    <a href="staff_inquiries.php" class="nav-item nav-dropdown-item <?php echo isActivePage('staff_inquiries.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                        </span>
                        <span>Inquiries</span>
                    </a>
                    <a href="staff_queue.php" class="nav-item nav-dropdown-item <?php echo isActivePage('staff_queue.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M4 6H2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm6 12v-2H8v2h2zm2-2h2v2h-2v-2zm0-3a1 1 0 0 0 1-1H7a1 1 0 0 0-1 1v3h10V7a1 1 0 0 0-1-1h-5a1 1 0 0 0-1 1z"/>
                            </svg>
                        </span>
                        <span>Queue Management</span>
                    </a>
                    <a href="staff_appointments.php" class="nav-item nav-dropdown-item <?php echo isActivePage('staff_appointments.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3m1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/>
                            </svg>
                        </span>
                        <span>Appointments</span>
                    </a>
                    <a href="staff_dentist_schedule.php" class="nav-item nav-dropdown-item <?php echo isActivePage('staff_dentist_schedule.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2m0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8s8 3.59 8 8s-3.59 8-8 8m2.5-6.5l-3.5-2V7h1v4l3 1.75z"/>
                            </svg>
                        </span>
                        <span>Dentist Schedule</span>
                    </a>
                </div>
            </div>

            <div class="nav-dropdown">
                <div class="nav-dropdown-toggle">
                    PATIENTS &amp; BILLING
                </div>
                <div class="nav-dropdown-content open">
                    <a href="staff_patient_records.php" class="nav-item nav-dropdown-item <?php echo isActivePage('staff_patient_records.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3s1.34 3 3 3m-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5S5 6.34 5 8s1.34 3 3 3m0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5m8 0c-.29 0-.62.02-.97.05c1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5"/>
                            </svg>
                        </span>
                        <span>Patient Records</span>
                    </a>
                    <a href="staff_payment.php" class="nav-item nav-dropdown-item <?php echo isActivePage('staff_payment.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/>
                            </svg>
                        </span>
                        <span>Payment</span>
                    </a>
                    <a href="staff_prescriptions.php" class="nav-item nav-dropdown-item <?php echo isActivePage('staff_prescriptions.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="12" y1="18" x2="12" y2="12"></line>
                                <line x1="9" y1="15" x2="15" y2="15"></line>
                            </svg>
                        </span>
                        <span>Prescriptions</span>
                    </a>
                </div>
            </div>
        </nav>
    </aside>

    <main class="main-content">
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

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
                        <div class="notification-tabs">
                            <button class="notification-tab active" data-tab="all" onclick="filterNotifications('all', this)">
                                All<span class="tab-count"><?php echo count($notifications); ?></span>
                            </button>
                            <button class="notification-tab" data-tab="unread" onclick="filterNotifications('unread', this)">
                                Unread<span class="tab-count"><?php echo $notificationTotal; ?></span>
                            </button>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $notif): ?>
                                    <div class="notification-item <?php echo $notif['is_read'] ? 'read' : 'unread'; ?>" data-notif-id="<?php echo $notif['id']; ?>" data-is-read="<?php echo $notif['is_read']; ?>">
                                        <div class="notification-icon">
                                            <?php 
                                            $icons = [
                                                'appointment' => '📅',
                                                'payment' => '💰',
                                                'inquiry' => '💬',
                                                'patient' => '👤',
                                                'reminder' => '⏰',
                                                'queue' => '📋',
                                                'cancellation' => '❌',
                                                'system' => '⚙️'
                                            ];
                                            echo $icons[$notif['type']] ?? '🔔';
                                            ?>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-meta">
                                                <strong><?php echo htmlspecialchars($notif['title']); ?></strong>
                                                <div class="notification-kebab">
                                                    <button class="kebab-btn" onclick="toggleKebabMenu(event, this)">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                            <circle cx="12" cy="5" r="2"/>
                                                            <circle cx="12" cy="12" r="2"/>
                                                            <circle cx="12" cy="19" r="2"/>
                                                        </svg>
                                                    </button>
                                                    <div class="kebab-menu">
                                                        <?php if (!$notif['is_read']): ?>
                                                            <button onclick="markNotificationRead(<?php echo $notif['id']; ?>, '<?php echo $notif['action_url'] ?? ''; ?>', event)">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                                Mark as read
                                                            </button>
                                                        <?php endif; ?>
                                                        <button class="delete-btn" onclick="deleteNotification(<?php echo $notif['id']; ?>, event)">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                                            Delete
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <p><?php echo htmlspecialchars($notif['message']); ?></p>
                                            <small><?php echo date('M j, g:i A', strtotime($notif['created_at'])); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php if (!empty($upcomingAppointments)): ?>
                                    <div class="notification-item" style="background: #eff6ff; border-left: 3px solid #3b82f6;">
                                        <div class="notification-icon" style="background: #3b82f6;">📅</div>
                                        <div class="notification-text">
                                            <strong style="color: #1e40af;">Scheduled Appointments</strong>
                                            <small style="display: block; color: #6b7280;">Today & Tomorrow</small>
                                        </div>
                                    </div>
                                    <?php foreach ($upcomingAppointments as $apt): 
                                        $isToday = date('Y-m-d', strtotime($apt['appointment_date'])) === date('Y-m-d');
                                        $dayLabel = $isToday ? 'Today' : 'Tomorrow';
                                    ?>
                                    <div class="notification-item" onclick="window.location.href='staff_appointments.php'">
                                        <div class="notification-icon">🕐</div>
                                        <div class="notification-text">
                                            <strong><?php echo htmlspecialchars($apt['patient_name'] ?? 'Patient'); ?></strong>
                                            <small style="display: block;"><?php echo htmlspecialchars($apt['service_name'] ?? 'Appointment'); ?></small>
                                            <small style="color: #3b82f6; display: block;">
                                                <?php echo $dayLabel; ?> at <?php echo date('g:i A', strtotime($apt['appointment_time'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="notification-item">
                                        <div class="notification-icon">📅</div>
                                        <div class="notification-text">No appointments scheduled</div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <button class="see-all-btn" type="button" onclick="markAllNotificationsRead()">Mark all as read</button>
                    </div>
                </div>
                <div class="header-user-summary">
                    <div class="header-user-name"><?php echo htmlspecialchars($displayHeaderName); ?></div>
                    <div class="header-user-role"><?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? 'staff')); ?></div>
                </div>
                <div class="user-profile" id="userProfileDropdown">
                    <div class="user-profile-info">
                        <div class="user-avatar">
                            <?php
                            $profPic = $_SESSION['profile_picture'] ?? '';
                            if (!empty($profPic) && file_exists(dirname(__DIR__) . '/' . $profPic)):
                            ?>
                                <img src="../../<?php echo htmlspecialchars($profPic); ?>" alt="Profile" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                            <?php else:
                                $initial = strtoupper(substr($_SESSION['display_name'] ?? 'S', 0, 1));
                            ?>
                                <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#005daa,#0571ca);color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;"><?php echo htmlspecialchars($initial); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="user-profile-dropdown">
                        <div class="dropdown-divider"></div>
                        <a href="../staff/staff_profile.php" class="dropdown-item settings">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.64l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.49-.41h-3.84c-.25 0-.45.17-.49.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.09-.47 0-.59.22L2.74 8.87c-.12.22-.07.5.12.64l2.03 1.58c-.05.3-.07.62-.07.94 0 .33.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.64l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.49.41h3.84c.25 0 .45-.17.49-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.09.47 0 .59-.22l1.92-3.32c.12-.22.07-.5-.12-.64l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
                            </svg>
                            <span>Settings</span>
                        </a>
                        <a href="../../logout.php" class="dropdown-item logout">
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

        <div class="content-area">
            <div class="content-main">
