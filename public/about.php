<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'About';
require __DIR__ . '/includes/layout_header.php';
?>

<section class="section">
  <div class="container" style="max-width:760px">
    <h2 class="section-title">HiddenXcel</h2>
    <p class="section-sub"><?php e('tagline'); ?></p>

    <div class="card" style="margin-top:24px">
      <p style="color:var(--text);line-height:1.8">
        SMM Packages is built by <strong>HiddenXcel</strong> — a team that runs its own SMM panel
        and WhatsApp bot in production. We built this platform because we needed it ourselves:
        a safe, official way to sell and support SMM services over WhatsApp, without QR-scan
        session hacks that get numbers banned.
      </p>
      <p style="margin-top:16px;line-height:1.8">
        Every service here runs on the <strong>official Meta WhatsApp Cloud API</strong>, is sold
        <strong>a la carte</strong> (buy only what you need), and can be paid for in
        <strong>USDT or mobile money</strong>. No credits. No fear banners. Just infrastructure that works.
      </p>
    </div>

    <div style="text-align:center;margin-top:38px">
      <a class="btn btn-primary btn-lg" href="register.php"><i class="fa-solid fa-rocket"></i> <?php e('cta_start_free'); ?></a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
