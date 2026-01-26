<?php
/**
 * User Management - Admin page for managing system users
 */

$pageTitle = 'User Management';

$users = [
    ['id' => 1, 'username' => 'admin', 'fullName' => 'Administrator', 'email' => 'admin@rfclinic.com', 'role' => 'admin', 'status' => 'active'],
    ['id' => 2, 'username' => 'dentist', 'fullName' => 'Dr. Rex', 'email' => 'dentist@rfclinic.com', 'role' => 'dentist', 'status' => 'active'],
    ['id' => 3, 'username' => 'staff', 'fullName' => 'Staff', 'email' => 'staff@rfclinic.com', 'role' => 'staff', 'status' => 'active'],
    ['id' => 4, 'username' => 'juan_dela', 'fullName' => 'Juan Dela Cruz', 'email' => 'juan@email.com', 'role' => 'staff', 'status' => 'inactive'],
];
$totalUsers = count($users);

require_once __DIR__ . '/includes/admin_layout_start.php';
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
                    </select>
                </div>

                <!-- Users Table -->
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['fullName']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><span class="role-badge <?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                                <td><span class="status-badge <?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                                <td class="action-buttons">
                                    <button class="action-btn icon" title="Edit">✏️</button>
                                    <button class="action-btn icon" title="Delete">🗑️</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

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
require_once __DIR__ . '/includes/admin_layout_end.php';
?>
