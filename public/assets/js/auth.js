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
      showAlert('Please enter your Corporate ID/Email and password.', 'warning');
      return;
    }

    // Set button loading state
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span>Verifying credentials...</span>';
    hideAlert();

    const formData = new FormData(loginForm);
    formData.append('action', 'login');

    const res = await App.request('/api/auth.php', {
      method: 'POST',
      body: formData
    });

    if (res.ok && res.data.success) {
      App.toast('Login successful! Redirecting to workspace...', 'success');
      submitBtn.innerHTML = '<span>Redirecting...</span>';
      setTimeout(() => {
        window.location.href = res.data.data.redirect || '/admin/index.php';
      }, 700);
    } else {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalBtnText;
      const errorMsg = res.data?.message || 'Authentication failed. Please check your credentials.';
      showAlert(errorMsg, 'error');
      App.toast(errorMsg, 'error');
    }
  });

  function showAlert(message, type = 'error') {
    if (alertBox) {
      alertBox.textContent = message;
      alertBox.className = `quick-creds-box ${type === 'error' ? 'toast-error' : 'toast-warning'}`;
      alertBox.style.display = 'block';
    }
  }

  function hideAlert() {
    if (alertBox) {
      alertBox.style.display = 'none';
    }
  }
});
