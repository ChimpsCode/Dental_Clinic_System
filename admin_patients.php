<?php
/**
 * Patient Records - Admin page for viewing all patient records
 */

$pageTitle = 'Patient Records';

try {
    require_once 'config/database.php';
    
    // Check if archive columns exist (backward compatibility)
    $checkColumn = $pdo->query("SHOW COLUMNS FROM patients LIKE 'is_archived'");
    $hasArchiveColumn = $checkColumn->rowCount() > 0;
    
    // Check if registration_source column exists
    $checkSourceCol = $pdo->query("SHOW COLUMNS FROM patients LIKE 'registration_source'");
    $hasSourceColumn = $checkSourceCol->rowCount() > 0;
    
    // Pagination
    $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $patientsPerPage = 7;
    $offset = ($currentPage - 1) * $patientsPerPage;
    
    // Build WHERE clause - exclude archived and appointment-only patients
    $whereConditions = [];
    if ($hasArchiveColumn) {
        $whereConditions[] = "is_archived = 0";
    }
    // Only show patients who are fully registered
    // 'direct' = registered directly
    // 'appointment_converted' = came from appointment and completed new admission
    // 'appointment' = only booked appointment, not yet processed (HIDDEN)
    if ($hasSourceColumn) {
        $whereConditions[] = "(registration_source IS NULL OR registration_source = 'direct' OR registration_source = 'appointment_converted')";
    }
    
    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) FROM patients $whereClause";
    $stmt = $pdo->query($countQuery);
    $totalPatients = $stmt->fetchColumn();
    $totalPages = ceil($totalPatients / $patientsPerPage);
    
    // Get patients with queue status and pagination
    // Exclude archived and appointment-only patients
    $whereConditions = [];
    if ($hasArchiveColumn) {
        $whereConditions[] = "p.is_archived = 0";
    }
    if ($hasSourceColumn) {
        $whereConditions[] = "(p.registration_source IS NULL OR p.registration_source = 'direct' OR p.registration_source = 'appointment_converted')";
    }
    
    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
    
    $stmt = $pdo->query("
        SELECT p.*, 
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
               q.status as queue_status, 
               q.treatment_type,
               q.queue_time,
               q.priority
        FROM patients p 
        LEFT JOIN queue q ON p.id = q.patient_id 
            AND q.id = (
                SELECT MAX(id) FROM queue 
                WHERE patient_id = p.id 
                AND DATE(q.created_at) = CURDATE()
            )
        $whereClause
        ORDER BY p.created_at DESC
        LIMIT $offset, $patientsPerPage
    ");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate stats from ALL patients (not just current page)
    // Exclude archived and appointment-only patients
    $statsConditions = [];
    if ($hasArchiveColumn) {
        $statsConditions[] = "p.is_archived = 0";
    }
    if ($hasSourceColumn) {
        $statsConditions[] = "(p.registration_source IS NULL OR p.registration_source = 'direct' OR p.registration_source = 'appointment_converted')";
    }
    
    $statsWhere = !empty($statsConditions) ? "WHERE " . implode(" AND ", $statsConditions) . " AND" : "WHERE";
    
    $inQueueStmt = $pdo->query("
        SELECT COUNT(DISTINCT p.id) FROM patients p
        LEFT JOIN queue q ON p.id = q.patient_id 
        $statsWhere q.status IN ('waiting', 'in_procedure')
        AND (q.id IS NULL OR q.id = (
            SELECT MAX(id) FROM queue WHERE patient_id = p.id AND DATE(created_at) = CURDATE()
        ))
    ");
    $inQueue = $inQueueStmt->fetchColumn();
    
    $scheduledStmt = $pdo->query("
        SELECT COUNT(DISTINCT p.id) FROM patients p
        LEFT JOIN queue q ON p.id = q.patient_id 
        $statsWhere q.status = 'scheduled'
        AND (q.id IS NULL OR q.id = (
            SELECT MAX(id) FROM queue WHERE patient_id = p.id AND DATE(created_at) = CURDATE()
        ))
    ");
    $scheduledCount = $scheduledStmt->fetchColumn();
    
    // Stats for new patients this month
    $monthConditions = [];
    if ($hasArchiveColumn) {
        $monthConditions[] = "is_archived = 0";
    }
    if ($hasSourceColumn) {
        $monthConditions[] = "(registration_source IS NULL OR registration_source = 'direct' OR registration_source = 'appointment_converted')";
    }
    $monthConditions[] = "created_at > DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    
    $newThisMonthWhere = "WHERE " . implode(" AND ", $monthConditions);
    $newThisMonthStmt = $pdo->query("
        SELECT COUNT(*) FROM patients $newThisMonthWhere
    ");
    $newThisMonth = $newThisMonthStmt->fetchColumn();
    
    // Pagination calculations
    $showingStart = $totalPatients > 0 ? $offset + 1 : 0;
    $showingEnd = min($offset + $patientsPerPage, $totalPatients);
    
} catch (Exception $e) {
    $patients = [];
    $totalPatients = 0;
    $inQueue = 0;
    $scheduledCount = 0;
    $newThisMonth = 0;
    $currentPage = 1;
    $totalPages = 0;
    $patientsPerPage = 7;
    $showingStart = 0;
    $showingEnd = 0;
}

require_once __DIR__ . '/includes/admin_layout_start.php';
?>
            <div class="content-main">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Patient Records</h2>
                </div>

                <!-- Summary Stats Cards -->
                <div class="summary-cards" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 20px; margin-bottom: 24px;">
                    <div class="summary-card" style="background: white; border-radius: 12px; padding: 20px; border: 1px solid #e5e7eb; display: flex; align-items: center; gap: 16px;">
                        <div class="summary-icon" style="width: 48px; height: 48px; background: #dbeafe; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">👥</div>
                        <div class="summary-info">
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo $totalPatients; ?></h3>
                            <p style="font-size: 0.875rem; color: #6b7280; margin: 0;">Total Patients</p>
                        </div>
                    </div>
                    <div class="summary-card" style="background: white; border-radius: 12px; padding: 20px; border: 1px solid #e5e7eb; display: flex; align-items: center; gap: 16px;">
                        <div class="summary-icon" style="width: 48px; height: 48px; background: #fef3c7; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">⏰</div>
                        <div class="summary-info">
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo $inQueue; ?></h3>
                            <p style="font-size: 0.875rem; color: #6b7280; margin: 0;">In Queue</p>
                        </div>
                    </div>
                    <div class="summary-card" style="background: white; border-radius: 12px; padding: 20px; border: 1px solid #e5e7eb; display: flex; align-items: center; gap: 16px;">
                        <div class="summary-icon" style="width: 48px; height: 48px; background: #e0e7ff; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">📅</div>
                        <div class="summary-info">
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo $scheduledCount; ?></h3>
                            <p style="font-size: 0.875rem; color: #6b7280; margin: 0;">Scheduled</p>
                        </div>
                    </div>
                    <div class="summary-card" style="background: white; border-radius: 12px; padding: 20px; border: 1px solid #e5e7eb; display: flex; align-items: center; gap: 16px;">
                        <div class="summary-icon" style="width: 48px; height: 48px; background: #d1fae5; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">✓</div>
                        <div class="summary-info">
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo $newThisMonth; ?></h3>
                            <p style="font-size: 0.875rem; color: #6b7280; margin: 0;">New This Month</p>
                        </div>
                    </div>
                    <div class="summary-card" style="background: white; border-radius: 12px; padding: 20px; border: 1px solid #e5e7eb; display: flex; align-items: center; gap: 16px;">
                        <div class="summary-icon" style="width: 48px; height: 48px; background: #fee2e2; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">📋</div>
                        <div class="summary-info">
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #111827; margin: 0;"><?php echo $totalPatients; ?></h3>
                            <p style="font-size: 0.875rem; color: #6b7280; margin: 0;">Registered Patients</p>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="search-filters">
                    <input type="text" class="search-input" placeholder="Search by name or phone..." id="searchInput">
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="waiting">Waiting</option>
                        <option value="in_procedure">In Procedure</option>
                        <option value="completed">Completed</option>
                        <option value="scheduled">Scheduled</option>
                    </select>
                </div>

                <!-- Patients Table -->
                <div class="table-container" style="height: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>First Name</th>
                                <th>Middle Name</th>
                                <th>Last Name</th>
                                <th>Contact</th>
                                <th>Services</th>
                                <th>Dentist</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="patientsTableBody">
                            <?php if (empty($patients)): ?>
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 60px; color: #6b7280;">
                                        No patients found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($patients as $patient): ?>
                                    <?php 
                                    // Format time like staff queue
                                    $queueTime = $patient['queue_time'] ?? '';
                                    $time12hr = '';
                                    $time24hr = '';
                                    if (!empty($queueTime)) {
                                        $timeObj = new DateTime($queueTime);
                                        $time12hr = $timeObj->format('g:i A');
                                        $time24hr = $timeObj->format('H:i');
                                    }
                                    ?>
                                    <tr class="patient-row" 
                                        data-name="<?php echo strtolower(htmlspecialchars($patient['first_name'] ?? 'Unknown')); ?>"
                                        data-phone="<?php echo strtolower(htmlspecialchars($patient['phone'] ?? '')); ?>">
                                        <td>
                                            <div class="patient-name"><?php echo htmlspecialchars($patient['first_name'] ?? 'Unknown'); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($patient['middle_name'] ?? ''); ?></td>
                                        <td>
                                            <div class="patient-name"><?php echo htmlspecialchars($patient['last_name'] ?? 'Unknown'); ?></div>
                                        </td>
                                        <td>
                                            <div class="patient-contact">
                                                <div><?php echo htmlspecialchars($patient['phone'] ?: 'N/A'); ?></div>
                                                <div><?php echo htmlspecialchars($patient['email'] ?? ''); ?></div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($patient['treatment_type'] ?? 'General Checkup'); ?></td>
                                        <td>Dr. Rex</td>
                                        <td>
                                            <div class="time-display">
                                                <span class="time-12hr"><?php echo $time12hr; ?></span>
                                                <span class="time-24hr"><?php echo $time24hr; ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                            $status = $patient['queue_status'];
                                            if ($status === 'waiting') {
                                                echo '<span class="status-badge waiting">Waiting</span>';
                                            } elseif ($status === 'in_procedure') {
                                                echo '<span class="status-badge in-procedure">In Procedure</span>';
                                            } elseif ($status === 'completed') {
                                                echo '<span class="status-badge completed">Completed</span>';
                                            } elseif ($status === 'on_hold') {
                                                echo '<span class="status-badge on-hold">On Hold</span>';
                                            } elseif ($status === 'scheduled') {
                                                echo '<span class="status-badge scheduled">Scheduled</span>';
                                            } else {
                                                echo '<span class="status-badge">Not in Queue</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="patient-kebab-menu">
                                                <button class="patient-kebab-btn" data-patient-id="<?php echo $patient['id']; ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                                        <circle cx="12" cy="6" r="2"/>
                                                        <circle cx="12" cy="12" r="2"/>
                                                        <circle cx="12" cy="18" r="2"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination - Below table -->
                <?php if ($totalPatients > 0): ?>
                    <div class="pagination">
                        <span class="pagination-info">Showing <?php echo $showingStart; ?>-<?php echo $showingEnd; ?> of <?php echo $totalPatients; ?> patients</span>
                        <div class="pagination-buttons">
            <?php if ($currentPage > 1): ?>
                <a href="?page=<?php echo $currentPage - 1; ?>" class="pagination-btn">Previous</a>
            <?php else: ?>
                <button class="pagination-btn" disabled>Previous</button>
            <?php endif; ?>
            
            <?php
            // Smart page number display
            $maxVisiblePages = 5;
            $startPage = max(1, $currentPage - floor($maxVisiblePages / 2));
            $endPage = min($totalPages, $startPage + $maxVisiblePages - 1);
            
            if ($endPage - $startPage + 1 < $maxVisiblePages) {
                $startPage = max(1, $endPage - $maxVisiblePages + 1);
            }
            
            if ($startPage > 1) {
                ?>
                <a href="?page=1" class="pagination-btn">1</a>
                <?php
                if ($startPage > 2) {
                    ?>
                    <span class="pagination-ellipsis">...</span>
                    <?php
                }
            }
            
            for ($i = $startPage; $i <= $endPage; $i++) {
                if ($i == $currentPage) {
                    ?>
                    <button class="pagination-btn active"><?php echo $i; ?></button>
                    <?php
                } else {
                    ?>
                    <a href="?page=<?php echo $i; ?>" class="pagination-btn"><?php echo $i; ?></a>
                    <?php
                }
            }
            
            if ($endPage < $totalPages) {
                if ($endPage < $totalPages - 1) {
                    ?>
                    <span class="pagination-ellipsis">...</span>
                    <?php
                }
                ?>
                <a href="?page=<?php echo $totalPages; ?>" class="pagination-btn"><?php echo $totalPages; ?></a>
                <?php
            }
            ?>
            
                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?php echo $currentPage + 1; ?>" class="pagination-btn">Next</a>
                <?php else: ?>
                    <button class="pagination-btn" disabled>Next</button>
                <?php endif; ?>
                    </div>
                    </div>
                <?php endif; ?>
            </div>

<script>
const patients = <?php echo json_encode($patients); ?>;

document.getElementById('searchInput').addEventListener('input', filterPatients);
document.getElementById('statusFilter').addEventListener('change', filterPatients);

function filterPatients() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    
    document.querySelectorAll('.patient-row').forEach(row => {
        const name = (row.dataset.name || '').toLowerCase();
        const phone = (row.dataset.phone || '').toLowerCase();
        const patientId = row.querySelector('.patient-kebab-btn')?.dataset.patientId;
        
        // Get patient object from our data
        const patient = patients.find(p => p.id == patientId);
        const queueStatus = patient?.queue_status || '';
        
        const matchSearch = !search || name.includes(search) || phone.includes(search);
        const matchStatus = !statusFilter || queueStatus === statusFilter || statusFilter === '';
        
        row.style.display = (matchSearch && matchStatus) ? '' : 'none';
    });
}

// Patient Kebab Menu Functions
let patientKebabDropdown = null;
let patientKebabBackdrop = null;
let patientActiveButton = null;

function createPatientKebabDropdown() {
    patientKebabDropdown = document.createElement('div');
    patientKebabDropdown.className = 'patient-kebab-dropdown-portal';
    patientKebabDropdown.id = 'patientKebabDropdownPortal';
    document.body.appendChild(patientKebabDropdown);

    patientKebabBackdrop = document.createElement('div');
    patientKebabBackdrop.className = 'patient-kebab-backdrop';
    patientKebabBackdrop.id = 'patientKebabBackdrop';
    document.body.appendChild(patientKebabBackdrop);

    patientKebabBackdrop.addEventListener('click', closePatientKebabDropdown);
}

function getPatientMenuItems(patientId) {
    return `
        <a href="javascript:void(0)" data-action="view" data-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            View
        </a>
        <a href="quick_session.php?patient_id=${patientId}" data-action="session" data-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            New Session
        </a>
        <a href="javascript:void(0)" data-action="appointment" data-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            Add Appointment
        </a>
        <a href="javascript:void(0)" data-action="delete" data-id="${patientId}" style="color: #dc2626;">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20.54 5.23l-1.39-1.68C18.88 3.21 18.47 3 18 3H6c-.47 0-.88.21-1.16.55L3.46 5.23C3.17 5.57 3 6.02 3 6.5V19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6.5c0-.48-.17-.93-.46-1.27zM12 17.5L6.5 12H10v-2h4v2h3.5L12 17.5z"/>
            </svg>
            Archive
        </a>
    `;
}

function positionPatientKebabDropdown(button) {
    if (!patientKebabDropdown || !button) return;

    const rect = button.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    const padding = 15;
    const dropdownWidth = 200;
    
    let left = rect.right + 5;
    let top = rect.bottom + 8;

    if (left + dropdownWidth > viewportWidth - padding) {
        left = rect.left - dropdownWidth - 5;
    }
    
    if (left < padding) {
        left = padding;
    }
    
    if (top + 150 > viewportHeight - padding) {
        top = rect.top - 150 - 8;
    }
    
    if (top < padding) {
        top = padding;
    }

    patientKebabDropdown.style.left = left + 'px';
    patientKebabDropdown.style.top = top + 'px';
}

function openPatientKebabDropdown(button) {
    if (!patientKebabDropdown) {
        createPatientKebabDropdown();
    }

    const patientId = button.dataset.patientId;

    patientKebabDropdown.innerHTML = getPatientMenuItems(patientId);
    positionPatientKebabDropdown(button);

    patientKebabDropdown.classList.add('show');
    patientKebabBackdrop.classList.add('show');
    patientActiveButton = button;
    button.classList.add('active');

    patientKebabDropdown.addEventListener('click', handlePatientKebabClick);
}

function closePatientKebabDropdown() {
    if (patientKebabDropdown) {
        patientKebabDropdown.classList.remove('show');
        patientKebabDropdown.innerHTML = '';
    }
    if (patientKebabBackdrop) {
        patientKebabBackdrop.classList.remove('show');
    }
    if (patientActiveButton) {
        patientActiveButton.classList.remove('active');
        patientActiveButton = null;
    }
}

function handlePatientKebabClick(e) {
    const link = e.target.closest('a[data-action]');
    if (!link) return;

    e.preventDefault();
    e.stopPropagation();

    const action = link.dataset.action;
    const id = parseInt(link.dataset.id);

    closePatientKebabDropdown();

switch(action) {
        case 'view':
            viewPatientDetails(id);
            break;
        case 'session':
            window.location.href = 'quick_session.php?patient_id=' + id;
            break;
        case 'appointment':
            openAddAppointmentModal(id);
            break;
        case 'delete':
            deletePatient(id);
            break;
    }
}

document.addEventListener('click', function(e) {
    const button = e.target.closest('.patient-kebab-btn');
    if (button) {
        e.preventDefault();
        e.stopPropagation();

        if (patientActiveButton === button && patientKebabDropdown && patientKebabDropdown.classList.contains('show')) {
            closePatientKebabDropdown();
        } else {
            if (patientActiveButton) {
                patientActiveButton.classList.remove('active');
            }
            openPatientKebabDropdown(button);
        }
        return;
    }

    if (!e.target.closest('.patient-kebab-dropdown-portal')) {
        closePatientKebabDropdown();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (patientKebabDropdown && patientKebabDropdown.classList.contains('show')) {
            closePatientKebabDropdown();
        }
    }
});

window.addEventListener('resize', function() {
    if (patientKebabDropdown && patientKebabDropdown.classList.contains('show') && patientActiveButton) {
        positionPatientKebabDropdown(patientActiveButton);
    }
});

// View Patient Details
function viewPatientDetails(patientId) {
    const patient = patients.find(p => p.id == patientId);
    if (!patient) return;
    
    const fullName = `${patient.first_name || ''} ${patient.middle_name || ''} ${patient.last_name || ''} ${patient.suffix || ''}`.trim();
    alert('Patient: ' + (fullName || 'Unknown') + '\nPhone: ' + (patient.phone || 'N/A') + '\nEmail: ' + (patient.email || 'N/A'));
}

// Archive Patient (Soft Delete)
function deletePatient(patientId) {
    const patient = patients.find(p => p.id == patientId);
    if (!patient) return;
    
    const fullName = `${patient.first_name || ''} ${patient.middle_name || ''} ${patient.last_name || ''} ${patient.suffix || ''}`.trim();
    if (confirm(`Are you sure you want to archive this patient?\n\nPatient: ${fullName || 'Unknown'}\nPhone: ${patient.phone || 'N/A'}\n\nYou can restore archived patients from the Archive page.`)) {
        fetch('patient_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=archive&patient_id=' + patientId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Patient archived successfully');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to archive patient');
        });
    }
}

// Add Appointment Modal Functions
let selectedPatientId = null;

function openAddAppointmentModal(patientId) {
    const patient = patients.find(p => p.id == patientId);
    
    if (patient) {
        selectedPatientId = patientId;
        
        const fullName = (patient.first_name || '') + ' ' + (patient.middle_name || '' + ' ') + (patient.last_name || '');
        
        document.getElementById('appointmentPatientName').textContent = fullName.trim();
        document.getElementById('appointmentPatientId').value = patientId;
        document.getElementById('appointmentPatientPhone').value = patient.phone || '';
        
        document.getElementById('appointmentModal').classList.add('active');
    } else {
        alert('Patient not found');
    }
}

function closeAddAppointmentModal() {
    document.getElementById('appointmentModal').classList.remove('active');
}

document.getElementById('appointmentModal').addEventListener('click', function(e) {
    if (e.target === this || e.target.classList.contains('modal-backdrop') || e.target.closest('.modal-container') === e.target) {
        closeAddAppointmentModal();
    }
});

document.getElementById('addAppointmentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('process_patient_appointment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Appointment scheduled successfully!');
            closeAddAppointmentModal();
            location.reload();
        } else {
            alert(data.message || 'Error scheduling appointment');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error scheduling appointment');
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (document.getElementById('appointmentModal').classList.contains('active')) {
            closeAddAppointmentModal();
        }
    }
});
</script>

