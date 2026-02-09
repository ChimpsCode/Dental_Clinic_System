<?php
$pageTitle = 'Patient Records';
require_once 'includes/staff_layout_start.php';

// Pagination settings
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;

try {
    require_once 'config/database.php';
    
    // Check if is_archived column exists
    $checkColumn = $pdo->query("SHOW COLUMNS FROM patients LIKE 'is_archived'");
    $hasArchiveColumn = $checkColumn->rowCount() > 0;
    
    // Build WHERE clause to exclude archived patients
    $whereClause = $hasArchiveColumn ? "WHERE is_archived = 0" : "";
    $whereClauseP = $hasArchiveColumn ? "WHERE p.is_archived = 0" : "";
    
    // Get total count for pagination (exclude archived)
    $countQuery = "SELECT COUNT(*) FROM patients $whereClause";
    $countStmt = $pdo->query($countQuery);
    $totalPatients = $countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalPatients / $itemsPerPage));
    
    // Ensure current page is valid
    if ($currentPage > $totalPages) $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    // Calculate showing range
    $showingStart = $totalPatients > 0 ? $offset + 1 : 0;
    $showingEnd = min($offset + $itemsPerPage, $totalPatients);
    
    // Get all patients for stats (without pagination, exclude archived)
    $allStmt = $pdo->query("
        SELECT p.*,
               (SELECT status FROM queue WHERE patient_id = p.id ORDER BY created_at DESC LIMIT 1) as queue_status,
               (SELECT treatment_type FROM queue WHERE patient_id = p.id ORDER BY created_at DESC LIMIT 1) as current_treatment
        FROM patients p
        $whereClauseP
    ");
    $allPatients = $allStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get paginated patients (exclude archived)
    $stmt = $pdo->prepare("
        SELECT p.*,
               (SELECT status FROM queue WHERE patient_id = p.id ORDER BY created_at DESC LIMIT 1) as queue_status,
               (SELECT treatment_type FROM queue WHERE patient_id = p.id ORDER BY created_at DESC LIMIT 1) as current_treatment
        FROM patients p 
        $whereClauseP
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate stats from all patients (exclude archived)
    $inQueue = count(array_filter($allPatients, fn($p) => in_array($p['queue_status'] ?? '', ['waiting', 'in_procedure'])));
    $scheduledCount = count(array_filter($allPatients, fn($p) => ($p['queue_status'] ?? '') === 'scheduled'));
    $newThisMonth = count(array_filter($allPatients, fn($p) => !empty($p['created_at']) && strtotime($p['created_at']) > strtotime('-30 days')));
    
} catch (Exception $e) {
    $patients = [];
    $allPatients = [];
    $totalPatients = 0;
    $totalPages = 1;
    $showingStart = 0;
    $showingEnd = 0;
    $inQueue = 0;
    $scheduledCount = 0;
    $newThisMonth = 0;
}
?>

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

<!-- Search Toolbar -->
<div class="search-toolbar" style="background: white; padding: 16px 24px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e5e7eb; display: flex; gap: 16px; align-items: center;">
    <div class="search-input-container" style="position: relative; flex: 1;">
        <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #6b7280;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="m19.6 21l-6.3-6.3q-.75.6-1.725.95T9.5 16q-2.725 0-4.612-1.888T3 9.5t1.888-4.612T9.5 3t4.613 1.888T16 9.5q0 1.1-.35 2.075T14.7 13.3l6.3 6.3zM9.5 14q1.875 0 3.188-1.312T14 9.5t-1.312-3.187T9.5 5T6.313 6.313T5 9.5t1.313 3.188T9.5 14"/></svg>
        </span>
        <input type="text" id="searchInput" placeholder="Search patient name or phone..." class="search-input" style="width: 100%; padding: 12px 16px 12px 44px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; outline: none;">
    </div>
    <select id="statusFilter" style="padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; background: white; cursor: pointer;">
        <option value="">All Status</option>
        <option value="waiting">Waiting</option>
        <option value="in_procedure">In Procedure</option>
        <option value="completed">Completed</option>
        <option value="scheduled">Scheduled</option>
        <option value="none">No Queue</option>
    </select>
</div>

<!-- Patient Records Table -->
<div class="section-card" style="background: white; border-radius: 12px; padding: 24px; border: 1px solid #e5e7eb;">
    <div class="section-title" style="font-size: 1.1rem; font-weight: 600; margin-bottom: 16px; color: #111827;">
        <span>All Patient Records</span>
    </div>
    <div style="overflow-x: auto;">
        <table class="data-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">First Name</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">Middle Name</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">Last Name</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">Age/Gender</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">Contact</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">Current Treatment</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">Queue Status</th>
                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">Actions</th>
                </tr>
            </thead>
            <tbody id="patientsTableBody">
                <?php if (empty($patients)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 60px; color: #6b7280;">
                            <p style="font-size: 1.1rem; margin-bottom: 8px;">No patient records found</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($patients as $patient): 
                        $status = $patient['queue_status'] ?? '';
                        $statusColors = [
                            'waiting' => '#fef3c7:#92400e',
                            'in_procedure' => '#dbeafe:#1d4ed8',
                            'completed' => '#d1fae5:#065f46',
                            'on_hold' => '#f3f4f6:#6b7280',
                            'cancelled' => '#fee2e2:#dc2626',
                            'scheduled' => '#e0e7ff:#4338ca'
                        ];
                        $bgColor = '#f3f4f6';
                        $textColor = '#6b7280';
                        if (isset($statusColors[$status])) {
                            list($bg, $text) = explode(':', $statusColors[$status]);
                            $bgColor = $bg;
                            $textColor = $text;
                        }
                    ?>
                        <tr class="patient-row" 
                            data-name="<?php echo strtolower(htmlspecialchars($patient['first_name'] ?? 'Unknown')); ?>"
                            data-phone="<?php echo strtolower(htmlspecialchars($patient['phone'] ?? '')); ?>"
                            data-status="<?php echo $status; ?>">
                            <td style="padding: 12px 16px; border-bottom: 1px solid #f3f4f6;">
                                <span style="font-weight: 500; color: #111827;"><?php echo htmlspecialchars($patient['first_name'] ?? 'Unknown'); ?></span>
                            </td>
                            <td style="padding: 12px 16px; border-bottom: 1px solid #f3f4f6;">
                                <?php echo htmlspecialchars($patient['middle_name'] ?? ''); ?>
                            </td>
                            <td style="padding: 12px 16px; border-bottom: 1px solid #f3f4f6;">
                                <span style="font-weight: 500; color: #111827;"><?php echo htmlspecialchars($patient['last_name'] ?? 'Unknown'); ?></span>
                            </td>
                            <td style="padding: 12px 16px; border-bottom: 1px solid #f3f4f6;">
                                <div style="font-size: 0.9rem;">
                                    <span style="font-weight: 500;"><?php echo $patient['age'] ?? 'N/A'; ?> yrs</span>
                                    <span style="color: #6b7280; margin-left: 8px;"><?php echo ucfirst($patient['gender'] ?? 'N/A'); ?></span>
                                </div>
                            </td>
                            <td style="padding: 12px 16px; border-bottom: 1px solid #f3f4f6;">
                                <div style="font-size: 0.85rem; color: #6b7280;">
                                    <div><?php echo htmlspecialchars($patient['phone'] ?: 'N/A'); ?></div>
                                    <div><?php echo htmlspecialchars($patient['email'] ?? ''); ?></div>
                                </div>
                            </td>
                            <td style="padding: 12px 16px; border-bottom: 1px solid #f3f4f6;">
                                <span style="font-weight: 500; color: #111827;"><?php echo htmlspecialchars($patient['current_treatment'] ?: 'None'); ?></span>
                            </td>
                            <td style="padding: 12px 16px; border-bottom: 1px solid #f3f4f6;">
                                <span style="background: <?php echo $bgColor; ?>; color: <?php echo $textColor; ?>; padding: 4px 12px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">
                                    <?php echo ucfirst(str_replace('_', ' ', $status ?: 'None')); ?>
                                </span>
                            </td>
                            <td style="padding: 12px 16px; border-bottom: 1px solid #f3f4f6;">
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
</div>

<!-- Pagination -->
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

<!-- Full Screen Patient Record Modal -->
<div id="patientRecordModal" class="fullscreen-modal-overlay">
    <div class="fullscreen-modal-content">
        <div class="fullscreen-modal-header">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 600; margin: 0;">Patient Record Details</h2>
                <p id="staffModalPatientName" style="color: #6b7280; margin: 4px 0 0 0; font-size: 0.9rem;"></p>
            </div>
            <button onclick="closePatientRecordModal()" class="fullscreen-modal-close">&times;</button>
        </div>
        <div class="fullscreen-modal-body" id="patientRecordContent">
        </div>
    </div>
</div>

<style>
.fullscreen-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    justify-content: center;
    backdrop-filter: blur(0px);
    z-index: 99999;
}
.fullscreen-modal-overlay.active {
    display: flex;
    align-items: center;
}
.fullscreen-modal-content {
    background: white;
    border-radius: 16px;
    width: 95%;
    max-width: 900px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    position: relative;
    z-index: 100000;
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
    font-size: 0.9rem;
    color: #111827;
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

/* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 99999;
}

.modal-overlay.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-backdrop {
    display: none;
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
    pointer-events: none;
}

.modal-overlay.active .modal-container {
    pointer-events: auto;
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
</style>

<script>
const patients = <?php echo json_encode($patients); ?>;

// Portal Pattern: Move modals to body level to escape stacking context
// This ensures modals appear above sidebar and all other elements
(function() {
    const patientRecordModal = document.getElementById('patientRecordModal');
    const appointmentModal = document.getElementById('appointmentModal');
    
    if (patientRecordModal) {
        document.body.appendChild(patientRecordModal);
    }
    if (appointmentModal) {
        document.body.appendChild(appointmentModal);
    }
})();

document.getElementById('searchInput').addEventListener('input', filterPatients);
document.getElementById('statusFilter').addEventListener('change', filterPatients);

function filterPatients() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    
    document.querySelectorAll('.patient-row').forEach(row => {
        const matchSearch = !search || 
            row.dataset.name.includes(search) || 
            row.dataset.phone.includes(search);
        const matchStatus = !status || row.dataset.status === status;
        
        row.style.display = (matchSearch && matchStatus) ? '' : 'none';
    });
}

function viewPatientRecord(patientId) {
    fetch('patient_record_details.php?id=' + patientId)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const p = data.patient;
            const m = data.medical_history || {};
            const d = data.dental_history || {};
            const q = data.queue_item || {};
            
            const allergies = m.allergies || 'None';
            const medications = m.current_medications || 'None';
            const conditions = m.medical_conditions || 'None';
            
            const fullName = `${p.first_name || ''} ${p.middle_name || ''} ${p.last_name || ''} ${p.suffix || ''}`.trim();
            document.getElementById('staffModalPatientName').innerText = fullName || 'Unknown';
            
            const hasMedicalAlert = allergies === 'Yes' || 
                                   conditions.toLowerCase().includes('diabetes') ||
                                   conditions.toLowerCase().includes('heart') ||
                                   conditions.toLowerCase().includes('blood pressure') ||
                                   conditions.toLowerCase().includes('asthma');
            
            document.getElementById('patientRecordContent').innerHTML = `
                <div class="patient-info-grid">
                    <div class="patient-info-item">
                        <div class="patient-info-label">First Name</div>
                        <div class="patient-info-value">${p.first_name || 'N/A'}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Middle Name</div>
                        <div class="patient-info-value">${p.middle_name || ''}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Last Name</div>
                        <div class="patient-info-value">${p.last_name || 'N/A'}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">Suffix</div>
                        <div class="patient-info-value">${p.suffix || ''}</div>
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
                    <div class="patient-info-item" style="grid-column: 1 / -1;">
                        <div class="patient-info-label">Address</div>
                        <div class="patient-info-value">${p.address || 'N/A'} ${p.city ? ', ' + p.city : ''} ${p.province ? ', ' + p.province : ''}</div>
                    </div>
                </div>

                ${hasMedicalAlert ? `
                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <div style="color: #dc2626; font-weight: 600; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">⚠️ Medical Alert - Important for Treatment</div>
                    <div class="medical-alert-grid">
                        <div class="patient-info-item">
                            <div class="patient-info-label">Allergies</div>
                            <div class="patient-info-value" style="${allergies === 'Yes' ? 'color: #dc2626;' : ''}">${allergies}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Diabetes</div>
                            <div class="patient-info-value" style="${conditions.toLowerCase().includes('diabetes') ? 'color: #dc2626;' : ''}">${conditions.toLowerCase().includes('diabetes') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Heart Disease</div>
                            <div class="patient-info-value" style="${conditions.toLowerCase().includes('heart') ? 'color: #dc2626;' : ''}">${conditions.toLowerCase().includes('heart') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">High Blood Pressure</div>
                            <div class="patient-info-value" style="${conditions.toLowerCase().includes('blood pressure') ? 'color: #dc2626;' : ''}">${conditions.toLowerCase().includes('blood pressure') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Asthma</div>
                            <div class="patient-info-value" style="${conditions.toLowerCase().includes('asthma') ? 'color: #dc2626;' : ''}">${conditions.toLowerCase().includes('asthma') ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="patient-info-item" style="grid-column: 1 / -1;">
                            <div class="patient-info-label">Current Medications</div>
                            <div class="patient-info-value">${medications}</div>
                        </div>
                    </div>
                </div>
                ` : ''}

                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                    <h3 style="font-size: 1rem; font-weight: 600; color: #1e40af; margin-bottom: 16px;">📋 Service Requested</h3>
                    <div class="patient-info-grid">
                        <div class="patient-info-item">
                            <div class="patient-info-label">Treatment Type</div>
                            <div class="patient-info-value">${q.treatment_type || 'Consultation'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Selected Teeth</div>
                            <div class="patient-info-value">${q.teeth_numbers ? getStaffPatientTeethDisplayText(q.teeth_numbers) : 'None specified'}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">Status</div>
                            <div class="patient-info-value">
                                <span style="background: ${q.status === 'in_procedure' ? '#dcfce7' : q.status === 'waiting' ? '#fef3c7' : '#f3f4f6'}; color: ${q.status === 'in_procedure' ? '#15803d' : q.status === 'waiting' ? '#d97706' : '#6b7280'}; padding: 4px 12px; border-radius: 9999px; font-size: 0.85rem;">
                                    ${q.status ? q.status.replace('_', ' ').toUpperCase() : 'NONE'}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="background: #f9fafb; border-radius: 12px; padding: 20px;">
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
                    </div>
                </div>
                
                <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end;">
                    <button onclick="closePatientRecordModal()" class="btn-cancel" style="padding: 10px 24px; background: #f3f4f6; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">Close</button>
                </div>
            `;
            document.getElementById('patientRecordModal').classList.add('active');
        }
    });
}

function closePatientRecordModal() {
    document.getElementById('patientRecordModal').classList.remove('active');
}

document.getElementById('patientRecordModal').addEventListener('click', function(e) {
    if (e.target === this) closePatientRecordModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePatientRecordModal();
});

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
            viewPatientRecord(id);
            break;
        case 'appointment':
            openAddAppointmentModal(id);
            break;
        case 'session':
            window.location.href = 'quick_session.php?patient_id=' + id;
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

// Add Appointment Modal Functions
let selectedPatientId = null;

function openAddAppointmentModal(patientId) {
    // Find patient data from the existing patients array
    const patient = patients.find(p => p.id == patientId);
    
    if (patient) {
        selectedPatientId = patientId;
        
        // Build full name from patient data
        const fullName = (patient.first_name || '') + ' ' + (patient.middle_name || '' + ' ') + (patient.last_name || '');
        
        // Update modal content
        document.getElementById('appointmentPatientName').textContent = fullName.trim();
        document.getElementById('appointmentPatientId').value = patientId;
        document.getElementById('appointmentPatientPhone').value = patient.phone || '';
        
        // Show modal
        document.getElementById('appointmentModal').classList.add('active');
    } else {
        alert('Patient not found');
    }
}

function closeAddAppointmentModal() {
    document.getElementById('appointmentModal').classList.remove('active');
    selectedPatientId = null;
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

// Convert teeth numbers to arch labels
function getStaffPatientTeethDisplayText(teethString) {
    if (!teethString || teethString.trim() === '') {
        return 'None specified';
    }
    
    const teeth = teethString.split(',').map(t => parseInt(t.trim())).filter(t => !isNaN(t));
    if (teeth.length === 0) {
        return 'None specified';
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

<!-- Add Appointment Modal -->
<div id="appointmentModal" class="modal-overlay">
    <div class="modal-backdrop"></div>
    <div class="modal-container">
        <div class="modal" style="max-width: 480px;">
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

<?php require_once 'includes/staff_layout_end.php'; ?>
