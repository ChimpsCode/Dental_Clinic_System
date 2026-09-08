<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
require_once __DIR__ . '/../../includes/session_bootstrap.php';
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once __DIR__ . '/../../includes/discount_helper.php';
require_once __DIR__ . '/../../includes/input_sanitize.php';

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
    $otpResult = sanitize_digit_code($_POST['otp'] ?? '', 6, 'Verification code');
    if (!$otpResult['ok']) {
        echo json_encode(['success' => false, 'message' => $otpResult['error']]);
        exit();
    }
    $enteredOtp = $otpResult['value'];
    $storedOtp = $_SESSION['reg_otp'] ?? '';
    $expires = $_SESSION['reg_otp_expires'] ?? 0;
    $regData = $_SESSION['reg_data'] ?? null;

    if (!$storedOtp) {
        echo json_encode(['success' => false, 'message' => 'Verification code has expired. Please register again.']);
        exit();
    }

    if (!$regData) {
        $fallback = sanitize_patient_registration_input($_POST, ['require_dob' => false]);
        $password = (string)($_POST['password'] ?? '');
        if ($fallback['ok'] && $password !== '') {
            $regData = $fallback['data'];
            $regData['pwd_id'] = normalizePwdIdField(!empty($_POST['is_pwd']), $fallback['data']['pwd_id']);
            list($regData['senior_id_type'], $regData['senior_citizen_id']) = normalizeSeniorIdFields(
                $fallback['data']['senior_id_type'],
                $fallback['data']['senior_citizen_id']
            );
            $regData['password'] = $password;
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
    $address = $regData['address'] ?? '';
    $emergencyContactName = $regData['emergency_contact_name'] ?? '';
    $emergencyContactPhone = $regData['emergency_contact_phone'] ?? '';
    $emergencyContactRelationship = $regData['emergency_contact_relationship'] ?? '';
    $password = $regData['password'];

    try {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        ensureDiscountSchema($pdo);

        $pdo->beginTransaction();

        // Re-check uniqueness at insert time: the email was validated when the
        // OTP was sent, but another registration may have completed since then.
        $dupStmt = $pdo->prepare("SELECT id FROM users WHERE (email = ? OR username = ?) AND COALESCE(is_archived, 0) = 0 LIMIT 1");
        $dupStmt->execute([$email, $email]);
        if ($dupStmt->fetch()) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'This email is already registered. Please log in instead.']);
            exit();
        }

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

        $patientInsertColumns = [
            'first_name',
            'middle_name',
            'last_name',
            'phone',
            'email',
            'gender',
            'date_of_birth'
        ];
        $patientInsertValues = [
            $first_name,
            $middle_name ?: null,
            $last_name,
            $phone,
            $email,
            $gender,
            $date_of_birth ?: null
        ];

        if (tableHasColumn($pdo, 'patients', 'street_address')) {
            $patientInsertColumns[] = 'street_address';
            $patientInsertValues[] = $address !== '' ? $address : null;
        }
        if (tableHasColumn($pdo, 'patients', 'emergency_contact_name')) {
            $patientInsertColumns[] = 'emergency_contact_name';
            $patientInsertValues[] = $emergencyContactName !== '' ? $emergencyContactName : null;
        }
        if (tableHasColumn($pdo, 'patients', 'emergency_contact_phone')) {
            $patientInsertColumns[] = 'emergency_contact_phone';
            $patientInsertValues[] = $emergencyContactPhone !== '' ? $emergencyContactPhone : null;
        }
        if (tableHasColumn($pdo, 'patients', 'emergency_contact_relationship')) {
            $patientInsertColumns[] = 'emergency_contact_relationship';
            $patientInsertValues[] = $emergencyContactRelationship !== '' ? $emergencyContactRelationship : null;
        }
        if (tableHasColumn($pdo, 'patients', 'senior_citizen_id')) {
            $patientInsertColumns[] = 'senior_citizen_id';
            $patientInsertValues[] = ($regData['senior_citizen_id'] ?? '') !== '' ? $regData['senior_citizen_id'] : null;
        }
        if (tableHasColumn($pdo, 'patients', 'senior_id_type')) {
            $patientInsertColumns[] = 'senior_id_type';
            $patientInsertValues[] = ($regData['senior_id_type'] ?? '') !== '' ? $regData['senior_id_type'] : null;
        }
        if (tableHasColumn($pdo, 'patients', 'pwd_id')) {
            $patientInsertColumns[] = 'pwd_id';
            $patientInsertValues[] = ($regData['pwd_id'] ?? '') !== '' ? $regData['pwd_id'] : null;
        }

        $insertPatientSql = "
            INSERT INTO patients (
                " . implode(', ', $patientInsertColumns) . "
            ) VALUES (" . implode(', ', array_fill(0, count($patientInsertColumns), '?')) . ")
        ";
        $insertPatientParams = $patientInsertValues;
        $stmt = $pdo->prepare($insertPatientSql);
        $stmt->execute($insertPatientParams);
        $patient_id = $pdo->lastInsertId();

        if (tableHasColumn($pdo, 'users', 'patient_id')) {
            $linkStmt = $pdo->prepare("UPDATE users SET patient_id = ? WHERE id = ?");
            $linkStmt->execute([$patient_id, $user_id]);
        }

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

    $fields = sanitize_patient_registration_input($_POST, ['require_dob' => false]);
    if (!$fields['ok']) {
        throw new Exception($fields['error']);
    }

    $first_name = $fields['data']['first_name'];
    $middle_name = $fields['data']['middle_name'];
    $last_name = $fields['data']['last_name'];
    $email = $fields['data']['email'];
    $phone = $fields['data']['phone'];
    $gender = $fields['data']['gender'];
    $date_of_birth = $fields['data']['date_of_birth'];
    $address = $fields['data']['address'];
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

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

    $emergency_contact_name = $fields['data']['emergency_contact_name'];
    $emergency_contact_phone = $fields['data']['emergency_contact_phone'];
    $emergency_contact_relationship = $fields['data']['emergency_contact_relationship'];
    $senior_citizen_id = $fields['data']['senior_citizen_id'];
    $senior_id_type = $fields['data']['senior_id_type'];
    list($senior_id_type, $senior_citizen_id) = normalizeSeniorIdFields($senior_id_type, $senior_citizen_id);
    $pwd_id = normalizePwdIdField(!empty($_POST['is_pwd']), $fields['data']['pwd_id']);

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
        'address' => $address,
        'emergency_contact_name' => $emergency_contact_name,
        'emergency_contact_phone' => $emergency_contact_phone,
        'emergency_contact_relationship' => $emergency_contact_relationship,
        'senior_citizen_id' => $senior_citizen_id,
        'senior_id_type' => $senior_id_type,
        'pwd_id' => $pwd_id,
        'password' => $password,
    ];

    require_once __DIR__ . '/../../includes/send_otp_email.php';
    $emailError = null;
    $emailSent = sendOtpEmail($email, $first_name, $otpCode, 'registration', $emailError);

    if (!$emailSent) {
        $_SESSION['reg_otp'] = '';
        $_SESSION['reg_otp_expires'] = 0;
        $_SESSION['reg_data'] = null;
        $detail = $emailError ? ' (' . $emailError . ')' : '';
        throw new Exception('Unable to send verification email. Please check your SMTP settings or try again later.' . $detail);
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
