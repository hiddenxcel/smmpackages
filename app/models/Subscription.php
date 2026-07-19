<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * Subscription — a la carte: each row is ONE service_key for ONE tenant,
 * with its own starts_at / ends_at / status. A tenant may hold several.
 *
 * The service gate (isServiceActive) is the single source of truth every
 * bot run and dashboard lock check goes through.
 */
class Subscription extends BaseModel
{
    public const SERVICES = ['order_bot', 'support_bot', 'ai_tickets', 'number_rental'];

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM subscriptions WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    /** All subscriptions for a tenant (any status), newest first. */
    public static function forTenant(int $tenantId): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM subscriptions WHERE tenant_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll();
    }

    /**
     * THE GATE. Is a given service currently active for this tenant?
     * Active = status 'active' AND (ends_at is NULL/open OR still in the future).
     */
    public static function isServiceActive(int $tenantId, string $serviceKey): bool
    {
        $stmt = self::db()->prepare(
            "SELECT 1 FROM subscriptions
             WHERE tenant_id = ? AND service_key = ? AND status = 'active'
               AND (ends_at IS NULL OR ends_at > NOW())
             LIMIT 1"
        );
        $stmt->execute([$tenantId, $serviceKey]);

        return $stmt->fetch() !== false;
    }

    /** The active subscription row for a service, or null (used to show days left). */
    public static function activeForService(int $tenantId, string $serviceKey): ?array
    {
        $stmt = self::db()->prepare(
            "SELECT * FROM subscriptions
             WHERE tenant_id = ? AND service_key = ? AND status = 'active'
               AND (ends_at IS NULL OR ends_at > NOW())
             ORDER BY ends_at DESC LIMIT 1"
        );
        $stmt->execute([$tenantId, $serviceKey]);

        return $stmt->fetch() ?: null;
    }

    /** Map serviceKey => bool active, for the whole dashboard in one pass. */
    public static function statusMap(int $tenantId): array
    {
        $map = [];
        foreach (self::SERVICES as $service) {
            $map[$service] = self::isServiceActive($tenantId, $service);
        }

        return $map;
    }

    /**
     * True if a service is in SANDBOX (reseller is setting it up but hasn't gone
     * live/paid). Sandbox is NOT live — the public gate (isServiceActive) still
     * returns false — but dashboard setup pages and self-test are unlocked.
     */
    public static function isSandbox(int $tenantId, string $serviceKey): bool
    {
        $stmt = self::db()->prepare(
            "SELECT 1 FROM subscriptions
             WHERE tenant_id = ? AND service_key = ? AND status = 'sandbox' LIMIT 1"
        );
        $stmt->execute([$tenantId, $serviceKey]);

        return $stmt->fetch() !== false;
    }

    /** serviceKey => 'active' | 'sandbox' | 'locked' — the tri-state dashboards use. */
    public static function stateMap(int $tenantId): array
    {
        $map = [];
        foreach (self::SERVICES as $service) {
            if (self::isServiceActive($tenantId, $service)) {
                $map[$service] = 'active';
            } elseif (self::isSandbox($tenantId, $service)) {
                $map[$service] = 'sandbox';
            } else {
                $map[$service] = 'locked';
            }
        }

        return $map;
    }

    /** A service is "usable" (setup pages + self-test allowed) if active OR sandbox. */
    public static function isUsable(int $tenantId, string $serviceKey): bool
    {
        return self::isServiceActive($tenantId, $serviceKey)
            || self::isSandbox($tenantId, $serviceKey);
    }

    /**
     * Give a brand-new tenant a sandbox row for every service, so they land in a
     * fully explorable dashboard for free. Idempotent: skips services that already
     * have any subscription row.
     */
    public static function provisionSandbox(int $tenantId): void
    {
        foreach (self::SERVICES as $service) {
            $existing = self::db()->prepare('SELECT 1 FROM subscriptions WHERE tenant_id = ? AND service_key = ? LIMIT 1');
            $existing->execute([$tenantId, $service]);
            if ($existing->fetch() !== false) {
                continue;
            }
            $plan = Plan::forService($service);
            self::create($tenantId, $service, $plan['id'] ?? null, 'sandbox');
        }
    }

    /**
     * Flip a service from sandbox to active (called after a successful "Go Live"
     * payment). Returns true if a sandbox row was promoted.
     */
    public static function goLive(int $tenantId, string $serviceKey, int $months = 1): bool
    {
        $stmt = self::db()->prepare(
            "SELECT id FROM subscriptions WHERE tenant_id = ? AND service_key = ? AND status = 'sandbox' LIMIT 1"
        );
        $stmt->execute([$tenantId, $serviceKey]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            return false;
        }

        return self::activate((int) $id, $months);
    }

    /** Whole days left on an active subscription, or null if open/none. */
    public static function daysLeft(int $tenantId, string $serviceKey): ?int
    {
        $sub = self::activeForService($tenantId, $serviceKey);
        if ($sub === null || empty($sub['ends_at'])) {
            return null;
        }

        $diff = strtotime($sub['ends_at']) - time();

        return max(0, (int) floor($diff / 86400));
    }

    /**
     * Reserve a pending row for checkout. If the service is in SANDBOX, flip that
     * existing row to 'pending' and reuse it (so paying promotes it to active,
     * with no orphaned sandbox row). Otherwise create a fresh pending row.
     */
    public static function reservePending(int $tenantId, string $serviceKey, ?int $planId = null): int
    {
        $stmt = self::db()->prepare(
            "SELECT id FROM subscriptions WHERE tenant_id = ? AND service_key = ? AND status = 'sandbox' LIMIT 1"
        );
        $stmt->execute([$tenantId, $serviceKey]);
        $id = $stmt->fetchColumn();

        if ($id !== false) {
            self::db()->prepare("UPDATE subscriptions SET status = 'pending', plan_id = COALESCE(?, plan_id) WHERE id = ?")
                ->execute([$planId, (int) $id]);

            return (int) $id;
        }

        return self::create($tenantId, $serviceKey, $planId, 'pending');
    }

    public static function create(int $tenantId, string $serviceKey, ?int $planId = null, string $status = 'pending'): int
    {
        $stmt = self::db()->prepare(
            'INSERT INTO subscriptions (tenant_id, plan_id, service_key, status)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$tenantId, $planId, $serviceKey, $status]);

        return (int) self::db()->lastInsertId();
    }

    /** Activate (or extend) a subscription by a number of months from now. */
    public static function activate(int $id, int $months = 1): bool
    {
        $stmt = self::db()->prepare(
            "UPDATE subscriptions
             SET status = 'active',
                 starts_at = COALESCE(starts_at, NOW()),
                 ends_at = DATE_ADD(GREATEST(COALESCE(ends_at, NOW()), NOW()), INTERVAL ? MONTH)
             WHERE id = ?"
        );

        return $stmt->execute([$months, $id]);
    }

    /** Cron: mark active subscriptions whose ends_at has passed as expired. */
    public static function expireOverdue(): int
    {
        $stmt = self::db()->query(
            "UPDATE subscriptions
             SET status = 'expired'
             WHERE status = 'active' AND ends_at IS NOT NULL AND ends_at <= NOW()"
        );

        return $stmt->rowCount();
    }
}
