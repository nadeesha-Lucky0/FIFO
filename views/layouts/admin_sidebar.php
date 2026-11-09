<?php
use App\Core\Auth;
$currentUser = Auth::user();
$currentScript = basename($_SERVER['SCRIPT_NAME']);
?>
<aside class="app-sidebar" id="appSidebar">
  <div class="sidebar-brand" style="padding: 16px 20px;">
    <a href="/admin/index.php" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
      <img src="/assets/images/EGL-NEW.png" alt="EGL Logo" style="height: 36px; width: auto;">
      <span style="font-weight: 800; color: #650b2b; font-size: 1.05rem;">EGL Admin</span>
    </a>
  </div>

  <div class="sidebar-menu">
    <a href="/admin/index.php" class="sidebar-link <?= ($currentScript === 'index.php') ? 'active' : '' ?>">
      <span><?= (Auth::role() === 'code_admin') ? 'Code Management' : 'Admin Console' ?></span>
    </a>
    <?php if (in_array(Auth::role(), ['super_admin', 'admin'])): ?>
    <a href="/admin/users.php" class="sidebar-link <?= ($currentScript === 'users.php') ? 'active' : '' ?>">
      <span>User Management</span>
    </a>
    <?php endif; ?>
    <a href="/admin/profile.php" class="sidebar-link <?= ($currentScript === 'profile.php') ? 'active' : '' ?>">
      <span>Admin Profile</span>
    </a>
  </div>

  <div class="sidebar-footer">
    <a href="/admin/profile.php" class="user-snippet">
      <div class="avatar" style="background-color: <?= htmlspecialchars($currentUser['avatar_color'] ?? '#4f46e5') ?>;">
        <?= strtoupper(substr($currentUser['name'] ?? 'A', 0, 1)) ?>
      </div>
      <div class="user-snippet-details">
        <div class="user-snippet-name"><?= htmlspecialchars($currentUser['name'] ?? 'Admin User') ?></div>
        <div class="user-snippet-role"><?= htmlspecialchars($currentUser['role_display'] ?? 'Administrator') ?></div>
      </div>
    </a>
  </div>
</aside>
