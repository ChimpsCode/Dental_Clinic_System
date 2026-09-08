<?php
ob_start();

require_once __DIR__ . '/../../includes/session_bootstrap.php';

if (!empty($GLOBALS['APP_SESSION_EXPIRED'])) {
    ob_end_clean();
    header('Location: ../../master/welcome-page/login.php?expired=1');
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

require_once '../../config/database.php';
require_once '../../includes/patient_session_helper.php';
$patientSessionSettings = getPatientSessionSettings($pdo, (int)($_SESSION['user_id'] ?? 0));

require_once '../../includes/notification_functions.php';
ensureNotificationSchema($pdo);
checkAndNotifyOverduePayments($pdo);

try {
    $accountStmt = $pdo->prepare("SELECT email, phone FROM users WHERE id = ? LIMIT 1");
    $accountStmt->execute([$user_id]);
    $account = $accountStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $conditions = [];
    $parameters = [];
    if ($patient_id) {
        $conditions[] = 'p.id = ?';
        $parameters[] = (int)$patient_id;
    }
    if (!empty($account['email'])) {
        $conditions[] = 'p.email = ?';
        $parameters[] = $account['email'];
    }
    if (!empty($account['phone'])) {
        $conditions[] = 'p.phone = ?';
        $parameters[] = $account['phone'];
    }
    $patient = null;
    if ($conditions) {
        $stmt = $pdo->prepare(
            "SELECT p.*, p.first_name, p.middle_name, p.last_name,
                    COALESCE(u.email, p.email) AS email,
                    COALESCE(u.phone, p.phone) AS phone
             FROM patients p
             LEFT JOIN users u ON u.id = ?
             WHERE (" . implode(' OR ', $conditions) . ")
               AND COALESCE(p.is_archived, 0) = 0
             ORDER BY CASE WHEN p.id = ? THEN 0 ELSE 1 END
             LIMIT 1"
        );
        $stmt->execute(array_merge([$user_id], $parameters, [(int)($patient_id ?: 0)]));
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$patient && !$patient_id) {
        throw new Exception('Patient not found');
    }
    
    if ($patient) {
        $patient_id = $patient['id'];
        $_SESSION['patient_id'] = $patient_id;
    }

    $stmt = $pdo->prepare("
        SELECT 
            b.id AS billing_id,
            CONCAT('INV-', LPAD(b.id, 4, '0')) AS invoice_number,
            b.total_amount,
            b.paid_amount,
            b.balance,
            COALESCE(b.payment_status, 'Unpaid') AS payment_status,
            b.billing_date,
            b.due_date,
            q.treatment_type,
            q.queue_number,
            b.subtotal_amount,
            b.discount_type,
            b.discount_amount,
            b.notes
        FROM billing b
        LEFT JOIN queue q ON CAST(q.patient_id AS UNSIGNED) = b.patient_id AND DATE(q.created_at) = DATE(b.billing_date)
        WHERE b.patient_id = ?
          AND LOWER(COALESCE(b.payment_status, 'unpaid')) != 'cancelled'
        ORDER BY b.billing_date DESC, b.id DESC
    ");
    $stmt->execute([$patient_id]);
    $billings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT py.*, CONCAT('INV-', LPAD(py.billing_id, 4, '0')) AS invoice_number
        FROM payments py
        WHERE py.patient_id = ?
        ORDER BY py.payment_date DESC, py.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalPaid = 0;
    $totalPending = 0;
    $totalAmount = 0;
    $nearestDueDate = null;
    $nearestDueBalance = 0;
    foreach ($billings as $b) {
        $statusLower = strtolower((string)($b['payment_status'] ?? 'unpaid'));
        if ($statusLower === 'cancelled') {
            continue;
        }
        $totalAmount += floatval($b['total_amount'] ?? 0);
        $totalPaid += floatval($b['paid_amount'] ?? 0);
        $balanceVal = floatval($b['balance'] ?? 0);
        $totalPending += $balanceVal;
        $dueVal = $b['due_date'] ?? '';
        if ($balanceVal > 0 && $statusLower !== 'paid' && !empty($dueVal)) {
            if ($nearestDueDate === null || $dueVal < $nearestDueDate) {
                $nearestDueDate = $dueVal;
                $nearestDueBalance = $balanceVal;
            }
        }
    }
    
} catch (Exception $e) {
    $patient = [];
    $billings = [];
    $payments = [];
    $totalPaid = 0;
    $totalPending = 0;
    $totalAmount = 0;
    $nearestDueDate = null;
    $nearestDueBalance = 0;
}

$pageTitle = 'Billing';
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
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 24px; }
        .stat-card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-bottom: 16px; }
        .stat-icon.paid { background: #d1fae5; color: #065f46; }
        .stat-icon.pending { background: #fef3c7; color: #92400e; }
        .stat-icon.total { background: #dbeafe; color: #1e40af; }
        .stat-value { font-size: 28px; font-weight: 700; color: #1a1a2e; margin-bottom: 4px; }
        .stat-label { font-size: 14px; color: #6b7280; }
        .section { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 24px; }
        .section-title { font-size: 18px; font-weight: 700; color: #1a1a2e; margin-bottom: 20px; }
        .invoice-table { width: 100%; border-collapse: collapse; }
        .invoice-table th { text-align: left; padding: 12px 16px; background: #f9fafb; font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; }
        .invoice-table td { padding: 16px; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #374151; }
        .invoice-table tr:last-child td { border-bottom: none; }
        .invoice-id { font-weight: 600; color: #1a1a2e; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-failed { background: #fee2e2; color: #991b1b; }
        .empty-state { text-align: center; padding: 48px 24px; color: #6b7280; }
        .empty-state i { font-size: 48px; margin-bottom: 16px; color: #d1d5db; }
        .empty-state h3 { font-size: 18px; color: #374151; margin-bottom: 8px; }
        .amount { font-weight: 600; color: #1a1a2e; }
        .due-banner { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 20px; padding: 14px 16px; border-radius: 10px; background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; font-size: 14px; line-height: 1.5; }
        .due-banner i { margin-top: 2px; }
        .due-date-overdue { color: #dc2626; font-weight: 600; }
        .due-date-today { color: #d97706; font-weight: 600; }
        .due-date-upcoming { color: #374151; font-weight: 500; }
        .due-date-empty { color: #9ca3af; }
        @media (max-width: 768px) {
            .sidebar { width: 60px; padding: 24px 0; }
            .sidebar-brand span, .menu-item span { display: none; }
            .sidebar-brand { justify-content: center; padding: 0; }
            .menu-item { justify-content: center; padding: 14px; }
            .main-wrapper { margin-left: 60px; }
            .dashboard-header { padding: 12px 16px; }
            .user-details { display: none; }
            .dashboard-main { padding: 16px; }
            .stats-grid { grid-template-columns: 1fr; }
            .invoice-table { display: block; overflow-x: auto; }
        }
    </style>
    <link rel="stylesheet" href="../../assets/css/patient_portal.css">
    <?php require __DIR__ . '/../../includes/app_notice_modal_script.php'; ?>
</head>
<body>
    <aside class="sidebar" id="patientSidebar">
        <div class="sidebar-brand">
            <img src="../../assets/images/Logo.png" alt="RF Dental Clinic">
            <span>RF Dental Clinic</span>
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
            <a href="billing.php" class="menu-item active">
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
                <h1 style="font-size: 20px; font-weight: 700; color: #1a1a2e; margin: 0;">Billing</h1>
                <p style="font-size: 13px; color: #6b7280; margin: 4px 0 0 0;">View your payment history and invoices</p>
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
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon paid"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value">₱<?php echo number_format($totalPaid, 2); ?></div>
                    <div class="stat-label">Total Paid</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
                    <div class="stat-value">₱<?php echo number_format($totalPending, 2); ?></div>
                    <div class="stat-label">Outstanding Balance</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon total"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div class="stat-value"><?php echo count($billings); ?></div>
                    <div class="stat-label">Total Transactions</div>
                </div>
            </div>

            <div class="section">
                <h2 class="section-title">Billing History</h2>
                <?php if (!empty($nearestDueDate) && $nearestDueBalance > 0): ?>
                    <div class="due-banner">
                        <i class="fas fa-calendar-alt"></i>
                        <span>You have ₱<?php echo number_format($nearestDueBalance, 2); ?> due by <?php echo date('M j, Y', strtotime($nearestDueDate)); ?>. Please settle your balance at the clinic.</span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($billings)): ?>
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Due Date</th>
                                <th>Treatment</th>
                                <th>Amount</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($billings as $b): ?>
                                <?php
                                    $statusClass = strtolower($b['payment_status'] ?? 'unpaid');
                                    if ($statusClass === 'paid') $statusClass = 'completed';
                                    elseif ($statusClass === 'partial') $statusClass = 'pending';
                                    else $statusClass = 'failed';
                                    $treatmentDisplay = $b['treatment_type'] ?: ($b['notes'] ?: 'General Checkup');
                                    $hasDiscount = !empty($b['discount_amount']) && floatval($b['discount_amount']) > 0;
                                    $subtotal = floatval($b['subtotal_amount'] ?? 0);
                                    $totalAmt = floatval($b['total_amount'] ?? 0);
                                    $rowPaid = strtolower((string)($b['payment_status'] ?? '')) === 'paid' || floatval($b['balance'] ?? 0) <= 0;
                                    $dueRaw = $b['due_date'] ?? '';
                                    $dueHtml = '<span class="due-date-empty">—</span>';
                                    if (!$rowPaid && !empty($dueRaw)) {
                                        $dueTs = strtotime($dueRaw);
                                        $todayTs = strtotime(date('Y-m-d'));
                                        $dueLabel = date('M j, Y', $dueTs);
                                        if ($dueTs < $todayTs) {
                                            $dueHtml = '<span class="due-date-overdue">' . htmlspecialchars($dueLabel) . ' (Overdue)</span>';
                                        } elseif ($dueTs === $todayTs) {
                                            $dueHtml = '<span class="due-date-today">' . htmlspecialchars($dueLabel) . ' (Due today)</span>';
                                        } else {
                                            $dueHtml = '<span class="due-date-upcoming">' . htmlspecialchars($dueLabel) . '</span>';
                                        }
                                    }
                                ?>
                                <tr>
                                    <td class="invoice-id"><?php echo htmlspecialchars($b['invoice_number']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($b['billing_date'])); ?></td>
                                    <td><?php echo $dueHtml; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($treatmentDisplay); ?>
                                        <?php if ($hasDiscount): ?>
                                            <br><small style="color:#6b7280;text-decoration:line-through;">₱<?php echo number_format($subtotal, 2); ?></small>
                                            <small style="color:#dc2626;margin-left:4px;">(<?php echo htmlspecialchars(ucfirst($b['discount_type'])); ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="amount">₱<?php echo number_format($totalAmt, 2); ?></td>
                                    <td class="amount" style="color:#065f46;">₱<?php echo number_format(floatval($b['paid_amount']), 2); ?></td>
                                    <td class="amount" style="color:<?php echo $b['balance'] > 0 ? '#dc2626' : '#065f46'; ?>;">₱<?php echo number_format(floatval($b['balance']), 2); ?></td>
                                    <td><span class="status-badge status-<?php echo $statusClass; ?>"><?php echo htmlspecialchars(ucfirst($b['payment_status'] ?? 'Unpaid')); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <h3>No Billing History</h3>
                        <p>Your billing history will appear here after your visits.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="section">
                <h2 class="section-title">Payment Transactions</h2>
                <?php if (!empty($payments)): ?>
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td class="invoice-id"><?php echo htmlspecialchars($payment['invoice_number'] ?? ''); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                    <td class="amount" style="color:#065f46;">₱<?php echo number_format(floatval($payment['amount']), 2); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($payment['payment_method'] ?? 'Cash')); ?></td>
                                    <td><?php echo htmlspecialchars($payment['reference_number'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-money-bill"></i>
                        <h3>No Payments Yet</h3>
                        <p>Your payment records will appear here once payments are processed.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
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
