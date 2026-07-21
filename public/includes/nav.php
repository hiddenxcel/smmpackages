<?php
// Dashboard sidebar. Expects $activeSide and $tenant (authed row).
//
// TWO-LEVEL (drill-in) sidebar: the main panel shows top-level sections plus two
// drill-in buttons — Order Bot and Support Bot. Clicking one swaps the whole
// sidebar for that service's pages (with a "← Back" header); the rest of the
// menu (Dashboard, Channels, Panel, Growth, Billing, Settings) lives on main.
//
// The sidebar stays SUBSCRIPTION-AWARE (à la carte): a service's drill-in button
// only appears once that service is relevant (sandbox/active), and operational
// pages inside only once it's paid.
//   - order_bot  live  → Payment Gateways, Customers, Orders
//   - support_bot live → Support Bot, Templates, Guarantee
//   - any service live → Growth (Broadcast, Referrals), Telegram (Soon)

$soon = '<span style="margin-left:auto;font-size:.62rem;font-weight:700;background:rgba(255,255,255,.08);color:var(--gray-500);padding:2px 7px;border-radius:999px">' . htmlspecialchars(Lang::t('soon')) . '</span>';

// --- Subscription state drives what the sidebar shows ---
$navTid = (int) ($tenant['id'] ?? 0);
$navState = $navTid ? Subscription::stateMap($navTid) : [];
// accessible for setup/self-test (sandbox OR active)
$svcOn = fn (string $k): bool => in_array($navState[$k] ?? 'locked', ['active', 'sandbox'], true);
// paid & live (active only)
$svcLive = fn (string $k): bool => ($navState[$k] ?? '') === 'active';

$orderOn     = $svcOn('order_bot');       // sandbox or paid → show setup pages
$orderLive   = $svcLive('order_bot');     // paid → show operational pages
$supportLive = $svcLive('support_bot');
$anyLive     = $svcLive('order_bot') || $svcLive('support_bot') || $svcLive('ai_tickets') || $svcLive('number_rental');

// Which drill-in panel does the CURRENT page belong to? Drives the initial view
// so a deep link (e.g. orders.php) opens straight into the Order Bot panel.
$orderKeys   = ['order-bot', 'bot-services', 'bot-gateways', 'bot-customers', 'orders'];
$supportKeys = ['support-bot', 'templates', 'guarantee'];
$startPanel = in_array($activeSide ?? '', $orderKeys, true) ? 'orderbot'
    : (in_array($activeSide ?? '', $supportKeys, true) ? 'supportbot' : 'main');

function sideLink(string $key, string $href, string $icon, string $label, bool $enabled = true): void
{
    global $activeSide, $soon;
    $active = ($activeSide === $key) ? ' active' : '';
    $lbl = htmlspecialchars($label);
    if ($enabled) {
        echo '<a class="side-link' . $active . '" href="' . $href . '" data-label="' . $lbl . '"><i class="' . $icon . '"></i> <span>' . $lbl . '</span></a>';
    } else {
        echo '<a class="side-link" data-label="' . $lbl . '" style="opacity:.45;cursor:default" onclick="return false"><i class="' . $icon . '"></i> <span>' . $lbl . ' ' . $soon . '</span></a>';
    }
}

function sideGroup(string $key, string $labelKey): void
{
    echo '<div class="sidebar-group" data-group="' . $key . '">' . htmlspecialchars(Lang::t($labelKey)) . '</div>';
}

/**
 * A drill-in button that swaps the sidebar to $panel when clicked.
 * $badge = optional count of pages inside, shown as a subtitle.
 */
