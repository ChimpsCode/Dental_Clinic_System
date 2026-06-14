<?php
ob_start();

$__sessionLifetime = 600;
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => $__sessionLifetime,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    ini_set('session.gc_maxlifetime', $__sessionLifetime);
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../master/welcome-page/login.php');
    exit();
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'staff'], true)) {
    http_response_code(403);
    echo "Unauthorized";
    exit();
}

require_once '../../config/database.php';

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

    $pdo->exec("
        UPDATE `messages`
        SET `sender_type` = CASE
            WHEN `sent_by` = 'patient' THEN 'patient'
            WHEN `sent_by` = 'admin' THEN 'admin'
            ELSE 'system'
        END
        WHERE `sender_type` IS NULL OR `sender_type` = ''
    ");
}

function ensureTypingTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `chat_typing_status` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `patient_id` INT(11) NOT NULL,
            `sender_type` ENUM('patient','staff') NOT NULL,
            `is_typing` TINYINT(1) NOT NULL DEFAULT 0,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_chat_typing` (`patient_id`, `sender_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
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

function senderExpr(array $columns): string
{
    if (isset($columns['sender_type'])) {
        return "sender_type";
    }
    if (isset($columns['sent_by'])) {
        return "CASE WHEN sent_by='patient' THEN 'patient' WHEN sent_by='admin' THEN 'admin' ELSE 'system' END";
    }
    return "'patient'";
}

function hasUnreadColumn(array $columns): bool
{
    return isset($columns['is_read']);
}

