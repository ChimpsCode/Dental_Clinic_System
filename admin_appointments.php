<?php
/**
 * Appointments - Admin page for viewing and managing all appointments
 */

$pageTitle = 'Appointments';

require_once __DIR__ . '/includes/admin_layout_start.php';
?>
            <div class="content-main">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Appointments Management</h2>
                    <button class="btn-primary" onclick="exportAppointments()">📥 Export Report</button>
                </div>

                <!-- Stats Cards -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon blue">📅</div>
                        <div class="summary-info">
                            <h3>12</h3>
                            <p>Today's Appointments</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon green">✓</div>
                        <div class="summary-info">
                            <h3>8</h3>
                            <p>Completed</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon yellow">⏳</div>
                        <div class="summary-info">
                            <h3>3</h3>
                            <p>Pending</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon red">❌</div>
                        <div class="summary-info">
                            <h3>1</h3>
                            <p>Cancelled</p>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="search-filters">
                    <input type="text" class="search-input" placeholder="Search appointments..." id="appointmentSearch">
                    <select class="filter-select" id="dateFilter">
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="all">All Time</option>
                    </select>
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <!-- Appointments Table -->
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Patient</th>
                                <th>Service</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="appointmentsTableBody">
                            <!-- Sample data -->
                            <tr>
                                <td>A001</td>
                                <td>Maria Santos</td>
                                <td>Root Canal</td>
                                <td>2024-01-12</td>
                                <td>09:00 AM</td>
                                <td><span class="status-badge completed">Completed</span></td>
                                <td class="action-buttons">
                                    <button class="action-btn icon" title="View">👁️</button>
                                    <button class="action-btn icon" title="Edit">✏️</button>
                                </td>
                            </tr>
                            <tr>
                                <td>A002</td>
                                <td>Roberto Garcia</td>
                                <td>Denture Adjustment</td>
                                <td>2024-01-12</td>
                                <td>10:30 AM</td>
                                <td><span class="status-badge pending">Pending</span></td>
                                <td class="action-buttons">
                                    <button class="action-btn icon" title="View">👁️</button>
                                    <button class="action-btn icon" title="Edit">✏️</button>
                                </td>
                            </tr>
                            <tr>
                                <td>A003</td>
                                <td>Ana Reyes</td>
                                <td>Oral Prophylaxis</td>
                                <td>2024-01-12</td>
                                <td>11:00 AM</td>
                                <td><span class="status-badge cancelled">Cancelled</span></td>
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
                    <span class="pagination-info">Showing 1-3 of 45 appointments</span>
                    <div class="pagination-buttons">
                        <button class="pagination-btn" disabled>Previous</button>
                        <button class="pagination-btn active">1</button>
                        <button class="pagination-btn">2</button>
                        <button class="pagination-btn">15</button>
                        <button class="pagination-btn">Next</button>
                    </div>
                </div>
            </div>

<?php
require_once __DIR__ . '/includes/admin_layout_end.php';
?>
