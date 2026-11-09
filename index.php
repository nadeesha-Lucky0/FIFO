<?php
require_once __DIR__ . '/app/config/config.php';

use App\Core\Auth;
use App\Core\Session;

$base = defined('BASE_PATH') ? BASE_PATH : '';

// If already logged in, redirect directly to admin dashboard
if (Auth::check()) {
    header("Location: " . $base . "/admin/index.php");
    exit;
}

$pageTitle = 'Account Information Portal';
$currentYear = (int)date('Y');
$months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

// Fetch departments dynamically from database
$deptModel = new \App\Models\Department();
$departments = $deptModel->getAllActive();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars(Session::csrfToken()) ?>">
  <meta name="base-url" content="<?= htmlspecialchars($base) ?>">
  <title><?= $pageTitle ?> | <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/design-system.css">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/dashboard.css">
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html, body {
      height: 100%;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      color: #1e293b;
    }

    /* Beautiful Light-Blurred Background Container */
    body {
      position: relative;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 30px 16px;
      margin: 0;
      background-color: #0f172a;
    }

    /* Fixed Background Image Layer with Perfect Light Blur */
    .bg-image-layer {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background-image: url('<?= $base ?>/assets/images/Victoria-Dam1.jpg');
      background-position: center center;
      background-repeat: no-repeat;
      background-size: cover;
      filter: blur(3px) brightness(0.96);
      -webkit-filter: blur(3px) brightness(0.96);
      transform: scale(1.03); /* Clean blur edges */
      z-index: 0;
    }

    /* Subtle Translucent Overlay - preserves full clarity of the background image */
    .bg-overlay-layer {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: linear-gradient(180deg, rgba(15, 23, 42, 0.18) 0%, rgba(255, 255, 255, 0.12) 50%, rgba(15, 23, 42, 0.25) 100%);
      z-index: 1;
      pointer-events: none;
    }

    /* Main Content Wrapper */
    .portal-container {
      position: relative;
      z-index: 2;
      width: 100%;
      max-width: 620px;
      margin: 0 auto;
    }

    /* Office Enterprise Portal Card */
    .portal-office-card {
      background: rgba(255, 255, 255, 0.96);
      border: 1px solid rgba(255, 255, 255, 0.85);
      border-radius: 8px;
      box-shadow: 0 20px 45px rgba(0, 0, 0, 0.22), 0 4px 12px rgba(0, 0, 0, 0.1);
      padding: 28px 32px 30px;
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
    }

    /* Corporate Header with EGL Logo */
    .portal-office-header {
      display: flex;
      align-items: center;
      gap: 16px;
      margin-bottom: 22px;
      padding-bottom: 16px;
      border-bottom: 2px solid #650b2b;
    }

    .portal-office-logo {
      height: 52px;
      width: auto;
      object-fit: contain;
      flex-shrink: 0;
    }

    .portal-title-group h1 {
      font-size: 1.25rem;
      font-weight: 800;
      color: #650b2b;
      line-height: 1.25;
      letter-spacing: -0.01em;
    }

    .portal-title-group p {
      font-size: 0.82rem;
      color: #64748b;
      font-weight: 600;
      margin-top: 3px;
    }

    /* Office Fieldset Box */
    .office-fieldset {
      border: 1px solid #94a3b8;
      border-radius: 4px;
      padding: 20px 24px 24px;
      background: #ffffff;
      margin: 0;
      position: relative;
    }

    .office-legend {
      font-size: 0.95rem;
      font-weight: 700;
      color: #334155;
      padding: 0 8px;
      margin-left: 8px;
      width: auto;
    }

    .form-group-row {
      margin-bottom: 15px;
    }

    .office-label {
      display: block;
      font-size: 0.86rem;
      font-weight: 500;
      color: #334155;
      margin-bottom: 5px;
    }

    /* Professional Office Text Boxes & Dropdowns */
    .office-input, .office-select {
      width: 100%;
      height: 34px;
      padding: 5px 10px;
      font-size: 0.88rem;
      border: 1px solid #94a3b8;
      border-radius: 3px;
      background-color: #ffffff;
      color: #0f172a;
      font-family: inherit;
      outline: none;
      transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .office-input:focus, .office-select:focus {
      border-color: #650b2b;
      box-shadow: 0 0 0 2px rgba(101, 11, 43, 0.15);
    }

    .office-select {
      color: #334155;
      cursor: pointer;
    }

    .password-input-wrapper {
      position: relative;
      width: 100%;
      display: flex;
      align-items: center;
    }

    .password-input-wrapper .office-input {
      padding-right: 36px;
    }

    .password-input-wrapper .btn-toggle-password {
      position: absolute;
      right: 0;
      top: 0;
      bottom: 0;
      width: 34px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: transparent;
      border: none;
      color: #64748b;
      cursor: pointer;
      padding: 0;
      transition: color 0.15s ease;
    }

    .password-input-wrapper .btn-toggle-password:hover {
      color: #650b2b;
    }

    .password-input-wrapper .btn-toggle-password svg {
      pointer-events: none;
      display: block;
    }

    /* Professional Office Action Button */
    .office-btn-login {
      margin-top: 10px;
      background: #f1f5f9;
      color: #0f172a;
      border: 1px solid #64748b;
      border-radius: 3px;
      padding: 6px 24px;
      font-size: 0.88rem;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
      transition: all 0.15s ease;
    }

    .office-btn-login:hover {
      background: #650b2b;
      color: #ffffff;
      border-color: #650b2b;
      box-shadow: 0 2px 6px rgba(101, 11, 43, 0.25);
    }

    .office-btn-login:active {
      transform: translateY(1px);
    }

    .office-btn-login:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    /* Alert Message */
    #authAlert {
      margin-bottom: 16px;
      padding: 10px 14px;
      border-radius: 4px;
      font-size: 0.85rem;
      display: none;
    }

    /* Professional Copyright Line */
    .portal-copy-footer {
      margin-top: 16px;
      text-align: center;
      font-size: 0.78rem;
      color: #64748b;
      font-weight: 500;
    }
  </style>
