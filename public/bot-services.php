<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/../app/models/BotService.php';
require_once __DIR__ . '/../app/models/TenantPanel.php';
require_once __DIR__ . '/../app/services/SmmProviderClient.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

// Common platforms + service categories offered as dropdowns (plus a free-text
// "Other…" escape so a tenant is never locked to this list).
$PLATFORM_OPTIONS = [
    'Instagram', 'TikTok', 'YouTube', 'Facebook', 'Twitter / X', 'Telegram',
    'Spotify', 'Snapchat', 'LinkedIn', 'Threads', 'Twitch', 'SoundCloud',
    'WhatsApp', 'Pinterest', 'Discord', 'Website Traffic',
];
$CATEGORY_OPTIONS = [
    'Followers', 'Likes', 'Views', 'Subscribers', 'Comments', 'Shares',
    'Members', 'Plays', 'Watch Time', 'Saves', 'Reach', 'Story Views',
    'Live Views', 'Reactions', 'Votes', 'Traffic',
];

/**
 * Split a panel's combined category (e.g. "Instagram Followers", "TikTok Views")
 * into [platform, subCategory]. If the first word is a known platform we use it
 * as the platform and the rest as the category; otherwise the whole string is
 * the platform with no sub-category.
 * @return array{0:string,1:string}
 */
function splitPanelCategory(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') { return ['Other', '']; }

    $known = ['instagram', 'tiktok', 'youtube', 'facebook', 'twitter', 'x', 'telegram', 'spotify', 'threads', 'linkedin', 'snapchat', 'twitch', 'soundcloud'];
    $parts = preg_split('/\s+/', $raw, 2);
    if (count($parts) === 2 && in_array(mb_strtolower($parts[0]), $known, true)) {
        return [$parts[0], $parts[1]];
    }

    return [$raw, ''];
}

