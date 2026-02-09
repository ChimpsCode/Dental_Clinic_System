<?php
/**
 * Staff New Admission - Same UI as dentist's NewAdmission.php
 * This file is Staff-specific to ensure role security
 * When clicking "Back to Dashboard", it returns to staff-dashboard.php
 */

// Start session and validate staff access
ob_start();
session_start();

// Strict role validation - only staff can access
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    // If admin, redirect to admin dashboard
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        ob_end_clean();
        header('Location: admin_dashboard.php');
        exit();
    }
    // If dentist or other role, redirect to main dashboard
    ob_end_clean();
    header('Location: dashboard.php');
    exit();
}

// Fetch services from database for the services selection
try {
    require_once 'config/database.php';
    $stmt = $pdo->query("
        SELECT id, name, mode, price, duration_minutes
        FROM services 
        WHERE is_active = 1 
        ORDER BY 
            CASE mode 
                WHEN 'BULK' THEN 1 
                WHEN 'SINGLE' THEN 2 
                WHEN 'NONE' THEN 3 
            END,
            name
    ");
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group services by mode
    $servicesByMode = [
        'SINGLE' => [],
        'BULK' => [],
        'NONE' => []
    ];
    foreach ($services as $service) {
        $servicesByMode[$service['mode']][] = $service;
    }
} catch (Exception $e) {
    $servicesByMode = ['SINGLE' => [], 'BULK' => [], 'NONE' => []];
}

 $username = $_SESSION['username'] ?? 'Staff';
 $fullName = $_SESSION['full_name'] ?? 'Staff';

 $inquiryData = null;
 $appointmentData = null;
 $appointmentId = null;
 
 // Check for inquiry_id
if (isset($_GET['inquiry_id']) && is_numeric($_GET['inquiry_id'])) {
    try {
        require_once 'config/database.php';
        $stmt = $pdo->prepare("SELECT * FROM inquiries WHERE id = ?");
        $stmt->execute([$_GET['inquiry_id']]);
        $inquiryData = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $inquiryData = null;
    }
}

// Check for appointment_id
if (isset($_GET['appointment_id']) && is_numeric($_GET['appointment_id'])) {
    try {
        require_once 'config/database.php';
        $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
        $stmt->execute([$_GET['appointment_id']]);
        $appointmentData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($appointmentData) {
            $appointmentId = $_GET['appointment_id'];
        }
    } catch (Exception $e) {
        $appointmentData = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Admission - RF Dental Clinic (Staff)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        /* Stepper active state */
        .step-active {
            background: #3b82f6 !important;
            border-color: #3b82f6 !important;
            color: white !important;
        }
        
        /* Completed step state */
        .step-completed {
            background: #10b981 !important;
            border-color: #10b981 !important;
            color: white !important;
        }
        
        /* Staff badge */
        .staff-badge {
            background: #dbeafe;
            color: #1e40af;
            padding: 2px 10px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* ---------------------------------------------------------
           NEW 3D DENTAL CHART STYLES (Only for Step 5)
           --------------------------------------------------------- */
        .dental-stage {
            perspective: 1000px; /* Essential for 3D effect */
            padding: 20px;
        }

        .arch-container {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-bottom: 30px; /* Space between upper and lower jaw */
            transform-style: preserve-3d;
        }

        /* The Tooth Container (Cube) */
        .tooth-wrapper {
            width: 34px;
            height: 46px;
            position: relative;
            transform-style: preserve-3d;
            transform: rotateX(-20deg) rotateY(0deg); /* Slight tilt back */
            transition: transform 0.3s ease, filter 0.3s ease;
            cursor: pointer;
        }

        /* Hover Effect: Tooth leans towards user */
        .tooth-wrapper:hover {
            transform: rotateX(0deg) scale(1.1);
            z-index: 10;
        }

        /* The visible face of the tooth */
        .tooth-face {
            position: absolute;
            width: 100%;
            height: 100%;
            background: white;
            border: 1px solid #cbd5e1; /* Slate-300 */
            border-radius: 6px 6px 12px 12px; /* Anatomical shape */
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.75rem;
            color: #64748b; /* Slate-500 */
            box-shadow: 
                0 4px 0 #94a3b8, /* 3D thickness/shadow */
                0 5px 6px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }

        /* Roots visual styling (optional visual cues at bottom) */
        .tooth-face::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 50%;
            transform: translateX(-50%);
            width: 60%;
            height: 4px;
            background: #cbd5e1;
            border-radius: 0 0 4px 4px;
            z-index: -1;
        }

        /* STATE: SELECTED (Blue) */
        .tooth-wrapper.selected .tooth-face {
            background: #bfdbfe; /* Blue-200 */
            border-color: #3b82f6; /* Blue-500 */
            color: #1e3a8a;
            box-shadow: 
                0 4px 0 #3b82f6,
                0 5px 10px rgba(59, 130, 246, 0.3);
            transform: translateY(-2px);
        }
        .tooth-wrapper.selected .tooth-face::after {
            background: #3b82f6;
        }

        /* STATE: NEEDS ATTENTION (Yellow) */
        .tooth-wrapper.attention .tooth-face {
            background: #fef08a; /* Yellow-200 */
            border-color: #eab308; /* Yellow-500 */
            color: #713f12;
            box-shadow: 
                0 4px 0 #eab308,
                0 5px 10px rgba(234, 179, 8, 0.3);
            transform: translateY(-2px);
        }
        .tooth-wrapper.attention .tooth-face::after {
            background: #eab308;
        }

        /* Labels */
        .quadrant-label {
            position: absolute;
            font-size: 0.7rem;
            color: #94a3b8;
            font-weight: 600;
            pointer-events: none;
        }
        .q-ur { top: 0; left: 0; }
        .q-ul { top: 0; right: 0; }
        .q-lr { bottom: 0; left: 0; }
        .q-ll { bottom: 0; right: 0; }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            max-width: 500px;
            animation: slideUp 0.4s ease;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-icon {
            font-size: 60px;
            margin-bottom: 20px;
            animation: bounce 0.6s ease;
        }

        .modal-icon.success { color: #10b981; }
        .modal-icon.error { color: #ef4444; }
        .modal-icon.loading { color: #3b82f6; }

        .modal-title {
            font-size: 24px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 15px;
        }

        .modal-message {
            color: #6b7280;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .modal-btn {
            padding: 10px 30px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .modal-btn-primary {
            background-color: #3b82f6;
            color: white;
        }

        .modal-btn-primary:hover {
            background-color: #2563eb;
            transform: translateY(-2px);
        }

        .modal-btn-secondary {
            background-color: #e5e7eb;
            color: #374151;
        }

        .modal-btn-secondary:hover {
            background-color: #d1d5db;
            transform: translateY(-2px);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes bounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .spinner {
            border: 4px solid #e5e7eb;
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

    </style>
</head>
<body class="bg-gray-50 text-slate-800 min-h-screen flex flex-col">

    <?php if ($inquiryData): ?>
    <div class="max-w-7xl mx-auto w-full px-6 pt-6">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4 flex items-center gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            <span class="text-sm text-blue-700">Forwarded from Inquiry: <strong><?php echo htmlspecialchars(trim(($inquiryData['first_name'] ?? '') . ' ' . ($inquiryData['middle_name'] ?? '') . ' ' . ($inquiryData['last_name'] ?? ''))); ?></strong> (<?php echo htmlspecialchars($inquiryData['source'] ?? ''); ?>)</span>
            <a href="staff_inquiries.php" class="ml-auto text-blue-600 hover:text-blue-800 text-sm font-medium">View Original Inquiry</a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($appointmentData): ?>
    <div class="max-w-7xl mx-auto w-full px-6 pt-6">
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4 flex items-center gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            <span class="text-sm text-green-700">Forwarded from Appointment: <strong><?php echo htmlspecialchars(trim(($appointmentData['first_name'] ?? '') . ' ' . ($appointmentData['last_name'] ?? ''))); ?></strong> (<?php echo htmlspecialchars($appointmentData['appointment_date'] ?? ''); ?>)</span>
            <a href="staff_appointments.php" class="ml-auto text-green-600 hover:text-green-800 text-sm font-medium">View Original Appointment</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="flex-1 flex flex-col md:flex-row max-w-7xl mx-auto w-full p-6 gap-8">

        <!-- Left Sidebar (Stepper) - Sticky with Back to Dashboard -->
        <div class="w-full md:w-1/4 flex flex-col sticky top-6 h-[calc(100vh-3rem)] overflow-y-auto">
            
            <!-- Back to Dashboard Button -->
            <div class="mb-6">
                <a href="#" onclick="goBackToDashboard(); return false;" class="inline-flex items-center text-slate-500 hover:text-blue-500 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                    <span class="text-sm font-medium">Back to Dashboard</span>
                </a>
            </div>
            <!-- Logo -->
            <div class="mb-10 flex items-center gap-2">
                <img src="assets/images/Logo.png" alt="RF Dental Clinic Logo" class="w-8 h-8 rounded-md">
                <h1 class="text-xl font-bold text-slate-800">New Patient</h1>
            </div>

            <!-- Vertical Stepper -->
            <div class="relative">
                <!-- Vertical Line -->
                <div class="absolute left-4 top-4 bottom-4 w-0.5 bg-slate-200 -z-10"></div>

                <nav class="space-y-8">
                    <!-- Step 1: Patient Information -->
                    <div id="nav-step-1" class="flex items-center gap-4 relative pl-0">
                        <div id="icon-step-1" class="w-8 h-8 rounded-full flex items-center justify-center border-2 transition-colors duration-200 z-10 bg-blue-500 border-blue-500 text-white">
                            <div id="dot-step-1" class="w-3 h-3 bg-white rounded-full"></div>
                        </div>
                        <span id="text-step-1" class="text-sm font-medium text-blue-500 transition-colors duration-200">Patient Information</span>
                    </div>

                    <!-- Step 2: Dental History -->
                    <div id="nav-step-2" class="flex items-center gap-4 relative pl-0 opacity-50">
                        <div id="icon-step-2" class="w-8 h-8 rounded-full flex items-center justify-center border-2 transition-colors duration-200 z-10 bg-white border-slate-300 text-slate-400">
                            <span class="text-xs font-medium">2</span>
                        </div>
                        <span id="text-step-2" class="text-sm font-medium text-slate-400 transition-colors duration-200">Dental History</span>
                    </div>

                    <!-- Step 3: Medical History -->
                    <div id="nav-step-3" class="flex items-center gap-4 relative pl-0 opacity-50">
                        <div id="icon-step-3" class="w-8 h-8 rounded-full flex items-center justify-center border-2 transition-colors duration-200 z-10 bg-white border-slate-300 text-slate-400">
                            <span class="text-xs font-medium">3</span>
                        </div>
                        <span id="text-step-3" class="text-sm font-medium text-slate-400 transition-colors duration-200">Medical History</span>
                    </div>

                    <!-- Step 4: Services -->
                    <div id="nav-step-4" class="flex items-center gap-4 relative pl-0 opacity-50">
                        <div id="icon-step-4" class="w-8 h-8 rounded-full flex items-center justify-center border-2 transition-colors duration-200 z-10 bg-white border-slate-300 text-slate-400">
                            <span class="text-xs font-medium">4</span>
                        </div>
                        <span id="text-step-4" class="text-sm font-medium text-slate-400 transition-colors duration-200">Services</span>
                    </div>

                    <!-- Step 5: Dental Chart -->
                    <div id="nav-step-5" class="flex items-center gap-4 relative pl-0 opacity-50">
                        <div id="icon-step-5" class="w-8 h-8 rounded-full flex items-center justify-center border-2 transition-colors duration-200 z-10 bg-white border-slate-300 text-slate-400">
                            <span class="text-xs font-medium">5</span>
                        </div>
                        <span id="text-step-5" class="text-sm font-medium text-slate-400 transition-colors duration-200">Dental Chart</span>
                    </div>
                </nav>
            </div>
        </div>

        <!-- Right Content Area (Form) -->
        <div class="w-full md:w-3/4 bg-white rounded-xl shadow-sm border border-slate-100 p-8 md:p-10 flex flex-col">
            
            <!-- Header Container (Will update text based on step) -->
            <div id="form-header" class="mb-8">
                <h2 class="text-2xl font-bold text-slate-800">Patient Information</h2>
                <p class="text-slate-500 text-sm mt-1">Please fill in the patient's details below.</p>
            </div>

            <form id="admissionForm" class="flex flex-col flex-1">
                <!-- Hidden Fields for Source Tracking -->
                <?php if ($appointmentId): ?>
                <input type="hidden" name="appointment_id" value="<?php echo $appointmentId; ?>">
                <input type="hidden" name="source" value="appointment">
                <?php endif; ?>
                <?php if (isset($_GET['inquiry_id'])): ?>
                <input type="hidden" name="inquiry_id" value="<?php echo htmlspecialchars($_GET['inquiry_id']); ?>">
                <?php endif; ?>
                
                <!-- STEP 1: Patient Information -->
                <div id="step-1" class="space-y-8 flex-1">
                    
                    <!-- Section 1: Personal Details -->
                    <section>
                        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-4">Personal Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-slate-600 mb-1">First Name</label>
                                <input type="text" name="firstName" value="<?php echo htmlspecialchars($inquiryData['first_name'] ?? $appointmentData['first_name'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="Juan">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-slate-600 mb-1">Middle Name</label>
                                <input type="text" name="middleName" value="<?php echo htmlspecialchars($inquiryData['middle_name'] ?? $appointmentData['middle_name'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-slate-600 mb-1">Last Name</label>
                                <input type="text" name="lastName" value="<?php echo htmlspecialchars($inquiryData['last_name'] ?? $appointmentData['last_name'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="Dela Cruz">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-slate-600 mb-1">Suffix</label>
                                <input type="text" name="suffix" value="<?php echo htmlspecialchars($appointmentData['suffix'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="Jr.">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-slate-600 mb-1">Birthdate</label>
                                <input type="date" name="birthdate" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm text-slate-600">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-slate-600 mb-1">Age <span class="text-xs text-slate-400">(auto-calculated)</span></label>
                                <input type="number" name="age" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm bg-gray-50" placeholder="Auto" readonly>
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-slate-600 mb-1">Gender</label>
                                <div class="relative">
                                    <select name="gender" class="w-full px-3 py-2 border border-gray-300 rounded-md appearance-none focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm text-slate-600 bg-white">
                                        <option value="" disabled selected>Select</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="absolute right-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                </div>
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-slate-600 mb-1">Religion</label>
                                <input type="text" name="religion" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                        </div>
                    </section>

                    <!-- Section 2: Contact Information -->
                    <section class="pt-4">
                        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-4">Contact Information</h3>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-1">Home Address</label>
                                <input type="text" name="address" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="123 Main St, Barangay">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-1">City</label>
                                <input type="text" name="city" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-1">Province</label>
                                <input type="text" name="province" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-1">Zip Code</label>
                                <input type="text" name="zipCode" pattern="[0-9]*" inputmode="numeric" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-1">Mobile Number</label>
                                <input type="tel" name="mobileNumber" value="<?php echo htmlspecialchars($inquiryData['contact_info'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" inputmode="numeric" maxlength="11">
                                <div class="text-xs text-red-500 mt-1 hidden" id="mobileError">Mobile number must be exactly 11 digits</div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-slate-600 mb-1">Email Address</label>
                                <input type="text" name="emailAddress" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="example@email.com">
                                <div class="text-xs text-red-500 mt-1 hidden" id="emailError">Email must contain @ symbol</div>
                            </div>
                        </div>
                    </section>

                    <!-- Section 3: Insurance Information -->
                    <section class="pt-4">
                        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-4">Insurance Information <span class="text-slate-300 font-normal">(Optional)</span></h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-1">Dental Insurance</label>
                                <input type="text" name="dentalInsurance" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-1">Effective Date</label>
                                <input type="date" name="effectiveDate" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm text-slate-600">
                            </div>
                        </div>
                    </section>
                    <!-- Patient Info Validation Message -->
                    <div id="patientInfoValidation" class="hidden mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm">
                        Please complete required patient information: First Name, Last Name, Birthdate, Gender, and Mobile Number.
                    </div>
                </div>

<!-- STEP 2: Dental History -->
                <div id="step-2" class="hidden space-y-8 flex-1">
                    <section>
                        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-4">Dental History</h3>
                        
                        <!-- New Patient / No Dental History Option -->
                        <div class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" id="newPatientNoDentalHistory" name="newPatientNoDentalHistory" value="new_patient" class="w-5 h-5 text-green-500 rounded focus:ring-green-500" onchange="toggleDentalHistory()">
                                <span class="text-sm font-medium text-slate-700">New patient / No previous dental history</span>
                            </label>
                        </div>
                        
<div id="dentalHistorySection">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-1">Previous Dentist</label>
                                <input type="text" name="prevDentist" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="Dr. Name">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-1">Date of Last Visit</label>
                                <input type="date" name="lastVisitDate" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm text-slate-600">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-1">Reason for Last Visit</label>
                                <textarea name="reasonLastVisit" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="e.g., Routine Checkup, Toothache"></textarea>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-1">Previous Treatments (Fillings, Extraction, etc.)</label>
                                <textarea name="prevTreatments" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-1">Current Dental Complaints</label>
                                <textarea name="complaints" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="e.g., Sensitivity, Pain"></textarea>
                            </div>
                        </div>
                        </div>
                        
                        <!-- Validation message -->
                        <div id="dentalHistoryValidation" class="hidden mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm">
                            Please check "New patient / No previous dental history" or fill in at least one dental history field to proceed.
                        </div>
                    </section>
                </div>

<!-- STEP 3: Medical History -->
                <div id="step-3" class="hidden space-y-8 flex-1">
                    <section>
                        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-4">Medical History</h3>
                        
                        <!-- No Medical Conditions Option -->
                        <div class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" id="noMedicalConditions" name="noMedicalConditions" value="none" class="w-5 h-5 text-green-500 rounded focus:ring-green-500" onchange="toggleMedicalConditions()">
                                <span class="text-sm font-medium text-slate-700">No medical conditions / None of the below apply</span>
                            </label>
                        </div>
                        
                        <div id="medicalConditionsSection" class="mb-6">
                            <label class="block text-sm font-medium text-slate-600 mb-3">Do you have any of the following?</label>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                <label class="medical-condition-item flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors">
                                    <input type="checkbox" name="medicalConditions" value="diabetes" class="w-4 h-4 text-blue-500 rounded focus:ring-blue-500">
                                    <span class="text-sm text-slate-600">Diabetes</span>
                                </label>
                                <label class="medical-condition-item flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors">
                                    <input type="checkbox" name="medicalConditions" value="heart_disease" class="w-4 h-4 text-blue-500 rounded focus:ring-blue-500">
                                    <span class="text-sm text-slate-600">Heart Disease</span>
                                </label>
                                <label class="medical-condition-item flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors">
                                    <input type="checkbox" name="medicalConditions" value="high_bp" class="w-4 h-4 text-blue-500 rounded focus:ring-blue-500">
                                    <span class="text-sm text-slate-600">High Blood Pressure</span>
                                </label>
                                <label class="medical-condition-item flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors">
                                    <input type="checkbox" name="medicalConditions" value="asthma" class="w-4 h-4 text-blue-500 rounded focus:ring-blue-500">
                                    <span class="text-sm text-slate-600">Asthma</span>
                                </label>
                                <label class="medical-condition-item flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors">
                                    <input type="checkbox" name="medicalConditions" value="allergies" class="w-4 h-4 text-blue-500 rounded focus:ring-blue-500">
                                    <span class="text-sm text-slate-600">Allergies</span>
                                </label>
                                <label class="medical-condition-item flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors">
                                    <input type="checkbox" name="medicalConditions" value="surgery" class="w-4 h-4 text-blue-500 rounded focus:ring-blue-500">
                                    <span class="text-sm text-slate-600">Previous Surgery</span>
                                </label>
                            </div>
                        </div>

                        <div id="medicationsSection" class="grid grid-cols-1 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-1">Current Medications</label>
                                <textarea name="medications" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="List any medications you are currently taking"></textarea>
                            </div>
                        </div>
                        
                        <!-- Validation message -->
                        <div id="medicalHistoryValidation" class="hidden mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm">
                            Please select at least one medical condition or check "No medical conditions" to proceed.
                        </div>
                    </section>
                </div>

                <!-- STEP 4: Services -->
                <div id="step-4" class="hidden space-y-8 flex-1">
                    <section>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider">Select Services</h3>
                            <button type="button" onclick="loadServices()" class="text-sm text-blue-500 hover:text-blue-600 font-medium">
                                🔄 Refresh Services
                            </button>
                        </div>
                        <div id="servicesContainer" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Services loaded dynamically from database -->
                            <div style="text-align: center; padding: 40px; color: #6b7280; grid-column: 1 / -1;">
                                Loading services...
                            </div>
                        </div>
                    </section>
                </div>

                <!-- STEP 5: 3D DENTAL CHART -->
                <div id="step-5" class="hidden space-y-8 flex-1">
                    <section class="dental-stage relative min-h-[400px] flex flex-col justify-center">
                        
                        <!-- Dynamic Instruction -->
                        <div id="dentalChartInstruction" class="text-center text-sm text-slate-500 mb-6 font-medium">
                            Select a service to see instructions
                        </div>
                        
                        <!-- Tooth Type Toggle Buttons -->
                        <div class="flex justify-center gap-3 mb-6">
                            <button type="button" id="btn-primary" onclick="setToothType('primary')" class="px-4 py-2 rounded-lg font-medium transition-colors border-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2Z"/></svg>
                                Primary
                            </button>
                            <button type="button" id="btn-permanent" onclick="setToothType('permanent')" class="px-4 py-2 rounded-lg font-medium transition-colors border-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L18 8L16 6M12 2L10 4L8 2M4 6L2 8L4 10M16 18L18 20L20 22M12 22L10 20L8 22M4 22L2 20L4 18"/></svg>
                                Adult
                            </button>
                        </div>
                        
                        <!-- Arch Selection Buttons (Hidden by default) -->
                        <div id="archSelectionButtons" class="hidden flex justify-center gap-4 mb-6">
                            <button type="button" onclick="selectArch('upper')" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium transition-colors flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12h20M2 12a4 4 0 0 1 4-4h12a4 4 0 0 1 4 4"/></svg>
                                Select Upper Arch
                            </button>
                            <button type="button" onclick="selectArch('lower')" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium transition-colors flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12h20M2 12a4 4 0 0 0 4 4h12a4 4 0 0 0 4-4"/></svg>
                                Select Lower Arch
                            </button>
                        </div>
                        
                        <!-- UPPER ARCH (Maxilla) -->
                        <div class="mb-6 relative">
                            <div id="upperArchLabel" class="text-center text-xs text-slate-400 mb-2 uppercase tracking-wide">Upper Arch (Maxilla)</div>
                            <div class="arch-container" id="upperArch">
                                <!-- Permanent Upper Right (18-11) -->
                                <div class="tooth-wrapper" data-tooth="18" onclick="toggleTooth3D(this)"><div class="tooth-face">18</div></div>
                                <div class="tooth-wrapper" data-tooth="17" onclick="toggleTooth3D(this)"><div class="tooth-face">17</div></div>
                                <div class="tooth-wrapper" data-tooth="16" onclick="toggleTooth3D(this)"><div class="tooth-face">16</div></div>
                                <div class="tooth-wrapper" data-tooth="15" onclick="toggleTooth3D(this)"><div class="tooth-face">15</div></div>
                                <div class="tooth-wrapper" data-tooth="14" onclick="toggleTooth3D(this)"><div class="tooth-face">14</div></div>
                                <div class="tooth-wrapper" data-tooth="13" onclick="toggleTooth3D(this)"><div class="tooth-face">13</div></div>
                                <div class="tooth-wrapper" data-tooth="12" onclick="toggleTooth3D(this)"><div class="tooth-face">12</div></div>
                                <div class="tooth-wrapper" data-tooth="11" onclick="toggleTooth3D(this)"><div class="tooth-face">11</div></div>
                                
                                <!-- Spacer for center -->
                                <div class="w-4"></div>

                                <!-- Permanent Upper Left (21-28) -->
                                <div class="tooth-wrapper" data-tooth="21" onclick="toggleTooth3D(this)"><div class="tooth-face">21</div></div>
                                <div class="tooth-wrapper" data-tooth="22" onclick="toggleTooth3D(this)"><div class="tooth-face">22</div></div>
                                <div class="tooth-wrapper" data-tooth="23" onclick="toggleTooth3D(this)"><div class="tooth-face">23</div></div>
                                <div class="tooth-wrapper" data-tooth="24" onclick="toggleTooth3D(this)"><div class="tooth-face">24</div></div>
                                <div class="tooth-wrapper" data-tooth="25" onclick="toggleTooth3D(this)"><div class="tooth-face">25</div></div>
                                <div class="tooth-wrapper" data-tooth="26" onclick="toggleTooth3D(this)"><div class="tooth-face">26</div></div>
                                <div class="tooth-wrapper" data-tooth="27" onclick="toggleTooth3D(this)"><div class="tooth-face">27</div></div>
                                <div class="tooth-wrapper" data-tooth="28" onclick="toggleTooth3D(this)"><div class="tooth-face">28</div></div>
                            </div>

                            <!-- Primary Upper Arch (Separate container, hidden by default) -->
                            <div class="arch-container hidden" id="primaryUpperArch">
                                <!-- Primary Upper Right (55-51) -->
                                <div class="tooth-wrapper" data-tooth="55" onclick="toggleTooth3D(this)"><div class="tooth-face">55</div></div>
                                <div class="tooth-wrapper" data-tooth="54" onclick="toggleTooth3D(this)"><div class="tooth-face">54</div></div>
                                <div class="tooth-wrapper" data-tooth="53" onclick="toggleTooth3D(this)"><div class="tooth-face">53</div></div>
                                <div class="tooth-wrapper" data-tooth="52" onclick="toggleTooth3D(this)"><div class="tooth-face">52</div></div>
                                <div class="tooth-wrapper" data-tooth="51" onclick="toggleTooth3D(this)"><div class="tooth-face">51</div></div>
                                
                                <!-- Spacer -->
                                <div class="w-4"></div>

                                <!-- Primary Upper Left (61-65) -->
                                <div class="tooth-wrapper" data-tooth="61" onclick="toggleTooth3D(this)"><div class="tooth-face">61</div></div>
                                <div class="tooth-wrapper" data-tooth="62" onclick="toggleTooth3D(this)"><div class="tooth-face">62</div></div>
                                <div class="tooth-wrapper" data-tooth="63" onclick="toggleTooth3D(this)"><div class="tooth-face">63</div></div>
                                <div class="tooth-wrapper" data-tooth="64" onclick="toggleTooth3D(this)"><div class="tooth-face">64</div></div>
                                <div class="tooth-wrapper" data-tooth="65" onclick="toggleTooth3D(this)"><div class="tooth-face">65</div></div>
                            </div>
                        </div>

                        <!-- LOWER ARCH (Mandible) -->
                        <div class="relative">
                            <div id="lowerArchLabel" class="text-center text-xs text-slate-400 mb-2 uppercase tracking-wide">Lower Arch (Mandible)</div>
                            <div class="arch-container" id="lowerArch">
                                <!-- Permanent Lower Right (48-41) -->
                                <div class="tooth-wrapper" data-tooth="48" onclick="toggleTooth3D(this)"><div class="tooth-face">48</div></div>
                                <div class="tooth-wrapper" data-tooth="47" onclick="toggleTooth3D(this)"><div class="tooth-face">47</div></div>
                                <div class="tooth-wrapper" data-tooth="46" onclick="toggleTooth3D(this)"><div class="tooth-face">46</div></div>
                                <div class="tooth-wrapper" data-tooth="45" onclick="toggleTooth3D(this)"><div class="tooth-face">45</div></div>
                                <div class="tooth-wrapper" data-tooth="44" onclick="toggleTooth3D(this)"><div class="tooth-face">44</div></div>
                                <div class="tooth-wrapper" data-tooth="43" onclick="toggleTooth3D(this)"><div class="tooth-face">43</div></div>
                                <div class="tooth-wrapper" data-tooth="42" onclick="toggleTooth3D(this)"><div class="tooth-face">42</div></div>
                                <div class="tooth-wrapper" data-tooth="41" onclick="toggleTooth3D(this)"><div class="tooth-face">41</div></div>

                                <!-- Spacer for center -->
                                <div class="w-4"></div>

                                <!-- Permanent Lower Left (31-38) -->
                                <div class="tooth-wrapper" data-tooth="31" onclick="toggleTooth3D(this)"><div class="tooth-face">31</div></div>
                                <div class="tooth-wrapper" data-tooth="32" onclick="toggleTooth3D(this)"><div class="tooth-face">32</div></div>
                                <div class="tooth-wrapper" data-tooth="33" onclick="toggleTooth3D(this)"><div class="tooth-face">33</div></div>
                                <div class="tooth-wrapper" data-tooth="34" onclick="toggleTooth3D(this)"><div class="tooth-face">34</div></div>
                                <div class="tooth-wrapper" data-tooth="35" onclick="toggleTooth3D(this)"><div class="tooth-face">35</div></div>
                                <div class="tooth-wrapper" data-tooth="36" onclick="toggleTooth3D(this)"><div class="tooth-face">36</div></div>
                                <div class="tooth-wrapper" data-tooth="37" onclick="toggleTooth3D(this)"><div class="tooth-face">37</div></div>
                                <div class="tooth-wrapper" data-tooth="38" onclick="toggleTooth3D(this)"><div class="tooth-face">38</div></div>
                            </div>

                            <!-- Primary Lower Arch (Separate container, hidden by default) -->
                            <div class="arch-container hidden" id="primaryLowerArch">
                                <!-- Primary Lower Right (85-81) -->
                                <div class="tooth-wrapper" data-tooth="85" onclick="toggleTooth3D(this)"><div class="tooth-face">85</div></div>
                                <div class="tooth-wrapper" data-tooth="84" onclick="toggleTooth3D(this)"><div class="tooth-face">84</div></div>
                                <div class="tooth-wrapper" data-tooth="83" onclick="toggleTooth3D(this)"><div class="tooth-face">83</div></div>
                                <div class="tooth-wrapper" data-tooth="82" onclick="toggleTooth3D(this)"><div class="tooth-face">82</div></div>
                                <div class="tooth-wrapper" data-tooth="81" onclick="toggleTooth3D(this)"><div class="tooth-face">81</div></div>
                                
                                <!-- Spacer -->
                                <div class="w-4"></div>

                                <!-- Primary Lower Left (71-75) -->
                                <div class="tooth-wrapper" data-tooth="71" onclick="toggleTooth3D(this)"><div class="tooth-face">71</div></div>
                                <div class="tooth-wrapper" data-tooth="72" onclick="toggleTooth3D(this)"><div class="tooth-face">72</div></div>
                                <div class="tooth-wrapper" data-tooth="73" onclick="toggleTooth3D(this)"><div class="tooth-face">73</div></div>
                                <div class="tooth-wrapper" data-tooth="74" onclick="toggleTooth3D(this)"><div class="tooth-face">74</div></div>
                                <div class="tooth-wrapper" data-tooth="75" onclick="toggleTooth3D(this)"><div class="tooth-face">75</div></div>
                            </div>
                        </div>

                        <!-- Legend -->
                        <div class="flex justify-center gap-6 mt-8 text-xs text-slate-500">
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 bg-white border border-slate-300 rounded"></div> Healthy
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 bg-blue-200 border border-blue-500 rounded"></div> Selected
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 bg-yellow-200 border border-yellow-500 rounded"></div> Attention
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Footer Actions -->
                <div class="mt-10 flex justify-between">
                    <!-- Back Button (Hidden on Step 1) -->
                    <button type="button" id="btn-back" onclick="goBack()" class="hidden bg-gray-200 hover:bg-gray-300 text-slate-600 px-8 py-2.5 rounded-md font-medium transition-colors flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        Back
                    </button>
                    
                    <!-- Next Button -->
                    <button type="button" id="btn-next" onclick="goToStep(2)" class="bg-blue-500 hover:bg-blue-600 text-white px-8 py-2.5 rounded-md font-medium transition-colors flex items-center gap-2 ml-auto">
                        Next <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </button>

                    <!-- Submit Button -->
                    <button type="button" onclick="handleSubmit()" id="btn-submit" class="hidden bg-green-500 hover:bg-green-600 text-white px-8 py-2.5 rounded-md font-medium transition-colors flex items-center gap-2 ml-auto">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        Submit
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Go back to previous page (Staff Dashboard)
        function goBackToDashboard() {
            // Try to go back in browser history
            if (document.referrer.includes('staff-dashboard.php')) {
                window.history.back();
            } else {
                // If no history, redirect to staff dashboard
                window.location.href = 'staff-dashboard.php';
            }
        }

        // Go back one step in the form
        function goBack() {
            if (currentStep > 1) {
                goToStep(currentStep - 1);
            }
        }

        let currentStep = 1;

        // Step titles for each step
        const stepTitles = {
            1: { title: 'Patient Information', desc: 'Please fill in the patient\'s details below.' },
            2: { title: 'Dental History', desc: 'Provide details about the patient\'s dental history.' },
            3: { title: 'Medical History', desc: 'Important health information for safe treatment.' },
            4: { title: 'Services', desc: 'Select the services the patient needs.' },
            5: { title: 'Dental Chart', desc: 'Mark teeth that require attention.' }
        };

        function goToStep(step) {
            // Validate patient info when moving from step 1 to step 2
            if (currentStep === 1 && step === 2) {
                if (!validatePatientInfo()) {
                    return; // Don't proceed if validation fails
                }
            }
            // Validate dental history when moving from step 2 to step 3
            if (currentStep === 2 && step === 3) {
                if (!validateDentalHistory()) {
                    return; // Don't proceed if validation fails
                }
            }
            
            // Validate medical history when moving from step 3 to step 4
            if (currentStep === 3 && step === 4) {
                if (!validateMedicalHistory()) {
                    return; // Don't proceed if validation fails
                }
            }
            
            currentStep = step;
            
            // Hide all steps
            for (let i = 1; i <= 5; i++) {
                document.getElementById('step-' + i).classList.add('hidden');
            }
            
            // Show target step
            document.getElementById('step-' + step).classList.remove('hidden');

            // Update Header
            const headerTitle = document.querySelector('#form-header h2');
            const headerDesc = document.querySelector('#form-header p');
            
            if (stepTitles[step]) {
                headerTitle.innerText = stepTitles[step].title;
                headerDesc.innerText = stepTitles[step].desc;
            }

            // Update navigation
            updateNav(step);

            // Update buttons
            const backBtn = document.getElementById('btn-back');
            const nextBtn = document.getElementById('btn-next');
            const submitBtn = document.getElementById('btn-submit');
            
            backBtn.style.display = step > 1 ? 'flex' : 'none';
            
            if (step < 5) {
                nextBtn.style.display = 'flex';
                nextBtn.innerHTML = 'Next <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>';
                nextBtn.onclick = function() { goToStep(step + 1); };
                submitBtn.style.display = 'none';
            } else {
                nextBtn.style.display = 'none';
                submitBtn.style.display = 'flex';
            }
        }

        function updateNav(activeStep) {
            for(let i = 1; i <= 5; i++) {
                const navItem = document.getElementById('nav-step-' + i);
                const icon = document.getElementById('icon-step-' + i);
                const text = document.getElementById('text-step-' + i);
                const dot = document.getElementById('dot-step-' + i);

                if (i < activeStep) {
                    // Completed steps
                    navItem.classList.remove('opacity-50');
                    icon.className = 'w-8 h-8 rounded-full flex items-center justify-center border-2 transition-colors duration-200 z-10 bg-green-500 border-green-500 text-white';
                    if(dot) dot.classList.remove('hidden');
                    if(!dot) icon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                    text.className = 'text-sm fonttext-green-500 transition-colors duration-200';
} else if (i === activeStep) {
// Current active step
navItem.classList.remove('opacity-50');
icon.className = 'w-8 h-8 rounded-full flex items-center justify-center border-2 transition-colors duration-200 z-10 bg-blue-500 border-blue-500 text-white';
if(dot) dot.classList.remove('hidden');
if(!dot) icon.innerHTML = '<div class="w-3 h-3 bg-white rounded-full"></div>';
text.className = 'text-sm font-medium text-blue-500 transition-colors duration-200';
} else {
// Future steps
navItem.classList.add('opacity-50');
icon.className = 'w-8 h-8 rounded-full flex items-center justify-center border-2 transition-colors duration-200 z-10 bg-white border-slate-300 text-slate-400';
if(dot) dot.classList.add('hidden');
if(!dot) icon.innerHTML = '<span class="text-xs font-medium">' + i + '</span>';
text.className = 'text-sm font-medium text-slate-400 transition-colors duration-200';
}
        }
        }

        /**
         * Toggle medical conditions section when "No medical conditions" is checked
         */
        function toggleMedicalConditions() {
            const noConditionsCheckbox = document.getElementById('noMedicalConditions');
            const conditionsSection = document.getElementById('medicalConditionsSection');
            const medicationsSection = document.getElementById('medicationsSection');
            const conditionCheckboxes = document.querySelectorAll('input[name="medicalConditions"]');
            const validationMessage = document.getElementById('medicalHistoryValidation');
            
            // Hide validation message when user takes action
            if (validationMessage) {
                validationMessage.classList.add('hidden');
            }
            
            if (noConditionsCheckbox.checked) {
                // Disable and gray out medical conditions
                conditionsSection.style.opacity = '0.5';
                conditionsSection.style.pointerEvents = 'none';
                medicationsSection.style.opacity = '0.5';
                medicationsSection.style.pointerEvents = 'none';
                
                // Uncheck all medical condition checkboxes
                conditionCheckboxes.forEach(cb => {
                    cb.checked = false;
                });
                
                // Clear medications textarea
                const medicationsTextarea = document.querySelector('textarea[name="medications"]');
                if (medicationsTextarea) {
                    medicationsTextarea.value = '';
                }
            } else {
                // Re-enable medical conditions
                conditionsSection.style.opacity = '1';
                conditionsSection.style.pointerEvents = 'auto';
                medicationsSection.style.opacity = '1';
                medicationsSection.style.pointerEvents = 'auto';
            }
        }

        /**
         * Validate medical history step
         * @returns {boolean} True if valid, false otherwise
         */
        function validateMedicalHistory() {
            const noConditionsCheckbox = document.getElementById('noMedicalConditions');
            const conditionCheckboxes = document.querySelectorAll('input[name="medicalConditions"]:checked');
            const validationMessage = document.getElementById('medicalHistoryValidation');
            
            // Valid if "No medical conditions" is checked OR at least one condition is selected
            const isValid = noConditionsCheckbox.checked || conditionCheckboxes.length > 0;
            
            if (!isValid) {
                // Show validation message
                if (validationMessage) {
                    validationMessage.classList.remove('hidden');
                }
                return false;
            }
            
            // Hide validation message
            if (validationMessage) {
                validationMessage.classList.add('hidden');
            }
            return true;
        }

        /**
         * Validate dental history step
         * @returns {boolean} True if valid, false otherwise
         */
        function validateDentalHistory() {
            const newPatientCheckbox = document.getElementById('newPatientNoDentalHistory');
            const prevDentist = document.querySelector('input[name="prevDentist"]').value.trim();
            const lastVisitDate = document.querySelector('input[name="lastVisitDate"]').value.trim();
            const reasonLastVisit = document.querySelector('textarea[name="reasonLastVisit"]').value.trim();
            const prevTreatments = document.querySelector('textarea[name="prevTreatments"]').value.trim();
            const complaints = document.querySelector('textarea[name="complaints"]').value.trim();
            const validationMessage = document.getElementById('dentalHistoryValidation');
            
            // Valid if "New patient" is checked OR at least one field is filled
            const hasAnyData = prevDentist || lastVisitDate || reasonLastVisit || prevTreatments || complaints;
            const isValid = newPatientCheckbox.checked || hasAnyData;
            
            if (!isValid) {
                // Show validation message
                if (validationMessage) {
                    validationMessage.classList.remove('hidden');
                }
                return false;
            }
            
            // Hide validation message
            if (validationMessage) {
                validationMessage.classList.add('hidden');
            }
            return true;
        }

        /**
         * Validate patient information (Step 1)
         * Required: firstName, lastName, birthdate, gender, mobileNumber
         */
        function validatePatientInfo() {
            const firstName = document.querySelector('input[name="firstName"]').value.trim();
            const lastName = document.querySelector('input[name="lastName"]').value.trim();
            const birthdate = document.querySelector('input[name="birthdate"]').value.trim();
            const gender = document.querySelector('select[name="gender"]').value;
            const mobile = document.querySelector('input[name="mobileNumber"]').value.trim();
            const validationMessage = document.getElementById('patientInfoValidation');

            const isValid = firstName && lastName && birthdate && gender && mobile && mobile.length === 11;

            if (!isValid) {
                if (validationMessage) validationMessage.classList.remove('hidden');
                return false;
            }

            if (validationMessage) validationMessage.classList.add('hidden');
            return true;
        }

        /**
         * Toggle dental history section when "New patient / No dental history" is checked
         */
        function toggleDentalHistory() {
            const newPatientCheckbox = document.getElementById('newPatientNoDentalHistory');
            const dentalHistorySection = document.getElementById('dentalHistorySection');
            
            if (newPatientCheckbox.checked) {
                // Disable and gray out dental history fields
                dentalHistorySection.style.opacity = '0.5';
                dentalHistorySection.style.pointerEvents = 'none';
                
                // Clear all dental history fields
                const prevDentist = document.querySelector('input[name="prevDentist"]');
                const lastVisitDate = document.querySelector('input[name="lastVisitDate"]');
                const reasonLastVisit = document.querySelector('textarea[name="reasonLastVisit"]');
                const prevTreatments = document.querySelector('textarea[name="prevTreatments"]');
                const complaints = document.querySelector('textarea[name="complaints"]');
                
                if (prevDentist) prevDentist.value = '';
                if (lastVisitDate) lastVisitDate.value = '';
                if (reasonLastVisit) reasonLastVisit.value = '';
                if (prevTreatments) prevTreatments.value = '';
                if (complaints) complaints.value = '';
            } else {
                // Re-enable dental history fields
                dentalHistorySection.style.opacity = '1';
                dentalHistorySection.style.pointerEvents = 'auto';
            }
        }

// Add event listeners to hide validation messages
        document.addEventListener('DOMContentLoaded', function() {
            // Medical history validation
            const conditionCheckboxes = document.querySelectorAll('input[name="medicalConditions"]');
            const medicalValidationMessage = document.getElementById('medicalHistoryValidation');
            
            conditionCheckboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    if (medicalValidationMessage) {
                        medicalValidationMessage.classList.add('hidden');
                    }
                });
            });
            
            // Dental history validation
            const dentalFields = [
                'input[name="prevDentist"]',
                'input[name="lastVisitDate"]',
                'textarea[name="reasonLastVisit"]',
                'textarea[name="prevTreatments"]',
                'textarea[name="complaints"]'
            ];
            const dentalValidationMessage = document.getElementById('dentalHistoryValidation');
            
            dentalFields.forEach(selector => {
                const field = document.querySelector(selector);
                if (field) {
                    field.addEventListener('input', function() {
                        if (dentalValidationMessage) {
                            dentalValidationMessage.classList.add('hidden');
                        }
                    });
                }
            });

            // ============ ZIP CODE - ONLY NUMBERS ============
            const zipInput = document.querySelector('input[name="zipCode"]');
            if (zipInput) {
                zipInput.addEventListener('keypress', function(e) {
                    // Block non-numeric characters
                    if (!/[0-9]/.test(e.key)) {
                        e.preventDefault();
                    }
                });
                zipInput.addEventListener('input', function() {
                    // Remove any non-numeric characters that slip through
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
                // Prevent paste of non-numeric content
                zipInput.addEventListener('paste', function(e) {
                    const pasteText = (e.clipboardData || window.clipboardData).getData('text');
                    if (!/^[0-9]*$/.test(pasteText)) {
                        e.preventDefault();
                    }
                });
            }

            // ============ MOBILE NUMBER VALIDATION ============
            const mobileInput = document.querySelector('input[name="mobileNumber"]');
            const mobileError = document.getElementById('mobileError');
            if (mobileInput) {
                mobileInput.addEventListener('input', function() {
                    // Allow only numbers
                    this.value = this.value.replace(/\D/g, '');
                    // Validate 11 digits
                    if (this.value.length === 11) {
                        mobileError.classList.add('hidden');
                        this.classList.remove('border-red-500');
                    } else if (this.value.length > 0) {
                        mobileError.classList.remove('hidden');
                        this.classList.add('border-red-500');
                    } else {
                        mobileError.classList.add('hidden');
                        this.classList.remove('border-red-500');
                    }
                });
                mobileInput.addEventListener('blur', function() {
                    const mobile = this.value.trim();
                    if (mobile.length > 0 && mobile.length !== 11) {
                        mobileError.classList.remove('hidden');
                        this.classList.add('border-red-500');
                    } else if (mobile.length === 11) {
                        mobileError.classList.add('hidden');
                        this.classList.remove('border-red-500');
                    }
                });
                mobileInput.addEventListener('keypress', function(e) {
                    if (!/[0-9]/.test(e.key)) {
                        e.preventDefault();
                    }
                });
            }

            // ============ EMAIL VALIDATION ============
            const emailInput = document.querySelector('input[name="emailAddress"]');
            const emailError = document.getElementById('emailError');
            if (emailInput) {
                emailInput.addEventListener('input', function() {
                    if (this.value.trim() && !this.value.includes('@')) {
                        emailError.classList.remove('hidden');
                        this.classList.add('border-red-500');
                    } else {
                        emailError.classList.add('hidden');
                        this.classList.remove('border-red-500');
                    }
                });
                emailInput.addEventListener('blur', function() {
                    if (this.value.trim() && !this.value.includes('@')) {
                        emailError.classList.remove('hidden');
                        this.classList.add('border-red-500');
                    } else {
                        emailError.classList.add('hidden');
                        this.classList.remove('border-red-500');
                    }
                });
            }
        });


            // ============ PATIENT INFO - ENABLE/DISABLE NEXT ============
            const nextBtn = document.getElementById('btn-next');
            function updateNextButtonState() {
                // Only apply during step 1
                if (currentStep !== 1) {
                    // Ensure button enabled for other steps
                    if (nextBtn) {
                        nextBtn.disabled = false;
                        nextBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    }
                    return;
                }

                const firstName = document.querySelector('input[name="firstName"]').value.trim();
                const lastName = document.querySelector('input[name="lastName"]').value.trim();
                const birthdate = document.querySelector('input[name="birthdate"]').value.trim();
                const gender = document.querySelector('select[name="gender"]').value;
                const mobile = document.querySelector('input[name="mobileNumber"]').value.trim();

                const canProceed = firstName && lastName && birthdate && gender && mobile && mobile.length === 11;

                if (nextBtn) {
                    nextBtn.disabled = !canProceed;
                    if (!canProceed) {
                        nextBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    } else {
                        nextBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    }
                }
            }

            // Wire up event listeners on required fields
            const requiredSelectors = [
                'input[name="firstName"]',
                'input[name="lastName"]',
                'input[name="birthdate"]',
                'select[name="gender"]',
                'input[name="mobileNumber"]'
            ];
            requiredSelectors.forEach(sel => {
                const el = document.querySelector(sel);
                if (el) {
                    el.addEventListener('input', updateNextButtonState);
                    el.addEventListener('change', updateNextButtonState);
                }
            });

            // Initial check
            updateNextButtonState();
        // Load services when page loads
        document.addEventListener('DOMContentLoaded', loadServices);

        // Global services data loaded from database
        let dbServices = [];
        
        /**
         * Load services from database
         */
        function loadServices() {
            const container = document.getElementById('servicesContainer');
            if (container) {
                container.innerHTML = '<div style="text-align: center; padding: 40px; color: #6b7280; grid-column: 1 / -1;">Loading services...</div>';
            }
            
            fetch('api_public_services.php')
                .then(response => response.json())
                .then(data => {
                    console.log('[SERVICES] Loaded:', data);
                    if (data.success) {
                        dbServices = data.services;
                        renderServices();
                    } else {
                        console.error('[SERVICES] Error:', data.message);
                        if (container) {
                            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc2626; grid-column: 1 / -1;">Error loading services: ' + data.message + '</div>';
                        }
                    }
                })
                .catch(error => {
                    console.error('[SERVICES] Error loading services:', error);
                    if (container) {
                        container.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc2626; grid-column: 1 / -1;">Failed to load services. Please try again.</div>';
                    }
                });
        }
        
        /**
         * Render services from loaded data
         */
        function renderServices() {
            const container = document.getElementById('servicesContainer');
            if (!container) return;
            
            console.log('[SERVICES] Rendering services. Total:', dbServices.length);
            
            // Group services by mode
            const grouped = { 'SINGLE': [], 'BULK': [], 'NONE': [] };
            dbServices.forEach(service => {
                if (grouped[service.mode]) {
                    grouped[service.mode].push(service);
                }
            });
            
            console.log('[SERVICES] Grouped:', grouped);
            
            let html = '';
            
            // SINGLE mode services first
            if (grouped['SINGLE'].length > 0) {
                grouped['SINGLE'].forEach(service => {
                    html += createServiceCheckbox(service);
                });
            }
            
            // BULK mode services
            if (grouped['BULK'].length > 0) {
                grouped['BULK'].forEach(service => {
                    html += createServiceCheckbox(service);
                });
            }
            
            // NONE mode services
            if (grouped['NONE'].length > 0) {
                grouped['NONE'].forEach(service => {
                    html += createServiceCheckbox(service);
                });
            }
            
            if (html === '') {
                html = '<div style="text-align: center; padding: 40px; color: #6b7280; grid-column: 1 / -1;">No services available. Please contact admin to add services.</div>';
            }
            
            container.innerHTML = html;
            console.log('[SERVICES] Services rendered successfully');
        }
        
        /**
         * Create service checkbox HTML
         */
        function createServiceCheckbox(service) {
            const price = parseFloat(service.price).toLocaleString('en-PH', { minimumFractionDigits: 2 });
            const duration = service.duration_minutes ? ` - ${service.duration_minutes} mins` : '';
            
            return `
                <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <input type="checkbox" name="services[]" value="${escapeHtml(service.name)}" data-mode="${service.mode}" data-id="${service.id}" class="w-5 h-5 text-blue-500 rounded focus:ring-blue-500" onchange="updateDentalChartMode()">
                    <div>
                        <span class="block text-sm font-medium text-slate-800">${escapeHtml(service.name)}</span>
                        <span class="block text-xs text-slate-500">₱${price}${duration}</span>
                    </div>
                </label>
            `;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        /**
         * Helper function to get the service mode based on service name
         * 
         * @param {string} serviceName - The display name of the service
         * @returns {string} 'BULK' | 'SINGLE' | 'NONE'
         */
        function getServiceMode(serviceName) {
            const service = dbServices.find(s => s.name === serviceName);
            if (service) {
                return service.mode;
            }
            return 'NONE';
        }
        
        /**
         * Get the instruction text for the selected service
         * @param {string} serviceName - The display name of the service
         * @returns {string} Instruction text
         */
        function getServiceInstruction(serviceName) {
            const mode = getServiceMode(serviceName);
            
            if (mode === 'BULK') {
                return 'Select teeth for treatment. Use "Select Upper/Lower Arch" buttons for bulk selection.';
            } else if (mode === 'SINGLE') {
                return 'Click specific tooth to ' + getActionVerb(serviceName);
            } else {
                return 'No tooth selection required for this service.';
            }
        }
        
        /**
         * Get the action verb for single mode services
         * @param {string} serviceName - The display name of the service
         * @returns {string} Action verb
         */
        function getActionVerb(serviceName) {
            // Try to get verb from database service
            const service = dbServices.find(s => s.name === serviceName);
            if (service) {
                // Generate verb from service name
                const name = service.name.toLowerCase();
                if (name.includes('extraction')) return 'extract';
                if (name.includes('root canal')) return 'treat';
                if (name.includes('xray') || name.includes('x-ray')) return 'x-ray';
                if (name.includes('crown')) return 'crown';
                if (name.includes('bridge')) return 'bridge';
                if (name.includes('restoration') || name.includes('filling') || name.includes('pasta')) return 'restore/fill';
                if (name.includes('cleaning')) return 'clean';
            }
            return 'treat';
        }
        
        /**
         * Determine the overall mode based on all selected services
         * Priority: BULK > SINGLE > NONE
         * @returns {string} 'BULK' | 'SINGLE' | 'NONE'
         */
        function getOverallMode() {
            const checkedBoxes = document.querySelectorAll('input[name="services[]"]:checked');
            
            if (checkedBoxes.length === 0) {
                return 'NONE';
            }
            
            let hasBulk = false;
            let hasSingle = false;
            
            checkedBoxes.forEach(cb => {
                const mode = getServiceMode(cb.value);
                if (mode === 'BULK') hasBulk = true;
                if (mode === 'SINGLE') hasSingle = true;
            });
            
            // BULK takes priority over SINGLE
            if (hasBulk) return 'BULK';
            if (hasSingle) return 'SINGLE';
            return 'NONE';
        }
        
        /**
         * Update the dental chart mode based on selected services
         * Shows/hides arch selection buttons and updates instruction text
         */
        function updateDentalChartMode() {
            const mode = getOverallMode();
            const instruction = document.getElementById('dentalChartInstruction');
            const archButtons = document.getElementById('archSelectionButtons');
            
            // Get teeth based on current tooth type
            let teeth = [];
            if (toothType === 'primary') {
                const primaryUpper = document.querySelectorAll('#primaryUpperArch .tooth-wrapper');
                const primaryLower = document.querySelectorAll('#primaryLowerArch .tooth-wrapper');
                teeth = [...primaryUpper, ...primaryLower];
            } else {
                const permanentUpper = document.querySelectorAll('#upperArch .tooth-wrapper');
                const permanentLower = document.querySelectorAll('#lowerArch .tooth-wrapper');
                teeth = [...permanentUpper, ...permanentLower];
            }
            
            // Get the first selected service for instruction
            const firstChecked = document.querySelector('input[name="services[]"]:checked');
            const serviceName = firstChecked ? firstChecked.value : '';
            
            // Update instruction text
            if (serviceName) {
                instruction.innerText = getServiceInstruction(serviceName);
            } else {
                instruction.innerText = 'Select a service to see instructions';
            }
            
            // Show/hide arch selection buttons based on mode
            if (mode === 'BULK') {
                archButtons.classList.remove('hidden');
                teeth.forEach(tooth => {
                    tooth.style.pointerEvents = 'auto';
                    tooth.style.opacity = '1';
                });
            } else if (mode === 'SINGLE') {
                archButtons.classList.add('hidden');
                teeth.forEach(tooth => {
                    tooth.style.pointerEvents = 'auto';
                    tooth.style.opacity = '1';
                });
            } else {
                archButtons.classList.add('hidden');
                teeth.forEach(tooth => {
                    tooth.style.pointerEvents = 'none';
                    tooth.style.opacity = '0.5';
                });
            }
        }
        
        /**
         * Select or deselect all teeth in an arch
         * @param {string} arch - 'upper' or 'lower'
         */
        function selectArch(arch) {
            let archElement;
            if (toothType === 'primary') {
                archElement = document.getElementById('primary' + arch.charAt(0).toUpperCase() + arch.slice(1) + 'Arch');
            } else {
                archElement = document.getElementById(arch + 'Arch');
            }
            
            if (!archElement) return;
            
            const teeth = archElement.querySelectorAll('.tooth-wrapper');
            
            // Check if all teeth are already selected
            const allSelected = Array.from(teeth).every(tooth => 
                tooth.classList.contains('selected')
            );
            
            teeth.forEach(tooth => {
                if (allSelected) {
                    // Deselect all
                    tooth.classList.remove('selected', 'attention');
                } else {
                    // Select all
                    tooth.classList.add('selected');
                    tooth.classList.remove('attention');
                }
            });
        }

        // --- OLD FUNCTION (Kept for compatibility, though Step 5 HTML changed) ---
function toggleTooth(btn) {
// Toggle between yellow (needs attention) and blue (selected)
if (btn.classList.contains('bg-blue-200')) {
btn.classList.remove('bg-blue-200', 'border-blue-400');
btn.classList.add('bg-yellow-200', 'border-yellow-400');
} else if (btn.classList.contains('bg-yellow-200')) {
btn.classList.remove('bg-yellow-200', 'border-yellow-400');
} else {
btn.classList.remove('bg-white');
btn.classList.add('bg-blue-200', 'border-blue-400');
}
}

        // --- NEW FUNCTION FOR 3D TOOTH INTERACTION ---
        function toggleTooth3D(wrapper) {
            const mode = getOverallMode();
            
            if (mode === 'NONE') {
                return; // Don't allow interaction when mode is NONE
            }
            
            if (wrapper.classList.contains('selected')) {
                wrapper.classList.remove('selected');
                wrapper.classList.add('attention');
            } else if (wrapper.classList.contains('attention')) {
                wrapper.classList.remove('attention');
            } else {
                wrapper.classList.add('selected');
            }
        }

        // Tooth Type Toggle Functions
        let toothType = 'permanent'; // 'primary' or 'permanent'
        let toothTypeManuallySet = false; // Track if user manually changed

        function setToothType(type) {
            toothType = type;
            toothTypeManuallySet = true;
            updateToothTypeButtons();
            updateToothVisibility();
        }

        function updateToothTypeButtons() {
            const primaryBtn = document.getElementById('btn-primary');
            const permanentBtn = document.getElementById('btn-permanent');
            
            if (toothType === 'primary') {
                primaryBtn.className = 'px-4 py-2 rounded-lg font-medium transition-colors border-2 border-blue-500 bg-blue-500 text-white';
                permanentBtn.className = 'px-4 py-2 rounded-lg font-medium transition-colors border-2 border-slate-300 bg-white text-slate-600';
            } else {
                primaryBtn.className = 'px-4 py-2 rounded-lg font-medium transition-colors border-2 border-slate-300 bg-white text-slate-600';
                permanentBtn.className = 'px-4 py-2 rounded-lg font-medium transition-colors border-2 border-blue-500 bg-blue-500 text-white';
            }
        }

        function updateToothVisibility() {
            const permanentUpperArch = document.getElementById('upperArch');
            const permanentLowerArch = document.getElementById('lowerArch');
            const primaryUpperArch = document.getElementById('primaryUpperArch');
            const primaryLowerArch = document.getElementById('primaryLowerArch');
            const upperLabel = document.getElementById('upperArchLabel');
            const lowerLabel = document.getElementById('lowerArchLabel');
            
            if (toothType === 'primary') {
                permanentUpperArch.classList.add('hidden');
                permanentLowerArch.classList.add('hidden');
                primaryUpperArch.classList.remove('hidden');
                primaryLowerArch.classList.remove('hidden');
                upperLabel.innerText = 'Primary Upper Arch';
                lowerLabel.innerText = 'Primary Lower Arch';
            } else {
                permanentUpperArch.classList.remove('hidden');
                permanentLowerArch.classList.remove('hidden');
                primaryUpperArch.classList.add('hidden');
                primaryLowerArch.classList.add('hidden');
                upperLabel.innerText = 'Upper Arch (Maxilla)';
                lowerLabel.innerText = 'Lower Arch (Mandible)';
            }
        }

        function initializeToothTypeFromAge() {
            const ageInput = document.querySelector('input[name="age"]');
            if (ageInput && ageInput.value && !toothTypeManuallySet) {
                const age = parseInt(ageInput.value);
                // Default to primary teeth for patients under 12, permanent for 12+
                toothType = age < 12 ? 'primary' : 'permanent';
                updateToothTypeButtons();
                updateToothVisibility();
            }
        }

        /**
         * Calculate age from birthdate and update the age field
         * @param {string} birthdate - Date string in YYYY-MM-DD format
         * @returns {number} Age in years
         */
        function calculateAgeFromBirthdate(birthdate) {
            if (!birthdate) return null;
            
            const today = new Date();
            const birthDate = new Date(birthdate);
            
            // Check if valid date
            if (isNaN(birthDate.getTime())) return null;
            
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            // Adjust age if birthday hasn't occurred yet this year
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            return age >= 0 ? age : 0;
        }

        /**
         * Handle birthdate change - auto-calculate and fill age
         */
        function handleBirthdateChange() {
            const birthdateInput = document.querySelector('input[name="birthdate"]');
            const ageInput = document.querySelector('input[name="age"]');
            
            if (birthdateInput && ageInput && birthdateInput.value) {
                const age = calculateAgeFromBirthdate(birthdateInput.value);
                if (age !== null) {
                    ageInput.value = age;
                    // Trigger change event to update tooth type
                    ageInput.dispatchEvent(new Event('change'));
                }
            }
        }

        // Listen for age changes to auto-update tooth type
        document.addEventListener('DOMContentLoaded', function() {
            const ageInput = document.querySelector('input[name="age"]');
            if (ageInput) {
                ageInput.addEventListener('change', function() {
                    if (!toothTypeManuallySet) {
                        initializeToothTypeFromAge();
                    }
                });
            }
            
            // Listen for birthdate change to auto-calculate age
            const birthdateInput = document.querySelector('input[name="birthdate"]');
            if (birthdateInput) {
                birthdateInput.addEventListener('change', function() {
                    // Calculate and fill age from birthdate
                    handleBirthdateChange();
                    
                    // Update tooth type based on new age
                    if (!toothTypeManuallySet) {
                        initializeToothTypeFromAge();
                    }
                });
            }
            
            // Initial check
            initializeToothTypeFromAge();
        });

        function handleSubmit() {
            const form = document.getElementById('admissionForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            // Get selected services
            const services = [];
            document.querySelectorAll('input[name="services[]"]:checked').forEach(cb => {
                services.push(cb.value);
            });
            data.services = services;

            // Get selected teeth
            const selectedTeeth = [];
            document.querySelectorAll('.tooth-wrapper.selected, .tooth-wrapper.attention').forEach(wrapper => {
                selectedTeeth.push(wrapper.dataset.tooth);
            });
            data.selectedTeeth = selectedTeeth;
            
            // Add selected teeth to form data
            selectedTeeth.forEach(tooth => {
                formData.append('selectedTeeth[]', tooth);
            });

            // Get medical conditions
            const medicalConditions = [];
            document.querySelectorAll('input[name="medicalConditions"]:checked').forEach(cb => {
                medicalConditions.push(cb.value);
            });
            data.medicalConditions = medicalConditions;

            const patientName = data.firstName && data.lastName ? (data.firstName + ' ' + data.lastName) : 'Patient';

            // Show loading modal
            showModal('loading', 'Submitting...', 'Processing patient admission...');

            // Submit to server
            fetch('process_staff_admission.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showModal('success', 'Success!', 'Patient <strong>' + patientName + '</strong> has been admitted successfully and added to the queue.', [
                        { text: 'View Queue', onclick: () => { window.location.href = 'staff_queue.php'; } },
                        { text: 'Go to Dashboard', onclick: () => { window.location.href = 'staff-dashboard.php'; } }
                    ]);
                } else {
                    showModal('error', 'Error', result.message || 'Failed to save patient. Please try again.', [
                        { text: 'Okay', onclick: closeModal }
                    ]);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showModal('error', 'Error', 'An error occurred while saving. Please try again.', [
                    { text: 'Okay', onclick: closeModal }
                ]);
            });
        }

        function showModal(type, title, message, buttons = []) {
            const modal = document.getElementById('resultModal');
            const icon = document.getElementById('modalIcon');
            const titleEl = document.getElementById('modalTitle');
            const messageEl = document.getElementById('modalMessage');
            const buttonsContainer = document.getElementById('modalButtons');

            // Set icon based on type
            if (type === 'success') {
                icon.innerHTML = '✓';
                icon.className = 'modal-icon success';
            } else if (type === 'error') {
                icon.innerHTML = '✕';
                icon.className = 'modal-icon error';
            } else if (type === 'loading') {
                icon.innerHTML = '<div class="spinner"></div>';
                icon.className = 'modal-icon loading';
            }

            titleEl.textContent = title;
            messageEl.innerHTML = message;

            // Clear and add buttons
            buttonsContainer.innerHTML = '';
            if (buttons.length > 0) {
                buttons.forEach(btn => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'modal-btn ' + (btn.primary ? 'modal-btn-primary' : 'modal-btn-primary');
                    button.textContent = btn.text;
                    button.onclick = btn.onclick;
                    buttonsContainer.appendChild(button);
                });
            }

            modal.classList.add('show');
        }

        function closeModal() {
            const modal = document.getElementById('resultModal');
            modal.classList.remove('show');
        }

</script>

    <!-- Result Modal -->
    <div id="resultModal" class="modal">
        <div class="modal-content">
            <div id="modalIcon" class="modal-icon"></div>
            <div id="modalTitle" class="modal-title"></div>
            <div id="modalMessage" class="modal-message"></div>
            <div id="modalButtons" class="modal-buttons"></div>
        </div>
    </div>

</body>
</html>
```