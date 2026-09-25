<?php
require_once __DIR__ . '/../../includes/session_bootstrap.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/login_device_alert.php';

$token = trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));
$userId = ($pdo instanceof PDO) ? findUserIdForDeviceResetToken($pdo, $token) : 0;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId > 0) {
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if ($password === '' || $confirm === '') {
        $error = 'Please fill in all fields.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $upd = $pdo->prepare('UPDATE users SET password = ?, device_reset_token_hash = NULL, device_reset_expires = NULL WHERE id = ? AND device_reset_token_hash = ? LIMIT 1');
            $upd->execute([$hash, $userId, hash('sha256', strtolower($token))]);
            if ($upd->rowCount() < 1) {
                $userId = 0;
                $error = 'This link has expired. Sign in and use the notification if you still need to change your password.';
            } else {
                bumpUserAuthVersion($pdo, $userId);
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', [
                        'expires' => time() - 42000,
                        'path' => $params['path'] ?: '/',
                        'domain' => $params['domain'] ?? '',
                        'secure' => (bool)$params['secure'],
                        'httponly' => (bool)$params['httponly'],
                        'samesite' => $params['samesite'] ?? 'Lax',
                    ]);
                    session_destroy();
                }
                $success = 'Password saved. Other devices were signed out.';
                $userId = 0;
                header('refresh:2;url=login.php?form=1');
            }
        } catch (Throwable $e) {
            error_log('new-device-password: ' . $e->getMessage());
            $error = 'Unable to change the password. Please try again.';
        }
    }
} elseif ($userId <= 0) {
    $error = 'This link has expired. Sign in and use the notification if you still need to change your password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New device sign-in - RF Dental Clinic</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Segoe UI, Arial, sans-serif; }
        body {
            background: url("../../assets/images/Background.jpg");
            background-size: cover;
            background-attachment: fixed;
            background-repeat: no-repeat;
        }
        .container { display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 20px; }
        .login-box {
            background: #f7f7f7;
            width: 380px;
            max-width: 100%;
            padding: 45px 40px;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, .18);
            text-align: center;
        }
        .logo { width: 50px; margin-bottom: 20px; }
        .login-box h2 { color: #1673ff; margin-bottom: 16px; font-size: 22px; }
        .description-text { color: #666; font-size: 14px; margin-bottom: 20px; line-height: 1.5; }
        .login-box input {
            width: 100%;
            padding: 13px;
            margin: 12px 0;
            border: none;
            border-radius: 5px;
            background: #e6e6e6;
            font-size: 14px;
        }
        .login-box button {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 5px;
            background: #0d5bd7;
            color: white;
            font-weight: bold;
            cursor: pointer;
            margin-top: 15px;
        }
        .notice { padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 13px; }
        .notice.error { color: #f44336; background: #ffebee; }
        .notice.success { color: #2e7d32; background: #e8f5e9; }
        .back-link-wrapper { margin-top: 20px; }
        .back-link-wrapper a { color: #0d5bd7; text-decoration: none; font-weight: 500; }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-box">
            <img src="../../assets/images/Logo.png" class="logo" alt="RF Logo">
            <h2>New device sign-in</h2>
            <p class="description-text">If this sign-in was not you, set a new password. Every browser that is already signed in will be signed out.</p>
            <?php if ($error): ?>
                <div class="notice error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="notice success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($userId > 0 && $success === ''): ?>
                <form method="POST" action="new-device-password.php">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <input type="password" name="password" placeholder="Enter new password" minlength="8" required>
                    <input type="password" name="confirm_password" placeholder="Confirm new password" minlength="8" required>
                    <button type="submit">Save and sign out other devices</button>
                </form>
            <?php endif; ?>
            <div class="back-link-wrapper">
                <a href="login.php?form=1">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
