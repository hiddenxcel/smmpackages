<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];
$db = DB::conn();

// --- Service gate + days left (a la carte) ---
$gate = Subscription::statusMap($tenantId);
$state = Subscription::stateMap($tenantId);        // active | sandbox | locked
$sandboxCount = count(array_filter($state, fn ($s) => $s === 'sandbox'));
$anyLive = in_array('active', $state, true);
$daysLeft = [];
foreach (Subscription::SERVICES as $svc) {
    $daysLeft[$svc] = $gate[$svc] ? Subscription::daysLeft($tenantId, $svc) : null;
}

// --- Usage meters + live counters + chart data ---
$usage = UsageStats::forTenant($tenantId);
$today = UsageStats::todayCounts($tenantId);
$chart = UsageStats::orders30d($tenantId);

// --- WhatsApp connection ---
$whatsapp = TenantWhatsApp::forTenant($tenantId);
$hasWhatsApp = $whatsapp !== null;
$whatsappStatus = $whatsapp['status'] ?? null;

// --- Best plan / renewal info: pick the active subscription ending soonest ---
$stmt = $db->prepare(
    "SELECT s.service_key, s.ends_at, p.name AS plan_name, p.price_monthly, p.currency
     FROM subscriptions s LEFT JOIN plans p ON p.id = s.plan_id
     WHERE s.tenant_id = ? AND s.status = 'active' AND (s.ends_at IS NULL OR s.ends_at > NOW())
     ORDER BY s.ends_at IS NULL, s.ends_at ASC LIMIT 1"
);
$stmt->execute([$tenantId]);
$renewal = $stmt->fetch() ?: null;
$renewalDays = ($renewal && !empty($renewal['ends_at'])) ? max(0, (int) ceil((strtotime($renewal['ends_at']) - time()) / 86400)) : null;
// Progress: assume a 30-day cycle for the bar fill.
$renewalPct = $renewalDays !== null ? (int) min(100, round($renewalDays / 30 * 100)) : 0;

$anyBotActive = $gate['order_bot'] || $gate['support_bot'];

// --- Onboarding state: shown until the reseller goes live (has any paid sub) ---
// Keeps a brand-new sandbox account free of empty analytics ("no clutter"):
// a guided checklist toward the aha-moment (test the bot) and then Go Live.
$onbActive = !$anyLive;
$hasPanel     = TenantPanel::countForTenant($tenantId) > 0;
$hasServices  = count(BotService::allForTenant($tenantId)) > 0;
$testNumbers  = BotSettings::get($tenantId, 'order')['shop']['test_numbers'] ?? [];
$hasTestNumber = !empty($testNumbers);
$hasTested    = (int) $db->query('SELECT COUNT(*) FROM bot_messages WHERE tenant_id = ' . $tenantId)->fetchColumn() > 0;

$onbSteps = [
    ['key' => 'account',  'done' => true,          'icon' => 'fa-solid fa-user-check',    'href' => null],
    ['key' => 'whatsapp', 'done' => $hasWhatsApp,  'icon' => 'fa-brands fa-whatsapp',     'href' => 'whatsapp.php'],
    ['key' => 'panel',    'done' => $hasPanel,     'icon' => 'fa-solid fa-plug',          'href' => 'panels.php'],
    ['key' => 'services', 'done' => $hasServices,  'icon' => 'fa-solid fa-tags',          'href' => 'bot-services.php'],
    ['key' => 'test',     'done' => $hasTested,    'icon' => 'fa-solid fa-flask',         'href' => 'order-bot.php'],
];
$onbTotal = count($onbSteps);
$onbDone  = count(array_filter($onbSteps, fn ($s) => $s['done']));
$onbPct   = (int) round($onbDone / $onbTotal * 100);
$onbAllDone = $onbDone === $onbTotal;
$onbNext = null;
foreach ($onbSteps as $s) { if (!$s['done']) { $onbNext = $s; break; } }
// SVG ring geometry (r=54 → circumference ≈ 339.29)
$onbCirc = 2 * M_PI * 54;
$onbOffset = $onbCirc * (1 - $onbPct / 100);

// --- Greeting by local hour ---
$h = (int) date('G');
$greetKey = $h < 12 ? 'greet_morning' : ($h < 17 ? 'greet_afternoon' : 'greet_evening');

