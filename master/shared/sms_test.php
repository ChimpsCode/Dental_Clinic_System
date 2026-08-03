<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../master/welcome-page/login.php');
    exit();
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['staff', 'admin', 'dentist'], true)) {
    http_response_code(403);
    echo 'Unauthorized';
    exit();
}

require_once __DIR__ . '/../../includes/sms_functions.php';

$recipients = defined('SMS_TEST_RECIPIENTS') ? (array)SMS_TEST_RECIPIENTS : [];
$keyConfigured = defined('TEXTBEE_API_KEY') && TEXTBEE_API_KEY !== '' && TEXTBEE_API_KEY !== 'YOUR_TEXBEE_API_KEY';
$deviceConfigured = defined('TEXTBEE_DEVICE_ID') && TEXTBEE_DEVICE_ID !== '' && TEXTBEE_DEVICE_ID !== 'YOUR_TEXBEE_DEVICE_ID';

$results = [];
$formNumber = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $formNumber = trim((string)($_POST['number'] ?? ''));
    if ($formNumber !== '') {
        $results[$formNumber] = sendSMS($formNumber, 'RF Dental Clinic SMS test message.');
    }
}

if (isset($_GET['send']) && $_GET['send'] === '1') {
    foreach ($recipients as $to) {
        $results[$to] = sendSMS($to, 'RF Dental Clinic SMS test message.');
    }
}

$connResult = null;
if (isset($_GET['conn']) && $_GET['conn'] === '1') {
    $connResult = testTextBeeConnection();
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
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #eee; }
        code { background: #f1f3f4; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
        .btn { display: inline-block; background: #1a73e8; color: #fff; text-decoration: none; padding: 10px 16px; border-radius: 6px; font-size: 14px; border: none; cursor: pointer; }
        .btn:hover { background: #1765cc; }
        input[type=text] { width: 100%; padding: 10px 12px; border: 1px solid #dadce0; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
        .muted { color: #5f6368; font-size: 13px; }
        pre { white-space: pre-wrap; margin: 0; }
    </style>
</head>
<body>
    <div class="card">
        <h1>SMS Test</h1>

        <form method="post" action="">
            <label for="sms-number"><strong>Send a test SMS to a phone number:</strong></label>
            <input type="text" id="sms-number" name="number" value="<?php echo htmlspecialchars($formNumber); ?>" placeholder="e.g. 09171234567" required>
            <p class="muted">Enter any PH mobile number. Any format works: 09171234567, 9171234567, or 639171234567.</p>
            <button type="submit" class="btn">Send test SMS</button>
        </form>

        <div class="status <?php echo $keyConfigured ? 'ok' : 'warn'; ?>">
            <?php if ($keyConfigured): ?>
                TextBee API key is configured.
            <?php else: ?>
                TextBee API key is NOT configured. Open <code>config/sms_credentials.php</code> and set <code>TEXTBEE_API_KEY</code>.
            <?php endif; ?>
        </div>

        <div class="status <?php echo $deviceConfigured ? 'ok' : 'warn'; ?>">
            <?php if ($deviceConfigured): ?>
                TextBee device ID is configured.
            <?php else: ?>
                TextBee device ID is NOT configured. Open <code>config/sms_credentials.php</code> and set <code>TEXTBEE_DEVICE_ID</code>.
            <?php endif; ?>
        </div>

        <div class="status <?php echo $recipients ? 'ok' : 'warn'; ?>">
            <?php echo $recipients ? count($recipients) . ' test recipient(s) configured.' : 'No test recipients yet. Add numbers to <code>SMS_TEST_RECIPIENTS</code> in <code>config/sms_credentials.php</code> (international format, e.g. 639171234567).'; ?>
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
</body>
</html>
