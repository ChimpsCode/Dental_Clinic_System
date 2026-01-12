<?php
/**
 * Admin Dashboard - Main admin landing page
 * Uses the centralized Admin Layout for consistent sidebar and navigation
 */

$pageTitle = 'Dashboard';

// Include the layout start
require_once __DIR__ . '/includes/admin_layout_start.php';

// Dashboard statistics (placeholder - replace with actual database queries)
$totalUsers = 15;
$totalPatients = 150;
$totalAppointments = 45;
$totalRevenue = 25000;
$pendingPayments = 5;
$completedToday = 8;
?>
            <!-- Admin Dashboard Content -->
            <div class="content-main">
                <!-- Summary Cards -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon yellow">👥</div>
                        <div class="summary-info">
                            <h3><?php echo $totalUsers; ?></h3>
                            <p>Total Users</p>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon blue">🏥</div>
                        <div class="summary-info">
                            <h3><?php echo $totalPatients; ?></h3>
                            <p>Total Patients</p>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon green">📅</div>
                        <div class="summary-info">
                            <h3><?php echo $totalAppointments; ?></h3>
                            <p>Appointments</p>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon green">💰</div>
                        <div class="summary-info">
                            <h3>₱<?php echo number_format($totalRevenue); ?></h3>
                            <p>Total Revenue</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats Row -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon yellow">⏳</div>
                        <div class="summary-info">
                            <h3><?php echo $pendingPayments; ?></h3>
                            <p>Pending Payments</p>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon green">✓</div>
                        <div class="summary-info">
                            <h3><?php echo $completedToday; ?></h3>
                            <p>Completed Today</p>
                        </div>
                    </div>
                </div>

                <!-- Admin Action Cards -->
                <div class="section-card">
                    <h2 class="section-title">⚡ Quick Actions</h2>
                    <div class="quick-actions">
                        <a href="admin_users.php" class="action-card">
                            <span class="action-icon">👤</span>
                            <span class="action-text">Manage Users</span>
                        </a>
                        <a href="admin_patients.php" class="action-card">
                            <span class="action-icon">📋</span>
                            <span class="action-text">View Patients</span>
                        </a>
                        <a href="admin_billing.php" class="action-card">
                            <span class="action-icon">💵</span>
                            <span class="action-text">Billing Overview</span>
                        </a>
                        <a href="admin_audit_trail.php" class="action-card">
                            <span class="action-icon">📝</span>
                            <span class="action-text">Audit Logs</span>
                        </a>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="section-card">
                    <h2 class="section-title">📊 Recent Activity</h2>
                    <div class="activity-list">
                        <div class="activity-item">
                            <span class="activity-icon">👤</span>
                            <div class="activity-details">
                                <span class="activity-text">New user registered: <strong>John Doe</strong></span>
                                <span class="activity-time">2 hours ago</span>
                            </div>
                        </div>
                        <div class="activity-item">
                            <span class="activity-icon">💰</span>
                            <div class="activity-details">
                                <span class="activity-text">Payment received: <strong>₱1,500</strong> from Maria Santos</span>
                                <span class="activity-time">4 hours ago</span>
                            </div>
                        </div>
                        <div class="activity-item">
                            <span class="activity-icon">📅</span>
                            <div class="activity-details">
                                <span class="activity-text">Appointment completed: <strong>Root Canal</strong> for Roberto Garcia</span>
                                <span class="activity-time">5 hours ago</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar -->
            <aside class="content-sidebar">
                <!-- Notifications -->
                <div class="sidebar-section">
                    <h3 class="sidebar-section-title">Notifications</h3>
                    <p class="sidebar-section-subtitle">Recent system alerts</p>
                    
                    <div class="notification-item">
                        <div class="notification-icon">⚠️</div>
                        <div class="notification-text">5 pending payments require attention</div>
                    </div>
                    
                    <div class="notification-item">
                        <div class="notification-icon">👤</div>
                        <div class="notification-text">2 new patient registrations today</div>
                    </div>
                    
                    <button class="see-all-btn">See all notifications</button>
                </div>

                <!-- System Status -->
                <div class="sidebar-section">
                    <h3 class="sidebar-section-title">System Status</h3>
                    <div class="status-item">
                        <span class="status-label">Database</span>
                        <span class="status-value online">Connected</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Active Sessions</span>
                        <span class="status-value">12</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Last Backup</span>
                        <span class="status-value">2 hours ago</span>
                    </div>
                </div>
            </aside>

<?php
// Include the layout end
require_once __DIR__ . '/includes/admin_layout_end.php';
?>
