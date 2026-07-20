<?php

require_once __DIR__ . '/../../models/BotConversation.php';
require_once __DIR__ . '/../../models/BotOrder.php';
require_once __DIR__ . '/../../models/BotCustomer.php';
require_once __DIR__ . '/../../models/BotService.php';
require_once __DIR__ . '/../../models/TenantPanel.php';
require_once __DIR__ . '/../../models/BotSettings.php';
require_once __DIR__ . '/../SmmProviderClient.php';
require_once __DIR__ . '/../BotMessenger.php';
require_once __DIR__ . '/../WalletTopup.php';

/**
 * OrderBotHandler — wallet-based order-placement state machine over WhatsApp
 * (and Telegram, via BotMessenger). Ported from kuzapanel-bot and made
 * multi-tenant: everything is scoped to the tenant whose number received the
 * message.
 *
 * Flow:
 *   (hi) -> SELECT_PLATFORM -> SELECT_SERVICE -> SELECT_QTY -> SEND_LINK
 *        -> CONFIRM -> [wallet check]
 *              enough  -> debit wallet + place order on the tenant's panel
 *              short   -> offer top-up (handed to WalletTopup, Awamu 4)
 *
 * Services + prices come from bot_services (the tenant's own catalogue and
 * their own my_price). The customer has a wallet (bot_customers.balance) that
 * placing an order debits. Currency is the tenant's chosen currency.
 */
class OrderBotHandler
{
    private const BOT = 'order';

    /** Quantity presets offered as a list (filtered by the service's min/max). */
    private const QTY_PACKAGES = [100, 500, 1000, 2000, 5000, 10000, 50000, 100000];

    private array $tenant;
    private int $tenantId;
    private BotMessenger $wa;
    private string $currency;

    public function __construct(array $tenant, BotMessenger $wa)
    {
        $this->tenant = $tenant;
        $this->tenantId = (int) $tenant['id'];
        $this->wa = $wa;
        $this->currency = BotSettings::get($this->tenantId, self::BOT)['shop']['currency'] ?? 'USD';
    }

    public function handle(string $from, string $text): void
    {
        $text = trim($text);
        $lower = mb_strtolower($text);

        // Ensure the customer + wallet exist for this tenant.
        BotCustomer::getOrCreate($this->tenantId, $from);

        $convo = BotConversation::get($this->tenantId, $from, self::BOT);
        $state = $convo['state'] ?? 'IDLE';
        $ctx = $convo['context'] ?? [];

        // Global reset keywords.
        if (in_array($lower, ['hi', 'hello', 'menu', 'start', 'habari', 'mambo', '#'], true) || $state === 'IDLE') {
            $this->startFlow($from);

            return;
        }

        switch ($state) {
            case 'SELECT_PLATFORM':
                $this->onPlatformChosen($from, $text);
                break;

            case 'SELECT_CATEGORY':
                $this->onCategoryChosen($from, $text, $ctx);
                break;

            case 'SELECT_SERVICE':
                $this->onServiceChosen($from, $text, $ctx);
                break;

            case 'SELECT_QTY':
                $this->onQuantityChosen($from, $text, $ctx);
                break;

            case 'SEND_LINK':
                $this->onLink($from, $text, $ctx);
                break;

            case 'CONFIRM':
                $this->onConfirm($from, $lower, $ctx);
                break;

            case 'TOPUP_DECISION':
                $this->onTopupDecision($from, $lower, $ctx);
                break;

            case 'TOPUP_PHONE':
                WalletTopup::onPhone($this->tenant, $this->wa, $from, $text, $ctx);
                break;

            case 'AWAITING_BINANCE_ORDER':
                // Customer is reporting their Binance Order ID for verification.
                WalletTopup::verifyBinanceOrder($this->tenant, $this->wa, $from, $text, $ctx);
                break;

            case 'AWAITING_PAYMENT':
                // Waiting for the gateway webhook. A message here means the
                // customer is checking in — reassure them.
                $this->wa->sendText($from, "⏳ Waiting for your payment to be confirmed. Approve the prompt on your phone, or send *hi* to cancel and start over.");
                break;

            default:
                $this->startFlow($from);
        }
    }

    // ---- Step 1: platforms -------------------------------------------------

