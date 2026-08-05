<?php
$pageTitle = 'Dentist Schedule';

require_once '../../config/database.php';

// Inline migration: ensure schedules table has required columns
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM schedules LIKE 'start_time'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE schedules ADD COLUMN start_time TIME DEFAULT '08:00:00'");
        $pdo->exec("ALTER TABLE schedules ADD COLUMN end_time TIME DEFAULT '17:00:00'");
        $pdo->exec("ALTER TABLE schedules ADD COLUMN is_available TINYINT(1) DEFAULT 1");
    }
} catch (Exception $e) {
    // Silent fail — page still works with defaults
}

// Inline migration: create dentist_unavailable table for date-specific overrides
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS dentist_unavailable (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dentist_id INT NOT NULL,
        unavailable_date DATE NOT NULL,
        is_full_day TINYINT(1) DEFAULT 1,
        start_time TIME DEFAULT NULL,
        end_time TIME DEFAULT NULL,
        reason VARCHAR(255) DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (dentist_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY uq_dentist_date (dentist_id, unavailable_date)
    )");
} catch (Exception $e) {
    // Silent fail
}

try {
    // Get all dentists
    $dentists = $pdo->query("
        SELECT u.id, u.first_name, u.middle_name, u.last_name, u.email, u.phone
        FROM users u
        WHERE u.role = 'Dentist' AND (u.is_archived = 0 OR u.is_archived IS NULL)
        ORDER BY u.first_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Get existing schedules
    $schedules = $pdo->query("SELECT * FROM schedules ORDER BY doctor_id, FIELD(available_day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')")->fetchAll(PDO::FETCH_ASSOC);
    
    // Index schedules by doctor_id + available_day
    $schedIndex = [];
    foreach ($schedules as $s) {
        $schedIndex[$s['doctor_id'] . '_' . $s['available_day']] = $s;
    }

    // Get date-specific overrides for all dentists
    $dateOverrides = $pdo->query("SELECT * FROM dentist_unavailable ORDER BY dentist_id, unavailable_date")->fetchAll(PDO::FETCH_ASSOC);
    $overrideIndex = [];
    foreach ($dateOverrides as $o) {
        $overrideIndex[$o['dentist_id'] . '_' . $o['unavailable_date']] = $o;
    }

    $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $dayLabels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
} catch (Exception $e) {
    $dentists = [];
    $schedIndex = [];
    $overrideIndex = [];
    $error = $e->getMessage();
}

require_once '../../includes/staff_layout_start.php';
?>

<div class="page-header">
    <div>
        <h1 style="margin: 0; font-size: 1.5rem; font-weight: 600; color: #1e293b;">Dentist Schedule</h1>
        <p style="margin: 4px 0 0; color: #64748b; font-size: 0.875rem;">Manage dentist availability for appointments</p>
    </div>
    <button onclick="saveAllSchedules()" class="btn-primary" style="display: flex; align-items: center; gap: 8px; padding: 10px 24px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Save All Changes
    </button>
</div>

<?php if (!empty($error)): ?>
    <div style="padding: 16px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #dc2626; margin-bottom: 20px;">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div id="scheduleContainer">
    <?php if (empty($dentists)): ?>
        <div style="text-align: center; padding: 60px; color: #64748b;">
            <p style="font-size: 16px;">No dentists found. Please add a dentist user first.</p>
        </div>
    <?php else: ?>
        <?php foreach ($dentists as $dentist): ?>
            <?php
                $fullName = trim($dentist['first_name'] . ' ' . ($dentist['middle_name'] ? $dentist['middle_name'] . ' ' : '') . $dentist['last_name']);
            ?>
            <div class="dentist-schedule-card" data-doctor-id="<?php echo $dentist['id']; ?>" style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 20px; overflow: hidden;">
                <div style="padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #3b82f6; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 16px;">
                        <?php echo strtoupper(substr($dentist['first_name'], 0, 1) . substr($dentist['last_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 1rem; font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($fullName); ?></h3>
                        <span style="font-size: 0.8rem; color: #64748b;"><?php echo htmlspecialchars($dentist['email'] ?? ''); ?></span>
                    </div>
                    <?php
                        $allDaysEnabled = true;
                        foreach ($daysOfWeek as $chkDay) {
                            $chkKey = $dentist['id'] . '_' . $chkDay;
                            $chkSched = $schedIndex[$chkKey] ?? null;
                            if (!($chkSched ? (int)$chkSched['is_available'] : 1)) { $allDaysEnabled = false; break; }
                        }
                    ?>
                    <div style="margin-left: auto; display: flex; align-items: center; gap: 8px;">
                        <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: #64748b; cursor: pointer;">
                            <input type="checkbox" class="toggle-all-days" data-doctor="<?php echo $dentist['id']; ?>" <?php echo $allDaysEnabled ? 'checked' : ''; ?>>
                            All days enabled
                        </label>
                    </div>
                </div>
                <div style="padding: 16px 20px;">
                    <div class="schedule-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px;">
                        <?php foreach ($daysOfWeek as $dayIndex => $day): ?>
                            <?php
                                $key = $dentist['id'] . '_' . $day;
                                $sched = $schedIndex[$key] ?? null;
                                $isAvailable = $sched ? (int)$sched['is_available'] : 1;
                                $startTime = $sched ? substr($sched['start_time'], 0, 5) : '08:00';
                                $endTime = $sched ? substr($sched['end_time'], 0, 5) : '17:00';
                            ?>
                            <div class="day-card" data-doctor="<?php echo $dentist['id']; ?>" data-day="<?php echo $day; ?>" style="border: 1px solid <?php echo $isAvailable ? '#3b82f6' : '#e2e8f0'; ?>; border-radius: 8px; padding: 10px; background: <?php echo $isAvailable ? '#eff6ff' : '#f8fafc'; ?>;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <span style="font-size: 12px; font-weight: 600; color: #475569; text-transform: uppercase;"><?php echo substr($day, 0, 3); ?></span>
                                    <label style="display: flex; align-items: center;">
                                        <input type="checkbox" class="day-toggle" data-doctor="<?php echo $dentist['id']; ?>" data-day="<?php echo $day; ?>" <?php echo $isAvailable ? 'checked' : ''; ?> style="width: 16px; height: 16px; cursor: pointer;">
                                    </label>
                                </div>
                                <div class="time-inputs" style="display: <?php echo $isAvailable ? 'flex' : 'none'; ?>; flex-direction: column; gap: 4px;">
                                    <div style="display: flex; align-items: center; gap: 4px;">
                                        <span style="font-size: 10px; color: #64748b; min-width: 14px;">F</span>
                                        <input type="time" class="start-time" value="<?php echo $startTime; ?>" data-doctor="<?php echo $dentist['id']; ?>" data-day="<?php echo $day; ?>" style="width: 100%; padding: 4px 6px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 12px; outline: none;">
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 4px;">
                                        <span style="font-size: 10px; color: #64748b; min-width: 14px;">T</span>
                                        <input type="time" class="end-time" value="<?php echo $endTime; ?>" data-doctor="<?php echo $dentist['id']; ?>" data-day="<?php echo $day; ?>" style="width: 100%; padding: 4px 6px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 12px; outline: none;">
                                    </div>
                                </div>
                                <div class="off-label" style="display: <?php echo $isAvailable ? 'none' : 'block'; ?>; text-align: center; padding: 14px 0; font-size: 11px; color: #94a3b8; font-weight: 500;">
                                    Day Off/leave
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- Date Overrides Section -->
                <?php
                    $docOvCount = 0;
                    foreach ($overrideIndex as $oKey => $oVal) {
                        if (strpos($oKey, $dentist['id'] . '_') === 0) $docOvCount++;
                    }
                ?>
                <div style="padding: 0 20px 16px; border-top: 1px solid #e5e7eb;">
                    <details class="date-overrides-section" data-doctor="<?php echo $dentist['id']; ?>">
                        <summary style="padding: 12px 0; cursor: pointer; font-size: 13px; font-weight: 600; color: #374151; display: flex; align-items: center; gap: 8px; user-select: none;">
                            <span>📅 Date Overrides</span>
                            <span class="override-badge" id="overrideBadge_<?php echo $dentist['id']; ?>" style="font-size: 11px; background: #e5e7eb; color: #374151; padding: 1px 8px; border-radius: 9999px;"><?php echo $docOvCount; ?></span>
                        </summary>
                        <div class="override-cal">
                            <div class="override-cal-nav">
                                <button type="button" onclick="changeOverrideMonth(<?php echo $dentist['id']; ?>, -1)" style="background: none; border: 1px solid #d1d5db; border-radius: 4px; padding: 2px 8px; cursor: pointer; font-size: 14px;">&#9664;</button>
                                <strong class="override-cal-title" id="overrideCalTitle_<?php echo $dentist['id']; ?>"></strong>
                                <button type="button" onclick="changeOverrideMonth(<?php echo $dentist['id']; ?>, 1)" style="background: none; border: 1px solid #d1d5db; border-radius: 4px; padding: 2px 8px; cursor: pointer; font-size: 14px;">&#9654;</button>
                            </div>
                            <div class="override-cal-grid" id="overrideCalGrid_<?php echo $dentist['id']; ?>"></div>
                        </div>
                    </details>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div id="saveStatus" style="position: fixed; bottom: 20px; right: 20px; padding: 12px 24px; border-radius: 8px; font-size: 14px; font-weight: 500; display: none; z-index: 9999;"></div>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
}
.btn-primary:hover { opacity: 0.9; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.day-card { transition: all 0.2s ease; }
.day-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.time-inputs input[type="time"]:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.15); }

.override-cal { margin-top: 8px; }
.override-cal-nav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.override-cal-title { font-size: 14px; color: #1e293b; }
.override-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; text-align: center; }
.override-cal-grid .cal-day-header { font-size: 10px; font-weight: 600; color: #6b7280; padding: 4px 0; text-transform: uppercase; }
.override-cal-grid .cal-day { padding: 6px 0; font-size: 12px; border-radius: 4px; cursor: pointer; color: #374151; transition: all 0.1s; }
.override-cal-grid .cal-day:hover { background: #eff6ff; }
.override-cal-grid .cal-day.other-month { color: #d1d5db; }
.override-cal-grid .cal-day.today { font-weight: 700; background: #dbeafe; }
.override-cal-grid .cal-day.absent { background: #fef2f2; color: #dc2626; font-weight: 600; border: 1px solid #fecaca; }
.override-cal-grid .cal-day.absent:hover { background: #fee2e2; }
.override-cal-grid .cal-day.custom { background: #fff7ed; color: #c2410c; font-weight: 600; border: 1px solid #fed7aa; }
.override-cal-grid .cal-day.custom:hover { background: #ffedd5; }

.date-overrides-section[open] .override-badge { background: #3b82f6; color: white; }

.override-popup-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.4); z-index: 999999; justify-content: center; align-items: center; }
.override-popup-overlay.active { display: flex; }
.override-popup { background: white; border-radius: 12px; padding: 24px; width: 360px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); position: relative; }
.override-popup h3 { margin: 0 0 16px; font-size: 1rem; font-weight: 600; color: #1e293b; }
.override-popup .form-group { margin-bottom: 12px; }
.override-popup label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 4px; }
.override-popup .radio-group { display: flex; gap: 16px; margin-bottom: 12px; }
.override-popup .radio-group label { font-weight: 400; display: flex; align-items: center; gap: 6px; cursor: pointer; }
.override-popup .time-row { display: flex; gap: 8px; align-items: center; }
.override-popup .time-row input[type=time] { flex: 1; padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; }
.override-popup .popup-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px; padding-top: 16px; border-top: 1px solid #e5e7eb; }
.override-popup .popup-actions button { padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
.override-popup .btn-remove { background: #fef2f2; color: #dc2626; }
.override-popup .btn-remove:hover { background: #fee2e2; }
.override-popup .btn-save { background: #3b82f6; color: white; }
.override-popup .btn-save:hover { background: #2563eb; }
.override-popup .btn-cancel { background: #f3f4f6; color: #374151; }
.override-popup .btn-cancel:hover { background: #e5e7eb; }

@media (max-width: 900px) {
    .schedule-grid { grid-template-columns: repeat(4, 1fr) !important; }
}
@media (max-width: 600px) {
    .schedule-grid { grid-template-columns: repeat(2, 1fr) !important; }
}
</style>

<script>
var dateOverridesData = <?php echo json_encode($dateOverrides); ?>;
var overrideCalMonths = {};
var overridePending = {}; // { 'doctorId_date': { is_full_day, start_time, end_time, reason } }

document.querySelectorAll('.day-toggle').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var card = this.closest('.day-card');
        var timeInputs = card.querySelector('.time-inputs');
        var offLabel = card.querySelector('.off-label');
        if (this.checked) {
            timeInputs.style.display = 'flex';
            offLabel.style.display = 'none';
            card.style.borderColor = '#3b82f6';
            card.style.background = '#eff6ff';
        } else {
            timeInputs.style.display = 'none';
            offLabel.style.display = 'block';
            card.style.borderColor = '#e2e8f0';
            card.style.background = '#f8fafc';
        }
    });
});

document.querySelectorAll('.toggle-all-days').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var doctorId = this.dataset.doctor;
        var dayToggles = document.querySelectorAll('.day-toggle[data-doctor="' + doctorId + '"]');
        dayToggles.forEach(function(t) {
            t.checked = cb.checked;
            t.dispatchEvent(new Event('change'));
        });
    });
});

function showSaveStatus(message, isError) {
    var el = document.getElementById('saveStatus');
    el.textContent = message;
    el.style.background = isError ? '#fef2f2' : '#eff6ff';
    el.style.color = isError ? '#dc2626' : '#1d4ed8';
    el.style.border = '1px solid ' + (isError ? '#fecaca' : '#93c5fd');
    el.style.display = 'block';
    setTimeout(function() { el.style.display = 'none'; }, 3000);
}

function saveAllSchedules() {
    var btn = document.querySelector('.btn-primary');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    var scheduleData = [];
    document.querySelectorAll('.dentist-schedule-card').forEach(function(card) {
        var doctorId = card.dataset.doctorId;
        document.querySelectorAll('.day-card[data-doctor="' + doctorId + '"]').forEach(function(dayCard) {
            var day = dayCard.dataset.day;
            var toggle = dayCard.querySelector('.day-toggle');
            var startTime = dayCard.querySelector('.start-time').value;
            var endTime = dayCard.querySelector('.end-time').value;
            var isAvailable = toggle.checked ? 1 : 0;
            if (isAvailable && !startTime) startTime = '08:00';
            if (isAvailable && !endTime) endTime = '17:00';
            scheduleData.push({
                doctor_id: doctorId,
                day: day,
                start_time: startTime,
                end_time: endTime,
                is_available: isAvailable
            });
        });
    });

    // Collect date overrides
    var overrideData = [];
    var removedOverrides = [];
    for (var key in overridePending) {
        if (!overridePending.hasOwnProperty(key)) continue;
        var parts = key.split('_');
        var doctorId = parts[0];
        var date = parts.slice(1).join('_');
        var ov = overridePending[key];
        if (ov._removed) {
            // This was saved in DB but removed by user — tell server to delete
            removedOverrides.push({ doctor_id: parseInt(doctorId), date: date });
            continue;
        }
        overrideData.push({
            doctor_id: parseInt(doctorId),
            date: date,
            is_full_day: ov.is_full_day ? 1 : 0,
            start_time: ov.start_time || null,
            end_time: ov.end_time || null,
            reason: ov.reason || ''
        });
    }

    fetch('staff_dentist_schedule.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_schedules', schedules: scheduleData, date_overrides: overrideData, removed_overrides: removedOverrides })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showSaveStatus('All schedules saved successfully!');
        } else {
            showSaveStatus(data.message || 'Error saving schedules', true);
        }
    })
    .catch(function(err) {
        showSaveStatus('Network error: ' + err.message, true);
    })
    .finally(function() {
        btn.disabled = false;
        btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Save All Changes';
    });
}

/* ─── Date Override Calendar ─── */
function initOverrideCalendars() {
    document.querySelectorAll('.dentist-schedule-card').forEach(function(card) {
        var doctorId = card.dataset.doctorId;
        var now = new Date();
        overrideCalMonths[doctorId] = { year: now.getFullYear(), month: now.getMonth() };
        renderOverrideCalendar(doctorId);
    });
}

function changeOverrideMonth(doctorId, dir) {
    var m = overrideCalMonths[doctorId];
    if (!m) return;
    m.month += dir;
    if (m.month < 0) { m.month = 11; m.year--; }
    if (m.month > 11) { m.month = 0; m.year++; }
    renderOverrideCalendar(doctorId);
}

function renderOverrideCalendar(doctorId) {
    var m = overrideCalMonths[doctorId];
    if (!m) return;
    var grid = document.getElementById('overrideCalGrid_' + doctorId);
    var title = document.getElementById('overrideCalTitle_' + doctorId);
    if (!grid || !title) return;

    var monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    title.textContent = monthNames[m.month] + ' ' + m.year;

    grid.innerHTML = '';

    var days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    days.forEach(function(d) {
        var h = document.createElement('div');
        h.className = 'cal-day-header';
        h.textContent = d;
        grid.appendChild(h);
    });

    var firstDay = new Date(m.year, m.month, 1).getDay();
    var totalDays = new Date(m.year, m.month + 1, 0).getDate();
    var today = new Date();
    today.setHours(0, 0, 0, 0);

    for (var i = 0; i < firstDay; i++) {
        var e = document.createElement('div');
        e.className = 'cal-day other-month';
        e.textContent = '';
        grid.appendChild(e);
    }

    for (var d = 1; d <= totalDays; d++) {
        var dateObj = new Date(m.year, m.month, d);
        var dateStr = m.year + '-' + String(m.month + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
        var el = document.createElement('div');
        el.className = 'cal-day';
        el.textContent = d;

        if (dateObj.getTime() === today.getTime()) el.classList.add('today');

        // Check for overrides
        var ovKey = doctorId + '_' + dateStr;
        var existingOv = dateOverridesData.find(function(o) {
            return String(o.dentist_id) === String(doctorId) && o.unavailable_date === dateStr;
        });
        var pendingOv = overridePending[ovKey];

        if (pendingOv) {
            if (pendingOv._removed) {
                // Was saved but removed pending — show as normal
            } else if (pendingOv.is_full_day) {
                el.classList.add('absent');
            } else {
                el.classList.add('custom');
            }
        } else if (existingOv) {
            if (parseInt(existingOv.is_full_day)) {
                el.classList.add('absent');
            } else {
                el.classList.add('custom');
            }
        }

        el.dataset.date = dateStr;
        el.addEventListener('click', function() { openOverridePopup(doctorId, this.dataset.date); });
        grid.appendChild(el);
    }

    // Update badge count
    updateOverrideBadge(doctorId);
}

function updateOverrideBadge(doctorId) {
    var badge = document.getElementById('overrideBadge_' + doctorId);
    if (!badge) return;
    var count = 0;
    dateOverridesData.forEach(function(o) {
        if (String(o.dentist_id) === String(doctorId) && !overridePending[doctorId + '_' + o.unavailable_date + '._removed']) count++;
    });
    for (var k in overridePending) {
        if (!overridePending.hasOwnProperty(k)) continue;
        if (k.indexOf(doctorId + '_') === 0 && !overridePending[k]._removed
            && !overridePending[k]._existing) count++;
    }
    badge.textContent = count;
}

/* ─── Override Popover ─── */
var activePopupDoctor = null;
var activePopupDate = null;

function openOverridePopup(doctorId, dateStr) {
    activePopupDoctor = doctorId;
    activePopupDate = dateStr;

    var popup = document.getElementById('overridePopup');
    var overlay = document.getElementById('overridePopupOverlay');
    var title = document.getElementById('overridePopupTitle');
    var reasonInput = document.getElementById('overridePopupReason');

    var d = new Date(dateStr + 'T00:00:00');
    title.textContent = d.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

    var ovKey = doctorId + '_' + dateStr;
    var existing = dateOverridesData.find(function(o) {
        return String(o.dentist_id) === String(doctorId) && o.unavailable_date === dateStr;
    });
    var pending = overridePending[ovKey];

    var isFullDay = true;
    var startTime = '08:00';
    var endTime = '12:00';
    var reason = '';

    if (pending) {
        if (pending._removed) {
            // removed — use defaults
        } else {
            isFullDay = pending.is_full_day;
            startTime = pending.start_time || '08:00';
            endTime = pending.end_time || '12:00';
            reason = pending.reason || '';
        }
    } else if (existing) {
        isFullDay = parseInt(existing.is_full_day) === 1;
        startTime = existing.start_time ? String(existing.start_time).slice(0, 5) : '08:00';
        endTime = existing.end_time ? String(existing.end_time).slice(0, 5) : '12:00';
        reason = existing.reason || '';
    }

    // Check if there's an existing saved override — track for badge
    if (existing && !pending) {
        overridePending[ovKey] = {
            is_full_day: isFullDay ? 1 : 0,
            start_time: isFullDay ? null : startTime,
            end_time: isFullDay ? null : endTime,
            reason: reason,
            _existing: true,
            _removed: false
        };
    }

    document.getElementById('overrideFullDay').checked = isFullDay;
    document.getElementById('overrideCustomHours').checked = !isFullDay;
    document.getElementById('overrideStartTime').value = startTime;
    document.getElementById('overrideEndTime').value = endTime;
    reasonInput.value = reason;

    toggleOverrideTimeInputs();

    document.body.appendChild(overlay);
    overlay.classList.add('active');
}

function toggleOverrideTimeInputs() {
    var isFullDay = document.getElementById('overrideFullDay').checked;
    document.getElementById('overrideTimeRow').style.display = isFullDay ? 'none' : 'flex';
}

function saveOverridePopup() {
    var doctorId = activePopupDoctor;
    var dateStr = activePopupDate;
    if (!doctorId || !dateStr) return;

    var isFullDay = document.getElementById('overrideFullDay').checked;
    var startTime = document.getElementById('overrideStartTime').value;
    var endTime = document.getElementById('overrideEndTime').value;
    var reason = document.getElementById('overridePopupReason').value;

    var ovKey = doctorId + '_' + dateStr;

    overridePending[ovKey] = {
        is_full_day: isFullDay ? 1 : 0,
        start_time: isFullDay ? null : (startTime || '08:00'),
        end_time: isFullDay ? null : (endTime || '12:00'),
        reason: reason,
        _existing: false,
        _removed: false
    };

    closeOverridePopup();
    renderOverrideCalendar(doctorId);
}

function removeOverridePopup() {
    var doctorId = activePopupDoctor;
    var dateStr = activePopupDate;
    if (!doctorId || !dateStr) return;

    var ovKey = doctorId + '_' + dateStr;
    var existing = dateOverridesData.find(function(o) {
        return String(o.dentist_id) === String(doctorId) && o.unavailable_date === dateStr;
    });

    if (existing) {
        // Mark for removal
        overridePending[ovKey] = { _removed: true };
    } else {
        delete overridePending[ovKey];
    }

    closeOverridePopup();
    renderOverrideCalendar(doctorId);
}

function closeOverridePopup() {
    document.getElementById('overridePopupOverlay').classList.remove('active');
    activePopupDoctor = null;
    activePopupDate = null;
}

document.addEventListener('DOMContentLoaded', function() {
    initOverrideCalendars();

    // Click outside popup to close
    document.getElementById('overridePopupOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeOverridePopup();
    });
});
</script>

<div id="overridePopupOverlay" class="override-popup-overlay">
    <div class="override-popup">
        <h3 id="overridePopupTitle">Date Override</h3>
        <div class="form-group">
            <label>Absence Type</label>
            <div class="radio-group">
                <label><input type="radio" name="overrideType" id="overrideFullDay" checked onchange="toggleOverrideTimeInputs()"> Absent all day</label>
                <label><input type="radio" name="overrideType" id="overrideCustomHours" onchange="toggleOverrideTimeInputs()"> Custom hours</label>
            </div>
        </div>
        <div class="form-group" id="overrideTimeRow" style="display:none;">
            <label>Time Range</label>
            <div class="time-row">
                <span style="font-size:12px;color:#64748b;">F</span>
                <input type="time" id="overrideStartTime" value="08:00">
                <span style="font-size:12px;color:#64748b;">T</span>
                <input type="time" id="overrideEndTime" value="12:00">
            </div>
        </div>
        <div class="form-group">
            <label>Reason (optional)</label>
            <input type="text" id="overridePopupReason" placeholder="e.g., Training, Sick leave..." style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;outline:none;">
        </div>
        <div class="popup-actions">
            <button class="btn-remove" onclick="removeOverridePopup()">Remove</button>
            <button class="btn-cancel" onclick="closeOverridePopup()">Cancel</button>
            <button class="btn-save" onclick="saveOverridePopup()">Save</button>
        </div>
    </div>
</div>

<?php
// Handle save action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (($input['action'] ?? '') !== 'save_schedules') {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
        }
        
        $schedules = $input['schedules'] ?? [];
        $pdo->beginTransaction();
        
        foreach ($schedules as $s) {
            $doctorId = (int)($s['doctor_id'] ?? 0);
            $day = $s['day'] ?? '';
            $startTime = $s['start_time'] ?? '08:00';
            $endTime = $s['end_time'] ?? '17:00';
            $isAvailable = (int)($s['is_available'] ?? 0);
            
            if (!$doctorId || !$day) continue;
            
            // Check if record exists
            $stmt = $pdo->prepare("SELECT id FROM schedules WHERE doctor_id = ? AND available_day = ?");
            $stmt->execute([$doctorId, $day]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $stmt = $pdo->prepare("UPDATE schedules SET start_time = ?, end_time = ?, is_available = ? WHERE id = ?");
                $stmt->execute([$startTime, $endTime, $isAvailable, $existing['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO schedules (doctor_id, available_day, start_time, end_time, is_available) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$doctorId, $day, $startTime, $endTime, $isAvailable]);
            }
        }
        
        // Save date overrides
        $dateOverrides = $input['date_overrides'] ?? [];
        foreach ($dateOverrides as $o) {
            $doctorId = (int)($o['doctor_id'] ?? 0);
            $date = $o['date'] ?? '';
            $isFullDay = (int)($o['is_full_day'] ?? 1);
            $startTime = !empty($o['start_time']) ? $o['start_time'] : null;
            $endTime = !empty($o['end_time']) ? $o['end_time'] : null;
            $reason = $o['reason'] ?? '';
            
            if (!$doctorId || !$date) continue;
            
            // Upsert
            $stmt = $pdo->prepare("SELECT id FROM dentist_unavailable WHERE dentist_id = ? AND unavailable_date = ?");
            $stmt->execute([$doctorId, $date]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $stmt = $pdo->prepare("UPDATE dentist_unavailable SET is_full_day = ?, start_time = ?, end_time = ?, reason = ? WHERE id = ?");
                $stmt->execute([$isFullDay, $startTime, $endTime, $reason, $existing['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO dentist_unavailable (dentist_id, unavailable_date, is_full_day, start_time, end_time, reason) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$doctorId, $date, $isFullDay, $startTime, $endTime, $reason]);
            }
        }
        
        // Handle removed overrides (ones that existed in DB but were removed by user)
        $removedOverrides = $input['removed_overrides'] ?? [];
        if (!empty($removedOverrides)) {
            foreach ($removedOverrides as $r) {
                $rDoctorId = (int)($r['doctor_id'] ?? 0);
                $rDate = $r['date'] ?? '';
                if ($rDoctorId && $rDate) {
                    $stmt = $pdo->prepare("DELETE FROM dentist_unavailable WHERE dentist_id = ? AND unavailable_date = ?");
                    $stmt->execute([$rDoctorId, $rDate]);
                }
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

require_once '../../includes/staff_layout_end.php';
?>
