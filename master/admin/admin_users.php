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
               u.role_id, u.last_active, u.last_logout, u.is_logged_in,
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
                            ?>
                            <tr>
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

                    @keyframes fadeInKebab {
                        from { opacity: 0; transform: translateY(-4px); }
                        to { opacity: 1; transform: translateY(0); }
                    }
                </style>

<script>
                document.addEventListener('DOMContentLoaded', function() {
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
                    const kebabDropdown = document.getElementById('userKebabDropdown');
                    const kebabBackdrop = document.getElementById('userKebabBackdrop');
                    let activeKebabBtn = null;

                    function getUserMenuItems(userId, status) {
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
                            <a href="javascript:void(0)" class="danger" onclick="deleteUser(${userId})">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                </svg>
                                Delete
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
                        const dropdownHeight = 220;
                        
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
                        const userId = button.dataset.userId;
                        const status = button.dataset.userStatus;
                        
                        kebabDropdown.innerHTML = getUserMenuItems(userId, status);
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

                    // Kebab button click handler
                    document.addEventListener('click', function(e) {
                        const button = e.target.closest('.user-kebab-btn');
                        if (button) {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            if (activeKebabBtn === button && kebabDropdown.classList.contains('show')) {
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
                    });

                    // ESC key to close
                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape') closeKebabDropdown();
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
    <div style="width: 100%; max-width: 500px; margin: 0 20px; max-height: 90vh; overflow-y: auto; background: white; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e5e7eb; padding: 20px 20px 16px;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 50px; height: 50px; background: #6366f1; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; font-weight: 700;">
                                            ${(btn.dataset.userFirst || 'U').charAt(0).toUpperCase()}
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
                                            <p style="margin: 0; color: #6b7280; font-size: 12px;">Role</p>
                                            <p style="margin: 4px 0 0 0; color: #111827; font-size: 14px; font-weight: 500;">${roleDisplay}</p>
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

// Toggle User Status
                    window.toggleUserStatus = function(userId, newStatus) {
                        closeKebabDropdown();
                        if (!confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this user?`)) return;
                        
                        fetch('admin_users_actions.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=toggle_status&id=' + userId + '&status=' + newStatus
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert(data.message || 'Failed to update status');
                            }
                        })
                        .catch(() => {
                            alert('Error updating user status');
                        });
                    };
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
    