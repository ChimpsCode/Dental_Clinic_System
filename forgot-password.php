<?php
session_start();
require_once 'config/database.php';
require_once 'config/mailer_config.php';

$error = '';
$success = '';
$currentStep = isset($_GET['step']) && is_numeric($_GET['step']) ? max(1, min(3, (int)$_GET['step'])) : 1;

// Helpers
function jsonError($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}
function jsonSuccess($data = []) {
    http_response_code(200);
    echo json_encode(['success' => true] + $data);
    exit;
}
function isStrongPassword($pwd) {
    return is_string($pwd)
        && strlen($pwd) >= 8
        && preg_match('/[A-Z]/', $pwd)
        && preg_match('/[a-z]/', $pwd)
        && preg_match('/[0-9]/', $pwd)
        && preg_match('/[^A-Za-z0-9]/', $pwd);
}

// AJAX endpoints for single-page wizard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    if ($action === 'send_code') {
        $email = trim($_POST['email'] ?? '');
        if ($email === '') {
            jsonError('Please enter your email address.');
        }
        try {
            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                jsonError('Email not found.');
            }
            $resetCode = random_int(100000, 999999);
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_code'] = $resetCode;
            $_SESSION['reset_code_sent_at'] = time();

            $subject = 'RF Dental Clinic - Password Reset Code';
            $message = "Hi " . $user['username'] . ",\n\nYour verification code is: " . $resetCode . "\n\nEnter this code in the application to continue resetting your password.";

            // Build HTML email (reusing earlier template)
            ob_start();
            ?>
            <html><head><meta charset="UTF-8"><title>RF Dental Clinic - Password Reset</title></head>
            <body style="margin:0;padding:0;background-color:#f3f4f6;font-family:Segoe UI,Arial,sans-serif;">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f3f4f6;padding:24px 0;">
                    <tr><td align="center">
                        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:520px;background-color:#ffffff;border-radius:12px;box-shadow:0 10px 25px rgba(15,23,42,0.15);overflow:hidden;">
                            <tr><td style="background:linear-gradient(135deg,#2563eb,#1d4ed8);padding:20px 28px;color:#ffffff;">
                                <div style="font-size:18px;font-weight:600;">RF Dental Clinic</div>
                                <div style="font-size:13px;opacity:0.9;margin-top:4px;">Secure password reset</div>
                            </td></tr>
                            <tr><td style="padding:24px 28px 8px 28px;color:#111827;">
                                <div style="font-size:16px;font-weight:600;margin-bottom:8px;">Hi <?php echo htmlspecialchars($user['username']); ?>,</div>
                                <div style="font-size:14px;line-height:1.6;color:#4b5563;">You requested to reset your password for <strong>RF Dental Clinic</strong>.</div>
                            </td></tr>
                            <tr><td style="padding:12px 28px 4px 28px;">
                                <div style="font-size:13px;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;margin-bottom:6px;">Your verification code</div>
                                <div style="display:inline-block;padding:14px 24px;border-radius:999px;background-color:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:22px;font-weight:700;letter-spacing:0.25em;"><?php echo $resetCode; ?></div>
                            </td></tr>
                            <tr><td style="padding:16px 28px 4px 28px;">
                                <div style="font-size:13px;color:#6b7280;line-height:1.6;">Enter this code in the application to continue resetting your password.</div>
                            </td></tr>
                            <tr><td style="padding:4px 28px 20px 28px;">
                                <div style="font-size:12px;color:#9ca3af;line-height:1.6;">If you did not request this, you can safely ignore this email.</div>
                            </td></tr>
                        </table>
                    </td></tr>
                </table>
            </body></html>
            <?php
            $htmlMessage = ob_get_clean();

            $mailSent = false;
            $phpMailerBase = __DIR__ . '/PHPMailer/src';
            if (file_exists($phpMailerBase . '/PHPMailer.php')) {
                require_once $phpMailerBase . '/Exception.php';
                require_once $phpMailerBase . '/PHPMailer.php';
                require_once $phpMailerBase . '/SMTP.php';
                try {
                    $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
                    $mailer->isSMTP();
                    $mailer->Host       = gethostbyname(SMTP_HOST);
                    $mailer->SMTPAuth   = true;
                    $mailer->Username   = SMTP_USER;
                    $mailer->Password   = SMTP_PASS;
                    $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mailer->Port       = SMTP_PORT;
                    $mailer->SMTPOptions = [
                        'ssl' => [
                            'verify_peer'       => false,
                            'verify_peer_name'  => false,
                            'allow_self_signed' => true,
                        ],
                    ];
                    $mailer->Timeout = 20;
                    $mailer->SMTPKeepAlive = false;

                    $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME);
                    $mailer->addAddress($email, $user['username']);
                    $mailer->isHTML(true);
                    $mailer->Subject = $subject;
                    $mailer->Body    = $htmlMessage;
                    $mailer->AltBody = $message;
                    $mailer->send();
                    $mailSent = true;
                } catch (\PHPMailer\PHPMailer\Exception $e) {
                    error_log('PHPMailer error (forgot-password ajax send): ' . $e->getMessage());
                    jsonError('Unable to send email: ' . htmlspecialchars($e->getMessage()), 500);
                }
            } else {
                jsonError('PHPMailer files not found. Expected path: PHPMailer/src');
            }
            if ($mailSent) {
                jsonSuccess(['message' => 'Code sent. Check your email.', 'step' => 2]);
            }
        } catch (Exception $e) {
            jsonError('Request failed. Please try again.', 500);
        }
    } elseif ($action === 'verify_code') {
        $code = trim($_POST['code'] ?? '');
        $sessionCode = $_SESSION['reset_code'] ?? null;
        $codeSentAt  = $_SESSION['reset_code_sent_at'] ?? null;
        $codeTtlSec  = 3 * 60;
        if (!$sessionCode || !$codeSentAt || (time() - $codeSentAt) > $codeTtlSec) {
            jsonError('Code expired. Please request a new one.', 410);
        }
        if ($code === '') {
            jsonError('Enter the code sent to your email.');
        }
        if ($code !== (string)$sessionCode) {
            jsonError('Incorrect code. Please try again.');
        }
        $_SESSION['reset_code_verified'] = true;
        jsonSuccess(['message' => 'Code verified.', 'step' => 3]);
    } elseif ($action === 'change_password') {
        if (empty($_SESSION['reset_code_verified']) || empty($_SESSION['reset_user_id'])) {
            jsonError('Verification required before changing password.', 403);
        }
        $password = trim($_POST['password'] ?? '');
        $confirm  = trim($_POST['confirm_password'] ?? '');
        if (!isStrongPassword($password)) {
            jsonError('Password must be at least 8 chars, with upper, lower, number, and symbol.');
        }
        if ($password !== $confirm) {
            jsonError('Passwords do not match.');
        }
        try {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $_SESSION['reset_user_id']]);
            // Clear reset session
            unset($_SESSION['reset_email'], $_SESSION['reset_user_id'], $_SESSION['reset_code'], $_SESSION['reset_code_sent_at'], $_SESSION['reset_code_verified']);
            jsonSuccess(['message' => 'Password updated. Redirecting to login...', 'redirect' => 'login.php']);
        } catch (Exception $e) {
            jsonError('Failed to update password. Please try again.', 500);
        }
    } else {
        jsonError('Unknown action', 400);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your email address';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Generate a 6-digit verification code
                $resetCode = random_int(100000, 999999);

                // Store details in session for later verification / reset
                $_SESSION['reset_email']   = $email;
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_code']    = $resetCode;
                $_SESSION['reset_code_sent_at'] = time();

                // Prepare email content (plain text + HTML version)
                $subject = 'RF Dental Clinic - Password Reset Code';

                $message = "Hi " . $user['username'] . ",\n\n"
                    . "You requested to reset your password for RF Dental Clinic.\n\n"
                    . "Your verification code is: " . $resetCode . "\n\n"
                    . "Enter this code in the application to continue resetting your password.\n\n"
                    . "If you did not request this, you can safely ignore this email.\n\n"
                    . "Best regards,\n"
                    . "RF Dental Clinic";

                $htmlMessage = '
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>RF Dental Clinic - Password Reset</title>
                </head>
                <body style="margin:0;padding:0;background-color:#f3f4f6;font-family:Segoe UI,Arial,sans-serif;">
                    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f3f4f6;padding:24px 0;">
                        <tr>
                            <td align="center">
                                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:520px;background-color:#ffffff;border-radius:12px;box-shadow:0 10px 25px rgba(15,23,42,0.15);overflow:hidden;">
                                    <tr>
                                        <td style="background:linear-gradient(135deg,#2563eb,#1d4ed8);padding:20px 28px;color:#ffffff;">
                                            <div style="font-size:18px;font-weight:600;">RF Dental Clinic</div>
                                            <div style="font-size:13px;opacity:0.9;margin-top:4px;">Secure password reset</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:24px 28px 8px 28px;color:#111827;">
                                            <div style="font-size:16px;font-weight:600;margin-bottom:8px;">Hi ' . htmlspecialchars($user['username']) . ',</div>
                                            <div style="font-size:14px;line-height:1.6;color:#4b5563;">
                                                You requested to reset your password for <strong>RF Dental Clinic</strong>.
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:12px 28px 4px 28px;">
                                            <div style="font-size:13px;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;margin-bottom:6px;">
                                                Your verification code
                                            </div>
                                            <div style="display:inline-block;padding:14px 24px;border-radius:999px;background-color:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:22px;font-weight:700;letter-spacing:0.25em;">
                                                ' . $resetCode . '
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:16px 28px 4px 28px;">
                                            <div style="font-size:13px;color:#6b7280;line-height:1.6;">
                                                Enter this code in the application to continue resetting your password.
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:4px 28px 20px 28px;">
                                            <div style="font-size:12px;color:#9ca3af;line-height:1.6;">
                                                If you did not request this, you can safely ignore this email.
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0 28px 24px 28px;">
                                            <div style="font-size:13px;color:#4b5563;margin-bottom:4px;">Best regards,</div>
                                            <div style="font-size:13px;color:#111827;font-weight:600;">RF Dental Clinic</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="background-color:#f9fafb;padding:10px 28px 14px 28px;">
                                            <div style="font-size:11px;color:#9ca3af;line-height:1.5;">
                                                This is an automated message. Please do not reply directly to this email.
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </body>
                </html>';

                // Use PHPMailer directly (no Composer) with your Gmail credentials from mailer_config.php
                $mailSent = false;
                $phpMailerBase = __DIR__ . '/PHPMailer/src';

                if (file_exists($phpMailerBase . '/PHPMailer.php')) {
                    require_once $phpMailerBase . '/Exception.php';
                    require_once $phpMailerBase . '/PHPMailer.php';
                    require_once $phpMailerBase . '/SMTP.php';

                    try {
                        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
                        $mailer->isSMTP();
                        // Force IPv4 to avoid some local DNS/IPv6 issues
                        $mailer->Host       = gethostbyname(SMTP_HOST);
                        $mailer->SMTPAuth   = true;
                        $mailer->Username   = SMTP_USER;
                        $mailer->Password   = SMTP_PASS;
                        $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mailer->Port       = SMTP_PORT;
                        // Relax SSL checks for local dev; remove in production if not needed
                        $mailer->SMTPOptions = [
                            'ssl' => [
                                'verify_peer'       => false,
                                'verify_peer_name'  => false,
                                'allow_self_signed' => true,
                            ],
                        ];

                        $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME);
                        $mailer->addAddress($email, $user['username']);

                        $mailer->isHTML(true);
                        $mailer->Timeout = 20; // seconds, slightly longer but still snappy
                        $mailer->SMTPKeepAlive = false;
                        $mailer->Subject = $subject;
                        $mailer->Body    = $htmlMessage;
                        $mailer->AltBody = $message;

                        $mailer->send();
                        $mailSent = true;
                    } catch (\PHPMailer\PHPMailer\Exception $e) {
                        // Surface the detailed PHPMailer error so you can see the actual problem
                        $error = 'Mailer error: ' . htmlspecialchars($e->getMessage());
                        error_log('PHPMailer error (forgot-password): ' . $e->getMessage());
                        $mailSent = false;
                    }
                } else {
                    $error = 'PHPMailer files not found. Expected path: ' . $phpMailerBase;
                    error_log('PHPMailer files not found in /PHPMailer/src. Email not sent.');
                }

                if ($mailSent) {
                    $success = 'A verification code has been sent to your email address.';
                    // Redirect to verification page after 2 seconds
                    header("refresh:2;url=verify-reset-code.php");
                } else {
                    // If $error was not already set in the PHPMailer block, provide a generic message
                    if (empty($error)) {
                        $error = 'Unable to send email. Please check that PHPMailer is in the PHPMailer/src folder and that your Gmail/app password in mailer_config.php is correct.';
                    }
                }
            } else {
                $error = 'Email not found';
            }
        } catch (PDOException $e) {
            $error = 'Request failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - RF Dental Clinic</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Segoe UI, Arial, sans-serif;
        }

        body {
            margin: 0;
            padding: 0;
            width: 100vw;
            height: 100vh;
            background: url("assets/images/Background.jpg");
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            position: fixed;
        }

        .container {
            display: flex;
            height: 100vh;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .centered {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-box {
            background: #f7f7f7;
            width: 420px;
            padding: 42px 38px 32px;
            border-radius: 16px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.22);
            text-align: center;
        }

        .logo {
            width: 50px;
            margin-bottom: 20px;
         
        }

        .login-box h2 {
            color: #0f172a;
            margin-bottom: 8px;
            font-size: 26px;
        }

        .login-box input {
            width: 100%;
            padding: 14px;
            margin: 14px 0;
            border: 1px solid #d6d6d6;
            border-radius: 10px;
            background: #ffffff;
            font-size: 14px;
            outline: none;
        }

        .login-box button {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            font-weight: 700;
            letter-spacing: 0.5px;
            cursor: pointer;
            margin-top: 12px;
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.35);
            transition: transform 0.1s ease, box-shadow 0.2s ease, opacity 0.15s ease;
        }

        .login-box button:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 28px rgba(37, 99, 235, 0.4);
        }

        .login-box button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
            box-shadow: 0 8px 16px rgba(37, 99, 235, 0.25);
        }

        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.45);
            border-top-color: #fff;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .description-text {
            color: #475569;
            font-size: 14px;
            margin-bottom: 22px;
            line-height: 1.6;
        }

        .stepper {
            display: inline-flex;
            align-items: center;
            gap: 30px;
            margin: 18px auto 26px;
            padding: 0 5px;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            flex: 1;
            position: relative;
        }
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 16px;
            right: -80%;
            width: 100%;
            height: 2px;
            background: #e2e8f0;
            z-index: 0;
        }
        .step-number {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-weight: 700;
            font-size: 14px;
            background: #e2e8f0;
            color: #475569;
            position: relative;
            z-index: 1;
        }
        .step.active .step-number {
            background: #1d4ed8;
            color: #fff;
            box-shadow: 0 10px 20px rgba(29, 78, 216, 0.28);
            border: 2px solid #93c5fd;
        }
        .step.completed .step-number {
            background: #bfdbfe;
            color: #0f172a;
            box-shadow: 0 8px 16px rgba(15, 23, 42, 0.12);
            border: 1px solid #bfdbfe;
        }
        .step label {
            font-size: 12px;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .back-link-wrapper {
            margin-top: 20px;
        }

        .back-link-wrapper a {
            color: #0d5bd7;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link-wrapper a:hover {
            text-decoration: underline;
        }

        /* Notification Toast */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.25s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            max-width: 350px;
            word-wrap: break-word;
            overflow: hidden;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 5px solid;
            background: white;
        }

        .notification.show {
            opacity: 1;
        }

        .notification::before {
            content: '✓';
            font-size: 20px;
            font-weight: bold;
            flex-shrink: 0;
        }

        .notification::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: currentColor;
            animation: progressBar 1.6s linear forwards;
        }

        @keyframes progressBar {
            0% {
                width: 0;
            }
            100% {
                width: 100%;
            }
        }

        .success-notification {
            color: #4caf50;
        }

        .success-notification::before {
            color: #4caf50;
        }

        .error-notification {
            color: #f44336;
        }

        .error-notification::before {
            content: '✕';
        }

        /* Page Exit Animation */
        @keyframes pageExit {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(18px);
            }
        }

        body.exit-animation {
            animation: pageExit 0.35s ease-in forwards;
        }

        @media (max-width: 1024px) {
            .container {
                padding: 0 50px;
            }

            .left {
                width: 40%;
            }

            .right {
                width: 60%;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
                flex-direction: column;
            }

            .left {
                width: 100%;
                display: none;
            }

            .right {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="centered">
            <div class="login-box">
                <img src="assets/images/Logo.png" class="logo" alt="RF Logo">
                <h2>Forgot Password</h2>

                <p class="description-text">Enter your email address and we’ll email you a 6-digit code to reset your password.</p>

                <div class="stepper">
                    <div class="step <?php echo $currentStep === 1 ? 'active' : ($currentStep > 1 ? 'completed' : ''); ?>">
                        <div class="step-number">1</div>
                    </div>
                    <div class="step <?php echo $currentStep === 2 ? 'active' : ($currentStep > 2 ? 'completed' : ''); ?>">
                        <div class="step-number">2</div>
                    </div>
                    <div class="step <?php echo $currentStep === 3 ? 'active' : ''; ?>">
                        <div class="step-number">3</div>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div style="color: #f44336; background: #ffebee; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 13px;">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div style="color: #4caf50; background: #e8f5e9; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 13px;">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form id="resetForm">
                    <div id="step1" class="step-pane">
                        <input type="email" name="email" id="email" placeholder="Enter your email" required autofocus>
                        <button type="submit" id="sendBtn"><span id="sendBtnText">Send Code</span></button>
                    </div>

                    <div id="step2" class="step-pane" style="display:none;">
                        <input type="text" name="code" id="code" placeholder="Enter verification code" maxlength="6" pattern="\\d{6}">
                        <button type="button" id="verifyBtn"><span id="verifyBtnText">Verify Code</span></button>
                    </div>

                    <div id="step3" class="step-pane" style="display:none;">
                        <input type="password" name="password" id="password" placeholder="Enter new password" required>
                        <div id="pwdRequirements" style="display:none; background:rgba(15,23,42,0.04); border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; text-align:left; margin:8px 0 12px; color:#0f172a;">
                            <div style="font-weight:700; margin-bottom:6px;">Password Requirements:</div>
                            <ul style="list-style:none; padding-left:0; margin:0; font-size:13px; line-height:1.55;">
                                <li data-rule="len" style="display:flex; gap:8px; align-items:center;"><span class="req-icon">✗</span>At least 8 characters</li>
                                <li data-rule="upper" style="display:flex; gap:8px; align-items:center;"><span class="req-icon">✗</span>At least one uppercase letter</li>
                                <li data-rule="lower" style="display:flex; gap:8px; align-items:center;"><span class="req-icon">✗</span>At least one lowercase letter</li>
                                <li data-rule="num" style="display:flex; gap:8px; align-items:center;"><span class="req-icon">✗</span>At least one number</li>
                                <li data-rule="sym" style="display:flex; gap:8px; align-items:center;"><span class="req-icon">✗</span>At least one symbol</li>
                            </ul>
                        </div>
                        <div id="strengthMeter" style="display:none; text-align:left; font-size:12px; margin:4px 0 10px; color:#475569;">
                            <div id="strengthBar" style="height:6px; border-radius:999px; background:#e2e8f0; overflow:hidden;">
                                <span id="strengthFill" style="display:block; height:100%; width:10%; background:#ef4444; transition:width 0.2s ease, background 0.2s ease;"></span>
                            </div>
                            <div id="strengthLabel" style="margin-top:6px; font-weight:600;">Weak password</div>
                        </div>
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password" required>
                        <button type="button" id="changeBtn"><span id="changeBtnText">Change Password</span></button>
                    </div>

                    <div class="back-link-wrapper">
                        <a href="login.php" class="page-transition">Back to Login</a>
                    </div>
                </form>
            </div>
    </div>

    <script>
        // Page transition effect for navigation links
        document.addEventListener('DOMContentLoaded', function() {
            const pageTransitionLinks = document.querySelectorAll('.page-transition');
            pageTransitionLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const url = this.href;
                    document.body.classList.add('exit-animation');
                    setTimeout(() => { window.location.href = url; }, 350);
                });
            });

            const stepElems = document.querySelectorAll('.step');
            const stepperNums = [1,2,3];
            const resetForm = document.getElementById('resetForm');
            const setStep = (n) => {
                stepperNums.forEach((num, idx) => {
                    const el = stepElems[idx];
                    el.classList.remove('active','completed');
                    if (num === n) el.classList.add('active');
                    if (num < n) el.classList.add('completed');
                });
                document.getElementById('step1').style.display = n === 1 ? 'block' : 'none';
                document.getElementById('step2').style.display = n === 2 ? 'block' : 'none';
                document.getElementById('step3').style.display = n === 3 ? 'block' : 'none';
            };

            const showError = (msg) => {
                alert(msg);
            };
            const postJson = (data, onSuccess) => {
                fetch('forgot-password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(data)
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        onSuccess(res);
                    } else {
                        showError(res.message || 'Something went wrong');
                    }
                })
                .catch(() => showError('Network error. Please try again.'));
            };

            // Buttons
            const sendBtn = document.getElementById('sendBtn');
            const sendBtnText = document.getElementById('sendBtnText');
            const verifyBtn = document.getElementById('verifyBtn');
            const verifyBtnText = document.getElementById('verifyBtnText');
            const changeBtn = document.getElementById('changeBtn');
            const changeBtnText = document.getElementById('changeBtnText');
            const pwdInput = document.getElementById('password');
            const confirmInput = document.getElementById('confirm_password');
            const strengthMeter = document.getElementById('strengthMeter');
            const strengthFill = document.getElementById('strengthFill');
            const strengthLabel = document.getElementById('strengthLabel');
            const reqBox = document.getElementById('pwdRequirements');
            const reqItems = {
                len: reqBox.querySelector('[data-rule="len"] .req-icon'),
                upper: reqBox.querySelector('[data-rule="upper"] .req-icon'),
                lower: reqBox.querySelector('[data-rule="lower"] .req-icon'),
                num: reqBox.querySelector('[data-rule="num"] .req-icon'),
                sym: reqBox.querySelector('[data-rule="sym"] .req-icon'),
            };

            const strengthLevels = [
                {label:'Very Weak', color:'#ef4444', min:0},
                {label:'Weak', color:'#f97316', min:25},
                {label:'Fair', color:'#f59e0b', min:45},
                {label:'Good', color:'#10b981', min:65},
                {label:'Strong', color:'#059669', min:80}
            ];

            const computeStrength = (value) => {
                if (!value) return 0;
                let score = Math.min(40, value.length * 4); // length weight
                if (/[A-Z]/.test(value)) score += 15;
                if (/[a-z]/.test(value)) score += 15;
                if (/[0-9]/.test(value)) score += 15;
                if (/[^A-Za-z0-9]/.test(value)) score += 15;
                if (value.length >= 12) score += 10;
                return Math.min(score, 100);
            };

            const updateStrength = () => {
                const val = pwdInput.value;
                const score = computeStrength(val);
                if (val.length === 0) {
                    strengthMeter.style.display = 'none';
                    reqBox.style.display = 'none';
                    return;
                }
                reqBox.style.display = 'block';
                strengthMeter.style.display = 'block';
                strengthFill.style.width = `${Math.max(score,10)}%`;
                const level = strengthLevels.slice().reverse().find(l => score >= l.min) || strengthLevels[0];
                strengthFill.style.background = level.color;
                strengthLabel.textContent = `${level.label} password`;

                // update requirement ticks
                const checks = {
                    len: val.length >= 8,
                    upper: /[A-Z]/.test(val),
                    lower: /[a-z]/.test(val),
                    num: /[0-9]/.test(val),
                    sym: /[^A-Za-z0-9]/.test(val),
                };
                Object.entries(checks).forEach(([key, ok]) => {
                    reqItems[key].textContent = ok ? '✓' : '✗';
                    reqItems[key].style.color = ok ? '#10b981' : '#ef4444';
                });
            };

            pwdInput.addEventListener('input', updateStrength);
            confirmInput.addEventListener('input', () => {
                confirmInput.setCustomValidity(confirmInput.value && confirmInput.value !== pwdInput.value ? 'Passwords do not match' : '');
            });


            sendBtn.addEventListener('click', () => {
                sendBtn.disabled = true;
                sendBtnText.innerHTML = '<span class="spinner"></span>Sending...';
                postJson({ action: 'send_code', email: document.getElementById('email').value }, (res) => {
                    setStep(2);
                });
                setTimeout(() => { sendBtn.disabled = false; sendBtnText.textContent = 'Send Code'; }, 800);
            });

            verifyBtn.addEventListener('click', () => {
                verifyBtn.disabled = true;
                verifyBtnText.innerHTML = '<span class="spinner"></span>Verifying...';
                postJson({ action: 'verify_code', code: document.getElementById('code').value }, (res) => {
                    setStep(3);
                });
                setTimeout(() => { verifyBtn.disabled = false; verifyBtnText.textContent = 'Verify Code'; }, 800);
            });

            changeBtn.addEventListener('click', () => {
                changeBtn.disabled = true;
                changeBtnText.innerHTML = '<span class="spinner"></span>Updating...';
                postJson({
                    action: 'change_password',
                    password: document.getElementById('password').value,
                    confirm_password: document.getElementById('confirm_password').value
                }, (res) => {
                    changeBtnText.textContent = 'Success';
                    setTimeout(() => { window.location.href = res.redirect || 'login.php'; }, 800);
                });
                setTimeout(() => { changeBtn.disabled = false; changeBtnText.textContent = 'Change Password'; }, 1200);
            });

            // Allow Enter/submit to trigger the relevant action per step
            if (resetForm) {
                resetForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    if (document.getElementById('step1').style.display !== 'none') {
                        sendBtn.click();
                    } else if (document.getElementById('step2').style.display !== 'none') {
                        verifyBtn.click();
                    } else if (document.getElementById('step3').style.display !== 'none') {
                        changeBtn.click();
                    }
                });
            }

            // initialize step view
            setStep(1);
        });
    </script>

</body>
</html>
