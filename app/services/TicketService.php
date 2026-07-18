<?php

require_once __DIR__ . '/../models/Ticket.php';
require_once __DIR__ . '/../models/TicketMessage.php';
require_once __DIR__ . '/../models/TenantAi.php';
require_once __DIR__ . '/../models/Tenant.php';
require_once __DIR__ . '/../models/Subscription.php';
require_once __DIR__ . '/DeepSeekClient.php';

/**
 * TicketService — orchestrates AI ticket support for a tenant.
 * Gate: the tenant's ai_tickets subscription must be active.
 */
class TicketService
{
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
