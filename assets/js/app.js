/**
 * FIFO System Core Application JS Library
 * Handles Toasts, Modals, CSRF, and Dynamic Base URL Fetching
 */

const App = {
  // Get base URL prefix (e.g. '/FIFO' or '')
  getBaseUrl() {
    const meta = document.querySelector('meta[name="base-url"]');
    return meta ? meta.getAttribute('content') : '';
  },

  // Toast Notification System
  toast(message, type = 'info', duration = 3500) {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const icons = {
      success: '✓',
      error: '✕',
      info: 'ℹ'
    };

    toast.innerHTML = `
      <div>${icons[type] || 'ℹ'}</div>
      <div>${message}</div>
    `;

    container.appendChild(toast);

    requestAnimationFrame(() => {
      toast.classList.add('show');
    });

    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 250);
    }, duration);
  },

  // Modal Manager
  modal: {
    open(modalId) {
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
      }
    },
    close(modalId) {
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
      }
    }
  },

  // Dynamic AJAX Request with CSRF
  async request(endpoint, options = {}) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const basePath = this.getBaseUrl();
    
    // Prefix endpoint with base path if needed
    let fullUrl = endpoint;
    if (endpoint.startsWith('/') && basePath && !endpoint.startsWith(basePath)) {
      fullUrl = basePath + endpoint;
    }

    const headers = {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-Token': csrfToken,
      ...(options.headers || {})
    };

    try {
      const response = await fetch(fullUrl, { ...options, headers });
      const data = await response.json();
      return { ok: response.ok, status: response.status, data };
    } catch (err) {
      console.error('Request Error:', err);
      return {
        ok: false,
        status: 500,
        data: { success: false, message: 'Server communication failed.' }
      };
    }
  },

  // Alias for request
  async fetchJson(endpoint, options = {}) {
    return this.request(endpoint, options);
  },

  init() {
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
      btn.addEventListener('click', () => {
        const modal = btn.closest('.modal-backdrop');
        if (modal) {
          modal.classList.remove('active');
          document.body.style.overflow = '';
        }
      });
    });

    // Password visibility toggle handler (delegated to support all forms and modals)
    document.addEventListener('click', (e) => {
      const toggleBtn = e.target.closest('.btn-toggle-password');
      if (!toggleBtn) return;
      e.preventDefault();

      const wrapper = toggleBtn.closest('.password-input-wrapper') || toggleBtn.parentElement;
      const input = wrapper ? wrapper.querySelector('input') : null;
      if (!input) return;

      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';

      const eyeShow = toggleBtn.querySelector('.eye-show');
      const eyeHide = toggleBtn.querySelector('.eye-hide');

      if (eyeShow && eyeHide) {
        eyeShow.style.display = isPassword ? 'none' : 'block';
        eyeHide.style.display = isPassword ? 'block' : 'none';
      }

      const label = isPassword ? 'Hide password' : 'Show password';
      toggleBtn.setAttribute('aria-label', label);
      toggleBtn.setAttribute('title', label);
    });
  }
};

document.addEventListener('DOMContentLoaded', () => App.init());
