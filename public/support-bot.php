<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

$notice = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();
    $s = BotSettings::get($tenantId, 'support');
    $s['commands']['refill'] = isset($_POST['cmd_refill']);
    $s['commands']['status'] = isset($_POST['cmd_status']);
    $s['commands']['cancel'] = isset($_POST['cmd_cancel']);
    $s['commands']['speedup'] = isset($_POST['cmd_speedup']);
    $s['spam']['enabled'] = isset($_POST['spam_enabled']);
    $s['spam']['repeat_threshold'] = max(1, (int) ($_POST['spam_threshold'] ?? 3));
    $s['spam']['window_minutes'] = max(1, (int) ($_POST['spam_window'] ?? 5));
    $s['spam']['disable_minutes'] = max(1, (int) ($_POST['spam_disable'] ?? 60));
    $staffRaw = trim($_POST['staff_numbers'] ?? '');
    $s['staff']['numbers'] = $staffRaw === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $staffRaw))));
    BotSettings::save($tenantId, 'support', $s);
    header('Location: support-bot.php?saved=1');
    exit;
}

if (isset($_GET['saved'])) {
    $notice = Lang::t('bs_saved');
}

$active = Subscription::isServiceActive($tenantId, 'support_bot');
$daysLeft = $active ? Subscription::daysLeft($tenantId, 'support_bot') : null;
$whatsapp = TenantWhatsApp::forTenant($tenantId);
$hasPanel = TenantPanel::countForTenant($tenantId) > 0;
$ruleCount = count(GuaranteeRule::forTenant($tenantId));
$settings = BotSettings::get($tenantId, 'support');

$pageTitle = Lang::t('sbot_title');
$activeSide = 'support-bot';
require __DIR__ . '/includes/dash_header.php';
?>

<?php if ($notice !== null): ?>
<div class="alert alert-success"><i class="fa-solid fa-circle-check" style="margin-top:3px"></i> <?= htmlspecialchars($notice) ?></div>
<?php endif; ?>

<?php if (!$active): ?>
<div class="alert alert-danger">
  <i class="fa-solid fa-lock" style="margin-top:3px"></i>
  <?php e('sbot_locked'); ?> <a href="subscription.php" style="margin-left:6px;font-weight:600"><?php e('buy_now'); ?></a>
</div>
<?php else: ?>
<div class="alert alert-success">
  <i class="fa-solid fa-circle-check" style="margin-top:3px"></i>
  <?php e('sbot_active_msg'); ?>
  <?php if ($daysLeft !== null): ?><strong style="margin-left:6px"><?= htmlspecialchars(Lang::t('days_left', ['days' => $daysLeft])) ?></strong><?php endif; ?>
</div>
<?php endif; ?>

<!-- Commands -->
<div class="card" style="margin-bottom:22px">
  <h3 style="margin-top:0"><?php e('sbot_commands'); ?></h3>
  <ul style="list-style:none;display:flex;flex-direction:column;gap:10px;margin-top:8px">
    <li><code style="background:var(--bg-soft);padding:3px 8px;border-radius:6px">status</code> — <?php e('sbot_cmd_status'); ?></li>
    <li><code style="background:var(--bg-soft);padding:3px 8px;border-radius:6px">refill</code> — <?php e('sbot_cmd_refill'); ?></li>
    <li><code style="background:var(--bg-soft);padding:3px 8px;border-radius:6px">cancel</code> — <?php e('sbot_cmd_cancel'); ?></li>
    <li><code style="background:var(--bg-soft);padding:3px 8px;border-radius:6px">speedup</code> — <?php e('sbot_cmd_speedup'); ?></li>
  </ul>
</div>

