<?php
/**
 * Reports - Admin page for printable reports
 */

$pageTitle = 'Reports';

require_once __DIR__ . '/includes/admin_layout_start.php';
?>
            <div class="content-main">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Reports</h2>
                    <p class="page-subtitle">Generate and print printable reports</p>
                </div>

                <!-- Report Types Grid -->
                <div class="reports-grid">
                    <!-- Patient Report -->
                    <div class="report-card">
                        <div class="report-icon">👥</div>
                        <h3>Patient Report</h3>
                        <p>Complete list of all registered patients with contact information and visit history.</p>
                        <div class="report-actions">
                            <button class="btn-secondary" onclick="previewReport('patients')">Preview</button>
                            <button class="btn-primary" onclick="printReport('patients')">🖨️ Print</button>
                        </div>
                    </div>

                    <!-- Appointments Report -->
                    <div class="report-card">
                        <div class="report-icon">📅</div>
                        <h3>Appointments Report</h3>
                        <p>Detailed appointments log including completed, pending, and cancelled appointments.</p>
                        <div class="report-actions">
                            <button class="btn-secondary" onclick="previewReport('appointments')">Preview</button>
                            <button class="btn-primary" onclick="printReport('appointments')">🖨️ Print</button>
                        </div>
                    </div>

                    <!-- Billing Report -->
                    <div class="report-card">
                        <div class="report-icon">💰</div>
                        <h3>Billing Report</h3>
                        <p>Financial summary including all transactions, payments received, and pending amounts.</p>
                        <div class="report-actions">
                            <button class="btn-secondary" onclick="previewReport('billing')">Preview</button>
                            <button class="btn-primary" onclick="printReport('billing')">🖨️ Print</button>
                        </div>
                    </div>

                    <!-- Revenue Report -->
                    <div class="report-card">
                        <div class="report-icon">📈</div>
                        <h3>Revenue Report</h3>
                        <p>Revenue breakdown by service type, time period, and payment status.</p>
                        <div class="report-actions">
                            <button class="btn-secondary" onclick="previewReport('revenue')">Preview</button>
                            <button class="btn-primary" onclick="printReport('revenue')">🖨️ Print</button>
                        </div>
                    </div>

                    <!-- Services Report -->
                    <div class="report-card">
                        <div class="report-icon">🦷</div>
                        <h3>Services Report</h3>
                        <p>Summary of services rendered, frequency, and revenue by service type.</p>
                        <div class="report-actions">
                            <button class="btn-secondary" onclick="previewReport('services')">Preview</button>
                            <button class="btn-primary" onclick="printReport('services')">🖨️ Print</button>
                        </div>
                    </div>

                    <!-- Daily Summary -->
                    <div class="report-card">
                        <div class="report-icon">📊</div>
                        <h3>Daily Summary</h3>
                        <p>Day-by-day summary of patients, appointments, and revenue.</p>
                        <div class="report-actions">
                            <button class="btn-secondary" onclick="previewReport('daily')">Preview</button>
                            <button class="btn-primary" onclick="printReport('daily')">🖨️ Print</button>
                        </div>
                    </div>
                </div>

                <!-- Report Generator -->
                <div class="section-card">
                    <h2 class="section-title">📋 Custom Report Generator</h2>
                    <div class="report-form">
                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label>Report Type</label>
                                <select id="customReportType" class="form-control">
                                    <option value="patients">Patient Report</option>
                                    <option value="appointments">Appointments Report</option>
                                    <option value="billing">Billing Report</option>
                                    <option value="revenue">Revenue Report</option>
                                </select>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>Date Range</label>
                                <select id="dateRange" class="form-control">
                                    <option value="today">Today</option>
                                    <option value="week">This Week</option>
                                    <option value="month" selected>This Month</option>
                                    <option value="custom">Custom Range</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button class="btn-secondary" onclick="generateCustomReport()">Generate Report</button>
                            <button class="btn-primary" onclick="printCustomReport()">🖨️ Print Report</button>
                            <button class="btn-secondary" onclick="exportToPDF()">📄 Export PDF</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Print Preview Modal -->
            <div id="printPreviewModal" class="modal-overlay">
                <div class="modal" style="max-width: 800px;">
                    <div class="modal-header">
                        <h2>Report Preview</h2>
                        <div class="modal-actions">
                            <button class="btn-secondary" onclick="closePreviewModal()">Close</button>
                            <button class="btn-primary" onclick="printCurrentReport()">🖨️ Print</button>
                        </div>
                    </div>
                    <div class="print-preview-content" id="printPreviewContent">
                        <!-- Report content will be loaded here -->
                    </div>
                </div>
            </div>

<?php
require_once __DIR__ . '/includes/admin_layout_end.php';
?>
