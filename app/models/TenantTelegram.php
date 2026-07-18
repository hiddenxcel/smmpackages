<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * TenantTelegram — a tenant's connected Telegram bot. bot_token encrypted.
 * webhook_secret is a random path token used to route Telegram updates to the
 * right tenant (Telegram posts to telegram.php?s=<secret>).
 */
class TenantTelegram extends BaseModel
{
    public static function forTenant(int $tenantId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM tenant_telegram WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);

        return $stmt->fetch() ?: null;
    }

    /** THE ROUTER KEY: map a webhook secret to its tenant's telegram row. */
    public static function findBySecret(string $secret): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM tenant_telegram WHERE webhook_secret = ?');
        $stmt->execute([$secret]);

        return $stmt->fetch() ?: null;
    }

    /** Upsert. Blank token = keep existing. Returns [id, secret]. */
    public static function save(int $tenantId, ?string $botTokenPlain, ?string $botUsername): array
    {
        $existing = self::forTenant($tenantId);
        $tokenEnc = ($botTokenPlain !== null && $botTokenPlain !== '')
            ? Crypto::encrypt($botTokenPlain)
            : ($existing['bot_token_enc'] ?? null);
        $secret = $existing['webhook_secret'] ?? bin2hex(random_bytes(24));

        if ($existing !== null) {
            self::db()->prepare(
                "UPDATE tenant_telegram SET bot_token_enc = ?, bot_username = ?, status = 'active' WHERE tenant_id = ?"
            )->execute([$tokenEnc, $botUsername, $tenantId]);

            return ['id' => (int) $existing['id'], 'secret' => $secret];
        }

        self::db()->prepare(
            "INSERT INTO tenant_telegram (tenant_id, bot_token_enc, bot_username, webhook_secret, status)
             VALUES (?, ?, ?, ?, 'active')"
        )->execute([$tenantId, $tokenEnc, $botUsername, $secret]);

        return ['id' => (int) self::db()->lastInsertId(), 'secret' => $secret];
    }

    public static function token(array $telegram): ?string
    {
        return Crypto::decrypt($telegram['bot_token_enc'] ?? null);
    }
}
