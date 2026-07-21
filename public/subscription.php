<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

$notice = null;
$noticeType = 'success';

if (isset($_GET['paid']))      { $notice = Lang::t('sub_paid'); }
if (isset($_GET['cancelled'])) { $notice = Lang::t('sub_cancelled'); $noticeType = 'danger'; }
if (isset($_GET['ussd']))      { $notice = Lang::t('sub_ussd_sent'); }

// Payment method selection + gateway checkout now live on checkout.php; this
// page only picks a service + term, then links there.

$plans = Plan::allActive();
$gate = Subscription::statusMap($tenantId);
$stateMap = Subscription::stateMap($tenantId);          // active | sandbox | locked
$goLive = isset($_GET['golive']);                        // arrived via a "Go Live" button
$goLiveSvc = $_GET['svc'] ?? '';                          // a specific service to highlight

// Focused checkout: arriving with ?svc=<key> (from a "Subscribe" button) shows
// ONLY that one service, so the reseller isn't overwhelmed by every plan. If the
// key is unknown we fall back to the full catalogue.
$focusSvc = '';
if ($goLiveSvc !== '' && in_array($goLiveSvc, array_column($plans, 'service_key'), true)) {
    $focusSvc = $goLiveSvc;
    $plans = array_values(array_filter($plans, fn ($p) => $p['service_key'] === $focusSvc));
}

$sandboxCount = count(array_filter($stateMap, fn ($s) => $s === 'sandbox'));

$svcIcon = [
    'order_bot'     => 'fa-solid fa-cart-shopping',
    'support_bot'   => 'fa-solid fa-headset',
    'ai_tickets'    => 'fa-solid fa-robot',
    'ai_chat'       => 'fa-solid fa-comments',
    'number_rental' => 'fa-solid fa-phone',
];

$pageTitle = Lang::t('sub_title');
$activeSide = 'subscription';
require __DIR__ . '/includes/dash_header.php';
?>

<?php if ($focusSvc !== ''): ?>
<style>.sub-focus-grid{display:block}.sub-focus-grid > .card{width:100%}</style>
<!-- Focused checkout: only the one service the reseller chose to subscribe to. -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:22px;flex-wrap:wrap">
  <a href="subscription.php" class="btn btn-outline" style="padding:8px 16px">
    <i class="fa-solid fa-arrow-left"></i> <?php e('sub_view_all'); ?>
  </a>
  <span style="color:var(--text-muted)"><?php e('sub_focus_note'); ?></span>
</div>
<?php elseif ($goLive && $sandboxCount > 0): ?>
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

<div class="<?= $focusSvc !== '' ? 'sub-focus-grid' : 'grid grid-2' ?>"<?= $focusSvc !== '' ? ' style="max-width:560px"' : '' ?>>
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
    <form method="get" action="checkout.php" class="checkout-form">
      <input type="hidden" name="svc" value="<?= htmlspecialchars($key) ?>">
      <input type="hidden" name="months" value="1" class="months-input">

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

      <div class="pay-total">
        <span class="pt-label"><?php e('sub_total'); ?></span>
        <span class="pt-amt"><?= htmlspecialchars($amt[1]) ?></span>
      </div>

      <button class="btn btn-primary btn-block btn-lg" type="submit">
        <i class="fa-solid <?= $isSandbox ? 'fa-rocket' : 'fa-arrow-right' ?>"></i>
        <span class="pay-cta" data-tpl="<?= htmlspecialchars(Lang::t('sub_pay', ['amount' => '__AMT__']), ENT_QUOTES) ?>" data-golive="<?= $isSandbox ? '1' : '0' ?>" data-golivetxt="<?= htmlspecialchars(Lang::t('sandbox_go_live'), ENT_QUOTES) ?>"><?php if ($isSandbox): ?><?= htmlspecialchars(Lang::t('sandbox_go_live')) ?> — <?php endif; ?><?= htmlspecialchars(Lang::t('sub_pay', ['amount' => $amt[1]])) ?></span>
      </button>
    </form>
  </div>
  <?php endforeach; ?>
</div>

<script>
document.querySelectorAll('.checkout-form').forEach(function(form){
  var monthsInput  = form.querySelector('.months-input');
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
  }

  form.querySelectorAll('.pay-period').forEach(function(btn){
    btn.addEventListener('click', function(){
      form.querySelectorAll('.pay-period').forEach(function(b){ b.classList.remove('active'); });
      btn.classList.add('active');
      monthsInput.value = btn.dataset.m;
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
