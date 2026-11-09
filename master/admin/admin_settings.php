<?php
/**
 * Settings - Admin page for system settings
 */

$pageTitle = 'Settings';

require_once __DIR__ . '/../../config/database.php';

// Load settings from database
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($rows as $key => $value) {
    $settings[$key] = $value;
}

// Parse business hours
$defaultHours = [
    'monday'    => ['open' => '08:00', 'close' => '17:00', 'is_open' => true],
    'tuesday'   => ['open' => '08:00', 'close' => '17:00', 'is_open' => true],
    'wednesday' => ['open' => '08:00', 'close' => '17:00', 'is_open' => true],
    'thursday'  => ['open' => '08:00', 'close' => '17:00', 'is_open' => true],
    'friday'    => ['open' => '08:00', 'close' => '17:00', 'is_open' => true],
    'saturday'  => ['open' => '09:00', 'close' => '13:00', 'is_open' => true],
    'sunday'    => ['open' => '', 'close' => '', 'is_open' => false]
];
$businessHours = isset($settings['business_hours']) ? json_decode($settings['business_hours'], true) : $defaultHours;
if (!$businessHours) $businessHours = $defaultHours;

// Parse unavailable dates
$unavailableDates = isset($settings['unavailable_dates']) ? json_decode($settings['unavailable_dates'], true) : [];
if (!$unavailableDates) $unavailableDates = [];

require_once __DIR__ . '/../../includes/admin_layout_start.php';

// Two-factor status is per-user (Google Authenticator), not a clinic-wide flag.
$adminTwoFactorEnabled = false;
$adminUserId = (int)($_SESSION['user_id'] ?? 0);
require_once __DIR__ . '/../../includes/payment_approval_helper.php';
ensurePaymentApprovalSchema($pdo);
$_SESSION['payment_approval_csrf'] = $_SESSION['payment_approval_csrf'] ?? bin2hex(random_bytes(32));
$pinStmt = $pdo->prepare('SELECT pin_hash IS NOT NULL FROM admin_payment_pins WHERE user_id = ?');
$pinStmt->execute([$adminUserId]);
$paymentPinConfigured = (bool)$pinStmt->fetchColumn();
if ($adminUserId > 0) {
    try {
        $twoFaStmt = $pdo->prepare("SELECT two_factor_enabled FROM users WHERE id = ? LIMIT 1");
        $twoFaStmt->execute([$adminUserId]);
        $adminTwoFactorEnabled = (int)$twoFaStmt->fetchColumn() === 1;
    } catch (Throwable $e) {
        $adminTwoFactorEnabled = false;
    }
}

