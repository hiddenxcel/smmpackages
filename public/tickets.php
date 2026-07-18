<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

$active = Subscription::isServiceActive($tenantId, 'ai_tickets');
$viewId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$notice = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $active) {
    Csrf::verify();
    $action = $_POST['action'] ?? '';
    $tid = (int) ($_POST['ticket_id'] ?? 0);
    $ticket = Ticket::findForTenant($tid, $tenantId);

    if ($ticket !== null) {
        if ($action === 'reply') {
            $msg = trim($_POST['message'] ?? '');
            if ($msg !== '') {
                TicketMessage::add($tid, 'staff', $msg);
            }
        }
        if ($action === 'resolve') {
            Ticket::setStatus($tid, $tenantId, 'resolved');
        }
        if ($action === 'reopen') {
            Ticket::setStatus($tid, $tenantId, 'open');
        }
    }
    header('Location: tickets.php' . ($tid ? '?id=' . $tid : ''));
    exit;
}

$viewTicket = $viewId ? Ticket::findForTenant($viewId, $tenantId) : null;

$pageTitle = Lang::t('tk_title');
$activeSide = 'tickets';
require __DIR__ . '/includes/dash_header.php';

$senderMeta = [
    'customer' => ['tk_sender_customer', 'var(--bg-soft)', 'flex-start'],
    'ai'       => ['tk_sender_ai', 'var(--primary-soft)', 'flex-start'],
    'staff'    => ['tk_sender_staff', 'rgba(245,158,11,.12)', 'flex-end'],
];
?>

<?php if (!$active): ?>
<div class="alert alert-danger">
  <i class="fa-solid fa-lock" style="margin-top:3px"></i>
  <?php e('tk_locked'); ?> <a href="subscription.php" style="margin-left:6px;font-weight:600"><?php e('buy_now'); ?></a>
</div>
<?php endif; ?>

<?php if ($viewTicket !== null): ?>
  <!-- Ticket thread -->
  <a href="tickets.php" style="display:inline-block;margin-bottom:16px;font-size:.9rem"><i class="fa-solid fa-arrow-left"></i> <?php e('tk_back'); ?></a>
  <div class="card" style="margin-bottom:18px">
    <div style="display:flex;align-items:center;gap:12px">
      <div style="flex:1">
        <h3 style="margin:0"><?= htmlspecialchars($viewTicket['subject']) ?></h3>
        <div style="font-size:.85rem;color:var(--text-muted)"><?= htmlspecialchars($viewTicket['customer_identifier'] ?? 'Anonymous') ?></div>
      </div>
      <span class="status-chip <?= $viewTicket['status'] === 'resolved' ? 'active' : 'locked' ?>" style="position:static"><?= htmlspecialchars($viewTicket['status']) ?></span>
      <form method="post" action="tickets.php" style="display:inline">
        <?= Csrf::field() ?>
        <input type="hidden" name="ticket_id" value="<?= (int) $viewTicket['id'] ?>">
        <?php if ($viewTicket['status'] !== 'resolved'): ?>
          <input type="hidden" name="action" value="resolve">
          <button class="btn btn-outline" type="submit"><?php e('tk_mark_resolved'); ?></button>
        <?php else: ?>
          <input type="hidden" name="action" value="reopen">
          <button class="btn btn-outline" type="submit"><?php e('tk_reopen'); ?></button>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <div class="card" style="margin-bottom:18px">
    <?php foreach (TicketMessage::forTicket($viewTicket['id']) as $m):
      [$lblKey, $bg, $align] = $senderMeta[$m['sender']] ?? ['tk_sender_customer', 'var(--bg-soft)', 'flex-start']; ?>
      <div style="display:flex;justify-content:<?= $align ?>;margin-bottom:12px">
        <div style="max-width:80%;background:<?= $bg ?>;padding:11px 15px;border-radius:12px">
          <div style="font-size:.72rem;font-weight:700;color:var(--text-muted);margin-bottom:3px"><?php e($lblKey); ?></div>
          <div style="white-space:pre-wrap;font-size:.92rem"><?= nl2br(htmlspecialchars($m['message'])) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($active): ?>
  <form method="post" action="tickets.php" class="card" style="display:flex;gap:10px">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="reply">
    <input type="hidden" name="ticket_id" value="<?= (int) $viewTicket['id'] ?>">
    <input class="form-control" style="flex:1" type="text" name="message" placeholder="<?= htmlspecialchars(Lang::t('tk_reply_ph')) ?>" required>
    <button class="btn btn-primary" type="submit"><?php e('tk_send'); ?></button>
  </form>
  <?php endif; ?>

<?php else: ?>
  <!-- Ticket list -->
  <?php $tickets = Ticket::forTenant($tenantId); $counts = Ticket::statusCounts($tenantId); ?>
  <div class="grid grid-4" style="margin-bottom:22px">
    <div class="card stat-tile"><div class="num"><?= (int) ($counts['open'] ?? 0) ?></div><div class="lbl"><?php e('tk_open'); ?></div></div>
    <div class="card stat-tile"><div class="num"><?= (int) ($counts['pending'] ?? 0) ?></div><div class="lbl"><?php e('tk_pending'); ?></div></div>
    <div class="card stat-tile"><div class="num"><?= (int) ($counts['resolved'] ?? 0) ?></div><div class="lbl"><?php e('tk_resolved'); ?></div></div>
    <div class="card stat-tile"><div class="num"><?= (int) ($counts['closed'] ?? 0) ?></div><div class="lbl"><?php e('tk_closed'); ?></div></div>
  </div>

  <div class="card" style="padding:0;overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;min-width:640px">
      <thead>
        <tr style="text-align:left;border-bottom:1px solid var(--border)">
          <th style="padding:14px 18px;font-size:.8rem;color:var(--text-muted)"><?php e('tk_subject'); ?></th>
          <th style="padding:14px 18px;font-size:.8rem;color:var(--text-muted)"><?php e('tk_customer'); ?></th>
          <th style="padding:14px 18px;font-size:.8rem;color:var(--text-muted)"><?php e('tk_status'); ?></th>
          <th style="padding:14px 18px;font-size:.8rem;color:var(--text-muted)"><?php e('tk_updated'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($tickets)): ?>
        <tr><td colspan="4" style="padding:34px;text-align:center;color:var(--text-muted)"><?php e('tk_none'); ?></td></tr>
        <?php else: foreach ($tickets as $t): ?>
        <tr style="border-bottom:1px solid var(--border);cursor:pointer" onclick="location.href='tickets.php?id=<?= (int) $t['id'] ?>'">
          <td style="padding:14px 18px;font-weight:600"><?= htmlspecialchars($t['subject']) ?></td>
          <td style="padding:14px 18px;color:var(--text-muted)"><?= htmlspecialchars($t['customer_identifier'] ?? '—') ?></td>
          <td style="padding:14px 18px"><span class="status-chip <?= $t['status'] === 'resolved' ? 'active' : 'locked' ?>" style="position:static"><?= htmlspecialchars($t['status']) ?></span></td>
          <td style="padding:14px 18px;color:var(--text-muted);font-size:.85rem"><?= date('M j, H:i', strtotime($t['updated_at'])) ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
