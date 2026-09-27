<?php
/**
 * Payment Logs - Admin ledger of money received (payments table).
 */

$pageTitle = 'Payment Logs';

require_once __DIR__ . '/../../config/database.php';

$logRows = [];
$totalCollected = 0;
$totalTransactions = 0;
$showingStart = 0;
$showingEnd = 0;
$filteredTotal = 0;
$currentPage = 1;
$totalPages = 1;
$perPage = 10;
$dbError = '';
$searchQuery = '';
$dateFilter = 'all';

try {
    $searchQuery = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
    $dateFilter = isset($_GET['date']) ? trim((string)$_GET['date']) : 'all';
    $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

    $payCols = [];
    foreach ($pdo->query('SHOW COLUMNS FROM payments') as $col) {
        $payCols[$col['Field']] = true;
    }

    $select = ['pay.id', 'pay.amount', 'pay.payment_date', 'pay.created_at', 'pay.billing_id'];
    $hasMethod = isset($payCols['payment_method']);
    $hasRef = isset($payCols['reference_number']);
    $hasNotes = isset($payCols['notes']);
    $hasCreatedBy = isset($payCols['created_by']);
    $hasPatientId = isset($payCols['patient_id']);

    if ($hasMethod) {
        $select[] = 'pay.payment_method';
    }
    if ($hasRef) {
        $select[] = 'pay.reference_number';
    }
    if ($hasNotes) {
        $select[] = 'pay.notes';
    }
    if ($hasCreatedBy) {
        $select[] = 'pay.created_by';
        $select[] = "TRIM(CONCAT_WS(' ', NULLIF(TRIM(u.first_name), ''), NULLIF(TRIM(u.last_name), ''))) AS recorded_by";
    }
    $select[] = 'p.first_name';
    $select[] = 'p.middle_name';
    $select[] = 'p.last_name';
    $select[] = 'p.suffix';
    $select[] = 'COALESCE(pay.billing_id, b.id) AS invoice_billing_id';
    $select[] = 'b.payment_status';

    $patientJoin = $hasPatientId
        ? 'LEFT JOIN patients p ON p.id = pay.patient_id'
        : 'LEFT JOIN billing bjoin ON bjoin.id = pay.billing_id LEFT JOIN patients p ON p.id = bjoin.patient_id';
    $billingJoin = 'LEFT JOIN billing b ON b.id = pay.billing_id';
    $userJoin = $hasCreatedBy ? 'LEFT JOIN users u ON u.id = pay.created_by' : '';

    $where = 'WHERE 1=1';
    $params = [];

    if ($searchQuery !== '') {
        $where .= " AND (
            CONCAT_WS(' ',
                NULLIF(TRIM(p.first_name), ''),
                NULLIF(TRIM(p.middle_name), ''),
                NULLIF(TRIM(p.last_name), ''),
                NULLIF(TRIM(p.suffix), '')
            ) LIKE :search
            OR pay.billing_id LIKE :searchId
            OR pay.id LIKE :searchId2
        ";
        if ($hasRef) {
            $where .= ' OR pay.reference_number LIKE :searchRef';
        }
        $where .= ')';
        $params[':search'] = '%' . $searchQuery . '%';
        $params[':searchId'] = '%' . $searchQuery . '%';
        $params[':searchId2'] = '%' . $searchQuery . '%';
        if ($hasRef) {
            $params[':searchRef'] = '%' . $searchQuery . '%';
        }
    }

    switch ($dateFilter) {
        case 'today':
            $where .= ' AND DATE(pay.payment_date) = CURDATE()';
            break;
        case 'week':
            $where .= ' AND pay.payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
            break;
        case 'month':
            $where .= ' AND pay.payment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
            break;
    }

    $from = "FROM payments pay $billingJoin $patientJoin $userJoin $where";

    $statsSql = "SELECT
            COALESCE(SUM(CASE
                WHEN LOWER(COALESCE(b.payment_status, '')) NOT IN ('cancelled', 'canceled', 'voided')
                THEN pay.amount ELSE 0
            END), 0) AS total_collected,
            COUNT(*) AS total_transactions
        $from";
    $statsStmt = $pdo->prepare($statsSql);
    foreach ($params as $key => $value) {
        $statsStmt->bindValue($key, $value);
    }
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $totalCollected = (float)($stats['total_collected'] ?? 0);
    $totalTransactions = (int)($stats['total_transactions'] ?? 0);
    $filteredTotal = $totalTransactions;
    $totalPages = max(1, (int)ceil($filteredTotal / $perPage));
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }
    $offset = ($currentPage - 1) * $perPage;
    $showingStart = $filteredTotal ? $offset + 1 : 0;
    $showingEnd = min($offset + $perPage, $filteredTotal);

    $listSql = 'SELECT ' . implode(', ', $select) . " $from ORDER BY pay.payment_date DESC, pay.id DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($listSql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $dbError = $e->getMessage();
    $logRows = [];
}

