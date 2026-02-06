<?php
$pageTitle = 'Queue Management';
require_once 'config/database.php';
require_once 'includes/dentist_layout_start.php';

// Pagination settings
$itemsPerPage = 7;
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;

try {
    // Get ALL queue data for counts (without pagination)
    $allStmt = $pdo->query("
        SELECT q.*, p.full_name, p.phone
        FROM queue q 
        LEFT JOIN patients p ON q.patient_id = p.id 
        WHERE q.status IN ('waiting', 'in_procedure', 'completed', 'on_hold', 'cancelled')
        AND DATE(q.created_at) = CURDATE()
        ORDER BY 
            CASE q.status 
                WHEN 'in_procedure' THEN 1 
                WHEN 'waiting' THEN 2 
                WHEN 'on_hold' THEN 3 
                WHEN 'completed' THEN 4 
                WHEN 'cancelled' THEN 5 
            END,
            q.queue_time ASC
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
}

.pagination-btn {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    background: white;
    color: #374151;
    border-radius: 6px;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    min-width: 40px;
    text-align: center;
}

.pagination-btn:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

.pagination-btn.active {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
}

.pagination-btn:disabled {
    background: #f9fafb;
    color: #9ca3af;
    cursor: not-allowed;
    opacity: 0.5;
}

.pagination-ellipsis {
    padding: 8px 4px;
    color: #6b7280;
    font-weight: 500;
}

/* Summary Widgets Grid */
.summary-widgets {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 20px;
    margin-bottom: 0px;
    width: 100%;
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
    margin-bottom: 0px;
    display: flex;
    gap: 16px;
    align-items: center;
    width: 100%;
    box-sizing: border-box;
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

/* Main Content Override - Remove the 260px margin from layout */
div.main-content {
    margin-left: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    flex: 1;
    display: flex;
    flex-direction: column;
}

/* Inner main content styling */
.main-content {
    background: white;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    height: auto;
    min-height: auto;
}

/* Tabs Navigation */
.tabs-navigation {
    display: flex;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
    padding: 0 20px;
    gap: 8px;
}

.tab-item {
    padding: 16px 20px;
    cursor: pointer;
    font-weight: 500;
    color: #6b7280;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    white-space: nowrap;
}

.tab-item:hover {
    color: #374151;
}

.tab-item.active {
    color: #2563eb;
    border-bottom-color: #2563eb;
    background: white;
}

.tab-count {
    background: #e5e7eb;
    color: #6b7280;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
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

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6b7280;
}

.empty-state svg {
    width: 64px;
    height: 64px;
    margin-bottom: 16px;
    opacity: 0.3;
}

.empty-state h3 {
    font-size: 1.125rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}

/* Responsive */
@media (max-width: 1200px) {
    .summary-widgets {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .summary-widgets {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .control-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .control-bar .search-input,
    .control-bar .filter-select {
        width: 100%;
    }
    
    .tabs-navigation {
        overflow-x: auto;
        padding: 0 10px;
    }
    
    .tab-item {
        padding: 12px 16px;
        font-size: 0.85rem;
    }
    
    .queue-table {
        font-size: 0.85rem;
    }
    
    .queue-table th,
    .queue-table td {
        padding: 12px 16px;
    }
}
</style>

<!-- Summary Widgets -->
<div class="summary-widgets">
    <div class="summary-widget">
        <div class="summary-widget-icon yellow">⏰</div>
        <div class="summary-widget-info">
            <h3><?php echo $waitingCount; ?></h3>
            <p>Waiting</p>
        </div>
    </div>
    <div class="summary-widget">
        <div class="summary-widget-icon blue">⚕️</div>
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
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Tabs Navigation -->
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
        <div class="tab-item" data-tab="cancelled">
            Cancelled <span class="tab-count"><?php echo $cancelledCount; ?></span>
        </div>
    </div>

    <!-- Data Table -->
    <div class="queue-table-container">
        <table class="queue-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Patient Name</th>
                    <th>Status</th>
                    <th>Treatment</th>
                    <th>Time In</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="queueTableBody">
                <?php if (empty($queueItems)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                </svg>
                                <h3>No patients in queue</h3>
                                <p>No queue items for today</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($queueItems as $index => $item): 
                        $queueTime = new DateTime($item['queue_time']);
                        $time12hr = $queueTime->format('g:i A');
                        $time24hr = $queueTime->format('H:i');
                        $statusClass = str_replace('_', '-', $item['status']);
                        $displayStatus = str_replace('_', ' ', ucfirst($item['status']));
                    ?>
                        <tr class="queue-row" data-status="<?php echo $item['status']; ?>" data-name="<?php echo strtolower(htmlspecialchars($item['full_name'] ?? '')); ?>" data-treatment="<?php echo strtolower(htmlspecialchars($item['treatment_type'] ?? '')); ?>">
                            <td><?php echo $showingStart + $index; ?></td>
                            <td>
                                <div class="patient-name"><?php echo htmlspecialchars($item['full_name'] ?? 'Unknown'); ?></div>
                                <div style="font-size: 0.8rem; color: #6b7280;"><?php echo htmlspecialchars($item['phone'] ?? ''); ?></div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $statusClass; ?>">
                                    <span class="status-dot"></span>
                                    <?php echo $displayStatus; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($item['treatment_type'] ?? 'General Checkup'); ?></td>
                            <td>
                                <div class="time-display">
                                    <span class="time-12hr"><?php echo $time12hr; ?></span>
                                    <span class="time-24hr"><?php echo $time24hr; ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="queue-kebab-menu">
                                    <button class="queue-kebab-btn" data-queue-id="<?php echo $item['id']; ?>">
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

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <span class="pagination-info">Showing <?php echo $showingStart; ?>-<?php echo $showingEnd; ?> of <?php echo $totalQueueItems; ?> queue items</span>
        <div class="pagination-buttons">
            <?php if ($currentPage > 1): ?>
                <a href="?page=<?php echo $currentPage - 1; ?>" class="pagination-btn">Previous</a>
            <?php else: ?>
                <button class="pagination-btn" disabled>Previous</button>
            <?php endif; ?>
            
            <?php
            $startPage = max(1, $currentPage - 2);
            $endPage = min($totalPages, $startPage + 4);
            if ($endPage - $startPage < 4) {
                $startPage = max(1, $endPage - 4);
            }
            
            for ($i = $startPage; $i <= $endPage; $i++):
                if ($i == $currentPage):
            ?>
                <button class="pagination-btn active"><?php echo $i; ?></button>
            <?php else: ?>
                <a href="?page=<?php echo $i; ?>" class="pagination-btn"><?php echo $i; ?></a>
            <?php 
                endif;
            endfor;
            ?>
            
            <?php if ($currentPage < $totalPages): ?>
                <a href="?page=<?php echo $currentPage + 1; ?>" class="pagination-btn">Next</a>
            <?php else: ?>
                <button class="pagination-btn" disabled>Next</button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Queue Kebab Menu Functionality
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

function getQueueMenuItems(queueId, status) {
    let menuHTML = '';
    
    if (status === 'waiting') {
        menuHTML += `
            <a href="javascript:void(0)" onclick="updateQueueStatus(${queueId}, 'in_procedure')">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                Start Procedure
            </a>
            <a href="javascript:void(0)" onclick="updateQueueStatus(${queueId}, 'on_hold')">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                Put On Hold
            </a>
        `;
    } else if (status === 'in_procedure') {
        menuHTML += `
            <a href="javascript:void(0)" onclick="updateQueueStatus(${queueId}, 'completed')">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
                Complete
            </a>
            <a href="javascript:void(0)" onclick="updateQueueStatus(${queueId}, 'waiting')">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                Back to Waiting
            </a>
        `;
    } else if (status === 'on_hold') {
        menuHTML += `
            <a href="javascript:void(0)" onclick="updateQueueStatus(${queueId}, 'waiting')">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                Resume
            </a>
        `;
    }
    
    menuHTML += `
        <a href="dentist_treatments.php?queue_id=${queueId}" class="primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
            Treatment
        </a>
        <a href="quick_session.php?queue_id=${queueId}" class="primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            Session
        </a>
        <a href="javascript:void(0)" onclick="removeFromQueue(${queueId})" class="danger">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            Remove
        </a>
    `;
    
    return menuHTML;
}

function positionQueueKebabDropdown(button) {
    if (!queueKebabDropdown || !button) return;

    const rect = button.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    const padding = 15;
    const dropdownWidth = 200;
    
    let left = rect.right + 5;
    let top = rect.bottom + 8;

    if (left + dropdownWidth > viewportWidth - padding) {
        left = rect.left - dropdownWidth - 5;
    }
    
    if (left < padding) {
        left = padding;
    }
    
    if (top + 200 > viewportHeight - padding) {
        top = rect.top - 200 - 8;
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
    const row = button.closest('.queue-row');
    const status = row.dataset.status;

    queueKebabDropdown.innerHTML = getQueueMenuItems(queueId, status);
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
    const link = e.target.closest('a');
    if (!link) return;

    if (link.getAttribute('href') && link.getAttribute('href') !== 'javascript:void(0)') {
        return;
    }

    e.preventDefault();
    e.stopPropagation();

    closeQueueKebabDropdown();
}

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

    if (!e.target.closest('.queue-kebab-dropdown-portal')) {
        closeQueueKebabDropdown();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (queueKebabDropdown && queueKebabDropdown.classList.contains('show')) {
            closeQueueKebabDropdown();
        }
    }
});

window.addEventListener('resize', function() {
    if (queueKebabDropdown && queueKebabDropdown.classList.contains('show') && queueActiveButton) {
        positionQueueKebabDropdown(queueActiveButton);
    }
});

// Update Queue Status
function updateQueueStatus(queueId, status) {
    fetch('queue_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=update_status&queue_id=' + queueId + '&status=' + status
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating status');
    });
}

// Remove from Queue
function removeFromQueue(queueId) {
    if (!confirm('Remove this patient from the queue?')) return;
    
    fetch('queue_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=remove&queue_id=' + queueId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error removing from queue');
    });
}

// Tab Navigation
document.querySelectorAll('.tab-item').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        const tabName = this.dataset.tab;
        filterQueueTable(tabName);
    });
});

function filterQueueTable(status) {
    const rows = document.querySelectorAll('.queue-row');
    
    rows.forEach(row => {
        if (status === 'all' || row.dataset.status === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Search and Filter
const queueSearch = document.getElementById('queueSearch');
const statusFilter = document.getElementById('statusFilter');

function filterTable() {
    const searchTerm = queueSearch.value.toLowerCase();
    const statusValue = statusFilter.value;
    const rows = document.querySelectorAll('.queue-row');
    
    rows.forEach(row => {
        const name = row.dataset.name;
        const treatment = row.dataset.treatment;
        const status = row.dataset.status;
        
        const matchesSearch = !searchTerm || name.includes(searchTerm) || treatment.includes(searchTerm);
        const matchesStatus = !statusValue || status === statusValue;
        
        if (matchesSearch && matchesStatus) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

if (queueSearch) {
    queueSearch.addEventListener('input', filterTable);
}

if (statusFilter) {
    statusFilter.addEventListener('change', filterTable);
}
</script>

<?php require_once 'includes/dentist_layout_end.php'; ?>
