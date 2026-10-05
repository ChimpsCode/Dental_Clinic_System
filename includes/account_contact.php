<?php
require_once __DIR__ . '/input_sanitize.php';

function accountContactIdentifier(array $data): string
{
    return ($data['contact_method'] ?? 'email') === 'phone' ? $data['phone'] : $data['email'];
}

/** Phone login is reserved for a single account, including legacy contact numbers. */
function assertPatientContactAvailable(PDO $pdo, array $data, int $excludeId = 0): void
{
    $identifier = accountContactIdentifier($data);
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id <> ? AND (username = ? OR (? <> '' AND email = ?)) LIMIT 1");
    $stmt->execute([$excludeId, $identifier, $data['email'], $data['email']]);
    if ($stmt->fetchColumn()) {
        throw new RuntimeException('This email or mobile number is already registered. Please log in or contact the clinic.');
    }
    $phone = $data['phone'];
    $international = '63' . substr($phone, 1);
    if (($data['contact_method'] ?? 'email') === 'phone') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id <> ? AND (phone IN (?, ?, ?, ?) OR username IN (?, ?, ?, ?)) LIMIT 1");
    } else {
        // Email accounts may share contact numbers, unless one is a mobile login.
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id <> ? AND username IN (?, ?, ?, ?) LIMIT 1");
    }
    $args = [$excludeId, $phone, $international, '+' . $international, substr($phone, 1)];
    if (($data['contact_method'] ?? 'email') === 'phone') {
        $args = array_merge($args, [$phone, $international, '+' . $international, substr($phone, 1)]);
    }
    $stmt->execute($args);
    if ($stmt->fetchColumn()) {
        throw new RuntimeException('This mobile number is already associated with an account. Use another number or contact the clinic.');
    }
}

function sendAccountVerification(string $destination, string $name, string $code, string $purpose = 'registration'): bool
{
    if (strpos($destination, '@') !== false) {
        require_once __DIR__ . '/send_otp_email.php';
        return sendOtpEmail($destination, $name, $code, $purpose);
    }
    require_once __DIR__ . '/sms_functions.php';
    $label = $purpose === 'registration' ? 'registration' : ($purpose === 'password_change' ? 'password change' : 'password reset');
    $result = sendSMS($destination, "RF Dental Clinic: Your $label code is $code. It expires in 3 minutes. Do not share this code.");
    return !empty($result['success']);
}

/** Serialize identity writes even on installations without a unique username index. */
function lockAccountContactWrites(PDO $pdo): void
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') return;
    $stmt = $pdo->query("SELECT GET_LOCK('rf_patient_account_contact', 10)");
    if ((int)$stmt->fetchColumn() !== 1) {
        throw new RuntimeException('Another account update is in progress. Please try again.');
    }
    // Connections are nonpersistent; MySQL also releases this lock when a response exits.
}

/** Bind an unexpired code to the exact registration details, never a different recipient. */
function registrationOtpReusable(array $data): bool
{
    $stored = $_SESSION['reg_data'] ?? null;
    if (!is_array($stored)) return false;
    ksort($data);
    ksort($stored);
    return !empty($_SESSION['reg_otp'])
        && (int)($_SESSION['reg_otp_expires'] ?? 0) > time()
        && $stored === $data;
}

function registrationOtpThrottle(): void
{
    if (time() - (int)($_SESSION['reg_otp_sent_at'] ?? 0) < 60) {
        throw new RuntimeException('Please wait one minute before requesting another verification code.');
    }
}

function registrationOtpAttemptAllowed(): bool
{
    $_SESSION['reg_otp_attempts'] = (int)($_SESSION['reg_otp_attempts'] ?? 0) + 1;
    if ($_SESSION['reg_otp_attempts'] > 5) {
        unset($_SESSION['reg_otp'], $_SESSION['reg_data']);
        return false;
    }
    return true;
}
