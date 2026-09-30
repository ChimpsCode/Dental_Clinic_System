<?php
ob_start();

require_once __DIR__ . '/../../includes/session_bootstrap.php';

if (!empty($GLOBALS['APP_SESSION_EXPIRED']) || ($GLOBALS['APP_ACCOUNT_REMOVED'] ?? '') !== '') {
    ob_end_clean();
    // XHR callers must get JSON they can act on, not a login-page redirect.
    $revoked = app_session_revoked_response();
    $wantsJson = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
        || ($_POST['ajax'] ?? '') === '1';
    if ($wantsJson) {
        header('Content-Type: application/json');
        echo json_encode($revoked);
    } else {
        header('Location: ' . app_session_login_url());
    }
    exit();
}

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php');
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
$patient_id = $_SESSION['patient_id'] ?? null;
$message = '';
$messageType = '';
$isEmbed = isset($_GET['embed']) && $_GET['embed'] === '1';
$isAjaxRequest = $_SERVER['REQUEST_METHOD'] === 'POST'
    && (($_POST['ajax'] ?? '0') === '1' || (($_POST['action'] ?? '') === 'send_message'));

require_once '../../config/database.php';
require_once '../../includes/patient_session_helper.php';
require_once '../../includes/typing_status_helper.php';
require_once '../../includes/inquiry_module_helper.php';
$patientSessionSettings = getPatientSessionSettings($pdo, (int)($_SESSION['user_id'] ?? 0));

const AUTO_REPLY_TEXT = "Thank you for your message! Please wait for our staff to respond your message. We appreciate your patience.";

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}

function tableColumns(PDO $pdo, string $table): array
{
    $cols = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cols[$row['Field']] = true;
    }
    return $cols;
}

