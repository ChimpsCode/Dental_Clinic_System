<?php

require_once __DIR__ . '/../../includes/session_bootstrap.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/audit_helper.php';
require_once __DIR__ . '/../../includes/two_factor_actions_lib.php';

$role = strtolower((string)($_SESSION['role'] ?? ''));
$allowedRoles = ['admin', 'staff', 'dentist'];

if (empty($_SESSION['user_id']) || !in_array($role, $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$username = (string)($_SESSION['username'] ?? 'unknown');
$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');

$auditCallback = null;
if ($role === 'admin') {
    $auditCallback = function (string $event) use ($pdo, $userId, $username, $role): void {
        if ($event === 'enable') {
            auditedUpsertSetting($pdo, 'two_factor_auth', '1', 'Enabled two-factor authentication', $userId, $username, $role);
            logAudit($pdo, $userId, $username, $role, 'update', 'users', 'Enabled authenticator-based two-factor authentication');
            return;
        }

        auditedUpsertSetting($pdo, 'two_factor_auth', '0', 'Disabled two-factor authentication', $userId, $username, $role);
        logAudit($pdo, $userId, $username, $role, 'update', 'users', 'Disabled authenticator-based two-factor authentication');
    };
}

$result = handleTwoFactorAction($pdo, $action, $userId, $username, $role, [
    'sync_global_setting' => $role === 'admin',
    'audit_callback' => $auditCallback,
]);

if ($result !== null) {
    echo json_encode($result);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
