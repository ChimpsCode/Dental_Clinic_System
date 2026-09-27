<?php
/**
 * Export Audit Logs - Export audit trail data to CSV or Excel format
 */

require_once __DIR__ . '/../../includes/require_admin.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/audit_helper.php';

function exportJsonError(string $message, int $status = 400): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function auditXmlText(string $value): string
{
    $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function auditColumnName(int $index): string
{
    $name = '';
    $index++;
    while ($index > 0) {
        $index--;
        $name = chr(65 + ($index % 26)) . $name;
        $index = intdiv($index, 26);
    }
    return $name;
}

function auditZipStored(array $files): string
{
    $body = '';
    $central = '';
    $offset = 0;
    foreach ($files as $name => $data) {
        $name = str_replace('\\', '/', (string)$name);
        $size = strlen($data);
        $crc = crc32($data) & 0xffffffff;
        $nameLength = strlen($name);
        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLength, 0) . $name . $data;
        $body .= $local;
        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset) . $name;
        $offset += strlen($local);
    }
    return $body . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($central), $offset, 0);
}

function auditBuildXlsx(array $rows): string
{
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    foreach ($rows as $rowIndex => $row) {
        $excelRow = $rowIndex + 1;
        $sheet .= '<row r="' . $excelRow . '">';
        foreach (array_values($row) as $columnIndex => $value) {
            $ref = auditColumnName($columnIndex) . $excelRow;
            $sheet .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . auditXmlText((string)$value) . '</t></is></c>';
        }
        $sheet .= '</row>';
    }
    $sheet .= '</sheetData></worksheet>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>';
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Audit logs" sheetId="1" r:id="rId1"/></sheets></workbook>';
    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>';

    $binary = auditZipStored([
        '[Content_Types].xml' => $contentTypes,
        '_rels/.rels' => $rels,
        'xl/workbook.xml' => $workbook,
        'xl/_rels/workbook.xml.rels' => $workbookRels,
        'xl/worksheets/sheet1.xml' => $sheet,
    ]);
    $tmp = tempnam(sys_get_temp_dir(), 'auditxlsx');
    if ($tmp === false || file_put_contents($tmp, $binary) === false) {
        throw new RuntimeException('Could not create the Excel file.');
    }
    return $tmp;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    exportJsonError('Export requests must be sent from the audit trail page.', 405);
}

if (!($pdo instanceof PDO)) {
    exportJsonError('The database is not available.', 500);
}

