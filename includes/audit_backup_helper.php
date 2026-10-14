<?php
const AUDIT_BACKUP_GUARD = "<?php http_response_code(404); exit; ?>\n";
require_once __DIR__ . '/audit_backup_history.php';

function auditBackupDirectory(): string {
    return __DIR__ . '/../storage/audit_backups';
}

function auditSavedBackupPath(string $name, ?string $directory = null): string {
    if (!preg_match('/^audit-backup-\d{4}-\d{2}-\d{2}-\d{6}-[a-f0-9]{16}\.csv\.php$/D', $name)) {
        throw new InvalidArgumentException('Invalid saved backup.');
    }
    $directory = $directory ?? auditBackupDirectory();
    $path = $directory . '/' . $name;
    if (!is_file($path) || is_link($path)) throw new InvalidArgumentException('Saved backup not found.');
    return $path;
}

function auditCleanupCutoff(string $value): string {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value || $date > new DateTimeImmutable('today -30 days')) {
        throw new InvalidArgumentException('Choose a valid date at least 30 days ago. Recent logs are kept.');
    }
    return $date->format('Y-m-d 00:00:00');
}

function auditBackupCsvCell($value): string {
    $value = (string)($value ?? '');
    // Prevent spreadsheet applications from executing audit text as a formula.
    return preg_match('/^[\s\x00-\x1f]*[=+@-]/', $value) ? "'" . $value : $value;
}

// Version 2 preserves NULL, literal apostrophes and spreadsheet-safe text.
function auditBackupRestoreCell($value): string {
    if ($value === null) return '\\N';
    $value = (string)$value;
    if (str_starts_with($value, '\\')) return '\\' . $value;
    if (str_starts_with($value, "'")) return "'" . $value;
    return auditBackupCsvCell($value);
}

function backupAndDeleteAuditLogs(PDO $pdo, string $cutoff, array $actor, ?string $directory = null): array {
    $engine = $pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs'")->fetchColumn();
    if (strtolower((string)$engine) !== 'innodb') throw new RuntimeException('Audit cleanup requires a transactional audit table.');
    $directory = $directory ?? auditBackupDirectory();
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Backup storage is unavailable.');
    $name = 'audit-backup-' . date('Y-m-d-His') . '-' . bin2hex(random_bytes(8)) . '.csv.php';
    $finalPath = $directory . '/' . $name;
    $path = $directory . '/' . $name . '.partial.php';
    $file = fopen($path, 'xb');
    if (!$file) throw new RuntimeException('Could not create the backup. No logs were deleted.');
    $count = 0;
    try {
        if (fwrite($file, AUDIT_BACKUP_GUARD) !== strlen(AUDIT_BACKUP_GUARD) || fwrite($file, "\xEF\xBB\xBF") !== 3) throw new RuntimeException('Could not write the backup.');
        if (fputcsv($file, ['#dental-audit-backup', '2'], ',', '"', '') === false) throw new RuntimeException('Could not write the backup format.');
        $pdo->beginTransaction();
        $maxId = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) FROM audit_logs')->fetchColumn();
        $cursor = 0;
        $columns = null;
        do {
            $stmt = $pdo->prepare('SELECT * FROM audit_logs WHERE created_at < ? AND id > ? AND id <= ? ORDER BY id LIMIT 500 FOR UPDATE');
            $stmt->execute([$cutoff, $cursor, $maxId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                if ($columns === null) {
                    $columns = array_keys($row);
                    if (fputcsv($file, $columns, ',', '"', '') === false) throw new RuntimeException('Could not write backup columns.');
                }
                if (fputcsv($file, array_map('auditBackupRestoreCell', array_values($row)), ',', '"', '') === false) throw new RuntimeException('Could not write backup records.');
                $cursor = (int)$row['id'];
                $count++;
            }
        } while (count($rows) === 500);
        if (!$count) throw new InvalidArgumentException('No logs exist before the selected date. Nothing was deleted.');
        if (!fflush($file) || (function_exists('fsync') && !fsync($file))) throw new RuntimeException('Could not safely save the backup.');
        fclose($file);
        $file = null;
        if (!rename($path, $finalPath)) throw new RuntimeException('Could not finalize the backup.');
        $path = $finalPath;
        writeAuditBackupHistory($name, ['file'=>$name, 'count'=>$count, 'created_at'=>date('Y-m-d H:i:s'),
            'csv_sha256'=>auditBackupCsvHash($path), 'confirmed_at'=>null], $directory);
        $delete = $pdo->prepare('DELETE FROM audit_logs WHERE created_at < ? AND id <= ?');
        $delete->execute([$cutoff, $maxId]);
        if ($delete->rowCount() !== $count) throw new RuntimeException('The audit records changed. Cleanup was cancelled.');
        $metadata = json_encode(['backup' => $name, 'count' => $count, 'before' => $cutoff, 'sha256' => hash_file('sha256', $path)]);
        if (!logAudit($pdo, $actor['id'], $actor['username'], 'admin', 'delete', 'audit_logs',
            "Backed up and deleted {$count} audit logs before {$cutoff}. Backup: {$name}", null, 'audit_logs', null, $metadata)) {
            throw new RuntimeException('Could not record the cleanup action.');
        }
        $pdo->commit();
        return ['file' => $name, 'count' => $count];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (is_resource($file)) fclose($file);
        // Keep completed backups even on a failed commit, so a copy cannot be lost.
        throw $e;
    }
}
