<?php
$pageTitle = 'Treatment Plan';

try {
    require_once 'config/database.php';
    
    // Get treatment plans with patient info
    $stmt = $pdo->query("
        SELECT 
            tp.id,
            tp.patient_id,
            p.first_name,
            p.middle_name,
            p.last_name,
            p.suffix,
            p.phone,
            tp.treatment_name,
            tp.treatment_type,
            tp.total_sessions,
            tp.completed_sessions,
            tp.teeth_numbers,
            tp.status,
            tp.notes,
            tp.created_at,
            tp.next_session_date
        FROM treatment_plans tp
        LEFT JOIN patients p ON tp.patient_id = p.id
        ORDER BY tp.created_at DESC
    ");
    $treatmentPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build full_name for each treatment plan
    foreach ($treatmentPlans as &$plan) {
        $parts = array_filter([
            $plan['first_name'] ?? '',
            $plan['middle_name'] ?? '',
            $plan['last_name'] ?? '',
            $plan['suffix'] ?? ''
        ]);
        $plan['full_name'] = implode(' ', $parts);
    }
    unset($plan);
    
    // Get patients from queue for the dropdown (today's queue with active statuses)
    $stmtPatients = $pdo->query("
        SELECT DISTINCT p.id, p.first_name, p.middle_name, p.last_name, p.suffix, p.phone
        FROM patients p
        JOIN queue q ON p.id = q.patient_id
        WHERE q.status IN ('waiting', 'in_procedure', 'on_hold')
        AND DATE(q.created_at) = CURDATE()
        ORDER BY p.last_name ASC, p.first_name ASC
    ");
    $queuePatients = $stmtPatients->fetchAll(PDO::FETCH_ASSOC);
    
    // Build full_name for queue patients
    foreach ($queuePatients as &$patient) {
        $parts = array_filter([
            $patient['first_name'] ?? '',
            $patient['middle_name'] ?? '',
            $patient['last_name'] ?? '',
            $patient['suffix'] ?? ''
        ]);
        $patient['full_name'] = implode(' ', $parts);
    }
    unset($patient);
    
    // Get patients from treatment plans (patients who already have treatment plans but not in today's queue)
    $stmtTreatmentPatients = $pdo->query("
        SELECT DISTINCT p.id, p.first_name, p.middle_name, p.last_name, p.suffix, p.phone
        FROM patients p
        JOIN treatment_plans tp ON p.id = tp.patient_id
        WHERE p.id NOT IN (
            SELECT DISTINCT patient_id FROM queue 
            WHERE status IN ('waiting', 'in_procedure', 'on_hold')
            AND DATE(created_at) = CURDATE()
        )
        ORDER BY p.last_name ASC, p.first_name ASC
    ");
    $treatmentPatients = $stmtTreatmentPatients->fetchAll(PDO::FETCH_ASSOC);
    
    // Build full_name for treatment patients
    foreach ($treatmentPatients as &$patient) {
        $parts = array_filter([
            $patient['first_name'] ?? '',
            $patient['middle_name'] ?? '',
            $patient['last_name'] ?? '',
            $patient['suffix'] ?? ''
        ]);
        $patient['full_name'] = implode(' ', $parts);
    }
    unset($patient);
    
    // Combine both lists: queue patients first, then treatment patients
    $patients = array_merge($queuePatients, $treatmentPatients);
    
    // Get services for dropdown
    $stmtServices = $pdo->query("SELECT id, name, price, mode FROM services WHERE is_active = 1 ORDER BY name");
    $services = $stmtServices->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $treatmentPlans = [];
    $patients = [];
    $services = [];
}

// Calculate stats
$activePlans = count(array_filter($treatmentPlans, fn($tp) => $tp['status'] === 'active'));
$inProgress = count(array_filter($treatmentPlans, fn($tp) => $tp['status'] === 'in_progress'));
$completedPlans = count(array_filter($treatmentPlans, fn($tp) => $tp['status'] === 'completed'));

require_once 'includes/dentist_layout_start.php';
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
            <h3><?php echo $activePlans + $inProgress; ?></h3>
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
            <h3><?php echo count(array_filter($treatmentPlans, fn($tp) => !empty($tp['next_session_date']))); ?></h3>
            <p>Sessions Today</p>
        </div>
    </div>
</div>

<!-- Search & Actions -->
<div class="filter-bar-container">
    <input type="text" id="searchInput" class="clean-input search-bar" placeholder="Search by name or phone...">
    
    <select id="statusFilter" class="clean-input filter-dropdown">
        <option value="">All Status</option>
        <option value="waiting">Waiting</option>
        <option value="in_procedure">In Procedure</option>
        <option value="completed">Completed</option>
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
                    <th>Patient Name</th>
                    <th>Treatment</th>
                    <th>Teeth</th>
                    <th>Progress</th>
                    <th>Status</th>
                    <th>Next Session</th>
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
                            data-status="<?php echo htmlspecialchars($plan['status']); ?>">
                            <td>
                                <div class="patient-name" style="font-weight: 500;">
                                    <a href="patient_details.php?id=<?php echo $plan['patient_id']; ?>" style="text-decoration: none; color: inherit;">
                                        <?php echo htmlspecialchars($plan['full_name'] ?? 'Unknown'); ?>
                                    </a>
                                </div>
                                <div style="font-size: 0.85rem; color: #6b7280;"><?php echo htmlspecialchars($plan['phone'] ?? 'No phone'); ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 500;"><?php echo htmlspecialchars($plan['treatment_name'] ?? $plan['treatment_type'] ?? 'General'); ?></div>
                                <div style="font-size: 0.85rem; color: #6b7280;"><?php echo htmlspecialchars($plan['treatment_type'] ?? ''); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($plan['teeth_numbers'] ?? 'N/A'); ?></td>
                            <td>
                                <?php 
                                    $total = $plan['total_sessions'] ?? 1;
                                    $completed = $plan['completed_sessions'] ?? 0;
                                    $percent = round(($completed / $total) * 100);
                                ?>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 80px; height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden;">
                                        <div style="width: <?php echo $percent; ?>%; height: 100%; background: #0ea5e9; border-radius: 3px;"></div>
                                    </div>
                                    <span style="font-size: 0.85rem; color: #6b7280;"><?php echo $completed; ?>/<?php echo $total; ?></span>
                                </div>
                            </td>
                            <td>
                                <?php 
                                    $status = $plan['status'] ?? 'active';
                                    $statusColors = [
                                        'active' => ['bg' => 'dbeafe', 'text' => '1e40af'],
                                        'in_progress' => ['bg' => 'fef3c7', 'text' => '92400e'],
                                        'completed' => ['bg' => 'dcfce7', 'text' => '15803d'],
                                        'on_hold' => ['bg' => 'f3f4f6', 'text' => '6b7280'],
                                        'cancelled' => ['bg' => 'fee2e2', 'text' => 'dc2626'],
                                    ];
                                    $colors = $statusColors[$status] ?? $statusColors['active'];
                                ?>
                                <span class="status-badge" style="background: #<?php echo $colors['bg']; ?>; color: #<?php echo $colors['text']; ?>;">
                                    <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($plan['next_session_date'])): ?>
                                    <div><?php echo date('M d, Y', strtotime($plan['next_session_date'])); ?></div>
                                <?php else: ?>
                                    <span style="color: #9ca3af;">Not scheduled</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="patient-actions">
                                    <button class="action-btn icon" title="View Details" onclick="viewTreatmentPlan(<?php echo $plan['id']; ?>)">👁️</button>
                                    <button class="action-btn icon" title="Edit" onclick="editTreatmentPlan(<?php echo $plan['id']; ?>)">✏️</button>
                                    <button class="action-btn icon" title="Update Progress" onclick="updateProgress(<?php echo $plan['id']; ?>)">📈</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- View Treatment Plan Modal -->
<div id="viewPlanModal" class="modal-overlay">
    <div class="modal" style="width: 600px; max-height: 90vh; overflow-y: auto;">
        <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Treatment Plan Details</h2>
        <div id="viewPlanContent"></div>
        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px;">
            <button onclick="closeViewPlanModal()" class="btn-cancel">Close</button>
            <button onclick="editCurrentPlan()" class="btn-primary">Edit Plan</button>
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
            
            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label>Treatment Name *</label>
                    <input type="text" name="treatment_name" id="treatment_name" required class="form-control" placeholder="e.g., Root Canal Therapy">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Treatment Type</label>
                    <select name="treatment_type" id="treatment_type" class="form-control">
                        <option value="">Select Type</option>
                        <option value="Root Canal">Root Canal</option>
                        <option value="Extraction">Extraction</option>
                        <option value="Filling">Filling</option>
                        <option value="Cleaning">Cleaning</option>
                        <option value="Braces">Braces/Orthodontics</option>
                        <option value="Denture">Denture</option>
                        <option value="Crown & Bridge">Crown & Bridge</option>
                        <option value="Whitening">Whitening</option>
                        <option value="Checkup">Checkup</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Teeth Numbers</label>
                    <input type="text" name="teeth_numbers" id="teeth_numbers" class="form-control" placeholder="e.g., 14, 16, 26">
                    <small style="color: #6b7280;">Separate with commas</small>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Total Sessions</label>
                    <input type="number" name="total_sessions" id="total_sessions" class="form-control" value="1" min="1" max="20">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Completed Sessions</label>
                    <input type="number" name="completed_sessions" id="completed_sessions" class="form-control" value="0" min="0" max="20">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="active">Active</option>
                        <option value="in_progress">In Progress</option>
                        <option value="on_hold">On Hold</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Next Session Date</label>
                    <input type="date" name="next_session_date" id="next_session_date" class="form-control">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Estimated Cost (₱)</label>
                    <input type="number" name="estimated_cost" id="estimated_cost" class="form-control" placeholder="0.00" step="0.01">
                </div>
            </div>
            
            <div class="form-group">
                <label>Treatment Notes</label>
                <textarea name="notes" id="notes" rows="4" class="form-control" placeholder="Detailed description of the treatment plan, procedures to be done, etc."></textarea>
            </div>
            
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
        document.getElementById('searchTreatmentPlan').addEventListener('input', filterPlans);
        document.getElementById('statusFilter').addEventListener('change', filterPlans);
        
        // Form submissions
        document.getElementById('treatmentPlanForm').addEventListener('submit', saveTreatmentPlan);
        document.getElementById('progressForm').addEventListener('submit', saveProgress);
        
        // Service selection auto-fill
        document.getElementById('treatment_type')?.addEventListener('change', function() {
            const serviceName = this.value;
            // Could auto-fill treatment_name if needed
        });
    });

    function filterPlans() {
        const search = document.getElementById('searchTreatmentPlan').value.toLowerCase();
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
        
        // Safely handle patient select - it may not exist if no patients are available
        const patientSelect = document.getElementById('patientSelect');
        if (patientSelect) {
            patientSelect.disabled = false;
            patientSelect.value = '';
        }
        
        document.getElementById('treatmentPlanModal').style.display = 'flex';
    }

    function closeTreatmentPlanModal() {
        document.getElementById('treatmentPlanModal').style.display = 'none';
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

    function viewTreatmentPlan(id) {
        const plan = treatmentPlans.find(p => p.id == id);
        if (!plan) return;
        
        currentPlanId = id;
        const total = plan.total_sessions || 1;
        const completed = plan.completed_sessions || 0;
        const percent = Math.round((completed / total) * 100);
        
        document.getElementById('viewPlanContent').innerHTML = `
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <div style="color: #6b7280; font-size: 0.875rem;">Patient</div>
                        <div style="font-weight: 600; font-size: 1.1rem;">${plan.full_name || 'Unknown'}</div>
                        <div style="color: #6b7280;">${plan.phone || 'No phone'}</div>
                    </div>
                    <div>
                        <div style="color: #6b7280; font-size: 0.875rem;">Treatment</div>
                        <div style="font-weight: 600; font-size: 1.1rem;">${plan.treatment_name || plan.treatment_type || 'General'}</div>
                        <div style="color: #6b7280;">${plan.treatment_type || ''}</div>
                    </div>
                </div>
                
                <div style="background: #f9fafb; padding: 16px; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: #6b7280;">Progress</span>
                        <span style="font-weight: 600;">${completed} / ${total} sessions (${percent}%)</span>
                    </div>
                    <div style="height: 10px; background: #e5e7eb; border-radius: 5px; overflow: hidden;">
                        <div style="width: ${percent}%; height: 100%; background: linear-gradient(90deg, #0ea5e9, #0284c7); border-radius: 5px; transition: width 0.3s ease;"></div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <div style="color: #6b7280; font-size: 0.875rem;">Teeth Numbers</div>
                        <div>${plan.teeth_numbers || 'Not specified'}</div>
                    </div>
                    <div>
                        <div style="color: #6b7280; font-size: 0.875rem;">Status</div>
                        <span class="status-badge" style="background: ${plan.status === 'completed' ? '#dcfce7' : plan.status === 'in_progress' ? '#fef3c7' : '#dbeafe'}; color: ${plan.status === 'completed' ? '#15803d' : plan.status === 'in_progress' ? '#92400e' : '#1e40af'};">
                            ${(plan.status || 'active').replace('_', ' ')}
                        </span>
                    </div>
                    <div>
                        <div style="color: #6b7280; font-size: 0.875rem;">Next Session</div>
                        <div>${plan.next_session_date ? new Date(plan.next_session_date).toLocaleDateString() : 'Not scheduled'}</div>
                    </div>
                    <div>
                        <div style="color: #6b7280; font-size: 0.875rem;">Created</div>
                        <div>${new Date(plan.created_at).toLocaleDateString()}</div>
                    </div>
                </div>
                
                ${plan.notes ? `
                    <div>
                        <div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 8px;">Notes</div>
                        <div style="background: #f9fafb; padding: 16px; border-radius: 8px; line-height: 1.6;">${plan.notes}</div>
                    </div>
                ` : ''}
            </div>
        `;
        
        document.getElementById('viewPlanModal').style.display = 'flex';
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
        document.getElementById('treatment_name').value = plan.treatment_name || '';
        document.getElementById('treatment_type').value = plan.treatment_type || '';
        document.getElementById('teeth_numbers').value = plan.teeth_numbers || '';
        document.getElementById('total_sessions').value = plan.total_sessions || 1;
        document.getElementById('completed_sessions').value = plan.completed_sessions || 0;
        document.getElementById('status').value = plan.status || 'active';
        document.getElementById('next_session_date').value = plan.next_session_date || '';
        document.getElementById('notes').value = plan.notes || '';
        
        document.getElementById('treatmentPlanModal').style.display = 'flex';
    }

    function updateProgress(id) {
        const plan = treatmentPlans.find(p => p.id == id);
        if (!plan) return;
        
        currentPlanId = id;
        document.getElementById('progress_plan_id').value = id;
        document.getElementById('update_completed').value = plan.completed_sessions || 0;
        document.getElementById('update_completed').max = plan.total_sessions || 10;
        document.getElementById('session_notes').value = '';
        document.getElementById('update_next_date').value = plan.next_session_date || '';
        
        document.getElementById('progressModal').style.display = 'flex';
    }

    function saveTreatmentPlan(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('process_treatment_plan.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeTreatmentPlanModal();
                location.reload();
            } else {
                alert(data.message || 'Error saving treatment plan');
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
        
        fetch('update_treatment_progress.php', {
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
    });
</script>

<style>
/* Modal Styles - Portal Pattern */
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
</style>

<?php require_once 'includes/dentist_layout_end.php'; ?>
