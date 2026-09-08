<?php
/**
 * Quick Session - Simplified form for returning patients
 * Creates a new queue entry for existing patients
 * Standalone full-width page without sidebar navigation
 */

// Start session only if not already started
require_once __DIR__ . '/../../includes/session_bootstrap.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user role
$userRole = $_SESSION['role'] ?? 'staff';
$pageTitle = 'New Session';

// Determine the patient list page to redirect back to based on role
if ($userRole === 'admin') {
    $patientListPage = '../admin/admin_patients.php';
    $queuePage = '../admin/admin_queue.php';
} elseif ($userRole === 'dentist') {
    $patientListPage = '../dentist/dentist_patients.php';
    $queuePage = '../dentist/dentist_queue.php';
} else {
    $patientListPage = '../staff/staff_patient_records.php';
    $queuePage = '../staff/staff_queue.php';
}

try {
    require_once '../../config/database.php';
    
    // Get all services for dropdown with mode
    $stmt = $pdo->query("SELECT id, name, price, duration_minutes, mode FROM services WHERE is_active = 1 ORDER BY name");
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $services = [];
    }

    // Clinic info for queue ticket print
    $queueClinicName = 'RF Dental Clinic';
    $queueClinicAddress = '';
    try {
        $settingsRows = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('clinic_name', 'clinic_address')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $queueClinicName = $settingsRows['clinic_name'] ?? $queueClinicName;
        $queueClinicAddress = $settingsRows['clinic_address'] ?? '';
    } catch (Exception $e) {
        // best-effort
    }

// Get patient ID from URL
$patientId = $_GET['patient_id'] ?? 0;

if (!$patientId) {
    header('Location: ' . $patientListPage);
    exit;
}

