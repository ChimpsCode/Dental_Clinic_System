<?php
/**
 * Admin Dashboard - Main admin landing page
 * Uses the centralized Admin Layout for consistent sidebar and navigation
 */

$pageTitle = 'Dashboard';

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/queue_management_helper.php';

// Include the layout start
require_once __DIR__ . '/../../includes/admin_layout_start.php';

// Dashboard statistics
$totalPatients = 0;
$totalAppointments = 0;
$totalRevenue = 0;
$pendingPayments = 0;
$completedToday = 0;
$topServices = [];
$liveQueue = [];
$todayAppointments = [];
    $today = new DateTime();
$startDate = new DateTime($today->format('Y-m-01'));
$endDate = clone $today;
$startStr = $startDate->format('Y-m-d');
$endStr = $endDate->format('Y-m-d');

try {
    ensureQueueManagementSchema($pdo);
    // Total Patients (only active, not archived)
    $totalPatients = (int)($pdo->query("SELECT COUNT(*) FROM patients WHERE is_archived = 0")->fetchColumn() ?? 0);
    
    // Total Appointments (all appointments)
    $totalAppointments = (int)($pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn() ?? 0);
    
    // Total Revenue - sum of all paid amounts from billing
    $totalRevenue = (float)($pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM billing")->fetchColumn() ?? 0);
    
    // Pending Payments - billing records with outstanding balance
    $pendingPayments = (int)($pdo->query("
        SELECT COUNT(*)
        FROM billing
        WHERE payment_status NOT IN ('paid', 'Paid')
           AND (balance IS NOT NULL AND balance > 0)
    ")->fetchColumn() ?? 0);
    
    // Completed Today - queue items marked as completed today
    $completedToday = (int)($pdo->query("
        SELECT COUNT(*)
        FROM queue
        WHERE status = 'completed'
          AND DATE(completed_at) = CURDATE()
    ")->fetchColumn() ?? 0);

    // Current 6 months data for dashboard revenue overview
    $chartYear = (int)date('Y');
    $halfYearStart = date('Y-m-d', strtotime('-5 months first day of this month'));
    $halfYearEnd = date('Y-m-t');

    $stmt = $pdo->query("
            SELECT service_name, COUNT(*) AS total
            FROM (
                SELECT s.name AS service_name
                FROM appointments a
                JOIN services s ON a.service_id = s.id
                WHERE a.service_id IS NOT NULL AND a.service_id > 0

                UNION ALL

                SELECT COALESCE(s.name, q.treatment_type) AS service_name
                FROM queue q
                LEFT JOIN services s ON q.service_id = s.id
                WHERE (q.service_id IS NOT NULL AND q.service_id > 0)
                   OR (q.treatment_type IS NOT NULL AND q.treatment_type != '')
            ) combined
            WHERE service_name IS NOT NULL AND service_name != ''
            GROUP BY service_name
            ORDER BY total DESC
            LIMIT 4
        ");
        $topServices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get live queue data (next 5 patients in line)
    try {
        $stmt = $pdo->query("
            SELECT q.id, q.status, q.queue_time, q.treatment_type, q.queue_number,
                   COALESCE(CONCAT(p.first_name, ' ', COALESCE(p.middle_name, ''), ' ', p.last_name), 'Unknown') AS patient_name,
                   q.status as status_name
            FROM queue q
            LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED)
            WHERE q.status IN ('waiting', 'in_procedure')
              AND (q.is_archived = 0 OR q.is_archived IS NULL)
            ORDER BY 
                CASE q.status 
                    WHEN 'in_procedure' THEN 1 
                    WHEN 'waiting' THEN 2 
                    ELSE 3 
                END,
                q.queue_time ASC
            LIMIT 5
        ");
        $liveQueue = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $liveQueue = [];
    }
    
    // Get upcoming appointments for overview (limit to 3 for dashboard)
    try {
        // Get total count of upcoming appointments
        $totalUpcomingAppts = (int)($pdo->query("
            SELECT COUNT(*)
            FROM appointments
            WHERE appointment_date >= CURDATE()
              AND (is_archived = 0 OR is_archived IS NULL)
        ")->fetchColumn() ?? 0);

        // Get only 2 upcoming appointments for overview
        $apptStmt = $pdo->query("
            SELECT a.id, a.appointment_date, a.appointment_time, a.status,
                   COALESCE(p.first_name, 'Unknown') as first_name,
                   COALESCE(p.middle_name, '') as middle_name,
                   COALESCE(p.last_name, '') as last_name,
                   s.name as service_name,
                   a.status as status_name
            FROM appointments a
            LEFT JOIN patients p ON CAST(a.patient_id AS UNSIGNED) = p.id
            LEFT JOIN services s ON a.service_id = s.id
            WHERE a.appointment_date >= CURDATE()
              AND (a.is_archived = 0 OR a.is_archived IS NULL)
            ORDER BY a.appointment_date ASC, a.appointment_time ASC
            LIMIT 2
        ");
        $todayAppointments = $apptStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $todayAppointments = [];
        $totalUpcomingAppts = 0;
    }

} catch (Exception $e) {
    $totalPatients = 0;
    $totalAppointments = 0;
    $totalRevenue = 0;
    $pendingPayments = 0;
    $completedToday = 0;
    $totalCollected = 0;
    $topServices = [];
    $liveQueue = [];
    $todayAppointments = [];
    error_log("Admin Dashboard Error: " . $e->getMessage());
}

// Get total collected today
try {
    $totalCollected = (float)($pdo->query("
        SELECT COALESCE(SUM(paid_amount), 0) 
        FROM billing 
        WHERE DATE(billing_date) = CURDATE() 
          AND payment_status NOT IN ('unpaid', 'pending')
    ")->fetchColumn() ?? 0);
} catch (Exception $e) {
    $totalCollected = 0;
}
?>

            <!-- Admin Dashboard Content -->
            <div class="content-main">
                <!-- Summary Cards -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon blue">🏥</div>
                        <div class="summary-info">
                            <h3><?php echo $totalPatients; ?></h3>
                            <p>Total Patients</p>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon green">📅</div>
                        <div class="summary-info">
                            <h3><?php echo $totalAppointments; ?></h3>
                            <p>Appointments</p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon green">💰</div>
                        <div class="summary-info">
                            <h3>₱<?php echo number_format($totalRevenue); ?></h3>
                            <p>Total Revenue</p>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon yellow">⏳</div>
                        <div class="summary-info">
                            <h3><?php echo $pendingPayments; ?></h3>
                            <p>Pending Payments</p>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon green">✓</div>
                        <div class="summary-info">
                            <h3 id="ad-completed-today"><?php echo $completedToday; ?></h3>
                            <p>Completed Today</p>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon blue">💵</div>
                        <div class="summary-info">
                            <h3>₱<?php echo number_format($totalCollected); ?></h3>
                            <p>Collected Today</p>
                        </div>
                    </div>
                </div>


                <!-- Revenue Overview & Live Queue Side by Side -->
                <div class="dashboard-row" style="display: flex; flex-direction: row; gap: 20px; width: 100%;">
                    <!-- Revenue Overview -->
                    <div class="section-card" style="flex: 1; min-width: 300px;">
                        <h2 class="section-title">6-Month Revenue</h2>
                        <div style="display: flex; align-items: flex-end; gap: 15px; height: 180px; padding: 10px 20px 30px 20px; border-bottom: 1px solid #e5e7eb;">
                                <?php 
                                // Get last 6 months dynamically
                                $currentMonth = (int)date('n');
                                $currentYear = (int)date('Y');
                                $sixMonthsData = [];
                                
                                for ($i = 5; $i >= 0; $i--) {
                                    $month = $currentMonth - $i;
                                    $year = $currentYear;
                                    if ($month <= 0) {
                                        $month += 12;
                                        $year -= 1;
                                    }
                                    $ym = sprintf('%d-%02d', $year, $month);
                                    $sixMonthsData[$ym] = 0;
                                }
                                
                                // Fetch actual revenue data for the 6-month window
                                $revenueStmt = $pdo->prepare("
                                    SELECT DATE_FORMAT(billing_date, '%Y-%m') AS ym, SUM(paid_amount) AS total
                                    FROM billing
                                    WHERE billing_date BETWEEN ? AND ?
                                    GROUP BY ym
                                    ORDER BY ym
                                ");
                                $revenueStmt->execute([$halfYearStart, $halfYearEnd]);
                                while ($row = $revenueStmt->fetch(PDO::FETCH_ASSOC)) {
                                    $sixMonthsData[$row['ym']] = (float)$row['total'];
                                }
                                
                                $maxRevenue = max($sixMonthsData);
                                if ($maxRevenue == 0) $maxRevenue = 1;
                                $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                                
                                // Sort by date and take last 6 months that have data
                                ksort($sixMonthsData);
                                $sixMonthsData = array_slice($sixMonthsData, -6, 6, true);
                                
                                foreach ($sixMonthsData as $ym => $total) {
                                    $monthNum = (int)substr($ym, 5, 2);
                                    $label = $labels[$monthNum - 1];
                                    if ($total > 0) {
                                        $height = ($total / $maxRevenue) * 100;
                                        if ($height < 20) $height = 20;
                                        $bg = 'linear-gradient(to top, #3b82f6, #60a5fa)';
                                    } else {
                                        $height = 8;
                                        $bg = '#e5e7eb';
                                    }
                                    ?>
                                    <div style="flex: 1; height: <?php echo $height; ?>%; min-height: 15px; background: <?php echo $bg; ?>; border-radius: 4px 4px 0 0; position: relative;" title="<?php echo $label; ?>: ₱<?php echo number_format($total); ?>">
                                        <span style="position: absolute; bottom: -25px; left: 50%; transform: translateX(-50%); font-size: 12px; color: #6b7280; white-space: nowrap;"><?php echo $label; ?></span>
                                    </div>
                                    <?php
                                }
                                ?>
                        </div>
                    </div>

                    <!-- Live Queue Status -->
                    <div class="section-card" style="flex: 1; min-width: 300px; border: 2px solid #10b981; border-radius: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h2 class="section-title" style="margin-bottom: 0;">Live Queue Status</h2>
                            <span style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: #10b981; font-weight: 500;">
                                <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; animation: pulse 2s infinite;"></span> Live
                            </span>
                        </div>
                        <div class="queue-table-container">
                            <?php if (!empty($liveQueue)): ?>
                                <table class="queue-table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                    <thead>
                                        <tr style="background: #f9fafb;">
                                            <th style="text-align: left; padding: 10px 12px; color: #6b7280; font-weight: 600; font-size: 12px;">#</th>
                                            <th style="text-align: left; padding: 10px 12px; color: #6b7280; font-weight: 600; font-size: 12px;">Patient Name</th>
                                            <th style="text-align: left; padding: 10px 12px; color: #6b7280; font-weight: 600; font-size: 12px;">Treatment</th>
                                            <th style="text-align: left; padding: 10px 12px; color: #6b7280; font-weight: 600; font-size: 12px;">Status</th>
                                            <th style="text-align: left; padding: 10px 12px; color: #6b7280; font-weight: 600; font-size: 12px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($liveQueue as $index => $queueItem): ?>
                                            <?php
                                                $queueTime = new DateTime($queueItem['queue_time']);
                                                $now = new DateTime();
                                                $waitingInterval = $queueTime->diff($now);
                                                $waitingMinutes = $waitingInterval->h * 60 + $waitingInterval->i;
                                                
                                                $statusLabel = $queueItem['status_name'] ?? 'Waiting';
                                                $badgeStyle = '';
                                                
                                                switch($statusLabel) {
                                                    case 'waiting':
                                                        $statusLabel = 'Waiting';
                                                        $badgeStyle = 'background: #fef3c7; color: #d97706;';
                                                        break;
                                                    case 'in_procedure':
                                                        $statusLabel = 'In Procedure';
                                                        $badgeStyle = 'background: #dbeafe; color: #2563eb;';
                                                        break;
                                                    case 'completed':
                                                        $statusLabel = 'Completed';
                                                        $badgeStyle = 'background: #dcfce7; color: #15803d;';
                                                        break;
                                                    case 'cancelled':
                                                        $statusLabel = 'Cancelled';
                                                        $badgeStyle = 'background: #fee2e2; color: #dc2626;';
                                                        break;
                                                    default:
                                                        $statusLabel = 'Waiting';
                                                        $badgeStyle = 'background: #fef3c7; color: #d97706;';
                                                }
                                            ?>
                                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                                <td style="padding: 12px; color: #374151;">#<?php echo $queueItem['queue_number'] ?? '?'; ?></td>
                                                <td style="padding: 12px; color: #374151;"><?php echo htmlspecialchars(trim($queueItem['patient_name'])); ?></td>
                                                <td style="padding: 12px; color: #374151;"><?php echo htmlspecialchars($queueItem['treatment_type'] ?: 'General'); ?></td>
                                                <td style="padding: 12px;"><span style="display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; <?php echo $badgeStyle; ?>"><?php echo $statusLabel; ?></span></td>
                                                <td style="padding: 12px;">
                                                    <?php if (($queueItem['status_name'] ?? '') === 'in_procedure'): ?>
                                                        <button onclick="openCompleteProcedureModal(<?php echo $queueItem['id']; ?>)" style="padding: 4px 10px; background: #2563eb; color: white; border: none; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer;">Complete</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="empty-queue" style="padding: 30px; text-align: center; background: #f0fdf4; border-radius: 8px;">
                                    <p style="font-size: 16px; font-weight: 500; color: #166534; margin: 0;">No patients in queue</p>
                                    <span style="font-size: 12px; color: #15803d;">Waiting room is empty</span>
                                </div>
                            <?php endif; ?>
                        </div>
    </div>
                </div>

                <!-- Top Services -->
                <div class="section-card" style="margin-bottom: 40px;">
                    <h2 class="section-title">Top Services</h2>
                    <div class="stat-content">
                        <?php if (!empty($topServices)): ?>
                            <?php foreach ($topServices as $index => $service): ?>
                                <div class="service-rank">
                                    <span class="rank"><?php echo $index + 1; ?></span>
                                    <span class="service-name"><?php echo htmlspecialchars($service['service_name'] ?: 'Unknown'); ?></span>
                                    <span class="service-count"><?php echo number_format((int)$service['total']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="service-rank">
                                <span class="rank">1</span>
                                <span class="service-name">No data</span>
                                <span class="service-count">0</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Right Sidebar -->
            <aside class="content-sidebar">
                <!-- Quick Actions -->
                <div class="sidebar-section">
                    <h3 class="sidebar-section-title">Quick Actions</h3>
                    <p class="sidebar-section-subtitle">Shortcuts to common tasks</p>

                    <div class="quick-actions quick-actions-sidebar">
                        <a href="admin_users.php" class="action-card">
                            <span class="action-icon">👥</span>
                            <span class="action-text">Manage Users</span>
                        </a>
                        <a href="admin_patients.php" class="action-card">
                            <span class="action-icon">📋</span>
                            <span class="action-text">View Patients</span>
                        </a>
                        <a href="admin_payment.php" class="action-card">
                            <span class="action-icon">💳</span>
                            <span class="action-text">Payment Overview</span>
                        </a>
                        <a href="admin_audit_trail.php" class="action-card">
                            <span class="action-icon">📝</span>
                            <span class="action-text">Audit Logs</span>
                        </a>
                    </div>
                </div>

                <!-- Today's Appointments Overview -->
                    <div class="section-card" >
                        <h2 class="section-title">📅 Upcoming Appointments</h2>
                        <div style="max-height: 180px; overflow-y: auto;">
                            <?php if (empty($todayAppointments)): ?>
                                <p style="text-align: center; color: #6b7280; padding: 20px;">No upcoming appointments</p>
                            <?php else: ?>
                                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                    <thead>
                                        <tr style="background: #f9fafb;">
                                            <th style="text-align: left; padding: 10px 12px; color: #6b7280; font-weight: 600; font-size: 12px;">Time</th>
                                            <th style="text-align: left; padding: 10px 12px; color: #6b7280; font-weight: 600; font-size: 12px;">Patient</th>
                                            <th style="text-align: left; padding: 10px 12px; color: #6b7280; font-weight: 600; font-size: 12px;">Service</th>
                                            <th style="text-align: left; padding: 10px 12px; color: #6b7280; font-weight: 600; font-size: 12px;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($todayAppointments as $appt): 
                                            $apptPatientName = trim(($appt['first_name'] ?? '') . ' ' . ($appt['middle_name'] ?? '') . ' ' . ($appt['last_name'] ?? ''));
                                            $statusLabel = $appt['status_name'] ?? 'Scheduled';
                                            
                                            $badgeStyle = 'background: #dbeafe; color: #1d4ed8;';
                                            if (stripos($statusLabel, 'queue') !== false) { $badgeStyle = 'background: #fef3c7; color: #92400e;'; }
                                            if (stripos($statusLabel, 'pending') !== false) { $badgeStyle = 'background: #fed7aa; color: #c2410c;'; }
                                            if (stripos($statusLabel, 'complete') !== false) { $badgeStyle = 'background: #dcfce7; color: #15803d;'; }
                                            if (stripos($statusLabel, 'cancel') !== false) { $badgeStyle = 'background: #fee2e2; color: #dc2626;'; }
                                        ?>
                                        <tr style="border-bottom: 1px solid #f3f4f6;">
                                            <td style="padding: 12px; color: #374151;"><?php echo date('h:i A', strtotime($appt['appointment_time'] ?? '')); ?></td>
                                            <td style="padding: 12px; color: #374151;"><?php echo htmlspecialchars($apptPatientName ?: 'Unknown'); ?></td>
                                            <td style="padding: 12px; color: #374151;"><?php echo htmlspecialchars($appt['service_name'] ?? 'General'); ?></td>
                                            <td style="padding: 12px;">
                                                <span style="display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; <?php echo $badgeStyle; ?>">
                                                    <?php echo htmlspecialchars($statusLabel); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                        <a href="admin_appointments.php" style="display: block; text-align: center; margin-top: 12px; padding: 10px; background: #eff6ff; border-radius: 6px; color: #0284c7; text-decoration: none; font-size: 13px; font-weight: 500;">
                            View All Appointments (<?php echo $totalUpcomingAppts; ?> upcoming) →
                        </a>
                    </div>
                







            </aside>

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
                <div class="cp-kicker">Admin Dashboard</div>
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

<?php
// Include the layout end
require_once __DIR__ . '/../../includes/admin_layout_end.php';
?>


<style>
@keyframes pulse {
    0% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.2); }
    100% { opacity: 1; transform: scale(1); }
}

/* Revenue Bar Chart */
.chart-placeholder {
    min-height: 170px;
    width: 100%;
}

</style>

<script>
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

window.onclick = function(event) {
    const cpModal = document.getElementById('completeProcedureModal');
    if (event.target === cpModal) closeCompleteProcedureModal();
};

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeCompleteProcedureModal();
});

// Queue count auto-polling for admin dashboard
var adQueueCountHash = '';

function pollAdminQueueCounts() {
    fetch('../shared/queue_poll.php')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) return;
            if (data.hash !== adQueueCountHash) {
                adQueueCountHash = data.hash;
                var el = document.getElementById('ad-completed-today');
                if (el) el.textContent = data.counts.completed;
            }
        })
        .catch(function(e) { console.error('Admin queue poll error:', e); });
}

setInterval(pollAdminQueueCounts, 10000);
pollAdminQueueCounts();
</script>

<style>
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
