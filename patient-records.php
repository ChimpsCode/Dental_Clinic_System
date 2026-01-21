<?php
$pageTitle = 'Patient Records';
require_once 'includes/staff_layout_start.php';

try {
    require_once 'config/database.php';
    
    // Get all patients with queue status
    $stmt = $pdo->query("
        SELECT p.*,
               (SELECT status FROM queue WHERE patient_id = p.id ORDER BY created_at DESC LIMIT 1) as queue_status,
               (SELECT treatment_type FROM queue WHERE patient_id = p.id ORDER BY created_at DESC LIMIT 1) as current_treatment
        FROM patients p 
        ORDER BY p.created_at DESC
    ");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate stats
    $totalPatients = count($patients);
    $inQueue = count(array_filter($patients, fn($p) => in_array($p['queue_status'] ?? '', ['waiting', 'in_procedure'])));
    $newThisMonth = count(array_filter($patients, fn($p) => !empty($p['created_at']) && strtotime($p['created_at']) > strtotime('-30 days')));
    
} catch (Exception $e) {
    $patients = [];
    $totalPatients = 0;
    $inQueue = 0;
    $newThisMonth = 0;
}
?>

<!-- Summary Stats Cards -->
<div class="summary-cards" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px;">
    <div class="summary-card" style="background: white; border-radius: 12px; padding: 20px; border: 1px solid #e5e7eb; display: flex; align-items: center; gap: 16px;">
        <div class="summary-icon" style="width: 48px; height: 48px; background: #dbeafe; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">👥</div>
        <div class="summary-info">
            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo $totalPatients; ?></h3>
            <p style="font-size: 0.875rem; color: #6b7280; margin: 0;">Total Patients</p>
        </div>
    </div>
    <div class="summary-card" style="background: white; border-radius: 12px; padding: 20px; border: 1px solid #e5e7eb; display: flex; align-items: center; gap: 16px;">
        <div class="summary-icon" style="width: 48px; height: 48px; background: #fef3c7; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">⏰</div>
        <div class="summary-info">
            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo $inQueue; ?></h3>
            <p style="font-size: 0.875rem; color: #6b7280; margin: 0;">In Queue</p>
        </div>
    </div>
    <div class="summary-card" style="background: white; border-radius: 12px; padding: 20px; border: 1px solid #e5e7eb; display: flex; align-items: center; gap: 16px;">
        <div class="summary-icon" style="width: 48px; height: 48px; background: #d1fae5; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">✓</div>
        <div class="summary-info">
            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo $newThisMonth; ?></h3>
            <p style="font-size: 0.875rem; color: #6b7280; margin: 0;">New This Month</p>
        </div>
    </div>
    <div class="summary-card" style="background: white; border-radius: 12px; padding: 20px; border: 1px solid #e5e7eb; display: flex; align-items: center; gap: 16px;">
        <div class="summary-icon" style="width: 48px; height: 48px; background: #fee2e2; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">📋</div>
        <div class="summary-info">
            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo $totalPatients; ?></h3>
            <p style="font-size: 0.875rem; color: #6b7280; margin: 0;">Registered Patients</p>
        </div>
    </div>
</div>

<!-- Search Toolbar -->
<div class="search-toolbar" style="background: white; padding: 16px 24px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e5e7eb; display: flex; gap: 16px; align-items: center;">
    <div class="search-input-container" style="position: relative; flex: 1;">
        <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #6b7280;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="m19.6 21l-6.3-6.3q-.75.6-1.725.95T9.5 16q-2.725 0-4.612-1.888T3 9.5t1.888-4.612T9.5 3t4.613 1.888T16 9.5q0 1.1-.35 2.075T14.7 13.3l6.3 6.3zM9.5 14q1.875 0 3.188-1.312T14 9.5t-1.312-3.187T9.5 5T6.313 6.313T5 9.5t1.313 3.188T9.5 14"/></svg>
        </span>
        <input type="text" id="searchInput" placeholder="Search patient name or phone..." class="search-input" style="width: 100%; padding: 12px 16px 12px 44px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; outline: none;">
    </div>
    <select id="statusFilter" style="padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; background: white; cursor: pointer;">
        <option value="">All Status</option>
        <option value="waiting">Waiting</option>
        <option value="in_procedure">In Procedure</option>
        <option value="completed">Completed</option>
        <option value="none">No Queue</option>
    </select>
</div>

<!-- Patient Records Table -->
<div class="section-card" style="background: white; border-radius: 12px; padding: 24px; border: 1px solid #e5e7eb;">
    <div class="section-title" style="font-size: 1.1rem; font-weight: 600; margin-bottom: 16px; color: #111827;">
        <span>All Patient Records</span>
    </div>
    <div style="overflow-x: auto;">
        <table class="data-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">Patient Name</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">Age/Gender</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">Contact</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">Current Treatment</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">Queue Status</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">Date Added</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">Actions</th>
                </tr>
            </thead>
            <tbody id="patientsTableBody">
                <?php if (empty($patients)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 60px; color: #6b7280;">
                            <p style="font-size: 1.1rem; margin-bottom: 8px;">No patient records found</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($patients as $patient): 
                        $status = $patient['queue_status'] ?? '';
                        $statusColors = [
                            'waiting' => '#fef3c7:#92400e',
                            'in_procedure' => '#dbeafe:#1d4ed8',
                            'completed' => '#d1fae5:#065f46',
                            'on_hold' => '#f3f4f6:#6b7280',
                            'cancelled' => '#fee2e2:#dc2626'
                        ];
                        $bgColor = '#f3f4f6';
                        $textColor = '#6b7280';
                        if (isset($statusColors[$status])) {
                            list($bg, $text) = explode(':', $statusColors[$status]);
                            $bgColor = $bg;
                            $textColor = $text;
                        }
                    ?>
                        <tr class="patient-row" 
                            data-name="<?php echo strtolower(htmlspecialchars($patient['full_name'] ?? 'Unknown')); ?>" 
                            data-phone="<?php echo strtolower(htmlspecialchars($patient['phone'] ?? '')); ?>"
                            data-status="<?php echo $status; ?>">
                            <td style="padding: 12px 16px; border-bottom: 1px solid #f3f4f6;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 40px; height: 40px; background: #e5e7eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #6b7280; font-weight: 600;">
                                        <?php echo strtoupper(substr($patient['full_name'] ?? 'U', 0, 1)); ?>
                                    </div>
                                    <span style="font-weight: 500; color: #111827;"><?php echo htmlspecialchars($patient['full_name'] ?? 'Unknown'); ?></span>
                                </div>
                            </td>
                            <td style="padding: 12px 16px; border-bottom: 1px solid #f3f4f6;">
                                <div style="font-size: 0.9rem;">
                                    <span style="font-weight: 500;"><?php echo $patient['age'] ?? 'N/A'; ?> yrs</span>
                                    <span style="color: #6b7280; margin-left: 8px;"><?php echo ucfirst($patient['gender'] ?? 'N/A'); ?></span>
                                </div>
                            </td>
                            <td style="padding: 12px 16px; border-bottom: 1px solid #f3f4f6;">
                                <div style="font-size: 0.85rem; color: #6b7280;">
                                    <div><?php echo htmlspecialchars($patient['phone'] ?: 'N/A'); ?></div>
                                    <div><?php echo htmlspecialchars($patient['email'] ?? ''); ?></div>
                                </div>
                            </td>
                            <td style="padding: 12px 16px; border-bottom: 1px solid #f3f4f6;">
                                <span style="font-weight: 500; color: #111827;"><?php echo htmlspecialchars($patient['current_treatment'] ?: 'None'); ?></span>
                            </td>
                            <td style="padding: 12px 16px; border-bottom: 1px solid #f3f4f6;">
                                <span style="background: <?php echo $bgColor; ?>; color: <?php echo $textColor; ?>; padding: 4px 12px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">
                                    <?php echo ucfirst(str_replace('_', ' ', $status ?: 'None')); ?>
                                </span>
                            </td>
                            <td style="padding: 12px 16px; border-bottom: 1px solid #f3f4f6;">
                                <span style="font-weight: 500;"><?php echo !empty($patient['created_at']) ? date('M d, Y', strtotime($patient['created_at'])) : 'N/A'; ?></span>
                            </td>
                            <td style="padding: 12px 16px; border-bottom: 1px solid #f3f4f6;">
                                <div class="patient-kebab-menu">
                                    <button class="patient-kebab-btn" data-patient-id="<?php echo $patient['id']; ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                            <circle cx="12" cy="6" r="2"/>
                                            <circle cx="12" cy="12" r="2"/>
                                            <circle cx="12" cy="18" r="2"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Full Screen Patient Record Modal -->
<div id="patientRecordModal" class="fullscreen-modal-overlay">
    <div class="fullscreen-modal-content">
        <div class="fullscreen-modal-header">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 600; margin: 0;">Patient Record Details</h2>
                <p id="staffModalPatientName" style="color: #6b7280; margin: 4px 0 0 0; font-size: 0.9rem;"></p>
            </div>
            <button onclick="closePatientRecordModal()" class="fullscreen-modal-close">&times;</button>
        </div>
        <div class="fullscreen-modal-body" id="patientRecordContent">
        </div>
    </div>
</div>

<style>
.fullscreen-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.7);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}
.fullscreen-modal-overlay.active {
    display: flex;
}
.fullscreen-modal-content {
    background: white;
    border-radius: 16px;
    width: 95%;
    max-width: 900px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}
