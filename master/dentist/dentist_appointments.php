<?php
$pageTitle = 'Appointments';
require_once '../../config/database.php';

// Auto-cancel no-show trigger on page visit
try {
    require_once __DIR__ . '/../../includes/auto_cancel_helper.php';
    autoCancelNoShows($pdo);
} catch (Exception $e) {
    // Silently fail
}

try {
    require_once __DIR__ . '/../../includes/appointment_reminder_helper.php';
    sendDueAppointmentReminders($pdo);
} catch (Exception $e) {
    // Silently fail
}

// Auto-decline expired pending approval requests
try {
    require_once __DIR__ . '/../../includes/appointment_approval_helper.php';
    autoDeclineExpiredPendingAppointments($pdo);
} catch (Exception $e) {
    // Silently fail
}

require_once '../../includes/dentist_layout_start.php';

$clinicName = 'RF Dental Clinic';
$clinicAddress = '';
try {
    $clinicRows = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('clinic_name', 'clinic_address')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $clinicName = $clinicRows['clinic_name'] ?? $clinicName;
    $clinicAddress = $clinicRows['clinic_address'] ?? '';
} catch (Exception $e) {
    // Settings query is best-effort
}

// Load settings for business hours and unavailable dates
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('business_hours', 'unavailable_dates')");
    $settingsRows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $businessHours = isset($settingsRows['business_hours']) ? json_decode($settingsRows['business_hours'], true) : [];
    $unavailableDates = isset($settingsRows['unavailable_dates']) ? json_decode($settingsRows['unavailable_dates'], true) : [];
    if (!$businessHours) $businessHours = [];
    if (!$unavailableDates) $unavailableDates = [];
} catch (Exception $e) {
    $unavailableDates = [];
}

// Fetch appointment statistics
try {
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN DATE(appointment_date) = CURDATE() THEN 1 ELSE 0 END) as today,
        SUM(CASE WHEN status = 'Completed' AND DATE(appointment_date) = CURDATE() THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'Cancelled' AND DATE(appointment_date) = CURDATE() THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN status = 'Pending Approval' THEN 1 ELSE 0 END) as pending
    FROM appointments");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $totalAppointments = $stats['total'] ?? 0;
    $todayAppointments = $stats['today'] ?? 0;
    $completedAppointments = $stats['completed'] ?? 0;
    $cancelledAppointments = $stats['cancelled'] ?? 0;
    $pendingAppointments = $stats['pending'] ?? 0;
} catch (Exception $e) {
    $totalAppointments = $todayAppointments = $completedAppointments = $cancelledAppointments = $pendingAppointments = 0;
}

