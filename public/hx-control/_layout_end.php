    </div>
  </div>
</div>
<script>
var menuBtn=document.getElementById('menuBtn');
if(menuBtn)menuBtn.addEventListener('click',function(){document.querySelector('.sidebar').classList.toggle('open');});
var sidebar=document.getElementById('sidebar'), sideToggle=document.getElementById('sideToggle');
if(sidebar && localStorage.getItem('sidebar')==='collapsed'){sidebar.classList.add('collapsed');}
if(sideToggle)sideToggle.addEventListener('click',function(){
  sidebar.classList.toggle('collapsed');
  localStorage.setItem('sidebar', sidebar.classList.contains('collapsed')?'collapsed':'expanded');
});
</script>
</body>
</html>
