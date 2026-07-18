<?php

require_once __DIR__ . '/../lib/DB.php';
require_once __DIR__ . '/../models/Tenant.php';

/**
 * ReferralReward — grants the referrer account credit when a referred tenant
 * makes their FIRST successful payment.
 *
 * Reward = a percentage of that first payment (REWARD_PERCENT), capped at
 * REWARD_CAP. The UNIQUE(referred_id) on referral_rewards plus the
 * markFirstPayment() guard make this fire at most once per referred tenant.
 */
class ReferralReward
{
    private const REWARD_PERCENT = 0.20; // 20% of the referred tenant's first payment
    private const REWARD_CAP = 20.00;    // never award more than $20 per referral

    /**
     * Call after a subscription payment is confirmed. No-op unless this is the
     * referred tenant's first payment and they were referred by someone.
     *
     * @return float the credit granted (0 if none)
     */
    public static function onFirstPayment(int $referredTenantId, float $paymentAmount, ?int $paymentId = null, string $currency = 'USD'): float
    {
        $tenant = Tenant::find($referredTenantId);
        if ($tenant === null || empty($tenant['referred_by'])) {
            return 0.0;
        }

        // Only the first payment triggers a reward (atomic guard).
        if (!Tenant::markFirstPayment($referredTenantId)) {
            return 0.0;
        }

        $referrerId = (int) $tenant['referred_by'];
        $reward = min(self::REWARD_CAP, round($paymentAmount * self::REWARD_PERCENT, 2));
        if ($reward <= 0) {
            return 0.0;
        }

        $db = DB::conn();

        try {
            $db->prepare(
                'INSERT INTO referral_rewards (referrer_id, referred_id, amount, currency, payment_id)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([$referrerId, $referredTenantId, $reward, $currency, $paymentId]);
        } catch (\PDOException $e) {
            // UNIQUE(referred_id) violated: reward already granted — bail out.
            return 0.0;
        }

        Tenant::addReferralCredit($referrerId, $reward);

        return $reward;
    }

    /** Rewards earned by a referrer (for their dashboard). */
    public static function forReferrer(int $referrerId): array
    {
        $stmt = DB::conn()->prepare(
            "SELECT rr.*, t.business_name AS referred_name
             FROM referral_rewards rr
             JOIN tenants t ON t.id = rr.referred_id
             WHERE rr.referrer_id = ?
             ORDER BY rr.created_at DESC"
        );
        $stmt->execute([$referrerId]);

        return $stmt->fetchAll();
    }
}
