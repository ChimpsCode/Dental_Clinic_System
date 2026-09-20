<?php
/**
 * Patient Logs - Admin list of overflowed patient records (not Archive).
 */

$pageTitle = 'Patient Logs';

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/patient_list_overflow_helper.php';

$logRows = [];
$totalInLog = 0;
$showingStart = 0;
$showingEnd = 0;
$filteredTotal = 0;
$currentPage = 1;
$totalPages = 1;
$perPage = 10;
$dbError = '';
$searchQuery = '';
$dateFilter = 'all';

try {
    ensurePatientLogColumn($pdo);

    $searchQuery = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
    $dateFilter = isset($_GET['date']) ? trim((string)$_GET['date']) : 'all';
    $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

    $patCols = [];
    foreach ($pdo->query('SHOW COLUMNS FROM patients') as $col) {
        $patCols[$col['Field']] = true;
    }

    $select = [
        'p.id',
        'p.first_name',
        'p.middle_name',
        'p.last_name',
        'p.suffix',
        'p.created_at',
    ];
    $hasPhone = isset($patCols['phone']);
    $hasType = isset($patCols['patient_type']);
    if ($hasPhone) {
        $select[] = 'p.phone';
    }
    if ($hasType) {
        $select[] = 'p.patient_type';
    }

    $where = 'WHERE COALESCE(p.is_archived, 0) = 0 AND COALESCE(p.in_patient_log, 0) = 1';
    $params = [];

    if ($searchQuery !== '') {
        $where .= " AND (
            CONCAT_WS(' ',
                NULLIF(TRIM(p.first_name), ''),
                NULLIF(TRIM(p.middle_name), ''),
                NULLIF(TRIM(p.last_name), ''),
                NULLIF(TRIM(p.suffix), '')
            ) LIKE :search
        ";
        if ($hasPhone) {
            $where .= ' OR p.phone LIKE :searchPhone';
        }
        $where .= ')';
        $params[':search'] = '%' . $searchQuery . '%';
        if ($hasPhone) {
            $params[':searchPhone'] = '%' . $searchQuery . '%';
        }
    }

    switch ($dateFilter) {
        case 'today':
            $where .= ' AND DATE(p.created_at) = CURDATE()';
            break;
        case 'week':
            $where .= ' AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
            break;
        case 'month':
            $where .= ' AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
            break;
    }

    $from = "FROM patients p $where";

    $countStmt = $pdo->prepare("SELECT COUNT(*) $from");
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $filteredTotal = (int)$countStmt->fetchColumn();
    $totalInLog = $filteredTotal;
    $totalPages = max(1, (int)ceil($filteredTotal / $perPage));
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }
    $offset = ($currentPage - 1) * $perPage;
    $showingStart = $filteredTotal ? $offset + 1 : 0;
    $showingEnd = min($offset + $perPage, $filteredTotal);

    $listSql = 'SELECT ' . implode(', ', $select) . " $from ORDER BY p.created_at ASC, p.id ASC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($listSql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $dbError = $e->getMessage();
    $logRows = [];
}

