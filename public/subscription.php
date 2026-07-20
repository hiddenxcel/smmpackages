<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

$billing = new SubscriptionBilling($config);

$notice = null;
$noticeType = 'success';
$redirectUrl = null;
$binancePanel = null; // set when Binance manual-verify checkout is initiated

if (isset($_GET['paid']))      { $notice = Lang::t('sub_paid'); }
if (isset($_GET['cancelled'])) { $notice = Lang::t('sub_cancelled'); $noticeType = 'danger'; }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();

    // Binance "internal transfer" verify step: tenant pasted their Order ID.
    if (($_POST['action'] ?? '') === 'binance_verify') {
        $ref = trim($_POST['transaction_ref'] ?? '');
        $binanceOrderId = trim($_POST['binance_order_id'] ?? '');
        $vr = $billing->verifyBinance($tenant, $ref, $binanceOrderId);
        if (!empty($vr['success'])) {
            $notice = Lang::t('sub_paid');
            $noticeType = 'success';
        } else {
            $notice = Lang::t('bnc_err_' . ($vr['error_type'] ?? 'not_found'));
            $noticeType = 'danger';
            // Re-show the panel so the tenant can retry with a corrected ID.
            $binancePanel = [
                'pay_id' => (string) ($config['billing']['binance']['pay_id'] ?? ''),
                'amount' => number_format((float) ($_POST['amount'] ?? 0), 2),
                'transaction_ref' => $ref,
            ];
        }
    } else {
        $serviceKey = $_POST['service_key'] ?? '';
        $months = (int) ($_POST['months'] ?? 1);
        $gateway = $_POST['gateway'] ?? '';
        $extra = ['phone' => trim($_POST['phone'] ?? '')];

        $result = $billing->checkout($tenant, $serviceKey, $months, $gateway, $extra);

        if (!empty($result['success'])) {
            if (!empty($result['binance_manual'])) {
                // Show the "send USDT to our Binance ID + paste Order ID" panel.
                $binancePanel = [
                    'pay_id' => (string) ($result['pay_id'] ?? ''),
                    'amount' => number_format((float) ($result['amount'] ?? 0), 2),
                    'transaction_ref' => (string) ($result['transaction_ref'] ?? ''),
                ];
            } elseif (!empty($result['redirect_url'])) {
                // Crypto gateways: bounce the tenant to the hosted invoice.
                header('Location: ' . $result['redirect_url']);
                exit;
            } else {
                // Snippe: on-phone USSD, stay here with a "check your phone" notice.
                $notice = Lang::t('sub_ussd_sent');
                $noticeType = 'success';
            }
        } else {
            $notice = Lang::t('sub_failed', ['msg' => $result['message'] ?? '']);
            $noticeType = 'danger';
        }
    }
}

$plans = Plan::allActive();
$gate = Subscription::statusMap($tenantId);
$stateMap = Subscription::stateMap($tenantId);          // active | sandbox | locked
$goLive = isset($_GET['golive']);                        // arrived via a "Go Live" button
$goLiveSvc = $_GET['svc'] ?? '';                          // a specific service to highlight
$sandboxCount = count(array_filter($stateMap, fn ($s) => $s === 'sandbox'));

$svcIcon = [
    'order_bot'     => 'fa-solid fa-cart-shopping',
    'support_bot'   => 'fa-solid fa-headset',
    'ai_tickets'    => 'fa-solid fa-robot',
    'number_rental' => 'fa-solid fa-phone',
];

$pageTitle = Lang::t('sub_title');
$activeSide = 'subscription';
require __DIR__ . '/includes/dash_header.php';
?>

<?php if ($goLive && $sandboxCount > 0): ?>
<!-- Go Live banner: the reseller clicked "Go Live" in the sandbox dashboard. -->
<div class="sandbox-banner" style="margin-bottom:22px">
  <div class="sb-ico"><i class="fa-solid fa-rocket"></i></div>
  <div class="sb-text">
    <strong><?php e('golive_title'); ?></strong>
    <span><?php e('golive_sub'); ?></span>
  </div>
</div>
<?php else: ?>
<p style="color:var(--text-muted);margin-bottom:24px"><?php e('sub_intro'); ?></p>
<?php endif; ?>

