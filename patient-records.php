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

$username = $_SESSION['username'] ?? 'User';
$fullName = $_SESSION['full_name'] ?? 'Dr. Rex';

// Sample patient data for demonstration
$patients = [
    [
        'id' => 1,
        'name' => 'Maria Santos',
        'treatment' => 'Follow-up Checkup',
        'last_visit' => '2024-01-15',
        'time' => '09:00 AM'
    ],
    [
        'id' => 2,
        'name' => 'Roberto Garcia',
        'treatment' => 'Denture Adjustment',
        'last_visit' => '2024-01-10',
        'time' => '10:30 AM'
    ],
    [
        'id' => 3,
        'name' => 'Juan Dela Cruz',
        'treatment' => 'Root Canal',
        'last_visit' => '2023-12-20',
        'time' => '02:00 PM'
    ],
    [
        'id' => 4,
        'name' => 'Ana Reyes',
        'treatment' => 'Teeth Cleaning',
        'last_visit' => '2024-01-08',
        'time' => '11:15 AM'
    ]
];

$totalPatients = count($patients);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records - RF Dental Clinic</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f7fa;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .sidebar {
            width: 260px;
            background: #FFFFFF;
            color: rgb(0, 0, 0);
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar-logo {
            padding: 25px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-logo span {
            font-size: 18px;
            font-weight: 600;
            color: rgb(0, 0, 0);
        }

        .sidebar-nav {
            flex: 1;
            padding: 20px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            color: rgba(0, 0, 0, 0.8);
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.1);
            color: #0575D5;
        }

        .nav-item.active {
            background: #2563eb;
            color: white;
            border-left: 4px solid #60a5fa;
        }

        .sidebar-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: 10px;
            padding-left: 20px;
        }

        .main-content {
            flex: 1;
            background: #f5f7fa;
            min-height: 100vh;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }

        .top-header {
            background: white;
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            z-index: 1001;
            position: relative;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            z-index: 1;
        }

        .menu-toggle {
            display: none;
            flex-direction: column;
            gap: 4px;
            cursor: pointer;
            padding: 8px;
            margin-right: 10px;
            border-radius: 4px;
            transition: background 0.2s;
            position: relative;
            z-index: 2000;
            user-select: none;
            -webkit-user-select: none;
            pointer-events: auto !important;
            min-width: 40px;
        }

        .menu-toggle:hover {
            background: #f3f4f6;
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }

        .header-right {
            display: flex;
            align-items: center;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .user-name {
            font-weight: 500;
            color: #374151;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: #e5e7eb;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            font-weight: 600;
        }

        .search-toolbar {
            background: transparent;
            padding: 20px 30px;
            display: flex;
            align-items: center;
            gap: 16px;
            justify-content: space-between;
        }

        .search-input-container {
            position: relative;
            flex: 1;
        }

        .search-input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 1px solid #d1d5db;
            border-radius: 9999px;
            font-size: 14px;
            background: transparent;
        }

        .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            font-size: 16px;
        }

        .toolbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .filter-btn {
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: transparent;
            color: #374151;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .total-patients-box {
            padding: 12px 16px;
            background: transparent;
            border-radius: 8px;
            font-weight: 600;
            color: #1f2937;
            border: 1px solid #d1d5db;
        }

        .action-btn {
            padding: 12px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .patient-table-container {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin: 30px;
            margin-top: 0;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .patient-table {
            width: 100%;
            border-collapse: collapse;
        }

        .patient-table thead {
            background: #f9fafb;
        }

        .patient-table th {
            padding: 16px 24px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
        }

        .patient-table td {
            padding: 20px 24px;
            border-bottom: 1px solid #f3f4f6;
        }

        .patient-name-cell {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.3s;
            padding: 8px 12px;
            border-radius: 6px;
        }
        
        .patient-name-cell:hover {
            background: #dbeafe;
            transform: translateX(4px);
        }
        
        .patient-avatar {
            width: 40px;
            height: 40px;
            background: #e5e7eb;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            font-weight: 600;
        }

        .patient-name {
            font-weight: 500;
            color: #1f2937;
        }

        .time-date-cell {
            display: flex;
            flex-direction: column;
        }

        .time-text {
            font-weight: 600;
            color: #1f2937;
        }

        .date-text {
            color: #6b7280;
            font-size: 14px;
        }

        .treatment-text {
            color: #374151;
        }

        .actions-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #6b7280;
        }

        .delete-btn {
            color: #dc2626;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 20px;
            background: transparent;
        }

        .pagination-btn {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: transparent;
            color: #374151;
            cursor: pointer;
            font-size: 14px;
        }

        .pagination-btn.active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
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
            <a href="patient-records.php" class="nav-item active">
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
        
        <div class="sidebar-footer">
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
                    <h1>Patient Records</h1>
                    <p>Manage patient records and appointments.</p>
                </div>
            </div>
            
            <div class="header-right">
                <div class="user-profile">
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 32 32'%3E%3Ccircle cx='16' cy='16' r='16' fill='%23e5e7eb'/%3E%3Ctext x='16' y='22' font-family='Arial' font-size='18' fill='%236b7280' text-anchor='middle'%3E%F0%9F%91%A4%3C/text%3E%3C/svg%3E" alt="User">
                    <span class="user-name"><?php echo htmlspecialchars($fullName); ?></span>
                </div>
            </div>
        </header>

        <!-- Search & Filter Section -->
        <div class="search-toolbar">
            <div class="search-input-container">
                <span class="search-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="m19.6 21l-6.3-6.3q-.75.6-1.725.95T9.5 16q-2.725 0-4.612-1.888T3 9.5t1.888-4.612T9.5 3t4.613 1.888T16 9.5q0 1.1-.35 2.075T14.7 13.3l6.3 6.3zM9.5 14q1.875 0 3.188-1.312T14 9.5t-1.312-3.187T9.5 5T6.313 6.313T5 9.5t1.313 3.188T9.5 14"/></svg></span>
                <input type="text" class="search-input" placeholder="Search name" id="searchInput">
            </div>
            <div class="toolbar-right">
                <button class="filter-btn">
                    <span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M3.75 7a.75.75 0 0 1 .75-.75h15a.75.75 0 0 1 0 1.5h-15A.75.75 0 0 1 3.75 7m2.5 5a.75.75 0 0 1 .75-.75h10a.75.75 0 0 1 0 1.5H7a.75.75 0 0 1-.75-.75m3 5a.75.75 0 0 1 .75-.75h4a.75.75 0 0 1 0 1.5h-4a.75.75 0 0 1-.75-.75"/></svg></span>
                    Filter
                </button>
                <div class="total-patients-box">
                    Total Patient: <?php echo $totalPatients; ?>
                </div>
                <button class="action-btn btn-primary">Add New Patient</button>
            </div>
        </div>

        <!-- Patient List Table -->
        <div class="patient-table-container">
            <table class="patient-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Time and Date</th>
                        <th>Treatment</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $patient): ?>
                    <tr>
                        <td>
                            <a href="patient_details.php?id=<?php echo $patient['id']; ?>" class="patient-name-cell" style="text-decoration: none; color: inherit;">
                                <div class="patient-avatar">
                                    <?php echo strtoupper(substr($patient['name'], 0, 1)); ?>
                                </div>
                                <span class="patient-name"><?php echo htmlspecialchars($patient['name']); ?></span>
                            </a>
                        </td>
                        <td>
                            <div class="time-date-cell">
                                <span class="time-text"><?php echo htmlspecialchars($patient['time']); ?></span>
                                <span class="date-text"><?php echo htmlspecialchars($patient['last_visit']); ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="treatment-text"><?php echo htmlspecialchars($patient['treatment']); ?></span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <button class="action-btn">⋯</button>
                                <button class="action-btn delete-btn">🗑️</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination">
            <button class="pagination-btn" disabled>Prev</button>
            <button class="pagination-btn active">1</button>
            <button class="pagination-btn">2</button>
            <button class="pagination-btn">Next</button>
        </div>
    </main>

    <script src="assets/js/dashboard.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.patient-table tbody tr');
            
            rows.forEach(row => {
                const name = row.querySelector('.patient-name').textContent.toLowerCase();
                row.style.display = name.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>