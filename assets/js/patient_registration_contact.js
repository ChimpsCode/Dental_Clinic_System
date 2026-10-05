(() => {
    const form = document.querySelector('#registerForm, #registrationForm');
    if (!form) return;
    const method = form.querySelector('[name="contact_method"]');
    const email = form.querySelector('[name="email"]');
    const phone = form.querySelector('[name="phone"]');
    if (!method || !email || !phone) return;
    const emailGroup = email.closest('.input-group, .form-group');
    const mobileInput = form.querySelector('#reg-contact-mobile');
    const mobileGroup = form.querySelector('[data-contact-mobile-group]');
    const stages = [...form.querySelectorAll('[data-registration-stage]')];
    const summary = form.querySelector('#reg-contact-summary');
    const summaryLabel = form.querySelector('[data-contact-summary-label]');
    const error = form.querySelector('[data-contact-error]');
    let currentStage = 'details';
    const identifier = () => (method.value === 'phone' ? phone.value : email.value).trim();

    function showError(message) {
        if (error) {
            error.textContent = message;
            error.style.display = message ? 'block' : 'none';
        }
    }

    function showStage(stage, focus = true) {
        if (!['details', 'contact', 'password'].includes(stage)) return;
        currentStage = stage;
        stages.forEach(panel => {
            panel.style.display = panel.dataset.registrationStage === stage ? 'block' : 'none';
        });
        if (stage === 'contact' && mobileInput) mobileInput.value = phone.value;
        if (summary) summary.value = identifier();
        if (summaryLabel) summaryLabel.textContent = method.value === 'phone' ? 'mobile number' : 'email address';
        const submit = form.querySelector('button[type="submit"]');
        if (submit) submit.style.display = stage === 'password' ? '' : 'none';
        const detailsNext = form.querySelector('#registerNextBtn');
        if (detailsNext) detailsNext.style.display = stage === 'details' ? 'flex' : 'none';
        const modalBody = form.closest('.modal-body');
        if (modalBody) modalBody.scrollTop = 0;
        const intro = modalBody?.querySelector('.modal-intro');
        if (intro) intro.textContent = {
            details: 'Complete your patient details, then choose how to register.',
            contact: 'Choose email or mobile number, enter your contact, then select Next.',
            password: 'Confirm your contact below and create your password.'
        }[stage];
        if (focus) {
            const panel = stages.find(node => node.dataset.registrationStage === stage);
            const first = stage === 'password' ? form.querySelector('[name="password"]')
                : stage === 'contact' ? (method.value === 'phone' ? mobileInput : email)
                : panel?.querySelector('input:not([type="hidden"]):not([aria-hidden="true"]):not([disabled]), select');
            first?.focus();
        }
    }

    function validatePanel(stage) {
        const panel = stages.find(node => node.dataset.registrationStage === stage);
        const invalid = [...(panel?.querySelectorAll('input, select') || [])]
            .find(field => !field.disabled && field.type !== 'hidden' && !field.checkValidity());
        if (invalid) {
            showStage(stage, false);
            invalid.reportValidity();
            invalid.focus();
            return false;
        }
        return true;
    }

    function nextContact() {
        showError('');
        if (method.value === 'phone' && mobileInput) {
            phone.value = mobileInput.value.trim();
            if (!/^09\d{9}$/.test(phone.value)) {
                showError('Enter an 11-digit mobile number starting with 09.');
                mobileInput.focus();
                return false;
            }
        }
        if (!validatePanel('contact')) return false;
        const status = document.getElementById('reg-email-status');
        if (method.value === 'email' && status?.textContent) {
            showError(status.textContent);
            return false;
        }
        showStage('password');
        return true;
    }

    function update() {
        const mobile = method.value === 'phone';
        email.required = !mobile;
        email.disabled = mobile;
        if (emailGroup) emailGroup.style.display = mobile ? 'none' : '';
        if (mobileGroup) mobileGroup.style.display = mobile ? '' : 'none';
        if (mobileInput) {
            mobileInput.required = mobile;
            mobileInput.disabled = !mobile;
            mobileInput.value = phone.value;
        }
        showError('');
        const hint = form.querySelector('[data-contact-hint]');
        if (hint) hint.textContent = mobile
            ? 'We will text a verification code to your phone number. Use this number to log in.'
            : 'We will email a verification code. Use your email address to log in.';
        const status = document.getElementById('reg-email-status');
        if (status) { status.textContent = ''; status.style.display = 'none'; }
        if (mobile) {
            // Cancel any pending duplicate-email result in page-specific handlers.
            email.value = '';
            email.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
    window.PatientRegistration = {
        isMobile: () => method.value === 'phone',
        identifier,
        select: value => { method.value = value; update(); },
        showStage
    };
    mobileInput?.addEventListener('input', () => {
        mobileInput.value = mobileInput.value.replace(/\D/g, '').slice(0, 11);
        phone.value = mobileInput.value;
        showError('');
    });
    form.querySelector('[data-registration-contact-next]')?.addEventListener('click', nextContact);
    form.querySelector('[data-registration-details-next]')?.addEventListener('click', () => {
        if (validatePanel('details')) showStage('contact');
    });
    form.querySelectorAll('[data-registration-back]').forEach(button => {
        button.addEventListener('click', () => showStage(button.dataset.registrationBack));
    });
    // Enter follows the same step sequence as Next; it cannot submit hidden passwords.
    form.addEventListener('submit', event => {
        if (currentStage !== 'password') {
            event.preventDefault();
            event.stopImmediatePropagation();
            if (currentStage === 'contact') nextContact();
            else if (validatePanel('details')) showStage('contact');
        } else {
            for (const stage of ['details', 'contact', 'password']) {
                if (!validatePanel(stage)) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    break;
                }
            }
        }
    }, true);
    method.addEventListener('change', update);
    update();
    showStage('details', false);
})();
