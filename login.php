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
        // Set JSON header
        header('Content-Type: application/json; charset=utf-8');
        
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
if (empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
            exit();
        }

        try {
            require_once 'config/database.php';
            
            if (!isset($pdo)) {
                throw new Exception('Database connection not available');
            }
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'] ?? '';
                $_SESSION['role'] = $user['role'] ?? 'admin';
                
                // Get full_name from database
                $_SESSION['full_name'] = !empty($user['full_name']) ? $user['full_name'] : $user['username'];

                // First login check (optional column)
                $isFirstLogin = false;
                try {
                    $colStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'first_login'");
                    if ($colStmt && $colStmt->fetch(PDO::FETCH_ASSOC)) {
                        $isFirstLogin = isset($user['first_login']) && (int)$user['first_login'] === 1;
                        if ($isFirstLogin) {
                            $upd = $pdo->prepare("UPDATE users SET first_login = 0 WHERE id = ?");
                            $upd->execute([$user['id']]);
                        }
                    }
                } catch (Exception $e) {
                    // Ignore if column doesn't exist
                    $isFirstLogin = false;
                }
                $_SESSION['first_login'] = $isFirstLogin ? 1 : 0;
                
                // Log successful login
                require_once 'includes/audit_helper.php';
                logAudit($pdo, $user['id'], $user['username'], $user['role'], 'login', 'users', 'Successful login from ' . ($_SERVER['HTTP_USER_AGENT'] ? 'web browser' : 'unknown device'));
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    $redirect = 'admin_dashboard.php';
                } elseif ($user['role'] === 'staff') {
                    $redirect = 'staff-dashboard.php';
                } elseif ($user['role'] === 'dentist') {
                    $redirect = 'dentist_dashboard.php';
                } else {
                    $redirect = 'dashboard.php';
                }
                $_SESSION['login_redirect'] = $redirect;
                echo json_encode(['success' => true, 'message' => 'Logged in successfully', 'redirect' => $redirect]);
                exit();
            } else {
                // Log failed login attempt
                require_once 'includes/audit_helper.php';
                if ($user) {
                    // User exists but wrong password
                    logFailedLogin($pdo, $username, 'invalid_password');
                } else {
                    // User not found
                    logFailedLogin($pdo, $username, 'user_not_found');
                }
                
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
        try {
            require_once 'config/database.php';
            
            if (!isset($pdo)) {
                throw new Exception('Database connection not available');
            }
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'] ?? '';
                $_SESSION['role'] = $user['role'] ?? 'admin';
                
                // Get full_name from database
                $_SESSION['full_name'] = !empty($user['full_name']) ? $user['full_name'] : $user['username'];

                // First login check (optional column)
                $isFirstLogin = false;
                try {
                    $colStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'first_login'");
                    if ($colStmt && $colStmt->fetch(PDO::FETCH_ASSOC)) {
                        $isFirstLogin = isset($user['first_login']) && (int)$user['first_login'] === 1;
                        if ($isFirstLogin) {
                            $upd = $pdo->prepare("UPDATE users SET first_login = 0 WHERE id = ?");
                            $upd->execute([$user['id']]);
                        }
                    }
                } catch (Exception $e) {
                    $isFirstLogin = false;
                }
                $_SESSION['first_login'] = $isFirstLogin ? 1 : 0;
                
                // Log successful login
                require_once 'includes/audit_helper.php';
                logAudit($pdo, $user['id'], $user['username'], $user['role'], 'login', 'users', 'Successful login');
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header('Location: admin_dashboard.php');
                } elseif ($user['role'] === 'staff') {
                    header('Location: staff-dashboard.php');
                } elseif ($user['role'] === 'dentist') {
                    header('Location: dentist_dashboard.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit();
            } else {
                // Log failed login attempt
                require_once 'includes/audit_helper.php';
                if ($user) {
                    logFailedLogin($pdo, $username, 'invalid_password');
                } else {
                    logFailedLogin($pdo, $username, 'user_not_found');
                }
                
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
    <link rel="icon" type="image/png" href="assets/images/Logo.png">
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
            
            <div class="alert alert-error" id="errorMessage" style="display: none;"></div>
            
            <form id="loginForm" method="POST">
                <div class="input-group">
                    <input type="text" id="username" name="username" placeholder="Username" required autocomplete="username">
                </div>
                
                <div class="input-group">
                    <input type="password" id="password" name="password" placeholder="Password" required autocomplete="current-password">
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
                
                <div class="form-links">
                    <a href="forgot-password.php" class="link page-transition">Forgot password ?</a>
                </div>
                <div class="form-links">
                    <span class="separator">For clinic applicants, please contact the administrator.</span>
                </div>
                
                <button type="submit" class="login-btn" id="loginBtn">
                    <span id="loginBtnText">LOGIN</span>
                </button>
            </form>
        </div>
    </div>
    
    <div id="toast" class="toast">
        <div class="toast-wrapper">
            <div class="toast-left-border"></div>
            <div class="toast-icon-wrapper">
                <span class="toast-icon">✓</span>
            </div>
            <div class="toast-content">
                <div class="toast-title">Success</div>
                <div class="toast-message">Your changes are saved successfully</div>
            </div>
            <button class="toast-close" onclick="closeToast()">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            <div class="toast-loading-line"></div>
        </div>
    </div>
    
    <script src="assets/js/login.js"></script>
</body>
</html>