require_once __DIR__ . '/../../includes/admin_layout_start.php';
?>
            <div class="content-main">
                <div class="summary-cards">
                    <div class="summary-card card-total-patients">
                        <div class="summary-icon">
                            <img src="../../assets/icons/total-patients.png" alt="Patients in Log">
                        </div>
                        <div class="summary-info">
                            <h3><?php echo number_format($totalInLog); ?></h3>
                            <p>Patients in Log</p>
                        </div>
                    </div>
                </div>

                <?php if ($dbError): ?>
                <div style="padding: 16px; background: #fee2e2; color: #dc2626; border: 1px solid #f87171; border-radius: 8px; margin-bottom: 16px;">
                    <strong>Database Error:</strong> <?php echo htmlspecialchars($dbError); ?>
                </div>
                <?php endif; ?>

                <form method="GET" class="search-filters">
                    <input type="text" class="search-input" name="search" placeholder="Search by name or phone..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <select class="filter-select" name="date">
                        <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>All Time</option>
                        <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php echo $dateFilter === 'month' ? 'selected' : ''; ?>>This Month</option>
                    </select>
                    <button type="submit" class="btn-primary" style="padding: 8px 16px;">Search</button>
                </form>

                <div class="page-header">
                    <h2>Patient Logs</h2>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>First Name</th>
                                <th>Middle Name</th>
                                <th>Last Name</th>
                                <th>Contact</th>
                                <th>Type</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($logRows) === 0): ?>
                            <tr>
                                <td colspan="7" style="text-align:center;padding:40px;color:#6b7280;">No patients in log.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($logRows as $row):
                                $pid = (int)($row['id'] ?? 0);
                                $first = trim((string)($row['first_name'] ?? ''));
                                $middle = trim((string)($row['middle_name'] ?? ''));
                                $last = trim((string)($row['last_name'] ?? ''));
                                $suffix = trim((string)($row['suffix'] ?? ''));
                                $phone = trim((string)($row['phone'] ?? ''));
                                $type = trim((string)($row['patient_type'] ?? ''));
                                $created = $row['created_at'] ?? '';
                                $fullName = trim($first . ' ' . $middle . ' ' . $last . ' ' . $suffix);
                                $fullName = preg_replace('/\s+/', ' ', $fullName);
                            ?>
                            <tr>
                                <td><?php echo $first !== '' ? htmlspecialchars($first) : '—'; ?></td>
                                <td><?php echo $middle !== '' ? htmlspecialchars($middle) : '—'; ?></td>
                                <td><?php echo $last !== '' ? htmlspecialchars($last) : '—'; ?></td>
                                <td><?php echo $phone !== '' ? htmlspecialchars($phone) : 'N/A'; ?></td>
                                <td><?php echo $type !== '' ? htmlspecialchars($type) : '—'; ?></td>
                                <td><?php echo $created !== '' ? htmlspecialchars(substr((string)$created, 0, 10)) : 'N/A'; ?></td>
                                <td>
                                    <div class="patient-kebab-menu">
                                        <button type="button" class="patient-kebab-btn"
                                            data-patient-id="<?php echo $pid; ?>"
                                            data-name="<?php echo htmlspecialchars($fullName, ENT_QUOTES); ?>"
                                            data-phone="<?php echo htmlspecialchars($phone, ENT_QUOTES); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                                <circle cx="12" cy="6" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="18" r="2"/>
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

                <?php if ($filteredTotal > 0): ?>
                <div class="pagination">
                    <span class="pagination-info">Showing <?php echo $showingStart; ?>-<?php echo $showingEnd; ?> of <?php echo $filteredTotal; ?> entries</span>
                    <div class="pagination-buttons">
                        <?php
                        $queryString = $_GET;
                        unset($queryString['page'], $queryString['__pjax'], $queryString['_pjax']);
                        $baseUrl = '?' . http_build_query($queryString);
                        if (!empty($queryString)) {
                            $baseUrl .= '&';
                        }
                        ?>
                        <a href="<?php echo $baseUrl; ?>page=<?php echo $currentPage - 1; ?>" class="pagination-btn <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>" <?php echo $currentPage <= 1 ? 'onclick="return false;"' : ''; ?>>Previous</a>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == 1 || $i == $totalPages || ($i >= $currentPage - 1 && $i <= $currentPage + 1)): ?>
                                <a href="<?php echo $baseUrl; ?>page=<?php echo $i; ?>" class="pagination-btn <?php echo $i == $currentPage ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php elseif ($i == $currentPage - 2 || $i == $currentPage + 2): ?>
                                <span class="pagination-btn disabled">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <a href="<?php echo $baseUrl; ?>page=<?php echo $currentPage + 1; ?>" class="pagination-btn <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>" <?php echo $currentPage >= $totalPages ? 'onclick="return false;"' : ''; ?>>Next</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
<?php require_once __DIR__ . '/../../includes/admin_patient_log_view_modal.php'; ?>
<script>
var patientKebabDropdown = null;
var patientKebabBackdrop = null;
var patientActiveButton = null;

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
    if (!patientKebabDropdown || !patientKebabDropdown.isConnected) {
        patientKebabDropdown = null;
        patientKebabBackdrop = null;
        createPatientKebabDropdown();
    }

    patientKebabDropdown.innerHTML = getPatientMenuItems(button.dataset.patientId);
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
        patientKebabDropdown.style.display = '';
        patientKebabDropdown.style.visibility = '';
        patientKebabDropdown.style.left = '';
        patientKebabDropdown.style.top = '';
        patientKebabDropdown.removeEventListener('click', handlePatientKebabClick);
    }
    if (patientKebabBackdrop) {
        patientKebabBackdrop.classList.remove('show');
    }
    if (patientActiveButton) {
        patientActiveButton.classList.remove('active');
        patientActiveButton = null;
    }
}

