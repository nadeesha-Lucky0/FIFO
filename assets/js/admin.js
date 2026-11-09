/**
 * Super Admin Dashboard Script
 * 2-Step Location & Department Management, User Management & Live SL Clock
 */

document.addEventListener('DOMContentLoaded', () => {
  initSriLankaClock();
  initSidebarToggle();
  initTabNavigation();
  initUserPortalNavigation();
  initCumulativeChartTooltips();
  initInventoryFormHandlers();
  initModals();
  initLocationManagement();
  initDepartmentManagement();
  initRoleFilter();
  initUserManagement();
  initProfileManagement();
  initPermissionsMatrix();
  initDefineCodeManager();
  initIssueCodeManagement();
});

/**
 * Global Helper: Instant Google Search in a New Tab
 * Allows Code Admin to research any requested or master item instantly on Google
 */
window.openGoogleSearch = function (queryOrEl, event) {
  if (queryOrEl && typeof queryOrEl.preventDefault === 'function' && !event) {
    event = queryOrEl;
    queryOrEl = event.currentTarget || event.target;
  }
  if (event) {
    if (typeof event.stopPropagation === 'function') event.stopPropagation();
    if (typeof event.preventDefault === 'function') event.preventDefault();
  }

  let query = queryOrEl;
  if (queryOrEl && (queryOrEl instanceof Element || queryOrEl.nodeType === 1)) {
    const el = queryOrEl.closest('button, a, [data-query]') || queryOrEl;
    query = el.getAttribute('data-query')
      || el.dataset?.query
      || el.getAttribute('data-search')
      || el.closest('td')?.querySelector('span')?.textContent
      || el.textContent
      || '';
  }

  if (!query) return;
  const cleanQuery = String(query).replace(/\s+/g, ' ').trim();
  if (!cleanQuery || cleanQuery === '-' || cleanQuery.toLowerCase() === 'loading...') return;
  const searchUrl = 'https://www.google.com/search?q=' + encodeURIComponent(cleanQuery);
  window.open(searchUrl, '_blank', 'noopener,noreferrer');
};

// Delegated click listener for any globe search buttons
document.addEventListener('click', function (e) {
  const globeBtn = e.target.closest('.btn-search-globe, [data-search-globe]');
  if (globeBtn) {
    e.preventDefault();
    e.stopPropagation();
    window.openGoogleSearch(globeBtn, e);
  }
});

/**
 * Live Sri Lankan Date and Time (Hour, Minute, Second)
 */
function initSriLankaClock() {
  const dateDisplay = document.getElementById('slDateDisplay');
  const timeDisplay = document.getElementById('slTimeDisplay');
  if (!dateDisplay || !timeDisplay) return;

  function updateClock() {
    const now = new Date();

    const dateFormatter = new Intl.DateTimeFormat('en-GB', {
      timeZone: 'Asia/Colombo',
      weekday: 'short',
      day: '2-digit',
      month: 'short',
      year: 'numeric'
    });

    const timeFormatter = new Intl.DateTimeFormat('en-US', {
      timeZone: 'Asia/Colombo',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: true
    });

    dateDisplay.textContent = dateFormatter.format(now);
    timeDisplay.textContent = timeFormatter.format(now);
  }

  updateClock();
  setInterval(updateClock, 1000);
}

/**
 * Global Modal Management System
 */
window.openModal = function (modalId) {
  const modal = document.getElementById(modalId);
  if (!modal) return;
  const form = modal.querySelector('form');
  if (form && !modal.dataset.keepValues && modalId !== 'editCodeRequestModal') {
    form.reset();
  }
  if (modalId === 'createRoleModal') {
    document.querySelectorAll('.role-perm-cb-create').forEach(cb => cb.checked = true);
  }
  modal.classList.add('active');
};

window.closeModal = function (modalId) {
  if (modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove('active');
      modal.style.display = '';
      modal.style.opacity = '';
      modal.style.visibility = '';
    }
  } else {
    document.querySelectorAll('.modal-backdrop').forEach(m => {
      m.classList.remove('active');
      m.style.display = '';
      m.style.opacity = '';
      m.style.visibility = '';
    });
  }
};

function initModals() {
  // Global click delegate for opening modals
  document.addEventListener('click', (e) => {
    const openBtn = e.target.closest('[data-open-modal]');
    if (openBtn) {
      e.preventDefault();
      const modalId = openBtn.getAttribute('data-open-modal');
      window.openModal(modalId);
    }
  });

  // Global click delegate for closing modals via close button or Cancel button
  document.addEventListener('click', (e) => {
    const closeBtn = e.target.closest('[data-close-modal]');
    if (closeBtn) {
      e.preventDefault();
      const parentModal = closeBtn.closest('.modal-backdrop');
      if (parentModal && parentModal.id) {
        window.closeModal(parentModal.id);
      } else if (parentModal) {
        parentModal.classList.remove('active');
        parentModal.style.display = 'none';
        parentModal.style.opacity = '';
        parentModal.style.visibility = '';
      } else {
        window.closeModal();
      }
    }
  });
}

/**
 * 1. Left Sidebar Tab Navigation
 */
function initTabNavigation() {
  const tabButtons = document.querySelectorAll('[data-tab-target]');
  const tabPanes = document.querySelectorAll('.admin-tab-pane');
  const topbarTitle = document.getElementById('topbarPageTitle');

  const tabTitles = {
    'tab-define-code': 'Add & Update Master Codes (Code Admin)',
    'tab-code-approvals': 'Code Management & Approvals',
    'tab-master-codes': 'Registered Master Codes Library',
    'tab-departments': 'Locations & Department Structure',
    'tab-issue-codes': 'Issue Categories & Destination Codes',
    'tab-user-roles': 'User Roles & Code Management',
    'tab-users': 'User Accounts & Roles',
    'tab-fifo': 'FIFO Inventory Batches',
    'tab-roles': 'Roles & Permissions Matrix',
    'tab-audit': 'System Security Audit Logs',
    'tab-profile': 'Admin Profile'
  };

  function switchTab(tabId) {
    tabButtons.forEach(btn => {
      if (btn.getAttribute('data-tab-target') === tabId) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    });

    tabPanes.forEach(pane => {
      if (pane.id === tabId) {
        pane.classList.add('active');
      } else {
        pane.classList.remove('active');
      }
    });

    const activeBtn = document.querySelector('[data-tab-target="' + tabId + '"]');
    if (topbarTitle) {
      const labelSpan = activeBtn ? activeBtn.querySelector('.tab-label') : null;
      topbarTitle.textContent = (labelSpan ? labelSpan.textContent.trim() : null) || (activeBtn ? activeBtn.getAttribute('title') : null) || tabTitles[tabId] || 'Portal';
    }

    if (tabId === 'tab-user-issue-entry' && typeof window.initIssueMaterialSelector === 'function') {
      window.initIssueMaterialSelector();
    }

    if (history.replaceState) {
      history.replaceState(null, null, '#' + tabId);
    }
  }

  tabButtons.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const tabId = btn.getAttribute('data-tab-target');
      switchTab(tabId);
    });
  });

  // Handle Hash on Load or fall back to active tab
  const hash = window.location.hash ? window.location.hash.substring(1) : '';
  if (hash && document.getElementById(hash) && document.querySelector('[data-tab-target="' + hash + '"]')) {
    switchTab(hash);
  } else {
    const activeBtn = document.querySelector('.sidebar-tab-btn.active');
    if (activeBtn) {
      switchTab(activeBtn.getAttribute('data-tab-target'));
    }
  }
}

/**
 * 1.1 Sidebar Collapse & Expand Toggle Manager
 */
function initSidebarToggle() {
  window.toggleSidebarCollapse = function () {
    const sidebar = document.getElementById('appSidebar') || document.querySelector('.app-sidebar');
    if (!sidebar) return;
    const isCollapsed = sidebar.classList.toggle('collapsed');
    try {
      localStorage.setItem('admin_sidebar_collapsed', isCollapsed ? '1' : '0');
    } catch (e) {}

    // Dispatch resize event so tables and charts adapt smoothly
    setTimeout(() => {
      window.dispatchEvent(new Event('resize'));
    }, 240);
  };

  // Restore state on load if not already handled
  try {
    if (localStorage.getItem('admin_sidebar_collapsed') === '1') {
      const sidebar = document.getElementById('appSidebar') || document.querySelector('.app-sidebar');
      if (sidebar && !sidebar.classList.contains('collapsed')) {
        sidebar.classList.add('collapsed');
      }
    }
  } catch (e) {}
}

/**
 * 2. Step 1: Location / Power Station Management
 */
function initLocationManagement() {
  const createLocForm = document.getElementById('createLocationForm');
  const editLocForm = document.getElementById('editLocationForm');

  // Create Location
  if (createLocForm) {
    createLocForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitBtn = createLocForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Saving Location...';

      const formData = new FormData(createLocForm);
      formData.append('action', 'create');

      const res = await App.request('/api/locations.php', {
        method: 'POST',
        body: formData
      });

      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;

      if (res.ok && res.data.success) {
        App.toast('Power Station / Location added successfully!', 'success');
        window.closeModal('createLocationModal');
        setTimeout(() => window.location.reload(), 600);
      } else {
        App.toast(res.data?.message || 'Failed to add location.', 'error');
      }
    });
  }

  // Edit Location
  if (editLocForm) {
    editLocForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitBtn = editLocForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Updating Location...';

      const formData = new FormData(editLocForm);
      formData.append('action', 'update');

      const res = await App.request('/api/locations.php', {
        method: 'POST',
        body: formData
      });

      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;

      if (res.ok && res.data.success) {
        App.toast('Location updated successfully!', 'success');
        window.closeModal('editLocationModal');
        setTimeout(() => window.location.reload(), 600);
      } else {
        App.toast(res.data?.message || 'Failed to update location.', 'error');
      }
    });
  }
}

// Global Edit Location Handler
window.editLocation = function (id, name, code, type, status) {
  const modal = document.getElementById('editLocationModal');
  const form = document.getElementById('editLocationForm');
  if (!modal || !form) return;

  form.querySelector('[name="id"]').value = id;
  form.querySelector('[name="name"]').value = name;
  form.querySelector('[name="code"]').value = code;
  if (form.querySelector('[name="type"]')) {
    form.querySelector('[name="type"]').value = type;
  }
  if (form.querySelector('[name="status"]')) {
    form.querySelector('[name="status"]').value = status;
  }

  modal.classList.add('active');
};

// Global Delete Location Handler
window.deleteLocation = async function (id, name) {
  if (!confirm(`Are you sure you want to delete "${name}"? This location and its assigned departments will be affected.`)) {
    return;
  }

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  const formData = new FormData();
  formData.append('action', 'delete');
  formData.append('id', id);
  formData.append('_csrf_token', csrfToken);

  const res = await App.request('/api/locations.php', {
    method: 'POST',
    body: formData
  });

  if (res.ok && res.data.success) {
    App.toast('Location deleted successfully.', 'success');
    const row = document.getElementById(`loc-row-${id}`);
    if (row) row.remove();
    setTimeout(() => window.location.reload(), 500);
  } else {
    App.toast(res.data?.message || 'Could not delete location.', 'error');
  }
};

/**
 * 3. Step 2: Department Management Under Location
 */
function initDepartmentManagement() {
  const createForm = document.getElementById('createDepartmentForm');
  const editForm = document.getElementById('editDepartmentForm');
  const locFilter = document.getElementById('filterDeptLocation');

  // Filter departments by Location
  if (locFilter) {
    locFilter.addEventListener('change', () => {
      const selectedLocId = locFilter.value;
      const rows = document.querySelectorAll('#departmentsTableBody tr');
      rows.forEach(r => {
        const rowLocId = r.getAttribute('data-location-id');
        if (!selectedLocId || rowLocId === selectedLocId) {
          r.style.display = '';
        } else {
          r.style.display = 'none';
        }
      });
    });
  }

  // Auto-generate / define department code when Location is selected (number code only)
  const setupAutoDeptCode = (form) => {
    if (!form) return;
    const locSelect = form.querySelector('select[name="location_id"]');
    const codeInput = form.querySelector('input[name="code"]');
    if (!locSelect || !codeInput) return;

    const updateCode = () => {
      const selectedOption = locSelect.selectedOptions ? locSelect.selectedOptions[0] : null;
      if (!selectedOption || !selectedOption.value) return;

      const locCode = selectedOption.getAttribute('data-code') || '';
      // Extract numbers only (e.g. '833')
      const numberOnly = locCode.replace(/\D/g, '');

      if (numberOnly) {
        codeInput.value = numberOnly;
      } else if (locCode) {
        codeInput.value = locCode;
      }
    };

    locSelect.addEventListener('change', () => {
      updateCode();
    });
  };

  setupAutoDeptCode(createForm);

  // Create Department
  if (createForm) {
    createForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitBtn = createForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Saving Department...';

      const formData = new FormData(createForm);
      formData.append('action', 'create');

      const res = await App.request('/api/departments.php', {
        method: 'POST',
        body: formData
      });

      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;

      if (res.ok && res.data.success) {
        App.toast('Department added successfully!', 'success');
        window.closeModal('createDepartmentModal');
        setTimeout(() => window.location.reload(), 600);
      } else {
        App.toast(res.data?.message || 'Failed to add department.', 'error');
      }
    });
  }

  // Edit Department
  if (editForm) {
    editForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitBtn = editForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Updating...';

      const formData = new FormData(editForm);
      formData.append('action', 'update');

      const res = await App.request('/api/departments.php', {
        method: 'POST',
        body: formData
      });

      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;

      if (res.ok && res.data.success) {
        App.toast('Department updated successfully!', 'success');
        window.closeModal('editDepartmentModal');
        setTimeout(() => window.location.reload(), 600);
      } else {
        App.toast(res.data?.message || 'Failed to update department.', 'error');
      }
    });
  }
}

// Global function to open Edit Department Modal
window.editDepartment = function (id, locationId, name, code, status) {
  const modal = document.getElementById('editDepartmentModal');
  const form = document.getElementById('editDepartmentForm');
  if (!modal || !form) return;

  form.querySelector('[name="id"]').value = id;
  if (form.querySelector('[name="location_id"]')) {
    form.querySelector('[name="location_id"]').value = locationId;
  }
  form.querySelector('[name="name"]').value = name;
  form.querySelector('[name="code"]').value = code;
  if (form.querySelector('[name="status"]')) {
    form.querySelector('[name="status"]').value = status;
  }

  modal.classList.add('active');
};

// Global function to delete Department
window.deleteDepartment = async function (id, name) {
  if (!confirm(`Are you sure you want to delete the department "${name}"?`)) {
    return;
  }

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  const formData = new FormData();
  formData.append('action', 'delete');
  formData.append('id', id);
  formData.append('_csrf_token', csrfToken);

  const res = await App.request('/api/departments.php', {
    method: 'POST',
    body: formData
  });

  if (res.ok && res.data.success) {
    App.toast('Department deleted.', 'success');
    const row = document.getElementById(`dept-row-${id}`);
    if (row) row.remove();
    setTimeout(() => window.location.reload(), 500);
  } else {
    App.toast(res.data?.message || 'Could not delete department.', 'error');
  }
};

/**
 * 4. User Management
 */
function initUserManagement() {
  const createForm = document.getElementById('createUserForm');
  const editForm = document.getElementById('editUserForm');
  const createRoleForm = document.getElementById('createRoleForm');
  const roleDisplayInput = document.getElementById('roleDisplayNameInput');

  const appData = window.APP_DATA || { locations: [], departments: [], roles: [] };

  // Helper to populate department select based on location
  function updateDeptOptions(deptSelectEl, locationId, useIdAsValue = false, includeAllOpt = false, selectedVal = null) {
    if (!deptSelectEl) return;
    deptSelectEl.innerHTML = '';

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.disabled = true;
    placeholder.selected = !selectedVal;
    placeholder.textContent = '-- Select Department --';
    deptSelectEl.appendChild(placeholder);

    if (!locationId || locationId == 0) {
      return;
    }

    const filtered = appData.departments.filter(d => d.location_id == locationId);

    if (filtered.length === 0) {
      const emptyOpt = document.createElement('option');
      emptyOpt.value = '';
      emptyOpt.disabled = true;
      emptyOpt.textContent = 'No departments in this station';
      deptSelectEl.appendChild(emptyOpt);
    } else {
      filtered.forEach(d => {
        const opt = document.createElement('option');
        opt.value = useIdAsValue ? d.id : d.name;
        opt.dataset.deptId = d.id;
        opt.dataset.deptName = d.name;
        opt.textContent = `${d.name} (${d.code || 'Dept'})`;
        if (selectedVal && (selectedVal == d.id || selectedVal == d.name)) {
          opt.selected = true;
        }
        deptSelectEl.appendChild(opt);
      });
    }
  }

  // Helper to populate role select with all defined roles (universal across power stations)
  function updateRoleOptions(roleSelectEl, locationId = null, selectedRoleId = null) {
    if (!roleSelectEl) return;
    roleSelectEl.innerHTML = '';

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.disabled = true;
    placeholder.selected = !selectedRoleId;
    placeholder.textContent = '-- Select Role --';
    roleSelectEl.appendChild(placeholder);

    const rolesList = appData.roles || [];
    const filtered = rolesList.filter(r => {
      const isSuperAdmin = (r.name === 'super_admin' || r.id == 1);
      if (isSuperAdmin && r.id != selectedRoleId) return false;
      return true;
    });

    if (filtered.length === 0) {
      const emptyOpt = document.createElement('option');
      emptyOpt.value = '';
      emptyOpt.disabled = true;
      emptyOpt.selected = true;
      emptyOpt.textContent = 'No defined roles available';
      roleSelectEl.appendChild(emptyOpt);
    } else {
      filtered.forEach(r => {
        const opt = document.createElement('option');
        opt.value = r.id;
        opt.textContent = r.display_name;
        if (selectedRoleId && selectedRoleId == r.id) {
          opt.selected = true;
        }
        roleSelectEl.appendChild(opt);
      });
    }
  }

  // Create User Cascading (Location -> Role)
  const createUserLocSelect = document.getElementById('createUserLocation');
  const createUserRoleSelect = document.getElementById('createUserRole');
  if (createUserLocSelect && createUserRoleSelect) {
    createUserLocSelect.addEventListener('change', () => {
      const currentRoleVal = createUserRoleSelect.value;
      updateRoleOptions(createUserRoleSelect, createUserLocSelect.value, currentRoleVal);
    });
  }

  // Edit User Cascading (Location -> Role)
  const editUserLocSelect = document.getElementById('editUserLocation');
  const editUserRoleSelect = document.getElementById('editUserRoleId');
  if (editUserLocSelect && editUserRoleSelect) {
    editUserLocSelect.addEventListener('change', () => {
      const currentRoleVal = editUserRoleSelect.value;
      updateRoleOptions(editUserRoleSelect, editUserLocSelect.value, currentRoleVal);
    });
  }



  // Create User Submission
  if (createForm) {
    createForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitBtn = createForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Creating...';

      const formData = new FormData(createForm);
      formData.append('action', 'create');

      const res = await App.request('/api/users.php', {
        method: 'POST',
        body: formData
      });

      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;

      if (res.ok && res.data.success) {
        App.toast('User created successfully!', 'success');
        window.closeModal('createUserModal');
        setTimeout(() => window.location.reload(), 600);
      } else {
        const errMsg = (res.data?.errors && Object.keys(res.data.errors).length > 0)
          ? Object.values(res.data.errors).join(' ')
          : (res.data?.message || 'Failed to create user.');
        App.toast(errMsg, 'error');
      }
    });
  }

  // Edit User Submission
  if (editForm) {
    editForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitBtn = editForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Saving Changes...';

      const formData = new FormData(editForm);
      formData.append('action', 'update');

      const res = await App.request('/api/users.php', {
        method: 'POST',
        body: formData
      });

      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;

      if (res.ok && res.data.success) {
        App.toast('User account updated successfully!', 'success');
        window.closeModal('editUserModal');
        setTimeout(() => window.location.reload(), 600);
      } else {
        const errMsg = (res.data?.errors && Object.keys(res.data.errors).length > 0)
          ? Object.values(res.data.errors).join(' ')
          : (res.data?.message || 'Failed to update user.');
        App.toast(errMsg, 'error');
      }
    });
  }

  // Role Permission Select All / Deselect All Handlers
  const btnRolePermSelectAll = document.getElementById('btnRolePermSelectAll');
  const btnRolePermDeselectAll = document.getElementById('btnRolePermDeselectAll');
  if (btnRolePermSelectAll) {
    btnRolePermSelectAll.addEventListener('click', () => {
      document.querySelectorAll('.role-perm-cb-create').forEach(cb => cb.checked = true);
    });
  }
  if (btnRolePermDeselectAll) {
    btnRolePermDeselectAll.addEventListener('click', () => {
      document.querySelectorAll('.role-perm-cb-create').forEach(cb => cb.checked = false);
    });
  }

  const btnEditRolePermSelectAll = document.getElementById('btnEditRolePermSelectAll');
  const btnEditRolePermDeselectAll = document.getElementById('btnEditRolePermDeselectAll');
  if (btnEditRolePermSelectAll) {
    btnEditRolePermSelectAll.addEventListener('click', () => {
      document.querySelectorAll('.role-perm-cb-edit').forEach(cb => cb.checked = true);
    });
  }
  if (btnEditRolePermDeselectAll) {
    btnEditRolePermDeselectAll.addEventListener('click', () => {
      document.querySelectorAll('.role-perm-cb-edit').forEach(cb => cb.checked = false);
    });
  }

  // Define Role Submission
  if (createRoleForm) {
    createRoleForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitBtn = createRoleForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Defining Role...';

      const formData = new FormData(createRoleForm);
      formData.append('action', 'create');

      const res = await App.request('/api/roles.php', {
        method: 'POST',
        body: formData
      });

      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;

      if (res.ok && res.data.success) {
        App.toast('New role and tab permissions defined successfully!', 'success');
        window.closeModal('createRoleModal');
        setTimeout(() => window.location.reload(), 600);
      } else {
        App.toast(res.data?.message || 'Failed to define role.', 'error');
      }
    });
  }

  // Edit Role Submission
  const editRoleForm = document.getElementById('editRoleForm');
  if (editRoleForm) {
    editRoleForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitBtn = editRoleForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Saving Changes...';

      const formData = new FormData(editRoleForm);
      formData.append('action', 'update');
      formData.append('_save_permissions', '1');

      const res = await App.request('/api/roles.php', {
        method: 'POST',
        body: formData
      });

      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;

      if (res.ok && res.data.success) {
        App.toast('Role and tab permissions updated successfully!', 'success');
        window.closeModal('editRoleModal');
        setTimeout(() => window.location.reload(), 600);
      } else {
        App.toast(res.data?.message || 'Failed to update role.', 'error');
      }
    });
  }
}

// Global function to open Edit User Modal with pre-populated data
window.openEditUserModal = function (user) {
  if (!user) return;
  const appData = window.APP_DATA || { locations: [], departments: [], roles: [] };

  window.openModal('editUserModal');

  const idInput = document.getElementById('editUserId');
  const nameInput = document.getElementById('editUserFullName');
  const userInput = document.getElementById('editUserUsername');
  const locSelect = document.getElementById('editUserLocation');
  const roleSelect = document.getElementById('editUserRoleId');
  const phoneInput = document.getElementById('editUserPhone');
  const statusSelect = document.getElementById('editUserStatus');

  if (idInput) idInput.value = user.id || '';
  if (nameInput) nameInput.value = user.full_name || '';
  if (userInput) userInput.value = user.username || user.full_name || '';
  if (phoneInput) phoneInput.value = user.phone || '';
  if (statusSelect) statusSelect.value = user.status || 'active';

  const pwdInput = document.querySelector('#editUserForm input[name="password"]');
  if (pwdInput) pwdInput.value = '';

  // Find location for user
  let locationId = user.location_id;
  if (!locationId && user.role_id) {
    const r = appData.roles.find(role => role.id == user.role_id);
    if (r && r.location_id) locationId = r.location_id;
  }
  if (!locationId && appData.locations[0]) {
    locationId = appData.locations[0].id;
  }

  if (locSelect && locationId) {
    locSelect.value = locationId;
  }

  // Populate all defined roles
  if (roleSelect) {
    updateRoleOptions(roleSelect, locationId, user.role_id);
  }
};

// Global function to open Edit Role Modal with populated data and tab permissions
window.openEditRoleModal = function (role) {
  if (!role) return;
  window.openModal('editRoleModal');

  const idInput = document.getElementById('editRoleId');
  const displayInput = document.getElementById('editRoleDisplayName');
  const codeInput = document.getElementById('editRoleCode');

  if (idInput) idInput.value = role.id || '';
  if (displayInput) displayInput.value = role.display_name || '';
  if (codeInput) codeInput.value = role.code || '';

  // Populate tab permissions for this role from APP_DATA.permissionsMatrix
  const matrix = window.APP_DATA?.permissionsMatrix || {};
  document.querySelectorAll('.role-perm-cb-edit').forEach(cb => {
    const permKey = cb.getAttribute('data-perm-key') || cb.name.replace(/^permissions\[|\]$/g, '');
    if (matrix[permKey] && typeof matrix[permKey][role.id] !== 'undefined') {
      cb.checked = (matrix[permKey][role.id] === 1);
    } else {
      cb.checked = true;
    }
  });
};

window.editRole = function (id, displayName, name, description, code) {
  window.openEditRoleModal({ id, display_name: displayName, name, description, code });
};

window.deleteRole = async function (id, roleName) {
  if (!confirm(`Are you sure you want to delete the user role "${roleName}"?`)) {
    return;
  }

  const formData = new FormData();
  formData.append('action', 'delete');
  formData.append('id', id);
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  if (csrf) formData.append('_csrf_token', csrf);

  const res = await App.request('/api/roles.php', {
    method: 'POST',
    body: formData
  });

  if (res.ok && res.data.success) {
    App.toast('Role deleted successfully!', 'success');
    const row = document.getElementById('role-row-' + id);
    if (row) row.remove();
  } else {
    App.toast(res.data?.message || 'Failed to delete role.', 'error');
  }
};

/**
 * Filter Defined Roles table by Power Station / Location only
 */
function initRoleFilter() {
  const locFilter = document.getElementById('filterRoleLocation');
  if (!locFilter) return;

  locFilter.addEventListener('change', () => {
    const locVal = locFilter.value;
    const rows = document.querySelectorAll('#rolesTableBody tr');

    rows.forEach(r => {
      if (r.id === 'emptyRolesRow') return;
      const rLoc = r.getAttribute('data-location-id') || '';

      if (!locVal) {
        // Show all
        r.style.display = '';
      } else if (locVal === 'global') {
        // Show global roles
        r.style.display = (rLoc === '' || rLoc === '0') ? '' : 'none';
      } else {
        // Match specific location
        r.style.display = (rLoc === locVal) ? '' : 'none';
      }
    });
  });
}

// Global delete role function
window.deleteRole = async function (roleId, roleName) {
  if (!confirm(`Are you sure you want to delete role "${roleName}"?`)) {
    return;
  }

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  const formData = new FormData();
  formData.append('action', 'delete');
  formData.append('id', roleId);
  formData.append('_csrf_token', csrfToken);

  const res = await App.request('/api/roles.php', {
    method: 'POST',
    body: formData
  });

  if (res.ok && res.data.success) {
    App.toast('Role deleted.', 'success');
    const row = document.getElementById(`role-row-${roleId}`);
    if (row) row.remove();
    setTimeout(() => window.location.reload(), 500);
  } else {
    App.toast(res.data?.message || 'Could not delete role.', 'error');
  }
};

// Global delete user function
window.deleteUser = async function (userId, userName) {
  if (!confirm(`Are you sure you want to delete user "${userName}"?`)) {
    return;
  }

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  const formData = new FormData();
  formData.append('action', 'delete');
  formData.append('id', userId);
  formData.append('_csrf_token', csrfToken);

  const res = await App.request('/api/users.php', {
    method: 'POST',
    body: formData
  });

  if (res.ok && res.data.success) {
    App.toast('User deleted.', 'success');
    const row = document.getElementById(`user-row-${userId}`);
    if (row) row.remove();
    setTimeout(() => window.location.reload(), 500);
  } else {
    App.toast(res.data?.message || 'Could not delete user.', 'error');
  }
};

/**
 * 5. Profile Management
 */
function initProfileManagement() {
  const profileForm = document.getElementById('superAdminProfileForm');
  const passwordForm = document.getElementById('superAdminPasswordForm');

  if (profileForm) {
    profileForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitBtn = profileForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Saving...';

      const formData = new FormData(profileForm);
      formData.append('action', 'update_profile');

      const res = await App.request('/api/users.php', {
        method: 'POST',
        body: formData
      });

      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;

      if (res.ok && res.data.success) {
        App.toast('Profile updated successfully!', 'success');
        setTimeout(() => window.location.reload(), 600);
      } else {
        App.toast(res.data?.message || 'Failed to update profile.', 'error');
      }
    });
  }

  if (passwordForm) {
    passwordForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitBtn = passwordForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Updating Password...';

      const formData = new FormData(passwordForm);
      formData.append('action', 'update_password');

      const res = await App.request('/api/users.php', {
        method: 'POST',
        body: formData
      });

      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;

      if (res.ok && res.data.success) {
        App.toast('Password updated successfully!', 'success');
        passwordForm.reset();
      } else {
        App.toast(res.data?.message || 'Failed to update password.', 'error');
      }
    });
  }
}

/**
 * 6. Role & User Permissions Matrix Management (Interactive ON/OFF Switches)
 */
function initPermissionsMatrix() {
  // Delegate toggles for both role and user permissions
  document.querySelectorAll('.matrix-perm-toggle').forEach(toggle => {
    toggle.addEventListener('change', async function () {
      const roleId = this.getAttribute('data-role-id');
      const userId = this.getAttribute('data-user-id');
      const permKey = this.getAttribute('data-permission-key');
      const targetName = this.getAttribute('data-target-name') || this.getAttribute('data-role-name') || 'Account';
      const permLabel = this.getAttribute('data-perm-label') || 'Permission';
      const isGranted = this.checked ? '1' : '0';
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

      const formData = new FormData();
      formData.append('action', 'toggle');
      if (userId) {
        formData.append('user_id', userId);
      } else if (roleId) {
        formData.append('role_id', roleId);
      }
      formData.append('permission_key', permKey);
      formData.append('is_granted', isGranted);
      formData.append('_csrf_token', csrfToken);

      // Disable toggle while updating to prevent race conditions
      this.disabled = true;

      const res = await App.request('/api/permissions.php', {
        method: 'POST',
        body: formData
      });

      this.disabled = false;

      if (res.ok && res.data.success) {
        const stateText = isGranted === '1' ? 'ENABLED' : 'DISABLED';
        App.toast(`${targetName}: ${permLabel} is now ${stateText}.`, 'success', 2500);
      } else {
        // Revert toggle state on error
        this.checked = !this.checked;
        App.toast(res.data?.message || 'Failed to update permission setting.', 'error');
      }
    });
  });

  // Matrix View Mode Switcher (Roles vs Users)
  document.querySelectorAll('[data-matrix-mode]').forEach(btn => {
    btn.addEventListener('click', function () {
      const targetMode = this.getAttribute('data-matrix-mode');
      document.querySelectorAll('[data-matrix-mode]').forEach(b => b.classList.remove('active'));
      this.classList.add('active');

      const rolesWrap = document.getElementById('rolesMatrixWrap');
      const usersWrap = document.getElementById('usersMatrixWrap');
      if (rolesWrap && usersWrap) {
        if (targetMode === 'users') {
          rolesWrap.style.display = 'none';
          usersWrap.style.display = 'block';
        } else {
          usersWrap.style.display = 'none';
          rolesWrap.style.display = 'block';
        }
      }
    });
  });

  // Matrix Search Filter
  const matrixSearch = document.getElementById('matrixSearchInput');
  if (matrixSearch) {
    matrixSearch.addEventListener('input', function () {
      const q = this.value.trim().toLowerCase();
      document.querySelectorAll('.matrix-table tbody tr').forEach(row => {
        if (row.classList.contains('matrix-category-row')) {
          row.style.display = '';
          return;
        }
        const text = row.textContent.toLowerCase();
        row.style.display = (!q || text.includes(q)) ? '' : 'none';
      });
    });
  }

  // Horizontal Scroll Buttons
  const btnLeft = document.getElementById('btnScrollMatrixLeft');
  const btnRight = document.getElementById('btnScrollMatrixRight');
  const matrixWrap = document.getElementById('usersMatrixWrap');

  if (btnLeft && matrixWrap) {
    btnLeft.addEventListener('click', () => {
      matrixWrap.scrollBy({ left: -320, behavior: 'smooth' });
    });
  }

  if (btnRight && matrixWrap) {
    btnRight.addEventListener('click', () => {
      matrixWrap.scrollBy({ left: 320, behavior: 'smooth' });
    });
  }

  // Enable horizontal wheel scroll and drag-to-scroll anywhere in the middle of the table
  if (matrixWrap) {
    let isDown = false;
    let startX;
    let scrollLeft;

    matrixWrap.addEventListener('mousedown', (e) => {
      // Ignore clicks on switches or clickable inputs
      if (e.target.closest('.matrix-switch') || e.target.tagName === 'INPUT' || e.target.tagName === 'BUTTON') return;
      isDown = true;
      matrixWrap.style.cursor = 'grabbing';
      startX = e.pageX - matrixWrap.offsetLeft;
      scrollLeft = matrixWrap.scrollLeft;
    });

    document.addEventListener('mouseup', () => {
      if (isDown) {
        isDown = false;
        matrixWrap.style.cursor = '';
      }
    });

    matrixWrap.addEventListener('mousemove', (e) => {
      if (!isDown) return;
      e.preventDefault();
      const x = e.pageX - matrixWrap.offsetLeft;
      const walk = (x - startX) * 1.5;
      matrixWrap.scrollLeft = scrollLeft - walk;
    });
  }
}

/**
 * 7. Non-Admin User Portal Horizontal Navigation (Image 1 Specification)
 */
