<?php
/**
 * User Management - Admin page for managing system users
 */

$pageTitle = 'User Management';

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/admin_layout_start.php';

$users = [];
$totalUsers = 0;

try {
    // Get users with role names from roles table
    // If role_id is NULL, treat as Patient
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.first_name, u.middle_name, u.last_name, u.email, u.status, u.is_archived,
               u.role_id, u.last_active,
               CASE 
                   WHEN u.role_id IS NULL THEN 'Patient'
                   ELSE r.role_name
               END as role_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        ORDER BY u.id ASC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as &$user) {
        if (!isset($user['status']) || $user['status'] === null || $user['status'] === '') {
            $user['status'] = 'Active';
        }
    }
    unset($user);
    $totalUsers = count($users);
} catch (Exception $e) {
    $users = [];
    $totalUsers = 0;
}
?>
            <div class="content-main">
                <!-- Page Header -->
                <div class="page-header">
                    <div>
                        <h2>User Management</h2>
                        <p class="page-subtitle">Total Users: <strong><?php echo $totalUsers; ?></strong></p>
                    </div>
                    <button class="btn-primary" onclick="openUserModal()">+ Add New User</button>
                </div>

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
                </div>

                <!-- Users Table -->
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
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
                            <?php foreach ($users as $user): ?>
                            <?php
                                $username = $user['username'] ?? '';
                                $firstName = $user['first_name'] ?? '';
                                $middleName = $user['middle_name'] ?? '';
                                $lastName = $user['last_name'] ?? '';
                                $email = $user['email'] ?? '';
                            ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($username); ?></td>
                                <td><?php echo htmlspecialchars($firstName); ?></td>
                                <td><?php echo htmlspecialchars($middleName); ?></td>
                                <td><?php echo htmlspecialchars($lastName); ?></td>
                                <td><?php echo htmlspecialchars($email); ?></td>
                                <td><span class="role-badge <?php echo strtolower($user['role_name'] ?? ''); ?>"><?php echo htmlspecialchars($user['role_name'] ?? 'N/A'); ?></span></td>
                                <td><span class="status-badge <?php echo strtolower($user['status']); ?>"><?php 
                                    $lastActive = $user['last_active'] ?? null;
                                    if ($lastActive) {
                                        // Output placeholder for JS to update
                                        ?><span class="user-status" data-last-active="<?php echo htmlspecialchars($lastActive); ?>">checking...</span><?php
                                    } else {
                                        // No last_active - show as offline with styling
                                        ?><span style="color:#dc2626;font-weight:bold;">Offline</span><?php
                                    }
                                    ?></span></td>
                                <td class="action-buttons">
                                    <button
                                        class="user-kebab-btn"
                                        type="button"
                                        data-user-id="<?php echo (int)$user['id']; ?>"
                                        data-user-status="<?php echo htmlspecialchars($user['status']); ?>"
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
                        background: #fff;
                        border: 1px solid #e5e7eb;
                        border-radius: 10px;
                        box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.2);
                        min-width: 170px;
                        z-index: 10000;
                        overflow: hidden;
                    }

                    .user-kebab-dropdown.show {
                        display: block;
                        animation: fadeInKebab 0.16s ease;
                    }

                    .user-kebab-dropdown a {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        padding: 10px 14px;
                        color: #374151;
                        text-decoration: none;
                        font-size: 0.95rem;
                        transition: background 0.15s ease, color 0.15s ease;
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

                    @keyframes fadeInKebab {
                        from { opacity: 0; transform: translateY(-4px); }
                        to { opacity: 1; transform: translateY(0); }
                    }
                </style>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Get timezone offset between browser and server
                    const serverTime = new Date("<?php echo date('Y-m-d H:i:s'); ?>");
                    const clientTime = new Date();
                    const timeOffset = (serverTime - clientTime) / 60000; // offset in minutes
                    
                    function getTimeAgo(timestamp) {
                        if (!timestamp) return '';
                        const serverTs = new Date(timestamp);
                        const now = new Date();
                        const adjustedNow = new Date(now.getTime() + timeOffset * 60000);
                        const diffMs = adjustedNow - serverTs;
                        const diffSec = Math.floor(diffMs / 1000);
                        const diffMin = Math.floor(diffSec / 60);
                        const diffHour = Math.floor(diffMin / 60);
                        const diffDay = Math.floor(diffHour / 24);
                        if (diffSec < 60) return 'just now';
                        if (diffMin < 60) return diffMin + 'm ago';
                        if (diffHour < 24) return diffHour + 'h ago';
                        if (diffDay < 7) return diffDay + 'd ago';
                        return Math.floor(diffDay / 365) + 'y ago';
                    }
                    
                    function updateStatuses() {
                        document.querySelectorAll('.user-status[data-last-active]').forEach(function(el) {
                            const lastActive = el.dataset.lastActive;
                            if (lastActive) {
                                const ago = getTimeAgo(lastActive);
                                if (ago === 'just now' || ago.endsWith('m ago')) {
                                    el.textContent = 'Active';
                                    el.style.color = '#065f46';
                                } else {
                                    el.textContent = ago;
                                    el.style.color = '#6b7280';
                                }
                            }
                        });
                    }
                    
                    updateStatuses();
                    setInterval(updateStatuses, 30000);
                });
                </script>

                <!-- Pagination -->
                <div class="pagination">
                    <span class="pagination-info">Showing 1-<?php echo $totalUsers; ?> of <?php echo $totalUsers; ?> users</span>
                    <div class="pagination-buttons">
                        <button class="pagination-btn" disabled>Previous</button>
                        <button class="pagination-btn active">1</button>
                        <button class="pagination-btn">2</button>
                        <button class="pagination-btn">3</button>
                        <button class="pagination-btn">Next</button>
                    </div>
                </div>
            </div>

<?php
require_once __DIR__ . '/../../includes/admin_layout_end.php';
?>
