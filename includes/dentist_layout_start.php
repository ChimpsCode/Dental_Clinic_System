<?php
/**
 * Dentist Layout - Centralized layout wrapper for all dentist pages
 * Ensures strict role-based separation: only dentists can access dentist pages
 * and Dentist Sidebar is always displayed regardless of which dentist page is loaded.
 */

ob_start();

require_once __DIR__ . '/session_bootstrap.php';

// Session already expired on entry (inactivity window elapsed) -> bounce to login
if (!empty($GLOBALS['APP_SESSION_EXPIRED'])) {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php?expired=1');
    exit();
}

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dentist') {
    ob_end_clean();
    header('Location: login.php');
    exit();
}

// Get user info from session
$username = $_SESSION['username'] ?? 'Dentist';
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
$displayName = $dbFirstName ?: trim($_SESSION['display_name'] ?? 'Dr. Dentist');

// Extract honorific if present and clean the name
$honorific = '';
if (preg_match('/^Dr\.?\s+/i', $displayName, $matches)) {
    $honorific = 'Dr. ';
    $cleanName = trim(substr($displayName, strlen($matches[0])));
} else {
    // We explicitly ensure 'Dr. ' is added for dentists if it wasn't already in their display name
    $honorific = 'Dr. ';
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
            WHERE (payment_status IN ('pending', 'unpaid', 'partial')
               OR (balance IS NOT NULL AND balance > 0))
              AND LOWER(COALESCE(payment_status, '')) != 'cancelled'
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
            $notifStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
            $notifStmt->execute([$userId]);
            $notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $countStmt->execute([$userId]);
            $notificationTotal = (int)$countStmt->fetchColumn();
        }
    }
} catch (Exception $e) {
    $notifications = [];
    $notificationTotal = 0;
}

$notifLatestAt = !empty($notifications[0]['created_at']) ? $notifications[0]['created_at'] : null;
$notifSeenAt = $_SESSION['notif_panel_seen_at'] ?? null;
$notifSeenAt = (is_string($notifSeenAt) && trim($notifSeenAt) !== '') ? $notifSeenAt : null;
$notifUnseen = !$notifSeenAt || ($notifLatestAt && strtotime((string)$notifLatestAt) > strtotime((string)$notifSeenAt));
$notifRinging = $notifUnseen && $notifLatestAt && (time() - strtotime($notifLatestAt) < 60);
$notifShowBadge = $notificationTotal > 0 && $notifUnseen;

// Get page title for header
$pageTitle = $pageTitle ?? 'Dentist Dashboard';

$__pjax = isset($_GET['__pjax']) && $_GET['__pjax'] === '1';

// Helper function to check if current page matches
function isActivePage($page) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    return $currentPage === $page;
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

