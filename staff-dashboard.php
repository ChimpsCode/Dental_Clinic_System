<?php
$pageTitle = 'Dashboard';
require_once 'includes/staff_layout_start.php';
?>

<!-- VIEW 1: DASHBOARD -->
<div id="view-dashboard" class="view-section active">
    <!-- Summary Cards -->
    <div class="summary-cards">
        <div class="summary-card">
            <div class="summary-icon yellow">⏰</div>
            <div class="summary-info">
                <h3 id="count-waiting">3</h3>
                <p>Waiting</p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon green">✓</div>
            <div class="summary-info">
                <h3 id="count-completed">0</h3>
                <p>Completed</p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon red">⚠️</div>
            <div class="summary-info">
                <h3 id="count-cancelled">1</h3>
                <p>Cancelled</p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon gray">⊘</div>
            <div class="summary-info">
                <h3 id="count-skipped">0</h3>
                <p>Skipped</p>
            </div>
        </div>
    </div>

    <div class="two-column">
        <div class="left-column">
            <!-- Today's Schedule -->
            <div class="section-card">
                <h2 class="section-title">📅 Today's Schedule</h2>
                <div class="patient-list" id="schedule-list">
                    <div class="patient-item">
                        <div class="patient-info">
                            <div class="patient-name">Maria Santos</div>
                            <div class="patient-details">
                                <span class="status-badge now-serving">NOW SERVING</span>
                                <span class="patient-time">09:30 AM</span>
                            </div>
                            <div class="patient-treatment">Root Canal (Session 2)</div>
                            <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">Source: Phone Call</div>
                        </div>
                        <div class="patient-actions">
                            <button class="action-btn icon view-btn">👁️</button>
                        </div>
                    </div>
                    <div class="patient-item">
                        <div class="patient-info">
                            <div class="patient-name">Juan Dela Cruz</div>
                            <div class="patient-details">
                                <span class="status-badge waiting">Waiting</span>
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
                            <div class="patient-name">Ana Reyes</div>
                            <div class="patient-details">
                                <span class="status-badge waiting">Waiting</span>
                                <span class="patient-time">10:00 AM</span>
                            </div>
                            <div class="patient-treatment">Oral Prophylaxis</div>
                        </div>
                        <div class="patient-actions">
                            <button class="action-btn icon view-btn">👁️</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Live Queue Controller -->
            <div class="live-queue">
                <div class="live-queue-header">
                    <div class="live-queue-title">Live Queue Controller</div>
                    <div class="now-serving-badge"><span>👁️</span><span>Now Serving</span></div>
                </div>
                <div class="live-patient">
                    <div class="patient-name" id="live-patient-name">Maria Santos</div>
                    <div class="patient-details" style="margin-top: 10px;">
                        <span class="status-badge now-serving">In Chair</span>
                        <span class="patient-time" id="live-patient-time">09:30 AM</span>
                    </div>
                    <div class="patient-treatment" id="live-patient-treatment" style="margin-top: 8px;">Root Canal (Session 2)</div>
                </div>
                <button class="complete-btn" id="completeTreatmentBtn">
                    <span>✓</span> <span>Complete Treatment</span>
                </button>
                <div class="complete-btn-text">Click when patient treatment is finished</div>
            </div>

            <!-- Up Next -->
            <div class="section-card">
                <h2 class="section-title">⏭️ Up Next</h2>
                <div class="patient-list" id="up-next-list">
                    <div class="patient-item" id="next-juan">
                        <div class="patient-info">
                            <div class="patient-name">Juan Dela Cruz</div>
                            <div class="patient-details">
                                <span class="status-badge waiting">Waiting</span>
                                <span class="patient-time">09:00 AM</span>
                            </div>
                            <div class="patient-treatment">Root Canal (Session 2)</div>
                        </div>
                        <div class="patient-actions">
                            <button class="action-btn icon view-btn">👁️</button>
                            <button class="action-btn icon delete-btn" onclick="this.innerHTML = 'Calling...'; setTimeout(() => this.innerHTML = '📞', 2000)">📞</button>
                            <button class="action-btn icon delete-btn" onclick="moveToCancelled(this)">⚠️</button>
                        </div>
                    </div>
                    <div class="patient-item" id="next-ana">
                        <div class="patient-info">
                            <div class="patient-name">Ana Reyes</div>
                            <div class="patient-details">
                                <span class="status-badge waiting">Waiting</span>
                                <span class="patient-time">10:30 AM</span>
                            </div>
                            <div class="patient-treatment">Oral Prophylaxis</div>
                        </div>
                        <div class="patient-actions">
                            <button class="action-btn icon view-btn">👁️</button>
                            <button class="action-btn icon delete-btn" onclick="this.innerHTML = 'Calling...'; setTimeout(() => this.innerHTML = '📞', 2000)">📞</button>
                            <button class="action-btn icon delete-btn" onclick="moveToCancelled(this)">⚠️</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cancelled -->
            <div class="section-card">
                <h2 class="section-title">Cancelled</h2>
                <div class="patient-list" id="cancelled-list">
                    <div class="patient-item" id="cancelled-roberto">
                        <div class="patient-info">
                            <div class="patient-name" style="text-decoration: line-through; color: #9ca3af;">Roberto Garcia</div>
                            <div class="patient-details">
                                <span class="status-badge cancelled">Cancelled</span>
                            </div>
                            <div class="patient-treatment">Denture Adjustment</div>
                            <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">No show / Did not arrive</div>
                        </div>
                        <div class="patient-actions">
                            <button class="action-btn icon view-btn">👁️</button>
                            <button class="action-btn" style="background: #84cc16; color: white; padding: 8px 12px; border-radius: 6px; border: none; font-size: 12px; cursor: pointer;" onclick="requeuePatient('cancelled-roberto', 'Roberto Garcia', 'Denture Adjustment', '11:00 AM')">Re-queue</button>
                            <button class="action-btn icon delete-btn">🗑️</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Notifications & Reminders -->
        <div class="right-column">
            <div class="notification-box">
                <h3>🔔 Notification</h3>
                <div class="notification-item">
                    <div class="notification-title">Upcoming Appointment</div>
                    <div class="notification-detail">Next patient arriving in 10 mins</div>
                </div>
            </div>
             
            <div class="notification-box">
                <h3>✓ Daily Reminders / To-Do</h3>
                <ul class="reminder-list" id="reminder-list">
                    <li onclick="this.classList.toggle('checked')">Reply to Facebook inquiries from last night</li>
                    <li onclick="this.classList.toggle('checked')">Print consent form for tomorrow</li>
                    <li onclick="this.classList.toggle('checked')">Call inquiries for new stocks of Composites</li>
                </ul>
                <button class="add-reminder-btn" id="addReminderBtn">+ Add New Reminder</button>
                <a href="#" class="see-all-link">See all reminders</a>
            </div>
        </div>
    </div>
