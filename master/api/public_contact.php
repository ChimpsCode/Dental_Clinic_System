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

if (!$firstName || !$lastName || !$email || !$phone || !$message) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled out.']);
    exit;
}

$fullName = trim("$firstName $middleName $lastName");
$fullName = preg_replace('/\s+/', ' ', $fullName);

$stmt = $pdo->prepare("
    INSERT INTO inquiries (full_name, phone, email, concern, source, status, created_at)
    VALUES (?, ?, ?, ?, 'Website', 'Open', NOW())
");
$stmt->execute([$fullName, $phone, $email, $message]);

echo json_encode(['success' => true, 'message' => 'Your message has been received. We will reply to you via email.']);
