<?php
// Start output buffering for faster response
ob_start();
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';

$username = $_SESSION['username'] ?? 'User';
$fullName = $_SESSION['full_name'] ?? 'Dr. Ann';

// Get filter from query string
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Calculate appointment counts
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

try {
    // Get total appointments count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments");
    $totalAppointments = $stmt->fetch()['total'] ?? 0;
    
    // Get today's appointments count
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE DATE(appointment_date) = ?");
    $stmt->execute([$today]);
    $todayCount = $stmt->fetch()['total'] ?? 0;
    
    // Get tomorrow's appointments count
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE DATE(appointment_date) = ?");
    $stmt->execute([$tomorrow]);
    $tomorrowCount = $stmt->fetch()['total'] ?? 0;
    
    // Get upcoming appointments count (after tomorrow)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE DATE(appointment_date) > ?");
    $stmt->execute([$tomorrow]);
    $upcomingCount = $stmt->fetch()['total'] ?? 0;
    
    // Get appointments based on filter
    $appointments = [];
    if ($filter === 'today') {
        $stmt = $pdo->prepare("SELECT a.*, p.full_name, p.phone FROM appointments a 
                              LEFT JOIN patients p ON a.patient_id = p.id 
                              WHERE DATE(a.appointment_date) = ? 
                              AND (p.full_name LIKE ? OR p.phone LIKE ?)
                              ORDER BY a.appointment_date ASC");
        $searchTerm = "%$search%";
        $stmt->execute([$today, $searchTerm, $searchTerm]);
        $appointments = $stmt->fetchAll();
    } elseif ($filter === 'tomorrow') {
        $stmt = $pdo->prepare("SELECT a.*, p.full_name, p.phone FROM appointments a 
                              LEFT JOIN patients p ON a.patient_id = p.id 
                              WHERE DATE(a.appointment_date) = ? 
                              AND (p.full_name LIKE ? OR p.phone LIKE ?)
                              ORDER BY a.appointment_date ASC");
        $searchTerm = "%$search%";
        $stmt->execute([$tomorrow, $searchTerm, $searchTerm]);
        $appointments = $stmt->fetchAll();
    } elseif ($filter === 'upcoming') {
        $stmt = $pdo->prepare("SELECT a.*, p.full_name, p.phone FROM appointments a 
                              LEFT JOIN patients p ON a.patient_id = p.id 
                              WHERE DATE(a.appointment_date) > ? 
                              AND (p.full_name LIKE ? OR p.phone LIKE ?)
                              ORDER BY a.appointment_date ASC");
        $searchTerm = "%$search%";
        $stmt->execute([$tomorrow, $searchTerm, $searchTerm]);
        $appointments = $stmt->fetchAll();
    } else {
        // All appointments
        $stmt = $pdo->prepare("SELECT a.*, p.full_name, p.phone FROM appointments a 
                              LEFT JOIN patients p ON a.patient_id = p.id 
                              WHERE p.full_name LIKE ? OR p.phone LIKE ?
                              ORDER BY a.appointment_date ASC");
        $searchTerm = "%$search%";
        $stmt->execute([$searchTerm, $searchTerm]);
        $appointments = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // If tables don't exist, initialize them
    error_log("Database error: " . $e->getMessage());
    $totalAppointments = 0;
    $todayCount = 0;
    $tomorrowCount = 0;
    $upcomingCount = 0;
    $appointments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - RF Dental Clinic</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/appointments.css">
</head>
<body>
    <!-- Left Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="assets/images/Logo.png" alt="RF Logo">
            <span>RF Dental Clinic</span>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
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
            <a href="appointments.php" class="nav-item active">
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3m1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/></svg></span>
                <span>Appointments</span>
            </a>
            <a href="analytics.php" class="nav-item">
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M3 22V8h4v14zm7 0V2h4v20zm7 0v-8h4v8z"/></svg></span>
                <span>Analytics</span>
            </a>
            <a href="#" class="nav-item">
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
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <div class="menu-toggle" id="menuToggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <div class="header-title">
                    <h1>Appointments</h1>
                    <p>Manage and schedule patient appointments</p>
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
            <div class="appointments-container">
                <!-- Summary Cards -->
                <div class="appointment-summary-cards">
                    <div class="appointment-summary-card total">
                        <div class="card-menu">⋯</div>
                        <h3><?php echo $totalAppointments; ?></h3>
                        <p>Total Appointments</p>
                    </div>
                    
                    <div class="appointment-summary-card today">
                        <div class="card-menu">⋯</div>
                        <h3><?php echo $todayCount; ?></h3>
                        <p>Today</p>
                    </div>
                    
                    <div class="appointment-summary-card tomorrow">
                        <div class="card-menu">⋯</div>
                        <h3><?php echo $tomorrowCount; ?></h3>
                        <p>Tomorrow</p>
                    </div>
                    
                    <div class="appointment-summary-card upcoming">
                        <div class="card-menu">⋯</div>
                        <h3><?php echo $upcomingCount; ?></h3>
                        <p>Upcoming</p>
                    </div>
                </div>

                <!-- Search and Filter Section -->
                <div class="search-filter-section">
                    <form method="GET" action="appointments.php" id="searchForm">
                        <div class="search-bar">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                            <input type="text" name="search" placeholder="Search by name or phone..." value="<?php echo htmlspecialchars($search); ?>">
                            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        </div>
                        
                        <div class="filter-buttons">
                            <button type="button" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>" data-filter="all">
                                All (<?php echo $totalAppointments; ?>)
                            </button>
                            <button type="button" class="filter-btn <?php echo $filter === 'today' ? 'active' : ''; ?>" data-filter="today">
                                Today (<?php echo $todayCount; ?>)
                            </button>
                            <button type="button" class="filter-btn <?php echo $filter === 'tomorrow' ? 'active' : ''; ?>" data-filter="tomorrow">
                                Tomorrow (<?php echo $tomorrowCount; ?>)
                            </button>
                            <button type="button" class="filter-btn <?php echo $filter === 'upcoming' ? 'active' : ''; ?>" data-filter="upcoming">
                                Upcoming (<?php echo $upcomingCount; ?>)
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Appointments List or Empty State -->
                <?php if (empty($appointments)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📅</div>
                    <h3>No Appointments Found</h3>
                    <p>Start by creating a new appointment</p>
                    <button class="new-appointment-btn" onclick="alert('New Appointment feature coming soon!')">
                        <span>+</span>
                        <span>New Appointment</span>
                    </button>
                </div>
                <?php else: ?>
                <div class="appointments-list">
                    <?php foreach ($appointments as $appointment): ?>
                    <div class="appointment-item">
                        <div class="appointment-info">
                            <h4><?php echo htmlspecialchars($appointment['full_name'] ?? 'Unknown Patient'); ?></h4>
                            <p class="appointment-date">
                                <?php 
                                $date = new DateTime($appointment['appointment_date']);
                                echo $date->format('F d, Y') . ' at ' . $date->format('h:i A');
                                ?>
                            </p>
                            <?php if (!empty($appointment['phone'])): ?>
                            <p>Phone: <?php echo htmlspecialchars($appointment['phone']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($appointment['notes'])): ?>
                            <p>Notes: <?php echo htmlspecialchars($appointment['notes']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="patient-actions">
                            <button class="action-btn icon view-btn" title="View">👁️</button>
                            <button class="action-btn icon delete-btn" title="Delete">🗑️</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="assets/js/dashboard.js"></script>
    <script>
        // Filter button functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const filter = this.getAttribute('data-filter');
                const form = document.getElementById('searchForm');
                const filterInput = form.querySelector('input[name="filter"]');
                filterInput.value = filter;
                form.submit();
            });
        });
        
        // Search form submission
        document.getElementById('searchForm').addEventListener('submit', function(e) {
            // Form will submit normally
        });
    </script>
</body>
</html>
