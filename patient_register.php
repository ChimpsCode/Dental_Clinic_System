<?php
session_start();
require_once 'config/database.php';

// Redirect if already logged in (patients shouldn't need to register if logged in)
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Determine where to go back to
$referrer = $_SERVER['HTTP_REFERER'] ?? 'home.php';
// Only allow internal pages
if (strpos($referrer, 'patient_register.php') !== false || 
    strpos($referrer, 'login.php') !== false ||
    strpos($referrer, 'forgot-password.php') !== false) {
    $referrer = 'home.php';
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($firstName) || empty($lastName) || empty($email) || empty($phone) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        try {
            // Check if PDO connection exists
            if (!isset($pdo)) {
                $error = 'Database connection failed. Please check your database configuration.';
            } else {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'An account with this email already exists';
                } else {
                    // Create username from email
                    $username = explode('@', $email)[0];
                    
                    // Check if username exists and append number if needed
                    $baseUsername = $username;
                    $counter = 1;
                    while (true) {
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                        $stmt->execute([$username]);
                        if (!$stmt->fetch()) {
                            break;
                        }
                        $username = $baseUsername . $counter;
                        $counter++;
                    }
                    
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert into users table
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, ?)");
                    $result = $stmt->execute([$username, $hashedPassword, $email, $firstName, $lastName, 'user']);
                    
                    if ($result) {
                        $userId = $pdo->lastInsertId();
                        
                        // Create patient record
                        $stmt = $pdo->prepare("INSERT INTO patients (first_name, last_name, full_name, email, phone, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                        $stmt->execute([$firstName, $lastName, "$firstName $lastName", $email, $phone]);
                        
                        $success = 'Account created successfully! You can now login with your email and password.';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - RF Dental Clinic</title>
    <link rel="icon" type="image/png" href="assets/images/Logo.png">
    <link rel="stylesheet" href="assets/css/login.css">
    <style>
        /* Additional styles for patient registration */
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-row .input-group {
            flex: 1;
        }
        .subtitle {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-form-container" style="max-width: 450px;">
            <button type="button" class="login-close-btn" id="closeBtn" aria-label="Close registration" onclick="window.location.href='<?php echo htmlspecialchars($referrer); ?>'">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>

            <div class="logo-container">
                <img src="assets/images/Logo.png" alt="RF Logo" class="logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <svg class="logo" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" style="display:none;">
                    <path d="M50 20 L30 40 L30 60 L50 80 L70 60 L70 40 Z" fill="#2563eb" stroke="#2563eb" stroke-width="2"/>
                    <text x="50" y="45" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white" text-anchor="middle">R</text>
                    <text x="50" y="70" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white" text-anchor="middle">F</text>
                </svg>
            </div>
            
            <h1 class="clinic-name">Create Account</h1>
            <p class="subtitle">Join RF Dental Clinic to manage your appointments and health records</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error" style="background-color: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" style="background-color: #dcfce7; color: #16a34a; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form id="registerForm" method="POST">
                <div class="form-row">
                    <div class="input-group">
                        <input type="text" id="first_name" name="first_name" placeholder="First Name" required autocomplete="given-name">
                    </div>
                    <div class="input-group">
                        <input type="text" id="last_name" name="last_name" placeholder="Last Name" required autocomplete="family-name">
                    </div>
                </div>
                
                <div class="input-group">
                    <input type="email" id="email" name="email" placeholder="Email Address" required autocomplete="email">
                </div>
                
                <div class="input-group">
                    <input type="tel" id="phone" name="phone" placeholder="Phone Number" required autocomplete="tel">
                </div>
                
                <div class="input-group">
                    <input type="password" id="password" name="password" placeholder="Password (min. 6 characters)" required autocomplete="new-password">
                </div>
                
                <div class="input-group">
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required autocomplete="new-password">
                </div>
                
                <div class="form-links" style="margin-bottom: 20px;">
                    <span class="separator">By registering, you agree to our Terms of Service and Privacy Policy.</span>
                </div>
                
                <button type="submit" class="login-btn" id="registerBtn">
                    <span id="registerBtnText">CREATE ACCOUNT</span>
                </button>
            </form>
            
            <div class="form-links" style="margin-top: 20px; justify-content: center;">
                <span>Already have an account? </span>
                <a href="<?php echo htmlspecialchars($referrer); ?>" class="link page-transition">Go Back</a>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const submitBtn = document.getElementById('registerBtn');
            
            if (form && submitBtn) {
                form.addEventListener('submit', function(e) {
                    // Basic validation
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    
                    if (password !== confirmPassword) {
                        e.preventDefault();
                        alert('Passwords do not match!');
                        return false;
                    }
                    
                    if (password.length < 6) {
                        e.preventDefault();
                        alert('Password must be at least 6 characters long!');
                        return false;
                    }
                    
                    // Show loading state
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span>Creating Account...</span>';
                });
            }
        });
    </script>
</body>
</html>
