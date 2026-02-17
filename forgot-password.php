<?php
session_start();
require_once 'config/database.php';
require_once 'config/mailer_config.php';

$error = '';
$success = '';

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
                    // Redirect to new password page after 2 seconds (where you can verify the code if implemented)
                    header("refresh:2;url=set-new-password.php");
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
    <title>Reset Password - RF Dental Clinic</title>
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
            transition: opacity 0.3s ease;
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
            animation: progressBar 2s linear forwards;
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
                transform: translateX(20px);
            }
        }

        body.exit-animation {
            animation: pageExit 0.5s ease-in forwards;
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
                <h2>Reset Password</h2>

                <p class="description-text">Enter your email address to receive a password reset link.</p>

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

                <form method="POST" action="forgot-password.php">
                    <input type="email" name="email" placeholder="Enter your email" required autofocus>

                    <button type="submit">Send Reset Link</button>

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
                    
                    // Add exit animation
                    document.body.classList.add('exit-animation');
                    
                    // Navigate after animation completes
                    setTimeout(() => {
                        window.location.href = url;
                    }, 500);
                });
            });
        });
    </script>

</body>
</html>