.fullscreen-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px;
    border-bottom: 1px solid #e5e7eb;
    position: sticky;
    top: 0;
    background: white;
    z-index: 10;
}
.fullscreen-modal-close {
    background: none;
    border: none;
    font-size: 2rem;
    cursor: pointer;
    color: #6b7280;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s;
}
.fullscreen-modal-close:hover {
    background: #f3f4f6;
    color: #111827;
}
.fullscreen-modal-body {
    padding: 24px;
}
.patient-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.patient-info-item {
    background: #f9fafb;
    padding: 12px 16px;
    border-radius: 8px;
}
.patient-info-label {
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 4px;
}
.patient-info-value {
    font-size: 0.9rem;
    color: #111827;
    font-weight: 500;
}

/* Patient Kebab Menu Styles */
.patient-kebab-menu {
    position: relative;
    display: inline-block;
}

.patient-kebab-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    border-radius: 50%;
    color: #6b7280;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.patient-kebab-btn:hover {
    background-color: #f3f4f6;
    color: #374151;
}

.patient-kebab-btn.active {
    background-color: #e5e7eb;
    color: #111827;
}

.patient-kebab-dropdown-portal {
    display: none;
    position: fixed;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    min-width: 200px;
    max-width: 220px;
    width: auto;
    z-index: 99999;
    overflow: hidden;
}

