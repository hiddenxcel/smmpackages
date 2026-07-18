<?php

/**
 * BinancePayClient — USDT via Binance Pay merchant API.
 * https://developers.binance.com/docs/binance-pay/api-order-create-v3
 *
 * Flow: initiate() creates an order and returns a checkout/universal URL;
 * Binance POSTs a webhook on completion. Both the request signature and the
 * webhook verification use the merchant secret with SHA512-HMAC over
 * "{timestamp}\n{nonce}\n{body}\n".
 */
class BinancePayClient
{
    private const ORDER_URL = 'https://bpay.binanceapi.com/binancepay/openapi/v3/order';
    private const QUERY_URL = 'https://bpay.binanceapi.com/binancepay/openapi/v2/order/query';

    private string $apiKey;
    private string $apiSecret;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->apiSecret = $config['api_secret'] ?? '';
    }

    /**
     * @param array $data amount, currency(USDT), order_id (=transaction_ref), description, webhook_url, return_url
     * @return array ['success'=>bool, 'redirect_url'=>?string, 'reference'=>?string, 'message'=>?string]
     */
    public function initiate(array $data): array
    {
        $payload = [
            'env' => ['terminalType' => 'WEB'],
            'merchantTradeNo' => $data['order_id'],
            'orderAmount' => (float) $data['amount'],
            'currency' => $data['currency'] ?? 'USDT',
            'goods' => [
                'goodsType' => '02',
                'goodsCategory' => '6000',
                'referenceGoodsId' => (string) $data['order_id'],
                'goodsName' => $data['description'] ?? 'SMM Packages subscription',
            ],
            'webhookUrl' => $data['webhook_url'] ?? '',
            'returnUrl' => $data['return_url'] ?? '',
        ];

        $body = json_encode($payload);
        $timestamp = (string) round(microtime(true) * 1000);
        $nonce = self::randomNonce();
        $signature = $this->sign($timestamp, $nonce, $body);

        $ch = curl_init(self::ORDER_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'BinancePay-Timestamp: ' . $timestamp,
                'BinancePay-Nonce: ' . $nonce,
                'BinancePay-Certificate-SN: ' . $this->apiKey,
                'BinancePay-Signature: ' . $signature,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $responseRaw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'message' => "cURL error: {$err}"];
        }

        $result = json_decode($responseRaw, true);

        if (($result['status'] ?? '') === 'SUCCESS' && isset($result['data']['universalUrl'])) {
            return [
                'success' => true,
                'redirect_url' => $result['data']['checkoutUrl'] ?? $result['data']['universalUrl'],
                'reference' => (string) ($result['data']['prepayId'] ?? $data['order_id']),
            ];
        }

        return ['success' => false, 'message' => $result['errorMessage'] ?? 'Unknown Binance Pay error'];
    }

    /**
     * Verify a webhook: reconstruct "{timestamp}\n{nonce}\n{body}\n" and
     * HMAC-SHA512 with the merchant secret against BinancePay-Signature.
     */
    public function verifySignature(string $body, string $timestamp, string $nonce, string $signature): bool
    {
        if (empty($this->apiSecret)) {
            error_log('[BinancePayClient] WARNING: apiSecret is empty — webhook signature NOT verified');

            return true; // no secret configured (dev) -> allow
        }

        $expected = $this->sign($timestamp, $nonce, $body);

        return hash_equals($expected, $signature);
    }

    /** Binance webhook bizStatus PAY_SUCCESS = paid. */
    public static function isPaidStatus(?string $bizStatus): bool
    {
        return $bizStatus === 'PAY_SUCCESS';
    }

    private function sign(string $timestamp, string $nonce, string $body): string
    {
        $payload = $timestamp . "\n" . $nonce . "\n" . $body . "\n";

        return strtoupper(hash_hmac('sha512', $payload, $this->apiSecret));
    }

    private static function randomNonce(): string
    {
        return bin2hex(random_bytes(16));
    }
}
