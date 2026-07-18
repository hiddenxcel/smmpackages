<?php
require_once __DIR__ . '/_boot.php';
$admin = SuperadminAuth::require('login.php');

$notice = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();
    $pid = (int) ($_POST['plan_id'] ?? 0);
    $monthly = (float) ($_POST['price_monthly'] ?? 0);
    $yearly = (float) ($_POST['price_yearly'] ?? 0);
    $maxPanels = (int) ($_POST['max_panels'] ?? 1);
    $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

    if ($pid > 0) {
        DB::conn()->prepare(
            'UPDATE plans SET price_monthly=?, price_yearly=?, max_panels=?, status=? WHERE id=?'
        )->execute([$monthly, $yearly, $maxPanels, $status, $pid]);
        ActivityLog::record('superadmin', (int) $admin['id'], 'plan_update', ['plan_id' => $pid, 'monthly' => $monthly]);
        $notice = 'Plan updated.';
    }
}

$plans = DB::conn()->query('SELECT * FROM plans ORDER BY sort_order')->fetchAll();

$pageTitle = 'Plans';
$activeNav = 'plans';
require __DIR__ . '/_layout.php';
?>

<?php if ($notice !== null): ?>
<div class="alert alert-success"><i class="fa-solid fa-circle-check" style="margin-top:3px"></i> <?= htmlspecialchars($notice) ?></div>
<?php endif; ?>

<div class="grid grid-2">
  <?php foreach ($plans as $p): ?>
  <div class="card">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
      <h3 style="margin:0;flex:1"><?= htmlspecialchars($p['name']) ?></h3>
      <span class="status-chip <?= $p['status'] === 'active' ? 'active' : 'locked' ?>" style="position:static"><?= htmlspecialchars($p['status']) ?></span>
    </div>
    <div style="font-size:.82rem;color:var(--text-muted);margin-bottom:12px"><?= htmlspecialchars($p['service_key']) ?> · <?= htmlspecialchars($p['currency']) ?></div>
    <form method="post" action="plans.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="plan_id" value="<?= (int) $p['id'] ?>">
      <div class="grid grid-2">
        <div class="form-group"><label>Monthly</label><input class="form-control" type="number" step="0.01" name="price_monthly" value="<?= htmlspecialchars($p['price_monthly']) ?>"></div>
        <div class="form-group"><label>Yearly</label><input class="form-control" type="number" step="0.01" name="price_yearly" value="<?= htmlspecialchars($p['price_yearly']) ?>"></div>
      </div>
      <div class="grid grid-2">
        <div class="form-group"><label>Max panels</label><input class="form-control" type="number" name="max_panels" value="<?= (int) $p['max_panels'] ?>"></div>
        <div class="form-group"><label>Status</label>
          <select class="form-control" name="status">
            <option value="active" <?= $p['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $p['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
      </div>
      <button class="btn btn-primary" type="submit">Save</button>
    </form>
  </div>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
