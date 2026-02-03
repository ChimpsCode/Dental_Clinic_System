<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Set display name from session
$username = $_SESSION['username'] ?? 'User';
$fullName = $_SESSION['full_name'] ?? 'Administrator';

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $avg_service_time = intval($_POST['avg_service_time'] ?? 30);
        $max_patients_per_day = intval($_POST['max_patients_per_day'] ?? 40);
        $auto_waiting_to_serving = isset($_POST['auto_waiting_to_serving']) ? 1 : 0;
        $auto_serving_to_completed = isset($_POST['auto_serving_to_completed']) ? 1 : 0;
        $enable_walkin = isset($_POST['enable_walkin']) ? 1 : 0;
        $priority_senior = isset($_POST['priority_senior']) ? 1 : 0;
        $priority_pwd = isset($_POST['priority_pwd']) ? 1 : 0;
        $priority_emergency = isset($_POST['priority_emergency']) ? 1 : 0;
        $queue_format = $_POST['queue_format'] ?? 'Q-###';

        // Validate inputs
        if ($avg_service_time < 1) {
            throw new Exception('Average service time must be at least 1 minute');
        }
        if ($max_patients_per_day < 1) {
            throw new Exception('Maximum patients per day must be at least 1');
        }

        // Save settings to session (in a real app, save to database)
        $_SESSION['queue_settings'] = [
            'avg_service_time' => $avg_service_time,
            'max_patients_per_day' => $max_patients_per_day,
            'auto_waiting_to_serving' => $auto_waiting_to_serving,
            'auto_serving_to_completed' => $auto_serving_to_completed,
            'enable_walkin' => $enable_walkin,
            'priority_senior' => $priority_senior,
            'priority_pwd' => $priority_pwd,
            'priority_emergency' => $priority_emergency,
            'queue_format' => $queue_format
        ];

        $success_message = 'Queue settings saved successfully!';
    } catch (Exception $e) {
        $error_message = 'Error: ' . $e->getMessage();
    }
}

