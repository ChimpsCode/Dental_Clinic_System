<?php
session_start();

require_once '../../config/database.php';

// Load clinic settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settingsRows = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$clinicSettings = [];
foreach ($settingsRows as $key => $value) {
    $clinicSettings[$key] = $value;
}
$clinicName = $clinicSettings['clinic_name'] ?? 'RF Dental Clinic';
$welcomeLogo = $clinicSettings['welcome_logo'] ?? '';
$loginLogo = $welcomeLogo ? '../../' . $welcomeLogo : '../../assets/images/Logo.png';

require_once '../../lib/security/captcha_lib.php';

// Only generate captcha if not set or expired - but don't auto-regenerate on page load to prevent mismatch
if (isCaptchaExpired()) {
    generateCaptcha();
}

$error = '';
$success = '';

function isStrongPassword(string $pwd): bool {
    return strlen($pwd) >= 8
        && preg_match('/[A-Z]/', $pwd)
        && preg_match('/[a-z]/', $pwd)
        && preg_match('/[0-9]/', $pwd)
        && preg_match('/[^A-Za-z0-9]/', $pwd);
}

function tableHasColumn(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare(
        "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1"
    );
    $stmt->execute([$table, $column]);
    $cache[$key] = (bool)$stmt->fetchColumn();
    return $cache[$key];
}

function updateUserSessionColumns(PDO $pdo, int $userId, array $columnAssignments): void {
    $updates = [];

    foreach ($columnAssignments as $column => $expression) {
        if (tableHasColumn($pdo, 'users', $column)) {
            $updates[] = $column . ' = ' . $expression;
        }
    }

    if (empty($updates)) {
        return;
    }

    $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
}

