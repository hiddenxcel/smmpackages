<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

$notice = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();
    $s = BotSettings::get($tenantId, 'order');
    $s['spam']['enabled'] = isset($_POST['spam_enabled']);
    $s['spam']['repeat_threshold'] = max(1, (int) ($_POST['spam_threshold'] ?? 3));
    $s['spam']['window_minutes'] = max(1, (int) ($_POST['spam_window'] ?? 5));
    $s['spam']['disable_minutes'] = max(1, (int) ($_POST['spam_disable'] ?? 60));
    $staffRaw = trim($_POST['staff_numbers'] ?? '');
    $s['staff']['numbers'] = $staffRaw === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $staffRaw))));
    BotSettings::save($tenantId, 'order', $s);
    header('Location: order-bot.php?saved=1');
    exit;
}

if (isset($_GET['saved'])) {
    $notice = Lang::t('bs_saved');
}

$active = Subscription::isServiceActive($tenantId, 'order_bot');
$daysLeft = $active ? Subscription::daysLeft($tenantId, 'order_bot') : null;
$whatsapp = TenantWhatsApp::forTenant($tenantId);
$hasPanel = TenantPanel::countForTenant($tenantId) > 0;
$settings = BotSettings::get($tenantId, 'order');

$pageTitle = Lang::t('obot_title');
$activeSide = 'order-bot';
require __DIR__ . '/includes/dash_header.php';
?>

<?php if ($notice !== null): ?>
<div class="alert alert-success"><i class="fa-solid fa-circle-check" style="margin-top:3px"></i> <?= htmlspecialchars($notice) ?></div>
<?php endif; ?>

<?php if (!$active): ?>
<div class="alert alert-danger">
  <i class="fa-solid fa-lock" style="margin-top:3px"></i>
  <?php e('obot_locked'); ?> <a href="subscription.php" style="margin-left:6px;font-weight:600"><?php e('buy_now'); ?></a>
</div>
<?php else: ?>
<div class="alert alert-success">
  <i class="fa-solid fa-circle-check" style="margin-top:3px"></i>
  <?php e('obot_active_msg'); ?>
  <?php if ($daysLeft !== null): ?><strong style="margin-left:6px"><?= htmlspecialchars(Lang::t('days_left', ['days' => $daysLeft])) ?></strong><?php endif; ?>
</div>
<?php endif; ?>

<!-- Readiness checklist -->
<div class="card" style="margin-bottom:22px">
  <h3 style="margin-top:0"><?php e('obot_flow_title'); ?></h3>
  <p style="color:var(--text-muted)"><?php e('obot_flow'); ?></p>
</div>

<div class="grid grid-3">
  <div class="card">
    <div class="sys-row" style="border:none;padding:0">
      <i class="fa-solid <?= $active ? 'fa-circle-check ok' : 'fa-circle-minus off' ?>"></i>
      <strong><?php e('side_subscription'); ?></strong>
    </div>
  </div>
  <div class="card">
    <div class="sys-row" style="border:none;padding:0">
      <i class="fa-solid <?= $hasPanel ? 'fa-circle-check ok' : 'fa-circle-minus off' ?>"></i>
      <strong><?php e('side_panels'); ?></strong>
      <?php if (!$hasPanel): ?><a href="panels.php" style="margin-left:auto;font-size:.85rem"><?php e('panels_add'); ?></a><?php endif; ?>
    </div>
  </div>
  <div class="card">
    <div class="sys-row" style="border:none;padding:0">
      <i class="fa-solid <?= ($whatsapp && $whatsapp['status'] === 'active') ? 'fa-circle-check ok' : 'fa-circle-minus off' ?>"></i>
      <strong><?php e('wa_title'); ?></strong>
      <?php if (!$whatsapp): ?><a href="whatsapp.php" style="margin-left:auto;font-size:.85rem"><?php e('wa_save'); ?></a><?php endif; ?>
    </div>
  </div>
</div>

<!-- Spam / staff settings -->
<form method="post" action="order-bot.php" style="margin-top:22px">
  <?= Csrf::field() ?>
  <div class="card">
    <h3 style="margin-top:0"><i class="fa-solid fa-shield"></i> <?php e('bs_spam'); ?></h3>
    <label style="display:flex;align-items:center;gap:10px;padding:8px 0"><input type="checkbox" name="spam_enabled" <?= $settings['spam']['enabled'] ? 'checked' : '' ?>> <?php e('bs_spam_enable'); ?></label>
    <div class="grid grid-3">
      <div class="form-group"><label><?php e('bs_spam_threshold'); ?></label><input class="form-control" type="number" name="spam_threshold" value="<?= (int) $settings['spam']['repeat_threshold'] ?>" min="1"></div>
      <div class="form-group"><label><?php e('bs_spam_window'); ?></label><input class="form-control" type="number" name="spam_window" value="<?= (int) $settings['spam']['window_minutes'] ?>" min="1"></div>
      <div class="form-group"><label><?php e('bs_spam_disable'); ?></label><input class="form-control" type="number" name="spam_disable" value="<?= (int) $settings['spam']['disable_minutes'] ?>" min="1"></div>
    </div>
    <div class="form-group" style="margin-bottom:0">
      <label><?php e('bs_staff'); ?></label>
      <input class="form-control" type="text" name="staff_numbers" value="<?= htmlspecialchars(implode(', ', $settings['staff']['numbers'])) ?>" placeholder="+255700000000, +255711111111">
    </div>
    <button class="btn btn-primary" type="submit" style="margin-top:16px"><?php e('bs_save'); ?></button>
  </div>
</form>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
