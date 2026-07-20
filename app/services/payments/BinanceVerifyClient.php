<?php

/**
 * BinanceVerifyClient — "Binance internal transfer" (no-KYB) payment verifier.
 *
 * Unlike BinancePayClient (Binance Pay Merchant API, needs an Entity/KYB
 * account), this uses a plain Binance **Spot API key** with only read access.
 * The merchant shows their own Binance Pay ID; the payer sends USDT/USDC/BUSD
 * to it as a free internal transfer and reports the Binance Order ID. We then
 * read the merchant's own last-24h Binance Pay history and confirm that order.
 *
 * Endpoint: GET https://api.binance.com/sapi/v1/pay/transactions
 * Signature: HMAC-SHA256 of the query string with the API secret.
 *
 * verifyOrder() returns:
 *   ['success'=>bool, 'error_type'=>?string, 'message'=>string,
 *    'currency'=>?string, 'amount'=>?float]
 * error_type is one of: not_found, amount_mismatch, invalid_currency,
 * too_old, api_error, connection.
 */
class BinanceVerifyClient
{
    private const ENDPOINT = 'https://api.binance.com/sapi/v1/pay/transactions';
    private const ALLOWED_CURRENCIES = ['USDT', 'USDC', 'BUSD'];

    /** Accept a transfer only if it happened within this window (ms). */
    private const MAX_AGE_MS = 1800000; // 30 minutes

    /** Amount tolerance (covers tiny float rounding). */
    private const AMOUNT_TOLERANCE = 0.01;

    private string $apiKey;
    private string $apiSecret;

    /** The merchant Binance ID shown to payers (display only; not used to verify). */
    public string $payId;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->apiSecret = $config['api_secret'] ?? '';
        $this->payId = (string) ($config['pay_id'] ?? '');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->apiSecret !== '';
    }

    /**
     * Verify that $orderId is a valid, recent, incoming transfer of $expectAmount
     * (treated 1:1 as USDT) into the merchant's Binance Pay history.
     */
    public function verifyOrder(string $orderId, float $expectAmount): array
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            return ['success' => false, 'error_type' => 'not_found', 'message' => 'Please enter the Binance Order ID.'];
        }
        if (!$this->isConfigured()) {
            return ['success' => false, 'error_type' => 'api_error', 'message' => 'Binance is not configured.'];
        }

        $timestamp = (int) round(microtime(true) * 1000);
        $startTime = $timestamp - 86400000; // last 24h
        $query = "startTimestamp={$startTime}&timestamp={$timestamp}&recvWindow=60000";
        $signature = hash_hmac('sha256', $query, $this->apiSecret);
        $url = self::ENDPOINT . "?{$query}&signature={$signature}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['X-MBX-APIKEY: ' . $this->apiKey],
            CURLOPT_TIMEOUT => 30,
        ]);
        $responseRaw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'error_type' => 'connection', 'message' => "Connection error: {$err}"];
        }

        $result = json_decode($responseRaw, true);

        if (!is_array($result)
            || ($result['code'] ?? '') !== '000000'
            || empty($result['success'])) {
            // Success responses use code '000000'; auth/other errors use the
            // Spot API error shape {"code":-2008,"msg":"..."}.
            $apiMsg = $result['msg'] ?? ($result['message'] ?? 'Unknown Binance API error');

            return ['success' => false, 'error_type' => 'api_error', 'message' => "Binance API error: {$apiMsg}"];
        }

        $rows = (isset($result['data']) && is_array($result['data'])) ? $result['data'] : [];

        foreach ($rows as $tx) {
            if ((string) ($tx['orderId'] ?? '') !== $orderId) {
                continue;
            }

            // Found the order — diagnose it precisely.
            $txAmount = (float) ($tx['amount'] ?? 0);
            $txCurrency = strtoupper((string) ($tx['currency'] ?? ''));
            $txTime = (int) ($tx['transactionTime'] ?? 0);

            if ($txAmount <= 0) {
                return ['success' => false, 'error_type' => 'amount_mismatch', 'message' => 'Invalid transaction type (outgoing).'];
            }
            if (!in_array($txCurrency, self::ALLOWED_CURRENCIES, true)) {
                return ['success' => false, 'error_type' => 'invalid_currency', 'message' => "Invalid currency ({$txCurrency}). Only USDT, USDC or BUSD are accepted."];
            }
            if (abs($txAmount - $expectAmount) > self::AMOUNT_TOLERANCE) {
                return [
                    'success' => false,
                    'error_type' => 'amount_mismatch',
                    'message' => 'Amount mismatch. Expected ' . rtrim(rtrim(number_format($expectAmount, 2), '0'), '.') . " but this transfer is {$txAmount} {$txCurrency}.",
                ];
            }
            if (($timestamp - $txTime) > self::MAX_AGE_MS) {
                return ['success' => false, 'error_type' => 'too_old', 'message' => 'This transfer matches an order older than 30 minutes. Please make a fresh transfer.'];
            }

            return [
                'success' => true,
                'error_type' => null,
                'message' => 'Payment verified.',
                'currency' => $txCurrency,
                'amount' => $txAmount,
            ];
        }

        return ['success' => false, 'error_type' => 'not_found', 'message' => 'Transaction not found. Check the Order ID and make sure the transfer is complete.'];
    }
}
