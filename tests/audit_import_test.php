<?php
require_once __DIR__ . '/../includes/audit_import_helper.php';
function assertAuditImport(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function logAudit($pdo, ...$args): bool {
    if (!empty($GLOBALS['failImportAudit'])) return false;
    $pdo->exec("INSERT INTO audit_logs (description,created_at) VALUES ('Import event','2026-01-01 00:00:00')");
    return true;
}
class ImportTestPDO extends PDO {
    public function __construct() { parent::__construct('sqlite::memory:'); $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
    public function prepare(string $query, array $options = []): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options); }
    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false {
        if (str_contains($query, 'information_schema.TABLES')) return parent::query("SELECT 'InnoDB'");
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$args);
    }
}
function writeImportFixture(string $path, array $rows, bool $v2 = true): void {
    $file = fopen($path, 'wb'); fwrite($file, "\xEF\xBB\xBF");
    if ($v2) fputcsv($file, ['#dental-audit-backup','2'], ',', '"', '');
    fputcsv($file, AUDIT_IMPORT_COLUMNS, ',', '"', '');
    foreach ($rows as $row) fputcsv($file, array_map($v2 ? 'auditBackupRestoreCell' : 'auditBackupCsvCell', array_map(static fn($column) => $row[$column], AUDIT_IMPORT_COLUMNS)), ',', '"', '');
    fclose($file);
}
function rejectImport(string $path, bool $saved = false): void {
    try { readAuditImport($path, $saved); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('Invalid backup was accepted.');
}
$path = tempnam(sys_get_temp_dir(), 'audit-import-test-');
$directory = sys_get_temp_dir() . '/audit-saved-test-' . bin2hex(random_bytes(6));
mkdir($directory);
$savedName = 'audit-backup-2020-01-01-080000-0123456789abcdef.csv.php';
$savedPath = $directory . '/' . $savedName;
try {
    $base = array_fill_keys(AUDIT_IMPORT_COLUMNS, null);
    $base = array_replace($base, ['id'=>1,'user_id'=>8,'username'=>'fixture','user_role'=>'admin','module'=>'users','action_type'=>'update','status'=>'success','affected_table'=>'users','affected_id'=>9,'description'=>"=value,\"quoted\"\nnext line",'old_value'=>'','new_value'=>"'=literal",'ip_address'=>'127.0.0.1','user_agent'=>'\\N','created_at'=>'2020-01-01 08:30:00']);
    writeImportFixture($path, [$base]);
    assertAuditImport(readAuditImport($path)[0] === $base, 'Version 2 did not preserve the original values.');
    file_put_contents($savedPath, AUDIT_BACKUP_GUARD . file_get_contents($path));
    assertAuditImport(readAuditImport(auditSavedBackupPath($savedName, $directory), true)[0] === $base, 'Saved backup could not be read safely.');
    foreach (['../' . $savedName, $savedName . '.partial.php', 'arbitrary.php'] as $invalidName) {
        $rejected = false;
        try { auditSavedBackupPath($invalidName, $directory); } catch (InvalidArgumentException $e) { $rejected = true; }
        assertAuditImport($rejected, 'Unsafe saved backup filename was accepted.');
    }
    file_put_contents($savedPath, 'invalid guard'); rejectImport($savedPath, true);
    writeImportFixture($path, [$base], false);
    $legacy = readAuditImport($path)[0];
    assertAuditImport($legacy['description'] === $base['description'] && $legacy['created_at'] === $base['created_at'], 'Legacy backup could not be read.');
    foreach (['action_type'=>'run_sql','created_at'=>'2026-02-30 00:00:00','id'=>'1); DELETE FROM users','username'=>str_repeat('a',51)] as $key=>$value) {
        writeImportFixture($path, [array_replace($base, [$key=>$value])]); rejectImport($path);
    }
    file_put_contents($path, "Name,Action\nAdmin,login\n"); rejectImport($path);
    writeImportFixture($path, [$base,$base]); rejectImport($path);
    $pdo = new ImportTestPDO();
    $definitions = array_map(static fn($column) => $column === 'id' ? 'id INTEGER PRIMARY KEY AUTOINCREMENT' : $column . ' TEXT', AUDIT_IMPORT_COLUMNS);
    $pdo->exec('CREATE TABLE audit_logs (' . implode(',', $definitions) . ')');
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)'); $pdo->exec('INSERT INTO users VALUES (8)');
    $missingUser = array_replace($base, ['id'=>2,'user_id'=>999,'username'=>'Deleted account']);
    $conflict = array_replace($base, ['id'=>3,'description'=>'Backup value']);
    $pdo->exec("INSERT INTO audit_logs (id,description) VALUES (3,'Keep existing value')");
    $rows = [$base,$missingUser,$conflict];
    writeImportFixture($path, $rows);
    file_put_contents($savedPath, AUDIT_BACKUP_GUARD . file_get_contents($path));
    $savedHash = hash_file('sha256', $savedPath);
    $rows = readAuditImport(auditSavedBackupPath($savedName, $directory), true);
    $preview = analyzeAuditImport($pdo,$rows);
    assertAuditImport($preview['restore'] === 2 && $preview['conflicts'] === 1 && $preview['missing_users'] === 1, 'Preview counts incorrect.');
    $result = restoreAuditImport($pdo,$rows,['id'=>8,'username'=>'fixture'],'fixture.csv',str_repeat('a',64));
    assertAuditImport($result['restore'] === 2 && $result['conflicts'] === 1, 'Restore counts incorrect.');
    assertAuditImport(is_file($savedPath) && hash_file('sha256', $savedPath) === $savedHash, 'Restore changed the saved backup.');
    assertAuditImport($pdo->query('SELECT description FROM audit_logs WHERE id=3')->fetchColumn() === 'Keep existing value', 'Conflicting log was overwritten.');
    $restored = $pdo->query('SELECT * FROM audit_logs WHERE id=2')->fetch(PDO::FETCH_ASSOC);
    assertAuditImport($restored['user_id'] === null && $restored['username'] === 'Deleted account' && $restored['created_at'] === $base['created_at'], 'Missing user history was lost.');
    $again = analyzeAuditImport($pdo,$rows);
    assertAuditImport($again['restore'] === 0 && $again['duplicates'] === 2 && $again['conflicts'] === 1, 'Reimport duplicated history.');
    $GLOBALS['failImportAudit'] = true;
    try { restoreAuditImport($pdo,[array_replace($base,['id'=>99])],['id'=>8,'username'=>'fixture'],'fixture.csv','hash'); }
    catch (RuntimeException $e) {}
    assertAuditImport((int)$pdo->query('SELECT COUNT(*) FROM audit_logs WHERE id=99')->fetchColumn() === 0 && !$pdo->inTransaction(), 'Failed restore was not rolled back.');
    echo "Audit import tests passed: uploaded and protected saved CSV, safe filenames, retained backups, validation, duplicates, conflicts, missing users, restoration, and rollback.\n";
} finally { unlink($path); if (is_file($savedPath)) unlink($savedPath); rmdir($directory); }
