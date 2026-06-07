<?php
$pageTitle = 'Dashboard';
require_once '../../config/database.php';
require_once '../../includes/queue_management_helper.php';
require_once '../../includes/staff_layout_start.php';

try {
    // Ensure queue table has proper schema
    ensureQueueManagementSchema($pdo);
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
                                <button class="action-btn icon delete-btn">
                                    <svg viewBox="-2.4 -2.4 28.80 28.80" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#000000" width="16" height="16"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" stroke="#CCCCCC" stroke-width="0.144"></g><g id="SVGRepo_iconCarrier"> <path d="M5.82907 6.65808H18.6325V19.2906C18.6325 20.3951 17.7371 21.2906 16.6325 21.2906H7.82907C6.7245 21.2906 5.82907 20.3951 5.82907 19.2906V6.65808Z" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M4 5.74365L20.4615 5.74365" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M14.9134 3H9.54816L8.57266 5.74359H15.8889L14.9134 3Z" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></path> <line x1="12.3163" y1="10.4017" x2="12.3163" y2="17.547" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></line> <line x1="9.57266" y1="10.4017" x2="9.57266" y2="17.547" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></line> <line x1="15.0598" y1="10.4017" x2="15.0598" y2="17.547" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></line> </g></svg>
                                </button>
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
                    <div class="now-serving-badge">
                        <span>
                        
                        </span>
                        <span>In Procedure</span></div>
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
                                <button class="action-btn text-btn" onclick="moveToOnHoldDashboard(<?php echo $item['id']; ?>)">Hold</button>
                                <button 
                                    class="action-btn text-btn" 
                                    style="background-color: red; color: white; border: none; padding: 5px 10px; cursor: pointer;"
                                    onclick="cancelPatientDashboard(<?php echo $item['id']; ?>)">
                                    Cancel
                                </button>
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
                                <button class="action-btn icon view-btn" onclick="viewPatientDashboard(<?php echo (int)$item['patient_id']; ?>)">
                                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" width="16" height="16"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path opacity="0.15" fill-rule="evenodd" clip-rule="evenodd" d="M12 5C5.63636 5 2 12 2 12C2 12 5.63636 19 12 19C18.3636 19 22 12 22 12C22 12 18.3636 5 12 5ZM15 12C15 13.6569 13.6569 15 12 15C10.3431 15 9 13.6569 9 12C9 10.3431 10.3431 9 12 9C13.6569 9 15 10.3431 15 12Z" fill="#000000"></path> <path d="M12 5C5.63636 5 2 12 2 12C2 12 5.63636 19 12 19C18.3636 19 22 12 22 12C22 12 18.3636 5 12 5Z" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15Z" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>
                                </button>
                                <button class="action-btn" style="background: #84cc16; color: white; padding: 8px 12px; border-radius: 6px; border: none; font-size: 12px; cursor: pointer;" onclick="requeuePatientDashboard(<?php echo $item['id']; ?>)">Re-queue</button>
                                <button class="action-btn icon delete-btn">
                                    <svg viewBox="-2.4 -2.4 28.80 28.80" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#000000" width="16" height="16"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" stroke="#CCCCCC" stroke-width="0.144"></g><g id="SVGRepo_iconCarrier"> <path d="M5.82907 6.65808H18.6325V19.2906C18.6325 20.3951 17.7371 21.2906 16.6325 21.2906H7.82907C6.7245 21.2906 5.82907 20.3951 5.82907 19.2906V6.65808Z" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M4 5.74365L20.4615 5.74365" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M14.9134 3H9.54816L8.57266 5.74359H15.8889L14.9134 3Z" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></path> <line x1="12.3163" y1="10.4017" x2="12.3163" y2="17.547" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></line> <line x1="9.57266" y1="10.4017" x2="9.57266" y2="17.547" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></line> <line x1="15.0598" y1="10.4017" x2="15.0598" y2="17.547" stroke="#000000" stroke-width="1.152" stroke-linecap="round" stroke-linejoin="round"></line> </g></svg>
                                </button>
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
              
            <!-- Upcoming Appointments Overview -->
            <?php 
            $staffTodayAppointments = [];
            $staffTotalUpcomingAppts = 0;
            try {
                $staffTotalUpcomingAppts = (int)($pdo->query("
                    SELECT COUNT(*)
                    FROM appointments
                    WHERE appointment_date >= CURDATE()
                      AND (is_archived = 0 OR is_archived IS NULL)
                ")->fetchColumn() ?? 0);

                $staffApptStmt = $pdo->query("
                    SELECT a.id, a.appointment_date, a.appointment_time, a.status_id,
                           COALESCE(p.first_name, 'Unknown') as first_name, 
                           COALESCE(p.middle_name, '') as middle_name, 
                           COALESCE(p.last_name, '') as last_name,
                           s.name as service_name,
                           st.status_name
                    FROM appointments a
                    LEFT JOIN patients p ON CAST(a.patient_id AS UNSIGNED) = p.id
                    LEFT JOIN services s ON a.service_id = s.id
                    LEFT JOIN appointment_statuses st ON a.status_id = st.id
                    WHERE a.appointment_date >= CURDATE()
                      AND (a.is_archived = 0 OR a.is_archived IS NULL)
                    ORDER BY a.appointment_date ASC, a.appointment_time ASC
                    LIMIT 5
                ");
                $staffTodayAppointments = $staffApptStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $staffTodayAppointments = [];
                $staffTotalUpcomingAppts = 0;
            }
            ?>
            <div class="notification-box">
                <h3>📅 Appointments Overview</h3>
                <div style="max-height: 250px; overflow-y: auto;">
                    <?php if (empty($staffTodayAppointments)): ?>
                        <p style="text-align: center; color: #6b7280; padding: 20px;">No upcoming appointments</p>
                    <?php else: ?>
                        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                            <thead>
                                <tr style="background: #f9fafb;">
                                    <th style="text-align: left; padding: 6px 8px; color: #6b7280; font-weight: 600; font-size: 10px;">Time</th>
                                    <th style="text-align: left; padding: 6px 8px; color: #6b7280; font-weight: 600; font-size: 10px;">Patient</th>
                                    <th style="text-align: left; padding: 6px 8px; color: #6b7280; font-weight: 600; font-size: 10px;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staffTodayAppointments as $appt): 
                                    $apptPatientName = trim(($appt['first_name'] ?? '') . ' ' . ($appt['middle_name'] ?? '') . ' ' . ($appt['last_name'] ?? ''));
                                    $statusLabel = $appt['status_name'] ?? 'Scheduled';
                                    $statusId = (int)$appt['status_id'];
                                    
                                    $badgeStyle = 'background: #dbeafe; color: #1d4ed8;';
                                    if ($statusId === 2 || stripos($statusLabel, 'queue') !== false) { $badgeStyle = 'background: #fef3c7; color: #92400e;'; }
                                    if ($statusId === 3 || stripos($statusLabel, 'pending') !== false) { $badgeStyle = 'background: #fed7aa; color: #c2410c;'; }
                                    if ($statusId === 4 || stripos($statusLabel, 'complete') !== false) { $badgeStyle = 'background: #dcfce7; color: #15803d;'; }
                                    if ($statusId === 5 || stripos($statusLabel, 'cancel') !== false) { $badgeStyle = 'background: #fee2e2; color: #dc2626;'; }
                                ?>
                                <tr style="border-bottom: 1px solid #f3f4f6;">
                                    <td style="padding: 8px 6px; color: #374151; font-size: 11px;"><?php echo date('h:i A', strtotime($appt['appointment_time'] ?? '')); ?></td>
                                    <td style="padding: 8px 6px; color: #374151; font-size: 11px;"><?php echo htmlspecialchars($apptPatientName ?: 'Unknown'); ?></td>
                                    <td style="padding: 8px 6px;">
                                        <span style="display: inline-block; padding: 2px 6px; border-radius: 8px; font-size: 9px; font-weight: 600; <?php echo $badgeStyle; ?>">
                                            <?php echo htmlspecialchars($statusLabel); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <a href="staff_appointments.php" style="display: block; text-align: center; margin-top: 12px; padding: 8px; background: #f3f4f6; border-radius: 6px; color: #374151; text-decoration: none; font-size: 12px;">View All (<?php echo $staffTotalUpcomingAppts; ?>)</a>
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
        </div>
        <p id="dashboardMessageText" style="color: #374151; margin: 0 0 20px;"></p>
        <div style="display: flex; justify-content: flex-end;">
            <button type="button" onclick="closeDashboardMessageModal()" style="padding: 10px 20px; background: #0ea5e9; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">Close</button>
        </div>
    </div>
</div>

<!-- Inquiry Panel Backdrop -->
<div class="dashboard-inquiry-backdrop" id="dashboardInquiryBackdrop" onclick="toggleInquiryPanel()"></div>

<!-- Floating Inquiry Button -->
<button type="button" id="dashboardInquiryFab" class="dashboard-inquiry-fab" onclick="toggleInquiryPanel()" aria-label="Inquiries" title="Inquiries">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 2 11 13"></path>
        <path d="m22 2-7 20-4-9-9-4Z"></path>
    </svg>
    <span class="dashboard-inquiry-fab-badge" id="dashboardInquiryFabBadge" style="display:none;">0</span>
</button>

<!-- Inquiry Panel -->
<div class="dashboard-inquiry-panel" id="dashboardInquiryPanel">
    <div id="inquiryPanelListView" style="display:flex;flex-direction:column;flex:1;min-height:0;">
        <div class="dashboard-inquiry-panel-header">
            <h3>Inquiries</h3>
            <span class="dashboard-inquiry-panel-count" id="dashboardInquiryCount">0</span>
            <button type="button" class="dashboard-inquiry-panel-close" onclick="toggleInquiryPanel()" aria-label="Close">&times;</button>
        </div>
        <div class="dashboard-inquiry-panel-list" id="dashboardInquiryList">
            <div class="dashboard-inquiry-loading">Loading...</div>
        </div>
    </div>
    <div id="inquiryPanelChatView" style="display:none;flex-direction:column;flex:1;min-height:0;">
        <div class="chat-modal-header">
            <button type="button" class="chat-modal-back" onclick="showInquiryList()" aria-label="Back">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
            </button>
            <div class="chat-modal-avatar" id="inquiryChatAvatar">JD</div>
            <div class="chat-modal-user-info">
                <div class="chat-modal-name" id="inquiryChatName"></div>
                <div class="chat-modal-email" id="inquiryChatContact"></div>
            </div>
            <div class="chat-modal-source" id="inquiryChatSource"></div>
        </div>
        <div class="chat-modal-messages" id="inquiryChatMessages"></div>
        <div class="chat-channel-info" id="inquiryChatChannel"></div>
        <form id="inquiryChatReplyForm" class="chat-compose">
            <input type="hidden" id="inquiryChatReplyId">
            <div class="chat-send-row">
                <textarea id="inquiryChatReplyMsg" placeholder="Type your reply..." rows="1" required></textarea>
                <button type="submit" class="chat-send-btn" aria-label="Send">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="m22 2-7 20-4-9-9-4Z"/></svg>
                </button>
            </div>
        </form>
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
            const dentalHistory = data.dental_history || [];
            const completedPlans = data.completed_plans || [];
            const q = data.queue_item || {};

            const allergies = m.allergies || 'None';
            const medications = m.current_medications || 'None';
            const medicalConditions = m.medical_conditions || 'None';

            const patientFullName = `${p.first_name || ''} ${p.middle_name || ''} ${p.last_name || ''} ${p.suffix || ''}`.trim() || 'N/A';
            
            // Build dental history HTML
            let dentalHistoryHTML = '';
            if (dentalHistory.length > 0) {
                dentalHistoryHTML = '<div style="display: flex; flex-direction: column; gap: 12px;">';
                dentalHistory.forEach(treatment => {
                    const dentistName = treatment.dentist_first_name && treatment.dentist_last_name 
                        ? `Dr. ${treatment.dentist_first_name} ${treatment.dentist_last_name}` 
                        : 'Unknown Dentist';
                    const treatmentDate = treatment.completed_at 
                        ? new Date(treatment.completed_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })
                        : (treatment.treatment_date || 'N/A');
                    dentalHistoryHTML += `
                        <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                <div>
                                    <div style="font-weight: 600; color: #111827;">${treatment.treatment_type || 'Treatment'}</div>
                                    <div style="font-size: 0.85rem; color: #6b7280;">Teeth: ${getStaffTeethDisplayText(treatment.teeth_numbers || '')}</div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 0.85rem; color: #6b7280;">${treatmentDate}</div>
                                    <div style="font-size: 0.8rem; color: #059669; font-weight: 500;">${dentistName}</div>
                                </div>
                            </div>
                            ${treatment.procedure_notes ? `<div style="font-size: 0.85rem; color: #6b7280; border-top: 1px solid #f3f4f6; padding-top: 8px; margin-top: 8px;">${treatment.procedure_notes}</div>` : ''}
                        </div>
                    `;
                });
                dentalHistoryHTML += '</div>';
            } else if (completedPlans.length > 0) {
                dentalHistoryHTML = '<div style="display: flex; flex-direction: column; gap: 12px;">';
                completedPlans.forEach(plan => {
                    const planDate = new Date(plan.treatment_date).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
                    dentalHistoryHTML += `
                        <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                <div>
                                    <div style="font-weight: 600; color: #111827;">${plan.treatment_name || plan.treatment_type || 'Treatment Plan'}</div>
                                    <div style="font-size: 0.85rem; color: #6b7280;">Teeth: ${plan.teeth_numbers || 'N/A'}</div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 0.85rem; color: #6b7280;">${planDate}</div>
                                    <div style="font-size: 0.8rem; color: #059669; font-weight: 500;">Plan Completed</div>
                                </div>
                            </div>
                            ${plan.notes ? `<div style="font-size: 0.85rem; color: #6b7280; border-top: 1px solid #f3f4f6; padding-top: 8px; margin-top: 8px;">${plan.notes}</div>` : ''}
                        </div>
                    `;
                });
                dentalHistoryHTML += '</div>';
            } else {
                dentalHistoryHTML = '<p style="color: #6b7280; text-align: center; padding: 20px;">No previous treatment records found.</p>';
            }

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
                <div style="margin-top: 20px;">
                    <h3 style="font-size: 0.875rem; font-weight: 600; color: #059669; margin-bottom: 12px;">📜 Dental History (Previous Treatments)</h3>
                    <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 16px;">
                        ${dentalHistoryHTML}
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

// ===== Dashboard Inquiry Panel =====
let dashboardInquiries = [];
let dashboardInquiryMap = {};

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;',
        '"': '&quot;', "'": '&#39;'
    }[character]));
}

