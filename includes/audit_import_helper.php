<?php
require_once __DIR__ . '/audit_backup_helper.php';

const AUDIT_IMPORT_MAX_BYTES = 5 * 1024 * 1024;
const AUDIT_IMPORT_MAX_ROWS = 10000;
const AUDIT_IMPORT_COLUMNS = ['id', 'user_id', 'username', 'user_role', 'module', 'action_type', 'status', 'affected_table', 'affected_id', 'description', 'old_value', 'new_value', 'ip_address', 'user_agent', 'created_at'];

function auditImportDecode(?string $value, bool $version2): ?string {
    if ($value === null) $value = '';
    if ($version2 && $value === '\\N') return null;
    if ($version2 && str_starts_with($value, '\\\\')) return substr($value, 1);
    if ($version2 && str_starts_with($value, "''")) return substr($value, 1);
    if (str_starts_with($value, "'") && preg_match('/^[\s\x00-\x1f]*[=+@-]/', substr($value, 1))) return substr($value, 1);
    return $value;
}

function readAuditImport(string $path, bool $savedBackup = false): array {
    if (!is_file($path) || filesize($path) === 0 || filesize($path) > AUDIT_IMPORT_MAX_BYTES) {
        throw new InvalidArgumentException('Choose a backup CSV no larger than 5 MB.');
    }
    $file = fopen($path, 'rb');
    if (!$file) throw new InvalidArgumentException('Could not read the uploaded CSV.');
    try {
        if ($savedBackup && fread($file, strlen(AUDIT_BACKUP_GUARD)) !== AUDIT_BACKUP_GUARD) {
            throw new InvalidArgumentException('The saved backup is invalid.');
        }
        $csvStart = ftell($file);
        $prefix = fread($file, 3);
        if ($prefix !== "\xEF\xBB\xBF") fseek($file, $csvStart);
        $header = fgetcsv($file, null, ',', '"', '');
        $version2 = $header === ['#dental-audit-backup', '2'];
        if ($version2) $header = fgetcsv($file, null, ',', '"', '');
        if (!is_array($header) || count($header) !== count(AUDIT_IMPORT_COLUMNS)
            || array_diff(AUDIT_IMPORT_COLUMNS, $header) || array_diff($header, AUDIT_IMPORT_COLUMNS)) {
            throw new InvalidArgumentException('Use the full CSV from “Back up & delete old logs”. Report exports are not restore backups.');
        }
        $rows = []; $seen = []; $line = 0;
        $limits = ['username' => 50, 'user_role' => 50, 'module' => 50, 'status' => 20, 'affected_table' => 50, 'ip_address' => 45];
        while (($cells = fgetcsv($file, null, ',', '"', '')) !== false) {
            if ($cells === [null]) continue;
            if (++$line > AUDIT_IMPORT_MAX_ROWS) throw new InvalidArgumentException('Restore at most 10,000 logs per file.');
            if (count($cells) !== count($header)) throw new InvalidArgumentException("Record {$line} has an invalid number of columns.");
            $row = array_combine($header, array_map(static fn($cell) => auditImportDecode($cell, $version2), $cells));
            foreach ($row as $key => $value) {
                if ($value !== null && (str_contains($value, "\0") || !preg_match('//u', $value))) throw new InvalidArgumentException("Record {$line} contains invalid text.");
                if (isset($limits[$key]) && $value !== null && mb_strlen($value, 'UTF-8') > $limits[$key]) throw new InvalidArgumentException("Record {$line}: {$key} is too long.");
                if (in_array($key, ['description', 'old_value', 'new_value', 'user_agent'], true) && strlen((string)$value) > 65535) throw new InvalidArgumentException("Record {$line}: {$key} is too long.");
            }
            foreach (['id', 'user_id', 'affected_id'] as $key) {
                if ($key !== 'id' && ($row[$key] === null || $row[$key] === '')) { $row[$key] = null; continue; }
                if (!preg_match('/^\d+$/D', (string)$row[$key]) || (int)$row[$key] > 2147483647 || ($key === 'id' && (int)$row[$key] <= 0)) throw new InvalidArgumentException("Record {$line}: invalid {$key}.");
                $row[$key] = (int)$row[$key];
            }
            if ($row['action_type'] === '') $row['action_type'] = null;
            if ($row['action_type'] !== null && !in_array($row['action_type'], ['login','logout','create','read','update','delete','payment','status_change','failed_login'], true)) throw new InvalidArgumentException("Record {$line} contains an unknown action.");
            $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string)$row['created_at']);
            if (!$date || $date->format('Y-m-d H:i:s') !== $row['created_at']) throw new InvalidArgumentException("Record {$line} has an invalid log date.");
            if (isset($seen[$row['id']])) {
                if ($seen[$row['id']] !== $row) throw new InvalidArgumentException('The CSV contains conflicting records with the same ID.');
                throw new InvalidArgumentException('The CSV contains repeated record IDs. Use the original backup.');
            }
            $seen[$row['id']] = $row; $rows[] = $row;
        }
        if (!$rows) throw new InvalidArgumentException('The backup contains no audit logs.');
        return $rows;
    } finally { fclose($file); }
}