<style>
/* Table Column Alignment */
.data-table th,
.data-table td {
    vertical-align: middle;
    text-align: left;
}
.data-table th:first-child,
.data-table td:first-child {
    text-align: left;
}
.data-table th:nth-child(5),
.data-table td:nth-child(5),
.data-table th:nth-child(6),
.data-table td:nth-child(6),
.data-table th:nth-child(7),
.data-table td:nth-child(7),
.data-table th:nth-child(8),
.data-table td:nth-child(8),
.data-table th:nth-child(9),
.data-table td:nth-child(9) {
    text-align: center;
}
.data-table th:last-child,
.data-table td:last-child {
    text-align: center;
    width: 60px;
}

/* Patient Contact */
.patient-contact {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

/* Status Badge Styles */
.status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}

.status-badge.waiting {
    background: #fef3c7;
    color: #92400e;
}

.status-badge.in-procedure {
    background: #dbeafe;
    color: #1e40af;
}

.status-badge.completed {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.on-hold {
    background: #fef3c7;
    color: #92400e;
}

.status-badge.scheduled {
    background: #e0e7ff;
    color: #3730a3;
}

/* Patient Cell Styles */
.patient-info-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.patient-avatar {
    width: 40px;
    height: 40px;
    background: #e5e7eb;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6b7280;
    font-weight: 600;
}

.patient-name {
    font-weight: 500;
    font-size: 0.9rem;
}

.patient-age-gender {
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: 2px;
}

.patient-contact {
    font-size: 0.85rem;
    color: #6b7280;
}

/* Time Display */
.time-display {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
}

.time-12hr {
    font-weight: 500;
    font-size: 0.875rem;
    color: #374151;
}

.time-24hr {
    font-size: 0.75rem;
    color: #9ca3af;
}

/* Pagination Styles */
.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 32px;
    padding: 24px;
    background: white;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.pagination-info {
    font-size: 0.875rem;
    color: #6b7280;
}

.pagination-buttons {
    display: flex;
    gap: 8px;
    align-items: center;
}

.pagination-btn {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    background: white;
    color: #374151;
    border-radius: 6px;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    min-width: 40px;
    text-align: center;
}

.pagination-btn:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

.pagination-btn.active {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
}



.pagination-btn.disabled {
    background: #f9fafb;
    color: #9ca3af;
    cursor: not-allowed;
    opacity: 0.5;
}

.pagination-ellipsis {
    padding: 8px 4px;
    color: #6b7280;
    font-weight: 500;
}

/* Patient Kebab Menu Styles */
.patient-kebab-menu {
    position: relative;
    display: inline-block;
}

.patient-kebab-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    border-radius: 50%;
    color: #6b7280;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.patient-kebab-btn:hover {
    background-color: #f3f4f6;
    color: #374151;
}

