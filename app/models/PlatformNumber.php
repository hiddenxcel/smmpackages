<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * PlatformNumber — HiddenXcel's own rentable Cloud API numbers (multi-country).
 * cloud_api_token stored encrypted. When rented, it backs a tenant_whatsapp
 * connection with source='rented'.
 */
class PlatformNumber extends BaseModel
{
    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM platform_numbers WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function findByPhoneNumberId(string $phoneNumberId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM platform_numbers WHERE phone_number_id = ?');
        $stmt->execute([$phoneNumberId]);

        return $stmt->fetch() ?: null;
    }

    /** Numbers available to rent right now. */
    public static function available(): array
    {
        $stmt = self::db()->query(
            "SELECT * FROM platform_numbers WHERE status = 'available' ORDER BY country, display_number"
        );

        return $stmt->fetchAll();
    }

    public static function setStatus(int $id, string $status): bool
    {
        $stmt = self::db()->prepare('UPDATE platform_numbers SET status = ? WHERE id = ?');

        return $stmt->execute([$status, $id]);
    }

    public static function token(array $number): ?string
    {
        return Crypto::decrypt($number['cloud_api_token_enc'] ?? null);
    }
}