function initUserPortalNavigation() {
  const tabTriggers = document.querySelectorAll('.user-nav-tab-trigger');
  const userPanes = document.querySelectorAll('.user-tab-pane');
  const navItems = document.querySelectorAll('.user-nav-item');

  if (!tabTriggers.length || !userPanes.length) return;

  const tabParentMap = {
    'tab-user-home': 'tab-user-home',
    'tab-user-fifo': 'tab-user-fifo',
    'tab-user-receipts': 'tab-user-fifo',
    'tab-user-receipts-entry': 'tab-user-fifo',
    'tab-user-receipts-validation': 'tab-user-fifo',
    'tab-user-receipts-approval': 'tab-user-fifo',
    'tab-user-receipts-edit': 'tab-user-fifo',
    'tab-user-receipts-posted-cancel': 'tab-user-fifo',
    'tab-user-receipts-unposted-cancel': 'tab-user-fifo',
    'tab-user-issue-entry': 'tab-user-fifo',
    'tab-user-issue-edit': 'tab-user-fifo',
    'tab-user-issue-validation': 'tab-user-fifo',
    'tab-user-issue-approval': 'tab-user-fifo',
    'tab-user-issue-posted-cancel': 'tab-user-fifo',
    'tab-user-issue-unposted-cancel': 'tab-user-fifo',
    'tab-user-posting': 'tab-user-fifo',
    'tab-user-physical-verification': 'tab-user-fifo',
    'tab-user-pv-freeze': 'tab-user-fifo',
    'tab-user-pv-generate-sheet': 'tab-user-fifo',
    'tab-user-pv-entry': 'tab-user-fifo',
    'tab-user-pv-edit': 'tab-user-fifo',
    'tab-user-pv-validation': 'tab-user-fifo',
    'tab-user-pv-approval': 'tab-user-fifo',
    'tab-user-pv-report-avg-consumption': 'tab-user-fifo',
    'tab-user-pv-report-final': 'tab-user-fifo',
    'tab-user-pv-report-other': 'tab-user-fifo',
    'tab-user-pv-report-sheet-summary': 'tab-user-fifo',
    'tab-user-pv-report-av7-summary': 'tab-user-fifo',
    'tab-user-pv-posting': 'tab-user-fifo',
    'tab-user-pv-gl-posting': 'tab-user-fifo',
    'tab-user-pv-unfreeze': 'tab-user-fifo',
    'tab-user-cashbook': 'tab-user-cashbook',
    'tab-user-payslip-cancellation': 'tab-user-cashbook',
    'tab-user-reports': 'tab-user-reports',
    'tab-user-report-qoh-batch': 'tab-user-reports',
    'tab-user-report-qoh-material': 'tab-user-reports',
    'tab-user-report-monthly-transfers': 'tab-user-reports',
    'tab-user-report-avg-diesel': 'tab-user-reports',
    'tab-user-report-cpc-procurement': 'tab-user-reports',
    'tab-user-report-inventory-inquiry': 'tab-user-reports',
    'tab-user-report-job-issue': 'tab-user-reports',
    'tab-user-report-monthly-tx': 'tab-user-reports',
    'tab-user-report-grn-issue-summary': 'tab-user-reports',
    'tab-user-report-material-flow': 'tab-user-reports',
    'tab-user-tasks': 'tab-user-tasks',
    'tab-user-task-cost-adjustment': 'tab-user-tasks',
    'tab-user-task-qty-correction': 'tab-user-tasks',
    'tab-user-profile': 'tab-user-profile',
    'tab-user-code': 'tab-user-code',
    'tab-user-code-item-search': 'tab-user-code',
    'tab-user-code-roles': 'tab-user-code'
  };

  function switchUserTab(targetPaneId) {
    if (targetPaneId === 'tab-user-receipts') targetPaneId = 'tab-user-receipts-entry';
    if (targetPaneId === 'tab-user-physical-verification') targetPaneId = 'tab-user-pv-entry';
    if (targetPaneId === 'tab-user-code-item-search' && window.itemSearchController?.['user']) {
      window.itemSearchController['user'].init();
    }
    userPanes.forEach(pane => {
      if (pane.id === targetPaneId) {
        pane.classList.add('active');
      } else {
        pane.classList.remove('active');
      }
    });

    const activeParentId = tabParentMap[targetPaneId] || targetPaneId;

    // Update horizontal nav item active indicators
    navItems.forEach(item => {
      const parentTarget = item.getAttribute('data-tab-parent');
      if (parentTarget === activeParentId) {
        item.classList.add('active');
        const mainLink = item.querySelector('.user-nav-link');
        if (mainLink) mainLink.classList.add('active');
      } else {
        item.classList.remove('active');
        const mainLink = item.querySelector('.user-nav-link');
        if (mainLink) mainLink.classList.remove('active');
      }
    });

    // Scroll to top of content smoothly
    window.scrollTo({ top: 0, behavior: 'smooth' });

    // Close any open navigation menus
    navItems.forEach(item => item.classList.remove('open'));
    document.querySelectorAll('.user-dropdown-submenu').forEach(sub => sub.classList.remove('open'));

    if (history.replaceState) {
      history.replaceState(null, null, '#' + targetPaneId);
    }

    // Auto-populate edit forms when switching to Unposted Edit tabs
    if (targetPaneId === 'tab-user-receipts-edit') {
      const rSel = document.getElementById('receiptsEditDocNo');
      if (rSel) {
        const docVal = rSel.value || 'GRN-2026-0091';
        rSel.value = docVal;
        if (typeof window.updateReceiptCurrentDocDetails === 'function') {
          window.updateReceiptCurrentDocDetails(docVal, 'receipts');
        }
      }
    } else if (targetPaneId === 'tab-user-issue-edit') {
      const iSel = document.getElementById('issueEditDocNo');
      if (iSel) {
        const docVal = iSel.value || 'GIN-2026-0041';
        iSel.value = docVal;
        if (typeof window.updateReceiptCurrentDocDetails === 'function') {
          window.updateReceiptCurrentDocDetails(docVal, 'issue');
        }
      }
    } else if (targetPaneId === 'tab-user-code-roles') {
      if (typeof window.loadStationInventory === 'function') {
        window.loadStationInventory();
      }
      if (typeof window.loadStationRequests === 'function') {
        window.loadStationRequests();
      }
      if (typeof window.updateSimpleCode === 'function') {
        window.updateSimpleCode();
      }
    }
  }

  // Expose globally so inline onclick handlers and external calls can invoke switchUserTab
  window.switchUserTab = switchUserTab;

  // Toggle dropdown menus on click without loading any pages
  const dropdownToggles = document.querySelectorAll('.user-dropdown-toggle');
  dropdownToggles.forEach(toggleBtn => {
    toggleBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const parentItem = toggleBtn.closest('.user-nav-item');
      if (!parentItem) return;
      const isOpen = parentItem.classList.contains('open');
      navItems.forEach(item => item.classList.remove('open'));
      document.querySelectorAll('.user-dropdown-submenu').forEach(sub => sub.classList.remove('open'));
      if (!isOpen) {
        parentItem.classList.add('open');
      }
    });
  });

  // Toggle flyout submenus on click
  const submenuTriggers = document.querySelectorAll('.user-submenu-trigger');
  submenuTriggers.forEach(subBtn => {
    subBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const parentSubmenu = subBtn.closest('.user-dropdown-submenu');
      if (!parentSubmenu) return;
      const isSubOpen = parentSubmenu.classList.contains('open');
      const siblings = parentSubmenu.parentElement ? parentSubmenu.parentElement.querySelectorAll(':scope > .user-dropdown-submenu') : [];
      siblings.forEach(s => s.classList.remove('open'));
      if (!isSubOpen) {
        parentSubmenu.classList.add('open');
      }
    });

    // When hovering over another submenu trigger, close other opened sibling submenus to prevent overlap
    subBtn.addEventListener('mouseenter', () => {
      const parentSubmenu = subBtn.closest('.user-dropdown-submenu');
      if (parentSubmenu && parentSubmenu.parentElement) {
        const siblings = parentSubmenu.parentElement.querySelectorAll(':scope > .user-dropdown-submenu');
        siblings.forEach(s => {
          if (s !== parentSubmenu) s.classList.remove('open');
        });
      }
    });
  });

  // Close open dropdowns when clicking outside
  document.addEventListener('click', (e) => {
    const isNavbarClick = e.target.closest('.user-navbar');
    if (!isNavbarClick) {
      navItems.forEach(item => item.classList.remove('open'));
      document.querySelectorAll('.user-dropdown-submenu').forEach(sub => sub.classList.remove('open'));
    }
  });

  // Robust Global Delegation for All Tab Triggers
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('.user-nav-tab-trigger');
    if (trigger) {
      e.preventDefault();
      const targetPane = trigger.getAttribute('data-user-tab');
      if (targetPane) {
        switchUserTab(targetPane);
      }
    }
  });

  // Direct element listener binding for all present triggers
  tabTriggers.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const targetPane = btn.getAttribute('data-user-tab');
      if (targetPane) {
        switchUserTab(targetPane);
      }
    });
  });

  // Handle Hash on page load
  const currentHash = window.location.hash ? window.location.hash.substring(1) : '';
  if (currentHash && document.getElementById(currentHash) && document.getElementById(currentHash).classList.contains('user-tab-pane')) {
    switchUserTab(currentHash);
  }
}

/**
 * 8. Cumulative Transaction Values Chart Interactive Tooltips (Image 2 Specification)
 */
function initCumulativeChartTooltips() {
  const chartWrapper = document.getElementById('svgChartWrapper');
  const tooltip = document.getElementById('chartTooltip');
  if (!chartWrapper || !tooltip) return;

  const barRects = chartWrapper.querySelectorAll('.chart-bar-rect');

  barRects.forEach(bar => {
    bar.addEventListener('mouseenter', (e) => {
      const category = bar.getAttribute('data-category') || 'Transaction';
      const month = bar.getAttribute('data-month') || '';
      const monthName = bar.getAttribute('data-month-name') || '';
      const val = bar.getAttribute('data-val') || '0.00';

      tooltip.innerHTML = `
        <div class="chart-tooltip-title">${category} &bull; ${month} (${monthName})</div>
        <div class="chart-tooltip-val">Rs. ${val}</div>
      `;
      tooltip.style.display = 'block';
      bar.style.opacity = '0.85';
      bar.style.filter = 'brightness(1.15)';
    });

    bar.addEventListener('mousemove', (e) => {
      const wrapperRect = chartWrapper.getBoundingClientRect();
      const x = e.clientX - wrapperRect.left;
      const y = e.clientY - wrapperRect.top;
      tooltip.style.left = `${x}px`;
      tooltip.style.top = `${y - 10}px`;
    });

    bar.addEventListener('mouseleave', () => {
      tooltip.style.display = 'none';
      bar.style.opacity = '1';
      bar.style.filter = 'none';
    });
  });
}

/**
 * 9. Inventory Form Handlers (Receipts / Issues)
 */
function initInventoryFormHandlers() {
  const receiptForm = document.getElementById('stockReceiptForm');
  const issueForm = document.getElementById('stockIssueEntryForm');

  if (receiptForm) {
    receiptForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitBtn = receiptForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Saving Receipt...';

      const formData = new FormData(receiptForm);
      formData.append('action', 'create_receipt');

      const res = await App.request('/api/inventory.php', {
        method: 'POST',
        body: formData
      });

      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;

      if (res.ok && res.data.success) {
        App.toast(res.data.message || 'GRN Receipt created successfully!', 'success');
        receiptForm.reset();
        setTimeout(() => window.location.reload(), 700);
      } else {
        App.toast(res.data?.message || 'Failed to create GRN receipt.', 'error');
      }
    });
  }

  if (issueForm) {
    issueForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitBtn = issueForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Submitting Issue...';

      const formData = new FormData(issueForm);
      formData.append('action', 'create_issue');

      const res = await App.request('/api/inventory.php', {
        method: 'POST',
        body: formData
      });

      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;

      if (res.ok && res.data.success) {
        App.toast(res.data.message || 'Issue voucher submitted successfully!', 'success');
        issueForm.reset();
        setTimeout(() => window.location.reload(), 700);
      } else {
        App.toast(res.data?.message || 'Failed to submit stock issue.', 'error');
      }
    });
  }

  const payslipForm = document.getElementById('payslipCancellationForm');
  if (payslipForm) {
    payslipForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitBtn = payslipForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Submitting Request...';

      const formData = new FormData(payslipForm);
      formData.append('action', 'create_payslip_cancellation');

      const res = await App.request('/api/cashbook.php', {
        method: 'POST',
        body: formData
      });

      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;

      if (res.ok && res.data.success) {
        App.toast(res.data.message || 'Payslip cancellation submitted successfully!', 'success');
        payslipForm.reset();
        setTimeout(() => window.location.reload(), 700);
      } else {
        App.toast(res.data?.message || 'Failed to submit payslip cancellation.', 'error');
      }
    });
  }

  const costAdjForm = document.getElementById('costAdjustmentForm');
  if (costAdjForm) {
    costAdjForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitBtn = costAdjForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Applying Adjustment...';

      const formData = new FormData(costAdjForm);
      formData.append('action', 'create_cost_adjustment');

      const res = await App.request('/api/tasks.php', {
        method: 'POST',
        body: formData
      });

      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;

      if (res.ok && res.data.success) {
        App.toast(res.data.message || 'Cost adjustment applied successfully!', 'success');
        costAdjForm.reset();
        setTimeout(() => window.location.reload(), 700);
      } else {
        App.toast(res.data?.message || 'Failed to apply cost adjustment.', 'error');
      }
    });
  }

  const legacyCostForm = document.getElementById('legacyCostCenterCorrectionForm');
  if (legacyCostForm) {
    legacyCostForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitBtn = legacyCostForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Updating Quantities...';

      const formData = new FormData(legacyCostForm);

      const res = await App.request('/api/tasks.php', {
        method: 'POST',
        body: formData
      });

      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;

      if (res.ok && res.data.success) {
        App.toast(res.data.message || 'Quantities updated successfully!', 'success');
        legacyCostForm.reset();
      } else {
        App.toast(res.data?.message || 'Authentication or update failed.', 'error');
      }
    });
  }

  const btnUpdateVerif = document.getElementById('btnLegacyUpdateVerification');
  const verifForm = document.getElementById('legacyVerificationTablesForm');
  if (btnUpdateVerif && verifForm) {
    btnUpdateVerif.addEventListener('click', async () => {
      const originalText = btnUpdateVerif.innerHTML;
      btnUpdateVerif.disabled = true;
      btnUpdateVerif.innerHTML = 'Updating Tables...';

      const formData = new FormData(verifForm);
      formData.append('action', 'update_verification_tables');

      const res = await App.request('/api/tasks.php', {
        method: 'POST',
        body: formData
      });

      btnUpdateVerif.disabled = false;
      btnUpdateVerif.innerHTML = originalText;

      if (res.ok && res.data.success) {
        App.toast(res.data.message || 'Verification tables updated successfully!', 'success');
      } else {
        App.toast(res.data?.message || 'Failed to update verification tables.', 'error');
      }
    });
  }

  const btnCreateBatch = document.getElementById('btnLegacyCreateBatch');
  if (btnCreateBatch && verifForm) {
    btnCreateBatch.addEventListener('click', async () => {
      const originalText = btnCreateBatch.innerHTML;
      btnCreateBatch.disabled = true;
      btnCreateBatch.innerHTML = 'Creating Batch...';

      const formData = new FormData(verifForm);
      formData.append('action', 'create_verification_batch');

      const res = await App.request('/api/tasks.php', {
        method: 'POST',
        body: formData
      });

      btnCreateBatch.disabled = false;
      btnCreateBatch.innerHTML = originalText;

      if (res.ok && res.data.success) {
        App.toast(res.data.message || 'Verification batch created successfully!', 'success');
      } else {
        App.toast(res.data?.message || 'Failed to create verification batch.', 'error');
      }
    });
  }
}

/**
 * Unposted Document Edit: Collapsible Blue Panels Toggle
 */
window.toggleEditPanel = function (panelId, btn) {
  const panel = document.getElementById(panelId);
  if (!panel) return;
  if (panel.style.display === 'none') {
    panel.style.display = panel.dataset.prevDisplay || 'flex';
    btn.textContent = '-';
  } else {
    panel.dataset.prevDisplay = panel.style.display || 'flex';
    panel.style.display = 'none';
    btn.textContent = '+';
  }
};

/**
 * Unposted Document Edit: Populate Current Document Details & Form from Database
 */
window.updateReceiptCurrentDocDetails = function (docNo, prefix) {
  if (!docNo) {
    window.resetCurrentDocDetails(prefix);
    return;
  }

  // Check window.dbUnpostedDocs (live database records from MySQL)
  const dbDocs = window.dbUnpostedDocs || (window.APP_DATA && window.APP_DATA.dbUnpostedDocs) || {};
  let d = dbDocs[docNo];

  if (!d) {
    // If not found in memory, fallback or fetch
    d = {
      profile: 'General Document Voucher',
      profileVal: 'PF-GRN',
      receiptFrom: 'Purchase Order',
      sourceDept: '832.00',
      receiptDetails: 'Draft Record ' + docNo,
      warehouse: 'Mechanical Stores',
      nbt: '0.00',
      vat: '18.00%',
      vatVal: 18.00,
      transport: '0.00',
      transportVal: 0.00,
      other: '0.00',
      otherVal: 0.00,
      invoiceTotal: '0.00',
      discountForAll: '0.00',
      discountVal: 0.00,
      deliveryDate: '',
      deliveryDateRaw: '',
      accDate: '',
      accDateRaw: '',
      dueDate: '',
      dueDateRaw: '',
      invoiceNum: '',
      orderRef: '',
      otherRef1: '',
      otherRef2: '',
      remarks: ''
    };
  }

  const setT = (id, val) => {
    const el = document.getElementById(prefix + '-cur-' + id);
    if (el) el.textContent = (val !== null && val !== undefined) ? val : '';
  };

  // 1. Populate Green Info Box
  setT('doc-profile', d.profile);
  setT('receipt-from', d.receiptFrom);
  setT('source-dept', d.sourceDept);
  setT('receipt-details', d.receiptDetails);
  setT('warehouse', d.warehouse);
  setT('nbt', d.nbt);
  setT('vat', d.vat);
  setT('transport', d.transport);
  setT('other', d.other);
  setT('invoice-total', d.invoiceTotal);
  setT('discount-for-all', d.discountForAll);
  setT('delivery-date', d.deliveryDate);
  setT('acc-date', d.accDate);
  setT('due-date', d.dueDate);
  setT('invoice-num', d.invoiceNum);
  setT('order-ref', d.orderRef);

  const remEl = document.getElementById(prefix + '-cur-remarks');
  if (remEl) remEl.value = d.remarks || '';

  // 2. Populate Edit Form Inputs
  const form = document.getElementById(prefix + 'UnpostedEditForm');
  if (form) {
    const pfSelect = form.querySelector('[name="update_doc_profile"]');
    if (pfSelect) {
      if (d.profileVal && Array.from(pfSelect.options).some(o => o.value === d.profileVal)) {
        pfSelect.value = d.profileVal;
      } else {
        const matchingOpt = Array.from(pfSelect.options).find(o =>
          (o.text && d.profile && (o.text.trim().toLowerCase().includes(d.profile.toLowerCase()) || d.profile.toLowerCase().includes(o.text.trim().toLowerCase())))
        );
        if (matchingOpt) pfSelect.value = matchingOpt.value;
      }
      if (typeof window.handleIssueDocProfileChange === 'function' && prefix === 'issue') {
        window.handleIssueDocProfileChange(pfSelect.value, 'edit');
      } else {
        const statusBox = document.getElementById(prefix + 'DocPfStatusBox');
        if (statusBox) {
          statusBox.textContent = pfSelect.value ? (pfSelect.options[pfSelect.selectedIndex] ? pfSelect.options[pfSelect.selectedIndex].text : d.profile) : d.profile;
          statusBox.style.color = '#1e40af';
        }
      }
    }

    const setInputVal = (name, val) => {
      const input = form.querySelector('[name="' + name + '"]');
      if (input && val !== undefined && val !== null) {
        input.value = val;
      }
    };

    const setSelectVal = (name, val) => {
      const sel = form.querySelector('[name="' + name + '"]');
      if (sel && val) {
        const match = Array.from(sel.options).find(o =>
          o.value === val ||
          o.text.trim().toLowerCase() === val.toLowerCase() ||
          o.text.trim().toLowerCase().includes(val.toLowerCase()) ||
          val.toLowerCase().includes(o.text.trim().toLowerCase())
        );
        if (match) sel.value = match.value;
      }
    };

    setSelectVal('update_status', d.status || 'Draft');
    setSelectVal('update_receipt_from', d.receiptFrom);
    setInputVal('update_source_dept', d.sourceDept);
    setInputVal('update_delivery_date', d.deliveryDateRaw || '');
    setInputVal('update_acc_date', d.accDateRaw || '');
    setInputVal('update_due_date', d.dueDateRaw || '');
    setSelectVal('update_warehouse', d.warehouse);

    setInputVal('val_vat', d.vatVal !== undefined ? d.vatVal : (d.vat ? d.vat.toString().replace('%', '') : ''));
    setInputVal('val_transport', d.transportVal !== undefined ? d.transportVal : (d.transport ? d.transport.toString().replace(/,/g, '') : ''));
    setInputVal('val_nbt', d.nbtVal !== undefined ? d.nbtVal : (d.nbt ? d.nbt.toString().replace(/,/g, '') : ''));
    setInputVal('val_other', d.otherVal !== undefined ? d.otherVal : (d.other ? d.other.toString().replace(/,/g, '') : ''));

    setInputVal('ref_invoice_number', d.invoiceNum);
    setInputVal('ref_order_reference', d.orderRef);
    setInputVal('ref_other_1', d.otherRef1 || '');
    setInputVal('ref_other_2', d.otherRef2 || '');

    const remInput = form.querySelector('[name="remarks_text"]');
    if (remInput) remInput.value = d.remarks || '';

    setInputVal('invoice_total_without_tax', d.invoiceTotal ? d.invoiceTotal.toString().replace(/,/g, '') : '');
    setInputVal('discount_for_all', d.discountVal !== undefined ? d.discountVal : (d.discountForAll ? d.discountForAll.toString().replace('%', '') : ''));

    const singleCb = form.querySelector('[name="is_single_material"]');
    if (singleCb) singleCb.checked = !!d.isSingleMaterial;

    const percentCb = form.querySelector('[name="is_percentage"]');
    if (percentCb) percentCb.checked = !!d.isPercentage;
  }
};

window.resetCurrentDocDetails = function (prefix) {
  const ids = [
    'doc-profile', 'receipt-from', 'source-dept', 'receipt-details',
    'warehouse', 'nbt', 'vat', 'transport', 'other',
    'invoice-total', 'discount-for-all', 'delivery-date',
    'acc-date', 'due-date', 'invoice-num', 'order-ref'
  ];
  ids.forEach(id => {
    const el = document.getElementById(prefix + '-cur-' + id);
    if (el) el.textContent = '';
  });
  const remEl = document.getElementById(prefix + '-cur-remarks');
  if (remEl) remEl.value = '';
};

/**
 * Save / Update Unposted Document Live to MySQL Database
 */
window.saveUnpostedDocument = async function (prefix, formEl) {
  const selectEl = document.getElementById(prefix === 'receipts' ? 'receiptsEditDocNo' : 'issueEditDocNo');
  const docNo = selectEl ? selectEl.value : '';
  if (!docNo) {
    App.toast('Please select a Document Number to update.', 'warning');
    return;
  }

  const form = formEl || document.getElementById(prefix + 'UnpostedEditForm');
  if (!form) return;

  const submitBtn = form.querySelector('button[type="submit"]');
  const originalText = submitBtn ? submitBtn.innerHTML : 'Update';
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Saving to Database...';
  }

  const formData = new FormData(form);
  formData.append('action', 'update_unposted_document');
  formData.append('doc_no', docNo);

  const res = await App.request('/api/inventory.php', {
    method: 'POST',
    body: formData
  });

  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalText;
  }

  if (res.ok && res.data.success) {
    App.toast(res.data.message || 'Document [' + docNo + '] updated successfully in database!', 'success');
    if (res.data.data && res.data.data.document) {
      const d = res.data.data.document;
      const formattedDoc = {
        profile: d.doc_profile || 'Goods Received Note',
        profileVal: d.doc_profile_code || 'PF-GRN',
        status: d.status || 'Draft',
        receiptFrom: d.receipt_from || 'Purchase Order',
        sourceDept: d.source_dept || '832.00',
        receiptDetails: d.receipt_details || '',
        warehouse: d.warehouse || 'Mechanical Stores',
        nbt: Number(d.nbt || 0).toFixed(2),
        nbtVal: Number(d.nbt || 0),
        vat: Number(d.vat || 0).toFixed(2) + '%',
        vatVal: Number(d.vat || 0),
        transport: Number(d.transport || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
        transportVal: Number(d.transport || 0),
        other: Number(d.other_cost || 0).toFixed(2),
        otherVal: Number(d.other_cost || 0),
        invoiceTotal: Number(d.invoice_total || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
        discountForAll: Number(d.discount_for_all || 0).toFixed(2) + (d.is_percentage ? '%' : ''),
        discountVal: Number(d.discount_for_all || 0),
        isSingleMaterial: !!d.is_single_material,
        isPercentage: !!d.is_percentage,
        deliveryDate: d.delivery_date || '',
        deliveryDateRaw: d.delivery_date || '',
        accDate: d.acc_date || '',
        accDateRaw: d.acc_date || '',
        dueDate: d.due_date || '',
        dueDateRaw: d.due_date || '',
        invoiceNum: d.invoice_number || '',
        orderRef: d.order_reference || '',
        otherRef1: d.other_ref_1 || '',
        otherRef2: d.other_ref_2 || '',
        remarks: d.remarks || ''
      };

      if (!window.dbUnpostedDocs) window.dbUnpostedDocs = {};
      window.dbUnpostedDocs[docNo] = formattedDoc;
      window.updateReceiptCurrentDocDetails(docNo, prefix);
    }
  } else {
    App.toast(res.data?.message || 'Failed to update document in database.', 'error');
  }
};

/**
 * Issue Entry: Update Available & Reorder Quantities upon Material selection
 */
window.updateIssueMaterialQty = function (val) {
  const items = (typeof issueStationMaterials !== 'undefined' && issueStationMaterials.length > 0)
    ? issueStationMaterials
    : (window.STATION_ADDED_ITEMS || []);
  const found = items.find(it => (it.code || it.formatted_code) === val || (it.raw_code === val));
  const availEl = document.getElementById('issueAvailQty');
  const reorderEl = document.getElementById('issueReorderQty');
  if (found) {
    if (availEl) availEl.value = parseFloat(found.avail !== undefined ? found.avail : (found.max_stock_level || 0));
    if (reorderEl) reorderEl.value = parseFloat(found.reorder !== undefined ? found.reorder : (found.min_reorder_level || 0));
  } else {
    const sel = document.getElementById('issueMaterialSelect');
    if (sel && sel.options && sel.selectedIndex >= 0) {
      const opt = sel.options[sel.selectedIndex];
      if (availEl) availEl.value = opt ? opt.getAttribute('data-avail') || '' : '';
      if (reorderEl) reorderEl.value = opt ? opt.getAttribute('data-reorder') || '' : '';
    }
  }
};

/**
 * ============================================================================
 * Issue Entry: Power Station Added Material Combobox & Live Search
 * ============================================================================
 */
let issueStationMaterials = [];
let currentFilteredIssueMaterials = [];
let highlightedMaterialIndex = -1;

window.initIssueMaterialSelector = function () {
  const container = document.getElementById('issueMaterialDropdownList');
  if (!container) return;

  // Use preloaded station items or fetch from API
  if (Array.isArray(window.STATION_ADDED_ITEMS) && window.STATION_ADDED_ITEMS.length > 0) {
    issueStationMaterials = window.STATION_ADDED_ITEMS;
    window.renderIssueMaterialList(issueStationMaterials);
  } else {
    window.loadStationMaterialsForIssue();
  }
};

window.renderIssueMaterialList = function (items) {
  const container = document.getElementById('issueMaterialDropdownList');
  const countBadge = document.getElementById('issueMaterialCountBadge');
  if (!container) return;

  currentFilteredIssueMaterials = items || [];
  highlightedMaterialIndex = -1;

  if (countBadge) {
    countBadge.textContent = `${items.length} code${items.length === 1 ? '' : 's'}`;
  }

  if (!items || items.length === 0) {
    container.innerHTML = `
      <div style="padding: 20px 14px; text-align: center; color: #64748b; font-size: 0.88rem;">
        No codes found matching your search.
      </div>`;
    return;
  }

  // Show up to 100 items for high performance rendering
  const maxDisplay = 100;
  const displayItems = items.slice(0, maxDisplay);

  let html = displayItems.map((it, idx) => `
    <div class="issue-material-item" id="issueMatItem_${idx}" data-idx="${idx}"
      style="padding: 8px 12px; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background 0.12s ease; background: #ffffff;"
      onmouseover="highlightIssueMaterialIndex(${idx});"
      onmouseout="unhighlightIssueMaterialIndex(${idx});"
      onclick="selectIssueMaterialByIndex(${idx});">
      <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
        <div style="display: flex; align-items: center; gap: 8px; overflow: hidden;">
          <span style="font-family: monospace; font-size: 0.84rem; font-weight: 800; color: #166534; background: #dcfce7; border: 1px solid #86efac; padding: 2px 6px; border-radius: 3px; white-space: nowrap;">
            ${escapeHtml(it.code || it.formatted_code || '')}
          </span>
          <span style="font-size: 0.88rem; font-weight: 600; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
            ${escapeHtml(it.name || it.item_name || '')}
          </span>
        </div>
        <span style="font-size: 0.72rem; font-weight: 700; color: #475569; background: #f1f5f9; border: 1px solid #e2e8f0; padding: 2px 5px; border-radius: 3px; white-space: nowrap;">
          ${escapeHtml(it.uom || 'NO')}
        </span>
      </div>
      <div style="display: flex; align-items: center; gap: 14px; margin-top: 4px; font-size: 0.74rem; color: #64748b;">
        <span>Bay: <strong style="color: #334155;">${escapeHtml(it.bay || it.local_bay_location || 'Bay-A1')}</strong></span>
        <span>Reorder Level: <strong style="color: #334155;">${parseFloat(it.reorder !== undefined ? it.reorder : (it.min_reorder_level || 0))}</strong></span>
        <span>Avail: <strong style="color: #166534;">${parseFloat(it.avail !== undefined ? it.avail : (it.max_stock_level || 0))}</strong></span>
      </div>
    </div>
  `).join('');

  if (items.length > maxDisplay) {
    html += `
      <div style="padding: 8px 12px; text-align: center; font-size: 0.75rem; color: #64748b; background: #f8fafc; border-top: 1px solid #e2e8f0;">
        Showing first ${maxDisplay} of ${items.length} codes. Type in the box to narrow down.
      </div>`;
  }

  container.innerHTML = html;
};

window.highlightIssueMaterialIndex = function (idx) {
  highlightedMaterialIndex = idx;
  const items = document.querySelectorAll('.issue-material-item');
  items.forEach((el, i) => {
    if (i === idx) {
      el.style.backgroundColor = '#f0fdf4';
    } else {
      el.style.backgroundColor = '#ffffff';
    }
  });
};

window.unhighlightIssueMaterialIndex = function (idx) {
  const el = document.getElementById(`issueMatItem_${idx}`);
  if (el) el.style.backgroundColor = '#ffffff';
};

window.selectIssueMaterialByIndex = function (idx) {
  const it = currentFilteredIssueMaterials[idx];
  if (it) {
    selectIssueMaterialItem(it);
  }
};

window.selectIssueMaterialItem = function (item) {
  const searchInput = document.getElementById('issueMaterialSearchInput');
  const hiddenSelect = document.getElementById('issueMaterialSelect');
  const availEl = document.getElementById('issueAvailQty');
  const reorderEl = document.getElementById('issueReorderQty');
  const clearBtn = document.getElementById('issueMaterialClearBtn');
  const menu = document.getElementById('issueMaterialDropdownMenu');

  const code = item.code || item.formatted_code || '';
  const name = item.name || item.item_name || '';
  const uom = item.uom || 'NO';
  const avail = item.avail !== undefined ? item.avail : (item.max_stock_level || 0);
  const reorder = item.reorder !== undefined ? item.reorder : (item.min_reorder_level || 0);

  if (searchInput) {
    searchInput.value = `${code} • ${name} (${uom})`;
  }
  if (hiddenSelect) {
    hiddenSelect.value = code;
  }
  if (availEl) {
    availEl.value = parseFloat(avail) || 0;
  }
  if (reorderEl) {
    reorderEl.value = parseFloat(reorder) || 0;
  }
  if (clearBtn) {
    clearBtn.style.display = 'block';
  }
  if (menu) {
    menu.style.display = 'none';
  }

  // Focus required quantity for seamless operator typing
  const reqQtyEl = document.getElementById('issueReqQty');
  if (reqQtyEl) {
    reqQtyEl.focus();
  }
};

window.filterIssueMaterialDropdown = function (query) {
  const q = (query || '').trim().toLowerCase();
  const clearBtn = document.getElementById('issueMaterialClearBtn');
  if (clearBtn) {
    clearBtn.style.display = q ? 'block' : 'none';
  }

  openIssueMaterialDropdown();

  if (!q) {
    renderIssueMaterialList(issueStationMaterials);
    return;
  }

  const filtered = issueStationMaterials.filter(it => {
    const code = (it.code || it.formatted_code || '').toLowerCase();
    const raw = (it.raw_code || '').toLowerCase();
    const name = (it.name || it.item_name || '').toLowerCase();
    const bay = (it.bay || it.local_bay_location || '').toLowerCase();
    return code.includes(q) || raw.includes(q) || name.includes(q) || bay.includes(q);
  });

  renderIssueMaterialList(filtered);
};

window.openIssueMaterialDropdown = function () {
  const menu = document.getElementById('issueMaterialDropdownMenu');
  if (menu) {
    menu.style.display = 'block';
  }
};

window.toggleIssueMaterialDropdown = function () {
  const menu = document.getElementById('issueMaterialDropdownMenu');
  if (menu) {
    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
  }
};

window.clearIssueMaterialSelection = function (focusInput = false) {
  const searchInput = document.getElementById('issueMaterialSearchInput');
  const hiddenSelect = document.getElementById('issueMaterialSelect');
  const availEl = document.getElementById('issueAvailQty');
  const reorderEl = document.getElementById('issueReorderQty');
  const clearBtn = document.getElementById('issueMaterialClearBtn');

  if (searchInput) {
    searchInput.value = '';
    if (focusInput) searchInput.focus();
  }
  if (hiddenSelect) hiddenSelect.value = '';
  if (availEl) availEl.value = '';
  if (reorderEl) reorderEl.value = '';
  if (clearBtn) clearBtn.style.display = 'none';

  renderIssueMaterialList(issueStationMaterials);
};

window.handleIssueMaterialKeydown = function (e) {
  const menu = document.getElementById('issueMaterialDropdownMenu');
  if (!menu || menu.style.display === 'none') {
    if (e.key === 'ArrowDown' || e.key === 'Enter') {
      openIssueMaterialDropdown();
      e.preventDefault();
    }
    return;
  }

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    if (highlightedMaterialIndex < currentFilteredIssueMaterials.length - 1) {
      highlightedMaterialIndex++;
      highlightIssueMaterialIndex(highlightedMaterialIndex);
      const el = document.getElementById(`issueMatItem_${highlightedMaterialIndex}`);
      if (el) el.scrollIntoView({ block: 'nearest' });
    }
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    if (highlightedMaterialIndex > 0) {
      highlightedMaterialIndex--;
      highlightIssueMaterialIndex(highlightedMaterialIndex);
      const el = document.getElementById(`issueMatItem_${highlightedMaterialIndex}`);
      if (el) el.scrollIntoView({ block: 'nearest' });
    }
  } else if (e.key === 'Enter') {
    e.preventDefault();
    if (highlightedMaterialIndex >= 0 && highlightedMaterialIndex < currentFilteredIssueMaterials.length) {
      selectIssueMaterialByIndex(highlightedMaterialIndex);
    }
  } else if (e.key === 'Escape') {
    menu.style.display = 'none';
  }
};

