<?php
/**
 * Analytics - Admin page for viewing clinic analytics and statistics
 * 
 * sample text
 */

$pageTitle = 'Analytics';

require_once __DIR__ . '/includes/admin_layout_start.php';
?>
            <div class="content-main">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Analytics Dashboard</h2>
                    <select class="filter-select" id="analyticsPeriod">
                        <option value="week">This Week</option>
                        <option value="month" selected>This Month</option>
                        <option value="quarter">This Quarter</option>
                        <option value="year">This Year</option>
                    </select>
                </div>

                <!-- Key Metrics -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon blue">👥</div>
                        <div class="summary-info">
                            <h3>156</h3>
                            <p>Total Patients</p>
                            <span class="trend up">↑ 12% vs last month</span>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon green">📅</div>
                        <div class="summary-info">
                            <h3>89</h3>
                            <p>Appointments</p>
                            <span class="trend up">↑ 8% vs last month</span>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon green">💰</div>
                        <div class="summary-info">
                            <h3>₱125,000</h3>
                            <p>Revenue</p>
                            <span class="trend up">↑ 15% vs last month</span>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon yellow">⏱️</div>
                        <div class="summary-info">
                            <h3>45 min</h3>
                            <p>Avg. Wait Time</p>
                            <span class="trend down">↓ 5 min vs last month</span>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="charts-row">
                    <!-- Revenue Chart -->
                    <div class="chart-card">
                        <h3 class="chart-title">Revenue Overview</h3>
                        <div class="chart-placeholder">
                            <div class="bar-chart">
                                <div class="bar" style="height: 60%;" data-label="Jan"></div>
                                <div class="bar" style="height: 75%;" data-label="Feb"></div>
                                <div class="bar" style="height: 65%;" data-label="Mar"></div>
                                <div class="bar" style="height: 85%;" data-label="Apr"></div>
                                <div class="bar" style="height: 70%;" data-label="May"></div>
                                <div class="bar" style="height: 90%;" data-label="Jun"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Appointments Chart -->
                    <div class="chart-card">
                        <h3 class="chart-title">Appointments by Type</h3>
                        <div class="chart-placeholder">
                            <div class="pie-chart">
                                <div class="pie-segment" style="--percentage: 35; --color: #3b82f6;"></div>
                                <div class="pie-segment" style="--percentage: 25; --color: #10b981;"></div>
                                <div class="pie-segment" style="--percentage: 20; --color: #f59e0b;"></div>
                                <div class="pie-segment" style="--percentage: 20; --color: #ef4444;"></div>
                            </div>
                            <div class="chart-legend">
                                <span class="legend-item"><span class="legend-color" style="background: #3b82f6;"></span> Root Canal</span>
                                <span class="legend-item"><span class="legend-color" style="background: #10b981;"></span> Cleaning</span>
                                <span class="legend-item"><span class="legend-color" style="background: #f59e0b;"></span> Extraction</span>
                                <span class="legend-item"><span class="legend-color" style="background: #ef4444;"></span> Other</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Grid -->
                <div class="stats-grid">
                    <!-- Patient Demographics -->
                    <div class="stat-card">
                        <h3 class="stat-title">Patient Demographics</h3>
                        <div class="stat-content">
                            <div class="stat-row">
                                <span>New Patients (This Month)</span>
                                <span class="stat-value">24</span>
                            </div>
                            <div class="stat-row">
                                <span>Returning Patients</span>
                                <span class="stat-value">132</span>
                            </div>
                            <div class="stat-row">
                                <span>Most Common Age Group</span>
                                <span class="stat-value">25-35</span>
                            </div>
                            <div class="stat-row">
                                <span>Gender Distribution</span>
                                <span class="stat-value">60% F / 40% M</span>
                            </div>
                        </div>
                    </div>

                    <!-- Popular Services -->
                    <div class="stat-card">
                        <h3 class="stat-title">Top Services</h3>
                        <div class="stat-content">
                            <div class="service-rank">
                                <span class="rank">1</span>
                                <span class="service-name">Root Canal Treatment</span>
                                <span class="service-count">32</span>
                            </div>
                            <div class="service-rank">
                                <span class="rank">2</span>
                                <span class="service-name">Oral Prophylaxis</span>
                                <span class="service-count">28</span>
                            </div>
                            <div class="service-rank">
                                <span class="rank">3</span>
                                <span class="service-name">Tooth Extraction</span>
                                <span class="service-count">18</span>
                            </div>
                            <div class="service-rank">
                                <span class="rank">4</span>
                                <span class="service-name">Denture Services</span>
                                <span class="service-count">11</span>
                            </div>
                        </div>
                    </div>

                    <!-- Staff Performance -->
                    <div class="stat-card">
                        <h3 class="stat-title">Staff Performance</h3>
                        <div class="stat-content">
                            <div class="staff-stat">
                                <span class="staff-name">Dr. Rex</span>
                                <span class="staff-metric">45 patients treated</span>
                            </div>
                            <div class="staff-stat">
                                <span class="staff-name">Staff Member</span>
                                <span class="staff-metric">89 appointments managed</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

<?php
require_once __DIR__ . '/includes/admin_layout_end.php';
?>
