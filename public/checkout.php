<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

$billing = new SubscriptionBilling($config);

$notice = null;
$noticeType = 'success';
$binancePanel = null; // set when Binance manual-verify checkout is initiated

// ---- The service + term being checked out (from the subscription page) ----
$serviceKey = $_REQUEST['svc'] ?? ($_REQUEST['service_key'] ?? '');
$months = (int) ($_REQUEST['months'] ?? 1);
if (!in_array($months, [1, 3, 6, 12], true)) {
    $months = 1;
}

$plan = $serviceKey !== '' ? Plan::forService($serviceKey) : null;
if ($plan === null) {
    // Nothing valid to check out — send them back to pick a service.
    header('Location: subscription.php');
    exit;
}

// ---- Pricing for the chosen term (needed by the POST gateway guard too) ----
$amounts = [
    1  => (float) $plan['price_monthly'],
    3  => (float) $plan['price_monthly'] * 3,
    6  => (float) $plan['price_monthly'] * 6,
    12 => (float) $plan['price_yearly'],
];
$total = $amounts[$months] ?? $amounts[1];

// NOWPayments enforces a ~$20 minimum invoice; below that it rejects checkout.
// Only offer it when the total clears the floor, and default to another gateway
// (Cryptomus — crypto, no big minimum) when it doesn't.
$nowpayMin = 20.0;
$showNowpay = $total >= $nowpayMin;
$defaultGateway = $showNowpay ? 'nowpayments' : 'cryptomus';

// ---- POST: run the actual checkout with the chosen gateway ----
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();

    // Binance "internal transfer" verify step: tenant pasted their Order ID.
    if (($_POST['action'] ?? '') === 'binance_verify') {
        $ref = trim($_POST['transaction_ref'] ?? '');
        $binanceOrderId = trim($_POST['binance_order_id'] ?? '');
        $vr = $billing->verifyBinance($tenant, $ref, $binanceOrderId);
        if (!empty($vr['success'])) {
            header('Location: subscription.php?paid=1');
            exit;
        }
        $notice = Lang::t('bnc_err_' . ($vr['error_type'] ?? 'not_found'));
        $noticeType = 'danger';
        $binancePanel = [
            'pay_id' => (string) ($config['billing']['binance']['pay_id'] ?? ''),
            'amount' => number_format((float) ($_POST['amount'] ?? 0), 2),
            'transaction_ref' => $ref,
        ];
    } else {
        $gateway = $_POST['gateway'] ?? '';
        $extra = ['phone' => trim($_POST['phone'] ?? '')];

        // Server-side guard: never let a below-minimum NOWPayments checkout
        // through, even if the UI tile was hidden but the POST was forged.
        if ($gateway === 'nowpayments' && !$showNowpay) {
            $gateway = $defaultGateway;
        }

        $result = $billing->checkout($tenant, $serviceKey, $months, $gateway, $extra);

        if (!empty($result['success'])) {
            if (!empty($result['binance_manual'])) {
                $binancePanel = [
                    'pay_id' => (string) ($result['pay_id'] ?? ''),
                    'amount' => number_format((float) ($result['amount'] ?? 0), 2),
                    'transaction_ref' => (string) ($result['transaction_ref'] ?? ''),
                ];
            } elseif (!empty($result['redirect_url'])) {
                header('Location: ' . $result['redirect_url']);
                exit;
            } else {
                // Snippe: on-phone USSD — bounce back to subscription with a notice.
                header('Location: subscription.php?ussd=1');
                exit;
            }
        } else {
            $notice = Lang::t('sub_failed', ['msg' => $result['message'] ?? '']);
            $noticeType = 'danger';
        }
    }
}

$svcIcon = [
    'order_bot'     => 'fa-solid fa-cart-shopping',
    'support_bot'   => 'fa-solid fa-headset',
    'ai_tickets'    => 'fa-solid fa-robot',
    'ai_chat'       => 'fa-solid fa-comments',
    'number_rental' => 'fa-solid fa-phone',
];
$termLabel = [1 => 'sub_1m', 3 => 'sub_3m', 6 => 'sub_6m', 12 => 'sub_12m'][$months] ?? 'sub_1m';

