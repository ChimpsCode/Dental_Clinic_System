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

require_once '../../includes/dentist_layout_start.php';

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
        SUM(CASE WHEN status = 'Cancelled' AND DATE(appointment_date) = CURDATE() THEN 1 ELSE 0 END) as cancelled
    FROM appointments");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $totalAppointments = $stats['total'] ?? 0;
    $todayAppointments = $stats['today'] ?? 0;
    $completedAppointments = $stats['completed'] ?? 0;
    $cancelledAppointments = $stats['cancelled'] ?? 0;
} catch (Exception $e) {
    $totalAppointments = $todayAppointments = $completedAppointments = $cancelledAppointments = 0;
}

// Fetch all appointments with treatment plan info
try {
    $stmt = $pdo->query("SELECT a.*, 
        p.first_name, p.middle_name, p.last_name,
        p.phone, p.email,
        s.name as service_name, s.price as service_price,
        a.status as status_label,
        tp.id as treatment_plan_id, tp.plan_name as treatment_plan_name
    FROM appointments a 
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN services s ON a.service_id = s.id
    LEFT JOIN treatment_plans tp ON a.id = tp.appointment_id
    WHERE (a.is_archived = 0 OR a.is_archived IS NULL)
    ORDER BY a.appointment_date DESC, a.appointment_time DESC");
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $appointments = [];
}
?>

<!-- Add extra CSS for status badges -->
<style>
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

<!-- Appointment Stats -->
<div class="summary-cards">
    <div class="summary-card">
        <div class="summary-icon blue" style="background: #e0f2fe; color: #0284c7;">📋</div>
        <div class="summary-info">
            <h3><?php echo $totalAppointments; ?></h3>
            <p>Total Appointments</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon yellow">⏰</div>
        <div class="summary-info">
            <h3><?php echo $todayAppointments; ?></h3>
            <p>Today</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon green">✓</div>
        <div class="summary-info">
            <h3><?php echo $completedAppointments; ?></h3>
            <p>Completed</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon red" style="background: #fee2e2; color: #dc2626;">⚠️</div>
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

<!-- Appointments Table -->
<div class="table-container" style="background: white; border-radius: 8px;">
    <table class="data-table">
        <thead>
            <tr>
                <th>First Name</th>
                <th>Middle Name</th>
                <th>Last Name</th>
                <th>Date & Time</th>
                <th>Treatment</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($appointments)): ?>
                <?php foreach ($appointments as $apt): ?>
                    <?php
                        $firstName = htmlspecialchars($apt['first_name'] ?? '');
                        $middleName = htmlspecialchars($apt['middle_name'] ?? '');
                        $lastName = htmlspecialchars($apt['last_name'] ?? '');

                        $appointmentDateInput = $apt['appointment_date'] ?? '';
                        $appointmentTimeInput = $apt['appointment_time'] ?? '';
                        $appointmentDateTime = null;

                        if ($appointmentDateInput) {
                            $candidate = $appointmentDateInput;
                            if ($appointmentTimeInput) {
                                $candidate .= ' ' . $appointmentTimeInput;
                            }

                            try {
                                $appointmentDateTime = new DateTime($candidate);
                            } catch (Exception $e) {
                                $appointmentDateTime = null;
                            }
                        }

                        $appointmentTime = $appointmentDateTime ? $appointmentDateTime->format('h:i A') : '';
                        $appointmentDateStr = $appointmentDateTime ? $appointmentDateTime->format('M d, Y') : '';

                        if (!$appointmentDateStr && $appointmentDateInput) {
                            $appointmentDateStr = date('M d, Y', strtotime($appointmentDateInput));
                        }
                        if (!$appointmentTime && $appointmentTimeInput) {
                            $appointmentTime = date('h:i A', strtotime($appointmentTimeInput));
                        }
                        
                        // Robust status resolution
                        $status = $apt['status_label'] ?? $apt['status'] ?? 'Scheduled';
                        $statusText = $status;
                    ?>
                    <tr class="appointment-row" data-id="<?php echo $apt['id']; ?>" data-date="<?php echo $apt['appointment_date']; ?>" data-time="<?php echo $appointmentTime; ?>" data-name="<?php echo strtolower($firstName . ' ' . $lastName); ?>" data-first-name="<?php echo htmlspecialchars($firstName); ?>" data-middle-name="<?php echo htmlspecialchars($middleName); ?>" data-last-name="<?php echo htmlspecialchars($lastName); ?>" data-phone="<?php echo htmlspecialchars($apt['phone'] ?? ''); ?>" data-status="<?php echo $statusText; ?>" data-type="<?php echo htmlspecialchars($apt['service_name'] ?? $apt['treatment'] ?? 'Consultation'); ?>" data-notes="<?php echo htmlspecialchars($apt['notes'] ?? ''); ?>" data-price="<?php echo htmlspecialchars($apt['service_price'] ?? '500'); ?>">
                        <td><?php echo $firstName ?: '-'; ?></td>
                        <td><?php echo $middleName ?: '-'; ?></td>
                        <td><?php echo $lastName ?: '-'; ?></td>
                        <td>
                            <div><?php echo $appointmentDateStr; ?></div>
                            <div style="font-size: 0.85rem; color: #6b7280;"><?php echo $appointmentTime; ?></div>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($apt['service_name'] ?? 'Consultation'); ?>
                            <?php if (!empty($apt['treatment_plan_id'])): ?>
                                <br><span class="status-badge" style="background: #fef3c7; color: #92400e; font-size: 0.7rem; margin-top: 4px; display: inline-block;">📋 Treatment Plan</span>
                            <?php endif; ?>
                        </td>
                         <?php 
                            $statusColor = match($status) {
                                'Completed' => 'background: #d1fae5; color: #065f46;',
                                'Cancelled' => 'background: #fee2e2; color: #991b1b;',
                                'In Queue' => 'background: #fef3c7; color: #92400e;',
                                'Pending Payment' => 'background: #fed7aa; color: #c2410c;',
                                'arrived' => 'background: #ccfbf1; color: #0f766e;',
                                default => 'background: #e0f2fe; color: #0369a1;' // Scheduled
                            };
                         ?>
                         <td><span class="status-badge" style="<?php echo $statusColor; ?>"><?php echo $statusText; ?></span></td>
                         <td>
                                <div class="appt-kebab-menu">
                                    <button class="appt-kebab-btn" data-appt-id="<?php echo $apt['id']; ?>" data-status="<?php echo htmlspecialchars($statusText); ?>" data-checked-in="<?php echo $apt['check_in_time'] ?? ''; ?>">
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
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2rem; color: #6b7280;">No appointments found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
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
<div id="newAppointmentModal" class="modal-overlay">
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
<div id="viewAppointmentModal" class="modal-overlay">
    <div class="modal" style="width: 500px;">
        <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Appointment Details</h2>
        <div id="viewAppointmentContent"></div>
        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px;">
            <button onclick="closeViewAppointmentModal()" class="btn-cancel">Close</button>
        </div>
    </div>
