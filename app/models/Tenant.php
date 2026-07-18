<?php

require_once __DIR__ . '/BaseModel.php';

class Tenant extends BaseModel
{
    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM tenants WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM tenants WHERE email = ?');
        $stmt->execute([strtolower(trim($email))]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Create a tenant with a bcrypt password hash + unique referral code.
     * $referredBy is the id of the referring tenant, if signed up via a code.
     */
    public static function create(string $businessName, string $email, string $password, ?string $phone = null, string $lang = 'en', ?int $referredBy = null): array
    {
        $stmt = self::db()->prepare(
            'INSERT INTO tenants (business_name, email, phone, password_hash, lang, referral_code, referred_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            trim($businessName),
            strtolower(trim($email)),
            $phone,
            password_hash($password, PASSWORD_BCRYPT),
            $lang,
            self::generateReferralCode(),
            $referredBy,
        ]);

        return self::find((int) self::db()->lastInsertId());
    }

    public static function findByReferralCode(string $code): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM tenants WHERE referral_code = ?');
        $stmt->execute([strtoupper(trim($code))]);

        return $stmt->fetch() ?: null;
    }

    private static function generateReferralCode(): string
    {
        do {
            $code = 'HX' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
            $stmt = self::db()->prepare('SELECT 1 FROM tenants WHERE referral_code = ?');
            $stmt->execute([$code]);
        } while ($stmt->fetch() !== false);

        return $code;
    }

    /** Add referral credit (USD) to a tenant's balance. */
    public static function addReferralCredit(int $id, float $amount): bool
    {
        $stmt = self::db()->prepare('UPDATE tenants SET referral_credit = referral_credit + ? WHERE id = ?');

        return $stmt->execute([$amount, $id]);
    }

    /** Spend up to $amount of credit; returns the amount actually applied. */
    public static function spendReferralCredit(int $id, float $amount): float
    {
        $tenant = self::find($id);
        if ($tenant === null) {
            return 0.0;
        }
        $available = (float) $tenant['referral_credit'];
        $applied = min($available, max(0.0, $amount));
        if ($applied > 0) {
            self::db()->prepare('UPDATE tenants SET referral_credit = referral_credit - ? WHERE id = ?')
                ->execute([$applied, $id]);
        }

        return $applied;
    }

    /** Mark first payment done; returns true only on the first transition. */
    public static function markFirstPayment(int $id): bool
    {
        $stmt = self::db()->prepare('UPDATE tenants SET first_payment_done = 1 WHERE id = ? AND first_payment_done = 0');
        $stmt->execute([$id]);

        return $stmt->rowCount() === 1;
    }

    public static function countReferred(int $id): int
    {
        $stmt = self::db()->prepare('SELECT COUNT(*) FROM tenants WHERE referred_by = ?');
        $stmt->execute([$id]);

        return (int) $stmt->fetchColumn();
    }

    public static function verifyPassword(array $tenant, string $password): bool
    {
        return password_verify($password, $tenant['password_hash']);
    }

    public static function setLang(int $id, string $lang): bool
    {
        $stmt = self::db()->prepare('UPDATE tenants SET lang = ? WHERE id = ?');

        return $stmt->execute([$lang, $id]);
    }

    public static function updatePassword(int $id, string $password): bool
    {
        $stmt = self::db()->prepare('UPDATE tenants SET password_hash = ? WHERE id = ?');

        return $stmt->execute([password_hash($password, PASSWORD_BCRYPT), $id]);
    }

    public static function updateProfile(int $id, string $businessName, ?string $phone): bool
    {
        $stmt = self::db()->prepare('UPDATE tenants SET business_name = ?, phone = ? WHERE id = ?');

        return $stmt->execute([trim($businessName), $phone, $id]);
    }
}
