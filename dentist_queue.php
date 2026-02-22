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
        SELECT q.*, p.first_name, p.middle_name, p.last_name, p.suffix, p.phone
        FROM queue q 
        LEFT JOIN patients p ON q.patient_id = p.id 
        WHERE q.status IN ('waiting', 'in_procedure', 'pending_payment', 'completed', 'on_hold', 'cancelled')
        ORDER BY 
            CASE q.status 
                WHEN 'in_procedure' THEN 1 
                WHEN 'waiting' THEN 2 
                WHEN 'pending_payment' THEN 3 
                WHEN 'on_hold' THEN 4 
                WHEN 'completed' THEN 5 
                WHEN 'cancelled' THEN 6 
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
    
    // Check if there's a procedure currently in progress
    $inProcedurePatient = null;
    foreach ($allQueueItems as $item) {
        if ($item['status'] === 'in_procedure') {
            $inProcedurePatient = $item;
            break;
        }
    }
    
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
/* Align items neatly in a row */
/* Force the container to keep items in a row */
.control-bar {
    display: flex !important;
    flex-direction: row !important; /* Forces side-by-side layout */
    align-items: center;
    gap: 16px;
    width: 100%;
    margin-bottom: 24px;
    flex-wrap: nowrap; /* Prevents items from wrapping to the next line */
}

/* Ensure the search container stretches, but not too far */
.search-input-container {
    flex: 1 !important;
    max-width: auto !important;
    position: relative;
}

/* Stop the dropdown from stretching 100% across the screen */
.filter-select {
    width: auto !important; 
    min-width: 160px;
    
    /* Clean Styling */
    background-color: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 10px 16px;
    font-size: 0.95rem;
    color: #334155;
    outline: none;
    height: 42px;
    cursor: pointer;
    
    /* Custom clean arrow */
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E');
    background-repeat: no-repeat;
    background-position: right 14px top 50%;
    background-size: 10px auto;
    padding-right: 36px;
}

/* Positioning for the search magnifying glass */
.search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    display: flex;
}

/* Ensure the input itself behaves */
.search-input {
    width: 100% !important;
    background-color: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 10px 16px 10px 40px; /* 40px left padding avoids the icon */
    font-size: 0.95rem;
    color: #334155;
    outline: none;
    height: 42px;
    box-sizing: border-box;
}

.search-input:focus, .filter-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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

.status-badge.pending-payment {
    background: #fef3c7;
    color: #d97706;
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
    .queue-table th,
    .queue-table td {
        padding: 8px;
        font-size: 0.8rem;
    }
    
    .status-badge {
        font-size: 0.75rem;
        padding: 2px 6px;
    }
    
    .treatment-type,
    .time-display {
        font-size: 0.8rem;
    }
    
    .time-display {
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
    }
    
    .queue-kebab-btn {
        width: 24px;
        height: 24px;
    }
}

/* Prescription Modal Styles */
.safety-info {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.safety-info h3 {
    margin: 0 0 15px 0;
    color: #15803d;
    font-size: 1rem;
}

.safety-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 12px;
}

.safety-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.safety-item strong {
    font-size: 0.85rem;
    color: #374151;
}

.warning-text {
    color: #dc2626;
    font-weight: 600;
    background: #fee2e2;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.8rem;
}

.info-text {
    color: #059669;
    background: #ecfdf5;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.8rem;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #374151;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.95rem;
    transition: border-color 0.2s;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #0284c7;
}

.form-group small {
    display: block;
    margin-top: 5px;
    color: #6b7280;
    font-size: 0.8rem;
}

.modal {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 20px 0;
    border-bottom: 1px solid #e5e7eb;
}

.modal-header h2 {
    margin: 0;
    color: #1f2937;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6b7280;
    padding: 0;
    width: 30px;
    height: 30px;
}

.modal-close:hover {
    color: #374151;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 0 20px 20px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    border-top: 1px solid #e5e7eb;
    margin-top: 20px;
    padding-top: 20px;
}

