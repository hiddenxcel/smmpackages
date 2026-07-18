<?php

require_once __DIR__ . '/../models/GuaranteeRule.php';

/**
 * GuaranteeMatcher — decides, from a service name, whether a refill is allowed
 * and for how many days, using a tenant's guarantee rules.
 *
 * Precedence: a NO-GUARANTEE keyword match always wins (refill blocked), even
 * if a guarantee keyword also matches. Otherwise the guarantee keyword with the
 * longest match wins (so "365 days" beats a bare "5 days" substring, etc.).
 * refill_days = 0 means lifetime (∞).
 */
class GuaranteeMatcher
{
    private array $rules;

    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    public static function forTenant(int $tenantId): self
    {
        return new self(GuaranteeRule::forTenant($tenantId));
    }

    /**
     * @return array{allowed:bool, days:?int, lifetime:bool, matched:?string}
     *   allowed=false when a no-guarantee rule matches or nothing matches.
     */
    public function evaluate(string $serviceName): array
    {
        $haystack = mb_strtolower($serviceName);

        // 1. No-guarantee wins outright.
        foreach ($this->rules as $r) {
            if ($r['rule_type'] === 'no_guarantee' && $this->matches($haystack, $r['keyword'])) {
                return ['allowed' => false, 'days' => null, 'lifetime' => false, 'matched' => $r['keyword']];
            }
        }

        // 2. Best guarantee match (longest keyword).
        $best = null;
        foreach ($this->rules as $r) {
            if ($r['rule_type'] !== 'guarantee' || !$this->matches($haystack, $r['keyword'])) {
                continue;
            }
            if ($best === null || mb_strlen($r['keyword']) > mb_strlen($best['keyword'])) {
                $best = $r;
            }
        }

        if ($best !== null) {
            $days = (int) $best['refill_days'];

            return ['allowed' => true, 'days' => $days ?: null, 'lifetime' => $days === 0, 'matched' => $best['keyword']];
        }

        // 3. No rule matched — default to no guarantee (safe).
        return ['allowed' => false, 'days' => null, 'lifetime' => false, 'matched' => null];
    }

    private function matches(string $haystack, string $keyword): bool
    {
        $keyword = mb_strtolower(trim($keyword));

        return $keyword !== '' && str_contains($haystack, $keyword);
    }
}
