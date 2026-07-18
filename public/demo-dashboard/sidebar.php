<?php
/**
 * sidebar.php — Premium Glassmorphism Sidebar Component
 *
 * Expects: $activePage (string) — current page key, e.g. 'dashboard'
 *          $appName (string, optional) — brand name
 */

$activePage = $activePage ?? 'dashboard';
$appName    = $appName ?? 'NovaPay';

$menu = [
    ['section' => 'Main'],
    [
        'key'  => 'dashboard',
        'icon' => 'fa-solid fa-grid-2',
        'label' => 'Dashboard',
        'href'  => 'index.php',
    ],
    [
        'key'  => 'transactions',
        'icon' => 'fa-solid fa-arrow-right-arrow-left',
        'label' => 'Transactions',
        'href'  => 'transactions.php',
    ],
    [
        'key'  => 'payments',
        'icon' => 'fa-solid fa-credit-card',
        'label' => 'Payments',
        'href'  => 'payments.php',
    ],
    [
        'key'  => 'customers',
        'icon' => 'fa-solid fa-users',
        'label' => 'Customers',
        'href'  => 'customers.php',
    ],
    [
        'key'  => 'wallets',
        'icon' => 'fa-solid fa-wallet',
        'label' => 'Wallets',
        'href'  => 'wallets.php',
    ],
    ['section' => 'Analytics'],
    [
        'key'  => 'reports',
        'icon' => 'fa-solid fa-chart-mixed',
        'label' => 'Reports',
        'href'  => 'reports.php',
    ],
    ['section' => 'System'],
    [
        'key'  => 'settings',
        'icon' => 'fa-solid fa-gear',
        'label' => 'Settings',
        'href'  => 'settings.php',
    ],
    [
        'key'  => 'api',
        'icon' => 'fa-solid fa-code',
        'label' => 'API',
        'href'  => 'api.php',
    ],
    [
        'key'  => 'support',
        'icon' => 'fa-solid fa-life-ring',
        'label' => 'Support',
        'href'  => 'support.php',
    ],
];
?>
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Main navigation">
  <div class="sidebar-inner">

    <!-- Logo -->
    <a class="sidebar-logo" href="index.php">
      <span class="logo-icon"><i class="fa-solid fa-bolt"></i></span>
      <span class="logo-text"><?= htmlspecialchars($appName) ?></span>
    </a>

    <!-- Navigation -->
    <nav class="sidebar-nav" id="sidebarNav">
      <?php foreach ($menu as $item): ?>
        <?php if (isset($item['section'])): ?>
          <div class="nav-section"><?= htmlspecialchars($item['section']) ?></div>
        <?php else: ?>
          <a class="nav-item<?= $activePage === $item['key'] ? ' active' : '' ?>"
             href="<?= htmlspecialchars($item['href']) ?>"
             data-page="<?= htmlspecialchars($item['key']) ?>"
             tabindex="0"
             role="menuitem"
             aria-current="<?= $activePage === $item['key'] ? 'page' : 'false' ?>">
            <span class="nav-icon"><i class="<?= htmlspecialchars($item['icon']) ?>"></i></span>
            <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
            <span class="nav-tooltip" aria-hidden="true"><?= htmlspecialchars($item['label']) ?></span>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>

    <!-- Logout -->
    <a class="nav-item" href="logout.php" role="menuitem" style="margin-top:4px">
      <span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
      <span class="nav-label">Logout</span>
      <span class="nav-tooltip" aria-hidden="true">Logout</span>
    </a>

    <!-- Toggle -->
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar" type="button">
      <span class="toggle-icon"><i class="fa-solid fa-angles-left"></i></span>
      <span class="toggle-label">Collapse</span>
    </button>

  </div>
</aside>

<!-- Mobile overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>
