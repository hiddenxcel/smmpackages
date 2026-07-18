/**
 * sidebar.js — Toggle, Mobile slide, Keyboard navigation, Theme switch.
 * Zero dependencies. Vanilla JS only.
 */
(function () {
  'use strict';

  /* ---------- DOM refs ---------- */
  var sidebar      = document.getElementById('sidebar');
  var toggle       = document.getElementById('sidebarToggle');
  var mobileBtn    = document.getElementById('mobileMenuBtn');
  var overlay      = document.getElementById('sidebarOverlay');
  var themeSwitch  = document.getElementById('themeSwitch');
  var navItems     = sidebar ? sidebar.querySelectorAll('.nav-item[href]') : [];

  var STORAGE_KEY  = 'sidebar_collapsed';
  var THEME_KEY    = 'theme';

  /* ---------- Sidebar toggle (desktop) ---------- */
  function isDesktop() {
    return window.innerWidth > 900;
  }

  function setCollapsed(collapsed) {
    if (!sidebar) return;
    if (collapsed) {
      sidebar.classList.remove('expanded');
    } else {
      sidebar.classList.add('expanded');
    }
    try { localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0'); } catch (e) {}
  }

  function initSidebarState() {
    if (!sidebar || !isDesktop()) return;
    try {
      var saved = localStorage.getItem(STORAGE_KEY);
      if (saved === '1') {
        sidebar.classList.remove('expanded');
      } else {
        sidebar.classList.add('expanded');
      }
    } catch (e) {
      sidebar.classList.add('expanded');
    }
  }

  if (toggle) {
    toggle.addEventListener('click', function () {
      if (isDesktop()) {
        var isExpanded = sidebar.classList.contains('expanded');
        setCollapsed(isExpanded);
      } else {
        closeMobile();
      }
    });
  }

  /* ---------- Mobile slide ---------- */
  function openMobile() {
    if (!sidebar || !overlay) return;
    sidebar.classList.add('mobile-open');
    overlay.classList.add('visible');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    // Focus first nav item
    var first = sidebar.querySelector('.nav-item[href]');
    if (first) first.focus();
  }

  function closeMobile() {
    if (!sidebar || !overlay) return;
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('visible');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  if (mobileBtn) {
    mobileBtn.addEventListener('click', function () {
      if (sidebar.classList.contains('mobile-open')) {
        closeMobile();
      } else {
        openMobile();
      }
    });
  }

  if (overlay) {
    overlay.addEventListener('click', closeMobile);
  }

  /* ---------- Keyboard navigation ---------- */
  function getVisibleItems() {
    return Array.prototype.slice.call(navItems).filter(function (el) {
      return el.offsetParent !== null;
    });
  }

  function focusItem(index) {
    var items = getVisibleItems();
    if (items.length === 0) return;
    if (index < 0) index = items.length - 1;
    if (index >= items.length) index = 0;
    items[index].focus();
  }

  function handleNavKeydown(e) {
    var items = getVisibleItems();
    var idx = items.indexOf(document.activeElement);
    if (idx === -1) return;

    switch (e.key) {
      case 'ArrowDown':
      case 'ArrowRight':
        e.preventDefault();
        focusItem(idx + 1);
        break;
      case 'ArrowUp':
      case 'ArrowLeft':
        e.preventDefault();
        focusItem(idx - 1);
        break;
      case 'Home':
        e.preventDefault();
        focusItem(0);
        break;
      case 'End':
        e.preventDefault();
        focusItem(items.length - 1);
        break;
      case 'Escape':
        if (!isDesktop()) closeMobile();
        break;
    }
  }

  if (sidebar) {
    sidebar.addEventListener('keydown', handleNavKeydown);
  }

  /* ---------- Theme toggle ---------- */
  function setTheme(dark) {
    if (dark) {
      document.documentElement.setAttribute('data-theme', 'dark');
    } else {
      document.documentElement.removeAttribute('data-theme');
    }
    if (themeSwitch) {
      var icon = themeSwitch.querySelector('i');
      if (icon) {
        icon.className = dark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
      }
    }
    try { localStorage.setItem(THEME_KEY, dark ? 'dark' : 'light'); } catch (e) {}
  }

  function initTheme() {
    try {
      var saved = localStorage.getItem(THEME_KEY);
      setTheme(saved === 'dark');
    } catch (e) {}
  }

  if (themeSwitch) {
    themeSwitch.addEventListener('click', function () {
      var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
      setTheme(!isDark);
    });
  }

  /* ---------- Resize handler ---------- */
  var resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      if (isDesktop()) {
        closeMobile();
        document.body.style.overflow = '';
      }
    }, 100);
  });

  /* ---------- Close mobile on nav click ---------- */
  navItems.forEach(function (item) {
    item.addEventListener('click', function () {
      if (!isDesktop()) closeMobile();
    });
  });

  /* ---------- Init ---------- */
  initSidebarState();
  initTheme();

})();
