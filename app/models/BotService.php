<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * BotService — a tenant's saleable service, priced by the tenant.
 *
 * Ported from kuzapanel-bot's Service model, made multi-tenant. Each service is
 * imported from the tenant's panel (provider_service_id links back to the panel
 * so orders can be forwarded), but my_price is what the TENANT charges their
 * customer — the tenant sets it themselves. cost_price is optional bookkeeping.
 *
 * Every query is scoped by tenant_id so a tenant only ever sees their own
 * catalogue.
 */
class BotService extends BaseModel
{
    public static function find(int $tenantId, int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM bot_services WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$tenantId, $id]);

        return $stmt->fetch() ?: null;
    }

    /** Distinct active platforms for this tenant, in display order. */
    public static function activePlatforms(int $tenantId): array
    {
        $stmt = self::db()->prepare(
            "SELECT platform FROM bot_services
             WHERE tenant_id = ? AND status = 'active'
             GROUP BY platform ORDER BY MIN(sort_order), platform"
        );
        $stmt->execute([$tenantId]);

        return array_column($stmt->fetchAll(), 'platform');
    }

    /** Active services on a platform for this tenant. */
    public static function activeByPlatform(int $tenantId, string $platform): array
    {
        $stmt = self::db()->prepare(
            "SELECT * FROM bot_services
             WHERE tenant_id = ? AND status = 'active' AND platform = ?
             ORDER BY sort_order, name"
        );
        $stmt->execute([$tenantId, $platform]);

        return $stmt->fetchAll();
    }

    /**
     * Distinct non-empty categories on a platform (for the category-picker step).
     * Returns category strings in display order. Services with a NULL/'' category
     * are grouped under a single bucket the caller labels (they don't appear here).
     */
    public static function categoriesByPlatform(int $tenantId, string $platform): array
    {
        $stmt = self::db()->prepare(
            "SELECT category FROM bot_services
             WHERE tenant_id = ? AND status = 'active' AND platform = ?
               AND category IS NOT NULL AND category <> ''
             GROUP BY category ORDER BY MIN(sort_order), category"
        );
        $stmt->execute([$tenantId, $platform]);

        return array_column($stmt->fetchAll(), 'category');
    }

    /** How many active services on a platform have NO category (the "other" bucket). */
    public static function uncategorisedCount(int $tenantId, string $platform): int
    {
        $stmt = self::db()->prepare(
            "SELECT COUNT(*) FROM bot_services
             WHERE tenant_id = ? AND status = 'active' AND platform = ?
               AND (category IS NULL OR category = '')"
        );
        $stmt->execute([$tenantId, $platform]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Active services on a platform within a category. Pass category '' (or null)
     * to get the uncategorised bucket.
     */
    public static function activeByPlatformCategory(int $tenantId, string $platform, ?string $category): array
    {
        if ($category === null || $category === '') {
            $stmt = self::db()->prepare(
                "SELECT * FROM bot_services
                 WHERE tenant_id = ? AND status = 'active' AND platform = ?
                   AND (category IS NULL OR category = '')
                 ORDER BY sort_order, name"
            );
            $stmt->execute([$tenantId, $platform]);
        } else {
            $stmt = self::db()->prepare(
                "SELECT * FROM bot_services
                 WHERE tenant_id = ? AND status = 'active' AND platform = ? AND category = ?
                 ORDER BY sort_order, name"
            );
            $stmt->execute([$tenantId, $platform, $category]);
        }

        return $stmt->fetchAll();
    }

    /** All services for this tenant (dashboard listing). */
    public static function allForTenant(int $tenantId): array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM bot_services WHERE tenant_id = ? ORDER BY sort_order, platform, name'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll();
    }

    public static function create(int $tenantId, array $data): int
    {
        $nextOrder = self::db()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM bot_services WHERE tenant_id = ?');
        $nextOrder->execute([$tenantId]);
        $sort = (int) $nextOrder->fetchColumn();

        $stmt = self::db()->prepare(
            'INSERT INTO bot_services
                (tenant_id, panel_id, provider_service_id, platform, category, name, unit_label,
                 cost_price, my_price, min_quantity, max_quantity, link_instructions, status, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tenantId,
            $data['panel_id'] ?? null,
            $data['provider_service_id'],
            $data['platform'],
            self::cleanCategory($data['category'] ?? null),
            $data['name'],
            $data['unit_label'] ?? 'Followers',
            $data['cost_price'] ?? null,
            $data['my_price'],
            $data['min_quantity'] ?? 1,
            $data['max_quantity'] ?? 100000,
            $data['link_instructions'] ?? null,
            $data['status'] ?? 'active',
            $data['sort_order'] ?? $sort,
        ]);

        return (int) self::db()->lastInsertId();
    }

    /** Update mutable fields (tenant-scoped). Only provided keys change. */
    public static function update(int $tenantId, int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        foreach (['name', 'platform', 'category', 'unit_label', 'cost_price', 'my_price', 'min_quantity', 'max_quantity', 'link_instructions', 'status'] as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "$col = ?";
                $params[] = $col === 'category' ? self::cleanCategory($data[$col]) : $data[$col];
            }
        }
        if ($fields === []) {
            return false;
        }
        $params[] = $tenantId;
        $params[] = $id;

        $stmt = self::db()->prepare('UPDATE bot_services SET ' . implode(', ', $fields) . ' WHERE tenant_id = ? AND id = ?');

        return $stmt->execute($params);
    }

    public static function delete(int $tenantId, int $id): bool
    {
        $stmt = self::db()->prepare('DELETE FROM bot_services WHERE tenant_id = ? AND id = ?');

        return $stmt->execute([$tenantId, $id]);
    }

    /** True if the tenant already imported this provider service from this panel. */
    public static function existsForPanelService(int $tenantId, int $panelId, string $providerServiceId): bool
    {
        $stmt = self::db()->prepare(
            'SELECT 1 FROM bot_services WHERE tenant_id = ? AND panel_id = ? AND provider_service_id = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $panelId, $providerServiceId]);

        return $stmt->fetch() !== false;
    }

    /** Normalise a category: trim, cap length, empty → NULL (uncategorised). */
    private static function cleanCategory(?string $category): ?string
    {
        $category = trim((string) $category);

        return $category === '' ? null : mb_substr($category, 0, 80);
    }
}