function getInitials(name) {
    const parts = (name || 'P').trim().split(/\s+/);
    const first = parts[0] || '';
    const last = parts.length > 1 ? parts[parts.length - 1] : '';
    return ((first[0] || '') + (last[0] || '')).toUpperCase() || 'P';
}

function toggleInquiryPanel() {
    const panel = document.getElementById('dashboardInquiryPanel');
    const backdrop = document.getElementById('dashboardInquiryBackdrop');
    const isOpen = panel.classList.toggle('open');
    backdrop.classList.toggle('open', isOpen);
    if (isOpen && !dashboardInquiries.length) {
        fetchDashboardInquiries();
    }
}

function showInquiryList() {
    document.getElementById('inquiryPanelListView').style.display = 'flex';
    document.getElementById('inquiryPanelChatView').style.display = 'none';
}

async function fetchDashboardInquiries() {
    try {
        const url = '../shared/inquiry_actions.php?action=get_inquiries&limit=30';
        const response = await fetch(url);
        const data = await response.json();
        if (!data.success) return;
        dashboardInquiries = data.inquiries;
        dashboardInquiryMap = Object.fromEntries(dashboardInquiries.map((item) => [String(item.id), item]));
        renderDashboardInquiries();
        updateDashboardInquiryBadge(data.unread_count);
    } catch (_err) {}
}

