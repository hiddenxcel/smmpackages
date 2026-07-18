<?php
/**
 * WhatsApp Cloud API webhook (Meta -> BotRouter).
 *
 *  GET  : Meta verification handshake (hub.verify_token must match META_VERIFY_TOKEN).
 *  POST : inbound events. Verify X-Hub-Signature-256 (HMAC-SHA256 with app secret),
 *         then hand the payload to BotRouter. Always 200 quickly so Meta doesn't retry.
 */

require_once __DIR__ . '/../../app/lib/DB.php';
require_once __DIR__ . '/../../app/lib/Crypto.php';
require_once __DIR__ . '/../../app/services/bots/BotRouter.php';

$config = require __DIR__ . '/../../config/config.php';
DB::connect($config['db']);
if (!empty($config['app']['key'])) {
    Crypto::init($config['app']['key']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// --- GET: verification handshake ---
if ($method === 'GET') {
    $mode = $_GET['hub_mode'] ?? ($_GET['hub.mode'] ?? '');
    $token = $_GET['hub_verify_token'] ?? ($_GET['hub.verify_token'] ?? '');
    $challenge = $_GET['hub_challenge'] ?? ($_GET['hub.challenge'] ?? '');

    $expected = $config['meta']['verify_token'] ?? '';

    if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, (string) $token)) {
        header('Content-Type: text/plain');
        echo $challenge;
        exit;
    }

    http_response_code(403);
    exit('verification failed');
}

// --- POST: inbound events ---
$body = file_get_contents('php://input') ?: '';

$appSecret = $config['meta']['app_secret'] ?? '';
if ($appSecret !== '') {
    $sigHeader = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    $expectedSig = 'sha256=' . hash_hmac('sha256', $body, $appSecret);
    if (!is_string($sigHeader) || !hash_equals($expectedSig, $sigHeader)) {
        http_response_code(401);
        exit('invalid signature');
    }
}
// If no app secret is configured (dev), signature is skipped.

$payload = json_decode($body, true);
if (!is_array($payload)) {
    http_response_code(400);
    exit('bad payload');
}

try {
    $status = BotRouter::route($payload);
} catch (\Throwable $e) {
    error_log('[whatsapp webhook] ' . $e->getMessage());
    $status = 'error';
}

// Meta only needs a 200; the status is for our logs.
http_response_code(200);
header('Content-Type: text/plain');
echo $status;