// Money helper for prices
function money2(float $v): string { return rtrim(rtrim(number_format($v, 2), '0'), '.'); }

// Service card meta (order matters: Number ⭐ recommended, then bots, then AI)
$svcMeta = [
    'number_rental' => ['icon' => 'fa-solid fa-phone',         'label' => Lang::t('svc_number_title'),  'rec' => true],
    'order_bot'     => ['icon' => 'fa-solid fa-cart-shopping', 'label' => Lang::t('svc_order_title'),   'rec' => false],
    'support_bot'   => ['icon' => 'fa-solid fa-headset',       'label' => Lang::t('svc_support_title'), 'rec' => false],
    'ai_tickets'    => ['icon' => 'fa-solid fa-robot',         'label' => Lang::t('svc_ai_title'),      'rec' => false],
];
// Prices per service (from catalogue) for locked cards
$priceStmt = $db->query("SELECT service_key, price_monthly, currency FROM plans WHERE status='active'");
$prices = [];
foreach ($priceStmt->fetchAll() as $r) { $prices[$r['service_key']] = $r; }

$pageTitle = Lang::t('dash_v2_title');
$activeSide = 'dashboard';
require __DIR__ . '/includes/dash_header.php';
?>

<?php if (isset($_GET['welcome'])): ?>
<!-- One-time celebratory line right after free sign-up. -->
<div class="welcome-toast" id="welcomeToast">
  <span class="wt-emoji">🎉</span>
  <span><strong><?php e('onb_welcome_toast_t'); ?></strong> <?php e('onb_welcome_toast_s'); ?></span>
  <button class="wt-x" onclick="document.getElementById('welcomeToast').remove()" aria-label="close">&times;</button>
</div>
<?php endif; ?>

<?php if (!$onbActive): ?>
<!-- Title row -->
<div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:26px">
  <div>
    <h1 class="dash-title" style="margin-bottom:4px"><?php e('dash_v2_title'); ?></h1>
    <div style="color:var(--text-muted)"><?php e('dash_v2_sub'); ?></div>
  </div>
</div>
<?php endif; ?>

<?php if ($sandboxCount > 0 && !$onbActive): ?>
<!-- Sandbox banner: shown once partially live (onboarding hero covers new users). -->
<div class="sandbox-banner">
  <div class="sb-ico"><i class="fa-solid fa-flask"></i></div>
  <div class="sb-text">
    <strong><?php e('sandbox_title'); ?></strong>
    <span><?php e('sandbox_sub_partial'); ?></span>
  </div>
  <a href="subscription.php?golive=1" class="btn btn-primary sb-cta"><i class="fa-solid fa-rocket"></i> <?php e('sandbox_go_live'); ?></a>
</div>
<?php endif; ?>

