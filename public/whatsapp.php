<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

$notice = null;
$noticeType = 'success';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_own') {
        $token = trim($_POST['token'] ?? '');
        $pnid = trim($_POST['phone_number_id'] ?? '');
        $waba = trim($_POST['waba_id'] ?? '');
        $display = trim($_POST['display_number'] ?? '');
        $botType = in_array($_POST['bot_type'] ?? '', ['order', 'support', 'both'], true) ? $_POST['bot_type'] : 'order';

        if ($pnid === '') {
            $notice = Lang::t('err_required');
            $noticeType = 'danger';
        } elseif (TenantWhatsApp::phoneNumberIdTakenByOther($pnid, $tenantId)) {
            $notice = Lang::t('wa_pnid_taken');
            $noticeType = 'danger';
        } else {
            // saveByPnid keys on phone_number_id, so a tenant can add a SECOND
            // number (e.g. one for Order Bot, one for Support Bot).
            TenantWhatsApp::saveByPnid($tenantId, 'own', $token !== '' ? $token : null, $pnid, $waba ?: null, $display ?: null, $botType);
            $notice = Lang::t('wa_saved');
        }
    }

    if ($action === 'delete_number') {
        TenantWhatsApp::deleteForTenant((int) ($_POST['id'] ?? 0), $tenantId);
        $notice = Lang::t('wa_saved');
    }

    if ($action === 'rent') {
        $numberId = (int) ($_POST['number_id'] ?? 0);
        if (!Subscription::isServiceActive($tenantId, 'number_rental')) {
            $notice = Lang::t('wa_rent_need_sub');
            $noticeType = 'danger';
        } else {
            $number = PlatformNumber::find($numberId);
            if ($number !== null && $number['status'] === 'available') {
                // Bind the rented number as this tenant's WhatsApp connection.
                $plainToken = PlatformNumber::token($number);
                TenantWhatsApp::save($tenantId, 'rented', $plainToken, $number['phone_number_id'], $number['waba_id'], $number['display_number'], 'order');
                PlatformNumber::setStatus($numberId, 'rented');
                $sub = Subscription::activeForService($tenantId, 'number_rental');
                NumberRental::create($tenantId, $numberId, $sub['id'] ?? null, $sub['ends_at'] ?? null);
                $notice = Lang::t('wa_rented_ok');
            }
        }
    }
}

$numbers = TenantWhatsApp::allForTenant($tenantId);
$whatsapp = $numbers[0] ?? null;
$availableNumbers = PlatformNumber::available();
$botLabel = ['order' => 'Order Bot', 'support' => 'Support Bot', 'both' => 'Order + Support'];
$baseUrl = rtrim($config['app']['url'] ?? '', '/');
$webhookUrl = $baseUrl . '/webhooks/whatsapp.php';
$verifyToken = $config['meta']['verify_token'] ?? '';

$pageTitle = Lang::t('wa_title');
$activeSide = 'whatsapp';
require __DIR__ . '/includes/dash_header.php';
?>

<?php if ($notice !== null): ?>
<div class="alert alert-<?= $noticeType ?>">
  <i class="fa-solid <?= $noticeType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="margin-top:3px"></i>
  <?= htmlspecialchars($notice) ?>
</div>
<?php endif; ?>

<?php if ($numbers !== []): ?>
<div class="card" style="margin-bottom:22px">
  <h3 style="margin-top:0"><?php e('wa_connected'); ?> (<?= count($numbers) ?>)</h3>
  <?php foreach ($numbers as $n): ?>
  <div style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border)">
    <div class="card-icon" style="width:38px;height:38px"><i class="fa-brands fa-whatsapp"></i></div>
    <div style="flex:1;min-width:0">
      <strong><?= htmlspecialchars($n['display_number'] ?: $n['phone_number_id']) ?></strong>
      <div style="font-size:.82rem;color:var(--text-muted)">
        <?= htmlspecialchars($n['source']) ?> ·
        <span style="color:var(--primary);font-weight:600"><?= htmlspecialchars($botLabel[$n['bot_type']] ?? $n['bot_type']) ?></span>
      </div>
    </div>
    <span class="status-chip <?= $n['status'] === 'active' ? 'active' : 'danger' ?>" style="position:static"><?= htmlspecialchars($n['status']) ?></span>
    <form method="post" action="whatsapp.php" onsubmit="return confirm('Remove this number?')" style="margin:0">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="delete_number">
      <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
      <button class="btn btn-outline" type="submit" style="padding:6px 10px;color:#DC2626"><i class="fa-solid fa-trash"></i></button>
    </form>
  </div>
  <?php endforeach; ?>
  <div style="font-size:.82rem;color:var(--text-muted);margin-top:12px">
    💡 Tip: add a <strong>second number</strong> below and set it to <strong>Support Bot</strong> to run support on a different WhatsApp line.
  </div>
