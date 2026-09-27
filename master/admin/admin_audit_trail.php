<?php
/**
 * Audit Trail - Admin page for viewing system activity logs
 */

$pageTitle = 'Audit Trail';

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/audit_helper.php';

purgeOldAuditLogs($pdo);

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

require_once __DIR__ . '/../../includes/admin_layout_start.php';
?>
            <div class="content-main">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Audit Trail</h2>
<!-- Export Options -->
                
                    
                    <div class="export-options">
                        <button class="btn-secondary" onclick="openAuditExport('csv')">Export as CSV</button>
                        <button class="btn-secondary" onclick="openAuditExport('excel')">Export as Excel</button>
                        <button class="btn-secondary" onclick="openAuditExport('print')">Print Logs</button>
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
                        <option value="archive" <?php echo $actionFilter === 'archive' ? 'selected' : ''; ?>>Archive</option>
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

            

            <div class="modal-overlay" id="auditRangeModal">
                <div class="modal-content audit-export-modal">
                    <div class="modal-header">
                        <h3>Choose a time frame</h3>
                        <button type="button" class="modal-close" id="closeAuditRangeModal" aria-label="Close">&times;</button>
                    </div>
                    <p class="audit-export-help">Logs older than 1 year are already removed, so 12 months is the furthest this file can go. Search, action, role, and status on this page still apply.</p>
                    <p class="audit-export-error" id="auditRangeError" hidden></p>
                    <div class="form-group">
                        <label for="auditTimeMode">Time frame</label>
                        <select class="form-control" id="auditTimeMode">
                            <option value="months" selected>Last few months</option>
                            <option value="datetime">From a date and time</option>
                        </select>
                    </div>
                    <div class="form-group" id="auditMonthsWrap">
                        <label for="auditMonths">How far back</label>
                        <select class="form-control" id="auditMonths">
                            <option value="1">1 month</option>
                            <option value="3" selected>3 months</option>
                            <option value="6">6 months</option>
                            <option value="12">12 months (all activity still on file)</option>
                        </select>
                    </div>
                    <div class="form-group" id="auditDatetimeWrap" style="display: none;">
                        <label for="auditFromDatetime">From</label>
                        <input type="datetime-local" class="form-control" id="auditFromDatetime">
                        <span class="audit-export-help">Includes logs from this moment until now.</span>
                    </div>
                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" class="btn-secondary" id="cancelAuditRange">Cancel</button>
                        <button type="button" class="btn-primary" id="continueAuditRange">Continue</button>
                    </div>
                </div>
            </div>

            <div class="modal-overlay" id="auditPasswordModal">
                <div class="modal-content audit-export-modal">
                    <div class="modal-header">
                        <h3>Confirm it is you</h3>
                        <button type="button" class="modal-close" id="closeAuditPasswordModal" aria-label="Close">&times;</button>
                    </div>
                    <p class="audit-export-help">Type your current login password. The file is created only after the password matches.</p>
                    <p class="audit-export-error" id="auditPasswordError" hidden></p>
                    <div class="form-group">
                        <label for="auditExportPassword">Current password</label>
                        <input type="password" class="form-control" id="auditExportPassword" autocomplete="current-password" placeholder="Type your login password">
                    </div>
                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" class="btn-secondary" id="backToAuditRange">Back</button>
                        <button type="button" class="btn-primary" id="confirmAuditExport">Download</button>
                    </div>
                </div>
            </div>

            <style>
                .audit-export-modal { background: white; border-radius: 12px; padding: 24px; max-width: 440px; width: 100%; }
                .audit-export-modal .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
                .audit-export-modal .modal-header h3 { margin: 0; font-size: 18px; }
                .audit-export-modal .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280; line-height: 1; }
                .audit-export-modal .form-group { margin-bottom: 16px; }
                .audit-export-modal .form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #374151; }
                .audit-export-modal .form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
                .audit-export-help { color: #4b5563; font-size: 13px; line-height: 1.5; margin: 0 0 16px; }
                .audit-export-error { color: #b91c1c; font-size: 13px; margin: 0 0 12px; }
                @media print {
                    .sidebar,
                    .sidebar-overlay,
                    .top-header,
                    .export-options,
                    .summary-cards,
                    .search-filters,
                    .pagination {
                        display: none !important;
                    }
                    .main-content {
                        margin: 0 !important;
                        padding: 0 !important;
                        width: 100% !important;
                    }
                    .table-container {
                        overflow: visible !important;
                        box-shadow: none !important;
                    }
                }
            </style>
            <script>
                var auditExportFormat = 'csv';
                var auditRangeModal = document.getElementById('auditRangeModal');
                var auditPasswordModal = document.getElementById('auditPasswordModal');
                var auditTimeMode = document.getElementById('auditTimeMode');
                var auditMonthsWrap = document.getElementById('auditMonthsWrap');
                var auditDatetimeWrap = document.getElementById('auditDatetimeWrap');
                var auditPasswordInput = document.getElementById('auditExportPassword');
                var auditPasswordError = document.getElementById('auditPasswordError');
                var auditConfirmBtn = document.getElementById('confirmAuditExport');

                function syncAuditTimeFields() {
                    var byDate = auditTimeMode.value === 'datetime';
                    auditMonthsWrap.style.display = byDate ? 'none' : '';
                    auditDatetimeWrap.style.display = byDate ? '' : 'none';
                }

                function showAuditPasswordError(message) {
                    auditPasswordError.textContent = message;
                    auditPasswordError.hidden = false;
                }

                function clearAuditPasswordError() {
                    auditPasswordError.textContent = '';
                    auditPasswordError.hidden = true;
                }

                function closeAuditExportModals() {
                    auditRangeModal.classList.remove('active');
                    auditPasswordModal.classList.remove('active');
                    auditPasswordInput.value = '';
                    clearAuditPasswordError();
                    auditConfirmBtn.disabled = false;
                    auditConfirmBtn.textContent = auditExportFormat === 'print' ? 'Print' : 'Download';
                }

                function openAuditExport(format) {
                    auditExportFormat = format;
                    auditConfirmBtn.textContent = format === 'print' ? 'Print' : 'Download';
                    clearAuditPasswordError();
                    auditRangeModal.classList.add('active');
                }

                function exportAuditLogs(format) {
                    openAuditExport(format);
                }

                function printAuditLogs() {
                    openAuditExport('print');
                }

                auditTimeMode.addEventListener('change', syncAuditTimeFields);
                document.getElementById('closeAuditRangeModal').addEventListener('click', closeAuditExportModals);
                document.getElementById('cancelAuditRange').addEventListener('click', closeAuditExportModals);
                document.getElementById('closeAuditPasswordModal').addEventListener('click', closeAuditExportModals);
                document.getElementById('continueAuditRange').addEventListener('click', function () {
                    var rangeError = document.getElementById('auditRangeError');
                    if (auditTimeMode.value === 'datetime' && !document.getElementById('auditFromDatetime').value) {
                        rangeError.textContent = 'Choose a date and time.';
                        rangeError.hidden = false;
                        return;
                    }
                    rangeError.hidden = true;
                    auditRangeModal.classList.remove('active');
                    clearAuditPasswordError();
                    auditPasswordModal.classList.add('active');
                    auditPasswordInput.focus();
                });
                document.getElementById('backToAuditRange').addEventListener('click', function () {
                    auditPasswordModal.classList.remove('active');
                    auditPasswordInput.value = '';
                    clearAuditPasswordError();
                    auditRangeModal.classList.add('active');
                });

                auditConfirmBtn.addEventListener('click', function () {
                    var password = auditPasswordInput.value;
                    if (!password) {
                        showAuditPasswordError('Enter your current password.');
                        return;
                    }
                    if (auditTimeMode.value === 'datetime' && !document.getElementById('auditFromDatetime').value) {
                        showAuditPasswordError('Choose a date and time.');
                        return;
                    }

                    auditConfirmBtn.disabled = true;
                    auditConfirmBtn.textContent = auditExportFormat === 'print' ? 'Preparing print…' : 'Preparing download…';
                    clearAuditPasswordError();

                    var formData = new FormData();
                    formData.append('format', auditExportFormat);
                    formData.append('password', password);
                    formData.append('time_mode', auditTimeMode.value);
                    formData.append('months', document.getElementById('auditMonths').value);
                    formData.append('from_datetime', document.getElementById('auditFromDatetime').value);
                    formData.append('search', document.getElementById('auditSearch').value);
                    formData.append('action', document.getElementById('actionFilter').value);
                    formData.append('role', document.getElementById('roleFilter').value);
                    formData.append('status', document.getElementById('statusFilter').value);

                    fetch('../api/export_audit.php', { method: 'POST', body: formData })
                        .then(function (res) {
                            var type = res.headers.get('content-type') || '';
                            if (type.indexOf('application/json') !== -1) {
                                return res.json().then(function (data) {
                                    throw new Error((data && data.message) || 'The export could not be created.');
                                });
                            }
                            if (!res.ok) {
                                throw new Error('The export could not be created.');
                            }
                            if (auditExportFormat === 'print') {
                                return res.text().then(function (html) {
                                    var printWindow = window.open('', '_blank');
                                    if (!printWindow) {
                                        throw new Error('Allow pop-ups to print the logs.');
                                    }
                                    printWindow.document.open();
                                    printWindow.document.write(html);
                                    printWindow.document.close();
                                });
                            }
                            var match = (res.headers.get('content-disposition') || '').match(/filename="([^"]+)"/);
                            var filename = match ? match[1] : (auditExportFormat === 'excel' ? 'audit_logs.xlsx' : 'audit_logs.csv');
                            return res.blob().then(function (blob) {
                                var url = URL.createObjectURL(blob);
                                var link = document.createElement('a');
                                link.href = url;
                                link.download = filename;
                                document.body.appendChild(link);
                                link.click();
                                link.remove();
                                URL.revokeObjectURL(url);
                            });
                        })
                        .then(function () {
                            closeAuditExportModals();
                        })
                        .catch(function (err) {
                            showAuditPasswordError(err.message || 'The export could not be created.');
                            auditConfirmBtn.disabled = false;
                            auditConfirmBtn.textContent = auditExportFormat === 'print' ? 'Print' : 'Download';
                        });
                });

                auditPasswordInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        auditConfirmBtn.click();
                    }
                });
            </script>

<?php
require_once __DIR__ . '/../../includes/admin_layout_end.php';
?>
