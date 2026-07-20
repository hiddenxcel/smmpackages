<?php

require_once __DIR__ . '/../models/Subscription.php';

/**
 * ServiceGate — one place to decide what a tenant may do with a service's pages,
 * based on that service's subscription state.
 *
 *   active  → full access (view + write)
 *   sandbox → not paid yet; OPERATIONAL pages are blocked (Go Live to unlock),
 *             SETUP pages allow configuration (handled by those pages directly)
 *   locked  → was paid (expired/cancelled) or never subscribed → read-only + renew
 *
 * Operational pages (gateways, customers, orders) call this to gate themselves so
 * a link hidden from the sidebar can't simply be reached by URL.
 */
class ServiceGate
{
    /** @return 'active'|'sandbox'|'locked' */
    public static function state(int $tenantId, string $service): string
    {
        if (Subscription::isServiceActive($tenantId, $service)) {
            return 'active';
        }
        if (Subscription::isSandbox($tenantId, $service)) {
            return 'sandbox';
        }

        return 'locked';
    }

    public static function canWrite(int $tenantId, string $service): bool
    {
        return self::state($tenantId, $service) === 'active';
    }
}
