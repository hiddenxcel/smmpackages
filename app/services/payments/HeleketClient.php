<?php

/**
 * HeleketClient — USDT / crypto via Heleket hosted checkout.
 * https://doc.heleket.com/getting-started/introduction
 *
 * Heleket shares Cryptomus's API contract (it is the same gateway family):
 * no KYB needed. Flow: initiate() creates an invoice and returns its checkout
 * URL; the payer pays crypto there, and Heleket POSTs a webhook on status
 * change. Both the request and the webhook are signed with:
 *   sign = md5( base64_encode(json_body) + api_key )
 * placed in the JSON body (webhook) or the `sign` header (request).
 */
class HeleketClient
{
    private const INVOICE_URL = 'https://api.heleket.com/v1/payment';
    private const INFO_URL = 'https://api.heleket.com/v1/payment/info';

    private string $merchant;   // Merchant UUID
    private string $apiKey;      // Payment API key (used for sign + webhook verify)

    public function __construct(array $config)
    {
        // api_key = Payment API key; merchant = Merchant UUID.
        $this->apiKey = $config['api_key'] ?? '';
        $this->merchant = $config['merchant'] ?? '';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->merchant !== '';
    }

    /**
     * @param array $data amount, currency, order_id (=transaction_ref),
     *                     webhook_url, success_url, description
     * @return array ['success'=>bool, 'redirect_url'=>?string, 'reference'=>?string, 'message'=>?string]
     */
    public function initiate(array $data): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Heleket is not configured.'];
        }

        $payload = [
            'amount' => (string) round((float) $data['amount'], 2),
            'currency' => strtoupper($data['currency'] ?? 'USD'),
            'order_id' => (string) $data['order_id'],
            'url_callback' => $data['webhook_url'] ?? '',
            'url_success' => $data['success_url'] ?? '',
            'url_return' => $data['cancel_url'] ?? ($data['success_url'] ?? ''),
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $sign = md5(base64_encode($body) . $this->apiKey);

        $ch = curl_init(self::INVOICE_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'merchant: ' . $this->merchant,
                'sign: ' . $sign,
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

        // Success: state 0 with result.url + result.uuid.
        if (is_array($result) && (int) ($result['state'] ?? 1) === 0 && !empty($result['result']['url'])) {
            return [
                'success' => true,
                'redirect_url' => $result['result']['url'],
                'reference' => (string) ($result['result']['uuid'] ?? $data['order_id']),
            ];
        }

        $msg = $result['message'] ?? ($result['errors'] ? json_encode($result['errors']) : 'Unknown Heleket error');

        return ['success' => false, 'message' => $msg];
    }

    /**
     * Verify a webhook: recompute md5(base64(payload-without-sign) + api_key)
     * and compare to the `sign` field in the payload. Empty api_key (dev) allows.
     */
    public function verifyWebhook(array $data): bool
    {
        if ($this->apiKey === '') {
            error_log('[HeleketClient] WARNING: apiKey empty — webhook signature NOT verified');

            return true; // no key configured (dev) -> allow
        }

        $sign = $data['sign'] ?? '';
        if ($sign === '') {
            return false;
        }

        unset($data['sign']);
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $expected = md5(base64_encode($body) . $this->apiKey);

        return hash_equals($expected, (string) $sign);
    }

    /** Heleket terminal "paid" statuses. */
    public static function isPaidStatus(?string $status): bool
    {
        return in_array((string) $status, ['paid', 'paid_over', 'finished'], true);
    }
}
