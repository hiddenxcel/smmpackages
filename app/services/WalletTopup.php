<?php

require_once __DIR__ . '/BotMessenger.php';
require_once __DIR__ . '/../models/BotConversation.php';
require_once __DIR__ . '/../models/BotCustomer.php';
require_once __DIR__ . '/../models/BotPayment.php';
require_once __DIR__ . '/../models/BotSettings.php';
require_once __DIR__ . '/../models/TenantPaymentGateway.php';
require_once __DIR__ . '/payments/GatewayRegistry.php';
require_once __DIR__ . '/payments/SnippeClient.php';
require_once __DIR__ . '/payments/NowPaymentsClient.php';
require_once __DIR__ . '/payments/BinancePayClient.php';

/**
 * WalletTopup — the wallet top-up + gateway payment sub-flow.
 *
 * When an order needs more balance (or a customer chooses "Top up"), the Order
 * Bot hands control here. This:
 *   1. picks the tenant's active gateway,
 *   2. asks for the payment phone,
 *   3. creates a pending bot_payments row and initiates the gateway push,
 *   4. stashes the pending order intent in the conversation context so the
 *      payment webhook (public/webhooks/bot-payment.php) can complete it.
 *
 * On webhook success the wallet is credited and the pending order placed — see
 * completeAfterPayment(), which the webhook calls.
 *
 * Note: this uses bot_type 'order' conversation rows (same channel as the order
 * flow) with a TOPUP_PHONE state so the customer's next message is their phone.
 */
class WalletTopup
{
    private const BOT = 'order';

    /**
     * Begin a top-up. $ctx carries the pending order (service/link/quantity/
     * amount/shortfall). We ask for the payment phone next.
     */
    public static function begin(array $tenant, BotMessenger $wa, string $from, array $ctx): void
    {
        $tenantId = (int) $tenant['id'];

        $gateway = self::pickGateway($tenantId);
        if ($gateway === null) {
            $wa->sendText($from, "⚠️ Online payment isn't set up for this store yet. Please contact support to add funds.");
            BotConversation::reset($tenantId, $from, self::BOT);

            return;
        }

        $ctx['gateway'] = $gateway['gateway'];
        $ctx['topup_amount'] = (float) ($ctx['shortfall'] ?? $ctx['amount'] ?? 0);

        // Mobile-money gateways need a payer phone; crypto/card ones return a
        // payment link, so we can initiate straight away.
        if (GatewayRegistry::needsPhone((string) $gateway['gateway'])) {
            $suggest = self::localPhone($from);
            BotConversation::set($tenantId, $from, self::BOT, 'TOPUP_PHONE', $ctx);
            $wa->sendButtons(
                $from,
                "📱 Enter the phone to pay from (mobile money). Use *{$suggest}* or send another number.",
                [
                    ['id' => 'pay_saved:' . $suggest, 'title' => '📱 ' . $suggest],
                    ['id' => 'pay_cancel', 'title' => 'Cancel'],
                ]
            );

            return;
        }

        // Crypto/card: no phone needed.
        self::initiate($tenant, $wa, $from, '', $ctx);
    }

    /**
     * Handle the payment-phone step (called by OrderBotHandler when state is
     * TOPUP_PHONE). $text is either a pay_saved:<phone> id, pay_cancel, or a
     * typed phone number.
     */
    public static function onPhone(array $tenant, BotMessenger $wa, string $from, string $text, array $ctx): void
    {
        $tenantId = (int) $tenant['id'];

        if ($text === 'pay_cancel') {
            $wa->sendText($from, "❌ Payment cancelled. Send *hi* to start again.");
            BotConversation::reset($tenantId, $from, self::BOT);

            return;
        }

        $payPhone = str_starts_with($text, 'pay_saved:') ? substr($text, 10) : $text;
        $payPhone = self::intlPhone($payPhone);

        if (strlen($payPhone) < 11) {
            $wa->sendText($from, "That phone number doesn't look right. Please send it again (e.g. 07XXXXXXXX).");

            return;
        }

        self::initiate($tenant, $wa, $from, $payPhone, $ctx);
    }

