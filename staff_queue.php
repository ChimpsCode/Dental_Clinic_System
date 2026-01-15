<?php
$pageTitle = 'Queue Management';
require_once 'includes/staff_layout_start.php';

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
?>

<style>
/* Full Screen Modal Overlay */
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

/* 3D Dental Chart Styles for Modal */
.dental-chart-modal {
    perspective: 1000px;
    padding: 20px;
}

.dental-arch-modal {
    display: flex;
    justify-content: center;
    gap: 4px;
    margin-bottom: 20px;
    transform-style: preserve-3d;
}

.tooth-modal {
    width: 36px;
    height: 48px;
    position: relative;
    transform-style: preserve-3d;
    transform: rotateX(-15deg);
    transition: transform 0.3s ease;
    cursor: default;
}

.tooth-modal.selected {
    transform: rotateX(0deg) scale(1.15);
    z-index: 10;
}

.tooth-modal .tooth-face-modal {
    position: absolute;
    width: 100%;
    height: 100%;
    background: white;
    border: 2px solid #d1d5db;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 600;
    color: #6b7280;
    transition: all 0.2s;
}

.tooth-modal.selected .tooth-face-modal {
    background: #3b82f6;
    border-color: #1d4ed8;
    color: white;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

.tooth-label {
    position: absolute;
    bottom: -24px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 0.65rem;
    color: #9ca3af;
    white-space: nowrap;
}

/* Patient Info Grid */
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

/* Medical Alert Box */
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

/* Action Buttons */
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
            
            // Parse teeth numbers for 3D chart
            const teethNumbers = q.teeth_numbers ? q.teeth_numbers.split(',').map(t => t.trim()) : [];
            
            document.getElementById('modalPatientName').innerText = p.full_name || 'Unknown';
            
            // Generate 3D Dental Chart HTML
            const upperTeeth = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
            const lowerTeeth = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];
            
            let upperChartHTML = '<div class="dental-arch-modal">';
            upperTeeth.forEach(tooth => {
                const isSelected = teethNumbers.includes(tooth.toString());
                upperChartHTML += `
                    <div class="tooth-modal ${isSelected ? 'selected' : ''}">
                        <div class="tooth-face-modal">${tooth}</div>
                    </div>
                `;
            });
            upperChartHTML += '</div>';
            
            let lowerChartHTML = '<div class="dental-arch-modal">';
            lowerTeeth.forEach(tooth => {
                const isSelected = teethNumbers.includes(tooth.toString());
                lowerChartHTML += `
                    <div class="tooth-modal ${isSelected ? 'selected' : ''}">
                        <div class="tooth-face-modal">${tooth}</div>
                    </div>
                `;
            });
            lowerChartHTML += '</div>';
            
            // Check for medical alerts
            const hasMedicalAlert = allergies === 'Yes' || 
                                   medicalConditions.toLowerCase().includes('diabetes') ||
                                   medicalConditions.toLowerCase().includes('heart') ||
                                   medicalConditions.toLowerCase().includes('blood pressure') ||
                                   medicalConditions.toLowerCase().includes('asthma');
            
            document.getElementById('fullScreenModalContent').innerHTML = `
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
                        <div class="patient-info-label">Phone</div>
                        <div class="patient-info-value">${p.phone || 'N/A'}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Email</div>
                        <div class="patient-info-value">${p.email || 'N/A'}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Queue Status</div>
                        <div class="patient-info-value">
                            <span class="status-badge" style="background: ${q.status === 'in_procedure' ? '#dcfce7' : q.status === 'waiting' ? '#fef3c7' : '#f3f4f6'}; color: ${q.status === 'in_procedure' ? '#15803d' : q.status === 'waiting' ? '#d97706' : '#6b7280'}; padding: 4px 12px; border-radius: 9999px; font-size: 0.85rem;">
                                ${q.status ? q.status.replace('_', ' ').toUpperCase() : 'N/A'}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Medical Alert (If applicable) -->
                ${hasMedicalAlert ? `
                <div class="medical-alert">
                    <div class="medical-alert-title">⚠️ Medical Alert - Important for Treatment</div>
                    <div class="medical-alert-grid">
                        <div class="medical-alert-item">
                            <div class="medical-alert-item-label">Allergies</div>
                            <div class="medical-alert-item-value ${allergies === 'Yes' ? 'danger' : ''}">${allergies}</div>
                        </div>
                        <div class="medical-alert-item">
                            <div class="medical-alert-item-label">Diabetes</div>
                            <div class="medical-alert-item-value ${medicalConditions.toLowerCase().includes('diabetes') ? 'danger' : ''}">${medicalConditions.toLowerCase().includes('diabetes') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="medical-alert-item">
                            <div class="medical-alert-item-label">Heart Disease</div>
                            <div class="medical-alert-item-value ${medicalConditions.toLowerCase().includes('heart') ? 'danger' : ''}">${medicalConditions.toLowerCase().includes('heart') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="medical-alert-item">
                            <div class="medical-alert-item-label">High Blood Pressure</div>
                            <div class="medical-alert-item-value ${medicalConditions.toLowerCase().includes('blood pressure') ? 'danger' : ''}">${medicalConditions.toLowerCase().includes('blood pressure') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="medical-alert-item">
                            <div class="medical-alert-item-label">Asthma</div>
                            <div class="medical-alert-item-value ${medicalConditions.toLowerCase().includes('asthma') ? 'danger' : ''}">${medicalConditions.toLowerCase().includes('asthma') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="medical-alert-item">
                            <div class="medical-alert-item-label">Current Medications</div>
                            <div class="medical-alert-item-value">${medications}</div>
                        </div>
                    </div>
                </div>
                ` : ''}

                <!-- Service Requested -->
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
                            <div class="patient-info-label">Queue Time</div>
                            <div class="patient-info-value">${q.queue_time ? new Date(q.queue_time).toLocaleTimeString() : 'N/A'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Notes</div>
                            <div class="patient-info-value">${q.notes || 'None'}</div>
                        </div>
                    </div>
                </div>

                <!-- 3D Dental Chart -->
                <div style="background: #f9fafb; border-radius: 12px; padding: 24px; margin-bottom: 20px;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: #374151; margin-bottom: 20px; text-align: center;">🦷 Dental Chart - Selected Teeth Highlighted</h3>
                    
                    <div style="text-align: center; margin-bottom: 8px; color: #6b7280; font-size: 0.85rem;">Upper Arch (Maxilla)</div>
                    <div class="dental-chart-modal">
                        ${upperChartHTML}
                    </div>
                    
                    <div style="text-align: center; margin: 20px 0 8px 0; color: #6b7280; font-size: 0.85rem;">Lower Arch (Mandible)</div>
                    <div class="dental-chart-modal">
                        ${lowerChartHTML}
                    </div>
                    
                    <div style="display: flex; justify-content: center; gap: 24px; margin-top: 20px; font-size: 0.85rem;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; background: white; border: 2px solid #d1d5db; border-radius: 4px;"></div>
                            <span style="color: #6b7280;">Healthy</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 20px; height: 20px; background: #3b82f6; border: 2px solid #1d4ed8; border-radius: 4px;"></div>
                            <span style="color: #374151; font-weight: 500;">Selected for Treatment</span>
                        </div>
                    </div>
                </div>

                <!-- Dental History -->
                <div style="background: #f9fafb; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
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

                <!-- Address -->
                <div style="background: #f9fafb; border-radius: 12px; padding: 20px;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: #374151; margin-bottom: 16px;">📍 Address</h3>
                    <p style="color: #374151;">${p.address || 'N/A'} ${p.city ? ', ' + p.city : ''} ${p.province ? ', ' + p.province : ''}</p>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons-row">
                    <button onclick="closeFullScreenModal()" class="btn-action btn-action-secondary">Close</button>
                    ${q.status === 'waiting' ? `<button onclick="startProcedure(${q.id}); closeFullScreenModal();" class="btn-action btn-action-primary">Start Procedure</button>` : ''}
                    ${q.status === 'in_procedure' ? `<button onclick="completeTreatment(${q.id}); closeFullScreenModal();" class="btn-action btn-action-success">Mark Complete</button>` : ''}
                </div>
            `;
            
            document.getElementById('fullScreenPatientModal').classList.add('active');
        }
    });
}

function closeFullScreenModal() {
    document.getElementById('fullScreenPatientModal').classList.remove('active');
}

// Close modal on outside click
document.getElementById('fullScreenPatientModal').addEventListener('click', function(e) {
    if (e.target === this) closeFullScreenModal();
});

document.getElementById('callingModal').addEventListener('click', function(e) {
    if (e.target === this) closeCallingModal();
});

// ESC key to close modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeFullScreenModal();
        closeCallingModal();
    }
});
</script>

<?php require_once 'includes/staff_layout_end.php'; ?>
