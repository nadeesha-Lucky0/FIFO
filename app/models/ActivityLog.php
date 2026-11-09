<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class ActivityLog {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getRecent(int $limit = 10): array {
        $stmt = $this->db->prepare("
            SELECT a.*, u.full_name as user_name, u.username, u.avatar_color, r.display_name as role_display_name
            FROM activity_logs a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN roles r ON u.role_id = r.id
            ORDER BY a.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function log(int $userId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): bool {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address)
                VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip_address)
            ");
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            return $stmt->execute([
                ':user_id' => $userId,
                ':action' => $action,
                ':entity_type' => $entityType,
                ':entity_id' => $entityId,
                ':details' => $details,
                ':ip_address' => $ipAddress
            ]);
        } catch (\PDOException $e) {
            error_log("ActivityLog error: " . $e->getMessage());
            return false;
        }
    }
}
