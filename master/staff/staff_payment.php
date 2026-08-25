<?php
/**
 * Staff Payment - View and manage payments for patients from the queue
 * Shows patients from queue with their payment status
 * Staff can mark patients as paid after dentist completes treatment
 */

$pageTitle = 'Payment';

require_once __DIR__ . '/../../config/database.php';

// Pagination settings
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;












// Get payment records from queue with payment status
try {
    $conn = $pdo;
    
    // Force PDO to throw errors so they don't fail silently
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

   // Get filter parameters
    $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $dateFilter = isset($_GET['date']) ? trim($_GET['date']) : 'all';
    
    // Billing-based listing so staff can see all billed patients, not only active queue rows.
    $whereClause = "WHERE 1=1";
    $params = [];
    
    // Apply search filter
    if (!empty($searchQuery)) {
        $whereClause .= " AND (CONCAT(p.first_name, ' ', p.last_name) LIKE :search 
                  OR q.treatment_type LIKE :search2)";
        $params[':search'] = "%$searchQuery%";
        $params[':search2'] = "%$searchQuery%";
    }
    
    // Apply payment status filter
    if (!empty($statusFilter)) {
        if ($statusFilter === 'paid') {
            $whereClause .= " AND b.payment_status = 'Paid'";
        } elseif ($statusFilter === 'unpaid') {
            $whereClause .= " AND (b.id IS NULL OR b.payment_status IS NULL OR b.payment_status IN ('Unpaid', 'Partial', 'Overdue'))";
        }
    }
    
    // Apply date filter
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
        default:
            // All time - no filter
            break;
    }
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) FROM billing b
                 LEFT JOIN patients p ON b.patient_id = p.id
                 LEFT JOIN queue q ON CAST(q.patient_id AS UNSIGNED) = b.patient_id AND DATE(q.created_at) = DATE(b.billing_date)
                 $whereClause";
    $countStmt = $conn->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalRecords / $itemsPerPage));
    
    // Ensure current page is valid
    if ($currentPage > $totalPages) $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    // Calculate showing range
    $showingStart = $totalRecords > 0 ? $offset + 1 : 0;
    $showingEnd = min($offset + $itemsPerPage, $totalRecords);
    
    // Build the query with pagination
    $sql = "SELECT 
                q.id as queue_id,
                q.queue_number,
                b.patient_id,
                q.treatment_type,
                q.teeth_numbers,
                COALESCE(q.status, 'Unknown') as queue_status,
                COALESCE(q.created_at, b.billing_date) as queue_date,
                q.notes as queue_notes,
                CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                p.phone as patient_phone,
                b.id as billing_id,
                CONCAT('INV-', LPAD(b.id, 4, '0')) as invoice_number,
                COALESCE(b.total_amount, 0) as amount,
                COALESCE(b.payment_status, 'Unpaid') as payment_status,
                CASE
                    WHEN b.payment_status = 'Paid' THEN 'paid'
                    WHEN b.payment_status = 'Partial' THEN 'partial'
                    ELSE 'unpaid'
                END AS effective_payment_status,
                b.billing_date,
                b.paid_amount,
                b.notes as billing_notes,
                b.subtotal_amount,
                b.discount_type,
                b.discount_amount
            FROM billing b
            LEFT JOIN patients p ON b.patient_id = p.id
            LEFT JOIN queue q ON CAST(q.patient_id AS UNSIGNED) = b.patient_id AND DATE(q.created_at) = DATE(b.billing_date)
            $whereClause
            ORDER BY b.billing_date DESC, b.id DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $paymentRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    







    // Get summary statistics from the same billing/payment data source used by the table.
    // This keeps the cards in sync after staff marks payments as paid.
    $statsSql = "
        SELECT 
            (SELECT COALESCE(SUM(p.amount), 0)
             FROM payments p
             WHERE DATE(p.payment_date) = CURDATE()) AS total_collected_today,
             
            (SELECT COALESCE(SUM(b.total_amount), 0)
             FROM billing b
             WHERE DATE(b.billing_date) = CURDATE()
               AND b.payment_status != 'Paid') AS pending_today,
               
            (SELECT COUNT(*)
             FROM billing b
             WHERE DATE(b.billing_date) = CURDATE()
               AND b.payment_status = 'Paid') AS completed_today,
               
            (SELECT COUNT(*)
             FROM billing b
             WHERE DATE(b.billing_date) = CURDATE()
               AND b.payment_status != 'Paid') AS unpaid_today
    ";
    $statsStmt = $conn->query($statsSql);
    $stats = $statsStmt ? $statsStmt->fetch(PDO::FETCH_ASSOC) : [
        'total_collected_today' => 0, 'pending_today' => 0, 'completed_today' => 0, 'unpaid_today' => 0
    ];
    
    // Get services for calculating amounts
    $servicesStmt = $conn->query("SELECT id, name, price FROM services");
    $servicesList = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);
    $servicesMap = [];
    foreach ($servicesList as $service) {
        $servicesMap[strtolower($service['name'])] = $service['price'];
    }
    
} catch (Exception $e) {
    // FORCES THE ERROR TO SHOW ON SCREEN INSTEAD OF FAILING SILENTLY
    die("<div style='padding: 20px; background: #fee2e2; color: #dc2626; border: 1px solid #f87171; border-radius: 8px; margin: 20px; font-family: sans-serif;'>
        <h3 style='margin-top:0;'>Database Error Detected:</h3>
        <p>" . htmlspecialchars($e->getMessage()) . "</p>
        <p><em>Please take a screenshot of this red box so we can fix the exact column causing the issue!</em></p>
    </div>");
}














// Helper function to calculate amount from treatment type
function calculateAmount($treatmentType, $servicesMap) {
    if (empty($treatmentType)) return 0;
    
    $total = 0;
    $treatments = explode(',', $treatmentType);
    foreach ($treatments as $treatment) {
        $treatment = strtolower(trim($treatment));
        if (isset($servicesMap[$treatment])) {
            $total += $servicesMap[$treatment];
        }
    }
    return $total > 0 ? $total : 500; // Default amount if service not found
}

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$projectBaseUrl = '/';
if (!empty($scriptName)) {
    $projectBaseUrl = rtrim(dirname(dirname(dirname($scriptName))), '/') . '/';
}

$receiptHtmlTemplate = @file_get_contents(__DIR__ . '/../../receipt.html');
$receiptCssTemplate = @file_get_contents(__DIR__ . '/../../receipt.css');

if ($receiptHtmlTemplate === false) {
    $receiptHtmlTemplate = '';
}
if ($receiptCssTemplate === false) {
    $receiptCssTemplate = '';
}

require_once __DIR__ . '/../../includes/staff_layout_start.php';
?>
            <div class="content-main">
                

                <!-- Stats Cards -->
                <div class="summary-cards">
                    <div class="summary-card card-collected">
                        <div class="summary-icon">
                            <img src="../../assets/icons/peso.png" alt="Collected Today">
                        </div>
                        <div class="summary-info">
                            <h3>P<?php echo number_format($stats['total_collected_today'] ?? 0, 2); ?></h3>
                            <p>Collected Today</p>
                        </div>
                    </div>
                    <div class="summary-card card-pending">
                        <div class="summary-icon">
                            <img src="../../assets/icons/pending.png" alt="Pending Today">
                        </div>
                        <div class="summary-info">
                            <h3>P<?php echo number_format($stats['pending_today'] ?? 0, 2); ?></h3>
                            <p>Pending Today</p>
                        </div>
                    </div>
                    <div class="summary-card card-completed">
                        <div class="summary-icon">
                            <img src="../../assets/icons/check.png" alt="Completed Today">
                        </div>
                        <div class="summary-info">
                            <h3><?php echo number_format($stats['completed_today'] ?? 0); ?></h3>
                            <p>Completed Today</p>
                        </div>
                    </div>
                    <div class="summary-card card-unpaid">
                        <div class="summary-icon">
                            <img src="../../assets/icons/unpaid.png" alt="Unpaid Today">
                        </div>
                        <div class="summary-info">
                            <h3><?php echo number_format($stats['unpaid_today'] ?? 0); ?></h3>
                            <p>Unpaid Today</p>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                
                    <form method="GET" class="filter-form" id="filterForm">
                        <div class="search-input-wrap">
                            <span class="search-input-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="m19.6 21l-6.3-6.3q-.75.6-1.725.95T9.5 16q-2.725 0-4.612-1.888T3 9.5t1.888-4.612T9.5 3t4.613 1.888T16 9.5q0 1.1-.35 2.075T14.7 13.3l6.3 6.3zM9.5 14q1.875 0 3.188-1.312T14 9.5t-1.312-3.187T9.5 5T6.313 6.313T5 9.5t1.313 3.188T9.5 14"/>
                                </svg>
                            </span>
                            <input type="text" class="search-input" placeholder="Search" 
                                   name="search" id="paymentSearch" value="<?php echo htmlspecialchars($searchQuery); ?>">
                        </div>
                        <select class="filter-select" name="status" id="paymentStatus" onchange="document.getElementById('filterForm').submit()">
                            <option value="">All Status</option>
                            <option value="paid" <?php echo $statusFilter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="unpaid" <?php echo $statusFilter === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                        </select>
                        <select class="filter-select" name="date" id="dateRange" onchange="document.getElementById('filterForm').submit()">
                            <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>All Time</option>
                            <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo $dateFilter === 'month' ? 'selected' : ''; ?>>This Month</option>
                        </select>
                        <button type="submit" class="btn-filter">Search</button>
                    </form>


                <!-- Payment Table -->
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Queue #</th>
                                <th>Patient</th>
                                <th>Treatment</th>
                                <th>Amount</th>
                                <th>Queue Status</th>
                                <th>Payment Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="paymentTableBody">
                            <?php if (empty($paymentRecords)): ?>
                            <tr>
                                <td colspan="7" class="no-records">
                                    <div class="empty-state">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24">
                                            <path fill="currentColor" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2m-5 14H7v-2h7zm3-4H7v-2h10zm0-4H7V7h10z"/>
                                        </svg>
                                        <p>No payment records found for this period</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($paymentRecords as $record): ?>
                            <?php 
                                $payStatus = $record['effective_payment_status'] ?? 'unpaid';
                                $isPaid = ($payStatus === 'paid');
                                $isPartial = ($payStatus === 'partial');
                                $calculatedAmount = $record['amount'] > 0 ? $record['amount'] : calculateAmount($record['treatment_type'], $servicesMap);
                            ?>
                            <tr data-queue-id="<?php echo (int)($record['queue_id'] ?? 0); ?>" data-patient-id="<?php echo (int)$record['patient_id']; ?>" data-billing-id="<?php echo (int)$record['billing_id']; ?>">
                                <td><strong><?php echo !empty($record['queue_number']) ? ('#' . $record['queue_number']) : ('INV-' . str_pad($record['billing_id'], 4, '0', STR_PAD_LEFT)); ?></strong></td>
                                <td>
                                    <div class="patient-info">
                                        <span class="patient-name"><?php echo htmlspecialchars($record['patient_name']); ?></span>
                                        <?php if ($record['patient_phone']): ?>
                                        <span class="patient-phone"><?php echo htmlspecialchars($record['patient_phone']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="treatment-type"><?php echo htmlspecialchars($record['treatment_type'] ?: 'General Checkup'); ?></span>
                                </td>
                                <td>
                                    <strong>P<?php echo number_format($calculatedAmount, 2); ?></strong>
                                    <?php if (($record['discount_amount'] ?? 0) > 0): ?>
                                        <div style="margin-top:3px;">
                                            <span style="text-decoration:line-through;color:#94a3b8;font-size:11px;">P<?php echo number_format((float)($record['subtotal_amount'] ?: ($calculatedAmount + $record['discount_amount'])), 2); ?></span>
                                            <span style="display:inline-block;background:#ecfdf5;color:#047857;font-size:10px;font-weight:700;padding:1px 7px;border-radius:9999px;border:1px solid #a7f3d0;margin-left:4px;">
                                                <?php echo strtoupper($record['discount_type']); ?> 20% &minus;P<?php echo number_format((float)$record['discount_amount'], 2); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        $qs = strtolower($record['queue_status'] ?? '');
                                        if ($isPaid || $qs === 'completed') {
                                            // If billing already paid, show completed regardless of queue status
                                            echo '<span class="status-badge queue-completed">Completed</span>';
                                        } elseif (strpos($qs, 'pending') !== false || $qs === 'pending_payment') {
                                            echo '<span class="status-badge queue-pending-payment">Pending Payment</span>';
                                        } elseif ($qs === 'in_procedure' || $qs === 'in procedure') {
                                            echo '<span class="status-badge queue-in-procedure">In Procedure</span>';
                                        } else {
                                            echo '<span class="status-badge queue-waiting">Waiting</span>';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($isPaid): ?>
                                        <span class="status-badge paid">Paid</span>
                                    <?php elseif ($isPartial): ?>
                                        <span class="status-badge partial">Partial</span>
                                    <?php else: ?>
                                        <span class="status-badge unpaid">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-cell">
                                    <div class="pay-kebab-menu">
                                        <button
                                            class="pay-kebab-btn"
                                            data-queue-id="<?php echo (int)($record['queue_id'] ?? 0); ?>"
                                            data-patient-id="<?php echo $record['patient_id']; ?>"
                                            data-billing-id="<?php echo (int)$record['billing_id']; ?>"
                                            data-amount="<?php echo $calculatedAmount; ?>"
                                            data-treatment="<?php echo htmlspecialchars($record['treatment_type'] ?: 'General Checkup'); ?>"
                                            data-is-paid="<?php echo $isPaid ? '1' : ($isPartial ? '0' : '0'); ?>"
                                            data-paid-amount="<?php echo (float)($record['paid_amount'] ?? 0); ?>"
                                            data-balance="<?php echo max(0, $calculatedAmount - (float)($record['paid_amount'] ?? 0)); ?>"
                                            data-discount-type="<?php echo htmlspecialchars($record['discount_type'] ?? 'none'); ?>"
                                            aria-label="Payment actions for queue <?php echo $record['queue_id']; ?>"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                                <circle cx="12" cy="6" r="2"/>
                                                <circle cx="12" cy="12" r="2"/>
                                                <circle cx="12" cy="18" r="2"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalRecords > 0): ?>
                <!-- Pagination -->
                <div class="pagination">
                    <span class="pagination-info">Showing <?php echo $showingStart; ?>-<?php echo $showingEnd; ?> of <?php echo $totalRecords; ?> records</span>
                    <div class="pagination-buttons">
                        <?php 
                        // Build query string for pagination links (preserve filters)
                        $queryParams = [];
                        if (!empty($searchQuery)) $queryParams['search'] = $searchQuery;
                        if (!empty($statusFilter)) $queryParams['status'] = $statusFilter;
                        if (!empty($dateFilter)) $queryParams['date'] = $dateFilter;
                        $queryString = http_build_query($queryParams);
                        $queryString = $queryString ? '&' . $queryString : '';
                        ?>
                        
                        <?php if ($currentPage > 1): ?>
                            <a href="?page=<?php echo $currentPage - 1 . $queryString; ?>" class="pagination-btn">Previous</a>
                        <?php else: ?>
                            <button class="pagination-btn" disabled>Previous</button>
                        <?php endif; ?>
                        
                        <?php
                        // Smart page number display
                        $maxVisiblePages = 5;
                        $startPage = max(1, $currentPage - floor($maxVisiblePages / 2));
                        $endPage = min($totalPages, $startPage + $maxVisiblePages - 1);
                        
                        if ($endPage - $startPage + 1 < $maxVisiblePages) {
                            $startPage = max(1, $endPage - $maxVisiblePages + 1);
                        }
                        
                        if ($startPage > 1) {
                            ?>
                            <a href="?page=1<?php echo $queryString; ?>" class="pagination-btn">1</a>
                            <?php
                            if ($startPage > 2) {
                                ?>
                                <span class="pagination-ellipsis">...</span>
                                <?php
                            }
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            if ($i == $currentPage) {
                                ?>
                                <button class="pagination-btn active"><?php echo $i; ?></button>
                                <?php
                            } else {
                                ?>
                                <a href="?page=<?php echo $i . $queryString; ?>" class="pagination-btn"><?php echo $i; ?></a>
                                <?php
                            }
                        }
                        
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                ?>
                                <span class="pagination-ellipsis">...</span>
                                <?php
                            }
                            ?>
                            <a href="?page=<?php echo $totalPages . $queryString; ?>" class="pagination-btn"><?php echo $totalPages; ?></a>
                            <?php
                        }
                        ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="?page=<?php echo $currentPage + 1 . $queryString; ?>" class="pagination-btn">Next</a>
                        <?php else: ?>
                            <button class="pagination-btn" disabled>Next</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- View Payment Details Modal -->
            <div id="paymentModal" class="modal-overlay" style="display: none;">
                <div class="modal" style="max-width:800px; width:90%;">
                    <div class="modal-header">
                        <h2>Payment Details</h2>
                        <button class="modal-close" onclick="closePaymentModal()">&times;</button>
                    </div>
                    <div class="modal-body" id="paymentModalContent">
                        <!-- Content loaded dynamically -->
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" onclick="closePaymentModal()">Close</button>
                    </div>
                </div>
            </div>

            <!-- Mark as Paid Confirmation Modal -->
            <div id="confirmPaymentModal" class="modal-overlay" style="display: none;">
                <div class="modal">
                    <div class="modal-header">
                        <h2>Confirm Payment</h2>
                        <button class="modal-close" onclick="closeConfirmPaymentModal()">&times;</button>
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
                        <input type="hidden" id="confirmPaymentQueueId">
                        <input type="hidden" id="confirmPaymentPatientId">
                        <input type="hidden" id="confirmPaymentBillingId">
                        <input type="hidden" id="confirmPaymentAmountValue">
                        <input type="hidden" id="confirmPaymentTreatmentValue">
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" onclick="closeConfirmPaymentModal()">Cancel</button>
                        <button type="button" class="btn-primary" onclick="confirmPayment()">Confirm Payment</button>
                    </div>
                </div>
            </div>

            <!-- Record Payment Modal -->
            <div id="recordPaymentModal" class="modal-overlay" style="display: none;">
                <div class="modal" style="max-width:480px;">
                    <div class="modal-header">
                        <h2>💰 Record Payment</h2>
                        <button class="modal-close" onclick="closeRecordPaymentModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div id="recordPaymentBreakdown" style="background:#f9fafb;border-radius:8px;padding:14px;margin-bottom:14px;border:1px solid #e5e7eb;">
                        </div>
                        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px;margin-bottom:16px;">
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
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151;">Payment Amount</label>
                            <input type="number" id="recordPaymentAmount" step="0.01" min="0.01" style="width: 100%; padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box;">
                        </div>
                        <div style="margin-top: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151;">Payment Method</label>
                            <select id="recordPaymentMethod" style="width: 100%; padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 0.9rem; background: white; box-sizing: border-box;">
                                <option value="cash">Cash</option>
                                <option value="gcash">GCash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="installment">Installment</option>
                                <option value="downpayment">Downpayment</option>
                            </select>
                        </div>
                        <input type="hidden" id="recordPaymentQueueId">
                        <input type="hidden" id="recordPaymentPatientId">
                        <input type="hidden" id="recordPaymentBillingId">
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" onclick="closeRecordPaymentModal()">Cancel</button>
                        <button type="button" class="btn-primary" onclick="confirmRecordPayment()">Record Payment</button>
                    </div>
                </div>
            </div>

            <!-- Payment History Modal -->
            <div id="paymentHistoryModal" class="modal-overlay" style="display: none;">
                <div class="modal" style="max-width: 550px;">
                    <div class="modal-header">
                        <h2>Payment History</h2>
                        <button class="modal-close" onclick="closePaymentHistoryModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <table style="width:100%;border-collapse:collapse;">
                            <thead>
                                <tr style="background:#f9fafb;text-align:left;">
                                    <th style="padding:0.5rem;font-size:0.8rem;color:#6b7280;font-weight:600;text-transform:uppercase;">Date</th>
                                    <th style="padding:0.5rem;font-size:0.8rem;color:#6b7280;font-weight:600;text-transform:uppercase;">Amount</th>
                                    <th style="padding:0.5rem;font-size:0.8rem;color:#6b7280;font-weight:600;text-transform:uppercase;">Method</th>
                                    <th style="padding:0.5rem;font-size:0.8rem;color:#6b7280;font-weight:600;text-transform:uppercase;">Reference</th>
                                </tr>
                            </thead>
                            <tbody id="paymentHistoryBody">
                                <tr><td colspan="4" style="text-align:center;padding:2rem;color:#9ca3af;">Loading...</td></tr>
                            </tbody>
                        </table>
                        <div id="paymentHistoryEmpty" style="display:none;text-align:center;padding:2rem;color:#9ca3af;">
                            <p>No payment records found.</p>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" onclick="closePaymentHistoryModal()">Close</button>
                    </div>
                </div>
            </div>

            <!-- Set Due Date Modal -->
            <div id="setDueDateModal" class="modal-overlay" style="display: none;">
                <div class="modal" style="max-width: 400px;">
                    <div class="modal-header">
                        <h2>Set Due Date</h2>
                        <button class="modal-close" onclick="closeSetDueDateModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <label style="display:block;margin-bottom:0.5rem;font-weight:500;color:#374151;">Due Date</label>
                        <input type="date" id="setDueDateInput" style="width:100%;padding:0.5rem;border:1px solid #e5e7eb;border-radius:8px;font-size:0.9rem;box-sizing:border-box;">
                        <input type="hidden" id="setDueDateBillingId">
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" onclick="closeSetDueDateModal()">Cancel</button>
                        <button type="button" class="btn-primary" onclick="confirmSetDueDate()">Save</button>
                    </div>
                </div>
            </div>

            <!-- Print Invoice Modal -->
            <div id="printPaymentModal" class="modal-overlay" style="display: none;">
                <div class="modal print-invoice-modal">
                    <div class="modal-header">
                        <h2>Print Invoice</h2>
                        <button class="modal-close" onclick="closePrintPaymentModal()">&times;</button>
                    </div>
                    <div class="modal-body print-payment-body" id="printPaymentContent">
                        <div class="receipt-preview-shell">
                            <iframe id="receiptPreviewFrame" class="receipt-preview-frame" title="Receipt preview" loading="eager" scrolling="no"></iframe>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" onclick="closePrintPaymentModal()">Close</button>
                        <button type="button" class="btn-primary" onclick="confirmPrint()">Print</button>
                    </div>
                </div>
            </div>

            <style>
                .page-subtitle {
                    color: #6b7280;
                    margin-top: 0.25rem;
                    font-size: 0.9rem;
                }

                .summary-cards {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 1rem;
                    margin-bottom: 1.5rem;
                }

                .summary-card {
                    background: white;
                    border-radius: 12px;
                    padding: 1.25rem;
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }

                .summary-icon {
                    width: 48px;
                    height: 48px;
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .summary-icon.green { background: #dcfce7; color: #16a34a; }
                .summary-icon.yellow { background: #fef3c7; color: #d97706; }
                .summary-icon.blue { background: #dbeafe; color: #0d92f7; }
                .summary-icon.red { background: #fee2e2; color: #dc2626; }

                .summary-info h3 {
                    font-size: 1.25rem;
                    font-weight: 600;
                    color: #1f2937;
                    margin: 0;
                }

                .summary-info p {
                    color: #6b7280;
                    font-size: 0.875rem;
                    margin: 0;
                }
                /*input here  YY */

                .filter-form {
                    display: flex;
                    gap: 0.75rem;
                    flex-wrap: wrap;
                    align-items: center;
                    margin-bottom: 20px;
                }

                .search-input-wrap {
                    position: relative;
                    flex: 1;
                    min-width: 200px;
                }

                .search-input-icon {
                    position: absolute;
                    left: 14px;
                    top: 50%;
                    transform: translateY(-50%);
                    color: #6b7280;
                    display: flex;
                    align-items: center;
                    pointer-events: none;
                }

                .search-input {
                    width: 100%;
                    padding: 0.5rem 1rem 0.5rem 2.75rem;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    font-size: 0.9rem;
                }

                .filter-select {
                    padding: 0.5rem 1rem;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    font-size: 0.9rem;
                    background: white;
                    cursor: pointer;
                }

                .btn-filter {
                    padding: 0.5rem 1rem;
                    background: #0ea5e9;
                    color: white;
                    border: none;
                    border-radius: 8px;
                    cursor: pointer;
                    font-weight: 500;
                }

                .btn-filter:hover {
                    background: #0284c7;
                }

                .table-container {
                    background: white;
                    border-radius: 12px;
                    overflow: visible;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }

                .data-table {
                    width: 100%;
                    border-collapse: collapse;
                }

                .data-table th,
                .data-table td {
                    padding: 1rem;
                    text-align: left;
                    border-bottom: 1px solid #f3f4f6;
                }

                .data-table th {
                    background: #f9fafb;
                    font-weight: 600;
                    color: #374151;
                    font-size: 0.875rem;
                }

                .data-table td {
                    font-size: 0.9rem;
                    color: #4b5563;
                }

                .patient-info {
                    display: flex;
                    flex-direction: column;
                    gap: 0.25rem;
                }

                .patient-name {
                    font-weight: 500;
                    color: #1f2937;
                }

                .patient-phone {
                    font-size: 0.8rem;
                    color: #6b7280;
                }

                .treatment-type {
                    display: block;
                }

                .teeth-info {
                    display: block;
                    font-size: 0.8rem;
                    color: #6b7280;
                    margin-top: 0.25rem;
                }

                .status-badge {
                    display: inline-block;
                    padding: 0.25rem 0.75rem;
                    border-radius: 9999px;
                    font-size: 0.75rem;
                    font-weight: 600;
                    text-transform: uppercase;
                }

                .status-badge.paid {
                    background: #dcfce7;
                    color: #16a34a;
                }
                
                .status-badge.partial {
                    background: #fef3c7;
                    color: #92400e;
                }
                
                .status-badge.unpaid {
                    background: #fef3c7;
                    color: #d97706;
                }

                .status-badge.queue-completed {
                    background: #dbeafe;
                    color: #0d92f7;
                }

                .status-badge.queue-pending-payment {
                    background: #fef3c7;
                    color: #d97706;
                }

                .status-badge.queue-in-procedure {
                    background: #e0e7ff;
                    color: #4f46e5;
                }

                .status-badge.queue-waiting {
                    background: #f3f4f6;
                    color: #6b7280;
                }

                /* Payment Kebab Menu Styles - Portal Based */
                .action-cell {
                    text-align: center;
                }

                .pay-kebab-menu {
                    position: relative;
                    display: inline-block;
                }

                .pay-kebab-btn {
                    background: none;
                    border: none;
                    cursor: pointer;
                    padding: 8px;
                    border-radius: 50%;
                    color: #6b7280;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: all 0.2s ease;
                }

                .pay-kebab-btn:hover {
                    background-color: #f3f4f6;
                    color: #374151;
                }

                .pay-kebab-btn.active {
                    background-color: #e5e7eb;
                    color: #111827;
                }

                .pay-kebab-dropdown-portal {
                    display: none;
                    position: fixed;
                    background: white;
                    border-radius: 8px;
                    min-width: 200px;
                    max-width: 220px;
                    width: auto;
                    z-index: 99999;
                    overflow: hidden;
                }

                .pay-kebab-dropdown-portal.show {
                    display: block;
                    animation: payKebabFadeIn 0.15s ease;
                }

                @keyframes payKebabFadeIn {
                    from { opacity: 0; transform: scale(0.95) translateY(-4px); }
                    to { opacity: 1; transform: scale(1) translateY(0); }
                }

                .pay-kebab-dropdown-portal a {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    padding: 10px 16px;
                    color: #374151;
                    text-decoration: none;
                    font-size: 0.875rem;
                    transition: all 0.15s ease;
                    cursor: pointer;
                    white-space: nowrap;
                }

                .pay-kebab-dropdown-portal a:hover {
                    background-color: #f9fafb;
                    color: #111827;
                }

                .pay-kebab-dropdown-portal a svg {
                    flex-shrink: 0;
                }

                .pay-kebab-dropdown-portal a:first-child {
                    border-radius: 8px 8px 0 0;
                }

                .pay-kebab-dropdown-portal a:last-child {
                    border-radius: 0 0 8px 8px;
                }

                .pay-kebab-dropdown-portal a.danger {
                    color: #dc2626;
                }

                .pay-kebab-dropdown-portal a.danger:hover {
                    background-color: #fef2f2;
                }

                .pay-kebab-backdrop {
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    z-index: 99998;
                }

                .pay-kebab-backdrop.show {
                    display: block;
                }

                .no-records {
                    text-align: center;
                    padding: 3rem !important;
                }

                .empty-state {
                    color: #9ca3af;
                }

                .empty-state svg {
                    margin-bottom: 0.5rem;
                }

                /* Pagination Styles - Matching Admin Style */
                .pagination {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 16px 20px;
                    margin-top: 12px;
                }

                .pagination-info {
                    color: #6b7280;
                    font-size: 0.875rem;
                }

                .pagination-buttons {
                    display: flex;
                    gap: 8px;
                    align-items: center;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                }

                .pagination-btn {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 8px 16px;
                    background-color: #ffffff;
                    border: 1px solid #e2e8f0;
                    border-radius: 6px;
                    text-decoration: none;
                    color: #4a5568;
                    font-size: 14px;
                    transition: all 0.2s ease;
                    min-width: 32px;
                    cursor: pointer;
                }

                .pagination-btn:hover:not(.active):not(:disabled) {
                    background-color: #f7fafc;
                    border-color: #cbd5e0;
                }

                .pagination-btn.active {
                    background-color: #0d92f7;
                    color: #ffffff;
                    border-color: #0d92f7;
                }

                .pagination-btn:disabled {
                    color: #a0aec0;
                    background-color: #fff;
                    cursor: not-allowed;
                    border-color: #edf2f7;
                }

                .pagination-ellipsis {
                    color: #a0aec0;
                    padding: 0 4px;
                }

               
/* Modal Styles - Portal Pattern */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 99999;
    align-items: center;
    justify-content: center;
}

.modal-overlay[style*="display: flex"] {
    display: flex !important;
}

.modal {
    background: white;
    border-radius: 12px;
    padding: 28px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    position: relative;
    z-index: 100000;
}
                .modal-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 1rem 1.5rem;
                    border-bottom: 1px solid #e5e7eb;
                }

                .modal-header h2 {
                    margin: 0;
                    font-size: 1.25rem;
                }

                .modal-close {
                    background: none;
                    border: none;
                    font-size: 1.5rem;
                    cursor: pointer;
                    color: #6b7280;
                }

                .modal-body {
                    padding: 1.5rem;
                }

                .print-invoice-modal {
                    max-width: min(96vw, 920px);
                    width: min(96vw, 920px);
                }

                .print-payment-body {
                    padding: 0;
                    background: transparent;
                }

                .receipt-preview-shell {
                    width: 174mm;
                    height: auto;
                    margin: 0 auto;
                    overflow: visible;
                    background: transparent;
                }

                .receipt-preview-frame {
                    width: 174mm;
                    height: auto;
                    border: 0;
                    display: block;
                    background: #ffffff;
                    overflow: hidden;
                }

                .payment-details {
                    background: #f9fafb;
                    border-radius: 8px;
                    padding: 1rem;
                    margin-top: 1rem;
                }

                .detail-row {
                    display: flex;
                    justify-content: space-between;
                    padding: 0.5rem 0;
                }

                .detail-row:not(:last-child) {
                    border-bottom: 1px solid #e5e7eb;
                }

                .detail-label {
                    color: #6b7280;
                }

                .detail-value {
                    font-weight: 600;
                    color: #1f2937;
                }

                .modal-actions {
                    padding: 1rem 1.5rem;
                    border-top: 1px solid #e5e7eb;
                    display: flex;
                    justify-content: flex-end;
                    gap: 0.75rem;
                }

                .btn-cancel {
                    padding: 0.5rem 1rem;
                    background: #0ea5e9;
                    color: white;
                    border: none;
                    border-radius: 8px;
                    cursor: pointer;
                    font-weight: 500;
                }

                .btn-cancel:hover {
                    background: #0284c7;
                }

                .btn-primary {
                    padding: 0.5rem 1rem;
                    background: #16a34a;
                    color: white;
                    border: none;
                    border-radius: 8px;
                    cursor: pointer;
                    font-weight: 500;
                }

                .btn-primary:hover {
                    background: #15803d;
                }

                @media (max-width: 768px) {
                    .summary-cards {
                        grid-template-columns: repeat(2, 1fr);
                    }

                    .data-table {
                        font-size: 0.8rem;
                    }

                    .data-table th,
                    .data-table td {
                        padding: 0.75rem 0.5rem;
                    }
                }
            </style>

            <script>
                var staffPaymentData = {};
                const receiptTemplateHtml = <?php echo json_encode($receiptHtmlTemplate); ?>;
                const receiptTemplateCss = <?php echo json_encode($receiptCssTemplate); ?>;
                const receiptBaseUrl = <?php echo json_encode($projectBaseUrl); ?>;
                const receiptImageUrl = receiptBaseUrl + 'assets/images/profile.png';
                const receiptPreviewOverrides = `
                    .receipt-book {
                        width: min(174mm, 100%) !important;
                        max-width: 174mm !important;
                        min-height: 248mm !important;
                        padding: 10px !important;
                        transform: none !important;
                        transform-origin: top center;
                        margin: 0 auto;
                    }

                    .invoice-paper {
                        min-height: 248mm;
                    }

                    .invoice-content {
                        padding: 24px 26px 20px !important;
                    }

                    .invoice-content::after {
                        inset: 12px !important;
                    }

                    @media print {
                        @page {
                            size: A5 portrait;
                            margin: 6mm;
                        }

                        html, body {
                            width: 174mm;
                            height: 248mm;
                        }

                        .receipt-book {
                            width: 174mm !important;
                            max-width: 174mm !important;
                            min-height: 248mm !important;
                            padding: 0 !important;
                            transform: none !important;
                        }

                        .invoice-paper {
                            min-height: 248mm;
                        }

                        .print-btn {
                            display: none !important;
                        }
                    }

                    @media (max-width: 760px) {
                        .receipt-book {
                            width: min(100%, 174mm) !important;
                        }
                    }
                `;

                <?php foreach ($paymentRecords as $record): ?>
                <?php if (!empty($record['billing_id'])): ?>
                staffPaymentData[<?php echo (int)$record['billing_id']; ?>] = {
                    billing_id: <?php echo (int)$record['billing_id']; ?>,
                    patient_id: <?php echo (int)$record['patient_id']; ?>,
                    queue_id: <?php echo (int)($record['queue_id'] ?? 0); ?>,
                    patient: '<?php echo addslashes($record['patient_name'] ?? ''); ?>',
                    phone: '<?php echo addslashes($record['patient_phone'] ?? ''); ?>',
                    services: '<?php echo addslashes($record['treatment_type'] ?: 'General Checkup'); ?>',
                    amount: <?php echo $record['amount'] ?? 0; ?>,
                    paid_amount: <?php echo (float)($record['paid_amount'] ?? 0); ?>,
                    balance: <?php echo max(0, ($record['amount'] ?? 0) - (float)($record['paid_amount'] ?? 0)); ?>,
                    date: '<?php echo date('Y-m-d', strtotime($record['billing_date'])); ?>',
                    status: '<?php echo htmlspecialchars($record['payment_status'] ?? 'Unpaid'); ?>',
                    invoice_id: '<?php echo !empty($record['queue_number']) ? ('#' . $record['queue_number']) : ("INV-" . str_pad($record['billing_id'], 4, '0', STR_PAD_LEFT)); ?>'
                };
                <?php endif; ?>
                <?php endforeach; ?>

                var currentPrintBillingId = null;

                // Portal Pattern: Move modals to body level to escape stacking context
                // This ensures modals appear above sidebar and all other elements
                (function() {
                    const paymentModal = document.getElementById('paymentModal');
                    const confirmPaymentModal = document.getElementById('confirmPaymentModal');
                    const recordPaymentModal = document.getElementById('recordPaymentModal');
                    const paymentHistoryModal = document.getElementById('paymentHistoryModal');
                    const setDueDateModal = document.getElementById('setDueDateModal');
                    const printPaymentModal = document.getElementById('printPaymentModal');
                    
                    if (paymentModal) {
                        document.body.appendChild(paymentModal);
                    }
                    if (confirmPaymentModal) {
                        document.body.appendChild(confirmPaymentModal);
                    }
                    if (recordPaymentModal) {
                        document.body.appendChild(recordPaymentModal);
                    }
                    if (paymentHistoryModal) {
                        document.body.appendChild(paymentHistoryModal);
                    }
                    if (setDueDateModal) {
                        document.body.appendChild(setDueDateModal);
                    }
                    if (printPaymentModal) {
                        document.body.appendChild(printPaymentModal);
                    }
                })();

                // Payment Kebab Menu - Portal Based (mirrors appointment behavior)
                let payKebabDropdown = null;
                let payKebabBackdrop = null;
                let payActiveButton = null;

                function createPayKebabDropdown() {
                    payKebabDropdown = document.createElement('div');
                    payKebabDropdown.className = 'pay-kebab-dropdown-portal';
                    payKebabDropdown.id = 'payKebabDropdownPortal';
                    document.body.appendChild(payKebabDropdown);

                    payKebabBackdrop = document.createElement('div');
                    payKebabBackdrop.className = 'pay-kebab-backdrop';
                    payKebabBackdrop.id = 'payKebabBackdrop';
                    document.body.appendChild(payKebabBackdrop);

                    payKebabBackdrop.addEventListener('click', closePayKebabDropdown);
                }

                function getPayMenuItems(button) {
                    const queueId = button.dataset.queueId;
                    const patientId = button.dataset.patientId;
                    const amount = button.dataset.amount;
                    const treatment = button.dataset.treatment;
                    const billingId = button.dataset.billingId || '0';
                    const isPaid = button.dataset.isPaid === '1';
                    const paidAmount = button.dataset.paidAmount || '0';
                    const balance = button.dataset.balance || amount;

                    return `
                        <a href="javascript:void(0)" data-action="view" data-queue-id="${queueId}" data-patient-id="${patientId}" data-billing-id="${billingId}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24">
                                <path fill="none" stroke="currentColor" stroke-width="2" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            View Details
                        </a>
                        <a href="javascript:void(0)" data-action="payment-history" data-billing-id="${billingId}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24">
                                <path fill="none" stroke="currentColor" stroke-width="2" d="M12 2v20M2 12h20"/>
                            </svg>
                            Payment History
                        </a>
                        ${!isPaid ? `
                        <a href="javascript:void(0)" data-action="record-payment" data-billing-id="${billingId}" data-queue-id="${queueId}" data-patient-id="${patientId}" data-amount="${amount}" data-treatment="${treatment}" data-paid-amount="${paidAmount}" data-balance="${balance}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24">
                                <path fill="none" stroke="currentColor" stroke-width="2" d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                            Record Payment
                        </a>
                        <a href="javascript:void(0)" data-action="mark-paid" data-billing-id="${billingId}" data-queue-id="${queueId}" data-patient-id="${patientId}" data-amount="${amount}" data-treatment="${treatment}" data-paid-amount="${paidAmount}" data-balance="${balance}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24">
                                <path fill="none" stroke="currentColor" stroke-width="2" d="M20 6 9 17l-5-5"/>
                            </svg>
                            Mark as Paid
                        </a>
                        <a href="javascript:void(0)" data-action="set-due-date" data-billing-id="${billingId}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24">
                                <path fill="none" stroke="currentColor" stroke-width="2" d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 16H5V10h14z"/>
                            </svg>
                            Set Due Date
                        </a>
                        <a href="javascript:void(0)" data-action="toggle-discount" data-billing-id="${billingId}" data-apply="${button.dataset.discountType === 'none' ? '1' : '0'}">
                            ${button.dataset.discountType === 'none'
                                ? `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M21.41 11.58l-9-9A2 2 0 0 0 11 2H4a2 2 0 0 0-2 2v7a2 2 0 0 0 .59 1.42l9 9A2 2 0 0 0 13 22a2 2 0 0 0 1.41-.59l7-7A2 2 0 0 0 22 13a2 2 0 0 0-.59-1.42M6.5 8A1.5 1.5 0 1 1 8 6.5A1.5 1.5 0 0 1 6.5 8"/></svg>
                            Apply SC/PWD Discount`
                                : `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M19 6.41L17.59 5L12 10.59L6.41 5L5 6.41L10.59 12L5 17.59L6.41 19L12 13.41L17.59 19L19 17.59L13.41 12z"/></svg>
                            Remove SC/PWD Discount`}
                        </a>` : ''}
                        <a href="javascript:void(0)" data-action="print" data-invoice-id="${queueId}" data-billing-id="${billingId}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24">
                                <path fill="none" stroke="currentColor" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8" fill="none" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            Print Invoice
                        </a>
                    `;
                }

                function positionPayKebabDropdown(button) {
                    if (!payKebabDropdown || !button) return;

                    const rect = button.getBoundingClientRect();
                    const viewportWidth = window.innerWidth;
                    const viewportHeight = window.innerHeight;
                    const padding = 12;

                    // Measure dropdown size by temporarily showing it invisibly
                    payKebabDropdown.style.display = 'block';
                    payKebabDropdown.style.visibility = 'hidden';
                    const dropdownRect = payKebabDropdown.getBoundingClientRect();
                    const dropdownWidth = dropdownRect.width || 200;
                    const dropdownHeight = dropdownRect.height || 120;

                    // Prefer left side of the button, vertically centered
                    let left = rect.left - dropdownWidth - 8;
                    let top = rect.top + (rect.height / 2) - (dropdownHeight / 2);

                    // If it overflows on the left, place it on the right
                    if (left < padding) {
                        left = rect.right + 8;
                    }

                    // Clamp vertical position
                    if (top + dropdownHeight > viewportHeight - padding) {
                        top = viewportHeight - padding - dropdownHeight;
                    }
                    if (top < padding) {
                        top = padding;
                    }

                    payKebabDropdown.style.left = left + 'px';
                    payKebabDropdown.style.top = top + 'px';
                    payKebabDropdown.style.visibility = 'visible';
                }

                function openPayKebabDropdown(button) {
                    if (!payKebabDropdown) {
                        createPayKebabDropdown();
                    }

                    payKebabDropdown.innerHTML = getPayMenuItems(button);
                    positionPayKebabDropdown(button);

                    payKebabDropdown.classList.add('show');
                    payKebabBackdrop.classList.add('show');
                    payActiveButton = button;
                    button.classList.add('active');

                    payKebabDropdown.addEventListener('click', handlePayKebabClick);
                }

                function closePayKebabDropdown() {
                    if (payKebabDropdown) {
                        payKebabDropdown.classList.remove('show');
                        payKebabDropdown.style.display = 'none';
                        payKebabDropdown.style.visibility = 'hidden';
                    }
                    if (payKebabBackdrop) {
                        payKebabBackdrop.classList.remove('show');
                    }
                    if (payActiveButton) {
                        payActiveButton.classList.remove('active');
                        payActiveButton = null;
                    }
                }

                function handlePayKebabClick(e) {
                    const link = e.target.closest('a');
                    if (!link) return;

                    e.preventDefault();
                    const action = link.dataset.action;
                    const queueId = link.dataset.queueId;
                    const patientId = link.dataset.patientId;
                    const billingId = link.dataset.billingId;
                    const amount = link.dataset.amount;
                    const treatment = link.dataset.treatment;
                    const paidAmount = link.dataset.paidAmount || '0';
                    const balance = link.dataset.balance || amount;

                    closePayKebabDropdown();

                    switch (action) {
                        case 'view':
                            viewPaymentDetails(queueId, patientId, billingId);
                            break;
                        case 'payment-history':
                            openPaymentHistory(billingId);
                            break;
                        case 'record-payment':
                            openRecordPayment(queueId, patientId, amount, treatment, billingId, paidAmount, balance);
                            break;
                        case 'mark-paid':
                            markAsPaid(queueId, patientId, amount, treatment, billingId, paidAmount, balance);
                            break;
                        case 'set-due-date':
                            openSetDueDate(billingId);
                            break;
                        case 'toggle-discount':
                            toggleDiscount(billingId, link.dataset.apply === '1');
                            break;
                        case 'print':
                            printPayment(billingId);
                            break;
                    }
                }

                function toggleDiscount(billingId, apply) {
                    if (!billingId || billingId === '0') { alert('No billing record attached yet.'); return; }
                    fetch('../shared/billing_actions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'toggle_discount', billing_id: billingId, apply: apply })
                    })
                    .then(r => r.json())
                    .then(data => {
                        alert(data.message || (data.success ? 'Done' : 'Failed'));
                        if (data.success) location.reload();
                    })
                    .catch(() => alert('Request failed. Please try again.'));
                }

                // Click handler for kebab buttons
                document.addEventListener('click', function(e) {
                    const button = e.target.closest('.pay-kebab-btn');
                    if (button) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        if (payActiveButton === button) {
                            closePayKebabDropdown();
                        } else {
                            closePayKebabDropdown();
                            openPayKebabDropdown(button);
                        }
                    } else if (!e.target.closest('.pay-kebab-dropdown-portal')) {
                        closePayKebabDropdown();
                    }
                });

                function formatPayStatus(status) {
                    const s = (status || 'unpaid').toLowerCase();
                    if (s === 'paid') return '<span style="background:#dcfce7;color:#16a34a;padding:2px 10px;border-radius:9999px;font-size:0.75rem;font-weight:600;text-transform:uppercase;">Paid</span>';
                    if (s === 'partial') return '<span style="background:#fef3c7;color:#92400e;padding:2px 10px;border-radius:9999px;font-size:0.75rem;font-weight:600;text-transform:uppercase;">Partial</span>';
                    return '<span style="background:#fee2e2;color:#dc2626;padding:2px 10px;border-radius:9999px;font-size:0.75rem;font-weight:600;text-transform:uppercase;">Unpaid</span>';
                }

                function formatDueDate(dateStr) {
                    if (!dateStr) return '';
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    const due = new Date(dateStr);
                    due.setHours(0, 0, 0, 0);
                    const diff = Math.ceil((due - today) / (1000 * 60 * 60 * 24));
                    if (diff < 0) {
                        return '<span style="color:#dc2626;font-weight:600;">' + dateStr + ' (Overdue by ' + Math.abs(diff) + ' days)</span>';
                    } else if (diff === 0) {
                        return '<span style="color:#d97706;font-weight:600;">' + dateStr + ' (Due today)</span>';
                    } else if (diff <= 7) {
                        return '<span style="color:#d97706;font-weight:600;">' + dateStr + ' (' + diff + ' days left)</span>';
                    }
                    return '<span style="color:#16a34a;font-weight:600;">' + dateStr + '</span>';
                }

                function viewPaymentDetails(queueId, patientId, billingId) {
                    closePayKebabDropdown();
                    
                    document.getElementById('paymentModal').style.display = 'flex';
                    document.getElementById('paymentModalContent').innerHTML = `
                        <div style="text-align: center; padding: 2rem;">
                            <p>Loading payment details...</p>
                        </div>
                    `;

                    // First get billing info to find patient_id, then fetch all billings for that patient
                    var billingEndpoint = billingId && billingId !== '0'
                        ? '../shared/billing_actions.php?action=get_billing&billing_id=' + encodeURIComponent(billingId)
                        : '../shared/billing_actions.php?action=get_details&queue_id=' + encodeURIComponent(queueId || 0) + '&patient_id=' + encodeURIComponent(patientId || 0);

                    fetch(billingEndpoint)
                        .then(response => response.json())
                        .then(data => {
                            var pid = patientId;
                            if (data.success && data.billing) {
                                pid = data.billing.patient_id || pid;
                            }
                            if (data.success && data.patient_id) {
                                pid = data.patient_id || pid;
                            }
                            
                            // Now fetch all billings for this patient
                            return fetch('../user-patient/patient_record_details.php?id=' + encodeURIComponent(pid))
                                .then(resp => resp.json())
                                .then(detailData => {
                                    if (!detailData.success) {
                                        // Fallback: show single billing
                                        renderSingleBillingDetails(data);
                                        return;
                                    }
                                    var allBillings = detailData.all_billings || [];
                                    var patientInfo = detailData.patient || {};
                                    var patientName = [patientInfo.first_name || '', patientInfo.middle_name || '', patientInfo.last_name || ''].filter(Boolean).join(' ') || 'N/A';
                                    
                                    // Calculate totals
                                    var totalAmount = 0, paidAmount = 0, balance = 0;
                                    allBillings.forEach(function(bl) {
                                        totalAmount += parseFloat(bl.total_amount || 0);
                                        paidAmount += parseFloat(bl.paid_amount || 0);
                                        balance += parseFloat(bl.balance || 0);
                                    });
                                    
                                    var overallPaid = true;
                                    allBillings.forEach(function(bl) {
                                        if ((bl.payment_status || 'unpaid').toLowerCase() !== 'paid') overallPaid = false;
                                    });
                                    
                                    var statusBadge = overallPaid 
                                        ? '<span style="background:#dcfce7;color:#16a34a;padding:3px 10px;border-radius:9999px;font-size:0.8rem;font-weight:600;">PAID</span>'
                                        : (balance > 0 ? '<span style="background:#fee2e2;color:#dc2626;padding:3px 10px;border-radius:9999px;font-size:0.8rem;font-weight:600;">UNPAID</span>' : '<span style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:9999px;font-size:0.8rem;font-weight:600;">PARTIAL</span>');
                                    
                                    var billingRows = allBillings.map(function(bl) {
                                        var st = (bl.payment_status || 'unpaid').toLowerCase();
                                        var sBg = '#fee2e2', sClr = '#991b1b', sLbl = 'UNPAID';
                                        var rowBg = '#fff5f5';
                                        if (st === 'paid') { sBg = '#dcfce7'; sClr = '#15803d'; sLbl = 'PAID'; rowBg = '#f0fdf4'; }
                                        else if (st === 'partial') { sBg = '#fef3c7'; sClr = '#92400e'; sLbl = 'PARTIAL'; rowBg = '#fffbeb'; }
                                        return '<tr style="border-top:1px solid #f1f5f9;background:' + rowBg + ';">' +
                                            '<td style="padding:10px 12px;color:#374151;font-weight:500;">' + (bl.service_name || 'General Service') + '</td>' +
                                            '<td style="padding:10px 12px;text-align:right;color:#111827;font-weight:600;">P' + parseFloat(bl.total_amount).toFixed(2) + '</td>' +
                                            '<td style="padding:10px 12px;text-align:right;color:#16a34a;font-weight:600;">P' + parseFloat(bl.paid_amount).toFixed(2) + '</td>' +
                                            '<td style="padding:10px 12px;text-align:right;color:' + (parseFloat(bl.balance) > 0 ? '#dc2626' : '#16a34a') + ';font-weight:600;">P' + parseFloat(bl.balance).toFixed(2) + '</td>' +
                                            '<td style="padding:10px 12px;text-align:center;"><span style="background:' + sBg + ';color:' + sClr + ';padding:3px 10px;border-radius:9999px;font-size:0.75rem;font-weight:600;">' + sLbl + '</span></td></tr>';
                                    }).join('');

                                    document.getElementById('paymentModalContent').innerHTML = `
                                        <div style="margin-bottom:16px;">
                                            <div class="detail-row">
                                                <span class="detail-label">Patient:</span>
                                                <span class="detail-value">${patientName}</span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Phone:</span>
                                                <span class="detail-value">${patientInfo.phone || 'N/A'}</span>
                                            </div>
                                        </div>
                                        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px;margin-bottom:16px;">
                                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                                <span style="color:#166534;font-weight:600;">Overall Status</span>
                                                ${statusBadge}
                                            </div>
                                            <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:0.9rem;">
                                                <span style="color:#6b7280;">Total: <strong>P${totalAmount.toFixed(2)}</strong></span>
                                                <span style="color:#16a34a;">Paid: <strong>P${paidAmount.toFixed(2)}</strong></span>
                                                <span style="color:${balance > 0 ? '#dc2626' : '#16a34a'};">Balance: <strong>P${balance.toFixed(2)}</strong></span>
                                            </div>
                                        </div>
                                        <div style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:12px;">
                                            <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                                                <thead><tr style="background:#f0fdf4;">
                                                    <th style="padding:10px 12px;text-align:left;color:#166534;font-weight:600;">Service</th>
                                                    <th style="padding:10px 12px;text-align:right;color:#166534;font-weight:600;">Amount</th>
                                                    <th style="padding:10px 12px;text-align:right;color:#166534;font-weight:600;">Paid</th>
                                                    <th style="padding:10px 12px;text-align:right;color:#166534;font-weight:600;">Balance</th>
                                                    <th style="padding:10px 12px;text-align:center;color:#166534;font-weight:600;">Status</th>
                                                </tr></thead>
                                                <tbody>
                                                    ${billingRows}
                                                    <tr style="border-top:2px solid #d1d5db;background:#f0fdf4;">
                                                        <td style="padding:10px 12px;color:#166534;font-weight:700;">TOTAL</td>
                                                        <td style="padding:10px 12px;text-align:right;color:#166534;font-weight:700;">P${totalAmount.toFixed(2)}</td>
                                                        <td style="padding:10px 12px;text-align:right;color:#16a34a;font-weight:700;">P${paidAmount.toFixed(2)}</td>
                                                        <td style="padding:10px 12px;text-align:right;color:${balance > 0 ? '#dc2626' : '#16a34a'};font-weight:700;">P${balance.toFixed(2)}</td>
                                                        <td></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    `;
                                });
                        })
                        .catch(error => {
                            document.getElementById('paymentModalContent').innerHTML = `
                                <p style="color: #dc2626;">Error loading details. Please try again.</p>
                            `;
                        });
                }

                function renderSingleBillingDetails(data) {
                    if (data.success && data.billing) {
                        const billing = data.billing;
                        const patientName = [billing.first_name || '', billing.middle_name || '', billing.last_name || '', billing.suffix || ''].filter(Boolean).join(' ') || 'N/A';
                        document.getElementById('paymentModalContent').innerHTML = `
                            <div class="billing-detail-view">
                                <div class="detail-row"><span class="detail-label">Invoice:</span><span class="detail-value">INV-${String(billing.id).padStart(4, '0')}</span></div>
                                <div class="detail-row"><span class="detail-label">Patient:</span><span class="detail-value">${patientName}</span></div>
                                <div class="detail-row"><span class="detail-label">Phone:</span><span class="detail-value">${billing.phone || 'N/A'}</span></div>
                                <div class="detail-row"><span class="detail-label">Amount:</span><span class="detail-value">P${parseFloat(billing.total_amount || 0).toFixed(2)}</span></div>
                                <div class="detail-row"><span class="detail-label">Paid Amount:</span><span class="detail-value">P${parseFloat(billing.paid_amount || 0).toFixed(2)}</span></div>
                                <div class="detail-row"><span class="detail-label">Payment Status:</span><span class="detail-value">${formatPayStatus(billing.payment_status)}</span></div>
                                <div class="detail-row"><span class="detail-label">Billing Date:</span><span class="detail-value">${billing.billing_date || 'N/A'}</span></div>
                                <div class="detail-row"><span class="detail-label">Due Date:</span><span class="detail-value">${billing.due_date ? formatDueDate(billing.due_date) : '<span style="color:#9ca3af;">Not set</span>'}</span></div>
                                <div class="detail-row"><span class="detail-label">Notes:</span><span class="detail-value">${billing.notes || 'N/A'}</span></div>
                            </div>
                        `;
                    } else if (data.success) {
                        document.getElementById('paymentModalContent').innerHTML = `
                            <div class="billing-detail-view">
                                <div class="detail-row"><span class="detail-label">Patient:</span><span class="detail-value">${data.patient_name}</span></div>
                                <div class="detail-row"><span class="detail-label">Treatment:</span><span class="detail-value">${data.treatment_type || 'General Checkup'}</span></div>
                                <div class="detail-row"><span class="detail-label">Queue Status:</span><span class="detail-value">${data.queue_status}</span></div>
                                <div class="detail-row"><span class="detail-label">Amount:</span><span class="detail-value">P${parseFloat(data.amount).toFixed(2)}</span></div>
                                <div class="detail-row"><span class="detail-label">Payment Status:</span><span class="detail-value">${formatPayStatus(data.payment_status)}</span></div>
                                <div class="detail-row"><span class="detail-label">Date:</span><span class="detail-value">${data.queue_date}</span></div>
                                <div class="detail-row"><span class="detail-label">Due Date:</span><span class="detail-value">${data.due_date ? formatDueDate(data.due_date) : '<span style="color:#9ca3af;">Not set</span>'}</span></div>
                            </div>
                        `;
                    } else {
                        document.getElementById('paymentModalContent').innerHTML = `<p style="color: #dc2626;">Error loading details: ${data.message}</p>`;
                    }
                }

                function closePaymentModal() {
                    document.getElementById('paymentModal').style.display = 'none';
                }

                function markAsPaid(queueId, patientId, amount, treatment, billingId, paidAmount, balance) {
                    // Close kebab menu
                    closePayKebabDropdown();
                    
                    var balanceVal = parseFloat(balance || 0);
                    var paidVal = parseFloat(paidAmount || 0);
                    var amountVal = parseFloat(amount || 0);
                    
                    // Set modal values
                    document.getElementById('confirmPaymentQueueId').value = queueId;
                    document.getElementById('confirmPaymentPatientId').value = patientId;
                    document.getElementById('confirmPaymentBillingId').value = billingId || 0;
                    document.getElementById('confirmPaymentAmountValue').value = balanceVal > 0 ? balanceVal : amountVal;
                    document.getElementById('confirmPaymentTreatmentValue').value = treatment;
                    document.getElementById('confirmPaymentTotalAmount').textContent = 'P' + amountVal.toFixed(2);
                    document.getElementById('confirmPaymentPaidSoFar').textContent = 'P' + paidVal.toFixed(2);
                    document.getElementById('confirmPaymentBalance').textContent = 'P' + (balanceVal > 0 ? balanceVal : amountVal).toFixed(2);
                    document.getElementById('confirmPaymentTreatment').textContent = treatment || 'General Checkup';
                    
                    // Show modal
                    document.getElementById('confirmPaymentModal').style.display = 'flex';
                }

                function closeConfirmPaymentModal() {
                    document.getElementById('confirmPaymentModal').style.display = 'none';
                }

                function confirmPayment() {
                    const queueId = document.getElementById('confirmPaymentQueueId').value;
                    const patientId = document.getElementById('confirmPaymentPatientId').value;
                    const billingId = document.getElementById('confirmPaymentBillingId').value;
                    const amount = document.getElementById('confirmPaymentAmountValue').value;
                    const treatment = document.getElementById('confirmPaymentTreatmentValue').value;
                    
                    // Send AJAX request to mark as paid
                    fetch('../shared/billing_actions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'mark_paid',
                            billing_id: billingId,
                            queue_id: queueId,
                            patient_id: patientId,
                            amount: amount,
                            treatment: treatment
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            closeConfirmPaymentModal();
                            // Refresh the page to show updated status
                            window.location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('Error processing payment. Please try again.');
                    });
                }

                function openRecordPayment(queueId, patientId, amount, treatment, billingId, paidAmount, balance) {
                    closePayKebabDropdown();

                    document.getElementById('recordPaymentQueueId').value = queueId || 0;
                    document.getElementById('recordPaymentPatientId').value = patientId || 0;
                    document.getElementById('recordPaymentBillingId').value = billingId || 0;
                    document.getElementById('recordPaymentTotalAmount').textContent = 'P' + parseFloat(amount || 0).toFixed(2);
                    document.getElementById('recordPaymentPaidSoFar').textContent = 'P' + parseFloat(paidAmount || 0).toFixed(2);
                    document.getElementById('recordPaymentBalance').textContent = 'P' + parseFloat(balance || amount || 0).toFixed(2);
                    document.getElementById('recordPaymentAmount').value = parseFloat(balance || amount || 0).toFixed(2);
                    document.getElementById('recordPaymentMethod').value = 'cash';

                    // Fetch all billings for this patient to show breakdown
                    fetch('../user-patient/patient_record_details.php?id=' + encodeURIComponent(patientId))
                        .then(resp => resp.json())
                        .then(detailData => {
                            var breakdownHtml = '';
                            if (detailData.success) {
                                var allBillings = detailData.all_billings || [];
                                var unpaidBills = allBillings.filter(function(bl) {
                                    return (bl.payment_status || 'unpaid').toLowerCase() !== 'paid';
                                });
                                if (unpaidBills.length > 0) {
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

                                    // Update totals to reflect ALL unpaid billings
                                    var totalAmt = 0, totalPaid = 0, totalBal = 0;
                                    allBillings.forEach(function(bl) {
                                        totalAmt += parseFloat(bl.total_amount || 0);
                                        totalPaid += parseFloat(bl.paid_amount || 0);
                                        totalBal += parseFloat(bl.balance || 0);
                                    });
                                    document.getElementById('recordPaymentTotalAmount').textContent = 'P' + totalAmt.toFixed(2);
                                    document.getElementById('recordPaymentPaidSoFar').textContent = 'P' + totalPaid.toFixed(2);
                                    document.getElementById('recordPaymentBalance').textContent = 'P' + totalBal.toFixed(2);
                                    document.getElementById('recordPaymentAmount').value = totalBal.toFixed(2);
                                } else {
                                    breakdownHtml = '<div style="color:#16a34a;text-align:center;padding:4px;">All services paid</div>';
                                }
                            }
                            document.getElementById('recordPaymentBreakdown').innerHTML = breakdownHtml;
                        })
                        .catch(function() {
                            document.getElementById('recordPaymentBreakdown').innerHTML = '';
                        });

                    document.getElementById('recordPaymentModal').style.display = 'flex';
                }

                function closeRecordPaymentModal() {
                    document.getElementById('recordPaymentModal').style.display = 'none';
                }

                function confirmRecordPayment() {
                    const billingId = document.getElementById('recordPaymentBillingId').value;
                    const queueId = document.getElementById('recordPaymentQueueId').value;
                    const patientId = document.getElementById('recordPaymentPatientId').value;
                    const amount = document.getElementById('recordPaymentAmount').value;
                    const paymentMethod = document.getElementById('recordPaymentMethod').value;

                    if (!amount || parseFloat(amount) <= 0) {
                        alert('Please enter a valid payment amount.');
                        return;
                    }

                    fetch('../shared/billing_actions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'record_payment',
                            billing_id: billingId,
                            queue_id: queueId,
                            patient_id: patientId,
                            amount: amount,
                            payment_method: paymentMethod
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            closeRecordPaymentModal();
                            window.location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('Error recording payment. Please try again.');
                    });
                }

                function openPaymentHistory(billingId) {
                    closePayKebabDropdown();
                    if (!billingId || billingId === '0') {
                        alert('No billing record found for this patient.');
                        return;
                    }

                    const modal = document.getElementById('paymentHistoryModal');
                    const tbody = document.getElementById('paymentHistoryBody');
                    const empty = document.getElementById('paymentHistoryEmpty');
                    modal.style.display = 'flex';
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:2rem;color:#6b7280;">Loading...</td></tr>';
                    empty.style.display = 'none';

                    fetch('../shared/billing_actions.php?action=get_payments&billing_id=' + encodeURIComponent(billingId))
                        .then(response => response.json())
                        .then(data => {
                            tbody.innerHTML = '';
                            if (data.success && data.payments && data.payments.length > 0) {
                                empty.style.display = 'none';
                                data.payments.forEach(p => {
                                    const row = document.createElement('tr');
                                    const methodLabel = (p.payment_method || 'Cash').charAt(0).toUpperCase() + (p.payment_method || 'Cash').slice(1);
                                    row.innerHTML = `
                                        <td>${p.payment_date || 'N/A'}</td>
                                        <td><strong>P${parseFloat(p.amount || 0).toFixed(2)}</strong></td>
                                        <td>${methodLabel}</td>
                                        <td>${p.reference_number || '-'}</td>
                                    `;
                                    tbody.appendChild(row);
                                });
                            } else {
                                empty.style.display = 'block';
                            }
                        })
                        .catch(error => {
                            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:2rem;color:#dc2626;">Error loading payment history.</td></tr>';
                        });
                }

                function closePaymentHistoryModal() {
                    document.getElementById('paymentHistoryModal').style.display = 'none';
                }

                function openSetDueDate(billingId) {
                    closePayKebabDropdown();
                    if (!billingId || billingId === '0') {
                        alert('No billing record found for this patient.');
                        return;
                    }

                    const modal = document.getElementById('setDueDateModal');
                    document.getElementById('setDueDateBillingId').value = billingId;
                    document.getElementById('setDueDateInput').value = '';

                    fetch('../shared/billing_actions.php?action=get_billing&billing_id=' + encodeURIComponent(billingId))
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.billing) {
                                const dueDate = data.billing.due_date || '';
                                if (dueDate) {
                                    document.getElementById('setDueDateInput').value = dueDate;
                                } else {
                                    const future = new Date();
                                    future.setDate(future.getDate() + 30);
                                    document.getElementById('setDueDateInput').value = future.toISOString().split('T')[0];
                                }
                            } else {
                                const future = new Date();
                                future.setDate(future.getDate() + 30);
                                document.getElementById('setDueDateInput').value = future.toISOString().split('T')[0];
                            }
                        })
                        .catch(() => {
                            const future = new Date();
                            future.setDate(future.getDate() + 30);
                            document.getElementById('setDueDateInput').value = future.toISOString().split('T')[0];
                        });

                    modal.style.display = 'flex';
                }

                function closeSetDueDateModal() {
                    document.getElementById('setDueDateModal').style.display = 'none';
                }

                function confirmSetDueDate() {
                    const billingId = document.getElementById('setDueDateBillingId').value;
                    const dueDate = document.getElementById('setDueDateInput').value;

                    if (!dueDate) {
                        alert('Please select a due date.');
                        return;
                    }

                    fetch('../shared/billing_actions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'set_due_date',
                            billing_id: billingId,
                            due_date: dueDate
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            closeSetDueDateModal();
                            window.location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('Error setting due date. Please try again.');
                    });
                }

                function printPayment(billingId) {
                    closePayKebabDropdown();
                    if (!billingId || billingId === '0') {
                        alert('No billing record found for this patient.');
                        return;
                    }

                    currentPrintBillingId = billingId;
                    var p = staffPaymentData[billingId];

                    if (p) {
                        renderPrintInvoice(p);
                    } else {
                        fetch('../shared/billing_actions.php?action=get_billing&billing_id=' + encodeURIComponent(billingId))
                            .then(response => response.json())
                            .then(data => {
                                if (data.success && data.billing) {
                                    var b = data.billing;
                                    var fullName = [b.first_name || '', b.middle_name || '', b.last_name || '', b.suffix || ''].filter(Boolean).join(' ') || 'N/A';
                                    renderPrintInvoice({
                                        invoice_id: 'INV-' + String(b.id).padStart(4, '0'),
                                        patient: fullName,
                                        phone: b.phone || '',
                                        services: b.treatment_type || 'General Service',
                                        amount: b.total_amount || 0,
                                        paid_amount: b.paid_amount || 0,
                                        balance: b.balance || 0,
                                        date: b.billing_date || '',
                                        dueDate: b.due_date || '',
                                        status: b.payment_status || 'Unpaid'
                                    });
                                } else {
                                    alert('Error loading billing details.');
                                }
                            })
                            .catch(() => alert('Error loading billing details.'));
                    }
                }

                function renderPrintInvoice(p) {
                    var frame = document.getElementById('receiptPreviewFrame');
                    if (!frame) {
                        alert('Receipt preview is unavailable.');
                        return;
                    }

                    var srcdoc = buildReceiptSrcdoc();
                    frame.onload = function() {
                        fillReceiptPreview(frame, p);
                    };
                    frame.srcdoc = srcdoc;
                    document.getElementById('printPaymentModal').style.display = 'flex';
                }

                function buildReceiptSrcdoc() {
                    var html = receiptTemplateHtml || '';
                    if (!html) {
                        return '<!DOCTYPE html><html><body><div style="padding:24px;font-family:Arial,sans-serif;">Receipt template not found.</div></body></html>';
                    }

                    html = html.replace('<link rel="stylesheet" href="receipt.css">', '<style>' + receiptTemplateCss + '\n' + receiptPreviewOverrides + '</style>');
                    html = html.replace('src="profile.png"', 'src="' + receiptImageUrl + '"');
                    html = html.replace('<head>', '<head><base href="' + receiptBaseUrl + '">');
                    html = html.replace(/<button class="print-btn" onclick="window\.print\(\)">Print Invoice<\/button>\s*/i, '');
                    return html;
                }

                function formatReceiptMoney(value) {
                    var numeric = parseFloat(value);
                    if (!Number.isFinite(numeric)) {
                        numeric = 0;
                    }
                    return numeric.toLocaleString('en-PH', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }

                function formatReceiptDate(value) {
                    if (!value) {
                        return '';
                    }

                    var dateValue = new Date(value);
                    if (Number.isNaN(dateValue.getTime())) {
                        return String(value);
                    }

                    return dateValue.toLocaleDateString('en-PH', {
                        year: 'numeric',
                        month: 'short',
                        day: '2-digit'
                    });
                }

                function fillReceiptPreview(frame, p) {
                    var doc = frame.contentDocument || (frame.contentWindow && frame.contentWindow.document);
                    if (!doc) return;

                    if (doc.documentElement) {
                        doc.documentElement.style.overflow = 'hidden';
                        doc.documentElement.style.margin = '0';
                        doc.documentElement.style.padding = '0';
                    }
                    if (doc.body) {
                        doc.body.style.overflow = 'hidden';
                        doc.body.style.margin = '0';
                        doc.body.style.padding = '0';
                    }

                    var invoiceInput = doc.querySelector('.editable-box');
                    if (invoiceInput) {
                        invoiceInput.value = p.invoice_id || 'N/A';
                    }

                    var metaInputs = doc.querySelectorAll('.invoice-meta .line-input');
                    if (metaInputs[0]) {
                        metaInputs[0].value = formatReceiptDate(p.date || '');
                    }

                    var billInputs = doc.querySelectorAll('.bill-to .line-input');
                    if (billInputs[0]) {
                        billInputs[0].value = p.patient || 'N/A';
                    }
                    if (billInputs[1]) {
                        billInputs[1].value = p.phone || '';
                    }

                    var serviceNames = String(p.services || 'General Service')
                        .split(',')
                        .map(function(service) { return service.trim(); })
                        .filter(Boolean);
                    var rows = doc.querySelectorAll('.services-table tbody tr');
                    rows.forEach(function(row, index) {
                        var particularInput = row.querySelector('.table-input');
                        var amountInput = row.querySelector('.amount-input');

                        if (!particularInput || !amountInput) return;

                        if (index === 0) {
                            particularInput.value = serviceNames.length ? serviceNames.join(', ') : 'General Service';
                            amountInput.value = formatReceiptMoney(p.amount || 0);
                        } else {
                            particularInput.value = '';
                            amountInput.value = '';
                        }
                    });

                    var totalInput = doc.querySelector('.total-input');
                    if (totalInput) {
                        totalInput.value = formatReceiptMoney(p.amount || 0);
                    }

                    if (doc.defaultView && typeof doc.defaultView.updateTotal === 'function') {
                        doc.defaultView.updateTotal();
                    }

                    requestAnimationFrame(function() {
                        requestAnimationFrame(function() {
                            var height = Math.max(
                                doc.body ? doc.body.scrollHeight : 0,
                                doc.documentElement ? doc.documentElement.scrollHeight : 0
                            );
                            if (height > 0) {
                                frame.style.height = height + 'px';
                            }
                        });
                    });
                }

                function closePrintPaymentModal() {
                    document.getElementById('printPaymentModal').style.display = 'none';
                    currentPrintBillingId = null;
                }

                function confirmPrint() {
                    if (currentPrintBillingId) {
                        fetch('../shared/billing_actions.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'mark_printed', billing_id: currentPrintBillingId })
                        }).catch(() => {});
                    }

                    var frame = document.getElementById('receiptPreviewFrame');
                    if (frame && frame.contentWindow) {
                        frame.contentWindow.focus();
                        frame.contentWindow.print();
                        return;
                    }

                    window.print();
                }

                // Close modals when clicking outside
                document.getElementById('paymentModal').addEventListener('click', function(e) {
                    if (e.target === this) {
                        closePaymentModal();
                    }
                });

                document.getElementById('confirmPaymentModal').addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeConfirmPaymentModal();
                    }
                });

                document.getElementById('recordPaymentModal').addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeRecordPaymentModal();
                    }
                });

                document.getElementById('paymentHistoryModal').addEventListener('click', function(e) {
                    if (e.target === this) {
                        closePaymentHistoryModal();
                    }
                });

                document.getElementById('setDueDateModal').addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeSetDueDateModal();
                    }
                });

                document.getElementById('printPaymentModal').addEventListener('click', function(e) {
                    if (e.target === this) {
                        closePrintPaymentModal();
                    }
                });

                // Filter visible rows locally while typing to avoid page reload flicker
                const paymentSearchInput = document.getElementById('paymentSearch');
                const paymentTableBody = document.getElementById('paymentTableBody');

                if (paymentSearchInput && paymentTableBody) {
                    const paymentRows = Array.from(paymentTableBody.querySelectorAll('tr[data-queue-id]'));
                    const emptyStateRow = paymentTableBody.querySelector('.no-records')?.closest('tr') || null;
                    let noMatchRow = document.getElementById('paymentNoMatchRow');

                    if (!noMatchRow && paymentRows.length > 0) {
                        noMatchRow = document.createElement('tr');
                        noMatchRow.id = 'paymentNoMatchRow';
                        noMatchRow.hidden = true;
                        noMatchRow.innerHTML = `
                            <td colspan="7" class="no-records">
                                <div class="empty-state">
                                    <p>No matching payment records found</p>
                                </div>
                            </td>
                        `;
                        paymentTableBody.appendChild(noMatchRow);
                    }

                    function filterPaymentRows() {
                        const query = paymentSearchInput.value.trim().toLowerCase();
                        let visibleCount = 0;

                        paymentRows.forEach(function(row) {
                            const rowText = row.textContent.toLowerCase();
                            const matches = !query || rowText.includes(query);
                            row.hidden = !matches;

                            if (matches) {
                                visibleCount++;
                            }
                        });

                        if (emptyStateRow) {
                            emptyStateRow.hidden = paymentRows.length > 0 || !!query;
                        }

                        if (noMatchRow) {
                            noMatchRow.hidden = !(paymentRows.length > 0 && query && visibleCount === 0);
                        }
                    }

                    paymentSearchInput.addEventListener('input', filterPaymentRows);
                    filterPaymentRows();
                }
            </script>

<?php
require_once __DIR__ . '/../../includes/staff_layout_end.php';
?>
