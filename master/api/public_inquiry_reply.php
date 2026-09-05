<?php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/inquiry_module_helper.php';

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
    ensureInquiryTypingSchema($pdo);

    if ($action === 'set_typing') {
        $isTyping = (int)($_POST['is_typing'] ?? 0) === 1 ? 1 : 0;
        $typingStmt = $pdo->prepare("
            INSERT INTO inquiry_typing_status (inquiry_id, sender_type, is_typing, updated_at)
            VALUES (?, 'patient', ?, NOW())
            ON DUPLICATE KEY UPDATE is_typing = VALUES(is_typing), updated_at = NOW()
        ");
        $typingStmt->execute([$inquiryId, $isTyping]);
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

    $typingStmt = $pdo->prepare("
        SELECT is_typing, updated_at, user_id
        FROM inquiry_typing_status
        WHERE inquiry_id = ? AND sender_type = 'staff'
        LIMIT 1
    ");
    $typingStmt->execute([$inquiryId]);
    $typingRow = $typingStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $staffIsTyping = $typingRow
        && (int)$typingRow['is_typing'] === 1
        && (time() - strtotime((string)$typingRow['updated_at'])) <= 6;

    $staffUserId = (int)($typingRow['user_id'] ?? 0);
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

    $mailerConfig = __DIR__ . '/../../config/mailer_config.php';
    $phpMailerBase = __DIR__ . '/../../PHPMailer/src';
    if (file_exists($mailerConfig) && file_exists($phpMailerBase . '/PHPMailer.php')) {
        require_once $mailerConfig;
        require_once $phpMailerBase . '/Exception.php';
        require_once $phpMailerBase . '/PHPMailer.php';
        require_once $phpMailerBase . '/SMTP.php';

        $staffEmail = MAIL_FROM;

        $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $clinicSettings = [];
        while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
            $clinicSettings[$row['setting_key']] = $row['setting_value'];
        }
        $clinicName = $clinicSettings['clinic_name'] ?? 'RF Dental Clinic';

        if (!empty($inquiry['assigned_staff_id'])) {
            $staffStmt = $pdo->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
            $staffStmt->execute([(int)$inquiry['assigned_staff_id']]);
            $assignedEmail = $staffStmt->fetchColumn();
            if ($assignedEmail) {
                $staffEmail = $assignedEmail;
            }
        }

        try {
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = trim((string)SMTP_HOST);
            $mailer->SMTPAuth = true;
            $mailer->Username = SMTP_USER;
            $mailer->Password = preg_replace('/\s+/', '', (string)SMTP_PASS);
            $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->Port = SMTP_PORT;
            $mailer->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
            $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mailer->addAddress($staffEmail);
            $mailer->isHTML(true);
            $mailer->Subject = "New reply from {$fullName} regarding their inquiry";
            $mailer->Body = '<p>A guest has replied to their inquiry:</p>'
                . '<p><strong>From:</strong> ' . htmlspecialchars($fullName) . ' (' . htmlspecialchars($inquiry['email'] ?? '') . ')</p>'
                . '<p><strong>Message:</strong></p>'
                . '<blockquote style="padding:12px 16px;border-left:4px solid #2563eb;background:#f8fafc;margin:0;">'
                . nl2br(htmlspecialchars($message))
                . '</blockquote>'
                . '<p style="margin-top:20px;"><a href="' . htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . '/staff/staff_inquiries.php') . '" style="display:inline-block;padding:12px 24px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;">View inquiry in dashboard</a></p>'
                . '<hr><p style="color:#64748b;font-size:13px;">This is an automated notification from ' . htmlspecialchars($clinicName) . '.</p>';
            $mailer->AltBody = "New reply from {$fullName} ({$inquiry['email']}):\n\n{$message}\n\n-- {$clinicName}";
            $mailer->send();
        } catch (\Exception $e) {
            error_log("Failed to send staff notification about guest reply: " . $e->getMessage());
        }
    }

    echo json_encode(['success' => true, 'message' => 'Your reply has been sent.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
