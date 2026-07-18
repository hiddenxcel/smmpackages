<?php
require_once __DIR__ . '/includes/bootstrap.php';

$plans = Plan::allActive();

$svcMeta = [
    'order_bot'     => 'fa-solid fa-cart-shopping',
    'support_bot'   => 'fa-solid fa-headset',
    'ai_tickets'    => 'fa-solid fa-robot',
    'number_rental' => 'fa-solid fa-phone',
];

$pageTitle = Lang::t('nav_pricing');
$activeNav = 'pricing';
require __DIR__ . '/includes/layout_header.php';
?>

<section class="section">
  <div class="container">
    <h2 class="section-title"><?php e('nav_pricing'); ?></h2>
    <p class="section-sub"><?php e('faq_a5'); ?></p>

    <div class="tabs">
      <button class="tab-btn active" data-tab="pane-monthly">Monthly</button>
      <button class="tab-btn" data-tab="pane-yearly">Yearly <span style="opacity:.8">-20%</span></button>
    </div>

    <div class="tab-pane active" id="pane-monthly">
      <div class="grid grid-4">
        <?php foreach ($plans as $p): ?>
        <div class="card" style="text-align:center">
          <div class="card-icon" style="margin:0 auto"><i class="<?= $svcMeta[$p['service_key']] ?? 'fa-solid fa-cube' ?>"></i></div>
          <h3><?= htmlspecialchars($p['name']) ?></h3>
          <p><?= htmlspecialchars($p['description'] ?? '') ?></p>
          <div class="price-tag"><?= money((float) $p['price_monthly']) ?><small><?php e('per_month'); ?></small></div>
          <a class="btn btn-primary btn-block" style="margin-top:14px" href="register.php"><?php e('buy_now'); ?></a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tab-pane" id="pane-yearly">
      <div class="grid grid-4">
        <?php foreach ($plans as $p): ?>
        <div class="card" style="text-align:center">
          <div class="card-icon" style="margin:0 auto"><i class="<?= $svcMeta[$p['service_key']] ?? 'fa-solid fa-cube' ?>"></i></div>
          <h3><?= htmlspecialchars($p['name']) ?></h3>
          <p><?= htmlspecialchars($p['description'] ?? '') ?></p>
          <div class="price-tag"><?= money((float) $p['price_yearly']) ?><small>/yr</small></div>
          <div style="font-size:.8rem;color:var(--success);font-weight:600">≈ <?= money((float) $p['price_yearly'] / 12) ?><?php e('per_month'); ?></div>
          <a class="btn btn-primary btn-block" style="margin-top:14px" href="register.php"><?php e('buy_now'); ?></a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div style="margin-top:44px">
      <div class="pay-methods">
        <span class="chip usdt"><i class="fa-brands fa-btc"></i> USDT</span>
        <span class="chip"><i class="fa-solid fa-mobile-screen"></i> M-Pesa</span>
        <span class="chip"><i class="fa-solid fa-mobile-screen"></i> Tigo Pesa</span>
        <span class="chip"><i class="fa-solid fa-mobile-screen"></i> Airtel Money</span>
        <span class="chip"><i class="fa-solid fa-mobile-screen"></i> Halopesa</span>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