window.handleIssueAddMaterial = function () {
  const hiddenSelect = document.getElementById('issueMaterialSelect');
  const searchInput = document.getElementById('issueMaterialSearchInput');
  const reqQtyEl = document.getElementById('issueReqQty');
  const selectedCode = hiddenSelect ? hiddenSelect.value : '';
  const selectedText = searchInput ? searchInput.value : '';
  const reqQty = reqQtyEl ? parseInt(reqQtyEl.value, 10) : 0;

  if (!selectedCode && !selectedText) {
    if (typeof App !== 'undefined' && App.toast) {
      App.toast('Please search and select a material from the power station code catalog first.', 'warning');
    } else {
      alert('Please search and select a material from the power station code catalog first.');
    }
    const input = document.getElementById('issueMaterialSearchInput');
    if (input) input.focus();
    return;
  }

  if (!reqQty || reqQty <= 0) {
    if (typeof App !== 'undefined' && App.toast) {
      App.toast('Please enter a valid required quantity.', 'warning');
    } else {
      alert('Please enter a valid required quantity.');
    }
    if (reqQtyEl) reqQtyEl.focus();
    return;
  }

  const msg = `Material [${selectedCode || selectedText}] (Qty: ${reqQty}) added to issue dispatch list.`;
  if (typeof App !== 'undefined' && App.toast) {
    App.toast(msg, 'success');
  } else {
    alert(msg);
  }

  // Reset inputs for adding the next material
  clearIssueMaterialSelection(true);
  if (reqQtyEl) reqQtyEl.value = '';
};

window.loadStationMaterialsForIssue = async function (stationId) {
  try {
    const sid = stationId || window.APP_DATA?.userStationId || 1;
    const res = await App.request(`/api/code_management.php?action=station_inventory&station_id=${sid}`);
    if (res.ok && res.data?.items) {
      issueStationMaterials = res.data.items.map(it => ({
        id: it.id,
        master_item_id: it.master_item_id,
        code: it.formatted_code,
        raw_code: it.raw_code,
        name: it.item_name,
        uom: it.uom || 'NO',
        bay: it.local_bay_location || 'Bay-A1',
        avail: parseFloat(it.max_stock_level) || 0,
        reorder: parseFloat(it.min_reorder_level) || 0
      }));
      window.STATION_ADDED_ITEMS = issueStationMaterials;
      renderIssueMaterialList(issueStationMaterials);
    }
  } catch (err) {
    console.error('Error loading station materials for issue:', err);
  }
};

// Close dropdown when clicking outside
document.addEventListener('click', function (e) {
  const menu = document.getElementById('issueMaterialDropdownMenu');
  if (menu && menu.style.display !== 'none') {
    if (!e.target.closest('#issueMaterialSearchInput') &&
      !e.target.closest('#issueMaterialDropdownMenu') &&
      !e.target.closest('button[title="Show Station Codes"]') &&
      !e.target.closest('#issueMaterialClearBtn')) {
      menu.style.display = 'none';
    }
  }
});

// Initialize unposted document forms and issue material selector on initial load
document.addEventListener('DOMContentLoaded', () => {
  const rSel = document.getElementById('receiptsEditDocNo');
  if (rSel && rSel.value) {
    window.updateReceiptCurrentDocDetails(rSel.value, 'receipts');
  }

  const iSel = document.getElementById('issueEditDocNo');
  if (iSel && iSel.value) {
    window.updateReceiptCurrentDocDetails(iSel.value, 'issue');
  }

  // Initialize Code Management System
  initCodeManagementModule();

  // Initialize Simple 4-Box User Code Tab if present
  if (document.getElementById('simpleTier1')) {
    updateSimpleCode();
  }

  // Initialize Station Inventory Table if present
  if (document.getElementById('stationItemsTableBody')) {
    loadStationInventory();
  }

  // Initialize Station Requests Table if present
  if (document.getElementById('stationRequestsTableBody')) {
    loadStationRequests();
  }

  // Initialize Station Material Combobox in Issue Entry
  if (document.getElementById('issueMaterialDropdownList')) {
    initIssueMaterialSelector();
  }
});

/**
 * ============================================================================
 * Inventory Item Code Generation & Approval Management System JS Module
 * ============================================================================
 */
let liveSearchDebounceTimer = null;
let currentActiveBranch = { tier1: 1, tier2: 1, tier3: 1 };
let currentActiveReqData = null;

function initCodeManagementModule() {
  const appData = window.APP_DATA || {};
  const tiers = appData.tierHierarchy || { tier1: [], tier2: {}, tier3: {} };

  // 1. Setup Generator Tier Cascading
  setupTierCascading('codeGenTier1', 'codeGenTier2', 'codeGenTier3');

  // 2. Setup Request Form Tier Cascading
  setupTierCascading('reqTier1', 'reqTier2', 'reqTier3');

  // 3. Initial load of Tier 1-01-01 in Generator
  onCodeGenTier3Change();

  // 4. Initial load of Admin Queue if admin desk is present
  if (document.getElementById('adminRequestsQueueBody') || document.getElementById('adminConsoleRequestsQueueBody')) {
    loadAdminRequestsQueue();
  }
}

function setupTierCascading(t1Id, t2Id, t3Id) {
  const t1Select = document.getElementById(t1Id);
  const t2Select = document.getElementById(t2Id);
  const t3Select = document.getElementById(t3Id);
  if (!t1Select || !t2Select || !t3Select) return;

  const appData = window.APP_DATA || {};
  const tiers = appData.tierHierarchy || { tier1: [], tier2: {}, tier3: {} };

  function populateTier2() {
    const selectedT1Option = t1Select.options[t1Select.selectedIndex];
    const t1DbId = selectedT1Option ? selectedT1Option.getAttribute('data-id') : null;
    t2Select.innerHTML = '';

    const t2Children = (t1DbId && tiers.tier2 && tiers.tier2[t1DbId]) ? tiers.tier2[t1DbId] : [];
    if (t2Children.length === 0) {
      for (let i = 1; i <= 5; i++) {
        const opt = document.createElement('option');
        opt.value = i;
        opt.setAttribute('data-id', i);
        opt.textContent = String(i).padStart(2, '0');
        t2Select.appendChild(opt);
      }
    } else {
      t2Children.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.code_val;
        opt.setAttribute('data-id', item.id);
        opt.textContent = String(item.code_val).padStart(2, '0') + (item.name ? ' - ' + item.name : '');
        t2Select.appendChild(opt);
      });
    }
    populateTier3();
  }

  function populateTier3() {
    const selectedT2Option = t2Select.options[t2Select.selectedIndex];
    const t2DbId = selectedT2Option ? selectedT2Option.getAttribute('data-id') : null;
    t3Select.innerHTML = '';

    const t3Children = (t2DbId && tiers.tier3 && tiers.tier3[t2DbId]) ? tiers.tier3[t2DbId] : [];
    if (t3Children.length === 0) {
      for (let i = 1; i <= 4; i++) {
        const opt = document.createElement('option');
        opt.value = i;
        opt.setAttribute('data-id', i);
        opt.textContent = String(i).padStart(2, '0');
        t3Select.appendChild(opt);
      }
    } else {
      t3Children.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.code_val;
        opt.setAttribute('data-id', item.id);
        opt.textContent = String(item.code_val).padStart(2, '0') + (item.name ? ' - ' + item.name : '');
        t3Select.appendChild(opt);
      });
    }
  }

  t1Select.addEventListener('change', populateTier2);
  t2Select.addEventListener('change', populateTier3);

  // Initial population
  populateTier2();
}

function switchCodeSubView(subViewId) {
  document.querySelectorAll('.code-sub-pane').forEach(el => el.style.display = 'none');
  document.querySelectorAll('.code-pill-btn').forEach(btn => btn.classList.remove('active'));

  const targetPane = document.getElementById(subViewId);
  if (targetPane) targetPane.style.display = 'block';

  if (subViewId === 'sub-view-generator') {
    const b = document.getElementById('btnSubGenerator');
    if (b) b.classList.add('active');
  } else if (subViewId === 'sub-view-request') {
    const b = document.getElementById('btnSubRequest');
    if (b) b.classList.add('active');
  } else if (subViewId === 'sub-view-station') {
    const b = document.getElementById('btnSubStation');
    if (b) b.classList.add('active');
    loadStationInventory();
    loadStationRequests();
  } else if (subViewId === 'sub-view-roles') {
    const b = document.getElementById('btnSubRoles');
    if (b) b.classList.add('active');
  } else if (subViewId === 'sub-view-admin') {
    const b = document.getElementById('btnSubAdmin');
    if (b) b.classList.add('active');
    loadAdminRequestsQueue();
  }
}

window.switchCodeSubView = switchCodeSubView;

// Tier change triggers
function onCodeGenTier1Change() {
  onCodeGenTier3Change();
}
function onCodeGenTier2Change() {
  onCodeGenTier3Change();
}

function onCodeGenTier3Change() {
  const t1 = parseInt(document.getElementById('codeGenTier1')?.value || '1', 10);
  const t2 = parseInt(document.getElementById('codeGenTier2')?.value || '1', 10);
  const t3 = parseInt(document.getElementById('codeGenTier3')?.value || '1', 10);
  const query = document.getElementById('liveDuplicateQuery')?.value || '';

  currentActiveBranch = { tier1: t1, tier2: t2, tier3: t3 };
  const branchText = `${t1}-${String(t2).padStart(2, '0')}-${String(t3).padStart(2, '0')}`;

  const branchLabel = document.getElementById('activeBranchLabel');
  if (branchLabel) branchLabel.textContent = branchText;

  queryAvailability(t1, t2, t3, query);
}

window.onCodeGenTier1Change = onCodeGenTier1Change;
window.onCodeGenTier2Change = onCodeGenTier2Change;
window.onCodeGenTier3Change = onCodeGenTier3Change;

function onLiveDuplicateSearch(val) {
  clearTimeout(liveSearchDebounceTimer);
  liveSearchDebounceTimer = setTimeout(() => {
    const t1 = currentActiveBranch.tier1;
    const t2 = currentActiveBranch.tier2;
    const t3 = currentActiveBranch.tier3;
    queryAvailability(t1, t2, t3, val);
  }, 250);
}

window.onLiveDuplicateSearch = onLiveDuplicateSearch;

