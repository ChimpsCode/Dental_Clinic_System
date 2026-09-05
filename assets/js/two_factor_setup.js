(function(window) {
    'use strict';

    function initTwoFactorSetup(options) {
        const endpoint = options && options.endpoint ? options.endpoint : '../shared/two_factor_actions.php';
        const initiallyEnabled = !!(options && options.initiallyEnabled);

        const toggle = document.getElementById('twoFactorAuth');
        const statusBadge = document.getElementById('twoFactorStatusBadge');
        const setupModal = document.getElementById('twoFactorSetupModal');
        const disableModal = document.getElementById('twoFactorDisableModal');
        const qrWrap = document.getElementById('twoFactorQrWrap');
        const setupKeyInput = document.getElementById('twoFactorSetupKey');
        const confirmCodeInput = document.getElementById('twoFactorConfirmCode');
        const disableCodeInput = document.getElementById('twoFactorDisableCode');
        const setupError = document.getElementById('twoFactorSetupError');
        const disableError = document.getElementById('twoFactorDisableError');
        const confirmBtn = document.getElementById('twoFactorConfirmBtn');
        const disableConfirmBtn = document.getElementById('twoFactorDisableConfirmBtn');
        const successModal = document.getElementById('twoFactorSuccessModal');
        const successTitle = document.getElementById('twoFactorSuccessTitle');
        const successMessage = document.getElementById('twoFactorSuccessMessage');
        const successIcon = document.getElementById('twoFactorSuccessIcon');
        const successOkBtn = document.getElementById('twoFactorSuccessOkBtn');

        if (!toggle || !setupModal || !disableModal) {
            return;
        }

        let twoFactorEnabled = initiallyEnabled;
        let setupInFlight = false;

        function setStatusBadge(enabled) {
            twoFactorEnabled = enabled;
            toggle.checked = enabled;
            if (!statusBadge) return;
            statusBadge.textContent = enabled ? 'Enabled' : 'Disabled';
            statusBadge.classList.toggle('on', enabled);
            statusBadge.classList.toggle('off', !enabled);
        }

        function showError(el, message) {
            if (!el) return;
            el.textContent = message || '';
            el.classList.toggle('visible', !!message);
        }

        function openModal(modal) {
            modal.classList.add('active');
        }

        function closeSetupModal(cancelPending) {
            setupModal.classList.remove('active');
            showError(setupError, '');
            if (confirmCodeInput) confirmCodeInput.value = '';
            if (cancelPending) {
                const formData = new FormData();
                formData.append('action', 'cancel_2fa_setup');
                fetch(endpoint, { method: 'POST', body: formData }).catch(function() {});
            }
        }

        function closeDisableModal() {
            disableModal.classList.remove('active');
            showError(disableError, '');
            if (disableCodeInput) disableCodeInput.value = '';
        }

        function showSuccessModal(title, message, type) {
            if (!successModal) return;
            if (successTitle) successTitle.textContent = title;
            if (successMessage) successMessage.textContent = message;
            if (successIcon) {
                successIcon.textContent = type === 'disabled' ? '!' : '✓';
                successIcon.classList.remove('enabled', 'disabled');
                successIcon.classList.add(type === 'disabled' ? 'disabled' : 'enabled');
            }
            successModal.classList.add('active');
        }

        function closeSuccessModal() {
            if (successModal) successModal.classList.remove('active');
        }

        function startSetup() {
            if (setupInFlight) return;
            setupInFlight = true;
            showError(setupError, '');
            if (qrWrap) qrWrap.innerHTML = '<div style="padding: 40px; color: #6b7280;">Generating QR code...</div>';
            if (setupKeyInput) setupKeyInput.value = '';
            if (confirmCodeInput) confirmCodeInput.value = '';
            openModal(setupModal);

            const formData = new FormData();
            formData.append('action', 'start_2fa_setup');

            fetch(endpoint, { method: 'POST', body: formData })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    setupInFlight = false;
                    if (!data.success) {
                        showError(setupError, data.message || 'Could not start 2FA setup.');
                        setStatusBadge(false);
                        return;
                    }

                    if (qrWrap) {
                        if (data.qr_data_uri) {
                            qrWrap.innerHTML = '<img src="' + data.qr_data_uri + '" alt="Authenticator QR code">';
                        } else if (data.qr_svg) {
                            qrWrap.innerHTML = data.qr_svg;
                        }
                    }
                    if (setupKeyInput) {
                        setupKeyInput.value = data.secret_display || data.secret || '';
                    }
                    if (confirmCodeInput) {
                        confirmCodeInput.focus();
                    }
                })
                .catch(function() {
                    setupInFlight = false;
                    showError(setupError, 'Failed to start 2FA setup. Please try again.');
                    setStatusBadge(false);
                });
        }

        function confirmSetup() {
            const code = (confirmCodeInput ? confirmCodeInput.value : '').trim();
            if (!/^\d{6}$/.test(code)) {
                showError(setupError, 'Enter the 6-digit code from your authenticator app.');
                return;
            }

            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Verifying...';
            showError(setupError, '');

            const formData = new FormData();
            formData.append('action', 'confirm_2fa_setup');
            formData.append('code', code);

            fetch(endpoint, { method: 'POST', body: formData })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = 'Enable 2FA';
                    if (!data.success) {
                        showError(setupError, data.message || 'Invalid verification code.');
                        if (confirmCodeInput) {
                            confirmCodeInput.value = '';
                            confirmCodeInput.focus();
                        }
                        return;
                    }
                    setStatusBadge(true);
                    closeSetupModal(false);
                    showSuccessModal(
                        '2FA Enabled',
                        'Two-factor authentication is now enabled for your account.',
                        'enabled'
                    );
                })
                .catch(function() {
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = 'Enable 2FA';
                    showError(setupError, 'Verification failed. Please try again.');
                });
        }

        function confirmDisable() {
            const code = (disableCodeInput ? disableCodeInput.value : '').trim();
            if (!/^\d{6}$/.test(code)) {
                showError(disableError, 'Enter the 6-digit code from your authenticator app.');
                return;
            }

            disableConfirmBtn.disabled = true;
            disableConfirmBtn.textContent = 'Disabling...';
            showError(disableError, '');

            const formData = new FormData();
            formData.append('action', 'disable_2fa');
            formData.append('code', code);

            fetch(endpoint, { method: 'POST', body: formData })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    disableConfirmBtn.disabled = false;
                    disableConfirmBtn.textContent = 'Disable 2FA';
                    if (!data.success) {
                        showError(disableError, data.message || 'Could not disable 2FA.');
                        if (disableCodeInput) {
                            disableCodeInput.value = '';
                            disableCodeInput.focus();
                        }
                        return;
                    }
                    setStatusBadge(false);
                    closeDisableModal();
                    showSuccessModal(
                        '2FA Disabled',
                        'Two-factor authentication has been disabled.',
                        'disabled'
                    );
                })
                .catch(function() {
                    disableConfirmBtn.disabled = false;
                    disableConfirmBtn.textContent = 'Disable 2FA';
                    showError(disableError, 'Request failed. Please try again.');
                });
        }

        toggle.addEventListener('change', function() {
            if (toggle.checked) {
                toggle.checked = false;
                startSetup();
            } else {
                toggle.checked = true;
                if (disableCodeInput) disableCodeInput.value = '';
                showError(disableError, '');
                openModal(disableModal);
                if (disableCodeInput) disableCodeInput.focus();
            }
        });

        document.getElementById('twoFactorSetupCloseBtn').addEventListener('click', function() {
            closeSetupModal(true);
            setStatusBadge(twoFactorEnabled);
        });
        document.getElementById('twoFactorSetupCancelBtn').addEventListener('click', function() {
            closeSetupModal(true);
            setStatusBadge(twoFactorEnabled);
        });
        document.getElementById('twoFactorDisableCloseBtn').addEventListener('click', function() {
            closeDisableModal();
            setStatusBadge(twoFactorEnabled);
        });
        document.getElementById('twoFactorDisableCancelBtn').addEventListener('click', function() {
            closeDisableModal();
            setStatusBadge(twoFactorEnabled);
        });

        confirmBtn.addEventListener('click', confirmSetup);
        disableConfirmBtn.addEventListener('click', confirmDisable);

        if (successOkBtn) {
            successOkBtn.addEventListener('click', closeSuccessModal);
        }
        if (successModal) {
            successModal.addEventListener('click', function(e) {
                if (e.target === successModal) closeSuccessModal();
            });
        }

        document.getElementById('twoFactorCopyKeyBtn').addEventListener('click', function() {
            const value = (setupKeyInput && setupKeyInput.value ? setupKeyInput.value : '').replace(/\s+/g, '');
            if (!value || !setupKeyInput) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(function() {
                    const btn = document.getElementById('twoFactorCopyKeyBtn');
                    const original = btn.textContent;
                    btn.textContent = 'Copied';
                    setTimeout(function() { btn.textContent = original; }, 1500);
                }).catch(function() {
                    setupKeyInput.select();
                    document.execCommand('copy');
                });
            } else {
                setupKeyInput.select();
                document.execCommand('copy');
            }
        });

        if (confirmCodeInput) {
            confirmCodeInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') confirmSetup();
            });
            confirmCodeInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 6);
            });
        }
        if (disableCodeInput) {
            disableCodeInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') confirmDisable();
            });
            disableCodeInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 6);
            });
        }

        setupModal.addEventListener('click', function(e) {
            if (e.target === setupModal) {
                closeSetupModal(true);
                setStatusBadge(twoFactorEnabled);
            }
        });
        disableModal.addEventListener('click', function(e) {
            if (e.target === disableModal) {
                closeDisableModal();
                setStatusBadge(twoFactorEnabled);
            }
        });

        setStatusBadge(initiallyEnabled);
    }

    window.initTwoFactorSetup = initTwoFactorSetup;
})(window);
