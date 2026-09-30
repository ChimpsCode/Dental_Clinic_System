<?php
ob_start();

require_once __DIR__ . '/../../includes/session_bootstrap.php';

if (!empty($GLOBALS['APP_SESSION_EXPIRED']) || ($GLOBALS['APP_ACCOUNT_REMOVED'] ?? '') !== '') {
    ob_end_clean();
    header('Location: ' . app_session_login_url());
    exit();
}

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php');
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
$patient_id = $_SESSION['patient_id'] ?? null;
$patient = null;
$upcoming_appointments = [];
$past_appointments = [];
$bookedDateSlots = [];
$patient_name = 'Patient';

require_once '../../config/database.php';
require_once '../../includes/patient_session_helper.php';
$patientSessionSettings = getPatientSessionSettings($pdo, (int)($_SESSION['user_id'] ?? 0));

// Resolve patient_id from user account if session has no valid link
if ($user_id) {
    try {
        $hasPatientUserId = (bool)$pdo->query("SHOW COLUMNS FROM patients LIKE 'user_id'")->fetch(PDO::FETCH_ASSOC);

        if ($hasPatientUserId && !$patient_id) {
            $resolveStmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ? AND is_archived = 0 LIMIT 1");
            $resolveStmt->execute([$user_id]);
            $resolved = $resolveStmt->fetch(PDO::FETCH_ASSOC);
            if ($resolved) {
                $patient_id = (int)$resolved['id'];
                $_SESSION['patient_id'] = $patient_id;
            }
        }

        if (!$patient_id) {
            $userStmt = $pdo->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
            $userStmt->execute([$user_id]);
            $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($userInfo['email'])) {
                $resolveStmt = $pdo->prepare("SELECT id FROM patients WHERE email = ? AND is_archived = 0 LIMIT 1");
                $resolveStmt->execute([$userInfo['email']]);
                $resolved = $resolveStmt->fetch(PDO::FETCH_ASSOC);
                if ($resolved) {
                    $patient_id = (int)$resolved['id'];
                    $_SESSION['patient_id'] = $patient_id;
                }
            }
        }

        if (!$patient_id) {
            $userStmt = $pdo->prepare("SELECT phone FROM users WHERE id = ? LIMIT 1");
            $userStmt->execute([$user_id]);
            $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($userInfo['phone'])) {
                $resolveStmt = $pdo->prepare("SELECT id FROM patients WHERE phone = ? AND is_archived = 0 LIMIT 1");
                $resolveStmt->execute([$userInfo['phone']]);
                $resolved = $resolveStmt->fetch(PDO::FETCH_ASSOC);
                if ($resolved) {
                    $patient_id = (int)$resolved['id'];
                    $_SESSION['patient_id'] = $patient_id;
                }
            }
        }
    } catch (Exception $e) {}
}

// Get patient data
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if ($patient) {
    $firstName = trim($patient['first_name'] ?? '');
    $lastName = trim($patient['last_name'] ?? '');
    $patient_name = $firstName ?: 'Patient';
    // Avatar: First letter of first name + last name (e.g., "John Doe" → "JD")
    $avatar_initial = strtoupper(substr($firstName, 0, 1)) . strtoupper(substr($lastName, 0, 1));
}

// Check if is_archived column exists on appointments
$hasArchivedCol = false;
try {
    $checkArchived = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'is_archived'");
    $hasArchivedCol = $checkArchived && $checkArchived->rowCount() > 0;
} catch (Exception $e) { $hasArchivedCol = false; }

