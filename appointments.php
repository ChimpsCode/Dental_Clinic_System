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
    <style>
        /* --- CSS VARIABLES & RESET --- */
        :root {
            --primary-color: #3b82f6; /* Royal Blue */
            --primary-hover: #2563eb;
            --text-dark: #1f2937;
            --text-gray: #6b7280;
            --text-light: #9ca3af;
            --bg-body: #f3f4f6;
            --bg-white: #ffffff;
            --border-color: #e5e7eb;
            --card-shadow: 0 2px 8px rgba(0,0,0,0.05);
            --sidebar-width: 260px;
            --header-height: 70px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-dark);
            height: 100vh;
            display: flex;
            overflow: hidden;
        }

        /* --- SIDEBAR --- */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--bg-white);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            padding: 20px;
            z-index: 10;
            transition: var(--transition);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 40px;
            color: var(--primary-color);
            font-weight: 700;
            font-size: 18px;
        }
        
        .logo svg {
            width: 32px;
            height: 32px;
        }

        .nav-menu {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            text-decoration: none;
            color: var(--text-gray);
            border-radius: 8px;
            font-weight: 500;
            transition: var(--transition);
            cursor: pointer;
        }

        .nav-item svg {
            width: 20px;
            height: 20px;
            stroke-width: 2;
        }

        .nav-item:hover {
            background-color: #f9fafb;
            color: var(--primary-color);
        }

        .nav-item.active {
            background-color: #eff6ff;
            color: var(--primary-color);
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        /* --- MAIN CONTENT AREA --- */
        .main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* --- HEADER --- */
        header {
            height: var(--header-height);
            background-color: var(--bg-white);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 30px;
            border-bottom: 1px solid var(--border-color);
        }

        .header-title h1 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .header-title p {
            font-size: 13px;
            color: var(--text-gray);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-name {
            font-size: 14px;
            font-weight: 600;
        }

        /* --- DASHBOARD CONTENT --- */
        .content-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-white);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
            border: 1px solid transparent;
        }

        /* Top border colors per image */
        .stat-card.total { border-top: 4px solid #fbbf24; }
        .stat-card.today { border-top: 4px solid #10b981; }
        .stat-card.tomorrow { border-top: 4px solid #3b82f6; }
        .stat-card.upcoming { border-top: 4px solid #ef4444; }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .stat-menu {
            color: var(--text-light);
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-card.total .stat-value { color: #d97706; }
        .stat-card.today .stat-value { color: #059669; }
        .stat-card.tomorrow .stat-value { color: #2563eb; }
        .stat-card.upcoming .stat-value { color: #dc2626; }

        .stat-label {
            font-size: 14px;
            color: var(--text-gray);
        }

        /* Filter Section */
        .filter-container {
            background: var(--bg-white);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        .search-wrapper {
            position: relative;
            margin-bottom: 20px;
        }

        .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-gray);
            width: 18px;
            height: 18px;
        }

        .search-input {
            width: 100%;
            padding: 12px 12px 12px 44px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        .search-input:focus {
            border-color: var(--primary-color);
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            background: var(--bg-white);
            border-radius: 8px;
            color: var(--text-gray);
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .filter-btn.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* Empty State */
        .empty-state-container {
            background: var(--bg-white);
            padding: 60px 20px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            text-align: center;
        }

        .empty-icon {
            font-size: 48px;
            color: #d1d5db;
            margin-bottom: 16px;
            display: inline-block;
        }

        .empty-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .empty-desc {
            color: var(--text-gray);
            margin-bottom: 30px;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.2);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        /* --- MODAL (Hidden by default) --- */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 100;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .modal-overlay.open {
            display: flex;
            opacity: 1;
        }

        .modal {
            background: white;
            width: 100%;
            max-width: 500px;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transform: translateY(20px);
            transition: transform 0.3s;
        }

        .modal-overlay.open .modal {
            transform: translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-gray);
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-dark);
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
        }

        .modal-footer {
            margin-top: 24px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-secondary {
            background: white;
            border: 1px solid var(--border-color);
            color: var(--text-dark);
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                position: absolute;
                left: -100%;
                height: 100%;
            }
            .sidebar.show {
                left: 0;
            }
            header {
                padding: 0 20px;
            }
            .content-scroll {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="logo">
            <!-- Tooth Icon Placeholder -->
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4.22 19.78c.49-.49 1.08-1.21 1.08-2.58 0-1.63-1.02-2.28-1.3-3.15-.31-.96.09-2.13.96-2.66 1.26-.77 2.72-.19 3.42.81.39.56.62.94.62.94s.23-.38.62-.94c.7-1 2.16-1.58 3.42-.81.87.53 1.27 1.7.96 2.66-.28.87-1.3 1.52-1.3 3.15 0 1.37.59 2.09 1.08 2.58"></path>
                <path d="M12 2C8 2 7 5 7 7c0 1.68.83 2.5 1.46 3.08"></path>
                <path d="M12 2c4 0 5 3 5 5 0 1.68-.83 2.5-1.46 3.08"></path>
                <path d="M9 13.5v2.7"></path>
                <path d="M15 13.5v2.7"></path>
            </svg>
            RF Dental Clinic
        </div>

        <nav class="nav-menu">
            <a href="#" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                Dashboard
            </a>
            <a href="#" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                Patient Records
            </a>
            <a href="#" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                New Admission
            </a>
            <a href="#" class="nav-item active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                Appointments
            </a>
            <a href="#" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                Analytics
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="#" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                Logout
            </a>
        </div>
    </aside>

    <!-- MAIN WRAPPER -->
    <div class="main-wrapper">
        
        <!-- HEADER -->
        <header>
            <div class="header-title">
                <h1>Appointments</h1>
                <p>Manage and schedule patient appointments</p>
            </div>
            <div class="user-profile">
                <span class="user-name">Dr. Ann</span>
                <div class="user-avatar">
                    <img src="https://picsum.photos/seed/doctorann/100/100" alt="Dr. Ann Avatar">
                </div>
            </div>
        </header>

        <!-- CONTENT SCROLL AREA -->
        <div class="content-scroll">
            
            <!-- STATS CARDS -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-header">
                        <div class="stat-menu">⋮</div>
                    </div>
                    <div class="stat-value">0</div>
                    <div class="stat-label">Total Appointments</div>
                </div>
                <div class="stat-card today">
                    <div class="stat-header">
                        <div class="stat-menu">⋮</div>
                    </div>
                    <div class="stat-value">0</div>
                    <div class="stat-label">Today</div>
                </div>
                <div class="stat-card tomorrow">
                    <div class="stat-header">
                        <div class="stat-menu">⋮</div>
                    </div>
                    <div class="stat-value">0</div>
                    <div class="stat-label">Tomorrow</div>
                </div>
                <div class="stat-card upcoming">
                    <div class="stat-header">
                        <div class="stat-menu">⋮</div>
                    </div>
                    <div class="stat-value">0</div>
                    <div class="stat-label">Upcoming</div>
                </div>
            </div>

            <!-- FILTER SECTION -->
            <div class="filter-container">
                <div class="search-wrapper">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" class="search-input" placeholder="Search appointments...">
                </div>
                <div class="filter-buttons">
                    <button class="filter-btn active">All (0)</button>
                    <button class="filter-btn">Today (0)</button>
                    <button class="filter-btn">Tomorrow (0)</button>
                    <button class="filter-btn">Upcoming (0)</button>
                </div>
            </div>

            <!-- EMPTY STATE (As shown in image) -->
            <div class="empty-state-container">
                <div class="empty-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                </div>
                <h3 class="empty-title">No Appointments Found</h3>
                <p class="empty-desc">Start by creating a new appointment</p>
                <button class="btn-primary" id="btnNewAppointment">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    New Appointment
                </button>
            </div>

        </div>
    </div>

    <!-- NEW APPOINTMENT MODAL -->
    <div class="modal-overlay" id="appointmentModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">New Appointment</h3>
                <button class="close-modal" id="closeModal">&times;</button>
            </div>
            <form id="appointmentForm">
                <div class="form-group">
                    <label class="form-label">Patient Name</label>
                    <input type="text" class="form-control" placeholder="Enter full name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Time</label>
                    <input type="time" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea class="form-control" rows="3" placeholder="Reason for visit..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" id="cancelBtn">Cancel</button>
                    <button type="submit" class="btn-primary">Create Appointment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JAVASCRIPT LOGIC -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            
            // --- FILTER BUTTONS LOGIC ---
            const filterBtns = document.querySelectorAll('.filter-btn');
            
            filterBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    // Remove active class from all
                    filterBtns.forEach(b => b.classList.remove('active'));
                    // Add active to clicked
                    btn.classList.add('active');
                    
                    // In a real app, this would filter data. 
                    // Here we just visually toggle to simulate the interaction.
                });
            });

            // --- MODAL LOGIC ---
            const modal = document.getElementById('appointmentModal');
            const openBtn = document.getElementById('btnNewAppointment');
            const closeBtn = document.getElementById('closeModal');
            const cancelBtn = document.getElementById('cancelBtn');
            const form = document.getElementById('appointmentForm');

            function openModal() {
                modal.classList.add('open');
            }

            function closeModal() {
                modal.classList.remove('open');
                form.reset();
            }

            openBtn.addEventListener('click', openModal);
            closeBtn.addEventListener('click', closeModal);
            cancelBtn.addEventListener('click', closeModal);

            // Close on clicking outside modal content
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeModal();
                }
            });

            // Form Submission Simulation
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                
                // Simulate API call/processing
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                
                submitBtn.textContent = "Saving...";
                submitBtn.disabled = true;

                setTimeout(() => {
                    // Show success feedback (simple alert替代)
                    // Ideally this would be a toast notification
                    closeModal();
                    
                    // Update the UI to show the new appointment
                    // For this demo, we just update the "Total" count to show something happened
                    const totalCard = document.querySelector('.stat-card.total .stat-value');
                    let count = parseInt(totalCard.textContent);
                    totalCard.textContent = count + 1;

                    // Hide empty state and show list (Simulated)
                    const emptyState = document.querySelector('.empty-state-container');
                    const emptyTitle = emptyState.querySelector('.empty-title');
                    const emptyDesc = emptyState.querySelector('.empty-desc');
                    
                    emptyTitle.textContent = "Appointment Created!";
                    emptyDesc.textContent = "List view would be updated here in the full version.";
                    
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }, 800);
            });

            // --- SIDEBAR ACTIVE STATE ---
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', (e) => {
                    e.preventDefault(); // Prevent actual navigation
                    navItems.forEach(n => n.classList.remove('active'));
                    item.classList.add('active');
                });
            });
        });
    </script>
</body>
</html>