async function queryAvailability(t1, t2, t3, query = '') {
  const tbody = document.getElementById('registeredItemsTableBody');
  if (!tbody) return;

  tbody.innerHTML = `
    <tr>
      <td colspan="7" style="text-align: center; padding: 18px; color: #64748b;">
        <span class="spinner" style="display:inline-block; width:16px; height:16px; border:2px solid #650b2b; border-top-color:transparent; border-radius:50%; animation:spin 0.6s linear infinite; vertical-align:middle; margin-right:6px;"></span>
        Querying FIFO Master Database for branch ${t1}-${String(t2).padStart(2, '0')}-${String(t3).padStart(2, '0')}...
      </td>
    </tr>
  `;

  try {
    const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
    const res = await App.request(`/api/code_management.php?action=check_availability&tier1=${t1}&tier2=${t2}&tier3=${t3}&q=${encodeURIComponent(query)}`);

    if (res.ok && res.data) {
      const d = res.data;

      // Update Preview Display
      const pBadge = document.getElementById('previewCodeBadge');
      const pRaw = document.getElementById('previewRawCode');
      const pSeq = document.getElementById('previewSeqVal');
      if (pBadge) pBadge.textContent = d.preview_formatted_code;
      if (pRaw) pRaw.textContent = d.preview_raw_code;
      if (pSeq) pSeq.textContent = d.suggested_seq_formatted;

      // Update Request Form Suggested Seq
      const reqSeq = document.getElementById('reqSuggestedSeq');
      if (reqSeq) reqSeq.value = d.suggested_seq_formatted;

      // Update Counter
      const countLabel = document.getElementById('registeredCountLabel');
      if (countLabel) {
        countLabel.innerHTML = `Showing <strong>${d.items.length}</strong> registered items under branch <strong>${d.tier_combination}</strong>`;
      }

      if (d.items.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="7" style="text-align: center; padding: 28px; color: #94a3b8; font-size: 0.88rem;">
              No registered items found under branch <strong>${d.tier_combination}</strong>.
              <div style="margin-top: 6px;">Next available sequence is <strong>${d.suggested_seq_formatted}</strong> (${d.preview_formatted_code}).</div>
            </td>
          </tr>
        `;
      } else {
        const sortedDItems = (d.items || []).slice().sort((a, b) => {
          const seqA = parseInt(a.tier4_seq !== undefined && a.tier4_seq !== null ? a.tier4_seq : (String(a.formatted_code || '').split('-').pop() || 0), 10) || 0;
          const seqB = parseInt(b.tier4_seq !== undefined && b.tier4_seq !== null ? b.tier4_seq : (String(b.formatted_code || '').split('-').pop() || 0), 10) || 0;
          if (seqA !== seqB) return seqA - seqB;
          return String(a.formatted_code || '').localeCompare(String(b.formatted_code || ''));
        });
        tbody.innerHTML = sortedDItems.map(item => {
          const isInStation = Number(item.is_in_station) > 0;
          const statusBadge = isInStation
            ? `<span class="badge-in-station">✓ In Station</span>`
            : `<span style="font-size: 0.74rem; color: #64748b;">Not in Station</span>`;

          const actionBtn = isInStation
            ? `<span style="font-size: 0.76rem; color: #10b981; font-weight: 700;">Provisioned</span>`
            : `<button type="button" class="btn btn-secondary btn-sm" style="font-size: 0.74rem; padding: 3px 8px;"
                 onclick="openImportModal(${item.id}, '${item.formatted_code}', '${encodeURIComponent(item.item_name)}')">
                 + Import to Station
               </button>`;

          return `
            <tr>
              <td><span class="code-tag-badge">${item.formatted_code}</span></td>
              <td><code style="font-size: 0.8rem; color: #64748b;">${item.raw_code}</code></td>
              <td>
                <strong style="color: #0f172a; font-size: 0.9rem;">${escapeHtml(item.item_name)}</strong>
                ${item.specifications ? `<div style="font-size: 0.76rem; color: #64748b; margin-top: 2px;">${escapeHtml(item.specifications)}</div>` : ''}
              </td>
              <td><span class="badge badge-secondary" style="font-size: 0.72rem;">${escapeHtml(item.uom)}</span></td>
              <td style="text-align: center; font-weight: 700; color: #0284c7;">${String(item.tier4_seq).padStart(3, '0')}</td>
              <td>${statusBadge}</td>
              <td style="text-align: right;">${actionBtn}</td>
            </tr>
          `;
        }).join('');
      }
    } else {
      tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:#dc2626; padding:18px;">Failed to query master catalog: ${res.data?.message || 'Server error'}</td></tr>`;
    }
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; color:#dc2626; padding:18px;">Connection error: ${err.message}</td></tr>`;
  }
}

function prefillAndSwitchToRequest() {
  const t1 = document.getElementById('codeGenTier1')?.value || '1';
  const t2 = document.getElementById('codeGenTier2')?.value || '1';
  const t3 = document.getElementById('codeGenTier3')?.value || '1';

  const reqT1 = document.getElementById('reqTier1');
  const reqT2 = document.getElementById('reqTier2');
  const reqT3 = document.getElementById('reqTier3');

  if (reqT1) {
    reqT1.value = t1;
    reqT1.dispatchEvent(new Event('change'));
  }
  if (reqT2) {
    setTimeout(() => {
      reqT2.value = t2;
      reqT2.dispatchEvent(new Event('change'));
      setTimeout(() => {
        if (reqT3) reqT3.value = t3;
      }, 50);
    }, 50);
  }

  switchCodeSubView('sub-view-request');
  document.getElementById('reqItemName')?.focus();
}

window.prefillAndSwitchToRequest = prefillAndSwitchToRequest;

// Request form handlers
function onReqTier1Change() {
  // Cascaded automatically by setupTierCascading
}
function onReqTier2Change() {
  // Cascaded automatically by setupTierCascading
}
function onReqTier3Change() {
  // Update suggested seq
  const t1 = parseInt(document.getElementById('reqTier1')?.value || '1', 10);
  const t2 = parseInt(document.getElementById('reqTier2')?.value || '1', 10);
  const t3 = parseInt(document.getElementById('reqTier3')?.value || '1', 10);

  const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
  App.request(`/api/code_management.php?action=check_availability&tier1=${t1}&tier2=${t2}&tier3=${t3}`).then(res => {
    if (res.ok && res.data) {
      const seqEl = document.getElementById('reqSuggestedSeq');
      if (seqEl) seqEl.value = res.data.suggested_seq_formatted;
    }
  });
}

window.onReqTier1Change = onReqTier1Change;
window.onReqTier2Change = onReqTier2Change;
window.onReqTier3Change = onReqTier3Change;

async function submitCodeCreationRequest(e) {
  e.preventDefault();
  const form = e.target;
  const btn = document.getElementById('btnSubmitCodeReq');
  if (btn) btn.disabled = true;

  try {
    const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
    const formData = new FormData(form);
    formData.append('action', 'create_request');

    const res = await App.request('/api/code_management.php', {
      method: 'POST',
      body: formData
    });

    if (res.ok && res.data?.success) {
      App.toast(res.data.message || 'Code Creation Request submitted successfully!', 'success');
      form.reset();
      switchCodeSubView('sub-view-station');
    } else {
      App.toast(res.data?.message || 'Failed to submit code request.', 'error');
    }
  } catch (err) {
    App.toast(`Error: ${err.message}`, 'error');
  } finally {
    if (btn) btn.disabled = false;
  }
}

window.submitCodeCreationRequest = submitCodeCreationRequest;

// Station Inventory & Requests Loaders
let stationInventorySearchDebounce = null;
window.onSearchStationInventory = function (query) {
  clearTimeout(stationInventorySearchDebounce);
  stationInventorySearchDebounce = setTimeout(() => {
    loadStationInventory(query.trim());
  }, 250);
};

async function loadStationInventory(search = '') {
  const tbody = document.getElementById('stationItemsTableBody');
  if (!tbody) return;

  tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:18px; color:#64748b;">Loading power station code catalog...</td></tr>`;

  try {
    const userStationId = window.APP_DATA?.userStationId || '';
    const queryParam = search ? `&q=${encodeURIComponent(search)}` : '';
    const res = await App.request(`/api/code_management.php?action=station_inventory&station_id=${userStationId}${queryParam}`);

    if (res.ok && res.data?.items) {
      const items = res.data.items;
      if (items.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:24px; color:#94a3b8;">${search ? 'No matching items found for "' + escapeHtml(search) + '".' : 'No items provisioned in this power station catalog yet.'}</td></tr>`;
      } else {
        tbody.innerHTML = items.map(it => `
          <tr>
            <td><span class="code-tag-badge" style="font-size:0.92rem; font-weight:800; color:#650b2b; background:#fdf2f5; border:1px solid #fbcfe8; padding:4px 9px; border-radius:4px; font-family: monospace;">${it.formatted_code}</span></td>
            <td><strong style="color: #0f172a; font-size: 0.92rem;">${escapeHtml(it.item_name)}</strong></td>
            <td><span style="font-size: 0.82rem; color: #64748b;">${escapeHtml(it.specifications || '-')}</span></td>
            <td><span class="badge badge-secondary" style="font-weight:600;">${escapeHtml(it.uom)}</span></td>
            <td><code style="background:#f1f5f9; padding:3px 7px; border-radius:3px; color:#0f172a; font-weight:700;">${escapeHtml(it.local_bay_location || 'Bay-01')}</code></td>
            <td><span class="badge badge-success" style="font-size:0.75rem; padding:4px 8px; font-weight:700;">✓ Provisioned in Station</span></td>
          </tr>
        `).join('');
      }
    }
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color:#dc2626; padding:18px;">Failed to load station items: ${err.message}</td></tr>`;
  }
}

async function loadStationRequests() {
  const tbody = document.getElementById('stationRequestsTableBody');
  const cardEl = document.getElementById('myStationRequestsCard');
  if (!tbody) return;

  try {
    const userStationId = window.APP_DATA?.userStationId || '';
    const res = await App.request(`/api/code_management.php?action=list_requests&station_id=${userStationId}`);

    if (res.ok && res.data?.requests) {
      const requests = res.data.requests;
      window._stationRequestsMap = window._stationRequestsMap || {};
      requests.forEach(r => {
        window._stationRequestsMap[r.id] = r;
      });

      if (requests.length === 0) {
        if (cardEl) cardEl.style.display = 'block';
        tbody.innerHTML = `
          <tr>
            <td colspan="6" style="text-align: center; padding: 24px; color: #64748b; font-size: 0.8125rem;">
              No code creation requests found. Use the <strong>Request Code</strong> button above to request new codes.
            </td>
          </tr>
        `;
      } else {
        if (cardEl) cardEl.style.display = 'block';
        tbody.innerHTML = requests.map(r => {
          const isAddedToMaster = Number(r.is_added_to_master) > 0;
          const isInStation = Number(r.is_added_to_station) > 0;

          let statusBadge = `<span style="font-size:0.72rem; font-weight:700; color:#c2410c; background:#fff7ed; border:1px solid #fed7aa; padding:2px 8px; border-radius:3px; display:inline-block;">PENDING REVIEW</span>`;
          if (isAddedToMaster) {
            statusBadge = `<span style="font-size:0.72rem; font-weight:700; color:#15803d; background:#dcfce7; border:1px solid #86efac; padding:2px 8px; border-radius:3px; display:inline-block;">ADDED TO MASTER</span>`;
          } else if (r.status === 'APPROVED') {
            statusBadge = `<span style="font-size:0.72rem; font-weight:700; color:#0369a1; background:#e0f2fe; border:1px solid #bae6fd; padding:2px 8px; border-radius:3px; display:inline-block;">APPROVED (PENDING MASTER)</span>`;
          } else if (r.status === 'REJECTED') {
            statusBadge = `<span style="font-size:0.72rem; font-weight:700; color:#991b1b; background:#fee2e2; border:1px solid #fecaca; padding:2px 8px; border-radius:3px; display:inline-block;">REJECTED</span>`;
          }

          let displayCode = r.approved_formatted_code;
          let isApprovedCode = Boolean(displayCode);
          if (!displayCode) {
            const t1 = String(r.tier1 || 1).padStart(2, '0');
            const t2 = String(r.tier2 || 1).padStart(2, '0');
            const t3 = String(r.tier3 || 1).padStart(2, '0');
            const rawSeq = r.assigned_tier4_seq !== null && r.assigned_tier4_seq !== undefined ? r.assigned_tier4_seq : (r.suggested_tier4_seq !== null && r.suggested_tier4_seq !== undefined ? r.suggested_tier4_seq : (r.tier4_seq !== null && r.tier4_seq !== undefined ? r.tier4_seq : 0));
            const t4 = String(rawSeq).padStart(3, '0');
            displayCode = `${t1}-${t2}-${t3}-${t4}`;
          }

          const codeCol = (isAddedToMaster || isApprovedCode)
            ? `<span style="font-family:monospace; font-size:0.8rem; font-weight:700; color:#650b2b; background:#fdf2f5; border:1px solid #fbcfe8; padding:1px 6px; border-radius:3px;">${escapeHtml(displayCode)}</span>`
            : `<span style="font-family:monospace; font-size:0.8rem; font-weight:700; color:#475569; background:#f1f5f9; border:1px solid #cbd5e1; padding:1px 6px; border-radius:3px;">${escapeHtml(displayCode)}</span>`;

          let actionCol = '-';
          if (isAddedToMaster) {
            if (isInStation) {
              actionCol = `<span style="font-size:0.74rem; font-weight:700; color:#059669; background:#ecfdf5; border:1px solid #a7f3d0; padding:3px 8px; border-radius:3px; display:inline-flex; align-items:center; gap:4px;">&#10003; Added to Station</span>`;
            } else {
              actionCol = `
                <button type="button" class="btn btn-sm" onclick="openImportModal(${r.generated_master_item_id || 0}, '${escapeHtml(displayCode)}', '${encodeURIComponent(r.item_name || '')}');"
                  style="font-size:0.74rem; font-weight:700; padding:4px 12px; border-radius:3px; background:#650b2b; color:#ffffff; border:1px solid #4a071e; cursor:pointer;"
                  title="Add code to station table without searching">
                  + Add to Station
                </button>
              `;
            }
          } else if (r.status === 'APPROVED') {
            actionCol = `<span style="font-size:0.73rem; color:#0284c7; font-weight:600;" title="Code approved in staging. Cannot add to station until Code Admin clicks '+ Add to Master'">Awaiting Master Add</span>`;
          } else if (r.status === 'REJECTED') {
            const remarksText = r.review_remarks ? escapeHtml(r.review_remarks) : 'Rejected by Code Administrator';
            actionCol = `
              <div style="display:inline-flex; gap:5px; align-items:center;">
                <span style="font-size:0.72rem; color:#991b1b; font-weight:700; background:#fee2e2; border:1px solid #fca5a5; padding:2px 7px; border-radius:3px; cursor:help;" title="${remarksText}">Rejected</span>
                <button type="button" class="btn btn-sm" onclick="openEditCodeRequestModal(${r.id});"
                  style="font-size:0.72rem; font-weight:700; padding:3px 9px; border-radius:3px; background:#0284c7; color:#ffffff; border:1px solid #0369a1; cursor:pointer; display:inline-flex; align-items:center; gap:2px;"
                  title="Edit details and resubmit this request">
                  ✏️ Edit
                </button>
                <button type="button" class="btn btn-sm" onclick="deleteStationCodeRequest(${r.id});"
                  style="font-size:0.72rem; font-weight:700; padding:3px 8px; border-radius:3px; background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; cursor:pointer; display:inline-flex; align-items:center;"
                  title="Permanently delete this rejected request">
                  🗑️
                </button>
              </div>
            `;
          } else {
            // PENDING STATUS: Users can edit their details or cancel/delete their pending request
            actionCol = `
              <div style="display:inline-flex; gap:5px; align-items:center;">
                <span style="font-size:0.72rem; color:#d97706; font-weight:700; background:#fef3c7; border:1px solid #fde68a; padding:2px 7px; border-radius:3px;">Pending</span>
                <button type="button" class="btn btn-sm" onclick="openEditCodeRequestModal(${r.id});"
                  style="font-size:0.72rem; font-weight:700; padding:3px 9px; border-radius:3px; background:#0284c7; color:#ffffff; border:1px solid #0369a1; cursor:pointer; display:inline-flex; align-items:center; gap:2px;"
                  title="Edit details while request is pending approval">
                  ✏️ Edit
                </button>
                <button type="button" class="btn btn-sm" onclick="deleteStationCodeRequest(${r.id});"
                  style="font-size:0.72rem; font-weight:700; padding:3px 8px; border-radius:3px; background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1; cursor:pointer; display:inline-flex; align-items:center;"
                  title="Cancel and delete this pending request">
                  🗑️
                </button>
              </div>
            `;
          }

          return `
            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;"
                onmouseenter="this.style.backgroundColor='#f8fafc';" onmouseleave="this.style.backgroundColor='transparent';">
              <td style="padding: 6px 10px; white-space: nowrap;">${codeCol}</td>
              <td style="padding: 6px 10px;">
                <strong style="color:#0f172a; font-size:0.8125rem;">${escapeHtml(r.item_name)}</strong>
                ${r.specifications ? `<div style="font-size:0.74rem; color:#64748b; margin-top: 1px;">${escapeHtml(r.specifications)}</div>` : ''}
              </td>
              <td style="padding: 6px 10px; font-size:0.78rem; color:#475569;">${escapeHtml(r.uom || 'Nos')}</td>
              <td style="padding: 6px 10px; font-size:0.75rem; color:#64748b; white-space: nowrap;">${r.created_at ? r.created_at.substring(0, 16) : '-'}</td>
              <td style="padding: 6px 10px; white-space: nowrap;">${statusBadge}</td>
              <td style="padding: 6px 10px; text-align: right; white-space: nowrap;">${actionCol}</td>
            </tr>
          `;
        }).join('');
      }
    } else {
      if (cardEl) cardEl.style.display = 'block';
    }
  } catch (err) {
    console.warn('loadStationRequests error:', err);
  }
}

window.loadStationInventory = loadStationInventory;
window.loadStationRequests = loadStationRequests;

// Import to Station Modal
function openImportModal(masterItemId, code, encodedName) {
  if (!masterItemId || Number(masterItemId) <= 0) {
    App.toast('This code has not been added to the Master Database yet. Only Master Added (Orange) codes can be added to power stations.', 'warning');
    return;
  }
  const name = decodeURIComponent(encodedName);
  document.getElementById('modalImportItemId').value = masterItemId;
  document.getElementById('modalImportCodeLabel').textContent = code;
  document.getElementById('modalImportNameLabel').textContent = name;
  window.openModal('importStationModal');
}

window.openImportModal = openImportModal;

async function executeImportToStation(e) {
  e.preventDefault();
  const form = e.target;
  try {
    const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
    const formData = new FormData(form);
    formData.append('action', 'import_to_station');

    const res = await App.request('/api/code_management.php', {
      method: 'POST',
      body: formData
    });

    if (res.ok && res.data?.success) {
      App.toast(res.data.message || 'Item added to Power Station Code Table successfully!', 'success');
      window.closeModal('importStationModal');
      if (typeof onCodeGenTier3Change === 'function') onCodeGenTier3Change();
      if (typeof loadStationInventory === 'function') loadStationInventory();
      if (typeof loadStationRequests === 'function') loadStationRequests();
      if (typeof window.updateSimpleCode === 'function') window.updateSimpleCode();
    } else {
      App.toast(res.data?.message || 'Failed to import item to station.', 'error');
    }
  } catch (err) {
    App.toast(`Error: ${err.message}`, 'error');
  }
}

window.executeImportToStation = executeImportToStation;

// Admin Code Approval Queue // Admin Code Approval Queue Loaders with Live Title & Description Search
let _adminConsoleReqSearchDebounce = null;

window.onAdminConsoleReqSearchInput = function () {
  clearTimeout(_adminConsoleReqSearchDebounce);
  _adminConsoleReqSearchDebounce = setTimeout(() => {
    filterAndRenderAdminRequestsQueue();
  }, 150);
};

window.clearAdminConsoleReqSearch = function () {
  const input = document.getElementById('adminConsoleReqSearchInput');
  if (input) {
    input.value = '';
    filterAndRenderAdminRequestsQueue();
    input.focus();
  }
};

window.filterAndRenderAdminRequestsQueue = function () {
  const deskBody = document.getElementById('adminRequestsQueueBody');
  const consoleBody = document.getElementById('adminConsoleRequestsQueueBody');
  const targetBodies = [deskBody, consoleBody].filter(Boolean);
  if (targetBodies.length === 0) return;

  const countEl = document.getElementById('adminConsoleReqCount');
  const searchInput = document.getElementById('adminConsoleReqSearchInput');
  const allRequests = (window._cachedAdminRequests || []).filter(r => Number(r.origin_station_id) > 0);
  const search = (searchInput?.value || '').trim().toLowerCase();

  let filtered = allRequests;
  if (search) {
    const searchWords = search.split(/\s+/).filter(Boolean);
    filtered = allRequests.filter(r => {
      const title = (r.item_name || '').toLowerCase();
      const origDesc = (r.orig_description || '').toLowerCase();
      const refNo = (r.request_no || '').toLowerCase();
      const stName = (r.station_name || '').toLowerCase();
      const stCode = (r.station_code || '').toLowerCase();
      const uom = (r.uom || '').toLowerCase();
      const requester = (r.requester_name || r.requester_username || '').toLowerCase();
      const code = `${String(r.tier1).padStart(2, '0')}-${String(r.tier2).padStart(2, '0')}-${String(r.tier3).padStart(2, '0')}`;
      const combined = `${title} ${origDesc} ${refNo} ${stName} ${stCode} ${uom} ${requester} ${code}`;
      return searchWords.every(w => combined.includes(w));
    });
  }

  // Update dynamic count indicator
  if (countEl) {
    if (search) {
      countEl.innerHTML = `Found <strong style="color: #0284c7;">${filtered.length}</strong> of ${allRequests.length} requests matching "<strong>${escapeHtml(search)}</strong>"`;
    } else {
      countEl.textContent = `Showing all ${allRequests.length} requests in current queue`;
    }
  }

  if (filtered.length === 0) {
    const emptyMsg = search
      ? `<tr><td colspan="8" style="text-align:center; padding:24px; color:#64748b; font-size:0.84rem;">No requests match your search "<strong>${escapeHtml(search)}</strong>".</td></tr>`
      : `<tr><td colspan="8" style="text-align:center; padding:20px; color:#64748b; font-size:0.8125rem;">No requests currently in this queue.</td></tr>`;
    targetBodies.forEach(b => { b.innerHTML = emptyMsg; });
    return;
  }

  const isCodeAdmin = Boolean(window.APP_DATA?.isCodeAdmin || window.APP_DATA?.userRole === 'code_admin');

  const renderHtml = filtered.map(r => {
    const isMasterCommitted = Boolean(Number(r.is_added_to_master) > 0 && Number(r.generated_master_item_id || 0) > 0);
    const isStagedApproved = Boolean(r.status === 'APPROVED' && !isMasterCommitted);
    const isPending = r.status === 'PENDING';

    let actionBtns = '';
    if (isPending) {
      actionBtns = `
        <div style="display:inline-flex; gap:4px; align-items:center;">
          <button type="button" class="btn btn-sm"
            style="font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 3px; background: #650b2b; color: #ffffff; border: 1px solid #4a071e; cursor: pointer; white-space: nowrap;"
            onclick="openReviewRequestModal(${r.id});">
            Review &amp; Approve
          </button>
          <button type="button" class="btn btn-sm"
            style="font-size: 0.72rem; font-weight: 700; padding: 2px 7px; border-radius: 3px; background: #f0f9ff; color: #0284c7; border: 1px solid #bae6fd; cursor: pointer; white-space: nowrap;"
            onclick="openEditCodeRequestModal(${r.id});"
            title="Edit pending request">
            ✏️
          </button>
        </div>
      `;
    } else if (isStagedApproved) {
      // Code is already approved in staging - show only the last step: + Add to Master!
      actionBtns = `
        <button type="button" class="btn btn-sm"
          onclick="executeDirectAddToMaster(${r.id});"
          style="font-size: 0.72rem; font-weight: 700; padding: 2px 10px; border-radius: 3px; background: #ea580c; color: #ffffff; border: 1px solid #c2410c; cursor: pointer; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;"
          title="Code already approved. Click final step to commit into Central Master Database">
          + Add to Master
        </button>
      `;
    } else if (r.status === 'APPROVED' && isMasterCommitted) {
      actionBtns = `
        <span style="font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 3px; display: inline-block; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; white-space: nowrap;">
          ✓ IN MASTER
        </span>
      `;
    } else if (r.status === 'REJECTED') {
      actionBtns = `
        <span style="font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 3px; display: inline-block; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; white-space: nowrap;">
          REJECTED
        </span>
      `;
    } else {
      actionBtns = `<span style="font-size: 0.72rem; font-weight: 700; padding: 1px 6px; border-radius: 3px; display: inline-block; background: #f1f5f9; color: #475569;">${escapeHtml(r.status)}</span>`;
    }

    const stagedBadge = isStagedApproved
      ? `<span style="font-size: 0.68rem; font-weight: 700; color: #0369a1; background: #e0f2fe; border: 1px solid #bae6fd; padding: 1px 5px; border-radius: 3px; white-space: nowrap;">STAGED (PENDING MASTER)</span>`
      : '';

    const googleSearchBtn = isCodeAdmin
      ? `<button type="button" class="btn btn-sm btn-search-globe"
           data-query="${escapeHtml(r.item_name)}"
           onclick="openGoogleSearch(this, event);"
           style="font-size: 0.72rem; font-weight: 700; padding: 1px 6px; border-radius: 3px; background: #f0f9ff; color: #0284c7; border: 1px solid #bae6fd; cursor: pointer; display: inline-flex; align-items: center; gap: 2px;"
           title="Search '${escapeHtml(r.item_name)}' on Google">
           🌐 Google
         </button>`
      : '';

    return `
        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;"
            onmouseenter="this.style.backgroundColor='#f8fafc';" onmouseleave="this.style.backgroundColor='transparent';">
          <td style="padding: 4px 8px; white-space: nowrap;">
            <span style="font-family: monospace; font-size: 0.78rem; font-weight: 700; color: #475569; background: #f8fafc; border: 1px solid #cbd5e1; padding: 1px 6px; border-radius: 3px;">${escapeHtml(r.request_no)}</span>
          </td>
          <td style="padding: 4px 8px; white-space: nowrap;">
            <span style="font-family: monospace; font-size: 0.8rem; font-weight: 700; color: #0f172a;">${String(r.tier1).padStart(2, '0')}-${String(r.tier2).padStart(2, '0')}-${String(r.tier3).padStart(2, '0')}</span>
          </td>
          <td style="padding: 4px 8px; white-space: nowrap; font-family: monospace; color: #0284c7; font-weight: 700; font-size: 0.8rem;">
            ${String(r.assigned_tier4_seq !== null && r.assigned_tier4_seq !== undefined ? r.assigned_tier4_seq : (r.suggested_tier4_seq !== null && r.suggested_tier4_seq !== undefined ? r.suggested_tier4_seq : (r.tier4_seq !== null && r.tier4_seq !== undefined ? r.tier4_seq : 0))).padStart(3, '0')}
          </td>
          <td style="padding: 4px 8px;">
            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
              <strong style="color: #0f172a; font-size: 0.8125rem;">${escapeHtml(r.item_name)}</strong>
              ${stagedBadge}
              ${googleSearchBtn}
            </div>
          </td>
          <td style="padding: 4px 8px; font-size: 0.78rem; color: #475569;">${escapeHtml(r.uom || 'Nos')}</td>
          <td style="padding: 4px 8px; white-space: nowrap;">
            <strong style="color: #650b2b; font-size: 0.8rem;">${escapeHtml(r.station_name || 'Station ' + r.origin_station_id)}</strong>
          </td>
          <td style="padding: 4px 8px; font-size: 0.78rem; color: #475569; white-space: nowrap;">
            ${escapeHtml(r.requester_name || r.requester_username || 'Staff')}
          </td>
          <td style="padding: 4px 8px; text-align: right; white-space: nowrap;">${actionBtns}</td>
        </tr>
      `;
  }).join('');

  targetBodies.forEach(b => {
    b.innerHTML = renderHtml;
  });
};

async function loadAdminRequestsQueue() {
  const deskBody = document.getElementById('adminRequestsQueueBody');
  const consoleBody = document.getElementById('adminConsoleRequestsQueueBody');
  const targetBodies = [deskBody, consoleBody].filter(Boolean);
  if (targetBodies.length === 0) return;

  const filterStatus = document.getElementById('adminFilterStatus')?.value || document.getElementById('adminConsoleReqFilter')?.value || 'PENDING';

  targetBodies.forEach(b => {
    b.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:18px; color:#64748b; font-size:0.8125rem;">Loading request queue...</td></tr>`;
  });

  try {
    const res = await App.request(`/api/code_management.php?action=list_requests&status=${filterStatus}`);

    if (res.ok && res.data?.requests) {
      const requests = res.data.requests;
      window._stationRequestsMap = window._stationRequestsMap || {};
      requests.forEach(r => {
        window._stationRequestsMap[r.id] = r;
      });

      const pendingCount = requests.filter(r => r.status === 'PENDING' && Number(r.origin_station_id) > 0).length;
      const deskBadge = document.getElementById('adminDeskBadge');
      if (deskBadge) {
        deskBadge.textContent = pendingCount;
        deskBadge.style.display = pendingCount > 0 ? 'inline-block' : 'none';
      }

      window._cachedAdminRequests = requests;
      filterAndRenderAdminRequestsQueue();
    }
  } catch (err) {
    targetBodies.forEach(b => {
      b.innerHTML = `<tr><td colspan="8" style="text-align:center; color:#dc2626; padding:18px; font-size:0.8125rem;">Failed to load queue: ${err.message}</td></tr>`;
    });
  }
}

window.loadAdminRequestsQueue = loadAdminRequestsQueue;

async function openReviewRequestModal(reqOrId) {
  let req = null;
  if (typeof reqOrId === 'object' && reqOrId !== null) {
    req = reqOrId;
  } else if (window._cachedAdminRequests && Array.isArray(window._cachedAdminRequests)) {
    req = window._cachedAdminRequests.find(r => Number(r.id) === Number(reqOrId));
  }

  // If not found in cache, fetch immediately from server
  if (!req && reqOrId) {
    try {
      const res = await App.request('/api/code_management.php?action=list_requests');
      if (res.ok && res.data?.requests) {
        window._cachedAdminRequests = res.data.requests;
        req = window._cachedAdminRequests.find(r => Number(r.id) === Number(reqOrId));
      }
    } catch (e) {
      console.warn('Failed to fetch request details:', e);
    }
  }

  if (!req) {
    App.toast('Could not find request details. Please refresh the page.', 'error');
    return;
  }

  currentActiveReqData = req;
  window.openModal('reviewCodeRequestModal');

  const reqIdEl = document.getElementById('modalReviewReqId');
  const reqRefEl = document.getElementById('modalReqRefTitle');
  const branchEl = document.getElementById('modalBranchLabel');
  const requesterEl = document.getElementById('modalRequesterMeta');
  const itemNameEl = document.getElementById('modalReviewItemName');
  const specsEl = document.getElementById('modalReviewSpecs');
  const uomEl = document.getElementById('modalReviewUom');
  const stationEl = document.getElementById('modalReviewStation');
  const remarksEl = document.getElementById('modalReviewRemarks');
  const branchPrefixEl = document.getElementById('modalReviewBranchPrefix');
  const assignedSeqInput = document.getElementById('modalReviewAssignedSeq');

  if (reqIdEl) reqIdEl.value = req.id || '';
  if (reqRefEl) reqRefEl.textContent = req.request_no || '';
  if (branchEl) branchEl.textContent = `Tier 1 (${String(req.tier1).padStart(2, '0')}) • Tier 2 (${String(req.tier2).padStart(2, '0')}) • Tier 3 (${String(req.tier3).padStart(2, '0')})`;
  if (requesterEl) requesterEl.textContent = `Requested by ${req.requester_name || req.requester_username || 'Staff'} • ${req.station_name || 'Origin Station'}`;

  const prefixStr = `${req.tier1}-${String(req.tier2).padStart(2, '0')}-${String(req.tier3).padStart(2, '0')}-`;
  if (branchPrefixEl) branchPrefixEl.textContent = prefixStr;

  let initialSeq = req.assigned_tier4_seq !== null && req.assigned_tier4_seq !== undefined ? Number(req.assigned_tier4_seq) : (req.suggested_tier4_seq !== null && req.suggested_tier4_seq !== undefined ? Number(req.suggested_tier4_seq) : 0);

  if (assignedSeqInput) {
    assignedSeqInput.value = initialSeq;
  }

  // Live query next sequence for the branch if not assigned
  if (req.assigned_tier4_seq === null || req.assigned_tier4_seq === undefined) {
    try {
      const metaBase = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
      const availRes = await App.request(`/api/code_management.php?action=check_availability&tier1=${req.tier1}&tier2=${req.tier2}&tier3=${req.tier3}`);
      if (availRes.ok && availRes.data && availRes.data.next_sequence !== undefined) {
        initialSeq = Number(availRes.data.next_sequence);
        if (assignedSeqInput) assignedSeqInput.value = initialSeq;
      }
    } catch (seqErr) {
      console.warn('Seq check error:', seqErr);
    }
  }

  updateReviewComputedCodePreview();

  const combinedName = (req.specifications && !req.item_name.includes(req.specifications))
    ? `${req.item_name} ${req.specifications}`.trim()
    : (req.item_name || '');
  if (itemNameEl) itemNameEl.value = combinedName.toUpperCase();
  if (specsEl) specsEl.value = '';
  if (uomEl) uomEl.value = (req.uom || 'NOS').toUpperCase();
  if (stationEl) stationEl.value = req.station_name || 'Origin Station';
  if (remarksEl) remarksEl.value = 'Approved and committed to FIFO Master Database.';
}

function updateReviewComputedCodePreview() {
  if (!currentActiveReqData) return;
  const assignedSeqInput = document.getElementById('modalReviewAssignedSeq');
  const computedCodeEl = document.getElementById('modalComputedCode');
  const seqVal = assignedSeqInput ? parseInt(assignedSeqInput.value, 10) : 0;
  const validSeq = isNaN(seqVal) || seqVal < 0 ? 0 : seqVal;
  const seqFormatted = String(validSeq).padStart(3, '0');
  if (computedCodeEl) {
    computedCodeEl.textContent = `${currentActiveReqData.tier1}-${String(currentActiveReqData.tier2).padStart(2, '0')}-${String(currentActiveReqData.tier3).padStart(2, '0')}-${seqFormatted}`;
  }
}

window.updateReviewComputedCodePreview = updateReviewComputedCodePreview;
window.openReviewRequestModal = openReviewRequestModal;

async function executeApproveCodeRequest(e) {
  if (e) e.preventDefault();
  const form = document.getElementById('reviewCodeRequestForm');
  const btn = document.getElementById('btnConfirmApproval');
  if (btn) btn.disabled = true;

  try {
    const formData = new FormData(form);
    formData.append('action', 'approve_request');
    formData.append('add_to_master', '1');

    const res = await App.request('/api/code_management.php', {
      method: 'POST',
      body: formData
    });

    if (res.ok && res.data?.success) {
      App.toast(`Code ${res.data.raw_code || ''} officially added to FIFO Master Database!`, 'success');
      window.closeModal('reviewCodeRequestModal');
      if (typeof loadAdminRequestsQueue === 'function') loadAdminRequestsQueue();
      if (typeof reloadMasterCodesTable === 'function') reloadMasterCodesTable();
      if (typeof onCodeGenTier3Change === 'function') onCodeGenTier3Change();
      if (typeof loadStationInventory === 'function') loadStationInventory();
      if (typeof loadStationRequests === 'function') loadStationRequests();
      if (typeof window.updateSimpleCode === 'function') window.updateSimpleCode();
    } else {
      App.toast(res.data?.message || 'Failed to approve request.', 'error');
    }
  } catch (err) {
    App.toast(`Error: ${err.message}`, 'error');
  } finally {
    if (btn) btn.disabled = false;
  }
}

async function executeApproveOnly(e) {
  if (e) e.preventDefault();
  const form = document.getElementById('reviewCodeRequestForm');
  if (!form) return;

  try {
    const formData = new FormData(form);
    formData.append('action', 'approve_request');
    formData.append('add_to_master', '0');

    const res = await App.request('/api/code_management.php', {
      method: 'POST',
      body: formData
    });

    if (res.ok && res.data?.success) {
      App.toast(`Request approved in staging! Allocated Code: ${res.data.raw_code || ''}. You can click '+ Add to Master' anytime.`, 'success');
      window.closeModal('reviewCodeRequestModal');
      if (typeof loadAdminRequestsQueue === 'function') loadAdminRequestsQueue();
      if (typeof reloadMasterCodesTable === 'function') reloadMasterCodesTable();
      if (typeof onCodeGenTier3Change === 'function') onCodeGenTier3Change();
      if (typeof loadStationInventory === 'function') loadStationInventory();
      if (typeof loadStationRequests === 'function') loadStationRequests();
    } else {
      App.toast(res.data?.message || 'Failed to approve request.', 'error');
    }
  } catch (err) {
    App.toast(`Error: ${err.message}`, 'error');
  }
}

window.executeApproveCodeRequest = executeApproveCodeRequest;
window.executeApproveOnly = executeApproveOnly;

async function executeRejectCodeRequest() {
  if (!currentActiveReqData) return;
  const reason = prompt('Please enter the reason for rejection (mandatory):', 'Item specifications incomplete or duplicate found.');
  if (!reason || !reason.trim()) return;

  try {
    const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
    const formData = new FormData();
    formData.append('action', 'reject_request');
    formData.append('_csrf_token', window.APP_DATA?.csrfToken || '');
    formData.append('request_id', currentActiveReqData.id);
    formData.append('remarks', reason.trim());

    const res = await App.request('/api/code_management.php', {
      method: 'POST',
      body: formData
    });

    if (res.ok && res.data?.success) {
      App.toast('Request rejected successfully.', 'success');
      window.closeModal('reviewCodeRequestModal');
      loadAdminRequestsQueue();
    } else {
      App.toast(res.data?.message || 'Failed to reject request.', 'error');
    }
  } catch (err) {
    App.toast(`Error: ${err.message}`, 'error');
  }
}

window.executeRejectCodeRequest = executeRejectCodeRequest;

function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/**
 * ============================================================================
 * Simple 4-Box User Code Tab Handler (Sequential 1st -> 2nd -> 3rd -> 4th Choice)
 * ============================================================================
 */
let simpleCodeDebounce = null;

window.onSimpleCodeSearchFocus = function () {
  const searchInput = document.getElementById('simpleCodeSearchInput');
  if (searchInput && searchInput.value.trim().length > 0) {
    updateSimpleCode();
  }
};

window.onSimpleCodeSearchInput = function () {
  const searchInput = document.getElementById('simpleCodeSearchInput');
  const clearBtn = document.getElementById('clearSimpleSearchBtn');
  if (clearBtn) {
    clearBtn.style.display = (searchInput && searchInput.value.trim().length > 0) ? 'block' : 'none';
  }
  updateSimpleCode();
};

window.clearSimpleCodeSearch = function () {
  const searchInput = document.getElementById('simpleCodeSearchInput');
  const clearBtn = document.getElementById('clearSimpleSearchBtn');
  const dropdown = document.getElementById('simpleSearchResultsDropdown');
  if (searchInput) {
    searchInput.value = '';
    searchInput.focus();
  }
  if (clearBtn) {
    clearBtn.style.display = 'none';
  }
  if (dropdown) {
    dropdown.style.display = 'none';
    dropdown.innerHTML = '';
  }
  updateSimpleCode();
};

window.selectSearchResultItem = function (id, tier1, tier2, tier3, tier4_seq, formatted_code, itemNameEncoded) {
  const itemName = decodeURIComponent(itemNameEncoded || '');
  const t1El = document.getElementById('simpleTier1');
  const t2El = document.getElementById('simpleTier2');
  const t3El = document.getElementById('simpleTier3');
  const t4El = document.getElementById('simpleTier4');
  const searchInput = document.getElementById('simpleCodeSearchInput');
  const dropdown = document.getElementById('simpleSearchResultsDropdown');

  if (dropdown) {
    dropdown.style.display = 'none';
  }

  // 1. Set 1st dropdown
  if (t1El) {
    t1El.value = tier1;
    window.onSimpleTier1Change();
  }

  // 2. Set 2nd dropdown
  if (t2El) {
    t2El.value = tier2;
    window.onSimpleTier2Change();
  }

  // 3. Set 3rd dropdown
  if (t3El) {
    t3El.value = tier3;
    window.onSimpleTier3Change();
  }

  // 4. Set 4th sequence input
  if (t4El) {
    t4El.value = String(tier4_seq || 0).padStart(3, '0');
  }

  // 5. Update search input
  if (searchInput) {
    searchInput.value = `${formatted_code} - ${itemName}`;
    const clearBtn = document.getElementById('clearSimpleSearchBtn');
    if (clearBtn) clearBtn.style.display = 'block';
  }

  // 6. Update breadcrumb banner
  updateSimpleCode();
  App.toast(`Selected: ${formatted_code} - ${itemName}`, 'info');
};

window.directRequestStagedCode = async function (requestId, tier1, tier2, tier3, seq, formattedCode, encodedName, encodedUom) {
  const itemName = decodeURIComponent(encodedName || '');
  const uom = decodeURIComponent(encodedUom || 'NOS');
  const userStationName = window.APP_DATA?.userStationName || 'Your Station';

  if (!confirm(`Do you want to directly request approved code ${formattedCode} ("${itemName}") for ${userStationName}?`)) {
    return;
  }

  try {
    const metaBase = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || (window.APP_DATA?.csrfToken || '');
    const userStationId = window.APP_DATA?.userStationId || '';

    const formData = new FormData();
    formData.append('action', 'create_request');
    formData.append('tier1', tier1);
    formData.append('tier2', tier2);
    formData.append('tier3', tier3);
    formData.append('suggested_tier4_seq', seq);
    formData.append('item_name', itemName);
    formData.append('uom', uom);
    formData.append('referenced_request_id', requestId || '');
    if (userStationId) formData.append('origin_station_id', userStationId);
    if (csrfToken) formData.append('_csrf_token', csrfToken);

    const res = await App.request('/api/code_management.php', {
      method: 'POST',
      body: formData
    });

    if (res.ok && (res.data?.success || res.data?.id)) {
      App.toast(res.data.message || `Request for approved code ${formattedCode} submitted successfully! Code Admin can now add it to Master.`, 'success');
      const dropdown = document.getElementById('simpleSearchResultsDropdown');
      if (dropdown) dropdown.style.display = 'none';
      if (typeof loadStationRequests === 'function') loadStationRequests();
      if (typeof updateSimpleCode === 'function') updateSimpleCode();
      if (typeof loadAdminRequestsQueue === 'function') loadAdminRequestsQueue();
    } else {
      App.toast(res.data?.message || 'Failed to submit code request.', 'error');
    }
  } catch (err) {
    App.toast(`Error requesting code: ${err.message}`, 'error');
  }
};

function renderSearchResultsDropdown(items, searchQ) {
  const dropdown = document.getElementById('simpleSearchResultsDropdown');
  if (!dropdown) return;

  if (!searchQ) {
    dropdown.style.display = 'none';
    dropdown.innerHTML = '';
    return;
  }

  const tiers = window.APP_DATA?.tierHierarchy || { tier1: [], tier2: {}, tier3: {} };

  if (items.length === 0) {
    dropdown.innerHTML = `
      <div style="padding: 16px 20px; text-align: center; color: #64748b; font-size: 0.88rem;">
        <div style="font-weight: 700; color: #0f172a; margin-bottom: 4px;">No Master Items Found</div>
        <div>No registered codes match "<strong>${escapeHtml(searchQ)}</strong>". You can create a request using the 1st, 2nd, 3rd, 4th boxes below.</div>
      </div>
    `;
    dropdown.style.display = 'block';
    return;
  }

  const headerHtml = `
    <div style="padding: 8px 14px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 0.78rem; font-weight: 700; color: #475569; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 2;">
      <span>MATCHING MASTER CODES (${items.length})</span>
      <button type="button" onclick="document.getElementById('simpleSearchResultsDropdown').style.display='none';" style="background: transparent; border: none; font-size: 1.1rem; color: #94a3b8; cursor: pointer; line-height: 1;" title="Close">&times;</button>
    </div>
  `;

  const itemsHtml = items.map(it => {
    const isInStation = Number(it.is_in_station) > 0;
    const isStaged = Boolean(Number(it.is_staged) > 0 || (it.status === 'APPROVED' && !Number(it.is_added_to_master)));

    // Resolve full hierarchy path for this item
    const t1Obj = (tiers.tier1 || []).find(t => Number(t.code_val) === Number(it.tier1));
    let t2Obj = null;
    if (t1Obj && tiers.tier2 && tiers.tier2[t1Obj.id]) {
      t2Obj = tiers.tier2[t1Obj.id].find(t => Number(t.code_val) === Number(it.tier2));
    }
    let t3Obj = null;
    if (t2Obj && tiers.tier3 && tiers.tier3[t2Obj.id]) {
      t3Obj = tiers.tier3[t2Obj.id].find(t => Number(t.code_val) === Number(it.tier3));
    }

    const pathParts = [];
    if (t1Obj) pathParts.push(escapeHtml(t1Obj.name));
    if (t2Obj) pathParts.push(escapeHtml(t2Obj.name));
    if (t3Obj) pathParts.push(escapeHtml(t3Obj.name));
    const pathStr = pathParts.join(' &gt; ');

    let actionBadge = '';
    if (isInStation) {
      actionBadge = `<span style="font-size: 0.76rem; color: #166534; font-weight: 700; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 3px 8px; border-radius: 3px; white-space: nowrap;">✓ In Station</span>`;
    } else if (isStaged) {
      actionBadge = `
        <div style="display: flex; align-items: center; gap: 6px;">
          <span style="font-size: 0.7rem; font-weight: 700; color: #0369a1; background: #e0f2fe; border: 1px solid #bae6fd; padding: 2px 6px; border-radius: 3px; white-space: nowrap;">STAGED (PENDING MASTER)</span>
          <button type="button" class="btn btn-sm" onclick="event.stopPropagation(); directRequestStagedCode(${Number(it.request_id || it.id || 0)}, ${it.tier1}, ${it.tier2}, ${it.tier3}, ${it.tier4_seq}, '${escapeHtml(it.formatted_code)}', '${encodeURIComponent(it.item_name)}', '${encodeURIComponent(it.uom || 'NOS')}');"
            style="font-size: 0.74rem; font-weight: 700; padding: 3px 10px; border-radius: 3px; background: #0284c7; color: #ffffff; border: 1px solid #0369a1; cursor: pointer; white-space: nowrap;" title="Directly request this approved code for your station">
            Request Code
          </button>
        </div>
      `;
    } else {
      actionBadge = `
        <button type="button" class="btn btn-primary btn-sm" onclick="event.stopPropagation(); openImportModal(${it.id}, '${it.formatted_code}', '${encodeURIComponent(it.item_name)}')"
          style="font-size: 0.76rem; font-weight: 700; padding: 3px 10px; border-radius: 3px; background: linear-gradient(180deg, #8ba5c4 0%, #617d9e 100%); color: #ffffff; border: 1px solid #486585; cursor: pointer; white-space: nowrap;">
          + Add Code
        </button>
      `;
    }

    return `
      <div class="search-result-row"
        onclick="selectSearchResultItem(${it.id}, ${it.tier1}, ${it.tier2}, ${it.tier3}, ${it.tier4_seq}, '${escapeHtml(it.formatted_code)}', '${encodeURIComponent(it.item_name)}');"
        style="padding: 10px 14px; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background 0.15s ease;"
        onmouseenter="this.style.background='#f8fafc';" onmouseleave="this.style.background='#ffffff';">
        
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 4px;">
          <div>
            <span style="font-family: monospace; font-size: 0.95rem; font-weight: 800; color: #650b2b; background: #fdf2f5; border: 1px solid #fbcfe8; padding: 2px 8px; border-radius: 3px;">
              ${escapeHtml(it.formatted_code)}
            </span>
            <span style="font-size: 0.82rem; font-family: monospace; color: #475569; margin-left: 8px; font-weight: 600;">
              Raw: ${escapeHtml(it.raw_code || '-')}
            </span>
          </div>
          <div>${actionBadge}</div>
        </div>

        <div style="font-size: 0.94rem; font-weight: 700; color: #0f172a; margin-bottom: 3px;">
          ${escapeHtml(it.item_name)}
        </div>

        ${it.specifications ? `<div style="font-size: 0.82rem; color: #475569; margin-bottom: 4px; line-height: 1.3;"><strong>Specs:</strong> ${escapeHtml(it.specifications)}</div>` : ''}

        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.76rem; color: #64748b; margin-top: 4px;">
          <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 80%;">
            <span style="color: #94a3b8; font-weight: 600;">Category:</span> ${pathStr || '-'}
          </div>
          <div>
            <span style="background: #e2e8f0; padding: 2px 7px; border-radius: 2px; font-weight: 600; color: #334155;">UoM: ${escapeHtml(it.uom || 'Nos')}</span>
          </div>
        </div>

      </div>
    `;
  }).join('');

  dropdown.innerHTML = headerHtml + itemsHtml;
  dropdown.style.display = 'block';
}

// Global click outside to dismiss search results dropdown
document.addEventListener('click', function (e) {
  if (!e.target.closest('#simpleCodeSearchInput') && !e.target.closest('#simpleSearchResultsDropdown')) {
    const dropdown = document.getElementById('simpleSearchResultsDropdown');
    if (dropdown) dropdown.style.display = 'none';
  }
});

window.resetSimpleCodeForm = function () {
  const searchInput = document.getElementById('simpleCodeSearchInput');
  const clearBtn = document.getElementById('clearSimpleSearchBtn');
  const dropdown = document.getElementById('simpleSearchResultsDropdown');
  if (searchInput) searchInput.value = '';
  if (clearBtn) clearBtn.style.display = 'none';
  if (dropdown) {
    dropdown.style.display = 'none';
    dropdown.innerHTML = '';
  }

  const t1 = document.getElementById('simpleTier1');
  const t2 = document.getElementById('simpleTier2');
  const t3 = document.getElementById('simpleTier3');
  const t4 = document.getElementById('simpleTier4');
  if (t1) t1.value = '';
  if (t2) {
    t2.innerHTML = '<option value="" selected disabled>-- 2nd --</option>';
    t2.value = '';
    t2.disabled = true;
    t2.style.background = '#f1f5f9';
    t2.style.opacity = '0.6';
    t2.style.cursor = 'not-allowed';
  }
  if (t3) {
    t3.innerHTML = '<option value="" selected disabled>-- 3rd --</option>';
    t3.value = '';
    t3.disabled = true;
    t3.style.background = '#f1f5f9';
    t3.style.opacity = '0.6';
    t3.style.cursor = 'not-allowed';
  }
  if (t4) {
    t4.value = '';
    t4.disabled = true;
    t4.placeholder = '';
    t4.style.background = '#f1f5f9';
    t4.style.opacity = '0.6';
    t4.style.cursor = 'not-allowed';
  }
  const catBar = document.getElementById('simpleActiveCategoryBar');
  if (catBar) catBar.style.display = 'none';
  updateSimpleCode();
};

window.selectExplorerTier1 = function (val) {
  const t1 = document.getElementById('simpleTier1');
  if (t1) {
    t1.value = val;
    window.onSimpleTier1Change();
  }
};

window.selectExplorerTier2 = function (val) {
  const t2 = document.getElementById('simpleTier2');
  if (t2) {
    t2.value = val;
    window.onSimpleTier2Change();
  }
};

window.selectExplorerTier3 = function (val) {
  const t3 = document.getElementById('simpleTier3');
  if (t3) {
    t3.value = val;
    window.onSimpleTier3Change();
  }
};

window.onSimpleTier1Change = function () {
  const t1El = document.getElementById('simpleTier1');
  const t2El = document.getElementById('simpleTier2');
  const t3El = document.getElementById('simpleTier3');
  const t4El = document.getElementById('simpleTier4');

  if (!t1El || !t1El.value) return;

  // Enable & populate 2nd dropdown with numbers only
  if (t2El) {
    t2El.innerHTML = '<option value="" selected disabled>-- 2nd --</option>';
    const tiers = window.APP_DATA?.tierHierarchy || { tier1: [], tier2: {}, tier3: {} };
    const t1Obj = (tiers.tier1 || []).find(t => Number(t.code_val) === Number(t1El.value));
    const t2Children = (t1Obj && tiers.tier2 && tiers.tier2[t1Obj.id]) ? tiers.tier2[t1Obj.id] : [];

    if (t2Children.length > 0) {
      t2Children.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.code_val;
        opt.textContent = String(item.code_val).padStart(2, '0') + (item.name ? ' - ' + item.name : '');
        t2El.appendChild(opt);
      });
    } else {
      for (let i = 1; i <= 19; i++) {
        const opt = document.createElement('option');
        opt.value = i;
        opt.textContent = String(i).padStart(2, '0');
        t2El.appendChild(opt);
      }
    }
    t2El.disabled = false;
    t2El.value = '';
    t2El.style.background = '#ffffff';
    t2El.style.opacity = '1';
    t2El.style.cursor = 'pointer';
  }

  // Reset & Disable 3rd and 4th
  if (t3El) {
    t3El.innerHTML = '<option value="" selected disabled>-- 3rd --</option>';
    t3El.disabled = true;
    t3El.value = '';
    t3El.style.background = '#f1f5f9';
    t3El.style.opacity = '0.6';
    t3El.style.cursor = 'not-allowed';
  }
  if (t4El) {
    t4El.disabled = true;
    t4El.value = '';
    t4El.placeholder = '';
    t4El.style.background = '#f1f5f9';
    t4El.style.opacity = '0.6';
    t4El.style.cursor = 'not-allowed';
  }

  updateSimpleCode();
};

window.onSimpleTier2Change = function () {
  const t1El = document.getElementById('simpleTier1');
  const t2El = document.getElementById('simpleTier2');
  const t3El = document.getElementById('simpleTier3');
  const t4El = document.getElementById('simpleTier4');

  if (!t2El || !t2El.value) return;

  // Enable & populate 3rd dropdown with numbers only
  if (t3El) {
    t3El.innerHTML = '<option value="" selected disabled>-- 3rd --</option>';
    const tiers = window.APP_DATA?.tierHierarchy || { tier1: [], tier2: {}, tier3: {} };
    const t1Obj = (tiers.tier1 || []).find(t => Number(t.code_val) === Number(t1El ? t1El.value : 1));
    const t2Children = (t1Obj && tiers.tier2 && tiers.tier2[t1Obj.id]) ? tiers.tier2[t1Obj.id] : [];
    const t2Obj = t2Children.find(t => Number(t.code_val) === Number(t2El.value));
    const t3Children = (t2Obj && tiers.tier3 && tiers.tier3[t2Obj.id]) ? tiers.tier3[t2Obj.id] : [];

    if (t3Children.length > 0) {
      t3Children.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.code_val;
        opt.textContent = String(item.code_val).padStart(2, '0') + (item.name ? ' - ' + item.name : '');
        t3El.appendChild(opt);
      });
    } else {
      for (let i = 1; i <= 19; i++) {
        const opt = document.createElement('option');
        opt.value = i;
        opt.textContent = String(i).padStart(2, '0');
        t3El.appendChild(opt);
      }
    }
    t3El.disabled = false;
    t3El.value = '';
    t3El.style.background = '#ffffff';
    t3El.style.opacity = '1';
    t3El.style.cursor = 'pointer';
  }

  // Reset & Disable 4th
  if (t4El) {
    t4El.disabled = true;
    t4El.value = '';
    t4El.placeholder = '';
    t4El.style.background = '#f1f5f9';
    t4El.style.opacity = '0.6';
    t4El.style.cursor = 'not-allowed';
  }

  updateSimpleCode();
};

window.onSimpleTier3Change = function () {
  const t3El = document.getElementById('simpleTier3');
  if (!t3El || !t3El.value) return;
  updateSimpleCode();
};

