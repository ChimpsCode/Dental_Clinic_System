document.addEventListener('DOMContentLoaded', function() {
    // Initialize Carousel
    initializeCarousel();
    
    const loginForm = document.getElementById('loginForm');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');
    const loginBtn = document.getElementById('loginBtn');
    const errorMessage = document.getElementById('errorMessage');
    const toast = document.getElementById('toast');
    const captchaImage = document.getElementById('captchaImage');
    const refreshCaptcha = document.getElementById('refreshCaptcha');
    const captchaInput = document.getElementById('captchaInput');

    // 2FA elements
    const twoFactorContainer = document.getElementById('twoFactorContainer');
    const twoFactorForm = document.getElementById('twoFactorForm');
    const twoFactorCode = document.getElementById('twoFactorCode');
    const twoFactorVerifyBtn = document.getElementById('twoFactorVerifyBtn');
    const twoFactorVerifyBtnText = document.getElementById('twoFactorVerifyBtnText');
    const twoFactorError = document.getElementById('twoFactorError');
    const backToLoginFrom2faLink = document.getElementById('backToLoginFrom2faLink');

    console.log('=== PAGE LOADED ===');
    console.log('Login form:', loginForm);
    console.log('Login button:', loginBtn);
    console.log('Toast element:', toast);
    
    // Carousel initialization function
    function initializeCarousel() {
        const slides = document.querySelectorAll('.carousel-slide');
        const dots = document.querySelectorAll('.dot');
        let currentSlide = 0;
        let slideInterval;
        
        function showSlide(n) {
            // Remove active class from all slides and dots
            slides.forEach(slide => slide.classList.remove('active'));
            dots.forEach(dot => dot.classList.remove('active'));
            
            // Add active class to current slide and dot
            if (slides[n]) {
                slides[n].classList.add('active');
            }
            if (dots[n]) {
                dots[n].classList.add('active');
            }
        }
        
        function nextSlide() {
            currentSlide = (currentSlide + 1) % slides.length;
            showSlide(currentSlide);
        }
        
        function goToSlide(n) {
            currentSlide = n % slides.length;
            showSlide(currentSlide);
            clearInterval(slideInterval);
            startAutoPlay();
        }
        
        function startAutoPlay() {
            slideInterval = setInterval(nextSlide, 5000); // Change slide every 5 seconds
        }
        
        // Add click handlers to dots
        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => goToSlide(index));
        });
        
        // Show first slide and start autoplay
        showSlide(0);
        startAutoPlay();
    }

    // Clear error message on page load (fresh login page)
    if (errorMessage) {
        errorMessage.style.display = 'none';
        errorMessage.textContent = '';
    }

    // Captcha refresh functionality
    if (refreshCaptcha) {
        refreshCaptcha.addEventListener('click', function() {
            refreshCaptchaCode();
        });
    }

    // Captcha timer and refresh function
    let captchaTimeLeft = 120;
    let captchaInterval;
    const captchaTimer = document.getElementById('captchaTimer');
    
    function refreshCaptchaCode() {
        const fallback = document.getElementById('captchaFallback');
        fetch('../../lib/security/captcha_refresh.php?force=1', { credentials: 'same-origin', cache: 'no-store' })
            .then(response => response.text())
            .then(code => {
                // Refresh image using the SAME session code generated above.
                if (captchaImage) {
                    captchaImage.src = '../../lib/security/captcha_image.php?t=' + Date.now();
                    captchaImage.style.display = 'inline-block';
                }

                if (fallback) {
                    fallback.style.display = 'none';
                }
            })
            .catch(() => {
                if (fallback) {
                    fallback.style.display = 'none';
                }
            });
        
       
        captchaTimeLeft = 120;
        if (captchaTimer) {
            captchaTimer.textContent = '2:00';
        }
        
        // Clear existing interval and restart
        if (captchaInterval) clearInterval(captchaInterval);
        startCaptchaTimer();
        
        if (captchaInput) {
            captchaInput.value = '';
            captchaInput.focus();
        }
    }
    
    function startCaptchaTimer() {
        captchaInterval = setInterval(function() {
            captchaTimeLeft--;
            const minutes = Math.floor(captchaTimeLeft / 60);
            const seconds = captchaTimeLeft % 60;
            
            if (captchaTimer) {
                captchaTimer.textContent = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
            }
            
            if (captchaTimeLeft <= 0) {
                refreshCaptchaCode();
            }
        }, 1000);
    }
    
    if (captchaTimer) {
        startCaptchaTimer();
    }

    // Registration toggle & submission
    const loginContainer = document.getElementById('loginContainer');
    const registrationContainer = document.getElementById('registrationContainer');
    const forgotContainer = document.getElementById('forgotContainer');
    const createAccountLink = document.getElementById('createAccountLink');
    const forgotPasswordLink = document.getElementById('forgotPasswordLink');
    const backToLoginLink = document.getElementById('backToLoginLink');
    const backToLoginFromForgotLink = document.getElementById('backToLoginFromForgotLink');
    const registerForm = document.getElementById('registerForm');
    const regStep1 = document.getElementById('regStep1');
    const regStep2 = document.getElementById('regStep2');
    const registerNextBtn = document.getElementById('registerNextBtn');
    const registerBackBtn = document.getElementById('registerBackBtn');
    const registerBtn = document.getElementById('registerBtn');
    const registerBtnText = document.getElementById('registerBtnText');
    const registerError = document.getElementById('registerError');
    const registerSuccess = document.getElementById('registerSuccess');
    const registerPasswordInput = document.getElementById('reg_password');
    const registerConfirmPasswordInput = document.getElementById('reg_confirm_password');
    const toggleRegisterPassword = document.getElementById('toggleRegPassword');
    const toggleRegisterConfirmPassword = document.getElementById('toggleRegConfirmPassword');
    const forgotError = document.getElementById('forgotError');
    const forgotSuccess = document.getElementById('forgotSuccess');
    const fpStep1 = document.getElementById('fpStep1');
    const fpStep2 = document.getElementById('fpStep2');
    const fpStep3 = document.getElementById('fpStep3');
    const fpEmail = document.getElementById('fp_email');
    const fpCode = document.getElementById('fp_code');
    const fpNewPassword = document.getElementById('fp_new_password');
    const fpConfirmPassword = document.getElementById('fp_confirm_password');
    const toggleFpNewPassword = document.getElementById('toggleFpNewPassword');
    const toggleFpConfirmPassword = document.getElementById('toggleFpConfirmPassword');
    const fpSendBtn = document.getElementById('fpSendBtn');
    const fpSendBtnText = document.getElementById('fpSendBtnText');
    const fpVerifyBtn = document.getElementById('fpVerifyBtn');
    const fpVerifyBtnText = document.getElementById('fpVerifyBtnText');
    const fpChangeBtn = document.getElementById('fpChangeBtn');
    const fpChangeBtnText = document.getElementById('fpChangeBtnText');
    const fpNode1 = document.getElementById('fpNode1');
    const fpNode2 = document.getElementById('fpNode2');
    const fpNode3 = document.getElementById('fpNode3');
    const fpLine1 = document.getElementById('fpLine1');
    const fpLine2 = document.getElementById('fpLine2');

    function showRegistrationStep(step) {
        if (regStep1) regStep1.style.display = step === 1 ? 'block' : 'none';
        if (regStep2) regStep2.style.display = step === 2 ? 'block' : 'none';
        if (registerNextBtn) registerNextBtn.style.display = step === 1 ? 'flex' : 'none';
        if (registerBtn) registerBtn.style.display = step === 2 ? 'flex' : 'none';
    }

    function showRegistration() {
        if (loginContainer) loginContainer.style.display = 'none';
        if (forgotContainer) forgotContainer.style.display = 'none';
        if (registrationContainer) registrationContainer.style.display = 'block';
        showRegistrationStep(1);
        document.title = 'Sign Up - RF Dental Clinic';
        if (window.location.hash !== '#register') {
            history.replaceState(null, '', '#register');
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function showLogin() {
        if (registrationContainer) registrationContainer.style.display = 'none';
        if (forgotContainer) forgotContainer.style.display = 'none';
        if (twoFactorContainer) twoFactorContainer.style.display = 'none';
        if (loginContainer) loginContainer.style.display = 'block';
        document.title = 'RF Dental Clinic - Login';
        if (window.location.hash !== '#login') {
            history.replaceState(null, '', '#login');
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
        // Clear any stale 2FA session data
        fetch('../../master/welcome-page/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=clear_2fa'
        }).catch(function(){});        
    }

    function showForgot() {
        if (loginContainer) loginContainer.style.display = 'none';
        if (registrationContainer) registrationContainer.style.display = 'none';
        if (forgotContainer) forgotContainer.style.display = 'block';
        document.title = 'Forgot Password - RF Dental Clinic';
        if (window.location.hash !== '#forgot') {
            history.replaceState(null, '', '#forgot');
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function showTwoFactor() {
        if (loginContainer) loginContainer.style.display = 'none';
        if (registrationContainer) registrationContainer.style.display = 'none';
        if (forgotContainer) forgotContainer.style.display = 'none';
        if (twoFactorContainer) twoFactorContainer.style.display = 'block';
        document.title = 'Two-Factor Auth - RF Dental Clinic';
        window.scrollTo({ top: 0, behavior: 'smooth' });
        if (twoFactorCode) { twoFactorCode.value = ''; twoFactorCode.focus(); }
        if (twoFactorError) twoFactorError.style.display = 'none';
    }

    [createAccountLink, backToLoginLink, forgotPasswordLink, backToLoginFromForgotLink, backToLoginFrom2faLink].forEach(link => {
        if (link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                if (this === createAccountLink) {
                    showRegistration();
                } else if (this === forgotPasswordLink) {
                    showForgot();
                } else {
                    showLogin();
                }
            });
        }
    });

    const regOtpStep = document.getElementById('regOtpStep');
    const regOtpCode = document.getElementById('regOtpCode');
    const regOtpVerifyBtn = document.getElementById('regOtpVerifyBtn');
    const regOtpVerifyBtnText = document.getElementById('regOtpVerifyBtnText');
    const regOtpError = document.getElementById('regOtpError');
    const regOtpEmailDisplay = document.getElementById('regOtpEmailDisplay');
    const regOtpResendBtn = document.getElementById('regOtpResendBtn');
    const regOtpTimer = document.getElementById('regOtpTimer');
    const backToRegisterFromOtpLink = document.getElementById('backToRegisterFromOtpLink');
    let regOtpCountdown = null;

    function showRegOtpStep(email) {
        if (registerForm) registerForm.style.display = 'none';
        if (registerBtn) registerBtn.style.display = 'none';
        if (regOtpStep) regOtpStep.style.display = 'block';
        if (regOtpEmailDisplay) regOtpEmailDisplay.textContent = email;
        if (regOtpCode) { regOtpCode.value = ''; regOtpCode.focus(); }
        if (regOtpError) regOtpError.style.display = 'none';
        startRegOtpTimer();
    }

    function hideRegOtpStep() {
        if (regOtpStep) regOtpStep.style.display = 'none';
        if (registerForm) registerForm.style.display = 'block';
        showRegistrationStep(2);
        stopRegOtpTimer();
    }

    function startRegOtpTimer() {
        stopRegOtpTimer();
        let timeLeft = 180;
        if (regOtpTimer) regOtpTimer.textContent = '(03:00)';
        if (regOtpResendBtn) regOtpResendBtn.disabled = true;
        regOtpCountdown = setInterval(function() {
            timeLeft--;
            if (timeLeft <= 0) {
                stopRegOtpTimer();
                if (regOtpTimer) regOtpTimer.textContent = '(expired)';
                if (regOtpResendBtn) regOtpResendBtn.disabled = false;
                return;
            }
            const m = String(Math.floor(timeLeft / 60)).padStart(2, '0');
            const s = String(timeLeft % 60).padStart(2, '0');
            if (regOtpTimer) regOtpTimer.textContent = '(' + m + ':' + s + ')';
        }, 1000);
    }

    function stopRegOtpTimer() {
        if (regOtpCountdown) {
            clearInterval(regOtpCountdown);
            regOtpCountdown = null;
        }
    }

    function sendRegisterRequest(body, onSuccess) {
        if (registerError) registerError.style.display = 'none';

        fetch('../../master/user-patient/patient_register.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'include',
            body: body.toString()
        })
        .then(async res => {
            const text = await res.text();
            let data = null;
            try {
                data = JSON.parse(text);
            } catch (e) {
                if (!res.ok) throw new Error('Server error (' + res.status + ').');
                throw new Error('Invalid server response.');
            }
            if (!res.ok) throw new Error(data?.message || ('Server error (' + res.status + ').'));
            return data;
        })
        .then(data => {
            onSuccess(data);
        })
        .catch((err) => {
            const msg = err?.message || 'Registration failed. Please try again.';
            if (registerError) {
                registerError.textContent = msg;
                registerError.style.display = 'block';
            }
            showToast('Error', msg, 'error');
        })
        .finally(() => {
            if (registerBtn) registerBtn.disabled = false;
            if (registerBtnText) registerBtnText.textContent = 'Create Account';
        });
    }

    function isStrongPassword(password) {
        return password.length >= 8
            && /[A-Z]/.test(password)
            && /[a-z]/.test(password)
            && /[0-9]/.test(password)
            && /[^A-Za-z0-9]/.test(password);
    }

    if (registerNextBtn) {
        registerNextBtn.addEventListener('click', function() {
            const firstName = document.getElementById('reg_first_name')?.value.trim() || '';
            const lastName = document.getElementById('reg_last_name')?.value.trim() || '';
            const phone = document.getElementById('reg_phone')?.value.trim() || '';
            const gender = document.getElementById('reg_gender')?.value || '';
            const dob = document.getElementById('reg_dob')?.value || '';
            const emergencyName = document.getElementById('reg_emergency_name')?.value.trim() || '';
            const emergencyPhone = document.getElementById('reg_emergency_phone')?.value.trim() || '';
            const emergencyRelationship = document.getElementById('reg_emergency_relationship')?.value.trim() || '';

            if (!firstName || !lastName || !phone || !gender || !dob || !emergencyName || !emergencyPhone || !emergencyRelationship) {
                showToast('Error', 'Please complete all personal and emergency contact details before continuing.', 'error');
                return;
            }

            showRegistrationStep(2);
            const emailField = document.getElementById('reg_email');
            if (emailField) emailField.focus();
        });
    }

    if (registerBackBtn) {
        registerBackBtn.addEventListener('click', function() {
            showRegistrationStep(1);
            const firstNameField = document.getElementById('reg_first_name');
            if (firstNameField) firstNameField.focus();
        });
    }

    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const pwd = document.getElementById('reg_password').value;
            const confirmPwd = document.getElementById('reg_confirm_password').value;
            const email = document.getElementById('reg_email')?.value.trim() || '';

            if (!email) {
                showToast('Error', 'Please enter your email address.', 'error');
                return;
            }

            if (pwd !== confirmPwd) {
                showToast('Error', 'Passwords do not match', 'error');
                return;
            }
            if (!isStrongPassword(pwd)) {
                showToast('Error', 'Password must be at least 8 characters and include uppercase, lowercase, number, and symbol', 'error');
                return;
            }

            const formData = new FormData(registerForm);
            const body = new URLSearchParams();
            formData.forEach((value, key) => body.append(key, value));
            body.append('action', 'register');

            if (registerBtn) registerBtn.disabled = true;
            if (registerBtnText) registerBtnText.textContent = 'Sending code...';

            sendRegisterRequest(body, function(data) {
                if (data.success) {
                    if (data.step === 'otp') {
                        const email = document.getElementById('reg_email')?.value || '';
                        showRegOtpStep(email);
                        showToast('Success', 'Verification code sent to your email.', 'success');
                    } else {
                        if (registerSuccess) {
                            registerSuccess.textContent = data.message || 'Account created successfully!';
                            registerSuccess.style.display = 'block';
                        }
                        showToast('Success', 'Account created! You can now log in.', 'success');
                        setTimeout(function() {
                            showLogin();
                            if (usernameInput) {
                                usernameInput.value = document.getElementById('reg_email')?.value || '';
                                usernameInput.focus();
                            }
                        }, 900);
                    }
                } else {
                    const msg = data.message || 'Registration failed. Please try again.';
                    if (registerError) {
                        registerError.textContent = msg;
                        registerError.style.display = 'block';
                    }
                    showToast('Error', msg, 'error');
                }
            });
        });
    }

    if (regOtpVerifyBtn) {
        regOtpVerifyBtn.addEventListener('click', function() {
            const code = regOtpCode ? regOtpCode.value.trim() : '';
            if (!code || code.length !== 6) {
                showToast('Error', 'Please enter the 6-digit verification code.', 'error');
                return;
            }

            regOtpVerifyBtn.disabled = true;
            if (regOtpVerifyBtnText) regOtpVerifyBtnText.textContent = 'Verifying...';
            if (regOtpError) regOtpError.style.display = 'none';

            const body = new URLSearchParams();
            body.append('action', 'verify_otp');
            body.append('otp', code);

            fetch('../../master/user-patient/patient_register.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'include',
                body: body.toString()
            })
            .then(async res => {
                const text = await res.text();
                let data = null;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    if (!res.ok) throw new Error('Server error (' + res.status + ').');
                    throw new Error('Invalid server response.');
                }
                if (!res.ok) throw new Error(data?.message || ('Server error (' + res.status + ').'));
                return data;
            })
            .then(data => {
                if (data.success) {
                    showToast('Success', 'Account created! You can now log in.', 'success');
                    stopRegOtpTimer();
                    hideRegOtpStep();
                    setTimeout(function() {
                        showLogin();
                        if (usernameInput) {
                            usernameInput.value = document.getElementById('reg_email')?.value || '';
                            usernameInput.focus();
                        }
                    }, 900);
                } else {
                    const msg = data.message || 'Verification failed.';
                    if (regOtpError) {
                        regOtpError.textContent = msg;
                        regOtpError.style.display = 'block';
                    }
                    showToast('Error', msg, 'error');
                }
            })
            .catch((err) => {
                const msg = err?.message || 'Verification failed. Please try again.';
                if (regOtpError) {
                    regOtpError.textContent = msg;
                    regOtpError.style.display = 'block';
                }
                showToast('Error', msg, 'error');
            })
            .finally(function() {
                regOtpVerifyBtn.disabled = false;
                if (regOtpVerifyBtnText) regOtpVerifyBtnText.textContent = 'Verify & Create Account';
            });
        });
    }

    if (regOtpResendBtn) {
        regOtpResendBtn.addEventListener('click', function() {
            const email = document.getElementById('reg_email')?.value || '';
            const body = new URLSearchParams();
            body.append('action', 'register');
            body.append('first_name', document.getElementById('reg_first_name')?.value || '');
            body.append('middle_name', document.getElementById('reg_middle_name')?.value || '');
            body.append('last_name', document.getElementById('reg_last_name')?.value || '');
            body.append('email', email);
            body.append('phone', document.getElementById('reg_phone')?.value || '');
            body.append('gender', document.getElementById('reg_gender')?.value || '');
            body.append('date_of_birth', document.getElementById('reg_dob')?.value || '');
            body.append('address', document.getElementById('reg_address')?.value || '');
            body.append('password', document.getElementById('reg_password')?.value || '');
            body.append('confirm_password', document.getElementById('reg_confirm_password')?.value || '');

            regOtpResendBtn.disabled = true;
            regOtpResendBtn.textContent = 'Sending...';

            sendRegisterRequest(body, function(data) {
                if (data.success && data.step === 'otp') {
                    showToast('Success', 'New verification code sent.', 'success');
                    startRegOtpTimer();
                } else {
                    const msg = data.message || 'Failed to resend code.';
                    showToast('Error', msg, 'error');
                    regOtpResendBtn.disabled = false;
                    regOtpResendBtn.textContent = 'Resend';
                }
            });
        });
    }

    if (backToRegisterFromOtpLink) {
        backToRegisterFromOtpLink.addEventListener('click', function(e) {
            e.preventDefault();
            hideRegOtpStep();
        });
    }

    function setForgotStep(step) {
        if (fpStep1) fpStep1.style.display = step === 1 ? 'block' : 'none';
        if (fpStep2) fpStep2.style.display = step === 2 ? 'block' : 'none';
        if (fpStep3) fpStep3.style.display = step === 3 ? 'block' : 'none';

        [fpNode1, fpNode2, fpNode3].forEach(node => {
            if (node) node.classList.remove('active', 'done');
        });
        [fpLine1, fpLine2].forEach(line => {
            if (line) line.classList.remove('active');
        });

        if (step === 1) {
            if (fpNode1) fpNode1.classList.add('active');
        } else if (step === 2) {
            if (fpNode1) fpNode1.classList.add('done');
            if (fpLine1) fpLine1.classList.add('active');
            if (fpNode2) fpNode2.classList.add('active');
        } else if (step === 3) {
            if (fpNode1) fpNode1.classList.add('done');
            if (fpNode2) fpNode2.classList.add('done');
            if (fpLine1) fpLine1.classList.add('active');
            if (fpLine2) fpLine2.classList.add('active');
            if (fpNode3) fpNode3.classList.add('active');
        }
    }

    function clearForgotMessages() {
        if (forgotError) forgotError.style.display = 'none';
        if (forgotSuccess) forgotSuccess.style.display = 'none';
    }

    function showForgotError(message) {
        if (forgotError) {
            forgotError.textContent = message;
            forgotError.style.display = 'block';
        }
        showToast('Error', message, 'error');
    }

    function showForgotSuccess(message) {
        if (forgotSuccess) {
            forgotSuccess.textContent = message;
            forgotSuccess.style.display = 'block';
        }
        showToast('Success', message, 'success');
    }

    function postForgotAction(payload) {
        return fetch('../../master/welcome-page/login.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(payload).toString()
        }).then(res => res.json());
    }

    if (fpSendBtn) {
        fpSendBtn.addEventListener('click', function() {
            const email = (fpEmail?.value || '').trim();
            if (!email) {
                showForgotError('Please enter your email address.');
                return;
            }
            clearForgotMessages();
            fpSendBtn.disabled = true;
            if (fpSendBtnText) fpSendBtnText.textContent = 'Sending...';
            postForgotAction({ action: 'send_code', email: email })
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Failed to send code.');
                    showForgotSuccess(data.message || 'Code sent. Check your email.');
                    setForgotStep(2);
                })
                .catch(err => showForgotError(err.message || 'Failed to send code.'))
                .finally(() => {
                    fpSendBtn.disabled = false;
                    if (fpSendBtnText) fpSendBtnText.textContent = 'Send Code';
                });
        });
    }

    if (fpVerifyBtn) {
        fpVerifyBtn.addEventListener('click', function() {
            const code = (fpCode?.value || '').trim();
            if (!code) {
                showForgotError('Enter the 6-digit code sent to your email.');
                return;
            }
            clearForgotMessages();
            fpVerifyBtn.disabled = true;
            if (fpVerifyBtnText) fpVerifyBtnText.textContent = 'Verifying...';
            postForgotAction({ action: 'verify_code', code: code })
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Verification failed.');
                    showForgotSuccess(data.message || 'Code verified.');
                    setForgotStep(3);
                })
                .catch(err => showForgotError(err.message || 'Verification failed.'))
                .finally(() => {
                    fpVerifyBtn.disabled = false;
                    if (fpVerifyBtnText) fpVerifyBtnText.textContent = 'Verify Code';
                });
        });
    }

    if (fpChangeBtn) {
        fpChangeBtn.addEventListener('click', function() {
            const newPwd = fpNewPassword?.value || '';
            const confirmPwd = fpConfirmPassword?.value || '';
            if (!newPwd || !confirmPwd) {
                showForgotError('Please complete both password fields.');
                return;
            }
            clearForgotMessages();
            fpChangeBtn.disabled = true;
            if (fpChangeBtnText) fpChangeBtnText.textContent = 'Updating...';
            postForgotAction({ action: 'change_password', password: newPwd, confirm_password: confirmPwd })
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Failed to update password.');
                    showForgotSuccess(data.message || 'Password updated successfully.');
                    setTimeout(() => showLogin(), 800);
                })
                .catch(err => showForgotError(err.message || 'Failed to update password.'))
                .finally(() => {
                    fpChangeBtn.disabled = false;
                    if (fpChangeBtnText) fpChangeBtnText.textContent = 'Change Password';
                });
        });
    }

    // Sync UI with URL hash on load and browser back/forward.
    function syncViewWithHash() {
        if (window.location.hash === '#register') {
            if (loginContainer) loginContainer.style.display = 'none';
            if (registrationContainer) registrationContainer.style.display = 'block';
            if (forgotContainer) forgotContainer.style.display = 'none';
            showRegistrationStep(1);
            document.title = 'Sign Up - RF Dental Clinic';
        } else if (window.location.hash === '#forgot') {
            if (loginContainer) loginContainer.style.display = 'none';
            if (registrationContainer) registrationContainer.style.display = 'none';
            if (forgotContainer) forgotContainer.style.display = 'block';
            document.title = 'Forgot Password - RF Dental Clinic';
        } else {
            if (registrationContainer) registrationContainer.style.display = 'none';
            if (forgotContainer) forgotContainer.style.display = 'none';
            if (loginContainer) loginContainer.style.display = 'block';
            document.title = 'RF Dental Clinic - Login';
            if (!window.location.hash) {
                history.replaceState(null, '', '#login');
            }
        }
    }

    setForgotStep(1);
    syncViewWithHash();
    window.addEventListener('hashchange', syncViewWithHash);

    // Show toast notification function (defined early for use in validation)
    function showToast(title, message, type = 'success') {
        if (!toast) return;
        
        const toastTitle = toast.querySelector('.toast-title');
        const toastMessage = toast.querySelector('.toast-message');
        const toastIcon = toast.querySelector('.toast-icon');
        const loadingLine = toast.querySelector('.toast-loading-line');
        
        if (toastTitle) toastTitle.textContent = title;
        if (toastMessage) toastMessage.textContent = message;
        
        // Update icon based on type
        if (toastIcon) {
            if (type === 'error') {
                toastIcon.textContent = '✕';
            } else {
                toastIcon.textContent = '✓';
            }
        }
        
        // Remove previous type classes
        toast.classList.remove('error', 'success');
        
        // Add type class
        if (type === 'error') {
            toast.classList.add('error');
        } else {
            toast.classList.add('success');
        }
        
        // Reset loading line animation
        if (loadingLine) {
            loadingLine.style.animation = 'none';
            // Force reflow to reset animation
            void loadingLine.offsetWidth;
            loadingLine.style.animation = '';
        }
        
        // Show toast
        toast.classList.add('show');
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            closeToast();
        }, 1000);
    }

    // Close toast function
    window.closeToast = function() {
        if (toast) {
            toast.classList.remove('show');
        }
    };

    // Expose for external use (e.g., account deleted redirect)
    window.showToast = showToast;

    // Show error message function - now uses toast instead of alert
    function showError(message) {
        // Use toast notification instead of alert box
        showToast('Error', message, 'error');
    }

    // Clear error message when user starts typing
    if (usernameInput && errorMessage) {
        usernameInput.addEventListener('input', function() {
            errorMessage.style.display = 'none';
            errorMessage.textContent = '';
        });
    }

    if (passwordInput && errorMessage) {
        passwordInput.addEventListener('input', function() {
            errorMessage.style.display = 'none';
            errorMessage.textContent = '';
        });
    }

    function setupPasswordToggle(input, toggle) {
        if (!input || !toggle) return;

        function updateToggleVisibility() {
            toggle.style.display = input.value.length > 0 ? 'flex' : 'none';
        }

        input.addEventListener('input', updateToggleVisibility);
        input.addEventListener('paste', updateToggleVisibility);
        input.addEventListener('keyup', updateToggleVisibility);
        updateToggleVisibility();

        toggle.addEventListener('click', function() {
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);

            const eyeOpen = toggle.querySelector('.eye-open');
            const eyeClosed = toggle.querySelector('.eye-closed');

            if (type === 'password') {
                if (eyeOpen) eyeOpen.style.display = 'block';
                if (eyeClosed) eyeClosed.style.display = 'none';
            } else {
                if (eyeOpen) eyeOpen.style.display = 'none';
                if (eyeClosed) eyeClosed.style.display = 'block';
            }
        });
    }

    // Toggle password visibility buttons - show/hide based on input
    setupPasswordToggle(passwordInput, togglePassword);
    setupPasswordToggle(registerPasswordInput, toggleRegisterPassword);
    setupPasswordToggle(registerConfirmPasswordInput, toggleRegisterConfirmPassword);
    setupPasswordToggle(fpNewPassword, toggleFpNewPassword);
    setupPasswordToggle(fpConfirmPassword, toggleFpConfirmPassword);

    // Form submission handler
    function handleLoginAttempt(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
            
            console.log('=== LOGIN CLICKED ===');
            
            const username = usernameInput ? usernameInput.value.trim() : '';
            const password = passwordInput ? passwordInput.value : '';
            const captcha = captchaInput ? captchaInput.value.trim() : '';

            console.log('Username:', username);
            console.log('Password:', password ? '***' : 'empty');
            console.log('Captcha:', captcha);

            // Clear previous error
            if (errorMessage) {
                errorMessage.style.display = 'none';
            }

            // Basic validation
            if (!username) {
                showToast('Error', 'Please enter your username', 'error');
                if (usernameInput) usernameInput.focus();
                return false;
            }

            if (!password) {
                showToast('Error', 'Please enter your password', 'error');
                if (passwordInput) passwordInput.focus();
                return false;
            }

            if (!captcha) {
                showToast('Error', 'Please enter the captcha code', 'error');
                if (captchaInput) captchaInput.focus();
                return false;
            }

            if (captcha.length !== 6 || !/^\d+$/.test(captcha)) {
                showToast('Error', 'Captcha must be 6 digits', 'error');
                if (captchaInput) captchaInput.focus();
                return false;
            }

            // Submit via AJAX
            const formData = new FormData(loginForm);
            formData.append('ajax', 'true');
            
            console.log('Sending AJAX request...');
            
            fetch('../../master/welcome-page/login.php', {
                method: 'POST',
                body: formData,
                credentials: 'include',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(async response => {
                console.log('Response received:', response);
                console.log('Response status:', response.status);
                console.log('Response ok:', response.ok);
                
                // Get response text first
                const text = await response.text();
                console.log('Response text:', text);
                
                // Check if response is ok
                if (!response.ok) {
                    // Try to parse error message if it's JSON
                    try {
                        const errorData = JSON.parse(text);
                        throw { type: 'http_error', status: response.status, data: errorData };
                    } catch (e) {
                        if (e.type === 'http_error') throw e;
                        throw { type: 'http_error', status: response.status, message: 'Server error: ' + response.status };
                    }
                }
                
                // Try to parse JSON
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed data:', data);
                    return data;
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response text that failed to parse:', text);
                    // If we can't parse JSON but got a 200 response, it might be HTML (redirect happened)
                    // Check if we're being redirected
                    if (text.includes('<!DOCTYPE') || text.includes('<html')) {
                        // Looks like we got HTML instead of JSON - might be a redirect
                        // Check if we should redirect
                        throw { type: 'redirect', message: 'Received HTML response, possible redirect' };
                    }
                    throw { type: 'parse_error', message: 'Invalid JSON response from server', originalError: e };
                }
            })
            .then(data => {
                console.log('Processing data:', data);
                
                // Validate data structure
                if (!data || typeof data !== 'object') {
                    throw { type: 'invalid_data', message: 'Invalid response format' };
                }
                
                if (data.success === true) {
                    if (data.two_factor_required === true) {
                        console.log('=== 2FA REQUIRED ===');
                        showToast('Info', data.message || 'Verification code sent to your email', 'success');
                        showTwoFactor();
                        return;
                    }

                    console.log('=== LOGIN SUCCESSFUL ===');
                    console.log('Showing toast...');
                    
                    // Show success toast
                    showToast('Success', 'Login successful!', 'success');
                    
                    const targetRedirect = data.redirect
                        ? new URL(data.redirect, window.location.href).toString()
                        : new URL('../../dashboard.php', window.location.href).toString();

                    loginBtn.disabled = true;
                    console.log('Resolved redirect target:', targetRedirect);

                    setTimeout(() => {
                        console.log('Redirecting to:', targetRedirect);
                        window.location.replace(targetRedirect);
                    }, 1000);
                } else {
                    console.log('=== LOGIN FAILED ===');
                    console.log('Error message:', data.message || 'Unknown error');
                    
                    // Show error toast
                    showToast('Error', data.message || 'An error occurred while logging in', 'error');

                    // Keep captcha in sync after invalid/expired attempts
                    if ((data.message || '').toLowerCase().includes('captcha')) {
                        refreshCaptchaCode();
                    }
                }
            })
            .catch(error => {
                console.error('=== ERROR ===');
                console.error('Error type:', error.type || error.name);
                console.error('Error message:', error.message || error);
                console.error('Full error:', error);
                
                // Don't show error if it's a redirect (login was successful)
                if (error.type === 'redirect') {
                    console.log('Redirect detected, login was successful');
                    return; // Don't show error, just let the redirect happen
                }
                
                // Only show error if we're still on the login page
                if (!window.location.pathname.includes('login.php')) {
                    console.log('Not on login page, skipping error display');
                    return;
                }
                
                // Show appropriate error message
                if (error.type === 'http_error') {
                    showToast('Error', error.data?.message || error.message || 'Server error occurred', 'error');
                } else if (error.type === 'parse_error') {
                    showToast('Error', 'Server response error. Please try again.', 'error');
                } else if (error.name === 'TypeError' && (error.message.includes('fetch') || error.message.includes('Failed to fetch'))) {
                    showToast('Error', 'Network error. Please check your connection and try again.', 'error');
                } else {
                    showToast('Error', 'An error occurred. Please try again.', 'error');
                }
            });
    }

    if (loginForm && loginBtn) {
        loginBtn.addEventListener('click', handleLoginAttempt);
        loginForm.addEventListener('submit', handleLoginAttempt);
    }

    // 2FA verification handler
    if (twoFactorVerifyBtn) {
        twoFactorVerifyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const code = twoFactorCode ? twoFactorCode.value.trim() : '';
            
            if (!code || code.length !== 6) {
                if (twoFactorError) { twoFactorError.textContent = 'Please enter a valid 6-digit code'; twoFactorError.style.display = 'block'; }
                return;
            }
            
            twoFactorVerifyBtn.disabled = true;
            twoFactorVerifyBtnText.textContent = 'Verifying...';
            if (twoFactorError) twoFactorError.style.display = 'none';

            const formData = new FormData();
            formData.append('action', 'verify_2fa');
            formData.append('code', code);

            fetch('../../master/welcome-page/login.php', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Success', 'Login successful!', 'success');
                    const targetRedirect = data.redirect
                        ? new URL(data.redirect, window.location.href).toString()
                        : new URL('../../dashboard.php', window.location.href).toString();
                    setTimeout(() => { window.location.replace(targetRedirect); }, 1000);
                } else {
                    showToast('Error', data.message || 'Invalid code', 'error');
                    if (twoFactorCode) { twoFactorCode.value = ''; twoFactorCode.focus(); }
                    twoFactorVerifyBtn.disabled = false;
                    twoFactorVerifyBtnText.textContent = 'Verify Code';
                }
            })
            .catch(() => {
                showToast('Error', 'Verification failed. Please try again.', 'error');
                twoFactorVerifyBtn.disabled = false;
                twoFactorVerifyBtnText.textContent = 'Verify Code';
            });
        });
    }

    // Enter key for 2FA
    if (twoFactorCode) {
        twoFactorCode.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && twoFactorVerifyBtn && !twoFactorVerifyBtn.disabled) {
                twoFactorVerifyBtn.click();
            }
        });
    }

    // Show error message function - now uses toast instead of alert
    function showError(message) {
        // Use toast notification instead of alert box
        showToast('Error', message, 'error');
    }

    // Input field focus animations
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            if (this.parentElement) {
                this.parentElement.classList.add('focused');
            }
        });

        input.addEventListener('blur', function() {
            if (!this.value && this.parentElement) {
                this.parentElement.classList.remove('focused');
            }
        });

        if (input.value && input.parentElement) {
            input.parentElement.classList.add('focused');
        }
    });

    // Enter key to submit
    document.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && document.activeElement && document.activeElement.tagName === 'INPUT') {
            const isRegisterVisible = registrationContainer && registrationContainer.style.display !== 'none';
            const isForgotVisible = forgotContainer && forgotContainer.style.display !== 'none';
            if (isForgotVisible) {
                if (fpStep1 && fpStep1.style.display !== 'none' && fpSendBtn && !fpSendBtn.disabled) {
                    fpSendBtn.click();
                } else if (fpStep2 && fpStep2.style.display !== 'none' && fpVerifyBtn && !fpVerifyBtn.disabled) {
                    fpVerifyBtn.click();
                } else if (fpStep3 && fpStep3.style.display !== 'none' && fpChangeBtn && !fpChangeBtn.disabled) {
                    fpChangeBtn.click();
                }
            } else if (isRegisterVisible) {
                if (regOtpStep && regOtpStep.style.display !== 'none' && regOtpVerifyBtn && !regOtpVerifyBtn.disabled) {
                    regOtpVerifyBtn.click();
                } else if (registerForm && registerForm.style.display !== 'none' && registerBtn && !registerBtn.disabled) {
                    registerBtn.click();
                }
            } else if (loginBtn && !loginBtn.disabled) {
                    if (loginForm.requestSubmit) {
                        loginForm.requestSubmit();
                    } else {
                        loginBtn.click();
                    }
            }
        }
    });

    // Auto-focus username field
    if (usernameInput && !usernameInput.value) {
        setTimeout(function() {
            usernameInput.focus();
        }, 100);
    }

    // Show PHP error message as toast on page load if it exists
    if (errorMessage && errorMessage.textContent.trim()) {
        const errorText = errorMessage.textContent.trim();
        if (errorText) {
            // Hide the alert box
            errorMessage.style.display = 'none';
            // Show as toast notification
            showToast('Error', errorText, 'error');
        }
    }

    // Page transition effect for navigation links
    const pageTransitionLinks = document.querySelectorAll('.page-transition');
    pageTransitionLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.href;
            
            // Add exit animation
            document.body.classList.add('exit-animation');
            
            // Navigate after animation completes
            setTimeout(() => {
                window.location.href = url;
            }, 350);
        });
    });
});
