<?php
require_once __DIR__ . '/_boot.php';
$admin = SuperadminAuth::require('login.php');

$fStatus = $_GET['status'] ?? '';
$where = '';
$params = [];
if (in_array($fStatus, ['pending', 'success', 'failed'], true)) {
    $where = 'WHERE sp.status = ?';
    $params[] = $fStatus;
}

$stmt = DB::conn()->prepare(
    "SELECT sp.*, t.business_name
     FROM subscription_payments sp
     JOIN tenants t ON t.id = sp.tenant_id
     {$where}
     ORDER BY sp.created_at DESC LIMIT 300"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Payments';
$activeNav = 'payments';
require __DIR__ . '/_layout.php';
?>

<form method="get" action="payments.php" style="margin-bottom:18px">
  <select class="form-control" style="width:auto" name="status" onchange="this.form.submit()">
    <option value="">All statuses</option>
    <?php foreach (['success','pending','failed'] as $s): ?>
    <option value="<?= $s ?>" <?= $fStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
    <?php endforeach; ?>
  </select>
</form>

<div class="card" style="padding:0;overflow-x:auto">
  <table style="width:100%;border-collapse:collapse;min-width:800px">
    <thead>
      <tr style="text-align:left;border-bottom:1px solid var(--border)">
        <?php foreach (['Reference','Tenant','Amount','Gateway','Months','Status','Date'] as $h): ?>
        <th style="padding:12px 16px;font-size:.78rem;color:var(--text-muted)"><?= $h ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
      <tr><td colspan="7" style="padding:30px;text-align:center;color:var(--text-muted)">No payments.</td></tr>
      <?php else: foreach ($rows as $r):
        $chip = match ($r['status']) { 'success' => 'active', 'failed' => 'danger', default => 'locked' }; ?>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:12px 16px;font-family:monospace;font-size:.8rem"><?= htmlspecialchars($r['transaction_ref']) ?></td>
        <td style="padding:12px 16px"><?= htmlspecialchars($r['business_name']) ?></td>
        <td style="padding:12px 16px;font-weight:600"><?= htmlspecialchars($r['currency']) ?> <?= number_format((float) $r['amount'], 2) ?></td>
        <td style="padding:12px 16px"><?= htmlspecialchars($r['gateway']) ?></td>
        <td style="padding:12px 16px"><?= (int) $r['months'] ?></td>
        <td style="padding:12px 16px"><span class="status-chip <?= $chip ?>" style="position:static"><?= htmlspecialchars($r['status']) ?></span></td>
        <td style="padding:12px 16px;color:var(--text-muted);font-size:.83rem"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
