<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * BotOrder — an order placed through a tenant's bot, mirrored to the provider.
 * Always scoped by tenant_id.
 */
class BotOrder extends BaseModel
{
    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM bot_orders WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function create(
        int $tenantId,
        ?int $panelId,
        string $customerPhone,
        ?string $serviceId,
        ?string $serviceName,
        ?string $link,
        ?int $quantity,
        ?float $charge,
        ?string $providerOrderId = null,
        string $status = 'pending'
    ): int {
        $stmt = self::db()->prepare(
            "INSERT INTO bot_orders
                (tenant_id, panel_id, provider_order_id, customer_phone, service_id, service_name, link, quantity, charge, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$tenantId, $panelId, $providerOrderId, $customerPhone, $serviceId, $serviceName, $link, $quantity, $charge, $status]);

        return (int) self::db()->lastInsertId();
    }

    public static function setProviderOrder(int $id, string $providerOrderId, string $status = 'processing'): bool
    {
        $stmt = self::db()->prepare('UPDATE bot_orders SET provider_order_id = ?, status = ? WHERE id = ?');

        return $stmt->execute([$providerOrderId, $status, $id]);
    }

    /**
     * Create a wallet-based order (priced): records the customer, the amount
     * charged, how it was paid, and the payment status. Used by the wallet
     * Order Bot. Returns the new order id.
     */
    public static function createWallet(array $data): int
    {
        $stmt = self::db()->prepare(
            "INSERT INTO bot_orders
                (tenant_id, panel_id, customer_phone, customer_id, service_id, service_name, link,
                 quantity, amount, payment_status, paid_from, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['tenant_id'],
            $data['panel_id'] ?? null,
            $data['customer_phone'],
            $data['customer_id'] ?? null,
            $data['service_id'] ?? null,
            $data['service_name'] ?? null,
            $data['link'] ?? null,
            $data['quantity'] ?? null,
            $data['amount'] ?? null,
            $data['payment_status'] ?? 'pending',
            $data['paid_from'] ?? null,
            $data['status'] ?? 'pending',
        ]);

        return (int) self::db()->lastInsertId();
    }

    public static function markPaid(int $id, string $paidFrom): bool
    {
        $stmt = self::db()->prepare("UPDATE bot_orders SET payment_status = 'paid', paid_from = ? WHERE id = ?");

        return $stmt->execute([$paidFrom, $id]);
    }

    public static function setError(int $id, ?string $message): bool
    {
        $stmt = self::db()->prepare('UPDATE bot_orders SET order_error = ? WHERE id = ?');

        return $stmt->execute([$message, $id]);
    }

    public static function forTenant(int $tenantId, int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));
        $stmt = self::db()->prepare(
            "SELECT * FROM bot_orders WHERE tenant_id = ? ORDER BY created_at DESC LIMIT ?"
        );
        $stmt->execute([$tenantId, $limit]);

        return $stmt->fetchAll();
    }

    /** Orders that still need a status poll (have a provider id, not in a terminal state). */
    public static function needingSync(int $limit = 200): array
    {
        $limit = max(1, min($limit, 500));
        $stmt = self::db()->prepare(
            "SELECT * FROM bot_orders
             WHERE provider_order_id IS NOT NULL
               AND (status IS NULL OR LOWER(status) NOT IN ('completed','canceled','cancelled','partial','refunded'))
             ORDER BY updated_at ASC LIMIT ?"
        );
        $stmt->execute([$limit]);

        return $stmt->fetchAll();
    }

    public static function updateStatus(int $id, ?string $status): bool
    {
        $stmt = self::db()->prepare('UPDATE bot_orders SET status = ? WHERE id = ?');

        return $stmt->execute([$status, $id]);
    }

    /** Status counts for the orders page summary. */
    public static function statusCounts(int $tenantId): array
    {
        $stmt = self::db()->prepare(
            'SELECT status, COUNT(*) AS n FROM bot_orders WHERE tenant_id = ? GROUP BY status'
        );
        $stmt->execute([$tenantId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['status'] ?? 'unknown'] = (int) $row['n'];
        }

        return $out;
    }
}
