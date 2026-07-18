<?php

require_once __DIR__ . '/../models/Tenant.php';

/**
 * TenantAuth — session-based auth for tenants.
 *
 * SECURITY: the current tenant_id is read ONLY from the session (never from
 * request input). Every tenant-scoped query keys on TenantAuth::id().
 */
class TenantAuth
{
    private const SESSION_KEY = 'tenant_id';

    public static function boot(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /** Log a tenant in: regenerate the session id, then bind the tenant. */
    public static function login(int $tenantId): void
    {
        self::boot();
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = $tenantId;
    }

    public static function logout(): void
    {
        self::boot();
        unset($_SESSION[self::SESSION_KEY]);
        session_regenerate_id(true);
    }

    public static function check(): bool
    {
        self::boot();

        return isset($_SESSION[self::SESSION_KEY]);
    }

    /** Current tenant id from session, or null. */
    public static function id(): ?int
    {
        self::boot();

        return isset($_SESSION[self::SESSION_KEY]) ? (int) $_SESSION[self::SESSION_KEY] : null;
    }

    /** Current tenant row, or null. Also logs out if the tenant is suspended/gone. */
    public static function user(): ?array
    {
        $id = self::id();
        if ($id === null) {
            return null;
        }

        $tenant = Tenant::find($id);
        if ($tenant === null || $tenant['status'] === 'suspended') {
            self::logout();

            return null;
        }

        return $tenant;
    }

    /** Guard a dashboard page: redirect to login if not authenticated. */
    public static function require(string $loginUrl = 'login.php'): array
    {
        $tenant = self::user();
        if ($tenant === null) {
            header('Location: ' . $loginUrl);
            exit;
        }

        return $tenant;
    }

    /** Redirect already-authenticated tenants away from login/register. */
    public static function redirectIfAuthed(string $dashboardUrl = 'index.php'): void
    {
        if (self::check()) {
            header('Location: ' . $dashboardUrl);
            exit;
        }
    }
}