// Fetch all appointments with treatment plan info
try {
    $stmt = $pdo->query("SELECT a.*, 
        p.first_name, p.middle_name, p.last_name,
        p.phone, p.email,
        s.name as service_name, s.price as service_price,
        a.status as status_label,
        tp.id as treatment_plan_id, tp.plan_name as treatment_plan_name,
        q.status as queue_status, q.queue_number, q.queue_label
    FROM appointments a 
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN services s ON a.service_id = s.id
    LEFT JOIN treatment_plans tp ON a.id = tp.appointment_id
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
    ORDER BY a.appointment_date ASC, a.appointment_time ASC");
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $appointments = [];
}

// Group appointments into 3 panels: scheduled (left), in queue (right top), pending approval (right bottom)
$sectionLabels = ['scheduled' => 'Scheduled', 'inqueue' => 'In Queue', 'pendingapproval' => 'Pending Approval'];
$sections = ['scheduled' => [], 'inqueue' => [], 'pendingapproval' => []];

function dentistApptDisplayStatus(array $appt): string {
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

function dentistApptPanel(string $displayStatus): string {
    if (strpos($displayStatus, 'Pending Approval') !== false) return 'pendingapproval';
    if ($displayStatus === 'Scheduled') return 'scheduled';
    return 'inqueue';
}

$queuePanelOrder = ['In Procedure' => 0, 'In Queue' => 1, 'Pending Payment' => 2, 'Completed' => 3, 'Cancelled' => 4];

foreach ($appointments as $appt) {
    $displayStatus = dentistApptDisplayStatus($appt);
    $appt['display_status'] = $displayStatus;
    if ($displayStatus === 'Completed' || $displayStatus === 'Cancelled') {
        continue;
    }
    $sections[dentistApptPanel($displayStatus)][] = $appt;
}

// Order the In Queue panel to read like a live board: procedure -> queue -> payment -> done -> cancelled
usort($sections['inqueue'], function ($a, $b) use ($queuePanelOrder) {
    $pa = $queuePanelOrder[$a['display_status']] ?? 9;
    $pb = $queuePanelOrder[$b['display_status']] ?? 9;
    if ($pa !== $pb) return $pa <=> $pb;
    return strcmp((string)($b['appointment_date'] ?? ''), (string)($a['appointment_date'] ?? ''));
});
?>

<!-- Add extra CSS for status badges -->
<style>
.summary-cards {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 24px;
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

.status-badge.status-pending-approval {
    background: #fef3c7;
    color: #b45309;
}

.mini-card-notes {
    font-size: 11px;
    color: #6b7280;
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

.status-badge.status-in-procedure {
    background: #cffafe;
    color: #0e7490;
}
/* Summary cards - light blue dashboard design (matches staff dashboard) */
.summary-cards .summary-card {
    background: #ffffff;
    padding: 22px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 16px;
    border: none;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    min-height: 100px;
    position: relative;
    overflow: hidden;
    transition: transform 0.2s ease;
    cursor: default;
}
.summary-cards .summary-card:hover {
    transform: translateY(-2px);
}
.summary-cards .summary-icon,
.summary-cards .summary-info {
    position: relative;
    z-index: 2;
}
.summary-cards .summary-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: #d3e0fe;
}
.summary-cards .summary-icon img {
    width: 24px;
    height: 24px;
    object-fit: contain;
}
.summary-cards .summary-info h3 {
    font-size: 1.8rem;
    font-weight: 500;
    color: #4b5563;
    line-height: 1;
    margin-bottom: 4px;
}
.summary-cards .summary-info p {
    font-size: 0.9rem;
    color: #6b7280;
    margin: 0;
    font-weight: 500;
}
.summary-cards .summary-card::after {
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
.summary-cards .summary-card.card-total::after {
    background-image: url('../../assets/icons/calendar.png');
}
.summary-cards .summary-card.card-today::after {
    background-image: url('../../assets/icons/today.png');
}
.summary-cards .summary-card.card-pending::after {
    background-image: url('../../assets/icons/pending.png');
}
.summary-cards .summary-card.card-completed::after {
    background-image: url('../../assets/icons/check.png');
}
.summary-cards .summary-card.card-cancelled::after {
    background-image: url('../../assets/icons/delete.png');
}
</style>

<!-- Appointment Stats -->
<div class="summary-cards">
    <div class="summary-card card-total">
        <div class="summary-icon">
            <img src="../../assets/icons/calendar.png" alt="Total Appointments">
        </div>
        <div class="summary-info">
            <h3><?php echo $totalAppointments; ?></h3>
            <p>Total Appointments</p>
        </div>
    </div>
    <div class="summary-card card-pending">
        <div class="summary-icon">
            <img src="../../assets/icons/pending.png" alt="Pending">
        </div>
        <div class="summary-info">
            <h3><?php echo $pendingAppointments; ?></h3>
            <p>Pending</p>
        </div>
    </div>
    <div class="summary-card card-today">
        <div class="summary-icon">
            <img src="../../assets/icons/today.png" alt="Today">
        </div>
        <div class="summary-info">
            <h3><?php echo $todayAppointments; ?></h3>
            <p>Today</p>
        </div>
    </div>
    <div class="summary-card card-completed">
        <div class="summary-icon">
            <img src="../../assets/icons/check.png" alt="Completed">
        </div>
        <div class="summary-info">
            <h3><?php echo $completedAppointments; ?></h3>
            <p>Completed</p>
        </div>
    </div>
    <div class="summary-card card-cancelled">
        <div class="summary-icon">
            <img src="../../assets/icons/delete.png" alt="Cancelled">
        </div>
        <div class="summary-info">
            <h3><?php echo $cancelledAppointments; ?></h3>
            <p>Cancelled</p>
        </div>
    </div>
</div>

<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
    <button class="view-toggle-btn active" data-view="list">📋 List View</button>
    <button class="view-toggle-btn" data-view="calendar">📅 Calendar View</button>
</div>

<style>
.view-toggle-btn { padding: 8px 16px; border: 1px solid #d1d5db; background: white; cursor: pointer; font-size: 14px; font-weight: 500; color: #6b7280; border-radius: 6px; transition: all 0.2s; }
.view-toggle-btn:hover { background: #f3f4f6; }
.view-toggle-btn.active { background: #3f75ea; color: white; border-color: #3f75ea; }
.calendar-view { display: none; }
.calendar-view.active { display: block; }
.list-view { display: block; }
.list-view.hidden { display: none; }
.dentist-cal { background: white; border-radius: 12px; padding: 24px; }
.dentist-cal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.dentist-cal-title { font-size: 20px; font-weight: 700; color: #1a1a2e; }
.dentist-cal-nav { display: flex; gap: 8px; }
.dentist-cal-nav button { background: #f3f4f6; border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: all 0.2s; }
.dentist-cal-nav button:hover { background: #e5e7eb; }
.dentist-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
.dentist-cal-day-header { text-align: center; font-size: 12px; font-weight: 600; color: #6b7280; padding: 8px 0; text-transform: uppercase; }
.dentist-cal-day { min-height: 100px; padding: 8px; border: 1px solid #e5e7eb; border-radius: 8px; background: white; cursor: pointer; transition: all 0.2s; }
.dentist-cal-day:hover { border-color: #3f75ea; background: #f0f7ff; }
.dentist-cal-day.today { background: #dcf0ff; border-color: #3f75ea; }
.dentist-cal-day.empty { background: #ededed; cursor: default; border-color: #f3f4f6; }
.dentist-cal-day.empty:hover { background: #f9fafb; border-color: #f3f4f6; }
.dentist-cal-day.closed { background: #fef2f2; border-color: #fecaca; }
.dentist-cal-day .day-num { font-weight: 500; font-size: 14px; color: #374151; margin-bottom: 4px; }
.dentist-cal-day .appt-count { font-size: 11px; color: #3f75ea; background: #e0f2fe; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-bottom: 4px; }
.dentist-cal-day .appt-mini { font-size: 10px; color: #6b7280; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dentist-cal-day .closed-label { font-size: 11px; color: #dc2626; font-weight: 500; }
.dentist-day-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; justify-content: center; align-items: center; }
.dentist-day-modal.active { display: flex; }
.dentist-day-modal-content { background: white; border-radius: 12px; padding: 24px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; }
.dentist-day-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.dentist-day-modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280; }
.dentist-day-modal-item { padding: 12px; border-bottom: 1px solid #f3f4f6; }
.dentist-day-modal-item:last-child { border-bottom: none; }
</style>

    <div class="list-view" id="listView">
        <div class="search-filters">
            <div class="filter-tabs">
                <span class="active" data-filter="all">All</span>
                <span data-filter="today">Today</span>
                <span data-filter="week">This Week</span>
                <span data-filter="month">This Month</span>
            </div>
            <input type="text" class="search-input" placeholder="Search appointments..." id="appointmentSearch">

        
            
            <div style="display: flex; gap: 10px;">
                <button class="btn-primary" onclick="openNewAppointmentModal()">+ New Appointment</button>
            </div>
    </div>

<?php
$sectionIcons = ['scheduled' => '📅', 'inqueue' => '⏳', 'pendingapproval' => '🕓'];
$sectionClasses = ['scheduled' => 'scheduled', 'inqueue' => 'inqueue', 'pendingapproval' => 'pendingapproval'];
$sectionIds = ['scheduled' => 'countScheduled', 'inqueue' => 'countInqueue', 'pendingapproval' => 'countPendingapproval'];
?>
<div class="status-grid status-grid-3panel">
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
                <?php foreach ($sections[$sec] as $apt): 
                    $fn = htmlspecialchars($apt['first_name'] ?? '');
                    $mn = htmlspecialchars($apt['middle_name'] ?? '');
                    $ln = htmlspecialchars($apt['last_name'] ?? '');
                    $name = trim("$fn $mn $ln") ?: 'Unknown';
                    $time = !empty($apt['appointment_time']) ? date('h:i A', strtotime($apt['appointment_time'])) : '';
                    $date = date('Y-m-d', strtotime($apt['appointment_date']));
                    $dateLabel = date('M j, Y', strtotime($apt['appointment_date']));
                    $treatment = htmlspecialchars($apt['service_name'] ?? 'Consultation');
                    $status = $apt['display_status'] ?? ($apt['status_label'] ?? $apt['status'] ?? 'Scheduled');
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
                     data-id="<?php echo $apt['id']; ?>"
                     data-name="<?php echo strtolower($name); ?>"
                     data-date="<?php echo $date; ?>"
                     data-status="<?php echo strtolower($status); ?>"
                     <?php if ($isLiveQueue): ?>data-live-status="1"<?php endif; ?>>
                    <div class="mini-card-top">
                        <span class="mini-card-name" title="<?php echo $name; ?>"><?php echo $name; ?></span>
                        <span class="mini-card-time"><?php echo $time; ?></span>
                        <div class="appt-kebab-menu" style="flex-shrink:0;">
                            <button type="button" class="appt-kebab-btn" aria-label="Appointment actions" aria-expanded="false" data-appt-id="<?php echo $apt['id']; ?>" data-status="<?php echo htmlspecialchars($status); ?>" data-checked-in="<?php echo $apt['check_in_time'] ?? ''; ?>">
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
                        <span class="mini-card-treatment"><?php echo $treatment; ?></span>
                        <span class="status-badge badge-small <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                    </div>
                    <?php
                    $pendingNotes = trim((string)($apt['notes'] ?? ''));
                    if ($sec === 'pendingapproval' && $pendingNotes !== ''):
                    ?>
                    <div class="mini-card-notes">notes: <?php echo htmlspecialchars($pendingNotes); ?></div>
                    <?php endif; ?>
                    <?php if ($sec === 'pendingapproval'): ?>
                    <div class="appt-approval-actions">
                        <button type="button" class="btn-approve" onclick="approveAppointment(<?php echo (int)$apt['id']; ?>)">✓ Approve</button>
                        <button type="button" class="btn-decline" onclick="declineAppointment(<?php echo (int)$apt['id']; ?>)">✕ Decline</button>
                        <button type="button" class="pa-call" style="width:40px;min-width:40px;padding:8px;border:1px solid #bfdbfe;border-radius:6px;background:#eff6ff;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;" data-patient-name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" data-phone="<?php echo htmlspecialchars($apt['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" onclick="openAppointmentCallModalFromButton(this)" title="View patient phone number" aria-label="View patient phone number"><img src="../../assets/icons/call_button.png" alt="Phone" style="width:18px;height:18px;object-fit:contain;"></button>
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
    <div class="dentist-cal">
        <div class="dentist-cal-header">
            <h3 class="dentist-cal-title" id="dentistCalMonthYear"></h3>
            <div class="dentist-cal-nav">
                <button onclick="changeDentistCalMonth(-1)">&#9664;</button>
                <button onclick="changeDentistCalMonth(1)">&#9654;</button>
            </div>
        </div>
        <div class="dentist-cal-grid" id="dentistCalGrid"></div>
    </div>
</div>

<div class="dentist-day-modal" id="dentistDayModal">
    <div class="dentist-day-modal-content">
        <div class="dentist-day-modal-header">
            <h3 id="dentistDayTitle">Appointments</h3>
            <button class="dentist-day-modal-close" onclick="closeDentistDayModal()">&times;</button>
        </div>
        <div id="dentistDayContent"></div>
    </div>
</div>

<!-- New Appointment Modal -->
<div id="newAppointmentModal" class="modal-overlay" data-preserve-open="1">
    <div class="modal" style="width: 550px; max-height: 90vh; overflow-y: auto;">
        <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Schedule New Appointment</h2>
        <form id="newAppointmentForm">
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>First Name *</label>
                    <input type="text" name="first_name" required class="form-control" placeholder="Enter first name">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" class="form-control" placeholder="Enter middle name">
                </div>
            </div>
            
            <div class="form-group">
                <label>Last Name *</label>
                <input type="text" name="last_name" required class="form-control" placeholder="Enter last name">
            </div>
            
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Phone *</label>
                    <input type="tel" name="phone" required class="form-control" placeholder="e.g., 09123456789">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" placeholder="patient@email.com">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Appointment Date *</label>
                    <input type="date" name="appointment_date" required class="form-control">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Appointment Time *</label>
                    <input type="time" name="appointment_time" required class="form-control">
                </div>
            </div>
            
            <div class="form-group">
                <label>Treatment</label>
                <select name="treatment" class="form-control">
                    <option value="Consultation">Consultation</option>
                    <option value="Teeth Cleaning">Teeth Cleaning</option>
                    <option value="Root Canal">Root Canal</option>
                    <option value="Extraction">Extraction</option>
                    <option value="Filling">Filling</option>
                    <option value="Braces">Braces/Orthodontics</option>
                    <option value="Denture">Denture</option>
                    <option value="Crown & Bridge">Crown & Bridge</option>
                    <option value="Whitening">Whitening</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" rows="3" class="form-control" placeholder="Additional notes about the appointment..."></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" onclick="closeNewAppointmentModal()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-primary">Schedule Appointment</button>
            </div>
        </form>
    </div>
</div>

<!-- View Appointment Modal -->
<div id="viewAppointmentModal" class="modal-overlay" data-preserve-open="1">
    <div class="modal" style="width: 500px;">
        <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Appointment Details</h2>
        <div id="viewAppointmentContent"></div>
        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px;">
            <button onclick="closeViewAppointmentModal()" class="btn-cancel">Close</button>
        </div>
    </div>
</div>

<!-- Edit Appointment Modal -->
<div id="editAppointmentModal" class="modal-overlay" data-preserve-open="1">
    <div class="modal" style="width: 600px; max-height: 90vh; overflow-y: auto;">
        <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Reschedule Appointment</h2>
        <form id="editAppointmentForm">
            <input type="hidden" name="appointment_id" id="edit_appointment_id">
            <input type="hidden" name="appointment_time" id="edit_appointment_time">
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>First Name *</label>
                    <input type="text" name="first_name" id="edit_first_name" required class="form-control" placeholder="Enter first name">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" id="edit_middle_name" class="form-control" placeholder="Enter middle name">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" id="edit_last_name" required class="form-control" placeholder="Enter last name">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Phone *</label>
                    <input type="tel" name="phone" id="edit_phone" required class="form-control" placeholder="e.g., 09123456789">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control" placeholder="patient@email.com">
                </div>
            </div>

            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <div style="font-size: 13px; font-weight: 600; color: #166534; margin-bottom: 12px;">Select Appointment Slot</div>
                <div class="form-row" style="margin-bottom: 12px;">
                    <div class="form-group" style="flex: 1; margin-bottom: 0;">
                        <label>Treatment * <span style="font-size: 11px; color: #6b7280; font-weight: 400;">(select first to set duration)</span></label>
                        <select name="treatment" id="edit_treatment" class="form-control" required>
                            <option value="">-- Select Treatment --</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1; margin-bottom: 0;">
                        <label>Date *</label>
                        <input type="date" name="appointment_date" id="edit_appointment_date" required class="form-control">
                    </div>
                </div>
                <div id="dentistEditSlotGrid" style="margin-top: 8px;">
                    <div style="text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; background: #f8fafc; border-radius: 8px; border: 1px dashed #d1d5db;">
                        Select a treatment and date to see available time slots
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Status</label>
                    <select name="status" id="edit_status" class="form-control">
                        <option value="Scheduled">Scheduled</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" id="edit_notes" rows="3" class="form-control" placeholder="Additional notes about the appointment..."></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" onclick="closeEditAppointmentModal()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-primary" id="editSaveBtn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div id="queuePrintContainer" aria-hidden="true"></div>

<script>
var appointments = <?php echo json_encode($appointments); ?>;
const unavailableDates = <?php echo json_encode($unavailableDates); ?>;

let dentistCalYear, dentistCalMonth;

function initDentistCalendar() {
    const today = new Date();
    dentistCalYear = today.getFullYear();
    dentistCalMonth = today.getMonth();
    renderDentistCalendar();
}

function changeDentistCalMonth(dir) {
    dentistCalMonth += dir;
    if (dentistCalMonth < 0) { dentistCalMonth = 11; dentistCalYear--; }
    if (dentistCalMonth > 11) { dentistCalMonth = 0; dentistCalYear++; }
    renderDentistCalendar();
}

function renderDentistCalendar() {
    document.getElementById('dentistCalMonthYear').textContent = new Date(dentistCalYear, dentistCalMonth).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    
    const grid = document.getElementById('dentistCalGrid');
    grid.innerHTML = '';
    
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    days.forEach(d => {
        const h = document.createElement('div');
        h.className = 'dentist-cal-day-header';
        h.textContent = d;
        grid.appendChild(h);
    });
    
    const firstDay = new Date(dentistCalYear, dentistCalMonth, 1).getDay();
    const totalDays = new Date(dentistCalYear, dentistCalMonth + 1, 0).getDate();
    const prevDays = new Date(dentistCalYear, dentistCalMonth, 0).getDate();
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    for (let i = firstDay - 1; i >= 0; i--) {
        const d = document.createElement('div');
        d.className = 'dentist-cal-day empty';
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
        const dateStr = `${dentistCalYear}-${String(dentistCalMonth + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
        const dateObj = new Date(dentistCalYear, dentistCalMonth, i);
        
        d.className = 'dentist-cal-day';
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
                        const isTP = appt.treatment_plan_id ? ' <span style="color: #92400e; font-size: 0.7rem;">📋</span>' : '';
                        html += `<div class="appt-mini">${time} - ${name.trim()}${isTP}</div>`;
                    });
                    if (appts.length > 3) {
                        html += `<div class="appt-mini">+${appts.length - 3} more</div>`;
                    }
                }
            d.innerHTML = html;
        }
        
        d.addEventListener('click', () => openDentistDayModal(dateStr, apptByDate[dateStr] || [], closedEntry));
        grid.appendChild(d);
    }
    
    const remaining = 42 - (firstDay + totalDays);
    for (let i = 1; i <= remaining; i++) {
        const d = document.createElement('div');
        d.className = 'dentist-cal-day empty';
        d.textContent = i;
        grid.appendChild(d);
    }
}

function openDentistDayModal(dateStr, appts, closedEntry) {
    const formatted = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    document.getElementById('dentistDayTitle').textContent = formatted;
    
    const content = document.getElementById('dentistDayContent');
    
    if (closedEntry && closedEntry.status === 'closed') {
        content.innerHTML = `<p style="color: #dc2626; font-weight: 500;">Clinic is closed${closedEntry.note ? ' - ' + closedEntry.note : ''}</p>`;
    } else if (appts.length === 0) {
        content.innerHTML = '<p style="color: #6b7280;">No appointments for this day</p>';
    } else {
        let html = '';
        appts.forEach(appt => {
            const time = appt.appointment_time ? new Date('1970-01-01 ' + appt.appointment_time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : 'N/A';
            const name = [appt.first_name, appt.middle_name, appt.last_name].filter(n => n).join(' ') || 'Unknown';
            const status = appt.status || appt.status_label || 'Scheduled';
            const isTreatmentPlan = appt.treatment_plan_id ? true : false;
            html += `<div class="dentist-day-modal-item">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: 500;">${name}${isTreatmentPlan ? ' <span style="background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 9999px; font-size: 0.7rem;">📋 Treatment Plan</span>' : ''}</div>
                        <div style="font-size: 14px; color: #6b7280;">${time} - ${appt.service_name || appt.treatment || 'General'}</div>
                        ${isTreatmentPlan ? `<div style="font-size: 12px; color: #92400e; margin-top: 2px;">Plan: ${appt.treatment_plan_name || ''}</div>` : ''}
                    </div>
                    <span class="status-badge" style="background: ${status === 'Completed' ? '#d1fae5' : status === 'Cancelled' ? '#fee2e2' : status.toLowerCase() === 'arrived' ? '#ccfbf1' : '#e0f2fe'}; color: ${status === 'Completed' ? '#065f46' : status === 'Cancelled' ? '#dc2626' : status.toLowerCase() === 'arrived' ? '#0f766e' : '#1d4ed8'};">${status}</span>
                </div>
            </div>`;
        });
        content.innerHTML = html;
    }
    
    document.getElementById('dentistDayModal').classList.add('active');
}

function closeDentistDayModal() {
    document.getElementById('dentistDayModal').classList.remove('active');
}

document.addEventListener('click', function(e) {
    if (e.target === document.getElementById('dentistDayModal')) {
        closeDentistDayModal();
    }
});

// Portal Pattern: Move modal to body level so they survive
// refreshAppointmentsAjax(), which replaces .content-main (and would otherwise
// destroy the submit/change listeners attached inside these modals).
window.__portaledDentistApptModals = {};
document.addEventListener('DOMContentLoaded', function() {
    ['newAppointmentModal', 'editAppointmentModal'].forEach(function(id) {
        var modal = document.getElementById(id);
        if (modal) {
            document.body.appendChild(modal);
            window.__portaledDentistApptModals[id] = modal;
        }
    });

    // After an AJAX refresh the re-rendered .content-main contains fresh copies
    // of these modals; remove the duplicates so getElementById keeps resolving
    // to the persistent (listener-bound) portaled nodes.
    function removeDuplicateDentistPortaledModals() {
        var map = window.__portaledDentistApptModals || {};
        Object.keys(map).forEach(function(id) {
            var keep = map[id];
            if (!keep || !keep.isConnected) return;
            document.querySelectorAll('[id="' + id + '"]').forEach(function(node) {
                if (node !== keep && node.parentNode) node.parentNode.removeChild(node);
            });
        });
    }

    if (typeof window.refreshAppointmentsAjax === 'function') {
        var origDentistRefresh = window.refreshAppointmentsAjax;
        window.refreshAppointmentsAjax = function() {
            var result = origDentistRefresh.apply(this, arguments);
            Promise.resolve(result).then(removeDuplicateDentistPortaledModals);
            return result;
        };
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const newModal = document.getElementById('newAppointmentModal');

    // Close new modal when clicking outside
    if (newModal) {
        newModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeNewAppointmentModal();
            }
        });
    }
    
    // Filter tabs click handler
    document.querySelectorAll('.filter-tabs span').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.filter-tabs span').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            filterAppointments();
        });
    });
    
    // Search input handler
    const searchInput = document.getElementById('appointmentSearch');
    if (searchInput) {
        searchInput.addEventListener('input', filterAppointments);
    }
    
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
                initDentistCalendar();
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

        const counts = { scheduled: 0, inqueue: 0, pendingapproval: 0 };

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

        document.getElementById('countScheduled').textContent = counts.scheduled;
        document.getElementById('countInqueue').textContent = counts.inqueue;
        document.getElementById('countPendingapproval').textContent = counts.pendingapproval;
    }

    function getStatusBadgeClass(status) {
        const s = (status || '').toLowerCase();
        if (s.includes('completed')) return 'status-completed';
        if (s.includes('cancel')) return 'status-cancelled';
        if (s.includes('queue')) return 'status-in-queue';
        if (s.includes('pending')) return 'status-pending';
        return 'status-scheduled';
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

    function openNewAppointmentModal() {
    document.getElementById('newAppointmentForm').reset();
    
    // Set default date to today
    const today = new Date().toISOString().split('T')[0];
    document.querySelector('input[name="appointment_date"]').value = today;
    
    // Set default time
    document.querySelector('input[name="appointment_time"]').value = '09:00';
    
    document.getElementById('newAppointmentModal').style.display = 'flex';
}

function closeNewAppointmentModal() {
    document.getElementById('newAppointmentModal').style.display = 'none';
}

// Handle form submission
document.getElementById('newAppointmentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('process_dentist_appointment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Appointment scheduled successfully!');
            closeNewAppointmentModal();
            refreshAppointmentsAjax();
        } else {
            alert(data.message || 'Error scheduling appointment');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error scheduling appointment. Please try again.');
    });
});

// ESC key to close modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeNewAppointmentModal();
    }
});

// View Appointment function
function escapeHtml(str) {
    if (str == null || str === '') return '';
    const d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
}

function viewAppointment(id) {
    const appointment = appointments.find(a => a.id == id);
    if (!appointment) return;
    
    const appointmentDate = new Date(appointment.appointment_date);
    const dateStr = appointmentDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    const timeStr = appointmentDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    
    const eh = function(v) { return escapeHtml(v == null ? '' : String(v)); };
    document.getElementById('viewAppointmentContent').innerHTML = `
        <div style="display: flex; flex-direction: column; gap: 16px;">
            <div><span style="color: #6b7280;">Patient:</span> 
                <span style="font-weight: 600; margin-left: 8px;">${eh(appointment.first_name)}</span>
                ${appointment.middle_name ? `<span style="color: #6b7280; margin-left: 4px;">${eh(appointment.middle_name)}</span>` : ''}
                <span style="font-weight: 600; margin-left: 4px;">${eh(appointment.last_name)}</span>
            </div>
            <div><span style="color: #6b7280;">Phone:</span> <span style="font-weight: 500; margin-left: 8px;">${eh(appointment.phone || 'N/A')}</span></div>
            <div><span style="color: #6b7280;">Date:</span> <span style="font-weight: 500; margin-left: 8px;">${dateStr}</span></div>
            <div><span style="color: #6b7280;">Time:</span> <span style="font-weight: 500; margin-left: 8px;">${timeStr}</span></div>
            <div><span style="color: #6b7280;">Treatment:</span> <span style="font-weight: 500; margin-left: 8px;">${eh(appointment.service_name || appointment.treatment || 'Consultation')}</span></div>
            <div><span style="color: #6b7280;">Status:</span> <span class="status-badge ${getStatusBadgeClass(appointment.status)}" style="margin-left: 8px;">${eh(appointment.status || 'Pending')}</span></div>
            ${appointment.notes ? `<div><span style="color: #6b7280;">Notes:</span><p style="background: #f9fafb; padding: 12px; border-radius: 8px; margin: 8px 0 0;">${eh(appointment.notes)}</p></div>` : ''}
        </div>
    `;
    
    document.getElementById('viewAppointmentModal').style.display = 'flex';
}

var selectedDentistEditSlot = null;

function loadDentistEditServices(callback) {
    fetch('../shared/get_services.php')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var select = document.getElementById('edit_treatment');
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
        })
        .catch(function() {
            if (typeof callback === 'function') callback();
        });
}

function formatDentistSlotTime(t) {
    var parts = t.split(':');
    var h = parseInt(parts[0]);
    var m = parts[1];
    var ampm = h >= 12 ? 'PM' : 'AM';
    if (h > 12) h -= 12;
    if (h === 0) h = 12;
    return h + ':' + m + ' ' + ampm;
}

function loadDentistEditSlots(callback) {
    var serviceId = document.getElementById('edit_treatment').value;
    var date = document.getElementById('edit_appointment_date').value;
    var grid = document.getElementById('dentistEditSlotGrid');
    var btn = document.getElementById('editSaveBtn');

    if (!serviceId || !date) {
        grid.innerHTML = '<div style="text-align: center; padding: 20px; color: #94a3b8; font-size: 13px; background: #f8fafc; border-radius: 8px; border: 1px dashed #d1d5db;">Select a treatment and date to see available time slots</div>';
        btn.disabled = true;
        if (typeof callback === 'function') callback();
        return;
    }

    selectedDentistEditSlot = null;
    btn.disabled = true;
    grid.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading available slots...</div>';

    // Exclude this appointment itself so its current time stays selectable
    var url = '../user-patient/get_slots.php?date=' + date + '&service_id=' + serviceId;
    var appointmentId = document.getElementById('edit_appointment_id').value;
    if (appointmentId) url += '&exclude_appointment_id=' + encodeURIComponent(appointmentId);

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
            legend.innerHTML = '<span style="color:#15803d;"><span style="background:#dcfce7;border:2px solid #15803d;display:inline-block;width:14px;height:14px;border-radius:3px;margin-right:4px;vertical-align:middle;"></span> Available</span><span style="color:#991b1b;"><span style="background:#fee2e2;border:2px solid #991b1b;display:inline-block;width:14px;height:14px;border-radius:3px;margin-right:4px;vertical-align:middle;"></span> Booked / Conflicts with treatment duration</span>';
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
                s.textContent = formatDentistSlotTime(slot);

                if (!isBooked) {
                    s.dataset.time = slot;
                    s.onclick = function() {
                        document.querySelectorAll('#dentistEditSlotGrid .slot-item').forEach(function(el) {
                            el.style.borderColor = '#bbf7d0';
                            el.style.background = '#f0fdf4';
                        });
                        this.style.borderColor = '#15803d';
                        this.style.background = '#bbf7d0';
                        selectedDentistEditSlot = slot;
                        document.getElementById('edit_appointment_time').value = slot;
                        document.getElementById('editSaveBtn').disabled = false;
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

// Slots must react whenever the treatment (duration) or date changes
document.getElementById('edit_treatment').addEventListener('change', loadDentistEditSlots);
document.getElementById('edit_appointment_date').addEventListener('change', loadDentistEditSlots);

function editAppointment(id) {
    const appointment = appointments.find(a => a.id == id);
    if (!appointment) return;

    // Fill patient details
    document.getElementById('edit_appointment_id').value = appointment.id;
    document.getElementById('edit_first_name').value = appointment.first_name || '';
    document.getElementById('edit_middle_name').value = appointment.middle_name || '';
    document.getElementById('edit_last_name').value = appointment.last_name || '';
    document.getElementById('edit_phone').value = appointment.phone || '';
    document.getElementById('edit_email').value = appointment.email || '';
    document.getElementById('edit_notes').value = appointment.notes || '';

    // Keep original time as fallback so Save stays possible even if the
    // current slot can no longer be auto-selected from the grid.
    document.getElementById('edit_appointment_time').value = appointment.appointment_time ? String(appointment.appointment_time).slice(0, 5) : '';
    selectedDentistEditSlot = null;

    // Status: normalize to the DB enum values used by the dropdown
    var statusSelect = document.getElementById('edit_status');
    var rawStatus = String(appointment.status || appointment.status_label || 'Scheduled');
    var normalized = rawStatus.charAt(0).toUpperCase() + rawStatus.slice(1).toLowerCase();
    var match = Array.prototype.find.call(statusSelect.options, function(o) { return o.value.toLowerCase() === normalized.toLowerCase(); });
    if (match) {
        statusSelect.value = match.value;
    } else {
        // Preserve an unexpected existing status instead of silently changing it
        var opt = document.createElement('option');
        opt.value = rawStatus;
        opt.textContent = rawStatus;
        statusSelect.appendChild(opt);
        statusSelect.value = rawStatus;
    }

    // Load services, set treatment/date, then render slots
    loadDentistEditServices(function() {
        var svcSelect = document.getElementById('edit_treatment');
        if (appointment.service_id) {
            svcSelect.value = appointment.service_id;
        } else {
            var nameKey = (appointment.service_name || appointment.treatment || '').toLowerCase();
            for (var i = 0; i < svcSelect.options.length; i++) {
                if (nameKey && svcSelect.options[i].text.toLowerCase().indexOf(nameKey) !== -1) {
                    svcSelect.value = svcSelect.options[i].value;
                    break;
                }
            }
        }

        document.getElementById('edit_appointment_date').value = appointment.appointment_date ? String(appointment.appointment_date).split(' ')[0] : '';

        closeApptKebabDropdown();
        document.getElementById('editAppointmentModal').style.display = 'flex';

        loadDentistEditSlots(function() {
            var currentTime = appointment.appointment_time ? String(appointment.appointment_time).slice(0, 5) : '';
            if (currentTime) {
                var slotItems = document.querySelectorAll('#dentistEditSlotGrid .slot-item');
                for (var i = 0; i < slotItems.length; i++) {
                    if (slotItems[i].dataset.time === currentTime) {
                        slotItems[i].click();
                        break;
                    }
                }
            }
            // Fallback: allow saving with the original time when the slot could
            // not be auto-selected (e.g. it now overlaps another booking).
            if (document.getElementById('edit_appointment_id').value && document.getElementById('edit_appointment_time').value) {
                document.getElementById('editSaveBtn').disabled = false;
            }
        });
    });
}

function closeEditAppointmentModal() {
    document.getElementById('editAppointmentModal').style.display = 'none';
}

// Handle edit form submission
document.getElementById('editAppointmentForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    formData.append('action', 'edit');
    formData.append('id', document.getElementById('edit_appointment_id').value);

    // Send the selected service so the backend stores the correct service_id
    var serviceId = document.getElementById('edit_treatment').value;
    if (serviceId) formData.set('service_id', serviceId);

    // Use the picked slot; fall back to the preserved original time
    if (selectedDentistEditSlot) {
        formData.set('appointment_time', selectedDentistEditSlot);
    }
    if (!formData.get('appointment_time')) {
        alert('Please select an appointment time slot.');
        return;
    }

    fetch('process_dentist_appointment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Appointment updated successfully!');
            closeEditAppointmentModal();
            refreshAppointmentsAjax();
        } else {
            alert(data.message || 'Error updating appointment');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating appointment. Please try again.');
    });
});

function closeViewAppointmentModal() {
    document.getElementById('viewAppointmentModal').style.display = 'none';
}

function viewAppointmentDetails(id) {
    // Close kebab dropdown first
    if (typeof closeApptKebabDropdown === 'function') {
        closeApptKebabDropdown();
    }
    
    // Get appointment data from mini-card data attributes
    const card = document.querySelector(`.appointment-mini-card[data-id="${id}"]`);
    if (!card) {
        alert('Appointment not found');
        return;
    }
    
    const nameEl = card.querySelector('.mini-card-name');
    const nameText = nameEl ? nameEl.textContent.trim() : '';
    const nameParts = nameText.split(/\s+/);
    const firstName = nameParts[0] || '';
    const lastName = nameParts.length > 1 ? nameParts[nameParts.length - 1] : '';
    const middleName = nameParts.length > 2 ? nameParts.slice(1, -1).join(' ') : '';
    const phone = card.getAttribute('data-phone') || 'N/A';
    const date = card.dataset.date || '';
    const timeEl = card.querySelector('.mini-card-time');
    const time = timeEl ? timeEl.textContent.trim() : '';
    const treatmentEl = card.querySelector('.mini-card-treatment');
    const treatment = treatmentEl ? treatmentEl.textContent.trim() : 'Consultation';
    const statusRaw = card.dataset.status || 'scheduled';
    const status = statusRaw.charAt(0).toUpperCase() + statusRaw.slice(1);
    const notes = card.getAttribute('data-notes') || '';
    
    const appointmentDate = new Date(date + ' ' + time);
    const dateStr = appointmentDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    const timeStr = appointmentDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    
    let statusBg, statusColor;
    switch(status) {
        case 'Completed':
            statusBg = '#d1fae5'; statusColor = '#065f46'; break;
        case 'Cancelled':
            statusBg = '#fee2e2'; statusColor = '#991b1b'; break;
        case 'In Queue':
            statusBg = '#fef3c7'; statusColor = '#92400e'; break;
        case 'Pending Payment':
            statusBg = '#fed7aa'; statusColor = '#c2410c'; break;
        case 'arrived':
            statusBg = '#ccfbf1'; statusColor = '#0f766e'; break;
        default:
            statusBg = '#e0f2fe'; statusColor = '#0369a1';
    }
    
    // Store current values for editing
    let currentAmount = 0;
    let currentPaymentStatus = 'unpaid';
    let isEditing = false;
    const defaultPrice = 0;
    
    // Always fetch fresh price from services table (reflects admin changes)
    fetch('../shared/billing_actions.php?action=get_details&appointment_id=' + id)
    .then(r => r.json())
    .then(billingData => {
        console.log('BillingData:', billingData);
        // Use the amount from billing_actions.php (fetches from services table)
        if (billingData.success && billingData.amount) {
            currentAmount = parseFloat(billingData.amount);
        } else {
            // Fallback to row price if fetch fails
            currentAmount = defaultPrice > 0 ? defaultPrice : getDefaultTreatmentPrice(treatment);
        }
        currentPaymentStatus = billingData.payment_status || 'unpaid';
        renderModal();
    })
    .catch(error => {
        console.error('Error:', error);
        currentAmount = defaultPrice > 0 ? defaultPrice : getDefaultTreatmentPrice(treatment);
        currentPaymentStatus = 'unpaid';
        renderModal();
    });
    
    function getDefaultTreatmentPrice(treat) {
        // Default prices for common treatments
        const defaultPrices = {
            'consultation': 500,
            'checkup': 500,
            'cleaning': 1500,
            'whitening': 5000,
            'extraction': 1000,
            'filling': 1500,
            'root canal': 8000,
            'crown': 15000,
            'braces': 50000,
            'implant': 25000
        };
        const lower = (treat || 'consultation').toLowerCase();
        return defaultPrices[lower] || 500;
    }
    
    function renderModal() {
        const amountDisplay = currentAmount.toLocaleString('en-PH', { minimumFractionDigits: 2 });
        const paymentBg = currentPaymentStatus === 'paid' ? '#d1fae5' : '#fef3c7';
        const paymentColor = currentPaymentStatus === 'paid' ? '#065f46' : '#92400e';
        
        const editSection = isEditing ? `
            <div style="background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; padding: 12px;">
                <div style="margin-bottom: 12px;">
                    <span style="color: #92400e; font-weight: 600;">Adjust Amount:</span>
                    <input type="number" id="editAmountInput" value="${currentAmount}" style="width: 100%; padding: 8px; border: 2px solid #f59e0b; border-radius: 6px; font-size: 1rem; margin-top: 4px;" step="0.01" min="0">
                </div>
                <div style="display: flex; gap: 8px;">
                    <button onclick="saveAppointmentAmount(${id}, ${currentAmount})" style="background: #059669; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600;">Save</button>
                    <button onclick="renderModal()" style="background: #6b7280; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;">Cancel</button>
                </div>
            </div>
        ` : `
            <div style="background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; padding: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #92400e; font-weight: 600;">Unpaid Amount:</span>
                    <span style="font-size: 1.25rem; font-weight: 700; color: #111827;">₱${amountDisplay}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px;">
                    <span style="color: #92400e;">Payment Status:</span>
                    <span class="status-badge" style="background: ${paymentBg}; color: ${paymentColor}; padding: 4px 12px; border-radius: 9999px; font-size: 0.875rem; text-transform: uppercase;">${currentPaymentStatus}</span>
                </div>
                <div style="display: flex; gap: 8px; margin-top: 12px;">
                    <button onclick="toggleEditAmount()" style="background: #f59e0b; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600;">Adjust Amount</button>
                    ${currentPaymentStatus !== 'paid' ? `<button onclick="markAppointmentPaid(${id})" style="background: #059669; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600;">Mark as Paid</button>` : ''}
                </div>
            </div>
        `;
        
        const eh = function(v) { return escapeHtml(v == null ? '' : String(v)); };
        document.getElementById('viewAppointmentContent').innerHTML = `
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div><span style="color: #6b7280;">Patient:</span> 
                    <span style="font-weight: 600; margin-left: 8px;">${eh(firstName)}</span>
                    ${middleName && middleName !== '-' ? `<span style="color: #6b7280; margin-left: 4px;">${eh(middleName)}</span>` : ''}
                    <span style="font-weight: 600; margin-left: 4px;">${eh(lastName)}</span>
                </div>
                <div><span style="color: #6b7280;">Phone:</span> <span style="font-weight: 500; margin-left: 8px;">${eh(phone)}</span></div>
                <div><span style="color: #6b7280;">Date:</span> <span style="font-weight: 500; margin-left: 8px;">${dateStr}</span></div>
                <div><span style="color: #6b7280;">Time:</span> <span style="font-weight: 500; margin-left: 8px;">${timeStr}</span></div>
                <div><span style="color: #6b7280;">Treatment:</span> <span style="font-weight: 500; margin-left: 8px;">${eh(treatment)}</span></div>
                ${editSection}
                <div><span style="color: #6b7280;">Status:</span> <span class="status-badge" style="margin-left: 8px; background: ${statusBg}; color: ${statusColor}; padding: 4px 12px; border-radius: 9999px; font-size: 0.875rem;">${eh(status)}</span></div>
                ${notes ? `<div><span style="color: #6b7280;">Notes:</span><p style="background: #f9fafb; padding: 12px; border-radius: 8px; margin: 8px 0 0;">${eh(notes)}</p></div>` : '<div><span style="color: #6b7280;">Notes:</span><p style="background: #f9fafb; padding: 12px; border-radius: 8px; margin: 8px 0 0; color: #9ca3af;">No notes</p></div>'}
            </div>
        `;
        
        document.getElementById('viewAppointmentModal').style.display = 'flex';
    }
    
    window.toggleEditAmount = function() {
        isEditing = true;
        renderModal();
    };
    
    window.saveAppointmentAmount = function(apptId, oldAmount) {
        const newAmount = parseFloat(document.getElementById('editAmountInput').value) || 0;
        
        fetch('../shared/billing_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_amount',
                appointment_id: apptId,
                new_amount: newAmount,
                reason: 'Dentist adjustment'
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Amount updated to ₱' + newAmount.toLocaleString('en-PH', { minimumFractionDigits: 2 }));
                isEditing = false;
                currentAmount = newAmount;
                renderModal();
            } else {
                alert('Error: ' + (data.message || 'Failed to save'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error saving amount');
        });
    };
    
    window.markAppointmentPaid = function(apptId) {
        if (!confirm('Mark this appointment as paid for ₱' + currentAmount.toLocaleString('en-PH', { minimumFractionDigits: 2 }) + '?')) return;
        
        fetch('../shared/billing_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'mark_paid',
                appointment_id: apptId,
                amount: currentAmount,
                treatment: treatment
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Payment marked as paid!');
                currentPaymentStatus = 'paid';
                renderModal();
                refreshAppointmentsAjax();
            } else {
                alert('Error: ' + (data.message || 'Failed to mark as paid'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error marking as paid');
        });
    };
}

// Close view modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const viewModal = document.getElementById('viewAppointmentModal');
    if (viewModal) {
        viewModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeViewAppointmentModal();
            }
        });
    }
    
    // Portal Pattern: Move modals to body level
    const newModal = document.getElementById('newAppointmentModal');
    if (newModal) {
        document.body.appendChild(newModal);
    }
    const viewModalPortal = document.getElementById('viewAppointmentModal');
    if (viewModalPortal) {
        document.body.appendChild(viewModalPortal);
    }
});

// Appointment status auto-polling
var apptPollHashDentist = '';

function startAppointmentPolling() {
    setInterval(function() {
        fetch('../shared/appointments_poll.php')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) return;
                if (data.hash !== apptPollHashDentist) {
                    apptPollHashDentist = data.hash;
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
            badge.className = 'status-badge badge-small ' + getDentistApptBadgeClass(newStatus);
        }
    }
}

function getDentistApptBadgeClass(status) {
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
</script>

<!-- Kebab Menu Functions -->
<script>
// The sidebar router runs DOMContentLoaded callbacks immediately. Initialize
// menu state first so both sidebar navigation and a full page load can bind it.
var apptKebabDropdown = null;
var apptKebabBackdrop = null;
var apptActiveButton = null;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize kebab menu
    initApptKebabMenu();
});

function initApptKebabMenu() {
    closeApptKebabDropdown();
    apptKebabDropdown = document.getElementById('apptKebabDropdown') || document.createElement('div');
    apptKebabDropdown.className = 'appt-kebab-dropdown-portal';
    apptKebabDropdown.id = 'apptKebabDropdown';
    document.body.appendChild(apptKebabDropdown);
    
    apptKebabBackdrop = document.getElementById('apptKebabBackdrop') || document.createElement('div');
    apptKebabBackdrop.className = 'appt-kebab-backdrop';
    apptKebabBackdrop.id = 'apptKebabBackdrop';
    document.body.appendChild(apptKebabBackdrop);
    
    apptKebabBackdrop.onclick = closeApptKebabDropdown;
    
    // Click handler for kebab buttons
    if (window.__dentistApptKebabClickHandler) {
        document.removeEventListener('click', window.__dentistApptKebabClickHandler);
    }
    window.__dentistApptKebabClickHandler = function(e) {
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
    };
    document.addEventListener('click', window.__dentistApptKebabClickHandler);
}

function openApptKebabDropdown(button) {
    const apptId = button.getAttribute('data-appt-id');
    const status = (button.getAttribute('data-status') || '').toLowerCase();
    const checkedIn = button.getAttribute('data-checked-in') || '';
    const isPendingApproval = status.includes('pending approval');
    const isArrived = checkedIn.trim() !== '';

    let html = `
        <a href="javascript:void(0)" onclick="viewAppointmentDetails(${apptId})">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            View Details
        </a>
        <a href="javascript:void(0)" onclick="editAppointment(${apptId})">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
            Reschedule
        </a>
    `;

    if (!isPendingApproval && !isArrived) {
        html += `<a href="javascript:void(0)" onclick="markArrived(${apptId})" style="color:#374151;"><span aria-hidden="true">✓</span> Mark as Arrived</a>`;
    }
    if (!isPendingApproval) {
        html += `<a href="javascript:void(0)" onclick="openCancelReasonModal(${apptId})" style="color:#374151;"><span aria-hidden="true">✕</span> Cancel Appointment</a>`;
    }

    apptKebabDropdown.innerHTML = html;
    
    // Measure the visible menu so it stays inside the viewport on phones too.
    apptKebabDropdown.classList.add('show');
    const rect = button.getBoundingClientRect();
    const menuRect = apptKebabDropdown.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const padding = 12;
    
    let left = rect.right + 8;
    let top = rect.top;
    
    if (left + menuRect.width > viewportWidth - padding) {
        left = rect.left - menuRect.width - 8;
    }
    left = Math.max(padding, Math.min(left, viewportWidth - padding - menuRect.width));
    top = Math.max(padding, Math.min(top, viewportHeight - padding - menuRect.height));
    
    apptKebabDropdown.style.left = left + 'px';
    apptKebabDropdown.style.top = top + 'px';
    
    apptKebabDropdown.classList.add('show');
    apptKebabBackdrop.classList.add('show');
    apptActiveButton = button;
    button.classList.add('active');
    button.setAttribute('aria-expanded', 'true');
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
        apptActiveButton.setAttribute('aria-expanded', 'false');
        apptActiveButton = null;
    }
}

    function markArrived(id) {
        closeApptKebabDropdown();
        openMarkArrivedModal(id);
    }

    const clinicPrintInfo = {
        name: <?php echo json_encode($clinicName); ?>,
        address: <?php echo json_encode($clinicAddress); ?>
    };

    function printQueueTicket(opts) {
        var o = opts || {};
        var container = document.getElementById('queuePrintContainer');
        if (!container) return;
        var now = new Date();
        var dateStr = o.date || (now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0'));
        var timeStr = o.time || (String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0'));
        var number = (o.queueNumber || o.queueLabel || '').replace('#', '');
        var label = o.queueLabel || (number ? '#' + number : '');
        var LINE = '----------------------------------------';
        var esc = function(str) {
            return String(str == null ? '' : str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        };
        container.innerHTML =
            '<div class="queue-ticket">' +
                '<div class="qt-line">' + LINE + '</div>' +
                '<div class="qt-clinic">' + esc(clinicPrintInfo.name || 'RF Dental Clinic') + '</div>' +
                (clinicPrintInfo.address ? '<div class="qt-address">' + esc(clinicPrintInfo.address) + '</div>' : '') +
                '<div class="qt-label">Queue Number</div>' +
                '<div class="qt-number">' + esc(label) + '</div>' +
                '<div class="qt-line">' + LINE + '</div>' +
                '<div class="qt-meta">Patient: ' + esc(o.patientName || '') + '</div>' +
                '<div class="qt-meta">Treatment: ' + esc(o.treatment || '') + '</div>' +
                '<div class="qt-meta">Date: ' + esc(dateStr) + '  Time: ' + esc(timeStr) + '</div>' +
                '<div class="qt-line">' + LINE + '</div>' +
            '</div>';
        document.body.appendChild(container);
        if (window.rfLogPrint) window.rfLogPrint('Queue Ticket', 'Queue ' + label + ' - ' + (o.patientName || ''), 'queue', o.queueId || null);
        window.print();
    }

    let markArrivedId = null;
    let markArrivedQueueId = null;
    let markArrivedPatientName = '';
    let markArrivedTreatment = '';

    function buildVitalsHtml(prefix, values) {
        var v = values || {};
        var field = function(name, label, placeholder) {
            return '<div><label>' + label + '</label><input type="text" name="' + prefix + name + '" placeholder="' + placeholder + '" value="' + escapeHtml(v[name] || '') + '"></div>';
        };
        return '' +
            '<div class="vitals-section">' +
                '<h4>Vital Signs (optional)</h4>' +
                '<p class="vitals-sub">Blood pressure, heart rate, temperature, oxygen saturation</p>' +
                '<div class="vitals-grid">' +
                    field('blood_pressure', 'Blood Pressure (mmHg)', '120/80') +
                    field('heart_rate', 'Heart Rate (bpm)', '72') +
                    field('temperature', 'Temperature (&deg;C)', '36.5') +
                    field('oxygen_saturation', 'Oxygen Saturation (%)', '98') +
                '</div>' +
            '</div>' +
            '<div class="vitals-section">' +
                '<h4>Body Measurement (optional)</h4>' +
                '<p class="vitals-sub">Height, weight, and computed BMI</p>' +
                '<div class="vitals-grid">' +
                    field('height', 'Height (cm)', '165') +
                    field('weight', 'Weight (kg)', '58') +
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

    function openMarkArrivedModal(id) {
        markArrivedId = id;
        markArrivedQueueId = null;

        const staleMarkArrived = document.getElementById('markArrivedModal');
        if (staleMarkArrived && staleMarkArrived.dataset.palette !== 'edit-info') staleMarkArrived.remove();

        if (!document.getElementById('markArrivedModal')) {
            const modal = document.createElement('div');
            modal.id = 'markArrivedModal';
            modal.className = 'modal-overlay';
            // Keep open across background polling refreshes; Done/Cancel close it explicitly
            modal.dataset.preserveOpen = '1';
            modal.dataset.palette = 'edit-info';
            modal.style.display = 'none';
            modal.style.background = 'rgba(15, 23, 42, 0.45)';
            modal.innerHTML = `
                <div class="modal" style="width: 100%; max-width: 420px; padding: 40px 24px 24px; padding-top: 40px !important; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,.35);">
                    <div style="text-align: center; margin-bottom: 16px;">
                        <div style="width: 56px; height: 56px; border-radius: 50%; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; background: #e0f2fe; color: #0284c7; font-size: 28px; font-weight: 700;">i</div>
                        <h2 style="margin: 0; font-size: 18px; font-weight: 700; color: #0c4a6e;">Mark as Arrived</h2>
                        <p style="margin: 4px 0 0; color: #6b7280; font-size: 13px;">Check-in appointment &amp; add to queue</p>
                    </div>
                    <div id="markArrivedBody">
                        <div id="markArrivedInfo" style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px 16px; margin-bottom: 16px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px;">
                                <span id="markArrivedPatient" style="font-weight: 600; font-size: 1rem;"></span>
                                <span id="markArrivedTime" style="color: #6b7280; font-size: 0.85rem; white-space: nowrap;"></span>
                            </div>
                            <div style="color: #6b7280; font-size: 0.85rem; margin-top: 4px;">Scheduled appointment</div>
                        </div>
                        <p style="color: #4b5563; margin: 0 0 16px; font-size: 14px; line-height: 1.5;">Mark this patient as arrived? They will be automatically added to today&rsquo;s queue.</p>
                    </div>
                    <div id="markArrivedResult" style="display: none;"></div>
                    <div id="markArrivedActions" style="display: flex; gap: 12px; margin-top: 8px; justify-content: center; flex-wrap: wrap;">
                        <button onclick="closeMarkArrivedModal()" class="btn-cancel" style="min-width: 120px; padding: 10px 20px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; display:inline-flex;align-items:center;justify-content:center;">Cancel</button>
                        <button id="markArrivedConfirmBtn" onclick="confirmMarkArrived()" class="btn-primary" style="min-width: 120px; padding: 10px 20px; background: #0ea5e9; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; display:inline-flex;align-items:center;justify-content:center;">Confirm</button>
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
        actions.style.justifyContent = 'center';
        actions.innerHTML = `
            <button onclick="closeMarkArrivedModal()" class="btn-cancel" style="min-width: 120px; padding: 10px 20px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; display:inline-flex;align-items:center;justify-content:center;">Cancel</button>
            <button id="markArrivedConfirmBtn" onclick="confirmMarkArrived()" class="btn-primary" style="min-width: 120px; padding: 10px 20px; background: #0ea5e9; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; display:inline-flex;align-items:center;justify-content:center;">Confirm</button>
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

    function saveMarkArrivedVitals() {
        const btn = document.getElementById('markArrivedDoneBtn');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Saving...';
        }
        const finish = function() {
            closeMarkArrivedModal();
            if (typeof refreshAppointmentsAjax === 'function') refreshAppointmentsAjax();
        };
        if (!markArrivedQueueId) {
            finish();
            return;
        }
        const payload = Object.assign({ queue_id: markArrivedQueueId }, collectVitals('markArrived_'));
        fetch('../shared/save_queue_vitals.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).finally(finish);
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
                    <button onclick="printMarkArrivedTicket()" class="btn-cancel" style="min-width: 120px; padding: 10px 20px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; display:inline-flex;align-items:center;justify-content:center;">Print Queue No.</button>
                    <button id="markArrivedDoneBtn" onclick="saveMarkArrivedVitals()" class="btn-primary" style="min-width: 120px; padding: 10px 20px; background: #0ea5e9; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; display:inline-flex;align-items:center;justify-content:center;">Done</button>
                `;
            } else {
                btn.disabled = false;
                btn.textContent = 'Confirm';
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
            btn.textContent = 'Confirm';
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

    window.openMarkArrivedModal = openMarkArrivedModal;
    window.closeMarkArrivedModal = closeMarkArrivedModal;
    window.confirmMarkArrived = confirmMarkArrived;
    window.saveMarkArrivedVitals = saveMarkArrivedVitals;
    window.printMarkArrivedTicket = printMarkArrivedTicket;

    function forwardToQueue(id) {
        closeApptKebabDropdown();
        const card = document.querySelector(`.appointment-mini-card[data-id="${id}"]`);
        if (!card) return;

        const nameEl = card.querySelector('.mini-card-name');
        const nameText = nameEl ? nameEl.textContent.trim() : '';
        const nameParts = nameText.split(/\s+/);
        const firstName = nameParts[0] || '';
        const lastName = nameParts.length > 1 ? nameParts[nameParts.length - 1] : '';
        const middleName = nameParts.length > 2 ? nameParts.slice(1, -1).join(' ') : '';
        const phone = card.getAttribute('data-phone') || '';

        if (confirm('Forward this appointment to Queue?')) {
            let url = '../staff/staff_new_admission.php?appointment_id=' + id;
            if (firstName) url += '&first_name=' + encodeURIComponent(firstName);
            if (middleName) url += '&middle_name=' + encodeURIComponent(middleName);
            if (lastName) url += '&last_name=' + encodeURIComponent(lastName);
            if (phone) url += '&phone=' + encodeURIComponent(phone);
            window.location.href = url;
        }
    }

    // Cancel Reason Modal
    let cancelAppointmentId = null;
    
    function openCancelReasonModal(id) {
        closeApptKebabDropdown();
        
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
</script>
<script src="../../assets/js/appointment_ajax_refresh.js?v=2"></script>

<style>
/* Filter Tabs Styles */
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

/* Modal Styles */
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

/* Scheduled and pending approval appointments share this details modal. */
#viewAppointmentModal > .modal {
    padding-top: 28px !important;
}

.vitals-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.vitals-section { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; margin-bottom: 16px; }
.vitals-section h4 { margin: 0 0 4px; font-size: 0.85rem; font-weight: 600; color: #334155; }
.vitals-section .vitals-sub { margin: 0 0 12px; font-size: 0.75rem; color: #94a3b8; }
.vitals-section label { display: block; font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 4px; }
.vitals-section input { width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.85rem; outline: none; box-sizing: border-box; }
.vitals-section input:focus { border-color: #2563eb; }

#queuePrintContainer { display: none; }
@media print {
    @page { size: 58mm auto; margin: 0; }
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
    .queue-ticket { width: 100% !important; padding: 0 !important; margin: 0 auto; border: none !important; text-align: center !important; }
    .queue-ticket .qt-line { font-size: 2.5mm; font-weight: 700; line-height: 1.1; white-space: nowrap; overflow: hidden; }
    .queue-ticket .qt-clinic { font-size: 3.6mm; font-weight: 700; text-transform: uppercase; white-space: normal; word-break: break-word; margin-top: 1mm; }
    .queue-ticket .qt-address { font-size: 2.2mm; margin-bottom: 1mm; white-space: normal; word-break: break-word; }
    .queue-ticket .qt-label { font-size: 2.8mm; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-top: 2mm; }
    .queue-ticket .qt-number { font-size: 15mm; font-weight: 700; line-height: 1.05; margin: 0; }
    .queue-ticket .qt-meta { font-size: 2.5mm; margin-top: 1mm; white-space: normal; word-break: break-word; }
}

.form-row {
    display: flex;
    gap: 16px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
    margin-bottom: 6px;
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.9rem;
    outline: none;
    transition: border-color 0.2s;
}

.form-control:focus {
    border-color: #3b82f6;
    ring: 2px solid rgba(59, 130, 246, 0.2);
}

.modal-actions {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.btn-cancel {
    background: #f3f4f6;
    color: #374151;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-cancel:hover {
    background: #e5e7eb;
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
    min-width: 160px;
    max-width: calc(100vw - 24px);
    max-height: calc(100dvh - 24px);
    width: 220px;
    z-index: 100001;
    overflow-x: hidden;
    overflow-y: auto;
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

.summary-cards {
    margin-bottom: 0px;
    flex: 1;
}

</style>

<?php require_once '../../includes/dentist_layout_end.php'; ?>
