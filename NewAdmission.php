<?php
/**
 * New Admission - Dentist Version
 * Same as staff version but with dentist-specific navigation
 */
ob_start();
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: login.php');
    exit();
}

// Redirect staff to their dashboard
if (isset($_SESSION['role']) && $_SESSION['role'] === 'staff') {
    ob_end_clean();
    header('Location: staff-dashboard.php');
    exit();
}

// Redirect admin to their dashboard
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    ob_end_clean();
    header('Location: admin_dashboard.php');
    exit();
}

$username = $_SESSION['username'] ?? 'User';
$fullName = $_SESSION['full_name'] ?? 'Dr. Rex';

$inquiryData = null;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Admission - RF Dental Clinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .step-active { background: #3b82f6 !important; border-color: #3b82f6 !important; color: white !important; }
        .step-completed { background: #10b981 !important; border-color: #10b981 !important; color: white !important; }
        .dentist-badge { background: #d1fae5; color: #065f46; padding: 2px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
    </style>
</head>
<body class="bg-gray-50 text-slate-800 min-h-screen flex flex-col">

    <!-- Dentist Header Bar -->
    <header class="bg-white border-b border-gray-200 px-6 py-3">
        <div class="flex items-center justify-between max-w-7xl mx-auto">
            <div class="flex items-center gap-3">
                <img src="assets/images/Logo.png" alt="RF Logo" class="w-8 h-8 rounded-md">
                <div>
                    <h1 class="text-lg font-bold text-slate-800">RF Dental Clinic</h1>
                    <p class="text-xs text-slate-500">Dentist Portal</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <span class="dentist-badge">Dentist</span>
                <span class="text-sm font-medium text-slate-600"><?php echo htmlspecialchars($fullName); ?></span>
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 32 32'%3E%3Ccircle cx='16' cy='16' r='16' fill='%23e5e7eb'/%3E%3Ctext x='16' y='22' font-family='Arial' font-size='18' fill='%236b7280' text-anchor='middle'%3E👤%3C/text%3E%3C/svg%3E" alt="User" class="w-8 h-8">
            </div>
        </div>
    </header>

    <!-- Top Navigation - Goes back to Dentist Dashboard -->
    <div class="p-6">
        <a href="dashboard.php" class="inline-flex items-center text-slate-500 hover:text-blue-500 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            <span class="text-sm font-medium">Back to Dashboard</span>
        </a>
    </div>

    <?php if ($inquiryData): ?>
    <div class="max-w-7xl mx-auto w-full px-6">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4 flex items-center gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            <span class="text-sm text-blue-700">Forwarded from Inquiry: <strong><?php echo htmlspecialchars(trim(($inquiryData['first_name'] ?? '') . ' ' . ($inquiryData['middle_name'] ?? '') . ' ' . ($inquiryData['last_name'] ?? ''))); ?></strong> (<?php echo htmlspecialchars($inquiryData['source'] ?? ''); ?>)</span>
            <a href="inquiries.php" class="ml-auto text-blue-600 hover:text-blue-800 text-sm font-medium">View Original Inquiry</a>
        </div>
    </div>
    <?php endif; ?>

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
            
            <!-- Header Container -->
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
                                <input type="text" name="firstName" value="<?php echo htmlspecialchars($inquiryData['first_name'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="Juan">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-slate-600 mb-1">Middle Name</label>
                                <input type="text" name="middleName" value="<?php echo htmlspecialchars($inquiryData['middle_name'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-slate-600 mb-1">Last Name</label>
                                <input type="text" name="lastName" value="<?php echo htmlspecialchars($inquiryData['last_name'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="Dela Cruz">
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
                                <input type="tel" name="mobileNumber" value="<?php echo htmlspecialchars($inquiryData['contact_info'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
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
                                <label class="block text-sm font-medium text-slate-600 mb-1">Previous Treatments</label>
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

                <!-- STEP 5: Dental Chart -->
                <div id="step-5" class="hidden space-y-8 flex-1">
                    <section>
                        <div class="flex justify-between items-center mb-4">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-1">Dental Chart</h3>
                                <p class="text-sm text-slate-500">Click on teeth to mark them for treatment.</p>
                            </div>
                            
                            <!-- Tooth Type Toggle Buttons -->
                            <div class="flex gap-2">
                                <button type="button" id="btn-primary" onclick="setToothType('primary')" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors border-2 border-slate-300 bg-white text-slate-600">
                                    Primary/Minor
                                </button>
                                <button type="button" id="btn-permanent" onclick="setToothType('permanent')" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors border-2 border-blue-500 bg-blue-500 text-white">
                                    Permanent/Adult
                                </button>
                            </div>
                        </div>
                        
                        <!-- Permanent Teeth (Default) -->
                        <div id="permanentTeethSection">
                            <!-- Upper Teeth -->
                            <div class="mb-4">
                                <p class="text-xs font-medium text-slate-400 uppercase mb-2">Upper Teeth (18-11)</p>
                                <div class="flex flex-wrap gap-1 justify-center max-w-md mx-auto">
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="18" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">18</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="17" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">17</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="16" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">16</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="15" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">15</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="14" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">14</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="13" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">13</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="12" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">12</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="11" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">11</button>
                                </div>
                            </div>

                            <!-- Lower Teeth -->
                            <div class="mb-4">
                                <p class="text-xs font-medium text-slate-400 uppercase mb-2">Lower Teeth (41-48)</p>
                                <div class="flex flex-wrap gap-1 justify-center max-w-md mx-auto">
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="41" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">41</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="42" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">42</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="43" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">43</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="44" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">44</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="45" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">45</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="46" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">46</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="47" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">47</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="48" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">48</button>
                                </div>
                            </div>
                        </div>

                        <!-- Primary Teeth (Hidden by default) -->
                        <div id="primaryTeethSection" class="hidden">
                            <!-- Primary Upper Teeth -->
                            <div class="mb-4">
                                <p class="text-xs font-medium text-slate-400 uppercase mb-2">Primary Upper (55-51)</p>
                                <div class="flex flex-wrap gap-1 justify-center max-w-md mx-auto">
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="55" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">55</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="54" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">54</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="53" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">53</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="52" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">52</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="51" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">51</button>
                                </div>
                                <p class="text-xs font-medium text-slate-400 uppercase mb-2 mt-3">Primary Upper (61-65)</p>
                                <div class="flex flex-wrap gap-1 justify-center max-w-md mx-auto">
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="61" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">61</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="62" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">62</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="63" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">63</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="64" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">64</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="65" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">65</button>
                                </div>
                            </div>

                            <!-- Primary Lower Teeth -->
                            <div class="mb-4">
                                <p class="text-xs font-medium text-slate-400 uppercase mb-2">Primary Lower (71-75)</p>
                                <div class="flex flex-wrap gap-1 justify-center max-w-md mx-auto">
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="71" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">71</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="72" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">72</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="73" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">73</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="74" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">74</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="75" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">75</button>
                                </div>
                                <p class="text-xs font-medium text-slate-400 uppercase mb-2 mt-3">Primary Lower (81-85)</p>
                                <div class="flex flex-wrap gap-1 justify-center max-w-md mx-auto">
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="81" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">81</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="82" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">82</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="83" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">83</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="84" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">84</button>
                                    <button type="button" onclick="toggleTooth(this)" data-tooth="85" class="tooth-btn w-8 h-10 border-2 border-slate-300 rounded bg-white hover:bg-blue-100 text-xs">85</button>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Footer Actions -->
                <div class="mt-10 flex justify-between">
                    <button type="button" id="btn-back" onclick="goBack()" class="hidden bg-gray-200 hover:bg-gray-300 text-slate-600 px-8 py-2.5 rounded-md font-medium transition-colors flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        Back
                    </button>
                    
                    <button type="button" id="btn-next" onclick="goToStep(2)" class="bg-blue-500 hover:bg-blue-600 text-white px-8 py-2.5 rounded-md font-medium transition-colors flex items-center gap-2 ml-auto">
                        Next <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </button>

                    <button type="button" onclick="handleSubmit()" id="btn-submit" class="hidden bg-green-500 hover:bg-green-600 text-white px-8 py-2.5 rounded-md font-medium transition-colors flex items-center gap-2 ml-auto">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        Submit
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentStep = 1;

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

        // Go back one step in the form
        function goBack() {
            if (currentStep > 1) {
                goToStep(currentStep - 1);
            }
        }

        function updateNav(activeStep) {
            for(let i = 1; i <= 5; i++) {
                const navItem = document.getElementById('nav-step-' + i);
                const icon = document.getElementById('icon-step-' + i);
                const text = document.getElementById('text-step-' + i);
                const dot = document.getElementById('dot-step-' + i);

                if (i < activeStep) {
                    navItem.classList.remove('opacity-50');
                    icon.className = 'w-8 h-8 rounded-full flex items-center justify-center border-2 transition-colors duration-200 z-10 bg-green-500 border-green-500 text-white';
                    if(dot) dot.classList.remove('hidden');
                    if(!dot) icon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                    text.className = 'text-sm font-medium text-green-500 transition-colors duration-200';
                } else if (i === activeStep) {
                    navItem.classList.remove('opacity-50');
                    icon.className = 'w-8 h-8 rounded-full flex items-center justify-center border-2 transition-colors duration-200 z-10 bg-blue-500 border-blue-500 text-white';
                    if(dot) dot.classList.remove('hidden');
                    if(!dot) icon.innerHTML = '<div class="w-3 h-3 bg-white rounded-full"></div>';
                    text.className = 'text-sm font-medium text-blue-500 transition-colors duration-200';
                } else {
                    navItem.classList.add('opacity-50');
                    icon.className = 'w-8 h-8 rounded-full flex items-center justify-center border-2 transition-colors duration-200 z-10 bg-white border-slate-300 text-slate-400';
                    if(dot) dot.classList.add('hidden');
                    if(!dot) icon.innerHTML = '<span class="text-xs font-medium">' + i + '</span>';
                    text.className = 'text-sm font-medium text-slate-400 transition-colors duration-200';
                }
            }
        }

        function toggleTooth(btn) {
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
                primaryBtn.className = 'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors border-2 border-blue-500 bg-blue-500 text-white';
                permanentBtn.className = 'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors border-2 border-slate-300 bg-white text-slate-600';
            } else {
                primaryBtn.className = 'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors border-2 border-slate-300 bg-white text-slate-600';
                permanentBtn.className = 'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors border-2 border-blue-500 bg-blue-500 text-white';
            }
        }

        function updateToothVisibility() {
            const permanentSection = document.getElementById('permanentTeethSection');
            const primarySection = document.getElementById('primaryTeethSection');
            
            if (toothType === 'primary') {
                permanentSection.classList.add('hidden');
                primarySection.classList.remove('hidden');
            } else {
                permanentSection.classList.remove('hidden');
                primarySection.classList.add('hidden');
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
            // Also initialize on birthdate change
            const birthdateInput = document.querySelector('input[name="birthdate"]');
            if (birthdateInput) {
                birthdateInput.addEventListener('change', function() {
                    // Wait for age to be calculated
                    setTimeout(function() {
                        if (!toothTypeManuallySet) {
                            initializeToothTypeFromAge();
                        }
                    }, 100);
                });
            }
            // Initial check
            initializeToothTypeFromAge();
        });

        function handleSubmit() {
            const form = document.getElementById('admissionForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            const services = [];
            document.querySelectorAll('input[name="services[]"]:checked').forEach(cb => {
                services.push(cb.value);
            });
            data.services = services;
            
            const selectedTeeth = [];
            document.querySelectorAll('.tooth-btn.bg-blue-200, .tooth-btn.bg-yellow-200').forEach(btn => {
                selectedTeeth.push(btn.dataset.tooth);
            });
            data.selectedTeeth = selectedTeeth;
            
            const medicalConditions = [];
            document.querySelectorAll('input[name="medicalConditions"]:checked').forEach(cb => {
                medicalConditions.push(cb.value);
            });
            data.medicalConditions = medicalConditions;

            console.log('Form Data Submitted:', data);
            
            const patientName = data.firstName && data.lastName ? (data.firstName + ' ' + data.lastName) : 'Patient';
            
            if (confirm('Patient admission submitted successfully!\n\nPatient: ' + patientName + '\n\nReturn to Dashboard?')) {
                window.location.href = 'dashboard.php';
            }
        }

        // Auto-calculate age from birthdate
        document.addEventListener('DOMContentLoaded', function() {
            const birthdateInput = document.querySelector('input[name="birthdate"]');
            const ageInput = document.querySelector('input[name="age"]');
            
            if (birthdateInput && ageInput) {
                birthdateInput.addEventListener('change', function() {
                    const birthdate = this.value;
                    if (birthdate) {
                        const today = new Date();
                        const birthDate = new Date(birthdate);
                        let age = today.getFullYear() - birthDate.getFullYear();
                        const monthDiff = today.getMonth() - birthDate.getMonth();
                        
                        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                            age--;
                        }
                        
                        ageInput.value = age;
                    }
                });
            }
        });
    </script>
</body>
</html>
