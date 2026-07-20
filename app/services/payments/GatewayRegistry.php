<?php

/**
 * GatewayRegistry — the single source of truth for every payment gateway a
 * tenant can offer their customers.
 *
 * Each entry declares:
 *   label   : human name shown in the UI
 *   type    : mobile | crypto | card  (drives the icon and whether a phone is asked)
 *   ready   : true = wired end-to-end (a client exists and WalletTopup can drive it);
 *             false = selectable in the UI so a tenant can store keys, but live
 *             payment is not built yet (shown as "Coming soon").
 *   fields  : the credential inputs to show, keyed by the column they map to.
 *             Every gateway stores at most two secrets in tenant_payment_gateways
 *             (api_key_enc, webhook_secret_enc); a field's `store` says which.
 *
 * Adding a new live gateway later = flip ready=>true and add a client + a case
 * in WalletTopup::client() and a webhook. Nothing else in the UI changes.
 */
class GatewayRegistry
{
    public const GATEWAYS = [
        // ---- Live (client exists, end-to-end) ----
        'snippe' => [
            'label' => 'Snippe (Mobile Money)',
            'type' => 'mobile',
            'ready' => true,
            'fields' => [
                ['name' => 'api_key', 'label' => 'API key', 'store' => 'api_key'],
                ['name' => 'webhook_secret', 'label' => 'Webhook secret', 'store' => 'webhook_secret'],
            ],
        ],
        'nowpayments' => [
            'label' => 'NOWPayments (USDT / Crypto)',
            'type' => 'crypto',
            'ready' => true,
            'fields' => [
                ['name' => 'api_key', 'label' => 'API key', 'store' => 'api_key'],
                ['name' => 'webhook_secret', 'label' => 'IPN secret', 'store' => 'webhook_secret'],
            ],
        ],
        'binance' => [
            'label' => 'Binance (USDT — Internal Transfer)',
            'type' => 'crypto',
            'ready' => true,
            // Manual/verify: no webhook. The customer sends USDT to the tenant's
            // Binance ID and reports the Binance Order ID, verified via the Spot
            // API. Needs a Binance ID (stored in BotSettings shop.binance_pay_id).
            'verify' => true,
            'fields' => [
                ['name' => 'api_key', 'label' => 'Binance Spot API key (read)', 'store' => 'api_key'],
                ['name' => 'webhook_secret', 'label' => 'Binance Spot API secret', 'store' => 'webhook_secret'],
            ],
        ],

        'cryptomus' => [
            'label' => 'Cryptomus (USDT / Crypto)',
            'type' => 'crypto',
            'ready' => true,
            // Cryptomus needs a Merchant UUID + Payment API key. We reuse the two
            // secret slots: api_key = Payment API key, webhook_secret = Merchant UUID.
            'fields' => [
                ['name' => 'api_key', 'label' => 'Payment API key', 'store' => 'api_key'],
                ['name' => 'webhook_secret', 'label' => 'Merchant UUID', 'store' => 'webhook_secret'],
            ],
        ],

        // ---- Selectable now, wired later ----
        'flutterwave' => [
            'label' => 'Flutterwave (Cards / Mobile)',
            'type' => 'card',
            'ready' => false,
            'fields' => [
                ['name' => 'api_key', 'label' => 'Secret key', 'store' => 'api_key'],
                ['name' => 'webhook_secret', 'label' => 'Webhook hash', 'store' => 'webhook_secret'],
            ],
        ],
        'stripe' => [
            'label' => 'Stripe (Cards)',
            'type' => 'card',
            'ready' => false,
            'fields' => [
                ['name' => 'api_key', 'label' => 'Secret key', 'store' => 'api_key'],
                ['name' => 'webhook_secret', 'label' => 'Webhook signing secret', 'store' => 'webhook_secret'],
            ],
        ],
        'paypal' => [
            'label' => 'PayPal',
            'type' => 'card',
            'ready' => false,
            'fields' => [
                ['name' => 'api_key', 'label' => 'Client ID', 'store' => 'api_key'],
                ['name' => 'webhook_secret', 'label' => 'Secret', 'store' => 'webhook_secret'],
            ],
        ],
        'pesapal' => [
            'label' => 'Pesapal (East Africa)',
            'type' => 'card',
            'ready' => false,
            'fields' => [
                ['name' => 'api_key', 'label' => 'Consumer key', 'store' => 'api_key'],
                ['name' => 'webhook_secret', 'label' => 'Consumer secret', 'store' => 'webhook_secret'],
            ],
        ],
        'zenopay' => [
            'label' => 'ZenoPay (Mobile Money)',
            'type' => 'mobile',
            'ready' => false,
            'fields' => [
                ['name' => 'api_key', 'label' => 'API key', 'store' => 'api_key'],
                ['name' => 'webhook_secret', 'label' => 'Webhook secret', 'store' => 'webhook_secret'],
            ],
        ],
        'momopay' => [
            'label' => 'MoMoPay (Mobile Money)',
            'type' => 'mobile',
            'ready' => false,
            'fields' => [
                ['name' => 'api_key', 'label' => 'API key', 'store' => 'api_key'],
                ['name' => 'webhook_secret', 'label' => 'Webhook secret', 'store' => 'webhook_secret'],
            ],
        ],
    ];

    public static function all(): array
    {
        return self::GATEWAYS;
    }

    public static function get(string $code): ?array
    {
        return self::GATEWAYS[$code] ?? null;
    }

    public static function exists(string $code): bool
    {
        return isset(self::GATEWAYS[$code]);
    }

    public static function isReady(string $code): bool
    {
        return !empty(self::GATEWAYS[$code]['ready']);
    }

    /** True if this gateway is manual/verify (no webhook; payer reports an ID). */
    public static function isVerify(string $code): bool
    {
        return !empty(self::GATEWAYS[$code]['verify']);
    }

    public static function label(string $code): string
    {
        return self::GATEWAYS[$code]['label'] ?? ucfirst($code);
    }

    /** True if this gateway collects a payer phone (mobile-money style). */
    public static function needsPhone(string $code): bool
    {
        return (self::GATEWAYS[$code]['type'] ?? '') === 'mobile';
    }
}
