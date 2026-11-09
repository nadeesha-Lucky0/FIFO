<?php
namespace App\Models;

use App\Config\Database;
use PDO;
use PDOException;

class Permission {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->ensureTable();
    }

    private function ensureTable(): void {
        $isMysql = (defined('DB_DRIVER') && DB_DRIVER === 'mysql');
        $pkType = $isMysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $roleFk = $isMysql
            ? 'FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE'
            : 'FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE';
        $userFk = $isMysql
            ? 'FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE'
            : 'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE';

        // Role Permissions Table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS role_permissions (
                id $pkType,
                role_id INT NOT NULL,
                permission_key VARCHAR(100) NOT NULL,
                is_granted TINYINT DEFAULT 1,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(role_id, permission_key),
                $roleFk
            )
        ");

        // User Custom Permissions Table (for individual user overrides)
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS user_permissions (
                id $pkType,
                user_id INT NOT NULL,
                permission_key VARCHAR(100) NOT NULL,
                is_granted TINYINT DEFAULT 1,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, permission_key),
                $userFk
            )
        ");

        $this->seedDefaults();
    }

    public static function getAvailablePermissions(): array {
        return [
            '1. Home Tab' => [
                'tab-user-home' => [
                    'label' => 'Home Dashboard & Overview',
                    'desc' => 'Cumulative Monthly Transaction Value Graph & Daily Activity',
                    'path' => 'Home',
                    'level' => 1
                ],
            ],
            '2. Inventory - FIFO → Transaction → Receipts' => [
                'tab-user-receipts-entry' => [
                    'label' => 'Receipts: Entry',
                    'desc' => 'Transaction > Receipts > Entry screen',
                    'path' => 'Inventory - FIFO > Transaction > Receipts > Entry',
                    'level' => 3
                ],
                'tab-user-receipts-validation' => [
                    'label' => 'Receipts: Validation',
                    'desc' => 'Transaction > Receipts > Validation screen',
                    'path' => 'Inventory - FIFO > Transaction > Receipts > Validation',
                    'level' => 3
                ],
                'tab-user-receipts-approval' => [
                    'label' => 'Receipts: Approval',
                    'desc' => 'Transaction > Receipts > Approval screen',
                    'path' => 'Inventory - FIFO > Transaction > Receipts > Approval',
                    'level' => 3
                ],
                'tab-user-receipts-edit' => [
                    'label' => 'Receipts: Unposted Document Edit',
                    'desc' => 'Transaction > Receipts > Unposted Document Edit',
                    'path' => 'Inventory - FIFO > Transaction > Receipts > Unposted Document Edit',
                    'level' => 3
                ],
                'tab-user-receipts-posted-cancel' => [
                    'label' => 'Receipts: Posted Cancellation',
                    'desc' => 'Transaction > Receipts > Posted Cancellation',
                    'path' => 'Inventory - FIFO > Transaction > Receipts > Posted Cancellation',
                    'level' => 3
                ],
                'tab-user-receipts-unposted-cancel' => [
                    'label' => 'Receipts: Unposted Cancellation',
                    'desc' => 'Transaction > Receipts > Unposted Cancellation',
                    'path' => 'Inventory - FIFO > Transaction > Receipts > Unposted Cancellation',
                    'level' => 3
                ],
            ],
            '2. Inventory - FIFO → Transaction → Issue & Posting' => [
                'tab-user-issue-entry' => [
                    'label' => 'Issue: Entry',
                    'desc' => 'Transaction > Issue > Entry screen',
                    'path' => 'Inventory - FIFO > Transaction > Issue > Entry',
                    'level' => 3
                ],
                'tab-user-issue-edit' => [
                    'label' => 'Issue: Edit',
                    'desc' => 'Transaction > Issue > Edit screen',
                    'path' => 'Inventory - FIFO > Transaction > Issue > Edit',
                    'level' => 3
                ],
                'tab-user-issue-validation' => [
                    'label' => 'Issue: Validation',
                    'desc' => 'Transaction > Issue > Validation screen',
                    'path' => 'Inventory - FIFO > Transaction > Issue > Validation',
                    'level' => 3
                ],
                'tab-user-issue-approval' => [
                    'label' => 'Issue: Approval',
                    'desc' => 'Transaction > Issue > Approval screen',
                    'path' => 'Inventory - FIFO > Transaction > Issue > Approval',
                    'level' => 3
                ],
                'tab-user-issue-posted-cancel' => [
                    'label' => 'Issue: Posted Cancellation',
                    'desc' => 'Transaction > Issue > Posted Cancellation',
                    'path' => 'Inventory - FIFO > Transaction > Issue > Posted Cancellation',
                    'level' => 3
                ],
                'tab-user-issue-unposted-cancel' => [
                    'label' => 'Issue: Unposted Cancellation',
                    'desc' => 'Transaction > Issue > Unposted Cancellation',
                    'path' => 'Inventory - FIFO > Transaction > Issue > Unposted Cancellation',
                    'level' => 3
                ],
                'tab-user-posting' => [
                    'label' => 'Transaction: Posting',
                    'desc' => 'Transaction > Posting screen',
                    'path' => 'Inventory - FIFO > Transaction > Posting',
                    'level' => 2
                ],
            ],
            '2. Inventory - FIFO → Physical Verification' => [
                'tab-user-pv-freeze' => [
                    'label' => 'PV: Freeze Batches',
                    'desc' => 'Physical Verification > Freeze Batches',
                    'path' => 'Inventory - FIFO > Physical Verification > Freeze Batches',
                    'level' => 2
                ],
                'tab-user-pv-generate-sheet' => [
                    'label' => 'PV: Generate Sheet',
                    'desc' => 'Physical Verification > Generate Sheet',
                    'path' => 'Inventory - FIFO > Physical Verification > Generate Sheet',
                    'level' => 2
                ],
                'tab-user-pv-entry' => [
                    'label' => 'PV: Verification Entry',
                    'desc' => 'Physical Verification > Verification Entry',
                    'path' => 'Inventory - FIFO > Physical Verification > Verification Entry',
                    'level' => 2
                ],
                'tab-user-pv-edit' => [
                    'label' => 'PV: Verification Edit',
                    'desc' => 'Physical Verification > Verification Edit',
                    'path' => 'Inventory - FIFO > Physical Verification > Verification Edit',
                    'level' => 2
                ],
                'tab-user-pv-validation' => [
                    'label' => 'PV: Verification Validation',
                    'desc' => 'Physical Verification > Verification Validation',
                    'path' => 'Inventory - FIFO > Physical Verification > Verification Validation',
                    'level' => 2
                ],
                'tab-user-pv-approval' => [
                    'label' => 'PV: Verification Approval',
                    'desc' => 'Physical Verification > Verification Approval',
                    'path' => 'Inventory - FIFO > Physical Verification > Verification Approval',
                    'level' => 2
                ],
                'tab-user-pv-report-avg-consumption' => [
                    'label' => 'PHV Report: Avg Consumption',
                    'desc' => 'Physical Verification > PHV Reports > Avg Consumption',
                    'path' => 'Inventory - FIFO > Physical Verification > PHV Reports > Avg Consumption',
                    'level' => 3
                ],
                'tab-user-pv-report-final' => [
                    'label' => 'PHV Report: Final Report',
                    'desc' => 'Physical Verification > PHV Reports > Final Report',
                    'path' => 'Inventory - FIFO > Physical Verification > PHV Reports > Final Report',
                    'level' => 3
                ],
                'tab-user-pv-report-other' => [
                    'label' => 'PHV Report: Other Reports',
                    'desc' => 'Physical Verification > PHV Reports > Other Reports',
                    'path' => 'Inventory - FIFO > Physical Verification > PHV Reports > Other Reports',
                    'level' => 3
                ],
                'tab-user-pv-report-sheet-summary' => [
                    'label' => 'PHV Report: PHV Sheet Summary',
                    'desc' => 'Physical Verification > PHV Reports > PHV Sheet Summary',
                    'path' => 'Inventory - FIFO > Physical Verification > PHV Reports > PHV Sheet Summary',
                    'level' => 3
                ],
                'tab-user-pv-report-av7-summary' => [
                    'label' => 'PHV Report: AV 7 Summary',
                    'desc' => 'Physical Verification > PHV Reports > AV 7 Summary',
                    'path' => 'Inventory - FIFO > Physical Verification > PHV Reports > AV 7 Summary',
                    'level' => 3
                ],
                'tab-user-pv-posting' => [
                    'label' => 'PV: Verification Posting',
                    'desc' => 'Physical Verification > Verification Posting',
                    'path' => 'Inventory - FIFO > Physical Verification > Verification Posting',
                    'level' => 2
                ],
                'tab-user-pv-gl-posting' => [
                    'label' => 'PV: Verification GL Posting',
                    'desc' => 'Physical Verification > Verification GL Posting',
                    'path' => 'Inventory - FIFO > Physical Verification > Verification GL Posting',
                    'level' => 2
                ],
                'tab-user-pv-unfreeze' => [
                    'label' => 'PV: Unfreeze Batches',
                    'desc' => 'Physical Verification > Unfreeze Batches',
                    'path' => 'Inventory - FIFO > Physical Verification > Unfreeze Batches',
                    'level' => 2
                ],
            ],
            '3. Cash Book' => [
                'tab-user-payslip-cancellation' => [
                    'label' => 'Payslip Cancellation',
                    'desc' => 'Cash Book > Payslip Cancelation screen',
                    'path' => 'Cash Book > Payslip Cancelation',
                    'level' => 2
                ],
            ],
            '4. Reports' => [
                'tab-user-report-qoh-batch' => [
                    'label' => 'QOH: Batch Wise Report',
                    'desc' => 'Reports > Quantity On Hand > Batch wise report',
                    'path' => 'Reports > Quantity On Hand > Batch wise report',
                    'level' => 3
                ],
                'tab-user-report-qoh-material' => [
                    'label' => 'QOH: Material Wise Report',
                    'desc' => 'Reports > Quantity On Hand > Material wise report',
                    'path' => 'Reports > Quantity On Hand > Material wise report',
                    'level' => 3
                ],
                'tab-user-report-monthly-transfers' => [
                    'label' => 'Monthly Transfers Report',
                    'desc' => 'Reports > Monthly Transfers report',
                    'path' => 'Reports > Monthly Transfers',
                    'level' => 2
                ],
                'tab-user-report-avg-diesel' => [
                    'label' => 'Average Diesel Price',
                    'desc' => 'Reports > Average Diesel Price report',
                    'path' => 'Reports > Average Diesel Price',
                    'level' => 2
                ],
                'tab-user-report-cpc-procurement' => [
                    'label' => 'CPC Procurements Report',
                    'desc' => 'Reports > CPC Procurements report',
                    'path' => 'Reports > CPC Procurements report',
                    'level' => 2
                ],
                'tab-user-report-inventory-inquiry' => [
                    'label' => 'Inventory Inquiry Report',
                    'desc' => 'Reports > Inventory Inquiry Report',
                    'path' => 'Reports > Inventory Inquiry Report',
                    'level' => 2
                ],
                'tab-user-report-job-issue' => [
                    'label' => 'Job Wise Material Issue Report',
                    'desc' => 'Reports > Job wise material Issue Report',
                    'path' => 'Reports > Job wise material Issue Report',
                    'level' => 2
                ],
                'tab-user-report-monthly-tx' => [
                    'label' => 'Monthly Transaction Report',
                    'desc' => 'Reports > Monthly Transaction',
                    'path' => 'Reports > Monthly Transaction',
                    'level' => 2
                ],
                'tab-user-report-grn-issue-summary' => [
                    'label' => 'GRN - Issue Summary',
                    'desc' => 'Reports > GRN - Issue Summary',
                    'path' => 'Reports > GRN - Issue Summary',
                    'level' => 2
                ],
                'tab-user-report-material-flow' => [
                    'label' => 'Material Flow Report',
                    'desc' => 'Reports > Material Flow Report',
                    'path' => 'Reports > Material Flow Report',
                    'level' => 2
                ],
            ],
            '5. Authorized Tasks' => [
                'tab-user-task-cost-adjustment' => [
                    'label' => 'Cost Adjustments',
                    'desc' => 'Authorized Tasks > Cost Adjustments',
                    'path' => 'Authorized Tasks > Cost Adjustments',
                    'level' => 2
                ],
                'tab-user-task-qty-correction' => [
                    'label' => 'Allocated Qty Correction',
                    'desc' => 'Authorized Tasks > Allocated Qty Correction',
                    'path' => 'Authorized Tasks > Allocated Qty Correction',
                    'level' => 2
                ],
            ],
            '6. Code Management' => [
                'tab-user-code-roles' => [
                    'label' => 'User Roles & Codes',
                    'desc' => 'Code Management > User Roles > Defined Roles & Code Option',
                    'path' => 'Code Management > User Roles & Codes',
                    'level' => 2
                ],
                'tab-user-code-locations' => [
                    'label' => 'Station / Location Codes',
                    'desc' => 'Code Management > Station Codes',
                    'path' => 'Code Management > Station Codes',
                    'level' => 2
                ],
                'tab-user-code-departments' => [
                    'label' => 'Department Codes',
                    'desc' => 'Code Management > Department Codes',
                    'path' => 'Code Management > Department Codes',
                    'level' => 2
                ],
            ]
        ];
    }

    private function seedDefaults(): void {
        $roles = (new Role())->all();
        $permissions = self::getAvailablePermissions();

        $isMysql = (defined('DB_DRIVER') && DB_DRIVER === 'mysql');
        $insertSql = $isMysql
            ? "INSERT IGNORE INTO role_permissions (role_id, permission_key, is_granted) VALUES (:role_id, :permission_key, :is_granted)"
            : "INSERT OR IGNORE INTO role_permissions (role_id, permission_key, is_granted) VALUES (:role_id, :permission_key, :is_granted)";

        $stmt = $this->db->prepare($insertSql);

        foreach ($roles as $r) {
            $isSuperAdmin = ($r['name'] === 'super_admin');
            $isAdmin = in_array($r['name'], ['super_admin', 'admin', 'code_admin']);

            foreach ($permissions as $category => $items) {
                foreach ($items as $permKey => $permMeta) {
                    $isGranted = 1;
                    // Standard staff tabs are ON by default for everyone
                    // Only Admin management tabs are restricted to admins
                    if (str_starts_with($category, '6. Admin') && !$isAdmin) {
                        $isGranted = 0;
                    }
                    if ($isSuperAdmin) {
                        $isGranted = 1;
                    }

                    $stmt->execute([
                        ':role_id' => $r['id'],
                        ':permission_key' => $permKey,
                        ':is_granted' => $isGranted
                    ]);
                }
            }
        }
    }

    public function getMatrix(): array {
        $stmt = $this->db->query("SELECT role_id, permission_key, is_granted FROM role_permissions");
        $matrix = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $matrix[$row['permission_key']][$row['role_id']] = (int)$row['is_granted'];
        }
        return $matrix;
    }

    public function getUserMatrix(): array {
        $stmt = $this->db->query("SELECT user_id, permission_key, is_granted FROM user_permissions");
        $matrix = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $matrix[$row['permission_key']][$row['user_id']] = (int)$row['is_granted'];
        }
        return $matrix;
    }

    public function setPermission(int $roleId, string $permissionKey, bool $isGranted): bool {
        $isMysql = (defined('DB_DRIVER') && DB_DRIVER === 'mysql');
        $sql = $isMysql
            ? "INSERT INTO role_permissions (role_id, permission_key, is_granted, updated_at)
               VALUES (:role_id, :permission_key, :is_granted, CURRENT_TIMESTAMP)
               ON DUPLICATE KEY UPDATE is_granted = VALUES(is_granted), updated_at = CURRENT_TIMESTAMP"
            : "INSERT INTO role_permissions (role_id, permission_key, is_granted, updated_at)
               VALUES (:role_id, :permission_key, :is_granted, CURRENT_TIMESTAMP)
               ON CONFLICT(role_id, permission_key) DO UPDATE SET
                   is_granted = :is_granted2,
                   updated_at = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        $params = [
            ':role_id' => $roleId,
            ':permission_key' => $permissionKey,
            ':is_granted' => $isGranted ? 1 : 0
        ];
        if (!$isMysql) {
            $params[':is_granted2'] = $isGranted ? 1 : 0;
        }
        return $stmt->execute($params);
    }

    public static function getAllPermissionKeys(): array {
        $keys = [];
        foreach (self::getAvailablePermissions() as $category => $items) {
            foreach ($items as $permKey => $permMeta) {
                $keys[] = $permKey;
            }
        }
        return $keys;
    }

    public function getRolePermissions(int $roleId): array {
        $stmt = $this->db->prepare("SELECT permission_key, is_granted FROM role_permissions WHERE role_id = :role_id");
        $stmt->execute([':role_id' => $roleId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['permission_key']] = (int)$row['is_granted'];
        }
        return $result;
    }

    public function copyRolePermissionsToUser(int $roleId, int $userId): void {
        $isMysql = (defined('DB_DRIVER') && DB_DRIVER === 'mysql');
        $rolePerms = $this->getRolePermissions($roleId);
        $allKeys = self::getAllPermissionKeys();

        $sql = $isMysql
            ? "INSERT INTO user_permissions (user_id, permission_key, is_granted, updated_at)
               VALUES (:user_id, :permission_key, :is_granted, CURRENT_TIMESTAMP)
               ON DUPLICATE KEY UPDATE is_granted = VALUES(is_granted), updated_at = CURRENT_TIMESTAMP"
            : "INSERT INTO user_permissions (user_id, permission_key, is_granted, updated_at)
               VALUES (:user_id, :permission_key, :is_granted, CURRENT_TIMESTAMP)
               ON CONFLICT(user_id, permission_key) DO UPDATE SET is_granted = :is_granted2, updated_at = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        foreach ($allKeys as $permKey) {
            $isGranted = isset($rolePerms[$permKey]) ? (int)$rolePerms[$permKey] : 1;
            $params = [
                ':user_id' => $userId,
                ':permission_key' => $permKey,
                ':is_granted' => $isGranted
            ];
            if (!$isMysql) {
                $params[':is_granted2'] = $isGranted;
            }
            $stmt->execute($params);
        }
    }

    public function grantAllUserPermissions(int $userId): void {
        $isMysql = (defined('DB_DRIVER') && DB_DRIVER === 'mysql');
        $sql = $isMysql
            ? "INSERT INTO user_permissions (user_id, permission_key, is_granted, updated_at)
               VALUES (:user_id, :permission_key, 1, CURRENT_TIMESTAMP)
               ON DUPLICATE KEY UPDATE is_granted = 1, updated_at = CURRENT_TIMESTAMP"
            : "INSERT INTO user_permissions (user_id, permission_key, is_granted, updated_at)
               VALUES (:user_id, :permission_key, 1, CURRENT_TIMESTAMP)
               ON CONFLICT(user_id, permission_key) DO UPDATE SET is_granted = 1, updated_at = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        foreach (self::getAllPermissionKeys() as $permKey) {
            $stmt->execute([
                ':user_id' => $userId,
                ':permission_key' => $permKey
            ]);
        }
    }

    public function setUserPermission(int $userId, string $permissionKey, bool $isGranted): bool {
        $isMysql = (defined('DB_DRIVER') && DB_DRIVER === 'mysql');
        $sql = $isMysql
            ? "INSERT INTO user_permissions (user_id, permission_key, is_granted, updated_at)
               VALUES (:user_id, :permission_key, :is_granted, CURRENT_TIMESTAMP)
               ON DUPLICATE KEY UPDATE is_granted = VALUES(is_granted), updated_at = CURRENT_TIMESTAMP"
            : "INSERT INTO user_permissions (user_id, permission_key, is_granted, updated_at)
               VALUES (:user_id, :permission_key, :is_granted, CURRENT_TIMESTAMP)
               ON CONFLICT(user_id, permission_key) DO UPDATE SET
                   is_granted = :is_granted2,
                   updated_at = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        $params = [
            ':user_id' => $userId,
            ':permission_key' => $permissionKey,
            ':is_granted' => $isGranted ? 1 : 0
        ];
        if (!$isMysql) {
            $params[':is_granted2'] = $isGranted ? 1 : 0;
        }
        return $stmt->execute($params);
    }

    public function hasPermission(int $roleId, string $permissionKey, ?int $userId = null): bool {
        $role = ($roleId > 0) ? (new Role())->findById($roleId) : null;
        if ($role && $role['name'] === 'super_admin') {
            return true;
        }

        // Check user override first if provided
        if ($userId && $userId > 0) {
            $stmtUser = $this->db->prepare("SELECT is_granted FROM user_permissions WHERE user_id = :user_id AND permission_key = :permission_key");
            $stmtUser->execute([':user_id' => $userId, ':permission_key' => $permissionKey]);
            $userVal = $stmtUser->fetchColumn();
            if ($userVal !== false && $userVal !== null) {
                return ((int)$userVal) === 1;
            }
        }

        if ($roleId > 0) {
            $stmt = $this->db->prepare("SELECT is_granted FROM role_permissions WHERE role_id = :role_id AND permission_key = :permission_key");
            $stmt->execute([':role_id' => $roleId, ':permission_key' => $permissionKey]);
            $val = $stmt->fetchColumn();
            if ($val !== false && $val !== null) {
                return ((int)$val) === 1;
            }
        }

        // Default to granted for standard staff tabs, restrict admin tabs to admins
        $isAdminRole = ($role && in_array($role['name'], ['super_admin', 'admin', 'code_admin']));
        if ($permissionKey === 'tab-departments') {
            return (bool)$isAdminRole;
        }
        if (in_array($permissionKey, ['tab-users', 'tab-roles'])) {
            return ($role && in_array($role['name'], ['super_admin', 'admin']));
        }
        return true;
    }
}
