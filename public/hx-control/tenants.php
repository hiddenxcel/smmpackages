<?php
require_once __DIR__ . '/_boot.php';
$admin = SuperadminAuth::require('login.php');

$notice = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();
    $action = $_POST['action'] ?? '';
    $tid = (int) ($_POST['tenant_id'] ?? 0);
    $tenant = Tenant::find($tid);

    if ($tenant !== null) {
        if ($action === 'suspend') {
            DB::conn()->prepare("UPDATE tenants SET status='suspended' WHERE id=?")->execute([$tid]);
            ActivityLog::record('superadmin', (int) $admin['id'], 'tenant_suspend', ['tenant_id' => $tid]);
            $notice = "Tenant #{$tid} suspended.";
        }
        if ($action === 'activate') {
            DB::conn()->prepare("UPDATE tenants SET status='active' WHERE id=?")->execute([$tid]);
            ActivityLog::record('superadmin', (int) $admin['id'], 'tenant_activate', ['tenant_id' => $tid]);
            $notice = "Tenant #{$tid} activated.";
        }
    }
}

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $stmt = DB::conn()->prepare(
        "SELECT t.*,
            (SELECT COUNT(*) FROM subscriptions s WHERE s.tenant_id=t.id AND s.status='active' AND (s.ends_at IS NULL OR s.ends_at>NOW())) AS active_subs
         FROM tenants t
         WHERE t.business_name LIKE ? OR t.email LIKE ?
         ORDER BY t.created_at DESC LIMIT 200"
    );
    $like = '%' . addcslashes($q, '%_') . '%';
    $stmt->execute([$like, $like]);
} else {
    $stmt = DB::conn()->query(
        "SELECT t.*,
            (SELECT COUNT(*) FROM subscriptions s WHERE s.tenant_id=t.id AND s.status='active' AND (s.ends_at IS NULL OR s.ends_at>NOW())) AS active_subs
         FROM tenants t ORDER BY t.created_at DESC LIMIT 200"
    );
}
$rows = $stmt->fetchAll();

$pageTitle = 'Tenants';
$activeNav = 'tenants';
require __DIR__ . '/_layout.php';
?>

<?php if ($notice !== null): ?>
<div class="alert alert-success"><i class="fa-solid fa-circle-check" style="margin-top:3px"></i> <?= htmlspecialchars($notice) ?></div>
<?php endif; ?>

<form method="get" action="tenants.php" style="margin-bottom:18px;display:flex;gap:10px;max-width:420px">
  <input class="form-control" type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search name or email…">
  <button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
</form>

<div class="card" style="padding:0;overflow-x:auto">
  <table style="width:100%;border-collapse:collapse;min-width:760px">
    <thead>
      <tr style="text-align:left;border-bottom:1px solid var(--border)">
        <?php foreach (['ID','Business','Email','Lang','Active svc','Status','Joined',''] as $h): ?>
        <th style="padding:12px 16px;font-size:.78rem;color:var(--text-muted)"><?= $h ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
      <tr><td colspan="8" style="padding:30px;text-align:center;color:var(--text-muted)">No tenants found.</td></tr>
      <?php else: foreach ($rows as $t): ?>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:12px 16px;color:var(--text-muted)">#<?= (int) $t['id'] ?></td>
        <td style="padding:12px 16px;font-weight:600"><?= htmlspecialchars($t['business_name']) ?></td>
        <td style="padding:12px 16px;font-size:.88rem"><?= htmlspecialchars($t['email']) ?></td>
        <td style="padding:12px 16px;text-transform:uppercase;font-size:.8rem"><?= htmlspecialchars($t['lang']) ?></td>
        <td style="padding:12px 16px"><?= (int) $t['active_subs'] ?></td>
        <td style="padding:12px 16px"><span class="status-chip <?= $t['status'] === 'active' ? 'active' : 'danger' ?>" style="position:static"><?= htmlspecialchars($t['status']) ?></span></td>
        <td style="padding:12px 16px;color:var(--text-muted);font-size:.83rem"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
        <td style="padding:12px 16px">
          <form method="post" action="tenants.php" style="display:inline" onsubmit="return confirm('Change status for #<?= (int) $t['id'] ?>?')">
            <?= Csrf::field() ?>
            <input type="hidden" name="tenant_id" value="<?= (int) $t['id'] ?>">
            <?php if ($t['status'] === 'active'): ?>
              <input type="hidden" name="action" value="suspend">
              <button class="btn btn-outline" style="padding:6px 12px;color:var(--danger);border-color:var(--danger)" type="submit">Suspend</button>
            <?php else: ?>
              <input type="hidden" name="action" value="activate">
              <button class="btn btn-outline" style="padding:6px 12px;color:var(--success);border-color:var(--success)" type="submit">Activate</button>
            <?php endif; ?>
          </form>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
