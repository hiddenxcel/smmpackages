<?php
// Snippe mobile money webhook. Verify HMAC signature + timestamp, then fulfil once.
require_once __DIR__ . '/_boot.php';

$body = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_X_SNIPPE_SIGNATURE'] ?? '';
$timestamp = $_SERVER['HTTP_X_SNIPPE_TIMESTAMP'] ?? '0';

$client = new SnippeClient($config['billing']['snippe'] ?? []);

if (!$client->verifySignature($body, $signature, $timestamp)) {
    http_response_code(401);
    webhookLog('webhook_snippe_bad_sig', ['ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
    exit('invalid signature');
}

$data = json_decode($body, true) ?: [];
// transaction_ref is what we passed as metadata.order_id at initiate().
$transactionRef = $data['metadata']['order_id'] ?? ($data['data']['metadata']['order_id'] ?? '');
$status = $data['status'] ?? ($data['data']['status'] ?? null);

if ($transactionRef === '') {
    http_response_code(400);
    exit('missing order_id');
}

$paid = in_array(strtolower((string) $status), ['success', 'completed', 'paid'], true);

if ($paid) {
    $billing = new SubscriptionBilling($config);
    $activated = $billing->fulfil($transactionRef, $body);
    webhookLog('webhook_snippe', ['ref' => $transactionRef, 'status' => $status, 'activated' => $activated]);
} else {
    webhookLog('webhook_snippe_other', ['ref' => $transactionRef, 'status' => $status]);
}

http_response_code(200);
echo 'ok';
