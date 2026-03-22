document.addEventListener('DOMContentLoaded', function() {
  const loginOverlay = document.getElementById('loginOverlay');
  const headerLoginBtn = document.getElementById('headerLoginBtn');
  const loginCloseBtn = document.getElementById('loginCloseBtn');
  const inlineLoginForm = document.getElementById('integratedLoginFormInline');
  const inlineUsername = document.getElementById('integrated-username-inline');
  const inlinePassword = document.getElementById('integrated-password-inline');
  const inlineTogglePassword = document.getElementById('integratedTogglePasswordInline');
  const inlineLoginBtn = document.getElementById('integratedLoginBtnInline');
  const loginErrorMessage = document.getElementById('loginErrorMessageInline');
  const headerSignUpBtn = document.querySelector('.header-actions a[href="patient_register.php"]');
  const switchToSignup = document.getElementById('switchToSignup');
  const inlineSignupClose = document.getElementById('inlineSignupClose');
  const inlineSignupForm = document.getElementById('integratedSignupFormInline');
  const signupFirst = document.getElementById('signup-firstname');
  const signupLast = document.getElementById('signup-lastname');
  const signupEmail = document.getElementById('signup-email');
  const signupPhone = document.getElementById('signup-phone');
  const signupPassword = document.getElementById('signup-password');
  const signupConfirm = document.getElementById('signup-confirm');
  const signupBtn = document.getElementById('integratedSignupBtnInline');
  const signupErrorMessage = document.getElementById('signupErrorMessageInline');
  const switchToLogin = document.getElementById('switchToLogin');
  const integrationToast = document.getElementById('integrationToast');

  console.log('=== HOME INTEGRATION SCRIPT LOADED ===');

  function setInlineMode(mode='login') {
    document.body.classList.add('inline-login-active');
    if (mode === 'signup') {
      document.body.classList.add('inline-mode-signup');
      if (signupFirst) signupFirst.focus();
    } else {
      document.body.classList.remove('inline-mode-signup');
      if (inlineUsername) inlineUsername.focus();
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  // Open login - redirect to login.php
  function openLoginOverlay() { 
    window.location.href = 'login.php';
  }
  
  // Open signup - redirect to patient_register.php
  function openSignupOverlay() { 
    window.location.href = 'patient_register.php';
  }

  // Close login (inline) - kept for compatibility but does nothing
  function closeLoginOverlay() {
    document.body.classList.remove('inline-login-active', 'inline-mode-signup');
    if (inlineLoginForm) inlineLoginForm.reset();
    if (inlineSignupForm) inlineSignupForm.reset();
    if (loginErrorMessage) {
      loginErrorMessage.style.display = 'none';
      loginErrorMessage.textContent = '';
    }
    if (signupErrorMessage) {
      signupErrorMessage.style.display = 'none';
      signupErrorMessage.textContent = '';
    }
  }

  if (headerLoginBtn) {
    headerLoginBtn.addEventListener('click', function(e) {
      e.preventDefault();
      openLoginOverlay();
    });
  }
  if (headerSignUpBtn) {
    headerSignUpBtn.addEventListener('click', function(e) {
      e.preventDefault();
      openSignupOverlay();
    });
  }

  if (loginCloseBtn) {
    loginCloseBtn.addEventListener('click', closeLoginOverlay);
  }
  const inlineCloseBtn = document.getElementById('inlineLoginClose');
  if (inlineCloseBtn) inlineCloseBtn.addEventListener('click', closeLoginOverlay);
  if (inlineSignupClose) inlineSignupClose.addEventListener('click', closeLoginOverlay);
  if (switchToSignup) switchToSignup.addEventListener('click', (e)=>{e.preventDefault(); openSignupOverlay();});
  if (switchToLogin) switchToLogin.addEventListener('click', (e)=>{e.preventDefault(); openLoginOverlay();});

  // Close on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.body.classList.contains('inline-login-active')) {
      closeLoginOverlay();
    }
  });

  // Show Toast Notification
  function showToast(title, message, type = 'success') {
    if (!integrationToast) return;
    
    const toastTitle = integrationToast.querySelector('.toast-title');
    const toastMessage = integrationToast.querySelector('.toast-message');
    const toastIcon = integrationToast.querySelector('.toast-icon');
    const loadingLine = integrationToast.querySelector('.toast-loading-line');
    
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
    integrationToast.classList.remove('error', 'success');
    
    // Add type class
    if (type === 'error') {
      integrationToast.classList.add('error');
    } else {
      integrationToast.classList.add('success');
    }
    
    // Reset loading line animation
    if (loadingLine) {
      loadingLine.style.animation = 'none';
      void loadingLine.offsetWidth;
      loadingLine.style.animation = '';
    }
    
    // Show toast
    integrationToast.classList.add('show');
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
      closeIntegrationToast();
    }, 5000);
  }

  // Close Toast
  window.closeIntegrationToast = function() {
    if (integrationToast) {
      integrationToast.classList.remove('show');
    }
  };

  // Show error message
  function showError(message) {
    showToast('Error', message, 'error');
  }

  // Clear error message when user starts typing
  if (inlineUsername && loginErrorMessage) {
    inlineUsername.addEventListener('input', function() {
      loginErrorMessage.style.display = 'none';
      loginErrorMessage.textContent = '';
    });
  }

  if (inlinePassword && loginErrorMessage) {
    inlinePassword.addEventListener('input', function() {
      loginErrorMessage.style.display = 'none';
      loginErrorMessage.textContent = '';
    });
  }

  // Toggle password visibility button - show/hide based on input
  if (inlineTogglePassword && inlinePassword) {
    function updateToggleVisibility() {
      inlineTogglePassword.style.display = inlinePassword.value.length > 0 ? 'flex' : 'none';
    }
    inlinePassword.addEventListener('input', updateToggleVisibility);
    inlinePassword.addEventListener('paste', updateToggleVisibility);
    inlinePassword.addEventListener('keyup', updateToggleVisibility);
    updateToggleVisibility();
    inlineTogglePassword.addEventListener('click', function() {
      const type = inlinePassword.getAttribute('type') === 'password' ? 'text' : 'password';
      inlinePassword.setAttribute('type', type);
      
      const eyeOpen = inlineTogglePassword.querySelector('.eye-open');
      const eyeClosed = inlineTogglePassword.querySelector('.eye-closed');
      
      if (type === 'password') {
        if (eyeOpen) eyeOpen.style.display = 'block';
        if (eyeClosed) eyeClosed.style.display = 'none';
      } else {
        if (eyeOpen) eyeOpen.style.display = 'none';
        if (eyeClosed) eyeClosed.style.display = 'block';
      }
    });
  }

  // Signup form handler
  if (inlineSignupForm && signupBtn) {
    signupBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();

      const first = signupFirst?.value.trim();
      const last = signupLast?.value.trim();
      const email = signupEmail?.value.trim();
      const phone = signupPhone?.value.trim();
      const pass = signupPassword?.value || '';
      const confirm = signupConfirm?.value || '';

      if (!first || !last || !email || !phone || !pass || !confirm) {
        showError('Please fill in all fields');
        return;
      }
      if (pass !== confirm) {
        showError('Passwords do not match');
        return;
      }

      signupBtn.disabled = true;
      const originalText = signupBtn.textContent;
      signupBtn.textContent = 'CREATING...';

      const formData = new FormData();
      formData.append('first_name', first);
      formData.append('last_name', last);
      formData.append('email', email);
      formData.append('phone', phone);
      formData.append('password', pass);
      formData.append('confirm_password', confirm);

      fetch('patient_register.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          showToast('Account created', 'You can now log in.', 'success');
          openLoginOverlay();
          if (inlineUsername) inlineUsername.value = email;
          if (inlinePassword) inlinePassword.value = '';
        } else {
          showError(data.message || 'Registration failed. Please try again.');
        }
      })
      .catch(err => {
        console.error(err);
        showError('Network error. Please try again.');
      })
      .finally(() => {
        signupBtn.disabled = false;
        signupBtn.textContent = originalText;
      });
    });
  }

  // Form submission handler
  if (inlineLoginForm && inlineLoginBtn) {
    inlineLoginBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      console.log('=== LOGIN CLICKED ===');
      
      const username = inlineUsername ? inlineUsername.value.trim() : '';
      const password = inlinePassword ? inlinePassword.value : '';

      console.log('Username:', username);
      console.log('Password:', password ? '***' : 'empty');

      if (!username || !password) {
        showError('Please fill in all fields');
        return;
      }

      // Show toast notification while logging in
      showToast('Logging In', 'Please wait while we verify your credentials...', 'success');

      // Disable button during submission
      inlineLoginBtn.disabled = true;
      const originalText = inlineLoginBtn.textContent;
      inlineLoginBtn.textContent = 'LOGGING IN...';

      // Prepare form data
      const formData = new FormData();
      formData.append('username', username);
      formData.append('password', password);

      // Send AJAX request
      console.log('Sending login request to: login.php');
      console.log('Username:', username);
      
      fetch('login.php', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      })
      .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers.get('Content-Type'));
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const contentType = response.headers.get('Content-Type');
        if (!contentType || !contentType.includes('application/json')) {
          throw new Error('Invalid response format. Expected JSON.');
        }
        
        return response.json();
      })
      .then(data => {
        console.log('Response data:', data);
        
        if (data.success) {
          showToast('Success', 'Logged in successfully!', 'success');
          
          // Redirect after a short delay
          setTimeout(() => {
            window.location.href = data.redirect || '../dashboard.php';
          }, 1500);
        } else {
          showError(data.message || 'Login failed. Please try again.');
          inlineLoginBtn.disabled = false;
          inlineLoginBtn.textContent = originalText;
          inlinePassword.value = '';
          inlinePassword.type = 'password';
          
          // Reset password toggle
          const eyeOpen = inlineTogglePassword.querySelector('.eye-open');
          const eyeClosed = inlineTogglePassword.querySelector('.eye-closed');
          if (eyeOpen) eyeOpen.style.display = 'block';
          if (eyeClosed) eyeClosed.style.display = 'none';
          inlineTogglePassword.style.display = 'none';
        }
      })
      .catch(error => {
        console.error('Fetch error:', error);
        showError('Network error: ' + error.message);
        inlineLoginBtn.disabled = false;
        inlineLoginBtn.textContent = originalText;
      });
    });

    // Allow Enter key to submit form
    inlineLoginForm.addEventListener('keypress', function(e) {
      if (e.key === 'Enter' && !inlineLoginBtn.disabled) {
        e.preventDefault();
        inlineLoginBtn.click();
      }
    });
  }
});
