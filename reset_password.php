<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = 'Invalid or missing reset token';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $token = $_POST['token'] ?? '';

    if (empty($password) || empty($confirmPassword)) {
        $error = 'Please fill in all fields';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        try {
            // For now, we'll use the token from session as we don't have a proper token system
            // In production, you should validate the token against a database
            if (isset($_SESSION['reset_user_id'])) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $result = $stmt->execute([$hashedPassword, $_SESSION['reset_user_id']]);
                
                if ($result) {
                    // Clear session
                    unset($_SESSION['reset_user_id']);
                    unset($_SESSION['reset_email']);
                    $success = 'Password reset successfully! You can now login with your new password.';
                    // Redirect to login after 2 seconds
                    header("refresh:2;url=login.php");
                } else {
                    $error = 'Failed to reset password. Please try again.';
                }
            } else {
                $error = 'Invalid reset session. Please request a new password reset.';
            }
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = 'An error occurred. Please try again.';
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

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 13px;
        }

        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ef5350;
        }

        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #66bb6a;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="centered">
            <div class="login-box">
                <img src="assets/images/Logo.png" class="logo" alt="RF Logo">
                <h2>Reset Password</h2>

                <p class="description-text">Enter your new password below.</p>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if (!$success && empty($error)): ?>
                <form method="POST" action="reset_password.php">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <input type="password" name="password" placeholder="New Password" required>
                    
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required>

                    <button type="submit">Reset Password</button>

                    <div class="back-link-wrapper">
                        <a href="login.php" class="page-transition">Back to Login</a>
                    </div>
                </form>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                <div class="back-link-wrapper">
                    <a href="login.php" class="page-transition">Back to Login</a>
                </div>
                <?php endif; ?>
            </div>
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

    <style>
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
    </style>

</body>
</html>
