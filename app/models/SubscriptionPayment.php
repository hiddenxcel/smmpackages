<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * SubscriptionPayment — SaaS billing records (tenant -> HiddenXcel).
 * transaction_ref is UNIQUE: that uniqueness plus the "already success?" check
 * in markSuccess() is the replay protection for webhook-driven activation.
 */
class SubscriptionPayment extends BaseModel
{
    public const GATEWAYS = ['nowpayments', 'binance', 'snippe', 'cryptomus', 'heleket'];

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM subscription_payments WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function findByRef(string $ref): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM subscription_payments WHERE transaction_ref = ?');
        $stmt->execute([$ref]);

        return $stmt->fetch() ?: null;
    }

    /** Replay guard for the Binance verify flow: has this Order ID been used? */
    public static function binanceOrderUsed(string $binanceOrderId): bool
    {
        $stmt = self::db()->prepare(
            'SELECT id FROM subscription_payments WHERE binance_order_id = ? LIMIT 1'
        );
        $stmt->execute([$binanceOrderId]);

        return $stmt->fetch() !== false;
    }

    /** Record the verified Binance Order ID on a payment (replay evidence). */
    public static function setBinanceOrder(int $paymentId, string $binanceOrderId): void
    {
        self::db()->prepare('UPDATE subscription_payments SET binance_order_id = ? WHERE id = ?')
            ->execute([$binanceOrderId, $paymentId]);
    }

    public static function forTenant(int $tenantId): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM subscription_payments WHERE tenant_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll();
    }

    public static function create(
        int $tenantId,
        string $gateway,
        string $transactionRef,
        float $amount,
        int $months = 1,
        ?int $planId = null,
        ?int $subscriptionId = null,
        string $currency = 'USD'
    ): int {
        $stmt = self::db()->prepare(
            'INSERT INTO subscription_payments
                (tenant_id, plan_id, subscription_id, gateway, transaction_ref, amount, currency, months, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'pending\')'
        );
        $stmt->execute([$tenantId, $planId, $subscriptionId, $gateway, $transactionRef, $amount, $currency, $months]);

        return (int) self::db()->lastInsertId();
    }

    /**
     * Mark a payment success — but only if it is NOT already success (replay guard).
     * Returns true only on the transition pending/failed -> success, so the caller
     * activates the subscription exactly once.
     */
    public static function markSuccess(string $ref, ?string $rawResponse = null): bool
    {
        $stmt = self::db()->prepare(
            "UPDATE subscription_payments
             SET status = 'success', raw_response = ?
             WHERE transaction_ref = ? AND status <> 'success'"
        );
        $stmt->execute([$rawResponse, $ref]);

        return $stmt->rowCount() === 1;
    }

    public static function markFailed(string $ref, ?string $rawResponse = null): bool
    {
        $stmt = self::db()->prepare(
            "UPDATE subscription_payments
             SET status = 'failed', raw_response = ?
             WHERE transaction_ref = ? AND status = 'pending'"
        );

        return $stmt->execute([$rawResponse, $ref]);
    }

    /**
     * Cron: stale pending payments older than the given minutes -> failed.
     * Any referral credit reserved on those payments is refunded to the tenant.
     */
    public static function expireStale(int $minutes = 60): int
    {
        $db = self::db();

        // Refund reserved credit for the rows we're about to fail.
        $stale = $db->prepare(
            "SELECT id, tenant_id, credit_applied FROM subscription_payments
             WHERE status = 'pending' AND credit_applied > 0
               AND created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)"
        );
        $stale->execute([$minutes]);
        foreach ($stale->fetchAll() as $row) {
            $db->prepare('UPDATE tenants SET referral_credit = referral_credit + ? WHERE id = ?')
                ->execute([$row['credit_applied'], $row['tenant_id']]);
        }

        $stmt = $db->prepare(
            "UPDATE subscription_payments
             SET status = 'failed'
             WHERE status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)"
        );
        $stmt->execute([$minutes]);

        return $stmt->rowCount();
    }
}
