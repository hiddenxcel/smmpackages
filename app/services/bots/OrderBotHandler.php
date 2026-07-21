<?php

require_once __DIR__ . '/../../models/BotConversation.php';
require_once __DIR__ . '/../../models/BotOrder.php';
require_once __DIR__ . '/../../models/BotCustomer.php';
require_once __DIR__ . '/../../models/BotService.php';
require_once __DIR__ . '/../../models/TenantPanel.php';
require_once __DIR__ . '/../../models/BotSettings.php';
require_once __DIR__ . '/../../models/Subscription.php';
require_once __DIR__ . '/../../models/TenantAi.php';
require_once __DIR__ . '/../../helpers/BotLang.php';
require_once __DIR__ . '/../SmmProviderClient.php';
require_once __DIR__ . '/../BotMessenger.php';
require_once __DIR__ . '/../WalletTopup.php';
require_once __DIR__ . '/../DeepSeekClient.php';

/**
 * OrderBotHandler — wallet-based, multi-language WhatsApp/Telegram bot with a
 * main menu (ported from kuzapanel-bot and made multi-tenant).
 *
 * Everything is scoped to the tenant whose number received the message. Every
 * customer-facing string is translated via BotLang in the customer's resolved
 * language: their saved bot_customers.lang, else the tenant's shop.lang default.
 *
 * "hi" -> MAIN_MENU (a 9-option list). From there:
 *   🛒 New Order  -> SELECT_PLATFORM -> SELECT_SERVICE -> SELECT_QTY -> SEND_LINK
 *                 -> CONFIRM -> [wallet check] pay-from-wallet OR top-up (WalletTopup)
 *   💰 Add Funds  -> TOPUP_AMOUNT -> WalletTopup (standalone wallet top-up)
 *   👤 Profile / 🎁 Referral / 📦 Track  -> informational replies
 *   🎧 Support    -> AI chat (if the tenant's ai_chat add-on is active) else admin number
 *   ⚙️ Settings   -> SELECT_LANG -> saves bot_customers.lang
 *   👥 Group / 🌐 Website -> tenant links (menu rows shown only if configured)
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
    private array $shop;
    private string $lang = BotLang::DEFAULT;

    public function __construct(array $tenant, BotMessenger $wa)
    {
        $this->tenant = $tenant;
        $this->tenantId = (int) $tenant['id'];
        $this->wa = $wa;
        $this->shop = BotSettings::get($this->tenantId, self::BOT)['shop'] ?? [];
        $this->currency = $this->shop['currency'] ?? 'USD';
    }

    public function handle(string $from, string $text): void
    {
        $text = trim($text);
        $lower = mb_strtolower($text);

        // Ensure the customer + wallet exist, and resolve their language once.
        $customer = BotCustomer::getOrCreate($this->tenantId, $from);
        $this->lang = BotLang::resolve($customer, $this->shop['lang'] ?? null);

        $convo = BotConversation::get($this->tenantId, $from, self::BOT);
        $state = $convo['state'] ?? 'IDLE';
        $ctx = $convo['context'] ?? [];

        // Global reset keywords -> main menu.
        if (in_array($lower, ['hi', 'hello', 'menu', 'start', 'habari', 'mambo', '#'], true) || $state === 'IDLE') {
            $this->sendMainMenu($from, $customer);

            return;
        }

        switch ($state) {
            case 'MAIN_MENU':
                $this->onMainMenu($from, $text, $customer);
                break;

            case 'SELECT_LANG':
                $this->onLanguageChosen($from, $text, $customer);
                break;

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
                $this->onConfirm($from, $lower, $ctx, $customer);
                break;

            case 'TOPUP_AMOUNT':
                $this->onTopupAmount($from, $text, $ctx);
                break;

            case 'TOPUP_DECISION':
                $this->onTopupDecision($from, $lower, $ctx);
                break;

            case 'TOPUP_PHONE':
                WalletTopup::onPhone($this->tenant, $this->wa, $from, $text, $ctx);
                break;

            case 'AWAITING_BINANCE_ORDER':
                WalletTopup::verifyBinanceOrder($this->tenant, $this->wa, $from, $text, $ctx);
                break;

            case 'AWAITING_PAYMENT':
                $this->wa->sendText($from, $this->t('awaiting_payment'));
                break;

            case 'AI_CHAT':
                $this->onAiChat($from, $text, $ctx);
                break;

            default:
                $this->sendMainMenu($from, $customer);
        }
    }

    /** Translate a key in the current customer's language. */
    private function t(string $key, array $vars = []): string
    {
        return BotLang::t($this->lang, $key, $vars);
    }

    // ---- Main menu ---------------------------------------------------------

    private function sendMainMenu(string $from, array $customer): void
    {
        $name = ($customer['name'] ?? '') !== '' ? $customer['name'] : $this->t('default_customer_name');
        $welcome = $this->t('menu_welcome', [
            'name_upper' => mb_strtoupper($name),
            'name' => $name,
            'business' => $this->tenant['business_name'] ?? '',
        ]);

        $rows = [
            ['id' => 'main:new_order', 'title' => $this->t('menu_new_order_title'), 'description' => $this->t('menu_new_order_desc')],
            ['id' => 'main:topup', 'title' => $this->t('menu_topup_title'), 'description' => $this->t('menu_topup_desc')],
            ['id' => 'main:profile', 'title' => $this->t('menu_profile_title'), 'description' => $this->t('menu_profile_desc')],
            ['id' => 'main:referral', 'title' => $this->t('menu_referral_title'), 'description' => $this->t('menu_referral_desc')],
            ['id' => 'main:track', 'title' => $this->t('menu_track_title'), 'description' => $this->t('menu_track_desc')],
            ['id' => 'main:support', 'title' => $this->t('menu_support_title'), 'description' => $this->t('menu_support_desc')],
            ['id' => 'main:settings', 'title' => $this->t('menu_settings_title'), 'description' => $this->t('menu_settings_desc')],
        ];
        // Optional tenant links — shown only when configured.
        if (trim((string) ($this->shop['group_url'] ?? '')) !== '') {
            $rows[] = ['id' => 'main:group', 'title' => $this->t('menu_group_title'), 'description' => $this->t('menu_group_desc')];
        }
        if (trim((string) ($this->shop['website_url'] ?? '')) !== '') {
            $rows[] = ['id' => 'main:website', 'title' => $this->t('menu_website_title'), 'description' => $this->t('menu_website_desc')];
        }

        BotConversation::set($this->tenantId, $from, self::BOT, 'MAIN_MENU', []);
        $this->wa->sendList($from, $welcome, $this->t('btn_open_menu'), $this->t('menu_header'), $rows, 'WELCOME');
    }

    private function onMainMenu(string $from, string $text, array $customer): void
    {
        $choice = str_starts_with($text, 'main:') ? substr($text, 5) : '';

        switch ($choice) {
            case 'new_order':
                $this->startOrderFlow($from);
                break;
            case 'topup':
                $this->startTopup($from);
                break;
            case 'profile':
                $this->sendProfile($from, $customer);
                break;
            case 'referral':
                $this->sendReferral($from, $customer);
                break;
            case 'track':
                $this->sendTracking($from);
                break;
            case 'support':
                $this->startSupport($from);
                break;
            case 'settings':
                $this->sendLanguageChooser($from);
                break;
            case 'group':
                $this->wa->sendText($from, $this->t('group_info', ['url' => $this->shop['group_url'] ?? '']));
                BotConversation::reset($this->tenantId, $from, self::BOT);
                break;
            case 'website':
                $this->wa->sendText($from, $this->t('website_info', ['url' => $this->shop['website_url'] ?? '']));
                BotConversation::reset($this->tenantId, $from, self::BOT);
                break;
            default:
                $this->wa->sendText($from, $this->t('not_understood_menu'));
        }
    }

    // ---- Settings / language -----------------------------------------------

    private function sendLanguageChooser(string $from): void
    {
        // WhatsApp button messages allow up to 3 buttons, so 5 languages go in a list.
        $rows = [];
        foreach (BotLang::SUPPORTED as $code) {
            $rows[] = ['id' => 'lang:' . $code, 'title' => $this->t('lang_name_' . $code), 'description' => ''];
        }
        BotConversation::set($this->tenantId, $from, self::BOT, 'SELECT_LANG', []);
        $this->wa->sendList($from, $this->t('settings_choose_language'), $this->t('menu_settings_title'), $this->t('menu_header'), $rows);
    }

    private function onLanguageChosen(string $from, string $text, array $customer): void
    {
        if (!str_starts_with($text, 'lang:')) {
            $this->wa->sendText($from, $this->t('settings_press_language'));

            return;
        }

        $chosen = BotLang::normalize(substr($text, 5));
        BotCustomer::setLang((int) $customer['id'], $chosen);
        $this->lang = $chosen;

        // Confirm in the newly chosen language, then reopen the menu in it.
        $this->wa->sendText($from, $this->t('language_changed'));
        $customer['lang'] = $chosen;
        $this->sendMainMenu($from, $customer);
    }

    // ---- Profile / Referral / Track ----------------------------------------

    private function sendProfile(string $from, array $customer): void
    {
        $this->wa->sendText($from, $this->t('profile', [
            'balance' => $this->money((float) $customer['balance']),
            'spent' => $this->money((float) ($customer['total_spent'] ?? 0)),
            'code' => $customer['referral_code'] ?? '—',
        ]));
        BotConversation::reset($this->tenantId, $from, self::BOT);
    }

    private function sendReferral(string $from, array $customer): void
    {
        $count = BotCustomer::countReferrals((int) $customer['id']);
        $this->wa->sendText($from, $this->t('referral_info', [
            'code' => $customer['referral_code'] ?? '—',
            'count' => $count,
            'earnings' => $this->money((float) ($customer['referral_earnings'] ?? 0)),
        ]));
        BotConversation::reset($this->tenantId, $from, self::BOT);
    }

    private function sendTracking(string $from): void
    {
        $orders = BotOrder::recentForCustomer($this->tenantId, $from, 5);
        if ($orders === []) {
            $this->wa->sendText($from, $this->t('track_none'));
            BotConversation::reset($this->tenantId, $from, self::BOT);

            return;
        }

        $body = $this->t('track_header');
        foreach ($orders as $o) {
            $number = !empty($o['provider_order_id']) ? $o['provider_order_id'] : $o['id'];
            $body .= $this->t('track_line', [
                'number' => $number,
                'service' => $o['service_name'] ?? '—',
                'status' => $o['status'] ?? 'pending',
                'amount' => $this->money((float) ($o['amount'] ?? 0)),
            ]);
        }
        $body .= $this->t('track_footer');

        $this->wa->sendText($from, $body);
        BotConversation::reset($this->tenantId, $from, self::BOT);
    }

    // ---- Support (AI add-on gated) -----------------------------------------

    private function startSupport(string $from): void
    {
        // The tenant picks how Support is handled: 'ai' (AI chat) or 'admin'.
        // AI is a paid add-on, so it only actually engages when chosen AND the
        // ai_chat subscription is active AND a DeepSeek key is set — otherwise we
        // fall back to handing the customer to the admin number.
        $aiActive = ($this->shop['support_mode'] ?? 'admin') === 'ai'
            && Subscription::isServiceActive($this->tenantId, 'ai_chat')
            && TenantAi::apiKey($this->tenantId) !== null;

        if ($aiActive) {
            BotConversation::set($this->tenantId, $from, self::BOT, 'AI_CHAT', ['history' => []]);
            $this->wa->sendText($from, $this->t('support_ai_intro'));

            return;
        }

        $agent = $this->firstStaffNumber();
        if ($agent !== '') {
            $this->wa->sendText($from, $this->t('support_human', ['url' => 'https://wa.me/' . $agent]));
        } else {
            $this->wa->sendText($from, $this->t('support_human_soon'));
        }
        BotConversation::reset($this->tenantId, $from, self::BOT);
    }

    private function onAiChat(string $from, string $text, array $ctx): void
    {
        $apiKey = TenantAi::apiKey($this->tenantId);
        if ($apiKey === null || !Subscription::isServiceActive($this->tenantId, 'ai_chat')) {
            $agent = $this->firstStaffNumber();
            $this->wa->sendText($from, $agent !== ''
                ? $this->t('support_human', ['url' => 'https://wa.me/' . $agent])
                : $this->t('support_human_soon'));
            BotConversation::reset($this->tenantId, $from, self::BOT);

            return;
        }

        $history = $ctx['history'] ?? [];
        $client = new DeepSeekClient($apiKey, $this->tenant['business_name'] ?? 'our store');
        $result = $client->reply($history, $text, $this->lang);

        if (empty($result['success'])) {
            $this->wa->sendText($from, $this->t('support_ai_unavailable'));

            return;
        }

        $this->wa->sendText($from, $result['reply']);
        $history[] = ['role' => 'user', 'content' => $text];
        $history[] = ['role' => 'assistant', 'content' => $result['reply']];
        $history = array_slice($history, -6);
        BotConversation::set($this->tenantId, $from, self::BOT, 'AI_CHAT', ['history' => $history]);
    }

    // ---- New order: platforms ----------------------------------------------

    private function startOrderFlow(string $from): void
    {
        $platforms = BotService::activePlatforms($this->tenantId);

        if ($platforms === []) {
            $this->wa->sendText($from, $this->t('store_not_ready'));
            BotConversation::reset($this->tenantId, $from, self::BOT);

            return;
        }

        $rows = [];
        foreach (array_slice($platforms, 0, 10) as $p) {
            $rows[] = ['id' => 'plat_' . $p, 'title' => $p, 'description' => ''];
        }

        BotConversation::set($this->tenantId, $from, self::BOT, 'SELECT_PLATFORM', []);
        $this->wa->sendList($from, $this->t('choose_platform'), $this->t('btn_platforms'), $this->t('platforms_header'), $rows, 'WELCOME');
    }

    private function onPlatformChosen(string $from, string $text): void
    {
        $platform = str_starts_with($text, 'plat_') ? substr($text, 5) : $text;

        if (BotService::activeByPlatform($this->tenantId, $platform) === []) {
            $this->wa->sendText($from, $this->t('pick_platform_again'));

            return;
        }

        $categories = BotService::categoriesByPlatform($this->tenantId, $platform);
        $uncategorised = BotService::uncategorisedCount($this->tenantId, $platform);
        $buckets = count($categories) + ($uncategorised > 0 ? 1 : 0);

        if (count($categories) > 1 && $buckets <= 10) {
            $rows = [];
            foreach ($categories as $cat) {
                $rows[] = ['id' => 'cat_' . rawurlencode($cat), 'title' => mb_substr($cat, 0, 24), 'description' => ''];
            }
            if ($uncategorised > 0) {
                $rows[] = ['id' => 'cat_', 'title' => $this->t('category_other'), 'description' => ''];
            }
            BotConversation::set($this->tenantId, $from, self::BOT, 'SELECT_CATEGORY', ['platform' => $platform]);
            $this->wa->sendList($from, $this->t('choose_category', ['platform' => $platform]), $this->t('categories_header'), $this->t('categories_header'), $rows, 'SELECT_CATEGORY');

            return;
        }

        $this->sendServiceList($from, $platform, null, BotService::activeByPlatform($this->tenantId, $platform));
    }

    private function onCategoryChosen(string $from, string $text, array $ctx): void
    {
        $platform = $ctx['platform'] ?? '';
        $category = str_starts_with($text, 'cat_') ? rawurldecode(substr($text, 4)) : $text;

        $services = BotService::activeByPlatformCategory($this->tenantId, $platform, $category);
        if ($services === []) {
            $this->wa->sendText($from, $this->t('pick_category_again'));

            return;
        }

        $this->sendServiceList($from, $platform, $category !== '' ? $category : null, $services);
    }

    private function sendServiceList(string $from, string $platform, ?string $category, array $services): void
    {
        $rows = [];
        $index = [];
        foreach (array_slice($services, 0, 10) as $s) {
            $rows[] = [
                'id' => 'svc_' . $s['id'],
                'title' => mb_substr($s['name'], 0, 24),
                'description' => $this->money(($s['my_price'] / 1000)) . ' ' . $this->t('per_1k'),
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
        $this->wa->sendList($from, $this->t('choose_service', ['heading' => $heading]), $this->t('btn_services'), $this->t('services_header'), $rows, 'SELECT_SERVICE');
    }

    // ---- New order: service ------------------------------------------------

    private function onServiceChosen(string $from, string $text, array $ctx): void
    {
        $serviceId = str_starts_with($text, 'svc_') ? substr($text, 4) : $text;
        $service = $ctx['services'][$serviceId] ?? null;

        if ($service === null) {
            $this->wa->sendText($from, $this->t('pick_service_again'));

            return;
        }

        $ctx['service'] = $service;
        BotConversation::set($this->tenantId, $from, self::BOT, 'SELECT_QTY', $ctx);
        $this->sendQuantityChoices($from, $service);
    }

    // ---- New order: quantity -----------------------------------------------

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
        $rows[] = ['id' => 'qty_custom', 'title' => $this->t('qty_custom_title'), 'description' => "{$service['min']} – {$service['max']}"];

        $this->wa->sendList($from, $this->t('how_many', ['service' => $service['name']]), $this->t('btn_packages'), $this->t('packages_header'), $rows, 'SELECT_QTY');
    }

    private function onQuantityChosen(string $from, string $text, array $ctx): void
    {
        $service = $ctx['service'];
        $choice = str_starts_with($text, 'qty_') ? substr($text, 4) : $text;

        if ($choice === 'custom') {
            $this->wa->sendText($from, $this->t('qty_custom_prompt', ['min' => $service['min'], 'max' => $service['max']]));

            return;
        }

        $qty = (int) preg_replace('/[^0-9]/', '', $choice);
        if ($qty < $service['min'] || $qty > $service['max']) {
            $this->wa->sendText($from, $this->t('qty_out_of_range', ['min' => $service['min'], 'max' => $service['max']]));

            return;
        }

        $ctx['quantity'] = $qty;
        BotConversation::set($this->tenantId, $from, self::BOT, 'SEND_LINK', $ctx);
        $this->wa->sendText($from, $this->t('send_link', ['service' => $service['name']]), 'SEND_LINK');
    }

    // ---- New order: link ---------------------------------------------------

    private function onLink(string $from, string $text, array $ctx): void
    {
        if (!filter_var($text, FILTER_VALIDATE_URL)) {
            $this->wa->sendText($from, $this->t('invalid_link'));

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
            $this->t('confirm_order', [
                'service' => $service['name'],
                'link' => $text,
                'qty' => number_format($qty),
                'total' => $this->money($amount),
            ]),
            [
                ['id' => 'confirm_yes', 'title' => $this->t('btn_confirm')],
                ['id' => 'confirm_no', 'title' => $this->t('btn_cancel')],
            ]
        );
    }

    // ---- New order: confirm -> wallet --------------------------------------

    private function onConfirm(string $from, string $lower, array $ctx, array $customer): void
    {
        if (!in_array($lower, ['confirm_yes', 'confirm', 'yes', 'ndio', 'ndiyo'], true)) {
            BotConversation::reset($this->tenantId, $from, self::BOT);
            $this->wa->sendText($from, $this->t('order_cancelled'), 'GOODBYE');

            return;
        }

        $customer = BotCustomer::getOrCreate($this->tenantId, $from);
        $amount = (float) $ctx['amount'];

        if ((float) $customer['balance'] >= $amount) {
            $this->completeFromWallet($from, $customer, $ctx);

            return;
        }

        $shortfall = round($amount - (float) $customer['balance'], 2);
        $ctx['shortfall'] = $shortfall;
        BotConversation::set($this->tenantId, $from, self::BOT, 'TOPUP_DECISION', $ctx);
        $this->wa->sendButtons(
            $from,
            $this->t('insufficient_balance', [
                'balance' => $this->money((float) $customer['balance']),
                'amount' => $this->money($amount),
                'shortfall' => $this->money($shortfall),
            ]),
            [
                ['id' => 'topup_yes', 'title' => $this->t('btn_topup_pay')],
                ['id' => 'topup_no', 'title' => $this->t('btn_cancel')],
            ]
        );
    }

    private function completeFromWallet(string $from, array $customer, array $ctx): void
    {
        $service = $ctx['service'];
        $amount = (float) $ctx['amount'];

        if (!BotCustomer::debit((int) $customer['id'], $amount)) {
            $this->wa->sendText($from, $this->t('wallet_charge_failed'));
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
            $this->t('order_placed', [
                'number' => $number,
                'service' => $service['name'],
                'qty' => number_format((int) $ctx['quantity']),
                'amount' => $this->money($amount),
                'balance' => $this->money((float) $customer['balance'] - $amount),
            ]),
            'CONFIRMED'
        );

        $this->notifyStaff("🛒 New order *#{$number}*\n{$service['name']} × " . number_format((int) $ctx['quantity']) . "\nFrom: {$from}");
    }

    private function submitToPanel(int $orderId, array $service, array $ctx): void
    {
        if (empty($service['panel_id'])) {
            return;
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

    // ---- Standalone wallet top-up (Add Funds menu) -------------------------

    private function startTopup(string $from): void
    {
        BotConversation::set($this->tenantId, $from, self::BOT, 'TOPUP_AMOUNT', []);
        $this->wa->sendText($from, $this->t('topup_prompt', [
            'cur' => $this->currency,
            'min' => $this->money((float) ($this->shop['min_topup'] ?? 1)),
        ]));
    }

    private function onTopupAmount(string $from, string $text, array $ctx): void
    {
        $amount = (float) preg_replace('/[^0-9.]/', '', str_replace(',', '', $text));
        $min = (float) ($this->shop['min_topup'] ?? 1);

        if ($amount < $min || $amount <= 0) {
            $this->wa->sendText($from, $this->t('topup_amount_invalid', [
                'min' => $this->money($min),
                'cur' => $this->currency,
            ]));

            return;
        }

        // Hand to WalletTopup as a standalone top-up (no pending order attached).
        $ctx['topup_amount'] = $amount;
        WalletTopup::begin($this->tenant, $this->wa, $from, $ctx);
    }

    // ---- Top-up decision after a short order --------------------------------

    private function onTopupDecision(string $from, string $lower, array $ctx): void
    {
        if (!in_array($lower, ['topup_yes', 'yes', 'ndio', 'ndiyo'], true)) {
            BotConversation::reset($this->tenantId, $from, self::BOT);
            $this->wa->sendText($from, $this->t('order_cancelled'));

            return;
        }

        WalletTopup::begin($this->tenant, $this->wa, $from, $ctx);
    }

    // ---- helpers -----------------------------------------------------------

    private function firstStaffNumber(): string
    {
        $numbers = BotSettings::get($this->tenantId, self::BOT)['staff']['numbers'] ?? [];
        foreach ($numbers as $n) {
            $d = preg_replace('/\D/', '', (string) $n);
            if ($d !== '') {
                return $d;
            }
        }

        return '';
    }

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
