<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * BotSettings — per (tenant, bot_type) JSON settings: command toggles, spam
 * protection, response options, staff numbers. Merged over sensible defaults
 * so a missing key always resolves.
 */
class BotSettings extends BaseModel
{
    public const DEFAULTS = [
        'commands' => [
            'refill' => true,
            'status' => true,
            'cancel' => true,
            'speedup' => false,
        ],
        'spam' => [
            'enabled' => true,
            'repeat_threshold' => 3,   // identical/rapid messages
            'window_minutes' => 5,
            'disable_minutes' => 60,   // how long a flagged number is muted
        ],
        'response' => [
            'show_provider_name' => false,
            'detailed_status' => true,
        ],
        'staff' => [
            'numbers' => [],           // phone numbers that bypass limits
        ],
        // Shop settings for the wallet-based Order Bot: the tenant's currency
        // (shown to their customers), wallet top-up floor, and referral reward.
        'shop' => [
            'currency' => 'USD',       // tenant picks their own (USD, TZS, KES, …)
            'min_topup' => 1,          // minimum wallet top-up in that currency
            'referral_percent' => 0,   // % of a referred customer's first deposit
            'test_numbers' => [],      // phones that may try the bot while in SANDBOX
        ],
    ];

    public static function get(int $tenantId, string $botType): array
    {
        $stmt = self::db()->prepare(
            'SELECT settings FROM tenant_bot_settings WHERE tenant_id = ? AND bot_type = ?'
        );
        $stmt->execute([$tenantId, $botType]);
        $row = $stmt->fetch();

        $stored = ($row && $row['settings']) ? json_decode($row['settings'], true) : [];

        return self::mergeDefaults(self::DEFAULTS, is_array($stored) ? $stored : []);
    }

    public static function save(int $tenantId, string $botType, array $settings): void
    {
        $stmt = self::db()->prepare(
            "INSERT INTO tenant_bot_settings (tenant_id, bot_type, settings)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE settings = VALUES(settings)"
        );
        $stmt->execute([$tenantId, $botType, json_encode($settings)]);
    }

    public static function isCommandEnabled(int $tenantId, string $botType, string $command): bool
    {
        $s = self::get($tenantId, $botType);

        return (bool) ($s['commands'][$command] ?? false);
    }

    public static function isStaff(int $tenantId, string $botType, string $phone): bool
    {
        $s = self::get($tenantId, $botType);
        $numbers = array_map(fn ($n) => preg_replace('/\D/', '', (string) $n), $s['staff']['numbers'] ?? []);

        return in_array(preg_replace('/\D/', '', $phone), $numbers, true);
    }

    /**
     * Recursive merge: stored values override defaults, missing keys stay.
     * Associative sub-arrays merge recursively; list arrays (e.g. staff numbers)
     * are replaced wholesale by the stored value.
     */
    private static function mergeDefaults(array $defaults, array $stored): array
    {
        foreach ($defaults as $k => $v) {
            if (is_array($v) && self::isAssoc($v) && isset($stored[$k]) && is_array($stored[$k])) {
                $defaults[$k] = self::mergeDefaults($v, $stored[$k]);
            } elseif (array_key_exists($k, $stored)) {
                $defaults[$k] = $stored[$k];
            }
        }

        return $defaults;
    }

    /** True for associative arrays; false for list arrays (including empty). */
    private static function isAssoc(array $arr): bool
    {
        return $arr !== [] && array_keys($arr) !== range(0, count($arr) - 1);
    }
}
