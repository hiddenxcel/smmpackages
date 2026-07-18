<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Privacy Policy';
require __DIR__ . '/includes/layout_header.php';
?>

<section class="section">
  <div class="container" style="max-width:760px">
    <h2 class="section-title">Privacy Policy</h2>
    <div class="card" style="margin-top:24px;line-height:1.8">
      <h3>1. What we collect</h3>
      <p>Account details (business name, email, phone), service configuration (panel URLs, WhatsApp identifiers), and payment records. Bot conversation logs are stored on behalf of the tenant that owns the bot.</p>
      <h3 style="margin-top:22px">2. How secrets are stored</h3>
      <p>API keys, access tokens, and gateway secrets are encrypted at rest with AES-256-GCM. They are decrypted only at the moment of use and never shown back in full.</p>
      <h3 style="margin-top:22px">3. What we share</h3>
      <p>Nothing is sold or shared with third parties, except the calls required to run your services (Meta WhatsApp Cloud API, your SMM panel API, your chosen payment gateway).</p>
      <h3 style="margin-top:22px">4. Retention</h3>
      <p>Active account data is kept while your subscription runs. After expiry, data is retained for 30 days and then permanently deleted on request or by scheduled cleanup.</p>
      <h3 style="margin-top:22px">5. Contact</h3>
      <p>Privacy questions: support@smmpackages.com</p>
      <p style="margin-top:22px;color:var(--text-muted);font-size:.85rem">Last updated: <?= date('F Y') ?></p>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
