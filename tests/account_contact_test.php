<?php
// Run: php tests/account_contact_test.php (uses only an in-memory database).
if (PHP_SAPI !== 'cli') exit('CLI only');
require_once __DIR__ . '/../includes/account_recovery.php';
$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function rejects(callable $operation, string $message): void {
    try { $operation(); } catch (RuntimeException $e) { check(true, $message); return; }
    check(false, $message);
}
$post = [
    'first_name' => 'Sample', 'last_name' => 'Patient', 'phone' => '09123456789',
    'gender' => 'female', 'date_of_birth' => '1995-01-01',
    'emergency_contact_name' => 'Sample Guardian', 'emergency_contact_phone' => '09987654321',
    'emergency_contact_relationship' => 'Parent',
];
check(!sanitize_patient_registration_input($post)['ok'], 'Default email registration must require email');
$post['contact_method'] = 'phone';
$result = sanitize_patient_registration_input($post, ['require_dob' => true]);
check($result['ok'] && $result['data']['email'] === '', 'Mobile registration must allow no email');
check(accountContactIdentifier($result['data']) === '09123456789', 'Mobile number must become login');
$post['phone'] = '+639123456789';
check(sanitize_patient_registration_input($post)['data']['phone'] === '09123456789', 'International number must normalize');
check(sanitize_login_identifier('+639123456789')['value'] === '09123456789', 'International login must normalize');
$post['contact_method'] = 'invalid';
check(!sanitize_patient_registration_input($post)['ok'], 'Unknown registration method must fail');
$post['contact_method'] = 'phone';
$post['phone'] = '123';
check(!sanitize_patient_registration_input($post)['ok'], 'Invalid mobile must fail');
$post['phone'] = '09123456789';
$post['contact_method'] = 'email';
$post['email'] = 'Sample@Example.com';
check(sanitize_patient_registration_input($post)['data']['email'] === 'sample@example.com', 'Email registration must keep working');

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, email TEXT, phone TEXT, is_archived INTEGER DEFAULT 0, role TEXT DEFAULT 'Patient')");
$mobile = ['contact_method' => 'phone', 'email' => '', 'phone' => '09123456789'];
$pdo->exec("INSERT INTO users (id, username, email, phone, is_archived) VALUES (1, 'someone', NULL, NULL, 0)");
assertPatientContactAvailable($pdo, $mobile);
check(true, 'An unrelated empty email must not block mobile registration');
foreach (['09123456789', '639123456789', '+639123456789', '9123456789'] as $alias) {
    $pdo->prepare('UPDATE users SET phone = ? WHERE id = 1')->execute([$alias]);
    rejects(fn() => assertPatientContactAvailable($pdo, $mobile), 'Existing number spelling must block duplicate login: ' . $alias);
}
assertPatientContactAvailable($pdo, $mobile, 1);
check(true, 'An account must be allowed to retain its own phone');
$pdo->exec("UPDATE users SET username = '09123456789', phone = '09123456789' WHERE id = 1");
$emailAccount = ['contact_method' => 'email', 'email' => 'other@example.com', 'phone' => '09123456789'];
rejects(fn() => assertPatientContactAvailable($pdo, $emailAccount), 'Email registration must not reuse a reserved mobile login');
$pdo->exec("UPDATE users SET username = 'family@example.com', email = 'family@example.com' WHERE id = 1");
assertPatientContactAvailable($pdo, $emailAccount);
check(true, 'Email accounts may still share ordinary contact numbers');
$pdo->exec("INSERT INTO users (id, username, email, phone, is_archived) VALUES (2, 'other@example.com', 'other@example.com', '09123456789', 0)");
$_SESSION = [];
check(!sendAccountRecoveryCode($pdo, '09123456789')['success'], 'Recovery must reject shared numbers without sending');
check(!sendAccountRecoveryCode($pdo, 'missing@example.com')['success'], 'Recovery must reject unknown email without sending');
$_SESSION['reset_code_sent_at'] = time();
check(!sendAccountRecoveryCode($pdo, 'other@example.com')['success'], 'Recovery must throttle resends');

$_SESSION = ['reg_otp' => '123456', 'reg_otp_expires' => time() + 180, 'reg_data' => $mobile];
check(registrationOtpReusable($mobile), 'Identical registration may reuse its unexpired code');
check(!registrationOtpReusable(array_merge($mobile, ['phone' => '09987654321'])), 'Recipient changes must never reuse an old code');
$_SESSION['reg_otp_expires'] = time() - 1;
check(!registrationOtpReusable($mobile), 'Expired registration code must not be reused');
$_SESSION['reg_otp_sent_at'] = time();
rejects(fn() => registrationOtpThrottle(), 'Registration must throttle resends');
$_SESSION = [];
for ($i = 0; $i < 5; $i++) check(registrationOtpAttemptAllowed(), 'First five verification attempts are permitted');
check(!registrationOtpAttemptAllowed(), 'Sixth verification attempt must invalidate the code');
$_SESSION = ['reset_user_id' => 1, 'reset_code_verified' => true, 'reset_code_attempts' => 5];
check(!accountRecoveryAttemptAllowed() && !isset($_SESSION['reset_user_id']), 'Recovery attempt limit must clear authorization');
echo "$checks account contact checks passed.\n";
