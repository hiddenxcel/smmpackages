<?php

/**
 * BotLang — per-customer translations for the WhatsApp/Telegram bot
 * conversation. Ported from kuzapanel-bot and made multi-tenant.
 *
 * This is SEPARATE from Lang.php (the dashboard/session locale). The bot has no
 * browser session: the language is resolved from the customer's saved
 * bot_customers.lang, falling back to the tenant's chosen shop.lang, then EN.
 * So callers pass the resolved locale straight in — nothing here reads $_SESSION.
 *
 * Strings live in app/lang/bot/{locale}.php. Placeholders use {name} style and
 * are replaced via strtr with the $vars map passed to t().
 */
class BotLang
{
    public const DEFAULT = 'en';
    public const SUPPORTED = ['en', 'fr', 'sw', 'tr', 'hi'];

    /** Human names for each locale, shown in the in-bot language chooser. */
    public const NAMES = [
        'en' => 'English',
        'fr' => 'Français',
        'sw' => 'Kiswahili',
        'tr' => 'Türkçe',
        'hi' => 'हिन्दी',
    ];

    /** @var array<string, array<string, string>> loaded string tables per locale */
    private static array $strings = [];

    /**
     * Translate $key in $lang, substituting {placeholders} from $vars.
     * Missing key falls back to the DEFAULT locale, then to the key itself.
     */
    public static function t(string $lang, string $key, array $vars = []): string
    {
        $lang = self::normalize($lang);
        $text = self::strings($lang)[$key] ?? self::strings(self::DEFAULT)[$key] ?? $key;

        return $vars === [] ? $text : strtr($text, self::bracify($vars));
    }

    /** Coerce any input to a supported locale (defaults to EN). */
    public static function normalize(?string $lang): string
    {
        return in_array($lang, self::SUPPORTED, true) ? $lang : self::DEFAULT;
    }

    /**
     * Resolve the locale for a message: the customer's saved lang if set and
     * supported, otherwise the tenant's shop.lang default, otherwise EN.
     */
    public static function resolve(?array $customer, ?string $tenantDefault): string
    {
        $custLang = $customer['lang'] ?? null;
        if (in_array($custLang, self::SUPPORTED, true)) {
            return $custLang;
        }

        return self::normalize($tenantDefault);
    }

    private static function strings(string $lang): array
    {
        if (!isset(self::$strings[$lang])) {
            $path = __DIR__ . "/../lang/bot/{$lang}.php";
            self::$strings[$lang] = is_file($path) ? (array) require $path : [];
        }

        return self::$strings[$lang];
    }

    /** Let callers pass either {key} or key; normalise to {key} for strtr. */
    private static function bracify(array $vars): array
    {
        $out = [];
        foreach ($vars as $k => $v) {
            $key = str_starts_with((string) $k, '{') ? (string) $k : '{' . $k . '}';
            $out[$key] = (string) $v;
        }

        return $out;
    }
}
