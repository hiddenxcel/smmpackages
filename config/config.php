<?php

require_once __DIR__ . '/../app/lib/Env.php';

Env::load(__DIR__ . '/.env');

return [
    'app' => [
        'env'  => Env::get('APP_ENV', 'local'),
        'url'  => Env::get('APP_URL', ''),
        'key'  => Env::get('APP_KEY', ''),
        'name' => Env::get('APP_NAME', 'SMM Packages'),
    ],

    'db' => [
        'host' => Env::get('DB_HOST', 'localhost'),
        'name' => Env::get('DB_NAME', 'smmpackages'),
        'user' => Env::get('DB_USER', 'root'),
        'pass' => Env::get('DB_PASS', ''),
    ],

    // SaaS billing (tenant -> HiddenXcel). International-first: USDT + mobile money.
    'billing' => [
        'nowpayments' => [
            'api_key'      => Env::get('NOWPAYMENTS_API_KEY', ''),
            'ipn_secret'   => Env::get('NOWPAYMENTS_IPN_SECRET', ''),
            'pay_currency' => Env::get('NOWPAYMENTS_PAY_CURRENCY', 'usdttrc20'),
        ],
        'binance' => [
            // Spot API key (read only) for the "internal transfer" verify flow.
            'api_key'    => Env::get('BINANCE_PAY_API_KEY', ''),
            'api_secret' => Env::get('BINANCE_PAY_API_SECRET', ''),
            // The merchant Binance ID payers send USDT to (shown on checkout).
            'pay_id'     => Env::get('BINANCE_PAY_ID', ''),
        ],
        'snippe' => [
            'api_key'        => Env::get('SNIPPE_API_KEY', ''),
            'webhook_secret' => Env::get('SNIPPE_WEBHOOK_SECRET', ''),
            // Snippe is Tanzania mobile money — it charges in TZS, not USD.
            // Plans are priced in USD, so convert at this rate for Snippe only.
            'usd_to_tzs'     => (float) Env::get('SNIPPE_USD_TO_TZS', '2600'),
        ],
        'cryptomus' => [
            // Payment API key + Merchant UUID (no KYB). Hosted crypto checkout.
            'api_key'  => Env::get('CRYPTOMUS_API_KEY', ''),
            'merchant' => Env::get('CRYPTOMUS_MERCHANT_ID', ''),
        ],
        'heleket' => [
            // Same contract as Cryptomus (no KYB). Hosted crypto checkout.
            'api_key'  => Env::get('HELEKET_API_KEY', ''),
            'merchant' => Env::get('HELEKET_MERCHANT_ID', ''),
        ],
    ],

    // Meta app secret — used to verify X-Hub-Signature-256 on WhatsApp webhooks.
    'meta' => [
        'app_secret'   => Env::get('META_APP_SECRET', ''),
        'verify_token' => Env::get('META_VERIFY_TOKEN', ''),
    ],

    'links' => [
        'whatsapp_url' => Env::get('SUPPORT_WHATSAPP_URL', '#'),
    ],
];
