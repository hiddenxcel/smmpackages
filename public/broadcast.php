<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

$gated = Subscription::isServiceActive($tenantId, 'order_bot')
    || Subscription::isServiceActive($tenantId, 'support_bot');

$notice = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $gated) {
    Csrf::verify();
    $message = trim($_POST['message'] ?? '');
    if ($message !== '') {
        $res = Broadcast::create($tenantId, mb_substr($message, 0, 4000));
        header('Location: broadcast.php?queued=' . (int) $res['count']);
        exit;
    }
}

if (isset($_GET['queued'])) {
    $notice = Lang::t('bc_queued', ['count' => (int) $_GET['queued']]);
}

$eligible = $gated ? count(Broadcast::eligibleRecipients($tenantId)) : 0;
$history = Broadcast::forTenant($tenantId);

$pageTitle = Lang::t('bc_title');
$activeSide = 'broadcast';
require __DIR__ . '/includes/dash_header.php';
?>

<p style="color:var(--text-muted);margin-bottom:20px"><?php e('bc_intro'); ?></p>

<?php if ($notice !== null): ?>
<div class="alert alert-success"><i class="fa-solid fa-circle-check" style="margin-top:3px"></i> <?= htmlspecialchars($notice) ?></div>
<?php endif; ?>

<?php if (!$gated): ?>
<div class="alert alert-danger">
  <i class="fa-solid fa-lock" style="margin-top:3px"></i>
  <?php e('bc_locked'); ?> <a href="subscription.php" style="margin-left:6px;font-weight:600"><?php e('buy_now'); ?></a>
</div>
<?php else: ?>

<div class="card" style="margin-bottom:24px">
  <div style="margin-bottom:12px;color:var(--text-muted);font-size:.9rem">
    <i class="fa-solid fa-users"></i> <?= htmlspecialchars(Lang::t('bc_audience', ['count' => $eligible])) ?>
  </div>
  <?php if ($eligible === 0): ?>
    <p style="color:var(--text-muted)"><?php e('bc_empty'); ?></p>
  <?php else: ?>
  <form method="post" action="broadcast.php">
    <?= Csrf::field() ?>
    <div class="form-group">
      <label><?php e('bc_message'); ?></label>
      <textarea class="form-control" name="message" rows="4" maxlength="4000" placeholder="<?= htmlspecialchars(Lang::t('bc_message_ph')) ?>" required></textarea>
    </div>
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane"></i> <?php e('bc_send'); ?></button>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card" style="padding:0;overflow-x:auto">
  <h3 style="padding:18px 18px 0;margin:0"><?php e('bc_history'); ?></h3>
  <table style="width:100%;border-collapse:collapse;min-width:560px;margin-top:12px">
    <thead>
      <tr style="text-align:left;border-bottom:1px solid var(--border)">
        <th style="padding:12px 18px;font-size:.8rem;color:var(--text-muted)"><?php e('bc_message'); ?></th>
        <th style="padding:12px 18px;font-size:.8rem;color:var(--text-muted)"><?php e('bc_status'); ?></th>
        <th style="padding:12px 18px;font-size:.8rem;color:var(--text-muted)"><?php e('bc_sent'); ?></th>
        <th style="padding:12px 18px;font-size:.8rem;color:var(--text-muted)"><?php e('bc_when'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($history)): ?>
      <tr><td colspan="4" style="padding:30px;text-align:center;color:var(--text-muted)"><?php e('bc_none'); ?></td></tr>
      <?php else: foreach ($history as $b):
        $chip = match ($b['status']) { 'completed' => 'active', 'cancelled' => 'danger', default => 'locked' }; ?>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:12px 18px;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars(mb_substr($b['message'], 0, 60)) ?></td>
        <td style="padding:12px 18px"><span class="status-chip <?= $chip ?>" style="position:static"><?= htmlspecialchars($b['status']) ?></span></td>
        <td style="padding:12px 18px"><?= (int) $b['sent'] ?> / <?= (int) $b['total'] ?></td>
        <td style="padding:12px 18px;color:var(--text-muted);font-size:.85rem"><?= date('M j, H:i', strtotime($b['created_at'])) ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
