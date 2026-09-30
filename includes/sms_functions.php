<?php

$smsCredentialsFile = __DIR__ . '/../config/sms_credentials.php';
if (is_file($smsCredentialsFile)) {
    require_once $smsCredentialsFile;
} else {
    // SMS is optional. Keep appointment and registration workflows working
    // when local credentials have not been configured.
    if (!defined('TEXTBEE_API_KEY')) {
        define('TEXTBEE_API_KEY', '');
    }
    if (!defined('TEXTBEE_DEVICE_ID')) {
        define('TEXTBEE_DEVICE_ID', '');
    }
    if (!defined('SMS_TEST_RECIPIENTS')) {
        define('SMS_TEST_RECIPIENTS', []);
    }
}

/**
 * Remember the most recent transport failure so the UI can explain *why* a
 * message did not go out. Without this, every failure looked identical to
 * staff ("the gateway rejected the message"), which is how an expired API key
 * went unnoticed.
 */
/**
 * Pull the provider's own explanation out of a failed TextBee response.
 * Falls back to the HTTP status when the body is not JSON.
 */
function smsProviderErrorReason(string $raw, int $httpCode): string
{
    $data = json_decode($raw, true);
    if (is_array($data)) {
        foreach (['error', 'message', 'data.message'] as $key) {
            $val = $data;
            foreach (explode('.', $key) as $seg) {
                if (!is_array($val) || !array_key_exists($seg, $val)) { $val = null; break; }
                $val = $val[$seg];
            }
            if (is_string($val) && trim($val) !== '') {
                $code = isset($data['code']) && is_string($data['code']) ? ' [' . $data['code'] . ']' : '';
                return trim($val) . $code;
            }
        }
    }
    $raw = trim($raw);
    if ($raw !== '' && strlen($raw) <= 120) {
        return $raw;
    }
    return 'HTTP ' . ($httpCode ?: 'no response');
}

/**
 * Turn a raw transport error into something a receptionist can act on.
 */
function describeSmsTransportError(?string $error): string
{
    if ($error === null || trim($error) === '') {
        return 'the SMS gateway rejected the message';
    }
    $error = trim($error);
    $lower = strtolower($error);

    if (strpos($lower, 'unauthorized') !== false
        || strpos($lower, 'auth_invalid') !== false
        || strpos($lower, 'http 401') !== false
        || strpos($lower, 'http \'401\'') !== false) {
        return 'the SMS gateway rejected our API key (HTTP 401). The key in config/sms_credentials.php is no longer valid and must be replaced.';
    }
    if (strpos($lower, 'forbidden') !== false || strpos($lower, 'http 403') !== false) {
        return 'the SMS gateway refused the request (HTTP 403). Check the device ID and that SMS is enabled for this TextBee account.';
    }
    if (strpos($lower, 'quota') !== false
        || strpos($lower, 'limit') !== false
        || strpos($lower, 'used all') !== false
        || strpos($lower, 'plan allows') !== false
        || strpos($lower, 'upgrade your plan') !== false) {
        return 'the SMS gateway has no remaining credit for today (TextBee free plan allows 50 messages per day, resets at midnight UTC)';
    }
    if (strpos($lower, 'not found') !== false || strpos($lower, 'http 404') !== false) {
        return 'the registered SMS device was not found in TextBee. Re-check the device ID in config/sms_credentials.php.';
    }
    if (strpos($lower, 'api key not configured') !== false || strpos($lower, 'device id not configured') !== false) {
        return 'the SMS gateway is not configured on this server';
    }
    if (strpos($lower, 'phone number is empty') !== false) {
        return 'the patient has no phone number on file';
    }
    if (strpos($lower, 'timed out') !== false || strpos($lower, 'timeout') !== false) {
        return 'the SMS gateway did not respond in time';
    }

    return 'the SMS gateway rejected the message (' . $error . ')';
}

function smsSetLastError(?string $error): void
{
    $GLOBALS['sms_last_error'] = $error;
}

