<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Admission - RF Dental Clinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-slate-800 min-h-screen flex flex-col">

    <!-- Top Navigation -->
    <div class="p-6">
        <a href="dashboard.php" class="inline-flex items-center text-slate-500 hover:text-blue-500 transition-colors">
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
                <h1 class="text-xl font-bold text-slate-800">RF Dental Clinic</h1>
            </div>

            <!-- Vertical Stepper -->
            <div class="relative">
                <!-- Vertical Line -->
                <div class="absolute left-4 top-4 bottom-4 w-0.5 bg-slate-200 -z-10"></div>

                <nav class="space-y-8">
                    <!-- Step 1: Active -->
                    <div class="flex items-center gap-4 relative pl-0">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center border-2 transition-colors duration-200 z-10 bg-blue-500 border-blue-500 text-white">
                            <div class="w-3 h-3 bg-white rounded-full"></div>
                        </div>
                        <span class="text-sm font-medium text-blue-500 transition-colors duration-200">Patient Information</span>
                    </div>

                    <!-- Step 2: Inactive -->
                    <div class="flex items-center gap-4 relative pl-0">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center border-2 transition-colors duration-200 z-10 bg-white border-slate-300 text-slate-400">
                            <span class="text-xs font-medium">2</span>
                        </div>
                        <span class="text-sm font-medium text-slate-400 transition-colors duration-200">Dental History</span>
                    </div>

                    <!-- Step 3: Inactive -->
                    <div class="flex items-center gap-4 relative pl-0">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center border-2 transition-colors duration-200 z-10 bg-white border-slate-300 text-slate-400">
                            <span class="text-xs font-medium">3</span>
                        </div>
                        <span class="text-sm font-medium text-slate-400 transition-colors duration-200">Medical History</span>
                    </div>

                    <!-- Step 4: Inactive -->
                    <div class="flex items-center gap-4 relative pl-0">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center border-2 transition-colors duration-200 z-10 bg-white border-slate-300 text-slate-400">
                            <span class="text-xs font-medium">4</span>
                        </div>
                        <span class="text-sm font-medium text-slate-400 transition-colors duration-200">Services</span>
                    </div>

                    <!-- Step 5: Inactive -->
                    <div class="flex items-center gap-4 relative pl-0">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center border-2 transition-colors duration-200 z-10 bg-white border-slate-300 text-slate-400">
                            <span class="text-xs font-medium">5</span>
                        </div>
                        <span class="text-sm font-medium text-slate-400 transition-colors duration-200">Dental Chart</span>
                    </div>
                </nav>
            </div>
        </div>

        <!-- Right Content Area (Form) -->
        <div class="w-full md:w-3/4 bg-white rounded-xl shadow-sm border border-slate-100 p-8 md:p-10 flex flex-col">
            
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-slate-800">Patient Information</h2>
                <p class="text-slate-500 text-sm mt-1">Please fill in the patient's details below.</p>
            </div>

            <form id="admissionForm" class="flex flex-col flex-1">
                <div class="space-y-8 flex-1">
                    
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

                <!-- Footer Actions -->
                <div class="mt-10 flex justify-end">
                    <button type="button" onclick="handleSubmit()" class="bg-blue-500 hover:bg-blue-600 text-white px-8 py-2.5 rounded-md font-medium transition-colors flex items-center gap-2">
                        Next <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function handleSubmit() {
            const form = document.getElementById('admissionForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            console.log('Form Data Submitted:', data);
            // Mock submission logic
        }
    </script>
</body>
</html>
