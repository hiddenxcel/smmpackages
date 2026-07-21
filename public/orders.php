<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

require_once __DIR__ . '/../app/helpers/ServiceGate.php';
$gateSvcKey = 'order_bot';
$gateState = ServiceGate::state($tenantId, $gateSvcKey);
$gateBlock = $gateState === 'sandbox';   // not paid → hide operational content

// --- Kanban drag-drop endpoint: move an order to a new column (JSON) ---
// Columns map to a canonical status; a card dropped in a column gets that status.
$KANBAN_MOVE = [
    'pending'    => 'pending',
    'processing' => 'processing',
    'completed'  => 'completed',
    'canceled'   => 'canceled',
];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'move') {
    header('Content-Type: application/json');
    if (!Csrf::check($_POST[Csrf::FIELD] ?? null)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'msg' => 'bad token']);
        return;
    }
    if ($gateBlock) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'msg' => 'locked']);
        return;
    }
    $id = (int) ($_POST['id'] ?? 0);
    $col = (string) ($_POST['col'] ?? '');
    if ($id < 1 || !isset($KANBAN_MOVE[$col])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'bad input']);
        return;
    }
    $ok = BotOrder::moveStatus($tenantId, $id, $KANBAN_MOVE[$col]);
    echo json_encode(['ok' => $ok, 'status' => $KANBAN_MOVE[$col]]);
    return;
}

$orders = BotOrder::forTenant($tenantId, 300);
$counts = BotOrder::statusCounts($tenantId);
$total = array_sum($counts);

require_once __DIR__ . '/../app/models/BotSettings.php';
$currency = BotSettings::get($tenantId, 'order')['shop']['currency'] ?? 'USD';

$view = ($_GET['view'] ?? 'board') === 'table' ? 'table' : 'board';

$pageTitle = Lang::t('orders_title');
$activeSide = 'orders';
require __DIR__ . '/includes/dash_header.php';
require __DIR__ . '/includes/gate_banner.php';
if ($gateBlock) { require __DIR__ . '/includes/dash_footer.php'; return; }

$payChip = static function (?string $s): string {
    return match ($s) {
        'paid' => 'active',
        'failed' => 'danger',
        default => 'locked',
    };
};

$statusChip = function (?string $s): string {
    $s = strtolower((string) $s);
    return match (true) {
        str_contains($s, 'complet') => 'active',
        str_contains($s, 'cancel'), str_contains($s, 'error') => 'danger',
        default => 'locked',
    };
};

// Bucket every order into one of the four Kanban columns.
$colOf = static function (?string $s): string {
    $s = strtolower((string) $s);
    return match (true) {
        str_contains($s, 'complet') => 'completed',
        str_contains($s, 'cancel'), str_contains($s, 'error'),
            str_contains($s, 'partial'), str_contains($s, 'refund') => 'canceled',
        str_contains($s, 'process') => 'processing',
        default => 'pending',
    };
};

$cols = [
    'pending'    => ['label' => Lang::t('kb_pending'),    'buckets' => []],
    'processing' => ['label' => Lang::t('kb_processing'), 'buckets' => []],
    'completed'  => ['label' => Lang::t('kb_completed'),  'buckets' => []],
    'canceled'   => ['label' => Lang::t('kb_issues'),     'buckets' => []],
];
foreach ($orders as $o) {
    $cols[$colOf($o['status'])]['buckets'][] = $o;
}
?>

