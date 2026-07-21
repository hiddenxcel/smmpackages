<?php
// Heleket payment webhook. Verify sign (md5(base64(body)+api_key)), fulfil once.
require_once __DIR__ . '/_boot.php';

$body = file_get_contents('php://input') ?: '';
$data = json_decode($body, true) ?: [];

$client = new HeleketClient($config['billing']['heleket'] ?? []);

if (!$client->verifyWebhook($data)) {
    http_response_code(401);
    webhookLog('webhook_heleket_bad_sig', ['ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
    exit('invalid signature');
}

$transactionRef = $data['order_id'] ?? '';
$status = $data['status'] ?? null;

if ($transactionRef === '') {
    http_response_code(400);
    exit('missing order_id');
}

// Only activate on a genuinely paid status.
if (HeleketClient::isPaidStatus($status)) {
    $billing = new SubscriptionBilling($config);
    $activated = $billing->fulfil($transactionRef, $body);
    webhookLog('webhook_heleket', ['ref' => $transactionRef, 'status' => $status, 'activated' => $activated]);
} else {
    webhookLog('webhook_heleket_pending', ['ref' => $transactionRef, 'status' => $status]);
}

http_response_code(200);
echo 'ok';
