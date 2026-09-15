<?php
/**
 * Shared input sanitization for public welcome-page forms.
 * Rejects HTML/XSS payloads; stores plain text only (do not htmlspecialchars on save).
 */

function input_str_len(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function contains_html(string $value): bool
{
    if ($value === '') {
        return false;
    }
    if (preg_match('/<[^>]*>/u', $value)) {
        return true;
    }
    if (strpbrk($value, '<>') !== false) {
        return true;
    }
    if (preg_match('/on\w+\s*=/iu', $value)) {
        return true;
    }
    if (preg_match('/javascript\s*:/iu', $value)) {
        return true;
    }
    return false;
}

function sanitize_ok(string $value): array
{
    return ['ok' => true, 'value' => $value, 'error' => null];
}

function sanitize_fail(string $error): array
{
    return ['ok' => false, 'value' => '', 'error' => $error];
}

function sanitize_name($raw, bool $required = true, int $maxLen = 80, string $label = 'Name'): array
{
    $value = trim((string)$raw);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    if ($value === '') {
        return $required ? sanitize_fail($label . ' is required.') : sanitize_ok('');
    }
    if (input_str_len($value) > $maxLen) {
        return sanitize_fail($label . ' is too long.');
    }
    if (!preg_match("/^[\\p{L}\\s\\-]+$/u", $value)) {
        return sanitize_fail($label . ' may only contain letters, spaces, and hyphens.');
    }

    return sanitize_ok($value);
}

function sanitize_email($raw, bool $required = true): array
{
    $value = strtolower(trim((string)$raw));

    if ($value === '') {
        return $required ? sanitize_fail('Email is required.') : sanitize_ok('');
    }
    if (preg_match('/[<>()\/"]/', $value)) {
        return sanitize_fail('Please enter a valid email address.');
    }
    if (input_str_len($value) > 254) {
        return sanitize_fail('Email is too long.');
    }
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return sanitize_fail('Please enter a valid email address.');
    }

    return sanitize_ok($value);
}

function sanitize_phone($raw, bool $required = true): array
{
    $digits = preg_replace('/\D+/', '', (string)$raw) ?? '';

    if ($digits === '') {
        return $required ? sanitize_fail('Phone number is required.') : sanitize_ok('');
    }
    if (!preg_match('/^09\d{9}$/', $digits)) {
        return sanitize_fail('Enter a valid 11-digit mobile number starting with 09.');
    }

    return sanitize_ok($digits);
}

function sanitize_message($raw, bool $required = true, int $maxLen = 2000, string $label = 'Message'): array
{
    $value = trim((string)$raw);

    if ($value === '') {
        return $required ? sanitize_fail($label . ' is required.') : sanitize_ok('');
    }
    if (contains_html($value)) {
        return sanitize_fail('This field contains invalid characters.');
    }
    if (input_str_len($value) > $maxLen) {
        return sanitize_fail($label . ' is too long.');
    }

    $clean = trim(strip_tags($value));
    if ($clean === '') {
        return $required ? sanitize_fail($label . ' is required.') : sanitize_ok('');
    }

    return sanitize_ok($clean);
}

function sanitize_address($raw, bool $required = false, int $maxLen = 255): array
{
    $value = trim((string)$raw);
    $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;

    if ($value === '') {
        return $required ? sanitize_fail('Address is required.') : sanitize_ok('');
    }
    if (contains_html($value)) {
        return sanitize_fail('This field contains invalid characters.');
    }
    if (input_str_len($value) > $maxLen) {
        return sanitize_fail('Address is too long.');
    }
    if (!preg_match("/^[\\p{L}\\p{N}\\s.,#'\\-\\/]+$/u", $value)) {
        return sanitize_fail('Address contains invalid characters.');
    }

    return sanitize_ok($value);
}

function sanitize_login_identifier($raw): array
{
    $value = trim((string)$raw);

    if ($value === '') {
        return sanitize_fail('Username is required.');
    }
    if (preg_match('/[<>()\/"]/', $value)) {
        return sanitize_fail('Please enter a valid username or email.');
    }
    if (input_str_len($value) > 254) {
        return sanitize_fail('Username is too long.');
    }
    if (strpos($value, '@') !== false) {
        return sanitize_email($value, true);
    }
    if (!preg_match('/^[A-Za-z0-9._\\-]+$/', $value)) {
        return sanitize_fail('Username contains invalid characters.');
    }

    return sanitize_ok($value);
}

function sanitize_digit_code($raw, int $length = 6, string $label = 'Code'): array
{
    $digits = preg_replace('/\D+/', '', (string)$raw) ?? '';

    if ($digits === '') {
        return sanitize_fail($label . ' is required.');
    }
    if (strlen($digits) !== $length) {
        return sanitize_fail('Enter a valid ' . $length . '-digit ' . strtolower($label) . '.');
    }

    return sanitize_ok($digits);
}

function sanitize_choice($raw, array $allowed, bool $required = true, string $label = 'Value', bool $caseInsensitive = false): array
{
    $value = trim((string)$raw);

    if ($value === '') {
        return $required ? sanitize_fail($label . ' is required.') : sanitize_ok('');
    }
    if (contains_html($value)) {
        return sanitize_fail('This field contains invalid characters.');
    }

    if ($caseInsensitive) {
        foreach ($allowed as $option) {
            if (strcasecmp((string)$option, $value) === 0) {
                return sanitize_ok((string)$option);
            }
        }
        return sanitize_fail('Please select a valid ' . strtolower($label) . '.');
    }

    if (!in_array($value, $allowed, true)) {
        return sanitize_fail('Please select a valid ' . strtolower($label) . '.');
    }

    return sanitize_ok($value);
}

