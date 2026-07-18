<?php

/**
 * Lang — bilingual+ UI translation. Default EN (international-first), plus FR + SW.
 *
 * Resolution order for the active language:
 *   1. ?lang=xx in the query (persisted to session, and to the tenant row if logged in)
 *   2. session 'lang'
 *   3. logged-in tenant's stored lang
 *   4. 'en' (default)
 *
 * Strings live in app/lang/{en,fr,sw}.php. Missing keys fall back to EN, then to the key.
 */
class Lang
{
    public const SUPPORTED = ['en', 'fr', 'sw'];
    public const DEFAULT = 'en';

    private static ?string $active = null;
    private static array $strings = [];
    private static array $fallback = [];

    private static function boot(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Resolve + load the active language. Call once per request (e.g. in the header).
     * $tenantLang is the stored lang of a logged-in tenant, if any.
     */
    public static function init(?string $tenantLang = null): string
    {
        self::boot();

        $lang = null;

        if (isset($_GET['lang']) && in_array($_GET['lang'], self::SUPPORTED, true)) {
            $lang = $_GET['lang'];
            $_SESSION['lang'] = $lang;
        } elseif (isset($_SESSION['lang']) && in_array($_SESSION['lang'], self::SUPPORTED, true)) {
            $lang = $_SESSION['lang'];
        } elseif ($tenantLang !== null && in_array($tenantLang, self::SUPPORTED, true)) {
            $lang = $tenantLang;
        }

        $lang = $lang ?? self::DEFAULT;
        self::$active = $lang;

        self::$fallback = self::loadFile(self::DEFAULT);
        self::$strings = $lang === self::DEFAULT ? self::$fallback : self::loadFile($lang);

        return $lang;
    }

    private static function loadFile(string $lang): array
    {
        $path = __DIR__ . '/../lang/' . $lang . '.php';

        return is_file($path) ? (array) require $path : [];
    }

    public static function current(): string
    {
        return self::$active ?? self::DEFAULT;
    }

    /** Translate a key, with {placeholders}. Falls back EN -> key. */
    public static function t(string $key, array $vars = []): string
    {
        if (self::$active === null) {
            self::init();
        }

        $text = self::$strings[$key] ?? self::$fallback[$key] ?? $key;

        foreach ($vars as $k => $v) {
            $text = str_replace('{' . $k . '}', (string) $v, $text);
        }

        return $text;
    }

    /** Build a URL that switches language, preserving the current path + query. */
    public static function switchUrl(string $lang): string
    {
        $params = $_GET;
        $params['lang'] = $lang;

        return strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($params);
    }
}

/** Short global helpers for templates. */
function __(string $key, array $vars = []): string
{
    return Lang::t($key, $vars);
}

function e(string $key, array $vars = []): void
{
    echo htmlspecialchars(Lang::t($key, $vars), ENT_QUOTES);
}