function drillButton(string $panel, string $icon, string $label, string $sub): void
{
    echo '<button type="button" class="side-drill" data-open="' . $panel . '">'
       . '<i class="' . $icon . '"></i>'
       . '<span class="sd-body"><span class="sd-label">' . htmlspecialchars($label) . '</span>'
       . '<span class="sd-sub">' . htmlspecialchars($sub) . '</span></span>'
       . '<i class="fa-solid fa-chevron-right sd-caret"></i>'
       . '</button>';
}
?>
<aside class="sidebar" id="sidebar" data-panel="<?= $startPanel ?>">
  <button class="side-toggle" id="sideToggle" aria-label="Collapse menu"><i class="fa-solid fa-table-columns"></i></button>
  <a class="nav-logo" href="index.php">
    <span class="logo-mark"><i class="fa-brands fa-whatsapp"></i></span>
    <span><?= htmlspecialchars(Lang::t('brand')) ?></span>
  </a>

  <!-- ============ MAIN PANEL ============ -->
  <div class="side-panel" data-panel="main">
    <?php /* Quick links: the top-level pages every reseller reaches for —
             dashboard, channels (WhatsApp/Telegram) and the panel connection. */ ?>
    <?php sideGroup('quicklinks', 'side_quicklinks'); ?>
    <div class="side-group-items">
      <?php sideLink('dashboard', 'index.php', 'fa-solid fa-chart-line', Lang::t('side_dashboard')); ?>
      <?php sideLink('whatsapp', 'whatsapp.php', 'fa-brands fa-whatsapp', Lang::t('side_whatsapp'), true); ?>
      <?php if ($anyLive): ?>
        <?php sideLink('telegram', 'telegram.php', 'fa-brands fa-telegram', Lang::t('tg_side'), false); ?>
      <?php endif; ?>
      <?php sideLink('panels', 'panels.php', 'fa-solid fa-plug', Lang::t('side_panels'), true); ?>
    </div>

    <?php /* Drill-in buttons for the two bot services. */ ?>
    <?php if ($orderOn || $supportLive): ?>
    <?php sideGroup('bots', 'side_bots_grp'); ?>
    <div class="side-group-items">
      <?php if ($orderOn): ?>
        <?php drillButton('orderbot', 'fa-solid fa-cart-shopping', Lang::t('side_orderbot_grp'), Lang::t('side_orderbot_hint')); ?>
      <?php endif; ?>
      <?php if ($supportLive): ?>
        <?php drillButton('supportbot', 'fa-solid fa-headset', Lang::t('side_supportbot_grp'), Lang::t('side_supportbot_hint')); ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($anyLive): ?>
    <?php sideGroup('growth', 'side_growth_grp'); ?>
    <div class="side-group-items">
      <?php sideLink('broadcast', 'broadcast.php', 'fa-solid fa-bullhorn', Lang::t('bc_side'), true); ?>
      <?php sideLink('referrals', 'referrals.php', 'fa-solid fa-gift', Lang::t('ref_side'), true); ?>
    </div>

    <?php sideGroup('tickets', 'side_tickets_grp'); ?>
    <div class="side-group-items">
      <?php sideLink('tickets', 'tickets.php', 'fa-solid fa-ticket', Lang::t('side_tickets'), false); ?>
      <?php sideLink('ai', 'settings-ai.php', 'fa-solid fa-robot', Lang::t('side_ai'), false); ?>
    </div>
    <?php endif; ?>

    <?php sideGroup('billing', 'side_billing'); ?>
    <div class="side-group-items">
      <?php sideLink('subscription', 'subscription.php', 'fa-solid fa-credit-card', Lang::t('side_subscription'), true); ?>
      <?php sideLink('invoices', 'invoices.php', 'fa-solid fa-receipt', Lang::t('side_invoices'), true); ?>
    </div>

    <?php sideGroup('settings', 'side_settings'); ?>
    <div class="side-group-items">
      <?php sideLink('profile', 'profile.php', 'fa-solid fa-user', Lang::t('side_profile')); ?>
    </div>
  </div>

  <!-- ============ ORDER BOT PANEL ============ -->
  <?php if ($orderOn): ?>
  <div class="side-panel" data-panel="orderbot">
    <button type="button" class="side-back" data-back><i class="fa-solid fa-arrow-left"></i> <?php e('side_back'); ?></button>
    <div class="side-panel-title"><i class="fa-solid fa-cart-shopping"></i> <?php e('side_orderbot_grp'); ?></div>
    <div class="side-group-items">
      <?php sideLink('order-bot', 'order-bot.php', 'fa-solid fa-cart-shopping', Lang::t('side_order_bot'), true); ?>
      <?php sideLink('bot-services', 'bot-services.php', 'fa-solid fa-tags', Lang::t('side_bot_services'), true); ?>
      <?php if ($orderLive): ?>
        <?php sideLink('bot-gateways', 'bot-gateways.php', 'fa-solid fa-money-bill-wave', Lang::t('side_bot_gateways'), true); ?>
        <?php sideLink('bot-customers', 'bot-customers.php', 'fa-solid fa-users', Lang::t('side_bot_customers'), true); ?>
        <?php sideLink('orders', 'orders.php', 'fa-solid fa-box', Lang::t('side_orders'), true); ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ============ SUPPORT BOT PANEL ============ -->
  <?php if ($supportLive): ?>
  <div class="side-panel" data-panel="supportbot">
    <button type="button" class="side-back" data-back><i class="fa-solid fa-arrow-left"></i> <?php e('side_back'); ?></button>
    <div class="side-panel-title"><i class="fa-solid fa-headset"></i> <?php e('side_supportbot_grp'); ?></div>
    <div class="side-group-items">
      <?php sideLink('support-bot', 'support-bot.php', 'fa-solid fa-headset', Lang::t('side_support_bot'), true); ?>
      <?php sideLink('templates', 'templates.php', 'fa-solid fa-message', Lang::t('side_templates'), true); ?>
      <?php sideLink('guarantee', 'guarantee-rules.php', 'fa-solid fa-recycle', Lang::t('side_guarantee'), true); ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Pinned bottom: help + logout, always in reach. -->
  <div class="side-bottom">
    <a class="side-link" href="contact.php" data-label="<?= htmlspecialchars(Lang::t('side_help'), ENT_QUOTES) ?>">
      <i class="fa-solid fa-circle-question"></i> <span><?= htmlspecialchars(Lang::t('side_help')) ?></span>
    </a>
    <a class="side-link danger" href="logout.php" data-label="<?= htmlspecialchars(Lang::t('nav_logout'), ENT_QUOTES) ?>">
      <i class="fa-solid fa-arrow-right-from-bracket"></i> <span><?= htmlspecialchars(Lang::t('nav_logout')) ?></span>
    </a>
  </div>
</aside>

<script>
(function () {
  var sb = document.getElementById('sidebar');
  if (!sb) return;
  function show(panel, back) {
    sb.classList.remove('swap-in', 'swap-back');
    // force reflow so the animation restarts even on repeat clicks
    void sb.offsetWidth;
    sb.classList.add(back ? 'swap-back' : 'swap-in');
    sb.setAttribute('data-panel', panel);
  }
  sb.querySelectorAll('.side-drill').forEach(function (btn) {
    btn.addEventListener('click', function () { show(btn.dataset.open, false); });
  });
  sb.querySelectorAll('[data-back]').forEach(function (btn) {
    btn.addEventListener('click', function () { show('main', true); });
  });
})();
</script>
