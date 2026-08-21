<?php
$pageTitle = 'Queue Management';
require_once '../../config/database.php';
require_once '../../includes/dentist_layout_start.php';
require_once '../../includes/queue_management_helper.php';

// Auto-archive stale entries from previous days
$today = date('Y-m-d');
archiveStaleQueueEntries($pdo, $today);

// Pagination settings
$itemsPerPage = 5;
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;

try {
    $queueColumns = array_flip($pdo->query('SHOW COLUMNS FROM queue')->fetchAll(PDO::FETCH_COLUMN));
    $dentistQueueDateExpression = isset($queueColumns['queue_date'])
        ? 'COALESCE(q.queue_date, DATE(q.created_at))'
        : 'DATE(q.created_at)';

    // Get ALL queue data for counts (without pagination)
    $allStmt = $pdo->prepare("
        SELECT q.*, 
               COALESCE(p.first_name, 'Unknown') as first_name, 
               COALESCE(p.middle_name, '') as middle_name, 
               COALESCE(p.last_name, '') as last_name, 
               COALESCE(p.suffix, '') as suffix, 
               COALESCE(p.phone, '') as phone,
               COALESCE(q.status, 'waiting') as status_text
        FROM queue q 
        LEFT JOIN patients p ON CAST(q.patient_id AS UNSIGNED) = p.id
        WHERE q.status IN ('waiting', 'in_procedure', 'completed', 'cancelled', 'on_hold') AND q.is_archived = 0
          AND {$dentistQueueDateExpression} = ?
        ORDER BY 
            CASE COALESCE(q.status, 'waiting')
                WHEN 'in_procedure' THEN 1 
                WHEN 'waiting' THEN 2 
                WHEN 'completed' THEN 3 
                ELSE 2
            END,
            q.queue_time ASC
    ");
    $allStmt->execute([$today]);
    $allQueueItems = $allStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate counts
    $waitingCount = count(array_filter($allQueueItems, fn($q) => ($q['status_text'] ?? '') === 'waiting'));
    $procedureCount = count(array_filter($allQueueItems, fn($q) => ($q['status_text'] ?? '') === 'in_procedure'));
    $completedToday = count(array_filter($allQueueItems, fn($q) => ($q['status_text'] ?? '') === 'completed'));
    $onHoldCount = count(array_filter($allQueueItems, fn($q) => ($q['status_text'] ?? '') === 'on_hold'));
    $cancelledCount = count(array_filter($allQueueItems, fn($q) => ($q['status_text'] ?? '') === 'cancelled'));
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
        if (($item['status_text'] ?? '') === 'in_procedure') {
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
    padding-bottom: 0px;

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
    padding-top: 15px;
    padding-bottom: 15px;
    background: transparent;
    position: relative;
    z-index: 10;
}

.queue-table-container {
    background: white;
    border-radius: 12px;
    height: auto;
    min-height: 300px;
    overflow-x: auto;
    overflow-y: visible;
    margin-bottom: 20px;
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
    background: #fbf9f9;
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
    padding-bottom: 25px;
}

.summary-widget {
    background: #ffffff;
    border-radius: 12px;
    padding: 22px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 16px;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    position: relative;
    overflow: hidden;
    cursor: default;
}

.summary-widget:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.07);
}

.summary-widget-icon,
.summary-widget-info {
    position: relative;
    z-index: 2;
}