<!-- Header: title + view toggle -->
<div class="orders-bar">
  <div class="orders-counts">
    <span class="oc"><b><?= number_format($total) ?></b> <?php e('ord_total'); ?></span>
    <span class="oc dot-p"><b><?= number_format(count($cols['pending']['buckets'])) ?></b> <?php e('kb_pending'); ?></span>
    <span class="oc dot-w"><b><?= number_format(count($cols['processing']['buckets'])) ?></b> <?php e('kb_processing'); ?></span>
    <span class="oc dot-g"><b><?= number_format(count($cols['completed']['buckets'])) ?></b> <?php e('kb_completed'); ?></span>
  </div>
  <div class="view-toggle">
    <a href="?view=board" class="vt<?= $view === 'board' ? ' on' : '' ?>"><i class="fa-solid fa-table-columns"></i> <?php e('kb_board'); ?></a>
    <a href="?view=table" class="vt<?= $view === 'table' ? ' on' : '' ?>"><i class="fa-solid fa-list"></i> <?php e('kb_table'); ?></a>
  </div>
</div>

<?php if ($view === 'board'): ?>
<!-- ===== KANBAN BOARD ===== -->
<div class="kanban" id="kanban" data-csrf="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
  <?php foreach ($cols as $key => $col): ?>
  <div class="kb-col" data-col="<?= $key ?>">
    <div class="kb-col-head kb-<?= $key ?>">
      <span class="kb-col-title"><?= htmlspecialchars($col['label']) ?></span>
      <span class="kb-col-count"><?= count($col['buckets']) ?></span>
    </div>
    <div class="kb-list" data-col="<?= $key ?>">
      <?php if (empty($col['buckets'])): ?>
        <div class="kb-empty"><?php e('kb_empty'); ?></div>
      <?php else: foreach ($col['buckets'] as $o): ?>
      <div class="kb-card" draggable="true" data-id="<?= (int) $o['id'] ?>">
        <div class="kb-card-top">
          <span class="kb-oid">#<?= htmlspecialchars($o['provider_order_id'] ?? (string) $o['id']) ?></span>
          <?php if ($o['amount'] !== null): ?>
          <span class="kb-amt"><?= htmlspecialchars($currency) ?> <?= number_format((float) $o['amount'], 2) ?></span>
          <?php endif; ?>
        </div>
        <div class="kb-svc"><?= htmlspecialchars($o['service_name'] ?? '—') ?></div>
        <div class="kb-meta">
          <span><i class="fa-solid fa-hashtag"></i> <?= number_format((int) $o['quantity']) ?></span>
          <span><i class="fa-solid fa-user"></i> <?= htmlspecialchars($o['customer_phone']) ?></span>
        </div>
        <div class="kb-card-foot">
          <span class="status-chip <?= $payChip($o['payment_status'] ?? null) ?>" style="position:static"><?= htmlspecialchars($o['payment_status'] ?? '—') ?></span>
          <span class="kb-date"><?= date('M j, H:i', strtotime($o['created_at'])) ?></span>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<div class="kb-toast" id="kbToast"></div>

