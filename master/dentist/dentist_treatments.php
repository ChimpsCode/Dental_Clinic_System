<?php
$pageTitle = 'Treatment Plan';

require_once '../../config/database.php';

// Get treatment plans with appointment info - NO try-catch to see any errors
$sql = "
    SELECT tp.*, 
        a.reference_code as appointment_ref, 
        a.appointment_date as appt_date, 
        a.appointment_time as appt_time,
        a.status as appt_status
    FROM treatment_plans tp
    LEFT JOIN appointments a ON tp.appointment_id = a.id
    ORDER BY tp.created_at DESC
";
$treatmentPlans = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Get patient names for treatment plans
$patientNames = [];
if (!empty($treatmentPlans)) {
    $patientIds = array_unique(array_column($treatmentPlans, "patient_id"));
    if (!empty($patientIds)) {
        $placeholders = implode(',', array_fill(0, count($patientIds), '?'));
        $stmt = $pdo->prepare("SELECT id, first_name, middle_name, last_name, suffix FROM patients WHERE id IN ($placeholders)");
        $stmt->execute(array_values($patientIds));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $patientNames[$p['id']] = trim($p['first_name'] . ' ' . ($p['middle_name'] ?? '') . ' ' . $p['last_name'] . ' ' . ($p['suffix'] ?? ''));
        }
    }
}