<?php if ($onbActive): ?>
<!-- ══ ONBOARDING: premium guided setup (replaces empty analytics) ══ -->
<section class="onb">
  <div class="onb-hero">
    <div class="onb-hero-main">
      <span class="onb-eyebrow"><i class="fa-solid fa-wand-magic-sparkles"></i> <?php e('onb_eyebrow'); ?></span>
      <h1><?= htmlspecialchars(Lang::t('onb_welcome', ['name' => $tenant['business_name']])) ?> 👋</h1>
      <p><?php e($onbAllDone ? 'onb_intro_done' : 'onb_intro'); ?></p>
      <div class="onb-cta-row">
        <?php if ($onbAllDone): ?>
          <a href="subscription.php?golive=1" class="btn btn-primary btn-lg"><i class="fa-solid fa-rocket"></i> <?php e('sandbox_go_live'); ?></a>
        <?php else: ?>
          <a href="<?= htmlspecialchars($onbNext['href']) ?>" class="btn btn-primary btn-lg"><?= htmlspecialchars(Lang::t('onb_s_' . $onbNext['key'] . '_t')) ?> <i class="fa-solid fa-arrow-right"></i></a>
          <a href="subscription.php?golive=1" class="btn btn-outline btn-lg"><i class="fa-solid fa-rocket"></i> <?php e('sandbox_go_live'); ?></a>
        <?php endif; ?>
      </div>
    </div>
    <div class="onb-ring" role="img" aria-label="<?= $onbPct ?>%">
      <svg viewBox="0 0 120 120">
        <defs>
          <linearGradient id="onbGrad" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="var(--primary)"/>
            <stop offset="100%" stop-color="#22c55e"/>
          </linearGradient>
        </defs>
        <circle class="onb-ring-track" cx="60" cy="60" r="54"/>
        <circle class="onb-ring-fill" cx="60" cy="60" r="54"
          stroke-dasharray="<?= round($onbCirc, 2) ?>" stroke-dashoffset="<?= round($onbOffset, 2) ?>"/>
      </svg>
      <div class="onb-ring-txt">
        <span class="onb-ring-pct"><?= $onbPct ?>%</span>
        <span class="onb-ring-cap"><?= $onbDone ?>/<?= $onbTotal ?></span>
      </div>
    </div>
  </div>

  <div class="onb-steps">
    <?php $i = 0; foreach ($onbSteps as $s): $i++;
      $isNext = !$s['done'] && $onbNext && $s['key'] === $onbNext['key'];
      $cls = $s['done'] ? 'done' : ($isNext ? 'active' : 'todo');
      $tag = 'div'; $attr = '';
      if (!$s['done'] && $s['href']) { $tag = 'a'; $attr = ' href="' . htmlspecialchars($s['href']) . '"'; }
    ?>
    <<?= $tag ?> class="onb-step <?= $cls ?>"<?= $attr ?>>
      <span class="onb-step-ico">
        <?php if ($s['done']): ?><i class="fa-solid fa-check"></i><?php else: ?><span class="onb-step-n"><?= $i ?></span><?php endif; ?>
      </span>
      <span class="onb-step-body">
        <span class="onb-step-title"><?php e('onb_s_' . $s['key'] . '_t'); ?></span>
        <span class="onb-step-sub"><?php e('onb_s_' . $s['key'] . '_s'); ?></span>
      </span>
      <span class="onb-step-action">
        <?php if ($s['done']): ?>
          <span class="onb-chip done"><i class="fa-solid fa-check"></i> <?php e('onb_done'); ?></span>
        <?php elseif ($isNext): ?>
          <span class="onb-chip go"><?php e('onb_start'); ?> <i class="fa-solid fa-arrow-right"></i></span>
        <?php else: ?>
          <span class="onb-chip"><i class="fa-solid fa-arrow-right"></i></span>
        <?php endif; ?>
      </span>
    </<?= $tag ?>>
    <?php endforeach; ?>

    <!-- Final: Go Live (the conversion) -->
    <a href="subscription.php?golive=1" class="onb-golive <?= $onbAllDone ? 'ready' : '' ?>">
      <span class="onb-step-ico"><i class="fa-solid fa-rocket"></i></span>
      <span class="onb-step-body">
        <span class="onb-step-title"><?php e('onb_golive_t'); ?></span>
        <span class="onb-step-sub"><?php e('onb_golive_s'); ?></span>
      </span>
      <span class="onb-step-action"><span class="onb-chip go"><?php e('sandbox_go_live'); ?> <i class="fa-solid fa-arrow-right"></i></span></span>
    </a>
  </div>
