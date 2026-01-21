<?php
/**
 * Patient Records - Admin page for viewing all patient records
 */

$pageTitle = 'Patient Records';

try {
    require_once 'config/database.php';
    $stmt = $pdo->query("
        SELECT p.*,
               (SELECT status FROM queue WHERE patient_id = p.id ORDER BY created_at DESC LIMIT 1) as queue_status
        FROM patients p 
        ORDER BY p.created_at DESC
    ");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate stats
    $totalPatients = count($patients);
    $inQueue = count(array_filter($patients, fn($p) => in_array($p['queue_status'] ?? '', ['waiting', 'in_procedure'])));
    $scheduledCount = count(array_filter($patients, fn($p) => ($p['queue_status'] ?? '') === 'scheduled'));
    $newThisMonth = count(array_filter($patients, fn($p) => !empty($p['created_at']) && strtotime($p['created_at']) > strtotime('-30 days')));
} catch (Exception $e) {
    $patients = [];
    $totalPatients = 0;
    $inQueue = 0;
    $scheduledCount = 0;
    $newThisMonth = 0;
}

require_once __DIR__ . '/includes/admin_layout_start.php';
?>
            <div class="content-main">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Patient Records</h2>
                </div>

                <!-- Summary Stats Cards -->
                <div class="summary-cards" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 20px; margin-bottom: 24px;">
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
                        <div class="summary-icon" style="width: 48px; height: 48px; background: #e0e7ff; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">📅</div>
                        <div class="summary-info">
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo $scheduledCount; ?></h3>
                            <p style="font-size: 0.875rem; color: #6b7280; margin: 0;">Scheduled</p>
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

                <!-- Search and Filter -->
                <div class="search-filters">
                    <input type="text" class="search-input" placeholder="Search by name or phone..." id="searchInput">
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="waiting">Waiting</option>
                        <option value="in_procedure">In Procedure</option>
                        <option value="completed">Completed</option>
                        <option value="scheduled">Scheduled</option>
                    </select>
                </div>

                <!-- Patients Table -->
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th>Age/Gender</th>
                                <th>Contact</th>
                                <th>Date Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="patientsTableBody">
                            <?php if (empty($patients)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 60px; color: #6b7280;">
                                        No patients found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($patients as $patient): ?>
                                    <tr class="patient-row" 
                                        data-name="<?php echo strtolower(htmlspecialchars($patient['full_name'] ?? 'Unknown')); ?>"
                                        data-phone="<?php echo strtolower(htmlspecialchars($patient['phone'] ?? '')); ?>">
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <div style="width: 40px; height: 40px; background: #e5e7eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #6b7280; font-weight: 600;">
                                                    <?php echo strtoupper(substr($patient['full_name'] ?? 'U', 0, 1)); ?>
                                                </div>
                                                <span style="font-weight: 500;"><?php echo htmlspecialchars($patient['full_name'] ?? 'Unknown'); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.9rem;">
                                                <span style="font-weight: 500;"><?php echo $patient['age'] ?? 'N/A'; ?> yrs</span>
                                                <span style="color: #6b7280; margin-left: 8px;"><?php echo ucfirst($patient['gender'] ?? 'N/A'); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.85rem; color: #6b7280;">
                                                <div><?php echo htmlspecialchars($patient['phone'] ?: 'N/A'); ?></div>
                                                <div><?php echo htmlspecialchars($patient['email'] ?? ''); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-weight: 500;"><?php echo !empty($patient['created_at']) ? date('M d, Y', strtotime($patient['created_at'])) : 'N/A'; ?></span>
                                        </td>
                                        <td>
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

<script>
const patients = <?php echo json_encode($patients); ?>;

document.getElementById('searchInput').addEventListener('input', filterPatients);
document.getElementById('statusFilter').addEventListener('change', filterPatients);

function filterPatients() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    
    document.querySelectorAll('.patient-row').forEach(row => {
        const matchSearch = !search || 
            row.dataset.name.includes(search) || 
            row.dataset.phone.includes(search);
        
        row.style.display = matchSearch ? '' : 'none';
    });
}

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
            viewPatientDetails(id);
            break;
        case 'appointment':
            openAddAppointmentModal(id);
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

// View Patient Details
function viewPatientDetails(patientId) {
    const patient = patients.find(p => p.id == patientId);
    if (!patient) return;
    
    alert('Patient: ' + (patient.full_name || 'Unknown') + '\nPhone: ' + (patient.phone || 'N/A') + '\nEmail: ' + (patient.email || 'N/A'));
}

// Add Appointment Modal Functions
let selectedPatientId = null;

function openAddAppointmentModal(patientId) {
    const patient = patients.find(p => p.id == patientId);
    
    if (patient) {
        selectedPatientId = patientId;
        
        const fullName = (patient.first_name || '') + ' ' + (patient.middle_name || '' + ' ') + (patient.last_name || '');
        
        document.getElementById('appointmentPatientName').textContent = fullName.trim();
        document.getElementById('appointmentPatientId').value = patientId;
        document.getElementById('appointmentPatientPhone').value = patient.phone || '';
        
        document.getElementById('appointmentModal').classList.add('active');
    } else {
        alert('Patient not found');
    }
}

function closeAddAppointmentModal() {
    document.getElementById('appointmentModal').classList.remove('active');
}

document.getElementById('appointmentModal').addEventListener('click', function(e) {
    if (e.target === this || e.target.classList.contains('modal-backdrop') || e.target.closest('.modal-container') === e.target) {
        closeAddAppointmentModal();
    }
});

document.getElementById('addAppointmentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('process_patient_appointment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Appointment scheduled successfully!');
            closeAddAppointmentModal();
            location.reload();
        } else {
            alert(data.message || 'Error scheduling appointment');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error scheduling appointment');
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (document.getElementById('appointmentModal').classList.contains('active')) {
            closeAddAppointmentModal();
        }
    }
});
</script>

