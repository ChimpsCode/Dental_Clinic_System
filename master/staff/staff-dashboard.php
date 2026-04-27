<?php
$pageTitle = 'Dashboard';
require_once '../../config/database.php';
require_once '../../includes/staff_layout_start.php';

try {
    // Get queue data
    $stmt = $pdo->query("
        SELECT q.*, COALESCE(p.first_name, 'Unknown') as first_name, COALESCE(p.middle_name, '') as middle_name, COALESCE(p.last_name, '') as last_name, COALESCE(p.suffix, '') as suffix, COALESCE(p.phone, '') as phone, COALESCE(qs.status_name, 'waiting') as status_text
        FROM queue q 
        LEFT JOIN patients p ON CAST(q.patient_id AS UNSIGNED) = p.id 
        LEFT JOIN queue_statuses qs ON qs.id = q.status_id
        WHERE q.status_id IN (1, 2, 3, 4, 5, 6) AND q.is_archived = 0
        ORDER BY 
            CASE COALESCE(qs.status_name, 'waiting')
                WHEN 'in_procedure' THEN 1 
                WHEN 'waiting' THEN 2 
                WHEN 'completed' THEN 3 
                ELSE 2
            END,
            q.queue_time ASC
    ");
    $queueItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $waitingCount = count(array_filter($queueItems, fn($q) => ($q['status_text'] ?? '') === 'waiting'));
    $procedureCount = count(array_filter($queueItems, fn($q) => ($q['status_text'] ?? '') === 'in_procedure'));
    $completedCount = count(array_filter($queueItems, fn($q) => ($q['status_text'] ?? '') === 'completed'));
    $onHoldCount = count(array_filter($queueItems, fn($q) => ($q['status_text'] ?? '') === 'on_hold'));
    $cancelledCount = count(array_filter($queueItems, fn($q) => ($q['status_text'] ?? '') === 'cancelled'));
    
    $inProcedureItem = array_filter($queueItems, fn($q) => ($q['status_text'] ?? '') === 'in_procedure');
    $inProcedureItem = !empty($inProcedureItem) ? reset($inProcedureItem) : null;

    $waitingItems = array_filter($queueItems, fn($q) => ($q['status_text'] ?? '') === 'waiting');

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
                    $cancelledItems = array_filter($queueItems, fn($q) => ($q['status_text'] ?? '') === 'cancelled');
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
                                <button class="action-btn icon view-btn" onclick="viewPatientDashboard(<?php echo (int)$item['patient_id']; ?>)">👁️</button>
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
                    $onHoldItems = array_filter($queueItems, fn($q) => ($q['status_text'] ?? '') === 'on_hold');
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
                                <button class="action-btn icon view-btn" onclick="viewPatientDashboard(<?php echo (int)$item['patient_id']; ?>)">👁️</button>
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

<!-- Complete Treatment Modal -->
<div id="completeTreatmentModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div class="modal complete-treatment-modal" style="position: relative; z-index: 100000;">
        <div class="complete-treatment-bg-orb complete-treatment-bg-orb-a"></div>
        <div class="complete-treatment-bg-orb complete-treatment-bg-orb-b"></div>
        <div class="complete-treatment-banner">
            <div class="complete-treatment-icon-wrap">
                <span class="complete-treatment-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M9.2 16.2 5.5 12.5l-1.4 1.4 5.1 5.1L20 8.2l-1.4-1.4z"></path>
                    </svg>
                </span>
            </div>
            <div>
                <div class="complete-treatment-kicker">Live Queue Controller</div>
                <h2 style="font-size: 1.55rem; font-weight: 700; margin: 6px 0 0;">Complete Treatment</h2>
            </div>
            <button onclick="closeCompleteTreatmentModal()" class="complete-treatment-close">×</button>
        </div>
        <div class="complete-treatment-body">
            <p class="complete-treatment-text">Mark treatment as complete and move this patient to payment?</p>
            <div class="complete-treatment-highlight">
                <div class="complete-treatment-highlight-icon">🦷</div>
                <div>
                    <div class="complete-treatment-highlight-label">Patient Care Flow</div>
                    <div class="complete-treatment-highlight-value">Treatment finishes here, billing continues next.</div>
                </div>
            </div>
            <div class="complete-treatment-note">
                <span class="complete-treatment-note-dot"></span>
                The dashboard and payment list will refresh after confirmation.
            </div>
        </div>
        <input type="hidden" id="completeTreatmentQueueId">
        <div class="complete-treatment-actions">
            <button type="button" onclick="closeCompleteTreatmentModal()" class="complete-treatment-cancel">Keep Open</button>
            <button type="button" onclick="confirmCompleteTreatment()" class="complete-treatment-confirm">Yes, Complete</button>
        </div>
    </div>
</div>

<!-- Dashboard Confirm Modal -->
<div id="dashboardConfirmModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div class="modal" style="background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 460px; position: relative; z-index: 100000;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 id="dashboardConfirmTitle" style="font-size: 1.25rem; font-weight: 600; margin: 0;">Confirm Action</h2>
            <button onclick="closeDashboardConfirmModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">×</button>
        </div>
        <p id="dashboardConfirmMessage" style="color: #374151; margin: 0 0 20px;"></p>
        <div style="display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" onclick="closeDashboardConfirmModal()" style="padding: 10px 20px; background: #f3f4f6; color: #374151; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">Cancel</button>
            <button type="button" id="dashboardConfirmButton" style="padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Confirm</button>
        </div>
    </div>
</div>

<!-- Dashboard Message Modal -->
<div id="dashboardMessageModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; align-items: center; justify-content: center;">
    <div class="modal" style="background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 460px; position: relative; z-index: 100000;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 id="dashboardMessageTitle" style="font-size: 1.25rem; font-weight: 600; margin: 0;">Notice</h2>
            <button onclick="closeDashboardMessageModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">×</button>
        </div>
        <p id="dashboardMessageText" style="color: #374151; margin: 0 0 20px;"></p>
        <div style="display: flex; justify-content: flex-end;">
            <button type="button" onclick="closeDashboardMessageModal()" style="padding: 10px 20px; background: #f3f4f6; color: #374151; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">Close</button>
        </div>
    </div>
</div>

<script>
const queueItems = <?php echo json_encode($queueItems); ?>;
let dashboardConfirmHandler = null;

// Portal Pattern: Move modals to body level to escape stacking context
// This ensures modals appear above sidebar and all other elements
(function() {
    const cancelledModal = document.getElementById('cancelledModal');
    const patientRecordModal = document.getElementById('patientRecordModal');
    const completeTreatmentModal = document.getElementById('completeTreatmentModal');
    const dashboardConfirmModal = document.getElementById('dashboardConfirmModal');
    const dashboardMessageModal = document.getElementById('dashboardMessageModal');
    
    if (cancelledModal) {
        document.body.appendChild(cancelledModal);
    }
    if (patientRecordModal) {
        document.body.appendChild(patientRecordModal);
    }
    if (completeTreatmentModal) {
        document.body.appendChild(completeTreatmentModal);
    }
    if (dashboardConfirmModal) {
        document.body.appendChild(dashboardConfirmModal);
    }
    if (dashboardMessageModal) {
        document.body.appendChild(dashboardMessageModal);
    }
})();

function showDashboardMessage(message, title = 'Notice') {
    document.getElementById('dashboardMessageTitle').textContent = title;
    document.getElementById('dashboardMessageText').textContent = message;
    document.getElementById('dashboardMessageModal').style.display = 'flex';
}

function closeDashboardMessageModal() {
    document.getElementById('dashboardMessageModal').style.display = 'none';
}

function openDashboardConfirmModal(message, onConfirm, title = 'Confirm Action') {
    document.getElementById('dashboardConfirmTitle').textContent = title;
    document.getElementById('dashboardConfirmMessage').textContent = message;
    dashboardConfirmHandler = onConfirm;
    document.getElementById('dashboardConfirmModal').style.display = 'flex';
}

function closeDashboardConfirmModal() {
    document.getElementById('dashboardConfirmModal').style.display = 'none';
    dashboardConfirmHandler = null;
}

document.getElementById('dashboardConfirmButton').addEventListener('click', function() {
    if (typeof dashboardConfirmHandler === 'function') {
        const handler = dashboardConfirmHandler;
        closeDashboardConfirmModal();
        handler();
    }
});

// Dashboard Queue Actions
function completeCurrentPatient(queueId) {
    document.getElementById('completeTreatmentQueueId').value = queueId;
    document.getElementById('completeTreatmentModal').style.display = 'flex';
}

function closeCompleteTreatmentModal() {
    document.getElementById('completeTreatmentModal').style.display = 'none';
    document.getElementById('completeTreatmentQueueId').value = '';
}

function confirmCompleteTreatment() {
    const queueId = document.getElementById('completeTreatmentQueueId').value;
    if (!queueId) return;

    fetch('../shared/queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'complete', queue_id: queueId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeCompleteTreatmentModal();
            location.reload();
        } else {
            showDashboardMessage(data.message || 'Unable to complete treatment', 'Treatment Status');
        }
    })
    .catch(() => {
        showDashboardMessage('Unable to complete treatment', 'Treatment Status');
    });
}

