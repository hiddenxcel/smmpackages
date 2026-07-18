<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Terms of Service';
require __DIR__ . '/includes/layout_header.php';
?>

<section class="section">
  <div class="container" style="max-width:760px">
    <h2 class="section-title">Terms of Service</h2>
    <div class="card" style="margin-top:24px;line-height:1.8">
      <h3>1. Service</h3>
      <p>SMM Packages provides WhatsApp bot and AI ticket infrastructure to SMM panel owners on a subscription basis. Each service is sold separately and billed monthly or yearly.</p>
      <h3 style="margin-top:22px">2. Acceptable use</h3>
      <p>You may not use the platform — including rented numbers — for spam, harassment, or any activity that violates Meta's WhatsApp Business terms. Violations lead to immediate suspension without refund.</p>
      <h3 style="margin-top:22px">3. Payments &amp; renewal</h3>
      <p>Subscriptions activate on confirmed payment (USDT or mobile money) and run for the purchased period. If a subscription expires, the service pauses; your data is retained for 30 days.</p>
      <h3 style="margin-top:22px">4. Your data &amp; keys</h3>
      <p>API keys and tokens you provide are encrypted at rest. You remain responsible for the validity of your panel, WhatsApp, and payment gateway credentials.</p>
      <h3 style="margin-top:22px">5. Liability</h3>
      <p>The platform is provided "as is". HiddenXcel is not liable for losses caused by third-party services (Meta, payment gateways, SMM providers) or by misuse of your account.</p>
      <p style="margin-top:22px;color:var(--text-muted);font-size:.85rem">Last updated: <?= date('F Y') ?></p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
