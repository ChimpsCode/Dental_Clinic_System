<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
session_start();
header('Content-Type: application/json');
require_once '../../config/database.php';

function tableHasColumn(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
    $stmt->execute([$table, $column]);
    $cache[$key] = (bool)$stmt->fetchColumn();
    return $cache[$key];
}

function findRoleId(PDO $pdo, string $roleName): ?int {
    static $cache = [];
    $key = strtolower($roleName);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE LOWER(role_name) = LOWER(?) LIMIT 1");
    $stmt->execute([$roleName]);
    $roleId = $stmt->fetchColumn();
    $cache[$key] = $roleId !== false ? (int)$roleId : null;
    return $cache[$key];
}

function isStrongPassword(string $password): bool {
    return strlen($password) >= 8
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
}

$action = trim($_POST['action'] ?? 'register');

if ($action === 'verify_otp') {
    $enteredOtp = trim($_POST['otp'] ?? '');
    $storedOtp = $_SESSION['reg_otp'] ?? '';
    $expires = $_SESSION['reg_otp_expires'] ?? 0;
    $regData = $_SESSION['reg_data'] ?? null;

    if (!$storedOtp) {
        echo json_encode(['success' => false, 'message' => 'Verification code has expired. Please register again.']);
        exit();
    }

    if (!$regData) {
        $firstName = trim($_POST['first_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($firstName !== '' && $lastName !== '' && $email !== '' && $phone !== '' && $password !== '') {
            $regData = [
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'gender' => $gender,
                'date_of_birth' => $dateOfBirth,
                'password' => $password,
            ];
        }
    }

    if (!$regData) {
        echo json_encode(['success' => false, 'message' => 'Registration details were lost. Please start again.']);
        exit();
    }

    if (time() > $expires) {
        $_SESSION['reg_otp'] = '';
        $_SESSION['reg_otp_expires'] = 0;
        echo json_encode(['success' => false, 'message' => 'Verification code has expired. Please register again.']);
        exit();
    }

    if ((string)$enteredOtp !== (string)$storedOtp) {
        error_log("RF_DEBUG OTP MISMATCH: entered=[" . var_export($enteredOtp, true) . "] stored=[" . var_export($storedOtp, true) . "] entered_type=" . gettype($enteredOtp) . " stored_type=" . gettype($storedOtp));
        echo json_encode(['success' => false, 'message' => 'Invalid verification code. Please try again.']);
        exit();
    }

    $_SESSION['reg_otp'] = '';
    $_SESSION['reg_otp_expires'] = 0;

    $first_name = $regData['first_name'];
    $middle_name = $regData['middle_name'] ?? '';
    $last_name = $regData['last_name'];
    $email = $regData['email'];
    $phone = $regData['phone'];
    $gender = $regData['gender'];
    $date_of_birth = $regData['date_of_birth'] ?? '';
    $password = $regData['password'];

    try {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $pdo->beginTransaction();

        $userColumns = ['first_name', 'middle_name', 'last_name', 'username', 'password', 'email', 'phone', 'gender', 'date_of_birth'];
        $userValues = [$first_name, $middle_name ?: null, $last_name, $email, $hashedPassword, $email, $phone, $gender, $date_of_birth ?: null];

        if (tableHasColumn($pdo, 'users', 'role_id')) {
            $patientRoleId = findRoleId($pdo, 'Patient');
            $userColumns[] = 'role_id';
            $userValues[] = $patientRoleId;
        } elseif (tableHasColumn($pdo, 'users', 'role')) {
            $userColumns[] = 'role';
            $userValues[] = 'Patient';
        }

        $placeholders = implode(', ', array_fill(0, count($userColumns), '?'));
        $stmt = $pdo->prepare("INSERT INTO users (" . implode(', ', $userColumns) . ") VALUES ($placeholders)");
        $stmt->execute($userValues);
        $user_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO patients (
                first_name, middle_name, last_name,
                phone, email, gender, date_of_birth
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $first_name,
            $middle_name ?: null,
            $last_name,
            $phone,
            $email,
            $gender,
            $date_of_birth ?: null
        ]);
        $patient_id = $pdo->lastInsertId();

        $pdo->commit();

        try {
            require_once __DIR__ . '/../../includes/sms_functions.php';
            sendRegistrationWelcomeSMS($phone, $first_name);
        } catch (\Throwable $e) {
            error_log("Welcome SMS skipped (non-blocking): " . $e->getMessage());
        }

        $_SESSION['user_id'] = $user_id;
        $_SESSION['role'] = 'user';
        $_SESSION['user_role'] = 'user';
        $_SESSION['user_name'] = $first_name;
        $_SESSION['patient_id'] = $patient_id;
        $_SESSION['display_name'] = $first_name;
        $_SESSION['new_registration'] = true;
        $_SESSION['is_new_patient'] = true;
        $_SESSION['reg_data'] = null;

        echo json_encode([
            'success' => true,
            'message' => 'Registration successful!',
            'redirect' => '../user-patient/patient-dashboard.php'
        ]);

    } catch (\Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Registration Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Registration failed. Please try again.'
        ]);
    }
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $age = trim($_POST['age'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($first_name)) {
        throw new Exception('First name is required');
    }

    if (empty($last_name)) {
        throw new Exception('Last name is required');
    }

    if (empty($email)) {
        throw new Exception('Email is required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }

    if (empty($phone)) {
        throw new Exception('Phone number is required');
    }

    if (empty($gender)) {
        throw new Exception('Gender is required');
    }

    if (empty($password)) {
        throw new Exception('Password is required');
    }

    if ($password !== $confirm_password) {
        throw new Exception('Passwords do not match');
    }

    if (!isStrongPassword($password)) {
        throw new Exception('Password must be at least 8 characters and include uppercase, lowercase, number, and symbol');
    }

    $stmt = $pdo->prepare("SELECT id, is_archived FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$email, $email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        if ($existing['is_archived']) {
            throw new Exception('This email was previously used and is no longer active. Please contact support.');
        }
        throw new Exception('Email already registered. Please login instead.');
    }

    $otpCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['reg_otp'] = $otpCode;
    $_SESSION['reg_otp_expires'] = time() + 180;
    $_SESSION['reg_data'] = [
        'first_name' => $first_name,
        'middle_name' => $middle_name,
        'last_name' => $last_name,
        'email' => $email,
        'phone' => $phone,
        'gender' => $gender,
        'date_of_birth' => $date_of_birth,
        'password' => $password,
    ];

    require_once __DIR__ . '/../../includes/send_otp_email.php';
    $emailSent = sendOtpEmail($email, $first_name, $otpCode);

    if (!$emailSent) {
        $_SESSION['reg_otp'] = '';
        $_SESSION['reg_otp_expires'] = 0;
        $_SESSION['reg_data'] = null;
        throw new Exception('Unable to send verification email. Please check your email address or try again later.');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Verification code sent to your email.',
        'step' => 'otp'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