</div>

<!-- Edit Appointment Modal -->
<div id="editAppointmentModal" class="modal-overlay">
    <div class="modal" style="width: 550px; max-height: 90vh; overflow-y: auto;">
        <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Edit Appointment</h2>
        <form id="editAppointmentForm">
            <input type="hidden" name="appointment_id" id="edit_appointment_id">
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>First Name *</label>
                    <input type="text" name="first_name" id="edit_first_name" required class="form-control" placeholder="Enter first name">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" id="edit_middle_name" class="form-control" placeholder="Enter middle name">
                </div>
            </div>
            
            <div class="form-group">
                <label>Last Name *</label>
                <input type="text" name="last_name" id="edit_last_name" required class="form-control" placeholder="Enter last name">
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
            
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Appointment Date *</label>
                    <input type="date" name="appointment_date" id="edit_appointment_date" required class="form-control">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Appointment Time *</label>
                    <input type="time" name="appointment_time" id="edit_appointment_time" required class="form-control">
                </div>
            </div>
            
            <div class="form-group">
                <label>Treatment</label>
                <select name="treatment" id="edit_treatment" class="form-control">
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
                <label>Status</label>
                <select name="status" id="edit_status" class="form-control">
                    <option value="scheduled">Scheduled</option>
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" id="edit_notes" rows="3" class="form-control" placeholder="Additional notes about the appointment..."></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" onclick="closeEditAppointmentModal()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>



<script>
const appointments = <?php echo json_encode($appointments); ?>;
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

