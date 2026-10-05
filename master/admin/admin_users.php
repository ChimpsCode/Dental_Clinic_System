<?php
/**
 * User Management - Admin page for managing system users
 */

$pageTitle = 'User Management';

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/admin_layout_start.php';

$users = [];
$totalUsers = 0;
$dentistCount = 0;
$staffCount = 0;
$registeredPatients = 0;
$unregisteredPatients = 0;

try {
    // Get users with role names from roles table
    // If role is not set, treat as Patient
    // Exclude archived users
    // Build select list dynamically based on actual columns in `users` to avoid unknown column errors
    $colsStmt = $pdo->query("SHOW COLUMNS FROM users");
    $cols = array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    // Check patients table and its columns (used as fallback for some patient fields)
    $patientsTableExists = false;
    $patientsCols = [];
    try {
        $tbl = $pdo->query("SHOW TABLES LIKE 'patients'")->fetchColumn();
        if ($tbl) {
            $patientsTableExists = true;
            $pColsStmt = $pdo->query("SHOW COLUMNS FROM patients");
            $patientsCols = array_column($pColsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
        }
    } catch (Exception $e) {
        // ignore
    }

    $desired = [
        'id','username','first_name','middle_name','last_name','email',
        'phone','address','emergency_contact_name','emergency_contact_phone','emergency_contact_relationship',
        'profile_picture','status','is_archived','role','gender','date_of_birth','last_active','last_logout','is_logged_in','two_factor_enabled'
    ];

    $selectParts = [];
    foreach ($desired as $c) {
        if (in_array($c, $cols)) {
            if ($c === 'role') {
                $selectParts[] = "u.role as role_name";
            } else {
                $selectParts[] = "u.$c";
            }
            continue;
        }

        // Fallback: if users table doesn't have emergency fields but patients table does,
        // select from patients using users.patient_id link.
        if ($patientsTableExists && preg_match('/^emergency_contact_/', $c) && in_array($c, $patientsCols)) {
            $selectParts[] = "p.$c as $c";
            // ensure we'll left join patients later
            $needPatientsJoin = true;
        }
    }

    // If we will select from patients, ensure LEFT JOIN clause
    $joinClause = '';
    if (!empty($needPatientsJoin)) {
        $joinClause = ' LEFT JOIN patients p ON p.id = u.patient_id ';
    }

    // Ensure we always have the core identifying columns
    $requiredAlways = ['u.id'];
    $select = implode(', ', array_unique(array_merge($requiredAlways, $selectParts)));

    $sql = "SELECT {$select} FROM users u {$joinClause} WHERE (u.is_archived = 0 OR u.is_archived IS NULL) ORDER BY u.id ASC";
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as &$user) {
        if (!isset($user['status']) || $user['status'] === null || $user['status'] === '') {
            $user['status'] = 'Active';
        }
    }
    unset($user);
    $totalUsers = count($users);
    foreach ($users as $u) {
        $roleLower = strtolower((string)($u['role_name'] ?? ''));
        if ($roleLower === 'dentist') {
            $dentistCount++;
        } elseif ($roleLower === 'staff') {
            $staffCount++;
        }
    }
} catch (Exception $e) {
    $users = [];
    $totalUsers = 0;
    $usersQueryError = $e->getMessage();
    error_log('[admin_users] users query error: ' . $usersQueryError);
}