$password = (string)($_POST['password'] ?? '');
if ($password === '') {
    exportJsonError('Enter your current password.');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$passStmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
$passStmt->execute([$userId]);
$hash = (string)$passStmt->fetchColumn();
if ($hash === '' || !password_verify($password, $hash)) {
    exportJsonError('That password does not match your account.');
}

$format = (string)($_POST['format'] ?? 'csv');
if (!in_array($format, ['csv', 'excel', 'print'], true)) {
    $format = 'csv';
}

$timeMode = (($_POST['time_mode'] ?? 'months') === 'datetime') ? 'datetime' : 'months';
if ($timeMode === 'datetime') {
    $rawWhen = str_replace('T', ' ', trim((string)($_POST['from_datetime'] ?? '')));
    $when = DateTime::createFromFormat('Y-m-d H:i', substr($rawWhen, 0, 16));
    $parsed = DateTime::getLastErrors();
    if (!$when || ($parsed && (($parsed['warning_count'] ?? 0) > 0 || ($parsed['error_count'] ?? 0) > 0))) {
        exportJsonError('Choose a date and time.');
    }
    if ($when > new DateTime('now')) {
        exportJsonError('Choose a date and time that is not in the future.');
    }
    $fromDatetime = $when->format('Y-m-d H:i:s');
    $rangeLabel = 'From ' . $when->format('F j, Y g:i A') . ' until now';
} else {
    $months = (int)($_POST['months'] ?? 3);
    if (!in_array($months, [1, 3, 6, 12], true)) {
        $months = 3;
    }
    $when = new DateTime('now');
    $when->modify('-' . $months . ' months');
    $fromDatetime = $when->format('Y-m-d H:i:s');
    $rangeLabel = 'Last ' . $months . ' ' . ($months === 1 ? 'month' : 'months');
}

$filters = ['from_datetime' => $fromDatetime];
$searchQuery = trim((string)($_POST['search'] ?? ''));
$actionFilter = trim((string)($_POST['action'] ?? ''));
$roleFilter = trim((string)($_POST['role'] ?? ''));
$statusFilter = trim((string)($_POST['status'] ?? ''));
if ($searchQuery !== '') {
    $filters['search'] = $searchQuery;
}
if ($actionFilter !== '') {
    $filters['action_type'] = $actionFilter;
}
if ($roleFilter !== '') {
    $filters['user_role'] = $roleFilter;
}
if ($statusFilter !== '') {
    $filters['status'] = $statusFilter;
}

$auditData = getAuditLogs($pdo, $filters, 10000, 0);
$logs = $auditData['logs'];

// Generate filename
$filename = 'audit_logs_' . date('Y-m-d_H-i-s');

// Set headers for download
if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output CSV
    $output = fopen('php://output', 'w');
    
    // Header row
    fputcsv($output, [
        'ID',
        'Timestamp',
        'User',
        'Role',
        'Action Type',
        'Module',
        'Description',
        'Old Value',
        'New Value',
        'IP Address',
        'Status'
    ]);
    
    // Data rows
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['id'],
            $log['created_at'],
            $log['username'] ?? 'Unknown',
            $log['user_role'] ?? 'Unknown',
            $log['action_type'] ?? 'Unknown',
            $log['module'] ?? 'Unknown',
            $log['description'] ?? '',
            $log['old_value'] ?? '',
            $log['new_value'] ?? '',
            $log['ip_address'] ?? 'N/A',
            $log['status'] ?? 'success'
        ]);
    }
    
    fclose($output);
    exit;
    
} elseif ($format === 'excel') {
    $rows = [[
        'ID',
        'Timestamp',
        'User',
        'Role',
        'Action Type',
        'Module',
        'Description',
        'Old Value',
        'New Value',
        'IP Address',
        'Status'
    ]];
    foreach ($logs as $log) {
        $rows[] = [
            $log['id'],
            $log['created_at'],
            $log['username'] ?? 'Unknown',
            $log['user_role'] ?? 'Unknown',
            $log['action_type'] ?? 'Unknown',
            $log['module'] ?? 'Unknown',
            $log['description'] ?? '',
            $log['old_value'] ?? '',
            $log['new_value'] ?? '',
            $log['ip_address'] ?? 'N/A',
            $log['status'] ?? 'success'
        ];
    }

    try {
        $xlsxPath = auditBuildXlsx($rows);
    } catch (Throwable $e) {
        error_log('Audit Excel export error: ' . $e->getMessage());
        exportJsonError('The Excel file could not be created.', 500);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Content-Length: ' . filesize($xlsxPath));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($xlsxPath);
    @unlink($xlsxPath);
    exit;

} elseif ($format === 'print') {
    require_once __DIR__ . '/../../includes/print_log_helper.php';
    logPrint($pdo, 'Audit Log', 'Audit trail printout (' . ($rangeLabel ?? 'All') . ', ' . count($logs) . ' entries)', 'audit_logs', null, 'admin_audit_trail.php');
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Audit logs</title>
        <style>
            body { font-family: Arial, sans-serif; color: #111; margin: 24px; }
            h1 { font-size: 20px; margin: 0 0 4px; }
            p { margin: 0 0 16px; color: #444; }
            table { width: 100%; border-collapse: collapse; font-size: 12px; }
            th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
            th { background: #f3f4f6; }
        </style>
    </head>
    <body>
        <h1>Audit logs</h1>
        <p><?php echo htmlspecialchars($rangeLabel); ?> · <?php echo count($logs); ?> rows</p>
        <table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>Description</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)($log['created_at'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string)($log['username'] ?? 'Unknown')); ?></td>
                    <td><?php echo htmlspecialchars((string)($log['user_role'] ?? 'Unknown')); ?></td>
                    <td><?php echo htmlspecialchars((string)($log['action_type'] ?? 'Unknown')); ?></td>
                    <td><?php echo htmlspecialchars((string)($log['module'] ?? 'Unknown')); ?></td>
                    <td><?php echo htmlspecialchars((string)($log['description'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string)($log['status'] ?? 'success')); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <script>window.addEventListener('load', function () { window.print(); });</script>
    </body>
    </html>
    <?php
    exit;

} else {
    // Default to CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Timestamp', 'User', 'Role', 'Action Type', 'Module', 'Description', 'IP Address', 'Status']);
    
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['id'],
            $log['created_at'],
            $log['username'] ?? 'Unknown',
            $log['user_role'] ?? 'Unknown',
            $log['action_type'] ?? 'Unknown',
            $log['module'] ?? 'Unknown',
            $log['description'] ?? '',
            $log['ip_address'] ?? 'N/A',
            $log['status'] ?? 'success'
        ]);
    }
    
    fclose($output);
    exit;
}
?>
