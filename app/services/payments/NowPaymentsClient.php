<?php

/**
 * NowPaymentsClient — USDT / crypto via NOWPayments hosted invoice.
 * https://documenter.getpostman.com/view/7907941/S1a32n38
 *
 * Flow: initiate() creates a hosted invoice and returns its redirect URL;
 * the payer pays in crypto there, and NOWPayments POSTs an IPN callback.
 * IPN is verified with HMAC-SHA512 over the JSON-sorted body using IPN secret.
 */
class NowPaymentsClient
{
    private const INVOICE_URL = 'https://api.nowpayments.io/v1/invoice';
    private const STATUS_URL = 'https://api.nowpayments.io/v1/payment/';

    private string $apiKey;
    private string $ipnSecret;
    private string $payCurrency;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->ipnSecret = $config['ipn_secret'] ?? '';
        $this->payCurrency = $config['pay_currency'] ?? 'usdttrc20';
    }

    /**
     * @param array $data amount, currency, order_id (=transaction_ref), description,
     *                     success_url, cancel_url, webhook_url (ipn_callback_url)
     * @return array ['success'=>bool, 'redirect_url'=>?string, 'reference'=>?string, 'message'=>?string]
     */
    public function initiate(array $data): array
    {
        $payload = [
            'price_amount' => (float) $data['amount'],
            'price_currency' => strtolower($data['currency'] ?? 'usd'),
            'pay_currency' => $this->payCurrency,
            'order_id' => $data['order_id'],
            'order_description' => $data['description'] ?? 'SMM Packages subscription',
            'ipn_callback_url' => $data['webhook_url'],
            'success_url' => $data['success_url'] ?? '',
            'cancel_url' => $data['cancel_url'] ?? '',
        ];

        $ch = curl_init(self::INVOICE_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
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

        if (isset($result['invoice_url'])) {
            return [
                'success' => true,
                'redirect_url' => $result['invoice_url'],
                'reference' => (string) ($result['id'] ?? $data['order_id']),
            ];
        }

        return ['success' => false, 'message' => $result['message'] ?? 'Unknown NOWPayments error'];
    }

    public function checkStatus(string $paymentId): ?string
    {
        $ch = curl_init(self::STATUS_URL . urlencode($paymentId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['x-api-key: ' . $this->apiKey],
            CURLOPT_TIMEOUT => 15,
        ]);

        $responseRaw = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($responseRaw, true);

        return $result['payment_status'] ?? null;
    }

    /**
     * Verify an IPN callback: HMAC-SHA512 over the request body with keys
     * sorted alphabetically, compared against the x-nowpayments-sig header.
     */
    public function verifySignature(string $body, string $signature): bool
    {
        if (empty($this->ipnSecret)) {
            error_log('[NowPaymentsClient] WARNING: ipnSecret is empty — webhook signature NOT verified');

            return true; // no secret configured (dev) -> allow
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return false;
        }

        $sorted = self::ksortRecursive($data);
        $expected = hash_hmac('sha512', json_encode($sorted, JSON_UNESCAPED_SLASHES), $this->ipnSecret);

        return hash_equals($expected, $signature);
    }

    /** A "finished"/"confirmed" status means the crypto payment cleared. */
    public static function isPaidStatus(?string $status): bool
    {
        return in_array($status, ['finished', 'confirmed'], true);
    }

    private static function ksortRecursive(array $arr): array
    {
        ksort($arr);
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $arr[$k] = self::ksortRecursive($v);
            }
        }

        return $arr;
    }
}
