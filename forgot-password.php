<?php
session_start();
require_once 'config/database.php';

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
                // Store email in session for password reset
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_user_id'] = $user['id'];
                $success = 'Password reset instructions have been sent to your email.';
                // Redirect to new password page after 2 seconds
                header("refresh:2;url=set-new-password.php");
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
