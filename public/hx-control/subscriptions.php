<?php
require_once __DIR__ . '/_boot.php';
$admin = SuperadminAuth::require('login.php');

$fStatus = $_GET['status'] ?? '';
$fService = $_GET['service'] ?? '';

$where = [];
$params = [];
if (in_array($fStatus, ['pending', 'active', 'expired', 'cancelled'], true)) {
    $where[] = 's.status = ?';
    $params[] = $fStatus;
}
if (in_array($fService, Subscription::SERVICES, true)) {
    $where[] = 's.service_key = ?';
    $params[] = $fService;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = DB::conn()->prepare(
    "SELECT s.*, t.business_name, p.name AS plan_name
     FROM subscriptions s
     JOIN tenants t ON t.id = s.tenant_id
     LEFT JOIN plans p ON p.id = s.plan_id
     {$whereSql}
     ORDER BY s.created_at DESC LIMIT 300"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Subscriptions';
$activeNav = 'subscriptions';
require __DIR__ . '/_layout.php';
?>

<form method="get" action="subscriptions.php" style="margin-bottom:18px;display:flex;gap:10px;flex-wrap:wrap">
  <select class="form-control" style="width:auto" name="status" onchange="this.form.submit()">
    <option value="">All statuses</option>
    <?php foreach (['active','pending','expired','cancelled'] as $s): ?>
    <option value="<?= $s ?>" <?= $fStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="form-control" style="width:auto" name="service" onchange="this.form.submit()">
    <option value="">All services</option>
    <?php foreach (Subscription::SERVICES as $sv): ?>
    <option value="<?= $sv ?>" <?= $fService === $sv ? 'selected' : '' ?>><?= htmlspecialchars($sv) ?></option>
    <?php endforeach; ?>
  </select>
</form>

<div class="card" style="padding:0;overflow-x:auto">
  <table style="width:100%;border-collapse:collapse;min-width:720px">
    <thead>
      <tr style="text-align:left;border-bottom:1px solid var(--border)">
        <?php foreach (['Tenant','Service','Status','Starts','Ends'] as $h): ?>
        <th style="padding:12px 16px;font-size:.78rem;color:var(--text-muted)"><?= $h ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
      <tr><td colspan="5" style="padding:30px;text-align:center;color:var(--text-muted)">No subscriptions.</td></tr>
      <?php else: foreach ($rows as $r):
        $chip = match ($r['status']) { 'active' => 'active', 'expired','cancelled' => 'danger', default => 'locked' }; ?>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:12px 16px;font-weight:600"><?= htmlspecialchars($r['business_name']) ?></td>
        <td style="padding:12px 16px"><?= htmlspecialchars($r['service_key']) ?></td>
        <td style="padding:12px 16px"><span class="status-chip <?= $chip ?>" style="position:static"><?= htmlspecialchars($r['status']) ?></span></td>
        <td style="padding:12px 16px;color:var(--text-muted);font-size:.83rem"><?= $r['starts_at'] ? date('M j, Y', strtotime($r['starts_at'])) : '—' ?></td>
        <td style="padding:12px 16px;color:var(--text-muted);font-size:.83rem"><?= $r['ends_at'] ? date('M j, Y', strtotime($r['ends_at'])) : '—' ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
