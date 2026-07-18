<?php

/**
 * WhatsAppCloudClient — multi-tenant WhatsApp Cloud API sender.
 * Ported from hiddenxcel/kuzapanel-bot's WhatsAppClient, but token,
 * phone_number_id, footer and tenant_id are per-tenant (no globals).
 *
 * Every outbound message is logged to bot_messages for the owning tenant.
 */
require_once __DIR__ . '/BotMessenger.php';

class WhatsAppCloudClient implements BotMessenger
{
    private const GRAPH_VERSION = 'v22.0';

    private string $token;
    private string $phoneNumberId;
    private int $tenantId;
    private string $footer;

    public function __construct(string $token, string $phoneNumberId, int $tenantId, string $footer = '')
    {
        $this->token = $token;
        $this->phoneNumberId = $phoneNumberId;
        $this->tenantId = $tenantId;
        $this->footer = $footer;
    }

    public static function forTenant(array $whatsapp, int $tenantId, string $footer = ''): self
    {
        $token = TenantWhatsApp::token($whatsapp) ?? '';
        if ($token === '') {
            error_log("[WhatsAppCloudClient] Empty token for tenant #{$tenantId}");
        }

        return new self(
            $token,
            (string) $whatsapp['phone_number_id'],
            $tenantId,
            $footer
        );
    }

    public function sendText(string $to, string $message, ?string $templateKey = null): bool
    {
        $ok = $this->send([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $message],
        ]);
        $this->logOut($to, $message, $templateKey);

        return $ok;
    }

    /** @param array<int, array{id:string,title:string,description?:string}> $rows */
    public function sendList(string $to, string $bodyText, string $buttonText, string $sectionTitle, array $rows, ?string $templateKey = null): bool
    {
        $ok = $this->send([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => ['text' => $bodyText],
                'footer' => ['text' => $this->footer],
                'action' => [
                    'button' => $buttonText,
                    'sections' => [[
                        'title' => $sectionTitle,
                        'rows' => array_map(fn ($r) => [
                            'id' => $r['id'],
                            'title' => mb_substr($r['title'], 0, 24),
                            'description' => mb_substr($r['description'] ?? '', 0, 72),
                        ], $rows),
                    ]],
                ],
            ],
        ]);
        $this->logOut($to, "[list] {$bodyText}", $templateKey);

        return $ok;
    }

    /** @param array<int, array{id:string,title:string}> $buttons up to 3 */
    public function sendButtons(string $to, string $bodyText, array $buttons, ?string $templateKey = null): bool
    {
        $ok = $this->send([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $bodyText],
                'footer' => ['text' => $this->footer],
                'action' => [
                    'buttons' => array_map(fn ($b) => [
                        'type' => 'reply',
                        'reply' => ['id' => $b['id'], 'title' => mb_substr($b['title'], 0, 20)],
                    ], $buttons),
                ],
            ],
        ]);
        $this->logOut($to, "[buttons] {$bodyText}", $templateKey);

        return $ok;
    }

    public function markReadWithTyping(string $messageId): bool
    {
        return $this->send([
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
            'typing_indicator' => ['type' => 'text'],
        ]);
    }

    private function send(array $payload): bool
    {
        if ($this->token === '') {
            error_log("[WhatsAppCloudClient] Skipping send — empty token for tenant #{$this->tenantId}");

            return false;
        }

        $url = 'https://graph.facebook.com/' . self::GRAPH_VERSION . "/{$this->phoneNumberId}/messages";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->token,
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            error_log("[WhatsAppCloudClient] cURL error: {$err}");

            return false;
        }
        if ($httpCode !== 200) {
            error_log("[WhatsAppCloudClient] Send failed ({$httpCode}): {$body}");

            return false;
        }

        return true;
    }

    private function logOut(string $to, string $message, ?string $templateKey): void
    {
        try {
            $stmt = DB::conn()->prepare(
                "INSERT INTO bot_messages (tenant_id, customer_phone, direction, message, template_key)
                 VALUES (?, ?, 'out', ?, ?)"
            );
            $stmt->execute([$this->tenantId, $to, $message, $templateKey]);
        } catch (\Throwable $e) {
            error_log('[WhatsAppCloudClient] log failed: ' . $e->getMessage());
        }
    }
}