</div>

<!-- VIEW 2: APPOINTMENTS -->
<div id="view-appointments" class="view-section">
    <!-- Appointment Stats Header -->
    <div class="appt-stats-grid">
        <div class="appt-stat-card">
            <h4 id="appt-total">0</h4>
            <p>Total Appointments</p>
        </div>
        <div class="appt-stat-card">
            <h4 id="appt-today">0</h4>
            <p>Today</p>
        </div>
        <div class="appt-stat-card">
            <h4 id="appt-tomorrow">0</h4>
            <p>Tomorrow</p>
        </div>
        <div class="appt-stat-card">
            <h4 id="appt-upcoming">0</h4>
            <p>Upcoming</p>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="search-filters">
        <div class="filter-tabs">
            <span class="active">All (0)</span>
            <span>Upcoming</span>
            <span>Completed</span>
            <span>Cancelled</span>
        </div>
        <input type="text" class="search-input" placeholder="Search appointments...">
    </div>

    <!-- Empty State (Simulated) -->
    <div class="empty-state" id="appt-empty-state">
        <h3>No Appointments Found</h3>
        <p>Create your first appointment to get started.</p>
    </div>

    <!-- Add Button -->
    <div style="margin-top: 20px; display: flex; justify-content: center;">
        <button class="btn-primary" onclick="openModal()">+ New Appointment</button>
    </div>
</div>

<!-- NEW APPOINTMENT MODAL -->
<div id="appointmentModal" class="modal-overlay">
    <div class="modal">
        <h2 style="margin-bottom: 24px; font-size: 1.5rem;">New Appointment</h2>
         
        <div class="form-group">
            <label>Patient Name</label>
            <input type="text" id="appt-name" class="form-control" placeholder="Enter full name">
        </div>

        <div class="form-group">
            <label>Phone Number</label>
            <input type="text" id="appt-phone" class="form-control" value="0912 - 345 - 6789">
        </div>

        <div class="form-row">
            <div class="form-group" style="flex:1;">
                <label>Date</label>
                <input type="date" id="appt-date" class="form-control">
            </div>
            <div class="form-group" style="flex:1;">
                <label>Time</label>
                <input type="time" id="appt-time" class="form-control">
            </div>
        </div>

        <div class="form-group">
            <label>Inquiry Source</label>
            <div class="radio-group">
                <label class="radio-option">
                    <input type="radio" name="source" value="Phone Call" checked> Phone Call
                </label>
                <label class="radio-option">
                    <input type="radio" name="source" value="Messenger"> Messenger
                </label>
                <label class="radio-option">
                    <input type="radio" name="source" value="Walk in"> Walk in
                </label>
            </div>
        </div>

        <div class="form-group">
            <label>Notes</label>
            <textarea id="appt-notes" class="form-control" rows="3" placeholder="Add notes..."></textarea>
        </div>

        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal()">Cancel</button>
            <button class="btn-primary" onclick="createAppointment()">Create Appointment</button>
        </div>
    </div>
