<?php
/**
 * Billing - Admin page for viewing billing records
 */

$pageTitle = 'Billing';

require_once __DIR__ . '/../../config/database.php';

$billingRecords = [];
$totalCollected = 0;
$pendingPayments = 0;
$totalTransactions = 0;
$overdueCount = 0;
$dbError = '';

try {
    $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $dateFilter = isset($_GET['date']) ? trim($_GET['date']) : 'all';

    $whereClause = "WHERE LOWER(COALESCE(b.payment_status, 'unpaid')) != 'cancelled'";
    $params = [];

    if ($searchQuery !== '') {
        $whereClause .= " AND (
            CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,'')) LIKE :search
            OR b.id LIKE :searchId
        )";
        $params[':search'] = '%' . $searchQuery . '%';
        $params[':searchId'] = '%' . $searchQuery . '%';
    }

    if ($statusFilter === 'paid') {
        $whereClause .= " AND b.payment_status = 'Paid'";
    } elseif ($statusFilter === 'unpaid') {
        $whereClause .= " AND b.payment_status IN ('Unpaid', 'Partial', 'Overdue')";
    }

    switch ($dateFilter) {
        case 'today':
            $whereClause .= " AND DATE(b.billing_date) = CURDATE()";
            break;
        case 'week':
            $whereClause .= " AND b.billing_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $whereClause .= " AND b.billing_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
    }

    $statsSql = "
        SELECT
            COALESCE(SUM(CASE WHEN b.payment_status IN ('Paid', 'Partial') THEN b.paid_amount ELSE 0 END), 0) AS total_collected,
            COALESCE(SUM(CASE WHEN b.payment_status IN ('Partial', 'Unpaid', 'Overdue') THEN b.balance ELSE 0 END), 0) AS pending_payments,
            COUNT(*) AS total_transactions,
            SUM(CASE
                WHEN b.payment_status = 'Overdue' THEN 1
                WHEN b.payment_status = 'Unpaid' AND b.due_date IS NOT NULL AND b.due_date < CURDATE() AND COALESCE(b.balance, 0) > 0 THEN 1
                ELSE 0
            END) AS overdue_count
        FROM billing b
        LEFT JOIN patients p ON p.id = b.patient_id
        $whereClause
    ";
    $statsStmt = $pdo->prepare($statsSql);
    foreach ($params as $key => $value) {
        $statsStmt->bindValue($key, $value);
    }
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $totalCollected = (float)($stats['total_collected'] ?? 0);
    $pendingPayments = (float)($stats['pending_payments'] ?? 0);
    $totalTransactions = (int)($stats['total_transactions'] ?? 0);
    $overdueCount = (int)($stats['overdue_count'] ?? 0);

    // Senior/PWD discounts this calendar month (for BIR purchase book reference)
    try {
        $discStmt = $pdo->query("SELECT
                COUNT(*) AS discount_count,
                COALESCE(SUM(discount_amount), 0) AS discount_total,
                COALESCE(SUM(CASE WHEN discount_type = 'senior' THEN discount_amount ELSE 0 END), 0) AS senior_total,
                COALESCE(SUM(CASE WHEN discount_type = 'pwd' THEN discount_amount ELSE 0 END), 0) AS pwd_total
            FROM billing
            WHERE discount_amount > 0 AND YEAR(billing_date) = YEAR(CURDATE()) AND MONTH(billing_date) = MONTH(CURDATE())");
        $discStats = $discStmt->fetch(PDO::FETCH_ASSOC) ?: ['discount_count' => 0, 'discount_total' => 0, 'senior_total' => 0, 'pwd_total' => 0];
    } catch (Exception $e) {
        $discStats = ['discount_count' => 0, 'discount_total' => 0, 'senior_total' => 0, 'pwd_total' => 0];
    }

    $sql = "
        SELECT
            b.id AS billing_id,
            b.patient_id,
            b.total_amount,
            b.paid_amount,
            b.balance,
            b.payment_status,
            b.billing_date,
            b.due_date,
            b.subtotal_amount,
            b.discount_type,
            b.discount_amount,
            p.first_name,
            p.last_name,
            svc.name AS service_name,
            COALESCE(q.treatment_type, svc.name, 'General Service') AS treatment_type
        FROM billing b
        LEFT JOIN patients p ON p.id = b.patient_id
        LEFT JOIN appointments a ON a.id = b.appointment_id
        LEFT JOIN services svc ON a.service_id = svc.id
        LEFT JOIN (
            SELECT patient_id, DATE(created_at) AS created_date,
                   MIN(id) AS id,
                   MIN(treatment_type) AS treatment_type
            FROM queue
            GROUP BY patient_id, DATE(created_at)
        ) q ON q.patient_id = b.patient_id AND q.created_date = DATE(b.billing_date)
        $whereClause
        ORDER BY b.billing_date DESC
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $billingRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $billingRecords = [];
    $dbError = $e->getMessage();
}

