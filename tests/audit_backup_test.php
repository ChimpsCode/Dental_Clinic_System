<?php
require_once __DIR__ . '/../includes/audit_backup_helper.php';

function checkAuditBackup(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function logAudit(...$args): bool { return $GLOBALS['auditTestLogSucceeds'] ?? true; }
class AuditBackupTestStatement extends PDOStatement {
    public array $params = [];
    public function __construct(private AuditBackupTestPDO $db, private string $sql) {}
    public function execute(?array $params = null): bool {
        $this->params = $params ?? [];
        if (str_starts_with($this->sql, 'DELETE')) {
            checkAuditBackup(count(glob($this->db->directory . '/*.csv.php')) === 1, 'Deletion preceded finalized backup.');
            $this->db->deleted = count($this->db->rows);
        }
        return true;
    }
    public function fetchColumn(int $column = 0): mixed {
        return str_contains($this->sql, 'ENGINE') ? 'InnoDB' : count($this->db->rows);
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
        return array_slice(array_values(array_filter($this->db->rows, fn($row) => $row['id'] > $this->params[1])), 0, 500);
    }
    public function rowCount(): int { return $this->db->deleted; }
}
class AuditBackupTestPDO extends PDO {
    public bool $active = false, $committed = false, $rolledBack = false;
    public int $deleted = 0;
    public array $rows = [];
    public function __construct(public string $directory) {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false { return new AuditBackupTestStatement($this, $query); }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new AuditBackupTestStatement($this, $query); }
    public function beginTransaction(): bool { $this->active = true; return true; }
    public function inTransaction(): bool { return $this->active; }
    public function commit(): bool { $this->committed = true; $this->active = false; return true; }
    public function rollBack(): bool { $this->rolledBack = true; $this->active = false; $this->deleted = 0; return true; }
}

$directory = sys_get_temp_dir() . '/clinic-audit-test-' . bin2hex(random_bytes(8));
mkdir($directory);
try {
    checkAuditBackup(auditBackupCsvCell('=HYPERLINK("test")')[0] === "'", 'Unsafe CSV formula.');
    foreach ([date('Y-m-d'), '2026-02-30', '../invalid'] as $date) {
        try { auditCleanupCutoff($date); throw new RuntimeException('Invalid cutoff accepted.'); }
        catch (InvalidArgumentException $e) {}
    }
    $cutoff = auditCleanupCutoff(date('Y-m-d', strtotime('-31 days')));
    $db = new AuditBackupTestPDO($directory);
    for ($id = 1; $id <= 501; $id++) $db->rows[] = ['id' => $id, 'description' => '=test,"quoted"', 'created_at' => '2020-01-01 00:00:00'];
    $result = backupAndDeleteAuditLogs($db, $cutoff, ['id' => 1, 'username' => 'fixture'], $directory);
    checkAuditBackup($result['count'] === 501 && $db->committed && $db->deleted === 501, 'Batched cleanup failed.');
    $file = fopen($directory . '/' . $result['file'], 'rb');
    checkAuditBackup(fread($file, strlen(AUDIT_BACKUP_GUARD)) === AUDIT_BACKUP_GUARD, 'Missing direct-access guard.');
    fread($file, 3);
    $header = fgetcsv($file, null, ',', '"', '');
    $rows = 0;
    while (($row = fgetcsv($file, null, ',', '"', '')) !== false) { $rows++; checkAuditBackup($row[1] === "'=test,\"quoted\"", 'CSV escaping failed.'); }
    fclose($file);
    checkAuditBackup($rows === 501 && count($header) === 3, 'Backup omitted records or columns.');
    unlink($directory . '/' . $result['file']);
    $db = new AuditBackupTestPDO($directory);
    $db->rows = [['id' => 1, 'description' => 'fixture']];
    $GLOBALS['auditTestLogSucceeds'] = false;
    try { backupAndDeleteAuditLogs($db, $cutoff, ['id' => 1, 'username' => 'fixture'], $directory); throw new RuntimeException('Audit failure accepted.'); }
    catch (RuntimeException $e) { checkAuditBackup($db->rolledBack && !$db->committed && $db->deleted === 0, 'Failure did not roll back.'); }
    foreach (glob($directory . '/*') as $file) unlink($file);
    $GLOBALS['auditTestLogSucceeds'] = true;
    $db = new AuditBackupTestPDO($directory);
    try { backupAndDeleteAuditLogs($db, $cutoff, ['id' => 1, 'username' => 'fixture'], $directory); throw new RuntimeException('Empty deletion accepted.'); }
    catch (InvalidArgumentException $e) { checkAuditBackup($db->rolledBack && $db->deleted === 0, 'Empty cleanup mutated records.'); }
    echo "Audit backup tests passed: cutoff protection, CSV safety, 501-row batching, backup before deletion, rollback, and empty selections.\n";
} finally {
    foreach (glob($directory . '/*') as $file) unlink($file);
    rmdir($directory);
}
