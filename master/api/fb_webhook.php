<?php
header('Content-Type: text/plain');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/facebook_config.php';
require_once __DIR__ . '/../../includes/inquiry_module_helper.php';
require_once __DIR__ . '/../../includes/fb_messenger_helper.php';

ensureInquiryModuleSchema($pdo);
ensureFBMessengerSchema($pdo);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    if ($mode === 'subscribe' && $token === FB_VERIFY_TOKEN) {
        echo $challenge;
        exit;
    }

    http_response_code(403);
    echo 'Verification failed';
    exit;
}

if ($method === 'POST') {
    $rawBody = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

    if (!verifyFBWebhookSignature($rawBody, $signature)) {
        http_response_code(403);
        echo 'Invalid signature';
        exit;
    }

    $payload = json_decode($rawBody, true);
    if (!$payload || ($payload['object'] ?? '') !== 'page') {
        http_response_code(200);
        echo 'Not a page event';
        exit;
    }

    $entries = $payload['entry'] ?? [];
    foreach ($entries as $entry) {
        $pageId = $entry['id'] ?? '';
        if ((string)$pageId !== (string)FB_PAGE_ID) {
            continue;
        }

        $messagingEvents = $entry['messaging'] ?? [];
        foreach ($messagingEvents as $event) {
            $senderId = $event['sender']['id'] ?? '';
            $timestamp = $event['timestamp'] ?? time();
            $message = $event['message'] ?? null;

            if (!$senderId || !$message) {
                continue;
            }

            $messageText = $message['text'] ?? '';
            if ($messageText === '') {
                continue;
            }

            $isEcho = $message['is_echo'] ?? false;
            if ($isEcho) {
                continue;
            }

            $existingInquiry = findInquiryByFBSender($pdo, $senderId);

            if ($existingInquiry) {
                $existingFirst = trim($existingInquiry['first_name'] ?? '');
                $existingLast = trim($existingInquiry['last_name'] ?? '');
                if ($existingFirst === 'Messenger' || $existingFirst === '' || ($existingFirst === 'Facebook' && $existingLast === 'User')) {
                    if (!$isDebug) {
                        $profile = getFBUserProfile($senderId);
                        if ($profile['first_name'] !== '' || $profile['last_name'] !== '') {
                            $pdo->prepare("UPDATE inquiries SET first_name = ?, last_name = ? WHERE id = ?")->execute([$profile['first_name'], $profile['last_name'], $existingInquiry['id']]);
                        }
                    }
                }
                appendFBMessageToInquiry($pdo, (int)$existingInquiry['id'], $messageText);
            } else {
                if ($isDebug) {
                    $firstName = $payload['debug_first_name'] ?? 'Facebook';
                    $lastName = $payload['debug_last_name'] ?? 'User';
                } else {
                    $profile = getFBUserProfile($senderId);
                    $firstName = $profile['first_name'] ?: 'Facebook';
                    $lastName = $profile['last_name'] ?: 'User';
                }
                $inquiryId = createInquiryFromFB($pdo, $senderId, $firstName, $lastName, $messageText);

                if (!$isDebug) {
                    sendFBAutoReply($senderId);
                }
            }
        }
    }

    http_response_code(200);
    echo 'EVENT_RECEIVED';
    exit;
}

http_response_code(405);
echo 'Method not allowed';
