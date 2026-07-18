<?php
require_once __DIR__ . '/_boot.php';
$admin = SuperadminAuth::require('login.php');

$mrr = AdminStats::mrr();
$arr = $mrr * 12;
$byService = AdminStats::activeByService();
$tenants = AdminStats::tenantCounts();

// Churn proxy: subscriptions expired/cancelled in the last 30 days vs active.
$db = DB::conn();
$expired30 = (int) $db->query(
    "SELECT COUNT(*) FROM subscriptions WHERE status IN ('expired','cancelled') AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
)->fetchColumn();
$activeNow = (int) $db->query(
    "SELECT COUNT(*) FROM subscriptions WHERE status='active' AND (ends_at IS NULL OR ends_at>NOW())"
)->fetchColumn();
$churn = ($activeNow + $expired30) > 0 ? round($expired30 / ($activeNow + $expired30) * 100, 1) : 0.0;

// Revenue by gateway.
$byGateway = $db->query(
    "SELECT gateway, COALESCE(SUM(amount),0) AS total FROM subscription_payments WHERE status='success' GROUP BY gateway"
)->fetchAll();

$pageTitle = 'Reports';
$activeNav = 'reports';
require __DIR__ . '/_layout.php';
?>

<div class="grid grid-4" style="margin-bottom:24px">
  <div class="card stat-tile"><div class="num" style="color:var(--primary)"><?= money($mrr) ?></div><div class="lbl">MRR</div></div>
  <div class="card stat-tile"><div class="num"><?= money($arr) ?></div><div class="lbl">ARR (projected)</div></div>
  <div class="card stat-tile"><div class="num"><?= $churn ?>%</div><div class="lbl">Churn (30d)</div></div>
  <div class="card stat-tile"><div class="num"><?= (int) $tenants['active_paying'] ?></div><div class="lbl">Paying tenants</div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3 style="margin-top:0">Active subscriptions by service</h3>
    <?php foreach ($serviceLabels as $key => $label): ?>
    <div class="sys-row"><span><?= htmlspecialchars($label) ?></span><strong style="margin-left:auto"><?= (int) ($byService[$key] ?? 0) ?></strong></div>
    <?php endforeach; ?>
  </div>
  <div class="card">
    <h3 style="margin-top:0">Revenue by gateway (all time)</h3>
    <?php if (empty($byGateway)): ?><p style="color:var(--text-muted)">No revenue yet.</p><?php else: foreach ($byGateway as $g): ?>
    <div class="sys-row"><span><?= htmlspecialchars($g['gateway']) ?></span><strong style="margin-left:auto"><?= money((float) $g['total']) ?></strong></div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
