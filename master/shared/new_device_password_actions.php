<?php
require_once __DIR__ . '/../../includes/session_bootstrap.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/login_device_alert.php';

header('Content-Type: application/json');

$userId = (int)($_SESSION['user_id'] ?? 0);
$role = strtolower((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? ''));
if ($role === 'administrator') {
    $role = 'admin';
}

if ($userId <= 0 || !in_array($role, ['admin', 'staff', 'dentist'], true)) {
    echo json_encode(['success' => false, 'message' => 'Not allowed.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Unable to change the password.']);
    exit;
}

$currentPassword = (string)($_POST['current_password'] ?? '');
$newPassword = (string)($_POST['new_password'] ?? '');
$confirmPassword = (string)($_POST['confirm_password'] ?? '');

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    echo json_encode(['success' => false, 'message' => 'Please fill in all password fields.']);
    exit;
}
if (strlen($newPassword) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}
if ($newPassword !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $stored = (string)($stmt->fetchColumn() ?: '');
    $matches = false;
    if ($stored !== '' && password_verify($currentPassword, $stored)) {
        $matches = true;
    } elseif ($stored !== '' && strlen($stored) === strlen($currentPassword) && hash_equals($stored, $currentPassword)) {
        $matches = true;
    }
    if (!$matches) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $upd = $pdo->prepare('UPDATE users SET password = ? WHERE id = ? LIMIT 1');
    $upd->execute([$hash, $userId]);
    bumpUserAuthVersion($pdo, $userId);
    echo json_encode(['success' => true, 'message' => 'Password saved. Other devices were signed out.']);
} catch (Throwable $e) {
    error_log('new_device_password_actions: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to change the password.']);
}