<?php $availCredit = (float) ($tenant['referral_credit'] ?? 0); if ($availCredit > 0): ?>
<div class="alert alert-success">
  <i class="fa-solid fa-gift" style="margin-top:3px"></i>
  <?= htmlspecialchars(Lang::t('ref_credit_note', ['amount' => '$' . number_format($availCredit, 2)])) ?>
</div>
<?php endif; ?>

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

  <form method="post" action="subscription.php" style="margin-top:16px">
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
<?php endif; ?>

<div class="grid grid-2">
  <?php foreach ($plans as $plan): $key = $plan['service_key']; $active = $gate[$key] ?? false;
        $svcState = $stateMap[$key] ?? 'locked';
        $isSandbox = $svcState === 'sandbox';
        $highlight = $goLive && $isSandbox && ($goLiveSvc === '' || $goLiveSvc === $key);
        $activeSub = $active ? Subscription::activeForService($tenantId, $key) : null; ?>
  <div class="card<?= $highlight ? ' card-golive' : '' ?>"<?= $highlight ? ' id="golive-'.htmlspecialchars($key).'"' : '' ?>>
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px">
      <div class="card-icon"><i class="<?= $svcIcon[$key] ?? 'fa-solid fa-cube' ?>"></i></div>
      <div>
        <h3 style="margin:0"><?= htmlspecialchars($plan['name']) ?></h3>
        <div style="font-size:.9rem;color:var(--text-muted)"><?= money((float) $plan['price_monthly']) ?><?php e('per_month'); ?></div>
      </div>
      <?php if ($active): ?>
        <span class="status-chip active" style="position:static;margin-left:auto">✅ <?php e('status_active'); ?></span>
      <?php elseif ($isSandbox): ?>
        <span class="status-chip" style="position:static;margin-left:auto;background:rgba(245,158,11,.15);color:var(--accent-dark)">🧪 <?php e('svc_sandbox'); ?></span>
      <?php endif; ?>
    </div>

    <?php if ($active && $activeSub && !empty($activeSub['ends_at'])): ?>
      <p style="font-size:.9rem;color:var(--text-muted);margin-bottom:14px">
        <i class="fa-regular fa-clock"></i>
        <?= htmlspecialchars(Lang::t('sub_active_until', ['date' => date('M j, Y', strtotime($activeSub['ends_at']))])) ?>
      </p>
    <?php endif; ?>

    <?php $amt = [
        1  => money((float) $plan['price_monthly']),
        3  => money((float) $plan['price_monthly'] * 3),
        6  => money((float) $plan['price_monthly'] * 6),
        12 => money((float) $plan['price_yearly']),
    ]; ?>
    <form method="post" action="subscription.php" class="checkout-form">
      <?= Csrf::field() ?>
      <input type="hidden" name="service_key" value="<?= htmlspecialchars($key) ?>">
      <input type="hidden" name="months" value="1" class="months-input">
      <input type="hidden" name="gateway" value="nowpayments" class="gateway-input">

      <div class="pay-field">
        <span class="pay-flabel"><?php e('sub_choose_period'); ?></span>
        <div class="pay-periods">
          <?php foreach ([1 => 'sub_1m', 3 => 'sub_3m', 6 => 'sub_6m', 12 => 'sub_12m'] as $mo => $lk): ?>
          <button type="button" class="pay-period<?= $mo === 1 ? ' active' : '' ?>" data-m="<?= $mo ?>" data-amt="<?= htmlspecialchars($amt[$mo], ENT_QUOTES) ?>">
            <?php if ($mo === 12): ?><span class="pp-badge"><?php e('sub_save20'); ?></span><?php endif; ?>
            <span class="pp-t"><?php e($lk); ?></span>
            <span class="pp-a"><?= htmlspecialchars($amt[$mo]) ?></span>
          </button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="pay-field">
        <span class="pay-flabel"><?php e('sub_choose_gateway'); ?></span>
        <div class="pay-methods2">
          <button type="button" class="pay-method2 active" data-gw="nowpayments">
            <span class="pm-ico usdt"><i class="fa-brands fa-bitcoin"></i></span>
            <span class="pm-body"><span class="pm-n">USDT</span><span class="pm-s">NOWPayments</span></span>
            <span class="pm-check"><i class="fa-solid fa-check"></i></span>
          </button>
          <button type="button" class="pay-method2" data-gw="binance">
            <span class="pm-ico binance"><i class="fa-solid fa-b"></i></span>
            <span class="pm-body"><span class="pm-n">Binance</span><span class="pm-s">USDT</span></span>
            <span class="pm-check"><i class="fa-solid fa-check"></i></span>
          </button>
          <button type="button" class="pay-method2" data-gw="cryptomus">
            <span class="pm-ico usdt"><i class="fa-solid fa-coins"></i></span>
            <span class="pm-body"><span class="pm-n">Cryptomus</span><span class="pm-s">USDT · BTC</span></span>
            <span class="pm-check"><i class="fa-solid fa-check"></i></span>
          </button>
          <button type="button" class="pay-method2" data-gw="snippe">
            <span class="pm-ico mobile"><i class="fa-solid fa-mobile-screen-button"></i></span>
            <span class="pm-body"><span class="pm-n"><?php e('sub_gw_mobile'); ?></span><span class="pm-s">M-Pesa · Tigo · Airtel</span></span>
            <span class="pm-check"><i class="fa-solid fa-check"></i></span>
          </button>
        </div>
      </div>

      <div class="pay-field phone-group" style="display:none">
        <span class="pay-flabel"><?php e('sub_phone_label'); ?></span>
        <input class="form-control" type="tel" name="phone" value="<?= htmlspecialchars($tenant['phone'] ?? '') ?>" placeholder="+255...">
      </div>

      <div class="pay-total">
        <span class="pt-label"><?php e('sub_total'); ?></span>
        <span class="pt-amt"><?= htmlspecialchars($amt[1]) ?></span>
      </div>

      <button class="btn btn-primary btn-block btn-lg" type="submit">
        <i class="fa-solid <?= $isSandbox ? 'fa-rocket' : 'fa-lock' ?>"></i>
        <span class="pay-cta" data-tpl="<?= htmlspecialchars(Lang::t('sub_pay', ['amount' => '__AMT__']), ENT_QUOTES) ?>" data-golive="<?= $isSandbox ? '1' : '0' ?>" data-golivetxt="<?= htmlspecialchars(Lang::t('sandbox_go_live'), ENT_QUOTES) ?>"><?php if ($isSandbox): ?><?= htmlspecialchars(Lang::t('sandbox_go_live')) ?> — <?php endif; ?><?= htmlspecialchars(Lang::t('sub_pay', ['amount' => $amt[1]])) ?></span>
      </button>
    </form>
  </div>
  <?php endforeach; ?>
