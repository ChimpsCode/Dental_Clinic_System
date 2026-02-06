<?php
/**
 * Archive Management Page
 * 
 * This page provides a centralized interface for managing archived records
 * across all modules with soft delete functionality.
 * 
 * Features:
 * - 7-Tab Interface (Patient Records, Appointments, Queue, Treatment Plans, Services, Inquiries, Dentist/Doctor Records)
 * - Search functionality
 * - Date range filtering
 * - Bulk restore
 * - Bulk permanent delete
 * - Individual restore/delete actions
 * 
 * @package Dental_Clinic_System
 * @version 1.0
 * @date 2026-02-04
 */

$pageTitle = 'Archive Management';
require_once 'config/database.php';

// Check if archive columns exist
$archiveReady = true;
$tables = ['patients', 'appointments', 'queue', 'treatment_plans', 'services', 'inquiries', 'users'];

foreach ($tables as $table) {
    $checkColumn = $pdo->query("SHOW COLUMNS FROM $table LIKE 'is_archived'");
    if ($checkColumn->rowCount() == 0) {
        $archiveReady = false;
        break;
    }
}

// Get archived counts if ready
$archivedCounts = [
    'patients' => 0,
    'appointments' => 0,
    'queue' => 0,
    'treatment_plans' => 0,
    'services' => 0,
    'inquiries' => 0,
    'users' => 0
];

if ($archiveReady) {
    foreach ($archivedCounts as $table => &$count) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table WHERE is_archived = 1");
            $count = $stmt->fetchColumn();
        } catch (Exception $e) {
            $count = 0;
        }
    }
}

require_once 'includes/admin_layout_start.php';
?>

