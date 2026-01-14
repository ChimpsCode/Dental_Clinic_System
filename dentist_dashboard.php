<?php
$pageTitle = 'Dentist Dashboard';
require_once 'includes/dentist_layout_start.php';
?>

<!-- Summary Cards -->
<div class="summary-cards">
    <div class="summary-card">
        <div class="summary-icon yellow">⏰</div>
        <div class="summary-info">
            <h3>8</h3>
            <p>Today's Appointments</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon green">✓</div>
        <div class="summary-info">
            <h3>12</h3>
            <p>Completed Today</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon blue" style="background: #e0f2fe; color: #0284c7;">📋</div>
        <div class="summary-info">
            <h3>5</h3>
            <p>Pending Treatments</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon red" style="background: #fee2e2; color: #dc2626;">📝</div>
        <div class="summary-info">
            <h3>3</h3>
            <p>New Prescriptions</p>
        </div>
    </div>
</div>

<div class="two-column">
    <div class="left-column">
        <!-- Today's Appointments -->
        <div class="section-card">
            <h2 class="section-title">📅 Today's Appointments</h2>
            <div class="patient-list">
                <div class="patient-item">
                    <div class="patient-info">
                        <div class="patient-name">Maria Santos</div>
                        <div class="patient-details">
                            <span class="status-badge" style="background: #dcfce7; color: #15803d;">In Progress</span>
                            <span class="patient-time">09:00 AM</span>
                        </div>
                        <div class="patient-treatment">Root Canal (Session 2)</div>
                    </div>
                    <div class="patient-actions">
                        <button class="action-btn icon view-btn">👁️</button>
                    </div>
                </div>
                <div class="patient-item">
                    <div class="patient-info">
                        <div class="patient-name">Juan Dela Cruz</div>
                        <div class="patient-details">
                            <span class="status-badge" style="background: #e0f2fe; color: #0369a1;">Upcoming</span>
                            <span class="patient-time">10:00 AM</span>
                        </div>
                        <div class="patient-treatment">Oral Prophylaxis</div>
                    </div>
                    <div class="patient-actions">
                        <button class="action-btn icon view-btn">👁️</button>
                    </div>
                </div>
                <div class="patient-item">
                    <div class="patient-info">
                        <div class="patient-name">Ana Reyes</div>
                        <div class="patient-details">
                            <span class="status-badge" style="background: #e0f2fe; color: #0369a1;">Upcoming</span>
                            <span class="patient-time">11:00 AM</span>
                        </div>
                        <div class="patient-treatment">Denture Adjustment</div>
                    </div>
                    <div class="patient-actions">
                        <button class="action-btn icon view-btn">👁️</button>
                    </div>
                </div>
                <div class="patient-item">
                    <div class="patient-info">
                        <div class="patient-name">Roberto Garcia</div>
                        <div class="patient-details">
                            <span class="status-badge" style="background: #e0f2fe; color: #0369a1;">Upcoming</span>
                            <span class="patient-time">02:00 PM</span>
                        </div>
                        <div class="patient-treatment">Braces Adjustment</div>
                    </div>
                    <div class="patient-actions">
                        <button class="action-btn icon view-btn">👁️</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Treatment Queue -->
        <div class="section-card">
            <h2 class="section-title">⚕️ Treatment Queue</h2>
            <div class="patient-list">
                <div class="patient-item">
                    <div class="patient-info">
                        <div class="patient-name">Maria Santos</div>
                        <div class="patient-treatment">Root Canal - Cleaning & Filling</div>
                        <div style="font-size: 0.85rem; color: #6b7280; margin-top: 4px;">Session 2 of 3</div>
                    </div>
                    <div class="patient-actions">
                        <button class="action-btn" style="background: #22c55e; color: white;">Start</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Notifications & Quick Actions -->
    <div class="right-column">
        <div class="notification-box">
            <h3>🔔 Notifications</h3>
            <div class="notification-item">
                <div class="notification-title">New Patient Inquiry</div>
                <div class="notification-detail">Mark Sy interested in braces</div>
            </div>
            <div class="notification-item">
                <div class="notification-title">Appointment Confirmed</div>
                <div class="notification-detail">Ana Reyes confirmed for 11:00 AM</div>
            </div>
        </div>
        
        <div class="notification-box">
            <h3>🚀 Quick Actions</h3>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <button class="btn-primary" style="width: 100%;">+ New Prescription</button>
                <button class="btn-primary" style="width: 100%;">+ Add Treatment Notes</button>
                <button class="btn-primary" style="width: 100%;">View Patient History</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/dentist_layout_end.php'; ?>