.summary-widget-icon {
    width: 54px;
    height: 54px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.summary-widget-icon img {
    width: 28px;
    height: 28px;
    object-fit: contain;
}

.summary-widget-icon.yellow {
    background: #d3e0fe;
    
}

.summary-widget-icon.blue {
    background: #d3e0fe;
}

.summary-widget-icon.green {
    background: #d3e0fe;
}

.summary-widget-icon.red {
    background: #d3e0fe;
}

.summary-widget-icon.gray {
    background: #d3e0fe;
}

.summary-widget-info h3 {
    font-size: 1.8rem;
    font-weight: 700;
    color: #27427e;
    margin: 0;
    line-height: 1;
}

.summary-widget-info p {
    font-size: 0.9rem;
    color: #5b6ea3;
    margin: 4px 0 0 0;
}

/* Large background watermark icon (right side) */
.summary-widget::after {
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
.summary-widget.card-waiting::after {
    background-image: url('../../assets/icons/waiting.png');
}
.summary-widget.card-in-procedure::after {
    background-image: url('../../assets/icons/in-procedure.png');
}
.summary-widget.card-completed::after {
    background-image: url('../../assets/icons/check.png');
}
.summary-widget.card-on-hold::after {
    background-image: url('../../assets/icons/pause.png');
}
.summary-widget.card-cancelled::after {
    background-image: url('../../assets/icons/delete.png');
}
/* Align items neatly in a row */
/* Force the container to keep items in a row */
.control-bar {
    display: flex !important;
    flex-direction: row !important; /* Forces side-by-side layout */
    align-items: center;
    gap: 16px;
    width: 100%;
    
    padding-bottom: 25px;
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
    overflow: visible;
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
    background: #f3f4f6;
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
    color: #1d4ed8;
}

.status-badge.in-procedure .status-dot {
    background: #2563eb;
}

.status-badge.on-hold {
    background: #fed7aa;
    color: #c2410c;
}

.status-badge.pending-payment {
    background: #fed7aa;
    color: #c2410c;
}

.status-badge.completed {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.cancelled {
    background: #fee2e2;
    color: #dc2626;
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
    background: #c2410c;
}

.status-badge.completed .status-dot {
    background: #15803d;
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

    /* Patient Record Details Modal */
    .fullscreen-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.65);
        justify-content: center;
        align-items: center;
        z-index: 2147483647;
    }
    .fullscreen-modal-overlay.active {
        display: flex;
    }
    .fullscreen-modal-content {
        background: white;
        border-radius: 16px;
        width: 95%;
        max-width: 900px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        position: relative;
        z-index: 100000;
    }
    .fullscreen-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 24px;
        border-bottom: 1px solid #e5e7eb;
        position: sticky;
        top: 0;
        background: white;
        z-index: 10;
    }
    .fullscreen-modal-close {
        background: none;
        border: none;
        font-size: 2rem;
        cursor: pointer;
        color: #6b7280;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
    }
    .fullscreen-modal-body {
        padding: 24px;
    }
    .patient-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
    }
    .patient-info-item {
        background: #f9fafb;
        border-radius: 8px;
        padding: 12px;
    }
    .patient-info-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 4px;
    }
    .patient-info-value {
        font-size: 0.95rem;
        color: #111827;
        font-weight: 500;
    }