$notice = null;
$noticeType = 'success';
$importList = null;   // when a panel fetch returns services to pick from
$importPanelId = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();
    $action = $_POST['action'] ?? '';

    // A dropdown value of "__other" means the tenant typed a custom value in the
    // paired *_other field; fall back to that.
    $pickOrOther = static function (string $sel, string $other): string {
        $v = trim($_POST[$sel] ?? '');
        return $v === '__other' ? trim($_POST[$other] ?? '') : $v;
    };

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $platform = $pickOrOther('platform', 'platform_other');
        $providerServiceId = trim($_POST['provider_service_id'] ?? '');
        $myPrice = (float) ($_POST['my_price'] ?? 0);

        if ($name === '' || $platform === '' || $providerServiceId === '' || $myPrice <= 0) {
            $notice = 'Please fill in the name, platform, provider service ID, and a price above 0.';
            $noticeType = 'danger';
        } else {
            BotService::create($tenantId, [
                'panel_id' => ($_POST['panel_id'] ?? '') !== '' ? (int) $_POST['panel_id'] : null,
                'provider_service_id' => $providerServiceId,
                'platform' => $platform,
                'category' => $pickOrOther('category', 'category_other'),
                'name' => $name,
                'unit_label' => trim($_POST['unit_label'] ?? '') ?: 'Followers',
                'cost_price' => ($_POST['cost_price'] ?? '') !== '' ? (float) $_POST['cost_price'] : null,
                'my_price' => $myPrice,
                'min_quantity' => max(1, (int) ($_POST['min_quantity'] ?? 1)),
                'max_quantity' => max(1, (int) ($_POST['max_quantity'] ?? 100000)),
            ]);
            $notice = 'Service added.';
        }
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        BotService::update($tenantId, $id, [
            'name' => trim($_POST['name'] ?? ''),
            'category' => $pickOrOther('category', 'category_other'),
            'my_price' => (float) ($_POST['my_price'] ?? 0),
            'min_quantity' => max(1, (int) ($_POST['min_quantity'] ?? 1)),
            'max_quantity' => max(1, (int) ($_POST['max_quantity'] ?? 100000)),
            'status' => ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        ]);
        $notice = 'Service updated.';
    }

    if ($action === 'delete') {
        BotService::delete($tenantId, (int) ($_POST['id'] ?? 0));
        $notice = 'Service deleted.';
    }

    // Fetch a panel's catalogue so the tenant can pick services to import.
    if ($action === 'fetch_panel') {
        $importPanelId = (int) ($_POST['panel_id'] ?? 0);
        $panel = TenantPanel::findForTenant($importPanelId, $tenantId);
        if ($panel === null) {
            $notice = 'Panel not found.';
            $noticeType = 'danger';
        } else {
            $res = SmmProviderClient::fromPanel($panel)->getServices();
            if (empty($res['success'])) {
                $notice = 'Could not load services from that panel: ' . ($res['message'] ?? 'error');
                $noticeType = 'danger';
            } else {
                $importList = array_slice($res['services'], 0, 200);
            }
        }
    }

    // Import one selected service from the panel (price defaults to cost — tenant edits after).
    if ($action === 'import_one') {
        $panelId = (int) ($_POST['panel_id'] ?? 0);
        $providerServiceId = trim($_POST['provider_service_id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $platform = trim($_POST['platform'] ?? '') ?: 'Other';
        $cost = ($_POST['cost_price'] ?? '') !== '' ? (float) $_POST['cost_price'] : null;
        $myPrice = (float) ($_POST['my_price'] ?? 0);

        if ($providerServiceId === '' || $name === '' || $myPrice <= 0) {
            $notice = 'Set a price above 0 to import this service.';
            $noticeType = 'danger';
        } elseif (BotService::existsForPanelService($tenantId, $panelId, $providerServiceId)) {
            $notice = 'That service is already imported.';
            $noticeType = 'danger';
        } else {
            BotService::create($tenantId, [
                'panel_id' => $panelId,
                'provider_service_id' => $providerServiceId,
                'platform' => $platform,
                'category' => trim($_POST['category'] ?? ''),
                'name' => $name,
                'unit_label' => 'Followers',
                'cost_price' => $cost,
                'my_price' => $myPrice,
                'min_quantity' => max(1, (int) ($_POST['min_quantity'] ?? 1)),
                'max_quantity' => max(1, (int) ($_POST['max_quantity'] ?? 100000)),
            ]);
            $notice = 'Imported "' . $name . '". Set your own price any time below.';
        }
    }
}

$services = BotService::allForTenant($tenantId);
$panels = TenantPanel::forTenant($tenantId);
$currency = BotSettings::get($tenantId, 'order')['shop']['currency'] ?? 'USD';

$pageTitle = 'Bot Services & Pricing';
$activeSide = 'bot-services';
require __DIR__ . '/includes/dash_header.php';

$money = static fn ($v) => $currency . ' ' . (($v == (int) $v) ? number_format((float) $v, 0) : number_format((float) $v, 2));
?>

<div style="margin-bottom:22px">
  <h1 class="dash-title" style="margin-bottom:4px">Bot Services &amp; Pricing</h1>
  <div style="color:var(--text-muted)">Set what your Order Bot sells and the price your customers pay (in <?= htmlspecialchars($currency) ?>). Prices are per 1,000 units.</div>
</div>

<?php if ($notice !== null): ?>
<div class="alert alert-<?= $noticeType ?>">
  <i class="fa-solid <?= $noticeType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="margin-top:3px"></i>
  <?= htmlspecialchars($notice) ?>
</div>
<?php endif; ?>

<!-- Import from a panel -->
<div class="wapi-card" style="margin-bottom:18px">
  <div class="wc-head">
    <div class="wc-ico blue"><i class="fa-solid fa-download"></i></div>
    <div class="wc-title" style="font-size:1rem">Import from a panel</div>
  </div>
  <?php if ($panels === []): ?>
    <p style="color:var(--text-muted);margin:0">Connect a panel first on the <a href="panels.php">Panels</a> page, then import its services here.</p>
  <?php else: ?>
    <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="fetch_panel">
      <div>
        <label style="font-size:.8rem;color:var(--text-muted)">Panel</label><br>
        <select name="panel_id" class="form-control" required>
          <?php foreach ($panels as $p): ?>
            <option value="<?= (int) $p['id'] ?>" <?= ($importPanelId === (int) $p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-outline" type="submit"><i class="fa-solid fa-rotate"></i> Load services</button>
    </form>

    <?php if ($importList !== null): ?>
      <div style="overflow-x:auto;margin-top:16px">
        <table style="width:100%;border-collapse:collapse;font-size:.86rem">
          <thead>
            <tr style="text-align:left;color:var(--text-muted)">
              <th style="padding:8px">Service</th><th style="padding:8px">Cost/1k</th>
              <th style="padding:8px">Your price/1k</th><th style="padding:8px">Min–Max</th><th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($importList as $s):
              $sid = (string) ($s['service'] ?? $s['id'] ?? '');
              if ($sid === '') { continue; }
              $sname = (string) ($s['name'] ?? 'Service');
              $srate = (string) ($s['rate'] ?? '0');
              // Panels label services by a combined category like "Instagram Followers".
              // Split it into a platform (first known word) + a sub-category so the
              // bot can offer Platform → Category → Service.
              [$splatform, $scategory] = splitPanelCategory((string) ($s['category'] ?? 'Other'));
              $smin = (int) ($s['min'] ?? 1);
              $smax = (int) ($s['max'] ?? 100000); ?>
            <tr style="border-top:1px solid var(--glass-border)">
              <form method="post">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="import_one">
                <input type="hidden" name="panel_id" value="<?= (int) $importPanelId ?>">
                <input type="hidden" name="provider_service_id" value="<?= htmlspecialchars($sid, ENT_QUOTES) ?>">
                <input type="hidden" name="name" value="<?= htmlspecialchars($sname, ENT_QUOTES) ?>">
                <input type="hidden" name="platform" value="<?= htmlspecialchars($splatform, ENT_QUOTES) ?>">
                <input type="hidden" name="category" value="<?= htmlspecialchars($scategory, ENT_QUOTES) ?>">
                <input type="hidden" name="cost_price" value="<?= htmlspecialchars($srate, ENT_QUOTES) ?>">
                <input type="hidden" name="min_quantity" value="<?= $smin ?>">
                <input type="hidden" name="max_quantity" value="<?= $smax ?>">
                <td style="padding:8px"><strong><?= htmlspecialchars($sname) ?></strong><br><span style="color:var(--text-muted);font-size:.76rem"><?= htmlspecialchars($splatform) ?><?= $scategory !== '' ? ' · ' . htmlspecialchars($scategory) : '' ?> · #<?= htmlspecialchars($sid) ?></span></td>
                <td style="padding:8px"><?= htmlspecialchars($srate) ?></td>
                <td style="padding:8px"><input type="number" step="0.0001" min="0" name="my_price" class="form-control" style="width:110px" placeholder="e.g. <?= htmlspecialchars($srate) ?>" required></td>
                <td style="padding:8px;white-space:nowrap"><?= number_format($smin) ?>–<?= number_format($smax) ?></td>
                <td style="padding:8px"><button class="btn btn-primary" type="submit" style="padding:6px 12px">Import</button></td>
              </form>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- Current services -->
<div class="wapi-card">
  <div class="wc-head">
    <div class="wc-ico green"><i class="fa-solid fa-tags"></i></div>
    <div class="wc-title" style="font-size:1rem">Your services (<?= count($services) ?>)</div>
  </div>

  <?php if ($services === []): ?>
    <p style="color:var(--text-muted);margin:0">No services yet. Import from a panel above, or add one manually below.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:.88rem">
        <thead>
          <tr style="text-align:left;color:var(--text-muted)">
            <th style="padding:8px">Service</th><th style="padding:8px">Category</th><th style="padding:8px">Your price/1k</th>
            <th style="padding:8px">Min–Max</th><th style="padding:8px">Status</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($services as $s): ?>
          <tr style="border-top:1px solid var(--glass-border)">
            <form method="post">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <td style="padding:8px">
                <input type="text" name="name" value="<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>" class="form-control" style="width:220px">
                <div style="color:var(--text-muted);font-size:.74rem;margin-top:2px"><?= htmlspecialchars($s['platform']) ?> · #<?= htmlspecialchars($s['provider_service_id']) ?><?= $s['cost_price'] !== null ? ' · cost ' . htmlspecialchars($s['cost_price']) : '' ?></div>
              </td>
              <td style="padding:8px">
                <?php $cur = (string) ($s['category'] ?? ''); $inList = $cur === '' || in_array($cur, $CATEGORY_OPTIONS, true); ?>
                <select name="category" class="form-control" style="width:130px" data-other="cat_<?= (int) $s['id'] ?>">
                  <option value="" <?= $cur === '' ? 'selected' : '' ?>>— none —</option>
                  <?php foreach ($CATEGORY_OPTIONS as $opt): ?><option value="<?= htmlspecialchars($opt, ENT_QUOTES) ?>" <?= $cur === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option><?php endforeach; ?>
                  <?php if (!$inList): ?><option value="<?= htmlspecialchars($cur, ENT_QUOTES) ?>" selected><?= htmlspecialchars($cur) ?></option><?php endif; ?>
                  <option value="__other">Other…</option>
                </select>
                <input type="text" id="cat_<?= (int) $s['id'] ?>" name="category_other" class="form-control" placeholder="Type category" style="display:none;margin-top:6px;width:130px">
              </td>
              <td style="padding:8px"><input type="number" step="0.0001" min="0" name="my_price" value="<?= htmlspecialchars($s['my_price'], ENT_QUOTES) ?>" class="form-control" style="width:100px"></td>
              <td style="padding:8px;white-space:nowrap">
                <input type="number" name="min_quantity" value="<?= (int) $s['min_quantity'] ?>" class="form-control" style="width:75px;display:inline-block">–
                <input type="number" name="max_quantity" value="<?= (int) $s['max_quantity'] ?>" class="form-control" style="width:85px;display:inline-block">
              </td>
              <td style="padding:8px">
                <select name="status" class="form-control" style="width:110px">
                  <option value="active" <?= $s['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                  <option value="inactive" <?= $s['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
              </td>
              <td style="padding:8px;white-space:nowrap">
                <button class="btn btn-primary" type="submit" style="padding:6px 10px">Save</button>
            </form>
                <form method="post" style="display:inline" onsubmit="return confirm('Delete this service?')">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                  <button class="btn btn-outline" type="submit" style="padding:6px 10px;color:#DC2626">Delete</button>
                </form>
              </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Add manually -->
<div class="wapi-card" style="margin-top:18px">
  <div class="wc-head">
    <div class="wc-ico purple"><i class="fa-solid fa-plus"></i></div>
    <div class="wc-title" style="font-size:1rem">Add a service manually</div>
  </div>
  <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;align-items:end">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="add">
    <div><label style="font-size:.8rem;color:var(--text-muted)">Name</label><input type="text" name="name" class="form-control" placeholder="Instagram Followers" required></div>
    <div>
      <label style="font-size:.8rem;color:var(--text-muted)">Platform</label>
      <select name="platform" class="form-control" data-other="platformOther" required>
        <option value="">— choose —</option>
        <?php foreach ($PLATFORM_OPTIONS as $opt): ?><option value="<?= htmlspecialchars($opt, ENT_QUOTES) ?>"><?= htmlspecialchars($opt) ?></option><?php endforeach; ?>
        <option value="__other">Other… (type it)</option>
      </select>
      <input type="text" id="platformOther" name="platform_other" class="form-control" placeholder="Type platform" style="display:none;margin-top:8px">
    </div>
    <div>
      <label style="font-size:.8rem;color:var(--text-muted)">Category (optional)</label>
      <select name="category" class="form-control" data-other="categoryOther">
        <option value="">— none —</option>
        <?php foreach ($CATEGORY_OPTIONS as $opt): ?><option value="<?= htmlspecialchars($opt, ENT_QUOTES) ?>"><?= htmlspecialchars($opt) ?></option><?php endforeach; ?>
        <option value="__other">Other… (type it)</option>
      </select>
      <input type="text" id="categoryOther" name="category_other" class="form-control" placeholder="Type category" style="display:none;margin-top:8px">
    </div>
    <div><label style="font-size:.8rem;color:var(--text-muted)">Panel (optional)</label>
      <select name="panel_id" class="form-control">
        <option value="">— none —</option>
        <?php foreach ($panels as $p): ?><option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div><label style="font-size:.8rem;color:var(--text-muted)">Provider service ID</label><input type="text" name="provider_service_id" class="form-control" placeholder="101" required></div>
    <div><label style="font-size:.8rem;color:var(--text-muted)">Your price / 1k (<?= htmlspecialchars($currency) ?>)</label><input type="number" step="0.0001" min="0" name="my_price" class="form-control" required></div>
    <div><label style="font-size:.8rem;color:var(--text-muted)">Min qty</label><input type="number" name="min_quantity" class="form-control" value="100"></div>
    <div><label style="font-size:.8rem;color:var(--text-muted)">Max qty</label><input type="number" name="max_quantity" class="form-control" value="100000"></div>
    <div><button class="btn btn-primary btn-block" type="submit">Add service</button></div>
  </form>
</div>

<script>
/* Reveal the paired "type it" text box when a dropdown's "Other…" is chosen. */
document.querySelectorAll('select[data-other]').forEach(function (sel) {
  var box = document.getElementById(sel.dataset.other);
  if (!box) return;
  function sync() {
    var other = sel.value === '__other';
    box.style.display = other ? '' : 'none';
    box.required = other && sel.required;
    if (!other) box.value = '';
  }
  sel.addEventListener('change', sync);
  sync();
});
</script>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
