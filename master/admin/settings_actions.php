<?php
/**
 * Settings Actions - Handle saving and loading system settings
 */

require_once __DIR__ . '/../../includes/session_bootstrap.php';header('Content-Type: application/json');

require_once '../../config/database.php';
require_once '../../includes/audit_helper.php';
require_once '../../lib/security/totp_lib.php';
require_once '../../lib/security/qrcode_lib.php';

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
    $stmt = $pdo->prepare("SELECT two_factor_enabled, two_factor_secret FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['enabled' => false, 'secret' => null];
    }
    return [
        'enabled' => (int)($row['two_factor_enabled'] ?? 0) === 1,
        'secret' => $row['two_factor_secret'] ?? null,
    ];
}

if ($action === 'get_2fa_status') {
    $state = fetchAdminTwoFactorState($pdo, (int)$auditUserId);
    echo json_encode([
        'success' => true,
        'enabled' => $state['enabled'],
        'has_secret' => totpIsValidSecret($state['secret']),
    ]);
    exit;
}

if ($action === 'start_2fa_setup') {
    $state = fetchAdminTwoFactorState($pdo, (int)$auditUserId);
    if ($state['enabled']) {
        echo json_encode(['success' => false, 'message' => 'Two-factor authentication is already enabled.']);
        exit;
    }

    $secret = totpGenerateSecret();
    $_SESSION['pending_2fa_secret'] = $secret;
    $_SESSION['pending_2fa_started_at'] = time();

    $accountName = trim((string)($_SESSION['email'] ?? ''));
    if ($accountName === '') {
        $accountName = (string)($_SESSION['username'] ?? 'admin');
    }

    $issuer = 'RF Dental Clinic';
    try {
        $issuerStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'clinic_name' LIMIT 1");
        $issuerStmt->execute();
        $clinicName = trim((string)$issuerStmt->fetchColumn());
        if ($clinicName !== '') {
            $issuer = $clinicName;
        }
    } catch (Throwable $e) {
        // Fall back to the hardcoded issuer above.
    }

    $otpauthUri = totpProvisioningUri($secret, $accountName, $issuer);

    try {
        $qrSvg = qrCodeSvg($otpauthUri, 'M', 6, 4);
        $qrDataUri = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
    } catch (QrCodeException $e) {
        unset($_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_started_at']);
        echo json_encode(['success' => false, 'message' => 'Could not generate QR code. Please try again.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'secret' => $secret,
        'secret_display' => totpFormatSecretForDisplay($secret),
        'otpauth_uri' => $otpauthUri,
        'qr_svg' => $qrSvg,
        'qr_data_uri' => $qrDataUri,
        'account' => $accountName,
        'issuer' => $issuer,
    ]);
    exit;
}

if ($action === 'confirm_2fa_setup') {
    $code = trim((string)($_POST['code'] ?? ''));
    $pendingSecret = (string)($_SESSION['pending_2fa_secret'] ?? '');
    $startedAt = (int)($_SESSION['pending_2fa_started_at'] ?? 0);

    if ($pendingSecret === '' || $startedAt === 0) {
        echo json_encode(['success' => false, 'message' => 'No setup in progress. Please start again.']);
        exit;
    }

    // Give the admin 15 minutes to scan the QR and enter a code.
    if ((time() - $startedAt) > 900) {
        unset($_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_started_at']);
        echo json_encode(['success' => false, 'message' => 'Setup expired. Please start again.']);
        exit;
    }

    if (!totpVerify($pendingSecret, $code, 1)) {
        echo json_encode(['success' => false, 'message' => 'Invalid verification code. Please try again.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE users SET two_factor_enabled = 1, two_factor_secret = ? WHERE id = ? LIMIT 1");
    $stmt->execute([$pendingSecret, (int)$auditUserId]);

    // Keep the legacy settings flag in sync so existing UI reads still work.
    auditedUpsertSetting($pdo, 'two_factor_auth', '1', 'Enabled two-factor authentication', $auditUserId, $auditUsername, $auditRole);
    logAudit($pdo, (int)$auditUserId, $auditUsername, $auditRole, 'update', 'users', 'Enabled authenticator-based two-factor authentication');

    unset($_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_started_at']);

    echo json_encode(['success' => true, 'message' => 'Two-factor authentication enabled.']);
    exit;
}

if ($action === 'cancel_2fa_setup') {
    unset($_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_started_at']);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'disable_2fa') {
    $state = fetchAdminTwoFactorState($pdo, (int)$auditUserId);
    if (!$state['enabled']) {
        echo json_encode(['success' => true, 'message' => 'Two-factor authentication is already disabled.']);
        exit;
    }

    // Require a current authenticator code so an unlocked browser session alone
    // cannot silently disable 2FA.
    $code = trim((string)($_POST['code'] ?? ''));
    if (!totpIsValidSecret($state['secret']) || !totpVerify((string)$state['secret'], $code, 1)) {
        echo json_encode(['success' => false, 'message' => 'Enter a valid authenticator code to disable 2FA.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$auditUserId]);

    auditedUpsertSetting($pdo, 'two_factor_auth', '0', 'Disabled two-factor authentication', $auditUserId, $auditUsername, $auditRole);
    logAudit($pdo, (int)$auditUserId, $auditUsername, $auditRole, 'update', 'users', 'Disabled authenticator-based two-factor authentication');

    unset($_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_started_at']);

    echo json_encode(['success' => true, 'message' => 'Two-factor authentication disabled.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
