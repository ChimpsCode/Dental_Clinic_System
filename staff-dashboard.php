<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - RF Dental Clinic</title>
    <style>
        /* CSS Variables for consistent theming */
        :root {
            --primary-color: #0ea5e9; /* Light Blue */
            --primary-dark: #0284c7;
            --sidebar-bg: #FFFFFF; /* White */
            --sidebar-text: #000000;
            --sidebar-active-bg: #2563eb;
            --sidebar-active-text: #ffffff;
            --bg-body: #f3f4f6;
            --bg-card: #ffffff;
            --text-main: #111827;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            
            /* Status Colors */
            --status-waiting-bg: #e0f2fe;
            --status-waiting-text: #0369a1;
            --status-serving-bg: #dcfce7;
            --status-serving-text: #15803d;
            --status-cancelled-bg: #fee2e2;
            --status-cancelled-text: #b91c1c;
            
            /* Card Colors */
            --card-waiting: #f59e0b;
            --card-completed: #22c55e;
            --card-cancelled: #ef4444;
            --card-skipped: #9ca3af;
        }

        /* Reset & Base Styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* Sidebar Styles */
        aside.sidebar {
            width: 260px;
            background-color: var(--sidebar-bg);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            flex-shrink: 0;
            transition: width 0.3s ease;
        }

        .sidebar-logo {
            padding: 25px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #000000;
            font-size: 1.125rem;
            font-weight: 600;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-logo img {
            width: 40px;
            height: 40px;
            border-radius: 8px;
        }

        .sidebar-nav {
            padding: 20px 0;
            flex: 1;
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

        .nav-item svg {
            width: 20px;
            height: 20px;
        }

        .sidebar-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        /* Main Content Styles */
        main.main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Header */
        header.top-header {
            background-color: var(--bg-card);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            height: 80px;
        }

        .header-title h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .header-title p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 6px 12px;
            
        }

        .user-profile img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-name {
            font-weight: 500;
            font-size: 0.95rem;
        }

        /* Dashboard Scroll Area */
        .content-area {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
        }

        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }

        .summary-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .summary-icon.yellow { background: #fef3c7; color: var(--card-waiting); }
        .summary-icon.green { background: #dcfce7; color: var(--card-completed); }
        .summary-icon.red { background: #fee2e2; color: var(--card-cancelled); }
        .summary-icon.gray { background: #f3f4f6; color: var(--card-skipped); }

        .summary-info h3 {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1;
        }

        .summary-info p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        /* Two Column Grid */
        .two-column {
            display: grid;
            grid-template-columns: 2.5fr 1fr; /* Left wider than right */
            gap: 24px;
        }

        /* Sections (Left Column) */
        .section-card, .notification-box {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Patient List Items */
        .patient-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .patient-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            transition: background 0.2s;
        }

        .patient-item:hover {
            background-color: #f9fafb;
        }

        .patient-name {
            font-weight: 600;
            font-size: 1rem;
        }

        .patient-details {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 4px;
            flex-wrap: wrap;
        }

        .patient-treatment {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        /* Status Badges */
        .status-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.now-serving, .status-badge.in-chair {
            background-color: var(--status-serving-bg);
            color: var(--status-serving-text);
        }

        .status-badge.waiting {
            background-color: var(--status-waiting-bg);
            color: var(--status-waiting-text);
        }

        .status-badge.cancelled {
            background-color: var(--status-cancelled-bg);
            color: var(--status-cancelled-text);
        }

        .patient-time {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .patient-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            background: transparent;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 6px 10px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-btn:hover {
            background-color: #f3f4f6;
            border-color: #d1d5db;
        }

        /* Live Queue Controller */
        .live-queue {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid #bae6fd;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .live-queue-header {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }

        .live-queue-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #0c4a6e;
        }

        .now-serving-badge {
            background: #0ea5e9;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(14, 165, 233, 0.4); }
            70% { box-shadow: 0 0 0 6px rgba(14, 165, 233, 0); }
            100% { box-shadow: 0 0 0 0 rgba(14, 165, 233, 0); }
        }

        .live-patient {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .live-patient .patient-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .live-patient .patient-treatment {
            font-size: 1.1rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .complete-btn {
            background-color: var(--card-completed);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin: 0 auto;
            width: 100%;
            max-width: 300px;
            transition: transform 0.1s, background-color 0.2s;
        }

        .complete-btn:hover {
            background-color: #16a34a;
            transform: translateY(-1px);
        }

        .complete-btn:active {
            transform: translateY(0);
        }

        .complete-btn-text {
            margin-top: 8px;
            font-size: 0.85rem;
            color: #64748b;
        }

        /* Right Column */
        .notification-box h3 {
            font-size: 1rem;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .notification-item {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 8px;
        }

        .notification-title {
            font-weight: 600;
            color: #92400e;
            font-size: 0.95rem;
        }

        .notification-detail {
            font-size: 0.85rem;
            color: #b45309;
            margin-top: 4px;
        }

        .reminder-list {
            list-style: none;
            margin-bottom: 12px;
        }

        .reminder-list li {
            position: relative;
            padding-left: 20px;
            margin-bottom: 8px;
            font-size: 0.9rem;
            color: var(--text-main);
        }

        .reminder-list li::before {
            content: "•";
            color: var(--primary-color);
            position: absolute;
            left: 0;
            font-weight: bold;
        }

        .add-reminder-btn {
            background: transparent;
            border: 1px dashed var(--primary-color);
            color: var(--primary-color);
            width: 100%;
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.2s;
        }

        .add-reminder-btn:hover {
            background: #f0f9ff;
        }

        .see-all-link {
            display: block;
            text-align: center;
            margin-top: 12px;
            font-size: 0.85rem;
            color: var(--text-secondary);
            text-decoration: none;
        }

        .see-all-link:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .two-column {
                grid-template-columns: 1fr;
            }
            .summary-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            aside.sidebar {
                width: 70px;
            }
            .sidebar-logo span, .nav-item span, .sidebar-footer span {
                display: none;
            }
            .nav-item {
                justify-content: center;
                padding: 12px;
            }
            .summary-cards {
                grid-template-columns: 1fr;
            }
            header.top-header {
                padding: 16px;
            }
            .content-area {
                padding: 16px;
            }
        }
    </style>
</head>
<body>

    <!-- Left Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <!-- Placeholder for Logo -->
            <img src="assets/images/Logo.png" alt="RF Logo">
            <span>RF Dental</span>
        </div>
        
        <nav class="sidebar-nav">
            <a href="#" class="nav-item active">
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M3 12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1zm0 8a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1zm10 0a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-6a1 1 0 0 0-1 1zm1-17a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1z"/></svg></span>
                <span>Dashboard</span>
            </a>
            <a href="#" class="nav-item">
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
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 32 32'%3E%3Ccircle cx='16' cy='16' r='16' fill='%23e5e7eb'/%3E%3Ctext x='16' y='22' font-family='Arial' font-size='18' fill='%236b7280' text-anchor='middle'%3E👤%3C/text%3E%3C/svg%3E" alt="User">
                    <span class="user-name">Staff </span>

                </div>
            </div>
        </header>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-icon yellow">⏰</div>
                    <div class="summary-info">
                        <h3 id="count-waiting">3</h3>
                        <p>Waiting</p>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon green">✓</div>
                    <div class="summary-info">
                        <h3 id="count-completed">0</h3>
                        <p>Completed</p>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon red">⚠️</div>
                    <div class="summary-info">
                        <h3 id="count-cancelled">1</h3>
                        <p>Cancelled</p>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon gray">⊘</div>
                    <div class="summary-info">
                        <h3 id="count-skipped">0</h3>
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
                        <div class="patient-list" id="schedule-list">
                            <!-- PHP: Maria Santos -->
                            <div class="patient-item">
                                <div class="patient-info">
                                    <div class="patient-name">Maria Santos</div>
                                    <div class="patient-details">
                                        <span class="status-badge now-serving">NOW SERVING</span>
                                        <span class="patient-time">09:30 AM</span>
                                    </div>
                                    <div class="patient-treatment">Root Canal (Session 2)</div>
                                    <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">Source: Phone Call</div>
                                </div>
                                <div class="patient-actions">
                                    <button class="action-btn icon view-btn" title="View">👁️</button>
                                </div>
                            </div>
                            
                            <!-- PHP: Juan Dela Cruz -->
                            <div class="patient-item">
                                <div class="patient-info">
                                    <div class="patient-name">Juan Dela Cruz</div>
                                    <div class="patient-details">
                                        <span class="status-badge waiting">Waiting</span>
                                        <span class="patient-time">09:00 AM</span>
                                    </div>
                                    <div class="patient-treatment">Root Canal (Session 2)</div>
                                    <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">Source: Walk-in</div>
                                </div>
                                <div class="patient-actions">
                                    <button class="action-btn icon view-btn" title="View">👁️</button>
                                </div>
                            </div>

                            <!-- PHP: Ana Reyes -->
                            <div class="patient-item">
                                <div class="patient-info">
                                    <div class="patient-name">Ana Reyes</div>
                                    <div class="patient-details">
                                        <span class="status-badge waiting">Waiting</span>
                                        <span class="patient-time">10:00 AM</span>
                                    </div>
                                    <div class="patient-treatment">Oral Prophylaxis</div>
                                    <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">Source: Walk-in</div>
                                </div>
                                <div class="patient-actions">
                                    <button class="action-btn icon view-btn" title="View">👁️</button>
                                </div>
                            </div>

                            <!-- PHP: Roberto Garcia (Moved to cancelled later in logic, but shown here for context if needed) -->
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
                            <div class="patient-name" id="live-patient-name">Maria Santos</div>
                            <div class="patient-details" style="margin-top: 10px;">
                                <span class="status-badge in-chair">In Chair</span>
                                <span class="patient-time" id="live-patient-time">09:30 AM</span>
                            </div>
                            <div class="patient-treatment" id="live-patient-treatment" style="margin-top: 8px;">Root Canal (Session 2)</div>
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
                        <div class="patient-list" id="up-next-list">
                            <!-- PHP: Juan Dela Cruz -->
                            <div class="patient-item" id="next-juan">
                                <div class="patient-info">
                                    <div class="patient-name">Juan Dela Cruz</div>
                                    <div class="patient-details">
                                        <span class="status-badge waiting">Waiting</span>
                                        <span class="patient-time">09:00 AM</span>
                                    </div>
                                    <div class="patient-treatment">Root Canal (Session 2)</div>
                                </div>
                                <div class="patient-actions">
                                    <button class="action-btn icon view-btn" title="View">👁️</button>
                                    <button class="action-btn icon delete-btn" title="Call Patient" onclick="this.innerHTML = 'Calling...'; setTimeout(() => this.innerHTML = '📞', 2000)">📞</button>
                                    <button class="action-btn icon delete-btn" title="Not Present" onclick="moveToCancelled(this)">⚠️</button>
                                </div>
                            </div>
                            
                            <!-- PHP: Ana Reyes -->
                            <div class="patient-item" id="next-ana">
                                <div class="patient-info">
                                    <div class="patient-name">Ana Reyes</div>
                                    <div class="patient-details">
                                        <span class="status-badge waiting">Waiting</span>
                                        <span class="patient-time">10:30 AM</span>
                                    </div>
                                    <div class="patient-treatment">Oral Prophylaxis</div>
                                </div>
                                <div class="patient-actions">
                                    <button class="action-btn icon view-btn" title="View">👁️</button>
                                    <button class="action-btn icon delete-btn" title="Call Patient" onclick="this.innerHTML = 'Calling...'; setTimeout(() => this.innerHTML = '📞', 2000)">📞</button>
                                    <button class="action-btn icon delete-btn" title="Not Present" onclick="moveToCancelled(this)">⚠️</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cancelled -->
                    <div class="section-card">
                        <h2 class="section-title">Cancelled</h2>
                        <div class="patient-list" id="cancelled-list">
                            <!-- PHP: Roberto Garcia -->
                            <div class="patient-item" id="cancelled-roberto">
                                <div class="patient-info">
                                    <div class="patient-name">Roberto Garcia</div>
                                    <div class="patient-details">
                                        <span class="status-badge cancelled">Cancelled</span>
                                    </div>
                                    <div class="patient-treatment">Denture Adjustment</div>
                                    <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">No show / Did not arrive</div>
                                </div>
                                <div class="patient-actions">
                                    <button class="action-btn icon view-btn" title="View">👁️</button>
                                    <button class="action-btn" style="background: #84cc16; color: white; padding: 8px 12px; border-radius: 6px; border: none; font-size: 12px; cursor: pointer;" onclick="requeuePatient('cancelled-roberto', 'Roberto Garcia', 'Denture Adjustment', '11:00 AM')">Re-queue</button>
                                    <button class="action-btn icon delete-btn" title="Delete">🗑️</button>
                                </div>
                            </div>
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
                        <ul class="reminder-list" id="reminder-list">
                            <li>Reply to Facebook inquiries from last night</li>
                            <li>Print consent form for tomorrow</li>
                            <li>Call inquiries for new stocks of Composites</li>
                        </ul>
                        <button class="add-reminder-btn" id="addReminderBtn">+ Add New Reminder</button>
                        <a href="#" class="see-all-link">See all reminders</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // DOM Elements
        const countWaiting = document.getElementById('count-waiting');
        const countCompleted = document.getElementById('count-completed');
        const countCancelled = document.getElementById('count-cancelled');
        
        const liveName = document.getElementById('live-patient-name');
        const liveTime = document.getElementById('live-patient-time');
        const liveTreatment = document.getElementById('live-patient-treatment');
        const completeBtn = document.getElementById('completeTreatmentBtn');
        
        const upNextList = document.getElementById('up-next-list');
        const cancelledList = document.getElementById('cancelled-list');
        const reminderList = document.getElementById('reminder-list');
        const addReminderBtn = document.getElementById('addReminderBtn');

        // State
        let completedCount = 0;

        // Function: Complete Treatment
        completeBtn.addEventListener('click', () => {
            // 1. Update Stats
            completedCount++;
            countCompleted.innerText = completedCount;
            
            // 2. Animation feedback
            completeBtn.innerText = "✓ Treatment Completed";
            completeBtn.style.backgroundColor = "#15803d";
            
            setTimeout(() => {
                // Reset button text
                completeBtn.innerHTML = "<span>✓</span><span>Complete Treatment</span>";
                completeBtn.style.backgroundColor = "";

                // 3. Logic: Check if there is someone "Up Next"
                const nextPatientItem = upNextList.firstElementChild;
                
                if (nextPatientItem) {
                    // Get data from the next patient
                    const name = nextPatientItem.querySelector('.patient-name').innerText;
                    const time = nextPatientItem.querySelector('.patient-time').innerText;
                    const treatment = nextPatientItem.querySelector('.patient-treatment').innerText;

                    // Update Live Queue
                    liveName.innerText = name;
                    liveTime.innerText = time;
                    liveTreatment.innerText = treatment;

                    // Remove from Up Next
                    nextPatientItem.remove();

                    // Update Waiting Count (decrease by 1)
                    let currentWaiting = parseInt(countWaiting.innerText);
                    if(currentWaiting > 0) countWaiting.innerText = currentWaiting - 1;
                } else {
                    // No more patients
                    liveName.innerText = "No Patients";
                    liveTime.innerText = "--:--";
                    liveTreatment.innerText = "Queue Empty";
                }

            }, 1000);
        });

        // Function: Add Reminder
        addReminderBtn.addEventListener('click', () => {
            const reminders = ["Check inventory", "Verify insurance claims", "Sterilize tools", "Update patient records", "Lunch break"];
            const randomReminder = reminders[Math.floor(Math.random() * reminders.length)];
            
            const li = document.createElement('li');
            li.innerText = randomReminder;
            // Highlight new item
            li.style.color = "#0ea5e9";
            li.style.fontWeight = "bold";
            
            reminderList.appendChild(li);
            
            // Remove highlight after a few seconds
            setTimeout(() => {
                li.style.color = "";
                li.style.fontWeight = "";
            }, 2000);
        });

        // Function: Move to Cancelled (from Up Next)
        window.moveToCancelled = function(btn) {
            const patientItem = btn.closest('.patient-item');
            const name = patientItem.querySelector('.patient-name').innerText;
            const treatment = patientItem.querySelector('.patient-treatment').innerText;

            // Create Cancelled Item structure
            const cancelledItem = document.createElement('div');
            cancelledItem.className = 'patient-item';
            cancelledItem.innerHTML = `
                <div class="patient-info">
                    <div class="patient-name" style="text-decoration: line-through; color: #9ca3af;">${name}</div>
                    <div class="patient-details">
                        <span class="status-badge cancelled">Cancelled</span>
                    </div>
                    <div class="patient-treatment">${treatment}</div>
                    <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">Not Present / Cancelled by Staff</div>
                </div>
                <div class="patient-actions">
                    <button class="action-btn icon view-btn" title="View">👁️</button>
                    <button class="action-btn" style="background: #84cc16; color: white; padding: 8px 12px; border-radius: 6px; border: none; font-size: 12px; cursor: pointer;" onclick="requeueNew(this, '${name}', '${treatment}')">Re-queue</button>
                    <button class="action-btn icon delete-btn" title="Delete">🗑️</button>
                </div>
            `;

            cancelledList.appendChild(cancelledItem);
            patientItem.remove();

            // Update Counts
            let currentCancelled = parseInt(countCancelled.innerText);
            countCancelled.innerText = currentCancelled + 1;
            
            let currentWaiting = parseInt(countWaiting.innerText);
            if(currentWaiting > 0) countWaiting.innerText = currentWaiting - 1;
        };

        // Function: Re-queue (Hardcoded ID version for Roberto)
        window.requeuePatient = function(id, name, treatment, time) {
            const item = document.getElementById(id);
            if(item) {
                createWaitingItem(name, treatment, time);
                item.remove();
                
                // Update Counts
                let currentCancelled = parseInt(countCancelled.innerText);
                if(currentCancelled > 0) countCancelled.innerText = currentCancelled - 1;
                
                let currentWaiting = parseInt(countWaiting.innerText);
                countWaiting.innerText = currentWaiting + 1;
            }
        };

        // Function: Re-queue (Dynamic version for newly cancelled items)
        window.requeueNew = function(btn, name, treatment) {
            const item = btn.closest('.patient-item');
            createWaitingItem(name, treatment, "Re-queued");
            item.remove();

             // Update Counts
             let currentCancelled = parseInt(countCancelled.innerText);
            if(currentCancelled > 0) countCancelled.innerText = currentCancelled - 1;
            
            let currentWaiting = parseInt(countWaiting.innerText);
            countWaiting.innerText = currentWaiting + 1;
        };

        function createWaitingItem(name, treatment, time) {
            const newItem = document.createElement('div');
            newItem.className = 'patient-item';
            newItem.innerHTML = `
                <div class="patient-info">
                    <div class="patient-name">${name}</div>
                    <div class="patient-details">
                        <span class="status-badge waiting">Waiting</span>
                        <span class="patient-time">${time}</span>
                    </div>
                    <div class="patient-treatment">${treatment}</div>
                </div>
                <div class="patient-actions">
                    <button class="action-btn icon view-btn" title="View">👁️</button>
                    <button class="action-btn icon delete-btn" title="Call Patient" onclick="this.innerHTML = 'Calling...'; setTimeout(() => this.innerHTML = '📞', 2000)">📞</button>
                    <button class="action-btn icon delete-btn" title="Not Present" onclick="moveToCancelled(this)">⚠️</button>
                </div>
            `;
            upNextList.appendChild(newItem);
        }
    </script>
</body>
</html>