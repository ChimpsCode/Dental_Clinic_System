<?php
/**
 * Phone-number change verification for patients.
 *
 * A patient can only change the number that receives their SMS once they prove
 * they control the NEW number, so the OTP is always delivered by SMS regardless
 * of their notification preferences.
 *
 * Actions:
 *   send_otp     - generate a 6-digit code, text it to the new number
 *   verify_otp   - exchange the code for an actual phone update
 *   cancel_otp   - discard a pending code
 *
 * Anti-spam limits on send_otp: 2 minutes between codes for the same number,
 * 3 resends, then a 1 hour cool-off. sms_log is also consulted per target
 * number so dropping the session cookie cannot be used to keep texting.
 */
ob_start();
require_once __DIR__ . '/../../includes/session_bootstrap.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/sms_functions.php';

$revoked = app_session_revoked_response();
if ($revoked !== null || !isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    echo json_encode($revoked ?? ['success' => false, 'message' => 'Authentication required']);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$patientId = (int)($_SESSION['patient_id'] ?? 0);
$action = $_POST['action'] ?? '';

const PHONE_OTP_TTL_SECONDS = 300;
const PHONE_OTP_RESEND_SECONDS = 120;
const PHONE_OTP_MAX_ATTEMPTS = 5;
const PHONE_OTP_MAX_RESENDS = 3;
const PHONE_OTP_RESEND_BLOCK_SECONDS = 3600;
const PHONE_OTP_HOURLY_SMS_LIMIT = 6;
const PHONE_OTP_RESEND_BLOCK_MESSAGE = 'Please try again in an hour. You have requested too many verification codes for this number.';

function phoneOtpFail(string $message, int $status = 400, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(array_merge(['success' => false, 'message' => $message], $extra));
    exit();
}

function phoneOtpClear(): void
{
    unset(
        $_SESSION['phone_change_otp'],
        $_SESSION['phone_change_otp_expires'],
        $_SESSION['phone_change_for'],
        $_SESSION['phone_change_sent_at'],
        $_SESSION['phone_change_attempts'],
        $_SESSION['phone_change_resends']
    );
}

function phoneOtpBlockRemaining(string $phone): int
{
    $block = $_SESSION['phone_change_resend_block'] ?? null;
    if (!is_array($block) || ($block['phone'] ?? '') !== $phone) {
        return 0;
    }
    return max(0, (int)($block['until'] ?? 0) - time());
}

function phoneOtpBlockResends(string $phone): void
{
    $_SESSION['phone_change_resend_block'] = [
        'phone' => $phone,
        'until' => time() + PHONE_OTP_RESEND_BLOCK_SECONDS,
    ];
}

function phoneOtpSmsSentInLastHour(PDO $pdo, string $phone): int
{
    try {
        ensureSmsLogSchema($pdo);
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM sms_log
             WHERE category = 'phone_change_otp'
               AND phone IN (?, ?)
               AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stmt->execute([$phone, '63' . $phone]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('phone_change_actions: sms_log limit check failed - ' . $e->getMessage());
        return 0;
    }
}

/**
 * Accept 09XXXXXXXXX, 9XXXXXXXXX, 63XXXXXXXXX or +63XXXXXXXXX and store it in
 * one canonical form so the code can never be replayed against a different
 * spelling of the same number.
 */
function normalizePhoneForOtp(string $raw): string
{
    $digits = preg_replace('/\D+/', '', $raw);
    if ($digits === '' || strlen($digits) < 10 || strlen($digits) > 15) {
        return '';
    }
    if (strpos($digits, '63') === 0) {
        $digits = substr($digits, 2);
    } elseif (strpos($digits, '0') === 0) {
        $digits = substr($digits, 1);
    }
    if (strlen($digits) !== 10 || $digits[0] !== '9') {
        return '';
    }
    return $digits;
}

if ($action === 'cancel_otp') {
    phoneOtpClear();
    echo json_encode(['success' => true, 'message' => 'Verification cancelled']);
    exit();
}

if ($action === 'send_otp') {
    $phone = normalizePhoneForOtp((string)($_POST['phone'] ?? ''));
    if ($phone === '') {
        phoneOtpFail('Please enter a valid 10-digit Philippine mobile number (e.g. 0917 123 4567).');
    }

    if (!smsIsConfigured()) {
        phoneOtpFail('SMS is not available right now, so the number cannot be verified. Please contact the clinic.', 503);
    }

    $blockedLeft = phoneOtpBlockRemaining($phone);
    if ($blockedLeft > 0) {
        phoneOtpFail(PHONE_OTP_RESEND_BLOCK_MESSAGE, 429, [
            'blocked' => true,
            'retry_after' => $blockedLeft,
        ]);
    }

    if (phoneOtpSmsSentInLastHour($pdo, $phone) >= PHONE_OTP_HOURLY_SMS_LIMIT) {
        phoneOtpBlockResends($phone);
        phoneOtpFail(PHONE_OTP_RESEND_BLOCK_MESSAGE, 429, [
            'blocked' => true,
            'retry_after' => PHONE_OTP_RESEND_BLOCK_SECONDS,
        ]);
    }

    $lastSentAt = (int)($_SESSION['phone_change_sent_at'] ?? 0);
    $cooldownLeft = $lastSentAt > 0 ? PHONE_OTP_RESEND_SECONDS - (time() - $lastSentAt) : 0;
    if ($cooldownLeft > 0) {
        phoneOtpFail('Please wait ' . $cooldownLeft . ' seconds before requesting another code.', 429, [
            'retry_after' => $cooldownLeft,
        ]);
    }

    $isResend = ($_SESSION['phone_change_for'] ?? null) === $phone;
    $resends = (int)($_SESSION['phone_change_resends'] ?? 0);
    if ($isResend && $resends >= PHONE_OTP_MAX_RESENDS) {
        phoneOtpBlockResends($phone);
        phoneOtpFail(PHONE_OTP_RESEND_BLOCK_MESSAGE, 429, [
            'blocked' => true,
            'retry_after' => PHONE_OTP_RESEND_BLOCK_SECONDS,
        ]);
    }

    $current = patientSmsPhone($pdo, $patientId);
    if ($current !== '' && $current === $phone) {
        phoneOtpFail('That is already the phone number on your account.');
    }

    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $message = "Your RF Dental Clinic verification code is {$otp}. It expires in 5 minutes. Do not share it with anyone.";

    // ignorePreference: the code that authorises changing the SMS number must
    // be deliverable even when the patient has appointment texts switched off.
    $sent = sendPatientSmsTracked(
        $pdo,
        $patientId,
        $message,
        'phone_change_otp',
        null,
        true,
        false,
        $phone
    );

    if (!$sent) {
        phoneOtpFail('We could not send a code to that number. Please check it and try again.', 502);
    }

    $_SESSION['phone_change_otp'] = $otp;
    $_SESSION['phone_change_otp_expires'] = time() + PHONE_OTP_TTL_SECONDS;
    $_SESSION['phone_change_for'] = $phone;
    $_SESSION['phone_change_sent_at'] = time();
    $_SESSION['phone_change_attempts'] = 0;
    $_SESSION['phone_change_resends'] = $isResend ? $resends + 1 : 0;

    // Never echo the code itself.
    echo json_encode([
        'success' => true,
        'sent' => true,
        'expires_in' => PHONE_OTP_TTL_SECONDS,
        'resend_after' => PHONE_OTP_RESEND_SECONDS,
        'resends_left' => max(0, PHONE_OTP_MAX_RESENDS - (int)$_SESSION['phone_change_resends']),
        'message' => 'We sent a 6-digit code to ' . $phone . '.',
    ]);
    exit();
}

if ($action === 'verify_otp') {
    $phone = normalizePhoneForOtp((string)($_POST['phone'] ?? ''));
    $otp = trim((string)($_POST['otp'] ?? ''));

    if ($phone === '') {
        phoneOtpFail('Please enter a valid 10-digit Philippine mobile number.');
    }
    if (!preg_match('/^\d{6}$/', $otp)) {
        phoneOtpFail('Enter the 6-digit code from your text message.');
    }

    $stored = $_SESSION['phone_change_otp'] ?? null;
    $expires = (int)($_SESSION['phone_change_otp_expires'] ?? 0);
    $for = $_SESSION['phone_change_for'] ?? null;

    if (!$stored || $expires < time() || $for !== $phone) {
        phoneOtpClear();
        phoneOtpFail('That code has expired or was sent to a different number. Please request a new code.');
    }

    $attempts = (int)($_SESSION['phone_change_attempts'] ?? 0);
    if ($attempts >= PHONE_OTP_MAX_ATTEMPTS) {
        phoneOtpClear();
        phoneOtpFail('Too many incorrect attempts. Please request a new code.', 429);
    }

    if (!hash_equals((string)$stored, $otp)) {
        $_SESSION['phone_change_attempts'] = $attempts + 1;
        phoneOtpFail('That code is not correct. Please try again.');
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET phone = ? WHERE id = ?')->execute([$phone, $userId]);
        if ($patientId > 0) {
            $pdo->prepare('UPDATE patients SET phone = ? WHERE id = ?')->execute([$phone, $patientId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('phone_change_actions: update failed - ' . $e->getMessage());
        phoneOtpFail('We could not save your new number. Please try again.', 500);
    }

    unset($_SESSION['phone_change_resend_block']);
    phoneOtpClear();

    echo json_encode([
        'success' => true,
        'phone' => $phone,
        'message' => 'Phone number updated to ' . $phone . '.',
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
