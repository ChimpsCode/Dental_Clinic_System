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
    
    $stmt = $pdo->prepare("
        SELECT * FROM medical_records 
        WHERE patient_id = ?
        ORDER BY record_date DESC
    ");
    $stmt->execute([$patient_id]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT * FROM prescriptions 
        WHERE patient_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $patient = [];
    $records = [];
    $prescriptions = [];
}

$pageTitle = 'Medical Records';
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #f5f7fa; min-height: 100vh; display: flex; }
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
        .section { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 24px; }
        .section-title { font-size: 18px; font-weight: 700; color: #1a1a2e; margin-bottom: 20px; }
        .tabs { display: flex; gap: 8px; border-bottom: 2px solid #e5e7eb; margin-bottom: 24px; }
        .tab-btn { padding: 12px 24px; background: none; border: none; font-size: 14px; font-weight: 500; color: #6b7280; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s; }
        .tab-btn:hover { color: #1f2937; }
        .tab-btn.active { color: #005daa; border-bottom-color: #005daa; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .record-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
        .record-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .record-type { font-size: 16px; font-weight: 600; color: #1a1a2e; }
        .record-date { font-size: 13px; color: #6b7280; }
        .record-content { font-size: 14px; color: #374151; line-height: 1.6; }
        .record-footer { margin-top: 12px; padding-top: 12px; border-top: 1px solid #e5e7eb; display: flex; gap: 16px; font-size: 13px; color: #6b7280; }
        .record-footer span { display: flex; align-items: center; gap: 6px; }
        .prescription-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
        .prescription-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
        .rx-number { font-size: 12px; color: #6b7280; background: #f3f4f6; padding: 4px 8px; border-radius: 4px; }
        .medicine-list { list-style: none; }
        .medicine-item { padding: 12px 0; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; }
        .medicine-item:last-child { border-bottom: none; }
        .medicine-name { font-weight: 600; color: #1a1a2e; }
        .medicine-dosage { font-size: 13px; color: #6b7280; }
        .empty-state { text-align: center; padding: 48px 24px; color: #6b7280; }
        .empty-state i { font-size: 48px; margin-bottom: 16px; color: #d1d5db; }
        .empty-state h3 { font-size: 18px; color: #374151; margin-bottom: 8px; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .info-card { background: #f9fafb; padding: 16px; border-radius: 8px; }
        .info-label { font-size: 12px; color: #6b7280; text-transform: uppercase; margin-bottom: 4px; }
        .info-value { font-size: 16px; font-weight: 600; color: #1a1a2e; }
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
    <link rel="stylesheet" href="../../assets/css/patient_portal.css">
</head>
<body>
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
            <a href="medical_records.php" class="menu-item active">
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
                <h1 style="font-size: 20px; font-weight: 700; color: #1a1a2e; margin: 0;">Medical Records</h1>
                <p style="font-size: 13px; color: #6b7280; margin: 4px 0 0 0;">View your dental history and prescriptions</p>
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
            <div class="section">
                <h3 class="section-title">Health Information</h3>
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-label">Blood Type</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient['blood_type'] ?? 'Not recorded'); ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Allergies</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient['allergies'] ?? 'None'); ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Medical Conditions</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient['medical_conditions'] ?? 'None'); ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Current Medications</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient['current_medications'] ?? 'None'); ?></div>
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="tabs">
                    <button class="tab-btn active" data-tab="records">Dental Records (<?php echo count($records); ?>)</button>
                    <button class="tab-btn" data-tab="prescriptions">Prescriptions (<?php echo count($prescriptions); ?>)</button>
                </div>

                <div id="records" class="tab-content active">
                    <?php if (!empty($records)): ?>
                        <?php foreach ($records as $record): ?>
                            <div class="record-card">
                                <div class="record-header">
                                    <div class="record-type"><?php echo htmlspecialchars($record['record_type'] ?? 'Dental Checkup'); ?></div>
                                    <div class="record-date"><?php echo date('F j, Y', strtotime($record['record_date'] ?? $record['created_at'])); ?></div>
                                </div>
                                <div class="record-content">
                                    <?php echo nl2br(htmlspecialchars($record['notes'] ?? $record['description'] ?? 'No notes available.')); ?>
                                </div>
                                <div class="record-footer">
                                    <?php if (!empty($record['dentist_name'])): ?>
                                        <span><i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($record['dentist_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-medical-alt"></i>
                            <h3>No Medical Records</h3>
                            <p>Your dental records will appear here after your visits.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="prescriptions" class="tab-content">
                    <?php if (!empty($prescriptions)): ?>
                        <?php foreach ($prescriptions as $rx): ?>
                            <div class="prescription-card">
                                <div class="prescription-header">
                                    <div>
                                        <div class="record-type"><?php echo htmlspecialchars($rx['medication_name'] ?? 'Prescription'); ?></div>
                                        <div class="record-date"><?php echo date('F j, Y', strtotime($rx['created_at'])); ?></div>
                                    </div>
                                    <div class="rx-number"><?php echo htmlspecialchars($rx['dosage'] ?? ''); ?></div>
                                </div>
                                <div class="record-content">
                                    <?php echo nl2br(htmlspecialchars($rx['instructions'] ?? 'No instructions available.')); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-pills"></i>
                            <h3>No Prescriptions</h3>
                            <p>Your prescriptions will appear here after your visits.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                document.getElementById(this.dataset.tab).classList.add('active');
            });
        });
    </script>
    <a href="messages.php" class="floating-message-btn" data-chat-launcher aria-label="Open messages" title="Messages">
        <i class="fas fa-message"></i>
        <span class="unread-badge" id="unreadBadge" style="display: none;">0</span>
    </a>
    <script>
        (function () {
            const launcher = document.querySelector('[data-chat-launcher]');
            if (!launcher) return;
            const unreadBadge = document.getElementById('unreadBadge');
            let overlay = document.getElementById('floatingChatOverlay');
            let panel = document.getElementById('floatingChatPanel');
            if (!overlay) { overlay = document.createElement('div'); overlay.id = 'floatingChatOverlay'; overlay.className = 'floating-chat-overlay'; document.body.appendChild(overlay); }
            if (!panel) { panel = document.createElement('div'); panel.id = 'floatingChatPanel'; panel.className = 'floating-chat-panel'; panel.innerHTML = `<div class="floating-chat-header"><div class="floating-chat-title"><i class="fas fa-comments"></i><span>Messages</span></div><div class="floating-chat-actions"><button type="button" class="floating-chat-control" data-chat-minimize aria-label="Minimize"><i class="fas fa-minus"></i></button><button type="button" class="floating-chat-control" data-chat-close aria-label="Close"><i class="fas fa-xmark"></i></button></div></div><iframe class="floating-chat-frame" src="messages.php?embed=1" title="Messages"></iframe>`; document.body.appendChild(panel); }
            function closeChat() { overlay.classList.remove('is-open'); panel.classList.remove('is-open'); }
            function openChat(event) {
                event.preventDefault();
                overlay.classList.add('is-open');
                panel.classList.add('is-open');
                const frame = panel.querySelector('.floating-chat-frame');
                if (frame && frame.contentWindow) {
                    window.setTimeout(() => {
                        frame.contentWindow.postMessage({ type: 'patient-chat-opened' }, window.location.origin);
                    }, 120);
                }
            }
            function updateUnreadBadge(count) {
                if (!unreadBadge) return;
                if (count > 0) {
                    unreadBadge.textContent = count > 99 ? '99+' : String(count);
                    unreadBadge.style.display = 'inline-flex';
                } else {
                    unreadBadge.style.display = 'none';
                }
            }
            async function pollUnreadCount() {
                try {
                    const payload = new FormData();
                    payload.append('ajax', '1');
                    payload.append('action', 'get_unread_count');
                    const response = await fetch('messages.php', {
                        method: 'POST',
                        body: payload,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) return;
                    updateUnreadBadge(Number(data.unread_count || 0));
                } catch (_err) {}
            }
            launcher.addEventListener('click', openChat);
            overlay.addEventListener('click', closeChat);
            panel.querySelector('[data-chat-close]').addEventListener('click', closeChat);
            panel.querySelector('[data-chat-minimize]').addEventListener('click', closeChat);
            window.addEventListener('message', function (event) {
                if (event.origin !== window.location.origin) return;
                if (event.data && event.data.type === 'patient-chat-unread') {
                    updateUnreadBadge(Number(event.data.count || 0));
                }
            });
            pollUnreadCount();
            setInterval(pollUnreadCount, 3000);
        })();
    </script>
    <script>
        (function() {
            const LOGOUT_AFTER_MS = 10 * 60 * 1000;
            let timer;
            const reset = () => {
                clearTimeout(timer);
                timer = setTimeout(() => window.location.href = '../../logout.php', LOGOUT_AFTER_MS);
            };
            ['click','mousemove','keydown','scroll','touchstart'].forEach(evt =>
                document.addEventListener(evt, reset, { passive: true })
            );
            reset();
        })();
    </script>
</body>
</html>