</div>

<script>
document.querySelectorAll('.checkout-form').forEach(function(form){
  var monthsInput  = form.querySelector('.months-input');
  var gatewayInput = form.querySelector('.gateway-input');
  var phoneGroup   = form.querySelector('.phone-group');
  var totalAmt     = form.querySelector('.pt-amt');
  var cta          = form.querySelector('.pay-cta');
  var payTpl       = cta.dataset.tpl;
  var goLive       = cta.dataset.golive === '1';
  var goLivePrefix = cta.dataset.golivetxt + ' — ';

  function currentAmt(){
    var p = form.querySelector('.pay-period.active');
    return p ? p.dataset.amt : '';
  }
  function refresh(){
    var amt = currentAmt();
    totalAmt.textContent = amt;
    cta.textContent = (goLive ? goLivePrefix : '') + payTpl.replace('__AMT__', amt);
    phoneGroup.style.display = (gatewayInput.value === 'snippe') ? 'block' : 'none';
  }

  form.querySelectorAll('.pay-period').forEach(function(btn){
    btn.addEventListener('click', function(){
      form.querySelectorAll('.pay-period').forEach(function(b){ b.classList.remove('active'); });
      btn.classList.add('active');
      monthsInput.value = btn.dataset.m;
      refresh();
    });
  });
  form.querySelectorAll('.pay-method2').forEach(function(btn){
    btn.addEventListener('click', function(){
      form.querySelectorAll('.pay-method2').forEach(function(b){ b.classList.remove('active'); });
      btn.classList.add('active');
      gatewayInput.value = btn.dataset.gw;
      refresh();
    });
  });
  refresh();
});

/* Arriving via "Go Live" — scroll the highlighted service card into view. */
(function(){
  var target = document.querySelector('.card-golive');
  if (target) { setTimeout(function(){ target.scrollIntoView({behavior:'smooth', block:'center'}); }, 200); }
})();
</script>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
