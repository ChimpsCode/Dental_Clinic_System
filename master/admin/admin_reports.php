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
    $whereClause = $hasArchiveColumn ? "WHERE (a.is_archived = 0 OR a.is_archived IS NULL)" : "";

    $appointmentsReport = $pdo->query("
        SELECT a.id, p.first_name, p.middle_name, p.last_name,
               a.appointment_date, a.appointment_time, a.status
        FROM appointments a
        LEFT JOIN patients p ON p.id = a.patient_id
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

    $revenueReport = $pdo->query("
        SELECT COALESCE(t.procedure_name, a.treatment, 'General Service') AS service,
               COALESCE(SUM(b.total_amount), 0) AS total
        FROM billing b
        LEFT JOIN treatments t ON t.id = b.treatment_id
        LEFT JOIN appointments a ON a.id = b.appointment_id
        GROUP BY service
        ORDER BY total DESC
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
                        <select id="dateRange" class="form-control">
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month" selected>This Month</option>
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
    const data = reportData[type] || [];
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
        headers = ['Service', 'Total Revenue'];
        rows = data.map(r => [r.service ?? 'General Service', `₱${Number(r.total || 0).toLocaleString()}`]);
    } else if (type === 'services') {
        headers = ['Service', 'Frequency'];
        rows = data.map(s => [s.service ?? 'Service', s.total ?? 0]);
    } else if (type === 'daily') {
        headers = ['Date', 'New Patients', 'Appointments', 'Revenue'];
        rows = data.map(d => [d.day ?? '', d.new_patients ?? 0, d.appointments ?? 0, `₱${Number(d.revenue || 0).toLocaleString()}`]);
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