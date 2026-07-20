<?php

require_once __DIR__ . '/../models/Ticket.php';
require_once __DIR__ . '/../models/TicketMessage.php';
require_once __DIR__ . '/../models/TenantAi.php';
require_once __DIR__ . '/../models/Tenant.php';
require_once __DIR__ . '/../models/Subscription.php';
require_once __DIR__ . '/../models/TenantPanel.php';
require_once __DIR__ . '/SmmProviderClient.php';
require_once __DIR__ . '/DeepSeekClient.php';

/**
 * TicketService — orchestrates AI ticket support for a tenant.
 * Gate: the tenant's ai_tickets subscription must be active.
 */
class TicketService
{
    /** Subcategories the ticket form accepts (mirrors the Support Bot). */
    public const SUBCATEGORIES = [
        'refill'   => 'Refill',
        'speedup'  => 'Speed Up',
        'cancel'   => 'Cancel',
        'partial'  => 'Partial / Fake Complete',
        'status'   => 'Order Status',
        'topup'    => 'Top-Up Issue',
    ];

    /**
     * Create a structured (SMMGen-style) ticket from the form:
     * category (ai|human) + subcategory + order id + optional message.
     * For AI tickets, the panel order status is fetched and fed to DeepSeek
     * as context so the reply is grounded in the real order.
     *
     * @return array{ok:bool, ticket_id?:int, ai_reply?:?string, message?:string}
     */
    public static function createFromForm(
        int $tenantId,
        string $category,
        ?string $subcategory,
        string $orderRef,
        ?string $customer,
        string $message = ''
    ): array {
        if (!Subscription::isServiceActive($tenantId, 'ai_tickets')) {
            return ['ok' => false, 'message' => 'AI tickets not active for this tenant.'];
        }

        $tenant = Tenant::find($tenantId);
        if ($tenant === null || $tenant['status'] === 'suspended') {
            return ['ok' => false, 'message' => 'Tenant not available.'];
        }

        $category = $category === 'human' ? 'human' : 'ai';
        $subcategory = isset(self::SUBCATEGORIES[$subcategory]) ? $subcategory : null;
        $orderRef = trim($orderRef);
        if ($orderRef === '') {
            return ['ok' => false, 'message' => 'Order ID is required.'];
        }

        $subLabel = $subcategory ? self::SUBCATEGORIES[$subcategory] : 'Support';
        $subject = "{$subLabel} — Order {$orderRef}";

        $ticketId = Ticket::createStructured(
            $tenantId, $customer, $category, $subcategory, $orderRef, $subject
        );

        // The customer's opening message combines the request + any note.
        $opening = "[{$subLabel}] Order #{$orderRef}" . ($message !== '' ? "\n{$message}" : '');
        TicketMessage::add($ticketId, 'customer', $opening);

        // Human tickets just wait for staff.
        if ($category === 'human') {
            return ['ok' => true, 'ticket_id' => $ticketId, 'ai_reply' => null,
                    'message' => 'Ticket created. Our team will reply shortly.'];
        }

        // AI tickets: fetch the order status from the tenant's panel for context.
        $orderContext = self::orderContext($tenantId, $orderRef);

        $apiKey = TenantAi::apiKey($tenantId);
        if ($apiKey === null || $apiKey === '') {
            return ['ok' => true, 'ticket_id' => $ticketId, 'ai_reply' => null,
                    'message' => 'Ticket created. Our team will reply shortly.'];
        }

        $userPrompt = "A customer opened a support ticket.\n"
            . "Issue type: {$subLabel}\n"
            . "Order ID: {$orderRef}\n"
            . ($orderContext !== '' ? "Order status from our system: {$orderContext}\n" : "")
            . ($message !== '' ? "Customer note: {$message}\n" : "")
            . "Reply helpfully about this issue.";

        $client = new DeepSeekClient($apiKey, $tenant['business_name']);
        $res = $client->reply([], $userPrompt, $tenant['lang'] ?? 'en');

        if (empty($res['success'])) {
            return ['ok' => true, 'ticket_id' => $ticketId, 'ai_reply' => null,
                    'message' => $res['message'] ?? 'AI unavailable; staff will reply.'];
        }

        TicketMessage::add($ticketId, 'ai', $res['reply']);

        return ['ok' => true, 'ticket_id' => $ticketId, 'ai_reply' => $res['reply']];
    }

    /** Fetch a short order-status summary from the tenant's panel (best-effort). */
    private static function orderContext(int $tenantId, string $orderRef): string
    {
        $panel = TenantPanel::forTenant($tenantId)[0] ?? null;
        if ($panel === null) {
            return '';
        }
        try {
            $client = SmmProviderClient::fromPanel($panel);
            $res = $client->checkStatus($orderRef);
        } catch (\Throwable $e) {
            return '';
        }
        if (empty($res['success'])) {
            return 'order not found on panel';
        }
        $parts = ["status={$res['status']}"];
        if (($res['start_count'] ?? null) !== null) { $parts[] = "start={$res['start_count']}"; }
        if (($res['remains'] ?? null) !== null) { $parts[] = "remaining={$res['remains']}"; }

        return implode(', ', $parts);
    }

    /**
     * Add a customer message to a ticket (creating the ticket if needed) and,
     * if the AI is configured + gated on, generate + store an AI reply.
     *
     * @return array{ok:bool, ticket_id?:int, ai_reply?:?string, message?:string}
     */
    public static function customerMessage(int $tenantId, ?int $ticketId, ?string $customer, string $message, ?string $subject = null): array
    {
        if (!Subscription::isServiceActive($tenantId, 'ai_tickets')) {
            return ['ok' => false, 'message' => 'AI tickets not active for this tenant.'];
        }

        $tenant = Tenant::find($tenantId);
        if ($tenant === null || $tenant['status'] === 'suspended') {
            return ['ok' => false, 'message' => 'Tenant not available.'];
        }

        // Resolve or create the ticket (scoped to tenant).
        if ($ticketId !== null) {
            $ticket = Ticket::findForTenant($ticketId, $tenantId);
            if ($ticket === null) {
                return ['ok' => false, 'message' => 'Ticket not found.'];
            }
        } else {
            $ticketId = Ticket::create($tenantId, $customer, $subject ?: mb_substr($message, 0, 60));
        }

        // History BEFORE adding the new message (so it isn't duplicated).
        $history = TicketMessage::asChatHistory($ticketId);
        TicketMessage::add($ticketId, 'customer', $message);

        // AI reply.
        $apiKey = TenantAi::apiKey($tenantId);
        if ($apiKey === null || $apiKey === '') {
            return ['ok' => true, 'ticket_id' => $ticketId, 'ai_reply' => null, 'message' => 'No AI key; message stored for staff.'];
        }

        $client = new DeepSeekClient($apiKey, $tenant['business_name']);
        $res = $client->reply($history, $message, $tenant['lang'] ?? 'en');

        if (empty($res['success'])) {
            return ['ok' => true, 'ticket_id' => $ticketId, 'ai_reply' => null, 'message' => $res['message'] ?? 'AI unavailable.'];
        }

        TicketMessage::add($ticketId, 'ai', $res['reply']);

        return ['ok' => true, 'ticket_id' => $ticketId, 'ai_reply' => $res['reply']];
    }
}