</div>
<?php endif; ?>

<!-- Two paths -->
<div class="grid grid-2" style="margin-bottom:22px">
  <div class="card" style="border-color:var(--primary)">
    <div class="card-icon"><i class="fa-solid fa-wrench"></i></div>
    <h3><?php e('wa_own'); ?></h3>
    <p><?php e('wa_own_desc'); ?></p>
    <div class="badge" style="margin-top:8px"><?php e('wa_own_free'); ?></div>
  </div>
  <div class="card">
    <div class="card-icon accent"><i class="fa-solid fa-phone"></i></div>
    <h3><?php e('wa_rent'); ?></h3>
    <p><?php e('wa_rent_desc'); ?></p>
    <div class="badge" style="margin-top:8px;background:rgba(245,158,11,.14);color:var(--accent)"><?php e('wa_rent_price'); ?></div>
  </div>
</div>

<!-- Add / connect a number form -->
<div class="card" style="margin-bottom:22px">
  <h3 style="margin-top:0"><?= $numbers === [] ? htmlspecialchars(Lang::t('wa_own')) : '➕ Add another number' ?></h3>
  <div class="alert" style="background:var(--primary-soft);color:var(--primary);font-size:.85rem;display:block">
    <div><strong><?php e('wa_webhook_info'); ?></strong> <code style="word-break:break-all"><?= htmlspecialchars($webhookUrl) ?></code></div>
    <div style="margin-top:6px"><strong><?php e('wa_verify_info'); ?></strong> <code><?= htmlspecialchars($verifyToken) ?></code></div>
    <div style="margin-top:6px">Both numbers use the same webhook URL — Meta routes each by its <em>phone number ID</em>, and this bot picks the right Order/Support handler per number.</div>
  </div>
  <form method="post" action="whatsapp.php">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="save_own">
    <div class="form-group">
      <label><?php e('wa_token'); ?></label>
      <input class="form-control" type="password" name="token" placeholder="EAAG...">
      <div class="form-hint"><?php e('wa_token_hint'); ?></div>
    </div>
    <div class="grid grid-2">
      <div class="form-group">
        <label><?php e('wa_pnid'); ?></label>
        <input class="form-control" type="text" name="phone_number_id" value="" required>
        <div class="form-hint"><?php e('wa_pnid_hint'); ?></div>
      </div>
      <div class="form-group">
        <label><?php e('wa_waba'); ?></label>
        <input class="form-control" type="text" name="waba_id" value="">
      </div>
    </div>
    <div class="grid grid-2">
      <div class="form-group">
        <label><?php e('wa_display'); ?></label>
        <input class="form-control" type="text" name="display_number" value="" placeholder="+255...">
      </div>
      <div class="form-group">
        <label><?php e('wa_bot_type'); ?></label>
        <select class="form-control" name="bot_type">
          <option value="order"><?php e('wa_bot_order'); ?></option>
          <option value="support"><?php e('wa_bot_support'); ?></option>
          <option value="both"><?php e('wa_bot_both'); ?></option>
        </select>
        <div class="form-hint">Choose <strong>Support Bot</strong> for a dedicated support line.</div>
      </div>
    </div>
    <button class="btn btn-primary" type="submit"><i class="fa-brands fa-whatsapp"></i> <?php e('wa_save'); ?></button>
  </form>
</div>

<!-- Rent a number -->
<div class="card">
  <h3 style="margin-top:0"><?php e('wa_rent'); ?></h3>
  <?php if (empty($availableNumbers)): ?>
    <p style="color:var(--text-muted)"><?php e('wa_rent_none'); ?></p>
  <?php else: foreach ($availableNumbers as $num): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border)">
      <i class="fa-solid fa-phone" style="color:var(--primary)"></i>
      <div style="flex:1">
        <strong><?= htmlspecialchars($num['display_number']) ?></strong>
        <span style="color:var(--text-muted);font-size:.85rem"> · <?= htmlspecialchars($num['country'] ?? '') ?> · <?= htmlspecialchars($num['currency']) ?> <?= number_format((float) $num['monthly_cost'], 2) ?>/mo</span>
      </div>
      <form method="post" action="whatsapp.php">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="rent">
        <input type="hidden" name="number_id" value="<?= (int) $num['id'] ?>">
        <button class="btn btn-accent" type="submit"><?php e('wa_rent_btn'); ?></button>
      </form>
    </div>
  <?php endforeach; endif; ?>
</div>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
