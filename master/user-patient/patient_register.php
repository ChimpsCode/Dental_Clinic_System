<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

function respond(bool $success, string $message, int $status = 200): void {
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.', 405);
}

$firstName = trim($_POST['first_name'] ?? '');
$middleName = trim($_POST['middle_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = (string)($_POST['password'] ?? '');
$confirmPassword = (string)($_POST['confirm_password'] ?? '');

if ($firstName === '' || $lastName === '' || $email === '' || $phone === '' || $username === '' || $password === '' || $confirmPassword === '') {
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

if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username)) {
    respond(false, 'Username must be 3-30 characters and only letters, numbers, dot, underscore, or hyphen.');
}

try {
    require_once '../../config/database.php';
    if (!isset($pdo)) {
        respond(false, 'Database connection not available.', 500);
    }

    $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $checkEmail->execute([$email]);
    if ($checkEmail->fetch(PDO::FETCH_ASSOC)) {
        respond(false, 'Email already exists.');
    }

    $checkUsername = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $checkUsername->execute([$username]);
    if ($checkUsername->fetch(PDO::FETCH_ASSOC)) {
        respond(false, 'Username already exists.');
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();

    $userColumns = ['username', 'password', 'email', 'role'];
    $userValues = [$username, $passwordHash, $email, 'user'];
    if (tableHasColumn($pdo, 'users', 'first_name')) {
        $userColumns[] = 'first_name';
        $userValues[] = $firstName;
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
    if (tableHasColumn($pdo, 'patients', 'registration_source')) {
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
    respond(true, 'Account created successfully.');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Patient registration error: ' . $e->getMessage());
    respond(false, 'Registration failed. Please try again.', 500);
}
