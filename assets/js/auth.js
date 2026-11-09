/**
 * Authentication Handling Script
 */
document.addEventListener('DOMContentLoaded', () => {
  const loginForm = document.getElementById('loginForm');
  const alertBox = document.getElementById('authAlert');
  const submitBtn = document.getElementById('submitBtn');

  if (!loginForm) return;

  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const identifierInput = document.getElementById('identifier');
    const passwordInput = document.getElementById('password');

    const identifier = identifierInput.value.trim();
    const password = passwordInput.value;

    if (!identifier || !password) {
      showAlert('Please enter both username and password.', 'error');
      return;
    }

    const originalBtnText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Signing in...';
    hideAlert();

    const formData = new FormData(loginForm);
    formData.append('action', 'login');

    const res = await App.request('/api/auth.php', {
      method: 'POST',
      body: formData
    });

    if (res.ok && res.data.success) {
      App.toast('Login successful! Redirecting...', 'success');
      submitBtn.innerHTML = 'Redirecting...';
      setTimeout(() => {
        window.location.href = res.data.data.redirect || (App.getBaseUrl() + '/admin/index.php');
      }, 500);
    } else {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalBtnText;
      const errorMsg = res.data?.message || 'Login failed.';
      showAlert(errorMsg, 'error');
      App.toast(errorMsg, 'error');
    }
  });

  // Password visibility toggle handler
  document.querySelectorAll('.btn-toggle-password').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      const wrapper = this.closest('.password-input-wrapper') || this.parentElement;
      const input = wrapper ? wrapper.querySelector('input') : null;
      if (!input) return;

      const isPassword = (input.type === 'password');
      input.type = isPassword ? 'text' : 'password';

      const eyeShow = this.querySelector('.eye-show');
      const eyeHide = this.querySelector('.eye-hide');

      if (eyeShow) eyeShow.style.setProperty('display', isPassword ? 'none' : 'block', 'important');
      if (eyeHide) eyeHide.style.setProperty('display', isPassword ? 'block' : 'none', 'important');

      const label = isPassword ? 'Hide password' : 'Show password';
      this.setAttribute('aria-label', label);
      this.setAttribute('title', label);
    });
  });

  function showAlert(message, type = 'error') {
    if (alertBox) {
      alertBox.textContent = message;
      alertBox.className = 'quick-creds-box';
      alertBox.style.color = '#ef4444';
      alertBox.style.borderColor = '#fee2e2';
      alertBox.style.backgroundColor = '#fef2f2';
      alertBox.style.display = 'block';
    }
  }

  function hideAlert() {
    if (alertBox) {
      alertBox.style.display = 'none';
    }
  }
});
