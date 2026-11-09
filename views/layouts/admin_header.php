<?php
use App\Core\Auth;
$currentUser = Auth::user();
?>
<header class="app-topbar">
  <div class="topbar-left">
    <h1 class="page-title"><?= $pageHeader ?? 'Dashboard' ?></h1>
  </div>

  <div class="topbar-right">
    <div class="system-status-indicator">
      <span class="pulse-dot"></span>
      <span>FIFO Engine: Active</span>
    </div>

    <div style="display: flex; align-items: center; gap: 12px;">
      <a href="/admin/profile.php" class="avatar" style="background-color: <?= htmlspecialchars($currentUser['avatar_color'] ?? '#4f46e5') ?>;" title="Manage Profile">
        <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
      </a>
      <a href="/logout.php" class="btn btn-secondary btn-sm" title="Sign out of current session">
        <span>Logout</span>
      </a>
    </div>
  </div>
</header>
