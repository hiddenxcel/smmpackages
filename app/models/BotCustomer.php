<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * BotCustomer — a tenant's end-customer and their wallet balance.
 *
 * Ported from kuzapanel-bot's single-shop Customer model, made multi-tenant:
 * every lookup/mutation is scoped by tenant_id, and the same phone number is a
 * DIFFERENT customer for a different tenant (UNIQUE tenant_id+phone).
 *
 * The wallet: credit() adds funds (top-up / referral), debit() spends them
 * atomically (WHERE balance >= amount so it can never go negative), and
 * total_spent tracks lifetime purchases.
 */
class BotCustomer extends BaseModel
{
    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM bot_customers WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function findByPhone(int $tenantId, string $phone): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM bot_customers WHERE tenant_id = ? AND phone = ?');
        $stmt->execute([$tenantId, $phone]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Fetch the customer (updating their display name if changed) or create one.
     * A fresh customer gets a per-tenant-unique referral code.
     */
    public static function getOrCreate(int $tenantId, string $phone, ?string $name = null): array
    {
        $customer = self::findByPhone($tenantId, $phone);

        if ($customer !== null) {
            if ($name !== null && $name !== '' && $customer['name'] !== $name) {
                self::db()->prepare('UPDATE bot_customers SET name = ? WHERE id = ?')->execute([$name, $customer['id']]);
                $customer['name'] = $name;
            }

            return $customer;
        }

        $stmt = self::db()->prepare(
            'INSERT INTO bot_customers (tenant_id, phone, name, referral_code) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$tenantId, $phone, $name, self::generateUniqueReferralCode($tenantId)]);

        return self::findByPhone($tenantId, $phone);
    }

    /**
     * Paginated, searchable customer list for the tenant dashboard, with each
     * customer's total order count attached. Scoped to the tenant.
     */
    public static function search(int $tenantId, string $q = '', int $page = 1, int $perPage = 25): array
    {
        $where = 'WHERE c.tenant_id = ?';
        $params = [$tenantId];

        if ($q !== '') {
            $where .= ' AND (c.phone LIKE ? OR c.name LIKE ? OR c.referral_code LIKE ?)';
            $like = '%' . self::escapeLike($q) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $countStmt = self::db()->prepare("SELECT COUNT(*) AS c FROM bot_customers c $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['c'];

        $perPage = max(1, $perPage);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $stmt = self::db()->prepare(
            "SELECT c.*, COUNT(o.id) AS order_count
             FROM bot_customers c
             LEFT JOIN bot_orders o ON o.customer_id = c.id
             $where
             GROUP BY c.id
             ORDER BY c.created_at DESC
             LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute($params);

        return [
            'rows' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
        ];
    }

    public static function findByReferralCode(int $tenantId, string $code): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM bot_customers WHERE tenant_id = ? AND referral_code = ?');
        $stmt->execute([$tenantId, strtoupper($code)]);

        return $stmt->fetch() ?: null;
    }

    public static function setReferredBy(int $id, int $referrerId): bool
    {
        // Only set once, and never self-refer.
        $stmt = self::db()->prepare(
            'UPDATE bot_customers SET referred_by = ? WHERE id = ? AND referred_by IS NULL AND id <> ?'
        );
        $stmt->execute([$referrerId, $id, $referrerId]);

        return $stmt->rowCount() === 1;
    }

    public static function creditReferralEarning(int $id, float $amount): bool
    {
        $stmt = self::db()->prepare(
            'UPDATE bot_customers SET balance = balance + ?, referral_earnings = referral_earnings + ? WHERE id = ?'
        );

        return $stmt->execute([$amount, $amount, $id]);
    }

    public static function markFirstDepositDone(int $id): bool
    {
        $stmt = self::db()->prepare('UPDATE bot_customers SET first_deposit_done = 1 WHERE id = ?');

        return $stmt->execute([$id]);
    }

    public static function countReferrals(int $id): int
    {
        $stmt = self::db()->prepare('SELECT COUNT(*) AS c FROM bot_customers WHERE referred_by = ?');
        $stmt->execute([$id]);

        return (int) $stmt->fetch()['c'];
    }

    public static function setLang(int $id, string $lang): bool
    {
        $stmt = self::db()->prepare('UPDATE bot_customers SET lang = ? WHERE id = ?');

        return $stmt->execute([$lang, $id]);
    }

    public static function setLastPaymentPhone(int $id, string $paymentPhone): bool
    {
        $stmt = self::db()->prepare('UPDATE bot_customers SET last_payment_phone = ? WHERE id = ?');

        return $stmt->execute([$paymentPhone, $id]);
    }

    public static function credit(int $id, float $amount): bool
    {
        $stmt = self::db()->prepare('UPDATE bot_customers SET balance = balance + ? WHERE id = ?');

        return $stmt->execute([$amount, $id]);
    }

    /**
     * Spend from the wallet atomically. The WHERE balance >= amount guard means
     * a concurrent/insufficient debit fails (returns false) rather than going
     * negative. total_spent grows by the amount (lifetime purchases).
     */
    public static function debit(int $id, float $amount): bool
    {
        $stmt = self::db()->prepare(
            'UPDATE bot_customers SET balance = balance - ?, total_spent = total_spent + ? WHERE id = ? AND balance >= ?'
        );
        $stmt->execute([$amount, $amount, $id, $amount]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Manual tenant balance adjustment (does NOT touch total_spent — not a
     * purchase). $amount may be positive or negative; never goes negative.
     */
    public static function adjustBalance(int $id, float $amount): bool
    {
        $stmt = self::db()->prepare(
            'UPDATE bot_customers SET balance = balance + ? WHERE id = ? AND balance + ? >= 0'
        );
        $stmt->execute([$amount, $id, $amount]);

        return $stmt->rowCount() === 1;
    }

    /** Per-tenant-unique referral code (uses tenant prefix + hex). */
    private static function generateUniqueReferralCode(int $tenantId): string
    {
        do {
            $code = 'C' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 7));
            $stmt = self::db()->prepare('SELECT 1 FROM bot_customers WHERE tenant_id = ? AND referral_code = ?');
            $stmt->execute([$tenantId, $code]);
        } while ($stmt->fetch() !== false);

        return $code;
    }

    private static function escapeLike(string $s): string
    {
        return addcslashes($s, '%_\\');
    }
}
