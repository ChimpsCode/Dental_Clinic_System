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

require_once '../../config/database.php';

try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.first_name, u.middle_name, u.last_name, u.email, u.phone
        FROM patients p 
        LEFT JOIN users u ON u.id = p.user_id 
        WHERE p.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient && !$patient_id) {
        throw new Exception('Patient not found');
    }
    
    if (!$patient_id && $patient) {
        $patient_id = $patient['id'];
        $_SESSION['patient_id'] = $patient_id;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
        $subject = trim($_POST['subject'] ?? '');
        $message_text = trim($_POST['message'] ?? '');
        
        if (!empty($subject) && !empty($message_text)) {
            $stmt = $pdo->prepare("INSERT INTO messages (patient_id, subject, message, sent_by, created_at) VALUES (?, ?, ?, 'patient', NOW())");
            $stmt->execute([$patient_id, $subject, $message_text]);
            $message = 'Message sent successfully!';
            $messageType = 'success';
        } else {
            $message = 'Please fill in all fields.';
            $messageType = 'error';
        }
    }
    
    $stmt = $pdo->prepare("
        SELECT * FROM messages 
        WHERE patient_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $patient = [];
    $messages = [];
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
    <link rel="stylesheet" href="../../assets/css/patient_portal.css">
    <?php endif; ?>
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
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px; }
        .form-input, .form-textarea { width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; font-family: inherit; transition: border-color 0.2s; }
        .form-input:focus, .form-textarea:focus { outline: none; border-color: #005daa; box-shadow: 0 0 0 3px rgba(0,93,170,0.1); }
        .form-textarea { min-height: 150px; resize: vertical; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: #005daa; color: white; }
        .btn-primary:hover { background: #004080; }
        .message-card { padding: 16px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 12px; }
        .message-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
        .message-subject { font-weight: 600; color: #1a1a2e; }
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
        .chat-meta { font-size: 11px; opacity: 0.72; margin-top: 6px; }
        .chat-subject { font-weight: 700; margin-bottom: 6px; }
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
                <?php foreach (array_reverse($messages) as $msg): ?>
                    <div class="chat-bubble <?php echo $msg['sent_by'] === 'patient' ? 'sent' : 'received'; ?>">
                        <?php if (!empty($msg['subject'])): ?>
                            <div class="chat-subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                        <?php endif; ?>
                        <div><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
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
            <form method="POST" action="?embed=1">
                <input type="hidden" name="send_message" value="1">
                <div class="form-group" style="margin-bottom: 10px;">
                    <input type="text" name="subject" class="form-input" placeholder="Subject" required>
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
    <script>
        const chatHistory = document.getElementById('chatHistory');
        if (chatHistory) {
            chatHistory.scrollTop = chatHistory.scrollHeight;
        }
    </script>
    </body>
    </html>
    <?php exit; endif; ?>

    <aside class="sidebar">
        <a href="../../index.php" class="sidebar-brand">
            <img src="../../assets/images/Logo.png" alt="RF Dental Clinic">
            <span>RF Dental</span>
        </a>
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
        </nav>
    </aside>

    <div class="main-wrapper">
        <header class="dashboard-header">
            <div class="header-left">
                <h1 style="font-size: 20px; font-weight: 700; color: #1a1a2e; margin: 0;">Messages</h1>
                <p style="font-size: 13px; color: #6b7280; margin: 4px 0 0 0;">Send messages to the clinic and view your correspondence</p>
            </div>
            <div class="header-right">
                <div class="notification-bell">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php 
                            $first = $patient['first_name'] ?? $_SESSION['user_name'] ?? '';
                            $last = $patient['last_name'] ?? '';
                            $initials = strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
                            echo htmlspecialchars($initials ?: 'P');
                        ?>
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
                    <form method="POST" action="">
                        <input type="hidden" name="send_message" value="1">
                        <div class="form-group">
                            <label class="form-label">Subject</label>
                            <input type="text" name="subject" class="form-input" placeholder="Enter subject" required>
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
                    <h2 class="section-title"><i class="fas fa-inbox"></i> Message History</h2>
                    <?php if (!empty($messages)): ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="message-card">
                                <div class="message-header">
                                    <div class="message-subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                                    <div class="message-date"><?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></div>
                                </div>
                                <div class="message-body"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                <div class="message-footer">
                                    <span class="message-type <?php echo $msg['sent_by'] === 'patient' ? 'type-sent' : 'type-received'; ?>">
                                        <?php echo $msg['sent_by'] === 'patient' ? 'Sent' : 'Received'; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-envelope-open"></i>
                            <h3>No Messages Yet</h3>
                            <p>Send a message to the clinic using the form.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <a href="messages.php" class="floating-message-btn" data-chat-launcher aria-label="Open messages" title="Messages">
        <i class="fas fa-message"></i>
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
    </script>
</body>
</html>
