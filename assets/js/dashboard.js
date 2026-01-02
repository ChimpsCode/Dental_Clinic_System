document.addEventListener('DOMContentLoaded', function() {
    // Toggle reminder checkboxes
    const reminderCheckboxes = document.querySelectorAll('.reminder-checkbox');
    reminderCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('click', function() {
            this.classList.toggle('checked');
            const reminderItem = this.closest('.reminder-item');
            reminderItem.classList.toggle('checked');
            
            if (this.classList.contains('checked')) {
                this.innerHTML = '✓';
            } else {
                this.innerHTML = '';
            }
        });
    });

    // Complete Treatment Button
    const completeBtn = document.getElementById('completeTreatmentBtn');
    if (completeBtn) {
        completeBtn.addEventListener('click', function() {
            if (confirm('Mark this treatment as completed?')) {
                // Here you would typically make an AJAX call to update the database
                alert('Treatment marked as completed!');
                location.reload();
            }
        });
    }

    // Call Patient Buttons
    const callPatientBtns = document.querySelectorAll('.call-patient-btn');
    callPatientBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const patientName = this.closest('.patient-item').querySelector('.patient-name').textContent;
            alert(`Calling ${patientName}...`);
            // Here you would typically make an AJAX call or trigger a notification
        });
    });

    // Not Present Buttons
    const notPresentBtns = document.querySelectorAll('.not-present-btn');
    notPresentBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const patientName = this.closest('.patient-item').querySelector('.patient-name').textContent;
            if (confirm(`Mark ${patientName} as not present?`)) {
                // Here you would typically make an AJAX call to update the status
                alert('Patient marked as not present');
                location.reload();
            }
        });
    });

    // Re-queue Buttons
    const requeueBtns = document.querySelectorAll('.requeue-btn');
    requeueBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const patientName = this.closest('.patient-item').querySelector('.patient-name').textContent;
            if (confirm(`Re-queue ${patientName}?`)) {
                // Here you would typically make an AJAX call to re-queue the patient
                alert('Patient re-queued successfully');
                location.reload();
            }
        });
    });

    // Delete/Cancel Buttons
    const deleteBtns = document.querySelectorAll('.delete-btn');
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const patientName = this.closest('.patient-item').querySelector('.patient-name').textContent;
            if (confirm(`Are you sure you want to delete this appointment for ${patientName}?`)) {
                // Here you would typically make an AJAX call to delete
                alert('Appointment deleted');
                location.reload();
            }
        });
    });

    // View Patient Buttons
    const viewBtns = document.querySelectorAll('.view-btn');
    viewBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const patientName = this.closest('.patient-item').querySelector('.patient-name').textContent;
            // Here you would typically navigate to patient details page
            alert(`Viewing details for ${patientName}`);
        });
    });

    // Add New Reminder
    const addReminderBtn = document.getElementById('addReminderBtn');
    if (addReminderBtn) {
        addReminderBtn.addEventListener('click', function() {
            const reminderText = prompt('Enter new reminder:');
            if (reminderText && reminderText.trim()) {
                // Here you would typically make an AJAX call to add the reminder
                const reminderList = document.querySelector('.reminder-list');
                const newReminder = document.createElement('div');
                newReminder.className = 'reminder-item';
                newReminder.innerHTML = `
                    <div class="reminder-checkbox"></div>
                    <span class="reminder-text">${reminderText}</span>
                `;
                reminderList.appendChild(newReminder);
                
                // Add event listener to new checkbox
                newReminder.querySelector('.reminder-checkbox').addEventListener('click', function() {
                    this.classList.toggle('checked');
                    const reminderItem = this.closest('.reminder-item');
                    reminderItem.classList.toggle('checked');
                    if (this.classList.contains('checked')) {
                        this.innerHTML = '✓';
                    } else {
                        this.innerHTML = '';
                    }
                });
            }
        });
    }

    // Navigation active state
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            if (this.getAttribute('href') !== '#') {
                // Remove active class from all items
                navItems.forEach(nav => nav.classList.remove('active'));
                // Add active class to clicked item
                this.classList.add('active');
            }
        });
    });
});

