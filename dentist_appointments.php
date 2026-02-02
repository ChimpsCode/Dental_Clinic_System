<?php
$pageTitle = 'Appointments';
require_once 'includes/dentist_layout_start.php';
require_once 'config/database.php';

// Debug: Check database connection
error_log("Checking appointments table...");

// Fetch appointment statistics
try {
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN DATE(appointment_date) = CURDATE() THEN 1 ELSE 0 END) as today,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointments");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Stats: " . json_encode($stats));
    
    $totalAppointments = $stats['total'] ?? 0;
    $todayAppointments = $stats['today'] ?? 0;
    $completedAppointments = $stats['completed'] ?? 0;
    $cancelledAppointments = $stats['cancelled'] ?? 0;
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
    $totalAppointments = $todayAppointments = $completedAppointments = $cancelledAppointments = 0;
}

// Fetch all appointments
try {
    $stmt = $pdo->query("SELECT a.*, 
        CONCAT(COALESCE(a.first_name, ''), ' ', COALESCE(a.middle_name, ''), ' ', COALESCE(a.last_name, '')) as full_name,
        p.phone, p.email FROM appointments a 
        LEFT JOIN patients p ON a.patient_id = p.id 
        ORDER BY a.appointment_date DESC");
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Appointments found: " . count($appointments));
} catch (Exception $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
    $appointments = [];
}
?>

<!-- Appointment Stats -->
<div class="summary-cards">
    <div class="summary-card">
        <div class="summary-icon blue" style="background: #e0f2fe; color: #0284c7;">📋</div>
        <div class="summary-info">
            <h3><?php echo $totalAppointments; ?></h3>
            <p>Total Appointments</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon yellow">⏰</div>
        <div class="summary-info">
            <h3><?php echo $todayAppointments; ?></h3>
            <p>Today</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon green">✓</div>
        <div class="summary-info">
            <h3><?php echo $completedAppointments; ?></h3>
            <p>Completed</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon red" style="background: #fee2e2; color: #dc2626;">⚠️</div>
        <div class="summary-info">
            <h3><?php echo $cancelledAppointments; ?></h3>
            <p>Cancelled</p>
        </div>
    </div>
</div>

<!-- Search & Filters -->
<div class="search-filters">
    <div class="filter-tabs">
        <span class="active">All</span>
        <span>Today</span>
        <span>This Week</span>
        <span>This Month</span>
    </div>
    <input type="text" class="search-input" placeholder="Search appointments...">
</div>

<!-- Appointments Table -->
<div class="section-card">
    <div class="section-title">
        <span>Appointments List</span>
        <button class="btn-primary">+ New Appointment</button>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>Patient Name</th>
                <th>Date & Time</th>
                <th>Treatment</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($appointments)): ?>
                <?php foreach ($appointments as $apt): ?>
                    <?php
                        $patientName = trim(($apt['full_name'] ?? '') ?: trim(($apt['first_name'] ?? '') . ' ' . ($apt['middle_name'] ?? '') . ' ' . ($apt['last_name'] ?? '')));
                        $patientName = preg_replace('/\s+/', ' ', $patientName) ?: 'Unknown';
                        $appointmentDate = new DateTime($apt['appointment_date']);
                        $appointmentTime = $appointmentDate->format('h:i A');
                        $appointmentDateStr = $appointmentDate->format('M d, Y');
                        $status = $apt['status'] ?? 'scheduled';
                        
                        // Determine status badge color
                        $statusColor = match($status) {
                            'completed' => 'background: #dcfce7; color: #15803d;',
                            'cancelled' => 'background: #fee2e2; color: #dc2626;',
                            default => 'background: #e0f2fe; color: #0369a1;'
                        };
                        $statusText = ucfirst($status);
                    ?>
                    <tr>
                        <td>
                            <div class="patient-name"><?php echo htmlspecialchars($patientName); ?></div>
                            <div style="font-size: 0.85rem; color: #6b7280;">Phone: <?php echo htmlspecialchars($apt['phone'] ?? 'N/A'); ?></div>
                        </td>
                        <td>
                            <div><?php echo $appointmentDateStr; ?></div>
                            <div style="font-size: 0.85rem; color: #6b7280;"><?php echo $appointmentTime; ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($apt['treatment'] ?? 'General Checkup'); ?></td>
                        <td><span class="status-badge" style="<?php echo $statusColor; ?>"><?php echo $statusText; ?></span></td>
                        <td>
                            <div class="patient-actions">
                                <button class="action-btn icon view-btn">👁️</button>
                                <button class="action-btn icon">📝</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 2rem; color: #6b7280;">No appointments found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/dentist_layout_end.php'; ?>