try {
    if (!empty($patientsTableExists)) {
        $linkedCondition = '';
        if (in_array('user_id', $patientsCols ?? [], true)) {
            $linkedCondition = '(p.user_id IS NOT NULL AND p.user_id <> 0)';
        } elseif (in_array('patient_id', $cols ?? [], true)) {
            $linkedCondition = 'EXISTS (SELECT 1 FROM users u WHERE u.patient_id = p.id)';
        }

        if ($linkedCondition !== '') {
            $patientWhere = in_array('is_archived', $patientsCols, true)
                ? 'WHERE (p.is_archived = 0 OR p.is_archived IS NULL)'
                : '';
            $patientStats = $pdo->query("
                SELECT
                    SUM(CASE WHEN {$linkedCondition} THEN 1 ELSE 0 END) AS registered,
                    SUM(CASE WHEN {$linkedCondition} THEN 0 ELSE 1 END) AS unregistered
                FROM patients p
                {$patientWhere}
            ")->fetch(PDO::FETCH_ASSOC) ?: [];
            $registeredPatients = (int)($patientStats['registered'] ?? 0);
            $unregisteredPatients = (int)($patientStats['unregistered'] ?? 0);
        }
    }
} catch (Exception $e) {
    error_log('[admin_users] patient stats error: ' . $e->getMessage());
}
?>
            <div class="content-main">
                <div class="summary-cards">
                    <div class="summary-card card-total-users">
                        <div class="summary-info">
                            <h3><?php echo number_format($totalUsers); ?></h3>
                            <p>Total Users</p>
                        </div>
                    </div>
                    <div class="summary-card card-dentist">
                        <div class="summary-info">
                            <h3><?php echo number_format($dentistCount); ?></h3>
                            <p>Dentist</p>
                        </div>
                    </div>
                    <div class="summary-card card-staff">
                        <div class="summary-info">
                            <h3><?php echo number_format($staffCount); ?></h3>
                            <p>Staff</p>
                        </div>
                    </div>
                    <div class="summary-card card-registered">
                        <div class="summary-info">
                            <h3><?php echo number_format($registeredPatients); ?></h3>
                            <p>Registered Patients</p>
                        </div>
                    </div>
                    <div class="summary-card card-unregistered">
                        <div class="summary-info">
                            <h3><?php echo number_format($unregisteredPatients); ?></h3>
                            <p>Unregistered Patients</p>
                        </div>
                    </div>
                </div>

                <!-- Page Header -->
                <!-- <div class="page-header">
                    <div>
                        <h2>User Management</h2>
                        <p class="page-subtitle">Total Users: <strong><?php echo $totalUsers; ?></strong></p>
                    </div>
                    <button class="btn-primary" onclick="openUserModal()">+ Add New User</button>
                </div> -->

                <!-- Search and Filter -->
                <div class="search-filters">
                    <input type="text" class="search-input" placeholder="Search users..." id="userSearch">
                    <select class="filter-select" id="roleFilter">
                        <option value="">All Roles</option>
                        <option value="admin">Admin</option>
                        <option value="dentist">Dentist</option>
                        <option value="staff">Staff</option>
                        <option value="patient">Patient</option>
                    </select>
                    <button class="btn-primary" onclick="openUserModal()">+ Add New User</button>
                </div>
                

                <!-- Users Table -->
                <div class="table-container">
                    <?php if (!empty($usersQueryError)): ?>
                        <div style="background:#fee2e2;border:1px solid #fca5a5;padding:12px;border-radius:8px;margin-bottom:12px;color:#991b1b;">
                            <strong>Error loading users:</strong> <?php echo htmlspecialchars($usersQueryError); ?>
                        </div>
                    <?php endif; ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Username</th>
                                <th>First Name</th>
                                <th>Middle Name</th>
                                <th>Last Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <?php $rowNum = 1; foreach ($users as $user): ?>
                            <?php
                                $username = $user['username'] ?? '';
                                $firstName = $user['first_name'] ?? '';
                                $middleName = $user['middle_name'] ?? '';
                                $lastName = $user['last_name'] ?? '';
                                $email = $user['email'] ?? '';
                                // Normalize emergency contact fields across possible schema variations
                                $emergencyName = $user['emergency_contact_name'] ?? $user['emergency_name'] ?? $user['ec_name'] ?? '';
                                $emergencyPhone = $user['emergency_contact_phone'] ?? $user['emergency_phone'] ?? $user['ec_phone'] ?? '';
                                $emergencyRelationship = $user['emergency_contact_relationship'] ?? $user['emergency_relationship'] ?? $user['ec_relationship'] ?? '';
                                $rowRole = strtolower((string)($user['role_name'] ?? ''));
                                if ($rowRole === 'user') $rowRole = 'patient';
                            ?>
                            <tr data-role="<?php echo htmlspecialchars($rowRole); ?>">
                                <td><?php echo $rowNum++; ?></td>
                                <td><?php echo htmlspecialchars($username); ?></td>
                                <td><?php echo htmlspecialchars($firstName); ?></td>
                                <td><?php echo htmlspecialchars($middleName); ?></td>
                                <td><?php echo htmlspecialchars($lastName); ?></td>
                                <td><?php echo htmlspecialchars($email); ?></td>
                                <td><span class="role-badge <?php echo strtolower($user['role_name'] ?? ''); ?>"><?php echo htmlspecialchars($user['role_name'] ?? 'N/A'); ?></span></td>
                                <td><span class="status-badge <?php echo strtolower($user['status']); ?>"><?php 
                                    $lastActive = $user['last_active'] ?? null;
                                    $lastLogout = $user['last_logout'] ?? null;
                                    $isLoggedIn = $user['is_logged_in'] ?? null;
                                    
                                    if ($isLoggedIn == 1 || ($isLoggedIn === null && $lastActive !== null && $lastLogout === null)) {
                                        // User is logged in (is_logged_in = 1) OR legacy user (is_logged_in = NULL but has last_active and no last_logout)
                                        ?><span class="user-status" data-logged-in="true">Active</span><?php
                                    } elseif ($lastLogout !== null) {
                                        // User logged out, show time since logout
                                        ?><span class="user-status" data-last-logout="<?php echo htmlspecialchars($lastLogout); ?>">checking...</span><?php
                                    } elseif ($lastActive !== null) {
                                        // Has logged in before but not currently logged in, use last_active as fallback
                                        ?><span class="user-status" data-last-logout="<?php echo htmlspecialchars($lastActive); ?>">checking...</span><?php
                                    } else {
                                        // Never logged in
                                        ?><span style="color:#dc2626;font-weight:bold;">Offline</span><?php
                                    }
                                    ?></span></td>
                                <td class="action-buttons">
                                    <button
                                        class="user-kebab-btn"
                                        type="button"
                                        data-user-id="<?php echo (int)$user['id']; ?>"
                                        data-user-status="<?php echo htmlspecialchars($user['status']); ?>"
                                        data-user-username="<?php echo htmlspecialchars($user['username'] ?? ''); ?>"
                                        data-user-first="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>"
                                        data-user-middle="<?php echo htmlspecialchars($user['middle_name'] ?? ''); ?>"
                                        data-user-last="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>"
                                        data-user-email="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                        data-user-role="<?php echo htmlspecialchars($user['role_name'] ?? ''); ?>"
                                        data-user-two-factor="<?php echo (int)($user['two_factor_enabled'] ?? 0); ?>"
                                        data-user-gender="<?php echo htmlspecialchars($user['gender'] ?? ''); ?>"
                                        data-user-dob="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>"
                                        data-user-phone="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                        data-user-address="<?php echo htmlspecialchars($user['address'] ?? ''); ?>"
                                        data-user-emergency-name="<?php echo htmlspecialchars($emergencyName); ?>"
                                        data-user-emergency-phone="<?php echo htmlspecialchars($emergencyPhone); ?>"
                                        data-user-emergency-relationship="<?php echo htmlspecialchars($emergencyRelationship); ?>"
                                        data-user-profile-picture="<?php echo htmlspecialchars($user['profile_picture'] ?? ''); ?>"
                                        aria-label="User actions"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                            <circle cx="12" cy="5" r="2"></circle>
                                            <circle cx="12" cy="12" r="2"></circle>
                                            <circle cx="12" cy="19" r="2"></circle>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div id="reset2faModal" class="modal-overlay">
                    <div class="modal">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <h2 style="margin: 0; font-size: 1.25rem; font-weight: 600; color: #111827;">Reset Two-Factor Authentication</h2>
                            <button type="button" onclick="closeReset2faModal()" aria-label="Close" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280; line-height: 1;">&times;</button>
                        </div>
                        <p class="reset2fa-message">Reset two-factor authentication for this user? They will be able to log in without an authenticator code and must set up 2FA again from their profile.</p>
                        <input type="hidden" id="reset2faUserId" value="">
                        <div class="reset2fa-actions">
                            <button type="button" class="btn-reset2fa-cancel" onclick="closeReset2faModal()">Cancel</button>
                            <button type="button" class="btn-reset2fa-danger" id="reset2faConfirmBtn" onclick="confirmResetUser2FA()">Reset 2FA</button>
                        </div>
                    </div>
                </div>

                <div id="userKebabDropdown" class="user-kebab-dropdown"></div>
                <div id="userKebabBackdrop" class="user-kebab-backdrop"></div>

                <style>
                    .user-kebab-btn {
                        background: none;
                        border: none;
                        cursor: pointer;
                        padding: 8px;
                        border-radius: 50%;
                        color: #6b7280;
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        transition: all 0.2s ease;
                    }

                    .user-kebab-btn:hover,
                    .user-kebab-btn.active {
                        background-color: #f3f4f6;
                        color: #111827;
                    }

                    .user-kebab-dropdown {
                        position: fixed;
                        display: none;
                        background: #ffffff;
                        border: 1px solid #e5e7eb;
                        border-radius: 10px;
                        box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.2);
                        min-width: 180px;
                        z-index: 100000;
                        overflow: visible;
                        max-height: none;
                    }

                    .user-kebab-dropdown.show {
                        display: flex !important;
                        flex-direction: column;
                        animation: fadeInKebab 0.16s ease;
                        visibility: visible !important;
                        opacity: 1 !important;
                    }

                    .user-kebab-dropdown a {
                        display: flex !important;
                        align-items: center;
                        gap: 10px;
                        padding: 10px 14px;
                        color: #374151;
                        text-decoration: none;
                        font-size: 0.95rem;
                        transition: background 0.15s ease, color 0.15s ease;
                        white-space: nowrap;
                        visibility: visible !important;
                        opacity: 1 !important;
                    }

                    .user-kebab-dropdown a:hover {
                        background: #f3f4f6;
                        color: #111827;
                    }

                    .user-kebab-dropdown .danger {
                        color: #b91c1c;
                    }

                    .user-kebab-dropdown .danger:hover {
                        background: #fef2f2;
                        color: #991b1b;
                    }

                    .user-kebab-dropdown svg {
                        width: 18px;
                        height: 18px;
                        color: currentColor;
                    }

                    .user-kebab-backdrop {
                        position: fixed;
                        inset: 0;
                        display: none;
                        z-index: 9999;
                    }

                    .user-kebab-backdrop.show {
                        display: block;
                    }

                    #reset2faModal.modal-overlay {
                        z-index: 100001;
                    }

                    #reset2faModal .modal {
                        z-index: 100002;
                        max-width: 460px;
                        text-align: left;
                    }

                    #reset2faModal .reset2fa-message {
                        margin: 0 0 24px;
                        color: #4b5563;
                        font-size: 15px;
                        line-height: 1.6;
                    }

                    #reset2faModal .reset2fa-actions {
                        display: flex;
                        justify-content: flex-end;
                        gap: 12px;
                    }

                    #reset2faModal .btn-reset2fa-danger {
                        padding: 10px 20px;
                        background: #dc2626;
                        color: #fff;
                        border: none;
                        border-radius: 8px;
                        cursor: pointer;
                        font-weight: 500;
                    }

                    #reset2faModal .btn-reset2fa-danger:hover {
                        background: #b91c1c;
                    }

                    #reset2faModal .btn-reset2fa-danger:disabled {
                        opacity: 0.6;
                        cursor: not-allowed;
                    }

                    #reset2faModal .btn-reset2fa-cancel {
                        padding: 10px 20px;
                        background: #fff;
                        color: #374151;
                        border: 1px solid #d1d5db;
                        border-radius: 8px;
                        cursor: pointer;
                        font-weight: 500;
                    }

                    #reset2faModal .btn-reset2fa-cancel:hover {
                        background: #f9fafb;
                    }

                    @keyframes fadeInKebab {
                        from { opacity: 0; transform: translateY(-4px); }
                        to { opacity: 1; transform: translateY(0); }
                    }
                </style>

