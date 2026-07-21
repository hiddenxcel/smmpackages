<?php

require_once __DIR__ . '/../models/Plan.php';
require_once __DIR__ . '/../models/Subscription.php';
require_once __DIR__ . '/../models/SubscriptionPayment.php';
require_once __DIR__ . '/payments/NowPaymentsClient.php';
require_once __DIR__ . '/payments/BinancePayClient.php';
require_once __DIR__ . '/payments/BinanceVerifyClient.php';
require_once __DIR__ . '/payments/SnippeClient.php';
require_once __DIR__ . '/payments/CryptomusClient.php';
require_once __DIR__ . '/payments/HeleketClient.php';
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

        // Pending subscription + pending payment (linked). If this service is
        // already in SANDBOX (free sign-up), reuse that row — "Go Live" promotes
        // it — instead of leaving an orphaned sandbox row behind.
        $subscriptionId = Subscription::reservePending($tenantId, $serviceKey, (int) $plan['id']);
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

        // Binance uses the "internal transfer" (no-KYB) flow: no gateway call
        // here. We keep the payment pending and render a panel showing our
        // Binance ID; the tenant sends USDT (1:1 with the USD amount) and then
        // verifies via verifyBinance() below.
        if ($gateway === 'binance') {
            return [
                'success' => true,
                'binance_manual' => true,
                'pay_id' => (string) ($this->config['billing']['binance']['pay_id'] ?? ''),
                'amount' => $amount,
                'currency' => 'USDT',
                'transaction_ref' => $transactionRef,
            ];
        }

        $client = $this->gatewayClient($gateway);
        $baseUrl = rtrim($this->config['app']['url'] ?? '', '/');

        // Per-gateway currency/amount: NOWPayments takes USD; Binance Pay settles
        // in USDT; Snippe is Tanzania mobile money and must be charged in TZS
        // (converted from the USD price). The DB payment stays in USD for accounting.
        $gwAmount = $amount;
        $gwCurrency = $currency;
        if ($gateway === 'binance') {
            $gwCurrency = 'USDT';
        } elseif ($gateway === 'snippe') {
            $rate = (float) ($this->config['billing']['snippe']['usd_to_tzs'] ?? 2600);
            $gwCurrency = 'TZS';
            $gwAmount = (int) ceil($amount * $rate);
        }

        // Snippe requires a non-empty lastname. We only store a single business
        // name, so split it (or reuse it) to always send something valid.
        $name = trim((string) ($tenant['business_name'] ?? 'Tenant'));
        $nameParts = preg_split('/\s+/', $name, 2) ?: ['Tenant'];
        $firstName = $nameParts[0] !== '' ? $nameParts[0] : 'Tenant';
        $lastName = $nameParts[1] ?? $firstName;

        $data = [
            'amount' => $gwAmount,
            'currency' => $gwCurrency,
            'order_id' => $transactionRef,
            'description' => $plan['name'] . ' — ' . $months . ' month(s)',
            'webhook_url' => $baseUrl . '/webhooks/' . $gateway . '.php',
            'success_url' => $baseUrl . '/subscription.php?paid=1',
            'cancel_url' => $baseUrl . '/subscription.php?cancelled=1',
            'return_url' => $baseUrl . '/subscription.php?paid=1',
            'firstname' => $firstName,
            'lastname' => $lastName,
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
     * Verify a Binance "internal transfer" and activate on success.
     * The tenant reports the Binance Order ID for the USDT they sent to our
     * Binance ID; we confirm it against our own Binance Pay history.
     *
     * @return array ['success'=>bool, 'message'=>string, 'error_type'=>?string]
     */
    public function verifyBinance(array $tenant, string $transactionRef, string $binanceOrderId): array
    {
        $binanceOrderId = trim($binanceOrderId);
        if ($binanceOrderId === '') {
            return ['success' => false, 'error_type' => 'not_found', 'message' => 'Please enter the Binance Order ID.'];
        }

        $payment = SubscriptionPayment::findByRef($transactionRef);
        if ($payment === null || (int) $payment['tenant_id'] !== (int) $tenant['id']) {
            return ['success' => false, 'error_type' => 'not_found', 'message' => 'Payment not found.'];
        }
        if (($payment['status'] ?? '') === 'success') {
            return ['success' => true, 'message' => 'Already verified.'];
        }

        // Replay guard: this Binance Order ID must not already back a payment.
        if (SubscriptionPayment::binanceOrderUsed($binanceOrderId)) {
            return ['success' => false, 'error_type' => 'used', 'message' => 'This Binance Order ID has already been used.'];
        }

        $client = new BinanceVerifyClient($this->config['billing']['binance'] ?? []);
        $result = $client->verifyOrder($binanceOrderId, (float) $payment['amount']);

        if (empty($result['success'])) {
            // Do not fail the payment — let the tenant retry with a corrected ID.
            return ['success' => false, 'error_type' => $result['error_type'] ?? 'not_found', 'message' => $result['message'] ?? 'Verification failed.'];
        }

        // Record the Order ID first (unique column doubles as the replay guard),
        // then activate via the shared, idempotent fulfil().
        SubscriptionPayment::setBinanceOrder((int) $payment['id'], $binanceOrderId);
        $this->fulfil($transactionRef, json_encode(['binance_order_id' => $binanceOrderId, 'verified' => $result]));

        return ['success' => true, 'message' => 'Payment verified.'];
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
            'cryptomus'   => new CryptomusClient($this->config['billing']['cryptomus'] ?? []),
            'heleket'     => new HeleketClient($this->config['billing']['heleket'] ?? []),
            default       => throw new InvalidArgumentException("Unknown gateway: {$gateway}"),
        };
    }

    private function makeRef(string $gateway): string
    {
        return 'SMM-' . strtoupper($gateway[0]) . '-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));
    }
}
