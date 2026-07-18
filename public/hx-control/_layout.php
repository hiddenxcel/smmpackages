<?php
// hx-control shell. Expects _boot.php + $admin. Optional $pageTitle, $activeNav.
$pageTitle = $pageTitle ?? 'Control';
$activeNav = $activeNav ?? '';

$navItems = [
    'dashboard'     => ['index.php', 'fa-chart-line', 'Dashboard'],
    'tenants'       => ['tenants.php', 'fa-users', 'Tenants'],
    'subscriptions' => ['subscriptions.php', 'fa-repeat', 'Subscriptions'],
    'payments'      => ['payments.php', 'fa-receipt', 'Payments'],
    'plans'         => ['plans.php', 'fa-box', 'Plans'],
    'numbers'       => ['numbers.php', 'fa-phone', 'Numbers'],
    'reports'       => ['reports.php', 'fa-chart-pie', 'Reports'],
    'activity'      => ['activity.php', 'fa-list-check', 'Activity Log'],
    'settings'      => ['settings.php', 'fa-gear', 'Settings'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> — HX Control</title>
<meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Nunito:wght@700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">
<script>document.documentElement.setAttribute('data-theme','dark');</script>
</head>
<body>
<div class="dash-layout">
  <aside class="sidebar" id="sidebar">
    <button class="side-toggle" id="sideToggle" aria-label="Collapse menu"><i class="fa-solid fa-chevron-left"></i></button>
    <a class="nav-logo" href="index.php" style="color:#fff">
      <span class="logo-mark" style="background:var(--accent)"><i class="fa-solid fa-shield-halved"></i></span>
      <span>HX Control</span>
    </a>
    <div class="sidebar-group">Platform</div>
    <?php foreach ($navItems as $key => [$href, $icon, $label]): ?>
      <a class="side-link <?= $activeNav === $key ? 'active' : '' ?>" href="<?= $href ?>" data-label="<?= htmlspecialchars($label) ?>">
        <i class="fa-solid <?= $icon ?>"></i> <span><?= htmlspecialchars($label) ?></span>
      </a>
    <?php endforeach; ?>
  </aside>
  <div class="dash-main">
    <div class="dash-topbar">
      <div style="display:flex;align-items:center;gap:14px">
        <button class="menu-btn" id="menuBtn"><i class="fa-solid fa-bars"></i></button>
        <strong><?= htmlspecialchars($pageTitle) ?></strong>
      </div>
      <div style="display:flex;align-items:center;gap:12px">
        <span style="color:var(--text-muted);font-size:.9rem"><i class="fa-solid fa-user-shield"></i> <?= htmlspecialchars($admin['username']) ?></span>
        <a class="btn btn-outline" href="logout.php" style="padding:8px 14px"><i class="fa-solid fa-arrow-right-from-bracket"></i> Log out</a>
      </div>
    </div>
    <div class="dash-content">
