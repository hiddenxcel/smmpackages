<?php

require_once __DIR__ . '/BaseModel.php';

class Plan extends BaseModel
{
    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM plans WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function findByCode(string $code): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM plans WHERE code = ?');
        $stmt->execute([$code]);

        return $stmt->fetch() ?: null;
    }

    /** All active plans, ordered for display. */
    public static function allActive(): array
    {
        $stmt = self::db()->query(
            "SELECT * FROM plans WHERE status = 'active' ORDER BY sort_order ASC, id ASC"
        );

        return $stmt->fetchAll();
    }

    /** The active plan for a given service (a la carte: one plan per service). */
    public static function forService(string $serviceKey): ?array
    {
        $stmt = self::db()->prepare(
            "SELECT * FROM plans WHERE service_key = ? AND status = 'active' ORDER BY sort_order ASC LIMIT 1"
        );
        $stmt->execute([$serviceKey]);

        return $stmt->fetch() ?: null;
    }
}