<style>
/* Archive Page Specific Styles */
.archive-container {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

/* Tab Navigation */
.tab-navigation {
    display: flex;
    gap: 4px;
    border-bottom: 2px solid #e5e7eb;
    margin-bottom: 0px;
    overflow-x: visible;
    padding-bottom: 2px;
    flex-wrap: wrap;
    min-height: 50px;
    align-items: flex-end;
}

.tab-btn {
    padding: 8px 14px;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 0.75rem;
    font-weight: 500;
    color: #6b7280;
    transition: all 0.3s ease;
    white-space: nowrap;
    margin-bottom: -2px;
    flex-shrink: 0;
}

.tab-btn:hover {
    color: #0ea5e9;
    background: #f0f9ff;
}

.tab-btn.active {
    color: #0ea5e9;
    border-bottom-color: #0ea5e9;
    background: #f0f9ff;
}

/* Tab Content */
.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* Bulk Actions */
.bulk-actions {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
}

.btn-restore {
    padding: 10px 20px;
    background: #10b981;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-restore:hover:not(:disabled) {
    background: #059669;
}

.btn-restore:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-delete-forever {
    padding: 10px 20px;
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-delete-forever:hover:not(:disabled) {
    background: #dc2626;
}

.btn-delete-forever:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-filter {
    padding: 10px 20px;
    background: #0ea5e9;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-filter:hover {
    background: #0284c7;
}

.btn-reset {
    padding: 10px 20px;
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-reset:hover {
    background: #e5e7eb;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.75rem;
    margin-right: 8px;
}

/* Summary Cards - 7 columns */
.summary-cards.archive-stats {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 12px;
    margin-bottom: 0px;
}

.summary-card.archive-card {
    background: white;
    padding: 16px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    text-align: center;
    transition: all 0.2s;
}

.summary-card.archive-card:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.summary-card.archive-card .count {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 4px;
}

.summary-card.archive-card .label {
    font-size: 0.75rem;
    color: #6b7280;
}

/* Color coding for each module */
.summary-card.patients .count { color: #667eea; }
.summary-card.appointments .count { color: #f093fb; }
.summary-card.queue .count { color: #4facfe; }
.summary-card.treatment_plans .count { color: #43e97b; }
.summary-card.services .count { color: #fa709a; }
.summary-card.inquiries .count { color: #30cfd0; }
.summary-card.users .count { color: #a8edea; }

/* Responsive */
@media (max-width: 1400px) {
    .summary-cards.archive-stats {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 1024px) {
    .summary-cards.archive-stats {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .summary-cards.archive-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .tab-navigation {
        flex-wrap: wrap;
        gap: 4px;
    }
    
    .tab-btn {
        padding: 8px 12px;
        font-size: 0.75rem;
    }
}

/* Migration Alert */
.migration-alert {
    background: #fef3c7;
    border: 1px solid #fbbf24;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 24px;
}

.migration-alert h3 {
    color: #92400e;
    margin-bottom: 10px;
}

.migration-alert p {
    color: #78350f;
    margin-bottom: 15px;
}

.migration-alert code {
    background: #fef9c3;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: monospace;
}

/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: capitalize;
}

.status-scheduled {
    background: #dbeafe;
    color: #1e40af;
}

.status-completed {
    background: #d1fae5;
    color: #065f46;
}

.status-cancelled {
    background: #fee2e2;
    color: #991b1b;
}

.status-waiting {
    background: #fef3c7;
    color: #92400e;
}

.status-in_procedure {
    background: #e0e7ff;
    color: #3730a3;
}

.status-on_hold {
    background: #f3e8ff;
    color: #6b21a8;
}
</style>

<div class="content-main">
    <!-- Page Header -->
    <div class="page-header" style="margin-bottom: 0px;">
        <h2 style="margin: 0;">Archive Management</h2>
        <p style="color: #6b7280; font-size: 0.875rem; margin: 8px 0 0 0;">
            Manage archived records - restore or permanently delete
        </p>
    </div>

    <?php if (!$archiveReady): ?>
    <!-- Migration Required Alert -->
    <div class="migration-alert">
        <h3>Archive System Setup Required</h3>
        <p>The archive system is not yet configured. Please run the database migration to enable archive functionality.</p>
        <p><strong>Steps:</strong></p>
        <ol style="color: #78350f; margin-bottom: 15px;">
            <li>Open phpMyAdmin</li>
            <li>Select <code>dental_management</code> database</li>
            <li>Go to SQL tab</li>
            <li>Paste contents of <code>config/add_archive_columns.sql</code></li>
            <li>Click "Go"</li>
        </ol>
        <p style="margin-bottom: 0;">Or run via MySQL CLI: <code>mysql -u root -p dental_management &lt; config/add_archive_columns.sql</code></p>
    </div>
    <?php endif; ?>

    <!-- Summary Statistics Cards -->
    <div class="summary-cards archive-stats">
        <div class="summary-card archive-card patients">
            <div class="count"><?php echo $archivedCounts['patients']; ?></div>
            <div class="label">Patient Records</div>
        </div>
        <div class="summary-card archive-card appointments">
            <div class="count"><?php echo $archivedCounts['appointments']; ?></div>
            <div class="label">Appointments</div>
        </div>
        <div class="summary-card archive-card queue">
            <div class="count"><?php echo $archivedCounts['queue']; ?></div>
            <div class="label">Queue Management</div>
        </div>
        <div class="summary-card archive-card treatment_plans">
            <div class="count"><?php echo $archivedCounts['treatment_plans']; ?></div>
            <div class="label">Treatment Plans</div>
        </div>
        <div class="summary-card archive-card services">
            <div class="count"><?php echo $archivedCounts['services']; ?></div>
            <div class="label">Services/Procedures</div>
        </div>
        <div class="summary-card archive-card inquiries">
            <div class="count"><?php echo $archivedCounts['inquiries']; ?></div>
            <div class="label">Inquiries</div>
        </div>
        <div class="summary-card archive-card users">
            <div class="count"><?php echo $archivedCounts['users']; ?></div>
            <div class="label">Dentist/Doctor Records</div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-navigation">
        <button class="tab-btn active" data-tab="patients" onclick="switchTab('patients')">
            Patient Records
        </button>
        <button class="tab-btn" data-tab="appointments" onclick="switchTab('appointments')">
            Appointments
        </button>
        <button class="tab-btn" data-tab="queue" onclick="switchTab('queue')">
            Queue Management
        </button>
        <button class="tab-btn" data-tab="treatment_plans" onclick="switchTab('treatment_plans')">
            Treatment Plans
        </button>
        <button class="tab-btn" data-tab="services" onclick="switchTab('services')">
            Services/Procedures
        </button>
        <button class="tab-btn" data-tab="inquiries" onclick="switchTab('inquiries')">
            Inquiries
        </button>
        <button class="tab-btn" data-tab="users" onclick="switchTab('users')">
            Dentist/Doctor Records
        </button>
    </div>

    <!-- PATIENT RECORDS TAB -->
    <div id="patients-content" class="tab-content active">
        <?php if (!$archiveReady): ?>
        <div style="text-align: center; padding: 60px; color: #6b7280;">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 20px; opacity: 0.5;">
                <path d="M20.54 5.23l-1.39-1.68C18.88 3.21 18.47 3 18 3H6c-.47 0-.88.21-1.16.55L3.46 5.23C3.17 5.57 3 6.02 3 6.5V19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6.5c0-.48-.17-.93-.46-1.27zM12 17.5L6.5 12H10v-2h4v2h3.5L12 17.5z"/>
            </svg>
            <h3 style="margin-bottom: 10px;">Archive Not Configured</h3>
            <p>Please run the database migration to enable archive functionality.</p>
        </div>
        <?php else: ?>
        <!-- Search & Filters -->
        <div class="search-filters" style="margin-bottom: 16px;">
            <input type="text" id="patients-search" placeholder="Search by patient name..." class="search-input" style="min-width: 300px;">
            <input type="date" id="patients-dateFrom" class="filter-select" title="From Date">
            <input type="date" id="patients-dateTo" class="filter-select" title="To Date">
            <button class="btn-filter" onclick="loadArchivedPatients(1)">Filter</button>
            <button class="btn-reset" onclick="resetFilters('patients')">Reset</button>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions">
            <button id="bulk-restore-patients" class="btn-restore" onclick="bulkAction('patients', 'restore')" disabled>
                Restore Selected
            </button>
            <button id="bulk-delete-patients" class="btn-delete-forever" onclick="bulkAction('patients', 'delete_forever')" disabled>
                Delete Forever
            </button>
        </div>

        <!-- Table -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">
                            <input type="checkbox" id="select-all-patients" onchange="toggleSelectAll('patients', this.checked)">
                        </th>
                        <th>Patient</th>
                        <th>Contact</th>
                        <th>Date of Birth</th>
                        <th>Archived Date</th>
                        <th style="width: 200px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="patients-table-body">
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 60px; color: #6b7280;">
                            Loading archived patients...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination" id="patients-pagination"></div>
        <?php endif; ?>
    </div>

    <!-- APPOINTMENTS TAB -->
    <div id="appointments-content" class="tab-content">
        <?php if (!$archiveReady): ?>
        <div style="text-align: center; padding: 60px; color: #6b7280;">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 20px; opacity: 0.5;">
                <path d="M20.54 5.23l-1.39-1.68C18.88 3.21 18.47 3 18 3H6c-.47 0-.88.21-1.16.55L3.46 5.23C3.17 5.57 3 6.02 3 6.5V19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6.5c0-.48-.17-.93-.46-1.27zM12 17.5L6.5 12H10v-2h4v2h3.5L12 17.5z"/>
            </svg>
            <h3 style="margin-bottom: 10px;">Archive Not Configured</h3>
            <p>Please run the database migration to enable archive functionality.</p>
        </div>
        <?php else: ?>
        <!-- Search & Filters -->
        <div class="search-filters" style="margin-bottom: 16px;">
            <input type="text" id="appointments-search" placeholder="Search by patient name..." class="search-input" style="min-width: 300px;">
            <input type="date" id="appointments-dateFrom" class="filter-select" title="Appointment Date From">
            <input type="date" id="appointments-dateTo" class="filter-select" title="Appointment Date To">
            <button class="btn-filter" onclick="loadArchivedAppointments(1)">Filter</button>
            <button class="btn-reset" onclick="resetFilters('appointments')">Reset</button>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions">
            <button id="bulk-restore-appointments" class="btn-restore" onclick="bulkAction('appointments', 'restore')" disabled>
                Restore Selected
            </button>
            <button id="bulk-delete-appointments" class="btn-delete-forever" onclick="bulkAction('appointments', 'delete_forever')" disabled>
                Delete Forever
            </button>
        </div>

        <!-- Table -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">
                            <input type="checkbox" id="select-all-appointments" onchange="toggleSelectAll('appointments', this.checked)">
                        </th>
                        <th>Patient</th>
                        <th>Appointment Date & Time</th>
                        <th>Service</th>
                        <th>Status</th>
                        <th>Archived Date</th>
                        <th style="width: 200px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="appointments-table-body">
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 60px; color: #6b7280;">
                            Loading archived appointments...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination" id="appointments-pagination"></div>
        <?php endif; ?>
    </div>

    <!-- QUEUE TAB (Placeholder) -->
    <div id="queue-content" class="tab-content">
        <div style="text-align: center; padding: 60px; color: #6b7280;">
            <h3>Queue Management Archive</h3>
            <p>This feature will be implemented in Phase 3.</p>
        </div>
    </div>

    <!-- TREATMENT PLANS TAB (Placeholder) -->
    <div id="treatment_plans-content" class="tab-content">
        <div style="text-align: center; padding: 60px; color: #6b7280;">
            <h3>Treatment Plans Archive</h3>
            <p>This feature will be implemented in Phase 4.</p>
        </div>
    </div>

    <!-- SERVICES TAB (Placeholder) -->
    <div id="services-content" class="tab-content">
        <div style="text-align: center; padding: 60px; color: #6b7280;">
            <h3>Services/Procedures Archive</h3>
            <p>This feature will be implemented in Phase 5.</p>
        </div>
    </div>

    <!-- INQUIRIES TAB -->
    <div id="inquiries-content" class="tab-content">
        <?php if (!$archiveReady): ?>
        <div style="text-align: center; padding: 60px; color: #6b7280;">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 20px; opacity: 0.5;">
                <path d="M20.54 5.23l-1.39-1.68C18.88 3.21 18.47 3 18 3H6c-.47 0-.88.21-1.16.55L3.46 5.23C3.17 5.57 3 6.02 3 6.5V19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6.5c0-.48-.17-.93-.46-1.27zM12 17.5L6.5 12H10v-2h4v2h3.5L12 17.5z"/>
            </svg>
            <h3 style="margin-bottom: 10px;">Archive Not Configured</h3>
            <p>Please run the database migration to enable archive functionality.</p>
        </div>
        <?php else: ?>
        <!-- Search & Filters -->
        <div class="search-filters" style="margin-bottom: 16px;">
            <input type="text" id="inquiries-search" placeholder="Search by name..." class="search-input" style="min-width: 300px;">
            <input type="date" id="inquiries-dateFrom" class="filter-select" title="Submission Date From">
            <input type="date" id="inquiries-dateTo" class="filter-select" title="Submission Date To">
            <button class="btn-filter" onclick="loadArchivedInquiries(1)">Filter</button>
            <button class="btn-reset" onclick="resetFilters('inquiries')">Reset</button>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions">
            <button id="bulk-restore-inquiries" class="btn-restore" onclick="bulkAction('inquiries', 'restore')" disabled>
                Restore Selected
            </button>
            <button id="bulk-delete-inquiries" class="btn-delete-forever" onclick="bulkAction('inquiries', 'delete_forever')" disabled>
                Delete Forever
            </button>
        </div>

        <!-- Table -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">
                            <input type="checkbox" id="select-all-inquiries" onchange="toggleSelectAll('inquiries', this.checked)">
                        </th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Subject</th>
                        <th>Submission Date</th>
                        <th>Archived Date</th>
                        <th style="width: 200px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="inquiries-table-body">
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 60px; color: #6b7280;">
                            Loading archived inquiries...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination" id="inquiries-pagination"></div>
        <?php endif; ?>
    </div>

    <!-- USERS TAB (Placeholder) -->
    <div id="users-content" class="tab-content">
        <div style="text-align: center; padding: 60px; color: #6b7280;">
            <h3>Dentist/Doctor Records Archive</h3>
            <p>This feature will be implemented in Phase 7.</p>
        </div>
    </div>
</div>

<script src="assets/js/archive.js"></script>

<?php require_once 'includes/admin_layout_end.php'; ?>
