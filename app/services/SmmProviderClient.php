<?php

/**
 * SmmProviderClient — adapter for the standard SMM API v2.
 * Ported from hiddenxcel/kuzapanel-bot and generalised for two auth styles:
 *   - 'param'  : key passed in the POST body as `key` (most panels, v2)
 *   - 'header' : key passed as an Authorization / X-Api-Key header (some PerfectPanel v2)
 *
 * Endpoint is the panel's api/v2 URL. All calls return ['success'=>bool, ...].
 */
class SmmProviderClient
{
    private string $apiUrl;
    private string $apiKey;
    private string $authMethod;

    public function __construct(array $panel)
    {
        $this->apiUrl = rtrim($panel['api_url'], '/');
        $this->apiKey = $panel['api_key'];
        $this->authMethod = $panel['auth_method'] ?? 'param';
    }

    public static function fromPanel(array $panel): self
    {
        return new self([
            'api_url' => $panel['api_url'],
            'api_key' => TenantPanel::apiKey($panel) ?? '',
            'auth_method' => $panel['auth_method'] ?? 'param',
        ]);
    }

    public function checkBalance(): array
    {
        $result = $this->call(['action' => 'balance']);

        if ($result === null || isset($result['error'])) {
            return ['success' => false, 'message' => $result['error'] ?? 'Provider error'];
        }

        return ['success' => true, 'balance' => $result['balance'] ?? null, 'currency' => $result['currency'] ?? null];
    }

    public function getServices(): array
    {
        $result = $this->call(['action' => 'services']);

        if (!is_array($result) || isset($result['error'])) {
            return ['success' => false, 'message' => $result['error'] ?? 'Provider error', 'services' => []];
        }

        return ['success' => true, 'services' => $result];
    }

    public function addOrder(string $serviceId, string $link, int $quantity): array
    {
        $result = $this->call(['action' => 'add', 'service' => $serviceId, 'link' => $link, 'quantity' => $quantity]);

        if ($result === null || isset($result['error'])) {
            return ['success' => false, 'message' => $result['error'] ?? 'Provider error'];
        }
        if (!isset($result['order'])) {
            return ['success' => false, 'message' => 'Provider returned no order ID'];
        }

        return ['success' => true, 'order_id' => (string) $result['order']];
    }

    public function checkStatus(string $orderId): array
    {
        $result = $this->call(['action' => 'status', 'order' => $orderId]);

        if ($result === null || isset($result['error'])) {
            return ['success' => false, 'message' => $result['error'] ?? 'Provider error'];
        }

        return [
            'success' => true,
            'status' => $result['status'] ?? null,
            'start_count' => $result['start_count'] ?? null,
            'remains' => $result['remains'] ?? null,
        ];
    }

    public function refill(string $orderId): array
    {
        $result = $this->call(['action' => 'refill', 'order' => $orderId]);

        if ($result === null || isset($result['error'])) {
            return ['success' => false, 'message' => $result['error'] ?? 'Provider error'];
        }

        return ['success' => true, 'refill' => $result['refill'] ?? null];
    }

    private function call(array $data): ?array
    {
        $headers = [];

        if ($this->authMethod === 'header') {
            $headers[] = 'X-Api-Key: ' . $this->apiKey;
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        } else {
            $data['key'] = $this->apiKey;
        }

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $response === false) {
            error_log("[SmmProviderClient] cURL error: {$err}");

            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            error_log('[SmmProviderClient] Invalid response: ' . substr((string) $response, 0, 200));

            return null;
        }

        return $decoded;
    }
}