    private function startFlow(string $from): void
    {
        $platforms = BotService::activePlatforms($this->tenantId);

        if ($platforms === []) {
            $this->wa->sendText($from, "⚠️ This store isn't set up yet. Please try again later.");
            BotConversation::reset($this->tenantId, $from, self::BOT);

            return;
        }

        $rows = [];
        foreach (array_slice($platforms, 0, 10) as $p) {
            $rows[] = ['id' => 'plat_' . $p, 'title' => $p, 'description' => ''];
        }

        BotConversation::set($this->tenantId, $from, self::BOT, 'SELECT_PLATFORM', []);
        $welcome = "👋 Welcome to *{$this->tenant['business_name']}*!\nChoose a platform:";
        $this->wa->sendList($from, $welcome, 'View platforms', 'Platforms', $rows, 'WELCOME');
    }

    private function onPlatformChosen(string $from, string $text): void
    {
        $platform = str_starts_with($text, 'plat_') ? substr($text, 5) : $text;

        if (BotService::activeByPlatform($this->tenantId, $platform) === []) {
            $this->wa->sendText($from, "Please pick a platform from the list. Send *hi* to see it again.");

            return;
        }

        // WhatsApp lists cap at 10 rows, so a platform with many services is split
        // by category. Show a category picker only when it actually helps: more
        // than one named category (and no more than 10 to fit the list). One
        // category, or none, skips straight to the service list.
        $categories = BotService::categoriesByPlatform($this->tenantId, $platform);
        $uncategorised = BotService::uncategorisedCount($this->tenantId, $platform);
        $buckets = count($categories) + ($uncategorised > 0 ? 1 : 0);

        if (count($categories) > 1 && $buckets <= 10) {
            $rows = [];
            foreach ($categories as $cat) {
                $rows[] = ['id' => 'cat_' . rawurlencode($cat), 'title' => mb_substr($cat, 0, 24), 'description' => ''];
            }
            if ($uncategorised > 0) {
                $rows[] = ['id' => 'cat_', 'title' => 'Other', 'description' => ''];
            }
            BotConversation::set($this->tenantId, $from, self::BOT, 'SELECT_CATEGORY', ['platform' => $platform]);
            $this->wa->sendList($from, "*{$platform}* — choose a category:", 'Categories', 'Categories', $rows, 'SELECT_CATEGORY');

            return;
        }

        // No useful category split — go straight to services for the platform.
        $this->sendServiceList($from, $platform, null, BotService::activeByPlatform($this->tenantId, $platform));
    }

    // ---- Step 1b: category (only when a platform has several) --------------

    private function onCategoryChosen(string $from, string $text, array $ctx): void
    {
        $platform = $ctx['platform'] ?? '';
        $category = str_starts_with($text, 'cat_') ? rawurldecode(substr($text, 4)) : $text;

        $services = BotService::activeByPlatformCategory($this->tenantId, $platform, $category);
        if ($services === []) {
            $this->wa->sendText($from, "Please pick a category from the list. Send *hi* to start over.");

            return;
        }

        $this->sendServiceList($from, $platform, $category !== '' ? $category : null, $services);
    }

    /** Build + send the service list (max 10) and set SELECT_SERVICE state. */
    private function sendServiceList(string $from, string $platform, ?string $category, array $services): void
    {
        $rows = [];
        $index = [];
        foreach (array_slice($services, 0, 10) as $s) {
            $rows[] = [
                'id' => 'svc_' . $s['id'],
                'title' => mb_substr($s['name'], 0, 24),
                'description' => $this->money(($s['my_price'] / 1000)) . " / 1k",
            ];
            $index[(string) $s['id']] = [
                'id' => (int) $s['id'],
                'name' => $s['name'],
                'my_price' => (float) $s['my_price'],
                'min' => (int) $s['min_quantity'],
                'max' => (int) $s['max_quantity'],
                'unit' => $s['unit_label'],
                'panel_id' => $s['panel_id'] !== null ? (int) $s['panel_id'] : null,
                'provider_service_id' => $s['provider_service_id'],
            ];
        }

        BotConversation::set($this->tenantId, $from, self::BOT, 'SELECT_SERVICE', [
            'platform' => $platform,
            'category' => $category,
            'services' => $index,
        ]);
        $heading = $category !== null ? "*{$platform} · {$category}*" : "*{$platform}*";
        $this->wa->sendList($from, "{$heading} — choose a service:", 'Services', 'Services', $rows, 'SELECT_SERVICE');
    }

