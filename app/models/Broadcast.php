<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * Broadcast — a tenant's bulk WhatsApp message + its queued recipients.
 * Recipients are drawn from customers seen (inbound) within the 24h window,
 * so a free-form send is allowed by the WhatsApp Cloud API.
 */
class Broadcast extends BaseModel
{
    public const WINDOW_HOURS = 24;
    public const BATCH_SIZE = 50;

    /** Distinct customer phones for a tenant seen within the window. */
    public static function eligibleRecipients(int $tenantId, int $windowHours = self::WINDOW_HOURS): array
    {
        $stmt = self::db()->prepare(
            "SELECT DISTINCT customer_phone FROM bot_messages
             WHERE tenant_id = ? AND direction = 'in'
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)"
        );
        $stmt->execute([$tenantId, $windowHours]);

        return array_column($stmt->fetchAll(), 'customer_phone');
    }

    /** Create a queued broadcast + its recipient rows. Returns [id, count]. */
    public static function create(int $tenantId, string $message): array
    {
        $recipients = self::eligibleRecipients($tenantId);
        $db = self::db();

        $db->prepare("INSERT INTO broadcasts (tenant_id, message, status, total) VALUES (?, ?, 'queued', ?)")
            ->execute([$tenantId, $message, count($recipients)]);
        $id = (int) $db->lastInsertId();

        if ($recipients) {
            $ins = $db->prepare('INSERT INTO broadcast_recipients (broadcast_id, customer_phone) VALUES (?, ?)');
            foreach ($recipients as $phone) {
                $ins->execute([$id, $phone]);
            }
        }

        return ['id' => $id, 'count' => count($recipients)];
    }

    public static function forTenant(int $tenantId, int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));
        $stmt = self::db()->prepare(
            "SELECT * FROM broadcasts WHERE tenant_id = ? ORDER BY created_at DESC LIMIT {$limit}"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll();
    }

    /** Queued broadcasts across all tenants (for the cron). */
    public static function pending(int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));
        $stmt = self::db()->query(
            "SELECT * FROM broadcasts WHERE status IN ('queued','sending') ORDER BY created_at ASC LIMIT {$limit}"
        );

        return $stmt->fetchAll();
    }

    /** Next batch of pending recipients for a broadcast. */
    public static function nextBatch(int $broadcastId, int $size = self::BATCH_SIZE): array
    {
        $size = max(1, min($size, 200));
        $stmt = self::db()->prepare(
            "SELECT * FROM broadcast_recipients WHERE broadcast_id = ? AND status = 'pending' LIMIT {$size}"
        );
        $stmt->execute([$broadcastId]);

        return $stmt->fetchAll();
    }

    public static function markRecipient(int $recipientId, string $status): void
    {
        self::db()->prepare('UPDATE broadcast_recipients SET status = ? WHERE id = ?')
            ->execute([$status, $recipientId]);
    }

    public static function setStatus(int $id, string $status): void
    {
        self::db()->prepare('UPDATE broadcasts SET status = ? WHERE id = ?')->execute([$status, $id]);
    }

    public static function bumpCounts(int $id, int $sent, int $failed): void
    {
        self::db()->prepare('UPDATE broadcasts SET sent = sent + ?, failed = failed + ? WHERE id = ?')
            ->execute([$sent, $failed, $id]);
    }

    /** Remaining pending recipients for a broadcast. */
    public static function pendingCount(int $broadcastId): int
    {
        $stmt = self::db()->prepare("SELECT COUNT(*) FROM broadcast_recipients WHERE broadcast_id = ? AND status = 'pending'");
        $stmt->execute([$broadcastId]);

        return (int) $stmt->fetchColumn();
    }
}
