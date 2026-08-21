(function () {
    window.showAppointmentUpdateSuccess = function (changes) {
        var modal = document.getElementById('appointmentUpdateSuccessModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'appointmentUpdateSuccessModal';
            modal.className = 'modal-overlay';
            modal.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100000;align-items:center;justify-content:center;';
            modal.innerHTML = '<div style="background:#fff;border-radius:12px;padding:24px;width:90%;max-width:520px;box-shadow:0 20px 50px rgba(0,0,0,.2);">' +
                '<h2 style="margin:0 0 8px;color:#166534;font-size:1.25rem;">Appointment Successfully Updated</h2>' +
                '<p style="margin:0 0 16px;color:#374151;">The appointment information was changed successfully.</p>' +
                '<div id="appointmentUpdateSuccessBody"></div>' +
                '<div style="display:flex;justify-content:flex-end;margin-top:20px;"><button type="button" id="appointmentUpdateSuccessClose" style="padding:10px 20px;background:#16a34a;color:#fff;border:0;border-radius:8px;cursor:pointer;font-weight:600;">Close</button></div></div>';
            document.body.appendChild(modal);
            modal.querySelector('#appointmentUpdateSuccessClose').addEventListener('click', function () { modal.style.display = 'none'; });
        }

        var body = modal.querySelector('#appointmentUpdateSuccessBody');
        if (!changes.length) {
            body.innerHTML = '<div style="padding:12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;color:#166534;">The appointment was saved successfully.</div>';
        } else {
            body.innerHTML = changes.map(function (change) {
                return '<div style="display:grid;grid-template-columns:110px 1fr;gap:8px;padding:10px 0;border-bottom:1px solid #f3f4f6;">' +
                    '<strong style="color:#374151;">' + escapeAppointmentText(change.label) + '</strong>' +
                    '<span style="color:#374151;">' + escapeAppointmentText(change.from) + ' &rarr; ' + escapeAppointmentText(change.to) + '</span></div>';
            }).join('');
        }
        modal.style.display = 'flex';
    };

    function escapeAppointmentText(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character];
        });
    }

    window.openAppointmentCallModal = function (name, phone) {
        var modal = document.getElementById('appointmentCallModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'appointmentCallModal';
            modal.className = 'modal-overlay';
            modal.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;';
            modal.innerHTML = '<div style="background:white;border-radius:12px;padding:24px;width:90%;max-width:360px;position:relative;z-index:100000;">' +
                '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">' +
                '<h2 style="font-size:1.1rem;font-weight:600;margin:0;">Patient Contact</h2>' +
                '<button type="button" aria-label="Close" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#6b7280;">&times;</button></div>' +
                '<div id="appointmentCallModalBody" style="color:#374151;text-align:center;padding:8px 0 4px;"></div></div>';
            document.body.appendChild(modal);
            modal.querySelector('button').addEventListener('click', closeAppointmentCallModal);
            modal.addEventListener('click', function (event) {
                if (event.target === modal) closeAppointmentCallModal();
            });
        }

        var safePhone = phone || 'No phone number on file';
        var body = document.getElementById('appointmentCallModalBody');
        body.innerHTML = '';
        var nameEl = document.createElement('div');
        nameEl.style.cssText = 'font-size:0.95rem;color:#6b7280;margin-bottom:8px;';
        nameEl.textContent = name || 'Unknown patient';
        var phoneEl = document.createElement('a');
        phoneEl.href = 'tel:' + encodeURIComponent(safePhone);
        phoneEl.style.cssText = 'font-size:1.35rem;font-weight:700;color:#2563eb;text-decoration:none;word-break:break-word;';
        phoneEl.textContent = safePhone;
        body.appendChild(nameEl);
        body.appendChild(phoneEl);
        modal.style.display = 'flex';
    };

    window.openAppointmentCallModalFromButton = function (button) {
        openAppointmentCallModal(button.dataset.patientName, button.dataset.phone);
    };

    function closeAppointmentCallModal() {
        var modal = document.getElementById('appointmentCallModal');
        if (modal) modal.style.display = 'none';
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeAppointmentCallModal();
    });

    window.openAppointmentDecisionModal = function (action, onConfirm) {
        var isDecline = action === 'decline';
        var modal = document.getElementById('appointmentDecisionModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'appointmentDecisionModal';
            modal.className = 'modal-overlay';
            modal.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;';
            modal.innerHTML = '<div style="background:white;border-radius:12px;padding:24px;width:90%;max-width:460px;position:relative;z-index:100000;">' +
                '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">' +
                '<h2 id="appointmentDecisionTitle" style="font-size:1.25rem;font-weight:600;margin:0;"></h2>' +
                '<button type="button" aria-label="Close" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#6b7280;">&times;</button></div>' +
                '<p id="appointmentDecisionMessage" style="color:#374151;margin:0 0 16px;white-space:pre-line;"></p>' +
                '<label id="appointmentDecisionReasonLabel" for="appointmentDecisionReason" style="display:none;font-size:0.85rem;font-weight:600;color:#374151;margin-bottom:6px;">Reason (optional)</label>' +
                '<textarea id="appointmentDecisionReason" rows="3" placeholder="Enter a reason for declining this request..." style="display:none;width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:0.9rem;resize:vertical;box-sizing:border-box;margin-bottom:20px;"></textarea>' +
                '<div style="display:flex;justify-content:flex-end;gap:12px;">' +
                '<button type="button" id="appointmentDecisionCancel" style="padding:10px 20px;background:#f3f4f6;color:#374151;border:none;border-radius:8px;cursor:pointer;font-weight:500;">Cancel</button>' +
                '<button type="button" id="appointmentDecisionConfirm" style="padding:10px 20px;color:white;border:none;border-radius:8px;cursor:pointer;font-weight:600;"></button></div></div>';
            document.body.appendChild(modal);
            modal.querySelector('button[aria-label="Close"]').addEventListener('click', closeAppointmentDecisionModal);
            modal.querySelector('#appointmentDecisionCancel').addEventListener('click', closeAppointmentDecisionModal);
            modal.addEventListener('click', function (event) {
                if (event.target === modal) closeAppointmentDecisionModal();
            });
        }

        document.getElementById('appointmentDecisionTitle').textContent = isDecline ? 'Decline Appointment' : 'Approve Appointment';
        document.getElementById('appointmentDecisionMessage').textContent = isDecline
            ? 'Decline this appointment request?\n\nThe patient will be notified.'
            : 'Approve this appointment request?\n\nThe patient will be notified and the slot will be added to the calendar.';
        var reasonLabel = document.getElementById('appointmentDecisionReasonLabel');
        var reasonInput = document.getElementById('appointmentDecisionReason');
        reasonLabel.style.display = isDecline ? 'block' : 'none';
        reasonInput.style.display = isDecline ? 'block' : 'none';
        reasonInput.value = '';
        var confirmButton = document.getElementById('appointmentDecisionConfirm');
        confirmButton.textContent = isDecline ? 'Decline' : 'Approve';
        confirmButton.style.background = isDecline ? '#dc2626' : '#16a34a';
        confirmButton.onclick = function () {
            var reason = isDecline ? reasonInput.value.trim() : '';
            closeAppointmentDecisionModal();
            onConfirm(reason);
        };
        modal.style.display = 'flex';
    };

    function closeAppointmentDecisionModal() {
        var modal = document.getElementById('appointmentDecisionModal');
        if (modal) modal.style.display = 'none';
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeAppointmentDecisionModal();
    });

    window.refreshAppointmentsAjax = function () {
        var content = document.querySelector('.content-main');
        if (!content) return Promise.resolve(false);

        var activeFilter = document.querySelector('.filter-tabs span.active');
        var searchInput = document.getElementById('appointmentSearch');
        var activeView = document.querySelector('.view-toggle-btn.active');
        var state = {
            filter: activeFilter ? activeFilter.dataset.filter : 'all',
            search: searchInput ? searchInput.value : '',
            view: activeView ? activeView.dataset.view : 'list'
        };

        content.setAttribute('aria-busy', 'true');
        return fetch(window.location.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) throw new Error('Appointment refresh failed');
                return response.text();
            })
            .then(function (html) {
                var parsed = new DOMParser().parseFromString(html, 'text/html');
                var nextContent = parsed.querySelector('.content-main');
                if (!nextContent) throw new Error('Appointment content was not found');

                var appointmentDataScript = Array.prototype.find.call(parsed.scripts, function (script) {
                    return /(?:const|var)\s+appointments\s*=/.test(script.textContent);
                });
                if (appointmentDataScript) {
                    var dataMatch = appointmentDataScript.textContent.match(/(?:const|var)\s+appointments\s*=\s*(\[[\s\S]*?\]);/);
                    if (dataMatch) {
                        try {
                            window.appointments = JSON.parse(dataMatch[1]);
                        } catch (error) {
                            console.warn('Unable to update appointment data after refresh:', error);
                        }
                    }
                }

                content.replaceWith(nextContent);
                restoreAppointmentView(state);
                document.querySelectorAll('.modal-overlay').forEach(function (modal) {
                    // Never force-close modals the user is actively using
                    // (e.g. the Reschedule Appointment form); polling-triggered
                    // refreshes would otherwise dismiss them mid-edit.
                    if (modal.dataset.preserveOpen === '1') return;
                    modal.style.display = 'none';
                });
                return true;
            })
            .catch(function (error) {
                console.error('Appointment AJAX refresh error:', error);
                return false;
            });
    };

    function restoreAppointmentView(state) {
        var filterTabs = document.querySelectorAll('.filter-tabs span');
        filterTabs.forEach(function (tab) {
            tab.classList.toggle('active', tab.dataset.filter === state.filter);
            if (!tab.dataset.ajaxBound) {
                tab.dataset.ajaxBound = '1';
                tab.addEventListener('click', function () {
                    filterTabs.forEach(function (item) { item.classList.remove('active'); });
                    tab.classList.add('active');
                    if (typeof window.filterAppointments === 'function') window.filterAppointments();
                });
            }
        });

        var searchInput = document.getElementById('appointmentSearch');
        if (searchInput) {
            searchInput.value = state.search;
            if (!searchInput.dataset.ajaxBound) {
                searchInput.dataset.ajaxBound = '1';
                searchInput.addEventListener('input', function () {
                    if (typeof window.filterAppointments === 'function') window.filterAppointments();
                });
            }
        }

        var viewButtons = document.querySelectorAll('.view-toggle-btn');
        viewButtons.forEach(function (button) {
            button.classList.toggle('active', button.dataset.view === state.view);
            if (!button.dataset.ajaxBound) {
                button.dataset.ajaxBound = '1';
                button.addEventListener('click', function () {
                    viewButtons.forEach(function (item) { item.classList.remove('active'); });
                    button.classList.add('active');
                    var listView = document.getElementById('listView');
                    var calendarView = document.getElementById('calendarView');
                    if (button.dataset.view === 'list') {
                        if (listView) listView.classList.remove('hidden');
                        if (calendarView) calendarView.classList.remove('active');
                    } else {
                        if (listView) listView.classList.add('hidden');
                        if (calendarView) calendarView.classList.add('active');
                        if (typeof window.initAdminCalendar === 'function') window.initAdminCalendar();
                        if (typeof window.initStaffCalendar === 'function') window.initStaffCalendar();
                        if (typeof window.initDentistCalendar === 'function') window.initDentistCalendar();
                    }
                });
            }
        });

        if (typeof window.filterAppointments === 'function') window.filterAppointments();
        if (state.view === 'calendar') {
            var listView = document.getElementById('listView');
            var calendarView = document.getElementById('calendarView');
            if (listView) listView.classList.add('hidden');
            if (calendarView) calendarView.classList.add('active');
            if (typeof window.initAdminCalendar === 'function') window.initAdminCalendar();
            if (typeof window.initStaffCalendar === 'function') window.initStaffCalendar();
            if (typeof window.initDentistCalendar === 'function') window.initDentistCalendar();
        }
    }
})();
