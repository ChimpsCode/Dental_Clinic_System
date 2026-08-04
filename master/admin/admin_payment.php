<?php
/**
 * Payment - Admin page for viewing and managing payments
 * Features: Payment History, Record Payment, Set Due Date, Print Invoice, Edit Amount
 */

$pageTitle = 'Payment';

require_once __DIR__ . '/../../config/database.php';

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

try {
    $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $dateFilter = isset($_GET['date']) ? trim($_GET['date']) : 'all';
    $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

    $whereClause = "WHERE 1=1";
    $params = [];

    if ($searchQuery !== '') {
        $whereClause .= " AND (
            CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.middle_name,''), ' ', COALESCE(p.last_name,'')) LIKE :search
            OR b.id LIKE :searchId
        )";
        $params[':search'] = '%' . $searchQuery . '%';
        $params[':searchId'] = '%' . $searchQuery . '%';
    }

    if ($statusFilter === 'paid') {
        $whereClause .= " AND b.payment_status = 'Paid'";
    } elseif ($statusFilter === 'partial') {
        $whereClause .= " AND b.payment_status = 'Partial'";
    } elseif ($statusFilter === 'unpaid') {
        $whereClause .= " AND b.payment_status IN ('Unpaid', 'Overdue')";
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

    $countSql = "SELECT COUNT(DISTINCT b.id) as total FROM billing b LEFT JOIN patients p ON p.id = b.patient_id $whereClause";
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
            b.balance,
            b.payment_status AS payment_status,
            b.billing_date,
            b.due_date,
            p.first_name,
            p.middle_name,
            p.last_name,
            p.suffix,
            p.phone,
            svc.name AS service_name,
            COALESCE(q.treatment_type, svc.name, 'General Service') AS treatment_type,
            q.id AS queue_id
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
    $billingRecords = [];
    $dbError = $e->getMessage();
}

