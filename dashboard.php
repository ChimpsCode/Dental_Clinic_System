<?php
ob_start();
session_start();

// Redirect admins to admin dashboard
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    ob_end_clean();
    header('Location: admin_dashboard.php');
    exit();
}

// Redirect staff to their dashboard
if (isset($_SESSION['role']) && $_SESSION['role'] === 'staff') {
    ob_end_clean();
    header('Location: staff-dashboard.php');
    exit();
}

// Redirect non-logged-in users to login
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'] ?? 'User';
$fullName = $_SESSION['full_name'] ?? 'Dr. Rex';

// Get real queue data from database
try {
    require_once 'config/database.php';
    
    $stmt = $pdo->query("
        SELECT q.*, p.full_name, p.phone, p.age
        FROM queue q 
        LEFT JOIN patients p ON q.patient_id = p.id 
        WHERE DATE(q.created_at) = CURDATE()
        AND q.status IN ('waiting', 'in_procedure', 'completed', 'on_hold', 'cancelled')
        ORDER BY 
            CASE q.status 
                WHEN 'in_procedure' THEN 1 
                WHEN 'waiting' THEN 2 
                WHEN 'on_hold' THEN 3 
                WHEN 'completed' THEN 4 
                WHEN 'cancelled' THEN 5 
            END,
            q.queue_time ASC
    ");
    $queueItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $waitingCount = count(array_filter($queueItems, fn($q) => $q['status'] === 'waiting'));
    $completedCount = count(array_filter($queueItems, fn($q) => $q['status'] === 'completed'));
    $cancelledCount = count(array_filter($queueItems, fn($q) => $q['status'] === 'cancelled'));
    $onHoldCount = count(array_filter($queueItems, fn($q) => $q['status'] === 'on_hold'));
    
    $inProcedureItem = array_filter($queueItems, fn($q) => $q['status'] === 'in_procedure');
    $inProcedureItem = !empty($inProcedureItem) ? reset($inProcedureItem) : null;
    
    $waitingItems = array_filter($queueItems, fn($q) => $q['status'] === 'waiting');
    $onHoldItems = array_filter($queueItems, fn($q) => $q['status'] === 'on_hold');
    $completedItems = array_filter($queueItems, fn($q) => $q['status'] === 'completed');
    
} catch (Exception $e) {
    $queueItems = [];
    $waitingCount = 0;
    $completedCount = 0;
    $cancelledCount = 0;
    $onHoldCount = 0;
    $inProcedureItem = null;
    $waitingItems = [];
    $onHoldItems = [];
    $completedItems = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inquiry & Queue Dashboard - RF Dental Clinic</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <!-- Left Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="assets/images/Logo.png" alt="RF Logo">
            <span>RF Dental Clinic</span>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item active">
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M3 12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1zm0 8a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1zm10 0a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-6a1 1 0 0 0-1 1zm1-17a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1z"/></svg></span>
                <span>Dashboard</span>
            </a>
            <a href="patient-records.php" class="nav-item">
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3s1.34 3 3 3m-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5S5 6.34 5 8s1.34 3 3 3m0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5m8 0c-.29 0-.62.02-.97.05c1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5"/></svg></span>
                <span>Patient Records</span>
            </a>
            <a href="NewAdmission.php" class="nav-item">
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M15.25 18.75q.3 0 .525-.225T16 18t-.225-.525t-.525-.225t-.525.225T14.5 18t.225.525t.525.225m2.75 0q.3 0 .525-.225T18.75 18t-.225-.525T18 17.25t-.525.225t-.225.525t.225.525t.525.225m2.75 0q.3 0 .525-.225T21.5 18t-.225-.525t-.525-.225t-.525.225T20 18t.225.525t.525.225M5 21q-.825 0-1.412-.587T3 19V5q0-.825.588-1.412T5 3h14q.825 0 1.413.588T21 5v6.7q-.475-.225-.975-.387T19 11.075V5H5v14h6.05q.075.55.238 1.05t.387.95zm0-3v1V5v6.075V11zm2-1h4.075q.075-.525.238-1.025t.362-.975H7zm0-4h6.1q.8-.75 1.788-1.25T17 11.075V11H7zm0-4h10V7H7zm11 14q-2.075 0-3.537-1.463T13 18t1.463-3.537T18 13t3.538 1.463T23 18t-1.463 3.538T18 23"/></svg></span>
                <span>New Admission</span>
            </a>
            <a href="appointments.php" class="nav-item">
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3m1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/></svg></span>
                <span>Appointments</span>
            </a>
            <a href="analytics.php" class="nav-item">
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M3 22V8h4v14zm7 0V2h4v20zm7 0v-8h4v8z"/></svg></span>
                <span>Analytics</span>
            </a>
            <a href="settings.php" class="nav-item">
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="m9.25 22l-.4-3.2q-.325-.125-.612-.3t-.563-.375L4.7 19.375l-2.75-4.75l2.575-1.95Q4.5 12.5 4.5 12.338v-.675q0-.163.025-.338L1.95 9.375l2.75-4.75l2.975 1.25q.275-.2.575-.375t.6-.3l.4-3.2h5.5l.4 3.2q.325.125.613.3t.562.375l2.975-1.25l2.75 4.75l-2.575 1.95q.025.175.025.338v.674q0 .163-.05.338l2.575 1.95l-2.75 4.75l-2.95-1.25q-.275.2-.575.375t-.6.3l-.4 3.2zm2.8-6.5q1.45 0 2.475-1.025T15.55 12t-1.025-2.475T12.05 8.5q-1.475 0-2.488 1.025T8.55 12t1.013 2.475T12.05 15.5"/></svg></span>
                <span>Settings</span>
            </a>
        </nav>
        
        <div class="sidebar-footer" style="border-top: 1px solid #6b7280; margin-top: 10px; padding-left: 20px;">
            <a href="logout.php" class="nav-item">
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M5 21q-.825 0-1.412-.587T3 19V5q0-.825.588-1.412T5 3h6q.425 0 .713.288T12 4t-.288.713T11 5H5v14h6q.425 0 .713.288T12 20t-.288.713T11 21zm12.175-8H10q-.425 0-.712-.288T9 12t.288-.712T10 11h7.175L15.3 9.125q-.275-.275-.275-.675t.275-.7t.7-.313t.725.288L20.3 11.3q.3.3.3.7t-.3.7l-3.575 3.575q-.3.3-.712.288t-.713-.313q-.275-.3-.262-.712t.287-.688z"/></svg></span>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <div class="menu-toggle" id="menuToggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <div class="header-title">
                    <h1>Inquiry & Queue Management System</h1>
                    <p>Manage patient inquiries and today's queue.</p>
                </div>
            </div>
            
            <div class="header-right">
                <div class="user-profile">
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 32 32'%3E%3Ccircle cx='16' cy='16' r='16' fill='%23e5e7eb'/%3E%3Ctext x='16' y='22' font-family='Arial' font-size='18' fill='%236b7280' text-anchor='middle'%3E👤%3C/text%3E%3C/svg%3E" alt="User">
                    <span class="user-name"><?php echo htmlspecialchars($fullName); ?></span>
                    
                </div>
            </div>
        </header>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Main Content -->
            <div class="content-main">
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
                        <div class="summary-icon green">✓</div>
                        <div class="summary-info">
                            <h3><?php echo $completedCount; ?></h3>
                            <p>Completed</p>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon red" style="cursor: pointer;" onclick="openCancelledModal()">⚠️</div>
                        <div class="summary-info">
                            <h3><?php echo $cancelledCount; ?></h3>
                            <p style="cursor: pointer;" onclick="openCancelledModal()">Cancelled</p>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon gray">⏸️</div>
                        <div class="summary-info">
                            <h3><?php echo $onHoldCount; ?></h3>
                            <p>On Hold</p>
                        </div>
                    </div>
                </div>

                <!-- Cancelled Modal -->
                <div id="cancelledModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
                    <div class="modal" style="background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 700px; max-height: 80vh; overflow-y: auto;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h2 style="font-size: 1.25rem; font-weight: 600; margin: 0;">Cancelled / On Hold</h2>
                            <button onclick="closeCancelledModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">×</button>
                        </div>
                        <div id="cancelled-modal-list">
                            <?php 
                            $cancelledItems = array_filter($queueItems, fn($q) => $q['status'] === 'cancelled');
                            if (empty($cancelledItems)): ?>
                                <p style="text-align: center; color: #6b7280; padding: 20px;">No cancelled patients today</p>
                            <?php else: ?>
                                <?php foreach ($cancelledItems as $item): ?>
                                <div class="patient-item" style="padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 12px;">
                                    <div class="patient-name" style="text-decoration: line-through; color: #9ca3af;"><?php echo htmlspecialchars($item['full_name'] ?? 'Unknown'); ?></div>
                                    <div style="color: #6b7280; font-size: 0.875rem;"><?php echo htmlspecialchars($item['treatment_type'] ?? ''); ?></div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Live Queue Controller -->
                <div class="live-queue">
                    <div class="live-queue-header">
                        <div class="live-queue-title">Live Queue Controller</div>
                        <div class="now-serving-badge"><span>👁️</span><span>Now Serving</span></div>
                    </div>
                    <div class="live-patient">
                        <div class="patient-name" id="live-patient-name"><?php echo $inProcedureItem ? htmlspecialchars($inProcedureItem['full_name']) : 'No Patient'; ?></div>
                        <div class="patient-details" style="margin-top: 10px;">
                            <span class="status-badge now-serving">In Chair</span>
                            <span class="patient-time" id="live-patient-time"><?php echo $inProcedureItem ? htmlspecialchars($inProcedureItem['queue_time']) : '--:--'; ?></span>
                        </div>
                        <div class="patient-treatment" id="live-patient-treatment" style="margin-top: 8px;"><?php echo $inProcedureItem ? htmlspecialchars($inProcedureItem['treatment_type']) : 'Queue Empty'; ?></div>
                    </div>
                    <?php if ($inProcedureItem): ?>
                    <button class="complete-btn" id="completeTreatmentBtn" onclick="completeCurrentPatient(<?php echo $inProcedureItem['id']; ?>)">
                        <span>✓</span> <span>Complete Treatment</span>
                    </button>
                    <?php else: ?>
                    <button class="complete-btn" id="completeTreatmentBtn" disabled style="background: #9ca3af;">
                        <span>✓</span> <span>No Patient in Chair</span>
                    </button>
                    <?php endif; ?>
                    <div class="complete-btn-text">Click when patient treatment is finished</div>
                </div>

                <!-- Up Next -->
                <div class="section-card">
                    <h2 class="section-title">⏭️ Up Next</h2>
                    <div class="patient-list" id="up-next-list">
                        <?php if (empty($waitingItems)): ?>
                            <div style="text-align: center; padding: 40px 20px; color: #6b7280;">
                                <p>No patients waiting</p>
                            </div>
                        <?php else: ?>
                            <?php foreach (array_slice($waitingItems, 0, 5) as $item): ?>
                            <div class="patient-item">
                                <div class="patient-info">
                                    <div class="patient-name"><?php echo htmlspecialchars($item['full_name'] ?? 'Unknown'); ?></div>
                                    <div class="patient-details">
                                        <span class="status-badge waiting">Waiting</span>
                                        <span class="patient-time"><?php echo htmlspecialchars($item['queue_time'] ?? ''); ?></span>
                                    </div>
                                    <div class="patient-treatment"><?php echo htmlspecialchars($item['treatment_type'] ?? ''); ?></div>
                                </div>
                                <div class="patient-actions">
                                    <button class="action-btn text-btn" onclick="viewPatientRecord(<?php echo $item['patient_id']; ?>)">See</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- On Hold -->
                <div class="section-card">
                    <h2 class="section-title">⏸️ On Hold</h2>
                    <div class="patient-list">
                        <?php if (empty($onHoldItems)): ?>
                            <div style="text-align: center; padding: 40px 20px; color: #6b7280;">
                                <p>No patients on hold</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($onHoldItems as $item): ?>
                            <div class="patient-item" style="opacity: 0.7;">
                                <div class="patient-name"><?php echo htmlspecialchars($item['full_name'] ?? 'Unknown'); ?></div>
                                <div style="color: #6b7280; font-size: 0.875rem;"><?php echo htmlspecialchars($item['treatment_type'] ?? ''); ?></div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon green">✓</div>
                        <div class="summary-info">
                            <h3><?php echo $completedCount; ?></h3>
                            <p>Completed</p>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon red">⚠️</div>
                        <div class="summary-info">
                            <h3><?php echo $cancelledCount; ?></h3>
                            <p>Cancelled</p>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon blue">👥</div>
                        <div class="summary-info">
                            <h3><?php echo $totalPatients; ?></h3>
                            <p>Total Patients (<?php echo $totalPercentage; ?>%)</p>
                        </div>
                    </div>
                </div>

                <!-- Today's Schedule -->
                <div class="section-card">
                    <h2 class="section-title">📅 Today's Schedule</h2>
                    <div class="patient-list">
                        <?php foreach ($todaySchedule as $patient): ?>
                        <div class="patient-item">
                            <div class="patient-info">
                                <div class="patient-name"><?php echo htmlspecialchars($patient['name']); ?></div>
                                <div class="patient-details">
                                    <span class="status-badge <?php echo $patient['status']; ?>">
                                        <?php 
                                        if (isset($patient['in_chair'])) {
                                            echo 'In Chair';
                                        } else {
                                            echo ucfirst(str_replace('-', ' ', $patient['status']));
                                        }
                                        ?>
                                    </span>
                                    <?php if (isset($patient['in_chair'])): ?>
                                    <span class="status-badge now-serving">NOW SERVING</span>
                                    <?php endif; ?>
                                    <span class="patient-time"><?php echo htmlspecialchars($patient['time']); ?></span>
                                </div>
                                <div class="patient-treatment"><?php echo htmlspecialchars($patient['treatment']); ?></div>
                            </div>
                            <div class="patient-actions">
                                <button class="action-btn icon view-btn" title="View">👁️</button>
                                <?php if ($patient['status'] !== 'now-serving'): ?>
                                <button class="action-btn icon delete-btn" title="Delete">🗑️</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Live Queue Controller -->
                <div class="live-queue">
                    <div class="live-queue-header">
                        <div class="live-queue-title">Live Queue Controller</div>
                        <div class="now-serving-badge">
                            <span>👁️</span>
                            <span>Now Serving</span>
                        </div>
                    </div>
                    
                    <div class="live-patient">
                        <div class="patient-name">Maria Santos</div>
                        <div class="patient-details" style="margin-top: 10px;">
                            <span class="status-badge in-chair">In Chair</span>
                            <span class="patient-time">09:00 AM</span>
                        </div>
                        <div class="patient-treatment" style="margin-top: 8px;">Follow-up Checkup</div>
                    </div>
                    
                    <button class="complete-btn" id="completeTreatmentBtn">
                        <span>✓</span>
                        <span>Complete Treatment</span>
                    </button>
                    <div class="complete-btn-text">Click when patient treatment is finished</div>
                </div>

                <!-- Up Next -->
                <div class="section-card">
                    <h2 class="section-title">⏭️ Up Next</h2>
                    <div class="patient-list">
                        <?php foreach ($upNext as $patient): ?>
                        <div class="patient-item">
                            <div class="patient-info">
                                <div class="patient-name"><?php echo htmlspecialchars($patient['name']); ?></div>
                                <div class="patient-details">
                                    <span class="status-badge <?php echo $patient['status']; ?>">Waiting</span>
                                    <span class="patient-time"><?php echo htmlspecialchars($patient['time']); ?></span>
                                </div>
                                <div class="patient-treatment"><?php echo htmlspecialchars($patient['treatment']); ?></div>
                            </div>
                            <div class="patient-actions">
                                <button class="action-btn icon view-btn" title="View">👁️</button>
                                <button class="action-btn primary call-patient-btn">Call Patient</button>
                                <button class="action-btn danger not-present-btn">Not Present</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Cancelled -->
                <div class="section-card">
                    <h2 class="section-title">❌ Cancelled</h2>
                    <div class="patient-list">
                        <?php foreach ($cancelled as $patient): ?>
                        <div class="patient-item">
                            <div class="patient-info">
                                <div class="patient-name"><?php echo htmlspecialchars($patient['name']); ?></div>
                                <div class="patient-details">
                                    <span class="status-badge <?php echo $patient['status']; ?>">Cancelled</span>
                                    <span class="patient-time"><?php echo htmlspecialchars($patient['time'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="patient-treatment"><?php echo htmlspecialchars($patient['treatment']); ?></div>
                                <?php if (isset($patient['reason'])): ?>
                                <div class="patient-treatment" style="color: #dc2626; margin-top: 4px;">
                                    <?php echo htmlspecialchars($patient['reason']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="patient-actions">
                                <button class="action-btn icon view-btn" title="View">👁️</button>
                                <button class="action-btn success requeue-btn">Re-queue</button>
                                <button class="action-btn icon delete-btn" title="Delete">🗑️</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar -->
            <aside class="content-sidebar">
                <!-- Notifications -->
                <div class="sidebar-section">
                    <h3 class="sidebar-section-title">Notification</h3>
                    <p class="sidebar-section-subtitle">Upcoming notifications</p>
                    
                    <div class="notification-item">
                        <div class="notification-icon">⏰</div>
                        <div class="notification-text">Next patient arriving in 15 mins</div>
                    </div>
                    
                    <button class="see-all-btn">See all notifications</button>
                </div>

                <!-- Daily Reminders -->
                <div class="sidebar-section">
                    <h3 class="sidebar-section-title">Daily Reminders / To-Do</h3>
                    
                    <div class="reminder-list">
                        <div class="reminder-item checked">
                            <div class="reminder-checkbox checked">✓</div>
                            <span class="reminder-text">Reply to Facebook inquiries from last night</span>
                        </div>
                        
                        <div class="reminder-item">
                            <div class="reminder-checkbox"></div>
                            <span class="reminder-text">Print consent forms for tomorrow</span>
                        </div>
                        
                        <div class="reminder-item">
                            <div class="reminder-checkbox"></div>
                            <span class="reminder-text">Call supplier for new stocks of Composite</span>
                        </div>
                    </div>
                    
                    <button class="add-reminder-btn" id="addReminderBtn">+ Add New Reminder</button>
                    <button class="see-all-btn">See all reminders</button>
                </div>
            </aside>
        </div>
    </main>

    <script src="assets/js/dashboard.js"></script>
</body>
</html>