</section>
<?php else: ?>
<!-- Top row: hero + WhatsApp + renewal -->
<div class="grid dash-top-grid" style="gap:20px;margin-bottom:8px">
  <!-- Hero greeting -->
  <div class="hero-card">
    <div class="workspace-name"><?php e('brand'); ?></div>
    <span class="greeting-badge"><?php e($greetKey); ?></span>
    <h2><?= htmlspecialchars(Lang::t('greet_hi', ['name' => $tenant['business_name']])) ?> 👋</h2>
    <div class="date"><?= date('l, F j, Y') ?></div>

    <!-- Quick access, inside the hero (target look) -->
    <div class="hero-quick">
      <div class="hq-label"><?php e('quick_access'); ?></div>
      <div class="hq-grid">
        <a href="broadcast.php" class="hq-card">
          <span class="hq-ico"><i class="fa-solid fa-rocket"></i></span>
          <span class="hq-txt">
            <span class="hq-name"><?php e('bc_side'); ?></span>
            <span class="hq-sub"><?php e('quick_broadcast_sub'); ?></span>
          </span>
          <i class="fa-solid fa-chevron-right hq-arrow"></i>
        </a>
        <a href="templates.php" class="hq-card">
          <span class="hq-ico"><i class="fa-solid fa-sliders"></i></span>
          <span class="hq-txt">
            <span class="hq-name"><?php e('quick_toolset'); ?></span>
            <span class="hq-sub"><?php e('quick_toolset_sub'); ?></span>
          </span>
          <i class="fa-solid fa-chevron-right hq-arrow"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- WhatsApp connect card -->
  <div class="stat-card">
    <div class="card-top">
      <div class="stat-icon green"><i class="fa-brands fa-whatsapp"></i></div>
      <?php if ($whatsappStatus === 'active'): ?>
        <span class="badge-verified"><i class="fa-solid fa-check"></i> <?php e('verified'); ?></span>
      <?php elseif ($hasWhatsApp): ?>
        <span class="badge-verified badge-pending"><?php e('pending'); ?></span>
      <?php endif; ?>
    </div>
    <h3 style="margin:0 0 4px;font-size:1.05rem"><?php e('connect_whatsapp'); ?></h3>
    <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:2px"><?php e('connect_whatsapp_sub'); ?></p>
    <?php if (!$hasWhatsApp): ?>
      <div class="method-grid">
        <a href="whatsapp.php?method=rent" class="method-btn primary">
          <i class="fa-solid fa-star"></i><span><?php e('rent_number_short'); ?></span><small><?php e('two_minutes'); ?></small>
        </a>
        <a href="whatsapp.php?method=own" class="method-btn">
          <i class="fa-solid fa-wrench"></i><span><?php e('own_number_short'); ?></span><small><?php e('have_meta'); ?></small>
        </a>
      </div>
    <?php else: ?>
      <div style="margin:14px 0 10px">
        <strong><?= htmlspecialchars($whatsapp['display_number'] ?: $whatsapp['phone_number_id']) ?></strong>
        <div style="color:var(--text-muted);font-size:.82rem"><?php e($whatsapp['source'] === 'rented' ? 'wa_source_rented' : 'wa_source_own'); ?></div>
      </div>
    <?php endif; ?>
    <a href="whatsapp.php" class="btn btn-primary btn-block"><?php e('manage_whatsapp'); ?></a>
  </div>

  <!-- Renewal card -->
  <div class="renewal-card">
    <div class="plan-row">
      <span class="plan-crown"><i class="fa-solid fa-crown"></i></span>
      <span class="plan-name"><?= $renewal ? htmlspecialchars($renewal['plan_name'] ?? 'Plan') : htmlspecialchars(Lang::t('no_active_plan')) ?></span>
    </div>
    <?php if ($renewal): ?>
      <div class="plan-meta"><?= htmlspecialchars(Lang::t('renew_expires', ['date' => $renewal['ends_at'] ? date('Y-m-d', strtotime($renewal['ends_at'])) : '—'])) ?><?= $renewalDays !== null ? ' · ' . htmlspecialchars(Lang::t('renew_days_left', ['days' => $renewalDays])) : '' ?></div>
      <div class="plan-price big"><?= htmlspecialchars($renewal['currency'] ?? 'USD') ?> <?= money2((float) ($renewal['price_monthly'] ?? 0)) ?><small>/<?php e('per_month'); ?></small></div>
      <div class="plan-billing"><?php e('billed_monthly'); ?> · <?= htmlspecialchars($tenant['email'] ?? '') ?></div>
      <div class="renewal-row">
        <span><?= $renewalDays !== null ? htmlspecialchars(Lang::t('renew_days_left', ['days' => $renewalDays])) : '∞' ?></span>
        <span><?= $renewalPct ?>%</span>
      </div>
      <div class="progress-bar <?= $renewalDays !== null && $renewalDays <= 5 ? 'danger' : 'green' ?>"><span style="width:<?= $renewalPct ?>%"></span></div>
      <a href="subscription.php" class="btn btn-outline btn-block" style="margin-top:14px"><?php e('renew_extend'); ?></a>
    <?php else: ?>
      <div class="plan-meta" style="margin:10px 0 14px"><?php e('dash_v2_sub'); ?></div>
      <a href="subscription.php" class="btn btn-primary btn-block"><?php e('choose_plan'); ?></a>
    <?php endif; ?>
  </div>
</div>
<?php endif; /* end top-row (full dashboard only) */ ?>

