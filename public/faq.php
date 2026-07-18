<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = Lang::t('nav_faq');
$activeNav = 'faq';
require __DIR__ . '/includes/layout_header.php';
?>

<section class="section">
  <div class="container" style="max-width:760px">
    <h2 class="section-title"><?php e('faq_title'); ?></h2>
    <div style="margin-top:38px">
      <?php for ($i = 1; $i <= 5; $i++): ?>
      <details class="faq-item" <?= $i === 1 ? 'open' : '' ?>>
        <summary><?php e("faq_q{$i}"); ?></summary>
        <div class="faq-body"><?php e("faq_a{$i}"); ?></div>
      </details>
      <?php endfor; ?>
    </div>

    <div style="text-align:center;margin-top:44px">
      <a class="btn btn-primary btn-lg" href="<?= htmlspecialchars($config['links']['whatsapp_url'] ?? '#') ?>">
        <i class="fa-brands fa-whatsapp"></i> <?php e('cta_whatsapp'); ?>
      </a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
