<?php

require_once __DIR__ . '/../../models/TenantTelegram.php';
require_once __DIR__ . '/../../models/Tenant.php';
require_once __DIR__ . '/../../models/Subscription.php';
require_once __DIR__ . '/../TelegramClient.php';
require_once __DIR__ . '/OrderBotHandler.php';

/**
 * TelegramRouter — Telegram counterpart of BotRouter.
 *
 * The webhook secret (URL ?s=) maps to a tenant's telegram row. We then gate on
 * order_bot and run the SAME OrderBotHandler via a TelegramClient. Text messages
 * and inline-keyboard callbacks are both normalised to (chat_id, text/id).
 */
class TelegramRouter
{
    public static function route(string $secret, array $update): string
    {
        $telegram = TenantTelegram::findBySecret($secret);
        if ($telegram === null) {
            return 'unknown_secret';
        }

        $tenantId = (int) $telegram['tenant_id'];
        $tenant = Tenant::find($tenantId);
        if ($tenant === null || $tenant['status'] === 'suspended') {
            return 'tenant_inactive';
        }

        // Normalise the update to a chat_id + text/callback id.
        [$chatId, $text] = self::extract($update);
        if ($chatId === null) {
            return 'no_chat';
        }

        // Use a "tg:" prefixed identifier everywhere (conversation + logs) so a
        // Telegram user never collides with a WhatsApp phone number, and stats
        // stay consistent. TelegramClient strips the prefix before sending.
        $identifier = 'tg:' . $chatId;

        self::logIn($tenantId, $identifier, $text);

        // Telegram is an order-bot channel (Phase 7). Gate on order_bot.
        $wa = TelegramClient::forTenant($telegram, $tenantId);
        if (!Subscription::isServiceActive($tenantId, 'order_bot')) {
            $wa->sendText($identifier, "⏸️ This service is currently paused. Please try again later.", 'SUBSCRIPTION_EXPIRED');

            return 'gate_locked';
        }

        (new OrderBotHandler($tenant, $wa))->handle($identifier, $text);

        return 'handled_order';
    }

    /** @return array{0:?int,1:string} [chat_id, text-or-callback-id] */
    private static function extract(array $update): array
    {
        if (isset($update['message'])) {
            return [
                $update['message']['chat']['id'] ?? null,
                (string) ($update['message']['text'] ?? ''),
            ];
        }
        if (isset($update['callback_query'])) {
            return [
                $update['callback_query']['message']['chat']['id'] ?? null,
                (string) ($update['callback_query']['data'] ?? ''),
            ];
        }

        return [null, ''];
    }

    private static function logIn(int $tenantId, string $identifier, string $text): void
    {
        try {
            DB::conn()->prepare(
                "INSERT INTO bot_messages (tenant_id, customer_phone, direction, message)
                 VALUES (?, ?, 'in', ?)"
            )->execute([$tenantId, $identifier, $text]);
        } catch (\Throwable $e) {
            error_log('[TelegramRouter] logIn failed: ' . $e->getMessage());
        }
    }
}