function analyzeAuditImport(PDO $pdo, array $rows, bool $lock = false): array {
    $existing = []; $userIds = []; $validUsers = [];
    foreach (array_chunk(array_column($rows, 'id'), 500) as $ids) {
        $sql = 'SELECT * FROM audit_logs WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        if ($lock) $sql .= ' FOR UPDATE';
        $stmt = $pdo->prepare($sql); $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $existing[(int)$row['id']] = $row;
    }
    foreach ($rows as $row) if ($row['user_id'] !== null) $userIds[] = $row['user_id'];
    foreach (array_chunk(array_values(array_unique($userIds)), 500) as $ids) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) $validUsers[(int)$id] = true;
    }
    $new = []; $duplicates = 0; $conflicts = 0; $missingUsers = 0;
    foreach ($rows as $row) {
        if ($row['user_id'] !== null && !isset($validUsers[$row['user_id']])) { $row['user_id'] = null; $missingUsers++; }
        if (isset($existing[$row['id']])) {
            $same = true;
            foreach (AUDIT_IMPORT_COLUMNS as $column) {
                if ((string)($existing[$row['id']][$column] ?? '') !== (string)($row[$column] ?? '')) { $same = false; break; }
            }
            if ($same) $duplicates++; else $conflicts++;
        } else $new[] = $row;
    }
    $dates = array_column($rows, 'created_at');
    return ['total' => count($rows), 'restore' => count($new), 'duplicates' => $duplicates, 'conflicts' => $conflicts,
        'missing_users' => $missingUsers, 'oldest' => min($dates), 'newest' => max($dates), 'rows' => $new];
}

function restoreAuditImport(PDO $pdo, array $rows, array $actor, string $filename, string $hash): array {
    $engine = $pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs'")->fetchColumn();
    if (strtolower((string)$engine) !== 'innodb') throw new RuntimeException('Restoring logs requires a transactional audit table.');
    try {
        $pdo->beginTransaction();
        $result = analyzeAuditImport($pdo, $rows, true);
        $columns = implode(',', array_map(static fn($column) => '`' . $column . '`', AUDIT_IMPORT_COLUMNS));
        $insert = $pdo->prepare('INSERT INTO audit_logs (' . $columns . ') VALUES (' . implode(',', array_fill(0, count(AUDIT_IMPORT_COLUMNS), '?')) . ')');
        foreach ($result['rows'] as $row) $insert->execute(array_map(static fn($column) => $row[$column], AUDIT_IMPORT_COLUMNS));
        unset($result['rows']);
        if (!logAudit($pdo, $actor['id'], $actor['username'], 'admin', 'create', 'audit_logs',
            "Restored {$result['restore']} audit logs from backup; skipped {$result['duplicates']} duplicates and {$result['conflicts']} ID conflicts.",
            null, 'audit_logs', null, json_encode(['file' => $filename, 'sha256' => $hash] + $result, JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('Could not record the restoration.');
        }
        $pdo->commit(); return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
