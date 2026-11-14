<?php
/**
 * Settings Actions - Handle saving and loading system settings
 */

require_once __DIR__ . '/../../includes/session_bootstrap.php';header('Content-Type: application/json');

require_once '../../config/database.php';
require_once '../../includes/audit_helper.php';
require_once '../../includes/two_factor_actions_lib.php';

// This endpoint only backs the admin settings screen, so refuse anything else.
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$auditUserId = $_SESSION['user_id'] ?? null;
$auditUsername = $_SESSION['username'] ?? 'unknown';
$auditRole = $_SESSION['role'] ?? 'admin';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (in_array($action, ['save_textbee_settings', 'test_textbee_connection'], true)) {
    if (empty($_SESSION['textbee_settings_csrf'])
        || !hash_equals((string)$_SESSION['textbee_settings_csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Reload the page and try again.']);
        exit;
    }
}

if ($action === 'save_textbee_settings') {
    $credentialsFile = __DIR__ . '/../../config/sms_credentials.php';
    $existingApiKey = '';
    $existingDeviceId = '';
    $existingRecipients = [];

    if (is_file($credentialsFile)) {
        require_once $credentialsFile;
        $existingApiKey = defined('TEXTBEE_API_KEY') ? (string)TEXTBEE_API_KEY : '';
        $existingDeviceId = defined('TEXTBEE_DEVICE_ID') ? (string)TEXTBEE_DEVICE_ID : '';
        $existingRecipients = defined('SMS_TEST_RECIPIENTS') ? (array)SMS_TEST_RECIPIENTS : [];
    }

    $apiKey = trim((string)($_POST['textbee_api_key'] ?? ''));
    $deviceId = trim((string)($_POST['textbee_device_id'] ?? ''));
    $apiKey = $apiKey === '' ? $existingApiKey : $apiKey;
    $deviceId = $deviceId === '' ? $existingDeviceId : $deviceId;

    if ($apiKey === '' || strlen($apiKey) > 200 || preg_match('/\s/', $apiKey)) {
        echo json_encode(['success' => false, 'message' => 'Enter a valid TextBee API key.']);
        exit;
    }
    if ($deviceId === '' || strlen($deviceId) > 120 || !preg_match('/^[A-Za-z0-9_-]+$/', $deviceId)) {
        echo json_encode(['success' => false, 'message' => 'Enter a valid TextBee device ID.']);
        exit;
    }

    $contents = "<?php\n\n"
        . "// Local SMS credentials. Managed from the admin Settings page.\n"
        . "define('TEXTBEE_API_KEY', " . var_export($apiKey, true) . ");\n"
        . "define('TEXTBEE_DEVICE_ID', " . var_export($deviceId, true) . ");\n"
        . "define('SMS_TEST_RECIPIENTS', " . var_export(array_values($existingRecipients), true) . ");\n";
    $temporaryFile = $credentialsFile . '.tmp';

    if (@file_put_contents($temporaryFile, $contents, LOCK_EX) === false
        || !@rename($temporaryFile, $credentialsFile)) {
        @unlink($temporaryFile);
        echo json_encode(['success' => false, 'message' => 'The server could not save the TextBee credentials. Check the config folder permissions.']);
        exit;
    }

    auditedUpsertSetting($pdo, 'textbee_configured', '1', 'Updated TextBee SMS credentials', $auditUserId, $auditUsername, $auditRole);
    echo json_encode(['success' => true, 'message' => 'TextBee credentials saved.']);
    exit;
}

if ($action === 'test_textbee_connection') {
    require_once '../../includes/sms_functions.php';
    $result = testTextBeeConnection();
    if (!empty($result['success'])) {
        echo json_encode(['success' => true, 'message' => 'TextBee connection is working. No SMS was sent.']);
    } else {
        $error = (string)($result['error'] ?? 'TextBee connection failed.');
        echo json_encode(['success' => false, 'message' => 'TextBee connection failed: ' . $error]);
    }
    exit;
}

if ($action === 'get_settings') {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    echo json_encode(['success' => true, 'settings' => $settings]);
    exit;
}

if ($action === 'save_settings') {
    $clinicName = trim($_POST['clinic_name'] ?? '');
    $clinicAddress = trim($_POST['clinic_address'] ?? '');
    $clinicPhone = trim($_POST['clinic_phone'] ?? '');
    $clinicEmail = trim($_POST['clinic_email'] ?? '');
    
    $settingsToSave = [
        'clinic_name' => $clinicName,
        'clinic_address' => $clinicAddress,
        'clinic_phone' => $clinicPhone,
        'clinic_email' => $clinicEmail
    ];
    
    foreach ($settingsToSave as $key => $value) {
        auditedUpsertSetting($pdo, $key, $value, 'Updated clinic settings', $auditUserId, $auditUsername, $auditRole);
    }
    
    echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
    exit;
}

