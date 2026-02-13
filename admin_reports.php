<?php
/**
 * Reports - Admin page for printable reports
 */

$pageTitle = 'Reports';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/admin_layout_start.php';

$patientsReport = [];
$appointmentsReport = [];
$billingReport = [];
$revenueReport = [];
$servicesReport = [];
$dailySummaryReport = [];

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

function buildReportHtml($title, $tableHtml) {
    $generated = date('M d, Y h:i A');
    return '
        <div class="print-report">
            <div class="print-header">
                <div class="print-brand">
                    <div class="print-logo">RF</div>
                    <div>
                        <div class="print-title">RF Dental Clinic</div>
                        <div class="print-subtitle">' . htmlspecialchars($title) . '</div>
                    </div>
                </div>
                <div class="print-meta">
                    <div>Generated: ' . htmlspecialchars($generated) . '</div>
                </div>
            </div>
            <div class="print-body">' . $tableHtml . '</div>
            <div class="print-footer">
                <div>Confidential - For internal use only</div>
                <div>RF Dental Clinic</div>
            </div>
        </div>
    ';
}

try {
    $patientsReport = $pdo->query("
        SELECT id, full_name, phone, email, created_at
        FROM patients
        ORDER BY created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $checkCol = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'is_archived'");
    $hasArchiveColumn = $checkCol->rowCount() > 0;
    $whereClause = $hasArchiveColumn ? "WHERE (a.is_archived = 0 OR a.is_archived IS NULL)" : "";

    $appointmentsReport = $pdo->query("
        SELECT a.id,
               TRIM(CONCAT(
                    COALESCE(p.first_name, a.first_name, ''), ' ',
                    COALESCE(p.middle_name, a.middle_name, ''), ' ',
                    COALESCE(p.last_name, a.last_name, '')
               )) AS patient,
               a.appointment_date, a.appointment_time, a.treatment, a.status
        FROM appointments a
        LEFT JOIN patients p ON p.id = a.patient_id
        $whereClause
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $billingReport = $pdo->query("
        SELECT b.id, CONCAT(p.first_name, ' ', p.last_name) AS patient,
               b.total_amount, b.paid_amount, b.balance, b.payment_status,
               b.billing_date, b.due_date
        FROM billing b
        LEFT JOIN patients p ON p.id = b.patient_id
        ORDER BY b.billing_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $revenueReport = $pdo->query("
        SELECT COALESCE(t.procedure_name, a.treatment, 'General Service') AS service,
               COALESCE(SUM(b.total_amount), 0) AS total
        FROM billing b
        LEFT JOIN treatments t ON t.id = b.treatment_id
        LEFT JOIN appointments a ON a.id = b.appointment_id
        GROUP BY service
        ORDER BY total DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $servicesReport = $pdo->query("
        SELECT procedure_name AS service, COUNT(*) AS total
        FROM treatments
        GROUP BY procedure_name
        ORDER BY total DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($servicesReport)) {
        $servicesReport = $pdo->query("
            SELECT treatment AS service, COUNT(*) AS total
            FROM appointments
            GROUP BY treatment
            ORDER BY total DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    $dailySummaryReport = $pdo->query("
        SELECT
            DATE(d.day) AS day,
            COALESCE(p.new_patients, 0) AS new_patients,
            COALESCE(a.total_appointments, 0) AS appointments,
            COALESCE(r.total_revenue, 0) AS revenue
        FROM (
            SELECT CURDATE() - INTERVAL (a.a) DAY AS day
            FROM (SELECT 0 a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) a
        ) d
        LEFT JOIN (
            SELECT DATE(created_at) AS day, COUNT(*) AS new_patients
            FROM patients
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(created_at)
        ) p ON p.day = DATE(d.day)
        LEFT JOIN (
            SELECT appointment_date AS day, COUNT(*) AS total_appointments
            FROM appointments
            WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY appointment_date
        ) a ON a.day = DATE(d.day)
        LEFT JOIN (
            SELECT payment_date AS day, COALESCE(SUM(amount), 0) AS total_revenue
            FROM payments
            WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY payment_date
        ) r ON r.day = DATE(d.day)
        ORDER BY day ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $patientsReport = [];
    $appointmentsReport = [];
    $billingReport = [];
    $revenueReport = [];
    $servicesReport = [];
    $dailySummaryReport = [];
}
?>
            <div class="content-main">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Reports</h2>
                    <p class="page-subtitle">Generate and print printable reports</p>
                </div>

                <!-- Report Types Grid -->
                <div class="reports-grid">
                    <div class="report-card">
                        <div class="report-icon">&#128101;</div>
                        <h3>Patient Report</h3>
                        <p>Complete list of all registered patients with contact information and visit history.</p>
                        <div class="report-actions">
                            <button class="btn-secondary" onclick="previewReport('patients')">Preview</button>
                            <button class="btn-primary" onclick="printReport('patients')">&#128424; Print</button>
                        </div>
                    </div>
                    <div class="report-card">
                        <div class="report-icon">&#128197;</div>
                        <h3>Appointments Report</h3>
                        <p>Detailed appointments log including completed, pending, and cancelled appointments.</p>
                        <div class="report-actions">
                            <button class="btn-secondary" onclick="previewReport('appointments')">Preview</button>
                            <button class="btn-primary" onclick="printReport('appointments')">&#128424; Print</button>
                        </div>
                    </div>
                    <div class="report-card">
                        <div class="report-icon">&#128176;</div>
                        <h3>Billing Report</h3>
                        <p>Financial summary including all transactions, payments received, and pending amounts.</p>
                        <div class="report-actions">
                            <button class="btn-secondary" onclick="previewReport('billing')">Preview</button>
                            <button class="btn-primary" onclick="printReport('billing')">&#128424; Print</button>
                        </div>
                    </div>
                    <div class="report-card">
                        <div class="report-icon">&#128200;</div>
                        <h3>Revenue Report</h3>
                        <p>Revenue breakdown by service type, time period, and payment status.</p>
                        <div class="report-actions">
                            <button class="btn-secondary" onclick="previewReport('revenue')">Preview</button>
                            <button class="btn-primary" onclick="printReport('revenue')">&#128424; Print</button>
                        </div>
                    </div>
                    <div class="report-card">
                        <div class="report-icon">&#129405;</div>
                        <h3>Services Report</h3>
                        <p>Summary of services rendered, frequency, and revenue by service type.</p>
                        <div class="report-actions">
                            <button class="btn-secondary" onclick="previewReport('services')">Preview</button>
                            <button class="btn-primary" onclick="printReport('services')">&#128424; Print</button>
                        </div>
                    </div>
                    <div class="report-card">
                        <div class="report-icon">&#128202;</div>
                        <h3>Daily Summary</h3>
                        <p>Day-by-day summary of patients, appointments, and revenue.</p>
                        <div class="report-actions">
                            <button class="btn-secondary" onclick="previewReport('daily')">Preview</button>
                            <button class="btn-primary" onclick="printReport('daily')">&#128424; Print</button>
                        </div>
                    </div>
                </div>

                <!-- Report Generator -->
                <div class="section-card">
                    <h2 class="section-title">&#128203; Custom Report Generator</h2>
                    <div class="report-form">
                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label>Report Type</label>
                                <select id="customReportType" class="form-control">
                                    <option value="patients">Patient Report</option>
                                    <option value="appointments">Appointments Report</option>
                                    <option value="billing">Billing Report</option>
                                    <option value="revenue">Revenue Report</option>
                                    <option value="services">Services Report</option>
                                    <option value="daily">Daily Summary</option>
                                </select>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>Date Range</label>
                                <select id="dateRange" class="form-control">
                                    <option value="today">Today</option>
                                    <option value="week">This Week</option>
                                    <option value="month" selected>This Month</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button class="btn-secondary" onclick="generateCustomReport()">Generate Report</button>
                            <button class="btn-primary" onclick="printCustomReport()">&#128424; Print Report</button>
                            <button class="btn-secondary" onclick="exportToPDF()">&#128196; Export PDF</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Print Preview Modal -->
            <div id="printPreviewModal" class="modal-overlay">
                <div class="modal" style="max-width: 900px;">
                    <div class="modal-header">
                        <h2>Report Preview</h2>
                        <div class="modal-actions">
                            <button class="btn-secondary" onclick="closePreviewModal()">Close</button>
                            <button class="btn-primary" onclick="printCurrentReport()">&#128424; Print</button>
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
    'daily' => $dailySummaryReport
];
$reportJson = json_encode($reportPayload, $jsonFlags);

$reportHtmlPayload = [
    'patients' => buildReportHtml('Patient Report', buildReportTable(
        ['ID', 'Full Name', 'Phone', 'Email', 'Registered'],
        array_map(fn($p) => [
            $p['id'] ?? '',
            $p['full_name'] ?? '',
            $p['phone'] ?? '',
            $p['email'] ?? '',
            isset($p['created_at']) ? date('M d, Y', strtotime($p['created_at'])) : ''
        ], $patientsReport)
    )),
    'appointments' => buildReportHtml('Appointments Report', buildReportTable(
        ['ID', 'Patient', 'Date', 'Time', 'Treatment', 'Status'],
        array_map(fn($a) => [
            $a['id'] ?? '',
            $a['patient'] ?? '',
            $a['appointment_date'] ?? '',
            $a['appointment_time'] ?? '',
            $a['treatment'] ?? '',
            $a['status'] ?? ''
        ], $appointmentsReport)
    )),
    'billing' => buildReportHtml('Billing Report', buildReportTable(
        ['Invoice', 'Patient', 'Total', 'Paid', 'Balance', 'Status', 'Date', 'Due'],
        array_map(fn($b) => [
            'INV-' . str_pad((string)($b['id'] ?? 0), 4, '0', STR_PAD_LEFT),
            $b['patient'] ?? '',
            '₱' . number_format((float)($b['total_amount'] ?? 0), 2),
            '₱' . number_format((float)($b['paid_amount'] ?? 0), 2),
            '₱' . number_format((float)($b['balance'] ?? 0), 2),
            $b['payment_status'] ?? '',
            $b['billing_date'] ?? '',
            $b['due_date'] ?? ''
        ], $billingReport)
    )),
    'revenue' => buildReportHtml('Revenue Report', buildReportTable(
        ['Service', 'Total Revenue'],
        array_map(fn($r) => [
            $r['service'] ?? 'General Service',
            '₱' . number_format((float)($r['total'] ?? 0), 2)
        ], $revenueReport)
    )),
    'services' => buildReportHtml('Services Report', buildReportTable(
        ['Service', 'Frequency'],
        array_map(fn($s) => [
            $s['service'] ?? 'Service',
            $s['total'] ?? 0
        ], $servicesReport)
    )),
    'daily' => buildReportHtml('Daily Summary', buildReportTable(
        ['Date', 'New Patients', 'Appointments', 'Revenue'],
        array_map(fn($d) => [
            $d['day'] ?? '',
            $d['new_patients'] ?? 0,
            $d['appointments'] ?? 0,
            '₱' . number_format((float)($d['revenue'] ?? 0), 2)
        ], $dailySummaryReport)
    ))
];
$reportHtmlJson = json_encode($reportHtmlPayload, $jsonFlags);
?>
<script id="reportData" type="application/json"><?php echo $reportJson; ?></script>
<script id="reportHtml" type="application/json"><?php echo $reportHtmlJson; ?></script>
<?php
$pageScript = <<<'SCRIPT'
<script>
const reportData = JSON.parse(document.getElementById('reportData')?.textContent || '{}');
const reportHtml = JSON.parse(document.getElementById('reportHtml')?.textContent || '{}');
const reportTitles = {
    patients: 'Patient Report',
    appointments: 'Appointments Report',
    billing: 'Billing Report',
    revenue: 'Revenue Report',
    services: 'Services Report',
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

function renderReport(type) {
    if (reportHtml[type]) {
        return reportHtml[type];
    }

    const data = reportData[type] || [];
    let headers = [];
    let rows = [];

    if (type === 'patients') {
        headers = ['ID', 'Full Name', 'Phone', 'Email', 'Registered'];
        rows = data.map(p => [
            p.id ?? '',
            p.full_name ?? '',
            p.phone ?? '',
            p.email ?? '',
            p.created_at ? new Date(p.created_at).toLocaleDateString() : ''
        ]);
    } else if (type === 'appointments') {
        headers = ['ID', 'Patient', 'Date', 'Time', 'Treatment', 'Status'];
        rows = data.map(a => [
            a.id ?? '',
            a.patient ?? '',
            a.appointment_date ?? '',
            a.appointment_time ?? '',
            a.treatment ?? '',
            a.status ?? ''
        ]);
    } else if (type === 'billing') {
        headers = ['Invoice', 'Patient', 'Total', 'Paid', 'Balance', 'Status', 'Date', 'Due'];
        rows = data.map(b => [
            `INV-${String(b.id).padStart(4, '0')}`,
            b.patient ?? '',
            `₱${Number(b.total_amount || 0).toLocaleString()}`,
            `₱${Number(b.paid_amount || 0).toLocaleString()}`,
            `₱${Number(b.balance || 0).toLocaleString()}`,
            b.payment_status ?? '',
            b.billing_date ?? '',
            b.due_date ?? ''
        ]);
    } else if (type === 'revenue') {
        headers = ['Service', 'Total Revenue'];
        rows = data.map(r => [
            r.service ?? 'General Service',
            `₱${Number(r.total || 0).toLocaleString()}`
        ]);
    } else if (type === 'services') {
        headers = ['Service', 'Frequency'];
        rows = data.map(s => [
            s.service ?? 'Service',
            s.total ?? 0
        ]);
    } else if (type === 'daily') {
        headers = ['Date', 'New Patients', 'Appointments', 'Revenue'];
        rows = data.map(d => [
            d.day ?? '',
            d.new_patients ?? 0,
            d.appointments ?? 0,
            `₱${Number(d.revenue || 0).toLocaleString()}`
        ]);
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
            <div class="print-body">
                ${table}
            </div>
            <div class="print-footer">
                <div>Confidential - For internal use only</div>
                <div>RF Dental Clinic</div>
            </div>
        </div>
    `;
}

window.previewReport = function(type) {
    lastPreviewType = type;
    const content = renderReport(type);
    document.getElementById('printPreviewContent').innerHTML = content;
    document.getElementById('printPreviewModal').classList.add('active');
};

window.printReport = function(type) {
    const html = renderReport(type);
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
    win.focus();
    win.print();
};

window.printCurrentReport = function() {
    window.printReport(lastPreviewType);
};

window.closePreviewModal = function() {
    document.getElementById('printPreviewModal').classList.remove('active');
};

window.generateCustomReport = function() {
    const type = document.getElementById('customReportType').value;
    window.previewReport(type);
};

window.printCustomReport = function() {
    const type = document.getElementById('customReportType').value;
    window.printReport(type);
};

window.exportToPDF = function() {
    const type = document.getElementById('customReportType').value;
    window.printReport(type);
};
</script>
SCRIPT;
?>

<?php
require_once __DIR__ . '/includes/admin_layout_end.php';
?>