function sanitize_date($raw, bool $required = false, string $label = 'Date'): array
{
    $value = trim((string)$raw);

    if ($value === '') {
        return $required ? sanitize_fail($label . ' is required.') : sanitize_ok('');
    }
    if (contains_html($value)) {
        return sanitize_fail('This field contains invalid characters.');
    }

    $dt = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    $hasErrors = is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);

    if (!$dt || $dt->format('Y-m-d') !== $value || $hasErrors) {
        return sanitize_fail('Please enter a valid ' . strtolower($label) . '.');
    }

    $today = new DateTime('today');
    if ($dt > $today) {
        return sanitize_fail($label . ' cannot be in the future.');
    }

    return sanitize_ok($value);
}

function sanitize_id_code($raw, bool $required = false, int $maxLen = 30, string $label = 'ID number'): array
{
    $value = trim((string)$raw);

    if ($value === '') {
        return $required ? sanitize_fail($label . ' is required.') : sanitize_ok('');
    }
    if (contains_html($value)) {
        return sanitize_fail('This field contains invalid characters.');
    }
    if (input_str_len($value) > $maxLen) {
        return sanitize_fail($label . ' is too long.');
    }
    if (!preg_match('/^[A-Za-z0-9\\-\/]+$/', $value)) {
        return sanitize_fail($label . ' may only contain letters, numbers, hyphens, and slashes.');
    }

    return sanitize_ok($value);
}

/**
 * @return array{ok:bool,error:?string,data:array}
 */
function sanitize_patient_registration_input(array $post, array $options = []): array
{
    $requireDob = !empty($options['require_dob']);
    $allowedGenders = ['male', 'female'];
    $allowedRelationships = ['Parent', 'Spouse', 'Child', 'Sibling', 'Grandparent', 'Relative', 'Guardian', 'Friend', 'Other'];

    $first = sanitize_name($post['first_name'] ?? '', true, 80, 'First name');
    if (!$first['ok']) {
        return ['ok' => false, 'error' => $first['error'], 'data' => []];
    }

    $middle = sanitize_name($post['middle_name'] ?? '', false, 80, 'Middle name');
    if (!$middle['ok']) {
        return ['ok' => false, 'error' => $middle['error'], 'data' => []];
    }

    $last = sanitize_name($post['last_name'] ?? '', true, 80, 'Last name');
    if (!$last['ok']) {
        return ['ok' => false, 'error' => $last['error'], 'data' => []];
    }

    $email = sanitize_email($post['email'] ?? '', true);
    if (!$email['ok']) {
        return ['ok' => false, 'error' => $email['error'], 'data' => []];
    }

    $phone = sanitize_phone($post['phone'] ?? '', true);
    if (!$phone['ok']) {
        return ['ok' => false, 'error' => $phone['error'], 'data' => []];
    }

    $gender = sanitize_choice($post['gender'] ?? '', $allowedGenders, true, 'Gender', true);
    if (!$gender['ok']) {
        return ['ok' => false, 'error' => $gender['error'], 'data' => []];
    }

    $dob = sanitize_date($post['date_of_birth'] ?? '', $requireDob, 'Date of birth');
    if (!$dob['ok']) {
        return ['ok' => false, 'error' => $dob['error'], 'data' => []];
    }

    $address = sanitize_address($post['address'] ?? '', false);
    if (!$address['ok']) {
        return ['ok' => false, 'error' => $address['error'], 'data' => []];
    }

    $emergencyName = sanitize_name($post['emergency_contact_name'] ?? '', true, 80, 'Emergency contact name');
    if (!$emergencyName['ok']) {
        return ['ok' => false, 'error' => $emergencyName['error'], 'data' => []];
    }

    $emergencyPhone = sanitize_phone($post['emergency_contact_phone'] ?? '', true);
    if (!$emergencyPhone['ok']) {
        return ['ok' => false, 'error' => $emergencyPhone['error'], 'data' => []];
    }

    $relationship = sanitize_choice($post['emergency_contact_relationship'] ?? '', $allowedRelationships, true, 'Relationship');
    if (!$relationship['ok']) {
        return ['ok' => false, 'error' => $relationship['error'], 'data' => []];
    }

    $seniorId = sanitize_id_code($post['senior_citizen_id'] ?? '', false, 30, 'Senior ID number');
    if (!$seniorId['ok']) {
        return ['ok' => false, 'error' => $seniorId['error'], 'data' => []];
    }

    $pwdId = sanitize_id_code($post['pwd_id'] ?? '', false, 30, 'PWD ID number');
    if (!$pwdId['ok']) {
        return ['ok' => false, 'error' => $pwdId['error'], 'data' => []];
    }

    $seniorIdType = trim((string)($post['senior_id_type'] ?? ''));
    if ($seniorIdType !== '' && contains_html($seniorIdType)) {
        return ['ok' => false, 'error' => 'This field contains invalid characters.', 'data' => []];
    }

    return [
        'ok' => true,
        'error' => null,
        'data' => [
            'first_name' => $first['value'],
            'middle_name' => $middle['value'],
            'last_name' => $last['value'],
            'email' => $email['value'],
            'phone' => $phone['value'],
            'gender' => strtolower($gender['value']),
            'date_of_birth' => $dob['value'],
            'address' => $address['value'],
            'emergency_contact_name' => $emergencyName['value'],
            'emergency_contact_phone' => $emergencyPhone['value'],
            'emergency_contact_relationship' => $relationship['value'],
            'senior_citizen_id' => $seniorId['value'],
            'senior_id_type' => $seniorIdType,
            'pwd_id' => $pwdId['value'],
        ],
    ];
}
