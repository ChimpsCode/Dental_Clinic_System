<?php
/** Patient test-SMS quota, persisted per account and locked across sessions. */
function patientSmsTestQuotaState(array $sends, int $now): array
{
    $today = (new DateTimeImmutable('@' . $now))
        ->setTimezone(new DateTimeZone('Asia/Manila'))->setTime(0, 0);
    $start = $today->getTimestamp();
    $reset = $today->modify('+1 day')->getTimestamp();
    $count = count(array_filter($sends, static fn($sent) => $sent >= $start && $sent < $reset));
    $last = $sends ? max($sends) : 0;
    $next = $last ? $last + 300 : 0;
    $reason = 'ready';
    $message = '';
    if ($count >= 2) {
        $reason = 'daily_limit';
        $next = max($next, $reset);
        $message = 'You have used both test SMS for today. Please try again tomorrow.';
    } elseif ($next > $now) {
        $reason = 'cooldown';
        $message = 'Please wait ' . (int)ceil(($next - $now) / 60) . ' minute(s) before sending another test SMS.';
    }
    return [
        'can_send' => $reason === 'ready',
        'remaining' => max(0, 2 - $count),
        'retry_after' => max(0, $next - $now),
        'reset_after' => $reset - $now,
        'reason' => $reason,
        'message' => $message,
    ];
}

function getPatientSmsTestQuota(int $userId): array
{
    return applyPatientSmsTestQuota($userId, null);
}

function sendPatientSmsTestLimited(int $userId, callable $send): array
{
    return applyPatientSmsTestQuota($userId, $send);
}

function writePatientSmsTestSends($file, array $sends): bool
{
    $contents = implode("\n", $sends) . ($sends ? "\n" : '');
    return rewind($file) && ftruncate($file, 0)
        && fwrite($file, $contents) === strlen($contents) && fflush($file);
}

function applyPatientSmsTestQuota(int $userId, ?callable $send): array
{
    $unavailable = [
        'can_send' => false, 'remaining' => 0, 'retry_after' => 0, 'reset_after' => 0,
        'reason' => 'unavailable', 'sent' => false, 'attempted' => false,
        'message' => 'Test SMS is temporarily unavailable. Please try again later.',
    ];
    $dir = dirname(__DIR__) . '/cache/rate_limit';
    if ($userId <= 0 || (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir))) {
        return $unavailable;
    }
    $file = @fopen($dir . '/sms_test_' . $userId, 'c+');
    if ($file === false) return $unavailable;
    if (!flock($file, LOCK_EX)) {
        fclose($file);
        return $unavailable;
    }
    try {
        $raw = stream_get_contents($file);
        if ($raw === false) return $unavailable;
        // Accept the old single-timestamp format as one previous send.
        $lines = preg_split('/\s+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);
        $sends = [];
        foreach ($lines as $line) {
            if (!ctype_digit($line) || (int)$line <= 0) return $unavailable;
            $sends[] = (int)$line;
        }
        $now = time();
        $quota = patientSmsTestQuotaState($sends, $now);
        $quota['sent'] = false;
        $quota['attempted'] = false;
        if ($send === null || !$quota['can_send']) return $quota;

        // Reserve before contacting the gateway; never send if persistence fails.
        $cutoff = min($now - 300, $now + $quota['reset_after'] - 86400);
        $sends = array_values(array_filter($sends, static fn($sent) => $sent >= $cutoff));
        $reserved = array_merge($sends, [$now]);
        if (!writePatientSmsTestSends($file, $reserved)) return $unavailable;
        try {
            $sent = (bool)$send();
        } catch (Throwable $e) {
            // Delivery is uncertain: retain the reservation to prevent duplicates.
            error_log('Patient SMS test error: ' . $e->getMessage());
            return array_merge(patientSmsTestQuotaState($reserved, time()), ['sent' => false, 'attempted' => true]);
        }
        if ($sent) {
            // Start the five-minute cooldown when the gateway finishes accepting it.
            $reserved[count($reserved) - 1] = time();
            if (!writePatientSmsTestSends($file, $reserved)) return $unavailable;
        } else {
            // A definite gateway rejection does not consume a successful-send slot.
            if (!writePatientSmsTestSends($file, $sends)) return $unavailable;
            $reserved = $sends;
        }
        return array_merge(patientSmsTestQuotaState($reserved, time()), ['sent' => $sent, 'attempted' => true]);
    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
}