if ($action === 'save_business_hours') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $businessHours = $input['business_hours'] ?? null;
    
    if (!$businessHours) {
        echo json_encode(['success' => false, 'message' => 'No business hours data provided']);
        exit;
    }
    
    auditedUpsertSetting($pdo, 'business_hours', json_encode($businessHours), 'Updated business hours', $auditUserId, $auditUsername, $auditRole);
    
    echo json_encode(['success' => true, 'message' => 'Business hours saved successfully']);
    exit;
}

if ($action === 'get_business_hours') {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'business_hours'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $hours = json_decode($result['setting_value'], true);
        echo json_encode(['success' => true, 'business_hours' => $hours]);
    } else {
        echo json_encode(['success' => true, 'business_hours' => []]);
    }
    exit;
}

if ($action === 'save_unavailable_date') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $date = $input['date'] ?? '';
    $status = $input['status'] ?? 'closed';
    $note = $input['note'] ?? '';
    $customOpen = $input['custom_open'] ?? null;
    $customClose = $input['custom_close'] ?? null;
    
    if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'unavailable_dates'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $unavailableDates = $row ? json_decode($row['setting_value'], true) : [];
    
    $existingIndex = array_search($date, array_column($unavailableDates, 'date'));
    
    $newEntry = [
        'date' => $date,
        'status' => $status,
        'note' => $note
    ];
    
    if ($status === 'custom') {
        $newEntry['custom_open'] = $customOpen;
        $newEntry['custom_close'] = $customClose;
    }
    
    if ($existingIndex !== false) {
        $unavailableDates[$existingIndex] = $newEntry;
    } else {
        $unavailableDates[] = $newEntry;
    }
    
    usort($unavailableDates, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });
    
    auditedUpsertSetting($pdo, 'unavailable_dates', json_encode($unavailableDates), 'Updated unavailable dates', $auditUserId, $auditUsername, $auditRole);
    
    echo json_encode(['success' => true, 'message' => 'Date saved successfully']);
    exit;
}

if ($action === 'delete_unavailable_date') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $date = $input['date'] ?? '';
    
    if (empty($date)) {
        echo json_encode(['success' => false, 'message' => 'No date specified']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'unavailable_dates'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $unavailableDates = $row ? json_decode($row['setting_value'], true) : [];
    
    $unavailableDates = array_values(array_filter($unavailableDates, function($entry) use ($date) {
        return $entry['date'] !== $date;
    }));
    
    auditedUpsertSetting($pdo, 'unavailable_dates', json_encode($unavailableDates), 'Removed unavailable date', $auditUserId, $auditUsername, $auditRole);
    
    echo json_encode(['success' => true, 'message' => 'Date removed successfully']);
    exit;
}

if ($action === 'bulk_add_unavailable_dates') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $startDate = $input['start_date'] ?? '';
    $endDate = $input['end_date'] ?? '';
    $status = $input['status'] ?? 'closed';
    $note = $input['note'] ?? '';
    $customOpen = $input['custom_open'] ?? null;
    $customClose = $input['custom_close'] ?? null;
    
    if (empty($startDate) || empty($endDate)) {
        echo json_encode(['success' => false, 'message' => 'Start and end dates are required']);
        exit;
    }
    
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    
    if ($start > $end) {
        echo json_encode(['success' => false, 'message' => 'Start date must be before end date']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'unavailable_dates'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $unavailableDates = $row ? json_decode($row['setting_value'], true) : [];
    $existingDates = array_column($unavailableDates, 'date');
    
    $added = 0;
    $current = clone $start;
    
    while ($current <= $end) {
        $dateStr = $current->format('Y-m-d');
        
        if (!in_array($dateStr, $existingDates)) {
            $newEntry = [
                'date' => $dateStr,
                'status' => $status,
                'note' => $note
            ];
            
            if ($status === 'custom') {
                $newEntry['custom_open'] = $customOpen;
                $newEntry['custom_close'] = $customClose;
            }
            
            $unavailableDates[] = $newEntry;
            $added++;
        }
        
        $current->modify('+1 day');
    }
    
    usort($unavailableDates, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });
    
    auditedUpsertSetting($pdo, 'unavailable_dates', json_encode($unavailableDates), 'Bulk updated unavailable dates', $auditUserId, $auditUsername, $auditRole);
    
    echo json_encode(['success' => true, 'message' => "Added $added date(s) successfully"]);
    exit;
}

if ($action === 'get_unavailable_dates') {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'unavailable_dates'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $unavailableDates = json_decode($row['setting_value'], true);
        echo json_encode(['success' => true, 'unavailable_dates' => $unavailableDates]);
    } else {
        echo json_encode(['success' => true, 'unavailable_dates' => []]);
    }
    exit;
}

