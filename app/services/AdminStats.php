<?php

require_once __DIR__ . '/../lib/DB.php';

/**
 * AdminStats — platform-wide metrics for the super-admin (MRR, tenants, growth).
 * MRR is computed from active subscriptions, normalising each to a monthly
 * figure from its linked plan (yearly plans -> price_yearly / 12).
 */
class AdminStats
{
    /** Monthly Recurring Revenue across all active subscriptions (USD). */
    public static function mrr(): float
    {
        // Each active subscription contributes its plan's monthly price. We can't
        // tell 1- vs 12-month term from the subscription alone, so we use the
        // plan's monthly price as the normalised recurring figure.
        $stmt = DB::conn()->query(
            "SELECT COALESCE(SUM(p.price_monthly), 0) AS mrr
             FROM subscriptions s
             JOIN plans p ON p.id = s.plan_id
             WHERE s.status = 'active' AND (s.ends_at IS NULL OR s.ends_at > NOW())"
        );

        return (float) $stmt->fetchColumn();
    }

    /** Total confirmed revenue to date (USD). */
    public static function totalRevenue(): float
    {
        $stmt = DB::conn()->query(
            "SELECT COALESCE(SUM(amount), 0) FROM subscription_payments WHERE status = 'success'"
        );

        return (float) $stmt->fetchColumn();
    }

    public static function tenantCounts(): array
    {
        $db = DB::conn();
        $total = (int) $db->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
        $suspended = (int) $db->query("SELECT COUNT(*) FROM tenants WHERE status = 'suspended'")->fetchColumn();
        $withActive = (int) $db->query(
            "SELECT COUNT(DISTINCT tenant_id) FROM subscriptions
             WHERE status = 'active' AND (ends_at IS NULL OR ends_at > NOW())"
        )->fetchColumn();

        return [
            'total' => $total,
            'suspended' => $suspended,
            'active_paying' => $withActive,
            'free' => $total - $withActive,
        ];
    }

    /** Active subscription count per service_key. */
    public static function activeByService(): array
    {
        $stmt = DB::conn()->query(
            "SELECT service_key, COUNT(*) AS n FROM subscriptions
             WHERE status = 'active' AND (ends_at IS NULL OR ends_at > NOW())
             GROUP BY service_key"
        );
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['service_key']] = (int) $row['n'];
        }

        return $out;
    }

    /** New tenants per month for the last N months (for a growth chart). */
    public static function tenantGrowth(int $months = 6): array
    {
        $stmt = DB::conn()->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS n
             FROM tenants
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             GROUP BY ym ORDER BY ym"
        );
        $stmt->execute([$months]);

        return $stmt->fetchAll();
    }

    /** Revenue this calendar month (USD). */
    public static function revenueThisMonth(): float
    {
        $stmt = DB::conn()->query(
            "SELECT COALESCE(SUM(amount), 0) FROM subscription_payments
             WHERE status = 'success' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
        );

        return (float) $stmt->fetchColumn();
    }
}
