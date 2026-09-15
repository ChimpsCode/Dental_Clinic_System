<?php
/**
 * Analytics - Admin page for viewing clinic analytics and statistics
 */

$pageTitle = 'Analytics';

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/admin_layout_start.php';

$period = $_GET['period'] ?? 'month';
$period = in_array($period, ['week', 'month', 'quarter', 'year'], true) ? $period : 'month';

$today = new DateTime('today');
$startDate = clone $today;
$endDate = clone $today;
$occurEndDate = clone $today;

switch ($period) {
    case 'week':
        $startDate = (clone $today)->modify('-6 days');
        $occurEndDate = (clone $today)->modify('+6 days');
        break;
    case 'quarter':
        $startDate = (clone $today)->modify('-3 months');
        $occurEndDate = (clone $today)->modify('+3 months');
        break;
    case 'year':
        $startDate = (clone $today)->modify('-12 months');
        $occurEndDate = (clone $today)->modify('+12 months');
        break;
    case 'month':
    default:
        $startDate = (clone $today)->modify('-29 days');
        $occurEndDate = (clone $today)->modify('+29 days');
        break;
}

$startStr = $startDate->format('Y-m-d');
$endStr = $endDate->format('Y-m-d');
$occurEndStr = $occurEndDate->format('Y-m-d');

$totalPatients = 0;
$appointmentsCount = 0;
$revenueTotal = 0;
$avgWaitMinutes = 0;
$monthlyRevenue = 0;
$totalCollected = 0;
$pendingPayments = 0;
$todaysPatients = 0;
$monthlyRevenueData = [];
$chartYear = (int)date('Y');
$newPatients = 0;
$returningPatients = 0;
$ageGroupLabel = 'N/A';
$genderDistribution = 'N/A';
$topServices = [];
$topDentistName = 'Dentist';
$topDentistCount = 0;
$topStaffName = 'Staff';
$topStaffCount = 0;
$appointmentTypeSlices = [];
$appointmentTypeGradient = '#e5e7eb';
$appointmentTypeTotal = 0;
$periodStats = [
    'today' => ['inquiries' => 0, 'appointments' => 0],
    'week' => ['inquiries' => 0, 'appointments' => 0],
    'month' => ['inquiries' => 0, 'appointments' => 0],
    'total' => ['inquiries' => 0, 'appointments' => 0],
];

