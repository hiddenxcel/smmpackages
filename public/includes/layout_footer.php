<?php
// Public footer + shared JS. Expects bootstrap + layout_header included.
$supportWhatsApp = $config['links']['whatsapp_url']  ?? '#';
$supportTelegram = $config['links']['telegram_url']  ?? '';
$supportEmail    = $config['links']['support_email'] ?? 'support@smmpackages.com';
?>
<footer class="footer">
  <div class="container">

    <!-- Newsletter strip -->
    <div class="footer-cta">
      <div class="footer-cta-copy">
        <h3><?php e('footer_news_title'); ?></h3>
        <p><?php e('footer_news_sub'); ?></p>
      </div>
      <form class="footer-news" id="footerNews" onsubmit="return false">
        <div class="footer-news-field">
          <i class="fa-solid fa-envelope"></i>
          <input type="email" required placeholder="<?= htmlspecialchars(Lang::t('footer_news_ph'), ENT_QUOTES) ?>" aria-label="<?= htmlspecialchars(Lang::t('footer_news_ph'), ENT_QUOTES) ?>">
        </div>
        <button class="btn btn-primary" type="submit"><?php e('footer_news_btn'); ?> <i class="fa-solid fa-arrow-right"></i></button>
      </form>
      <p class="footer-news-ok" id="footerNewsOk"><i class="fa-solid fa-circle-check"></i> <?php e('footer_news_ok'); ?></p>
    </div>

    <div class="footer-grid">
      <div class="footer-brandcol">
        <a class="nav-logo" href="home.php" style="color:#fff">
          <span class="logo-mark"><i class="fa-brands fa-whatsapp"></i></span>
          <?= htmlspecialchars(Lang::t('brand')) ?>
        </a>
        <p class="footer-tag"><?= htmlspecialchars(Lang::t('tagline')) ?></p>

        <span class="footer-status"><span class="footer-status-dot"></span> <?php e('footer_status'); ?></span>

        <div class="footer-social" aria-label="<?= htmlspecialchars(Lang::t('footer_follow'), ENT_QUOTES) ?>">
          <?php if ($supportWhatsApp !== '#' && $supportWhatsApp !== ''): ?>
          <a href="<?= htmlspecialchars($supportWhatsApp) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
          <?php endif; ?>
          <?php if ($supportTelegram !== ''): ?>
          <a href="<?= htmlspecialchars($supportTelegram) ?>" target="_blank" rel="noopener" aria-label="Telegram"><i class="fa-brands fa-telegram"></i></a>
          <?php endif; ?>
          <a href="mailto:<?= htmlspecialchars($supportEmail) ?>" aria-label="Email"><i class="fa-solid fa-envelope"></i></a>
          <a href="contact.php" aria-label="<?= htmlspecialchars(Lang::t('footer_contact'), ENT_QUOTES) ?>"><i class="fa-solid fa-headset"></i></a>
        </div>
      </div>

      <div>
        <h4><?php e('footer_products'); ?></h4>
        <ul>
          <li><a href="pricing.php"><?php e('svc_order_title'); ?></a></li>
          <li><a href="pricing.php"><?php e('svc_support_title'); ?></a></li>
          <li><a href="pricing.php"><?php e('svc_ai_title'); ?></a></li>
          <li><a href="pricing.php"><?php e('svc_number_title'); ?></a></li>
        </ul>
      </div>
      <div>
        <h4><?php e('footer_company'); ?></h4>
        <ul>
          <li><a href="about.php"><?php e('nav_how'); ?></a></li>
          <li><a href="faq.php"><?php e('nav_faq'); ?></a></li>
          <li><a href="contact.php"><?php e('footer_contact'); ?></a></li>
        </ul>
      </div>
      <div>
        <h4><?php e('footer_legal'); ?></h4>
        <ul>
          <li><a href="terms.php">Terms</a></li>
          <li><a href="privacy.php">Privacy</a></li>
        </ul>
      </div>
    </div>

    <!-- Payment strip -->
    <div class="footer-pay">
      <span class="footer-pay-note"><?php e('footer_pay_note'); ?></span>
      <span class="footer-pay-chips">
        <span class="pay-chip"><i class="fa-brands fa-bitcoin"></i> USDT</span>
        <span class="pay-chip"><i class="fa-solid fa-mobile-screen"></i> M-Pesa</span>
        <span class="pay-chip"><i class="fa-brands fa-cc-visa"></i> Card</span>
        <span class="pay-chip"><i class="fa-solid fa-coins"></i> Crypto</span>
      </span>
    </div>

    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> HiddenXcel. <?php e('footer_rights'); ?></span>
      <span class="footer-made"><i class="fa-solid fa-heart"></i> <?php e('footer_made'); ?></span>
    </div>
  </div>
