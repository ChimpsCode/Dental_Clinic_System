<?php
require_once __DIR__ . '/../../includes/inquiry_module_helper.php';
$defaultFavicon = inquiryPublicAssetUrl('assets/images/Logo.png');
$token = trim($_GET['token'] ?? '');
if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!doctype html>
    <html lang="en">
    <head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Invalid Link</title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($defaultFavicon, ENT_QUOTES); ?>">
    <style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f1f5f9;color:#0f172a;padding:20px}.card{background:#fff;border-radius:24px;box-shadow:0 20px 40px rgba(15,23,42,.06);padding:48px;text-align:center;max-width:480px;width:100%}.card h1{font-size:1.5rem;margin-bottom:12px}.card p{color:#64748b;line-height:1.6}</style>
    </head>
    <body>
    <div class="card"><h1>Invalid Link</h1><p>This conversation link is invalid or has expired. Please contact the clinic if you need assistance.</p></div>
    </body>
    </html>
    <?php
    exit;
}

require_once '../../config/database.php';

ensureInquiryModuleSchema($pdo);

$stmt = $pdo->prepare("
    SELECT id, first_name, middle_name, last_name, email, concern, inquiry_message, status, created_at, assigned_staff_id
    FROM inquiries
    WHERE reply_token = ? AND COALESCE(is_archived, 0) = 0
    LIMIT 1
");
$stmt->execute([$token]);
$inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inquiry) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!doctype html>
    <html lang="en">
    <head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Conversation Not Found</title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($defaultFavicon, ENT_QUOTES); ?>">
    <style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f1f5f9;color:#0f172a;padding:20px}.card{background:#fff;border-radius:24px;box-shadow:0 20px 40px rgba(15,23,42,.06);padding:48px;text-align:center;max-width:480px;width:100%}.card h1{font-size:1.5rem;margin-bottom:12px}.card p{color:#64748b;line-height:1.6}</style>
    </head>
    <body>
    <div class="card"><h1>Conversation Not Found</h1><p>This conversation could not be found. It may have been removed or archived.</p></div>
    </body>
    </html>
    <?php
    exit;
}

$inquiryId = (int)$inquiry['id'];
$replies = fetchInquiryReplies($pdo, $inquiryId);
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$projectBase = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
$apiUrl = $baseUrl . $projectBase . '/api/public_inquiry_reply.php';

$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$clinicSettings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $clinicSettings[$row['setting_key']] = $row['setting_value'];
}
$clinicName = $clinicSettings['clinic_name'] ?? 'RF Dental Clinic';
$clinicEmail = $clinicSettings['clinic_email'] ?? '';
$clinicPhone = $clinicSettings['clinic_phone'] ?? '';
$logo = $clinicSettings['welcome_logo'] ?? '';
$logoFull = $logo ? inquiryPublicAssetUrl($logo) : inquiryPublicAssetUrl('assets/images/Logo.png');

