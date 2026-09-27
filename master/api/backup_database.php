<?php
/**
 * Create a clinic database backup and download it.
 * Admin session and the current account password are both required.
 */

require_once __DIR__ . '/../../includes/require_admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/audit_helper.php';
require_once __DIR__ . '/../../includes/backup_helper.php';

function backupJsonError(string $message, int $status = 400): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    backupJsonError('Backup requests must be sent from the settings page.', 405);
}

if (!($pdo instanceof PDO)) {
    backupJsonError('The database is not available.', 500);
}

$months = normalizeBackupMonths($_POST['months'] ?? 12);
$password = (string)($_POST['password'] ?? '');
if ($password === '') {
    backupJsonError('Enter your current password.');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$hash = (string)$stmt->fetchColumn();
if ($hash === '' || !password_verify($password, $hash)) {
    backupJsonError('That password does not match your account.');
}

$lock = null;
try {
    $lock = backupLockHandle();
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        backupJsonError('A backup is already running. Try again in a moment.', 409);
    }

    set_time_limit(120);
    $info = createDatabaseBackup($pdo, $months);

    logAudit(
        $pdo,
        $userId,
        (string)($_SESSION['username'] ?? 'admin'),
        'admin',
        'create',
        'settings',
        'Downloaded a clinic backup with ' . $months . ' months of activity history (' . $info['filename'] . ', ' . $info['size_label'] . ')'
    );

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $info['filename'] . '"');
    header('Content-Length: ' . $info['bytes']);
    header('X-Backup-Created: ' . $info['created_label']);
    header('X-Backup-Size: ' . $info['size_label']);
    header('Cache-Control: no-store');
    readfile($info['path']);
} catch (Throwable $e) {
    error_log('Manual backup error: ' . $e->getMessage());
    if (!headers_sent()) {
        $message = $e->getMessage() === 'Could not lock the backup folder.'
            ? 'A backup is already running. Try again in a moment.'
            : 'The backup could not be created.';
        backupJsonError($message, 500);
    }
} finally {
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
