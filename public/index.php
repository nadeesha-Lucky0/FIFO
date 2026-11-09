<?php
require_once dirname(__DIR__) . '/app/config/config.php';

use App\Core\Auth;

$pageTitle = 'Home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Home | <?= APP_NAME ?></title>
  <link rel="stylesheet" href="/assets/css/design-system.css">
  <link rel="stylesheet" href="/assets/css/landing.css">
</head>
<body>

  <!-- Navigation -->
  <nav class="landing-nav">
    <div class="landing-nav-container">
      <a href="/index.php" class="brand-logo">
        <div class="brand-logo-icon">F</div>
        <span>FIFO System</span>
      </a>

      <div>
        <?php if (Auth::check()): ?>
          <a href="/admin/index.php" class="btn btn-primary btn-sm">Admin Dashboard</a>
          <a href="/logout.php" class="btn btn-secondary btn-sm" style="margin-left: 8px;">Logout</a>
        <?php else: ?>
          <a href="/login.php" class="btn btn-primary btn-sm">Admin Login</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="hero-section">
    <span class="hero-badge">Company FIFO System</span>
    <h1 class="hero-title">Welcome to FIFO Management</h1>
    <p class="hero-description">
      A simple and secure platform for managing company users, profiles, and administration.
    </p>

    <div class="hero-actions">
      <?php if (Auth::check()): ?>
        <a href="/admin/index.php" class="btn btn-primary btn-lg">Go to Admin Profile &amp; Users &rarr;</a>
      <?php else: ?>
        <a href="/login.php" class="btn btn-primary btn-lg">Admin Login &rarr;</a>
      <?php endif; ?>
    </div>
  </section>

  <!-- Footer -->
  <footer class="landing-footer">
    <div>&copy; <?= date('Y') ?> FIFO System. All rights reserved.</div>
  </footer>

</body>
</html>
