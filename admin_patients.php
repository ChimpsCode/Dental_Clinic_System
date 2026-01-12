<?php
/**
 * Patient Records - Admin page for viewing all patient records
 */

$pageTitle = 'Patient Records';

require_once __DIR__ . '/includes/admin_layout_start.php';
?>
            <div class="content-main">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Patient Records</h2>
                    <button class="btn-primary" onclick="exportPatients()">📥 Export CSV</button>
                </div>

                <!-- Search and Filter -->
                <div class="search-filters">
                    <input type="text" class="search-input" placeholder="Search by name, ID, or phone..." id="patientSearch">
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <!-- Patients Table -->
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Date of Birth</th>
                                <th>Last Visit</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="patientsTableBody">
                            <!-- Sample data - replace with database queries -->
                            <tr>
                                <td>P001</td>
                                <td>Maria Santos</td>
                                <td>0912-345-6789</td>
                                <td>maria@email.com</td>
                                <td>1985-03-15</td>
                                <td>2024-01-10</td>
                                <td class="action-buttons">
                                    <button class="action-btn icon" title="View">👁️</button>
                                    <button class="action-btn icon" title="Edit">✏️</button>
                                </td>
                            </tr>
                            <tr>
                                <td>P002</td>
                                <td>Roberto Garcia</td>
                                <td>0918-765-4321</td>
                                <td>roberto@email.com</td>
                                <td>1990-07-22</td>
                                <td>2024-01-08</td>
                                <td class="action-buttons">
                                    <button class="action-btn icon" title="View">👁️</button>
                                    <button class="action-btn icon" title="Edit">✏️</button>
                                </td>
                            </tr>
                            <tr>
                                <td>P003</td>
                                <td>Ana Reyes</td>
                                <td>0922-111-2233</td>
                                <td>ana@email.com</td>
                                <td>1978-11-30</td>
                                <td>2024-01-05</td>
                                <td class="action-buttons">
                                    <button class="action-btn icon" title="View">👁️</button>
                                    <button class="action-btn icon" title="Edit">✏️</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination">
                    <span class="pagination-info">Showing 1-3 of 150 patients</span>
                    <div class="pagination-buttons">
                        <button class="pagination-btn" disabled>Previous</button>
                        <button class="pagination-btn active">1</button>
                        <button class="pagination-btn">2</button>
                        <button class="pagination-btn">50</button>
                        <button class="pagination-btn">Next</button>
                    </div>
                </div>
            </div>

<?php
require_once __DIR__ . '/includes/admin_layout_end.php';
?>
