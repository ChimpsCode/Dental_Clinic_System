<?php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/inquiry_module_helper.php';

ensureInquiryModuleSchema($pdo);

$firstName = trim($_POST['first_name'] ?? '');
$middleName = trim($_POST['middle_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');
$serviceId = isset($_POST['service_id']) ? (int)$_POST['service_id'] : null;
$preferredDate = trim($_POST['preferred_date'] ?? '');
$preferredTime = trim($_POST['preferred_time'] ?? '');

if (!$firstName || !$lastName || !$email || !$phone || !$message) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled out.']);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO inquiries (first_name, middle_name, last_name, phone, email, concern, source, status, created_at, service_id, preferred_date, preferred_time)
    VALUES (?, ?, ?, ?, ?, ?, 'Website', 'Unread', NOW(), ?, ?, ?)
");
$stmt->execute([$firstName, $middleName ?: null, $lastName, $phone, $email, $message, $serviceId, $preferredDate ?: null, $preferredTime ?: null]);

echo json_encode(['success' => true, 'message' => 'Your message has been received. We will reply to you via email.']);