function renderDashboardInquiries() {
    const list = document.getElementById('dashboardInquiryList');
    if (!dashboardInquiries.length) {
        list.innerHTML = '<div class="dashboard-inquiry-empty">No inquiries yet</div>';
        document.getElementById('dashboardInquiryCount').textContent = '0';
        return;
    }
    document.getElementById('dashboardInquiryCount').textContent = dashboardInquiries.length;
    list.innerHTML = dashboardInquiries.map((inquiry) => {
        const fullName = [inquiry.first_name, inquiry.middle_name, inquiry.last_name].filter(Boolean).join(' ') || 'Unknown';
        const preview = (inquiry.concern || inquiry.inquiry_message || 'No message').slice(0, 80);
        const status = inquiry.status || 'Unread';
        const initials = getInitials(fullName);
        return `
            <div class="dashboard-inquiry-item">
                <div class="dashboard-inquiry-item-avatar">${escapeHtml(initials)}</div>
                <div class="dashboard-inquiry-item-body">
                    <div class="dashboard-inquiry-item-top">
                        <span class="dashboard-inquiry-item-name">${escapeHtml(fullName)}</span>
                        <span class="dashboard-inquiry-item-status ${escapeHtml(status)}">${escapeHtml(status)}</span>
                    </div>
                    <div class="dashboard-inquiry-item-preview">${escapeHtml(preview)}</div>
                    <button class="dashboard-inquiry-item-reply" onclick="openDashboardInquiryChat(${Number(inquiry.id)})">Reply</button>
                </div>
            </div>
        `;
    }).join('');
}

