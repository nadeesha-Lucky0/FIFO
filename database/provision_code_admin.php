<?php
require_once dirname(__DIR__) . '/app/config/config.php';

use App\Config\Database;

try {
    $db = Database::getConnection();
    echo "Connected to database.\n";

    // 1. Check or insert 'code_admin' role
    $stmt = $db->prepare("SELECT * FROM roles WHERE name = 'code_admin'");
    $stmt->execute();
    $role = $stmt->fetch();

    if (!$role) {
        $insertRole = $db->prepare("
            INSERT INTO roles (name, display_name, description, location_id, department_id)
            VALUES ('code_admin', 'Code Administrator', 'Administrator dedicated exclusively to location, department, and system code management', NULL, NULL)
        ");
        $insertRole->execute();
        $roleId = (int)$db->lastInsertId();
        echo "Created 'code_admin' role with ID: {$roleId}\n";
    } else {
        $roleId = (int)$role['id'];
        echo "Found existing 'code_admin' role with ID: {$roleId}\n";
    }

    // 2. Check or create 'admin01' user
    $stmtUser = $db->prepare("SELECT * FROM users WHERE username = 'admin01'");
    $stmtUser->execute();
    $user = $stmtUser->fetch();

    $passwordHash = password_hash('admin01', PASSWORD_DEFAULT);

    if (!$user) {
        $insertUser = $db->prepare("
            INSERT INTO users (role_id, username, email, password_hash, full_name, department, phone, avatar_color, status)
            VALUES (:role_id, 'admin01', 'admin01@egl.lk', :pwd, 'Code Admin (admin01)', 'Code Management', '0112345678', '#0284c7', 'active')
        ");
        $insertUser->execute([
            ':role_id' => $roleId,
            ':pwd' => $passwordHash
        ]);
        $userId = (int)$db->lastInsertId();
        echo "Created 'admin01' user with ID: {$userId}\n";
    } else {
        // Update password and role to ensure it is admin01 and code_admin
        $updateUser = $db->prepare("
            UPDATE users 
            SET role_id = :role_id, password_hash = :pwd, department = 'Code Management', status = 'active'
            WHERE username = 'admin01'
        ");
        $updateUser->execute([
            ':role_id' => $roleId,
            ':pwd' => $passwordHash
        ]);
        echo "Updated existing 'admin01' user (ID: {$user['id']}) with new password and role.\n";
    }

    // 3. Grant default code management permissions to role_permissions for code_admin if table exists
    $allPerms = \App\Models\Permission::getAvailablePermissions();
    foreach ($allPerms as $cat => $items) {
        foreach ($items as $permKey => $permMeta) {
            $isGranted = 1;
            // Admin management tabs like staff tabs vs admin tabs
            $permStmt = $db->prepare("
                INSERT INTO role_permissions (role_id, permission_key, is_granted)
                VALUES (:role_id, :permission_key, :is_granted)
                ON DUPLICATE KEY UPDATE is_granted = VALUES(is_granted)
            ");
            $permStmt->execute([
                ':role_id' => $roleId,
                ':permission_key' => $permKey,
                ':is_granted' => $isGranted
            ]);
        }
    }
    // 4. Backfill Role Codes if empty
    $roleModel = new \App\Models\Role();
    $roleCodes = [
        'super_admin' => 'SA',
        'code_admin' => 'CA',
        'admin' => 'ADM',
        'sk' => 'SK',
        'cc' => 'CC',
        'ce' => 'CE',
        'inventory_manager' => 'IM',
        'operator' => 'OPR',
        'viewer' => 'AUD'
    ];
    foreach ($roleCodes as $rName => $rCode) {
        $db->exec("UPDATE roles SET code = '{$rCode}' WHERE name = '{$rName}' AND (code IS NULL OR code = '')");
    }
    echo "Role codes assigned and backfilled.\n";

    echo "MySQL provisioning complete!\n";

    // Also sync SQLite if sqlite file exists
    $sqliteFile = defined('SQLITE_FILE') ? SQLITE_FILE : dirname(__DIR__) . '/database/fifo_system.db';
    if (file_exists($sqliteFile)) {
        $sqlite = new PDO('sqlite:' . $sqliteFile);
        $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sqlite->exec("
            INSERT OR IGNORE INTO roles (id, name, display_name, description)
            VALUES (8, 'code_admin', 'Code Administrator', 'Administrator dedicated exclusively to location, department, and system code management')
        ");
        $sqliteStmt = $sqlite->prepare("
            INSERT OR REPLACE INTO users (id, role_id, username, email, password_hash, full_name, department, phone, avatar_color, status)
            VALUES (8, 8, 'admin01', 'admin01@egl.lk', :pwd, 'Code Admin (admin01)', 'Code Management', '0112345678', '#0284c7', 'active')
        ");
        $sqliteStmt->execute([':pwd' => $passwordHash]);
        echo "SQLite database synced!\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
