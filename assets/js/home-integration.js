document.addEventListener('DOMContentLoaded', function() {
  const loginOverlay = document.getElementById('loginOverlay');
  const headerLoginBtn = document.getElementById('headerLoginBtn');
  const loginCloseBtn = document.getElementById('loginCloseBtn');
  const integratedLoginForm = document.getElementById('integratedLoginForm');
  const integratedUsername = document.getElementById('integrated-username');
  const integratedPassword = document.getElementById('integrated-password');
  const integratedTogglePassword = document.getElementById('integratedTogglePassword');
  const integratedLoginBtn = document.getElementById('integratedLoginBtn');
  const loginErrorMessage = document.getElementById('loginErrorMessage');
  const integrationToast = document.getElementById('integrationToast');

  console.log('=== HOME INTEGRATION SCRIPT LOADED ===');

  // Open login overlay
  function openLoginOverlay() {
    console.log('Opening login overlay...');
    loginOverlay.classList.add('active');
    document.body.classList.add('login-active');
    document.body.style.overflow = 'hidden';
    integratedUsername.focus();
  }

  // Close login overlay
  function closeLoginOverlay() {
    console.log('Closing login overlay...');
    loginOverlay.classList.remove('active');
    document.body.classList.remove('login-active');
    document.body.style.overflow = 'auto';
    // Reset form
    integratedLoginForm.reset();
    if (loginErrorMessage) {
      loginErrorMessage.style.display = 'none';
      loginErrorMessage.textContent = '';
    }
  }

  // Event listeners for opening login
  if (headerLoginBtn) {
    headerLoginBtn.addEventListener('click', function(e) {
      e.preventDefault();
      openLoginOverlay();
    });
  }

  // Event listener for closing login
  if (loginCloseBtn) {
    loginCloseBtn.addEventListener('click', closeLoginOverlay);
  }

  // Close on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && loginOverlay.classList.contains('active')) {
      closeLoginOverlay();
    }
  });

  // Close on overlay click (outside form)
  loginOverlay.addEventListener('click', function(e) {
    if (e.target === loginOverlay) {
      closeLoginOverlay();
    }
  });

  // Don't close when clicking inside form
  document.querySelector('.login-form-container').addEventListener('click', function(e) {
    e.stopPropagation();
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
  if (integratedUsername && loginErrorMessage) {
    integratedUsername.addEventListener('input', function() {
      loginErrorMessage.style.display = 'none';
      loginErrorMessage.textContent = '';
    });
  }

  if (integratedPassword && loginErrorMessage) {
    integratedPassword.addEventListener('input', function() {
      loginErrorMessage.style.display = 'none';
      loginErrorMessage.textContent = '';
    });
  }

  // Toggle password visibility button - show/hide based on input
  if (integratedTogglePassword && integratedPassword) {
    // Show/hide toggle button based on password input value
    function updateToggleVisibility() {
      if (integratedPassword.value.length > 0) {
        integratedTogglePassword.style.display = 'flex';
      } else {
        integratedTogglePassword.style.display = 'none';
      }
    }
    
    // Listen to input events
    integratedPassword.addEventListener('input', updateToggleVisibility);
    integratedPassword.addEventListener('paste', updateToggleVisibility);
    integratedPassword.addEventListener('keyup', updateToggleVisibility);
    
    // Check initial state
    updateToggleVisibility();
    
    // Toggle password visibility
    integratedTogglePassword.addEventListener('click', function() {
      const type = integratedPassword.getAttribute('type') === 'password' ? 'text' : 'password';
      integratedPassword.setAttribute('type', type);
      
      // Toggle between eye-open and eye-closed icons
      const eyeOpen = integratedTogglePassword.querySelector('.eye-open');
      const eyeClosed = integratedTogglePassword.querySelector('.eye-closed');
      
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
  if (integratedLoginForm && integratedLoginBtn) {
    integratedLoginBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      console.log('=== LOGIN CLICKED ===');
      
      const username = integratedUsername ? integratedUsername.value.trim() : '';
      const password = integratedPassword ? integratedPassword.value : '';

      console.log('Username:', username);
      console.log('Password:', password ? '***' : 'empty');

      if (!username || !password) {
        showError('Please fill in all fields');
        return;
      }

      // Disable button during submission
      integratedLoginBtn.disabled = true;
      const originalText = integratedLoginBtn.textContent;
      integratedLoginBtn.textContent = 'LOGGING IN...';

      // Prepare form data
      const formData = new FormData();
      formData.append('username', username);
      formData.append('password', password);

      // Send AJAX request
      fetch('login.php', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      })
      .then(response => {
        console.log('Response status:', response.status);
        return response.json();
      })
      .then(data => {
        console.log('Response data:', data);
        
        if (data.success) {
          showToast('Success', 'Logged in successfully!', 'success');
          
          // Redirect after a short delay
          setTimeout(() => {
            window.location.href = data.redirect || 'dashboard.php';
          }, 1500);
        } else {
          showError(data.message || 'Login failed. Please try again.');
          integratedLoginBtn.disabled = false;
          integratedLoginBtn.textContent = originalText;
          integratedPassword.value = '';
          integratedPassword.type = 'password';
          
          // Reset password toggle
          const eyeOpen = integratedTogglePassword.querySelector('.eye-open');
          const eyeClosed = integratedTogglePassword.querySelector('.eye-closed');
          if (eyeOpen) eyeOpen.style.display = 'block';
          if (eyeClosed) eyeClosed.style.display = 'none';
          integratedTogglePassword.style.display = 'none';
        }
      })
      .catch(error => {
        console.error('Fetch error:', error);
        showError('Network error. Please try again.');
        integratedLoginBtn.disabled = false;
        integratedLoginBtn.textContent = originalText;
      });
    });

    // Allow Enter key to submit form
    integratedLoginForm.addEventListener('keypress', function(e) {
      if (e.key === 'Enter' && !integratedLoginBtn.disabled) {
        e.preventDefault();
        integratedLoginBtn.click();
      }
    });
  }
});