$fullName = trim(implode(' ', array_filter([$inquiry['first_name'] ?? '', $inquiry['middle_name'] ?? '', $inquiry['last_name'] ?? '']))) ?: 'Guest';
$originalMessage = $inquiry['concern'] ?? $inquiry['inquiry_message'] ?? '';
$createdAt = $inquiry['created_at'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Conversation with <?php echo htmlspecialchars($clinicName); ?></title>
    <link rel="icon" href="<?php echo htmlspecialchars($logoFull, ENT_QUOTES); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .top-bar {
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        .top-bar img { height: 36px; width: auto; }
        .top-bar span { font-size: 1.1rem; font-weight: 700; color: #0f172a; }
        .container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }
        .conversation-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(15,23,42,.06);
            width: 100%;
            max-width: 640px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .conversation-header {
            padding: 24px 24px 16px;
            border-bottom: 1px solid #e5e7eb;
        }
        .conversation-header h1 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .conversation-header p {
            font-size: 0.875rem;
            color: #64748b;
        }
        .messages-container {
            flex: 1;
            padding: 20px 24px;
            overflow-y: auto;
            max-height: 460px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: #fcfdff;
        }
        .bubble {
            max-width: 88%;
            padding: 12px 16px;
            border-radius: 18px;
            line-height: 1.55;
            font-size: 14px;
            word-break: break-word;
        }
        .bubble.guest {
            align-self: flex-end;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            border-bottom-right-radius: 6px;
        }
        .bubble.clinic {
            align-self: flex-start;
            background: #f0f2f5;
            color: #111827;
            border-bottom-left-radius: 6px;
        }
        .bubble .meta {
            font-size: 11px;
            opacity: 0.65;
            margin-top: 6px;
        }
        .bubble.clinic .meta { color: rgba(255,255,255,0.8); }
        .bubble.system {
            align-self: center;
            background: transparent;
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 12px;
            max-width: 100%;
        }
        .bubble-label {
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 3px;
            opacity: 0.7;
        }
        .bubble.guest .bubble-label { color: rgba(255,255,255,0.7); }
        .bubble.clinic .bubble-label { color: #64748b; }
        .compose-area {
            padding: 16px 24px 20px;
            border-top: 1px solid #e5e7eb;
            background: #fff;
        }
        .compose-row {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        .compose-row textarea {
            flex: 1;
            border: 1px solid #d1d5db;
            border-radius: 20px;
            padding: 12px 16px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            resize: none;
            min-height: 48px;
            max-height: 120px;
            outline: none;
            transition: border-color .2s;
            background: #f9fafb;
        }
        .compose-row textarea:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,.1);
            background: #fff;
        }
        .send-btn {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: none;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: transform .15s, box-shadow .15s;
            box-shadow: 0 4px 12px rgba(37,99,235,.25);
        }
        .send-btn:hover { transform: scale(1.05); box-shadow: 0 6px 16px rgba(37,99,235,.35); }
        .send-btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }
        .compose-status {
            min-height: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            font-size: 13px;
            color: #64748b;
        }
        .compose-status.error { color: #b91c1c; }
        .compose-status.success { color: #16a34a; }
        .spinner { display: none; width: 16px; height: 16px; border: 2px solid #e5e7eb; border-top-color: #2563eb; border-radius: 50%; animation: spin .6s linear infinite; }
        .spinner.show { display: inline-block; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .footer {
            text-align: center;
            padding: 16px 24px;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #e5e7eb;
            background: #fff;
        }
        .footer a { color: #2563eb; text-decoration: none; }
        .empty-state { text-align: center; padding: 40px 20px; color: #64748b; }
        .empty-state p { font-size: 14px; margin-top: 8px; }
        .inquiry-typing-row {
            align-self: flex-start;
            display: none;
            align-items: center;
            gap: 8px;
        }
        .inquiry-typing-avatar {
            width: 32px;
            height: 32px;
            flex: 0 0 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border-radius: 50%;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
        }
        .inquiry-typing-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        .inquiry-typing-indicator {
            display: flex;
            align-items: center;
            min-width: 52px;
            min-height: 36px;
            padding: 10px 14px;
            border-radius: 18px;
            border-bottom-left-radius: 6px;
            background: #eef1f5;
            color: #64748b;
        }
        .inquiry-typing-dots {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .inquiry-typing-dots span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #94a3b8;
            animation: inquiryTypingWave 1.1s infinite ease-in-out;
        }
        .inquiry-typing-dots span:nth-child(2) { animation-delay: .15s; }
        .inquiry-typing-dots span:nth-child(3) { animation-delay: .3s; }
        @keyframes inquiryTypingWave {
            0%, 60%, 100% { transform: translateY(0); opacity: .45; }
            30% { transform: translateY(-3px); opacity: 1; }
        }
        @media (max-width: 600px) {
            .messages-container { max-height: 360px; padding: 16px; }
            .conversation-header { padding: 20px 16px 12px; }
            .compose-area { padding: 12px 16px 16px; }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <img src="<?php echo htmlspecialchars($logoFull); ?>" alt="Logo" onerror="this.style.display='none'">
        <span><?php echo htmlspecialchars($clinicName); ?></span>
    </div>

    <div class="container">
        <div class="conversation-card">
            <div class="conversation-header">
                <h1>Your Conversation</h1>
                <p>Reply to <?php echo htmlspecialchars($clinicName); ?> regarding your inquiry</p>
            </div>

            <div class="messages-container" id="messagesContainer">
                <div class="bubble guest">
                    <div class="bubble-label">You</div>
                    <div><?php echo nl2br(htmlspecialchars($originalMessage)); ?></div>
                    <div class="meta"><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($createdAt))); ?></div>
                </div>

                <?php if (empty($replies)): ?>
                    <div class="bubble system">Waiting for a reply from the clinic</div>
                <?php else: ?>
                    <?php foreach ($replies as $reply): ?>
                        <?php if ($reply['sender_type'] === 'staff'): ?>
                            <div class="bubble clinic">
                                <div class="bubble-label"><?php echo htmlspecialchars($clinicName); ?></div>
                                <div><?php echo nl2br(htmlspecialchars($reply['message'])); ?></div>
                                <div class="meta"><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($reply['created_at']))); ?></div>
                            </div>
                        <?php else: ?>
                            <div class="bubble guest">
                                <div class="bubble-label">You</div>
                                <div><?php echo nl2br(htmlspecialchars($reply['message'])); ?></div>
                                <div class="meta"><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($reply['created_at']))); ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div id="staffTypingIndicator" class="inquiry-typing-row" aria-live="polite">
                    <div class="inquiry-typing-avatar">
                        <img id="staffTypingAvatar" src="<?php echo htmlspecialchars($logoFull); ?>" alt="RF Dental Clinic">
                    </div>
                    <div class="inquiry-typing-indicator">
                        <span class="inquiry-typing-dots" aria-label="Staff is typing" title="Staff is typing"><span></span><span></span><span></span></span>
                    </div>
                </div>
            </div>

            <div class="compose-area">
                <form id="replyForm" class="compose-row">
                    <textarea id="messageInput" name="message" placeholder="Type your reply..." rows="1" required></textarea>
                    <button type="submit" class="send-btn" id="sendBtn" aria-label="Send">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="m22 2-7 20-4-9-9-4Z"/></svg>
                    </button>
                </form>
                <div class="compose-status" id="composeStatus">
                    <span class="spinner" id="spinner"></span>
                    <span id="statusText"></span>
                </div>
            </div>

            <div class="footer">
                <?php echo htmlspecialchars($clinicName); ?> &mdash;
                <?php if ($clinicPhone): ?><?php echo htmlspecialchars($clinicPhone); ?> &mdash;<?php endif; ?>
                <?php if ($clinicEmail): ?><?php echo htmlspecialchars($clinicEmail); ?><?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        const token = <?php echo json_encode($token); ?>;
        const apiUrl = <?php echo json_encode($apiUrl); ?>;
        const clinicName = <?php echo json_encode($clinicName); ?>;
        const form = document.getElementById('replyForm');
        const input = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');
        const container = document.getElementById('messagesContainer');
        const spinner = document.getElementById('spinner');
        const statusText = document.getElementById('statusText');
        const staffTyping = document.getElementById('staffTypingIndicator');
        let lastReplyId = <?php
            $lastId = 0;
            foreach ($replies as $reply) {
                $lastId = max($lastId, (int)($reply['id'] ?? 0));
            }
            echo (int)$lastId;
        ?>;
        let typingState = 0;
        let typingSentAt = 0;
        let typingTimer = null;
        let pollInFlight = false;

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, function(c) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
            });
        }

        function formatDate(dateStr) {
            const d = new Date(dateStr.replace(' ', 'T'));
            return isNaN(d.getTime()) ? dateStr : d.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
        }

        function addBubble(message, type, timestamp) {
            const bubble = document.createElement('div');
            bubble.className = 'bubble ' + type;
            const label = type === 'clinic' ? clinicName : 'You';
            bubble.innerHTML = '<div class="bubble-label">' + escapeHtml(label) + '</div><div>' + escapeHtml(message).replace(/\n/g, '<br>') + '</div><div class="meta">' + formatDate(timestamp) + '</div>';
            container.insertBefore(bubble, staffTyping || null);
            container.scrollTop = container.scrollHeight;
            const sysMsg = container.querySelector('.bubble.system');
            if (sysMsg) sysMsg.remove();
        }

        function showStaffTyping(visible) {
            if (!staffTyping) return;
            staffTyping.style.display = visible ? 'flex' : 'none';
            if (visible) container.scrollTop = container.scrollHeight;
        }

        function sendTypingState(isTyping) {
            const now = Date.now();
            if (typingState === isTyping && (!isTyping || now - typingSentAt < 2500)) return;
            typingState = isTyping;
            typingSentAt = now;
            const fd = new FormData();
            fd.append('token', token);
            fd.append('action', 'set_typing');
            fd.append('is_typing', isTyping ? '1' : '0');
            fetch(apiUrl, { method: 'POST', body: fd }).catch(function(){});
        }

        async function pollConversation() {
            if (pollInFlight || document.hidden) return;
            pollInFlight = true;
            const fd = new FormData();
            fd.append('token', token);
            fd.append('action', 'conversation_updates');
            fd.append('last_reply_id', String(lastReplyId));
            try {
                const res = await fetch(apiUrl, { method: 'POST', body: fd });
                const data = await res.json();
                if (!data.success) return;
                (data.replies || []).forEach(function(reply) {
                    lastReplyId = Math.max(lastReplyId, parseInt(reply.id, 10) || 0);
                    const type = reply.sender_type === 'staff' ? 'clinic' : 'guest';
                    addBubble(reply.message || '', type, reply.created_at || '');
                });
                showStaffTyping(!!data.other_is_typing);
            } catch (_err) {
            } finally {
                pollInFlight = false;
            }
        }

        function setStatus(message, type) {
            statusText.textContent = message;
            statusText.className = type || '';
        }

        input.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            if (this.value.trim()) {
                sendTypingState(1);
                clearTimeout(typingTimer);
                typingTimer = setTimeout(function() { sendTypingState(0); }, 1500);
            } else {
                clearTimeout(typingTimer);
                sendTypingState(0);
            }
        });
        input.addEventListener('blur', function() {
            clearTimeout(typingTimer);
            sendTypingState(0);
        });

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const message = input.value.trim();
            if (!message) return;

            sendBtn.disabled = true;
            spinner.classList.add('show');
            setStatus('Sending...', '');

            try {
                const formData = new FormData();
                formData.append('token', token);
                formData.append('message', message);

                const res = await fetch(apiUrl, { method: 'POST', body: formData });
                const data = await res.json();

                if (!data.success) {
                    setStatus(data.message || 'Failed to send reply.', 'error');
                    sendBtn.disabled = false;
                    spinner.classList.remove('show');
                    return;
                }

                sendTypingState(0);
                addBubble(message, 'guest', new Date().toISOString().replace('T', ' ').slice(0, 19));
                input.value = '';
                input.style.height = 'auto';
                setStatus('Reply sent successfully.', 'success');
                setTimeout(() => { statusText.textContent = ''; statusText.className = ''; }, 3000);
            } catch (err) {
                setStatus('Something went wrong. Please try again.', 'error');
            } finally {
                sendBtn.disabled = false;
                spinner.classList.remove('show');
            }
        });

        container.scrollTop = container.scrollHeight;
        setInterval(pollConversation, 2000);
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) pollConversation();
        });
        window.addEventListener('beforeunload', function() { sendTypingState(0); });
    </script>
</body>
</html>
