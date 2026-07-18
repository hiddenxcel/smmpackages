<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * TenantPanel — a tenant's connected SMM panel. api_key stored encrypted.
 * All reads are scoped by tenant_id (from session, never input).
 */
class TenantPanel extends BaseModel
{
    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM tenant_panels WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    /** Find scoped to a tenant (isolation guard for any id coming near input). */
    public static function findForTenant(int $id, int $tenantId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM tenant_panels WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);

        return $stmt->fetch() ?: null;
    }

    public static function forTenant(int $tenantId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM tenant_panels WHERE tenant_id = ? ORDER BY id');
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll();
    }

    public static function countForTenant(int $tenantId): int
    {
        $stmt = self::db()->prepare('SELECT COUNT(*) FROM tenant_panels WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param string $apiKeyPlain plaintext key (encrypted here before storage)
     */
    public static function create(
        int $tenantId,
        string $name,
        string $panelType,
        string $apiUrl,
        string $apiKeyPlain,
        string $apiVersion,
        string $authMethod,
        ?float $balance = null,
        ?string $balanceCurrency = null,
        ?int $servicesCount = null
    ): int {
        $stmt = self::db()->prepare(
            "INSERT INTO tenant_panels
                (tenant_id, name, panel_type, api_url, api_key_enc, api_version, auth_method,
                 last_checked_at, last_balance, balance_currency, services_count, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, 'active')"
        );
        $stmt->execute([
            $tenantId, $name, $panelType, $apiUrl,
            Crypto::encrypt($apiKeyPlain), $apiVersion, $authMethod,
            $balance, $balanceCurrency, $servicesCount,
        ]);

        return (int) self::db()->lastInsertId();
    }

    public static function delete(int $id, int $tenantId): bool
    {
        $stmt = self::db()->prepare('DELETE FROM tenant_panels WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    /** Decrypt the stored API key for use at call time. */
    public static function apiKey(array $panel): ?string
    {
        return Crypto::decrypt($panel['api_key_enc'] ?? null);
    }
}