function verifyAndUpgradePassword($pdo, array $user, string $inputPassword): bool {
    $storedPassword = (string)($user['password'] ?? '');
    if ($storedPassword === '') {
        return false;
    }

    if (password_verify($inputPassword, $storedPassword)) {
        return true;
    }

    // Legacy compatibility: allow plaintext passwords from old dumps once,
    // then immediately upgrade to a secure hash.
    if (hash_equals($storedPassword, $inputPassword)) {
        try {
            $newHash = password_hash($inputPassword, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? LIMIT 1");
            $upd->execute([$newHash, (int)$user['id']]);
        } catch (Exception $e) {
            // Ignore upgrade failure; login can still proceed.
        }
        return true;
    }

    return false;
}

function ensurePatientRecordForUser(PDO $pdo, array $user): ?int {
    if (tableHasColumn($pdo, 'patients', 'user_id')) {
        $patientStmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ? LIMIT 1");
        $patientStmt->execute([(int)$user['id']]);
        $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
        if ($patient) {
            return (int)$patient['id'];
        }
    }

    $email = trim((string)($user['email'] ?? ''));
    if ($email !== '') {
        $patientStmt = $pdo->prepare("SELECT id FROM patients WHERE email = ? AND is_archived = 0 LIMIT 1");
        $patientStmt->execute([$email]);
        $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
        if ($patient) {
            return (int)$patient['id'];
        }
    }

    $patientColumns = [];
    $patientValues = [];

    if (tableHasColumn($pdo, 'patients', 'first_name')) {
        $patientColumns[] = 'first_name';
        $patientValues[] = trim((string)($user['first_name'] ?? 'Patient')) ?: 'Patient';
    }
    if (tableHasColumn($pdo, 'patients', 'middle_name')) {
        $middleName = trim((string)($user['middle_name'] ?? ''));
        $patientColumns[] = 'middle_name';
        $patientValues[] = $middleName !== '' ? $middleName : null;
    }
    if (tableHasColumn($pdo, 'patients', 'last_name')) {
        $lastName = trim((string)($user['last_name'] ?? ''));
        $patientColumns[] = 'last_name';
        $patientValues[] = $lastName !== '' ? $lastName : 'Patient';
    }
    if (tableHasColumn($pdo, 'patients', 'email')) {
        $patientColumns[] = 'email';
        $patientValues[] = $email !== '' ? $email : null;
    }
    if (tableHasColumn($pdo, 'patients', 'phone')) {
        $phone = trim((string)($user['phone'] ?? ''));
        $patientColumns[] = 'phone';
        $patientValues[] = $phone !== '' ? $phone : null;
    }
    if (tableHasColumn($pdo, 'patients', 'gender')) {
        $gender = trim((string)($user['gender'] ?? ''));
        $normalizedGender = in_array($gender, ['Male', 'Female'], true) ? $gender : null;
        $patientColumns[] = 'gender';
        $patientValues[] = $normalizedGender;
    }
    if (tableHasColumn($pdo, 'patients', 'date_of_birth')) {
        $dob = trim((string)($user['date_of_birth'] ?? ''));
        $patientColumns[] = 'date_of_birth';
        $patientValues[] = $dob !== '' ? $dob : null;
    }
    if (tableHasColumn($pdo, 'patients', 'registration_source_id')) {
        $patientColumns[] = 'registration_source_id';
        $patientValues[] = null;
    } elseif (tableHasColumn($pdo, 'patients', 'registration_source')) {
        $patientColumns[] = 'registration_source';
        $patientValues[] = 'self-register';
    }
    if (tableHasColumn($pdo, 'patients', 'user_id')) {
        $patientColumns[] = 'user_id';
        $patientValues[] = (int)$user['id'];
    }

    if (empty($patientColumns)) {
        return null;
    }

    $placeholders = implode(', ', array_fill(0, count($patientColumns), '?'));
    $sql = "INSERT INTO patients (" . implode(', ', $patientColumns) . ") VALUES (" . $placeholders . ")";
    $insertStmt = $pdo->prepare($sql);
    $insertStmt->execute($patientValues);

    return (int)$pdo->lastInsertId();
}

// Determine where to go back to
$referrer = $_SERVER['HTTP_REFERER'] ?? 'home.php';
// Only allow internal pages
if (strpos($referrer, 'login.php') !== false || 
    strpos($referrer, 'forgot-password.php') !== false ||
    strpos($referrer, 'master/user-patient/patient_register.php') !== false) {
    $referrer = 'home.php';
}

// Handle AJAX forgot-password actions in this same page
if (
    isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) && in_array($_POST['action'], ['send_code', 'verify_code', 'change_password'], true)
) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        require_once '../../config/database.php';
        if (!isset($pdo)) {
            throw new Exception('Database connection not available');
        }

        $action = $_POST['action'];

        if ($action === 'send_code') {
            $email = trim($_POST['email'] ?? '');
            if ($email === '') {
                echo json_encode(['success' => false, 'message' => 'Please enter your email address.']);
                exit();
            }

            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ? AND is_archived = 0 LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'Email not found.']);
                exit();
            }

            $resetCode = (string)random_int(100000, 999999);
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_user_id'] = (int)$user['id'];
            $_SESSION['reset_code'] = $resetCode;
            $_SESSION['reset_code_sent_at'] = time();
            unset($_SESSION['reset_code_verified']);

            require_once '../../config/mailer_config.php';
            $subject = 'RF Dental Clinic - Password Reset Code';
            $plain = "Hi " . ($user['username'] ?? 'User') . ", your verification code is: " . $resetCode;
            $html = '<p>Hi ' . htmlspecialchars($user['username'] ?? 'User') . ',</p>'
                . '<p>Your verification code is:</p>'
                . '<p style="font-size:22px;font-weight:bold;letter-spacing:4px;">' . htmlspecialchars($resetCode) . '</p>'
                . '<p>Enter this code to continue resetting your password.</p>';

            $phpMailerBase = __DIR__ . '/../../PHPMailer/src';
            if (!file_exists($phpMailerBase . '/PHPMailer.php')) {
                echo json_encode(['success' => false, 'message' => 'Email service is not configured.']);
                exit();
            }

            require_once $phpMailerBase . '/Exception.php';
            require_once $phpMailerBase . '/PHPMailer.php';
            require_once $phpMailerBase . '/SMTP.php';

            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = gethostbyname(SMTP_HOST);
            $mailer->SMTPAuth = true;
            $mailer->Username = SMTP_USER;
            $mailer->Password = SMTP_PASS;
            $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->Port = SMTP_PORT;
            $mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
            $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mailer->addAddress($email, $user['username'] ?? 'User');
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $html;
            $mailer->AltBody = $plain;
            $mailer->send();

            echo json_encode(['success' => true, 'message' => 'Code sent. Check your email.', 'step' => 2]);
            exit();
        }

        if ($action === 'verify_code') {
            $code = trim($_POST['code'] ?? '');
            $sessionCode = (string)($_SESSION['reset_code'] ?? '');
            $sentAt = (int)($_SESSION['reset_code_sent_at'] ?? 0);
            if ($sessionCode === '' || $sentAt === 0 || (time() - $sentAt) > 180) {
                echo json_encode(['success' => false, 'message' => 'Code expired. Please request a new one.']);
                exit();
            }
            if ($code === '' || $code !== $sessionCode) {
                echo json_encode(['success' => false, 'message' => 'Incorrect verification code.']);
                exit();
            }

            $_SESSION['reset_code_verified'] = true;
            echo json_encode(['success' => true, 'message' => 'Code verified.', 'step' => 3]);
            exit();
        }

        if ($action === 'change_password') {
            if (empty($_SESSION['reset_code_verified']) || empty($_SESSION['reset_user_id'])) {
                echo json_encode(['success' => false, 'message' => 'Verification required before changing password.']);
                exit();
            }

            $newPassword = (string)($_POST['password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');
            if (!isStrongPassword($newPassword)) {
                echo json_encode(['success' => false, 'message' => 'Password must be at least 8 chars with upper, lower, number, and symbol.']);
                exit();
            }
            if ($newPassword !== $confirmPassword) {
                echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
                exit();
            }

            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? LIMIT 1");
            $stmt->execute([$hash, (int)$_SESSION['reset_user_id']]);

            unset(
                $_SESSION['reset_email'],
                $_SESSION['reset_user_id'],
                $_SESSION['reset_code'],
                $_SESSION['reset_code_sent_at'],
                $_SESSION['reset_code_verified']
            );

            echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
            exit();
        }
    } catch (Exception $e) {
        error_log('Forgot password inline flow error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Request failed. Please try again.']);
        exit();
    }
}

