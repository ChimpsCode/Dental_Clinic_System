<?php
ob_start();

require_once __DIR__ . '/../../includes/session_bootstrap.php';

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
$passwordOtpPending = !empty($_SESSION['patient_password_otp']) && (int)($_SESSION['patient_password_otp_expires'] ?? 0) > time();

require_once '../../config/database.php';
require_once '../../includes/patient_session_helper.php';
require_once '../../includes/send_otp_email.php';
require_once '../../includes/patient_user_sync_helper.php';

$patientSessionSettings = getPatientSessionSettings($pdo, (int)$user_id);
$settingsPatientUserJoin = 'LEFT JOIN users u ON u.id = p.user_id';
try {
    $usersColumns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
    $patientsColumns = $pdo->query('SHOW COLUMNS FROM patients')->fetchAll(PDO::FETCH_COLUMN);
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
        
        if ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (password_verify($current_password, $user['password'])) {
                if (strlen($new_password) >= 8) {
                    if ($new_password === $confirm_password) {
                        $passwordOtp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                        $_SESSION['patient_password_otp'] = password_hash($passwordOtp, PASSWORD_DEFAULT);
                        $_SESSION['patient_password_pending'] = password_hash($new_password, PASSWORD_DEFAULT);
                        $_SESSION['patient_password_otp_expires'] = time() + 180;
                        if (sendOtpEmail($user['email'] ?? '', $user['first_name'] ?? 'Patient', $passwordOtp, 'password_change')) {
                            $passwordOtpPending = true;
                            $message = 'A verification code was sent to your email.';
                            $messageType = 'success';
                        } else {
                            unset($_SESSION['patient_password_otp'], $_SESSION['patient_password_pending'], $_SESSION['patient_password_otp_expires']);
                            $message = 'Unable to send the verification code. Please try again.';
                            $messageType = 'error';
                        }
                    } else {
                        $message = 'New passwords do not match.';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Password must be at least 8 characters.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Current password is incorrect.';
                $messageType = 'error';
            }
        }

        if ($action === 'verify_password_otp') {
            $enteredOtp = trim($_POST['password_otp'] ?? '');
            $storedOtp = $_SESSION['patient_password_otp'] ?? '';
            $expires = (int)($_SESSION['patient_password_otp_expires'] ?? 0);
            $pendingPassword = $_SESSION['patient_password_pending'] ?? '';

            if (!$storedOtp || !$pendingPassword || $expires < time()) {
                unset($_SESSION['patient_password_otp'], $_SESSION['patient_password_pending'], $_SESSION['patient_password_otp_expires']);
                $message = 'The verification code has expired. Please start again.';
                $messageType = 'error';
                $passwordOtpPending = false;
            } elseif (!preg_match('/^\d{6}$/', $enteredOtp) || !password_verify($enteredOtp, $storedOtp)) {
                $message = 'The verification code is invalid.';
                $messageType = 'error';
                $passwordOtpPending = true;
            } else {
                $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                $stmt->execute([$pendingPassword, $user_id]);
                unset($_SESSION['patient_password_otp'], $_SESSION['patient_password_pending'], $_SESSION['patient_password_otp_expires']);
                $message = 'Password changed successfully!';
                $messageType = 'success';
                $passwordOtpPending = false;
            }
        }

        if ($action === 'cancel_password_otp') {
            unset($_SESSION['patient_password_otp'], $_SESSION['patient_password_pending'], $_SESSION['patient_password_otp_expires']);
            $passwordOtpPending = false;
            $message = 'Password change cancelled.';
            $messageType = 'success';
        }
        
        if ($action === 'update_notifications') {
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
            
            $stmt = $pdo->prepare("UPDATE users SET email_notifications = ?, sms_notifications = ? WHERE id = ?");
            $stmt->execute([$email_notifications, $sms_notifications, $user_id]);
            $message = 'Notification preferences updated!';
            $messageType = 'success';
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
    <link rel="stylesheet" href="../../assets/css/patient_portal.css">
</head>
<body>
    <aside class="sidebar">
        <a href="../../index.php" class="sidebar-brand">
            <img src="../../assets/images/Logo.png" alt="RF Dental Clinic">
            <span>RF Dental</span>
        </a>
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

    <div class="main-wrapper">
        <header class="dashboard-header">
            <div class="header-left">
                <h1 style="font-size: 20px; font-weight: 700; color: #1a1a2e; margin: 0;">Account Settings</h1>
                <p style="font-size: 13px; color: #6b7280; margin: 4px 0 0 0;">Manage your account preferences and security</p>
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
                        <span class="account-info-label">User ID</span>
                        <span class="account-info-value"><?php echo htmlspecialchars($user['id'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="account-info-row">
                        <span class="account-info-label">Email</span>
                        <span class="account-info-value"><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></span>
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
                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <div class="password-field-wrapper">
                            <input type="password" name="current_password" class="form-input password-input" required>
                            <button type="button" class="password-toggle" aria-label="Show current password"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <div class="password-field-wrapper">
                            <input type="password" name="new_password" class="form-input password-input" minlength="8" required>
                            <button type="button" class="password-toggle" aria-label="Show new password"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <div class="password-field-wrapper">
                            <input type="password" name="confirm_password" class="form-input password-input" minlength="8" required>
                            <button type="button" class="password-toggle" aria-label="Show confirmed password"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>

            <div class="section">
                <h2 class="section-title"><i class="fas fa-bell"></i> Notification Preferences</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_notifications">
                    <div class="checkbox-group">
                        <input type="checkbox" id="email_notifications" name="email_notifications" class="checkbox-input" <?php echo ($user['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                        <label for="email_notifications" class="checkbox-label">Email Notifications</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" id="sms_notifications" name="sms_notifications" class="checkbox-input" <?php echo ($user['sms_notifications'] ?? 0) ? 'checked' : ''; ?>>
                        <label for="sms_notifications" class="checkbox-label">SMS Notifications</label>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Preferences
                    </button>
                </form>
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
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Session Preference</button>
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

            <?php if ($passwordOtpPending): ?>
                <div class="modal-overlay is-open" id="passwordOtpModal">
                    <div class="modal-box">
                        <div class="modal-icon" style="color:#005daa;"><i class="fas fa-envelope-circle-check"></i></div>
                        <h2 class="modal-title">Email Verification Code</h2>
                        <p class="modal-desc">Enter the 6-digit code sent to <?php echo htmlspecialchars($user['email'] ?? 'your email address'); ?> to finish changing your password.</p>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="verify_password_otp">
                            <input type="text" name="password_otp" class="modal-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Enter verification code" autocomplete="one-time-code" required autofocus>
                            <div class="modal-actions">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Verify &amp; Save</button>
                            </div>
                        </form>
                        <form method="POST" action="" style="margin-top:10px;">
                            <input type="hidden" name="action" value="cancel_password_otp">
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
    <a href="messages.php" class="floating-message-btn" data-chat-launcher aria-label="Open messages" title="Messages">
        <i class="fas fa-message"></i>
        <span class="unread-badge" id="unreadBadge" style="display: none;">0</span>
    </a>
    <script>
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
    <script>
        (function() {
            const SESSION_TIMEOUT_ENABLED = <?php echo $patientSessionSettings['enabled'] ? 'true' : 'false'; ?>;
            const LOGOUT_AFTER_MS = <?php echo (int)$patientSessionSettings['timeout_minutes'] * 60 * 1000; ?>;
            if (!SESSION_TIMEOUT_ENABLED) return;
            let timer;
            const reset = () => {
                clearTimeout(timer);
                timer = setTimeout(() => window.location.href = '../../logout.php', LOGOUT_AFTER_MS);
            };
            ['click','mousemove','keydown','scroll','touchstart'].forEach(evt =>
                document.addEventListener(evt, reset, { passive: true })
            );
            reset();
        })();
    </script>
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
