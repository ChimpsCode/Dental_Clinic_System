<?php
/**
 * Patient Records - Admin page for viewing all patient records
 */

$pageTitle = 'Patient Records';

try {
    require_once __DIR__ . '/../../config/database.php';
    
    // Pagination
    $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $patientsPerPage = 7;
    $offset = ($currentPage - 1) * $patientsPerPage;
    
    // WHERE clause - only active patients
    $whereClause = "WHERE p.is_archived = 0";
    
    // Get total count for pagination
    $stmt = $pdo->query("SELECT COUNT(*) FROM patients p $whereClause");
    $totalPatients = $stmt->fetchColumn();
    $totalPages = ceil($totalPatients / $patientsPerPage);
    
    // Get patients with appointment status and pagination
    $whereClause = "WHERE p.is_archived = 0";
    
    $stmt = $pdo->prepare("
        SELECT p.*, 
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
               a.status_id as appointment_status_id,
               a.status_name as appointment_status,
               a.appointment_date,
               a.appointment_time,
               svc.name as service_name
        FROM patients p 
        LEFT JOIN (
            SELECT a1.*, s.status_name
            FROM appointments a1
            LEFT JOIN appointment_statuses s ON a1.status_id = s.id
            WHERE a1.id = (
                SELECT MAX(a2.id) FROM appointments a2 WHERE a2.patient_id = a1.patient_id
            )
        ) a ON p.id = a.patient_id
        LEFT JOIN services svc ON a.service_id = svc.id
        $whereClause
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $patientsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate stats from ALL patients
    // Get patients with completed appointments
    $completedStmt = $pdo->query("
        SELECT COUNT(DISTINCT p.id) FROM patients p
        INNER JOIN appointments a ON p.id = a.patient_id
        WHERE p.is_archived = 0 AND a.status_id = 3
    ");
    $completedCount = $completedStmt->fetchColumn();
    
    // Get patients with pending appointments (status 1 = Pending, 2 = Confirmed)
    $pendingStmt = $pdo->query("
        SELECT COUNT(DISTINCT p.id) FROM patients p
        INNER JOIN appointments a ON p.id = a.patient_id
        WHERE p.is_archived = 0 AND a.status_id IN (1, 2) AND a.appointment_date >= CURDATE()
    ");
    $pendingCount = $pendingStmt->fetchColumn();
    
    // Stats for new patients this month
    $newThisMonthStmt = $pdo->query("
        SELECT COUNT(*) FROM patients 
        WHERE is_archived = 0 AND created_at > DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $newThisMonth = $newThisMonthStmt->fetchColumn();
    
    // For backward compatibility
    $inQueue = $pendingCount;
    $scheduledCount = $completedCount;
    
    // Pagination calculations
    $showingStart = $totalPatients > 0 ? $offset + 1 : 0;
    $showingEnd = min($offset + $patientsPerPage, $totalPatients);
    
} catch (Exception $e) {
    $patients = [];
    $totalPatients = 0;
    $inQueue = 0;
    $scheduledCount = 0;
    $completedCount = 0;
    $pendingCount = 0;
    $newThisMonth = 0;
    $currentPage = 1;
    $totalPages = 0;
    $patientsPerPage = 7;
    $showingStart = 0;
    $showingEnd = 0;
}

require_once __DIR__ . '/../../includes/admin_layout_start.php';
?>
            <div class="content-main">
                <!-- Page Header -->
                
                <!-- Summary Stats Cards -->
                <div class="summary-cards" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 20px; margin-bottom: 0px;">
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
                        <option value="1">Pending</option>
                        <option value="2">Confirmed</option>
                        <option value="3">Completed</option>
                        <option value="4">Cancelled</option>
                        <option value="5">No Show</option>
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
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="patientsTableBody">
                            <?php if (empty($patients)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 60px; color: #6b7280;">
                                        No patients found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($patients as $patient): ?>
                                    <?php 
                                    // Format appointment time
                                    $appointmentTime = $patient['appointment_time'] ?? '';
                                    $time12hr = '';
                                    $time24hr = '';
                                    if (!empty($appointmentTime)) {
                                        $timeObj = new DateTime($appointmentTime);
                                        $time12hr = $timeObj->format('g:i A');
                                        $time24hr = $timeObj->format('H:i');
                                    }
                                    // Format appointment date
                                    $appointmentDate = $patient['appointment_date'] ?? '';
                                    $dateDisplay = '';
                                    if (!empty($appointmentDate)) {
                                        $dateObj = new DateTime($appointmentDate);
                                        $dateDisplay = $dateObj->format('M d, Y');
                                    }
                                    ?>
                                    <tr class="patient-row" 
                                        data-name="<?php echo strtolower(htmlspecialchars($patient['first_name'] ?? 'Unknown')); ?>"
                                        data-phone="<?php echo strtolower(htmlspecialchars($patient['phone'] ?? '')); ?>">
                                        <td>
                                            <?php echo htmlspecialchars($patient['first_name'] ?? ''); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($patient['middle_name'] ?? ''); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($patient['last_name'] ?? ''); ?>
                                        </td>
                                        <td>
                                            <div class="patient-contact">
                                                <div><?php echo htmlspecialchars($patient['phone'] ?: 'N/A'); ?></div>
                                                <div><?php echo htmlspecialchars($patient['email'] ?? ''); ?></div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($patient['service_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php 
                                            $statusId = (int)($patient['appointment_status_id'] ?? 0);
                                            $statusLabels = [
                                                0 => '--',
                                                1 => 'Scheduled',
                                                2 => 'In Queue',
                                                3 => 'Unpaid',
                                                4 => 'Done',
                                                5 => 'Cancelled'
                                            ];
                                            $statusColors = [
                                                0 => '#f3f4f6:#6b7280',
                                                1 => '#dbeafe:#1d4ed8',
                                                2 => '#fef3c7:#92400e',
                                                3 => '#fed7aa:#c2410c',
                                                4 => '#d1fae5:#065f46',
                                                5 => '#fee2e2:#dc2626'
                                            ];
                                            $statusLabel = $statusLabels[$statusId] ?? '--';
                                            $colors = $statusColors[$statusId] ?? '#f3f4f6:#6b7280';
                                            list($bg, $text) = explode(':', $colors);
                                            ?>
                                            <span class="status-badge" style="background: <?php echo $bg; ?>; color: <?php echo $text; ?>;">
                                                <?php echo $statusLabel; ?>
                                            </span>
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
        const appointmentStatusId = patient?.appointment_status_id || 0;
        
        const matchSearch = !search || name.includes(search) || phone.includes(search);
        const matchStatus = !statusFilter || String(appointmentStatusId) === statusFilter || statusFilter === '';
        
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
        <a href="javascript:void(0)" data-action="edit" data-id="${patientId}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
            Edit Info
        </a>
        <a href="../shared/quick_session.php?patient_id=${patientId}" data-action="session" data-id="${patientId}">
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
        case 'edit':
            editPatientInfo(id);
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

// View Patient Details - Modal
function viewPatientDetails(patientId) {
    const patient = patients.find(p => p.id == patientId);
    if (!patient) return;
    
    const fullName = `${patient.first_name || ''} ${patient.middle_name || ''} ${patient.last_name || ''} ${patient.suffix || ''}`.trim();
    const membershipStatus = patient.email ? 'Registered' : 'Guest';
    const membershipBadge = patient.email ? 
        '<span style="background: #d1fae5; color: #065f46; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">✓ Web Account</span>' :
        '<span style="background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">Guest</span>';
    
    // Remove existing modal
    const existingModal = document.getElementById('view-patient-modal');
    if (existingModal) existingModal.remove();
    
    const overlay = document.createElement('div');
    overlay.id = 'view-patient-modal';
    overlay.className = 'modal-overlay active';
    
    overlay.innerHTML = `
        <div class="modal" style="max-width: 500px; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e5e7eb; padding-bottom: 16px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 50px; height: 50px; background: #0ea5e9; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; font-weight: 700;">
                        ${(patient.first_name || 'P').charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <h3 style="margin: 0; color: #111827; font-size: 18px;">${fullName || 'Unknown Patient'}</h3>
                        <p style="margin: 4px 0 0 0; color: #6b7280; font-size: 13px;">Patient ID: ${patient.id}</p>
                    </div>
                </div>
                <button onclick="closeViewPatientModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280;">&times;</button>
            </div>
            
            <div style="display: flex; gap: 8px; margin-bottom: 20px;">
                ${membershipBadge}
            </div>
            
            <!-- Personal Info -->
            <div style="background: #f9fafb; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <h4 style="margin: 0 0 12px 0; color: #374151; font-size: 14px; font-weight: 600;">👤 Personal Information</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <p style="margin: 0; color: #6b7280; font-size: 12px;">First Name</p>
                        <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${patient.first_name || '-'}</p>
                    </div>
                    <div>
                        <p style="margin: 0; color: #6b7280; font-size: 12px;">Middle Name</p>
                        <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${patient.middle_name || '-'}</p>
                    </div>
                    <div>
                        <p style="margin: 0; color: #6b7280; font-size: 12px;">Last Name</p>
                        <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${patient.last_name || '-'}</p>
                    </div>
                    <div>
                        <p style="margin: 0; color: #6b7280; font-size: 12px;">Suffix</p>
                        <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${patient.suffix || '-'}</p>
                    </div>
                </div>
            </div>
            
            <!-- Contact Info -->
            <div style="background: #f9fafb; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <h4 style="margin: 0 0 12px 0; color: #374151; font-size: 14px; font-weight: 600;">📞 Contact Information</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <p style="margin: 0; color: #6b7280; font-size: 12px;">Phone</p>
                        <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${patient.phone || '-'}</p>
                    </div>
                    <div>
                        <p style="margin: 0; color: #6b7280; font-size: 12px;">Email</p>
                        <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${patient.email || '-'}</p>
                    </div>
                </div>
            </div>
            
            <!-- Additional Info -->
            <div style="background: #f9fafb; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <h4 style="margin: 0 0 12px 0; color: #374151; font-size: 14px; font-weight: 600;">📋 Additional Information</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <p style="margin: 0; color: #6b7280; font-size: 12px;">Date of Birth</p>
                        <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${patient.date_of_birth || '-'}</p>
                    </div>
                    <div>
                        <p style="margin: 0; color: #6b7280; font-size: 12px;">Gender</p>
                        <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${patient.gender || '-'}</p>
                    </div>
                    <div>
                        <p style="margin: 0; color: #6b7280; font-size: 12px;">Registered Date</p>
                        <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${patient.created_at ? new Date(patient.created_at).toLocaleDateString() : '-'}</p>
                    </div>
                    <div>
                        <p style="margin: 0; color: #6b7280; font-size: 12px;">Address ID</p>
                        <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${patient.address_id || '-'}</p>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div style="display: flex; gap: 12px; justify-content: flex-end; border-top: 1px solid #e5e7eb; padding-top: 16px;">
                <button onclick="closeViewPatientModal()" 
                    style="padding: 10px 20px; background: #0ea5e9; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px;">
                    Close
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(overlay);
    document.body.classList.add('modal-open');
}

function closeViewPatientModal() {
    const modal = document.getElementById('view-patient-modal');
    if (modal) modal.remove();
    document.body.classList.remove('modal-open');
}

// Edit Patient Info - Modal
function editPatientInfo(patientId) {
    const patient = patients.find(p => p.id == patientId);
    if (!patient) return;
    
    const fullName = `${patient.first_name || ''} ${patient.middle_name || ''} ${patient.last_name || ''} ${patient.suffix || ''}`.trim();
    
    // Remove existing modal
    const existingModal = document.getElementById('edit-patient-modal');
    if (existingModal) existingModal.remove();
    
    const overlay = document.createElement('div');
    overlay.id = 'edit-patient-modal';
    overlay.className = 'modal-overlay active';
    
    overlay.innerHTML = `
        <div class="modal" style="max-width: 550px; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e5e7eb; padding-bottom: 16px;">
                <h3 style="margin: 0; color: #111827; font-size: 18px;">✏️ Edit Patient Information</h3>
                <button onclick="closeEditPatientModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280;">&times;</button>
            </div>
            
            <form id="edit-patient-form">
                <input type="hidden" name="patient_id" value="${patient.id}">
                
                <!-- Name Section -->
                <div style="margin-bottom: 20px;">
                    <h4 style="margin: 0 0 12px 0; color: #374151; font-size: 14px; font-weight: 600;">Personal Information</h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 4px;">First Name *</label>
                            <input type="text" name="first_name" value="${patient.first_name || ''}" required
                                style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 4px;">Middle Name</label>
                            <input type="text" name="middle_name" value="${patient.middle_name || ''}"
                                style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 4px;">Last Name *</label>
                            <input type="text" name="last_name" value="${patient.last_name || ''}" required
                                style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 4px;">Suffix</label>
                            <input type="text" name="suffix" value="${patient.suffix || ''}" placeholder="Jr., Sr., III, etc."
                                style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                        </div>
                    </div>
                </div>
                
                <!-- Contact Section -->
                <div style="margin-bottom: 20px;">
                    <h4 style="margin: 0 0 12px 0; color: #374151; font-size: 14px; font-weight: 600;">Contact Information</h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 4px;">Phone</label>
                            <input type="text" name="phone" value="${patient.phone || ''}" placeholder="09XXXXXXXXX"
                                style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 4px;">Email</label>
                            <input type="email" name="email" value="${patient.email || ''}" placeholder="email@example.com"
                                style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                        </div>
                    </div>
                </div>
                
                <!-- Additional Info -->
                <div style="margin-bottom: 20px;">
                    <h4 style="margin: 0 0 12px 0; color: #374151; font-size: 14px; font-weight: 600;">Additional Information</h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div>
                            <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 4px;">Date of Birth</label>
                            <input type="date" name="date_of_birth" value="${patient.date_of_birth || ''}"
                                style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 4px;">Gender</label>
                            <select name="gender" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                                <option value="">-- Select --</option>
                                <option value="Male" ${patient.gender === 'Male' ? 'selected' : ''}>Male</option>
                                <option value="Female" ${patient.gender === 'Female' ? 'selected' : ''}>Female</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Buttons -->
                <div style="display: flex; gap: 12px; justify-content: flex-end; border-top: 1px solid #e5e7eb; padding-top: 16px; margin-top: 8px;">
                    <button type="button" onclick="closeEditPatientModal()" 
                        style="padding: 10px 20px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer; font-size: 14px;">
                        Cancel
                    </button>
                    <button type="submit" 
                        style="padding: 10px 20px; background: #0ea5e9; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px;">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(overlay);
    document.body.classList.add('modal-open');
    
    // Handle form submit
    document.getElementById('edit-patient-form').addEventListener('submit', function(e) {
        e.preventDefault();
        savePatientChanges();
    });
}

function closeEditPatientModal() {
    const modal = document.getElementById('edit-patient-modal');
    if (modal) modal.remove();
    document.body.classList.remove('modal-open');
}

function savePatientChanges() {
    const form = document.getElementById('edit-patient-form');
    const formData = new FormData(form);
    
    fetch('../../master/shared/update_patient_info.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Patient information updated successfully!');
            closeEditPatientModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Failed to save changes');
    });
}

// Archive Patient (Soft Delete) - with modal confirmation
function deletePatient(patientId) {
    const patient = patients.find(p => p.id == patientId);
    if (!patient) return;
    
    const fullName = `${patient.first_name || ''} ${patient.middle_name || ''} ${patient.last_name || ''} ${patient.suffix || ''}`.trim();
    
    // Create modal
    const existingModal = document.getElementById('archive-patient-modal');
    if (existingModal) existingModal.remove();
    
    const overlay = document.createElement('div');
    overlay.id = 'archive-patient-modal';
    overlay.className = 'modal-overlay active';
    overlay.innerHTML = `
        <div class="modal" style="max-width: 400px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #92400e; font-size: 18px;">📦 Archive Patient</h3>
                <button onclick="closeArchivePatientModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280;">&times;</button>
            </div>
            <div style="background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <p style="color: #92400e; font-size: 14px; margin: 0; font-weight: 600;">
                    Archive this patient?
                </p>
                <p style="color: #b45309; font-size: 13px; margin: 8px 0 0 0;">
                    Patient: <strong>${fullName || 'Unknown'}</strong><br>
                    Phone: ${patient.phone || 'N/A'}
                </p>
                <p style="color: #a16207; font-size: 13px; margin: 8px 0 0 0;">
                    You can restore archived patients from the Archive page.
                </p>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button onclick="closeArchivePatientModal()" 
                    style="padding: 10px 20px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer; font-size: 14px;">
                    Cancel
                </button>
                <button onclick="confirmArchivePatient(${patientId})" 
                    style="padding: 10px 20px; background: #92400e; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px;">
                    Archive
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);
    document.body.classList.add('modal-open');
}

function closeArchivePatientModal() {
    const modal = document.getElementById('archive-patient-modal');
    if (modal) modal.remove();
    document.body.classList.remove('modal-open');
}

function confirmArchivePatient(patientId) {
    closeArchivePatientModal();
    
    fetch('../../master/user-patient/patient_actions.php', {
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
    
    fetch('../../master/user-patient/process_patient_appointment.php', {
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
.data-table th:nth-child(2),
.data-table td:nth-child(2),
.data-table th:nth-child(3),
.data-table td:nth-child(3),
.data-table th:nth-child(4),
.data-table td:nth-child(4),
.data-table th:nth-child(5),
.data-table td:nth-child(5),
.data-table th:nth-child(6),
.data-table td:nth-child(6) {
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
    padding-top: 0px;
    padding-bottom: 20px;
    margin-top: 0px;
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
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px 16px;
    background-color: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    text-decoration: none;
    color: #4a5568;
    font-size: 14px;
    transition: all 0.2s ease;
    min-width: 32px;
    cursor: pointer;
}

.pagination-btn:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

.pagination-btn.active {
    background: #3f75ea;
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
    color: #a0aec0;
    padding: 0 4px;
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
require_once __DIR__ . '/../../includes/admin_layout_end.php';
?>
