<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    
    if (empty($username)) {
        $error = 'Please enter your username';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, email FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // In a real application, you would send a password reset email here
                $success = 'Password reset instructions have been sent to your email (if registered).';
            } else {
                $error = 'Username not found';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - RF Dental Clinic</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="container">
        <div class="background-overlay"></div>
        
        <div class="login-form-container">
            <div class="logo-container">
                <img src="assets/images/RF.logo.svg" alt="RF Logo" class="logo">
            </div>
            
            <h1 class="clinic-name">Reset Password</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="forgot-password.php">
                <div class="input-group">
                    <input type="text" name="username" placeholder="Username" required autofocus>
                </div>
                
                <div class="form-links">
                    <a href="login.php" class="link">Back to Login</a>
                </div>
                
                <button type="submit" class="login-btn">
                    <span>RESET PASSWORD</span>
                </button>
            </form>
        </div>
    </div>
</body>
</html>