<!-- Readiness -->
<div class="grid grid-3">
  <div class="card"><div class="sys-row" style="border:none;padding:0">
    <i class="fa-solid <?= $active ? 'fa-circle-check ok' : 'fa-circle-minus off' ?>"></i> <strong><?php e('side_subscription'); ?></strong>
  </div></div>
  <div class="card"><div class="sys-row" style="border:none;padding:0">
    <i class="fa-solid <?= $hasPanel ? 'fa-circle-check ok' : 'fa-circle-minus off' ?>"></i> <strong><?php e('side_panels'); ?></strong>
    <?php if (!$hasPanel): ?><a href="panels.php" style="margin-left:auto;font-size:.85rem"><?php e('panels_add'); ?></a><?php endif; ?>
  </div></div>
  <div class="card"><div class="sys-row" style="border:none;padding:0">
    <i class="fa-solid <?= $ruleCount > 0 ? 'fa-circle-check ok' : 'fa-circle-minus off' ?>"></i> <strong><?php e('gr_title'); ?></strong>
    <a href="guarantee-rules.php" style="margin-left:auto;font-size:.85rem"><?= $ruleCount ?></a>
  </div></div>
</div>

<!-- Settings -->
<form method="post" action="support-bot.php" style="margin-top:22px">
  <?= Csrf::field() ?>
  <div class="grid grid-2">
    <div class="card">
      <h3 style="margin-top:0"><i class="fa-solid fa-sliders"></i> <?php e('bs_commands'); ?></h3>
      <label style="display:flex;align-items:center;gap:10px;padding:8px 0"><input type="checkbox" name="cmd_status" <?= $settings['commands']['status'] ? 'checked' : '' ?>> <?php e('bs_cmd_status'); ?></label>
      <label style="display:flex;align-items:center;gap:10px;padding:8px 0"><input type="checkbox" name="cmd_refill" <?= $settings['commands']['refill'] ? 'checked' : '' ?>> <?php e('bs_cmd_refill'); ?></label>
      <label style="display:flex;align-items:center;gap:10px;padding:8px 0"><input type="checkbox" name="cmd_cancel" <?= $settings['commands']['cancel'] ? 'checked' : '' ?>> <?php e('bs_cmd_cancel'); ?></label>
      <label style="display:flex;align-items:center;gap:10px;padding:8px 0"><input type="checkbox" name="cmd_speedup" <?= $settings['commands']['speedup'] ? 'checked' : '' ?>> <?php e('bs_cmd_speedup'); ?></label>
    </div>
    <div class="card">
      <h3 style="margin-top:0"><i class="fa-solid fa-shield"></i> <?php e('bs_spam'); ?></h3>
      <label style="display:flex;align-items:center;gap:10px;padding:8px 0"><input type="checkbox" name="spam_enabled" <?= $settings['spam']['enabled'] ? 'checked' : '' ?>> <?php e('bs_spam_enable'); ?></label>
      <div class="grid grid-2">
        <div class="form-group"><label><?php e('bs_spam_threshold'); ?></label><input class="form-control" type="number" name="spam_threshold" value="<?= (int) $settings['spam']['repeat_threshold'] ?>" min="1"></div>
        <div class="form-group"><label><?php e('bs_spam_window'); ?></label><input class="form-control" type="number" name="spam_window" value="<?= (int) $settings['spam']['window_minutes'] ?>" min="1"></div>
      </div>
      <div class="form-group"><label><?php e('bs_spam_disable'); ?></label><input class="form-control" type="number" name="spam_disable" value="<?= (int) $settings['spam']['disable_minutes'] ?>" min="1"></div>
    </div>
  </div>
  <div class="card" style="margin-top:18px">
    <div class="form-group" style="margin-bottom:0">
      <label><?php e('bs_staff'); ?></label>
      <input class="form-control" type="text" name="staff_numbers" value="<?= htmlspecialchars(implode(', ', $settings['staff']['numbers'])) ?>" placeholder="+255700000000, +255711111111">
    </div>
  </div>
  <button class="btn btn-primary" type="submit" style="margin-top:16px"><?php e('bs_save'); ?></button>
</form>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
