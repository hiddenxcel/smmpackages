<?php
/**
 * Gate banner for operational pages. Expects $gateState ('active'|'sandbox'|'locked')
 * and $gateSvcKey (e.g. 'order_bot'). Renders nothing when active.
 *
 * The including page then checks $gateBlock: when true (sandbox — not paid), it
 * should render ONLY this banner and stop (Go Live to unlock). When locked
 * (expired), the page shows its content read-only and hides write controls.
 */
if (!isset($gateState) || $gateState === 'active') {
    return;
}
$gateSvcKey = $gateSvcKey ?? '';
?>
<?php if ($gateState === 'sandbox'): ?>
<div class="gate-card">
  <div class="gate-ico live"><i class="fa-solid fa-rocket"></i></div>
  <div class="gate-body">
    <strong><?php e('gate_live_title'); ?></strong>
    <span><?php e('gate_live_sub'); ?></span>
  </div>
  <a href="subscription.php?golive=1<?= $gateSvcKey ? '&svc=' . htmlspecialchars($gateSvcKey) : '' ?>" class="btn btn-primary"><i class="fa-solid fa-rocket"></i> <?php e('sandbox_go_live'); ?></a>
</div>
<?php else: /* locked / expired */ ?>
<div class="gate-card locked">
  <div class="gate-ico lock"><i class="fa-solid fa-lock"></i></div>
  <div class="gate-body">
    <strong><?php e('gate_renew_title'); ?></strong>
    <span><?php e('gate_renew_sub'); ?></span>
  </div>
  <a href="subscription.php" class="btn btn-primary"><i class="fa-solid fa-arrows-rotate"></i> <?php e('renew_extend'); ?></a>
</div>
<?php endif; ?>