function renderCategoryExplorer(t1, t2, t3, t4Num, items, nextSeq) {
  const container = document.getElementById('tierCategoryExplorer');
  if (container) {
    container.innerHTML = '';
    container.style.display = 'none';
  }
  return;

  const tiers = window.APP_DATA?.tierHierarchy || { tier1: [], tier2: {}, tier3: {} };
  const t1List = tiers.tier1 || [];

  const t1Obj = (t1 !== null) ? t1List.find(t => Number(t.code_val) === t1) : null;
  let t2Obj = null;
  let t2Children = [];
  if (t1Obj && tiers.tier2 && tiers.tier2[t1Obj.id]) {
    t2Children = tiers.tier2[t1Obj.id] || [];
    if (t2 !== null) {
      t2Obj = t2Children.find(t => Number(t.code_val) === t2);
    }
  }

  let t3Obj = null;
  let t3Children = [];
  if (t2Obj && tiers.tier3 && tiers.tier3[t2Obj.id]) {
    t3Children = tiers.tier3[t2Obj.id] || [];
    if (t3 !== null) {
      t3Obj = t3Children.find(t => Number(t.code_val) === t3);
    }
  }

  // 1. If 1st, 2nd, and 3rd are selected -> Show Category Breadcrumb + Registered Items (Tier 4 Table)
  if (t1 !== null && t2 !== null && t3 !== null) {
    const nextPadded = String(nextSeq || 1).padStart(3, '0');

    // Sort registered items ascending by 4th sequence: 000, 001, 002...
    const sortedItems = (items || []).slice().sort((a, b) => {
      const getSeq = (it) => {
        if (it.tier4_seq !== undefined && it.tier4_seq !== null && it.tier4_seq !== '') {
          const val = parseInt(it.tier4_seq, 10);
          if (!isNaN(val)) return val;
        }
        if (it.formatted_code) {
          const parts = String(it.formatted_code).split('-');
          const last = parseInt(parts[parts.length - 1], 10);
          if (!isNaN(last)) return last;
        }
        if (it.raw_code) {
          const last = parseInt(String(it.raw_code).slice(-3), 10);
          if (!isNaN(last)) return last;
        }
        return 0;
      };
      const seqA = getSeq(a);
      const seqB = getSeq(b);
      if (seqA !== seqB) return seqA - seqB;
      return String(a.formatted_code || '').localeCompare(String(b.formatted_code || ''));
    });

    const itemsRows = sortedItems.map(it => {
      const isInStation = Number(it.is_in_station) > 0;
      const isStaged = Boolean(Number(it.is_staged) > 0 || (it.status === 'APPROVED' && !Number(it.is_added_to_master)));
      const isCurrentSeq = (t4Num !== null && Number(it.tier4_seq) === t4Num);

      let statusBadge = '';
      let actionBtn = '';

      if (isInStation) {
        statusBadge = `<span style="font-size: 0.72rem; font-weight: 700; color: #166534; background: #dcfce7; border: 1px solid #bbf7d0; padding: 1px 6px; border-radius: 3px; display: inline-block;">✓ In Station</span>`;
        actionBtn = `<span style="font-size: 0.72rem; color: #166534; font-weight: 600;">Added</span>`;
      } else if (isStaged) {
        statusBadge = `<span style="font-size: 0.72rem; font-weight: 700; color: #0369a1; background: #e0f2fe; border: 1px solid #bae6fd; padding: 1px 6px; border-radius: 3px; display: inline-block;">STAGED (PENDING MASTER)</span>`;
        actionBtn = `
          <button type="button" class="btn btn-sm" onclick="directRequestStagedCode(${Number(it.request_id || it.id || 0)}, ${it.tier1}, ${it.tier2}, ${it.tier3}, ${it.tier4_seq}, '${escapeHtml(it.formatted_code)}', '${encodeURIComponent(it.item_name)}', '${encodeURIComponent(it.uom || 'NOS')}');"
            style="font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 3px; background: #0284c7; color: #ffffff; border: 1px solid #0369a1; cursor: pointer; white-space: nowrap;" title="Directly request this approved code for your station">
            Request Code
          </button>
        `;
      } else {
        statusBadge = `<span style="font-size: 0.72rem; color: #475569; background: #f1f5f9; border: 1px solid #e2e8f0; padding: 1px 6px; border-radius: 3px; display: inline-block;">Master Only</span>`;
        actionBtn = `
          <button type="button" class="btn btn-sm" onclick="openImportModal(${it.id}, '${it.formatted_code}', '${encodeURIComponent(it.item_name)}')"
            style="font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 3px; background: #650b2b; color: #ffffff; border: 1px solid #4a071e; cursor: pointer;">
            + Add Code
          </button>
        `;
      }

      return `
        <tr style="border-bottom: 1px solid #f1f5f9; ${isCurrentSeq ? 'background: #fdf2f5; font-weight: 600;' : ''}"
            onmouseenter="this.style.backgroundColor='${isCurrentSeq ? '#fce7f3' : '#f8fafc'}';" onmouseleave="this.style.backgroundColor='${isCurrentSeq ? '#fdf2f5' : 'transparent'}';">
          <td style="padding: 4px 8px; white-space: nowrap;">
            <span style="font-family: monospace; font-size: 0.8rem; font-weight: 700; color: #650b2b; background: #fdf2f5; border: 1px solid #fbcfe8; padding: 1px 6px; border-radius: 3px;">${escapeHtml(it.formatted_code)}</span>
          </td>
          <td style="padding: 4px 8px; white-space: nowrap;"><code style="font-size: 0.78rem; color: #475569;">${escapeHtml(it.raw_code || '-')}</code></td>
          <td style="padding: 4px 8px; font-weight: 600; color: #0f172a;">${escapeHtml(it.item_name)}</td>
          <td style="padding: 4px 8px; color: #64748b; font-size: 0.78rem;">${escapeHtml(it.specifications || '-')}</td>
          <td style="padding: 4px 8px; color: #475569; font-size: 0.78rem;">${escapeHtml(it.uom || 'Nos')}</td>
          <td style="padding: 4px 8px; white-space: nowrap;">${statusBadge}</td>
          <td style="padding: 4px 8px; text-align: right; white-space: nowrap;">${actionBtn}</td>
        </tr>
      `;
    }).join('');

    container.innerHTML = `
      <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px 12px; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px; font-size: 0.8125rem;">
        <div style="display: flex; align-items: center; gap: 6px; font-weight: 600; color: #1e293b;">
          <span style="color: #64748b; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">Active Path:</span>
          <a href="javascript:void(0)" onclick="selectExplorerTier1(${t1})" style="color: #0284c7; text-decoration: underline;">${t1Obj ? escapeHtml(t1Obj.name) : 'Group ' + t1}</a>
          <span style="color: #94a3b8;">&gt;</span>
          <a href="javascript:void(0)" onclick="selectExplorerTier2(${t2})" style="color: #0284c7; text-decoration: underline;">${t2Obj ? escapeHtml(t2Obj.name) : 'Sub-Group ' + t2}</a>
          <span style="color: #94a3b8;">&gt;</span>
          <span style="color: #650b2b;">${t3Obj ? escapeHtml(t3Obj.name) : 'Classification ' + t3}</span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
          <button type="button" onclick="selectExplorerTier2(${t2});"
            style="font-size: 0.72rem; color: #334155; background: #ffffff; border: 1px solid #cbd5e1; padding: 2px 8px; border-radius: 3px; cursor: pointer; font-weight: 600;">
            &larr; Back to Classifications
          </button>
          <div style="font-size: 0.75rem; color: #64748b;">
            Next available sequence: <strong style="color: #0f172a; font-family: monospace;">${nextPadded}</strong>
          </div>
        </div>
      </div>

      <div style="border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="padding: 7px 12px; background: #f8fafc; border-bottom: 1px solid #cbd5e1; display: flex; justify-content: space-between; align-items: center;">
          <span style="font-size: 0.84rem; font-weight: 700; color: #0f172a;">Registered Items in this Classification (${items.length})</span>
          <span style="font-size: 0.75rem; color: #64748b;">Items under ${t1}-${String(t2).padStart(2, '0')}-${String(t3).padStart(2, '0')}-xxx</span>
        </div>
        <div style="overflow-x: auto; max-height: 480px; overflow-y: auto;">
          <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
            <thead style="position: sticky; top: 0; background: #f1f5f9; z-index: 2; border-bottom: 1px solid #cbd5e1;">
              <tr>
                <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 110px;">ITEM CODE</th>
                <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 110px;">RAW CODE</th>
                <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">ITEM DESCRIPTION &amp; NAME</th>
                <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">SPECIFICATIONS</th>
                <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 70px;">UOM</th>
                <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 110px;">STATION STATUS</th>
                <th style="padding: 5px 8px; text-align: right; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 90px;">ACTION</th>
              </tr>
            </thead>
            <tbody>
              ${items.length > 0 ? itemsRows : `
                <tr>
                  <td colspan="7" style="text-align: center; padding: 20px; color: #64748b; font-size: 0.8125rem;">
                    No registered items under this classification yet. Click <strong>Request Code</strong> above to request a new item code.
                  </td>
                </tr>
              `}
            </tbody>
          </table>
        </div>
      </div>
    `;
    return;
  }

  // 2. If 1st and 2nd are selected -> Show Classifications Table (Tier 3 Table)
  if (t1 !== null && t2 !== null) {
    const t3Rows = t3Children.map(t3Item => `
      <tr onclick="selectExplorerTier3(${t3Item.code_val});" style="cursor: pointer; border-bottom: 1px solid #f1f5f9;"
          onmouseenter="this.style.backgroundColor='#f8fafc';" onmouseleave="this.style.backgroundColor='transparent';">
        <td style="padding: 4px 8px; white-space: nowrap;">
          <span style="font-family: monospace; font-size: 0.8rem; font-weight: 700; color: #650b2b; background: #fdf2f5; border: 1px solid #fbcfe8; padding: 1px 6px; border-radius: 3px;">${String(t3Item.code_val).padStart(2, '0')}</span>
        </td>
        <td style="padding: 4px 8px; font-weight: 600; color: #1e293b;">${escapeHtml(t3Item.name)}</td>
        <td style="padding: 4px 8px; font-family: monospace; color: #64748b; font-size: 0.78rem; white-space: nowrap;">${t1}-${String(t2).padStart(2, '0')}-${String(t3Item.code_val).padStart(2, '0')}-...</td>
        <td style="padding: 4px 8px; text-align: right; white-space: nowrap;">
          <button type="button" class="btn btn-sm" onclick="event.stopPropagation(); selectExplorerTier3(${t3Item.code_val});"
            style="font-size: 0.74rem; font-weight: 700; padding: 3px 10px; border-radius: 3px; background: #475569; color: #ffffff; border: 1px solid #334155; cursor: pointer; white-space: nowrap; display: inline-block;">
            Select (${t3Item.code_val})
          </button>
        </td>
      </tr>
    `).join('');

    container.innerHTML = `
      <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px 12px; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between; font-size: 0.8125rem;">
        <div style="display: flex; align-items: center; gap: 6px; font-weight: 600; color: #1e293b;">
          <span style="color: #64748b; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">Active Path:</span>
          <a href="javascript:void(0)" onclick="selectExplorerTier1(${t1})" style="color: #0284c7; text-decoration: underline;">${t1Obj ? escapeHtml(t1Obj.name) : 'Group ' + t1}</a>
          <span style="color: #94a3b8;">&gt;</span>
          <span style="color: #650b2b;">${t2Obj ? escapeHtml(t2Obj.name) : 'Sub-Group ' + t2}</span>
        </div>
        <div>
          <button type="button" onclick="selectExplorerTier1(${t1});"
            style="font-size: 0.72rem; color: #334155; background: #ffffff; border: 1px solid #cbd5e1; padding: 2px 8px; border-radius: 3px; cursor: pointer; font-weight: 600; white-space: nowrap;">
            &larr; Back to Sub-Groups
          </button>
        </div>
      </div>

      <div style="border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="padding: 7px 12px; background: #f8fafc; border-bottom: 1px solid #cbd5e1; display: flex; justify-content: space-between; align-items: center;">
          <span style="font-size: 0.84rem; font-weight: 700; color: #0f172a;">3rd Classifications in ${t2Obj ? escapeHtml(t2Obj.name) : 'Sub-Group ' + t2} (${t3Children.length})</span>
          <span style="font-size: 0.75rem; color: #64748b;">Click a row or button to select 3rd Classification</span>
        </div>
        <div style="overflow-x: auto; max-height: 480px; overflow-y: auto;">
          <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
            <thead style="position: sticky; top: 0; background: #f1f5f9; z-index: 2; border-bottom: 1px solid #cbd5e1;">
              <tr>
                <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 75px; white-space: nowrap;">3RD CODE</th>
                <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">CLASSIFICATION NAME</th>
                <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 140px; white-space: nowrap;">PREFIX BRANCH</th>
                <th style="padding: 5px 8px; text-align: right; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 110px; white-space: nowrap;">ACTION</th>
              </tr>
            </thead>
            <tbody>
              ${t3Rows}
            </tbody>
          </table>
        </div>
      </div>
    `;
    return;
  }

  // 3. If 1st is selected -> Show Sub-Groups Table (Tier 2 Table)
  if (t1 !== null) {
    const t2Rows = t2Children.map(t2Item => {
      const subCount = (tiers.tier3 && tiers.tier3[t2Item.id]) ? tiers.tier3[t2Item.id].length : 0;
      return `
        <tr onclick="selectExplorerTier2(${t2Item.code_val});" style="cursor: pointer; border-bottom: 1px solid #f1f5f9;"
            onmouseenter="this.style.backgroundColor='#f8fafc';" onmouseleave="this.style.backgroundColor='transparent';">
          <td style="padding: 4px 8px; white-space: nowrap;">
            <span style="font-family: monospace; font-size: 0.8rem; font-weight: 700; color: #650b2b; background: #fdf2f5; border: 1px solid #fbcfe8; padding: 1px 6px; border-radius: 3px;">${String(t2Item.code_val).padStart(2, '0')}</span>
          </td>
          <td style="padding: 4px 8px; font-weight: 600; color: #1e293b;">${escapeHtml(t2Item.name)}</td>
          <td style="padding: 4px 8px; color: #64748b; font-size: 0.78rem; white-space: nowrap;">${subCount} Classifications</td>
          <td style="padding: 4px 8px; text-align: right; white-space: nowrap;">
            <button type="button" class="btn btn-sm" onclick="event.stopPropagation(); selectExplorerTier2(${t2Item.code_val});"
              style="font-size: 0.74rem; font-weight: 700; padding: 3px 10px; border-radius: 3px; background: #475569; color: #ffffff; border: 1px solid #334155; cursor: pointer; white-space: nowrap; display: inline-block;">
              Select (${t2Item.code_val})
            </button>
          </td>
        </tr>
      `;
    }).join('');

    container.innerHTML = `
      <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px 12px; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between; font-size: 0.8125rem;">
        <div style="display: flex; align-items: center; gap: 6px; font-weight: 600; color: #1e293b;">
          <span style="color: #64748b; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">Active Group:</span>
          <span style="color: #650b2b;">${String(t1).padStart(2, '0')} - ${t1Obj ? escapeHtml(t1Obj.name) : 'Group ' + t1}</span>
        </div>
        <div>
          <button type="button" onclick="resetSimpleCodeForm();"
            style="font-size: 0.72rem; color: #334155; background: #ffffff; border: 1px solid #cbd5e1; padding: 2px 8px; border-radius: 3px; cursor: pointer; font-weight: 600; white-space: nowrap;">
            &larr; All 1st Categories
          </button>
        </div>
      </div>

      <div style="border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="padding: 7px 12px; background: #f8fafc; border-bottom: 1px solid #cbd5e1; display: flex; justify-content: space-between; align-items: center;">
          <span style="font-size: 0.84rem; font-weight: 700; color: #0f172a;">2nd Sub-Groups in Group ${t1} (${t2Children.length})</span>
          <span style="font-size: 0.75rem; color: #64748b;">Click a row or button to select 2nd Sub-Group</span>
        </div>
        <div style="overflow-x: auto; max-height: 480px; overflow-y: auto;">
          <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
            <thead style="position: sticky; top: 0; background: #f1f5f9; z-index: 2; border-bottom: 1px solid #cbd5e1;">
              <tr>
                <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 75px; white-space: nowrap;">2ND CODE</th>
                <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">SUB-GROUP NAME</th>
                <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 140px; white-space: nowrap;">CLASSIFICATIONS</th>
                <th style="padding: 5px 8px; text-align: right; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 110px; white-space: nowrap;">ACTION</th>
              </tr>
            </thead>
            <tbody>
              ${t2Rows}
            </tbody>
          </table>
        </div>
      </div>
    `;
    return;
  }

  // 4. Default / Initial state (t1 === null) -> Show 1st Main Categories / Groups Table
  const t1Rows = t1List.map(t1Item => {
    const subCount = (tiers.tier2 && tiers.tier2[t1Item.id]) ? tiers.tier2[t1Item.id].length : 0;
    return `
      <tr onclick="selectExplorerTier1(${t1Item.code_val});" style="cursor: pointer; border-bottom: 1px solid #f1f5f9;"
          onmouseenter="this.style.backgroundColor='#f8fafc';" onmouseleave="this.style.backgroundColor='transparent';">
        <td style="padding: 5px 8px; white-space: nowrap;">
          <span style="font-family: monospace; font-size: 0.8rem; font-weight: 700; color: #650b2b; background: #fdf2f5; border: 1px solid #fbcfe8; padding: 1px 6px; border-radius: 3px;">${String(t1Item.code_val).padStart(2, '0')}</span>
        </td>
        <td style="padding: 5px 8px; font-weight: 600; color: #1e293b;">${escapeHtml(t1Item.name)}</td>
        <td style="padding: 5px 8px; color: #64748b; font-size: 0.78rem; white-space: nowrap;">${subCount} Sub-Groups</td>
        <td style="padding: 5px 8px; text-align: right; white-space: nowrap;">
          <button type="button" class="btn btn-sm" onclick="event.stopPropagation(); selectExplorerTier1(${t1Item.code_val});"
            style="font-size: 0.74rem; font-weight: 700; padding: 3px 10px; border-radius: 3px; background: #475569; color: #ffffff; border: 1px solid #334155; cursor: pointer; white-space: nowrap; display: inline-block;">
            Select (${t1Item.code_val})
          </button>
        </td>
      </tr>
    `;
  }).join('');

  container.innerHTML = `
    <div style="border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
      <div style="padding: 7px 12px; background: #f8fafc; border-bottom: 1px solid #cbd5e1; display: flex; justify-content: space-between; align-items: center;">
        <span style="font-size: 0.84rem; font-weight: 700; color: #0f172a;">1st Main Categories / Groups (${t1List.length})</span>
        <span style="font-size: 0.75rem; color: #64748b;">Click a row or button to select 1st Category</span>
      </div>
      <div style="overflow-x: auto; max-height: 480px; overflow-y: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
          <thead style="position: sticky; top: 0; background: #f1f5f9; z-index: 2; border-bottom: 1px solid #cbd5e1;">
            <tr>
              <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 75px; white-space: nowrap;">1ST CODE</th>
              <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">CATEGORY NAME</th>
              <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 140px; white-space: nowrap;">SUB-GROUPS</th>
              <th style="padding: 5px 8px; text-align: right; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 110px; white-space: nowrap;">ACTION</th>
            </tr>
          </thead>
          <tbody>
            ${t1Rows}
          </tbody>
        </table>
      </div>
    </div>
  `;
}

window.updateSimpleCode = function () {
  clearTimeout(simpleCodeDebounce);
  simpleCodeDebounce = setTimeout(async () => {
    const t1El = document.getElementById('simpleTier1');
    const t2El = document.getElementById('simpleTier2');
    const t3El = document.getElementById('simpleTier3');
    const t4El = document.getElementById('simpleTier4');
    const searchInput = document.getElementById('simpleCodeSearchInput');

    const searchQ = (searchInput?.value || '').toString().trim();
    const t1Val = t1El?.value || '';
    const t2Val = t2El?.value || '';
    const t3Val = t3El?.value || '';
    const t4Raw = (t4El?.value || '').toString().trim();

    const t1 = t1Val ? parseInt(t1Val, 10) : null;
    const t2 = t2Val ? parseInt(t2Val, 10) : null;
    const t3 = t3Val ? parseInt(t3Val, 10) : null;
    let t4Num = (t4Raw !== '') ? parseInt(t4Raw.replace(/\D/g, '') || '0', 10) : null;
    if (t4Num !== null && isNaN(t4Num)) t4Num = null;
    if (t4Num !== null && t4Num > 999) t4Num = 999;

    // Fetch items / availability if tiers or search are selected
    let items = [];
    let nextSeq = 1;
    try {
      const metaBase = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
      const userStationId = window.APP_DATA?.userStationId || '';
      let apiEndpoint = `${metaBase}/api/code_management.php?action=check_availability&station_id=${userStationId}`;
      if (t1 !== null) apiEndpoint += `&tier1=${t1}`;
      if (t2 !== null) apiEndpoint += `&tier2=${t2}`;
      if (t3 !== null) apiEndpoint += `&tier3=${t3}`;
      if (searchQ) apiEndpoint += `&q=${encodeURIComponent(searchQ)}`;

      const response = await fetch(apiEndpoint, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (response.ok) {
        const resData = await response.json();
        items = (resData && resData.items) ? resData.items : ((resData && resData.data && resData.data.items) ? resData.data.items : []);
        nextSeq = resData.next_sequence !== undefined ? Number(resData.next_sequence) : (resData.data?.next_sequence !== undefined ? Number(resData.data.next_sequence) : 1);
        if (Array.isArray(items)) {
          items.sort((a, b) => {
            const seqA = parseInt(a.tier4_seq !== undefined && a.tier4_seq !== null ? a.tier4_seq : (String(a.formatted_code || '').split('-').pop() || 0), 10) || 0;
            const seqB = parseInt(b.tier4_seq !== undefined && b.tier4_seq !== null ? b.tier4_seq : (String(b.formatted_code || '').split('-').pop() || 0), 10) || 0;
            if (seqA !== seqB) return seqA - seqB;
            return String(a.formatted_code || '').localeCompare(String(b.formatted_code || ''));
          });
        }
      }
    } catch (err) {
      console.warn('Filter query error:', err);
    }

    // Update 4th sequence display automatically with next sequential number
    if (t4El) {
      if (t1 !== null && t2 !== null && t3 !== null) {
        t4El.value = String(nextSeq).padStart(3, '0');
        t4El.disabled = false;
        t4El.readOnly = true;
        t4El.style.background = '#f8fafc';
        t4El.style.opacity = '1';
        t4El.style.cursor = 'default';
        t4El.style.fontFamily = 'monospace';
        t4El.style.fontWeight = '700';
        t4El.style.color = '#0284c7';
        t4El.title = `Sequential 4th Code: ${String(nextSeq).padStart(3, '0')} (Contiguous next sequence)`;
      } else {
        t4El.value = '';
        t4El.disabled = true;
        t4El.readOnly = true;
        t4El.style.background = '#f1f5f9';
        t4El.style.opacity = '0.6';
        t4El.style.cursor = 'not-allowed';
      }
    }

    // 1. If search input has query, render search dropdown popup
    if (searchQ) {
      renderSearchResultsDropdown(items, searchQ);
    } else {
      const dropdown = document.getElementById('simpleSearchResultsDropdown');
      if (dropdown) {
        dropdown.style.display = 'none';
        dropdown.innerHTML = '';
      }
    }

    // 2. Render dynamic category hierarchy & items explorer below the form
    renderCategoryExplorer(t1, t2, t3, null, items, nextSeq);
  }, 100);
};

// Initial Explorer & Queue Render on Page Load
document.addEventListener('DOMContentLoaded', function () {
  setTimeout(() => {
    if (typeof updateSimpleCode === 'function') {
      updateSimpleCode();
    }
    if (typeof loadAdminRequestsQueue === 'function') {
      loadAdminRequestsQueue();
    }
    if (typeof loadStationRequests === 'function') {
      loadStationRequests();
    }
  }, 200);
});

window.openSimpleRequestModal = async function () {
  const t1Val = document.getElementById('simpleTier1')?.value;
  const t2Val = document.getElementById('simpleTier2')?.value;
  const t3Val = document.getElementById('simpleTier3')?.value;

  if (!t1Val || !t2Val || !t3Val) {
    App.toast('Please select 1st, 2nd, and 3rd dropdowns first to request an item code.', 'warning');
    return;
  }

  const t1 = parseInt(t1Val, 10);
  const t2 = parseInt(t2Val, 10);
  const t3 = parseInt(t3Val, 10);

  const metaBase = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
  const userStationId = window.APP_DATA?.userStationId || '';
  let apiEndpoint = `${metaBase}/api/code_management.php?action=check_availability&tier1=${t1}&tier2=${t2}&tier3=${t3}&station_id=${userStationId}`;

  let nextSeq = 0;

  try {
    const response = await fetch(apiEndpoint, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    if (response.ok) {
      const resData = await response.json();
      nextSeq = resData.next_sequence !== undefined ? Number(resData.next_sequence) : (resData.data?.next_sequence !== undefined ? Number(resData.data.next_sequence) : 0);
    }
  } catch (err) {
    console.warn('Availability check error:', err);
  }

  const formattedCode = `${t1}-${String(t2).padStart(2, '0')}-${String(t3).padStart(2, '0')}-XXX`;

  const t1Input = document.getElementById('modalReqTier1');
  const t2Input = document.getElementById('modalReqTier2');
  const t3Input = document.getElementById('modalReqTier3');
  const t4Input = document.getElementById('modalReqTier4');
  const codeLabel = document.getElementById('modalReqFormattedCode');
  const branchLabel = document.getElementById('modalReqBranchLabel');

  if (t1Input) t1Input.value = t1;
  if (t2Input) t2Input.value = t2;
  if (t3Input) t3Input.value = t3;
  if (t4Input) t4Input.value = ''; // Exclusively assigned by Admin on approval
  if (codeLabel) codeLabel.textContent = formattedCode;
  if (branchLabel) branchLabel.textContent = 'Assigned by Admin on Approval';

  const tiers = window.APP_DATA?.tierHierarchy || { tier1: [], tier2: {}, tier3: {} };
  const t1Obj = (tiers.tier1 || []).find(t => Number(t.code_val) === t1);
  let t2Obj = null;
  if (t1Obj && tiers.tier2 && tiers.tier2[t1Obj.id]) {
    t2Obj = tiers.tier2[t1Obj.id].find(t => Number(t.code_val) === t2);
  }
  let t3Obj = null;
  if (t2Obj && tiers.tier3 && tiers.tier3[t2Obj.id]) {
    t3Obj = tiers.tier3[t2Obj.id].find(t => Number(t.code_val) === t3);
  }

  const branchNameParts = [
    t1Obj ? `${String(t1).padStart(2, '0')} - ${t1Obj.name}` : `${t1}`,
    t2Obj ? `${String(t2).padStart(2, '0')} - ${t2Obj.name}` : `${String(t2).padStart(2, '0')}`,
    t3Obj ? `${String(t3).padStart(2, '0')} - ${t3Obj.name}` : `${String(t3).padStart(2, '0')}`
  ];

  const p1 = document.getElementById('modalReqPathT1');
  const p2 = document.getElementById('modalReqPathT2');
  const p3 = document.getElementById('modalReqPathT3');
  if (p1) { p1.textContent = t1Obj ? `${String(t1).padStart(2,'0')} - ${t1Obj.name}` : `Tier ${t1}`; p1.title = p1.textContent; }
  if (p2) { p2.textContent = t2Obj ? `${String(t2).padStart(2,'0')} - ${t2Obj.name}` : `Tier ${t2}`; p2.title = p2.textContent; }
  if (p3) { p3.textContent = t3Obj ? `${String(t3).padStart(2,'0')} - ${t3Obj.name}` : `Tier ${t3}`; p3.title = p3.textContent; }

  window.openModal('requestNewCodeModal');
  setTimeout(() => {
    document.getElementById('modalReqItemName')?.focus();
    if (typeof window.updateModalSuggestions === 'function') {
      window.updateModalSuggestions();
    }
  }, 100);
};

// =====================================================================
// LIVE DUPLICATE CHECKER & EXISTING CODE WORD SUGGESTIONS ENGINE
// =====================================================================
let _modalReqDebounce = null;
let _cachedCatalogItems = null;
let _cachedPendingRequests = null;

window.onModalReqNameInput = function () {
  clearTimeout(_modalReqDebounce);
  _modalReqDebounce = setTimeout(() => {
    window.updateModalSuggestions();
  }, 90);
};

window.updateModalSuggestions = async function () {
  const tbody = document.getElementById('modalReqSuggestionsBody');
  const countBadge = document.getElementById('modalReqSuggestionsCount');
  const alertBox = document.getElementById('modalReqDuplicateAlert');
  if (!tbody) return;

  const inputName = (document.getElementById('modalReqItemName')?.value || '').trim();
  const inputSpecs = (document.getElementById('modalReqSpecs')?.value || '').trim();
  const t1 = parseInt(document.getElementById('modalReqTier1')?.value || '1', 10);
  const t2 = parseInt(document.getElementById('modalReqTier2')?.value || '1', 10);
  const t3 = parseInt(document.getElementById('modalReqTier3')?.value || '1', 10);
  const tiers = window.APP_DATA?.tierHierarchy || { tier1: [], tier2: {}, tier3: {} };

  // 1. Gather all items (Master Items + Catalog Items + Pending Requests)
  let items = [];
  if (window.itemSearchController?.['user']?.allItems?.length > 0) {
    items = window.itemSearchController['user'].allItems;
  } else if (_cachedCatalogItems && _cachedCatalogItems.length > 0) {
    items = _cachedCatalogItems;
  } else {
    try {
      const res = await App.request('/api/code_management.php?action=list_master_items&limit=25000');
      if (res.ok && res.data?.items) {
        _cachedCatalogItems = res.data.items;
        items = _cachedCatalogItems;
      }
    } catch (e) {
      console.warn('Error fetching catalog for modal suggestions:', e);
    }
  }

  // Also include pending requests if not already cached
  if (!_cachedPendingRequests) {
    try {
      const reqRes = await App.request('/api/code_management.php?action=list_requests');
      if (reqRes.ok && reqRes.data?.requests) {
        _cachedPendingRequests = reqRes.data.requests;
      }
    } catch (e) {}
  }
  const pendingRequests = _cachedPendingRequests || [];

  // Full unified pool across entire system
  const allPool = [];
  const seenCodes = new Set();

  (items || []).forEach(it => {
    const fCode = String(it.formatted_code || '');
    if (fCode) seenCodes.add(fCode);
    allPool.push({
      id: it.id,
      code: fCode,
      tier1: Number(it.tier1 || 0),
      tier2: Number(it.tier2 || 0),
      tier3: Number(it.tier3 || 0),
      name: it.orig_description || it.item_name || '',
      specs: it.specifications || '',
      uom: it.orig_uom || it.uom || 'Nos',
      is_master: Number(it.is_added_to_master) > 0,
      status: Number(it.is_added_to_master) > 0 ? 'MASTER' : 'STAGED',
      station_name: ''
    });
  });

  pendingRequests.forEach(r => {
    const rawSeq = r.assigned_tier4_seq !== null && r.assigned_tier4_seq !== undefined ? r.assigned_tier4_seq : (r.suggested_tier4_seq !== null && r.suggested_tier4_seq !== undefined ? r.suggested_tier4_seq : 0);
    const fCode = String(r.approved_formatted_code || `${r.tier1}-${String(r.tier2).padStart(2,'0')}-${String(r.tier3).padStart(2,'0')}-${String(rawSeq).padStart(3,'0')}`);
    if (!seenCodes.has(fCode)) {
      seenCodes.add(fCode);
      allPool.push({
        id: r.generated_master_item_id || r.id,
        code: fCode,
        tier1: Number(r.tier1 || 0),
        tier2: Number(r.tier2 || 0),
        tier3: Number(r.tier3 || 0),
        name: r.item_name || '',
        specs: r.specifications || '',
        uom: r.uom || 'Nos',
        is_master: Number(r.is_added_to_master) > 0,
        status: Number(r.is_added_to_master) > 0 ? 'MASTER' : (r.status === 'APPROVED' ? 'STAGED' : 'PENDING'),
        station_name: r.station_name || ''
      });
    }
  });

  const queryRaw = (inputName + ' ' + inputSpecs).trim().toLowerCase();
  const queryWords = queryRaw.split(/[^a-zA-Z0-9]+/).filter(w => w.length >= 2);

  // If no words typed yet -> Show ready system search prompt
  if (queryWords.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="6" style="text-align: center; padding: 46px 20px;">
          <div style="font-size: 2.2rem; margin-bottom: 8px;">🔍</div>
          <strong style="color: #0f172a; font-size: 0.95rem;">System-Wide Duplicate Prevention Search Ready</strong>
          <p style="color: #64748b; font-size: 0.8125rem; max-width: 520px; margin: 6px auto 0 auto; line-height: 1.5;">
            Type any words in the <strong>Item Description / Name</strong> box on the left (e.g. <em>BEARING 6205, ARALDITE, OIL, VALVE</em>). The system will search across <strong>all 10,960+ system codes in all categories</strong> to help prevent duplicate item creation.
          </p>
        </td>
      </tr>
    `;
    if (alertBox) alertBox.style.display = 'none';
    if (countBadge) {
      countBadge.textContent = 'Live System Search';
      countBadge.style.background = '#e0f2fe';
      countBadge.style.color = '#0369a1';
      countBadge.style.borderColor = '#bae6fd';
    }
    return;
  }

  // System-wide matching across ALL categories
  const scored = [];
  allPool.forEach(it => {
    const targetText = (it.name + ' ' + it.specs + ' ' + it.code).toLowerCase();
    let matchCount = 0;
    queryWords.forEach(w => {
      if (targetText.includes(w)) matchCount++;
    });

    if (matchCount > 0) {
      let score = matchCount * 15;
      if (matchCount === queryWords.length) score += 50; // All words match
      if (targetText.includes(queryRaw)) score += 60; // Exact phrase match
      if (it.tier1 === t1) score += 8;
      if (it.tier2 === t2) score += 8;
      if (it.tier3 === t3) score += 12;
      if (it.is_master) score += 5;

      scored.push({ item: it, score, matchCount });
    }
  });

  scored.sort((a, b) => b.score - a.score);
  const filtered = scored.slice(0, 60).map(s => s.item);

  if (filtered.length > 0) {
    if (alertBox) alertBox.style.display = 'block';
    if (countBadge) {
      countBadge.textContent = `${filtered.length} related system codes found`;
      countBadge.style.background = '#fef3c7';
      countBadge.style.color = '#92400e';
      countBadge.style.borderColor = '#fde68a';
    }
  } else {
    if (alertBox) alertBox.style.display = 'none';
    if (countBadge) {
      countBadge.textContent = `0 duplicates found`;
      countBadge.style.background = '#dcfce7';
      countBadge.style.color = '#15803d';
      countBadge.style.borderColor = '#86efac';
    }
    tbody.innerHTML = `
      <tr>
        <td colspan="6" style="text-align: center; padding: 42px 20px;">
          <div style="font-size: 1.8rem; margin-bottom: 6px;">✅</div>
          <strong style="color: #15803d; font-size: 0.95rem;">No Duplicate Codes Found Anywhere in System</strong>
          <p style="color: #64748b; font-size: 0.8125rem; margin: 4px 0 0 0;">
            No existing items in any category match "${escapeHtml(inputName)}". You can safely submit this new code request!
          </p>
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = filtered.map(it => {
    let statusBadge = '';
    if (it.status === 'MASTER') {
      statusBadge = `<span style="font-size:0.71rem; font-weight:700; color:#c2410c; background:#fff7ed; border:1px solid #fed7aa; padding:2px 6px; border-radius:3px; white-space:nowrap;">MASTER ADDED</span>`;
    } else if (it.status === 'STAGED') {
      statusBadge = `<span style="font-size:0.71rem; font-weight:700; color:#0284c7; background:#e0f2fe; border:1px solid #bae6fd; padding:2px 6px; border-radius:3px; white-space:nowrap;">STAGED (PENDING MASTER)</span>`;
    } else {
      statusBadge = `<span style="font-size:0.71rem; font-weight:700; color:#b45309; background:#fef3c7; border:1px solid #fde68a; padding:2px 6px; border-radius:3px; white-space:nowrap;">PENDING REVIEW</span>`;
    }

    const codeBadge = it.is_master
      ? `<span style="font-family:monospace; font-size:0.78rem; font-weight:700; color:#650b2b; background:#fdf2f5; border:1px solid #fbcfe8; padding:2px 6px; border-radius:3px;">${escapeHtml(it.code)}</span>`
      : `<span style="font-family:monospace; font-size:0.78rem; font-weight:700; color:#0284c7; background:#e0f2fe; border:1px solid #bae6fd; padding:2px 6px; border-radius:3px;">${escapeHtml(it.code)}</span>`;

    const branchText = `${String(it.tier1).padStart(2,'0')}.${String(it.tier2).padStart(2,'0')}.${String(it.tier3).padStart(2,'0')}`;

    let highlightedName = escapeHtml(it.name);
    if (queryWords.length > 0) {
      queryWords.forEach(w => {
        try {
          const regex = new RegExp(`(${w})`, 'gi');
          highlightedName = highlightedName.replace(regex, '<mark style="background:#fef08a; padding:0 1px; font-weight:800;">$1</mark>');
        } catch (e) {}
      });
    }

    let actionBtn = '';
    if (it.is_master) {
      actionBtn = `
        <button type="button" class="btn btn-sm" onclick="addSuggestedMasterToStation(${it.id}, '${escapeHtml(it.code)}', '${encodeURIComponent(it.name)}');"
          style="font-size:0.72rem; font-weight:700; padding:3px 8px; border-radius:3px; background:#650b2b; color:#ffffff; border:1px solid #4a071e; cursor:pointer; white-space:nowrap;"
          title="Item is already in Master Database! Click to add directly to power station table">
          + Add to Station
        </button>
      `;
    } else {
      actionBtn = `
        <button type="button" class="btn btn-sm" onclick="useSuggestedItemName('${escapeHtml(it.name)}');"
          style="font-size:0.72rem; font-weight:600; padding:3px 8px; border-radius:3px; background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; cursor:pointer; white-space:nowrap;"
          title="Use this item name for your request">
          Use Text
        </button>
      `;
    }

    return `
      <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;"
          onmouseenter="this.style.backgroundColor='#f8fafc';" onmouseleave="this.style.backgroundColor='transparent';">
        <td style="padding: 6px 8px; white-space: nowrap;">${codeBadge}</td>
        <td style="padding: 6px 8px; text-align: center; font-family: monospace; font-size: 0.74rem; color: #64748b; white-space: nowrap;">${branchText}</td>
        <td style="padding: 6px 10px;">
          <div style="font-weight: 700; color: #0f172a; font-size: 0.8rem;">${highlightedName}</div>
          ${it.specs ? `<div style="font-size: 0.72rem; color: #64748b; margin-top: 1px;">${escapeHtml(it.specs)}</div>` : ''}
          ${it.station_name ? `<div style="font-size: 0.7rem; color: #0284c7; margin-top: 1px;">Requested by: ${escapeHtml(it.station_name)}</div>` : ''}
        </td>
        <td style="padding: 6px 8px; text-align: center; font-size: 0.76rem; color: #475569;">${escapeHtml(it.uom)}</td>
        <td style="padding: 6px 8px; text-align: center; white-space: nowrap;">${statusBadge}</td>
        <td style="padding: 6px 8px; text-align: center; white-space: nowrap;">${actionBtn}</td>
      </tr>
    `;
  }).join('');
};

window.addSuggestedMasterToStation = function (masterId, code, encodedName) {
  if (typeof window.closeModal === 'function') {
    window.closeModal('requestNewCodeModal');
  } else {
    const modal = document.getElementById('requestNewCodeModal');
    if (modal) {
      modal.style.display = 'none';
      modal.classList.remove('active');
    }
  }
  if (typeof openImportModal === 'function') {
    openImportModal(masterId, code, encodedName);
  }
};

window.useSuggestedItemName = function (text) {
  const input = document.getElementById('modalReqItemName');
  if (input) {
    input.value = text.toUpperCase();
    if (typeof onModalReqNameInput === 'function') {
      onModalReqNameInput();
    }
  }
};

window.previewAddCodeImage = function (slot, input) {
  if (input.files && input.files[0]) {
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = function (e) {
      const placeholder = document.getElementById(`modalReqImg${slot}Placeholder`);
      const preview = document.getElementById(`modalReqImg${slot}Preview`);
      const imgTag = document.getElementById(`modalReqImg${slot}Tag`);
      if (placeholder) placeholder.style.display = 'none';
      if (imgTag) imgTag.src = e.target.result;
      if (preview) preview.style.display = 'block';
    };
    reader.readAsDataURL(file);
  }
};

window.removeAddCodeImage = function (slot) {
  const input = document.getElementById(`modalReqImg${slot}Input`);
  if (input) input.value = '';
  const placeholder = document.getElementById(`modalReqImg${slot}Placeholder`);
  const preview = document.getElementById(`modalReqImg${slot}Preview`);
  const imgTag = document.getElementById(`modalReqImg${slot}Tag`);
  if (imgTag) imgTag.src = '';
  if (preview) preview.style.display = 'none';
  if (placeholder) placeholder.style.display = 'block';
};

window.executeSimpleCodeRequest = async function (e) {
  e.preventDefault();
  const form = e.target;
  const btn = document.getElementById('btnSubmitSimpleReq');
  const originalText = btn ? btn.innerHTML : 'Submit Code Request';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = 'Submitting Request...';
  }

  try {
    const metaBase = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || (window.APP_DATA?.csrfToken || '');
    const formData = new FormData(form);
    formData.append('action', 'create_request');
    if (!formData.get('_csrf_token') && csrfToken) {
      formData.append('_csrf_token', csrfToken);
    }

    const apiEndpoint = `${metaBase}/api/code_management.php`;
    const response = await fetch(apiEndpoint, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': csrfToken
      },
      body: formData
    });

    let resData = {};
    try {
      resData = await response.json();
    } catch (parseErr) {
      console.error('Response parse error:', parseErr);
    }

    if (response.ok && (resData.success || resData.data?.id)) {
      App.toast(resData.message || 'Item Code Creation Request submitted successfully for Administrator Approval!', 'success');
      form.reset();
      window.removeAddCodeImage(1);
      window.removeAddCodeImage(2);
      window.closeModal('requestNewCodeModal');
      if (typeof window.updateSimpleCode === 'function') {
        window.updateSimpleCode();
      }
      if (typeof window.loadStationRequests === 'function') {
        window.loadStationRequests();
      }
      if (typeof window.loadAdminRequestsQueue === 'function') {
        window.loadAdminRequestsQueue();
      }
    } else {
      const errorMsg = resData.message || (resData.errors && resData.errors.length ? resData.errors.join(', ') : 'Failed to submit code creation request. Please verify inputs.');
      App.toast(errorMsg, 'error');
    }
  } catch (err) {
    console.error('executeSimpleCodeRequest Error:', err);
    App.toast(`Submission Error: ${err.message}`, 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = originalText;
    }
  }
};

