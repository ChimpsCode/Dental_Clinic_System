<?php
require_once __DIR__ . '/../../includes/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../master/welcome-page/login.php');
    exit();
}

// Admin only. This page can send SMS to an arbitrary number, so it is a
// paid relay: previously any staff, dentist or admin account could post to it.
$role = $_SESSION['role'] ?? '';
if ($role !== 'admin') {
    http_response_code(403);
    echo 'Unauthorized';
    exit();
}

require_once __DIR__ . '/../../includes/sms_functions.php';
require_once __DIR__ . '/../../config/database.php';

// Hard cap so this page cannot be used to burn through SMS credit.
const SMS_TEST_MAX_PER_HOUR = 20;
const SMS_TEST_WINDOW_SECONDS = 3600;

function smsTestThrottleFile(): string
{
    return dirname(__DIR__, 2) . '/cache/rate_limit/sms_test_staff';
}

function smsTestSendsInWindow(): int
{
    $file = smsTestThrottleFile();
    if (!is_file($file)) {
        return 0;
    }
    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        return 0;
    }
    $cutoff = time() - SMS_TEST_WINDOW_SECONDS;
    return count(array_filter($lines, static fn($ts) => (int)$ts >= $cutoff));
}

function smsTestRecordSend(): void
{
    $file = smsTestThrottleFile();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $cutoff = time() - SMS_TEST_WINDOW_SECONDS;
    $lines = array_values(array_filter($lines, static fn($ts) => (int)$ts >= $cutoff));
    $lines[] = (string)time();
    @file_put_contents($file, implode("\n", $lines) . "\n");
}

$recipients = defined('SMS_TEST_RECIPIENTS') ? (array)SMS_TEST_RECIPIENTS : [];
$keyConfigured = smsHasApiKey();
$deviceConfigured = smsHasDeviceId();
$rateLimitHit = smsTestSendsInWindow() >= SMS_TEST_MAX_PER_HOUR;
$rateLimitRemaining = max(0, SMS_TEST_MAX_PER_HOUR - smsTestSendsInWindow());

$results = [];
$formNumber = '';
$notices = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !$rateLimitHit) {
    $formNumber = trim((string)($_POST['number'] ?? ''));
    if ($formNumber !== '') {
        $result = sendSMS($formNumber, 'RF Dental Clinic SMS test message.');
        $results[$formNumber] = $result;
        logSms($pdo, [
            'phone' => formatPhoneNumber($formNumber),
            'message' => 'RF Dental Clinic SMS test message.',
            'category' => 'staff_test',
            'status' => !empty($result['success']) ? 'sent' : 'failed',
            'provider_message_id' => $result['sms_id'] ?? null,
            'error_message' => !empty($result['success']) ? null : ($result['error'] ?? 'Unknown'),
            'requested_by' => (int)$_SESSION['user_id'],
        ]);
        smsTestRecordSend();
    }
}

if (isset($_GET['send']) && $_GET['send'] === '1' && !$rateLimitHit) {
    foreach ($recipients as $to) {
        $result = sendSMS($to, 'RF Dental Clinic SMS test message.');
        $results[$to] = $result;
        logSms($pdo, [
            'phone' => formatPhoneNumber($to),
            'message' => 'RF Dental Clinic SMS test message.',
            'category' => 'staff_test',
            'status' => !empty($result['success']) ? 'sent' : 'failed',
            'provider_message_id' => $result['sms_id'] ?? null,
            'error_message' => !empty($result['success']) ? null : ($result['error'] ?? 'Unknown'),
            'requested_by' => (int)$_SESSION['user_id'],
        ]);
        smsTestRecordSend();
    }
}

if ($rateLimitHit) {
    $notices[] = 'Hourly test-SMS limit reached (' . SMS_TEST_MAX_PER_HOUR . ' per hour). Try again later.';
}

$connResult = null;
if (isset($_GET['conn']) && $_GET['conn'] === '1') {
    $connResult = testTextBeeConnection();
}

