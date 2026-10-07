(function () {
    'use strict';
    const config = window.PATIENT_APPOINTMENT_MANAGEMENT;
    const editModal = document.getElementById('patientAppointmentEditModal');
    const confirmModal = document.getElementById('patientAppointmentConfirmModal');
    if (!config || !editModal || !confirmModal) return;
    const get = id => document.getElementById(id);
    let appointment = null, pending = null, busy = false, slotsVersion = 0, returnFocus = null, done = false;

    function show(modal) { modal.classList.add('active'); modal.querySelector('button,select,input').focus(); }
    function hide(modal) { modal.classList.remove('active'); }
    function close() {
        if (busy) return;
        if (done) { window.location.href = 'appointments.php?tab=upcoming'; return; }
        hide(editModal); hide(confirmModal);
        if (returnFocus) returnFocus.focus();
    }
    function summary(values) {
        const box = get('patientAppointmentConfirmDetails');
        box.replaceChildren();
        Object.entries(values).forEach(([label, value]) => {
            const row = document.createElement('p'), heading = document.createElement('strong');
            heading.textContent = label + ': ';
            row.append(heading, document.createTextNode(value || 'None'));
            box.appendChild(row);
        });
    }
    function displayTime(time) {
        const [h, m] = String(time).split(':');
        return (Number(h) % 12 || 12) + ':' + m + (Number(h) >= 12 ? ' PM' : ' AM');
    }
    async function loadSlots(preferred) {
        const version = ++slotsVersion, select = get('patientEditTime');
        select.replaceChildren(new Option('Loading available times...', ''));
        select.disabled = true;
        get('patientEditReview').disabled = true;
        get('patientEditError').textContent = '';
        const params = new URLSearchParams({ date:get('patientEditDate').value, service_id:get('patientEditService').value,
            exclude_appointment_id:appointment.id, dentist_id:appointment.dentist_id || 0 });
        try {
            const response = await fetch('get_slots.php?' + params, { credentials:'same-origin' });
            const data = await response.json();
            if (version !== slotsVersion) return;
            select.replaceChildren(new Option('Select an available time', ''));
            if (!data.success || !data.is_open || !data.available_slots?.length) {
                throw new Error(data.message || 'No available times on this date. Please choose another date.');
            }
            data.available_slots.forEach(time => select.add(new Option(displayTime(time), time)));
            if (preferred && data.available_slots.includes(preferred)) select.value = preferred;
            select.disabled = false;
            get('patientEditReview').disabled = false;
        } catch (error) {
            if (version !== slotsVersion) return;
            select.replaceChildren(new Option('No available times', ''));
            get('patientEditError').textContent = error.message || 'Unable to load available times.';
        }
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-appt-management]');
        if (!button || busy) return;
        appointment = config.appointments.find(item => Number(item.id) === Number(button.dataset.appointmentId));
        if (!appointment) return;
        returnFocus = button; done = false; pending = null;
        get('patientAppointmentConfirmError').textContent = '';
        get('patientAppointmentConfirmBack').hidden = false;
        if (button.dataset.apptManagement === 'edit') {
            get('patientEditDate').value = appointment.appointment_date;
            get('patientEditService').value = appointment.service_id || '';
            get('patientEditNotes').value = appointment.notes || '';
            show(editModal);
            loadSlots(String(appointment.appointment_time).slice(0, 5));
        } else {
            const request = button.dataset.apptManagement === 'request_cancel';
            pending = { action:request ? 'request_cancel' : 'cancel', appointment_id:Number(appointment.id) };
            get('patientAppointmentConfirmTitle').textContent = request ? 'Request Appointment Cancellation' : 'Cancel Appointment';
            get('patientAppointmentConfirmMessage').textContent = request
                ? 'This appointment has already been approved. Please provide a reason for cancellation. The clinic will review your request and may contact you to verify it. Your appointment remains scheduled until cancellation is approved. You may also call the clinic directly.'
                : 'Are you sure you wish to cancel this appointment? This action cannot be undone. You may submit a new booking request if needed.';
            get('patientAppointmentConfirmSave').textContent = request ? 'Submit Cancellation Request' : 'Confirm Cancellation';
            get('patientAppointmentCancelReasonLabel').textContent = request ? 'Reason for cancellation (required)' : 'Reason for cancellation (optional)';
            const contact = get('patientAppointmentClinicContact');
            contact.replaceChildren(); contact.hidden = !request || !config.clinicPhone;
            if (request && config.clinicPhone) {
                const link = document.createElement('a'); link.href = 'tel:' + config.clinicPhone.replace(/[^+\d]/g, '');
                link.textContent = 'Call the clinic: ' + config.clinicPhone; contact.appendChild(link);
            }
            get('patientAppointmentCancelReasonWrap').hidden = false;
            get('patientAppointmentCancelReason').value = '';
            summary({ Service:appointment.service_name || 'Dental appointment', Date:appointment.appointment_date, Time:displayTime(appointment.appointment_time) });
            show(confirmModal);
        }
    });
    get('patientEditDate').addEventListener('change', () => loadSlots());
    get('patientEditService').addEventListener('change', () => loadSlots());
    get('patientAppointmentEditForm').addEventListener('submit', function (event) {
        event.preventDefault();
        if (busy || !this.reportValidity() || get('patientEditTime').disabled) return;
        pending = { action:'edit', appointment_id:Number(appointment.id), appointment_date:get('patientEditDate').value,
            appointment_time:get('patientEditTime').value, service_id:Number(get('patientEditService').value), notes:get('patientEditNotes').value.trim() };
        if (pending.appointment_date === appointment.appointment_date && pending.appointment_time === String(appointment.appointment_time).slice(0,5)
            && pending.service_id === Number(appointment.service_id) && pending.notes === String(appointment.notes || '').trim()) {
            get('patientEditError').textContent = 'No changes were made. Your one-time edit has not been used.';
            return;
        }
        get('patientAppointmentConfirmTitle').textContent = 'Confirm Appointment Changes';
        get('patientAppointmentConfirmMessage').textContent = 'This appointment request may be edited only once. Please carefully review the details below. Once confirmed, no further online edits will be permitted. The request will remain subject to staff approval.';
        get('patientAppointmentConfirmSave').textContent = 'Confirm Changes';
        get('patientAppointmentConfirmError').textContent = '';
        get('patientAppointmentCancelReasonWrap').hidden = true;
        summary({ Service:get('patientEditService').selectedOptions[0].textContent, Date:pending.appointment_date, Time:displayTime(pending.appointment_time), Notes:pending.notes });
        hide(editModal); show(confirmModal);
    });
    get('patientAppointmentConfirmBack').addEventListener('click', function () {
        if (busy) return;
        if (pending?.action === 'edit') { hide(confirmModal); show(editModal); }
        else close();
    });
    get('patientAppointmentConfirmSave').addEventListener('click', async function () {
        if (done) { close(); return; }
        if (!pending || busy) return;
        busy = true;
        confirmModal.querySelectorAll('button').forEach(button => { button.disabled = true; });
        get('patientAppointmentConfirmError').textContent = '';
        try {
            const input = { ...pending, csrf:config.csrf };
            if (['cancel','request_cancel'].includes(pending.action)) input.cancel_reason = get('patientAppointmentCancelReason').value.trim();
            if (pending.action === 'request_cancel' && !input.cancel_reason) throw new Error('Please enter your reason for requesting cancellation.');
            const response = await fetch('manage_appointment.php', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'}, body:JSON.stringify(input) });
            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Unable to update this appointment.');
            done = true;
            get('patientAppointmentConfirmTitle').textContent = pending.action === 'edit' ? 'Appointment Updated' : (pending.action === 'request_cancel' ? 'Cancellation Requested' : 'Appointment Cancelled');
            get('patientAppointmentConfirmMessage').textContent = data.message;
            get('patientAppointmentConfirmDetails').replaceChildren();
            get('patientAppointmentCancelReasonWrap').hidden = true;
            get('patientAppointmentConfirmBack').hidden = true;
            this.textContent = 'Done';
        } catch (error) {
            get('patientAppointmentConfirmError').textContent = error.message || 'Unable to save. Please try again.';
        } finally {
            busy = false;
            confirmModal.querySelectorAll('button').forEach(button => { button.disabled = false; });
        }
    });
    editModal.querySelector('[data-close-patient-edit]').addEventListener('click', close);
    [editModal, confirmModal].forEach(modal => {
        modal.addEventListener('click', event => { if (event.target === modal) close(); });
        modal.addEventListener('keydown', event => {
            if (event.key === 'Escape') { event.preventDefault(); close(); }
            if (event.key === 'Tab') {
                const items = Array.from(modal.querySelectorAll('button,input,select,textarea')).filter(item => !item.disabled && !item.hidden && item.getClientRects().length);
                const first = items[0], last = items[items.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        });
    });
    window.addPatientAppointmentManagementCard = function (card, item) {
        if (!Number(item.id)) return;
        config.appointments.push(item);
        const controls = document.createElement('div');
        controls.className = 'patient-apt-controls';
        controls.appendChild(card.querySelector('.apt-status'));
        const actions = document.createElement('div');
        actions.className = 'patient-apt-actions';
        for (const [action, label] of [['edit','Edit Request'], ['cancel','Cancel Appointment']]) {
            const button = document.createElement('button');
            button.type = 'button'; button.textContent = label;
            button.dataset.apptManagement = action; button.dataset.appointmentId = item.id;
            if (action === 'cancel') button.className = 'patient-apt-cancel';
            actions.appendChild(button);
        }
        controls.appendChild(actions); card.appendChild(controls);
    };
    if (new URLSearchParams(window.location.search).get('tab') === 'upcoming') {
        document.querySelectorAll('.tab-btn').forEach(button => button.classList.toggle('active', button.dataset.tab === 'upcoming'));
        document.querySelectorAll('.tab-content').forEach(panel => panel.classList.toggle('active', panel.id === 'upcoming'));
    }
}());
