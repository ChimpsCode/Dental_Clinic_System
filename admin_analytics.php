<?php
/**
 * Analytics - Admin page for viewing clinic analytics and statistics
 */

$pageTitle = 'Analytics';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/admin_layout_start.php';

$period = $_GET['period'] ?? 'month';
$period = in_array($period, ['week', 'month', 'quarter', 'year'], true) ? $period : 'month';

$today = new DateTime('today');
$startDate = clone $today;
$endDate = clone $today;

switch ($period) {
    case 'week':
        $startDate = (clone $today)->modify('-6 days');
        break;
    case 'quarter':
        $month = (int)$today->format('n');
        $quarterStartMonth = (int)(floor(($month - 1) / 3) * 3) + 1;
        $startDate = new DateTime($today->format('Y') . '-' . str_pad((string)$quarterStartMonth, 2, '0', STR_PAD_LEFT) . '-01');
        break;
    case 'year':
        $startDate = new DateTime($today->format('Y') . '-01-01');
        break;
    case 'month':
    default:
        $startDate = new DateTime($today->format('Y-m-01'));
        break;
}

$startStr = $startDate->format('Y-m-d');
$endStr = $endDate->format('Y-m-d');

$totalPatients = 0;
$appointmentsCount = 0;
$revenueTotal = 0;
$avgWaitMinutes = 0;
$monthlyRevenue = 0;
$totalCollected = 0;
$pendingPayments = 0;
$todaysPatients = 0;
$monthlyRevenueData = [];
$newPatients = 0;
$returningPatients = 0;
$ageGroupLabel = 'N/A';
$genderDistribution = 'N/A';
$topServices = [];
$topDentistName = 'Dentist';
$topDentistCount = 0;
$topStaffName = 'Staff';
$topStaffCount = 0;

