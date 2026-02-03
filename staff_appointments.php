<?php
$pageTitle = 'Appointments';

// Pagination settings
$itemsPerPage = 5;
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;

try {
    require_once 'config/database.php';
    
    // Check if deleted_by_staff column exists
    $columnExists = false;
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'deleted_by_staff'");
        $columnExists = $checkCol->rowCount() > 0;
    } catch (Exception $e) {
        $columnExists = false;
    }
    
    // Build WHERE clause based on column existence
    $whereClause = $columnExists ? "WHERE (a.deleted_by_staff = 0 OR a.deleted_by_staff IS NULL)" : "";
    $countWhereClause = $columnExists ? "WHERE deleted_by_staff = 0 OR deleted_by_staff IS NULL" : "";
    
    // Get total count for pagination
    $countStmt = $pdo->query("SELECT COUNT(*) FROM appointments " . $countWhereClause);
    $totalAppointments = $countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalAppointments / $itemsPerPage));
    
    // Ensure current page is valid
    if ($currentPage > $totalPages) $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    // Calculate showing range
    $showingStart = $totalAppointments > 0 ? $offset + 1 : 0;
    $showingEnd = min($offset + $itemsPerPage, $totalAppointments);
    
    // Get all appointments for stats (without pagination)
    $allStmt = $pdo->query("SELECT a.*, 
                         CONCAT(a.first_name, ' ', IFNULL(a.middle_name, ''), ' ', a.last_name) as full_name, 
                         p.phone 
                         FROM appointments a 
                         LEFT JOIN patients p ON a.patient_id = p.id
                         " . $whereClause);
    $allAppointments = $allStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get paginated appointments
    $stmt = $pdo->prepare("SELECT a.*, 
                         CONCAT(a.first_name, ' ', IFNULL(a.middle_name, ''), ' ', a.last_name) as full_name, 
                         p.phone 
                         FROM appointments a 
                         LEFT JOIN patients p ON a.patient_id = p.id 
                         " . $whereClause . "
                         ORDER BY a.created_at DESC
                         LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $appointments = [];
    $allAppointments = [];
    $totalAppointments = 0;
    $totalPages = 1;
    $showingStart = 0;
    $showingEnd = 0;
}

$today = date('Y-m-d');
$todayCount = count(array_filter($allAppointments, function($a) use ($today) {
    return $a['appointment_date'] === $today;
}));
$completedCount = count(array_filter($allAppointments, function($a) {
    return strtolower($a['status'] ?? '') === 'completed';
}));
$cancelledCount = count(array_filter($allAppointments, function($a) {
    return strtolower($a['status'] ?? '') === 'cancelled';
}));

$inquiryData = null;
$showModal = false;
if (isset($_GET['inquiry_id']) && is_numeric($_GET['inquiry_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM inquiries WHERE id = ?");
        $stmt->execute([$_GET['inquiry_id']]);
        $inquiryData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($inquiryData) {
            $showModal = true;
        }
    } catch (Exception $e) {
        $inquiryData = null;
    }
}

require_once 'includes/staff_layout_start.php';
?>

<!-- Appointment Stats -->
<div class="summary-cards">
    <div class="summary-card">
        <div class="summary-icon blue" style="background: #e0f2fe; color: #0284c7;">📋</div>
        <div class="summary-info">
            <h3><?php echo $totalAppointments; ?></h3>
            <p>Total Appointments</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon yellow">⏰</div>
        <div class="summary-info">
            <h3><?php echo $todayCount; ?></h3>
            <p>Today</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon green">✓</div>
        <div class="summary-info">
            <h3><?php echo $completedCount; ?></h3>
            <p>Completed</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon red" style="background: #fee2e2; color: #dc2626;">⚠️</div>
        <div class="summary-info">
            <h3><?php echo $cancelledCount; ?></h3>
            <p>Cancelled</p>
        </div>
    </div>
</div>

<!-- Search & Filters -->
<div class="search-filters">
    <div class="filter-tabs">
        <span class="active" data-filter="all">All</span>
        <span data-filter="today">Today</span>
        <span data-filter="week">This Week</span>
        <span data-filter="month">This Month</span>
    </div>
    <input type="text" class="search-input" id="searchAppointment" placeholder="Search appointments...">
</div>

<?php if ($inquiryData): ?>
<div style="background: #dbeafe; border: 1px solid #3b82f6; border-radius: 8px; padding: 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" viewBox="0 0" fill="none" stroke="currentColor" stroke-width="2" stroke 24 24-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
    <span style="color: #1e40af; font-size: 0.9rem;">
        <strong>Forwarded from Inquiry:</strong> <?php echo htmlspecialchars(trim(($inquiryData['first_name'] ?? '') . ' ' . ($inquiryData['middle_name'] ?? '') . ' ' . ($inquiryData['last_name'] ?? ''))); ?> (<?php echo htmlspecialchars($inquiryData['source'] ?? ''); ?>)
    </span>
    <a href="staff_inquiries.php" style="margin-left: auto; color: #2563eb; font-size: 0.875rem; text-decoration: none;">View Original Inquiry</a>
</div>
<?php endif; ?>

<!-- Appointments Table -->
<div class="section-card">
    <div class="section-title">
        <span>Appointments List</span>
        <button class="btn-primary" onclick="openAppointmentModal()">+ New Appointment</button>
    </div>
    
    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Patient Name</th>
                    <th>Date & Time</th>
                    <th>Treatment</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="appointmentsTableBody">
                <?php if (empty($appointments)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 60px; color: #6b7280;">
                            <p style="font-size: 1.1rem; margin-bottom: 8px;">No appointments found</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($appointments as $appt): ?>
                        <tr class="appointment-row" 
                            data-name="<?php echo strtolower(htmlspecialchars($appt['full_name'] ?? 'Unknown')); ?>"
                            data-date="<?php echo htmlspecialchars($appt['appointment_date']); ?>"
                            data-status="<?php echo strtolower($appt['status'] ?? ''); ?>">
                            <td>
                                <div class="patient-name"><?php echo htmlspecialchars($appt['full_name'] ?? 'Unknown'); ?></div>
                                <div style="font-size: 0.85rem; color: #6b7280;">Phone: <?php echo htmlspecialchars($appt['phone'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <div><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></div>
                                <div style="font-size: 0.85rem; color: #6b7280;"><?php echo htmlspecialchars($appt['appointment_time'] ?? 'N/A'); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($appt['treatment'] ?? 'General'); ?></td>
                            <td>
                                <?php 
                                    $status = $appt['status'] ?? 'Pending';
                                    $statusClass = strtolower($status);
                                    $statusColors = [
                                        'completed' => 'dcfce7', '15803d',
                                        'cancelled' => 'fee2e2', 'dc2626',
                                        'upcoming' => 'e0f2fe', '0369a1',
                                        'pending' => 'fef3c7', '92400e'
                                    ];
                                    $bgColor = $statusColors[$statusClass . '_bg'] ?? 'fef3c7';
                                    $textColor = $statusColors[$statusClass . '_text'] ?? '92400e';
                                ?>
                                <span class="status-badge" style="background: #<?php echo $bgColor; ?>; color: #<?php echo $textColor; ?>;"><?php echo htmlspecialchars($status); ?></span>
                            </td>
                            <td>
                                <div class="appt-kebab-menu">
                                    <button class="appt-kebab-btn" data-appt-id="<?php echo $appt['id']; ?>">
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
</div>

<!-- Pagination -->
<div class="pagination">
    <span class="pagination-info">Showing <?php echo $showingStart; ?>-<?php echo $showingEnd; ?> of <?php echo $totalAppointments; ?> appointments</span>
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
        
        // Adjust start page if we're near the end
        if ($endPage - $startPage + 1 < $maxVisiblePages) {
            $startPage = max(1, $endPage - $maxVisiblePages + 1);
        }
        
        // Always show page 1
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
        
        // Always show last page
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

 <!-- View Appointment Modal -->
<div id="viewAppointmentModal" class="modal-overlay">
    <div class="modal" style="width: 500px;">
        <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Appointment Details</h2>
        <div id="viewAppointmentContent"></div>
        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px;">
            <button onclick="closeViewAppointmentModal()" class="btn-cancel">Close</button>
            <button onclick="editAppointmentFromView()" class="btn-primary">Edit</button>
        </div>
    </div>
</div>

<!-- New Appointment Modal -->
<div id="appointmentModal" class="modal-overlay">
    <div class="modal" style="width: 550px;">
        <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Schedule New Appointment</h2>
        <form id="appointmentForm">
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>First Name *</label>
                    <input type="text" name="first_name" required class="form-control" placeholder="Enter first name" value="<?php echo htmlspecialchars($inquiryData['first_name'] ?? ''); ?>">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" class="form-control" placeholder="Enter middle name" value="<?php echo htmlspecialchars($inquiryData['middle_name'] ?? ''); ?>">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" required class="form-control" placeholder="Enter last name" value="<?php echo htmlspecialchars($inquiryData['last_name'] ?? ''); ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="0912-345-6789" value="<?php echo htmlspecialchars($inquiryData['contact_info'] ?? ''); ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Appointment Date *</label>
                    <input type="date" name="appointment_date" required class="form-control">
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
                <button type="button" onclick="closeAppointmentModal()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-primary">Schedule Appointment</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Pagination Styles - Matching Admin Style */
.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    margin-top: 12px;
}

.pagination-info {
    color: #6b7280;
    font-size: 0.875rem;
}

.pagination-buttons {
    display: flex;
    gap: 8px;
    align-items: center;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
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

.pagination-btn:hover:not(.active):not(:disabled) {
    background-color: #f7fafc;
    border-color: #cbd5e0;
}

.pagination-btn.active {
    background-color: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
}

.pagination-btn:disabled {
    color: #a0aec0;
    background-color: #fff;
    cursor: not-allowed;
    border-color: #edf2f7;
}

.pagination-ellipsis {
    color: #a0aec0;
    padding: 0 4px;
}

/* Appointment Kebab Menu Styles - Portal Based */
.appt-kebab-menu {
    position: relative;
    display: inline-block;
}

.appt-kebab-btn {
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

.appt-kebab-btn:hover {
    background-color: #f3f4f6;
    color: #374151;
}

.appt-kebab-btn.active {
    background-color: #e5e7eb;
    color: #111827;
}

.appt-kebab-dropdown-portal {
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

.appt-kebab-dropdown-portal.show {
    display: block;
    animation: apptKebabFadeIn 0.15s ease;
}

@keyframes apptKebabFadeIn {
    from { opacity: 0; transform: scale(0.95) translateY(-4px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.appt-kebab-dropdown-portal a {
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

.appt-kebab-dropdown-portal a:hover {
    background-color: #f9fafb;
    color: #111827;
}

.appt-kebab-dropdown-portal a svg {
    flex-shrink: 0;
}

.appt-kebab-dropdown-portal a:first-child {
    border-radius: 8px 8px 0 0;
}

.appt-kebab-dropdown-portal a:last-child {
    border-radius: 0 0 8px 8px;
}

.appt-kebab-dropdown-portal a.danger {
    color: #dc2626;
}

.appt-kebab-dropdown-portal a.danger:hover {
    background-color: #fef2f2;
}

.appt-kebab-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 99998;
}

.appt-kebab-backdrop.show {
    display: block;
}

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
</style>

<script>
    const appointments = <?php echo json_encode($appointments); ?>;

    // Portal Pattern: Move modals to body level to escape stacking context
    // This ensures modals appear above sidebar and all other elements
    (function() {
        const viewAppointmentModal = document.getElementById('viewAppointmentModal');
        const appointmentModal = document.getElementById('appointmentModal');
        
        if (viewAppointmentModal) {
            document.body.appendChild(viewAppointmentModal);
        }
        if (appointmentModal) {
            document.body.appendChild(appointmentModal);
        }
    })();

    document.addEventListener('DOMContentLoaded', function() {
        // Filter tabs click handler
        document.querySelectorAll('.filter-tabs span').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.filter-tabs span').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                filterAppointments();
            });
        });

        // Search input handler
        document.getElementById('searchAppointment').addEventListener('input', filterAppointments);
        
        // Auto-open modal if forwarded from inquiry
        <?php if ($showModal): ?>
        openAppointmentModal();
        <?php endif; ?>
    });

    function filterAppointments() {
        const search = document.getElementById('searchAppointment').value.toLowerCase();
        const activeFilter = document.querySelector('.filter-tabs span.active').dataset.filter;
        const today = new Date().toISOString().split('T')[0];
        const weekEnd = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
        const monthEnd = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

        document.querySelectorAll('.appointment-row').forEach(row => {
            const nameMatch = !search || row.dataset.name.includes(search);
            
            let dateMatch = true;
            if (activeFilter === 'today') {
                dateMatch = row.dataset.date === today;
            } else if (activeFilter === 'week') {
                dateMatch = row.dataset.date >= today && row.dataset.date <= weekEnd;
            } else if (activeFilter === 'month') {
                dateMatch = row.dataset.date >= today && row.dataset.date <= monthEnd;
            }
            
            row.style.display = (nameMatch && dateMatch) ? '' : 'none';
        });
    }

    function openAppointmentModal() {
        document.getElementById('appointmentModal').style.display = 'flex';
    }

    function closeAppointmentModal() {
        document.getElementById('appointmentModal').style.display = 'none';
    }

    function closeViewAppointmentModal() {
        document.getElementById('viewAppointmentModal').style.display = 'none';
    }

    document.getElementById('appointmentModal').addEventListener('click', function(e) {
        if (e.target === this) closeAppointmentModal();
    });

    document.getElementById('viewAppointmentModal').addEventListener('click', function(e) {
        if (e.target === this) closeViewAppointmentModal();
    });

    document.getElementById('appointmentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('process_appointment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeAppointmentModal();
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

    function viewAppointment(id) {
        const appt = appointments.find(a => a.id == id);
        if (!appt) return;
        
        document.getElementById('viewAppointmentContent').innerHTML = `
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div><span style="color: #6b7280;">Patient:</span> <span style="font-weight: 600; margin-left: 8px;">${appt.full_name || 'Unknown'}</span></div>
                <div><span style="color: #6b7280;">Phone:</span> <span style="font-weight: 500; margin-left: 8px;">${appt.phone || 'N/A'}</span></div>
                <div><span style="color: #6b7280;">Date:</span> <span style="font-weight: 500; margin-left: 8px;">${new Date(appt.appointment_date).toLocaleDateString()}</span></div>
                <div><span style="color: #6b7280;">Time:</span> <span style="font-weight: 500; margin-left: 8px;">${appt.appointment_time || 'N/A'}</span></div>
                <div><span style="color: #6b7280;">Treatment:</span> <span style="font-weight: 500; margin-left: 8px;">${appt.treatment || 'General'}</span></div>
                <div><span style="color: #6b7280;">Status:</span> <span class="status-badge" style="margin-left: 8px; background: ${appt.status === 'Completed' ? '#dcfce7' : appt.status === 'Cancelled' ? '#fee2e2' : '#e0f2fe'}; color: ${appt.status === 'Completed' ? '#15803d' : appt.status === 'Cancelled' ? '#dc2626' : '#0369a1'};">${appt.status || 'Pending'}</span></div>
                ${appt.notes ? `<div><span style="color: #6b7280;">Notes:</span><p style="background: #f9fafb; padding: 12px; border-radius: 8px; margin: 8px 0 0;">${appt.notes}</p></div>` : ''}
            </div>
        `;
        
        document.getElementById('viewAppointmentModal').style.display = 'flex';
    }

    function editAppointmentFromView() {
        closeViewAppointmentModal();
        // Could open edit modal here
    }

    function cancelAppointment(id) {
        if (confirm('Are you sure you want to cancel this appointment?')) {
            fetch('cancel_appointment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Error cancelling appointment');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error cancelling appointment');
            });
        }
    }

    // Appointment Kebab Menu Functions - Portal Based
    let apptKebabDropdown = null;
    let apptKebabBackdrop = null;
    let apptActiveButton = null;

    function createApptKebabDropdown() {
        apptKebabDropdown = document.createElement('div');
        apptKebabDropdown.className = 'appt-kebab-dropdown-portal';
        apptKebabDropdown.id = 'apptKebabDropdownPortal';
        document.body.appendChild(apptKebabDropdown);

        apptKebabBackdrop = document.createElement('div');
        apptKebabBackdrop.className = 'appt-kebab-backdrop';
        apptKebabBackdrop.id = 'apptKebabBackdrop';
        document.body.appendChild(apptKebabBackdrop);

        apptKebabBackdrop.addEventListener('click', closeApptKebabDropdown);
    }

    function getApptMenuItems(apptId) {
        return `
            <a href="javascript:void(0)" data-action="view" data-id="${apptId}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                View
            </a>
            <a href="javascript:void(0)" data-action="edit" data-id="${apptId}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                Edit
            </a>
            <a href="javascript:void(0)" data-action="forward" data-id="${apptId}">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 12h-16"/>
                    <path d="M12 5l7 7-7 7"/>
                    <path d="M3 5v14"/>
                </svg>
                Forward to Admission
            </a>
            <a href="javascript:void(0)" data-action="delete" data-id="${apptId}" class="danger">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
                Delete
            </a>
        `;
    }

    function positionApptKebabDropdown(button) {
        if (!apptKebabDropdown || !button) return;

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
        
        if (top + 180 > viewportHeight - padding) {
            top = rect.top - 180 - 8;
        }
        
        if (top < padding) {
            top = padding;
        }

        apptKebabDropdown.style.left = left + 'px';
        apptKebabDropdown.style.top = top + 'px';
    }

    function openApptKebabDropdown(button) {
        if (!apptKebabDropdown) {
            createApptKebabDropdown();
        }

        const apptId = button.dataset.apptId;

        apptKebabDropdown.innerHTML = getApptMenuItems(apptId);
        positionApptKebabDropdown(button);

        apptKebabDropdown.classList.add('show');
        apptKebabBackdrop.classList.add('show');
        apptActiveButton = button;
        button.classList.add('active');

        apptKebabDropdown.addEventListener('click', handleApptKebabClick);
    }

    function closeApptKebabDropdown() {
        if (apptKebabDropdown) {
            apptKebabDropdown.classList.remove('show');
        }
        if (apptKebabBackdrop) {
            apptKebabBackdrop.classList.remove('show');
        }
        if (apptActiveButton) {
            apptActiveButton.classList.remove('active');
            apptActiveButton = null;
        }
    }

    function handleApptKebabClick(e) {
        const link = e.target.closest('a');
        if (!link) return;

        e.preventDefault();
        const action = link.dataset.action;
        const id = link.dataset.id;

        closeApptKebabDropdown();

        switch (action) {
            case 'view':
                viewAppointment(id);
                break;
            case 'edit':
                editAppointment(id);
                break;
            case 'forward':
                forwardToAdmission(id);
                break;
            case 'delete':
                deleteAppointment(id);
                break;
        }
    }

    // Click handler for kebab buttons
    document.addEventListener('click', function(e) {
        const button = e.target.closest('.appt-kebab-btn');
        if (button) {
            e.preventDefault();
            e.stopPropagation();
            
            if (apptActiveButton === button) {
                closeApptKebabDropdown();
            } else {
                closeApptKebabDropdown();
                openApptKebabDropdown(button);
            }
        } else if (!e.target.closest('.appt-kebab-dropdown-portal')) {
            closeApptKebabDropdown();
        }
    });

    function editAppointment(id) {
        const appt = appointments.find(a => a.id == id);
        if (!appt) return;
        
        // For now, show alert - you can implement edit modal later
        alert('Edit functionality for appointment #' + id + ' - Coming soon!');
    }

    function forwardToAdmission(id) {
        const appt = appointments.find(a => a.id == id);
        if (!appt) return;
        
        if (confirm('Forward this appointment to admission?')) {
            // Redirect to new admission form with appointment data
            window.location.href = 'new_admission.php?appointment_id=' + id;
        }
    }

    function deleteAppointment(id) {
        if (confirm('Are you sure you want to delete this appointment?\n\nNote: This will hide the appointment from your view. Admin can still see and permanently delete it.')) {
            fetch('delete_appointment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, soft_delete: true })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Error deleting appointment');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting appointment');
            });
        }
    }
</script>

<?php require_once 'includes/staff_layout_end.php'; ?>
