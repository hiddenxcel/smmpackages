<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * TicketMessage — a message within a ticket. sender ∈ customer|ai|staff.
 */
class TicketMessage extends BaseModel
{
    public static function forTicket(int $ticketId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY id ASC');
        $stmt->execute([$ticketId]);

        return $stmt->fetchAll();
    }

    public static function add(int $ticketId, string $sender, string $message): int
    {
        $stmt = self::db()->prepare(
            'INSERT INTO ticket_messages (ticket_id, sender, message) VALUES (?, ?, ?)'
        );
        $stmt->execute([$ticketId, $sender, $message]);
        Ticket::touch($ticketId);

        return (int) self::db()->lastInsertId();
    }

    /** History as [{role, content}] for the AI, mapping sender -> chat role. */
    public static function asChatHistory(int $ticketId): array
    {
        $out = [];
        foreach (self::forTicket($ticketId) as $m) {
            $out[] = [
                'role' => $m['sender'] === 'customer' ? 'user' : 'assistant',
                'content' => $m['message'],
            ];
        }

        return $out;
    }
}
