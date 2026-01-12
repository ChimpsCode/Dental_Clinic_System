<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') {    
    // Handle AJAX login request
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
            exit();
        }

        // Allow quick admin login without DB for development/testing
        if ($username === 'admin' && $password === 'admin123') {
            $_SESSION['user_id'] = 0;
            $_SESSION['username'] = 'admin';
            $_SESSION['role'] = 'admin';
            $_SESSION['full_name'] = 'Administrator';
            echo json_encode(['success' => true, 'message' => 'Logged in successfully', 'redirect' => 'admin_dashboard.php']);
            exit();
        }

        try {
            require_once 'config/database.php';
            
            if (!isset($pdo)) {
                throw new Exception('Database connection not available');
            }
            
            $stmt = $pdo->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'] ?? 'admin';
                if (!empty($user['full_name'])) {
                    $_SESSION['full_name'] = $user['full_name'];
                }
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    $redirect = 'admin_dashboard.php';
                } elseif ($user['role'] === 'staff') {
                    $redirect = 'staff-dashboard.php';
                } else {
                    $redirect = 'dashboard.php';
                }
                echo json_encode(['success' => true, 'message' => 'Logged in successfully', 'redirect' => $redirect]);
                exit();
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
                exit();
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database connection error.']);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Login failed. Please try again.']);
            exit();
        }
    }
    
    // Regular form submission (for non-JS fallback)
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        // Allow quick admin login without DB for development/testing
        if ($username === 'admin' && $password === 'admin123') {
            $_SESSION['user_id'] = 0;
            $_SESSION['username'] = 'admin';
            $_SESSION['role'] = 'admin';
            $_SESSION['full_name'] = 'Administrator';
            header('Location: admin_dashboard.php');
            exit();
        }

        try {
            require_once 'config/database.php';
            
            if (!isset($pdo)) {
                throw new Exception('Database connection not available');
            }
            
            $stmt = $pdo->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'] ?? 'admin';
                if (!empty($user['full_name'])) {
                    $_SESSION['full_name'] = $user['full_name'];
                }
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header('Location: admin_dashboard.php');
                } elseif ($user['role'] === 'staff') {
                    header('Location: staff-dashboard.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit();
            } else {
                $error = 'Invalid username or password';
            }
        } catch (PDOException $e) {
            $error = 'Database connection error. Please check your database settings.';
        } catch (Exception $e) {
            $error = 'Login failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RF Dental Clinic - Login</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="container">
        <img src="assets/images/Dentists.png" alt="Dentist" class="dentist-image" onerror="this.style.display='none';">
        <div class="login-form-container">
            <div class="logo-container">
                <img src="assets/images/Logo.png" alt="RF Logo" class="logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <svg class="logo" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" style="display:none;">
                    <path d="M50 20 L30 40 L30 60 L50 80 L70 60 L70 40 Z" fill="#2563eb" stroke="#2563eb" stroke-width="2"/>
                    <text x="50" y="45" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white" text-anchor="middle">R</text>
                    <text x="50" y="70" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white" text-anchor="middle">F</text>
                </svg>
            </div>
            
            <h1 class="clinic-name">RF Dental Clinic</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error" id="errorMessage"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form id="loginForm" method="POST">
                <div class="input-group">
                    <input type="text" id="username" name="username" placeholder="Username" required autocomplete="username">
                </div>
                
                <div class="input-group">
                    <input type="password" id="password" name="password" placeholder="Password" required autocomplete="current-password">
                    <button type="button" class="toggle-password" id="togglePassword" aria-label="Toggle password visibility">
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
                
                <div class="form-links">
                    <a href="forgot-password.php" class="link">Forgot password ?</a>
                    <span class="separator">or</span>
                    <a href="register.php" class="link">create an account</a>
                </div>
                
                <button type="submit" class="login-btn" id="loginBtn">
                    <span id="loginBtnText">LOGIN</span>
                    <span id="loginSpinner" style="display: none; margin-left: 8px;">⏳</span>
                </button>
            </form>
        </div>
    </div>
    
    <div id="toast" class="toast">
        <div class="toast-wrapper">
            <span class="toast-line toast-line-top"></span>
            <span class="toast-line toast-line-right"></span>
            <span class="toast-line toast-line-bottom"></span>
            <span class="toast-line toast-line-left"></span>
            <span class="toast-icon">✓</span>
            <span class="toast-message">Login Successful!</span>
        </div>
    </div>
    
    <script src="assets/js/login.js"></script>
</body>
</html>

