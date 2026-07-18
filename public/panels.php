<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

$notice = null;
$noticeType = 'success';

// Panel cap: max_panels comes from the order_bot plan (the service that uses panels).
$orderPlan = Plan::forService('order_bot');
$maxPanels = (int) ($orderPlan['max_panels'] ?? 1);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $pid = (int) ($_POST['panel_id'] ?? 0);
        if (TenantPanel::delete($pid, $tenantId)) {
            $notice = Lang::t('panels_deleted');
        }
    }

    if ($action === 'detect') {
        if (TenantPanel::countForTenant($tenantId) >= $maxPanels) {
            $notice = Lang::t('panels_limit', ['max' => $maxPanels]);
            $noticeType = 'danger';
        } else {
            $url = trim($_POST['api_url'] ?? '');
            $key = trim($_POST['api_key'] ?? '');
            $type = $_POST['panel_type'] ?? null;
            $name = trim($_POST['name'] ?? '');

            $result = PanelDetector::detect($url, $key, $type);

            if (!empty($result['success'])) {
                $cfg = $result['config'];
                $panelName = $name !== '' ? $name : (parse_url($cfg['api_url'], PHP_URL_HOST) ?: 'Panel');
                TenantPanel::create(
                    $tenantId,
                    $panelName,
                    $cfg['panel_type'],
                    $cfg['api_url'],
                    $key,
                    $cfg['api_version'],
                    $cfg['auth_method'],
                    $cfg['balance'] ?? null,
                    $cfg['currency'] ?? null,
                    $cfg['services_count'] ?? null
                );
                $bal = ($cfg['currency'] ? $cfg['currency'] . ' ' : '') . number_format((float) ($cfg['balance'] ?? 0), 2);
                $notice = Lang::t('panels_detected', [
                    'name' => $panelName,
                    'count' => $cfg['services_count'] ?? '?',
                    'bal' => $bal,
                ]);
            } else {
                $notice = Lang::t('panels_detect_fail', ['msg' => $result['message'] ?? '']);
                $noticeType = 'danger';
            }
        }
    }
}

$panels = TenantPanel::forTenant($tenantId);

$pageTitle = Lang::t('panels_page_title');
$activeSide = 'panels';
require __DIR__ . '/includes/dash_header.php';
?>

<?php if ($notice !== null): ?>
<div class="alert alert-<?= $noticeType ?>">
  <i class="fa-solid <?= $noticeType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="margin-top:3px"></i>
  <?= htmlspecialchars($notice) ?>
</div>
<?php endif; ?>

<div class="grid grid-2">
  <!-- Auto-detect wizard -->
  <div class="card">
    <h3 style="margin-top:0"><?php e('panels_add'); ?></h3>
    <div class="alert" style="background:var(--primary-soft);color:var(--primary);font-size:.83rem;display:block;line-height:1.55">
      <?php e('panels_key_help'); ?>
    </div>
    <form method="post" action="panels.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="detect">
      <div class="form-group">
        <label><?php e('panels_type'); ?></label>
        <select class="form-control" name="panel_type">
          <option value="perfectpanel"><?php e('panels_type_pp'); ?></option>
          <option value="rentalpanel"><?php e('panels_type_rp'); ?></option>
          <option value="custom" selected><?php e('panels_type_custom'); ?></option>
        </select>
      </div>
      <div class="form-group">
        <label><?php e('panels_name'); ?></label>
        <input class="form-control" type="text" name="name" placeholder="My Panel">
      </div>
      <div class="form-group">
        <label><?php e('panels_url'); ?></label>
        <input class="form-control" type="text" name="api_url" required placeholder="https://yourpanel.com">
        <div class="form-hint"><?php e('panels_url_hint'); ?></div>
      </div>
      <div class="form-group">
        <label><?php e('panels_key'); ?></label>
        <input class="form-control" type="password" name="api_key" required>
        <div class="form-hint"><?php e('panels_key_hint'); ?></div>
      </div>
      <button class="btn btn-primary btn-block" type="submit"><i class="fa-solid fa-magnifying-glass"></i> <?php e('panels_detect'); ?></button>
    </form>
  </div>

  <!-- Connected panels -->
  <div>
    <?php if (empty($panels)): ?>
      <div class="card" style="text-align:center;color:var(--text-muted)"><?php e('panels_none'); ?></div>
    <?php else: foreach ($panels as $p): ?>
      <div class="card" style="margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
          <div class="card-icon"><i class="fa-solid fa-plug"></i></div>
          <div style="flex:1">
            <h3 style="margin:0"><?= htmlspecialchars($p['name']) ?></h3>
            <div style="font-size:.82rem;color:var(--text-muted)"><?= htmlspecialchars($p['panel_type']) ?> · <?= htmlspecialchars($p['api_version']) ?> · <?= htmlspecialchars($p['auth_method']) ?></div>
          </div>
          <span class="status-chip <?= $p['status'] === 'active' ? 'active' : 'danger' ?>" style="position:static"><?= htmlspecialchars($p['status']) ?></span>
        </div>
        <div style="display:flex;gap:24px;font-size:.9rem;color:var(--text-muted);margin-bottom:12px">
          <span><?php e('panels_balance'); ?>: <strong style="color:var(--text)"><?= htmlspecialchars(($p['balance_currency'] ? $p['balance_currency'] . ' ' : '') . number_format((float) ($p['last_balance'] ?? 0), 2)) ?></strong></span>
          <span><?php e('panels_services'); ?>: <strong style="color:var(--text)"><?= (int) $p['services_count'] ?></strong></span>
        </div>
        <form method="post" action="panels.php" onsubmit="return confirm('<?= htmlspecialchars(Lang::t('confirm_delete'), ENT_QUOTES) ?>')">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="panel_id" value="<?= (int) $p['id'] ?>">
          <button class="btn btn-outline" type="submit" style="color:var(--danger);border-color:var(--danger)"><i class="fa-solid fa-trash"></i> <?php e('delete'); ?></button>
        </form>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
