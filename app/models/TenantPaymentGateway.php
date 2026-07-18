<?php

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/../lib/Crypto.php';

/**
 * TenantPaymentGateway — a payment gateway a TENANT has configured for their
 * own customers to pay with (wallet top-ups / order payments). This is the
 * tenant's OWN money rail — separate from the SaaS gateways the tenant uses to
 * pay HiddenXcel (those live in config/.env).
 *
 * api_key / webhook_secret are stored encrypted. gateway is a free-form code
 * (e.g. 'snippe', 'nowpayments') so each tenant picks what they support.
 */
class TenantPaymentGateway extends BaseModel
{
    /** Active gateways for a tenant (decrypted secrets included). */
    public static function activeForTenant(int $tenantId): array
    {
        $stmt = self::db()->prepare(
            "SELECT * FROM tenant_payment_gateways WHERE tenant_id = ? AND status = 'active' ORDER BY gateway"
        );
        $stmt->execute([$tenantId]);

        return array_map([self::class, 'decryptRow'], $stmt->fetchAll());
    }

    public static function allForTenant(int $tenantId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM tenant_payment_gateways WHERE tenant_id = ? ORDER BY gateway');
        $stmt->execute([$tenantId]);

        return array_map([self::class, 'decryptRow'], $stmt->fetchAll());
    }

    public static function find(int $tenantId, string $gateway): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM tenant_payment_gateways WHERE tenant_id = ? AND gateway = ?');
        $stmt->execute([$tenantId, $gateway]);
        $row = $stmt->fetch();

        return $row ? self::decryptRow($row) : null;
    }

    /**
     * Upsert a tenant gateway. Blank api_key/webhook_secret keeps the existing
     * encrypted value (so editing status doesn't wipe stored keys).
     */
    public static function save(int $tenantId, string $gateway, ?string $apiKey, ?string $webhookSecret, string $status = 'active'): void
    {
        $existing = self::rawRow($tenantId, $gateway);

        $apiKeyEnc = ($apiKey !== null && $apiKey !== '')
            ? Crypto::encrypt($apiKey)
            : ($existing['api_key_enc'] ?? null);

        $secretEnc = ($webhookSecret !== null && $webhookSecret !== '')
            ? Crypto::encrypt($webhookSecret)
            : ($existing['webhook_secret_enc'] ?? null);

        if ($existing !== null) {
            $stmt = self::db()->prepare(
                'UPDATE tenant_payment_gateways SET api_key_enc = ?, webhook_secret_enc = ?, status = ? WHERE tenant_id = ? AND gateway = ?'
            );
            $stmt->execute([$apiKeyEnc, $secretEnc, $status, $tenantId, $gateway]);

            return;
        }

        $stmt = self::db()->prepare(
            'INSERT INTO tenant_payment_gateways (tenant_id, gateway, api_key_enc, webhook_secret_enc, status) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$tenantId, $gateway, $apiKeyEnc, $secretEnc, $status]);
    }

    public static function delete(int $tenantId, string $gateway): bool
    {
        $stmt = self::db()->prepare('DELETE FROM tenant_payment_gateways WHERE tenant_id = ? AND gateway = ?');

        return $stmt->execute([$tenantId, $gateway]);
    }

    private static function rawRow(int $tenantId, string $gateway): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM tenant_payment_gateways WHERE tenant_id = ? AND gateway = ?');
        $stmt->execute([$tenantId, $gateway]);

        return $stmt->fetch() ?: null;
    }

    /** Add decrypted api_key + webhook_secret alongside the raw row. */
    private static function decryptRow(array $row): array
    {
        $row['api_key'] = Crypto::decrypt($row['api_key_enc'] ?? null);
        $row['webhook_secret'] = Crypto::decrypt($row['webhook_secret_enc'] ?? null);

        return $row;
    }
}
