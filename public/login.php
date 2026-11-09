<?php
require_once dirname(__DIR__) . '/app/config/config.php';

use App\Core\Auth;
use App\Core\Session;

if (Auth::check()) {
    header("Location: /admin/index.php");
    exit;
}

$pageTitle = 'Admin Login';
$flashMessages = Session::getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars(Session::csrfToken()) ?>">
  <title><?= $pageTitle ?> | <?= APP_NAME ?></title>
  <link rel="stylesheet" href="/assets/css/design-system.css">
  <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>

  <div class="auth-wrapper">
    <div class="auth-card">
      <div style="text-align: center; margin-bottom: 24px;">
        <div class="avatar avatar-lg" style="margin: 0 auto 12px; background: var(--primary);">F</div>
        <h2 style="font-size: 1.4rem; font-weight: 700; color: #0f172a;">Admin Login</h2>
        <p style="font-size: 0.875rem; color: #64748b; margin-top: 4px;">Sign in to access admin profile and user management</p>
      </div>

      <!-- Quick credentials hint -->
      <div class="quick-creds-box" style="line-height: 1.5;">
        <strong>Login Credentials:</strong><br>
        <strong>Super Admin:</strong> <code>admin</code> &bull; <code>Admin@12345</code><br>
        <strong>Code Admin:</strong> <code>admin01</code> &bull; <code>admin01</code>
      </div>

      <div id="authAlert" style="display: none; margin-bottom: 16px;"></div>

      <form id="loginForm" method="POST" action="/api/auth.php">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

        <div class="form-group">
          <label class="form-label" for="identifier">Username or Email</label>
          <input 
            type="text" 
            id="identifier" 
            name="identifier" 
            class="form-control" 
            placeholder="admin" 
            value="admin"
            required 
            autocomplete="username"
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="password-input-wrapper">
            <input 
              type="password" 
              id="password" 
              name="password" 
              class="form-control" 
              placeholder="Password" 
              value="Admin@12345"
              required 
              autocomplete="current-password"
            >
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

        <button type="submit" id="submitBtn" class="btn btn-primary" style="width: 100%; margin-top: 8px;">
          Sign In
        </button>
      </form>

      <div style="text-align: center; margin-top: 20px;">
        <a href="/index.php" style="font-size: 0.85rem; color: #64748b;">&larr; Back to Home</a>
      </div>
    </div>
  </div>

  <div id="toast-container"></div>
  <script src="/assets/js/app.js"></script>
  <script src="/assets/js/auth.js"></script>
</body>
</html>
