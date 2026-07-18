<?php
require_once __DIR__ . '/_boot.php';
$admin = SuperadminAuth::require('login.php');

$notice = null;

// Change super-admin password.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();
    if (($_POST['action'] ?? '') === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        if (!Superadmin::verifyPassword($admin, $current)) {
            $notice = ['danger', 'Current password is incorrect.'];
        } elseif (strlen($new) < 8) {
            $notice = ['danger', 'New password must be at least 8 characters.'];
        } else {
            DB::conn()->prepare('UPDATE superadmins SET password_hash=? WHERE id=?')
                ->execute([password_hash($new, PASSWORD_BCRYPT), (int) $admin['id']]);
            ActivityLog::record('superadmin', (int) $admin['id'], 'hx_password_change');
            $notice = ['success', 'Password updated.'];
        }
    }
}

// Config health (booleans only — never echo secrets).
$health = [
    'APP_KEY set' => !empty($config['app']['key']),
    'NOWPayments key' => !empty($config['billing']['nowpayments']['api_key']),
    'Binance Pay key' => !empty($config['billing']['binance']['api_key']),
    'Snippe key' => !empty($config['billing']['snippe']['api_key']),
    'Meta app secret' => !empty($config['meta']['app_secret']),
    'Meta verify token' => !empty($config['meta']['verify_token']),
];

$pageTitle = 'Settings';
$activeNav = 'settings';
require __DIR__ . '/_layout.php';
?>

<?php if ($notice !== null): ?>
<div class="alert alert-<?= $notice[0] ?>"><i class="fa-solid <?= $notice[0] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="margin-top:3px"></i> <?= htmlspecialchars($notice[1]) ?></div>
<?php endif; ?>

<div class="grid grid-2">
  <div class="card">
    <h3 style="margin-top:0">Configuration health</h3>
    <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:14px">Secrets live in config/.env and are never shown here.</p>
    <?php foreach ($health as $label => $ok): ?>
    <div class="sys-row">
      <i class="fa-solid <?= $ok ? 'fa-circle-check ok' : 'fa-circle-xmark' ?>" style="<?= $ok ? '' : 'color:var(--danger)' ?>"></i>
      <span><?= htmlspecialchars($label) ?></span>
      <strong style="margin-left:auto;font-size:.85rem;color:var(--text-muted)"><?= $ok ? 'set' : 'missing' ?></strong>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h3 style="margin-top:0">Change password</h3>
    <form method="post" action="settings.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="password">
      <div class="form-group"><label>Current password</label><input class="form-control" type="password" name="current_password" required></div>
      <div class="form-group"><label>New password</label><input class="form-control" type="password" name="new_password" minlength="8" required></div>
      <button class="btn btn-primary" type="submit">Update password</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