require_once __DIR__ . '/../../includes/admin_layout_start.php';
?>
            <div class="content-main">
                <div class="page-header">
                    <h2>Billing Overview</h2>
                </div>

                <?php if ($dbError): ?>
                <div style="padding: 16px; background: #fee2e2; color: #dc2626; border: 1px solid #f87171; border-radius: 8px; margin-bottom: 16px;">
                    <strong>Database Error:</strong> <?php echo htmlspecialchars($dbError); ?>
                </div>
                <?php endif; ?>

                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon green">&#128176;</div>
                        <div class="summary-info">
                            <h3>&#8369;<?php echo number_format($totalCollected, 2); ?></h3>
                            <p>Total Collected</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon yellow">&#9203;</div>
                        <div class="summary-info">
                            <h3>&#8369;<?php echo number_format($pendingPayments, 2); ?></h3>
                            <p>Pending Payments</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon blue">&#128203;</div>
                        <div class="summary-info">
                            <h3><?php echo number_format($totalTransactions); ?></h3>
                            <p>Total Transactions</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon red">&#10060;</div>
                        <div class="summary-info">
                            <h3><?php echo number_format($overdueCount); ?></h3>
                            <p>Overdue</p>
                        </div>
                    </div>
                    <div class="summary-card" title="Senior: &#8369;<?php echo number_format((float)$discStats['senior_total'], 2); ?> · PWD: &#8369;<?php echo number_format((float)$discStats['pwd_total'], 2); ?>">
                        <div class="summary-icon green">&#127875;</div>
                        <div class="summary-info">
                            <h3>&#8369;<?php echo number_format((float)$discStats['discount_total'], 2); ?></h3>
                            <p>SC/PWD Discounts (This Month &middot; <?php echo (int)$discStats['discount_count']; ?>)</p>
                        </div>
                    </div>
                </div>

                <form method="GET" class="search-filters">
                    <input type="text" class="search-input" placeholder="Search by patient name or invoice..." name="search" value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
                    <select class="filter-select" name="status">
                        <option value="">All Status</option>
                        <option value="paid" <?php echo ($statusFilter ?? '') === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="unpaid" <?php echo ($statusFilter ?? '') === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                    </select>
                    <select class="filter-select" name="date">
                        <option value="all" <?php echo ($dateFilter ?? 'all') === 'all' ? 'selected' : ''; ?>>All Time</option>
                        <option value="today" <?php echo ($dateFilter ?? '') === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo ($dateFilter ?? '') === 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php echo ($dateFilter ?? '') === 'month' ? 'selected' : ''; ?>>This Month</option>
                    </select>
                    <button type="submit" class="btn-primary" style="padding: 8px 16px;">Search</button>
                </form>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Patient</th>
                                <th>Services</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($billingRecords) == 0): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #6b7280;">
                                    No billing records found.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($billingRecords as $record):
                                $invoiceNum = 'INV-' . str_pad($record['billing_id'], 3, '0', STR_PAD_LEFT);
                                $fullName = trim(($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? ''));
                                $services = $record['service_name'] ?? $record['treatment_type'] ?? 'General Service';
                                $status = strtolower($record['payment_status'] ?? 'unpaid');
                                $isOverdue = !empty($record['due_date']) && $record['due_date'] < date('Y-m-d') && ($record['balance'] ?? 0) > 0;
                                $displayStatus = $isOverdue ? 'overdue' : $status;
                            ?>
                            <tr>
                                <td><?php echo $invoiceNum; ?></td>
                                <td><?php echo htmlspecialchars($fullName ?: 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($services); ?></td>
                                <td>
                                    &#8369;<?php echo number_format($record['total_amount'] ?? 0, 2); ?>
                                    <?php if (($record['discount_amount'] ?? 0) > 0): ?>
                                        <div style="margin-top:3px;">
                                            <span style="text-decoration:line-through;color:#94a3b8;font-size:11px;">&#8369;<?php echo number_format((float)($record['subtotal_amount'] ?: (($record['total_amount'] ?? 0) + $record['discount_amount'])), 2); ?></span>
                                            <span style="display:inline-block;background:#ecfdf5;color:#047857;font-size:10px;font-weight:700;padding:1px 7px;border-radius:9999px;border:1px solid #a7f3d0;margin-left:4px;">
                                                <?php echo strtoupper($record['discount_type']); ?> 20% &minus;&#8369;<?php echo number_format((float)$record['discount_amount'], 2); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($record['billing_date'])); ?></td>
                                <td><?php echo !empty($record['due_date']) ? date('Y-m-d', strtotime($record['due_date'])) : 'N/A'; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $displayStatus; ?>">
                                        <?php echo $isOverdue ? 'Overdue' : ucfirst($status); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <p style="text-align: right; color: #6b7280; font-size: 0.875rem; margin-top: 12px;">
                    Showing <?php echo count($billingRecords); ?> record(s)
                </p>
            </div>

<?php
require_once __DIR__ . '/../../includes/admin_layout_end.php';
?>
