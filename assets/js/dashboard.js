// Mobile Sidebar Toggle - Attach immediately
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.querySelector('.sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

function toggleSidebar(e) {
    if (e) e.preventDefault();
    if (e) e.stopPropagation();
    
    sidebar.classList.toggle('open');
    sidebarOverlay.classList.toggle('active');
    document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
}

if (menuToggle) {
    menuToggle.addEventListener('click', toggleSidebar);
}

if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', toggleSidebar);
}

// Handle window resize
window.addEventListener('resize', function() {
    if (window.innerWidth > 1024) {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }
});

// Rest of the code wrapped in DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    // Close sidebar when clicking on nav items (mobile)
    const navItemsSidebar = document.querySelectorAll('.nav-item');
    navItemsSidebar.forEach(item => {
        item.addEventListener('click', function() {
            if (window.innerWidth <= 1024 && sidebar.classList.contains('open')) {
                toggleSidebar();
            }
        });
    });

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
        });
    });

    // Not Present Buttons
    const notPresentBtns = document.querySelectorAll('.not-present-btn');
    notPresentBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const patientName = this.closest('.patient-item').querySelector('.patient-name').textContent;
            if (confirm(`Mark ${patientName} as not present?`)) {
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
            alert(`Viewing details for ${patientName}`);
        });
    });

    // Add New Reminder
    const addReminderBtn = document.getElementById('addReminderBtn');
    if (addReminderBtn) {
        addReminderBtn.addEventListener('click', function() {
            const reminderText = prompt('Enter new reminder:');
            if (reminderText && reminderText.trim()) {
                const reminderList = document.querySelector('.reminder-list');
                const newReminder = document.createElement('div');
                newReminder.className = 'reminder-item';
                newReminder.innerHTML = `
                    <div class="reminder-checkbox"></div>
                    <span class="reminder-text">${reminderText}</span>
                `;
                reminderList.appendChild(newReminder);
                
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
                navItems.forEach(nav => nav.classList.remove('active'));
                this.classList.add('active');
            }
        });
    });

    // User Profile Dropdown
    const userProfile = document.getElementById('userProfileDropdown');
    if (userProfile) {
        // Toggle dropdown on click
        userProfile.addEventListener('click', function(e) {
            e.stopPropagation();
            userProfile.classList.toggle('active');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userProfile.contains(e.target)) {
                userProfile.classList.remove('active');
            }
        });
        
        // Close dropdown on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && userProfile.classList.contains('active')) {
                userProfile.classList.remove('active');
            }
        });
    }
});
