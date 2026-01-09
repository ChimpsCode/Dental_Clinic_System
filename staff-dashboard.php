<?php
ob_start();
session_start();

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'] ?? 'User';
$fullName = $_SESSION['full_name'] ?? 'Staff Member';

// Sample data
$waitingCount = 3;
$completedCount = 0;
$cancelledCount = 0;
$skippedCount = 0;

$todaySchedule = [
    ['name' => 'Maria Santos', 'status' => 'now-serving', 'in_chair' => true, 'treatment' => 'Root Canal (Session 2)', 'time' => '09:30 AM', 'source' => 'Source: Phone Call'],
    ['name' => 'Juan Dela Cruz', 'status' => 'waiting', 'treatment' => 'Root Canal (Session 2)', 'time' => '09:00 AM', 'source' => 'Source: Walk-in'],
    ['name' => 'Ana Reyes', 'status' => 'waiting', 'treatment' => 'Oral Prophylaxis', 'time' => '10:00 AM', 'source' => 'Source: Walk-in'],
    ['name' => 'Roberto Garcia', 'status' => 'waiting', 'treatment' => 'Denture Adjustment', 'time' => '11:00 AM', 'source' => 'Source: Phone Call']
];

$upNext = [
    ['name' => 'Juan Dela Cruz', 'status' => 'waiting', 'treatment' => 'Root Canal (Session 2)', 'time' => '09:00 AM'],
    ['name' => 'Ana Reyes', 'status' => 'waiting', 'treatment' => 'Oral Prophylaxis', 'time' => '10:30 AM']
];

$cancelled = [
    ['name' => 'Roberto Garcia', 'status' => 'cancelled', 'treatment' => 'Denture Adjustment', 'reason' => 'No show / Did not arrive']
];

