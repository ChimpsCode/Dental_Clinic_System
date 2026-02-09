<?php
$pageTitle = 'Queue Management';
require_once 'config/database.php';
require_once 'includes/staff_layout_start.php';

// Pagination settings
$itemsPerPage = 7;
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;

try {
    // Get ALL queue data for counts (without pagination)
    $allStmt = $pdo->query("
        SELECT q.*, p.first_name, p.middle_name, p.last_name, p.suffix, p.phone
        FROM queue q 
        LEFT JOIN patients p ON q.patient_id = p.id 
        WHERE q.status IN ('waiting', 'in_procedure', 'completed', 'on_hold', 'cancelled')
        ORDER BY 
            CASE q.status 
                WHEN 'in_procedure' THEN 1 
                WHEN 'waiting' THEN 2 
                WHEN 'on_hold' THEN 3 
                WHEN 'completed' THEN 4 
                WHEN 'cancelled' THEN 5 
            END,
            q.queue_time DESC
    ");
    $allQueueItems = $allStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate counts from all items
    $waitingCount = count(array_filter($allQueueItems, fn($q) => $q['status'] === 'waiting'));
    $procedureCount = count(array_filter($allQueueItems, fn($q) => $q['status'] === 'in_procedure'));
    $completedToday = count(array_filter($allQueueItems, fn($q) => $q['status'] === 'completed'));
    $onHoldCount = count(array_filter($allQueueItems, fn($q) => $q['status'] === 'on_hold'));
    $cancelledCount = count(array_filter($allQueueItems, fn($q) => $q['status'] === 'cancelled'));
    $totalInQueue = $waitingCount + $procedureCount + $onHoldCount;
    
    // Pagination calculations
    $totalQueueItems = count($allQueueItems);
    $totalPages = max(1, ceil($totalQueueItems / $itemsPerPage));
    
    if ($currentPage > $totalPages) $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    // Calculate showing range
    $showingStart = $totalQueueItems > 0 ? $offset + 1 : 0;
    $showingEnd = min($offset + $itemsPerPage, $totalQueueItems);
    
    // Get paginated queue items
    $queueItems = array_slice($allQueueItems, $offset, $itemsPerPage);
    
} catch (Exception $e) {
    $queueItems = [];
    $allQueueItems = [];
    $waitingCount = 0;
    $procedureCount = 0;
    $completedToday = 0;
    $onHoldCount = 0;
    $cancelledCount = 0;
    $totalInQueue = 0;
    $totalQueueItems = 0;
    $totalPages = 1;
    $showingStart = 0;
    $showingEnd = 0;
}
?>

<style>
/* Ensure consistent background */
.content-area {
    background-color: #f3f4f6;
}

/* Queue Kebab Menu Styles - Portal Based */
.queue-kebab-menu {
    position: relative;
    display: inline-block;
}

.queue-kebab-btn {
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

.queue-kebab-btn:hover {
    background-color: #f3f4f6;
    color: #374151;
}

.queue-kebab-btn.active {
    background-color: #e5e7eb;
    color: #111827;
}

.queue-kebab-dropdown-portal {
    display: none;
    position: fixed;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    min-width: 160px;
    max-width: 200px;
    width: auto;
    z-index: 99999;
    overflow: hidden;
}

.queue-kebab-dropdown-portal.show {
    display: block;
    animation: queueKebabFadeIn 0.15s ease;
}

@keyframes queueKebabFadeIn {
    from { opacity: 0; transform: scale(0.95) translateY(-4px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.queue-kebab-dropdown-portal a {
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

.queue-kebab-dropdown-portal a:hover {
    background-color: #f9fafb;
    color: #111827;
}

.queue-kebab-dropdown-portal a svg {
    flex-shrink: 0;
}

.queue-kebab-dropdown-portal a:first-child {
    border-radius: 8px 8px 0 0;
}

.queue-kebab-dropdown-portal a:last-child {
    border-radius: 0 0 8px 8px;
}

.queue-kebab-dropdown-portal a.danger {
    color: #dc2626;
}

.queue-kebab-dropdown-portal a.danger:hover {
    background-color: #fef2f2;
}

.queue-kebab-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 99998;
}

.queue-kebab-backdrop.show {
    display: block;
}

/* Pagination Styles */
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

/* Top Summary Widgets */
.summary-widgets {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

.summary-widget {
    background: white;
    border-radius: 12px;
    padding: 24px;
    border: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.2s;
}

.summary-widget:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.summary-widget-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
}

.summary-widget-icon.yellow {
    background: #fef3c7;
    color: #d97706;
}

.summary-widget-icon.green {
    background: #dcfce7;
    color: #16a34a;
}

.summary-widget-icon.blue {
    background: #dbeafe;
    color: #2563eb;
}

.summary-widget-icon.red {
    background: #fee2e2;
    color: #dc2626;
}

.summary-widget-icon.gray {
    background: #f3f4f6;
    color: #6b7280;
}

.summary-widget-info h3 {
    font-size: 2rem;
    font-weight: 700;
    color: #111827;
    margin: 0;
    line-height: 1;
}

.summary-widget-info p {
    font-size: 0.875rem;
    color: #6b7280;
    margin: 4px 0 0 0;
}

/* Control Bar */
.control-bar {
    background: white;
    border-radius: 12px;
    padding: 20px 24px;
    border: 1px solid #e5e7eb;
    margin-bottom: 24px;
    display: flex;
    gap: 16px;
    align-items: center;
}

.control-bar .search-input {
    flex: 1;
    padding: 12px 16px 12px 44px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.9rem;
    outline: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='%236b7280'%3E%3Cpath d='m19.6 21l-6.3-6.3q-.75.6-1.725.95T9.5 16q-2.725 0-4.612-1.888T3 9.5t1.888-4.612T9.5 3t4.613 1.888T16 9.5q0 1.1-.35 2.075T14.7 13.3l6.3 6.3zM9.5 14q1.875 0 3.188-1.312T14 9.5t-1.312-3.187T9.5 5T6.313 6.313T5 9.5t1.313 3.188T9.5 14'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: 14px center;
    transition: border-color 0.2s;
}

.control-bar .search-input:focus {
    border-color: #2563eb;
}

.control-bar .filter-select {
    padding: 12px 16px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.9rem;
    background: white;
    cursor: pointer;
    min-width: 150px;
}

.control-bar .btn-add-patient {
    padding: 12px 24px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background 0.2s;
}

.control-bar .btn-add-patient:hover {
    background: #1d4ed8;
}

/* Two Column Layout */
.two-column-layout {
    display: grid;
    grid-template-columns: 100% 20%;
    gap: 24px;
    align-items: start;
}

/* Main Content (Left Column) */
div.main-content {
    margin-left: 0px;
}

.main-content {
    background: white;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    height: auto;
    min-height: auto;
}

/* Data Table Container - auto height */
.queue-table-container {
    overflow-x: auto;
    height: auto;
    max-height: none;
}

/* Tabbed Navigation */
.tabs-navigation {
    display: flex;
    border-bottom: 2px solid #f3f4f6;
    background: #fafbfc;
    padding: 0 24px;
}

.tab-item {
    padding: 16px 24px;
    cursor: pointer;
    font-weight: 500;
    color: #6b7280;
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
    position: relative;
}

.tab-item:hover {
    color: #374151;
    background: rgba(0, 0, 0, 0.02);
}

.tab-item.active {
    color: #2563eb;
    border-bottom-color: #2563eb;
    background: white;
}

.tab-count {
    display: inline-block;
    background: #e5e7eb;
    color: #6b7280;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    margin-left: 6px;
    font-weight: 600;
}

.tab-item.active .tab-count {
    background: #dbeafe;
    color: #2563eb;
}

/* Data Table */
.queue-table {
    width: 100%;
    border-collapse: collapse;
}

.queue-table thead {
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
}

.queue-table th {
    padding: 14px 20px;
    text-align: left;
    font-weight: 600;
    color: #6b7280;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.queue-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #f3f4f6;
    color: #374151;
    font-size: 0.9rem;
}

.queue-table tbody tr {
    transition: background 0.15s;
}

.queue-table tbody tr:hover {
    background: #f9fafb;
}

.patient-name {
    font-weight: 600;
    color: #111827;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 5px 12px;
    border-radius: 16px;
    font-size: 0.75rem;
    font-weight: 600;
    gap: 6px;
}

.status-badge.waiting {
    background: #fef3c7;
    color: #92400e;
}

.status-badge.in-procedure {
    background: #dbeafe;
    color: #1e40af;
}

.status-badge.on-hold {
    background: #fed7aa;
    color: #9a3412;
}

.status-badge.completed {
    background: #dcfce7;
    color: #166534;
}

.status-badge.cancelled {
    background: #fee2e2;
    color: #991b1b;
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.status-badge.waiting .status-dot {
    background: #d97706;
}

.status-badge.in-procedure .status-dot {
    background: #2563eb;
}

.status-badge.on-hold .status-dot {
    background: #ea580c;
}

.status-badge.completed .status-dot {
    background: #16a34a;
}

.status-badge.cancelled .status-dot {
    background: #dc2626;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.action-btn {
    padding: 6px 12px;
    border: 1px solid #d1d5db;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
    color: #374151;
    transition: all 0.2s;
}

.action-btn:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
}

.action-btn.primary {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
}

.action-btn.primary:hover {
    background: #1d4ed8;
}

.more-dropdown {
    position: relative;
}

.more-btn {
    padding: 6px 10px;
    border: 1px solid #d1d5db;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.more-btn:hover {
    background: #f3f4f6;
}

.time-display {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.time-12hr {
    font-weight: 600;
    color: #111827;
}

.time-24hr {
    font-size: 0.75rem;
    color: #6b7280;
}



.widget-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    padding: 20px;
}

.widget-title {
    font-size: 1rem;
    font-weight: 600;
    color: #111827;
    margin: 0 0 16px 0;
}

.widget-card .btn-primary,
.widget-card .btn-secondary {
    width: 100%;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    display: block;
    transition: all 0.2s;
    margin-bottom: 12px;
}

.widget-card .btn-primary:last-child,
.widget-card .btn-secondary:last-child {
    margin-bottom: 0;
}

.widget-card .btn-primary {
    background: #2563eb;
    color: white;
    border: none;
}

.widget-card .btn-primary:hover {
    background: #1d4ed8;
}

.widget-card .btn-secondary {
    background: white;
    color: #374151;
    border: 1px solid #d1d5db;
}

.widget-card .btn-secondary:hover {
    background: #f3f4f6;
}

.queue-summary-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.queue-summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f3f4f6;
}

.queue-summary-item:last-child {
    border-bottom: none;
}

.queue-summary-label {
    color: #6b7280;
    font-size: 0.9rem;
}

.queue-summary-value {
    font-weight: 700;
    font-size: 1.1rem;
    color: #111827;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6b7280;
}

.empty-state svg {
    width: 64px;
    height: 64px;
    margin: 0 auto 16px;
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 1.1rem;
    color: #374151;
    margin: 0 0 8px 0;
}

.empty-state p {
    font-size: 0.9rem;
    margin: 0;
}


</style>

<!-- Top Summary Widgets -->
<div class="summary-widgets">
    <div class="summary-widget">
        <div class="summary-widget-icon yellow">⏰</div>
        <div class="summary-widget-info">
            <h3><?php echo $waitingCount; ?></h3>
            <p>Waiting</p>
        </div>
    </div>
    <div class="summary-widget">
        <div class="summary-widget-icon blue">⚙</div>
        <div class="summary-widget-info">
            <h3><?php echo $procedureCount; ?></h3>
            <p>In Procedure</p>
        </div>
    </div>
    <div class="summary-widget">
        <div class="summary-widget-icon green">✓</div>
        <div class="summary-widget-info">
            <h3><?php echo $completedToday; ?></h3>
            <p>Completed</p>
        </div>
    </div>
    <div class="summary-widget">
        <div class="summary-widget-icon gray">⏸</div>
        <div class="summary-widget-info">
            <h3><?php echo $onHoldCount; ?></h3>
            <p>On Hold</p>
        </div>
    </div>
    <div class="summary-widget">
        <div class="summary-widget-icon red">✕</div>
        <div class="summary-widget-info">
            <h3><?php echo $cancelledCount; ?></h3>
            <p>Cancelled</p>
        </div>
    </div>
</div>

<!-- Control Bar -->
<div class="control-bar">
    <input type="text" class="search-input" id="queueSearch" placeholder="Search by name, treatment...">
    <select class="filter-select" id="statusFilter">
        <option value="">All Status</option>
        <option value="waiting">Waiting</option>
        <option value="in_procedure">In Procedure</option>
        <option value="on_hold">On Hold</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
    </select>
    <a href="staff_new_admission.php" class="btn-add-patient">
        <span>+</span> Add New Patient
    </a>
</div>

<!-- Two Column Layout -->
<div class="two-column-layout">
    <!-- Left Column: Main Content -->
    <div class="main-content">
        <!-- Tabbed Navigation -->
        <div class="tabs-navigation">
            <div class="tab-item active" data-tab="all">
                All <span class="tab-count"><?php echo count($queueItems); ?></span>
            </div>
            <div class="tab-item" data-tab="waiting">
                Waiting <span class="tab-count"><?php echo $waitingCount; ?></span>
            </div>
            <div class="tab-item" data-tab="in_procedure">
                In Procedure <span class="tab-count"><?php echo $procedureCount; ?></span>
            </div>
            <div class="tab-item" data-tab="on_hold">
                On Hold <span class="tab-count"><?php echo $onHoldCount; ?></span>
            </div>
            <div class="tab-item" data-tab="completed">
                Completed <span class="tab-count"><?php echo $completedToday; ?></span>
            </div>
        </div>

        <!-- Data Table -->
        <div class="queue-table-container">
            <table class="queue-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>First Name</th>
                        <th>Middle Name</th>
                        <th>Last Name</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Treatment</th>
                        <th>Assigned Doctor</th>
                        <th>Time In</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="queueTableBody">
                    <?php if (empty($queueItems)): ?>
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                    </svg>
                                    <h3>No patients in queue</h3>
                                    <p>Add new patients to get started with queue management</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($queueItems as $index => $item):
                            $queueTime = new DateTime($item['queue_time']);
                            $time12hr = $queueTime->format('g:i A');
                            $time24hr = $queueTime->format('H:i');
                            $statusClass = strtolower(str_replace('_', '-', $item['status']));
                            $statusLabel = ucwords(str_replace('_', ' ', $item['status']));
                        ?>
                            <tr class="queue-row" data-status="<?php echo $item['status']; ?>" data-name="<?php echo strtolower(($item['first_name'] ?? '')); ?>">
                                <td><?php echo str_pad($item['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <div class="patient-name"><?php echo htmlspecialchars($item['first_name'] ?: 'Unknown'); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($item['middle_name'] ?? ''); ?></td>
                                <td>
                                    <div class="patient-name"><?php echo htmlspecialchars($item['last_name'] ?: 'Unknown'); ?></div>
                                </td>
                                <td>
                                    <div style="font-size: 0.75rem; color: #6b7280;">
                                        <?php echo htmlspecialchars($item['phone'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <span class="status-dot"></span>
                                        <?php echo $statusLabel; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($item['treatment_type'] ?? 'General Checkup'); ?></td>
                                <td>Dr. Rex</td>
                                <td>
                                    <div class="time-display">
                                        <span class="time-12hr"><?php echo $time12hr; ?></span>
                                        <span class="time-24hr"><?php echo $time24hr; ?></span>
                                    </div>
                                </td>
<td>
                                    <div class="queue-kebab-menu">
                                        <button class="queue-kebab-btn" data-queue-id="<?php echo $item['id']; ?>" data-patient-id="<?php echo $item['patient_id']; ?>" data-status="<?php echo $item['status']; ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                                <circle cx="12" cy="6" r="2"/>
                                                <circle cx="12" cy="12" r="2"/>
                                                <circle cx="12" cy="18" r="2"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
</tbody>
            </table>
        </div>
    </div>

    <!-- Right Column: Sidebar -->
    
</div>

<!-- Pagination - Outside the box -->
<?php if ($totalQueueItems > 0): ?>
<div class="pagination">
    <span class="pagination-info">Showing <?php echo $showingStart; ?>-<?php echo $showingEnd; ?> of <?php echo $totalQueueItems; ?> patients</span>
    <div class="pagination-buttons">
        <?php if ($currentPage > 1): ?>
            <a href="?page=<?php echo $currentPage - 1; ?>" class="pagination-btn">Previous</a>
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
            <a href="?page=1" class="pagination-btn">1</a>
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
                <a href="?page=<?php echo $i; ?>" class="pagination-btn"><?php echo $i; ?></a>
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
            <a href="?page=<?php echo $totalPages; ?>" class="pagination-btn"><?php echo $totalPages; ?></a>
            <?php
        }
        ?>
        
        <?php if ($currentPage < $totalPages): ?>
            <a href="?page=<?php echo $currentPage + 1; ?>" class="pagination-btn">Next</a>
        <?php else: ?>
            <button class="pagination-btn" disabled>Next</button>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
// Queue Kebab Menu - Portal Based (same as patient-records)
let queueKebabDropdown = null;
let queueKebabBackdrop = null;
let queueActiveButton = null;

function createQueueKebabDropdown() {
    queueKebabDropdown = document.createElement('div');
    queueKebabDropdown.className = 'queue-kebab-dropdown-portal';
    queueKebabDropdown.id = 'queueKebabDropdownPortal';
    document.body.appendChild(queueKebabDropdown);

    queueKebabBackdrop = document.createElement('div');
    queueKebabBackdrop.className = 'queue-kebab-backdrop';
    queueKebabBackdrop.id = 'queueKebabBackdrop';
    document.body.appendChild(queueKebabBackdrop);

    queueKebabBackdrop.addEventListener('click', closeQueueKebabDropdown);
}

function getQueueMenuItems(queueId, patientId, status) {
    // In Procedure: Show only View
    if (status === 'in_procedure') {
        return `
        <a href="javascript:void(0)" data-action="view" data-queue-id="${queueId}" data-patient-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            View
        </a>
        `;
    }
    
    // Completed: Show View and Delete
    if (status === 'completed') {
        return `
        <a href="javascript:void(0)" data-action="view" data-queue-id="${queueId}" data-patient-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            View
        </a>
        <a href="javascript:void(0)" class="danger" data-action="delete" data-queue-id="${queueId}" data-patient-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6"></polyline>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                <line x1="10" y1="11" x2="10" y2="17"></line>
                <line x1="14" y1="11" x2="14" y2="17"></line>
            </svg>
            Delete
        </a>
        `;
    }
    
    // Cancelled: Show View, Re-queue, and Delete
    if (status === 'cancelled') {
        return `
        <a href="javascript:void(0)" data-action="view" data-queue-id="${queueId}" data-patient-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            View
        </a>
        <a href="javascript:void(0)" data-action="requeue" data-queue-id="${queueId}" data-patient-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="23 4 23 10 17 10"></polyline>
                <polyline points="1 20 1 14 7 14"></polyline>
                <path d="M3.51 9a9 9 0 0 1 14.85-3.36M20.49 15a9 9 0 0 1-14.85 3.36"></path>
            </svg>
            Re-queue
        </a>
        <a href="javascript:void(0)" class="danger" data-action="delete" data-queue-id="${queueId}" data-patient-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6"></polyline>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                <line x1="10" y1="11" x2="10" y2="17"></line>
                <line x1="14" y1="11" x2="14" y2="17"></line>
            </svg>
            Delete
        </a>
        `;
    }
    
    // Default: Show View, Hold, Re-queue (if on_hold), Cancel
    return `
        <a href="javascript:void(0)" data-action="view" data-queue-id="${queueId}" data-patient-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            View
        </a>
        <a href="javascript:void(0)" data-action="hold" data-queue-id="${queueId}" data-patient-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="6" y="4" width="4" height="16"></rect>
                <rect x="14" y="4" width="4" height="16"></rect>
            </svg>
            Hold
        </a>
        ${status === 'on_hold' ? `
        <a href="javascript:void(0)" data-action="requeue" data-queue-id="${queueId}" data-patient-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="23 4 23 10 17 10"></polyline>
                <polyline points="1 20 1 14 7 14"></polyline>
                <path d="M3.51 9a9 9 0 0 1 14.85-3.36M20.49 15a9 9 0 0 1-14.85 3.36"></path>
            </svg>
            Re-queue
        </a>
        ` : ''}
        <a href="javascript:void(0)" class="danger" data-action="cancel" data-queue-id="${queueId}" data-patient-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
            Cancel
        </a>
    `;
}

function positionQueueKebabDropdown(button) {
    if (!queueKebabDropdown || !button) return;

    const rect = button.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    const padding = 15;
    const dropdownWidth = 160;
    const dropdownHeight = 130;
    
    let left = rect.right + 5;
    let top = rect.top;

    // Position to the left if not enough space on right
    if (left + dropdownWidth > viewportWidth - padding) {
        left = rect.left - dropdownWidth - 5;
    }
    
    if (left < padding) {
        left = padding;
    }
    
    // Position above if not enough space below
    if (top + dropdownHeight > viewportHeight - padding) {
        top = rect.bottom - dropdownHeight;
    }
    
    if (top < padding) {
        top = padding;
    }

    queueKebabDropdown.style.left = left + 'px';
    queueKebabDropdown.style.top = top + 'px';
}

function openQueueKebabDropdown(button) {
    if (!queueKebabDropdown) {
        createQueueKebabDropdown();
    }

    const queueId = button.dataset.queueId;
    const patientId = button.dataset.patientId;
    const status = button.dataset.status;

    queueKebabDropdown.innerHTML = getQueueMenuItems(queueId, patientId, status);
    positionQueueKebabDropdown(button);

    queueKebabDropdown.classList.add('show');
    queueKebabBackdrop.classList.add('show');
    queueActiveButton = button;
    button.classList.add('active');

    queueKebabDropdown.addEventListener('click', handleQueueKebabClick);
}

function closeQueueKebabDropdown() {
    if (queueKebabDropdown) {
        queueKebabDropdown.classList.remove('show');
        queueKebabDropdown.innerHTML = '';
    }
    if (queueKebabBackdrop) {
        queueKebabBackdrop.classList.remove('show');
    }
    if (queueActiveButton) {
        queueActiveButton.classList.remove('active');
        queueActiveButton = null;
    }
}

function handleQueueKebabClick(e) {
    const link = e.target.closest('a[data-action]');
    if (!link) return;

    e.preventDefault();
    e.stopPropagation();

    const action = link.dataset.action;
    const queueId = parseInt(link.dataset.queueId);
    const patientId = parseInt(link.dataset.patientId);

    closeQueueKebabDropdown();

    switch(action) {
        case 'view':
            viewPatientQueue(patientId);
            break;
        case 'hold':
            holdPatientQueue(queueId);
            break;
        case 'requeue':
            requeuePatientQueue(queueId);
            break;
        case 'cancel':
            cancelPatientQueue(queueId);
            break;
        case 'delete':
            deleteQueueItem(queueId);
            break;
    }
}

// Click handler for kebab buttons
document.addEventListener('click', function(e) {
    const button = e.target.closest('.queue-kebab-btn');
    if (button) {
        e.preventDefault();
        e.stopPropagation();

        if (queueActiveButton === button && queueKebabDropdown && queueKebabDropdown.classList.contains('show')) {
            closeQueueKebabDropdown();
        } else {
            if (queueActiveButton) {
                queueActiveButton.classList.remove('active');
            }
            openQueueKebabDropdown(button);
        }
        return;
    }
});

// View Patient
function viewPatientQueue(patientId) {
    fetch('get_patient.php?id=' + patientId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const p = data.patient;
                const fullName = `${p.first_name || ''} ${p.middle_name || ''} ${p.last_name || ''} ${p.suffix || ''}`.trim();
                alert('Patient: ' + (fullName || 'Unknown') + '\nPhone: ' + (data.patient.phone || 'N/A'));
            } else {
                alert('Patient details not found');
            }
        })
        .catch(() => {
            alert('Viewing patient ID: ' + patientId);
        });
}

// Hold Patient
function holdPatientQueue(queueId) {
    if (!confirm('Put this patient on hold?')) return;
    
    fetch('queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'on_hold', queue_id: queueId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Failed to put patient on hold');
        }
    })
    .catch(() => {
        alert('Error putting patient on hold');
    });
}

// Re-queue Patient
function requeuePatientQueue(queueId) {
    if (!confirm('Re-queue this patient?')) return;
    
    fetch('queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'requeue', queue_id: queueId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Failed to re-queue patient');
        }
    })
    .catch(() => {
        alert('Error re-queuing patient');
    });
}

