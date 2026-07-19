<?php
/**
 * Sandbox notice — a slim "you're setting up in a free sandbox" strip shown on
 * setup pages (panels, services, bot config) when the tenant hasn't gone live.
 * Include after dash_header. Shows only if the tenant has ANY sandbox service.
 * Expects $tenantId in scope.
 */
if (!isset($tenantId)) {
    return;
}
$sbCount = count(array_filter(Subscription::stateMap((int) $tenantId), fn ($s) => $s === 'sandbox'));
if ($sbCount === 0) {
    return;
}
?>
<div class="sandbox-banner" style="margin-bottom:20px">
  <div class="sb-ico"><i class="fa-solid fa-flask"></i></div>
  <div class="sb-text">
    <strong><?php e('sandbox_setup_title'); ?></strong>
    <span><?php e('sandbox_setup_sub'); ?></span>
  </div>
  <a href="subscription.php?golive=1" class="btn btn-primary sb-cta"><i class="fa-solid fa-rocket"></i> <?php e('sandbox_go_live'); ?></a>
</div>
