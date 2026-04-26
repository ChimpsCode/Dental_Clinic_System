<?php
/**
 * Admin Dashboard - Main admin landing page
 * Uses the centralized Admin Layout for consistent sidebar and navigation
 */

$pageTitle = 'Dashboard';

require_once __DIR__ . '/../../config/database.php';

// Include the layout start
require_once __DIR__ . '/../../includes/admin_layout_start.php';

// Dashboard statistics
$totalUsers = 15;
$totalPatients = 0;
$totalAppointments = 0;
$totalRevenue = 0;
$pendingPayments = 0;
$completedToday = 0;
$monthlyRevenueData = [];
$chartYear = (int)date('Y');
$monthlyRevenueData = [];
$chartYear = (int)date('Y');
$topServices = [];
    $liveQueue = [];
    $todayAppointments = [];
    $today = new DateTime();
$startDate = new DateTime($today->format('Y-m-01'));
$endDate = clone $today;
$startStr = $startDate->format('Y-m-d');
$endStr = $endDate->format('Y-m-d');

try {
    $totalPatients = (int)($pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn() ?? 0);
    $totalAppointments = (int)($pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn() ?? 0);

    $totalRevenue = (float)($pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments")->fetchColumn() ?? 0);

    $pendingPayments = (int)($pdo->query("
        SELECT COUNT(*)
        FROM billing
        WHERE payment_status IN ('pending', 'unpaid', 'partial')
           OR (balance IS NOT NULL AND balance > 0)
    ")->fetchColumn() ?? 0);

    $completedToday = (int)($pdo->query("
        SELECT COUNT(*)
        FROM queue
        WHERE status_id = 4
          AND DATE(completed_at) = CURDATE()
    ")->fetchColumn() ?? 0);

    // Current 6 months data for dashboard revenue overview (current year only)
    $chartYear = (int)date('Y');
    $halfYearStart = $chartYear . '-01-01';
    $halfYearEnd = date('Y-m-t');

    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(billing_date, '%Y-%m') AS ym,
               COALESCE(SUM(paid_amount), 0) AS total
        FROM billing
        WHERE billing_date BETWEEN ? AND ?
        GROUP BY ym
        ORDER BY ym ASC
    ");
    $stmt->execute([$halfYearStart, $halfYearEnd]);
    $halfYearRevenueData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $chartYear = (int)date('Y');

    $stmt = $pdo->prepare("
            SELECT s.name AS service_name, COUNT(*) AS total
            FROM treatments t
            LEFT JOIN services s ON t.service_id = s.id
            WHERE t.treatment_date BETWEEN ? AND ?
            GROUP BY s.name
            ORDER BY total DESC
            LIMIT 4
        ");
        $stmt->execute([$startStr, $endStr]);
        $topServices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get live queue data (next 5 patients in line)
    try {
        $stmt = $pdo->query("
            SELECT q.id, q.status_id, q.queue_time, q.treatment_type,
                   COALESCE(CONCAT(p.first_name, ' ', COALESCE(p.middle_name, ''), ' ', p.last_name), 'Unknown') AS patient_name,
                   qs.status_name
            FROM queue q
            LEFT JOIN patients p ON p.id = CAST(q.patient_id AS UNSIGNED)
            LEFT JOIN queue_statuses qs ON q.status_id = qs.id
            WHERE q.status_id IN (1, 2)
              AND (q.is_archived = 0 OR q.is_archived IS NULL)
            ORDER BY 
                CASE q.status_id 
                    WHEN 2 THEN 1 
                    WHEN 1 THEN 2 
                    ELSE 3 
                END,
                q.queue_time ASC
            LIMIT 5
        ");
        $liveQueue = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $liveQueue = [];
    }
    
    // Get today's appointments for overview
    try {
        $apptStmt = $pdo->query("
            SELECT a.id, a.appointment_date, a.appointment_time, a.status_id,
                   COALESCE(p.first_name, 'Unknown') as first_name, 
                   COALESCE(p.middle_name, '') as middle_name, 
                   COALESCE(p.last_name, '') as last_name,
                   s.name as service_name,
                   st.status_name
            FROM appointments a
            LEFT JOIN patients p ON CAST(a.patient_id AS UNSIGNED) = p.id
            LEFT JOIN services s ON a.service_id = s.id
            LEFT JOIN appointment_statuses st ON a.status_id = st.id
            WHERE DATE(a.appointment_date) = CURDATE()
              AND (a.is_archived = 0 OR a.is_archived IS NULL)
            ORDER BY a.appointment_time ASC
            LIMIT 10
        ");
        $todayAppointments = $apptStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $todayAppointments = [];
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
    // Debug: log error
    error_log("Admin Dashboard Error: " . $e->getMessage());
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
                </div>

                <!-- Quick Stats Row -->
                <div class="summary-cards">
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
                            <h3><?php echo $completedToday; ?></h3>
                            <p>Completed Today</p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon blue">💵</div>
                        <div class="summary-info">
                            <h3>₱<?php echo number_format($totalRevenue); ?></h3>
                            <p>Total Collected</p>
                        </div>
                    </div>
                </div>

                <!-- Revenue Overview & Live Queue Side by Side -->
                <div class="dashboard-row" style="display: flex; flex-direction: row; gap: 20px; width: 100%;">
                    <!-- Revenue Overview -->
                    <div class="section-card" style="flex: 1; min-width: 300px;">
                        <h2 class="section-title">6-Month Revenue (<?php echo date('Y'); ?>)</h2>
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
                                
                                // Fetch actual revenue data directly - include ALL months with data
                                $revenueStmt = $pdo->query("
                                    SELECT DATE_FORMAT(billing_date, '%Y-%m') AS ym, SUM(paid_amount) AS total
                                    FROM billing
                                    WHERE billing_date >= '2025-11-01'
                                    GROUP BY ym
                                    ORDER BY ym
                                ");
                                while ($row = $revenueStmt->fetch(PDO::FETCH_ASSOC)) {
                                    $sixMonthsData[$row['ym']] = (float)$row['total'];
                                }
                                
                                // Filter to only show months with data in recent range
                                $sixMonthsFiltered = array_filter($sixMonthsData, function($v) { return $v > 0 || true; });
                                
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
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($liveQueue as $index => $queueItem): ?>
                                            <?php
                                                $queueTime = new DateTime($queueItem['queue_time']);
                                                $now = new DateTime();
                                                $waitingInterval = $queueTime->diff($now);
                                                $waitingMinutes = $waitingInterval->h * 60 + $waitingInterval->i;
                                                
                                                $statusId = (int)$queueItem['status_id'];
                                                $statusLabel = $queueItem['status_name'] ?? 'Scheduled';
                                                $badgeStyle = '';
                                                
                                                switch($statusId) {
                                                    case 1:
                                                        $statusLabel = 'Waiting';
                                                        $badgeStyle = 'background: #fef3c7; color: #d97706;';
                                                        break;
                                                    case 2:
                                                        $statusLabel = 'In Procedure';
                                                        $badgeStyle = 'background: #dbeafe; color: #2563eb;';
                                                        break;
                                                    case 3:
                                                        $statusLabel = 'Completed';
                                                        $badgeStyle = 'background: #dcfce7; color: #15803d;';
                                                        break;
                                                    case 4:
                                                        $statusLabel = 'Cancelled';
                                                        $badgeStyle = 'background: #fee2e2; color: #dc2626;';
                                                        break;
                                                    default:
                                                        $statusLabel = 'Waiting';
                                                        $badgeStyle = 'background: #fef3c7; color: #d97706;';
                                                }
                                            ?>
                                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                                <td style="padding: 12px; color: #374151;"><?php echo $index + 1; ?></td>
                                                <td style="padding: 12px; color: #374151;"><?php echo htmlspecialchars(trim($queueItem['patient_name'])); ?></td>
                                                <td style="padding: 12px; color: #374151;"><?php echo htmlspecialchars($queueItem['treatment_type'] ?: 'General'); ?></td>
                                                <td style="padding: 12px;"><span style="display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; <?php echo $badgeStyle; ?>"><?php echo $statusLabel; ?></span></td>
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

                <!-- Top Services -->
                
                

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
                    <div class="section-card" style="margin-top: 20px;">
                        <h2 class="section-title">📅 Appointments Overview</h2>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php if (empty($todayAppointments)): ?>
                                <p style="text-align: center; color: #6b7280; padding: 20px;">No appointments today</p>
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
                                            $statusId = (int)$appt['status_id'];
                                            
                                            $badgeStyle = 'background: #dbeafe; color: #1d4ed8;';
                                            if ($statusId === 2 || stripos($statusLabel, 'queue') !== false) { $badgeStyle = 'background: #fef3c7; color: #92400e;'; }
                                            if ($statusId === 3 || stripos($statusLabel, 'pending') !== false) { $badgeStyle = 'background: #fed7aa; color: #c2410c;'; }
                                            if ($statusId === 4 || stripos($statusLabel, 'complete') !== false) { $badgeStyle = 'background: #dcfce7; color: #15803d;'; }
                                            if ($statusId === 5 || stripos($statusLabel, 'cancel') !== false) { $badgeStyle = 'background: #fee2e2; color: #dc2626;'; }
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
                        <a href="admin_appointments.php" style="display: block; text-align: center; margin-top: 12px; padding: 10px; background: #f3f4f6; border-radius: 6px; color: #374151; text-decoration: none; font-size: 13px;">View All Appointments</a>
                    </div>
                







            </aside>

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
