<?php
/**
 * Simple IP + bucket rate limiter using cache files.
 * Returns true when the request is allowed.
 */
function rate_limit_allow(string $bucket, int $maxHits, int $windowSeconds): bool
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'rate_limit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        return true;
    }

    $file = $dir . DIRECTORY_SEPARATOR . hash('sha256', $bucket . '|' . $ip) . '.json';
    $now = time();
    $hits = [];

    $fh = @fopen($file, 'c+');
    if ($fh === false) {
        return true;
    }

    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        return true;
    }

    $raw = stream_get_contents($fh);
    $decoded = json_decode((string)$raw, true);
    if (is_array($decoded)) {
        $hits = $decoded;
    }

    $hits = array_values(array_filter($hits, static function ($t) use ($now, $windowSeconds) {
        return is_numeric($t) && (int)$t > ($now - $windowSeconds);
    }));

    if (count($hits) >= $maxHits) {
        flock($fh, LOCK_UN);
        fclose($fh);
        return false;
    }

    $hits[] = $now;
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($hits));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);

    return true;
}
