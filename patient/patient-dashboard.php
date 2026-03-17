<?php
ob_start();

// Set 10-minute session lifetime
$__sessionLifetime = 600;
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => $__sessionLifetime,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    ini_set('session.gc_maxlifetime', $__sessionLifetime);
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: ../login.php');
    exit();
}

// Verify this is a patient (user role)
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'user') {
    ob_end_clean();
    header('Location: ../login.php');
    exit();
}

// Get patient info
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    ob_end_clean();
    header('Location: ../login.php');
    exit();
}

// Patient_id might not be set yet, we'll get it from the database
$patient_id = $_SESSION['patient_id'] ?? null;

// Get patient details from database
require_once '../config/database.php';
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.first_name, u.middle_name, u.last_name, u.email 
        FROM patients p 
        LEFT JOIN users u ON u.id = p.user_id 
        WHERE p.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        throw new Exception('Patient not found');
    }
    
    // Save patient_id to session if it's not set
    if (!$patient_id) {
        $patient_id = $patient['id'];
        $_SESSION['patient_id'] = $patient_id;
    }
    
    // Get appointments
    $stmt = $pdo->prepare("
        SELECT a.* 
        FROM appointments a 
        WHERE a.patient_id = ? 
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute([$patient_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $patient = [];
    $appointments = [];
}

$pageTitle = 'Patient Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - RF Dental Clinic</title>
    <link rel="icon" type="image/png" href="../assets/images/Logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/patient-dashboard.css">
    <style>
        /* Base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }
        
        /* Header */
        .dashboard-header {
            background: white;
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo img {
            height: 40px;
            width: auto;
        }
        
        .logo span {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a2e;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }
        
        .user-details {
            text-align: right;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 14px;
            color: #1a1a2e;
        }
        
        .user-email {
            font-size: 12px;
            color: #666;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.2s;
        }
        
        .logout-btn:hover {
            background: #c82333;
        }
        
        /* Main content */
        .dashboard-main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }
        
        .welcome-section {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        
        .welcome-section h1 {
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .welcome-section p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        
        /* Calendar Section */
        .calendar-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .calendar-header h2 {
            font-size: 18px;
            color: #1a1a2e;
        }
        
        .calendar-nav {
            display: flex;
            gap: 8px;
        }
        
        .calendar-nav button {
            background: #f0f0f0;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .calendar-nav button:hover {
            background: #e0e0e0;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
        }
        
        .calendar-day-header {
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            color: #666;
            padding: 8px;
        }
        
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        
        .calendar-day:hover {
            background: #f0f0f0;
        }
        
        .calendar-day.other-month {
            color: #ccc;
        }
        
        .calendar-day.today {
            background: #e3f2fd;
            color: #007bff;
            font-weight: 600;
        }
        
        .calendar-day.has-appointment {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .calendar-day.has-appointment::after {
            content: '';
            position: absolute;
            bottom: 4px;
            width: 4px;
            height: 4px;
            background: #2e7d32;
            border-radius: 50%;
        }
        
        /* Sidebar */
        .sidebar-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 24px;
        }
        
        .sidebar-section h3 {
            font-size: 16px;
            margin-bottom: 16px;
            color: #1a1a2e;
        }
        
        .appointment-list {
            list-style: none;
        }
        
        .appointment-item {
            padding: 12px;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 13px;
        }
        
        .appointment-date {
            font-weight: 600;
            color: #007bff;
        }
        
        .appointment-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            margin-left: 8px;
        }
        
        .status-scheduled {
            background: #e3f2fd;
            color: #007bff;
        }
        
        .status-approved {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        /* Time Slots */
        .time-slots {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 12px;
        }
        
        .time-slot {
            padding: 8px;
            text-align: center;
            background: #f8f9fa;
            border: 1px solid #eee;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        }
        
        .time-slot:hover {
            background: #e9ecef;
        }
        
        .time-slot.selected {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .time-slot.unavailable {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Book Appointment Section */
        .book-appointment {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .book-appointment h3 {
            font-size: 16px;
            margin-bottom: 16px;
        }
        
        .book-appointment form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .book-appointment select,
        .book-appointment textarea {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
        }
        
        .book-appointment button {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }
        
        .book-appointment button:hover {
            background: #0056b3;
        }
        
        /* Responsive */
        @media (max-width: 900px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 600px) {
            .dashboard-header {
                padding: 12px 16px;
            }
            
            .user-details {
                display: none;
            }
            
            .dashboard-main {
                padding: 16px;
            }
            
            .welcome-section {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <header class="dashboard-header">
        <div class="logo">
            <img src="../assets/images/Logo.png" alt="RF Dental Clinic">
            <span>RF Dental Clinic</span>
        </div>
        <div class="user-info">
            <div class="user-avatar">
                <?php 
                    $initials = strtoupper(substr($patient['first_name'] ?? 'P', 0, 1) . substr($patient['last_name'] ?? '', 0, 1));
                    echo $initials;
                ?>
            </div>
            <div class="user-details">
                <div class="user-name">
                    <?php echo htmlspecialchars($patient['first_name'] ?? 'Patient') . ' ' . htmlspecialchars($patient['last_name'] ?? ''); ?>
                </div>
                <div class="user-email">
                    <?php echo htmlspecialchars($patient['email'] ?? ''); ?>
                </div>
            </div>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <main class="dashboard-main">
        <section class="welcome-section">
            <h1>Welcome back, <?php echo htmlspecialchars($patient['first_name'] ?? 'Patient'); ?>!</h1>
            <p>Manage your appointments and view your dental health information.</p>
        </section>

        <div class="dashboard-grid">
            <div class="main-content">
                <section class="calendar-section">
                    <div class="calendar-header">
                        <h2 id="currentMonthYear">Select a Date</h2>
                        <div class="calendar-nav">
                            <button type="button" id="prevMonth">&lt;</button>
                            <button type="button" id="nextMonth">&gt;</button>
                        </div>
                    </div>
                    <div class="calendar-grid" id="calendarGrid">
                        <!-- Calendar will be generated by JavaScript -->
                    </div>
                    
                    <div class="time-slots-section" style="margin-top: 24px; display: none;" id="timeSlotsSection">
                        <h3>Available Time Slots for <span id="selectedDateDisplay"></span></h3>
                        <div class="time-slots" id="timeSlots">
                            <!-- Time slots will be generated by JavaScript -->
                        </div>
                        <button type="button" id="confirmAppointment" style="margin-top: 16px; background: #28a745;" disabled>
                            Confirm Appointment
                        </button>
                    </div>
                </section>

                <section class="book-appointment" style="margin-top: 24px;">
                    <h3>Book a New Appointment</h3>
                    <form id="appointmentForm">
                        <select name="treatment" required>
                            <option value="">Select Treatment</option>
                            <option value="1">Consultation</option>
                            <option value="2">Teeth Cleaning</option>
                            <option value="3">Dental Checkup</option>
                            <option value="4">Root Canal</option>
                            <option value="5">Braces</option>
                        </select>
                        <textarea name="notes" placeholder="Additional notes (optional)" rows="2"></textarea>
                        <button type="submit">Request Appointment</button>
                    </form>
                </section>
            </div>

            <aside class="sidebar">
                <section class="sidebar-section">
                    <h3>Upcoming Appointments</h3>
                    <?php if (!empty($appointments)): ?>
                        <ul class="appointment-list">
                            <?php foreach ($appointments as $apt): ?>
                                <li class="appointment-item">
                                    <span class="appointment-date">
                                        <?php echo date('M j, Y', strtotime($apt['appointment_date'])); ?>
                                    </span>
                                    <span class="appointment-time">
                                        <?php echo date('g:i A', strtotime($apt['appointment_time'])); ?>
                                    </span>
                                    <span class="appointment-status status-<?php echo $apt['status']; ?>">
                                        <?php echo ucfirst($apt['status']); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="color: #666; font-size: 14px;">No upcoming appointments.</p>
                    <?php endif; ?>
                </section>

                <section class="sidebar-section">
                    <h3>Your Information</h3>
                    <div style="font-size: 13px; color: #666; line-height: 1.8;">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['first_name'] ?? '') . ' ' . htmlspecialchars($patient['last_name'] ?? ''); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phone'] ?? 'Not provided'); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($patient['email'] ?? 'Not provided'); ?></p>
                    </div>
                </section>
            </aside>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarGrid = document.getElementById('calendarGrid');
            const currentMonthYear = document.getElementById('currentMonthYear');
            const prevMonthBtn = document.getElementById('prevMonth');
            const nextMonthBtn = document.getElementById('nextMonth');
            const timeSlotsSection = document.getElementById('timeSlotsSection');
            const timeSlotsContainer = document.getElementById('timeSlots');
            const selectedDateDisplay = document.getElementById('selectedDateDisplay');
            const confirmAppointmentBtn = document.getElementById('confirmAppointment');
            const appointmentForm = document.getElementById('appointmentForm');
            
            let currentDate = new Date();
            let selectedDate = null;
            let selectedTime = null;
            
            // Generate calendar
            function renderCalendar() {
                const year = currentDate.getFullYear();
                const month = currentDate.getMonth();
                
                currentMonthYear.textContent = new Date(year, month).toLocaleDateString('en-US', {
                    month: 'long',
                    year: 'numeric'
                });
                
                calendarGrid.innerHTML = '';
                
                // Add day headers
                const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                dayHeaders.forEach(day => {
                    const header = document.createElement('div');
                    header.className = 'calendar-day-header';
                    header.textContent = day;
                    calendarGrid.appendChild(header);
                });
                
                const firstDay = new Date(year, month, 1).getDay();
                const totalDays = new Date(year, month + 1, 0).getDate();
                const prevMonthDays = new Date(year, month, 0).getDate();
                
                // Previous month days
                for (let i = firstDay - 1; i >= 0; i--) {
                    const day = document.createElement('div');
                    day.className = 'calendar-day other-month';
                    day.textContent = prevMonthDays - i;
                    calendarGrid.appendChild(day);
                }
                
                // Current month days
                const today = new Date();
                for (let i = 1; i <= totalDays; i++) {
                    const day = document.createElement('div');
                    day.className = 'calendar-day';
                    day.textContent = i;
                    
                    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
                    
                    // Check if it's today
                    if (today.getFullYear() === year && today.getMonth() === month && today.getDate() === i) {
                        day.classList.add('today');
                    }
                    
                    // Check if it's selected
                    if (selectedDate && selectedDate.getDate() === i && 
                        selectedDate.getMonth() === month && selectedDate.getFullYear() === year) {
                        day.classList.add('has-appointment');
                    }
                    
                    // Disable past dates and weekends
                    const dateObj = new Date(year, month, i);
                    const dayOfWeek = dateObj.getDay();
                    const isPast = dateObj < today.setHours(0,0,0,0);
                    
                    if (isPast || dayOfWeek === 0 || dayOfWeek === 6) {
                        day.style.opacity = '0.5';
                        day.style.cursor = 'not-allowed';
                    } else {
                        day.addEventListener('click', function() {
                            selectedDate = new Date(year, month, i);
                            selectedDateDisplay.textContent = selectedDate.toLocaleDateString('en-US', {
                                weekday: 'long',
                                month: 'long',
                                day: 'numeric'
                            });
                            timeSlotsSection.style.display = 'block';
                            renderTimeSlots();
                        });
                    }
                    
                    calendarGrid.appendChild(day);
                }
                
                // Next month days
                const remainingDays = 42 - (firstDay + totalDays);
                for (let i = 1; i <= remainingDays; i++) {
                    const day = document.createElement('div');
                    day.className = 'calendar-day other-month';
                    day.textContent = i;
                    calendarGrid.appendChild(day);
                }
            }
            
            function renderTimeSlots() {
                const times = [
                    '09:00 AM', '09:30 AM', '10:00 AM', '10:30 AM',
                    '11:00 AM', '11:30 AM', '01:00 PM', '01:30 PM',
                    '02:00 PM', '02:30 PM', '03:00 PM', '03:30 PM',
                    '04:00 PM', '04:30 PM'
                ];
                
                timeSlotsContainer.innerHTML = '';
                times.forEach(time => {
                    const slot = document.createElement('div');
                    slot.className = 'time-slot';
                    slot.textContent = time;
                    slot.addEventListener('click', function() {
                        document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
                        slot.classList.add('selected');
                        selectedTime = time;
                        confirmAppointmentBtn.disabled = false;
                    });
                    timeSlotsContainer.appendChild(slot);
                });
            }
            
            // Navigation
            prevMonthBtn.addEventListener('click', function() {
                currentDate.setMonth(currentDate.getMonth() - 1);
                renderCalendar();
            });
            
            nextMonthBtn.addEventListener('click', function() {
                currentDate.setMonth(currentDate.getMonth() + 1);
                renderCalendar();
            });
            
            // Form submission
            appointmentForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                if (!selectedDate || !selectedTime) {
                    alert('Please select a date and time slot.');
                    return;
                }
                
                const formData = new FormData(appointmentForm);
                formData.append('appointment_date', selectedDate.toISOString().split('T')[0]);
                formData.append('appointment_time', selectedTime);
                formData.append('patient_id', '<?php echo $patient_id; ?>');
                
                try {
                    const response = await fetch('../process_patient_appointment.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('Appointment booked successfully!');
                        location.reload();
                    } else {
                        alert(result.message || 'Failed to book appointment');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                }
            });
            
            // Initial render
            renderCalendar();
        });
    </script>
</body>
</html>