.patient-kebab-btn.active {
    background-color: #e5e7eb;
    color: #111827;
}

.patient-kebab-dropdown-portal {
    display: none;
    position: fixed;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    min-width: 200px;
    max-width: 220px;
    width: auto;
    z-index: 99999;
    overflow: hidden;
}

.patient-kebab-dropdown-portal.show {
    display: block;
    animation: patientKebabFadeIn 0.15s ease;
}

@keyframes patientKebabFadeIn {
    from { opacity: 0; transform: scale(0.95) translateY(-4px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.patient-kebab-dropdown-portal a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    color: #374151;
    text-decoration: none;
    font-size: 0.875rem;
    transition: all 0.15s ease;
    cursor: pointer;
    white-space: nowrap;
}

.patient-kebab-dropdown-portal a:hover {
    background-color: #f9fafb;
    color: #111827;
}

.patient-kebab-dropdown-portal a svg {
    flex-shrink: 0;
}

.patient-kebab-dropdown-portal a:first-child {
    border-radius: 8px 8px 0 0;
}

.patient-kebab-dropdown-portal a:last-child {
    border-radius: 0 0 8px 8px;
}

.patient-kebab-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 99998;
}

.patient-kebab-backdrop.show {
    display: block;
}

/* Form Styles */
.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
    margin-bottom: 6px;
}

