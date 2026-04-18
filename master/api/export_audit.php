<?php
/**
 * Export Audit Logs - Export audit trail data to CSV or Excel format
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/audit_helper.php';

// Get parameters
$format = $_GET['format'] ?? 'csv';
$searchQuery = $_GET['search'] ?? '';
$actionFilter = $_GET['action'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date'] ?? '';
$dateTo = $_GET['date'] ?? '';

// Build filters
$filters = [];
if (!empty($searchQuery)) {
    $filters['search'] = $searchQuery;
}
if (!empty($actionFilter)) {
    $filters['action_type'] = $actionFilter;
}
if (!empty($roleFilter)) {
    $filters['user_role'] = $roleFilter;
}
if (!empty($statusFilter)) {
    $filters['status'] = $statusFilter;
}
if (!empty($dateFrom)) {
    $filters['date_from'] = $dateFrom;
    $filters['date_to'] = $dateTo ?: $dateFrom;
}

// Get all audit logs (no pagination for export)
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
    // For Excel, we'll create a simple XML-based format that Excel can open
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output Excel-compatible HTML
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta charset="UTF-8">
        <title>Audit Logs Export</title>
        <style>
            .header { background-color: #2563eb; color: white; font-weight: bold; }
            .row:nth-child(even) { background-color: #f9fafb; }
            td { mso-number-format: \@; }
        </style>
    </head>
    <body>
        <table border="1">
            <tr class="header">
                <td>ID</td>
                <td>Timestamp</td>
                <td>User</td>
                <td>Role</td>
                <td>Action Type</td>
                <td>Module</td>
                <td>Description</td>
                <td>Old Value</td>
                <td>New Value</td>
                <td>IP Address</td>
                <td>Status</td>
            </tr>
            <?php foreach ($logs as $log): ?>
            <tr class="row">
                <td><?php echo $log['id']; ?></td>
                <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                <td><?php echo htmlspecialchars($log['username'] ?? 'Unknown'); ?></td>
                <td><?php echo htmlspecialchars($log['user_role'] ?? 'Unknown'); ?></td>
                <td><?php echo htmlspecialchars($log['action_type'] ?? 'Unknown'); ?></td>
                <td><?php echo htmlspecialchars($log['module'] ?? 'Unknown'); ?></td>
                <td><?php echo htmlspecialchars($log['description'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($log['old_value'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($log['new_value'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($log['status'] ?? 'success'); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
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