</head>
<body>

  <!-- Blurred Background Image Layer -->
  <div class="bg-image-layer"></div>
  <!-- Soft Translucent Overlay Layer -->
  <div class="bg-overlay-layer"></div>

  <!-- Interactive Portal Container -->
  <div class="portal-container">

    <div class="portal-office-card">

      <!-- Corporate Header with EGL Logo -->
      <div class="portal-office-header">
        <img src="<?= $base ?>/assets/images/EGL-NEW.png" alt="Electricity Generation Lanka (Pvt) Ltd Logo" class="portal-office-logo">
        <div class="portal-title-group">
          <h1>Electricity Generation Lanka (Pvt) Ltd</h1>
          <p>FIFO Operational Ledger &amp; Departmental Management Portal</p>
        </div>
      </div>

      <div id="authAlert"></div>

      <!-- Account Information Form -->
      <form id="loginForm" method="POST" action="<?= $base ?>/api/auth.php">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

        <fieldset class="office-fieldset">
          <legend class="office-legend">Account Information</legend>

          <!-- Username -->
          <div class="form-group-row">
            <label class="office-label" for="identifier">Username:</label>
            <input 
              type="text" 
              id="identifier" 
              name="identifier" 
              class="office-input" 
              required 
              autocomplete="username"
            >
          </div>

          <!-- Password -->
          <div class="form-group-row">
            <label class="office-label" for="password">Password:</label>
            <div class="password-input-wrapper">
              <input 
                type="password" 
                id="password" 
                name="password" 
                class="office-input" 
                required 
                autocomplete="current-password"
              >
              <button type="button" class="btn-toggle-password" aria-label="Show password" title="Show password" tabindex="-1" onclick="if(window.togglePasswordVisibility){window.togglePasswordVisibility(this);}">
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

          <!-- Department -->
          <div class="form-group-row">
            <label class="office-label" for="department">Department:</label>
            <input 
              type="text" 
              id="department" 
              name="department" 
              list="departmentList" 
              class="office-input"
            >
            <datalist id="departmentList">
              <?php foreach ($departments as $dept): ?>
                <option value="<?= htmlspecialchars($dept) ?>"></option>
              <?php endforeach; ?>
            </datalist>
          </div>

          <!-- Login Year -->
          <div class="form-group-row">
            <label class="office-label" for="login_year">Login Year:</label>
            <select id="login_year" name="login_year" class="office-select">
              <option value="" selected>Select Login Year</option>
              <?php for ($y = $currentYear + 1; $y >= $currentYear - 6; $y--): ?>
                <option value="<?= $y ?>"><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>

          <!-- Login Month -->
          <div class="form-group-row">
            <label class="office-label" for="login_month">Login Month:</label>
            <select id="login_month" name="login_month" class="office-select">
              <option value="" selected>Select Month</option>
              <?php foreach ($months as $idx => $m): ?>
                <option value="<?= $m ?>"><?= $m ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Log In Button -->
          <div style="margin-top: 10px;">
            <button type="submit" id="submitBtn" class="office-btn-login">
              Log In
            </button>
          </div>

        </fieldset>
      </form>

    </div>

    <!-- Footer Copyright Note -->
    <div class="portal-copy-footer">
      &copy; <?= date('Y') ?> Electricity Generation Lanka (Pvt) Ltd. All rights reserved.
    </div>

  </div>

  <div id="toast-container"></div>
  <script>
    window.togglePasswordVisibility = function(btn) {
      if (!btn) return;
      var wrapper = btn.closest('.password-input-wrapper') || btn.parentElement;
      var input = wrapper ? wrapper.querySelector('input') : null;
      if (!input) return;

      var isPassword = (input.type === 'password');
      input.type = isPassword ? 'text' : 'password';

      var eyeShow = btn.querySelector('.eye-show');
      var eyeHide = btn.querySelector('.eye-hide');

      if (eyeShow) eyeShow.style.setProperty('display', isPassword ? 'none' : 'block', 'important');
      if (eyeHide) eyeHide.style.setProperty('display', isPassword ? 'block' : 'none', 'important');

      var label = isPassword ? 'Hide password' : 'Show password';
      btn.setAttribute('aria-label', label);
      btn.setAttribute('title', label);
    };
  </script>
  <script src="<?= $base ?>/assets/js/app.js?v=<?= time() ?>"></script>
  <script src="<?= $base ?>/assets/js/auth.js?v=<?= time() ?>"></script>
</body>
</html>
