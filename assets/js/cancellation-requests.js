(function () {
    'use strict';
    if (window.cancellationReviewLoaded) return;
    window.cancellationReviewLoaded = true;
    const endpoint = new URL('../shared/cancellation_requests.php', window.location.href).href;
    let requests = [], loading = false, busy = false, active = null, focusBefore = null, openVersion = 0;
    const overlay = document.createElement('div');
    overlay.className = 'cancellation-review-overlay';
    overlay.setAttribute('data-persist-layout', '1');
    overlay.hidden = true;
    overlay.innerHTML = '<div class="cancellation-review-modal" role="dialog" aria-modal="true" aria-labelledby="cancellationReviewTitle" tabindex="-1">' +
        '<h2 id="cancellationReviewTitle">Review Cancellation Request</h2><div data-review-details></div>' +
        '<label>Clinic note (optional)<textarea data-review-note maxlength="1000" rows="3"></textarea></label>' +
        '<p data-review-error class="cancellation-review-error" role="status"></p><div class="cancellation-review-actions">' +
        '<button type="button" data-review-close>Close</button><button type="button" data-decision="decline">Decline Request</button>' +
        '<button type="button" data-decision="approve">Approve Cancellation</button></div></div>';
    document.body.appendChild(overlay);
    const get = selector => overlay.querySelector(selector);
    function close() { if (busy) return; ++openVersion; overlay.hidden = true; if (focusBefore?.isConnected) focusBefore.focus(); }
    get('[data-review-close]').addEventListener('click', close);
    overlay.addEventListener('click', event => { if (event.target === overlay) close(); });
    overlay.addEventListener('keydown', event => {
        if (event.key === 'Escape') { event.preventDefault(); close(); }
        if (event.key === 'Tab') {
            const items = [...overlay.querySelectorAll('button,textarea,a')].filter(item => !item.disabled && !item.hidden && item.getClientRects().length);
            if (!items.length) { event.preventDefault(); return; }
            if (event.shiftKey && document.activeElement === items[0]) { event.preventDefault(); items.at(-1).focus(); }
            else if (!event.shiftKey && document.activeElement === items.at(-1)) { event.preventDefault(); items[0].focus(); }
        }
    });
    async function api(url, options) {
        const response = await fetch(url, { credentials:'same-origin', cache:'no-store', ...options });
        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'Unable to load cancellation requests.');
        return data;
    }
    function detail(label, value) {
        const row = document.createElement('p'), title = document.createElement('strong');
        title.textContent = label + ': '; row.append(title, document.createTextNode(value || 'Not provided')); return row;
    }
    async function open(id) {
        if (busy) return;
        const version = ++openVersion;
        active = null; focusBefore = document.activeElement;
        overlay.hidden = false; get('.cancellation-review-modal').focus();
        get('[data-review-error]').textContent = '';
        get('[data-review-note]').value = '';
        get('[data-review-details]').textContent = 'Loading request...';
        overlay.querySelectorAll('[data-decision]').forEach(button => { button.hidden = true; });
        try {
            const data = await api(endpoint + '?id=' + encodeURIComponent(id));
            if (version !== openVersion) return;
            active = data.requests[0];
            if (!active) throw new Error('Cancellation request not found.');
            const box = get('[data-review-details]'); box.replaceChildren();
            box.append(detail('Patient', [active.first_name, active.last_name].filter(Boolean).join(' ')),
                detail('Appointment', '#' + active.appointment_id + ' · ' + active.appointment_date + ' ' + active.appointment_time),
                detail('Service', active.service_name), detail('Reason', active.reason), detail('Requested', active.requested_at),
                detail('Request status', active.status), detail('Appointment status', active.appointment_status));
            if (active.phone) {
                const phone = document.createElement('a'); phone.href = 'tel:' + active.phone.replace(/[^+\d]/g, '');
                phone.textContent = 'Call patient: ' + active.phone; box.appendChild(phone);
            }
            if (active.review_note) box.append(detail('Clinic note', active.review_note));
            box.append(detail('Review', 'Contact the patient if verification is needed. Approving cancels the appointment; declining leaves it unchanged.'));
            overlay.querySelectorAll('[data-decision]').forEach(button => { button.hidden = active.status !== 'pending'; });
            get('[data-review-note]').disabled = active.status !== 'pending';
        } catch (error) { if (version === openVersion) get('[data-review-error]').textContent = error.message; }
    }
    overlay.querySelectorAll('[data-decision]').forEach(button => button.addEventListener('click', async () => {
        if (busy || !active || active.status !== 'pending') return;
        busy = true;
        overlay.querySelectorAll('button').forEach(item => { item.disabled = true; });
        get('[data-review-error]').textContent = '';
        try {
            const result = await api(endpoint, { method:'POST', headers:{'Content-Type':'application/json'},
                body:JSON.stringify({ request_id:active.id, decision:button.dataset.decision, note:get('[data-review-note]').value.trim(), csrf:window.CANCELLATION_REVIEW_CSRF }) });
            active.status = button.dataset.decision === 'approve' ? 'approved' : 'declined';
            get('[data-review-details]').replaceChildren(detail('Result', result.message));
            overlay.querySelectorAll('[data-decision]').forEach(item => { item.hidden = true; });
            get('[data-review-note]').disabled = true;
            if (window.appCache) {
                const role = window.location.pathname.split('/').at(-2);
                window.appCache.invalidate([role + '_appointments.php', role + '-dashboard.php']);
            }
            if (/_(?:appointments)\.php$/.test(window.location.pathname) && window.refreshAppointmentsAjax) await window.refreshAppointmentsAjax({ afterCurrent:true });
            if (window.loadDashboardData) window.loadDashboardData();
            await load();
        } catch (error) { get('[data-review-error]').textContent = error.message; }
        finally { busy = false; overlay.querySelectorAll('button').forEach(item => { item.disabled = false; }); }
    }));
    function render() {
        document.querySelectorAll('[data-cancellation-list]').forEach(box => {
            box.replaceChildren();
            if (!requests.length) { box.textContent = 'No pending cancellation requests.'; return; }
            requests.forEach(item => {
                const row = document.createElement('div'); row.className = 'cancellation-request-row';
                const info = document.createElement('div'), name = document.createElement('strong');
                name.textContent = [item.first_name, item.last_name].filter(Boolean).join(' ') || 'Patient';
                info.append(name, detail('Appointment', item.appointment_date + ' ' + item.appointment_time), detail('Reason', item.reason));
                const button = document.createElement('button'); button.type = 'button'; button.textContent = 'Review';
                button.addEventListener('click', () => open(item.id)); row.append(info, button); box.appendChild(row);
            });
        });
        document.querySelectorAll('[data-cancellation-count]').forEach(box => { box.textContent = requests.length; });
    }
    function mount() {
        const calendar = document.querySelector('.view-toggle-btn[data-view="calendar"]');
        if (calendar && !document.querySelector('.cancellation-tab')) {
            const button = document.createElement('button'); button.type = 'button'; button.className = 'cancellation-tab';
            button.innerHTML = 'Cancellation Requests (<span data-cancellation-count>0</span>)';
            calendar.after(button);
        }
        if (calendar) calendar.parentElement.style.flexWrap = 'wrap';
        if (calendar && !document.getElementById('cancellationRequestsPanel')) {
            const panel = document.createElement('section'); panel.className = 'cancellation-panel'; panel.id = 'cancellationRequestsPanel'; panel.hidden = true;
            panel.innerHTML = '<h2>Cancellation Requests</h2><div data-cancellation-list></div>';
            calendar.parentElement.after(panel);
        }
        const pending = document.getElementById('pending-approval-list');
        if (pending && !document.getElementById('dashboardCancellationRequests')) {
            const panel = document.createElement('section'); panel.className = 'section-card cancellation-panel'; panel.id = 'dashboardCancellationRequests';
            panel.innerHTML = '<h2>Cancellation Requests (<span data-cancellation-count>0</span>)</h2><div data-cancellation-list></div>';
            pending.closest('.section-card').after(panel);
        }
        render();
    }
    async function load() {
        mount();
        if (loading || !document.querySelector('[data-cancellation-list]')) return;
        loading = true;
        try { requests = (await api(endpoint)).requests; render(); }
        catch (error) { document.querySelectorAll('[data-cancellation-list]').forEach(box => { box.textContent = error.message; }); }
        finally { loading = false; }
    }
    window.showCancellationRequests = function () {
        mount();
        document.querySelectorAll('.view-toggle-btn').forEach(button => button.classList.remove('active'));
        document.querySelector('.cancellation-tab')?.classList.add('active');
        const list = document.getElementById('listView'), calendar = document.getElementById('calendarView');
        list?.classList.add('hidden'); if (list) list.style.display = 'none';
        calendar?.classList.remove('active'); if (calendar) calendar.style.display = 'none';
        const panel = document.getElementById('cancellationRequestsPanel'); if (panel) panel.hidden = false;
        load();
    };
    document.addEventListener('click', event => {
        const notification = event.target.closest('.notif-item');
        if (notification) {
            const url = new URL(notification.getAttribute('href'), location.href), id = url.searchParams.get('cancellation_request');
            if (id && /^\d+$/.test(id)) {
                event.preventDefault(); event.stopImmediatePropagation();
                document.querySelectorAll('.notif-dropdown').forEach(dropdown => dropdown.classList.remove('open'));
                const notifId = notification.dataset.notifId;
                if (notifId) fetch(new URL('../shared/notification_actions.php', location.href), { method:'POST',
                    headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=mark_read&id=' + encodeURIComponent(notifId) }).catch(() => {});
                notification.classList.remove('unread'); open(id); return;
            }
        }
        if (event.target.closest('.cancellation-tab')) { event.preventDefault(); window.showCancellationRequests(); }
        else if (event.target.closest('.view-toggle-btn')) {
            const panel = document.getElementById('cancellationRequestsPanel'); if (panel) panel.hidden = true;
            document.querySelector('.cancellation-tab')?.classList.remove('active');
            const list = document.getElementById('listView'), calendar = document.getElementById('calendarView');
            if (list) list.style.display = ''; if (calendar) calendar.style.display = '';
        }
    }, true);
    document.addEventListener('pjax:loaded', load);
    document.addEventListener('appointments:refreshed', load);
    setInterval(() => { if (document.visibilityState === 'visible') load(); }, 10000);
    function init() {
        load(); const id = new URLSearchParams(location.search).get('cancellation_request');
        if (id && /^\d+$/.test(id)) open(id);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
