<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$fullName = $_SESSION['full_name'] ?? 'Dr. Rex';

$patientId = $_GET['id'] ?? 1;

$patient = [
    'id' => 1,
    'name' => 'Maria Santos',
    'age' => 35,
    'phone' => '+63 912 345 6789',
    'email' => 'maria.santos@email.com',
    'first_visit' => '2023-03-15',
    'total_visits' => 12,
    'total_paid' => '₱15,000',
    'status' => 'Active'
];

$treatments = [
    [
        'id' => 1,
        'date' => '2024-01-15',
        'procedure' => 'Root Canal (Session 2)',
        'tooth' => '#18',
        'status' => 'Done',
        'doctor' => 'Dr. Rex'
    ],
    [
        'id' => 2,
        'date' => '2023-12-20',
        'procedure' => 'Tooth Extraction',
        'tooth' => '#16',
        'status' => 'Done',
        'doctor' => 'Dr. Rex'
    ],
    [
        'id' => 3,
        'date' => '2023-11-10',
        'procedure' => 'Tooth Filling',
        'tooth' => '#14',
        'status' => 'Done',
        'doctor' => 'Dr. Rex'
    ],
    [
        'id' => 4,
        'date' => '2023-10-05',
        'procedure' => 'Dental Cleaning',
        'tooth' => 'N/A',
        'status' => 'Ongoing',
        'doctor' => 'Dr. Rex'
    ]
];

