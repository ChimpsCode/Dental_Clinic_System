<?php
/**
 * Reports - Admin page for printable reports
 */

$pageTitle = 'Reports';

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/admin_layout_start.php';

$patientsReport = [];
$appointmentsReport = [];
$billingReport = [];
$revenueReport = [];
$servicesReport = [];
$dailySummaryReport = [];
$printedInvoices = [];
$printedDaily = [];

// Get date filter from GET parameter
$dateFilter = $_GET['date_filter'] ?? 'all';
$dateCondition = '';
$groupBy = '';
switch ($dateFilter) {
    case 'today':
        $dateCondition = " AND DATE(b.billing_date) = CURDATE()";
        break;
    case 'week':
        $dateCondition = " AND b.billing_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $dateCondition = " AND b.billing_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
    case 'all':
    default:
        $dateCondition = "";
        break;
}

// Check if filtering by specific month (format: 2026-01)
$selectedMonth = $_GET['month'] ?? '';
if (!empty($selectedMonth)) {
    if (preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
        $dateCondition = " AND DATE_FORMAT(b.billing_date, '%Y-%m') = '" . $selectedMonth . "'";
        $dateFilter = 'month';
    } else {
        $selectedMonth = '';
    }
}

function buildReportTable($headers, $rows) {
    $thead = '<tr>' . implode('', array_map(fn($h) => '<th>' . htmlspecialchars($h) . '</th>', $headers)) . '</tr>';
    if (empty($rows)) {
        $tbody = '<tr><td colspan="' . count($headers) . '" style="text-align:center;color:#6b7280;">No records found</td></tr>';
    } else {
        $tbodyRows = [];
        foreach ($rows as $row) {
            $cells = array_map(fn($c) => '<td>' . htmlspecialchars((string)$c) . '</td>', $row);
            $tbodyRows[] = '<tr>' . implode('', $cells) . '</tr>';
        }
        $tbody = implode('', $tbodyRows);
    }
    return '<table class="print-table"><thead>' . $thead . '</thead><tbody>' . $tbody . '</tbody></table>';
}

