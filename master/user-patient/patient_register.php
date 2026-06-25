<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

function respond(bool $success, string $message, array $extra = [], int $status = 200): void {
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit();
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.', [], 405);
}

$action = trim($_POST['action'] ?? 'register');

if ($action === 'verify_otp') {
    $enteredOtp = trim($_POST['otp'] ?? '');
    $storedOtp = $_SESSION['reg_otp'] ?? '';
    $expires = $_SESSION['reg_otp_expires'] ?? 0;
    $regData = $_SESSION['reg_data'] ?? null;

    if (!$regData || !$storedOtp) {
        respond(false, 'Registration session expired. Please try again.');
    }

    if (time() > $expires) {
        $_SESSION['reg_otp'] = '';
        $_SESSION['reg_otp_expires'] = 0;
        respond(false, 'Verification code has expired. Please register again.');
    }

    if ((string)$enteredOtp !== (string)$storedOtp) {
        error_log("RF_DEBUG OTP MISMATCH: entered=[" . var_export($enteredOtp, true) . "] stored=[" . var_export($storedOtp, true) . "] entered_type=" . gettype($enteredOtp) . " stored_type=" . gettype($storedOtp));
        respond(false, 'Invalid verification code. Please try again.');
    }

    $_SESSION['reg_otp'] = '';
    $_SESSION['reg_otp_expires'] = 0;

    $firstName = $regData['first_name'];
    $middleName = $regData['middle_name'] ?? '';
    $lastName = $regData['last_name'];
    $email = $regData['email'];
    $phone = $regData['phone'];
    $password = $regData['password'];

    try {
        require_once '../../config/database.php';
        if (!isset($pdo)) {
            respond(false, 'Database connection not available.', [], 500);
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $pdo->beginTransaction();

        $userColumns = ['username', 'password', 'email'];
        $userValues = [$email, $passwordHash, $email];

        if (tableHasColumn($pdo, 'users', 'role_id')) {
            $patientRoleId = findRoleId($pdo, 'Patient');
            $userColumns[] = 'role_id';
            $userValues[] = $patientRoleId;
        } elseif (tableHasColumn($pdo, 'users', 'role')) {
            $userColumns[] = 'role';
            $userValues[] = 'user';
        }

        if (tableHasColumn($pdo, 'users', 'phone')) {
            $userColumns[] = 'phone';
            $userValues[] = $phone;
        }
        if (tableHasColumn($pdo, 'users', 'first_name')) {
            $userColumns[] = 'first_name';
            $userValues[] = $firstName;
        }
        if (tableHasColumn($pdo, 'users', 'middle_name')) {
            $userColumns[] = 'middle_name';
            $userValues[] = $middleName !== '' ? $middleName : null;
        }
        if (tableHasColumn($pdo, 'users', 'last_name')) {
            $userColumns[] = 'last_name';
            $userValues[] = $lastName;
        }

        $placeholders = implode(', ', array_fill(0, count($userColumns), '?'));
        $userSql = "INSERT INTO users (" . implode(', ', $userColumns) . ") VALUES (" . $placeholders . ")";
        $insertUser = $pdo->prepare($userSql);
        $insertUser->execute($userValues);
        $userId = (int)$pdo->lastInsertId();

        $patientColumns = [];
        $patientValues = [];
        $fullName = trim($firstName . ' ' . $lastName);

        if (tableHasColumn($pdo, 'patients', 'first_name')) {
            $patientColumns[] = 'first_name';
            $patientValues[] = $firstName;
        }
        if (tableHasColumn($pdo, 'patients', 'middle_name')) {
            $patientColumns[] = 'middle_name';
            $patientValues[] = $middleName !== '' ? $middleName : null;
        }
        if (tableHasColumn($pdo, 'patients', 'last_name')) {
            $patientColumns[] = 'last_name';
            $patientValues[] = $lastName;
        }
        if (tableHasColumn($pdo, 'patients', 'full_name')) {
            $patientColumns[] = 'full_name';
            $patientValues[] = $fullName;
        }
        if (tableHasColumn($pdo, 'patients', 'phone')) {
            $patientColumns[] = 'phone';
            $patientValues[] = $phone;
        }
        if (tableHasColumn($pdo, 'patients', 'email')) {
            $patientColumns[] = 'email';
            $patientValues[] = $email;
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
            $patientValues[] = $userId;
        }

        if (empty($patientColumns)) {
            throw new RuntimeException('No compatible patient columns found for insert.');
        }

        $patientPlaceholders = implode(', ', array_fill(0, count($patientColumns), '?'));
        $patientSql = "INSERT INTO patients (" . implode(', ', $patientColumns) . ") VALUES (" . $patientPlaceholders . ")";
        $insertPatient = $pdo->prepare($patientSql);
        $insertPatient->execute($patientValues);

        $pdo->commit();

        $_SESSION['reg_data'] = null;

        $_SESSION['new_registration'] = true;

        respond(true, 'Account created successfully.');
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Patient registration error: ' . $e->getMessage());
        respond(false, 'Registration failed. Please try again.', [], 500);
    }
}

$firstName = trim($_POST['first_name'] ?? '');
$middleName = trim($_POST['middle_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = (string)($_POST['password'] ?? '');
$confirmPassword = (string)($_POST['confirm_password'] ?? '');

if ($firstName === '' || $lastName === '' || $email === '' || $phone === '' || $password === '' || $confirmPassword === '') {
    respond(false, 'Please fill in all required fields.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Please enter a valid email address.');
}

if ($password !== $confirmPassword) {
    respond(false, 'Passwords do not match.');
}

if (strlen($password) < 6) {
    respond(false, 'Password must be at least 6 characters.');
}

try {
    require_once '../../config/database.php';
    if (!isset($pdo)) {
        respond(false, 'Database connection not available.', [], 500);
    }

    $checkEmail = $pdo->prepare("SELECT id, is_archived FROM users WHERE email = ? OR username = ? LIMIT 1");
    $checkEmail->execute([$email, $email]);
    $existing = $checkEmail->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        if ($existing['is_archived']) {
            respond(false, 'This email was previously used and is no longer active. Please contact support to restore your account.');
        }
        respond(false, 'Email already exists.');
    }

    $otpCode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['reg_otp'] = $otpCode;
    $_SESSION['reg_otp_expires'] = time() + 180;
    $_SESSION['reg_data'] = [
        'first_name' => $firstName,
        'middle_name' => $middleName,
        'last_name' => $lastName,
        'email' => $email,
        'phone' => $phone,
        'password' => $password,
    ];

    require_once __DIR__ . '/../../includes/send_otp_email.php';
    $emailSent = sendOtpEmail($email, $firstName, $otpCode);

    if (!$emailSent) {
        $_SESSION['reg_otp'] = '';
        $_SESSION['reg_otp_expires'] = 0;
        $_SESSION['reg_data'] = null;
        respond(false, 'Unable to send verification email. Please check your email address or try again later.');
    }

    respond(true, 'Verification code sent to your email.', ['step' => 'otp']);
} catch (Throwable $e) {
    error_log('Patient registration OTP send error: ' . $e->getMessage());
    respond(false, 'Registration failed. Please try again.', [], 500);
}
