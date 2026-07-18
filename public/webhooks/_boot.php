<?php

/**
 * Webhook bootstrap — minimal, no session/lang. Loads config, DB, Crypto,
 * billing. Webhooks must NOT trust the payload; each verifies with its gateway.
 */

require_once __DIR__ . '/../../app/lib/DB.php';
require_once __DIR__ . '/../../app/lib/Crypto.php';
require_once __DIR__ . '/../../app/services/SubscriptionBilling.php';
require_once __DIR__ . '/../../app/helpers/RateLimit.php';

$config = require __DIR__ . '/../../config/config.php';

DB::connect($config['db']);
if (!empty($config['app']['key'])) {
    Crypto::init($config['app']['key']);
}

/** Log webhook activity to activity_log (system actor). */
function webhookLog(string $action, array $details): void
{
    try {
        $stmt = DB::conn()->prepare(
            "INSERT INTO activity_log (actor_type, actor_id, action, details, ip)
             VALUES ('system', NULL, ?, ?, ?)"
        );
        $stmt->execute([$action, json_encode($details), $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) {
        error_log('[webhook] log failed: ' . $e->getMessage());
    }
}
