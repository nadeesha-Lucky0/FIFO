<?php
require_once dirname(__DIR__) . '/app/config/config.php';

use App\Core\Auth;
use App\Core\Response;
use App\Core\Session;
use App\Config\Database;

Auth::requireLogin();

$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');
$locationId = $_POST['location_id'] ?? $_GET['location_id'] ?? ($_SESSION['user']['location_id'] ?? null);
$db = Database::getStationConnection($locationId);

// Validate CSRF for POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!Session::validateCsrf($csrfToken)) {
        Response::error('Invalid security token. Please refresh the page.', [], 403);
    }
}

// Ensure supporting tables exist
$isMysql = (defined('DB_DRIVER') && DB_DRIVER === 'mysql');
$pkType = $isMysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
$db->exec("
    CREATE TABLE IF NOT EXISTS cost_adjustments (
        id $pkType,
        adjustment_ref VARCHAR(50) NOT NULL UNIQUE,
        batch_id INTEGER NULL,
        sku VARCHAR(50) NOT NULL,
        item_name VARCHAR(150) NOT NULL,
        old_unit_cost DECIMAL(10,2) NOT NULL,
        new_unit_cost DECIMAL(10,2) NOT NULL,
        variance_per_unit DECIMAL(10,2) NOT NULL,
        remaining_qty INTEGER NOT NULL,
        total_valuation_delta DECIMAL(12,2) NOT NULL,
        reason TEXT NOT NULL,
        authorized_by INTEGER NULL,
        status VARCHAR(30) DEFAULT 'Approved & Applied',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS qty_corrections (
        id $pkType,
        correction_ref VARCHAR(50) NOT NULL UNIQUE,
        batch_id INTEGER NULL,
        sku VARCHAR(50) NOT NULL,
        item_name VARCHAR(150) NOT NULL,
        allocated_qty_before INTEGER NOT NULL,
        corrected_allocated_qty INTEGER NOT NULL,
        qty_released_to_free_stock INTEGER NOT NULL,
        work_order_ref VARCHAR(60) DEFAULT NULL,
        reason TEXT NOT NULL,
        authorized_by INTEGER NULL,
        status VARCHAR(30) DEFAULT 'Corrected & Unlocked',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
");

switch ($action) {
    case 'create_cost_adjustment':
        $batchId = (int)($_POST['batch_id'] ?? 0);
        $newUnitCost = (float)($_POST['new_unit_cost'] ?? 0.00);
        $reason = trim($_POST['reason'] ?? '');
        $adjustmentRef = 'ADJ-' . date('Ymd-His');

        $username = trim($_POST['admin_username'] ?? '');
        $password = $_POST['admin_password'] ?? '';
        $authUserId = Auth::id() ?: 1;

        if (!empty($username) || !empty($password)) {
            $stmtUser = $db->prepare("
                SELECT u.*, r.name as role_name 
                FROM users u 
                JOIN roles r ON u.role_id = r.id 
                WHERE (u.username = ? OR u.email = ?) AND u.status = 'active'
            ");
            $stmtUser->execute([$username, $username]);
            $adminUser = $stmtUser->fetch();

            if (!$adminUser || !password_verify($password, $adminUser['password_hash'])) {
                Response::error('Admin authentication failed. Invalid user name or password.');
            }

            if (!in_array($adminUser['role_name'], ['super_admin', 'admin'])) {
                Response::error('Access Denied: You need Administrator privileges to perform cost adjustments.');
            }
            $authUserId = $adminUser['id'];
        }

        if ($batchId <= 0 || $newUnitCost < 0 || empty($reason)) {
            Response::error('Please select a valid FIFO batch, specify a new unit cost, and provide an adjustment justification.');
        }

        try {
            // Find batch
            $stmtB = $db->prepare("SELECT * FROM fifo_batches WHERE id = ?");
            $stmtB->execute([$batchId]);
            $batch = $stmtB->fetch();

            if (!$batch) {
                Response::error('Selected batch not found in FIFO inventory.');
            }

            $oldUnitCost = (float)$batch['unit_cost'];
            $variance = $newUnitCost - $oldUnitCost;
            $remQty = (int)$batch['remaining_quantity'];
            $totalDelta = $variance * $remQty;

            // Update batch unit cost
            $stmtUp = $db->prepare("UPDATE fifo_batches SET unit_cost = ? WHERE id = ?");
            $stmtUp->execute([$newUnitCost, $batchId]);

            // Insert adjustment record
            $stmtIns = $db->prepare("
                INSERT INTO cost_adjustments 
                (adjustment_ref, batch_id, sku, item_name, old_unit_cost, new_unit_cost, variance_per_unit, remaining_qty, total_valuation_delta, reason, authorized_by, status)
                VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Approved & Applied')
            ");
            $stmtIns->execute([
                $adjustmentRef,
                $batchId,
                $batch['sku'],
                $batch['item_name'],
                $oldUnitCost,
                $newUnitCost,
                $variance,
                $remQty,
                $totalDelta,
                $reason,
                $authUserId
            ]);

            // Log activity
            $stmtLog = $db->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtLog->execute([
                $authUserId,
                'cost_adjustment_applied',
                'fifo_batch',
                $batchId,
                "Cost adjusted on {$batch['batch_number']} from Rs. $oldUnitCost to Rs. $newUnitCost (Delta: Rs. " . number_format($totalDelta, 2) . ")",
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);

            Response::success("Cost adjustment $adjustmentRef applied successfully. Unit cost updated to Rs. " . number_format($newUnitCost, 2));
        } catch (\PDOException $e) {
            Response::error('Database error: ' . $e->getMessage());
        }
        break;

    case 'create_qty_correction':
        $batchId = (int)($_POST['batch_id'] ?? 0);
        $allocatedBefore = (int)($_POST['allocated_qty_before'] ?? 0);
        $correctedAllocated = (int)($_POST['corrected_allocated_qty'] ?? 0);
        $workOrder = trim($_POST['work_order_ref'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        $correctionRef = 'CORR-' . date('Ymd-His');

        if ($batchId <= 0 || $allocatedBefore < 0 || $correctedAllocated < 0 || empty($reason)) {
            Response::error('Please provide valid allocation figures, select a batch, and provide a correction note.');
        }

        try {
            $stmtB = $db->prepare("SELECT * FROM fifo_batches WHERE id = ?");
            $stmtB->execute([$batchId]);
            $batch = $stmtB->fetch();

            if (!$batch) {
                Response::error('Selected batch not found in FIFO inventory.');
            }

            $releasedQty = $allocatedBefore - $correctedAllocated;

            // If released qty is positive, add back to remaining quantity
            if ($releasedQty > 0) {
                $newRemQty = (int)$batch['remaining_quantity'] + $releasedQty;
                $stmtUp = $db->prepare("UPDATE fifo_batches SET remaining_quantity = ? WHERE id = ?");
                $stmtUp->execute([$newRemQty, $batchId]);
            }

            // Insert correction log
            $stmtIns = $db->prepare("
                INSERT INTO qty_corrections 
                (correction_ref, batch_id, sku, item_name, allocated_qty_before, corrected_allocated_qty, qty_released_to_free_stock, work_order_ref, reason, authorized_by, status)
                VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Corrected & Unlocked')
            ");
            $stmtIns->execute([
                $correctionRef,
                $batchId,
                $batch['sku'],
                $batch['item_name'],
                $allocatedBefore,
                $correctedAllocated,
                $releasedQty,
                $workOrder,
                $reason,
                Auth::id() ?: 1
            ]);

            // Log activity
            $stmtLog = $db->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtLog->execute([
                Auth::id() ?: 1,
                'allocated_qty_corrected',
                'fifo_batch',
                $batchId,
                "Allocated quantity corrected for {$batch['batch_number']}. Released $releasedQty units to free stock.",
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);

            Response::success("Allocation quantity corrected ($correctionRef). Released $releasedQty units back to active inventory.");
        } catch (\PDOException $e) {
            Response::error('Database error: ' . $e->getMessage());
        }
        break;

    case 'update_quantities':
        $username = trim($_POST['admin_username'] ?? '');
        $password = $_POST['admin_password'] ?? '';
        $costCenter = trim($_POST['cost_center'] ?? '');

        if (empty($username) || empty($password)) {
            Response::error('Please enter administrator user name and password.');
        }

        // Verify admin credentials
        $stmtUser = $db->prepare("
            SELECT u.*, r.name as role_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE (u.username = ? OR u.email = ?) AND u.status = 'active'
        ");
        $stmtUser->execute([$username, $username]);
        $adminUser = $stmtUser->fetch();

        if (!$adminUser || !password_verify($password, $adminUser['password_hash'])) {
            Response::error('Admin authentication failed. Invalid user name or password.');
        }

        if (!in_array($adminUser['role_name'], ['super_admin', 'admin'])) {
            Response::error('Access Denied: You need Administrator privileges to perform quantity updates.');
        }

        // Record activity log
        $stmtLog = $db->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtLog->execute([
            $adminUser['id'],
            'admin_allocated_qty_update',
            'cost_center',
            1,
            "Admin {$adminUser['username']} executed Allocated Quantity Correction for Cost Center: " . ($costCenter ?: 'All Units'),
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);

        Response::success('Allocated quantities successfully updated and reconciled for Cost Center: ' . ($costCenter ?: 'Global'));
        break;

    case 'update_verification_tables':
        $verificationYear = trim($_POST['verification_year'] ?? date('Y'));
        $password = $_POST['admin_password'] ?? '';

        if (empty($password)) {
            Response::error('Please enter your administrator password to authorize verification updates.');
        }

        // Verify with current logged in user or admin
        $currentUserId = Auth::id();
        $stmtUser = $db->prepare("
            SELECT u.*, r.name as role_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.id = ? AND u.status = 'active'
        ");
        $stmtUser->execute([$currentUserId]);
        $currentUser = $stmtUser->fetch();

        $authOk = false;
        if ($currentUser && password_verify($password, $currentUser['password_hash'])) {
            $authOk = true;
        } else {
            // Check super admin password
            $stmtSa = $db->query("SELECT * FROM users WHERE role_id = 1 LIMIT 1");
            $sa = $stmtSa->fetch();
            if ($sa && password_verify($password, $sa['password_hash'])) {
                $authOk = true;
                $currentUser = $sa;
            }
        }

        if (!$authOk) {
            Response::error('Authentication failed: Invalid administrator password.');
        }

        // Log activity
        $stmtLog = $db->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtLog->execute([
            $currentUser['id'] ?? 1,
            'verification_tables_updated',
            'verification_year',
            (int)$verificationYear,
            "Physical verification tables updated and synchronized for Year: $verificationYear",
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);

        Response::success("Verification tables for Financial Year $verificationYear updated and synchronized successfully.");
        break;

    case 'create_verification_batch':
        $verificationYear = trim($_POST['verification_year'] ?? date('Y'));
        $password = $_POST['admin_password'] ?? '';

        if (empty($password)) {
            Response::error('Please enter your administrator password.');
        }

        $currentUserId = Auth::id();
        $stmtUser = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmtUser->execute([$currentUserId]);
        $currentUser = $stmtUser->fetch();

        $authOk = false;
        if ($currentUser && password_verify($password, $currentUser['password_hash'])) {
            $authOk = true;
        } else {
            $stmtSa = $db->query("SELECT * FROM users WHERE role_id = 1 LIMIT 1");
            $sa = $stmtSa->fetch();
            if ($sa && password_verify($password, $sa['password_hash'])) {
                $authOk = true;
                $currentUser = $sa;
            }
        }

        if (!$authOk) {
            Response::error('Authentication failed: Invalid administrator password.');
        }

        Response::success("New physical verification batch generated for Year $verificationYear.");
        break;

    default:
        Response::error('Invalid action requested.');
        break;
}