/**
 * Edit Code Creation Request Handlers (Pending Requests Only)
 */
window.previewEditCodeImage = function (slot, input) {
  if (input.files && input.files[0]) {
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = function (e) {
      const placeholder = document.getElementById(`editReqImg${slot}Placeholder`);
      const preview = document.getElementById(`editReqImg${slot}Preview`);
      const imgTag = document.getElementById(`editReqImg${slot}Tag`);
      const delInput = document.getElementById(`editReqDeleteImg${slot}`);
      if (delInput) delInput.value = '0';
      if (placeholder) placeholder.style.display = 'none';
      if (imgTag) imgTag.src = e.target.result;
      if (preview) preview.style.display = 'block';
    };
    reader.readAsDataURL(file);
  }
};

window.removeEditCodeImage = function (slot) {
  const input = document.getElementById(`editReqImg${slot}Input`);
  if (input) input.value = '';
  const placeholder = document.getElementById(`editReqImg${slot}Placeholder`);
  const preview = document.getElementById(`editReqImg${slot}Preview`);
  const imgTag = document.getElementById(`editReqImg${slot}Tag`);
  const delInput = document.getElementById(`editReqDeleteImg${slot}`);
  if (delInput) delInput.value = '1';
  if (imgTag) imgTag.src = '';
  if (preview) preview.style.display = 'none';
  if (placeholder) placeholder.style.display = 'block';
};

window.populateEditCodeRequestForm = function (r) {
  if (!r) return;

  const idInput = document.getElementById('editReqId');
  if (idInput) idInput.value = r.id || r.request_id || '';

  const noEl = document.getElementById('editReqNo');
  if (noEl) noEl.textContent = r.request_no || (r.id ? `REQ-${r.id}` : 'REQ-0000');

  const t1 = String(r.tier1 || 1).padStart(2, '0');
  const t2 = String(r.tier2 || 1).padStart(2, '0');
  const t3 = String(r.tier3 || 1).padStart(2, '0');
  const rawSeq = r.assigned_tier4_seq !== null && r.assigned_tier4_seq !== undefined ? r.assigned_tier4_seq : (r.suggested_tier4_seq !== null && r.suggested_tier4_seq !== undefined ? r.suggested_tier4_seq : (r.tier4_seq !== null && r.tier4_seq !== undefined ? r.tier4_seq : 0));
  const t4 = String(rawSeq).padStart(3, '0');
  const codeEl = document.getElementById('editReqDisplayCode');
  if (codeEl) codeEl.textContent = `${t1}-${t2}-${t3}-${t4}`;

  // Status Badge & Submit Button
  const badgeEl = document.getElementById('editReqStatusBadge');
  const btnEl = document.getElementById('btnSubmitEditReq');
  if (r.status === 'REJECTED') {
    if (badgeEl) {
      badgeEl.textContent = 'REJECTED (EDIT TO RESUBMIT)';
      badgeEl.style.color = '#991b1b';
      badgeEl.style.background = '#fee2e2';
      badgeEl.style.borderColor = '#fca5a5';
    }
    if (btnEl) btnEl.innerHTML = 'Resubmit for Approval';
  } else {
    if (badgeEl) {
      badgeEl.textContent = 'PENDING';
      badgeEl.style.color = '#c2410c';
      badgeEl.style.background = '#fff7ed';
      badgeEl.style.borderColor = '#fed7aa';
    }
    if (btnEl) btnEl.innerHTML = 'Save Changes';
  }

  // Description / Name
  const nameInput = document.getElementById('editReqItemName');
  if (nameInput) {
    nameInput.value = (r.item_name || r.orig_description || '').toUpperCase();
  }

  // Amount (LKR) / Unit Price
  const priceInput = document.getElementById('editReqUnitPrice');
  if (priceInput) {
    if (r.unit_price !== null && r.unit_price !== undefined && r.unit_price !== '' && Number(r.unit_price) > 0) {
      priceInput.value = Number(r.unit_price).toFixed(2);
    } else {
      priceInput.value = '';
    }
  }

  // Unit of Measure
  const uomSelect = document.getElementById('editReqUom');
  if (uomSelect && (r.uom || r.orig_uom)) {
    const rawUom = String(r.uom || r.orig_uom).trim();
    let matched = false;
    for (let opt of uomSelect.options) {
      if (opt.value.toUpperCase() === rawUom.toUpperCase() || opt.text.toUpperCase().startsWith(rawUom.toUpperCase())) {
        uomSelect.value = opt.value;
        matched = true;
        break;
      }
    }
    if (!matched) {
      const newOpt = new Option(rawUom, rawUom, true, true);
      uomSelect.add(newOpt);
      uomSelect.value = rawUom;
    }
  }

  // Image inputs & delete flags
  const del1 = document.getElementById('editReqDeleteImg1');
  if (del1) del1.value = '0';
  const del2 = document.getElementById('editReqDeleteImg2');
  if (del2) del2.value = '0';
  const file1 = document.getElementById('editReqImg1Input');
  if (file1) file1.value = '';
  const file2 = document.getElementById('editReqImg2Input');
  if (file2) file2.value = '';

  const metaBase = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';

  // Image 1 preview
  const p1 = document.getElementById('editReqImg1Placeholder');
  const pr1 = document.getElementById('editReqImg1Preview');
  const t1Tag = document.getElementById('editReqImg1Tag');
  if (r.image_url_1) {
    let src1 = r.image_url_1;
    if (src1.startsWith('/') && metaBase && !src1.startsWith(metaBase)) {
      src1 = metaBase + src1;
    }
    if (t1Tag) t1Tag.src = src1;
    if (pr1) pr1.style.display = 'block';
    if (p1) p1.style.display = 'none';
  } else {
    if (t1Tag) t1Tag.src = '';
    if (pr1) pr1.style.display = 'none';
    if (p1) p1.style.display = 'block';
  }

  // Image 2 preview
  const p2 = document.getElementById('editReqImg2Placeholder');
  const pr2 = document.getElementById('editReqImg2Preview');
  const t2Tag = document.getElementById('editReqImg2Tag');
  if (r.image_url_2) {
    let src2 = r.image_url_2;
    if (src2.startsWith('/') && metaBase && !src2.startsWith(metaBase)) {
      src2 = metaBase + src2;
    }
    if (t2Tag) t2Tag.src = src2;
    if (pr2) pr2.style.display = 'block';
    if (p2) p2.style.display = 'none';
  } else {
    if (t2Tag) t2Tag.src = '';
    if (pr2) pr2.style.display = 'none';
    if (p2) p2.style.display = 'block';
  }
};

window.openEditCodeRequestModal = async function (param) {
  let reqId = null;
  let cached = null;

  if (typeof param === 'object' && param !== null) {
    reqId = param.id || param.request_id;
    cached = param;
  } else if (typeof param === 'number' || typeof param === 'string') {
    reqId = parseInt(param, 10);
    if (window._stationRequestsMap && window._stationRequestsMap[reqId]) {
      cached = window._stationRequestsMap[reqId];
    }
  }

  // STRICT BUSINESS RULE: Only editable if status is PENDING or REJECTED!
  if (cached && (Number(cached.is_added_to_master) > 0 || (cached.status && cached.status !== 'PENDING' && cached.status !== 'REJECTED'))) {
    App.toast('This code request has already been approved by Administrator and can no longer be edited.', 'warning');
    return;
  }

  // 1. Immediately open modal and populate cached details with 0 latency!
  if (cached) {
    window.openModal('editCodeRequestModal');
    const modal = document.getElementById('editCodeRequestModal');
    if (modal) {
      modal.classList.add('active');
      modal.style.display = 'flex';
      modal.style.opacity = '1';
      modal.style.visibility = 'visible';
    }
    window.populateEditCodeRequestForm(cached);
  }

  // 2. Fetch fresh details from server to ensure 100% sync
  if (reqId) {
    try {
      const res = await App.request(`/api/code_management.php?action=get_code_request&id=${reqId}`);
      if (!res.ok || !res.data?.request) {
        if (!cached) {
          App.toast(res.data?.message || 'Could not load request details.', 'error');
        }
        return;
      }
      const r = res.data.request;

      if (Number(r.is_added_to_master) > 0 || (r.status !== 'PENDING' && r.status !== 'REJECTED')) {
        window.closeModal('editCodeRequestModal');
        App.toast('This code request has already been approved by Administrator and can no longer be edited.', 'warning');
        return;
      }

      window._stationRequestsMap = window._stationRequestsMap || {};
      window._stationRequestsMap[r.id] = r;

      // Open modal if not open
      window.openModal('editCodeRequestModal');
      const modal = document.getElementById('editCodeRequestModal');
      if (modal) {
        modal.classList.add('active');
        modal.style.display = 'flex';
        modal.style.opacity = '1';
        modal.style.visibility = 'visible';
      }

      // Populate fresh details
      window.populateEditCodeRequestForm(r);
    } catch (err) {
      console.error('openEditCodeRequestModal error:', err);
      if (!cached) {
        App.toast(`Failed to load request: ${err.message}`, 'error');
      }
    }
  }
};

window.deleteStationCodeRequest = async function (requestId) {
  if (!requestId) return;

  const confirmed = confirm('Are you sure you want to permanently delete this code request? This action cannot be undone.');
  if (!confirmed) return;

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || (window.APP_DATA?.csrfToken || '');
    const formData = new FormData();
    formData.append('action', 'delete_code_request');
    formData.append('request_id', requestId);
    formData.append('_csrf_token', csrfToken);

    const res = await App.request('/api/code_management.php', {
      method: 'POST',
      body: formData
    });

    if (res.ok && res.data?.success) {
      App.toast(res.data.message || 'Code request deleted successfully.', 'success');
      if (typeof window.loadStationRequests === 'function') {
        window.loadStationRequests();
      }
      if (typeof window.loadAdminRequestsQueue === 'function') {
        window.loadAdminRequestsQueue();
      }
    } else {
      App.toast(res.data?.message || 'Failed to delete code request.', 'error');
    }
  } catch (err) {
    console.error('deleteStationCodeRequest error:', err);
    App.toast(`Delete error: ${err.message}`, 'error');
  }
};

window.executeEditCodeRequest = async function (e) {
  e.preventDefault();
  const form = e.target;
  const btn = document.getElementById('btnSubmitEditReq');
  const originalText = btn ? btn.innerHTML : 'Save Changes';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = 'Saving Changes...';
  }

  try {
    const metaBase = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || (window.APP_DATA?.csrfToken || '');
    const formData = new FormData(form);
    formData.append('action', 'update_code_request');
    if (!formData.get('_csrf_token') && csrfToken) {
      formData.append('_csrf_token', csrfToken);
    }

    const res = await fetch(`${metaBase}/api/code_management.php`, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': csrfToken
      },
      body: formData
    });

    const resData = await res.json().catch(() => ({}));
    if (res.ok && resData.success) {
      App.toast(resData.message || 'Code request details updated successfully.', 'success');
      window.closeModal('editCodeRequestModal');
      if (typeof window.loadStationRequests === 'function') {
        window.loadStationRequests();
      }
      if (typeof window.loadAdminRequestsQueue === 'function') {
        window.loadAdminRequestsQueue();
      }
    } else {
      App.toast(resData.message || 'Failed to update code request. Please verify inputs.', 'error');
    }
  } catch (err) {
    console.error('executeEditCodeRequest error:', err);
    App.toast(`Update error: ${err.message}`, 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = originalText;
    }
  }
};

/**
 * ============================================================================
 * Smart Industrial Spell Checker & Auto-Corrector (100% Free & Intelligent)
 * Supports Full UPPERCASE / CAPITAL Letters, Case-Preservation & Engineering Whitelist
 * ============================================================================
 */
const SmartSpellChecker = {
  whitelist: new Set([
    'SKF', 'FAG', 'NTN', 'NSK', 'KOYO', 'TIMKEN', 'INA', 'NACHI',
    'ARALDITE', 'LOCTITE', 'MOBIL', 'SHELL', 'CASTROL', 'TOTAL', 'CALTEX',
    'PTFE', 'EPDM', 'NBR', 'FKM', 'VITON', 'PVC', 'HDPE', 'SS316', 'SS304', 'MS', 'GI',
    'UOM', 'NOS', 'MTR', 'LTR', 'KGS', 'RPM', 'KVA', 'KW', 'MW', 'BAR', 'PSI', 'MM', 'CM',
    'BSP', 'NPT', 'JIS', 'DIN', 'ISO', 'ANSI', 'PN16', 'PN25', 'PN40',
    'KOTHMALE', 'VICTORIA', 'RANDENIGALA', 'RANTAMBE', 'SAMANALAWEWA',
    'LAXAPANA', 'POLPITIYA', 'WIMALASURENDRA', 'CANYON', 'BOWATENNA', 'UKUWELA', 'KILINOCHCHI',
    'CEB', 'FIFO', 'GEN', 'TURBINE', 'STATOR', 'ROTOR', 'EXCITER', 'GOVERNOR', 'WATT', 'VOLT', 'AMP'
  ]),

  cache: new Map(),
  containers: {},

  // Strictly enforce UPPERCASE for all replacements
  matchCase(original, replacement) {
    if (!replacement) return '';
    return String(replacement).toUpperCase();
  },

  hide(inputId) {
    const cId = this.containers ? this.containers[inputId] : null;
    const container = cId ? document.getElementById(cId) : null;
    if (container) {
      container.style.display = 'none';
      container.innerHTML = '';
    }
  },

  dismiss(inputId) {
    this.hide(inputId);
  },

  async check(text) {
    if (!text || text.trim().length < 3) return [];
    const cleanText = text.trim().toUpperCase();
    if (this.cache.has(cleanText)) return this.cache.get(cleanText);

    const suggestions = [];
    const detectedWords = new Set();

    // Normalize to sentence/title case so NLP spell checker doesn't ignore UPPERCASE words
    const normalizedText = cleanText.toLowerCase().replace(/(^\w{1})|(\s+\w{1})/g, letter => letter.toUpperCase());

    try {
      // 1. Primary Engine: LanguageTool Public API (with normalized text to catch ALL-CAPS typos)
      const params = new URLSearchParams({
        text: normalizedText,
        language: 'en-US'
      });

      const response = await fetch('https://api.languagetool.org/v2/check', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
      });

      if (response.ok) {
        const data = await response.json();
        if (data && data.matches) {
          data.matches.forEach(m => {
            const originalWord = cleanText.substring(m.offset, m.offset + m.length).toUpperCase();
            if (this.whitelist.has(originalWord) || detectedWords.has(originalWord)) return;

            // Check if LanguageTool is simply suggesting brand casing (e.g. iPhone for IPHONE)
            // If any replacement in uppercase equals the original word, it's ALREADY valid!
            const isAlreadyCorrect = m.replacements && m.replacements.some(r => String(r.value || '').trim().toUpperCase() === originalWord);
            if (isAlreadyCorrect) {
              this.whitelist.add(originalWord);
              return;
            }

            if (m.replacements && m.replacements.length > 0) {
              const matchedReplacements = m.replacements
                .map(r => String(r.value || '').trim().toUpperCase())
                .filter(r => r && r !== originalWord && !this.whitelist.has(r));

              if (matchedReplacements.length > 0) {
                suggestions.push({
                  offset: m.offset,
                  length: m.length,
                  word: originalWord,
                  replacements: matchedReplacements.slice(0, 3),
                  message: m.message
                });
                detectedWords.add(originalWord);
              }
            }
          });
        }
      }
    } catch (err) {
      console.warn('LanguageTool check error:', err);
    }

    // 2. Secondary / Word-Level Engine (Datamuse API) for technical words or fallback
    const words = cleanText.split(/[^A-Z0-9_-]+/).filter(w => w.length > 3 && !/^\d+$/.test(w));
    for (const w of words) {
      const upperW = w.toUpperCase();
      if (this.whitelist.has(upperW) || detectedWords.has(upperW)) continue;

      try {
        const res = await fetch(`https://api.datamuse.com/words?sp=${encodeURIComponent(w.toLowerCase())}&max=2`);
        if (res.ok) {
          const list = await res.json();
          if (list.length > 0) {
            const bestMatch = String(list[0].word || '').trim().toUpperCase();
            if (bestMatch && bestMatch !== upperW && !this.whitelist.has(bestMatch)) {
              suggestions.push({
                offset: cleanText.indexOf(w),
                length: w.length,
                word: upperW,
                replacements: [bestMatch],
                message: `Suggested spelling for "${upperW}"`
              });
              detectedWords.add(upperW);
            }
          }
        }
      } catch (e) { }
    }

    this.cache.set(cleanText, suggestions);
    return suggestions;
  },

  attach(inputId, containerId) {
    const input = document.getElementById(inputId);
    if (!input) return;

    this.containers = this.containers || {};
    this.containers[inputId] = containerId;

    // Real-time uppercase enforcement on typing, pasting, keyup, and change
    const enforceUpper = () => {
      const start = input.selectionStart;
      const end = input.selectionEnd;
      const upper = (input.value || '').toUpperCase();
      if (input.value !== upper) {
        input.value = upper;
        if (start !== null && end !== null && typeof input.setSelectionRange === 'function') {
          input.setSelectionRange(start, end);
        }
      }
    };
    input.addEventListener('input', enforceUpper);
    input.addEventListener('keyup', enforceUpper);
    input.addEventListener('change', enforceUpper);
    input.addEventListener('paste', () => setTimeout(enforceUpper, 10));

    let container = document.getElementById(containerId);
    if (!container) {
      container = document.createElement('div');
      container.id = containerId;
      container.style.cssText = 'margin-top: 4px; font-size: 0.73rem; min-height: 18px; display: none; align-items: center; gap: 6px; flex-wrap: wrap; transition: all 0.2s ease;';
      input.parentNode.insertBefore(container, input.nextSibling);
    }

    let debounceTimer = null;
    const triggerCheck = () => {
      enforceUpper();
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(async () => {
        const text = (input.value || '').toUpperCase();
        if (!text || text.trim().length < 3) {
          this.hide(inputId);
          return;
        }

        const issues = await SmartSpellChecker.check(text);
        if (issues.length === 0) {
          this.hide(inputId);
          return;
        }

        container.style.display = 'flex';
        container.innerHTML = `
          <span style="color: #ea580c; font-weight: 700; display: flex; align-items: center; gap: 3px;">
            <span>⚡ Fix:</span>
          </span>
          ${issues.map(iss => `
            <span style="color: #475569; background: #fff7ed; border: 1px solid #fed7aa; padding: 1px 6px; border-radius: 3px; display: inline-flex; align-items: center; gap: 4px;">
              <span style="text-decoration: line-through; color: #dc2626; font-weight: 600; text-transform: uppercase;">${escapeHtml(iss.word)}</span>
              <span>&rarr;</span>
              ${iss.replacements.map(rep => `
                <button type="button" class="btn btn-sm" onclick="SmartSpellChecker.applyFix('${inputId}', '${escapeHtml(iss.word)}', '${escapeHtml(rep)}')"
                  style="font-size: 0.72rem; font-weight: 700; color: #15803d; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 1px 6px; border-radius: 3px; cursor: pointer; text-transform: uppercase;" title="Click to replace with ${escapeHtml(rep)}">
                  ${escapeHtml(rep)}
                </button>
              `).join('')}
            </span>
          `).join('')}
          <button type="button" onclick="SmartSpellChecker.dismiss('${inputId}')"
            style="background: none; border: none; color: #94a3b8; font-size: 0.75rem; font-weight: 700; cursor: pointer; padding: 0 4px; margin-left: auto;"
            title="Dismiss suggestions">
            ✕
          </button>
        `;
      }, 400);
    };

    input.addEventListener('input', triggerCheck);
    input.addEventListener('focus', triggerCheck);
  },

  applyFix(inputId, oldWord, newWord) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const cleanOld = String(oldWord || '').trim().toUpperCase();
    const cleanNew = String(newWord || '').trim().toUpperCase();
    if (!cleanOld || !cleanNew) return;

    // Whitelist both to ensure this corrected word is never flagged again
    this.whitelist.add(cleanNew);
    this.whitelist.add(cleanOld);
    this.cache.clear();

    const regex = new RegExp(`\\b${cleanOld}\\b`, 'gi');
    input.value = (input.value.replace(regex, cleanNew) || '').toUpperCase();

    // Immediately hide the suggestions box
    this.hide(inputId);

    App.toast(`Corrected: "${cleanOld}" → "${cleanNew}"`, 'success');
    input.dispatchEvent(new Event('input'));
  },

  async autoFixAll(inputId) {
    const input = document.getElementById(inputId);
    if (!input || !input.value) return;
    input.value = input.value.toUpperCase();
    const issues = await this.check(input.value);
    if (issues.length === 0) {
      App.toast('No spelling mistakes detected!', 'info');
      this.hide(inputId);
      return;
    }
    let updated = input.value;
    let fixCount = 0;
    issues.forEach(iss => {
      if (iss.replacements && iss.replacements.length > 0) {
        const targetRep = String(iss.replacements[0] || '').toUpperCase();
        this.whitelist.add(targetRep);
        this.whitelist.add(iss.word);
        const regex = new RegExp(`\\b${iss.word}\\b`, 'gi');
        updated = updated.replace(regex, targetRep);
        fixCount++;
      }
    });
    input.value = updated.toUpperCase();
    this.cache.clear();

    // Immediately hide the suggestions box
    this.hide(inputId);

    App.toast(`Auto-corrected ${fixCount} spelling mistake(s)!`, 'success');
    input.dispatchEvent(new Event('input'));
  }
};

window.SmartSpellChecker = SmartSpellChecker;

// Auto-attach Spell Checker on Page Load & Enforce Uppercase on All Description/Spec Fields
document.addEventListener('DOMContentLoaded', function () {
  setTimeout(() => {
    ['modalReviewItemName', 'modalReqItemName'].forEach(id => {
      const el = document.getElementById(id);
      if (el) {
        el.style.textTransform = 'uppercase';
        el.addEventListener('input', function () {
          const s = this.selectionStart, e = this.selectionEnd;
          this.value = (this.value || '').toUpperCase();
          if (s !== null && e !== null && typeof this.setSelectionRange === 'function') {
            this.setSelectionRange(s, e);
          }
        });
      }
    });
    SmartSpellChecker.attach('modalReviewItemName', 'spellSuggestReviewItemName');
    SmartSpellChecker.attach('modalReqItemName', 'spellSuggestReqItemName');
  }, 400);
});

// =========================================================================
// REGISTERED MASTER CODES TAB MANAGEMENT (FILTERS, LIVE SEARCH, RELOAD)
// =========================================================================
let masterCodeSearchDebounce = null;

window.onMasterCodeSearchInput = function () {
  clearTimeout(masterCodeSearchDebounce);
  masterCodeSearchDebounce = setTimeout(() => {
    reloadMasterCodesTable();
  }, 250);
};

window.onMasterCodeTier1FilterChange = function () {
  const t1Select = document.getElementById('masterCodeFilterTier1');
  const t2Select = document.getElementById('masterCodeFilterTier2');
  if (!t1Select || !t2Select) return;

  const t1Val = t1Select.value;
  const tiers = window.APP_DATA?.tierHierarchy || { tier1: [], tier2: {}, tier3: {} };
  const selectedOption = t1Select.options[t1Select.selectedIndex];
  const t1DbId = selectedOption ? selectedOption.getAttribute('data-db-id') : null;

  t2Select.innerHTML = '<option value="">All 2nd</option>';
  if (t1Val && t1DbId && tiers.tier2 && tiers.tier2[t1DbId]) {
    tiers.tier2[t1DbId].forEach(t2 => {
      const opt = document.createElement('option');
      opt.value = t2.code_val;
      opt.textContent = String(t2.code_val).padStart(2, '0');
      t2Select.appendChild(opt);
    });
    t2Select.disabled = false;
    t2Select.style.background = '#ffffff';
    t2Select.style.opacity = '1';
  } else {
    t2Select.disabled = true;
    t2Select.style.background = '#f1f5f9';
    t2Select.style.opacity = '0.7';
  }
  reloadMasterCodesTable();
};

window.onMasterCodeTier2FilterChange = function () {
  reloadMasterCodesTable();
};

window.onMasterCodeStatusFilterChange = function () {
  reloadMasterCodesTable();
};

window.resetMasterCodeFilters = function () {
  const sInput = document.getElementById('masterCodeSearchInput');
  const t1 = document.getElementById('masterCodeFilterTier1');
  const t2 = document.getElementById('masterCodeFilterTier2');
  const bothRadio = document.querySelector('input[name="masterCodeStatusRadio"][value="both"]');
  if (sInput) sInput.value = '';
  if (t1) t1.value = '';
  if (t2) {
    t2.innerHTML = '<option value="">All 2nd</option>';
    t2.disabled = true;
    t2.style.background = '#f1f5f9';
    t2.style.opacity = '0.7';
  }
  if (bothRadio) {
    bothRadio.checked = true;
  }
  reloadMasterCodesTable();
};

// =========================================================================
// REGISTERED MASTER CODES TAB MANAGEMENT (HIGH PERFORMANCE PAGINATED ENGINE)
// =========================================================================
let masterCodesAllItems = null;        // Full cached array of master library items
let masterCodesFilteredItems = [];     // Currently filtered slice
let masterCodesCurrentPage = 1;        // Current active page
let masterCodesPageSize = 50;          // Rows per page (50, 100, 250, 500)
let masterCodesIsLoading = false;      // Loading lock

window.onMasterCodePageSizeChange = function (newSize) {
  masterCodesPageSize = parseInt(newSize, 10) || 50;
  masterCodesCurrentPage = 1;
  renderMasterCodesPage();
};

window.changeMasterCodesPage = function (targetPage) {
  const totalPages = Math.max(1, Math.ceil(masterCodesFilteredItems.length / masterCodesPageSize));
  if (targetPage < 1) targetPage = 1;
  if (targetPage > totalPages) targetPage = totalPages;
  masterCodesCurrentPage = targetPage;
  renderMasterCodesPage();

  // Smoothly scroll table container back to top
  const tableContainer = document.querySelector('#tab-define-code .card div[style*="overflow-y"]');
  if (tableContainer) tableContainer.scrollTop = 0;
};

window.jumpToMasterCodePage = function () {
  const input = document.getElementById('masterCodeJumpPageInput');
  if (!input) return;
  const p = parseInt(input.value, 10);
  if (!isNaN(p)) {
    changeMasterCodesPage(p);
  }
};

