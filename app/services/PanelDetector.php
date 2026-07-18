<?php

require_once __DIR__ . '/SmmProviderClient.php';

/**
 * PanelDetector — auto-detect a panel's API config from a URL + admin key.
 *
 * The URL may be a bare domain (https://panel.com) or the full api/v2 endpoint.
 * We normalise to an api/v2 URL, then probe a `balance` call across the two
 * auth methods until one returns a valid balance — that combination is the
 * detected config. Services count is fetched as a bonus signal.
 */
class PanelDetector
{
    /** @return array ['success'=>bool, 'config'=>?array, 'message'=>?string] */
    public static function detect(string $rawUrl, string $apiKey, ?string $panelType = null): array
    {
        $endpoint = self::normaliseUrl($rawUrl);

        // Probe order: param+v2 is by far the most common; header covers PerfectPanel-style.
        $attempts = [
            ['auth_method' => 'param',  'api_version' => 'v2'],
            ['auth_method' => 'header', 'api_version' => 'v2'],
        ];

        foreach ($attempts as $attempt) {
            $client = new SmmProviderClient([
                'api_url' => $endpoint,
                'api_key' => $apiKey,
                'auth_method' => $attempt['auth_method'],
            ]);

            $balance = $client->checkBalance();
            if (!empty($balance['success']) && $balance['balance'] !== null) {
                $servicesCount = null;
                $services = $client->getServices();
                if (!empty($services['success'])) {
                    $servicesCount = count($services['services']);
                }

                return [
                    'success' => true,
                    'config' => [
                        'api_url' => $endpoint,
                        'auth_method' => $attempt['auth_method'],
                        'api_version' => $attempt['api_version'],
                        'panel_type' => $panelType ?: self::guessType($attempt['auth_method']),
                        'balance' => (float) $balance['balance'],
                        'currency' => $balance['currency'] ?? null,
                        'services_count' => $servicesCount,
                    ],
                ];
            }
        }

        return ['success' => false, 'message' => 'Could not connect. Check the URL and Admin API key, or use Manual Configuration.'];
    }

    /** Normalise a panel URL to its api/v2 endpoint. */
    public static function normaliseUrl(string $url): string
    {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        $url = rtrim($url, '/');

        // Already an api endpoint? leave it.
        if (preg_match('#/api(/v\d)?$#i', $url)) {
            return $url;
        }

        return $url . '/api/v2';
    }

    private static function guessType(string $authMethod): string
    {
        return $authMethod === 'header' ? 'perfectpanel' : 'custom';
    }
}
