<?php
/**
 * User Management - Admin page for managing system users
 */

$pageTitle = 'User Management';

require_once __DIR__ . '/includes/admin_layout_start.php';
?>
            <div class="content-main">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>User Management</h2>
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
                            <!-- Sample data - replace with database queries -->
                            <tr>
                                <td>1</td>
                                <td>admin</td>
                                <td>Administrator</td>
                                <td>admin@rfclinic.com</td>
                                <td><span class="role-badge admin">Admin</span></td>
                                <td><span class="status-badge active">Active</span></td>
                                <td class="action-buttons">
                                    <button class="action-btn icon" title="Edit">✏️</button>
                                    <button class="action-btn icon" title="Delete">🗑️</button>
                                </td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>dentist</td>
                                <td>Dr. Rex</td>
                                <td>dentist@rfclinic.com</td>
                                <td><span class="role-badge dentist">Dentist</span></td>
                                <td><span class="status-badge active">Active</span></td>
                                <td class="action-buttons">
                                    <button class="action-btn icon" title="Edit">✏️</button>
                                    <button class="action-btn icon" title="Delete">🗑️</button>
                                </td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td>staff</td>
                                <td>Staff Member</td>
                                <td>staff@rfclinic.com</td>
                                <td><span class="role-badge staff">Staff</span></td>
                                <td><span class="status-badge active">Active</span></td>
                                <td class="action-buttons">
                                    <button class="action-btn icon" title="Edit">✏️</button>
                                    <button class="action-btn icon" title="Delete">🗑️</button>
                                </td>
                            </tr>
                            <tr>
                                <td>4</td>
                                <td>juan_dela</td>
                                <td>Juan Dela Cruz</td>
                                <td>juan@email.com</td>
                                <td><span class="role-badge staff">Staff</span></td>
                                <td><span class="status-badge inactive">Inactive</span></td>
                                <td class="action-buttons">
                                    <button class="action-btn icon" title="Edit">✏️</button>
                                    <button class="action-btn icon" title="Delete">🗑️</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination">
                    <span class="pagination-info">Showing 1-4 of 15 users</span>
                    <div class="pagination-buttons">
                        <button class="pagination-btn" disabled>Previous</button>
                        <button class="pagination-btn active">1</button>
                        <button class="pagination-btn">2</button>
                        <button class="pagination-btn">3</button>
                        <button class="pagination-btn">Next</button>
                    </div>
                </div>
            </div>

            <!-- Add/Edit User Modal -->
            <div id="userModal" class="modal-overlay">
                <div class="modal">
                    <h2 id="modalTitle">Add New User</h2>
                    
                    <form id="userForm">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" id="username" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" id="fullName" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" id="email" class="form-control" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label>Role</label>
                                <select id="role" class="form-control" required>
                                    <option value="staff">Staff</option>
                                    <option value="dentist">Dentist</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>Status</label>
                                <select id="status" class="form-control">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" id="password" class="form-control">
                            <small>Leave blank to keep existing password</small>
                        </div>
                        
                        <div class="modal-actions">
                            <button type="button" class="btn-cancel" onclick="closeUserModal()">Cancel</button>
                            <button type="submit" class="btn-primary">Save User</button>
                        </div>
                    </form>
                </div>
            </div>

<?php
require_once __DIR__ . '/includes/admin_layout_end.php';
?>
