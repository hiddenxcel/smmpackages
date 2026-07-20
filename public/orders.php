<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

require_once __DIR__ . '/../app/helpers/ServiceGate.php';
$gateSvcKey = 'order_bot';
$gateState = ServiceGate::state($tenantId, $gateSvcKey);
$gateBlock = $gateState === 'sandbox';   // not paid → hide operational content

$orders = BotOrder::forTenant($tenantId, 200);
$counts = BotOrder::statusCounts($tenantId);
$total = array_sum($counts);

require_once __DIR__ . '/../app/models/BotSettings.php';
$currency = BotSettings::get($tenantId, 'order')['shop']['currency'] ?? 'USD';

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
?>

<!-- Summary counts -->
<div class="grid grid-4" style="margin-bottom:24px">
  <div class="card stat-tile"><div class="num"><?= number_format($total) ?></div><div class="lbl"><?php e('ord_total'); ?></div></div>
  <div class="card stat-tile"><div class="num"><?= number_format($counts['processing'] ?? 0) ?></div><div class="lbl">Processing</div></div>
  <div class="card stat-tile"><div class="num"><?= number_format($counts['completed'] ?? 0) ?></div><div class="lbl">Completed</div></div>
  <div class="card stat-tile"><div class="num"><?= number_format($counts['pending'] ?? 0) ?></div><div class="lbl">Pending</div></div>
</div>

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

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
