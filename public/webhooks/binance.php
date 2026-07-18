<?php
// Binance Pay webhook. Verify signature (timestamp+nonce+body), then fulfil once.
require_once __DIR__ . '/_boot.php';

$body = file_get_contents('php://input') ?: '';
$timestamp = $_SERVER['HTTP_BINANCEPAY_TIMESTAMP'] ?? '';
$nonce = $_SERVER['HTTP_BINANCEPAY_NONCE'] ?? '';
$signature = $_SERVER['HTTP_BINANCEPAY_SIGNATURE'] ?? '';

$client = new BinancePayClient($config['billing']['binance'] ?? []);

if (!$client->verifySignature($body, $timestamp, $nonce, $signature)) {
    http_response_code(401);
    webhookLog('webhook_binance_bad_sig', ['ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
    echo json_encode(['returnCode' => 'FAIL', 'returnMessage' => 'invalid signature']);
    exit;
}

$data = json_decode($body, true) ?: [];
$bizStatus = $data['bizStatus'] ?? null;

// The merchantTradeNo (our transaction_ref) is nested inside the JSON "data" string.
$inner = isset($data['data']) ? json_decode($data['data'], true) : [];
$transactionRef = $inner['merchantTradeNo'] ?? '';

if ($transactionRef !== '' && BinancePayClient::isPaidStatus($bizStatus)) {
    $billing = new SubscriptionBilling($config);
    $activated = $billing->fulfil($transactionRef, $body);
    webhookLog('webhook_binance', ['ref' => $transactionRef, 'status' => $bizStatus, 'activated' => $activated]);
} else {
    webhookLog('webhook_binance_other', ['ref' => $transactionRef, 'status' => $bizStatus]);
}

// Binance expects this exact acknowledgement shape.
http_response_code(200);
echo json_encode(['returnCode' => 'SUCCESS', 'returnMessage' => null]);
