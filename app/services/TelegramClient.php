<?php

/**
 * TelegramClient — a drop-in replacement for WhatsAppCloudClient's send API,
 * so the existing OrderBotHandler works unchanged over Telegram.
 *
 * The public surface (sendText/sendList/sendButtons/markReadWithTyping) matches
 * WhatsAppCloudClient. Lists and buttons both render as inline keyboards, whose
 * callback_data carries the same row/button ids the handler expects.
 *
 * $to is the Telegram chat_id (numeric). Every outbound message is logged to
 * bot_messages, tagged with the tenant so stats/broadcasts stay consistent.
 */
require_once __DIR__ . '/BotMessenger.php';

class TelegramClient implements BotMessenger
{
    private string $token;
    private int $tenantId;

    public function __construct(string $token, int $tenantId)
    {
        $this->token = $token;
        $this->tenantId = $tenantId;
    }

    public static function forTenant(array $telegram, int $tenantId): self
    {
        return new self((string) TenantTelegram::token($telegram), $tenantId);
    }

    public function sendText(string $to, string $message, ?string $templateKey = null): bool
    {
        $ok = $this->call('sendMessage', [
            'chat_id' => $this->chatId($to),
            'text' => $this->plain($message),
        ]);
        $this->logOut($to, $message, $templateKey);

        return $ok;
    }

    /** @param array<int,array{id:string,title:string,description?:string}> $rows */
    public function sendList(string $to, string $bodyText, string $buttonText, string $sectionTitle, array $rows, ?string $templateKey = null): bool
    {
        $keyboard = array_map(fn ($r) => [[
            'text' => $r['title'] . (!empty($r['description']) ? ' — ' . $r['description'] : ''),
            'callback_data' => mb_substr($r['id'], 0, 64),
        ]], $rows);

        $ok = $this->call('sendMessage', [
            'chat_id' => $this->chatId($to),
            'text' => $this->plain($bodyText),
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
        ]);
        $this->logOut($to, "[list] {$bodyText}", $templateKey);

        return $ok;
    }

    /** @param array<int,array{id:string,title:string}> $buttons */
    public function sendButtons(string $to, string $bodyText, array $buttons, ?string $templateKey = null): bool
    {
        $keyboard = [array_map(fn ($b) => [
            'text' => $b['title'],
            'callback_data' => mb_substr($b['id'], 0, 64),
        ], $buttons)];

        $ok = $this->call('sendMessage', [
            'chat_id' => $this->chatId($to),
            'text' => $this->plain($bodyText),
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
        ]);
        $this->logOut($to, "[buttons] {$bodyText}", $templateKey);

        return $ok;
    }

    /** No typing indicator needed on Telegram; keep the signature for compatibility. */
    public function markReadWithTyping(string $messageId): bool
    {
        return true;
    }

    /** Register the webhook URL with Telegram (called from setup). */
    public function setWebhook(string $url): array
    {
        $ch = curl_init("https://api.telegram.org/bot{$this->token}/setWebhook");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['url' => $url]),
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        return json_decode($body, true) ?: ['ok' => false];
    }

    /** Fetch the bot's username (validates the token). */
    public function getMe(): ?array
    {
        $ch = curl_init("https://api.telegram.org/bot{$this->token}/getMe");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
        $body = curl_exec($ch);
        curl_close($ch);
        $res = json_decode($body, true);

        return ($res['ok'] ?? false) ? $res['result'] : null;
    }

    private function call(string $method, array $params): bool
    {
        $ch = curl_init("https://api.telegram.org/bot{$this->token}/{$method}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || $httpCode !== 200) {
            error_log("[TelegramClient] {$method} failed ({$httpCode}): " . ($err ?: $body));

            return false;
        }

        return true;
    }

    /** Strip the "tg:" identifier prefix to get the numeric chat_id for the API. */
    private function chatId(string $to): string
    {
        return str_starts_with($to, 'tg:') ? substr($to, 3) : $to;
    }

    /** Strip WhatsApp *bold* markers — Telegram plain text mode. */
    private function plain(string $text): string
    {
        return str_replace('*', '', $text);
    }

    private function logOut(string $to, string $message, ?string $templateKey): void
    {
        // $to already carries the "tg:" identifier prefix from the router.
        $identifier = str_starts_with($to, 'tg:') ? $to : 'tg:' . $to;
        try {
            DB::conn()->prepare(
                "INSERT INTO bot_messages (tenant_id, customer_phone, direction, message, template_key)
                 VALUES (?, ?, 'out', ?, ?)"
            )->execute([$this->tenantId, $identifier, $message, $templateKey]);
        } catch (\Throwable $e) {
            error_log('[TelegramClient] log failed: ' . $e->getMessage());
        }
    }
}
