<?php
ob_start();

require_once __DIR__ . '/../../includes/session_bootstrap.php';

if (!empty($GLOBALS['APP_SESSION_EXPIRED'])) {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php?expired=1');
    exit();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

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

require_once '../../config/database.php';
require_once '../../includes/patient_session_helper.php';
$patientSessionSettings = getPatientSessionSettings($pdo, (int)($_SESSION['user_id'] ?? 0));
require_once '../../includes/inquiry_module_helper.php';

ensureInquiryModuleSchema($pdo);
ensureInquiryReplySchema($pdo);

// Load clinic settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$clinicSettings = [];
while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
    $clinicSettings[$row['setting_key']] = $row['setting_value'];
}
$clinicName    = $clinicSettings['clinic_name'] ?? 'RF Dental Clinic';
$clinicPhone   = $clinicSettings['clinic_phone'] ?? '';
$clinicEmail   = $clinicSettings['clinic_email'] ?? '';
$clinicAddress = $clinicSettings['clinic_address'] ?? '';
$clinicLogo    = $clinicSettings['welcome_logo'] ?? '';
$logoFull      = $clinicLogo ? '../../' . $clinicLogo : '../../assets/images/Logo.png';

$adminPicStmt = $pdo->prepare("SELECT profile_picture FROM users WHERE role = 'Admin' ORDER BY id ASC LIMIT 1");
$adminPicStmt->execute();
$adminPicPath = $adminPicStmt->fetchColumn();
$clinicAvatar = ($adminPicPath && file_exists(dirname(__DIR__, 2) . '/' . $adminPicPath)) ? '../../' . $adminPicPath : $logoFull;

// Load business hours
$defaultHours = [
    'monday'    => ['open' => '08:00', 'close' => '17:00', 'is_open' => true],
    'tuesday'   => ['open' => '08:00', 'close' => '17:00', 'is_open' => true],
    'wednesday' => ['open' => '08:00', 'close' => '17:00', 'is_open' => true],
    'thursday'  => ['open' => '08:00', 'close' => '17:00', 'is_open' => true],
    'friday'    => ['open' => '08:00', 'close' => '17:00', 'is_open' => true],
    'saturday'  => ['open' => '09:00', 'close' => '13:00', 'is_open' => true],
    'sunday'    => ['open' => '',      'close' => '',      'is_open' => false]
];
$businessHours = isset($clinicSettings['business_hours']) ? json_decode($clinicSettings['business_hours'], true) : $defaultHours;
if (!$businessHours) $businessHours = $defaultHours;

// Build grouped hours for display
function getHoursGroups($hours) {
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $dayNames = [
        'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
        'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'
    ];
    $groups = [];
    $currentGroup = null;
    foreach ($days as $day) {
        if (!empty($hours[$day]['is_open'])) {
            $timeRange = date('g:i A', strtotime($hours[$day]['open'])) . ' - ' . date('g:i A', strtotime($hours[$day]['close']));
            if ($currentGroup && $currentGroup['time'] === $timeRange) {
                $currentGroup['end'] = $day;
            } else {
                if ($currentGroup) $groups[] = $currentGroup;
                $currentGroup = ['start' => $day, 'end' => $day, 'time' => $timeRange];
            }
        } else {
            if ($currentGroup) { $groups[] = $currentGroup; $currentGroup = null; }
        }
    }
    if ($currentGroup) $groups[] = $currentGroup;
    foreach ($groups as &$g) {
        if ($g['start'] === $g['end']) {
            $g['label'] = $dayNames[$g['start']];
        } else {
            $g['label'] = $dayNames[$g['start']] . ' - ' . $dayNames[$g['end']];
        }
    }
    return $groups;
}
$hoursGroups = getHoursGroups($businessHours);