</div>

<script>
    // --- View Switching Logic ---
    function switchView(viewName, element) {
        // Update Sidebar Active State
        document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
        if(element) element.classList.add('active');

        // Hide all views
        document.querySelectorAll('.view-section').forEach(el => el.classList.remove('active'));

        // Show selected view
        document.getElementById('view-' + viewName).classList.add('active');

        // Update Title
        const titles = {
            'dashboard': 'Inquiry & Queue Dashboard',
            'appointments': 'Appointments Management'
        };
        document.getElementById('page-title').innerText = titles[viewName] || 'Staff Dashboard';
    }

    // --- Dashboard Logic ---
    const countWaiting = document.getElementById('count-waiting');
    const countCompleted = document.getElementById('count-completed');
    const countCancelled = document.getElementById('count-cancelled');
    const liveName = document.getElementById('live-patient-name');
    const liveTime = document.getElementById('live-patient-time');
    const liveTreatment = document.getElementById('live-patient-treatment');
    const completeBtn = document.getElementById('completeTreatmentBtn');
    const upNextList = document.getElementById('up-next-list');
    const cancelledList = document.getElementById('cancelled-list');
    const reminderList = document.getElementById('reminder-list');
    const addReminderBtn = document.getElementById('addReminderBtn');

    let completedCount = 0;

    completeBtn.addEventListener('click', () => {
        completedCount++;
        countCompleted.innerText = completedCount;
        completeBtn.innerText = "✓ Treatment Completed";
        completeBtn.style.backgroundColor = "#15803d";
         
        setTimeout(() => {
            completeBtn.innerHTML = "<span>✓</span><span>Complete Treatment</span>";
            completeBtn.style.backgroundColor = "";
            const nextPatientItem = upNextList.firstElementChild;
                 
            if (nextPatientItem) {
                const name = nextPatientItem.querySelector('.patient-name').innerText;
                const time = nextPatientItem.querySelector('.patient-time').innerText;
                const treatment = nextPatientItem.querySelector('.patient-treatment').innerText;
                liveName.innerText = name;
                liveTime.innerText = time;
                liveTreatment.innerText = treatment;
                nextPatientItem.remove();
                let currentWaiting = parseInt(countWaiting.innerText);
                if(currentWaiting > 0) countWaiting.innerText = currentWaiting - 1;
            } else {
                liveName.innerText = "No Patients";
                liveTime.innerText = "--:--";
                liveTreatment.innerText = "Queue Empty";
            }
        }, 1000);
    });

    addReminderBtn.addEventListener('click', () => {
        const reminders = ["Check inventory", "Verify insurance claims", "Sterilize tools", "Update patient records", "Lunch break"];
        const randomReminder = reminders[Math.floor(Math.random() * reminders.length)];
        const li = document.createElement('li');
        li.setAttribute('onclick', 'this.classList.toggle("checked")');
        li.innerText = randomReminder;
        reminderList.appendChild(li);
    });

    window.moveToCancelled = function(btn) {
        const patientItem = btn.closest('.patient-item');
        const name = patientItem.querySelector('.patient-name').innerText;
        const treatment = patientItem.querySelector('.patient-treatment').innerText;

        const cancelledItem = document.createElement('div');
        cancelledItem.className = 'patient-item';
        cancelledItem.innerHTML = `
            <div class="patient-info">
                <div class="patient-name" style="text-decoration: line-through; color: #9ca3af;">${name}</div>
                <div class="patient-details">
                    <span class="status-badge cancelled">Cancelled</span>
                </div>
                <div class="patient-treatment">${treatment}</div>
            </div>
            <div class="patient-actions">
                <button class="action-btn icon view-btn">👁️</button>
                <button class="action-btn" style="background: #84cc16; color: white; padding: 8px 12px; border-radius: 6px; border: none; font-size: 12px; cursor: pointer;" onclick="requeueNew(this, '${name}', '${treatment}')">Re-queue</button>
                <button class="action-btn icon delete-btn">🗑️</button>
            </div>
        `;
        cancelledList.appendChild(cancelledItem);
        patientItem.remove();
        let currentCancelled = parseInt(countCancelled.innerText);
        countCancelled.innerText = currentCancelled + 1;
        let currentWaiting = parseInt(countWaiting.innerText);
        if(currentWaiting > 0) countWaiting.innerText = currentWaiting - 1;
    };

    window.requeuePatient = function(id, name, treatment, time) {
        const item = document.getElementById(id);
        if(item) {
            createWaitingItem(name, treatment, time);
            item.remove();
            let currentCancelled = parseInt(countCancelled.innerText);
            if(currentCancelled > 0) countCancelled.innerText = currentCancelled - 1;
            let currentWaiting = parseInt(countWaiting.innerText);
            countWaiting.innerText = currentWaiting + 1;
        }
    };

    window.requeueNew = function(btn, name, treatment) {
        const item = btn.closest('.patient-item');
        createWaitingItem(name, treatment, "Re-queued");
        item.remove();
        let currentCancelled = parseInt(countCancelled.innerText);
        if(currentCancelled > 0) countCancelled.innerText = currentCancelled - 1;
        let currentWaiting = parseInt(countWaiting.innerText);
        countWaiting.innerText = currentWaiting + 1;
    };

    function createWaitingItem(name, treatment, time) {
        const newItem = document.createElement('div');
        newItem.className = 'patient-item';
        newItem.innerHTML = `
            <div class="patient-info">
                <div class="patient-name">${name}</div>
                <div class="patient-details">
                    <span class="status-badge waiting">Waiting</span>
                    <span class="patient-time">${time}</span>
                </div>
                <div class="patient-treatment">${treatment}</div>
            </div>
            <div class="patient-actions">
                <button class="action-btn icon view-btn">👁️</button>
                <button class="action-btn icon delete-btn" onclick="this.innerHTML = 'Calling...'; setTimeout(() => this.innerHTML = '📞', 2000)">📞</button>
                <button class="action-btn icon delete-btn" onclick="moveToCancelled(this)">⚠️</button>
            </div>
        `;
        upNextList.appendChild(newItem);
    }

    // --- Appointments Logic ---
    function openModal() {
        document.getElementById('appointmentModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('appointmentModal').style.display = 'none';
    }

    // Close modal on outside click
    window.onclick = function(event) {
        const modal = document.getElementById('appointmentModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    function createAppointment() {
        // Get values
        const name = document.getElementById('appt-name').value;
        const phone = document.getElementById('appt-phone').value;
        const date = document.getElementById('appt-date').value;
        const time = document.getElementById('appt-time').value;
        const source = document.querySelector('input[name="source"]:checked').value;
        const notes = document.getElementById('appt-notes').value;

        if(!name || !date || !time) {
            alert("Please fill in Name, Date, and Time.");
            return;
        }

        // Hide Empty State
        document.getElementById('appt-empty-state').style.display = 'none';

        // Create Item
        const container = document.querySelector('.search-filters');
         
        const newAppt = document.createElement('div');
        newAppt.style.cssText = "background: white; padding: 16px; border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; animation: fadeIn 0.3s ease;";
        newAppt.innerHTML = `
            <div>
                <div style="font-weight: 700; font-size: 1rem;">${name}</div>
                <div style="font-size: 0.9rem; color: #64748b; margin-top: 4px;">${date} at ${time} • ${source}</div>
                <div style="font-size: 0.85rem; color: #64748b; margin-top: 2px;">${notes}</div>
            </div>
            <div>
                <button class="btn-cancel" style="padding: 6px 12px; font-size: 0.85rem;">Edit</button>
            </div>
        `;

        // Insert after the filter bar
        const filterBar = document.querySelector('.search-filters');
        filterBar.parentNode.insertBefore(newAppt, filterBar.nextSibling);

        // Update Stats (Simulation)
        let total = parseInt(document.getElementById('appt-total').innerText);
        document.getElementById('appt-total').innerText = total + 1;
        document.getElementById('appt-upcoming').innerText = parseInt(document.getElementById('appt-upcoming').innerText) + 1;

        // Reset Form & Close
        document.getElementById('appt-name').value = "";
        document.getElementById('appt-date').value = "";
        document.getElementById('appt-time').value = "";
        document.getElementById('appt-notes').value = "";
        closeModal();
    }
</script>

<?php require_once 'includes/staff_layout_end.php'; ?>