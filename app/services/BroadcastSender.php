<?php

require_once __DIR__ . '/../models/Broadcast.php';
require_once __DIR__ . '/../models/Tenant.php';
require_once __DIR__ . '/../models/TenantWhatsApp.php';
require_once __DIR__ . '/../models/Subscription.php';
require_once __DIR__ . '/WhatsAppCloudClient.php';

/**
 * BroadcastSender — processes queued broadcasts in rate-safe batches.
 *
 * Gate: the tenant must have an active order_bot OR support_bot subscription
 * (i.e. a working WhatsApp number). A suspended tenant or a broadcast whose
 * tenant lost its number is cancelled.
 */
class BroadcastSender
{
    /** Process one batch for a single broadcast. Returns [sent, failed, done]. */
    public static function processBroadcast(array $broadcast): array
    {
        $tenantId = (int) $broadcast['tenant_id'];
        $broadcastId = (int) $broadcast['id'];

        $tenant = Tenant::find($tenantId);
        $whatsapp = TenantWhatsApp::forTenant($tenantId);
        $gated = Subscription::isServiceActive($tenantId, 'order_bot')
            || Subscription::isServiceActive($tenantId, 'support_bot');

        if ($tenant === null || $tenant['status'] === 'suspended' || $whatsapp === null || !$gated) {
            Broadcast::setStatus($broadcastId, 'cancelled');

            return ['sent' => 0, 'failed' => 0, 'done' => true];
        }

        Broadcast::setStatus($broadcastId, 'sending');
        $wa = WhatsAppCloudClient::forTenant($whatsapp, $tenantId, '© ' . ($tenant['business_name'] ?? ''));

        $batch = Broadcast::nextBatch($broadcastId);
        $sent = 0;
        $failed = 0;

        foreach ($batch as $r) {
            $ok = $wa->sendText($r['customer_phone'], $broadcast['message'], 'BROADCAST');
            Broadcast::markRecipient((int) $r['id'], $ok ? 'sent' : 'failed');
            $ok ? $sent++ : $failed++;
        }

        Broadcast::bumpCounts($broadcastId, $sent, $failed);

        $done = Broadcast::pendingCount($broadcastId) === 0;
        if ($done) {
            Broadcast::setStatus($broadcastId, 'completed');
        }

        return ['sent' => $sent, 'failed' => $failed, 'done' => $done];
    }
}
