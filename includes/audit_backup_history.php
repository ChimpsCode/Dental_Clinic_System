<?php
// Small protected manifests contain backup identity/counts, never audit rows.
function auditBackupHistoryPath(string $name, string $directory): string {
    if (!preg_match('/^audit-backup-\d{4}-\d{2}-\d{2}-\d{6}-[a-f0-9]{16}\.csv\.php$/D', $name)) {
        throw new InvalidArgumentException('Invalid backup history entry.');
    }
    return $directory . '/' . $name . '.meta.php';
}

function writeAuditBackupHistory(string $name, array $metadata, ?string $directory = null): void {
    $directory = $directory ?? auditBackupDirectory();
    $path = auditBackupHistoryPath($name, $directory);
    if (is_link($path)) throw new RuntimeException('Backup history is unavailable.');
    $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.partial.php';
    $payload = AUDIT_BACKUP_GUARD . json_encode($metadata, JSON_THROW_ON_ERROR);
    if (file_put_contents($temporary, $payload, LOCK_EX) !== strlen($payload)) throw new RuntimeException('Could not save backup history.');
    if (!rename($temporary, $path)) { unlink($temporary); throw new RuntimeException('Could not finalize backup history.'); }
}

function readAuditBackupHistory(string $name, ?string $directory = null): array {
    $directory = $directory ?? auditBackupDirectory();
    $path = auditBackupHistoryPath($name, $directory);
    if (!is_file($path) || is_link($path)) throw new InvalidArgumentException('Backup history not found.');
    $contents = file_get_contents($path);
    if (!str_starts_with((string)$contents, AUDIT_BACKUP_GUARD)) throw new RuntimeException('Invalid backup history.');
    $metadata = json_decode(substr($contents, strlen(AUDIT_BACKUP_GUARD)), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($metadata) || ($metadata['file'] ?? '') !== $name || !preg_match('/^[a-f0-9]{64}$/D', (string)($metadata['csv_sha256'] ?? ''))) {
        throw new RuntimeException('Invalid backup history.');
    }
    return $metadata;
}

function auditBackupCsvHash(string $path): string {
    $stream = fopen($path, 'rb');
    if (!$stream) throw new RuntimeException('Backup could not be read.');
    try {
        if (fread($stream, strlen(AUDIT_BACKUP_GUARD)) !== AUDIT_BACKUP_GUARD) throw new InvalidArgumentException('Invalid saved backup.');
        $hash = hash_init('sha256'); hash_update_stream($hash, $stream);
        return hash_final($hash);
    } finally { fclose($stream); }
}

function verifyAuditHistoryCsv(string $name, string $hash, ?string $directory = null): array {
    $metadata = readAuditBackupHistory($name, $directory);
    if (!hash_equals($metadata['csv_sha256'], $hash)) {
        throw new InvalidArgumentException('This CSV does not match the selected backup history entry. Select the original downloaded file.');
    }
    return $metadata;
}

// Register backups made by earlier versions without removing any data.
function registerAuditBackupHistory(string $name, ?string $directory = null): array {
    $directory = $directory ?? auditBackupDirectory();
    if (is_file(auditBackupHistoryPath($name, $directory))) return readAuditBackupHistory($name, $directory);
    $path = auditSavedBackupPath($name, $directory);
    $hash = auditBackupCsvHash($path);
    $stream = fopen($path, 'rb');
    if (!$stream) throw new RuntimeException('Backup could not be read.');
    $count = 0; $oldest = null; $newest = null;
    try {
        fseek($stream, strlen(AUDIT_BACKUP_GUARD));
        if (fread($stream, 3) !== "\xEF\xBB\xBF") fseek($stream, strlen(AUDIT_BACKUP_GUARD));
        $header = fgetcsv($stream, null, ',', '"', '');
        if ($header === ['#dental-audit-backup', '2']) $header = fgetcsv($stream, null, ',', '"', '');
        if (!is_array($header) || !in_array('id', $header, true) || !in_array('created_at', $header, true)) throw new InvalidArgumentException('Invalid saved backup columns.');
        $dateIndex = array_search('created_at', $header, true);
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            if ($row === [null]) continue;
            if (count($row) !== count($header)) throw new InvalidArgumentException('Invalid saved backup record.');
            $count++; $date = $row[$dateIndex];
            $oldest = $oldest === null || $date < $oldest ? $date : $oldest;
            $newest = $newest === null || $date > $newest ? $date : $newest;
        }
    } finally { fclose($stream); }
    $metadata = ['file'=>$name, 'count'=>$count, 'created_at'=>date('Y-m-d H:i:s', filemtime($path)),
        'oldest'=>$oldest, 'newest'=>$newest, 'csv_sha256'=>$hash, 'confirmed_at'=>null];
    writeAuditBackupHistory($name, $metadata, $directory);
    return $metadata;
}