    /** Create the pending payment and trigger the gateway push. */
    private static function initiate(array $tenant, BotMessenger $wa, string $from, string $payPhone, array $ctx): void
    {
        $tenantId = (int) $tenant['id'];
        $customer = BotCustomer::getOrCreate($tenantId, $from);
        $amount = (float) ($ctx['topup_amount'] ?? 0);
        $currency = BotSettings::get($tenantId, self::BOT)['shop']['currency'] ?? 'TZS';

        $gatewayCfg = TenantPaymentGateway::find($tenantId, (string) ($ctx['gateway'] ?? ''));
        if ($gatewayCfg === null) {
            $wa->sendText($from, "⚠️ Payment gateway unavailable. Please contact support.");
            BotConversation::reset($tenantId, $from, self::BOT);

            return;
        }

        // Local reference used to correlate the webhook back to this payment.
        $ref = 'SMMTOP' . $tenantId . '-' . $customer['id'] . '-' . substr(bin2hex(random_bytes(4)), 0, 8);

        $paymentId = BotPayment::create($tenantId, [
            'type' => 'wallet_topup',
            'customer_id' => (int) $customer['id'],
            'gateway' => $gatewayCfg['gateway'],
            'transaction_ref' => $ref,
            'amount' => $amount,
        ]);

        // Stash the pending order so the webhook can place it after crediting.
        $ctx['payment_id'] = $paymentId;
        $ctx['pay_phone'] = $payPhone;
        BotConversation::set($tenantId, $from, self::BOT, 'AWAITING_PAYMENT', $ctx);
        BotCustomer::setLastPaymentPhone((int) $customer['id'], $payPhone);

        $client = self::client($gatewayCfg['gateway'], $gatewayCfg);
        $webhookUrl = self::webhookUrl();

        $result = $client->initiate([
            'order_id' => $ref,
            'phone' => $payPhone,
            'amount' => $amount,
            'currency' => $currency,
            'firstname' => $customer['name'] ?? 'Customer',
            'lastname' => '',
            'email' => '',
            'webhook_url' => $webhookUrl,
        ]);

        if (empty($result['success'])) {
            BotPayment::markFailed($paymentId);
            $wa->sendText($from, "⚠️ Couldn't start the payment: " . ($result['message'] ?? 'try again') . ".");
            BotConversation::reset($tenantId, $from, self::BOT);

            return;
        }

        // Some gateways return their own reference — track it as the ref the
        // webhook will send back, so we can correlate it.
        if (!empty($result['reference'])) {
            BotPayment::setTransactionRef($paymentId, (string) $result['reference']);
            $ctx['gateway_ref'] = (string) $result['reference'];
            BotConversation::set($tenantId, $from, self::BOT, 'AWAITING_PAYMENT', $ctx);
        }

        // Crypto/card gateways return a payment link; mobile-money pushes a USSD.
        $amountLabel = $currency . ' ' . number_format($amount, 0);
        if (!empty($result['redirect_url'])) {
            $wa->sendText(
                $from,
                "💳 To add *{$amountLabel}* to your wallet, complete the payment here:\n{$result['redirect_url']}\n\nYour order is placed automatically once payment is confirmed."
            );
        } else {
            $wa->sendText(
                $from,
                "💳 A payment request for *{$amountLabel}* was sent to *" . self::localPhone($payPhone) . "*.\nApprove it on your phone. Your order is placed automatically once payment is confirmed."
            );
        }
    }

    /**
     * Called by the payment webhook after a gateway confirms success. Credits
     * the wallet, pays any referral bonus on first deposit, and completes the
     * pending order carried in the conversation context. Idempotent via
     * BotPayment::markSuccess (replay guard).
     */
    public static function completeAfterPayment(int $paymentId): void
    {
        $payment = BotPayment::find($paymentId);
        if ($payment === null) {
            return;
        }

        // Replay guard: only the first success transition proceeds.
        if (!BotPayment::markSuccess($paymentId)) {
            return;
        }

        $tenantId = (int) $payment['tenant_id'];
        $customer = BotCustomer::find((int) $payment['customer_id']);
        if ($customer === null) {
            return;
        }

        BotCustomer::credit((int) $customer['id'], (float) $payment['amount']);
        self::payReferralBonus($tenantId, $customer, (float) $payment['amount']);

        // Complete the pending order, if the conversation still holds one.
        $convo = BotConversation::get($tenantId, $customer['phone'], self::BOT);
        if ($convo === null || ($convo['state'] ?? '') !== 'AWAITING_PAYMENT') {
            return;
        }
        $ctx = $convo['context'] ?? [];
        if (empty($ctx['service']) || empty($ctx['amount'])) {
            return;
        }

        $fresh = BotCustomer::find((int) $customer['id']);
        if ($fresh === null || (float) $fresh['balance'] < (float) $ctx['amount']) {
            return; // still short (partial top-up) — leave funds in wallet
        }

        // Reuse the order handler's wallet completion by re-driving CONFIRM.
        require_once __DIR__ . '/bots/OrderBotHandler.php';
        // The handler debits + places the order; we call its public confirm path
        // by setting state to CONFIRM and replaying a confirm. Simpler: place
        // directly here to avoid needing a messenger. We debit then place.
        self::placePendingOrder($tenantId, $fresh, $ctx);
    }

