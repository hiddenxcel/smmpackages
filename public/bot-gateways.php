<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/../app/models/TenantPaymentGateway.php';
require_once __DIR__ . '/../app/models/BotSettings.php';
require_once __DIR__ . '/../app/services/payments/GatewayRegistry.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

require_once __DIR__ . '/../app/helpers/ServiceGate.php';
$gateSvcKey = 'order_bot';
$gateState = ServiceGate::state($tenantId, $gateSvcKey);
$canWrite = $gateState === 'active';
$gateBlock = $gateState === 'sandbox';

$notice = null;
$noticeType = 'success';

// All gateways come from the registry (single source of truth).
$SUPPORTED = GatewayRegistry::all();

// Only an active (paid) Order Bot may change payment settings; expired = read-only.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $canWrite) {
    Csrf::verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_shop') {
        $settings = BotSettings::get($tenantId, 'order');
        $settings['shop']['currency'] = strtoupper(trim($_POST['currency'] ?? 'USD')) ?: 'USD';
        $settings['shop']['min_topup'] = max(0, (float) ($_POST['min_topup'] ?? 1));
        $settings['shop']['referral_percent'] = max(0, min(100, (float) ($_POST['referral_percent'] ?? 0)));
        BotSettings::save($tenantId, 'order', $settings);
        $notice = 'Shop settings saved.';
    }

    if ($action === 'save_gateway') {
        $gateway = $_POST['gateway'] ?? '';
        if (!isset($SUPPORTED[$gateway])) {
            $notice = 'Unknown gateway.';
            $noticeType = 'danger';
        } else {
            $apiKey = trim($_POST['api_key'] ?? '');
            $secret = trim($_POST['webhook_secret'] ?? '');
            $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
            // Blank keys keep the existing encrypted values (edit status without re-typing).
            TenantPaymentGateway::save($tenantId, $gateway, $apiKey !== '' ? $apiKey : null, $secret !== '' ? $secret : null, $status);
            $notice = ucfirst($gateway) . ' gateway saved.';
        }
    }

    if ($action === 'save_binance_id') {
        $settings = BotSettings::get($tenantId, 'order');
        $settings['shop']['binance_pay_id'] = trim($_POST['binance_pay_id'] ?? '');
        BotSettings::save($tenantId, 'order', $settings);
        $notice = 'Binance ID saved.';
    }

    if ($action === 'delete_gateway') {
        TenantPaymentGateway::delete($tenantId, (string) ($_POST['gateway'] ?? ''));
        $notice = 'Gateway removed.';
    }
}

$shop = BotSettings::get($tenantId, 'order')['shop'];
$webhookUrl = rtrim($config['app']['url'] ?? '', '/') . '/webhooks/bot-payment.php';
$configured = [];
foreach (TenantPaymentGateway::allForTenant($tenantId) as $g) {
    $configured[$g['gateway']] = $g;
}

$pageTitle = 'Payment Gateways';
$activeSide = 'bot-gateways';
require __DIR__ . '/includes/dash_header.php';
require __DIR__ . '/includes/gate_banner.php';
if ($gateBlock) { require __DIR__ . '/includes/dash_footer.php'; return; }
?>

<div style="margin-bottom:22px">
  <h1 class="dash-title" style="margin-bottom:4px">Payment Gateways</h1>
  <div style="color:var(--text-muted)">Set the currency your customers pay in, and connect the gateway they top up their wallet with. Keys are encrypted.</div>
</div>

<?php if ($notice !== null): ?>
<div class="alert alert-<?= $noticeType ?>">
  <i class="fa-solid <?= $noticeType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="margin-top:3px"></i>
  <?= htmlspecialchars($notice) ?>
</div>
<?php endif; ?>

<!-- Shop settings: currency, min top-up, referral -->
<div class="wapi-card" style="margin-bottom:18px">
  <div class="wc-head">
    <div class="wc-ico green"><i class="fa-solid fa-coins"></i></div>
    <div class="wc-title" style="font-size:1rem">Shop settings</div>
  </div>
  <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;align-items:end">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="save_shop">
    <div>
      <label style="font-size:.8rem;color:var(--text-muted)">Currency (customer-facing)</label>
      <input type="text" name="currency" class="form-control" value="<?= htmlspecialchars($shop['currency']) ?>" maxlength="5" placeholder="USD">
    </div>
    <div>
      <label style="font-size:.8rem;color:var(--text-muted)">Minimum top-up</label>
      <input type="number" step="0.01" min="0" name="min_topup" class="form-control" value="<?= htmlspecialchars($shop['min_topup']) ?>">
    </div>
    <div>
      <label style="font-size:.8rem;color:var(--text-muted)">Referral bonus %</label>
      <input type="number" step="0.1" min="0" max="100" name="referral_percent" class="form-control" value="<?= htmlspecialchars($shop['referral_percent']) ?>">
    </div>
    <div><button class="btn btn-primary btn-block" type="submit">Save settings</button></div>
  </form>
</div>

