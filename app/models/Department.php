<?php
namespace App\Models;

use App\Config\Database;
use PDO;
use PDOException;

class Department {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
        try {
            $this->ensureTableExists();
        } catch (\Throwable $e) {
            error_log("Department ensureTableExists notice: " . $e->getMessage());
        }
    }

    private function ensureTableExists(): void {
        try {
            $check = $this->db->query("SELECT 1 FROM `departments` LIMIT 1");
            if ($check !== false) {
                return;
            }
        } catch (\Throwable $e) {
            // Table doesn't exist yet, proceed with creation
        }

        // Ensure locations table exists first
        try {
            (new Location());
        } catch (\Throwable $e) {
            // Ignore location pre-check error
        }

        $isMysql = (defined('DB_DRIVER') && DB_DRIVER === 'mysql');
        $pkType = $isMysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $locFk = $isMysql 
            ? 'FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL'
            : 'FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL';

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS departments (
                id $pkType,
                location_id INT DEFAULT 1,
                name VARCHAR(100) NOT NULL,
                code VARCHAR(30) NOT NULL,
                description TEXT DEFAULT NULL,
                head_of_department VARCHAR(100) DEFAULT NULL,
                status VARCHAR(20) DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                $locFk
            );
        ");

        // Automatically drop unique index on department code or name if they exist
        try {
            if ($isMysql) {
                $codeIndexes = $this->db->query("SHOW INDEX FROM `departments` WHERE Key_name = 'code' AND Non_unique = 0")->fetchAll();
                if (!empty($codeIndexes)) {
                    $this->db->exec("ALTER TABLE `departments` DROP INDEX `code`");
                }
                $nameIndexes = $this->db->query("SHOW INDEX FROM `departments` WHERE Key_name = 'name' AND Non_unique = 0")->fetchAll();
                if (!empty($nameIndexes)) {
                    $this->db->exec("ALTER TABLE `departments` DROP INDEX `name`");
                }
            }
        } catch (\Exception $e) {
            // Index may already be dropped
        }

        // Check if location_id column exists
        try {
            if ($isMysql) {
                $cols = $this->db->query("SHOW COLUMNS FROM `departments` LIKE 'location_id'")->fetchAll();
                if (empty($cols)) {
                    $this->db->exec("ALTER TABLE `departments` ADD COLUMN `location_id` INT DEFAULT 1;");
                }
            } else {
                $cols = $this->db->query("PRAGMA table_info(departments)")->fetchAll(PDO::FETCH_COLUMN, 1);
                if (!in_array('location_id', $cols)) {
                    $this->db->exec("ALTER TABLE departments ADD COLUMN location_id INTEGER DEFAULT 1;");
                }
            }
        } catch (\Exception $e) {
            // Column may already exist
        }
    }

    public function getAll(?int $locationId = null): array {
        $sql = "
            SELECT d.*, 
                   l.name as location_name,
                   l.code as location_code,
                   l.type as location_type,
                   (SELECT COUNT(*) FROM users u WHERE u.department = d.name) as user_count
            FROM departments d
            LEFT JOIN locations l ON d.location_id = l.id
            WHERE 1=1
        ";
        $params = [];

        if ($locationId !== null && $locationId > 0) {
            $sql .= " AND d.location_id = :location_id";
            $params[':location_id'] = $locationId;
        }

        $sql .= " ORDER BY (l.name IS NULL), CAST(l.code AS UNSIGNED) ASC, l.code ASC, CAST(d.code AS UNSIGNED) ASC, d.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getAllActive(): array {
        $stmt = $this->db->query("
            SELECT d.name 
            FROM departments d
            WHERE d.status = 'active' 
            ORDER BY d.name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    public function getAllGroupedByLocation(): array {
        $stmt = $this->db->query("
            SELECT d.id, d.name, d.code, d.location_id, 
                   COALESCE(l.name, 'General Headquarters') as location_name,
                   l.type as location_type
            FROM departments d
            LEFT JOIN locations l ON d.location_id = l.id
            WHERE d.status = 'active'
            ORDER BY location_name ASC, d.name ASC
        ");
        $results = $stmt->fetchAll();

        $grouped = [];
        foreach ($results as $row) {
            $loc = $row['location_name'];
            if (!isset($grouped[$loc])) {
                $grouped[$loc] = [];
            }
            $grouped[$loc][] = $row;
        }
        return $grouped;
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT d.*, l.name as location_name 
            FROM departments d
            LEFT JOIN locations l ON d.location_id = l.id
            WHERE d.id = :id 
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO departments (location_id, name, code, description, head_of_department, status)
            VALUES (:location_id, :name, :code, :description, :head_of_department, :status)
        ");

        $stmt->execute([
            ':location_id' => !empty($data['location_id']) ? (int)$data['location_id'] : 1,
            ':name' => trim($data['name']),
            ':code' => strtoupper(trim($data['code'])),
            ':description' => trim($data['description'] ?? ''),
            ':head_of_department' => trim($data['head_of_department'] ?? ''),
            ':status' => $data['status'] ?? 'active'
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['location_id'])) {
            $fields[] = "location_id = :location_id";
            $params[':location_id'] = (int)$data['location_id'];
        }
        if (isset($data['name'])) {
            $fields[] = "name = :name";
            $params[':name'] = trim($data['name']);
        }
        if (isset($data['code'])) {
            $fields[] = "code = :code";
            $params[':code'] = strtoupper(trim($data['code']));
        }
        if (isset($data['description'])) {
            $fields[] = "description = :description";
            $params[':description'] = trim($data['description']);
        }
        if (isset($data['head_of_department'])) {
            $fields[] = "head_of_department = :head_of_department";
            $params[':head_of_department'] = trim($data['head_of_department']);
        }
        if (isset($data['status'])) {
            $fields[] = "status = :status";
            $params[':status'] = $data['status'];
        }

        if (empty($fields)) {
            return true;
        }

        $sql = "UPDATE departments SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM departments WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function isNameTaken(string $name, ?int $excludeId = null, ?int $locationId = null): bool {
        $sql = "SELECT id FROM departments WHERE LOWER(name) = LOWER(:name)";
        $params = [':name' => trim($name)];
        if ($locationId !== null && $locationId > 0) {
            $sql .= " AND location_id = :location_id";
            $params[':location_id'] = $locationId;
        }
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }

    public function isCodeTaken(string $code, ?int $excludeId = null, ?int $locationId = null): bool {
        $sql = "SELECT id FROM departments WHERE LOWER(code) = LOWER(:code)";
        $params = [':code' => trim($code)];
        if ($locationId !== null && $locationId > 0) {
            $sql .= " AND location_id = :location_id";
            $params[':location_id'] = $locationId;
        }
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }
}
