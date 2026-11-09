<?php
require_once dirname(__DIR__, 2) . '/app/config/config.php';

use App\Core\Auth;
use App\Models\User;

Auth::requireLogin();

$userModel = new User();
$user = $userModel->findById(Auth::id());

$pageTitle = 'Admin Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars(\App\Core\Session::csrfToken()) ?>">
  <title><?= $pageTitle ?> | <?= APP_NAME ?></title>
  <link rel="stylesheet" href="/assets/css/design-system.css">
  <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body style="background-color: #f8fafc;">

  <!-- Admin Navbar -->
  <nav class="admin-navbar">
    <a href="/admin/index.php" class="brand-logo">
      <div class="brand-logo-icon">F</div>
      <span>Admin Console</span>
    </a>

    <div style="display: flex; align-items: center; gap: 16px;">
      <a href="/admin/index.php" class="btn btn-secondary btn-sm">&larr; Back to Users</a>
      <a href="/logout.php" class="btn btn-danger btn-sm">Logout</a>
    </div>
  </nav>

  <main class="admin-container">
    <div class="profile-grid">
      
      <!-- Summary Card -->
      <div>
        <div class="card" style="text-align: center;">
          <div id="profileAvatarDisplay" class="avatar avatar-lg" style="margin: 0 auto 16px; background-color: var(--primary);">
            <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
          </div>
          <h2 style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 4px;" id="profileNameDisplay">
            <?= htmlspecialchars($user['full_name']) ?>
          </h2>
          <span class="badge badge-primary"><?= htmlspecialchars($user['role_display_name']) ?></span>

          <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-subtle); text-align: left; font-size: 0.875rem; display: flex; flex-direction: column; gap: 10px;">
            <div><span style="color: #64748b;">Username:</span> <strong>@<?= htmlspecialchars($user['username']) ?></strong></div>
            <div><span style="color: #64748b;">Department:</span> <strong><?= htmlspecialchars($user['department'] ?? 'Administration') ?></strong></div>
            <div><span style="color: #64748b;">Status:</span> <span class="badge badge-success">Active</span></div>
          </div>
        </div>
      </div>

      <!-- Forms -->
      <div style="display: flex; flex-direction: column; gap: 20px;">
        <!-- Edit Profile -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Edit Profile Information</h3>
          </div>

          <form id="profileInfoForm">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Session::csrfToken()) ?>">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
              <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
              </div>

              <div class="form-group">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
              <div class="form-group">
                <label class="form-label">Department</label>
                <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($user['department'] ?? '') ?>">
              </div>

              <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
              </div>
            </div>

            <div style="display: flex; justify-content: flex-end;">
              <button type="submit" class="btn btn-primary">Save Profile</button>
            </div>
          </form>
        </div>

        <!-- Change Password -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Change Password</h3>
          </div>

          <form id="changePasswordForm">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Session::csrfToken()) ?>">

            <div class="form-group">
              <label class="form-label">Current Password *</label>
              <div class="password-input-wrapper">
                <input type="password" name="current_password" class="form-control" placeholder="Current password" required>
                <button type="button" class="btn-toggle-password" aria-label="Show password" title="Show password" tabindex="-1">
                  <svg class="eye-show" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  <svg class="eye-hide" style="display:none;" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                  </svg>
                </button>
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
              <div class="form-group">
                <label class="form-label">New Password *</label>
                <div class="password-input-wrapper">
                  <input type="password" name="new_password" class="form-control" placeholder="New password" required>
                  <button type="button" class="btn-toggle-password" aria-label="Show password" title="Show password" tabindex="-1">
                    <svg class="eye-show" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path>
                      <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg class="eye-hide" style="display:none;" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                      <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                  </button>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Confirm New Password *</label>
                <div class="password-input-wrapper">
                  <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
                  <button type="button" class="btn-toggle-password" aria-label="Show password" title="Show password" tabindex="-1">
                    <svg class="eye-show" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path>
                      <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg class="eye-hide" style="display:none;" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                      <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <div style="display: flex; justify-content: flex-end;">
              <button type="submit" class="btn btn-secondary">Update Password</button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </main>

  <div id="toast-container"></div>
  <script src="/assets/js/app.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const infoForm = document.getElementById('profileInfoForm');
      const passForm = document.getElementById('changePasswordForm');
      const avatarDisplay = document.getElementById('profileAvatarDisplay');
      const nameDisplay = document.getElementById('profileNameDisplay');

      if (infoForm) {
        infoForm.addEventListener('submit', async (e) => {
          e.preventDefault();
          const submitBtn = infoForm.querySelector('button[type="submit"]');
          submitBtn.disabled = true;
          submitBtn.innerHTML = 'Saving...';

          const formData = new FormData(infoForm);
          formData.append('action', 'update');

          const res = await App.request('/api/profile.php', {
            method: 'POST',
            body: formData
          });

          submitBtn.disabled = false;
          submitBtn.innerHTML = 'Save Profile';

          if (res.ok && res.data.success) {
            App.toast('Profile updated successfully!', 'success');
            if (res.data.data?.user?.name && nameDisplay) {
              nameDisplay.textContent = res.data.data.user.name;
              avatarDisplay.textContent = res.data.data.user.name.charAt(0).toUpperCase();
            }
          } else {
            App.toast(res.data?.message || 'Failed to update profile.', 'error');
          }
        });
      }

      if (passForm) {
        passForm.addEventListener('submit', async (e) => {
          e.preventDefault();
          const submitBtn = passForm.querySelector('button[type="submit"]');
          submitBtn.disabled = true;
          submitBtn.innerHTML = 'Updating...';

          const formData = new FormData(passForm);
          formData.append('action', 'password');

          const res = await App.request('/api/profile.php', {
            method: 'POST',
            body: formData
          });

          submitBtn.disabled = false;
          submitBtn.innerHTML = 'Update Password';

          if (res.ok && res.data.success) {
            App.toast('Password updated successfully!', 'success');
            passForm.reset();
          } else {
            App.toast(res.data?.message || 'Failed to change password.', 'error');
          }
        });
      }
    });
  </script>
</body>
</html>