/* Start Procedure Modal */
.start-procedure-modal {
    background:
        radial-gradient(circle at top right, rgba(14, 165, 233, 0.18), transparent 32%),
        radial-gradient(circle at bottom left, rgba(34, 197, 94, 0.14), transparent 28%),
        linear-gradient(180deg, #ffffff 0%, #f7fcff 100%);
    border-radius: 22px;
    padding: 0;
    width: 90%;
    max-width: 560px;
    overflow: hidden;
    box-shadow: 0 30px 80px rgba(15, 23, 42, 0.24);
    border: 1px solid rgba(14, 165, 233, 0.12);
}
.sp-bg-orb { position: absolute; border-radius: 999px; pointer-events: none; filter: blur(8px); opacity: 0.55; }
.sp-bg-orb-a { width: 140px; height: 140px; right: -30px; top: 88px; background: radial-gradient(circle, rgba(14, 165, 233, 0.2) 0%, rgba(14, 165, 233, 0) 72%); }
.sp-bg-orb-b { width: 160px; height: 160px; left: -40px; bottom: -30px; background: radial-gradient(circle, rgba(34, 197, 94, 0.16) 0%, rgba(34, 197, 94, 0) 74%); }
.sp-banner {
    display: flex; align-items: center; gap: 16px; padding: 24px 26px 18px;
    background: linear-gradient(135deg, rgba(255,255,255,0.12), rgba(255,255,255,0) 42%), linear-gradient(135deg, #0b7db3 0%, #0891b2 38%, #0f9f53 100%);
    color: white; position: relative;
}
.sp-icon-wrap {
    width: 54px; height: 54px; border-radius: 16px; display: flex; align-items: center; justify-content: center;
    background: rgba(255, 255, 255, 0.16); border: 1px solid rgba(255, 255, 255, 0.22); backdrop-filter: blur(6px); flex-shrink: 0;
}
.sp-icon { display: flex; align-items: center; justify-content: center; }
.sp-icon svg { width: 26px; height: 26px; fill: currentColor; }
.sp-kicker { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.12em; opacity: 0.9; font-weight: 700; }
.sp-close {
    margin-left: auto; background: rgba(255, 255, 255, 0.12); color: white;
    border: 1px solid rgba(255, 255, 255, 0.18); width: 40px; height: 40px; border-radius: 12px;
    font-size: 1.5rem; cursor: pointer; backdrop-filter: blur(8px);
}
.sp-body { padding: 24px 26px 12px; position: relative; }
.sp-text { color: #1f2937; font-size: 1.06rem; line-height: 1.6; margin: 0 0 16px; font-weight: 500; }
.sp-highlight {
    display: flex; align-items: center; gap: 14px; padding: 14px 16px; border-radius: 16px;
    background: linear-gradient(135deg, #ecfeff 0%, #f0fdf4 100%); border: 1px solid #d9f99d; margin-bottom: 14px;
}
.sp-highlight-icon {
    width: 42px; height: 42px; border-radius: 14px; display: flex; align-items: center; justify-content: center;
    background: white; box-shadow: 0 10px 24px rgba(8, 145, 178, 0.12); font-size: 1.25rem; flex-shrink: 0;
}
.sp-highlight-label { color: #0f766e; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; margin-bottom: 3px; }
.sp-highlight-value { color: #1f2937; font-size: 0.95rem; font-weight: 600; }
.sp-note {
    display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 14px;
    background: #f0fdf4; color: #166534; font-size: 0.92rem; border: 1px solid #dcfce7;
}
.sp-note-dot { width: 10px; height: 10px; border-radius: 999px; background: #22c55e; box-shadow: 0 0 0 5px rgba(34, 197, 94, 0.14); flex-shrink: 0; }
.sp-actions { display: flex; justify-content: flex-end; gap: 12px; padding: 18px 26px 26px; }
.sp-btn-cancel, .sp-btn-confirm {
    border: none; border-radius: 14px; padding: 13px 20px; cursor: pointer; font-weight: 700; font-size: 0.97rem;
    transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
}
.sp-btn-cancel { background: #eef6fb; color: #315266; border: 1px solid #d8e9f4; }
.sp-btn-confirm { background: linear-gradient(135deg, #0891b2 0%, #0f9f53 100%); color: white; box-shadow: 0 14px 28px rgba(8, 145, 178, 0.24); }
.sp-btn-cancel:hover, .sp-btn-confirm:hover, .sp-close:hover { transform: translateY(-1px); box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08); }
@media (max-width: 640px) {
    .sp-banner, .sp-body, .sp-actions { padding-left: 18px; padding-right: 18px; }
    .sp-actions { flex-direction: column-reverse; }
    .sp-btn-cancel, .sp-btn-confirm { width: 100%; }
}
</style>

<!-- Summary Widgets -->
<div class="summary-widgets">
    <div class="summary-widget card-waiting">
        <div class="summary-widget-icon yellow">
            <img src="../../assets/icons/waiting.png" alt="Waiting">
        </div>
        <div class="summary-widget-info">
            <h3><?php echo $waitingCount; ?></h3>
            <p>Waiting</p>
        </div>
    </div>
    <div class="summary-widget card-in-procedure">
        <div class="summary-widget-icon blue">
            <img src="../../assets/icons/in-procedure.png" alt="In Procedure">
        </div>
        <div class="summary-widget-info">
            <h3><?php echo $procedureCount; ?></h3>
            <p>In Procedure</p>
        </div>
    </div>
    <div class="summary-widget card-completed">
        <div class="summary-widget-icon green">
            <img src="../../assets/icons/check.png" alt="Completed">
        </div>
        <div class="summary-widget-info">
            <h3><?php echo $completedToday; ?></h3>
            <p>Completed</p>
        </div>
    </div>
    <div class="summary-widget card-on-hold">
        <div class="summary-widget-icon gray">
            <img src="../../assets/icons/pause.png" alt="On Hold">
        </div>
        <div class="summary-widget-info">
            <h3><?php echo $onHoldCount; ?></h3>
            <p>On Hold</p>
        </div>
    </div>
    <div class="summary-widget card-cancelled">
        <div class="summary-widget-icon red">
            <img src="../../assets/icons/delete.png" alt="Cancelled">
        </div>
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
                        $statusText = $item['status_text'] ?? 'waiting';
                        $statusClass = str_replace('_', '-', $statusText);
                        $displayStatus = ucfirst(str_replace('_', ' ', $statusText));
                    ?>
                        <tr class="queue-row" data-status="<?php echo $statusText; ?>" data-name="<?php echo strtolower(htmlspecialchars($item['first_name'] ?? '')); ?>" data-treatment="<?php echo strtolower(htmlspecialchars($item['treatment_type'] ?? '')); ?>">
                            <td><?php echo '#' . ($item['queue_number'] ?? ($showingStart + $index)); ?></td>
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
                            <td><?php echo htmlspecialchars($item['treatment_type'] ?? 'Consultation'); ?></td>
                            <td>
                                <div class="time-display">
                                    <span class="time-12hr"><?php echo $time12hr; ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="queue-kebab-menu">
                                    <button class="queue-kebab-btn" data-queue-id="<?php echo $item['id']; ?>" data-patient-id="<?php echo $item['patient_id']; ?>" data-patient-name="<?php echo htmlspecialchars(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? '')); ?>" data-treatment="<?php echo htmlspecialchars($item['treatment_type'] ?? 'General Checkup'); ?>">
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
    
</div>
 <!-- Pagination -->
        <?php if ($totalQueueItems > 0): ?>
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

<!-- Patient Record Details Modal -->
<div id="queuePatientModal" class="fullscreen-modal-overlay">
    <div class="fullscreen-modal-content">
        <div class="fullscreen-modal-header">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 600; margin: 0;">Patient Record Details</h2>
                <p id="queueModalPatientName" style="color: #6b7280; margin: 4px 0 0 0; font-size: 0.9rem;"></p>
            </div>
            <button type="button" onclick="closeQueuePatientModal()" class="fullscreen-modal-close">&times;</button>
        </div>
        <div class="fullscreen-modal-body" id="queuePatientContent"></div>
    </div>
</div>

<!-- Start Procedure Modal -->
<div id="startProcedureModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div class="start-procedure-modal" style="position: relative; z-index: 100000;">
        <div class="sp-bg-orb sp-bg-orb-a"></div>
        <div class="sp-bg-orb sp-bg-orb-b"></div>
        <div class="sp-banner">
            <div class="sp-icon-wrap">
                <span class="sp-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </span>
            </div>
            <div>
                <div class="sp-kicker">Dentist Queue</div>
                <h2 style="font-size: 1.55rem; font-weight: 700; margin: 6px 0 0;">Start Procedure</h2>
            </div>
            <button onclick="closeStartProcedureModal()" class="sp-close">&times;</button>
        </div>
        <div class="sp-body">
            <p class="sp-text">You are about to start the procedure for this patient.</p>
            <div class="sp-highlight">
                <div class="sp-highlight-icon">&#129463;</div>
                <div>
                    <div class="sp-highlight-label">Patient</div>
                    <div class="sp-highlight-value" id="startProcedurePatientName">-</div>
                </div>
            </div>
            <div class="sp-highlight">
                <div class="sp-highlight-icon">&#128137;</div>
                <div>
                    <div class="sp-highlight-label">Treatment</div>
                    <div class="sp-highlight-value" id="startProcedureTreatment">-</div>
                </div>
            </div>
            <div class="sp-note">
                <span class="sp-note-dot"></span>
                This will move the patient to "In Procedure" status.
            </div>
        </div>
        <input type="hidden" id="startProcedureQueueId">
        <div class="sp-actions">
            <button type="button" onclick="closeStartProcedureModal()" class="sp-btn-cancel">Cancel</button>
            <button type="button" onclick="confirmStartProcedure()" class="sp-btn-confirm">Start Procedure</button>
        </div>
    </div>
</div>

<script>
// Queue Kebab Menu Functionality
let queueKebabDropdown = null;
let queueKebabBackdrop = null;
let queueActiveButton = null;

// Track if there's a procedure currently in progress
var inProcedurePatient = <?php echo $inProcedurePatient ? json_encode(['id' => $inProcedurePatient['id'], 'name' => trim(($inProcedurePatient['first_name'] ?? '') . ' ' . ($inProcedurePatient['last_name'] ?? ''))]) : 'null'; ?>;

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

function getQueueMenuItems(queueId, status, patientId, patientName, treatment) {
    let menuHTML = '';
    
    menuHTML += `
        <a href="javascript:void(0)" onclick="viewPatientQueue(${patientId})">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            View Patient
        </a>
    `;
    
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
                <a href="javascript:void(0)" onclick="openStartProcedureModal(${queueId}, '${patientName.replace(/'/g, "\\'")}', '${treatment.replace(/'/g, "\\'")}')">
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
    `;

    if (status === 'waiting' || status === 'on_hold') {
        menuHTML += `
            <a href="javascript:void(0)" class="danger" onclick="cancelPatientQueue(${queueId})">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
                Cancel
            </a>
        `;
    }
    
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
    const patientId = button.dataset.patientId || '0';
    const patientName = button.dataset.patientName || 'Unknown';
    const treatment = button.dataset.treatment || 'General Checkup';
    const row = button.closest('.queue-row');
    const status = row.dataset.status;

    queueKebabDropdown.innerHTML = getQueueMenuItems(queueId, status, patientId, patientName, treatment);
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
        closeStartProcedureModal();
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
    fetch('master/dentist/prescription_actions.php', {
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
    
    fetch('master/dentist/prescription_actions.php', {
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
    const actionMap = {
        'in_procedure': 'start_procedure',
        'completed': 'complete',
        'on_hold': 'on_hold',
        'waiting': 'requeue',
        'cancelled': 'cancel'
    };
    const action = actionMap[status];
    if (!action) {
        alert('Unknown status: ' + status);
        return;
    }

    fetch('../shared/queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: action, queue_id: parseInt(queueId) })
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

// Cancel Patient
function cancelPatientQueue(queueId) {
    if (!confirm('Cancel this patient from the queue?')) return;
    updateQueueStatus(queueId, 'cancelled');
}

// Start Procedure Modal
function openStartProcedureModal(queueId, patientName, treatment) {
    document.getElementById('startProcedureQueueId').value = queueId;
    document.getElementById('startProcedurePatientName').textContent = patientName || 'Unknown';
    document.getElementById('startProcedureTreatment').textContent = treatment || 'General Checkup';
    document.getElementById('startProcedureModal').style.display = 'flex';
}

function closeStartProcedureModal() {
    document.getElementById('startProcedureModal').style.display = 'none';
    document.getElementById('startProcedureQueueId').value = '';
}

function confirmStartProcedure() {
    var queueId = document.getElementById('startProcedureQueueId').value;
    if (!queueId) return;

    fetch('../shared/queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'start_procedure', queue_id: parseInt(queueId) })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            closeStartProcedureModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Unable to start procedure'));
        }
    })
    .catch(function() {
        alert('Unable to start procedure');
    });
}

// View Patient Record Details
function viewPatientQueue(patientId) {
    document.getElementById('queuePatientModal').classList.add('active');
    document.getElementById('queueModalPatientName').textContent = 'Loading...';
    document.getElementById('queuePatientContent').innerHTML = '<div style="text-align:center;padding:3rem;"><p>Loading patient details...</p></div>';

    fetch('../user-patient/patient_record_details.php?id=' + encodeURIComponent(patientId))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const p = data.patient;
                const m = data.medical_history || {};
                const d = data.dental_history || {};
                const q = data.queue_item || {};
                const fullName = `${p.first_name || ''} ${p.middle_name || ''} ${p.last_name || ''} ${p.suffix || ''}`.trim() || 'Unknown';
                const conditions = m.medical_conditions || 'None';
                const allergies = m.allergies || 'None';
                const medications = m.current_medications || 'None';

                document.getElementById('queueModalPatientName').textContent = fullName;
                document.getElementById('queuePatientContent').innerHTML = `
                    <div class="patient-info-grid">
                        <div class="patient-info-item">
                            <div class="patient-info-label">First Name</div>
                            <div class="patient-info-value">${p.first_name || 'N/A'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Middle Name</div>
                            <div class="patient-info-value">${p.middle_name || ''}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Last Name</div>
                            <div class="patient-info-value">${p.last_name || 'N/A'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Phone</div>
                            <div class="patient-info-value">${p.phone || 'N/A'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Age</div>
                            <div class="patient-info-value">${p.age || 'N/A'} years</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Gender</div>
                            <div class="patient-info-value">${p.gender || 'N/A'}</div>
                        </div>
                        <div class="patient-info-item" style="grid-column: 1 / -1;">
                            <div class="patient-info-label">Address</div>
                            <div class="patient-info-value">${p.street_address || 'N/A'}${p.city ? ', ' + p.city : ''}${p.province ? ', ' + p.province : ''}</div>
                        </div>
                    </div>
                    <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                        <h3 style="font-size: 1rem; font-weight: 600; color: #1e40af; margin-bottom: 16px;">Service Requested</h3>
                        <div class="patient-info-grid">
                            <div class="patient-info-item">
                                <div class="patient-info-label">Treatment Type</div>
                                <div class="patient-info-value">${q.treatment_type || 'Consultation'}</div>
                            </div>
                            <div class="patient-info-item">
                                <div class="patient-info-label">Selected Teeth</div>
                                <div class="patient-info-value">${q.teeth_numbers ? q.teeth_numbers : 'None specified'}</div>
                            </div>
                            <div class="patient-info-item">
                                <div class="patient-info-label">Status</div>
                                <div class="patient-info-value">${q.status ? q.status.replace(/_/g, ' ') : 'None'}</div>
                            </div>
                        </div>
                    </div>
                    <div id="queueBillingSummary"></div>
                    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                        <h3 style="font-size: 1rem; font-weight: 600; color: #dc2626; margin-bottom: 16px;">Medical History</h3>
                        <div class="patient-info-grid">
                            <div class="patient-info-item">
                                <div class="patient-info-label">Allergies</div>
                                <div class="patient-info-value">${allergies}</div>
                            </div>
                            <div class="patient-info-item">
                                <div class="patient-info-label">Conditions</div>
                                <div class="patient-info-value">${conditions}</div>
                            </div>
                            <div class="patient-info-item" style="grid-column: 1 / -1;">
                                <div class="patient-info-label">Current Medications</div>
                                <div class="patient-info-value">${medications}</div>
                            </div>
                        </div>
                    </div>
                    <div style="background: #f9fafb; border-radius: 12px; padding: 20px;">
                        <h3 style="font-size: 1rem; font-weight: 600; color: #374151; margin-bottom: 16px;">Dental History</h3>
                        <div class="patient-info-grid">
                            <div class="patient-info-item">
                                <div class="patient-info-label">Previous Dentist</div>
                                <div class="patient-info-value">${d.previous_dentist || 'N/A'}</div>
                            </div>
                            <div class="patient-info-item">
                                <div class="patient-info-label">Last Visit</div>
                                <div class="patient-info-value">${d.last_visit_date || 'N/A'}</div>
                            </div>
                            <div class="patient-info-item" style="grid-column: 1 / -1;">
                                <div class="patient-info-label">Current Complaints</div>
                                <div class="patient-info-value">${d.current_complaints || 'None'}</div>
                            </div>
                        </div>
                    </div>
                `;
                loadQueueBillingSummary(patientId);
            } else {
                alert('Patient details not found');
                document.getElementById('queuePatientModal').classList.remove('active');
            }
        })
        .catch(() => {
            alert('Unable to load patient details');
            document.getElementById('queuePatientModal').classList.remove('active');
        });
}

function loadQueueBillingSummary(patientId) {
    fetch('../shared/billing_actions.php?action=get_details&patient_id=' + encodeURIComponent(patientId))
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('queueBillingSummary');
            if (!container) return;

            if (data.success && data.billing_id) {
                var billingId = data.billing_id;
                return fetch('../shared/billing_actions.php?action=get_billing&billing_id=' + billingId)
                    .then(r => r.json())
                    .then(billData => {
                        if (billData.success && billData.billing) {
                            var b = billData.billing;
                            var statusColor = (b.payment_status || '').toLowerCase() === 'paid' ? '#166534' : '#92400e';
                            var statusBg = (b.payment_status || '').toLowerCase() === 'paid' ? '#dcfce7' : ((b.payment_status || '').toLowerCase() === 'partial' ? '#fef3c7' : '#fee2e2');
                            var statusText = (b.payment_status || 'Unpaid').charAt(0).toUpperCase() + (b.payment_status || 'Unpaid').slice(1).toLowerCase();
                            container.innerHTML = renderBillingSummaryHTML(b, statusColor, statusBg, statusText);
                        } else {
                            container.innerHTML = renderNoBillingHTML();
                        }
                    });
            } else {
                container.innerHTML = renderNoBillingHTML();
            }
        })
        .catch(() => {
            var container = document.getElementById('queueBillingSummary');
            if (container) container.innerHTML = renderNoBillingHTML();
        });
}

function renderBillingSummaryHTML(b, statusColor, statusBg, statusText) {
    var paidAmt = parseFloat(b.paid_amount || 0);
    var totalAmt = parseFloat(b.total_amount || 0);
    var balanceAmt = parseFloat(b.balance || 0);
    return '<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 20px; margin-bottom: 20px;">' +
        '<h3 style="font-size: 1rem; font-weight: 600; color: #166534; margin-bottom: 16px;">Billing Summary</h3>' +
        '<div class="patient-info-grid">' +
        '<div class="patient-info-item"><div class="patient-info-label">Invoice #</div><div class="patient-info-value">INV-' + String(b.id).padStart(4, '0') + '</div></div>' +
        '<div class="patient-info-item"><div class="patient-info-label">Total Amount</div><div class="patient-info-value">P' + totalAmt.toFixed(2) + '</div></div>' +
        '<div class="patient-info-item"><div class="patient-info-label">Paid Amount</div><div class="patient-info-value" style="color: #166534;">P' + paidAmt.toFixed(2) + '</div></div>' +
        '<div class="patient-info-item"><div class="patient-info-label">Balance</div><div class="patient-info-value" style="color: ' + (balanceAmt > 0 ? '#dc2626' : '#166534') + '; font-weight: 700;">P' + balanceAmt.toFixed(2) + '</div></div>' +
        '<div class="patient-info-item"><div class="patient-info-label">Payment Status</div><div class="patient-info-value"><span style="background:' + statusBg + '; color:' + statusColor + '; padding: 2px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">' + statusText + '</span></div></div>' +
        '<div class="patient-info-item"><div class="patient-info-label">Billing Date</div><div class="patient-info-value">' + (b.billing_date || 'N/A') + '</div></div>' +
        '<div class="patient-info-item"><div class="patient-info-label">Due Date</div><div class="patient-info-value">' + (b.due_date || 'Not set') + '</div></div>' +
        '</div></div>';
}

function renderNoBillingHTML() {
    return '<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 20px; margin-bottom: 20px;">' +
        '<h3 style="font-size: 1rem; font-weight: 600; color: #166534; margin-bottom: 16px;">Billing Summary</h3>' +
        '<p style="color: #6b7280; margin: 0;">No billing record found for this patient.</p></div>';
}

function closeQueuePatientModal() {
    document.getElementById('queuePatientModal').classList.remove('active');
}

document.addEventListener('DOMContentLoaded', function() {
    const queuePatientModal = document.getElementById('queuePatientModal');
    const assignDoctorModal = document.getElementById('assignDoctorModal');

    if (queuePatientModal && queuePatientModal.parentElement !== document.body) {
        document.body.appendChild(queuePatientModal);
    }
    if (assignDoctorModal && assignDoctorModal.parentElement !== document.body) {
        document.body.appendChild(assignDoctorModal);
    }
});

document.getElementById('queuePatientModal').addEventListener('click', function(e) {
    if (e.target === this) closeQueuePatientModal();
});

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

// Queue auto-polling
var previousQueueHash = '';
var POLL_INTERVAL_MS = 8000;
var ITEMS_PER_PAGE = 5;

function startQueuePolling() {
    setInterval(function() {
        fetch('../shared/queue_poll.php')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) return;
                if (data.hash !== previousQueueHash) {
                    previousQueueHash = data.hash;
                    refreshQueueTable(data);
                }
            })
            .catch(function(e) { console.error('Queue poll error:', e); });
    }, POLL_INTERVAL_MS);
}

function refreshQueueTable(data) {
    var tbody = document.getElementById('queueTableBody');
    if (!tbody) return;

    var items = data.items;
    var counts = data.counts;

    // Update tab counts (dentist: All, Waiting, In Procedure, Completed, Cancelled)
    var tabs = document.querySelectorAll('.tab-item');
    for (var t = 0; t < tabs.length; t++) {
        var tab = tabs[t];
        var countSpan = tab.querySelector('.tab-count');
        if (!countSpan) continue;
        var tabName = tab.dataset.tab;
        if (tabName === 'all') countSpan.textContent = counts.total;
        else if (tabName === 'in_procedure') countSpan.textContent = counts.in_procedure;
        else if (tabName === 'completed') countSpan.textContent = counts.completed;
        else if (tabName === 'on_hold') countSpan.textContent = counts.on_hold;
        else if (tabName === 'waiting') countSpan.textContent = counts.waiting;
        else if (tabName === 'cancelled') countSpan.textContent = counts.cancelled;
    }

    // Update inProcedurePatient for kebab menu
    var inProc = null;
    for (var i = 0; i < items.length; i++) {
        if (items[i].status === 'in_procedure') {
            inProc = { id: items[i].id, name: (items[i].first_name || '') + ' ' + (items[i].last_name || '') };
            break;
        }
    }
    inProcedurePatient = inProc;

    // Empty state
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="empty-state">' +
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">' +
            '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>' +
            '</svg><h3>No patients in queue</h3><p>No queue items for today</p></div></td></tr>';
        updatePaginationInfo(items.length, 1);
        return;
    }

    // Pagination
    var currentPage = 1;
    var activeBtn = document.querySelector('.pagination-btn.active');
    if (activeBtn) currentPage = parseInt(activeBtn.textContent) || 1;
    var totalPages = Math.max(1, Math.ceil(items.length / ITEMS_PER_PAGE));
    if (currentPage > totalPages) currentPage = totalPages;
    var offset = (currentPage - 1) * ITEMS_PER_PAGE;
    var pageItems = items.slice(offset, offset + ITEMS_PER_PAGE);
    var showingStart = items.length > 0 ? offset + 1 : 0;
    var showingEnd = Math.min(offset + ITEMS_PER_PAGE, items.length);

    // Build rows
    var html = '';
    for (var i = 0; i < pageItems.length; i++) {
        var item = pageItems[i];
        var time12hr = formatQueueTime(item.queue_time);
        var statusText = item.status || 'waiting';
        var statusClass = statusText.replace(/_/g, '-');
        var displayStatus = statusText.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); });
        var rowNumber = item.queue_number || (showingStart + i);
        var patientName = (item.first_name || 'Unknown') + ' ' + (item.last_name || '');
        var treatment = item.treatment_type || 'Consultation';

        html += '<tr class="queue-row" data-status="' + statusText + '" data-name="' + (item.first_name || '').toLowerCase() + '" data-treatment="' + treatment.toLowerCase() + '">';
        html += '<td>#' + rowNumber + '</td>';
        html += '<td><div class="patient-name">' + escapeHtml(item.first_name || 'Unknown') + '</div></td>';
        html += '<td>' + escapeHtml(item.middle_name || '') + '</td>';
        html += '<td><div class="patient-name">' + escapeHtml(item.last_name || 'Unknown') + '</div></td>';
        html += '<td><div style="font-size: 0.8rem; color: #6b7280;">' + escapeHtml(item.phone || '') + '</div></td>';
        html += '<td><span class="status-badge ' + statusClass + '"><span class="status-dot"></span>' + displayStatus + '</span></td>';
        html += '<td>' + escapeHtml(treatment) + '</td>';
        html += '<td><div class="time-display"><span class="time-12hr">' + time12hr + '</span></div></td>';
        html += '<td><div class="queue-kebab-menu"><button class="queue-kebab-btn" data-queue-id="' + item.id + '" data-patient-id="' + item.patient_id + '" data-patient-name="' + escapeHtml(patientName) + '" data-treatment="' + escapeHtml(treatment) + '">';
        html += '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="6" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="18" r="2"/></svg>';
        html += '</button></div></td>';
        html += '</tr>';
    }
    tbody.innerHTML = html;
    updatePaginationInfo(items.length, currentPage);

    // Re-apply filters
    var activeTab = document.querySelector('.tab-item.active');
    if (activeTab) filterQueueTable(activeTab.dataset.tab);
    if (typeof filterTable === 'function') { try { filterTable(); } catch(e) {} }
}

function formatQueueTime(timeStr) {
    if (!timeStr) return 'N/A';
    var parts = timeStr.split(':');
    if (parts.length < 2) return timeStr;
    var h = parseInt(parts[0], 10);
    var m = parseInt(parts[1], 10);
    var ampm = h >= 12 ? 'PM' : 'AM';
    if (h === 0) h = 12;
    else if (h > 12) h = h - 12;
    return h + ':' + (m < 10 ? '0' + m : m) + ' ' + ampm;
}

function escapeHtml(str) {
    if (!str) return '';
    var d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

function updatePaginationInfo(totalItems, currentPage) {
    var info = document.querySelector('.pagination-info');
    if (!info) return;
    if (totalItems === 0) {
        info.textContent = 'Showing 0-0 of 0 queue items';
        return;
    }
    var totalPages = Math.max(1, Math.ceil(totalItems / ITEMS_PER_PAGE));
    if (currentPage > totalPages) currentPage = totalPages;
    var offset = (currentPage - 1) * ITEMS_PER_PAGE;
    var start = offset + 1;
    var end = Math.min(offset + ITEMS_PER_PAGE, totalItems);
    info.textContent = 'Showing ' + start + '-' + end + ' of ' + totalItems + ' queue items';
}

document.addEventListener('DOMContentLoaded', startQueuePolling);
</script>

<?php require_once '../../includes/dentist_layout_end.php'; ?>
