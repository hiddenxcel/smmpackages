<?php

require_once __DIR__ . '/../../models/TenantPanel.php';
require_once __DIR__ . '/../../models/BotOrder.php';
require_once __DIR__ . '/../../models/BotConversation.php';
require_once __DIR__ . '/../../models/BotSettings.php';
require_once __DIR__ . '/../../models/ResponseTemplate.php';
require_once __DIR__ . '/../../models/TenantAi.php';
require_once __DIR__ . '/../SmmProviderClient.php';
require_once __DIR__ . '/../GuaranteeMatcher.php';
require_once __DIR__ . '/../DeepSeekClient.php';
require_once __DIR__ . '/../BotMessenger.php';

/**
 * SupportBotHandler — menu-driven, AI-assisted support over WhatsApp/Telegram.
 *
 * The customer sends anything (hi / help / random) and gets a numbered Quick
 * Menu. They reply with a number to pick an action; most actions then ask for
 * an Order ID and act on the tenant's panel (refill is guarantee-gated). Two
 * actions are special: "Talk to a Human" notifies staff, and "AI FAQ" opens a
 * DeepSeek chat.
 *
 * State (bot_conversations, bot_type='support'):
 *   MENU        -> waiting for a menu number
 *   AWAIT_ORDER -> waiting for an order id for the chosen action (ctx.action)
 *   AI_FAQ      -> free AI chat (ctx.history)
 *
 * Global words at any step: 0 / menu -> main menu, back -> menu, cancel -> exit.
 */
class SupportBotHandler
{
    private const BOT = 'support';

    /** Menu actions in order. Key = number the customer types. */
    private const ACTIONS = [
        '1' => ['key' => 'refill',   'label' => '1️⃣ Refill',                 'needs_order' => true],
        '2' => ['key' => 'speedup',  'label' => '2️⃣ Speed Up',              'needs_order' => true],
        '3' => ['key' => 'cancel',   'label' => '3️⃣ Cancel',                'needs_order' => true],
        '4' => ['key' => 'partial',  'label' => '4️⃣ Partial / Fake Comp',   'needs_order' => true],
        '5' => ['key' => 'human',    'label' => '5️⃣ 👤 Talk to a Human Agent', 'needs_order' => false],
        '6' => ['key' => 'status',   'label' => '6️⃣ 📦 Order Status',        'needs_order' => true],
        '7' => ['key' => 'topup',    'label' => '7️⃣ 💸 Balance Top-Up Issue', 'needs_order' => false],
        '8' => ['key' => 'faq',      'label' => '8️⃣ ❓ AI FAQ',              'needs_order' => false],
    ];

    private array $tenant;
    private int $tenantId;
    private BotMessenger $wa;

    public function __construct(array $tenant, BotMessenger $wa)
    {
        $this->tenant = $tenant;
        $this->tenantId = (int) $tenant['id'];
        $this->wa = $wa;
    }

    public function handle(string $from, string $text): void
    {
        $text = trim($text);
        $lower = mb_strtolower($text);

        $convo = BotConversation::get($this->tenantId, $from, self::BOT);
        $state = $convo['state'] ?? 'IDLE';
        $ctx = $convo['context'] ?? [];

        // Global navigation words (except inside AI chat, where 'cancel' still exits).
        if (in_array($lower, ['cancel', 'exit', 'toka'], true)) {
            BotConversation::reset($this->tenantId, $from, self::BOT);
            $this->wa->sendText($from, "👋 Support closed. Send any message to open the menu again.");

            return;
        }
        if (in_array($lower, ['0', 'menu', 'back', 'hi', 'hello', 'help', 'habari', 'start'], true) || $state === 'IDLE') {
            $this->sendMenu($from);

            return;
        }

        switch ($state) {
            case 'MENU':
                $this->onMenuChoice($from, $text);
                break;

            case 'AWAIT_ORDER':
                $this->onOrderId($from, $text, $ctx);
                break;

            case 'AI_FAQ':
                $this->onAiChat($from, $text, $ctx);
                break;

            default:
                $this->sendMenu($from);
        }
    }

    // ---- Menu --------------------------------------------------------------

    private function sendMenu(string $from): void
    {
        BotConversation::set($this->tenantId, $from, self::BOT, 'MENU', []);

        $lines = [];
        foreach (self::ACTIONS as $a) {
            $lines[] = $a['label'];
        }

        $body = "📋 *Quick Menu (AI)*\n\n"
            . "Welcome to {$this->tenant['business_name']} — AI & Human Support\n"
            . "We solve your problems in seconds with our AI-powered support bot. 🤖\n\n"
            . "👇 Please choose an option:\n\n"
            . implode("\n", $lines)
            . "\n\nReply with *0* for Main Menu, *back* to go back, or *cancel* to exit.";

        $this->wa->sendText($from, $body, 'SUPPORT_MENU');
    }

