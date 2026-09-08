<?php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/inquiry_module_helper.php';
require_once '../../includes/input_sanitize.php';

ensureInquiryModuleSchema($pdo);

$first = sanitize_name($_POST['first_name'] ?? '', true, 80, 'First name');
$middle = sanitize_name($_POST['middle_name'] ?? '', false, 80, 'Middle name');
$last = sanitize_name($_POST['last_name'] ?? '', true, 80, 'Last name');
$emailResult = sanitize_email($_POST['email'] ?? '', true);
$phoneResult = sanitize_phone($_POST['phone'] ?? '', true);
$messageResult = sanitize_message($_POST['message'] ?? '', true, 2000, 'Message');

foreach ([$first, $middle, $last, $emailResult, $phoneResult, $messageResult] as $field) {
    if (!$field['ok']) {
        echo json_encode(['success' => false, 'message' => $field['error']]);
        exit;
    }
}

$firstName = $first['value'];
$middleName = $middle['value'];
$lastName = $last['value'];
$email = $emailResult['value'];
$phone = $phoneResult['value'];
$message = $messageResult['value'];
$serviceId = isset($_POST['service_id']) ? (int)$_POST['service_id'] : null;
$preferredDate = trim($_POST['preferred_date'] ?? '');
$preferredTime = trim($_POST['preferred_time'] ?? '');

if (contains_html($preferredDate) || contains_html($preferredTime)) {
    echo json_encode(['success' => false, 'message' => 'This field contains invalid characters.']);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO inquiries (first_name, middle_name, last_name, phone, email, concern, source, status, created_at, service_id, preferred_date, preferred_time)
    VALUES (?, ?, ?, ?, ?, ?, 'Website', 'Unread', NOW(), ?, ?, ?)
");
$stmt->execute([$firstName, $middleName ?: null, $lastName, $phone, $email, $message, $serviceId, $preferredDate ?: null, $preferredTime ?: null]);

$inquiryId = (int)$pdo->lastInsertId();

$token = bin2hex(random_bytes(32));
$tokenStmt = $pdo->prepare("UPDATE inquiries SET reply_token = ? WHERE id = ?");
$tokenStmt->execute([$token, $inquiryId]);

echo json_encode(['success' => true, 'message' => 'Your message has been sent! We will reply to you via email.', 'token' => $token]);