$pageTitle = Lang::t('checkout_title');
$activeSide = 'subscription';
require __DIR__ . '/includes/dash_header.php';
?>

<div style="margin-bottom:22px">
  <a href="subscription.php" class="btn btn-outline" style="padding:8px 16px">
    <i class="fa-solid fa-arrow-left"></i> <?php e('sub_view_all'); ?>
  </a>
</div>

<?php if ($notice !== null): ?>
<div class="alert alert-<?= $noticeType ?>">
  <i class="fa-solid <?= $noticeType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="margin-top:3px"></i>
  <?= htmlspecialchars($notice) ?>
</div>
<?php endif; ?>

<?php if ($binancePanel !== null): ?>
<!-- Binance "internal transfer" panel: send USDT to our Binance ID, then verify. -->
<div class="card bnc-panel" style="max-width:520px;margin:0 auto 24px">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
    <div class="card-icon" style="background:#F0B90B;color:#181A20"><i class="fa-solid fa-b"></i></div>
    <div>
      <h3 style="margin:0"><?php e('bnc_title'); ?></h3>
      <div style="font-size:.9rem;color:var(--text-muted)"><?php e('bnc_send_note'); ?></div>
    </div>
  </div>
  <div class="bnc-amt">
    <span class="pt-label"><?php e('bnc_amount'); ?></span>
    <span class="pt-amt"><?= htmlspecialchars($binancePanel['amount']) ?> USDT</span>
  </div>
  <div class="pay-field" style="margin-top:14px">
    <span class="pay-flabel"><?php e('bnc_pay_id'); ?></span>
    <div class="bnc-idrow">
      <input type="text" class="form-control" id="bncPayId" value="<?= htmlspecialchars($binancePanel['pay_id']) ?>" readonly>
      <button type="button" class="btn btn-secondary" onclick="bncCopy()"><i class="fa-solid fa-copy"></i></button>
    </div>
    <?php if ($binancePanel['pay_id'] === ''): ?>
      <p style="margin-top:7px;font-size:.82rem;color:var(--danger)"><i class="fa-solid fa-triangle-exclamation"></i> <?php e('bnc_no_id'); ?></p>
    <?php endif; ?>
  </div>
  <form method="post" action="checkout.php?svc=<?= htmlspecialchars($serviceKey) ?>&months=<?= $months ?>" style="margin-top:16px">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="binance_verify">
    <input type="hidden" name="transaction_ref" value="<?= htmlspecialchars($binancePanel['transaction_ref']) ?>">
    <input type="hidden" name="amount" value="<?= htmlspecialchars($binancePanel['amount']) ?>">
    <div class="pay-field">
      <span class="pay-flabel"><?php e('bnc_order_id'); ?></span>
      <input type="text" class="form-control" name="binance_order_id" placeholder="e.g. 381126275..." required>
    </div>
    <button class="btn btn-primary btn-block btn-lg" type="submit" style="margin-top:14px">
      <i class="fa-solid fa-circle-check"></i> <?php e('bnc_verify'); ?>
    </button>
  </form>
  <ol class="bnc-steps">
    <li><?php e('bnc_step1'); ?></li>
    <li><?php e('bnc_step2'); ?></li>
    <li><?php e('bnc_step3'); ?></li>
  </ol>
</div>
<script>
function bncCopy(){
  var f = document.getElementById('bncPayId'); if(!f) return;
  f.select(); f.setSelectionRange(0, 99999);
  try { navigator.clipboard.writeText(f.value); } catch(e) { document.execCommand('copy'); }
}
</script>
<?php else: ?>

