<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * Ticket — a support ticket owned by a tenant, raised by one of their customers.
 * Always scoped by tenant_id.
 */
class Ticket extends BaseModel
{
    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM tickets WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM tickets WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);

        return $stmt->fetch() ?: null;
    }

    public static function forTenant(int $tenantId, ?string $status = null): array
    {
        if ($status !== null) {
            $stmt = self::db()->prepare('SELECT * FROM tickets WHERE tenant_id = ? AND status = ? ORDER BY updated_at DESC');
            $stmt->execute([$tenantId, $status]);
        } else {
            $stmt = self::db()->prepare('SELECT * FROM tickets WHERE tenant_id = ? ORDER BY updated_at DESC');
            $stmt->execute([$tenantId]);
        }

        return $stmt->fetchAll();
    }

    public static function create(int $tenantId, ?string $customerIdentifier, string $subject, string $priority = 'normal'): int
    {
        $stmt = self::db()->prepare(
            "INSERT INTO tickets (tenant_id, customer_identifier, subject, status, priority)
             VALUES (?, ?, ?, 'open', ?)"
        );
        $stmt->execute([$tenantId, $customerIdentifier, $subject, $priority]);

        return (int) self::db()->lastInsertId();
    }

    public static function setStatus(int $id, int $tenantId, string $status): bool
    {
        $stmt = self::db()->prepare('UPDATE tickets SET status = ? WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$status, $id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    public static function touch(int $id): void
    {
        self::db()->prepare('UPDATE tickets SET updated_at = NOW() WHERE id = ?')->execute([$id]);
    }

    public static function statusCounts(int $tenantId): array
    {
        $stmt = self::db()->prepare('SELECT status, COUNT(*) AS n FROM tickets WHERE tenant_id = ? GROUP BY status');
        $stmt->execute([$tenantId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['status']] = (int) $row['n'];
        }

        return $out;
    }
}