// Load current settings
$settings = $_SESSION['queue_settings'] ?? [
    'avg_service_time' => 30,
    'max_patients_per_day' => 40,
    'auto_waiting_to_serving' => 1,
    'auto_serving_to_completed' => 1,
    'enable_walkin' => 0,
    'priority_senior' => 1,
    'priority_pwd' => 1,
    'priority_emergency' => 1,
    'queue_format' => 'Q-###'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Settings - Dental Clinic System</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/settings.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f5f7fa;
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
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-logo {
            padding: 25px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-logo img {
            width: 40px;
            height: 40px;
        }

        .sidebar-logo span {
            font-size: 18px;
            font-weight: 600;
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

        .nav-item-icon {
            width: 20px;
            height: 20px;
            font-size: 18px;
        }

        .nav-item-icon svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        .sidebar-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .top-header {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .header-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 15px;
            border-left: 1px solid #eee;
        }

        /* Admin profile in top header (compact) */
        .admin-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f3f4f6;
            padding: 6px 10px;
            border-radius: 999px;
            box-shadow: none;
        }

        .admin-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #eef2f7;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            font-weight: 600;
            color: #374151;
        }

        .admin-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .admin-initials {
            font-size: 14px;
            line-height: 1;
            display: none;
        }

        .admin-avatar.initials .admin-initials {
            display: inline-block;
        }

        .admin-meta {
            display: flex;
            flex-direction: column;
            line-height: 1;
        }

        .admin-name {
            font-size: 13px;
            color: #111827;
            font-weight: 600;
        }

        .content-wrapper {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
        }

        .settings-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .settings-header {
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 15px;
        }

        .settings-header h1 {
            color: #333;
            font-size: 28px;
            margin: 0;
        }

        .back-link {
            display: none;
        }

        .settings-section {
            margin-bottom: 35px;
            padding: 20px;
            background: #f9f9f9;
            border-left: 4px solid #007bff;
            border-radius: 4px;
        }

        .settings-section h2 {
            color: #007bff;
            font-size: 18px;
            margin-top: 0;
            margin-bottom: 20px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input[type="number"],
        .form-group input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }

        .form-group input[type="number"]:focus,
        .form-group input[type="text"]:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.2);
        }

        .form-group .example-text {
            color: #999;
            font-size: 12px;
            margin-top: 5px;
            font-style: italic;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            margin-right: 10px;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            color: #333;
            font-weight: 400;
            font-size: 14px;
        }

        .toggle-switch {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #28a745;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .status-text {
            font-size: 12px;
            color: #666;
            margin-left: 10px;
        }

        .status-text.on {
            color: #28a745;
            font-weight: 600;
        }

        .status-text.off {
            color: #dc3545;
            font-weight: 600;
        }

        .form-actions {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .btn {
            padding: 12px 30px;
            font-size: 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }

            .sidebar {
                position: fixed;
                left: -280px;
                top: 0;
                height: 100vh;
                transition: left 0.3s;
                z-index: 1000;
            }

            .sidebar.active {
                left: 0;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
                height: auto;
                min-height: 100vh;
            }

            .settings-container {
                margin: 0;
                padding: 20px;
            }

            .settings-header h1 {
                font-size: 22px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
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
            <a href="staff_patient_records.php" class="nav-item">
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
            <a href="settings.php" class="nav-item active">
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="m9.25 22l-.4-3.2q-.325-.125-.612-.3t-.563-.375L4.7 19.375l-2.75-4.75l2.575-1.95Q4.5 12.5 4.5 12.338v-.675q0-.163.025-.338L1.95 9.375l2.75-4.75l2.975 1.25q.275-.2.575-.375t.6-.3l.4-3.2h5.5l.4 3.2q.325.125.613.3t.562.375l2.975-1.25l2.75 4.75l-2.575 1.95q.025.175.025.338v.675q0 .163-.025.338l2.575 1.95l-2.75 4.75l-2.975-1.25q-.275.2-.562.375t-.613.3l-.4 3.2zm2.75-3h1l.35-2.65q.55-.125 1.012-.387t.863-.65l2.5 1.05l.5-.85l-2.075-1.575q.125-.35.2-.738t.075-.762q0-.375-.075-.75t-.2-.7l2.075-1.575l-.5-.85l-2.5 1.05q-.4-.425-.863-.688t-1.012-.387l-.35-2.65h-1l-.35 2.65q-.55.125-1.012.387t-.863.65l-2.5-1.05l-.5.85l2.075 1.575q-.125.325-.2.712t-.075.763q0 .375.075.75t.2.725l-2.075 1.575l.5.85l2.5-1.05q.4.425.863.688t1.012.387z"/></svg></span>
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
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <h2 class="header-title">⚙️ Queue Settings</h2>
            </div>
            <div class="header-right">
                <div class="user-profile">
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 32 32'%3E%3Ccircle cx='16' cy='16' r='16' fill='%23e5e7eb'/%3E%3Ctext x='16' y='22' font-family='Arial' font-size='18' fill='%236b7280' text-anchor='middle'%3E%F0%9F%91%A4%3C/text%3E%3C/svg%3E" alt="User">
                    <span class="user-name"><?php echo htmlspecialchars($fullName); ?></span>
                </div>
            </div>
        </header>

        <!-- Scrollable Content Area -->
        <div class="content-wrapper">

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                ✓ <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                ✗ <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="settings-container">
            <div class="settings-header">
                <h1>Queue Settings</h1>
            </div>

        <form method="POST" action="">
            <!-- Service Time Settings -->
            <div class="settings-section">
                <h2>Service Time Configuration</h2>

                <div class="form-group">
                    <label for="avg_service_time">Average Service Time per Patient</label>
                    <input type="number" id="avg_service_time" name="avg_service_time" 
                           value="<?php echo $settings['avg_service_time']; ?>" 
                           min="1" required>
                    <div class="example-text">Example: 30 minutes</div>
                </div>

                <div class="form-group">
                    <label for="max_patients_per_day">Maximum Patients per Day</label>
                    <input type="number" id="max_patients_per_day" name="max_patients_per_day" 
                           value="<?php echo $settings['max_patients_per_day']; ?>" 
                           min="1" required>
                    <div class="example-text">Example: 40</div>
                </div>
            </div>

            <!-- Auto Queue Status Update -->
            <div class="settings-section">
                <h2>Auto Queue Status Update</h2>

                <div class="checkbox-group">
                    <input type="checkbox" id="auto_waiting_to_serving" name="auto_waiting_to_serving" 
                           value="1" <?php echo $settings['auto_waiting_to_serving'] ? 'checked' : ''; ?>>
                    <label for="auto_waiting_to_serving">☑ Auto move Waiting → Now Serving</label>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="auto_serving_to_completed" name="auto_serving_to_completed" 
                           value="1" <?php echo $settings['auto_serving_to_completed'] ? 'checked' : ''; ?>>
                    <label for="auto_serving_to_completed">☑ Auto move Now Serving → Completed</label>
                </div>
            </div>

            <!-- Walk-In Patients -->
            <div class="settings-section">
                <h2>Walk-In Patients</h2>

                <div class="toggle-switch">
                    <label class="switch">
                        <input type="checkbox" id="enable_walkin" name="enable_walkin" 
                               value="1" <?php echo $settings['enable_walkin'] ? 'checked' : ''; ?>
                               onchange="updateWalkInStatus()">
                        <span class="slider"></span>
                    </label>
                    <span class="status-text" id="walkin-status" 
                          style="<?php echo $settings['enable_walkin'] ? 'color: #28a745;' : 'color: #dc3545;'; ?>">
                        <?php echo $settings['enable_walkin'] ? 'ON' : 'OFF'; ?>
                    </span>
                </div>
                <p style="color: #666; font-size: 13px; margin-top: 10px;">
                    Toggle ON / OFF to enable or disable walk-in patient registration
                </p>
            </div>

            <!-- Priority Patients -->
            <div class="settings-section">
                <h2>Priority Patients</h2>

                <div class="checkbox-group">
                    <input type="checkbox" id="priority_senior" name="priority_senior" 
                           value="1" <?php echo $settings['priority_senior'] ? 'checked' : ''; ?>>
                    <label for="priority_senior">☑ Senior Citizen</label>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="priority_pwd" name="priority_pwd" 
                           value="1" <?php echo $settings['priority_pwd'] ? 'checked' : ''; ?>>
                    <label for="priority_pwd">☑ PWD (Person with Disability)</label>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="priority_emergency" name="priority_emergency" 
                           value="1" <?php echo $settings['priority_emergency'] ? 'checked' : ''; ?>>
                    <label for="priority_emergency">☑ Emergency</label>
                </div>
            </div>

            <!-- Queue Number Format -->
            <div class="settings-section">
                <h2>Queue Number Format</h2>

                <div class="form-group">
                    <label for="queue_format">Queue Number Format</label>
                    <input type="text" id="queue_format" name="queue_format" 
                           value="<?php echo htmlspecialchars($settings['queue_format']); ?>" 
                           placeholder="Q-###" required>
                    <div class="example-text">
                        Example: Q-001 (Use # for auto-increment numbers)
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">💾 Save Queue Settings</button>
                <button type="reset" class="btn btn-secondary">↻ Reset</button>
            </div>
        </form>
        </div>
    </div>

    <script>
        function updateWalkInStatus() {
            const toggle = document.getElementById('enable_walkin');
            const status = document.getElementById('walkin-status');
            
            if (toggle.checked) {
                status.textContent = 'ON';
                status.className = 'status-text on';
                status.style.color = '#28a745';
            } else {
                status.textContent = 'OFF';
                status.className = 'status-text off';
                status.style.color = '#dc3545';
            }
        }

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const avgTime = parseInt(document.getElementById('avg_service_time').value);
            const maxPatients = parseInt(document.getElementById('max_patients_per_day').value);

            if (avgTime < 1 || isNaN(avgTime)) {
                e.preventDefault();
                alert('Average service time must be at least 1 minute');
                return false;
            }

            if (maxPatients < 1 || isNaN(maxPatients)) {
                e.preventDefault();
                alert('Maximum patients per day must be at least 1');
                return false;
            }
        });
    </script>
</body>
</html>
