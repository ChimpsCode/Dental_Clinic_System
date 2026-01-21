<?php
$pageTitle = 'Dentist Dashboard';
require_once 'includes/dentist_layout_start.php';

try {
    require_once 'config/database.php';
    
    // Get queue statistics
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM queue GROUP BY status");
    $queueStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $waitingCount = $queueStats['waiting'] ?? 0;
    $inProcedureCount = $queueStats['in_procedure'] ?? 0;
    $completedToday = $queueStats['completed'] ?? 0;
    $onHoldCount = $queueStats['on_hold'] ?? 0;
    
    // Get today's completed count
    $stmt = $pdo->query("SELECT COUNT(*) FROM queue WHERE status = 'completed' AND DATE(updated_at) = CURDATE()");
    $completedToday = $stmt->fetchColumn();
    
    // Get patients currently in queue
    $stmt = $pdo->query("
        SELECT q.*, p.full_name, p.phone, p.gender, p.age
        FROM queue q
        JOIN patients p ON q.patient_id = p.id
        WHERE q.status IN ('waiting', 'in_procedure')
        ORDER BY q.priority ASC, q.queue_time ASC
        LIMIT 10
    ");
    $queuePatients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent patients (last 30 days)
    $stmt = $pdo->query("SELECT COUNT(*) FROM patients WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $newPatientsMonth = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $waitingCount = 0;
    $inProcedureCount = 0;
    $completedToday = 0;
    $onHoldCount = 0;
    $queuePatients = [];
    $newPatientsMonth = 0;
}
?>

<!-- Summary Cards -->
<div class="summary-cards">
    <div class="summary-card">
        <div class="summary-icon yellow">⏰</div>
        <div class="summary-info">
            <h3><?php echo $waitingCount; ?></h3>
            <p>Waiting</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon blue" style="background: #dbeafe; color: #1d4ed8;">⚙️</div>
        <div class="summary-info">
            <h3><?php echo $inProcedureCount; ?></h3>
            <p>In Procedure</p>
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
        <div class="summary-icon red" style="background: #fef3c7; color: #b45309;">⏸️</div>
        <div class="summary-info">
            <h3><?php echo $onHoldCount; ?></h3>
            <p>On Hold</p>
        </div>
    </div>
</div>

<div class="two-column">
    <div class="left-column">
        <!-- Treatment Queue -->
        <div class="section-card">
            <h2 class="section-title">⚕️ Treatment Queue</h2>
            <?php if (empty($queuePatients)): ?>
                <div style="text-align: center; padding: 40px 20px; color: #6b7280;">
                    <p>No patients in queue</p>
                </div>
            <?php else: ?>
                <div class="patient-list">
                    <?php foreach ($queuePatients as $patient): ?>
                        <div class="patient-item">
                            <div class="patient-info">
                                <div class="patient-name"><?php echo htmlspecialchars($patient['full_name']); ?></div>
                                <div class="patient-details">
                                    <span class="status-badge" style="background: <?php echo $patient['status'] === 'in_procedure' ? '#dbeafe' : '#fef3c7'; ?>; color: <?php echo $patient['status'] === 'in_procedure' ? '#1d4ed8' : '#92400e'; ?>;">
                                        <?php echo $patient['status'] === 'in_procedure' ? 'In Progress' : 'Waiting'; ?>
                                    </span>
                                    <span class="patient-time"><?php echo htmlspecialchars($patient['treatment_type'] ?? 'General'); ?></span>
                                </div>
                                <div class="patient-treatment"><?php echo htmlspecialchars($patient['teeth_numbers'] ? 'Teeth: ' . $patient['teeth_numbers'] : 'All teeth'); ?></div>
                            </div>
                            <div class="patient-actions">
                                <?php if ($patient['status'] === 'waiting'): ?>
                                    <button onclick="startProcedure(<?php echo $patient['id']; ?>)" class="action-btn" style="background: #22c55e; color: white;">Start</button>
                                <?php else: ?>
                                    <button onclick="completeProcedure(<?php echo $patient['id']; ?>)" class="action-btn" style="background: #2563eb; color: white;">Complete</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Notifications & Quick Actions -->
    <div class="right-column">
        <div class="notification-box">
            <h3>📊 Quick Stats</h3>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <div style="background: #f3f4f6; padding: 12px; border-radius: 8px;">
                    <div style="font-size: 1.5rem; font-weight: 700; color: #059669;"><?php echo $newPatientsMonth; ?></div>
                    <div style="font-size: 0.85rem; color: #6b7280;">New Patients (30 days)</div>
                </div>
                <div style="background: #f3f4f6; padding: 12px; border-radius: 8px;">
                    <div style="font-size: 1.5rem; font-weight: 700; color: #0891b2;"><?php echo count($queuePatients); ?></div>
                    <div style="font-size: 0.85rem; color: #6b7280;">Active Queue</div>
                </div>
            </div>
        </div>
        
        <div class="notification-box">
            <h3>🚀 Quick Actions</h3>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <a href="dentist_patients.php" class="btn-primary" style="width: 100%; text-align: center; text-decoration: none;">View All Patients</a>
                <a href="dentist_appointments.php" class="btn-primary" style="width: 100%; text-align: center; text-decoration: none;">View Appointments</a>
            </div>
        </div>
    </div>
</div>

<script>
// Queue actions
function startProcedure(queueId) {
    fetch('queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=start_procedure&queue_id=' + queueId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function completeProcedure(queueId) {
    fetch('queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=complete&queue_id=' + queueId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>

<?php require_once 'includes/dentist_layout_end.php'; ?>