<!-- MY SERVICES -->
<div class="dash-section-label"><?php e('sec_my_services'); ?></div>
<div class="grid grid-4">
  <?php foreach ($svcMeta as $key => $meta): $st = $state[$key] ?? 'locked';
    $cls = 'is-' . $st; if ($meta['rec']) $cls .= ' recommended';
    $manageHref = $key === 'number_rental' ? 'whatsapp.php' : ($key === 'order_bot' ? 'order-bot.php' : ($key === 'support_bot' ? 'support-bot.php' : 'tickets.php')); ?>
  <div class="svc-card <?= $cls ?>"<?= $meta['rec'] ? ' data-badge="' . htmlspecialchars(Lang::t('svc_recommended'), ENT_QUOTES) . '"' : '' ?>>
    <div class="svc-ico"><i class="<?= $meta['icon'] ?>"></i></div>
    <div class="svc-name"><?= htmlspecialchars($meta['label']) ?></div>
    <?php if ($st === 'active'): ?>
      <div class="svc-state on">✅ <?php e('svc_active'); ?></div>
      <div class="svc-sub"><?= $daysLeft[$key] !== null ? htmlspecialchars(Lang::t('renew_days_left', ['days' => $daysLeft[$key]])) : '∞' ?></div>
      <a href="<?= $manageHref ?>" class="btn btn-outline btn-block"><?php e('svc_manage'); ?></a>
    <?php elseif ($st === 'sandbox'): ?>
      <div class="svc-state sandbox">🧪 <?php e('svc_sandbox'); ?></div>
      <div class="svc-sub"><?php e('svc_sandbox_sub'); ?></div>
      <a href="<?= $manageHref ?>" class="btn btn-outline btn-block"><?php e('svc_setup'); ?></a>
      <a href="subscription.php?golive=1&svc=<?= htmlspecialchars($key) ?>" class="btn btn-primary btn-block" style="margin-top:8px"><?php e('sandbox_go_live'); ?></a>
    <?php else: ?>
      <div class="svc-state off">🔒 <?php e('svc_locked'); ?></div>
      <div class="svc-sub"><?php if (isset($prices[$key])): ?><?= htmlspecialchars($prices[$key]['currency']) ?> <?= money2((float) $prices[$key]['price_monthly']) ?><?php e('per_month'); ?><?php endif; ?></div>
      <a href="subscription.php" class="btn btn-primary btn-block"><?php e('buy_now'); ?></a>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<?php if (!$onbActive): /* Analytics hidden until live — no empty clutter */ ?>
<!-- RESOURCE INSIGHTS (Wapi-style: X / Y + % UTILIZED) -->
<?php $activeSvcCount = count(array_filter($gate)); ?>
<div class="dash-section-label"><?php e('sec_resource_insights'); ?></div>
<div class="insight-grid cols-5">
  <?php
  $resources = [
      ['panels',   'blue',   'fa-solid fa-plug',    'usage_panels'],
      ['orders',   'green',  'fa-solid fa-box',     'usage_orders'],
      ['messages', 'purple', 'fa-solid fa-message', 'usage_messages'],
      ['refills',  'orange', 'fa-solid fa-recycle', 'usage_refills'],
  ];
  foreach ($resources as [$mk, $color, $icon, $labelKey]): $m = $usage[$mk]; ?>
  <div class="wapi-card">
    <div class="wc-head">
      <div class="wc-ico <?= $color ?>"><i class="<?= $icon ?>"></i></div>
      <div class="wc-title"><?php e($labelKey); ?></div>
    </div>
    <div class="wc-num"><?= number_format($m['used']) ?> <span class="wc-max">/ <?= number_format($m['max']) ?></span></div>
    <div class="wc-track <?= $m['band'] ?>"><span style="width:<?= max(2, $m['pct']) ?>%"></span></div>
    <div class="wc-foot <?= $m['band'] ?>"><?= $m['pct'] ?>% <?php e('utilized'); ?></div>
  </div>
  <?php endforeach; ?>
  <!-- Active services -->
  <div class="wapi-card">
    <div class="wc-head">
      <div class="wc-ico teal"><i class="fa-solid fa-layer-group"></i></div>
      <div class="wc-title"><?php e('res_active_services'); ?></div>
    </div>
    <div class="wc-num"><?= (int) $activeSvcCount ?> <span class="wc-max">/ <?= count(Subscription::SERVICES) ?></span></div>
    <div class="wc-track green"><span style="width:<?= max(2, (int) round($activeSvcCount / max(1, count(Subscription::SERVICES)) * 100)) ?>%"></span></div>
    <div class="wc-foot <?= $anyBotActive ? 'green' : '' ?>"><?php e($anyBotActive ? 'stat_bot_active' : 'stat_bot_off'); ?></div>
  </div>
