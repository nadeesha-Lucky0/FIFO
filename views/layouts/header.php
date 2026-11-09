<?php
use App\Core\Session;
use App\Core\Auth;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars(Session::csrfToken()) ?>">
  <title><?= $pageTitle ?? 'FIFO Enterprise' ?> | <?= APP_NAME ?></title>
  <link rel="stylesheet" href="/assets/css/design-system.css">
  <link rel="stylesheet" href="/assets/css/landing.css">
  <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>
  <div class="ambient-glow-bg"></div>

  <!-- Public Top Navigation -->
  <nav class="landing-nav">
      <a href="<?= (defined('BASE_PATH') ? BASE_PATH : '') ?>/index.php" class="egl-nav-brand" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
        <img src="<?= (defined('BASE_PATH') ? BASE_PATH : '') ?>/assets/images/EGL-NEW.png" alt="EGL Logo" style="height: 38px; width: auto;">
        <span style="font-size: 1.1rem; font-weight: 800; color: #650b2b;">Electricity Generation Lanka</span>
      </a>

      <ul class="nav-links">
        <li><a href="/index.php#features" class="nav-link">Features</a></li>
        <li><a href="/index.php#visualizer" class="nav-link">FIFO Engine</a></li>
        <li><a href="/index.php#metrics" class="nav-link">Metrics</a></li>
        <li><a href="/index.php#about" class="nav-link">Architecture</a></li>
      </ul>

      <div class="nav-cta">
        <?php if (Auth::check()): ?>
          <a href="<?= Auth::isAdmin() ? '/admin/index.php' : '/portal/index.php' ?>" class="btn btn-secondary btn-sm">
            <span>Workspace</span>
            <span class="badge badge-primary"><?= htmlspecialchars(Auth::role()) ?></span>
          </a>
          <a href="/logout.php" class="btn btn-danger btn-sm">Sign Out</a>
        <?php else: ?>
          <a href="/login.php" class="btn btn-secondary btn-sm">Sign In</a>
          <a href="/login.php" class="btn btn-primary btn-sm">Admin Portal</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>
