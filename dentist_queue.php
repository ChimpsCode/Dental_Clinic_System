<?php
$pageTitle = 'Queue Management';

try {
    require_once 'config/database.php';
    
    // Get queue data with patient info
    $stmt = $pdo->query("
        SELECT q.*, p.full_name, p.phone, p.age, p.gender, p.address, p.date_of_birth,
               p.dental_insurance, p.email, p.medical_conditions, p.middle_name, p.suffix,
               p.city, p.province, p.religion
        FROM queue q 
        LEFT JOIN patients p ON q.patient_id = p.id 
        ORDER BY 
            CASE q.status 
                WHEN 'in_procedure' THEN 1 
                WHEN 'waiting' THEN 2 
                WHEN 'on_hold' THEN 3 
                WHEN 'completed' THEN 4 
                WHEN 'cancelled' THEN 5 
            END,
            q.queue_time ASC
    ");
    $queueItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $waitingCount = count(array_filter($queueItems, fn($q) => $q['status'] === 'waiting'));
    $procedureCount = count(array_filter($queueItems, fn($q) => $q['status'] === 'in_procedure'));
    $completedToday = count(array_filter($queueItems, fn($q) => $q['status'] === 'completed'));
    $onHoldCount = count(array_filter($queueItems, fn($q) => $q['status'] === 'on_hold'));
    
} catch (Exception $e) {
    $queueItems = [];
    $waitingCount = 0;
    $procedureCount = 0;
    $completedToday = 0;
    $onHoldCount = 0;
}

require_once 'includes/dentist_layout_start.php';
?>

<!-- Queue Stats -->
<div class="summary-cards">
    <div class="summary-card">
        <div class="summary-icon yellow">⏰</div>
        <div class="summary-info">
            <h3><?php echo $waitingCount; ?></h3>
            <p>Currently Waiting</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon blue" style="background: #dbeafe; color: #2563eb;">⚕️</div>
        <div class="summary-info">
            <h3><?php echo $procedureCount; ?></h3>
            <p>In Procedure</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon green">✓</div>
        <div class="summary-info">
            <h3><?php echo $completedToday; ?></h3>
            <p>Completed Today</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon gray">⏸️</div>
        <div class="summary-info">
            <h3><?php echo $onHoldCount; ?></h3>
            <p>On Hold</p>
        </div>
    </div>
</div>

<!-- Search & Filters -->
<div class="search-filters">
    <input type="text" id="searchInput" placeholder="Search by name, treatment..." class="search-input" style="flex: 1;">
    <select id="statusFilter" class="filter-select">
        <option value="">All Status</option>
        <option value="waiting">Waiting</option>
        <option value="in_procedure">In Procedure</option>
        <option value="completed">Completed</option>
        <option value="on_hold">On Hold</option>
        <option value="cancelled">Cancelled</option>
    </select>
</div>

<!-- Queue Sections -->
<div class="two-column">
    <div class="left-column">
        <!-- Waiting Queue -->
        <div class="section-card">
            <h2 class="section-title">⏳ Waiting Queue</h2>
            <div class="patient-list" id="waitingList">
                <?php 
                $waitingItems = array_filter($queueItems, fn($q) => $q['status'] === 'waiting');
                if (empty($waitingItems)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: #6b7280;">
                        <p>No patients waiting</p>
                    </div>
                <?php else: 
                    foreach ($waitingItems as $item): ?>
                    <div class="patient-item" 
                         data-name="<?php echo strtolower($item['full_name'] ?? ''); ?>" 
                         data-status="waiting"
                         data-treatment="<?php echo strtolower($item['treatment_type'] ?? ''); ?>">
                        <div class="patient-info">
                            <div class="patient-name"><?php echo htmlspecialchars($item['full_name'] ?? 'Unknown'); ?></div>
                            <div class="patient-details">
                                <span class="status-badge" style="background: #fef3c7; color: #d97706; font-size: 0.75rem;">Waiting</span>
                                <span style="color: #6b7280; margin-left: 8px;">
                                    <?php echo htmlspecialchars($item['treatment_type'] ?? 'Consultation'); ?>
                                </span>
                            </div>
                            <?php if (!empty($item['teeth_numbers'])): ?>
                                <div class="patient-treatment" style="font-size: 0.85rem; color: #6b7280;">
                                    Teeth: <?php echo htmlspecialchars($item['teeth_numbers']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="patient-actions">
                            <button onclick="callPatient(<?php echo $item['id']; ?>)" class="action-btn icon" title="Call">📞</button>
                            <button onclick="startProcedure(<?php echo $item['id']; ?>)" class="action-btn icon" title="Start Procedure">▶️</button>
                            <button onclick="viewFullPatientRecord(<?php echo $item['patient_id']; ?>, <?php echo $item['id']; ?>)" class="action-btn icon" title="See Details">👁️</button>
                            <button onclick="moveToOnHold(<?php echo $item['id']; ?>)" class="action-btn icon" title="On Hold">⏸️</button>
                            <button onclick="cancelPatient(<?php echo $item['id']; ?>)" class="action-btn icon" title="Cancel">✕</button>
                        </div>
                    </div>
                    <?php endforeach; 
                endif; ?>
            </div>
        </div>

        <!-- In Procedure -->
        <div class="section-card">
            <h2 class="section-title">⚕️ In Procedure</h2>
            <div class="patient-list" id="procedureList">
                <?php 
                $procedureItems = array_filter($queueItems, fn($q) => $q['status'] === 'in_procedure');
                if (empty($procedureItems)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: #6b7280;">
                        <p>No patients in procedure</p>
                    </div>
                <?php else: 
                    foreach ($procedureItems as $item): ?>
                    <div class="patient-item" 
                         data-name="<?php echo strtolower($item['full_name'] ?? ''); ?>" 
                         data-status="in_procedure"
                         style="border-left: 4px solid #0ea5e9;">
                        <div class="patient-info">
                            <div class="patient-name"><?php echo htmlspecialchars($item['full_name'] ?? 'Unknown'); ?></div>
                            <div class="patient-details">
                                <span class="status-badge" style="background: #dcfce7; color: #15803d; font-size: 0.75rem;">In Chair</span>
                                <span style="color: #6b7280; margin-left: 8px;">
                                    <?php echo htmlspecialchars($item['treatment_type'] ?? ''); ?>
                                </span>
                            </div>
                            <?php if (!empty($item['teeth_numbers'])): ?>
                                <div class="patient-treatment" style="font-size: 0.85rem; color: #6b7280;">
                                    Teeth: <?php echo htmlspecialchars($item['teeth_numbers']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="patient-actions">
                            <button onclick="completeTreatment(<?php echo $item['id']; ?>)" class="action-btn" style="background: #22c55e; color: white; padding: 6px 12px;" title="Complete">✓ Done</button>
                            <button onclick="viewFullPatientRecord(<?php echo $item['patient_id']; ?>, <?php echo $item['id']; ?>)" class="action-btn icon" title="See Details">👁️</button>
                        </div>
                    </div>
                    <?php endforeach; 
                endif; ?>
            </div>
        </div>

        <!-- On Hold -->
        <div class="section-card">
            <h2 class="section-title">⏸️ On Hold</h2>
            <div class="patient-list" id="onholdList">
                <?php 
                $onholdItems = array_filter($queueItems, fn($q) => $q['status'] === 'on_hold');
                if (empty($onholdItems)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: #6b7280;">
                        <p>No patients on hold</p>
                    </div>
                <?php else: 
                    foreach ($onholdItems as $item): ?>
                    <div class="patient-item" 
                         data-name="<?php echo strtolower($item['full_name'] ?? ''); ?>" 
                         data-status="on_hold"
                         style="opacity: 0.7;">
                        <div class="patient-info">
                            <div class="patient-name"><?php echo htmlspecialchars($item['full_name'] ?? 'Unknown'); ?></div>
                            <div class="patient-details">
                                <span class="status-badge" style="background: #f3f4f6; color: #6b7280; font-size: 0.75rem;">On Hold</span>
                                <span style="color: #6b7280; margin-left: 8px;">
                                    <?php echo htmlspecialchars($item['treatment_type'] ?? ''); ?>
                                </span>
                            </div>
                        </div>
                        <div class="patient-actions">
                            <button onclick="requeuePatient(<?php echo $item['id']; ?>)" class="action-btn" style="background: #84cc16; color: white; padding: 6px 12px; border: none; border-radius: 4px; font-size: 0.8rem;">Re-queue</button>
                            <button onclick="viewFullPatientRecord(<?php echo $item['patient_id']; ?>, <?php echo $item['id']; ?>)" class="action-btn icon" title="See Details">👁️</button>
                        </div>
                    </div>
                    <?php endforeach; 
                endif; ?>
            </div>
        </div>

        <!-- Completed Today -->
        <div class="section-card">
            <h2 class="section-title">✅ Completed Today</h2>
            <div class="patient-list" id="completedList">
                <?php 
                $completedItems = array_filter($queueItems, fn($q) => $q['status'] === 'completed');
                if (empty($completedItems)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: #6b7280;">
                        <p>No patients completed today</p>
                    </div>
                <?php else: 
                    foreach ($completedItems as $item): ?>
                    <div class="patient-item" style="opacity: 0.7;">
                        <div class="patient-info">
                            <div class="patient-name"><?php echo htmlspecialchars($item['full_name'] ?? 'Unknown'); ?></div>
                            <div class="patient-details">
                                <span class="status-badge" style="background: #e5e7eb; color: #6b7280; font-size: 0.75rem;">Completed</span>
                                <span style="color: #6b7280; margin-left: 8px;">
                                    <?php echo htmlspecialchars($item['treatment_type'] ?? ''); ?>
                                </span>
                            </div>
                        </div>
                        <div class="patient-actions">
                            <button onclick="viewFullPatientRecord(<?php echo $item['patient_id']; ?>, <?php echo $item['id']; ?>)" class="action-btn icon" title="See Details">👁️</button>
                        </div>
                    </div>
                    <?php endforeach; 
                endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div class="right-column">
        <div class="notification-box">
            <h3>📊 Queue Summary</h3>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f3f4f6;">
                    <span style="color: #6b7280;">Total in Queue</span>
                    <span style="font-weight: 600;"><?php echo count($queueItems); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f3f4f6;">
                    <span style="color: #6b7280;">Waiting</span>
                    <span style="font-weight: 600;"><?php echo $waitingCount; ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f3f4f6;">
                    <span style="color: #6b7280;">In Procedure</span>
                    <span style="font-weight: 600;"><?php echo $procedureCount; ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 8px 0;">
                    <span style="color: #6b7280;">Completed</span>
                    <span style="font-weight: 600;"><?php echo $completedToday; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Full Screen Patient Record Modal -->
<div id="fullScreenPatientModal" class="fullscreen-modal-overlay">
    <div class="fullscreen-modal-content">
        <div class="fullscreen-modal-header">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 600; margin: 0;">Patient Record Details</h2>
                <p id="modalPatientName" style="color: #6b7280; margin: 4px 0 0 0; font-size: 0.9rem;"></p>
            </div>
            <button onclick="closeFullScreenModal()" class="fullscreen-modal-close">&times;</button>
        </div>
        <div class="fullscreen-modal-body" id="fullScreenModalContent">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<!-- Calling Modal -->
<div id="callingModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div class="modal" style="background: white; border-radius: 12px; padding: 40px; width: 90%; max-width: 400px; text-align: center;">
        <div style="font-size: 4rem; margin-bottom: 20px;">📢</div>
        <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 10px;">Calling Patient</h2>
        <p id="callingPatientName" style="font-size: 1.25rem; color: #374151; margin-bottom: 20px;"></p>
        <p style="color: #6b7280; margin-bottom: 20px;">Please proceed to the dental chair</p>
        <button onclick="closeCallingModal()" class="btn-cancel" style="padding: 10px 24px;">Close</button>
    </div>
</div>

<script>
const queueItems = <?php echo json_encode($queueItems); ?>;
let currentQueueItem = null;

// Search and filter functionality
document.getElementById('searchInput').addEventListener('input', filterQueue);
document.getElementById('statusFilter').addEventListener('change', filterQueue);

function filterQueue() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    
    document.querySelectorAll('.patient-item').forEach(item => {
        const nameMatch = !search || item.dataset.name.includes(search);
        const statusMatch = !status || item.dataset.status === status;
        item.style.display = (nameMatch && statusMatch) ? 'flex' : 'none';
    });
}

// Queue Actions
function callPatient(queueId) {
    const item = queueItems.find(q => q.id == queueId);
    if (!item) return;
    
    document.getElementById('callingPatientName').innerText = item.full_name;
    document.getElementById('callingModal').style.display = 'flex';
    
    fetch('queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'call', queue_id: queueId })
    });
}

function closeCallingModal() {
    document.getElementById('callingModal').style.display = 'none';
}

function startProcedure(queueId) {
    if (!confirm('Move patient to In Procedure?')) return;
    
    fetch('queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'start_procedure', queue_id: queueId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message);
        }
    });
}

function completeTreatment(queueId) {
    if (!confirm('Mark treatment as complete?\n\nPatient will be moved to Completed.')) return;
    
    fetch('queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'complete', queue_id: queueId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message);
        }
    });
}

function moveToOnHold(queueId) {
    if (!confirm('Put patient on hold?\n\nThey can be re-queued later.')) return;
    
    fetch('queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'on_hold', queue_id: queueId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message);
        }
    });
}