function ensureMessagesTable(PDO $pdo): void
{
    // Create base table if it does not exist yet.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `messages` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `patient_id` INT(11) NOT NULL,
            `subject` VARCHAR(255) DEFAULT NULL,
            `message` TEXT NOT NULL,
            `sent_by` ENUM('patient','admin') NOT NULL DEFAULT 'patient',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_messages_patient_id` (`patient_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $columns = tableColumns($pdo, 'messages');

    // Add missing columns needed by auto-reply flow (safe idempotent checks).
    if (!isset($columns['sender_id'])) {
        $pdo->exec("ALTER TABLE `messages` ADD COLUMN `sender_id` INT NULL AFTER `id`");
    }
    if (!isset($columns['receiver_id'])) {
        $pdo->exec("ALTER TABLE `messages` ADD COLUMN `receiver_id` INT NULL AFTER `sender_id`");
    }
    if (!isset($columns['sender_type'])) {
        $pdo->exec("ALTER TABLE `messages` ADD COLUMN `sender_type` ENUM('patient','admin','system') NOT NULL DEFAULT 'patient' AFTER `message`");
    }
    if (!isset($columns['auto_reply_for_message_id'])) {
        $pdo->exec("ALTER TABLE `messages` ADD COLUMN `auto_reply_for_message_id` INT NULL AFTER `sender_type`");
    }
    if (!isset($columns['is_read'])) {
        $pdo->exec("ALTER TABLE `messages` ADD COLUMN `is_read` TINYINT(1) NOT NULL DEFAULT 0 AFTER `auto_reply_for_message_id`");
    }

    // Backfill sender_type for older rows.
    $pdo->exec("
        UPDATE `messages`
        SET `sender_type` = CASE
            WHEN `sent_by` = 'patient' THEN 'patient'
            WHEN `sent_by` = 'admin' THEN 'admin'
            ELSE 'system'
        END
        WHERE `sender_type` IS NULL OR `sender_type` = ''
    ");

    // Add unique index for duplicate auto-reply protection if missing.
    $idxStmt = $pdo->query("SHOW INDEX FROM `messages` WHERE Key_name = 'uq_messages_auto_reply_for_message_id'");
    $indexExists = (bool)$idxStmt->fetch(PDO::FETCH_ASSOC);
    if (!$indexExists) {
        $pdo->exec("CREATE UNIQUE INDEX `uq_messages_auto_reply_for_message_id` ON `messages` (`auto_reply_for_message_id`)");
    }
}

function buildMessageInsert(PDO $pdo, array $columns, array $data): int
{
    $insertCols = [];
    $placeholders = [];
    $values = [];

    foreach ($data as $col => $val) {
        if (isset($columns[$col])) {
            $insertCols[] = "`$col`";
            $placeholders[] = '?';
            $values[] = $val;
        }
    }

    if (isset($columns['created_at'])) {
        $insertCols[] = '`created_at`';
        $placeholders[] = 'NOW()';
    }

    $sql = "INSERT INTO messages (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    return (int)$pdo->lastInsertId();
}

function fetchMessageById(PDO $pdo, array $columns, int $id): ?array
{
    $senderExpr = isset($columns['sender_type'])
        ? "sender_type"
        : (isset($columns['sent_by']) ? "CASE WHEN sent_by='patient' THEN 'patient' WHEN sent_by='admin' THEN 'admin' ELSE 'system' END" : "'patient'");

    $stmt = $pdo->prepare("
        SELECT id, message, created_at, {$senderExpr} AS sender_type
        FROM messages
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function markPatientInboxAsRead(PDO $pdo, int $patientId, array $columns): void
{
    if ($patientId <= 0 || !isset($columns['is_read'])) {
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE messages
        SET is_read = 1
        WHERE patient_id = ? AND sender_type != 'patient' AND is_read = 0
    ");
    $stmt->execute([$patientId]);
}

function getPatientUnreadCount(PDO $pdo, int $patientId, array $columns): int
{
    if ($patientId <= 0 || !isset($columns['is_read'])) {
        return 0;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM messages
        WHERE patient_id = ? AND sender_type != 'patient' AND is_read = 0
    ");
    $stmt->execute([$patientId]);
    return (int)$stmt->fetchColumn();
}

try {
    ensureMessagesTable($pdo);
    $messageColumns = tableColumns($pdo, 'messages');

    $userStmt = $pdo->prepare("
        SELECT id, first_name, middle_name, last_name, email, phone
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $hasPatientUserId = (bool)$pdo->query("SHOW COLUMNS FROM patients LIKE 'user_id'")->fetch(PDO::FETCH_ASSOC);

    if ($hasPatientUserId) {
        $stmt = $pdo->prepare("
            SELECT p.*, COALESCE(u.first_name, p.first_name) AS first_name, COALESCE(u.middle_name, p.middle_name) AS middle_name, COALESCE(u.last_name, p.last_name) AS last_name, u.email, u.phone
            FROM patients p
            LEFT JOIN users u ON u.id = p.user_id
            WHERE p.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
    } elseif (!empty($patient_id)) {
        $stmt = $pdo->prepare("
            SELECT p.*,
                   ? AS first_name,
                   ? AS middle_name,
                   ? AS last_name,
                   ? AS email,
                   ? AS phone
            FROM patients p
            WHERE p.id = ?
            LIMIT 1
        ");
        $stmt->execute([
            $user['first_name'] ?? null,
            $user['middle_name'] ?? null,
            $user['last_name'] ?? null,
            $user['email'] ?? null,
            $user['phone'] ?? null,
            (int)$patient_id,
        ]);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.*,
                   ? AS first_name,
                   ? AS middle_name,
                   ? AS last_name,
                   ? AS email,
                   ? AS phone
            FROM patients p
            WHERE p.email = ?
            LIMIT 1
        ");
        $stmt->execute([
            $user['first_name'] ?? null,
            $user['middle_name'] ?? null,
            $user['last_name'] ?? null,
            $user['email'] ?? null,
            $user['phone'] ?? null,
            $user['email'] ?? '',
        ]);
    }

    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient && !$patient_id) {
        throw new Exception('Patient not found');
    }
    
    if (!$patient_id && $patient) {
        $patient_id = $patient['id'];
        $_SESSION['patient_id'] = $patient_id;
    }

    $senderExpr = isset($messageColumns['sender_type'])
        ? "sender_type"
        : (isset($messageColumns['sent_by']) ? "CASE WHEN sent_by='patient' THEN 'patient' WHEN sent_by='admin' THEN 'admin' ELSE 'system' END" : "'patient'");

    $isAjaxSend = $_SERVER['REQUEST_METHOD'] === 'POST'
        && (($_POST['ajax'] ?? '0') === '1')
        && (($_POST['action'] ?? '') === 'send_message');
    $isAjaxPoll = $_SERVER['REQUEST_METHOD'] === 'POST'
        && (($_POST['ajax'] ?? '0') === '1')
        && (($_POST['action'] ?? '') === 'poll_messages');
    $isAjaxTyping = $_SERVER['REQUEST_METHOD'] === 'POST'
        && (($_POST['ajax'] ?? '0') === '1')
        && (($_POST['action'] ?? '') === 'set_typing');
    $isAjaxUnreadCount = $_SERVER['REQUEST_METHOD'] === 'POST'
        && (($_POST['ajax'] ?? '0') === '1')
        && (($_POST['action'] ?? '') === 'get_unread_count');
    $isAjaxMarkRead = $_SERVER['REQUEST_METHOD'] === 'POST'
        && (($_POST['ajax'] ?? '0') === '1')
        && (($_POST['action'] ?? '') === 'mark_read');

    // AJAX: Get unread message count for patient
    if ($isAjaxUnreadCount) {
        jsonResponse(['success' => true, 'unread_count' => getPatientUnreadCount($pdo, (int)$patient_id, $messageColumns)]);
    }

    // AJAX: Mark messages as read
    if ($isAjaxMarkRead) {
        markPatientInboxAsRead($pdo, (int)$patient_id, $messageColumns);
        jsonResponse(['success' => true, 'unread_count' => getPatientUnreadCount($pdo, (int)$patient_id, $messageColumns)]);
    }

    if ($isAjaxPoll) {
        $lastId = (int)($_POST['last_id'] ?? 0);
        $hasIsRead = isset($messageColumns['is_read']);
        $isReadExpr = $hasIsRead ? 'is_read' : '0';
        $pollStmt = $pdo->prepare("
            SELECT id, message, created_at, {$senderExpr} AS sender_type, {$isReadExpr} AS is_read
            FROM messages
            WHERE patient_id = ? AND id > ?
            ORDER BY id ASC
        ");
        $pollStmt->execute([$patient_id, $lastId]);
        $newMessages = $pollStmt->fetchAll(PDO::FETCH_ASSOC);
        $typing = getTypingStatus('chat', (int)$patient_id, 'staff', 8);
        jsonResponse([
            'success' => true,
            'messages' => $newMessages,
            'staff_typing' => $typing['is_typing'],
            'unread_count' => getPatientUnreadCount($pdo, (int)$patient_id, $messageColumns)
        ]);
    }

    if ($isAjaxTyping) {
        $isTyping = (int)($_POST['is_typing'] ?? 0) === 1;
        setTypingStatus('chat', (int)$patient_id, 'patient', $isTyping);
        jsonResponse(['success' => true]);
    }

    if ($isAjaxSend) {
        // AJAX endpoint: send patient message, then immediately insert one system auto-reply.
        $message_text = trim($_POST['message'] ?? '');
        if ($message_text === '') {
            jsonResponse(['success' => false, 'message' => 'Please enter a message.'], 422);
        }

        try {
            $pdo->beginTransaction();

            // 1) Save patient message first.
            $patientMessageId = buildMessageInsert($pdo, $messageColumns, [
                'patient_id' => $patient_id,
                'sender_id' => $user_id,
                'receiver_id' => null,
                'subject' => '',
                'message' => $message_text,
                'sent_by' => 'patient',
                'sender_type' => 'patient',
            ]);

            // 2) Prevent duplicate auto-reply for the same patient message when linkage column exists.
            $autoReplyExists = false;
            if (isset($messageColumns['auto_reply_for_message_id'])) {
                $dupStmt = $pdo->prepare("SELECT id FROM messages WHERE auto_reply_for_message_id = ? LIMIT 1");
                $dupStmt->execute([$patientMessageId]);
                $autoReplyExists = (bool)$dupStmt->fetchColumn();
            }

            $autoReply = null;
            if (!$autoReplyExists) {
                // 3) Insert clinic/system auto-reply as a separate message record.
                $systemMessageId = buildMessageInsert($pdo, $messageColumns, [
                    'patient_id' => $patient_id,
                    'sender_id' => null,
                    'receiver_id' => $user_id,
                    'subject' => '',
                    'message' => AUTO_REPLY_TEXT,
                    'sent_by' => 'admin',
                    'sender_type' => 'system',
                    'auto_reply_for_message_id' => $patientMessageId,
                ]);
                $autoReply = fetchMessageById($pdo, $messageColumns, $systemMessageId);
            }

            $pdo->commit();

            $patientMsg = fetchMessageById($pdo, $messageColumns, $patientMessageId);
            $responseMessages = [];
            if ($patientMsg) {
                $responseMessages[] = $patientMsg;
            }
            if ($autoReply) {
                $responseMessages[] = $autoReply;
            }

            jsonResponse([
                'success' => true,
                'message' => 'Message sent successfully.',
                'messages' => $responseMessages
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            jsonResponse([
                'success' => false,
                'message' => 'Unable to send message right now.'
            ], 500);
        }
    }

    $hasIsRead = isset($messageColumns['is_read']);
    $isReadExpr = $hasIsRead ? 'is_read' : '0';
    $stmt = $pdo->prepare("
        SELECT id, message, created_at, {$senderExpr} AS sender_type, {$isReadExpr} AS is_read
        FROM messages
        WHERE patient_id = ?
        ORDER BY created_at ASC, id ASC
    ");
    $stmt->execute([$patient_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    if ($isAjaxRequest) {
        jsonResponse([
            'success' => false,
            'message' => 'Chat error: ' . $e->getMessage()
        ], 500);
    }
    $patient = [];
    $messages = [];
}

// Calculate initial unread count for patient
$initialUnreadCount = 0;
if (!empty($patient_id) && isset($messageColumns['is_read'])) {
    try {
        $initialUnreadCount = getPatientUnreadCount($pdo, (int)$patient_id, $messageColumns);
    } catch (Exception $e) {
        $initialUnreadCount = 0;
    }
}

$chatStaffAvatar = '../../assets/images/Logo.png';
try {
    $staffAvatarStmt = $pdo->query("
        SELECT profile_picture
        FROM users
        WHERE LOWER(COALESCE(role, '')) IN ('staff', 'admin')
          AND profile_picture IS NOT NULL
          AND profile_picture != ''
        ORDER BY CASE WHEN LOWER(role) = 'staff' THEN 0 ELSE 1 END, id ASC
        LIMIT 1
    ");
    $staffAvatarPath = (string)$staffAvatarStmt->fetchColumn();
    if ($staffAvatarPath !== '' && file_exists(dirname(__DIR__, 2) . '/' . $staffAvatarPath)) {
        $chatStaffAvatar = '../../' . ltrim($staffAvatarPath, '/\\');
    }
} catch (Exception $e) {
    // Keep the clinic logo fallback when no staff profile image is available.
}

$pageTitle = 'Messages';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - RF Dental Clinic</title>
    <link rel="icon" type="image/png" href="../../assets/images/Logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if (!$isEmbed): ?>
    <link rel="stylesheet" href="../../assets/css/patient_portal.css?v=7">
    <?php endif; ?>
    <?php require __DIR__ . '/../../includes/app_notice_modal_script.php'; ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, sans-serif; background: <?php echo $isEmbed ? '#ffffff' : '#f5f7fa'; ?>; min-height: 100vh; display: <?php echo $isEmbed ? 'block' : 'flex'; ?>; }
        .sidebar { width: 220px; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); padding: 32px 0; position: fixed; left: 0; top: 0; height: 100vh; overflow-y: auto; color: white; box-shadow: 2px 0 8px rgba(0,0,0,0.1); z-index: 40; }
        .sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 0 24px; margin-bottom: 40px; text-decoration: none; color: white; }
        .sidebar-brand img { height: 32px; width: auto; }
        .sidebar-brand span { font-size: 16px; font-weight: 700; white-space: nowrap; }
        .sidebar-menu { display: flex; flex-direction: column; gap: 8px; padding: 0 12px; }
        .menu-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; color: rgba(255,255,255,0.7); text-decoration: none; border-radius: 8px; transition: all 0.2s; cursor: pointer; font-size: 14px; font-weight: 500; }
        .menu-item:hover { background: rgba(255,255,255,0.1); color: white; }
        .menu-item.active { background: rgba(255,255,255,0.2); color: white; font-weight: 600; }
        .menu-item-with-badge { justify-content: space-between; }
        .menu-item-main { display: inline-flex; align-items: center; gap: 12px; }
        .nav-unread-badge, .floating-unread-badge, .section-title-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 999px;
            background: #dc2626;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            line-height: 1;
        }
        .menu-item i { width: 18px; text-align: center; }
        .main-wrapper { flex: 1; margin-left: 220px; display: flex; flex-direction: column; min-height: 100vh; }
        .dashboard-header { background: white; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; position: sticky; top: 0; z-index: 50; height: 70px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .header-right { display: flex; align-items: center; gap: 16px; }
        .user-profile { position: relative; display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #005daa, #0571ca); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; flex-shrink: 0; }
        .user-details { display: flex; flex-direction: column; }
        .user-name { font-weight: 600; font-size: 14px; color: #1a1a2e; }
        .user-role { font-size: 12px; color: #6b7280; }
        .dropdown-toggle { font-size: 16px; color: #6b7280; cursor: pointer; }
        .user-dropdown { position: absolute; top: 100%; right: 0; margin-top: 8px; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 180px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s; z-index: 1000; }
        .user-profile:hover .user-dropdown { opacity: 1; visibility: visible; transform: translateY(0); }
        .dropdown-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #374151; text-decoration: none; transition: background 0.2s; font-size: 14px; }
        .dropdown-item:hover { background: #f3f4f6; }
        .dropdown-item.logout { color: #dc3545; border-top: 1px solid #e5e7eb; }
        .dashboard-main { flex: 1; padding: 24px 32px; max-width: 1200px; width: 100%; margin: 0 auto; }
        .page-header { margin-bottom: 24px; }
        .page-title { font-size: 28px; font-weight: 700; color: #1a1a2e; margin-bottom: 8px; }
        .page-subtitle { color: #6b7280; font-size: 14px; }
        .grid-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .section { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .section-title { font-size: 18px; font-weight: 700; color: #1a1a2e; margin-bottom: 20px; }
        .section-title-with-badge { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .section-title-badge { min-width: 24px; height: 24px; padding: 0 8px; font-size: 12px; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px; }
        .form-input, .form-textarea { width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; font-family: inherit; transition: border-color 0.2s; }
        .form-input:focus, .form-textarea:focus { outline: none; border-color: #005daa; box-shadow: 0 0 0 3px rgba(0,93,170,0.1); }
        .form-textarea { min-height: 150px; resize: vertical; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: #005daa; color: white; }
        .btn-primary:hover { background: #004080; }
        .message-card { padding: 16px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 12px; }
        .message-card.unread-message { border-color: #fecaca; background: #fff5f5; }
        .message-card.unread-message .message-body,
        .message-card.unread-message .message-date { font-weight: 600; }
        .message-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
        .message-date { font-size: 12px; color: #6b7280; }
        .message-body { font-size: 14px; color: #374151; line-height: 1.5; }
        .message-footer { margin-top: 12px; padding-top: 12px; border-top: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center; }
        .message-type { font-size: 12px; padding: 4px 8px; border-radius: 4px; }
        .type-sent { background: #dbeafe; color: #1e40af; }
        .type-received { background: #d1fae5; color: #065f46; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .empty-state { text-align: center; padding: 32px 24px; color: #6b7280; }
        .empty-state i { font-size: 40px; margin-bottom: 12px; color: #d1d5db; }
        .empty-state h3 { font-size: 16px; color: #374151; margin-bottom: 4px; }
        .chat-shell { display: grid; grid-template-rows: 1fr auto; height: 100vh; background: #ffffff; color: #111827; }
        .chat-history { overflow-y: auto; padding: 18px 16px 10px; display: flex; flex-direction: column; gap: 12px; }
        .chat-bubble { max-width: 82%; padding: 12px 14px; border-radius: 18px; line-height: 1.5; font-size: 14px; }
        .chat-bubble.sent { align-self: flex-end; background: linear-gradient(135deg, #8b5cf6, #2563eb); color: #ffffff; border-bottom-right-radius: 6px; }
        .chat-bubble.received { align-self: flex-start; background: #f3f4f6; color: #111827; border-bottom-left-radius: 6px; }
        .chat-bubble.received.unread-message { background: #fff1f2; border: 1px solid #fecdd3; font-weight: 600; }
        .chat-bubble .inq-quote {
            font-size: 12px;
            line-height: 1.4;
            padding: 8px 10px;
            margin-bottom: 8px;
            border-radius: 8px;
            border-left: 3px solid #2563eb;
            background: #e5e7eb;
            color: #374151;
        }
        .chat-bubble.sent .inq-quote {
            background: rgba(255,255,255,.16);
            border-left-color: rgba(255,255,255,.75);
            color: rgba(255,255,255,.95);
        }
        .chat-meta { font-size: 11px; opacity: 0.72; margin-top: 6px; }
        .chat-compose { padding: 14px; border-top: 1px solid #e5e7eb; background: #ffffff; }
        .chat-compose .form-input,
        .chat-compose .form-textarea { background: #ffffff; border-color: #d1d5db; color: #111827; }
        .chat-compose .form-input::placeholder,
        .chat-compose .form-textarea::placeholder { color: #9ca3af; }
        .chat-compose .form-label { color: #6b7280; }
        .chat-send-row { display: flex; gap: 10px; align-items: flex-end; }
        .chat-send-row .form-group { flex: 1; margin-bottom: 0; }
        .chat-send-row .btn { min-width: 120px; }
        .chat-empty { text-align: center; color: #6b7280; padding: 40px 20px; }
        .typing-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 8px;
            margin-top: 4px;
        }
        .typing-avatar {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #dbe3ee;
            background: #fff;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.12);
        }
        .typing-bubble {
            display: flex;
            align-items: center;
            min-width: 54px;
            min-height: 38px;
            padding: 10px 14px;
            border-radius: 19px;
            border-bottom-left-radius: 6px;
            background: #e2e8f0;
        }
        .typing-dots { display: inline-flex; gap: 4px; align-items: center; }
        .typing-dots span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #64748b;
            display: inline-block;
            animation: typingWave 1.1s infinite ease-in-out;
        }
        .typing-dots span:nth-child(2) { animation-delay: 0.15s; }
        .typing-dots span:nth-child(3) { animation-delay: 0.3s; }
        @keyframes typingWave {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.45; }
            30% { transform: translateY(-4px); opacity: 1; }
        }
        .quick-replies { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
        .quick-reply-btn {
            border: 1px solid #c7d2fe;
            background: #eef2ff;
            color: #1f3a8a;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .quick-reply-btn:hover { background: #dbeafe; border-color: #93c5fd; }
        @media (max-width: 1024px) {
            .grid-layout { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { width: 60px; padding: 24px 0; }
            .sidebar-brand span, .menu-item span { display: none; }
            .sidebar-brand { justify-content: center; padding: 0; }
            .menu-item { justify-content: center; padding: 14px; }
            .main-wrapper { margin-left: 60px; }
            .dashboard-header { padding: 12px 16px; }
            .user-details { display: none; }
            .dashboard-main { padding: 16px; }
        }
    </style>
</head>
<body>
    <?php if ($isEmbed): ?>
    <div class="chat-shell">
        <div class="chat-history" id="chatHistory">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="chat-bubble <?php echo ($msg['sender_type'] === 'patient') ? 'sent' : 'received'; ?> <?php echo ($msg['sender_type'] !== 'patient' && (int)($msg['is_read'] ?? 1) === 0) ? 'unread-message' : ''; ?>" data-message-id="<?php echo (int)$msg['id']; ?>">
                        <div><?php echo renderInquiryQuotedMessageHtml((string)($msg['message'] ?? '')); ?></div>
                        <div class="chat-meta"><?php echo date('M j, g:i A', strtotime($msg['created_at'])); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="chat-empty">
                    <i class="fas fa-comments" style="font-size: 34px; margin-bottom: 12px;"></i>
                    <div>No messages yet. Start chatting with the clinic.</div>
                </div>
            <?php endif; ?>
        </div>

        <div class="chat-compose">
            <form method="POST" action="?embed=1" class="js-chat-form">
                <div class="quick-replies">
                    <button type="button" class="quick-reply-btn" data-quick-reply="Hi clinic, I need to schedule a consultation.">Schedule consultation</button>
                    <button type="button" class="quick-reply-btn" data-quick-reply="Hello, I would like to ask about my upcoming appointment.">Ask about appointment</button>
                    <button type="button" class="quick-reply-btn" data-quick-reply="Good day, can I request my billing details?">Request billing details</button>
                </div>
                <div class="chat-send-row">
                    <div class="form-group">
                        <textarea name="message" class="form-textarea" placeholder="Type your message here..." required style="min-height: 54px;"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script src="../../assets/js/inquiry_quote.js"></script>
    <script>
        (function () {
            const chatHistory = document.getElementById('chatHistory');
            const form = document.querySelector('.js-chat-form');
            const AUTO_REPLY_TEXT = <?php echo json_encode(AUTO_REPLY_TEXT); ?>;
            let lastUnreadCount = <?php echo (int)$initialUnreadCount; ?>;

            function escapeHtml(value) {
                const div = document.createElement('div');
                div.textContent = value || '';
                return div.innerHTML;
            }

            function formatDate(dateStr) {
                const d = new Date(dateStr.replace(' ', 'T'));
                if (Number.isNaN(d.getTime())) return dateStr;
                return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
            }

            function scrollToBottom() {
                if (!chatHistory) return;
                chatHistory.scrollTop = chatHistory.scrollHeight;
            }

            function scrollToLatestMessage() {
                scrollToBottom();
                window.requestAnimationFrame(scrollToBottom);
                window.setTimeout(scrollToBottom, 80);
            }

            function isChatNearBottom() {
                if (!chatHistory) return false;
                return chatHistory.scrollHeight - chatHistory.scrollTop - chatHistory.clientHeight <= 120;
            }

            const renderedMessageIds = new Set(
                Array.from(document.querySelectorAll('[data-message-id]'))
                    .map((el) => Number(el.getAttribute('data-message-id')))
                    .filter((id) => Number.isFinite(id) && id > 0)
            );

            function playNotificationSound() {
                if (!window.AudioContext && !window.webkitAudioContext) return;
                const Ctx = window.AudioContext || window.webkitAudioContext;
                const audioCtx = new Ctx();
                const oscillator = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                oscillator.type = 'sine';
                oscillator.frequency.value = 988;
                gain.gain.value = 0.03;
                oscillator.connect(gain);
                gain.connect(audioCtx.destination);
                oscillator.start();
                oscillator.stop(audioCtx.currentTime + 0.12);
            }

            function updateParentUnread(count) {
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ type: 'patient-chat-unread', count }, window.location.origin);
                }
            }

            function clearUnreadStyles() {
                document.querySelectorAll('.unread-message').forEach((el) => el.classList.remove('unread-message'));
            }

            function getLastMessageId() {
                if (renderedMessageIds.size === 0) return 0;
                return Math.max(...renderedMessageIds);
            }

            function appendMessage(msg, forceScroll = false) {
                if (!chatHistory) return;
                const messageId = Number(msg.id || 0);
                if (messageId > 0 && renderedMessageIds.has(messageId)) return;
                const shouldAutoScroll = forceScroll || isChatNearBottom();
                const empty = document.querySelector('.chat-empty');
                if (empty) empty.remove();
                const isPatient = msg.sender_type === 'patient';
                const bubble = document.createElement('div');
                const isUnreadClinicMessage = !isPatient && Number(msg.is_read || 0) === 0;
                bubble.className = `chat-bubble ${isPatient ? 'sent' : 'received'} ${isUnreadClinicMessage ? 'unread-message' : ''}`;
                if (messageId > 0) {
                    bubble.setAttribute('data-message-id', String(messageId));
                    renderedMessageIds.add(messageId);
                }
                const messageHtml = (typeof inquiryQuotedMessageHtml === 'function')
                    ? inquiryQuotedMessageHtml(msg.message)
                    : escapeHtml(msg.message).replace(/\n/g, '<br>');
                bubble.innerHTML = `
                    <div>${messageHtml}</div>
                    <div class="chat-meta">${escapeHtml(formatDate(msg.created_at))}</div>
                `;
                chatHistory.appendChild(bubble);
                if (shouldAutoScroll) scrollToLatestMessage();
            }

            function appendSendResultMessages(msgs) {
                (msgs || []).forEach((msg) => {
                    const isAutoReply = (msg.sender_type === 'system' || msg.sender_type === 'admin')
                        && (msg.message || '').trim() === AUTO_REPLY_TEXT;
                    if (isAutoReply) {
                        ensureTypingIndicator(true);
                        setTimeout(() => {
                            appendMessage(msg, true);
                            ensureTypingIndicator(false);
                        }, 1200);
                    } else {
                        appendMessage(msg, true);
                    }
                });
            }

            function ensureTypingIndicator(visible) {
                if (!chatHistory) return;
                const shouldAutoScroll = isChatNearBottom();
                let el = document.getElementById('staffTypingIndicator');
                if (!el && visible) {
                    el = document.createElement('div');
                    el.id = 'staffTypingIndicator';
                    el.className = 'typing-indicator';
                    el.innerHTML = '<img src="<?php echo htmlspecialchars($chatStaffAvatar, ENT_QUOTES); ?>" class="typing-avatar" alt="Staff"><span class="typing-bubble"><span class="typing-dots" aria-label="Staff is typing" title="Staff is typing"><span></span><span></span><span></span></span></span>';
                    chatHistory.appendChild(el);
                }
                if (el) {
                    el.style.display = visible ? 'flex' : 'none';
                    if (visible && shouldAutoScroll) scrollToLatestMessage();
                }
            }

            let typingTimer = null;
            let typingState = 0;
            async function sendTypingState(isTyping) {
                if (!form || typingState === isTyping) return;
                typingState = isTyping;
                const payload = new FormData();
                payload.append('ajax', '1');
                payload.append('action', 'set_typing');
                payload.append('is_typing', isTyping ? '1' : '0');
                try {
                    await fetch(form.getAttribute('action') || window.location.href, {
                        method: 'POST',
                        body: payload,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                } catch (_e) {}
            }

            async function pollMessages() {
                if (!form) return;
                const payload = new FormData();
                payload.append('ajax', '1');
                payload.append('action', 'poll_messages');
                payload.append('last_id', String(getLastMessageId()));

                try {
                    const response = await fetch(form.getAttribute('action') || window.location.href, {
                        method: 'POST',
                        body: payload,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) return;
                    let hasNewClinicMessage = false;
                    (data.messages || []).forEach((msg) => {
                        if (msg.sender_type !== 'patient') hasNewClinicMessage = true;
                        appendMessage(msg);
                    });
                    if (hasNewClinicMessage) playNotificationSound();
                    clearUnreadStyles();
                    lastUnreadCount = Number(data.unread_count || 0);
                    updateParentUnread(lastUnreadCount);
                    ensureTypingIndicator(!!data.staff_typing);
                } catch (_err) {
                    // Ignore poll errors and retry on next interval.
                }
            }

            function bindQuickReplies(scope) {
                scope.querySelectorAll('.quick-reply-btn').forEach((btn) => {
                    btn.addEventListener('click', function () {
                        const text = btn.getAttribute('data-quick-reply') || '';
                        const input = scope.querySelector('textarea[name="message"]');
                        if (!input) return;
                        input.value = text;
                        scope.requestSubmit();
                    });
                });
            }

            if (form) {
                bindQuickReplies(form);
                form.addEventListener('submit', async function (event) {
                    event.preventDefault();
                    const messageInput = form.querySelector('textarea[name="message"]');
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const text = (messageInput?.value || '').trim();
                    if (!text) return;

                    const payload = new FormData();
                    payload.append('ajax', '1');
                    payload.append('action', 'send_message');
                    payload.append('message', text);

                    if (submitBtn) submitBtn.disabled = true;
                    try {
                        const response = await fetch(form.getAttribute('action') || window.location.href, {
                            method: 'POST',
                            body: payload,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const raw = await response.text();
                        let data = null;
                        try {
                            data = JSON.parse(raw);
                        } catch (parseError) {
                            throw new Error('Server returned invalid response. Please run DB migration first.');
                        }
                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Failed to send message.');
                        }

                        appendSendResultMessages(data.messages || []);
                        if (messageInput) {
                            messageInput.value = '';
                            messageInput.focus();
                        }
                        sendTypingState(0);
                    } catch (err) {
                        alert(err.message || 'Failed to send message.');
                    } finally {
                        if (submitBtn) submitBtn.disabled = false;
                    }
                });

                const messageInput = form.querySelector('textarea[name="message"]');
                if (messageInput) {
                    messageInput.addEventListener('keydown', function (event) {
                        if (event.key === 'Enter' && !event.shiftKey) {
                            event.preventDefault();
                            form.requestSubmit();
                        }
                    });
                    messageInput.addEventListener('input', function () {
                        const hasText = (messageInput.value || '').trim().length > 0;
                        if (!hasText) {
                            if (typingTimer) clearTimeout(typingTimer);
                            sendTypingState(0);
                            return;
                        }
                        sendTypingState(1);
                        if (typingTimer) clearTimeout(typingTimer);
                        typingTimer = setTimeout(() => sendTypingState(0), 1500);
                    });
                }
            }

            scrollToLatestMessage();
            setInterval(pollMessages, 3000);
            async function markMessagesAsRead() {
                const payload = new FormData();
                payload.append('ajax', '1');
                payload.append('action', 'mark_read');
                try {
                    await fetch(form.getAttribute('action') || window.location.href, {
                        method: 'POST',
                        body: payload,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    clearUnreadStyles();
                    lastUnreadCount = 0;
                    updateParentUnread(0);
                } catch (_e) {}
            }
            updateParentUnread(lastUnreadCount);

            window.addEventListener('message', function (event) {
                if (event.origin !== window.location.origin) return;
                if (event.data && event.data.type === 'patient-chat-opened') {
                    scrollToLatestMessage();
                    markMessagesAsRead();
                }
            });
            
            window.addEventListener('beforeunload', function () { sendTypingState(0); });
        })();
    </script>
</body>
    </html>
    <?php exit; endif; ?>

    <aside class="sidebar" id="patientSidebar">
        <div class="sidebar-brand">
            <img src="../../assets/images/Logo.png" alt="RF Dental Clinic">
            <span>RF Dental</span>
        </div>
        <nav class="sidebar-menu">
            <a href="patient-dashboard.php" class="menu-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="inquiry_consultation.php" class="menu-item">
                <i class="fas fa-comments"></i>
                <span>Inquiry / Consultation</span>
            </a>
            <a href="appointments.php" class="menu-item">
                <i class="fas fa-calendar-alt"></i>
                <span>My appointments</span>
            </a>
            <a href="medical_records.php" class="menu-item">
                <i class="fas fa-file-medical"></i>
                <span>Medical records</span>
            </a>
            <a href="billing.php" class="menu-item">
                <i class="fas fa-credit-card"></i>
                <span>Billing</span>
            </a>
            <a href="settings.php" class="menu-item">
                <i class="fas fa-cog"></i>
                <span>Account settings</span>
            </a>
            <a href="messages.php" class="menu-item active menu-item-with-badge">
                <span class="menu-item-main">
                    <i class="fas fa-message"></i>
                    <span>Messages</span>
                </span>
                <span class="nav-unread-badge" id="sidebarUnreadBadge" style="display: <?php echo $initialUnreadCount > 0 ? 'inline-flex' : 'none'; ?>;">
                    <?php echo $initialUnreadCount > 99 ? '99+' : $initialUnreadCount; ?>
                </span>
            </a>
        </nav>
    </aside>
    <?php include '../../includes/patient_sidebar_overlay.php'; ?>

    <div class="main-wrapper">
        <header class="dashboard-header">
            <div class="header-left">
                <?php include '../../includes/patient_menu_toggle.php'; ?>
                <div>
                <h1 style="font-size: 20px; font-weight: 700; color: #1a1a2e; margin: 0;">Messages</h1>
                <p style="font-size: 13px; color: #6b7280; margin: 4px 0 0 0;">Send messages to the clinic and view your correspondence</p>
                </div>
            </div>
            <div class="header-right">
                <?php include '../../includes/patient_tour_widget.php'; ?>
                <?php include '../../includes/patient_notification_widget.php'; ?>
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php 
                            $profPic = $patient['profile_picture'] ?? $_SESSION['profile_picture'] ?? '';
                            $first = ($patient['first_name'] ?? '') ?: ($_SESSION['user_name'] ?? $_SESSION['display_name'] ?? '');
                            $last = $patient['last_name'] ?? '';
                            if (!empty($profPic) && file_exists(dirname(__DIR__, 2) . '/' . $profPic)):
                        ?>
                            <img src="../../<?php echo htmlspecialchars($profPic); ?>" alt="Profile" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                        <?php 
                            else:
                            $initials = strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
                        ?>
                            <?php echo htmlspecialchars($initials ?: 'P'); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name">
                            <?php 
                                $displayName = trim($first ?: 'Patient');
                                echo htmlspecialchars($displayName);
                            ?>
                        </div>
                        <div class="user-role">Patient</div>
                    </div>
                    <div class="dropdown-toggle">
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="user-dropdown">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i>
                            <span>Profile</span>
                        </a>
                        <a href="../../logout.php" class="dropdown-item logout">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="grid-layout">
                <div class="section">
                    <h2 class="section-title"><i class="fas fa-paper-plane"></i> Send a Message</h2>
                    <form method="POST" action="" class="js-chat-form">
                        <div class="quick-replies">
                            <button type="button" class="quick-reply-btn" data-quick-reply="Hi clinic, I need to schedule a consultation.">Schedule consultation</button>
                            <button type="button" class="quick-reply-btn" data-quick-reply="Hello, I would like to ask about my upcoming appointment.">Ask about appointment</button>
                            <button type="button" class="quick-reply-btn" data-quick-reply="Good day, can I request my billing details?">Request billing details</button>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-textarea" placeholder="Type your message here..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </form>
                </div>

                <div class="section">
                    <h2 class="section-title section-title-with-badge">
                        <span><i class="fas fa-inbox"></i> Message History</span>
                        <span class="section-title-badge" id="panelUnreadBadge" style="display: <?php echo $initialUnreadCount > 0 ? 'inline-flex' : 'none'; ?>;">
                            <?php echo $initialUnreadCount > 99 ? '99+' : $initialUnreadCount; ?>
                        </span>
                    </h2>
                    <div id="pageMessageHistory">
                    <?php if (!empty($messages)): ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="message-card <?php echo ($msg['sender_type'] !== 'patient' && (int)($msg['is_read'] ?? 1) === 0) ? 'unread-message' : ''; ?>" data-message-id="<?php echo (int)$msg['id']; ?>">
                                <div class="message-header">
                                    <div class="message-date"><?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></div>
                                </div>
                                <div class="message-body"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                <div class="message-footer">
                                    <span class="message-type <?php echo $msg['sender_type'] === 'patient' ? 'type-sent' : 'type-received'; ?>">
                                        <?php echo $msg['sender_type'] === 'patient' ? 'Sent' : 'Clinic'; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" id="pageEmptyState">
                            <i class="fas fa-envelope-open"></i>
                            <h3>No Messages Yet</h3>
                            <p>Send a message to the clinic using the form.</p>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <a href="messages.php" class="floating-message-btn" data-chat-launcher aria-label="Open messages" title="Messages">
        <i class="fas fa-message"></i>
        <span class="floating-unread-badge unread-badge" id="floatingUnreadBadge" style="display: <?php echo $initialUnreadCount > 0 ? 'inline-flex' : 'none'; ?>;">
            <?php echo $initialUnreadCount > 99 ? '99+' : $initialUnreadCount; ?>
        </span>
    </a>
    <script>
        (function () {
            const launcher = document.querySelector('[data-chat-launcher]');
            if (!launcher) return;
            let overlay = document.getElementById('floatingChatOverlay');
            let panel = document.getElementById('floatingChatPanel');
            if (!overlay) { overlay = document.createElement('div'); overlay.id = 'floatingChatOverlay'; overlay.className = 'floating-chat-overlay'; document.body.appendChild(overlay); }
            if (!panel) { panel = document.createElement('div'); panel.id = 'floatingChatPanel'; panel.className = 'floating-chat-panel'; panel.innerHTML = `<div class="floating-chat-header"><div class="floating-chat-title"><i class="fas fa-comments"></i><span>Messages</span></div><div class="floating-chat-actions"><button type="button" class="floating-chat-control" data-chat-minimize aria-label="Minimize"><i class="fas fa-minus"></i></button><button type="button" class="floating-chat-control" data-chat-close aria-label="Close"><i class="fas fa-xmark"></i></button></div></div><iframe class="floating-chat-frame" src="messages.php?embed=1" title="Messages"></iframe>`; document.body.appendChild(panel); }
            function closeChat() { overlay.classList.remove('is-open'); panel.classList.remove('is-open'); }
            launcher.addEventListener('click', function (event) { event.preventDefault(); overlay.classList.add('is-open'); panel.classList.add('is-open'); });
            overlay.addEventListener('click', closeChat);
            panel.querySelector('[data-chat-close]').addEventListener('click', closeChat);
            panel.querySelector('[data-chat-minimize]').addEventListener('click', closeChat);
        })();

        (function () {
            const chatHistory = document.getElementById('chatHistory');
            const pageHistory = document.getElementById('pageMessageHistory');
            const AUTO_REPLY_TEXT = <?php echo json_encode(AUTO_REPLY_TEXT); ?>;
            let lastUnreadCount = <?php echo (int)$initialUnreadCount; ?>;

            function escapeHtml(value) {
                const div = document.createElement('div');
                div.textContent = value || '';
                return div.innerHTML;
            }

            function formatDate(dateStr, isEmbed) {
                const d = new Date(dateStr.replace(' ', 'T'));
                if (Number.isNaN(d.getTime())) return dateStr;
                return d.toLocaleString(undefined, isEmbed
                    ? { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }
                    : { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
            }

            function scrollToBottom(el) {
                if (!el) return;
                el.scrollTop = el.scrollHeight;
            }

            function scrollToLatestMessages() {
                scrollToBottom(chatHistory);
                scrollToBottom(pageHistory);
                window.requestAnimationFrame(() => {
                    scrollToBottom(chatHistory);
                    scrollToBottom(pageHistory);
                });
                window.setTimeout(() => {
                    scrollToBottom(chatHistory);
                    scrollToBottom(pageHistory);
                }, 80);
            }

            function isMessageViewNearBottom() {
                const container = chatHistory || pageHistory;
                if (!container) return false;
                return container.scrollHeight - container.scrollTop - container.clientHeight <= 120;
            }

            function removeEmptyStates() {
                const chatEmpty = document.querySelector('.chat-empty');
                if (chatEmpty) chatEmpty.remove();
                const pageEmpty = document.getElementById('pageEmptyState');
                if (pageEmpty) pageEmpty.remove();
            }

            const embedRenderedIds = new Set(
                Array.from(document.querySelectorAll('#chatHistory [data-message-id]'))
                    .map((el) => Number(el.getAttribute('data-message-id')))
                    .filter((id) => Number.isFinite(id) && id > 0)
            );
            const pageRenderedIds = new Set(
                Array.from(document.querySelectorAll('#pageMessageHistory [data-message-id]'))
                    .map((el) => Number(el.getAttribute('data-message-id')))
                    .filter((id) => Number.isFinite(id) && id > 0)
            );

            function playNotificationSound() {
                if (!window.AudioContext && !window.webkitAudioContext) return;
                const Ctx = window.AudioContext || window.webkitAudioContext;
                const audioCtx = new Ctx();
                const oscillator = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                oscillator.type = 'sine';
                oscillator.frequency.value = 988;
                gain.gain.value = 0.03;
                oscillator.connect(gain);
                gain.connect(audioCtx.destination);
                oscillator.start();
                oscillator.stop(audioCtx.currentTime + 0.12);
            }

            function updateUnreadBadges(count) {
                ['sidebarUnreadBadge', 'panelUnreadBadge', 'floatingUnreadBadge', 'unreadBadge'].forEach((id) => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    if (count > 0) {
                        el.textContent = count > 99 ? '99+' : String(count);
                        el.style.display = 'inline-flex';
                    } else {
                        el.style.display = 'none';
                    }
                });
            }

            function notifyParentUnread(count) {
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ type: 'patient-chat-unread', count }, window.location.origin);
                }
            }

            function clearUnreadStyles() {
                document.querySelectorAll('.unread-message').forEach((el) => el.classList.remove('unread-message'));
            }

            function getLastMessageId() {
                const allIds = [...embedRenderedIds, ...pageRenderedIds];
                if (allIds.length === 0) return 0;
                return Math.max(...allIds);
            }

            function appendEmbedMessage(msg, forceScroll = false) {
                if (!chatHistory) return;
                const messageId = Number(msg.id || 0);
                if (messageId > 0 && embedRenderedIds.has(messageId)) return;
                const shouldAutoScroll = forceScroll || isMessageViewNearBottom();
                const bubble = document.createElement('div');
                const isPatient = msg.sender_type === 'patient';
                const isUnreadClinicMessage = !isPatient && Number(msg.is_read || 0) === 0;
                bubble.className = `chat-bubble ${isPatient ? 'sent' : 'received'} ${isUnreadClinicMessage ? 'unread-message' : ''}`;
                if (messageId > 0) {
                    bubble.setAttribute('data-message-id', String(messageId));
                    embedRenderedIds.add(messageId);
                }
                bubble.innerHTML = `
                    <div>${escapeHtml(msg.message).replace(/\n/g, '<br>')}</div>
                    <div class="chat-meta">${escapeHtml(formatDate(msg.created_at, true))}</div>
                `;
                chatHistory.appendChild(bubble);
                if (shouldAutoScroll) scrollToLatestMessages();
            }

            function appendPageMessage(msg) {
                if (!pageHistory) return;
                const messageId = Number(msg.id || 0);
                if (messageId > 0 && pageRenderedIds.has(messageId)) return;
                const card = document.createElement('div');
                const isPatient = msg.sender_type === 'patient';
                const isUnreadClinicMessage = !isPatient && Number(msg.is_read || 0) === 0;
                card.className = `message-card ${isUnreadClinicMessage ? 'unread-message' : ''}`;
                if (messageId > 0) {
                    card.setAttribute('data-message-id', String(messageId));
                    pageRenderedIds.add(messageId);
                }
                card.innerHTML = `
                    <div class="message-header">
                        <div class="message-date">${escapeHtml(formatDate(msg.created_at, false))}</div>
                    </div>
                    <div class="message-body">${escapeHtml(msg.message).replace(/\n/g, '<br>')}</div>
                    <div class="message-footer">
                        <span class="message-type ${isPatient ? 'type-sent' : 'type-received'}">${isPatient ? 'Sent' : 'Clinic'}</span>
                    </div>
                `;
                pageHistory.appendChild(card);
            }

            function appendSendResultMessages(msgs) {
                (msgs || []).forEach((msg) => {
                    const isAutoReply = (msg.sender_type === 'system' || msg.sender_type === 'admin')
                        && (msg.message || '').trim() === AUTO_REPLY_TEXT;
                    if (isAutoReply) {
                        ensureTypingIndicator(true);
                        setTimeout(() => {
                            appendEmbedMessage(msg, true);
                            appendPageMessage(msg);
                            ensureTypingIndicator(false);
                        }, 1200);
                    } else {
                        appendEmbedMessage(msg, true);
                        appendPageMessage(msg);
                    }
                });
            }

            function ensureTypingIndicator(visible) {
                const container = chatHistory || pageHistory;
                if (!container) return;
                const shouldAutoScroll = isMessageViewNearBottom();
                let el = document.getElementById('staffTypingIndicator');
                if (!el && visible) {
                    el = document.createElement('div');
                    el.id = 'staffTypingIndicator';
                    el.className = 'typing-indicator';
                    el.innerHTML = '<img src="<?php echo htmlspecialchars($chatStaffAvatar, ENT_QUOTES); ?>" class="typing-avatar" alt="Staff"><span class="typing-bubble"><span class="typing-dots" aria-label="Staff is typing" title="Staff is typing"><span></span><span></span><span></span></span></span>';
                    container.appendChild(el);
                }
                if (el) {
                    el.style.display = visible ? 'flex' : 'none';
                    if (visible && shouldAutoScroll) {
                        scrollToLatestMessages();
                    }
                }
            }

            let typingTimer = null;
            let typingState = 0;
            async function sendTypingState(isTyping) {
                if (typingState === isTyping) return;
                typingState = isTyping;
                const payload = new FormData();
                payload.append('ajax', '1');
                payload.append('action', 'set_typing');
                payload.append('is_typing', isTyping ? '1' : '0');
                try {
                    await fetch(window.location.pathname + window.location.search, {
                        method: 'POST',
                        body: payload,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                } catch (_e) {}
            }

            async function pollMessages() {
                const payload = new FormData();
                payload.append('ajax', '1');
                payload.append('action', 'poll_messages');
                payload.append('last_id', String(getLastMessageId()));

                try {
                    const response = await fetch(window.location.pathname + window.location.search, {
                        method: 'POST',
                        body: payload,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) return;
                    removeEmptyStates();
                    let hasNewClinicMessage = false;
                    (data.messages || []).forEach((msg) => {
                        if (msg.sender_type !== 'patient') hasNewClinicMessage = true;
                        appendEmbedMessage(msg);
                        appendPageMessage(msg);
                    });
                    if (hasNewClinicMessage) playNotificationSound();
                    clearUnreadStyles();
                    lastUnreadCount = Number(data.unread_count || 0);
                    updateUnreadBadges(lastUnreadCount);
                    notifyParentUnread(lastUnreadCount);
                    ensureTypingIndicator(!!data.staff_typing);
                } catch (_err) {
                    // Ignore poll errors and continue polling.
                }
            }

            async function refreshUnreadCount() {
                const payload = new FormData();
                payload.append('ajax', '1');
                payload.append('action', 'get_unread_count');
                try {
                    const response = await fetch(window.location.pathname + window.location.search, {
                        method: 'POST',
                        body: payload,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) return;
                    lastUnreadCount = Number(data.unread_count || 0);
                    updateUnreadBadges(lastUnreadCount);
                    notifyParentUnread(lastUnreadCount);
                } catch (_err) {}
            }

            function bindQuickReplies() {
                document.querySelectorAll('.js-chat-form').forEach((form) => {
                    form.querySelectorAll('.quick-reply-btn').forEach((btn) => {
                        btn.addEventListener('click', function () {
                            const text = btn.getAttribute('data-quick-reply') || '';
                            const input = form.querySelector('textarea[name="message"]');
                            if (!input) return;
                            input.value = text;
                            form.requestSubmit();
                        });
                    });
                });
            }

            async function sendMessage(form) {
                const messageInput = form.querySelector('textarea[name="message"]');
                const submitBtn = form.querySelector('button[type="submit"]');
                const text = (messageInput?.value || '').trim();
                if (!text) return;

                const payload = new FormData();
                payload.append('ajax', '1');
                payload.append('action', 'send_message');
                payload.append('message', text);

                if (submitBtn) submitBtn.disabled = true;
                try {
                    const actionUrl = form.getAttribute('action') || window.location.href;
                    const response = await fetch(actionUrl, {
                        method: 'POST',
                        body: payload,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    const raw = await response.text();
                    let data = null;
                    try {
                        data = JSON.parse(raw);
                    } catch (parseError) {
                        throw new Error('Server returned invalid response. Please run DB migration first.');
                    }
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Failed to send message.');
                    }

                    removeEmptyStates();
                    appendSendResultMessages(data.messages || []);

                    if (messageInput) {
                        messageInput.value = '';
                        messageInput.focus();
                    }
                    sendTypingState(0);
                } catch (err) {
                    alert(err.message || 'Failed to send message.');
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                }
            }

            document.querySelectorAll('.js-chat-form').forEach((form) => {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    sendMessage(form);
                });
                const messageInput = form.querySelector('textarea[name="message"]');
                if (messageInput) {
                    messageInput.addEventListener('keydown', function (event) {
                        if (event.key === 'Enter' && !event.shiftKey) {
                            event.preventDefault();
                            form.requestSubmit();
                        }
                    });
                    messageInput.addEventListener('input', function () {
                        const hasText = (messageInput.value || '').trim().length > 0;
                        if (!hasText) {
                            if (typingTimer) clearTimeout(typingTimer);
                            sendTypingState(0);
                            return;
                        }
                        sendTypingState(1);
                        if (typingTimer) clearTimeout(typingTimer);
                        typingTimer = setTimeout(() => sendTypingState(0), 1500);
                    });
                }
            });
            bindQuickReplies();

            scrollToLatestMessages();
            updateUnreadBadges(lastUnreadCount);
            notifyParentUnread(lastUnreadCount);
            async function markMessagesRead() {
                const payload = new FormData();
                payload.append('ajax', '1');
                payload.append('action', 'mark_read');
                try {
                    const response = await fetch(window.location.pathname + window.location.search, {
                        method: 'POST',
                        body: payload,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) return;
                    clearUnreadStyles();
                    lastUnreadCount = Number(data.unread_count || 0);
                    updateUnreadBadges(lastUnreadCount);
                    notifyParentUnread(lastUnreadCount);
                } catch (_err) {}
            }
            markMessagesRead();
            refreshUnreadCount();
            setInterval(pollMessages, 3000);
            window.addEventListener('beforeunload', function () { sendTypingState(0); });
        })();
    </script>
    <?php
        $__stoEnabled = !empty($patientSessionSettings['enabled']);
        $__stoMs = (int)($patientSessionSettings['timeout_minutes'] ?? 60) * 60 * 1000;
        require __DIR__ . '/../../includes/session_timeout_script.php';
    ?>
</body>
</html>
