<?php
use App\Core\Auth;
$currentUser = Auth::user();
$currentScript = basename($_SERVER['SCRIPT_NAME']);
?>
<aside class="app-sidebar" id="appSidebar">
  <div class="sidebar-brand" style="padding: 16px 20px;">
    <a href="/portal/index.php" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
      <img src="/assets/images/EGL-NEW.png" alt="EGL Logo" style="height: 36px; width: auto;">
      <span style="font-weight: 800; color: #650b2b; font-size: 1.05rem;">EGL Portal</span>
    </a>
  </div>

  <div class="sidebar-menu">
    <a href="/portal/index.php" class="sidebar-link <?= ($currentScript === 'index.php') ? 'active' : '' ?>">
      <span>FIFO Queue Workspace</span>
    </a>
    <a href="/portal/profile.php" class="sidebar-link <?= ($currentScript === 'profile.php') ? 'active' : '' ?>">
      <span>My Profile</span>
    </a>
    <?php if (Auth::isAdmin()): ?>
    <a href="/admin/index.php" class="sidebar-link">
      <span>Switch to Admin Panel</span>
    </a>
    <?php endif; ?>
  </div>

  <div class="sidebar-footer">
    <a href="/portal/profile.php" class="user-snippet">
      <div class="avatar" style="background-color: <?= htmlspecialchars($currentUser['avatar_color'] ?? '#06b6d4') ?>;">
        <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
      </div>
      <div class="user-snippet-details">
        <div class="user-snippet-name"><?= htmlspecialchars($currentUser['name'] ?? 'Staff Member') ?></div>
        <div class="user-snippet-role"><?= htmlspecialchars($currentUser['role_display'] ?? 'Operator') ?></div>
      </div>
    </a>
  </div>
</aside>
