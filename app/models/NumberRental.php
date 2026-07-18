<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * NumberRental — links a tenant to a platform_number for a subscription period.
 */
class NumberRental extends BaseModel
{
    public static function activeForTenant(int $tenantId): ?array
    {
        $stmt = self::db()->prepare(
            "SELECT nr.*, pn.display_number, pn.phone_number_id
             FROM number_rentals nr
             JOIN platform_numbers pn ON pn.id = nr.platform_number_id
             WHERE nr.tenant_id = ? AND nr.status = 'active'
               AND (nr.ends_at IS NULL OR nr.ends_at > NOW())
             ORDER BY nr.id DESC LIMIT 1"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetch() ?: null;
    }

    public static function create(int $tenantId, int $platformNumberId, ?int $subscriptionId, ?string $endsAt): int
    {
        $stmt = self::db()->prepare(
            "INSERT INTO number_rentals (tenant_id, platform_number_id, subscription_id, starts_at, ends_at, status)
             VALUES (?, ?, ?, NOW(), ?, 'active')"
        );
        $stmt->execute([$tenantId, $platformNumberId, $subscriptionId, $endsAt]);

        return (int) self::db()->lastInsertId();
    }

    public static function revoke(int $id): bool
    {
        $stmt = self::db()->prepare("UPDATE number_rentals SET status = 'revoked' WHERE id = ?");

        return $stmt->execute([$id]);
    }
}
