<?php
session_start();
require_once '../../config/database.php';

// Load clinic settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settingsRows = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$clinicSettings = [];
foreach ($settingsRows as $key => $value) {
    $clinicSettings[$key] = $value;
}
$clinicName = $clinicSettings['clinic_name'] ?? 'RF Dental Clinic';
$welcomeLogo = $clinicSettings['welcome_logo'] ?? '';
$loginLogo = $welcomeLogo ? '../../' . $welcomeLogo : '../../assets/images/Logo.png';

$error = '';
$success = '';

function isStrongPassword(string $pwd): bool {
    return strlen($pwd) >= 8
        && preg_match('/[A-Z]/', $pwd)
        && preg_match('/[a-z]/', $pwd)
        && preg_match('/[0-9]/', $pwd)
        && preg_match('/[^A-Za-z0-9]/', $pwd);
}

// Handle AJAX forgot-password actions
if (
    isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) && in_array($_POST['action'], ['send_code', 'verify_code', 'change_password'], true)
) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        require_once '../../config/database.php';
        if (!isset($pdo)) {
            throw new Exception('Database connection not available');
        }

        $action = $_POST['action'];

        if ($action === 'send_code') {
            $email = trim($_POST['email'] ?? '');
            if ($email === '') {
                echo json_encode(['success' => false, 'message' => 'Please enter your email address.']);
                exit();
            }

            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ? AND is_archived = 0 LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'Email not found.']);
                exit();
            }

            $resetCode = (string)random_int(100000, 999999);
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_user_id'] = (int)$user['id'];
            $_SESSION['reset_code'] = $resetCode;
            $_SESSION['reset_code_sent_at'] = time();
            unset($_SESSION['reset_code_verified']);

            require_once '../../config/mailer_config.php';
            $subject = 'RF Dental Clinic - Password Reset Code';
            $plain = "Hi " . ($user['username'] ?? 'User') . ", your verification code is: " . $resetCode;
            $html = '<p>Hi ' . htmlspecialchars($user['username'] ?? 'User') . ',</p>'
                . '<p>Your verification code is:</p>'
                . '<p style="font-size:22px;font-weight:bold;letter-spacing:4px;">' . htmlspecialchars($resetCode) . '</p>'
                . '<p>Enter this code to continue resetting your password.</p>';

            $phpMailerBase = __DIR__ . '/../../PHPMailer/src';
            if (!file_exists($phpMailerBase . '/PHPMailer.php')) {
                echo json_encode(['success' => false, 'message' => 'Email service is not configured.']);
                exit();
            }

            require_once $phpMailerBase . '/Exception.php';
            require_once $phpMailerBase . '/PHPMailer.php';
            require_once $phpMailerBase . '/SMTP.php';

            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = gethostbyname(SMTP_HOST);
            $mailer->SMTPAuth = true;
            $mailer->Username = SMTP_USER;
            $mailer->Password = SMTP_PASS;
            $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->Port = SMTP_PORT;
            $mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
            $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mailer->addAddress($email, $user['username'] ?? 'User');
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $html;
            $mailer->AltBody = $plain;
            $mailer->send();

            echo json_encode(['success' => true, 'message' => 'Code sent. Check your email.', 'step' => 2]);
            exit();
        }

        if ($action === 'verify_code') {
            $code = trim($_POST['code'] ?? '');
            $sessionCode = (string)($_SESSION['reset_code'] ?? '');
            $sentAt = (int)($_SESSION['reset_code_sent_at'] ?? 0);
            if ($sessionCode === '' || $sentAt === 0 || (time() - $sentAt) > 180) {
                echo json_encode(['success' => false, 'message' => 'Code expired. Please request a new one.']);
                exit();
            }
            if ($code === '' || $code !== $sessionCode) {
                echo json_encode(['success' => false, 'message' => 'Incorrect verification code.']);
                exit();
            }

            $_SESSION['reset_code_verified'] = true;
            echo json_encode(['success' => true, 'message' => 'Code verified.', 'step' => 3]);
            exit();
        }

        if ($action === 'change_password') {
            if (empty($_SESSION['reset_code_verified']) || empty($_SESSION['reset_user_id'])) {
                echo json_encode(['success' => false, 'message' => 'Verification required before changing password.']);
                exit();
            }

            $newPassword = (string)($_POST['password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');
            if (!isStrongPassword($newPassword)) {
                echo json_encode(['success' => false, 'message' => 'Password must be at least 8 chars with upper, lower, number, and symbol.']);
                exit();
            }
            if ($newPassword !== $confirmPassword) {
                echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
                exit();
            }

            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? LIMIT 1");
            $stmt->execute([$hash, (int)$_SESSION['reset_user_id']]);

            unset(
                $_SESSION['reset_email'],
                $_SESSION['reset_user_id'],
                $_SESSION['reset_code'],
                $_SESSION['reset_code_sent_at'],
                $_SESSION['reset_code_verified']
            );

            echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
            exit();
        }
    } catch (Exception $e) {
        error_log('Forgot password inline flow error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Request failed. Please try again.']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo htmlspecialchars($clinicName); ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($loginLogo); ?>">
    <link rel="stylesheet" href="../../assets/css/login-new.css">
    <style>
        #forgotContainer {
            position: relative;
            max-height: calc(100vh - 24px);
            overflow: hidden;
            padding-top: 12px;
            padding-bottom: 10px;
        }
        #forgotContainer .logo { width: 72px; height: 72px; }
        #forgotContainer .logo-container { margin-bottom: 6px; }
        #forgotContainer .clinic-name { font-size: 24px; line-height: 1.1; margin-bottom: 4px; }
        #forgotContainer .login-subtitle { margin-bottom: 8px; }
        #forgotContainer .input-group { margin-bottom: 10px; }
        #forgotContainer .input-group input { padding: 10px 14px; }
        #forgotContainer .form-links { margin: 10px 0 8px 0 !important; }
        #forgotContainer .login-btn { padding: 12px 20px; margin-top: 8px; }
        .forgot-progress {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin: 8px 0 16px;
        }
        .forgot-progress .node {
            width: 28px;
            height: 28px;
            border-radius: 999px;
            border: 2px solid #cbd5e1;
            color: #64748b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            background: #ffffff;
        }
        .forgot-progress .node.active {
            border-color: #2563eb;
            background: #2563eb;
            color: #ffffff;
        }
        .forgot-progress .node.done {
            border-color: #22c55e;
            background: #22c55e;
            color: #ffffff;
        }
        .forgot-progress .line {
            width: 32px;
            height: 2px;
            background: #cbd5e1;
        }
        .forgot-progress .line.active {
            background: #2563eb;
        }
    </style>
