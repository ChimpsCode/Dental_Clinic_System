<?php
$pageTitle = 'Appointments';
require_once 'includes/dentist_layout_start.php';
require_once 'config/database.php';

// Debug: Check database connection
error_log("Checking appointments table...");

// Fetch appointment statistics
try {
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN DATE(appointment_date) = CURDATE() THEN 1 ELSE 0 END) as today,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM appointments");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Stats: " . json_encode($stats));
    
    $totalAppointments = $stats['total'] ?? 0;
    $todayAppointments = $stats['today'] ?? 0;
    $completedAppointments = $stats['completed'] ?? 0;
    $cancelledAppointments = $stats['cancelled'] ?? 0;
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
    $totalAppointments = $todayAppointments = $completedAppointments = $cancelledAppointments = 0;
}

// Fetch all appointments
try {
    $stmt = $pdo->query("SELECT a.*, 
        a.first_name, a.middle_name, a.last_name,
        p.phone, p.email FROM appointments a 
        LEFT JOIN patients p ON a.patient_id = p.id 
        ORDER BY a.appointment_date DESC");
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Appointments found: " . count($appointments));
} catch (Exception $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
    $appointments = [];
}
?>

<!-- Appointment Stats -->
<div class="summary-cards">
    <div class="summary-card">
        <div class="summary-icon blue" style="background: #e0f2fe; color: #0284c7;">📋</div>
        <div class="summary-info">
            <h3><?php echo $totalAppointments; ?></h3>
            <p>Total Appointments</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon yellow">⏰</div>
        <div class="summary-info">
            <h3><?php echo $todayAppointments; ?></h3>
            <p>Today</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon green">✓</div>
        <div class="summary-info">
            <h3><?php echo $completedAppointments; ?></h3>
            <p>Completed</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon red" style="background: #fee2e2; color: #dc2626;">⚠️</div>
        <div class="summary-info">
            <h3><?php echo $cancelledAppointments; ?></h3>
            <p>Cancelled</p>
        </div>
    </div>
</div>

<!-- Search & Filters -->
<div class="search-filters">
    <div class="filter-tabs">
        <span class="active">All</span>
        <span>Today</span>
        <span>This Week</span>
        <span>This Month</span>
    </div>
    <input type="text" class="search-input" placeholder="Search appointments...">
</div>

<!-- Appointments Table -->
<div class="section-card">
    <div class="section-title">
        <span>Appointments List</span>
        <button class="btn-primary" onclick="openNewAppointmentModal()">+ New Appointment</button>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>First Name</th>
                <th>Middle Name</th>
                <th>Last Name</th>
                <th>Date & Time</th>
                <th>Treatment</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($appointments)): ?>
                <?php foreach ($appointments as $apt): ?>
                    <?php
                        $firstName = htmlspecialchars($apt['first_name'] ?? '');
                        $middleName = htmlspecialchars($apt['middle_name'] ?? '');
                        $lastName = htmlspecialchars($apt['last_name'] ?? '');
                        $appointmentDate = new DateTime($apt['appointment_date']);
                        $appointmentTime = $appointmentDate->format('h:i A');
                        $appointmentDateStr = $appointmentDate->format('M d, Y');
                        $status = $apt['status'] ?? 'scheduled';
                        
                        // Determine status badge color
                        $statusColor = match($status) {
                            'completed' => 'background: #dcfce7; color: #15803d;',
                            'cancelled' => 'background: #fee2e2; color: #dc2626;',
                            default => 'background: #e0f2fe; color: #0369a1;'
                        };
                        $statusText = ucfirst($status);
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600;"><?php echo $firstName ?: '-'; ?></div>
                        </td>
                        <td>
                            <div style="color: #6b7280;"><?php echo $middleName ?: '-'; ?></div>
                        </td>
                        <td>
                            <div style="font-weight: 600;"><?php echo $lastName ?: '-'; ?></div>
                        </td>
                        <td>
                            <div><?php echo $appointmentDateStr; ?></div>
                            <div style="font-size: 0.85rem; color: #6b7280;"><?php echo $appointmentTime; ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($apt['treatment'] ?? 'General Checkup'); ?></td>
                         <td><span class="status-badge" style="<?php echo $statusColor; ?>"><?php echo $statusText; ?></span></td>
                         <td>
                                <div class="appt-kebab-menu">
                                    <button class="appt-kebab-btn" data-appt-id="<?php echo $apt['id']; ?>">
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
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2rem; color: #6b7280;">No appointments found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- New Appointment Modal -->
<div id="newAppointmentModal" class="modal-overlay">
    <div class="modal" style="width: 550px; max-height: 90vh; overflow-y: auto;">
        <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Schedule New Appointment</h2>
        <form id="newAppointmentForm">
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>First Name *</label>
                    <input type="text" name="first_name" required class="form-control" placeholder="Enter first name">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" class="form-control" placeholder="Enter middle name">
                </div>
            </div>
            
            <div class="form-group">
                <label>Last Name *</label>
                <input type="text" name="last_name" required class="form-control" placeholder="Enter last name">
            </div>
            
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Phone *</label>
                    <input type="tel" name="phone" required class="form-control" placeholder="e.g., 09123456789">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" placeholder="patient@email.com">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Appointment Date *</label>
                    <input type="date" name="appointment_date" required class="form-control">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Appointment Time *</label>
                    <input type="time" name="appointment_time" required class="form-control">
                </div>
            </div>
            
            <div class="form-group">
                <label>Treatment</label>
                <select name="treatment" class="form-control">
                    <option value="General Checkup">General Checkup</option>
                    <option value="Teeth Cleaning">Teeth Cleaning</option>
                    <option value="Root Canal">Root Canal</option>
                    <option value="Extraction">Extraction</option>
                    <option value="Filling">Filling</option>
                    <option value="Braces">Braces/Orthodontics</option>
                    <option value="Denture">Denture</option>
                    <option value="Crown & Bridge">Crown & Bridge</option>
                    <option value="Whitening">Whitening</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" rows="3" class="form-control" placeholder="Additional notes about the appointment..."></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" onclick="closeNewAppointmentModal()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-primary">Schedule Appointment</button>
            </div>
        </form>
    </div>
