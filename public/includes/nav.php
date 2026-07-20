<?php
// Dashboard sidebar. Expects $activeSide and $tenant (authed row).
//
// The sidebar is SUBSCRIPTION-AWARE (à la carte): a service's pages only appear
// once that service is relevant. A brand-new / sandbox reseller sees a small
// "setup" menu (dashboard, channel, panel, order-bot basics, billing) — the rest
// unlocks per service after they pay, so the value stays behind the paywall.
//   - order_bot  live  → Payment Gateways, Customers, Orders
//   - support_bot live → Support Bot, Templates, Guarantee
//   - any service live → Growth (Broadcast, Referrals), Telegram (Soon)
// Pages not yet built stay disabled with a "Soon" chip.

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
    echo '<div class="sidebar-group" data-group="' . $key . '">' . htmlspecialchars(Lang::t($labelKey))
       . '<i class="fa-solid fa-chevron-down grp-caret"></i></div>';
}
?>
<aside class="sidebar" id="sidebar">
  <button class="side-toggle" id="sideToggle" aria-label="Collapse menu"><i class="fa-solid fa-chevron-left"></i></button>
  <a class="nav-logo" href="index.php">
    <span class="logo-mark"><i class="fa-brands fa-whatsapp"></i></span>
    <span><?= htmlspecialchars(Lang::t('brand')) ?></span>
  </a>

  <form class="side-search" action="orders.php" method="get">
    <i class="fa-solid fa-magnifying-glass"></i>
    <input type="search" name="q" placeholder="<?= htmlspecialchars(Lang::t('search_ph')) ?>" autocomplete="off">
  </form>

  <?php sideGroup('overview', 'side_overview'); ?>
  <div class="side-group-items">
    <?php sideLink('dashboard', 'index.php', 'fa-solid fa-chart-line', Lang::t('side_dashboard')); ?>
  </div>

  <?php /* Channels: WhatsApp is always needed for setup; Telegram unlocks once live. */ ?>
  <?php sideGroup('channels', 'side_channels'); ?>
  <div class="side-group-items">
    <?php sideLink('whatsapp', 'whatsapp.php', 'fa-brands fa-whatsapp', Lang::t('side_whatsapp'), true); ?>
    <?php if ($anyLive): ?>
      <?php sideLink('telegram', 'telegram.php', 'fa-brands fa-telegram', Lang::t('tg_side'), false); ?>
    <?php endif; ?>
  </div>

  <?php /* Panel: the SMM provider connection that fulfils orders — needed to set up. */ ?>
  <?php sideGroup('panel', 'side_panel'); ?>
  <div class="side-group-items">
    <?php sideLink('panels', 'panels.php', 'fa-solid fa-plug', Lang::t('side_panels'), true); ?>
  </div>

  <?php /* ORDER BOT: setup pages while on (sandbox/active); operational pages only when paid. */ ?>
  <?php if ($orderOn): ?>
  <?php sideGroup('orderbot', 'side_orderbot_grp'); ?>
  <div class="side-group-items">
    <?php sideLink('order-bot', 'order-bot.php', 'fa-solid fa-cart-shopping', Lang::t('side_order_bot'), true); ?>
    <?php sideLink('bot-services', 'bot-services.php', 'fa-solid fa-tags', Lang::t('side_bot_services'), true); ?>
    <?php if ($orderLive): ?>
      <?php sideLink('bot-gateways', 'bot-gateways.php', 'fa-solid fa-money-bill-wave', Lang::t('side_bot_gateways'), true); ?>
      <?php sideLink('bot-customers', 'bot-customers.php', 'fa-solid fa-users', Lang::t('side_bot_customers'), true); ?>
      <?php sideLink('orders', 'orders.php', 'fa-solid fa-box', Lang::t('side_orders'), true); ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php /* SUPPORT BOT: unlocks only when the support_bot service is paid. */ ?>
  <?php if ($supportLive): ?>
  <?php sideGroup('supportbot', 'side_supportbot_grp'); ?>
  <div class="side-group-items">
    <?php sideLink('support-bot', 'support-bot.php', 'fa-solid fa-headset', Lang::t('side_support_bot'), true); ?>
    <?php sideLink('templates', 'templates.php', 'fa-solid fa-message', Lang::t('side_templates'), true); ?>
    <?php sideLink('guarantee', 'guarantee-rules.php', 'fa-solid fa-recycle', Lang::t('side_guarantee'), true); ?>
  </div>
  <?php endif; ?>

  <?php /* Growth: reach customers + earn from referrals — once at least one service is live. */ ?>
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
