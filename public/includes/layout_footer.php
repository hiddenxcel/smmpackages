<?php
// Public footer + shared JS. Expects bootstrap + layout_header included.
$supportWhatsApp = $config['links']['whatsapp_url'] ?? '#';
?>
<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <a class="nav-logo" href="home.php" style="color:#fff;margin-bottom:14px">
          <span class="logo-mark"><i class="fa-brands fa-whatsapp"></i></span>
          <?= htmlspecialchars(Lang::t('brand')) ?>
        </a>
        <p style="font-size:.88rem;color:var(--gray-500);max-width:280px;margin-top:12px"><?= htmlspecialchars(Lang::t('tagline')) ?></p>
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
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> HiddenXcel. <?php e('footer_rights'); ?></span>
      <a href="<?= htmlspecialchars($supportWhatsApp) ?>"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
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
