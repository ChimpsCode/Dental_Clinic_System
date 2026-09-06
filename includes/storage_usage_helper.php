<?php
/**
 * Storage usage helper for the admin sidebar widget.
 *
 * Reports how much of the hosting allowance the system occupies: the size of
 * every deployed file under the project root plus the MySQL database size,
 * measured against a fixed quota that matches the InfinityFree free plan.
 */

// Hosting allowance (InfinityFree free plan: 3 GB).
if (!defined('STORAGE_QUOTA_BYTES')) {
    define('STORAGE_QUOTA_BYTES', 3 * 1024 * 1024 * 1024);
}

// How long a measurement stays valid before the files are walked again.
if (!defined('STORAGE_USAGE_TTL')) {
    define('STORAGE_USAGE_TTL', 900);
}

// Directories that never reach the host, so they must not count against the quota.
if (!defined('STORAGE_SKIP_DIRS')) {
    define('STORAGE_SKIP_DIRS', '.git,.opencode,node_modules');
}

/**
 * Formats a byte count using the largest unit that keeps the number readable.
 *
 * @param  int|float $bytes
 * @return string
 */
function formatStorageSize($bytes) {
    $bytes = (float)$bytes;

    if ($bytes >= 1024 * 1024 * 1024) {
        return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return (int)$bytes . ' B';
}

/**
 * Sums the size of every file under the project root, skipping directories
 * that are not deployed to the host.
 *
 * @return int Total bytes
 */
function measureProjectFileBytes() {
    $root = dirname(__DIR__);
    $skip = array_filter(array_map('trim', explode(',', STORAGE_SKIP_DIRS)));
    $total = 0;

    $flags = FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS;
    $directory = new RecursiveDirectoryIterator($root, $flags);
    $filter = new RecursiveCallbackFilterIterator($directory, function ($current) use ($skip) {
        if ($current->isDir()) {
            return !in_array($current->getFilename(), $skip, true);
        }
        return true;
    });

    $iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $size = @$file->getSize();
            if ($size !== false) {
                $total += $size;
            }
        }
    }

    return $total;
}

/**
 * Reads the size of the current database from information_schema.
 *
 * @param  PDO|null $pdo
 * @return int Total bytes, or 0 when unavailable
 */
function measureDatabaseBytes($pdo) {
    if (!($pdo instanceof PDO)) {
        return 0;
    }

    try {
        $stmt = $pdo->query("SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.TABLES WHERE table_schema = DATABASE()");
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log('Storage usage: database size query failed - ' . $e->getMessage());
        return 0;
    }
}

/**
 * Returns the system's storage usage against the hosting quota.
 *
 * The measurement is cached on disk so a full file walk only happens once per
 * STORAGE_USAGE_TTL window instead of on every page load.
 *
 * @param  PDO|null $pdo
 * @param  bool     $forceRefresh Recompute even if a fresh cache exists
 * @return array|null Usage details, or null when nothing could be measured
 */
function getStorageUsage($pdo = null, $forceRefresh = false) {
    $cacheFile = dirname(__DIR__) . '/cache/storage_usage.json';
    $cached = null;

    if (is_readable($cacheFile)) {
        $raw = @file_get_contents($cacheFile);
        $decoded = $raw === false ? null : json_decode($raw, true);
        if (is_array($decoded) && isset($decoded['total_bytes'], $decoded['computed_at'])) {
            $cached = $decoded;
            $isFresh = (time() - (int)$decoded['computed_at']) < STORAGE_USAGE_TTL;
            $sameQuota = (int)($decoded['quota_bytes'] ?? 0) === STORAGE_QUOTA_BYTES;
            if (!$forceRefresh && $isFresh && $sameQuota) {
                return $cached;
            }
        }
    }

    // Bump the cache timestamp before the walk so parallel requests keep using
    // the previous result instead of all scanning at once.
    if ($cached !== null) {
        @touch($cacheFile);
    }

    try {
        $fileBytes = measureProjectFileBytes();
    } catch (Exception $e) {
        error_log('Storage usage: file scan failed - ' . $e->getMessage());
        return $cached;
    }

    $dbBytes = measureDatabaseBytes($pdo);
    $totalBytes = $fileBytes + $dbBytes;

    $usedPct = STORAGE_QUOTA_BYTES > 0 ? (int)round($totalBytes / STORAGE_QUOTA_BYTES * 100) : 0;
    $usedPct = max(0, min(100, $usedPct));
    if ($usedPct < 1 && $totalBytes > 0) {
        $usedPct = 1;
    }

    $usage = [
        'files_bytes' => $fileBytes,
        'db_bytes' => $dbBytes,
        'total_bytes' => $totalBytes,
        'quota_bytes' => STORAGE_QUOTA_BYTES,
        'used_pct' => $usedPct,
        'computed_at' => time()
    ];

    $cacheDir = dirname($cacheFile);
    if (is_dir($cacheDir)) {
        @file_put_contents($cacheFile, json_encode($usage), LOCK_EX);
    }

    return $usage;
}