try {
     // Get patient details with calculated age
     $stmt = $pdo->prepare("SELECT *, TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) AS age FROM patients WHERE id = ?");
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

        .vital-signs-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .success-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }

        .success-modal-overlay.active {
            display: flex;
        }

        .success-modal {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 440px;
            width: 90%;
            text-align: center;
            box-shadow: 0 25px 60px rgba(0,0,0,0.2);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .success-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #d1fae5;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .success-icon svg {
            width: 32px;
            height: 32px;
            color: #059669;
        }

        .success-modal h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .success-modal .queue-number {
            font-size: 2rem;
            font-weight: 800;
            color: #3b82f6;
            margin: 12px 0 24px;
        }

        .success-modal .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn-print-queue {
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid #3b82f6;
            background: white;
            color: #3b82f6;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-print-queue:hover {
            background: #eff6ff;
        }

        .btn-done {
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            background: #3b82f6;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-done:hover {
            background: #2563eb;
        }

        /* Queue ticket print */
        #queuePrintContainer { display: none; }
        @media print {
            @page { size: 58mm auto; margin: 0; }
            /* 58mm paper has ~48mm printable area - keep everything inside it */
            html, body { width: 48mm; height: auto; margin: 0 !important; padding: 0 !important; background: #fff !important; overflow: hidden !important; }
            body * { visibility: hidden !important; box-sizing: border-box !important; float: none !important; }
            #queuePrintContainer, #queuePrintContainer * {
                visibility: visible !important;
                font-family: 'Courier New', monospace !important;
                box-sizing: border-box !important;
                text-align: center !important;
            }
            #queuePrintContainer {
                display: block !important;
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                right: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 48mm !important;
                text-align: center !important;
            }
            /* Physical mm sizing - immune to driver DPI/scaling. No CSS borders (thermal drivers drop hairlines); separators are dash characters */
            .queue-ticket { width: 100% !important; padding: 0 !important; margin: 0 auto; border: none !important; text-align: center !important; }
            .queue-ticket .qt-line { font-size: 2.5mm; font-weight: 700; line-height: 1.1; white-space: nowrap; overflow: hidden; }
            .queue-ticket .qt-clinic { font-size: 3.6mm; font-weight: 700; text-transform: uppercase; white-space: normal; word-break: break-word; margin-top: 1mm; }
            .queue-ticket .qt-address { font-size: 2.2mm; margin-bottom: 1mm; white-space: normal; word-break: break-word; }
            .queue-ticket .qt-label { font-size: 2.8mm; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-top: 2mm; }
            .queue-ticket .qt-number { font-size: 15mm; font-weight: 700; line-height: 1.05; margin: 0; }
            .queue-ticket .qt-meta { font-size: 2.5mm; margin-top: 1mm; white-space: normal; word-break: break-word; }
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

            .vital-signs-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <?php require __DIR__ . '/../../includes/app_notice_modal_script.php'; ?>
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

        <?php
        // Build full name from separate fields
        $patientFullName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['middle_name'] ?? '') . ' ' . ($patient['last_name'] ?? '') . ' ' . ($patient['suffix'] ?? ''));
        ?>

        <!-- Page Header -->
        <div class="page-header">
            <h2>New Session - <?php echo htmlspecialchars($patientFullName ?: 'Patient'); ?> 
                <span style="font-size: 0.9em; color: #64748b; font-weight: normal;">
                    (<?php echo $patient['age'] ?? 'N/A'; ?> years old)
                </span>
            </h2>
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
                                <p><?php echo htmlspecialchars($patientFullName ?: 'N/A'); ?></p>
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
                            <select name="treatment_type" id="treatmentType" class="form-control" required onchange="handleServiceChange()">
                                <option value="">Select a service...</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?php echo htmlspecialchars($service['name']); ?>" data-id="<?php echo $service['id']; ?>">
                                        <?php echo htmlspecialchars($service['name']); ?> (₱<?php echo number_format($service['price'], 2); ?>)
                                    </option>
                                <?php endforeach; ?>
                                <option value="Other" data-id="">Other (Custom)</option>
                            </select>
                            <input type="hidden" name="service_id" id="serviceIdInput" value="">
                        </div>

                        <div class="form-group" id="customTreatmentGroup" style="display: none;">
                            <label class="form-label">Custom Treatment</label>
                            <input type="text" name="custom_treatment" class="form-control" placeholder="Enter treatment name">
                        </div>
                    </div>
                </div>

                <!-- Right Column: Vital Signs, Body Measurement & Notes -->
                <div>
                    <!-- Vital Signs (Optional) -->
                    <div class="card" style="margin-bottom: 24px;">
                        <h3 class="card-title">Vital Signs <span style="font-weight:400;color:#94a3b8;font-size:0.85rem;">(Optional)</span></h3>
                        <div class="vital-signs-grid">
                            <div class="form-group">
                                <label class="form-label">Blood Pressure</label>
                                <input type="text" name="blood_pressure" class="form-control" placeholder="e.g., 120/80 mmHg">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Heart Rate</label>
                                <input type="text" name="heart_rate" class="form-control" placeholder="e.g., 72 bpm">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Temperature</label>
                                <input type="text" name="temperature" class="form-control" placeholder="e.g., 36.5 C">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Oxygen Saturation</label>
                                <input type="text" name="oxygen_saturation" class="form-control" placeholder="e.g., 98%">
                            </div>
                        </div>
                    </div>

                    <!-- Body Measurement (Optional) -->
                    <div class="card" style="margin-bottom: 24px;">
                        <h3 class="card-title">Body Measurement <span style="font-weight:400;color:#94a3b8;font-size:0.85rem;">(Optional)</span></h3>
                        <div class="vital-signs-grid">
                            <div class="form-group">
                                <label class="form-label">Height</label>
                                <input type="text" name="height" class="form-control" placeholder="e.g., 170 cm">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Weight</label>
                                <input type="text" name="weight" class="form-control" placeholder="e.g., 65 kg">
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="card">
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
                <button type="submit" class="btn btn-primary" id="addToQueueButton">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Add to Queue
                </button>
            </div>
        </form>
    </div>

    <!-- Success Modal -->
    <div class="success-modal-overlay" id="successModal">
        <div class="success-modal">
            <div class="success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
            <h3>Patient added to queue successfully!</h3>
            <div class="queue-number" id="modalQueueNumber">#0</div>
            <div class="modal-buttons">
                <button type="button" class="btn-print-queue" onclick="printQueueNumber()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    Print Queue No.
                </button>
                <button type="button" class="btn-done" onclick="goToQueue()">Done</button>
            </div>
        </div>
    </div>

    <!-- Hidden queue ticket used for print -->
    <div id="queuePrintContainer"></div>

    <script>
        var _queuePage = '<?php echo $queuePage; ?>';
        var _queueId = 0;
        var queueClinicPrintInfo = {
            name: <?php echo json_encode($queueClinicName); ?>,
            address: <?php echo json_encode($queueClinicAddress); ?>
        };

        function escapeHtml(str) {
            if (!str) return '';
            var d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }

        // Handle service change - show/hide custom treatment input
        function handleServiceChange() {
            var select = document.getElementById('treatmentType');
            var customGroup = document.getElementById('customTreatmentGroup');
            var selectedOption = select.options[select.selectedIndex];
            var serviceName = select.value;
            var serviceId = selectedOption.dataset.id || '';
            document.getElementById('serviceIdInput').value = serviceId;

            if (serviceName === 'Other') {
                customGroup.style.display = 'block';
            } else {
                customGroup.style.display = 'none';
            }
        }

        // Form submission
        document.getElementById('quickSessionForm').addEventListener('submit', function(e) {
            e.preventDefault();

            var submitButton = document.getElementById('addToQueueButton');
            if (submitButton && submitButton.disabled) return;
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.dataset.originalText = submitButton.innerHTML;
                submitButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-dashoffset="32"><animate attributeName="stroke-dashoffset" values="32;0" dur="0.8s" repeatCount="indefinite"/></circle></svg> Adding...';
            }

            var formData = new FormData(this);
            var treatmentType = formData.get('treatment_type');
            var customTreatment = formData.get('custom_treatment');

            if (treatmentType === 'Other' && customTreatment) {
                formData.set('treatment_type', customTreatment);
            }

            fetch('process_quick_session.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    var modal = document.getElementById('successModal');
                    document.getElementById('modalQueueNumber').textContent = data.queue_label || ('#' + data.queue_number);
                    _queueId = data.queue_id;
                    if (data.already_in_queue) {
                        modal.querySelector('h3').textContent = 'Patient is already in the queue!';
                    } else {
                        modal.querySelector('h3').textContent = 'Patient added to queue successfully!';
                    }
                    modal.classList.add('active');
                } else {
                    alert('Error: ' + data.message);
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.innerHTML = submitButton.dataset.originalText || 'Add to Queue';
                    }
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = submitButton.dataset.originalText || 'Add to Queue';
                }
            });
        });

        function printQueueNumber() {
            var container = document.getElementById('queuePrintContainer');
            if (!container) return;

            var queueNum = document.getElementById('modalQueueNumber').textContent;
            var patientName = '<?php echo addslashes(htmlspecialchars($patientFullName)); ?>';
            var treatmentType = document.getElementById('treatmentType').value || 'General Checkup';
            var now = new Date();
            var dateStr = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
            var timeStr = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
            var number = String(queueNum).replace('#', '');
            var label = number ? '#' + number : '';
            var LINE = '----------------------------------------';

            container.innerHTML =
                '<div class="queue-ticket">' +
                    '<div class="qt-line">' + LINE + '</div>' +
                    '<div class="qt-clinic">' + escapeHtml(queueClinicPrintInfo.name || 'RF Dental Clinic') + '</div>' +
                    (queueClinicPrintInfo.address ? '<div class="qt-address">' + escapeHtml(queueClinicPrintInfo.address) + '</div>' : '') +
                    '<div class="qt-label">Queue Number</div>' +
                    '<div class="qt-number">' + escapeHtml(label) + '</div>' +
                    '<div class="qt-line">' + LINE + '</div>' +
                    '<div class="qt-meta">Patient: ' + escapeHtml(patientName) + '</div>' +
                    '<div class="qt-meta">Treatment: ' + escapeHtml(treatmentType) + '</div>' +
                    '<div class="qt-meta">Date: ' + escapeHtml(dateStr) + '  Time: ' + escapeHtml(timeStr) + '</div>' +
                    '<div class="qt-line">' + LINE + '</div>' +
                '</div>';

            // Move container to <body> so no transformed/offset ancestor shifts the ticket
            document.body.appendChild(container);
            window.print();
        }

        function goToQueue() {
            window.location.href = _queuePage + '?highlight=' + _queueId;
        }
    </script>
</body>
</html>
