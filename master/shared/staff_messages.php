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
            SELECT id, patient_id, message, created_at, {$senderSqlExpr} AS sender_type
            FROM messages
            WHERE patient_id = ? AND id > ?
            ORDER BY id ASC
        ");
        $pollStmt->execute([$patientId, $lastId]);
        $newMessages = $pollStmt->fetchAll(PDO::FETCH_ASSOC);

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
               t.last_message
        FROM (
            SELECT m1.patient_id, m1.created_at AS last_at, m1.message AS last_message
            FROM messages m1
            INNER JOIN (
                SELECT patient_id, MAX(id) AS max_id
                FROM messages
                GROUP BY patient_id
            ) m2 ON m1.id = m2.max_id
        ) t
        LEFT JOIN patients p ON p.id = t.patient_id
        ORDER BY t.last_at DESC
    ");
    $threads = $threadsStmt->fetchAll(PDO::FETCH_ASSOC);

    $selectedPatientId = (int)($_GET['patient_id'] ?? 0);
    if ($selectedPatientId <= 0 && !empty($threads)) {
        $selectedPatientId = (int)$threads[0]['patient_id'];
    }

    $messages = [];
    if ($selectedPatientId > 0) {
        $msgStmt = $pdo->prepare("
            SELECT id, patient_id, message, created_at, {$senderSqlExpr} AS sender_type
            FROM messages
            WHERE patient_id = ?
            ORDER BY created_at ASC, id ASC
        ");
        $msgStmt->execute([$selectedPatientId]);
        $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Staff Messages</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f6fb; }
        .chat-wrap { height: calc(100vh - 24px); margin: 12px; }
        .thread-list { height: 100%; overflow-y: auto; }
        .thread-item { text-decoration: none; color: inherit; display: block; border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; background: #fff; }
        .thread-item.active { border-color: #3b82f6; background: #eff6ff; }
        .chat-card { height: 100%; display: flex; flex-direction: column; }
        .chat-history { flex: 1; overflow-y: auto; padding: 14px; background: #fff; border-radius: 12px 12px 0 0; border: 1px solid #e5e7eb; border-bottom: 0; }
        .bubble { max-width: 76%; border-radius: 14px; padding: 10px 12px; margin-bottom: 8px; font-size: 14px; }
        .bubble.patient { background: #f3f4f6; color: #111827; border-bottom-left-radius: 6px; }
        .bubble.staff { margin-left: auto; background: #2563eb; color: #fff; border-bottom-right-radius: 6px; }
        .meta { font-size: 11px; opacity: 0.75; margin-top: 5px; }
        .composer { border: 1px solid #e5e7eb; border-top: 0; border-radius: 0 0 12px 12px; background: #fff; padding: 10px; }
        .typing-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #6b7280;
            margin: 4px 0 0 2px;
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
    </style>
</head>
<body>
    <div class="chat-wrap">
        <div class="row g-3 h-100">
            <div class="col-lg-4 h-100">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong><i class="fa-solid fa-comments"></i> Patient Inbox</strong>
                        <a href="<?php echo $role === 'admin' ? '../admin/admin_dashboard.php' : '../staff/staff-dashboard.php'; ?>" class="btn btn-sm btn-outline-secondary">Back</a>
                    </div>
                    <div class="card-body thread-list">
                        <?php if (!empty($fatalError)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($fatalError); ?></div>
                        <?php elseif (empty($threads)): ?>
                            <div class="text-muted">No messages yet.</div>
                        <?php else: ?>
                            <?php foreach ($threads as $t): ?>
                                <a class="thread-item <?php echo ((int)$t['patient_id'] === (int)$selectedPatientId) ? 'active' : ''; ?>"
                                   href="?patient_id=<?php echo (int)$t['patient_id']; ?>">
                                    <div class="fw-semibold"><?php echo htmlspecialchars($t['patient_name']); ?></div>
                                    <div class="small text-muted text-truncate"><?php echo htmlspecialchars($t['last_message'] ?? ''); ?></div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-8 h-100">
                <div class="chat-card">
                    <div class="chat-history" id="chatHistory">
                        <?php if ($selectedPatientId <= 0): ?>
                            <div class="text-muted">Select a patient thread.</div>
                        <?php else: ?>
                            <?php foreach ($messages as $m): ?>
                                <?php $isPatient = (($m['sender_type'] ?? 'patient') === 'patient'); ?>
                                <div class="bubble <?php echo $isPatient ? 'patient' : 'staff'; ?>" data-message-id="<?php echo (int)$m['id']; ?>">
                                    <div><?php echo nl2br(htmlspecialchars($m['message'])); ?></div>
                                    <div class="meta"><?php echo date('M j, Y g:i A', strtotime($m['created_at'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($selectedPatientId > 0): ?>
                    <form class="composer" id="replyForm">
                        <input type="hidden" name="patient_id" value="<?php echo (int)$selectedPatientId; ?>">
                        <div class="input-group">
                            <textarea class="form-control" name="message" rows="2" placeholder="Reply to patient..." required></textarea>
                            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Send</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const history = document.getElementById('chatHistory');
            const form = document.getElementById('replyForm');
            if (history) history.scrollTop = history.scrollHeight;
            if (!form) return;
            const patientId = form.querySelector('input[name="patient_id"]')?.value || '';
            const renderedMessageIds = new Set(
                Array.from(document.querySelectorAll('.bubble'))
                    .map((el) => Number(el.getAttribute('data-message-id')))
                    .filter((id) => Number.isFinite(id) && id > 0)
            );

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
                    el.className = 'typing-indicator';
                    el.innerHTML = '<span class="typing-dots" aria-label="Patient is typing" title="Patient is typing"><span></span><span></span><span></span></span>';
                    history.appendChild(el);
                }
                if (el) {
                    el.style.display = visible ? 'block' : 'none';
                    if (visible) history.scrollTop = history.scrollHeight;
                }
            }

            function appendMessage(msg) {
                const messageId = Number(msg.id || 0);
                if (messageId > 0 && renderedMessageIds.has(messageId)) return;
                const isPatient = (msg.sender_type === 'patient');
                const box = document.createElement('div');
                box.className = isPatient ? 'bubble patient' : 'bubble staff';
                if (messageId > 0) {
                    box.setAttribute('data-message-id', String(messageId));
                    renderedMessageIds.add(messageId);
                }
                box.innerHTML = '<div>' + esc(msg.message).replace(/\n/g, '<br>') + '</div><div class="meta">' + esc(fmt(msg.created_at)) + '</div>';
                history.appendChild(box);
                history.scrollTop = history.scrollHeight;
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
                    (data.messages || []).forEach(appendMessage);
                    ensureTypingIndicator(!!data.patient_typing);
                } catch (_e) {}
            }

            setInterval(pollMessages, 3000);
            window.addEventListener('beforeunload', function () { sendTypingState(0); });
        })();
    </script>
</body>
</html>
