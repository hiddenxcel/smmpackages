<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

$billing = new SubscriptionBilling($config);

$notice = null;
$noticeType = 'success';
$redirectUrl = null;

if (isset($_GET['paid']))      { $notice = Lang::t('sub_paid'); }
if (isset($_GET['cancelled'])) { $notice = Lang::t('sub_cancelled'); $noticeType = 'danger'; }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();

    $serviceKey = $_POST['service_key'] ?? '';
    $months = (int) ($_POST['months'] ?? 1);
    $gateway = $_POST['gateway'] ?? '';
    $extra = ['phone' => trim($_POST['phone'] ?? '')];

    $result = $billing->checkout($tenant, $serviceKey, $months, $gateway, $extra);

    if (!empty($result['success'])) {
        if (!empty($result['redirect_url'])) {
            // Crypto gateways: bounce the tenant to the hosted invoice.
            header('Location: ' . $result['redirect_url']);
            exit;
        }
        // Snippe: on-phone USSD, stay here with a "check your phone" notice.
        $notice = Lang::t('sub_ussd_sent');
        $noticeType = 'success';
    } else {
        $notice = Lang::t('sub_failed', ['msg' => $result['message'] ?? '']);
        $noticeType = 'danger';
    }
}

$plans = Plan::allActive();
$gate = Subscription::statusMap($tenantId);

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

<p style="color:var(--text-muted);margin-bottom:24px"><?php e('sub_intro'); ?></p>

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

<div class="grid grid-2">
  <?php foreach ($plans as $plan): $key = $plan['service_key']; $active = $gate[$key] ?? false;
        $activeSub = $active ? Subscription::activeForService($tenantId, $key) : null; ?>
  <div class="card">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px">
      <div class="card-icon"><i class="<?= $svcIcon[$key] ?? 'fa-solid fa-cube' ?>"></i></div>
      <div>
        <h3 style="margin:0"><?= htmlspecialchars($plan['name']) ?></h3>
        <div style="font-size:.9rem;color:var(--text-muted)"><?= money((float) $plan['price_monthly']) ?><?php e('per_month'); ?></div>
      </div>
      <?php if ($active): ?>
        <span class="status-chip active" style="position:static;margin-left:auto">✅ <?php e('status_active'); ?></span>
      <?php endif; ?>
    </div>

    <?php if ($active && $activeSub && !empty($activeSub['ends_at'])): ?>
      <p style="font-size:.9rem;color:var(--text-muted);margin-bottom:14px">
        <i class="fa-regular fa-clock"></i>
        <?= htmlspecialchars(Lang::t('sub_active_until', ['date' => date('M j, Y', strtotime($activeSub['ends_at']))])) ?>
      </p>
    <?php endif; ?>

    <form method="post" action="subscription.php" class="checkout-form">
      <?= Csrf::field() ?>
      <input type="hidden" name="service_key" value="<?= htmlspecialchars($key) ?>">

      <div class="form-group">
        <label><?php e('sub_choose_period'); ?></label>
        <select class="form-control period-select" name="months"
                data-m1="<?= money((float) $plan['price_monthly']) ?>"
                data-m3="<?= money((float) $plan['price_monthly'] * 3) ?>"
                data-m6="<?= money((float) $plan['price_monthly'] * 6) ?>"
                data-m12="<?= money((float) $plan['price_yearly']) ?>">
          <option value="1" data-amt="<?= money((float) $plan['price_monthly']) ?>"><?php e('sub_1m'); ?> — <?= money((float) $plan['price_monthly']) ?></option>
          <option value="3" data-amt="<?= money((float) $plan['price_monthly'] * 3) ?>"><?php e('sub_3m'); ?> — <?= money((float) $plan['price_monthly'] * 3) ?></option>
          <option value="6" data-amt="<?= money((float) $plan['price_monthly'] * 6) ?>"><?php e('sub_6m'); ?> — <?= money((float) $plan['price_monthly'] * 6) ?></option>
          <option value="12" data-amt="<?= money((float) $plan['price_yearly']) ?>"><?php e('sub_12m'); ?> — <?= money((float) $plan['price_yearly']) ?></option>
        </select>
      </div>

      <div class="form-group">
        <label><?php e('sub_choose_gateway'); ?></label>
        <select class="form-control gateway-select" name="gateway">
          <option value="nowpayments"><?php e('sub_gw_nowpayments'); ?></option>
          <option value="binance"><?php e('sub_gw_binance'); ?></option>
          <option value="snippe"><?php e('sub_gw_snippe'); ?></option>
        </select>
      </div>

      <div class="form-group phone-group" style="display:none">
        <label><?php e('sub_phone_label'); ?></label>
        <input class="form-control" type="tel" name="phone" value="<?= htmlspecialchars($tenant['phone'] ?? '') ?>" placeholder="+255...">
      </div>

      <button class="btn btn-primary btn-block" type="submit">
        <i class="fa-solid fa-lock"></i>
        <span class="pay-label"><?= htmlspecialchars(Lang::t('sub_pay', ['amount' => money((float) $plan['price_monthly'])])) ?></span>
      </button>
    </form>
  </div>
  <?php endforeach; ?>
</div>

<script>
document.querySelectorAll('.checkout-form').forEach(function(form){
  var period = form.querySelector('.period-select');
  var gateway = form.querySelector('.gateway-select');
  var phoneGroup = form.querySelector('.phone-group');
  var payLabel = form.querySelector('.pay-label');
  var payTpl = <?= json_encode(Lang::t('sub_pay', ['amount' => '__AMT__'])) ?>;

  function refresh(){
    var amt = period.options[period.selectedIndex].dataset.amt;
    payLabel.textContent = payTpl.replace('__AMT__', amt);
    phoneGroup.style.display = (gateway.value === 'snippe') ? 'block' : 'none';
  }
  period.addEventListener('change', refresh);
  gateway.addEventListener('change', refresh);
  refresh();
});
</script>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