// Get appointments for this patient
$archivedFilter = $hasArchivedCol ? " AND a.is_archived = 0" : "";
$stmt = $pdo->prepare("
    SELECT a.*, 
           s.name as service_name,
           COALESCE(a.status, 'Scheduled') as status_name
    FROM appointments a
    LEFT JOIN services s ON a.service_id = s.id
    WHERE a.patient_id = ?{$archivedFilter}
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute([$patient_id]);
$all_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate upcoming and past
$today = date('Y-m-d');
$completedStatuses = ['completed', 'cancelled'];
foreach ($all_appointments as $apt) {
    $apt_date = substr($apt['appointment_date'], 0, 10);
    $apt_status = strtolower(trim($apt['status'] ?? ''));
    if (in_array($apt_status, $completedStatuses)) {
        $past_appointments[] = $apt;
    } elseif ($apt_date >= $today) {
        $upcoming_appointments[] = $apt;
    } else {
        $past_appointments[] = $apt;
    }
}

// Get booked dates for calendar (any active booking that day, excluding cancelled/completed/archived)
$stmt = $pdo->query("SELECT appointment_date, appointment_time FROM appointments WHERE appointment_date >= CURDATE() AND COALESCE(is_archived, 0) = 0 AND deleted_at IS NULL AND LOWER(COALESCE(status, '')) NOT IN ('cancelled', 'canceled', 'completed')");
$bookedDates = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($bookedDates as $booked) {
    $date = date('Y-m-d', strtotime($booked['appointment_date']));
    $time = date('H:i', strtotime($booked['appointment_time']));
    if (!isset($bookedDateSlots[$date])) {
        $bookedDateSlots[$date] = [];
    }
    $bookedDateSlots[$date][] = $time;
}

require_once '../../includes/medical_certificate_helper.php';
ensureMedicalCertificateService($pdo);

// Get services for booking
$stmt = $pdo->prepare("SELECT id, name, duration_minutes, price FROM services WHERE is_active = 1 ORDER BY name");
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'My Appointments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - RF Dental Clinic</title>
    <link rel="icon" type="image/png" href="../../assets/images/Logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #f5f7fa; min-height: 100vh; display: flex; }
        .sidebar { width: 220px; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); padding: 32px 0; position: fixed; left: 0; top: 0; height: 100vh; overflow-y: auto; color: white; box-shadow: 2px 0 8px rgba(0,0,0,0.1); z-index: 40; }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 0 24px; margin-bottom: 40px; text-decoration: none; color: white; }
        .sidebar-brand img { height: 32px; width: auto; }
        .sidebar-brand span { font-size: 16px; font-weight: 700; white-space: nowrap; }
        .sidebar-menu { display: flex; flex-direction: column; gap: 8px; padding: 0 12px; }
        .menu-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; color: rgba(255,255,255,0.7); text-decoration: none; border-radius: 8px; transition: all 0.2s; cursor: pointer; font-size: 14px; font-weight: 500; }
        .menu-item:hover { background: rgba(255,255,255,0.1); color: white; }
        .menu-item.active { background: rgba(255,255,255,0.2); color: white; font-weight: 600; }
        .menu-item i { width: 18px; text-align: center; }
        .main-wrapper { flex: 1; margin-left: 220px; display: flex; flex-direction: column; min-height: 100vh; }
        .dashboard-header { background: white; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; position: sticky; top: 0; z-index: 50; height: 70px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .header-right { display: flex; align-items: center; gap: 16px; }
        .user-profile { position: relative; display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #005daa, #0571ca); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; flex-shrink: 0; }
        .user-details { display: flex; flex-direction: column; }
        .user-name { font-weight: 600; font-size: 14px; color: #1a1a2e; }
        .user-role { font-size: 12px; color: #6b7280; }
        .dropdown-toggle { font-size: 16px; color: #6b7280; cursor: pointer; }
        .user-dropdown { position: absolute; top: 100%; right: 0; margin-top: 8px; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 180px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s; z-index: 1000; }
        .user-profile:hover .user-dropdown { opacity: 1; visibility: visible; transform: translateY(0); }
        .dropdown-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #374151; text-decoration: none; transition: background 0.2s; font-size: 14px; }
        .dropdown-item:hover { background: #f3f4f6; }
        .dropdown-item.logout { color: #dc3545; border-top: 1px solid #e5e7eb; }
        .dashboard-main { flex: 1; padding: 24px 32px; max-width: 1400px; width: 100%; margin: 0 auto; }
        .page-header { margin-bottom: 24px; }
        .page-title { font-size: 28px; font-weight: 700; color: #1a1a2e; margin-bottom: 8px; }
        .page-subtitle { color: #6b7280; font-size: 14px; }
        
        .tabs { display: flex; gap: 8px; border-bottom: 2px solid #e5e7eb; margin-bottom: 24px; }
        .tab-btn { padding: 12px 24px; background: none; border: none; font-size: 14px; font-weight: 500; color: #6b7280; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s; }
        .tab-btn:hover { color: #1f2937; }
        .tab-btn.active { color: #005daa; border-bottom-color: #005daa; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        
        .booking-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        
        .calendar-box { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .calendar-title { font-size: 20px; font-weight: 700; color: #1a1a2e; }
        .calendar-nav { display: flex; gap: 8px; }
        .calendar-nav button { background: #f3f4f6; border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: all 0.2s; }
        .calendar-nav button:hover { background: #e5e7eb; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
        .calendar-day-header { text-align: center; font-size: 12px; font-weight: 600; color: #6b7280; padding: 8px 0; text-transform: uppercase; }
        .calendar-day { position: relative; min-height: 46px; width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; font-size: 13px; border-radius: 8px; cursor: pointer; transition: all 0.2s; font-weight: 500; border: 1px solid #e5e7eb; background: white; }
        .calendar-day .day-num { display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; line-height: 1; }
        .calendar-day .day-dot { width: 5px; height: 5px; border-radius: 50%; background: #2563eb; flex-shrink: 0; }
        .calendar-day:hover:not(.disabled) { background: #f0f7ff; border-color: #005daa; }
        .calendar-day.disabled { color: #d1d5db; cursor: not-allowed; background: #f9fafb; }
        .calendar-day.disabled .day-num { color: #d1d5db; }
        .calendar-day.has-appointment { background: #78acff; border-color: #3b82f6; }
        .calendar-day.has-appointment .day-num { color: #fff; }
        .calendar-day.has-appointment .day-dot { background: #fff; }
        .calendar-day.today { background: #eff6ff; border-color: #bfdbfe; }
        .calendar-day.today .day-num { background: #249d77; color: #fff; font-weight: 700; }
        .calendar-day.today.has-appointment .day-dot { background: #2563eb; }
        .calendar-day.selected { background: #226eab; border-color: #005daa; }
        .calendar-day.selected .day-num { color: #fff; }
        .calendar-day.selected .day-dot { background: #fff; }
        .calendar-day.sunday { background: #fef3c7; color: #92400e; border-color: #fef3c7; }
        .calendar-day.sunday .day-num { color: #92400e; }
        
        .booking-box { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); height: fit-content; }
        .booking-title { font-size: 18px; font-weight: 700; color: #1a1a2e; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .booking-title i { color: #005daa; }
        
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px; }
        .form-select { width: 100%; padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; background: white; cursor: pointer; }
        .form-select:focus { outline: none; border-color: #005daa; box-shadow: 0 0 0 3px rgba(0,93,170,0.1); }
        .form-textarea { width: 100%; padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; font-family: inherit; resize: vertical; min-height: 80px; }
        .form-textarea:focus { outline: none; border-color: #005daa; box-shadow: 0 0 0 3px rgba(0,93,170,0.1); }
        
        .time-slots { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 12px 0; }
        .time-slot { padding: 12px 8px; text-align: center; background: #f3f4f6; border: 2px solid transparent; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.2s; }
        .time-slot:hover:not(.unavailable) { background: #e5e7eb; border-color: #005daa; }
        .time-slot.available { background: #d1fae5; color: #065f46; }
        .time-slot.available:hover { background: #a7f3d0; }
        .time-slot.selected { background: #005daa; color: white; border-color: #005daa; }
        .time-slot.unavailable { background: #fee2e2; color: #991b1b; cursor: not-allowed; opacity: 0.7; }
        
        .btn-book { width: 100%; padding: 14px 24px; background: #005daa; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 16px; }
        .btn-book:hover { background: #004080; }
        .btn-book:disabled { background: #9ca3af; cursor: not-allowed; }
        
        .selected-date { font-size: 20px; font-weight: 700; color: #005daa; margin-bottom: 16px; text-align: center; }
        
        .legend { display: flex; gap: 16px; margin-top: 16px; font-size: 12px; color: #6b7280; flex-wrap: wrap; align-items: center; }
        .legend-item { display: flex; align-items: center; gap: 6px; }
        .legend-dot { width: 14px; height: 14px; border-radius: 4px; position: relative; flex-shrink: 0; }
        .legend-today { background: #d1fae5; border: 1px solid #a7f3d0; }
        .legend-today::after { content: ''; position: absolute; inset: 0; margin: auto; width: 10px; height: 10px; border-radius: 50%; background: #249d77; }
        .legend-selected { background: #005daa; border: 1px solid #005daa; }
        .legend-appointment { background: #3b82f6; border: 1px solid #3b82f6; }
        .legend-appointment::after { content: ''; position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 6px; height: 6px; border-radius: 50%; background: #fff; }
        .legend-available { background: white; border: 1px solid #e5e7eb; }
        .legend-closed { background: #fef3c7; border: 1px solid #fef3c7; }
        
        .empty-state { text-align: center; padding: 48px 24px; color: #6b7280; }
        .empty-state i { font-size: 48px; margin-bottom: 16px; color: #d1d5db; }
        .empty-state h3 { font-size: 18px; color: #374151; margin-bottom: 8px; }
        
        .appointment-card { display: flex; gap: 16px; padding: 20px; border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 16px; align-items: center; }
        .apt-date { min-width: 80px; text-align: center; padding: 12px; background: #f0f7ff; border-radius: 8px; }
        .apt-month { font-size: 12px; font-weight: 600; color: #005daa; text-transform: uppercase; }
        .apt-day { font-size: 28px; font-weight: 700; color: #1a1a2e; }
        .apt-year { font-size: 12px; color: #6b7280; }
        .apt-details { flex: 1; }
        .apt-time { font-size: 14px; color: #6b7280; margin-bottom: 8px; }
        .apt-treatment { font-size: 16px; font-weight: 600; color: #1a1a2e; margin-bottom: 4px; }
        .apt-dentist { font-size: 14px; color: #6b7280; }
        .apt-notes { font-size: 12px; color: #6b7280; margin-top: 4px; }
        .apt-status { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-scheduled { background: #e0f2fe; color: #0369a1; }
        .status-pending-approval { background: #fef3c7; color: #b45309; }
        .status-in-queue { background: #fef3c7; color: #92400e; }
        .status-pending-payment { background: #fed7aa; color: #c2410c; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal-overlay.active { display: flex; }
        .modal { background: white; border-radius: 16px; max-width: 500px; width: 90%; padding: 24px; animation: modalSlideIn 0.3s ease; }
        @keyframes modalSlideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal h3 { font-size: 20px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
        .modal-success { color: #10b981; }
        .modal-close { float: right; background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280; }
        .modal-close:hover { color: #1a1a2e; }
        .modal-content { text-align: center; padding: 20px 0; }
        .modal-content i { font-size: 64px; color: #10b981; margin-bottom: 16px; }
        .modal-content h4 { font-size: 18px; color: #1a1a2e; margin-bottom: 8px; }
        .modal-content p { color: #6b7280; margin-bottom: 16px; }
        .modal-details { background: #f3f4f6; padding: 16px; border-radius: 8px; text-align: left; margin-top: 16px; }
        .modal-details p { margin-bottom: 8px; font-size: 14px; }
        .modal-details strong { color: #1a1a2e; }
        .modal-btn { width: 100%; padding: 14px 24px; background: #005daa; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; margin-top: 16px; }
        .modal-btn:hover { background: #004080; }
        
        @media (max-width: 1024px) { .booking-layout { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .dashboard-header { padding: 12px 16px; }
            .user-details { display: none; }
            .dashboard-main { padding: 12px; padding-bottom: calc(88px + env(safe-area-inset-bottom, 0px)); }
            .page-title { font-size: 22px; }
            .tabs { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .tab-btn { flex-shrink: 0; padding: 10px 16px; }
            .time-slots { grid-template-columns: repeat(2, 1fr); }
            .calendar-box, .booking-box, .card { padding: 16px; }
            .appointment-card { flex-direction: column; align-items: stretch; text-align: left; }
            .apt-date { width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px; }
            .modal { width: calc(100% - 24px); max-width: 500px; padding: 20px; }
        }
        @media (max-width: 480px) {
            .calendar-day { min-height: 40px; font-size: 12px; }
            .btn-book { min-height: 48px; font-size: 15px; }
            .form-select, .form-textarea { font-size: 16px; }
        }
    </style>
    <link rel="stylesheet" href="../../assets/css/patient_portal.css?v=7">
</head>
<body>
    <aside class="sidebar" id="patientSidebar">
        <div class="sidebar-brand">
            <img src="../../assets/images/Logo.png" alt="RF Dental Clinic">
            <span>RF Dental Clinic</span>
        </div>
        <nav class="sidebar-menu">
            <a href="patient-dashboard.php" class="menu-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="inquiry_consultation.php" class="menu-item">
                <i class="fas fa-comments"></i>
                <span>Inquiry / Consultation</span>
            </a>
            <a href="appointments.php" class="menu-item active">
                <i class="fas fa-calendar-alt"></i>
                <span>My appointments</span>
            </a>
            <a href="medical_records.php" class="menu-item">
                <i class="fas fa-file-medical"></i>
                <span>Medical records</span>
            </a>
            <a href="billing.php" class="menu-item">
                <i class="fas fa-credit-card"></i>
                <span>Billing</span>
            </a>
            <a href="settings.php" class="menu-item">
                <i class="fas fa-cog"></i>
                <span>Account settings</span>
            </a>
        </nav>
    </aside>
    <?php include '../../includes/patient_sidebar_overlay.php'; ?>

    <div class="main-wrapper">
        <header class="dashboard-header">
            <div class="header-left">
                <?php include '../../includes/patient_menu_toggle.php'; ?>
                <div>
                <h1 style="font-size: 20px; font-weight: 700; color: #1a1a2e; margin: 0;">My Appointments</h1>
                <p style="font-size: 13px; color: #6b7280; margin: 4px 0 0 0;">Book new appointments or view your scheduled visits</p>
                </div>
            </div>
            <div class="header-right">
                <?php include '../../includes/patient_notification_widget.php'; ?>
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php 
                            $profPic = $patient['profile_picture'] ?? $_SESSION['profile_picture'] ?? '';
                            $first = ($patient['first_name'] ?? '') ?: ($_SESSION['user_name'] ?? $_SESSION['display_name'] ?? '');
                            $last = $patient['last_name'] ?? '';
                            if (!empty($profPic) && file_exists(dirname(__DIR__, 2) . '/' . $profPic)):
                        ?>
                            <img src="../../<?php echo htmlspecialchars($profPic); ?>" alt="Profile" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                        <?php 
                            else:
                            $initials = strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
                        ?>
                            <?php echo htmlspecialchars($initials ?: 'P'); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars(trim($first ?: 'Patient')); ?></div>
                        <div class="user-role">Patient</div>
                    </div>
                    <div class="dropdown-toggle"><i class="fas fa-chevron-down"></i></div>
                    <div class="user-dropdown">
                        <a href="profile.php" class="dropdown-item"><i class="fas fa-user"></i><span>Profile</span></a>
                        <a href="../../logout.php" class="dropdown-item logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                    </div>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <div class="tabs">
                <button class="tab-btn active" data-tab="book">Book Appointment</button>
                <button class="tab-btn" data-tab="upcoming">Upcoming (<?php echo count($upcoming_appointments); ?>)</button>
                <button class="tab-btn" data-tab="past">Past (<?php echo count($past_appointments); ?>)</button>
            </div>

            <div id="book" class="tab-content active">
                <div class="booking-layout">
                    <div class="calendar-box">
                        <div class="calendar-header">
                            <h3 class="calendar-title" id="currentMonthYear"><?php echo date('F Y'); ?></h3>
                            <div class="calendar-nav">
                                <button id="prevMonth"><i class="fas fa-chevron-left"></i></button>
                                <button id="nextMonth"><i class="fas fa-chevron-right"></i></button>
                            </div>
                        </div>
                        <div class="calendar-grid" id="calendarGrid"></div>
                        <div class="legend">
                            <div class="legend-item"><span class="legend-dot legend-today"></span> Today</div>
                            <div class="legend-item"><span class="legend-dot legend-selected"></span> Selected</div>
                            <div class="legend-item"><span class="legend-dot legend-appointment"></span> Has Appointment</div>
                            <div class="legend-item"><span class="legend-dot legend-available"></span> Available</div>
                            <div class="legend-item"><span class="legend-dot legend-closed"></span> Closed (Sunday)</div>
                        </div>
                    </div>
                    
                    <div class="booking-box">
                        <h3 class="booking-title"><i class="fas fa-calendar-plus"></i> Book Your Appointment</h3>
                        
                        <div id="datePrompt" style="text-align: center; padding: 40px 0; color: #6b7280;">
                            <i class="fas fa-calendar-day" style="font-size: 48px; margin-bottom: 16px; display: block; color: #d1d5db;"></i>
                            <p>Click on a calendar date to start booking</p>
                        </div>
                        
                        <div id="bookingForm" style="display: none;">
                            <div class="selected-date" id="selectedDateDisplay"></div>
                            
                            <!-- Step 1: Select Service First -->
                            <div class="form-group">
                                <label class="form-label">Select Service</label>
                                <select id="serviceSelect" class="form-select">
                                    <option value="">-- Choose a service --</option>
                                    <?php foreach ($services as $svc): ?>
                                        <option value="<?php echo $svc['id']; ?>" data-duration="<?php echo $svc['duration_minutes']; ?>">
                                            <?php echo htmlspecialchars($svc['name']); ?> (<?php echo $svc['duration_minutes']; ?> min)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Step 2: Time Slots (show after service selected) -->
                            <div class="form-group">
                                <label class="form-label">Select Time Slot</label>
                                <div class="time-slots" id="timeSlotsGrid">
                                    <div style="grid-column: 1/-1; text-align: center; padding: 20px; color: #6b7280; background: #f9fafb; border-radius: 8px;">
                                        <i class="fas fa-clock" style="margin-right: 8px;"></i>
                                        Select a service to see available times
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Step 3: Notes -->
                            <div class="form-group">
                                <label class="form-label">Notes (Optional)</label>
                                <textarea id="appointmentNotes" class="form-textarea" placeholder="Any additional notes..."></textarea>
                            </div>
                            
                            <button id="bookBtn" class="btn-book" disabled>
                                <i class="fas fa-calendar-check"></i> Book Appointment
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="upcoming" class="tab-content">
                <?php if (!empty($upcoming_appointments)): ?>
                    <?php foreach ($upcoming_appointments as $apt): 
                        $statusName = $apt['status_name'] ?? 'Scheduled';
                        $statusClass = 'status-scheduled';
                        if (stripos($statusName, 'pending approval') !== false) $statusClass = 'status-pending-approval';
                        elseif (stripos($statusName, 'queue') !== false || stripos($statusName, 'in queue') !== false) $statusClass = 'status-in-queue';
                        elseif (stripos($statusName, 'pending') !== false) $statusClass = 'status-pending-payment';
                        elseif (stripos($statusName, 'cancel') !== false) $statusClass = 'status-cancelled';
                        elseif (stripos($statusName, 'complete') !== false) $statusClass = 'status-completed';
                    ?>
                        <div class="appointment-card">
                            <div class="apt-date">
                                <div class="apt-month"><?php echo date('M', strtotime($apt['appointment_date'])); ?></div>
                                <div class="apt-day"><?php echo date('d', strtotime($apt['appointment_date'])); ?></div>
                                <div class="apt-year"><?php echo date('Y', strtotime($apt['appointment_date'])); ?></div>
                            </div>
                            <div class="apt-details">
                                <div class="apt-time"><i class="far fa-clock"></i> <?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></div>
                                <div class="apt-treatment"><?php echo htmlspecialchars($apt['service_name'] ?? $apt['treatment'] ?? 'General Checkup'); ?></div>
                                <?php if (!empty($apt['notes'])): ?>
                                <div class="apt-notes"><i class="fas fa-sticky-note"></i> <?php echo htmlspecialchars(substr($apt['notes'], 0, 60)); ?><?php echo strlen($apt['notes']) > 60 ? '...' : ''; ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="apt-status <?php echo $statusClass; ?>">
                                <?php echo htmlspecialchars($statusName); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="far fa-calendar-alt"></i>
                        <h3>No Upcoming Appointments</h3>
                        <p>Book an appointment using the calendar above!</p>
                    </div>
                <?php endif; ?>
            </div>

            <div id="past" class="tab-content">
                <?php if (!empty($past_appointments)): ?>
                    <?php foreach ($past_appointments as $apt): ?>
                        <?php 
                        $statusName = $apt['status_name'] ?? 'Completed';
                        $statusClass = 'status-completed';
                        if (stripos($statusName, 'pending approval') !== false) $statusClass = 'status-pending-approval';
                        elseif (stripos($statusName, 'queue') !== false || stripos($statusName, 'in queue') !== false) $statusClass = 'status-in-queue';
                        elseif (stripos($statusName, 'pending') !== false) $statusClass = 'status-pending-payment';
                        elseif (stripos($statusName, 'cancel') !== false) $statusClass = 'status-cancelled';
                        elseif (stripos($statusName, 'complete') !== false) $statusClass = 'status-completed';
                        ?>
                        <div class="appointment-card">
                            <div class="apt-date" style="background: #f3f4f6;">
                                <div class="apt-month"><?php echo date('M', strtotime($apt['appointment_date'])); ?></div>
                                <div class="apt-day"><?php echo date('d', strtotime($apt['appointment_date'])); ?></div>
                                <div class="apt-year"><?php echo date('Y', strtotime($apt['appointment_date'])); ?></div>
                            </div>
                            <div class="apt-details">
                                <div class="apt-time"><i class="far fa-clock"></i> <?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></div>
                                <div class="apt-treatment"><?php echo htmlspecialchars($apt['service_name'] ?? $apt['treatment'] ?? 'General Checkup'); ?></div>
                            </div>
                            <span class="apt-status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusName); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="far fa-calendar-check"></i>
                        <h3>No Past Appointments</h3>
                        <p>Your completed appointments will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div class="modal-overlay" id="successModal">
        <div class="modal">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <h3><i class="fas fa-check-circle modal-success"></i> Booking Confirmed!</h3>
            <div class="modal-content">
                <i class="fas fa-check-circle"></i>
                <h4>Appointment Booked Successfully!</h4>
                <p id="modalMessage"></p>
                <div class="modal-details" id="modalDetails"></div>
                <button class="modal-btn" onclick="closeModal()">Great, Thank You!</button>
            </div>
        </div>
    </div>

    <script>
        const bookedDatesSlots = <?php echo json_encode($bookedDateSlots); ?>;
        let currentDate = new Date();
        let selectedDate = null;
        let selectedTime = null;

        function renderCalendar() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            
            document.getElementById('currentMonthYear').textContent = new Date(year, month).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            
            const grid = document.getElementById('calendarGrid');
            grid.innerHTML = '';
            
            const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            days.forEach(d => {
                const h = document.createElement('div');
                h.className = 'calendar-day-header';
                h.textContent = d;
                grid.appendChild(h);
            });
            
            const firstDay = new Date(year, month, 1).getDay();
            const totalDays = new Date(year, month + 1, 0).getDate();
            const prevDays = new Date(year, month, 0).getDate();
            
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            for (let i = firstDay - 1; i >= 0; i--) {
                const d = document.createElement('div');
                d.className = 'calendar-day disabled';
                d.textContent = prevDays - i;
                grid.appendChild(d);
            }
            
            for (let i = 1; i <= totalDays; i++) {
                const d = document.createElement('div');
                d.className = 'calendar-day';
                
                const num = document.createElement('span');
                num.className = 'day-num';
                num.textContent = i;
                d.appendChild(num);
                
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
                const dateObj = new Date(year, month, i);
                
                if (dateObj < today) {
                    d.classList.add('disabled');
                } else if (dateObj.getDay() === 0) {
                    d.classList.add('sunday');
                    d.title = 'Closed on Sundays';
                } else {
                    if (dateObj.getTime() === today.getTime()) {
                        d.classList.add('today');
                    }
                    
                    const hasAppointment = bookedDatesSlots[dateStr] && bookedDatesSlots[dateStr].length > 0;
                    if (hasAppointment) {
                        d.classList.add('has-appointment');
                        const dot = document.createElement('span');
                        dot.className = 'day-dot';
                        d.appendChild(dot);
                    }
                    
                    d.addEventListener('click', () => selectDate(dateStr, d));
                }
                
                grid.appendChild(d);
            }
            
            const remaining = 42 - (firstDay + totalDays);
            for (let i = 1; i <= remaining; i++) {
                const d = document.createElement('div');
                d.className = 'calendar-day disabled';
                d.textContent = i;
                grid.appendChild(d);
            }
        }

function selectDate(selDate, el) {
            document.querySelectorAll('.calendar-day').forEach(d => d.classList.remove('selected'));
            el.classList.add('selected');
            
            selectedDate = selDate;
            selectedTime = null;
            
            const formatted = new Date(selDate + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            
            document.getElementById('datePrompt').style.display = 'none';
            document.getElementById('bookingForm').style.display = 'block';
            document.getElementById('selectedDateDisplay').textContent = formatted;
            
            // Reset selections
            selectedTime = null;
            document.getElementById('serviceSelect').value = '';
            document.getElementById('timeSlotsGrid').innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 20px; color: #6b7280; background: #f9fafb; border-radius: 8px;"><i class="fas fa-clock"></i> Select a service to see available times</div>';
        }
        
        function loadSlots(serviceId) {
            const grid = document.getElementById('timeSlotsGrid');
            if (!selectedDate || !serviceId) return;
            
            grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
            
            fetch('./get_slots.php?date=' + selectedDate + '&service_id=' + serviceId)
                .then(function(resp) { return resp.json(); })
                .then(function(data) { 
                    displaySlots(data, grid); 
                })
                .catch(function(err) { 
                    grid.innerHTML = 'Error: ' + err.message; 
                });
        }
        
        function displaySlots(data, grid) {
            grid.innerHTML = '';
            
            if (!data.is_open) {
                grid.innerHTML = '<div style="color:orange;">' + (data.message || 'Clinic closed') + '</div>';
                return;
            }
            
            // Show legend
            const legend = document.createElement('div');
            legend.style.cssText = 'grid-column: 1/-1; display: flex; gap: 16px; margin-bottom: 12px; font-size: 12px;';
            legend.innerHTML = '<span style="color:#065f46;"><i class="fas fa-check-circle"></i> Available</span><span style="color:#991b1b;"><i class="fas fa-times-circle"></i> Booked</span>';
            grid.appendChild(legend);
            
            const allSlots = [...data.available_slots, ...data.booked_slots].sort();
            if (allSlots.length === 0) {
                grid.innerHTML += '<div style="color:orange;">No slots available</div>';
                return;
            }
            
            allSlots.forEach(function(slot) {
                const s = document.createElement('div');
                const isBooked = data.booked_slots.includes(slot);
                s.className = isBooked ? 'time-slot unavailable' : 'time-slot available';
                s.textContent = formatTime(slot);
                if (!isBooked) {
                    s.dataset.time = slot;
                    s.onclick = function() { selectTime(slot, s); };
                } else {
                    s.title = 'Already booked';
                }
                grid.appendChild(s);
            });
        }
        
        function formatTime(t) {
            const [h, m] = t.split(':');
            return `${parseInt(h) % 12 || 12}:${m} ${h >= 12 ? 'PM' : 'AM'}`;
        }

        function escapeHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function addUpcomingAppointment(dateStr, timeStr, serviceLabel, notes) {
            const upcoming = document.getElementById('upcoming');
            if (!upcoming) return;

            const emptyState = upcoming.querySelector('.empty-state');
            if (emptyState) emptyState.remove();

            const date = new Date(dateStr + 'T00:00:00');
            const month = date.toLocaleDateString('en-US', { month: 'short' });
            const day = String(date.getDate()).padStart(2, '0');
            const year = String(date.getFullYear());
            const serviceName = (serviceLabel || 'General Checkup').replace(/\s*\(\d+\s*min\)\s*$/i, '').trim();
            const notesText = (notes || '').trim();
            const notesHtml = notesText
                ? `<div class="apt-notes"><i class="fas fa-sticky-note"></i> ${escapeHtml(notesText.substring(0, 60))}${notesText.length > 60 ? '...' : ''}</div>`
                : '';

            const card = document.createElement('div');
            card.className = 'appointment-card';
            card.innerHTML = `
                <div class="apt-date">
                    <div class="apt-month">${escapeHtml(month)}</div>
                    <div class="apt-day">${escapeHtml(day)}</div>
                    <div class="apt-year">${escapeHtml(year)}</div>
                </div>
                <div class="apt-details">
                    <div class="apt-time"><i class="far fa-clock"></i> ${escapeHtml(formatTime(timeStr))}</div>
                    <div class="apt-treatment">${escapeHtml(serviceName)}</div>
                    ${notesHtml}
                </div>
                <span class="apt-status status-pending-approval">Pending Approval</span>
            `;
            upcoming.prepend(card);

            const count = upcoming.querySelectorAll('.appointment-card').length;
            const upcomingBtn = document.querySelector('.tab-btn[data-tab="upcoming"]');
            if (upcomingBtn) upcomingBtn.textContent = `Upcoming (${count})`;
        }

        function switchToUpcomingTab() {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            const upcomingBtn = document.querySelector('.tab-btn[data-tab="upcoming"]');
            if (upcomingBtn) upcomingBtn.classList.add('active');
            const upcoming = document.getElementById('upcoming');
            if (upcoming) upcoming.classList.add('active');
        }
        
        function selectTime(time, el) {
    // 1. Clear previous selections
    document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
    
    // 2. Get the duration of the selected service from the dropdown
    const select = document.getElementById('serviceSelect');
    const option = select.options[select.selectedIndex];
    
    // Fallback to 15 mins if it can't find the duration
    const duration = parseInt(option.getAttribute('data-duration')) || 15; 
    
    // 3. Calculate how many 15-minute blocks this service takes
    const blocksNeeded = Math.ceil(duration / 15);
    
    // 4. Get all slot elements as an array to find where we clicked
    const allSlots = Array.from(document.querySelectorAll('.time-slot'));
    const startIndex = allSlots.indexOf(el);
    
    // 5. Visually highlight the consecutive blocks
    if (startIndex !== -1) {
        for (let i = 0; i < blocksNeeded; i++) {
            const currentSlot = allSlots[startIndex + i];
            
            // Only add the highlight if the slot exists in the grid
            if (currentSlot) {
                currentSlot.classList.add('selected');
            }
        }
    }
    
    // 6. Set the selected time for the form submission
    selectedTime = time;
    updateBookBtn();
}
        
        function updateBookBtn() {
            document.getElementById('bookBtn').disabled = !(selectedDate && selectedTime && document.getElementById('serviceSelect').value);
        }
        
        // Service selection loads time slots for the selected date
        document.getElementById('serviceSelect').addEventListener('change', function() {
            const serviceId = this.value;
            if (selectedDate && serviceId) {
                loadSlots(serviceId);
            }
            updateBookBtn();
        });

        document.getElementById('bookBtn').addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Booking...';
            
            const formData = new FormData();
            formData.append('appointment_date', selectedDate);
            formData.append('appointment_time', selectedTime);
            formData.append('service_id', document.getElementById('serviceSelect').value);
            formData.append('notes', document.getElementById('appointmentNotes').value);
            
            fetch('book_appointment.php', { method: 'POST', body: formData })
                .then(r => r.text())
                .then(text => {
                    console.log('Response:', text);
                    const data = JSON.parse(text);
                    if (data.success) {
                        const bookedDate = selectedDate;
                        const bookedTime = selectedTime;
                        const serviceName = document.querySelector('#serviceSelect option:checked').text;
                        const notes = document.getElementById('appointmentNotes').value;
                        addUpcomingAppointment(bookedDate, bookedTime, serviceName, notes);
                        switchToUpcomingTab();
                        document.getElementById('modalMessage').textContent = data.message;
                        document.getElementById('modalDetails').innerHTML = `
                            <p><strong>Service:</strong> ${serviceName}</p>
                            <p><strong>Date:</strong> ${new Date(bookedDate + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
                            <p><strong>Time:</strong> ${formatTime(bookedTime)}</p>
                        `;
                        document.getElementById('successModal').classList.add('active');
                        
                        // Refresh the time slots to show the newly booked slot as unavailable
                        const serviceId = document.getElementById('serviceSelect').value;
                        if (serviceId) {
                            loadSlots(serviceId);
                        }
                        
                        // Reset time selection
                        selectedTime = null;
                        updateBookBtn();
                    } else {
                        alert(data.message || 'Booking failed');
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('An error occurred: ' + err.message)
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-calendar-check"></i> Book Appointment';
                });
        });

        function resetForm() {
            selectedDate = null;
            selectedTime = null;
            document.getElementById('serviceSelect').value = '';
            document.getElementById('appointmentNotes').value = '';
            document.getElementById('timeSlotsGrid').innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 20px; color: #6b7280; background: #f9fafb; border-radius: 8px;">Select a service to see available times</div>';
            document.getElementById('datePrompt').style.display = 'block';
            document.getElementById('bookingForm').style.display = 'none';
            document.querySelectorAll('.calendar-day').forEach(d => d.classList.remove('selected'));
            updateBookBtn();
        }

        function closeModal() {
            document.getElementById('successModal').classList.remove('active');
        }

        document.getElementById('prevMonth').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
        });

        document.getElementById('nextMonth').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
        });

        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                document.getElementById(this.dataset.tab).classList.add('active');
            });
        });

        renderCalendar();
    </script>
    <script>
        (function () {
            const launcher = document.querySelector('[data-chat-launcher]');
            if (!launcher) return;
            const unreadBadge = document.getElementById('unreadBadge');
            let overlay = document.getElementById('floatingChatOverlay');
            let panel = document.getElementById('floatingChatPanel');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'floatingChatOverlay';
                overlay.className = 'floating-chat-overlay';
                document.body.appendChild(overlay);
            }
            if (!panel) {
                panel = document.createElement('div');
                panel.id = 'floatingChatPanel';
                panel.className = 'floating-chat-panel';
                panel.innerHTML = `<div class="floating-chat-header"><div class="floating-chat-title"><i class="fas fa-comments"></i><span>Messages</span></div><div class="floating-chat-actions"><button type="button" class="floating-chat-control" data-chat-minimize aria-label="Minimize"><i class="fas fa-minus"></i></button><button type="button" class="floating-chat-control" data-chat-close aria-label="Close"><i class="fas fa-xmark"></i></button></div></div><iframe class="floating-chat-frame" src="messages.php?embed=1" title="Messages"></iframe>`;
                document.body.appendChild(panel);
            }
            function closeChat() { overlay.classList.remove('is-open'); panel.classList.remove('is-open'); }
            function openChat(event) {
                event.preventDefault();
                overlay.classList.add('is-open');
                panel.classList.add('is-open');
                const frame = panel.querySelector('.floating-chat-frame');
                if (frame && frame.contentWindow) {
                    window.setTimeout(() => {
                        frame.contentWindow.postMessage({ type: 'patient-chat-opened' }, window.location.origin);
                    }, 120);
                }
            }
            function updateUnreadBadge(count) {
                if (!unreadBadge) return;
                if (count > 0) {
                    unreadBadge.textContent = count > 99 ? '99+' : String(count);
                    unreadBadge.style.display = 'inline-flex';
                } else {
                    unreadBadge.style.display = 'none';
                }
            }
            async function pollUnreadCount() {
                try {
                    const payload = new FormData();
                    payload.append('ajax', '1');
                    payload.append('action', 'get_unread_count');
                    const response = await fetch('messages.php', {
                        method: 'POST',
                        body: payload,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) return;
                    updateUnreadBadge(Number(data.unread_count || 0));
                } catch (_err) {}
            }
            launcher.addEventListener('click', openChat);
            overlay.addEventListener('click', closeChat);
            panel.querySelector('[data-chat-close]').addEventListener('click', closeChat);
            panel.querySelector('[data-chat-minimize]').addEventListener('click', closeChat);
            window.addEventListener('message', function (event) {
                if (event.origin !== window.location.origin) return;
                if (event.data && event.data.type === 'patient-chat-unread') {
                    updateUnreadBadge(Number(event.data.count || 0));
                }
            });
            pollUnreadCount();
            setInterval(pollUnreadCount, 3000);
        })();
    </script>
    <?php
        $__stoEnabled = !empty($patientSessionSettings['enabled']);
        $__stoMs = (int)($patientSessionSettings['timeout_minutes'] ?? 60) * 60 * 1000;
        require __DIR__ . '/../../includes/session_timeout_script.php';
    ?>
</body>
</html>
