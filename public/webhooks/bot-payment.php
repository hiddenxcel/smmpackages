<?php
/**
 * Bot wallet-payment webhook (a TENANT's customer paid for a wallet top-up).
 *
 * Unlike the SaaS webhooks, this verifies against the TENANT's own gateway
 * secret: the payment's transaction_ref -> bot_payments row -> tenant_id ->
 * that tenant's gateway config (tenant_payment_gateways). On genuine success we
 * credit the customer's wallet and complete their pending order, exactly once
 * (replay-guarded in WalletTopup::completeAfterPayment via BotPayment::markSuccess).
 *
 * Always returns 200 quickly so the gateway doesn't retry a handled event.
 */
require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/../../app/models/BaseModel.php';
require_once __DIR__ . '/../../app/models/BotPayment.php';
require_once __DIR__ . '/../../app/models/BotCustomer.php';
require_once __DIR__ . '/../../app/models/BotConversation.php';
require_once __DIR__ . '/../../app/models/BotOrder.php';
require_once __DIR__ . '/../../app/models/BotSettings.php';
require_once __DIR__ . '/../../app/models/TenantPanel.php';
require_once __DIR__ . '/../../app/models/TenantPaymentGateway.php';
require_once __DIR__ . '/../../app/services/WalletTopup.php';
require_once __DIR__ . '/../../app/services/payments/SnippeClient.php';
require_once __DIR__ . '/../../app/services/payments/NowPaymentsClient.php';
require_once __DIR__ . '/../../app/services/payments/BinancePayClient.php';
require_once __DIR__ . '/../../app/services/payments/CryptomusClient.php';
require_once __DIR__ . '/../../app/services/payments/HeleketClient.php';

$body = file_get_contents('php://input') ?: '';
$data = json_decode($body, true) ?: [];

// The correlation ref is the order_id we set at initiate(). Its location in the
// payload differs per gateway, so we probe the known shapes.
$ref = $data['metadata']['order_id']                       // Snippe
    ?? ($data['data']['metadata']['order_id'] ?? null)     // Snippe (nested)
    ?? ($data['order_id'] ?? null)                         // NOWPayments
    ?? ($data['merchantTradeNo'] ?? null)                  // Binance Pay
    ?? ($data['data']['merchantTradeNo'] ?? null)
    ?? '';

if ($ref === '') {
    http_response_code(400);
    exit('missing order_id');
}

// Resolve the payment (and therefore the tenant) from the ref.
$payment = BotPayment::findByRefAnyTenant((string) $ref);
if ($payment === null) {
    http_response_code(404);
    webhookLog('bot_payment_unknown_ref', ['ref' => $ref]);
    exit('unknown ref');
}

$tenantId = (int) $payment['tenant_id'];
$gateway = (string) $payment['gateway'];
$gatewayCfg = TenantPaymentGateway::find($tenantId, $gateway);
$secret = $gatewayCfg['webhook_secret'] ?? '';
$apiKey = $gatewayCfg['api_key'] ?? '';

// Verify the signature with the TENANT's secret, per gateway. An empty stored
// secret means dev/unconfigured — skip verification (matches the rest of the
// codebase so local testing works without real keys).
$sigOk = true;
$status = null;

if ($gateway === 'nowpayments') {
    // NOWPayments: HMAC-SHA512 in x-nowpayments-sig; status in payment_status.
    if ($secret !== '') {
        $sig = $_SERVER['HTTP_X_NOWPAYMENTS_SIG'] ?? '';
        $sigOk = (new NowPaymentsClient(['api_key' => $apiKey, 'ipn_secret' => $secret]))->verifySignature($body, $sig);
    }
    $status = $data['payment_status'] ?? null;
} elseif ($gateway === 'binance') {
    // Binance Pay: signed over ts\nnonce\nbody; status in bizStatus.
    if ($secret !== '') {
        $ts = $_SERVER['HTTP_BINANCEPAY_TIMESTAMP'] ?? '';
        $nonce = $_SERVER['HTTP_BINANCEPAY_NONCE'] ?? '';
        $sig = $_SERVER['HTTP_BINANCEPAY_SIGNATURE'] ?? '';
        $sigOk = (new BinancePayClient(['api_key' => $apiKey, 'api_secret' => $secret]))->verifySignature($body, $ts, $nonce, $sig);
    }
    $status = $data['bizStatus'] ?? ($data['data']['bizStatus'] ?? null);
} elseif ($gateway === 'cryptomus') {
    // Cryptomus: sign = md5(base64(body-without-sign)+api_key), in the body.
    // Stored slots: api_key = Payment API key, webhook_secret = Merchant UUID.
    if ($apiKey !== '') {
        $sigOk = (new CryptomusClient(['api_key' => $apiKey, 'merchant' => $secret]))->verifyWebhook($data);
    }
    $status = $data['status'] ?? null;
} elseif ($gateway === 'heleket') {
    // Heleket: same contract as Cryptomus (sign = md5(base64(body-without-sign)+api_key)).
    // Stored slots: api_key = Payment API key, webhook_secret = Merchant UUID.
    if ($apiKey !== '') {
        $sigOk = (new HeleketClient(['api_key' => $apiKey, 'merchant' => $secret]))->verifyWebhook($data);
    }
    $status = $data['status'] ?? null;
} else {
    // Snippe (default): HMAC-SHA256 over "{ts}.{body}"; status in status.
    if ($secret !== '') {
        $sig = $_SERVER['HTTP_X_SNIPPE_SIGNATURE'] ?? '';
        $ts = $_SERVER['HTTP_X_SNIPPE_TIMESTAMP'] ?? '0';
        $sigOk = (new SnippeClient(['api_key' => $apiKey, 'webhook_secret' => $secret]))->verifySignature($body, $sig, $ts);
    }
    $status = $data['status'] ?? ($data['data']['status'] ?? null);
}

if (!$sigOk) {
    http_response_code(401);
    webhookLog('bot_payment_bad_sig', ['tenant' => $tenantId, 'gateway' => $gateway, 'ref' => $ref]);
    exit('invalid signature');
}

// Normalise "paid" across gateways (Snippe: success/paid; NOWPayments:
// finished/confirmed; Binance: PAY_SUCCESS/SUCCESS).
$paid = in_array(strtolower((string) $status), ['success', 'completed', 'paid', 'paid_over', 'finished', 'confirmed', 'pay_success'], true);

if ($paid) {
    WalletTopup::completeAfterPayment((int) $payment['id']);
    webhookLog('bot_payment_success', ['tenant' => $tenantId, 'ref' => $ref, 'payment' => $payment['id']]);
} else {
    if (in_array(strtolower((string) $status), ['failed', 'cancelled', 'error', 'expired', 'refunded', 'pay_closed'], true)) {
        BotPayment::markFailed((int) $payment['id']);
    }
    webhookLog('bot_payment_other', ['tenant' => $tenantId, 'ref' => $ref, 'status' => $status]);
}

http_response_code(200);
echo 'ok';