</footer>

<script>
/* Mobile nav */
var navToggle = document.getElementById('navToggle');
if (navToggle) navToggle.addEventListener('click', function(){
  document.getElementById('navMobile').classList.toggle('open');
});

/* Theme toggle (desktop + mobile share one handler) */
function smmToggleTheme(){
  var root = document.documentElement;
  /* Smooth light sweep while the palette flips. */
  root.classList.add('theme-fade');
  var dark = root.getAttribute('data-theme') === 'dark';
  if (dark) { root.removeAttribute('data-theme'); localStorage.setItem('theme','light'); }
  else { root.setAttribute('data-theme','dark'); localStorage.setItem('theme','dark'); }
  clearTimeout(window.__tf); window.__tf = setTimeout(function(){ root.classList.remove('theme-fade'); }, 600);
}
['themeToggle','themeToggleMobile'].forEach(function(id){
  var el = document.getElementById(id);
  if (el) el.addEventListener('click', smmToggleTheme);
});

/* Footer newsletter — client-side confirmation (no backend endpoint yet) */
var footerNews = document.getElementById('footerNews');
if (footerNews) footerNews.addEventListener('submit', function(){
  var input = footerNews.querySelector('input');
  if (!input.checkValidity()) { input.reportValidity(); return; }
  footerNews.classList.add('sent');
  var ok = document.getElementById('footerNewsOk');
  if (ok) ok.classList.add('show');
  input.value = '';
});

/* Language dropdown (landing navbar) */
var langMenu = document.getElementById('langMenu');
var langBtn = document.getElementById('langBtn');
if (langMenu && langBtn) {
  langBtn.addEventListener('click', function(e){
    e.stopPropagation();
    var open = langMenu.classList.toggle('open');
    langBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  document.addEventListener('click', function(){
    langMenu.classList.remove('open');
    langBtn.setAttribute('aria-expanded', 'false');
  });
}

/* Navbar glass effect on scroll */
var mainNav = document.getElementById('mainNav');
if (mainNav) {
  window.addEventListener('scroll', function(){
    if (window.scrollY > 30) { mainNav.classList.add('scrolled'); }
    else { mainNav.classList.remove('scrolled'); }
  }, { passive: true });
}

/* Intersection Observer — fade-up animations */
if ('IntersectionObserver' in window) {
  var fadeEls = document.querySelectorAll('.fade-up');
  var fadeObs = new IntersectionObserver(function(entries){
    entries.forEach(function(e){
      if (e.isIntersecting) {
        e.target.classList.add('visible');
        fadeObs.unobserve(e.target);
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
  fadeEls.forEach(function(el){ fadeObs.observe(el); });
  /* Reveal above-the-fold items right away so the hero is never blank. */
  requestAnimationFrame(function(){
    fadeEls.forEach(function(el){
      if (el.getBoundingClientRect().top < window.innerHeight) { el.classList.add('visible'); }
    });
  });
  /* Safety net: never leave content hidden if something goes wrong. */
  setTimeout(function(){
    document.querySelectorAll('.fade-up:not(.visible)').forEach(function(el){ el.classList.add('visible'); });
  }, 2500);
} else {
  document.querySelectorAll('.fade-up').forEach(function(el){ el.classList.add('visible'); });
}

/* Demo tabs (landing) */
document.querySelectorAll('.tab-btn').forEach(function(btn){
  btn.addEventListener('click', function(){
    document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
    document.querySelectorAll('.tab-pane').forEach(function(p){ p.classList.remove('active'); });
    btn.classList.add('active');
    var pane = document.getElementById(btn.dataset.tab);
    if (pane) pane.classList.add('active');
  });
});

/* PWA service worker */
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('assets/pwa/sw.js').catch(function(){});
}
</script>
</body>
</html>
