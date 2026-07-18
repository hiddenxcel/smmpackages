<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * BotConversation — per (tenant, customer_phone, bot_type) state machine row.
 * context holds flow data as JSON (selected network/service/etc). expires_at
 * lets stale conversations reset. Always scoped by tenant_id.
 */
class BotConversation extends BaseModel
{
    public const TTL_MINUTES = 30;

    public static function get(int $tenantId, string $phone, string $botType): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM bot_conversations WHERE tenant_id = ? AND customer_phone = ? AND bot_type = ?'
        );
        $stmt->execute([$tenantId, $phone, $botType]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        // Expired? treat as fresh (IDLE) by resetting.
        if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) {
            self::reset($tenantId, $phone, $botType);

            return null;
        }

        $row['context'] = $row['context'] ? (json_decode($row['context'], true) ?? []) : [];

        return $row;
    }

    /** Upsert state + context (context replaces existing). Refreshes TTL. */
    public static function set(int $tenantId, string $phone, string $botType, string $state, array $context = []): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + self::TTL_MINUTES * 60);

        $stmt = self::db()->prepare(
            "INSERT INTO bot_conversations (tenant_id, customer_phone, bot_type, state, context, expires_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE state = VALUES(state), context = VALUES(context), expires_at = VALUES(expires_at)"
        );
        $stmt->execute([$tenantId, $phone, $botType, $state, json_encode($context), $expiresAt]);
    }

    public static function reset(int $tenantId, string $phone, string $botType): void
    {
        $stmt = self::db()->prepare(
            'DELETE FROM bot_conversations WHERE tenant_id = ? AND customer_phone = ? AND bot_type = ?'
        );
        $stmt->execute([$tenantId, $phone, $botType]);
    }

    /** Cron: drop all expired conversations. */
    public static function cleanupExpired(): int
    {
        $stmt = self::db()->query(
            'DELETE FROM bot_conversations WHERE expires_at IS NOT NULL AND expires_at < NOW()'
        );

        return $stmt->rowCount();
    }
}
