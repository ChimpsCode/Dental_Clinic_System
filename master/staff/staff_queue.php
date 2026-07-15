<?php
$pageTitle = 'Queue Management';
require_once '../../config/database.php';
require_once '../../includes/staff_layout_start.php';

// Pagination settings
$itemsPerPage = 7;
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;

try {
    // Get ALL queue data for counts (without pagination)
    $allStmt = $pdo->query("
        SELECT q.*, 
               COALESCE(p.first_name, 'Unknown') as first_name, 
               COALESCE(p.middle_name, '') as middle_name, 
               COALESCE(p.last_name, '') as last_name, 
               COALESCE(p.suffix, '') as suffix, 
               COALESCE(p.phone, '') as phone,
               COALESCE(q.status, 'waiting') as status_text,
                'queue' as source_type,
                NULL as appointment_id,
                COALESCE(CONCAT(u_doc.first_name, ' ', u_doc.last_name), 'Unassigned') as assigned_doctor,
                q.queue_time as display_time
        FROM queue q 
        LEFT JOIN patients p ON CAST(q.patient_id AS UNSIGNED) = p.id
        LEFT JOIN users u_doc ON q.doctor_id = u_doc.id
        WHERE q.status IN ('waiting', 'in_procedure', 'completed', 'cancelled', 'on_hold') AND q.is_archived = 0
    ");
    $allQueueItems = $allStmt->fetchAll(PDO::FETCH_ASSOC);

    $appointmentStmt = $pdo->query("
        SELECT 
            NULL as id,
            a.patient_id,
            a.status,
            a.created_at,
            a.notes,
            COALESCE(p.first_name, 'Unknown') as first_name,
            COALESCE(p.middle_name, '') as middle_name,
            COALESCE(p.last_name, '') as last_name,
            COALESCE(p.suffix, '') as suffix,
            COALESCE(p.phone, '') as phone,
            CASE
                WHEN a.status = 'In Queue' THEN 'waiting'
                WHEN a.status = 'Scheduled' THEN 'waiting'
                ELSE LOWER(COALESCE(a.status, 'waiting'))
            END as status_text,
            'appointment' as source_type,
            a.id as appointment_id,
            COALESCE(s.name, 'General Checkup') as treatment_type,
            '' as teeth_numbers,
            COALESCE(a.appointment_time, '') as queue_time,
            'Unassigned' as assigned_doctor,
            COALESCE(a.appointment_time, '') as display_time
        FROM appointments a
        LEFT JOIN patients p ON CAST(a.patient_id AS UNSIGNED) = p.id
        LEFT JOIN services s ON s.id = a.service_id
        WHERE a.status IN ('In Queue')
          AND NOT EXISTS (
              SELECT 1
              FROM queue q
              WHERE CAST(q.patient_id AS UNSIGNED) = CAST(a.patient_id AS UNSIGNED)
                AND q.is_archived = 0
                AND q.status IN ('waiting', 'in_procedure', 'completed', 'cancelled', 'on_hold')
          )
    ");
    $appointmentQueueItems = $appointmentStmt->fetchAll(PDO::FETCH_ASSOC);

    $allQueueItems = array_merge($allQueueItems, $appointmentQueueItems);

    usort($allQueueItems, function ($a, $b) {
        $priorityMap = [
            'in_procedure' => 1,
            'waiting' => 2,
            'completed' => 3,
            'on_hold' => 4,
            'cancelled' => 5
        ];

        $statusA = $a['status_text'] ?? 'waiting';
        $statusB = $b['status_text'] ?? 'waiting';
        $priorityA = $priorityMap[$statusA] ?? 9;
        $priorityB = $priorityMap[$statusB] ?? 9;

        if ($priorityA !== $priorityB) {
            return $priorityA <=> $priorityB;
        }

        return strcmp((string)($a['display_time'] ?? ''), (string)($b['display_time'] ?? ''));
    });
    
    // Calculate counts - use status_text
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
    border-radius: 8px;
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

.fullscreen-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    justify-content: center;
    z-index: 99999;
}

.fullscreen-modal-overlay.active {
    display: flex;
    align-items: center;
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
    margin-bottom: 20px;
}

.patient-info-item {
    background: #f9fafb;
    padding: 12px 16px;
    border-radius: 8px;
}

.patient-info-label {
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 4px;
}

.patient-info-value {
    font-size: 0.9rem;
    color: #111827;
    font-weight: 500;
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



.time-12hr {
    font-weight: 400;
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

/* Complete Procedure Modal */
.complete-procedure-modal {
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
.cp-bg-orb { position: absolute; border-radius: 999px; pointer-events: none; filter: blur(8px); opacity: 0.55; }
.cp-bg-orb-a { width: 140px; height: 140px; right: -30px; top: 88px; background: radial-gradient(circle, rgba(14, 165, 233, 0.2) 0%, rgba(14, 165, 233, 0) 72%); }
.cp-bg-orb-b { width: 160px; height: 160px; left: -40px; bottom: -30px; background: radial-gradient(circle, rgba(34, 197, 94, 0.16) 0%, rgba(34, 197, 94, 0) 74%); }
.cp-banner {
    display: flex; align-items: center; gap: 16px; padding: 24px 26px 18px;
    background: linear-gradient(135deg, rgba(255,255,255,0.12), rgba(255,255,255,0) 42%), linear-gradient(135deg, #0b7db3 0%, #0891b2 38%, #0f9f53 100%);
    color: white; position: relative;
}
.cp-icon-wrap {
    width: 54px; height: 54px; border-radius: 16px; display: flex; align-items: center; justify-content: center;
    background: rgba(255, 255, 255, 0.16); border: 1px solid rgba(255, 255, 255, 0.22); backdrop-filter: blur(6px); flex-shrink: 0;
}
.cp-icon { display: flex; align-items: center; justify-content: center; }
.cp-icon svg { width: 26px; height: 26px; fill: currentColor; }
.cp-kicker { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.12em; opacity: 0.9; font-weight: 700; }
.cp-close {
    margin-left: auto; background: rgba(255, 255, 255, 0.12); color: white;
    border: 1px solid rgba(255, 255, 255, 0.18); width: 40px; height: 40px; border-radius: 12px;
    font-size: 1.5rem; cursor: pointer; backdrop-filter: blur(8px);
}
.cp-body { padding: 24px 26px 12px; position: relative; }
.cp-text { color: #1f2937; font-size: 1.06rem; line-height: 1.6; margin: 0 0 16px; font-weight: 500; }
.cp-highlight {
    display: flex; align-items: center; gap: 14px; padding: 14px 16px; border-radius: 16px;
    background: linear-gradient(135deg, #ecfeff 0%, #f0fdf4 100%); border: 1px solid #d9f99d; margin-bottom: 14px;
}
.cp-highlight-icon {
    width: 42px; height: 42px; border-radius: 14px; display: flex; align-items: center; justify-content: center;
    background: white; box-shadow: 0 10px 24px rgba(8, 145, 178, 0.12); font-size: 1.25rem; flex-shrink: 0;
}
.cp-highlight-label { color: #0f766e; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; margin-bottom: 3px; }
.cp-highlight-value { color: #1f2937; font-size: 0.95rem; font-weight: 600; }
.cp-note {
    display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 14px;
    background: #f0fdf4; color: #166534; font-size: 0.92rem; border: 1px solid #dcfce7;
}
.cp-note-dot { width: 10px; height: 10px; border-radius: 999px; background: #22c55e; box-shadow: 0 0 0 5px rgba(34, 197, 94, 0.14); flex-shrink: 0; }
.cp-actions { display: flex; justify-content: flex-end; gap: 12px; padding: 18px 26px 26px; }
.cp-btn-cancel, .cp-btn-confirm {
    border: none; border-radius: 14px; padding: 13px 20px; cursor: pointer; font-weight: 700; font-size: 0.97rem;
    transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
}
.cp-btn-cancel { background: #eef6fb; color: #315266; border: 1px solid #d8e9f4; }
.cp-btn-confirm { background: linear-gradient(135deg, #0891b2 0%, #0f9f53 100%); color: white; box-shadow: 0 14px 28px rgba(8, 145, 178, 0.24); }
.cp-btn-cancel:hover, .cp-btn-confirm:hover, .cp-close:hover { transform: translateY(-1px); box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08); }
@media (max-width: 640px) {
    .cp-banner, .cp-body, .cp-actions { padding-left: 18px; padding-right: 18px; }
    .cp-actions { flex-direction: column-reverse; }
    .cp-btn-cancel, .cp-btn-confirm { width: 100%; }
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
                All <span class="tab-count"><?php echo $totalQueueItems; ?></span>
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
                            $rawTime = $item['display_time'] ?? $item['queue_time'] ?? '';
                            $time12hr = 'N/A';
                            $time24hr = '--:--';
                            if (!empty($rawTime)) {
                                try {
                                    $queueTime = new DateTime($rawTime);
                                    $time12hr = $queueTime->format('g:i A');
                                    $time24hr = $queueTime->format('H:i');
                                } catch (Exception $e) {
                                    $time12hr = htmlspecialchars((string)$rawTime);
                                    $time24hr = '';
                                }
                            }
                            $statusText = $item['status_text'] ?? 'waiting';
                            $statusClass = strtolower(str_replace(' ', '-', $statusText));
                            $statusLabel = ucwords($statusText);
                            $rowNumber = $item['id'] ?? $item['appointment_id'] ?? ($index + 1);
                            $fullNameSearch = strtolower(trim(($item['first_name'] ?? '') . ' ' . ($item['middle_name'] ?? '') . ' ' . ($item['last_name'] ?? '')));
                        ?>
                            <tr class="queue-row" data-status="<?php echo $statusText; ?>" data-name="<?php echo htmlspecialchars($fullNameSearch); ?>">
                                <td><?php echo str_pad((string)$rowNumber, 3, '0', STR_PAD_LEFT); ?></td>
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
                                <td><?php echo htmlspecialchars($item['assigned_doctor'] ?? 'Dr. Rex'); ?></td>
                                <td>
                                    <div class="time-display">
                                        <span class="time-12hr" ><?php echo $time12hr; ?></span>
                                    </div>
                                </td>
<td>
                                    <div class="queue-kebab-menu">
                                    <button class="queue-kebab-btn" data-queue-id="<?php echo htmlspecialchars((string)($item['id'] ?? '')); ?>" data-appointment-id="<?php echo htmlspecialchars((string)($item['appointment_id'] ?? '')); ?>" data-patient-id="<?php echo $item['patient_id']; ?>" data-status="<?php echo $statusText; ?>" data-source-type="<?php echo htmlspecialchars($item['source_type'] ?? 'queue'); ?>" data-doctor-id="<?php echo htmlspecialchars((string)($item['doctor_id'] ?? '')); ?>">
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

<div id="assignDoctorModal" class="fullscreen-modal-overlay" style="display:none;">
    <div class="fullscreen-modal-content" style="max-width: 480px;">
        <div class="fullscreen-modal-header">
            <h2 style="font-size: 1.25rem; font-weight: 600; margin: 0;">Assign Doctor</h2>
            <button type="button" onclick="closeAssignDoctorModal()" class="fullscreen-modal-close">&times;</button>
        </div>
        <div class="fullscreen-modal-body">
            <input type="hidden" id="assignDoctorQueueId">
            <label style="display:block; font-size:0.85rem; font-weight:600; color:#374151; margin-bottom:8px;">Select Dentist</label>
            <select id="assignDoctorSelect" style="width:100%; padding:12px 16px; border:1px solid #d1d5db; border-radius:8px; font-size:0.9rem; background:white; cursor:pointer;">
                <option value="">Unassigned</option>
            </select>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:12px; padding:16px 24px 24px;">
            <button onclick="closeAssignDoctorModal()" style="padding:10px 20px; background:#f3f4f6; border:1px solid #d1d5db; border-radius:8px; cursor:pointer; font-weight:600; color:#374151;">Cancel</button>
            <button onclick="assignDoctor()" style="padding:10px 20px; background:#2563eb; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600;">Assign</button>
        </div>
    </div>
</div>

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

function getQueueMenuItems(queueId, patientId, status, appointmentId = '', sourceType = 'queue', doctorId = '') {
    if (sourceType === 'appointment' || !queueId) {
        return `
        <a href="javascript:void(0)" data-action="view" data-queue-id="${queueId}" data-appointment-id="${appointmentId}" data-patient-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            View
        </a>
        `;
    }

    // In Procedure: Show View, Complete, and Assign Doctor
    if (status === 'in_procedure') {
        return `
        <a href="javascript:void(0)" data-action="view" data-queue-id="${queueId}" data-patient-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            View
        </a>
        <a href="javascript:void(0)" data-action="complete" data-queue-id="${queueId}" data-patient-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
            Complete
        </a>
        <a href="javascript:void(0)" data-action="assign_doctor" data-queue-id="${queueId}" data-patient-id="${patientId}" data-doctor-id="${doctorId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Assign Doctor
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
    
    // Default: Show View, Hold, Assign Doctor, Re-queue (if on_hold), Cancel
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
        <a href="javascript:void(0)" data-action="assign_doctor" data-queue-id="${queueId}" data-patient-id="${patientId}" data-doctor-id="${doctorId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Assign Doctor
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
    
    const padding = 12;

    // Measure dropdown size by temporarily showing it invisibly
    queueKebabDropdown.style.display = 'block';
    queueKebabDropdown.style.visibility = 'hidden';
    const dropdownRect = queueKebabDropdown.getBoundingClientRect();
    const dropdownWidth = dropdownRect.width || 180;
    const dropdownHeight = dropdownRect.height || 130;
    
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

    queueKebabDropdown.style.left = left + 'px';
    queueKebabDropdown.style.top = top + 'px';
    queueKebabDropdown.style.visibility = 'visible';
}

function openQueueKebabDropdown(button) {
    if (!queueKebabDropdown) {
        createQueueKebabDropdown();
    }

    const queueId = button.dataset.queueId;
    const appointmentId = button.dataset.appointmentId || '';
    const patientId = button.dataset.patientId;
    const status = button.dataset.status;
    const sourceType = button.dataset.sourceType || 'queue';
    const doctorId = button.dataset.doctorId || '';

    queueKebabDropdown.innerHTML = getQueueMenuItems(queueId, patientId, status, appointmentId, sourceType, doctorId);
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
    const rawQueueId = link.dataset.queueId;
    const queueId = rawQueueId ? parseInt(rawQueueId, 10) : null;
    const patientId = parseInt(link.dataset.patientId);
    const doctorId = link.dataset.doctorId || '';

    closeQueueKebabDropdown();

    switch(action) {
        case 'view':
            viewPatientQueue(patientId);
            break;
        case 'complete':
            openCompleteProcedureModal(queueId);
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
        case 'assign_doctor':
            openAssignDoctorModal(queueId, doctorId);
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
                                <div class="patient-info-value">${q.teeth_numbers ? getQueueTeethDisplayText(q.teeth_numbers) : 'None specified'}</div>
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

// Hold Patient
function holdPatientQueue(queueId) {
    if (!confirm('Put this patient on hold?')) return;
    
    fetch('../shared/queue_actions.php', {
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
    
    fetch('../shared/queue_actions.php', {
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
    
    fetch('../shared/queue_actions.php', {
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
    
    fetch('../shared/queue_actions.php', {
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

// Assign Doctor Modal
let cachedDoctors = null;

function openAssignDoctorModal(queueId, currentDoctorId) {
    document.getElementById('assignDoctorQueueId').value = queueId;
    document.getElementById('assignDoctorModal').style.display = 'flex';
    var select = document.getElementById('assignDoctorSelect');
    select.innerHTML = '<option value="">Loading...</option>';

    function populateSelect(doctors) {
        select.innerHTML = '<option value="">Unassigned</option>';
        doctors.forEach(function(doc) {
            var fullName = ((doc.first_name || '') + ' ' + (doc.middle_name || '') + ' ' + (doc.last_name || '')).trim();
            var opt = document.createElement('option');
            opt.value = doc.id;
            opt.textContent = fullName || doc.username;
            if (String(doc.id) === String(currentDoctorId)) opt.selected = true;
            select.appendChild(opt);
        });
    }

    if (cachedDoctors) {
        populateSelect(cachedDoctors);
    } else {
        fetch('../shared/queue_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_doctors' })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.doctors) {
                cachedDoctors = data.doctors;
                populateSelect(data.doctors);
            } else {
                select.innerHTML = '<option value="">No doctors found</option>';
            }
        })
        .catch(function() {
            select.innerHTML = '<option value="">Failed to load doctors</option>';
        });
    }
}

function closeAssignDoctorModal() {
    document.getElementById('assignDoctorModal').style.display = 'none';
}

function assignDoctor() {
    var queueId = document.getElementById('assignDoctorQueueId').value;
    var doctorId = document.getElementById('assignDoctorSelect').value;

    fetch('../shared/queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'assign_doctor', queue_id: parseInt(queueId), doctor_id: doctorId ? parseInt(doctorId) : null })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Failed to assign doctor');
        }
    })
    .catch(function() {
        alert('Error assigning doctor');
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

function closeQueuePatientModal() {
    document.getElementById('queuePatientModal').classList.remove('active');
}

function closeFullScreenModal() {
    closeQueuePatientModal();
}

document.getElementById('queuePatientModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeQueuePatientModal();
});

// ESC key to close modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeQueuePatientModal();
        closeCompleteProcedureModal();
    }
});

// Complete Procedure Modal
function openCompleteProcedureModal(queueId) {
    document.getElementById('completeProcedureQueueId').value = queueId;
    document.getElementById('completeProcedureModal').style.display = 'flex';
}

function closeCompleteProcedureModal() {
    document.getElementById('completeProcedureModal').style.display = 'none';
    document.getElementById('completeProcedureQueueId').value = '';
}

function confirmCompleteProcedure() {
    const queueId = document.getElementById('completeProcedureQueueId').value;
    if (!queueId) return;

    fetch('../shared/queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'complete', queue_id: queueId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeCompleteProcedureModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Unable to complete procedure'));
        }
    })
    .catch(() => {
        alert('Unable to complete procedure');
    });
}

function getQueueTeethDisplayText(teethString) {
    if (!teethString || teethString.trim() === '') {
        return 'None specified';
    }

    const teeth = teethString.split(',').map(t => parseInt(t.trim(), 10)).filter(t => !isNaN(t));
    if (!teeth.length) {
        return 'None specified';
    }

    const upperArch = [11, 12, 13, 14, 15, 16, 17, 18, 21, 22, 23, 24, 25, 26, 27, 28];
    const lowerArch = [31, 32, 33, 34, 35, 36, 37, 38, 41, 42, 43, 44, 45, 46, 47, 48];
    const parts = [];

    if (teeth.length === 16 && upperArch.every(t => teeth.includes(t))) {
        parts.push('Upper Arch');
    }

    if (teeth.length === 16 && lowerArch.every(t => teeth.includes(t))) {
        parts.push('Lower Arch');
    }

    return parts.length ? parts.join(' + ') : teeth.sort((a, b) => a - b).join(', ');
}
</script>

<!-- Complete Procedure Modal -->
<div id="completeProcedureModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div class="complete-procedure-modal" style="position: relative; z-index: 100000;">
        <div class="cp-bg-orb cp-bg-orb-a"></div>
        <div class="cp-bg-orb cp-bg-orb-b"></div>
        <div class="cp-banner">
            <div class="cp-icon-wrap">
                <span class="cp-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M9.2 16.2 5.5 12.5l-1.4 1.4 5.1 5.1L20 8.2l-1.4-1.4z"></path>
                    </svg>
                </span>
            </div>
            <div>
                <div class="cp-kicker">Staff Queue</div>
                <h2 style="font-size: 1.55rem; font-weight: 700; margin: 6px 0 0;">Complete Procedure</h2>
            </div>
            <button onclick="closeCompleteProcedureModal()" class="cp-close">&times;</button>
        </div>
        <div class="cp-body">
            <p class="cp-text">Mark procedure as complete and move this patient to billing?</p>
            <div class="cp-highlight">
                <div class="cp-highlight-icon">&#129463;</div>
                <div>
                    <div class="cp-highlight-label">Patient Care Flow</div>
                    <div class="cp-highlight-value">Procedure finishes here, billing continues next.</div>
                </div>
            </div>
            <div class="cp-note">
                <span class="cp-note-dot"></span>
                The dashboard and payment list will refresh after confirmation.
            </div>
        </div>
        <input type="hidden" id="completeProcedureQueueId">
        <div class="cp-actions">
            <button type="button" onclick="closeCompleteProcedureModal()" class="cp-btn-cancel">Keep Open</button>
            <button type="button" onclick="confirmCompleteProcedure()" class="cp-btn-confirm">Yes, Complete</button>
        </div>
    </div>
</div>

<?php require_once '../../includes/staff_layout_end.php'; ?>