window.renderMasterCodesPage = function () {
  const tbody = document.getElementById('masterCodesTableBody');
  const countEl = document.getElementById('masterCodeResultCountText');
  const pagInfoEl = document.getElementById('masterCodePaginationInfo');
  const pagBtnsEl = document.getElementById('masterCodePaginationButtons');
  const jumpInput = document.getElementById('masterCodeJumpPageInput');
  const statTotalEl = document.getElementById('statTotalMasterCodes');
  if (!tbody) return;

  const totalItems = masterCodesFilteredItems.length;
  const totalPages = Math.max(1, Math.ceil(totalItems / masterCodesPageSize));

  if (masterCodesCurrentPage > totalPages) masterCodesCurrentPage = totalPages;
  if (masterCodesCurrentPage < 1) masterCodesCurrentPage = 1;

  const startIdx = (masterCodesCurrentPage - 1) * masterCodesPageSize;
  const endIdx = Math.min(startIdx + masterCodesPageSize, totalItems);
  const pageItems = masterCodesFilteredItems.slice(startIdx, endIdx);

  // Update Result Count Texts
  const displayRange = totalItems > 0 ? `${startIdx + 1} - ${endIdx}` : '0 - 0';
  const infoText = `Showing ${displayRange} of ${totalItems.toLocaleString()}`;
  if (countEl) countEl.textContent = infoText;
  if (pagInfoEl) pagInfoEl.textContent = `${infoText} (Page ${masterCodesCurrentPage} of ${totalPages})`;
  if (jumpInput) {
    jumpInput.max = totalPages;
    jumpInput.placeholder = String(masterCodesCurrentPage);
  }

  // Update total master count card if search/filters are clear
  const search = document.getElementById('masterCodeSearchInput')?.value?.trim();
  const t1 = document.getElementById('masterCodeFilterTier1')?.value;
  const t2 = document.getElementById('masterCodeFilterTier2')?.value;
  if (!search && !t1 && !t2 && statTotalEl && masterCodesAllItems) {
    const masterOnlyCount = masterCodesAllItems.filter(i => Number(i.is_added_to_master) > 0).length;
    statTotalEl.textContent = masterOnlyCount.toLocaleString();
  }

  // If empty
  if (pageItems.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="10" style="text-align: center; padding: 28px; color: #94a3b8; font-size: 0.88rem;">
          No registered master codes match your search criteria.
        </td>
      </tr>
    `;
    if (pagBtnsEl) pagBtnsEl.innerHTML = '';
    return;
  }

  // High Performance Batch Render (Clean CSS, zero inline hover event listeners)
  tbody.innerHTML = pageItems.map((it, idx) => {
    const t1Str = String(it.tier1).padStart(2, '0');
    const t2Str = String(it.tier2).padStart(2, '0');
    const t3Str = String(it.tier3).padStart(2, '0');
    const t4Str = String(it.tier4_seq).padStart(3, '0');
    const rawCode = it.raw_code || (t1Str + t2Str + t3Str + t4Str);
    const origDesc = it.orig_description || it.item_name || '';
    const origUom = it.orig_uom || it.uom || 'NO';
    const isMaster = Number(it.is_added_to_master) > 0;
    const isCodeAdmin = Boolean(window.APP_DATA?.isCodeAdmin || window.APP_DATA?.userRole === 'code_admin');
    const masterName = isMaster ? (it.master_db_name || it.item_name || '') : (it.master_db_name || '<span style="color: #94a3b8; font-style: italic;">(Pending Master Commit)</span>');
    const masterUom = isMaster ? (it.master_db_uom || it.uom || 'NO') : (it.master_db_uom || '-');
    const reqId = it.request_id || 0;
    const bgRow = (idx % 2 === 0) ? '#eef6fc' : '#f8fafc';

    const code1Cell = isMaster
      ? `<td style="padding: 3px 6px; text-align: center; border: 1px solid #cbd5e1; background: #ea580c;">
           <span style="display: block; width: 100%; color: #ffffff; font-family: monospace; font-size: 0.82rem; font-weight: 700; padding: 2px 4px; letter-spacing: 0.5px;">${escapeHtml(rawCode)}</span>
         </td>`
      : `<td style="padding: 3px 6px; text-align: center; border: 1px solid #cbd5e1;">
           <span style="display: block; width: 100%; color: #94a3b8; font-family: monospace; font-size: 0.82rem; font-weight: 500;">${escapeHtml(rawCode)}</span>
         </td>`;

    const actionCell = `
      <div style="display: inline-flex; gap: 4px; align-items: center; justify-content: center;">
        ${isMaster
        ? `<span style="font-size: 0.7rem; font-weight: 700; color: #15803d; background: #dcfce7; border: 1px solid #bbf7d0; padding: 2px 6px; border-radius: 3px;">ACTIVE</span>`
        : `<button type="button" class="btn btn-sm" onclick="executeDirectAddToMaster(${reqId});"
               style="font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 3px; background: #ea580c; color: #ffffff; border: 1px solid #c2410c; cursor: pointer; white-space: nowrap;" title="Click to officially commit this code into the Central Master Database">
               Add to Master
             </button>`
      }
        <button type="button" class="btn btn-sm"
          data-master-id="${Number(it.master_id || 0)}"
          data-request-id="${Number(it.request_id || 0)}"
          data-code="${escapeHtml(rawCode)}"
          data-name="${escapeHtml(it.master_db_name || it.item_name || '')}"
          data-uom="${escapeHtml(it.master_db_uom || (isMaster ? (it.uom || 'NOS') : (it.orig_uom || 'NOS')))}"
          data-orig-desc="${escapeHtml(origDesc)}"
          onclick="openUpdateMasterItemModal(this);"
          style="font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 3px; background: #0284c7; color: #ffffff; border: 1px solid #0369a1; cursor: pointer; white-space: nowrap;" title="Update Name and Unit of Measurement">
          Update
        </button>
      </div>
    `;

    return `
      <tr style="border-bottom: 1px solid #d1d5db; background: ${bgRow};">
        <td style="padding: 4px 6px; text-align: center; font-family: monospace; font-size: 0.8rem; font-weight: 600; color: #334155; border: 1px solid #cbd5e1;">
          ${t1Str}
        </td>
        <td style="padding: 4px 6px; text-align: center; font-family: monospace; font-size: 0.8rem; font-weight: 600; color: #334155; border: 1px solid #cbd5e1;">
          ${t2Str}
        </td>
        <td style="padding: 4px 6px; text-align: center; font-family: monospace; font-size: 0.8rem; font-weight: 600; color: #334155; border: 1px solid #cbd5e1;">
          ${t3Str}
        </td>
        <td style="padding: 4px 6px; text-align: center; font-family: monospace; font-size: 0.8rem; font-weight: 600; color: #0f172a; border: 1px solid #cbd5e1; background: linear-gradient(135deg, #16a34a 4px, transparent 4px);">
          ${t4Str}
        </td>
        <td style="padding: 4px 8px; font-weight: 600; color: #0f172a; border: 1px solid #cbd5e1; text-transform: uppercase; word-break: break-word;">
          <div style="display: flex; justify-content: space-between; align-items: center; gap: 4px;">
            <span>${escapeHtml(origDesc)}</span>
            ${isCodeAdmin ? `
              <button type="button" class="btn-search-globe"
                data-query="${escapeHtml(origDesc)}"
                onclick="openGoogleSearch(this, event);"
                style="background: transparent; border: none; cursor: pointer; font-size: 0.78rem; padding: 0 2px; color: #0284c7;"
                title="Search original description on Google">
                🌐
              </button>
            ` : ''}
          </div>
        </td>
        <td style="padding: 4px 6px; text-align: center; color: #475569; font-size: 0.78rem; border: 1px solid #cbd5e1; text-transform: uppercase;">
          ${escapeHtml(origUom)}
        </td>
        ${code1Cell}
        <td style="padding: 4px 8px; font-weight: 600; color: #1e293b; border: 1px solid #cbd5e1; text-transform: uppercase; word-break: break-word;">
          ${isMaster ? escapeHtml(it.master_db_name || it.item_name || '') : masterName}
        </td>
        <td style="padding: 4px 6px; text-align: center; color: #475569; font-size: 0.78rem; border: 1px solid #cbd5e1; text-transform: uppercase;">
          ${escapeHtml(masterUom)}
        </td>
        <td style="padding: 4px 6px; text-align: center; border: 1px solid #cbd5e1; white-space: nowrap;">
          ${actionCell}
        </td>
      </tr>
    `;
  }).join('');

  // Render Smart Sliding Window Pagination Controls Buttons
  if (pagBtnsEl) {
    let btnsHtml = '';
    const cur = masterCodesCurrentPage;

    // First & Prev Buttons
    btnsHtml += `<button type="button" class="master-page-btn" onclick="changeMasterCodesPage(1);" ${cur === 1 ? 'disabled' : ''} title="First Page">⏮ First</button>`;
    btnsHtml += `<button type="button" class="master-page-btn" onclick="changeMasterCodesPage(${cur - 1});" ${cur === 1 ? 'disabled' : ''} title="Previous Page">◀ Prev</button>`;

    // Page Numbers with sliding window
    const windowSize = 2; // shows 2 before and 2 after
    let startPage = Math.max(1, cur - windowSize);
    let endPage = Math.min(totalPages, cur + windowSize);

    if (startPage > 1) {
      btnsHtml += `<button type="button" class="master-page-btn ${cur === 1 ? 'active' : ''}" onclick="changeMasterCodesPage(1);">1</button>`;
      if (startPage > 2) {
        btnsHtml += `<span style="padding: 0 4px; color: #94a3b8;">...</span>`;
      }
    }

    for (let p = startPage; p <= endPage; p++) {
      btnsHtml += `<button type="button" class="master-page-btn ${cur === p ? 'active' : ''}" onclick="changeMasterCodesPage(${p});">${p}</button>`;
    }

    if (endPage < totalPages) {
      if (endPage < totalPages - 1) {
        btnsHtml += `<span style="padding: 0 4px; color: #94a3b8;">...</span>`;
      }
      btnsHtml += `<button type="button" class="master-page-btn ${cur === totalPages ? 'active' : ''}" onclick="changeMasterCodesPage(${totalPages});">${totalPages}</button>`;
    }

    // Next & Last Buttons
    btnsHtml += `<button type="button" class="master-page-btn" onclick="changeMasterCodesPage(${cur + 1});" ${cur === totalPages ? 'disabled' : ''} title="Next Page">Next ▶</button>`;
    btnsHtml += `<button type="button" class="master-page-btn" onclick="changeMasterCodesPage(${totalPages});" ${cur === totalPages ? 'disabled' : ''} title="Last Page">Last ⏭</button>`;

    pagBtnsEl.innerHTML = btnsHtml;
  }
};

window.reloadMasterCodesTable = async function (keepPage = false, forceRefresh = false) {
  const tbody = document.getElementById('masterCodesTableBody');
  if (!tbody) return;

  const search = (document.getElementById('masterCodeSearchInput')?.value || '').trim().toLowerCase();
  const t1 = document.getElementById('masterCodeFilterTier1')?.value || '';
  const t2 = document.getElementById('masterCodeFilterTier2')?.value || '';
  const statusRadio = document.querySelector('input[name="masterCodeStatusRadio"]:checked')?.value || 'both';

  // 1. Fetch data from server if not cached or forceRefresh requested
  if (!masterCodesAllItems || forceRefresh) {
    if (masterCodesIsLoading) return;
    masterCodesIsLoading = true;
    tbody.innerHTML = `<tr><td colspan="10" style="text-align:center; padding: 24px; color: #64748b;">⚡ Loading master codes library...</td></tr>`;

    try {
      const metaBase = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
      const url = `${metaBase}/api/code_management.php?action=list_master_items&limit=20000`;
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (res.ok) {
        const data = await res.json();
        masterCodesAllItems = (data && data.items) ? data.items : [];
      } else {
        masterCodesAllItems = [];
      }
    } catch (err) {
      console.warn('Failed to fetch master codes:', err);
      masterCodesAllItems = [];
    } finally {
      masterCodesIsLoading = false;
    }
  }

  // 2. High-speed In-Memory Filter (< 3 milliseconds for 10,960 records!)
  if (!masterCodesAllItems) masterCodesAllItems = [];

  masterCodesFilteredItems = masterCodesAllItems.filter(it => {
    if (t1 && String(it.tier1) !== String(t1)) return false;
    if (t2 && String(it.tier2) !== String(t2)) return false;

    const isMaster = Number(it.is_added_to_master) > 0;
    if (statusRadio === 'master_added' && !isMaster) return false;
    if (statusRadio === 'pending_master' && isMaster) return false;

    if (!search) return true;

    const raw = (it.raw_code || '').toLowerCase();
    const name = (it.item_name || '').toLowerCase();
    const orig = (it.orig_description || '').toLowerCase();
    const dbName = (it.master_db_name || '').toLowerCase();
    const st = (it.station_code || '').toLowerCase();
    const stName = (it.station_name || '').toLowerCase();
    const specs = (it.specifications || '').toLowerCase();
    const combined = `${raw} ${name} ${orig} ${dbName} ${st} ${stName} ${specs}`;
    const searchWords = search.split(/\s+/).filter(Boolean);
    return searchWords.every(w => combined.includes(w));
  });

  if (!keepPage) {
    masterCodesCurrentPage = 1;
  }

  // 3. Render the active page
  renderMasterCodesPage();
};

// Background pre-fetch on page load for instantaneous search and pagination
document.addEventListener('DOMContentLoaded', function () {
  setTimeout(() => {
    if (document.getElementById('masterCodesTableBody')) {
      reloadMasterCodesTable(true);
    }
  }, 200);
});

window.openUpdateMasterItemModal = function (itemDataOrEl) {
  if (!itemDataOrEl) return;
  let itemData = itemDataOrEl;
  if (itemDataOrEl instanceof Element || (itemDataOrEl && itemDataOrEl.nodeType === 1)) {
    itemData = {
      masterId: itemDataOrEl.getAttribute('data-master-id') || 0,
      requestId: itemDataOrEl.getAttribute('data-request-id') || 0,
      code: itemDataOrEl.getAttribute('data-code') || '',
      name: itemDataOrEl.getAttribute('data-name') || '',
      uom: itemDataOrEl.getAttribute('data-uom') || '',
      origDesc: itemDataOrEl.getAttribute('data-orig-desc') || ''
    };
  }
  const masterIdInput = document.getElementById('modalUpdateMasterId');
  const reqIdInput = document.getElementById('modalUpdateRequestId');
  const codeBadge = document.getElementById('modalUpdateItemCodeBadge');
  const origDescEl = document.getElementById('modalUpdateOrigDesc');
  const nameInput = document.getElementById('modalUpdateItemName');
  const uomInput = document.getElementById('modalUpdateUom');

  if (masterIdInput) masterIdInput.value = itemData.masterId || '';
  if (reqIdInput) reqIdInput.value = itemData.requestId || '';
  if (codeBadge) codeBadge.textContent = 'Code: ' + (itemData.code || '-');
  if (origDescEl) origDescEl.textContent = itemData.origDesc || (itemData.name || '-');
  if (nameInput) {
    nameInput.value = (itemData.name || '').toUpperCase();
    setTimeout(() => nameInput.focus(), 150);
  }
  if (uomInput) {
    uomInput.value = (itemData.uom || 'NOS').toUpperCase();
  }

  if (typeof window.openModal === 'function') {
    window.openModal('updateMasterItemModal');
  }
};

window.executeSaveMasterItemUpdate = async function (e) {
  if (e) e.preventDefault();
  const form = document.getElementById('updateMasterItemForm');
  const submitBtn = document.getElementById('btnSaveMasterItemUpdate');
  if (!form) return;

  const itemName = document.getElementById('modalUpdateItemName')?.value?.trim();
  const uom = document.getElementById('modalUpdateUom')?.value?.trim();
  if (!itemName) {
    App.toast('Master Database Name is required.', 'error');
    return;
  }
  if (!uom) {
    App.toast('Unit of Measurement (UoM) is required.', 'error');
    return;
  }

  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.textContent = 'Saving...';
  }

  try {
    const formData = new FormData(form);
    formData.append('action', 'update_master_item');
    if (!formData.get('_csrf_token')) {
      formData.append('_csrf_token', window.APP_DATA?.csrfToken || '');
    }

    const res = await App.request('/api/code_management.php', {
      method: 'POST',
      body: formData
    });

    if (res.ok && res.data?.success) {
      App.toast(res.data.message || 'Master Item updated successfully!', 'success');
      if (typeof window.closeModal === 'function') {
        window.closeModal('updateMasterItemModal');
      }
      if (typeof reloadMasterCodesTable === 'function') {
        reloadMasterCodesTable();
      }
      if (typeof loadAdminRequestsQueue === 'function') {
        loadAdminRequestsQueue();
      }
      if (typeof loadStationInventory === 'function') {
        loadStationInventory();
      }
    } else {
      App.toast(res.data?.message || 'Failed to update master item.', 'error');
    }
  } catch (err) {
    App.toast(`Error: ${err.message}`, 'error');
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Save & Update';
    }
  }
};

window.executeDirectAddToMaster = async function (requestId) {
  if (!requestId) return;
  if (!confirm('Are you sure you want to add this item to the Central FIFO Master Database? It will immediately become highlighted in ORANGE and available for all other Power Stations.')) {
    return;
  }
  try {
    const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
    const formData = new FormData();
    formData.append('action', 'add_to_master');
    formData.append('_csrf_token', window.APP_DATA?.csrfToken || '');
    formData.append('request_id', requestId);

    const res = await App.request('/api/code_management.php', {
      method: 'POST',
      body: formData
    });

    if (res.ok && res.data?.success) {
      App.toast(res.data.message || 'Item added to Central Master Database successfully!', 'success');
      reloadMasterCodesTable();
      if (typeof loadAdminRequestsQueue === 'function') loadAdminRequestsQueue();
      if (typeof onCodeGenTier3Change === 'function') onCodeGenTier3Change();
      if (typeof loadStationInventory === 'function') loadStationInventory();
      if (typeof loadStationRequests === 'function') loadStationRequests();
      if (typeof loadDefineCodeCatalog === 'function') loadDefineCodeCatalog();
    } else {
      App.toast(res.data?.message || 'Failed to add item to master database.', 'error');
    }
  } catch (err) {
    App.toast(`Error: ${err.message}`, 'error');
  }
};

/**
 * ===================================================/**
 * =========================================================================
 * CODE ADMIN: EXCEL HIERARCHY TREE MANAGER (1st, 2nd & 3rd CODES)
 * =========================================================================
 */
let _excelTierCache = null;
let _excelSearchDebounce = null;
let _tierSpellingDebounce = null;
window._currentTierSpellingCorrection = null;

async function initDefineCodeManager() {
  const tableBody = document.getElementById('excelHierarchyTableBody');
  if (!tableBody) return;

  // Initial load of categories cache & Excel hierarchy
  await refreshTierCache();
  loadExcelHierarchyTree();
}

/**
 * Fetch and cache all grouped tiers
 */
async function refreshTierCache() {
  try {
    const res = await App.request('/api/code_management.php?action=get_tiers');
    if (res.ok && res.data) {
      _excelTierCache = res.data;
      populateTierParentOptions();
    }
  } catch (e) {
    console.warn('Could not load tier cache:', e);
  }
}

/**
 * Populate parent category select dropdown based on current selected level
 */
function populateTierParentOptions(selectedParentId = null) {
  const tier1Select = document.getElementById('tier1StepSelect');
  if (!tier1Select) return;

  const currentVal = selectedParentId || tier1Select.value || _wizardState.t1Id;
  const tier1List = _excelTierCache?.tier1 || [];
  tier1Select.innerHTML = '<option value="">Select 1st Topic...</option>' +
    tier1List.map(t1 => {
      const codePad = String(t1.code_val).padStart(2, '0');
      return `<option value="${t1.id}">${codePad}. ${escapeHtml(t1.name)}</option>`;
    }).join('');

  if (currentVal && tier1List.some(t => String(t.id) === String(currentVal))) {
    tier1Select.value = currentVal;
  } else if (tier1List.length > 0 && !tier1Select.value) {
    tier1Select.selectedIndex = 1;
  }
}

/**
 * Progressive Code Creation Wizard State
 */
let _wizardState = {
  step: 1,
  t1Id: null,
  t1Code: '',
  t1Name: '',
  t2Id: null,
  t2Code: '',
  t2Name: '',
  t3Count: 0
};

/**
 * Switch Add Mode between 1st, 2nd, and 3rd Code via Radio Buttons / Tabs
 */
window.switchTierAddMode = function (level) {
  _wizardState.step = level;
  const levelInput = document.getElementById('tierNodeLevel');
  const parentInput = document.getElementById('tierNodeParent');
  const r1 = document.getElementById('radioTierMode1');
  const r2 = document.getElementById('radioTierMode2');
  const r3 = document.getElementById('radioTierMode3');
  const lbl1 = document.getElementById('lblRadioMode1');
  const lbl2 = document.getElementById('lblRadioMode2');
  const lbl3 = document.getElementById('lblRadioMode3');

  const title = document.getElementById('tierEditBarTitle');
  const c1 = document.getElementById('tier1StepContainer');
  const c2 = document.getElementById('tier2StepContainer');
  const contextBanner = document.getElementById('tierWizardContextBanner');
  const contextText = document.getElementById('tierWizardContextText');
  const codeValLabel = document.getElementById('tierCodeValLabel');
  const nameLabel = document.getElementById('tierNodeNameLabel');
  const nameInput = document.getElementById('tierNodeName');
  const tier1Select = document.getElementById('tier1StepSelect');
  const tier2Select = document.getElementById('tier2StepSelect');
  const saveBtn = document.getElementById('btnSaveTierNode');
  const saveOnlyBtn = document.getElementById('btnSaveOnlyDone');
  const submitAllBtn = document.getElementById('btnSubmitAllDone');

  if (levelInput) levelInput.value = String(level);

  // Sync radio buttons and labels
  if (level === 1 && r1) r1.checked = true;
  if (level === 2 && r2) r2.checked = true;
  if (level === 3 && r3) r3.checked = true;

  [[r1, lbl1], [r2, lbl2], [r3, lbl3]].forEach(([r, lbl], idx) => {
    if (!lbl) return;
    if (idx + 1 === level) {
      lbl.style.background = '#650b2b';
      lbl.style.color = '#ffffff';
      lbl.style.borderColor = '#4a071e';
    } else {
      lbl.style.background = '#ffffff';
      lbl.style.color = '#334155';
      lbl.style.borderColor = '#cbd5e1';
    }
  });

  populateTierParentOptions();

  if (level === 1) {
    if (c1) c1.style.display = 'none';
    if (c2) c2.style.display = 'none';
    if (contextBanner) contextBanner.style.display = 'none';
    if (parentInput) parentInput.value = '';
    if (title) title.textContent = '1ST CODE: DEFINE MAIN TOPIC';
    if (codeValLabel) codeValLabel.innerHTML = '1st Code No. <span style="color:#dc2626;">*</span>';
    if (nameLabel) nameLabel.innerHTML = '1st Main Topic Name <span style="color:#dc2626;">*</span>';
    if (nameInput) nameInput.placeholder = 'e.g. ADHESIVE, SEALANT, BONDING COMPOUND';
    if (saveBtn) {
      saveBtn.innerHTML = 'Save 1st Code &amp; Next to 2nd ➔';
      saveBtn.style.display = 'inline-block';
    }
    if (saveOnlyBtn) {
      saveOnlyBtn.innerHTML = 'Save 1st Code Only (Done)';
      saveOnlyBtn.style.display = 'inline-block';
    }
    if (submitAllBtn) submitAllBtn.style.display = 'none';
    suggestNextTierCodeVal();
    updateLiveCodePreview();
  } else if (level === 2) {
    if (c1) c1.style.display = 'block';
    if (c2) c2.style.display = 'none';
    if (title) title.textContent = '2ND CODE: DEFINE SUB TOPIC';
    if (codeValLabel) codeValLabel.innerHTML = '2nd Code No. <span style="color:#dc2626;">*</span>';
    if (nameLabel) nameLabel.innerHTML = '2nd Sub Topic Name <span style="color:#dc2626;">*</span>';
    if (nameInput) nameInput.placeholder = 'e.g. ADHESIVE or SEALANT';
    if (saveBtn) {
      saveBtn.innerHTML = 'Save 2nd Code &amp; Next to 3rd ➔';
      saveBtn.style.display = 'inline-block';
    }
    if (saveOnlyBtn) {
      saveOnlyBtn.innerHTML = 'Save 2nd Code Only (Done)';
      saveOnlyBtn.style.display = 'inline-block';
    }
    if (submitAllBtn) submitAllBtn.style.display = 'none';

    // Set 1st selection from wizard state or dropdown
    if (tier1Select) {
      if (_wizardState.t1Id) {
        tier1Select.value = _wizardState.t1Id;
      } else if (tier1Select.options.length > 1 && !tier1Select.value) {
        tier1Select.selectedIndex = 1;
      }
    }

    if (contextBanner && contextText && tier1Select?.value) {
      const t1Obj = (_excelTierCache?.tier1 || []).find(t => String(t.id) === String(tier1Select.value));
      if (t1Obj) {
        contextText.innerHTML = `<strong>1st Topic:</strong> ${String(t1Obj.code_val).padStart(2, '0')}. ${escapeHtml(t1Obj.name)}`;
        contextBanner.style.display = 'block';
      }
    }

    onStep1Tier1Change();
  } else if (level === 3) {
    if (c1) c1.style.display = 'block';
    if (c2) c2.style.display = 'block';
    if (title) title.textContent = '3RD CODE: ADD CATEGORY ITEMS';
    if (codeValLabel) codeValLabel.innerHTML = '3rd Code No. <span style="color:#dc2626;">*</span>';
    if (nameLabel) nameLabel.innerHTML = '3rd Category Description <span style="color:#dc2626;">*</span>';
    if (nameInput) nameInput.placeholder = 'e.g. INSTANT ADHESIVE, BINDING GUM, etc.';
    if (saveBtn) {
      saveBtn.innerHTML = 'Save 3rd Code &amp; Add Another (+)';
      saveBtn.style.display = 'inline-block';
    }
    if (saveOnlyBtn) {
      saveOnlyBtn.innerHTML = 'Save 3rd Code &amp; Finish (Done)';
      saveOnlyBtn.style.display = 'inline-block';
    }
    if (submitAllBtn) {
      submitAllBtn.style.display = 'none';
    }

    if (tier1Select) {
      if (_wizardState.t1Id) {
        tier1Select.value = _wizardState.t1Id;
      } else if (tier1Select.options.length > 1 && !tier1Select.value) {
        tier1Select.selectedIndex = 1;
      }
    }
    onStep1Tier1Change();

    if (tier2Select && _wizardState.t2Id) {
      tier2Select.value = _wizardState.t2Id;
      onStep2Tier2Change();
    }

    if (contextBanner && contextText && tier1Select?.value && tier2Select?.value) {
      const t1Obj = (_excelTierCache?.tier1 || []).find(t => String(t.id) === String(tier1Select.value));
      const t2List = _excelTierCache?.tier2?.[tier1Select.value] || [];
      const t2Obj = t2List.find(t => String(t.id) === String(tier2Select.value));
      if (t1Obj && t2Obj) {
        contextText.innerHTML = `<strong>Active Branch:</strong> ${String(t1Obj.code_val).padStart(2, '0')}.${String(t2Obj.code_val).padStart(2, '0')}. ${escapeHtml(t2Obj.name)} (under ${escapeHtml(t1Obj.name)})`;
        contextBanner.style.display = 'block';
      }
    }
  }
};

/**
 * Handle Selection of 1st Topic in Add Bar
 */
window.onStep1Tier1Change = function () {
  const level = parseInt(document.getElementById('tierNodeLevel')?.value || '1', 10);
  const tier1Select = document.getElementById('tier1StepSelect');
  const tier2Select = document.getElementById('tier2StepSelect');
  const parentInput = document.getElementById('tierNodeParent');
  const contextBanner = document.getElementById('tierWizardContextBanner');
  const contextText = document.getElementById('tierWizardContextText');
  const selectedT1Id = tier1Select ? tier1Select.value : null;

  if (selectedT1Id) {
    _wizardState.t1Id = selectedT1Id;
    const t1Obj = (_excelTierCache?.tier1 || []).find(t => String(t.id) === String(selectedT1Id));
    if (t1Obj) {
      _wizardState.t1Code = String(t1Obj.code_val).padStart(2, '0');
      _wizardState.t1Name = t1Obj.name;
    }
  }

  if (level === 2) {
    if (parentInput) parentInput.value = selectedT1Id || '';
    if (contextBanner && contextText && selectedT1Id) {
      contextText.innerHTML = `<strong>1st Topic:</strong> ${_wizardState.t1Code}. ${escapeHtml(_wizardState.t1Name)}`;
      contextBanner.style.display = 'block';
    }
    suggestNextTierCodeVal();
    updateLiveCodePreview();
  } else if (level === 3) {
    if (!tier2Select) return;
    if (!selectedT1Id) {
      tier2Select.innerHTML = '<option value="">Select 1st Topic First...</option>';
      if (parentInput) parentInput.value = '';
      updateLiveCodePreview();
      return;
    }

    const t2List = _excelTierCache?.tier2?.[selectedT1Id] || [];
    if (t2List.length === 0) {
      tier2Select.innerHTML = '<option value="">(No 2nd Sub Topics found - Add 2nd code first)</option>';
      if (parentInput) parentInput.value = '';
      suggestNextTierCodeVal();
      updateLiveCodePreview();
      return;
    }

    tier2Select.innerHTML = '<option value="">Select 2nd Topic...</option>' +
      t2List.map(t2 => {
        const codePad = String(t2.code_val).padStart(2, '0');
        return `<option value="${t2.id}">${codePad}. ${escapeHtml(t2.name)}</option>`;
      }).join('');

    if (_wizardState.t2Id && t2List.some(t => String(t.id) === String(_wizardState.t2Id))) {
      tier2Select.value = _wizardState.t2Id;
    } else {
      tier2Select.selectedIndex = 1;
    }
    onStep2Tier2Change();
  }
};

/**
 * Handle Selection of 2nd Topic in Add Bar
 */
window.onStep2Tier2Change = function () {
  const tier2Select = document.getElementById('tier2StepSelect');
  const parentInput = document.getElementById('tierNodeParent');
  const contextBanner = document.getElementById('tierWizardContextBanner');
  const contextText = document.getElementById('tierWizardContextText');
  const selectedT2Id = tier2Select ? tier2Select.value : null;

  if (selectedT2Id) {
    _wizardState.t2Id = selectedT2Id;
    const t1Id = document.getElementById('tier1StepSelect')?.value;
    const t2List = (t1Id && _excelTierCache?.tier2?.[t1Id]) ? _excelTierCache.tier2[t1Id] : [];
    const t2Obj = t2List.find(t => String(t.id) === String(selectedT2Id));
    if (t2Obj) {
      _wizardState.t2Code = String(t2Obj.code_val).padStart(2, '0');
      _wizardState.t2Name = t2Obj.name;
    }
  }

  if (parentInput) parentInput.value = selectedT2Id || '';

  if (contextBanner && contextText && _wizardState.t1Name && _wizardState.t2Name) {
    contextText.innerHTML = `<strong>Active Branch:</strong> ${_wizardState.t1Code}.${_wizardState.t2Code}. ${escapeHtml(_wizardState.t2Name)} (under ${escapeHtml(_wizardState.t1Name)})`;
    contextBanner.style.display = 'block';
  }

  suggestNextTierCodeVal();
  updateLiveCodePreview();
};

/**
 * Automatically calculate and suggest next code number for selected level/parent
 */
function suggestNextTierCodeVal() {
  const isEditing = document.getElementById('tierNodeId')?.value;
  if (isEditing) return; // Keep existing code when editing

  const level = parseInt(document.getElementById('tierNodeLevel')?.value || '1', 10);
  const parentId = document.getElementById('tierNodeParent')?.value;
  const codeValInput = document.getElementById('tierNodeCodeVal');
  if (!codeValInput) return;

  let maxVal = 0;

  if (level === 1) {
    const t1List = _excelTierCache?.tier1 || [];
    t1List.forEach(t => {
      const v = parseInt(t.code_val, 10);
      if (!isNaN(v) && v > maxVal) maxVal = v;
    });
  } else if (level === 2 && parentId) {
    const t2List = _excelTierCache?.tier2?.[parentId] || [];
    t2List.forEach(t => {
      const v = parseInt(t.code_val, 10);
      if (!isNaN(v) && v > maxVal) maxVal = v;
    });
  } else if (level === 3 && parentId) {
    const t3List = _excelTierCache?.tier3?.[parentId] || [];
    t3List.forEach(t => {
      const v = parseInt(t.code_val, 10);
      if (!isNaN(v) && v > maxVal) maxVal = v;
    });
  }

  codeValInput.value = maxVal + 1;
  updateLiveCodePreview();
}

/**
 * Update Live Code Prefix Preview Badge
 */
window.updateLiveCodePreview = function () {
  const level = parseInt(document.getElementById('tierNodeLevel')?.value || '1', 10);
  const codeVal = parseInt(document.getElementById('tierNodeCodeVal')?.value || '1', 10);
  const badge = document.getElementById('tierLivePreviewBadge');
  if (!badge) return;

  const codePad = String(isNaN(codeVal) ? 1 : codeVal).padStart(2, '0');

  if (level === 1) {
    badge.textContent = `${codePad}.`;
  } else if (level === 2) {
    const t1Select = document.getElementById('tier1StepSelect');
    const t1Id = t1Select ? t1Select.value : null;
    const t1Obj = (_excelTierCache?.tier1 || []).find(t => String(t.id) === String(t1Id));
    const t1Pad = t1Obj ? String(t1Obj.code_val).padStart(2, '0') : '01';
    badge.textContent = `${t1Pad}.${codePad}.`;
  } else if (level === 3) {
    const t1Select = document.getElementById('tier1StepSelect');
    const t2Select = document.getElementById('tier2StepSelect');
    const t1Id = t1Select ? t1Select.value : null;
    const t2Id = t2Select ? t2Select.value : null;

    const t1Obj = (_excelTierCache?.tier1 || []).find(t => String(t.id) === String(t1Id));
    const t1Pad = t1Obj ? String(t1Obj.code_val).padStart(2, '0') : '01';

    let t2Pad = '01';
    if (t1Id && _excelTierCache?.tier2?.[t1Id]) {
      const t2Obj = _excelTierCache.tier2[t1Id].find(t => String(t.id) === String(t2Id));
      if (t2Obj) t2Pad = String(t2Obj.code_val).padStart(2, '0');
    }

    badge.textContent = `${t1Pad}.${t2Pad}.${codePad}.`;
  }
};

/**
 * Load Excel Spreadsheet Hierarchy Tree
 */
window.loadExcelHierarchyTree = async function () {
  const tbody = document.getElementById('excelHierarchyTableBody');
  if (!tbody) return;

  const t1Filter = document.getElementById('excelTreeTier1Filter')?.value || '';
  const search = (document.getElementById('excelTreeSearchInput')?.value || '').trim();

  tbody.innerHTML = `
    <tr>
      <td colspan="6" style="text-align: center; padding: 24px; color: #64748b; font-size: 0.84rem;">
        Loading Excel spreadsheet hierarchy...
      </td>
    </tr>
  `;

  try {
    let url = '/api/code_management.php?action=list_tier_hierarchy';
    if (t1Filter) url += `&tier1=${encodeURIComponent(t1Filter)}`;
    if (search) url += `&q=${encodeURIComponent(search)}`;

    const res = await App.request(url);
    if (res.ok && res.data?.rows) {
      renderExcelHierarchyRows(res.data.rows);
    } else {
      tbody.innerHTML = `
        <tr>
          <td colspan="6" style="text-align: center; padding: 24px; color: #dc2626; font-size: 0.84rem;">
            ${res.data?.message || 'Failed to load code hierarchy.'}
          </td>
        </tr>
      `;
    }
  } catch (err) {
    tbody.innerHTML = `
      <tr>
        <td colspan="6" style="text-align: center; padding: 24px; color: #dc2626; font-size: 0.84rem;">
          Error: ${err.message}
        </td>
      </tr>
    `;
  }
};

/**
 * Render Rows matching the User's Excel Sheet
 */
function renderExcelHierarchyRows(rows) {
  const tbody = document.getElementById('excelHierarchyTableBody');
  if (!tbody) return;

  if (!rows || rows.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="6" style="text-align: center; padding: 28px; color: #94a3b8; font-size: 0.85rem;">
          No matching code categories found.
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = rows.map((r, idx) => {
    const level = parseInt(r.level, 10);
    const id = r.id;
    const name = r.name || '';
    const col1 = r.col1 || '';
    const col2 = r.col2 || '';
    const col3 = r.col3 || '';
    const prefix = r.prefix || '';

    // Styling according to Excel hierarchy level
    let rowBg = '#ffffff';
    let fontWeight = '400';
    let textColor = '#0f172a';
    let borderBottom = '1px solid #e2e8f0';

    if (level === 1) {
      rowBg = '#f1f5f9';
      fontWeight = '800';
      textColor = '#0f172a';
      borderBottom = '2px solid #cbd5e1';
    } else if (level === 2) {
      rowBg = '#f8fafc';
      fontWeight = '700';
      textColor = '#1e293b';
      borderBottom = '1px solid #cbd5e1';
    }

    // Excel green top-left corner triangle marker style on code cells
    const excelMarker = "background: linear-gradient(135deg, #16a34a 5px, transparent 5px);";

    return `
      <tr style="background: ${rowBg}; border-bottom: ${borderBottom};" onmouseenter="this.style.backgroundColor='#e2e8f0';" onmouseleave="this.style.backgroundColor='${rowBg}';">
        <!-- Col 1: 01. -->
        <td style="padding: 4px 8px; text-align: left; font-family: monospace; font-size: 0.82rem; font-weight: ${level === 1 ? '800' : '700'}; color: #000000; border: 1px solid #cbd5e1; ${col1 ? excelMarker : ''}">
          ${col1}
        </td>

        <!-- Col 2: 01. -->
        <td style="padding: 4px 8px; text-align: left; font-family: monospace; font-size: 0.82rem; font-weight: ${level === 2 ? '800' : '700'}; color: #000000; border: 1px solid #cbd5e1; ${col2 ? excelMarker : ''}">
          ${col2}
        </td>

        <!-- Col 3: 01. -->
        <td style="padding: 4px 8px; text-align: left; font-family: monospace; font-size: 0.82rem; font-weight: 700; color: #000000; border: 1px solid #cbd5e1; ${col3 ? excelMarker : ''}">
          ${col3}
        </td>

        <!-- Col 4: Combined Prefix (01. / 01.01. / 01.01.01.) -->
        <td style="padding: 4px 10px; text-align: left; font-family: monospace; font-size: 0.84rem; font-weight: 800; color: #000000; border: 1px solid #cbd5e1;">
          ${prefix}
        </td>

        <!-- Col 5: Description / Category Name -->
        <td style="padding: 4px 12px; font-weight: ${fontWeight}; font-size: 0.82rem; color: ${textColor}; border: 1px solid #cbd5e1; text-transform: uppercase;">
          ${escapeHtml(name)}
        </td>

        <!-- Col 6: Action Buttons -->
        <td style="padding: 3px 8px; text-align: center; border: 1px solid #cbd5e1; white-space: nowrap;">
          <button type="button" onclick="editTierNode(${id});" class="btn btn-sm"
            style="font-size: 0.72rem; font-weight: 700; padding: 2px 8px; background: #0284c7; color: #ffffff; border: 1px solid #0369a1; border-radius: 3px; cursor: pointer;">
            Edit
          </button>
          <button type="button" onclick="openDeleteTierModal(${id}, '${escapeHtml(name)}', '${escapeHtml(prefix)}');" class="btn btn-sm"
            style="font-size: 0.72rem; font-weight: 700; padding: 2px 7px; background: #ef4444; color: #ffffff; border: 1px solid #dc2626; border-radius: 3px; cursor: pointer; margin-left: 3px;" title="Delete this code">
            Delete
          </button>
        </td>
      </tr>
    `;
  }).join('');
}

/**
 * Filter & Search input handlers
 */
window.onExcelTreeFilterChange = function () {
  loadExcelHierarchyTree();
};

window.onExcelTreeSearchInput = function () {
  clearTimeout(_excelSearchDebounce);
  _excelSearchDebounce = setTimeout(() => {
    loadExcelHierarchyTree();
  }, 200);
};

/**
 * Show Simple Add Bar
 */
window.showSimpleAddTierBar = function (level = 1, parentId = null) {
  const bar = document.getElementById('simpleTierEditBar');
  const form = document.getElementById('simpleTierForm');
  const idInput = document.getElementById('tierNodeId');
  const saveBtn = document.getElementById('btnSaveTierNode');

  if (!bar || !form) return;

  form.reset();
  if (idInput) idInput.value = '';
  if (saveBtn) saveBtn.innerHTML = 'Save Code';

  switchTierAddMode(level);

  bar.style.display = 'block';
  const nameInput = document.getElementById('tierNodeName');
  if (nameInput) {
    nameInput.focus();
  }
};

/**
 * Hide Simple Add Bar
 */
window.hideSimpleAddTierBar = function () {
  const bar = document.getElementById('simpleTierEditBar');
  const form = document.getElementById('simpleTierForm');
  const chip = document.getElementById('tierSpellingChip');
  if (bar) bar.style.display = 'none';
  if (form) form.reset();
  if (chip) chip.style.display = 'none';
};

/**
 * Edit Tier Node
 */
window.editTierNode = async function (id) {
  if (!id) return;

  try {
    const res = await App.request(`/api/code_management.php?action=get_tier_category&id=${id}`);
    if (!res.ok || !res.data?.category) {
      App.toast('Could not fetch category details.', 'error');
      return;
    }

    const cat = res.data.category;
    const bar = document.getElementById('simpleTierEditBar');
    const idInput = document.getElementById('tierNodeId');
    const codeValInput = document.getElementById('tierNodeCodeVal');
    const nameInput = document.getElementById('tierNodeName');
    const title = document.getElementById('tierEditBarTitle');
    const saveBtn = document.getElementById('btnSaveTierNode');
    const saveOnlyBtn = document.getElementById('btnSaveOnlyDone');
    const submitAllBtn = document.getElementById('btnSubmitAllDone');

    if (!bar) return;

    const level = parseInt(cat.level || 1, 10);
    switchTierAddMode(level);

    if (idInput) idInput.value = cat.id;
    if (title) title.textContent = `EDIT CODE #${cat.id} (${cat.name})`;
    if (saveBtn) saveBtn.innerHTML = 'Update Code';
    if (saveOnlyBtn) saveOnlyBtn.style.display = 'none';
    if (submitAllBtn) submitAllBtn.style.display = 'none';

    if (codeValInput) codeValInput.value = cat.code_val;
    if (nameInput) nameInput.value = cat.name;

    // Handle cascading selectors based on level
    if (level === 2 && cat.parent_id) {
      const t1Select = document.getElementById('tier1StepSelect');
      if (t1Select) {
        t1Select.value = cat.parent_id;
        document.getElementById('tierNodeParent').value = cat.parent_id;
      }
    } else if (level === 3 && cat.parent_id) {
      // Find parent's parent (tier 1) for this tier 2 node
      let foundT1Id = null;
      for (const t1Id in (_excelTierCache?.tier2 || {})) {
        const list = _excelTierCache.tier2[t1Id] || [];
        if (list.some(t2 => String(t2.id) === String(cat.parent_id))) {
          foundT1Id = t1Id;
          break;
        }
      }

      if (foundT1Id) {
        const t1Select = document.getElementById('tier1StepSelect');
        if (t1Select) {
          t1Select.value = foundT1Id;
          onStep1Tier1Change();
          const t2Select = document.getElementById('tier2StepSelect');
          if (t2Select) {
            t2Select.value = cat.parent_id;
            document.getElementById('tierNodeParent').value = cat.parent_id;
          }
        }
      }
    }

    updateLiveCodePreview();

    bar.style.display = 'block';
    bar.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    if (nameInput) nameInput.focus();
  } catch (err) {
    App.toast(`Failed to load category: ${err.message}`, 'error');
  }
};

