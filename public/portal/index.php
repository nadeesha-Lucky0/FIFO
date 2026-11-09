<?php
require_once dirname(__DIR__, 2) . '/app/config/config.php';

use App\Core\Auth;
use App\Models\User;
use App\Config\Database;

// Security check: Must be logged in
Auth::requireLogin();

$currentUser = Auth::user();
$db = Database::getStationConnection($currentUser['location_id'] ?? null);

// Fetch FIFO queue batches
$fifoBatches = [];
try {
    $stmt = $db->query("SELECT * FROM fifo_batches ORDER BY received_date ASC");
    $fifoBatches = $stmt->fetchAll();
} catch (\Exception $e) {}

$pageHeader = 'FIFO Dispatch & Operator Terminal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars(\App\Core\Session::csrfToken()) ?>">
  <title>FIFO Operator Portal | <?= APP_NAME ?></title>
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
          <div class="system-status-indicator">
            <span class="pulse-dot"></span>
            <span>FIFO Enforced</span>
          </div>
          <div style="display: flex; align-items: center; gap: 12px;">
            <a href="/portal/profile.php" class="avatar" style="background-color: <?= htmlspecialchars($currentUser['avatar_color'] ?? '#06b6d4') ?>;">
              <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
            </a>
            <a href="/logout.php" class="btn btn-secondary btn-sm">Sign Out</a>
          </div>
        </div>
      </header>

      <div class="app-content">
        <!-- Staff Banner -->
        <div style="background: linear-gradient(135deg, rgba(6, 182, 212, 0.2) 0%, rgba(16, 185, 129, 0.15) 100%); border: 1px solid rgba(6, 182, 212, 0.3); border-radius: var(--radius-lg); padding: 24px 32px; margin-bottom: 28px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
          <div>
            <div style="font-size: 0.8rem; color: var(--secondary); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
              Active Terminal Station &bull; <?= htmlspecialchars($currentUser['role_display'] ?? 'Operator') ?>
            </div>
            <h2 style="font-size: 1.5rem; font-weight: 800; color: #fff;">
              Welcome, <?= htmlspecialchars($currentUser['name'] ?? 'Team Member') ?>
            </h2>
            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px;">
              Department: <strong style="color: #fff;"><?= htmlspecialchars($currentUser['department'] ?? 'Warehouse') ?></strong> &bull; Dispatches must strictly follow the First-In, First-Out sequence below.
            </p>
          </div>

          <div style="display: flex; gap: 10px;">
            <a href="/portal/profile.php" class="btn btn-secondary">
              <span>My Profile</span>
            </a>
          </div>
        </div>

        <!-- FIFO Queue Table -->
        <div class="table-container">
          <div class="card-header" style="margin-bottom: 0; padding: 20px 24px;">
            <div>
              <h3 class="card-title">Active FIFO Inventory Batches (Chronological Sequence)</h3>
              <div style="font-size: 0.825rem; color: var(--text-muted);">
                Items at the top arrived earliest and must be picked first.
              </div>
            </div>
            <span class="badge badge-success">Queue Synchronized</span>
          </div>

          <div style="overflow-x: auto;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>FIFO Priority</th>
                  <th>Batch / SKU</th>
                  <th>Item Name &amp; Category</th>
                  <th>Bin Location</th>
                  <th>Received Date</th>
                  <th>Available Qty</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php $priority = 1; foreach ($fifoBatches as $batch): ?>
                <tr>
                  <td>
                    <?php if ($priority === 1): ?>
                      <span class="badge badge-success" style="font-size: 0.8rem;">#1 DISPATCH FIRST</span>
                    <?php else: ?>
                      <span class="badge badge-primary">#<?= $priority ?> QUEUED</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div style="font-weight: 700; color: #fff; font-family: var(--font-mono);">
                      <?= htmlspecialchars($batch['batch_number']) ?>
                    </div>
                    <div style="font-size: 0.775rem; color: var(--secondary); font-family: var(--font-mono);">
                      <?= htmlspecialchars($batch['sku']) ?>
                    </div>
                  </td>
                  <td>
                    <div style="font-weight: 600; color: #f1f5f9;">
                      <?= htmlspecialchars($batch['item_name']) ?>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($batch['category']) ?></div>
                  </td>
                  <td>
                    <span class="badge badge-info"><?= htmlspecialchars($batch['location_bin']) ?></span>
                  </td>
                  <td>
                    <span style="font-family: var(--font-mono); color: #cbd5e1; font-size: 0.85rem;">
                      <?= htmlspecialchars($batch['received_date']) ?>
                    </span>
                  </td>
                  <td>
                    <span style="font-weight: 700; color: #fff; font-size: 1rem;"><?= $batch['remaining_quantity'] ?></span>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">units</span>
                  </td>
                  <td>
                    <?php if ($priority === 1): ?>
                      <button class="btn btn-primary btn-sm" onclick="App.toast('Batch <?= htmlspecialchars($batch['batch_number']) ?> picked for dispatch!', 'success')">
                        Pick Stock
                      </button>
                    <?php else: ?>
                      <button class="btn btn-secondary btn-sm" style="opacity: 0.6; cursor: not-allowed;" title="Cannot pick until earlier batches are depleted" disabled>
                        Locked
                      </button>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php $priority++; endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div id="toast-container"></div>
  <script src="/assets/js/app.js"></script>
</body>
</html>
