<?php
// Expects bootstrap.php included. Optional vars: $pageTitle, $activeNav.
$pageTitle = $pageTitle ?? Lang::t('brand');
$activeNav = $activeNav ?? '';
$isAuthed = TenantAuth::check();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(Lang::current()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars(Lang::t('brand')) ?></title>
<meta name="description" content="<?= htmlspecialchars(Lang::t('tagline')) ?>">
<meta name="theme-color" content="#0EA472">
<link rel="manifest" href="assets/pwa/manifest.json">
<link rel="icon" type="image/png" href="assets/img/icon-192.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Nunito:wght@700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">
<script>
(function(){
  var t=localStorage.getItem('theme');
  if(t==='dark'){document.documentElement.setAttribute('data-theme','dark');}
  // Mark JS active so fade-up elements start hidden (they stay visible without JS).
  document.documentElement.className += ' js';
})();
</script>
</head>
<body>

<nav class="navbar" id="mainNav">
  <div class="container nav-inner">
    <a class="nav-logo" href="home.php">
      <span class="logo-mark"><i class="fa-brands fa-whatsapp"></i></span>
      <?= htmlspecialchars(Lang::t('brand')) ?>
    </a>

    <ul class="nav-links">
      <li><a href="home.php#services"><?php e('nav_services'); ?></a></li>
      <li><a href="pricing.php"<?= $activeNav==='pricing' ? ' style="color:var(--primary)"' : '' ?>><?php e('nav_pricing'); ?></a></li>
      <li><a href="how-it-works.php"<?= $activeNav==='how' ? ' style="color:var(--primary)"' : '' ?>><?php e('nav_how'); ?></a></li>
      <li><a href="faq.php"<?= $activeNav==='faq' ? ' style="color:var(--primary)"' : '' ?>><?php e('nav_faq'); ?></a></li>
    </ul>

    <div class="nav-actions">
      <?php $langNames = ['en' => 'English', 'fr' => 'Français', 'sw' => 'Kiswahili']; $curLng = Lang::current(); ?>
      <div class="lang-menu" id="langMenu">
        <button class="lang-btn" id="langBtn" aria-haspopup="true" aria-expanded="false" aria-label="Language">
          <i class="fa-solid fa-globe"></i>
          <span class="lang-cur"><?= strtoupper($curLng) ?></span>
          <i class="fa-solid fa-chevron-down lang-caret"></i>
        </button>
        <div class="lang-drop" id="langDrop">
          <?php foreach (Lang::SUPPORTED as $lng): ?>
            <a href="<?= htmlspecialchars(Lang::switchUrl($lng)) ?>" class="<?= $curLng === $lng ? 'active' : '' ?>">
              <span class="lang-code"><?= strtoupper($lng) ?></span>
              <span class="lang-full"><?= htmlspecialchars($langNames[$lng] ?? strtoupper($lng)) ?></span>
              <?php if ($curLng === $lng): ?><i class="fa-solid fa-check lang-tick"></i><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <button class="theme-toggle" id="themeToggle" aria-label="Theme"><i class="fa-solid fa-moon"></i></button>
      <?php if ($isAuthed): ?>
        <a class="btn btn-primary" href="index.php"><?php e('nav_dashboard'); ?></a>
      <?php else: ?>
        <a class="btn btn-outline" href="login.php"><?php e('nav_login'); ?></a>
        <a class="btn btn-primary" href="register.php"><?php e('nav_start_free'); ?></a>
      <?php endif; ?>
      <button class="nav-toggle" id="navToggle" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
    </div>
  </div>

  <div class="nav-mobile" id="navMobile">
    <a href="home.php#services"><?php e('nav_services'); ?></a>
    <a href="pricing.php"><?php e('nav_pricing'); ?></a>
    <a href="how-it-works.php"><?php e('nav_how'); ?></a>
    <a href="faq.php"><?php e('nav_faq'); ?></a>
    <?php if ($isAuthed): ?>
      <a href="index.php"><?php e('nav_dashboard'); ?></a>
    <?php else: ?>
      <a href="login.php"><?php e('nav_login'); ?></a>
      <a href="register.php" style="color:var(--primary);font-weight:700"><?php e('nav_start_free'); ?></a>
    <?php endif; ?>
    <div style="display:flex;align-items:center;gap:12px;margin-top:8px;padding-top:12px;border-top:1px solid var(--glass-border)">
      <div class="lang-switch">
        <?php foreach (Lang::SUPPORTED as $lng): ?>
          <a href="<?= htmlspecialchars(Lang::switchUrl($lng)) ?>" class="<?= Lang::current()===$lng ? 'active' : '' ?>"><?= strtoupper($lng) ?></a>
        <?php endforeach; ?>
      </div>
      <button class="theme-toggle" id="themeToggleMobile" aria-label="Theme"><i class="fa-solid fa-moon"></i></button>
    </div>
  </div>
</nav>
