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

$sessionCode = $_SESSION['reset_code'] ?? null;
$codeSentAt  = $_SESSION['reset_code_sent_at'] ?? null;
$codeTtlSec  = 3 * 60;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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

                    <div class="back-link-wrapper">
                        <a href="login.php">Back to Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>
