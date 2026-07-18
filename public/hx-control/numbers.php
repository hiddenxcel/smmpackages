<?php
require_once __DIR__ . '/_boot.php';
$admin = SuperadminAuth::require('login.php');

$notice = null;
$noticeType = 'success';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $display = trim($_POST['display_number'] ?? '');
        $pnid = trim($_POST['phone_number_id'] ?? '');
        $token = trim($_POST['token'] ?? '');
        $waba = trim($_POST['waba_id'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $currency = trim($_POST['currency'] ?? 'USD') ?: 'USD';
        $cost = (float) ($_POST['monthly_cost'] ?? 0);

        if ($display === '' || $pnid === '' || $token === '') {
            $notice = 'Number, Phone Number ID and token are required.';
            $noticeType = 'danger';
        } else {
            try {
                DB::conn()->prepare(
                    "INSERT INTO platform_numbers (display_number, phone_number_id, cloud_api_token_enc, waba_id, country, currency, monthly_cost, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'available')"
                )->execute([$display, $pnid, Crypto::encrypt($token), $waba ?: null, $country ?: null, $currency, $cost]);
                ActivityLog::record('superadmin', (int) $admin['id'], 'number_add', ['pnid' => $pnid]);
                $notice = 'Number added to pool.';
            } catch (\PDOException $e) {
                $notice = 'That number or Phone Number ID already exists.';
                $noticeType = 'danger';
            }
        }
    }

    if ($action === 'delete') {
        $nid = (int) ($_POST['number_id'] ?? 0);
        // Only delete if not currently rented.
        DB::conn()->prepare("DELETE FROM platform_numbers WHERE id=? AND status<>'rented'")->execute([$nid]);
        $notice = 'Number removed.';
    }
}

$numbers = DB::conn()->query('SELECT * FROM platform_numbers ORDER BY status, country, display_number')->fetchAll();

$pageTitle = 'Numbers';
$activeNav = 'numbers';
require __DIR__ . '/_layout.php';
?>

<?php if ($notice !== null): ?>
<div class="alert alert-<?= $noticeType ?>"><i class="fa-solid <?= $noticeType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="margin-top:3px"></i> <?= htmlspecialchars($notice) ?></div>
<?php endif; ?>

<div class="grid grid-2">
  <div class="card">
    <h3 style="margin-top:0">Add a number to the pool</h3>
    <form method="post" action="numbers.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="add">
      <div class="grid grid-2">
        <div class="form-group"><label>Display number</label><input class="form-control" name="display_number" placeholder="+255..." required></div>
        <div class="form-group"><label>Phone Number ID</label><input class="form-control" name="phone_number_id" required></div>
      </div>
      <div class="form-group"><label>Cloud API token</label><input class="form-control" type="password" name="token" required></div>
      <div class="form-group"><label>WABA ID</label><input class="form-control" name="waba_id"></div>
      <div class="grid grid-2">
        <div class="form-group"><label>Country</label><input class="form-control" name="country" placeholder="Tanzania"></div>
        <div class="form-group"><label>Currency</label><input class="form-control" name="currency" value="USD"></div>
      </div>
      <div class="form-group"><label>Monthly cost (your cost)</label><input class="form-control" type="number" step="0.01" name="monthly_cost" value="0"></div>
      <button class="btn btn-primary" type="submit"><i class="fa-solid fa-plus"></i> Add number</button>
    </form>
  </div>

  <div>
    <?php if (empty($numbers)): ?>
      <div class="card" style="text-align:center;color:var(--text-muted)">No numbers in the pool.</div>
    <?php else: foreach ($numbers as $n): ?>
      <div class="card" style="margin-bottom:14px">
        <div style="display:flex;align-items:center;gap:12px">
          <i class="fa-solid fa-phone" style="color:var(--primary)"></i>
          <div style="flex:1">
            <strong><?= htmlspecialchars($n['display_number']) ?></strong>
            <div style="font-size:.82rem;color:var(--text-muted)"><?= htmlspecialchars($n['country'] ?? '—') ?> · <?= htmlspecialchars($n['currency']) ?> <?= number_format((float) $n['monthly_cost'], 2) ?>/mo</div>
          </div>
          <span class="status-chip <?= $n['status'] === 'available' ? 'active' : ($n['status'] === 'rented' ? 'locked' : 'danger') ?>" style="position:static"><?= htmlspecialchars($n['status']) ?></span>
          <?php if ($n['status'] !== 'rented'): ?>
          <form method="post" action="numbers.php" onsubmit="return confirm('Remove this number?')">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="number_id" value="<?= (int) $n['id'] ?>">
            <button class="btn btn-outline" style="padding:6px 10px;color:var(--danger);border-color:var(--danger)" type="submit"><i class="fa-solid fa-trash"></i></button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
