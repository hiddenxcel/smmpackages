<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * TenantAi — a tenant's AI configuration (DeepSeek API key, encrypted).
 */
class TenantAi extends BaseModel
{
    public static function forTenant(int $tenantId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM tenant_ai WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);

        return $stmt->fetch() ?: null;
    }

    /** Upsert. Blank key = keep the existing one. */
    public static function save(int $tenantId, ?string $deepseekKeyPlain): int
    {
        $existing = self::forTenant($tenantId);
        $keyEnc = ($deepseekKeyPlain !== null && $deepseekKeyPlain !== '')
            ? Crypto::encrypt($deepseekKeyPlain)
            : ($existing['deepseek_api_key_enc'] ?? null);

        if ($existing !== null) {
            self::db()->prepare("UPDATE tenant_ai SET deepseek_api_key_enc = ?, status = 'active' WHERE tenant_id = ?")
                ->execute([$keyEnc, $tenantId]);

            return (int) $existing['id'];
        }

        $stmt = self::db()->prepare(
            "INSERT INTO tenant_ai (tenant_id, deepseek_api_key_enc, status) VALUES (?, ?, 'active')"
        );
        $stmt->execute([$tenantId, $keyEnc]);

        return (int) self::db()->lastInsertId();
    }

    public static function apiKey(int $tenantId): ?string
    {
        $row = self::forTenant($tenantId);

        return $row ? Crypto::decrypt($row['deepseek_api_key_enc'] ?? null) : null;
    }
}
