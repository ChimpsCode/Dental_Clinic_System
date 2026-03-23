<?php
/**
 * Billing - Admin page for viewing billing and payments (Simple Paid/Unpaid status)
 */

$pageTitle = 'Billing';

require_once __DIR__ . '/../../includes/admin_layout_start.php';
?>
            <div class="content-main">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Billing Overview</h2>
                    <button class="btn-primary" onclick="exportBilling()">📥 Export Report</button>
                </div>

                <!-- Stats Cards -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon green">💰</div>
                        <div class="summary-info">
                            <h3>₱45,000</h3>
                            <p>Total Collected</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon yellow">⏳</div>
                        <div class="summary-info">
                            <h3>₱8,500</h3>
                            <p>Pending Payments</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon blue">📋</div>
                        <div class="summary-info">
                            <h3>25</h3>
                            <p>Total Transactions</p>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon red">❌</div>
                        <div class="summary-info">
                            <h3>2</h3>
                            <p>Overdue</p>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="search-filters">
                    <input type="text" class="search-input" placeholder="Search by patient name or invoice..." id="billingSearch">
                    <select class="filter-select" id="paymentStatus">
                        <option value="">All Status</option>
                        <option value="paid">Paid</option>
                        <option value="unpaid">Unpaid</option>
                    </select>
                    <select class="filter-select" id="dateRange">
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="all">All Time</option>
                    </select>
                </div>

                <!-- Billing Table -->
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Patient</th>
                                <th>Services</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="billingTableBody">
                            <!-- Sample data -->
                            <tr>
                                <td>INV-001</td>
                                <td>Maria Santos</td>
                                <td>Root Canal, X-Ray</td>
                                <td>₱5,000</td>
                                <td>2024-01-10</td>
                                <td>2024-01-17</td>
                                <td><span class="status-badge paid">Paid</span></td>
                                <td class="action-buttons">
                                    <button class="action-btn icon" title="View">👁️</button>
                                    <button class="action-btn icon" title="Print">🖨️</button>
                                </td>
                            </tr>
                            <tr>
                                <td>INV-002</td>
                                <td>Roberto Garcia</td>
                                <td>Denture Adjustment</td>
                                <td>₱1,500</td>
                                <td>2024-01-11</td>
                                <td>2024-01-18</td>
                                <td><span class="status-badge unpaid">Unpaid</span></td>
                                <td class="action-buttons">
                                    <button class="action-btn icon" title="View">👁️</button>
                                    <button class="action-btn success" title="Mark Paid">✓</button>
                                </td>
                            </tr>
                            <tr>
                                <td>INV-003</td>
                                <td>Ana Reyes</td>
                                <td>Oral Prophylaxis</td>
                                <td>₱2,000</td>
                                <td>2024-01-08</td>
                                <td>2024-01-15</td>
                                <td><span class="status-badge unpaid overdue">Overdue</span></td>
                                <td class="action-buttons">
                                    <button class="action-btn icon" title="View">👁️</button>
                                    <button class="action-btn success" title="Mark Paid">✓</button>
                                    <button class="action-btn icon" title="Send Reminder">📧</button>
                                </td>
                            </tr>
                            <tr>
                                <td>INV-004</td>
                                <td>Juan Dela Cruz</td>
                                <td>Tooth Extraction</td>
                                <td>₱3,000</td>
                                <td>2024-01-12</td>
                                <td>2024-01-19</td>
                                <td><span class="status-badge paid">Paid</span></td>
                                <td class="action-buttons">
                                    <button class="action-btn icon" title="View">👁️</button>
                                    <button class="action-btn icon" title="Print">🖨️</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination">
                    <span class="pagination-info">Showing 1-4 of 25 transactions</span>
                    <div class="pagination-buttons">
                        <button class="pagination-btn" disabled>Previous</button>
                        <button class="pagination-btn active">1</button>
                        <button class="pagination-btn">2</button>
                        <button class="pagination-btn">7</button>
                        <button class="pagination-btn">Next</button>
                    </div>
                </div>
            </div>

<?php
require_once __DIR__ . '/../../includes/admin_layout_end.php';
?>
