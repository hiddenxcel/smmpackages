<?php
/**
 * Telegram webhook (Telegram -> TelegramRouter).
 * Telegram posts JSON updates to /webhooks/telegram.php?s=<secret>.
 * The secret is the per-tenant routing key (never trust the token in payload).
 */

require_once __DIR__ . '/../../app/lib/DB.php';
require_once __DIR__ . '/../../app/lib/Crypto.php';
require_once __DIR__ . '/../../app/services/bots/TelegramRouter.php';

$config = require __DIR__ . '/../../config/config.php';
DB::connect($config['db']);
if (!empty($config['app']['key'])) {
    Crypto::init($config['app']['key']);
}

$secret = $_GET['s'] ?? '';
if ($secret === '' || !ctype_xdigit($secret)) {
    http_response_code(400);
    exit('bad secret');
}

$body = file_get_contents('php://input') ?: '';
$update = json_decode($body, true);
if (!is_array($update)) {
    http_response_code(400);
    exit('bad payload');
}

try {
    $status = TelegramRouter::route($secret, $update);
} catch (\Throwable $e) {
    error_log('[telegram webhook] ' . $e->getMessage());
    $status = 'error';
}

// Telegram only needs a 200; the status is for our logs.
http_response_code(200);
header('Content-Type: text/plain');
echo $status;
