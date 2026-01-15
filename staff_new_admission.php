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

 $username = $_SESSION['username'] ?? 'Staff';
 $fullName = $_SESSION['full_name'] ?? 'Staff Member';
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

    </style>
</head>
<body class="bg-gray-50 text-slate-800 min-h-screen flex flex-col">

    <!-- Top Navigation - Goes back to previous page (Staff Dashboard) -->
    <div class="p-6">
        <a href="#" onclick="goBack(); return false;" class="inline-flex items-center text-slate-500 hover:text-blue-500 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            <span class="text-sm font-medium">Back to Dashboard</span>
        </a>
    </div>

    <div class="flex-1 flex flex-col md:flex-row max-w-7xl mx-auto w-full p-6 gap-8">
        
        <!-- Left Sidebar (Stepper) -->
        <div class="w-full md:w-1/4 flex flex-col">
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
                
                <!-- STEP 1: Patient Information -->
                <div id="step-1" class="space-y-8 flex-1">
                    
                    <!-- Section 1: Personal Details -->
                    <section>
                        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-4">Personal Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-slate-600 mb-1">First Name</label>
                                <input type="text" name="firstName" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="Juan">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-slate-600 mb-1">Middle Name</label>
                                <input type="text" name="middleName" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-slate-600 mb-1">Last Name</label>
                                <input type="text" name="lastName" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="Dela Cruz">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-slate-600 mb-1">Suffix</label>
                                <input type="text" name="suffix" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="Jr.">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-slate-600 mb-1">Birthdate</label>
                                <input type="date" name="birthdate" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm text-slate-600">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-slate-600 mb-1">Age</label>
                                <input type="number" name="age" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="30">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-slate-600 mb-1">Gender</label>
                                <div class="relative">
                                    <select name="gender" class="w-full px-3 py-2 border border-gray-300 rounded-md appearance-none focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm text-slate-600 bg-white">
                                        <option value="" disabled selected>Select</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
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
                                <input type="text" name="zipCode" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-1">Mobile Number</label>
                                <input type="tel" name="mobileNumber" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-slate-600 mb-1">Email Address</label>
                                <input type="email" name="emailAddress" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
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
                </div>

                <!-- STEP 2: Dental History -->
                <div id="step-2" class="hidden space-y-8 flex-1">
                    <section>
                        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-4">Dental History</h3>
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
                    </section>
                </div>

                <!-- STEP 3: Medical History -->
                <div id="step-3" class="hidden space-y-8 flex-1">
                    <section>
                        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-4">Medical History</h3>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-slate-600 mb-3">Do you have any of the following?</label>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                <label class="flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors">
                                    <input type="checkbox" name="medicalConditions" value="diabetes" class="w-4 h-4 text-blue-500 rounded focus:ring-blue-500">
                                    <span class="text-sm text-slate-600">Diabetes</span>
                                </label>
                                <label class="flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors">
                                    <input type="checkbox" name="medicalConditions" value="heart_disease" class="w-4 h-4 text-blue-500 rounded focus:ring-blue-500">
                                    <span class="text-sm text-slate-600">Heart Disease</span>
                                </label>
                                <label class="flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors">
                                    <input type="checkbox" name="medicalConditions" value="high_bp" class="w-4 h-4 text-blue-500 rounded focus:ring-blue-500">
                                    <span class="text-sm text-slate-600">High Blood Pressure</span>
                                </label>
                                <label class="flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors">
                                    <input type="checkbox" name="medicalConditions" value="asthma" class="w-4 h-4 text-blue-500 rounded focus:ring-blue-500">
                                    <span class="text-sm text-slate-600">Asthma</span>
                                </label>
                                <label class="flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors">
                                    <input type="checkbox" name="medicalConditions" value="allergies" class="w-4 h-4 text-blue-500 rounded focus:ring-blue-500">
                                    <span class="text-sm text-slate-600">Allergies</span>
                                </label>
                                <label class="flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors">
                                    <input type="checkbox" name="medicalConditions" value="surgery" class="w-4 h-4 text-blue-500 rounded focus:ring-blue-500">
                                    <span class="text-sm text-slate-600">Previous Surgery</span>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-1">Current Medications</label>
                                <textarea name="medications" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="List any medications you are currently taking"></textarea>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- STEP 4: Services -->
                <div id="step-4" class="hidden space-y-8 flex-1">
                    <section>
                        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-4">Select Services</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                                <input type="checkbox" name="services[]" value="extraction" class="w-5 h-5 text-blue-500 rounded focus:ring-blue-500">
                                <div>
                                    <span class="block text-sm font-medium text-slate-800">Tooth Extraction</span>
                                    <span class="block text-xs text-slate-500">₱1,500 - 30 mins</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                                <input type="checkbox" name="services[]" value="root_canal" class="w-5 h-5 text-blue-500 rounded focus:ring-blue-500">
                                <div>
                                    <span class="block text-sm font-medium text-slate-800">Root Canal Treatment</span>
                                    <span class="block text-xs text-slate-500">₱5,000 - 90 mins</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                                <input type="checkbox" name="services[]" value="cleaning" class="w-5 h-5 text-blue-500 rounded focus:ring-blue-500">
                                <div>
                                    <span class="block text-sm font-medium text-slate-800">Oral Prophylaxis</span>
                                    <span class="block text-xs text-slate-500">₱2,000 - 45 mins</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                                <input type="checkbox" name="services[]" value="denture" class="w-5 h-5 text-blue-500 rounded focus:ring-blue-500">
                                <div>
                                    <span class="block text-sm font-medium text-slate-800">Denture Adjustment</span>
                                    <span class="block text-xs text-slate-500">₱1,500 - 30 mins</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                                <input type="checkbox" name="services[]" value="xray" class="w-5 h-5 text-blue-500 rounded focus:ring-blue-500">
                                <div>
                                    <span class="block text-sm font-medium text-slate-800">Dental X-Ray</span>
                                    <span class="block text-xs text-slate-500">₱800 - 15 mins</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                                <input type="checkbox" name="services[]" value="braces" class="w-5 h-5 text-blue-500 rounded focus:ring-blue-500">
                                <div>
                                    <span class="block text-sm font-medium text-slate-800">Braces Consultation</span>
                                    <span class="block text-xs text-slate-500">₱1,000 - 60 mins</span>
                                </div>
                            </label>
                        </div>
                    </section>
                </div>

                <!-- STEP 5: 3D DENTAL CHART (Updated Only) -->
                <div id="step-5" class="hidden space-y-8 flex-1">
                    <section class="dental-stage relative min-h-[400px] flex flex-col justify-center">
                        
                        <!-- UPPER ARCH (Maxilla) -->
                        <div class="mb-8 relative">
                            <div class="arch-container">
                                <!-- Upper Right (18-11) -->
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

                                <!-- Upper Left (21-28) -->
                                <div class="tooth-wrapper" data-tooth="21" onclick="toggleTooth3D(this)"><div class="tooth-face">21</div></div>
                                <div class="tooth-wrapper" data-tooth="22" onclick="toggleTooth3D(this)"><div class="tooth-face">22</div></div>
                                <div class="tooth-wrapper" data-tooth="23" onclick="toggleTooth3D(this)"><div class="tooth-face">23</div></div>
                                <div class="tooth-wrapper" data-tooth="24" onclick="toggleTooth3D(this)"><div class="tooth-face">24</div></div>
                                <div class="tooth-wrapper" data-tooth="25" onclick="toggleTooth3D(this)"><div class="tooth-face">25</div></div>
                                <div class="tooth-wrapper" data-tooth="26" onclick="toggleTooth3D(this)"><div class="tooth-face">26</div></div>
                                <div class="tooth-wrapper" data-tooth="27" onclick="toggleTooth3D(this)"><div class="tooth-face">27</div></div>
                                <div class="tooth-wrapper" data-tooth="28" onclick="toggleTooth3D(this)"><div class="tooth-face">28</div></div>
                            </div>
                        </div>

                        <!-- LOWER ARCH (Mandible) -->
                        <div class="relative">
                            <div class="arch-container">
                                <!-- Lower Right (48-41) -->
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

                                <!-- Lower Left (31-38) -->
                                <div class="tooth-wrapper" data-tooth="31" onclick="toggleTooth3D(this)"><div class="tooth-face">31</div></div>
                                <div class="tooth-wrapper" data-tooth="32" onclick="toggleTooth3D(this)"><div class="tooth-face">32</div></div>
                                <div class="tooth-wrapper" data-tooth="33" onclick="toggleTooth3D(this)"><div class="tooth-face">33</div></div>
                                <div class="tooth-wrapper" data-tooth="34" onclick="toggleTooth3D(this)"><div class="tooth-face">34</div></div>
                                <div class="tooth-wrapper" data-tooth="35" onclick="toggleTooth3D(this)"><div class="tooth-face">35</div></div>
                                <div class="tooth-wrapper" data-tooth="36" onclick="toggleTooth3D(this)"><div class="tooth-face">36</div></div>
                                <div class="tooth-wrapper" data-tooth="37" onclick="toggleTooth3D(this)"><div class="tooth-face">37</div></div>
                                <div class="tooth-wrapper" data-tooth="38" onclick="toggleTooth3D(this)"><div class="tooth-face">38</div></div>
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
                    <button type="button" id="btn-back" onclick="goToStep(1)" class="hidden bg-gray-200 hover:bg-gray-300 text-slate-600 px-8 py-2.5 rounded-md font-medium transition-colors flex items-center gap-2">
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
        function goBack() {
            // Try to go back in browser history
            if (document.referrer.includes('staff-dashboard.php')) {
                window.history.back();
            } else {
                // If no history, redirect to staff dashboard
                window.location.href = 'staff-dashboard.php';
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
// Wrapper is the .tooth-wrapper div
// Logic: Default -> Selected (Blue) -> Attention (Yellow) -> Default

if (wrapper.classList.contains('selected')) {
// Currently Selected (Blue) -> Change to Attention (Yellow)
wrapper.classList.remove('selected');
wrapper.classList.add('attention');
} else if (wrapper.classList.contains('attention')) {
// Currently Attention (Yellow) -> Reset to Default
wrapper.classList.remove('attention');
} else {
// Currently Default -> Change to Selected (Blue)
wrapper.classList.add('selected');
}
}

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

    // Get selected teeth (Checking both old buttons and new 3D wrappers)
    const selectedTeeth = [];

    // Check 3D Wrappers
    document.querySelectorAll('.tooth-wrapper.selected, .tooth-wrapper.attention').forEach(wrapper => {
        selectedTeeth.push(wrapper.dataset.tooth);
    });

    // Check Old Buttons (if any remain, though fully replaced in step 5 HTML)
    document.querySelectorAll('.tooth-btn.bg-blue-200, .tooth-btn.bg-yellow-200').forEach(btn => {
        selectedTeeth.push(btn.dataset.tooth);
    });

    data.selectedTeeth = selectedTeeth;

    // Get medical conditions
    const medicalConditions = [];
    document.querySelectorAll('input[name="medicalConditions"]:checked').forEach(cb => {
        medicalConditions.push(cb.value);
    });
    data.medicalConditions = medicalConditions;

    const patientName = data.firstName && data.lastName ? (data.firstName + ' ' + data.lastName) : 'Patient';

    // Submit to server
    fetch('process_staff_admission.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Patient admitted and added to queue successfully!\n\nPatient: ' + patientName + '\n\nView in Queue or Dashboard');
            window.location.href = 'staff_queue.php';
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving. Please try again.');
    });
}
</script></body>
</html>
```