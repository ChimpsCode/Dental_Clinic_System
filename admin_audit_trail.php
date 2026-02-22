<?php
/**
 * Audit Trail - Admin page for viewing system activity logs
 */

$pageTitle = 'Audit Trail';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/audit_helper.php';

// Get filter parameters
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$actionFilter = isset($_GET['action']) ? trim($_GET['action']) : '';
$roleFilter = isset($_GET['role']) ? trim($_GET['role']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$dateFilter = isset($_GET['date']) ? trim($_GET['date']) : '';

// Pagination settings
$itemsPerPage = 20;
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;

// Build filter array for getAuditLogs
$filters = [];
if (!empty($searchQuery)) {
    $filters['search'] = $searchQuery;
}
if (!empty($actionFilter)) {
    $filters['action_type'] = $actionFilter;
}
if (!empty($roleFilter)) {
    $filters['user_role'] = $roleFilter;
}
if (!empty($statusFilter)) {
    $filters['status'] = $statusFilter;
}
if (!empty($dateFilter)) {
    $filters['date_from'] = $dateFilter;
    $filters['date_to'] = $dateFilter;
}

// Get audit logs with filters
$auditData = getAuditLogs($pdo, $filters, $itemsPerPage, ($currentPage - 1) * $itemsPerPage);
$auditLogs = $auditData['logs'];
$totalRecords = $auditData['total'];
$totalPages = max(1, ceil($totalRecords / $itemsPerPage));

// Calculate showing range
$showingStart = $totalRecords > 0 ? (($currentPage - 1) * $itemsPerPage) + 1 : 0;
$showingEnd = min(($currentPage - 1) * $itemsPerPage + $itemsPerPage, $totalRecords);

// Get stats
$todayStats = getAuditStats($pdo, date('Y-m-d'));

require_once __DIR__ . '/includes/admin_layout_start.php';
?>
            <div class="content-main">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Audit Trail</h2>
<!-- Export Options -->
                
                    
                    <div class="export-options">
                        <button class="btn-secondary" onclick="exportAuditLogs('csv')">Export as CSV</button>
                        <button class="btn-secondary" onclick="exportAuditLogs('excel')">Export as Excel</button>
                        <button class="btn-secondary" onclick="printAuditLogs()">Print Logs</button>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon blue">📋</div>
                        <div class="summary-info">
                            <h3><?php echo number_format($todayStats['logins_today'] ?? 0); ?></h3>
                            <p>Logins Today</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon red">❌</div>
                        <div class="summary-info">
                            <h3><?php echo number_format($todayStats['failed_logins_today'] ?? 0); ?></h3>
                            <p>Failed Logins</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon green">💰</div>
                        <div class="summary-info">
                            <h3><?php echo number_format($todayStats['payments_today'] ?? 0); ?></h3>
                            <p>Payments Today</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon yellow">📊</div>
                        <div class="summary-info">
                            <h3><?php echo number_format($todayStats['total_logs'] ?? 0); ?></h3>
                            <p>Total Logs</p>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <form method="GET" class="search-filters">
                    <input type="text" class="search-input" placeholder="Search audit logs..." name="search" id="auditSearch" value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <select class="filter-select" name="action" id="actionFilter">
                        <option value="">All Actions</option>
                        <option value="login" <?php echo $actionFilter === 'login' ? 'selected' : ''; ?>>Login</option>
                        <option value="logout" <?php echo $actionFilter === 'logout' ? 'selected' : ''; ?>>Logout</option>
                        <option value="failed_login" <?php echo $actionFilter === 'failed_login' ? 'selected' : ''; ?>>Failed Login</option>
                        <option value="create" <?php echo $actionFilter === 'create' ? 'selected' : ''; ?>>Create</option>
                        <option value="read" <?php echo $actionFilter === 'read' ? 'selected' : ''; ?>>Read</option>
                        <option value="update" <?php echo $actionFilter === 'update' ? 'selected' : ''; ?>>Update</option>
                        <option value="delete" <?php echo $actionFilter === 'delete' ? 'selected' : ''; ?>>Delete</option>
                        <option value="status_change" <?php echo $actionFilter === 'status_change' ? 'selected' : ''; ?>>Status Change</option>
                        <option value="payment" <?php echo $actionFilter === 'payment' ? 'selected' : ''; ?>>Payment</option>
                    </select>
                    <select class="filter-select" name="role" id="roleFilter">
                        <option value="">All Users</option>
                        <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="dentist" <?php echo $roleFilter === 'dentist' ? 'selected' : ''; ?>>Dentist</option>
                        <option value="staff" <?php echo $roleFilter === 'staff' ? 'selected' : ''; ?>>Staff</option>
                    </select>
                    <select class="filter-select" name="status" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="success" <?php echo $statusFilter === 'success' ? 'selected' : ''; ?>>Success</option>
                        <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                    <input type="date" class="date-input" name="date" id="dateFilter" value="<?php echo htmlspecialchars($dateFilter); ?>">
                    <a href="admin_audit_trail.php" class="btn-filter reset">Reset</a>
                </form>

                <!-- Audit Log Table -->
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Action</th>
                                <th>Module</th>
                                <th>Details</th>
                                <th>IP Address</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="auditTableBody">
                            <?php if (empty($auditLogs)): ?>
                            <tr>
                                <td colspan="8" class="no-records">
                                    <div class="empty-state">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24">
                                            <path fill="currentColor" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2m-5 14H7v-2h7zm3-4H7v-2h10zm0-4H7V7h10z"/>
                                        </svg>
                                        <p>No audit logs found</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($auditLogs as $log): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($log['username'] ?? 'Unknown'); ?></td>
                                <td>
                                    <span class="role-badge <?php echo htmlspecialchars($log['user_role'] ?? ''); ?>">
                                        <?php echo ucfirst($log['user_role'] ?? 'Unknown'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="action-badge <?php echo htmlspecialchars($log['action_type'] ?? ''); ?>">
                                        <?php echo ucfirst(htmlspecialchars($log['action_type'] ?? 'Unknown')); ?>
                                    </span>
                                </td>
                                <td><?php echo ucfirst(htmlspecialchars($log['module'] ?? 'Unknown')); ?></td>
                                <td><?php echo htmlspecialchars($log['description'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if (($log['status'] ?? 'success') === 'success'): ?>
                                    <span class="status-badge success">Success</span>
                                    <?php else: ?>
                                    <span class="status-badge failed">Failed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <span class="pagination-info">Showing <?php echo $showingStart; ?>-<?php echo $showingEnd; ?> of <?php echo $totalRecords; ?> logs</span>
                    <div class="pagination-buttons">
                        <?php if ($currentPage > 1): ?>
                            <a href="?page=<?php echo $currentPage - 1; ?>&search=<?php echo urlencode($searchQuery); ?>&action=<?php echo urlencode($actionFilter); ?>&role=<?php echo urlencode($roleFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&date=<?php echo urlencode($dateFilter); ?>" class="pagination-btn">Previous</a>
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
                            echo '<a href="?page=1&search=' . urlencode($searchQuery) . '&action=' . urlencode($actionFilter) . '&role=' . urlencode($roleFilter) . '&status=' . urlencode($statusFilter) . '&date=' . urlencode($dateFilter) . '" class="pagination-btn">1</a>';
                            if ($startPage > 2) {
                                echo '<span class="pagination-ellipsis">...</span>';
                            }
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            if ($i == $currentPage) {
                                echo '<button class="pagination-btn active">' . $i . '</button>';
                            } else {
                                echo '<a href="?page=' . $i . '&search=' . urlencode($searchQuery) . '&action=' . urlencode($actionFilter) . '&role=' . urlencode($roleFilter) . '&status=' . urlencode($statusFilter) . '&date=' . urlencode($dateFilter) . '" class="pagination-btn">' . $i . '</a>';
                            }
                        }
                        
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                echo '<span class="pagination-ellipsis">...</span>';
                            }
                            echo '<a href="?page=' . $totalPages . '&search=' . urlencode($searchQuery) . '&action=' . urlencode($actionFilter) . '&role=' . urlencode($roleFilter) . '&status=' . urlencode($statusFilter) . '&date=' . urlencode($dateFilter) . '" class="pagination-btn">' . $totalPages . '</a>';
                        }
                        ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="?page=<?php echo $currentPage + 1; ?>&search=<?php echo urlencode($searchQuery); ?>&action=<?php echo urlencode($actionFilter); ?>&role=<?php echo urlencode($roleFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&date=<?php echo urlencode($dateFilter); ?>" class="pagination-btn">Next</a>
                        <?php else: ?>
                            <button class="pagination-btn" disabled>Next</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                
            </div>

            

            <script>
                // Export audit logs
                function exportAuditLogs(format) {
                    const search = document.getElementById('auditSearch').value;
                    const action = document.getElementById('actionFilter').value;
                    const role = document.getElementById('roleFilter').value;
                    const status = document.getElementById('statusFilter').value;
                    const date = document.getElementById('dateFilter').value;
                    
                    let url = 'export_audit.php?format=' + format;
                    if (search) url += '&search=' + encodeURIComponent(search);
                    if (action) url += '&action=' + encodeURIComponent(action);
                    if (role) url += '&role=' + encodeURIComponent(role);
                    if (status) url += '&status=' + encodeURIComponent(status);
                    if (date) url += '&date=' + encodeURIComponent(date);
                    
                    window.open(url, '_blank');
                }
                
                // Print audit logs
                function printAuditLogs() {
                    window.print();
                }
            </script>

<?php
require_once __DIR__ . '/includes/admin_layout_end.php';
?>