try {
    $totalPatients = (int)($pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn() ?? 0);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ?");
    $stmt->execute([$startStr, $endStr]);
    $appointmentsCount = (int)($stmt->fetchColumn() ?? 0);

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_date BETWEEN ? AND ?");
    $stmt->execute([$startStr, $endStr]);
    $revenueTotal = (float)($stmt->fetchColumn() ?? 0);

    $monthlyRevenue = (float)($pdo->query("
        SELECT COALESCE(SUM(amount), 0)
        FROM payments
        WHERE YEAR(payment_date) = YEAR(CURDATE())
          AND MONTH(payment_date) = MONTH(CURDATE())
    ")->fetchColumn() ?? 0);

    $totalCollected = (float)($pdo->query("
        SELECT COALESCE(SUM(paid_amount), 0)
        FROM billing
        WHERE payment_status = 'paid'
    ")->fetchColumn() ?? 0);

    $pendingPayments = (float)($pdo->query("
        SELECT COALESCE(SUM(balance), 0)
        FROM billing
        WHERE payment_status IN ('pending', 'unpaid', 'partial')
           OR (balance IS NOT NULL AND balance > 0)
    ")->fetchColumn() ?? 0);

    $todaysPatients = (int)($pdo->query("
        SELECT COUNT(*)
        FROM patients
        WHERE DATE(created_at) = CURDATE()
    ")->fetchColumn() ?? 0);

    $stmt = $pdo->query("
        SELECT
            DATE_FORMAT(payment_date, '%b') AS month_label,
            DATE_FORMAT(payment_date, '%Y-%m') AS ym,
            COALESCE(SUM(amount), 0) AS total
        FROM payments
        WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY ym, month_label
        ORDER BY ym ASC
    ");
    $monthlyRevenueData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, created_at, completed_at)), 0)
        FROM queue
        WHERE status = 'completed'
          AND completed_at IS NOT NULL
          AND DATE(completed_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$startStr, $endStr]);
    $avgWaitMinutes = (int)round((float)($stmt->fetchColumn() ?? 0));

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$startStr, $endStr]);
    $newPatients = (int)($stmt->fetchColumn() ?? 0);

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT a.patient_id)
        FROM appointments a
        JOIN patients p ON p.id = a.patient_id
        WHERE a.appointment_date BETWEEN ? AND ?
          AND DATE(p.created_at) < ?
    ");
    $stmt->execute([$startStr, $endStr, $startStr]);
    $returningPatients = (int)($stmt->fetchColumn() ?? 0);

    $stmt = $pdo->query("
        SELECT
            CASE
                WHEN age BETWEEN 0 AND 12 THEN '0-12'
                WHEN age BETWEEN 13 AND 17 THEN '13-17'
                WHEN age BETWEEN 18 AND 24 THEN '18-24'
                WHEN age BETWEEN 25 AND 35 THEN '25-35'
                WHEN age BETWEEN 36 AND 45 THEN '36-45'
                WHEN age BETWEEN 46 AND 60 THEN '46-60'
                WHEN age >= 61 THEN '60+'
                ELSE NULL
            END AS age_group,
            COUNT(*) AS total
        FROM patients
        WHERE age IS NOT NULL
        GROUP BY age_group
        ORDER BY total DESC
        LIMIT 1
    ");
    $ageGroupLabel = $stmt->fetchColumn() ?: 'N/A';

    $stmt = $pdo->query("
        SELECT
            SUM(CASE WHEN LOWER(gender) IN ('female', 'f') THEN 1 ELSE 0 END) AS female_count,
            SUM(CASE WHEN LOWER(gender) IN ('male', 'm') THEN 1 ELSE 0 END) AS male_count,
            COUNT(*) AS total
        FROM patients
        WHERE gender IS NOT NULL AND gender <> ''
    ");
    $genderRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['female_count' => 0, 'male_count' => 0, 'total' => 0];
    $genderTotal = (int)($genderRow['total'] ?? 0);
    if ($genderTotal > 0) {
        $femalePct = (int)round(((int)$genderRow['female_count'] / $genderTotal) * 100);
        $malePct = 100 - $femalePct;
        $genderDistribution = $femalePct . '% F / ' . $malePct . '% M';
    }

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

    $stmt = $pdo->prepare("
        SELECT u.full_name, COUNT(*) AS total
        FROM treatments t
        JOIN users u ON u.id = t.doctor_id
        WHERE u.role = 'dentist'
          AND t.treatment_date BETWEEN ? AND ?
        GROUP BY u.id
        ORDER BY total DESC
        LIMIT 1
    ");
    $stmt->execute([$startStr, $endStr]);
    $topDentist = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($topDentist) {
        $topDentistName = $topDentist['full_name'] ?: 'Dentist';
        $topDentistCount = (int)$topDentist['total'];
    }

    $stmt = $pdo->prepare("
        SELECT u.full_name, COUNT(*) AS total
        FROM appointments a
        JOIN users u ON u.id = a.created_by
        WHERE u.role = 'staff'
          AND a.appointment_date BETWEEN ? AND ?
        GROUP BY u.id
        ORDER BY total DESC
        LIMIT 1
    ");
    $stmt->execute([$startStr, $endStr]);
    $topStaff = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($topStaff) {
        $topStaffName = $topStaff['full_name'] ?: 'Staff';
        $topStaffCount = (int)$topStaff['total'];
    }
} catch (Exception $e) {
    // Keep defaults on error
}
?>
            <div class="content-main">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Analytics Dashboard</h2>
                    <select class="filter-select" id="analyticsPeriod">
                        <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>This Quarter</option>
                        <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>This Year</option>
                    </select>
                </div>

                <!-- Key Metrics -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon green">&#128176;</div>
                        <div class="summary-info">
                            <h3>&#8369;<?php echo number_format($monthlyRevenue, 2); ?></h3>
                            <p>Monthly Revenue</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon blue">&#128203;</div>
                        <div class="summary-info">
                            <h3>&#8369;<?php echo number_format($totalCollected, 2); ?></h3>
                            <p>Total Collected</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon yellow">&#9203;</div>
                        <div class="summary-info">
                            <h3>&#8369;<?php echo number_format($pendingPayments, 2); ?></h3>
                            <p>Pending Payment</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon blue">&#128101;</div>
                        <div class="summary-info">
                            <h3><?php echo number_format($todaysPatients); ?></h3>
                            <p>Today's Patients</p>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="charts-row">
                    <!-- Revenue Chart -->
                    <div class="chart-card">
                        <h3 class="chart-title">Revenue Overview</h3>
                        <div class="chart-placeholder">
                            <div class="bar-chart" id="revenueBars"></div>
                        </div>
                    </div>

                    <!-- Appointments Chart -->
                    <div class="chart-card">
                        <h3 class="chart-title">Appointments by Type</h3>
                        <div class="chart-placeholder">
                            <div class="pie-chart">
                                <div class="pie-segment" style="--percentage: 35; --color: #3b82f6;"></div>
                                <div class="pie-segment" style="--percentage: 25; --color: #10b981;"></div>
                                <div class="pie-segment" style="--percentage: 20; --color: #f59e0b;"></div>
                                <div class="pie-segment" style="--percentage: 20; --color: #ef4444;"></div>
                            </div>
                            <div class="chart-legend">
                                <span class="legend-item"><span class="legend-color" style="background: #3b82f6;"></span> Root Canal</span>
                                <span class="legend-item"><span class="legend-color" style="background: #10b981;"></span> Cleaning</span>
                                <span class="legend-item"><span class="legend-color" style="background: #f59e0b;"></span> Extraction</span>
                                <span class="legend-item"><span class="legend-color" style="background: #ef4444;"></span> Other</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Grid -->
                <div class="stats-grid">
                    <!-- Patient Demographics -->
                    <div class="stat-card">
                        <h3 class="stat-title">Patient Demographics</h3>
                        <div class="stat-content">
                            <div class="stat-row">
                                <span>New Patients (This Month)</span>
                                <span class="stat-value"><?php echo number_format($newPatients); ?></span>
                            </div>
                            <div class="stat-row">
                                <span>Returning Patients</span>
                                <span class="stat-value"><?php echo number_format($returningPatients); ?></span>
                            </div>
                            <div class="stat-row">
                                <span>Most Common Age Group</span>
                                <span class="stat-value"><?php echo htmlspecialchars($ageGroupLabel); ?></span>
                            </div>
                            <div class="stat-row">
                                <span>Gender Distribution</span>
                                <span class="stat-value"><?php echo htmlspecialchars($genderDistribution); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Popular Services -->
                    <div class="stat-card">
                        <h3 class="stat-title">Top Services</h3>
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

                    <!-- Staff Performance -->
                    <div class="stat-card">
                        <h3 class="stat-title">Staff Performance</h3>
                        <div class="stat-content">
                            <div class="staff-stat">
                                <span class="staff-name"><?php echo htmlspecialchars($topDentistName); ?></span>
                                <span class="staff-metric"><?php echo number_format($topDentistCount); ?> patients treated</span>
                            </div>
                            <div class="staff-stat">
                                <span class="staff-name"><?php echo htmlspecialchars($topStaffName); ?></span>
                                <span class="staff-metric"><?php echo number_format($topStaffCount); ?> appointments managed</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

<script>
    const revenueData = <?php echo json_encode($monthlyRevenueData); ?>;
    const barContainer = document.getElementById('revenueBars');
    if (barContainer) {
        const map = {};
        revenueData.forEach(item => {
            map[item.ym] = { label: item.month_label, total: Number(item.total) };
        });

        const months = [];
        const year = new Date().getFullYear();
        const labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        for (let m = 0; m < 12; m++) {
            const ym = `${year}-${String(m + 1).padStart(2, '0')}`;
            const label = labels[m];
            const entry = map[ym] || { label, total: 0 };
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

    document.getElementById('analyticsPeriod')?.addEventListener('change', function () {
        const params = new URLSearchParams(window.location.search);
        params.set('period', this.value);
        window.location.search = params.toString();
    });
</script>

<?php
require_once __DIR__ . '/includes/admin_layout_end.php';
?>