function updateDashboardInquiryBadge(count) {
    const badge = document.getElementById('dashboardInquiryFabBadge');
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.style.display = 'inline-flex';
    } else {
        badge.style.display = 'none';
    }
}

async function openDashboardInquiryChat(id) {
    const inquiry = dashboardInquiryMap[String(id)];
    if (!inquiry) return;

    const fullName = [inquiry.first_name, inquiry.middle_name, inquiry.last_name].filter(Boolean).join(' ') || 'Unknown Patient';
    const contactParts = [inquiry.email, inquiry.phone || inquiry.contact_info].filter(Boolean);
    document.getElementById('inquiryChatName').textContent = fullName;
    document.getElementById('inquiryChatContact').textContent = contactParts.join(' · ') || 'No contact';
    document.getElementById('inquiryChatSource').textContent = inquiry.source || 'Website';
    document.getElementById('inquiryChatAvatar').textContent = getInitials(fullName);
    document.getElementById('inquiryChatReplyId').value = inquiry.id;

    const concernText = inquiry.concern || inquiry.inquiry_message || 'No concern provided.';
    const container = document.getElementById('inquiryChatMessages');
    container.innerHTML = '';

    const originalBubble = document.createElement('div');
    originalBubble.className = 'chat-bubble received';
    const createdDate = inquiry.created_at ? new Date(String(inquiry.created_at).replace(' ', 'T')).toLocaleString() : '';
    originalBubble.innerHTML = '<div style="font-size:11px;font-weight:600;color:#64748b;margin-bottom:3px;">' + escapeHtml(fullName) + '</div><div>' + escapeHtml(concernText).replace(/\n/g, '<br>') + '</div><div class="chat-meta">' + escapeHtml(createdDate) + '</div>';
    container.appendChild(originalBubble);

    try {
        const formData = new FormData();
        formData.append('action', 'get_conversation');
        formData.append('id', id);
        const response = await fetch('../shared/inquiry_actions.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.success && data.replies) {
            data.replies.forEach((reply) => {
                const bubble = document.createElement('div');
                const isGuest = reply.sender_type === 'guest';
                bubble.className = 'chat-bubble ' + (isGuest ? 'received' : 'sent');
                const label = isGuest ? fullName : 'Staff';
                bubble.innerHTML = '<div style="font-size:11px;font-weight:600;color:' + (isGuest ? '#64748b' : 'rgba(255,255,255,0.7)') + ';margin-bottom:3px;">' + escapeHtml(label) + '</div><div>' + escapeHtml(reply.message).replace(/\n/g, '<br>') + '</div><div class="chat-meta">' + escapeHtml(reply.created_at ? new Date(String(reply.created_at).replace(' ', 'T')).toLocaleString() : '') + '</div>';
                container.appendChild(bubble);
            });
        }
    } catch (_err) {
        const replies = parseDashboardStaffReplies(inquiry.notes);
        replies.forEach((reply) => {
            const bubble = document.createElement('div');
            bubble.className = 'chat-bubble sent';
            bubble.innerHTML = '<div style="font-size:11px;font-weight:600;color:rgba(255,255,255,0.7);margin-bottom:3px;">Staff</div><div>' + escapeHtml(reply.message).replace(/\n/g, '<br>') + '</div><div class="chat-meta">' + escapeHtml(reply.timestamp) + '</div>';
            container.appendChild(bubble);
        });
    }

    container.scrollTop = container.scrollHeight;

    const channelInfo = document.getElementById('inquiryChatChannel');
    if ((inquiry.source || '') === 'Patient Portal' && inquiry.patient_id) {
        channelInfo.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Reply will appear in patient\'s account messages';
    } else {
        channelInfo.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M22 12v1a5 5 0 0 1-5 5h-1"/></svg> Reply will be sent via email with a conversation link to ' + escapeHtml(inquiry.email || 'the sender');
    }

    document.getElementById('inquiryPanelListView').style.display = 'none';
    document.getElementById('inquiryPanelChatView').style.display = 'flex';

    if ((inquiry.status || 'Unread') === 'Unread') {
        inquiry.status = 'Read';
        fetch('../shared/inquiry_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mark_read&id=' + id
        });
        renderDashboardInquiries();
    }

    document.getElementById('inquiryChatReplyMsg').value = '';
    document.getElementById('inquiryChatReplyMsg').style.height = 'auto';
    setTimeout(() => document.getElementById('inquiryChatReplyMsg').focus(), 200);
}

