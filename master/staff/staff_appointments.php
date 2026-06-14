<?php
$pageTitle = 'Appointments';

// Pagination settings
$itemsPerPage = 50;
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;

try {
    require_once '../../config/database.php';
    
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
        COALESCE(addr.street_address, '') as address,
        COALESCE(p.date_of_birth, '') as date_of_birth,
        COALESCE(p.gender, '') as gender,
        COALESCE(addr.city, '') as city,
        COALESCE(addr.province, '') as province,
        COALESCE(addr.zip_code, '') as zip_code,
        s.name as service_name,
        st.status_name as status_label,
        tp.id as treatment_plan_id, tp.plan_name as treatment_plan_name
        FROM appointments a 
        LEFT JOIN patients p ON CAST(a.patient_id AS UNSIGNED) = p.id
        LEFT JOIN addresses addr ON p.address_id = addr.id
        LEFT JOIN services s ON a.service_id = s.id
        LEFT JOIN appointment_statuses st ON a.status_id = st.id
        LEFT JOIN treatment_plans tp ON a.id = tp.appointment_id
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
    $checkCol = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'status'");
    $hasStatusCol = $checkCol->fetch() !== false;
    
    // Simple direct queries for today stats
    $todayCount = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()")->fetchColumn();
    
    if ($hasStatusCol) {
        $completedCount = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE LOWER(status) = 'completed' AND DATE(appointment_date) = CURDATE()")->fetchColumn();
        $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE LOWER(status) IN ('scheduled','pending') AND DATE(appointment_date) = CURDATE()")->fetchColumn();
        $cancelledCount = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE LOWER(status) = 'cancelled' AND DATE(appointment_date) = CURDATE()")->fetchColumn();
    } else {
        // status_id: 4=completed, 5=cancelled, 1=scheduled, 2=confirmed
        $completedCount = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status_id = 4 AND DATE(appointment_date) = CURDATE()")->fetchColumn();
        $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status_id IN (1,2) AND DATE(appointment_date) = CURDATE()")->fetchColumn();
        $cancelledCount = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status_id = 5 AND DATE(appointment_date) = CURDATE()")->fetchColumn();
    }
} catch (Exception $e) {
    $todayCount = 0;
    $completedCount = 0;
    $pendingCount = 0;
    $cancelledCount = 0;
}

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

    <div class="section-card">
        <div style="overflow-x: auto;">
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
                <tbody id="appointmentsTableBody">
                    <?php if (empty($appointments)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 60px; color: #6b7280;">
                                <p style="font-size: 1.1rem; margin-bottom: 8px;">No appointments found</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($appointments as $appt): 
                            $firstName = htmlspecialchars($appt['first_name'] ?? '');
                            $middleName = htmlspecialchars($appt['middle_name'] ?? '');
                            $lastName = htmlspecialchars($appt['last_name'] ?? '');
                            $fullName = trim("$firstName $middleName $lastName") ?: 'Unknown';
                            
                           $statusId = (int)($appt['status_id'] ?? 0);
                            $status = $appt['status_label'];

                            if ($statusId === 2 || stripos($appt['status'] ?? '', 'Queue') !== false) {
                                $status = 'In Queue';
                            }

                            if (!$status) {
                                if ($statusId === 1) $status = 'Scheduled';
                                elseif ($statusId === 2) $status = 'In Queue';
                                elseif ($statusId === 3) $status = 'Pending Payment';
                                elseif ($statusId === 4) $status = 'Completed';
                                elseif ($statusId === 5) $status = 'Cancelled';
                                else $status = $appt['status'] ?? 'Scheduled';
                            }
                        ?>
                            <tr class="appointment-row" 
                                data-name="<?php echo strtolower($fullName); ?>"
                                data-date="<?php echo date('Y-m-d', strtotime($appt['appointment_date'])); ?>"
                                data-status="<?php echo strtolower($status); ?>">
                                <td><?php echo $firstName ?: '-'; ?></td>
                                <td><?php echo $middleName ?: '-'; ?></td>
                                <td><?php echo $lastName ?: '-'; ?></td>
                                <td>
                                    <div><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></div>
                                    <div style="font-size: 0.85rem; color: #6b7280;">
                                        <?php 
                                        $time = $appt['appointment_time'] ?? '';
                                        if ($time) {
                                            echo date('h:i A', strtotime($time));
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($appt['service_name'] ?? 'General'); ?>
                                    <?php if (!empty($appt['treatment_plan_id'])): ?>
                                        <br><span class="status-badge" style="background: #fef3c7; color: #92400e; font-size: 0.7rem; margin-top: 4px; display: inline-block;">📋 Treatment Plan</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        $badgeClass = 'status-scheduled';
                                        if (stripos($status, 'arrived') !== false) $badgeClass = 'status-in-queue';
                                        if (stripos($status, 'queue') !== false) $badgeClass = 'status-in-queue';
                                        if (stripos($status, 'pending') !== false) $badgeClass = 'status-pending-payment';
                                        if (stripos($status, 'complete') !== false) $badgeClass = 'status-completed';
                                        if (stripos($status, 'cancel') !== false) $badgeClass = 'status-cancelled';
                                    ?>
                                    <span class="status-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                                </td>
                                <td>
                                    <div class="appt-kebab-menu">
                                    <button class="appt-kebab-btn" 
                                            data-appt-id="<?php echo $appt['id']; ?>"
                                            data-status-id="<?php echo $appt['status_id'] ?? ''; ?>"
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
    </div>

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
    <div class="modal" style="width: 550px;">
        <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Schedule New Appointment</h2>
        <form id="appointmentForm">
            <input type="hidden" name="appointment_id" id="appointmentId">
            <input type="hidden" name="patient_id" id="appointmentPatientId">
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
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Appointment Date *</label>
                    <input type="date" name="appointment_date" required class="form-control">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Time *</label>
                    <input type="time" name="appointment_time" required class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>Treatment</label>
                <select name="treatment" class="form-control">
                    <option value="General Checkup">General Checkup</option>
                    <option value="Teeth Cleaning">Teeth Cleaning</option>
                    <option value="Root Canal">Root Canal</option>
                    <option value="Tooth Extraction">Tooth Extraction</option>
                    <option value="Dental Fillings">Dental Fillings</option>
                    <option value="Braces Adjustment">Braces Adjustment</option>
                    <option value="Denture Fitting">Denture Fitting</option>
                    <option value="Oral Prophylaxis">Oral Prophylaxis</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" rows="3" class="form-control" placeholder="Additional notes or instructions..."></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeAppointmentModal()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-primary">Schedule Appointment</button>
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
    const appointments = <?php echo json_encode($appointments); ?>;
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

    function filterAppointments() {
        const search = document.getElementById('searchAppointment').value.toLowerCase();
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

    function openAppointmentModal() {
        document.getElementById('appointmentModal').style.display = 'flex';
    }

    function prepareNewAppointmentModal() {
        const form = document.getElementById('appointmentForm');
        if (form) {
            form.reset();
        }
        document.getElementById('appointmentId').value = '';
        document.getElementById('appointmentPatientId').value = '';
        document.querySelector('#appointmentModal h2').textContent = 'Schedule New Appointment';
        document.querySelector('#appointmentForm button[type="submit"]').textContent = 'Schedule Appointment';
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
        const formData = new FormData(this);
        
        for (var pair of formData.entries()) {
            console.log(pair[0]+ ', '+ pair[1]); 
        }
        
        fetch('../shared/process_appointment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                closeAppointmentModal();
                location.reload();
            } else {
                alert(data.message || 'Error scheduling appointment');
            }
        })
        .catch(error => {
            console.error('Error:', error);
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
                <div><span style="color: #6b7280;">Status:</span> <span class="status-badge" style="margin-left: 8px; background: ${getAppointmentDisplayStatus(appt) === 'Completed' ? '#dcfce7' : getAppointmentDisplayStatus(appt) === 'Cancelled' ? '#fee2e2' : '#e0f2fe'}; color: ${getAppointmentDisplayStatus(appt) === 'Completed' ? '#15803d' : getAppointmentDisplayStatus(appt) === 'Cancelled' ? '#dc2626' : '#0369a1'};">${getAppointmentDisplayStatus(appt)}</span></div>
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
        const statusId = button ? parseInt(button.getAttribute('data-status-id')) : 0;
        const status = button ? button.getAttribute('data-status') : '';
        const checkedIn = button ? button.getAttribute('data-checked-in') : '';
        const isCancelled = (statusId == 5 || (status && status.toLowerCase().includes('cancel')));
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
                <a href="javascript:void(0)" data-action="forward_to_queue" data-id="${apptId}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 12h-16"/>
                        <path d="M12 5l7 7-7 7"/>
                        <path d="M3 5v14"/>
                    </svg>
                    Forward to Queue
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
                case 'forward_to_queue':
                    forwardToQueue(id);
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
        form.elements.appointment_date.value = appt.appointment_date ? String(appt.appointment_date).split(' ')[0] : '';
        form.elements.appointment_time.value = appt.appointment_time ? String(appt.appointment_time).slice(0, 5) : '';
        form.elements.treatment.value = appt.service_name || appt.treatment || 'General Checkup';
        form.elements.notes.value = appt.notes || '';

        document.querySelector('#appointmentModal h2').textContent = 'Edit Appointment';
        document.querySelector('#appointmentForm button[type="submit"]').textContent = 'Save Changes';
        openAppointmentModal();
    }

    function getAppointmentDisplayStatus(appt) {
        if (!appt) return 'Pending';
        return appt.status_label || appt.status || 'Scheduled';
    }

    function markArrived(id) {
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
        const appt = appointments.find(a => a.id == id);
        if (!appt) return;
        
        if (confirm('Forward this appointment to Queue?\n\nThis will open the admission form where you can select the service and teeth before adding to the queue.')) {
            let url = 'staff_new_admission.php?appointment_id=' + id;
            
            if (appt.first_name) url += '&first_name=' + encodeURIComponent(appt.first_name);
            if (appt.middle_name) url += '&middle_name=' + encodeURIComponent(appt.middle_name);
            if (appt.last_name) url += '&last_name=' + encodeURIComponent(appt.last_name);
            if (appt.phone) url += '&phone=' + encodeURIComponent(appt.phone);
            if (appt.email) url += '&email=' + encodeURIComponent(appt.email);
            if (appt.address) url += '&address=' + encodeURIComponent(appt.address);
            if (appt.date_of_birth) url += '&date_of_birth=' + encodeURIComponent(appt.date_of_birth);
            if (appt.gender) url += '&gender=' + encodeURIComponent(appt.gender);
            if (appt.city) url += '&city=' + encodeURIComponent(appt.city);
            if (appt.province) url += '&province=' + encodeURIComponent(appt.province);
            if (appt.zip_code) url += '&zip_code=' + encodeURIComponent(appt.zip_code);
            
            window.location.href = url;
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
</script>

<?php require_once '../../includes/staff_layout_end.php'; ?>
