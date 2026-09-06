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
        q.status as queue_status, q.queue_number, q.queue_label,
        q.queue_id, q.blood_pressure, q.heart_rate, q.temperature,
        q.oxygen_saturation, q.height, q.weight, q.doctor_id
        FROM appointments a 
        LEFT JOIN patients p ON CAST(a.patient_id AS UNSIGNED) = p.id
        LEFT JOIN services s ON a.service_id = s.id
        LEFT JOIN treatment_plans tp ON a.id = tp.appointment_id
        LEFT JOIN users d ON a.dentist_id = d.id
        LEFT JOIN (
            SELECT q1.appointment_id, q1.status, q1.queue_number, q1.queue_label, q1.id AS queue_id,
                   q1.blood_pressure, q1.heart_rate, q1.temperature, q1.oxygen_saturation,
                   q1.height, q1.weight, q1.doctor_id
            FROM queue q1
            INNER JOIN (
                SELECT appointment_id, MAX(id) AS max_id
                FROM queue
                WHERE appointment_id IS NOT NULL AND is_archived = 0
                GROUP BY appointment_id
            ) latest ON latest.max_id = q1.id
        ) q ON q.appointment_id = a.id
        WHERE (a.is_archived = 0 OR a.is_archived IS NULL)
        ORDER BY a.appointment_date ASC, a.id ASC"; //FROM DESC
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
    if ($displayStatus === 'Completed' || $displayStatus === 'Cancelled') {
        continue;
    }
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

// Build absolute URL to the public online booking page
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$currentDir = dirname(str_replace('\\', '/', $_SERVER['SCRIPT_NAME']));
$onlineBookingUrl = $scheme . '://' . $host . dirname($currentDir) . '/welcome-page/home.php';

// Clinic info for the queue ticket print
$clinicName = 'RF Dental Clinic';
$clinicAddress = '';
try {
    $settingsRows = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('clinic_name', 'clinic_address')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $clinicName = $settingsRows['clinic_name'] ?? $clinicName;
    $clinicAddress = $settingsRows['clinic_address'] ?? '';
} catch (Exception $e) {
    // Settings query is best-effort
}
?>
<div class="summary-cards" 
    style="
    display: grid; 
    grid-template-columns: 
    repeat(5, 1fr); 
    gap: 15px; 
    margin-bottom: 24px;">

    <div class="summary-card card-total">
        <div class="summary-icon">
            <img src="../../assets/icons/calendar.png" alt="Total Appointments">
        </div>
        <div class="summary-info">
            <h3><?php echo $totalAppointments; ?></h3>
            <p>Total Appointments</p>
        </div>
    </div>
    <div class="summary-card card-today">
        <div class="summary-icon">
            <img src="../../assets/icons/today.png" alt="Today">
        </div>
        <div class="summary-info">
            <h3><?php echo $todayCount; ?></h3>
            <p>Today</p>
        </div>
    </div>
    <div class="summary-card card-pending">
        <div class="summary-icon">
            <img src="../../assets/icons/pending.png" alt="Pending">
        </div>
        <div class="summary-info">
            <h3><?php echo $pendingCount; ?></h3>
            <p>Pending</p>
        </div>
    </div>
    <div class="summary-card card-completed">
        <div class="summary-icon">
            <img src="../../assets/icons/check.png" alt="Completed">
        </div>
        <div class="summary-info">
            <h3><?php echo $completedCount; ?></h3>
            <p>Completed</p>
        </div>
    </div>
    <div class="summary-card card-cancelled">
        <div class="summary-icon">
            <img src="../../assets/icons/delete.png" alt="Cancelled">
        </div>
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
        grid-template-columns: repeat(6, minmax(0, 1fr)) !important;
        gap: 12px !important;
    }

    .summary-cards .summary-card:nth-child(-n+3) {
        grid-column: span 2;
    }

    .summary-cards .summary-card:nth-child(n+4) {
        grid-column: span 3;
    }
}

