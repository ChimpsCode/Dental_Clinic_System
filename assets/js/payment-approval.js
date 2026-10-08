(function () {
    'use strict';
    if (window.PaymentApproval) return;
    const endpoint = '../shared/payment_approval.php';
    let paymentId = 0, csrf = '', pending = false;
    let adminLoadVersion = 0;
    const el = id => document.getElementById(id);
    const text = (tag, value) => { const node = document.createElement(tag); node.textContent = value; return node; };
    const adminPlaceholder = label => {
        const select = el('paymentVoidAdmin'), option = text('option', label);
        option.value = ''; select.replaceChildren(option); select.value = ''; select.disabled = true;
    };
    const refreshPinSave = form => {
        const button = el('paymentApprovalPinEdit');
        if (button.dataset.editing !== 'true') return;
        const fields = form.querySelectorAll('input[type="password"]');
        const ready = fields[0].value !== '' && /^\d{6}$/.test(fields[1].value) && fields[1].value === fields[2].value;
        button.className = ready ? 'btn-primary' : 'btn-primary is-idle';
        button.disabled = pending || !ready;
    };
    const setPinEditing = (form, editing) => {
        const button = el('paymentApprovalPinEdit');
        button.dataset.editing = String(editing);
        button.type = editing ? 'submit' : 'button';
        button.textContent = editing ? 'Save changes' : 'Edit';
        button.className = editing ? 'btn-primary is-idle' : 'btn-edit';
        button.disabled = false;
        form.querySelectorAll('input[type="password"]').forEach(input => {
            input.disabled = !editing;
            input.classList.toggle('is-locked', !editing);
        });
        if (editing) refreshPinSave(form);
    };
    window.PaymentApproval = {
        renderHistoryRow(payment) {
            const row = document.createElement('tr');
            row.append(text('td', payment.payment_date || 'N/A'));
            const amount = text('td', '₱' + Number(payment.amount || 0).toFixed(2));
            row.append(amount, text('td', payment.payment_method || 'Cash'));
            const details = text('td', payment.reference_number || '-');
            const history = text('small', 'PAY-' + String(payment.id).padStart(5, '0')
                + '\nRecorded by: ' + (payment.recorded_by || 'Unknown')
                + '\nRecorded: ' + (payment.created_at || payment.payment_date || 'N/A'));
            history.className = 'payment-void-details';
            details.append(history);
            const action = document.createElement('td');
            action.className = 'payment-history-action';
            if (payment.voided_at) {
                action.append(text('strong', 'VOIDED'));
                const note = text('small', 'Reason: ' + payment.void_reason
                    + '\nInitiated by: ' + (payment.requested_by || 'Unknown')
                    + '\nApproved by: ' + (payment.approved_by || 'Unknown') + '\nVoided: ' + payment.voided_at);
                note.className = 'payment-void-details';
                details.append(note);
            } else {
                const button = text('button', 'Void Payment');
                button.type = 'button'; button.className = 'payment-void-trigger';
                button.dataset.paymentId = payment.id;
                button.dataset.amount = payment.amount;
                action.append(button);
            }
            row.append(details, action);
            return row;
        }
    };
    document.addEventListener('click', async event => {
        const edit = event.target.closest('#paymentApprovalPinEdit');
        if (edit && edit.dataset.editing !== 'true') {
            event.preventDefault();
            const form = el('paymentApprovalPinForm');
            form.reset();
            el('paymentApprovalPinStatus').textContent = '';
            setPinEditing(form, true);
            form.querySelectorAll('input[type="password"]')[0].focus();
            return;
        }
        const trigger = event.target.closest('.payment-void-trigger');
        if (trigger) {
            const loadVersion = ++adminLoadVersion;
            csrf = '';
            paymentId = Number(trigger.dataset.paymentId);
            el('paymentVoidForm').reset();
            el('paymentVoidSummary').textContent = 'Payment #PAY-'
                + String(paymentId).padStart(5, '0') + ' · ₱'
                + Number(trigger.dataset.amount).toLocaleString('en-PH', { minimumFractionDigits:2, maximumFractionDigits:2 });
            el('paymentVoidError').textContent = '';
            adminPlaceholder('Loading admins…');
            el('paymentVoidConfirm').disabled = true;
            el('paymentVoidDialog').showModal();
            try {
                const response = await fetch(endpoint, { cache: 'no-store' });
                const data = await response.json();
                if (loadVersion !== adminLoadVersion) return;
                if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load approval settings.');
                csrf = data.csrf;
                if (!Array.isArray(data.admins) || !data.admins.length) {
                    adminPlaceholder('No admins with a configured PIN');
                    throw new Error('An admin must configure their Payment Approval PIN in Admin Settings → Security · Payment Approval PIN first.');
                }
                adminPlaceholder('Select an approving admin');
                data.admins.forEach(admin => {
                    const name = [admin.first_name, admin.last_name].filter(Boolean).join(' ');
                    const username = admin.username || 'Admin #' + admin.id;
                    const option = text('option', name ? name + ' (' + username + ')' : username);
                    option.value = admin.id; el('paymentVoidAdmin').append(option);
                });
                el('paymentVoidAdmin').disabled = false;
                if (data.admins.length === 1) el('paymentVoidAdmin').value = String(data.admins[0].id);
                el('paymentVoidConfirm').disabled = !el('paymentVoidAdmin').value;
                el('paymentVoidReason').focus();
            } catch (error) {
                if (loadVersion !== adminLoadVersion) return;
                if (el('paymentVoidAdmin').children[0]?.textContent === 'Loading admins…') adminPlaceholder('Unable to load admins');
                el('paymentVoidError').textContent = error.message;
            }
        }
        if (event.target.closest('#paymentVoidCancel') && !pending) {
            adminLoadVersion++;
            el('paymentVoidForm').reset(); el('paymentVoidDialog').close(); csrf = '';
        }
    });
    document.addEventListener('cancel', event => {
        if (event.target.id !== 'paymentVoidDialog') return;
        if (pending) event.preventDefault();
        else { adminLoadVersion++; el('paymentVoidForm').reset(); csrf = ''; }
    }, true);
    document.addEventListener('change', event => {
        if (event.target.id === 'paymentVoidAdmin') el('paymentVoidConfirm').disabled = pending || !event.target.value;
    });
    document.addEventListener('input', event => {
        if (event.target.closest('#paymentApprovalPinForm')) refreshPinSave(el('paymentApprovalPinForm'));
    });
    document.addEventListener('submit', async event => {
        if (!['paymentVoidForm', 'paymentApprovalPinForm'].includes(event.target.id)) return;
        event.preventDefault();
        if (pending) return;
        const form = event.target, isVoid = form.id === 'paymentVoidForm';
        if (!isVoid && (el('paymentApprovalPinEdit').dataset.editing !== 'true' || el('paymentApprovalPinEdit').disabled)) return;
        const values = Object.fromEntries(new FormData(form));
        if (isVoid && (!csrf || el('paymentVoidAdmin').disabled || !values.admin_id)) {
            el('paymentVoidError').textContent = 'Select an approving admin before confirming.';
            return;
        }
        const status = isVoid ? el('paymentVoidError') : el('paymentApprovalPinStatus');
        const button = form.querySelector('[type="submit"]');
        pending = true; button.disabled = true; status.textContent = '';
        try {
            const response = await fetch(endpoint, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...values, csrf: isVoid ? csrf : values.csrf,
                    action: isVoid ? 'void_payment' : 'set_pin', payment_id: paymentId })
            });
            // Clear credentials immediately after sending, including failed attempts.
            form.querySelectorAll('input[type="password"]').forEach(input => { input.value = ''; });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Unable to save.');
            if (isVoid) { form.reset(); el('paymentVoidDialog').close(); window.location.reload(); }
            else {
                form.reset(); status.textContent = data.message;
                const badge = el('paymentApprovalPinConfigured');
                badge.textContent = 'PIN configured'; badge.className = 'twofa-status on';
                setPinEditing(form, false);
            }
        } catch (error) { status.textContent = error.message; }
        finally {
            pending = false;
            form.querySelectorAll('input[type="password"]').forEach(input => { input.value = ''; });
            if (isVoid) button.disabled = false;
            else refreshPinSave(form);
        }
    });
})();
