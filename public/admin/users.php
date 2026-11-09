<?php
require_once dirname(__DIR__, 2) . '/app/config/config.php';

use App\Core\Auth;
use App\Models\User;
use App\Models\Role;

Auth::requireAdmin();

if (!in_array(Auth::role(), ['super_admin', 'admin'])) {
    header("Location: /admin/index.php");
    exit;
}

$userModel = new User();
$roleModel = new Role();

$roles = $roleModel->all();
$users = $userModel->getAll();

$pageHeader = 'User Management & Access Control';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars(\App\Core\Session::csrfToken()) ?>">
  <title>User Management | <?= APP_NAME ?></title>
  <link rel="stylesheet" href="/assets/css/design-system.css">
  <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>
  <div class="ambient-glow-bg"></div>

  <div class="app-shell">
    <?php require_once ROOT_PATH . '/views/layouts/admin_sidebar.php'; ?>

    <main class="app-main">
      <?php require_once ROOT_PATH . '/views/layouts/admin_header.php'; ?>

      <div class="app-content">
        <!-- Action Header -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
          <div>
            <h2 style="font-size: 1.4rem; font-weight: 800; color: #fff;">Team Members &amp; System Accounts</h2>
            <p style="font-size: 0.875rem; color: var(--text-secondary);">
              Provision accounts, assign departmental roles, and manage access credentials across the FIFO pipeline.
            </p>
          </div>

          <button type="button" class="btn btn-primary" id="openCreateUserModal">
            <span>+ Create New User</span>
          </button>
        </div>

        <!-- User Table Card -->
        <div class="table-container">
          <!-- Toolbar -->
          <div class="table-toolbar">
            <div class="search-box">
              <input type="text" id="searchUsers" class="search-input" placeholder="Search by name, email, role, department...">
            </div>

            <div class="filter-group">
              <select id="filterRole" class="form-select" style="width: auto; padding: 8px 14px; font-size: 0.85rem;">
                <option value="">All Roles</option>
                <?php foreach ($roles as $r): ?>
                  <option value="<?= htmlspecialchars($r['name']) ?>"><?= htmlspecialchars($r['display_name']) ?></option>
                <?php endforeach; ?>
              </select>

              <select id="filterStatus" class="form-select" style="width: auto; padding: 8px 14px; font-size: 0.85rem;">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
                <option value="pending">Pending</option>
              </select>
            </div>
          </div>

          <!-- Table -->
          <div style="overflow-x: auto;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Team Member</th>
                  <th>System Role</th>
                  <th>Department</th>
                  <th>Account Status</th>
                  <th>Created Date</th>
                  <th style="text-align: right;">Actions</th>
                </tr>
              </thead>
              <tbody id="usersTableBody">
                <?php foreach ($users as $u): ?>
                <tr id="user-row-<?= $u['id'] ?>">
                  <td>
                    <div class="user-cell">
                      <div class="avatar" style="background-color: <?= htmlspecialchars($u['avatar_color'] ?? '#6366f1') ?>;">
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
                    <span style="color: #cbd5e1; font-size: 0.875rem;"><?= htmlspecialchars($u['department'] ?? 'General') ?></span>
                  </td>
                  <td>
                    <span class="status-pill <?= htmlspecialchars($u['status']) ?>">
                      <span style="width: 6px; height: 6px; border-radius: 50%; background: currentColor;"></span>
                      <?= ucfirst(htmlspecialchars($u['status'])) ?>
                    </span>
                  </td>
                  <td>
                    <span style="font-size: 0.8rem; color: var(--text-muted); font-family: var(--font-mono);">
                      <?= substr($u['created_at'], 0, 10) ?>
                    </span>
                  </td>
                  <td style="text-align: right;">
                    <div style="display: inline-flex; gap: 8px;">
                      <?php if ($u['id'] !== Auth::id()): ?>
                        <button class="btn btn-secondary btn-sm" onclick="toggleUserStatus(<?= $u['id'] ?>, '<?= $u['status'] === 'active' ? 'suspended' : 'active' ?>')">
                          <?= $u['status'] === 'active' ? 'Suspend' : 'Activate' ?>
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="deleteUser(<?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['full_name'])) ?>')">
                          Delete
                        </button>
                      <?php else: ?>
                        <span class="badge badge-info" style="text-transform: none; font-size: 0.75rem;">Current Account</span>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Reusable Create User Modal -->
  <?php require_once ROOT_PATH . '/views/components/modal.php'; ?>

  <div id="toast-container"></div>
  <script src="/assets/js/app.js"></script>
  <script src="/assets/js/users.js"></script>
</body>
</html>