function smsLastError(): ?string
{
    $err = $GLOBALS['sms_last_error'] ?? null;
    return is_string($err) && $err !== '' ? $err : null;
}

function sendSMS($phone, $message, bool $nonBlocking = false) {
    $phone = formatPhoneNumber($phone);
    if ($phone === '') {
        error_log('TextBee SMS skipped: empty phone number');
        smsSetLastError('Phone number is empty');
        return ['success' => false, 'error' => 'Phone number is empty'];
    }
    $result = sendViaTextBee($phone, $message, $nonBlocking);
    smsSetLastError(!empty($result['success']) ? null : ($result['error'] ?? 'Unknown error'));
    return $result;
}

function sendViaTextBee($phone, $message, bool $nonBlocking = false) {
    if (!defined('TEXTBEE_API_KEY') || TEXTBEE_API_KEY === '' || TEXTBEE_API_KEY === 'YOUR_TEXBEE_API_KEY') {
        error_log("TextBee SMS skipped: TEXTBEE_API_KEY not configured");
        return ['success' => false, 'error' => 'TextBee API key not configured'];
    }
    if (!defined('TEXTBEE_DEVICE_ID') || TEXTBEE_DEVICE_ID === '' || TEXTBEE_DEVICE_ID === 'YOUR_TEXBEE_DEVICE_ID') {
        error_log("TextBee SMS skipped: TEXTBEE_DEVICE_ID not configured");
        return ['success' => false, 'error' => 'TextBee device ID not configured'];
    }

    $recipient = $phone;
    if (substr($recipient, 0, 1) !== '+') {
        $recipient = '+' . $recipient;
    }

    $body = json_encode([
        'recipients' => [$recipient],
        'message' => $message,
    ]);

    $url = 'https://api.textbee.dev/api/v1/gateway/devices/' . rawurlencode(TEXTBEE_DEVICE_ID) . '/send-sms';

    if ($nonBlocking) {
        // The Apache SAPI on this XAMPP install has no curl extension, so
        // function_exists('curl_init') is false in the web app but true in
        // CLI. Both transports must therefore behave identically.
        if (function_exists('curl_init')) {
            return textBeePostViaCurl($url, $body, 'background');
        }
        return textBeePostViaShellCurl($url, $body);
    }

    return textBeePostViaStream($url, $body);
}

/**
 * Preferred transport: the PHP curl extension. Only available in CLI here.
 */
function textBeePostViaCurl(string $url, string $body, string $label = 'background')
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . TEXTBEE_API_KEY,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        error_log("TextBee SMS ($label) transport error: " . $err);
        return ['success' => false, 'error' => $err];
    }
    return textBeeInterpretResponse((string)$raw, $httpCode, $label);
}

/**
 * Fallback transport for SAPIs without the curl extension, i.e. the website.
 *
 * Two Windows traps are handled here:
 *  1. cmd.exe does not strip single quotes, so curl's -w '%{http_code}' yields
 *     a QUOTED "401" and $code[0] is "'" instead of "2". The old version thus
 *     reported failure even when the send succeeded. Double quotes are used
 *     and stray quotes are stripped before parsing.
 *  2. escapeshellarg() on Windows rewrites `"` (and % and !) to spaces, which
 *     corrupts a JSON payload. The body is therefore staged in a temp file and
 *     passed with --data-binary @file, sidestepping shell quoting entirely.
 */
