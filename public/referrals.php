<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

$rewards = ReferralReward::forReferrer($tenantId);
$totalEarned = array_sum(array_map(fn ($r) => (float) $r['amount'], $rewards));
$referredCount = Tenant::countReferred($tenantId);
$credit = (float) $tenant['referral_credit'];

$baseUrl = rtrim($config['app']['url'] ?? '', '/');
$refLink = $baseUrl . '/register.php?ref=' . urlencode($tenant['referral_code'] ?? '');

$pageTitle = Lang::t('ref_title');
$activeSide = 'referrals';
require __DIR__ . '/includes/dash_header.php';
?>

<p style="color:var(--text-muted);margin-bottom:22px"><?php e('ref_intro'); ?></p>

<div class="grid grid-3" style="margin-bottom:24px">
  <div class="card stat-tile"><div class="num" style="color:var(--primary)">$<?= number_format($credit, 2) ?></div><div class="lbl"><?php e('ref_credit'); ?></div></div>
  <div class="card stat-tile"><div class="num"><?= (int) $referredCount ?></div><div class="lbl"><?php e('ref_referred'); ?></div></div>
  <div class="card stat-tile"><div class="num">$<?= number_format($totalEarned, 2) ?></div><div class="lbl"><?php e('ref_earned'); ?></div></div>
</div>

<div class="card" style="margin-bottom:24px">
  <h3 style="margin-top:0"><?php e('ref_your_link'); ?></h3>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <input class="form-control" id="refLink" style="flex:1;min-width:260px;font-family:monospace" type="text" value="<?= htmlspecialchars($refLink) ?>" readonly>
    <button class="btn btn-primary" id="copyBtn" type="button"><i class="fa-solid fa-copy"></i> <?php e('ref_copy'); ?></button>
  </div>
</div>

<div class="card" style="padding:0;overflow-x:auto">
  <h3 style="padding:18px 18px 0;margin:0"><?php e('ref_history'); ?></h3>
  <table style="width:100%;border-collapse:collapse;min-width:520px;margin-top:12px">
    <thead>
      <tr style="text-align:left;border-bottom:1px solid var(--border)">
        <th style="padding:12px 18px;font-size:.8rem;color:var(--text-muted)"><?php e('ref_who'); ?></th>
        <th style="padding:12px 18px;font-size:.8rem;color:var(--text-muted)"><?php e('ref_amount'); ?></th>
        <th style="padding:12px 18px;font-size:.8rem;color:var(--text-muted)"><?php e('ref_when'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rewards)): ?>
      <tr><td colspan="3" style="padding:30px;text-align:center;color:var(--text-muted)"><?php e('ref_none'); ?></td></tr>
      <?php else: foreach ($rewards as $r): ?>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:12px 18px;font-weight:600"><?= htmlspecialchars($r['referred_name']) ?></td>
        <td style="padding:12px 18px;color:var(--success);font-weight:600">+<?= htmlspecialchars($r['currency']) ?> <?= number_format((float) $r['amount'], 2) ?></td>
        <td style="padding:12px 18px;color:var(--text-muted);font-size:.85rem"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<script>
document.getElementById('copyBtn').addEventListener('click', function () {
  var input = document.getElementById('refLink');
  input.select();
  navigator.clipboard.writeText(input.value).then(function () {
    var btn = document.getElementById('copyBtn');
    btn.innerHTML = '<i class="fa-solid fa-check"></i> <?= htmlspecialchars(Lang::t('ref_copied'), ENT_QUOTES) ?>';
    setTimeout(function () { btn.innerHTML = '<i class="fa-solid fa-copy"></i> <?= htmlspecialchars(Lang::t('ref_copy'), ENT_QUOTES) ?>'; }, 1500);
  });
});
</script>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