function parseDashboardStaffReplies(notes) {
    if (!notes) return [];
    const replies = [];
    const lines = notes.split('\n');
    let current = null;
    for (const line of lines) {
        const staffMatch = line.match(/^\[Staff replied on (.+?)\]/);
        if (staffMatch) {
            if (current) replies.push(current);
            current = { timestamp: staffMatch[1], message: '', type: 'sent' };
        } else if (current) {
            current.message += (current.message ? '\n' : '') + line;
        }
    }
    if (current) replies.push(current);
    return replies;
}

document.getElementById('inquiryChatReplyForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const id = document.getElementById('inquiryChatReplyId').value;
    const message = document.getElementById('inquiryChatReplyMsg').value.trim();
    if (!id || !message) return;

    const btn = event.currentTarget.querySelector('button[type="submit"]');
    btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('action', 'staff_reply');
        formData.append('id', id);
        formData.append('message', message);
        const response = await fetch('../shared/inquiry_actions.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (!data.success) {
            btn.disabled = false;
            return;
        }
        const now = new Date();
        const ts = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-' + String(now.getDate()).padStart(2,'0') + ' ' + String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0') + ':' + String(now.getSeconds()).padStart(2,'0');

        const container = document.getElementById('inquiryChatMessages');
        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble sent';
        bubble.innerHTML = '<div style="font-size:11px;font-weight:600;color:rgba(255,255,255,0.7);margin-bottom:3px;">Staff</div><div>' + escapeHtml(message).replace(/\n/g, '<br>') + '</div><div class="chat-meta">' + ts + '</div>';
        container.appendChild(bubble);
        container.scrollTop = container.scrollHeight;

        const entry = dashboardInquiryMap[String(id)];
        if (entry) {
            entry.status = 'Replied';
            entry.notes = (entry.notes || '') + '\n[Staff replied on ' + ts + ']\n' + message;
        }

        document.getElementById('inquiryChatReplyMsg').value = '';
        document.getElementById('inquiryChatReplyMsg').style.height = 'auto';
        btn.disabled = false;
        renderDashboardInquiries();
    } catch (_err) {
        btn.disabled = false;
    }
});