function auditBackupRestorationSummary(array $result, string $at): array {
    return ['at'=>$at, 'status'=>(int)($result['conflicts'] ?? 0) > 0 ? 'partial' : 'restored',
        'restored'=>(int)($result['restore'] ?? 0), 'duplicates'=>(int)($result['duplicates'] ?? 0),
        'conflicts'=>(int)($result['conflicts'] ?? 0), 'total'=>(int)($result['total'] ?? 0)];
}

function recordAuditBackupRestoration(string $name, array $result, ?string $directory = null): void {
    $metadata = readAuditBackupHistory($name, $directory);
    $metadata['last_restore'] = auditBackupRestorationSummary($result, date('Y-m-d H:i:s'));
    writeAuditBackupHistory($name, $metadata, $directory);
}

function recoverAuditBackupRestoration(PDO $pdo, string $name, array $metadata, string $directory): array {
    // Recognize restores performed before the history-status feature was added.
    if (isset($metadata['last_restore'])) return $metadata;
    $stmt = $pdo->prepare("SELECT new_value, created_at FROM audit_logs WHERE module = 'audit_logs' AND action_type = 'create' AND (new_value LIKE ? OR new_value LIKE ?) ORDER BY created_at DESC, id DESC LIMIT 1");
    $stmt->execute(['%"file":' . json_encode(substr($name, 0, -4)) . '%', '%"sha256":' . json_encode($metadata['csv_sha256']) . '%']);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    $details = $event ? json_decode((string)$event['new_value'], true) : null;
    if (is_array($details) && isset($details['restore'], $details['total'], $details['duplicates'], $details['conflicts'])) {
        $metadata['last_restore'] = auditBackupRestorationSummary($details, $event['created_at']);
        try { writeAuditBackupHistory($name, $metadata, $directory); }
        catch (Throwable $e) { error_log('Could not save recovered backup status: ' . $e->getMessage()); }
    }
    return $metadata;
}

function listAuditBackupHistory(?string $directory = null, ?PDO $pdo = null): array {
    $directory = $directory ?? auditBackupDirectory();
    foreach (glob($directory . '/audit-backup-*.csv.php') ?: [] as $path) registerAuditBackupHistory(basename($path), $directory);
    $files = glob($directory . '/audit-backup-*.csv.php.meta.php') ?: [];
    rsort($files); $result = [];
    foreach ($files as $path) {
        $name = substr(basename($path), 0, -strlen('.meta.php'));
        $metadata = readAuditBackupHistory($name, $directory);
        if ($pdo !== null) $metadata = recoverAuditBackupRestoration($pdo, $name, $metadata, $directory);
        $result[] = ['file'=>$name, 'label'=>substr($name,0,-4), 'count'=>(int)$metadata['count'],
            'created_at'=>$metadata['created_at'], 'has_server_copy'=>is_file($directory . '/' . $name),
            'last_restore'=>$metadata['last_restore'] ?? null];
    }
    return $result;
}

function confirmAuditBackupSaved(PDO $pdo, string $name, array $actor, ?string $directory = null): void {
    $directory = $directory ?? auditBackupDirectory();
    $metadata = registerAuditBackupHistory($name, $directory);
    $path = $directory . '/' . $name;
    if (!is_file($path)) return; // A repeated confirmation is safe.
    $path = auditSavedBackupPath($name, $directory);
    if (!hash_equals($metadata['csv_sha256'], auditBackupCsvHash($path))) throw new RuntimeException('The server backup changed. Nothing was removed.');
    if (!logAudit($pdo, $actor['id'], $actor['username'], 'admin', 'delete', 'audit_logs',
        'Admin confirmed a local CSV copy and authorized removal of the server audit backup: ' . substr($name, 0, -4),
        null, 'audit_logs', null, json_encode(['backup'=>$name,'count'=>$metadata['count'],'csv_sha256'=>$metadata['csv_sha256']]))) {
        throw new RuntimeException('Could not record confirmation. Nothing was removed.');
    }
    $metadata['confirmed_at'] = date('Y-m-d H:i:s');
    writeAuditBackupHistory($name, $metadata, $directory);
    // Only this validated backup is removed; its manifest remains for recovery.
    if (!unlink($path)) throw new RuntimeException('Could not remove the server copy. Please try confirmation again.');
}
