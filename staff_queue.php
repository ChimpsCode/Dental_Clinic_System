<?php
$pageTitle = 'Queue Management';
require_once 'includes/staff_layout_start.php';

try {
    require_once 'config/database.php';
    
    // Get queue data with patient info
    $stmt = $pdo->query("
        SELECT q.*, p.full_name, p.phone, p.age, p.gender, p.address, p.date_of_birth,
               p.dental_insurance, p.email, p.medical_conditions, p.middle_name, p.suffix,
               p.city, p.province, p.religion
        FROM queue q 
        LEFT JOIN patients p ON q.patien        WHERE DATE(q.queue_time) = CURDATE()
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
    $queueItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $waitingCount = count(array_filter($queueItems, fn($q) => $q['status'] === 'waiting'));
    $procedureCount = count(array_filter($queueItems, fn($q) => $q['status'] === 'in_procedure'));
    $completedToday = count(array_filter($queueItems, fn($q) => $q['status'] === 'completed'));
    $onHoldCount = count(array_filter($queueItems, fn($q) => $q['status'] === 'on_hold'));
    $cancelledCount = count(array_filter($queueItems, fn($q) => $q['status'] === 'cancelled'));
    $totalInQueue = $waitingCount + $procedureCount + $onHoldCount;
    
} catch (Exception $e) {
    $queueItems = [];
    $waitingCount = 0;
    $procedureCount = 0;
    $completedToday = 0;
    $onHoldCount = 0;
    $cancelledCount = 0;
    $totalInQueue = 0;
}
?>

<style>
/* Ensure consistent background */
.content-area {
    background-color: #f3f4f6;
}

/* Top Summary Widgets */
.summary-widgets {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
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
    border: -1px solid #e5e7eb;
    overflow: hidden;
    

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
.queue-table-container {
    overflow-x: auto;
}

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
        <div class="summary-widget-icon green">✓</div>
        <div class="summary-widget-info">
            <h3><?php echo $completedToday; ?></h3>
            <p>Completed</p>
        </div>
    </div>
    <div class="summary-widget">
        <div class="summary-widget-icon red">✕</div>
        <div class="summary-widget-info">
            <h3><?php echo $cancelledCount; ?></h3>
            <p>Cancelled</p>
        </div>
    </div>
    <div class="summary-widget">
        <div class="summary-widget-icon gray">⏸</div>
        <div class="summary-widget-info">
            <h3><?php echo $onHoldCount; ?></h3>
            <p>On Hold</p>
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
                        <th>Patient Name</th>
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
                            <td colspan="7">
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
                            <tr class="queue-row" data-status="<?php echo $item['status']; ?>" data-name="<?php echo strtolower($item['full_name'] ?? ''); ?>">
                                <td><?php echo str_pad($item['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <div class="patient-name"><?php echo htmlspecialchars($item['full_name'] ?? 'Unknown'); ?></div>
                                    <div style="font-size: 0.75rem; color: #6b7280; margin-top: 2px;">
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
                                    <div class="action-buttons">
                                        <button class="action-btn" onclick="callPatient(<?php echo $item['id']; ?>)" title="Call Patient">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                                            </svg>
                                            Call
                                        </button>
                                        <button class="action-btn" onclick="editPatient(<?php echo $item['id']; ?>)" title="Edit">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                                            </svg>
                                            Edit
                                        </button>
                                        <div class="more-dropdown">
                                            <button class="more-btn" onclick="toggleMoreMenu(this)">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                    <circle cx="12" cy="6" r="2"/>
                                                    <circle cx="12" cy="12" r="2"/>
                                                    <circle cx="12" cy="18" r="2"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
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

<script>
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
pleteTreatment(${q.id}); closeFullScreenModal();" class="btn-action btn-action-success">Mark Complete</button>` : ''}
                </div>
            `;
            
            document.getElementById('fullScreenPatientModal').classList.add('active');
        }
    });
}

function closeFullScreenModal() {
    document.getElementById('fullScreenPatientModal').classList.remove('active');
}

// Close modal on outside click
document.getElementById('fullScreenPatientModal').addEventListener('click', function(e) {
    if (e.target === this) closeFullScreenModal();
});

document.getElementById('callingModal').addEventListener('click', function(e) {
    if (e.target === this) closeCallingModal();
});

// ESC key to close modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeFullScreenModal();
        closeCallingModal();
    }
});
nt(${q.id}); closeFullScreenModal();" class="btn-action btn-action-success">Mark Complete</button>` : ''}
                </div>
            `;
            
            document.getElementById('fullScreenPatientModal').classList.add('active');
        }
    });
}

function closeFullScreenModal() {
    document.getElementById('fullScreenPatientModal').classList.remove('active');
}

// Close modal on outside click
document.getElementById('fullScreenPatientModal').addEventListener('click', function(e) {
    if (e.target === this) closeFullScreenModal();
});

document.getElementById('callingModal').addEventListener('click', function(e) {
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