    private function onMenuChoice(string $from, string $text): void
    {
        $choice = preg_replace('/[^0-9]/', '', $text);
        $action = self::ACTIONS[$choice] ?? null;

        if ($action === null) {
            $this->wa->sendText($from, "Please reply with a number from *1* to *8* (or *0* for the menu).");

            return;
        }

        // Command toggles: refill/status/cancel/speedup can be disabled by the tenant.
        if (in_array($action['key'], ['refill', 'status', 'cancel', 'speedup'], true)
            && !BotSettings::isCommandEnabled($this->tenantId, self::BOT, $action['key'])) {
            $this->wa->sendText($from, "That option isn't available. Reply *0* for the menu.");

            return;
        }

        // Actions that don't need an order id are handled immediately.
        switch ($action['key']) {
            case 'human':
                $this->connectHuman($from);
                return;
            case 'topup':
                $this->topupHelp($from);
                return;
            case 'faq':
                $this->startAiFaq($from);
                return;
        }

        // The rest ask for an order id.
        BotConversation::set($this->tenantId, $from, self::BOT, 'AWAIT_ORDER', ['action' => $action['key']]);
        $this->wa->sendText($from, "🔢 Please send the *Order ID* for *{$action['label']}*.\n(Reply *back* for the menu.)");
    }

    // ---- Order-id actions --------------------------------------------------

    private function onOrderId(string $from, string $text, array $ctx): void
    {
        $orderId = preg_replace('/[^A-Za-z0-9\-]/', '', $text);
        if ($orderId === '') {
            $this->wa->sendText($from, "That doesn't look like an order ID. Please send it again, or *back* for the menu.");

            return;
        }

        $action = $ctx['action'] ?? '';
        $panel = TenantPanel::forTenant($this->tenantId)[0] ?? null;

        // Actions that talk to the panel need one; 'partial' just notifies staff.
        if ($action !== 'partial' && $panel === null) {
            $this->wa->sendText($from, "⚠️ Support isn't fully set up yet. Please try again later.");
            BotConversation::reset($this->tenantId, $from, self::BOT);

            return;
        }

        $client = $panel !== null ? SmmProviderClient::fromPanel($panel) : null;

        match ($action) {
            'status'  => $this->doStatus($from, $client, $orderId),
            'refill'  => $this->doRefill($from, $client, $orderId),
            'cancel'  => $this->doCancel($from, $client, $orderId),
            'speedup' => $this->doSpeedup($from, $orderId),
            'partial' => $this->doPartial($from, $orderId),
            default   => $this->sendMenu($from),
        };

        // After acting, drop back to the menu for the next request.
        if ($action !== '') {
            BotConversation::set($this->tenantId, $from, self::BOT, 'MENU', []);
            $this->wa->sendText($from, "Reply *0* to see the menu again, or *cancel* to exit.");
        }
    }

    private function doStatus(string $from, ?SmmProviderClient $client, string $orderId): void
    {
        $res = $client->checkStatus($orderId);
        if (empty($res['success'])) {
            $this->wa->sendText($from, "❌ Order *#{$orderId}* not found: {$res['message']}", 'NOT_FOUND');

            return;
        }
        $msg = "📦 Order *#{$orderId}*\nStatus: *{$res['status']}*";
        if (($res['start_count'] ?? null) !== null) { $msg .= "\nStart: {$res['start_count']}"; }
        if (($res['remains'] ?? null) !== null) { $msg .= "\nRemaining: {$res['remains']}"; }
        $this->wa->sendText($from, $msg, 'STATUS_SUCCESS');
    }

    private function doRefill(string $from, ?SmmProviderClient $client, string $orderId): void
    {
        $serviceName = $this->lookupServiceName($orderId);
        $verdict = GuaranteeMatcher::forTenant($this->tenantId)->evaluate($serviceName ?? '');

        if (!$verdict['allowed']) {
            $this->wa->sendText($from, $this->msg('REFILL_NO_GUARANTEE', ['order_id' => $orderId],
                "🚫 Order *#{$orderId}* has no refill guarantee."), 'REFILL_NO_GUARANTEE');

            return;
        }

        $res = $client->refill($orderId);
        if (empty($res['success'])) {
            $this->wa->sendText($from, $this->msg('REFILL_ERROR', ['order_id' => $orderId, 'message' => $res['message']],
                "⚠️ Refill for *#{$orderId}* couldn't be submitted: {$res['message']}"), 'REFILL_ERROR');

            return;
        }

        $guarantee = $verdict['lifetime'] ? 'Lifetime ♾️' : ($verdict['days'] . ' days');
        $this->wa->sendText($from, $this->msg('REFILL_SUCCESS', ['order_id' => $orderId, 'guarantee' => $guarantee],
            "♻️ Refill for *#{$orderId}* submitted!\nGuarantee: {$guarantee} ✅"), 'REFILL_SUCCESS');
        $this->notifyStaff("♻️ Refill requested for *#{$orderId}* by {$from} (guarantee: {$guarantee})");
    }