function resetPatientKebabState() {
    closePatientKebabDropdown();
    if (patientKebabDropdown && patientKebabDropdown.parentNode) {
        patientKebabDropdown.parentNode.removeChild(patientKebabDropdown);
    }
    if (patientKebabBackdrop && patientKebabBackdrop.parentNode) {
        patientKebabBackdrop.parentNode.removeChild(patientKebabBackdrop);
    }
    patientKebabDropdown = null;
    patientKebabBackdrop = null;
    patientActiveButton = null;
}

function handlePatientKebabClick(e) {
    const link = e.target.closest('a[data-action]');
    if (!link) return;
    e.preventDefault();
    e.stopPropagation();
    const action = link.dataset.action;
    const id = parseInt(link.dataset.id, 10);
    closePatientKebabDropdown();
    if (action === 'view') {
        viewPatientDetails(id);
    } else if (action === 'delete') {
        deletePatient(id);
    }
}

function handlePatientLogKebabDocumentClick(e) {
    const button = e.target.closest('.patient-kebab-btn');
    if (button) {
        e.preventDefault();
        e.stopImmediatePropagation();
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
}

if (window.__adminPatientLogKebabClickHandler) {
    document.removeEventListener('click', window.__adminPatientLogKebabClickHandler, true);
}
window.__adminPatientLogKebabClickHandler = handlePatientLogKebabDocumentClick;
document.addEventListener('click', window.__adminPatientLogKebabClickHandler, true);

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && patientKebabDropdown && patientKebabDropdown.classList.contains('show')) {
        closePatientKebabDropdown();
    }
});
window.addEventListener('resize', function() {
    if (patientKebabDropdown && patientKebabDropdown.classList.contains('show') && patientActiveButton) {
        positionPatientKebabDropdown(patientActiveButton);
    }
});
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState !== 'visible') {
        resetPatientKebabState();
    }
});
window.addEventListener('pagehide', resetPatientKebabState);
window.addEventListener('pageshow', resetPatientKebabState);

function deletePatient(patientId) {
    const btn = document.querySelector('.patient-kebab-btn[data-patient-id="' + patientId + '"]');
    const fullName = btn ? (btn.dataset.name || 'Unknown') : 'Unknown';
    const phone = btn ? (btn.dataset.phone || 'N/A') : 'N/A';
    const existingModal = document.getElementById('archive-patient-modal');
    if (existingModal) existingModal.remove();

    const overlay = document.createElement('div');
    overlay.id = 'archive-patient-modal';
    overlay.className = 'modal-overlay active';
    overlay.innerHTML = `
        <div class="modal" style="max-width: 400px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #92400e; font-size: 18px;">Archive Patient</h3>
                <button onclick="closeArchivePatientModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280;">&times;</button>
            </div>
            <div style="background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <p style="color: #92400e; font-size: 14px; margin: 0; font-weight: 600;">Archive this patient?</p>
                <p style="color: #b45309; font-size: 13px; margin: 8px 0 0 0;">
                    Patient: <strong>${fullName}</strong><br>
                    Phone: ${phone || 'N/A'}
                </p>
                <p style="color: #a16207; font-size: 13px; margin: 8px 0 0 0;">
                    You can restore archived patients from the Archive page.
                </p>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button onclick="closeArchivePatientModal()" style="padding: 10px 20px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer; font-size: 14px;">Cancel</button>
                <button onclick="confirmArchivePatient(${patientId})" style="padding: 10px 20px; background: #92400e; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px;">Archive</button>
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

function stripPatientLogPortals() {
    if (typeof resetPatientKebabState === 'function') {
        resetPatientKebabState();
    }
    [
        'adminPatientModal',
        'recordPaymentModal',
        'paymentHistoryModal',
        'setDueDateModal',
        'odontogramModalOverlay',
        'odXrayViewerOverlay',
        'archive-patient-modal'
    ].forEach(function(id) {
        var el = document.getElementById(id);
        if (el && el.parentNode) {
            el.parentNode.removeChild(el);
        }
    });
    document.body.classList.remove('modal-open');
}

function confirmArchivePatient(patientId) {
    closeArchivePatientModal();
    fetch('../../master/user-patient/patient_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=archive&patient_id=' + patientId
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            stripPatientLogPortals();
            showAppNotice('Success', 'Patient archived successfully', 'success', function () {
                location.reload();
            });
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(function() {
        alert('Failed to archive patient');
    });
}
</script>
<?php require_once __DIR__ . '/../../includes/admin_layout_end.php'; ?>