</div>

<!-- View Appointment Modal -->
<div id="viewAppointmentModal" class="modal-overlay">
    <div class="modal" style="width: 500px;">
        <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Appointment Details</h2>
        <div id="viewAppointmentContent"></div>
        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px;">
            <button onclick="closeViewAppointmentModal()" class="btn-cancel">Close</button>
        </div>
    </div>
</div>

<!-- Edit Appointment Modal -->
<div id="editAppointmentModal" class="modal-overlay">
    <div class="modal" style="width: 550px; max-height: 90vh; overflow-y: auto;">
        <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Edit Appointment</h2>
        <form id="editAppointmentForm">
            <input type="hidden" name="appointment_id" id="edit_appointment_id">
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>First Name *</label>
                    <input type="text" name="first_name" id="edit_first_name" required class="form-control" placeholder="Enter first name">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" id="edit_middle_name" class="form-control" placeholder="Enter middle name">
                </div>
            </div>
            
            <div class="form-group">
                <label>Last Name *</label>
                <input type="text" name="last_name" id="edit_last_name" required class="form-control" placeholder="Enter last name">
            </div>
            
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Phone *</label>
                    <input type="tel" name="phone" id="edit_phone" required class="form-control" placeholder="e.g., 09123456789">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control" placeholder="patient@email.com">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Appointment Date *</label>
                    <input type="date" name="appointment_date" id="edit_appointment_date" required class="form-control">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Appointment Time *</label>
                    <input type="time" name="appointment_time" id="edit_appointment_time" required class="form-control">
                </div>
            </div>
            
            <div class="form-group">
                <label>Treatment</label>
                <select name="treatment" id="edit_treatment" class="form-control">
                    <option value="General Checkup">General Checkup</option>
                    <option value="Teeth Cleaning">Teeth Cleaning</option>
                    <option value="Root Canal">Root Canal</option>
                    <option value="Extraction">Extraction</option>
                    <option value="Filling">Filling</option>
                    <option value="Braces">Braces/Orthodontics</option>
                    <option value="Denture">Denture</option>
                    <option value="Crown & Bridge">Crown & Bridge</option>
                    <option value="Whitening">Whitening</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="edit_status" class="form-control">
                    <option value="scheduled">Scheduled</option>
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" id="edit_notes" rows="3" class="form-control" placeholder="Additional notes about the appointment..."></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" onclick="closeEditAppointmentModal()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>



