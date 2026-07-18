<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * BotPayment — an end-customer's payment (wallet top-up or direct order pay).
 *
 * Ported from kuzapanel-bot's Payment model, made multi-tenant. transaction_ref
 * is the gateway's reference; the webhook uses markSuccess() to transition a
 * payment to 'success' EXACTLY ONCE (replay guard), so crediting a wallet or
 * completing an order can never be double-applied.
 *
 * gateway is a free-form string because each tenant configures their own
 * gateways (tenant_payment_gateways).
 */
class BotPayment extends BaseModel
{
    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM bot_payments WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function findByRef(int $tenantId, string $ref): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM bot_payments WHERE tenant_id = ? AND transaction_ref = ?');
        $stmt->execute([$tenantId, $ref]);

        return $stmt->fetch() ?: null;
    }

    /** Look up by ref across tenants (webhook path — ref is globally unique enough). */
    public static function findByRefAnyTenant(string $ref): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM bot_payments WHERE transaction_ref = ? LIMIT 1');
        $stmt->execute([$ref]);

        return $stmt->fetch() ?: null;
    }

    public static function create(int $tenantId, array $data): int
    {
        $stmt = self::db()->prepare(
            'INSERT INTO bot_payments (tenant_id, type, order_id, customer_id, gateway, transaction_ref, amount, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tenantId,
            $data['type'] ?? 'wallet_topup',
            $data['order_id'] ?? null,
            $data['customer_id'] ?? null,
            $data['gateway'],
            $data['transaction_ref'] ?? null,
            $data['amount'],
            $data['status'] ?? 'pending',
        ]);

        return (int) self::db()->lastInsertId();
    }

    public static function setTransactionRef(int $id, string $ref): bool
    {
        $stmt = self::db()->prepare('UPDATE bot_payments SET transaction_ref = ? WHERE id = ?');

        return $stmt->execute([$ref, $id]);
    }

    /**
     * Transition a pending payment to 'success' exactly once.
     * Returns true ONLY on the first successful transition (replay guard):
     * subsequent webhook deliveries for the same ref return false, so wallet
     * credit / order completion runs a single time.
     */
    public static function markSuccess(int $id): bool
    {
        $stmt = self::db()->prepare("UPDATE bot_payments SET status = 'success' WHERE id = ? AND status = 'pending'");
        $stmt->execute([$id]);

        return $stmt->rowCount() === 1;
    }

    public static function markFailed(int $id): bool
    {
        $stmt = self::db()->prepare("UPDATE bot_payments SET status = 'failed' WHERE id = ? AND status = 'pending'");
        $stmt->execute([$id]);

        return $stmt->rowCount() === 1;
    }

    /** Mark long-stale pending payments as failed (USSD never completed). */
    public static function expireStalePending(int $tenantId, int $olderThanMinutes = 60): int
    {
        $stmt = self::db()->prepare(
            "UPDATE bot_payments SET status = 'failed'
             WHERE tenant_id = ? AND status = 'pending'
               AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) >= ?"
        );
        $stmt->execute([$tenantId, $olderThanMinutes]);

        return $stmt->rowCount();
    }

    public static function byCustomer(int $customerId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM bot_payments WHERE customer_id = ? ORDER BY created_at DESC');
        $stmt->execute([$customerId]);

        return $stmt->fetchAll();
    }
}