function textBeePostViaShellCurl(string $url, string $body)
{
    $label = 'shell fallback';
    $curlBin = textBeeFindCurlBinary();
    if ($curlBin === null) {
        error_log('TextBee SMS (shell fallback): curl binary not found on PATH');
        return ['success' => false, 'error' => 'curl is not available on this server'];
    }

    $isWindows = DIRECTORY_SEPARATOR === '\\';
    $nullDevice = $isWindows ? 'NUL' : '/dev/null';
    $reqFile = @tempnam(sys_get_temp_dir(), 'smsreq_');
    $resFile = @tempnam(sys_get_temp_dir(), 'smsres_');
    if ($reqFile === false) {
        return ['success' => false, 'error' => 'could not create a temporary file for the SMS request'];
    }
    if (@file_put_contents($reqFile, $body) === false) {
        @unlink($reqFile);
        return ['success' => false, 'error' => 'could not write the SMS request payload'];
    }

    $cmd = escapeshellarg($curlBin)
        . ' -s -o ' . escapeshellarg($resFile !== false ? $resFile : $nullDevice)
        . ' -w "%{http_code}"'
        . ' -X POST ' . escapeshellarg($url)
        . ' -H ' . escapeshellarg('Content-Type: application/json')
        . ' -H ' . escapeshellarg('x-api-key: ' . TEXTBEE_API_KEY)
        . ' --data-binary ' . escapeshellarg('@' . $reqFile)
        . ' --connect-timeout 3 --max-time 8'
        . ' 2>' . $nullDevice;

    $rawOut = (string)@shell_exec($cmd);

    // Tolerate quoted / padded output from any shell.
    $code = (int)trim(trim($rawOut), "\"' \t\r\n");

    $raw = '';
    if ($resFile !== false && is_file($resFile)) {
        $raw = (string)@file_get_contents($resFile);
        @unlink($resFile);
    }
    @unlink($reqFile);

    if ($code === 0) {
        // Could not even reach the provider (DNS, TLS, timeout, missing curl).
        error_log("TextBee SMS ($label): no HTTP response. raw=" . substr(trim($rawOut), 0, 120));
        return ['success' => false, 'error' => 'the SMS gateway could not be reached'];
    }

    return textBeeInterpretResponse($raw, $code, $label);
}

/**
 * Locate a usable curl binary for the shell fallback.
 */
function textBeeFindCurlBinary(): ?string
{
    $candidates = [];
    if (!empty($_SERVER['PATH']) || !empty($_SERVER['Path'])) {
        $path = $_SERVER['Path'] ?? $_SERVER['PATH'];
        foreach (explode(PATH_SEPARATOR, (string)$path) as $dir) {
            $candidates[] = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'curl.exe';
            $candidates[] = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'curl';
        }
    }
    $candidates[] = 'curl.exe';
    $candidates[] = 'curl';

    foreach ($candidates as $candidate) {
        if ($candidate === '' || $candidate === 'curl' || $candidate === 'curl.exe') {
            $found = @shell_exec('curl --version 2>' . (DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null'));
            if (is_string($found) && stripos($found, 'curl') !== false) {
                return 'curl';
            }
            continue;
        }
        if (is_file($candidate)) {
            return $candidate;
        }
    }
    return null;
}

/**
 * Turn a TextBee HTTP response into the array shape callers expect.
 * Shared by every transport so error reporting is consistent.
 */
function textBeeInterpretResponse(string $raw, int $httpCode, string $label = 'background')
{
    if ($httpCode >= 200 && $httpCode < 300) {
        $data = json_decode($raw, true);
        $batchId = is_array($data) ? ($data['data']['smsBatchId'] ?? null) : null;
        return [
            'success' => true,
            'message' => 'SMS sent successfully',
            'sms_id' => $batchId,
            'http_code' => $httpCode,
            'provider_status' => 'Queued',
        ];
    }

    // Keep the provider's own reason (e.g. "Invalid recipients") instead of a
    // bare status code so failures are diagnosable from the UI.
    $reason = smsProviderErrorReason($raw, $httpCode);
    error_log("TextBee SMS ($label) API error: HTTP $httpCode - $reason");
    return [
        'success' => false,
        'error' => "TextBee error: $reason",
        'http_code' => $httpCode,
    ];
}

/**
 * Blocking transport used for OTP codes, where the caller needs a real answer.
 * Pure PHP streams, so it works even without the curl extension.
 */
function textBeePostViaStream(string $url, string $body)
{
    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n" .
                    "x-api-key: " . TEXTBEE_API_KEY . "\r\n",
        'content' => $body,
        'timeout' => 30,
        'ignore_errors' => true,
    ]]);

    $response = @file_get_contents($url, false, $context);

    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
        $status = (int)$m[1];
    }

    if ($response === false) {
        $err = error_get_last();
        $msg = $err['message'] ?? 'Network error while contacting TextBee';
        error_log("TextBee SMS transport error: " . $msg);
        return ['success' => false, 'error' => $msg];
    }

    return textBeeInterpretResponse((string)$response, $status, 'stream');
}