function cancelPatient(queueId) {
    if (!confirm('Cancel this patient?\n\nThis cannot be undone.')) return;
    
    fetch('queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'cancel', queue_id: queueId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message);
        }
    });
}

function requeuePatient(queueId) {
    fetch('queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'requeue', queue_id: queueId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message);
        }
    });
}

// Full Screen Patient Record Modal with 3D Teeth Chart
function viewFullPatientRecord(patientId, queueId) {
    currentQueueItem = queueItems.find(q => q.id == queueId);
    
    fetch('queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_patient_record', patient_id: patientId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const p = data.patient;
            const m = data.medical_history || {};
            const d = data.dental_history || {};
            const q = currentQueueItem || {};
            
            const allergies = m.allergies || 'None';
            const medications = m.current_medications || 'None';
            const medicalConditions = m.medical_conditions || 'None';
            
            document.getElementById('modalPatientName').innerText = p ? `${p.full_name} | ${p.phone || 'No phone'} | ${p.age ? $p.age + ' years old' : ''}` : 'Unknown';
            
            const selectedTeeth = q.teeth_numbers ? q.teeth_numbers.split(',').map(t => t.trim()) : [];
            
            const teethUpper = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
            const teethLower = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];
            
            let teethHtml = '<div style="display: flex; flex-direction: column; gap: 30px;">';
            
            teethHtml += '<div><h4 style="margin-bottom: 12px; color: #374151;">Upper Teeth (Maxillary)</h4><div style="display: flex; justify-content: center; gap: 4px; flex-wrap: wrap;">';
            teethUpper.forEach(t => {
                const isSelected = selectedTeeth.includes(String(t)) || selectedTeeth.includes('0' + t);
                teethHtml += `<div style="width: 36px; height: 48px; background: ${isSelected ? '#3b82f6' : 'white'}; border: 2px solid ${isSelected ? '#1d4ed8' : '#d1d5db'}; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 600; color: ${isSelected ? 'white' : '#6b7280'};">${t}</div>`;
            });
            teethHtml += '</div></div>';
            
            teethHtml += '<div><h4 style="margin-bottom: 12px; color: #374151;">Lower Teeth (Mandibular)</h4><div style="display: flex; justify-content: center; gap: 4px; flex-wrap: wrap;">';
            teethLower.forEach(t => {
                const isSelected = selectedTeeth.includes(String(t)) || selectedTeeth.includes('0' + t);
                teethHtml += `<div style="width: 36px; height: 48px; background: ${isSelected ? '#3b82f6' : 'white'}; border: 2px solid ${isSelected ? '#1d4ed8' : '#d1d5db'}; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 600; color: ${isSelected ? 'white' : '#6b7280'};">${t}</div>`;
            });
            teethHtml += '</div></div>';
            
            teethHtml += '</div>';
            
            const hasMedicalConditions = m.medical_conditions && m.medical_conditions !== 'None' && m.medical_conditions !== '';
            
            document.getElementById('fullScreenModalContent').innerHTML = `
                ${hasMedicalConditions ? `
                <div class="medical-alert">
                    <div class="medical-alert-title">⚠️ Medical Alert</div>
                    <div class="medical-alert-grid">
                        <div class="medical-alert-item">
                            <div class="medical-alert-item-label">Conditions</div>
                            <div class="medical-alert-item-value danger">${m.medical_conditions || 'None'}</div>
                        </div>
                        <div class="medical-alert-item">
                            <div class="medical-alert-item-label">Allergies</div>
                            <div class="medical-alert-item-value danger">${allergies}</div>
                        </div>
                        <div class="medical-alert-item">
                            <div class="medical-alert-item-label">Medications</div>
                            <div class="medical-alert-item-value">${medications}</div>
                        </div>
                        <div class="medical-alert-item">
                            <div class="medical-alert-item-label">Blood Pressure</div>
                            <div class="medical-alert-item-value">${m.blood_pressure || 'N/A'}</div>
                        </div>
                    </div>
                </div>
                ` : ''}
                
                <div class="patient-info-grid">
                    <div class="patient-info-item">
                        <div class="patient-info-label">Full Name</div>
                        <div class="patient-info-value">${p ? p.full_name : 'Unknown'}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Phone</div>
                        <div class="patient-info-value">${p ? p.phone : 'N/A'}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Age</div>
                        <div class="patient-info-value">${p ? p.age : 'N/A'}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Gender</div>
                        <div class="patient-info-value">${p ? p.gender : 'N/A'}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Date of Birth</div>
                        <div class="patient-info-value">${p ? p.date_of_birth : 'N/A'}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Email</div>
                        <div class="patient-info-value">${p ? p.email : 'N/A'}</div>
                    </div>
                </div>
                
                <div style="background: #f9fafb; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
                    <h4 style="margin-bottom: 16px; color: #374151;">Current Queue Treatment</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                        <div>
                            <div style="color: #6b7280; font-size: 0.875rem;">Treatment Type</div>
                            <div style="font-weight: 600; font-size: 1.1rem;">${q.treatment_type || 'General Consultation'}</div>
                        </div>
                        <div>
                            <div style="color: #6b7280; font-size: 0.875rem;">Teeth</div>
                            <div style="font-weight: 600;">${q.teeth_numbers || 'Not specified'}</div>
                        </div>
                        <div>
                            <div style="color: #6b7280; font-size: 0.875rem;">Status</div>
                            <div style="font-weight: 600; text-transform: capitalize;">${q.status || 'N/A'}</div>
                        </div>
                        <div>
                            <div style="color: #6b7280; font-size: 0.875rem;">Priority</div>
                            <div style="font-weight: 600;">${q.priority || 'Normal'}</div>
                        </div>
                        ${q.notes ? `
                        <div style="grid-column: 1 / -1;">
                            <div style="color: #6b7280; font-size: 0.875rem;">Notes</div>
                            <div>${q.notes}</div>
                        </div>
                        ` : ''}
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <h4 style="margin-bottom: 16px; color: #374151;">Teeth Chart</h4>
                    ${teethHtml}
                </div>
                
                <div style="background: #f9fafb; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
                    <h4 style="margin-bottom: 16px; color: #374151;">Dental History</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                        <div>
                            <div style="color: #6b7280; font-size: 0.875rem;">Last Visit</div>
                            <div>${d.last_visit_date ? new Date(d.last_visit_date).toLocaleDateString() : 'No record'}</div>
                        </div>
                        <div>
                            <div style="color: #6b7280; font-size: 0.875rem;">Previous Treatment</div>
                            <div>${d.previous_treatment || 'No record'}</div>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <div style="color: #6b7280; font-size: 0.875rem;">Dentist Notes</div>
                            <div>${d.notes || 'No notes'}</div>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                    ${q.status === 'waiting' ? `<button onclick="closeFullScreenModal(); startProcedure(${q.id});" class="btn-action btn-action-primary">Start Procedure</button>` : ''}
                    ${q.status === 'in_procedure' ? `<button onclick="closeFullScreenModal(); completeTreatment(${q.id});" class="btn-action btn-action-success">Complete Treatment</button>` : ''}
                    ${q.status === 'waiting' || q.status === 'in_procedure' ? `<button onclick="closeFullScreenModal(); moveToOnHold(${q.id});" class="btn-action btn-action-secondary">Put On Hold</button>` : ''}
                    ${q.status === 'on_hold' ? `<button onclick="closeFullScreenModal(); requeuePatient(${q.id});" class="btn-action btn-action-primary">Re-queue</button>` : ''}
                    <button onclick="closeFullScreenModal()" class="btn-action btn-action-secondary">Close</button>
                </div>
            `;
            
            document.getElementById('fullScreenPatientModal').classList.add('active');
        }
    });
}

function closeFullScreenModal() {
    document.getElementById('fullScreenPatientModal').classList.remove('active');
    currentQueueItem = null;
}

// Close modal when clicking outside
document.getElementById('fullScreenPatientModal').addEventListener('click', function(e) {
    if (e.target === this) closeFullScreenModal();
});
</script>

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

.medical-alert-item-label {
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 4px;
}

.medical-alert-item-value {
    font-weight: 600;
}

.medical-alert-item-value.danger {
    color: #dc2626;
}

.action-buttons-row {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.btn-action {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}

.btn-action-primary {
    background: #3b82f6;
    color: white;
}

.btn-action-primary:hover {
    background: #2563eb;
}

.btn-action-success {
    background: #22c55e;
    color: white;
}

.btn-action-success:hover {
    background: #16a34a;
}

.btn-action-danger {
    background: #ef4444;
    color: white;
}

.btn-action-danger:hover {
    background: #dc2626;
}

.btn-action-secondary {
    background: #f3f4f6;
    color: #374151;
}

.btn-action-secondary:hover {
    background: #e5e7eb;
}
</style>

<?php require_once 'includes/dentist_layout_end.php'; ?>