/**
 * Handle Progressive Wizard Step Save
 */
window.handleWizardSaveCurrentStep = async function (event, advanceStep = true) {
  if (event && event.preventDefault) event.preventDefault();
  const form = document.getElementById('simpleTierForm');
  if (!form) return;

  const level = parseInt(document.getElementById('tierNodeLevel')?.value || '1', 10);
  const isEditing = document.getElementById('tierNodeId')?.value;
  const btn = document.getElementById('btnSaveTierNode');
  const saveOnlyBtn = document.getElementById('btnSaveOnlyDone');
  const nameInput = document.getElementById('tierNodeName');
  const nameVal = nameInput ? nameInput.value.trim() : '';

  if (!nameVal) {
    App.toast('Please enter a description / name.', 'warning');
    return;
  }

  const prevBtnHtml = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = 'Saving...';
  }
  if (saveOnlyBtn) saveOnlyBtn.disabled = true;

  try {
    const formData = new FormData(form);
    formData.append('action', 'save_tier_category');
    formData.append('_csrf_token', window.APP_DATA?.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');

    const res = await App.request('/api/code_management.php', {
      method: 'POST',
      body: formData
    });

    if (res.ok && res.data?.success) {
      const savedId = res.data.id;
      const savedCode = res.data.code_val || document.getElementById('tierNodeCodeVal')?.value;

      await refreshTierCache();
      loadExcelHierarchyTree();

      // If in editing mode, finish and close
      if (isEditing) {
        App.toast(res.data.message || 'Category code updated successfully!', 'success');
        hideSimpleAddTierBar();
        return;
      }

      // If user chose "Save Only (Done)" without advancing to next sub-topic / 3rd code
      if (!advanceStep) {
        const t1Select = document.getElementById('tier1StepSelect');
        const t2Select = document.getElementById('tier2StepSelect');
        const t1Obj = (_excelTierCache?.tier1 || []).find(t => String(t.id) === String(t1Select?.value));
        const t1Pad = t1Obj ? String(t1Obj.code_val).padStart(2, '0') : (_wizardState.t1Code || '01');
        let t2Pad = _wizardState.t2Code || '01';
        if (t1Select?.value && _excelTierCache?.tier2?.[t1Select.value]) {
          const t2Obj = _excelTierCache.tier2[t1Select.value].find(t => String(t.id) === String(t2Select?.value));
          if (t2Obj) t2Pad = String(t2Obj.code_val).padStart(2, '0');
        }

        if (level === 1) {
          App.toast(`1st Code ${String(savedCode).padStart(2, '0')}. "${nameVal}" saved successfully!`, 'success');
        } else if (level === 2) {
          App.toast(`2nd Code ${t1Pad}.${String(savedCode).padStart(2, '0')}. "${nameVal}" saved successfully!`, 'success');
        } else {
          App.toast(`3rd Code ${t1Pad}.${t2Pad}.${String(savedCode).padStart(2, '0')}. "${nameVal}" saved successfully!`, 'success');
        }
        hideSimpleAddTierBar();
        _wizardState = { step: 1, t1Id: null, t1Code: '', t1Name: '', t2Id: null, t2Code: '', t2Name: '', t3Count: 0 };
        return;
      }

      // Step 1 Completed -> Advance to Step 2
      if (level === 1) {
        _wizardState.t1Id = savedId;
        _wizardState.t1Code = String(savedCode).padStart(2, '0');
        _wizardState.t1Name = nameVal;

        App.toast(`1st Code ${String(savedCode).padStart(2, '0')}. "${nameVal}" saved. Now define 2nd sub-topic (or finish).`, 'success');

        // Reset name for step 2
        if (nameInput) nameInput.value = '';
        switchTierAddMode(2);
        if (nameInput) nameInput.focus();
      }
      // Step 2 Completed -> Advance to Step 3
      else if (level === 2) {
        _wizardState.t2Id = savedId;
        _wizardState.t2Code = String(savedCode).padStart(2, '0');
        _wizardState.t2Name = nameVal;

        App.toast(`2nd Code ${_wizardState.t1Code}.${String(savedCode).padStart(2, '0')}. "${nameVal}" saved. You can now add 3rd category items (optional) or finish.`, 'success');

        // Reset name for step 3
        if (nameInput) nameInput.value = '';
        switchTierAddMode(3);
        if (nameInput) nameInput.focus();
      }
      // Step 3 Completed -> Keep adding 3rd category items continuously
      else if (level === 3) {
        _wizardState.t3Count++;

        const t1Select = document.getElementById('tier1StepSelect');
        const t2Select = document.getElementById('tier2StepSelect');
        const t1Obj = (_excelTierCache?.tier1 || []).find(t => String(t.id) === String(t1Select?.value));
        const t1Pad = t1Obj ? String(t1Obj.code_val).padStart(2, '0') : (_wizardState.t1Code || '01');
        let t2Pad = _wizardState.t2Code || '01';
        if (t1Select?.value && _excelTierCache?.tier2?.[t1Select.value]) {
          const t2Obj = _excelTierCache.tier2[t1Select.value].find(t => String(t.id) === String(t2Select?.value));
          if (t2Obj) t2Pad = String(t2Obj.code_val).padStart(2, '0');
        }

        App.toast(`Added 3rd Category: ${t1Pad}.${t2Pad}.${String(savedCode).padStart(2, '0')}. "${nameVal}"! (Total ${_wizardState.t3Count} added)`, 'success');

        // Clear name input for next 3rd item, keep focus, auto-increment code number
        if (nameInput) {
          nameInput.value = '';
          nameInput.focus();
        }
        suggestNextTierCodeVal();
        updateLiveCodePreview();
      }
    } else {
      App.toast(res.data?.message || 'Failed to save code.', 'error');
    }
  } catch (err) {
    App.toast(`Error: ${err.message}`, 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = prevBtnHtml;
    }
    if (saveOnlyBtn) {
      saveOnlyBtn.disabled = false;
    }
  }
};

/**
 * Handle direct Save & Finish without advancing
 */
window.handleSaveAndFinish = function (event) {
  handleWizardSaveCurrentStep(event, false);
};

/**
 * Finish Code Wizard / Complete All
 */
window.finishCodeWizard = function () {
  hideSimpleAddTierBar();
  loadExcelHierarchyTree();
  if (_wizardState.t3Count > 0) {
    App.toast(`Successfully added ${_wizardState.t3Count} new category item(s) to the hierarchy!`, 'success');
  } else {
    App.toast('Hierarchy definition saved!', 'success');
  }
  _wizardState = { step: 1, t1Id: null, t1Code: '', t1Name: '', t2Id: null, t2Code: '', t2Name: '', t3Count: 0 };
};

// Backwards compatibility alias
window.handleSaveTierNode = window.handleWizardSaveCurrentStep;

/**
 * Open Safe Delete Confirmation Modal (Requires typing DELETE)
 */
window.openDeleteTierModal = function (id, name, prefix = '') {
  const modal = document.getElementById('deleteTierCategoryModal');
  const targetId = document.getElementById('deleteTierTargetId');
  const targetName = document.getElementById('deleteTierTargetName');
  const targetPrefix = document.getElementById('deleteTierTargetPrefix');
  const confirmInput = document.getElementById('deleteTierConfirmInput');
  const confirmBtn = document.getElementById('btnConfirmDeleteTier');

  if (!modal) return;

  if (targetId) targetId.value = id;
  if (targetName) targetName.textContent = name || 'Category';
  if (targetPrefix) targetPrefix.textContent = prefix ? `Code Prefix: ${prefix}` : `ID: #${id}`;
  if (confirmInput) {
    confirmInput.value = '';
    confirmInput.style.borderColor = '#cbd5e1';
  }
  if (confirmBtn) {
    confirmBtn.disabled = true;
    confirmBtn.style.opacity = '0.4';
    confirmBtn.style.cursor = 'not-allowed';
  }

  modal.classList.add('active');
  if (confirmInput) {
    setTimeout(() => confirmInput.focus(), 100);
  }
};

/**
 * Close Safe Delete Confirmation Modal
 */
window.closeDeleteTierModal = function () {
  const modal = document.getElementById('deleteTierCategoryModal');
  const confirmInput = document.getElementById('deleteTierConfirmInput');
  if (modal) modal.classList.remove('active');
  if (confirmInput) confirmInput.value = '';
};

/**
 * Real-time Validation for Delete Confirmation Input ("DELETE")
 */
window.onDeleteTierConfirmInput = function () {
  const input = document.getElementById('deleteTierConfirmInput');
  const confirmBtn = document.getElementById('btnConfirmDeleteTier');
  if (!input || !confirmBtn) return;

  const val = input.value.trim().toUpperCase();
  if (val === 'DELETE') {
    confirmBtn.disabled = false;
    confirmBtn.style.opacity = '1.0';
    confirmBtn.style.cursor = 'pointer';
    input.style.borderColor = '#16a34a';
  } else {
    confirmBtn.disabled = true;
    confirmBtn.style.opacity = '0.4';
    confirmBtn.style.cursor = 'not-allowed';
    input.style.borderColor = '#cbd5e1';
  }
};

/**
 * Execute Safe Deletion After "DELETE" Confirmation
 */
window.executeConfirmedTierDelete = async function () {
  const targetIdInput = document.getElementById('deleteTierTargetId');
  const confirmInput = document.getElementById('deleteTierConfirmInput');
  const confirmBtn = document.getElementById('btnConfirmDeleteTier');
  const id = targetIdInput ? targetIdInput.value : null;

  if (!id) return;
  if (confirmInput && confirmInput.value.trim().toUpperCase() !== 'DELETE') {
    App.toast('Please type DELETE to confirm deletion.', 'warning');
    return;
  }

  const prevBtnHtml = confirmBtn ? confirmBtn.innerHTML : '';
  if (confirmBtn) {
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = 'Deleting...';
  }

  try {
    const formData = new FormData();
    formData.append('action', 'delete_tier_category');
    formData.append('id', id);
    formData.append('_csrf_token', window.APP_DATA?.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');

    const res = await App.request('/api/code_management.php', {
      method: 'POST',
      body: formData
    });

    if (res.ok && res.data?.success) {
      App.toast(res.data.message || 'Category deleted successfully.', 'success');
      closeDeleteTierModal();
      await refreshTierCache();
      loadExcelHierarchyTree();
    } else {
      App.toast(res.data?.message || 'Failed to delete category.', 'error');
    }
  } catch (err) {
    App.toast(`Error: ${err.message}`, 'error');
  } finally {
    if (confirmBtn) {
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = prevBtnHtml;
    }
  }
};

/**
 * Real-time spelling, typo & duplicate check for category description
 */
window.onTierNodeNameInput = function (event) {
  const input = event.target;
  const text = input.value.toUpperCase();
  input.value = text;

  clearTimeout(_tierSpellingDebounce);
  const chip = document.getElementById('tierSpellingChip');
  const indicator = document.getElementById('tierSpellingIndicator');
  const saveBtn = document.getElementById('btnSaveTierNode');
  const level = parseInt(document.getElementById('tierNodeLevel')?.value || '1', 10);
  const editingId = document.getElementById('tierNodeId')?.value;
  const cleanTrim = text.trim();

  // Strict Duplicate Check for 1st and 2nd Tier names (cannot be added again)
  if (level === 1 && cleanTrim.length > 0) {
    const existingT1 = (_excelTierCache?.tier1 || []).find(t =>
      (t.name || '').trim().toUpperCase() === cleanTrim && (!editingId || String(t.id) !== String(editingId))
    );
    if (existingT1) {
      if (chip) chip.style.display = 'none';
      if (indicator) {
        indicator.innerHTML = `[Duplicate] <strong>"${escapeHtml(cleanTrim)}"</strong> already exists as 1st Code (${String(existingT1.code_val).padStart(2, '0')}.)! Duplicate 1st topics cannot be added.`;
        indicator.style.color = '#dc2626';
      }
      if (saveBtn) saveBtn.disabled = true;
      return;
    }
  } else if (level === 2 && cleanTrim.length > 0) {
    const t1Id = document.getElementById('tier1StepSelect')?.value;
    const t2List = (t1Id && _excelTierCache?.tier2?.[t1Id]) ? _excelTierCache.tier2[t1Id] : [];
    const existingT2 = t2List.find(t =>
      (t.name || '').trim().toUpperCase() === cleanTrim && (!editingId || String(t.id) !== String(editingId))
    );
    if (existingT2) {
      if (chip) chip.style.display = 'none';
      if (indicator) {
        indicator.innerHTML = `[Duplicate] <strong>"${escapeHtml(cleanTrim)}"</strong> already exists under this 1st topic as 2nd Code (${String(existingT2.code_val).padStart(2, '0')}.)! Duplicate 2nd sub-topics cannot be added.`;
        indicator.style.color = '#dc2626';
      }
      if (saveBtn) saveBtn.disabled = true;
      return;
    }
  }

  // Enable save button if no duplicate
  if (saveBtn) saveBtn.disabled = false;

  if (cleanTrim.length < 2) {
    if (chip) chip.style.display = 'none';
    if (indicator) {
      indicator.textContent = 'Clean';
      indicator.style.color = '#16a34a';
    }
    return;
  }

  _tierSpellingDebounce = setTimeout(async () => {
    try {
      const res = await App.request(`/api/code_management.php?action=suggest_code_items&q=${encodeURIComponent(text)}`);
      if (res.ok && res.data) {
        if (res.data.has_spelling_corrections && res.data.corrected_query) {
          window._currentTierSpellingCorrection = res.data.corrected_query;
          const sugEl = document.getElementById('tierSpellingSugText');
          if (sugEl) sugEl.textContent = res.data.corrected_query;
          if (chip) chip.style.display = 'block';
          if (indicator) {
            indicator.textContent = 'Typo Detected';
            indicator.style.color = '#d97706';
          }
        } else {
          window._currentTierSpellingCorrection = null;
          if (chip) chip.style.display = 'none';
          if (indicator) {
            indicator.textContent = 'Verified';
            indicator.style.color = '#16a34a';
          }
        }
      }
    } catch (e) {
      console.warn('Spelling check error:', e);
    }
  }, 250);
};

/**
 * Apply spelling correction suggestion to input
 */
window.applyTierSpelling = function () {
  if (window._currentTierSpellingCorrection) {
    const input = document.getElementById('tierNodeName');
    const chip = document.getElementById('tierSpellingChip');
    const indicator = document.getElementById('tierSpellingIndicator');
    if (input) {
      input.value = window._currentTierSpellingCorrection;
      if (chip) chip.style.display = 'none';
      if (indicator) {
        indicator.textContent = '✓ Verified';
        indicator.style.color = '#16a34a';
      }
      App.toast('Spelling correction applied!', 'info');
    }
  }
};

function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/**
 * Issue Destination Categories & Codes Management
 */
function initIssueCodeManagement() {
  const createForm = document.getElementById('createIssueCodeForm');
  const quickForm = document.getElementById('quickAddIssueCodeForm');
  const editForm = document.getElementById('editIssueCodeForm');

  const handleCreateSubmit = async (form, modalIdToClose) => {
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn ? submitBtn.innerHTML : 'Save';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Saving Code...';
    }

    const formData = new FormData(form);
    if (!formData.has('action')) formData.append('action', 'create');

    const res = await App.request('/api/issue_codes.php', {
      method: 'POST',
      body: formData
    });

    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;
    }

    if (res.ok && res.data.success) {
      App.toast('New code successfully added to dropdown & list!', 'success');
      if (modalIdToClose) window.closeModal(modalIdToClose);
      form.reset();
      setTimeout(() => window.location.reload(), 400);
    } else {
      App.toast(res.data?.message || 'Failed to create code.', 'error');
    }
  };

  if (createForm) {
    createForm.addEventListener('submit', (e) => {
      const code = (createForm.querySelector('#newIssueCodeCode')?.value || '').trim();
      const cat = createForm.querySelector('#newIssueCodeCategory')?.value || '';
      const dup = window.checkIssueCodeDuplicate(code, cat);
      if (dup) {
        e.preventDefault();
        App.toast(`Code "${code}" already exists in ${dup.category}! Duplicate codes cannot be added.`, 'error');
        const codeInput = createForm.querySelector('#newIssueCodeCode');
        if (codeInput) {
          codeInput.focus();
          codeInput.style.borderColor = '#dc2626';
        }
        return false;
      }
      e.preventDefault();
      handleCreateSubmit(createForm, 'createIssueCodeModal');
    });
  }

  if (quickForm) {
    quickForm.addEventListener('submit', (e) => {
      const code = (quickForm.querySelector('#quickAddCode')?.value || '').trim();
      const cat = quickForm.querySelector('#quickAddCategory')?.value || '';
      const dup = window.checkIssueCodeDuplicate(code, cat);
      if (dup) {
        e.preventDefault();
        App.toast(`Code "${code}" already exists in ${dup.category}! Duplicate codes cannot be added.`, 'error');
        const codeInput = quickForm.querySelector('#quickAddCode');
        if (codeInput) {
          codeInput.focus();
          codeInput.style.borderColor = '#dc2626';
        }
        return false;
      }
      e.preventDefault();
      handleCreateSubmit(quickForm, null);
    });
  }

  if (editForm) {
    editForm.addEventListener('submit', async (e) => {
      const id = parseInt(editForm.querySelector('#editIssueCodeId')?.value || '0', 10);
      const code = (editForm.querySelector('#editIssueCodeCode')?.value || '').trim();
      const cat = editForm.querySelector('#editIssueCodeCategory')?.value || '';
      const dup = window.checkIssueCodeDuplicate(code, cat, id);
      if (dup) {
        e.preventDefault();
        App.toast(`Code "${code}" already exists in ${dup.category}! Duplicate codes cannot be used.`, 'error');
        const codeInput = editForm.querySelector('#editIssueCodeCode');
        if (codeInput) {
          codeInput.focus();
          codeInput.style.borderColor = '#dc2626';
        }
        return false;
      }

      e.preventDefault();
      const submitBtn = editForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Updating Code...';

      const formData = new FormData(editForm);
      formData.append('action', 'update');

      const res = await App.request('/api/issue_codes.php', {
        method: 'POST',
        body: formData
      });

      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;

      if (res.ok && res.data.success) {
        App.toast('Issue code updated successfully!', 'success');
        window.closeModal('editIssueCodeModal');
        setTimeout(() => window.location.reload(), 400);
      } else {
        App.toast(res.data?.message || 'Failed to update issue code.', 'error');
      }
    });
  }
}

// Global modal opener for edit
window.openEditIssueCodeModal = function (id, category, code, title, displayLabel, status, sortOrder) {
  const form = document.getElementById('editIssueCodeForm');
  if (!form) return;

  form.querySelector('#editIssueCodeId').value = id;
  form.querySelector('#editIssueCodeCategory').value = category;
  form.querySelector('#editIssueCodeCode').value = code || '';
  form.querySelector('#editIssueCodeTitle').value = title;
  form.querySelector('#editIssueCodeDisplayLabel').value = displayLabel;
  if (form.querySelector('#editIssueCodeStatus')) {
    form.querySelector('#editIssueCodeStatus').value = status || 'active';
  }
  if (form.querySelector('#editIssueCodeSortOrder')) {
    form.querySelector('#editIssueCodeSortOrder').value = sortOrder || 0;
  }

  const errDiv = form.querySelector('#editIssueCodeDuplicateMsg');
  if (errDiv) {
    errDiv.style.display = 'none';
    errDiv.textContent = '';
  }
  const codeInp = form.querySelector('#editIssueCodeCode');
  if (codeInp) codeInp.style.borderColor = '';
  const submitBtn = form.querySelector('button[type="submit"]');
  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.style.opacity = '1';
    submitBtn.style.cursor = 'pointer';
  }

  updateEditIssueCodePreview();
  window.openModal('editIssueCodeModal');
};

// Global delete handler
window.deleteIssueCode = async function (id, label) {
  if (!confirm(`Are you sure you want to delete:\n"${label}"?\n\nThis will remove it from the dropdown list.`)) {
    return;
  }

  const formData = new FormData();
  formData.append('action', 'delete');
  formData.append('id', id);
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  if (csrf) formData.append('_csrf_token', csrf);

  const res = await App.request('/api/issue_codes.php', {
    method: 'POST',
    body: formData
  });

  if (res.ok && res.data.success) {
    App.toast(`Deleted "${label}" successfully!`, 'success');
    const row = document.getElementById('issue-code-row-' + id);
    if (row) {
      row.remove();
      filterIssueCodesTable();
    } else {
      setTimeout(() => window.location.reload(), 400);
    }
  } else {
    App.toast(res.data?.message || 'Failed to delete code.', 'error');
  }
};

// Duplicate Code Checker: Checks if a code is already registered
window.checkIssueCodeDuplicate = function (code, category, excludeId = null) {
  if (!code) return null;
  const cleanCode = code.trim().toLowerCase();
  if (!cleanCode) return null;

  const codes = window.EXISTING_ISSUE_CODES || [];
  return codes.find(item => {
    if (excludeId && Number(item.id) === Number(excludeId)) {
      return false;
    }
    const itemCode = (item.code || '').trim().toLowerCase();
    if (!itemCode) return false;
    return itemCode === cleanCode;
  }) || null;
};

// Real-time duplicate check for Quick Add
window.handleQuickAddCodeChange = function () {
  updateQuickAddDisplayPreview();

  const codeInput = document.getElementById('quickAddCode');
  const catInput = document.getElementById('quickAddCategory');
  const errDiv = document.getElementById('quickAddCodeDuplicateMsg');
  const submitBtn = document.getElementById('btnQuickAddSubmit');

  const codeVal = (codeInput?.value || '').trim();
  const catVal = catInput?.value || '';

  const dup = window.checkIssueCodeDuplicate(codeVal, catVal);
  if (dup) {
    if (errDiv) {
      errDiv.textContent = `Code "${codeVal}" already exists in ${dup.category} (${dup.display_label || dup.title})! Duplicate codes cannot be added.`;
      errDiv.style.display = 'block';
    }
    if (codeInput) {
      codeInput.style.borderColor = '#dc2626';
    }
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.style.opacity = '0.5';
      submitBtn.style.cursor = 'not-allowed';
    }
  } else {
    if (errDiv) {
      errDiv.style.display = 'none';
      errDiv.textContent = '';
    }
    if (codeInput) {
      codeInput.style.borderColor = '#cbd5e1';
    }
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.style.opacity = '1';
      submitBtn.style.cursor = 'pointer';
    }
  }
};

// Real-time duplicate check for Modal Add
window.handleNewIssueCodeChange = function () {
  updateNewIssueCodePreview();

  const codeInput = document.getElementById('newIssueCodeCode');
  const catInput = document.getElementById('newIssueCodeCategory');
  const errDiv = document.getElementById('newIssueCodeDuplicateMsg');
  const form = document.getElementById('createIssueCodeForm');
  const submitBtn = form?.querySelector('button[type="submit"]');

  const codeVal = (codeInput?.value || '').trim();
  const catVal = catInput?.value || '';

  const dup = window.checkIssueCodeDuplicate(codeVal, catVal);
  if (dup) {
    if (errDiv) {
      errDiv.textContent = `Code "${codeVal}" already exists in ${dup.category} (${dup.display_label || dup.title})! Duplicate codes cannot be added.`;
      errDiv.style.display = 'block';
    }
    if (codeInput) {
      codeInput.style.borderColor = '#dc2626';
    }
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.style.opacity = '0.5';
      submitBtn.style.cursor = 'not-allowed';
    }
  } else {
    if (errDiv) {
      errDiv.style.display = 'none';
      errDiv.textContent = '';
    }
    if (codeInput) {
      codeInput.style.borderColor = '';
    }
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.style.opacity = '1';
      submitBtn.style.cursor = 'pointer';
    }
  }
};

// Real-time duplicate check for Modal Edit
window.handleEditIssueCodeChange = function () {
  updateEditIssueCodePreview();

  const codeInput = document.getElementById('editIssueCodeCode');
  const catInput = document.getElementById('editIssueCodeCategory');
  const idInput = document.getElementById('editIssueCodeId');
  const errDiv = document.getElementById('editIssueCodeDuplicateMsg');
  const form = document.getElementById('editIssueCodeForm');
  const submitBtn = form?.querySelector('button[type="submit"]');

  const codeVal = (codeInput?.value || '').trim();
  const catVal = catInput?.value || '';
  const id = parseInt(idInput?.value || '0', 10);

  const dup = window.checkIssueCodeDuplicate(codeVal, catVal, id);
  if (dup) {
    if (errDiv) {
      errDiv.textContent = `Code "${codeVal}" already exists in ${dup.category} (${dup.display_label || dup.title})! Duplicate codes cannot be used.`;
      errDiv.style.display = 'block';
    }
    if (codeInput) {
      codeInput.style.borderColor = '#dc2626';
    }
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.style.opacity = '0.5';
      submitBtn.style.cursor = 'not-allowed';
    }
  } else {
    if (errDiv) {
      errDiv.style.display = 'none';
      errDiv.textContent = '';
    }
    if (codeInput) {
      codeInput.style.borderColor = '';
    }
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.style.opacity = '1';
      submitBtn.style.cursor = 'pointer';
    }
  }
};

// Auto display label preview helpers
window.updateNewIssueCodePreview = function () {
  const code = document.getElementById('newIssueCodeCode')?.value.trim();
  const title = document.getElementById('newIssueCodeTitle')?.value.trim();
  const target = document.getElementById('newIssueCodeDisplayLabel');
  const preview = document.getElementById('newIssueCodePreviewText');
  const formatted = (code && title) ? (code + ' -' + title) : (code || title || '—');
  if (target) target.value = formatted;
  if (preview) preview.textContent = formatted;
};

window.updateEditIssueCodePreview = function () {
  const code = document.getElementById('editIssueCodeCode')?.value.trim();
  const title = document.getElementById('editIssueCodeTitle')?.value.trim();
  const target = document.getElementById('editIssueCodeDisplayLabel');
  const preview = document.getElementById('editIssueCodePreviewText');
  const formatted = (code && title) ? (code + ' -' + title) : (code || title || '—');
  if (target) target.value = formatted;
  if (preview) preview.textContent = formatted;
};

// Quick Add display preview helper
window.updateQuickAddDisplayPreview = function () {
  const code = document.getElementById('quickAddCode')?.value.trim();
  const title = document.getElementById('quickAddTitle')?.value.trim();
  const target = document.getElementById('quickAddDisplayLabel');
  const preview = document.getElementById('quickAddPreviewString');
  const formatted = (code && title) ? (code + ' -' + title) : (code || title || '—');
  if (target) target.value = formatted;
  if (preview) preview.textContent = formatted;
};

// Category Selector: Isolates and manages each Issue To section separately
window.selectIssueCategory = function (categoryKey, tabBtn) {
  const cfg = window.ISSUE_CATEGORIES_CONFIG ? window.ISSUE_CATEGORIES_CONFIG[categoryKey] : null;

  // 1. Update tab button active states
  document.querySelectorAll('.issue-section-tab').forEach(btn => {
    btn.classList.remove('active');
    btn.style.border = '2px solid #cbd5e1';
    btn.style.background = '#f8fafc';
    const titleEl = btn.querySelector('strong');
    if (titleEl) titleEl.style.color = '#0f172a';
    const badgeEl = btn.querySelector('.badge');
    if (badgeEl) badgeEl.style.background = '#64748b';
  });

  if (tabBtn) {
    tabBtn.classList.add('active');
    tabBtn.style.border = '2px solid #650b2b';
    tabBtn.style.background = '#fdf2f4';
    const titleEl = tabBtn.querySelector('strong');
    if (titleEl) titleEl.style.color = '#650b2b';
    const badgeEl = tabBtn.querySelector('.badge');
    if (badgeEl) badgeEl.style.background = '#650b2b';
  }

  // 2. Update Add Box titles and fields
  const addTitleEl = document.getElementById('currentSelectedSectionTitle');
  const addSubEl = document.getElementById('currentSelectedSectionSubtitle');
  const hiddenCatInput = document.getElementById('quickAddCategory');
  const submitBtn = document.getElementById('btnQuickAddSubmit');
  const codeInput = document.getElementById('quickAddCode');
  const titleInput = document.getElementById('quickAddTitle');
  const listBadge = document.getElementById('currentListCategoryBadge');

  if (addTitleEl && cfg) addTitleEl.textContent = cfg.title;
  if (addSubEl && cfg) addSubEl.textContent = cfg.subtitle || '';
  if (hiddenCatInput) hiddenCatInput.value = categoryKey;
  if (submitBtn) {
    submitBtn.innerHTML = `+ Add`;
    submitBtn.disabled = false;
    submitBtn.style.opacity = '1';
    submitBtn.style.cursor = 'pointer';
  }
  if (codeInput) {
    if (cfg) codeInput.placeholder = cfg.code_placeholder;
    codeInput.style.borderColor = '#cbd5e1';
  }
  const errDiv = document.getElementById('quickAddCodeDuplicateMsg');
  if (errDiv) {
    errDiv.style.display = 'none';
    errDiv.textContent = '';
  }
  if (titleInput && cfg) titleInput.placeholder = cfg.name_placeholder;
  if (listBadge && cfg) listBadge.textContent = cfg.title;

  // 3. Update hidden filter input and filter list
  const catFilter = document.getElementById('issueCodeCategoryFilter');
  if (catFilter) catFilter.value = categoryKey;

  // Clear search input on category change for fresh view
  const searchInput = document.getElementById('issueCodeSearchInput');
  if (searchInput) {
    searchInput.value = '';
    searchInput.placeholder = `Search...`;
  }

  filterIssueCodesTable();
};

window.filterIssueCodesTable = function () {
  const searchInput = document.getElementById('issueCodeSearchInput');
  const catFilter = document.getElementById('issueCodeCategoryFilter');
  const statusFilter = document.getElementById('issueCodeStatusFilter');
  const clearBtn = document.getElementById('clearIssueCodeSearchBtn');

  const query = (searchInput?.value || '').trim().toLowerCase();
  const selectedCat = catFilter?.value || 'Section';
  const selectedStatus = statusFilter?.value || 'all';

  if (clearBtn) {
    clearBtn.style.display = query ? 'block' : 'none';
  }

  const rows = document.querySelectorAll('.issue-code-row');
  let visibleCount = 0;

  rows.forEach(row => {
    const rowCat = row.getAttribute('data-category');
    const rowStatus = row.getAttribute('data-status');
    const rowSearch = row.getAttribute('data-search') || '';

    const matchesCat = (rowCat === selectedCat);
    const matchesStatus = (selectedStatus === 'all' || rowStatus === selectedStatus);
    const matchesQuery = (!query || rowSearch.includes(query));

    if (matchesCat && matchesStatus && matchesQuery) {
      row.style.display = 'flex';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });

  const countEl = document.getElementById('selectedCategoryCount');
  if (countEl) countEl.textContent = visibleCount;

  const emptyRow = document.getElementById('emptyIssueCodesRow');
  if (emptyRow) {
    emptyRow.style.display = (visibleCount === 0) ? 'block' : 'none';
  }
};

/**
 * Dynamic Document Profile Preview and Reference Labels Handler (Inventory FIFO -> Issue)
 * Updates the preview status box and reference section labels based on Document Profile
 */
window.handleIssueDocProfileChange = function (val, prefix = 'entry') {
  const configs = {
    '': {
      title: 'Doc PF Not Selected',
      labels: ['Reference 1', 'Reference 2', 'Reference 3', 'Reference 4']
    },
    'TV-ISSUE': {
      title: 'MATERIAL TRANSFER NOTE- CE KOTMALE- 832.00',
      labels: ['REF 1', 'REF 2', 'REF 3', 'REF 4']
    },
    'MAT-REQ': {
      title: 'MATERIAL REQUISITONS- CE KOTMALE- 832.00',
      labels: ['COST CODE :', 'TO :', 'JOB NUMBER :', '']
    },
    'ISSUE': {
      title: 'MATERIAL ISSUE ADVICE- KOTMALE PS- 832.00',
      labels: ['TO [TYPE] :', 'JOB NO. / S/N NO. :', 'RECIPIENT :', 'DESIGNATION :']
    }
  };

  const conf = configs[val] || configs[''];

  // 1. Update Preview Status Box
  const statusBox = (prefix === 'edit')
    ? (document.getElementById('issueEditDocPfStatusBox') || document.getElementById('issueDocPfStatusBox'))
    : document.getElementById('issueDocPfStatusBox');
  if (statusBox) {
    statusBox.textContent = conf.title;
    statusBox.style.color = val ? '#1e40af' : '#4338ca';
  }

  // 2. Update Reference Labels
  const labelPrefix = (prefix === 'edit') ? 'issueEditRefLabel' : 'issueRefLabel';
  for (let i = 1; i <= 4; i++) {
    const lbl = document.getElementById(labelPrefix + i);
    if (lbl) {
      lbl.textContent = conf.labels[i - 1] ?? '';
    }
  }
};

// Initialize Issue Document Profile on DOMContentLoaded
document.addEventListener('DOMContentLoaded', function () {
  const issueSelect = document.getElementById('issueDocProfile');
  if (issueSelect && typeof window.handleIssueDocProfileChange === 'function') {
    window.handleIssueDocProfileChange(issueSelect.value, 'entry');
  }
  const issueEditSelect = document.getElementById('issueUpdateDocProfile');
  if (issueEditSelect && typeof window.handleIssueDocProfileChange === 'function') {
    window.handleIssueDocProfileChange(issueEditSelect.value, 'edit');
  }
});






