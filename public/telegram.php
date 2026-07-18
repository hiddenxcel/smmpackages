<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

$gated = Subscription::isServiceActive($tenantId, 'order_bot');
$notice = null;
$noticeType = 'success';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $gated) {
    Csrf::verify();
    $token = trim($_POST['bot_token'] ?? '');

    // Validate the token with Telegram (getMe) unless the user left it blank to keep the existing one.
    $existing = TenantTelegram::forTenant($tenantId);
    if ($token === '' && $existing === null) {
        $notice = Lang::t('err_required');
        $noticeType = 'danger';
    } else {
        $validateToken = $token !== '' ? $token : (string) TenantTelegram::token($existing);
        $client = new TelegramClient($validateToken, $tenantId);
        $me = $client->getMe();

        if ($me === null) {
            $notice = Lang::t('tg_invalid');
            $noticeType = 'danger';
        } else {
            $saved = TenantTelegram::save($tenantId, $token !== '' ? $token : null, $me['username'] ?? null);
            $baseUrl = rtrim($config['app']['url'] ?? '', '/');
            $webhookUrl = $baseUrl . '/webhooks/telegram.php?s=' . $saved['secret'];
            $hook = $client->setWebhook($webhookUrl);

            if (empty($hook['ok'])) {
                $notice = Lang::t('tg_webhook_fail');
                $noticeType = 'danger';
            } else {
                $notice = Lang::t('tg_saved');
            }
        }
    }
}

$telegram = TenantTelegram::forTenant($tenantId);

$pageTitle = Lang::t('tg_title');
$activeSide = 'telegram';
require __DIR__ . '/includes/dash_header.php';
?>

<p style="color:var(--text-muted);margin-bottom:20px"><?php e('tg_intro'); ?></p>

<?php if ($notice !== null): ?>
<div class="alert alert-<?= $noticeType ?>"><i class="fa-solid <?= $noticeType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="margin-top:3px"></i> <?= htmlspecialchars($notice) ?></div>
<?php endif; ?>

<?php if (!$gated): ?>
<div class="alert alert-danger">
  <i class="fa-solid fa-lock" style="margin-top:3px"></i>
  <?php e('tg_locked'); ?> <a href="subscription.php" style="margin-left:6px;font-weight:600"><?php e('buy_now'); ?></a>
</div>
<?php else: ?>

<?php if ($telegram !== null && !empty($telegram['bot_username'])): ?>
<div class="card" style="margin-bottom:22px">
  <div style="display:flex;align-items:center;gap:12px">
    <div class="card-icon"><i class="fa-brands fa-telegram"></i></div>
    <div style="flex:1">
      <h3 style="margin:0"><?= htmlspecialchars(Lang::t('tg_connected', ['username' => $telegram['bot_username']])) ?></h3>
    </div>
    <span class="status-chip <?= $telegram['status'] === 'active' ? 'active' : 'danger' ?>" style="position:static"><?= htmlspecialchars($telegram['status']) ?></span>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <form method="post" action="telegram.php">
    <?= Csrf::field() ?>
    <div class="form-group">
      <label><?php e('tg_token'); ?></label>
      <input class="form-control" type="password" name="bot_token" placeholder="<?= $telegram && $telegram['bot_token_enc'] ? '••••••• (saved)' : '123456:ABC-DEF...' ?>">
      <div class="form-hint"><?php e('tg_token_hint'); ?></div>
    </div>
    <button class="btn btn-primary" type="submit"><i class="fa-brands fa-telegram"></i> <?php e('tg_connect'); ?></button>
  </form>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