function testTextBeeConnection() {
    if (!defined('TEXTBEE_API_KEY') || TEXTBEE_API_KEY === '' || TEXTBEE_API_KEY === 'YOUR_TEXBEE_API_KEY') {
        return ['success' => false, 'error' => 'TextBee API key not configured'];
    }

    $context = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => "Accept: application/json\r\n" .
                    "x-api-key: " . TEXTBEE_API_KEY . "\r\n",
        'timeout' => 30,
        'ignore_errors' => true,
    ]]);

    $response = @file_get_contents('https://api.textbee.dev/api/v1/gateway/devices', false, $context);

    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
        $status = (int)$m[1];
    }

    if ($response === false) {
        $err = error_get_last();
        $msg = $err['message'] ?? 'Network error while contacting TextBee';
        error_log("TextBee connection test transport error: " . $msg);
        return ['success' => false, 'error' => $msg];
    }

    $data = json_decode($response, true);

    if ($status >= 200 && $status < 300 && is_array($data)) {
        return ['success' => true, 'status' => $status, 'response' => $response];
    }

    $desc = $data['error'] ?? $data['message'] ?? ('HTTP ' . $status);
    error_log("TextBee connection test failed (HTTP $status): " . $desc);
    return ['success' => false, 'status' => $status, 'error' => $desc];
}

function formatPhoneNumber($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (substr($phone, 0, 2) == '63') {
        return $phone;
    }
    
    if (substr($phone, 0, 1) == '9') {
        return '63' . $phone;
    }
    
    if (substr($phone, 0, 2) == '09') {
        return '63' . substr($phone, 1);
    }
    
    return $phone;
}

function sendAppointmentConfirmationSMS($phone, $patientName, $appointmentDate, $appointmentTime, $treatment, $isNewPatient = false, $nonBlocking = false) {
    $formattedDate = date('F j, Y', strtotime($appointmentDate));
    $formattedTime = date('g:i A', strtotime($appointmentTime));

    if ($isNewPatient) {
        $message = "Hello {$patientName}! Your appointment has been confirmed for {$formattedDate} at {$formattedTime} (Treatment: {$treatment}). See you soon! - RF Dental Clinic";
    } else {
        $message = "Thanks for booking with us! Your appointment is confirmed for {$formattedDate} at {$formattedTime} (Treatment: {$treatment}). See you soon! - RF Dental Clinic";
    }

    return sendSMS($phone, $message, $nonBlocking);
}

function sendAppointmentCancellationSMS($phone, $patientName, $appointmentDate, $cancelReason, $nonBlocking = false) {
    $formattedDate = date('F j, Y', strtotime($appointmentDate));
    
    $message = "Sorry to inform that your appointment on {$formattedDate} has been cancelled. Reason: {$cancelReason}. Please contact us if you have any questions. - RF Dental Clinic";
    
    return sendSMS($phone, $message, $nonBlocking);
}

function sendRegistrationWelcomeSMS($phone, $firstName) {
    $message = "Welcome to RF Dental Clinic, {$firstName}! Your account has been created successfully. You can now book appointments online. See you soon!";
    
    return sendSMS($phone, $message);
}

function buildRegistrationWelcomeSms(string $firstName): string
{
    $firstName = trim($firstName) !== '' ? trim($firstName) : 'there';
    return "Welcome to RF Dental Clinic, {$firstName}! Your account is ready and you can now book appointments online. - RF Dental Clinic";
}

// ---------------------------------------------------------------------------
// Message builders
//
// Kept next to the sender so the wording of every patient-facing text lives in
// one file. These return the text only; delivery is handled by
// sendPatientSmsTracked(), which applies preferences and writes the audit row.
// ---------------------------------------------------------------------------

