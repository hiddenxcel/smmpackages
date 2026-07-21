<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * TenantWhatsApp — a tenant's WhatsApp Cloud API connection.
 * phone_number_id is UNIQUE and is the webhook router key: an incoming Meta
 * webhook is mapped to its tenant by matching phone_number_id.
 * Tokens stored encrypted.
 */
class TenantWhatsApp extends BaseModel
{
    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM tenant_whatsapp WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function forTenant(int $tenantId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM tenant_whatsapp WHERE tenant_id = ? ORDER BY id LIMIT 1');
        $stmt->execute([$tenantId]);

        return $stmt->fetch() ?: null;
    }

    /** All of a tenant's connected numbers (a tenant may run several). */
    public static function allForTenant(int $tenantId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM tenant_whatsapp WHERE tenant_id = ? ORDER BY id');
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll();
    }

    /** The tenant's number for a given bot role (order/support). Falls back to a
     *  'both' number, then to any number, so a single-number setup still works. */
    public static function forTenantBot(int $tenantId, string $botType): ?array
    {
        $stmt = self::db()->prepare(
            "SELECT * FROM tenant_whatsapp
             WHERE tenant_id = ? AND status = 'active'
             ORDER BY (bot_type = ?) DESC, (bot_type = 'both') DESC, id
             LIMIT 1"
        );
        $stmt->execute([$tenantId, $botType]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Upsert a number keyed on phone_number_id (so a tenant can add SEVERAL
     * numbers — e.g. one for Order, one for Support). Blank token keeps the
     * existing one. Returns the row id.
     */
    public static function saveByPnid(
        int $tenantId,
        string $source,
        ?string $tokenPlain,
        string $phoneNumberId,
        ?string $wabaId,
        ?string $displayNumber,
        string $botType,
        ?string $verifyToken = null
    ): int {
        $stmt = self::db()->prepare('SELECT * FROM tenant_whatsapp WHERE phone_number_id = ? AND tenant_id = ?');
        $stmt->execute([$phoneNumberId, $tenantId]);
        $existing = $stmt->fetch() ?: null;

        $tokenEnc = ($tokenPlain !== null && $tokenPlain !== '')
            ? Crypto::encrypt($tokenPlain)
            : ($existing['cloud_api_token_enc'] ?? null);

        if ($existing !== null) {
            $upd = self::db()->prepare(
                "UPDATE tenant_whatsapp
                 SET source = ?, cloud_api_token_enc = ?, waba_id = ?, display_number = ?,
                     bot_type = ?, verify_token = ?, status = 'active'
                 WHERE id = ?"
            );
            $upd->execute([$source, $tokenEnc, $wabaId, $displayNumber, $botType, $verifyToken, $existing['id']]);

            return (int) $existing['id'];
        }

        $ins = self::db()->prepare(
            "INSERT INTO tenant_whatsapp
                (tenant_id, source, cloud_api_token_enc, phone_number_id, waba_id, display_number, bot_type, verify_token, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')"
        );
        $ins->execute([$tenantId, $source, $tokenEnc, $phoneNumberId, $wabaId, $displayNumber, $botType, $verifyToken]);

        return (int) self::db()->lastInsertId();
    }

    /** Delete one of the tenant's numbers (tenant-scoped). */
    public static function deleteForTenant(int $id, int $tenantId): bool
    {
        $stmt = self::db()->prepare('DELETE FROM tenant_whatsapp WHERE id = ? AND tenant_id = ?');

        return $stmt->execute([$id, $tenantId]);
    }

    /** THE ROUTER KEY: map an incoming webhook's phone_number_id to its tenant. */
    public static function findByPhoneNumberId(string $phoneNumberId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM tenant_whatsapp WHERE phone_number_id = ?');
        $stmt->execute([$phoneNumberId]);

        return $stmt->fetch() ?: null;
    }

    /** Is this phone_number_id already used by a DIFFERENT tenant? (uniqueness guard) */
    public static function phoneNumberIdTakenByOther(string $phoneNumberId, int $tenantId): bool
    {
        $stmt = self::db()->prepare(
            'SELECT 1 FROM tenant_whatsapp WHERE phone_number_id = ? AND tenant_id <> ? LIMIT 1'
        );
        $stmt->execute([$phoneNumberId, $tenantId]);

        return $stmt->fetch() !== false;
    }

    /**
     * True if this tenant already runs $botType on a DIFFERENT number than
     * $exceptPnid. Enforces "one number = one service": a service can't be
     * assigned to two numbers. ('both' historically covers order+support.)
     */
    public static function botRoleTakenByOther(int $tenantId, string $botType, string $exceptPnid): bool
    {
        $stmt = self::db()->prepare(
            "SELECT 1 FROM tenant_whatsapp
             WHERE tenant_id = ? AND phone_number_id <> ?
               AND (bot_type = ? OR bot_type = 'both')
             LIMIT 1"
        );
        $stmt->execute([$tenantId, $exceptPnid, $botType]);

        return $stmt->fetch() !== false;
    }

    /**
     * Upsert a tenant's WhatsApp connection. Token encrypted here.
     * A blank token means "keep the existing one" (edit without re-typing secrets).
     */
    public static function save(
        int $tenantId,
        string $source,
        ?string $tokenPlain,
        string $phoneNumberId,
        ?string $wabaId,
        ?string $displayNumber,
        string $botType,
        ?string $verifyToken = null
    ): int {
        $existing = self::forTenant($tenantId);

        $tokenEnc = ($tokenPlain !== null && $tokenPlain !== '')
            ? Crypto::encrypt($tokenPlain)
            : ($existing['cloud_api_token_enc'] ?? null);

        if ($existing !== null) {
            $stmt = self::db()->prepare(
                "UPDATE tenant_whatsapp
                 SET source = ?, cloud_api_token_enc = ?, phone_number_id = ?, waba_id = ?,
                     display_number = ?, bot_type = ?, verify_token = ?, status = 'active'
                 WHERE id = ?"
            );
            $stmt->execute([$source, $tokenEnc, $phoneNumberId, $wabaId, $displayNumber, $botType, $verifyToken, $existing['id']]);

            return (int) $existing['id'];
        }

        $stmt = self::db()->prepare(
            "INSERT INTO tenant_whatsapp
                (tenant_id, source, cloud_api_token_enc, phone_number_id, waba_id, display_number, bot_type, verify_token, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')"
        );
        $stmt->execute([$tenantId, $source, $tokenEnc, $phoneNumberId, $wabaId, $displayNumber, $botType, $verifyToken]);

        return (int) self::db()->lastInsertId();
    }

    public static function token(array $whatsapp): ?string
    {
        return Crypto::decrypt($whatsapp['cloud_api_token_enc'] ?? null);
    }
}