// Load active services
$servicesList = [];
$svcStmt = $pdo->query("SELECT id, name FROM services WHERE is_active = 1 AND (is_archived = 0 OR is_archived IS NULL) ORDER BY name");
while ($svc = $svcStmt->fetch(PDO::FETCH_ASSOC)) {
    $servicesList[] = $svc;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$userStmt = $pdo->prepare("
    SELECT id, first_name, middle_name, last_name, email, phone
    FROM users
    WHERE id = ?
    LIMIT 1
");
$userStmt->execute([$userId]);
$loggedInUser = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];

if (inquiryModuleColumnExists($pdo, 'patients', 'user_id')) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.first_name AS user_first_name, u.middle_name AS user_middle_name,
               u.last_name AS user_last_name, u.email AS user_email, u.phone AS user_phone
        FROM patients p
        LEFT JOIN users u ON u.id = p.user_id
        WHERE p.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
} elseif (!empty($_SESSION['patient_id'])) {
    $stmt = $pdo->prepare("
        SELECT p.*, ? AS user_first_name, ? AS user_middle_name, ? AS user_last_name,
               ? AS user_email, ? AS user_phone
        FROM patients p
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->execute([
        $loggedInUser['first_name'] ?? null,
        $loggedInUser['middle_name'] ?? null,
        $loggedInUser['last_name'] ?? null,
        $loggedInUser['email'] ?? null,
        $loggedInUser['phone'] ?? null,
        (int)$_SESSION['patient_id'],
    ]);
} else {
    $stmt = $pdo->prepare("
        SELECT p.*, ? AS user_first_name, ? AS user_middle_name, ? AS user_last_name,
               ? AS user_email, ? AS user_phone
        FROM patients p
        WHERE p.email = ?
        LIMIT 1
    ");
    $stmt->execute([
        $loggedInUser['first_name'] ?? null,
        $loggedInUser['middle_name'] ?? null,
        $loggedInUser['last_name'] ?? null,
        $loggedInUser['email'] ?? null,
        $loggedInUser['phone'] ?? null,
        $loggedInUser['email'] ?? '',
    ]);
}

$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php');
    exit();
}

$fullName = buildPatientFullName([
    'first_name' => $patient['first_name'] ?: ($patient['user_first_name'] ?? ''),
    'middle_name' => $patient['middle_name'] ?: ($patient['user_middle_name'] ?? ''),
    'last_name' => $patient['last_name'] ?: ($patient['user_last_name'] ?? ''),
]);

$displayFirstName = trim((string)preg_split('/\s+/', $fullName)[0]);

$phone = $patient['phone'] ?: ($patient['user_phone'] ?? '');
$email = $patient['email'] ?: ($patient['user_email'] ?? '');

$inqStmt = $pdo->prepare("
    SELECT i.*, s.name AS service_name
    FROM inquiries i
    LEFT JOIN services s ON s.id = i.service_id
    WHERE i.patient_id = ? AND COALESCE(i.is_archived, 0) = 0
    ORDER BY i.created_at DESC, i.id DESC
    LIMIT 1
");
$inqStmt->execute([(int)$patient['id']]);
$activeInquiry = $inqStmt->fetch(PDO::FETCH_ASSOC);

$messages = [];
if ($activeInquiry) {
    $concernText = $activeInquiry['concern'] ?: ($activeInquiry['inquiry_message'] ?? '');
    if ($concernText !== '') {
        $messages[] = [
            'type' => 'patient',
            'message' => $concernText,
            'created_at' => $activeInquiry['created_at'],
        ];
    }
    $replies = fetchInquiryReplies($pdo, (int)$activeInquiry['id']);
    foreach ($replies as $reply) {
        $messages[] = [
            'id' => $reply['id'],
            'type' => $reply['sender_type'] === 'staff' ? 'clinic' : 'patient',
            'message' => $reply['message'],
            'attachment' => $reply['attachment'] ?? null,
            'created_at' => $reply['created_at'],
        ];
    }
    usort($messages, function ($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });
}

$pageTitle = 'Inquiry / Consultation';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($clinicName); ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($logoFull); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/patient_portal.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; height: 100dvh; overflow: hidden; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #f5f7fa; display: flex; }
        .sidebar { width: 220px; position: fixed; top: 0; left: 0; height: 100vh; height: 100dvh; overflow-y: auto; }
        .main-wrapper { flex: 1; margin-left: 220px; height: 100dvh; min-height: 0; overflow: hidden; display: flex; flex-direction: column; }
        .dashboard-header { background: white; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; flex-shrink: 0; z-index: 50; height: 70px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .header-right { display: flex; align-items: center; gap: 16px; }
        .user-profile { position: relative; display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #005daa, #0571ca); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; }
        .user-details { display: flex; flex-direction: column; }
        .dropdown-toggle { color: #6b7280; }
        .user-dropdown { position: absolute; top: calc(100% + 8px); right: 0; background: #fff; border-radius: 10px; min-width: 180px; box-shadow: 0 12px 28px rgba(15, 23, 42, 0.15); border: 1px solid #e5e7eb; opacity: 0; visibility: hidden; transform: translateY(-8px); transition: all 0.2s ease; }
        .user-profile:hover .user-dropdown { opacity: 1; visibility: visible; transform: translateY(0); }
        .dropdown-item { display: flex; align-items: center; gap: 10px; padding: 12px 16px; color: #374151; text-decoration: none; }
        .dropdown-item.logout { border-top: 1px solid #e5e7eb; color: #dc2626; }
        
        .dashboard-main { flex: 1; min-height: 0; overflow: hidden; display: flex; flex-direction: column; padding: 24px 32px 16px; width: 100%; max-width: 1500px; margin: 0 auto; }
        
        /* Updated Grid Layout */
        .top-layout {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 24px;
            align-items: stretch;
            flex: 1;
            min-height: 0;
            overflow: hidden;
        }

        /* Conversation panel styling (Left Side) */
        .conversation-panel { 
            display: flex; 
            flex-direction: column; 
            height: 100%; 
            min-height: 0;
            max-height: 100%;
            border-radius: 12px; 
            overflow: hidden; 
            border: 1px solid #cecece; /* Blue border to match mockup selection if desired, or #e6eef8 */
            background: #fff; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .conv-header { display:flex; align-items:center; gap:14px; padding:18px 20px; border-bottom:1px solid #e5e7eb; background:#fafbff; flex-shrink:0; }
        .conv-avatar-placeholder { width:44px; height:44px; border-radius:50%; background:#d1d5db; }
        .conv-meta { flex:1; min-width:0; }
        .conv-name { font-size: 15px; font-weight:700; color:#0f172a; }
        .conv-status { font-size:12px; color:#6b7280; margin-top:2px; }
        .conv-body { flex:1; min-height:0; padding:18px 20px; overflow-y:auto; overscroll-behavior:contain; background:#fcfdff; display:flex; flex-direction:column; gap:10px; }
        .chat-bubble { max-width:88%; padding:12px 16px; border-radius:18px; line-height:1.55; font-size:14px; word-break:break-word; }
        .chat-bubble.received { align-self:flex-start; background:#f0f2f5; color:#111827; border-bottom-left-radius:6px; }
        .chat-bubble.sent { align-self:flex-end; background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; border-bottom-right-radius:6px; }
        .chat-label { font-size:11px; font-weight:600; margin-bottom:3px; }
        .chat-bubble.received .chat-label { color:#64748b; }
        .chat-bubble.sent .chat-label { color:rgba(255,255,255,.7); }
        .chat-meta { font-size:11px; opacity:.65; margin-top:6px; }
        .chat-bubble.sent .chat-meta { color:rgba(255,255,255,.8); }
        .typing-message-row { max-width:88px; padding:10px 14px; }
        .chat-typing-indicator { display:flex; align-items:center; min-height:18px; color:#64748b; }
        .chat-typing-dots { display:inline-flex; align-items:center; gap:4px; }
        .chat-typing-dots span { width:7px; height:7px; border-radius:50%; background:#64748b; animation:inquiryTyping 1.1s infinite ease-in-out; }
        .chat-typing-dots span:nth-child(2) { animation-delay:.15s; }
        .chat-typing-dots span:nth-child(3) { animation-delay:.3s; }
        @keyframes inquiryTyping { 0%,60%,100% { transform:translateY(0); opacity:.45; } 30% { transform:translateY(-3px); opacity:1; } }
        
        #inquiryForm { padding:14px 20px calc(18px + env(safe-area-inset-bottom, 0px)); border-top:1px solid #e5e7eb; background:#fff; flex-shrink:0; }
        .chat-send-row { display:flex; gap:10px; align-items:flex-end; }
        .chat-send-row textarea { flex:1; border:1px solid #d1d5db; border-radius:20px; padding:12px 16px; font:inherit; font-size:14px; resize:none; min-height:48px; max-height:120px; outline:none; background:#f9fafb; }
        .chat-send-row textarea:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.1); background:#fff; }
        .chat-attach-btn { width:40px; height:40px; border-radius:50%; border:none; background:#f1f5f9; color:#64748b; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .chat-attach-btn:hover { background:#e2e8f0; color:#2563eb; }
        .send-btn, .chat-send-btn { width:48px; height:48px; border-radius:50%; border:none; background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; box-shadow:0 4px 12px rgba(37,99,235,.25); }
        .conv-input { display:flex; gap:10px; align-items:flex-end; width:100%; }
        .icon-btn { width:42px; height:42px; flex:0 0 42px; display:inline-flex; align-items:center; justify-content:center; background:transparent; border:none; border-radius:50%; color:#64748b; font-size:18px; padding:0; cursor:pointer; transition:background .2s, color .2s; }
        .icon-btn:hover { background:#e8eef7; color:#2563eb; }
        .clinic-info-btn { display: none; }
        .clinic-profile-close { display: none; }
        .clinic-profile-backdrop { display: none; }
        .conv-text { flex:1; min-width:0; height:44px; border:none; padding:0 8px; background:transparent; font:inherit; font-size:14px; outline:none; }
        .conv-text:focus { border:none; box-shadow:none; }
        .send-btn { width:46px; height:46px; flex:0 0 46px; background:linear-gradient(135deg,#2563eb,#1d4ed8); border:none; color:#fff; padding:0; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; box-shadow:0 4px 12px rgba(37,99,235,.25); transition:transform .15s, box-shadow .15s; }
        .send-btn:hover { transform:scale(1.04); box-shadow:0 6px 16px rgba(37,99,235,.32); }
        .send-btn.has-text { color:#fff; background:linear-gradient(135deg,#2563eb,#1d4ed8); }

        .attachment-preview { padding:8px 20px 0; background:#fff; }
        .attachment-preview-item { display:inline-flex; align-items:center; gap:8px; padding:6px 12px; background:#f0f4ff; border:1px solid #c7d2fe; border-radius:8px; font-size:13px; color:#3730a3; max-width:250px; }
        .attachment-preview-item img { width:48px; height:48px; object-fit:cover; border-radius:6px; }
        .attachment-preview-item .att-name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; }
        .attachment-preview-item .att-remove { background:none; border:none; color:#ef4444; cursor:pointer; font-size:16px; padding:0 2px; line-height:1; }
        .chat-attachment-img { max-width:280px; max-height:280px; border-radius:8px; margin-top:6px; cursor:pointer; display:block; }
        .chat-attachment-file { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.25); border-radius:8px; margin-top:6px; color:inherit; text-decoration:none; font-size:13px; }
        .chat-attachment-file.file-received { background:rgba(0,0,0,0.05); border-color:rgba(0,0,0,0.1); color:#3730a3; }

        /* Clinic Profile Panel (Right Side) */
        .clinic-profile-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #cecece;
            padding: 32px 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            height: 100%;
            overflow-y: auto;
        }
        .clinic-profile-header {
            text-align: center;
            margin-bottom: 24px;
        }
        .clinic-avatar-large {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #d1d5db;
            margin: 0 auto 16px;
        }
        .clinic-name-large {
            font-size: 1.25rem;
            font-weight: 700;
            color: #005daa;
            margin-bottom: 6px;
        }
        .clinic-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #10b981;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
        }
        .clinic-reply-time {
            font-size: 0.8rem;
            color: #9ca3af;
            margin-top: 4px;
        }
        .profile-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 32px;
        }
        .btn-block {
            width: 100%;
            display: block;
            text-align: center;
            border-radius: 8px;
            padding: 12px 18px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-primary { background: #0070f3; color: #fff; border: none; }
        .btn-primary:hover { background: #005bb5; }
        .btn-outline { background: #fff; border: 1px solid #d1d5db; color: #374151; }
        .btn-outline:hover { background: #f9fafb; }
        
        .profile-section { margin-bottom: 28px; }
        .profile-section-title {
            font-size: 0.75rem;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 16px;
        }
        .contact-list { list-style: none; padding: 0; margin: 0; }
        .contact-list li {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 16px;
            font-size: 0.875rem;
            color: #374151;
            line-height: 1.4;
        }
        .contact-list i { color: #0070f3; margin-top: 3px; width: 16px; text-align: center; }
        .contact-label { display: block; color: #9ca3af; font-size: 0.75rem; margin-bottom: 2px; }
        
        .hours-list { list-style: none; padding: 0; margin: 0; font-size: 0.875rem; }
        .hours-list li { display: flex; gap: 16px; margin-bottom: 10px; }
        .hours-list i { color: #0070f3; margin-top: 3px; width: 16px; text-align: center; }
        .hours-row { flex: 1; display: flex; justify-content: space-between; color: #374151; }
        .hours-day { color: #9ca3af; }
        
        .services-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .service-pill {
            background: #cffafe;
            color: #0891b2;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 0.75rem;
            text-align: center;
            font-weight: 600;
        }

        .alert { display: none; padding: 14px 16px; border-radius: 14px; margin-bottom: 16px; font-size: 14px; font-weight: 600; flex-shrink: 0; }
        .alert.show { display: block; }
        .alert.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert.error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

        body.clinic-profile-open { overflow: hidden; }

        @media (max-width: 1180px) {
            .dashboard-main {
                padding: 12px 16px 12px;
                max-width: none;
            }
            .top-layout {
                grid-template-columns: 1fr;
                display: flex;
                flex-direction: column;
                gap: 0;
            }
            .conversation-panel { flex: 1; height: auto; width: 100%; min-height: 0; }
            .clinic-info-btn { display: inline-flex; }
            .clinic-profile-backdrop {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, 0.45);
                z-index: 240;
            }
            .clinic-profile-backdrop.is-open { display: block; }
            .clinic-profile-card {
                position: fixed;
                top: 0;
                right: 0;
                bottom: 0;
                width: min(100%, 380px);
                height: 100%;
                max-height: 100dvh;
                border-radius: 0;
                z-index: 250;
                transform: translateX(100%);
                transition: transform 0.25s ease;
                box-shadow: -8px 0 24px rgba(15, 23, 42, 0.18);
            }
            .clinic-profile-card.is-open { transform: translateX(0); }
            .clinic-profile-header { position: relative; padding-top: 8px; }
            .clinic-profile-close {
                display: inline-flex;
                position: absolute;
                top: 8px;
                right: 4px;
                z-index: 1;
            }
        }
        @media (max-width: 768px) {
            .main-wrapper { margin-left: 0; }
            .dashboard-header { padding: 12px 16px; height: auto; }
            .dashboard-main { padding: 8px; }
            .conversation-panel { border-radius: 10px; }
            .user-details { display: none; }
            #inquiryForm { padding: 10px 10px calc(10px + env(safe-area-inset-bottom, 0px)); }
            .conv-input { gap:3px; min-height:54px; padding:5px; }
            .conv-input .icon-btn { width:38px; height:38px; flex-basis:38px; }
            .send-btn { width:42px; height:42px; flex-basis:42px; }
            .conv-text { padding:0 5px; }
            .clinic-profile-card { width: 100%; }
        }
        
        /* Hide the site-wide floating message launcher on the Inquiry page only */
        .floating-message-btn { display: none !important; }
    </style>
    <script>
        (function () {
            // ensure messages list is scrolled to bottom on load
            window.addEventListener('DOMContentLoaded', () => {
                const messagesList = document.getElementById('messagesList');
                if (messagesList) {
                    const scroll = () => { messagesList.scrollTop = messagesList.scrollHeight; };
                    scroll();
                    requestAnimationFrame(scroll);
                    setTimeout(scroll, 100);
                    messagesList.querySelectorAll('img').forEach((image) => {
                        if (!image.complete) image.addEventListener('load', scroll, { once: true });
                    });
                }
            });
        })();
    </script>
</head>
<body>
    <aside class="sidebar" id="patientSidebar">
        <div class="sidebar-brand">
            <img src="<?php echo htmlspecialchars($logoFull); ?>" alt="<?php echo htmlspecialchars($clinicName); ?>">
            <span><?php echo htmlspecialchars($clinicName); ?></span>
        </div>
        <nav class="sidebar-menu">
            <a href="patient-dashboard.php" class="menu-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="inquiry_consultation.php" class="menu-item active">
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
    <?php include '../../includes/patient_sidebar_overlay.php'; ?>

    <div class="main-wrapper">
        <header class="dashboard-header">
            <div class="header-left">
                <?php include '../../includes/patient_menu_toggle.php'; ?>
                <div>
                <h1 style="font-size: 20px; font-weight: 700; color: #1a1a2e; margin: 0;">Inquiry / Consultation</h1>
                <p style="font-size: 13px; color: #6b7280; margin: 4px 0 0;">Send a consultation request and track your inquiry status.</p>
                </div>
            </div>
            <div class="header-right">
                <?php include '../../includes/patient_notification_widget.php'; ?>
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php 
                            $profPic = $patient['profile_picture'] ?? $_SESSION['profile_picture'] ?? '';
                            $first = ($patient['first_name'] ?? '') ?: ($patient['user_first_name'] ?? $_SESSION['user_name'] ?? $_SESSION['display_name'] ?? '');
                            $last = $patient['last_name'] ?: ($patient['user_last_name'] ?? '');
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
                        <div class="user-name"><?php echo htmlspecialchars(trim($first ?: 'Patient')); ?></div>
                        <div class="user-role">Patient</div>
                    </div>
                    <div class="dropdown-toggle"><i class="fas fa-chevron-down"></i></div>
                    <div class="user-dropdown">
                        <a href="profile.php" class="dropdown-item"><i class="fas fa-user"></i><span>Profile</span></a>
                        <a href="../../logout.php" class="dropdown-item logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                    </div>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <div id="formAlert" class="alert"></div>
            
            <div class="top-layout">
                <div class="conversation-panel">
                    <div class="conv-header">
                        <img src="<?php echo htmlspecialchars($clinicAvatar); ?>" alt="<?php echo htmlspecialchars($clinicName); ?>" style="width:44px;height:44px;border-radius:50%;object-fit:cover;">
                        <div class="conv-meta">
                            <div class="conv-name"><?php echo htmlspecialchars($clinicName); ?></div>
                        </div>
                        <button type="button" class="icon-btn clinic-info-btn" id="clinicInfoBtn" aria-label="Clinic information" aria-expanded="false" aria-controls="clinicProfileCard">
                            <i class="fas fa-info-circle"></i>
                        </button>
                    </div>

                    <div id="messagesList" class="conv-body">
                        <?php if (empty($messages)): ?>
                            <div class="chat-bubble received" data-empty-greeting>
                                <div class="chat-label"><?php echo htmlspecialchars($clinicName); ?></div>
                                <div>Hello, How are you today?<br>would you like to book now?</div>
                                <div class="chat-meta">11:00 AM</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <?php $ts = date('h:i A', strtotime($msg['created_at'] ?? 'now')); ?>
                                <?php $att = $msg['attachment'] ?? null; ?>
                                <?php $isImage = $att && preg_match('/\.(jpe?g|png|gif|webp)$/i', $att); ?>
                                <?php $isClinic = $msg['type'] === 'clinic'; ?>
                                <div class="chat-bubble <?php echo $isClinic ? 'received' : 'sent'; ?>"<?php echo !empty($msg['id']) ? ' data-reply-id="' . (int)$msg['id'] . '"' : ''; ?>>
                                    <div class="chat-label"><?php echo $isClinic ? htmlspecialchars($clinicName) : 'You'; ?></div>
                                    <div>
                                        <?php if ($msg['message']): ?><?php echo nl2br(htmlspecialchars($msg['message'])); ?><?php endif; ?>
                                        <?php if ($att): ?>
                                            <?php if ($isImage): ?>
                                                <img src="../<?php echo htmlspecialchars($att); ?>" class="chat-attachment-img" alt="Attachment" onclick="openLightbox(this.src)" style="cursor:pointer;">
                                            <?php else: ?>
                                                <a href="../<?php echo htmlspecialchars($att); ?>" target="_blank" class="chat-attachment-file<?php echo $isClinic ? ' file-received' : ''; ?>"><i class="fas fa-paperclip"></i> <?php echo htmlspecialchars(basename($att)); ?></a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="chat-meta"><?php echo $ts; ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <form id="inquiryForm">
                        <input type="hidden" name="action" value="create">
                        <div id="attachmentPreview" class="attachment-preview" style="display:none;"></div>
                        <div class="chat-send-row">
                            <button type="button" class="chat-attach-btn" id="attachFileBtn" aria-label="attach" title="Attach file">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                            </button>
                            <button type="button" class="chat-attach-btn" id="attachImageBtn" aria-label="image" title="Attach image">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                            </button>
                            <input type="file" id="fileInput" style="display:none;" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,image/*">
                            <textarea id="concern" name="concern" placeholder="Type your message..." rows="1"></textarea>
                            <button type="submit" class="chat-send-btn" aria-label="send">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="m22 2-7 20-4-9-9-4Z"/></svg>
                            </button>
                        </div>
                    </form>
                </div>

                <aside class="clinic-profile-card" id="clinicProfileCard">
                    <div class="clinic-profile-header">
                        <button type="button" class="icon-btn clinic-profile-close" id="clinicProfileClose" aria-label="Close clinic information">
                            <i class="fas fa-times"></i>
                        </button>
                        <img src="<?php echo htmlspecialchars($clinicAvatar); ?>" alt="<?php echo htmlspecialchars($clinicName); ?>" style="width:100px;height:100px;border-radius:50%;object-fit:cover;margin:0 auto 16px;display:block;">
                        <h2 class="clinic-name-large"><?php echo htmlspecialchars($clinicName); ?></h2>
                        <div class="clinic-status-badge"><span class="status-dot"></span> Online</div>
                        <div class="clinic-reply-time">Usually replies within 5 minutes</div>
                    </div>

                    <div class="profile-buttons">
                        <button type="button" class="btn-block btn-primary" onclick="window.location.href='appointments.php';">Book an Appointment</button>
                        <button type="button" class="btn-block btn-outline" onclick="window.location.href='medical_records.php';">View Records</button>
                    </div>

                    <div class="profile-section">
                        <h3 class="profile-section-title">CONTACT INFORMATION</h3>
                        <ul class="contact-list">
                            <?php if (!empty($clinicPhone)): ?>
                            <li>
                                <i class="fas fa-phone"></i>
                                <div>
                                    <span class="contact-label">Phone</span>
                                    <?php echo htmlspecialchars($clinicPhone); ?>
                                </div>
                            </li>
                            <?php endif; ?>
                            <?php if (!empty($clinicEmail)): ?>
                            <li>
                                <i class="fas fa-envelope"></i>
                                <div>
                                    <span class="contact-label">Email</span>
                                    <?php echo htmlspecialchars($clinicEmail); ?>
                                </div>
                            </li>
                            <?php endif; ?>
                            <?php if (!empty($clinicAddress)): ?>
                            <li>
                                <i class="fas fa-map-marker-alt"></i>
                                <div>
                                    <span class="contact-label">Address</span>
                                    <?php echo nl2br(htmlspecialchars($clinicAddress)); ?>
                                </div>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="profile-section">
                        <h3 class="profile-section-title">OFFICE HOURS</h3>
                        <ul class="hours-list">
                            <?php if (empty($hoursGroups)): ?>
                                <li><span style="color:#9ca3af;font-size:0.875rem;">Closed</span></li>
                            <?php else: ?>
                                <?php $first = true; ?>
                                <?php foreach ($hoursGroups as $hg): ?>
                                <li>
                                    <i class="fas fa-clock" style="<?php echo $first ? '' : 'visibility:hidden;'; ?>"></i>
                                    <div class="hours-row">
                                        <span class="hours-day"><?php echo htmlspecialchars($hg['label']); ?></span>
                                        <span><?php echo htmlspecialchars($hg['time']); ?></span>
                                    </div>
                                </li>
                                <?php $first = false; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="profile-section">
                        <h3 class="profile-section-title">AVAILABLE SERVICES</h3>
                        <div class="services-grid">
                            <?php if (empty($servicesList)): ?>
                                <div class="service-pill" style="grid-column: span 2;">No services listed</div>
                            <?php else: ?>
                                <?php foreach ($servicesList as $svc): ?>
                                <div class="service-pill"><?php echo htmlspecialchars($svc['name']); ?></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </aside>
            </div>
            <div class="clinic-profile-backdrop" id="clinicProfileBackdrop"></div>
        </main>
    </div>

    <script>
        const form = document.getElementById('inquiryForm');
        const formAlert = document.getElementById('formAlert');
        const messagesList = document.getElementById('messagesList');
        const concernInput = document.getElementById('concern');
        const sendBtn = document.querySelector('.chat-send-btn, .send-btn');
        const fileInput = document.getElementById('fileInput');
        const attachFileBtn = document.getElementById('attachFileBtn');
        const attachImageBtn = document.getElementById('attachImageBtn');
        const attachmentPreview = document.getElementById('attachmentPreview');
        const clinicInfoBtn = document.getElementById('clinicInfoBtn');
        const clinicProfileCard = document.getElementById('clinicProfileCard');
        const clinicProfileBackdrop = document.getElementById('clinicProfileBackdrop');
        const clinicProfileClose = document.getElementById('clinicProfileClose');
        const PROFILE_BREAKPOINT = 1180;

        function setClinicProfileOpen(open) {
            if (!clinicProfileCard) return;
            clinicProfileCard.classList.toggle('is-open', open);
            if (clinicProfileBackdrop) {
                clinicProfileBackdrop.classList.toggle('is-open', open);
            }
            document.body.classList.toggle('clinic-profile-open', open);
            if (clinicInfoBtn) clinicInfoBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        function closeClinicProfile() {
            setClinicProfileOpen(false);
        }

        if (clinicInfoBtn) {
            clinicInfoBtn.addEventListener('click', () => {
                setClinicProfileOpen(!clinicProfileCard.classList.contains('is-open'));
            });
        }
        if (clinicProfileClose) clinicProfileClose.addEventListener('click', closeClinicProfile);
        if (clinicProfileBackdrop) clinicProfileBackdrop.addEventListener('click', closeClinicProfile);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeClinicProfile();
        });
        window.addEventListener('resize', () => {
            if (window.innerWidth > PROFILE_BREAKPOINT) closeClinicProfile();
        });

        let selectedFile = null;
        let activeInquiryId = <?php echo (int)($activeInquiry['id'] ?? 0); ?>;
        const clinicAvatarUrl = <?php echo json_encode($clinicAvatar); ?>;
        const clinicNameLabel = <?php echo json_encode($clinicName); ?>;
        const renderedReplyIds = new Set(
            Array.from(messagesList.querySelectorAll('[data-reply-id]'))
                .map((element) => Number(element.dataset.replyId))
                .filter((id) => Number.isFinite(id) && id > 0)
        );
        let conversationPollInFlight = false;
        let typingState = 0;
        let lastTypingSentAt = 0;
        let typingStopTimer = null;

        concernInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                form.requestSubmit();
            }
        });
        concernInput.addEventListener('input', () => {
            concernInput.style.height = 'auto';
            concernInput.style.height = Math.min(concernInput.scrollHeight, 120) + 'px';
            sendBtn.classList.toggle('has-text', concernInput.value.trim().length > 0 || selectedFile !== null);
            const hasText = concernInput.value.trim().length > 0;
            if (!hasText) {
                clearTimeout(typingStopTimer);
                sendTypingState(0);
                return;
            }
            sendTypingState(1);
            clearTimeout(typingStopTimer);
            typingStopTimer = setTimeout(() => sendTypingState(0), 3000);
        });
        concernInput.addEventListener('blur', () => {
            clearTimeout(typingStopTimer);
            sendTypingState(0);
        });

        attachFileBtn.addEventListener('click', () => {
            fileInput.accept = '.pdf,.doc,.docx,.xls,.xlsx,.txt,image/*';
            fileInput.click();
        });
        attachImageBtn.addEventListener('click', () => {
            fileInput.accept = 'image/*';
            fileInput.click();
        });

        fileInput.addEventListener('change', () => handleFileSelect(fileInput.files[0]));

        function handleFileSelect(file) {
            if (!file) return;
            if (file.size > 10 * 1024 * 1024) {
                showFormAlert('error', 'File size must be 10MB or less.');
                return;
            }
            selectedFile = file;
            attachmentPreview.style.display = 'block';
            const isImage = file.type.startsWith('image/');
            attachmentPreview.innerHTML = '<div class="attachment-preview-item">'
                + (isImage ? '<img src="' + URL.createObjectURL(file) + '" alt="Preview">' : '<i class="fas fa-file" style="font-size:20px;"></i>')
                + '<span class="att-name">' + escapeHtml(file.name) + '</span>'
                + '<button type="button" class="att-remove" onclick="clearAttachment()">&times;</button>'
                + '</div>';
            sendBtn.classList.add('has-text');
        }

        function clearAttachment() {
            selectedFile = null;
            fileInput.value = '';
            attachmentPreview.style.display = 'none';
            attachmentPreview.innerHTML = '';
            sendBtn.classList.toggle('has-text', concernInput.value.trim().length > 0);
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }

        function showFormAlert(type, message) {
            formAlert.className = `alert show ${type}`;
            formAlert.textContent = message;
        }

        function openLightbox(src) {
            let overlay = document.getElementById('imgLightbox');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'imgLightbox';
                overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:999999;display:flex;align-items:center;justify-content:center;cursor:pointer;';
                overlay.innerHTML = '<img id="lightboxImg" style="max-width:90vw;max-height:90vh;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,0.5);">';
                overlay.addEventListener('click', () => overlay.style.display = 'none');
                document.body.appendChild(overlay);
            }
            document.getElementById('lightboxImg').src = src;
            overlay.style.display = 'flex';
        }

        function getLastReplyId() {
            return renderedReplyIds.size ? Math.max(...renderedReplyIds) : 0;
        }

        function isConversationNearBottom() {
            return messagesList.scrollHeight - messagesList.scrollTop - messagesList.clientHeight <= 120;
        }

        function scrollConversationToBottom(force = false) {
            if (!force && !isConversationNearBottom()) return;
            const scroll = () => { messagesList.scrollTop = messagesList.scrollHeight; };
            scroll();
            requestAnimationFrame(scroll);
            setTimeout(scroll, 100);
            if (force) {
                messagesList.querySelectorAll('img').forEach((image) => {
                    if (!image.complete) image.addEventListener('load', scroll, { once: true });
                });
            }
        }

        function formatMessageTime(value) {
            const date = new Date(String(value || '').replace(' ', 'T'));
            return Number.isNaN(date.getTime())
                ? ''
                : date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        function appendConversationReply(reply, localAttachmentUrl = '', forceScroll = false) {
            const replyId = Number(reply.id || 0);
            if (replyId > 0 && renderedReplyIds.has(replyId)) return;
            const shouldAutoScroll = forceScroll || isConversationNearBottom();

            const emptyGreeting = messagesList.querySelector('[data-empty-greeting]');
            if (emptyGreeting) emptyGreeting.remove();

            const isClinic = reply.sender_type === 'staff';
            const row = document.createElement('div');
            row.className = `chat-bubble ${isClinic ? 'received' : 'sent'}`;
            if (replyId > 0) {
                row.dataset.replyId = String(replyId);
                renderedReplyIds.add(replyId);
            }

            let attachmentHtml = '';
            const attachment = reply.attachment || '';
            const attachmentSrc = localAttachmentUrl || (attachment ? '../' + attachment : '');
            if (attachmentSrc) {
                const isImage = localAttachmentUrl
                    ? selectedFile && selectedFile.type.startsWith('image/')
                    : /\.(jpe?g|png|gif|webp)$/i.test(attachment);
                attachmentHtml = isImage
                    ? `<img src="${escapeHtml(attachmentSrc)}" class="chat-attachment-img" alt="Attachment" onclick="openLightbox(this.src)">`
                    : `<a href="${escapeHtml(attachmentSrc)}" target="_blank" class="chat-attachment-file${isClinic ? ' file-received' : ''}"><i class="fas fa-paperclip"></i> ${escapeHtml(attachment.split('/').pop() || selectedFile?.name || 'Attachment')}</a>`;
            }

            row.innerHTML = `<div class="chat-label">${escapeHtml(isClinic ? clinicNameLabel : 'You')}</div><div>${escapeHtml(reply.message || '').replace(/\n/g, '<br>')}${attachmentHtml}</div><div class="chat-meta">${escapeHtml(formatMessageTime(reply.created_at))}</div>`;

            const typing = document.getElementById('clinicTypingIndicator');
            messagesList.insertBefore(row, typing || null);
            if (shouldAutoScroll) scrollConversationToBottom(true);
        }

        function showClinicTyping(visible) {
            const shouldAutoScroll = isConversationNearBottom();
            let indicator = document.getElementById('clinicTypingIndicator');
            if (!indicator && visible) {
                indicator = document.createElement('div');
                indicator.id = 'clinicTypingIndicator';
                indicator.className = 'chat-bubble received typing-message-row';
                indicator.innerHTML = `<div class="chat-typing-indicator"><span class="chat-typing-dots" aria-label="Staff is typing"><span></span><span></span><span></span></span></div>`;
                messagesList.appendChild(indicator);
            }
            if (indicator) {
                indicator.style.display = visible ? 'block' : 'none';
                if (visible && shouldAutoScroll) scrollConversationToBottom(true);
            }
        }

        async function sendTypingState(isTyping) {
            if (!activeInquiryId) return;
            const now = Date.now();
            if (typingState === isTyping && (!isTyping || now - lastTypingSentAt < 2500)) return;
            typingState = isTyping;
            lastTypingSentAt = now;
            const data = new FormData();
            data.append('action', 'set_typing');
            data.append('id', String(activeInquiryId));
            data.append('is_typing', isTyping ? '1' : '0');
            try {
                await fetch('../shared/inquiry_actions.php', { method: 'POST', body: data });
            } catch (_error) {}
        }

        async function pollConversation() {
            if (!activeInquiryId || conversationPollInFlight || document.hidden) return;
            conversationPollInFlight = true;
            const data = new FormData();
            data.append('action', 'conversation_updates');
            data.append('id', String(activeInquiryId));
            data.append('last_reply_id', String(getLastReplyId()));
            try {
                const response = await fetch('../shared/inquiry_actions.php', { method: 'POST', body: data });
                const result = await response.json();
                if (!response.ok || !result.success) return;
                (result.replies || []).forEach((reply) => appendConversationReply(reply));
                showClinicTyping(!!result.other_is_typing);
            } catch (_error) {
                // A later poll will retry without changing the current conversation.
            } finally {
                conversationPollInFlight = false;
            }
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const message = concernInput.value.trim();
            if (!message && !selectedFile) {
                showFormAlert('error', 'Please type a message or attach a file.');
                return;
            }

            const formData = new FormData(form);
            formData.append('action', 'create');
            if (selectedFile) {
                formData.append('attachment', selectedFile);
            } else {
                formData.delete('attachment');
            }

            try {
                const response = await fetch('../shared/inquiry_actions.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (!data.success) {
                    showFormAlert('error', data.message || 'Unable to submit inquiry.');
                    return;
                }

                activeInquiryId = Number(data.inquiry_id || activeInquiryId);
                const localAttachmentUrl = selectedFile ? URL.createObjectURL(selectedFile) : '';
                appendConversationReply({
                    id: data.reply_id || 0,
                    sender_type: 'patient',
                    message,
                    attachment: selectedFile ? selectedFile.name : '',
                    created_at: new Date().toISOString()
                }, localAttachmentUrl, true);
                form.reset();
                clearAttachment();
                sendTypingState(0);
            } catch (error) {
                showFormAlert('error', 'Something went wrong while submitting your inquiry.');
            }
        });

        setInterval(pollConversation, 2000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) pollConversation();
        });
        window.addEventListener('beforeunload', () => sendTypingState(0));
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
    <?php
        $__stoEnabled = !empty($patientSessionSettings['enabled']);
        $__stoMs = (int)($patientSessionSettings['timeout_minutes'] ?? 60) * 60 * 1000;
        require __DIR__ . '/../../includes/session_timeout_script.php';
    ?>
</body>
</html>