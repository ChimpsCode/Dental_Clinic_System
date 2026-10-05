<?php
require_once __DIR__ . '/account_contact.php';

function sendAccountRecoveryCode(PDO $pdo, string $raw): array
{
    $isEmail = strpos($raw, '@') !== false;
    $sanitized = $isEmail ? sanitize_email($raw) : sanitize_phone($raw);
    if (!$sanitized['ok']) {
        return ['success' => false, 'message' => $sanitized['error']];
    }
    if (time() - (int)($_SESSION['reset_code_sent_at'] ?? 0) < 60) {
        return ['success' => false, 'message' => 'Please wait one minute before requesting another code.'];
    }
    $destination = $sanitized['value'];
    if ($isEmail) {
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ? AND COALESCE(is_archived, 0) = 0 LIMIT 2");
        $stmt->execute([$destination]);
    } else {
        $international = '63' . substr($destination, 1);
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE (phone IN (?, ?, ?, ?) OR username = ?) AND LOWER(role) = 'patient' AND COALESCE(is_archived, 0) = 0 LIMIT 2");
        $stmt->execute([$destination, $international, '+' . $international, substr($destination, 1), $destination]);
    }
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($users) !== 1) {
        return ['success' => false, 'message' => count($users) > 1
            ? 'This contact is shared by multiple accounts. Use your email or contact the clinic.'
            : 'No account was found for this email or mobile number.'];
    }
    unset($_SESSION['reset_code'], $_SESSION['reset_code_verified'], $_SESSION['reset_user_id']);
    $_SESSION['reset_code_sent_at'] = time();
    $code = (string)random_int(100000, 999999);
    if (!sendAccountVerification($destination, $users[0]['username'], $code, 'password_reset')) {
        return ['success' => false, 'message' => 'Unable to send the verification code. Please try again later or contact the clinic.'];
    }
    $_SESSION['reset_email'] = $destination;
    $_SESSION['reset_user_id'] = (int)$users[0]['id'];
    $_SESSION['reset_code'] = $code;
    $_SESSION['reset_code_attempts'] = 0;
    return ['success' => true, 'message' => $isEmail ? 'Code sent. Check your email.' : 'Code sent. Check your SMS messages.', 'step' => 2];
}

function accountRecoveryAttemptAllowed(): bool
{
    $_SESSION['reset_code_attempts'] = (int)($_SESSION['reset_code_attempts'] ?? 0) + 1;
    if ($_SESSION['reset_code_attempts'] > 5) {
        unset($_SESSION['reset_code'], $_SESSION['reset_code_verified'], $_SESSION['reset_user_id']);
        return false;
    }
    return true;
}