require_once __DIR__ . '/../../includes/admin_layout_start.php';
?>
            <div class="content-main">

                <!-- Stats Cards -->
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
                </div>

                <?php if ($dbError): ?>
                <div style="padding: 16px; background: #fee2e2; color: #dc2626; border: 1px solid #f87171; border-radius: 8px; margin-bottom: 16px; font-family: sans-serif;">
                    <strong>Database Error:</strong> <?php echo htmlspecialchars($dbError); ?>
                </div>
                <?php endif; ?>

                <!-- Search and Filter -->
                <form method="GET" class="search-filters">
                    <input type="text" class="search-input" placeholder="Search by patient name or invoice..." name="search" value="<?php echo htmlspecialchars($searchQuery); ?>">
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
                                $statusRaw = $record['payment_status'];
                                if (strtolower($statusRaw) === 'paid') {
                                    $status = 'paid';
                                } elseif (strtolower($statusRaw) === 'partial') {
                                    $status = 'partial';
                                } else {
                                    $status = 'unpaid';
                                }
                                $isOverdue = !empty($record['due_date']) && $record['due_date'] < date('Y-m-d') && $record['balance'] > 0;
                                $displayStatus = $isOverdue ? 'overdue' : $status;
                                $isPaid = ($status === 'paid');
                                $paidAmt = (float)($record['paid_amount'] ?? 0);
                                $balanceAmt = (float)($record['balance'] ?? 0);
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
                                <td>&#8369;<?php echo number_format($record['total_amount'], 2); ?></td>
                                <td>&#8369;<?php echo number_format($balanceAmt, 2); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($record['billing_date'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $displayStatus; ?>">
                                        <?php if ($isOverdue): ?>
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
                                        data-is-paid="<?php echo $isPaid ? '1' : '0'; ?>">
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
                        unset($queryString['page']);
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

            <!-- View Payment Details Modal -->
            <div id="viewPaymentModal" class="modal-overlay" style="display: none;">
                <div class="modal-backdrop"></div>
                <div class="modal" style="max-width: 500px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600;">&#128176; Payment Details</h2>
                        <button onclick="closePaymentModal('viewPaymentModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">&times;</button>
                    </div>
                    <div id="viewPaymentContent"></div>
                </div>
            </div>

            <!-- Mark as Paid Confirmation Modal -->
            <div id="confirmPaymentModal" class="modal-overlay" style="display: none;">
                <div class="modal-backdrop"></div>
                <div class="modal" style="max-width: 450px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600;">Confirm Payment</h2>
                        <button onclick="closePaymentModal('confirmPaymentModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to mark this as <strong>Paid</strong>?</p>
                        <div class="payment-details">
                            <div class="detail-row">
                                <span class="detail-label">Treatment:</span>
                                <span class="detail-value" id="confirmPaymentTreatment">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Total Amount:</span>
                                <span class="detail-value" id="confirmPaymentTotalAmount">&#8369;0.00</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Already Paid:</span>
                                <span class="detail-value" id="confirmPaymentPaidSoFar">&#8369;0.00</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Balance to Pay:</span>
                                <span class="detail-value" id="confirmPaymentBalance" style="font-weight: 700; color: #059669;">&#8369;0.00</span>
                            </div>
                        </div>
                        <input type="hidden" id="confirmPaymentQueueId">
                        <input type="hidden" id="confirmPaymentPatientId">
                        <input type="hidden" id="confirmPaymentBillingId">
                        <input type="hidden" id="confirmPaymentAmountValue">
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                        <button type="button" onclick="closePaymentModal('confirmPaymentModal')" style="padding: 10px 20px; background: white; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer;">Cancel</button>
                        <button type="button" onclick="confirmMarkPaid()" style="padding: 10px 20px; background: #0ea5e9; color: white; border: none; border-radius: 8px; cursor: pointer;">Confirm Payment</button>
                    </div>
                </div>
            </div>

            <!-- Record Payment Modal -->
            <div id="recordPaymentModal" class="modal-overlay" style="display: none;">
                <div class="modal-backdrop"></div>
                <div class="modal" style="max-width: 450px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600;">Record Payment</h2>
                        <button onclick="closePaymentModal('recordPaymentModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="payment-details">
                            <div class="detail-row">
                                <span class="detail-label">Treatment:</span>
                                <span class="detail-value" id="recordPaymentTreatment">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Total Amount:</span>
                                <span class="detail-value" id="recordPaymentTotalAmount">&#8369;0.00</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Paid So Far:</span>
                                <span class="detail-value" id="recordPaymentPaidSoFar">&#8369;0.00</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Balance:</span>
                                <span class="detail-value" id="recordPaymentBalance">&#8369;0.00</span>
                            </div>
                        </div>
                        <div style="margin-top: 1rem;">
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 6px;">Payment Amount</label>
                            <input type="number" id="recordPaymentAmount" step="0.01" min="0.01" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;">
                        </div>
                        <div style="margin-top: 1rem;">
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 6px;">Payment Method</label>
                            <select id="recordPaymentMethod" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;">
                                <option value="cash">Cash</option>
                                <option value="gcash">GCash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="installment">Installment</option>
                            </select>
                        </div>
                        <input type="hidden" id="recordPaymentQueueId">
                        <input type="hidden" id="recordPaymentPatientId">
                        <input type="hidden" id="recordPaymentBillingId">
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                        <button type="button" onclick="closePaymentModal('recordPaymentModal')" style="padding: 10px 20px; background: white; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer;">Cancel</button>
                        <button type="button" onclick="confirmRecordPayment()" style="padding: 10px 20px; background: #0ea5e9; color: white; border: none; border-radius: 8px; cursor: pointer;">Record Payment</button>
                    </div>
                </div>
            </div>

            <!-- Payment History Modal -->
            <div id="paymentHistoryModal" class="modal-overlay" style="display: none;">
                <div class="modal-backdrop"></div>
                <div class="modal" style="max-width: 550px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600;">Payment History</h2>
                        <button onclick="closePaymentModal('paymentHistoryModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">&times;</button>
                    </div>
                    <div id="paymentHistoryContent">
                        <p style="text-align: center; color: #6b7280; padding: 20px;">Loading...</p>
                    </div>
                </div>
            </div>

            <!-- Set Due Date Modal -->
            <div id="setDueDateModal" class="modal-overlay" style="display: none;">
                <div class="modal-backdrop"></div>
                <div class="modal" style="max-width: 400px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600;">Set Due Date</h2>
                        <button onclick="closePaymentModal('setDueDateModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 8px;">Due Date</label>
                            <input type="date" id="setDueDateInput" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;">
                        </div>
                        <input type="hidden" id="setDueDateBillingId">
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                        <button type="button" onclick="closePaymentModal('setDueDateModal')" style="padding: 10px 20px; background: white; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer;">Cancel</button>
                        <button type="button" onclick="confirmSetDueDate()" style="padding: 10px 20px; background: #0ea5e9; color: white; border: none; border-radius: 8px; cursor: pointer;">Save</button>
                    </div>
                </div>
            </div>

            <!-- Edit Payment Modal -->
            <div id="editPaymentModal" class="modal-overlay" style="display: none;">
                <div class="modal-backdrop"></div>
                <div class="modal" style="max-width: 450px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600;">&#9998; Edit Payment</h2>
                        <button onclick="closePaymentModal('editPaymentModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 8px;">Patient Name</label>
                            <input type="text" id="editPaymentPatient" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; background: #f3f4f6;" readonly>
                        </div>
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 8px;">Services</label>
                            <input type="text" id="editPaymentServices" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; background: #f3f4f6;" readonly>
                        </div>
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 8px;">New Amount (&#8369;)</label>
                            <input type="number" id="editPaymentAmount" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;" min="0" step="0.01">
                        </div>
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 8px;">Reason for Change</label>
                            <input type="text" id="editPaymentReason" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;" placeholder="e.g., Discount applied, Additional procedure">
                        </div>
                        <input type="hidden" id="editPaymentBillingId">
                        <input type="hidden" id="editPaymentInvoiceId">
                        <div style="display: flex; justify-content: flex-end; gap: 12px;">
                            <button type="button" onclick="closePaymentModal('editPaymentModal')" style="padding: 10px 20px; background: white; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer;">Cancel</button>
                            <button type="button" onclick="savePaymentEdit()" style="padding: 10px 20px; background: #0ea5e9; color: white; border: none; border-radius: 8px; cursor: pointer;">Save Changes</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Print Modal -->
            <div id="printPaymentModal" class="modal-overlay" style="display: none;">
                <div class="modal-backdrop"></div>
                <div class="modal" style="max-width: 450px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600;">&#128424; Print Invoice</h2>
                        <button onclick="closePaymentModal('printPaymentModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">&times;</button>
                    </div>
                    <div id="printPaymentContent"></div>
                    <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                        <button onclick="closePaymentModal('printPaymentModal')" style="padding: 10px 20px; background: white; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer;">Close</button>
                        <button onclick="confirmPrint()" style="padding: 10px 20px; background: #0ea5e9; color: white; border: none; border-radius: 8px; cursor: pointer;">&#128424; Print</button>
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
                .modal-backdrop { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
                .modal { background: white; border-radius: 16px; padding: 24px; max-width: 500px; width: 90%; max-height: 85vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); position: relative; z-index: 100002; }
                .payment-details { background: #f9fafb; border-radius: 8px; padding: 16px; }
                .payment-details .detail-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
                .payment-details .detail-row:last-child { margin-bottom: 0; }
                .payment-details .detail-label { color: #6b7280; font-size: 0.875rem; }
                .payment-details .detail-value { font-weight: 600; font-size: 0.95rem; }
                .table-container { height: fit-content; }
                .data-table { min-width: 800px; }
                .page-header { margin-bottom: 0px; }
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
                    var status = button.dataset.status;
                    var billingId = button.dataset.billingId || '0';
                    var patientId = button.dataset.patientId || '0';
                    var queueId = button.dataset.queueId || '0';
                    var amount = button.dataset.amount || '0';
                    var paidAmount = button.dataset.paidAmount || '0';
                    var balance = button.dataset.balance || '0';
                    var treatment = button.dataset.treatment || '';
                    var isPaid = button.dataset.isPaid === '1';

                    var html = '<a href="javascript:void(0)" data-action="view" data-invoice-id="' + invoiceId + '">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>' +
                        'View Details</a>' +
                        '<a href="javascript:void(0)" data-action="payment-history" data-billing-id="' + billingId + '">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' +
                        'Payment History</a>';

                    if (!isPaid) {
                        html += '<a href="javascript:void(0)" data-action="record-payment" data-billing-id="' + billingId + '" data-queue-id="' + queueId + '" data-patient-id="' + patientId + '" data-amount="' + amount + '" data-treatment="' + encodeURIComponent(treatment) + '" data-paid-amount="' + paidAmount + '" data-balance="' + balance + '">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>' +
                            'Record Payment</a>' +
                            '<a href="javascript:void(0)" data-action="mark-paid" data-billing-id="' + billingId + '" data-queue-id="' + queueId + '" data-patient-id="' + patientId + '" data-amount="' + amount + '" data-treatment="' + encodeURIComponent(treatment) + '" data-paid-amount="' + paidAmount + '" data-balance="' + balance + '">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>' +
                            'Mark as Paid</a>' +
                            '<a href="javascript:void(0)" data-action="set-due-date" data-billing-id="' + billingId + '">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>' +
                            'Set Due Date</a>' +
                            '<a href="javascript:void(0)" data-action="edit" data-invoice-id="' + invoiceId + '">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>' +
                            'Edit Amount</a>';
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
                    if (!paymentKebabDropdown) createPaymentKebabDropdown();
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
                        case 'view': viewPayment(invoiceId); break;
                        case 'mark-paid': openMarkPaid(invoiceId); break;
                        case 'edit': editPayment(invoiceId); break;
                        case 'print': printPayment(invoiceId); break;
                        case 'record-payment': openRecordPayment(link); break;
                        case 'payment-history': openPaymentHistory(link.dataset.billingId); break;
                        case 'set-due-date': openSetDueDate(link.dataset.billingId); break;
                    }
                }

                document.addEventListener('click', function(e) {
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
                });

                // View Payment Details
                function viewPayment(invoiceId) {
                    var p = paymentData[invoiceId];
                    if (!p) return;

                    var statusText = p.status.toLowerCase();
                    var statusClass = statusText === 'paid' ? 'paid' : (statusText === 'partial' ? 'partial' : 'unpaid');
                    var statusLabel = statusText.charAt(0).toUpperCase() + statusText.slice(1);

                    var html = '<div style="background: #f9fafb; border-radius: 8px; padding: 16px; margin-bottom: 16px;">' +
                        '<div style="display: flex; justify-content: space-between; margin-bottom: 12px;"><span style="color: #6b7280; font-size: 0.875rem;">Invoice #</span><span style="font-weight: 600;">' + p.id + '</span></div>' +
                        '<div style="display: flex; justify-content: space-between; margin-bottom: 12px;"><span style="color: #6b7280; font-size: 0.875rem;">Patient</span><span style="font-weight: 600;">' + p.patient + '</span></div>' +
                        '<div style="display: flex; justify-content: space-between; margin-bottom: 12px;"><span style="color: #6b7280; font-size: 0.875rem;">Phone</span><span>' + (p.phone || 'N/A') + '</span></div>' +
                        '</div>' +
                        '<div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 16px; margin-bottom: 16px;">' +
                        '<div style="display: flex; justify-content: space-between; margin-bottom: 12px;"><span style="color: #6b7280; font-size: 0.875rem;">Services</span><span>' + p.services + '</span></div>' +
                        '<div style="display: flex; justify-content: space-between; margin-bottom: 12px;"><span style="color: #6b7280; font-size: 0.875rem;">Total Amount</span><span style="font-weight: 700; font-size: 1.1rem;">&#8369;' + parseFloat(p.amount).toLocaleString('en-PH', {minimumFractionDigits: 2}) + '</span></div>' +
                        '<div style="display: flex; justify-content: space-between; margin-bottom: 12px;"><span style="color: #6b7280; font-size: 0.875rem;">Paid Amount</span><span style="font-weight: 600; color: #059669;">&#8369;' + parseFloat(p.paid_amount).toLocaleString('en-PH', {minimumFractionDigits: 2}) + '</span></div>' +
                        '<div style="display: flex; justify-content: space-between; margin-bottom: 12px;"><span style="color: #6b7280; font-size: 0.875rem;">Balance</span><span style="font-weight: 700; color: ' + (p.balance > 0 ? '#dc2626' : '#059669') + ';">&#8369;' + parseFloat(p.balance).toLocaleString('en-PH', {minimumFractionDigits: 2}) + '</span></div>' +
                        '<div style="display: flex; justify-content: space-between; margin-bottom: 12px;"><span style="color: #6b7280; font-size: 0.875rem;">Date</span><span>' + p.date + '</span></div>' +
                        '<div style="display: flex; justify-content: space-between; margin-bottom: 12px;"><span style="color: #6b7280; font-size: 0.875rem;">Due Date</span><span>' + (p.dueDate || 'N/A') + '</span></div>' +
                        '<div style="display: flex; justify-content: space-between;"><span style="color: #6b7280; font-size: 0.875rem;">Status</span><span class="status-badge ' + statusClass + '">' + statusLabel + '</span></div>' +
                        '</div>';

                    document.getElementById('viewPaymentContent').innerHTML = html;
                    document.getElementById('viewPaymentModal').style.display = 'flex';
                }

                // Mark as Paid - open confirmation modal
                function openMarkPaid(invoiceId) {
                    var p = paymentData[invoiceId];
                    if (!p) return;

                    document.getElementById('confirmPaymentQueueId').value = p.queue_id || 0;
                    document.getElementById('confirmPaymentPatientId').value = p.patient_id;
                    document.getElementById('confirmPaymentBillingId').value = p.billing_id;
                    document.getElementById('confirmPaymentAmountValue').value = p.balance;
                    document.getElementById('confirmPaymentTreatment').textContent = p.services;
                    document.getElementById('confirmPaymentTotalAmount').textContent = '\u20B1' + parseFloat(p.amount).toLocaleString('en-PH', {minimumFractionDigits: 2});
                    document.getElementById('confirmPaymentPaidSoFar').textContent = '\u20B1' + parseFloat(p.paid_amount).toLocaleString('en-PH', {minimumFractionDigits: 2});
                    document.getElementById('confirmPaymentBalance').textContent = '\u20B1' + parseFloat(p.balance).toLocaleString('en-PH', {minimumFractionDigits: 2});

                    document.getElementById('confirmPaymentModal').style.display = 'flex';
                }

                function confirmMarkPaid() {
                    var queueId = document.getElementById('confirmPaymentQueueId').value;
                    var patientId = document.getElementById('confirmPaymentPatientId').value;
                    var billingId = document.getElementById('confirmPaymentBillingId').value;
                    var amount = document.getElementById('confirmPaymentAmountValue').value;

                    fetch('../shared/billing_actions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'mark_paid', billing_id: billingId, queue_id: queueId, patient_id: patientId, amount: amount })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            closePaymentModal('confirmPaymentModal');
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(function() { alert('Error processing payment. Please try again.'); });
                }

                // Record Payment
                function openRecordPayment(link) {
                    var billingId = link.dataset.billingId;
                    var queueId = link.dataset.queueId;
                    var patientId = link.dataset.patientId;
                    var amount = parseFloat(link.dataset.amount) || 0;
                    var treatment = decodeURIComponent(link.dataset.treatment || '');
                    var paidAmount = parseFloat(link.dataset.paidAmount) || 0;
                    var balance = parseFloat(link.dataset.balance) || amount;

                    document.getElementById('recordPaymentQueueId').value = queueId || 0;
                    document.getElementById('recordPaymentPatientId').value = patientId || 0;
                    document.getElementById('recordPaymentBillingId').value = billingId || 0;
                    document.getElementById('recordPaymentTreatment').textContent = treatment || 'General Checkup';
                    document.getElementById('recordPaymentTotalAmount').textContent = '\u20B1' + amount.toLocaleString('en-PH', {minimumFractionDigits: 2});
                    document.getElementById('recordPaymentPaidSoFar').textContent = '\u20B1' + paidAmount.toLocaleString('en-PH', {minimumFractionDigits: 2});
                    document.getElementById('recordPaymentBalance').textContent = '\u20B1' + balance.toLocaleString('en-PH', {minimumFractionDigits: 2});
                    document.getElementById('recordPaymentAmount').value = balance;
                    document.getElementById('recordPaymentMethod').value = 'cash';
                    document.getElementById('recordPaymentModal').style.display = 'flex';
                }

                function confirmRecordPayment() {
                    var billingId = document.getElementById('recordPaymentBillingId').value;
                    var queueId = document.getElementById('recordPaymentQueueId').value;
                    var patientId = document.getElementById('recordPaymentPatientId').value;
                    var amount = document.getElementById('recordPaymentAmount').value;
                    var paymentMethod = document.getElementById('recordPaymentMethod').value;

                    if (!amount || parseFloat(amount) <= 0) { alert('Please enter a valid payment amount.'); return; }

                    fetch('../shared/billing_actions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'record_payment', billing_id: billingId, queue_id: queueId, patient_id: patientId, amount: amount, payment_method: paymentMethod })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) { closePaymentModal('recordPaymentModal'); location.reload(); }
                        else { alert('Error: ' + data.message); }
                    })
                    .catch(function() { alert('Error recording payment. Please try again.'); });
                }

                // Payment History
                function openPaymentHistory(billingId) {
                    if (!billingId || billingId === '0') { alert('No billing record found.'); return; }
                    document.getElementById('paymentHistoryContent').innerHTML = '<p style="text-align: center; color: #6b7280; padding: 20px;">Loading...</p>';
                    document.getElementById('paymentHistoryModal').style.display = 'flex';

                    fetch('../shared/billing_actions.php?action=get_payments&billing_id=' + billingId)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success && data.payments && data.payments.length > 0) {
                            var html = '<table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;"><thead><tr style="border-bottom: 2px solid #e5e7eb;"><th style="text-align: left; padding: 10px 8px; color: #6b7280;">Date</th><th style="text-align: right; padding: 10px 8px; color: #6b7280;">Amount</th><th style="text-align: left; padding: 10px 8px; color: #6b7280;">Method</th><th style="text-align: left; padding: 10px 8px; color: #6b7280;">Reference</th></tr></thead><tbody>';
                            data.payments.forEach(function(p) {
                                html += '<tr style="border-bottom: 1px solid #f3f4f6;">' +
                                    '<td style="padding: 10px 8px;">' + (p.payment_date || 'N/A') + '</td>' +
                                    '<td style="padding: 10px 8px; text-align: right; font-weight: 600; color: #059669;">\u20B1' + parseFloat(p.amount).toLocaleString('en-PH', {minimumFractionDigits: 2}) + '</td>' +
                                    '<td style="padding: 10px 8px;">' + (p.payment_method || 'N/A') + '</td>' +
                                    '<td style="padding: 10px 8px; color: #6b7280;">' + (p.reference_number || '-') + '</td></tr>';
                            });
                            html += '</tbody></table>';
                            document.getElementById('paymentHistoryContent').innerHTML = html;
                        } else {
                            document.getElementById('paymentHistoryContent').innerHTML = '<p style="text-align: center; color: #6b7280; padding: 20px;">No payment records found.</p>';
                        }
                    })
                    .catch(function() {
                        document.getElementById('paymentHistoryContent').innerHTML = '<p style="text-align: center; color: #dc2626; padding: 20px;">Error loading payment history.</p>';
                    });
                }

                // Set Due Date
                function openSetDueDate(billingId) {
                    if (!billingId || billingId === '0') { alert('No billing record found.'); return; }
                    document.getElementById('setDueDateBillingId').value = billingId;

                    fetch('../shared/billing_actions.php?action=get_billing&billing_id=' + billingId)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var dueDate = '';
                        if (data.success && data.billing && data.billing.due_date) {
                            dueDate = data.billing.due_date;
                        } else {
                            var d = new Date(); d.setDate(d.getDate() + 30);
                            dueDate = d.toISOString().split('T')[0];
                        }
                        document.getElementById('setDueDateInput').value = dueDate;
                        document.getElementById('setDueDateModal').style.display = 'flex';
                    })
                    .catch(function() {
                        var d = new Date(); d.setDate(d.getDate() + 30);
                        document.getElementById('setDueDateInput').value = d.toISOString().split('T')[0];
                        document.getElementById('setDueDateModal').style.display = 'flex';
                    });
                }

                function confirmSetDueDate() {
                    var billingId = document.getElementById('setDueDateBillingId').value;
                    var dueDate = document.getElementById('setDueDateInput').value;
                    if (!dueDate) { alert('Please select a due date.'); return; }

                    fetch('../shared/billing_actions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'set_due_date', billing_id: billingId, due_date: dueDate })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) { closePaymentModal('setDueDateModal'); location.reload(); }
                        else { alert('Error: ' + data.message); }
                    })
                    .catch(function() { alert('Error setting due date.'); });
                }

                // Edit Payment
                function editPayment(invoiceId) {
                    var p = paymentData[invoiceId];
                    if (!p) return;
                    document.getElementById('editPaymentInvoiceId').value = invoiceId;
                    document.getElementById('editPaymentBillingId').value = p.billing_id;
                    document.getElementById('editPaymentPatient').value = p.patient;
                    document.getElementById('editPaymentServices').value = p.services;
                    document.getElementById('editPaymentAmount').value = p.amount;
                    document.getElementById('editPaymentReason').value = '';
                    document.getElementById('editPaymentModal').style.display = 'flex';
                }

                function savePaymentEdit() {
                    var invoiceId = document.getElementById('editPaymentInvoiceId').value;
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
                            closePaymentModal('editPaymentModal');
                            location.reload();
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

                // Close modal
                function closePaymentModal(modalId) {
                    document.getElementById(modalId).style.display = 'none';
                }

                // Close modals on backdrop click
                document.querySelectorAll('.modal-overlay').forEach(function(modal) {
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) modal.style.display = 'none';
                    });
                });

                // Close on Escape
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        if (typeof closePaymentKebabDropdown === 'function') closePaymentKebabDropdown();
                        document.querySelectorAll('.modal-overlay').forEach(function(modal) { modal.style.display = 'none'; });
                    }
                });
            </script>

<style>
@media (max-width: 768px) {
    .summary-cards { grid-template-columns: 1fr 1fr !important; }
    .summary-card { padding: 16px !important; }
}
@media (max-width: 480px) {
    .summary-cards { grid-template-columns: 1fr 1fr !important; gap: 10px !important; }
    .summary-card { padding: 12px !important; }
}
</style>

<?php
require_once __DIR__ . '/../../includes/admin_layout_end.php';
?>
