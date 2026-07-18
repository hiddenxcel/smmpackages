<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * ResponseTemplate — bot message templates. tenant_id NULL = platform default.
 * A tenant's own row (same key+lang) overrides the default. Placeholders like
 * {order_id} are filled by the caller via render().
 */
class ResponseTemplate extends BaseModel
{
    /**
     * Resolve a template's content for a tenant+key+lang.
     * Order: tenant override (key+lang) -> tenant override (key+en) ->
     *        platform default (key+lang) -> platform default (key+en) -> null.
     */
    public static function resolve(int $tenantId, string $key, string $lang = 'en'): ?string
    {
        $sql = "SELECT content FROM response_templates
                WHERE template_key = ?
                  AND (tenant_id = ? OR tenant_id IS NULL)
                  AND lang IN (?, 'en')
                ORDER BY (tenant_id IS NULL) ASC, (lang = ?) DESC
                LIMIT 1";
        $stmt = self::db()->prepare($sql);
        $stmt->execute([$key, $tenantId, $lang, $lang]);
        $row = $stmt->fetch();

        return $row['content'] ?? null;
    }

    /** Resolve + fill {placeholders}. Returns null if no template exists. */
    public static function render(int $tenantId, string $key, array $vars = [], string $lang = 'en'): ?string
    {
        $content = self::resolve($tenantId, $key, $lang);
        if ($content === null) {
            return null;
        }
        foreach ($vars as $k => $v) {
            $content = str_replace('{' . $k . '}', (string) $v, $content);
        }

        return $content;
    }

    /** Platform defaults + this tenant's overrides, grouped by key, for the editor. */
    public static function forEditor(int $tenantId, string $lang = 'en'): array
    {
        $stmt = self::db()->prepare(
            "SELECT template_key, content, tenant_id
             FROM response_templates
             WHERE lang = ? AND (tenant_id = ? OR tenant_id IS NULL)"
        );
        $stmt->execute([$lang, $tenantId]);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = $row['template_key'];
            if (!isset($out[$key])) {
                $out[$key] = ['default' => null, 'override' => null];
            }
            if ($row['tenant_id'] === null) {
                $out[$key]['default'] = $row['content'];
            } else {
                $out[$key]['override'] = $row['content'];
            }
        }
        ksort($out);

        return $out;
    }

    /** Upsert a tenant override; empty content removes the override (revert to default). */
    public static function saveOverride(int $tenantId, string $key, string $lang, string $content): void
    {
        $content = trim($content);
        if ($content === '') {
            self::db()->prepare(
                'DELETE FROM response_templates WHERE tenant_id = ? AND template_key = ? AND lang = ?'
            )->execute([$tenantId, $key, $lang]);

            return;
        }

        self::db()->prepare(
            "INSERT INTO response_templates (tenant_id, template_key, lang, content, is_default)
             VALUES (?, ?, ?, ?, 0)
             ON DUPLICATE KEY UPDATE content = VALUES(content)"
        )->execute([$tenantId, $key, $lang, $content]);
    }
}