function buildSmsAppointmentDate(string $appointmentDate): string
{
    $appointmentDate = trim($appointmentDate);
    if ($appointmentDate === '') {
        return 'your appointment date';
    }
    $ts = strtotime($appointmentDate);
    return $ts ? date('F j, Y', $ts) : $appointmentDate;
}

function buildSmsAppointmentTime(string $appointmentTime): string
{
    $appointmentTime = trim($appointmentTime);
    if ($appointmentTime === '') {
        return '';
    }
    $ts = strtotime($appointmentTime);
    return $ts ? date('g:i A', $ts) : $appointmentTime;
}

function buildAppointmentApprovalSms(string $patientName, string $appointmentDate, string $appointmentTime, string $treatment, bool $isNewPatient = false): string
{
    $treatment = trim($treatment) !== '' ? $treatment : 'General Checkup';
    $when = trim(buildSmsAppointmentTime($appointmentTime));
    $schedule = buildSmsAppointmentDate($appointmentDate) . ($when !== '' ? " at {$when}" : '');

    $greeting = $isNewPatient
        ? "Hello {$patientName}! Welcome to RF Dental Clinic. Your appointment is confirmed"
        : "Thanks for booking with us! Your appointment is confirmed";

    return "{$greeting} for {$treatment} on {$schedule}. - RF Dental Clinic";
}

function buildAppointmentDeclineSms(string $patientName, string $appointmentDate, string $reason): string
{
    $when = buildSmsAppointmentDate($appointmentDate);
    $reason = trim($reason) !== '' ? trim($reason) : 'no reason given';
    return "Hi {$patientName}, RF Dental Clinic could not confirm your appointment on {$when}. Reason: {$reason}. Please book another slot. - RF Dental Clinic";
}

function buildAppointmentCancellationSms(string $patientName, string $appointmentDate, string $serviceName, string $reason): string
{
    $when = buildSmsAppointmentDate($appointmentDate);
    $serviceName = trim($serviceName) !== '' ? trim($serviceName) : '';
    $subject = $serviceName !== '' ? "{$serviceName} appointment" : 'appointment';
    $reason = trim($reason) !== '' ? trim($reason) : 'No reason provided';
    return "Hi {$patientName}, your {$subject} on {$when} at RF Dental Clinic has been cancelled. Reason: {$reason}. Please contact us to rebook. - RF Dental Clinic";
}

function buildAppointmentReminderSms(string $patientName, string $appointmentDate, string $appointmentTime, string $treatment, int $hoursBefore): string
{
    $formattedDate = date('F j, Y', strtotime($appointmentDate));
    $formattedTime = date('g:i A', strtotime($appointmentTime));
    $treatment = trim($treatment) !== '' ? $treatment : 'General Checkup';

    if ($hoursBefore === 2) {
        return "Reminder: Hi {$patientName}! Your appointment at RF Dental Clinic is today at {$formattedTime} ({$treatment}). See you soon!";
    }
    return "Reminder: Hi {$patientName}! You have an appointment at RF Dental Clinic on {$formattedDate} at {$formattedTime} ({$treatment}). See you soon!";
}

function sendAppointmentReminderSMS($phone, $patientName, $appointmentDate, $appointmentTime, $treatment, int $hoursBefore, bool $nonBlocking = false) {
    return sendSMS($phone, buildAppointmentReminderSms($patientName, $appointmentDate, $appointmentTime, $treatment, $hoursBefore), $nonBlocking);
}

// ---------------------------------------------------------------------------
// Audit log
// ---------------------------------------------------------------------------

/**
 * Create the sms_log table on demand so auditing never blocks a send.
 */