$sidebarClinicName = 'RF Dental Clinic';
try {
    $nameStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'clinic_name'");
    $nameStmt->execute();
    $savedClinicName = trim((string)$nameStmt->fetchColumn());
    if ($savedClinicName !== '') {
        $sidebarClinicName = $savedClinicName;
    }
} catch (Exception $e) {
}
?>
<?php if (!$__pjax): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - RF Dental Clinic Dentist</title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($dynamicLogo); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dentist_dashboard.css">
    <link rel="stylesheet" href="../../assets/css/notif_dropdown_mobile.css">
    <link rel="stylesheet" href="../../assets/css/appointments.css">
    <link rel="stylesheet" href="../../assets/css/mini_calendar.css">
    <link rel="stylesheet" href="../../assets/css/mobile_count_cards.css">
    <link rel="preload" as="image" href="../../assets/icons/waiting.png">
    <link rel="preload" as="image" href="../../assets/icons/in-procedure.png">
    <link rel="preload" as="image" href="../../assets/icons/check.png">
    <link rel="preload" as="image" href="../../assets/icons/pause.png">
    <style>
        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            z-index: 10;
        }
        .header-notifications {
            position: relative;
            z-index: 10;
        }
        .user-profile {
            position: relative;
            z-index: 100;
            cursor: pointer;
        }
        .user-profile-dropdown {
            z-index: 101 !important;
        }
        /* Dentist Notification Bell — same style as patient widget */
        .notif-wrapper { position: relative; }
        .notif-bell-btn {
            position: relative; background: none; border: none; cursor: pointer;
            font-size: 20px; color: #6b7280; padding: 6px; border-radius: 8px;
            transition: background 0.15s, color 0.15s;
        }
        .notif-bell-btn:hover { background: #f3f4f6; color: #1f2937; }
        .notif-bell-btn.ringing > i {
            display: inline-block;
            transform-origin: top center;
            animation: notifBellRing 1.6s ease-in-out infinite;
        }
        @keyframes notifBellRing {
            0%  { transform: rotate(0deg); }
            8%  { transform: rotate(14deg); }
            16% { transform: rotate(-14deg); }
            24% { transform: rotate(14deg); }
            32% { transform: rotate(-14deg); }
            40%, 100% { transform: rotate(0deg); }
        }
        .notif-badge {
            position: absolute; top: 0; right: 0; min-width: 18px; height: 18px;
            background: #ef4444; color: #fff; font-size: 10px; font-weight: 700;
            border-radius: 999px; display: inline-flex; align-items: center;
            justify-content: center; padding: 0 4px; line-height: 1;
            pointer-events: none; transform: translate(40%, -30%);
        }
        .notif-badge[hidden] { display: none; }
        .notif-dropdown {
            display: none; position: absolute; top: calc(100% + 8px); right: 0;
            width: 370px; max-height: 440px; background: #fff; border: 1px solid #e5e7eb;
            border-radius: 12px; box-shadow: 0 12px 40px rgba(0,0,0,0.12); z-index: 99998;
            overflow: hidden; flex-direction: column;
        }
        .notif-dropdown.open { display: flex; }
        .notif-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 16px; border-bottom: 1px solid #f3f4f6;
        }
        .notif-header h4 { margin: 0; font-size: 15px; font-weight: 700; color: #1f2937; }
        .notif-mark-all {
            background: none; border: none; color: #2563eb; font-size: 12px;
            font-weight: 600; cursor: pointer; padding: 0;
        }
        .notif-mark-all:hover { text-decoration: underline; }
        .notif-list { overflow-y: auto; max-height: 370px; }
        .notif-item {
            display: flex; gap: 10px; padding: 12px 16px; border-bottom: 1px solid #f9fafb;
            cursor: pointer; transition: background 0.12s; text-decoration: none; color: inherit;
        }
        .notif-item:hover { background: #f9fafb; }
        .notif-item.unread { background: #eff6ff; }
        .notif-item.unread:hover { background: #dbeafe; }
        .notif-icon {
            width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 14px; color: #fff;
        }
        .notif-icon.queue        { background: #f59e0b; }
        .notif-icon.reminder     { background: #8b5cf6; }
        .notif-icon.payment      { background: #ef4444; }
        .notif-icon.inquiry      { background: #3b82f6; }
        .notif-icon.appointment  { background: #10b981; }
        .notif-icon.approval     { background: #f97316; }
        .notif-icon.system       { background: #6b7280; }
        .notif-icon.cancellation { background: #dc2626; }
        .notif-icon.patient      { background: #06b6d4; }
        .notif-content { flex: 1; min-width: 0; }
        .notif-title { font-size: 13px; font-weight: 600; color: #1f2937; margin: 0; }
        .notif-msg   { font-size: 12px; color: #6b7280; margin: 2px 0 0; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .notif-time  { font-size: 11px; color: #9ca3af; margin-top: 3px; }
        .notif-empty { text-align: center; padding: 32px 16px; color: #9ca3af; font-size: 13px; }
        .notif-empty i { display: block; font-size: 28px; margin-bottom: 8px; color: #d1d5db; }
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
    <div id="workspaceSplash" style="position:fixed;inset:0;z-index:100000;background:none;background-color:transparent;backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);display:flex;align-items:center;justify-content:center;font-family:Inter,system-ui,sans-serif;">
        <div style="text-align:center;color:#0f172a;">
            <div style="width:36px;height:36px;margin:0 auto 14px;border:3px solid #dbe3ee;border-top-color:#005daa;border-radius:50%;animation:workspaceSpin .8s linear infinite;"></div>
            <p style="margin:0;font-size:15px;font-weight:600;">Initializing workspace...</p>
        </div>
    </div>
    <style>@keyframes workspaceSpin{to{transform:rotate(360deg)}}</style>
    <aside class="sidebar" id="dentistSidebar">
        <div class="sidebar-logo">
            <img src="<?php echo htmlspecialchars($dynamicLogo); ?>" alt="RF Logo">
            <span><?php echo htmlspecialchars($sidebarClinicName); ?></span>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-dropdown">
                <div class="nav-dropdown-toggle" style="cursor:default;">
                    MAIN
                </div>
                <div class="nav-dropdown-content open">
                    <a href="dentist_dashboard.php" class="nav-item nav-dropdown-item <?php echo isActivePage('dentist_dashboard.php') ? 'active' : ''; ?>">
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
                <div class="nav-dropdown-toggle" style="cursor:default;">
                    PATIENT CARE
                </div>
                <div class="nav-dropdown-content open">
                    <a href="dentist_patients.php" class="nav-item nav-dropdown-item <?php echo isActivePage('dentist_patients.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3s1.34 3 3 3m-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5S5 6.34 5 8s1.34 3 3 3m0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5m8 0c-.29 0-.62.02-.97.05c1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5"/>
                            </svg>
                        </span>
                        <span>Patient Records</span>
                    </a>
                    <a href="dentist_treatments.php" class="nav-item nav-dropdown-item <?php echo isActivePage('dentist_treatments.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                        </span>
                        <span>Treatment Plan</span>
                    </a>
                    <a href="dentist_consultation.php" class="nav-item nav-dropdown-item <?php echo isActivePage('dentist_consultation.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 3c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm7 13H5v-.23c0-.62.28-1.2.76-1.58C7.47 15.82 9.64 15 12 15s4.53.82 6.24 2.19c.48.38.76.97.76 1.58V19z"/>
                            </svg>
                        </span>
                        <span>Consultation</span>
                    </a>
                    <a href="dentist_prescriptions.php" class="nav-item nav-dropdown-item <?php echo isActivePage('dentist_prescriptions.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg fill="currentColor" width="24" height="24" viewBox="0 0 256 256" xmlns="http://www.w3.org/2000/svg">
                                <path d="M188.9707,188l19.51465-19.51465a12.0001,12.0001,0,0,0-16.9707-16.9707L172,171.0293l-34.01074-34.01062A55.99228,55.99228,0,0,0,120,28H72A12,12,0,0,0,60,40V192a12,12,0,0,0,24,0V140h23.0293l48,48-19.51465,19.51465a12.0001,12.0001,0,0,0,16.9707,16.9707L172,204.9707l19.51465,19.51465a12.0001,12.0001,0,0,0,16.9707-16.9707ZM84,52h36a32,32,0,0,1,0,64H84Z"/>
                            </svg>
                        </span>
                        <span>Prescriptions</span>
                    </a>
                </div>
            </div>

            <div class="nav-dropdown">
                <div class="nav-dropdown-toggle" style="cursor:default;">
                    OPERATIONS
                </div>
                <div class="nav-dropdown-content open">
                    <a href="dentist_appointments.php" class="nav-item nav-dropdown-item <?php echo isActivePage('dentist_appointments.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3m1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/>
                            </svg>
                        </span>
                        <span>Appointments</span>
                    </a>
                    <a href="dentist_queue.php" class="nav-item nav-dropdown-item <?php echo isActivePage('dentist_queue.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M4 6H2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm6 12v-2H8v2h2zm2-2h2v2h-2v-2zm0-3a1 1 0 0 0 1-1H7a1 1 0 0 0-1 1v3h10V7a1 1 0 0 0-1-1h-5a1 1 0 0 0-1 1z"/>
                            </svg>
                        </span>
                        <span>Queue Management</span>
                    </a>
                </div>
            </div>
        </nav>
        
    </aside>

    <main class="main-content">
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <header class="top-header">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle" type="button" aria-label="Toggle sidebar" aria-controls="dentistSidebar">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="header-title">
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <p>Dentist Panel - RF Dental Clinic</p>
                </div>
            </div>
            <div class="header-right">
                <!-- Dentist Notification Bell (same design as patient widget) -->
                <div class="notif-wrapper" id="dentistNotifWrapper">
                    <button type="button" class="notif-bell-btn<?php echo !empty($notifRinging) ? ' ringing' : ''; ?>" id="dentistNotifBellBtn" aria-label="Notifications">
                        <i class="fas fa-bell"></i>
                        <span class="notif-badge" id="dentistNotifBadge" <?php if (empty($notifShowBadge)) echo 'hidden'; ?>>
                            <?php echo $notificationTotal > 99 ? '99+' : $notificationTotal; ?>
                        </span>
                    </button>
                    <div class="notif-dropdown" id="dentistNotifDropdown">
                        <div class="notif-header">
                            <h4>Notifications</h4>
                            <button type="button" class="notif-mark-all" id="dentistNotifMarkAllBtn">Mark all read</button>
                        </div>
                        <div class="notif-list" id="dentistNotifList">
                            <?php if (empty($notifications)): ?>
                                <div class="notif-empty"><i class="fas fa-bell-slash"></i>No notifications yet</div>
                            <?php else: ?>
                                <?php foreach ($notifications as $n):
                                    $typeClass = htmlspecialchars($n['type'] ?? 'system');
                                    $iconMap = ['queue'=>'fa-layer-group','reminder'=>'fa-calendar-check','payment'=>'fa-credit-card','inquiry'=>'fa-comment-dots','appointment'=>'fa-calendar','approval'=>'fa-hourglass-half','cancellation'=>'fa-times-circle','patient'=>'fa-user-plus','system'=>'fa-info-circle'];
                                    $icon = $iconMap[$n['type']] ?? 'fa-bell';
                                    $unread = empty($n['is_read']) ? ' unread' : '';
                                    $diff = time() - strtotime($n['created_at']);
                                    if ($diff < 60) $timeAgo = 'Just now';
                                    elseif ($diff < 3600) $timeAgo = floor($diff/60).'m ago';
                                    elseif ($diff < 86400) $timeAgo = floor($diff/3600).'h ago';
                                    else $timeAgo = floor($diff/86400).'d ago';
                                    $notifType = strtolower((string)($n['type'] ?? 'system'));
                                    $notifHref = match ($notifType) {
                                        'appointment', 'approval', 'reminder', 'cancellation' => 'dentist_appointments.php',
                                        'patient' => 'dentist_patients.php',
                                        'queue' => 'dentist_queue.php',
                                        'inquiry' => 'dentist_consultation.php',
                                        'payment' => 'dentist_treatments.php',
                                        default => 'dentist_dashboard.php',
                                    };
                                ?>
                                <a class="notif-item<?php echo $unread; ?>" href="<?php echo htmlspecialchars($notifHref); ?>" data-notif-id="<?php echo (int)$n['id']; ?>" data-notif-type="<?php echo htmlspecialchars($notifType); ?>">
                                    <div class="notif-icon <?php echo $typeClass; ?>"><i class="fas <?php echo $icon; ?>"></i></div>
                                    <div class="notif-content">
                                        <p class="notif-title"><?php echo htmlspecialchars($n['title']); ?></p>
                                        <p class="notif-msg"><?php echo htmlspecialchars($n['message']); ?></p>
                                        <div class="notif-time"><?php echo $timeAgo; ?></div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="header-user-summary">
                    <div class="header-user-name"><?php echo htmlspecialchars($displayHeaderName); ?></div>
                    <div class="header-user-role"><?php echo htmlspecialchars('Dentist'); ?></div>
                </div>
                <div class="user-profile" id="userProfileDropdown" onclick="var menu=document.getElementById('userProfileDropdownMenu');menu.style.display=menu.style.display==='none'?'':'none';">
                    <div class="user-profile-info">
                        <div class="user-avatar">
                            <?php
                            $profPic = $_SESSION['profile_picture'] ?? '';
                            if (!empty($profPic) && file_exists(dirname(__DIR__) . '/' . $profPic)):
                            ?>
                                <img src="../../<?php echo htmlspecialchars($profPic); ?>" alt="Profile" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                            <?php else:
                                $initial = strtoupper(substr($_SESSION['display_name'] ?? 'D', 0, 1));
                            ?>
                                <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#005daa,#0571ca);color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;"><?php echo htmlspecialchars($initial); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="user-profile-dropdown" id="userProfileDropdownMenu" style="display:none;">
                        <div class="dropdown-divider"></div>
                        <a href="../dentist/dentist_profile.php" class="dropdown-item settings">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.64l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.49-.41h-3.84c-.25 0-.45.17-.49.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.09-.47 0-.59.22L2.74 8.87c-.12.22-.07.5.12.64l2.03 1.58c-.05.3-.07.62-.07.94 0 .33.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.64l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.49.41h3.84c.25 0 .45-.17.49-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.09.47 0 .59-.22l1.92-3.32c.12-.22.07-.5-.12-.64l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
                            </svg>
                            <span>Profile & Settings</span>
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
        <script>window.__shellBodyNodes = Array.prototype.slice.call(document.body.children);</script>
<?php endif; ?>
        <div class="content-area"<?php echo $__pjax ? ' data-page-title="' . htmlspecialchars($pageTitle) . '"' : ''; ?>>
            <div class="content-main">
