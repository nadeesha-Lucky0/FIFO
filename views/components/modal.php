<?php
// Reusable Create User Modal Component
$compRoleModel = new \App\Models\Role();
$compDeptModel = new \App\Models\Department();
$compRoles = $compRoleModel->all();
$compDepartments = $compDeptModel->getAll();
?>
<div class="modal-backdrop" id="createUserModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title">Create New Team Member</h3>
      <button type="button" class="modal-close" data-close-modal>&times;</button>
    </div>

    <form id="createUserForm">
      <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Session::csrfToken()) ?>">
      <div class="modal-body">
        <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 20px;">
          Provision a new user account with role-based permissions and access to the FIFO platform.
        </p>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
          <div class="form-group">
            <label class="form-label">Full Name *</label>
            <input type="text" name="full_name" class="form-control" placeholder="e.g. Alexander Reed" required>
          </div>

          <div class="form-group">
            <label class="form-label">Corporate Username *</label>
            <input type="text" name="username" class="form-control" placeholder="e.g. areed" required>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
          <div class="form-group">
            <label class="form-label">Corporate Email</label>
            <input type="email" name="email" class="form-control" placeholder="areed@egl.lk">
          </div>

          <div class="form-group">
            <label class="form-label">Contact Phone</label>
            <input type="text" name="phone" class="form-control" placeholder="+94 77 123 4567">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 16px;">
          <div class="form-group">
            <label class="form-label">System Role *</label>
            <select name="role_id" class="form-select" required>
              <option value="" disabled selected>-- Select Defined Role --</option>
              <?php foreach ($compRoles as $r): ?>
                <option value="<?= $r['id'] ?>" <?= ($r['name'] === 'operator') ? 'selected' : '' ?>>
                  <?= htmlspecialchars($r['display_name']) ?> (<?= htmlspecialchars($r['name']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Account Status</label>
            <select name="status" class="form-select">
              <option value="active" selected>Active</option>
              <option value="pending">Pending Verification</option>
              <option value="suspended">Suspended</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Assigned Department (Admin-Defined) *</label>
          <select name="department" class="form-select" required>
            <?php if (empty($compDepartments)): ?>
              <option value="" disabled selected>No admin departments defined yet. Please add a department first.</option>
            <?php else: ?>
              <option value="" disabled selected>-- Select Department --</option>
              <?php foreach ($compDepartments as $dept): ?>
                <option value="<?= htmlspecialchars($dept['name']) ?>">
                  <?= htmlspecialchars($dept['name']) ?> (<?= htmlspecialchars($dept['location_name'] ?? 'General') ?>)
                </option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Temporary Access Password *</label>
          <div class="password-input-wrapper">
            <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
            <button type="button" class="btn-toggle-password" aria-label="Show password" title="Show password" tabindex="-1">
              <svg class="eye-show" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
              <svg class="eye-hide" style="display:none;" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                <line x1="1" y1="1" x2="23" y2="23"></line>
              </svg>
            </button>
          </div>
          <div class="form-hint">The user can change this password after signing into their profile.</div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn-primary">Create User Account</button>
      </div>
    </form>
  </div>
</div>