.patient-kebab-dropdown-portal.show {
    display: block;
    animation: patientKebabFadeIn 0.15s ease;
}

@keyframes patientKebabFadeIn {
    from { opacity: 0; transform: scale(0.95) translateY(-4px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.patient-kebab-dropdown-portal a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    color: #374151;
    text-decoration: none;
    font-size: 0.875rem;
    transition: all 0.15s ease;
    cursor: pointer;
    white-space: nowrap;
}

.patient-kebab-dropdown-portal a:hover {
    background-color: #f9fafb;
    color: #111827;
}

.patient-kebab-dropdown-portal a svg {
    flex-shrink: 0;
}

.patient-kebab-dropdown-portal a:first-child {
    border-radius: 8px 8px 0 0;
}

.patient-kebab-dropdown-portal a:last-child {
    border-radius: 0 0 8px 8px;
}

.patient-kebab-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 99998;
}

.patient-kebab-backdrop.show {
    display: block;
}
</style>

<script>
document.getElementById('searchInput').addEventListener('input', filterPatients);
document.getElementById('statusFilter').addEventListener('change', filterPatients);

function filterPatients() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    
    document.querySelectorAll('.patient-row').forEach(row => {
        const matchSearch = !search || 
            row.dataset.name.includes(search) || 
            row.dataset.phone.includes(search);
        const matchStatus = !status || row.dataset.status === status;
        
        row.style.display = (matchSearch && matchStatus) ? '' : 'none';
    });
}

