<?php
namespace App\Models;

use App\Config\Database;
use PDO;
use PDOException;

class Role {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
        try {
            $this->ensureTableExists();
        } catch (\Throwable $e) {
            error_log("Role ensureTableExists notice: " . $e->getMessage());
        }
    }

    private function ensureTableExists(): void {
        try {
            $check = $this->db->query("SELECT 1 FROM `roles` LIMIT 1");
            if ($check !== false) {
                return;
            }
        } catch (\Throwable $e) {
            // Table doesn't exist yet, proceed with creation
        }

        $isMysql = (defined('DB_DRIVER') && DB_DRIVER === 'mysql');
        $pkType = $isMysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS roles (
                id $pkType,
                name VARCHAR(50) NOT NULL UNIQUE,
                code VARCHAR(50) DEFAULT NULL,
                display_name VARCHAR(100) NOT NULL,
                description TEXT DEFAULT NULL,
                location_id INT DEFAULT NULL,
                department_id INT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Add columns if missing in existing table
        try {
            if ($isMysql) {
                $colsCode = $this->db->query("SHOW COLUMNS FROM `roles` LIKE 'code'")->fetchAll();
                if (empty($colsCode)) {
                    $this->db->exec("ALTER TABLE `roles` ADD COLUMN `code` VARCHAR(50) DEFAULT NULL AFTER `name`;");
                }
                $cols = $this->db->query("SHOW COLUMNS FROM `roles` LIKE 'location_id'")->fetchAll();
                if (empty($cols)) {
                    $this->db->exec("ALTER TABLE `roles` ADD COLUMN `location_id` INT DEFAULT NULL;");
                }
                $cols2 = $this->db->query("SHOW COLUMNS FROM `roles` LIKE 'department_id'")->fetchAll();
                if (empty($cols2)) {
                    $this->db->exec("ALTER TABLE `roles` ADD COLUMN `department_id` INT DEFAULT NULL;");
                }
            } else {
                $cols = $this->db->query("PRAGMA table_info(roles)")->fetchAll(PDO::FETCH_COLUMN, 1);
                if (!in_array('code', $cols)) {
                    $this->db->exec("ALTER TABLE roles ADD COLUMN code TEXT DEFAULT NULL;");
                }
                if (!in_array('location_id', $cols)) {
                    $this->db->exec("ALTER TABLE roles ADD COLUMN location_id INTEGER DEFAULT NULL;");
                }
                if (!in_array('department_id', $cols)) {
                    $this->db->exec("ALTER TABLE roles ADD COLUMN department_id INTEGER DEFAULT NULL;");
                }
            }
        } catch (\Exception $e) {
            // columns already present
        }

        // Seed default system roles if empty
        $count = (int)$this->db->query("SELECT COUNT(*) FROM roles")->fetchColumn();
        if ($count === 0) {
            $defaults = [
                ['name' => 'super_admin', 'code' => 'SA', 'display_name' => 'Super Administrator', 'description' => 'Full unrestricted control over company settings, users, and audit logs'],
                ['name' => 'code_admin', 'code' => 'CA', 'display_name' => 'Code Administrator', 'description' => 'Administrator dedicated exclusively to location, department, and system code management'],
                ['name' => 'admin', 'code' => 'ADM', 'display_name' => 'System Administrator', 'description' => 'Manages users, departments, and system operations'],
                ['name' => 'inventory_manager', 'code' => 'IM', 'display_name' => 'Inventory Manager', 'description' => 'Manages FIFO batches, stock-in, stock-out queues, and supplier logs'],
                ['name' => 'operator', 'code' => 'OPR', 'display_name' => 'FIFO Operator / Staff', 'description' => 'Executes stock picking according to FIFO queue rules and updates counts'],
                ['name' => 'viewer', 'code' => 'AUD', 'display_name' => 'Auditor / Viewer', 'description' => 'Read-only access to inventory reports and batch timestamps']
            ];

            $stmt = $this->db->prepare("INSERT INTO roles (name, code, display_name, description) VALUES (:name, :code, :display_name, :description)");
            foreach ($defaults as $def) {
                $stmt->execute([
                    ':name' => $def['name'],
                    ':code' => $def['code'],
                    ':display_name' => $def['display_name'],
                    ':description' => $def['description']
                ]);
            }
        }
    }

    public function all(?int $locationId = null, ?int $departmentId = null): array {
        $sql = "
            SELECT r.*, 
                   l.name as location_name,
                   l.code as location_code,
                   d.name as department_name,
                   d.code as department_code,
                   (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) as user_count
            FROM roles r
            LEFT JOIN locations l ON r.location_id = l.id
            LEFT JOIN departments d ON r.department_id = d.id
            WHERE 1=1
        ";
        $params = [];

        if ($locationId !== null && $locationId > 0) {
            $sql .= " AND (r.location_id = :location_id OR r.location_id IS NULL)";
            $params[':location_id'] = $locationId;
        }

        if ($departmentId !== null && $departmentId > 0) {
            $sql .= " AND (r.department_id = :department_id OR r.department_id IS NULL)";
            $params[':department_id'] = $departmentId;
        }

        $sql .= " ORDER BY (r.location_id IS NULL), r.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT r.*, 
                   l.name as location_name,
                   d.name as department_name,
                   (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) as user_count 
            FROM roles r 
            LEFT JOIN locations l ON r.location_id = l.id
            LEFT JOIN departments d ON r.department_id = d.id
            WHERE r.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $role = $stmt->fetch();
        return $role ?: null;
    }

    public function findByName(string $name): ?array {
        $stmt = $this->db->prepare("SELECT * FROM roles WHERE name = :name");
        $stmt->execute([':name' => $name]);
        $role = $stmt->fetch();
        return $role ?: null;
    }

    public function isNameTaken(string $name, ?int $excludeId = null): bool {
        $sql = "SELECT id FROM roles WHERE LOWER(name) = LOWER(:name)";
        $params = [':name' => trim($name)];
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }

    public function create(array $data): int {
        $name = strtolower(preg_replace('/[^a-z0-9_]/', '_', trim($data['name'] ?? '')));
        $code = strtoupper(trim($data['code'] ?? ''));
        if (empty($code)) {
            $code = strtoupper($name);
        }
        $displayName = trim($data['display_name'] ?? '');
        $description = trim($data['description'] ?? '');
        $locationId = !empty($data['location_id']) ? (int)$data['location_id'] : null;
        $departmentId = !empty($data['department_id']) ? (int)$data['department_id'] : null;

        $stmt = $this->db->prepare("
            INSERT INTO roles (name, code, display_name, description, location_id, department_id)
            VALUES (:name, :code, :display_name, :description, :location_id, :department_id)
        ");

        $stmt->execute([
            ':name' => $name,
            ':code' => $code,
            ':display_name' => $displayName,
            ':description' => $description,
            ':location_id' => $locationId,
            ':department_id' => $departmentId
        ]);

        $roleId = (int)$this->db->lastInsertId();

        // Initialize default permissions for new role
        if ($roleId > 0) {
            $permModel = new Permission();
            $permissions = Permission::getAvailablePermissions();
            foreach ($permissions as $cat => $perms) {
                foreach ($perms as $permKey => $meta) {
                    $isGranted = in_array($permKey, ['locations.view', 'departments.view']);
                    $permModel->setPermission($roleId, $permKey, $isGranted);
                }
            }
        }

        return $roleId;
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['display_name'])) {
            $fields[] = "display_name = :display_name";
            $params[':display_name'] = trim($data['display_name']);
        }
        if (isset($data['code'])) {
            $fields[] = "code = :code";
            $params[':code'] = strtoupper(trim($data['code']));
        }
        if (isset($data['description'])) {
            $fields[] = "description = :description";
            $params[':description'] = trim($data['description']);
        }
        if (array_key_exists('location_id', $data)) {
            $fields[] = "location_id = :location_id";
            $params[':location_id'] = !empty($data['location_id']) ? (int)$data['location_id'] : null;
        }
        if (array_key_exists('department_id', $data)) {
            $fields[] = "department_id = :department_id";
            $params[':department_id'] = !empty($data['department_id']) ? (int)$data['department_id'] : null;
        }

        if (empty($fields)) {
            return true;
        }

        $sql = "UPDATE roles SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool {
        // Protect super_admin default role
        if ($id === 1) {
            return false;
        }

        // Check if users are assigned to this role
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE role_id = :id");
        $stmt->execute([':id' => $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            return false;
        }

        $stmt = $this->db->prepare("DELETE FROM roles WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
