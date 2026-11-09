<?php
namespace App\Models;

use App\Config\Database;
use PDO;
use PDOException;

class Location {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
        try {
            $this->ensureTableExists();
        } catch (\Throwable $e) {
            error_log("Location ensureTableExists notice: " . $e->getMessage());
        }
    }

    private function ensureTableExists(): void {
        try {
            $check = $this->db->query("SELECT 1 FROM `locations` LIMIT 1");
            if ($check !== false) {
                return;
            }
        } catch (\Throwable $e) {
            // Table doesn't exist yet, proceed with creation
        }

        $isMysql = (defined('DB_DRIVER') && DB_DRIVER === 'mysql');
        $pkType = $isMysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS locations (
                id $pkType,
                name VARCHAR(120) NOT NULL UNIQUE,
                code VARCHAR(30) NOT NULL UNIQUE,
                database_name VARCHAR(100) DEFAULT NULL,
                type VARCHAR(50) DEFAULT 'Hydroelectric',
                capacity_mw VARCHAR(50) DEFAULT NULL,
                province VARCHAR(100) DEFAULT NULL,
                district VARCHAR(100) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                status VARCHAR(20) DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Ensure database_name column exists
        try {
            if ($isMysql) {
                $cols = $this->db->query("SHOW COLUMNS FROM `locations` LIKE 'database_name'")->fetchAll();
                if (empty($cols)) {
                    $this->db->exec("ALTER TABLE `locations` ADD COLUMN `database_name` VARCHAR(100) DEFAULT NULL;");
                }
            } else {
                $cols = $this->db->query("PRAGMA table_info(locations)")->fetchAll(PDO::FETCH_COLUMN, 1);
                if (!in_array('database_name', $cols)) {
                    $this->db->exec("ALTER TABLE locations ADD COLUMN database_name VARCHAR(100) DEFAULT NULL;");
                }
            }
        } catch (\Exception $e) {
            // Column may already exist
        }
    }

    public function getAll(): array {
        $stmt = $this->db->query("
            SELECT l.*,
                   (SELECT COUNT(*) FROM departments d WHERE d.location_id = l.id) as department_count,
                   (SELECT COUNT(*) FROM users u JOIN departments d ON u.department = d.name WHERE d.location_id = l.id) as staff_count
            FROM locations l
            ORDER BY CAST(l.code AS UNSIGNED) ASC, l.code ASC
        ");
        return $stmt->fetchAll();
    }

    public function getAllActive(): array {
        $stmt = $this->db->query("
            SELECT id, name, code, database_name, type, capacity_mw 
            FROM locations 
            WHERE status = 'active' 
            ORDER BY CAST(code AS UNSIGNED) ASC, code ASC
        ");
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM locations WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function findByCode(string $code): ?array {
        $stmt = $this->db->prepare("SELECT * FROM locations WHERE code = :code LIMIT 1");
        $stmt->execute([':code' => trim($code)]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO locations (name, code, database_name, type, capacity_mw, province, district, description, status)
            VALUES (:name, :code, :database_name, :type, :capacity_mw, :province, :district, :description, :status)
        ");

        $stmt->execute([
            ':name' => trim($data['name']),
            ':code' => strtoupper(trim($data['code'])),
            ':database_name' => trim($data['database_name'] ?? ''),
            ':type' => trim($data['type'] ?? 'Hydroelectric'),
            ':capacity_mw' => trim($data['capacity_mw'] ?? ''),
            ':province' => trim($data['province'] ?? ''),
            ':district' => trim($data['district'] ?? ''),
            ':description' => trim($data['description'] ?? ''),
            ':status' => $data['status'] ?? 'active'
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['name'])) {
            $fields[] = "name = :name";
            $params[':name'] = trim($data['name']);
        }
        if (isset($data['code'])) {
            $fields[] = "code = :code";
            $params[':code'] = strtoupper(trim($data['code']));
        }
        if (isset($data['database_name'])) {
            $fields[] = "database_name = :database_name";
            $params[':database_name'] = trim($data['database_name']);
        }
        if (isset($data['type'])) {
            $fields[] = "type = :type";
            $params[':type'] = trim($data['type']);
        }
        if (isset($data['capacity_mw'])) {
            $fields[] = "capacity_mw = :capacity_mw";
            $params[':capacity_mw'] = trim($data['capacity_mw']);
        }
        if (isset($data['province'])) {
            $fields[] = "province = :province";
            $params[':province'] = trim($data['province']);
        }
        if (isset($data['district'])) {
            $fields[] = "district = :district";
            $params[':district'] = trim($data['district']);
        }
        if (isset($data['description'])) {
            $fields[] = "description = :description";
            $params[':description'] = trim($data['description']);
        }
        if (isset($data['status'])) {
            $fields[] = "status = :status";
            $params[':status'] = $data['status'];
        }

        if (empty($fields)) {
            return true;
        }

        $sql = "UPDATE locations SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM locations WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function isNameTaken(string $name, ?int $excludeId = null): bool {
        $sql = "SELECT id FROM locations WHERE LOWER(name) = LOWER(:name)";
        $params = [':name' => trim($name)];
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }

    public function isCodeTaken(string $code, ?int $excludeId = null): bool {
        $sql = "SELECT id FROM locations WHERE LOWER(code) = LOWER(:code)";
        $params = [':code' => trim($code)];
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }
}
