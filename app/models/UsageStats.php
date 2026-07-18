<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * UsageStats — this-month usage vs plan limits, for the dashboard meters.
 *
 * Returns, per metric: ['used' => N, 'max' => M, 'pct' => 0..100].
 * Limits come from the plans table (max_panels / max_orders_monthly /
 * max_messages_monthly / max_refills_monthly). We use the largest limit among
 * the tenant's ACTIVE subscriptions' plans, falling back to the order_bot
 * catalogue plan, so a tenant is never shown a smaller cap than they paid for.
 *
 * tenant_id always comes from the caller (which reads it from the session).
 */
class UsageStats extends BaseModel
{
    /** @return array{panels:array,orders:array,messages:array,refills:array} */
    public static function forTenant(int $tenantId): array
    {
        $limits = self::limits($tenantId);

        return [
            'panels'   => self::meter(self::countPanels($tenantId), (int) $limits['max_panels']),
            'orders'   => self::meter(self::countOrdersThisMonth($tenantId), (int) $limits['max_orders_monthly']),
            'messages' => self::meter(self::countMessagesThisMonth($tenantId), (int) $limits['max_messages_monthly']),
            'refills'  => self::meter(self::countRefillsThisMonth($tenantId), (int) $limits['max_refills_monthly']),
        ];
    }

    /** Build a meter: used, max, percentage, and a colour band for the bar. */
    private static function meter(int $used, int $max): array
    {
        $max = max(1, $max);
        $pct = (int) min(100, round($used / $max * 100));
        $band = $pct >= 100 ? 'danger' : ($pct >= 80 ? 'orange' : 'green');

        return ['used' => $used, 'max' => $max, 'pct' => $pct, 'band' => $band];
    }

    /**
     * Largest plan limits across the tenant's active subscriptions, falling
     * back to the order_bot catalogue plan, then to hard defaults.
     */
    private static function limits(int $tenantId): array
    {
        $cols = 'MAX(p.max_panels) AS max_panels, MAX(p.max_orders_monthly) AS max_orders_monthly,
                 MAX(p.max_messages_monthly) AS max_messages_monthly, MAX(p.max_refills_monthly) AS max_refills_monthly';

        $stmt = self::db()->prepare(
            "SELECT {$cols}
             FROM subscriptions s JOIN plans p ON p.id = s.plan_id
             WHERE s.tenant_id = ? AND s.status = 'active' AND (s.ends_at IS NULL OR s.ends_at > NOW())"
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch();

        if ($row && $row['max_orders_monthly'] !== null) {
            return $row;
        }

        // Fallback: the order_bot catalogue plan (the one that governs order/message caps).
        $stmt = self::db()->query(
            "SELECT max_panels, max_orders_monthly, max_messages_monthly, max_refills_monthly
             FROM plans WHERE service_key = 'order_bot' ORDER BY sort_order LIMIT 1"
        );
        $row = $stmt->fetch();

        return $row ?: ['max_panels' => 3, 'max_orders_monthly' => 1000, 'max_messages_monthly' => 5000, 'max_refills_monthly' => 100];
    }

    private static function countPanels(int $tenantId): int
    {
        $stmt = self::db()->prepare('SELECT COUNT(*) FROM tenant_panels WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn();
    }

    private static function countOrdersThisMonth(int $tenantId): int
    {
        $stmt = self::db()->prepare(
            "SELECT COUNT(*) FROM bot_orders
             WHERE tenant_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
        );
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn();
    }

    private static function countMessagesThisMonth(int $tenantId): int
    {
        $stmt = self::db()->prepare(
            "SELECT COUNT(*) FROM bot_messages
             WHERE tenant_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
        );
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn();
    }

    private static function countRefillsThisMonth(int $tenantId): int
    {
        $stmt = self::db()->prepare(
            "SELECT COUNT(*) FROM bot_orders
             WHERE tenant_id = ? AND refill_status IS NOT NULL
               AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
        );
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /** Small live counters for the "TAKWIMU ZA SASA" strip. */
    public static function todayCounts(int $tenantId): array
    {
        $db = self::db();
        $today = "DATE(created_at) = CURDATE()";

        $ordersToday = $db->prepare("SELECT COUNT(*) FROM bot_orders WHERE tenant_id = ? AND {$today}");
        $ordersToday->execute([$tenantId]);

        $completed = $db->prepare("SELECT COUNT(*) FROM bot_orders WHERE tenant_id = ? AND LOWER(status) LIKE '%complet%'");
        $completed->execute([$tenantId]);

        $inProgress = $db->prepare("SELECT COUNT(*) FROM bot_orders WHERE tenant_id = ? AND LOWER(status) LIKE '%progress%'");
        $inProgress->execute([$tenantId]);

        $refills = $db->prepare("SELECT COUNT(*) FROM bot_orders WHERE tenant_id = ? AND refill_status IS NOT NULL AND {$today}");
        $refills->execute([$tenantId]);

        return [
            'today'      => (int) $ordersToday->fetchColumn(),
            'completed'  => (int) $completed->fetchColumn(),
            'inprogress' => (int) $inProgress->fetchColumn(),
            'refills'    => (int) $refills->fetchColumn(),
        ];
    }

    /** Orders per day for the last 30 days, as [ ['d'=>'MM-DD','n'=>N], ... ]. */
    public static function orders30d(int $tenantId): array
    {
        $stmt = self::db()->prepare(
            "SELECT DATE_FORMAT(created_at, '%m-%d') AS d, COUNT(*) AS n
             FROM bot_orders
             WHERE tenant_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
             GROUP BY DATE(created_at) ORDER BY DATE(created_at)"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll();
    }
}
