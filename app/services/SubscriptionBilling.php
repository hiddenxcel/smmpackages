<?php

require_once __DIR__ . '/../models/Plan.php';
require_once __DIR__ . '/../models/Subscription.php';
require_once __DIR__ . '/../models/SubscriptionPayment.php';
require_once __DIR__ . '/payments/NowPaymentsClient.php';
require_once __DIR__ . '/payments/BinancePayClient.php';
require_once __DIR__ . '/payments/SnippeClient.php';
require_once __DIR__ . '/ReferralReward.php';

/**
 * SubscriptionBilling — SaaS checkout + activation orchestration (a la carte).
 *
 * checkout(): validates service+months, computes price, creates a pending
 *   subscription and a pending payment, calls the chosen gateway, returns a
 *   redirect URL (crypto) or an on-phone reference (Snippe).
 * fulfil(): called from webhooks — flips the payment to success ONCE
 *   (replay-guarded) and activates/extends the subscription.
 */
class SubscriptionBilling
{
    /** Allowed billing periods in months. Yearly (12) uses the plan's discounted price. */
    public const PERIODS = [1, 3, 6, 12];

    /** Minimum amount to actually charge a gateway (credit can't discount below this). */
    public const MIN_CHARGE = 1.00;

    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /** Price for a service over N months. 12 => plan yearly (already -20%). */
    public static function priceFor(array $plan, int $months): float
    {
        if ($months === 12) {
            return (float) $plan['price_yearly'];
        }

        return round((float) $plan['price_monthly'] * $months, 2);
    }

    /**
     * @param array  $tenant   authed tenant row
     * @param string $serviceKey  one of Subscription::SERVICES
     * @param int    $months
     * @param string $gateway  nowpayments|binance|snippe
     * @param array  $extra    ['phone'=>..] for Snippe; url builders
     * @return array ['success'=>bool, 'redirect_url'=>?string, 'reference'=>?string, 'transaction_ref'=>?string, 'message'=>?string]
     */
    public function checkout(array $tenant, string $serviceKey, int $months, string $gateway, array $extra = []): array
    {
        if (!in_array($serviceKey, Subscription::SERVICES, true)) {
            return ['success' => false, 'message' => 'Unknown service.'];
        }
        if (!in_array($months, self::PERIODS, true)) {
            return ['success' => false, 'message' => 'Invalid billing period.'];
        }
        if (!in_array($gateway, SubscriptionPayment::GATEWAYS, true)) {
            return ['success' => false, 'message' => 'Unknown gateway.'];
        }

        $plan = Plan::forService($serviceKey);
        if ($plan === null) {
            return ['success' => false, 'message' => 'No active plan for this service.'];
        }

        $tenantId = (int) $tenant['id'];
        $gross = self::priceFor($plan, $months);
        $currency = $plan['currency'] ?? 'USD';
        $transactionRef = $this->makeRef($gateway);

        // Apply referral credit as a discount, but never below a payable minimum
        // (so we always have a real transaction to confirm). The applied credit
        // is reserved on the payment and refunded if the payment fails/expires.
        $creditApplied = Tenant::spendReferralCredit($tenantId, max(0.0, $gross - self::MIN_CHARGE));
        $amount = round($gross - $creditApplied, 2);

        // Pending subscription + pending payment (linked).
        $subscriptionId = Subscription::create($tenantId, $serviceKey, (int) $plan['id'], 'pending');
        $paymentId = SubscriptionPayment::create(
            $tenantId,
            $gateway,
            $transactionRef,
            $amount,
            $months,
            (int) $plan['id'],
            $subscriptionId,
            $currency
        );
        if ($creditApplied > 0) {
            DB::conn()->prepare('UPDATE subscription_payments SET credit_applied = ? WHERE id = ?')
                ->execute([$creditApplied, $paymentId]);
        }

        $client = $this->gatewayClient($gateway);
        $baseUrl = rtrim($this->config['app']['url'] ?? '', '/');

        $data = [
            'amount' => $amount,
            'currency' => $currency,
            'order_id' => $transactionRef,
            'description' => $plan['name'] . ' — ' . $months . ' month(s)',
            'webhook_url' => $baseUrl . '/webhooks/' . $gateway . '.php',
            'success_url' => $baseUrl . '/subscription.php?paid=1',
            'cancel_url' => $baseUrl . '/subscription.php?cancelled=1',
            'return_url' => $baseUrl . '/subscription.php?paid=1',
            'firstname' => $tenant['business_name'] ?? 'Tenant',
            'lastname' => '',
            'email' => $tenant['email'] ?? '',
            'phone' => $extra['phone'] ?? ($tenant['phone'] ?? ''),
        ];

        $result = $client->initiate($data);
        $result['transaction_ref'] = $transactionRef;

        if (empty($result['success'])) {
            // Roll the pending records back to failed and refund any reserved credit.
            SubscriptionPayment::markFailed($transactionRef, $result['message'] ?? null);
            if ($creditApplied > 0) {
                Tenant::addReferralCredit($tenantId, $creditApplied);
            }
        }

        return $result;
    }

    /**
     * Activate a subscription from a confirmed payment. Idempotent:
     * markSuccess only transitions once, so activation runs exactly once.
     *
     * @return bool true if this call performed the activation
     */
    public function fulfil(string $transactionRef, ?string $rawResponse = null): bool
    {
        $payment = SubscriptionPayment::findByRef($transactionRef);
        if ($payment === null) {
            return false;
        }

        if (!SubscriptionPayment::markSuccess($transactionRef, $rawResponse)) {
            return false; // already fulfilled (replay) or not found
        }

        if (!empty($payment['subscription_id'])) {
            Subscription::activate((int) $payment['subscription_id'], (int) $payment['months']);
        }

        // Referral reward: if this is the referred tenant's first payment, credit
        // their referrer. Guarded to fire at most once per referred tenant.
        ReferralReward::onFirstPayment(
            (int) $payment['tenant_id'],
            (float) $payment['amount'],
            (int) $payment['id'],
            $payment['currency'] ?? 'USD'
        );

        return true;
    }

    public function gatewayClient(string $gateway): object
    {
        return match ($gateway) {
            'nowpayments' => new NowPaymentsClient($this->config['billing']['nowpayments'] ?? []),
            'binance'     => new BinancePayClient($this->config['billing']['binance'] ?? []),
            'snippe'      => new SnippeClient($this->config['billing']['snippe'] ?? []),
            default       => throw new InvalidArgumentException("Unknown gateway: {$gateway}"),
        };
    }

    private function makeRef(string $gateway): string
    {
        return 'SMM-' . strtoupper($gateway[0]) . '-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));
    }
}