require_once __DIR__ . '/../../includes/backup_helper.php';
$latestBackup = latestBackupInfo();
$backupLastLabel = $latestBackup['created_label'] ?? 'No backup yet';
$backupSizeLabel = $latestBackup['size_label'] ?? '—';
$autoBackupEnabled = ($settings['auto_backup_enabled'] ?? '0') === '1';
$autoBackupTime = (string)($settings['auto_backup_time'] ?? '02:00');
if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $autoBackupTime)) {
    $autoBackupTime = '02:00';
}
$autoBackupMode = (($settings['auto_backup_mode'] ?? 'month') === 'date') ? 'date' : 'month';
$autoBackupDate = (string)($settings['auto_backup_date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $autoBackupDate)) {
    $autoBackupDate = '';
}
$autoBackupDay = (int)($settings['auto_backup_day'] ?? 1);
if ($autoBackupDay < 1 || $autoBackupDay > 28) {
    $autoBackupDay = 1;
}
?>
            <style>
                /* Keep bottom spacing after the full settings form in the scrolling flex layout. */
                .settings-content { align-self: flex-start; padding-bottom: 40px; }
                .schedule-tabs { display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 2px solid #e5e7eb; padding-bottom: 0; }
                .schedule-tab { padding: 12px 24px; cursor: pointer; border: none; background: transparent; font-size: 15px; font-weight: 500; color: #6b7280; border-bottom: 3px solid transparent; transition: all 0.2s; }
                .schedule-tab:hover { color: #005daa; }
                .schedule-tab.active { color: #005daa; border-bottom-color: #005daa; }
                .schedule-panel { display: none; }
                .schedule-panel.active { display: block; }
                .day-schedule { display: flex; align-items: center; gap: 16px; margin-bottom: 12px; flex-wrap: wrap; padding: 12px; border-radius: 8px; transition: all 0.2s; background: #fff; border: 1px solid #f3f4f6; }
                .day-schedule:hover { border-color: #e5e7eb; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
                .day-label { font-weight: 600; min-width: 100px; color: #374151; font-size: 15px; }
                
                /* Toggle Switch Styling */
                .toggle-switch { position: relative; display: inline-block; width: 46px; height: 24px; margin-right: 8px; }
                .toggle-switch input { opacity: 0; width: 0; height: 0; }
                .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #e5e7eb; transition: .3s; border-radius: 24px; }
                .toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
                .toggle-switch input:checked + .toggle-slider { background-color: #005daa; }
                .toggle-switch input:checked + .toggle-slider:before { transform: translateX(22px); }

                .time-controls { display: flex; align-items: center; gap: 8px; flex: 1; }
                .time-select { width: 130px; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; background-color: #fff; cursor: pointer; }
                .time-input-manual { width: 130px; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; display: none; }
                .time-separator { color: #9ca3af; font-weight: 500; }
                
                .manual-choice { display: flex; align-items: center; gap: 6px; cursor: pointer; color: #6b7280; font-size: 13px; margin-left: auto; padding: 4px 8px; border-radius: 4px; transition: 0.2s; }
                .manual-choice:hover { background: #f3f4f6; color: #374151; }
                .manual-choice input { cursor: pointer; }

                .weekend { background: #f9fafb; }
                .day-closed { opacity: 0.7; }
                .day-closed .time-controls { pointer-events: none; opacity: 0.5; }
                
                .schedule-calendar { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
                .cal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
                .cal-title { font-size: 20px; font-weight: 700; color: #1a1a2e; }
                .cal-nav { display: flex; gap: 8px; }
                .cal-nav button { background: #f3f4f6; border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: all 0.2s; }
                .cal-nav button:hover { background: #e5e7eb; }
                .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
                .cal-day-header { text-align: center; font-size: 12px; font-weight: 600; color: #6b7280; padding: 8px 0; text-transform: uppercase; }
                .cal-day { min-height: 60px; width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 14px; border-radius: 8px; cursor: pointer; transition: all 0.2s; position: relative; font-weight: 500; border: 1px solid #e5e7eb; background: white; }
                .cal-day:hover { background: #f0f7ff; border-color: #005daa; }
                .cal-day.today { background: #dcf0ff; border-color: #005daa; }
                .cal-day.empty { background: #f9fafb; cursor: default; border-color: #f3f4f6; }
                .cal-day.empty:hover { background: #f9fafb; border-color: #f3f4f6; }
                .cal-day.status-closed { background: #fee2e2; border-color: #fca5a5; color: #991b1b; }
                .cal-day.status-closed:hover { background: #fecaca; }
                .cal-day.status-open { background: #d1fae5; border-color: #a7f3d0; color: #065f46; }
                .cal-day.status-half_am, .cal-day.status-half_pm { background: #fef3c7; border-color: #fde68a; color: #92400e; }
                .cal-day.status-custom { background: #e9d5ff; border-color: #d8b4fe; color: #7e22ce; }
                .cal-day.status-holiday { background: #dbeafe; border-color: #93c5fd; color: #1e40af; }
                .cal-day .status-indicator { position: absolute; top: 4px; right: 4px; width: 8px; height: 8px; border-radius: 50%; }
                .cal-day .status-indicator.closed { background: #dc2626; }
                .cal-day .status-indicator.open { background: #059669; }
                .cal-day .status-indicator.half_am, .cal-day .status-indicator.half_pm { background: #d97706; }
                .cal-day .status-indicator.custom { background: #9333ea; }
                .cal-day .status-indicator.holiday { background: #2563eb; }
                
                .unavailable-list { margin-top: 24px; }
                .unavailable-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid #f3f4f6; }
                .unavailable-item:last-child { border-bottom: none; }
                .unavailable-date { font-weight: 500; }
                .unavailable-status { font-size: 12px; padding: 4px 8px; border-radius: 4px; }
                .status-badge-closed { background: #fee2e2; color: #dc2626; }
                .status-badge-half_am { background: #fef3c7; color: #d97706; }
                .status-badge-half_pm { background: #fef3c7; color: #d97706; }
                .status-badge-custom { background: #e9d5ff; color: #9333ea; }
                .status-badge-holiday { background: #dbeafe; color: #2563eb; }
                .delete-date-btn { background: none; border: none; color: #ef4444; cursor: pointer; font-size: 18px; padding: 4px 8px; }
                .delete-date-btn:hover { background: #fee2e2; border-radius: 4px; }
                
                .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100001; justify-content: center; align-items: center; padding: 16px; }
                .modal-overlay.active { display: flex; }
                .modal-content { background: white; border-radius: 12px; padding: 24px; max-width: 400px; width: 100%; position: relative; z-index: 100002; }
                .modal-content.modal-2fa { max-width: 460px; max-height: calc(100vh - 32px); overflow-y: auto; }
                .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
                .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280; line-height: 1; }
                .form-group { margin-bottom: 16px; }
                .form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #374151; }
                .form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
                .btn-primary { background: #005daa; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; }
                .btn-primary:hover { background: #004080; }
                .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
                .btn-primary.is-idle,
                .btn-primary.is-idle:hover,
                .btn-primary.is-idle:disabled {
                    background: #e5e7eb;
                    color: #9ca3af;
                    opacity: 1;
                    cursor: default;
                    box-shadow: none;
                }
                .btn-edit { background: #e5e7eb; color: #4b5563; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; }
                .btn-edit:hover { background: #d1d5db; }
                .form-control.is-locked { background: #f3f4f6; color: #6b7280; }
                .btn-secondary { background: #f3f4f6; color: #374151; padding: 10px 20px; border: 1px solid #d1d5db; border-radius: 6px; cursor: pointer; }
                .btn-secondary:hover { background: #e5e7eb; }
                .bulk-form { background: #f9fafb; padding: 20px; border-radius: 8px; margin-top: 20px; }

                .twofa-status { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 999px; margin-top: 6px; }
                .twofa-status.on { background: #d1fae5; color: #065f46; }
                .twofa-status.off { background: #f3f4f6; color: #6b7280; }
                .backup-note, .backup-help { color: #4b5563; font-size: 14px; line-height: 1.5; margin: 0 0 16px; }
                .backup-help { font-size: 13px; }
                .backup-info { grid-template-columns: repeat(2, 1fr); }
                .backup-error { color: #b91c1c; font-size: 13px; margin: 0 0 12px; }
                .backup-modal { max-width: 440px; }
                @media (max-width: 640px) { .backup-info { grid-template-columns: 1fr; } }
                
                @media (max-width: 768px) {
                    .settings-content {
                        width: 100%;
                        min-width: 0;
                        padding: 0 0 calc(80px + env(safe-area-inset-bottom));
                    }
                    .settings-container,
                    .settings-section,
                    .settings-form {
                        width: 100%;
                        min-width: 0;
                        max-width: 100%;
                    }
                    .settings-section {
                        padding: 16px;
                    }
                    .settings-section input,
                    .settings-section select,
                    .settings-section textarea,
                    .settings-section button {
                        max-width: 100%;
                    }
                    .settings-section [style*="justify-content: flex-end"],
                    .settings-section [style*="display: flex"] {
                        flex-wrap: wrap;
                    }
                    .cal-day { min-height: 40px; font-size: 12px; }
                    .day-schedule { gap: 8px; }
                    .day-label { min-width: 80px; }
                }
            </style>

            <div class="content-main settings-content">
                <div class="page-header">
                    <h2>Settings</h2>
                    <p class="page-subtitle">Manage system configuration and preferences</p>
                </div>

                <div class="settings-container">
                    <div class="settings-section">
                        <h3 class="section-title">🏥 Clinic Information</h3>
                        <form class="settings-form" id="clinicSettingsForm">
                            <div class="form-group">
                                <label>Clinic Name</label>
                                <input type="text" class="form-control is-locked" id="clinicName" readonly value="<?php echo htmlspecialchars($settings['clinic_name'] ?? 'RF Dental Clinic'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Address</label>
                                <textarea class="form-control is-locked" id="clinicAddress" rows="2" readonly><?php echo htmlspecialchars($settings['clinic_address'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group" style="flex:1;">
                                    <label>Phone</label>
                                    <input type="text" class="form-control is-locked" id="clinicPhone" readonly value="<?php echo htmlspecialchars($settings['clinic_phone'] ?? ''); ?>">
                                </div>
                                <div class="form-group" style="flex:1;">
                                    <label>Email</label>
                                    <input type="email" class="form-control is-locked" id="clinicEmail" readonly value="<?php echo htmlspecialchars($settings['clinic_email'] ?? ''); ?>">
                                </div>
                            </div>
                            <button type="button" class="btn-edit" id="clinicEditBtn">Edit</button>
                        </form>
                    </div>

                    <div class="settings-section">
                        <h3 class="section-title">📅 Schedule Management</h3>
                        
                        <div class="schedule-tabs">
                            <button class="schedule-tab active" data-tab="business-hours">Business Hours</button>
                            <button class="schedule-tab" data-tab="schedule-calendar">Schedule Calendar</button>
                            <button class="schedule-tab" data-tab="bulk-add">Bulk Add</button>
                        </div>

                        <div class="schedule-panel active" id="panel-business-hours">
                            <form class="settings-form" id="businessHoursForm">
                                <?php
                                if (!function_exists('generateTimeOptions')) {
                                    function generateTimeOptions($selectedTime) {
                                        $options = '';
                                        for ($h = 0; $h < 24; $h++) {
                                            foreach (['00', '30'] as $m) {
                                                $time24 = sprintf('%02d:%s', $h, $m);
                                                $time12 = date('h:i A', strtotime($time24));
                                                $selected = ($time24 == $selectedTime) ? 'selected' : '';
                                                $options .= "<option value=\"$time24\" $selected>$time12</option>";
                                            }
                                        }
                                        return $options;
                                    }
                                }

                                $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                                foreach ($days as $day):
                                    $hours = $businessHours[$day] ?? ['open' => '', 'close' => '', 'is_open' => false];
                                    $isWeekend = in_array($day, ['saturday', 'sunday']);
                                ?>
                                <div class="day-schedule <?php echo $isWeekend ? 'weekend' : ''; ?> <?php echo !$hours['is_open'] ? 'day-closed' : ''; ?>" data-day="<?php echo $day; ?>">
                                    <label class="day-label"><?php echo ucfirst($day); ?></label>
                                    
                                    <label class="toggle-switch">
                                        <input type="checkbox" class="is-open-checkbox" <?php echo $hours['is_open'] ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>

                                    <div class="time-controls">
                                        <select class="time-select time-open">
                                            <?php echo generateTimeOptions($hours['open']); ?>
                                        </select>
                                        <input type="time" class="time-input-manual time-open-manual" value="<?php echo htmlspecialchars($hours['open'] ?? ''); ?>">
                                        
                                        <span class="time-separator">to</span>
                                        
                                        <select class="time-select time-close">
                                            <?php echo generateTimeOptions($hours['close']); ?>
                                        </select>
                                        <input type="time" class="time-input-manual time-close-manual" value="<?php echo htmlspecialchars($hours['close'] ?? ''); ?>">
                                        
                                        <label class="manual-choice">
                                            <input type="checkbox" class="manual-toggle-checkbox"> Manual
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <button type="submit" class="btn-primary is-idle" id="saveHoursBtn" disabled>Save Hours</button>
                            </form>
                        </div>

                        <div class="schedule-panel" id="panel-schedule-calendar">
                            <div class="schedule-calendar">
                                <div class="cal-header">
                                    <h3 class="cal-title" id="calMonthYear"></h3>
                                    <div class="cal-nav">
                                        <button onclick="changeCalMonth(-1)">&#9664;</button>
                                        <button onclick="changeCalMonth(1)">&#9654;</button>
                                    </div>
                                </div>
                                <div class="cal-grid" id="calGrid"></div>
                            </div>

                            <div class="unavailable-list" id="unavailableList">
                                <h4 style="margin-bottom: 12px; color: #374151;">📋 Unavailable Dates</h4>
                                <div id="unavailableItems"></div>
                            </div>
                        </div>

                        <div class="schedule-panel" id="panel-bulk-add">
                            <div class="bulk-form">
                                <h4 style="margin-bottom: 16px; color: #374151;">📦 Bulk Add Holidays / Closed Dates</h4>
                                <div class="form-group">
                                    <label>From Date</label>
                                    <input type="date" class="form-control" id="bulkStartDate">
                                </div>
                                <div class="form-group">
                                    <label>To Date</label>
                                    <input type="date" class="form-control" id="bulkEndDate">
                                </div>
                                <div class="form-group">
                                    <label>Status</label>
                                    <select class="form-control" id="bulkStatus">
                                        <option value="closed">Closed</option>
                                        <option value="holiday">Holiday</option>
                                        <option value="half_am">Half Day (AM)</option>
                                        <option value="half_pm">Half Day (PM)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Note (Optional)</label>
                                    <input type="text" class="form-control" id="bulkNote" placeholder="e.g., Christmas Holidays">
                                </div>
                                <button class="btn-primary" onclick="bulkAddDates()">Add Date Range</button>
                            </div>
                        </div>
                    </div>

                    <div class="settings-section">
                        <h3 class="section-title">🔔 Notifications</h3>
                        <form class="settings-form" id="notificationSettingsForm">
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
                            
                            <button type="submit" class="btn-primary is-idle" id="saveNotificationsBtn" disabled>Save Settings</button>
                        </form>
                    </div>

                    <div class="settings-section">
                        <h3 class="section-title">⏰ Auto-Cancel No-Show</h3>
                        <form class="settings-form" id="autoCancelForm">
                            <div class="toggle-setting">
                                <div class="toggle-info">
                                    <span class="toggle-label">Enable Auto-Cancel</span>
                                    <span class="toggle-description">Automatically cancel appointments when patients do not arrive within the set time window</span>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="autoCancelEnabled" <?php echo ($settings['auto_cancel_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="form-group" id="autoCancelTimeoutContainer" style="<?php echo ($settings['auto_cancel_enabled'] ?? '0') === '1' ? '' : 'display: none;'; ?>">
                                <label>No-Show Timeout</label>
                                <select class="form-control" id="autoCancelMinutes" style="width: auto;">
                                    <option value="10" <?php echo ($settings['auto_cancel_minutes'] ?? '15') == '10' ? 'selected' : ''; ?>>10 minutes after appointment time</option>
                                    <option value="15" <?php echo ($settings['auto_cancel_minutes'] ?? '15') == '15' ? 'selected' : ''; ?>>15 minutes after appointment time</option>
                                    <option value="20" <?php echo ($settings['auto_cancel_minutes'] ?? '15') == '20' ? 'selected' : ''; ?>>20 minutes after appointment time</option>
                                    <option value="30" <?php echo ($settings['auto_cancel_minutes'] ?? '15') == '30' ? 'selected' : ''; ?>>30 minutes after appointment time</option>
                                </select>
                            </div>
                            <button type="submit" class="btn-primary is-idle" id="saveAutoCancelBtn" disabled>Save Auto-Cancel Settings</button>
                        </form>
                    </div>

                    <div class="settings-section">
                        <h3 class="section-title">🔒 Security</h3>
                        <form class="settings-form" id="securitySettingsForm">
                            <div class="toggle-setting">
                                <div class="toggle-info">
                                    <span class="toggle-label">Two-Factor Authentication</span>
                                    <span class="toggle-description">Protect your admin account with Google Authenticator (or any TOTP app)</span>
                                    <span class="twofa-status <?php echo $adminTwoFactorEnabled ? 'on' : 'off'; ?>" id="twoFactorStatusBadge">
                                        <?php echo $adminTwoFactorEnabled ? 'Enabled' : 'Disabled'; ?>
                                    </span>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="twoFactorAuth" <?php echo $adminTwoFactorEnabled ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div class="toggle-setting">
                                <div class="toggle-info">
                                    <span class="toggle-label">Session Timeout</span>
                                    <span class="toggle-description">Auto logout after inactivity</span>
                                </div>
                                <select class="form-control" id="sessionTimeout" style="width: auto;">
                                    <option value="15" <?php echo ($settings['session_timeout'] ?? '30') == '15' ? 'selected' : ''; ?>>15 minutes</option>
                                    <option value="30" <?php echo ($settings['session_timeout'] ?? '30') == '30' ? 'selected' : ''; ?>>30 minutes</option>
                                    <option value="60" <?php echo ($settings['session_timeout'] ?? '30') == '60' ? 'selected' : ''; ?>>1 hour</option>
                                    <option value="120" <?php echo ($settings['session_timeout'] ?? '30') == '120' ? 'selected' : ''; ?>>2 hours</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Password Policy</label>
                                <select class="form-control" id="passwordPolicy">
                                    <option value="8">Minimum 8 characters</option>
                                    <option value="12" selected>Minimum 12 characters</option>
                                    <option value="16">Minimum 16 characters</option>
                                </select>
                            </div>
                            <button type="submit" class="btn-primary is-idle" id="saveSecurityBtn" disabled>Save Security Settings</button>
                        </form>
                    </div>

                    <div class="settings-section" id="paymentApprovalSecurity">
                        <h3 class="section-title">🔒 Security · Payment Approval PIN</h3>
                        <div class="toggle-setting">
                            <div class="toggle-info">
                                <span class="toggle-label">Your payment approval PIN</span>
                                <span class="toggle-description">Set or reset your private six-digit PIN. Enter it personally to approve payment voids requested by staff.</span>
                                <span class="twofa-status <?php echo $paymentPinConfigured ? 'on' : 'off'; ?>" id="paymentApprovalPinConfigured"><?php echo $paymentPinConfigured ? 'PIN configured' : 'PIN not configured'; ?></span>
                            </div>
                        </div>
                        <form class="settings-form" id="paymentApprovalPinForm" method="POST" action="../shared/payment_approval.php">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['payment_approval_csrf']); ?>">
                            <input type="hidden" name="action" value="set_pin">
                            <div class="form-group"><label for="approvalAccountPassword">Current account password</label><input class="form-control is-locked" id="approvalAccountPassword" name="password" type="password" autocomplete="current-password" required disabled></div>
                            <div class="form-row">
                                <div class="form-group" style="flex:1;"><label for="approvalNewPin">New approval PIN</label><input class="form-control is-locked" id="approvalNewPin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" required disabled></div>
                                <div class="form-group" style="flex:1;"><label for="approvalConfirmPin">Confirm approval PIN</label><input class="form-control is-locked" id="approvalConfirmPin" name="confirmation" type="password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="new-password" required disabled></div>
                            </div>
                            <p id="paymentApprovalPinStatus" role="status" class="backup-help"></p>
                            <button type="button" class="btn-edit" id="paymentApprovalPinEdit" data-editing="false">Edit</button>
                        </form>
                    </div>

                    <div class="settings-section">
                        <h3 class="section-title">💾 Backup & Data</h3>
                        <p class="backup-note">Downloading saves a copy of patients, appointments, billing, and settings to this computer. Activity logs older than 1 year are already removed, so 12 months is the furthest a file can go. Auto backup, when turned on, saves a copy on the server instead of downloading it.</p>
                        <div class="backup-info">
                            <div class="backup-stat">
                                <span class="backup-label">Last Backup</span>
                                <span class="backup-value" id="backupLastValue"><?php echo htmlspecialchars($backupLastLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="backup-stat">
                                <span class="backup-label">Backup Size</span>
                                <span class="backup-value" id="backupSizeValue"><?php echo htmlspecialchars($backupSizeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                        <form class="settings-form" id="autoBackupForm">
                            <div class="toggle-setting">
                                <div class="toggle-info">
                                    <span class="toggle-label">Auto Backup</span>
                                    <span class="toggle-description">When this is on, the server saves a full clinic copy with 12 months of activity history at the time you choose. It does not download the file to your computer.</span>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="autoBackupEnabled" <?php echo $autoBackupEnabled ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div id="autoBackupSchedule" style="<?php echo $autoBackupEnabled ? '' : 'display: none;'; ?>">
                                <div class="form-group">
                                    <label for="autoBackupTime">Time</label>
                                    <input type="time" class="form-control" id="autoBackupTime" value="<?php echo htmlspecialchars($autoBackupTime, ENT_QUOTES, 'UTF-8'); ?>" style="width: auto;">
                                </div>
                                <div class="form-group">
                                    <label for="autoBackupMode">When</label>
                                    <select class="form-control" id="autoBackupMode" style="width: auto;">
                                        <option value="date" <?php echo $autoBackupMode === 'date' ? 'selected' : ''; ?>>On a date</option>
                                        <option value="month" <?php echo $autoBackupMode === 'month' ? 'selected' : ''; ?>>Every month</option>
                                    </select>
                                </div>
                                <div class="form-group" id="autoBackupDateWrap" style="<?php echo $autoBackupMode === 'date' ? '' : 'display: none;'; ?>">
                                    <label for="autoBackupDate">Date</label>
                                    <input type="date" class="form-control" id="autoBackupDate" value="<?php echo htmlspecialchars($autoBackupDate, ENT_QUOTES, 'UTF-8'); ?>" style="width: auto;">
                                </div>
                                <div class="form-group" id="autoBackupDayWrap" style="<?php echo $autoBackupMode === 'month' ? '' : 'display: none;'; ?>">
                                    <label for="autoBackupDay">Day of the month</label>
                                    <select class="form-control" id="autoBackupDay" style="width: auto;">
                                        <?php for ($day = 1; $day <= 28; $day++): ?>
                                            <option value="<?php echo $day; ?>" <?php echo $autoBackupDay === $day ? 'selected' : ''; ?>><?php echo $day; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <span class="toggle-description">Runs on this day each month, after the time above. Days stop at 28 so short months still work.</span>
                                </div>
                            </div>
                            <button type="submit" class="btn-primary is-idle" id="saveAutoBackupBtn" disabled>Save Auto Backup</button>
                        </form>
                        <div class="form-actions">
                            <button type="button" class="btn-primary" id="openBackupDownload">Download a new backup</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-overlay" id="backupMonthsModal">
                <div class="modal-content backup-modal">
                    <div class="modal-header">
                        <h3>Choose activity history</h3>
                        <button type="button" class="modal-close" id="closeBackupMonthsModal" aria-label="Close">&times;</button>
                    </div>
                    <p class="backup-help">Patients, appointments, billing, and settings are always included. This choice only sets how much of the activity log goes in the file. Activity logs older than 1 year are already removed.</p>
                    <div class="form-group">
                        <label for="backupMonths">Activity history to include</label>
                        <select class="form-control" id="backupMonths">
                            <option value="1">1 month</option>
                            <option value="3">3 months</option>
                            <option value="6">6 months</option>
                            <option value="12" selected>12 months (all activity still on file)</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" class="btn-secondary" id="cancelBackupMonths">Cancel</button>
                        <button type="button" class="btn-primary" id="continueBackupMonths">Continue</button>
                    </div>
                </div>
            </div>

            <div class="modal-overlay" id="backupPasswordModal">
                <div class="modal-content backup-modal">
                    <div class="modal-header">
                        <h3>Confirm it is you</h3>
                        <button type="button" class="modal-close" id="closeBackupPasswordModal" aria-label="Close">&times;</button>
                    </div>
                    <p class="backup-help">Type your current login password. The backup file is created only after the password matches.</p>
                    <p class="backup-error" id="backupPasswordError" hidden></p>
                    <div class="form-group">
                        <label for="backupPassword">Current password</label>
                        <input type="password" class="form-control" id="backupPassword" autocomplete="current-password" placeholder="Type your login password">
                    </div>
                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" class="btn-secondary" id="backToBackupMonths">Back</button>
                        <button type="button" class="btn-primary" id="confirmBackupDownload">Download backup</button>
                    </div>
                </div>
            </div>

            <div class="modal-overlay" id="dateStatusModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="modalDateTitle">Set Date Status</h3>
                        <button class="modal-close" onclick="closeDateModal()">&times;</button>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select class="form-control" id="dateStatus">
                            <option value="open">Open (Normal Hours)</option>
                            <option value="closed">Closed</option>
                            <option value="half_am">Half Day (AM)</option>
                            <option value="half_pm">Half Day (PM)</option>
                            <option value="custom">Custom Hours</option>
                            <option value="holiday">Holiday</option>
                        </select>
                    </div>
                    <div class="form-group" id="customHoursFields" style="display: none;">
                        <div style="display: flex; gap: 12px;">
                            <div style="flex: 1;">
                                <label>Open Time</label>
                                <input type="time" class="form-control" id="customOpen">
                            </div>
                            <div style="flex: 1;">
                                <label>Close Time</label>
                                <input type="time" class="form-control" id="customClose">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Note (Optional)</label>
                        <input type="text" class="form-control" id="dateNote" placeholder="e.g., Renovation, Holiday">
                    </div>
                    <div style="display: flex; gap: 12px;">
                        <button class="btn-primary" onclick="saveDateStatus()">Save</button>
                        <button class="btn-secondary" onclick="deleteDateStatus()" id="deleteDateBtn" style="display: none;">Delete</button>
                    </div>
                </div>
            </div>

<?php require_once __DIR__ . '/../../includes/partials/two_factor_modals.php'; ?>

<script src="../../assets/js/payment-approval.js"></script>
<?php
require_once __DIR__ . '/../../includes/admin_layout_end.php';
?>

<script>
window.settingsSaveTrackers = {};

function setSettingsSaveReady(button, ready) {
    if (!button) return;
    button.disabled = !ready;
    button.classList.toggle('is-idle', !ready);
}

function trackSettingsForm(name, form, button, readState) {
    if (!form || !button) return;
    var saved = readState();
    function refresh() {
        setSettingsSaveReady(button, readState() !== saved);
    }
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    window.settingsSaveTrackers[name] = {
        refresh: refresh,
        commit: function() {
            saved = readState();
            setSettingsSaveReady(button, false);
        }
    };
    setSettingsSaveReady(button, false);
}

var clinicFields = ['clinicName', 'clinicAddress', 'clinicPhone', 'clinicEmail'].map(function(id) {
    return document.getElementById(id);
});
var clinicBtn = document.getElementById('clinicEditBtn');
var clinicEditing = false;
var clinicSaved = clinicFields.map(function(el) { return el.value; }).join('\n');

function readClinic() {
    return clinicFields.map(function(el) { return el.value; }).join('\n');
}

function showClinicEdit() {
    clinicEditing = false;
    clinicFields.forEach(function(el) {
        el.readOnly = true;
        el.classList.add('is-locked');
    });
    clinicBtn.className = 'btn-edit';
    clinicBtn.disabled = false;
    clinicBtn.textContent = 'Edit';
}

function showClinicSave(ready) {
    clinicEditing = true;
    clinicFields.forEach(function(el) {
        el.readOnly = false;
        el.classList.remove('is-locked');
    });
    clinicBtn.className = ready ? 'btn-primary' : 'btn-primary is-idle';
    clinicBtn.disabled = !ready;
    clinicBtn.textContent = 'Save changes';
}

clinicBtn.addEventListener('click', function() {
    if (!clinicEditing) {
        showClinicSave(readClinic() !== clinicSaved);
        clinicFields[0].focus();
        return;
    }
    if (!clinicBtn.disabled) {
        document.getElementById('clinicSettingsForm').requestSubmit();
    }
});

document.getElementById('clinicSettingsForm').addEventListener('input', function() {
    if (clinicEditing) showClinicSave(readClinic() !== clinicSaved);
});

document.getElementById('clinicSettingsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    if (!clinicEditing || readClinic() === clinicSaved) return;
    
    const formData = new FormData();
    formData.append('action', 'save_settings');
    formData.append('clinic_name', document.getElementById('clinicName').value);
    formData.append('clinic_address', document.getElementById('clinicAddress').value);
    formData.append('clinic_phone', document.getElementById('clinicPhone').value);
    formData.append('clinic_email', document.getElementById('clinicEmail').value);
    
    fetch('settings_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            clinicSaved = readClinic();
            showClinicEdit();
            var sidebarName = document.querySelector('.sidebar-logo span');
            if (sidebarName) {
                sidebarName.textContent = document.getElementById('clinicName').value.trim() || 'RF Dental Clinic';
            }
            alert('Settings saved successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(() => {
        alert('Failed to save settings');
    });
});

document.getElementById('notificationSettingsForm').addEventListener('submit', function(e) {
    e.preventDefault();
});

trackSettingsForm('notifications', document.getElementById('notificationSettingsForm'), document.getElementById('saveNotificationsBtn'), function() {
    return Array.prototype.map.call(document.querySelectorAll('#notificationSettingsForm input[type="checkbox"]'), function(box) {
        return box.checked ? '1' : '0';
    }).join('');
});

trackSettingsForm('autoCancel', document.getElementById('autoCancelForm'), document.getElementById('saveAutoCancelBtn'), function() {
    return (document.getElementById('autoCancelEnabled').checked ? '1' : '0') + '|' + document.getElementById('autoCancelMinutes').value;
});

trackSettingsForm('security', document.getElementById('securitySettingsForm'), document.getElementById('saveSecurityBtn'), function() {
    return document.getElementById('sessionTimeout').value + '|' + document.getElementById('passwordPolicy').value;
});

trackSettingsForm('autoBackup', document.getElementById('autoBackupForm'), document.getElementById('saveAutoBackupBtn'), function() {
    return [
        document.getElementById('autoBackupEnabled').checked ? '1' : '0',
        document.getElementById('autoBackupTime').value,
        document.getElementById('autoBackupMode').value,
        document.getElementById('autoBackupDate').value,
        document.getElementById('autoBackupDay').value
    ].join('|');
});

document.getElementById('autoCancelEnabled').addEventListener('change', function() {
    document.getElementById('autoCancelTimeoutContainer').style.display = this.checked ? '' : 'none';
});

document.getElementById('autoCancelForm').addEventListener('submit', function(e) {
    e.preventDefault();
    if (document.getElementById('saveAutoCancelBtn').disabled) return;

    const formData = new FormData();
    formData.append('action', 'save_auto_cancel_settings');
    formData.append('auto_cancel_enabled', document.getElementById('autoCancelEnabled').checked ? '1' : '0');
    formData.append('auto_cancel_minutes', document.getElementById('autoCancelMinutes').value);

    fetch('settings_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (window.settingsSaveTrackers.autoCancel) window.settingsSaveTrackers.autoCancel.commit();
            alert('Auto-cancel settings saved successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(() => {
        alert('Failed to save auto-cancel settings');
    });
});

document.getElementById('securitySettingsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    if (document.getElementById('saveSecurityBtn').disabled) return;

    const formData = new FormData();
    formData.append('action', 'save_security_settings');
    formData.append('session_timeout', document.getElementById('sessionTimeout').value);

    fetch('settings_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (window.settingsSaveTrackers.security) window.settingsSaveTrackers.security.commit();
            alert('Security settings saved successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(() => {
        alert('Failed to save security settings');
    });
});

</script>
<script src="../../assets/js/two_factor_setup.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    initTwoFactorSetup({
        endpoint: 'settings_actions.php',
        initiallyEnabled: <?php echo $adminTwoFactorEnabled ? 'true' : 'false'; ?>
    });
});
</script>
<script>
document.querySelectorAll('.schedule-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.schedule-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.schedule-panel').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('panel-' + this.dataset.tab).classList.add('active');
    });
});

// Initialize manual mode if needed
document.querySelectorAll('.day-schedule').forEach(row => {
    const openSelect = row.querySelector('select.time-open');
    const openVal = row.querySelector('.time-open-manual').value;
    const closeSelect = row.querySelector('select.time-close');
    const closeVal = row.querySelector('.time-close-manual').value;
    
    let needsManual = false;
    // Check if current values exist in dropdown options
    if (openVal && !Array.from(openSelect.options).some(opt => opt.value === openVal)) needsManual = true;
    if (closeVal && !Array.from(closeSelect.options).some(opt => opt.value === closeVal)) needsManual = true;
    
    if (needsManual) {
        const manualCheckbox = row.querySelector('.manual-toggle-checkbox');
        manualCheckbox.checked = true;
        manualCheckbox.dispatchEvent(new Event('change'));
    }
});

trackSettingsForm('hours', document.getElementById('businessHoursForm'), document.getElementById('saveHoursBtn'), function() {
    var parts = [];
    document.querySelectorAll('#businessHoursForm .day-schedule').forEach(function(row) {
        var isManual = row.querySelector('.manual-toggle-checkbox').checked;
        var openVal = isManual ? row.querySelector('.time-open-manual').value : row.querySelector('select.time-open').value;
        var closeVal = isManual ? row.querySelector('.time-close-manual').value : row.querySelector('select.time-close').value;
        parts.push([
            row.dataset.day,
            row.querySelector('.is-open-checkbox').checked ? '1' : '0',
            isManual ? '1' : '0',
            openVal,
            closeVal
        ].join('|'));
    });
    return parts.join(';');
});

document.querySelectorAll('.manual-toggle-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const row = this.closest('.day-schedule');
        const selects = row.querySelectorAll('.time-select');
        const manuals = row.querySelectorAll('.time-input-manual');
        
        if (this.checked) {
            selects.forEach(s => s.style.display = 'none');
            manuals.forEach(m => m.style.display = 'block');
        } else {
            selects.forEach(s => s.style.display = 'block');
            manuals.forEach(m => m.style.display = 'none');
        }
    });
});

document.querySelectorAll('.is-open-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const row = this.closest('.day-schedule');
        if (this.checked) {
            row.classList.remove('day-closed');
        } else {
            row.classList.add('day-closed');
        }
    });
});

document.getElementById('businessHoursForm').addEventListener('submit', function(e) {
    e.preventDefault();
    if (document.getElementById('saveHoursBtn').disabled) return;
    
    const businessHours = {};
    document.querySelectorAll('.day-schedule').forEach(row => {
        const day = row.dataset.day;
        const isManual = row.querySelector('.manual-toggle-checkbox').checked;
        const openVal = isManual ? row.querySelector('.time-open-manual').value : row.querySelector('select.time-open').value;
        const closeVal = isManual ? row.querySelector('.time-close-manual').value : row.querySelector('select.time-close').value;
        
        businessHours[day] = {
            open: openVal,
            close: closeVal,
            is_open: row.querySelector('.is-open-checkbox').checked
        };
    });
    
    fetch('settings_actions.php?action=save_business_hours', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ business_hours: businessHours })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (window.settingsSaveTrackers.hours) window.settingsSaveTrackers.hours.commit();
            alert('Business hours saved successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(() => {
        alert('Failed to save business hours');
    });
});

let calYear, calMonth;
let unavailableDates = <?php echo json_encode($unavailableDates); ?>;

function initCalendar() {
    const today = new Date();
    calYear = today.getFullYear();
    calMonth = today.getMonth();
    renderCalendar();
    renderUnavailableList();
}

function changeCalMonth(dir) {
    calMonth += dir;
    if (calMonth < 0) { calMonth = 11; calYear--; }
    if (calMonth > 11) { calMonth = 0; calYear++; }
    renderCalendar();
}

function renderCalendar() {
    document.getElementById('calMonthYear').textContent = new Date(calYear, calMonth).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    
    const grid = document.getElementById('calGrid');
    grid.innerHTML = '';
    
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    days.forEach(d => {
        const h = document.createElement('div');
        h.className = 'cal-day-header';
        h.textContent = d;
        grid.appendChild(h);
    });
    
    const firstDay = new Date(calYear, calMonth, 1).getDay();
    const totalDays = new Date(calYear, calMonth + 1, 0).getDate();
    const prevDays = new Date(calYear, calMonth, 0).getDate();
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    for (let i = firstDay - 1; i >= 0; i--) {
        const d = document.createElement('div');
        d.className = 'cal-day empty';
        d.textContent = prevDays - i;
        grid.appendChild(d);
    }
    
    for (let i = 1; i <= totalDays; i++) {
        const d = document.createElement('div');
        const dateStr = `${calYear}-${String(calMonth + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
        const dateObj = new Date(calYear, calMonth, i);
        
        d.textContent = i;
        d.className = 'cal-day';
        d.dataset.date = dateStr;
        
        if (dateObj.getTime() === today.getTime()) {
            d.classList.add('today');
        }
        
        const entry = unavailableDates.find(e => e.date === dateStr);
        if (entry) {
            d.classList.add('status-' + entry.status);
            const indicator = document.createElement('div');
            indicator.className = 'status-indicator ' + entry.status;
            d.appendChild(indicator);
            d.title = entry.note || entry.status;
        }
        
        d.addEventListener('click', () => openDateModal(dateStr));
        grid.appendChild(d);
    }
    
    const remaining = 42 - (firstDay + totalDays);
    for (let i = 1; i <= remaining; i++) {
        const d = document.createElement('div');
        d.className = 'cal-day empty';
        d.textContent = i;
        grid.appendChild(d);
    }
}

function renderUnavailableList() {
    const container = document.getElementById('unavailableItems');
    if (unavailableDates.length === 0) {
        container.innerHTML = '<p style="color: #6b7280; text-align: center; padding: 20px;">No unavailable dates configured</p>';
        return;
    }
    
    container.innerHTML = '';
    unavailableDates.forEach(entry => {
        const item = document.createElement('div');
        item.className = 'unavailable-item';
        
        const dateFormatted = new Date(entry.date + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        
        item.innerHTML = `
            <span class="unavailable-date">${dateFormatted}</span>
            <span class="unavailable-status status-badge-${entry.status}">${entry.status.replace('_', ' ').toUpperCase()}</span>
            <span style="color: #6b7280; font-size: 14px;">${entry.note || '-'}</span>
            <button class="delete-date-btn" onclick="deleteUnavailableDate('${entry.date}')">&times;</button>
        `;
        container.appendChild(item);
    });
}

let selectedDateForModal = null;

function openDateModal(dateStr) {
    selectedDateForModal = dateStr;
    const entry = unavailableDates.find(e => e.date === dateStr);
    
    document.getElementById('modalDateTitle').textContent = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    document.getElementById('dateStatus').value = entry ? entry.status : 'open';
    document.getElementById('dateNote').value = entry ? entry.note || '' : '';
    
    if (entry && entry.status === 'custom') {
        document.getElementById('customHoursFields').style.display = 'block';
        document.getElementById('customOpen').value = entry.custom_open || '';
        document.getElementById('customClose').value = entry.custom_close || '';
    } else {
        document.getElementById('customHoursFields').style.display = 'none';
    }
    
    document.getElementById('deleteDateBtn').style.display = entry ? 'inline-block' : 'none';
    document.getElementById('dateStatusModal').classList.add('active');
}

function closeDateModal() {
    document.getElementById('dateStatusModal').classList.remove('active');
    selectedDateForModal = null;
}

document.getElementById('dateStatus').addEventListener('change', function() {
    document.getElementById('customHoursFields').style.display = this.value === 'custom' ? 'block' : 'none';
});

function saveDateStatus() {
    const status = document.getElementById('dateStatus').value;
    const note = document.getElementById('dateNote').value;
    const customOpen = document.getElementById('customOpen').value;
    const customClose = document.getElementById('customClose').value;
    
    if (status === 'open') {
        deleteDateStatus(true);
        return;
    }
    
    fetch('settings_actions.php?action=save_unavailable_date', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            date: selectedDateForModal,
            status: status,
            note: note,
            custom_open: customOpen,
            custom_close: customClose
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const idx = unavailableDates.findIndex(e => e.date === selectedDateForModal);
            const newEntry = { date: selectedDateForModal, status, note, custom_open: customOpen, custom_close: customClose };
            if (idx !== -1) {
                unavailableDates[idx] = newEntry;
            } else {
                unavailableDates.push(newEntry);
                unavailableDates.sort((a, b) => a.date.localeCompare(b.date));
            }
            closeDateModal();
            renderCalendar();
            renderUnavailableList();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(() => {
        alert('Failed to save date status');
    });
}

function deleteDateStatus(silent = false) {
    if (!silent && !confirm('Remove this date from unavailable dates?')) return;
    
    fetch('settings_actions.php?action=delete_unavailable_date', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ date: selectedDateForModal })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            unavailableDates = unavailableDates.filter(e => e.date !== selectedDateForModal);
            closeDateModal();
            renderCalendar();
            renderUnavailableList();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(() => {
        alert('Failed to delete date');
    });
}

function deleteUnavailableDate(dateStr) {
    selectedDateForModal = dateStr;
    deleteDateStatus(false);
}

function bulkAddDates() {
    const startDate = document.getElementById('bulkStartDate').value;
    const endDate = document.getElementById('bulkEndDate').value;
    const status = document.getElementById('bulkStatus').value;
    const note = document.getElementById('bulkNote').value;
    
    if (!startDate || !endDate) {
        alert('Please select both start and end dates');
        return;
    }
    
    fetch('settings_actions.php?action=bulk_add_unavailable_dates', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            start_date: startDate,
            end_date: endDate,
            status: status,
            note: note
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            fetch('settings_actions.php?action=get_unavailable_dates')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        unavailableDates = data.unavailable_dates;
                        renderCalendar();
                        renderUnavailableList();
                    }
                });
            document.getElementById('bulkStartDate').value = '';
            document.getElementById('bulkEndDate').value = '';
            document.getElementById('bulkNote').value = '';
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(() => {
        alert('Failed to add dates');
    });
}

initCalendar();

(function () {
    var openBtn = document.getElementById('openBackupDownload');
    var monthsModal = document.getElementById('backupMonthsModal');
    var passwordModal = document.getElementById('backupPasswordModal');
    if (!openBtn || !monthsModal || !passwordModal) return;

    var monthsSelect = document.getElementById('backupMonths');
    var passwordInput = document.getElementById('backupPassword');
    var passwordError = document.getElementById('backupPasswordError');
    var downloadBtn = document.getElementById('confirmBackupDownload');
    var autoForm = document.getElementById('autoBackupForm');
    var autoEnabled = document.getElementById('autoBackupEnabled');
    var autoSchedule = document.getElementById('autoBackupSchedule');
    var autoMode = document.getElementById('autoBackupMode');
    var autoDateWrap = document.getElementById('autoBackupDateWrap');
    var autoDayWrap = document.getElementById('autoBackupDayWrap');

    function showPasswordError(message) {
        passwordError.textContent = message;
        passwordError.hidden = false;
    }

    function clearPasswordError() {
        passwordError.textContent = '';
        passwordError.hidden = true;
    }

    function closeBackupModals() {
        monthsModal.classList.remove('active');
        passwordModal.classList.remove('active');
        passwordInput.value = '';
        clearPasswordError();
        downloadBtn.disabled = false;
        downloadBtn.textContent = 'Download backup';
    }

    function syncAutoBackupFields() {
        autoSchedule.style.display = autoEnabled.checked ? '' : 'none';
        var monthly = autoMode.value === 'month';
        autoDateWrap.style.display = monthly ? 'none' : '';
        autoDayWrap.style.display = monthly ? '' : 'none';
    }

    openBtn.addEventListener('click', function () {
        monthsModal.classList.add('active');
    });
    document.getElementById('closeBackupMonthsModal').addEventListener('click', closeBackupModals);
    document.getElementById('cancelBackupMonths').addEventListener('click', closeBackupModals);
    document.getElementById('closeBackupPasswordModal').addEventListener('click', closeBackupModals);
    document.getElementById('continueBackupMonths').addEventListener('click', function () {
        monthsModal.classList.remove('active');
        clearPasswordError();
        passwordModal.classList.add('active');
        passwordInput.focus();
    });
    document.getElementById('backToBackupMonths').addEventListener('click', function () {
        passwordModal.classList.remove('active');
        passwordInput.value = '';
        clearPasswordError();
        monthsModal.classList.add('active');
    });

    downloadBtn.addEventListener('click', function () {
        var password = passwordInput.value;
        if (!password) {
            showPasswordError('Enter your current password.');
            return;
        }

        downloadBtn.disabled = true;
        downloadBtn.textContent = 'Creating backup…';
        clearPasswordError();

        var formData = new FormData();
        formData.append('months', monthsSelect.value);
        formData.append('password', password);

        fetch('../api/backup_database.php', { method: 'POST', body: formData })
            .then(function (res) {
                var type = res.headers.get('content-type') || '';
                if (type.indexOf('application/json') !== -1) {
                    return res.json().then(function (data) {
                        throw new Error((data && data.message) || 'The backup could not be created.');
                    });
                }
                if (!res.ok) {
                    throw new Error('The backup could not be created.');
                }
                var created = res.headers.get('X-Backup-Created') || '';
                var size = res.headers.get('X-Backup-Size') || '';
                var match = (res.headers.get('content-disposition') || '').match(/filename="([^"]+)"/);
                var filename = match ? match[1] : 'clinic_backup.sql';
                return res.blob().then(function (blob) {
                    return { blob: blob, filename: filename, created: created, size: size };
                });
            })
            .then(function (file) {
                var url = URL.createObjectURL(file.blob);
                var link = document.createElement('a');
                link.href = url;
                link.download = file.filename;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);
                if (file.created) document.getElementById('backupLastValue').textContent = file.created;
                if (file.size) document.getElementById('backupSizeValue').textContent = file.size;
                closeBackupModals();
            })
            .catch(function (err) {
                showPasswordError(err.message || 'The backup could not be created.');
                downloadBtn.disabled = false;
                downloadBtn.textContent = 'Download backup';
            });
    });

    passwordInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            downloadBtn.click();
        }
    });

    autoEnabled.addEventListener('change', syncAutoBackupFields);
    autoMode.addEventListener('change', syncAutoBackupFields);

    autoForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (document.getElementById('saveAutoBackupBtn').disabled) return;
        var formData = new FormData();
        formData.append('action', 'save_auto_backup');
        formData.append('auto_backup_enabled', autoEnabled.checked ? '1' : '0');
        formData.append('auto_backup_time', document.getElementById('autoBackupTime').value || '02:00');
        formData.append('auto_backup_mode', autoMode.value);
        formData.append('auto_backup_date', document.getElementById('autoBackupDate').value || '');
        formData.append('auto_backup_day', document.getElementById('autoBackupDay').value || '1');

        fetch('settings_actions.php', { method: 'POST', body: formData })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    if (window.settingsSaveTrackers.autoBackup) window.settingsSaveTrackers.autoBackup.commit();
                    alert('Auto backup saved successfully!');
                } else {
                    alert('Error: ' + (data.message || 'Could not save auto backup'));
                }
            })
            .catch(function () {
                alert('Failed to save auto backup');
            });
    });
})();
</script>
