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

    // Show error message function - now uses toast instead of alert
    function showError(message) {
        // Use toast notification instead of alert box
        showToast('Error', message, 'error');
    }

    // Toggle password visibility button - show/hide based on input
    if (togglePassword && passwordInput) {
        // Show/hide toggle button based on password input value
        function updateToggleVisibility() {
            if (passwordInput.value.length > 0) {
                togglePassword.style.display = 'flex';
            } else {
                togglePassword.style.display = 'none';
            }
        }
        
        // Listen to input events
        passwordInput.addEventListener('input', updateToggleVisibility);
        passwordInput.addEventListener('paste', updateToggleVisibility);
        passwordInput.addEventListener('keyup', updateToggleVisibility);
        
        // Check initial state
        updateToggleVisibility();
        
        // Toggle password visibility
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
                showToast('Error', 'Please enter your username', 'error');
                if (usernameInput) usernameInput.focus();
                return false;
            }

            if (!password) {
                showToast('Error', 'Please enter your password', 'error');
                if (passwordInput) passwordInput.focus();
                return false;
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
                    console.log('=== LOGIN SUCCESSFUL ===');
                    console.log('Showing toast...');
                    
                    // Show success toast
                    showToast('Success', 'Login successful!', 'success');
                    
                    // Wait 2 seconds for user to see animation
                    console.log('Waiting 2 seconds before redirect...');
                    setTimeout(() => {
                        console.log('Redirecting to:', data.redirect || 'admin_dashboard.php');
                        // Redirect based on user role (admin or staff)
                        window.location.href = data.redirect || 'admin_dashboard.php';
                    }, 1000);
                } else {
                    console.log('=== LOGIN FAILED ===');
                    console.log('Error message:', data.message || 'Unknown error');
                    
                    // Show error toast
                    showToast('Error', data.message || 'An error occurred while logging in', 'error');
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
            }, 500);
        });
    });
});
