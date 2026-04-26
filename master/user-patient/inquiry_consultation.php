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
require_once '../../includes/inquiry_module_helper.php';

ensureInquiryModuleSchema($pdo);

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

$services = fetchInquiryServices($pdo);

$stmt = $pdo->prepare("
    SELECT i.*, s.name AS service_name
    FROM inquiries i
    LEFT JOIN services s ON s.id = i.service_id
    WHERE i.patient_id = ? AND COALESCE(i.is_archived, 0) = 0
    ORDER BY i.created_at DESC, i.id DESC
");
$stmt->execute([(int)$patient['id']]);
$inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Inquiry / Consultation';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - RF Dental Clinic</title>
    <link rel="icon" type="image/png" href="../../assets/images/Logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/patient_portal.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #f5f7fa; min-height: 100vh; display: flex; }
        .sidebar { width: 220px; position: fixed; top: 0; left: 0; height: 100vh; overflow-y: auto; }
        .main-wrapper { flex: 1; margin-left: 220px; min-height: 100vh; display: flex; flex-direction: column; }
        .dashboard-header { background: white; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; position: sticky; top: 0; z-index: 50; height: 70px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .header-right { display: flex; align-items: center; gap: 16px; }
        .notification-bell { font-size: 20px; color: #6b7280; }
        .user-profile { position: relative; display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #005daa, #0571ca); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; }
        .user-details { display: flex; flex-direction: column; }
        .dropdown-toggle { color: #6b7280; }
        .user-dropdown { position: absolute; top: calc(100% + 8px); right: 0; background: #fff; border-radius: 10px; min-width: 180px; box-shadow: 0 12px 28px rgba(15, 23, 42, 0.15); border: 1px solid #e5e7eb; opacity: 0; visibility: hidden; transform: translateY(-8px); transition: all 0.2s ease; }
        .user-profile:hover .user-dropdown { opacity: 1; visibility: visible; transform: translateY(0); }
        .dropdown-item { display: flex; align-items: center; gap: 10px; padding: 12px 16px; color: #374151; text-decoration: none; }
        .dropdown-item.logout { border-top: 1px solid #e5e7eb; color: #dc2626; }
        .dashboard-main { flex: 1; padding: 24px 32px 40px; width: 100%; max-width: 1500px; margin: 0 auto; }
        .top-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.6fr) minmax(320px, 0.95fr);
            gap: 24px;
            align-items: start;
            margin-bottom: 24px;
        }
        .panel { background: #fff; border-radius: 20px; border: 1px solid #e5e7eb; box-shadow: 0 16px 30px rgba(15, 23, 42, 0.06); }
        .panel-header { padding: 24px 24px 0; }
        .panel-title { font-size: 1.3rem; font-weight: 800; color: #0f172a; }
        .panel-subtitle { margin-top: 6px; color: #64748b; font-size: 14px; }
        .panel-body { padding: 24px; }
        .form-panel {
            overflow: hidden;
        }
        .form-panel .panel-header {
            padding: 24px;
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border-bottom: 1px solid #dbeafe;
        }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.82);
            color: #2563eb;
            border: 1px solid #bfdbfe;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 16px;
            margin-bottom: 18px;
        }
        .readonly-field { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 14px; padding: 14px 16px; }
        .readonly-label, .field-label { display: block; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 6px; }
        .readonly-value { font-size: 15px; color: #0f172a; font-weight: 600; }
        .field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .field-group { margin-bottom: 16px; }
        .field-input, .field-select, .field-textarea {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 14px;
            padding: 13px 14px;
            font: inherit;
            color: #111827;
            background: #fff;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .field-textarea { min-height: 150px; resize: vertical; }
        .field-input:focus, .field-select:focus, .field-textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }
        .helper-text { margin-top: 8px; color: #64748b; font-size: 12px; line-height: 1.5; }
        .actions-row { display: flex; gap: 12px; align-items: center; margin-top: 20px; }
        .btn-primary, .btn-secondary {
            border: none;
            border-radius: 14px;
            padding: 13px 18px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }
        .btn-primary { background: #2563eb; color: #fff; box-shadow: 0 12px 20px rgba(37, 99, 235, 0.18); }
        .btn-secondary { background: #eef2ff; color: #1d4ed8; }
        .btn-primary:hover, .btn-secondary:hover { transform: translateY(-1px); }
        .side-stack {
            display: grid;
            gap: 18px;
        }
        .info-card {
            background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
        }
        .info-card .panel-body {
            display: grid;
            gap: 16px;
        }
        .support-box {
            background: #fff;
            border: 1px solid #dbeafe;
            border-radius: 18px;
            padding: 18px;
        }
        .support-box h3 {
            font-size: 1.05rem;
            color: #0f172a;
            margin-bottom: 12px;
        }
        .support-list {
            display: grid;
            gap: 12px;
            color: #64748b;
            line-height: 1.5;
        }
        .support-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .support-item i {
            color: #2563eb;
            margin-top: 3px;
        }
        .status-guide {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .guide-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 800;
        }
        .guide-pending { background: #fef3c7; color: #92400e; }
        .guide-contacted { background: #dbeafe; color: #1d4ed8; }
        .guide-scheduled { background: #dcfce7; color: #166534; }
        .guide-completed { background: #e5e7eb; color: #475569; }
        .guide-cancelled { background: #fee2e2; color: #b91c1c; }
        .history-section {
            margin-top: 8px;
        }
        .history-table-wrap { overflow-x: auto; }
        .history-table { width: 100%; border-collapse: collapse; min-width: 760px; }
        .history-table th { text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; padding: 0 0 14px; }
        .history-table td { padding: 18px 0; border-top: 1px solid #eef2f7; vertical-align: top; color: #0f172a; font-size: 14px; }
        .concern-preview { max-width: 240px; color: #475569; line-height: 1.5; }
        .status-badge { display: inline-flex; align-items: center; justify-content: center; padding: 7px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .status-Pending { background: #fef3c7; color: #92400e; }
        .status-Contacted { background: #dbeafe; color: #1d4ed8; }
        .status-Scheduled { background: #dcfce7; color: #166534; }
        .status-Completed { background: #e0f2fe; color: #0369a1; }
        .status-Cancelled { background: #fee2e2; color: #b91c1c; }
        .link-btn { background: none; border: none; color: #2563eb; font: inherit; font-weight: 700; cursor: pointer; padding: 0; }
        .empty-state { text-align: center; padding: 50px 24px; color: #64748b; }
        .empty-state i { font-size: 42px; color: #93c5fd; margin-bottom: 14px; }
        .alert { display: none; padding: 14px 16px; border-radius: 14px; margin-bottom: 16px; font-size: 14px; font-weight: 600; }
        .alert.show { display: block; }
        .alert.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert.error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1200;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal-card {
            width: min(720px, 100%);
            background: #fff;
            border-radius: 22px;
            box-shadow: 0 30px 60px rgba(15, 23, 42, 0.2);
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }
        .modal-head { padding: 22px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
        .modal-head h3 { font-size: 1.2rem; color: #0f172a; }
        .modal-close { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; width: 38px; height: 38px; cursor: pointer; }
        .modal-body { padding: 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px 24px; }
        .detail-block.full { grid-column: 1 / -1; }
        .detail-label { font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 700; margin-bottom: 6px; }
        .detail-value { color: #0f172a; line-height: 1.6; }
        @media (max-width: 1180px) {
            .top-layout { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { width: 60px; }
            .main-wrapper { margin-left: 60px; }
            .dashboard-header { padding: 12px 16px; height: 60px; }
            .dashboard-main { padding: 16px; }
            .info-grid,
            .field-grid,
            .modal-body { grid-template-columns: 1fr; }
            .user-details { display: none; }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <a href="../../index.php" class="sidebar-brand">
            <img src="../../assets/images/Logo.png" alt="RF Dental Clinic">
            <span>RF Dental Clinic</span>
        </a>
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

    <div class="main-wrapper">
        <header class="dashboard-header">
            <div class="header-left">
                <h1 style="font-size: 20px; font-weight: 700; color: #1a1a2e; margin: 0;">Inquiry / Consultation</h1>
                <p style="font-size: 13px; color: #6b7280; margin: 4px 0 0;">Send a consultation request and track your inquiry status.</p>
            </div>
            <div class="header-right">
                <div class="notification-bell"><i class="fas fa-bell"></i></div>
                <div class="user-profile">
                    <div class="user-avatar"><?php echo htmlspecialchars(strtoupper(substr(($patient['first_name'] ?: $patient['user_first_name']), 0, 1) . substr(($patient['last_name'] ?: $patient['user_last_name']), 0, 1)) ?: 'P'); ?></div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($displayFirstName ?: 'Patient'); ?></div>
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
            <div class="top-layout">
                <section class="panel form-panel">
                    <div class="panel-header">
                        <div class="eyebrow"><i class="fas fa-stethoscope"></i> Patient Support</div>
                        <div class="panel-title">New Inquiry</div>
                        <div class="panel-subtitle">Tell us what you are experiencing, what service you need, and when you prefer to visit. Your request is linked to your account automatically.</div>
                    </div>
                    <div class="panel-body">
                        <div id="formAlert" class="alert"></div>
                        <form id="inquiryForm">
                            <div class="info-grid">
                                <div class="readonly-field">
                                    <span class="readonly-label">Full Name</span>
                                    <div class="readonly-value"><?php echo htmlspecialchars($fullName ?: 'Patient'); ?></div>
                                </div>
                                <div class="readonly-field">
                                    <span class="readonly-label">Phone Number</span>
                                    <div class="readonly-value"><?php echo htmlspecialchars($phone ?: 'Not provided'); ?></div>
                                </div>
                                <div class="readonly-field">
                                    <span class="readonly-label">Email</span>
                                    <div class="readonly-value"><?php echo htmlspecialchars($email ?: 'Not provided'); ?></div>
                                </div>
                                <div class="field-group" style="margin-bottom: 0;">
                                    <label class="field-label" for="service_id">Service Needed</label>
                                    <select id="service_id" name="service_id" class="field-select">
                                        <option value="">Select a service</option>
                                        <?php foreach ($services as $service): ?>
                                            <option value="<?php echo (int)$service['id']; ?>"><?php echo htmlspecialchars($service['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="field-group">
                                <label class="field-label" for="concern">Concern / Message</label>
                                <textarea id="concern" name="concern" class="field-textarea" placeholder="Describe your concern, symptoms, or what you would like to ask." required></textarea>
                            </div>

                            <div class="field-grid">
                                <div class="field-group">
                                    <label class="field-label" for="preferred_date">Preferred Date</label>
                                    <input type="date" id="preferred_date" name="preferred_date" class="field-input">
                                </div>
                                <div class="field-group">
                                    <label class="field-label" for="preferred_time">Preferred Time</label>
                                    <input type="time" id="preferred_time" name="preferred_time" class="field-input">
                                </div>
                            </div>

                            <div class="field-grid">
                                <div class="field-group">
                                    <label class="field-label" for="pain_level">Pain Level</label>
                                    <select id="pain_level" name="pain_level" class="field-select">
                                        <option value="">Select pain level</option>
                                        <option value="1 - Mild">1 - Mild</option>
                                        <option value="2 - Noticeable">2 - Noticeable</option>
                                        <option value="3 - Moderate">3 - Moderate</option>
                                        <option value="4 - Strong">4 - Strong</option>
                                        <option value="5 - Severe">5 - Severe</option>
                                    </select>
                                </div>
                                <div class="field-group">
                                    <label class="field-label" for="urgency">Urgency</label>
                                    <select id="urgency" name="urgency" class="field-select">
                                        <option value="Low">Low</option>
                                        <option value="Medium" selected>Medium</option>
                                        <option value="High">High</option>
                                    </select>
                                </div>
                            </div>

                            <p class="helper-text">Preferred date and time are requests only. Staff will still confirm the final appointment schedule after reviewing your inquiry.</p>

                            <div class="actions-row">
                                <button type="submit" class="btn-primary">Submit Inquiry</button>
                                <button type="reset" class="btn-secondary">Clear Form</button>
                            </div>
                        </form>
                    </div>
                </section>

                <div class="side-stack">
                    <section class="panel info-card">
                        <div class="panel-header">
                            <div class="eyebrow"><i class="fas fa-link"></i> Account Linked</div>
                            <div class="panel-title">What happens next</div>
                            <div class="panel-subtitle">Your submission is attached to your logged-in patient profile so the clinic can respond faster and keep your request history in one place.</div>
                        </div>
                        <div class="panel-body">
                            <div class="support-box">
                                <h3>Inquiry details</h3>
                                <div class="support-list">
                                    <div class="support-item"><i class="fas fa-user-check"></i><span>Name, phone number, and email are prefilled from your account for this request.</span></div>
                                    <div class="support-item"><i class="fas fa-calendar-days"></i><span>Preferred date, time, and pain level are optional, but they help staff review your request faster.</span></div>
                                    <div class="support-item"><i class="fas fa-shield-heart"></i><span>Your request stays under your patient record automatically. It does not create a queue number.</span></div>
                                </div>
                            </div>
                            <div class="support-box">
                                <h3>Status guide</h3>
                                <div class="status-guide">
                                    <span class="guide-pill guide-pending">Pending</span>
                                    <span class="guide-pill guide-contacted">Contacted</span>
                                    <span class="guide-pill guide-scheduled">Scheduled</span>
                                    <span class="guide-pill guide-completed">Completed</span>
                                    <span class="guide-pill guide-cancelled">Cancelled</span>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <section class="panel history-section">
                <div class="panel-header">
                    <div class="panel-title">My Inquiries</div>
                    <div class="panel-subtitle">Track your submitted requests and their latest clinic status.</div>
                </div>
                <div class="panel-body">
                    <div class="history-table-wrap">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Date Submitted</th>
                                    <th>Service</th>
                                    <th>Concern Preview</th>
                                    <th>Preferred Schedule</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <?php if (empty($inquiries)): ?>
                                    <tr>
                                        <td colspan="6">
                                            <div class="empty-state">
                                                <i class="fas fa-comment-medical"></i>
                                                <h3 style="margin-bottom: 8px; color: #0f172a;">No inquiries yet</h3>
                                                <p>Use the form above to send your first consultation request.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($inquiries as $inquiry): ?>
                                        <?php
                                        $concernText = $inquiry['concern'] ?: ($inquiry['inquiry_message'] ?? '');
                                        $preview = mb_strlen($concernText) > 70 ? mb_substr($concernText, 0, 70) . '...' : $concernText;
                                        $scheduleParts = [];
                                        if (!empty($inquiry['preferred_date'])) {
                                            $scheduleParts[] = date('M d, Y', strtotime($inquiry['preferred_date']));
                                        }
                                        if (!empty($inquiry['preferred_time'])) {
                                            $scheduleParts[] = date('g:i A', strtotime($inquiry['preferred_time']));
                                        }
                                        ?>
                                        <tr data-inquiry='<?php echo htmlspecialchars(json_encode($inquiry), ENT_QUOTES, 'UTF-8'); ?>'>
                                            <td><?php echo date('M d, Y', strtotime($inquiry['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($inquiry['service_name'] ?: 'General Consultation'); ?></td>
                                            <td><div class="concern-preview"><?php echo htmlspecialchars($preview ?: 'No concern provided'); ?></div></td>
                                            <td><?php echo htmlspecialchars($scheduleParts ? implode(' at ', $scheduleParts) : 'No preference'); ?></td>
                                            <td><span class="status-badge status-<?php echo htmlspecialchars($inquiry['status']); ?>"><?php echo htmlspecialchars($inquiry['status']); ?></span></td>
                                            <td><button type="button" class="link-btn" onclick="openDetailModal(<?php echo (int)$inquiry['id']; ?>)">View Details</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <div class="modal-overlay" id="detailModal">
        <div class="modal-card">
            <div class="modal-head">
                <h3>Inquiry Details</h3>
                <button type="button" class="modal-close" onclick="closeDetailModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" id="detailModalBody"></div>
        </div>
    </div>

    <script>
        const inquiryCache = <?php echo json_encode(array_values($inquiries)); ?>;
        const form = document.getElementById('inquiryForm');
        const formAlert = document.getElementById('formAlert');
        const detailModal = document.getElementById('detailModal');
        const detailModalBody = document.getElementById('detailModalBody');

        function showFormAlert(type, message) {
            formAlert.className = `alert show ${type}`;
            formAlert.textContent = message;
        }

        async function refreshHistory() {
            const response = await fetch('../shared/inquiry_actions.php?action=patient_history');
            const data = await response.json();
            if (!data.success) return;
            window.location.reload();
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(form);
            formData.append('action', 'create');

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

                form.reset();
                document.getElementById('urgency').value = 'Medium';
                showFormAlert('success', data.message || 'Inquiry submitted successfully.');
                setTimeout(() => window.location.reload(), 700);
            } catch (error) {
                showFormAlert('error', 'Something went wrong while submitting your inquiry.');
            }
        });

        async function openDetailModal(id) {
            detailModalBody.innerHTML = '<div class="detail-block full"><div class="detail-value">Loading details...</div></div>';
            detailModal.classList.add('active');

            try {
                const response = await fetch(`../shared/inquiry_actions.php?action=detail&id=${id}`);
                const data = await response.json();
                if (!data.success) {
                    detailModalBody.innerHTML = `<div class="detail-block full"><div class="detail-value">${data.message || 'Unable to load details.'}</div></div>`;
                    return;
                }

                const inquiry = data.inquiry;
                const schedule = [
                    inquiry.preferred_date ? new Date(inquiry.preferred_date).toLocaleDateString() : '',
                    inquiry.preferred_time ? new Date(`1970-01-01T${inquiry.preferred_time}`).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : ''
                ].filter(Boolean).join(' at ') || 'No preferred schedule';

                detailModalBody.innerHTML = `
                    <div class="detail-block">
                        <div class="detail-label">Date Submitted</div>
                        <div class="detail-value">${new Date(inquiry.created_at).toLocaleString()}</div>
                    </div>
                    <div class="detail-block">
                        <div class="detail-label">Status</div>
                        <div class="detail-value"><span class="status-badge status-${inquiry.status}">${inquiry.status}</span></div>
                    </div>
                    <div class="detail-block">
                        <div class="detail-label">Service</div>
                        <div class="detail-value">${inquiry.service_name || 'General Consultation'}</div>
                    </div>
                    <div class="detail-block">
                        <div class="detail-label">Preferred Schedule</div>
                        <div class="detail-value">${schedule}</div>
                    </div>
                    <div class="detail-block">
                        <div class="detail-label">Pain Level</div>
                        <div class="detail-value">${inquiry.pain_level || 'Not specified'}</div>
                    </div>
                    <div class="detail-block">
                        <div class="detail-label">Urgency</div>
                        <div class="detail-value">${inquiry.urgency || 'Low'}</div>
                    </div>
                    <div class="detail-block full">
                        <div class="detail-label">Concern / Message</div>
                        <div class="detail-value">${(inquiry.resolved_concern || inquiry.concern || '').replace(/\n/g, '<br>') || 'No concern provided.'}</div>
                    </div>
                    <div class="detail-block full">
                        <div class="detail-label">Internal Notes</div>
                        <div class="detail-value">${inquiry.notes ? inquiry.notes.replace(/\n/g, '<br>') : 'No internal notes yet.'}</div>
                    </div>
                `;
            } catch (error) {
                detailModalBody.innerHTML = '<div class="detail-block full"><div class="detail-value">Unable to load details.</div></div>';
            }
        }

        function closeDetailModal() {
            detailModal.classList.remove('active');
        }

        detailModal.addEventListener('click', (event) => {
            if (event.target === detailModal) {
                closeDetailModal();
            }
        });
    </script>
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
