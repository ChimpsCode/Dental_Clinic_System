<?php
/**
 * Payment - Admin page for viewing and managing payments
 * Features: Payment Details modal (Overview / History / Record Payment), Apply Discount, Print Invoice, Edit Amount
 */

$pageTitle = 'Payment';

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/discount_helper.php';
ensureDiscountSchema($pdo);
require_once __DIR__ . '/../../includes/payment_approval_helper.php';
ensurePaymentApprovalSchema($pdo);

$isSuggest = isset($_GET['suggest']);
if ($isSuggest) {
    require_once __DIR__ . '/../../includes/session_bootstrap.php';
    header('Content-Type: application/json; charset=utf-8');
    if (!empty($GLOBALS['APP_SESSION_EXPIRED']) || !isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['suggestions' => []]);
        exit;
    }
}

$billingRecords = [];
$totalCollected = 0;
$pendingPayments = 0;
$totalTransactions = 0;
$overdueCount = 0;
$showingStart = 0;
$showingEnd = 0;
$filteredTotal = 0;
$currentPage = 1;
$totalPages = 1;
$perPage = 10;
$dbError = '';
$searchQuery = '';
$statusFilter = '';
$dateFilter = 'all';

try {
    $searchQuery = $isSuggest
        ? trim((string)($_GET['q'] ?? ''))
        : (isset($_GET['search']) ? trim($_GET['search']) : '');
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $dateFilter = isset($_GET['date']) ? trim($_GET['date']) : 'all';
    $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

    $whereClause = "WHERE 1=1";
    $params = [];

    $queueJoin = "LEFT JOIN queue q ON q.id = (
            SELECT q1.id FROM queue q1
            WHERE q1.patient_id = b.patient_id
              AND ((b.appointment_id IS NOT NULL AND q1.appointment_id = b.appointment_id)
                OR (b.appointment_id IS NULL AND DATE(q1.created_at) = DATE(b.billing_date)))
            ORDER BY q1.id DESC LIMIT 1
        )";

    $overallUnpaidExists = "EXISTS (
            SELECT 1 FROM billing x
            WHERE x.patient_id = b.patient_id
              AND LOWER(COALESCE(x.payment_status, '')) NOT IN ('paid', 'cancelled', 'partial')
              AND COALESCE(x.balance, 0) > 0
        )";
    $overallPartialExists = "EXISTS (
            SELECT 1 FROM billing x
            WHERE x.patient_id = b.patient_id
              AND LOWER(COALESCE(x.payment_status, '')) = 'partial'
              AND COALESCE(x.balance, 0) > 0
        )";
    $overallPaidExists = "EXISTS (
            SELECT 1 FROM billing x
            WHERE x.patient_id = b.patient_id
              AND LOWER(COALESCE(x.payment_status, '')) = 'paid'
        )";
    $overallStatusExpr = "CASE
            WHEN LOWER(COALESCE(b.payment_status, '')) = 'cancelled' THEN 'voided'
            WHEN LOWER(COALESCE(b.payment_status, '')) IN ('unpaid', 'pending')
                 AND COALESCE(b.total_amount, 0) = 0 AND COALESCE(b.paid_amount, 0) = 0 THEN 'unpaid'
            WHEN $overallUnpaidExists THEN 'unpaid'
            WHEN $overallPartialExists THEN 'partial'
            WHEN $overallPaidExists THEN 'paid'
            ELSE 'unpaid'
        END";
    $overallBalanceExpr = "(SELECT COALESCE(SUM(CASE
            WHEN LOWER(COALESCE(x.payment_status, '')) = 'cancelled' THEN 0
            ELSE COALESCE(x.balance, 0)
        END), 0) FROM billing x WHERE x.patient_id = b.patient_id)";

    if ($searchQuery !== '') {
        $whereClause .= " AND (
            CONCAT_WS(' ',
                NULLIF(TRIM(p.first_name), ''),
                NULLIF(TRIM(p.middle_name), ''),
                NULLIF(TRIM(p.last_name), ''),
                NULLIF(TRIM(p.suffix), '')
            ) LIKE :search
            OR b.id LIKE :searchId
        )";
        $params[':search'] = '%' . $searchQuery . '%';
        $params[':searchId'] = '%' . $searchQuery . '%';
    }

    if ($statusFilter === 'paid') {
        $whereClause .= " AND ($overallStatusExpr) = 'paid'";
    } elseif ($statusFilter === 'partial') {
        $whereClause .= " AND ($overallStatusExpr) = 'partial'";
    } elseif ($statusFilter === 'unpaid') {
        $whereClause .= " AND ($overallStatusExpr) IN ('unpaid', 'partial')";
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

    $latestRowClause = " AND b.id = (
            SELECT b2.id FROM billing b2
            WHERE b2.patient_id = b.patient_id
            ORDER BY COALESCE(b2.updated_at, b2.billing_date) DESC, b2.id DESC
            LIMIT 1
        )";
    $listWhereClause = $whereClause . $latestRowClause;

    if ($isSuggest) {
        $suggestions = [];
        if ($searchQuery !== '') {
            $suggestSql = "
                SELECT
                    b.id AS billing_id,
                    ($overallStatusExpr) AS payment_status,
                    p.first_name,
                    p.middle_name,
                    p.last_name,
                    p.suffix
                FROM billing b
                LEFT JOIN patients p ON p.id = b.patient_id
                $queueJoin
                $listWhereClause
                ORDER BY COALESCE(b.updated_at, b.billing_date) DESC, b.id DESC
                LIMIT 8
            ";
            $suggestStmt = $pdo->prepare($suggestSql);
            foreach ($params as $key => $value) {
                $suggestStmt->bindValue($key, $value);
            }
            $suggestStmt->execute();
            foreach ($suggestStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $fullName = trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? '') . ' ' . ($row['suffix'] ?? ''));
                $fullName = preg_replace('/\s+/', ' ', $fullName);
                $billingId = (int)($row['billing_id'] ?? 0);
                $suggestions[] = [
                    'name' => $fullName !== '' ? $fullName : 'Unknown Patient',
                    'invoice' => 'INV-' . str_pad((string)$billingId, 3, '0', STR_PAD_LEFT),
                    'billing_id' => $billingId,
                    'status' => $row['payment_status'] ?? '',
                    'search' => $fullName !== '' ? $fullName : (string)$billingId,
                ];
            }
        }
        echo json_encode(['suggestions' => $suggestions]);
        exit;
    }

    $statsSql = "
        SELECT
            COALESCE(SUM(CASE WHEN b.payment_status IN ('Paid', 'Partial') THEN b.paid_amount ELSE 0 END), 0) AS total_collected,
            COALESCE(SUM(CASE
                WHEN LOWER(COALESCE(b.payment_status, '')) = 'cancelled' THEN 0
                WHEN LOWER(COALESCE(q.status, '')) IN ('cancelled', 'canceled') THEN 0
                WHEN b.payment_status IN ('Partial', 'Unpaid', 'Overdue') THEN b.balance
                ELSE 0
            END), 0) AS pending_payments,
            COUNT(*) AS total_transactions,
            SUM(CASE
                WHEN LOWER(COALESCE(b.payment_status, '')) = 'cancelled' THEN 0
                WHEN LOWER(COALESCE(q.status, '')) IN ('cancelled', 'canceled') THEN 0
                WHEN b.payment_status = 'Paid' THEN 0
                WHEN b.payment_status = 'Overdue' THEN 1
                WHEN b.payment_status = 'Unpaid' AND b.due_date IS NOT NULL AND b.due_date < CURDATE() AND COALESCE(b.balance, 0) > 0 THEN 1
                ELSE 0
            END) AS overdue_count
        FROM billing b
        LEFT JOIN patients p ON p.id = b.patient_id
        $queueJoin
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

    $countSql = "SELECT COUNT(DISTINCT b.patient_id) as total FROM billing b LEFT JOIN patients p ON p.id = b.patient_id $queueJoin $listWhereClause";
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $filteredTotal = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($filteredTotal / $perPage);
    if ($currentPage > $totalPages) $currentPage = 1;
    $offset = ($currentPage - 1) * $perPage;

    $sql = "
        SELECT
            b.id AS billing_id,
            b.patient_id,
            b.treatment_id,
            b.appointment_id,
            b.total_amount,
            b.paid_amount,
            $overallBalanceExpr AS balance,
            ($overallStatusExpr) AS payment_status,
            b.billing_date,
            b.due_date,
            b.subtotal_amount,
            b.discount_type,
            b.discount_rate,
            b.discount_amount,
            b.discount_reason,
            p.first_name,
            p.middle_name,
            p.last_name,
            p.suffix,
            p.phone,
            svc.name AS service_name,
            COALESCE(q.treatment_type, svc.name, 'General Service') AS treatment_type,
            q.id AS queue_id,
            q.status AS queue_status
        FROM billing b
        LEFT JOIN patients p ON p.id = b.patient_id
        LEFT JOIN appointments a ON a.id = b.appointment_id
        LEFT JOIN services svc ON a.service_id = svc.id
        $queueJoin
        $listWhereClause
        ORDER BY COALESCE(b.updated_at, b.billing_date) DESC, b.id DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $billingRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $showingStart = $filteredTotal ? $offset + 1 : 0;
    $showingEnd = min($offset + $perPage, $filteredTotal);
} catch (Exception $e) {
    if (!empty($isSuggest)) {
        echo json_encode(['suggestions' => []]);
        exit;
    }
    $billingRecords = [];
    $dbError = $e->getMessage();
}