<script>
                document.addEventListener('DOMContentLoaded', function() {
                    var USERS_PER_PAGE = 10;
                    var usersCurrentPage = 1;

                    function getVisibleUserRows() {
                        var search = (document.getElementById('userSearch')?.value || '').toLowerCase().trim();
                        var role = (document.getElementById('roleFilter')?.value || '').toLowerCase();
                        return Array.from(document.querySelectorAll('#usersTableBody tr')).filter(function(row) {
                            var matchesSearch = !search || row.textContent.toLowerCase().includes(search);
                            var rowRole = (row.getAttribute('data-role') || '').toLowerCase();
                            if (rowRole === 'user') rowRole = 'patient';
                            var matchesRole = !role || rowRole === role;
                            return matchesSearch && matchesRole;
                        });
                    }

                    function renderUsersPage() {
                        var allRows = Array.from(document.querySelectorAll('#usersTableBody tr'));
                        var visibleRows = getVisibleUserRows();
                        var total = visibleRows.length;
                        var totalPages = Math.max(1, Math.ceil(total / USERS_PER_PAGE));
                        if (usersCurrentPage > totalPages) usersCurrentPage = totalPages;
                        if (usersCurrentPage < 1) usersCurrentPage = 1;

                        var start = (usersCurrentPage - 1) * USERS_PER_PAGE;
                        var end = start + USERS_PER_PAGE;
                        var pageRows = visibleRows.slice(start, end);

                        allRows.forEach(function(row) { row.style.display = 'none'; });
                        pageRows.forEach(function(row) { row.style.display = ''; });

                        var info = document.getElementById('usersPaginationInfo');
                        if (info) {
                            if (total === 0) {
                                info.textContent = 'Showing 0 of 0 users';
                            } else {
                                info.textContent = 'Showing ' + (start + 1) + '-' + (start + pageRows.length) + ' of ' + total + ' users';
                            }
                        }

                        var buttons = document.getElementById('usersPaginationButtons');
                        if (!buttons) return;
                        buttons.innerHTML = '';

                        var prev = document.createElement('button');
                        prev.type = 'button';
                        prev.className = 'pagination-btn';
                        prev.textContent = 'Previous';
                        prev.disabled = usersCurrentPage <= 1;
                        prev.addEventListener('click', function() {
                            if (usersCurrentPage > 1) {
                                usersCurrentPage -= 1;
                                renderUsersPage();
                            }
                        });
                        buttons.appendChild(prev);

                        for (var i = 1; i <= totalPages; i++) {
                            (function(pageNum) {
                                var btn = document.createElement('button');
                                btn.type = 'button';
                                btn.className = 'pagination-btn' + (pageNum === usersCurrentPage ? ' active' : '');
                                btn.textContent = String(pageNum);
                                btn.addEventListener('click', function() {
                                    usersCurrentPage = pageNum;
                                    renderUsersPage();
                                });
                                buttons.appendChild(btn);
                            })(i);
                        }

                        var next = document.createElement('button');
                        next.type = 'button';
                        next.className = 'pagination-btn';
                        next.textContent = 'Next';
                        next.disabled = usersCurrentPage >= totalPages || total === 0;
                        next.addEventListener('click', function() {
                            if (usersCurrentPage < totalPages) {
                                usersCurrentPage += 1;
                                renderUsersPage();
                            }
                        });
                        buttons.appendChild(next);
                    }

                    var searchInput = document.getElementById('userSearch');
                    var roleFilter = document.getElementById('roleFilter');
                    if (searchInput) {
                        searchInput.addEventListener('input', function() {
                            usersCurrentPage = 1;
                            renderUsersPage();
                        });
                    }
                    if (roleFilter) {
                        roleFilter.addEventListener('change', function() {
                            usersCurrentPage = 1;
                            renderUsersPage();
                        });
                    }
                    renderUsersPage();

                    function getTimeAgo(timestamp) {
                        if (!timestamp) return '';
                        
                        // FIX 3 IS HERE: Added safety check to prevent fatal JS error
                        if (!timestamp.includes(' ')) return 'checking...';
                        
                        const [datePart, timePart] = timestamp.split(' ');
                        const [year, month, day] = datePart.split('-');
                        const [hour, minute, second] = timePart.split(':');
                        const serverTs = new Date(Date.UTC(year, month - 1, day, hour, minute, second));
                        const now = new Date();
                        const diffMs = now - serverTs;
                        const diffSec = Math.floor(diffMs / 1000);
                        const diffMin = Math.floor(diffSec / 60);
                        const diffHour = Math.floor(diffMin / 60);
                        const diffDay = Math.floor(diffHour / 24);
                        const diffWeek = Math.floor(diffDay / 7);
                        const diffMonth = Math.floor(diffDay / 30);
                        if (diffSec < 60) return 'just now';
                        if (diffMin < 60) return diffMin + 'm ago';
                        if (diffHour < 24) return diffHour + 'h ago';
                        if (diffDay < 7) return diffDay + 'd ago';
                        if (diffWeek < 4) return diffWeek + 'w ago';
                        if (diffMonth < 12 && diffWeek >= 4) {
                            return diffMonth + ' month' + (diffMonth === 1 ? '' : 's') + ' ago';
                        }
                        return 'long time ago';
                    }

                    function updateStatuses() {
                        // Handle logged in users (green Active)
                        document.querySelectorAll('.user-status[data-logged-in]').forEach(function(el) {
                            el.textContent = 'Active';
                            el.style.color = '#065f46';
                        });
                        
                        // Handle logged out users with time since logout
                        document.querySelectorAll('.user-status[data-last-logout]').forEach(function(el) {
                            const lastLogout = el.dataset.lastLogout;
                            if (lastLogout) {
                                el.textContent = getTimeAgo(lastLogout);
                                el.style.color = '#6b7280';
                            }
                        });
                    }

                    updateStatuses();
                    setInterval(updateStatuses, 30000);

                    // Kebab Menu Functionality
                    var kebabDropdown = document.getElementById('userKebabDropdown');
                    var kebabBackdrop = document.getElementById('userKebabBackdrop');
                    let activeKebabBtn = null;

                    function getUserMenuItems(userId, status, role, twoFactor) {
                        const roleLower = (role || '').toLowerCase();
                        const canReset2fa = ['admin', 'staff', 'dentist'].includes(roleLower) && parseInt(twoFactor, 10) === 1;
                        const reset2faItem = canReset2fa ? `
                            <a href="javascript:void(0)" onclick="closeKebabDropdown(); resetUser2FA(${userId})">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                                Reset 2FA
                            </a>
                        ` : '';
                        return `
                            <a href="javascript:void(0)" onclick="viewUserDetails(${userId})">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                View
                            </a>
                            <a href="javascript:void(0)" onclick="editUser(${userId})">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                                Edit
                            </a>
                            ${reset2faItem}
                            <a href="javascript:void(0)" class="danger" onclick="deleteUser(${userId})">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                </svg>
                                Archive
                            </a>
                        `;
                    }

                    function positionKebabDropdown(button) {
                        if (!kebabDropdown || !button) return;
                        const rect = button.getBoundingClientRect();
                        const viewportWidth = window.innerWidth;
                        const viewportHeight = window.innerHeight;
                        const padding = 15;
                        const dropdownWidth = 170;
                        const dropdownHeight = 260;
                        
                        let left = rect.right + 5;
                        let top = rect.top;
                        
                        if (left + dropdownWidth > viewportWidth - padding) {
                            left = rect.left - dropdownWidth - 5;
                        }
                        if (left < padding) left = padding;
                        
                        if (top + dropdownHeight > viewportHeight - padding) {
                            top = rect.top - dropdownHeight;
                        }
                        if (top < padding) {
                            top = padding;
                        }
                        
                        kebabDropdown.style.left = left + 'px';
                        kebabDropdown.style.top = top + 'px';
                    }

                    function openKebabDropdown(button) {
                        kebabDropdown = document.getElementById('userKebabDropdown');
                        kebabBackdrop = document.getElementById('userKebabBackdrop');
                        if (!kebabDropdown || !kebabBackdrop) return;
                        const userId = button.dataset.userId;
                        const status = button.dataset.userStatus;
                        const role = button.dataset.userRole;
                        const twoFactor = button.dataset.userTwoFactor;
                        
                        kebabDropdown.innerHTML = getUserMenuItems(userId, status, role, twoFactor);
                        positionKebabDropdown(button);
                        
                        kebabDropdown.classList.add('show');
                        kebabBackdrop.classList.add('show');
                        activeKebabBtn = button;
                        button.classList.add('active');
                    }

                    function closeKebabDropdown() {
                        kebabDropdown.classList.remove('show');
                        kebabDropdown.innerHTML = '';
                        kebabBackdrop.classList.remove('show');
                        if (activeKebabBtn) {
                            activeKebabBtn.classList.remove('active');
                            activeKebabBtn = null;
                        }
                    }
                    window.closeKebabDropdown = closeKebabDropdown;

                    if (window.__adminUsersKebabClickHandler) {
                        document.removeEventListener('click', window.__adminUsersKebabClickHandler, true);
                        document.removeEventListener('click', window.__adminUsersKebabClickHandler, false);
                    }
                    function handleAdminUsersKebabDocumentClick(e) {
                        const button = e.target.closest('.user-kebab-btn');
                        if (button) {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            if (activeKebabBtn === button && kebabDropdown && kebabDropdown.classList.contains('show')) {
                                closeKebabDropdown();
                            } else {
                                if (activeKebabBtn) closeKebabDropdown();
                                openKebabDropdown(button);
                            }
                            return;
                        }
                        
                        if (!e.target.closest('.user-kebab-dropdown')) {
                            closeKebabDropdown();
                        }
                    }
                    window.__adminUsersKebabClickHandler = handleAdminUsersKebabDocumentClick;
                    document.addEventListener('click', window.__adminUsersKebabClickHandler, true);

                    // ESC key to close
                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape' && typeof closeKebabDropdown === 'function') closeKebabDropdown();
                    });

                    // View User Details Modal
                    window.viewUserDetails = function(userId) {
                            const btn = document.querySelector(`.user-kebab-btn[data-user-id="${userId}"]`);
                            if (!btn) return;
                            
                            const fullName = `${btn.dataset.userFirst || ''} ${btn.dataset.userMiddle || ''} ${btn.dataset.userLast || ''}`.trim();
                            const roleDisplay = btn.dataset.userRole || 'N/A';
                            const roleClass = roleDisplay.toLowerCase();
                            
                            // FIX 2 IS HERE: Added .toLowerCase() to handle capital "Active" from PHP
                            const statusDisplay = (btn.dataset.userStatus || '').toLowerCase() === 'active' ? 
                                '<span style="background: #d1fae5; color: #065f46; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">✓ Active</span>' :
                                '<span style="background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">✕ Inactive</span>';
                            
                            const existingModal = document.getElementById('view-user-modal');
                            if (existingModal) existingModal.remove();
                            
                            const overlay = document.createElement('div');
                            overlay.id = 'view-user-modal';
                            overlay.className = 'modal-overlay active';
                            
                            // FIX 1 IS HERE: Added position fixed and z-index to make it float
                            overlay.style.position = 'fixed';
                            overlay.style.inset = '0';
                            overlay.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
                            overlay.style.zIndex = '100001'; 
                            
                            overlay.style.display = 'flex';
                            overlay.style.alignItems = 'center';
                            overlay.style.justifyContent = 'center';
                        
                        overlay.innerHTML = `
    <div style="width: 100%; max-width: 640px; margin: 0 20px; max-height: 90vh; overflow-y: auto; background: white; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e5e7eb; padding: 20px 20px 16px;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 50px; height: 50px; background: #6366f1; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; font-weight: 700; overflow:hidden;">
                                            ${btn.dataset.userProfilePicture ? ('<img src="../../' + btn.dataset.userProfilePicture + '" alt="" style="width:50px;height:50px;object-fit:cover;display:block;">') : ( (btn.dataset.userFirst || 'U').charAt(0).toUpperCase() )}
                                        </div>
                                        <div>
                                            <h3 style="margin: 0; color: #111827; font-size: 18px;">${fullName || 'Unknown User'}</h3>
                                            <p style="margin: 4px 0 0 0; color: #6b7280; font-size: 13px;">User ID: ${userId}</p>
                                        </div>
                                    </div>
                                    <button onclick="closeViewUserModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280;">&times;</button>
                                </div>
                                
                                <div style="display: flex; gap: 8px; margin-bottom: 20px; padding: 0 20px;">
                                    ${statusDisplay}
                                    <span style="background: #e0e7ff; color: #4338ca; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">${roleDisplay}</span>
                                </div>
                                
                                <div style="background: #f9fafb; border-radius: 8px; padding: 16px; margin: 0 20px 16px;">
                                    <h4 style="margin: 0 0 12px 0; color: #374151; font-size: 14px; font-weight: 600;">👤 Account Information</h4>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                        <div>
                                            <p style="margin: 0; color: #6b7280; font-size: 12px;">Username</p>
                                            <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${btn.dataset.userUsername || '-'}</p>
                                        </div>
                                        <div>
                                            <p style="margin: 0; color: #6b7280; font-size: 12px;">Email</p>
                                            <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${btn.dataset.userEmail || '-'}</p>
                                        </div>
                                        <div>
                                            <p style="margin: 0; color: #6b7280; font-size: 12px;">Phone</p>
                                            <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${btn.dataset.userPhone || '-'}</p>
                                        </div>
                                        <div>
                                            <p style="margin: 0; color: #6b7280; font-size: 12px;">Address</p>
                                            <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${btn.dataset.userAddress || '-'}</p>
                                        </div>
                                    </div>
                                </div>

                                <div style="background: #f9fafb; border-radius: 8px; padding: 16px; margin: 0 20px 16px;">
                                    <h4 style="margin: 0 0 12px 0; color: #374151; font-size: 14px; font-weight: 600;">📝 Personal Information</h4>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                        <div>
                                            <p style="margin: 0; color: #6b7280; font-size: 12px;">First Name</p>
                                            <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${btn.dataset.userFirst || '-'}</p>
                                        </div>
                                        <div>
                                            <p style="margin: 0; color: #6b7280; font-size: 12px;">Middle Name</p>
                                            <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${btn.dataset.userMiddle || '-'}</p>
                                        </div>
                                        <div>
                                            <p style="margin: 0; color: #6b7280; font-size: 12px;">Last Name</p>
                                            <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${btn.dataset.userLast || '-'}</p>
                                        </div>
                                        <div>
                                            <p style="margin: 0; color: #6b7280; font-size: 12px;">Gender</p>
                                            <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${btn.dataset.userGender || '-'}</p>
                                        </div>
                                        <div>
                                            <p style="margin: 0; color: #6b7280; font-size: 12px;">Date of Birth</p>
                                            <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${btn.dataset.userDob || '-'}</p>
                                        </div>
                                        <div>
                                            <p style="margin: 0; color: #6b7280; font-size: 12px;">Role</p>
                                            <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${roleDisplay}</p>
                                        </div>
                                    </div>
                                </div>

                                <div style="background: #f9fafb; border-radius: 8px; padding: 16px; margin: 0 20px 16px;">
                                    <h4 style="margin: 0 0 12px 0; color: #374151; font-size: 14px; font-weight: 600;">📞 Emergency Contact</h4>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                        <div>
                                            <p style="margin: 0; color: #6b7280; font-size: 12px;">Contact Name</p>
                                            <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${btn.dataset.userEmergencyName || '-'}</p>
                                        </div>
                                        <div>
                                            <p style="margin: 0; color: #6b7280; font-size: 12px;">Contact Phone</p>
                                            <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${btn.dataset.userEmergencyPhone || '-'}</p>
                                        </div>
                                        <div style="grid-column: 1 / -1;">
                                            <p style="margin: 0; color: #6b7280; font-size: 12px;">Relationship</p>
                                            <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${btn.dataset.userEmergencyRelationship || '-'}</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="padding: 0 20px 20px;">
                                    <button onclick="closeViewUserModal()" style="width: 100%; padding: 12px; background: #f3f4f6; border: none; border-radius: 8px; color: #374151; font-size: 14px; font-weight: 600; cursor: pointer;">Close</button>
                                </div>
                            </div>
                        `;
                        
                        document.body.appendChild(overlay);
                        closeKebabDropdown();
                        
                        overlay.addEventListener('click', function(e) {
                            if (e.target === overlay) closeViewUserModal();
                        });
                    };

                    window.closeViewUserModal = function() {
                        const modal = document.getElementById('view-user-modal');
                        if (modal) modal.remove();
                    };

                    // Edit User
                    window.editUser = function(userId) {
                        closeKebabDropdown();
                        if (typeof openUserModal === 'function') {
                            openUserModal(userId);
                        }
                    };

                    // Open Add/Edit User Modal
                    window.openUserModal = function(userId) {
                        // If userId provided, populate from data attributes
                        const isEdit = !!userId;
                        let modal = document.getElementById('addUserModal');
                        if (!modal) {
                            // create modal HTML
                            modal = document.createElement('div');
                            modal.id = 'addUserModal';
                            modal.className = 'modal-overlay';
                            modal.style.display = 'none';
                            modal.innerHTML = `
                                <div class="modal user-modal au-modal">
                                    <div class="modal-header">
                                        <h3 id="addUserModalTitle">Add New User</h3>
                                        <button type="button" class="modal-close" aria-label="Close">&times;</button>
                                    </div>
                                    <form id="addUserForm">
                                        <div class="modal-body">
                                            <input type="hidden" name="id" id="au-id" />
                                            <div class="au-section-title">Personal Information</div>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>First Name</label>
                                                    <input class="form-control" type="text" name="first_name" id="au-first" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Middle Name</label>
                                                    <input class="form-control" type="text" name="middle_name" id="au-middle">
                                                </div>
                                                <div class="form-group">
                                                    <label>Last Name</label>
                                                    <input class="form-control" type="text" name="last_name" id="au-last" required>
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>Gender</label>
                                                    <select class="form-control" name="gender" id="au-gender"><option value="">Select</option><option value="male">Male</option><option value="female">Female</option></select>
                                                </div>
                                                <div class="form-group">
                                                    <label>Date of Birth</label>
                                                    <input class="form-control" type="date" name="date_of_birth" id="au-dob">
                                                </div>
                                                <div class="form-group" style="flex:0 0 90px;">
                                                    <label>Age</label>
                                                    <input class="form-control" type="number" id="au-age" readonly>
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>Phone</label>
                                                    <input class="form-control" type="tel" name="phone" id="au-phone">
                                                </div>
                                                <div class="form-group">
                                                    <label>Email</label>
                                                    <input class="form-control" type="email" name="email" id="au-email" placeholder="Optional for patients using mobile">
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label>Address</label>
                                                <input class="form-control" type="text" name="address" id="au-address">
                                            </div>
                                            <div class="au-section-title">Emergency Contact</div>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>Contact Name</label>
                                                    <input class="form-control" type="text" name="emergency_contact_name" id="au-em-name">
                                                </div>
                                                <div class="form-group">
                                                    <label>Contact Number</label>
                                                    <input class="form-control" type="tel" name="emergency_contact_phone" id="au-em-phone">
                                                </div>
                                                <div class="form-group">
                                                    <label>Relationship</label>
                                                    <input class="form-control" type="text" name="emergency_contact_relationship" id="au-em-relationship">
                                                </div>
                                            </div>
                                            <div class="au-section-title">Account</div>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>Username</label>
                                                    <input class="form-control" type="text" name="username" id="au-username" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Role</label>
                                                    <select class="form-control" name="role" id="au-role"><option value="admin">Admin</option><option value="staff">Staff</option><option value="dentist">Dentist</option><option value="patient">Patient</option></select>
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>Password</label>
                                                    <div class="input-group">
                                                        <input class="form-control" type="password" name="password" id="au-password" autocomplete="new-password">
                                                        <button type="button" class="toggle-password" data-target="au-password" aria-label="Toggle password visibility">
                                                            <svg class="eye-icon eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                            <svg class="eye-icon eye-closed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label>Confirm Password</label>
                                                    <div class="input-group">
                                                        <input class="form-control" type="password" name="confirm_password" id="au-confirm-password" autocomplete="new-password">
                                                        <button type="button" class="toggle-password" data-target="au-confirm-password" aria-label="Toggle password visibility">
                                                            <svg class="eye-icon eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                            <svg class="eye-icon eye-closed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-footer">
                                            <button type="submit" class="btn btn-primary" id="au-submit">Save</button>
                                        </div>
                                    </form>
                                </div>
                            `;
                            document.body.appendChild(modal);
                            // close handler
                            modal.querySelector('.modal-close').addEventListener('click', function(){ modal.style.display='none'; });
                            modal.addEventListener('click', function(e){ if (e.target === modal) modal.style.display='none'; });
                            // password visibility toggles
                            modal.querySelectorAll('.toggle-password').forEach(function(btn){
                                btn.addEventListener('click', function(){
                                    const input = document.getElementById(btn.dataset.target);
                                    if (!input) return;
                                    const show = input.type === 'password';
                                    input.type = show ? 'text' : 'password';
                                    btn.querySelector('.eye-open').style.display = show ? 'none' : '';
                                    btn.querySelector('.eye-closed').style.display = show ? '' : 'none';
                                });
                            });
                            // age calc
                            const dob = modal.querySelector('#au-dob'); const age = modal.querySelector('#au-age');
                            if (dob) dob.addEventListener('change', function(){ if (dob.value){ const d=new Date(dob.value); let a=new Date().getFullYear()-d.getFullYear(); if (new Date().getMonth()<d.getMonth()||(new Date().getMonth()==d.getMonth()&&new Date().getDate()<d.getDate())) a--; age.value = a>0&&a<120?a:''; } });
                            // submit handler - saves via admin_users_actions.php
                            modal.querySelector('#addUserForm').addEventListener('submit', function(e){
                                e.preventDefault();
                                const form = e.target;
                                const submitBtn = form.querySelector('#au-submit');
                                const userId = (form.querySelector('#au-id').value || '').trim();
                                const isEdit = userId !== '';

                                const val = function(name){ const el = form.querySelector('[name="' + name + '"]'); return el ? (el.value || '').trim() : ''; };
                                const genderRaw = val('gender').toLowerCase();

                                const params = new URLSearchParams();
                                params.set('action', isEdit ? 'update' : 'create');
                                if (isEdit) params.set('id', userId);
                                params.set('firstName', val('first_name'));
                                params.set('middleName', val('middle_name'));
                                params.set('lastName', val('last_name'));
                                params.set('email', val('email'));
                                params.set('phoneNumber', val('phone'));
                                params.set('gender', genderRaw === 'male' ? 'Male' : genderRaw === 'female' ? 'Female' : '');
                                params.set('dateOfBirth', val('date_of_birth'));
                                params.set('role', val('role') || 'patient');
                                params.set('status', 'active');
                                params.set('username', val('username'));
                                params.set('password', val('password'));
                                params.set('confirmPassword', val('confirm_password'));

                                if (!isEdit && params.get('password') !== '' && params.get('password') !== params.get('confirmPassword')) {
                                    alert('Passwords do not match.');
                                    return;
                                }

                                if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving...'; }

                                fetch('admin_users_actions.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: params.toString()
                                })
                                .then(res => res.json())
                                .then(data => {
                                    if (data.success) {
                                        (window.appCache ? window.appCache.refreshCurrent() : (window.location.reload.bind(window))());
                                    } else {
                                        alert(data.message || 'Failed to save user.');
                                        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Save'; }
                                    }
                                })
                                .catch(() => {
                                    alert('Error saving user. Please try again.');
                                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Save'; }
                                });
                            });
                        }

                        // populate when editing
                        if (isEdit) {
                            const btn = document.querySelector(`.user-kebab-btn[data-user-id="${userId}"]`);
                            if (!btn) return;
                            modal.querySelector('#addUserModalTitle').textContent = 'Edit User';
                            modal.querySelector('#au-id').value = userId;
                            modal.querySelector('#au-first').value = btn.dataset.userFirst || '';
                            modal.querySelector('#au-middle').value = btn.dataset.userMiddle || '';
                            modal.querySelector('#au-last').value = btn.dataset.userLast || '';
                            modal.querySelector('#au-phone').value = btn.dataset.userPhone || '';
                            modal.querySelector('#au-gender').value = btn.dataset.userGender || '';
                            modal.querySelector('#au-dob').value = btn.dataset.userDob || '';
                            modal.querySelector('#au-address').value = btn.dataset.userAddress || '';
                            modal.querySelector('#au-email').value = btn.dataset.userEmail || '';
                            modal.querySelector('#au-em-phone').value = btn.dataset.userEmergencyPhone || '';
                            modal.querySelector('#au-em-name').value = btn.dataset.userEmergencyName || '';
                            modal.querySelector('#au-em-relationship').value = btn.dataset.userEmergencyRelationship || '';
                            modal.querySelector('#au-username').value = btn.dataset.userUsername || '';
                            modal.querySelector('#au-role').value = (btn.dataset.userRole || 'patient').toLowerCase();
                        } else {
                            modal.querySelector('#addUserModalTitle').textContent = 'Add New User';
                            modal.querySelector('#addUserForm').reset();
                            modal.querySelectorAll('.toggle-password').forEach(function(btn){
                                const input = document.getElementById(btn.dataset.target);
                                if (input) input.type = 'password';
                                btn.querySelector('.eye-open').style.display = '';
                                btn.querySelector('.eye-closed').style.display = 'none';
                            });
                            modal.querySelector('#au-id').value = '';
                            modal.querySelector('#au-age').value = '';
                        }

                        modal.style.display = 'flex';
                        const firstInput = modal.querySelector('#au-first'); if (firstInput) firstInput.focus();
                    };

// Toggle User Status
                    window.toggleUserStatus = function(userId, newStatus) {
                        closeKebabDropdown();
                        window.appAsk('Are you sure you want to ' + (newStatus === 'active' ? 'activate' : 'deactivate') + ' this user?', function() {
                        fetch('admin_users_actions.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=toggle_status&id=' + userId + '&status=' + newStatus
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                (window.appCache ? window.appCache.refreshCurrent() : (window.location.reload.bind(window))());
                            } else {
                                alert(data.message || 'Failed to update status');
                            }
                        })
                        .catch(() => {
                            alert('Error updating user status');
                        });
                        }, { title: 'Please Confirm' });
                    };
                });
                </script>

                <!-- Pagination -->
                <div class="pagination">
                    <span class="pagination-info" id="usersPaginationInfo">Showing 0 of 0 users</span>
                    <div class="pagination-buttons" id="usersPaginationButtons"></div>
                </div>
            </div>

<?php
require_once __DIR__ . '/../../includes/admin_layout_end.php';
?>
    