<?php

/**
 * Bootstrap for all public pages: loads config, lib, models, helpers,
 * connects the DB, initialises Crypto + Lang. Include this first.
 */

require_once __DIR__ . '/../../app/lib/DB.php';
require_once __DIR__ . '/../../app/lib/Crypto.php';
require_once __DIR__ . '/../../app/models/BaseModel.php';
require_once __DIR__ . '/../../app/models/Tenant.php';
require_once __DIR__ . '/../../app/models/Plan.php';
require_once __DIR__ . '/../../app/models/Subscription.php';
require_once __DIR__ . '/../../app/models/SubscriptionPayment.php';
require_once __DIR__ . '/../../app/helpers/TenantAuth.php';
require_once __DIR__ . '/../../app/helpers/Csrf.php';
require_once __DIR__ . '/../../app/helpers/Lang.php';
require_once __DIR__ . '/../../app/helpers/RateLimit.php';
require_once __DIR__ . '/../../app/services/SubscriptionBilling.php';
require_once __DIR__ . '/../../app/models/TenantPanel.php';
require_once __DIR__ . '/../../app/services/PanelDetector.php';
require_once __DIR__ . '/../../app/models/TenantWhatsApp.php';
require_once __DIR__ . '/../../app/models/BotOrder.php';
require_once __DIR__ . '/../../app/models/BotService.php';
require_once __DIR__ . '/../../app/models/PlatformNumber.php';
require_once __DIR__ . '/../../app/models/NumberRental.php';
require_once __DIR__ . '/../../app/models/GuaranteeRule.php';
require_once __DIR__ . '/../../app/services/GuaranteeMatcher.php';
require_once __DIR__ . '/../../app/models/Ticket.php';
require_once __DIR__ . '/../../app/models/TicketMessage.php';
require_once __DIR__ . '/../../app/models/TenantAi.php';
require_once __DIR__ . '/../../app/services/TicketService.php';
require_once __DIR__ . '/../../app/models/ResponseTemplate.php';
require_once __DIR__ . '/../../app/models/BotSettings.php';
require_once __DIR__ . '/../../app/services/ReferralReward.php';
require_once __DIR__ . '/../../app/models/Broadcast.php';
require_once __DIR__ . '/../../app/models/TenantTelegram.php';
require_once __DIR__ . '/../../app/services/TelegramClient.php';
require_once __DIR__ . '/../../app/helpers/Money.php';
require_once __DIR__ . '/../../app/models/UsageStats.php';

$config = require __DIR__ . '/../../config/config.php';

// Error display: never leak stack traces / paths to users in production.
if (($config['app']['env'] ?? 'local') === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// Harden the session cookie before any session starts. Secure flag is enabled
// automatically when the request is over HTTPS.
$secureCookie = (($_SERVER['HTTPS'] ?? '') === 'on')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $secureCookie,
]);

DB::connect($config['db']);

if (!empty($config['app']['key'])) {
    Crypto::init($config['app']['key']);
}

TenantAuth::boot();

// Language: query/session first, then the logged-in tenant's preference, else EN.
$currentTenant = TenantAuth::check() ? TenantAuth::user() : null;
Lang::init($currentTenant['lang'] ?? null);

// Persist an explicit ?lang= switch onto the tenant profile.
if ($currentTenant !== null && isset($_GET['lang'])
    && in_array($_GET['lang'], Lang::SUPPORTED, true)
    && $_GET['lang'] !== $currentTenant['lang']) {
    Tenant::setLang((int) $currentTenant['id'], $_GET['lang']);
}
