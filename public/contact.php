<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = Lang::t('footer_contact');
require __DIR__ . '/includes/layout_header.php';
?>

<section class="section">
  <div class="container" style="max-width:640px">
    <h2 class="section-title"><?php e('footer_contact'); ?></h2>
    <p class="section-sub"><?php e('cta_final_sub'); ?></p>

    <div class="grid grid-2" style="margin-top:24px">
      <a class="card" style="text-align:center;color:inherit" href="<?= htmlspecialchars($config['links']['whatsapp_url'] ?? '#') ?>">
        <div class="card-icon" style="margin:0 auto"><i class="fa-brands fa-whatsapp"></i></div>
        <h3>WhatsApp</h3>
        <p><?php e('cta_whatsapp'); ?></p>
      </a>
      <a class="card" style="text-align:center;color:inherit" href="mailto:support@smmpackages.com">
        <div class="card-icon accent" style="margin:0 auto"><i class="fa-solid fa-envelope"></i></div>
        <h3>Email</h3>
        <p>support@smmpackages.com</p>
      </a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
