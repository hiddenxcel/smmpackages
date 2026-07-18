<?php

require_once __DIR__ . '/../models/BaseModel.php';

/**
 * RateLimit — fixed-window limiter backed by the rate_limits table.
 * Used for login, register, webhooks, and bot messages.
 */
class RateLimit extends BaseModel
{
    /**
     * Register one attempt for identifier+action. Returns true if allowed,
     * false if the limit for the current window has been exceeded.
     *
     * @param int $max          max attempts per window
     * @param int $windowSeconds window length in seconds
     */
    public static function hit(string $identifier, string $action, int $max = 5, int $windowSeconds = 900): bool
    {
        $db = self::db();

        $stmt = $db->prepare(
            'SELECT id, attempts, window_start,
                    (window_start < DATE_SUB(NOW(), INTERVAL ? SECOND)) AS expired
             FROM rate_limits WHERE identifier = ? AND action = ? LIMIT 1'
        );
        $stmt->execute([$windowSeconds, $identifier, $action]);
        $row = $stmt->fetch();

        if ($row === false) {
            $db->prepare('INSERT INTO rate_limits (identifier, action, attempts, window_start) VALUES (?, ?, 1, NOW())')
               ->execute([$identifier, $action]);

            return true;
        }

        if ((int) $row['expired'] === 1) {
            $db->prepare('UPDATE rate_limits SET attempts = 1, window_start = NOW() WHERE id = ?')
               ->execute([$row['id']]);

            return true;
        }

        if ((int) $row['attempts'] >= $max) {
            return false;
        }

        $db->prepare('UPDATE rate_limits SET attempts = attempts + 1 WHERE id = ?')->execute([$row['id']]);

        return true;
    }

    /** Clear the counter (e.g. after a successful login). */
    public static function clear(string $identifier, string $action): void
    {
        self::db()->prepare('DELETE FROM rate_limits WHERE identifier = ? AND action = ?')
                  ->execute([$identifier, $action]);
    }
}