    private function doCancel(string $from, ?SmmProviderClient $client, string $orderId): void
    {
        $res = $client->checkStatus($orderId);
        if (empty($res['success'])) {
            $this->wa->sendText($from, "❌ Order *#{$orderId}* not found.", 'CANCEL_INVALID');

            return;
        }
        $this->wa->sendText($from, "🗑️ Cancellation for *#{$orderId}* has been requested. Our team will confirm shortly.", 'CANCEL_SUCCESS');
        $this->notifyStaff("🗑️ Cancel requested for *#{$orderId}* by {$from}");
    }

    private function doSpeedup(string $from, string $orderId): void
    {
        $this->wa->sendText($from, "🚀 Speed-up for *#{$orderId}* requested. We'll prioritise it where possible.", 'SPEEDUP_SUCCESS');
        $this->notifyStaff("🚀 Speed-up requested for *#{$orderId}* by {$from}");
    }

    private function doPartial(string $from, string $orderId): void
    {
        $this->wa->sendText($from, "🧾 Thanks — a *partial / fake completion* report for *#{$orderId}* has been logged. Our team will review and compensate if eligible.", 'PARTIAL_LOGGED');
        $this->notifyStaff("🧾 Partial/Fake-comp report for *#{$orderId}* by {$from} — please review.");
    }

    // ---- Human + Top-up + AI FAQ -------------------------------------------

    private function connectHuman(string $from): void
    {
        $numbers = BotSettings::get($this->tenantId, self::BOT)['staff']['numbers'] ?? [];
        $agent = '';
        foreach ($numbers as $n) {
            $d = preg_replace('/\D/', '', (string) $n);
            if ($d !== '') { $agent = $d; break; }
        }

        if ($agent !== '') {
            $this->wa->sendText($from, "👤 Connecting you to a human agent.\nChat with us here: https://wa.me/{$agent}\n\nReply *0* for the menu.");
        } else {
            $this->wa->sendText($from, "👤 A human agent will reach out shortly. Reply *0* for the menu.");
        }
        $this->notifyStaff("🙋 Customer {$from} asked to talk to a human agent.");
        BotConversation::set($this->tenantId, $from, self::BOT, 'MENU', []);
    }

    private function topupHelp(string $from): void
    {
        $this->wa->sendText($from,
            "💸 *Balance / Top-Up Issue*\n\nIf your wallet top-up didn't reflect:\n"
            . "1. Wait 2–3 minutes — it's usually automatic after payment confirms.\n"
            . "2. If it still hasn't, reply *5* to talk to a human agent with your payment reference.\n\n"
            . "Reply *0* for the menu.",
            'TOPUP_HELP'
        );
        $this->notifyStaff("💸 Customer {$from} reported a top-up issue.");
        BotConversation::set($this->tenantId, $from, self::BOT, 'MENU', []);
    }

    private function startAiFaq(string $from): void
    {
        if (TenantAi::apiKey($this->tenantId) === null) {
            $this->wa->sendText($from, "❓ Our AI assistant isn't available right now. Reply *5* to talk to a human agent, or *0* for the menu.");
            BotConversation::set($this->tenantId, $from, self::BOT, 'MENU', []);

            return;
        }
        BotConversation::set($this->tenantId, $from, self::BOT, 'AI_FAQ', ['history' => []]);
        $this->wa->sendText($from, "🤖 *AI FAQ* — ask me anything about our services, orders, or payments.\n(Reply *back* to return to the menu, or *cancel* to exit.)");
    }

    private function onAiChat(string $from, string $text, array $ctx): void
    {
        $apiKey = TenantAi::apiKey($this->tenantId);
        if ($apiKey === null) {
            $this->sendMenu($from);

            return;
        }

        $history = $ctx['history'] ?? [];
        $client = new DeepSeekClient($apiKey, $this->tenant['business_name'] ?? 'our store');
        $result = $client->reply($history, $text, $this->tenant['lang'] ?? 'en');

        if (empty($result['success'])) {
            $this->wa->sendText($from, "⚠️ Couldn't reach the AI just now. Reply *5* for a human agent, or *back* for the menu.");

            return;
        }

        $this->wa->sendText($from, $result['reply']);

        $history[] = ['role' => 'user', 'content' => $text];
        $history[] = ['role' => 'assistant', 'content' => $result['reply']];
        $history = array_slice($history, -6);
        BotConversation::set($this->tenantId, $from, self::BOT, 'AI_FAQ', ['history' => $history]);
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

    private function lookupServiceName(string $providerOrderId): ?string
    {
        $stmt = DB::conn()->prepare(
            'SELECT service_name FROM bot_orders WHERE tenant_id = ? AND provider_order_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$this->tenantId, $providerOrderId]);
        $row = $stmt->fetch();

        return $row['service_name'] ?? null;
    }

    /** Resolve a tenant template (or fall back to $fallback). */
    private function msg(string $key, array $vars, string $fallback): string
    {
        $rendered = ResponseTemplate::render(
            $this->tenantId,
            $key,
            $vars + ['business' => $this->tenant['business_name'] ?? ''],
            $this->tenant['lang'] ?? 'en'
        );

        return $rendered ?? $fallback;
    }
}
