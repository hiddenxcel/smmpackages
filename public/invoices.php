<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

// Payment history joined to the service that was purchased.
$stmt = DB::conn()->prepare(
    "SELECT sp.transaction_ref, sp.amount, sp.currency, sp.gateway, sp.months,
            sp.status, sp.created_at, p.name AS plan_name, p.service_key
     FROM subscription_payments sp
     LEFT JOIN plans p ON p.id = sp.plan_id
     WHERE sp.tenant_id = ?
     ORDER BY sp.created_at DESC"
);
$stmt->execute([$tenantId]);
$rows = $stmt->fetchAll();

$statusChip = [
    'success' => ['success', 'fa-circle-check'],
    'pending' => ['locked', 'fa-clock'],
    'failed'  => ['danger', 'fa-circle-xmark'],
];

$pageTitle = Lang::t('invoices_title');
$activeSide = 'invoices';
require __DIR__ . '/includes/dash_header.php';
?>

<div class="card" style="padding:0;overflow-x:auto">
  <table style="width:100%;border-collapse:collapse;min-width:640px">
    <thead>
      <tr style="text-align:left;border-bottom:1px solid var(--border)">
        <th style="padding:14px 18px;font-size:.8rem;color:var(--text-muted)"><?php e('inv_ref'); ?></th>
        <th style="padding:14px 18px;font-size:.8rem;color:var(--text-muted)"><?php e('inv_service'); ?></th>
        <th style="padding:14px 18px;font-size:.8rem;color:var(--text-muted)"><?php e('inv_amount'); ?></th>
        <th style="padding:14px 18px;font-size:.8rem;color:var(--text-muted)"><?php e('inv_gateway'); ?></th>
        <th style="padding:14px 18px;font-size:.8rem;color:var(--text-muted)"><?php e('inv_status'); ?></th>
        <th style="padding:14px 18px;font-size:.8rem;color:var(--text-muted)"><?php e('inv_date'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
      <tr><td colspan="6" style="padding:34px;text-align:center;color:var(--text-muted)"><?php e('inv_none'); ?></td></tr>
      <?php else: foreach ($rows as $r): [$chipClass, $chipIcon] = $statusChip[$r['status']] ?? ['locked', 'fa-circle']; ?>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:14px 18px;font-family:monospace;font-size:.82rem"><?= htmlspecialchars($r['transaction_ref']) ?></td>
        <td style="padding:14px 18px"><?= htmlspecialchars($r['plan_name'] ?? $r['service_key'] ?? '—') ?> <span style="color:var(--text-muted);font-size:.82rem">×<?= (int) $r['months'] ?>m</span></td>
        <td style="padding:14px 18px;font-weight:600"><?= htmlspecialchars($r['currency']) ?> <?= number_format((float) $r['amount'], 2) ?></td>
        <td style="padding:14px 18px"><?= htmlspecialchars($r['gateway']) ?></td>
        <td style="padding:14px 18px"><span class="status-chip <?= $chipClass ?>" style="position:static"><i class="fa-solid <?= $chipIcon ?>"></i> <?= htmlspecialchars($r['status']) ?></span></td>
        <td style="padding:14px 18px;color:var(--text-muted);font-size:.85rem"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
