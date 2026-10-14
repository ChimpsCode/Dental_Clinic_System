<?php
require_once __DIR__ . '/../../includes/session_bootstrap.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/account_switch_helper.php';
require_once __DIR__ . '/../../includes/audit_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function linkReply(array $result, int $status = 200): void {
    http_response_code($status);
    echo json_encode($result);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') linkReply(['success' => false, 'message' => 'Invalid request.'], 405);
if (empty($_SESSION['user_id']) || !empty($GLOBALS['APP_SESSION_EXPIRED']) || !empty($GLOBALS['APP_ACCOUNT_REMOVED'])
    || switchableClinicRole($_SESSION['role'] ?? null) !== 'admin') {
    linkReply(['success' => false, 'message' => 'Only an admin can manage switch profiles.'], 403);
}
if (empty($_SESSION['clinic_switch_csrf']) || !hash_equals($_SESSION['clinic_switch_csrf'], (string)($_POST['csrf'] ?? ''))) {
    linkReply(['success' => false, 'message' => 'Reload the page and try again.'], 403);
}
$adminId = (int)$_SESSION['user_id'];
$adminStmt = $pdo->prepare('SELECT id, username, password, role, status, is_archived FROM users WHERE id = ? LIMIT 1');
$adminStmt->execute([$adminId]);
$admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
if (!$admin || switchableClinicRole($admin['role']) !== 'admin' || (int)$admin['is_archived'] !== 0
    || strcasecmp((string)$admin['status'], 'Active') !== 0) {
    linkReply(['success' => false, 'message' => 'Admin account is unavailable.'], 403);
}

$action = (string)($_POST['action'] ?? '');
try {
    if ($action === 'list') {
        $stmt = $pdo->prepare("SELECT id, username, first_name, last_name, role FROM users WHERE id <> ? AND LOWER(role) IN ('admin','dentist') AND COALESCE(is_archived, 0) = 0 AND (status IS NULL OR LOWER(status) = 'active') ORDER BY role, first_name, last_name");
        $stmt->execute([$adminId]);
        $links = clinicSwitchLinks($pdo);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $allowedIds = array_map(static fn($user) => (int)$user['id'], $users);
        linkReply(['success' => true, 'users' => $users, 'linked' => array_values(array_intersect($links[$adminId] ?? [], $allowedIds))]);
    }
    if (!in_array($action, ['add', 'remove'], true)) linkReply(['success' => false, 'message' => 'Unknown action.'], 400);
    if (time() < (int)($_SESSION['clinic_link_retry_after'] ?? 0)) {
        linkReply(['success' => false, 'message' => 'Too many password attempts. Try again in one minute.'], 429);
    }
    $targetId = (int)($_POST['id'] ?? 0);
    if ($targetId <= 0 || $targetId === $adminId) linkReply(['success' => false, 'message' => 'Choose another clinic account.'], 400);
    if (!password_verify((string)($_POST['password'] ?? ''), (string)$admin['password'])) {
        $_SESSION['clinic_link_attempts'] = (int)($_SESSION['clinic_link_attempts'] ?? 0) + 1;
        if ($_SESSION['clinic_link_attempts'] >= 5) {
            $_SESSION['clinic_link_attempts'] = 0;
            $_SESSION['clinic_link_retry_after'] = time() + 60;
        }
        linkReply(['success' => false, 'message' => 'Admin password is incorrect.'], 403);
    }
    $_SESSION['clinic_link_attempts'] = 0;
    $targetStmt = $pdo->prepare('SELECT id, username, role, status, is_archived FROM users WHERE id = ? LIMIT 1');
    $targetStmt->execute([$targetId]);
    $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
    if ($action === 'add' && (!$target || !switchableClinicRole($target['role']) || (int)$target['is_archived'] !== 0
        || strcasecmp((string)$target['status'], 'Active') !== 0)) {
        linkReply(['success' => false, 'message' => 'Selected account is unavailable.'], 400);
    }
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = setting_value')
        ->execute([CLINIC_SWITCH_SETTING, '{}']);
    $links = clinicSwitchLinks($pdo, true);
    $linked = $links[$adminId] ?? [];
    if ($action === 'add') $linked[] = $targetId;
    else $linked = array_values(array_diff($linked, [$targetId]));
    $links[$adminId] = array_values(array_unique($linked));
    $pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?')
        ->execute([json_encode($links, JSON_THROW_ON_ERROR), CLINIC_SWITCH_SETTING]);
    $pdo->commit();
    logAudit($pdo, $adminId, (string)$admin['username'], 'admin', 'update', 'users',
        "Admin " . ($action === 'add' ? 'linked' : 'removed') . " clinic switch profile for user ID {$targetId}", $targetId, 'users');
    linkReply(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Clinic switch setup failed: ' . $e->getMessage());
    linkReply(['success' => false, 'message' => 'Could not save switch profiles. Please try again.'], 500);
}