// Portal Pattern: Move modal to body level
document.addEventListener('DOMContentLoaded', function() {
    const newModal = document.getElementById('newAppointmentModal');
    if (newModal) {
        document.body.appendChild(newModal);
    }
    
    const editModal = document.getElementById('editAppointmentModal');
    if (editModal) {
        document.body.appendChild(editModal);
    }
    
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
        const rows = document.querySelectorAll('.appointment-row');
        
        rows.forEach(row => {
            const name = row.dataset.name || '';
            const date = row.dataset.date || '';
            const status = row.dataset.status || '';
            
            const nameMatch = name.includes(search);
            const today = new Date().toISOString().split('T')[0];
            const dateMatch = date === today;
            
            row.style.display = (nameMatch || !search) ? '' : 'none';
        });
    }

    function getStatusBadgeClass(status) {
        const s = (status || '').toLowerCase();
        if (s.includes('completed')) return 'status-completed';
        if (s.includes('cancel')) return 'status-cancelled';
        if (s.includes('queue')) return 'status-in-queue';
        if (s.includes('pending')) return 'status-pending';
        return 'status-scheduled';
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
            location.reload();
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
function viewAppointment(id) {
    const appointment = appointments.find(a => a.id == id);
    if (!appointment) return;
    
    const appointmentDate = new Date(appointment.appointment_date);
    const dateStr = appointmentDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    const timeStr = appointmentDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    
    document.getElementById('viewAppointmentContent').innerHTML = `
        <div style="display: flex; flex-direction: column; gap: 16px;">
            <div><span style="color: #6b7280;">Patient:</span> 
                <span style="font-weight: 600; margin-left: 8px;">${appointment.first_name || ''}</span>
                ${appointment.middle_name ? `<span style="color: #6b7280; margin-left: 4px;">${appointment.middle_name}</span>` : ''}
                <span style="font-weight: 600; margin-left: 4px;">${appointment.last_name || ''}</span>
            </div>
            <div><span style="color: #6b7280;">Phone:</span> <span style="font-weight: 500; margin-left: 8px;">${appointment.phone || 'N/A'}</span></div>
            <div><span style="color: #6b7280;">Date:</span> <span style="font-weight: 500; margin-left: 8px;">${dateStr}</span></div>
            <div><span style="color: #6b7280;">Time:</span> <span style="font-weight: 500; margin-left: 8px;">${timeStr}</span></div>
            <div><span style="color: #6b7280;">Treatment:</span> <span style="font-weight: 500; margin-left: 8px;">${appointment.service_name || appointment.treatment || 'Consultation'}</span></div>
            <div><span style="color: #6b7280;">Status:</span> <span class="status-badge ${getStatusBadgeClass(appointment.status)}" style="margin-left: 8px;">${appointment.status || 'Pending'}</span></div>
            ${appointment.notes ? `<div><span style="color: #6b7280;">Notes:</span><p style="background: #f9fafb; padding: 12px; border-radius: 8px; margin: 8px 0 0;">${appointment.notes}</p></div>` : ''}
        </div>
    `;
    
    document.getElementById('viewAppointmentModal').style.display = 'flex';
}

function editAppointment(id) {
    const appointment = appointments.find(a => a.id == id);
    if (!appointment) return;
    
    // Fill form with appointment data
    document.getElementById('edit_appointment_id').value = appointment.id;
    document.getElementById('edit_first_name').value = appointment.first_name || '';
    document.getElementById('edit_middle_name').value = appointment.middle_name || '';
    document.getElementById('edit_last_name').value = appointment.last_name || '';
    document.getElementById('edit_phone').value = appointment.phone || '';
    document.getElementById('edit_email').value = appointment.email || '';
    document.getElementById('edit_treatment').value = appointment.service_name || appointment.treatment || 'Consultation';
    document.getElementById('edit_appointment_date').value = appointment.appointment_date ? String(appointment.appointment_date).split(' ')[0] : '';
    document.getElementById('edit_appointment_time').value = appointment.appointment_time || '';
    document.getElementById('edit_status').value = appointment.status || 'scheduled';
    document.getElementById('edit_notes').value = appointment.notes || '';
    document.getElementById('editAppointmentModal').style.display = 'flex';
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
    
    fetch('process_dentist_appointment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Appointment updated successfully!');
            closeEditAppointmentModal();
            location.reload();
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
    
    // Get appointment data from table row data attributes
    const row = document.querySelector(`.appointment-row[data-id="${id}"]`);
    if (!row) {
        alert('Appointment not found');
        return;
    }
    
    const firstName = row.getAttribute('data-first-name') || row.cells[0].textContent.trim();
    const middleName = row.getAttribute('data-middle-name') || row.cells[1].textContent.trim();
    const lastName = row.getAttribute('data-last-name') || row.cells[2].textContent.trim();
    const phone = row.getAttribute('data-phone') || 'N/A';
    const date = row.getAttribute('data-date') || '';
    const time = row.getAttribute('data-time') || '';
    const treatment = row.getAttribute('data-type') || 'Consultation';
    const status = row.getAttribute('data-status') || 'Scheduled';
    const notes = row.getAttribute('data-notes') || '';
    
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
        
        document.getElementById('viewAppointmentContent').innerHTML = `
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div><span style="color: #6b7280;">Patient:</span> 
                    <span style="font-weight: 600; margin-left: 8px;">${firstName}</span>
                    ${middleName && middleName !== '-' ? `<span style="color: #6b7280; margin-left: 4px;">${middleName}</span>` : ''}
                    <span style="font-weight: 600; margin-left: 4px;">${lastName}</span>
                </div>
                <div><span style="color: #6b7280;">Phone:</span> <span style="font-weight: 500; margin-left: 8px;">${phone}</span></div>
                <div><span style="color: #6b7280;">Date:</span> <span style="font-weight: 500; margin-left: 8px;">${dateStr}</span></div>
                <div><span style="color: #6b7280;">Time:</span> <span style="font-weight: 500; margin-left: 8px;">${timeStr}</span></div>
                <div><span style="color: #6b7280;">Treatment:</span> <span style="font-weight: 500; margin-left: 8px;">${treatment}</span></div>
                ${editSection}
                <div><span style="color: #6b7280;">Status:</span> <span class="status-badge" style="margin-left: 8px; background: ${statusBg}; color: ${statusColor}; padding: 4px 12px; border-radius: 9999px; font-size: 0.875rem;">${status}</span></div>
                ${notes ? `<div><span style="color: #6b7280;">Notes:</span><p style="background: #f9fafb; padding: 12px; border-radius: 8px; margin: 8px 0 0;">${notes}</p></div>` : '<div><span style="color: #6b7280;">Notes:</span><p style="background: #f9fafb; padding: 12px; border-radius: 8px; margin: 8px 0 0; color: #9ca3af;">No notes</p></div>'}
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
                location.reload();
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
</script>

<!-- Kebab Menu Functions -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize kebab menu
    initApptKebabMenu();
});

let apptKebabDropdown = null;
let apptKebabBackdrop = null;
let apptActiveButton = null;

function initApptKebabMenu() {
    apptKebabDropdown = document.createElement('div');
    apptKebabDropdown.className = 'appt-kebab-dropdown-portal';
    apptKebabDropdown.id = 'apptKebabDropdown';
    document.body.appendChild(apptKebabDropdown);
    
    apptKebabBackdrop = document.createElement('div');
    apptKebabBackdrop.className = 'appt-kebab-backdrop';
    apptKebabBackdrop.id = 'apptKebabBackdrop';
    document.body.appendChild(apptKebabBackdrop);
    
    apptKebabBackdrop.addEventListener('click', closeApptKebabDropdown);
    
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
}

function openApptKebabDropdown(button) {
    const apptId = button.getAttribute('data-appt-id');
    const status = button.getAttribute('data-status') || 'scheduled';
    const checkedIn = button.getAttribute('data-checked-in') || '';
    const isCancelled = (status.toLowerCase().includes('cancel'));
    const isArrived = checkedIn && checkedIn.trim() !== '';

    let html = `
        <a href="javascript:void(0)" onclick="viewAppointmentDetails(${apptId})">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            View Details
        </a>
    `;

    if (!isArrived) {
        html += `
            <a href="javascript:void(0)" onclick="markArrived(${apptId})">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                Mark as Arrived
            </a>
        `;
    }

    if (isArrived) {
        html += `
            <a href="javascript:void(0)" onclick="forwardToQueue(${apptId})">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 12h-16"/>
                    <path d="M12 5l7 7-7 7"/>
                    <path d="M3 5v14"/>
                </svg>
                Forward to Queue
            </a>
        `;
    }

    if (!isCancelled) {
        html += `
            <a href="javascript:void(0)" onclick="openCancelReasonModal(${apptId})" style="color: #dc2626;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                Cancel Appointment
            </a>
        `;
    }

    apptKebabDropdown.innerHTML = html;
    
    // Position dropdown
    const rect = button.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const padding = 12;
    
    let left = rect.right + 8;
    let top = rect.top;
    
    if (left + 200 > viewportWidth - padding) {
        left = rect.left - 200 - 8;
    }
    if (top + 100 > viewportHeight - padding) {
        top = viewportHeight - padding - 100;
    }
    
    apptKebabDropdown.style.left = left + 'px';
    apptKebabDropdown.style.top = top + 'px';
    
    apptKebabDropdown.classList.add('show');
    apptKebabBackdrop.classList.add('show');
    apptActiveButton = button;
    button.classList.add('active');
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

    function markArrived(id) {
        closeApptKebabDropdown();
        if (confirm('Mark this patient as arrived?')) {
            fetch('../shared/mark_arrived.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ appt_id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
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

    function forwardToQueue(id) {
        closeApptKebabDropdown();
        const row = document.querySelector(`.appointment-row[data-id="${id}"]`);
        if (!row) return;

        const firstName = row.getAttribute('data-first-name') || '';
        const middleName = row.getAttribute('data-middle-name') || '';
        const lastName = row.getAttribute('data-last-name') || '';
        const phone = row.getAttribute('data-phone') || '';

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
</script>

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
    max-width: 180px;
    width: auto;
    z-index: 100001;
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


.summary-cards {
    margin-bottom: 0px;
}

</style>

<?php require_once '../../includes/dentist_layout_end.php'; ?>