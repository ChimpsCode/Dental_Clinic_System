<?php
/**
 * Admin Dashboard - Main admin landing page
 * Uses the centralized Admin Layout for consistent sidebar and navigation
 */

$pageTitle = 'Dashboard';

require_once __DIR__ . '/config/database.php';

// Include the layout start
require_once __DIR__ . '/includes/admin_layout_start.php';

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
        WHERE status = 'completed'
          AND DATE(updated_at) = CURDATE()
    ")->fetchColumn() ?? 0);

    // Current 6 months data for dashboard revenue overview
    $halfYearStart = date('Y-m-01', strtotime('-5 months'));
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
        SELECT procedure_name AS service_name, COUNT(*) AS total
        FROM treatments
        WHERE treatment_date BETWEEN ? AND ?
        GROUP BY procedure_name
        ORDER BY total DESC
        LIMIT 4
    ");
    $stmt->execute([$startStr, $endStr]);
    $topServices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($topServices)) {
        $stmt = $pdo->prepare("
            SELECT treatment AS service_name, COUNT(*) AS total
            FROM appointments
            WHERE appointment_date BETWEEN ? AND ?
            GROUP BY treatment
            ORDER BY total DESC
            LIMIT 4
        ");
        $stmt->execute([$startStr, $endStr]);
        $topServices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get live queue data (next 5 patients in line)
    try {
        $stmt = $pdo->query("
            SELECT q.id, q.status, q.queue_time, q.treatment_type,
                   CONCAT(p.first_name, ' ', COALESCE(p.middle_name, ''), ' ', p.last_name) AS patient_name
            FROM queue q
            JOIN patients p ON p.id = q.patient_id
            WHERE q.status IN ('waiting', 'in_procedure', 'on_hold')
              AND q.is_archived = 0
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

} catch (Exception $e) {
    $totalPatients = 0;
    $totalAppointments = 0;
    $totalRevenue = 0;
    $pendingPayments = 0;
    $completedToday = 0;
    $totalCollected = 0;
    $topServices = [];
    $liveQueue = [];
}
?>
<style>
@keyframes pulse {
    0% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.2); }
    100% { opacity: 1; transform: scale(1); }
}
</style>
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
                        <div class="chart-placeholder" style="min-height: 170px;">
                            <div class="bar-chart" id="revenueBars" style="height: 140px;"></div>
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
                            <!-- DEBUG: Queue count: <?php echo count($liveQueue); ?> -->
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
                                                
                                                $statusClass = '';
                                                $statusLabel = '';
                                                $badgeStyle = '';
                                                switch($queueItem['status']) {
                                                    case 'waiting':
                                                        $statusClass = 'waiting';
                                                        $statusLabel = 'Waiting';
                                                        $badgeStyle = 'background: #fef3c7; color: #d97706;';
                                                        break;
                                                    case 'in_procedure':
                                                        $statusClass = 'in-progress';
                                                        $statusLabel = 'In Procedure';
                                                        $badgeStyle = 'background: #dbeafe; color: #2563eb;';
                                                        break;
                                                    case 'on_hold':
                                                        $statusClass = 'on-hold';
                                                        $statusLabel = 'On Hold';
                                                        $badgeStyle = 'background: #f3f4f6; color: #6b7280;';
                                                        break;
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
                </div>

                <!-- Top Services -->
                <div class="section-card">
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

                <!-- System Status -->
                <div class="sidebar-section">
                    <h3 class="sidebar-section-title">System Status</h3>
                    <div class="status-item">
                        <span class="status-label">Database</span>
                        <span class="status-value online">Connected</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Active Sessions</span>
                        <span class="status-value">12</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Last Backup</span>
                        <span class="status-value">2 hours ago</span>
                    </div>
                </div>
            </aside>

<script>
    const revenueData = <?php echo json_encode($halfYearRevenueData); ?>;
    const barContainer = document.getElementById('revenueBars');
    if (barContainer) {
        const map = {};
        revenueData.forEach(item => {
            map[item.ym] = { total: Number(item.total) };
        });

        const months = [];
        const year = new Date().getFullYear();
        const labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        // Get last 6 months
        const today = new Date();
        for (let i = 5; i >= 0; i--) {
            const d = new Date(today.getFullYear(), today.getMonth() - i, 1);
            const ym = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
            const label = labels[d.getMonth()];
            const entry = map[ym] || { total: 0 };
            entry.label = label;
            months.push(entry);
        }

        const maxVal = Math.max(1, ...months.map(r => r.total));
        months.forEach((item) => {
            const rawPct = (item.total / maxVal) * 100;
            const heightPct = item.total > 0 ? Math.max(20, Math.round(rawPct)) : 8;
            const bar = document.createElement('div');
            bar.className = 'bar';
            bar.style.setProperty('--bar-height', heightPct + '%');
            bar.setAttribute('data-label', item.label);
            barContainer.appendChild(bar);
        });
    }
</script>

<?php
// Include the layout end
require_once __DIR__ . '/includes/admin_layout_end.php';
?>
