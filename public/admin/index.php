<?php
require_once dirname(__DIR__, 2) . '/app/config/config.php';

use App\Core\Auth;
use App\Models\User;
use App\Models\Role;

Auth::requireLogin();

$userModel = new User();
$roleModel = new Role();

$currentUser = Auth::user();
$adminDetails = $userModel->findById(Auth::id());
$users = $userModel->getAll();
$roles = $roleModel->all();

$pageTitle = 'Admin Dashboard';
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

  <!-- Admin Top Navbar -->
  <nav class="admin-navbar">
    <a href="/admin/index.php" class="brand-logo">
      <div class="brand-logo-icon">F</div>
      <span>Admin Console</span>
    </a>

    <div style="display: flex; align-items: center; gap: 16px;">
      <a href="/admin/profile.php" style="display: flex; align-items: center; gap: 8px; color: #334155; font-weight: 600; font-size: 0.9rem;">
        <div class="avatar" style="width: 32px; height: 32px; font-size: 0.8rem;"><?= strtoupper(substr($currentUser['name'] ?? 'A', 0, 1)) ?></div>
        <span><?= htmlspecialchars($currentUser['name'] ?? 'Admin') ?></span>
      </a>
      <a href="/admin/profile.php" class="btn btn-secondary btn-sm">Edit Profile</a>
      <a href="/logout.php" class="btn btn-danger btn-sm">Logout</a>
    </div>
  </nav>

  <!-- Main Container -->
  <main class="admin-container">

    <!-- Admin Profile Card -->
    <div class="card" style="margin-bottom: 24px;">
      <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; align-items: center; gap: 16px;">
          <div class="avatar avatar-lg" style="background-color: var(--primary);">
            <?= strtoupper(substr($adminDetails['full_name'] ?? 'A', 0, 1)) ?>
          </div>
          <div>
            <h2 style="font-size: 1.3rem; font-weight: 700; color: #0f172a; margin-bottom: 4px;">
              <?= htmlspecialchars($adminDetails['full_name']) ?>
            </h2>
            <div style="color: #64748b; font-size: 0.875rem;">
              <span>@<?= htmlspecialchars($adminDetails['username']) ?></span> &bull; 
              <span><?= htmlspecialchars($adminDetails['email']) ?></span> &bull; 
              <span class="badge badge-primary"><?= htmlspecialchars($adminDetails['role_display_name']) ?></span>
            </div>
          </div>
        </div>

        <div>
          <a href="/admin/profile.php" class="btn btn-secondary btn-sm">Manage Profile &amp; Password</a>
        </div>
      </div>
    </div>

    <!-- User Management Section with Add User Button -->
    <div class="card">
      <div class="card-header">
        <div>
          <h3 class="card-title">User Accounts</h3>
          <p style="font-size: 0.85rem; color: #64748b; margin-top: 2px;">Manage registered company users</p>
        </div>

        <button type="button" class="btn btn-primary" id="openCreateUserModal">
          + Add User
        </button>
      </div>

      <!-- Users Table -->
      <div style="overflow-x: auto;">
        <table class="data-table">
          <thead>
            <tr>
              <th>User</th>
              <th>Role</th>
              <th>Department</th>
              <th>Status</th>
              <th>Created Date</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody id="usersTableBody">
            <?php foreach ($users as $u): ?>
            <tr id="user-row-<?= $u['id'] ?>">
              <td>
                <div class="user-cell">
                  <div class="avatar" style="background-color: <?= htmlspecialchars($u['avatar_color'] ?? '#2563eb') ?>;">
                    <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                  </div>
                  <div>
                    <div class="user-cell-name"><?= htmlspecialchars($u['full_name']) ?></div>
                    <div class="user-cell-email">@<?= htmlspecialchars($u['username']) ?> &bull; <?= htmlspecialchars($u['email']) ?></div>
                  </div>
                </div>
              </td>
              <td>
                <span class="badge badge-primary"><?= htmlspecialchars($u['role_display_name']) ?></span>
              </td>
              <td>
                <span><?= htmlspecialchars($u['department'] ?? 'General') ?></span>
              </td>
              <td>
                <span class="badge badge-success"><?= ucfirst(htmlspecialchars($u['status'])) ?></span>
              </td>
              <td style="color: #64748b; font-size: 0.85rem;">
                <?= substr($u['created_at'], 0, 10) ?>
              </td>
              <td style="text-align: right;">
                <?php if ($u['id'] !== Auth::id()): ?>
                  <button class="btn btn-danger btn-sm" onclick="deleteUser(<?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['full_name'])) ?>')">
                    Delete
                  </button>
                <?php else: ?>
                  <span style="font-size: 0.8rem; color: #94a3b8;">Current Admin</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>

  <!-- Add User Modal -->
  <div class="modal-backdrop" id="createUserModal">
    <div class="modal-dialog">
      <div class="modal-header">
        <h3 class="modal-title">Add New User</h3>
        <button type="button" class="modal-close" data-close-modal>&times;</button>
      </div>

      <form id="createUserForm">
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Full Name *</label>
            <input type="text" name="full_name" class="form-control" placeholder="e.g. John Doe" required>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
            <div class="form-group">
              <label class="form-label">Username *</label>
              <input type="text" name="username" class="form-control" placeholder="johndoe" required>
            </div>

            <div class="form-group">
              <label class="form-label">Email Address *</label>
              <input type="email" name="email" class="form-control" placeholder="john@company.com" required>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
            <div class="form-group">
              <label class="form-label">Role *</label>
              <select name="role_id" class="form-select" required>
                <option value="4">User / Staff</option>
                <option value="3">Manager</option>
                <option value="2">Admin</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Department</label>
              <input type="text" name="department" class="form-control" placeholder="Operations" value="Operations">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Password *</label>
            <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
          <button type="submit" class="btn btn-primary">Add User</button>
        </div>
      </form>
    </div>
  </div>

  <div id="toast-container"></div>
  <script src="/assets/js/app.js"></script>
  <script src="/assets/js/users.js"></script>
</body>
</html>
