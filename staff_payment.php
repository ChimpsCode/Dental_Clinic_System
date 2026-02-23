<?php
/**
 * Staff Payment - View and manage payments for patients from the queue
 * Shows patients from queue with their payment status
 * Staff can mark patients as paid after dentist completes treatment
 */

$pageTitle = 'Payment';

require_once __DIR__ . '/config/database.php';

// Pagination settings
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;

// Get payment records from queue with payment status
try {
    $conn = $pdo;
    
    // Get filter parameters
    $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $dateFilter = isset($_GET['date']) ? trim($_GET['date']) : 'all';
    
    // Build base WHERE clause for reuse
    $whereClause = "WHERE q.status IN ('completed', 'pending_payment', 'in_procedure', 'waiting')";
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
            $whereClause .= " AND b.payment_status = 'paid'";
        } elseif ($statusFilter === 'unpaid') {
            $whereClause .= " AND (b.payment_status IS NULL OR b.payment_status IN ('pending', 'unpaid'))";
        }
    }
    
    // Apply date filter
    switch ($dateFilter) {
        case 'today':
            $whereClause .= " AND DATE(q.created_at) = CURDATE()";
            break;
        case 'week':
            $whereClause .= " AND q.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $whereClause .= " AND q.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
        default:
            // All time - no filter
            break;
    }
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) FROM queue q
                 LEFT JOIN patients p ON q.patient_id = p.id
                 LEFT JOIN billing b ON b.patient_id = q.patient_id AND DATE(b.billing_date) = DATE(q.created_at)
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
                q.patient_id,
                q.treatment_type,
                q.teeth_numbers,
                q.status as queue_status,
                q.created_at as queue_date,
                q.notes as queue_notes,
                CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                p.phone as patient_phone,
                b.id as billing_id,
                CONCAT('INV-', LPAD(COALESCE(b.id, q.id), 4, '0')) as invoice_number,
                COALESCE(b.total_amount, 0) as amount,
                COALESCE(b.payment_status, 'unpaid') as payment_status,
                b.billing_date,
                b.paid_amount,
                b.notes as billing_notes
            FROM queue q
            LEFT JOIN patients p ON q.patient_id = p.id
            LEFT JOIN billing b ON b.patient_id = q.patient_id AND DATE(b.billing_date) = DATE(q.created_at)
            $whereClause
            ORDER BY q.created_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $paymentRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary statistics based directly on today's queue + billing rows
    // Get summary statistics directly from source tables (payments, billing, queue)
    $statsSql = "
        SELECT 
            (SELECT COALESCE(SUM(amount), 0)
             FROM payments
             WHERE DATE(payment_date) = CURDATE()) AS total_collected_today,
             
            (SELECT COALESCE(SUM(total_amount), 0)
             FROM billing
             WHERE payment_status IN ('pending', 'unpaid')
               AND DATE(billing_date) = CURDATE()) AS pending_today,
               
            (SELECT COUNT(*)
             FROM queue
             WHERE status = 'completed'
               AND DATE(created_at) = CURDATE()) AS completed_today,
               
            (SELECT COUNT(*)
             FROM billing
             WHERE payment_status IN ('pending', 'unpaid')
               AND DATE(billing_date) = CURDATE()) AS unpaid_today
    ";
    $statsStmt = $conn->query($statsSql);
    $stats = $statsStmt ? $statsStmt->fetch(PDO::FETCH_ASSOC) : [
        'total_collected_today' => 0,
        'pending_today' => 0,
        'completed_today' => 0,
        'unpaid_today' => 0
    ];
    
    // Get services for calculating amounts
    $servicesStmt = $conn->query("SELECT id, name, price FROM services WHERE is_active = 1");
    $servicesList = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);
    $servicesMap = [];
    foreach ($servicesList as $service) {
        $servicesMap[strtolower($service['name'])] = $service['price'];
    }
    
} catch (Exception $e) {
    $paymentRecords = [];
    $stats = [
        'total_collected_today' => 0,
        'pending_today' => 0,
        'completed_today' => 0,
        'unpaid_today' => 0
    ];
    $servicesMap = [];
    $totalRecords = 0;
    $totalPages = 1;
    $showingStart = 0;
    $showingEnd = 0;
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

require_once __DIR__ . '/includes/staff_layout_start.php';
?>
            <div class="content-main">
                

                <!-- Stats Cards -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon green">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/>
                            </svg>
                        </div>
                        <div class="summary-info">
                            <h3>P<?php echo number_format($stats['total_collected_today'] ?? 0, 2); ?></h3>
                            <p>Collected Today</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon yellow">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2m0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8s8 3.59 8 8s-3.59 8-8 8m.5-13H11v6l5.2 3.2l.8-1.3l-4.5-2.7z"/>
                            </svg>
                        </div>
                        <div class="summary-info">
                            <h3>P<?php echo number_format($stats['pending_today'] ?? 0, 2); ?></h3>
                            <p>Pending Today</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon blue">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M9 16.17L4.83 12l-1.42 1.41L9 19L21 7l-1.41-1.41z"/>
                            </svg>
                        </div>
                        <div class="summary-info">
                            <h3><?php echo number_format($stats['completed_today'] ?? 0); ?></h3>
                            <p>Completed Today</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon red">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10s10-4.48 10-10S17.52 2 12 2m1 15h-2v-2h2zm0-4h-2V7h2z"/>
                            </svg>
                        </div>
                        <div class="summary-info">
                            <h3><?php echo number_format($stats['unpaid_today'] ?? 0); ?></h3>
                            <p>Unpaid Today</p>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="search-filters">
                    <form method="GET" class="filter-form" id="filterForm">
                        <input type="text" class="search-input" placeholder="Search by patient name or treatment..." 
                               name="search" id="paymentSearch" value="<?php echo htmlspecialchars($searchQuery); ?>">
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
                </div>

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
                                $isPaid = ($record['payment_status'] === 'paid');
                                $calculatedAmount = $record['amount'] > 0 ? $record['amount'] : calculateAmount($record['treatment_type'], $servicesMap);
                            ?>
                            <tr data-queue-id="<?php echo $record['queue_id']; ?>" data-patient-id="<?php echo $record['patient_id']; ?>">
                                <td><strong>Q-<?php echo str_pad($record['queue_id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
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
                                    <?php if ($record['teeth_numbers']): ?>
                                    <span class="teeth-info">Teeth: <?php echo htmlspecialchars($record['teeth_numbers']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><strong>P<?php echo number_format($calculatedAmount, 2); ?></strong></td>
                                <td>
                                    <?php if ($record['queue_status'] === 'completed'): ?>
                                        <span class="status-badge queue-completed">Completed</span>
                                    <?php elseif ($record['queue_status'] === 'pending_payment'): ?>
                                        <span class="status-badge queue-pending-payment">Pending Payment</span>
                                    <?php elseif ($record['queue_status'] === 'in_procedure'): ?>
                                        <span class="status-badge queue-in-procedure">In Procedure</span>
                                    <?php else: ?>
                                        <span class="status-badge queue-waiting">Waiting</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isPaid): ?>
                                        <span class="status-badge paid">Paid</span>
                                    <?php else: ?>
                                        <span class="status-badge unpaid">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-cell">
                                    <div class="pay-kebab-menu">
                                        <button
                                            class="pay-kebab-btn"
                                            data-queue-id="<?php echo $record['queue_id']; ?>"
                                            data-patient-id="<?php echo $record['patient_id']; ?>"
                                            data-amount="<?php echo $calculatedAmount; ?>"
                                            data-treatment="<?php echo htmlspecialchars($record['treatment_type'] ?: 'General Checkup'); ?>"
                                            data-is-paid="<?php echo $isPaid ? '1' : '0'; ?>"
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
                <div class="modal">
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
                                <span class="detail-label">Amount:</span>
                                <span class="detail-value" id="confirmPaymentAmount">P0.00</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Treatment:</span>
                                <span class="detail-value" id="confirmPaymentTreatment">-</span>
                            </div>
                        </div>
                        <input type="hidden" id="confirmPaymentQueueId">
                        <input type="hidden" id="confirmPaymentPatientId">
                        <input type="hidden" id="confirmPaymentAmountValue">
                        <input type="hidden" id="confirmPaymentTreatmentValue">
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" onclick="closeConfirmPaymentModal()">Cancel</button>
                        <button type="button" class="btn-primary" onclick="confirmPayment()">Confirm Payment</button>
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
                .summary-icon.blue { background: #dbeafe; color: #2563eb; }
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

                .search-filters {
                    background: white;
                    padding: 1rem;
                    border-radius: 12px;
                    margin-bottom: 1rem;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }

                .filter-form {
                    display: flex;
                    gap: 0.75rem;
                    flex-wrap: wrap;
                    align-items: center;
                }

                .search-input {
                    flex: 1;
                    min-width: 200px;
                    padding: 0.5rem 1rem;
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

                .status-badge.unpaid {
                    background: #fef3c7;
                    color: #d97706;
                }

                .status-badge.queue-completed {
                    background: #dbeafe;
                    color: #2563eb;
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
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
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
                    background-color: #2563eb;
                    color: #ffffff;
                    border-color: #2563eb;
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
                    background: #f3f4f6;
                    border: none;
                    border-radius: 8px;
                    cursor: pointer;
                    font-weight: 500;
                }

                .btn-cancel:hover {
                    background: #e5e7eb;
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
                // Portal Pattern: Move modals to body level to escape stacking context
                // This ensures modals appear above sidebar and all other elements
                (function() {
                    const paymentModal = document.getElementById('paymentModal');
                    const confirmPaymentModal = document.getElementById('confirmPaymentModal');
                    
                    if (paymentModal) {
                        document.body.appendChild(paymentModal);
                    }
                    if (confirmPaymentModal) {
                        document.body.appendChild(confirmPaymentModal);
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
                    const isPaid = button.dataset.isPaid === '1';

                    return `
                        <a href="javascript:void(0)" data-action="view" data-queue-id="${queueId}" data-patient-id="${patientId}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24">
                                <path fill="none" stroke="currentColor" stroke-width="2" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            View Details
                        </a>
                        ${!isPaid ? `
                        <a href="javascript:void(0)" data-action="mark-paid" data-queue-id="${queueId}" data-patient-id="${patientId}" data-amount="${amount}" data-treatment="${treatment}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24">
                                <path fill="none" stroke="currentColor" stroke-width="2" d="M20 6 9 17l-5-5"/>
                            </svg>
                            Mark as Paid
                        </a>` : ''}
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
                    const amount = link.dataset.amount;
                    const treatment = link.dataset.treatment;

                    closePayKebabDropdown();

                    switch (action) {
                        case 'view':
                            viewPaymentDetails(queueId, patientId);
                            break;
                        case 'mark-paid':
                            markAsPaid(queueId, patientId, amount, treatment);
                            break;
                    }
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

                function viewPaymentDetails(queueId, patientId) {
                    // Close kebab menu
                    closePayKebabDropdown();
                    
                    // Fetch and display details
                    document.getElementById('paymentModal').style.display = 'flex';
                    document.getElementById('paymentModalContent').innerHTML = `
                        <div style="text-align: center; padding: 2rem;">
                            <p>Loading payment details...</p>
                        </div>
                    `;
                    
                    // Fetch details via AJAX
                    fetch('billing_actions.php?action=get_details&queue_id=' + queueId + '&patient_id=' + patientId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                document.getElementById('paymentModalContent').innerHTML = `
                                    <div class="billing-detail-view">
                                        <div class="detail-row">
                                            <span class="detail-label">Patient:</span>
                                            <span class="detail-value">${data.patient_name}</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Treatment:</span>
                                            <span class="detail-value">${data.treatment_type || 'General Checkup'}</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Teeth Numbers:</span>
                                            <span class="detail-value">${data.teeth_numbers || 'N/A'}</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Queue Status:</span>
                                            <span class="detail-value">${data.queue_status}</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Amount:</span>
                                            <span class="detail-value">P${parseFloat(data.amount).toFixed(2)}</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Payment Status:</span>
                                            <span class="detail-value">${data.payment_status}</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Date:</span>
                                            <span class="detail-value">${data.queue_date}</span>
                                        </div>
                                    </div>
                                `;
                            } else {
                                document.getElementById('paymentModalContent').innerHTML = `
                                    <p style="color: #dc2626;">Error loading details: ${data.message}</p>
                                `;
                            }
                        })
                        .catch(error => {
                            document.getElementById('paymentModalContent').innerHTML = `
                                <p style="color: #dc2626;">Error loading details. Please try again.</p>
                            `;
                        });
                }

                function closePaymentModal() {
                    document.getElementById('paymentModal').style.display = 'none';
                }

                function markAsPaid(queueId, patientId, amount, treatment) {
                    // Close kebab menu
                    closePayKebabDropdown();
                    
                    // Set modal values
                    document.getElementById('confirmPaymentQueueId').value = queueId;
                    document.getElementById('confirmPaymentPatientId').value = patientId;
                    document.getElementById('confirmPaymentAmountValue').value = amount;
                    document.getElementById('confirmPaymentTreatmentValue').value = treatment;
                    document.getElementById('confirmPaymentAmount').textContent = 'P' + parseFloat(amount).toFixed(2);
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
                    const amount = document.getElementById('confirmPaymentAmountValue').value;
                    const treatment = document.getElementById('confirmPaymentTreatmentValue').value;
                    
                    // Send AJAX request to mark as paid
                    fetch('billing_actions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'mark_paid',
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

                // Submit form on Enter key in search input
                document.getElementById('paymentSearch').addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        document.getElementById('filterForm').submit();
                    }
                });
            </script>

<?php
require_once __DIR__ . '/includes/staff_layout_end.php';
?>
