<?php

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

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php');
    exit();
}

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        if (!function_exists('purgeOldAuditLogs')) {
            require_once __DIR__ . '/audit_helper.php';
        }
        purgeOldAuditLogs($pdo);
        if (!function_exists('runAutoBackupIfDue')) {
            require_once __DIR__ . '/backup_helper.php';
        }
        runAutoBackupIfDue($pdo);
    } catch (Exception $e) {
    }
}

// Get user info from session
$username = $_SESSION['username'] ?? 'Admin';
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
$displayName = $dbFirstName ?: trim($_SESSION['display_name'] ?? 'Administrator');

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
 
// Header notifications (database-driven)
$notifications = [];
$notificationTotal = 0;
$newAppointmentsToday = 0;
$pendingPaymentsCount = 0;
$upcomingAppointments = [];
try {
    if (isset($pdo)) {
        // Get user ID for admin
        $userId = (int)($_SESSION['user_id'] ?? 0);
        
        // Get notifications from database
        if ($userId > 0) {
            $notifStmt = $pdo->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 20
            ");
            $notifStmt->execute([$userId]);
            $notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $countStmt->execute([$userId]);
            $notificationTotal = (int)$countStmt->fetchColumn();
        }
        
        // Fallback: lightweight counts for quick stats
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
        
        // If no database notifications, use fallback counts
        if (empty($notifications)) {
            $notificationTotal = $newAppointmentsToday + $pendingPaymentsCount;
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

// Get page title for the header
$pageTitle = $pageTitle ?? 'Admin Dashboard';

// Pjax fragment mode detection
$__pjax = isset($_GET['__pjax']) && $_GET['__pjax'] === '1';

// Hosting storage usage for the sidebar storage widget
require_once __DIR__ . '/storage_usage_helper.php';
$__storage = getStorageUsage($pdo ?? null);
$__storageUsedPct = $__storage === null ? 0 : (int)$__storage['used_pct'];
$__storageBarClass = '';
if ($__storageUsedPct >= 90) {
    $__storageBarClass = ' is-danger';
} elseif ($__storageUsedPct >= 75) {
    $__storageBarClass = ' is-warning';
}

// Helper function to check if current page matches
function isActivePage($page) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    return $currentPage === $page;
}
?>
<?php if (!$__pjax): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - RF Dental Clinic Admin</title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($dynamicLogo); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/notif_dropdown_mobile.css">
    <link rel="stylesheet" href="../../assets/css/appointments.css">
    <link rel="stylesheet" href="../../assets/css/mini_calendar.css">
    <style>
        /* Inquiry panel base state. Must stay in <head>: the full panel styles
           live at the bottom of the dashboard page, so without this the panel
           paints unstyled and visible until that block is parsed. */
        .dashboard-inquiry-panel {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            width: 400px;
            max-width: 100vw;
            background: #fff;
            z-index: 2002;
            display: flex;
            flex-direction: column;
            transform: translateX(100%);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.28s ease, visibility 0.28s;
        }
        .dashboard-inquiry-panel.open {
            transform: translateX(0);
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }
        .dashboard-inquiry-view {
            display: flex;
            flex: 1;
            min-height: 0;
            flex-direction: column;
        }
        .dashboard-inquiry-view[hidden] {
            display: none !important;
        }
        .dashboard-inquiry-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.4);
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.28s ease, visibility 0.28s;
        }
        .dashboard-inquiry-backdrop.open {
            opacity: 1;
            visibility: visible;
        }
        .dashboard-inquiry-fab {
            position: fixed;
            right: 24px;
            bottom: 24px;
            width: 58px;
            height: 58px;
            border: 0;
            border-radius: 50%;
            background: #0d92f7;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            z-index: 2001;
            cursor: pointer;
        }
        .dashboard-inquiry-fab-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            min-width: 22px;
            height: 22px;
            padding: 0 6px;
            border: 2px solid #fff;
            border-radius: 999px;
            background: #dc2626;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            align-items: center;
            justify-content: center;
        }
        .dashboard-inquiry-fab-badge:not([hidden]) {
            display: inline-flex;
        }

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
    <div id="userModal" class="modal-overlay">
        <div class="modal user-modal">
            <h2 id="modalTitle">Add New User</h2>
            <p class="modal-subtitle">Enter user details to add a new system user</p>
            
            <form id="userForm">
                <input type="hidden" id="userId">
                
                <div class="form-grid">
                    <input type="text" id="firstName" class="form-control" placeholder="First Name" required>
                    <input type="text" id="middleName" class="form-control" placeholder="Middle Name (Optional)">
                </div>

                <input type="text" id="lastName" class="form-control" placeholder="Last Name" required>

                <div class="form-grid">
                    <input type="text" id="username" class="form-control" placeholder="Username" required>
                    <input type="email" id="email" class="form-control" placeholder="Email (optional for patients)">
                </div>

                <input type="tel" id="phoneNumber" class="form-control" placeholder="Phone Number (11 digits)" maxlength="11" pattern="[0-9]{11}">

                <div class="form-grid">
                    <select id="gender" class="form-control">
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                    <div style="display:flex; flex-direction:column; gap:4px;">
                        <input type="date" id="dateOfBirth" class="form-control" max="<?php echo date('Y-m-d'); ?>">
                        <span id="computedAge" style="font-size:12px; color:#6b7280; display:none;"></span>
                    </div>
                </div>

                <div class="form-grid" style="align-items:flex-start; row-gap:12px;">
                    <div>
                        <div class="input-group">
                            <input type="password" id="password" class="form-control" placeholder="Password">
                            <button type="button" class="toggle-password" id="togglePassword" aria-label="Toggle password visibility" style="display: flex; top: 28px;">
                                <svg class="eye-icon eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="eye-icon eye-closed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </button>
                        </div>
                        <div id="strengthMeterAdmin" style="display:none; margin:8px 0 4px; text-align:left; font-size:12px; color:#475569;">
                            <div style="height:6px; border-radius:999px; background:#e2e8f0; overflow:hidden;">
                                <span id="strengthFillAdmin" style="display:block; height:100%; width:10%; background:#ef4444; transition:width 0.2s ease, background 0.2s ease;"></span>
                            </div>
                            <div id="strengthLabelAdmin" style="margin-top:6px; font-weight:600;">Weak password</div>
                        </div>
                    </div>
                    <div id="pwdRequirementsAdmin" style="display:none; background:rgba(15,23,42,0.04); border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px; text-align:left; margin:0; color:#0f172a;">
                        <div style="font-weight:700; margin-bottom:6px;">Password Requirements:</div>
                        <ul style="list-style:none; padding-left:0; margin:0; font-size:13px; line-height:1.55;">
                            <li data-rule="len" style="display:flex; gap:8px; align-items:center;"><span class="req-icon">✗</span>At least 8 characters</li>
                            <li data-rule="upper" style="display:flex; gap:8px; align-items:center;"><span class="req-icon">✗</span>At least one uppercase letter</li>
                            <li data-rule="lower" style="display:flex; gap:8px; align-items:center;"><span class="req-icon">✗</span>At least one lowercase letter</li>
                            <li data-rule="num" style="display:flex; gap:8px; align-items:center;"><span class="req-icon">✗</span>At least one number</li>
                            <li data-rule="sym" style="display:flex; gap:8px; align-items:center;"><span class="req-icon">✗</span>At least one symbol</li>
                        </ul>
                    </div>
                    <div class="input-group" style="grid-column: 1 / span 2;">
                        <input type="password" id="confirmPassword" class="form-control" placeholder="Confirm Password">
                        <button type="button" class="toggle-password" id="toggleConfirmPassword" aria-label="Toggle confirm password visibility" style="display: flex; top: 28px;">
                            <svg class="eye-icon eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="eye-icon eye-closed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
                    </div>
                </div>

                <select id="role" class="form-control" required>
                    <option value="">Select Role</option>
                    <option value="admin">Admin</option>
                    <option value="dentist">Dentist</option>
                    <option value="staff">Staff</option>
                    <option value="patient">Patient</option>
                </select>

                <select id="status" class="form-control" required data-edit-only="true">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                
                <div class="modal-actions">
                    <button type="submit" class="btn-primary">Save User</button>
                    <button type="button" class="btn-cancel" onclick="closeUserModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <aside class="sidebar" id="adminSidebar">
        <div class="sidebar-logo">
            <img src="<?php echo htmlspecialchars($dynamicLogo); ?>" alt="RF Logo">
            <span><?php echo htmlspecialchars($sidebarClinicName); ?></span>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-dropdown">
                <button class="nav-dropdown-toggle" onclick="toggleSection('main')" aria-expanded="true">
                    <span class="dropdown-arrow">&#9654;</span>
                    MAIN
                </button>
                <div class="nav-dropdown-content" id="section-main">
                    <a href="admin_dashboard.php" class="nav-item nav-dropdown-item <?php echo isActivePage('admin_dashboard.php') ? 'active' : ''; ?>">
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
                <button class="nav-dropdown-toggle" onclick="toggleSection('clinic-ops')" aria-expanded="true">
                    <span class="dropdown-arrow">&#9654;</span>
                    CLINIC OPERATIONS
                </button>
                <div class="nav-dropdown-content" id="section-clinic-ops">
                    <a href="admin_patients.php" class="nav-item nav-dropdown-item <?php echo isActivePage('admin_patients.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3s1.34 3 3 3m-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5S5 6.34 5 8s1.34 3 3 3m0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5m8 0c-.29 0-.62.02-.97.05c1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5"/>
                            </svg>
                        </span>
                        <span>Patient Records</span>
                    </a>
                    <a href="admin_patient_log.php" class="nav-item nav-dropdown-item <?php echo isActivePage('admin_patient_log.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM8 17h2.5L14 8h-2.5L8 17z"/>
                            </svg>
                        </span>
                        <span>Patient Logs</span>
                    </a>
                    <a href="admin_queue.php" class="nav-item nav-dropdown-item <?php echo isActivePage('admin_queue.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M4 6H2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm6 12v-2H8v2h2zm2-2h2v2h-2v-2zm0-3a1 1 0 0 0 1-1H7a1 1 0 0 0-1 1v3h10V7a1 1 0 0 0-1-1h-5a1 1 0 0 0-1 1z"/>
                            </svg>
                        </span>
                        <span>Queue Management</span>
                    </a>
                    <a href="admin_appointments.php" class="nav-item nav-dropdown-item <?php echo isActivePage('admin_appointments.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3m1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/>
                            </svg>
                        </span>
                        <span>Appointments</span>
                    </a>
                </div>
            </div>

            <div class="nav-dropdown">
                <button class="nav-dropdown-toggle" onclick="toggleSection('billing')" aria-expanded="true">
                    <span class="dropdown-arrow">&#9654;</span>
                    BILLING &amp; SERVICES
                </button>
                <div class="nav-dropdown-content" id="section-billing">
                    <a href="admin_payment.php" class="nav-item nav-dropdown-item <?php echo isActivePage('admin_payment.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/>
                            </svg>  
                        </span>
                        <span>Payment</span>
                    </a>
                    <a href="admin_payment_log.php" class="nav-item nav-dropdown-item <?php echo isActivePage('admin_payment_log.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM8 17h2.5L14 8h-2.5L8 17z"/>
                            </svg>
                        </span>
                        <span>Payment Logs</span>
                    </a>
                    <a href="admin_services.php" class="nav-item nav-dropdown-item <?php echo isActivePage('admin_services.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"/>
                            </svg>
                        </span>
                        <span>Services List</span>
                    </a>
                </div>
            </div>

            <div class="nav-dropdown">
                <button class="nav-dropdown-toggle" onclick="toggleSection('insights')" aria-expanded="true">
                    <span class="dropdown-arrow">&#9654;</span>
                    INSIGHTS &amp; DATA
                </button>
                <div class="nav-dropdown-content" id="section-insights">
                    <a href="admin_analytics.php" class="nav-item nav-dropdown-item <?php echo isActivePage('admin_analytics.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M3 22V8h4v14zm7 0V2h4v20zm7 0v-8h4v8z"/>
                            </svg>
                        </span>
                        <span>Analytics</span>
                    </a>
                    <a href="admin_reports.php" class="nav-item nav-dropdown-item <?php echo isActivePage('admin_reports.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
                            </svg>
                        </span>
                        <span>Reports</span>
                    </a>
                </div>
            </div>

            <div class="nav-dropdown">
                <button class="nav-dropdown-toggle" onclick="toggleSection('system-admin')" aria-expanded="true">
                    <span class="dropdown-arrow">&#9654;</span>
                    SYSTEM ADMINISTRATION
                </button>
                <div class="nav-dropdown-content" id="section-system-admin">
                    <a href="admin_users.php" class="nav-item nav-dropdown-item <?php echo isActivePage('admin_users.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                        </span>
                        <span>User Management</span>
                    </a>
                    <a href="admin_welcome_content.php" class="nav-item nav-dropdown-item <?php echo isActivePage('admin_welcome_content.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12zm-9-4h2v2h-2zm0-6h2v4h-2z"/>
                            </svg>
                        </span>
                        <span>Content</span>
                    </a>
                    <a href="admin_audit_trail.php" class="nav-item nav-dropdown-item <?php echo isActivePage('admin_audit_trail.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                            </svg>
                        </span>
                        <span>Audit Trail</span>
                    </a>
                    <a href="admin_archive.php" class="nav-item nav-dropdown-item <?php echo isActivePage('admin_archive.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M20.54 5.23l-1.39-1.68C18.88 3.21 18.47 3 18 3H6c-.47 0-.88.21-1.16.55L3.46 5.23C3.17 5.57 3 6.02 3 6.5V19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6.5c0-.48-.17-.93-.46-1.27zM12 17.5L6.5 12H10v-2h4v2h3.5L12 17.5zM5.12 5l.81-1h12l.94 1H5.12z"/>
                            </svg>
                        </span>
                        <span>Archive</span>
                    </a>
                    <a href="admin_settings.php" class="nav-item nav-dropdown-item <?php echo isActivePage('admin_settings.php') ? 'active' : ''; ?>">
                        <span class="nav-item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.64l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.49-.41h-3.84c-.25 0-.45.17-.49.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.09-.47 0-.59.22L2.74 8.87c-.12.22-.07.5.12.64l2.03 1.58c-.05.3-.07.62-.07.94 0 .33.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.64l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.49.41h3.84c.25 0 .45-.17.49-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.09.47 0 .59-.22l1.92-3.32c.12-.22.07-.5-.12-.64l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
                            </svg>
                        </span>
                        <span>Settings</span>
                    </a>
                </div>
            </div>
        </nav>

        <div class="sidebar-storage">
            <div class="sidebar-storage-head">
                <span class="sidebar-storage-title">Storage</span>
                <span class="sidebar-storage-pct"><?php echo $__storage === null ? '—' : $__storageUsedPct . '% used'; ?></span>
            </div>
            <div class="sidebar-storage-bar">
                <div class="sidebar-storage-bar-fill<?php echo $__storageBarClass; ?>" style="width: <?php echo $__storageUsedPct; ?>%;"></div>
            </div>
            <div class="sidebar-storage-meta"<?php echo $__storage === null ? '' : ' title="' . htmlspecialchars('Files: ' . formatStorageSize($__storage['files_bytes']) . ' · Database: ' . formatStorageSize($__storage['db_bytes'])) . '"'; ?>>
                <?php echo $__storage === null ? 'Unavailable' : htmlspecialchars(formatStorageSize($__storage['total_bytes']) . ' used of ' . formatStorageSize($__storage['quota_bytes'])); ?>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <header class="top-header">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle" type="button" aria-label="Toggle sidebar" aria-controls="adminSidebar" >
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="header-title">
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <p>Admin Panel - RF Dental Clinic</p>
                </div>
            </div>
            
            <div class="header-right">
                <div class="notif-wrapper" id="adminNotifWrapper">
                    <button type="button" class="notif-bell-btn<?php echo !empty($notifRinging) ? ' ringing' : ''; ?>" id="adminNotifBellBtn" aria-label="Notifications">
                        <i class="fas fa-bell"></i>
                        <span class="notif-badge" id="adminNotifBadge" <?php if (empty($notifShowBadge)) echo 'hidden'; ?>>
                            <?php echo $notificationTotal > 99 ? '99+' : $notificationTotal; ?>
                        </span>
                    </button>
                    <div class="notif-dropdown" id="adminNotifDropdown">
                        <div class="notif-header">
                            <h4>Notifications</h4>
                            <button type="button" class="notif-mark-all" id="adminNotifMarkAllBtn">Mark all read</button>
                        </div>
                        <div class="notif-list" id="adminNotifList">
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
                                        'appointment', 'approval', 'reminder', 'cancellation' => 'admin_appointments.php',
                                        'patient' => 'admin_patients.php',
                                        'queue' => 'admin_queue.php',
                                        'inquiry' => 'admin_dashboard.php',
                                        'payment' => 'admin_payment.php',
                                        default => 'admin_dashboard.php',
                                    };
                                    if (preg_match('/[?&]cancellation_request=(\d+)/', (string)($n['action_url'] ?? ''), $cancelMatch)) $notifHref .= '?cancellation_request=' . $cancelMatch[1];
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
                    <div class="header-user-role"><?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? 'admin')); ?></div>
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
                                $initial = strtoupper(substr($_SESSION['display_name'] ?? 'A', 0, 1));
                            ?>
                                <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#005daa,#0571ca);color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;"><?php echo htmlspecialchars($initial); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="user-profile-dropdown">
                        <a href="../admin/admin_profile.php" class="dropdown-item settings">
                            <svg xmlns="http://www.w3.org/2000/svg" height="32" width="32" viewBox="0 0 590 520" fill="currentColor"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.-->
                                <path d="M463 448.2C440.9 409.8 399.4 384 352 384L288 384C240.6 384 199.1 409.8 177 448.2C212.2 487.4 263.2 512 320 512C376.8 512 427.8 487.3 463 448.2zM64 320C64 178.6 178.6 64 320 64C461.4 64 576 178.6 576 320C576 461.4 461.4 576 320 576C178.6 576 64 461.4 64 320zM320 336C359.8 336 392 303.8 392 264C392 224.2 359.8 192 320 192C280.2 192 248 224.2 248 264C248 303.8 280.2 336 320 336z"/></svg>
                            <span>Profile</span>
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

        <script>
        function toggleSection(id) {
            var btn = document.querySelector('.nav-dropdown-toggle[onclick*="' + id + '"]');
            var content = document.getElementById('section-' + id);
            var expanded = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', !expanded);
            content.classList.toggle('open');
            try { localStorage.setItem('sidebar_section_' + id, expanded ? '0' : '1'); } catch(e) {}
        }
        document.addEventListener('DOMContentLoaded', function() {
            var sectionIds = ['main', 'clinic-ops', 'billing', 'insights', 'system-admin'];
            var currentPage = window.location.pathname.split('/').pop();
            sectionIds.forEach(function(id) {
                var content = document.getElementById('section-' + id);
                var btn = document.querySelector('.nav-dropdown-toggle[onclick*="' + id + '"]');
                var hasActive = content.querySelector('.nav-item.active') !== null;
                var stored = null;
                try { stored = localStorage.getItem('sidebar_section_' + id); } catch(e) {}
                var open = hasActive ? true : (stored !== null ? stored === '1' : true);
                if (open) {
                    content.classList.add('open');
                    btn.setAttribute('aria-expanded', 'true');
                } else {
                    btn.setAttribute('aria-expanded', 'false');
                }
            });
        });
        </script>
        <script>window.__shellBodyNodes = Array.prototype.slice.call(document.body.children);</script>
        <?php endif; ?>
        <div class="content-area"<?php echo $__pjax ? ' data-page-title="' . htmlspecialchars($pageTitle) . '"' : ''; ?>>
