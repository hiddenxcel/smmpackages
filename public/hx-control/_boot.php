<?php
/**
 * hx-control bootstrap — the super-admin area (secret URL). Loads everything
 * needed and starts the (separate) super-admin session.
 */

require_once __DIR__ . '/../../app/lib/DB.php';
require_once __DIR__ . '/../../app/lib/Crypto.php';
require_once __DIR__ . '/../../app/models/BaseModel.php';
require_once __DIR__ . '/../../app/models/Superadmin.php';
require_once __DIR__ . '/../../app/models/Tenant.php';
require_once __DIR__ . '/../../app/models/Plan.php';
require_once __DIR__ . '/../../app/models/Subscription.php';
require_once __DIR__ . '/../../app/models/SubscriptionPayment.php';
require_once __DIR__ . '/../../app/models/PlatformNumber.php';
require_once __DIR__ . '/../../app/models/ActivityLog.php';
require_once __DIR__ . '/../../app/helpers/SuperadminAuth.php';
require_once __DIR__ . '/../../app/helpers/Csrf.php';
require_once __DIR__ . '/../../app/helpers/RateLimit.php';
require_once __DIR__ . '/../../app/services/AdminStats.php';
require_once __DIR__ . '/../../app/helpers/Money.php';

$config = require __DIR__ . '/../../config/config.php';

if (($config['app']['env'] ?? 'local') === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

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
SuperadminAuth::boot();

$serviceLabels = ['order_bot' => 'Order Bot', 'support_bot' => 'Support Bot', 'ai_tickets' => 'AI Tickets', 'number_rental' => 'Number Rental'];