</head>
<body>

    <a href="home.php" class="back-button" aria-label="Go back">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        <span>Back</span>
    </a>

    <div class="login-container">
        <div class="carousel-section">
            <div class="carousel-wrapper">
                <div class="carousel">
                    <div class="carousel-slide active">
                        <img src="../../assets/images/login-image1.jpg" alt="Dental Care" class="carousel-image">
                    </div>
                    <div class="carousel-slide">
                        <img src="../../assets/images/login-image2.jpg" alt="Dental Care" class="carousel-image">
                    </div>
                    <div class="carousel-slide">
                        <img src="../../assets/images/login-image3.jpg" alt="Dental Care" class="carousel-image">
                    </div>
                </div>

                <div class="carousel-content">
                    <h2 class="carousel-heading">Your Best Dental Care in Town,<br>Starts Here</h2>
                </div>

                <div class="carousel-dots">
                    <button class="dot active" data-slide="0" aria-label="Slide 1"></button>
                    <button class="dot" data-slide="1" aria-label="Slide 2"></button>
                    <button class="dot" data-slide="2" aria-label="Slide 3"></button>
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="login-form-container" id="forgotContainer">
                <div class="logo-container">
                    <img src="<?php echo htmlspecialchars($loginLogo); ?>" alt="RF Logo" class="logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <svg class="logo" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" style="display:none;">
                        <path d="M50 20 L30 40 L30 60 L50 80 L70 60 L70 40 Z" fill="#2563eb" stroke="#2563eb" stroke-width="2"/>
                        <text x="50" y="45" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white" text-anchor="middle">R</text>
                        <text x="50" y="70" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white" text-anchor="middle">F</text>
                    </svg>
                </div>

                <h1 class="clinic-name">Reset Password</h1>

                <div class="forgot-progress" id="forgotProgress">
                    <span class="node active" id="fpNode1">1</span>
                    <span class="line" id="fpLine1"></span>
                    <span class="node" id="fpNode2">2</span>
                    <span class="line" id="fpLine2"></span>
                    <span class="node" id="fpNode3">3</span>
                </div>

                <div class="alert alert-error" id="forgotError" style="display: none;"></div>
                <div class="alert alert-success" id="forgotSuccess" style="display: none;"></div>

                <form id="forgotInlineForm" onsubmit="return false;">
                    <div id="fpStep1" class="step-pane">
                        <p class="login-subtitle" style="text-align:center; margin-bottom: 16px;">
                            Enter your email and we will send a 6-digit code.
                        </p>
                        <div class="input-group">
                            <input type="email" id="fp_email" placeholder="Enter your email" required autocomplete="email">
                        </div>
                        <button type="button" class="login-btn" id="fpSendBtn">
                            <span id="fpSendBtnText">Send Code</span>
                        </button>
                    </div>

                    <div id="fpStep2" class="step-pane" style="display:none;">
                        <p class="login-subtitle" style="text-align:center; margin-bottom: 16px;">
                            Enter the 6-digit code sent to your email.
                        </p>
                        <div class="input-group">
                            <input type="text" id="fp_code" placeholder="Enter verification code" maxlength="6" inputmode="numeric" style="text-align: center; letter-spacing: 6px; font-size: 18px;">
                        </div>
                        <button type="button" class="login-btn" id="fpVerifyBtn">
                            <span id="fpVerifyBtnText">Verify Code</span>
                        </button>
                    </div>

                    <div id="fpStep3" class="step-pane" style="display:none;">
                        <p class="login-subtitle" style="text-align:center; margin-bottom: 16px;">
                            Enter your new password.
                        </p>
                        <div class="input-group">
                            <input type="password" id="fp_new_password" placeholder="New password" required autocomplete="new-password">
                        </div>
                        <div class="input-group">
                            <input type="password" id="fp_confirm_password" placeholder="Confirm new password" required autocomplete="new-password">
                        </div>
                        <button type="button" class="login-btn" id="fpChangeBtn">
                            <span id="fpChangeBtnText">Change Password</span>
                        </button>
                    </div>
                </form>

                <div class="form-links" style="margin-top: 16px; justify-content: center;">
                    <span>Remembered your password? </span>
                    <a href="login.php" class="link">Back to login</a>
                </div>
            </div>
        </div>
    </div>

    <div id="toast" class="toast">
        <div class="toast-wrapper">
            <div class="toast-left-border"></div>
            <div class="toast-icon-wrapper">
                <span class="toast-icon"></span>
            </div>
            <div class="toast-content">
                <span class="toast-title"></span>
                <span class="toast-message"></span>
            </div>
        </div>
        <div class="toast-loading-line"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toast = document.getElementById('toast');
            var slides = document.querySelectorAll('.carousel-slide');
            var dots = document.querySelectorAll('.dot');
            var currentSlide = 0;
            var slideInterval;

            function showSlide(n) {
                slides.forEach(function(s) { s.classList.remove('active'); });
                dots.forEach(function(d) { d.classList.remove('active'); });
                if (slides[n]) slides[n].classList.add('active');
                if (dots[n]) dots[n].classList.add('active');
            }

            function nextSlide() {
                currentSlide = (currentSlide + 1) % slides.length;
                showSlide(currentSlide);
            }

            function goToSlide(n) {
                currentSlide = n % slides.length;
                showSlide(currentSlide);
                clearInterval(slideInterval);
                startAutoPlay();
            }

            function startAutoPlay() {
                slideInterval = setInterval(nextSlide, 5000);
            }

            dots.forEach(function(dot, index) {
                dot.addEventListener('click', function() { goToSlide(index); });
            });

            showSlide(0);
            startAutoPlay();

            var fpStep1 = document.getElementById('fpStep1');
            var fpStep2 = document.getElementById('fpStep2');
            var fpStep3 = document.getElementById('fpStep3');
            var fpEmail = document.getElementById('fp_email');
            var fpCode = document.getElementById('fp_code');
            var fpNewPassword = document.getElementById('fp_new_password');
            var fpConfirmPassword = document.getElementById('fp_confirm_password');
            var fpSendBtn = document.getElementById('fpSendBtn');
            var fpSendBtnText = document.getElementById('fpSendBtnText');
            var fpVerifyBtn = document.getElementById('fpVerifyBtn');
            var fpVerifyBtnText = document.getElementById('fpVerifyBtnText');
            var fpChangeBtn = document.getElementById('fpChangeBtn');
            var fpChangeBtnText = document.getElementById('fpChangeBtnText');
            var fpNode1 = document.getElementById('fpNode1');
            var fpNode2 = document.getElementById('fpNode2');
            var fpNode3 = document.getElementById('fpNode3');
            var fpLine1 = document.getElementById('fpLine1');
            var fpLine2 = document.getElementById('fpLine2');
            var forgotError = document.getElementById('forgotError');
            var forgotSuccess = document.getElementById('forgotSuccess');

            function setForgotStep(step) {
                fpStep1.style.display = step === 1 ? 'block' : 'none';
                fpStep2.style.display = step === 2 ? 'block' : 'none';
                fpStep3.style.display = step === 3 ? 'block' : 'none';

                [fpNode1, fpNode2, fpNode3].forEach(function(node) {
                    node.classList.remove('active', 'done');
                });
                [fpLine1, fpLine2].forEach(function(line) {
                    line.classList.remove('active');
                });

                if (step === 1) {
                    fpNode1.classList.add('active');
                } else if (step === 2) {
                    fpNode1.classList.add('done');
                    fpLine1.classList.add('active');
                    fpNode2.classList.add('active');
                } else if (step === 3) {
                    fpNode1.classList.add('done');
                    fpNode2.classList.add('done');
                    fpLine1.classList.add('active');
                    fpLine2.classList.add('active');
                    fpNode3.classList.add('active');
                }
            }

            function clearForgotMessages() {
                forgotError.style.display = 'none';
                forgotSuccess.style.display = 'none';
            }

            function showForgotError(message) {
                forgotError.textContent = message;
                forgotError.style.display = 'block';
                showToast('Error', message, 'error');
            }

            function showForgotSuccess(message) {
                forgotSuccess.textContent = message;
                forgotSuccess.style.display = 'block';
                showToast('Success', message, 'success');
            }

            function postForgotAction(payload) {
                return fetch('reset_password.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(payload).toString()
                }).then(function(res) { return res.json(); });
            }

            fpSendBtn.addEventListener('click', function() {
                var email = (fpEmail.value || '').trim();
                if (!email) {
                    showForgotError('Please enter your email address.');
                    return;
                }
                clearForgotMessages();
                fpSendBtn.disabled = true;
                fpSendBtnText.textContent = 'Sending...';
                postForgotAction({ action: 'send_code', email: email })
                    .then(function(data) {
                        if (!data.success) throw new Error(data.message || 'Failed to send code.');
                        showForgotSuccess(data.message || 'Code sent. Check your email.');
                        setForgotStep(2);
                    })
                    .catch(function(err) {
                        showForgotError(err.message || 'Failed to send code.');
                    })
                    .finally(function() {
                        fpSendBtn.disabled = false;
                        fpSendBtnText.textContent = 'Send Code';
                    });
            });

            fpVerifyBtn.addEventListener('click', function() {
                var code = (fpCode.value || '').trim();
                if (!code) {
                    showForgotError('Enter the 6-digit code sent to your email.');
                    return;
                }
                clearForgotMessages();
                fpVerifyBtn.disabled = true;
                fpVerifyBtnText.textContent = 'Verifying...';
                postForgotAction({ action: 'verify_code', code: code })
                    .then(function(data) {
                        if (!data.success) throw new Error(data.message || 'Verification failed.');
                        showForgotSuccess(data.message || 'Code verified.');
                        setForgotStep(3);
                    })
                    .catch(function(err) {
                        showForgotError(err.message || 'Verification failed.');
                    })
                    .finally(function() {
                        fpVerifyBtn.disabled = false;
                        fpVerifyBtnText.textContent = 'Verify Code';
                    });
            });

            fpChangeBtn.addEventListener('click', function() {
                var newPwd = fpNewPassword.value || '';
                var confirmPwd = fpConfirmPassword.value || '';
                if (!newPwd || !confirmPwd) {
                    showForgotError('Please complete both password fields.');
                    return;
                }
                clearForgotMessages();
                fpChangeBtn.disabled = true;
                fpChangeBtnText.textContent = 'Updating...';
                postForgotAction({ action: 'change_password', password: newPwd, confirm_password: confirmPwd })
                    .then(function(data) {
                        if (!data.success) throw new Error(data.message || 'Failed to update password.');
                        showForgotSuccess(data.message || 'Password updated successfully.');
                        setTimeout(function() {
                            window.location.href = 'login.php';
                        }, 1200);
                    })
                    .catch(function(err) {
                        showForgotError(err.message || 'Failed to update password.');
                    })
                    .finally(function() {
                        fpChangeBtn.disabled = false;
                        fpChangeBtnText.textContent = 'Change Password';
                    });
            });

            function showToast(title, message, type) {
                if (!toast) return;
                var toastTitle = toast.querySelector('.toast-title');
                var toastMessage = toast.querySelector('.toast-message');
                var toastIcon = toast.querySelector('.toast-icon');
                var loadingLine = toast.querySelector('.toast-loading-line');

                if (toastTitle) toastTitle.textContent = title;
                if (toastMessage) toastMessage.textContent = message;
                if (toastIcon) {
                    toastIcon.textContent = type === 'error' ? '\u2715' : '\u2713';
                }
                toast.classList.remove('error', 'success');
                toast.classList.add(type === 'error' ? 'error' : 'success');
                if (loadingLine) {
                    loadingLine.style.animation = 'none';
                    void loadingLine.offsetWidth;
                    loadingLine.style.animation = '';
                }
                toast.classList.add('show');
                setTimeout(function() {
                    toast.classList.remove('show');
                }, 5000);
            }

            setForgotStep(1);

            document.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    if (fpStep1.style.display !== 'none' && fpSendBtn && !fpSendBtn.disabled) {
                        fpSendBtn.click();
                    } else if (fpStep2.style.display !== 'none' && fpVerifyBtn && !fpVerifyBtn.disabled) {
                        fpVerifyBtn.click();
                    } else if (fpStep3.style.display !== 'none' && fpChangeBtn && !fpChangeBtn.disabled) {
                        fpChangeBtn.click();
                    }
                }
            });

            setTimeout(function() { fpEmail.focus(); }, 100);
        });
    </script>

</body>
</html>
