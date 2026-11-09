<?php
namespace App\Core;

use App\Config\Database;
use PDOException;

class Logger {
    public static function log(string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null, ?int $userId = null): bool {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address)
                VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip_address)
            ");

            $currentUserId = $userId ?? Session::get('user_id');
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            return $stmt->execute([
                ':user_id' => $currentUserId,
                ':action' => $action,
                ':entity_type' => $entityType,
                ':entity_id' => $entityId,
                ':details' => $details,
                ':ip_address' => $ipAddress
            ]);
        } catch (PDOException $e) {
            error_log("Logging Error: " . $e->getMessage());
            return false;
        }
    }
}