// Handle 2FA clear (when user goes back to login)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_2fa') {
    unset($_SESSION['2fa_user_id'], $_SESSION['2fa_code'], $_SESSION['2fa_code_expiry'], $_SESSION['2fa_user_data']);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true]);
    exit();
}

// Handle 2FA verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_2fa') {
    header('Content-Type: application/json; charset=utf-8');
    $code = trim($_POST['code'] ?? '');
    $userId = (int)($_SESSION['2fa_user_id'] ?? 0);
    $storedHash = $_SESSION['2fa_code'] ?? '';
    $expiry = (int)($_SESSION['2fa_code_expiry'] ?? 0);

    if ($userId === 0 || empty($storedHash) || time() > $expiry) {
        echo json_encode(['success' => false, 'message' => 'Code expired. Please login again.']);
        exit();
    }

    if (empty($code) || !password_verify($code, $storedHash)) {
        echo json_encode(['success' => false, 'message' => 'Invalid verification code.']);
        exit();
    }

    $user = $_SESSION['2fa_user_data'] ?? null;
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
        exit();
    }

    // Clear 2FA session data
    unset($_SESSION['2fa_user_id'], $_SESSION['2fa_code'], $_SESSION['2fa_code_expiry'], $_SESSION['2fa_user_data']);

    // Complete login
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'] ?? '';
    $_SESSION['profile_picture'] = $user['profile_picture'] ?? null;

    if (isset($user['role'])) {
        $role = strtolower($user['role']);
    } else {
        $role = 'user';
    }
    if ($role === 'patient') $role = 'user';
    if ($role === 'administrator') $role = 'admin';
    $_SESSION['role'] = $role;
    $_SESSION['user_role'] = $role;

    if ($role === 'user') {
        $patientId = ensurePatientRecordForUser($pdo, $user);
        if ($patientId) {
            $_SESSION['patient_id'] = $patientId;
        }
    }

    $firstName = trim($user['first_name'] ?? '');
    $lastName = trim($user['last_name'] ?? '');
    $firstNameOnly = explode(' ', $firstName)[0] ?? '';
    $_SESSION['user_name'] = $firstName;

    if ($role === 'dentist') {
        $_SESSION['display_name'] = 'Dr. ' . trim($firstNameOnly ?: 'Dentist');
    } else {
        $roleLabel = ['admin' => 'Admin', 'staff' => 'Staff', 'user' => 'User'];
        $_SESSION['display_name'] = trim($firstNameOnly ?: ($roleLabel[$role] ?? $user['username']));
    }

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
    } catch (Exception $e) {}
    $_SESSION['first_login'] = $isFirstLogin ? 1 : 0;

    try {
        $priorLoginStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE user_id = ? AND action_type = 'login'");
        $priorLoginStmt->execute([$user['id']]);
        $_SESSION['is_new_patient'] = ((int)$priorLoginStmt->fetchColumn() === 0);
    } catch (Exception $e) {
        $_SESSION['is_new_patient'] = false;
    }

    require_once '../../includes/audit_helper.php';
    $recentCheck = $pdo->prepare("SELECT id FROM audit_logs WHERE username = ? AND action_type = 'login' AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND) ORDER BY id DESC LIMIT 1");
    $recentCheck->execute([$user['username']]);
    if (!$recentCheck->fetch()) {
        logAudit($pdo, $user['id'], $user['username'], $role, 'login', 'users', 'Successful login (2FA) from ' . ($_SERVER['HTTP_USER_AGENT'] ? 'web browser' : 'unknown device'));
    }

    try {
        updateUserSessionColumns($pdo, (int)$user['id'], [
            'last_active' => 'UTC_TIMESTAMP()',
            'last_logout' => 'NULL',
            'is_logged_in' => '1',
        ]);
    } catch (Exception $e) {}

    if ($role === 'admin') $redirect = '../admin/admin_dashboard.php';
    elseif ($role === 'staff') $redirect = '../staff/staff-dashboard.php';
    elseif ($role === 'dentist') $redirect = '../dentist/dentist_dashboard.php';
    elseif ($role === 'user') $redirect = '../user-patient/patient-dashboard.php';
    else $redirect = '../../dashboard.php';

    echo json_encode(['success' => true, 'message' => 'Logged in successfully', 'redirect' => $redirect, 'role' => $role]);
    exit();
}