    /** Debit the wallet and place the pending order on the tenant's panel. */
    private static function placePendingOrder(int $tenantId, array $customer, array $ctx): void
    {
        require_once __DIR__ . '/../models/BotOrder.php';
        require_once __DIR__ . '/../models/TenantPanel.php';
        require_once __DIR__ . '/SmmProviderClient.php';

        $service = $ctx['service'];
        $amount = (float) $ctx['amount'];

        if (!BotCustomer::debit((int) $customer['id'], $amount)) {
            return;
        }

        $orderId = BotOrder::createWallet([
            'tenant_id' => $tenantId,
            'panel_id' => $service['panel_id'] ?? null,
            'customer_phone' => $customer['phone'],
            'customer_id' => (int) $customer['id'],
            'service_id' => $service['provider_service_id'],
            'service_name' => $service['name'],
            'link' => $ctx['link'],
            'quantity' => (int) $ctx['quantity'],
            'amount' => $amount,
            'payment_status' => 'paid',
            'paid_from' => 'wallet',
            'status' => 'pending',
        ]);

        if (!empty($service['panel_id'])) {
            $panel = TenantPanel::findForTenant((int) $service['panel_id'], $tenantId);
            if ($panel !== null) {
                $result = SmmProviderClient::fromPanel($panel)
                    ->addOrder((string) $service['provider_service_id'], (string) $ctx['link'], (int) $ctx['quantity']);
                if (!empty($result['success'])) {
                    BotOrder::setProviderOrder($orderId, (string) $result['order_id'], 'processing');
                } else {
                    BotOrder::setError($orderId, $result['message'] ?? 'Provider error');
                }
            }
        }

        BotConversation::reset($tenantId, $customer['phone'], self::BOT);
    }

    /** On a referred customer's first deposit, credit the referrer a % bonus. */
    private static function payReferralBonus(int $tenantId, array $customer, float $depositAmount): void
    {
        if ((int) $customer['first_deposit_done'] === 1) {
            return;
        }
        BotCustomer::markFirstDepositDone((int) $customer['id']);

        if ($customer['referred_by'] === null) {
            return;
        }
        $referrer = BotCustomer::find((int) $customer['referred_by']);
        if ($referrer === null) {
            return;
        }

        $percent = (float) (BotSettings::get($tenantId, self::BOT)['shop']['referral_percent'] ?? 0);
        $bonus = round($depositAmount * ($percent / 100), 2);
        if ($bonus > 0) {
            BotCustomer::creditReferralEarning((int) $referrer['id'], $bonus);
        }
    }

    // ---- gateway helpers ---------------------------------------------------

    /**
     * Pick the tenant's first active gateway that is actually WIRED (ready).
     * A tenant may have saved keys for a "coming soon" gateway; we skip those
     * so a customer is never sent to a gateway we can't drive yet.
     */
    private static function pickGateway(int $tenantId): ?array
    {
        foreach (TenantPaymentGateway::activeForTenant($tenantId) as $g) {
            if (GatewayRegistry::isReady((string) $g['gateway'])) {
                return $g;
            }
        }

        return null;
    }

    /**
     * Build the correct gateway client from the tenant's stored (decrypted)
     * config. Each gateway stores its two secrets in api_key + webhook_secret
     * (see GatewayRegistry field mapping); this adapts them to each client's
     * expected config shape.
     */
    private static function client(string $gateway, array $cfg): object
    {
        $key = $cfg['api_key'] ?? '';
        $secret = $cfg['webhook_secret'] ?? '';

        return match ($gateway) {
            'nowpayments' => new NowPaymentsClient(['api_key' => $key, 'ipn_secret' => $secret]),
            'binance' => new BinancePayClient(['api_key' => $key, 'api_secret' => $secret]),
            default => new SnippeClient(['api_key' => $key, 'webhook_secret' => $secret]),
        };
    }

    private static function webhookUrl(): string
    {
        $config = require __DIR__ . '/../../config/config.php';
        $base = rtrim($config['app']['url'] ?? '', '/');

        return $base . '/webhooks/bot-payment.php';
    }

    private static function localPhone(string $phone): string
    {
        $d = preg_replace('/\D/', '', $phone);

        return str_starts_with($d, '255') ? '0' . substr($d, 3) : $d;
    }

    private static function intlPhone(string $phone): string
    {
        $d = preg_replace('/\D/', '', $phone);

        return str_starts_with($d, '0') ? '255' . substr($d, 1) : $d;
    }
}
