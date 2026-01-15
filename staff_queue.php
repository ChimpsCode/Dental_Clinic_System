<?php
$pageTitle = 'Queue Management';
require_once 'includes/staff_layout_start.php';

try {
    require_once 'config/database.php';
    
    // Get queue data with patient info
    $stmt = $pdo->query("
        SELECT q.*, p.full_name, p.phone, p.age, p.gender, p.address, p.date_of_birth,
               p.dental_insurance, p.email, p.medical_conditions
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
    <a href="staff_new_admission.php" class="btn-primary" style="text-decoration: none;">+ Add New Patient</a>
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
                        <a href="staff_new_admission.php" class="btn-primary" style="display: inline-block; margin-top: 12px; text-decoration: none;">Add New Patient</a>
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
                            <button onclick="viewPatientRecord(<?php echo $item['patient_id']; ?>)" class="action-btn icon" title="View Record">👁️</button>
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
                            <button onclick="viewPatientRecord(<?php echo $item['patient_id']; ?>)" class="action-btn icon" title="View Record">👁️</button>
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
                            <button onclick="viewPatientRecord(<?php echo $item['patient_id']; ?>)" class="action-btn icon" title="View Record">👁️</button>
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
                            <button onclick="viewPatientRecord(<?php echo $item['patient_id']; ?>)" class="action-btn icon" title="View Record">👁️</button>
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
            <h3>📋 Quick Actions</h3>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <a href="staff_new_admission.php" class="btn-primary" style="width: 100%; text-align: center; text-decoration: none;">+ Add New Patient</a>
                <a href="patient-records.php" class="btn-primary" style="width: 100%; text-align: center; text-decoration: none; background: #6b7280;">View All Records</a>
            </div>
        </div>
        
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

// Patient Record Modal
function viewPatientRecord(patientId) {
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
            const q = data.queue_item || {};
            
            const allergies = m.allergies || 'None';
            const medications = m.current_medications || 'None';
            const medicalConditions = m.medical_conditions || 'None';
            
            document.getElementById('patientRecordContent').innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <h3 style="font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 12px;">Personal Information</h3>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <div><span style="color: #6b7280; font-size: 0.875rem;">Full Name:</span> <span style="font-weight: 500;">${p.full_name || 'N/A'}</span></div>
                            <div><span style="color: #6b7280; font-size: 0.875rem;">Age:</span> <span style="font-weight: 500;">${p.age || 'N/A'} years</span></div>
                            <div><span style="color: #6b7280; font-size: 0.875rem;">Gender:</span> <span style="font-weight: 500;">${p.gender || 'N/A'}</span></div>
                            <div><span style="color: #6b7280; font-size: 0.875rem;">Phone:</span> <span style="font-weight: 500;">${p.phone || 'N/A'}</span></div>
                            <div><span style="color: #6b7280; font-size: 0.875rem;">Email:</span> <span style="font-weight: 500;">${p.email || 'N/A'}</span></div>
                        </div>
                    </div>
                    <div>
                        <h3 style="font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 12px;">Service Requested</h3>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <div><span style="color: #6b7280; font-size: 0.875rem;">Treatment:</span> <span style="font-weight: 500;">${q.treatment_type || 'N/A'}</span></div>
                            <div><span style="color: #6b7280; font-size: 0.875rem;">Teeth Numbers:</span> <span style="font-weight: 500;">${q.teeth_numbers || 'N/A'}</span></div>
                            <div><span style="color: #6b7280; font-size: 0.875rem;">Status:</span> 
                                <span class="status-badge" style="background: ${q.status === 'in_procedure' ? '#dcfce7' : q.status === 'waiting' ? '#fef3c7' : '#f3f4f6'}; color: ${q.status === 'in_procedure' ? '#15803d' : q.status === 'waiting' ? '#d97706' : '#6b7280'};">
                                    ${q.status || 'N/A'}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Medical History - IMPORTANT FOR DOCTOR -->
                <div style="margin-top: 20px;">
                    <h3 style="font-size: 0.875rem; font-weight: 600; color: #dc2626; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                        ⚠️ Medical History (Important)
                    </h3>
                    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div>
                                <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 4px;">Allergies</div>
                                <div style="font-weight: 500; ${allergies === 'Yes' ? 'color: #dc2626;' : ''}">${allergies}</div>
                            </div>
                            <div>
                                <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 4px;">Medical Conditions</div>
                                <div style="font-weight: 500;">${medicalConditions}</div>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 4px;">Current Medications</div>
                                <div style="font-weight: 500;">${medications}</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Dental History -->
                <div style="margin-top: 20px;">
                    <h3 style="font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 12px;">Dental History</h3>
                    <div style="background: #f9fafb; border-radius: 8px; padding: 16px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div>
                                <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 4px;">Previous Dentist</div>
                                <div style="font-weight: 500;">${d.previous_dentist || 'N/A'}</div>
                            </div>
                            <div>
                                <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 4px;">Last Visit</div>
                                <div style="font-weight: 500;">${d.last_visit_date || 'N/A'}</div>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 4px;">Current Complaints</div>
                                <div style="font-weight: 500;">${d.current_complaints || 'None'}</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Address -->
                <div style="margin-top: 20px;">
                    <h3 style="font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 12px;">Address</h3>
                    <div style="font-weight: 500;">${p.address || 'N/A'} ${p.city ? ', ' + p.city : ''} ${p.province ? ', ' + p.province : ''}</div>
                </div>
            `;
            
            document.getElementById('patientRecordModal').style.display = 'flex';
        }
    });
}

function closePatientRecordModal() {
    document.getElementById('patientRecordModal').style.display = 'none';
}

// Close modals on outside click
document.getElementById('patientRecordModal').addEventListener('click', function(e) {
    if (e.target === this) closePatientRecordModal();
});

document.getElementById('callingModal').addEventListener('click', function(e) {
    if (e.target === this) closeCallingModal();
});
</script>

<?php require_once 'includes/staff_layout_end.php'; ?>
