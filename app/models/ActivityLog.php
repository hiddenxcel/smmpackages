<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * ActivityLog — audit trail (tenant | superadmin | system actors).
 */
class ActivityLog extends BaseModel
{
    public static function record(string $actorType, ?int $actorId, string $action, array $details = [], ?string $ip = null): void
    {
        try {
            $stmt = self::db()->prepare(
                'INSERT INTO activity_log (actor_type, actor_id, action, details, ip) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$actorType, $actorId, $action, $details ? json_encode($details) : null, $ip ?? ($_SERVER['REMOTE_ADDR'] ?? null)]);
        } catch (\Throwable $e) {
            error_log('[ActivityLog] ' . $e->getMessage());
        }
    }

    public static function recent(int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));
        $stmt = self::db()->prepare("SELECT * FROM activity_log ORDER BY id DESC LIMIT ?");
        $stmt->execute([$limit]);

        return $stmt->fetchAll();
    }
}
