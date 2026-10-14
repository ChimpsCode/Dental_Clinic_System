<?php
require_once __DIR__ . '/../../includes/require_admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/audit_helper.php';
require_once __DIR__ . '/../../includes/audit_backup_helper.php';
header('Cache-Control: no-store');

function auditCleanupReply(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'download') {
    $name = (string)($_GET['file'] ?? '');
    if (!preg_match('/^audit-backup-\d{4}-\d{2}-\d{2}-\d{6}-[a-f0-9]{16}\.csv\.php$/D', $name)) auditCleanupReply(['success' => false, 'message' => 'Invalid backup.'], 400);
    $file = @fopen(auditBackupDirectory() . '/' . $name, 'rb');
    if (!$file || fread($file, strlen(AUDIT_BACKUP_GUARD)) !== AUDIT_BACKUP_GUARD) auditCleanupReply(['success' => false, 'message' => 'Backup not found.'], 404);
    header('Content-Type: text/csv; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="' . substr($name, 0, -4) . '"');
    fpassthru($file);
    fclose($file);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    try { auditCleanupReply(['success' => true, 'backups' => listAuditBackupHistory(null, $pdo)]); }
    catch (Throwable $e) { error_log('Backup history failed: ' . $e->getMessage()); auditCleanupReply(['success'=>false,'message'=>'Could not load backup history.'], 500); }
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') auditCleanupReply(['success' => false, 'message' => 'Use the cleanup form.'], 405);
if (empty($_SESSION['audit_cleanup_csrf']) || !hash_equals($_SESSION['audit_cleanup_csrf'], (string)($_POST['csrf'] ?? ''))) auditCleanupReply(['success' => false, 'message' => 'Reload the audit trail page and try again.'], 403);
try {
    if ($action === 'preview') {
        $cutoff = auditCleanupCutoff((string)($_POST['before'] ?? ''));
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM audit_logs WHERE created_at < ?');
        $stmt->execute([$cutoff]);
        $count = (int)$stmt->fetchColumn();
        $oldest = $pdo->query('SELECT MIN(created_at) FROM audit_logs')->fetchColumn();
        auditCleanupReply(['success' => true, 'count' => $count, 'oldest_log' => $oldest ?: null]);
    }
    if (!in_array($action, ['cleanup', 'confirm_saved'], true)) throw new InvalidArgumentException('Invalid action.');
    if (time() < ($_SESSION['audit_cleanup_retry_after'] ?? 0)) auditCleanupReply(['success' => false, 'message' => 'Too many password attempts. Wait one minute and try again.'], 429);
    $stmt = $pdo->prepare('SELECT id, username, password, role, status, is_archived FROM users WHERE id = ?');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$admin || strtolower($admin['role']) !== 'admin' || (int)$admin['is_archived'] !== 0
        || strcasecmp((string)($admin['status'] ?? 'Active'), 'Active') !== 0
        || !password_verify((string)($_POST['password'] ?? ''), $admin['password'])) {
        $_SESSION['audit_cleanup_attempts'] = ($_SESSION['audit_cleanup_attempts'] ?? 0) + 1;
        if ($_SESSION['audit_cleanup_attempts'] >= 5) { $_SESSION['audit_cleanup_retry_after'] = time() + 60; $_SESSION['audit_cleanup_attempts'] = 0; }
        auditCleanupReply(['success' => false, 'message' => 'The password does not match your admin account.'], 403);
    }
    $_SESSION['audit_cleanup_attempts'] = 0;
    if ($action === 'confirm_saved') {
        if (($_POST['saved'] ?? '') !== '1') throw new InvalidArgumentException('Confirm that the CSV is saved on your computer first.');
        confirmAuditBackupSaved($pdo, (string)($_POST['file'] ?? ''), $admin);
        auditCleanupReply(['success'=>true]);
    }
    $cutoff = auditCleanupCutoff((string)($_POST['before'] ?? ''));
    $result = backupAndDeleteAuditLogs($pdo, $cutoff, $admin);
    auditCleanupReply(['success' => true] + $result);
} catch (InvalidArgumentException $e) {
    auditCleanupReply(['success' => false, 'message' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log('Audit cleanup failed: ' . $e->getMessage());
    auditCleanupReply(['success' => false, 'message' => $action === 'confirm_saved'
        ? 'Could not remove the server copy. Check backup storage permissions and try confirmation again.'
        : 'Cleanup could not complete. No deletion was committed. Check backup storage and try again.'], 500);
}