$services = [
    ['name' => 'Tooth Extraction', 'date' => '2023-12-20'],
    ['name' => 'Root Canal', 'date' => '2024-01-15'],
    ['name' => 'Tooth Filling', 'date' => '2023-11-10'],
    ['name' => 'Dental Cleaning', 'date' => '2023-10-05']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details - RF Dental Clinic</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/patient-details.css">
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
            <a href="#" class="nav-item">
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3m1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/></svg></span>
                <span>Appointments</span>
            </a>
            <a href="#" class="nav-item">
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M3 22V8h4v14zm7 0V2h4v20zm7 0v-8h4v8z"/></svg></span>
                <span>Analytics</span>
            </a>
            <a href="#" class="nav-item">
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
                <button class="menu-toggle" id="menuToggle">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
                    </svg>
                </button>
                <h1 class="page-title">Patient Details</h1>
            </div>
            
            <div class="header-right">
                <div class="user-profile">
                    <span class="user-name"><?php echo htmlspecialchars($fullName); ?></span>
                    <div class="user-avatar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM21 9V7L15 1H5C3.89 1 3 1.89 3 3V21C3 22.11 3.89 23 5 23H19C20.11 23 21 22.11 21 21V9M19 9H14V4H19V9Z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <div class="page-content">
            <!-- Back Button -->
            <div class="back-button-container">
                <a href="patient-records.php" class="back-button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                    </svg>
                    Back to Patient List
                </a>
            </div>

            <!-- Patient Header Card -->
            <div class="patient-header-card">
                <div class="patient-info-left">
                    <h2 class="patient-name"><?php echo htmlspecialchars($patient['name']); ?></h2>
                    
                    <div class="patient-details">
                        <div class="detail-item">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                            <span class="detail-label">Age:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($patient['age']); ?></span>
                        </div>
                        <div class="detail-item">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.27.45.52.59.52.59.26 0 3.09-.58 5.24-2.42l2.97-3.21c.76-2.09-.03-4.66-1.77-6.29l-3.66-3.66c-1.63-1.74-4.2-2.53-6.29-1.77L14 6.55c-1.84-2.15-4.15-3.47-6.98-4.91L4.21 4.21c-1.63-1.63-1.63-4.28 0-5.91l3.66-3.66c1.63-1.63 4.28-1.63 5.91 0l2.83-2.83c1.63-1.63 1.63-4.28 0-5.91z"/>
                            </svg>
                            <span class="detail-label">Phone:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($patient['phone']); ?></span>
                        </div>
                        <div class="detail-item">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                            </svg>
                            <span class="detail-label">Email:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($patient['email']); ?></span>
                        </div>
                    </div>
                    
                    <div class="tab-buttons">
                        <button class="tab-btn active">Dental Record & History yyy</button>
                        <button class="tab-btn">Patient Information</button>
                    </div>
                </div>
                
                <div class="patient-action-right">
                    <button class="btn-view-full">View Full Details</button>
                </div>
            </div>

            <!-- Summary Section -->
            <div class="summary-section">
                <div class="stat-cards">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm-3-8V7h4v2h-4z"/>
                            </svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo htmlspecialchars($patient['total_visits']); ?></div>
                            <div class="stat-label">Total Visits</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                            </svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo htmlspecialchars($patient['first_visit']); ?></div>
                            <div class="stat-label">First Visit Date</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M11.8 10.9c-2.27-.59-3.65-2.26-3.65-4.8V4.5l.45-.22 2.08-1.07-1.07-2.08.45-.22V6.1c0-2.74 2.24-4.97 4.97-4.97h2.1c2.73 0 4.97 2.23 4.97 4.97v1.94l1.07 2.08-.45.22-1.07-1.07-.45-.22v.2c0 2.54-1.38 4.21-3.65 4.8zM8.03 2c1.87 0 3.4.82 3.53 2.12.3 1.31 1.44 2.27 2.76 2.78v.95c-2.5.52-4.32 2.8-4.32 5.2v3.1h3.1V13h-3.1v-3.1c0-2.4 1.82-4.68 4.32-5.2v-.95c1.32-.51 2.47-1.47 2.76-2.78.13-1.3 1.66-2.12 3.53-2.12z"/>
                            </svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo htmlspecialchars($patient['total_paid']); ?></div>
                            <div class="stat-label">Total Paid</div>
                        </div>
                    </div>
                </div>

                <div class="services-list">
                    <h3 class="list-title">All Services Performed</h3>
                    <div class="services">
                        <?php foreach ($services as $service): ?>
                        <div class="service-item">
                            <span class="service-name"><?php echo htmlspecialchars($service['name']); ?></span>
                            <span class="service-date"><?php echo htmlspecialchars($service['date']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="content-grid">
                <!-- Timeline Column -->
                <div class="timeline-column">
                    <h3 class="section-title">Timeline View</h3>
                    <div class="timeline">
                        <?php foreach ($treatments as $treatment): ?>
                        <div class="timeline-item">
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <span class="timeline-status <?php echo $treatment['status'] === 'Done' ? 'status-done' : 'status-ongoing'; ?>">
                                        <?php echo htmlspecialchars($treatment['status']); ?>
                                    </span>
                                    <span class="timeline-date"><?php echo htmlspecialchars($treatment['date']); ?></span>
                                </div>
                                <h4 class="timeline-procedure"><?php echo htmlspecialchars($treatment['procedure']); ?></h4>
                                <div class="timeline-details">
                                    <span class="tooth-number"><?php echo htmlspecialchars($treatment['tooth']); ?></span>
                                    <span class="doctor-name"><?php echo htmlspecialchars($treatment['doctor']); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Dental Chart Column -->
                <div class="dental-chart-column">
                    <h3 class="section-title">Dental Chart (Odontogram)</h3>
                    
                    <div class="dental-chart">
                        <div class="chart-controls">
                            <button class="toggle-btn active" id="adultToggle">Adult Teeth (32)</button>
                            <button class="toggle-btn" id="childToggle">Child Teeth (20)</button>
                        </div>
                        
                        <div class="teeth-container" id="teethContainer">
                            <div class="jaw-label">Upper Jaw (Maxilla)</div>
                            <div class="jaw upper-jaw" id="upperJaw">
                                <div class="tooth" data-tooth="18">18</div>
                                <div class="tooth" data-tooth="17">17</div>
                                <div class="tooth" data-tooth="16">16</div>
                                <div class="tooth" data-tooth="15">15</div>
                                <div class="tooth" data-tooth="14">14</div>
                                <div class="tooth" data-tooth="13">13</div>
                                <div class="tooth" data-tooth="12">12</div>
                                <div class="tooth" data-tooth="11">11</div>
                                <div class="tooth" data-tooth="21">21</div>
                                <div class="tooth" data-tooth="22">22</div>
                                <div class="tooth" data-tooth="23">23</div>
                                <div class="tooth" data-tooth="24">24</div>
                                <div class="tooth" data-tooth="25">25</div>
                                <div class="tooth" data-tooth="26">26</div>
                                <div class="tooth" data-tooth="27">27</div>
                                <div class="tooth" data-tooth="28">28</div>
                            </div>
                            
                            <div class="jaw-label">Lower Jaw (Mandible)</div>
                            <div class="jaw lower-jaw" id="lowerJaw">
                                <div class="tooth" data-tooth="48">48</div>
                                <div class="tooth" data-tooth="47">47</div>
                                <div class="tooth" data-tooth="46">46</div>
                                <div class="tooth" data-tooth="45">45</div>
                                <div class="tooth" data-tooth="44">44</div>
                                <div class="tooth" data-tooth="43">43</div>
                                <div class="tooth" data-tooth="42">42</div>
                                <div class="tooth" data-tooth="41">41</div>
                                <div class="tooth" data-tooth="31">31</div>
                                <div class="tooth" data-tooth="32">32</div>
                                <div class="tooth" data-tooth="33">33</div>
                                <div class="tooth" data-tooth="34">34</div>
                                <div class="tooth" data-tooth="35">35</div>
                                <div class="tooth" data-tooth="36">36</div>
                                <div class="tooth" data-tooth="37">37</div>
                                <div class="tooth" data-tooth="38">38</div>
                            </div>
                        </div>
                        
                        <div class="chart-legend">
                            <h3>Professional Dental Chart Reference</h3>
                            <div class="legend-grid">
                                <div class="legend-section">
                                    <h4>Condition</h4>
                                    <div class="legend-items">
                                        <div class="legend-item">
                                            <span class="legend-color" style="background: #10b981;"></span>
                                            <span class="legend-label">Present</span>
                                        </div>
                                        <div class="legend-item">
                                            <span class="legend-color" style="background: #ef4444;"></span>
                                            <span class="legend-label">Decayed</span>
                                        </div>
                                        <div class="legend-item">
                                            <span class="legend-color" style="background: #6b7280;"></span>
                                            <span class="legend-label">Missing</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="legend-section">
                                    <h4>Restorations</h4>
                                    <div class="legend-items">
                                        <div class="legend-item">
                                            <span class="legend-color" style="background: #3b82f6;"></span>
                                            <span class="legend-label">Amalgam</span>
                                        </div>
                                        <div class="legend-item">
                                            <span class="legend-color" style="background: #8b5cf6;"></span>
                                            <span class="legend-label">Composite</span>
                                        </div>
                                        <div class="legend-item">
                                            <span class="legend-color" style="background: #f59e0b;"></span>
                                            <span class="legend-label">Jacket Crown</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="legend-section">
                                    <h4>Surgery</h4>
                                    <div class="legend-items">
                                        <div class="legend-item">
                                            <span class="legend-color" style="background: #06b6d4;"></span>
                                            <span class="legend-label">Extraction</span>
                                        </div>
                                        <div class="legend-item">
                                            <span class="legend-color" style="background: #14b8a6;"></span>
                                            <span class="legend-label">Root Canal</span>
                                        </div>
                                        <div class="legend-item">
                                            <span class="legend-color" style="background: #a855f7;"></span>
                                            <span class="legend-label">Implant</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="legend-section">
                                    <h4>Periodontal Screening</h4>
                                    <div class="legend-items">
                                        <div class="legend-item">
                                            <span class="legend-color" style="background: #22c55e;"></span>
                                            <span class="legend-label">Healthy</span>
                                        </div>
                                        <div class="legend-item">
                                            <span class="legend-color" style="background: #f97316;"></span>
                                            <span class="legend-label">Gingivitis</span>
                                        </div>
                                        <div class="legend-item">
                                            <span class="legend-color" style="background: #dc2626;"></span>
                                            <span class="legend-label">Periodontitis</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script src="assets/js/patient-details.js"></script>
</body>
</html>
