<?php
/** Password changes remain pending until a code delivered to an account contact is verified. */
function patientPasswordContacts(array $user): array
{
    $contacts = [];
    $email = trim((string)($user['email'] ?? ''));
    $phone = trim((string)($user['phone'] ?? ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) $contacts['email'] = $email;
    if (preg_match('/^(?:09\d{9}|9\d{9}|\+?639\d{9})$/', preg_replace('/[\s()-]/', '', $phone))) $contacts['phone'] = $phone;
    return $contacts;
}

function patientPasswordChange(PDO $pdo, array $user, array &$session, array $input, ?callable $sender = null): array
{
    $action = $input['action'] ?? '';
    $fail = static function ($message) { return ['message' => $message, 'type' => 'error']; };
    if ($action === 'cancel_password_otp') {
        unset($session['patient_password_change']);
        return ['message' => 'Password change cancelled.', 'type' => 'success'];
    }
    if ($action === 'change_password') {
        unset($session['patient_password_change']);
        if (!password_verify((string)($input['current_password'] ?? ''), $user['password'])) return $fail('Current password is incorrect.');
        $password = (string)($input['new_password'] ?? '');
        if (strlen($password) < 8) return $fail('Password must be at least 8 characters.');
        if ($password !== ($input['confirm_password'] ?? '')) return $fail('New passwords do not match.');
        if (!patientPasswordContacts($user)) return $fail('Add a valid email address or mobile number to your profile before changing your password.');
        $session['patient_password_change'] = [
            'user_id' => (int)$user['id'], 'original' => $user['password'],
            'password' => password_hash($password, PASSWORD_DEFAULT), 'expires' => time() + 600,
            'attempts' => 0,
        ];
        return ['message' => 'Choose where to receive your verification code.', 'type' => 'success'];
    }
    $pending = &$session['patient_password_change'];
    if (!$pending || $pending['user_id'] !== (int)$user['id'] || $pending['expires'] <= time() || !hash_equals($pending['original'], $user['password'])) {
        unset($session['patient_password_change']);
        return $fail('The password change has expired. Please start again.');
    }
    if ($action === 'send_password_otp') {
        $contacts = patientPasswordContacts($user);
        $channel = (string)($input['password_channel'] ?? '');
        if (!isset($contacts[$channel])) return $fail('Choose an available email address or mobile number.');
        if (($pending['sent_at'] ?? 0) > time() - 60) return $fail('Please wait 60 seconds before sending another code.');
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $sender = $sender ?? 'sendAccountVerification';
        $pending['sent_at'] = time();
        if (!$sender($contacts[$channel], $user['first_name'] ?? 'Patient', $code, 'password_change')) return $fail('Unable to send the verification code. Please try again in 60 seconds.');
        $pending['otp'] = password_hash($code, PASSWORD_DEFAULT);
        $pending['otp_expires'] = time() + 180;
        $pending['destination'] = $contacts[$channel];
        $pending['channel'] = $channel;
        return ['message' => 'A verification code was sent to your ' . ($channel === 'email' ? 'email address.' : 'mobile number.'), 'type' => 'success'];
    }
    if ($action === 'verify_password_otp') {
        if (empty($pending['otp']) || ($pending['otp_expires'] ?? 0) <= time()) {
            unset($pending['otp']);
            return $fail('The code has expired. Please send a new code.');
        }
        $pending['attempts']++;
        $code = trim((string)($input['password_otp'] ?? ''));
        if (!preg_match('/^\d{6}$/', $code) || !password_verify($code, $pending['otp'])) {
            if ($pending['attempts'] >= 5) {
                unset($session['patient_password_change']);
                return $fail('Too many incorrect codes. Please start again.');
            }
            return $fail('The verification code is invalid.');
        }
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ? AND password = ?');
        $stmt->execute([$pending['password'], $user['id'], $pending['original']]);
        unset($session['patient_password_change']);
        if ($stmt->rowCount() !== 1) return $fail('Your password has changed elsewhere. Please start again.');
        return ['message' => 'Password changed successfully!', 'type' => 'success'];
    }
    return $fail('Invalid password change request.');
}
