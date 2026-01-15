<?php
$pageTitle = 'Patient Records';
require_once 'includes/dentist_layout_start.php';

try {
    require_once 'config/database.php';
    
    // Get all patients with their latest queue status
    $stmt = $pdo->query("
        SELECT p.*, 
               (SELECT status FROM queue WHERE patient_id = p.id ORDER BY created_at DESC LIMIT 1) as queue_status,
               (SELECT treatment_type FROM queue WHERE patient_id = p.id ORDER BY created_at DESC LIMIT 1) as current_treatment
        FROM patients p 
        ORDER BY p.created_at DESC
    ");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $patients = [];
}
?>

<!-- Summary Stats -->
<div class="summary-cards">
    <div class="summary-card">
        <div class="summary-icon blue" style="background: #e0f2fe; color: #0284c7;">👥</div>
        <div class="summary-info">
            <h3><?php echo count($patients); ?></h3>
            <p>Total Patients</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon yellow">⏰</div>
        <div class="summary-info">
            <h3><?php echo count(array_filter($patients, fn($p) => in_array($p['queue_status'] ?? '', ['waiting', 'in_procedure']))); ?></h3>
            <p>In Queue</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon green">✓</div>
        <div class="summary-info">
            <h3><?php echo count(array_filter($patients, fn($p) => !empty($p['created_at']) && strtotime($p['created_at']) > strtotime('-30 days'))); ?></h3>
            <p>New This Month</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon red" style="background: #fee2e2; color: #dc2626;">📋</div>
        <div class="summary-info">
            <h3><?php echo count($patients); ?></h3>
            <p>Registered Patients</p>
        </div>
    </div>
</div>

<!-- Search & Filters -->
<div class="search-filters">
    <input type="text" id="searchInput" placeholder="Search by name or phone..." class="search-input" style="flex: 1;">
    <select id="statusFilter" class="filter-select">
        <option value="">All Status</option>
        <option value="waiting">Waiting</option>
        <option value="in_procedure">In Procedure</option>
        <option value="completed">Completed</option>
    </select>
    <select id="sortFilter" class="filter-select">
        <option value="newest">Newest First</option>
        <option value="name">Name (A-Z)</option>
    </select>
</div>

<!-- Patient Records Table -->
<div class="section-card">
    <div class="section-title">
        <span>Patient Records</span>
    </div>

    <?php if (empty($patients)): ?>
        <div class="empty-state" style="text-align: center; padding: 60px 20px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5" style="margin: 0 auto 16px; display: block;">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <h3 style="color: #111827; font-size: 1.5rem; margin-bottom: 10px;">No Patients Found</h3>
            <p style="color: #6b7280; font-size: 0.9rem;">Patients will appear here once they are registered</p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table" id="patientsTable">
                <thead>
                    <tr>
                        <th>Patient Name</th>
                        <th>Age/Gender</th>
                        <th>Contact</th>
                        <th>Current Treatment</th>
                        <th>Queue Status</th>
                        <th>Date Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $patient): ?>
                        <tr class="patient-row" 
                            data-name="<?php echo strtolower(htmlspecialchars($patient['full_name'] ?? '')); ?>" 
                            data-phone="<?php echo strtolower(htmlspecialchars($patient['phone'] ?? '')); ?>" 
                            data-status="<?php echo $patient['queue_status'] ?? ''; ?>">
                            <td>
                                <div class="patient-name" style="font-weight: 600;"><?php echo htmlspecialchars($patient['full_name'] ?? 'Unknown'); ?></div>
                            </td>
                            <td>
                                <div style="font-size: 0.9rem;">
                                    <span style="font-weight: 500;"><?php echo $patient['age'] ?? 'N/A'; ?> yrs</span>
                                    <span style="color: #6b7280; margin-left: 8px;"><?php echo $patient['gender'] ?? 'N/A'; ?></span>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 0.85rem; color: #6b7280;">
                                    <div><?php echo htmlspecialchars($patient['phone'] ?: 'N/A'); ?></div>
                                    <div><?php echo htmlspecialchars($patient['email'] ?: ''); ?></div>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 0.9rem; font-weight: 500;">
                                    <?php echo htmlspecialchars($patient['current_treatment'] ?: 'N/A'); ?>
                                </div>
                            </td>
                            <td>
                                <?php 
                                    $status = $patient['queue_status'] ?? '';
                                    $statusColors = [
                                        'waiting' => '#fef3c7:#92400e',
                                        'in_procedure' => '#dbeafe:#1e40af',
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
                                <span class="status-badge" style="background: <?php echo $bgColor; ?>; color: <?php echo $textColor; ?>;">
                                    <?php echo ucfirst(str_replace('_', ' ', $status ?: 'None')); ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-size: 0.9rem; font-weight: 500;">
                                    <?php echo !empty($patient['created_at']) ? date('M d, Y', strtotime($patient['created_at'])) : 'N/A'; ?>
                                </div>
                            </td>
                            <td>
                                <div class="patient-actions">
                                    <button onclick="viewPatientDetails(<?php echo $patient['id']; ?>)" class="action-btn icon" title="View Details">👁️</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Patient Details Modal - Full Screen -->
<div id="patientModal" class="fullscreen-modal-overlay">
    <div class="fullscreen-modal-content">
        <div class="fullscreen-modal-header">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 600; margin: 0;">Patient Record Details</h2>
                <p id="dentistModalPatientName" style="color: #6b7280; margin: 4px 0 0 0; font-size: 0.9rem;"></p>
            </div>
            <button onclick="closePatientModal()" class="fullscreen-modal-close">&times;</button>
        </div>
        <div class="fullscreen-modal-body" id="patientModalContent">
            <!-- Content loaded dynamically -->
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
    font-weight: 600;
    color: #111827;
}

