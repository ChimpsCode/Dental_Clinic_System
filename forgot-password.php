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
// Determine where to go back to
$referrer = $_SERVER['HTTP_REFERER'] ?? 'home.php';
// Only allow internal pages
if (strpos($referrer, 'forgot-password.php') !== false || 
    strpos($referrer, 'patient_register.php') !== false) {
    $referrer = 'login.php';
}

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

            // Build HTML email
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - RF Dental Clinic</title>
    <link rel="icon" type="image/png" href="assets/images/Logo.png">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="container">
        <div class="login-form-container" style="max-width: 450px;">
            <!-- Close button to go back -->
            <button type="button" class="login-close-btn" onclick="window.location.href='<?php echo htmlspecialchars($referrer); ?>'" aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>

            <div class="logo-container">
                <img src="assets/images/Logo.png" alt="RF Logo" class="logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <svg class="logo" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" style="display:none;">
                    <path d="M50 20 L30 40 L30 60 L50 80 L70 60 L70 40 Z" fill="#2563eb" stroke="#2563eb" stroke-width="2"/>
                    <text x="50" y="45" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white" text-anchor="middle">R</text>
                    <text x="50" y="70" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white" text-anchor="middle">F</text>
                </svg>
            </div>
            
            <h1 class="clinic-name">Forgot Password</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error" style="background-color: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" style="background-color: #dcfce7; color: #16a34a; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form id="resetForm">
                <div id="step1" class="step-pane">
                    <p style="color: #6b7280; font-size: 14px; margin-bottom: 20px; text-align: center;">
                        Enter your email address and we'll email you a 6-digit code to reset your password.
                    </p>
                    <div class="input-group">
                        <input type="email" name="email" id="email" placeholder="Enter your email" required autofocus>
                    </div>
                    <button type="button" class="login-btn" id="sendBtn">
                        <span id="sendBtnText">Send Code</span>
                    </button>
                </div>

                <div id="step2" class="step-pane" style="display:none;">
                    <p style="color: #6b7280; font-size: 14px; margin-bottom: 20px; text-align: center;">
                        Enter the 6-digit code sent to your email.
                    </p>
                    <div class="input-group">
                        <input type="text" name="code" id="code" placeholder="Enter verification code" maxlength="6" pattern="\d{6}" style="text-align: center; letter-spacing: 8px; font-size: 18px;">
                    </div>
                    <button type="button" class="login-btn" id="verifyBtn">
                        <span id="verifyBtnText">Verify Code</span>
                    </button>
                </div>

                <div id="step3" class="step-pane" style="display:none;">
                    <p style="color: #6b7280; font-size: 14px; margin-bottom: 20px; text-align: center;">
                        Enter your new password.
                    </p>
                    <div class="input-group">
                        <input type="password" name="password" id="password" placeholder="New password" required>
                    </div>
                    <div class="input-group">
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password" required>
                    </div>
                    <button type="button" class="login-btn" id="changeBtn">
                        <span id="changeBtnText">Change Password</span>
                    </button>
                </div>

                <div class="form-links" style="margin-top: 20px; justify-content: center;">
                    <a href="login.php" class="link page-transition">Back to Login</a>
                </div>
            </form>
        </div>
    </div>

    <script>
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
                    if (stepElems[idx]) {
                        stepElems[idx].classList.remove('active','completed');
                        if (num === n) stepElems[idx].classList.add('active');
                        if (num < n) stepElems[idx].classList.add('completed');
                    }
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

            const sendBtn = document.getElementById('sendBtn');
            const sendBtnText = document.getElementById('sendBtnText');
            const verifyBtn = document.getElementById('verifyBtn');
            const verifyBtnText = document.getElementById('verifyBtnText');
            const changeBtn = document.getElementById('changeBtn');
            const changeBtnText = document.getElementById('changeBtnText');

            sendBtn.addEventListener('click', () => {
                sendBtn.disabled = true;
                sendBtnText.textContent = 'Sending...';
                postJson({ action: 'send_code', email: document.getElementById('email').value }, (res) => {
                    setStep(2);
                });
                setTimeout(() => { sendBtn.disabled = false; sendBtnText.textContent = 'Send Code'; }, 800);
            });

            verifyBtn.addEventListener('click', () => {
                verifyBtn.disabled = true;
                verifyBtnText.textContent = 'Verifying...';
                postJson({ action: 'verify_code', code: document.getElementById('code').value }, (res) => {
                    setStep(3);
                });
                setTimeout(() => { verifyBtn.disabled = false; verifyBtnText.textContent = 'Verify Code'; }, 800);
            });

            changeBtn.addEventListener('click', () => {
                changeBtn.disabled = true;
                changeBtnText.textContent = 'Updating...';
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

            // Initialize step view
            setStep(1);
        });
    </script>
</body>
</html>
