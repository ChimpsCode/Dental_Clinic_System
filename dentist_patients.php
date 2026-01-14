<?php
$pageTitle = 'Patient Records';
require_once 'includes/dentist_layout_start.php';

try {
    require_once 'config/database.php';
    
    // Get all patients
    $stmt = $pdo->query("SELECT * FROM patients ORDER BY last_visit_date DESC");
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
            <h3><?php echo count(array_filter($patients, fn($p) => $p['status'] === 'active')); ?></h3>
            <p>Active Patients</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon green">✓</div>
        <div class="summary-info">
            <h3><?php echo count(array_filter($patients, fn($p) => !empty($p['last_treatment_date']) && strtotime($p['last_treatment_date']) > strtotime('-30 days'))); ?></h3>
            <p>Visited This Month</p>
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
    <input type="text" id="searchInput" placeholder="Search by name, code, or phone..." class="search-input" style="flex: 1;">
    <select id="statusFilter" class="filter-select">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
    </select>
    <select id="sortFilter" class="filter-select">
        <option value="last_visit">Last Visit</option>
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
                        <th>Patient Code</th>
                        <th>Name</th>
                        <th>Age/Gender</th>
                        <th>Contact</th>
                        <th>Last Visit</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $patient): ?>
                        <tr class="patient-row" 
                            data-name="<?php echo strtolower($patient['full_name']); ?>" 
                            data-code="<?php echo strtolower($patient['patient_code']); ?>" 
                            data-phone="<?php echo strtolower($patient['phone']); ?>" 
                            data-status="<?php echo $patient['status']; ?>">
                            <td>
                                <span style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; font-weight: 600;">
                                    <?php echo htmlspecialchars($patient['patient_code']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="patient-name" style="font-weight: 600;"><?php echo htmlspecialchars($patient['full_name']); ?></div>
                            </td>
                            <td>
                                <div style="font-size: 0.9rem;">
                                    <span style="font-weight: 500;"><?php echo $patient['age']; ?> yrs</span>
                                    <span style="color: #6b7280; margin-left: 8px;"><?php echo $patient['gender']; ?></span>
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
                                     <?php echo !empty($patient['last_visit_date']) ? date('M d, Y', strtotime($patient['last_visit_date'])) : 'N/A'; ?>
                                 </div>
                             </td>
                             <td>
                                 <span class="status-badge" style="background: <?php echo $patient['status'] === 'active' ? '#d1fae5' : '#f3f4f6'; ?>; color: <?php echo $patient['status'] === 'active' ? '#065f46' : '#6b7280'; ?>;">
                                     <?php echo ucfirst($patient['status']); ?>
                                 </span>
                             </td>
                             <td>
                                 <div class="patient-actions">
                                     <button onclick="viewPatientDetails(<?php echo $patient['id']; ?>)" class="action-btn icon" title="View Details">👁️</button>
                                     <button onclick="addPrescription(<?php echo $patient['id']; ?>)" class="action-btn icon" title="Add Prescription">💊</button>
                                 </div>
                             </td>
                         </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Patient Details Modal -->
<div id="patientModal" class="modal-overlay">
    <div class="modal" style="max-width: 700px;">
        <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Patient Details</h2>
        <div id="patientModalContent"></div>
    </div>
</div>





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
            row.dataset.code.includes(search) || 
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
        if (sortBy === 'last_visit') {
            const lastVisitA = a.querySelector('td:nth-child(5) div').textContent.trim();
            const lastVisitB = b.querySelector('td:nth-child(5) div').textContent.trim();
            if (lastVisitA === 'N/A') return 1;
            if (lastVisitB === 'N/A') return -1;
            return new Date(lastVisitB) - new Date(lastVisitA);
        } else if (sortBy === 'treatments') {
            return b.dataset.name.localeCompare(a.dataset.name);
        } else {
            return a.dataset.name.localeCompare(b.dataset.name);
        }
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// View patient details
function viewPatientDetails(patientId) {
    const patient = patients.find(p => p.id === patientId);
    if (!patient) return;
    
    document.getElementById('patientModalContent').innerHTML = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <h3 style="font-size: 1.1rem; margin-bottom: 16px;">Personal Information</h3>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Patient Code:</span> <span style="font-weight: 500;">${patient.patient_code}</span></div>
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Full Name:</span> <span style="font-weight: 500;">${patient.full_name}</span></div>
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Age:</span> <span style="font-weight: 500;">${patient.age} years</span></div>
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Gender:</span> <span style="font-weight: 500;">${patient.gender}</span></div>
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Status:</span> <span class="status-badge" style="background: ${patient.status === 'active' ? '#d1fae5' : '#f3f4f6'}; color: ${patient.status === 'active' ? '#065f46' : '#6b7280'};">${patient.status}</span></div>
                </div>
            </div>
            <div>
                <h3 style="font-size: 1.1rem; margin-bottom: 16px;">Contact Information</h3>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Phone:</span> <span style="font-weight: 500;">${patient.phone || 'N/A'}</span></div>
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Email:</span> <span style="font-weight: 500;">${patient.email || 'N/A'}</span></div>
                </div>
            </div>
        </div>
        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
            <h3 style="font-size: 1.1rem; margin-bottom: 12px;">Treatment Statistics</h3>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                <div style="background: #f3f4f6; padding: 16px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 1.2rem; font-weight: 700; color: #059669;">${patient.last_visit_date ? new Date(patient.last_visit_date).toLocaleDateString() : 'N/A'}</div>
                    <div style="font-size: 0.9rem; color: #6b7280; margin-top: 4px;">Last Visit</div>
                </div>
                <div style="background: #f3f4f6; padding: 16px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 1.2rem; font-weight: 700; color: #0891b2;">${patient.status === 'active' ? 'Active' : 'Inactive'}</div>
                    <div style="font-size: 0.9rem; color: #6b7280; margin-top: 4px;">Patient Status</div>
                </div>
            </div>
        </div>
        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px;">
            <button onclick="closePatientModal()" class="btn-cancel">Close</button>
            <button onclick="addPrescription(${patient.id}); closePatientModal();" class="btn-primary">Add Prescription</button>
        </div>
    `;
    
    document.getElementById('patientModal').style.display = 'flex';
}

function closePatientModal() {
    document.getElementById('patientModal').style.display = 'none';
}

function closeTreatmentModal() {
    document.getElementById('treatmentModal').style.display = 'none';
}

function addPrescription(patientId) {
    alert('Prescription feature coming soon! This will open a prescription form for patient ID: ' + patientId);
}

// Close modal on outside click
document.getElementById('patientModal').addEventListener('click', function(e) {
    if (e.target === this) closePatientModal();
});

// Add treatment form submission
document.getElementById('addTreatmentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    alert('Treatment saved successfully! (This will be connected to database in next step)');
    closeAddTreatmentModal();
});

document.getElementById('treatmentModal').addEventListener('click', function(e) {
    if (e.target === this) closeTreatmentModal();
});

document.getElementById('addTreatmentModal').addEventListener('click', function(e) {
    if (e.target === this) closeAddTreatmentModal();
});

// Add treatment form submission
document.getElementById('addTreatmentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    alert('Treatment saved successfully! (This will be connected to database in the next step)');
    closeAddTreatmentModal();
});
</script>

<?php require_once 'includes/dentist_layout_end.php'; ?>