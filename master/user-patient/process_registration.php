<?php
header('Content-Type: application/json');
session_start();
require_once '../../config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get all fields from POST
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

    // Validate required fields
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

    if (strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters');
    }

    if ($password !== $confirm_password) {
        throw new Exception('Passwords do not match');
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception('Email already registered. Please login instead.');
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Start transaction
    $pdo->beginTransaction();

    // Create user account (for patient users, role_id = NULL)
    $stmt = $pdo->prepare("INSERT INTO users (first_name, middle_name, last_name, username, password, email) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$first_name, $middle_name ?: null, $last_name, $email, $hashedPassword, $email]);
    $user_id = $pdo->lastInsertId();

    // Create patient record (without address - use addresses table separately if needed)
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

    // Set session for automatic login
    $_SESSION['user_id'] = $user_id;
    $_SESSION['role'] = 'user';
    $_SESSION['user_role'] = 'user';
    $_SESSION['user_name'] = $first_name;
    $_SESSION['patient_id'] = $patient_id;

    echo json_encode([
        'success' => true,
        'message' => 'Registration successful!',
        'redirect' => '../user-patient/patient-dashboard.php'
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Registration Error: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