require_once __DIR__ . '/../../includes/admin_layout_start.php';
?>
            <div class="content-main">
                <div class="summary-cards">
                    <div class="summary-card card-collected">
                        <div class="summary-icon">
                            <img src="../../assets/icons/peso.png" alt="Total Collected">
                        </div>
                        <div class="summary-info">
                            <h3>&#8369;<?php echo number_format($totalCollected, 2); ?></h3>
                            <p>Money In</p>
                        </div>
                    </div>
                    <div class="summary-card card-transactions">
                        <div class="summary-icon">
                            <img src="../../assets/icons/transaction.png" alt="Total Transactions">
                        </div>
                        <div class="summary-info">
                            <h3><?php echo number_format($totalTransactions); ?></h3>
                            <p>Payment Entries</p>
                        </div>
                    </div>
                </div>

                <?php if ($dbError): ?>
                <div style="padding: 16px; background: #fee2e2; color: #dc2626; border: 1px solid #f87171; border-radius: 8px; margin-bottom: 16px;">
                    <strong>Database Error:</strong> <?php echo htmlspecialchars($dbError); ?>
                </div>
                <?php endif; ?>

                <form method="GET" class="search-filters">
                    <input type="text" class="search-input" name="search" placeholder="Search by patient, invoice, or reference..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <select class="filter-select" name="date">
                        <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>All Time</option>
                        <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php echo $dateFilter === 'month' ? 'selected' : ''; ?>>This Month</option>
                    </select>
                    <button type="submit" class="btn-primary" style="padding: 8px 16px;">Search</button>
                </form>

                <div class="page-header">
                    <h2>Payment Logs</h2>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Invoice #</th>
                                <th>Patient</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($logRows) === 0): ?>
                            <tr>
                                <td colspan="7" style="text-align:center;padding:40px;color:#6b7280;">No payment entries found.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($logRows as $row):
                                $billingId = (int)($row['invoice_billing_id'] ?? $row['billing_id'] ?? 0);
                                $invoiceNum = $billingId > 0 ? 'INV-' . str_pad((string)$billingId, 3, '0', STR_PAD_LEFT) : '—';
                                $fullName = trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? '') . ' ' . ($row['suffix'] ?? ''));
                                $fullName = preg_replace('/\s+/', ' ', $fullName);
                                $method = trim((string)($row['payment_method'] ?? ''));
                                $ref = trim((string)($row['reference_number'] ?? ''));
                                $recordedBy = trim((string)($row['recorded_by'] ?? ''));
                                $payDate = $row['payment_date'] ?? $row['created_at'] ?? '';
                                $billStatus = strtolower((string)($row['payment_status'] ?? ''));
                                $isVoided = in_array($billStatus, ['cancelled', 'canceled', 'voided'], true);
                            ?>
                            <tr>
                                <td><?php echo $payDate !== '' ? htmlspecialchars(substr((string)$payDate, 0, 10)) : 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($invoiceNum); ?></td>
                                <td><?php echo $fullName !== '' ? htmlspecialchars($fullName) : '<span style="color:#6b7280;">Unknown Patient</span>'; ?></td>
                                <td>
                                    &#8369;<?php echo number_format((float)($row['amount'] ?? 0), 2); ?>
                                    <?php if ($isVoided): ?>
                                    <div style="font-size:11px;font-weight:700;color:#dc2626;margin-top:2px;">Voided — not in Money In</div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $method !== '' ? htmlspecialchars($method) : '—'; ?></td>
                                <td><?php echo $ref !== '' ? htmlspecialchars($ref) : '—'; ?></td>
                                <td><?php echo $recordedBy !== '' ? htmlspecialchars($recordedBy) : '—'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($filteredTotal > 0): ?>
                <div class="pagination">
                    <span class="pagination-info">Showing <?php echo $showingStart; ?>-<?php echo $showingEnd; ?> of <?php echo $filteredTotal; ?> entries</span>
                    <div class="pagination-buttons">
                        <?php
                        $queryString = $_GET;
                        unset($queryString['page'], $queryString['__pjax'], $queryString['_pjax']);
                        $baseUrl = '?' . http_build_query($queryString);
                        if (!empty($queryString)) {
                            $baseUrl .= '&';
                        }
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

<style>
.summary-cards .summary-card {
    position: relative;
    overflow: hidden;
}
.summary-cards .summary-icon,
.summary-cards .summary-info {
    position: relative;
    z-index: 2;
}
.summary-cards .summary-icon {
    background: #d3e0fe;
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
.summary-cards .summary-card.card-transactions::after {
    background-image: url('../../assets/icons/transaction.png');
}
</style>
<?php require_once __DIR__ . '/../../includes/admin_layout_end.php'; ?>
