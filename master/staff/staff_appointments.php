<?php
$pageTitle = 'Appointments';

// Pagination settings
$itemsPerPage = 50;
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;

try {
    require_once '../../config/database.php';
    require_once __DIR__ . '/../../includes/appointment_approval_helper.php';
    
    // Auto-cancel no-show trigger on page visit
    try {
        require_once __DIR__ . '/../../includes/auto_cancel_helper.php';
        autoCancelNoShows($pdo);
    } catch (Exception $e) {
        // Silently fail
    }

    // Auto-decline expired pending approval requests
    try {
        autoDeclineExpiredPendingAppointments($pdo);
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
    
    // Get total count
    $totalAppointments = (int)$pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
    $totalPages = max(1, ceil($totalAppointments / $itemsPerPage));
    
    // Ensure current page is valid
    if ($currentPage > $totalPages) $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    // Calculate showing range
    $showingStart = $totalAppointments > 0 ? $offset + 1 : 0;
    $showingEnd = min($offset + $itemsPerPage, $totalAppointments);
    
    // Get all appointments - join with status table and treatment plans
    // Exclude archived appointments (is_archived = 1)
    $sql = "SELECT a.*, 
        COALESCE(p.first_name, 'Unknown') as first_name, 
        COALESCE(p.middle_name, '') as middle_name, 
        COALESCE(p.last_name, '') as last_name,
        COALESCE(p.phone, '') as phone, 
        COALESCE(p.email, '') as email,
        COALESCE(p.street_address, '') as address,
        COALESCE(p.date_of_birth, '') as date_of_birth,
        COALESCE(p.gender, '') as gender,
        COALESCE(p.city, '') as city,
        COALESCE(p.province, '') as province,
        COALESCE(p.zip_code, '') as zip_code,
        s.name as service_name,
        a.status as status_label,
        tp.id as treatment_plan_id, tp.plan_name as treatment_plan_name,
        CONCAT(d.first_name, ' ', d.last_name) as dentist_name,
        q.status as queue_status, q.queue_number, q.queue_label
        FROM appointments a 
        LEFT JOIN patients p ON CAST(a.patient_id AS UNSIGNED) = p.id
        LEFT JOIN services s ON a.service_id = s.id
        LEFT JOIN treatment_plans tp ON a.id = tp.appointment_id
        LEFT JOIN users d ON a.dentist_id = d.id
        LEFT JOIN (
            SELECT q1.appointment_id, q1.status, q1.queue_number, q1.queue_label
            FROM queue q1
            INNER JOIN (
                SELECT appointment_id, MAX(id) AS max_id
                FROM queue
                WHERE appointment_id IS NOT NULL AND is_archived = 0
                GROUP BY appointment_id
            ) latest ON latest.max_id = q1.id
        ) q ON q.appointment_id = a.id
        WHERE (a.is_archived = 0 OR a.is_archived IS NULL)
        ORDER BY a.appointment_date DESC, a.id DESC";
    $allAppointments = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
    // Paginate
    $appointments = array_slice($allAppointments, $offset, $itemsPerPage);
} catch (Exception $e) {
    $appointments = [];
    $allAppointments = [];
    $totalAppointments = 0;
    $totalPages = 1;
    $showingStart = 0;
    $showingEnd = 0;
    $unavailableDates = [];
    error_log("Staff Appointments Error: " . $e->getMessage());
}

$todayCount = 0;
$completedCount = 0;
$pendingCount = 0;
$cancelledCount = 0;
try {
    // Simple direct queries for today stats
    $todayCount = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()")->fetchColumn();
    $completedCount = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Completed' AND DATE(appointment_date) = CURDATE()")->fetchColumn();
    $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status IN ('Scheduled','In Queue','Pending Approval') AND DATE(appointment_date) = CURDATE()")->fetchColumn();
    $cancelledCount = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Cancelled' AND DATE(appointment_date) = CURDATE()")->fetchColumn();
} catch (Exception $e) {
    $todayCount = 0;
    $completedCount = 0;
    $pendingCount = 0;
    $cancelledCount = 0;
}

// Group appointments into 3 panels: scheduled (left), in queue (right top), pending approval (right bottom)
$sectionLabels = ['scheduled' => 'Scheduled', 'inqueue' => 'In Queue', 'pendingapproval' => 'Pending Approval'];
$sections = ['scheduled' => [], 'inqueue' => [], 'pendingapproval' => []];

function staffApptDisplayStatus(array $appt): string {
    $status = strtolower($appt['status_label'] ?? $appt['status'] ?? 'scheduled');
    $queueStatus = strtolower($appt['queue_status'] ?? '');
    if (strpos($status, 'pending approval') !== false) return 'Pending Approval';
    if (strpos($status, 'cancel') !== false || $queueStatus === 'cancelled') return 'Cancelled';
    if (strpos($status, 'complete') !== false || $queueStatus === 'completed') return 'Completed';
    if ($queueStatus === 'in_procedure') return 'In Procedure';
    if (strpos($status, 'pending payment') !== false || $queueStatus === 'pending_payment') return 'Pending Payment';
    if (strpos($status, 'in queue') !== false || strpos($status, 'arrived') !== false || in_array($queueStatus, ['waiting', 'on_hold'], true)) return 'In Queue';
    return 'Scheduled';
}

function staffApptPanel(string $displayStatus): string {
    if (strpos($displayStatus, 'Pending Approval') !== false) return 'pendingapproval';
    if ($displayStatus === 'Scheduled') return 'scheduled';
    return 'inqueue';
}

$queuePanelOrder = ['In Procedure' => 0, 'In Queue' => 1, 'Pending Payment' => 2, 'Completed' => 3, 'Cancelled' => 4];

foreach ($allAppointments as $appt) {
    $displayStatus = staffApptDisplayStatus($appt);
    $appt['display_status'] = $displayStatus;
    $sections[staffApptPanel($displayStatus)][] = $appt;
}

// Order the In Queue panel to read like a live board: procedure -> queue -> payment -> done -> cancelled
usort($sections['inqueue'], function ($a, $b) use ($queuePanelOrder) {
    $pa = $queuePanelOrder[$a['display_status']] ?? 9;
    $pb = $queuePanelOrder[$b['display_status']] ?? 9;
    if ($pa !== $pb) return $pa <=> $pb;
    return strcmp((string)($b['appointment_date'] ?? ''), (string)($a['appointment_date'] ?? ''));
});

$inquiryData = null;
$showModal = false;
if (isset($_GET['inquiry_id']) && is_numeric($_GET['inquiry_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM inquiries WHERE id = ?");
        $stmt->execute([$_GET['inquiry_id']]);
        $inquiryData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($inquiryData) {
            $showModal = true;
        }
    } catch (Exception $e) {
        $inquiryData = null;
    }
}

require_once '../../includes/staff_layout_start.php';
?>
<div class="summary-cards" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 24px;">
    <div class="summary-card" style="width: 100%;">
        <div class="summary-icon blue" style="background: #e0f2fe; color: #0284c7;">📋</div>
        <div class="summary-info">
            <h3><?php echo $totalAppointments; ?></h3>
            <p>Total Appointments</p>
        </div>
    </div>
    <div class="summary-card" style="width: 100%;">
        <div class="summary-icon yellow">⏰</div>
        <div class="summary-info">
            <h3><?php echo $todayCount; ?></h3>
            <p>Today</p>
        </div>
    </div>
    <div class="summary-card" style="width: 100%;">
        <div class="summary-icon yellow" style="background: #fef3c7; color: #d97706;">⏳</div>
        <div class="summary-info">
            <h3><?php echo $pendingCount; ?></h3>
            <p>Pending</p>
        </div>
    </div>
    <div class="summary-card" style="width: 100%;">
        <div class="summary-icon green">✓</div>
        <div class="summary-info">
            <h3><?php echo $completedCount; ?></h3>
            <p>Completed</p>
        </div>
    </div>
    <div class="summary-card" style="width: 100%;">
        <div class="summary-icon red" style="background: #fee2e2; color: #dc2626;">⚠️</div>
        <div class="summary-info">
            <h3><?php echo $cancelledCount; ?></h3>
            <p>Cancelled</p>
        </div>
    </div>
</div>

<style>
/* Staff appointments dashboard layout:
   Left = Scheduled (full height), right = In Queue (top) + Pending Approval (bottom) */
.status-grid.staff-status-grid {
    grid-template-columns: 3fr 2fr;
    grid-template-rows: 1fr 1fr;
}

.status-grid.staff-status-grid .status-section[data-section="scheduled"] {
    grid-row: 1 / span 2;
}

.status-badge.status-in-procedure {
    background: #cffafe;
    color: #0e7490;
}

@media (max-width: 1000px) {
    .status-grid.staff-status-grid {
        grid-template-columns: 1fr;
        grid-template-rows: auto;
        height: auto;
    }
    .status-grid.staff-status-grid .status-section[data-section="scheduled"] {
        grid-row: auto;
    }
    .status-grid.staff-status-grid .status-section {
        min-height: 280px;
        max-height: 480px;
    }
}

@media (max-width: 1200px) {
    .summary-cards {
        grid-template-columns: repeat(3, 1fr) !important;
    }
}

@media (max-width: 768px) {
    .summary-cards {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}

@media (max-width: 480px) {
    .summary-cards {
        grid-template-columns: 1fr !important;
    }
}

.view-toggle { display: flex; gap: 8px; margin-left: auto; }
.view-toggle-btn { padding: 8px 16px; border: 1px solid #d1d5db; background: white; cursor: pointer; font-size: 14px; font-weight: 500; color: #6b7280; border-radius: 6px; transition: all 0.2s; }
.view-toggle-btn:hover { background: #f3f4f6; }
.view-toggle-btn.active { background: #005daa; color: white; border-color: #005daa; }

.calendar-view { display: none; }
.calendar-view.active { display: block; }
.list-view { display: block; }
.list-view.hidden { display: none; }

.staff-cal { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.staff-cal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.staff-cal-title { font-size: 20px; font-weight: 700; color: #1a1a2e; }
.staff-cal-nav { display: flex; gap: 8px; }
.staff-cal-nav button { background: #f3f4f6; border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: all 0.2s; }
.staff-cal-nav button:hover { background: #e5e7eb; }
.staff-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
.staff-cal-day-header { text-align: center; font-size: 12px; font-weight: 600; color: #6b7280; padding: 8px 0; text-transform: uppercase; }
.staff-cal-day { min-height: 100px; padding: 8px; border: 1px solid #e5e7eb; border-radius: 8px; background: white; cursor: pointer; transition: all 0.2s; }
.staff-cal-day:hover { border-color: #005daa; background: #f0f7ff; }
.staff-cal-day.today { background: #dcf0ff; border-color: #005daa; }
.staff-cal-day.empty { background: #f9fafb; cursor: default; border-color: #f3f4f6; }
.staff-cal-day.empty:hover { background: #f9fafb; border-color: #f3f4f6; }
.staff-cal-day.closed { background: #fef2f2; border-color: #fecaca; }
.staff-cal-day .day-num { font-weight: 500; font-size: 14px; color: #374151; margin-bottom: 4px; }
.staff-cal-day .appt-count { font-size: 11px; color: #005daa; background: #e0f2fe; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-bottom: 4px; }
.staff-cal-day .appt-mini { font-size: 10px; color: #6b7280; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.staff-cal-day .closed-label { font-size: 11px; color: #dc2626; font-weight: 500; }

.staff-cal-day-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; justify-content: center; align-items: center; }
.staff-cal-day-modal.active { display: flex; }
.staff-cal-day-modal-content { background: white; border-radius: 12px; padding: 24px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; }
.staff-cal-day-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.staff-cal-day-modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280; }
.staff-cal-day-modal-item { padding: 12px; border-bottom: 1px solid #f3f4f6; }
.staff-cal-day-modal-item:last-child { border-bottom: none; }
</style>


<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
    <button class="view-toggle-btn active" data-view="list">📋 List View</button>
    <button class="view-toggle-btn" data-view="calendar">📅 Calendar View</button>
</div>
<div class="list-view" id="listView">
    
    <div class="search-filters">
        
        <div class="filter-tabs">
            <span class="active" data-filter="all">All</span>
            <span data-filter="today">Today</span>
            <span data-filter="week">This Week</span>
            <span data-filter="month">This Month</span>
        </div>
        <input type="text" class="search-input" id="searchAppointment" placeholder="Search appointments...">
        <div class="section-title">
            <button class="btn-primary" onclick="closeApptKebabDropdown(); prepareNewAppointmentModal()">+ New Appointment</button>
        </div>
    </div>

    <?php if ($inquiryData): ?>
    <div style="background: #dbeafe; border: 1px solid #3b82f6; border-radius: 8px; padding: 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" viewBox="0 0" fill="none" stroke="currentColor" stroke-width="2" stroke 24 24-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
        <span style="color: #1e40af; font-size: 0.9rem;">
            <strong>Forwarded from Inquiry:</strong> <?php echo htmlspecialchars(trim(($inquiryData['first_name'] ?? '') . ' ' . ($inquiryData['middle_name'] ?? '') . ' ' . ($inquiryData['last_name'] ?? ''))); ?> (<?php echo htmlspecialchars($inquiryData['source'] ?? ''); ?>)
        </span>
        <a href="staff_inquiries.php" style="margin-left: auto; color: #2563eb; font-size: 0.875rem; text-decoration: none;">View Original Inquiry</a>
    </div>
    <?php endif; ?>

    <?php
    $sectionIcons = ['scheduled' => '📅', 'inqueue' => '⏳', 'pendingapproval' => '🕓'];
    $sectionClasses = ['scheduled' => 'scheduled', 'inqueue' => 'inqueue', 'pendingapproval' => 'pendingapproval'];
    $sectionIds = ['scheduled' => 'countScheduled', 'inqueue' => 'countInqueue', 'pendingapproval' => 'countPendingapproval'];
    ?>
    <div class="status-grid staff-status-grid">
        <?php foreach (['scheduled', 'inqueue', 'pendingapproval'] as $sec): ?>
        <div class="status-section" data-section="<?php echo $sec; ?>">
            <div class="status-section-header <?php echo $sectionClasses[$sec]; ?>">
                <span class="status-section-title"><?php echo $sectionIcons[$sec]; ?> <?php echo $sectionLabels[$sec]; ?></span>
                <span class="status-section-count" id="<?php echo $sectionIds[$sec]; ?>"><?php echo count($sections[$sec]); ?></span>
            </div>
            <div class="status-section-body">
                <?php if (empty($sections[$sec])): ?>
                    <div class="mini-card-empty">No appointments</div>
                <?php else: ?>
                    <?php foreach ($sections[$sec] as $appt): 
                        $fn = htmlspecialchars($appt['first_name'] ?? '');
                        $mn = htmlspecialchars($appt['middle_name'] ?? '');
                        $ln = htmlspecialchars($appt['last_name'] ?? '');
                        $name = trim("$fn $mn $ln") ?: 'Unknown';
                        $time = !empty($appt['appointment_time']) ? date('h:i A', strtotime($appt['appointment_time'])) : '';
                        $date = date('Y-m-d', strtotime($appt['appointment_date']));
                        $treatment = htmlspecialchars($appt['service_name'] ?? 'General');
                        $dentist = htmlspecialchars($appt['dentist_name'] ?? 'Unassigned');
                        $status = $appt['display_status'] ?? ($appt['status_label'] ?? $appt['status'] ?? 'Scheduled');
                        $isLiveQueue = ($status === 'In Procedure');
                        $badgeClass = 'status-scheduled';
                        if (stripos($status, 'pending approval') !== false) $badgeClass = 'status-pending-approval';
                        elseif (stripos($status, 'in procedure') !== false) $badgeClass = 'status-in-procedure';
                        elseif (stripos($status, 'arrived') !== false) $badgeClass = 'status-arrived';
                        elseif (stripos($status, 'queue') !== false) $badgeClass = 'status-in-queue';
                        elseif (stripos($status, 'pending') !== false) $badgeClass = 'status-pending-payment';
                        elseif (stripos($status, 'complete') !== false) $badgeClass = 'status-completed';
                        elseif (stripos($status, 'cancel') !== false) $badgeClass = 'status-cancelled';
                    ?>
                    <div class="appointment-mini-card" 
                         data-name="<?php echo strtolower($name); ?>"
                         data-date="<?php echo $date; ?>"
                         data-status="<?php echo strtolower($status); ?>"
                         <?php if ($isLiveQueue): ?>data-live-status="1"<?php endif; ?>>
                        <div class="mini-card-top">
                            <span class="mini-card-name" title="<?php echo $name; ?>"><?php echo $name; ?></span>
                            <span class="mini-card-time"><?php echo $time; ?></span>
                            <div class="appt-kebab-menu" style="flex-shrink:0;">
                                <button class="appt-kebab-btn"
                                        data-appt-id="<?php echo $appt['id']; ?>"
                                        data-status="<?php echo htmlspecialchars($appt['status_label'] ?? $appt['status'] ?? 'Scheduled'); ?>"
                                        data-checked-in="<?php echo $appt['check_in_time'] ?? ''; ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                        <circle cx="12" cy="6" r="2"/>
                                        <circle cx="12" cy="12" r="2"/>
                                        <circle cx="12" cy="18" r="2"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="mini-card-bottom">
                            <span class="mini-card-treatment"><?php echo $treatment; ?> &middot; <?php echo $dentist; ?></span>
                            <span class="status-badge badge-small <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                        </div>
                        <?php if ($sec === 'pendingapproval'): ?>
                        <div class="appt-approval-actions">
                            <button type="button" class="btn-approve" onclick="approveAppointment(<?php echo (int)$appt['id']; ?>)">✓ Approve</button>
                            <button type="button" class="btn-decline" onclick="declineAppointment(<?php echo (int)$appt['id']; ?>)">✕ Decline</button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="calendar-view" id="calendarView">
    <div class="staff-cal">
        <div class="staff-cal-header">
            <h3 class="staff-cal-title" id="staffCalMonthYear"></h3>
            <div class="staff-cal-nav">
                <button onclick="changeStaffCalMonth(-1)">&#9664;</button>
                <button onclick="changeStaffCalMonth(1)">&#9654;</button>
            </div>
        </div>
        <div class="staff-cal-grid" id="staffCalGrid"></div>
    </div>
</div>

<div class="staff-cal-day-modal" id="staffCalDayModal">
    <div class="staff-cal-day-modal-content">
        <div class="staff-cal-day-modal-header">
            <h3 id="staffCalDayTitle">Appointments</h3>
            <button class="staff-cal-day-modal-close" onclick="closeStaffCalDayModal()">&times;</button>
        </div>
        <div id="staffCalDayContent"></div>
    </div>
</div>

<div id="viewAppointmentModal" class="modal-overlay">
    <div class="modal" style="width: 500px;">
        <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Appointment Details</h2>
        <div id="viewAppointmentContent"></div>
        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px;">
            <button onclick="closeViewAppointmentModal()" class="btn-primary">Close</button>
            <button onclick="editAppointmentFromView()" class="btn-primary">Edit</button>
        </div>
    </div>
</div>

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
                    <input type="text" name="first_name" required class="form-control" placeholder="Enter first name" value="<?php echo htmlspecialchars($inquiryData['first_name'] ?? ''); ?>">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" class="form-control" placeholder="Enter middle name" value="<?php echo htmlspecialchars($inquiryData['middle_name'] ?? ''); ?>">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" required class="form-control" placeholder="Enter last name" value="<?php echo htmlspecialchars($inquiryData['last_name'] ?? ''); ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="0912-345-6789" value="<?php echo htmlspecialchars($inquiryData['contact_info'] ?? ''); ?>">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" placeholder="example@email.com">
                </div>
            </div>
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <div style="font-size: 13px; font-weight: 600; color: #166534; margin-bottom: 12px;">Select Appointment Slot</div>
                <div class="form-row" style="margin-bottom: 12px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Treatment * <span style="font-size: 11px; color: #6b7280; font-weight: 400;">(select first to set duration)</span></label>
                        <select name="treatment" id="staffServiceSelect" class="form-control" required>
                            <option value="">-- Select Treatment --</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Dentist <span style="font-size: 11px; color: #6b7280; font-weight: 400;">(optional)</span></label>
                        <select name="dentist_id" id="staffDentistSelect" class="form-control">
                            <option value="">Any Available Dentist</option>
                        </select>
                    </div>
                </div>
                <div class="form-row" style="margin-bottom: 12px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Date *</label>
                        <input type="date" name="appointment_date" id="staffApptDate" required class="form-control">
                    </div>
                </div>
                <div id="staffSlotGrid" style="margin-top: 8px;">
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
                <button type="submit" class="btn-primary" id="staffScheduleBtn" disabled>Schedule Appointment</button>
            </div>
        </form>
    </div>
</div>

<style>
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
.search-input {
    width: 300px;
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
    border-radius: 8px;
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

.status-badge.status-in-queue {
    background: #9cd8a9;
    color: #285a11;
}

.status-badge.status-pending-payment {
    background: #fef3c7;
    color: #d97706;
}

.status-badge.status-completed {
    background: #dcfce7;
    color: #15803d;
}

.status-badge.status-cancelled {
    background: #fee2e2;
    color: #dc2626;
}

.status-badge.status-arrived {
    background: #ccfbf1;
    color: #0f766e;
}

.status-badge.status-pending-approval {
    background: #fef3c7;
    color: #b45309;
}

.appt-approval-actions {
    display: flex;
    gap: 8px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed #f3f4f6;
}

.btn-approve {
    flex: 1;
    padding: 8px 12px;
    background: #16a34a;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
}

.btn-approve:hover {
    background: #15803d;
}

.btn-decline {
    flex: 1;
    padding: 8px 12px;
    background: #dc2626;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
}

.btn-decline:hover {
    background: #b91c1c;
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

.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 99999;
    align-items: center;
    justify-content: center;
}

.modal-overlay[style*="display: flex"] {
    display: flex !important;
}

.modal {
    background: white;
    border-radius: 12px;
    padding: 28px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    position: relative;
    z-index: 100000;
}
</style>

<script>
    const appointments = <?php echo json_encode($allAppointments); ?>;
    const unavailableDates = <?php echo json_encode($unavailableDates); ?>;

    (function() {
        const viewAppointmentModal = document.getElementById('viewAppointmentModal');
        const appointmentModal = document.getElementById('appointmentModal');
        
        if (viewAppointmentModal) {
            document.body.appendChild(viewAppointmentModal);
        }
        if (appointmentModal) {
            document.body.appendChild(appointmentModal);
        }
    })();

    document.addEventListener('DOMContentLoaded', function() {
        createApptKebabDropdown();
        
        document.addEventListener('click', function(e) {
            const button = e.target.closest('.appt-kebab-btn');
            if (button) {
                e.preventDefault();
                e.stopPropagation();
                
                const btnId = button.id || ('appt-btn-' + button.dataset.apptId);
                
                if (apptActiveButtonId === btnId && apptKebabDropdown.classList.contains('show')) {
                    closeApptKebabDropdown();
                } else {
                    closeApptKebabDropdown();
                    openApptKebabDropdown(button);
                }
            } else if (!e.target.closest('.appt-kebab-dropdown-portal')) {
                closeApptKebabDropdown();
            }
        });
        
        document.querySelectorAll('.filter-tabs span').forEach(tab => {
            tab.addEventListener('click', function() {
                closeApptKebabDropdown();
                document.querySelectorAll('.filter-tabs span').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                filterAppointments();
            });
        });

        document.getElementById('searchAppointment').addEventListener('input', function() {
            closeApptKebabDropdown();
            filterAppointments();
        });
        
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
                    initStaffCalendar();
                }
            });
        });
        
        <?php if ($showModal): ?>
        openAppointmentModal();
        <?php endif; ?>
    });

    let staffCalYear, staffCalMonth;
    function initStaffCalendar() {
        const today = new Date();
        staffCalYear = today.getFullYear();
        staffCalMonth = today.getMonth();
        renderStaffCalendar();
    }

    function changeStaffCalMonth(dir) {
        staffCalMonth += dir;
        if (staffCalMonth < 0) { staffCalMonth = 11; staffCalYear--; }
        if (staffCalMonth > 11) { staffCalMonth = 0; staffCalYear++; }
        renderStaffCalendar();
    }

    function renderStaffCalendar() {
        document.getElementById('staffCalMonthYear').textContent = new Date(staffCalYear, staffCalMonth).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        
        const grid = document.getElementById('staffCalGrid');
        grid.innerHTML = '';
        
        const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        days.forEach(d => {
            const h = document.createElement('div');
            h.className = 'staff-cal-day-header';
            h.textContent = d;
            grid.appendChild(h);
        });
        
        const firstDay = new Date(staffCalYear, staffCalMonth, 1).getDay();
        const totalDays = new Date(staffCalYear, staffCalMonth + 1, 0).getDate();
        const prevDays = new Date(staffCalYear, staffCalMonth, 0).getDate();
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        for (let i = firstDay - 1; i >= 0; i--) {
            const d = document.createElement('div');
            d.className = 'staff-cal-day empty';
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
            const dateStr = `${staffCalYear}-${String(staffCalMonth + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
            const dateObj = new Date(staffCalYear, staffCalMonth, i);
            
            d.className = 'staff-cal-day';
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
                        const name = (appt.first_name || '') + ' ' + (appt.last_name || '');
                        html += `<div class="appt-mini">${time} - ${name.trim()}</div>`;
                    });
                    if (appts.length > 3) {
                        html += `<div class="appt-mini">+${appts.length - 3} more</div>`;
                    }
                }
                d.innerHTML = html;
            }
            
            d.addEventListener('click', () => openStaffCalDayModal(dateStr, apptByDate[dateStr] || [], closedEntry));
            grid.appendChild(d);
        }
        
        const remaining = 42 - (firstDay + totalDays);
        for (let i = 1; i <= remaining; i++) {
            const d = document.createElement('div');
            d.className = 'staff-cal-day empty';
            d.textContent = i;
            grid.appendChild(d);
        }
    }

    function openStaffCalDayModal(dateStr, appts, closedEntry) {
        const formatted = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        document.getElementById('staffCalDayTitle').textContent = formatted;
        
        const content = document.getElementById('staffCalDayContent');
        
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
                html += `<div class="staff-cal-day-modal-item">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 500;">${name}</div>
                            <div style="font-size: 14px; color: #6b7280;">${time} - ${appt.service_name || 'General'}</div>
                        </div>
                        <span class="status-badge status-${status.toLowerCase().replace(' ', '-')}">${status}</span>
                    </div>
                </div>`;
            });
            content.innerHTML = html;
        }
        
        document.getElementById('staffCalDayModal').classList.add('active');
    }

    function closeStaffCalDayModal() {
        document.getElementById('staffCalDayModal').classList.remove('active');
    }

    document.getElementById('staffCalDayModal').addEventListener('click', function(e) {
        if (e.target === this) closeStaffCalDayModal();
    });

    // Portal modal to body to escape stacking context (sidebar, header)
    (function() {
        const modal = document.getElementById('staffCalDayModal');
        if (modal) document.body.appendChild(modal);
    })();

    function filterAppointments() {
        const search = document.getElementById('searchAppointment').value.toLowerCase();
        const activeFilter = document.querySelector('.filter-tabs span.active').dataset.filter;
        const today = new Date().toISOString().split('T')[0];
        const weekEnd = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
        const monthEnd = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

        const counts = { pendingapproval: 0, scheduled: 0, inqueue: 0 };

        document.querySelectorAll('.appointment-mini-card').forEach(card => {
            const nameMatch = !search || card.dataset.name.includes(search);
            
            let dateMatch = true;
            if (activeFilter === 'today') {
                dateMatch = card.dataset.date === today;
            } else if (activeFilter === 'week') {
                dateMatch = card.dataset.date >= today && card.dataset.date <= weekEnd;
            } else if (activeFilter === 'month') {
                dateMatch = card.dataset.date >= today && card.dataset.date <= monthEnd;
            }
            
            const show = nameMatch && dateMatch;
            card.style.display = show ? '' : 'none';
            if (show) {
                const section = card.closest('.status-section');
                if (section) {
                    const key = section.dataset.section;
                    if (counts[key] !== undefined) counts[key]++;
                }
            }
        });

        document.getElementById('countPendingapproval').textContent = counts.pendingapproval;
        document.getElementById('countScheduled').textContent = counts.scheduled;
        document.getElementById('countInqueue').textContent = counts.inqueue;
    }

    var selectedStaffSlot = null;

    function openAppointmentModal() {
        document.getElementById('appointmentModal').style.display = 'flex';
    }

    function loadStaffServices(callback) {
        fetch('../shared/get_services.php')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var select = document.getElementById('staffServiceSelect');
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

    function loadStaffDentists(callback) {
        fetch('../shared/get_dentists.php')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var select = document.getElementById('staffDentistSelect');
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

    function loadStaffSlots(callback) {
        var serviceId = document.getElementById('staffServiceSelect').value;
        var dentistId = document.getElementById('staffDentistSelect').value;
        var date = document.getElementById('staffApptDate').value;
        var grid = document.getElementById('staffSlotGrid');
        var btn = document.getElementById('staffScheduleBtn');

        if (!serviceId || !date) {
            grid.innerHTML = '<div style="text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; background: #f8fafc; border-radius: 8px; border: 1px dashed #d1d5db;">Select a treatment, date, and optionally a dentist to see available time slots</div>';
            btn.disabled = true;
            if (typeof callback === 'function') callback();
            return;
        }

        selectedStaffSlot = null;
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
                    s.textContent = formatStaffTime(slot);

                    if (!isBooked) {
                        s.dataset.time = slot;
                        s.onclick = function() {
                            document.querySelectorAll('#staffSlotGrid .slot-item').forEach(function(el) {
                                el.style.borderColor = '#bbf7d0';
                                el.style.background = '#f0fdf4';
                            });
                            this.style.borderColor = '#15803d';
                            this.style.background = '#bbf7d0';
                            selectedStaffSlot = slot;
                            document.getElementById('selectedTimeInput').value = slot;
                            document.getElementById('staffScheduleBtn').disabled = false;
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

    function formatStaffTime(t) {
        var parts = t.split(':');
        var h = parseInt(parts[0]);
        var m = parts[1];
        var ampm = h >= 12 ? 'PM' : 'AM';
        if (h > 12) h -= 12;
        if (h === 0) h = 12;
        return h + ':' + m + ' ' + ampm;
    }

    function prepareNewAppointmentModal() {
        const form = document.getElementById('appointmentForm');
        if (form) form.reset();
        document.getElementById('appointmentId').value = '';
        document.getElementById('appointmentPatientId').value = '';
        document.getElementById('selectedTimeInput').value = '';
        document.getElementById('staffScheduleBtn').disabled = true;
        selectedStaffSlot = null;
        document.querySelector('#appointmentModal h2').textContent = 'Schedule New Appointment';
        document.querySelector('#appointmentForm button[type="submit"]').textContent = 'Schedule Appointment';
        document.getElementById('staffSlotGrid').innerHTML = '<div style="text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; background: #f8fafc; border-radius: 8px; border: 1px dashed #d1d5db;">Select a treatment, date, and optionally a dentist to see available time slots</div>';
        loadStaffServices();
        loadStaffDentists();
        openAppointmentModal();
    }

    function closeAppointmentModal() {
        document.getElementById('appointmentId').value = '';
        document.getElementById('appointmentPatientId').value = '';
        document.querySelector('#appointmentModal h2').textContent = 'Schedule New Appointment';
        document.querySelector('#appointmentForm button[type="submit"]').textContent = 'Schedule Appointment';
        document.getElementById('appointmentModal').style.display = 'none';
    }

    function closeViewAppointmentModal() {
        document.getElementById('viewAppointmentModal').dataset.appointmentId = '';
        document.getElementById('viewAppointmentModal').style.display = 'none';
    }

    document.getElementById('appointmentModal').addEventListener('click', function(e) {
        if (e.target === this) closeAppointmentModal();
    });

    document.getElementById('viewAppointmentModal').addEventListener('click', function(e) {
        if (e.target === this) closeViewAppointmentModal();
    });

    document.getElementById('appointmentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        
        // Add service_id from the treatment dropdown
        var serviceId = document.getElementById('staffServiceSelect').value;
        if (serviceId) formData.set('service_id', serviceId);
        
        // dentist_id already in formData from the select
        
        // Set the appointment_time from selected slot
        if (selectedStaffSlot) {
            formData.set('appointment_time', selectedStaffSlot);
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

    // Event listeners for slot loading
    document.getElementById('staffServiceSelect').addEventListener('change', loadStaffSlots);
    document.getElementById('staffDentistSelect').addEventListener('change', loadStaffSlots);
    document.getElementById('staffApptDate').addEventListener('change', loadStaffSlots);

    function viewAppointment(id) {
        const appt = appointments.find(a => a.id == id);
        if (!appt) return;
        
        const firstName = appt.first_name || '';
        const middleName = appt.middle_name || '';
        const lastName = appt.last_name || '';
        const displayName = [firstName, middleName, lastName].filter(n => n).join(' ') || 'Unknown';
        
        document.getElementById('viewAppointmentContent').innerHTML = `
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div>
                    <span style="color: #6b7280;">Patient:</span> 
                    <span style="font-weight: 600; margin-left: 8px;">${firstName}</span>
                    ${middleName ? `<span style="color: #6b7280; margin-left: 4px;">${middleName}</span>` : ''}
                    <span style="font-weight: 600; margin-left: 4px;">${lastName}</span>
                </div>
                <div><span style="color: #6b7280;">Phone:</span> <span style="font-weight: 500; margin-left: 8px;">${appt.phone || 'N/A'}</span></div>
                <div><span style="color: #6b7280;">Date:</span> <span style="font-weight: 500; margin-left: 8px;">${new Date(appt.appointment_date).toLocaleDateString()}</span></div>
                <div><span style="color: #6b7280;">Time:</span> <span style="font-weight: 500; margin-left: 8px;">${appt.appointment_time ? new Date('1970-01-01 ' + appt.appointment_time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : 'N/A'}</span></div>
                <div><span style="color: #6b7280;">Treatment:</span> <span style="font-weight: 500; margin-left: 8px;">${appt.service_name || appt.treatment || 'General'}</span></div>
                <div><span style="color: #6b7280;">Dentist:</span> <span style="font-weight: 500; margin-left: 8px;">${appt.dentist_name || 'Unassigned'}</span></div>
                <div><span style="color: #6b7280;">Status:</span> <span class="status-badge" style="margin-left: 8px; background: ${getAppointmentDisplayStatus(appt) === 'Completed' ? '#dcfce7' : getAppointmentDisplayStatus(appt) === 'Cancelled' ? '#fee2e2' : getAppointmentDisplayStatus(appt).toLowerCase() === 'in procedure' ? '#cffafe' : getAppointmentDisplayStatus(appt).toLowerCase() === 'arrived' ? '#ccfbf1' : '#e0f2fe'}; color: ${getAppointmentDisplayStatus(appt) === 'Completed' ? '#15803d' : getAppointmentDisplayStatus(appt) === 'Cancelled' ? '#dc2626' : getAppointmentDisplayStatus(appt).toLowerCase() === 'in procedure' ? '#0e7490' : getAppointmentDisplayStatus(appt).toLowerCase() === 'arrived' ? '#0f766e' : '#0369a1'};">${getAppointmentDisplayStatus(appt)}</span></div>
                ${appt.notes ? `<div><span style="color: #6b7280;">Notes:</span><p style="background: #f9fafb; padding: 12px; border-radius: 8px; margin: 8px 0 0;">${appt.notes}</p></div>` : ''}
            </div>
        `;
        
        document.getElementById('viewAppointmentModal').dataset.appointmentId = id;
        document.getElementById('viewAppointmentModal').style.display = 'flex';
    }

    function editAppointmentFromView() {
        const openViewModal = document.getElementById('viewAppointmentModal');
        const activeId = openViewModal ? openViewModal.dataset.appointmentId : '';
        closeViewAppointmentModal();
        if (activeId) {
            editAppointment(activeId);
        }
    }

    function cancelAppointment(id) {
        openCancelReasonModal(id);
    }

    let cancelAppointmentId = null;
    
    function openCancelReasonModal(id) {
        if (!document.getElementById('cancelReasonModal')) {
            const modal = document.createElement('div');
            modal.id = 'cancelReasonModal';
            modal.className = 'modal-overlay';
            modal.style.display = 'none';
            modal.innerHTML = `
                <div class="modal" style="width: 400px;">
                    <h2 style="margin: 0 0 16px; font-size: 1.25rem; font-weight: 600;">Cancel Appointment</h2>
                    <p style="color: #6b7280; margin-bottom: 16px;">Please provide a reason for cancelling:</p>
                    <textarea id="cancelReasonText" class="form-control" rows="4" placeholder="Enter reason..." style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; resize: vertical;"></textarea>
                    <div style="display: flex; gap: 12px; margin-top: 20px; justify-content: flex-end;">
                        <button onclick="closeCancelReasonModal()" class="btn-cancel" style="padding: 10px 20px; background: #f3f4f6; border: none; border-radius: 6px; cursor: pointer;">Cancel</button>
                        <button onclick="confirmCancelAppointment()" class="btn-primary" style="padding: 10px 20px; background: #dc2626; color: white; border: none; border-radius: 6px; cursor: pointer;">Confirm</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        cancelAppointmentId = id;
        document.getElementById('cancelReasonText').value = '';
        document.getElementById('cancelReasonModal').style.display = 'flex';
    }
    
    function closeCancelReasonModal() {
        if (document.getElementById('cancelReasonModal')) {
            document.getElementById('cancelReasonModal').style.display = 'none';
        }
        cancelAppointmentId = null;
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
                alert(data.message || 'Error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error');
        });
        
        closeCancelReasonModal();
    }

    function approveAppointment(id) {
        if (!confirm('Approve this appointment request?\n\nThe patient will be notified and the slot will be added to the calendar.')) return;
        fetch('../shared/approve_appointment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'approve', appointment_id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Appointment approved');
                location.reload();
            } else {
                alert(data.message || 'Error approving appointment');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Something went wrong. Please try again.');
        });
    }

    function declineAppointment(id) {
        const reason = prompt('Enter a reason for declining this request (optional):', '');
        if (reason === null) return;
        fetch('../shared/approve_appointment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'decline', appointment_id: id, decline_reason: reason })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Appointment request declined');
                location.reload();
            } else {
                alert(data.message || 'Error declining appointment');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Something went wrong. Please try again.');
        });
    }

    let apptKebabDropdown = null;
    let apptKebabBackdrop = null;
    let apptActiveButtonId = null;

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
        
        // Show Delete button if cancelled, Cancel button if not
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
        const padding = 12;

        apptKebabDropdown.style.display = 'block';
        apptKebabDropdown.style.visibility = 'hidden';
        const dropdownRect = apptKebabDropdown.getBoundingClientRect();
        const dropdownWidth = dropdownRect.width || 200;
        const dropdownHeight = dropdownRect.height || 120;

        let left = rect.left - dropdownWidth - 8;
        let top = rect.top + (rect.height / 2) - (dropdownHeight / 2);

        if (left < padding) {
            left = rect.right + 8;
        }

        if (top + dropdownHeight > viewportHeight - padding) {
            top = viewportHeight - padding - dropdownHeight;
        }
        if (top < padding) {
            top = padding;
        }

        apptKebabDropdown.style.left = left + 'px';
        apptKebabDropdown.style.top = top + 'px';
        apptKebabDropdown.style.visibility = 'visible';
    }

    function openApptKebabDropdown(button) {
        if (!apptKebabDropdown) {
            createApptKebabDropdown();
        }

        const apptId = button.dataset.apptId;
        const btnId = 'appt-btn-' + apptId;
        
        if (apptActiveButtonId) {
            const prevBtn = document.getElementById(apptActiveButtonId);
            if (prevBtn) prevBtn.classList.remove('active');
        }

        apptKebabDropdown.innerHTML = getApptMenuItems(apptId);
        positionApptKebabDropdown(button);

        apptKebabDropdown.style.display = '';
        apptKebabDropdown.classList.add('show');
        apptKebabBackdrop.style.display = '';
        apptKebabBackdrop.classList.add('show');
        apptActiveButtonId = btnId;
        button.id = btnId;
        button.classList.add('active');

        apptKebabDropdown.addEventListener('click', handleApptKebabClick);
    }

    function closeApptKebabDropdown() {
        if (apptKebabDropdown) {
            apptKebabDropdown.classList.remove('show');
            apptKebabDropdown.style.display = 'none';
        }
        if (apptKebabBackdrop) {
            apptKebabBackdrop.classList.remove('show');
            apptKebabBackdrop.style.display = 'none';
        }
        if (apptActiveButtonId) {
            const btn = document.getElementById(apptActiveButtonId);
            if (btn) btn.classList.remove('active');
            apptActiveButtonId = null;
        }
    }

    function handleApptKebabClick(e) {
        const link = e.target.closest('a');
        if (!link) return;

        e.preventDefault();
        e.stopPropagation();
        const action = link.dataset.action;
        const id = link.dataset.id;

        closeApptKebabDropdown();
        
        setTimeout(() => {
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
                case 'delete':
                    deleteAppointment(id);
                    break;
            }
        }, 10);
    }
    
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
        loadStaffServices(function() {
            var svcSelect = document.getElementById('staffServiceSelect');
            if (appt.service_id) {
                svcSelect.value = appt.service_id;
            } else {
                // Fallback: find by service_name
                for (var i = 0; i < svcSelect.options.length; i++) {
                    if (svcSelect.options[i].text.indexOf(appt.service_name || appt.treatment) !== -1) {
                        svcSelect.value = svcSelect.options[i].value;
                        break;
                    }
                }
            }

            loadStaffDentists(function() {
                var dentSelect = document.getElementById('staffDentistSelect');
                if (appt.dentist_id) {
                    dentSelect.value = appt.dentist_id;
                }

                var dateInput = document.getElementById('staffApptDate');
                dateInput.value = appt.appointment_date ? String(appt.appointment_date).split(' ')[0] : '';

                openAppointmentModal();

                // Load slots and auto-select the current time
                loadStaffSlots(function() {
                    var currentTime = appt.appointment_time ? String(appt.appointment_time).slice(0, 5) : '';
                    if (currentTime) {
                        var slotItems = document.querySelectorAll('#staffSlotGrid .slot-item');
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

    function getAppointmentDisplayStatus(appt) {
        if (!appt) return 'Pending';
        const s = (appt.status_label || appt.status || 'Scheduled');
        const qs = (appt.queue_status || '');
        if (s.toLowerCase().indexOf('pending approval') !== -1) return 'Pending Approval';
        if (s.toLowerCase().indexOf('cancel') !== -1 || qs === 'cancelled') return 'Cancelled';
        if (s.toLowerCase().indexOf('complete') !== -1 || qs === 'completed') return 'Completed';
        if (qs === 'in_procedure') return 'In Procedure';
        if (s.toLowerCase().indexOf('pending payment') !== -1 || qs === 'pending_payment') return 'Pending Payment';
        if (s.toLowerCase().indexOf('in queue') !== -1 || s.toLowerCase() === 'arrived' || qs === 'waiting' || qs === 'on_hold') return 'In Queue';
        return 'Scheduled';
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

    function deleteAppointment(id) {
        if (confirm('Are you sure you want to archive this appointment?\n\nThis will move it to the Archive page where admin can restore or permanently delete it.')) {
            fetch('../api/delete_appointment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, action: 'archive' }),
                credentials: 'same-origin'
            })
            .then(response => {
                console.log('Delete response status:', response.status);
                return response.text().then(text => {
                    console.log('Delete response text:', text);
                    try {
                        const data = JSON.parse(text);
                        return data;
                    } catch (e) {
                        throw new Error('Invalid JSON response: ' + text);
                    }
                });
            })
            .then(data => {
                console.log('Delete response data:', data);
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Delete Error:', error);
                alert('Error deleting appointment: ' + error.message);
            });
        }
    }

// Appointment status auto-polling
var apptPollHashStaff = '';

function startAppointmentPolling() {
    setInterval(function() {
        fetch('../shared/appointments_poll.php')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) return;
                if (data.hash !== apptPollHashStaff) {
                    apptPollHashStaff = data.hash;
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
    var cards = document.querySelectorAll('.appointment-mini-card');
    for (var i = 0; i < cards.length; i++) {
        var card = cards[i];
        // Queue-derived status (e.g. In Procedure) is more current than the appointment's own status
        if (card.dataset.liveStatus === '1') continue;
        var kebab = card.querySelector('.appt-kebab-btn');
        if (!kebab) continue;
        var apptId = parseInt(kebab.dataset.apptId);
        if (!apptId || !statusMap[apptId]) continue;
        var newStatus = statusMap[apptId];
        var currentStatus = card.dataset.status;
        if (newStatus.toLowerCase() === currentStatus.toLowerCase()) continue;
        card.dataset.status = newStatus.toLowerCase();
        kebab.dataset.status = newStatus;
        var badge = card.querySelector('.status-badge');
        if (badge) {
            badge.textContent = newStatus;
            badge.className = 'status-badge badge-small ' + getApptBadgeClass(newStatus);
        }
    }
}

function getApptBadgeClass(status) {
    var s = status.toLowerCase();
    if (s.indexOf('pending approval') !== -1) return 'status-pending-approval';
    if (s.indexOf('in procedure') !== -1) return 'status-in-procedure';
    if (s.indexOf('arrived') !== -1) return 'status-arrived';
    if (s.indexOf('queue') !== -1) return 'status-in-queue';
    if (s.indexOf('pending') !== -1) return 'status-pending-payment';
    if (s.indexOf('complete') !== -1) return 'status-completed';
    if (s.indexOf('cancel') !== -1) return 'status-cancelled';
    return 'status-scheduled';
}

document.addEventListener('DOMContentLoaded', startAppointmentPolling);

// Auto-scroll to Scheduled section when arriving from dashboard Upcoming Appointments
(function() {
    var params = new URLSearchParams(window.location.search);
    if (params.get('focus') !== 'scheduled') return;

    // Ensure List View is active
    document.querySelectorAll('.view-toggle-btn').forEach(function(b) {
        b.classList.toggle('active', b.dataset.view === 'list');
    });
    var listView = document.getElementById('listView');
    if (listView) listView.classList.remove('hidden');
    var calView = document.getElementById('calendarView');
    if (calView) calView.classList.remove('active');

    // Reset filter to All and clear search
    document.querySelectorAll('.filter-tabs span').forEach(function(t) {
        t.classList.toggle('active', t.dataset.filter === 'all');
    });
    var searchInput = document.getElementById('searchAppointment');
    if (searchInput) searchInput.value = '';
    filterAppointments();

    // Scroll to Scheduled section with a temporary highlight
    var section = document.querySelector('.status-section[data-section="scheduled"]');
    if (section) {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        section.classList.add('highlight-scheduled');
        setTimeout(function() {
            section.classList.remove('highlight-scheduled');
        }, 2500);
    }
})();
</script>

<?php require_once '../../includes/staff_layout_end.php'; ?>