<script>
// Portal Pattern: Move modal to body level
document.addEventListener('DOMContentLoaded', function() {
    const newModal = document.getElementById('newAppointmentModal');
    if (newModal) {
        document.body.appendChild(newModal);
    }
    
    const editModal = document.getElementById('editAppointmentModal');
    if (editModal) {
        document.body.appendChild(editModal);
    }
    
    // Close new modal when clicking outside
    if (newModal) {
        newModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeNewAppointmentModal();
            }
        });
    }
});

function openNewAppointmentModal() {
    document.getElementById('newAppointmentForm').reset();
    
    // Set default date to today
    const today = new Date().toISOString().split('T')[0];
    document.querySelector('input[name="appointment_date"]').value = today;
    
    // Set default time
    document.querySelector('input[name="appointment_time"]').value = '09:00';
    
    document.getElementById('newAppointmentModal').style.display = 'flex';
}

function closeNewAppointmentModal() {
    document.getElementById('newAppointmentModal').style.display = 'none';
}

// Handle form submission
document.getElementById('newAppointmentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('process_dentist_appointment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Appointment scheduled successfully!');
            closeNewAppointmentModal();
            location.reload();
        } else {
            alert(data.message || 'Error scheduling appointment');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error scheduling appointment. Please try again.');
    });
});

// ESC key to close modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeNewAppointmentModal();
    }
});

// View Appointment function
function viewAppointment(id) {
    const appointment = appointments.find(a => a.id == id);
    if (!appointment) return;
    
    const appointmentDate = new Date(appointment.appointment_date);
    const dateStr = appointmentDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    const timeStr = appointmentDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    
    document.getElementById('viewAppointmentContent').innerHTML = `
        <div style="display: flex; flex-direction: column; gap: 16px;">
            <div><span style="color: #6b7280;">Patient:</span> 
                <span style="font-weight: 600; margin-left: 8px;">${appointment.first_name || ''}</span>
                ${appointment.middle_name ? `<span style="color: #6b7280; margin-left: 4px;">${appointment.middle_name}</span>` : ''}
                <span style="font-weight: 600; margin-left: 4px;">${appointment.last_name || ''}</span>
            </div>
            <div><span style="color: #6b7280;">Phone:</span> <span style="font-weight: 500; margin-left: 8px;">${appointment.phone || 'N/A'}</span></div>
            <div><span style="color: #6b7280;">Date:</span> <span style="font-weight: 500; margin-left: 8px;">${dateStr}</span></div>
            <div><span style="color: #6b7280;">Time:</span> <span style="font-weight: 500; margin-left: 8px;">${timeStr}</span></div>
            <div><span style="color: #6b7280;">Treatment:</span> <span style="font-weight: 500; margin-left: 8px;">${appointment.treatment || 'General Checkup'}</span></div>
            <div><span style="color: #6b7280;">Status:</span> <span class="status-badge" style="margin-left: 8px; background: ${appointment.status === 'Completed' ? '#dcfce7' : appointment.status === 'Cancelled' ? '#fee2e2' : '#e0f2fe'}; color: ${appointment.status === 'Completed' ? '#15803d' : appointment.status === 'Cancelled' ? '#dc2626' : '#0369a1'}; padding: 4px 12px; border-radius: 9999px; font-size: 0.875rem;">${appointment.status || 'Pending'}</span></div>
            ${appointment.notes ? `<div><span style="color: #6b7280;">Notes:</span><p style="background: #f9fafb; padding: 12px; border-radius: 8px; margin: 8px 0 0;">${appointment.notes}</p></div>` : ''}
        </div>
    `;
    
    document.getElementById('viewAppointmentModal').style.display = 'flex';
}

