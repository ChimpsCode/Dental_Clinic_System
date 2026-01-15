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

// View patient details
function viewPatientDetails(patientId) {
    const patient = patients.find(p => p.id == patientId);
    if (!patient) return;
    
    document.getElementById('patientModalContent').innerHTML = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <h3 style="font-size: 1.1rem; margin-bottom: 16px;">Personal Information</h3>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Full Name:</span> <span style="font-weight: 500;">${patient.full_name || 'N/A'}</span></div>
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Age:</span> <span style="font-weight: 500;">${patient.age || 'N/A'} years</span></div>
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Gender:</span> <span style="font-weight: 500;">${patient.gender || 'N/A'}</span></div>
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Date of Birth:</span> <span style="font-weight: 500;">${patient.date_of_birth || 'N/A'}</span></div>
                </div>
            </div>
            <div>
                <h3 style="font-size: 1.1rem; margin-bottom: 16px;">Contact Information</h3>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Phone:</span> <span style="font-weight: 500;">${patient.phone || 'N/A'}</span></div>
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Email:</span> <span style="font-weight: 500;">${patient.email || 'N/A'}</span></div>
                    <div><span style="color: #6b7280; font-size: 0.9rem;">Address:</span> <span style="font-weight: 500;">${patient.address || 'N/A'}</span></div>
                </div>
            </div>
        </div>
        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
            <h3 style="font-size: 1.1rem; margin-bottom: 12px;">Current Status</h3>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                <div style="background: #f3f4f6; padding: 16px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 1.2rem; font-weight: 700; color: #059669;">${patient.current_treatment || 'None'}</div>
                    <div style="font-size: 0.9rem; color: #6b7280; margin-top: 4px;">Current Treatment</div>
                </div>
                <div style="background: #f3f4f6; padding: 16px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 1.2rem; font-weight: 700; color: #0891b2;">${patient.queue_status ? patient.queue_status.replace('_', ' ').toUpperCase() : 'NONE'}</div>
                    <div style="font-size: 0.9rem; color: #6b7280; margin-top: 4px;">Queue Status</div>
                </div>
            </div>
        </div>
        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px;">
            <button onclick="closePatientModal()" class="btn-cancel">Close</button>
        </div>
    `;
    
    document.getElementById('patientModal').style.display = 'flex';
}

function closePatientModal() {
    document.getElementById('patientModal').style.display = 'none';
}

// Close modal on outside click
document.getElementById('patientModal').addEventListener('click', function(e) {
    if (e.target === this) closePatientModal();
});
</script>

<?php require_once 'includes/dentist_layout_end.php'; ?>