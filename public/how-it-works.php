<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = Lang::t('nav_how');
$activeNav = 'how';
require __DIR__ . '/includes/layout_header.php';
?>

<section class="section">
  <div class="container">
    <h2 class="section-title"><?php e('steps_title'); ?></h2>
    <p class="section-sub"><?php e('tagline'); ?></p>

    <div class="grid grid-4" style="margin-top:44px">
      <?php for ($i = 1; $i <= 4; $i++): ?>
      <div class="step">
        <div class="step-num"><?= $i ?></div>
        <h3><?php e("step{$i}_title"); ?></h3>
        <p><?php e("step{$i}_body"); ?></p>
      </div>
      <?php endfor; ?>
    </div>

    <div style="margin-top:64px">
      <h2 class="section-title" style="font-size:1.5rem"><?php e('panels_title'); ?></h2>
      <p class="section-sub"><?php e('panels_sub'); ?></p>
      <div class="panel-logos">
        <span class="chip"><i class="fa-solid fa-circle-check"></i> PerfectPanel</span>
        <span class="chip"><i class="fa-solid fa-circle-check"></i> Rental Panel</span>
        <span class="chip"><i class="fa-solid fa-circle-check"></i> SMM API v2 (Custom)</span>
        <span class="chip"><i class="fa-solid fa-circle-check"></i> KuzaPanel</span>
      </div>
    </div>

    <div style="margin-top:64px">
      <div class="cta-band">
        <h2><?php e('cta_final_title'); ?></h2>
        <p><?php e('cta_final_sub'); ?></p>
        <a class="btn btn-accent btn-lg" href="register.php"><i class="fa-solid fa-rocket"></i> <?php e('cta_start_free'); ?></a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