function editAppointment(id) {
    const appointment = appointments.find(a => a.id == id);
    if (!appointment) return;
    
    // Fill form with appointment data
    document.getElementById('edit_appointment_id').value = appointment.id;
    document.getElementById('edit_first_name').value = appointment.first_name || '';
    document.getElementById('edit_middle_name').value = appointment.middle_name || '';
    document.getElementById('edit_last_name').value = appointment.last_name || '';
    document.getElementById('edit_phone').value = appointment.phone || '';
    document.getElementById('edit_email').value = appointment.email || '';
    document.getElementById('edit_treatment').value = appointment.treatment || 'General Checkup';
    document.getElementById('edit_status').value = appointment.status || 'scheduled';
    document.getElementById('edit_notes').value = appointment.notes || '';
    
    // Format date and time for input fields
    if (appointment.appointment_date) {
        document.getElementById('edit_appointment_date').value = appointment.appointment_date;
    }
    if (appointment.appointment_time) {
        document.getElementById('edit_appointment_time').value = appointment.appointment_time;
    }
    
    document.getElementById('editAppointmentModal').style.display = 'flex';
}

function closeEditAppointmentModal() {
    document.getElementById('editAppointmentModal').style.display = 'none';
}

// Handle edit form submission
document.getElementById('editAppointmentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'edit');
    formData.append('id', document.getElementById('edit_appointment_id').value);
    
    fetch('process_dentist_appointment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Appointment updated successfully!');
            closeEditAppointmentModal();
            location.reload();
        } else {
            alert(data.message || 'Error updating appointment');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating appointment. Please try again.');
    });
});

function closeViewAppointmentModal() {
    document.getElementById('viewAppointmentModal').style.display = 'none';
}

// Close view modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const viewModal = document.getElementById('viewAppointmentModal');
    if (viewModal) {
        viewModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeViewAppointmentModal();
            }
        });
    }
    
    // Portal Pattern: Move modals to body level
    const newModal = document.getElementById('newAppointmentModal');
    if (newModal) {
        document.body.appendChild(newModal);
    }
    const viewModalPortal = document.getElementById('viewAppointmentModal');
    if (viewModalPortal) {
        document.body.appendChild(viewModalPortal);
    }
});
</script>

<style>
/* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 99999;
    align-items: center;
    justify-content: center;
}

.modal-overlay[style*="display: flex"] {
    display: flex !important;
}

.modal {
    background: white;
    border-radius: 12px;
    padding: 28px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    position: relative;
    z-index: 100000;
}

.form-row {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
}

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

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.9rem;
    outline: none;
    transition: border-color 0.2s;
}

.form-control:focus {
    border-color: #3b82f6;
    ring: 2px solid rgba(59, 130, 246, 0.2);
}

.modal-actions {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.btn-cancel {
    background: #f3f4f6;
    color: #374151;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-cancel:hover {
    background: #e5e7eb;
}

/* Appointment Kebab Menu Styles - Portal Based */
.appt-kebab-menu {
    position: relative;
    display: inline-block;
}

.appt-kebab-btn {
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

.appt-kebab-btn:hover {
    background-color: #f3f4f6;
    color: #374151;
}

.appt-kebab-btn.active {
    background-color: #e5e7eb;
    color: #111827;
}

.appt-kebab-dropdown-portal {
    display: none;
    position: fixed;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    min-width: 160px;
    max-width: 180px;
    width: auto;
    z-index: 99999;
    overflow: hidden;
}

.appt-kebab-dropdown-portal.show {
    display: block;
    animation: apptKebabFadeIn 0.15s ease;
}

@keyframes apptKebabFadeIn {
    from { opacity: 0; transform: scale(0.95) translateY(-4px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.appt-kebab-dropdown-portal a {
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

.appt-kebab-dropdown-portal a:hover {
    background-color: #f9fafb;
    color: #111827;
}

.appt-kebab-dropdown-portal a svg {
    flex-shrink: 0;
}

.appt-kebab-dropdown-portal a:first-child {
    border-radius: 8px 8px 0 0;
}

.appt-kebab-dropdown-portal a:last-child {
    border-radius: 0 0 8px 8px;
}

.appt-kebab-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 99998;
}

.appt-kebab-backdrop.show {
    display: block;
}
.search-filters{
    margin-bottom: 0px;
}

.summary-cards {
    margin-bottom: 0px;
}
</style>

<?php require_once 'includes/dentist_layout_end.php'; ?>