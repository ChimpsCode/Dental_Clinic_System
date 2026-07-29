<?php
/**
 * Appointments - Admin page for viewing and managing all appointments
 */

$pageTitle = 'Appointments';

// Pagination settings
$itemsPerPage = 5;
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;

try {
    require_once __DIR__ . '/../../config/database.php';
    
    // Auto-cancel no-show trigger on page visit
    try {
        require_once __DIR__ . '/../../includes/auto_cancel_helper.php';
        autoCancelNoShows($pdo);
    } catch (Exception $e) {
        // Silently fail
    }
    
    // Load settings for business hours and unavailable dates
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('business_hours', 'unavailable_dates')");
    $settingsRows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $businessHours = isset($settingsRows['business_hours']) ? json_decode($settingsRows['business_hours'], true) : [];
    $unavailableDates = isset($settingsRows['unavailable_dates']) ? json_decode($settingsRows['unavailable_dates'], true) : [];
    if (!$businessHours) $businessHours = [];
    if (!$unavailableDates) $unavailableDates = [];
    
    // Check if is_archived column exists
    $checkCol = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'is_archived'");
    $hasArchiveColumn = $checkCol->rowCount() > 0;
    
    // Build WHERE clause
    $whereClause = $hasArchiveColumn ? "WHERE (a.is_archived = 0 OR a.is_archived IS NULL)" : "";
    $countWhereClause = $hasArchiveColumn ? "WHERE is_archived = 0 OR is_archived IS NULL" : "";
    
    // Get total count for pagination (exclude archived)
    $countStmt = $pdo->query("SELECT COUNT(*) FROM appointments $countWhereClause");
    $totalAppointments = $countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalAppointments / $itemsPerPage));
    
    // Ensure current page is valid
    if ($currentPage > $totalPages) $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    // Calculate showing range
    $showingStart = $totalAppointments > 0 ? $offset + 1 : 0;
    $showingEnd = min($offset + $itemsPerPage, $totalAppointments);
    
    // Get all appointments for stats (without pagination, exclude archived)
    $allStmt = $pdo->query("SELECT a.*, 
                         p.first_name, p.middle_name, p.last_name,
                         COALESCE(p.phone, '') AS phone,
                         s.name as service_name,
                         a.status as status_label,
                         CONCAT(d.first_name, ' ', d.last_name) as dentist_name
                         FROM appointments a 
                         LEFT JOIN patients p ON a.patient_id = p.id
                         LEFT JOIN services s ON a.service_id = s.id
                         LEFT JOIN users d ON a.dentist_id = d.id
                         " . $whereClause);
    $allAppointments = $allStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get paginated appointments (exclude archived)
    $stmt = $pdo->prepare("SELECT a.*, 
                         p.first_name, p.middle_name, p.last_name,
                         COALESCE(p.phone, '') AS phone,
                         s.name as service_name,
                         a.status as status_label,
                         tp.id as treatment_plan_id, tp.plan_name as treatment_plan_name,
                         CONCAT(d.first_name, ' ', d.last_name) as dentist_name
                         FROM appointments a 
                         LEFT JOIN patients p ON a.patient_id = p.id 
                         LEFT JOIN services s ON a.service_id = s.id
                         LEFT JOIN treatment_plans tp ON a.id = tp.appointment_id
                         LEFT JOIN users d ON a.dentist_id = d.id
                         $whereClause
                         ORDER BY a.created_at DESC
                         LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $appointments = [];
    $allAppointments = [];
    $totalAppointments = 0;
    $totalPages = 1;
    $showingStart = 0;
    $showingEnd = 0;
    $unavailableDates = [];
}

$todayCount = 0;
$completedCount = 0;
$pendingCount = 0;
$cancelledCount = 0;
try {
    $baseWhere = $countWhereClause ? $countWhereClause . " AND " : "WHERE ";
    $todayCount = (int)($pdo->query("SELECT COUNT(*) FROM appointments $baseWhere DATE(appointment_date) = CURDATE()")->fetchColumn() ?? 0);
    $completedCount = (int)($pdo->query("SELECT COUNT(*) FROM appointments $baseWhere status = 'Completed' AND DATE(appointment_date) = CURDATE()")->fetchColumn() ?? 0);
    $pendingCount = (int)($pdo->query("SELECT COUNT(*) FROM appointments $baseWhere status IN ('Scheduled','In Queue') AND DATE(appointment_date) = CURDATE()")->fetchColumn() ?? 0);
    $cancelledCount = (int)($pdo->query("SELECT COUNT(*) FROM appointments $baseWhere status = 'Cancelled' AND DATE(appointment_date) = CURDATE()")->fetchColumn() ?? 0);
} catch (Exception $e) {
    $todayCount = 0;
    $completedCount = 0;
    $pendingCount = 0;
    $cancelledCount = 0;
}

require_once __DIR__ . '/../../includes/admin_layout_start.php';
?>
            <div class="content-main">
                
                

                <!-- Stats Cards -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon blue">&#128197;</div>
                        <div class="summary-info">
                            <h3><?php echo $todayCount; ?></h3>
                            <p>Today's Appointments</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon green">&#10003;</div>
                        <div class="summary-info">
                            <h3><?php echo $completedCount; ?></h3>
                            <p>Completed</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon yellow">&#9203;</div>
                        <div class="summary-info">
                            <h3><?php echo $pendingCount; ?></h3>
                            <p>Pending</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon red">&#10060;</div>
                        <div class="summary-info">
                            <h3><?php echo $cancelledCount; ?></h3>
                            <p>Cancelled</p>
                        </div>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 12px;">
                    <button class="view-toggle-btn active" data-view="list">📋 List View</button>
                    <button class="view-toggle-btn" data-view="calendar">📅 Calendar View</button>
                </div>

                <style>
                .view-toggle-btn { padding: 8px 16px; border: 1px solid #d1d5db; background: white; cursor: pointer; font-size: 14px; font-weight: 500; color: #6b7280; border-radius: 6px; transition: all 0.2s; }
                .view-toggle-btn:hover { background: #f3f4f6; }
                .view-toggle-btn.active { background: #2563eb; color: white; border-color: #2563eb; }
                .calendar-view { display: none; }
                .calendar-view.active { display: block; }
                .list-view { display: block; }
                .list-view.hidden { display: none; }
                .admin-cal { background: white; border-radius: 12px; padding: 24px; }
                .admin-cal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
                .admin-cal-title { font-size: 20px; font-weight: 700; color: #1a1a2e; }
                .admin-cal-nav { display: flex; gap: 8px; }
                .admin-cal-nav button { background: #f3f4f6; border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: all 0.2s; }
                .admin-cal-nav button:hover { background: #e5e7eb; }
                .admin-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
                .admin-cal-day-header { text-align: center; font-size: 12px; font-weight: 600; color: #6b7280; padding: 8px 0; text-transform: uppercase; }
                .admin-cal-day { min-height: 100px; padding: 8px; border: 1px solid #e5e7eb; border-radius: 8px; background: white; cursor: pointer; transition: all 0.2s; }
                .admin-cal-day:hover { border-color: #2563eb; background: #f0f7ff; }
                .admin-cal-day.today { background: #dcf0ff; border-color: #2563eb; }
                .admin-cal-day.empty { background: #f9fafb; cursor: default; border-color: #f3f4f6; }
                .admin-cal-day.empty:hover { background: #f9fafb; border-color: #f3f4f6; }
                .admin-cal-day.closed { background: #fef2f2; border-color: #fecaca; }
                .admin-cal-day .day-num { font-weight: 500; font-size: 14px; color: #374151; margin-bottom: 4px; }
                .admin-cal-day .appt-count { font-size: 11px; color: #2563eb; background: #e0f2fe; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-bottom: 4px; }
                .admin-cal-day .appt-mini { font-size: 10px; color: #6b7280; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                .admin-cal-day .closed-label { font-size: 11px; color: #dc2626; font-weight: 500; }
                .admin-day-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; justify-content: center; align-items: center; }
                .admin-day-modal.active { display: flex; }
                .admin-day-modal-content { background: white; border-radius: 12px; padding: 24px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; }
                .admin-day-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
                .admin-day-modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280; }
                .admin-day-modal-item { padding: 12px; border-bottom: 1px solid #f3f4f6; }
                .admin-day-modal-item:last-child { border-bottom: none; }
                </style>

                <div class="list-view" id="listView">
                <div class="search-filters" style="margin-bottom: 20px;">
                    <div class="filter-tabs">
                        <span class="active" data-filter="all">All</span>
                        <span data-filter="today">Today</span>
                        <span data-filter="week">This Week</span>
                        <span data-filter="month">This Month</span>
                    </div>
                    <input type="text" class="search-input" placeholder="Search appointments..." id="appointmentSearch">

                    <div class="page-header">
                    <h2>Appointments Management</h2>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn-primary" onclick="prepareNewAppointmentModal()">+ New Appointment</button>
                    </div>
                </div>
                </div>

                <!-- Appointments Table -->
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>First Name</th>
                                <th>Middle Name</th>
                                <th>Last Name</th>
                                <th>Date & Time</th>
                                <th>Treatment</th>
                                <th>Dentist</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="appointmentsTableBody">
                            <?php if (empty($appointments)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 60px; color: #6b7280;">
                                        <p style="font-size: 1.1rem; margin-bottom: 8px;">No appointments found</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($appointments as $appt): ?>
                                    <?php
                                        $firstName = htmlspecialchars($appt['first_name'] ?? '');
                                        $middleName = htmlspecialchars($appt['middle_name'] ?? '');
                                        $lastName = htmlspecialchars($appt['last_name'] ?? '');
                                        $fullName = trim("$firstName $middleName $lastName") ?: 'Unknown';
                                    ?>
                                    <tr class="appointment-row" 
                                        data-name="<?php echo strtolower($fullName); ?>"
                                        data-date="<?php echo date('Y-m-d', strtotime($appt['appointment_date'])); ?>"
                                        data-status="<?php echo strtolower($appt['status_label'] ?? $appt['status'] ?? ''); ?>">
                                        <td><?php echo $firstName ?: '-'; ?></td>
                                        <td><?php echo $middleName ?: '-'; ?></td>
                                        <td><?php echo $lastName ?: '-'; ?></td>
                                        <td>
                                            <div><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></div>
                                            <div style="font-size: 0.85rem; color: #6b7280;"><?php echo date('h:i A', strtotime($appt['appointment_time'] ?? '')); ?></div>
                                            <div style="font-size: 0.85rem; color: #6b7280;">Phone: <?php echo htmlspecialchars($appt['phone'] ?? 'N/A'); ?></div>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($appt['service_name'] ?? 'General'); ?>
                                            <?php if (!empty($appt['treatment_plan_id'])): ?>
                                                <br><span class="status-badge" style="background: #fef3c7; color: #92400e; font-size: 0.7rem; margin-top: 4px; display: inline-block;">📋 Treatment Plan</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($appt['dentist_name'] ?? 'Unassigned'); ?></td>
                                        <td>
                                            <?php 
                                                // Robust status resolution
                                                $status = $appt['status_label'] ?? $appt['status'] ?? 'Scheduled';
                                                
                                                $statusClass = strtolower($status);
                                                // Map status to badge class
                                                $badgeClass = 'status-scheduled';
                                                if (stripos($status, 'arrived') !== false) $badgeClass = 'status-arrived';
                                                if (stripos($status, 'queue') !== false) $badgeClass = 'status-in-queue';
                                                if (stripos($status, 'pending') !== false) $badgeClass = 'status-pending';
                                                if (stripos($status, 'complete') !== false) $badgeClass = 'status-completed';
                                                if (stripos($status, 'cancel') !== false) $badgeClass = 'status-cancelled';
                                            ?>
                                            <span class="status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                                        </td>
                                        <td>
                                            <div class="appt-kebab-menu">
                                                <button class="appt-kebab-btn"
                                                        data-appt-id="<?php echo $appt['id']; ?>"
                                                        data-status="<?php echo htmlspecialchars($status); ?>"
                                                        data-checked-in="<?php echo $appt['check_in_time'] ?? ''; ?>">
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
                <div class="pagination">
                    <span class="pagination-info">Showing <?php echo $showingStart; ?>-<?php echo $showingEnd; ?> of <?php echo $totalAppointments; ?> appointments</span>
                    <div class="pagination-buttons">
                        <?php if ($currentPage > 1): ?>
                            <a href="?page=<?php echo $currentPage - 1; ?>" class="pagination-btn">Previous</a>
                        <?php else: ?>
                            <button class="pagination-btn" disabled>Previous</button>
                        <?php endif; ?>
                        
                        <?php
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
            </div>

            <div class="calendar-view" id="calendarView">
                <div class="admin-cal">
                    <div class="admin-cal-header">
                        <h3 class="admin-cal-title" id="adminCalMonthYear"></h3>
                        <div class="admin-cal-nav">
                            <button onclick="changeAdminCalMonth(-1)">&#9664;</button>
                            <button onclick="changeAdminCalMonth(1)">&#9654;</button>
                        </div>
                    </div>
                    <div class="admin-cal-grid" id="adminCalGrid"></div>
                </div>
            </div>

            <div class="admin-day-modal" id="adminDayModal">
                <div class="admin-day-modal-content">
                    <div class="admin-day-modal-header">
                        <h3 id="adminDayTitle">Appointments</h3>
                        <button class="admin-day-modal-close" onclick="closeAdminDayModal()">&times;</button>
                    </div>
                    <div id="adminDayContent"></div>
                </div>
            </div>

<!-- View Appointment Modal -->
<div id="viewAppointmentModal" class="modal-overlay">
    <div class="modal" style="width: 500px;">
        <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Appointment Details</h2>
        <div id="viewAppointmentContent"></div>
        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px;">
            <button onclick="closeViewAppointmentModal()" class="btn-cancel">Close</button>
            <button onclick="editAppointmentFromView()" class="btn-primary">Edit</button>
        </div>
    </div>
</div>

<!-- New Appointment Modal -->
<div id="appointmentModal" class="modal-overlay">
    <div class="modal" style="width: 600px;">
        <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Schedule New Appointment</h2>
        <form id="appointmentForm">
            <input type="hidden" name="appointment_id" id="appointmentId">
            <input type="hidden" name="patient_id" id="appointmentPatientId">
            <input type="hidden" name="appointment_time" id="selectedTimeInput">
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>First Name *</label>
                    <input type="text" name="first_name" required class="form-control" placeholder="Enter first name">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" class="form-control" placeholder="Enter middle name">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" required class="form-control" placeholder="Enter last name">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="0912-345-6789">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="patient@email.com">
                </div>
            </div>
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <div style="font-size: 13px; font-weight: 600; color: #166534; margin-bottom: 12px;">Select Appointment Slot</div>
                <div class="form-row" style="margin-bottom: 12px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Treatment * <span style="font-size: 11px; color: #6b7280; font-weight: 400;">(select first to set duration)</span></label>
                        <select name="treatment" id="adminServiceSelect" class="form-control" required>
                            <option value="">-- Select Treatment --</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Dentist <span style="font-size: 11px; color: #6b7280; font-weight: 400;">(optional)</span></label>
                        <select name="dentist_id" id="adminDentistSelect" class="form-control">
                            <option value="">Any Available Dentist</option>
                        </select>
                    </div>
                </div>
                <div class="form-row" style="margin-bottom: 12px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Date *</label>
                        <input type="date" name="appointment_date" id="adminApptDate" required class="form-control">
                    </div>
                </div>
                <div id="adminSlotGrid" style="margin-top: 8px;">
                    <div style="text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; background: #f8fafc; border-radius: 8px; border: 1px dashed #d1d5db;">
                        Select a treatment, date, and optionally a dentist to see available time slots
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" rows="2" class="form-control" placeholder="Additional notes or instructions..."></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeAppointmentModal()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-primary" id="adminScheduleBtn" disabled>Schedule Appointment</button>
            </div>
        </form>
    </div>
</div>

<style>
.filter-tabs {
    display: flex;
    gap: 8px;
}

.filter-tabs span {
    padding: 8px 16px;
    background: #f3f4f6;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.filter-tabs span:hover {
    background: #e5e7eb;
}

.filter-tabs span.active {
    background: #3f75ea;
    color: white;
}

.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 99999;
    justify-content: center;
    align-items: center;
}

.modal {
    background: white;
    border-radius: 12px;
    padding: 24px;
    max-height: 85vh;
    max-width: 95vw;
    overflow-y: auto;
    position: relative;
    z-index: 100000;
    margin: 20px;
}

.form-row {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #374151;
    font-size: 0.875rem;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.875rem;
    transition: border-color 0.2s, box-shadow 0.2s;
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.btn-cancel {
    padding: 10px 20px;
    background: #f3f4f6;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    color: #374151;
    cursor: pointer;
    font-size: 0.875rem;
}

.btn-cancel:hover {
    background: #e5e7eb;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

/* Appointment Kebab Menu Styles - Portal Based */
.appt-kebab-menu {
    position: relative;
    display: inline-block;
}

.appt-kebab-btn {
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

.appt-kebab-btn:hover {
    background-color: #f3f4f6;
    color: #374151;
}

.appt-kebab-btn.active {
    background-color: #e5e7eb;
    color: #111827;
}

.appt-kebab-dropdown-portal {
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

.appt-kebab-dropdown-portal.show {
    display: block;
    animation: apptKebabFadeIn 0.15s ease;
}

@keyframes apptKebabFadeIn {
    from { opacity: 0; transform: scale(0.95) translateY(-4px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.appt-kebab-dropdown-portal a {
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

.appt-kebab-dropdown-portal a:hover {
    background-color: #f9fafb;
    color: #111827;
}

.appt-kebab-dropdown-portal a svg {
    flex-shrink: 0;
}

.appt-kebab-dropdown-portal a:first-child {
    border-radius: 8px 8px 0 0;
}

.appt-kebab-dropdown-portal a:last-child {
    border-radius: 0 0 8px 8px;
}

.appt-kebab-dropdown-portal a.danger {
    color: #dc2626;
}

.appt-kebab-dropdown-portal a.danger:hover {
    background-color: #fef2f2;
}

.appt-kebab-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 99998;
}

.appt-kebab-backdrop.show {
    display: block;
}

/* Status Badge Colors */
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.status-scheduled {
    background: #dbeafe;
    color: #1d4ed8;
}

.status-badge.status-confirmed {
    background: #dbeafe;
    color: #1d4ed8;
}

.status-badge.status-in-queue, .status-badge.status-in_queue {
    background: #fef3c7;
    color: #92400e;
}

.status-badge.status-pending {
    background: #fed7aa;
    color: #c2410c;
}

.status-badge.status-pending_payment {
    background: #fed7aa;
    color: #c2410c;
}

.status-badge.status-completed {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.status-cancelled, .status-badge.status-cancel {
    background: #fee2e2;
    color: #991b1b;
}

.status-badge.status-arrived {
    background: #ccfbf1;
    color: #0f766e;
}

.status-badge.status-no-show {
    background: #f3f4f6;
    color: #6b7280;
}

.status-badge.status-rescheduled {
    background: #e0e7ff;
    color: #4338ca;
}
</style>

<script>
    const appointments = <?php echo json_encode($allAppointments); ?>;
    const unavailableDates = <?php echo json_encode($unavailableDates); ?>;
    
    let adminCalYear, adminCalMonth;

    function initAdminCalendar() {
        const today = new Date();
        adminCalYear = today.getFullYear();
        adminCalMonth = today.getMonth();
        renderAdminCalendar();
    }

    function changeAdminCalMonth(dir) {
        adminCalMonth += dir;
        if (adminCalMonth < 0) { adminCalMonth = 11; adminCalYear--; }
        if (adminCalMonth > 11) { adminCalMonth = 0; adminCalYear++; }
        renderAdminCalendar();
    }

    function renderAdminCalendar() {
        document.getElementById('adminCalMonthYear').textContent = new Date(adminCalYear, adminCalMonth).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        
        const grid = document.getElementById('adminCalGrid');
        grid.innerHTML = '';
        
        const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        days.forEach(d => {
            const h = document.createElement('div');
            h.className = 'admin-cal-day-header';
            h.textContent = d;
            grid.appendChild(h);
        });
        
        const firstDay = new Date(adminCalYear, adminCalMonth, 1).getDay();
        const totalDays = new Date(adminCalYear, adminCalMonth + 1, 0).getDate();
        const prevDays = new Date(adminCalYear, adminCalMonth, 0).getDate();
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        for (let i = firstDay - 1; i >= 0; i--) {
            const d = document.createElement('div');
            d.className = 'admin-cal-day empty';
            d.textContent = prevDays - i;
            grid.appendChild(d);
        }
        
        const apptByDate = {};
        appointments.forEach(appt => {
            const date = String(appt.appointment_date).split(' ')[0];
            if (!apptByDate[date]) apptByDate[date] = [];
            apptByDate[date].push(appt);
        });
        
        for (let i = 1; i <= totalDays; i++) {
            const d = document.createElement('div');
            const dateStr = `${adminCalYear}-${String(adminCalMonth + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
            const dateObj = new Date(adminCalYear, adminCalMonth, i);
            
            d.className = 'admin-cal-day';
            d.dataset.date = dateStr;
            
            if (dateObj.getTime() === today.getTime()) {
                d.classList.add('today');
            }
            
            const closedEntry = unavailableDates.find(e => e.date === dateStr);
            if (closedEntry && closedEntry.status === 'closed') {
                d.classList.add('closed');
                d.innerHTML = `<div class="day-num">${i}</div><div class="closed-label">Closed</div>`;
            } else {
                const appts = apptByDate[dateStr] || [];
                let html = `<div class="day-num">${i}</div>`;
                if (appts.length > 0) {
                    html += `<div class="appt-count">${appts.length} appointment${appts.length > 1 ? 's' : ''}</div>`;
                    appts.slice(0, 3).forEach(appt => {
                        const time = appt.appointment_time ? new Date('1970-01-01 ' + appt.appointment_time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : '';
                        const name = [appt.first_name, appt.middle_name, appt.last_name].filter(n => n).join(' ') || 'Unknown';
                        html += `<div class="appt-mini">${time} - ${name.trim()}</div>`;
                    });
                    if (appts.length > 3) {
                        html += `<div class="appt-mini">+${appts.length - 3} more</div>`;
                    }
                }
                d.innerHTML = html;
            }
            
            d.addEventListener('click', () => openAdminDayModal(dateStr, apptByDate[dateStr] || [], closedEntry));
            grid.appendChild(d);
        }
        
        const remaining = 42 - (firstDay + totalDays);
        for (let i = 1; i <= remaining; i++) {
            const d = document.createElement('div');
            d.className = 'admin-cal-day empty';
            d.textContent = i;
            grid.appendChild(d);
        }
    }

    function openAdminDayModal(dateStr, appts, closedEntry) {
        const formatted = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        document.getElementById('adminDayTitle').textContent = formatted;
        
        const content = document.getElementById('adminDayContent');
        
        if (closedEntry && closedEntry.status === 'closed') {
            content.innerHTML = `<p style="color: #dc2626; font-weight: 500;">Clinic is closed${closedEntry.note ? ' - ' + closedEntry.note : ''}</p>`;
        } else if (appts.length === 0) {
            content.innerHTML = '<p style="color: #6b7280;">No appointments for this day</p>';
        } else {
            let html = '';
            appts.forEach(appt => {
                const time = appt.appointment_time ? new Date('1970-01-01 ' + appt.appointment_time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : 'N/A';
                const name = [appt.first_name, appt.middle_name, appt.last_name].filter(n => n).join(' ') || 'Unknown';
                const status = appt.status_label || appt.status || 'Scheduled';
                html += `<div class="admin-day-modal-item">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 500;">${name}</div>
                            <div style="font-size: 14px; color: #6b7280;">${time} - ${appt.service_name || 'General'}</div>
                        </div>
                        <span class="status-badge" style="background: ${status === 'Completed' ? '#d1fae5' : status === 'Cancelled' ? '#fee2e2' : status.toLowerCase() === 'arrived' ? '#ccfbf1' : '#e0f2fe'}; color: ${status === 'Completed' ? '#065f46' : status === 'Cancelled' ? '#dc2626' : status.toLowerCase() === 'arrived' ? '#0f766e' : '#1d4ed8'};">${status}</span>
                    </div>
                </div>`;
            });
            content.innerHTML = html;
        }
        
        document.getElementById('adminDayModal').classList.add('active');
    }

    function closeAdminDayModal() {
        document.getElementById('adminDayModal').classList.remove('active');
    }

    document.addEventListener('click', function(e) {
        if (e.target === document.getElementById('adminDayModal')) {
            closeAdminDayModal();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Filter tabs click handler
        document.querySelectorAll('.filter-tabs span').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.filter-tabs span').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                filterAppointments();
            });
        });

        // Search input handler
        document.getElementById('appointmentSearch').addEventListener('input', filterAppointments);
        
        // View toggle
        document.querySelectorAll('.view-toggle-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.view-toggle-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const view = this.dataset.view;
                if (view === 'list') {
                    document.getElementById('listView').classList.remove('hidden');
                    document.getElementById('calendarView').classList.remove('active');
                } else {
                    document.getElementById('listView').classList.add('hidden');
                    document.getElementById('calendarView').classList.add('active');
                    initAdminCalendar();
                }
            });
        });
    });

    function filterAppointments() {
        const search = document.getElementById('appointmentSearch').value.toLowerCase();
        const activeFilter = document.querySelector('.filter-tabs span.active').dataset.filter;
        const today = new Date().toISOString().split('T')[0];
        const weekEnd = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
        const monthEnd = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

        document.querySelectorAll('.appointment-row').forEach(row => {
            const nameMatch = !search || row.dataset.name.includes(search);
            
            let dateMatch = true;
            if (activeFilter === 'today') {
                dateMatch = row.dataset.date === today;
            } else if (activeFilter === 'week') {
                dateMatch = row.dataset.date >= today && row.dataset.date <= weekEnd;
            } else if (activeFilter === 'month') {
                dateMatch = row.dataset.date >= today && row.dataset.date <= monthEnd;
            }
            
            row.style.display = (nameMatch && dateMatch) ? '' : 'none';
        });
    }

    var selectedAdminSlot = null;

    function loadAdminServices(callback) {
        fetch('../shared/get_services.php')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var select = document.getElementById('adminServiceSelect');
                select.innerHTML = '<option value="">-- Select Treatment --</option>';
                if (data.success && data.services) {
                    data.services.forEach(function(svc) {
                        var opt = document.createElement('option');
                        opt.value = svc.id;
                        opt.textContent = svc.name + ' (' + (svc.duration_minutes || 30) + ' min)';
                        opt.setAttribute('data-duration', svc.duration_minutes || 30);
                        select.appendChild(opt);
                    });
                }
                if (typeof callback === 'function') callback();
            });
    }

    function loadAdminDentists(callback) {
        fetch('../shared/get_dentists.php')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var select = document.getElementById('adminDentistSelect');
                select.innerHTML = '<option value="">Any Available Dentist</option>';
                if (data.success && data.dentists) {
                    data.dentists.forEach(function(d) {
                        var opt = document.createElement('option');
                        opt.value = d.id;
                        opt.textContent = d.first_name + ' ' + d.last_name;
                        select.appendChild(opt);
                    });
                }
                if (typeof callback === 'function') callback();
            });
    }

    function loadAdminSlots(callback) {
        var serviceId = document.getElementById('adminServiceSelect').value;
        var dentistId = document.getElementById('adminDentistSelect').value;
        var date = document.getElementById('adminApptDate').value;
        var grid = document.getElementById('adminSlotGrid');
        var btn = document.getElementById('adminScheduleBtn');

        if (!serviceId || !date) {
            grid.innerHTML = '<div style="text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; background: #f8fafc; border-radius: 8px; border: 1px dashed #d1d5db;">Select a treatment, date, and optionally a dentist to see available time slots</div>';
            btn.disabled = true;
            if (typeof callback === 'function') callback();
            return;
        }

        selectedAdminSlot = null;
        btn.disabled = true;
        grid.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading available slots...</div>';

        var url = '../user-patient/get_slots.php?date=' + date + '&service_id=' + serviceId;
        if (dentistId) url += '&dentist_id=' + dentistId;

        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                grid.innerHTML = '';
                if (!data.is_open) {
                    grid.innerHTML = '<div style="text-align: center; padding: 20px; color: #d97706; font-size: 13px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px;">' + (data.message || 'No slots available') + '</div>';
                    if (typeof callback === 'function') callback();
                    return;
                }

                var legend = document.createElement('div');
                legend.style.cssText = 'display: flex; gap: 16px; margin-bottom: 10px; font-size: 12px; color: #475569;';
                legend.innerHTML = '<span style="color:#15803d;"><span style="background:#dcfce7;border:2px solid #15803d;display:inline-block;width:14px;height:14px;border-radius:3px;margin-right:4px;vertical-align:middle;"></span> Available</span><span style="color:#991b1b;"><span style="background:#fee2e2;border:2px solid #991b1b;display:inline-block;width:14px;height:14px;border-radius:3px;margin-right:4px;vertical-align:middle;"></span> Booked</span>';
                grid.appendChild(legend);

                var allSlots = (data.available_slots || []).concat(data.booked_slots || []).sort();
                if (allSlots.length === 0) {
                    grid.innerHTML += '<div style="text-align: center; padding: 20px; color: #d97706; font-size: 13px;">No time slots available for this selection</div>';
                    if (typeof callback === 'function') callback();
                    return;
                }

                var slotGrid = document.createElement('div');
                slotGrid.style.cssText = 'display: flex; flex-wrap: wrap; gap: 6px;';

                var bookedArr = data.booked_slots || [];

                allSlots.forEach(function(slot) {
                    var s = document.createElement('div');
                    var isBooked = bookedArr.indexOf(slot) !== -1;
                    s.style.cssText = 'padding: 8px 14px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: ' + (isBooked ? 'not-allowed' : 'pointer') + '; border: 2px solid ' + (isBooked ? '#fecaca' : '#bbf7d0') + '; background: ' + (isBooked ? '#fef2f2' : '#f0fdf4') + '; color: ' + (isBooked ? '#991b1b' : '#166534') + '; transition: all 0.15s;';
                    s.textContent = formatAdminTime(slot);

                    if (!isBooked) {
                        s.dataset.time = slot;
                        s.onclick = function() {
                            document.querySelectorAll('#adminSlotGrid .slot-item').forEach(function(el) {
                                el.style.borderColor = '#bbf7d0';
                                el.style.background = '#f0fdf4';
                            });
                            this.style.borderColor = '#15803d';
                            this.style.background = '#bbf7d0';
                            selectedAdminSlot = slot;
                            document.getElementById('selectedTimeInput').value = slot;
                            document.getElementById('adminScheduleBtn').disabled = false;
                        };
                        s.classList.add('slot-item');
                    }
                    slotGrid.appendChild(s);
                });

                grid.appendChild(slotGrid);

                if (data.business_hours) {
                    var info = document.createElement('div');
                    info.style.cssText = 'margin-top: 10px; font-size: 11px; color: #94a3b8;';
                    info.textContent = 'Business hours: ' + data.business_hours.open + ' - ' + data.business_hours.close + ' | Slot duration: ' + data.service_duration + ' min';
                    grid.appendChild(info);
                }

                if (typeof callback === 'function') callback();
            })
            .catch(function(err) {
                grid.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc2626; font-size: 13px;">Error loading slots: ' + err.message + '</div>';
                if (typeof callback === 'function') callback();
            });
    }

    function formatAdminTime(t) {
        var parts = t.split(':');
        var h = parseInt(parts[0]);
        var m = parts[1];
        var ampm = h >= 12 ? 'PM' : 'AM';
        if (h > 12) h -= 12;
        if (h === 0) h = 12;
        return h + ':' + m + ' ' + ampm;
    }

    function openAppointmentModal() {
        document.getElementById('appointmentModal').style.display = 'flex';
    }

    function closeAppointmentModal() {
        document.getElementById('appointmentId').value = '';
        document.getElementById('appointmentPatientId').value = '';
        document.querySelector('#appointmentModal h2').textContent = 'Schedule New Appointment';
        document.querySelector('#appointmentForm button[type="submit"]').textContent = 'Schedule Appointment';
        document.getElementById('appointmentModal').style.display = 'none';
        document.getElementById('appointmentForm').reset();
        document.getElementById('adminSlotGrid').innerHTML = '<div style="text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; background: #f8fafc; border-radius: 8px; border: 1px dashed #d1d5db;">Select a treatment, date, and optionally a dentist to see available time slots</div>';
        selectedAdminSlot = null;
    }

    function prepareNewAppointmentModal() {
        const form = document.getElementById('appointmentForm');
        if (form) form.reset();
        document.getElementById('appointmentId').value = '';
        document.getElementById('appointmentPatientId').value = '';
        document.getElementById('selectedTimeInput').value = '';
        document.getElementById('adminScheduleBtn').disabled = true;
        selectedAdminSlot = null;
        document.querySelector('#appointmentModal h2').textContent = 'Schedule New Appointment';
        document.querySelector('#appointmentForm button[type="submit"]').textContent = 'Schedule Appointment';
        document.getElementById('adminSlotGrid').innerHTML = '<div style="text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; background: #f8fafc; border-radius: 8px; border: 1px dashed #d1d5db;">Select a treatment, date, and optionally a dentist to see available time slots</div>';
        loadAdminServices();
        loadAdminDentists();
        openAppointmentModal();
    }

    function closeViewAppointmentModal() {
        document.getElementById('viewAppointmentModal').style.display = 'none';
    }

    document.getElementById('appointmentModal').addEventListener('click', function(e) {
        if (e.target === this) closeAppointmentModal();
    });

    document.getElementById('viewAppointmentModal').addEventListener('click', function(e) {
        if (e.target === this) closeViewAppointmentModal();
    });

    // Event listeners for slot loading
    document.getElementById('adminServiceSelect').addEventListener('change', loadAdminSlots);
    document.getElementById('adminDentistSelect').addEventListener('change', loadAdminSlots);
    document.getElementById('adminApptDate').addEventListener('change', loadAdminSlots);

    document.getElementById('appointmentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);

        // Add service_id from the treatment dropdown
        var serviceId = document.getElementById('adminServiceSelect').value;
        if (serviceId) formData.set('service_id', serviceId);

        // Set the appointment_time from selected slot
        if (selectedAdminSlot) {
            formData.set('appointment_time', selectedAdminSlot);
        }

        fetch('../shared/process_appointment.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                closeAppointmentModal();
                location.reload();
            } else {
                alert(data.message || 'Error scheduling appointment');
            }
        })
        .catch(function(error) {
            alert('Error scheduling appointment: ' + error.message);
        });
    });

    function viewAppointment(id) {
        const appt = appointments.find(a => a.id == id);
        if (!appt) return;
        
        const firstName = appt.first_name || '';
        const middleName = appt.middle_name || '';
        const lastName = appt.last_name || '';
        const displayName = [firstName, middleName, lastName].filter(n => n).join(' ') || 'Unknown';
        
        const timeStr = appt.appointment_time ? new Date('1970-01-01 ' + appt.appointment_time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : 'N/A';
        
        document.getElementById('viewAppointmentContent').innerHTML = `
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div><span style="color: #6b7280;">Patient:</span> <span style="font-weight: 600; margin-left: 8px;">${displayName}</span></div>
                <div><span style="color: #6b7280;">Phone:</span> <span style="font-weight: 500; margin-left: 8px;">${appt.phone || 'N/A'}</span></div>
                <div><span style="color: #6b7280;">Date:</span> <span style="font-weight: 500; margin-left: 8px;">${new Date(appt.appointment_date).toLocaleDateString()}</span></div>
                <div><span style="color: #6b7280;">Time:</span> <span style="font-weight: 500; margin-left: 8px;">${timeStr}</span></div>
                <div><span style="color: #6b7280;">Treatment:</span> <span style="font-weight: 500; margin-left: 8px;">${appt.service_name || appt.treatment || 'General'}</span></div>
                <div><span style="color: #6b7280;">Dentist:</span> <span style="font-weight: 500; margin-left: 8px;">${appt.dentist_name || 'Unassigned'}</span></div>
                <div><span style="color: #6b7280;">Status:</span> <span class="status-badge" style="margin-left: 8px; background: ${appt.status === 'Completed' ? '#dcfce7' : appt.status === 'Cancelled' ? '#fee2e2' : (appt.status || '').toLowerCase() === 'arrived' ? '#ccfbf1' : '#e0f2fe'}; color: ${appt.status === 'Completed' ? '#15803d' : appt.status === 'Cancelled' ? '#dc2626' : (appt.status || '').toLowerCase() === 'arrived' ? '#0f766e' : '#0369a1'};">${appt.status ? appt.status.charAt(0).toUpperCase() + appt.status.slice(1) : 'Pending'}</span></div>
                ${appt.notes ? `<div><span style="color: #6b7280;">Notes:</span><p style="background: #f9fafb; padding: 12px; border-radius: 8px; margin: 8px 0 0;">${appt.notes}</p></div>` : ''}
            </div>
        `;
        
        document.getElementById('viewAppointmentModal').style.display = 'flex';
    }

    function editAppointmentFromView() {
        closeViewAppointmentModal();
        // Could open edit modal here
    }

    // Cancel Reason Modal HTML
    if (!document.getElementById('cancelReasonModal')) {
        const modal = document.createElement('div');
        modal.id = 'cancelReasonModal';
        modal.className = 'modal-overlay';
        modal.style.display = 'none';
        modal.innerHTML = `
            <div class="modal" style="width: 400px;">
                <h2 style="margin: 0 0 16px; font-size: 1.25rem; font-weight: 600;">Cancel Appointment</h2>
                <p style="color: #6b7280; margin-bottom: 16px;">Please provide a reason for cancelling this appointment:</p>
                <textarea id="cancelReasonText" class="form-control" rows="4" placeholder="Enter reason for cancellation..." style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; resize: vertical;"></textarea>
                <div style="display: flex; gap: 12px; margin-top: 20px; justify-content: flex-end;">
                    <button onclick="closeCancelReasonModal()" class="btn-cancel" style="padding: 10px 20px; background: #f3f4f6; border: none; border-radius: 6px; cursor: pointer;">Cancel</button>
                    <button onclick="confirmCancelAppointment()" class="btn-primary" style="padding: 10px 20px; background: #dc2626; color: white; border: none; border-radius: 6px; cursor: pointer;">Confirm Cancel</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    let cancelAppointmentId = null;
    
    function closeCancelReasonModal() {
        document.getElementById('cancelReasonModal').style.display = 'none';
        cancelAppointmentId = null;
    }
    
    function openCancelReasonModal(id) {
        cancelAppointmentId = id;
        document.getElementById('cancelReasonText').value = '';
        document.getElementById('cancelReasonModal').style.display = 'flex';
    }
    
    window.confirmCancelAppointment = function() {
        if (!cancelAppointmentId) return;
        
        const cancel_reason = document.getElementById('cancelReasonText').value;
        
        fetch('cancel_appointment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: cancelAppointmentId, cancel_reason: cancel_reason })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Error cancelling appointment');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error cancelling appointment');
        });
        
        closeCancelReasonModal();
    }

    function cancelAppointment(id) {
        openCancelReasonModal(id);
    }

    // Appointment Kebab Menu Functions - Portal Based
    let apptKebabDropdown = null;
    let apptKebabBackdrop = null;
    let apptActiveButton = null;

    function createApptKebabDropdown() {
        apptKebabDropdown = document.createElement('div');
        apptKebabDropdown.className = 'appt-kebab-dropdown-portal';
        apptKebabDropdown.id = 'apptKebabDropdownPortal';
        document.body.appendChild(apptKebabDropdown);

        apptKebabBackdrop = document.createElement('div');
        apptKebabBackdrop.className = 'appt-kebab-backdrop';
        apptKebabBackdrop.id = 'apptKebabBackdrop';
        document.body.appendChild(apptKebabBackdrop);

        apptKebabBackdrop.addEventListener('click', closeApptKebabDropdown);
    }

    function getApptMenuItems(apptId) {
        // Get status from the button's data attributes
        const button = document.querySelector(`.appt-kebab-btn[data-appt-id="${apptId}"]`);
        const status = button ? button.getAttribute('data-status') : '';
        const checkedIn = button ? button.getAttribute('data-checked-in') : '';
        const isCancelled = (status && status.toLowerCase().includes('cancel'));
        const isArrived = checkedIn && checkedIn.trim() !== '';

        let menuItems = `
            <a href="javascript:void(0)" data-action="view" data-id="${apptId}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                View
            </a>
            <a href="javascript:void(0)" data-action="edit" data-id="${apptId}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                Edit
            </a>
        `;

        if (!isArrived) {
            menuItems += `
                <a href="javascript:void(0)" data-action="mark_arrived" data-id="${apptId}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    Mark as Arrived
                </a>
            `;
        }

        if (isArrived) {
            menuItems += `
                <a href="javascript:void(0)" data-action="forward" data-id="${apptId}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 12h-16"/>
                        <path d="M12 5l7 7-7 7"/>
                        <path d="M3 5v14"/>
                    </svg>
                    Forward to Admission
                </a>
            `;
        }

        // Show Cancel button if not cancelled, Delete button if cancelled
        if (isCancelled) {
            menuItems += `
                <a href="javascript:void(0)" onclick="deleteAppointment(${apptId})" class="danger">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                    Delete Appointment
                </a>
            `;
        } else {
            menuItems += `
                <a href="javascript:void(0)" onclick="openCancelReasonModal(${apptId})" class="danger">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                    Cancel Appointment
                </a>
            `;
        }

        return menuItems;
    }

    function positionApptKebabDropdown(button) {
        if (!apptKebabDropdown || !button) return;

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
        
        if (top + 180 > viewportHeight - padding) {
            top = rect.top - 180 - 8;
        }
        
        if (top < padding) {
            top = padding;
        }

        apptKebabDropdown.style.left = left + 'px';
        apptKebabDropdown.style.top = top + 'px';
    }

    function openApptKebabDropdown(button) {
        if (!apptKebabDropdown) {
            createApptKebabDropdown();
        }

        const apptId = button.dataset.apptId;

        apptKebabDropdown.innerHTML = getApptMenuItems(apptId);
        positionApptKebabDropdown(button);

        apptKebabDropdown.classList.add('show');
        apptKebabBackdrop.classList.add('show');
        apptActiveButton = button;
        button.classList.add('active');

        apptKebabDropdown.addEventListener('click', handleApptKebabClick);
    }

    function closeApptKebabDropdown() {
        if (apptKebabDropdown) {
            apptKebabDropdown.classList.remove('show');
        }
        if (apptKebabBackdrop) {
            apptKebabBackdrop.classList.remove('show');
        }
        if (apptActiveButton) {
            apptActiveButton.classList.remove('active');
            apptActiveButton = null;
        }
    }

    function handleApptKebabClick(e) {
        const link = e.target.closest('a');
        if (!link) return;

        e.preventDefault();
        const action = link.dataset.action;
        const id = link.dataset.id;

        closeApptKebabDropdown();

        switch (action) {
            case 'view':
                viewAppointment(id);
                break;
            case 'edit':
                editAppointment(id);
                break;
            case 'mark_arrived':
                markArrived(id);
                break;
            case 'forward':
                forwardToAdmission(id);
                break;
            case 'delete':
                deleteAppointment(id);
                break;
        }
    }

    // Click handler for kebab buttons
    document.addEventListener('click', function(e) {
        const button = e.target.closest('.appt-kebab-btn');
        if (button) {
            e.preventDefault();
            e.stopPropagation();
            
            if (apptActiveButton === button) {
                closeApptKebabDropdown();
            } else {
                closeApptKebabDropdown();
                openApptKebabDropdown(button);
            }
        } else if (!e.target.closest('.appt-kebab-dropdown-portal')) {
            closeApptKebabDropdown();
        }
    });

    function editAppointment(id) {
        const appt = appointments.find(a => a.id == id);
        if (!appt) return;

        const form = document.getElementById('appointmentForm');
        if (!form) return;

        document.getElementById('appointmentId').value = appt.id || '';
        document.getElementById('appointmentPatientId').value = appt.patient_id || '';
        form.elements.first_name.value = appt.first_name || '';
        form.elements.middle_name.value = appt.middle_name || '';
        form.elements.last_name.value = appt.last_name || '';
        form.elements.phone.value = appt.phone || '';
        if (form.elements.email) {
            form.elements.email.value = appt.email || '';
        }
        form.elements.notes.value = appt.notes || '';

        document.querySelector('#appointmentModal h2').textContent = 'Edit Appointment';
        document.querySelector('#appointmentForm button[type="submit"]').textContent = 'Save Changes';

        // Load services and dentists, then set values and load slots
        loadAdminServices(function() {
            var svcSelect = document.getElementById('adminServiceSelect');
            if (appt.service_id) {
                svcSelect.value = appt.service_id;
            } else {
                for (var i = 0; i < svcSelect.options.length; i++) {
                    if (svcSelect.options[i].text.indexOf(appt.service_name || appt.treatment) !== -1) {
                        svcSelect.value = svcSelect.options[i].value;
                        break;
                    }
                }
            }

            loadAdminDentists(function() {
                var dentSelect = document.getElementById('adminDentistSelect');
                if (appt.dentist_id) {
                    dentSelect.value = appt.dentist_id;
                }

                var dateInput = document.getElementById('adminApptDate');
                dateInput.value = appt.appointment_date ? String(appt.appointment_date).split(' ')[0] : '';

                openAppointmentModal();

                loadAdminSlots(function() {
                    var currentTime = appt.appointment_time ? String(appt.appointment_time).slice(0, 5) : '';
                    if (currentTime) {
                        var slotItems = document.querySelectorAll('#adminSlotGrid .slot-item');
                        for (var i = 0; i < slotItems.length; i++) {
                            if (slotItems[i].dataset.time === currentTime) {
                                slotItems[i].click();
                                break;
                            }
                        }
                    }
                });
            });
        });
    }

    function markArrived(id) {
        if (confirm('Mark this patient as arrived?\n\nThey will be automatically added to today\'s queue.')) {
            fetch('../shared/mark_arrived.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ appt_id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Patient marked as arrived');
                    location.reload();
                } else {
                    alert(data.message || 'Error marking as arrived');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error marking as arrived');
            });
        }
    }

    function forwardToAdmission(id) {
        const appt = appointments.find(a => a.id == id);
        if (!appt) return;
        
        if (confirm('Forward this appointment to admission?')) {
            // Redirect to new admission form with appointment data
            window.location.href = 'new_admission.php?appointment_id=' + id;
        }
    }

    function deleteAppointment(id) {
        if (confirm('Are you sure you want to archive this appointment?\n\nIt will be moved to the Archive page where you can restore or permanently delete it.')) {
            fetch('../api/delete_appointment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, action: 'archive' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Error deleting appointment');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting appointment');
            });
        }
    }

// Appointment status auto-polling
var apptPollHashAdmin = '';

function startAppointmentPolling() {
    setInterval(function() {
        fetch('../shared/appointments_poll.php')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) return;
                if (data.hash !== apptPollHashAdmin) {
                    apptPollHashAdmin = data.hash;
                    refreshAppointmentStatuses(data.appointments);
                }
            })
            .catch(function(e) { console.error('Appointment poll error:', e); });
    }, 10000);
}

function refreshAppointmentStatuses(appts) {
    var statusMap = {};
    for (var i = 0; i < appts.length; i++) {
        statusMap[appts[i].id] = appts[i].status;
    }
    var rows = document.querySelectorAll('.appointment-row');
    for (var i = 0; i < rows.length; i++) {
        var row = rows[i];
        var kebab = row.querySelector('.appt-kebab-btn');
        if (!kebab) continue;
        var apptId = parseInt(kebab.dataset.apptId);
        if (!apptId || !statusMap[apptId]) continue;
        var newStatus = statusMap[apptId];
        var currentStatus = row.dataset.status;
        if (newStatus.toLowerCase() === currentStatus.toLowerCase()) continue;
        row.dataset.status = newStatus.toLowerCase();
        kebab.dataset.status = newStatus;
        var badge = row.querySelector('.status-badge');
        if (badge) {
            badge.textContent = newStatus;
            badge.className = 'status-badge ' + getApptBadgeClass(newStatus);
        }
    }
}

function getApptBadgeClass(status) {
    var s = status.toLowerCase();
    if (s.indexOf('arrived') !== -1) return 'status-arrived';
    if (s.indexOf('queue') !== -1) return 'status-in-queue';
    if (s.indexOf('pending') !== -1) return 'status-pending';
    if (s.indexOf('complete') !== -1) return 'status-completed';
    if (s.indexOf('cancel') !== -1) return 'status-cancelled';
    return 'status-scheduled';
}

document.addEventListener('DOMContentLoaded', startAppointmentPolling);
</script>

<?php
require_once __DIR__ . '/../../includes/admin_layout_end.php';
?>
