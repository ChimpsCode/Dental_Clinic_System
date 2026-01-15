<?php
/**
 * Patient Records - Dentist Version
 * Uses dentist layout with access to the same patient data as staff
 */

ob_start();
session_start();

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: login.php');
    exit();
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    ob_end_clean();
    header('Location: admin_dashboard.php');
    exit();
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'staff') {
    ob_end_clean();
    header('Location: patient-records.php');
    exit();
}

$username = $_SESSION['username'] ?? 'User';
$fullName = $_SESSION['full_name'] ?? 'Dr. Rex';

try {
    require_once 'config/database.php';
    $stmt = $pdo->query("SELECT * FROM patients ORDER BY created_at DESC LIMIT 100");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $patients = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records - RF Dental Clinic</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .search-toolbar {
            background: white;
            padding: 16px 24px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #e5e7eb;
            display: flex;
            gap: 16px;
            align-items: center;
        }
        .search-input-container { position: relative; flex: 1; }
        .search-input-container svg {
            position: absolute; left: 14px; top: 50%;
            transform: translateY(-50%); color: #6b7280;
        }
        .search-input {
            width: 100%; padding: 12px 16px 12px 44px;
            border: 1px solid #d1d5db; border-radius: 8px;
            font-size: 0.9rem; outline: none;
        }
        .search-input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .total-patients-box {
            padding: 10px 16px; background: #f9fafb;
            border-radius: 8px; font-weight: 600;
            color: #111827; border: 1px solid #e5e7eb; white-space: nowrap;
        }
        .section-card {
            background: white; border-radius: 12px;
            padding: 24px; border: 1px solid #e5e7eb;
        }
        .section-title {
            font-size: 1.1rem; font-weight: 600;
            margin-bottom: 16px; color: #111827;
        }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td {
            padding: 12px 16px; text-align: left;
            border-bottom: 1px solid #f3f4f6;
        }
        .data-table th {
            font-weight: 600; color: #6b7280;
            font-size: 0.85rem; text-transform: uppercase;
            background: #f9fafb;
        }
        .data-table tbody tr:hover { background-color: #f9fafb; }
        .patient-avatar {
            width: 40px; height: 40px; background: #e5e7eb;
            border-radius: 50%; display: flex;
            align-items: center; justify-content: center;
            color: #6b7280; font-weight: 600;
        }
        .patient-name { font-weight: 500; color: #111827; }
        .patient-actions { display: flex; gap: 8px; }
        .action-btn {
            background: transparent; border: 1px solid #d1d5db;
            border-radius: 6px; padding: 6px 10px;
            cursor: pointer; font-size: 1rem;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s; text-decoration: none;
        }
        .action-btn:hover { background-color: #f3f4f6; border-color: #9ca3af; }
        .dentist-badge {
            background: #d1fae5; color: #065f46;
            padding: 2px 10px; border-radius: 9999px;
            font-size: 0.75rem; font-weight: 600;
        }
    </style>
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
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M3 12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1zm0 8a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1z"/></svg></span>
                <span>Dashboard</span>
            </a>
            <a href="patient-records.php" class="nav-item active">
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3s1.34 3 3 3m-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5S5 6.34 5 8s1.34 3 3 3"/></svg></span>
                <span>Patient Records</span>
            </a>
            <a href="NewAdmission.php" class="nav-item">
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M15.25 18.75q.3 0 .525-.225T16 18t-.225-.525t-.525-.225t-.525.225T14.5 18t.225.525t.525.225m2.75 0q.3 0 .525-.225T18.75 18t-.225-.525T18 17.25t-.525.225t-.225.525t.525.225m2.75 0q.3 0 .525-.225T21.5 18t-.225-.525t-.525-.225t-.525.225T20 18t.225.525t.525.225M5 21q-.825 0-1.412-.587T3 19V5q0-.825.588-1.412T5 3h14q.825 0 1.413.588T21 5v6.7q-.475-.225-.975-.387T19 11.075V5H5v14h6.05q.075.55.238 1.05t.387.95zm0-3v1V5v6.075V11zm2-1h4.075q.075-.525.238-1.025t.362-.975H7zm0-4h6.1q.8-.75 1.788-1.25T17 11.075V11H7zm0-4h10V7H7zm11 14q-2.075 0-3.537-1.463T13 18t1.463-3.537T18 13t3.538 1.463T23 18t-1.463 3.538T18 23"/></svg></span>
                <span>New Admission</span>
            </a>
            <a href="appointments.php" class="nav-item">
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3m1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/></svg></span>
                <span>Appointments</span>
            </a>
        </nav>
        
        <div class="sidebar-footer" style="border-top: 1px solid #6b7280; margin-top: 10px; padding-left: 20px;">
            <a href="logout.php" class="nav-item">
                <span class="nav-item-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M5 21q-.825 0-1.412-.587T3 19V5q0-.825.588-1.412T5 3h6q.425 0 .713.288T12 4t-.288.713T11 5H5v14h6q.425 0 .713.288T12 20t-.288.713T11 21z"/></svg></span>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="top-header">
            <div class="header-left">
                <div class="header-title">
                    <h1>Patient Records</h1>
                    <p>Dentist Portal - RF Dental Clinic</p>
                </div>
            </div>
            <div class="header-right">
                <div class="user-profile">
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 32 32'%3E%3Ccircle cx='16' cy='16' r='16' fill='%23e5e7eb'/%3E%3Ctext x='16' y='22' font-family='Arial' font-size='18' fill='%236b7280' text-anchor='middle'%3E👤%3C/text%3E%3C/svg%3E" alt="User">
                    <span class="user-name"><?php echo htmlspecialchars($fullName); ?></span>
                    <span class="dentist-badge">Dentist</span>
                </div>
            </div>
        </header>

        <div class="content-area">
            <div class="content-main">
                <!-- Search Toolbar -->
                <div class="search-toolbar">
                    <div class="search-input-container">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="m19.6 21l-6.3-6.3q-.75.6-1.725.95T9.5 16q-2.725 0-4.612-1.888T3 9.5t1.888-4.612T9.5 3t4.613 1.888T16 9.5q0 1.1-.35 2.075T14.7 13.3l6.3 6.3zM9.5 14q1.875 0 3.188-1.312T14 9.5t-1.312-3.187T9.5 5T6.313 6.313T5 9.5t1.313 3.188T9.5 14"/></svg>
                        <input type="text" id="searchInput" placeholder="Search patient name..." class="search-input">
                    </div>
                    <div class="total-patients-box">
                        Total: <?php echo count($patients); ?> Patients
                    </div>
                </div>

                <!-- Patient Records Table -->
                <div class="section-card">
                    <div class="section-title">
                        <span>All Patient Records</span>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Patient Name</th>
                                    <th>Contact</th>
                                    <th>Date Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="patientsTableBody">
                                <?php if (empty($patients)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; padding: 60px; color: #6b7280;">
                                            <p style="font-size: 1.1rem; margin-bottom: 8px;">No patient records found</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($patients as $patient): ?>
                                        <tr class="patient-row" data-name="<?php echo strtolower(htmlspecialchars($patient['full_name'] ?? 'Unknown')); ?>">
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <div class="patient-avatar">
                                                        <?php echo strtoupper(substr($patient['full_name'] ?? 'U', 0, 1)); ?>
                                                    </div>
                                                    <span class="patient-name"><?php echo htmlspecialchars($patient['full_name'] ?? 'Unknown'); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></td>
                                            <td><?php echo isset($patient['created_at']) ? date('M d, Y', strtotime($patient['created_at'])) : 'N/A'; ?></td>
                                            <td>
                                                <div class="patient-actions">
                                                    <button onclick="viewPatientRecord(<?php echo $patient['id']; ?>)" class="action-btn icon" title="View">👁️</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Patient Record Modal -->
    <div id="patientRecordModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div class="modal" style="background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 700px; max-height: 85vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 1.25rem; font-weight: 600; margin: 0;">Patient Record</h2>
                <button onclick="closePatientRecordModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">×</button>
            </div>
            <div id="patientRecordContent"></div>
        </div>
    </div>

    <script>
        document.getElementById('searchInput').addEventListener('input', function() {
            const search = this.value.toLowerCase();
            document.querySelectorAll('.patient-row').forEach(row => {
                const match = row.dataset.name.includes(search);
                row.style.display = match ? '' : 'none';
            });
        });

        function viewPatientRecord(patientId) {
            fetch('patient_record_details.php?id=' + patientId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const p = data.patient;
                    const m = data.medical_history || {};
                    const d = data.dental_history || {};
                    
                    const allergies = m.allergies || 'None';
                    const medications = m.current_medications || 'None';
                    const conditions = m.medical_conditions || 'None';
                    
                    document.getElementById('patientRecordContent').innerHTML = `
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <h3 style="font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 12px;">Personal Information</h3>
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <div><span style="color: #6b7280;">Full Name:</span> <span style="font-weight: 500;">${p.full_name || 'N/A'}</span></div>
                                    <div><span style="color: #6b7280;">Age:</span> <span style="font-weight: 500;">${p.age || 'N/A'} years</span></div>
                                    <div><span style="color: #6b7280;">Gender:</span> <span style="font-weight: 500;">${p.gender || 'N/A'}</span></div>
                                    <div><span style="color: #6b7280;">Phone:</span> <span style="font-weight: 500;">${p.phone || 'N/A'}</span></div>
                                    <div><span style="color: #6b7280;">Email:</span> <span style="font-weight: 500;">${p.email || 'N/A'}</span></div>
                                </div>
                            </div>
                            <div>
                                <h3 style="font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 12px;">Service Requested</h3>
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <div><span style="color: #6b7280;">Treatment:</span> <span style="font-weight: 500;">${data.queue_item?.treatment_type || 'N/A'}</span></div>
                                    <div><span style="color: #6b7280;">Teeth:</span> <span style="font-weight: 500;">${data.queue_item?.teeth_numbers || 'N/A'}</span></div>
                                </div>
                            </div>
                        </div>
                        <div style="margin-top: 20px;">
                            <h3 style="font-size: 0.875rem; font-weight: 600; color: #dc2626; margin-bottom: 12px;">⚠️ Medical History</h3>
                            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                    <div>
                                        <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 4px;">Allergies</div>
                                        <div style="font-weight: 500; ${allergies === 'Yes' ? 'color: #dc2626;' : ''}">${allergies}</div>
                                    </div>
                                    <div>
                                        <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 4px;">Conditions</div>
                                        <div style="font-weight: 500;">${conditions}</div>
                                    </div>
                                    <div style="grid-column: 1 / -1;">
                                        <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 4px;">Current Medications</div>
                                        <div style="font-weight: 500;">${medications}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end;">
                            <button onclick="closePatientRecordModal()" class="btn-cancel" style="padding: 10px 24px;">Close</button>
                        </div>
                    `;
                    document.getElementById('patientRecordModal').style.display = 'flex';
                }
            });
        }

        function closePatientRecordModal() {
            document.getElementById('patientRecordModal').style.display = 'none';
        }

        document.getElementById('patientRecordModal').addEventListener('click', function(e) {
            if (e.target === this) closePatientRecordModal();
        });
    </script>
</body>
</html>
