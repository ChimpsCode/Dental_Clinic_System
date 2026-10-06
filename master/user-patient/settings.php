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
$message = '';
$messageType = '';
$passwordEditing = false;
$_SESSION['patient_password_csrf'] = $_SESSION['patient_password_csrf'] ?? bin2hex(random_bytes(32));
unset($_SESSION['patient_password_otp'], $_SESSION['patient_password_pending'], $_SESSION['patient_password_otp_expires']);

require_once '../../config/database.php';
require_once '../../includes/patient_session_helper.php';
require_once '../../includes/account_contact.php';
require_once '../../includes/patient_password_change.php';
require_once '../../includes/patient_user_sync_helper.php';
require_once '../../includes/sms_functions.php';
require_once '../../includes/patient_sms_test_limit.php';

$patientSessionSettings = getPatientSessionSettings($pdo, (int)$user_id);
$settingsPatientUserJoin = 'LEFT JOIN users u ON u.id = p.user_id';
$smsPreferencesAvailable = false;
$smsGatewayConfigured = smsIsConfigured();
$smsTestQuota = getPatientSmsTestQuota((int)$user_id);
$smsGatewayOnline = $smsGatewayConfigured;
if ($smsGatewayConfigured) {
    $smsGatewayOnline = true;
}
try {
    $usersColumns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
    $patientsColumns = $pdo->query('SHOW COLUMNS FROM patients')->fetchAll(PDO::FETCH_COLUMN);
    $smsPreferencesAvailable = in_array('sms_notifications', $usersColumns, true);
    if (in_array('patient_id', $usersColumns, true)) {
        $settingsPatientUserJoin = 'LEFT JOIN users u ON u.patient_id = p.id';
    } elseif (!in_array('user_id', $patientsColumns, true)) {
        $settingsPatientUserJoin = 'LEFT JOIN users u ON 1 = 0';
    }
} catch (Exception $e) {
    error_log('Patient settings schema detection error: ' . $e->getMessage());
}