.view-toggle { display: flex; gap: 8px; margin-left: auto; }
.view-toggle-btn { padding: 8px 16px; border: 1px solid #d1d5db; background: white; cursor: pointer; font-size: 14px; font-weight: 500; color: #6b7280; border-radius: 6px; transition: all 0.2s; }
.view-toggle-btn:hover { background: #f3f4f6; }
.view-toggle-btn.active { background: #0d92f7; color: white; border-color: #0d4bf7; }

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
    <button class="btn-online-booking" onclick="closeApptKebabDropdown(); openOnlineBookingModal()">🌐 Online Booking</button>
</div>
<style>
/* Match dentist appointments search-filters style */
.search-filters {
    background: transparent;
    padding: 0 0 16px;
    border: none;
    border-radius: 0;
    margin-bottom: 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.filter-tabs {
    display: flex;
    gap: 8px;
}
.filter-tabs span {
    margin-right: 15px;
    padding: 8px 16px;
    background: #f3f4f6;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.875rem;
    transition: all 0.2s;
}
.filter-tabs span:last-child {
    margin-right: 0;
}
.filter-tabs span:hover {
    background: #e5e7eb;
}
.filter-tabs span.active {
    background: #0d92f7;
    color: white;
}
</style>

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
                        $dateLabel = date('M j, Y', strtotime($appt['appointment_date']));
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
                            <span class="mini-card-date">📅 <?php echo htmlspecialchars($dateLabel); ?></span>
                            <span class="mini-card-treatment"><?php echo $treatment; ?> &middot; <?php echo $dentist; ?></span>
                            <span class="status-badge badge-small <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                        </div>
                        <?php if ($sec === 'pendingapproval'): ?>
                        <div class="appt-approval-actions">
                            <button type="button" class="btn-approve" onclick="approveAppointment(<?php echo (int)$appt['id']; ?>)">✓ Approve</button>
                            <button type="button" class="btn-decline" onclick="declineAppointment(<?php echo (int)$appt['id']; ?>)">✕ Decline</button>
                            <button type="button" class="pa-call" style="width:40px;min-width:40px;padding:8px;border:1px solid #bfdbfe;border-radius:6px;background:#eff6ff;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;" data-patient-name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" data-phone="<?php echo htmlspecialchars($appt['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" onclick="openAppointmentCallModalFromButton(this)" title="View patient phone number" aria-label="View patient phone number"><img src="../../assets/icons/call_button.png" alt="Phone" style="width:18px;height:18px;object-fit:contain;"></button>
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

<div id="viewAppointmentModal" class="modal-overlay" data-preserve-open="1">
    <div class="modal" style="width: 500px;">
        <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Appointment Details</h2>
        <div id="viewAppointmentContent"></div>
        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px; flex-wrap: wrap;">
            <button type="button" id="messagePatientFromNotesBtn" onclick="messagePatientFromNotes()" class="btn-primary" style="display:none; background:#0369a1;">Message patient</button>
            <button onclick="closeViewAppointmentModal()" class="btn-primary">Close</button>
            <button onclick="editAppointmentFromView()" class="btn-primary">Edit</button>
        </div>
    </div>
</div>

<div id="appointmentModal" class="modal-overlay" data-preserve-open="1">
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

<div id="onlineBookingModal" class="modal-overlay">
    <div class="modal" style="width: 480px; text-align: center;">
        <h2 style="margin: 0 0 8px; font-size: 1.25rem; font-weight: 600;">Online Booking</h2>
        <p style="margin: 0 0 20px; color: #6b7280; font-size: 0.9rem;">Scan the QR code or share the link so patients can book appointments online.</p>
        <div id="onlineBookingQr" class="qr-clickable" title="Click to enlarge" onclick="openQrMaximized()"></div>
        <div class="booking-link-row">
            <input type="text" id="onlineBookingLinkInput" readonly>
            <button type="button" onclick="copyBookingLink()">Copy</button>
        </div>
        <a href="#" id="onlineBookingLinkAnchor" target="_blank" rel="noopener" style="display: inline-block; margin-top: 8px; color: #2563eb; font-size: 0.875rem; text-decoration: none;">Open booking page</a>
        <div class="modal-actions">
            <button type="button" onclick="closeOnlineBookingModal()" class="btn-cancel">Close</button>
        </div>
    </div>
</div>

<div id="qrMaximizedModal" class="modal-overlay" data-preserve-open="1">
    <div class="modal" style="width: auto; max-width: 620px; text-align: center;">
        <h2 style="margin: 0 0 16px; font-size: 1.1rem; font-weight: 600;">Online Booking QR Code</h2>
        <div id="qrMaximizedContent"></div>
        <p style="margin: 14px 0 0; color: #6b7280; font-size: 0.85rem;" id="qrMaximizedUrl"></p>
        <div class="modal-actions">
            <button type="button" onclick="closeQrMaximized()" class="btn-cancel">Close</button>
        </div>
    </div>
</div>

<!-- Hidden queue ticket used for XP-80C thermal printing -->
<div id="queuePrintContainer" aria-hidden="true"></div>

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
    width: 600px;
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
    background: rgba(0, 0, 0, 0.74);
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

.section-title { display: flex; align-items: center; justify-content: flex-end; gap: 10px; }

.btn-online-booking {
    background: transparent; border: 1px solid #10b981; color: #059669;
    padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer;
    display: flex; align-items: center; gap: 8px; transition: background 0.2s;
}
.btn-online-booking:hover { background: #ecfdf5; }

.qr-clickable {
    cursor: pointer; 
    transition: transform 0.15s ease;
    padding-left: 100px;
}
.qr-clickable:hover { transform: scale(1.03); }

.booking-link-row {
    display: flex; gap: 10px; margin: 18px 0 8px;
}
.booking-link-row input {
    flex: 1; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 8px;
    font-size: 0.9rem; color: #475569; background: #f8fafc; outline: none;
}
.booking-link-row button {
    background: #2563eb; color: white; border: none; padding: 10px 18px;
    border-radius: 8px; font-weight: 600; cursor: pointer;
}
.booking-link-row button:hover { background: #1d4ed8; }

/* Vital sign / body measurement form */
.vitals-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.vitals-section { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; margin-bottom: 16px; }
.vitals-section h4 { margin: 0 0 4px; font-size: 0.85rem; font-weight: 600; color: #334155; }
.vitals-section .vitals-sub { margin: 0 0 12px; font-size: 0.75rem; color: #94a3b8; }
.vitals-section label { display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 4px; }
.vitals-section input { width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.85rem; outline: none; }
.vitals-section input:focus { border-color: #2563eb; }

/* Queue ticket print */
#queuePrintContainer { display: none; }
@media print {
    @page { size: 58mm auto; margin: 0; }
    /* 58mm paper has ~48mm printable area - keep everything inside it */
    html, body { width: 48mm; height: auto; margin: 0 !important; padding: 0 !important; background: #fff !important; overflow: hidden !important; }
    body * { visibility: hidden !important; box-sizing: border-box !important; float: none !important; }
    #queuePrintContainer, #queuePrintContainer * {
        visibility: visible !important;
        font-family: 'Courier New', monospace !important;
        box-sizing: border-box !important;
        text-align: center !important;
    }
    #queuePrintContainer {
        display: block !important;
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        right: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 48mm !important;
        text-align: center !important;
    }
    /* Physical mm sizing - immune to driver DPI/scaling. No CSS borders (thermal drivers drop hairlines); separators are dash characters */
    .queue-ticket { width: 100% !important; padding: 0 !important; margin: 0 auto; border: none !important; text-align: center !important; }
    .queue-ticket .qt-line { font-size: 2.5mm; font-weight: 700; line-height: 1.1; white-space: nowrap; overflow: hidden; }
    .queue-ticket .qt-clinic { font-size: 3.6mm; font-weight: 700; text-transform: uppercase; white-space: normal; word-break: break-word; margin-top: 1mm; }
    .queue-ticket .qt-address { font-size: 2.2mm; margin-bottom: 1mm; white-space: normal; word-break: break-word; }
    .queue-ticket .qt-label { font-size: 2.8mm; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-top: 2mm; }
    .queue-ticket .qt-number { font-size: 15mm; font-weight: 700; line-height: 1.05; margin: 0; }
    .queue-ticket .qt-meta { font-size: 2.5mm; margin-top: 1mm; white-space: normal; word-break: break-word; }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
    var appointments = <?php echo json_encode($allAppointments); ?>;
    const unavailableDates = <?php echo json_encode($unavailableDates); ?>;
    const onlineBookingUrl = <?php echo json_encode($onlineBookingUrl); ?>;
    const clinicPrintInfo = {
        name: <?php echo json_encode($clinicName); ?>,
        address: <?php echo json_encode($clinicAddress); ?>
    };

    function escapeHtmlQueue(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function printQueueTicket(opts) {
        var o = opts || {};
        var container = document.getElementById('queuePrintContainer');
        var now = new Date();
        var dateStr = o.date || (now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0'));
        var timeStr = o.time || (String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0'));
        var number = (o.queueNumber || o.queueLabel || '').replace('#', '');
        var label = o.queueLabel || (number ? '#' + number : '');
        var LINE = '----------------------------------------';
        container.innerHTML =
            '<div class="queue-ticket">' +
                '<div class="qt-line">' + LINE + '</div>' +
                '<div class="qt-clinic">' + escapeHtmlQueue(clinicPrintInfo.name || 'RF Dental Clinic') + '</div>' +
                (clinicPrintInfo.address ? '<div class="qt-address">' + escapeHtmlQueue(clinicPrintInfo.address) + '</div>' : '') +
                '<div class="qt-label">Queue Number</div>' +
                '<div class="qt-number">' + escapeHtmlQueue(label) + '</div>' +
                '<div class="qt-line">' + LINE + '</div>' +
                '<div class="qt-meta">Patient: ' + escapeHtmlQueue(o.patientName || '') + '</div>' +
                '<div class="qt-meta">Treatment: ' + escapeHtmlQueue(o.treatment || '') + '</div>' +
                '<div class="qt-meta">Date: ' + escapeHtmlQueue(dateStr) + '  Time: ' + escapeHtmlQueue(timeStr) + '</div>' +
                '<div class="qt-line">' + LINE + '</div>' +
            '</div>';

        // Move container to <body> so no transformed/offset ancestor shifts the ticket
        document.body.appendChild(container);
        window.print();
    }

    function buildVitalsHtml(prefix, values) {
        var v = values || {};
        return '' +
            '<div class="vitals-section">' +
                '<h4>Vital Signs (optional)</h4>' +
                '<p class="vitals-sub">Blood pressure, heart rate, temperature, oxygen saturation</p>' +
                '<div class="vitals-grid">' +
                    '<div><label>Blood Pressure (mmHg)</label><input type="text" name="' + prefix + 'blood_pressure" placeholder="120/80" value="' + escapeHtmlQueue(v.blood_pressure || '') + '"></div>' +
                    '<div><label>Heart Rate (bpm)</label><input type="text" name="' + prefix + 'heart_rate" placeholder="72" value="' + escapeHtmlQueue(v.heart_rate || '') + '"></div>' +
                    '<div><label>Temperature (&deg;C)</label><input type="text" name="' + prefix + 'temperature" placeholder="36.5" value="' + escapeHtmlQueue(v.temperature || '') + '"></div>' +
                    '<div><label>Oxygen Saturation (%)</label><input type="text" name="' + prefix + 'oxygen_saturation" placeholder="98" value="' + escapeHtmlQueue(v.oxygen_saturation || '') + '"></div>' +
                '</div>' +
            '</div>' +
            '<div class="vitals-section">' +
                '<h4>Body Measurement (optional)</h4>' +
                '<p class="vitals-sub">Height, weight, and computed BMI</p>' +
                '<div class="vitals-grid">' +
                    '<div><label>Height (cm)</label><input type="text" name="' + prefix + 'height" placeholder="165" value="' + escapeHtmlQueue(v.height || '') + '"></div>' +
                    '<div><label>Weight (kg)</label><input type="text" name="' + prefix + 'weight" placeholder="58" value="' + escapeHtmlQueue(v.weight || '') + '"></div>' +
                '</div>' +
            '</div>';
    }

    function collectVitals(prefix) {
        var out = {};
        ['blood_pressure', 'heart_rate', 'temperature', 'oxygen_saturation', 'height', 'weight'].forEach(function(f) {
            var el = document.querySelector('[name="' + prefix + f + '"]');
            if (el) out[f] = el.value.trim();
        });
        return out;
    }

    (function() {
        const viewAppointmentModal = document.getElementById('viewAppointmentModal');
        const appointmentModal = document.getElementById('appointmentModal');
        const onlineBookingModal = document.getElementById('onlineBookingModal');
        const qrMaximizedModal = document.getElementById('qrMaximizedModal');
        
        if (viewAppointmentModal) {
            document.body.appendChild(viewAppointmentModal);
        }
        if (appointmentModal) {
            document.body.appendChild(appointmentModal);
        }
        if (onlineBookingModal) {
            document.body.appendChild(onlineBookingModal);
        }
        if (qrMaximizedModal) {
            document.body.appendChild(qrMaximizedModal);
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

    // Portal modals to body so they escape the stacking context (sidebar, header)
    // AND survive refreshAppointmentsAjax(), which replaces .content-main and
    // would otherwise destroy every listener attached inside these modals
    // (treatment change -> reload slots, form submit, etc.).
    (function() {
        var modalIds = ['staffCalDayModal', 'appointmentModal', 'viewAppointmentModal', 'onlineBookingModal', 'qrMaximizedModal'];
        window.__portaledStaffApptModals = {};
        modalIds.forEach(function(id) {
            const modal = document.getElementById(id);
            if (modal) {
                document.body.appendChild(modal);
                window.__portaledStaffApptModals[id] = modal;
            }
        });
    })();

    // After an AJAX refresh the re-rendered .content-main contains fresh copies
    // of these modals; remove the duplicates so getElementById keeps resolving
    // to the persistent (listener-bound) portaled nodes.
    function removeDuplicateStaffPortaledModals() {
        var map = window.__portaledStaffApptModals || {};
        Object.keys(map).forEach(function(id) {
            var keep = map[id];
            if (!keep || !keep.isConnected) return;
            document.querySelectorAll('[id="' + id + '"]').forEach(function(node) {
                if (node !== keep && node.parentNode) node.parentNode.removeChild(node);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (typeof window.refreshAppointmentsAjax === 'function') {
            var origStaffRefresh = window.refreshAppointmentsAjax;
            window.refreshAppointmentsAjax = function() {
                var result = origStaffRefresh.apply(this, arguments);
                Promise.resolve(result).then(removeDuplicateStaffPortaledModals);
                return result;
            };
        }
    });

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
    var staffRescheduleOriginal = null;

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
        var appointmentId = document.getElementById('appointmentId').value;
        if (appointmentId) url += '&exclude_appointment_id=' + encodeURIComponent(appointmentId);
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
        staffRescheduleOriginal = null;
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
        staffRescheduleOriginal = null;
    }

    function closeViewAppointmentModal() {
        document.getElementById('viewAppointmentModal').dataset.appointmentId = '';
        document.getElementById('viewAppointmentModal').style.display = 'none';
    }

    function openOnlineBookingModal() {
        document.getElementById('onlineBookingLinkInput').value = onlineBookingUrl;
        document.getElementById('onlineBookingLinkAnchor').href = onlineBookingUrl;
        var qrContainer = document.getElementById('onlineBookingQr');
        qrContainer.innerHTML = '';
        if (typeof QRCode !== 'undefined') {
            new QRCode(qrContainer, { text: onlineBookingUrl, width: 220, height: 220, correctLevel: QRCode.CorrectLevel.M });
        } else {
            qrContainer.innerHTML = '<p style="color:#6b7280;font-size:0.9rem;padding:40px;">QR library failed to load</p>';
        }
        document.getElementById('onlineBookingModal').style.display = 'flex';
    }

    function closeOnlineBookingModal() {
        document.getElementById('onlineBookingModal').style.display = 'none';
    }

    function openQrMaximized() {
        var content = document.getElementById('qrMaximizedContent');
        content.innerHTML = '';
        if (typeof QRCode !== 'undefined') {
            new QRCode(content, { text: onlineBookingUrl, width: 480, height: 480, correctLevel: QRCode.CorrectLevel.M });
        } else {
            content.innerHTML = '<p style="color:#6b7280;font-size:0.9rem;padding:40px;">QR library failed to load</p>';
        }
        document.getElementById('qrMaximizedUrl').textContent = onlineBookingUrl;
        document.getElementById('qrMaximizedModal').style.display = 'flex';
    }

    function closeQrMaximized() {
        document.getElementById('qrMaximizedModal').style.display = 'none';
    }

    function copyBookingLink() {
        var linkInput = document.getElementById('onlineBookingLinkInput');
        var btn = linkInput.nextElementSibling;
        var originalText = btn.textContent;
        function done() {
            btn.textContent = 'Copied!';
            setTimeout(function() { btn.textContent = originalText; }, 1600);
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(onlineBookingUrl).then(done).catch(function() { fallbackCopy(); });
        } else {
            fallbackCopy();
        }
        function fallbackCopy() {
            linkInput.select();
            linkInput.setSelectionRange(0, 99999);
            try {
                document.execCommand('copy');
                done();
            } catch (e) {
                btn.textContent = 'Copy failed';
            }
        }
    }

    document.getElementById('appointmentModal').addEventListener('click', function(e) {
        if (e.target === this) closeAppointmentModal();
    });

    document.getElementById('viewAppointmentModal').addEventListener('click', function(e) {
        if (e.target === this) closeViewAppointmentModal();
    });

    document.getElementById('onlineBookingModal').addEventListener('click', function(e) {
        if (e.target === this) closeOnlineBookingModal();
    });

    document.getElementById('qrMaximizedModal').addEventListener('click', function(e) {
        if (e.target === this) closeQrMaximized();
    });

    document.getElementById('appointmentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        
        // Add service_id from the treatment dropdown
        var serviceId = document.getElementById('staffServiceSelect').value;
        if (serviceId) formData.set('service_id', serviceId);
        
        // dentist_id already in formData from the select
        
        // Set the appointment_time from the selected slot, retaining the
        // original time when an existing appointment is being edited.
        if (selectedStaffSlot) {
            formData.set('appointment_time', selectedStaffSlot);
        }

        if (formData.get('appointment_id') && !formData.get('appointment_time')) {
            alert('Please select an appointment time slot.');
            return;
        }
        
        fetch('../shared/process_appointment.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                var updatedAppointment = {
                    first_name: formData.get('first_name') || '',
                    middle_name: formData.get('middle_name') || '',
                    last_name: formData.get('last_name') || '',
                    email: formData.get('email') || '',
                    phone: formData.get('phone') || '',
                    appointment_date: formData.get('appointment_date') || '',
                    appointment_time: formData.get('appointment_time') || '',
                    service_name: document.getElementById('staffServiceSelect').selectedOptions[0] ? document.getElementById('staffServiceSelect').selectedOptions[0].textContent.replace(/\s*\([^)]*\)$/, '') : '',
                    dentist_name: document.getElementById('staffDentistSelect').selectedOptions[0] ? document.getElementById('staffDentistSelect').selectedOptions[0].textContent : 'Any Available Dentist'
                };
                closeAppointmentModal();
                refreshAppointmentsAjax().then(function() {
                    if (staffRescheduleOriginal) {
                        var changes = [];
                        var labels = {
                            first_name: 'First name',
                            middle_name: 'Middle name',
                            last_name: 'Last name',
                            email: 'Email',
                            phone: 'Phone number',
                            appointment_date: 'Date',
                            appointment_time: 'Time',
                            service_name: 'Service',
                            dentist_name: 'Dentist'
                        };
                        Object.keys(labels).forEach(function(key) {
                            var fromValue = staffRescheduleOriginal[key] || 'Not set';
                            var toValue = updatedAppointment[key] || 'Not set';
                            if (String(fromValue) !== String(toValue)) {
                                changes.push({ label: labels[key], from: fromValue, to: toValue });
                            }
                        });
                        showAppointmentUpdateSuccess(changes);
                        staffRescheduleOriginal = null;
                    }
                });
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
        document.getElementById('viewAppointmentModal').dataset.patientId = appt.patient_id || '';
        const msgBtn = document.getElementById('messagePatientFromNotesBtn');
        if (msgBtn) {
            msgBtn.style.display = (appt.notes && String(appt.notes).trim()) ? 'inline-flex' : 'none';
        }
        document.getElementById('viewAppointmentModal').style.display = 'flex';
    }

    function messagePatientFromNotes() {
        const modal = document.getElementById('viewAppointmentModal');
        const appointmentId = modal ? modal.dataset.appointmentId : '';
        const patientId = modal ? modal.dataset.patientId : '';
        if (!appointmentId) return;
        if (!patientId) {
            alert('This appointment is not linked to a patient record, so it cannot open Messages.');
            return;
        }

        const btn = document.getElementById('messagePatientFromNotesBtn');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Opening...';
        }

        const body = new URLSearchParams();
        body.set('action', 'open_from_appointment');
        body.set('appointment_id', appointmentId);

        fetch('../shared/inquiry_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        .then(function(r) { return r.text(); })
        .then(function(text) {
            var data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                throw new Error('Unable to open patient messages.');
            }
            return data;
        })
        .then(function(data) {
            if (!data.success || !data.inquiry_id) {
                throw new Error(data.message || 'Unable to open patient messages.');
            }
            try {
                sessionStorage.setItem('inquiryDraft', data.draft || '');
            } catch (e) {}
            window.location.href = 'staff_inquiries.php?open=' + encodeURIComponent(data.inquiry_id);
        })
        .catch(function(err) {
            alert(err.message || 'Unable to open patient messages.');
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Message patient';
            }
        });
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
            // Keep open across background polling refreshes; Cancel/Confirm close it explicitly
            modal.dataset.preserveOpen = '1';
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
                closeCancelReasonModal();
                refreshAppointmentsAjax();
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
        openAppointmentDecisionModal('approve', function() {
            fetch('../shared/approve_appointment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'approve', appointment_id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    refreshAppointmentsAjax();
                    showAppointmentNoticeModal(data.message || 'Appointment approved', { title: 'Appointment approved' });
                } else {
                    showAppointmentNoticeModal(data.message || 'Error approving appointment', { error: true, title: 'Could not approve' });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAppointmentNoticeModal('Something went wrong. Please try again.', { error: true });
            });
        });
    }

    function declineAppointment(id) {
        openAppointmentDecisionModal('decline', function(reason) {
            fetch('../shared/approve_appointment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'decline', appointment_id: id, decline_reason: reason })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    refreshAppointmentsAjax();
                    showAppointmentNoticeModal(data.message || 'Appointment request declined', { title: 'Appointment declined' });
                } else {
                    showAppointmentNoticeModal(data.message || 'Error declining appointment', { error: true, title: 'Could not decline' });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAppointmentNoticeModal('Something went wrong. Please try again.', { error: true });
            });
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
        const button = document.querySelector(`.appt-kebab-btn[data-appt-id="${apptId}"]`);
        const status = (button ? button.getAttribute('data-status') : '').toLowerCase();
        const checkedIn = button ? button.getAttribute('data-checked-in') : '';
        const isPendingApproval = status.includes('pending approval');
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
                Reschedule
            </a>
        `;

        if (!isPendingApproval && !isArrived) {
            menuItems += `<a href="javascript:void(0)" data-action="mark_arrived" data-id="${apptId}" style="color:#374151;"><span aria-hidden="true">✓</span> Mark as Arrived</a>`;
        }
        if (!isPendingApproval) {
            menuItems += `<a href="javascript:void(0)" data-action="cancel" data-id="${apptId}" style="color:#374151;"><span aria-hidden="true">✕</span> Cancel Appointment</a>`;
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
                case 'edit_queue':
                    openQueueEditModal(id);
                    break;
                case 'print_queue':
                    printAppointmentQueueTicket(id);
                    break;
                case 'mark_arrived':
                    markArrived(id);
                    break;
                case 'cancel':
                    openCancelReasonModal(id);
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
        document.getElementById('selectedTimeInput').value = appt.appointment_time ? String(appt.appointment_time).slice(0, 5) : '';
        selectedStaffSlot = null;
        staffRescheduleOriginal = {
            first_name: appt.first_name || '',
            middle_name: appt.middle_name || '',
            last_name: appt.last_name || '',
            email: appt.email || '',
            phone: appt.phone || '',
            appointment_date: appt.appointment_date ? String(appt.appointment_date).split(' ')[0] : '',
            appointment_time: appt.appointment_time ? String(appt.appointment_time).slice(0, 5) : '',
            service_name: appt.service_name || appt.treatment || '',
            dentist_name: appt.dentist_name || 'Any Available Dentist'
        };

        document.querySelector('#appointmentModal h2').textContent = 'Reschedule Appointment';
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
                    if (document.getElementById('appointmentId').value && document.getElementById('selectedTimeInput').value) {
                        document.getElementById('staffScheduleBtn').disabled = false;
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
        openMarkArrivedModal(id);
    }

    let markArrivedId = null;
    let markArrivedQueueId = null;
    let markArrivedPatientName = '';
    let markArrivedTreatment = '';

    function openMarkArrivedModal(id) {
        markArrivedId = id;
        markArrivedQueueId = null;

        if (!document.getElementById('markArrivedModal')) {
            const modal = document.createElement('div');
            modal.id = 'markArrivedModal';
            modal.className = 'modal-overlay';
            // Keep open across background polling refreshes; Done/Cancel close it explicitly
            modal.dataset.preserveOpen = '1';
            modal.style.display = 'none';
            modal.innerHTML = `
                <div class="modal" style="width: 560px;">
                    <div style="margin-bottom: 16px;">
                        <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600;">Mark as Arrived</h2>
                        <p style="margin: 2px 0 0; color: #6b7280; font-size: 0.85rem;">Check-in appointment &amp; add to queue</p>
                    </div>
                    <div id="markArrivedBody">
                        <div id="markArrivedInfo" style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px 16px; margin-bottom: 16px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px;">
                                <span id="markArrivedPatient" style="font-weight: 600; font-size: 1rem;"></span>
                                <span id="markArrivedTime" style="color: #6b7280; font-size: 0.85rem; white-space: nowrap;"></span>
                            </div>
                            <div style="color: #6b7280; font-size: 0.85rem; margin-top: 4px;">Scheduled appointment</div>
                        </div>
                        <p style="color: #374151; margin: 0 0 16px; font-size: 0.9rem;">Mark this patient as arrived? They will be automatically added to today&rsquo;s queue.</p>
                    </div>
                    <div id="markArrivedResult" style="display: none;"></div>
                    <div id="markArrivedActions" style="display: flex; gap: 12px; margin-top: 20px; justify-content: flex-end;">
                        <button onclick="closeMarkArrivedModal()" class="btn-cancel" style="padding: 10px 20px; background: #f3f4f6; border: none; border-radius: 6px; cursor: pointer;">Cancel</button>
                        <button id="markArrivedConfirmBtn" onclick="confirmMarkArrived()" class="btn-primary" style="padding: 10px 20px; background: #059669; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Confirm Mark as Arrived</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeMarkArrivedModal();
            });
        }

        let name = 'This patient';
        let time = '';
        let treatment = '';
        let card = document.querySelector('.appointment-mini-card[data-id="' + id + '"]');
        if (!card) {
            const kebab = document.querySelector('.appt-kebab-btn[data-appt-id="' + id + '"]');
            if (kebab) card = kebab.closest('.appointment-mini-card');
        }
        if (card) {
            const nameEl = card.querySelector('.mini-card-name');
            if (nameEl && nameEl.textContent.trim()) name = nameEl.textContent.trim();
            const timeEl = card.querySelector('.mini-card-time');
            if (timeEl && timeEl.textContent.trim()) time = timeEl.textContent.trim();
            const treatEl = card.querySelector('.mini-card-treatment');
            if (treatEl && treatEl.textContent.trim()) treatment = treatEl.textContent.trim();
        }
        markArrivedPatientName = name;
        markArrivedTreatment = treatment;
        document.getElementById('markArrivedPatient').textContent = name;
        document.getElementById('markArrivedTime').textContent = time;
        document.getElementById('markArrivedBody').style.display = 'block';
        document.getElementById('markArrivedResult').style.display = 'none';
        document.getElementById('markArrivedResult').innerHTML = '';
        const actions = document.getElementById('markArrivedActions');
        actions.style.justifyContent = 'flex-end';
        actions.innerHTML = `
            <button onclick="closeMarkArrivedModal()" class="btn-cancel" style="padding: 10px 20px; background: #f3f4f6; border: none; border-radius: 6px; cursor: pointer;">Cancel</button>
            <button id="markArrivedConfirmBtn" onclick="confirmMarkArrived()" class="btn-primary" style="padding: 10px 20px; background: #059669; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Confirm Mark as Arrived</button>
        `;
        actions.style.display = 'flex';
        document.getElementById('markArrivedModal').style.display = 'flex';
    }

    function closeMarkArrivedModal() {
        const modal = document.getElementById('markArrivedModal');
        if (modal) modal.style.display = 'none';
        markArrivedId = null;
        markArrivedQueueId = null;
        markArrivedPatientName = '';
        markArrivedTreatment = '';
    }

    function confirmMarkArrived() {
        if (!markArrivedId) return;
        const btn = document.getElementById('markArrivedConfirmBtn');
        btn.disabled = true;
        btn.textContent = 'Processing...';
        fetch('../shared/mark_arrived.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ appt_id: markArrivedId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                markArrivedQueueId = data.queue_id || null;
                const queueLabel = data.queue_label || '';
                const msg = data.message || 'Patient marked as arrived';
                document.getElementById('markArrivedBody').style.display = 'none';
                const result = document.getElementById('markArrivedResult');
                result.style.display = 'block';
                result.innerHTML = `
                    <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 10px; padding: 14px 16px; margin-bottom: 16px;">
                        <div style="font-weight: 600; color: #065f46; font-size: 1rem;">${queueLabel ? 'Patient added to queue as ' + queueLabel : msg}</div>
                        <div style="color: #047857; font-size: 0.85rem; margin-top: 2px;">${msg}</div>
                    </div>
                    <div id="markArrivedVitals">
                        ${buildVitalsHtml('markArrived_', {})}
                    </div>
                `;
                const actions = document.getElementById('markArrivedActions');
                actions.style.justifyContent = 'flex-end';
                actions.innerHTML = `
                    <button onclick="printMarkArrivedTicket()" class="btn-cancel" style="padding: 10px 20px; background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; border-radius: 6px; cursor: pointer; font-weight: 600;">🖨 Print Queue No.</button>
                    <button id="markArrivedDoneBtn" onclick="saveMarkArrivedVitals()" class="btn-primary" style="padding: 10px 20px; background: #059669; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Done</button>
                `;
            } else {
                btn.disabled = false;
                btn.textContent = 'Confirm Mark as Arrived';
                const result = document.getElementById('markArrivedResult');
                result.style.display = 'block';
                result.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 8px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 14px; color: #b91c1c; font-size: 0.9rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>${data.message || 'Error marking as arrived'}</span>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            btn.disabled = false;
            btn.textContent = 'Confirm Mark as Arrived';
            const result = document.getElementById('markArrivedResult');
            result.style.display = 'block';
            result.innerHTML = `
                <div style="display: flex; align-items: center; gap: 8px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 14px; color: #b91c1c; font-size: 0.9rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>Error marking as arrived. Please try again.</span>
                </div>
            `;
        });
    }

    function saveMarkArrivedVitals() {
        const btn = document.getElementById('markArrivedDoneBtn');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Saving...';
        }
        if (markArrivedQueueId) {
            const vitals = collectVitals('markArrived_');
            const payload = Object.assign({ queue_id: markArrivedQueueId }, vitals);
            fetch('../shared/save_queue_vitals.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).catch(function() {});
        }
        closeMarkArrivedModal();
        refreshAppointmentsAjax();
    }

    function printMarkArrivedTicket() {
        const now = new Date();
        const dateStr = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
        const timeStr = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
        const label = document.querySelector('#markArrivedResult') ? (document.querySelector('#markArrivedResult').textContent.match(/queue as\s+([#0-9]+)/i) || [])[1] || '' : '';
        printQueueTicket({
            patientName: markArrivedPatientName,
            treatment: markArrivedTreatment,
            queueLabel: label ? '#' + label.replace('#', '') : '',
            date: dateStr,
            time: timeStr
        });
    }

    window.openMarkArrivedModal = openMarkArrivedModal;
    window.closeMarkArrivedModal = closeMarkArrivedModal;
    window.confirmMarkArrived = confirmMarkArrived;
    window.saveMarkArrivedVitals = saveMarkArrivedVitals;
    window.printMarkArrivedTicket = printMarkArrivedTicket;

    function printAppointmentQueueTicket(apptId) {
        const appt = appointments.find(a => String(a.id) === String(apptId));
        if (!appt) return;
        const fn = appt.first_name || '';
        const mn = appt.middle_name || '';
        const ln = appt.last_name || '';
        const name = (fn + ' ' + mn + ' ' + ln).replace(/\s+/g, ' ').trim() || 'Patient';
        printQueueTicket({
            patientName: name,
            treatment: appt.service_name || appt.treatment || '',
            queueLabel: appt.queue_label || (appt.queue_number ? '#' + appt.queue_number : ''),
            date: appt.appointment_date ? String(appt.appointment_date).split(' ')[0] : '',
            time: appt.appointment_time ? String(appt.appointment_time).slice(0, 5) : ''
        });
    }

    let queueEditApptId = null;

    function openQueueEditModal(apptId) {
        const appt = appointments.find(a => String(a.id) === String(apptId));
        if (!appt) return;
        queueEditApptId = apptId;

        const fn = appt.first_name || '';
        const mn = appt.middle_name || '';
        const ln = appt.last_name || '';
        const name = (fn + ' ' + mn + ' ' + ln).replace(/\s+/g, ' ').trim() || 'Patient';

        if (!document.getElementById('queueEditModal')) {
            const modal = document.createElement('div');
            modal.id = 'queueEditModal';
            modal.className = 'modal-overlay';
            modal.style.display = 'none';
            document.body.appendChild(modal);
            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeQueueEditModal();
            });
        }

        const modalEl = document.getElementById('queueEditModal');
        modalEl.innerHTML = `
            <div class="modal" style="width: 620px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600;">Edit Queue - ${escapeHtmlQueue(name)}</h2>
                    <button onclick="closeQueueEditModal()" style="background:none;border:none;font-size:24px;cursor:pointer;color:#6b7280;">&times;</button>
                </div>
                <div style="display:flex;gap:12px;margin-bottom:12px;">
                    <div style="flex:1;"><label style="display:block;font-size:0.8rem;font-weight:600;color:#64748b;margin-bottom:4px;">First Name *</label><input id="queueEditFirstName" type="text" class="form-control" value="${escapeHtmlQueue(fn)}"></div>
                    <div style="flex:1;"><label style="display:block;font-size:0.8rem;font-weight:600;color:#64748b;margin-bottom:4px;">Middle Name</label><input id="queueEditMiddleName" type="text" class="form-control" value="${escapeHtmlQueue(mn)}"></div>
                    <div style="flex:1;"><label style="display:block;font-size:0.8rem;font-weight:600;color:#64748b;margin-bottom:4px;">Last Name *</label><input id="queueEditLastName" type="text" class="form-control" value="${escapeHtmlQueue(ln)}"></div>
                </div>
                <div style="display:flex;gap:12px;margin-bottom:16px;">
                    <div style="flex:1;"><label style="display:block;font-size:0.8rem;font-weight:600;color:#64748b;margin-bottom:4px;">Phone Number</label><input id="queueEditPhone" type="text" class="form-control" value="${escapeHtmlQueue(appt.phone || '')}"></div>
                    <div style="flex:1;"><label style="display:block;font-size:0.8rem;font-weight:600;color:#64748b;margin-bottom:4px;">Email</label><input id="queueEditEmail" type="text" class="form-control" value="${escapeHtmlQueue(appt.email || '')}"></div>
                </div>
                <div style="display:flex;gap:12px;margin-bottom:16px;">
                    <div style="flex:1;"><label style="display:block;font-size:0.8rem;font-weight:600;color:#64748b;margin-bottom:4px;">Treatment *</label><input id="queueEditTreatment" type="text" class="form-control" value="${escapeHtmlQueue(appt.service_name || appt.treatment || '')}" readonly style="background:#f1f5f9;color:#475569;"></div>
                    <div style="flex:1;"><label style="display:block;font-size:0.8rem;font-weight:600;color:#64748b;margin-bottom:4px;">Dentist (optional)</label><select id="queueEditDentist" class="form-control"><option value="">Unassigned</option></select></div>
                </div>
                <div id="queueEditVitals">
                    ${buildVitalsHtml('queueEdit_', {
                        blood_pressure: appt.blood_pressure || '',
                        heart_rate: appt.heart_rate || '',
                        temperature: appt.temperature || '',
                        oxygen_saturation: appt.oxygen_saturation || '',
                        height: appt.height || '',
                        weight: appt.weight || ''
                    })}
                </div>
                <div style="display:flex;gap:12px;margin-top:20px;justify-content:flex-end;">
                    <button onclick="printQueueEditTicket()" class="btn-cancel" style="padding:10px 20px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;border-radius:6px;cursor:pointer;font-weight:600;">🖨 Print Queue #</button>
                    <button onclick="closeQueueEditModal()" class="btn-cancel" style="padding:10px 20px;background:#f3f4f6;border:none;border-radius:6px;cursor:pointer;">Cancel</button>
                    <button id="queueEditSaveBtn" onclick="saveQueueEdit()" class="btn-primary" style="padding:10px 20px;background:#2563eb;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;">Save Changes</button>
                </div>
            </div>
        `;

        loadQueueEditDentists(appt.doctor_id || appt.dentist_id || '');
        document.getElementById('queueEditModal').style.display = 'flex';
    }

    function loadQueueEditDentists(selectedId) {
        const select = document.getElementById('queueEditDentist');
        if (!select) return;
        select.innerHTML = '<option value="">Unassigned</option>';
        fetch('../shared/get_dentists.php')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.dentists) {
                    data.dentists.forEach(function(d) {
                        const opt = document.createElement('option');
                        opt.value = d.id;
                        opt.textContent = 'Dr. ' + d.first_name + ' ' + d.last_name;
                        select.appendChild(opt);
                    });
                }
                if (selectedId) select.value = String(selectedId);
            });
    }

    function closeQueueEditModal() {
        const modal = document.getElementById('queueEditModal');
        if (modal) modal.style.display = 'none';
        queueEditApptId = null;
    }

    function printQueueEditTicket() {
        const appt = appointments.find(a => String(a.id) === String(queueEditApptId));
        if (!appt) return;
        const fn = appt.first_name || '';
        const mn = appt.middle_name || '';
        const ln = appt.last_name || '';
        const name = (fn + ' ' + mn + ' ' + ln).replace(/\s+/g, ' ').trim() || 'Patient';
        printQueueTicket({
            patientName: name,
            treatment: appt.service_name || appt.treatment || '',
            queueLabel: appt.queue_label || (appt.queue_number ? '#' + appt.queue_number : ''),
            date: appt.appointment_date ? String(appt.appointment_date).split(' ')[0] : '',
            time: appt.appointment_time ? String(appt.appointment_time).slice(0, 5) : ''
        });
    }

    function saveQueueEdit() {
        const appt = appointments.find(a => String(a.id) === String(queueEditApptId));
        if (!appt || !appt.queue_id) {
            alert('No queue entry linked to this appointment.');
            return;
        }
        const firstName = document.getElementById('queueEditFirstName').value.trim();
        const lastName = document.getElementById('queueEditLastName').value.trim();
        if (!firstName || !lastName) {
            alert('First Name and Last Name are required.');
            return;
        }
        const btn = document.getElementById('queueEditSaveBtn');
        btn.disabled = true;
        btn.textContent = 'Saving...';
        const payload = {
            queue_id: appt.queue_id,
            first_name: firstName,
            middle_name: document.getElementById('queueEditMiddleName').value.trim(),
            last_name: lastName,
            phone: document.getElementById('queueEditPhone').value.trim(),
            email: document.getElementById('queueEditEmail').value.trim(),
            dentist_id: document.getElementById('queueEditDentist').value || ''
        };
        Object.assign(payload, collectVitals('queueEdit_'));
        fetch('../shared/update_queue_entry.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                closeQueueEditModal();
                refreshAppointmentsAjax();
            } else {
                btn.disabled = false;
                btn.textContent = 'Save Changes';
                alert(data.message || 'Failed to save queue entry.');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Save Changes';
            alert('Failed to save queue entry. Please try again.');
        });
    }

    window.openQueueEditModal = openQueueEditModal;
    window.closeQueueEditModal = closeQueueEditModal;
    window.saveQueueEdit = saveQueueEdit;
    window.printQueueEditTicket = printQueueEditTicket;
    window.printAppointmentQueueTicket = printAppointmentQueueTicket;

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
                    refreshAppointmentsAjax();
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
                    if (typeof refreshAppointmentsAjax === 'function') refreshAppointmentsAjax();
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
<script src="../../assets/js/appointment_ajax_refresh.js?v=2"></script>

<?php require_once '../../includes/staff_layout_end.php'; ?>