function moveToOnHoldDashboard(queueId) {
    openDashboardConfirmModal('Put patient on hold?', function() {
        fetch('../shared/queue_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'on_hold', queue_id: queueId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showDashboardMessage(data.message || 'Unable to update queue status', 'Queue Status');
            }
        })
        .catch(() => {
            showDashboardMessage('Unable to update queue status', 'Queue Status');
        });
    });
}

function cancelPatientDashboard(queueId) {
    openDashboardConfirmModal('Cancel this patient?', function() {
        fetch('../shared/queue_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'cancel', queue_id: queueId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showDashboardMessage(data.message || 'Unable to cancel patient', 'Queue Status');
            }
        })
        .catch(() => {
            showDashboardMessage('Unable to cancel patient', 'Queue Status');
        });
    });
}

function requeuePatientDashboard(queueId) {
    fetch('../shared/queue_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'requeue', queue_id: queueId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showDashboardMessage(data.message || 'Unable to re-queue patient', 'Queue Status');
        }
    })
    .catch(() => {
        showDashboardMessage('Unable to re-queue patient', 'Queue Status');
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
    fetch('../user-patient/patient_record_details.php?id=' + encodeURIComponent(patientId))
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
        } else {
            showDashboardMessage(data.message || 'Unable to load patient record', 'Patient Record');
        }
    })
    .catch(() => {
        showDashboardMessage('Unable to load patient record', 'Patient Record');
    });
}

