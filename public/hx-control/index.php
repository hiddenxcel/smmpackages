<?php
require_once __DIR__ . '/_boot.php';
$admin = SuperadminAuth::require('login.php');

$mrr = AdminStats::mrr();
$revMonth = AdminStats::revenueThisMonth();
$revTotal = AdminStats::totalRevenue();
$tenants = AdminStats::tenantCounts();
$byService = AdminStats::activeByService();
$growth = AdminStats::tenantGrowth(6);

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/_layout.php';
?>

<div class="grid grid-4" style="margin-bottom:24px">
  <div class="card stat-tile"><div class="num" style="color:var(--primary)"><?= money($mrr) ?></div><div class="lbl">MRR</div></div>
  <div class="card stat-tile"><div class="num"><?= money($revMonth) ?></div><div class="lbl">Revenue (this month)</div></div>
  <div class="card stat-tile"><div class="num"><?= money($revTotal) ?></div><div class="lbl">Revenue (total)</div></div>
  <div class="card stat-tile"><div class="num"><?= (int) $tenants['active_paying'] ?></div><div class="lbl">Paying tenants</div></div>
</div>

<div class="grid grid-2" style="margin-bottom:24px">
  <div class="card">
    <h3 style="margin-top:0">Tenants</h3>
    <div class="sys-row"><i class="fa-solid fa-users ok"></i> Total: <strong style="margin-left:auto"><?= (int) $tenants['total'] ?></strong></div>
    <div class="sys-row"><i class="fa-solid fa-circle-check ok"></i> Paying: <strong style="margin-left:auto"><?= (int) $tenants['active_paying'] ?></strong></div>
    <div class="sys-row"><i class="fa-solid fa-user off"></i> Free / lapsed: <strong style="margin-left:auto"><?= (int) $tenants['free'] ?></strong></div>
    <div class="sys-row"><i class="fa-solid fa-ban" style="color:var(--danger)"></i> Suspended: <strong style="margin-left:auto"><?= (int) $tenants['suspended'] ?></strong></div>
  </div>
  <div class="card">
    <h3 style="margin-top:0">Active subscriptions by service</h3>
    <?php foreach ($serviceLabels as $key => $label): ?>
    <div class="sys-row"><span><?= htmlspecialchars($label) ?></span><strong style="margin-left:auto"><?= (int) ($byService[$key] ?? 0) ?></strong></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <h3 style="margin-top:0">New tenants (last 6 months)</h3>
  <?php if (empty($growth)): ?>
    <p style="color:var(--text-muted)">No data yet.</p>
  <?php else:
    $max = max(array_map(fn ($r) => (int) $r['n'], $growth)) ?: 1; ?>
    <div style="display:flex;align-items:flex-end;gap:14px;height:160px;padding-top:12px">
      <?php foreach ($growth as $g): $h = (int) round((int) $g['n'] / $max * 130); ?>
      <div style="flex:1;text-align:center">
        <div style="font-size:.8rem;font-weight:700;margin-bottom:4px"><?= (int) $g['n'] ?></div>
        <div style="background:var(--primary);border-radius:6px 6px 0 0;height:<?= max(4, $h) ?>px"></div>
        <div style="font-size:.72rem;color:var(--text-muted);margin-top:6px"><?= htmlspecialchars($g['ym']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