<!-- Gateways -->
<?php
$typeIcon = ['mobile' => 'fa-mobile-screen-button', 'crypto' => 'fa-coins', 'card' => 'fa-credit-card'];

// Render live gateways first, then "coming soon" ones.
$order = array_merge(
    array_keys(array_filter($SUPPORTED, fn ($m) => !empty($m['ready']))),
    array_keys(array_filter($SUPPORTED, fn ($m) => empty($m['ready'])))
);

$sectionShown = ['ready' => false, 'soon' => false];
foreach ($order as $code):
    $meta = $SUPPORTED[$code];
    $ready = !empty($meta['ready']);
    $existing = $configured[$code] ?? null;
    $isSet = $existing !== null;

    // Section heading before the first live / first soon card.
    if ($ready && !$sectionShown['ready']) { $sectionShown['ready'] = true; ?>
      <div class="dash-section-label">Available now</div>
    <?php } elseif (!$ready && !$sectionShown['soon']) { $sectionShown['soon'] = true; ?>
      <div class="dash-section-label">Coming soon — save your keys, we'll switch it on</div>
    <?php } ?>

<div class="wapi-card" style="margin-bottom:16px<?= $ready ? '' : ';opacity:.9' ?>">
  <div class="wc-head" style="justify-content:space-between">
    <div style="display:flex;align-items:center;gap:10px">
      <div class="wc-ico <?= $meta['type'] === 'crypto' ? 'orange' : ($meta['type'] === 'card' ? 'purple' : 'blue') ?>"><i class="fa-solid <?= $typeIcon[$meta['type']] ?? 'fa-money-bill-wave' ?>"></i></div>
      <div class="wc-title" style="font-size:1rem"><?= htmlspecialchars($meta['label']) ?></div>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <?php if (!$ready): ?><span class="badge-verified badge-pending">Coming soon</span><?php endif; ?>
      <?php if ($isSet): ?>
        <span class="badge-verified <?= $existing['status'] === 'active' ? '' : 'badge-pending' ?>"><?= $existing['status'] === 'active' ? 'Active' : 'Inactive' ?></span>
      <?php endif; ?>
    </div>
  </div>

  <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;align-items:end">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="save_gateway">
    <input type="hidden" name="gateway" value="<?= htmlspecialchars($code) ?>">
    <?php foreach ($meta['fields'] as $field):
        $stored = $isSet && !empty($existing[$field['store']]); ?>
      <div>
        <label style="font-size:.8rem;color:var(--text-muted)"><?= htmlspecialchars($field['label']) ?> <?= $stored ? '<span style="color:var(--primary)">(saved)</span>' : '' ?></label>
        <input type="password" name="<?= htmlspecialchars($field['store']) ?>" class="form-control" placeholder="<?= $stored ? '••••••••' : 'Paste value' ?>" autocomplete="off">
      </div>
    <?php endforeach; ?>
    <div>
      <label style="font-size:.8rem;color:var(--text-muted)">Status</label>
      <select name="status" class="form-control">
        <option value="active" <?= (!$isSet || $existing['status'] === 'active') ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= ($isSet && $existing['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
      </select>
    </div>
    <div><button class="btn btn-primary btn-block" type="submit">Save</button></div>
  </form>

  <?php if (!empty($meta['verify'])): ?>
  <!-- Binance internal-transfer: needs a Binance ID to show customers; no webhook. -->
  <form method="post" style="margin-top:14px;display:grid;grid-template-columns:1fr auto;gap:10px;align-items:end">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="save_binance_id">
    <div>
      <label style="font-size:.8rem;color:var(--text-muted)">Your Binance ID (customers send USDT here)</label>
      <input type="text" name="binance_pay_id" class="form-control" value="<?= htmlspecialchars($shop['binance_pay_id'] ?? '') ?>" placeholder="e.g. 113976754">
    </div>
    <div><button class="btn btn-primary" type="submit">Save ID</button></div>
  </form>
  <div style="font-size:.78rem;color:var(--text-muted);margin-top:8px;line-height:1.6">
    <i class="fa-solid fa-circle-info" style="color:var(--primary)"></i>
    Use a Binance <strong>Spot API key</strong> with only <strong>Enable Reading</strong> — no KYB or webhook needed.
    Customers send USDT to your Binance ID and paste the Order ID; the bot verifies it automatically.
  </div>
  <?php endif; ?>

  <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;gap:12px;flex-wrap:wrap">
    <div style="font-size:.8rem;color:var(--text-muted)">
      <?php if (!empty($meta['verify'])): ?>
      No webhook needed — payments are verified in-chat.
      <?php else: ?>
      Webhook URL:
      <code style="user-select:all"><?= htmlspecialchars($webhookUrl) ?></code>
      <?php endif; ?>
    </div>
    <?php if ($isSet): ?>
    <form method="post" onsubmit="return confirm('Remove this gateway?')">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="delete_gateway">
      <input type="hidden" name="gateway" value="<?= htmlspecialchars($code) ?>">
      <button class="btn btn-outline" type="submit" style="padding:6px 12px;color:#DC2626">Remove</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
