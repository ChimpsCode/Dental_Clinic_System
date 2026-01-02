<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        try {
            require_once 'config/database.php';
            
            // Check if database connection exists
            if (!isset($pdo)) {
                throw new Exception('Database connection not available');
            }
            
            // Optimized single query - fetch all needed data at once
            $stmt = $pdo->prepare("SELECT id, username, password, full_name FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Set session variables immediately
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                if (!empty($user['full_name'])) {
                    $_SESSION['full_name'] = $user['full_name'];
                }
                
                // Redirect immediately
                header('Location: dashboard.php');
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
        <div class="background-overlay"></div>
        
        <div class="login-form-container">
            <div class="logo-container">
                <img src="assets/images/RF.logo.svg" alt="RF Logo" class="logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
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
            
            <form id="loginForm" method="POST" action="login.php">
                <div class="input-group">
                    <input type="text" id="username" name="username" placeholder="Username" required autocomplete="username">
                </div>
                
                <div class="input-group">
                    <input type="password" id="password" name="password" placeholder="Password" required autocomplete="current-password">
                    <span class="toggle-password" id="togglePassword">👁️</span>
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
    
    <script src="assets/js/login.js"></script>
</body>
</html>