</div>

<!-- LIVE METRICS (Wapi-style real-time row) -->
<div class="dash-section-label"><?php e('sec_live_metrics'); ?></div>
<div class="insight-grid cols-5">
  <?php
  $metrics = [
      ['blue',   'fa-solid fa-box',          $today['today'],      'stat_today'],
      ['green',  'fa-solid fa-circle-check', $today['completed'],  'stat_completed'],
      ['orange', 'fa-solid fa-hourglass-half', $today['inprogress'], 'stat_inprogress'],
      ['purple', 'fa-solid fa-recycle',      $today['refills'],    'usage_refills'],
  ];
  foreach ($metrics as [$color, $icon, $val, $labelKey]): ?>
  <div class="wapi-card">
    <div class="wc-head">
      <div class="wc-ico <?= $color ?>"><i class="<?= $icon ?>"></i></div>
      <div class="wc-title"><?php e($labelKey); ?></div>
    </div>
    <div class="wc-num"><?= number_format($val) ?></div>
    <div class="wc-foot live"><i class="fa-solid fa-circle" style="font-size:.5rem"></i> <?php e('real_time'); ?></div>
  </div>
  <?php endforeach; ?>
  <div class="wapi-card">
    <div class="wc-head">
      <div class="wc-ico pink"><i class="fa-solid fa-bolt"></i></div>
      <div class="wc-title"><?php e('stat_bot'); ?></div>
    </div>
    <div class="wc-num" style="font-size:1.15rem;padding-top:4px"><?php e($anyBotActive ? 'stat_bot_active' : 'stat_bot_off'); ?></div>
    <div class="wc-foot <?= $anyBotActive ? 'live' : '' ?>"><?php e('real_time'); ?></div>
  </div>
</div>

<!-- ORDERS CHART (hand-drawn SVG) -->
<div class="dash-section-label"><?php e('sec_orders_chart'); ?></div>
<div class="card">
  <?php
  // Build a 30-slot series (fill gaps with 0).
  $byDay = [];
  foreach ($chart as $c) { $byDay[$c['d']] = (int) $c['n']; }
  $series = [];
  for ($i = 29; $i >= 0; $i--) { $d = date('m-d', strtotime("-{$i} day")); $series[] = $byDay[$d] ?? 0; }
  $maxV = max(1, max($series));
  $W = 720; $H = 150; $pad = 6; $n = count($series);
  $pts = [];
  foreach ($series as $i => $v) {
      $x = $pad + $i * (($W - 2 * $pad) / ($n - 1));
      $y = $H - $pad - ($v / $maxV) * ($H - 2 * $pad);
      $pts[] = [round($x, 1), round($y, 1)];
  }
  $linePath = 'M' . implode(' L', array_map(fn ($p) => "{$p[0]},{$p[1]}", $pts));
  $areaPath = "M{$pts[0][0]},{$pts[0][1]} L" . implode(' L', array_map(fn ($p) => "{$p[0]},{$p[1]}", $pts)) . " L{$pts[$n-1][0]},{$H} L{$pts[0][0]},{$H} Z";
  ?>
  <svg class="mini-chart" viewBox="0 0 <?= $W ?> <?= $H ?>" preserveAspectRatio="none" role="img" aria-label="Orders last 30 days">
    <defs>
      <linearGradient id="chartGrad" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="var(--primary)" stop-opacity="0.28"/>
        <stop offset="100%" stop-color="var(--primary)" stop-opacity="0"/>
      </linearGradient>
    </defs>
    <path d="<?= $areaPath ?>" fill="url(#chartGrad)"/>
    <path class="line" d="<?= $linePath ?>"/>
    <circle class="dot" cx="<?= $pts[$n-1][0] ?>" cy="<?= $pts[$n-1][1] ?>" r="4"/>
  </svg>
  <div style="display:flex;justify-content:space-between;color:var(--text-muted);font-size:.75rem;margin-top:6px">
    <span><?= date('M j', strtotime('-29 day')) ?></span>
    <span><?= date('M j') ?></span>
  </div>
</div>
<?php endif; /* end analytics (full dashboard only) */ ?>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