function markPatientThreadAsRead(PDO $pdo, int $patientId, array $columns): void
{
    if ($patientId <= 0 || !hasUnreadColumn($columns)) {
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE messages
        SET is_read = 1
        WHERE patient_id = ? AND sender_type = 'patient' AND is_read = 0
    ");
    $stmt->execute([$patientId]);
}

function getStaffUnreadSummary(PDO $pdo, array $columns): array
{
    if (!hasUnreadColumn($columns)) {
        return ['total_unread' => 0, 'thread_counts' => [], 'thread_order' => []];
    }

    $stmt = $pdo->query("
        SELECT patient_id, COUNT(*) AS unread_count
        FROM messages
        WHERE sender_type = 'patient' AND is_read = 0
        GROUP BY patient_id
    ");

    $threadCounts = [];
    $totalUnread = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $patientId = (int)($row['patient_id'] ?? 0);
        $count = (int)($row['unread_count'] ?? 0);
        if ($patientId > 0 && $count > 0) {
            $threadCounts[$patientId] = $count;
            $totalUnread += $count;
        }
    }

    $senderSql = senderExpr($columns);
    $orderStmt = $pdo->query("
        SELECT m1.patient_id,
               m1.message AS last_message,
               m1.created_at AS last_at,
               {$senderSql} AS last_sender_type
        FROM messages m1
        INNER JOIN (
            SELECT patient_id, MAX(id) AS max_id
            FROM messages
            WHERE sender_type != 'system'
            GROUP BY patient_id
        ) m2 ON m1.id = m2.max_id
        ORDER BY m1.created_at DESC
    ");
    $threadOrder = [];
    $threadUpdates = [];
    foreach ($orderStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pid = (int)$row['patient_id'];
        $threadOrder[] = $pid;
        $threadUpdates[$pid] = [
            'last_message' => $row['last_message'],
            'last_at' => $row['last_at'],
            'last_sender_type' => $row['last_sender_type'],
        ];
    }

    return [
        'total_unread' => $totalUnread,
        'thread_counts' => $threadCounts,
        'thread_order' => $threadOrder,
        'thread_updates' => $threadUpdates,
    ];
}

try {
    ensureMessagesTable($pdo);
    ensureTypingTable($pdo);
    $messageColumns = tableColumns($pdo, 'messages');
    $senderSqlExpr = senderExpr($messageColumns);
    $staffUserId = (int)($_SESSION['user_id'] ?? 0);

    $isAjaxSend = $_SERVER['REQUEST_METHOD'] === 'POST'
        && (($_POST['ajax'] ?? '0') === '1')
        && (($_POST['action'] ?? '') === 'send_reply');
    $isAjaxPoll = $_SERVER['REQUEST_METHOD'] === 'POST'
        && (($_POST['ajax'] ?? '0') === '1')
        && (($_POST['action'] ?? '') === 'poll_messages');
    $isAjaxTyping = $_SERVER['REQUEST_METHOD'] === 'POST'
        && (($_POST['ajax'] ?? '0') === '1')
        && (($_POST['action'] ?? '') === 'set_typing');
    $isAjaxUnreadSummary = $_SERVER['REQUEST_METHOD'] === 'POST'
        && (($_POST['ajax'] ?? '0') === '1')
        && (($_POST['action'] ?? '') === 'get_unread_summary');
    $isAjaxMarkRead = $_SERVER['REQUEST_METHOD'] === 'POST'
        && (($_POST['ajax'] ?? '0') === '1')
        && (($_POST['action'] ?? '') === 'mark_read');

    if ($isAjaxSend) {
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $messageText = trim($_POST['message'] ?? '');
        if ($patientId <= 0 || $messageText === '') {
            jsonResponse(['success' => false, 'message' => 'Patient and message are required.'], 422);
        }

        $newId = buildMessageInsert($pdo, $messageColumns, [
            'patient_id' => $patientId,
            'sender_id' => $staffUserId,
            'receiver_id' => null,
            'subject' => '',
            'message' => $messageText,
            'sender_type' => 'admin',
            'sent_by' => 'admin',
        ]);

        $stmt = $pdo->prepare("
            SELECT id, patient_id, message, created_at, {$senderSqlExpr} AS sender_type
            FROM messages
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$newId]);
        $msg = $stmt->fetch(PDO::FETCH_ASSOC);
        jsonResponse(['success' => true, 'message' => $msg]);
    }

    if ($isAjaxUnreadSummary) {
        jsonResponse(['success' => true] + getStaffUnreadSummary($pdo, $messageColumns));
    }

    if ($isAjaxMarkRead) {
        $patientId = (int)($_POST['patient_id'] ?? 0);
        if ($patientId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid patient.'], 422);
        }

        markPatientThreadAsRead($pdo, $patientId, $messageColumns);
        jsonResponse(['success' => true] + getStaffUnreadSummary($pdo, $messageColumns));
    }

    if ($isAjaxTyping) {
        $patientId = (int)($_POST['patient_id'] ?? 0);
        if ($patientId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid patient.'], 422);
        }
        $isTyping = (int)($_POST['is_typing'] ?? 0) === 1 ? 1 : 0;
        $typingUpsert = $pdo->prepare("
            INSERT INTO chat_typing_status (patient_id, sender_type, is_typing, updated_at)
            VALUES (?, 'staff', ?, NOW())
            ON DUPLICATE KEY UPDATE is_typing = VALUES(is_typing), updated_at = NOW()
        ");
        $typingUpsert->execute([$patientId, $isTyping]);
        jsonResponse(['success' => true]);
    }

    if ($isAjaxPoll) {
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $lastId = (int)($_POST['last_id'] ?? 0);
        if ($patientId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid patient.'], 422);
        }
        $pollStmt = $pdo->prepare("
            SELECT id, patient_id, message, created_at, {$senderSqlExpr} AS sender_type, is_read
            FROM messages
            WHERE patient_id = ? AND id > ?
            ORDER BY id ASC
        ");
        $pollStmt->execute([$patientId, $lastId]);
        $newMessages = array_values(array_filter(
            $pollStmt->fetchAll(PDO::FETCH_ASSOC),
            static fn(array $message): bool => (($message['sender_type'] ?? '') !== 'system')
        ));
        markPatientThreadAsRead($pdo, $patientId, $messageColumns);

        $typingStmt = $pdo->prepare("
            SELECT is_typing, updated_at
            FROM chat_typing_status
            WHERE patient_id = ? AND sender_type = 'patient'
            LIMIT 1
        ");
        $typingStmt->execute([$patientId]);
        $typingRow = $typingStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $patientTyping = false;
        if ($typingRow) {
            $recent = (time() - strtotime($typingRow['updated_at'])) <= 8;
            $patientTyping = ((int)$typingRow['is_typing'] === 1) && $recent;
        }

        jsonResponse([
            'success' => true,
            'messages' => $newMessages,
            'patient_typing' => $patientTyping
        ]);
    }

    $threadsStmt = $pdo->query("
        SELECT t.patient_id,
               t.last_at,
               COALESCE(CONCAT(p.first_name, ' ', p.last_name), CONCAT('Patient #', t.patient_id)) AS patient_name,
               t.last_message,
               COALESCE(um.unread_count, 0) AS unread_count
        FROM (
            SELECT m1.patient_id, m1.created_at AS last_at, m1.message AS last_message, m1.sender_type AS last_sender_type
            FROM messages m1
            INNER JOIN (
                SELECT patient_id, MAX(id) AS max_id
                FROM messages
                WHERE sender_type != 'system'
                GROUP BY patient_id
            ) m2 ON m1.id = m2.max_id
        ) t
        LEFT JOIN patients p ON p.id = t.patient_id
        LEFT JOIN (
            SELECT patient_id, COUNT(*) AS unread_count
            FROM messages
            WHERE sender_type = 'patient' AND is_read = 0
            GROUP BY patient_id
        ) um ON um.patient_id = t.patient_id
        ORDER BY t.last_at DESC
    ");
    $threads = $threadsStmt->fetchAll(PDO::FETCH_ASSOC);

    $selectedPatientId = (int)($_GET['patient_id'] ?? 0);
    if ($selectedPatientId <= 0 && !empty($threads)) {
        $selectedPatientId = (int)$threads[0]['patient_id'];
    }

    if ($selectedPatientId > 0) {
        markPatientThreadAsRead($pdo, $selectedPatientId, $messageColumns);
        foreach ($threads as &$thread) {
            if ((int)$thread['patient_id'] === $selectedPatientId) {
                $thread['unread_count'] = 0;
                break;
            }
        }
        unset($thread);
    }

    $messages = [];
    if ($selectedPatientId > 0) {
        $msgStmt = $pdo->prepare("
            SELECT id, patient_id, message, created_at, {$senderSqlExpr} AS sender_type, is_read
            FROM messages
            WHERE patient_id = ?
            ORDER BY created_at ASC, id ASC
        ");
        $msgStmt->execute([$selectedPatientId]);
        $messages = array_values(array_filter(
            $msgStmt->fetchAll(PDO::FETCH_ASSOC),
            static fn(array $message): bool => (($message['sender_type'] ?? '') !== 'system')
        ));
    }
} catch (Exception $e) {
    if (($_POST['ajax'] ?? '0') === '1') {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
    $threads = [];
    $messages = [];
    $selectedPatientId = 0;
    $fatalError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body { margin: 0; background: #f4f6fb; font-family: Arial, sans-serif; overflow: hidden; }
        .thread-badge, .fab-badge {
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
        .fab-wrap { position: fixed; right: 18px; bottom: 18px; z-index: 20; }
        .fab-badge { position: absolute; top: -3px; right: -3px; min-width: 22px; height: 22px; border: 2px solid #fff; }
        .staff-message-container {
            display: flex;
            height: 100vh;
            min-height: 100vh;
            overflow: hidden;
        }
        .staff-message-sidebar {
            display: flex;
            flex: 0 0 300px;
            width: 300px;
            min-width: 300px;
            flex-direction: column;
            border-right: 1px solid #dbe1ea;
            background: #fff;
            overflow: hidden;
        }
        .staff-message-chat {
            display: flex;
            flex: 1;
            min-width: 0;
            flex-direction: column;
            overflow: hidden;
        }
        .staff-message-header,
        .staff-message-search,
        .staff-message-tabs,
        .staff-message-chat-header,
        .staff-message-input {
            flex-shrink: 0;
            padding: 16px;
        }
        .staff-message-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px solid #eef2f7;
        }
        .staff-message-header h1 {
            margin: 0;
            font-size: 1.2rem;
        }
        .staff-message-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border: 1px solid #d7dde8;
            border-radius: 999px;
            color: #111827;
            text-decoration: none;
            flex-shrink: 0;
            transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1), background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .staff-message-back:hover,
        .staff-message-back:focus-visible {
            background: #eff6ff;
            border-color: #3b82f6;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
        }
        .staff-message-container {
            transition: opacity 0.34s cubic-bezier(0.22, 1, 0.36, 1), transform 0.34s cubic-bezier(0.22, 1, 0.36, 1), filter 0.34s cubic-bezier(0.22, 1, 0.36, 1);
            transform-origin: center;
            will-change: opacity, transform, filter;
        }
        .staff-message-container.is-leaving {
            opacity: 0;
            transform: scale(0.985);
            filter: blur(2px);
            pointer-events: none;
        }
        .staff-message-back.is-leaving {
            transform: scale(0.94);
        }
        .staff-message-search {
            border-bottom: 1px solid #eef2f7;
        }
        .staff-message-search input,
        .staff-message-composer-input textarea {
            width: 100%;
            min-width: 0;
            border: 1px solid #d7dde8;
            border-radius: 10px;
            padding: 10px 12px;
            resize: none;
        }
        .staff-message-tabs {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            border-bottom: 1px solid #eef2f7;
        }
        .staff-message-tab {
            border: 1px solid #d7dde8;
            background: #fff;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 0.875rem;
            white-space: nowrap;
        }
        .staff-message-tab.is-active {
            border-color: #3b82f6;
            background: #eff6ff;
        }
        .staff-message-list {
            flex: 1;
            overflow-y: auto;
            padding: 8px;
        }
        .thread-item {
            display: flex;
            gap: 12px;
            text-decoration: none;
            color: inherit;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 8px;
            background: #fff;
        }
        .thread-item.active { border-color: #3b82f6; }
        .thread-item.thread-unread { border-color: #fecaca; }
        .staff-message-avatar {
            width: 44px;
            height: 44px;
            flex: 0 0 44px;
            border-radius: 50%;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            font-weight: 700;
        }
        .staff-message-conversation-body {
            display: flex;
            flex: 1;
            min-width: 0;
            justify-content: space-between;
            gap: 10px;
        }
        .staff-message-conversation-main {
            flex: 1;
            min-width: 0;
        }
        .thread-topline {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }
        .staff-message-name {
            font-weight: 600;
        }
        .staff-message-role {
            font-size: 0.8rem;
            color: #6b7280;
            white-space: nowrap;
        }
        .staff-message-preview {
            font-size: 0.875rem;
            color: #4b5563;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .staff-message-conversation-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
            white-space: nowrap;
        }
        .staff-message-time {
            font-size: 0.75rem;
            color: #6b7280;
        }
        .staff-message-chat-header {
            position: sticky;
            top: 0;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            background: #fff;
            border-bottom: 1px solid #dbe1ea;
        }
        .staff-message-chat-user {
            font-size: 1rem;
            font-weight: 600;
        }
        .staff-message-chat-role {
            margin-top: 4px;
            font-size: 0.875rem;
            color: #6b7280;
        }
        .staff-message-action {
            border: 1px solid #d7dde8;
            border-radius: 10px;
            background: #fff;
            padding: 8px 12px;
        }
        .staff-message-messages {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .staff-message-empty {
            margin: auto 0;
            text-align: center;
            color: #6b7280;
        }
        .staff-message-row {
            display: flex;
            width: 100%;
        }
        .staff-message-row.is-received { justify-content: flex-start; }
        .staff-message-row.is-sent { justify-content: flex-end; }
        .staff-message-bubble-wrap {
            max-width: min(76%, 680px);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .bubble {
            border-radius: 14px;
            padding: 12px 14px;
            font-size: 14px;
            line-height: 1.45;
            word-break: break-word;
        }
        .bubble.patient { background: #f3f4f6; color: #111827; border-bottom-left-radius: 6px; }
        .bubble.staff { background: #2563eb; color: #fff; border-bottom-right-radius: 6px; }
        .bubble.unread-patient { background: #fee2e2; border: 1px solid #fecaca; font-weight: 600; }
        .meta { font-size: 11px; opacity: 0.75; }
        .staff-message-row.is-sent .meta { text-align: right; }
        .staff-message-input {
            position: sticky;
            bottom: 0;
            z-index: 2;
            background: #fff;
            border-top: 1px solid #dbe1ea;
        }
        .composer {
            display: flex;
            align-items: flex-end;
            gap: 12px;
        }
        .staff-message-composer-input {
            flex: 1;
            min-width: 0;
        }
        .staff-message-send {
            padding: 10px 16px;
            border: 0;
            border-radius: 10px;
            background: #2563eb;
            color: #fff;
            white-space: nowrap;
        }
        .typing-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #6b7280;
        }
        .typing-dots { display: inline-flex; gap: 4px; align-items: center; }
        .typing-dots span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #9ca3af;
            display: inline-block;
            animation: typingWave 1.1s infinite ease-in-out;
        }
        .typing-dots span:nth-child(2) { animation-delay: 0.15s; }
        .typing-dots span:nth-child(3) { animation-delay: 0.3s; }
        @keyframes typingWave {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.45; }
            30% { transform: translateY(-4px); opacity: 1; }
        }
        @media (max-width: 768px) {
            body { overflow: auto; }
            .staff-message-container {
                flex-direction: column;
                height: auto;
                min-height: 100vh;
            }
            .staff-message-sidebar {
                width: 100%;
                min-width: 0;
                flex-basis: auto;
                max-height: 42vh;
            }
            .staff-message-chat {
                min-height: 58vh;
            }
            .staff-message-bubble-wrap {
                max-width: 92%;
            }
        }
    </style>
</head>
<body>
    <a href="staff_messages.php" class="fab-wrap text-decoration-none" aria-label="Open staff messages">
        <span class="fab-badge" id="staffFabBadge" style="display:none;">0</span>
    </a>
    <?php
        $selectedThread = null;
        foreach ($threads as $thread) {
            if ((int)($thread['patient_id'] ?? 0) === (int)$selectedPatientId) {
                $selectedThread = $thread;
                break;
            }
        }
    ?>
    <div class="staff-message-container">
        <aside class="staff-message-sidebar">
            <header class="staff-message-header">
                <h1>Chats</h1>
                <a href="<?php echo $role === 'admin' ? '../admin/admin_dashboard.php' : '../staff/staff-dashboard.php'; ?>" class="staff-message-back" aria-label="Back to dashboard">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
            </header>

            <div class="staff-message-search">
                <input type="text" placeholder="Search conversations" aria-label="Search conversations">
            </div>

            <nav class="staff-message-tabs" aria-label="Conversation filters">
                <button type="button" class="staff-message-tab is-active" data-filter="all">All</button>
                <button type="button" class="staff-message-tab" data-filter="unread">Unread</button>
                <button type="button" class="staff-message-tab" data-filter="patients">Patients</button>
            </nav>

            <div class="staff-message-list">
                <?php if (!empty($fatalError)): ?>
                    <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($fatalError); ?></div>
                <?php elseif (empty($threads)): ?>
                    <div class="staff-message-empty">No messages yet.</div>
                <?php else: ?>
                    <?php foreach ($threads as $t): ?>
                        <?php
                            $threadUnread = (int)($t['unread_count'] ?? 0);
                            $patientName = trim((string)($t['patient_name'] ?? 'Patient'));
                            $initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $patientName), 0, 2));
                            if ($initials === '') {
                                $initials = 'PT';
                            }
                        ?>
                        <a class="thread-item <?php echo ((int)$t['patient_id'] === (int)$selectedPatientId) ? 'active' : ''; ?> <?php echo $threadUnread > 0 ? 'thread-unread' : ''; ?>"
                           data-thread-patient-id="<?php echo (int)$t['patient_id']; ?>"
                           data-thread-role="patient"
                           data-thread-name="<?php echo htmlspecialchars(strtolower($patientName), ENT_QUOTES); ?>"
                           data-thread-preview="<?php echo htmlspecialchars(strtolower((string)($t['last_message'] ?? '')), ENT_QUOTES); ?>"
                           data-thread-unread="<?php echo $threadUnread > 0 ? '1' : '0'; ?>"
                           href="?patient_id=<?php echo (int)$t['patient_id']; ?>">
                            <div class="staff-message-avatar" aria-hidden="true"><?php echo htmlspecialchars($initials); ?></div>
                            <div class="staff-message-conversation-body">
                                <div class="staff-message-conversation-main">
                                    <div class="thread-topline">
                                        <span class="staff-message-name"><?php echo htmlspecialchars($patientName); ?></span>
                                        <span class="staff-message-role">Patient</span>
                                    </div>
                                    <div class="staff-message-preview"><?php echo ((string)($t['last_sender_type'] ?? '') === 'admin' ? 'you: ' : '') . htmlspecialchars($t['last_message'] ?? ''); ?></div>
                                </div>
                                <div class="staff-message-conversation-meta">
                                    <span class="staff-message-time"><?php echo !empty($t['last_at']) ? date('M j, g:i A', strtotime($t['last_at'])) : ''; ?></span>
                                    <span class="thread-badge" data-thread-badge="<?php echo (int)$t['patient_id']; ?>" style="<?php echo $threadUnread > 0 ? '' : 'display:none;'; ?>">
                                        <?php echo $threadUnread; ?>
                                    </span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <section class="staff-message-chat">
            <header class="staff-message-chat-header">
                <div>
                    <div class="staff-message-chat-user"><?php echo htmlspecialchars($selectedThread['patient_name'] ?? 'Select a conversation'); ?></div>
                    <div class="staff-message-chat-role"><?php echo $selectedThread ? 'Patient' : 'No active thread'; ?></div>
                </div>
            </header>

            <div class="staff-message-messages" id="chatHistory">
                <?php if ($selectedPatientId <= 0): ?>
                    <div class="staff-message-empty">Select a conversation to view messages.</div>
                <?php else: ?>
                    <?php foreach ($messages as $m): ?>
                        <?php $isPatient = (($m['sender_type'] ?? 'patient') === 'patient'); ?>
                        <div class="staff-message-row <?php echo $isPatient ? 'is-received' : 'is-sent'; ?>">
                            <div class="staff-message-bubble-wrap">
                                <div class="bubble <?php echo $isPatient ? 'patient' : 'staff'; ?> <?php echo ($isPatient && (int)($m['is_read'] ?? 1) === 0) ? 'unread-patient' : ''; ?>" data-message-id="<?php echo (int)$m['id']; ?>">
                                    <div><?php echo nl2br(htmlspecialchars($m['message'])); ?></div>
                                </div>
                                <div class="meta"><?php echo date('M j, Y g:i A', strtotime($m['created_at'])); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="staff-message-input">
                <?php if ($selectedPatientId > 0): ?>
                <form class="composer" id="replyForm">
                    <input type="hidden" name="patient_id" value="<?php echo (int)$selectedPatientId; ?>">
                    <div class="staff-message-composer-input">
                        <textarea name="message" rows="2" placeholder="Type a message" required></textarea>
                    </div>
                    <button class="staff-message-send" type="submit"><i class="fa-solid fa-paper-plane"></i> Send</button>
                </form>
                <?php else: ?>
                    <div class="staff-message-empty">Choose a conversation from the sidebar to start replying.</div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <script>
        history.scrollRestoration = 'manual';

        (function () {
            const history = document.getElementById('chatHistory');
            const form = document.getElementById('replyForm');
            const backLink = document.querySelector('.staff-message-back');
            const pageContainer = document.querySelector('.staff-message-container');
            const searchInput = document.querySelector('.staff-message-search input');
            const filterButtons = Array.from(document.querySelectorAll('.staff-message-tab'));
            let activeFilter = 'all';

            function scrollToBottom() {
                if (!history) return;
                var lastMsg = history.lastElementChild;
                if (lastMsg) {
                    lastMsg.scrollIntoView({ block: 'end', inline: 'nearest' });
                } else {
                    history.scrollTop = history.scrollHeight;
                }
            }

            scrollToBottom();
            const patientId = form ? (form.querySelector('input[name="patient_id"]')?.value || '') : '';
            const renderedMessageIds = new Set(
                Array.from(document.querySelectorAll('.bubble'))
                    .map((el) => Number(el.getAttribute('data-message-id')))
                    .filter((id) => Number.isFinite(id) && id > 0)
            );
            let lastKnownUnreadTotal = 0;

            function normalizeText(value) {
                return String(value || '').toLowerCase().trim();
            }

            function applySidebarFilters() {
                const query = normalizeText(searchInput ? searchInput.value : '');
                document.querySelectorAll('.thread-item').forEach((item) => {
                    const name = normalizeText(item.getAttribute('data-thread-name'));
                    const preview = normalizeText(item.getAttribute('data-thread-preview'));
                    const role = normalizeText(item.getAttribute('data-thread-role'));
                    const isUnread = item.getAttribute('data-thread-unread') === '1';
                    const matchesSearch = !query || name.includes(query) || preview.includes(query) || role.includes(query);
                    const matchesFilter = activeFilter === 'all'
                        || (activeFilter === 'unread' && isUnread)
                        || (activeFilter === 'patients' && role === 'patient');
                    item.style.display = (matchesSearch && matchesFilter) ? 'flex' : 'none';
                });
            }

            filterButtons.forEach((button) => {
                button.addEventListener('click', function () {
                    activeFilter = button.getAttribute('data-filter') || 'all';
                    filterButtons.forEach((btn) => btn.classList.toggle('is-active', btn === button));
                    applySidebarFilters();
                });
            });

            if (searchInput) {
                searchInput.addEventListener('input', applySidebarFilters);
            }

            applySidebarFilters();

            if (backLink && pageContainer) {
                backLink.addEventListener('click', function (event) {
                    const targetHref = backLink.getAttribute('href');
                    if (!targetHref) return;
                    event.preventDefault();
                    backLink.classList.add('is-leaving');
                    pageContainer.classList.add('is-leaving');
                    window.setTimeout(function () {
                        window.location.href = targetHref;
                    }, 300);
                });
            }

            if (!form) {
                refreshUnreadSummary();
                return;
            }

            function esc(v) {
                const d = document.createElement('div');
                d.textContent = v || '';
                return d.innerHTML;
            }

            function fmt(dt) {
                const d = new Date((dt || '').replace(' ', 'T'));
                if (Number.isNaN(d.getTime())) return dt || '';
                return d.toLocaleString();
            }

            function getLastMessageId() {
                if (renderedMessageIds.size === 0) return 0;
                return Math.max(...renderedMessageIds);
            }

            function ensureTypingIndicator(visible) {
                if (!history) return;
                let el = document.getElementById('patientTypingIndicator');
                if (!el && visible) {
                    el = document.createElement('div');
                    el.id = 'patientTypingIndicator';
                    el.className = 'staff-message-row is-received';
                    el.innerHTML = '<div class="staff-message-bubble-wrap"><div class="typing-indicator"><span class="typing-dots" aria-label="Patient is typing" title="Patient is typing"><span></span><span></span><span></span></span></div></div>';
                    history.appendChild(el);
                }
                if (el) {
                    el.style.display = visible ? 'flex' : 'none';
                    if (visible) scrollToBottom();
                }
            }

            function appendMessage(msg) {
                const messageId = Number(msg.id || 0);
                if (messageId > 0 && renderedMessageIds.has(messageId)) return;
                const isPatient = (msg.sender_type === 'patient');
                const emptyState = history.querySelector('.staff-message-empty');
                if (emptyState) emptyState.remove();
                const row = document.createElement('div');
                row.className = isPatient ? 'staff-message-row is-received' : 'staff-message-row is-sent';
                const unreadClass = isPatient ? ' unread-patient' : '';
                if (messageId > 0) {
                    renderedMessageIds.add(messageId);
                }
                row.innerHTML = '<div class="staff-message-bubble-wrap"><div class="bubble ' + (isPatient ? 'patient' : 'staff') + unreadClass + '"' + (messageId > 0 ? ' data-message-id="' + String(messageId) + '"' : '') + '><div>' + esc(msg.message).replace(/\n/g, '<br>') + '</div></div><div class="meta">' + esc(fmt(msg.created_at)) + '</div></div>';
                history.appendChild(row);
                scrollToBottom();
            }

            function playNotificationSound() {
                if (!window.AudioContext && !window.webkitAudioContext) return;
                const Ctx = window.AudioContext || window.webkitAudioContext;
                const audioCtx = new Ctx();
                const oscillator = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                oscillator.type = 'sine';
                oscillator.frequency.value = 880;
                gain.gain.value = 0.03;
                oscillator.connect(gain);
                gain.connect(audioCtx.destination);
                oscillator.start();
                oscillator.stop(audioCtx.currentTime + 0.12);
            }

            function updateFabBadge(totalUnread) {
                const badge = document.getElementById('staffFabBadge');
                if (!badge) return;
                if (totalUnread > 0) {
                    badge.textContent = totalUnread > 99 ? '99+' : String(totalUnread);
                    badge.style.display = 'inline-flex';
                } else {
                    badge.style.display = 'none';
                }
            }

            function updateThreadBadges(threadCounts) {
                document.querySelectorAll('[data-thread-badge]').forEach((badgeEl) => {
                    const patientKey = badgeEl.getAttribute('data-thread-badge');
                    const count = Number(threadCounts?.[patientKey] ?? 0);
                    const link = badgeEl.closest('.thread-item');
                    if (count > 0) {
                        badgeEl.textContent = count > 99 ? '99+' : String(count);
                        badgeEl.style.display = 'inline-flex';
                        link?.classList.add('thread-unread');
                        link?.setAttribute('data-thread-unread', '1');
                    } else {
                        badgeEl.style.display = 'none';
                        link?.classList.remove('thread-unread');
                        link?.setAttribute('data-thread-unread', '0');
                    }
                });
                applySidebarFilters();
            }

            function reorderThreads(order, updates) {
                var list = document.querySelector('.staff-message-list');
                if (!list || !order || !order.length) return;
                var fragment = document.createDocumentFragment();
                order.forEach(function (pid) {
                    var el = list.querySelector('.thread-item[data-thread-patient-id="' + pid + '"]');
                    if (!el) return;
                    if (updates && updates[pid]) {
                        var u = updates[pid];
                        var preview = el.querySelector('.staff-message-preview');
                        if (preview) {
                            var senderPrefix = u.last_sender_type === 'admin' ? 'you: ' : '';
                            preview.textContent = senderPrefix + (u.last_message || '');
                        }
                        var timeEl = el.querySelector('.staff-message-time');
                        if (timeEl && u.last_at) {
                            var d = new Date(u.last_at.replace(' ', 'T'));
                            timeEl.textContent = d.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ', ' + d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                        }
                        el.setAttribute('data-thread-preview', (u.last_message || '').toLowerCase());
                        if (u.last_sender_type === 'admin') {
                            el.classList.remove('thread-unread');
                            el.setAttribute('data-thread-unread', '0');
                        } else if (Number(el.getAttribute('data-thread-unread')) === 0) {
                            el.classList.add('thread-unread');
                            el.setAttribute('data-thread-unread', '1');
                        }
                    }
                    fragment.appendChild(el);
                });
                list.appendChild(fragment);
            }

            async function refreshUnreadSummary(playSound = false) {
                const fd = new FormData();
                fd.append('ajax', '1');
                fd.append('action', 'get_unread_summary');
                try {
                    const r = await fetch(window.location.pathname + '?patient_id=' + encodeURIComponent(patientId), { method: 'POST', body: fd });
                    const data = await r.json();
                    if (!r.ok || !data.success) return;
                    const nextTotal = Number(data.total_unread || 0);
                    updateFabBadge(nextTotal);
                    if (data.thread_order) {
                        reorderThreads(data.thread_order, data.thread_updates);
                    }
                    updateThreadBadges(data.thread_counts || {});
                    if (playSound && nextTotal > lastKnownUnreadTotal) {
                        playNotificationSound();
                    }
                    lastKnownUnreadTotal = nextTotal;
                } catch (_e) {}
            }

            let typingTimer = null;
            let typingState = 0;
            async function sendTypingState(isTyping) {
                if (!patientId || typingState === isTyping) return;
                typingState = isTyping;
                const fd = new FormData();
                fd.append('ajax', '1');
                fd.append('action', 'set_typing');
                fd.append('patient_id', patientId);
                fd.append('is_typing', isTyping ? '1' : '0');
                try {
                    await fetch(window.location.pathname + '?patient_id=' + encodeURIComponent(patientId), { method: 'POST', body: fd });
                } catch (_e) {}
            }

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                const txt = form.querySelector('textarea[name="message"]');
                const btn = form.querySelector('button[type="submit"]');
                const patientId = form.querySelector('input[name="patient_id"]').value;
                const val = (txt.value || '').trim();
                if (!val) return;

                const fd = new FormData();
                fd.append('ajax', '1');
                fd.append('action', 'send_reply');
                fd.append('patient_id', patientId);
                fd.append('message', val);

                btn.disabled = true;
                try {
                    const r = await fetch(window.location.pathname + '?patient_id=' + encodeURIComponent(patientId), { method: 'POST', body: fd });
                    const data = await r.json();
                    if (!r.ok || !data.success) {
                        throw new Error(data.message || 'Failed to send reply.');
                    }
                    const msg = data.message;
                    appendMessage(msg);
                    txt.value = '';
                    txt.focus();
                    sendTypingState(0);
                } catch (err) {
                    alert(err.message || 'Failed to send reply.');
                } finally {
                    btn.disabled = false;
                }
            });

            const txt = form.querySelector('textarea[name="message"]');
            if (txt) {
                txt.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' && !event.shiftKey) {
                        event.preventDefault();
                        form.requestSubmit();
                    }
                });
                txt.addEventListener('input', function () {
                    const hasText = (txt.value || '').trim().length > 0;
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

            async function pollMessages() {
                if (!patientId) return;
                const fd = new FormData();
                fd.append('ajax', '1');
                fd.append('action', 'poll_messages');
                fd.append('patient_id', patientId);
                fd.append('last_id', String(getLastMessageId()));
                try {
                    const r = await fetch(window.location.pathname + '?patient_id=' + encodeURIComponent(patientId), { method: 'POST', body: fd });
                    const data = await r.json();
                    if (!r.ok || !data.success) return;
                    let hasNewPatientMessage = false;
                    (data.messages || []).forEach((msg) => {
                        if (msg.sender_type === 'patient') hasNewPatientMessage = true;
                        appendMessage(msg);
                    });
                    if (hasNewPatientMessage) {
                        playNotificationSound();
                    }
                    markCurrentThreadRead();
                    refreshUnreadSummary();
                    ensureTypingIndicator(!!data.patient_typing);
                } catch (_e) {}
            }

            async function markCurrentThreadRead() {
                if (!patientId) return;
                const fd = new FormData();
                fd.append('ajax', '1');
                fd.append('action', 'mark_read');
                fd.append('patient_id', patientId);
                try {
                    const r = await fetch(window.location.pathname + '?patient_id=' + encodeURIComponent(patientId), { method: 'POST', body: fd });
                    const data = await r.json();
                    if (!r.ok || !data.success) return;
                    document.querySelectorAll('.bubble.unread-patient').forEach((el) => el.classList.remove('unread-patient'));
                    updateFabBadge(Number(data.total_unread || 0));
                    if (data.thread_order) {
                        reorderThreads(data.thread_order, data.thread_updates);
                    }
                    updateThreadBadges(data.thread_counts || {});
                    lastKnownUnreadTotal = Number(data.total_unread || 0);
                } catch (_e) {}
            }

            setInterval(pollMessages, 3000);
            refreshUnreadSummary();
            markCurrentThreadRead();
            window.addEventListener('beforeunload', function () { sendTypingState(0); });
        })();
    </script>
</body>
</html>
