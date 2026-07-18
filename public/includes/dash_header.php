<?php
// Dashboard shell opener. Expects: bootstrap.php included, $tenant (authed row).
// Optional: $pageTitle, $activeSide.
$pageTitle = $pageTitle ?? Lang::t('nav_dashboard');
$activeSide = $activeSide ?? '';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(Lang::current()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars(Lang::t('brand')) ?></title>
<meta name="theme-color" content="#0EA472">
<link rel="manifest" href="assets/pwa/manifest.json">
<link rel="icon" type="image/png" href="assets/img/icon-192.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Nunito:wght@700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">
<script>
(function(){var t=localStorage.getItem('theme');if(t==='dark'){document.documentElement.setAttribute('data-theme','dark');}})();
</script>
</head>
<body>
<div class="dash-layout">
  <?php require __DIR__ . '/nav.php'; ?>

  <div class="dash-main">
    <div class="dash-topbar">
      <div style="display:flex;align-items:center;gap:14px;flex:1;min-width:0">
        <button class="menu-btn" id="menuBtn" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-search">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="search" id="quickFind" placeholder="<?= htmlspecialchars(Lang::t('search_ph')) ?>" autocomplete="off">
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:12px">
        <div class="lang-switch">
          <?php foreach (Lang::SUPPORTED as $lng): ?>
            <a href="<?= htmlspecialchars(Lang::switchUrl($lng)) ?>" class="<?= Lang::current()===$lng ? 'active' : '' ?>"><?= strtoupper($lng) ?></a>
          <?php endforeach; ?>
        </div>
        <button class="theme-toggle" id="themeToggle" aria-label="Theme"><i class="fa-solid fa-moon"></i></button>
        <div class="avatar-menu" id="avatarMenu">
          <button class="avatar-btn" id="avatarBtn" aria-label="Account">
            <span class="avatar-circle"><?= htmlspecialchars(mb_strtoupper(mb_substr($tenant['business_name'] ?? 'U', 0, 1))) ?></span>
            <span class="avatar-name"><?= htmlspecialchars($tenant['business_name'] ?? '') ?></span>
            <i class="fa-solid fa-chevron-down" style="font-size:.7rem"></i>
          </button>
          <div class="avatar-drop" id="avatarDrop">
            <a href="profile.php"><i class="fa-solid fa-user"></i> <?php e('side_profile'); ?></a>
            <a href="subscription.php"><i class="fa-solid fa-credit-card"></i> <?php e('side_subscription'); ?></a>
            <a href="logout.php" class="danger"><i class="fa-solid fa-arrow-right-from-bracket"></i> <?php e('nav_logout'); ?></a>
          </div>
        </div>
      </div>
    </div>
    <div class="dash-content">
