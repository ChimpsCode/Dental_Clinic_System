<?php
$pageTitle = 'Dashboard';
require_once 'config/database.php';
require_once 'includes/staff_layout_start.php';

try {
    // Get queue data - same query as queue management
    $stmt = $pdo->query("
        SELECT q.*, p.first_name, p.middle_name, p.last_name, p.suffix, p.phone
        FROM queue q 
        LEFT JOIN patients p ON q.patient_id = p.id 
        WHERE q.status IN ('waiting', 'in_procedure', 'completed', 'on_hold', 'cancelled')
        AND DATE(q.created_at) = CURDATE()
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
    $completedCount = count(array_filter($queueItems, fn($q) => $q['status'] === 'completed'));
    $onHoldCount = count(array_filter($queueItems, fn($q) => $q['status'] === 'on_hold'));
    $cancelledCount = count(array_filter($queueItems, fn($q) => $q['status'] === 'cancelled'));
    
    $inProcedureItem = array_filter($queueItems, fn($q) => $q['status'] === 'in_procedure');
    $inProcedureItem = !empty($inProcedureItem) ? reset($inProcedureItem) : null;

    $waitingItems = array_filter($queueItems, fn($q) => $q['status'] === 'waiting');

    // Helper function to build full name
    function buildPatientName($item) {
        return trim(($item['first_name'] ?? '') . ' ' . ($item['middle_name'] ?? '') . ' ' . ($item['last_name'] ?? '') . ' ' . ($item['suffix'] ?? ''));
    }
    
} catch (Exception $e) {
    $queueItems = [];
    $waitingCount = 0;
    $procedureCount = 0;
    $completedCount = 0;
    $onHoldCount = 0;
    $cancelledCount = 0;
    $inProcedureItem = null;
    $waitingItems = [];
}
?>

<!-- VIEW 1: DASHBOARD -->
<div id="view-dashboard" class="view-section active">
    <!-- Summary Cards -->
    <div class="summary-cards">
        <div class="summary-card">
            <div class="summary-icon yellow">⏰</div>
            <div class="summary-info">
                <h3 id="count-waiting"><?php echo $waitingCount; ?></h3>
                <p>Waiting</p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon green">✓</div>
            <div class="summary-info">
                <h3 id="count-completed"><?php echo $completedCount; ?></h3>
                <p>Completed</p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon red" style="cursor: pointer;" onclick="openCancelledModal()">⚠️</div>
            <div class="summary-info">
                <h3 id="count-cancelled"><?php echo $cancelledCount; ?></h3>
                <p style="cursor: pointer;" onclick="openCancelledModal()">Cancelled</p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon gray">⏸️</div>
            <div class="summary-info">
                <h3 id="count-skipped"><?php echo $onHoldCount; ?></h3>
                <p>On Hold</p>
            </div>
        </div>
    </div>

    <!-- Cancelled Modal -->
    <div id="cancelledModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
        <div class="modal" style="background: white; border-radius: 12px; padding: 0; width: 90%; max-width: 700px; max-height: 80vh; overflow-y: auto; position: relative; z-index: 100000;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 1.25rem; font-weight: 600; margin: 0;">Cancelled / On Hold</h2>
                <button onclick="closeCancelledModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">×</button>
            </div>
            <div style="display: flex; gap: 12px; margin-bottom: 20px;">
                <button onclick="filterCancelled('today')" id="cancelled-today-btn" style="padding: 8px 24px; border: 1px solid #d1d5db; border-radius: 6px; background: #2563eb; color: white; cursor: pointer;">Today</button>
                <button onclick="filterCancelled('week')" id="cancelled-week-btn" style="padding: 8px 24px; border: 1px solid #d1d5db; border-radius: 6px; background: white; color: #374151; cursor: pointer;">This Week</button>
            </div>
            <div id="cancelled-modal-list">
                <div id="cancelled-today-list">
                    <?php 
                    $cancelledItems = array_filter($queueItems, fn($q) => $q['status'] === 'cancelled');
                    if (empty($cancelledItems)): ?>
                        <p style="text-align: center; color: #6b7280; padding: 20px;">No cancelled patients today</p>
                    <?php else: ?>
                <?php foreach ($cancelledItems as $item):
                                $cancelledPatientName = buildPatientName($item);
                        ?>
                        <div class="patient-item" id="cancelled-<?php echo $item['id']; ?>">
                            <div class="patient-info">
                                <div class="patient-name" style="text-decoration: line-through; color: #9ca3af;"><?php echo htmlspecialchars($cancelledPatientName ?: 'Unknown'); ?></div>
                                <div class="patient-details">
                                    <span class="status-badge cancelled">Cancelled</span>
                                </div>
                                <div class="patient-treatment"><?php echo htmlspecialchars($item['treatment_type'] ?? ''); ?></div>
                            </div>
                            <div class="patient-actions">
                                <button class="action-btn icon view-btn">👁️</button>
                                <button class="action-btn" style="background: #84cc16; color: white; padding: 8px 12px; border-radius: 6px; border: none; font-size: 12px; cursor: pointer;" onclick="requeuePatientDashboard(<?php echo $item['id']; ?>)">Re-queue</button>
                                <button class="action-btn icon delete-btn">🗑️</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div id="cancelled-week-list" style="display: none;">
                    <p style="text-align: center; color: #6b7280; padding: 20px;">No cancelled patients this week</p>
                </div>
            </div>
        </div>
    </div>

    <div class="two-column">
        <div class="left-column">
            <!-- Live Queue Controller -->
            <div class="live-queue">
                <div class="live-queue-header">
                    <div class="live-queue-title">Live Queue Controller</div>
                    <div class="now-serving-badge"><span>👁️</span><span>Now Serving</span></div>
                </div>
                <div class="live-patient">
                    <?php $inProcedureName = $inProcedureItem ? buildPatientName($inProcedureItem) : ''; ?>
                    <div class="patient-name" id="live-patient-name"><?php echo $inProcedureItem ? htmlspecialchars($inProcedureName) : 'No Patient'; ?></div>
                    <div class="patient-details" style="margin-top: 10px;">
                        <span class="status-badge now-serving">In Chair</span>
                        <span class="patient-time" id="live-patient-time"><?php echo $inProcedureItem ? htmlspecialchars($inProcedureItem['queue_time']) : '--:--'; ?></span>
                    </div>
                    <div class="patient-treatment" id="live-patient-treatment" style="margin-top: 8px;"><?php echo $inProcedureItem ? htmlspecialchars($inProcedureItem['treatment_type']) : 'Queue Empty'; ?></div>
                </div>
                <?php if ($inProcedureItem): ?>
                <button class="complete-btn" id="completeTreatmentBtn" onclick="completeCurrentPatient(<?php echo $inProcedureItem['id']; ?>)">
                    <span>✓</span> <span>Complete Treatment</span>
                </button>
                <?php else: ?>
                <button class="complete-btn" id="completeTreatmentBtn" disabled style="background: #9ca3af;">
                    <span>✓</span> <span>No Patient in Chair</span>
                </button>
                <?php endif; ?>
                <div class="complete-btn-text">Click when patient treatment is finished</div>
            </div>

            <!-- Up Next -->
            <div class="section-card">
                <h2 class="section-title">⏭️ Up Next</h2>
                <div class="patient-list" id="up-next-list">
                    <?php if (empty($waitingItems)): ?>
                        <div style="text-align: center; padding: 0; color: #6b7280;">
                            <p>No patients waiting</p>
                            <a href="staff_new_admission.php" class="btn-primary" style="display: inline-block; margin-top: 12px; text-decoration: none;">Add New Patient</a>
                        </div>
                <?php else: ?>
                        <?php foreach (array_slice($waitingItems, 0, 5) as $index => $item):
                            $waitingPatientName = buildPatientName($item);
                        ?>
                        <div class="patient-item" id="next-<?php echo $item['id']; ?>">
                            <div class="patient-info">
                                <div class="patient-name"><?php echo htmlspecialchars($waitingPatientName ?: 'Unknown'); ?></div>
                                <div class="patient-details">
                                    <span class="status-badge waiting">Waiting</span>
                                    <span class="patient-time"><?php echo htmlspecialchars($item['queue_time'] ?? ''); ?></span>
                                </div>
                                <div class="patient-treatment"><?php echo htmlspecialchars($item['treatment_type'] ?? ''); ?></div>
                            </div>
                            <div class="patient-actions">
                                <button class="action-btn text-btn" onclick="viewPatientDashboard(<?php echo $item['patient_id']; ?>)">View</button>
                                <button class="action-btn text-btn" onclick="moveToOnHoldDashboard(<?php echo $item['id']; ?>)">On Hold</button>
                                <button class="action-btn text-btn" onclick="cancelPatientDashboard(<?php echo $item['id']; ?>)">Cancel</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- On Hold -->
            <div class="section-card">
                <h2 class="section-title">⏸️ On Hold</h2>
                <div class="patient-list" id="onhold-list">
                    <?php 
                    $onHoldItems = array_filter($queueItems, fn($q) => $q['status'] === 'on_hold');
                    if (empty($onHoldItems)): ?>
                        <div style="text-align: center; padding: 0; color: #6b7280;">
                            <p>No patients on hold</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($onHoldItems as $item):
                            $onHoldPatientName = buildPatientName($item);
                        ?>
                        <div class="patient-item" id="onhold-<?php echo $item['id']; ?>">
                            <div class="patient-info">
                                <div class="patient-name"><?php echo htmlspecialchars($onHoldPatientName ?: 'Unknown'); ?></div>
                                <div class="patient-details">
                                    <span class="status-badge cancelled">On Hold</span>
                                </div>
                                <div class="patient-treatment"><?php echo htmlspecialchars($item['treatment_type'] ?? ''); ?></div>
                            </div>
                            <div class="patient-actions">
                                <button class="action-btn icon view-btn">👁️</button>
                                <button class="action-btn" style="background: #84cc16; color: white; padding: 8px 12px; border-radius: 6px; border: none; font-size: 12px; cursor: pointer;" onclick="requeuePatientDashboard(<?php echo $item['id']; ?>)">Re-queue</button>
                                <button class="action-btn icon delete-btn">🗑️</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Notifications & Reminders -->
        <div class="right-column">
            <div class="notification-box">
                <h3>🔔 Quick Links</h3>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <a href="staff_queue.php" class="btn-primary" style="width: 100%; text-align: center; text-decoration: none;">View Full Queue</a>
                    <a href="staff_new_admission.php" class="btn-primary" style="width: 100%; text-align: center; text-decoration: none; background: #6b7280;">+ New Patient</a>
                </div>
            </div>
              
            <div class="notification-box">
                <h3>✓ Daily Reminders / To-Do</h3>
                <ul class="reminder-list" id="reminder-list">
                    <li onclick="this.classList.toggle('checked')">Reply to Facebook inquiries from last night</li>
                    <li onclick="this.classList.toggle('checked')">Print consent form for tomorrow</li>
                    <li onclick="this.classList.toggle('checked')">Call inquiries for new stocks of Composites</li>
                </ul>
                <button class="add-reminder-btn" id="addReminderBtn">+ Add New Reminder</button>
            </div>
        </div>
    </div>
</div>

<!-- Patient Record Modal -->
<div id="patientRecordModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div class="modal" style="background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 700px; max-height: 85vh; overflow-y: auto; position: relative; z-index: 100000;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="font-size: 1.25rem; font-weight: 600; margin: 0;">Patient Record</h2>
            <button onclick="closePatientRecordModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">×</button>
        </div>
        <div id="patientRecordContent"></div>
    </div>
</div>

<script>
const queueItems = <?php echo json_encode($queueItems); ?>;

// Portal Pattern: Move modals to body level to escape stacking context
// This ensures modals appear above sidebar and all other elements
(function() {
    const cancelledModal = document.getElementById('cancelledModal');
    const patientRecordModal = document.getElementById('patientRecordModal');
    
    if (cancelledModal) {
        document.body.appendChild(cancelledModal);
    }
    if (patientRecordModal) {
        document.body.appendChild(patientRecordModal);
    }
})();

// Dashboard Queue Actions
function completeCurrentPatient(queueId) {
    if (!confirm('Mark treatment as complete?')) return;
    
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

function moveToOnHoldDashboard(queueId) {
    if (!confirm('Put patient on hold?')) return;
    
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

function cancelPatientDashboard(queueId) {
    if (!confirm('Cancel this patient?')) return;
    
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

function requeuePatientDashboard(queueId) {
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

// Cancelled Modal Functions
function openCancelledModal() {
    document.getElementById('cancelledModal').style.display = 'flex';
}

function closeCancelledModal() {
    document.getElementById('cancelledModal').style.display = 'none';
}

function filterCancelled(filter) {
    const todayBtn = document.getElementById('cancelled-today-btn');
    const weekBtn = document.getElementById('cancelled-week-btn');
    const todayList = document.getElementById('cancelled-today-list');
    const weekList = document.getElementById('cancelled-week-list');
    
    if (filter === 'today') {
        todayBtn.style.background = '#2563eb';
        todayBtn.style.color = 'white';
        weekBtn.style.background = 'white';
        weekBtn.style.color = '#374151';
        todayList.style.display = 'block';
        weekList.style.display = 'none';
    } else {
        weekBtn.style.background = '#2563eb';
        weekBtn.style.color = 'white';
        todayBtn.style.background = 'white';
        todayBtn.style.color = '#374151';
        weekList.style.display = 'block';
        todayList.style.display = 'none';
    }
}

// Patient Record Modal
function viewPatientDashboard(patientId) {
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

            const patientFullName = `${p.first_name || ''} ${p.middle_name || ''} ${p.last_name || ''} ${p.suffix || ''}`.trim() || 'N/A';

            document.getElementById('patientRecordContent').innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <h3 style="font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 12px;">Personal Information</h3>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <div><span style="color: #6b7280; font-size: 0.875rem;">Full Name:</span> <span style="font-weight: 500;">${patientFullName}</span></div>
                            <div><span style="color: #6b7280; font-size: 0.875rem;">Age:</span> <span style="font-weight: 500;">${p.age || 'N/A'} years</span></div>
                            <div><span style="color: #6b7280; font-size: 0.875rem;">Phone:</span> <span style="font-weight: 500;">${p.phone || 'N/A'}</span></div>
                        </div>
                    </div>
                    <div>
                        <h3 style="font-size: 0.875rem; font-weight: 600; color: #6b7280; margin-bottom: 12px;">Service Requested</h3>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <div><span style="color: #6b7280; font-size: 0.875rem;">Treatment:</span> <span style="font-weight: 500;">${q.treatment_type || 'N/A'}</span></div>
                            <div><span style="color: #6b7280; font-size: 0.875rem;">Teeth:</span> <span style="font-weight: 500;">${getStaffTeethDisplayText(q.teeth_numbers || '')}</span></div>
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
                                <div style="font-weight: 500;">${medicalConditions}</div>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 4px;">Current Medications</div>
                                <div style="font-weight: 500;">${medications}</div>
                            </div>
                        </div>
                    </div>
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
window.onclick = function(event) {
    const cancelledModal = document.getElementById('cancelledModal');
    const patientModal = document.getElementById('patientRecordModal');
    if (event.target == cancelledModal) closeCancelledModal();
    if (event.target == patientModal) closePatientRecordModal();
}

// Reminder functionality
document.getElementById('addReminderBtn')?.addEventListener('click', () => {
    const reminders = ["Check inventory", "Verify insurance claims", "Sterilize tools", "Update patient records"];
    const randomReminder = reminders[Math.floor(Math.random() * reminders.length)];
    const li = document.createElement('li');
    li.setAttribute('onclick', 'this.classList.toggle("checked")');
    li.innerText = randomReminder;
    document.getElementById('reminder-list').appendChild(li);
});

// Convert teeth numbers to arch labels (matches staff terminology)
function getStaffTeethDisplayText(teethString) {
    if (!teethString || teethString.trim() === '') {
        return 'N/A';
    }
    
    const teeth = teethString.split(',').map(t => parseInt(t.trim())).filter(t => !isNaN(t));
    if (teeth.length === 0) {
        return 'N/A';
    }
    
    // Staff's arch definitions - exactly 16 teeth per arch
    const upperArch = [11, 12, 13, 14, 15, 16, 17, 18, 21, 22, 23, 24, 25, 26, 27, 28];
    const lowerArch = [31, 32, 33, 34, 35, 36, 37, 38, 41, 42, 43, 44, 45, 46, 47, 48];
    
    // Check for exact arch matches
    const hasUpperArch = teeth.length === 16 && upperArch.every(t => teeth.includes(t));
    const hasLowerArch = teeth.length === 16 && lowerArch.every(t => teeth.includes(t));
    
    const parts = [];
    
    if (hasUpperArch) {
        parts.push('Upper Arch');
    }
    
    if (hasLowerArch) {
        parts.push('Lower Arch');
    }
    
    if (parts.length === 0) {
        return teeth.sort((a, b) => a - b).join(', ');
    }
    
    return parts.join(' + ');
}
</script>



<style>
    .view-section {
    display: none;
    flex: 1;
    overflow-y: auto;
    padding: 0;
    animation: fadeIn 0.3s ease;
}

</style>
<?php require_once 'includes/staff_layout_end.php'; ?>