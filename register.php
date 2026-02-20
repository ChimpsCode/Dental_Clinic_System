<?php
session_start();
require_once 'config/database.php';

// Registration is admin-only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
$username = trim(isset($_POST['username']) ? $_POST['username'] : '');
$password = isset($_POST['password']) ? $_POST['password'] : '';
$confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
$email = trim(isset($_POST['email']) ? $_POST['email'] : '');
$firstName = trim(isset($_POST['first_name']) ? $_POST['first_name'] : '');
$lastName = trim(isset($_POST['last_name']) ? $_POST['last_name'] : '');
$role = 'staff';

    if (empty($username) || empty($password) || empty($confirmPassword) || empty($firstName) || empty($lastName)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        try {
            // Check if PDO connection exists
            if (!isset($pdo)) {
                $error = 'Database connection failed. Please check your database configuration.';
            } else {
                // Check if username already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = 'Username already exists';
                } else {
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, ?)");
                    $result = $stmt->execute([$username, $hashedPassword, $email, $firstName, $lastName, $role]);
                    if ($result) {
                        $success = 'Account created successfully! You can now login.';
                    } else {
                        $error = 'Failed to create account. Please try again.';
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = 'Registration failed: ' . htmlspecialchars($e->getMessage());
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - RF Dental Clinic</title>
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
            animation: fadeIn 0.2s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .container {
            display: flex;
            height: 100vh;
            align-items: center;
            justify-content: center;
            padding: 0 90px;
        }

        .left {
            width: 55%;
            display: none;
            align-items: flex-end;
        }

        .dentist-img {
            height: 100vh;
            object-fit: contain;
        }

        .right {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-box {
            background: #f7f7f7;
            width: 515px;
            height: 680px;
            padding: 11px 40px;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, .18);
            text-align: center;
            animation: slideUp 0.6s ease-out;
        }

        .logo {
            width: 60px;
            margin-bottom: -4px;
        }

        .login-box h2 {
            color: #1673ff;
            margin-bottom: 3px;
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

        .role-selection {
            margin: 20px 0 15px 0;
            text-align: left;
        }

        .role-selection label {
            display: block;
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .role-options {
            display: flex;
            gap: 20px;
            margin-bottom: 10px;
        }

        .role-option {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }

        .role-option input[type="radio"] {
            width: auto;
            margin: 0;
            cursor: pointer;
            width: 18px;
            height: 18px;
        }

        .role-option label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
        }

        .login-box select {
            width: 100%;
            padding: 13px;
            margin: 12px 0;
            border: none;
            border-radius: 5px;
            background: #e6e6e6;
            font-size: 14px;
            cursor: pointer;
        }

        .login-box select:focus {
            outline: none;
            background: #ddd;
        }login-box input:focus {
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
            transition: all 0.3s ease;
        }

        .login-box button:hover {
            background: #084bb5;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(13, 91, 215, 0.3);
        }

        .login-box button:active {
            transform: translateY(0);
        }

        .description-text {
            color: #666;
            font-size: 14px;
            margin-bottom: 9px;
            line-height: 1.5;
        }

        .back-link-wrapper {
            margin-top: 20px;
        }

        .back-link-wrapper a {
            color: #0d5bd7;
            text-decoration: none;
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

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-grid.full {
            grid-column: 1 / -1;
        }

        .form-grid input,
        .form-grid .input-group {
            width: 100%;
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
                <img src="assets/images/Logo.png" class="logo" alt="Logo">
                <h2>Create Account</h2>

                <p class="description-text">Create a new account to get started.</p>

                <form method="POST" action="register.php" id="registerForm">
                    <div class="form-grid">
                        <input type="text" name="first_name" id="firstName" placeholder="First Name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                        <input type="text" name="middle_name" id="middleName" placeholder="Middle Name (Optional)" value="<?php echo htmlspecialchars($_POST['middle_name'] ?? ''); ?>">
                    </div>

                    <input type="text" name="last_name" id="lastName" placeholder="Last Name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required style="width: 100%; padding: 13px; margin: 12px 0; border: none; border-radius: 5px; background: #e6e6e6; font-size: 14px;">
                    
                    <div class="form-grid">
                        <input type="text" name="username" id="username" placeholder="Username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                        <input type="email" name="email" id="email" placeholder="Email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-grid">
                        <div class="input-group">
                            <input type="password" name="password" id="password" placeholder="Password" required>
                            <button type="button" class="toggle-password" id="togglePassword" aria-label="Toggle password visibility" style="display: none;">
                                <svg class="eye-icon eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="eye-icon eye-closed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </button>
                        </div>
                        
                        <div class="input-group">
                            <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
                            <button type="button" class="toggle-password" id="toggleConfirmPassword" aria-label="Toggle confirm password visibility" style="display: none;">
                                <svg class="eye-icon eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="eye-icon eye-closed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </button>
                        </div>
                    </div>

                

                    <button type="submit">Create Account</button>
                </form>

                <?php if ($error): ?>
                    <script>
                        setTimeout(() => {
                            showErrorMessage("<?php echo htmlspecialchars($error); ?>");
                        }, 100);
                    </script>
                <?php endif; ?>

                <?php if ($success): ?>
                    <script>
                        setTimeout(() => {
                            showSuccessMessage("<?php echo htmlspecialchars($success); ?>");
                            setTimeout(() => {
                                window.location.href = "login.php";
                            }, 2000);
                        }, 100);
                    </script>
                <?php endif; ?>

                <p style="margin-top: 20px;">
                    <a href="login.php" style="color: #0d5bd7; text-decoration: none;">Already have an account? Login</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        function showSuccessMessage(message) {
            const notification = document.createElement('div');
            notification.className = 'notification success-notification';
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('show');
            }, 100);
            
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 2000);
        }

        function showErrorMessage(message) {
            const notification = document.createElement('div');
            notification.className = 'notification error-notification';
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('show');
            }, 100);
            
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }
    </script>

</body>
</html>

