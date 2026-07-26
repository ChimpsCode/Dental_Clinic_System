<?php
if (!defined('FB_APP_SECRET')) {
    require_once __DIR__ . '/../config/facebook_config.php';
}

function ensureFBMessengerSchema(PDO $pdo): void
{
    if (!inquiryColumnExists($pdo, 'inquiries', 'fb_sender_id')) {
        $pdo->exec("ALTER TABLE inquiries ADD COLUMN fb_sender_id VARCHAR(100) NULL AFTER source");
    }
}

function verifyFBWebhookSignature(string $rawBody, ?string $signature): bool
{
    if (empty($signature)) {
        return false;
    }
    $expectedSig = 'sha256=' . hash_hmac('sha256', $rawBody, FB_APP_SECRET);
    return hash_equals($expectedSig, $signature);
}

function getFBUserProfile(string $senderId): array
{
    $url = FB_GRAPH_URL . '/' . urlencode($senderId) . '?fields=first_name,last_name&access_token=' . urlencode(FB_PAGE_ACCESS_TOKEN);
    $ctx = stream_context_create([
        'http' => ['timeout' => 10, 'ignore_errors' => true],
        'ssl'  => ['verify_peer' => true],
    ]);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) {
        return ['first_name' => '', 'last_name' => ''];
    }
    $data = json_decode($response, true);
    return [
        'first_name' => $data['first_name'] ?? '',
        'last_name' => $data['last_name'] ?? '',
    ];
}

function sendFBMessage(string $recipientId, string $messageText): bool
{
    $url = FB_GRAPH_URL . '/' . urlencode(FB_PAGE_ID) . '/messages';
    $payload = json_encode([
        'recipient' => ['id' => $recipientId],
        'message' => ['text' => $messageText],
        'messaging_type' => 'RESPONSE',
    ]);
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nAuthorization: Bearer " . FB_PAGE_ACCESS_TOKEN,
            'content' => $payload,
            'timeout' => 10,
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => true],
    ]);
    $response = @file_get_contents($url, false, $ctx);
    $httpCode = isset($http_response_header) ? (int)substr($http_response_header[0], 9, 3) : 0;
    if ($httpCode < 200 || $httpCode >= 300) {
        error_log("FB Send Message failed (HTTP {$httpCode}): " . ($response ?: 'no response'));
        return false;
    }
    return true;
}

function sendFBAutoReply(string $recipientId): bool
{
    return sendFBMessage($recipientId, FB_AUTO_REPLY_MESSAGE);
}

function findInquiryByFBSender(PDO $pdo, string $fbSenderId): ?array
{
    ensureFBMessengerSchema($pdo);
    $stmt = $pdo->prepare("
        SELECT * FROM inquiries
        WHERE fb_sender_id = ? AND COALESCE(is_archived, 0) = 0
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([$fbSenderId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function createInquiryFromFB(PDO $pdo, string $fbSenderId, string $firstName, string $lastName, string $messageText): int
{
    ensureFBMessengerSchema($pdo);

    $compatibleStatus = getCompatibleInquiryStatus($pdo, 'Unread');
    $source = getCompatibleInquirySource($pdo, 'Fb messenger');

    $insertData = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'concern' => $messageText,
        'inquiry_message' => $messageText,
        'status' => $compatibleStatus,
        'source' => $source,
        'fb_sender_id' => $fbSenderId,
        'contact_info' => '',
    ];

    if (inquiryColumnExists($pdo, 'inquiries', 'status_id')) {
        $statusId = null;
        $statusLookup = $pdo->prepare("SELECT id FROM inquiry_statuses WHERE status_name = ? LIMIT 1");
        $statusLookup->execute([$compatibleStatus]);
        $statusId = $statusLookup->fetchColumn();
        if ($statusId !== false) {
            $insertData['status_id'] = (int)$statusId;
        }
    }

    $columns = [];
    $placeholders = [];
    $values = [];
    foreach ($insertData as $column => $value) {
        if (inquiryColumnExists($pdo, 'inquiries', $column)) {
            $columns[] = $column;
            if ($value === null || $value === '') {
                $placeholders[] = 'NULL';
            } else {
                $placeholders[] = '?';
                $values[] = $value;
            }
        }
    }

    if (inquiryColumnExists($pdo, 'inquiries', 'created_at')) {
        $columns[] = 'created_at';
        $placeholders[] = 'NOW()';
    }

    $stmt = $pdo->prepare("
        INSERT INTO inquiries (" . implode(', ', $columns) . ")
        VALUES (" . implode(', ', $placeholders) . ")
    ");
    $stmt->execute($values);

    return (int)$pdo->lastInsertId();
}

function appendFBMessageToInquiry(PDO $pdo, int $inquiryId, string $messageText): void
{
    ensureInquiryReplySchema($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO inquiry_replies (inquiry_id, message, sender_type, created_at)
        VALUES (?, ?, 'guest', NOW())
    ");
    $stmt->execute([$inquiryId, $messageText]);

    updateInquiryRecord($pdo, $inquiryId, ['status' => 'Unread']);
}