// Handle AJAX login request
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST' && 
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    // Set JSON header
    header('Content-Type: application/json; charset=utf-8');
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $captcha = $_POST['captcha'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
        exit();
    }
    
    // Skip captcha check if skip_captcha flag is set (used by welcome-back flow where patient identity is already verified)
    $skipCaptcha = isset($_POST['skip_captcha']) && $_POST['skip_captcha'] === '1';
    
    if (!$skipCaptcha) {
        if (empty($captcha)) {
            echo json_encode(['success' => false, 'message' => 'Please enter the captcha code']);
            exit();
        }
        
        if (!validateCaptcha($captcha)) {
            refreshCaptcha();
            echo json_encode(['success' => false, 'message' => 'Invalid or expired captcha code']);
            exit();
        }
    }
    
    try {
        require_once '../../config/database.php';
        
        if (!isset($pdo)) {
            throw new Exception('Database connection not available');
        }
        
        $stmt = $pdo->prepare("SELECT u.* FROM users u WHERE (u.username = ? OR u.email = ?) AND u.is_archived = 0 LIMIT 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && verifyAndUpgradePassword($pdo, $user, $password)) {
            // Clear existing session and set new session data
            session_regenerate_id(false);

            // Check if 2FA is enabled for this user (skip if no email)
            $twoFactorEnabled = (int)($user['two_factor_enabled'] ?? 0);
            $userEmail = trim($user['email'] ?? '');
            if ($twoFactorEnabled && !empty($userEmail)) {
                // Clear any stale 2FA data from previous attempts
                unset($_SESSION['2fa_user_id'], $_SESSION['2fa_code'], $_SESSION['2fa_code_expiry'], $_SESSION['2fa_user_data']);

                $twoFactorCode = (string)random_int(100000, 999999);
                $_SESSION['2fa_user_id'] = (int)$user['id'];
                $_SESSION['2fa_code'] = password_hash($twoFactorCode, PASSWORD_DEFAULT);
                $_SESSION['2fa_code_expiry'] = time() + 300;
                $_SESSION['2fa_user_data'] = $user;

                try {
                    require_once '../../config/mailer_config.php';
                    $phpMailerBase = __DIR__ . '/../../PHPMailer/src';
                    if (file_exists($phpMailerBase . '/PHPMailer.php')) {
                        require_once $phpMailerBase . '/Exception.php';
                        require_once $phpMailerBase . '/PHPMailer.php';
                        require_once $phpMailerBase . '/SMTP.php';

                        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
                        $mailer->isSMTP();
                        $mailer->Host = gethostbyname(SMTP_HOST);
                        $mailer->SMTPAuth = true;
                        $mailer->Username = SMTP_USER;
                        $mailer->Password = SMTP_PASS;
                        $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mailer->Port = SMTP_PORT;
                        $mailer->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
                        $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME);
                        $mailer->addAddress($user['email'], $user['username']);
                        $mailer->isHTML(true);
                        $mailer->Subject = 'RF Dental Clinic - Two-Factor Authentication Code';
                        $mailer->Body = '<p>Hi ' . htmlspecialchars($user['username']) . ',</p><p>Your 2FA verification code is:</p><p style="font-size:22px;font-weight:bold;letter-spacing:4px;">' . htmlspecialchars($twoFactorCode) . '</p><p>This code expires in 5 minutes.</p>';
                        $mailer->AltBody = 'Your 2FA code is: ' . $twoFactorCode;
                        $mailer->send();
                    }
                } catch (Exception $e) {}

                echo json_encode(['success' => true, 'two_factor_required' => true, 'message' => 'Verification code sent to your email']);
                exit();
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'] ?? '';
            $_SESSION['profile_picture'] = $user['profile_picture'] ?? null;

            if (isset($user['role'])) {
                $role = strtolower($user['role']);
            } else {
                $role = 'user';
            }
            if ($role === 'patient') { $role = 'user'; }
            if ($role === 'administrator') { $role = 'admin'; }
            $_SESSION['role'] = $role;
            $_SESSION['user_role'] = $role;
            
            // Get patient_id if this is a user role
            if ($role === 'user') {
                $patientId = ensurePatientRecordForUser($pdo, $user);
                if ($patientId) {
                    $_SESSION['patient_id'] = $patientId;
                }
            }
            
            // Compute display name from first_name + last_name
            $firstName = trim($user['first_name'] ?? '');
            $lastName = trim($user['last_name'] ?? '');
            $firstNameOnly = explode(' ', $firstName)[0] ?? '';
            $_SESSION['user_name'] = $firstName;
            
            if ($role === 'dentist') {
                $_SESSION['display_name'] = 'Dr. ' . trim($firstNameOnly ?: 'Dentist');
            } else {
                $roleLabel = ['admin' => 'Admin', 'staff' => 'Staff', 'user' => 'User'];
                $_SESSION['display_name'] = trim($firstNameOnly ?: ($roleLabel[$role] ?? $user['username']));
            }
            
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
            
            // Detect first-time login via audit_logs
            $_SESSION['is_new_patient'] = true;
            try {
                $priorLoginStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE user_id = ? AND action_type = 'login'");
                $priorLoginStmt->execute([$user['id']]);
                $_SESSION['is_new_patient'] = ((int)$priorLoginStmt->fetchColumn() === 0);
            } catch (Exception $e) {
                $_SESSION['is_new_patient'] = false;
            }
            
            // Log successful login (only if not logged in recent session to prevent duplicates)
            require_once '../../includes/audit_helper.php';
            
            // Check if user has a recent login (within 5 seconds) to prevent double logging
            $recentCheck = $pdo->prepare("SELECT id FROM audit_logs WHERE username = ? AND action_type = 'login' AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND) ORDER BY id DESC LIMIT 1");
            $recentCheck->execute([$user['username']]);
            if (!$recentCheck->fetch()) {
                logAudit($pdo, $user['id'], $user['username'], $role, 'login', 'users', 'Successful login from ' . ($_SERVER['HTTP_USER_AGENT'] ? 'web browser' : 'unknown device'));
            }
            
            // Update optional session-tracking columns only when they exist in this schema.
            try {
                updateUserSessionColumns($pdo, (int)$user['id'], [
                    'last_active' => 'UTC_TIMESTAMP()',
                    'last_logout' => 'NULL',
                    'is_logged_in' => '1',
                ]);
            } catch (Exception $e) {
                // Do not block login when optional tracking columns are missing.
            }
            
            // Redirect based on role
            if ($role === 'admin') {
                $redirect = '../admin/admin_dashboard.php';
            } elseif ($role === 'staff') {
                $redirect = '../staff/staff-dashboard.php';
            } elseif ($role === 'dentist') {
                $redirect = '../dentist/dentist_dashboard.php';
            } elseif ($role === 'user') {
                $redirect = '../user-patient/patient-dashboard.php';
            } else {
                $redirect = '../../dashboard.php';
            }
            $_SESSION['login_redirect'] = $redirect;
            echo json_encode(['success' => true, 'message' => 'Logged in successfully', 'redirect' => $redirect, 'role' => $role]);
            exit();
        } else {
            // Log failed login attempt
            require_once '../../includes/audit_helper.php';
            if ($user) {
                logFailedLogin($pdo, $username, 'invalid_password');
            } else {
                logFailedLogin($pdo, $username, 'user_not_found');
            }
            
            echo json_encode(['success' => false, 'message' => 'Wrong username or password. Please try again.']);
            refreshCaptcha();
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
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $captcha = $_POST['captcha'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } elseif (empty($captcha)) {
        $error = 'Please enter the captcha code';
    } elseif (!validateCaptcha($captcha)) {
        refreshCaptcha();
        $error = 'Invalid or expired captcha code';
    } else {
        try {
            require_once '../../config/database.php';
            
            if (!isset($pdo)) {
                throw new Exception('Database connection not available');
            }
            
            $stmt = $pdo->prepare("SELECT u.* FROM users u WHERE (u.username = ? OR u.email = ?) AND u.is_archived = 0 LIMIT 1");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && verifyAndUpgradePassword($pdo, $user, $password)) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(false);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'] ?? '';
                $_SESSION['profile_picture'] = $user['profile_picture'] ?? null;

                if (isset($user['role'])) {
                    $role = strtolower($user['role']);
                } else {
                    $role = 'user';
                }
                if ($role === 'patient') { $role = 'user'; }
                if ($role === 'administrator') { $role = 'admin'; }
                $_SESSION['role'] = $role;
                $_SESSION['user_role'] = $role;
                
                // Get patient_id if this is a user role
                if ($role === 'user') {
                    $patientId = ensurePatientRecordForUser($pdo, $user);
                    if ($patientId) {
                        $_SESSION['patient_id'] = $patientId;
                    }
                }
                
                // Compute display name from first_name + last_name
                $firstName = trim($user['first_name'] ?? '');
                $lastName = trim($user['last_name'] ?? '');
                $firstNameOnly = explode(' ', $firstName)[0] ?? '';
                $_SESSION['user_name'] = $firstName;
                
if ($role === 'dentist') {
                    $_SESSION['display_name'] = 'Dr. ' . trim($firstNameOnly ?: 'Dentist');
                } else {
                    $roleLabel = ['admin' => 'Admin', 'staff' => 'Staff', 'user' => 'User'];
                    $_SESSION['display_name'] = trim($firstNameOnly ?: ($roleLabel[$role] ?? $user['username']));
                }
                
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
                
                // Detect first-time login via audit_logs
                $_SESSION['is_new_patient'] = true;
                try {
                    $priorLoginStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE user_id = ? AND action_type = 'login'");
                    $priorLoginStmt->execute([$user['id']]);
                    $_SESSION['is_new_patient'] = ((int)$priorLoginStmt->fetchColumn() === 0);
                } catch (Exception $e) {
                    $_SESSION['is_new_patient'] = false;
                }
                
                // Log successful login
                require_once '../../includes/audit_helper.php';
                $recentCheck = $pdo->prepare("SELECT id FROM audit_logs WHERE username = ? AND action_type = 'login' AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND) ORDER BY id DESC LIMIT 1");
                $recentCheck->execute([$user['username']]);
                if (!$recentCheck->fetch()) {
                    logAudit($pdo, $user['id'], $user['username'], $role, 'login', 'users', 'Successful login from ' . ($_SERVER['HTTP_USER_AGENT'] ? 'web browser' : 'unknown device'));
                }
                
            // Redirect based on role
                if ($role === 'admin') {
                    header('Location: ../admin/admin_dashboard.php');
                } elseif ($role === 'staff') {
                    header('Location: ../staff/staff-dashboard.php');
                } elseif ($role === 'dentist') {
                    header('Location: ../dentist/dentist_dashboard.php');
                } elseif ($role === 'user') {
                    header('Location: ../user-patient/patient-dashboard.php');
                } else {
                    header('Location: ../../dashboard.php');
                }
                exit();
            } else {
                // Skip duplicate failed login logging - already logged at line 410
                $error = 'Wrong username or password. Please try again.';
            }
        } catch (PDOException $e) {
            $error = 'Database connection error. Please check your database settings.';
        } catch (Exception $e) {
            $error = 'Login failed. Please try again.';
        }
    }
}