try {
    $stmt = $pdo->prepare("
        SELECT p.*, COALESCE(u.first_name, p.first_name) AS first_name, COALESCE(u.middle_name, p.middle_name) AS middle_name, COALESCE(u.last_name, p.last_name) AS last_name, u.email, u.phone, u.created_at
        FROM patients p 
        {$settingsPatientUserJoin}
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient && $patient_id) {
        $stmt = $pdo->prepare("
            SELECT p.*, COALESCE(u.first_name, p.first_name) AS first_name, COALESCE(u.middle_name, p.middle_name) AS middle_name, COALESCE(u.last_name, p.last_name) AS last_name, u.email, u.phone, u.created_at
            FROM patients p 
            {$settingsPatientUserJoin}
            WHERE p.id = ?
        ");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$patient) {
        throw new Exception('Patient not found');
    }
    
    if (!$patient_id && $patient) {
        $patient_id = $patient['id'];
        $_SESSION['patient_id'] = $patient_id;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_email') {
            $new_email = trim($_POST['email'] ?? '');
            
            if (!empty($new_email) && filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->execute([$new_email, $user_id]);
                // Keep the clinical record (Patient Records) identical.
                syncUserToPatient($pdo, (int)$user_id);
                $user['email'] = $new_email;
                $message = 'Email updated successfully!';
                $messageType = 'success';
            } else {
                $message = 'Please enter a valid email address.';
                $messageType = 'error';
            }
        }
        
        if (in_array($action, ['change_password', 'send_password_otp', 'verify_password_otp', 'cancel_password_otp'], true)) {
            $result = is_string($_POST['password_csrf'] ?? null) && hash_equals($_SESSION['patient_password_csrf'], $_POST['password_csrf'])
                ? patientPasswordChange($pdo, $user, $_SESSION, $_POST)
                : ['message' => 'Please refresh the page and try again.', 'type' => 'error'];
            $message = $result['message'];
            $messageType = $result['type'];
            $passwordEditing = $action === 'change_password' && $messageType === 'error';
        }

        if ($action === 'update_notifications') {
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;

            $sets = [];
            $params = [];
            if ($smsPreferencesAvailable) {
                $sets[] = 'email_notifications = ?';
                $params[] = $email_notifications;
                $sets[] = 'sms_notifications = ?';
                $params[] = $sms_notifications;
            }
            if (!$sets) {
                $message = 'Notification preferences are unavailable on this installation. Please contact the clinic.';
                $messageType = 'error';
            } else {
                $params[] = $user_id;
                $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
                $user['email_notifications'] = $email_notifications;
                $user['sms_notifications'] = $sms_notifications;
                $message = 'Notification preferences updated!';
                $messageType = 'success';
            }
        }

        if ($action === 'update_session_timeout') {
            $enabled = isset($_POST['session_timeout_enabled']);
            $timeoutMinutes = (int)($_POST['session_timeout_minutes'] ?? 60);
            if (!in_array($timeoutMinutes, [15, 30, 60, 120], true)) {
                $message = 'Please select a valid session timeout.';
                $messageType = 'error';
            } elseif (savePatientSessionSettings($pdo, (int)$user_id, $enabled, $timeoutMinutes)) {
                $patientSessionSettings = ['enabled' => $enabled, 'timeout_minutes' => $timeoutMinutes];
                $message = 'Session timeout preferences updated!';
                $messageType = 'success';
            } else {
                $message = 'Unable to update session timeout preferences.';
                $messageType = 'error';
            }
        }
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $user = [];
    if ($message === '') {
        $message = 'Unable to save this setting. Please try again.';
        $messageType = 'error';
    }
    error_log('Patient settings error: ' . $e->getMessage());
}

$pageTitle = 'Account Settings';
$passwordContacts = patientPasswordContacts($user);
$passwordChange = $_SESSION['patient_password_change'] ?? [];
if ($passwordChange && (($passwordChange['expires'] ?? 0) <= time() || ($passwordChange['user_id'] ?? 0) !== (int)$user_id || !hash_equals($passwordChange['original'] ?? '', $user['password'] ?? ''))) {
    unset($_SESSION['patient_password_change']);
    $passwordChange = [];
}
$passwordOtpPending = !empty($passwordChange['otp']) && ($passwordChange['otp_expires'] ?? 0) > time();
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
        .dashboard-main { flex: 1; padding: 24px 32px; max-width: 800px; width: 100%; margin: 0 auto; }
        .page-header { margin-bottom: 24px; }
        .page-title { font-size: 28px; font-weight: 700; color: #1a1a2e; margin-bottom: 8px; }
        .page-subtitle { color: #6b7280; font-size: 14px; }
        .section { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 24px; }
        .section-title { font-size: 18px; font-weight: 700; color: #1a1a2e; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .section-title i { color: #005daa; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px; }
        .form-input { width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; transition: border-color 0.2s; }
        .form-input:focus { outline: none; border-color: #005daa; box-shadow: 0 0 0 3px rgba(0,93,170,0.1); }
        .password-field-wrapper { position: relative; }
        .password-field-wrapper .form-input { padding-right: 44px; }
        .password-toggle { position: absolute; top: 50%; right: 12px; transform: translateY(-50%); border: 0; background: none; color: #6b7280; cursor: pointer; padding: 4px; }
        .password-toggle:hover { color: #005daa; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: #005daa; color: white; }
        .btn-primary:hover { background: #004080; }
        .btn-secondary { background: #e5e7eb; color: #374151; }
        .btn-secondary:hover { background: #d1d5db; }
        .preference-save-btn:disabled { cursor: not-allowed; }
        .preference-save-btn.btn-secondary:disabled:hover { background: #e5e7eb; }
        #passwordChangeForm input:disabled { background: #f3f4f6; color: #9ca3af; cursor: not-allowed; }
        #passwordChangeForm .password-toggle:disabled { opacity: .4; cursor: default; }
        .password-contact-options { border: 0; padding: 0; margin: 0 0 24px; text-align: left; min-width: 0; }
        .password-contact-option { display: flex; align-items: center; gap: 12px; border: 1px solid #d1d5db; border-radius: 8px; padding: 14px; margin-top: 10px; cursor: pointer; }
        .password-contact-option:has(input:checked) { border-color: #005daa; background: #eff6ff; }
        .password-contact-option input { accent-color: #005daa; width: 18px; height: 18px; flex-shrink: 0; }
        .password-contact-option span { min-width: 0; }
        .password-contact-option small { display: block; color: #6b7280; margin-top: 4px; overflow-wrap: anywhere; }
        #passwordOtpModal .modal-box { max-height: 90vh; overflow-y: auto; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .checkbox-group { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
        .checkbox-input { width: 18px; height: 18px; cursor: pointer; }
        .checkbox-label { font-size: 14px; color: #374151; cursor: pointer; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .danger-zone { border: 1px solid #fecaca; background: #fef2f2; }
        .danger-zone .section-title { color: #991b1b; }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.is-open { display: flex; }
        .modal-box { background: white; border-radius: 12px; padding: 32px; max-width: 440px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); text-align: center; }
        .modal-icon { font-size: 48px; color: #dc3545; margin-bottom: 16px; }
        .modal-title { font-size: 20px; font-weight: 700; color: #1a1a2e; margin-bottom: 8px; }
        .modal-desc { font-size: 14px; color: #6b7280; margin-bottom: 24px; line-height: 1.5; }
        .modal-input { width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; margin-bottom: 16px; }
        .modal-input:focus { outline: none; border-color: #dc3545; box-shadow: 0 0 0 3px rgba(220,53,69,0.1); }
        .modal-actions { display: flex; gap: 12px; justify-content: center; }
        .modal-actions .btn { min-width: 120px; }
        .modal-error { color: #dc3545; font-size: 13px; margin-bottom: 12px; display: none; }
        .account-info { background: #f9fafb; padding: 16px; border-radius: 8px; margin-bottom: 20px; }
        .account-info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
        .account-info-row:last-child { border-bottom: none; }
        .account-info-label { color: #6b7280; font-size: 14px; }
        .account-info-value { color: #1a1a2e; font-size: 14px; font-weight: 500; }
        @media (max-width: 768px) {
            .sidebar { width: 60px; padding: 24px 0; }
            .sidebar-brand span, .menu-item span { display: none; }
            .sidebar-brand { justify-content: center; padding: 0; }
            .menu-item { justify-content: center; padding: 14px; }
            .main-wrapper { margin-left: 60px; }
            .dashboard-header { padding: 12px 16px; }
            .user-details { display: none; }
            .dashboard-main { padding: 16px; }
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
            <a href="appointments.php" class="menu-item">
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
            <a href="settings.php" class="menu-item active">
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
                <h1 style="font-size: 20px; font-weight: 700; color: #1a1a2e; margin: 0;">Account Settings</h1>
                <p style="font-size: 13px; color: #6b7280; margin: 4px 0 0 0;">Manage your account preferences and security</p>
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
                        <div class="user-name">
                            <?php 
                                $displayName = trim($first ?: 'Patient');
                                echo htmlspecialchars($displayName);
                            ?>
                        </div>
                        <div class="user-role">Patient</div>
                    </div>
                    <div class="dropdown-toggle">
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="user-dropdown">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i>
                            <span>Profile</span>
                        </a>
                        <a href="../../logout.php" class="dropdown-item logout">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="section">
                <h2 class="section-title"><i class="fas fa-user-circle"></i> Account Information</h2>
                <div class="account-info">
                    <div class="account-info-row">
                        <span class="account-info-label">Full Name</span>
                        <span class="account-info-value"><?php echo htmlspecialchars(trim(implode(' ', array_filter([
                            trim(($user['first_name'] ?? '') ?: ($patient['first_name'] ?? '')),
                            trim(($user['middle_name'] ?? '') ?: ($patient['middle_name'] ?? '')),
                            trim(($user['last_name'] ?? '') ?: ($patient['last_name'] ?? '')),
                        ], static function ($part) { return $part !== ''; }))) ?: 'N/A'); ?></span>
                    </div>
                    <div class="account-info-row">
                        <span class="account-info-label">Email</span>
                        <span class="account-info-value"><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="account-info-row">
                        <span class="account-info-label">Phone Number</span>
                        <span class="account-info-value"><?php echo htmlspecialchars(($user['phone'] ?? '') ?: 'N/A'); ?></span>
                    </div>
                    <div class="account-info-row">
                        <span class="account-info-label">Role</span>
                        <span class="account-info-value"><?php echo htmlspecialchars(ucfirst($user['role'] ?? 'Patient')); ?></span>
                    </div>
                    <div class="account-info-row">
                        <span class="account-info-label">Member Since</span>
                        <span class="account-info-value"><?php echo date('F j, Y', strtotime($user['created_at'] ?? 'now')); ?></span>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2 class="section-title"><i class="fas fa-lock"></i> Change Password</h2>
                <form method="POST" action="" id="passwordChangeForm">
                    <input type="hidden" name="action" value="change_password">
                                <input type="hidden" name="password_csrf" value="<?php echo htmlspecialchars($_SESSION['patient_password_csrf']); ?>">
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <div class="password-field-wrapper">
                            <input type="password" name="current_password" autocomplete="current-password" class="form-input password-input" <?php echo $passwordEditing ? '' : 'disabled'; ?> required>
                            <button type="button" class="password-toggle" <?php echo $passwordEditing ? '' : 'disabled'; ?> aria-label="Show current password"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <div class="password-field-wrapper">
                            <input type="password" name="new_password" autocomplete="new-password" class="form-input password-input" <?php echo $passwordEditing ? '' : 'disabled'; ?> minlength="8" required>
                            <button type="button" class="password-toggle" <?php echo $passwordEditing ? '' : 'disabled'; ?> aria-label="Show new password"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <div class="password-field-wrapper">
                            <input type="password" name="confirm_password" autocomplete="new-password" class="form-input password-input" <?php echo $passwordEditing ? '' : 'disabled'; ?> minlength="8" required>
                            <button type="button" class="password-toggle" <?php echo $passwordEditing ? '' : 'disabled'; ?> aria-label="Show confirmed password"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <p style="font-size:13px;color:#6b7280;margin-bottom:16px;">Use at least 8 characters for your new password.</p>
                    <button type="<?php echo $passwordEditing ? 'submit' : 'button'; ?>" class="btn <?php echo $passwordEditing ? 'btn-primary' : 'btn-secondary'; ?>" id="passwordEditBtn">
                        <i class="fas fa-key"></i> <span><?php echo $passwordEditing ? 'Save' : 'Change Password'; ?></span>
                    </button>
                </form>
            </div>

            <div class="section">
                <h2 class="section-title"><i class="fas fa-bell"></i> Notification Preferences</h2>
                <?php if (!$smsGatewayConfigured): ?>
                    <div class="alert alert-error" style="margin-bottom:16px;">
                        SMS is not available yet. Ask the clinic to finish setting up the SMS gateway.
                    </div>
                <?php endif; ?>
                <p style="color:#6b7280;font-size:14px;margin-bottom:16px;">
                    Choose how the clinic reaches you. SMS messages cover appointment approvals, cancellations,
                    and reminders. Turning SMS off only stops these texts &mdash; you will still see notifications
                    here in the portal, and security codes for changing your phone number are always sent.
                </p>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_notifications">
                    <div class="checkbox-group">
                        <input type="checkbox" id="email_notifications" name="email_notifications" class="checkbox-input" <?php echo ((int)($user['email_notifications'] ?? 1) === 1) ? 'checked' : ''; ?>>
                        <label for="email_notifications" class="checkbox-label">Email Notifications</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" id="sms_notifications" name="sms_notifications" class="checkbox-input" <?php echo ((int)($user['sms_notifications'] ?? 1) === 1) ? 'checked' : ''; ?> <?php echo $smsPreferencesAvailable ? '' : 'disabled'; ?>>
                        <label for="sms_notifications" class="checkbox-label">
                            SMS Notifications
                            <span style="color:#6b7280;font-weight:400;">
                                (<?php echo $smsPreferencesAvailable ? 'texts to ' . htmlspecialchars($user['phone'] ?? 'your phone on file') : 'unavailable'; ?>)
                            </span>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-secondary preference-save-btn" data-save-available="<?php echo $smsPreferencesAvailable ? 'true' : 'false'; ?>" disabled>
                        <i class="fas fa-save"></i> Save Preferences
                    </button>
                </form>

                <div style="margin-top:20px;padding-top:16px;border-top:1px solid #e5e7eb;">
                    <div class="section-title" style="font-size:15px;margin-bottom:12px;"><i class="fas fa-comment-sms"></i> Test your SMS</div>
                    <p style="color:#6b7280;font-size:14px;margin-bottom:12px;">Send yourself a test text to confirm texts are reaching this number. Maximum 2 test SMS per day, with a 5-minute wait between tests. Resets at midnight Philippine time.</p>
                    <button type="button" class="btn btn-secondary" id="smsTestBtn" <?php echo $smsGatewayConfigured && $smsTestQuota['can_send'] ? '' : 'disabled'; ?>>
                        <i class="fas fa-paper-plane"></i> Send me a test SMS
                    </button>
                    <div id="smsTestLimitStatus" role="status" aria-live="polite" style="margin-top:10px;font-size:13px;color:#6b7280;"><?php echo htmlspecialchars($smsTestQuota['message'] ?: $smsTestQuota['remaining'] . ' of 2 test SMS remaining today.'); ?></div>
                    <div id="smsTestResult" style="margin-top:12px;font-size:14px;display:none;"></div>
                </div>
            </div>

            <div class="section">
                <h2 class="section-title"><i class="fas fa-clock"></i> Security Session Timeout</h2>
                <p style="color:#6b7280;font-size:14px;margin-bottom:16px;">Choose whether your account should log out automatically after a period of inactivity.</p>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_session_timeout">
                    <div class="checkbox-group" style="margin-bottom:16px;">
                        <input type="checkbox" id="session_timeout_enabled" name="session_timeout_enabled" class="checkbox-input" <?php echo $patientSessionSettings['enabled'] ? 'checked' : ''; ?>>
                        <label for="session_timeout_enabled" class="checkbox-label">Enable automatic session timeout</label>
                    </div>
                    <div class="form-group" style="max-width:280px;">
                        <label class="form-label" for="session_timeout_minutes">Log out after inactivity</label>
                        <select id="session_timeout_minutes" name="session_timeout_minutes" class="form-select">
                            <?php foreach ([15, 30, 60, 120] as $timeoutOption): ?>
                                <option value="<?php echo $timeoutOption; ?>" <?php echo (int)$patientSessionSettings['timeout_minutes'] === $timeoutOption ? 'selected' : ''; ?>><?php echo $timeoutOption < 60 ? $timeoutOption . ' minutes' : ($timeoutOption / 60) . ' hour' . ($timeoutOption === 60 ? '' : 's'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary preference-save-btn" data-save-available="true" disabled><i class="fas fa-save"></i> Save Session Preference</button>
                </form>
            </div>

            <div class="section danger-zone">
                <h2 class="section-title"><i class="fas fa-exclamation-triangle"></i> Danger Zone</h2>
                <p style="color: #6b7280; font-size: 14px; margin-bottom: 16px;">
                    Once you delete your account, there is no going back. Please be certain.
                </p>
                <button type="button" class="btn btn-danger" id="deleteAccountBtn">
                    <i class="fas fa-trash"></i> Delete Account
                </button>
            </div>

            <?php if ($passwordChange): ?>
                <div class="modal-overlay is-open" id="passwordOtpModal" role="dialog" aria-modal="true" aria-labelledby="passwordOtpTitle">
                    <div class="modal-box">
                        <div class="modal-icon" style="color:#005daa;"><i class="fas fa-shield-alt"></i></div>
                        <h2 class="modal-title" id="passwordOtpTitle"><?php echo $passwordOtpPending ? 'Enter verification code' : 'Send verification code'; ?></h2>
                        <?php if ($messageType === 'error'): ?>
                            <p role="alert" style="color:#b91c1c;margin-bottom:16px;"><?php echo htmlspecialchars($message); ?></p>
                        <?php endif; ?>
                        <?php if ($passwordOtpPending): ?>
                            <p class="modal-desc">Enter the 6-digit code sent to <strong><?php echo htmlspecialchars($passwordChange['destination']); ?></strong>. The code expires in 3 minutes.</p>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="verify_password_otp">
                    <input type="hidden" name="password_csrf" value="<?php echo htmlspecialchars($_SESSION['patient_password_csrf']); ?>">
                                <label class="form-label" for="passwordOtpInput">Verification code</label>
                                <input id="passwordOtpInput" type="text" name="password_otp" class="modal-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Enter verification code" autocomplete="one-time-code" required autofocus>
                                <div class="modal-actions"><button type="submit" class="btn btn-primary">Verify &amp; Save</button></div>
                            </form>
                            <form method="POST" action="" style="margin-top:12px;">
                                <input type="hidden" name="action" value="send_password_otp">
                                <input type="hidden" name="password_csrf" value="<?php echo htmlspecialchars($_SESSION['patient_password_csrf']); ?>">
                                <input type="hidden" name="password_channel" value="<?php echo htmlspecialchars($passwordChange['channel']); ?>">
                                <button type="submit" class="btn btn-secondary">Resend OTP</button>
                            </form>
                        <?php else: ?>
                            <p class="modal-desc">Choose where to receive the OTP to confirm your password change.</p>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="send_password_otp">
                                <input type="hidden" name="password_csrf" value="<?php echo htmlspecialchars($_SESSION['patient_password_csrf']); ?>">
                                <fieldset class="password-contact-options">
                                    <legend class="form-label">Send OTP using</legend>
                                    <?php foreach ($passwordContacts as $channel => $destination): ?>
                                        <label class="password-contact-option">
                                            <input type="radio" name="password_channel" value="<?php echo $channel; ?>" required <?php echo $channel === array_key_first($passwordContacts) ? 'checked' : ''; ?>>
                                            <span><strong><?php echo $channel === 'email' ? 'Email' : 'SMS'; ?></strong><small><?php echo htmlspecialchars($destination); ?></small></span>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                                <div class="modal-actions"><button type="submit" class="btn btn-primary">Send OTP</button></div>
                            </form>
                        <?php endif; ?>
                        <form method="POST" action="" style="margin-top:10px;">
                            <input type="hidden" name="action" value="cancel_password_otp">
                                <input type="hidden" name="password_csrf" value="<?php echo htmlspecialchars($_SESSION['patient_password_csrf']); ?>">
                            <button type="submit" class="btn btn-secondary">Cancel</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Delete Account Confirmation Modal -->
            <div class="modal-overlay" id="deleteModal">
                <div class="modal-box">
                    <div class="modal-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <h2 class="modal-title">Delete Account</h2>
                    <p class="modal-desc">
                        This will permanently deactivate your account. You will lose access to all your data.
                        If you change your mind later, please contact the clinic to restore your account.
                    </p>
                    <div class="modal-error" id="deleteModalError"></div>
                    <input type="password" class="modal-input" id="deletePasswordInput" placeholder="Enter your password to confirm" autocomplete="off">
                    <div class="modal-actions">
                        <button type="button" class="btn btn-primary" id="deleteCancelBtn">Cancel</button>
                        <button type="button" class="btn btn-danger" id="deleteConfirmBtn"><i class="fas fa-trash"></i> Delete</button>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
        document.querySelectorAll('.preference-save-btn').forEach(function (button) {
            const form = button.form;
            const fields = Array.from(form.querySelectorAll('input:not([type="hidden"]):not(:disabled), select:not(:disabled)'));
            const readValues = function () {
                return JSON.stringify(fields.map(function (field) {
                    return field.type === 'checkbox' ? field.checked : field.value;
                }));
            };
            const initialValues = readValues();
            let saving = false;
            const updateButton = function () {
                const changed = readValues() !== initialValues && button.dataset.saveAvailable === 'true';
                button.disabled = !changed || saving;
                button.classList.toggle('btn-primary', changed);
                button.classList.toggle('btn-secondary', !changed);
            };
            form.addEventListener('input', updateButton);
            form.addEventListener('change', updateButton);
            form.addEventListener('submit', function (event) {
                if (button.disabled || saving) {
                    event.preventDefault();
                    return;
                }
                saving = true;
                button.disabled = true;
            });
            updateButton();
        });
        document.querySelectorAll('input[name="action"][value="send_password_otp"]').forEach(function (actionInput) {
            const form = actionInput.form;
            let sending = false;
            form.addEventListener('submit', function (event) {
                if (sending) {
                    event.preventDefault();
                    return;
                }
                sending = true;
                const button = form.querySelector('button[type="submit"]');
                button.disabled = true;
                button.textContent = 'Sending OTP…';
                button.setAttribute('aria-busy', 'true');
            });
        });
        const passwordEditBtn = document.getElementById('passwordEditBtn');
        passwordEditBtn.addEventListener('click', function (event) {
            if (this.type === 'submit') return;
            event.preventDefault();
            document.querySelectorAll('#passwordChangeForm input, #passwordChangeForm .password-toggle').forEach(function (field) { field.disabled = false; });
            this.type = 'submit';
            this.classList.replace('btn-secondary', 'btn-primary');
            this.querySelector('span').textContent = 'Save';
            document.querySelector('#passwordChangeForm input[name="current_password"]').focus();
        });

        (function () {
            const launcher = document.querySelector('[data-chat-launcher]');
            if (!launcher) return;
            const unreadBadge = document.getElementById('unreadBadge');
            let overlay = document.getElementById('floatingChatOverlay');
            let panel = document.getElementById('floatingChatPanel');
            if (!overlay) { overlay = document.createElement('div'); overlay.id = 'floatingChatOverlay'; overlay.className = 'floating-chat-overlay'; document.body.appendChild(overlay); }
            if (!panel) { panel = document.createElement('div'); panel.id = 'floatingChatPanel'; panel.className = 'floating-chat-panel'; panel.innerHTML = `<div class="floating-chat-header"><div class="floating-chat-title"><i class="fas fa-comments"></i><span>Messages</span></div><div class="floating-chat-actions"><button type="button" class="floating-chat-control" data-chat-minimize aria-label="Minimize"><i class="fas fa-minus"></i></button><button type="button" class="floating-chat-control" data-chat-close aria-label="Close"><i class="fas fa-xmark"></i></button></div></div><iframe class="floating-chat-frame" src="messages.php?embed=1" title="Messages"></iframe>`; document.body.appendChild(panel); }
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
    <script>
        document.querySelectorAll('.password-toggle').forEach(function(toggle) {
            toggle.addEventListener('click', function() {
                const input = toggle.parentElement.querySelector('.password-input');
                const icon = toggle.querySelector('i');
                const showing = input.type === 'text';
                input.type = showing ? 'password' : 'text';
                icon.classList.toggle('fa-eye', showing);
                icon.classList.toggle('fa-eye-slash', !showing);
                toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            });
        });
    </script>
    <script>
        (function() {
            const alertEl = document.querySelector('.alert-success');
            if (!alertEl) return;
            setTimeout(function() {
                alertEl.style.transition = 'opacity 0.3s ease';
                alertEl.style.opacity = '0';
                setTimeout(function() { alertEl.remove(); }, 300);
            }, 2500);
        })();
    </script>
    <script>
        (function() {
            var btn = document.getElementById('smsTestBtn');
            var out = document.getElementById('smsTestResult');
            var status = document.getElementById('smsTestLimitStatus');
            if (!btn || !out) return;
            var configured = <?php echo json_encode($smsGatewayConfigured); ?>;
            var sending = false;
            var quota;
            var retryAt = 0;
            var resetAt = 0;

            function updateQuota(next) {
                quota = next;
                retryAt = Date.now() + Number(quota.retry_after || 0) * 1000;
                resetAt = Date.now() + Number(quota.reset_after || 0) * 1000;
                refreshQuota();
            }
            function refreshQuota() {
                if (quota.reason === 'unavailable') {
                    btn.disabled = true;
                    status.textContent = quota.message;
                    return;
                }
                if (Date.now() >= resetAt) {
                    quota.remaining = 2;
                    resetAt += 86400000;
                }
                var wait = Math.max(0, Math.ceil((retryAt - Date.now()) / 1000));
                btn.disabled = !configured || sending || quota.remaining <= 0 || wait > 0;
                if (quota.remaining <= 0) {
                    status.textContent = 'Daily limit reached (2 of 2). Available again tomorrow.';
                } else {
                    status.textContent = quota.remaining + ' of 2 test SMS remaining today.';
                    if (wait > 0) {
                        status.textContent += ' Next test available in ' + Math.floor(wait / 60) + ':' + String(wait % 60).padStart(2, '0') + '.';
                    }
                }
            }
            updateQuota(<?php echo json_encode($smsTestQuota); ?>);
            setInterval(refreshQuota, 1000);

            btn.addEventListener('click', function() {
                if (btn.disabled || sending) return;
                sending = true;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                out.style.display = 'block';
                out.className = '';
                out.style.color = '#6b7280';
                out.textContent = 'Sending a test message...';

                var body = new URLSearchParams();
                body.append('action', 'send_test_sms');

                fetch('sms_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body.toString()
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.quota) updateQuota(data.quota);
                    out.style.color = data.success ? '#065f46' : '#991b1b';
                    out.textContent = data.message || (data.success ? 'Test SMS sent.' : 'Unable to send the test SMS.');
                })
                .catch(function() {
                    out.style.color = '#991b1b';
                    out.textContent = 'Connection error. Please try again.';
                })
                .finally(function() {
                    sending = false;
                    refreshQuota();
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send me a test SMS';
                });
            });
        })();
    </script>
    <script>
        (function() {
            const modal = document.getElementById('deleteModal');
            const openBtn = document.getElementById('deleteAccountBtn');
            const cancelBtn = document.getElementById('deleteCancelBtn');
            const confirmBtn = document.getElementById('deleteConfirmBtn');
            const passwordInput = document.getElementById('deletePasswordInput');
            const errorEl = document.getElementById('deleteModalError');

            function openModal() {
                modal.classList.add('is-open');
                passwordInput.value = '';
                errorEl.style.display = 'none';
                passwordInput.focus();
            }

            function closeModal() {
                modal.classList.remove('is-open');
            }

            openBtn.addEventListener('click', openModal);
            cancelBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeModal();
            });

            passwordInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') confirmBtn.click();
            });

            confirmBtn.addEventListener('click', async function() {
                const password = passwordInput.value.trim();
                if (!password) {
                    errorEl.textContent = 'Please enter your password.';
                    errorEl.style.display = 'block';
                    return;
                }

                confirmBtn.disabled = true;
                confirmBtn.textContent = 'Deleting...';

                try {
                    const body = new URLSearchParams();
                    body.append('action', 'self_delete');
                    body.append('password', password);

                    const res = await fetch('patient_actions.php', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: body
                    });

                    const data = await res.json();

                    if (data.success && data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        errorEl.textContent = data.message || 'Failed to delete account.';
                        errorEl.style.display = 'block';
                        confirmBtn.disabled = false;
                        confirmBtn.innerHTML = '<i class="fas fa-trash"></i> Delete';
                    }
                } catch (err) {
                    errorEl.textContent = 'Connection error. Please try again.';
                    errorEl.style.display = 'block';
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = '<i class="fas fa-trash"></i> Delete';
                }
            });
        })();
    </script>
</body>
</html>
