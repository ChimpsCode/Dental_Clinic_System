<?php
require_once __DIR__ . '/../../includes/require_admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/audit_helper.php';
require_once __DIR__ . '/../../includes/audit_import_helper.php';
header('Cache-Control: no-store');
header('Content-Type: application/json; charset=utf-8');
function auditImportReply(array $data, int $status = 200): void {
    http_response_code($status); echo json_encode($data); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') auditImportReply(['success' => false, 'message' => 'Use the upload form.'], 405);
if (empty($_SESSION['audit_cleanup_csrf']) || !hash_equals($_SESSION['audit_cleanup_csrf'], (string)($_POST['csrf'] ?? ''))) {
    auditImportReply(['success' => false, 'message' => 'Reload the audit trail page and try again.'], 403);
}
try {
    $action = (string)($_POST['action'] ?? '');
    if (!in_array($action, ['preview', 'restore'], true)) throw new InvalidArgumentException('Invalid import action.');
    $stmt = $pdo->prepare('SELECT id, username, password, role, status, is_archived FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$admin || strtolower((string)$admin['role']) !== 'admin' || (int)$admin['is_archived'] !== 0
        || strcasecmp((string)($admin['status'] ?? 'Active'), 'Active') !== 0) {
        auditImportReply(['success' => false, 'message' => 'An active admin account is required.'], 403);
    }
    if ($action === 'restore') {
        if (time() < (int)($_SESSION['audit_import_retry_after'] ?? 0)) auditImportReply(['success' => false, 'message' => 'Too many password attempts. Wait one minute and try again.'], 429);
        if (!password_verify((string)($_POST['password'] ?? ''), (string)$admin['password'])) {
            $_SESSION['audit_import_attempts'] = (int)($_SESSION['audit_import_attempts'] ?? 0) + 1;
            if ($_SESSION['audit_import_attempts'] >= 5) { $_SESSION['audit_import_retry_after'] = time() + 60; $_SESSION['audit_import_attempts'] = 0; }
            auditImportReply(['success' => false, 'message' => 'The password does not match your admin account.'], 403);
        }
        $_SESSION['audit_import_attempts'] = 0;
    }
    $source = (string)($_POST['source'] ?? 'upload');
    if (!in_array($source, ['upload', 'saved'], true)) throw new InvalidArgumentException('Invalid backup source.');
    // Both entry points restore from the admin's local CSV, never a server copy.
    $upload = $_FILES['backup'] ?? [];
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'] ?? '')) {
        throw new InvalidArgumentException('Choose a backup CSV within the server upload limit (maximum 5 MB).');
    }
    $filename = basename(str_replace('\\', '/', (string)($upload['name'] ?? '')));
    if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'csv') throw new InvalidArgumentException('Upload a .csv backup file.');
    $path = $upload['tmp_name'];
    $rows = readAuditImport($path);
    $hash = hash_file('sha256', $path);
    if ($source === 'saved') {
        $savedFile = (string)($_POST['saved_file'] ?? '');
        verifyAuditHistoryCsv($savedFile, $hash);
        $filename = substr($savedFile, 0, -4);
    }
    if ($action === 'preview') {
        $result = analyzeAuditImport($pdo, $rows);
        unset($result['rows']);
        $_SESSION['audit_import_preview'] = ['hash' => $hash, 'source' => $source, 'file' => $filename, 'expires' => time() + 900];
        auditImportReply(['success' => true] + $result);
    }
    $preview = $_SESSION['audit_import_preview'] ?? [];
    if (empty($preview['hash']) || (int)($preview['expires'] ?? 0) < time() || !hash_equals($preview['hash'], $hash)
        || ($preview['source'] ?? '') !== $source || ($preview['file'] ?? '') !== $filename) {
        throw new InvalidArgumentException('Preview this backup again before restoring it.');
    }
    $result = restoreAuditImport($pdo, $rows, $admin, $filename, $hash);
    if ($source === 'saved') {
        try { recordAuditBackupRestoration($savedFile, $result); }
        catch (Throwable $e) {
            // The database restore has committed; a status-write failure must not report it as failed.
            error_log('Could not update backup restoration status: ' . $e->getMessage());
            $result['history_warning'] = true;
        }
    }
    unset($_SESSION['audit_import_preview']);
    auditImportReply(['success' => true] + $result);
} catch (InvalidArgumentException $e) {
    auditImportReply(['success' => false, 'message' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log('Audit import failed: ' . $e->getMessage());
    auditImportReply(['success' => false, 'message' => 'Restoration could not complete. No restoration was committed. Please try again.'], 500);
}
