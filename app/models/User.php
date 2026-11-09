<?php
namespace App\Models;

use App\Config\Database;
use PDO;
use PDOException;

class User {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->ensureTableSchema();
    }

    private function ensureTableSchema(): void {
        try {
            $isMysql = (defined('DB_DRIVER') && DB_DRIVER === 'mysql');
            if ($isMysql) {
                $cols = $this->db->query("SHOW COLUMNS FROM `users` LIKE 'location_id'")->fetchAll();
                if (empty($cols)) {
                    $this->db->exec("ALTER TABLE `users` ADD COLUMN `location_id` INT DEFAULT NULL;");
                }
            } else {
                $cols = $this->db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
                if (!in_array('location_id', $cols)) {
                    $this->db->exec("ALTER TABLE users ADD COLUMN location_id INTEGER DEFAULT NULL;");
                }
            }
        } catch (\Exception $e) {
            // Column may already exist
        }
    }

    public function findByIdentifier(string $identifier): ?array {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name as role_name, r.display_name as role_display_name,
                   l.name as location_name, l.code as location_code
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN locations l ON u.location_id = l.id
            WHERE u.email = :email OR u.username = :username
            LIMIT 1
        ");
        $stmt->execute([
            ':email' => $identifier,
            ':username' => $identifier
        ]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name as role_name, r.display_name as role_display_name,
                   l.name as location_name, l.code as location_code
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN locations l ON u.location_id = l.id
            WHERE u.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function getAll(array $filters = []): array {
        $sql = "
            SELECT u.id, u.role_id, u.location_id, u.username, u.email, u.full_name, u.department, 
                   u.phone, u.avatar_color, u.status, u.last_login_at, u.created_at,
                   r.name as role_name, r.display_name as role_display_name,
                   COALESCE(lu.name, lr.name) as location_name,
                   COALESCE(lu.code, lr.code) as location_code
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN locations lu ON u.location_id = lu.id
            LEFT JOIN locations lr ON r.location_id = lr.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['role'])) {
            $sql .= " AND (r.name = :role OR r.id = :role_id)";
            $params[':role'] = $filters['role'];
            $params[':role_id'] = is_numeric($filters['role']) ? (int)$filters['role'] : 0;
        }

        if (!empty($filters['status'])) {
            $sql .= " AND u.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $sql .= " AND (u.full_name LIKE :search1 OR u.email LIKE :search2 OR u.username LIKE :search3 OR u.department LIKE :search4)";
            $params[':search1'] = $search;
            $params[':search2'] = $search;
            $params[':search3'] = $search;
            $params[':search4'] = $search;
        }

        $sql .= " ORDER BY u.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(array $data): int {
        $colors = ['#4f46e5', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#3b82f6'];
        $avatarColor = $data['avatar_color'] ?? $colors[array_rand($colors)];

        $stmt = $this->db->prepare("
            INSERT INTO users (role_id, location_id, username, email, password_hash, full_name, department, phone, avatar_color, status)
            VALUES (:role_id, :location_id, :username, :email, :password_hash, :full_name, :department, :phone, :avatar_color, :status)
        ");

        $stmt->execute([
            ':role_id' => $data['role_id'],
            ':location_id' => !empty($data['location_id']) ? (int)$data['location_id'] : null,
            ':username' => strtolower(trim($data['username'])),
            ':email' => strtolower(trim($data['email'])),
            ':password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            ':full_name' => trim($data['full_name']),
            ':department' => $data['department'] ?? 'General',
            ':phone' => $data['phone'] ?? null,
            ':avatar_color' => $avatarColor,
            ':status' => $data['status'] ?? 'active'
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['full_name'])) {
            $fields[] = "full_name = :full_name";
            $params[':full_name'] = trim($data['full_name']);
        }
        if (isset($data['email'])) {
            $fields[] = "email = :email";
            $params[':email'] = strtolower(trim($data['email']));
        }
        if (isset($data['department'])) {
            $fields[] = "department = :department";
            $params[':department'] = trim($data['department']);
        }
        if (isset($data['location_id'])) {
            $fields[] = "location_id = :location_id";
            $params[':location_id'] = !empty($data['location_id']) ? (int)$data['location_id'] : null;
        }
        if (isset($data['phone'])) {
            $fields[] = "phone = :phone";
            $params[':phone'] = trim($data['phone']);
        }
        if (isset($data['role_id'])) {
            $fields[] = "role_id = :role_id";
            $params[':role_id'] = (int)$data['role_id'];
        }
        if (isset($data['status'])) {
            $fields[] = "status = :status";
            $params[':status'] = $data['status'];
        }
        if (isset($data['avatar_color'])) {
            $fields[] = "avatar_color = :avatar_color";
            $params[':avatar_color'] = $data['avatar_color'];
        }

        if (empty($fields)) {
            return true;
        }

        $fields[] = "updated_at = CURRENT_TIMESTAMP";

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function updatePassword(int $id, string $newPassword): bool {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        return $stmt->execute([
            ':id' => $id,
            ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)
        ]);
    }

    public function updateLastLogin(int $id): bool {
        $stmt = $this->db->prepare("
            UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id
        ");
        return $stmt->execute([':id' => $id]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function isEmailTaken(string $email, ?int $excludeId = null): bool {
        $sql = "SELECT id FROM users WHERE email = :email";
        $params = [':email' => strtolower(trim($email))];
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }

    public function isUsernameTaken(string $username, ?int $excludeId = null): bool {
        $sql = "SELECT id FROM users WHERE username = :username";
        $params = [':username' => strtolower(trim($username))];
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }

    public function getStats(): array {
        $totalUsers = $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $activeUsers = $this->db->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
        $rolesCount = $this->db->query("SELECT COUNT(DISTINCT role_id) FROM users")->fetchColumn();
        $batchesCount = 0;
        try {
            $batchesCount = $this->db->query("SELECT COUNT(*) FROM fifo_batches")->fetchColumn();
        } catch (PDOException $e) {
            $batchesCount = 0;
        }

        return [
            'total_users' => (int)$totalUsers,
            'active_users' => (int)$activeUsers,
            'roles_count' => (int)$rolesCount,
            'batches_count' => (int)$batchesCount
        ];
    }
}
