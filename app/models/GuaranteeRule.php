<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * GuaranteeRule — per-tenant keyword rules that decide whether a service name
 * implies a refill guarantee (and for how many days). panel_id NULL = applies
 * to all of the tenant's panels.
 */
class GuaranteeRule extends BaseModel
{
    public static function forTenant(int $tenantId): array
    {
        $stmt = self::db()->prepare(
            "SELECT * FROM guarantee_rules WHERE tenant_id = ? AND status = 'active'
             ORDER BY rule_type, keyword"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll();
    }

    public static function create(int $tenantId, string $ruleType, string $keyword, ?int $refillDays, ?int $panelId = null): int
    {
        $stmt = self::db()->prepare(
            "INSERT INTO guarantee_rules (tenant_id, panel_id, rule_type, keyword, refill_days, status)
             VALUES (?, ?, ?, ?, ?, 'active')"
        );
        $stmt->execute([$tenantId, $panelId, $ruleType, trim($keyword), $refillDays]);

        return (int) self::db()->lastInsertId();
    }

    public static function delete(int $id, int $tenantId): bool
    {
        $stmt = self::db()->prepare('DELETE FROM guarantee_rules WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    /** Seed a sensible default rule set for a tenant (idempotent — only if none exist). */
    public static function seedDefaults(int $tenantId): void
    {
        $stmt = self::db()->prepare('SELECT COUNT(*) FROM guarantee_rules WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $noGuarantee = ['no refill', 'no guarantee', 'non refill', 'without guarantee'];
        foreach ($noGuarantee as $kw) {
            self::create($tenantId, 'no_guarantee', $kw, null);
        }

        $guarantee = [
            '7 days' => 7, '15 days' => 15, '20 days' => 20, '30 days' => 30,
            '60 days' => 60, '90 days' => 90, '365 days' => 365, 'lifetime' => 0,
        ];
        foreach ($guarantee as $kw => $days) {
            self::create($tenantId, 'guarantee', $kw, $days);
        }
    }
}
