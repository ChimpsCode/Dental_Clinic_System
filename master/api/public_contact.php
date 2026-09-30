<?php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/inquiry_module_helper.php';
require_once '../../includes/input_sanitize.php';
require_once '../../includes/rate_limit.php';

if (!rate_limit_allow('public_contact', 5, 600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many messages. Please wait a few minutes and try again.']);
    exit;
}

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

$token = generateReplyToken();
$tokenStmt = $pdo->prepare("UPDATE inquiries SET reply_token = ? WHERE id = ?");
$tokenStmt->execute([$token, $inquiryId]);

$conversationLink = inquiryConversationUrl($token);

// Tell the clinic first: without this the inquiry only existed in the database
// and staff never learned a new lead had arrived.
$inquiryRow = $pdo->prepare("
    SELECT id, first_name, middle_name, last_name, email, phone, concern, source, status, created_at, reply_token
    FROM inquiries WHERE id = ? LIMIT 1
");
$inquiryRow->execute([$inquiryId]);
$inquiryData = $inquiryRow->fetch(PDO::FETCH_ASSOC) ?: [];

$clinicNotified = sendNewInquiryStaffNotificationEmail($pdo, $inquiryData);

try {
    require_once '../../includes/notification_functions.php';
    ensureNotificationSchema($pdo);
    notifyNewInquiry($pdo, trim($firstName . ' ' . $lastName) ?: 'A website visitor', 'Website');
} catch (Throwable $e) {
    error_log('public_contact: in-app inquiry notification failed: ' . $e->getMessage());
}

$guestEmailed = sendInquiryConversationLinkEmail($email, $firstName, $conversationLink, $pdo);

echo json_encode([
    'success' => true,
    'message' => 'Your message has been sent! We will reply to you via email.',
    'link' => $conversationLink,
    'email_sent' => $guestEmailed,
    'clinic_notified' => $clinicNotified,
]);