$recentSms = [];
try {
    $recentSms = $pdo->query('SELECT phone, category, status, error_message, created_at FROM sms_log ORDER BY id DESC LIMIT 15')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // table may not exist yet on a fresh install
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Test</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 20px; }
        .card { background: #fff; border-radius: 8px; padding: 20px; max-width: 640px; margin: 0 auto 16px; box-shadow: 0 1px 3px rgba(0,0,0,.12); }
        h1 { margin-top: 0; font-size: 20px; }
        .status { padding: 10px 12px; border-radius: 6px; margin: 10px 0; font-size: 14px; }
        .ok { background: #e6f4ea; color: #137333; }
        .warn { background: #fef7e0; color: #b06000; }
        .bad { background: #fce8e6; color: #c5221f; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #eee; }
        code { background: #f1f3f4; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
        .btn { display: inline-block; background: #1a73e8; color: #fff; text-decoration: none; padding: 10px 16px; border-radius: 6px; font-size: 14px; border: none; cursor: pointer; }
        .btn:hover { background: #1765cc; }
        .btn[disabled] { background: #9aa0a6; cursor: not-allowed; }
        input[type=text] { width: 100%; padding: 10px 12px; border: 1px solid #dadce0; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
        .muted { color: #5f6368; font-size: 13px; }
        pre { white-space: pre-wrap; margin: 0; }
    </style>
</head>
<body>
    <div class="card">
        <h1>SMS Test</h1>

        <?php foreach ($notices as $notice): ?>
            <div class="status bad"><?php echo htmlspecialchars($notice); ?></div>
        <?php endforeach; ?>

        <form method="post" action="">
            <label for="sms-number"><strong>Send a test SMS to a phone number:</strong></label>
            <input type="text" id="sms-number" name="number" value="<?php echo htmlspecialchars($formNumber); ?>" placeholder="e.g. 09171234567" <?php echo $rateLimitHit ? 'disabled' : ''; ?> required>
            <p class="muted">Enter any PH mobile number. Any format works: 09171234567, 9171234567, or 639171234567.</p>
            <button type="submit" class="btn" <?php echo $rateLimitHit ? 'disabled' : ''; ?>>Send test SMS</button>
            <p class="muted">Admin-only. <?php echo $rateLimitRemaining; ?> of <?php echo SMS_TEST_MAX_PER_HOUR; ?> test messages left this hour.</p>
        </form>

        <div class="status <?php echo $keyConfigured ? 'ok' : 'warn'; ?>">
            <?php if ($keyConfigured): ?>
                TextBee API key is configured.
            <?php else: ?>
                TextBee API key is NOT configured. An admin can add it from <a href="../admin/admin_settings.php#textbeeSettings">Admin Settings → TextBee SMS Connection</a>.
            <?php endif; ?>
        </div>

        <div class="status <?php echo $deviceConfigured ? 'ok' : 'warn'; ?>">
            <?php if ($deviceConfigured): ?>
                TextBee device ID is configured.
            <?php else: ?>
                TextBee device ID is NOT configured. An admin can add it from <a href="../admin/admin_settings.php#textbeeSettings">Admin Settings → TextBee SMS Connection</a>.
            <?php endif; ?>
        </div>

        <div class="status <?php echo $recipients ? 'ok' : 'warn'; ?>">
            <?php echo $recipients ? count($recipients) . ' test recipient(s) configured.' : 'No default test recipients configured. Enter a number above for a one-off test SMS.'; ?>
        </div>

        <?php if ($recipients): ?>
            <p><a class="btn" href="?send=1">Send test SMS</a></p>
        <?php endif; ?>
        <p><a class="btn" style="background:#5f6368" href="?conn=1">Test connection (no SMS sent)</a></p>
    </div>

    <?php if ($connResult): ?>
        <div class="card">
            <h2>Connection test</h2>
            <div class="status <?php echo $connResult['success'] ? 'ok' : 'warn'; ?>">
                <?php if ($connResult['success']): ?>
                    Connected to TextBee (HTTP <?php echo $connResult['status']; ?>). API key is valid and the network is reachable.
                <?php else: ?>
                    Connection failed (HTTP <?php echo $connResult['status'] ?? 'n/a'; ?>): <?php echo htmlspecialchars($connResult['error'] ?? 'Unknown'); ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($recipients): ?>
        <div class="card">
            <h2>Test recipients</h2>
            <ul>
                <?php foreach ($recipients as $to): ?>
                    <li><code><?php echo htmlspecialchars($to); ?></code></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($results): ?>
        <div class="card">
            <h2>Results</h2>
            <table>
                <tr><th>Number</th><th>Status</th></tr>
                <?php foreach ($results as $to => $r): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($to); ?></code></td>
                        <td><?php echo $r['success'] ? '<span style="color:#137333">Sent (id: ' . htmlspecialchars($r['sms_id'] ?? 'n/a') . ', status: ' . htmlspecialchars($r['provider_status'] ?? 'Queued') . ')</span>' : '<span style="color:#d93025">Failed: ' . htmlspecialchars($r['error'] ?? 'Unknown') . '</span>'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <p class="muted">Full details are also written to <code>C:\xampp\apache\logs\error.log</code>.</p>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Recent SMS activity</h2>
        <p class="muted">Every outbound text is recorded so a missing message can be traced without reading the server logs.</p>
        <?php if (!$recentSms): ?>
            <p class="muted">Nothing recorded yet.</p>
        <?php else: ?>
            <table>
                <tr><th>When</th><th>Number</th><th>Category</th><th>Status</th><th>Detail</th></tr>
                <?php foreach ($recentSms as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string)$row['created_at']); ?></td>
                        <td><code><?php echo htmlspecialchars((string)$row['phone']); ?></code></td>
                        <td><?php echo htmlspecialchars((string)$row['category']); ?></td>
                        <td style="color:<?php echo $row['status'] === 'sent' ? '#137333' : ($row['status'] === 'skipped' ? '#b06000' : '#c5221f'); ?>;">
                            <?php echo htmlspecialchars((string)$row['status']); ?>
                        </td>
                        <td class="muted"><?php echo htmlspecialchars((string)($row['error_message'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