require_once __DIR__ . '/../../includes/admin_layout_start.php';
?>
            <div class="content-main">

                <!-- Stats Cards -->
                <div class="summary-cards">
                    <div class="summary-card card-collected">
                        <div class="summary-icon">
                            <img src="../../assets/icons/peso.png" alt="Total Collected">
                        </div>
                        <div class="summary-info">
                            <h3>&#8369;<?php echo number_format($totalCollected, 2); ?></h3>
                            <p>Total Collected</p>
                        </div>
                    </div>
                    <div class="summary-card card-unpaid">
                        <div class="summary-icon">
                            <img src="../../assets/icons/unpaid.png" alt="Pending Payments">
                        </div>
                        <div class="summary-info">
                            <h3>&#8369;<?php echo number_format($pendingPayments, 2); ?></h3>
                            <p>Pending Payments</p>
                        </div>
                    </div>
                    <div class="summary-card card-transactions">
                        <div class="summary-icon">
                            <img src="../../assets/icons/transaction.png" alt="Total Transactions">
                        </div>
                        <div class="summary-info">
                            <h3><?php echo number_format($totalTransactions); ?></h3>
                            <p>Total Transactions</p>
                        </div>
                    </div>
                    <div class="summary-card card-overdue">
                        <div class="summary-icon">
                            <img src="../../assets/icons/overdue.png" alt="Overdue">
                        </div>
                        <div class="summary-info">
                            <h3><?php echo number_format($overdueCount); ?></h3>
                            <p>Overdue</p>
                        </div>
                    </div>
                </div>

                <?php if ($dbError): ?>
                <div style="padding: 16px; background: #fee2e2; color: #dc2626; border: 1px solid #f87171; border-radius: 8px; margin-bottom: 16px; font-family: sans-serif;">
                    <strong>Database Error:</strong> <?php echo htmlspecialchars($dbError); ?>
                </div>
                <?php endif; ?>

                <!-- Search and Filter -->
                <form method="GET" class="search-filters" id="paymentSearchForm">
                    <div class="payment-search-wrap">
                        <input type="text" class="search-input" id="paymentSearch" placeholder="Search by patient name or invoice..." name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" autocomplete="off">
                        <div id="paymentSearchSuggestions" class="payment-search-suggestions" hidden></div>
                    </div>
                    <select class="filter-select" name="status">
                        <option value="">All Status</option>
                        <option value="paid" <?php echo $statusFilter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="partial" <?php echo $statusFilter === 'partial' ? 'selected' : ''; ?>>Partial</option>
                        <option value="unpaid" <?php echo $statusFilter === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                    </select>
                    <select class="filter-select" name="date">
                        <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>All Time</option>
                        <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php echo $dateFilter === 'month' ? 'selected' : ''; ?>>This Month</option>
                    </select>
                    <button type="submit" class="btn-primary" style="padding: 8px 16px;">Search</button>
                </form>

                <div class="page-header">
                    <h2>Payment Overview</h2>
                </div>

                <!-- Payment Table -->
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Patient</th>
                                <th>Services</th>
                                <th>Amount</th>
                                <th>Balance</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="paymentTableBody">
                            <?php if (count($billingRecords) == 0): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #6b7280;">
                                    No payment records found.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($billingRecords as $record):
                                $invoiceNum = 'INV-' . str_pad($record['billing_id'], 3, '0', STR_PAD_LEFT);
                                $fullName = trim(($record['first_name'] ?? '') . ' ' . ($record['middle_name'] ?? '') . ' ' . ($record['last_name'] ?? '') . ' ' . ($record['suffix'] ?? ''));
                                $fullName = preg_replace('/\s+/', ' ', $fullName);
                                $services = $record['service_name'] ?? $record['treatment_type'] ?? 'General Service';
                                $statusRaw = strtolower((string)($record['payment_status'] ?? 'unpaid'));
                                $isPaid = ($statusRaw === 'paid');
                                $isPartial = ($statusRaw === 'partial');
                                $isVoided = ($statusRaw === 'voided' || $statusRaw === 'cancelled');
                                if ($isPaid) {
                                    $status = 'paid';
                                } elseif ($isVoided) {
                                    $status = 'voided';
                                } elseif ($isPartial) {
                                    $status = 'partial';
                                } else {
                                    $status = 'unpaid';
                                }
                                $isOverdue = !$isVoided && !$isPaid && !empty($record['due_date']) && $record['due_date'] < date('Y-m-d') && (float)($record['balance'] ?? 0) > 0;
                                $displayStatus = $isVoided ? 'voided' : ($isOverdue ? 'overdue' : $status);
                                $paidAmt = (float)($record['paid_amount'] ?? 0);
                                $balanceAmt = $isVoided ? 0 : (float)($record['balance'] ?? 0);
                            ?>
                            <tr data-invoice-id="<?php echo $invoiceNum; ?>" data-status="<?php echo $status; ?>" data-name="<?php echo strtolower($fullName); ?>">
                                <td><?php echo $invoiceNum; ?></td>
                                <td>
                                    <?php if ($record['patient_id']): ?>
                                    <span style="font-weight: 500;"><?php echo htmlspecialchars($fullName); ?></span>
                                    <?php else: ?>
                                    <span style="color: #6b7280;">Unknown Patient</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($services); ?></td>
                                <td>
                                    &#8369;<?php echo number_format($record['total_amount'], 2); ?>
                                    <?php if (($record['discount_amount'] ?? 0) > 0): ?>
                                        <?php
                                            $dType = $record['discount_type'] ?? 'none';
                                            if ($dType === 'custom') {
                                                $dRate = (float)($record['discount_rate'] ?? 0);
                                                $dLabel = 'DISCOUNT' . ($dRate > 0 ? ' ' . rtrim(rtrim(number_format($dRate, 2), '0'), '.') . '%' : '');
                                            } else {
                                                $dLabel = strtoupper($dType) . ' 20%';
                                            }
                                        ?>
                                        <div style="margin-top:3px;">
                                            <span style="text-decoration:line-through;color:#94a3b8;font-size:11px;">&#8369;<?php echo number_format((float)($record['subtotal_amount'] ?: ($record['total_amount'] + $record['discount_amount'])), 2); ?></span>
                                            <span title="<?php echo htmlspecialchars($record['discount_reason'] ?? ''); ?>" style="display:inline-block;background:#ecfdf5;color:#047857;font-size:10px;font-weight:700;padding:1px 7px;border-radius:9999px;border:1px solid #a7f3d0;margin-left:4px;">
                                                <?php echo $dLabel; ?> &minus;&#8369;<?php echo number_format((float)$record['discount_amount'], 2); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>&#8369;<?php echo number_format($balanceAmt, 2); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($record['billing_date'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $displayStatus; ?>">
                                        <?php if ($isVoided): ?>
                                        Voided
                                        <?php elseif ($isOverdue): ?>
                                        Overdue
                                        <?php else: ?>
                                        <?php echo ucfirst($status); ?>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="queue-kebab-btn"
                                        data-invoice-id="<?php echo $invoiceNum; ?>"
                                        data-billing-id="<?php echo $record['billing_id']; ?>"
                                        data-patient-id="<?php echo $record['patient_id']; ?>"
                                        data-queue-id="<?php echo (int)($record['queue_id'] ?? 0); ?>"
                                        data-status="<?php echo $status; ?>"
                                        data-amount="<?php echo $record['total_amount']; ?>"
                                        data-paid-amount="<?php echo $paidAmt; ?>"
                                        data-balance="<?php echo $balanceAmt; ?>"
                                        data-treatment="<?php echo htmlspecialchars($services); ?>"
                                        data-is-paid="<?php echo $isPaid ? '1' : '0'; ?>"
                                        data-is-voided="<?php echo $isVoided ? '1' : '0'; ?>"
                                        data-discount-type="<?php echo htmlspecialchars($record['discount_type'] ?? 'none'); ?>"
                                        data-subtotal="<?php echo (float)($record['subtotal_amount'] ?: $record['total_amount']); ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                            <circle cx="12" cy="5" r="2"/>
                                            <circle cx="12" cy="12" r="2"/>
                                            <circle cx="12" cy="19" r="2"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($filteredTotal > 0): ?>
                <div class="pagination">
                    <span class="pagination-info">Showing <?php echo $showingStart; ?>-<?php echo $showingEnd; ?> of <?php echo $filteredTotal; ?> transactions</span>
                    <div class="pagination-buttons">
                        <?php
                        $queryString = $_GET;
                        unset($queryString['page'], $queryString['__pjax'], $queryString['_pjax']);
                        $baseUrl = '?' . http_build_query($queryString);
                        if (!empty($queryString)) $baseUrl .= '&';
                        ?>
                        <a href="<?php echo $baseUrl; ?>page=<?php echo $currentPage - 1; ?>" class="pagination-btn <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>" <?php echo $currentPage <= 1 ? 'onclick="return false;"' : ''; ?>>Previous</a>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == 1 || $i == $totalPages || ($i >= $currentPage - 1 && $i <= $currentPage + 1)): ?>
                                <a href="<?php echo $baseUrl; ?>page=<?php echo $i; ?>" class="pagination-btn <?php echo $i == $currentPage ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php elseif ($i == $currentPage - 2 || $i == $currentPage + 2): ?>
                                <span class="pagination-btn disabled">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <a href="<?php echo $baseUrl; ?>page=<?php echo $currentPage + 1; ?>" class="pagination-btn <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>" <?php echo $currentPage >= $totalPages ? 'onclick="return false;"' : ''; ?>>Next</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Payment Details Modal (Overview / History / Record Payment) -->
            <div id="paymentModal" class="modal-overlay" style="display: none;">
                <div class="modal pay-detail-modal">
                    <div class="modal-header">
                        <h2>Payment Details</h2>
                        <button type="button" class="modal-close" onclick="closePaymentModal()">&times;</button>
                    </div>
                    <div class="pay-detail-tabs">
                        <button type="button" class="pay-detail-tab active" data-tab="overview" onclick="switchPayDetailTab('overview')">Overview</button>
                        <button type="button" class="pay-detail-tab" data-tab="history" onclick="switchPayDetailTab('history')">History</button>
                        <button type="button" class="pay-detail-tab" data-tab="record" onclick="switchPayDetailTab('record')">Record Payment</button>
                </div>
                    <div class="modal-body pay-detail-body">
                        <input type="hidden" id="payDetailQueueId">
                        <input type="hidden" id="payDetailPatientId">
                        <input type="hidden" id="payDetailBillingId">
                        <input type="hidden" id="payDetailAmount">
                        <input type="hidden" id="payDetailPaidAmount">
                        <input type="hidden" id="payDetailBalance">
                        <input type="hidden" id="payDetailTreatment">
                        <input type="hidden" id="payDetailIsPaid">
                        <input type="hidden" id="payDetailIsVoided">
                        <input type="hidden" id="setDueDateBillingId">
                        <input type="hidden" id="confirmPaymentQueueId">
                        <input type="hidden" id="confirmPaymentPatientId">
                        <input type="hidden" id="confirmPaymentBillingId">
                        <input type="hidden" id="confirmPaymentAmountValue">
                        <input type="hidden" id="confirmPaymentTreatmentValue">
                        <input type="hidden" id="recordPaymentQueueId">
                        <input type="hidden" id="recordPaymentPatientId">
                        <input type="hidden" id="recordPaymentBillingId">
                        <input type="hidden" id="editPaymentBillingId">

                        <div id="payTabOverview" class="pay-detail-tab-panel">
                            <div id="paymentModalContent"></div>
                            <div id="payEditAmountSection" class="pay-detail-section" style="display:none;">
                                <h3>Edit Amount</h3>
                                <p class="pay-detail-hint">Change the invoice total. Provide a reason for the audit log.</p>
                                <div style="margin-bottom: 12px;">
                                    <label class="pay-detail-label" for="editPaymentAmount">New Amount (&#8369;)</label>
                                    <input type="number" id="editPaymentAmount" class="pay-detail-input" min="0" step="0.01">
                    </div>
                                <div style="margin-bottom: 12px;">
                                    <label class="pay-detail-label" for="editPaymentReason">Reason for Change</label>
                                    <input type="text" id="editPaymentReason" class="pay-detail-input" placeholder="e.g., Discount applied, Additional procedure">
                                </div>
                                <div class="pay-detail-due-row" style="justify-content:flex-end;">
                                    <button type="button" class="btn-primary" onclick="savePaymentEdit()">Save Changes</button>
                    </div>
                </div>
            </div>

                        <div id="payTabHistory" class="pay-detail-tab-panel" style="display:none;">
                            <div id="paidServicesWrap"></div>
                            <table class="pay-history-table" id="payHistoryPaymentsTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Reference</th>
                                        <th class="payment-history-action">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="paymentHistoryBody">
                                    <tr><td colspan="5" style="text-align:center;padding:2rem;color:#9ca3af;">Loading...</td></tr>
                                </tbody>
                            </table>
                            <div id="paymentHistoryEmpty" style="display:none;text-align:center;padding:2rem;color:#9ca3af;">
                                <p>No payment records found.</p>
                    </div>
                            </div>

                        <div id="payTabRecord" class="pay-detail-tab-panel" style="display:none;">
                            <div id="recordPaymentPaidState" class="pay-detail-paid-state" style="display:none;">
                                <p>All services paid.</p>
                            </div>
                            <div id="recordPaymentVoidedState" class="pay-detail-voided-state" style="display:none;">
                                <p>This invoice is voided. No payment is expected.</p>
                            </div>
                            <div id="recordPaymentFormWrap">
                                <div id="recordPaymentBreakdown" class="pay-detail-breakdown"></div>
                                <div class="pay-detail-balance-box">
                                    <div style="display:flex;justify-content:space-between;align-items:center;">
                                        <span style="color:#166534;font-weight:600;font-size:0.95rem;">Total Outstanding Balance</span>
                                        <span id="recordPaymentBalance" style="color:#dc2626;font-weight:700;font-size:1.15rem;">P0.00</span>
                                    </div>
                                    <div style="display:flex;justify-content:space-between;margin-top:6px;font-size:0.85rem;">
                                        <span style="color:#6b7280;">Total: <span id="recordPaymentTotalAmount">P0.00</span></span>
                                        <span style="color:#6b7280;">Paid: <span id="recordPaymentPaidSoFar" style="color:#16a34a;">P0.00</span></span>
                            </div>
                        </div>
                        <div style="margin-top: 1rem;">
                                    <label class="pay-detail-label">Payment Amount</label>
                                    <input type="number" id="recordPaymentAmount" step="0.01" min="0.01" class="pay-detail-input">
                        </div>
                        <div style="margin-top: 1rem;">
                                    <label class="pay-detail-label">Payment Method</label>
                                    <select id="recordPaymentMethod" class="pay-detail-input">
                                <option value="cash">Cash</option>
                                <option value="gcash">GCash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="installment">Installment</option>
                                <option value="downpayment">Downpayment</option>
                            </select>
                        </div>
                                <div class="pay-detail-due-row" style="margin-top:16px;justify-content:flex-end;">
                                    <button type="button" class="btn-primary" onclick="confirmRecordPayment()">Record Payment</button>
                    </div>
                    </div>
                </div>
            </div>
                    <div class="modal-actions pay-detail-footer">
                        <div id="payDueDateSection" class="pay-detail-footer-left" style="display:none;">
                            <label class="pay-detail-footer-label" for="setDueDateInput">Due Date</label>
                            <input type="date" id="setDueDateInput">
                            <button type="button" class="btn-primary" onclick="confirmSetDueDate()">Save due date</button>
                    </div>
                        <div class="pay-detail-footer-right">
                            <div id="payMarkPaidSection" style="display:none;">
                                <button type="button" class="btn-primary" id="payMarkPaidBtn" onclick="showMarkPaidConfirm()">Mark as Paid</button>
                </div>
                            <button type="button" class="btn-cancel" onclick="closePaymentModal()">Close</button>
            </div>
                    </div>
                </div>
            </div>

            <!-- Mark as Paid Confirmation Modal -->
            <div id="markPaidConfirmModal" class="modal-overlay" style="display: none; z-index: 100003;" onclick="if (event.target === this) hideMarkPaidConfirm()">
                <div class="modal mark-paid-confirm-modal">
                    <div class="modal-header">
                        <h2>Confirm Payment</h2>
                        <button type="button" class="modal-close" onclick="hideMarkPaidConfirm()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p style="margin:0 0 12px;">Are you sure you want to mark this as <strong>Paid</strong>?</p>
                        <div class="payment-details">
                            <div class="detail-row">
                                <span class="detail-label">Treatment:</span>
                                <span class="detail-value" id="confirmPaymentTreatment">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Total Amount:</span>
                                <span class="detail-value" id="confirmPaymentTotalAmount">P0.00</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Already Paid:</span>
                                <span class="detail-value" id="confirmPaymentPaidSoFar">P0.00</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Balance to Pay:</span>
                                <span class="detail-value" id="confirmPaymentBalance" style="font-weight: 700; color: #059669;">P0.00</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" onclick="hideMarkPaidConfirm()">Cancel</button>
                        <button type="button" class="btn-primary" onclick="confirmPayment()">Confirm Payment</button>
                    </div>
                </div>
            </div>

            <!-- Print Invoice Modal -->
            <div id="printPaymentModal" class="modal-overlay" style="display: none;">
                <div class="modal-backdrop"></div>
                <div class="modal" style="max-width: 450px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600;">Print Invoice</h2>
                        <button type="button" onclick="closePrintPaymentModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">&times;</button>
                    </div>
                    <div id="printPaymentContent"></div>
                    <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                        <button type="button" onclick="closePrintPaymentModal()" style="padding: 10px 20px; background: white; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer;">Close</button>
                        <button type="button" onclick="confirmPrint()" style="padding: 10px 20px; background: #0ea5e9; color: white; border: none; border-radius: 8px; cursor: pointer;">Print</button>
                    </div>
                </div>
            </div>

            <!-- Kebab Dropdown Portal -->
            <div id="paymentKebabDropdownPortal" class="queue-kebab-dropdown-portal"></div>
            <div id="paymentKebabBackdrop" class="queue-kebab-backdrop"></div>

            <style>
                .queue-kebab-menu { position: relative; display: inline-block; }
                .queue-kebab-btn { background: none; border: none; cursor: pointer; padding: 8px; border-radius: 50%; color: #6b7280; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; }
                .queue-kebab-btn:hover { background-color: #f3f4f6; color: #374151; }
                .queue-kebab-btn.active { background-color: #e5e7eb; color: #111827; }
                .queue-kebab-dropdown-portal { display: none; position: fixed; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15), 0 8px 10px -6px rgba(0,0,0,0.1); min-width: 180px; max-width: 220px; z-index: 99999; overflow: hidden; }
                .queue-kebab-dropdown-portal.show { display: block; animation: queueKebabFadeIn 0.15s ease; }
                @keyframes queueKebabFadeIn { from { opacity: 0; transform: scale(0.95) translateY(-4px); } to { opacity: 1; transform: scale(1) translateY(0); } }
                .queue-kebab-dropdown-portal a { display: flex; align-items: center; gap: 10px; padding: 10px 16px; color: #374151; text-decoration: none; font-size: 0.875rem; transition: all 0.15s ease; cursor: pointer; white-space: nowrap; }
                .queue-kebab-dropdown-portal a:hover { background-color: #f9fafb; color: #111827; }
                .queue-kebab-dropdown-portal a svg { flex-shrink: 0; }
                .queue-kebab-dropdown-portal a:first-child { border-radius: 8px 8px 0 0; }
                .queue-kebab-dropdown-portal a:last-child { border-radius: 0 0 8px 8px; }
                .queue-kebab-backdrop { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 99998; }
                .queue-kebab-backdrop.show { display: block; }
                .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 100001; align-items: center; justify-content: center; }
                .modal-overlay.active { display: flex; }
                .modal-overlay[style*="display: flex"] { display: flex !important; }
                .modal-backdrop { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
                .modal { background: white; border-radius: 16px; padding: 24px; max-width: 500px; width: 90%; max-height: 85vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); position: relative; z-index: 100002; }
                .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; border-bottom: 1px solid #e5e7eb; }
                .modal-header h2 { margin: 0; font-size: 1.25rem; font-weight: 600; }
                .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280; }
                .payment-details { background: #f9fafb; border-radius: 8px; padding: 16px; }
                .payment-details .detail-row, .detail-row { display: flex; justify-content: space-between; margin-bottom: 10px; padding: 0.5rem 0; }
                .payment-details .detail-row:last-child, .detail-row:last-child { margin-bottom: 0; }
                .detail-row:not(:last-child) { border-bottom: 1px solid #e5e7eb; }
                .payment-details .detail-label, .detail-label { color: #6b7280; font-size: 0.875rem; }
                .payment-details .detail-value, .detail-value { font-weight: 600; font-size: 0.95rem; color: #1f2937; }
                .table-container { height: fit-content; }
                .data-table { min-width: 800px; }
                .page-header { margin-bottom: 0px; }

                .pay-detail-modal {
                    max-width: 800px;
                    width: 90%;
                    padding: 0;
                    display: flex;
                    flex-direction: column;
                    max-height: 90vh;
                    overflow: hidden;
                }
                .pay-detail-modal .modal-header { flex-shrink: 0; }
                .pay-detail-tabs {
                    display: flex;
                    gap: 0;
                    padding: 0 1.25rem;
                    border-bottom: 1px solid #e5e7eb;
                    flex-shrink: 0;
                }
                .pay-detail-tab {
                    padding: 12px 20px;
                    background: none;
                    border: none;
                    font-size: 0.9rem;
                    font-weight: 500;
                    color: #6b7280;
                    cursor: pointer;
                    border-bottom: 2px solid transparent;
                    margin-bottom: -1px;
                    transition: all 0.2s;
                }
                .pay-detail-tab:hover { color: #1f2937; }
                .pay-detail-tab.active { color: #005daa; border-bottom-color: #005daa; }
                .pay-detail-body { flex: 1; overflow-y: auto; min-height: 0; padding: 1.25rem; }
                .pay-detail-footer {
                    flex-shrink: 0;
                    justify-content: space-between;
                    align-items: center;
                    width: 100%;
                    box-sizing: border-box;
                    gap: 0.75rem;
                    flex-wrap: wrap;
                }
                .pay-detail-footer-left,
                .pay-detail-footer-right { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
                .pay-detail-footer-right { margin-left: auto; }
                .pay-detail-footer-label { font-size: 0.85rem; font-weight: 600; color: #374151; white-space: nowrap; }
                .pay-detail-footer-left input[type="date"] { padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 0.9rem; }
                .pay-detail-section { margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid #e5e7eb; }
                .pay-detail-section h3 { margin: 0 0 0.75rem; font-size: 0.95rem; font-weight: 600; color: #1f2937; }
                .pay-detail-hint { margin: 0 0 0.75rem; font-size: 0.85rem; color: #6b7280; }
                .pay-detail-due-row { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
                .pay-detail-due-row input[type="date"] { padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 0.9rem; }
                .pay-detail-label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151; }
                .pay-detail-input { width: 100%; padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 0.9rem; background: white; box-sizing: border-box; }
                .pay-detail-breakdown { background: #f9fafb; border-radius: 8px; padding: 14px; margin-bottom: 14px; border: 1px solid #e5e7eb; }
                .pay-detail-balance-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 14px; margin-bottom: 16px; }
                .pay-detail-paid-state { text-align: center; padding: 2rem 1rem; color: #16a34a; font-weight: 600; }
                .pay-detail-voided-state { text-align: center; padding: 2rem 1rem; color: #dc2626; font-weight: 600; }
                .status-badge.partial { background: #fef3c7; color: #92400e; }
                .status-badge.voided { background: #fee2e2; color: #dc2626; }
                .mark-paid-confirm-modal { max-width: 440px; width: 90%; padding: 0; }
                .mark-paid-confirm-modal .modal-body { padding: 1.5rem; }
                .mark-paid-confirm-modal .btn-cancel { padding: 0.5rem 1rem; background: #0ea5e9; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; }
                .mark-paid-confirm-modal .btn-cancel:hover { background: #0284c7; }
                .mark-paid-confirm-modal .btn-primary { padding: 0.5rem 1rem; background: #16a34a; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; }
                .mark-paid-confirm-modal .btn-primary:hover { background: #15803d; }
                .pay-history-table { width: 100%; border-collapse: collapse; }
                .pay-history-table th { padding: 0.5rem; font-size: 0.8rem; color: #6b7280; font-weight: 600; text-transform: uppercase; text-align: left; background: #f9fafb; }
                .pay-history-table td { padding: 0.6rem 0.5rem; border-top: 1px solid #f3f4f6; font-size: 0.875rem; }
                .modal-actions { padding: 1rem 1.5rem; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 0.75rem; }
                .modal-actions.pay-detail-footer { justify-content: space-between; align-items: center; width: 100%; box-sizing: border-box; flex-wrap: wrap; }
                .pay-detail-modal .btn-cancel { padding: 0.5rem 1rem; background: #0ea5e9; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; }
                .pay-detail-modal .btn-cancel:hover { background: #0284c7; }
                .pay-detail-modal .btn-primary { padding: 0.5rem 1rem; background: #16a34a; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; }
                .pay-detail-modal .btn-primary:hover { background: #15803d; }
            </style>

            <script>
                var paymentData = {};

                <?php foreach ($billingRecords as $record): ?>
                <?php $invoiceNum = 'INV-' . str_pad($record['billing_id'], 3, '0', STR_PAD_LEFT); ?>
                <?php $fullName = trim(($record['first_name'] ?? '') . ' ' . ($record['middle_name'] ?? '') . ' ' . ($record['last_name'] ?? '') . ' ' . ($record['suffix'] ?? '')); ?>
                <?php $fullName = preg_replace('/\s+/', ' ', $fullName); ?>
                <?php $services = $record['service_name'] ?? $record['treatment_type'] ?? 'General Service'; ?>
                paymentData['<?php echo $invoiceNum; ?>'] = {
                    id: '<?php echo $invoiceNum; ?>',
                    billing_id: '<?php echo $record['billing_id']; ?>',
                    patient_id: '<?php echo $record['patient_id']; ?>',
                    queue_id: '<?php echo (int)($record['queue_id'] ?? 0); ?>',
                    patient: '<?php echo addslashes($fullName); ?>',
                    services: '<?php echo addslashes($services); ?>',
                    amount: <?php echo $record['total_amount']; ?>,
                    paid_amount: <?php echo $record['paid_amount'] ?? 0; ?>,
                    balance: <?php echo $record['balance'] ?? 0; ?>,
                    date: '<?php echo date('Y-m-d', strtotime($record['billing_date'])); ?>',
                    dueDate: '<?php echo !empty($record['due_date']) ? date('Y-m-d', strtotime($record['due_date'])) : ''; ?>',
                    status: '<?php echo $record['payment_status']; ?>',
                    phone: '<?php echo addslashes($record['phone'] ?? ''); ?>'
                };
                <?php endforeach; ?>

                var currentPrintId = null;
                var currentBillingId = null;

                // Kebab Menu - Portal Based
                var paymentKebabDropdown = null;
                var paymentKebabBackdrop = null;
                var paymentActiveButton = null;

                function createPaymentKebabDropdown() {
                    paymentKebabDropdown = document.createElement('div');
                    paymentKebabDropdown.className = 'queue-kebab-dropdown-portal';
                    paymentKebabDropdown.id = 'paymentKebabDropdownPortal';
                    document.body.appendChild(paymentKebabDropdown);

                    paymentKebabBackdrop = document.createElement('div');
                    paymentKebabBackdrop.className = 'queue-kebab-backdrop';
                    paymentKebabBackdrop.id = 'paymentKebabBackdrop';
                    document.body.appendChild(paymentKebabBackdrop);

                    paymentKebabBackdrop.addEventListener('click', closePaymentKebabDropdown);
                }

                function getPaymentMenuItems(button) {
                    var invoiceId = button.dataset.invoiceId;
                    var billingId = button.dataset.billingId || '0';
                    var patientId = button.dataset.patientId || '0';
                    var queueId = button.dataset.queueId || '0';
                    var amount = button.dataset.amount || '0';
                    var paidAmount = button.dataset.paidAmount || '0';
                    var balance = button.dataset.balance || '0';
                    var treatment = button.dataset.treatment || '';
                    var isPaid = button.dataset.isPaid === '1';
                    var isVoided = button.dataset.isVoided === '1';

                    var html = '<a href="javascript:void(0)" data-action="view" data-invoice-id="' + invoiceId + '" data-queue-id="' + queueId + '" data-patient-id="' + patientId + '" data-billing-id="' + billingId + '" data-amount="' + amount + '" data-treatment="' + encodeURIComponent(treatment) + '" data-paid-amount="' + paidAmount + '" data-balance="' + balance + '" data-is-paid="' + (isPaid ? '1' : '0') + '" data-is-voided="' + (isVoided ? '1' : '0') + '">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>' +
                        'View Details</a>';

                    if (!isPaid && !isVoided) {
                        html += (button.dataset.discountType === 'none'
                                ? '<a href="javascript:void(0)" data-action="apply-discount" data-billing-id="' + billingId + '" data-subtotal="' + (button.dataset.subtotal || amount) + '">' +
                                '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M21.41 11.58l-9-9A2 2 0 0 0 11 2H4a2 2 0 0 0-2 2v7a2 2 0 0 0 .59 1.42l9 9A2 2 0 0 0 13 22a2 2 0 0 0 1.41-.59l7-7A2 2 0 0 0 22 13a2 2 0 0 0-.59-1.42M6.5 8A1.5 1.5 0 1 1 8 6.5A1.5 1.5 0 0 1 6.5 8"/></svg>' +
                                'Apply Discount</a>'
                                : '<a href="javascript:void(0)" data-action="toggle-discount" data-billing-id="' + billingId + '" data-apply="0">' +
                                '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5L12 10.59L6.41 5L5 6.41L10.59 12L5 17.59L6.41 19L12 13.41L17.59 19L19 17.59L13.41 12z"/></svg>' +
                                'Remove Discount</a>');
                    }

                    html += '<a href="javascript:void(0)" data-action="print" data-invoice-id="' + invoiceId + '">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>' +
                        'Print Invoice</a>';

                    return html;
                }

                function positionPaymentKebabDropdown(button) {
                    if (!paymentKebabDropdown || !button) return;
                    var rect = button.getBoundingClientRect();
                    var left = rect.right + 5;
                    var top = rect.top;
                    if (left + 200 > window.innerWidth - 15) left = rect.left - 205;
                    if (left < 15) left = 15;
                    if (top + 250 > window.innerHeight - 15) top = rect.bottom - 250;
                    if (top < 15) top = 15;
                    paymentKebabDropdown.style.left = left + 'px';
                    paymentKebabDropdown.style.top = top + 'px';
                }

                function openPaymentKebabDropdown(button) {
                    if (!paymentKebabDropdown || !paymentKebabDropdown.isConnected) {
                        paymentKebabDropdown = null;
                        paymentKebabBackdrop = null;
                        createPaymentKebabDropdown();
                    }
                    paymentKebabDropdown.innerHTML = getPaymentMenuItems(button);
                    positionPaymentKebabDropdown(button);
                    paymentKebabDropdown.classList.add('show');
                    paymentKebabBackdrop.classList.add('show');
                    paymentActiveButton = button;
                    button.classList.add('active');
                    paymentKebabDropdown.addEventListener('click', handlePaymentKebabClick);
                }

                function closePaymentKebabDropdown() {
                    if (paymentKebabDropdown) { paymentKebabDropdown.classList.remove('show'); paymentKebabDropdown.innerHTML = ''; }
                    if (paymentKebabBackdrop) paymentKebabBackdrop.classList.remove('show');
                    if (paymentActiveButton) { paymentActiveButton.classList.remove('active'); paymentActiveButton = null; }
                }

                function handlePaymentKebabClick(e) {
                    var link = e.target.closest('a[data-action]');
                    if (!link) return;
                    e.preventDefault();
                    e.stopPropagation();
                    var action = link.dataset.action;
                    var invoiceId = link.dataset.invoiceId;
                    closePaymentKebabDropdown();

                    switch(action) {
                        case 'view':
                            viewPaymentDetails(link.dataset.queueId, link.dataset.patientId, link.dataset.billingId, {
                                amount: link.dataset.amount,
                                treatment: decodeURIComponent(link.dataset.treatment || ''),
                                paidAmount: link.dataset.paidAmount,
                                balance: link.dataset.balance,
                                isPaid: link.dataset.isPaid === '1',
                                isVoided: link.dataset.isVoided === '1'
                            });
                            break;
                        case 'print':
                            printPayment(invoiceId);
                            break;
                        case 'toggle-discount':
                            toggleDiscount(link.dataset.billingId, link.dataset.apply === '1');
                            break;
                        case 'apply-discount':
                            openApplyDiscountModal(link.dataset.billingId, link.dataset.subtotal, '\u20B1', function() {
                                (window.appCache ? window.appCache.refreshCurrent(['admin_dashboard.php']) : (window.location.reload.bind(window))());
                            });
                            break;
                    }
                }

                var applyDiscountState = null;

                function formatDiscountMoney(n) {
                    return (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                function openApplyDiscountModal(billingId, subtotal, currency, onDone) {
                    if (!billingId || billingId === '0') { alert('No billing record attached.'); return; }
                    closeApplyDiscountModal();
                    var labelStyle = 'display:block;font-size:0.8rem;font-weight:600;color:#374151;margin-bottom:6px;';
                    var rowStyle = 'display:flex;justify-content:space-between;padding:3px 0;';
                    var overlay = document.createElement('div');
                    overlay.className = 'modal-overlay';
                    overlay.style.cssText = 'display:flex;z-index:100002;';
                    overlay.innerHTML =
                        '<div style="background:#fff;border-radius:12px;padding:20px;width:92%;max-width:420px;box-shadow:0 20px 40px rgba(0,0,0,0.2);">' +
                            '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">' +
                                '<h2 style="margin:0;font-size:1.15rem;font-weight:600;color:#111827;">Apply Discount</h2>' +
                                '<button type="button" data-role="close" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#6b7280;">&times;</button>' +
                            '</div>' +
                            '<label style="' + labelStyle + '">Discount Type</label>' +
                            '<div style="display:flex;gap:16px;margin-bottom:14px;font-size:0.9rem;color:#374151;">' +
                                '<label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="radio" name="applyDiscountMode" value="percent" checked> Percentage (%)</label>' +
                                '<label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="radio" name="applyDiscountMode" value="fixed"> Fixed Amount (' + currency + ')</label>' +
                            '</div>' +
                            '<label style="' + labelStyle + '">Value</label>' +
                            '<input type="number" data-role="value" class="pay-detail-input" min="0" step="0.01" placeholder="e.g., 10" style="margin-bottom:14px;">' +
                            '<label style="' + labelStyle + '">Reason</label>' +
                            '<input type="text" data-role="reason" class="pay-detail-input" maxlength="255" placeholder="e.g., Promo, Goodwill, Loyal patient" style="margin-bottom:14px;">' +
                            '<div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px;font-size:0.85rem;color:#374151;">' +
                                '<div style="' + rowStyle + '"><span>Bill Amount</span><span data-role="subtotal"></span></div>' +
                                '<div style="' + rowStyle + 'color:#047857;"><span>Discount</span><span data-role="discount"></span></div>' +
                                '<div style="' + rowStyle + 'font-weight:700;border-top:1px solid #e5e7eb;margin-top:4px;padding-top:6px;"><span>New Total</span><span data-role="total"></span></div>' +
                            '</div>' +
                            '<p style="font-size:0.75rem;color:#6b7280;margin:10px 0 0;">The patient will be notified in their account.</p>' +
                            '<div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">' +
                                '<button type="button" data-role="cancel" style="padding:0.5rem 1rem;background:#fff;border:1px solid #d1d5db;border-radius:8px;cursor:pointer;font-weight:500;color:#374151;">Cancel</button>' +
                                '<button type="button" data-role="submit" style="padding:0.5rem 1rem;background:#16a34a;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:500;">Apply Discount</button>' +
                            '</div>' +
                        '</div>';
                    document.body.appendChild(overlay);

                    applyDiscountState = {
                        overlay: overlay,
                        billingId: billingId,
                        subtotal: parseFloat(subtotal) || 0,
                        currency: currency,
                        onDone: onDone,
                        submitting: false
                    };

                    overlay.addEventListener('click', function(e) { if (e.target === overlay) closeApplyDiscountModal(); });
                    overlay.querySelectorAll('input[name="applyDiscountMode"]').forEach(function(r) {
                        r.addEventListener('change', function() {
                            overlay.querySelector('[data-role="value"]').placeholder = r.value === 'fixed' ? 'e.g., 500' : 'e.g., 10';
                            updateApplyDiscountPreview();
                        });
                    });
                    overlay.querySelector('[data-role="value"]').addEventListener('input', updateApplyDiscountPreview);
                    overlay.querySelector('[data-role="close"]').addEventListener('click', closeApplyDiscountModal);
                    overlay.querySelector('[data-role="cancel"]').addEventListener('click', closeApplyDiscountModal);
                    overlay.querySelector('[data-role="submit"]').addEventListener('click', submitApplyDiscount);
                    updateApplyDiscountPreview();
                    overlay.querySelector('[data-role="value"]').focus();
                }

                function closeApplyDiscountModal() {
                    if (applyDiscountState && applyDiscountState.overlay && applyDiscountState.overlay.parentNode) {
                        applyDiscountState.overlay.parentNode.removeChild(applyDiscountState.overlay);
                    }
                    applyDiscountState = null;
                }

                function computeApplyDiscount() {
                    var s = applyDiscountState;
                    var mode = s.overlay.querySelector('input[name="applyDiscountMode"]:checked').value;
                    var value = parseFloat(s.overlay.querySelector('[data-role="value"]').value) || 0;
                    var amount = mode === 'percent' ? Math.round(s.subtotal * value) / 100 : value;
                    amount = Math.min(Math.max(amount, 0), s.subtotal);
                    return { mode: mode, value: value, amount: amount, total: s.subtotal - amount };
                }

                function updateApplyDiscountPreview() {
                    if (!applyDiscountState) return;
                    var s = applyDiscountState;
                    var c = computeApplyDiscount();
                    s.overlay.querySelector('[data-role="subtotal"]').textContent = s.currency + formatDiscountMoney(s.subtotal);
                    s.overlay.querySelector('[data-role="discount"]').textContent = '-' + s.currency + formatDiscountMoney(c.amount);
                    s.overlay.querySelector('[data-role="total"]').textContent = s.currency + formatDiscountMoney(c.total);
                }

                function submitApplyDiscount() {
                    var s = applyDiscountState;
                    if (!s || s.submitting) return;
                    var c = computeApplyDiscount();
                    var reason = s.overlay.querySelector('[data-role="reason"]').value.trim();
                    if (c.value <= 0) { alert('Please enter a discount value greater than zero.'); return; }
                    if (c.mode === 'percent' && c.value > 100) { alert('Discount percentage cannot exceed 100%.'); return; }
                    if (c.mode === 'fixed' && c.value > s.subtotal) { alert('Discount cannot exceed the bill amount of ' + s.currency + formatDiscountMoney(s.subtotal) + '.'); return; }
                    if (!reason) { alert('Please enter a reason for the discount.'); return; }

                    s.submitting = true;
                    var submitBtn = s.overlay.querySelector('[data-role="submit"]');
                    submitBtn.disabled = true;
                    fetch('../shared/billing_actions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'apply_custom_discount', billing_id: s.billingId, mode: c.mode, value: c.value, reason: reason })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        alert(data.message || (data.success ? 'Done' : 'Failed'));
                        if (data.success) {
                            var done = s.onDone;
                            closeApplyDiscountModal();
                            if (done) done();
                        } else {
                            s.submitting = false;
                            submitBtn.disabled = false;
                        }
                    })
                    .catch(function() {
                        alert('Request failed. Please try again.');
                        s.submitting = false;
                        submitBtn.disabled = false;
                    });
                }

                function toggleDiscount(billingId, apply) {
                    if (!billingId || billingId === '0') { alert('No billing record attached.'); return; }
                    fetch('../shared/billing_actions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'toggle_discount', billing_id: billingId, apply: apply })
                    })
                    .then(r => r.json())
                    .then(data => {
                        alert(data.message || (data.success ? 'Done' : 'Failed'));
                        if (data.success) (window.appCache ? window.appCache.refreshCurrent(['admin_dashboard.php']) : (window.location.reload.bind(window))());
                    })
                    .catch(() => alert('Request failed. Please try again.'));
                }

                if (window.__adminPaymentKebabClickHandler) {
                    document.removeEventListener('click', window.__adminPaymentKebabClickHandler, true);
                    document.removeEventListener('click', window.__adminPaymentKebabClickHandler, false);
                }
                function handleAdminPaymentKebabDocumentClick(e) {
                    var button = e.target.closest('.queue-kebab-btn');
                    if (button) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (paymentActiveButton === button && paymentKebabDropdown && paymentKebabDropdown.classList.contains('show')) {
                            closePaymentKebabDropdown();
                        } else {
                            if (paymentActiveButton) paymentActiveButton.classList.remove('active');
                            openPaymentKebabDropdown(button);
                        }
                    }
                }
                window.__adminPaymentKebabClickHandler = handleAdminPaymentKebabDocumentClick;
                document.addEventListener('click', window.__adminPaymentKebabClickHandler, true);

                function isVoidedPaymentStatus(status) {
                    var s = (status || '').toLowerCase();
                    return s === 'cancelled' || s === 'canceled' || s === 'voided';
                }

                function isVoidedBilling(bl, billingId, isVoided) {
                    if (isVoidedPaymentStatus(bl && bl.payment_status)) {
                        return true;
                    }
                    return !!isVoided && !!billingId && bl && String(bl.id) === String(billingId);
                }

                function currentPayDetailIsVoided() {
                    var el = document.getElementById('payDetailIsVoided');
                    return !!(el && el.value === '1');
                }

                function formatPayStatus(status) {
                    var s = (status || 'unpaid').toLowerCase();
                    if (s === 'paid') return '<span style="background:#dcfce7;color:#16a34a;padding:2px 10px;border-radius:9999px;font-size:0.75rem;font-weight:600;text-transform:uppercase;">Paid</span>';
                    if (s === 'partial') return '<span style="background:#fef3c7;color:#92400e;padding:2px 10px;border-radius:9999px;font-size:0.75rem;font-weight:600;text-transform:uppercase;">Partial</span>';
                    if (s === 'cancelled' || s === 'canceled' || s === 'voided') return '<span style="background:#fee2e2;color:#dc2626;padding:2px 10px;border-radius:9999px;font-size:0.75rem;font-weight:600;text-transform:uppercase;">Voided</span>';
                    return '<span style="background:#fee2e2;color:#dc2626;padding:2px 10px;border-radius:9999px;font-size:0.75rem;font-weight:600;text-transform:uppercase;">Unpaid</span>';
                }

                var payDetailPaidServices = [];

                function uniqueBillings(rows) {
                    var seen = {};
                    var out = [];
                    (rows || []).forEach(function(bl) {
                        var id = String(bl.id || '');
                        if (!id || seen[id]) return;
                        seen[id] = true;
                        out.push(bl);
                    });
                    return out;
                }

                function formatPayMoney(n) {
                    return 'P' + parseFloat(n || 0).toFixed(2);
                }

                function serviceAdjustmentNote(bl) {
                    var billed = parseFloat(bl.total_amount || 0);
                    var clinic = parseFloat(bl.service_price || 0);
                    if (!clinic || Math.abs(billed - clinic) < 0.01) return '';
                    var adj = billed - clinic;
                    var sign = adj >= 0 ? '+' : '\u2212';
                    return '<div style="font-size:0.75rem;color:#6b7280;font-weight:400;margin-top:4px;">Clinic price ' + formatPayMoney(clinic) + '<br>Adjustment ' + sign + 'P' + Math.abs(adj).toFixed(2) + '</div>';
                }

                function payServiceRowHtml(bl, billingId, invoiceVoided) {
                    var st = (bl.payment_status || 'unpaid').toLowerCase();
                    var rowVoided = isVoidedBilling(bl, billingId, invoiceVoided);
                    var rowBalance = rowVoided ? 0 : parseFloat(bl.balance || 0);
                    var sBg = '#fee2e2', sClr = '#991b1b', sLbl = 'UNPAID';
                    var rowBg = '#fff5f5';
                    if (rowVoided) { sBg = '#fee2e2'; sClr = '#dc2626'; sLbl = 'VOIDED'; rowBg = '#fef2f2'; }
                    else if (st === 'paid') { sBg = '#dcfce7'; sClr = '#15803d'; sLbl = 'PAID'; rowBg = '#f0fdf4'; }
                    else if (st === 'partial') { sBg = '#fef3c7'; sClr = '#92400e'; sLbl = 'PARTIAL'; rowBg = '#fffbeb'; }
                    return '<tr style="border-top:1px solid #f1f5f9;background:' + rowBg + ';">' +
                        '<td style="padding:10px 12px;color:#374151;font-weight:500;">' + (bl.service_name || 'General Service') + serviceAdjustmentNote(bl) + '</td>' +
                        '<td style="padding:10px 12px;text-align:right;color:#111827;font-weight:600;">' + formatPayMoney(bl.total_amount) + '</td>' +
                        '<td style="padding:10px 12px;text-align:right;color:#16a34a;font-weight:600;">' + formatPayMoney(bl.paid_amount) + '</td>' +
                        '<td style="padding:10px 12px;text-align:right;color:' + (rowBalance > 0 ? '#dc2626' : '#16a34a') + ';font-weight:600;">' + formatPayMoney(rowBalance) + '</td>' +
                        '<td style="padding:10px 12px;text-align:center;"><span style="background:' + sBg + ';color:' + sClr + ';padding:3px 10px;border-radius:9999px;font-size:0.75rem;font-weight:600;">' + sLbl + '</span></td></tr>';
                }

                function renderPaidServicesHistory(paidServices, hasPayments) {
                    var wrap = document.getElementById('paidServicesWrap');
                    if (!wrap) return;
                    if (!paidServices || !paidServices.length) {
                        wrap.innerHTML = '';
                        wrap.style.display = 'none';
                        return;
                    }
                    var rows = paidServices.map(function(bl) {
                        return payServiceRowHtml(bl, null, false);
                    }).join('');
                    wrap.style.display = 'block';
                    wrap.innerHTML = '<div style="font-weight:600;color:#166534;margin-bottom:8px;font-size:0.9rem;">Paid services</div>' +
                        '<div style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:16px;">' +
                        '<table style="width:100%;border-collapse:collapse;font-size:0.85rem;">' +
                        '<thead><tr style="background:#f0fdf4;">' +
                        '<th style="padding:10px 12px;text-align:left;color:#166534;font-weight:600;">Service</th>' +
                        '<th style="padding:10px 12px;text-align:right;color:#166534;font-weight:600;">Amount</th>' +
                        '<th style="padding:10px 12px;text-align:right;color:#166534;font-weight:600;">Paid</th>' +
                        '<th style="padding:10px 12px;text-align:right;color:#166534;font-weight:600;">Balance</th>' +
                        '<th style="padding:10px 12px;text-align:center;color:#166534;font-weight:600;">Status</th>' +
                        '</tr></thead><tbody>' + rows + '</tbody></table></div>' +
                        (hasPayments ? '<div style="font-weight:600;color:#374151;margin-bottom:8px;font-size:0.9rem;">Payments</div>' : '');
                }

                function formatDueDate(dateStr) {
                    if (!dateStr) return '';
                    var today = new Date();
                    today.setHours(0, 0, 0, 0);
                    var due = new Date(dateStr);
                    due.setHours(0, 0, 0, 0);
                    var diff = Math.ceil((due - today) / (1000 * 60 * 60 * 24));
                    if (diff < 0) {
                        return '<span style="color:#dc2626;font-weight:600;">' + dateStr + ' (Overdue by ' + Math.abs(diff) + ' days)</span>';
                    } else if (diff === 0) {
                        return '<span style="color:#d97706;font-weight:600;">' + dateStr + ' (Due today)</span>';
                    } else if (diff <= 7) {
                        return '<span style="color:#d97706;font-weight:600;">' + dateStr + ' (' + diff + ' days left)</span>';
                    }
                    return '<span style="color:#16a34a;font-weight:600;">' + dateStr + '</span>';
                }

                function defaultDueDateValue() {
                    var future = new Date();
                    future.setDate(future.getDate() + 30);
                    return future.toISOString().split('T')[0];
                }

                function switchPayDetailTab(tab) {
                    document.querySelectorAll('.pay-detail-tab').forEach(function(btn) {
                        btn.classList.toggle('active', btn.dataset.tab === tab);
                    });
                    document.getElementById('payTabOverview').style.display = tab === 'overview' ? 'block' : 'none';
                    document.getElementById('payTabHistory').style.display = tab === 'history' ? 'block' : 'none';
                    document.getElementById('payTabRecord').style.display = tab === 'record' ? 'block' : 'none';
                    if (tab === 'history') {
                        loadPaymentHistory(
                            document.getElementById('payDetailBillingId').value,
                            document.getElementById('payDetailPatientId').value
                        );
                    }
                }

                function setPayDetailContext(queueId, patientId, billingId, extras) {
                    extras = extras || {};
                    document.getElementById('payDetailQueueId').value = queueId || 0;
                    document.getElementById('payDetailPatientId').value = patientId || 0;
                    document.getElementById('payDetailBillingId').value = billingId || 0;
                    document.getElementById('payDetailAmount').value = extras.amount || 0;
                    document.getElementById('payDetailPaidAmount').value = extras.paidAmount || 0;
                    document.getElementById('payDetailBalance').value = extras.isVoided ? 0 : (extras.balance || extras.amount || 0);
                    document.getElementById('payDetailTreatment').value = extras.treatment || 'General Checkup';
                    document.getElementById('payDetailIsPaid').value = extras.isPaid ? '1' : '0';
                    document.getElementById('payDetailIsVoided').value = extras.isVoided ? '1' : '0';
                    document.getElementById('setDueDateBillingId').value = billingId || 0;
                    document.getElementById('recordPaymentQueueId').value = queueId || 0;
                    document.getElementById('recordPaymentPatientId').value = patientId || 0;
                    document.getElementById('recordPaymentBillingId').value = billingId || 0;
                    document.getElementById('editPaymentBillingId').value = billingId || 0;
                }

                function updateOverviewActions(isPaid, billingId, dueDate, isVoided) {
                    var dueSection = document.getElementById('payDueDateSection');
                    var markSection = document.getElementById('payMarkPaidSection');
                    var editSection = document.getElementById('payEditAmountSection');
                    var hasBilling = billingId && billingId !== '0';
                    var hideCollection = !!isPaid || !!isVoided;
                    dueSection.style.display = (hasBilling && !isVoided) ? 'flex' : 'none';
                    markSection.style.display = (!hideCollection && hasBilling) ? 'flex' : 'none';
                    if (editSection) {
                        editSection.style.display = (!hideCollection && hasBilling) ? 'block' : 'none';
                    }
                    hideMarkPaidConfirm();
                    if (hasBilling) {
                        document.getElementById('setDueDateInput').value = dueDate || defaultDueDateValue();
                        document.getElementById('editPaymentBillingId').value = billingId;
                        document.getElementById('editPaymentAmount').value = document.getElementById('payDetailAmount').value || '';
                        document.getElementById('editPaymentReason').value = '';
                    }
                }

                function viewPaymentDetails(queueId, patientId, billingId, extras) {
                    closePaymentKebabDropdown();
                    extras = extras || {};
                    setPayDetailContext(queueId, patientId, billingId, extras);
                    switchPayDetailTab('overview');
                    hideMarkPaidConfirm();
                    document.getElementById('paymentModal').style.display = 'flex';
                    document.getElementById('paymentModalContent').innerHTML =
                        '<div style="text-align: center; padding: 2rem;"><p>Loading payment details...</p></div>';
                    updateOverviewActions(!!extras.isPaid, billingId, '', !!extras.isVoided);
                    populateRecordPaymentTab();
                    payDetailPaidServices = [];
                    loadPaymentHistory(billingId, patientId);

                    var billingEndpoint = billingId && billingId !== '0'
                        ? '../shared/billing_actions.php?action=get_billing&billing_id=' + encodeURIComponent(billingId)
                        : '../shared/billing_actions.php?action=get_details&queue_id=' + encodeURIComponent(queueId || 0) + '&patient_id=' + encodeURIComponent(patientId || 0);

                    fetch(billingEndpoint)
                        .then(function(response) { return response.json(); })
                        .then(function(data) {
                            var pid = patientId;
                            var dueDate = '';
                            var invoicePaid = !!extras.isPaid;
                            var invoiceVoided = !!extras.isVoided;
                            if (data.success && data.billing) {
                                pid = data.billing.patient_id || pid;
                                dueDate = data.billing.due_date || '';
                                var fetchedStatus = (data.billing.payment_status || '').toLowerCase();
                                invoicePaid = fetchedStatus === 'paid' || invoicePaid;
                                invoiceVoided = invoiceVoided || isVoidedPaymentStatus(fetchedStatus);
                                document.getElementById('payDetailAmount').value = data.billing.total_amount || extras.amount || 0;
                                document.getElementById('payDetailPaidAmount').value = data.billing.paid_amount || extras.paidAmount || 0;
                                document.getElementById('payDetailBalance').value = invoiceVoided ? 0 : (data.billing.balance || extras.balance || 0);
                                if (data.billing.treatment_type) {
                                    document.getElementById('payDetailTreatment').value = data.billing.treatment_type;
                                }
                            }
                            if (data.success && data.patient_id) {
                                pid = data.patient_id || pid;
                            }
                            if (data.success && data.due_date) {
                                dueDate = data.due_date;
                            }
                            if (invoiceVoided) {
                                document.getElementById('payDetailBalance').value = 0;
                            }
                            document.getElementById('payDetailPatientId').value = pid || 0;
                            document.getElementById('recordPaymentPatientId').value = pid || 0;
                            document.getElementById('payDetailIsPaid').value = invoicePaid ? '1' : '0';
                            document.getElementById('payDetailIsVoided').value = invoiceVoided ? '1' : '0';
                            updateOverviewActions(invoicePaid, billingId, dueDate, invoiceVoided);
                            populateRecordPaymentTab();

                            return fetch('../user-patient/patient_record_details.php?id=' + encodeURIComponent(pid))
                                .then(function(resp) { return resp.json(); })
                                .then(function(detailData) {
                                    if (!detailData.success) {
                                        renderSingleBillingDetails(data);
                                        return;
                                    }
                                    var allBillings = uniqueBillings(detailData.all_billings || []);
                                    var patientInfo = detailData.patient || {};
                                    var patientName = [patientInfo.first_name || '', patientInfo.middle_name || '', patientInfo.last_name || ''].filter(Boolean).join(' ') || 'N/A';

                                    var totalAmount = 0, paidAmount = 0, balance = 0;
                                    var collectableUnpaid = false;
                                    var collectablePartial = false;
                                    var anyVoided = false;
                                    var dueBills = [];
                                    var paidBills = [];
                                    allBillings.forEach(function(bl) {
                                        var rowVoided = isVoidedBilling(bl, billingId, invoiceVoided);
                                        var st = (bl.payment_status || 'unpaid').toLowerCase();
                                        if (rowVoided) {
                                            anyVoided = true;
                                            return;
                                        }
                                        totalAmount += parseFloat(bl.total_amount || 0);
                                        paidAmount += parseFloat(bl.paid_amount || 0);
                                        balance += parseFloat(bl.balance || 0);
                                        if (st === 'paid') {
                                            paidBills.push(bl);
                                        } else {
                                            dueBills.push(bl);
                                            if (st === 'partial') collectablePartial = true;
                                            else collectableUnpaid = true;
                                        }
                                    });
                                    payDetailPaidServices = paidBills;

                                    var statusBadge;
                                    if (collectableUnpaid) {
                                        statusBadge = '<span style="background:#fee2e2;color:#dc2626;padding:3px 10px;border-radius:9999px;font-size:0.8rem;font-weight:600;">UNPAID</span>';
                                    } else if (collectablePartial) {
                                        statusBadge = '<span style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:9999px;font-size:0.8rem;font-weight:600;">PARTIAL</span>';
                                    } else if (invoiceVoided || anyVoided) {
                                        statusBadge = '<span style="background:#fee2e2;color:#dc2626;padding:3px 10px;border-radius:9999px;font-size:0.8rem;font-weight:600;">VOIDED</span>';
                                    } else {
                                        statusBadge = '<span style="background:#dcfce7;color:#16a34a;padding:3px 10px;border-radius:9999px;font-size:0.8rem;font-weight:600;">PAID</span>';
                                    }

                                    var dueTableHtml = '';
                                    if (dueBills.length) {
                                        var dueRows = dueBills.map(function(bl) {
                                            return payServiceRowHtml(bl, billingId, invoiceVoided);
                                        }).join('');
                                        var dueBilled = 0, duePaid = 0, dueBal = 0;
                                        dueBills.forEach(function(bl) {
                                            dueBilled += parseFloat(bl.total_amount || 0);
                                            duePaid += parseFloat(bl.paid_amount || 0);
                                            dueBal += parseFloat(bl.balance || 0);
                                        });
                                        dueTableHtml =
                                            '<div style="font-weight:600;color:#374151;margin-bottom:8px;font-size:0.9rem;">Still unpaid</div>' +
                                            '<div style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:12px;">' +
                                            '<table style="width:100%;border-collapse:collapse;font-size:0.85rem;">' +
                                            '<thead><tr style="background:#fff7ed;">' +
                                            '<th style="padding:10px 12px;text-align:left;color:#9a3412;font-weight:600;">Service</th>' +
                                            '<th style="padding:10px 12px;text-align:right;color:#9a3412;font-weight:600;">Amount</th>' +
                                            '<th style="padding:10px 12px;text-align:right;color:#9a3412;font-weight:600;">Paid</th>' +
                                            '<th style="padding:10px 12px;text-align:right;color:#9a3412;font-weight:600;">Balance</th>' +
                                            '<th style="padding:10px 12px;text-align:center;color:#9a3412;font-weight:600;">Status</th>' +
                                            '</tr></thead><tbody>' + dueRows +
                                            '<tr style="border-top:2px solid #d1d5db;background:#fff7ed;">' +
                                            '<td style="padding:10px 12px;color:#9a3412;font-weight:700;">TOTAL DUE</td>' +
                                            '<td style="padding:10px 12px;text-align:right;color:#9a3412;font-weight:700;">' + formatPayMoney(dueBilled) + '</td>' +
                                            '<td style="padding:10px 12px;text-align:right;color:#16a34a;font-weight:700;">' + formatPayMoney(duePaid) + '</td>' +
                                            '<td style="padding:10px 12px;text-align:right;color:#dc2626;font-weight:700;">' + formatPayMoney(dueBal) + '</td>' +
                                            '<td></td></tr></tbody></table></div>';
                                    } else {
                                        dueTableHtml = '<p style="color:#166534;margin:0 0 12px;font-size:0.9rem;">All services are paid. Open History to see paid items and payments.</p>';
                                    }

                                    document.getElementById('paymentModalContent').innerHTML =
                                        '<div style="margin-bottom:16px;">' +
                                            '<div class="detail-row"><span class="detail-label">Patient:</span><span class="detail-value">' + patientName + '</span></div>' +
                                            '<div class="detail-row"><span class="detail-label">Phone:</span><span class="detail-value">' + (patientInfo.phone || 'N/A') + '</span></div>' +
                        '</div>' +
                                        '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px;margin-bottom:16px;">' +
                                            '<div style="display:flex;justify-content:space-between;align-items:center;">' +
                                                '<span style="color:#0f172a;font-weight:600;">Still due</span>' + statusBadge +
                                            '</div>' +
                                            '<div style="margin-top:8px;font-size:1.25rem;font-weight:700;color:' + (balance > 0 ? '#dc2626' : '#16a34a') + ';">' + formatPayMoney(balance) + '</div>' +
                                            '<div style="display:flex;justify-content:space-between;margin-top:8px;font-size:0.85rem;color:#6b7280;">' +
                                                '<span>Billed: <strong>' + formatPayMoney(totalAmount) + '</strong></span>' +
                                                '<span>Collected: <strong style="color:#16a34a;">' + formatPayMoney(paidAmount) + '</strong></span>' +
                                            '</div>' +
                                        '</div>' + dueTableHtml;
                                    loadPaymentHistory(billingId, pid || patientId);
                                });
                        })
                        .catch(function() {
                            document.getElementById('paymentModalContent').innerHTML =
                                '<p style="color: #dc2626;">Error loading details. Please try again.</p>';
                        });
                }

                function renderSingleBillingDetails(data) {
                    var forceVoided = currentPayDetailIsVoided();
                    if (data.success && data.billing) {
                        var billing = data.billing;
                        var patientName = [billing.first_name || '', billing.middle_name || '', billing.last_name || '', billing.suffix || ''].filter(Boolean).join(' ') || 'N/A';
                        var payStatus = forceVoided ? 'voided' : billing.payment_status;
                        document.getElementById('paymentModalContent').innerHTML =
                            '<div class="billing-detail-view">' +
                                '<div class="detail-row"><span class="detail-label">Invoice:</span><span class="detail-value">INV-' + String(billing.id).padStart(3, '0') + '</span></div>' +
                                '<div class="detail-row"><span class="detail-label">Patient:</span><span class="detail-value">' + patientName + '</span></div>' +
                                '<div class="detail-row"><span class="detail-label">Phone:</span><span class="detail-value">' + (billing.phone || 'N/A') + '</span></div>' +
                                '<div class="detail-row"><span class="detail-label">Amount:</span><span class="detail-value">P' + parseFloat(billing.total_amount || 0).toFixed(2) + '</span></div>' +
                                '<div class="detail-row"><span class="detail-label">Paid Amount:</span><span class="detail-value">P' + parseFloat(billing.paid_amount || 0).toFixed(2) + '</span></div>' +
                                '<div class="detail-row"><span class="detail-label">Payment Status:</span><span class="detail-value">' + formatPayStatus(payStatus) + '</span></div>' +
                                '<div class="detail-row"><span class="detail-label">Billing Date:</span><span class="detail-value">' + (billing.billing_date || 'N/A') + '</span></div>' +
                                '<div class="detail-row"><span class="detail-label">Due Date:</span><span class="detail-value">' + (billing.due_date ? formatDueDate(billing.due_date) : '<span style="color:#9ca3af;">Not set</span>') + '</span></div>' +
                                '<div class="detail-row"><span class="detail-label">Notes:</span><span class="detail-value">' + (billing.notes || 'N/A') + '</span></div>' +
                        '</div>';
                    } else if (data.success) {
                        var fallbackStatus = forceVoided ? 'voided' : data.payment_status;
                        document.getElementById('paymentModalContent').innerHTML =
                            '<div class="billing-detail-view">' +
                                '<div class="detail-row"><span class="detail-label">Patient:</span><span class="detail-value">' + data.patient_name + '</span></div>' +
                                '<div class="detail-row"><span class="detail-label">Treatment:</span><span class="detail-value">' + (data.treatment_type || 'General Checkup') + '</span></div>' +
                                '<div class="detail-row"><span class="detail-label">Amount:</span><span class="detail-value">P' + parseFloat(data.amount).toFixed(2) + '</span></div>' +
                                '<div class="detail-row"><span class="detail-label">Payment Status:</span><span class="detail-value">' + formatPayStatus(fallbackStatus) + '</span></div>' +
                                '<div class="detail-row"><span class="detail-label">Date:</span><span class="detail-value">' + data.queue_date + '</span></div>' +
                                '<div class="detail-row"><span class="detail-label">Due Date:</span><span class="detail-value">' + (data.due_date ? formatDueDate(data.due_date) : '<span style="color:#9ca3af;">Not set</span>') + '</span></div>' +
                            '</div>';
                    } else {
                        document.getElementById('paymentModalContent').innerHTML = '<p style="color: #dc2626;">Error loading details: ' + (data.message || 'Unknown error') + '</p>';
                    }
                }

                function closePaymentModal() {
                    var modal = document.getElementById('paymentModal');
                    if (modal) modal.style.display = 'none';
                    hideMarkPaidConfirm();
                }

                function closePrintPaymentModal() {
                    var modal = document.getElementById('printPaymentModal');
                    if (modal) modal.style.display = 'none';
                }

                function showMarkPaidConfirm() {
                    var queueId = document.getElementById('payDetailQueueId').value;
                    var patientId = document.getElementById('payDetailPatientId').value;
                    var billingId = document.getElementById('payDetailBillingId').value;
                    var treatment = document.getElementById('payDetailTreatment').value;
                    var balanceVal = parseFloat(document.getElementById('payDetailBalance').value || 0);
                    var paidVal = parseFloat(document.getElementById('payDetailPaidAmount').value || 0);
                    var amountVal = parseFloat(document.getElementById('payDetailAmount').value || 0);

                    document.getElementById('confirmPaymentQueueId').value = queueId;
                    document.getElementById('confirmPaymentPatientId').value = patientId;
                    document.getElementById('confirmPaymentBillingId').value = billingId || 0;
                    document.getElementById('confirmPaymentAmountValue').value = balanceVal > 0 ? balanceVal : amountVal;
                    document.getElementById('confirmPaymentTreatmentValue').value = treatment;
                    document.getElementById('confirmPaymentTotalAmount').textContent = 'P' + amountVal.toFixed(2);
                    document.getElementById('confirmPaymentPaidSoFar').textContent = 'P' + paidVal.toFixed(2);
                    document.getElementById('confirmPaymentBalance').textContent = 'P' + (balanceVal > 0 ? balanceVal : amountVal).toFixed(2);
                    document.getElementById('confirmPaymentTreatment').textContent = treatment || 'General Checkup';
                    document.getElementById('markPaidConfirmModal').style.display = 'flex';
                }

                function hideMarkPaidConfirm() {
                    var confirmModal = document.getElementById('markPaidConfirmModal');
                    if (confirmModal) confirmModal.style.display = 'none';
                }

                function confirmPayment() {
                    var queueId = document.getElementById('confirmPaymentQueueId').value;
                    var patientId = document.getElementById('confirmPaymentPatientId').value;
                    var billingId = document.getElementById('confirmPaymentBillingId').value;
                    var amount = document.getElementById('confirmPaymentAmountValue').value;
                    var treatment = document.getElementById('confirmPaymentTreatmentValue').value;
                    var confirmBtn = document.querySelector('#markPaidConfirmModal .btn-primary');
                    if (confirmBtn && confirmBtn.disabled) return;
                    var resetConfirmBtn = function() {
                        if (!confirmBtn) return;
                        confirmBtn.disabled = false;
                        confirmBtn.textContent = 'Confirm Payment';
                    };
                    if (confirmBtn) {
                        confirmBtn.disabled = true;
                        confirmBtn.textContent = 'Processing...';
                    }

                    fetch('../shared/billing_actions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'mark_paid',
                            billing_id: billingId,
                            queue_id: queueId,
                            patient_id: patientId,
                            amount: amount,
                            treatment: treatment
                        })
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.success) {
                            hideMarkPaidConfirm();
                            closePaymentModal();
                            resetConfirmBtn();
                            if (window.appCache) {
                                showPaymentSuccessModal(amount, treatment, '\u20B1', null);
                                window.appCache.refreshCurrent(['admin_dashboard.php']);
                            } else {
                                showPaymentSuccessModal(amount, treatment, '\u20B1', function() { window.location.reload(); });
                            }
                        } else {
                            resetConfirmBtn();
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(function() {
                        resetConfirmBtn();
                        alert('Error processing payment. Please try again.');
                    });
                }

                function showPaymentSuccessModal(amount, treatment, currency, onClose, customMessage) {
                    var existing = document.getElementById('paymentSuccessModal');
                    if (existing && existing.parentNode) existing.parentNode.removeChild(existing);

                    var amountText = currency + (Number(amount) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    var overlay = document.createElement('div');
                    overlay.id = 'paymentSuccessModal';
                    overlay.className = 'modal-overlay';
                    // Keeps the router's refresh from removing it before the user closes it
                    overlay.setAttribute('data-persist-layout', '1');
                    overlay.style.cssText = 'display:flex;z-index:100004;';
                    overlay.innerHTML =
                        '<div style="background:#fff;border-radius:12px;padding:28px 24px 20px;width:90%;max-width:400px;text-align:center;box-shadow:0 20px 40px rgba(0,0,0,0.2);">' +
                            '<div style="width:56px;height:56px;border-radius:50%;background:#dcfce7;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">' +
                                '<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>' +
                            '</div>' +
                            '<h2 style="margin:0 0 8px;font-size:1.2rem;font-weight:600;color:#111827;">Payment Successful</h2>' +
                            '<p data-role="message" style="margin:0 0 6px;font-size:0.92rem;color:#374151;"></p>' +
                            '<p style="margin:0 0 18px;font-size:0.8rem;color:#6b7280;">The patient has been notified.</p>' +
                            '<button type="button" data-role="ok" style="padding:0.55rem 2rem;background:#16a34a;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:500;">OK</button>' +
                        '</div>';
                    overlay.querySelector('[data-role="message"]').textContent = customMessage ||
                        ('Payment of ' + amountText + ' for ' + (treatment || 'General Checkup') + ' has been recorded and marked as Paid.');
                    document.body.appendChild(overlay);

                    var closed = false;
                    function closeSuccess() {
                        if (closed) return;
                        closed = true;
                        document.removeEventListener('keydown', onKey);
                        if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                        if (onClose) onClose();
                    }
                    function onKey(e) { if (e.key === 'Escape') closeSuccess(); }
                    overlay.addEventListener('click', function(e) { if (e.target === overlay) closeSuccess(); });
                    overlay.querySelector('[data-role="ok"]').addEventListener('click', closeSuccess);
                    document.addEventListener('keydown', onKey);
                    overlay.querySelector('[data-role="ok"]').focus();
                }

                function populateRecordPaymentTab() {
                    var isPaid = document.getElementById('payDetailIsPaid').value === '1';
                    var isVoided = currentPayDetailIsVoided();
                    var paidState = document.getElementById('recordPaymentPaidState');
                    var voidedState = document.getElementById('recordPaymentVoidedState');
                    var formWrap = document.getElementById('recordPaymentFormWrap');
                    if (isVoided) {
                        if (paidState) paidState.style.display = 'none';
                        if (voidedState) voidedState.style.display = 'block';
                        formWrap.style.display = 'none';
                        return;
                    }
                    if (voidedState) voidedState.style.display = 'none';
                    if (isPaid) {
                        paidState.style.display = 'block';
                        formWrap.style.display = 'none';
                    } else {
                        paidState.style.display = 'none';
                        formWrap.style.display = 'block';
                    }

                    var queueId = document.getElementById('payDetailQueueId').value;
                    var patientId = document.getElementById('payDetailPatientId').value;
                    var billingId = document.getElementById('payDetailBillingId').value;
                    var amount = document.getElementById('payDetailAmount').value;
                    var paidAmount = document.getElementById('payDetailPaidAmount').value;
                    var balance = document.getElementById('payDetailBalance').value;

                    document.getElementById('recordPaymentQueueId').value = queueId || 0;
                    document.getElementById('recordPaymentPatientId').value = patientId || 0;
                    document.getElementById('recordPaymentBillingId').value = billingId || 0;
                    document.getElementById('recordPaymentTotalAmount').textContent = 'P' + parseFloat(amount || 0).toFixed(2);
                    document.getElementById('recordPaymentPaidSoFar').textContent = 'P' + parseFloat(paidAmount || 0).toFixed(2);
                    document.getElementById('recordPaymentBalance').textContent = 'P' + parseFloat(balance || 0).toFixed(2);
                    document.getElementById('recordPaymentAmount').value = parseFloat(balance || 0).toFixed(2);
                    document.getElementById('recordPaymentMethod').value = 'cash';

                    if (!patientId) {
                        document.getElementById('recordPaymentBreakdown').innerHTML = '';
                        return;
                    }

                    fetch('../user-patient/patient_record_details.php?id=' + encodeURIComponent(patientId))
                        .then(function(resp) { return resp.json(); })
                        .then(function(detailData) {
                            var breakdownHtml = '';
                            if (detailData.success) {
                                var allBillings = uniqueBillings(detailData.all_billings || []);
                                var unpaidBills = allBillings.filter(function(bl) {
                                    var st = (bl.payment_status || 'unpaid').toLowerCase();
                                    if (st === 'paid' || isVoidedBilling(bl, billingId, isVoided)) return false;
                                    return parseFloat(bl.balance || 0) > 0;
                                });
                                if (unpaidBills.length > 0) {
                                    paidState.style.display = 'none';
                                    formWrap.style.display = 'block';
                                    breakdownHtml = '<div style="font-weight:600;color:#374151;margin-bottom:8px;font-size:0.85rem;">Unpaid Services:</div>';
                                    breakdownHtml += '<table style="width:100%;border-collapse:collapse;font-size:0.82rem;">';
                                    unpaidBills.forEach(function(bl) {
                                        var st = (bl.payment_status || 'unpaid').toLowerCase();
                                        var sBg = '#fee2e2', sClr = '#991b1b', sLbl = 'UNPAID';
                                        if (st === 'partial') { sBg = '#fef3c7'; sClr = '#92400e'; sLbl = 'PARTIAL'; }
                                        breakdownHtml += '<tr style="border-top:1px solid #e5e7eb;">' +
                                            '<td style="padding:6px 0;color:#374151;font-weight:500;">' + (bl.service_name || 'General Service') + '</td>' +
                                            '<td style="padding:6px 0;text-align:right;color:#dc2626;font-weight:600;">P' + parseFloat(bl.balance).toFixed(2) + '</td>' +
                                            '<td style="padding:6px 8px;text-align:right;"><span style="background:' + sBg + ';color:' + sClr + ';padding:1px 6px;border-radius:9999px;font-size:0.7rem;font-weight:600;">' + sLbl + '</span></td></tr>';
                                    });
                                    breakdownHtml += '</table>';

                                    var totalAmt = 0, totalPaid = 0, outstanding = 0;
                                    allBillings.forEach(function(bl) {
                                        if (isVoidedBilling(bl, billingId, isVoided)) return;
                                        totalAmt += parseFloat(bl.total_amount || 0);
                                        totalPaid += parseFloat(bl.paid_amount || 0);
                                    });
                                    unpaidBills.forEach(function(bl) {
                                        outstanding += parseFloat(bl.balance || 0);
                                    });
                                    document.getElementById('recordPaymentTotalAmount').textContent = 'P' + totalAmt.toFixed(2);
                                    document.getElementById('recordPaymentPaidSoFar').textContent = 'P' + totalPaid.toFixed(2);
                                    document.getElementById('recordPaymentBalance').textContent = 'P' + outstanding.toFixed(2);
                                    document.getElementById('recordPaymentAmount').value = outstanding.toFixed(2);
                                } else {
                                    paidState.style.display = 'block';
                                    formWrap.style.display = 'none';
                                }
                            }
                            document.getElementById('recordPaymentBreakdown').innerHTML = breakdownHtml;
                        })
                        .catch(function() {
                            document.getElementById('recordPaymentBreakdown').innerHTML = '';
                        });
                }

                var recordPaymentInFlight = false;

                function confirmRecordPayment() {
                    if (recordPaymentInFlight) {
                        return;
                    }
                    if (currentPayDetailIsVoided()) {
                        alert('This invoice is voided. No payment is expected.');
                        return;
                    }
                    var billingId = document.getElementById('recordPaymentBillingId').value;
                    var queueId = document.getElementById('recordPaymentQueueId').value;
                    var patientId = document.getElementById('recordPaymentPatientId').value;
                    var amount = document.getElementById('recordPaymentAmount').value;
                    var paymentMethod = document.getElementById('recordPaymentMethod').value;

                    if (!amount || parseFloat(amount) <= 0) {
                        alert('Please enter a valid payment amount.');
                        return;
                    }

                    var methodEl = document.getElementById('recordPaymentMethod');
                    var methodLabel = methodEl && methodEl.selectedIndex >= 0 ? methodEl.options[methodEl.selectedIndex].text : paymentMethod;
                    var outstanding = parseFloat((document.getElementById('recordPaymentBalance').textContent || '').replace(/[^0-9.]/g, '')) || 0;

                    showRecordPaymentReview({
                        billingId: billingId,
                        queueId: queueId,
                        patientId: patientId,
                        amount: amount,
                        paymentMethod: paymentMethod,
                        methodLabel: methodLabel,
                        referenceNumber: '',
                        outstanding: outstanding,
                        currency: '\u20B1'
                    });
                }

                var recordPaymentReviewState = null;

                function formatReviewMoney(currency, n) {
                    return currency + (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                function showRecordPaymentReview(details) {
                    closeRecordPaymentReview();
                    var amountNum = parseFloat(details.amount) || 0;
                    var remaining = Math.max(0, Math.round((details.outstanding - amountNum) * 100) / 100);
                    var rowStyle = 'display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #e5e7eb;font-size:0.9rem;';
                    var labelStyle = 'color:#6b7280;';
                    var valueStyle = 'font-weight:600;color:#1f2937;text-align:right;';

                    var overlay = document.createElement('div');
                    overlay.id = 'recordPaymentReviewModal';
                    overlay.className = 'modal-overlay';
                    overlay.setAttribute('data-persist-layout', '1');
                    overlay.style.cssText = 'display:flex;z-index:100003;';
                    overlay.innerHTML =
                        '<div style="background:#fff;border-radius:12px;width:90%;max-width:440px;box-shadow:0 20px 40px rgba(0,0,0,0.2);overflow:hidden;">' +
                            '<div style="display:flex;justify-content:space-between;align-items:center;padding:1rem 1.5rem;border-bottom:1px solid #e5e7eb;">' +
                                '<h2 style="margin:0;font-size:1.2rem;font-weight:600;color:#111827;">Review Payment</h2>' +
                                '<button type="button" data-role="close" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#6b7280;">&times;</button>' +
                            '</div>' +
                            '<div style="padding:1.25rem 1.5rem;">' +
                                '<p style="margin:0 0 12px;font-size:0.85rem;color:#6b7280;">Please confirm the payment details below.</p>' +
                                '<div style="text-align:center;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px;margin-bottom:12px;">' +
                                    '<div style="font-size:0.8rem;color:#166534;font-weight:600;text-transform:uppercase;letter-spacing:0.03em;">Amount to Pay</div>' +
                                    '<div data-role="amount" style="font-size:1.6rem;font-weight:700;color:#166534;margin-top:2px;"></div>' +
                                '</div>' +
                                '<div style="' + rowStyle + '"><span style="' + labelStyle + '">Payment Method</span><span data-role="method" style="' + valueStyle + '"></span></div>' +
                                '<div data-role="refRow" style="' + rowStyle + 'display:none;"><span style="' + labelStyle + '">Reference No.</span><span data-role="reference" style="' + valueStyle + '"></span></div>' +
                                '<div style="' + rowStyle + '"><span style="' + labelStyle + '">Outstanding Balance</span><span data-role="outstanding" style="' + valueStyle + '"></span></div>' +
                                '<div style="' + rowStyle + 'border-bottom:none;"><span style="' + labelStyle + '">Remaining Balance After Payment</span><span data-role="remaining" style="' + valueStyle + '"></span></div>' +
                            '</div>' +
                            '<div style="display:flex;justify-content:flex-end;gap:10px;padding:1rem 1.5rem;border-top:1px solid #e5e7eb;">' +
                                '<button type="button" data-role="back" style="padding:0.5rem 1rem;background:#fff;border:1px solid #d1d5db;border-radius:8px;cursor:pointer;font-weight:500;color:#374151;">Back</button>' +
                                '<button type="button" data-role="confirm" style="padding:0.5rem 1rem;background:#16a34a;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:500;">Confirm Payment</button>' +
                            '</div>' +
                        '</div>';

                    overlay.querySelector('[data-role="amount"]').textContent = formatReviewMoney(details.currency, amountNum);
                    overlay.querySelector('[data-role="method"]').textContent = details.methodLabel;
                    if (details.referenceNumber) {
                        overlay.querySelector('[data-role="refRow"]').style.display = 'flex';
                        overlay.querySelector('[data-role="reference"]').textContent = details.referenceNumber;
                    }
                    overlay.querySelector('[data-role="outstanding"]').textContent = formatReviewMoney(details.currency, details.outstanding);
                    var remainingEl = overlay.querySelector('[data-role="remaining"]');
                    if (remaining <= 0) {
                        remainingEl.textContent = 'Fully Paid';
                        remainingEl.style.color = '#16a34a';
                    } else {
                        remainingEl.textContent = formatReviewMoney(details.currency, remaining);
                        remainingEl.style.color = '#dc2626';
                    }
                    document.body.appendChild(overlay);

                    function onKey(e) { if (e.key === 'Escape' && !recordPaymentInFlight) closeRecordPaymentReview(); }
                    recordPaymentReviewState = { overlay: overlay, onKey: onKey };
                    details.remaining = remaining;

                    overlay.addEventListener('click', function(e) { if (e.target === overlay && !recordPaymentInFlight) closeRecordPaymentReview(); });
                    overlay.querySelector('[data-role="close"]').addEventListener('click', function() { if (!recordPaymentInFlight) closeRecordPaymentReview(); });
                    overlay.querySelector('[data-role="back"]').addEventListener('click', function() { if (!recordPaymentInFlight) closeRecordPaymentReview(); });
                    overlay.querySelector('[data-role="confirm"]').addEventListener('click', function() { submitRecordPayment(details); });
                    document.addEventListener('keydown', onKey);
                    overlay.querySelector('[data-role="confirm"]').focus();
                }

                function closeRecordPaymentReview() {
                    if (!recordPaymentReviewState) return;
                    document.removeEventListener('keydown', recordPaymentReviewState.onKey);
                    if (recordPaymentReviewState.overlay.parentNode) {
                        recordPaymentReviewState.overlay.parentNode.removeChild(recordPaymentReviewState.overlay);
                    }
                    recordPaymentReviewState = null;
                }

                function submitRecordPayment(details) {
                    if (recordPaymentInFlight) {
                        return;
                    }
                    var recordBtn = document.querySelector('#payTabRecord button.btn-primary');
                    var confirmBtn = recordPaymentReviewState ? recordPaymentReviewState.overlay.querySelector('[data-role="confirm"]') : null;
                    var resetButtons = function() {
                        recordPaymentInFlight = false;
                        if (recordBtn) recordBtn.disabled = false;
                        if (confirmBtn) {
                            confirmBtn.disabled = false;
                            confirmBtn.textContent = 'Confirm Payment';
                        }
                    };
                    recordPaymentInFlight = true;
                    if (recordBtn) recordBtn.disabled = true;
                    if (confirmBtn) {
                        confirmBtn.disabled = true;
                        confirmBtn.textContent = 'Processing...';
                    }

                    fetch('../shared/billing_actions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'record_payment',
                            settle_outstanding: true,
                            billing_id: details.billingId,
                            queue_id: details.queueId,
                            patient_id: details.patientId,
                            amount: details.amount,
                            payment_method: details.paymentMethod
                        })
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.success) {
                            closeRecordPaymentReview();
                            closePaymentModal();
                            recordPaymentInFlight = false;
                            if (recordBtn) recordBtn.disabled = false;
                            var amountText = formatReviewMoney(details.currency, details.amount);
                            var message = 'Payment of ' + amountText + ' via ' + details.methodLabel + ' has been recorded. ' +
                                (details.remaining > 0
                                    ? 'Remaining balance: ' + formatReviewMoney(details.currency, details.remaining) + '.'
                                    : 'The account is now fully paid.');
                            if (window.appCache) {
                                showPaymentSuccessModal(details.amount, '', details.currency, null, message);
                                window.appCache.refreshCurrent(['admin_dashboard.php']);
                            } else {
                                showPaymentSuccessModal(details.amount, '', details.currency, function() { window.location.reload(); }, message);
                            }
                        } else {
                            resetButtons();
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(function() {
                        resetButtons();
                        alert('Error recording payment. Please try again.');
                    });
                }

                function loadPaymentHistory(billingId, patientId) {
                    var tbody = document.getElementById('paymentHistoryBody');
                    var empty = document.getElementById('paymentHistoryEmpty');
                    var payTable = document.getElementById('payHistoryPaymentsTable');
                    if (!tbody || !empty) return;

                    patientId = patientId || document.getElementById('payDetailPatientId').value;
                    billingId = billingId || document.getElementById('payDetailBillingId').value;

                    if ((!patientId || patientId === '0') && (!billingId || billingId === '0')) {
                        tbody.innerHTML = '';
                        empty.style.display = 'block';
                        empty.querySelector('p').textContent = 'No billing record found for this patient.';
                        renderPaidServicesHistory([], false);
                        if (payTable) payTable.style.display = '';
                        return;
                    }

                    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:2rem;color:#6b7280;">Loading...</td></tr>';
                    empty.style.display = 'none';
                    empty.querySelector('p').textContent = 'No payment records found.';
                    if (payTable) payTable.style.display = '';

                    var historyUrl = (patientId && patientId !== '0')
                        ? '../shared/billing_actions.php?action=get_payments&patient_id=' + encodeURIComponent(patientId)
                        : '../shared/billing_actions.php?action=get_payments&billing_id=' + encodeURIComponent(billingId);
                    fetch(historyUrl)
                        .then(function(response) { return response.json(); })
                    .then(function(data) {
                            var payments = (data.success && data.payments) ? data.payments : [];
                            var hasPaid = !!(payDetailPaidServices && payDetailPaidServices.length);
                            var hasPayments = payments.length > 0;
                            renderPaidServicesHistory(payDetailPaidServices, hasPayments);
                            tbody.innerHTML = '';
                            if (hasPayments) {
                                empty.style.display = 'none';
                                if (payTable) payTable.style.display = '';
                                payments.forEach(function(p) {
                                    tbody.appendChild(window.PaymentApproval.renderHistoryRow(p));
                                });
                        } else {
                                if (payTable) payTable.style.display = 'none';
                                empty.style.display = hasPaid ? 'none' : 'block';
                        }
                    })
                    .catch(function() {
                            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:2rem;color:#dc2626;">Error loading payment history.</td></tr>';
                    });
                }

                function confirmSetDueDate() {
                    var billingId = document.getElementById('setDueDateBillingId').value;
                    var dueDate = document.getElementById('setDueDateInput').value;

                    if (!billingId || billingId === '0') {
                        alert('No billing record found for this patient.');
                        return;
                    }
                    if (!dueDate) {
                        alert('Please select a due date.');
                        return;
                    }

                    fetch('../shared/billing_actions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'set_due_date', billing_id: billingId, due_date: dueDate })
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.success) {
                            alert('Due date saved.');
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(function() {
                        alert('Error setting due date. Please try again.');
                    });
                }

                function savePaymentEdit() {
                    var billingId = document.getElementById('editPaymentBillingId').value;
                    var newAmount = parseFloat(document.getElementById('editPaymentAmount').value);
                    var reason = document.getElementById('editPaymentReason').value;

                    if (isNaN(newAmount) || newAmount < 0) { alert('Please enter a valid amount'); return; }
                    if (!billingId) { alert('Billing ID is missing'); return; }

                    fetch('../shared/billing_actions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'update_amount', billing_id: billingId, new_amount: newAmount, reason: reason })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            (window.appCache ? window.appCache.refreshCurrent(['admin_dashboard.php']) : (window.location.reload.bind(window))());
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(function() { alert('Error updating payment.'); });
                }

                // Print Invoice
                function printPayment(invoiceId) {
                    var p = paymentData[invoiceId];
                    if (!p) return;
                    currentPrintId = invoiceId;
                    currentBillingId = p.billing_id;

                    var statusBadge = p.status.toLowerCase() === 'paid'
                        ? '<span style="background: #d1fae5; color: #065f46; padding: 4px 12px; border-radius: 9999px; font-size: 0.85rem;">PAID</span>'
                        : '<span style="background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 9999px; font-size: 0.85rem;">UNPAID</span>';

                    document.getElementById('printPaymentContent').innerHTML =
                        '<div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px;">' +
                        '<div style="text-align: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #e5e7eb;">' +
                        '<h3 style="margin: 0 0 8px; font-size: 1.5rem;">Dental Clinic</h3>' +
                        '<p style="margin: 0; color: #6b7280; font-size: 0.875rem;">smiles Dental Care Center</p>' +
                        '<p style="margin: 4px 0 0; color: #6b7280; font-size: 0.75rem;">123 Dental Street, City</p></div>' +
                        '<div style="display: flex; justify-content: space-between; margin-bottom: 16px;">' +
                        '<div><span style="color: #6b7280; font-size: 0.875rem;">Invoice #:</span> <strong>' + p.id + '</strong></div>' +
                        '<div><span style="color: #6b7280; font-size: 0.875rem;">Date:</span> ' + p.date + '</div></div>' +
                        '<div style="margin-bottom: 16px;"><span style="color: #6b7280; font-size: 0.875rem;">Patient:</span> <strong>' + p.patient + '</strong></div>' +
                        '<div style="margin-bottom: 16px;"><span style="color: #6b7280; font-size: 0.875rem;">Services:</span> ' + p.services + '</div>' +
                        '<div style="margin-bottom: 16px;"><span style="color: #6b7280; font-size: 0.875rem;">Due Date:</span> ' + (p.dueDate || 'N/A') + '</div>' +
                        '<div style="background: #f9fafb; border-radius: 8px; padding: 16px; margin: 24px 0; text-align: center;">' +
                        '<span style="color: #6b7280; font-size: 0.875rem;">Total Amount</span><br>' +
                        '<span style="font-size: 2rem; font-weight: 700; color: #059669;">\u20B1' + parseFloat(p.amount).toLocaleString('en-PH', {minimumFractionDigits: 2}) + '</span></div>' +
                        '<div style="text-align: center;">' + statusBadge + '</div>' +
                        '<div style="text-align: center; margin-top: 24px; padding-top: 16px; border-top: 1px solid #e5e7eb; color: #9ca3af; font-size: 0.75rem;">' +
                        '<p style="margin: 0;">Thank you for choosing our dental clinic!</p>' +
                        '<p style="margin: 4px 0 0;">Please keep this receipt for your records.</p></div></div>';

                    document.getElementById('printPaymentModal').style.display = 'flex';
                }

                function confirmPrint() {
                    if (currentBillingId) {
                        fetch('../shared/billing_actions.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'mark_printed', billing_id: currentBillingId })
                        }).catch(function() {});
                    }
                    window.print();
                }

                // Close modals on backdrop click
                document.querySelectorAll('.modal-overlay').forEach(function(modal) {
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) {
                            if (modal.id === 'paymentModal') {
                                closePaymentModal();
                            } else {
                                modal.style.display = 'none';
                            }
                        }
                    });
                });

                // Close on Escape
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        if (typeof closePaymentKebabDropdown === 'function') closePaymentKebabDropdown();
                        closePaymentModal();
                        closePrintPaymentModal();
                        if (typeof window.hidePaymentSuggestions === 'function') window.hidePaymentSuggestions();
                    }
                });

                (function initPaymentLiveSearch() {
                    var form = document.getElementById('paymentSearchForm');
                    var input = document.getElementById('paymentSearch');
                    var list = document.getElementById('paymentSearchSuggestions');
                    if (!form || !input || !list) return;

                    var debounceTimer = null;
                    var abortCtrl = null;
                    var items = [];
                    var activeIndex = -1;
                    var selecting = false;

                    function hidePaymentSuggestions() {
                        list.hidden = true;
                        list.innerHTML = '';
                        items = [];
                        activeIndex = -1;
                    }

                    window.hidePaymentSuggestions = hidePaymentSuggestions;

                    function suggestionSearchValue(item, query) {
                        var q = (query || '').trim();
                        if (/^inv-?/i.test(q) || /^\d+$/.test(q)) {
                            return String(item.billing_id);
                        }
                        return item.search || item.name || '';
                    }

                    function applySuggestion(item) {
                        if (!item) return;
                        selecting = true;
                        input.value = suggestionSearchValue(item, input.value);
                        hidePaymentSuggestions();
                        form.submit();
                    }

                    function highlight() {
                        var rows = list.querySelectorAll('.payment-search-suggestion');
                        for (var i = 0; i < rows.length; i++) {
                            if (i === activeIndex) rows[i].classList.add('is-active');
                            else rows[i].classList.remove('is-active');
                        }
                    }

                    function renderSuggestions(suggestions, query) {
                        items = suggestions || [];
                        activeIndex = items.length ? 0 : -1;
                        list.innerHTML = '';
                        if (!items.length) {
                            var empty = document.createElement('div');
                            empty.className = 'payment-search-empty';
                            empty.textContent = 'No matching records';
                            list.appendChild(empty);
                            list.hidden = false;
                            return;
                        }
                        items.forEach(function(item, idx) {
                            var btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'payment-search-suggestion' + (idx === 0 ? ' is-active' : '');
                            btn.setAttribute('role', 'option');
                            var name = document.createElement('span');
                            name.className = 'payment-search-suggestion-name';
                            name.textContent = item.name || 'Unknown Patient';
                            var meta = document.createElement('span');
                            meta.className = 'payment-search-suggestion-meta';
                            meta.textContent = (item.invoice || '') + (item.status ? ' · ' + item.status : '');
                            btn.appendChild(name);
                            btn.appendChild(meta);
                            btn.addEventListener('mousedown', function(e) {
                                e.preventDefault();
                                applySuggestion(item);
                            });
                            list.appendChild(btn);
                        });
                        list.hidden = false;
                    }

                    function fetchSuggestions(query) {
                        if (abortCtrl) abortCtrl.abort();
                        abortCtrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
                        var params = new URLSearchParams();
                        params.set('suggest', '1');
                        params.set('q', query);
                        var statusEl = form.querySelector('select[name="status"]');
                        var dateEl = form.querySelector('select[name="date"]');
                        if (statusEl && statusEl.value) params.set('status', statusEl.value);
                        if (dateEl && dateEl.value) params.set('date', dateEl.value);
                        var opts = {};
                        if (abortCtrl) opts.signal = abortCtrl.signal;
                        fetch('admin_payment.php?' + params.toString(), opts)
                            .then(function(res) { return res.json(); })
                            .then(function(data) {
                                if (input.value.trim() !== query) return;
                                renderSuggestions((data && data.suggestions) || [], query);
                            })
                            .catch(function() {});
                    }

                    input.addEventListener('input', function() {
                        var query = input.value.trim();
                        clearTimeout(debounceTimer);
                        if (!query) {
                            hidePaymentSuggestions();
                            return;
                        }
                        debounceTimer = setTimeout(function() {
                            fetchSuggestions(query);
                        }, 200);
                    });

                    input.addEventListener('keydown', function(e) {
                        if (list.hidden || !items.length) return;
                        if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            activeIndex = (activeIndex + 1) % items.length;
                            highlight();
                        } else if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            activeIndex = (activeIndex - 1 + items.length) % items.length;
                            highlight();
                        } else if (e.key === 'Enter' && activeIndex >= 0) {
                            e.preventDefault();
                            applySuggestion(items[activeIndex]);
                        }
                    });

                    input.addEventListener('blur', function() {
                        setTimeout(function() {
                            if (!selecting) hidePaymentSuggestions();
                            selecting = false;
                        }, 150);
                    });

                    document.addEventListener('click', function(e) {
                        if (!e.target.closest('.payment-search-wrap')) {
                            hidePaymentSuggestions();
                        }
                    });
                })();
            </script>

<style>
/* Summary cards - light blue dashboard design (matches staff dashboard) */
.summary-cards .summary-card {
    background: #ffffff;
    padding: 22px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 16px;
    border: none;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    min-height: 100px;
    position: relative;
    overflow: hidden;
    transition: transform 0.2s ease;
    cursor: default;
}
.summary-cards .summary-card:hover {
    transform: translateY(-2px);
}
.summary-cards .summary-icon,
.summary-cards .summary-info {
    position: relative;
    z-index: 2;
}
.summary-cards .summary-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: #d3e0fe;
}
.summary-cards .summary-icon img {
    width: 24px;
    height: 24px;
    object-fit: contain;
}
.summary-cards .summary-info h3 {
    font-size: 1.8rem;
    font-weight: 500;
    color: #4b5563;
    line-height: 1;
    margin-bottom: 4px;
}
.summary-cards .summary-info p {
    font-size: 0.9rem;
    color: #6b7280;
    margin: 0;
    font-weight: 500;
}
.summary-cards .summary-card::after {
    content: '';
    position: absolute;
    right: -25px;
    bottom: -15px;
    width: 100px;
    height: 100px;
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
    opacity: 0.12;
    z-index: 1;
    pointer-events: none;
}
.summary-cards .summary-card.card-collected::after {
    background-image: url('../../assets/icons/peso.png');
}
.summary-cards .summary-card.card-unpaid::after {
    background-image: url('../../assets/icons/unpaid.png');
}
.summary-cards .summary-card.card-transactions::after {
    background-image: url('../../assets/icons/transaction.png');
}
.summary-cards .summary-card.card-overdue::after {
    background-image: url('../../assets/icons/overdue.png');
}
@media (max-width: 768px) {
    .summary-cards { grid-template-columns: 1fr 1fr !important; }
    .summary-card { padding: 16px !important; }
}
@media (max-width: 480px) {
    .summary-cards { grid-template-columns: 1fr 1fr !important; gap: 10px !important; }
    .summary-card { padding: 12px !important; }
}

.payment-search-wrap {
    position: relative;
    flex: 1;
    min-width: 250px;
}
.payment-search-wrap .search-input {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}
.payment-search-suggestions {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: #ffffff;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    box-shadow: 0 8px 16px rgba(15, 23, 42, 0.12);
    z-index: 40;
    max-height: 280px;
    overflow-y: auto;
}
.payment-search-suggestion {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
    width: 100%;
    padding: 10px 14px;
    border: 0;
    background: transparent;
    cursor: pointer;
    text-align: left;
    font: inherit;
}
.payment-search-suggestion:hover,
.payment-search-suggestion.is-active {
    background: #f1f5f9;
}
.payment-search-suggestion-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: #0f172a;
}
.payment-search-suggestion-meta {
    font-size: 0.75rem;
    color: #64748b;
}
.payment-search-empty {
    padding: 10px 14px;
    font-size: 0.8125rem;
    color: #64748b;
}
</style>

<?php
require_once __DIR__ . '/../../includes/payment_approval_dialog.php';
require_once __DIR__ . '/../../includes/admin_layout_end.php';
?>