<!-- Checkout: order summary + payment method picker -->
<div style="max-width:560px;margin:0 auto">
  <div class="card" style="margin-bottom:18px">
    <div style="display:flex;align-items:center;gap:14px">
      <div class="card-icon"><i class="<?= $svcIcon[$serviceKey] ?? 'fa-solid fa-cube' ?>"></i></div>
      <div style="flex:1">
        <h3 style="margin:0"><?= htmlspecialchars($plan['name']) ?></h3>
        <div style="font-size:.9rem;color:var(--text-muted)"><?php e($termLabel); ?></div>
      </div>
      <div style="font-size:1.4rem;font-weight:700"><?= money($total) ?></div>
    </div>
  </div>

  <form method="post" action="checkout.php" class="checkout-form2">
    <?= Csrf::field() ?>
    <input type="hidden" name="svc" value="<?= htmlspecialchars($serviceKey) ?>">
    <input type="hidden" name="months" value="<?= $months ?>">
    <input type="hidden" name="gateway" value="<?= htmlspecialchars($defaultGateway) ?>" class="gateway-input">

    <div class="card">
      <div class="pay-field">
        <span class="pay-flabel"><?php e('sub_choose_gateway'); ?></span>
        <div class="pay-methods2">
          <?php if ($showNowpay): ?>
          <button type="button" class="pay-method2 active" data-gw="nowpayments">
            <span class="pm-ico usdt"><i class="fa-brands fa-bitcoin"></i></span>
            <span class="pm-body"><span class="pm-n">USDT</span><span class="pm-s">NOWPayments</span></span>
            <span class="pm-check"><i class="fa-solid fa-check"></i></span>
          </button>
          <?php endif; ?>
          <button type="button" class="pay-method2<?= $showNowpay ? '' : ' active' ?>" data-gw="cryptomus">
            <span class="pm-ico usdt"><i class="fa-solid fa-coins"></i></span>
            <span class="pm-body"><span class="pm-n">Cryptomus</span><span class="pm-s">USDT · BTC</span></span>
            <span class="pm-check"><i class="fa-solid fa-check"></i></span>
          </button>
          <button type="button" class="pay-method2" data-gw="heleket">
            <span class="pm-ico usdt"><i class="fa-solid fa-coins"></i></span>
            <span class="pm-body"><span class="pm-n">Heleket</span><span class="pm-s">USDT · BTC</span></span>
            <span class="pm-check"><i class="fa-solid fa-check"></i></span>
          </button>
          <button type="button" class="pay-method2" data-gw="snippe">
            <span class="pm-ico mobile"><i class="fa-solid fa-mobile-screen-button"></i></span>
            <span class="pm-body"><span class="pm-n"><?php e('sub_gw_mobile'); ?></span><span class="pm-s">M-Pesa · Tigo · Airtel · 🇹🇿 <?php e('sub_gw_tz_only'); ?></span></span>
            <span class="pm-check"><i class="fa-solid fa-check"></i></span>
          </button>
        </div>
        <?php if (!$showNowpay): ?>
        <div class="form-hint" style="margin-top:8px"><i class="fa-solid fa-circle-info"></i> <?= htmlspecialchars(Lang::t('sub_nowpay_min', ['min' => '$' . (int) $nowpayMin])) ?></div>
        <?php endif; ?>
      </div>

      <div class="pay-field phone-group" style="display:none">
        <span class="pay-flabel"><?php e('sub_phone_label'); ?></span>
        <input class="form-control" type="tel" name="phone" value="<?= htmlspecialchars($tenant['phone'] ?? '') ?>" placeholder="+255...">
      </div>

      <div class="pay-total">
        <span class="pt-label"><?php e('sub_total'); ?></span>
        <span class="pt-amt"><?= money($total) ?></span>
      </div>

      <button class="btn btn-primary btn-block btn-lg" type="submit">
        <i class="fa-solid fa-lock"></i> <?= htmlspecialchars(Lang::t('sub_pay', ['amount' => money($total)])) ?>
      </button>
    </div>
  </form>
</div>

<script>
(function(){
  var form = document.querySelector('.checkout-form2');
  if (!form) return;
  var gatewayInput = form.querySelector('.gateway-input');
  var phoneGroup   = form.querySelector('.phone-group');
  form.querySelectorAll('.pay-method2').forEach(function(btn){
    btn.addEventListener('click', function(){
      form.querySelectorAll('.pay-method2').forEach(function(b){ b.classList.remove('active'); });
      btn.classList.add('active');
      gatewayInput.value = btn.dataset.gw;
      phoneGroup.style.display = (btn.dataset.gw === 'snippe') ? 'block' : 'none';
    });
  });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
