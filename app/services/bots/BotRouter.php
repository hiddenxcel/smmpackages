<?php

require_once __DIR__ . '/../../models/TenantWhatsApp.php';
require_once __DIR__ . '/../../models/Tenant.php';
require_once __DIR__ . '/../../models/Subscription.php';
require_once __DIR__ . '/../WhatsAppCloudClient.php';
require_once __DIR__ . '/OrderBotHandler.php';
require_once __DIR__ . '/SupportBotHandler.php';
require_once __DIR__ . '/../../models/BotSettings.php';
require_once __DIR__ . '/../../models/BotConversation.php';
require_once __DIR__ . '/../../helpers/RateLimit.php';

/**
 * BotRouter — the heart of the platform (PLAN SEHEMU 6.1).
 *
 * Meta webhook -> read phone_number_id from payload -> map to tenant via
 * tenant_whatsapp (UNIQUE phone_number_id) -> check the tenant's subscription
 * gate -> dispatch to the right handler with a tenant-scoped WhatsApp client.
 *
 * A tenant with bot_type 'order' or 'both' + active order_bot runs the order
 * flow; support handling arrives in Phase 4.
 */
class BotRouter
{
    /**
     * Route a full Meta webhook payload (already JSON-decoded).
     * Returns a short status string for logging. Never throws to the caller.
     */
    public static function route(array $payload): string
    {
        $value = $payload['entry'][0]['changes'][0]['value'] ?? null;
        if ($value === null) {
            return 'no_value';
        }

        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
        if ($phoneNumberId === null) {
            return 'no_phone_number_id';
        }

        // Ignore status callbacks (delivered/read) — only handle inbound messages.
        $message = $value['messages'][0] ?? null;
        if ($message === null) {
            return 'no_message';
        }

        // 1. phone_number_id -> tenant
        $whatsapp = TenantWhatsApp::findByPhoneNumberId((string) $phoneNumberId);
        if ($whatsapp === null) {
            return 'unknown_number';
        }

        $tenantId = (int) $whatsapp['tenant_id'];
        $tenant = Tenant::find($tenantId);
        if ($tenant === null || $tenant['status'] === 'suspended') {
            return 'tenant_inactive';
        }

        $from = (string) ($message['from'] ?? '');
        $text = self::extractText($message);
        if ($from === '') {
            return 'no_from';
        }

        // Log inbound before anything else.
        self::logIn($tenantId, $from, $text);

        // 2. Decide which bot this message is for.
        $botType = $whatsapp['bot_type'] ?? 'both';
        $target = self::pickBot($botType, $text, $tenantId, $from);
        if ($target === null) {
            return 'no_bot_for_number';
        }

        // 3. Gate check — the chosen service must be active for this tenant.
        //    SANDBOX exception: if the service is in sandbox and the sender is one
        //    of the tenant's own test numbers, let it through so they can try the
        //    full flow before going live. Everyone else is still gate-locked.
        $serviceKey = $target === 'support' ? 'support_bot' : 'order_bot';
        if (!Subscription::isServiceActive($tenantId, $serviceKey)) {
            if (Subscription::isSandbox($tenantId, $serviceKey) && self::isTestNumber($tenantId, $from)) {
                // sandbox self-test — allowed. Fall through to dispatch.
            } else {
                // If a "both" number's chosen bot is locked but the other is
                // active (or sandbox-testable), fall back to it.
                $other = $target === 'support' ? 'order' : 'support';
                $otherKey = $other === 'support' ? 'support_bot' : 'order_bot';
                $otherOk = Subscription::isServiceActive($tenantId, $otherKey)
                    || (Subscription::isSandbox($tenantId, $otherKey) && self::isTestNumber($tenantId, $from));
                if ($botType === 'both' && $otherOk) {
                    $target = $other;
                    $serviceKey = $otherKey;
                } else {
                    self::sendPaused($tenant, $whatsapp, $from);

                    return 'gate_locked';
                }
            }
        }

        // 3b. Spam protection (staff numbers bypass). Uses the chosen bot's settings.
        $spam = BotSettings::get($tenantId, $target)['spam'] ?? [];
        $isStaff = BotSettings::isStaff($tenantId, $target, $from);
        if (!empty($spam['enabled']) && !$isStaff) {
            $threshold = (int) ($spam['repeat_threshold'] ?? 3);
            $window = (int) ($spam['window_minutes'] ?? 5) * 60;
            if (!RateLimit::hit("bot:{$tenantId}:{$from}", 'bot_msg', $threshold, $window)) {
                return 'spam_blocked';
            }
        }

        // 4. Dispatch.
        $footer = '© ' . ($tenant['business_name'] ?? '');
        $wa = WhatsAppCloudClient::forTenant($whatsapp, $tenantId, $footer);

        if (!empty($message['id'])) {
            $wa->markReadWithTyping((string) $message['id']);
        }

        if ($target === 'support') {
            (new SupportBotHandler($tenant, $wa))->handle($from, $text);

            return 'handled_support';
        }

        (new OrderBotHandler($tenant, $wa))->handle($from, $text);

        return 'handled_order';
    }

