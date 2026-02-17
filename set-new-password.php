<?php
session_start();
require_once 'config/database.php';

// Check if user came from forgot password
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_user_id'])) {
    header('Location: forgot-password.php');
    exit();
}

$error = '';
$success = '';
$email = $_SESSION['reset_email'];
$user_id = $_SESSION['reset_user_id'];

// Ensure we have a verification code in session
$sessionCode = $_SESSION['reset_code'] ?? null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password         = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $entered_code     = trim($_POST['verification_code'] ?? '');

    if (empty($entered_code)) {
        $error = 'Please enter the verification code sent to your email.';
    } elseif (!$sessionCode || $entered_code !== (string)$sessionCode) {
        $error = 'The verification code you entered is incorrect.';
    } elseif (empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);

            $success = 'Password has been reset successfully!';
            // Clear session data
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_code']);
            // Redirect to login after 2 seconds
            header("refresh:2;url=login.php");
        } catch (PDOException $e) {
            $error = 'Failed to reset password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set New Password - RF Dental Clinic</title>
    <link rel="stylesheet" href="assets/css/login.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Segoe UI, Arial, sans-serif;
        }

        body {
            background: url("assets/images/Background.jpg");
            background-size: cover;
            background-attachment: fixed;
            background-repeat: no-repeat;
        }

        .container {
            display: flex;
            height: 100vh;
            align-items: center;
            justify-content: space-between;
            padding: 0 90px;
        }

        .left {
            width: 55%;
            display: flex;
            align-items: flex-end;
        }

        .dentist-img {
            height: 100vh;
            object-fit: contain;
        }

        .right {
            width: 45%;
            display: flex;
            align-items: center;
            justify-content: flex-end;
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

        .login-box input:focus {
            outline: none;
            background: #ddd;
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
        <div class="left">
            <img src="assets/images/Dentists.png" class="dentist-img" alt="Dentist">
        </div>

        <div class="right">
            <div class="login-box">
                <img src="assets/images/Logo.png" class="logo" alt="RF Logo">
                <h2>Set New Password</h2>

                <p class="description-text">Enter your new password to complete the password reset.</p>

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

                <form method="POST" action="set-new-password.php">
                    <input type="text" name="verification_code" placeholder="Enter verification code" required>
                    <input type="password" name="password" placeholder="Enter new password" required>
                    <input type="password" name="confirm_password" placeholder="Confirm new password" required>

                    <button type="submit">Update Password</button>

                    <div class="back-link-wrapper">
                        <a href="login.php">Back to Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>