function viewPatientRecord(patientId) {
    fetch('patient_record_details.php?id=' + patientId)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const p = data.patient;
            const m = data.medical_history || {};
            const d = data.dental_history || {};
            const q = data.queue_item || {};
            
            const allergies = m.allergies || 'None';
            const medications = m.current_medications || 'None';
            const conditions = m.medical_conditions || 'None';
            
            document.getElementById('staffModalPatientName').innerText = p.full_name || 'Unknown';
            
            const hasMedicalAlert = allergies === 'Yes' || 
                                   conditions.toLowerCase().includes('diabetes') ||
                                   conditions.toLowerCase().includes('heart') ||
                                   conditions.toLowerCase().includes('blood pressure') ||
                                   conditions.toLowerCase().includes('asthma');
            
            document.getElementById('patientRecordContent').innerHTML = `
                <div class="patient-info-grid">
                    <div class="patient-info-item">
                        <div class="patient-info-label">Full Name</div>
                        <div class="patient-info-value">${p.full_name || 'N/A'}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Age</div>
                        <div class="patient-info-value">${p.age || 'N/A'} years</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Gender</div>
                        <div class="patient-info-value">${p.gender || 'N/A'}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Phone</div>
                        <div class="patient-info-value">${p.phone || 'N/A'}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Email</div>
                        <div class="patient-info-value">${p.email || 'N/A'}</div>
                    </div>
                    <div class="patient-info-item" style="grid-column: 1 / -1;">
                        <div class="patient-info-label">Address</div>
                        <div class="patient-info-value">${p.address || 'N/A'} ${p.city ? ', ' + p.city : ''} ${p.province ? ', ' + p.province : ''}</div>
                    </div>
                </div>

                ${hasMedicalAlert ? `
                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <div style="color: #dc2626; font-weight: 600; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">⚠️ Medical Alert - Important for Treatment</div>
                    <div class="medical-alert-grid">
                        <div class="patient-info-item">
                            <div class="patient-info-label">Allergies</div>
                            <div class="patient-info-value" style="${allergies === 'Yes' ? 'color: #dc2626;' : ''}">${allergies}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Diabetes</div>
                            <div class="patient-info-value" style="${conditions.toLowerCase().includes('diabetes') ? 'color: #dc2626;' : ''}">${conditions.toLowerCase().includes('diabetes') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Heart Disease</div>
                            <div class="patient-info-value" style="${conditions.toLowerCase().includes('heart') ? 'color: #dc2626;' : ''}">${conditions.toLowerCase().includes('heart') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">High Blood Pressure</div>
                            <div class="patient-info-value" style="${conditions.toLowerCase().includes('blood pressure') ? 'color: #dc2626;' : ''}">${conditions.toLowerCase().includes('blood pressure') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Asthma</div>
                            <div class="patient-info-value" style="${conditions.toLowerCase().includes('asthma') ? 'color: #dc2626;' : ''}">${conditions.toLowerCase().includes('asthma') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="patient-info-item" style="grid-column: 1 / -1;">
                            <div class="patient-info-label">Current Medications</div>
                            <div class="patient-info-value">${medications}</div>
                        </div>
                    </div>
                </div>
                ` : ''}

                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: #1e40af; margin-bottom: 16px;">📋 Service Requested</h3>
                    <div class="patient-info-grid">
                        <div class="patient-info-item">
                            <div class="patient-info-label">Treatment Type</div>
                            <div class="patient-info-value">${q.treatment_type || 'Consultation'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Selected Teeth</div>
                            <div class="patient-info-value">${q.teeth_numbers || 'None specified'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Status</div>
                            <div class="patient-info-value">
                                <span style="background: ${q.status === 'in_procedure' ? '#dcfce7' : q.status === 'waiting' ? '#fef3c7' : '#f3f4f6'}; color: ${q.status === 'in_procedure' ? '#15803d' : q.status === 'waiting' ? '#d97706' : '#6b7280'}; padding: 4px 12px; border-radius: 9999px; font-size: 0.85rem;">
                                    ${q.status ? q.status.replace('_', ' ').toUpperCase() : 'NONE'}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="background: #f9fafb; border-radius: 12px; padding: 20px;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: #374151; margin-bottom: 16px;">📜 Dental History</h3>
                    <div class="patient-info-grid">
                        <div class="patient-info-item">
                            <div class="patient-info-label">Previous Dentist</div>
                            <div class="patient-info-value">${d.previous_dentist || 'N/A'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Last Visit</div>
                            <div class="patient-info-value">${d.last_visit_date || 'N/A'}</div>
                        </div>
                        <div class="patient-info-item" style="grid-column: 1 / -1;">
                            <div class="patient-info-label">Current Complaints</div>
                            <div class="patient-info-value">${d.current_complaints || 'None'}</div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end;">
                    <button onclick="closePatientRecordModal()" class="btn-cancel" style="padding: 10px 24px; background: #f3f4f6; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">Close</button>
                </div>
            `;
            document.getElementById('patientRecordModal').classList.add('active');
        }
    });
}

function closePatientRecordModal() {
    document.getElementById('patientRecordModal').classList.remove('active');
}

document.getElementById('patientRecordModal').addEventListener('click', function(e) {
    if (e.target === this) closePatientRecordModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePatientRecordModal();
});

// Patient Kebab Menu Functions
let patientKebabDropdown = null;
let patientKebabBackdrop = null;
let patientActiveButton = null;

function createPatientKebabDropdown() {
    patientKebabDropdown = document.createElement('div');
    patientKebabDropdown.className = 'patient-kebab-dropdown-portal';
    patientKebabDropdown.id = 'patientKebabDropdownPortal';
    document.body.appendChild(patientKebabDropdown);

    patientKebabBackdrop = document.createElement('div');
    patientKebabBackdrop.className = 'patient-kebab-backdrop';
    patientKebabBackdrop.id = 'patientKebabBackdrop';
    document.body.appendChild(patientKebabBackdrop);

    patientKebabBackdrop.addEventListener('click', closePatientKebabDropdown);
}

function getPatientMenuItems(patientId) {
    return `
        <a href="javascript:void(0)" data-action="view" data-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            View
        </a>
        <a href="javascript:void(0)" data-action="appointment" data-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            Add Appointment
        </a>
        <a href="javascript:void(0)" data-action="session" data-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
            </svg>
            New Session
        </a>
    `;
}

function positionPatientKebabDropdown(button) {
    if (!patientKebabDropdown || !button) return;

    const rect = button.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    const padding = 15;
    const dropdownWidth = 200;
    
    let left = rect.right + 5;
    let top = rect.bottom + 8;

    if (left + dropdownWidth > viewportWidth - padding) {
        left = rect.left - dropdownWidth - 5;
    }
    
    if (left < padding) {
        left = padding;
    }
    
    if (top + 150 > viewportHeight - padding) {
        top = rect.top - 150 - 8;
    }
    
    if (top < padding) {
        top = padding;
    }

    patientKebabDropdown.style.left = left + 'px';
    patientKebabDropdown.style.top = top + 'px';
}

function openPatientKebabDropdown(button) {
    if (!patientKebabDropdown) {
        createPatientKebabDropdown();
    }

    const patientId = button.dataset.patientId;

    patientKebabDropdown.innerHTML = getPatientMenuItems(patientId);
    positionPatientKebabDropdown(button);

    patientKebabDropdown.classList.add('show');
    patientKebabBackdrop.classList.add('show');
    patientActiveButton = button;
    button.classList.add('active');

    patientKebabDropdown.addEventListener('click', handlePatientKebabClick);
}

function closePatientKebabDropdown() {
    if (patientKebabDropdown) {
        patientKebabDropdown.classList.remove('show');
        patientKebabDropdown.innerHTML = '';
    }
    if (patientKebabBackdrop) {
        patientKebabBackdrop.classList.remove('show');
    }
    if (patientActiveButton) {
        patientActiveButton.classList.remove('active');
        patientActiveButton = null;
    }
}

function handlePatientKebabClick(e) {
    const link = e.target.closest('a[data-action]');
    if (!link) return;

    e.preventDefault();
    e.stopPropagation();

    const action = link.dataset.action;
    const id = parseInt(link.dataset.id);

    closePatientKebabDropdown();

    switch(action) {
        case 'view':
            viewPatientRecord(id);
            break;
        case 'appointment':
            window.location.href = 'staff_appointments.php?patient_id=' + id;
            break;
        case 'session':
            window.location.href = 'staff_queue.php?patient_id=' + id;
            break;
    }
}

document.addEventListener('click', function(e) {
    const button = e.target.closest('.patient-kebab-btn');
    if (button) {
        e.preventDefault();
        e.stopPropagation();

        if (patientActiveButton === button && patientKebabDropdown && patientKebabDropdown.classList.contains('show')) {
            closePatientKebabDropdown();
        } else {
            if (patientActiveButton) {
                patientActiveButton.classList.remove('active');
            }
            openPatientKebabDropdown(button);
        }
        return;
    }

    if (!e.target.closest('.patient-kebab-dropdown-portal')) {
        closePatientKebabDropdown();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (patientKebabDropdown && patientKebabDropdown.classList.contains('show')) {
            closePatientKebabDropdown();
        }
    }
});

window.addEventListener('resize', function() {
    if (patientKebabDropdown && patientKebabDropdown.classList.contains('show') && patientActiveButton) {
        positionPatientKebabDropdown(patientActiveButton);
    }
});
</script>

<?php require_once 'includes/staff_layout_end.php'; ?>