const inquiryChatTextarea = document.getElementById('inquiryChatReplyMsg');
if (inquiryChatTextarea) {
    inquiryChatTextarea.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 100) + 'px';
    });
}

// Poll for unread count
async function pollDashboardInquiryBadge() {
    try {
        const response = await fetch('../shared/inquiry_actions.php?action=get_inquiries&limit=1');
        const data = await response.json();
        if (data.success) {
            updateDashboardInquiryBadge(data.unread_count);
        }
    } catch (_err) {}
}

setInterval(pollDashboardInquiryBadge, 5000);
pollDashboardInquiryBadge();

// Portal fixed elements to body to avoid ancestor transform/scroll issues
(function() {
    const fab = document.getElementById('dashboardInquiryFab');
    if (fab) document.body.appendChild(fab);
    const backdrop = document.getElementById('dashboardInquiryBackdrop');
    if (backdrop) document.body.appendChild(backdrop);
    const panel = document.getElementById('dashboardInquiryPanel');
    if (panel) document.body.appendChild(panel);
})();
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

/* Floating Inquiry Button */
.dashboard-inquiry-fab {
    position: fixed;
    right: 24px;
    bottom: 24px;
    width: 58px;
    height: 58px;
    border-radius: 50%;
    border: none;
    background: #059669 !important;
    color: #ffffff !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none !important;
    box-shadow: 0 14px 28px rgba(5, 150, 105, 0.28);
    z-index: 2001;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}
