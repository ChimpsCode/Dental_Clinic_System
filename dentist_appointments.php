<?php
$pageTitle = 'Appointments';
require_once 'includes/dentist_layout_start.php';
?>

<!-- Appointment Stats -->
<div class="summary-cards">
    <div class="summary-card">
        <div class="summary-icon blue" style="background: #e0f2fe; color: #0284c7;">📋</div>
        <div class="summary-info">
            <h3>24</h3>
            <p>Total Appointments</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon yellow">⏰</div>
        <div class="summary-info">
            <h3>8</h3>
            <p>Today</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon green">✓</div>
        <div class="summary-info">
            <h3>12</h3>
            <p>Completed</p>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-icon red" style="background: #fee2e2; color: #dc2626;">⚠️</div>
        <div class="summary-info">
            <h3>4</h3>
            <p>Cancelled</p>
        </div>
    </div>
</div>

<!-- Search & Filters -->
<div class="search-filters">
    <div class="filter-tabs">
        <span class="active">All</span>
        <span>Today</span>
        <span>This Week</span>
        <span>This Month</span>
    </div>
    <input type="text" class="search-input" placeholder="Search appointments...">
</div>

<!-- Appointments Table -->
<div class="section-card">
    <div class="section-title">
        <span>Appointments List</span>
        <button class="btn-primary">+ New Appointment</button>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>Patient Name</th>
                <th>Date & Time</th>
                <th>Treatment</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div class="patient-name">Maria Santos</div>
                    <div style="font-size: 0.85rem; color: #6b7280;">Phone: 0912-345-6789</div>
                </td>
                <td>
                    <div>Jan 13, 2026</div>
                    <div style="font-size: 0.85rem; color: #6b7280;">09:00 AM</div>
                </td>
                <td>Root Canal (Session 2)</td>
                <td><span class="status-badge" style="background: #dcfce7; color: #15803d;">Completed</span></td>
                <td>
                    <div class="patient-actions">
                        <button class="action-btn icon view-btn">👁️</button>
                        <button class="action-btn icon">📝</button>
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="patient-name">Juan Dela Cruz</div>
                    <div style="font-size: 0.85rem; color: #6b7280;">Phone: 0918-765-4321</div>
                </td>
                <td>
                    <div>Jan 13, 2026</div>
                    <div style="font-size: 0.85rem; color: #6b7280;">10:00 AM</div>
                </td>
                <td>Oral Prophylaxis</td>
                <td><span class="status-badge" style="background: #e0f2fe; color: #0369a1;">Upcoming</span></td>
                <td>
                    <div class="patient-actions">
                        <button class="action-btn icon view-btn">👁️</button>
                        <button class="action-btn icon">📝</button>
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="patient-name">Ana Reyes</div>
                    <div style="font-size: 0.85rem; color: #6b7280;">Phone: 0917-654-3210</div>
                </td>
                <td>
                    <div>Jan 13, 2026</div>
                    <div style="font-size: 0.85rem; color: #6b7280;">11:00 AM</div>
                </td>
                <td>Denture Adjustment</td>
                <td><span class="status-badge" style="background: #e0f2fe; color: #0369a1;">Upcoming</span></td>
                <td>
                    <div class="patient-actions">
                        <button class="action-btn icon view-btn">👁️</button>
                        <button class="action-btn icon">📝</button>
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="patient-name">Roberto Garcia</div>
                    <div style="font-size: 0.85rem; color: #6b7280;">Phone: 0923-456-7890</div>
                </td>
                <td>
                    <div>Jan 13, 2026</div>
                    <div style="font-size: 0.85rem; color: #6b7280;">02:00 PM</div>
                </td>
                <td>Braces Adjustment</td>
                <td><span class="status-badge" style="background: #e0f2fe; color: #0369a1;">Upcoming</span></td>
                <td>
                    <div class="patient-actions">
                        <button class="action-btn icon view-btn">👁️</button>
                        <button class="action-btn icon">📝</button>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<?php require_once 'includes/dentist_layout_end.php'; ?>