$showDeletedMsg = isset($_GET['deleted']) && $_GET['deleted'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RF Dental Clinic - Login</title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($loginLogo); ?>">
    <link rel="stylesheet" href="../../assets/css/login-new.css">
    <style>
        #registrationContainer {
            position: relative;
            max-height: calc(100vh - 24px);
            overflow: hidden;
            padding-top: 12px;
            padding-bottom: 10px;
        }
        #registrationContainer .logo {
            width: 72px;
            height: 72px;
        }
        #registrationContainer .logo-container {
            margin-bottom: 6px;
        }
        #registrationContainer .clinic-name {
            font-size: 24px;
            line-height: 1.1;
            margin-bottom: 4px;
        }
        #registrationContainer .login-subtitle {
            margin-bottom: 8px;
        }
        #registrationContainer .input-group {
            margin-bottom: 10px;
        }
        #registrationContainer .input-group input {
            padding: 10px 14px;
        }
        #registrationContainer .form-links {
            margin: 10px 0 8px 0 !important;
        }
        #registrationContainer .login-btn {
            padding: 12px 20px;
        }
        .login-close-btn {
            position: absolute;
            top: 16px;
            right: 16px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 6px;
            color: #6b7280;
            transition: color 0.2s ease;
        }
        .login-close-btn:hover { color: #111827; }
        #registrationContainer .login-btn {
            margin-top: 8px;
        }
        .forgot-progress {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin: 8px 0 16px;
        }
        .forgot-progress .node {
            width: 28px;
            height: 28px;
            border-radius: 999px;
            border: 2px solid #cbd5e1;
            color: #64748b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            background: #ffffff;
        }
        .forgot-progress .node.active {
            border-color: #2563eb;
            background: #2563eb;
            color: #ffffff;
        }
        .forgot-progress .node.done {
            border-color: #22c55e;
            background: #22c55e;
            color: #ffffff;
        }
        .forgot-progress .line {
            width: 32px;
            height: 2px;
            background: #cbd5e1;
        }
        .forgot-progress .line.active {
            background: #2563eb;
        }
    </style>