.dashboard-inquiry-fab:hover {
    transform: translateY(-2px);
    box-shadow: 0 18px 32px rgba(5, 150, 105, 0.34);
    background: #047857 !important;
}
.dashboard-inquiry-fab-badge {
    position: absolute;
    top: -2px;
    right: -2px;
    min-width: 22px;
    height: 22px;
    padding: 0 6px;
    border-radius: 999px;
    background: #dc2626;
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #ffffff;
    line-height: 1;
}

/* Inquiry Panel */
.dashboard-inquiry-panel {
    position: fixed;
    right: 0;
    top: 0;
    bottom: 0;
    width: 400px;
    max-width: 100vw;
    background: #ffffff;
    box-shadow: -8px 0 30px rgba(15, 23, 42, 0.12);
    z-index: 2002;
    display: none;
    flex-direction: column;
    animation: slideInRight 0.28s cubic-bezier(0.4, 0, 0.2, 1);
}
.dashboard-inquiry-panel.open {
    display: flex;
}
@keyframes slideInRight {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}
.dashboard-inquiry-panel-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 18px 20px;
    border-bottom: 1px solid #e5e7eb;
    background: #fafbff;
}
.dashboard-inquiry-panel-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: #0f172a;
    font-weight: 700;
}
.dashboard-inquiry-panel-count {
    font-size: 12px;
    font-weight: 700;
    background: #e2e8f0;
    color: #64748b;
    padding: 2px 8px;
    border-radius: 999px;
}
.dashboard-inquiry-panel-close {
    margin-left: auto;
    background: none;
    border: none;
    font-size: 1.6rem;
    color: #6b7280;
    cursor: pointer;
    padding: 0 4px;
    line-height: 1;
}
.dashboard-inquiry-panel-close:hover {
    color: #111827;
}
.dashboard-inquiry-panel-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px 0;
}
.dashboard-inquiry-loading {
    text-align: center;
    padding: 40px 20px;
    color: #64748b;
}

