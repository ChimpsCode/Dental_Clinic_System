<?php
/**
 * Quick Session - Simplified form for returning patients
 * Creates a new queue entry for existing patients
 * Standalone full-width page without sidebar navigation
 */

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user role
$userRole = $_SESSION['role'] ?? 'staff';
$pageTitle = 'New Session';

// Determine the patient list page to redirect back to
$patientListPage = ($userRole === 'admin') ? 'admin_patients.php' : 'patient-records.php';
$queuePage = ($userRole === 'admin') ? 'admin_queue.php' : 'staff_queue.php';

try {
    require_once 'config/database.php';
    
    // Get all services for dropdown
    $stmt = $pdo->query("SELECT id, name, price, duration_minutes FROM services WHERE is_active = 1 ORDER BY name");
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $services = [];
}

// Get patient ID from URL
$patientId = $_GET['patient_id'] ?? 0;

if (!$patientId) {
    header('Location: ' . $patientListPage);
    exit;
}

try {
    // Get patient details
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        header('Location: ' . $patientListPage);
        exit;
    }
} catch (Exception $e) {
    $patient = null;
    header('Location: ' . $patientListPage);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - RF Dental Clinic</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            color: #1e293b;
        }

        .page-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px 32px;
            min-height: 100vh;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 8px;
            background: white;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
            margin-bottom: 24px;
        }

        .back-link:hover {
            color: #2563eb;
            border-color: #2563eb;
            background: #eff6ff;
        }

        .page-header {
            margin-bottom: 32px;
        }

        .page-header h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: #64748b;
            font-size: 0.95rem;
        }

        .form-container {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 32px;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f1f5f9;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .info-item label {
            display: block;
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }

        .info-item p {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        select.form-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 44px;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .dental-chart-container {
            perspective: 1000px;
            padding: 24px;
        }

        .dental-chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .dental-chart-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #0f172a;
        }

        .tooth-type-toggle {
            display: flex;
            gap: 8px;
        }

        .tooth-type-btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            background: white;
            border: 2px solid #e2e8f0;
            color: #64748b;
        }

        .tooth-type-btn.active {
            background: #3b82f6;
            border-color: #3b82f6;
            color: white;
        }

        .tooth-type-btn:not(.active):hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }

        .arch-container {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-bottom: 32px;
            transform-style: preserve-3d;
        }

        .arch-label {
            text-align: center;
            font-size: 0.7rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 12px;
        }

        .tooth-wrapper {
            width: 34px;
            height: 46px;
            position: relative;
            transform-style: preserve-3d;
            transform: rotateX(-20deg) rotateY(0deg);
            transition: transform 0.3s ease;
            cursor: pointer;
        }

        .tooth-wrapper:hover {
            transform: rotateX(0deg) scale(1.1);
            z-index: 10;
        }

        .tooth-face {
            position: absolute;
            width: 100%;
            height: 100%;
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 6px 6px 12px 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.7rem;
            color: #64748b;
            box-shadow: 
                0 4px 0 #94a3b8,
                0 5px 6px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }

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

        .tooth-wrapper.selected .tooth-face {
            background: #bfdbfe;
            border-color: #3b82f6;
            color: #1e3a8a;
            box-shadow: 
                0 4px 0 #3b82f6,
                0 5px 10px rgba(59, 130, 246, 0.3);
            transform: translateY(-2px);
        }

        .tooth-wrapper.selected .tooth-face::after {
            background: #3b82f6;
        }

        .tooth-wrapper.attention .tooth-face {
            background: #fef08a;
            border-color: #eab308;
            color: #713f12;
            box-shadow: 
                0 4px 0 #eab308,
                0 5px 10px rgba(234, 179, 8, 0.3);
            transform: translateY(-2px);
        }

        .tooth-wrapper.attention .tooth-face::after {
            background: #eab308;
        }

        .legend {
            display: flex;
            justify-content: center;
            gap: 32px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
            margin-top: 24px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .legend-color {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            border: 1px solid;
        }

        .legend-text {
            font-size: 0.8rem;
            color: #64748b;
        }

        .hidden {
            display: none !important;
        }

        .button-row {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #f1f5f9;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-cancel {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        @media (max-width: 1024px) {
            .form-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .page-wrapper {
                padding: 16px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .arch-container {
                gap: 4px;
            }

            .tooth-wrapper {
                width: 28px;
                height: 40px;
            }

            .legend {
                flex-wrap: wrap;
                gap: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- Back Link -->
        <a href="<?php echo $patientListPage; ?>" class="back-link">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
            Back to Patients
        </a>

        <!-- Page Header -->
        <div class="page-header">
            <h2>New Session - <?php echo htmlspecialchars($patient['full_name'] ?? 'Patient'); ?></h2>
            <p class="page-subtitle">Quick queue entry for returning patient</p>
        </div>

        <!-- Form -->
        <form id="quickSessionForm">
            <input type="hidden" name="patient_id" value="<?php echo $patientId; ?>">
            
            <div class="form-container">
                <!-- Left Column: Patient Info & Service -->
                <div>
                    <!-- Patient Info Card -->
                    <div class="card" style="margin-bottom: 24px;">
                        <h3 class="card-title">Patient Information</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Full Name</label>
                                <p><?php echo htmlspecialchars($patient['full_name'] ?? 'N/A'); ?></p>
                            </div>
                            <div class="info-item">
                                <label>Age</label>
                                <p><?php echo $patient['age'] ?? 'N/A'; ?> years old</p>
                            </div>
                            <div class="info-item">
                                <label>Gender</label>
                                <p><?php echo ucfirst($patient['gender'] ?? 'N/A'); ?></p>
                            </div>
                            <div class="info-item">
                                <label>Contact</label>
                                <p><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Service Selection -->
                    <div class="card">
                        <h3 class="card-title">Service</h3>
                        
                        <div class="form-group">
                            <label class="form-label">Treatment Type *</label>
                            <select name="treatment_type" id="treatmentType" class="form-control" required>
                                <option value="">Select a service...</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?php echo htmlspecialchars($service['name']); ?>">
                                        <?php echo htmlspecialchars($service['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="Other">Other (Custom)</option>
                            </select>
                        </div>

                        <div class="form-group" id="customTreatmentGroup" style="display: none;">
                            <label class="form-label">Custom Treatment</label>
                            <input type="text" name="custom_treatment" class="form-control" placeholder="Enter treatment name">
                        </div>
                    </div>
                </div>

                <!-- Right Column: Dental Chart & Notes -->
                <div>
                    <!-- Dental Chart -->
                    <div class="card dental-chart-container">
                        <div class="dental-chart-header">
                            <h3>Dental Chart</h3>
                            
                            <!-- Tooth Type Toggle -->
                            <div class="tooth-type-toggle">
                                <button type="button" id="btn-primary" onclick="setToothType('primary')" class="tooth-type-btn">Primary</button>
                                <button type="button" id="btn-permanent" onclick="setToothType('permanent')" class="tooth-type-btn active">Adult</button>
                            </div>
                        </div>
                        
                        <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 20px;">Click on affected teeth to mark them for treatment. Click again to mark for attention.</p>

                        <!-- Permanent Teeth (Default) -->
                        <div id="permanentTeethSection">
                            <!-- UPPER ARCH -->
                            <div class="arch-label">Upper Arch (Maxilla)</div>
                            <div class="arch-container">
                                <?php for ($i = 18; $i >= 11; $i--): ?>
                                    <div class="tooth-wrapper" data-tooth="<?php echo $i; ?>" onclick="toggleTooth3D(this)">
                                        <div class="tooth-face"><?php echo $i; ?></div>
                                    </div>
                                <?php endfor; ?>
                                <div style="width: 12px;"></div>
                                <?php for ($i = 21; $i <= 28; $i++): ?>
                                    <div class="tooth-wrapper" data-tooth="<?php echo $i; ?>" onclick="toggleTooth3D(this)">
                                        <div class="tooth-face"><?php echo $i; ?></div>
                                    </div>
                                <?php endfor; ?>
                            </div>

                            <!-- LOWER ARCH -->
                            <div class="arch-label">Lower Arch (Mandible)</div>
                            <div class="arch-container">
                                <?php for ($i = 48; $i >= 41; $i--): ?>
                                    <div class="tooth-wrapper" data-tooth="<?php echo $i; ?>" onclick="toggleTooth3D(this)">
                                        <div class="tooth-face"><?php echo $i; ?></div>
                                    </div>
                                <?php endfor; ?>
                                <div style="width: 12px;"></div>
                                <?php for ($i = 31; $i <= 38; $i++): ?>
                                    <div class="tooth-wrapper" data-tooth="<?php echo $i; ?>" onclick="toggleTooth3D(this)">
                                        <div class="tooth-face"><?php echo $i; ?></div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <!-- Primary Teeth (Hidden by default) -->
                        <div id="primaryTeethSection" class="hidden">
                            <div class="arch-label">Primary Upper Arch</div>
                            <div class="arch-container">
                                <?php for ($i = 55; $i >= 51; $i--): ?>
                                    <div class="tooth-wrapper" data-tooth="<?php echo $i; ?>" onclick="toggleTooth3D(this)">
                                        <div class="tooth-face"><?php echo $i; ?></div>
                                    </div>
                                <?php endfor; ?>
                                <div style="width: 12px;"></div>
                                <?php for ($i = 61; $i <= 65; $i++): ?>
                                    <div class="tooth-wrapper" data-tooth="<?php echo $i; ?>" onclick="toggleTooth3D(this)">
                                        <div class="tooth-face"><?php echo $i; ?></div>
                                    </div>
                                <?php endfor; ?>
                            </div>

                            <div class="arch-label">Primary Lower Arch</div>
                            <div class="arch-container">
                                <?php for ($i = 85; $i >= 81; $i--): ?>
                                    <div class="tooth-wrapper" data-tooth="<?php echo $i; ?>" onclick="toggleTooth3D(this)">
                                        <div class="tooth-face"><?php echo $i; ?></div>
                                    </div>
                                <?php endfor; ?>
                                <div style="width: 12px;"></div>
                                <?php for ($i = 71; $i <= 75; $i++): ?>
                                    <div class="tooth-wrapper" data-tooth="<?php echo $i; ?>" onclick="toggleTooth3D(this)">
                                        <div class="tooth-face"><?php echo $i; ?></div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <input type="hidden" name="teeth_numbers" id="teethNumbers" value="">

                        <!-- Legend -->
                        <div class="legend">
                            <div class="legend-item">
                                <div class="legend-color" style="background: white; border-color: #cbd5e1; box-shadow: 0 2px 0 #94a3b8;"></div>
                                <span class="legend-text">Healthy</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background: #bfdbfe; border-color: #3b82f6;"></div>
                                <span class="legend-text">Selected</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background: #fef08a; border-color: #eab308;"></div>
                                <span class="legend-text">Attention</span>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="card" style="margin-top: 24px;">
                        <h3 class="card-title">Notes & Complaints</h3>
                        
                        <div class="form-group">
                            <label class="form-label">Chief Complaint</label>
                            <textarea name="complaint" rows="3" class="form-control" placeholder="e.g., Tooth pain, Regular checkup, Cleaning"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Additional Notes</label>
                            <textarea name="notes" rows="3" class="form-control" placeholder="Any additional information..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="button-row">
                <button type="button" onclick="window.location.href='<?php echo $patientListPage; ?>'" class="btn btn-cancel">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Add to Queue
                </button>
            </div>
        </form>
    </div>

    <script>
        // Tooth type toggle
        let toothType = 'permanent';
        
        function setToothType(type) {
            toothType = type;
            updateToothTypeButtons();
            updateToothVisibility();
        }
        
        function updateToothTypeButtons() {
            const primaryBtn = document.getElementById('btn-primary');
            const permanentBtn = document.getElementById('btn-permanent');
            
            if (toothType === 'primary') {
                primaryBtn.classList.add('active');
                permanentBtn.classList.remove('active');
            } else {
                permanentBtn.classList.add('active');
                primaryBtn.classList.remove('active');
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
        
        function toggleTooth3D(wrapper) {
            if (wrapper.classList.contains('selected')) {
                wrapper.classList.remove('selected');
                wrapper.classList.add('attention');
            } else if (wrapper.classList.contains('attention')) {
                wrapper.classList.remove('attention');
            } else {
                wrapper.classList.add('selected');
            }
            updateTeethNumbers();
        }
        
        function updateTeethNumbers() {
            const selected = document.querySelectorAll('.tooth-wrapper.selected, .tooth-wrapper.attention');
            const teeth = Array.from(selected).map(wrapper => wrapper.dataset.tooth);
            document.getElementById('teethNumbers').value = teeth.join(',');
        }
        
        // Service selection
        document.getElementById('treatmentType').addEventListener('change', function() {
            const customGroup = document.getElementById('customTreatmentGroup');
            if (this.value === 'Other') {
                customGroup.style.display = 'block';
            } else {
                customGroup.style.display = 'none';
            }
        });
        
        // Form submission
        document.getElementById('quickSessionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const treatmentType = formData.get('treatment_type');
            const customTreatment = formData.get('custom_treatment');
            
            if (treatmentType === 'Other' && customTreatment) {
                formData.set('treatment_type', customTreatment);
            }
            
            fetch('process_quick_session.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Patient added to queue successfully!\n\nQueue #: ' + data.queue_number + '\n\nView in Queue?');
                    window.location.href = '<?php echo $queuePage; ?>?highlight=' + data.queue_id;
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });
        
        // Auto-detect tooth type based on age
        document.addEventListener('DOMContentLoaded', function() {
            const age = <?php echo $patient['age'] ?? 0; ?>;
            if (age < 12) {
                setToothType('primary');
            } else {
                setToothType('permanent');
            }
        });
    </script>
</body>
</html>