</head>
<body>
    <!-- Back Button -->
    <a href="<?php echo htmlspecialchars($referrer); ?>" class="back-button" aria-label="Go back">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        <span>Back</span>
    </a>

    <div class="login-container">
        <!-- Left Side - Carousel -->
        <div class="carousel-section">
            <div class="carousel-wrapper">
                <div class="carousel">
                    <div class="carousel-slide active">
                        <img src="../../assets/images/login-image1.jpg" alt="Dental Care" class="carousel-image">
                    </div>
                    <div class="carousel-slide">
                        <img src="../../assets/images/login-image2.jpg" alt="Dental Care" class="carousel-image">
                    </div>
                    <div class="carousel-slide">
                        <img src="../../assets/images/login-image3.jpg" alt="Dental Care" class="carousel-image">
                    </div>
                </div>

                <div class="carousel-content">
                    <h2 class="carousel-heading">Your Best Dental Care in Town,<br>Starts Here</h2>
                </div>

                <div class="carousel-dots">
                    <button class="dot active" data-slide="0" aria-label="Slide 1"></button>
                    <button class="dot" data-slide="1" aria-label="Slide 2"></button>
                    <button class="dot" data-slide="2" aria-label="Slide 3"></button>
                </div>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="form-section">
            <div class="login-form-container" id="loginContainer">
                <div class="logo-container">
                    <img src="<?php echo htmlspecialchars($loginLogo); ?>" alt="RF Logo" class="logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <svg class="logo" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" style="display:none;">
                        <path d="M50 20 L30 40 L30 60 L50 80 L70 60 L70 40 Z" fill="#2563eb" stroke="#2563eb" stroke-width="2"/>
                        <text x="50" y="45" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white" text-anchor="middle">R</text>
                        <text x="50" y="70" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white" text-anchor="middle">F</text>
                    </svg>
                </div>
                
                <h1 class="clinic-name"><?php echo htmlspecialchars($clinicName); ?></h1>
                <p class="login-subtitle">Please login to your account</p>
                
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

                    <!-- Captcha Section -->
                    <div class="captcha-section">
                        <div class="captcha-label">
                            <label>Enter the number shown below</label>
                            <span class="captcha-timer">Expires in <span class="timer" id="captchaTimer">2:00</span></span>
                        </div>
                        <div class="captcha-wrapper">
                            <div class="captcha-display">
                                <img src="../../lib/security/captcha_image.php" alt="Captcha" id="captchaImage" class="captcha-img" onerror="this.style.display='none'; document.getElementById('captchaFallback').style.display='block';">
                                <div class="captcha-code" id="captchaFallback" style="display:none;"><?php echo $_SESSION['captcha_code']; ?></div>
                                <button type="button" class="captcha-refresh" id="refreshCaptcha" aria-label="Refresh captcha">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="23 4 23 10 17 10"></polyline>
                                        <polyline points="1 20 1 14 7 14"></polyline>
                                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                                    </svg>
                                </button>
                            </div>
                            <input type="text" id="captchaInput" name="captcha" class="captcha-input" placeholder="Enter code" maxlength="6" inputmode="numeric" required>
                        </div>
                    </div>
                    
                    <div class="form-links">
                        <a class="link" href="#forgot" id="forgotPasswordLink">Forgot password?</a>
                        <span class="separator">|</span>
                        <a class="link" href="#register" id="createAccountLink">Create an account</a>
                    </div>

                    <div class="remember-me-group" style="margin-bottom: 8px;">
                        <input type="checkbox" id="rememberMe" name="remember_me" value="1">
                        <label for="rememberMe">Remember me</label>
                    </div>
                    
                    <button type="submit" class="login-btn" id="loginBtn">Login</button>
                </form>

                <p class="applicant-note">For clinic applicants, please contact the administrator.</p>
                
                <?php if (!empty($error)): ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var errorDiv = document.getElementById('errorMessage');
                            errorDiv.textContent = '<?php echo addslashes($error); ?>';
                            errorDiv.style.display = 'block';
                        });
                    </script>
                <?php endif; ?>
            </div>

            <!-- Two-Factor Authentication (inline) -->
            <div class="login-form-container" id="twoFactorContainer" style="display: none;">
                <div class="logo-container">
                    <img src="<?php echo htmlspecialchars($loginLogo); ?>" alt="RF Logo" class="logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <svg class="logo" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" style="display:none;">
                        <path d="M50 20 L30 40 L30 60 L50 80 L70 60 L70 40 Z" fill="#2563eb" stroke="#2563eb" stroke-width="2"/>
                        <text x="50" y="45" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white" text-anchor="middle">R</text>
                        <text x="50" y="70" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white" text-anchor="middle">F</text>
                    </svg>
                </div>

                <h1 class="clinic-name">Two-Factor Authentication</h1>
                <p class="login-subtitle" style="text-align:center; margin-bottom: 16px;">
                    Enter the 6-digit code sent to your email.
                </p>

                <div class="alert alert-error" id="twoFactorError" style="display: none;"></div>

                <form id="twoFactorForm" onsubmit="return false;">
                    <div class="input-group">
                        <input type="text" id="twoFactorCode" placeholder="Enter verification code" maxlength="6" inputmode="numeric" style="text-align: center; letter-spacing: 6px; font-size: 18px;">
                    </div>
                    <button type="button" class="login-btn" id="twoFactorVerifyBtn">
                        <span id="twoFactorVerifyBtnText">Verify Code</span>
                    </button>
                </form>

                <div class="form-links" style="margin-top: 16px; justify-content: center;">
                    <span>Didn't receive the code? </span>
                    <a href="#login" class="link" id="backToLoginFrom2faLink">Back to login</a>
                </div>
            </div>

            <!-- Forgot Password (inline) -->
            <div class="login-form-container" id="forgotContainer" style="display: none;">
                <div class="logo-container">
                    <img src="<?php echo htmlspecialchars($loginLogo); ?>" alt="RF Logo" class="logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <svg class="logo" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" style="display:none;">
                        <path d="M50 20 L30 40 L30 60 L50 80 L70 60 L70 40 Z" fill="#2563eb" stroke="#2563eb" stroke-width="2"/>
                        <text x="50" y="45" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white" text-anchor="middle">R</text>
                        <text x="50" y="70" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white" text-anchor="middle">F</text>
                    </svg>
                </div>

                <h1 class="clinic-name">Forgot Password</h1>
                <div class="forgot-progress" id="forgotProgress">
                    <span class="node active" id="fpNode1">1</span>
                    <span class="line" id="fpLine1"></span>
                    <span class="node" id="fpNode2">2</span>
                    <span class="line" id="fpLine2"></span>
                    <span class="node" id="fpNode3">3</span>
                </div>
                <div class="alert alert-error" id="forgotError" style="display: none;"></div>
                <div class="alert alert-success" id="forgotSuccess" style="display: none;"></div>

                <form id="forgotInlineForm" onsubmit="return false;">
                    <div id="fpStep1" class="step-pane">
                        <p class="login-subtitle" style="text-align:center; margin-bottom: 16px;">
                            Enter your email and we will send a 6-digit code.
                        </p>
                        <div class="input-group">
                            <input type="email" id="fp_email" placeholder="Enter your email" required autocomplete="email">
                        </div>
                        <button type="button" class="login-btn" id="fpSendBtn">
                            <span id="fpSendBtnText">Send Code</span>
                        </button>
                    </div>

                    <div id="fpStep2" class="step-pane" style="display:none;">
                        <p class="login-subtitle" style="text-align:center; margin-bottom: 16px;">
                            Enter the 6-digit code sent to your email.
                        </p>
                        <div class="input-group">
                            <input type="text" id="fp_code" placeholder="Enter verification code" maxlength="6" inputmode="numeric" style="text-align: center; letter-spacing: 6px; font-size: 18px;">
                        </div>
                        <button type="button" class="login-btn" id="fpVerifyBtn">
                            <span id="fpVerifyBtnText">Verify Code</span>
                        </button>
                    </div>

                    <div id="fpStep3" class="step-pane" style="display:none;">
                        <p class="login-subtitle" style="text-align:center; margin-bottom: 16px;">
                            Enter your new password.
                        </p>
                        <div class="input-group">
                            <input type="password" id="fp_new_password" placeholder="New password" required autocomplete="new-password">
                        </div>
                        <div class="input-group">
                            <input type="password" id="fp_confirm_password" placeholder="Confirm new password" required autocomplete="new-password">
                        </div>
                        <button type="button" class="login-btn" id="fpChangeBtn">
                            <span id="fpChangeBtnText">Change Password</span>
                        </button>
                    </div>
                </form>

                <div class="form-links" style="margin-top: 16px; justify-content: center;">
                    <span>Remembered your password? </span>
                    <a href="#login" class="link" id="backToLoginFromForgotLink">Back to login</a>
                </div>
            </div>

            <!-- Patient Registration (inline) -->
            <div class="login-form-container" id="registrationContainer" style="display: none;">
                <div class="logo-container">
                    <img src="<?php echo htmlspecialchars($loginLogo); ?>" alt="RF Logo" class="logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <svg class="logo" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" style="display:none;">
                        <path d="M50 20 L30 40 L30 60 L50 80 L70 60 L70 40 Z" fill="#2563eb" stroke="#2563eb" stroke-width="2"/>
                        <text x="50" y="45" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white" text-anchor="middle">R</text>
                        <text x="50" y="70" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white" text-anchor="middle">F</text>
                    </svg>
                </div>
                
                <h1 class="clinic-name">Create Account</h1>
                <p class="login-subtitle">Register as a patient to book and track appointments.</p>

                <div class="alert alert-error" id="registerError" style="display: none;"></div>
                <div class="alert alert-success" id="registerSuccess" style="display: none;"></div>
                
                <form id="registerForm" method="POST">
                    <div style="display: flex; gap: 12px; width: 100%;">
                        <div class="input-group" style="flex:1;">
                            <input type="text" id="reg_first_name" name="first_name" placeholder="First Name" required autocomplete="given-name">
                        </div>
                        <div class="input-group" style="flex:1;">
                            <input type="text" id="reg_middle_name" name="middle_name" placeholder="Middle Name (optional)" autocomplete="additional-name">
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <input type="text" id="reg_last_name" name="last_name" placeholder="Last Name" required autocomplete="family-name">
                    </div>
                    
                    <div class="input-group">
                        <input type="email" id="reg_email" name="email" placeholder="Email Address" required autocomplete="email">
                    </div>
                    
                    <div class="input-group">
                        <input type="tel" id="reg_phone" name="phone" placeholder="Phone Number" required autocomplete="tel">
                    </div>
                    
                    <div class="input-group">
                        <input type="password" id="reg_password" name="password" placeholder="Password" required autocomplete="new-password">
                    </div>
                    
                    <div class="input-group">
                        <input type="password" id="reg_confirm_password" name="confirm_password" placeholder="Confirm Password" required autocomplete="new-password">
                    </div>

                    <div class="form-links" style="margin-bottom: 20px;">
                        <span class="separator">By registering, you agree to our Terms of Service and Privacy Policy.</span>
                    </div>
                    
                    <button type="submit" class="login-btn" id="registerBtn" style="display: flex; justify-content: center; align-items: center;">
                        <span id="registerBtnText">Create Account</span>
                    </button>
                </form>

                <div id="regOtpStep" class="step-pane" style="display: none;">
                    <p class="login-subtitle" style="text-align:center; margin-bottom: 16px;">
                        Enter the 6-digit verification code sent to <strong id="regOtpEmailDisplay"></strong>
                    </p>
                    <div class="alert alert-error" id="regOtpError" style="display: none;"></div>
                    <div class="input-group">
                        <input type="text" id="regOtpCode" placeholder="Enter verification code" maxlength="6" inputmode="numeric" style="text-align: center; letter-spacing: 6px; font-size: 18px;">
                    </div>
                    <button type="button" class="login-btn" id="regOtpVerifyBtn">
                        <span id="regOtpVerifyBtnText">Verify & Create Account</span>
                    </button>
                    <div style="text-align:center; margin-top: 12px;">
                        <span style="font-size:13px; color:#666;">Didn't receive the code? </span>
                        <button type="button" id="regOtpResendBtn" style="background:none; border:none; color:#2563eb; cursor:pointer; font-size:13px; text-decoration:underline;">Resend</button>
                        <span id="regOtpTimer" style="font-size:13px; color:#999; margin-left:4px;"></span>
                    </div>
                    <div class="form-links" style="margin-top: 16px; justify-content: center;">
                        <a href="#register" class="link" id="backToRegisterFromOtpLink">Back to registration form</a>
                    </div>
                </div>
                
                <div class="form-links" style="margin-top: 16px; justify-content: center;">
                    <span>Already have an account? </span>
                    <a href="#login" class="link" id="backToLoginLink">Login</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div id="toast" class="toast">
        <div class="toast-wrapper">
            <div class="toast-left-border"></div>
            <div class="toast-icon-wrapper">
                <span class="toast-icon"></span>
            </div>
            <div class="toast-content">
                <span class="toast-title"></span>
                <span class="toast-message"></span>
            </div>
        </div>
        <div class="toast-loading-line"></div>
    </div>
    
    <script src="../../assets/js/login.js"></script>
    
    <?php if ($showDeletedMsg): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            window.showToast('Account Deactivated', 'Your account has been deactivated. If you believe this was a mistake, please contact the clinic.', 'success');
        });
    </script>
    <?php endif; ?>
</body>
</html>