/* Inquiry Panel Items */
.dashboard-inquiry-item {
    display: flex;
    gap: 12px;
    padding: 14px 20px;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s;
    cursor: default;
}
.dashboard-inquiry-item:hover {
    background: #f8fafc;
}
.dashboard-inquiry-item-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
}
.dashboard-inquiry-item-body {
    flex: 1;
    min-width: 0;
}
.dashboard-inquiry-item-top {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}
.dashboard-inquiry-item-name {
    font-weight: 600;
    color: #0f172a;
    font-size: 14px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dashboard-inquiry-item-status {
    font-size: 10px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    white-space: nowrap;
}
.dashboard-inquiry-item-status.Unread {
    background: #fef3c7;
    color: #92400e;
}
.dashboard-inquiry-item-status.Read {
    background: #f0fdf4;
    color: #16a34a;
}
.dashboard-inquiry-item-status.Replied {
    background: #dbeafe;
    color: #1d4ed8;
}
.dashboard-inquiry-item-preview {
    font-size: 13px;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 6px;
}
.dashboard-inquiry-item-reply {
    font-size: 12px;
    color: #2563eb;
    font-weight: 600;
    background: none;
    border: none;
    padding: 4px 0;
    cursor: pointer;
}
.dashboard-inquiry-item-reply:hover {
    color: #1d4ed8;
    text-decoration: underline;
}
.dashboard-inquiry-empty {
    text-align: center;
    padding: 60px 20px;
    color: #64748b;
}

/* Inquiry Chat View — matches staff_inquiries.php chat modal classes */
.chat-modal-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px 20px;
    border-bottom: 1px solid #e5e7eb;
    background: #fafbff;
}
.chat-modal-back {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: none;
    background: #eef2ff;
    color: #1d4ed8;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background 0.15s;
}
.chat-modal-back:hover { background: #dbeafe; }
.chat-modal-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 15px;
    flex-shrink: 0;
}
.chat-modal-user-info {
    flex: 1;
    min-width: 0;
}
.chat-modal-name {
    font-weight: 700;
    color: #0f172a;
    font-size: 15px;
}
.chat-modal-email {
    font-size: 12px;
    color: #64748b;
    margin-top: 2px;
}
.chat-modal-source {
    font-size: 11px;
    font-weight: 700;
    padding: 5px 10px;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    flex-shrink: 0;
}
.chat-modal-messages {
    flex: 1;
    overflow-y: auto;
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #fcfdff;
}
.chat-bubble {
    max-width: 88%;
    padding: 12px 16px;
    border-radius: 18px;
    line-height: 1.55;
    font-size: 14px;
    word-break: break-word;
}
.chat-bubble.received {
    align-self: flex-start;
    background: #f0f2f5;
    color: #111827;
    border-bottom-left-radius: 6px;
}
.chat-bubble.sent {
    align-self: flex-end;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    border-bottom-right-radius: 6px;
}
.chat-bubble .chat-meta {
    font-size: 11px;
    opacity: 0.65;
    margin-top: 6px;
}
.chat-bubble.sent .chat-meta {
    color: rgba(255,255,255,0.8);
}
.chat-channel-info {
    padding: 10px 20px;
    font-size: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    border-top: 1px solid #e5e7eb;
    background: #fafbff;
    color: #475569;
}
.chat-compose {
    padding: 14px 20px 18px;
    border-top: 1px solid #e5e7eb;
    background: #fff;
}
.chat-send-row {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}
.chat-send-row textarea {
    flex: 1;
    border: 1px solid #d1d5db;
    border-radius: 20px;
    padding: 12px 16px;
    font: inherit;
    font-size: 14px;
    resize: none;
    min-height: 48px;
    max-height: 120px;
    outline: none;
    transition: border-color 0.2s;
    background: #f9fafb;
}
.chat-send-row textarea:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
    background: #fff;
}
.chat-send-btn {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    border: none;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: transform 0.15s, box-shadow 0.15s;
    box-shadow: 0 4px 12px rgba(37,99,235,0.25);
}
.chat-send-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 16px rgba(37,99,235,0.35);
}
.chat-send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.dashboard-inquiry-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.4);
    z-index: 2001;
    display: none;
}
.dashboard-inquiry-backdrop.open {
    display: block;
}

@media (max-width: 640px) {
    .dashboard-inquiry-fab {
        right: 16px;
        bottom: 24px;
        width: 52px;
        height: 52px;
    }
    .dashboard-inquiry-panel {
        width: 100vw;
    }
}

</style>
<?php require_once '../../includes/staff_layout_end.php'; ?>