    // ---- Step 2: service ---------------------------------------------------

    private function onServiceChosen(string $from, string $text, array $ctx): void
    {
        $serviceId = str_starts_with($text, 'svc_') ? substr($text, 4) : $text;
        $service = $ctx['services'][$serviceId] ?? null;

        if ($service === null) {
            $this->wa->sendText($from, "Please pick a service from the list. Send *hi* to start over.");

            return;
        }

        $ctx['service'] = $service;
        BotConversation::set($this->tenantId, $from, self::BOT, 'SELECT_QTY', $ctx);
        $this->sendQuantityChoices($from, $service);
    }

    // ---- Step 3: quantity --------------------------------------------------

    private function sendQuantityChoices(string $from, array $service): void
    {
        $rows = [];
        foreach (self::QTY_PACKAGES as $qty) {
            if ($qty < $service['min'] || $qty > $service['max']) {
                continue;
            }
            $label = $qty >= 1000 ? number_format($qty / 1000, 0) . 'K' : (string) $qty;
            $rows[] = [
                'id' => 'qty_' . $qty,
                'title' => $label . ' ' . $service['unit'],
                'description' => $this->money(($service['my_price'] / 1000) * $qty),
            ];
        }
        $rows[] = ['id' => 'qty_custom', 'title' => 'Custom amount', 'description' => "{$service['min']} – {$service['max']}"];

        $this->wa->sendList($from, "How many *{$service['name']}*?", 'Packages', 'Quantity', $rows, 'SELECT_QTY');
    }

    private function onQuantityChosen(string $from, string $text, array $ctx): void
    {
        $service = $ctx['service'];
        $choice = str_starts_with($text, 'qty_') ? substr($text, 4) : $text;

        if ($choice === 'custom') {
            $this->wa->sendText($from, "🔢 Enter a quantity ({$service['min']} – {$service['max']}).");
            // Stay in SELECT_QTY; next numeric message is parsed below.
            return;
        }

        $qty = (int) preg_replace('/[^0-9]/', '', $choice);
        if ($qty < $service['min'] || $qty > $service['max']) {
            $this->wa->sendText($from, "Please enter a quantity between {$service['min']} and {$service['max']}.");

            return;
        }

        $ctx['quantity'] = $qty;
        BotConversation::set($this->tenantId, $from, self::BOT, 'SEND_LINK', $ctx);
        $this->wa->sendText($from, "🔗 Send the *link* for *{$service['name']}* (profile or post URL).", 'SEND_LINK');
    }

    // ---- Step 4: link ------------------------------------------------------

    private function onLink(string $from, string $text, array $ctx): void
    {
        if (!filter_var($text, FILTER_VALIDATE_URL)) {
            $this->wa->sendText($from, "That doesn't look like a valid link. Please send the full URL (https://…).");

            return;
        }

        $service = $ctx['service'];
        $qty = (int) $ctx['quantity'];
        $amount = round(($service['my_price'] / 1000) * $qty, 2);

        $ctx['link'] = $text;
        $ctx['amount'] = $amount;
        BotConversation::set($this->tenantId, $from, self::BOT, 'CONFIRM', $ctx);

        $this->wa->sendButtons(
            $from,
            "✅ Confirm your order:\n\n*{$service['name']}*\nLink: {$text}\nQuantity: " . number_format($qty) . "\nTotal: *{$this->money($amount)}*",
            [
                ['id' => 'confirm_yes', 'title' => 'Confirm'],
                ['id' => 'confirm_no', 'title' => 'Cancel'],
            ]
        );
    }

    // ---- Step 5: confirm -> wallet -----------------------------------------

