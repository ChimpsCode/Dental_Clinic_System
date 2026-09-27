<?php
/**
 * Clinic database backups stored under storage/backups.
 * Direct web access is blocked by that folder's .htaccess.
 */

function backupStorageDir(): string
{
    return dirname(__DIR__) . '/storage/backups';
}

function ensureBackupStorage(): string
{
    $dir = backupStorageDir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function normalizeBackupMonths($months): int
{
    $months = (int)$months;
    return in_array($months, [1, 3, 6, 12], true) ? $months : 12;
}

function formatBackupSize(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return number_format($bytes / 1048576, 1) . ' MB';
}

function formatBackupTimestamp(int $timestamp): string
{
    return date('F j, Y g:i A', $timestamp);
}

function latestBackupInfo(): ?array
{
    $dir = backupStorageDir();
    if (!is_dir($dir)) {
        return null;
    }

    $files = glob($dir . '/clinic_backup_*.sql') ?: [];
    if (!$files) {
        return null;
    }

    usort($files, function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    $path = $files[0];
    $bytes = (int)filesize($path);
    $mtime = (int)filemtime($path);

    return [
        'path' => $path,
        'filename' => basename($path),
        'bytes' => $bytes,
        'size_label' => formatBackupSize($bytes),
        'created_label' => formatBackupTimestamp($mtime),
    ];
}

function pruneOldBackups(int $keep = 5): void
{
    $dir = backupStorageDir();
    $files = glob($dir . '/clinic_backup_*.sql') ?: [];
    if (count($files) <= $keep) {
        return;
    }

    usort($files, function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    foreach (array_slice($files, $keep) as $old) {
        @unlink($old);
    }
}

function backupQuoteIdent(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function backupColumnIsBinary(string $type): bool
{
    return (bool)preg_match('/\b(blob|binary|varbinary)\b/i', $type);
}

function backupSqlLiteral(PDO $pdo, $value, bool $binary): string
{
    if ($value === null) {
        return 'NULL';
    }
    if ($binary) {
        return '0x' . bin2hex((string)$value);
    }
    $quoted = $pdo->quote((string)$value);
    if ($quoted === false) {
        throw new RuntimeException('Could not encode a backup value.');
    }
    return $quoted;
}

/**
 * @return array{path:string,filename:string,bytes:int,size_label:string,created_label:string}
 */
function createDatabaseBackup(PDO $pdo, int $months): array
{
    $months = normalizeBackupMonths($months);
    $dir = ensureBackupStorage();
    $filename = 'clinic_backup_' . date('Y-m-d_His') . '.sql';
    $final = $dir . '/' . $filename;
    $tmp = $final . '.tmp';

    if ($final === $tmp) {
        throw new RuntimeException('Could not prepare the backup file.');
    }

    $handle = fopen($tmp, 'wb');
    if ($handle === false) {
        throw new RuntimeException('Could not create the backup file.');
    }

    try {
        $header = "-- Dental clinic backup\n"
            . '-- Created: ' . date('Y-m-d H:i:s') . "\n"
            . '-- Activity history included: ' . $months . ' ' . ($months === 1 ? "month\n" : "months\n")
            . "-- Other tables: full copy\n\n"
            . "SET NAMES utf8mb4;\n"
            . "SET FOREIGN_KEY_CHECKS=0;\n\n";
        fwrite($handle, $header);

        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
        foreach ($tables as $tableRow) {
            $table = (string)($tableRow[0] ?? '');
            if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                continue;
            }
            writeBackupTable($pdo, $handle, $table, $months);
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);
        $handle = null;

        if (!rename($tmp, $final)) {
            throw new RuntimeException('Could not save the backup file.');
        }

        pruneOldBackups(5);

        $bytes = (int)filesize($final);
        return [
            'path' => $final,
            'filename' => basename($final),
            'bytes' => $bytes,
            'size_label' => formatBackupSize($bytes),
            'created_label' => formatBackupTimestamp((int)filemtime($final)),
        ];
    } catch (Throwable $e) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        if (is_file($tmp)) {
            @unlink($tmp);
        }
        throw $e;
    }
}

function writeBackupTable(PDO $pdo, $handle, string $table, int $months): void
{
    $ident = backupQuoteIdent($table);
    $create = $pdo->query('SHOW CREATE TABLE ' . $ident)->fetch(PDO::FETCH_ASSOC);
    $createSql = $create['Create Table'] ?? '';
    if ($createSql === '') {
        throw new RuntimeException('Could not read table ' . $table . '.');
    }

    fwrite($handle, 'DROP TABLE IF EXISTS ' . $ident . ";\n");
    fwrite($handle, $createSql . ";\n\n");

    $columns = $pdo->query('SHOW COLUMNS FROM ' . $ident)->fetchAll(PDO::FETCH_ASSOC);
    if (!$columns) {
        return;
    }

    $names = [];
    $binary = [];
    foreach ($columns as $column) {
        $field = (string)$column['Field'];
        $names[] = $field;
        $binary[$field] = backupColumnIsBinary((string)$column['Type']);
    }

    $select = 'SELECT * FROM ' . $ident;
    if ($table === 'audit_logs') {
        $select .= ' WHERE `created_at` >= DATE_SUB(NOW(), INTERVAL ' . $months . ' MONTH)';
    }

    $stmt = $pdo->query($select);
    $columnList = implode(', ', array_map('backupQuoteIdent', $names));
    $batch = [];

    $flush = function () use (&$batch, $handle, $ident, $columnList) {
        if (!$batch) {
            return;
        }
        fwrite($handle, 'INSERT INTO ' . $ident . ' (' . $columnList . ") VALUES\n" . implode(",\n", $batch) . ";\n");
        $batch = [];
    };

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $values = [];
        foreach ($names as $name) {
            $values[] = backupSqlLiteral($pdo, $row[$name] ?? null, $binary[$name]);
        }
        $batch[] = '(' . implode(', ', $values) . ')';
        if (count($batch) >= 50) {
            $flush();
        }
    }
    $flush();
    fwrite($handle, "\n");
}

function backupLockHandle()
{
    $dir = ensureBackupStorage();
    $handle = fopen($dir . '/.backup.lock', 'c');
    if ($handle === false) {
        throw new RuntimeException('Could not lock the backup folder.');
    }
    return $handle;
}

/**
 * Slot id when an automatic backup should run now, or null when it should not.
 */
function backupSlotIfDue(array $settings, DateTimeInterface $now): ?string
{
    if (($settings['auto_backup_enabled'] ?? '0') !== '1') {
        return null;
    }

    $time = (string)($settings['auto_backup_time'] ?? '02:00');
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
        $time = '02:00';
    }

    $mode = (($settings['auto_backup_mode'] ?? 'month') === 'date') ? 'date' : 'month';
    $zone = $now->getTimezone();

    if ($mode === 'date') {
        $date = (string)($settings['auto_backup_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        $scheduled = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time, $zone);
        $parsed = DateTime::getLastErrors();
        if (!$scheduled || ($parsed && (($parsed['warning_count'] ?? 0) > 0 || ($parsed['error_count'] ?? 0) > 0)) || $now < $scheduled) {
            return null;
        }
        $slot = 'date:' . $scheduled->format('Y-m-d H:i');
    } else {
        $day = (int)($settings['auto_backup_day'] ?? 1);
        if ($day < 1 || $day > 28) {
            $day = 1;
        }
        $scheduled = DateTime::createFromFormat('Y-m-d H:i', $now->format('Y-m-') . sprintf('%02d', $day) . ' ' . $time, $zone);
        $parsed = DateTime::getLastErrors();
        if (!$scheduled || ($parsed && (($parsed['warning_count'] ?? 0) > 0 || ($parsed['error_count'] ?? 0) > 0)) || $now < $scheduled) {
            return null;
        }
        $slot = 'month:' . $scheduled->format('Y-m-d H:i');
    }

    if (($settings['auto_backup_last_slot'] ?? '') === $slot) {
        return null;
    }

    return $slot;
}

function loadAutoBackupSettings(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('auto_backup_enabled', 'auto_backup_time', 'auto_backup_mode', 'auto_backup_date', 'auto_backup_day', 'auto_backup_last_slot')");
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    return is_array($rows) ? $rows : [];
}

function saveBackupSetting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$key, $value]);
}

function runAutoBackupIfDue(PDO $pdo): bool
{
    try {
        if (!backupSlotIfDue(loadAutoBackupSettings($pdo), new DateTime('now'))) {
            return false;
        }

        $lock = backupLockHandle();
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return false;
        }

        try {
            $settings = loadAutoBackupSettings($pdo);
            $slot = backupSlotIfDue($settings, new DateTime('now'));
            if ($slot === null) {
                return false;
            }

            set_time_limit(120);
            $info = createDatabaseBackup($pdo, 12);
            saveBackupSetting($pdo, 'auto_backup_last_slot', $slot);

            if (!function_exists('logAudit')) {
                require_once __DIR__ . '/audit_helper.php';
            }
            logAudit(
                $pdo,
                null,
                'system',
                'admin',
                'create',
                'settings',
                'Automatic clinic backup created (' . $info['filename'] . ', ' . $info['size_label'] . ')'
            );

            return true;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    } catch (Throwable $e) {
        error_log('Auto backup error: ' . $e->getMessage());
        return false;
    }
}