$notifications = [
    ['type' => 'appointment', 'message' => 'Upcoming Appointment', 'detail' => 'Next patient arriving in 10 mins'],
    ['type' => 'reminder', 'message' => 'Daily Reminders / To-Do', 'items' => ['Reply to Facebook inquiries from last night', 'Print consent form for tomorrow', 'Call inquiries for new stocks of Composites']]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - RF Dental Clinic</title>
    <link rel="stylesheet" href="assets/css/staff_dashboard.css">
   
</head>
<body>
    <!-- Left Sidebar (Minimal) -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="assets/images/Logo.png" alt="RF Logo">
        </div>
        
        <nav class="sidebar-nav">
            <a href="staff-dashboard.php" class="nav-item active">
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M3 12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1zm0 8a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1zm10 0a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-6a1 1 0 0 0-1 1zm1-17a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1z"/></svg></span>
                <span>Dashboard</span>
            </a>
            <a href="patient-information.php" class="nav-item">
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3s1.34 3 3 3m-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5S5 6.34 5 8s1.34 3 3 3m0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5m8 0c-.29 0-.62.02-.97.05c1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5"/></svg></span>
                <span>Patient Information</span>
            </a>
            <a href="appointments.php" class="nav-item">
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3m1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/></svg></span>
                <span>Appointments</span>
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item">
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M5 21q-.825 0-1.412-.587T3 19V5q0-.825.588-1.412T5 3h6q.425 0 .713.288T12 4t-.288.713T11 5H5v14h6q.425 0 .713.288T12 20t-.288.713T11 21zm12.175-8H10q-.425 0-.712-.288T9 12t.288-.712T10 11h7.175L15.3 9.125q-.275-.275-.275-.675t.275-.7t.7-.313t.725.288L20.3 11.3q.3.3.3.7t-.3.7l-3.575 3.575q-.3.3-.712.288t-.713-.313q-.275-.3-.262-.712t.287-.688z"/></svg></span>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <div class="header-title">
                    <h1>Inquiry & Queue Dashboard</h1>
                    <p>Manage patient inquiries and today's queue.</p>
                </div>
            </div>
            
            <div class="header-right">
                <div class="user-profile">
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 32 32'%3E%3Ccircle cx='16' cy='16' r='16' fill='%23e5e7eb'/%3E%3Ctext x='16' y='22' font-family='Arial' font-size='18' fill='%236b7280' text-anchor='middle'%3E%F0%9F%91%A4%3C/text%3E%3C/svg%3E" alt="User">
                    <span class="user-name">Dr. <?php echo htmlspecialchars($fullName); ?></span>
                </div>
            </div>
        </header>

        <!-- Content Area -->
        <div class="content-area" style="padding: 20px 30px;">
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
                    <div class="summary-icon red">⚠️</div>
                    <div class="summary-info">
                        <h3><?php echo $cancelledCount; ?></h3>
                        <p>Cancelled</p>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon gray" style="background: #f3f4f6; color: #6b7280;">⊘</div>
                    <div class="summary-info">
                        <h3><?php echo $skippedCount; ?></h3>
                        <p>Skipped</p>
                    </div>
                </div>
            </div>

            <!-- Two Column Layout -->
            <div class="two-column">
                <div class="left-column">
                    <!-- Today's Schedule -->
                    <div class="section-card">
                        <h2 class="section-title">📅 Today's Schedule</h2>
                        <div class="patient-list">
                            <?php foreach ($todaySchedule as $patient): ?>
                            <div class="patient-item">
                                <div class="patient-info">
                                    <div class="patient-name"><?php echo htmlspecialchars($patient['name']); ?></div>
                                    <div class="patient-details">
                                        <span class="status-badge <?php echo str_replace('-', ' ', $patient['status']); ?>">
                                            <?php 
                                            if (isset($patient['in_chair'])) {
                                                echo 'NOW SERVING';
                                            } else {
                                                echo ucfirst(str_replace('-', ' ', $patient['status']));
                                            }
                                            ?>
                                        </span>
                                        <span class="patient-time"><?php echo htmlspecialchars($patient['time']); ?></span>
                                    </div>
                                    <div class="patient-treatment"><?php echo htmlspecialchars($patient['treatment']); ?></div>
                                    <div style="font-size: 12px; color: #6b7280; margin-top: 4px;"><?php echo htmlspecialchars($patient['source']); ?></div>
                                </div>
                                <div class="patient-actions">
                                    <button class="action-btn icon view-btn" title="View">👁️</button>
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
                                <span class="status-badge now-serving">In Chair</span>
                                <span class="patient-time">09:30 AM</span>
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
                                        <span class="status-badge <?php echo $patient['status']; ?>">
                                            <?php echo ucfirst(str_replace('-', ' ', $patient['status'])); ?>
                                        </span>
                                        <span class="patient-time"><?php echo htmlspecialchars($patient['time']); ?></span>
                                    </div>
                                    <div class="patient-treatment"><?php echo htmlspecialchars($patient['treatment']); ?></div>
                                </div>
                                <div class="patient-actions">
                                    <button class="action-btn icon view-btn" title="View">👁️</button>
                                    <button class="action-btn icon delete-btn" title="Call Patient">📞</button>
                                    <button class="action-btn icon delete-btn" title="Not Present">⚠️</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Cancelled -->
                    <div class="section-card">
                        <h2 class="section-title">Cancelled</h2>
                        <div class="patient-list">
                            <?php foreach ($cancelled as $patient): ?>
                            <div class="patient-item">
                                <div class="patient-info">
                                    <div class="patient-name"><?php echo htmlspecialchars($patient['name']); ?></div>
                                    <div class="patient-details">
                                        <span class="status-badge cancelled">Cancelled</span>
                                    </div>
                                    <div class="patient-treatment"><?php echo htmlspecialchars($patient['treatment']); ?></div>
                                    <div style="font-size: 12px; color: #6b7280; margin-top: 4px;"><?php echo htmlspecialchars($patient['reason']); ?></div>
                                </div>
                                <div class="patient-actions">
                                    <button class="action-btn icon view-btn" title="View">👁️</button>
                                    <button class="action-btn" style="background: #84cc16; color: white; padding: 8px 12px; border-radius: 6px; border: none; font-size: 12px; cursor: pointer;">Re-queue</button>
                                    <button class="action-btn icon delete-btn" title="Delete">🗑️</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Notifications -->
                <div class="right-column">
                    <div class="notification-box">
                        <h3>🔔 Notification</h3>
                        <div class="notification-item">
                            <div class="notification-title">Upcoming Appointment</div>
                            <div class="notification-detail">Next patient arriving in 10 mins</div>
                        </div>
                    </div>

                    <div class="notification-box">
                        <h3>✓ Daily Reminders / To-Do</h3>
                        <ul class="reminder-list">
                            <li>Reply to Facebook inquiries from last night</li>
                            <li>Print consent form for tomorrow</li>
                            <li>Call inquiries for new stocks of Composites</li>
                        </ul>
                        <button class="add-reminder-btn">+ Add New Reminder</button>
                        <a href="#" class="see-all-link">See all reminders</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/js/dashboard.js"></script>
</body>
</html>