.medical-alert {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.medical-alert-title {
    color: #dc2626;
    font-weight: 600;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.medical-alert-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
}

.medical-alert-item {
    background: white;
    padding: 12px;
    border-radius: 8px;
}

.medical-alert-item-value.danger {
    color: #dc2626;
}
</style>

<script>
const patients = <?php echo json_encode($patients); ?>;

// Search and filter functionality
document.getElementById('searchInput').addEventListener('input', filterPatients);
document.getElementById('statusFilter').addEventListener('change', filterPatients);
document.getElementById('sortFilter').addEventListener('change', sortPatients);

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

function sortPatients() {
    const sortBy = document.getElementById('sortFilter').value;
    const tbody = document.querySelector('#patientsTable tbody');
    const rows = Array.from(document.querySelectorAll('.patient-row'));
    
    rows.sort((a, b) => {
        if (sortBy === 'name') {
            return a.dataset.name.localeCompare(b.dataset.name);
        } else {
            // Keep original order (newest first)
            return 0;
        }
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// View patient details - Full screen modal with medical history
function viewPatientDetails(patientId) {
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
            const medicalConditions = m.medical_conditions || 'None';
            
            document.getElementById('dentistModalPatientName').innerText = p.full_name || 'Unknown';
            
            // Check for medical alerts
            const hasMedicalAlert = allergies === 'Yes' || 
                                   medicalConditions.toLowerCase().includes('diabetes') ||
                                   medicalConditions.toLowerCase().includes('heart') ||
                                   medicalConditions.toLowerCase().includes('blood pressure') ||
                                   medicalConditions.toLowerCase().includes('asthma');
            
            document.getElementById('patientModalContent').innerHTML = `
                <!-- Patient Basic Info -->
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
                        <div class="patient-info-label">Date of Birth</div>
                        <div class="patient-info-value">${p.date_of_birth || 'N/A'}</div>
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

                <!-- Medical Alert -->
                ${hasMedicalAlert ? `
                <div class="medical-alert">
                    <div class="medical-alert-title">⚠️ Medical Alert - Important for Treatment</div>
                    <div class="medical-alert-grid">
                        <div class="medical-alert-item">
                            <div class="patient-info-label">Allergies</div>
                            <div class="patient-info-value ${allergies === 'Yes' ? 'danger' : ''}">${allergies}</div>
                        </div>
                        <div class="medical-alert-item">
                            <div class="patient-info-label">Diabetes</div>
                            <div class="patient-info-value ${medicalConditions.toLowerCase().includes('diabetes') ? 'danger' : ''}">${medicalConditions.toLowerCase().includes('diabetes') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="medical-alert-item">
                            <div class="patient-info-label">Heart Disease</div>
                            <div class="patient-info-value ${medicalConditions.toLowerCase().includes('heart') ? 'danger' : ''}">${medicalConditions.toLowerCase().includes('heart') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="medical-alert-item">
                            <div class="patient-info-label">High Blood Pressure</div>
                            <div class="patient-info-value ${medicalConditions.toLowerCase().includes('blood pressure') ? 'danger' : ''}">${medicalConditions.toLowerCase().includes('blood pressure') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="medical-alert-item">
                            <div class="patient-info-label">Asthma</div>
                            <div class="patient-info-value ${medicalConditions.toLowerCase().includes('asthma') ? 'danger' : ''}">${medicalConditions.toLowerCase().includes('asthma') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="medical-alert-item" style="grid-column: 1 / -1;">
                            <div class="patient-info-label">Current Medications</div>
                            <div class="patient-info-value">${medications}</div>
                        </div>
                    </div>
                </div>
                ` : ''}

                <!-- Current Queue Status -->
                ${q ? `
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: #1e40af; margin-bottom: 16px;">📋 Current Queue Status</h3>
                    <div class="patient-info-grid">
                        <div class="patient-info-item">
                            <div class="patient-info-label">Treatment Type</div>
                            <div class="patient-info-value">${q.treatment_type || 'Consultation'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Selected Teeth</div>
                            <div class="patient-info-value">${q.teeth_numbers || 'None'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Status</div>
                            <div class="patient-info-value">
                                <span style="background: ${q.status === 'in_procedure' ? '#dcfce7' : q.status === 'waiting' ? '#fef3c7' : '#f3f4f6'}; color: ${q.status === 'in_procedure' ? '#15803d' : q.status === 'waiting' ? '#d97706' : '#6b7280'}; padding: 4px 12px; border-radius: 9999px; font-size: 0.85rem;">
                                    ${q.status ? q.status.replace('_', ' ').toUpperCase() : 'N/A'}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                ` : ''}

                <!-- Dental History -->
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
                        <div class="patient-info-item" style="grid-column: 1 / -1;">
                            <div class="patient-info-label">Previous Treatments</div>
                            <div class="patient-info-value">${d.previous_treatments || 'None'}</div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px;">
                    <button onclick="closePatientModal()" class="btn-cancel">Close</button>
                </div>
            `;
            
            document.getElementById('patientModal').classList.add('active');
        }
    });
}

function closePatientModal() {
    document.getElementById('patientModal').classList.remove('active');
}

// Close modal on outside click
document.getElementById('patientModal').addEventListener('click', function(e) {
    if (e.target === this) closePatientModal();
});

// ESC key to close modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePatientModal();
});
</script>

<?php require_once 'includes/dentist_layout_end.php'; ?>