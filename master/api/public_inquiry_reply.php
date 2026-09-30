<?php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/inquiry_module_helper.php';
require_once '../../includes/typing_status_helper.php';

ensureInquiryModuleSchema($pdo);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$token = trim($_REQUEST['token'] ?? '');

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing token.']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, first_name, middle_name, last_name, email, phone, concern, inquiry_message,
           service_id, source, status, created_at, assigned_staff_id
    FROM inquiries
    WHERE reply_token = ? AND COALESCE(is_archived, 0) = 0
    LIMIT 1
");
$stmt->execute([$token]);
$inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inquiry) {
    echo json_encode(['success' => false, 'message' => 'Conversation not found.']);
    exit;
}

$inquiryId = (int)$inquiry['id'];
$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? ''));

if ($action === 'set_typing' || $action === 'conversation_updates') {
    if ($action === 'set_typing') {
        $isTyping = (int)($_POST['is_typing'] ?? 0) === 1;
        setTypingStatus('inquiry', $inquiryId, 'patient', $isTyping);
        echo json_encode(['success' => true]);
        exit;
    }

    $lastReplyId = max(0, (int)($_POST['last_reply_id'] ?? 0));
    $replyStmt = $pdo->prepare("
        SELECT id, message, sender_type, attachment, created_at
        FROM inquiry_replies
        WHERE inquiry_id = ? AND id > ?
        ORDER BY id ASC
    ");
    $replyStmt->execute([$inquiryId, $lastReplyId]);
    $newReplies = $replyStmt->fetchAll(PDO::FETCH_ASSOC);

    $typing = getTypingStatus('inquiry', $inquiryId, 'staff', 6);
    $staffIsTyping = $typing['is_typing'];

    $staffUserId = $typing['user_id'];
    if ($staffUserId <= 0) {
        $staffUserId = (int)($inquiry['assigned_staff_id'] ?? 0);
    }

    echo json_encode([
        'success' => true,
        'replies' => $newReplies,
        'other_is_typing' => (bool)$staffIsTyping,
        'staff_avatar' => resolveStaffProfilePictureUrl($pdo, $staffUserId > 0 ? $staffUserId : null),
    ]);
    exit;
}

if ($method === 'GET') {
    $replies = fetchInquiryReplies($pdo, $inquiryId);

    $fullName = trim(implode(' ', array_filter([
        $inquiry['first_name'] ?? '',
        $inquiry['middle_name'] ?? '',
        $inquiry['last_name'] ?? '',
    ])));

    echo json_encode([
        'success' => true,
        'inquiry' => [
            'id' => $inquiryId,
            'full_name' => $fullName ?: 'Guest',
            'email' => $inquiry['email'] ?? '',
            'message' => $inquiry['concern'] ?? $inquiry['inquiry_message'] ?? '',
            'created_at' => $inquiry['created_at'] ?? '',
            'service_id' => $inquiry['service_id'] ?? null,
            'status' => $inquiry['status'] ?? 'Unread',
        ],
        'replies' => $replies,
    ]);
    exit;
}

if ($method === 'POST') {
    $message = trim($_POST['message'] ?? '');
    if ($message === '') {
        echo json_encode(['success' => false, 'message' => 'Message is required.']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO inquiry_replies (inquiry_id, message, sender_type, created_at)
        VALUES (?, ?, 'guest', NOW())
    ");
    $stmt->execute([$inquiryId, $message]);

    updateInquiryRecord($pdo, $inquiryId, ['status' => 'Unread']);

    $fullName = trim(implode(' ', array_filter([
        $inquiry['first_name'] ?? '',
        $inquiry['middle_name'] ?? '',
        $inquiry['last_name'] ?? '',
    ]))) ?: 'A guest';

    // Give the guest their portal link back so they can reopen this same
    // conversation from the email even if they lose the browser tab.
    $guestEmail = trim((string)($inquiry['email'] ?? ''));
    if ($guestEmail !== '') {
        sendInquiryConversationLinkEmail(
            $guestEmail,
            (string)($inquiry['first_name'] ?? $fullName),
            inquiryConversationUrl($token),
            $pdo,
            '',
            'Thank you for replying. We sent your message to the clinic and will get back to you shortly.'
        );
    }

    sendInquiryGuestReplyNotificationEmail($pdo, $inquiry, $message);

    echo json_encode(['success' => true, 'message' => 'Your reply has been sent.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