    /**
     * Decide which bot handles a message for a given number.
     *   order  -> order only; support -> support only.
     *   both   -> stay in whichever bot the customer is mid-conversation with;
     *             otherwise route explicit support words to the (menu-driven)
     *             Support Bot, and everything else to the Order Bot.
     * Returns 'order', 'support', or null (number has no bot capability).
     */
    private static function pickBot(string $botType, string $text, int $tenantId = 0, string $from = ''): ?string
    {
        if ($botType === 'order') {
            return 'order';
        }
        if ($botType === 'support') {
            return 'support';
        }
        if ($botType === 'both') {
            // Conversation stickiness: if the customer has an ACTIVE (non-idle)
            // flow with one bot, keep them there so a bare "1" or an order id
            // isn't misrouted.
            $orderConvo = BotConversation::get($tenantId, $from, 'order');
            if ($orderConvo !== null && ($orderConvo['state'] ?? 'IDLE') !== 'IDLE') {
                return 'order';
            }
            $supportConvo = BotConversation::get($tenantId, $from, 'support');
            if ($supportConvo !== null && ($supportConvo['state'] ?? 'IDLE') !== 'IDLE') {
                return 'support';
            }

            // Fresh message: explicit support words open the Support Bot menu.
            $first = mb_strtolower(strtok(trim($text), " \t"));

            return in_array($first, ['support', 'help', 'msaada', 'agent', 'refill', 'status', 'cancel', 'speedup'], true) ? 'support' : 'order';
        }

        return null;
    }

    /** Pull a usable text/interactive-id out of a Meta message object. */
    private static function extractText(array $message): string
    {
        $type = $message['type'] ?? 'text';

        return match ($type) {
            'text' => (string) ($message['text']['body'] ?? ''),
            'interactive' => (string) (
                $message['interactive']['list_reply']['id']
                ?? $message['interactive']['button_reply']['id']
                ?? ''
            ),
            'button' => (string) ($message['button']['payload'] ?? $message['button']['text'] ?? ''),
            default => '',
        };
    }

    /**
     * Is this sender one of the tenant's registered test numbers? Test numbers
     * live in the tenant's bot settings (shop.test_numbers) and let the reseller
     * try a sandbox service before going live. Digits-only compare.
     */
    private static function isTestNumber(int $tenantId, string $from): bool
    {
        $fromDigits = preg_replace('/\D/', '', $from);
        if ($fromDigits === '') {
            return false;
        }
        foreach (['order', 'support'] as $bot) {
            $nums = BotSettings::get($tenantId, $bot)['shop']['test_numbers'] ?? [];
            foreach ((array) $nums as $n) {
                if (preg_replace('/\D/', '', (string) $n) === $fromDigits) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function sendPaused(array $tenant, array $whatsapp, string $from): void
    {
        $token = TenantWhatsApp::token($whatsapp);
        if ($token === null || $token === '') {
            return; // can't reply without a token — stay silent
        }
        $wa = WhatsAppCloudClient::forTenant($whatsapp, (int) $tenant['id'], '© ' . ($tenant['business_name'] ?? ''));
        $wa->sendText($from, "⏸️ This service is currently paused. Please try again later.", 'SUBSCRIPTION_EXPIRED');
    }

    private static function logIn(int $tenantId, string $from, string $text): void
    {
        try {
            $stmt = DB::conn()->prepare(
                "INSERT INTO bot_messages (tenant_id, customer_phone, direction, message)
                 VALUES (?, ?, 'in', ?)"
            );
            $stmt->execute([$tenantId, $from, $text]);
        } catch (\Throwable $e) {
            error_log('[BotRouter] logIn failed: ' . $e->getMessage());
        }
    }
}
