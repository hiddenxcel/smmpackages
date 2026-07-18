<?php
/**
 * header.php — Layout wrapper with glassmorphism sidebar.
 *
 * Expects: $pageTitle (string), $activePage (string)
 *          $appName (string, optional)
 */
$pageTitle = $pageTitle ?? 'Dashboard';
$activePage = $activePage ?? 'dashboard';
$appName = $appName ?? 'NovaPay';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars($appName) ?></title>
  <meta name="description" content="<?= htmlspecialchars($appName) ?> admin dashboard">
  <meta name="theme-color" content="#047857">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <script>
    (function(){var t=localStorage.getItem('theme');if(t==='dark'){document.documentElement.setAttribute('data-theme','dark');}})();
  </script>
</head>
<body>

<div class="layout">

  <?php require __DIR__ . '/sidebar.php'; ?>

  <main class="main-content">
    <div class="content-header">
      <div style="display:flex;align-items:center;gap:14px">
        <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">
          <i class="fa-solid fa-bars"></i>
        </button>
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
      </div>
      <div class="header-actions">
        <button class="theme-switch" id="themeSwitch" aria-label="Toggle theme">
          <i class="fa-solid fa-moon"></i>
        </button>
      </div>
    </div>
    <div class="content-body">