function closePatientRecordModal() {
    document.getElementById('patientRecordModal').style.display = 'none';
}

// Close modals on outside click
window.onclick = function(event) {
    const cancelledModal = document.getElementById('cancelledModal');
    const patientModal = document.getElementById('patientRecordModal');
    const completeTreatmentModal = document.getElementById('completeTreatmentModal');
    const dashboardConfirmModal = document.getElementById('dashboardConfirmModal');
    const dashboardMessageModal = document.getElementById('dashboardMessageModal');
    if (event.target == cancelledModal) closeCancelledModal();
    if (event.target == patientModal) closePatientRecordModal();
    if (event.target == completeTreatmentModal) closeCompleteTreatmentModal();
    if (event.target == dashboardConfirmModal) closeDashboardConfirmModal();
    if (event.target == dashboardMessageModal) closeDashboardMessageModal();
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

.complete-treatment-modal {
    background:
        radial-gradient(circle at top right, rgba(14, 165, 233, 0.18), transparent 32%),
        radial-gradient(circle at bottom left, rgba(34, 197, 94, 0.14), transparent 28%),
        linear-gradient(180deg, #ffffff 0%, #f7fcff 100%);
    border-radius: 22px;
    padding: 0;
    width: 90%;
    max-width: 560px;
    overflow: hidden;
    box-shadow: 0 30px 80px rgba(15, 23, 42, 0.24);
    border: 1px solid rgba(14, 165, 233, 0.12);
}

.complete-treatment-bg-orb {
    position: absolute;
    border-radius: 999px;
    pointer-events: none;
    filter: blur(8px);
    opacity: 0.55;
}

.complete-treatment-bg-orb-a {
    width: 140px;
    height: 140px;
    right: -30px;
    top: 88px;
    background: radial-gradient(circle, rgba(14, 165, 233, 0.2) 0%, rgba(14, 165, 233, 0) 72%);
}

.complete-treatment-bg-orb-b {
    width: 160px;
    height: 160px;
    left: -40px;
    bottom: -30px;
    background: radial-gradient(circle, rgba(34, 197, 94, 0.16) 0%, rgba(34, 197, 94, 0) 74%);
}

.complete-treatment-banner {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 24px 26px 18px;
    background:
        linear-gradient(135deg, rgba(255,255,255,0.12), rgba(255,255,255,0) 42%),
        linear-gradient(135deg, #0b7db3 0%, #0891b2 38%, #0f9f53 100%);
    color: white;
    position: relative;
}

.complete-treatment-icon-wrap {
    width: 54px;
    height: 54px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.22);
    backdrop-filter: blur(6px);
    flex-shrink: 0;
}

.complete-treatment-icon {
    display: flex;
    align-items: center;
    justify-content: center;
}

.complete-treatment-icon svg {
    width: 26px;
    height: 26px;
    fill: currentColor;
}

.complete-treatment-kicker {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    opacity: 0.9;
    font-weight: 700;
}

.complete-treatment-close {
    margin-left: auto;
    background: rgba(255, 255, 255, 0.12);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.18);
    width: 40px;
    height: 40px;
    border-radius: 12px;
    font-size: 1.5rem;
    cursor: pointer;
    backdrop-filter: blur(8px);
}

.complete-treatment-body {
    padding: 24px 26px 12px;
    position: relative;
}

.complete-treatment-text {
    color: #1f2937;
    font-size: 1.06rem;
    line-height: 1.6;
    margin: 0 0 16px;
    font-weight: 500;
}

.complete-treatment-highlight {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    border-radius: 16px;
    background: linear-gradient(135deg, #ecfeff 0%, #f0fdf4 100%);
    border: 1px solid #d9f99d;
    margin-bottom: 14px;
}

.complete-treatment-highlight-icon {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    box-shadow: 0 10px 24px rgba(8, 145, 178, 0.12);
    font-size: 1.25rem;
    flex-shrink: 0;
}

.complete-treatment-highlight-label {
    color: #0f766e;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 700;
    margin-bottom: 3px;
}

.complete-treatment-highlight-value {
    color: #1f2937;
    font-size: 0.95rem;
    font-weight: 600;
}

.complete-treatment-note {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 14px;
    background: #f0fdf4;
    color: #166534;
    font-size: 0.92rem;
    border: 1px solid #dcfce7;
}

.complete-treatment-note-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: #22c55e;
    box-shadow: 0 0 0 5px rgba(34, 197, 94, 0.14);
    flex-shrink: 0;
}

.complete-treatment-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 18px 26px 26px;
}

.complete-treatment-cancel,
.complete-treatment-confirm {
    border: none;
    border-radius: 14px;
    padding: 13px 20px;
    cursor: pointer;
    font-weight: 700;
    font-size: 0.97rem;
    transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
}

.complete-treatment-cancel {
    background: #eef6fb;
    color: #315266;
    border: 1px solid #d8e9f4;
}

.complete-treatment-confirm {
    background: linear-gradient(135deg, #0891b2 0%, #0f9f53 100%);
    color: white;
    box-shadow: 0 14px 28px rgba(8, 145, 178, 0.24);
}

.complete-treatment-cancel:hover,
.complete-treatment-confirm:hover,
.complete-treatment-close:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
}

@media (max-width: 640px) {
    .complete-treatment-banner,
    .complete-treatment-body,
    .complete-treatment-actions {
        padding-left: 18px;
        padding-right: 18px;
    }

    .complete-treatment-actions {
        flex-direction: column-reverse;
    }

    .complete-treatment-cancel,
    .complete-treatment-confirm {
        width: 100%;
    }
}

</style>
<?php require_once '../../includes/staff_layout_end.php'; ?>
