    </div><!-- /dash-content -->
  </div><!-- /dash-main -->
</div><!-- /dash-layout -->

<script>
var themeToggle = document.getElementById('themeToggle');
if (themeToggle) themeToggle.addEventListener('click', function(){
  var root = document.documentElement;
  /* Smooth light sweep: enable cross-fade transitions just for the switch. */
  root.classList.add('theme-fade');
  var dark = root.getAttribute('data-theme') === 'dark';
  if (dark) { root.removeAttribute('data-theme'); localStorage.setItem('theme','light'); }
  else { root.setAttribute('data-theme','dark'); localStorage.setItem('theme','dark'); }
  clearTimeout(window.__tf); window.__tf = setTimeout(function(){ root.classList.remove('theme-fade'); }, 600);
});
var menuBtn = document.getElementById('menuBtn');
if (menuBtn) menuBtn.addEventListener('click', function(e){
  e.stopPropagation();
  var open = document.querySelector('.sidebar').classList.toggle('open');
  document.body.classList.toggle('nav-open', open);
});
/* Tap the scrim (anywhere outside the drawer) to close it. */
document.addEventListener('click', function(e){
  var sb = document.querySelector('.sidebar');
  if (sb && sb.classList.contains('open') && !sb.contains(e.target)) {
    sb.classList.remove('open');
    document.body.classList.remove('nav-open');
  }
});

/* Collapsible sidebar — remembers its state across pages. */
var sidebar = document.getElementById('sidebar');
var sideToggle = document.getElementById('sideToggle');
if (sidebar && localStorage.getItem('sidebar') === 'collapsed') {
  sidebar.classList.add('collapsed');
}
if (sideToggle) sideToggle.addEventListener('click', function(){
  sidebar.classList.toggle('collapsed');
  localStorage.setItem('sidebar', sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded');
});

/* Avatar dropdown. */
var avatarBtn = document.getElementById('avatarBtn');
var avatarDrop = document.getElementById('avatarDrop');
if (avatarBtn && avatarDrop) {
  avatarBtn.addEventListener('click', function(e){ e.stopPropagation(); avatarDrop.classList.toggle('open'); });
  document.addEventListener('click', function(){ avatarDrop.classList.remove('open'); });
}

/* Quick find — Enter routes to Orders search (the main searchable list). */
var quickFind = document.getElementById('quickFind');
if (quickFind) quickFind.addEventListener('keydown', function(e){
  if (e.key === 'Enter' && quickFind.value.trim() !== '') {
    location.href = 'orders.php?q=' + encodeURIComponent(quickFind.value.trim());
  }
});

/* Collapsible sidebar GROUPS — each group header toggles its items; state saved. */
(function(){
  var collapsed = JSON.parse(localStorage.getItem('sidegroups') || '{}');
  document.querySelectorAll('.sidebar-group[data-group]').forEach(function(hdr){
    var key = hdr.dataset.group;
    var items = hdr.nextElementSibling;
    if (!items || !items.classList.contains('side-group-items')) return;
    if (collapsed[key]) { hdr.classList.add('collapsed'); items.classList.add('hidden'); }
    hdr.addEventListener('click', function(){
      if (sidebar && sidebar.classList.contains('collapsed')) return; // no-op when rail is collapsed
      var isHidden = items.classList.toggle('hidden');
      hdr.classList.toggle('collapsed', isHidden);
      collapsed[key] = isHidden;
      localStorage.setItem('sidegroups', JSON.stringify(collapsed));
    });
  });
})();

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('assets/pwa/sw.js').catch(function(){});
}
</script>
</body>
</html>