.form-group .form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-group .form-control:focus {
    border-color: #0ea5e9;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.btn-primary {
    padding: 10px 20px;
    background: #0ea5e9;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary:hover {
    background: #0284c7;
}

.btn-cancel {
    padding: 10px 20px;
    background: white;
    color: #374151;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-cancel:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

/* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: 99999;
}

.modal-overlay.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

.modal-container {
    position: relative;
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    padding: 20px;
}

.modal {
    background: white;
    border-radius: 16px;
    padding: 32px;
    max-width: 480px;
    width: 100%;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
}
</style>

<!-- Add Appointment Modal -->
<div id="appointmentModal" class="modal-overlay">
    <div class="modal-backdrop"></div>
    <div class="modal-container">
        <div class="modal">
            <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Schedule Appointment</h2>
            
            <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 14px 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #0369a1;">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <div>
                    <div style="font-size: 0.75rem; color: #0369a1; font-weight: 600;">PATIENT</div>
                    <div id="appointmentPatientName" style="font-weight: 600; color: #0c4a6e;"></div>
                </div>
            </div>
            
            <form id="addAppointmentForm">
                <input type="hidden" id="appointmentPatientId" name="patient_id">
                <input type="hidden" id="appointmentPatientPhone" name="patient_phone">
                
                <div style="display: flex; gap: 16px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Date *</label>
                        <input type="date" name="appointment_date" required class="form-control" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Time *</label>
                        <input type="time" name="appointment_time" required class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Treatment</label>
                    <select name="treatment" class="form-control">
                        <option value="General Checkup">General Checkup</option>
                        <option value="Teeth Cleaning">Teeth Cleaning</option>
                        <option value="Root Canal">Root Canal</option>
                        <option value="Tooth Extraction">Tooth Extraction</option>
                        <option value="Dental Fillings">Dental Fillings</option>
                        <option value="Braces Adjustment">Braces Adjustment</option>
                        <option value="Denture Fitting">Denture Fitting</option>
                        <option value="Oral Prophylaxis">Oral Prophylaxis</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3" class="form-control" placeholder="Additional notes or instructions..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" onclick="closeAddAppointmentModal()" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-primary">Schedule Appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/admin_layout_end.php';
?>
