<?php
/**
 * Appointments - Admin page for viewing and managing all appointments
 */

$pageTitle = 'Appointments';

// Pagination settings
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;

try {
    require_once 'config/database.php';
    
    // Get total count for pagination
    $countStmt = $pdo->query("SELECT COUNT(*) FROM appointments");
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
                         CONCAT(p.first_name, ' ', IFNULL(p.middle_name, ''), ' ', p.last_name) as full_name, 
                         p.phone 
                         FROM appointments a 
                         LEFT JOIN patients p ON a.patient_id = p.id");
    $allAppointments = $allStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get paginated appointments
    $stmt = $pdo->prepare("SELECT a.*, 
                         CONCAT(p.first_name, ' ', IFNULL(p.middle_name, ''), ' ', p.last_name) as full_name, 
                         p.phone 
                         FROM appointments a 
                         LEFT JOIN patients p ON a.patient_id = p.id 
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
$pendingCount = count(array_filter($allAppointments, function($a) {
    return strtolower($a['status'] ?? '') === 'scheduled' || strtolower($a['status'] ?? '') === 'pending';
}));
$cancelledCount = count(array_filter($allAppointments, function($a) {
    return strtolower($a['status'] ?? '') === 'cancelled';
}));

require_once __DIR__ . '/includes/admin_layout_start.php';
?>
            <div class="content-main">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Appointments Management</h2>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn-primary" onclick="openAppointmentModal()">+ New Appointment</button>
                        <button class="btn-primary" onclick="exportAppointments()" style="background: #059669;">Export Report</button>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon blue">📅</div>
                        <div class="summary-info">
                            <h3><?php echo $todayCount; ?></h3>
                            <p>Today's Appointments</p>
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
                        <div class="summary-icon yellow">⏳</div>
                        <div class="summary-info">
                            <h3><?php echo $pendingCount; ?></h3>
                            <p>Pending</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon red">❌</div>
                        <div class="summary-info">
                            <h3><?php echo $cancelledCount; ?></h3>
                            <p>Cancelled</p>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="search-filters">
                    <div class="filter-tabs">
                        <span class="active" data-filter="all">All</span>
                        <span data-filter="today">Today</span>
                        <span data-filter="week">This Week</span>
                        <span data-filter="month">This Month</span>
                    </div>
                    <input type="text" class="search-input" placeholder="Search appointments..." id="appointmentSearch">
                </div>

                <!-- Appointments Table -->
                <div class="table-container">
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
                                            ?>
                                            <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn icon view-btn" title="View" onclick="viewAppointment(<?php echo $appt['id']; ?>)">👁️</button>
                                                <button class="action-btn icon" title="Edit">📝</button>
                                                <?php if (strtolower($status) !== 'completed' && strtolower($status) !== 'cancelled'): ?>
                                                <button class="action-btn icon" title="Cancel" onclick="cancelAppointment(<?php echo $appt['id']; ?>)">❌</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
    <div class="modal" style="width: 600px;">
        <h2 style="margin: 0 0 20px; font-size: 1.25rem; font-weight: 600;">Schedule New Appointment</h2>
        <form id="appointmentForm">
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>First Name *</label>
                    <input type="text" name="first_name" required class="form-control" placeholder="Enter first name">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" class="form-control" placeholder="Enter middle name">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" required class="form-control" placeholder="Enter last name">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="0912-345-6789">
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
.filter-tabs {
    display: flex;
    gap: 8px;
}

.filter-tabs span {
    padding: 8px 16px;
    background: #f3f4f6;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.filter-tabs span:hover {
    background: #e5e7eb;
}

.filter-tabs span.active {
    background: #2563eb;
    color: white;
}

.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}

.modal {
    background: white;
    border-radius: 12px;
    padding: 24px;
    max-height: 90vh;
    overflow-y: auto;
}

.form-row {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #374151;
    font-size: 0.875rem;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.875rem;
    transition: border-color 0.2s, box-shadow 0.2s;
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

.btn-cancel {
    padding: 10px 20px;
    background: #f3f4f6;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    color: #374151;
    cursor: pointer;
    font-size: 0.875rem;
}

.btn-cancel:hover {
    background: #e5e7eb;
}

.action-buttons {
    display: flex;
    gap: 8px;
}
</style>

<script>
    const appointments = <?php echo json_encode($appointments); ?>;

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
        document.getElementById('appointmentSearch').addEventListener('input', filterAppointments);
    });

    function filterAppointments() {
        const search = document.getElementById('appointmentSearch').value.toLowerCase();
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
        document.getElementById('appointmentForm').reset();
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

    function exportAppointments() {
        alert('Export functionality - This would generate a report of all appointments');
    }
</script>

<?php
require_once __DIR__ . '/includes/admin_layout_end.php';
?>
