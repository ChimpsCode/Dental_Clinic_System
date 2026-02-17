<?php
session_start();
require_once 'config/database.php';
require_once 'config/mailer_config.php';

// Check if user came from forgot password
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_user_id'])) {
    header('Location: forgot-password.php');
    exit();
}

$error = '';
$success = '';

$sessionCode = $_SESSION['reset_code'] ?? null;
$codeSentAt  = $_SESSION['reset_code_sent_at'] ?? null;
$codeTtlSec  = 3 * 60;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['resend_code'])) {
        // Regenerate code and resend email
        try {
            $email = $_SESSION['reset_email'];
            $stmt = $pdo->prepare("SELECT username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $resetCode = random_int(100000, 999999);
                $_SESSION['reset_code'] = $resetCode;
                $_SESSION['reset_code_sent_at'] = time();

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

                $mailSent = false;
                $phpMailerBase = __DIR__ . '/PHPMailer/src';

                if (file_exists($phpMailerBase . '/PHPMailer.php')) {
                    require_once $phpMailerBase . '/Exception.php';
                    require_once $phpMailerBase . '/PHPMailer.php';
                    require_once $phpMailerBase . '/SMTP.php';

                    try {
                        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
                        $mailer->isSMTP();
                        $mailer->Host       = SMTP_HOST;
                        $mailer->SMTPAuth   = true;
                        $mailer->Username   = SMTP_USER;
                        $mailer->Password   = SMTP_PASS;
                        $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mailer->Port       = SMTP_PORT;

                        $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME);
                        $mailer->addAddress($email, $user['username']);

                        $mailer->isHTML(true);
                        $mailer->Subject = $subject;
                        $mailer->Body    = $htmlMessage;
                        $mailer->AltBody = $message;

                        $mailer->send();
                        $mailSent = true;
                    } catch (\PHPMailer\PHPMailer\Exception $e) {
                        $error = 'Mailer error: ' . htmlspecialchars($e->getMessage());
                        error_log('PHPMailer error (verify-reset-code): ' . $e->getMessage());
                        $mailSent = false;
                    }
                } else {
                    $error = 'PHPMailer files not found. Expected path: ' . $phpMailerBase;
                    error_log('PHPMailer files not found in /PHPMailer/src. Email not sent.');
                }

                if ($mailSent) {
                    $success = 'A new verification code has been sent to your email.';
                } else {
                    if (empty($error)) {
                        $error = 'Unable to send email. Please check PHPMailer and your mailer_config.php credentials.';
                    }
                }
            } else {
                $error = 'User not found.';
            }
        } catch (PDOException $e) {
            $error = 'Request failed. Please try again.';
        }
    } else {
        $entered_code = trim($_POST['verification_code'] ?? '');

        if (!$sessionCode || !$codeSentAt || (time() - $codeSentAt) > $codeTtlSec) {
            $error = 'The verification code has expired. Please request a new one.';
            unset($_SESSION['reset_code'], $_SESSION['reset_code_sent_at']);
        } elseif (empty($entered_code)) {
            $error = 'Please enter the verification code sent to your email.';
        } elseif ($entered_code !== (string)$sessionCode) {
            $error = 'The verification code you entered is incorrect.';
        } else {
            $_SESSION['reset_code_verified'] = true;
            $success = 'Verification successful. Redirecting...';
            header("refresh:1;url=set-new-password.php");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Code - RF Dental Clinic</title>
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
            width: 380px;
            padding: 45px 40px;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, .18);
            text-align: center;
        }

        .logo {
            width: 50px;
            margin-bottom: 20px;
        }

        .login-box h2 {
            color: #1673ff;
            margin-bottom: 25px;
        }

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
            letter-spacing: 1px;
            cursor: pointer;
            margin-top: 15px;
        }

        .login-box button:hover {
            background: #084bb5;
        }

        .description-text {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.5;
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
    </style>
</head>
<body>

    <div class="container">
        <div class="centered">
            <div class="login-box">
                <img src="assets/images/Logo.png" class="logo" alt="RF Logo">
                <h2>Verify Code</h2>

                <p class="description-text">Enter the verification code sent to your email.</p>

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

                <form method="POST" action="verify-reset-code.php">
                    <input type="text" name="verification_code" placeholder="Enter verification code" required>

                    <button type="submit">Verify Code</button>

                    <button type="submit" name="resend_code" value="1" style="margin-top:10px;background:#6b7280;">Resend Code</button>

                    <div class="back-link-wrapper">
                        <a href="login.php">Back to Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>
