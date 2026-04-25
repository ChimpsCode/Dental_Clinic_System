<?php
ob_start();

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

require_once '../../config/database.php';

try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.first_name, u.middle_name, u.last_name, u.email, u.phone, u.created_at
        FROM patients p 
        LEFT JOIN users u ON u.id = p.user_id 
        WHERE p.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
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
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed_password, $user_id]);
                        $message = 'Password changed successfully!';
                        $messageType = 'success';
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
        
        if ($action === 'update_notifications') {
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
            
            $stmt = $pdo->prepare("UPDATE users SET email_notifications = ?, sms_notifications = ? WHERE id = ?");
            $stmt->execute([$email_notifications, $sms_notifications, $user_id]);
            $message = 'Notification preferences updated!';
            $messageType = 'success';
        }
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $user = [];
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
        .btn { padding: 12px 24px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: #005daa; color: white; }
        .btn-primary:hover { background: #004080; }
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
            <a href="profile.php" class="menu-item">
                <i class="fas fa-user"></i>
                <span>My profile</span>
            </a>
            <a href="billing.php" class="menu-item">
                <i class="fas fa-credit-card"></i>
                <span>Billing</span>
            </a>
            <a href="../shared/settings.php" class="menu-item active">
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
                <div class="notification-bell">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php 
                            $first = $patient['first_name'] ?? $_SESSION['user_name'] ?? '';
                            $last = $patient['last_name'] ?? '';
                            $initials = strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
                            echo htmlspecialchars($initials ?: 'P');
                        ?>
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
                        <input type="password" name="current_password" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-input" minlength="8" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-input" minlength="8" required>
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

            <div class="section danger-zone">
                <h2 class="section-title"><i class="fas fa-exclamation-triangle"></i> Danger Zone</h2>
                <p style="color: #6b7280; font-size: 14px; margin-bottom: 16px;">
                    Once you delete your account, there is no going back. Please be certain.
                </p>
                <button type="button" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete your account? This action cannot be undone.')">
                    <i class="fas fa-trash"></i> Delete Account
                </button>
            </div>
        </main>
    </div>
</body>
</html>