<?php else: ?>
<!-- ===== TABLE VIEW (unchanged) ===== -->
<div class="card" style="padding:0;overflow-x:auto">
  <table style="width:100%;border-collapse:collapse;min-width:720px">
    <thead>
      <tr style="text-align:left;border-bottom:1px solid var(--border)">
        <?php foreach (['ord_id','ord_customer','ord_service','ord_qty'] as $h): ?>
        <th style="padding:14px 18px;font-size:.8rem;color:var(--text-muted)"><?php e($h); ?></th>
        <?php endforeach; ?>
        <th style="padding:14px 18px;font-size:.8rem;color:var(--text-muted)">Amount</th>
        <th style="padding:14px 18px;font-size:.8rem;color:var(--text-muted)">Payment</th>
        <th style="padding:14px 18px;font-size:.8rem;color:var(--text-muted)"><?php e('ord_status'); ?></th>
        <th style="padding:14px 18px;font-size:.8rem;color:var(--text-muted)"><?php e('ord_date'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($orders)): ?>
      <tr><td colspan="8" style="padding:34px;text-align:center;color:var(--text-muted)"><?php e('ord_none'); ?></td></tr>
      <?php else: foreach ($orders as $o): ?>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:14px 18px;font-family:monospace;font-size:.82rem">#<?= htmlspecialchars($o['provider_order_id'] ?? (string) $o['id']) ?></td>
        <td style="padding:14px 18px"><?= htmlspecialchars($o['customer_phone']) ?></td>
        <td style="padding:14px 18px"><?= htmlspecialchars($o['service_name'] ?? '—') ?></td>
        <td style="padding:14px 18px"><?= number_format((int) $o['quantity']) ?></td>
        <td style="padding:14px 18px"><?= $o['amount'] !== null ? htmlspecialchars($currency) . ' ' . number_format((float) $o['amount'], 2) : '—' ?></td>
        <td style="padding:14px 18px">
          <span class="status-chip <?= $payChip($o['payment_status'] ?? null) ?>" style="position:static"><?= htmlspecialchars($o['payment_status'] ?? '—') ?></span>
          <?php if (!empty($o['paid_from'])): ?><div style="color:var(--text-muted);font-size:.72rem;margin-top:2px"><?= htmlspecialchars($o['paid_from']) ?></div><?php endif; ?>
        </td>
        <td style="padding:14px 18px"><span class="status-chip <?= $statusChip($o['status']) ?>" style="position:static"><?= htmlspecialchars($o['status'] ?? '—') ?></span></td>
        <td style="padding:14px 18px;color:var(--text-muted);font-size:.85rem"><?= date('M j, H:i', strtotime($o['created_at'])) ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php if ($view === 'board'): ?>
<script>
(function () {
  var board = document.getElementById('kanban');
  if (!board) return;
  var csrf = board.dataset.csrf;
  var toast = document.getElementById('kbToast');
  var dragEl = null;

  function showToast(msg, ok) {
    toast.textContent = msg;
    toast.className = 'kb-toast show' + (ok ? '' : ' err');
    setTimeout(function () { toast.className = 'kb-toast'; }, 2200);
  }

  board.querySelectorAll('.kb-card').forEach(function (card) {
    card.addEventListener('dragstart', function (e) {
      dragEl = card; card.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
    });
    card.addEventListener('dragend', function () {
      card.classList.remove('dragging'); dragEl = null;
      board.querySelectorAll('.kb-list.over').forEach(function (l) { l.classList.remove('over'); });
    });
  });

  board.querySelectorAll('.kb-list').forEach(function (list) {
    list.addEventListener('dragover', function (e) { e.preventDefault(); list.classList.add('over'); });
    list.addEventListener('dragleave', function () { list.classList.remove('over'); });
    list.addEventListener('drop', function (e) {
      e.preventDefault(); list.classList.remove('over');
      if (!dragEl) return;
      var toCol = list.dataset.col;
      var fromList = dragEl.parentElement;
      if (fromList === list) return;

      var empty = list.querySelector('.kb-empty');
      if (empty) empty.remove();
      list.appendChild(dragEl);
      recount();

      var id = dragEl.dataset.id;
      var body = new URLSearchParams();
      body.set('action', 'move'); body.set('id', id); body.set('col', toCol);
      body.set('<?= Csrf::FIELD ?>', csrf);

      fetch('orders.php', { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: body })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d && d.ok) { showToast('<?php e('kb_moved'); ?>', true); }
          else { showToast('<?php e('kb_move_fail'); ?>', false); }
        })
        .catch(function () { showToast('<?php e('kb_move_fail'); ?>', false); });
    });
  });

  function recount() {
    board.querySelectorAll('.kb-col').forEach(function (col) {
      var n = col.querySelectorAll('.kb-card').length;
      col.querySelector('.kb-col-count').textContent = n;
      var listEl = col.querySelector('.kb-list');
      if (n === 0 && !listEl.querySelector('.kb-empty')) {
        var e = document.createElement('div'); e.className = 'kb-empty';
        e.textContent = '<?php e('kb_empty'); ?>'; listEl.appendChild(e);
      }
    });
  }
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
