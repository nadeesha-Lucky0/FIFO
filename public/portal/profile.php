<?php
require_once dirname(__DIR__, 2) . '/app/config/config.php';

use App\Core\Auth;
use App\Models\User;

Auth::requireLogin();

$userModel = new User();
$user = $userModel->findById(Auth::id());

$pageHeader = 'My Employee Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars(\App\Core\Session::csrfToken()) ?>">
  <title>My Profile | <?= APP_NAME ?></title>
  <link rel="stylesheet" href="/assets/css/design-system.css">
  <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>
  <div class="ambient-glow-bg"></div>

  <div class="app-shell">
    <?php require_once ROOT_PATH . '/views/layouts/user_sidebar.php'; ?>

    <main class="app-main">
      <header class="app-topbar">
        <div class="topbar-left">
          <h1 class="page-title"><?= $pageHeader ?></h1>
        </div>
        <div class="topbar-right">
          <div style="display: flex; align-items: center; gap: 12px;">
            <a href="/portal/profile.php" class="avatar" style="background-color: <?= htmlspecialchars($user['avatar_color'] ?? '#06b6d4') ?>;">
              <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
            </a>
            <a href="/logout.php" class="btn btn-secondary btn-sm">Sign Out</a>
          </div>
        </div>
      </header>

      <div class="app-content">
        <div class="profile-grid">
          <!-- Summary Card -->
          <div>
            <div class="card profile-summary-card">
              <div class="profile-avatar-wrapper">
                <div id="profileAvatarDisplay" class="avatar avatar-lg" style="background-color: <?= htmlspecialchars($user['avatar_color'] ?? '#06b6d4') ?>;">
                  <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                </div>
              </div>

              <h2 class="profile-name" id="profileNameDisplay"><?= htmlspecialchars($user['full_name']) ?></h2>
              <div class="profile-role-badge">
                <span class="badge badge-info"><?= htmlspecialchars($user['role_display_name']) ?></span>
              </div>

              <div class="profile-detail-list">
                <div class="profile-detail-item">
                  <span class="profile-detail-label">Corporate Handle</span>
                  <span class="profile-detail-value" style="font-family: var(--font-mono);">@<?= htmlspecialchars($user['username']) ?></span>
                </div>
                <div class="profile-detail-item">
                  <span class="profile-detail-label">Department</span>
                  <span class="profile-detail-value"><?= htmlspecialchars($user['department'] ?? 'Operations') ?></span>
                </div>
                <div class="profile-detail-item">
                  <span class="profile-detail-label">Account Status</span>
                  <span class="status-pill active">Active</span>
                </div>
                <div class="profile-detail-item">
                  <span class="profile-detail-label">Member Since</span>
                  <span class="profile-detail-value"><?= substr($user['created_at'], 0, 10) ?></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Forms -->
          <div style="display: flex; flex-direction: column; gap: 24px;">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">My Personal Details</h3>
              </div>

              <form id="profileInfoForm">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Session::csrfToken()) ?>">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                  <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                  </div>

                  <div class="form-group">
                    <label class="form-label">Corporate Email *</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                  </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                  <div class="form-group">
                    <label class="form-label">Department / Unit</label>
                    <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($user['department'] ?? '') ?>">
                  </div>

                  <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">Avatar Badge Color</label>
                  <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="color" name="avatar_color" id="avatarColorInput" value="<?= htmlspecialchars($user['avatar_color'] ?? '#06b6d4') ?>" style="width: 44px; height: 38px; border: none; border-radius: var(--radius-sm); cursor: pointer; background: transparent;">
                    <span style="font-size: 0.825rem; color: var(--text-muted);">Customize your profile avatar color</span>
                  </div>
                </div>

                <div style="display: flex; justify-content: flex-end;">
                  <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
              </form>
            </div>

            <!-- Password Form -->
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Change Password</h3>
              </div>

              <form id="changePasswordForm">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Session::csrfToken()) ?>">

                <div class="form-group">
                  <label class="form-label">Current Password *</label>
                  <div class="password-input-wrapper">
                    <input type="password" name="current_password" class="form-control" placeholder="Enter current password" required>
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
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                  <div class="form-group">
                    <label class="form-label">New Password *</label>
                    <div class="password-input-wrapper">
                      <input type="password" name="new_password" class="form-control" placeholder="Min 6 characters" required>
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
                  </div>

                  <div class="form-group">
                    <label class="form-label">Confirm New Password *</label>
                    <div class="password-input-wrapper">
                      <input type="password" name="confirm_password" class="form-control" placeholder="Confirm password" required>
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
      </div>
    </main>
  </div>

  <div id="toast-container"></div>
  <script src="/assets/js/app.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const infoForm = document.getElementById('profileInfoForm');
      const passForm = document.getElementById('changePasswordForm');
      const colorInput = document.getElementById('avatarColorInput');
      const avatarDisplay = document.getElementById('profileAvatarDisplay');
      const nameDisplay = document.getElementById('profileNameDisplay');

      if (colorInput && avatarDisplay) {
        colorInput.addEventListener('input', (e) => {
          avatarDisplay.style.backgroundColor = e.target.value;
        });
      }

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
          submitBtn.innerHTML = 'Save Changes';

          if (res.ok && res.data.success) {
            App.toast('Profile details updated successfully!', 'success');
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