if ($action === 'save_auto_cancel_settings') {
    $enabled = ($_POST['auto_cancel_enabled'] ?? '0') === '1' ? '1' : '0';
    $minutes = (int)($_POST['auto_cancel_minutes'] ?? 15);
    if (!in_array($minutes, [10, 15, 20, 30])) {
        $minutes = 15;
    }

    auditedUpsertSetting($pdo, 'auto_cancel_enabled', $enabled, 'Updated auto-cancel settings', $auditUserId, $auditUsername, $auditRole);
    auditedUpsertSetting($pdo, 'auto_cancel_minutes', (string)$minutes, 'Updated auto-cancel settings', $auditUserId, $auditUsername, $auditRole);

    echo json_encode(['success' => true, 'message' => 'Auto-cancel settings saved successfully']);
    exit;
}

if ($action === 'save_auto_backup') {
    $enabled = ($_POST['auto_backup_enabled'] ?? '0') === '1' ? '1' : '0';
    $time = (string)($_POST['auto_backup_time'] ?? '02:00');
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
        $time = '02:00';
    }
    $mode = (($_POST['auto_backup_mode'] ?? 'month') === 'date') ? 'date' : 'month';
    $date = (string)($_POST['auto_backup_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = '';
    }
    $day = (int)($_POST['auto_backup_day'] ?? 1);
    if ($day < 1 || $day > 28) {
        $day = 1;
    }

    if ($enabled === '1' && $mode === 'date' && $date === '') {
        echo json_encode(['success' => false, 'message' => 'Choose the date for the automatic backup.']);
        exit;
    }

    $current = [];
    $currentStmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'auto_backup_%'");
    if ($currentStmt) {
        $current = $currentStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    }

    $scheduleChanged = ($current['auto_backup_enabled'] ?? '0') !== $enabled
        || ($current['auto_backup_time'] ?? '02:00') !== $time
        || ($current['auto_backup_mode'] ?? 'month') !== $mode
        || ($current['auto_backup_date'] ?? '') !== $date
        || (string)($current['auto_backup_day'] ?? '1') !== (string)$day;

    auditedUpsertSetting($pdo, 'auto_backup_enabled', $enabled, 'Updated auto backup', $auditUserId, $auditUsername, $auditRole);
    auditedUpsertSetting($pdo, 'auto_backup_time', $time, 'Updated auto backup', $auditUserId, $auditUsername, $auditRole);
    auditedUpsertSetting($pdo, 'auto_backup_mode', $mode, 'Updated auto backup', $auditUserId, $auditUsername, $auditRole);
    auditedUpsertSetting($pdo, 'auto_backup_date', $date, 'Updated auto backup', $auditUserId, $auditUsername, $auditRole);
    auditedUpsertSetting($pdo, 'auto_backup_day', (string)$day, 'Updated auto backup', $auditUserId, $auditUsername, $auditRole);
    if ($scheduleChanged) {
        auditedUpsertSetting($pdo, 'auto_backup_last_slot', '', 'Updated auto backup', $auditUserId, $auditUsername, $auditRole);
    }

    echo json_encode(['success' => true, 'message' => 'Auto backup saved successfully']);
    exit;
}

if ($action === 'save_security_settings') {
    $sessionTimeout = (int)($_POST['session_timeout'] ?? 30);
    if (!in_array($sessionTimeout, [15, 30, 60, 120], true)) {
        $sessionTimeout = 30;
    }

    auditedUpsertSetting($pdo, 'session_timeout', (string)$sessionTimeout, 'Updated security settings', $auditUserId, $auditUsername, $auditRole);

    echo json_encode(['success' => true, 'message' => 'Security settings saved successfully']);
    exit;
}

/**
 * Look up the currently authenticated admin's 2FA columns.
 */
function fetchAdminTwoFactorState(PDO $pdo, int $userId): array
{
    return fetchUserTwoFactorState($pdo, $userId);
}

$twoFactorActions = ['get_2fa_status', 'start_2fa_setup', 'confirm_2fa_setup', 'cancel_2fa_setup', 'disable_2fa'];
if (in_array($action, $twoFactorActions, true)) {
    $auditCallback = function (string $event) use ($pdo, $auditUserId, $auditUsername, $auditRole): void {
        if ($event === 'enable') {
            auditedUpsertSetting($pdo, 'two_factor_auth', '1', 'Enabled two-factor authentication', $auditUserId, $auditUsername, $auditRole);
            logAudit($pdo, (int)$auditUserId, $auditUsername, $auditRole, 'update', 'users', 'Enabled authenticator-based two-factor authentication');
            return;
        }

        auditedUpsertSetting($pdo, 'two_factor_auth', '0', 'Disabled two-factor authentication', $auditUserId, $auditUsername, $auditRole);
        logAudit($pdo, (int)$auditUserId, $auditUsername, $auditRole, 'update', 'users', 'Disabled authenticator-based two-factor authentication');
    };

    $result = handleTwoFactorAction($pdo, $action, (int)$auditUserId, $auditUsername, $auditRole, [
        'sync_global_setting' => true,
        'audit_callback' => $auditCallback,
    ]);
    echo json_encode($result);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
