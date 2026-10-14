<?php
require_once __DIR__ . '/../includes/audit_backup_helper.php';
function historyAssert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function logAudit(...$args): bool { return $GLOBALS['allowHistoryAudit'] ?? true; }
class HistoryTestPDO extends PDO { public function __construct() {} }
$directory = sys_get_temp_dir() . '/audit-history-test-' . bin2hex(random_bytes(8));
mkdir($directory);
$name = 'audit-backup-2020-01-01-000000-0123456789abcdef.csv.php';
$path = $directory . '/' . $name;
$csv = "\xEF\xBB\xBFid,description,created_at\n1,PRIVATE_FIXTURE,\"2020-01-01 00:00:00\"\n";
$actor = ['id'=>1,'username'=>'fixture']; $pdo = new HistoryTestPDO();
try {
    file_put_contents($path, AUDIT_BACKUP_GUARD . $csv);
    $history = registerAuditBackupHistory($name, $directory);
    historyAssert($history['count'] === 1 && $history['csv_sha256'] === hash('sha256',$csv), 'History fingerprint/count incorrect.');
    historyAssert(!str_contains(file_get_contents(auditBackupHistoryPath($name,$directory)), 'PRIVATE_FIXTURE'), 'History retained log content.');
    historyAssert(listAuditBackupHistory($directory)[0]['has_server_copy'] === true, 'Pending copy not indicated.');
    $GLOBALS['allowHistoryAudit'] = false;
    try { confirmAuditBackupSaved($pdo,$name,$actor,$directory); } catch (RuntimeException $e) {}
    historyAssert(is_file($path), 'Server copy removed before audit confirmation succeeded.');
    $GLOBALS['allowHistoryAudit'] = true;
    confirmAuditBackupSaved($pdo,$name,$actor,$directory);
    historyAssert(!is_file($path), 'Server backup data still exists after confirmation.');
    $historyOnly = listAuditBackupHistory($directory);
    historyAssert(count($historyOnly) === 1 && !$historyOnly[0]['has_server_copy'] && $historyOnly[0]['count'] === 1, 'History disappeared with backup data.');
    historyAssert($historyOnly[0]['last_restore'] === null, 'An unused backup was marked restored.');
    $historyDb = new PDO('sqlite::memory:');
    $historyDb->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY, module TEXT, action_type TEXT, new_value TEXT, created_at TEXT)');
    $event = ['file'=>substr($name,0,-4), 'sha256'=>hash('sha256',$csv), 'total'=>1,'restore'=>1,'duplicates'=>0,'conflicts'=>0];
    $historyDb->prepare('INSERT INTO audit_logs VALUES (1,?,?,?,?)')->execute(['audit_logs','create',json_encode($event),'2026-10-14 20:30:00']);
    $recovered = listAuditBackupHistory($directory, $historyDb)[0]['last_restore'];
    historyAssert($recovered['status'] === 'restored' && $recovered['at'] === '2026-10-14 20:30:00' && $recovered['restored'] === 1, 'Earlier restoration event was not recognized.');
    recordAuditBackupRestoration($name, ['total'=>3,'restore'=>1,'duplicates'=>1,'conflicts'=>1], $directory);
    $partial = listAuditBackupHistory($directory)[0]['last_restore'];
    historyAssert($partial['status'] === 'partial' && $partial['duplicates'] === 1 && $partial['conflicts'] === 1, 'Partial restoration counts were not saved.');
    recordAuditBackupRestoration($name, ['total'=>3,'restore'=>2,'duplicates'=>1,'conflicts'=>0], $directory);
    $complete = listAuditBackupHistory($directory)[0]['last_restore'];
    historyAssert($complete['status'] === 'restored' && $complete['restored'] === 2 && !empty($complete['at']), 'Latest restoration did not update the status.');
    historyAssert(!is_file($path), 'Status tracking recreated the server CSV.');
    verifyAuditHistoryCsv($name,hash('sha256',$csv),$directory);
    $rejected = false;
    try { verifyAuditHistoryCsv($name,hash('sha256',$csv . 'changed'),$directory); } catch (InvalidArgumentException $e) { $rejected = true; }
    historyAssert($rejected, 'Changed/wrong CSV was accepted for history restoration.');
    confirmAuditBackupSaved($pdo,$name,$actor,$directory);
    $rejected = false;
    try { confirmAuditBackupSaved($pdo,'../' . $name,$actor,$directory); } catch (InvalidArgumentException $e) { $rejected = true; }
    historyAssert($rejected, 'Unsafe backup path was accepted.');
    echo "Backup history tests passed: legacy registration, metadata only, audit failure protection, explicit copy removal, retained history, CSV fingerprint, repeat confirmation, and path safety.\n";
} finally { foreach (glob($directory . '/*') as $file) unlink($file); rmdir($directory); }