// Get service names (in case plan_name is a service ID)
$serviceNames = [];
$services = $pdo->query("SELECT id, name FROM services WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
foreach ($services as $s) {
    $serviceNames[$s['id']] = $s['name'];
}

// Map patient names and resolve service names
foreach ($treatmentPlans as &$plan) {
    $plan['full_name'] = $patientNames[$plan['patient_id']] ?? 'Patient #' . $plan['patient_id'];
    // If plan_name is a number, try to get service name
    if (is_numeric($plan['plan_name']) && isset($serviceNames[$plan['plan_name']])) {
        $plan['plan_name'] = $serviceNames[$plan['plan_name']];
    }
}
unset($plan);

// Get patients from queue for the dropdown
$queuePatients = $pdo->query("
    SELECT DISTINCT p.id, p.first_name, p.middle_name, p.last_name, p.suffix, p.phone
    FROM patients p
    JOIN queue q ON p.id = CAST(q.patient_id AS UNSIGNED)
    WHERE q.status IN ('waiting', 'in_procedure', 'completed')
    AND DATE(q.created_at) = CURDATE()
    ORDER BY p.last_name ASC, p.first_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Get patients from treatment plans 
$treatmentPatients = $pdo->query("
    SELECT DISTINCT p.id, p.first_name, p.middle_name, p.last_name, p.suffix, p.phone
    FROM patients p
    JOIN treatment_plans tp ON p.id = tp.patient_id
    WHERE p.id NOT IN (
        SELECT DISTINCT CAST(patient_id AS UNSIGNED) FROM queue 
        WHERE status IN ('waiting', 'in_procedure', 'completed')
        AND DATE(created_at) = CURDATE()
    )
    ORDER BY p.last_name ASC, p.first_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Combine both lists
$patients = array_merge($queuePatients, $treatmentPatients);

// Get services
$services = $pdo->query("SELECT id, name, price, mode FROM services WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Build full_name for patients
foreach ($patients as &$patient) {
    $parts = array_filter([
        $patient['first_name'] ?? '',
        $patient['middle_name'] ?? '',
        $patient['last_name'] ?? '',
        $patient['suffix'] ?? ''
    ]);
    $patient['full_name'] = implode(' ', $parts);
}
unset($patient);

// Calculate stats
$activePlans = 0; $completedPlans = 0;
if (!empty($treatmentPlans)) {
    foreach($treatmentPlans as $tp) {
        $st = $tp['overall_status'] ?? '';
        if ($st === 'Active') $activePlans++;
        if ($st === 'Completed') $completedPlans++;
    }
}

require_once '../../includes/dentist_layout_start.php';
?>

<!-- Summary Stats -->
<div class="summary-cards">
    <div class="summary-card">
        <div class="summary-icon blue" style="background: #e0f2fe; color: #0284c7;">📋</div>
        <div class="summary-info">
            <h3><?php echo count($treatmentPlans); ?></h3>
            <p>Total Plans</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon yellow">⚡</div>
        <div class="summary-info">
            <h3><?php echo $activePlans; ?></h3>
            <p>In Progress</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon green">✓</div>
        <div class="summary-info">
            <h3><?php echo $completedPlans; ?></h3>
            <p>Completed</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon" style="background: #fef3c7; color: #d97706;">📅</div>
        <div class="summary-info">
            <h3><?php echo count($treatmentPlans); ?></h3>
            <p>Total Plans</p>
        </div>
    </div>
</div>

<!-- Search & Actions -->
<div class="filter-bar-container">
    <input type="text" id="searchInput" class="clean-input search-bar" placeholder="Search by name or phone...">
    
    <select id="statusFilter" class="clean-input filter-dropdown">
        <option value="">All Status</option>
        <option value="Active">Active</option>
        <option value="Completed">Completed</option>
        <option value="On-Hold">On-Hold</option>
        <option value="Cancelled">Cancelled</option>
    </select>
    
    <select id="sortFilter" class="clean-input filter-dropdown">
        <option value="newest">Newest First</option>
        <option value="name">Name (A-Z)</option>
    </select>
</div>

<!-- Treatment Plans Table -->
<div class="section-card">
    <div class="section-title">
        <span>Active Treatment Plans</span>
    </div>
    
    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Treatment</th>
                    <th>Estimated Cost</th>
                    <th>Next Session</th>
                    <th>Status</th>
                    <th>Date Created</th>
                    <th>Appointment</th>
                    <th>Actions</th>
                </tr>
            </thead>
<tbody id="treatmentPlansTableBody">
                <?php if (empty($treatmentPlans)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 60px; color: #6b7280;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5" style="margin: 0 auto 16px; display: block;">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        <p style="font-size: 1.1rem; margin-bottom: 8px;">No treatment plans yet</p>
                        <p style="font-size: 0.875rem; color: #9ca3af;">Click "+ New Treatment Plan" to create one</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($treatmentPlans as $plan): ?>
<tr class="treatment-plan-row" 
                            data-name="<?php echo strtolower(htmlspecialchars($plan['full_name'] ?? 'Unknown')); ?>"
                            data-status="<?php echo htmlspecialchars($plan['overall_status'] ?? 'Active'); ?>">
                            <td>
                                <div class="patient-name" style="font-weight: 500;">
                                    <a href="master/user-patient/patient_details.php?id=<?php echo $plan['patient_id']; ?>" style="text-decoration: none; color: inherit;">
                                        <?php echo htmlspecialchars($plan['full_name'] ?? 'Patient #' . $plan['patient_id']); ?>
                                    </a>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 500;"><?php echo htmlspecialchars($plan['plan_name'] ?? ''); ?></div>
                            </td>
                            <td><?php echo $plan['total_estimated_cost'] ? '₱' . number_format($plan['total_estimated_cost'], 2) : '-'; ?></td>
                            <td>
                                <?php 
                                    $nextDate = $plan['next_session_date'] ?? null;
                                    $nextTime = $plan['next_session_time'] ?? null;
                                    if ($nextDate && $nextTime): 
                                        echo date('M d, Y', strtotime($nextDate)) . '<br><span style="font-size: 0.8rem; color: #6b7280;">' . date('h:i A', strtotime($nextTime)) . '</span>';
                                    elseif ($nextDate):
                                        echo date('M d, Y', strtotime($nextDate));
                                    else:
                                        echo '<span style="color: #9ca3af;">Not scheduled</span>';
                                    endif;
                                ?>
                            </td>
                            <td>
                                <?php 
                                    $status = $plan['overall_status'] ?? 'Active';
                                    $statusColors = [
                                        'Active' => ['bg' => 'dbeafe', 'text' => '1e40af'],
                                        'Completed' => ['bg' => 'dcfce7', 'text' => '15803d'],
                                        'On-Hold' => ['bg' => 'f3f4f6', 'text' => '6b7280'],
                                        'Cancelled' => ['bg' => 'fee2e2', 'text' => 'dc2626'],
                                    ];
                                    $colors = $statusColors[$status] ?? $statusColors['Active'];
                                ?>
                                <span class="status-badge" style="background: #<?php echo $colors['bg']; ?>; color: #<?php echo $colors['text']; ?>;">
                                    <?php echo htmlspecialchars($status); ?>
                                </span>
                            </td>
                            <td><?php echo $plan['created_at'] ? date('M d, Y', strtotime($plan['created_at'])) : '-'; ?></td>
                            <td>
                                <?php if (!empty($plan['appointment_ref'])): ?>
                                    <a href="dentist_appointments.php" style="color: #0284c7; text-decoration: none; font-size: 0.85rem;">
                                        <?php echo htmlspecialchars($plan['appointment_ref']); ?>
                                    </a>
                                    <br><span style="font-size: 0.75rem; color: #6b7280;"><?php echo $plan['appt_status'] ?? ''; ?></span>
                                <?php else: ?>
                                    <span style="color: #9ca3af; font-size: 0.85rem;">No appointment</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="tp-kebab-menu">
                                    <button type="button" class="tp-kebab-btn" data-plan-id="<?php echo $plan['id']; ?>" onclick="event.stopPropagation(); toggleTpKebab(this)">
                                        <span style="font-size: 18px; font-weight: bold;">⋮</span>
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

<!-- View Treatment Plan Modal (Patient History View) -->
<div id="viewPlanModal" class="modal-overlay">
    <div class="modal" style="width: 800px; max-width: 95%; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600;">📋 Patient Treatment Plans</h2>
            <button onclick="closeViewPlanModal()" class="modal-close" style="background: none; border: none; font-size: 1.5rem; color: #6b7280; cursor: pointer;">&times;</button>
        </div>
        
        <div class="modal-body">
            <div id="tpPatientInfo" style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 14px 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0369a1" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <div>
                    <div style="font-size: 0.75rem; color: #0369a1; font-weight: 600;">PATIENT</div>
                    <div id="tpPatientName" style="font-weight: 600; color: #0c4a6e;"></div>
                </div>
            </div>
            
            <div id="tpLoading" style="text-align: center; padding: 40px; color: #6b7280;">
                Loading treatment plans...
            </div>
            
            <div id="tpPlansList" style="display: none;">
                <!-- Plans will be loaded here -->
            </div>
            
            <div id="tpNoPlans" style="text-align: center; padding: 40px; color: #6b7280; display: none;">
                No treatment plans found for this patient.
            </div>

            <!-- Treatment History Section -->
            <div id="tpHistorySection" style="margin-top: 24px; display: none;">
                <h3 style="font-size: 0.9rem; font-weight: 600; color: #374151; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Past Treatment Records
                </h3>
                <div id="tpHistoryList">
                    <!-- History will be loaded here -->
                </div>
            </div>
        </div>
        
        <div class="modal-footer" style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px;">
            <button onclick="closeViewPlanModal()" class="btn-cancel">Close</button>
            <button onclick="addNewPlanForCurrentPatient()" class="btn-primary">+ Create New Plan</button>
        </div>
    </div>
</div>

<!-- New/Edit Treatment Plan Modal -->
<div id="treatmentPlanModal" class="modal-overlay">
    <div class="modal" style="width: 650px; max-height: 90vh; overflow-y: auto;">
        <h2 id="treatmentPlanModalTitle" style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">New Treatment Plan</h2>
        <form id="treatmentPlanForm">
            <input type="hidden" name="plan_id" id="plan_id" value="">
            
            <div class="form-group">
                <label>Patient *</label>
                <?php if (empty($patients)): ?>
                    <div style="padding: 12px 16px; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; color: #92400e; font-size: 0.875rem;">
                        <strong>⚠️ No patients available</strong><br>
                        There are no patients in the queue or with existing treatment plans. Please have patients check in at the queue before creating a treatment plan.
                    </div>
                <?php else: ?>
                    <select name="patient_id" id="patientSelect" required class="form-control">
                        <option value="">-- Select Patient --</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>">
                                <?php echo htmlspecialchars($patient['full_name']); ?> (<?php echo htmlspecialchars($patient['phone'] ?? 'No phone'); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #6b7280; display: block; margin-top: 6px;">Select a patient from queue or existing treatment plans</small>
                <?php endif; ?>
            </div>
            
<div class="form-group">
                <label>Plan Name *</label>
                <input type="text" name="plan_name" id="plan_name" class="form-control" required placeholder="e.g., Root Canal Treatment, Braces, etc.">
            </div>
            
            <div class="form-group" style="margin-bottom:16px;">
                <label>Number of Sessions</label>
                <div id="sessionCountBtns" style="display:flex; gap:6px; flex-wrap:wrap;">
                    <button type="button" class="sess-btn active" data-count="1" onclick="setSessionCount(1)">1</button>
                    <button type="button" class="sess-btn" data-count="2" onclick="setSessionCount(2)">2</button>
                    <button type="button" class="sess-btn" data-count="3" onclick="setSessionCount(3)">3</button>
                    <button type="button" class="sess-btn" data-count="4" onclick="setSessionCount(4)">4</button>
                    <button type="button" class="sess-btn" data-count="5" onclick="setSessionCount(5)">5</button>
                    <button type="button" class="sess-btn" data-count="6" onclick="setSessionCount(6)">6</button>
                    <button type="button" class="sess-btn" data-count="7" onclick="setSessionCount(7)">7</button>
                    <button type="button" class="sess-btn" data-count="8" onclick="setSessionCount(8)">8</button>
                </div>
                <input type="hidden" name="total_sessions" id="total_sessions" value="1">
                <input type="hidden" name="auto_schedule_all" id="tpAutoScheduleAll" value="0">
            </div>

            <div id="sessionRowsContainer"></div>

            <div class="form-group" style="margin-bottom:8px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:400; font-size:0.85rem;">
                    <input type="checkbox" id="tpAutoScheduleCheck" onchange="document.getElementById('tpAutoScheduleAll').value=this.checked?'1':'0'">
                    Auto-schedule all sessions on calendar
                </label>
            </div>

            <div class="form-group">
                <label>Teeth Numbers</label>
                <input type="text" name="teeth_numbers" id="teeth_numbers" class="form-control" placeholder="e.g., 11, 12, 13">
                <small style="color: #6b7280;">Enter tooth numbers separated by commas</small>
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" id="notes" rows="3" class="form-control" placeholder="Additional treatment notes..."></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Estimated Cost (₱)</label>
                    <input type="number" name="estimated_cost" id="estimated_cost" class="form-control" placeholder="0.00" step="0.01">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="Active">Active</option>
                        <option value="In-Progress">In-Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="On-Hold">On-Hold</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            
            <input type="hidden" name="plan_id" id="plan_id" value="">
            
            <div class="modal-actions">
                <button type="button" onclick="closeTreatmentPlanModal()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-primary">Save Treatment Plan</button>
            </div>
        </form>
    </div>
</div>

<!-- Update Progress Modal -->
<div id="progressModal" class="modal-overlay">
    <div class="modal" style="width: 400px;">
        <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Update Progress</h2>
        <form id="progressForm">
            <input type="hidden" name="progress_plan_id" id="progress_plan_id" value="">
            
            <div class="form-group">
                <label>Completed Sessions</label>
                <input type="number" name="update_completed" id="update_completed" class="form-control" value="0" min="0">
            </div>
            
            <div class="form-group">
                <label>Session Notes</label>
                <textarea name="session_notes" id="session_notes" rows="3" class="form-control" placeholder="What was done in this session?"></textarea>
            </div>
            
            <div class="form-group">
                <label>Next Session Date</label>
                <input type="date" name="update_next_date" id="update_next_date" class="form-control">
            </div>
            
            <div class="modal-actions">
                <button type="button" onclick="closeProgressModal()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
    const treatmentPlans = <?php echo json_encode($treatmentPlans); ?>;
    let currentPlanId = null;

    document.addEventListener('DOMContentLoaded', function() {
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', filterPlans);
        document.getElementById('statusFilter').addEventListener('change', filterPlans);
        
        // Form submissions
        document.getElementById('treatmentPlanForm').addEventListener('submit', saveTreatmentPlan);
        document.getElementById('progressForm').addEventListener('submit', saveProgress);
    });
    
    let tpKebabDropdown = null;
    let tpActiveButton = null;
    
    function toggleTpKebab(button) {
        // Close any open dropdown first
        if (tpActiveButton && tpActiveButton !== button && tpKebabDropdown) {
            tpActiveButton.classList.remove('active');
            tpKebabDropdown.style.display = 'none';
        }
        
        if (!tpKebabDropdown) {
            tpKebabDropdown = document.createElement('div');
            tpKebabDropdown.className = 'tp-kebab-dropdown';
            tpKebabDropdown.id = 'tpKebabDropdown';
            document.body.appendChild(tpKebabDropdown);
        }
        
        if (tpActiveButton === button && tpKebabDropdown.style.display === 'block') {
            // Close if clicking same button
            button.classList.remove('active');
            tpKebabDropdown.style.display = 'none';
            tpActiveButton = null;
        } else {
            // Open new dropdown
            const planId = button.getAttribute('data-plan-id');
            
            tpKebabDropdown.innerHTML = `
                <a href="javascript:void(0)" onclick="viewTreatmentPlan(${planId}); toggleTpKebab(document.activeElement)">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    View
                </a>
                <a href="javascript:void(0)" onclick="editTreatmentPlan(${planId}); toggleTpKebab(document.activeElement)">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    Edit
                </a>
            `;
            
            const rect = button.getBoundingClientRect();
            let left = rect.right + 8;
            let top = rect.top + 40;
            
            if (left + 160 > window.innerWidth) {
                left = rect.left - 168;
            }
            if (top + 100 > window.innerHeight) {
                top = window.innerHeight - 110;
            }
            
            tpKebabDropdown.style.left = left + 'px';
            tpKebabDropdown.style.top = top + 'px';
            tpKebabDropdown.style.display = 'block';
            
            button.classList.add('active');
            tpActiveButton = button;
        }
    }
    
    // Close kebab when clicking elsewhere
    document.addEventListener('click', function(e) {
        if (tpKebabDropdown && !e.target.closest('.tp-kebab-menu') && !e.target.closest('.tp-kebab-dropdown')) {
            tpKebabDropdown.style.display = 'none';
            if (tpActiveButton) {
                tpActiveButton.classList.remove('active');
                tpActiveButton = null;
            }
        }
    });

    function setSessionCount(n) {
        document.getElementById('total_sessions').value = n;
        document.querySelectorAll('.sess-btn').forEach(btn => {
            btn.classList.toggle('active', parseInt(btn.dataset.count) === n);
        });

        const container = document.getElementById('sessionRowsContainer');
        container.innerHTML = '';

        for (let i = 1; i <= n; i++) {
            const row = document.createElement('div');
            row.className = 'session-row';
            row.innerHTML =
                '<div class="session-row-header">Session ' + i + '</div>' +
                '<div class="session-row-fields">' +
                    '<input type="date" name="session_date_' + i + '" class="form-control session-date" min="' + new Date().toISOString().split('T')[0] + '">' +
                    '<div class="session-time-wrapper">' +
                        '<div class="session-time-slots" id="sessionSlots_' + i + '" data-session="' + i + '"></div>' +
                        '<input type="hidden" name="session_time_' + i + '" id="sessionTime_' + i + '" value="">' +
                    '</div>' +
                '</div>';
            container.appendChild(row);

            row.querySelector('.session-date').addEventListener('change', function() {
                const date = this.value;
                const slotContainer = document.getElementById('sessionSlots_' + i);
                const hiddenInput = document.getElementById('sessionTime_' + i);
                if (date) {
                    loadSessionTimeSlots(date, i, slotContainer, hiddenInput);
                } else {
                    slotContainer.innerHTML = '';
                    hiddenInput.value = '';
                }
            });
        }
    }

    function loadSessionTimeSlots(date, sessionNum, container, hiddenInput) {
        let url = '../user-patient/get_slots.php?date=' + encodeURIComponent(date);

        container.innerHTML = '<span style="color:#6b7280;font-size:0.8rem;">Loading...</span>';

        fetch(url)
        .then(res => res.json())
        .then(data => {
            container.innerHTML = '';

            if (!data.success || !data.is_open || data.is_past) {
                container.innerHTML = '<span style="color:#dc2626;font-size:0.8rem;">' + (data.message || 'Unavailable') + '</span>';
                return;
            }

            const available = data.available_slots || [];
            if (available.length === 0) {
                container.innerHTML = '<span style="color:#6b7280;font-size:0.8rem;">No available slots</span>';
                return;
            }

            available.forEach(slot => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'time-slot-btn';
                btn.textContent = formatTime12h(slot);
                btn.onclick = function() {
                    container.querySelectorAll('.time-slot-btn').forEach(b => {
                        b.style.background = '#f3f4f6';
                        b.style.color = '#374151';
                    });
                    this.style.background = '#3b82f6';
                    this.style.color = 'white';
                    hiddenInput.value = slot;
                };
                container.appendChild(btn);
            });
        })
        .catch(() => {
            container.innerHTML = '<span style="color:#dc2626;font-size:0.8rem;">Error loading slots</span>';
        });
    }

    function formatTime12h(time24) {
        const [hours, minutes] = time24.split(':').map(Number);
        const period = hours >= 12 ? 'PM' : 'AM';
        const hour12 = hours % 12 || 12;
        return hour12 + ':' + minutes.toString().padStart(2, '0') + ' ' + period;
    }

    function renderSessionsHtml(sessionsData) {
        if (!sessionsData) return '';
        try {
            const sessions = JSON.parse(sessionsData);
            if (!sessions.length) return '';
            let sHtml = '<div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e5e7eb;"><div style="color: #6b7280; font-size: 0.8rem; margin-bottom: 6px;">All Sessions</div><div style="font-size: 0.85rem;">';
            sessions.forEach(function(s, idx) {
                const parts = [];
                if (s.date) {
                    const d = new Date(s.date + 'T' + (s.time || '00:00:00'));
                    parts.push(d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }));
                }
                if (s.time) {
                    const d = new Date('2000-01-01T' + s.time);
                    parts.push(d.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' }));
                }
                sHtml += '<div style="margin-bottom: 3px;"><span style="font-weight:500;">Session ' + (idx + 1) + ':</span> ' + (parts.length ? parts.join(' @ ') : '<span style="color:#9ca3af;">Not scheduled</span>') + '</div>';
            });
            sHtml += '</div></div>';
            return sHtml;
        } catch(e) {
            return '';
        }
    }

    function filterPlans() {
        const search = document.getElementById('searchInput').value.toLowerCase();
        const status = document.getElementById('statusFilter').value;
        
        document.querySelectorAll('.treatment-plan-row').forEach(row => {
            const nameMatch = !search || row.dataset.name.includes(search);
            const statusMatch = !status || row.dataset.status === status;
            row.style.display = (nameMatch && statusMatch) ? '' : 'none';
        });
    }

    function openNewTreatmentPlanModal() {
        document.getElementById('treatmentPlanModalTitle').textContent = 'New Treatment Plan';
        document.getElementById('treatmentPlanForm').reset();
        document.getElementById('plan_id').value = '';
        document.getElementById('tpAutoScheduleAll').value = '0';
        document.getElementById('tpAutoScheduleCheck').checked = false;
        
        // Safely handle patient select - it may not exist if no patients are available
        const patientSelect = document.getElementById('patientSelect');
        if (patientSelect) {
            patientSelect.disabled = false;
            patientSelect.value = '';
        }
        
        // Initialize session rows
        setSessionCount(1);
        
        document.getElementById('treatmentPlanModal').style.display = 'flex';
    }

    function closeTreatmentPlanModal() {
        document.getElementById('treatmentPlanModal').style.display = 'none';
        document.getElementById('sessionRowsContainer').innerHTML = '';
        document.querySelectorAll('.sess-btn').forEach(btn => btn.classList.toggle('active', parseInt(btn.dataset.count) === 1));
        document.getElementById('total_sessions').value = '1';
        document.getElementById('tpAutoScheduleAll').value = '0';
        document.getElementById('tpAutoScheduleCheck').checked = false;
    }

    function closeViewPlanModal() {
        document.getElementById('viewPlanModal').style.display = 'none';
    }

    function closeProgressModal() {
        document.getElementById('progressModal').style.display = 'none';
    }

    document.getElementById('treatmentPlanModal').addEventListener('click', function(e) {
        if (e.target === this) closeTreatmentPlanModal();
    });

    document.getElementById('viewPlanModal').addEventListener('click', function(e) {
        if (e.target === this) closeViewPlanModal();
    });

    document.getElementById('progressModal').addEventListener('click', function(e) {
        if (e.target === this) closeProgressModal();
    });

    let currentPatientId = null;

    function viewTreatmentPlan(id) {
        const plan = treatmentPlans.find(p => p.id == id);
        if (!plan) return;
        
        currentPatientId = plan.patient_id;
        document.getElementById('tpPatientName').textContent = plan.full_name || 'Unknown Patient';
        
        // Show loading state
        document.getElementById('tpLoading').style.display = 'block';
        document.getElementById('tpPlansList').style.display = 'none';
        document.getElementById('tpNoPlans').style.display = 'none';
        document.getElementById('tpHistorySection').style.display = 'none';
        
        // Show modal
        document.getElementById('viewPlanModal').style.display = 'flex';
        
        // Fetch treatment plans for this patient
        fetch('../shared/process_treatment_plan.php?patient_id=' + currentPatientId)
        .then(response => response.json())
        .then(data => {
            document.getElementById('tpLoading').style.display = 'none';
            
            if (data.success) {
                // Render Plans
                if (data.plans && data.plans.length > 0) {
                    renderTreatmentPlans(data.plans);
                    document.getElementById('tpPlansList').style.display = 'block';
                } else {
                    document.getElementById('tpNoPlans').style.display = 'block';
                }

                // Render History
                if (data.history && data.history.length > 0) {
                    renderTreatmentHistory(data.history);
                    document.getElementById('tpHistorySection').style.display = 'block';
                }
            } else {
                document.getElementById('tpNoPlans').textContent = data.message || 'Error loading treatment plans';
                document.getElementById('tpNoPlans').style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('tpLoading').textContent = 'Error loading treatment plans';
        });
    }

    function renderTreatmentPlans(plans) {
        const container = document.getElementById('tpPlansList');
        let html = '';
        
        plans.forEach(plan => {
            const total = plan.total_sessions || 1;
            const completed = plan.completed_sessions || 0;
            const percent = Math.round((completed / total) * 100);
            const statusColor = plan.status === 'completed' ? '#22c55e' : plan.status === 'in_progress' ? '#f59e0b' : '#3b82f6';
            const statusText = plan.status === 'completed' ? 'Completed' : plan.status === 'in_progress' ? 'In Progress' : 'Active';
            
            html += `
                <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; margin-bottom: 16px; position: relative;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                        <div>
                            <div style="font-weight: 600; font-size: 1rem;">${plan.treatment_name || 'General'}</div>
                            <div style="font-size: 0.85rem; color: #6b7280;">Treatment Plan</div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="background: ${statusColor}20; color: ${statusColor}; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">
                                ${statusText}
                            </span>
                            <button onclick="editTreatmentPlan(${plan.id})" style="background: none; border: none; color: #6b7280; cursor: pointer; padding: 4px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                            <span style="font-size: 0.85rem; color: #6b7280;">Progress</span>
                            <span style="font-size: 0.85rem; font-weight: 600;">${completed} / ${total} sessions (${percent}%)</span>
                        </div>
                        <div style="height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                            <div style="width: ${percent}%; height: 100%; background: linear-gradient(90deg, #0ea5e9, #0284c7); border-radius: 4px;"></div>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 0.85rem;">
                        <div>
                            <div style="color: #6b7280;">Teeth</div>
                            <div style="font-weight: 500;">${plan.teeth_numbers || 'Not specified'}</div>
                        </div>
                        <div>
                            <div style="color: #6b7280;">Next Session</div>
                            <div style="font-weight: 500;">${plan.next_session_date ? new Date(plan.next_session_date).toLocaleDateString() : 'Not scheduled'}</div>
                        </div>
                    </div>
                    ${renderSessionsHtml(plan.sessions_data)}
                    ${plan.notes ? `
                        <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e5e7eb;">
                            <div style="color: #6b7280; font-size: 0.8rem; margin-bottom: 4px;">Notes</div>
                            <div style="font-size: 0.9rem;">${plan.notes}</div>
                        </div>
                    ` : ''}
                </div>
            `;
        });
        
        container.innerHTML = html;
    }

    function renderTreatmentHistory(history) {
        const container = document.getElementById('tpHistoryList');
        let html = '';
        
        history.forEach(item => {
            const date = new Date(item.treatment_date).toLocaleDateString('en-PH', { 
                year: 'numeric', month: 'short', day: 'numeric' 
            });
            const doctor = item.doctor_first && item.doctor_last ? `Dr. ${item.doctor_first} ${item.doctor_last}` : 'Dentist';
            
            html += `
                <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: 600; font-size: 0.85rem; color: #111827;">${item.service_name || item.treatment_type || 'Treatment'}</div>
                        <div style="font-size: 0.75rem; color: #6b7280; margin-top: 2px;">
                            <span>🦷 ${item.teeth_numbers || 'N/A'}</span>
                            <span style="margin: 0 8px; color: #e5e7eb;">|</span>
                            <span>👨‍⚕️ ${doctor}</span>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 0.8rem; font-weight: 500; color: #374151;">${date}</div>
                        <div style="font-size: 0.7rem; color: #059669; font-weight: 600; text-transform: uppercase; margin-top: 2px;">Completed</div>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
    }

    function addNewPlanForCurrentPatient() {
        if (!currentPatientId) return;
        closeViewPlanModal();
        
        document.getElementById('treatmentPlanModalTitle').textContent = 'New Treatment Plan';
        document.getElementById('treatmentPlanForm').reset();
        document.getElementById('plan_id').value = '';
        document.getElementById('tpAutoScheduleAll').value = '0';
        document.getElementById('tpAutoScheduleCheck').checked = false;
        
        const patientSelect = document.getElementById('patientSelect');
        if (patientSelect) {
            patientSelect.value = currentPatientId;
            patientSelect.disabled = true;
        }
        
        // Initialize session rows
        setSessionCount(1);
        
        document.getElementById('treatmentPlanModal').style.display = 'flex';
    }

    function editCurrentPlan() {
        closeViewPlanModal();
        if (currentPlanId) {
            editTreatmentPlan(currentPlanId);
        }
    }

    function editTreatmentPlan(id) {
        const plan = treatmentPlans.find(p => p.id == id);
        if (!plan) return;
        
        document.getElementById('treatmentPlanModalTitle').textContent = 'Edit Treatment Plan';
        document.getElementById('plan_id').value = plan.id;
        document.getElementById('patientSelect').value = plan.patient_id || '';
        document.getElementById('patientSelect').disabled = true;
        document.getElementById('plan_name').value = plan.plan_name || '';
        document.getElementById('teeth_numbers').value = plan.teeth_numbers || '';
        document.getElementById('notes').value = plan.notes || '';
        document.getElementById('estimated_cost').value = plan.total_estimated_cost || '';
        document.getElementById('status').value = plan.overall_status || 'Active';
        document.getElementById('tpAutoScheduleAll').value = '0';
        document.getElementById('tpAutoScheduleCheck').checked = false;
        
        // Restore session rows from saved sessions_data
        const totalSessions = parseInt(plan.total_sessions) || 1;
        setSessionCount(totalSessions);
        
        if (plan.sessions_data) {
            try {
                const sessions = JSON.parse(plan.sessions_data);
                sessions.forEach(function(s, idx) {
                    const i = idx + 1;
                    const dateInput = document.querySelector('[name="session_date_' + i + '"]');
                    const timeInput = document.getElementById('sessionTime_' + i);
                    if (dateInput && s.date) {
                        dateInput.value = s.date;
                    }
                    if (timeInput && s.time) {
                        timeInput.value = s.time;
                        // Load and select time slot
                        const slotContainer = document.getElementById('sessionSlots_' + i);
                        if (slotContainer && s.date) {
                            loadSessionTimeSlots(s.date, i, slotContainer, timeInput);
                            setTimeout(function() {
                                slotContainer.querySelectorAll('.time-slot-btn').forEach(function(btn) {
                                    if (btn.textContent === formatTime12h(s.time)) {
                                        btn.style.background = '#3b82f6';
                                        btn.style.color = 'white';
                                    }
                                });
                            }, 300);
                        }
                    }
                });
            } catch(e) {}
        }
        
        document.getElementById('treatmentPlanModal').style.display = 'flex';
    }

    function updateProgress(id) {
        // Note: The new schema doesn't support session tracking
        // This feature is temporarily disabled
        alert('Session tracking is not available in the current database schema. Please edit the plan directly to change status.');
    }

    function saveTreatmentPlan(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        // Set first session date/time as next_session_date/time for backward compat
        const firstDate = document.querySelector('[name="session_date_1"]');
        const firstTime = document.getElementById('sessionTime_1');
        if (firstDate && firstDate.value) {
            formData.set('next_session_date', firstDate.value);
            formData.set('next_session_time', firstTime ? firstTime.value : '');
        }
        
        fetch('../shared/process_treatment_plan.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message); // Show the message
            if (data.success) {
                closeTreatmentPlanModal();
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error saving treatment plan');
        });
    }

    function saveProgress(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('../api/update_treatment_progress.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeProgressModal();
                location.reload();
            } else {
                alert(data.message || 'Error updating progress');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating progress');
        });
    }
    
    // Portal Pattern: Move modals to body level to escape stacking context
    // This ensures modals appear above sidebar and all other elements
    document.addEventListener('DOMContentLoaded', function() {
        const viewPlanModal = document.getElementById('viewPlanModal');
        const treatmentPlanModal = document.getElementById('treatmentPlanModal');
        const progressModal = document.getElementById('progressModal');
        
        if (viewPlanModal) {
            document.body.appendChild(viewPlanModal);
        }
        if (treatmentPlanModal) {
            document.body.appendChild(treatmentPlanModal);
        }
        if (progressModal) {
            document.body.appendChild(progressModal);
        }
        
        // Close modals when clicking outside
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });
        
        // Pre-select patient if provided in URL
        const urlParams = new URLSearchParams(window.location.search);
        const preselectedPatientId = urlParams.get('patient_id');
        if (preselectedPatientId) {
            const patientSelect = document.getElementById('patientSelect');
            if (patientSelect) {
                patientSelect.value = preselectedPatientId;
                // Trigger change to populate services, etc.
                patientSelect.dispatchEvent(new Event('change'));
            }
        }
    });
</script>

<style>

/* Session Button Styles */
.sess-btn {
    width: 40px; height: 40px;
    border-radius: 50%;
    border: 2px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.sess-btn:hover {
    border-color: #3b82f6;
    color: #3b82f6;
    background: #eff6ff;
}
.sess-btn.active {
    background: #3b82f6;
    border-color: #3b82f6;
    color: #ffffff;
    box-shadow: 0 4px 8px rgba(59,130,246,0.3);
}
.session-row {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 10px;
}
.session-row-header {
    font-size: 0.85rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}
.session-row-fields {
    display: flex;
    gap: 10px;
    align-items: flex-start;
}
.session-row-fields .session-date {
    width: 180px;
    flex-shrink: 0;
}
.session-time-wrapper {
    flex: 1;
}
.session-time-slots {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.time-slot-btn {
    padding: 6px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: #f3f4f6;
    color: #374151;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s;
}
.time-slot-btn:hover {
    border-color: #3b82f6;
    background: #eff6ff;
}

/* Time Slot Button Styles */
.time-slot-btn {
    padding: 8px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #f3f4f6;
    color: #374151;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s;
}

.modal-actions {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}
.summary-cards{
    margin-bottom: 0px;

}
/* Container to keep everything in a neat row */
.filter-bar-container {
    display: flex;
    align-items: center;
    gap: 12px;         /* The exact spacing between the boxes in your image */
    width: 100%;
    padding-bottom: 25px;
    /* Optional: If your page background is white, you might want to wrap this in a very light gray (#f4f6f8) div so the white boxes pop, just like in your screenshot! */
}

/* Unified clean styling applied to BOTH the search input and dropdowns */
.clean-input {
    background-color: #ffffff;
    border: 1px solid #cbd5e1;  /* That specific soft, light grayish-blue border */
    border-radius: 6px;         /* Soft rounded corners */
    padding: 10px 14px;         /* Comfortable breathing room for the text */
    font-size: 14px;
    color: #334155;             /* Dark slate text */
    outline: none;              /* Removes the ugly default browser glow */
    transition: border-color 0.2s ease;
    box-sizing: border-box;
}

/* Match the soft gray placeholder text from your image */
.clean-input::placeholder {
    color: #94a3b8;
}

/* Subtle darker border when the user clicks to type or select */
.clean-input:focus {
    border-color: #94a3b8;
}

/* Search bar specific rule */
.search-bar {
    flex: 1; /* This forces the search bar to stretch and push the dropdowns to the right */
}

/* Dropdown specific rules for that ultra-clean look */
.filter-dropdown {
    cursor: pointer;
    min-width: 130px;
    
    /* This removes the clunky default browser dropdown arrow */
    appearance: none; 
    -webkit-appearance: none;
    -moz-appearance: none;
    
    /* Adds a clean, custom SVG arrow that perfectly matches the text color */
    background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23334155%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E');
    background-repeat: no-repeat;
    background-position: right 12px top 50%;
    background-size: 10px auto;
    padding-right: 32px; /* Makes room so the text doesn't overlap the custom arrow */
}

.search-filters{
    margin-bottom: 0px;
}

/* Treatment Plan Kebab Menu Styles */
.tp-kebab-menu {
    position: relative;
    display: inline-block;
}

.tp-kebab-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 6px 8px;
    border-radius: 6px;
    color: #6b7280;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.tp-kebab-btn:hover {
    background-color: #f3f4f6;
    color: #374151;
}

.tp-kebab-btn.active {
    background-color: #e5e7eb;
    color: #111827;
}

.tp-kebab-dropdown {
    display: none;
    position: fixed;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
    min-width: 150px;
    z-index: 100001;
    overflow: hidden;
}

.tp-kebab-dropdown.show {
    display: block;
}

.tp-kebab-dropdown a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    color: #374151;
    text-decoration: none;
    font-size: 0.875rem;
    cursor: pointer;
}

.tp-kebab-dropdown a:hover {
    background-color: #f9fafb;
    color: #111827;
}

.tp-kebab-dropdown a svg {
    width: 16px;
    height: 16px;
}
</style>

<?php require_once '../../includes/dentist_layout_end.php'; ?>