try {
    // Patient Report - fetch all patient data
    $patientsReport = $pdo->query("
        SELECT *
        FROM patients
        ORDER BY id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $checkCol = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'is_archived'");
    $hasArchiveColumn = $checkCol->rowCount() > 0;
    $appointmentWhere = $hasArchiveColumn ? "WHERE (a.is_archived = 0 OR a.is_archived IS NULL)" : "WHERE 1=1";
    $appointmentDateCondition = '';
    switch ($dateFilter) {
        case 'today':
            $appointmentDateCondition = " AND a.appointment_date = CURDATE()";
            break;
        case 'week':
            $appointmentDateCondition = " AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)"
                . " AND a.appointment_date < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)";
            break;
        case 'month':
            if ($selectedMonth !== '') {
                $appointmentDateCondition = " AND DATE_FORMAT(a.appointment_date, '%Y-%m') = '" . $selectedMonth . "'";
            } else {
                $appointmentDateCondition = " AND a.appointment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
                    . " AND a.appointment_date < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)";
            }
            break;
    }

    $appointmentsReport = $pdo->query("
        SELECT a.id, p.first_name, p.middle_name, p.last_name,
               a.appointment_date, a.appointment_time, a.status
        FROM appointments a
        LEFT JOIN patients p ON p.id = a.patient_id
        " . $appointmentWhere . $appointmentDateCondition . "
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $billingReport = $pdo->query("
        SELECT b.id, CONCAT(p.first_name, ' ', p.last_name) AS patient,
               b.total_amount, b.paid_amount, b.balance, b.payment_status,
               b.billing_date, b.due_date, b.printed_at
        FROM billing b
        LEFT JOIN patients p ON p.id = b.patient_id
        ORDER BY b.billing_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Revenue Report - Grouped by month then service
    // Service name resolved via 4 paths: treatments→services, appointments→services, notes field, amount fallback
$revenueStmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(b.billing_date, '%Y-%m') AS month,
        COALESCE(
            NULLIF(s.name, ''),
            NULLIF(sa.name, ''),
            NULLIF(
                IF(LOCATE('Services: ', b.notes) > 0,
                   TRIM(SUBSTRING_INDEX(b.notes, 'Services: ', -1)),
                   ''),
                ''
            ),
            '—'
        ) AS service,
        COALESCE(s.price, sa.price, b.total_amount, 0) AS service_price,
        COUNT(b.id) AS quantity,
        COALESCE(SUM(CASE WHEN b.payment_status IN ('Paid', 'Partial') THEN b.paid_amount ELSE 0 END), 0) AS subtotal
    FROM billing b
    LEFT JOIN treatments t ON b.treatment_id = t.id
    LEFT JOIN services s ON t.service_id = s.id
    LEFT JOIN appointments a ON b.appointment_id = a.id
    LEFT JOIN services sa ON a.service_id = sa.id
    WHERE 1=1" . $dateCondition . "
    GROUP BY month, service, service_price
    ORDER BY month DESC, service ASC
");
$revenueStmt->execute();
$revenueRows = $revenueStmt->fetchAll(PDO::FETCH_ASSOC);

// Restructure into hierarchical array: month -> services
$revenueByMonth = [];
foreach ($revenueRows as $row) {
    $month = $row['month'];
    if (!isset($revenueByMonth[$month])) {
        $revenueByMonth[$month] = [
            'label' => date('F Y', strtotime($month . '-01')),
            'services' => [],
            'monthly_total' => 0
        ];
    }
    $revenueByMonth[$month]['services'][] = [
        'service' => $row['service'],
        'service_price' => $row['service_price'],
        'quantity' => $row['quantity'],
        'subtotal' => $row['subtotal']
    ];
    $revenueByMonth[$month]['monthly_total'] += (float)$row['subtotal'];
}
krsort($revenueByMonth);
$annualTotal = array_sum(array_column($revenueByMonth, 'monthly_total'));

$revenueReport = [
    'months' => $revenueByMonth,
    'annual_total' => $annualTotal
];

// Get available months for dropdown
$availableMonths = $pdo->query("
    SELECT DISTINCT DATE_FORMAT(billing_date, '%Y-%m') AS month 
    FROM billing 
    WHERE billing_date IS NOT NULL 
    ORDER BY month DESC
")->fetchAll(PDO::FETCH_ASSOC);

    $printedInvoices = $pdo->query("
        SELECT b.id, CONCAT(p.first_name, ' ', p.last_name) AS patient,
               b.total_amount, b.payment_status, b.printed_at
        FROM billing b
        LEFT JOIN patients p ON p.id = b.patient_id
        WHERE b.printed_at IS NOT NULL
        ORDER BY b.printed_at DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);

    $printedDaily = $pdo->query("
        SELECT DATE(printed_at) AS day, COUNT(*) AS printed_count
        FROM billing
        WHERE printed_at IS NOT NULL
          AND printed_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(printed_at)
        ORDER BY day ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Services Report - Summary of services rendered with frequency and revenue
    $servicesReport = [];

    // Revenue lookup by service name from billing (using same COALESCE logic as Revenue Report)
    // Joined to treatments via patient_id + date since billing.treatment_id is always NULL
    $revenueByService = [];
    $revenueServiceStmt = $pdo->prepare("
        SELECT 
            COALESCE(
                NULLIF(s.name, ''),
                NULLIF(sa.name, ''),
                NULLIF(
                    IF(LOCATE('Services: ', b.notes) > 0,
                       TRIM(SUBSTRING_INDEX(b.notes, 'Services: ', -1)),
                       ''),
                    ''
                ),
                '—'
            ) AS service,
            COALESCE(SUM(CASE WHEN b.payment_status IN ('Paid', 'Partial') THEN b.paid_amount ELSE 0 END), 0) AS revenue
        FROM billing b
        LEFT JOIN treatments t ON b.patient_id = t.patient_id AND DATE(b.billing_date) = t.treatment_date
        LEFT JOIN services s ON t.service_id = s.id
        LEFT JOIN appointments a ON b.appointment_id = a.id
        LEFT JOIN services sa ON a.service_id = sa.id
        WHERE 1=1" . $dateCondition . "
        GROUP BY service
    ");
    $revenueServiceStmt->execute();
    foreach ($revenueServiceStmt->fetchAll(PDO::FETCH_ASSOC) as $rs) {
        $revenueByService[$rs['service']] = (float)$rs['revenue'];
    }

    // Get treatments grouped by service

    $treatments = $pdo->query("
        SELECT
            t.id,
            s.name AS service,
            t.cost
        FROM treatments t
        LEFT JOIN services s ON t.service_id = s.id
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($treatments)) {
        // Group by service
        $grouped = [];
        foreach ($treatments as $t) {
            $svc = $t['service'] ?? 'Unknown';
            if (!isset($grouped[$svc])) {
                $grouped[$svc] = ['frequency' => 0, 'treatment_cost' => 0, 'revenue' => 0];
            }
            $grouped[$svc]['frequency']++;
            $grouped[$svc]['treatment_cost'] += (float)$t['cost'];
        }

        // Merge revenue from billing
        foreach ($grouped as $service => &$stats) {
            $stats['revenue'] = $revenueByService[$service] ?? 0;
        }
        unset($stats);

        // Convert to indexed array
        foreach ($grouped as $service => $stats) {
            $servicesReport[] = array_merge(['service' => $service], $stats);
        }

        // Sort by frequency descending
        usort($servicesReport, fn($a, $b) => $b['frequency'] - $a['frequency']);
    }

    // Fallback to appointments table if no treatments found
    if (empty($servicesReport)) {
        $servicesReport = $pdo->query("
            SELECT COALESCE(s.name, 'Unknown') AS service, COUNT(*) AS frequency, 0 AS treatment_cost, 0 AS revenue
            FROM appointments a
            LEFT JOIN services s ON a.service_id = s.id
            WHERE s.name IS NOT NULL
            GROUP BY s.name
            ORDER BY frequency DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // Daily Summary - dynamic date range based on filter, revenue from billing
    $dailyStartDate = date('Y-m-d', strtotime('-29 days'));
    $dailyEndDate = date('Y-m-d');

    if (!empty($selectedMonth)) {
        $dailyStartDate = $selectedMonth . '-01';
        $dailyEndDate = date('Y-m-t', strtotime($dailyStartDate));
    } else {
        switch ($dateFilter) {
            case 'today':
                $dailyStartDate = date('Y-m-d');
                break;
            case 'week':
                $dailyStartDate = date('Y-m-d', strtotime('-6 days'));
                break;
        }
    }

    // Build date series in PHP
    $dailySummaryReport = [];
    $dailyPeriod = new DatePeriod(
        new DateTime($dailyStartDate),
        new DateInterval('P1D'),
        (new DateTime($dailyEndDate))->modify('+1 day')
    );
    foreach ($dailyPeriod as $dt) {
        $dayKey = $dt->format('Y-m-d');
        $dailySummaryReport[$dayKey] = [
            'day' => $dayKey,
            'new_patients' => 0,
            'appointments' => 0,
            'inquiries' => 0,
            'revenue' => 0
        ];
    }

    // New patients per day
        $stmt = $pdo->prepare("SELECT DATE(created_at) AS day, COUNT(*) AS total
                FROM patients
                WHERE COALESCE(is_archived, 0) = 0
                    AND DATE(created_at) BETWEEN ? AND ?
                GROUP BY DATE(created_at)");
    $stmt->execute([$dailyStartDate, $dailyEndDate]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $np) {
        if (isset($dailySummaryReport[$np['day']])) {
            $dailySummaryReport[$np['day']]['new_patients'] = (int)$np['total'];
        }
    }

    // Appointments per day
        $stmt = $pdo->prepare("SELECT appointment_date AS day, COUNT(*) AS total
                FROM appointments
                WHERE COALESCE(is_archived, 0) = 0
                    AND appointment_date BETWEEN ? AND ?
                GROUP BY appointment_date");
    $stmt->execute([$dailyStartDate, $dailyEndDate]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ad) {
        if (isset($dailySummaryReport[$ad['day']])) {
            $dailySummaryReport[$ad['day']]['appointments'] = (int)$ad['total'];
        }
    }

    // Inquiries received per day
    $stmt = $pdo->prepare("SELECT DATE(created_at) AS day, COUNT(*) AS total
        FROM inquiries
        WHERE COALESCE(is_archived, 0) = 0
          AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)");
    $stmt->execute([$dailyStartDate, $dailyEndDate]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $iq) {
        if (isset($dailySummaryReport[$iq['day']])) {
            $dailySummaryReport[$iq['day']]['inquiries'] = (int)$iq['total'];
        }
    }

    // Revenue per day from billing (paid_amount, not payments table)
    $stmt = $pdo->prepare("
        SELECT DATE(billing_date) AS day, COALESCE(SUM(paid_amount), 0) AS total
        FROM billing
        WHERE payment_status IN ('Paid', 'Partial') AND billing_date BETWEEN ? AND ?
        GROUP BY DATE(billing_date)
    ");
    $stmt->execute([$dailyStartDate, $dailyEndDate]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $rd) {
        if (isset($dailySummaryReport[$rd['day']])) {
            $dailySummaryReport[$rd['day']]['revenue'] = (float)$rd['total'];
        }
    }

    // Re-index to sequential
    $dailySummaryReport = array_values($dailySummaryReport);
} catch (Exception $e) {
    error_log('Reports page error: ' . $e->getMessage());
}
?>
            <div class="content-main">
                <div class="page-header">
                    <h2>Reports</h2>
                    <p class="page-subtitle">Generate and print printable reports</p>
                </div>

                <div class="section-card">
                    <h2 class="section-title">&#128203; Custom Report Generator</h2>
                    <div class="report-form">
                        <select id="customReportType" class="form-control">
                            <option value="patients">Patient Report</option>
                            <option value="appointments">Appointments Report</option>
                            <option value="billing">Billing Report</option>
                            <option value="revenue">Revenue Report</option>
                            <option value="services">Services Report</option>
                            <option value="daily">Daily Summary</option>
                        </select>
                        <select id="dateRange" class="form-control" onchange="applyDateFilter()">
                            <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>All Time</option>
                            <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo $dateFilter === 'month' && empty($selectedMonth) ? 'selected' : ''; ?>>This Month</option>
                            <optgroup label="By Month">
                                <?php foreach ($availableMonths as $m): ?>
                                <option value="<?php echo $m['month']; ?>" <?php echo $selectedMonth === $m['month'] ? 'selected' : ''; ?>><?php echo date('F Y', strtotime($m['month'] . '-01')); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                        
                        <button class="btn-secondary" onclick="rfGenerateCustomReport()">Generate</button>
                        <button class="btn-primary" onclick="rfPrintCustomReport()">Print</button>
                        <button class="btn-secondary" onclick="rfExportToPDF()">Export PDF</button>
                    </div>
                </div>
                
                <div class="reports-grid">
                    <div class="report-card">
                        <div class="report-icon">&#128101;</div>
                        <h3>Patient Report</h3>
                        <p>Complete list of all registered patients with contact information and visit history.</p>
                        <div class="report-actions">
                            <button class="btn-secondary" onclick="rfPreviewReport('patients')">Preview</button>
                            <button class="btn-primary" onclick="rfPrintReport('patients')">Print</button>
                        </div>
                    </div>      
                    <div class="report-card">
                        <div class="report-icon">&#128197;</div>
                        <h3>Appointments Report</h3>
                        <p>Detailed appointments log including completed, pending, and cancelled appointments.</p>
                        <div class="report-actions">
                            <button class="btn-secondary" onclick="rfPreviewReport('appointments')">Preview</button>
                            <button class="btn-primary" onclick="rfPrintReport('appointments')">&#128424; Print</button>
                        </div>
                    </div>
                    <div class="report-card">
                        <div class="report-icon">&#128176;</div>
                        <h3>Payment Report</h3>
                        <p>Financial summary including all transactions, payments received, and pending amounts.</p>
                        <div class="report-actions">
                            <button class="btn-secondary" onclick="rfPreviewReport('billing')">Preview</button>
                            <button class="btn-primary" onclick="rfPrintReport('billing')">&#128424; Print</button>
                        </div>
                    </div>
                    <div class="report-card">
                        <div class="report-icon">&#128200;</div>
                        <h3>Revenue Report</h3>
                        <p>Revenue breakdown by service type, time period, and payment status.</p>
                        <div class="report-actions">
                            <button class="btn-secondary" onclick="rfPreviewReport('revenue')">Preview</button>
                            <button class="btn-primary" onclick="rfPrintReport('revenue')">&#128424; Print</button>
                        </div>
                    </div>
                    <div class="report-card">
                        <div class="report-icon">&#129405;</div>
                        <h3>Services Report</h3>
                        <p>Summary of services rendered, frequency, and revenue by service type.</p>
                        <div class="report-actions">
                            <button class="btn-secondary" onclick="rfPreviewReport('services')">Preview</button>
                            <button class="btn-primary" onclick="rfPrintReport('services')">&#128424; Print</button>
                        </div>
                    </div>
                    <div class="report-card">
                        <div class="report-icon">&#128202;</div>
                        <h3>Daily Summary</h3>
                        <p>Day-by-day summary of patients, appointments, and revenue.</p>
                        <div class="report-actions">
                            <button class="btn-secondary" onclick="rfPreviewReport('daily')">Preview</button>
                            <button class="btn-primary" onclick="rfPrintReport('daily')">&#128424; Print</button>
                        </div>
                    </div>
                    <div class="report-card">
                        <div class="report-icon">&#128424;</div>
                        <h3>Printed Invoices</h3>
                        <p>Recently printed payment invoices with amount, status, and timestamp.</p>
                        <div class="report-actions">
                            <button class="btn-secondary" onclick="rfPreviewReport('printed')">Preview</button>
                            <button class="btn-primary" onclick="rfPrintReport('printed')">&#128424; Print</button>
                        </div>
                    </div>
                    <div class="report-card">
                        <div class="report-icon">&#128197;</div>
                        <h3>Print Activity</h3>
                        <p>Daily count of invoices printed in the last 7 days.</p>
                        <div class="report-actions">
                            <button class="btn-secondary" onclick="rfPreviewReport('printed_daily')">Preview</button>
                            <button class="btn-primary" onclick="rfPrintReport('printed_daily')">&#128424; Print</button>
                        </div>
                    </div>
                </div>

            </div>

            <div id="printPreviewModal" class="modal-overlay">
                <div class="modal" style="max-width: 900px;">
                    <div class="modal-header">
                        <h2>Report Preview</h2>
                        <div class="modal-actions">
                            <button class="btn-secondary" onclick="rfClosePreviewModal()">Close</button>
                            <button class="btn-primary" onclick="rfPrintCurrentReport()">&#128424; Print</button>
                        </div>
                    </div>
                    <div class="print-preview-content" id="printPreviewContent"></div>
                </div>
            </div>

<?php
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$reportPayload = [
    'patients' => $patientsReport,
    'appointments' => $appointmentsReport,
    'billing' => $billingReport,
    'revenue' => $revenueReport,
    'services' => $servicesReport,
    'daily' => $dailySummaryReport,
    'printed' => $printedInvoices,
    'printed_daily' => $printedDaily
];
$reportJson = json_encode($reportPayload, $jsonFlags);
?>
<script id="reportData" type="application/json"><?php echo $reportJson; ?></script>

<script>
let reportData = {};
try {
    const rawData = document.getElementById('reportData')?.textContent || '{}';
    reportData = JSON.parse(rawData);
} catch (error) {
    console.error("JSON Parse Error", error);
}

const reportTitles = {
    patients: 'Patient Report',
    appointments: 'Appointments Report',
    billing: 'Payment Report',
    revenue: 'Revenue Report',
    services: 'Services Report',
    printed: 'Recently Printed Invoices',
    printed_daily: 'Print Activity (Last 7 Days)',
    daily: 'Daily Summary'
};

let lastPreviewType = 'patients';

function buildTable(headers, rows) {
    const thead = `<tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr>`;
    let tbody = rows.map(r => `<tr>${r.map(c => `<td>${c}</td>`).join('')}</tr>`).join('');
    if (!tbody) {
        tbody = `<tr><td colspan="${headers.length}" style="text-align:center;color:#6b7280;">No records found</td></tr>`;
    }
    return `<table class="print-table"><thead>${thead}</thead><tbody>${tbody}</tbody></table>`;
}

function renderNewReport(type) {
    const data = getReportData(type);
    let headers = [];
    let rows = [];

    if (type === 'patients') {
        headers = ['No', 'First Name', 'Middle Name', 'Last Name', 'Suffix', 'Gender', 'Phone', 'Email', 'DOB', 'Archived', 'Created'];
        rows = data.map(p => [
            p.id ?? '', p.first_name ?? '', p.middle_name ?? '', p.last_name ?? '', p.suffix ?? '',
            p.gender ?? '', p.phone ?? '', p.email ?? '', p.date_of_birth ?? '',
            p.is_archived ? 'Yes' : 'No', p.created_at ? new Date(p.created_at).toLocaleDateString() : ''
        ]);
    } else if (type === 'appointments') {
        headers = ['ID', 'First Name', 'Middle Name', 'Last Name', 'Date', 'Time', 'Status'];
        rows = data.map(a => [
            a.id ?? '', a.first_name ?? '', a.middle_name ?? '', a.last_name ?? '',
            a.appointment_date ?? '', a.appointment_time ?? '', a.status ?? ''
        ]);
    } else if (type === 'billing') {
        headers = ['Invoice', 'Patient', 'Total', 'Paid', 'Balance', 'Status', 'Date', 'Due'];
        rows = data.map(b => [
            `INV-${String(b.id).padStart(4, '0')}`, b.patient ?? '',
            `₱${Number(b.total_amount || 0).toLocaleString()}`, `₱${Number(b.paid_amount || 0).toLocaleString()}`,
            `₱${Number(b.balance || 0).toLocaleString()}`, b.payment_status ?? '', b.billing_date ?? '', b.due_date ?? ''
        ]);
    } else if (type === 'revenue') {
        const months = data.months || {};
        const annualTotal = data.annual_total || 0;
        const monthKeys = Object.keys(months);
        if (monthKeys.length === 0) {
            headers = ['Service Name', 'Service Price', 'Quantity', 'Subtotal'];
            rows = [['No records found', '', '', '']];
        } else {
            let htmlParts = [];
            monthKeys.forEach((monthKey, i) => {
                const md = months[monthKey];
                const svcHeaders = ['Service Name', 'Service Price', 'Quantity', 'Subtotal'];
                const svcRows = md.services.map(s => [
                    s.service || 'Unknown',
                    `₱${Number(s.service_price || 0).toLocaleString()}`,
                    s.quantity || 0,
                    `₱${Number(s.subtotal || 0).toLocaleString()}`
                ]);
                svcRows.push([
                    '<strong>TOTAL</strong>',
                    '',
                    '',
                    `<strong>₱${Number(md.monthly_total || 0).toLocaleString()}</strong>`
                ]);
                const tbl = buildTable(svcHeaders, svcRows);
                htmlParts.push(
                    `<div style="margin-top:${i > 0 ? '28' : '0'}px;">`,
                    `<div style="font-size:15px;font-weight:700;margin-bottom:8px;color:#1f2937;">${md.label}</div>`,
                    tbl,
                    `</div>`
                );
            });
            htmlParts.push(
                `<div style="text-align:right;margin-top:24px;padding-top:14px;border-top:3px double #111;font-size:16px;font-weight:800;">Total Annual Revenue: ₱${Number(annualTotal).toLocaleString()}</div>`
            );
            return `
        <div class="print-report">
            <div class="print-header">
                <div class="print-brand">
                    <div class="print-logo">RF</div>
                    <div>
                        <div class="print-title">RF Dental Clinic</div>
                        <div class="print-subtitle">${reportTitles[type]}</div>
                    </div>
                </div>
                <div class="print-meta">
                    <div>Generated: ${new Date().toLocaleString()}</div>
                </div>
            </div>
            <div class="print-body">${htmlParts.join('')}</div>
            <div class="print-footer">
                <div>Confidential - For internal use only</div>
                <div>RF Dental Clinic</div>
            </div>
        </div>
    `;
        }
    } else if (type === 'services') {
        headers = ['Service', 'Frequency', 'Treatment Cost', 'Revenue'];
        rows = data.map(s => [
            s.service ?? 'Service',
            s.frequency ?? 0,
            `₱${Number(s.treatment_cost || 0).toLocaleString()}`,
            `₱${Number(s.revenue || 0).toLocaleString()}`
        ]);
    } else if (type === 'daily') {
        headers = ['Date', 'New Patients', 'Inquiries', 'Appointments', 'Revenue'];
        rows = data.map(d => [d.day ?? '', d.new_patients ?? 0, d.inquiries ?? 0, d.appointments ?? 0, `₱${Number(d.revenue || 0).toLocaleString()}`]);
    } else if (type === 'printed') {
        headers = ['Invoice #', 'Patient', 'Total Amount', 'Payment Status', 'Printed At'];
        rows = data.map(p => [
            `INV-${String(p.id).padStart(4, '0')}`,
            p.patient ?? '',
            `₱${Number(p.total_amount || 0).toLocaleString()}`,
            p.payment_status ?? '',
            p.printed_at ? new Date(p.printed_at).toLocaleString() : ''
        ]);
    } else if (type === 'printed_daily') {
        headers = ['Date', 'Printed Count'];
        rows = data.map(d => [d.day ?? '', d.printed_count ?? 0]);
    }

    const table = buildTable(headers, rows);
    return `
        <div class="print-report">
            <div class="print-header">
                <div class="print-brand">
                    <div class="print-logo">RF</div>
                    <div>
                        <div class="print-title">RF Dental Clinic</div>
                        <div class="print-subtitle">${reportTitles[type]}</div>
                    </div>
                </div>
                <div class="print-meta">
                    <div>Generated: ${new Date().toLocaleString()}</div>
                </div>
            </div>
            <div class="print-body">${table}</div>
            <div class="print-footer">
                <div>Confidential - For internal use only</div>
                <div>RF Dental Clinic</div>
            </div>
        </div>
    `;
}

function getReportData(type) {
    const source = reportData[type] || [];
    if (type !== 'patients' && type !== 'billing') return source;

    const selectedRange = document.getElementById('dateRange')?.value || 'all';
    if (selectedRange === 'all') return source;

    const now = new Date();
    const start = new Date(now);
    let end = new Date(now);
    end.setHours(23, 59, 59, 999);

    if (/^\d{4}-\d{2}$/.test(selectedRange)) {
        const [year, month] = selectedRange.split('-').map(Number);
        start.setFullYear(year, month - 1, 1);
        start.setHours(0, 0, 0, 0);
        end = new Date(year, month, 0, 23, 59, 59, 999);
    } else if (selectedRange === 'today') {
        start.setHours(0, 0, 0, 0);
    } else if (selectedRange === 'week') {
        start.setDate(start.getDate() - 6);
        start.setHours(0, 0, 0, 0);
    } else if (selectedRange === 'month') {
        start.setDate(start.getDate() - 29);
        start.setHours(0, 0, 0, 0);
    } else {
        return source;
    }

    const dateField = type === 'patients' ? 'created_at' : 'billing_date';
    return source.filter(record => {
        if (!record[dateField]) return false;
        const recordDate = new Date(String(record[dateField]).replace(' ', 'T'));
        return !Number.isNaN(recordDate.getTime()) && recordDate >= start && recordDate <= end;
    });
}

// Renamed all functions with 'rf' prefix to bypass hidden scripts
window.rfPreviewReport = function(type) {
    lastPreviewType = type;
    const content = renderNewReport(type);
    document.getElementById('printPreviewContent').innerHTML = content;
    document.getElementById('printPreviewModal').classList.add('active');
};

window.rfPrintReport = function(type) {
    const html = renderNewReport(type);
    const win = window.open('', '_blank');
    const doc = `
<!DOCTYPE html>
<html>
<head>
    <title>${reportTitles[type]}</title>
    <style>
        body { font-family: "Segoe UI", Arial, sans-serif; color:#111; margin: 24px; }
        .print-report { max-width: 980px; margin: 0 auto; }
        .print-header { display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #e5e7eb; padding-bottom:12px; margin-bottom:16px; }
        .print-brand { display:flex; gap:12px; align-items:center; }
        .print-logo { width:44px; height:44px; border-radius:10px; background:#2563eb; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; }
        .print-title { font-size:18px; font-weight:700; }
        .print-subtitle { font-size:13px; color:#6b7280; }
        .print-meta { font-size:12px; color:#6b7280; text-align:right; }
        .print-table { width:100%; border-collapse:collapse; }
        .print-table th, .print-table td { border:1px solid #e5e7eb; padding:8px 10px; font-size:12px; text-align:left; }
        .print-table th { background:#f9fafb; text-transform:uppercase; letter-spacing:.04em; font-size:11px; }
        .print-footer { margin-top:18px; font-size:11px; color:#9ca3af; display:flex; justify-content:space-between; }
        @media print { body { margin: 0.5in; } }
    </style>
</head>
<body>${html}</body>
</html>`;
    win.document.open();
    win.document.write(doc);
    win.document.close();
    setTimeout(() => { win.focus(); win.print(); }, 250);
};

window.rfPrintCurrentReport = function() { window.rfPrintReport(lastPreviewType); };
window.rfClosePreviewModal = function() { document.getElementById('printPreviewModal').classList.remove('active'); };
window.rfGenerateCustomReport = function() { window.rfPreviewReport(document.getElementById('customReportType').value); };
window.rfPrintCustomReport = function() { window.rfPrintReport(document.getElementById('customReportType').value); };
window.rfExportToPDF = function() { window.rfPrintReport(document.getElementById('customReportType').value); };
window.applyDateFilter = function() {
    const filter = document.getElementById('dateRange').value;
    const url = new URL(window.location.href);
    if (/^\d{4}-\d{2}$/.test(filter)) {
        url.searchParams.set('date_filter', 'month');
        url.searchParams.set('month', filter);
    } else {
        url.searchParams.set('date_filter', filter);
        url.searchParams.delete('month');
    }
    window.location.href = url.href;
};
</script>

<?php require_once __DIR__ . '/../../includes/admin_layout_end.php'; ?>

<style>
    .report-form { display: flex; align-items: center; justify-content: stretch; gap: 10px; padding: 16px 0; }
    .report-form .form-control { flex: 1; min-width: 0; }
    .report-form button { flex: 1; min-width: 0; max-width: 120px; }
    .section-card { height: auto; }
    .reports-grid { margin-bottom: 40px; padding-bottom: 40px; }
    .content-main { padding-bottom: 40px; }
</style>
<link rel="stylesheet" href="../../assets/css/admin_reports.css">