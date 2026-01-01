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

    $chartYear = (int)($pdo->query("
        SELECT COALESCE(MAX(YEAR(billing_date)), YEAR(CURDATE()))
        FROM billing
    ")->fetchColumn() ?? date('Y'));

    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(billing_date, '%Y-%m') AS ym,
               COALESCE(SUM(paid_amount), 0) AS total
        FROM billing
        WHERE YEAR(billing_date) = ?
        GROUP BY ym
        ORDER BY ym ASC
    ");
    $stmt->execute([$chartYear]);
    $monthlyRevenueData = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $totalPatients = 0;
    $totalAppointments = 0;
    $totalRevenue = 0;
    $pendingPayments = 0;
    $completedToday = 0;
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
                </div>

                <!-- Revenue Overview -->
                <div class="section-card">
                    <h2 class="section-title">Revenue Overview</h2>
                    <div class="chart-placeholder" style="min-height: 170px;">
                        <div class="bar-chart" id="revenueBars" style="height: 140px;"></div>
                    </div>
                </div>

            </div>

            <!-- Right Sidebar -->
            <aside class="content-sidebar">
                <!-- Notifications -->
                <div class="sidebar-section">
                    <h3 class="sidebar-section-title">Notifications</h3>
                    <p class="sidebar-section-subtitle">Recent system alerts</p>
                    
                    <div class="notification-item">
                        <div class="notification-icon">⚠️</div>
                        <div class="notification-text">5 pending payments require attention</div>
                    </div>
                    
                    <div class="notification-item">
                        <div class="notification-icon">👤</div>
                        <div class="notification-text">2 new patient registrations today</div>
                    </div>
                    
                    <button class="see-all-btn">See all notifications</button>
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
    const revenueData = <?php echo json_encode($monthlyRevenueData); ?>;
    const revenueYear = <?php echo (int)$chartYear; ?>;
    const barContainer = document.getElementById('revenueBars');
    if (barContainer) {
        const map = {};
        revenueData.forEach(item => {
            map[item.ym] = { total: Number(item.total) };
        });

        const months = [];
        const year = revenueYear || new Date().getFullYear();
        const labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        for (let m = 0; m < 12; m++) {
            const ym = `${year}-${String(m + 1).padStart(2, '0')}`;
            const label = labels[m];
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
