<?php
/**
 * Audit Trail - Admin page for viewing system activity logs
 */

$pageTitle = 'Audit Trail';

require_once __DIR__ . '/includes/admin_layout_start.php';
?>
            <div class="content-main">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Audit Trail</h2>
                    <p class="page-subtitle">Track all system activities and user actions</p>
                </div>

                <!-- Search and Filter -->
                <div class="search-filters">
                    <input type="text" class="search-input" placeholder="Search audit logs..." id="auditSearch">
                    <select class="filter-select" id="actionFilter">
                        <option value="">All Actions</option>
                        <option value="login">Login/Logout</option>
                        <option value="create">Create</option>
                        <option value="update">Update</option>
                        <option value="delete">Delete</option>
                        <option value="payment">Payment</option>
                    </select>
                    <select class="filter-select" id="userFilter">
                        <option value="">All Users</option>
                        <option value="admin">Admin</option>
                        <option value="dentist">Dentist</option>
                        <option value="staff">Staff</option>
                    </select>
                    <input type="date" class="date-input" id="dateFilter">
                </div>

                <!-- Audit Log Table -->
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody id="auditTableBody">
                            <!-- Sample audit log data -->
                            <tr>
                                <td>2024-01-12 14:32:15</td>
                                <td>Administrator</td>
                                <td><span class="role-badge admin">Admin</span></td>
                                <td><span class="action-badge login">Login</span></td>
                                <td>Successful login from desktop</td>
                                <td>192.168.1.100</td>
                            </tr>
                            <tr>
                                <td>2024-01-12 14:35:22</td>
                                <td>Dr. Rex</td>
                                <td><span class="role-badge dentist">Dentist</span></td>
                                <td><span class="action-badge create">Create</span></td>
                                <td>Created new patient record: Maria Santos</td>
                                <td>192.168.1.101</td>
                            </tr>
                            <tr>
                                <td>2024-01-12 14:40:45</td>
                                <td>Staff Member</td>
                                <td><span class="role-badge staff">Staff</span></td>
                                <td><span class="action-badge update">Update</span></td>
                                <td>Updated appointment status: #A001 to Completed</td>
                                <td>192.168.1.102</td>
                            </tr>
                            <tr>
                                <td>2024-01-12 14:45:10</td>
                                <td>Administrator</td>
                                <td><span class="role-badge admin">Admin</span></td>
                                <td><span class="action-badge create">Create</span></td>
                                <td>Created new user: juan_dela (Staff)</td>
                                <td>192.168.1.100</td>
                            </tr>
                            <tr>
                                <td>2024-01-12 14:50:33</td>
                                <td>Dr. Rex</td>
                                <td><span class="role-badge dentist">Dentist</span></td>
                                <td><span class="action-badge payment">Payment</span></td>
                                <td>Recorded payment ₱5,000 for INV-001</td>
                                <td>192.168.1.101</td>
                            </tr>
                            <tr>
                                <td>2024-01-12 15:00:00</td>
                                <td>Staff Member</td>
                                <td><span class="role-badge staff">Staff</span></td>
                                <td><span class="action-badge delete">Delete</span></td>
                                <td>Deleted reminder item #12</td>
                                <td>192.168.1.102</td>
                            </tr>
                            <tr>
                                <td>2024-01-12 15:15:22</td>
                                <td>Administrator</td>
                                <td><span class="role-badge admin">Admin</span></td>
                                <td><span class="action-badge update">Update</span></td>
                                <td>Modified system settings: Queue notifications</td>
                                <td>192.168.1.100</td>
                            </tr>
                            <tr>
                                <td>2024-01-12 15:30:45</td>
                                <td>Dr. Rex</td>
                                <td><span class="role-badge dentist">Dentist</span></td>
                                <td><span class="action-badge login">Logout</span></td>
                                <td>User logged out</td>
                                <td>192.168.1.101</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination">
                    <span class="pagination-info">Showing 1-8 of 1,245 log entries</span>
                    <div class="pagination-buttons">
                        <button class="pagination-btn" disabled>Previous</button>
                        <button class="pagination-btn active">1</button>
                        <button class="pagination-btn">2</button>
                        <button class="pagination-btn">156</button>
                        <button class="pagination-btn">Next</button>
                    </div>
                </div>

                <!-- Export Options -->
                <div class="section-card">
                    <h2 class="section-title">📥 Export Audit Logs</h2>
                    <div class="export-options">
                        <button class="btn-secondary" onclick="exportAuditLogs('csv')">📄 Export as CSV</button>
                        <button class="btn-secondary" onclick="exportAuditLogs('pdf')">📄 Export as PDF</button>
                        <button class="btn-secondary" onclick="exportAuditLogs('excel')">📊 Export as Excel</button>
                    </div>
                </div>
            </div>

<?php
require_once __DIR__ . '/includes/admin_layout_end.php';
?>
