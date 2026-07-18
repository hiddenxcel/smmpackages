<?php
// NOWPayments IPN callback. Verify x-nowpayments-sig (HMAC-SHA512), then fulfil once.
require_once __DIR__ . '/_boot.php';

$body = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_X_NOWPAYMENTS_SIG'] ?? '';

$client = new NowPaymentsClient($config['billing']['nowpayments'] ?? []);

if (!$client->verifySignature($body, $signature)) {
    http_response_code(401);
    webhookLog('webhook_nowpayments_bad_sig', ['ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
    exit('invalid signature');
}

$data = json_decode($body, true) ?: [];
$transactionRef = $data['order_id'] ?? '';
$status = $data['payment_status'] ?? null;

if ($transactionRef === '') {
    http_response_code(400);
    exit('missing order_id');
}

// Only activate on a genuinely paid status — never trust the caller's intent alone.
if (NowPaymentsClient::isPaidStatus($status)) {
    $billing = new SubscriptionBilling($config);
    $activated = $billing->fulfil($transactionRef, $body);
    webhookLog('webhook_nowpayments', ['ref' => $transactionRef, 'status' => $status, 'activated' => $activated]);
} else {
    webhookLog('webhook_nowpayments_pending', ['ref' => $transactionRef, 'status' => $status]);
}

http_response_code(200);
echo 'ok';
