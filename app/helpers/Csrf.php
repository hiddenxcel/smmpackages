<?php

/**
 * Csrf — per-session token for all POST forms.
 * Emit token() as a hidden field; validate with check() before processing POST.
 */
class Csrf
{
    private const SESSION_KEY = 'csrf_token';
    public const FIELD = '_csrf';

    private static function boot(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function token(): string
    {
        self::boot();

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /** Hidden input markup for forms. */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    /** Constant-time compare of a submitted token. */
    public static function check(?string $submitted): bool
    {
        self::boot();

        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($submitted) || $submitted === '') {
            return false;
        }

        return hash_equals($_SESSION[self::SESSION_KEY], $submitted);
    }

    /** Validate the POSTed token or kill the request. Call at the top of POST handlers. */
    public static function verify(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
            && !self::check($_POST[self::FIELD] ?? null)) {
            http_response_code(403);
            exit('Invalid or expired form token. Please go back and try again.');
        }
    }
}