// Cancel Patient
function cancelPatientQueue(queueId) {
    if (!confirm('Cancel this patient from the queue?')) return;
    
    fetch('queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'cancel', queue_id: queueId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Failed to cancel patient');
        }
    })
    .catch(() => {
        alert('Error cancelling patient');
    });
}

// Delete Queue Item
function deleteQueueItem(queueId) {
    if (!confirm('Delete this patient record from the queue? This action cannot be undone.')) return;
    
    fetch('queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', queue_id: queueId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Failed to delete patient record');
        }
    })
    .catch(() => {
        alert('Error deleting patient record');
    });
}

// Tab switching functionality
document.querySelectorAll('.tab-item').forEach(tab => {
    tab.addEventListener('click', function() {
        // Remove active class from all tabs
        document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
        
        // Add active class to clicked tab
        this.classList.add('active');
        
        // Filter table rows
        const filterStatus = this.dataset.tab;
        filterQueueTable(filterStatus);
    });
});

// Search functionality
document.getElementById('queueSearch').addEventListener('input', function() {
    filterQueueTable();
});

// Status filter functionality
document.getElementById('statusFilter').addEventListener('change', function() {
    filterQueueTable();
});

function filterQueueTable(tabStatus = null) {
    const searchTerm = document.getElementById('queueSearch').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('.queue-row');
    
    // Get active tab if not provided
    if (!tabStatus) {
        const activeTab = document.querySelector('.tab-item.active');
        tabStatus = activeTab ? activeTab.dataset.tab : 'all';
    }
    
    rows.forEach(row => {
        const name = row.dataset.name;
        const status = row.dataset.status;
        
        // Check search match
        const searchMatch = !searchTerm || name.includes(searchTerm);
        
        // Check tab filter
        const tabMatch = tabStatus === 'all' || status === tabStatus;
        
        // Check status dropdown filter
        const statusMatch = !statusFilter || status === statusFilter;
        
        // Show/hide row
        row.style.display = (searchMatch && tabMatch && statusMatch) ? '' : 'none';
    });
}

function callPatient(queueId) {
    alert('Calling patient #' + queueId);
    // Add your call patient logic here
}

function editPatient(queueId) {
    alert('Editing patient #' + queueId);
    // Add your edit patient logic here
}

function toggleMoreMenu(button) {
    alert('More options for this patient');
    // Add dropdown menu logic here
}

function openFullScreenPatientModal(queueId) {
    // Placeholder function for opening full screen patient modal
    alert('Opening patient details for queue ID: ' + queueId);
}

function closeFullScreenModal() {
    document.getElementById('fullScreenPatientModal').classList.remove('active');
}

// Close modal on outside click
document.getElementById('fullScreenPatientModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeFullScreenModal();
});

document.getElementById('callingModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeCallingModal();
});

// ESC key to close modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeFullScreenModal();
        closeCallingModal();
    }
});
</script>

<?php require_once 'includes/staff_layout_end.php'; ?>
