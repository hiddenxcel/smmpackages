<?php

require_once __DIR__ . '/../models/Superadmin.php';

/**
 * SuperadminAuth — session auth for the platform owner (HiddenXcel).
 * Uses a distinct session key so a tenant login can never be mistaken for a
 * super-admin login. Lives behind the secret hx-control URL.
 */
class SuperadminAuth
{
    private const SESSION_KEY = 'superadmin_id';

    public static function boot(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(int $adminId): void
    {
        self::boot();
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = $adminId;
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

    public static function id(): ?int
    {
        self::boot();

        return isset($_SESSION[self::SESSION_KEY]) ? (int) $_SESSION[self::SESSION_KEY] : null;
    }

    public static function user(): ?array
    {
        $id = self::id();

        return $id !== null ? Superadmin::find($id) : null;
    }

    public static function require(string $loginUrl = 'login.php'): array
    {
        $admin = self::user();
        if ($admin === null) {
            header('Location: ' . $loginUrl);
            exit;
        }

        return $admin;
    }

    public static function redirectIfAuthed(string $dashboardUrl = 'index.php'): void
    {
        if (self::check()) {
            header('Location: ' . $dashboardUrl);
            exit;
        }
    }
}