try {
    $totalPatients = (int)($pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn() ?? 0);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ?");
    $stmt->execute([$startStr, $endStr]);
    $appointmentsCount = (int)($stmt->fetchColumn() ?? 0);

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(paid_amount), 0) FROM billing WHERE billing_date BETWEEN ? AND ?");
    $stmt->execute([$startStr, $endStr]);
    $revenueTotal = (float)($stmt->fetchColumn() ?? 0);

    $chartYear = (int)($pdo->query("
        SELECT COALESCE(MAX(YEAR(billing_date)), YEAR(CURDATE()))
        FROM billing
    ")->fetchColumn() ?? date('Y'));

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(paid_amount), 0)
        FROM billing
        WHERE YEAR(billing_date) = ?
          AND MONTH(billing_date) = MONTH(CURDATE())
    ");
    $stmt->execute([$chartYear]);
    $monthlyRevenue = (float)($stmt->fetchColumn() ?? 0);

    $totalCollected = (float)($pdo->query("
        SELECT COALESCE(SUM(paid_amount), 0)
        FROM billing
        WHERE payment_status = 'paid'
    ")->fetchColumn() ?? 0);

    $pendingPayments = (float)($pdo->query("
        SELECT COALESCE(SUM(balance), 0)
        FROM billing
        WHERE (payment_status IN ('pending', 'unpaid', 'partial')
           OR (balance IS NOT NULL AND balance > 0))
          AND LOWER(COALESCE(payment_status, '')) != 'cancelled'
    ")->fetchColumn() ?? 0);

    $todaysPatients = (int)($pdo->query("
        SELECT COUNT(*)
        FROM patients
        WHERE DATE(created_at) = CURDATE()
    ")->fetchColumn() ?? 0);

        $periodStatsStmt = $pdo->query("
                SELECT
                        (SELECT COUNT(*) FROM inquiries
                         WHERE COALESCE(is_archived, 0) = 0
                             AND created_at >= CURDATE()
                             AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)) AS inquiries_today,
                        (SELECT COUNT(*) FROM inquiries
                         WHERE COALESCE(is_archived, 0) = 0
                             AND created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                             AND created_at < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)) AS inquiries_week,
                        (SELECT COUNT(*) FROM inquiries
                         WHERE COALESCE(is_archived, 0) = 0
                             AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                             AND created_at < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)) AS inquiries_month,
                        (SELECT COUNT(*) FROM appointments
                         WHERE COALESCE(is_archived, 0) = 0
                             AND appointment_date = CURDATE()) AS appointments_today,
                        (SELECT COUNT(*) FROM appointments
                         WHERE COALESCE(is_archived, 0) = 0
                             AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                             AND appointment_date < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)) AS appointments_week,
                        (SELECT COUNT(*) FROM appointments
                         WHERE COALESCE(is_archived, 0) = 0
                             AND appointment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                             AND appointment_date < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)) AS appointments_month,
                        (SELECT COUNT(*) FROM inquiries
                         WHERE COALESCE(is_archived, 0) = 0) AS inquiries_total,
                        (SELECT COUNT(*) FROM appointments
                         WHERE COALESCE(is_archived, 0) = 0) AS appointments_total
        ");
        $periodStatsRow = $periodStatsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $periodStats = [
                'today' => ['inquiries' => (int)($periodStatsRow['inquiries_today'] ?? 0), 'appointments' => (int)($periodStatsRow['appointments_today'] ?? 0)],
                'week' => ['inquiries' => (int)($periodStatsRow['inquiries_week'] ?? 0), 'appointments' => (int)($periodStatsRow['appointments_week'] ?? 0)],
                'month' => ['inquiries' => (int)($periodStatsRow['inquiries_month'] ?? 0), 'appointments' => (int)($periodStatsRow['appointments_month'] ?? 0)],
                'total' => ['inquiries' => (int)($periodStatsRow['inquiries_total'] ?? 0), 'appointments' => (int)($periodStatsRow['appointments_total'] ?? 0)],
        ];

    // Revenue series for full year (2026)
    $stmt = $pdo->prepare("
        SELECT
            DATE_FORMAT(billing_date, '%Y-%m') AS ym,
            COALESCE(SUM(paid_amount), 0) AS total
        FROM billing
        WHERE billing_date BETWEEN ? AND ?
        GROUP BY ym
        ORDER BY ym ASC
    ");
    $stmt->execute([$chartYear . '-01-01', $chartYear . '-12-31']);
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

    // Top Dentist - join with roles table using role_id
    $stmt = $pdo->prepare("
        SELECT CONCAT(u.first_name, ' ', u.last_name) as full_name, COUNT(*) AS total
        FROM treatments t
        JOIN users u ON u.id = t.doctor_id
        JOIN roles r ON r.id = u.role_id
        WHERE LOWER(r.role_name) = 'dentist'
          AND t.treatment_date BETWEEN ? AND ?
        GROUP BY u.id
        ORDER BY total DESC
        LIMIT 1
    ");
    $stmt->execute([$startStr, $endStr]);
    $topDentist = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($topDentist) {
        $topDentistName = trim($topDentist['full_name'] ?: '') ?: 'Dentist';
        $topDentistCount = (int)$topDentist['total'];
    }

    // Top Staff - join with roles table using role_id
    $stmt = $pdo->prepare("
        SELECT CONCAT(u.first_name, ' ', u.last_name) as full_name, COUNT(*) AS total
        FROM appointments a
        JOIN users u ON u.id = a.created_by
        JOIN roles r ON r.id = u.role_id
        WHERE LOWER(r.role_name) = 'staff'
          AND a.appointment_date BETWEEN ? AND ?
        GROUP BY u.id
        ORDER BY total DESC
        LIMIT 1
    ");
    $stmt->execute([$startStr, $endStr]);
    $topStaff = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($topStaff) {
        $topStaffName = trim($topStaff['full_name'] ?: '') ?: 'Staff';
        $topStaffCount = (int)$topStaff['total'];
    }
} catch (Exception $e) {
    // Keep defaults on error
}

try {
    $stmt = $pdo->prepare("
        SELECT service_name, COUNT(*) AS total
        FROM (
            SELECT s.name AS service_name
            FROM appointments a
            JOIN services s ON a.service_id = s.id
            WHERE a.service_id IS NOT NULL AND a.service_id > 0
              AND a.appointment_date BETWEEN ? AND ?

            UNION ALL

            SELECT COALESCE(s.name, q.treatment_type) AS service_name
            FROM queue q
            LEFT JOIN services s ON q.service_id = s.id
            WHERE (
                    (q.service_id IS NOT NULL AND q.service_id > 0)
                    OR (q.treatment_type IS NOT NULL AND q.treatment_type != '')
                  )
              AND COALESCE(q.queue_date, DATE(q.created_at)) BETWEEN ? AND ?
        ) combined
        WHERE service_name IS NOT NULL AND service_name != ''
        GROUP BY service_name
        ORDER BY total DESC
    ");
    $stmt->execute([$startStr, $endStr, $startStr, $endStr]);
    $appointmentTypeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $topServices = array_slice($appointmentTypeRows, 0, 4);

    $pieColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'];
    $topTypes = array_slice($appointmentTypeRows, 0, 3);
    $otherCount = 0;
    foreach (array_slice($appointmentTypeRows, 3) as $otherRow) {
        $otherCount += (int)($otherRow['total'] ?? 0);
    }

    foreach ($topTypes as $index => $typeRow) {
        $count = (int)($typeRow['total'] ?? 0);
        if ($count <= 0) {
            continue;
        }
        $appointmentTypeSlices[] = [
            'label' => (string)($typeRow['service_name'] ?? 'Unspecified'),
            'count' => $count,
            'color' => $pieColors[$index] ?? $pieColors[0],
        ];
        $appointmentTypeTotal += $count;
    }
    if ($otherCount > 0) {
        $appointmentTypeSlices[] = [
            'label' => 'Other',
            'count' => $otherCount,
            'color' => $pieColors[3],
        ];
        $appointmentTypeTotal += $otherCount;
    }

    if ($appointmentTypeTotal > 0) {
        $cursorDeg = 0.0;
        $gradientParts = [];
        foreach ($appointmentTypeSlices as $slice) {
            $spanDeg = ($slice['count'] / $appointmentTypeTotal) * 360;
            $fromDeg = $cursorDeg;
            $toDeg = $cursorDeg + $spanDeg;
            $gradientParts[] = $slice['color'] . ' ' . round($fromDeg, 2) . 'deg ' . round($toDeg, 2) . 'deg';
            $cursorDeg = $toDeg;
        }
        $appointmentTypeGradient = 'conic-gradient(' . implode(', ', $gradientParts) . ')';
    }
} catch (Exception $e) {
    $topServices = [];
    $appointmentTypeSlices = [];
    $appointmentTypeGradient = '#e5e7eb';
    $appointmentTypeTotal = 0;
}
?>
            <div class="content-main">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Analytics Dashboard</h2>
                    <select class="filter-select" id="analyticsPeriod">
                        <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Last 7 days</option>
                        <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Last 30 days</option>
                        <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>Last 3 months</option>
                        <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Last 12 months</option>
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
                <div class="charts-row" style="margin-bottom: 0px;">
                    <!-- Revenue Chart -->
                    <div class="chart-card">
                        <h3 class="chart-title">Revenue Overview</h3>
                        <div class="dash-revenue-chart" style="display: flex; align-items: flex-end; gap: 10px; height: 180px; padding: 10px 15px 30px 15px; border-bottom: 1px solid #e5e7eb;">
                                <?php 
                                // Get all 12 months for the chart year
                                $allMonths = [];
                                for ($m = 1; $m <= 12; $m++) {
                                    $ym = sprintf('%d-%02d', $chartYear, $m);
                                    $allMonths[$ym] = 0;
                                }
                                
                                // Fetch actual revenue data
                                $revenueStmt = $pdo->prepare("
                                    SELECT DATE_FORMAT(billing_date, '%Y-%m') AS ym, SUM(paid_amount) AS total
                                    FROM billing
                                    WHERE YEAR(billing_date) = ?
                                    GROUP BY ym
                                ");
                                $revenueStmt->execute([$chartYear]);
                                while ($row = $revenueStmt->fetch(PDO::FETCH_ASSOC)) {
                                    if (isset($allMonths[$row['ym']])) {
                                        $allMonths[$row['ym']] = (float)$row['total'];
                                    }
                                }
                                
                                $maxRevenue = max($allMonths);
                                if ($maxRevenue == 0) $maxRevenue = 1;
                                $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                                
                                foreach ($allMonths as $ym => $total) {
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
                                    <div class="dash-revenue-col" title="<?php echo $label; ?>: ₱<?php echo number_format($total); ?>">
                                        <div class="dash-revenue-bar" style="height: <?php echo $height; ?>%; min-height: 15px; background: <?php echo $bg; ?>;"></div>
                                        <span class="dash-revenue-label"><?php echo $label; ?></span>
                                    </div>
                                    <?php
                                }
                                ?>
                        </div>
                    </div>

                    <!-- Appointments Chart -->
                    <div class="chart-card">
                        <h3 class="chart-title">Appointments by Type</h3>
                        <div class="chart-placeholder">
                            <div class="pie-chart analytics-pie-chart" style="background: <?php echo htmlspecialchars($appointmentTypeGradient, ENT_QUOTES, 'UTF-8'); ?>;"></div>
                            <div class="chart-legend analytics-pie-legend">
                                <?php if ($appointmentTypeTotal > 0): ?>
                                    <?php foreach ($appointmentTypeSlices as $slice): ?>
                                        <?php $pct = (int)round(($slice['count'] / $appointmentTypeTotal) * 100); ?>
                                        <span class="legend-item">
                                            <span class="legend-color" style="background: <?php echo htmlspecialchars($slice['color'], ENT_QUOTES, 'UTF-8'); ?>;"></span>
                                            <?php echo htmlspecialchars($slice['label']); ?>
                                            (<?php echo number_format($slice['count']); ?> · <?php echo $pct; ?>%)
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="legend-item">No appointments</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <section class="period-statistics" id="periodStatistics" data-view="inquiry" aria-label="Inquiry and appointment statistics">
                    <div class="period-statistics-header">
                        <div>
                            <h2 id="periodStatisticsTitle">Inquiry Statistics</h2>
                            <p>Automatically calculated from active records.</p>
                        </div>
                        <select class="filter-select" id="periodStatisticsView" aria-label="Choose statistics type">
                            <option value="inquiry" selected>Inquiry Statistics</option>
                            <option value="appointment">Appointment Statistics</option>
                        </select>
                    </div>
                    <div class="period-statistics-grid">
                        <?php foreach (['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'total' => 'Total'] as $periodKey => $periodLabel): ?>
                            <div class="period-stat-card"
                                 data-inquiries="<?php echo (int)$periodStats[$periodKey]['inquiries']; ?>"
                                 data-appointments="<?php echo (int)$periodStats[$periodKey]['appointments']; ?>">
                                <h3><?php echo $periodLabel; ?></h3>
                                <div class="period-stat-row">
                                    <span class="period-stat-label">Patient inquiries</span>
                                    <strong class="period-stat-value"><?php echo number_format($periodStats[$periodKey]['inquiries']); ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Statistics Grid -->
                <div class="stats-grid" style="margin-bottom:25px; padding-bottom: 35px;">
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
    document.getElementById('analyticsPeriod')?.addEventListener('change', function () {
        const params = new URLSearchParams(window.location.search);
        params.set('period', this.value);
        window.location.search = params.toString();
    });

    (function () {
        const section = document.getElementById('periodStatistics');
        const select = document.getElementById('periodStatisticsView');
        const title = document.getElementById('periodStatisticsTitle');
        if (!section || !select || !title) return;

        function applyPeriodView(view) {
            const isAppointment = view === 'appointment';
            section.setAttribute('data-view', view);
            title.textContent = isAppointment ? 'Appointment Statistics' : 'Inquiry Statistics';
            section.querySelectorAll('.period-stat-card').forEach(function (card) {
                const value = isAppointment ? card.getAttribute('data-appointments') : card.getAttribute('data-inquiries');
                const label = card.querySelector('.period-stat-label');
                const strong = card.querySelector('.period-stat-value');
                if (label) label.textContent = isAppointment ? 'Appointments' : 'Patient inquiries';
                if (strong) strong.textContent = Number(value || 0).toLocaleString();
            });
        }

        select.addEventListener('change', function () {
            applyPeriodView(this.value);
        });
        applyPeriodView(select.value);
    })();
</script>
<style>
    .content-main {
        
        margin-bottom: 40px;
    }
    .period-statistics {
        padding: 20px;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        background: #ffffff;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
    }
    .period-statistics-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 16px;
    }
    .period-statistics-header h2 {
        margin: 0;
        color: #1f2937;
        font-size: 1.05rem;
    }
    .period-statistics-header p {
        margin: 4px 0 0;
        color: #64748b;
        font-size: 0.82rem;
    }
    .period-statistics-header .filter-select {
        flex-shrink: 0;
        min-width: 220px;
    }
    .period-statistics-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }
    .period-stat-card {
        padding: 16px;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: #f8fafc;
    }
    .period-stat-card h3 {
        margin: 0 0 10px;
        color: #1d4ed8;
        font-size: 0.95rem;
    }
    .period-stat-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 8px 0;
        border-top: 1px solid #e2e8f0;
        color: #64748b;
        font-size: 0.82rem;
    }
    .period-stat-row strong {
        color: #0f172a;
        font-size: 1.1rem;
    }
    @media (max-width: 900px) {
        .period-statistics-header { flex-direction: column; }
        .period-statistics-header .filter-select { width: 100%; min-width: 0; }
        .period-statistics-grid { grid-template-columns: 1fr 1fr; }
    }
</style>
<?php
require_once __DIR__ . '/../../includes/admin_layout_end.php';
?>
