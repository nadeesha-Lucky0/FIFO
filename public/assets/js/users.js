/**
 * User Management Script
 */
document.addEventListener('DOMContentLoaded', () => {
  const createUserForm = document.getElementById('createUserForm');
  const userTableBody = document.getElementById('usersTableBody');
  const openModalBtn = document.getElementById('openCreateUserModal');

  if (openModalBtn) {
    openModalBtn.addEventListener('click', () => {
      resetUserForm();
      App.modal.open('createUserModal');
    });
  }

  // Handle Add User submission
  if (createUserForm) {
    createUserForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearFormErrors();

      const submitBtn = createUserForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Adding User...';

      const formData = new FormData(createUserForm);
      formData.append('action', 'create');

      const res = await App.request('/api/users.php', {
        method: 'POST',
        body: formData
      });

      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;

      if (res.ok && res.data.success) {
        App.toast('New user added successfully!', 'success');
        App.modal.close('createUserModal');
        resetUserForm();
        loadUsers();
      } else {
        if (res.data?.errors) {
          showFormErrors(res.data.errors);
        }
        App.toast(res.data?.message || 'Failed to add user.', 'error');
      }
    });
  }

  // Load Users
  async function loadUsers() {
    const res = await App.request('/api/users.php?action=list');
    if (res.ok && res.data.success) {
      renderUsersTable(res.data.data.users);
    }
  }

  function renderUsersTable(users) {
    if (!userTableBody) return;

    if (users.length === 0) {
      userTableBody.innerHTML = `
        <tr>
          <td colspan="6" style="text-align: center; padding: 24px; color: #94a3b8;">
            No users available.
          </td>
        </tr>
      `;
      return;
    }

    userTableBody.innerHTML = users.map(user => `
      <tr id="user-row-${user.id}">
        <td>
          <div class="user-cell">
            <div class="avatar" style="background-color: ${user.avatar_color || '#2563eb'};">
              ${escapeHtml(user.full_name.charAt(0).toUpperCase())}
            </div>
            <div>
              <div class="user-cell-name">${escapeHtml(user.full_name)}</div>
              <div class="user-cell-email">@${escapeHtml(user.username)} &bull; ${escapeHtml(user.email)}</div>
            </div>
          </div>
        </td>
        <td>
          <span class="badge badge-primary">${escapeHtml(user.role_display_name || user.role_name)}</span>
        </td>
        <td>
          <span>${escapeHtml(user.department || 'General')}</span>
        </td>
        <td>
          <span class="badge badge-success">${capitalize(user.status)}</span>
        </td>
        <td style="color: #64748b; font-size: 0.85rem;">
          ${user.created_at ? user.created_at.substring(0, 10) : 'N/A'}
        </td>
        <td style="text-align: right;">
          <button class="btn btn-danger btn-sm" onclick="deleteUser(${user.id}, '${escapeHtml(user.full_name)}')">
            Delete
          </button>
        </td>
      </tr>
    `).join('');
  }

  // Delete user
  window.deleteUser = async function(id, name) {
    if (!confirm(`Are you sure you want to delete user "${name}"?`)) {
      return;
    }

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);

    const res = await App.request('/api/users.php', {
      method: 'POST',
      body: formData
    });

    if (res.ok && res.data.success) {
      App.toast(res.data.message, 'success');
      loadUsers();
    } else {
      App.toast(res.data?.message || 'Failed to delete user.', 'error');
    }
  };

  function resetUserForm() {
    if (createUserForm) {
      createUserForm.reset();
      clearFormErrors();
    }
  }

  function showFormErrors(errors) {
    for (const [field, message] of Object.entries(errors)) {
      const input = createUserForm.querySelector(`[name="${field}"]`);
      if (input) {
        input.classList.add('is-invalid');
        let errorEl = input.nextElementSibling;
        if (!errorEl || !errorEl.classList.contains('form-error')) {
          errorEl = document.createElement('div');
          errorEl.className = 'form-error';
          input.parentNode.appendChild(errorEl);
        }
        errorEl.textContent = message;
        errorEl.style.display = 'block';
      }
    }
  }

  function clearFormErrors() {
    if (!createUserForm) return;
    createUserForm.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    createUserForm.querySelectorAll('.form-error').forEach(el => el.style.display = 'none');
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, m => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    })[m]);
  }

  function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
  }
});
