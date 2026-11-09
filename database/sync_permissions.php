<?php
require_once __DIR__ . '/../app/config/config.php';

use App\Config\Database;
use App\Models\Permission;
use App\Models\Role;

echo "Syncing permissions for MySQL and SQLite...\n";

function syncDb($db, $name, $isMysql = true) {
    if (!$db) return;
    echo "Processing $name...\n";
    
    $roles = (new Role())->all();
    $permissions = Permission::getAvailablePermissions();
    
    foreach ($roles as $r) {
        $roleId = (int)$r['id'];
        echo "Role: {$r['name']} (ID: $roleId)\n";
        
        foreach ($permissions as $category => $items) {
            foreach ($items as $permKey => $permMeta) {
                // Grant all staff & code management tabs by default
                $isGranted = 1;
                if (str_starts_with($category, '7. Admin Console') && !in_array($r['name'], ['super_admin', 'admin', 'code_admin'])) {
                    $isGranted = 0;
                }
                
                if ($isMysql) {
                    $stmt = $db->prepare("
                        INSERT INTO role_permissions (role_id, permission_key, is_granted)
                        VALUES (:role_id, :permission_key, :is_granted)
                        ON DUPLICATE KEY UPDATE is_granted = VALUES(is_granted)
                    ");
                    $stmt->execute([
                        ':role_id' => $roleId,
                        ':permission_key' => $permKey,
                        ':is_granted' => $isGranted
                    ]);
                } else {
                    $stmtLite = $db->prepare("
                        INSERT INTO role_permissions (role_id, permission_key, is_granted)
                        VALUES (:role_id, :permission_key, :is_granted)
                        ON CONFLICT(role_id, permission_key) DO UPDATE SET is_granted = :is_granted2
                    ");
                    $stmtLite->execute([
                        ':role_id' => $roleId,
                        ':permission_key' => $permKey,
                        ':is_granted' => $isGranted,
                        ':is_granted2' => $isGranted
                    ]);
                }
            }
        }
    }
    
    // Check if user_permissions table exists
    try {
        $users = $db->query("SELECT id, role_id FROM users")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as $u) {
            $userId = (int)$u['id'];
            foreach (['tab-user-code-roles', 'tab-user-code-locations', 'tab-user-code-departments'] as $cKey) {
                if ($isMysql) {
                    $db->exec("
                        INSERT INTO user_permissions (user_id, permission_key, is_granted)
                        VALUES ($userId, '$cKey', 1)
                        ON DUPLICATE KEY UPDATE is_granted = 1
                    ");
                } else {
                    $db->exec("
                        INSERT INTO user_permissions (user_id, permission_key, is_granted)
                        VALUES ($userId, '$cKey', 1)
                        ON CONFLICT(user_id, permission_key) DO UPDATE SET is_granted = 1
                    ");
                }
            }
        }
    } catch (\Exception $ex) {
        echo "user_permissions skipped or not present in $name: " . $ex->getMessage() . "\n";
    }
    echo "Done for $name.\n";
}

$mysqlDb = Database::getConnection();
syncDb($mysqlDb, 'MySQL', true);

// Sync SQLite as well if exists
$sqlitePath = dirname(__DIR__) . '/database/fifo_system.db';
if (file_exists($sqlitePath)) {
    $sqliteDb = new PDO("sqlite:" . $sqlitePath);
    $sqliteDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    syncDb($sqliteDb, 'SQLite', false);
}

echo "All permissions successfully synced!\n";
