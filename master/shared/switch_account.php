<?php
require_once __DIR__ . '/../../includes/session_bootstrap.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/account_switch_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function switchReply(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') switchReply(['success' => false, 'message' => 'Invalid request.'], 405);
if (empty($_SESSION['user_id']) || !empty($GLOBALS['APP_SESSION_EXPIRED']) || !empty($GLOBALS['APP_ACCOUNT_REMOVED'])
    || !switchableClinicRole($_SESSION['role'] ?? null)) {
    switchReply(['success' => false, 'message' => 'Sign in to a clinic account.'], 403);
}
if (empty($_SESSION['clinic_switch_csrf']) || !hash_equals($_SESSION['clinic_switch_csrf'], (string)($_POST['csrf'] ?? ''))) {
    switchReply(['success' => false, 'message' => 'Reload the page and try again.'], 403);
}
$currentId = (int)$_SESSION['user_id'];
$sourceStmt = $pdo->prepare('SELECT id, role, status, is_archived FROM users WHERE id = ? LIMIT 1');
$sourceStmt->execute([$currentId]);
$source = $sourceStmt->fetch(PDO::FETCH_ASSOC);
if (!$source || switchableClinicRole($source['role']) !== switchableClinicRole($_SESSION['role'])
    || (int)$source['is_archived'] !== 0 || strcasecmp((string)$source['status'], 'Active') !== 0) {
    switchReply(['success' => false, 'message' => 'Your account is unavailable. Sign in again.'], 403);
}

$action = (string)($_POST['action'] ?? '');
try {
    if ($action === 'list') {
        switchReply(['success' => true, 'accounts' => clinicSwitchTargets($pdo, $currentId)]);
    }
    if ($action === 'switch') {
        $targetId = (int)($_POST['id'] ?? 0);
        if ($targetId <= 0 || $targetId === $currentId || !clinicSwitchAuthorized($pdo, $currentId, $targetId)) {
            switchReply(['success' => false, 'message' => 'This account is not linked for switching.'], 403);
        }
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND COALESCE(is_archived, 0) = 0 LIMIT 1');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$target || !switchableClinicRole($target['role']) || strcasecmp((string)$target['status'], 'Active') !== 0) {
            switchReply(['success' => false, 'message' => 'This account is unavailable.'], 403);
        }
        switchReply(['success' => true, 'redirect' => completeClinicAccountSwitch($pdo, $target)]);
    }
} catch (Throwable $e) {
    error_log('Account switch failed: ' . $e->getMessage());
    switchReply(['success' => false, 'message' => 'Could not switch accounts. Please try again.'], 500);
}
switchReply(['success' => false, 'message' => 'Unknown action.'], 400);