    private function onConfirm(string $from, string $lower, array $ctx): void
    {
        if (!in_array($lower, ['confirm_yes', 'confirm', 'yes', 'ndio', 'ndiyo'], true)) {
            BotConversation::reset($this->tenantId, $from, self::BOT);
            $this->wa->sendText($from, "❌ Order cancelled. Send *hi* to start again.", 'GOODBYE');

            return;
        }

        $customer = BotCustomer::getOrCreate($this->tenantId, $from);
        $amount = (float) $ctx['amount'];

        if ((float) $customer['balance'] >= $amount) {
            $this->completeFromWallet($from, $customer, $ctx);

            return;
        }

        // Not enough balance — offer to top up (Awamu 4 wires the gateways).
        $shortfall = round($amount - (float) $customer['balance'], 2);
        $ctx['shortfall'] = $shortfall;
        BotConversation::set($this->tenantId, $from, self::BOT, 'TOPUP_DECISION', $ctx);
        $this->wa->sendButtons(
            $from,
            "💰 Your balance is {$this->money((float) $customer['balance'])}, but this order costs {$this->money($amount)}.\nYou need {$this->money($shortfall)} more.",
            [
                ['id' => 'topup_yes', 'title' => 'Top up & pay'],
                ['id' => 'topup_no', 'title' => 'Cancel'],
            ]
        );
    }

    private function completeFromWallet(string $from, array $customer, array $ctx): void
    {
        $service = $ctx['service'];
        $amount = (float) $ctx['amount'];

        if (!BotCustomer::debit((int) $customer['id'], $amount)) {
            $this->wa->sendText($from, "⚠️ Couldn't charge your wallet. Please try again.");
            BotConversation::reset($this->tenantId, $from, self::BOT);

            return;
        }

        $orderId = BotOrder::createWallet([
            'tenant_id' => $this->tenantId,
            'panel_id' => $service['panel_id'],
            'customer_phone' => $from,
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

        $this->submitToPanel($orderId, $service, $ctx);
        BotConversation::reset($this->tenantId, $from, self::BOT);

        $order = BotOrder::find((int) $orderId);
        $number = !empty($order['provider_order_id']) ? $order['provider_order_id'] : $orderId;

        $this->wa->sendText(
            $from,
            "✅ Order *#{$number}* placed!\n\n*{$service['name']}*\nQuantity: " . number_format((int) $ctx['quantity']) . "\nCharged: {$this->money($amount)}\nNew balance: {$this->money((float) $customer['balance'] - $amount)}\n\nSend *hi* to order again.",
            'CONFIRMED'
        );

        $this->notifyStaff("🛒 New order *#{$number}*\n{$service['name']} × " . number_format((int) $ctx['quantity']) . "\nFrom: {$from}");
    }

    /** Forward the order to the tenant's panel (if one is linked). */
    private function submitToPanel(int $orderId, array $service, array $ctx): void
    {
        if (empty($service['panel_id'])) {
            return; // no panel linked; order stays pending for manual handling
        }

        $panel = TenantPanel::findForTenant((int) $service['panel_id'], $this->tenantId);
        if ($panel === null) {
            BotOrder::setError($orderId, 'Panel not found.');

            return;
        }

        $client = SmmProviderClient::fromPanel($panel);
        $result = $client->addOrder((string) $service['provider_service_id'], (string) $ctx['link'], (int) $ctx['quantity']);

        if (!empty($result['success'])) {
            BotOrder::setProviderOrder($orderId, (string) $result['order_id'], 'processing');
            BotOrder::setError($orderId, null);
        } else {
            BotOrder::setError($orderId, $result['message'] ?? 'Provider error');
        }
    }

    // ---- Top-up decision (handed to WalletTopup in Awamu 4) -----------------

    private function onTopupDecision(string $from, string $lower, array $ctx): void
    {
        if (!in_array($lower, ['topup_yes', 'yes', 'ndio', 'ndiyo'], true)) {
            BotConversation::reset($this->tenantId, $from, self::BOT);
            $this->wa->sendText($from, "❌ Order cancelled. Send *hi* to start again.");

            return;
        }

        // WalletTopup owns the gateway/phone sub-flow and, after payment, credits
        // the wallet and completes the pending order data carried in $ctx.
        WalletTopup::begin($this->tenant, $this->wa, $from, $ctx);
    }

    // ---- helpers -----------------------------------------------------------

    private function notifyStaff(string $message): void
    {
        $numbers = BotSettings::get($this->tenantId, self::BOT)['staff']['numbers'] ?? [];
        foreach ($numbers as $num) {
            $num = preg_replace('/\D/', '', (string) $num);
            if ($num !== '') {
                $this->wa->sendText($num, $message);
            }
        }
    }

    /** Format an amount in the tenant's currency. */
    private function money(float $amount): string
    {
        $n = $amount == (int) $amount ? number_format($amount, 0) : number_format($amount, 2);

        return $this->currency . ' ' . $n;
    }
}