function ensureSmsLogSchema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sms_log` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `phone` VARCHAR(20) NOT NULL,
        `message` TEXT NOT NULL,
        `category` VARCHAR(50) NOT NULL DEFAULT 'general',
        `status` ENUM('sent','failed','skipped') NOT NULL DEFAULT 'failed',
        `provider` VARCHAR(30) NOT NULL DEFAULT 'textbee',
        `provider_message_id` VARCHAR(64) DEFAULT NULL,
        `error_message` VARCHAR(255) DEFAULT NULL,
        `patient_id` INT(11) DEFAULT NULL,
        `appointment_id` INT(11) DEFAULT NULL,
        `requested_by` INT(11) DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_sms_log_phone` (`phone`),
        KEY `idx_sms_log_created` (`created_at`),
        KEY `idx_sms_log_category` (`category`),
        KEY `idx_sms_log_appointment` (`appointment_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ready = true;
}

/**
 * Record one outbound (or deliberately skipped) SMS.
 * Never throws: a logging failure must not break the caller's workflow.
 */
function logSms(PDO $pdo, array $entry): void
{
    try {
        ensureSmsLogSchema($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO sms_log (phone, message, category, status, provider, provider_message_id,
                                  error_message, patient_id, appointment_id, requested_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (string)($entry['phone'] ?? ''),
            (string)($entry['message'] ?? ''),
            (string)($entry['category'] ?? 'general'),
            (string)($entry['status'] ?? 'failed'),
            (string)($entry['provider'] ?? 'textbee'),
            isset($entry['provider_message_id']) ? (string)$entry['provider_message_id'] : null,
            isset($entry['error_message']) ? substr((string)$entry['error_message'], 0, 255) : null,
            !empty($entry['patient_id']) ? (int)$entry['patient_id'] : null,
            !empty($entry['appointment_id']) ? (int)$entry['appointment_id'] : null,
            !empty($entry['requested_by']) ? (int)$entry['requested_by'] : null,
        ]);
    } catch (Throwable $e) {
        error_log('logSms failed: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// Configuration + per-patient preference
// ---------------------------------------------------------------------------

/**
 * True when TextBee has both an API key and a device id.
 */
function smsIsConfigured(): bool
{
    return smsHasApiKey() && smsHasDeviceId();
}

function smsHasApiKey(): bool
{
    return defined('TEXTBEE_API_KEY')
        && TEXTBEE_API_KEY !== ''
        && TEXTBEE_API_KEY !== 'YOUR_TEXBEE_API_KEY';
}

function smsHasDeviceId(): bool
{
    return defined('TEXTBEE_DEVICE_ID')
        && TEXTBEE_DEVICE_ID !== ''
        && TEXTBEE_DEVICE_ID !== 'YOUR_TEXBEE_DEVICE_ID';
}

/**
 * Clinic-wide kill switch (settings.setting_key = 'sms_enabled').
 * Absent or empty values are treated as ON so a missing row never silently
 * disables patient messaging.
 */
function smsGloballyEnabled(PDO $pdo): bool
{
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'sms_enabled' LIMIT 1");
        $stmt->execute();
        $value = $stmt->fetchColumn();
    } catch (Throwable $e) {
        return true;
    }
    if ($value === false || $value === null || trim((string)$value) === '') {
        return true;
    }
    return !in_array(strtolower(trim((string)$value)), ['0', 'off', 'false', 'no', 'disabled'], true);
}

/**
 * Read users.sms_notifications for the login behind a patient record.
 * Returns null when the user row (or the column) is missing, so callers can
 * tell "opted out" apart from "unknown".
 */
function patientSmsPreference(PDO $pdo, int $patientId): ?bool
{
    if ($patientId <= 0) {
        return null;
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT sms_notifications FROM users
             WHERE patient_id = ? OR id = ?
             ORDER BY (patient_id = ?) DESC
             LIMIT 1'
        );
        $stmt->execute([$patientId, $patientId, $patientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('patientSmsPreference lookup failed: ' . $e->getMessage());
        return null;
    }
    if (!$row || !array_key_exists('sms_notifications', $row)) {
        return null;
    }
    return (int)$row['sms_notifications'] === 1;
}

/**
 * Best available phone for a patient: users.phone first (that is what the
 * verification flow keeps current), then patients.phone.
 */
function patientSmsPhone(PDO $pdo, int $patientId, ?string $fallback = null): string
{
    $fallback = trim((string)$fallback);
    if ($patientId <= 0) {
        return $fallback;
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT u.phone AS user_phone, p.phone AS patient_phone
             FROM patients p
             LEFT JOIN users u ON u.patient_id = p.id
             WHERE p.id = ?
             LIMIT 1'
        );
        $stmt->execute([$patientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            foreach (['user_phone', 'patient_phone'] as $key) {
                $candidate = trim((string)($row[$key] ?? ''));
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }
    } catch (Throwable $e) {
        error_log('patientSmsPhone lookup failed: ' . $e->getMessage());
    }
    return $fallback;
}

/**
 * Preference-aware SMS for a patient. Respects the patient's Notification
 * Preferences toggle and the clinic-wide switch, and always leaves an audit
 * trail so delivery can be verified after the fact.
 *
 * @param bool $ignorePreference Use for security codes (e.g. the phone-change
 *        OTP) that must be delivered even when appointment SMS is switched off.
 */
function sendPatientSmsTracked(
    PDO $pdo,
    int $patientId,
    string $message,
    string $category = 'general',
    ?int $appointmentId = null,
    bool $ignorePreference = false,
    bool $nonBlocking = true,
    ?string $phoneOverride = null
): bool {
    $phone = ($phoneOverride !== null && trim($phoneOverride) !== '')
        ? trim($phoneOverride)
        : patientSmsPhone($pdo, $patientId);

    if ($phone === '') {
        logSms($pdo, [
            'phone' => '', 'message' => $message, 'category' => $category, 'status' => 'skipped',
            'error_message' => 'No phone number on file', 'patient_id' => $patientId,
            'appointment_id' => $appointmentId,
        ]);
        return false;
    }

    if (!$ignorePreference) {
        if (!smsGloballyEnabled($pdo)) {
            logSms($pdo, [
                'phone' => $phone, 'message' => $message, 'category' => $category, 'status' => 'skipped',
                'error_message' => 'Clinic-wide SMS is disabled', 'patient_id' => $patientId,
                'appointment_id' => $appointmentId,
            ]);
            return false;
        }
        if (patientSmsPreference($pdo, $patientId) === false) {
            logSms($pdo, [
                'phone' => $phone, 'message' => $message, 'category' => $category, 'status' => 'skipped',
                'error_message' => 'Patient opted out of SMS notifications', 'patient_id' => $patientId,
                'appointment_id' => $appointmentId,
            ]);
            return false;
        }
    }

    if (!smsIsConfigured()) {
        logSms($pdo, [
            'phone' => $phone, 'message' => $message, 'category' => $category, 'status' => 'failed',
            'error_message' => 'TextBee not configured', 'patient_id' => $patientId,
            'appointment_id' => $appointmentId,
        ]);
        return false;
    }

    $result = sendSMS($phone, $message, $nonBlocking);
    $ok = !empty($result['success']);
    logSms($pdo, [
        'phone' => $phone, 'message' => $message, 'category' => $category,
        'status' => $ok ? 'sent' : 'failed',
        'provider_message_id' => $result['sms_id'] ?? null,
        'error_message' => $ok ? null : ($result['error'] ?? 'Unknown error'),
        'patient_id' => $patientId, 'appointment_id' => $appointmentId,
    ]);
    return $ok;
}

/**
 * Human-readable reason an SMS was not delivered, for staff-facing messages.
 */
function describeSmsSkipReason(PDO $pdo, int $patientId): string
{
    if (!smsIsConfigured()) {
        return 'the SMS gateway is not configured';
    }
    if (!smsGloballyEnabled($pdo)) {
        return 'clinic-wide SMS is switched off';
    }
    if (patientSmsPreference($pdo, $patientId) === false) {
        return 'the patient has SMS notifications switched off';
    }
    if (patientSmsPhone($pdo, $patientId) === '') {
        return 'the patient has no phone number on file';
    }
    // Returned without a trailing period: callers embed this mid-sentence.
    return rtrim(describeSmsTransportError(smsLastError()), " .");
}