.btn-cancel {
    background: #f3f4f6;
    color: #374151;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
}

.btn-cancel:hover {
    background: #e5e7eb;
}

.btn-primary {
    background: #0284c7;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}

.btn-primary:hover {
    background: #0369a1;
}
    
    .control-bar {
        flex-direction: column;
        align-items: stretch;
        margin-bottom: 0px;
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
    
    /* Prescription Modal Styles */
    .safety-info {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .safety-info h3 {
        margin: 0 0 15px 0;
        color: #15803d;
        font-size: 1rem;
    }

    .safety-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 12px;
    }

    .safety-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .safety-item strong {
        font-size: 0.85rem;
        color: #374151;
    }

    .warning-text {
        color: #dc2626;
        font-weight: 600;
        background: #fee2e2;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.8rem;
    }

    .info-text {
        color: #059669;
        background: #ecfdf5;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.8rem;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #374151;
    }

    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 0.95rem;
        transition: border-color 0.2s;
    }

    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #0284c7;
    }

    .form-group small {
        display: block;
        margin-top: 5px;
        color: #6b7280;
        font-size: 0.8rem;
    }

    .modal {
        background: white;
        border-radius: 12px;
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 20px 0;
        border-bottom: 1px solid #e5e7eb;
    }

    .modal-header h2 {
        margin: 0;
        color: #1f2937;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #6b7280;
        padding: 0;
        width: 30px;
        height: 30px;
    }

    .modal-close:hover {
        color: #374151;
    }

    .modal-body {
        padding: 20px;
    }

    .modal-footer {
        padding: 0 20px 20px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        border-top: 1px solid #e5e7eb;
        margin-top: 20px;
        padding-top: 20px;
    }

    .btn-cancel {
        background: #f3f4f6;
        color: #374151;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
    }

    .btn-cancel:hover {
        background: #e5e7eb;
    }

    .btn-primary {
        background: #0284c7;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
    }

    .btn-primary:hover {
        background: #0369a1;
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
    <div class="search-input-container">
        <span class="search-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
        </span>
        <input type="text" class="search-input with-icon" id="queueSearch" placeholder="Search by name, treatment...">
    </div>
    
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
                    <th>First Name</th>
                    <th>Middle Name</th>
                    <th>Last Name</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Treatment</th>
                    <th>Time In</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="queueTableBody">
                <?php if (empty($queueItems)): ?>
                    <tr>
                        <td colspan="9">
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
                        <tr class="queue-row" data-status="<?php echo $item['status']; ?>" data-name="<?php echo strtolower(htmlspecialchars($item['first_name'] ?? '')); ?>" data-treatment="<?php echo strtolower(htmlspecialchars($item['treatment_type'] ?? '')); ?>">
                            <td><?php echo $showingStart + $index; ?></td>
                            <td>
                                <div class="patient-name"><?php echo htmlspecialchars($item['first_name'] ?: 'Unknown'); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($item['middle_name'] ?? ''); ?></td>
                            <td>
                                <div class="patient-name"><?php echo htmlspecialchars($item['last_name'] ?: 'Unknown'); ?></div>
                            </td>
                            <td>
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

// Track if there's a procedure currently in progress
const inProcedurePatient = <?php echo $inProcedurePatient ? json_encode(['id' => $inProcedurePatient['id'], 'name' => trim(($inProcedurePatient['first_name'] ?? '') . ' ' . ($inProcedurePatient['last_name'] ?? ''))]) : 'null'; ?>;

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
        if (inProcedurePatient) {
            menuHTML += `
                <a href="javascript:void(0)" onclick="alert('Cannot start: ${inProcedurePatient.name} is currently in procedure. Please complete their procedure first.')" style="opacity: 0.5; cursor: not-allowed;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15v-3m0 0v-3m0 3h-3m3 0h3M12 21a9 9 0 1 1 0-18 9 9 0 0 1 0 18z"/></svg>
                    Start Procedure (Locked)
                </a>
            `;
        } else {
            menuHTML += `
                <a href="javascript:void(0)" onclick="updateQueueStatus(${queueId}, 'in_procedure')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    Start Procedure
                </a>
            `;
        }
        menuHTML += `
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
            <a href="javascript:void(0)" onclick="openQuickPrescriptionModal(${patientId}, ${queueId})">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 14h-5v5h5v-5z M5 14h5v5H5v-5z M14 14h-5v5h5v-5z"/></svg>
                💊 Prescription
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

// Open Quick Prescription Modal
function openQuickPrescriptionModal(patientId, queueId) {
    // Create and show prescription modal for specific patient
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.style.display = 'flex';
    modal.innerHTML = `
        <div class="fullscreen-modal-content" style="max-width: 800px;">
            <div class="fullscreen-modal-header">
                <div>
                    <h2 style="font-size: 1.5rem; font-weight: 600; margin: 0;">💊 Quick Prescription</h2>
                </div>
                <button class="fullscreen-modal-close" onclick="closeQuickPrescriptionModal()">&times;</button>
            </div>
            <div class="fullscreen-modal-body">
                <div id="quickPatientSafetyInfo" class="safety-info" style="display: none;">
                    <h3>👤 Patient Information</h3>
                    <div class="safety-grid">
                        <div class="safety-item">
                            <strong>Name:</strong>
                            <span id="quickPatientName"></span>
                        </div>
                        <div class="safety-item">
                            <strong>Age:</strong>
                            <span id="quickPatientAge"></span>
                        </div>
                        <div class="safety-item">
                            <strong>Allergies:</strong>
                            <span id="quickPatientAllergies" class="warning-text"></span>
                        </div>
                        <div class="safety-item">
                            <strong>Current Medications:</strong>
                            <span id="quickPatientMeds" class="info-text"></span>
                        </div>
                    </div>
                </div>
                
                <form id="quickPrescriptionForm">
                    <input type="hidden" id="quickPatientId" name="patient_id" value="${patientId}">
                    
                    <div class="form-group">
                        <label>Issue Date:</label>
                        <input type="date" name="issue_date" value="${new Date().toISOString().split('T')[0]}" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Diagnosis:</label>
                        <textarea name="diagnosis" rows="3" required placeholder="Reason for prescription..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Medications:</label>
                        <textarea name="medications" rows="4" required placeholder="e.g., Amoxicillin 500mg - 3 times daily for 7 days"></textarea>
                        <small>Enter medication details including dosage, frequency, and duration</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Instructions:</label>
                        <textarea name="instructions" rows="3" placeholder="Additional patient instructions..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeQuickPrescriptionModal()">Cancel</button>
                <button type="button" class="btn-primary" onclick="saveQuickPrescription()">Save Prescription</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Load patient safety info
    fetch('prescription_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_patient_info', patient_id: patientId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const patient = data.patient;
            
            document.getElementById('quickPatientName').textContent = patient.patient_name || 'N/A';
            document.getElementById('quickPatientAge').textContent = patient.age || 'N/A';
            document.getElementById('quickPatientAllergies').textContent = patient.allergies || 'None recorded';
            document.getElementById('quickPatientMeds').textContent = patient.current_medications || 'None recorded';
            
            document.getElementById('quickPatientSafetyInfo').style.display = 'block';
        }
    });
}

function closeQuickPrescriptionModal() {
    const modal = document.querySelector('.modal-overlay');
    if (modal) {
        modal.remove();
    }
}

function saveQuickPrescription() {
    const formData = new FormData(document.getElementById('quickPrescriptionForm'));
    const data = Object.fromEntries(formData);
    
    if (!data.medications || !data.diagnosis) {
        alert('Please fill in all required fields');
        return;
    }
    
    fetch('prescription_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'create_prescription', ...data })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeQuickPrescriptionModal();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

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