<style>
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

/* Form Styles */
.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
    margin-bottom: 6px;
}

.form-group .form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-group .form-control:focus {
    border-color: #0ea5e9;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.btn-primary {
    padding: 10px 20px;
    background: #0ea5e9;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary:hover {
    background: #0284c7;
}

.btn-cancel {
    padding: 10px 20px;
    background: white;
    color: #374151;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-cancel:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

/* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: 99999;
}

.modal-overlay.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

.modal-container {
    position: relative;
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    padding: 20px;
}

.modal {
    background: white;
    border-radius: 16px;
    padding: 32px;
    max-width: 480px;
    width: 100%;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
}
</style>

<!-- Add Appointment Modal -->
<div id="appointmentModal" class="modal-overlay">
    <div class="modal-backdrop"></div>
    <div class="modal-container">
        <div class="modal">
            <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Schedule Appointment</h2>
            
            <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 14px 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #0369a1;">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <div>
                    <div style="font-size: 0.75rem; color: #0369a1; font-weight: 600;">PATIENT</div>
                    <div id="appointmentPatientName" style="font-weight: 600; color: #0c4a6e;"></div>
                </div>
            </div>
            
            <form id="addAppointmentForm">
                <input type="hidden" id="appointmentPatientId" name="patient_id">
                <input type="hidden" id="appointmentPatientPhone" name="patient_phone">
                
                <div style="display: flex; gap: 16px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Date *</label>
                        <input type="date" name="appointment_date" required class="form-control" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Time *</label>
                        <input type="time" name="appointment_time" required class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Treatment</label>
                    <select name="treatment" class="form-control">
                        <option value="General Checkup">General Checkup</option>
                        <option value="Teeth Cleaning">Teeth Cleaning</option>
                        <option value="Root Canal">Root Canal</option>
                        <option value="Tooth Extraction">Tooth Extraction</option>
                        <option value="Dental Fillings">Dental Fillings</option>
                        <option value="Braces Adjustment">Braces Adjustment</option>
                        <option value="Denture Fitting">Denture Fitting</option>
                        <option value="Oral Prophylaxis">Oral Prophylaxis</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3" class="form-control" placeholder="Additional notes or instructions..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" onclick="closeAddAppointmentModal()" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-primary">Schedule Appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/admin_layout_end.php';
?>
