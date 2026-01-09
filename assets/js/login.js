document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');
    const loginBtn = document.getElementById('loginBtn');
    const errorMessage = document.getElementById('errorMessage');
    const toast = document.getElementById('toast');

    console.log('=== PAGE LOADED ===');
    console.log('Login form:', loginForm);
    console.log('Login button:', loginBtn);
    console.log('Toast element:', toast);

    // Toggle password visibility
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle between eye-open and eye-closed icons
            const eyeOpen = togglePassword.querySelector('.eye-open');
            const eyeClosed = togglePassword.querySelector('.eye-closed');
            
            if (type === 'password') {
                // Show password is hidden, show open eye
                if (eyeOpen) eyeOpen.style.display = 'block';
                if (eyeClosed) eyeClosed.style.display = 'none';
            } else {
                // Show password is visible, show closed eye
                if (eyeOpen) eyeOpen.style.display = 'none';
                if (eyeClosed) eyeClosed.style.display = 'block';
            }
        });
    }

    // Form submission handler
    if (loginForm && loginBtn) {
        loginBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('=== LOGIN CLICKED ===');
            
            const username = usernameInput ? usernameInput.value.trim() : '';
            const password = passwordInput ? passwordInput.value : '';

            console.log('Username:', username);
            console.log('Password:', password ? '***' : 'empty');

            // Clear previous error
            if (errorMessage) {
                errorMessage.style.display = 'none';
            }

            // Basic validation
            if (!username) {
                showError('Please enter your username');
                if (usernameInput) usernameInput.focus();
                return false;
            }

            if (!password) {
                showError('Please enter your password');
                if (passwordInput) passwordInput.focus();
                return false;
            }

            // Show loading state
            const loginBtnText = document.getElementById('loginBtnText');
            const loginSpinner = document.getElementById('loginSpinner');
            
            if (loginBtnText) {
                loginBtnText.textContent = 'LOGGING IN...';
            }
            if (loginSpinner) {
                loginSpinner.style.display = 'inline-block';
            }
            
            if (loginBtn) {
                loginBtn.disabled = true;
                loginBtn.style.opacity = '0.8';
                loginBtn.style.cursor = 'wait';
                loginBtn.style.backgroundColor = '#9ca3af';
            }

            // Submit via AJAX
            const formData = new FormData(loginForm);
            formData.append('ajax', 'true');
            
            console.log('Sending AJAX request...');
            
            fetch('login.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                console.log('Response received:', response);
                return response.json();
            })
            .then(data => {
                console.log('Data:', data);
                
                if (data.success) {
                    console.log('=== LOGIN SUCCESSFUL ===');
                    console.log('Showing toast...');
                    
                    // Show success toast with animated border
                    if (toast) {
                        toast.classList.add('show');
                        console.log('Toast show class added');
                        console.log('Toast current classes:', toast.className);
                    } else {
                        console.error('Toast element not found!');
                    }
                    
                    // Wait 2 seconds for user to see animation
                    console.log('Waiting 2 seconds before redirect...');
                    setTimeout(() => {
                        console.log('Redirecting to dashboard...');
                        // Redirect to dashboard
                        window.location.href = 'dashboard.php';
                    }, 2000);
                } else {
                    console.log('=== LOGIN FAILED ===');
                    console.log('Error message:', data.message);
                    
                    // Show error
                    showError(data.message);
                    
                    // Reset button state
                    if (loginBtn) {
                        loginBtn.disabled = false;
                        loginBtn.style.opacity = '1';
                        loginBtn.style.cursor = 'pointer';
                        loginBtn.style.backgroundColor = '';
                    }
                    if (loginBtnText) {
                        loginBtnText.textContent = 'LOGIN';
                    }
                    if (loginSpinner) {
                        loginSpinner.style.display = 'none';
                    }
                }
            })
            .catch(error => {
                console.error('=== NETWORK ERROR ===');
                console.error('Error:', error);
                
                showError('Network error. Please try again.');
                
                // Reset button state
                if (loginBtn) {
                    loginBtn.disabled = false;
                    loginBtn.style.opacity = '1';
                    loginBtn.style.cursor = 'pointer';
                    loginBtn.style.backgroundColor = '';
                }
                if (loginBtnText) {
                    loginBtnText.textContent = 'LOGIN';
                }
                if (loginSpinner) {
                    loginSpinner.style.display = 'none';
                }
            });
        });
    }

    // Show error message function
    function showError(message) {
        if (errorMessage) {
            errorMessage.textContent = message;
            errorMessage.style.display = 'block';
            errorMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-error';
            alertDiv.id = 'errorMessage';
            alertDiv.textContent = message;
            if (loginForm) {
                loginForm.insertBefore(alertDiv, loginForm.firstChild);
            }
        }
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
            if (loginBtn && !loginBtn.disabled) {
                loginBtn.click();
            }
        }
    });

    // Auto-focus username field
    if (usernameInput && !usernameInput.value) {
        setTimeout(function() {
            usernameInput.focus();
        }, 100);
    }
});
