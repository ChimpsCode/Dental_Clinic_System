<?php
/**
 * Settings - Admin page for system settings
 */

$pageTitle = 'Settings';

require_once __DIR__ . '/includes/admin_layout_start.php';
?>
            <div class="content-main">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Settings</h2>
                    <p class="page-subtitle">Manage system configuration and preferences</p>
                </div>

                <!-- Settings Sections -->
                <div class="settings-container">
                    <!-- Clinic Information -->
                    <div class="settings-section">
                        <h3 class="section-title">🏥 Clinic Information</h3>
                        <form class="settings-form">
                            <div class="form-group">
                                <label>Clinic Name</label>
                                <input type="text" class="form-control" value="RF Dental Clinic">
                            </div>
                            <div class="form-group">
                                <label>Address</label>
                                <textarea class="form-control" rows="2">123 Dental Street, City</textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group" style="flex:1;">
                                    <label>Phone</label>
                                    <input type="text" class="form-control" value="(02) 123-4567">
                                </div>
                                <div class="form-group" style="flex:1;">
                                    <label>Email</label>
                                    <input type="email" class="form-control" value="contact@rfclinic.com">
                                </div>
                            </div>
                            <button type="submit" class="btn-primary">Save Changes</button>
                        </form>
                    </div>

                    <!-- Business Hours -->
                    <div class="settings-section">
                        <h3 class="section-title">🕐 Business Hours</h3>
                        <form class="settings-form">
                            <div class="day-schedule">
                                <label class="day-label">Monday</label>
                                <input type="time" class="form-control time-input" value="08:00">
                                <span class="time-separator">to</span>
                                <input type="time" class="form-control time-input" value="17:00">
                                <label class="day-toggle">
                                    <input type="checkbox" checked> Open
                                </label>
                            </div>
                            <div class="day-schedule">
                                <label class="day-label">Tuesday</label>
                                <input type="time" class="form-control time-input" value="08:00">
                                <span class="time-separator">to</span>
                                <input type="time" class="form-control time-input" value="17:00">
                                <label class="day-toggle">
                                    <input type="checkbox" checked> Open
                                </label>
                            </div>
                            <div class="day-schedule">
                                <label class="day-label">Wednesday</label>
                                <input type="time" class="form-control time-input" value="08:00">
                                <span class="time-separator">to</span>
                                <input type="time" class="form-control time-input" value="17:00">
                                <label class="day-toggle">
                                    <input type="checkbox" checked> Open
                                </label>
                            </div>
                            <div class="day-schedule">
                                <label class="day-label">Thursday</label>
                                <input type="time" class="form-control time-input" value="08:00">
                                <span class="time-separator">to</span>
                                <input type="time" class="form-control time-input" value="17:00">
                                <label class="day-toggle">
                                    <input type="checkbox" checked> Open
                                </label>
                            </div>
                            <div class="day-schedule">
                                <label class="day-label">Friday</label>
                                <input type="time" class="form-control time-input" value="08:00">
                                <span class="time-separator">to</span>
                                <input type="time" class="form-control time-input" value="17:00">
                                <label class="day-toggle">
                                    <input type="checkbox" checked> Open
                                </label>
                            </div>
                            <div class="day-schedule weekend">
                                <label class="day-label">Saturday</label>
                                <input type="time" class="form-control time-input" value="09:00">
                                <span class="time-separator">to</span>
                                <input type="time" class="form-control time-input" value="13:00">
                                <label class="day-toggle">
                                    <input type="checkbox" checked> Open
                                </label>
                            </div>
                            <div class="day-schedule weekend">
                                <label class="day-label">Sunday</label>
                                <input type="time" class="form-control time-input" value="">
                                <span class="time-separator">to</span>
                                <input type="time" class="form-control time-input" value="">
                                <label class="day-toggle">
                                    <input type="checkbox"> Open
                                </label>
                            </div>
                            <button type="submit" class="btn-primary">Save Hours</button>
                        </form>
                    </div>

                    <!-- Notifications -->
                    <div class="settings-section">
                        <h3 class="section-title">🔔 Notifications</h3>
                        <form class="settings-form">
                            <div class="toggle-setting">
                                <div class="toggle-info">
                                    <span class="toggle-label">Email Notifications</span>
                                    <span class="toggle-description">Receive email alerts for important events</span>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="toggle-setting">
                                <div class="toggle-info">
                                    <span class="toggle-label">Appointment Reminders</span>
                                    <span class="toggle-description">Send automatic reminders to patients</span>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="toggle-setting">
                                <div class="toggle-info">
                                    <span class="toggle-label">Low Stock Alerts</span>
                                    <span class="toggle-description">Notify when supplies are running low</span>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <button type="submit" class="btn-primary">Save Settings</button>
                        </form>
                    </div>

                    <!-- Security -->
                    <div class="settings-section">
                        <h3 class="section-title">🔒 Security</h3>
                        <form class="settings-form">
                            <div class="toggle-setting">
                                <div class="toggle-info">
                                    <span class="toggle-label">Two-Factor Authentication</span>
                                    <span class="toggle-description">Add extra security to admin accounts</span>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="toggle-setting">
                                <div class="toggle-info">
                                    <span class="toggle-label">Session Timeout</span>
                                    <span class="toggle-description">Auto logout after inactivity</span>
                                </div>
                                <select class="form-control" style="width: auto;">
                                    <option value="15">15 minutes</option>
                                    <option value="30" selected>30 minutes</option>
                                    <option value="60">1 hour</option>
                                    <option value="120">2 hours</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Password Policy</label>
                                <select class="form-control">
                                    <option value="8">Minimum 8 characters</option>
                                    <option value="12" selected>Minimum 12 characters</option>
                                    <option value="16">Minimum 16 characters</option>
                                </select>
                            </div>
                            <button type="submit" class="btn-primary">Save Security Settings</button>
                        </form>
                    </div>

                    <!-- Backup -->
                    <div class="settings-section">
                        <h3 class="section-title">💾 Backup & Data</h3>
                        <form class="settings-form">
                            <div class="backup-info">
                                <div class="backup-stat">
                                    <span class="backup-label">Last Backup</span>
                                    <span class="backup-value">January 12, 2024 02:00 AM</span>
                                </div>
                                <div class="backup-stat">
                                    <span class="backup-label">Backup Size</span>
                                    <span class="backup-value">45.2 MB</span>
                                </div>
                                <div class="backup-stat">
                                    <span class="backup-label">Auto Backup</span>
                                    <span class="backup-value">Daily at 2:00 AM</span>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn-primary" onclick="createBackup()">🔄 Create Backup Now</button>
                                <button type="button" class="btn-secondary" onclick="downloadBackup()">📥 Download Latest Backup</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

<?php
require_once __DIR__ . '/includes/admin_layout_end.php';
?>
