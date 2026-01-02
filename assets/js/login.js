document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');
    const loginBtn = document.getElementById('loginBtn');
    const errorMessage = document.getElementById('errorMessage');

    // Toggle password visibility
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            togglePassword.textContent = type === 'password' ? '👁️' : '🙈';
        });
    }

    // Form submission handler
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const username = usernameInput ? usernameInput.value.trim() : '';
            const password = passwordInput ? passwordInput.value : '';

            // Clear previous error
            if (errorMessage) {
                errorMessage.style.display = 'none';
            }

            // Basic validation - only prevent if fields are empty
            if (!username) {
                e.preventDefault();
                showError('Please enter your username');
                if (usernameInput) usernameInput.focus();
                return false;
            }

            if (!password) {
                e.preventDefault();
                showError('Please enter your password');
                if (passwordInput) passwordInput.focus();
                return false;
            }

            // Show loading state - but DON'T prevent form submission
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
            
            // Allow form to submit - don't prevent default
            // Form will submit normally and server will handle redirect
        });
    }

    // Show error message function
    function showError(message) {
        if (errorMessage) {
            errorMessage.textContent = message;
            errorMessage.style.display = 'block';
            errorMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            // Create error message if it doesn't exist
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

        // Check if input has value on load
        if (input.value && input.parentElement) {
            input.parentElement.classList.add('focused');
        }
    });

    // Enter key to submit
    document.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && document.activeElement && document.activeElement.tagName === 'INPUT') {
            if (loginForm && !loginBtn.disabled) {
                loginForm.requestSubmit();
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
