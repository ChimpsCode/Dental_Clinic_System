<?php
header('Content-Type: application/json');
session_start();
require_once 'config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate required fields
    if (empty($name)) {
        throw new Exception('Name is required');
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

    if (empty($password) || strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters');
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception('Email already registered. Please login instead.');
    }

    // Parse name into components
    $nameParts = explode(' ', $name);
    $first_name = $nameParts[0] ?? '';
    $last_name = count($nameParts) > 1 ? $nameParts[count($nameParts) - 1] : '';
    $middle_name = count($nameParts) > 2 ? implode(' ', array_slice($nameParts, 1, -1)) : null;

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Start transaction
    $pdo->beginTransaction();

    // Create user account
    $stmt = $pdo->prepare("INSERT INTO users (first_name, middle_name, last_name, username, password, email, role) VALUES (?, ?, ?, ?, ?, ?, 'user')");
    $stmt->execute([$first_name, $middle_name, $last_name, $email, $hashedPassword, $email]);
    $user_id = $pdo->lastInsertId();

    // Create patient record
    $full_name = trim(($first_name ?? '') . ' ' . ($middle_name ?? '') . ' ' . ($last_name ?? ''));
    $stmt = $pdo->prepare("INSERT INTO patients (user_id, first_name, middle_name, last_name, full_name, phone, email, registration_source, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'lead_capture', 'active')");
    $stmt->execute([$user_id, $first_name, $middle_name, $last_name, $full_name, $phone, $email]);
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
        'redirect' => 'patient/patient-dashboard.php'
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