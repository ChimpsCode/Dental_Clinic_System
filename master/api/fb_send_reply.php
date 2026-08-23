<?php
ob_start();
require_once __DIR__ . '/../../includes/session_bootstrap.php';header('Content-Type: application/json');

function fbReplyJson(array $payload): void {
    global $pdo;
    if (ob_get_length() > 0) {
        ob_clean();
    }
    echo json_encode($payload);
    if (isset($pdo)) {
        $pdo = null;
    }
    exit();
}

if (!isset($_SESSION['user_id'])) {
    fbReplyJson(['success' => false, 'message' => 'Authentication required.']);
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['staff', 'admin', 'dentist'], true)) {
    fbReplyJson(['success' => false, 'message' => 'Unauthorized.']);
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/facebook_config.php';
require_once __DIR__ . '/../../includes/inquiry_module_helper.php';
require_once __DIR__ . '/../../includes/fb_messenger_helper.php';

ensureInquiryModuleSchema($pdo);
ensureFBMessengerSchema($pdo);

$id = (int)($_POST['id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if ($id <= 0) {
    fbReplyJson(['success' => false, 'message' => 'Invalid inquiry ID.']);
}

if ($message === '') {
    fbReplyJson(['success' => false, 'message' => 'Reply message is required.']);
}

$stmt = $pdo->prepare("SELECT id, fb_sender_id, source FROM inquiries WHERE id = ? AND COALESCE(is_archived, 0) = 0 LIMIT 1");
$stmt->execute([$id]);
$inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inquiry) {
    fbReplyJson(['success' => false, 'message' => 'Inquiry not found.']);
}

$fbSenderId = $inquiry['fb_sender_id'] ?? '';
if ($fbSenderId === '') {
    fbReplyJson(['success' => false, 'message' => 'This inquiry is not linked to a Facebook Messenger conversation.']);
}

$sent = sendFBMessage($fbSenderId, $message);

ensureInquiryReplySchema($pdo);

$stmt = $pdo->prepare("
    INSERT INTO inquiry_replies (inquiry_id, message, sender_type, created_at)
    VALUES (?, ?, 'staff', NOW())
");
$stmt->execute([$id, $message]);

updateInquiryRecord($pdo, $id, ['status' => 'Replied']);

if ($sent) {
    fbReplyJson(['success' => true, 'message' => 'Reply sent via Facebook Messenger.']);
} else {
    fbReplyJson(['success' => true, 'message' => 'Reply saved but failed to send via Messenger. The patient may need to message the page first within 24 hours.']);
}
