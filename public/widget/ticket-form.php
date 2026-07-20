<?php
/**
 * Public embeddable ticket FORM (SMMGen-style).
 * The reseller drops this into their panel/site via an <iframe> or link:
 *   <iframe src=".../widget/ticket-form.php?t=<tenant_id>" ...></iframe>
 *
 * Category (AI / Human) -> Subcategory -> Order ID -> Message -> Submit.
 * It POSTs to ticket.php?t=<tenant_id> (action=create) and shows the reply.
 * The page is standalone (no login) — the tenant id is the public key.
 */

require_once __DIR__ . '/../../app/lib/DB.php';
require_once __DIR__ . '/../../app/services/TicketService.php';

$config = require __DIR__ . '/../../config/config.php';
DB::connect($config['db']);

$tenantId = (int) ($_GET['t'] ?? 0);
$subs = TicketService::SUBCATEGORIES;

// The reseller's brand name, shown in the header (public, safe).
$brand = 'Support';
if ($tenantId > 0) {
    $stmt = DB::conn()->prepare('SELECT business_name FROM tenants WHERE id = ? AND status = "active"');
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch();
    if ($row) { $brand = $row['business_name']; }
}

$endpoint = 'ticket.php?t=' . $tenantId;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Support Tickets — <?= htmlspecialchars($brand) ?></title>
<style>
  :root{
    --bg:#0f1424; --card:#161d33; --card2:#1c2440; --line:#28304f;
    --ink:#e8ecf7; --muted:#8b93b3; --accent:#6d4dff; --accent2:#8a6dff;
    --ok:#1db981; --radius:14px;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);
    font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;padding:18px}
  .wrap{max-width:520px;margin:0 auto}
  .head{display:flex;align-items:center;gap:10px;margin-bottom:16px}
  .head h1{font-size:19px;margin:0}
  .head .dot{width:34px;height:34px;border-radius:9px;background:linear-gradient(135deg,var(--accent),var(--accent2));
    display:grid;place-items:center;font-size:17px}
  .card{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:18px;margin-bottom:14px}
  label{display:block;font-size:13px;color:var(--muted);margin:14px 0 6px;font-weight:600}
  label:first-child{margin-top:0}
  select,input,textarea{width:100%;background:var(--card2);border:1px solid var(--line);color:var(--ink);
    border-radius:10px;padding:11px 12px;font-size:14px;outline:none}
  select:focus,input:focus,textarea:focus{border-color:var(--accent)}
  textarea{resize:vertical;min-height:74px}
  .btn{width:100%;margin-top:18px;background:linear-gradient(135deg,var(--accent),var(--accent2));
    color:#fff;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:700;cursor:pointer}
  .btn:disabled{opacity:.6;cursor:default}
  .hint{font-size:12px;color:var(--muted);margin-top:6px}
  .result{display:none;margin-top:2px}
  .result.show{display:block}
  .bubble{background:var(--card2);border:1px solid var(--line);border-radius:10px;padding:12px 14px;
    font-size:14px;line-height:1.5;margin-bottom:10px;white-space:pre-wrap}
  .bubble.ai{border-left:3px solid var(--accent)}
  .bubble.sys{border-left:3px solid var(--ok);color:var(--ink)}
  .tag{display:inline-block;font-size:11px;font-weight:700;color:#fff;background:var(--accent);
    border-radius:6px;padding:2px 8px;margin-bottom:8px}
  .tag.ai{background:var(--accent)}
  .tag.human{background:#f0a23b}
  .err{color:#ff8080}
  .foot{display:flex;gap:8px;margin-top:10px}
  .foot input{flex:1}
  .foot button{width:auto;padding:0 18px;margin:0}
  .linkbtn{background:none;border:none;color:var(--accent2);cursor:pointer;font-size:13px;margin-top:6px;padding:0}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <div class="dot">&#127915;</div>
    <h1><?= htmlspecialchars($brand) ?> — Support</h1>
  </div>

  <!-- FORM -->
  <div class="card" id="formCard">
    <label>Category</label>
    <select id="category">
      <option value="ai">AI Support</option>
      <option value="human">Human Support</option>
    </select>

    <label>Subcategory</label>
    <select id="subcategory">
      <?php foreach ($subs as $key => $label): ?>
        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
      <?php endforeach; ?>
    </select>

    <label>Order ID</label>
    <input id="orderRef" placeholder="e.g. 548316" autocomplete="off">
    <div class="hint">The order this ticket is about (required).</div>

    <label>Message (optional)</label>
    <textarea id="message" placeholder="Describe your issue..."></textarea>

    <label>Your email / name (optional)</label>
    <input id="customer" placeholder="you@example.com" autocomplete="off">

    <button class="btn" id="submitBtn">Submit ticket</button>
    <div class="hint err" id="formErr" style="display:none"></div>
  </div>

  <!-- RESULT / THREAD -->
  <div class="card result" id="resultCard">
    <div id="thread"></div>
    <div class="foot">
      <input id="reply" placeholder="Type a reply...">
      <button class="btn" id="replyBtn" style="margin:0">Send</button>
    </div>
    <button class="linkbtn" id="newBtn">+ New ticket</button>
  </div>
</div>

<script>
(function(){
  var EP = <?= json_encode($endpoint) ?>;
  var ticketId = null;
  var formCard = document.getElementById('formCard');
  var resultCard = document.getElementById('resultCard');
  var thread = document.getElementById('thread');
  var formErr = document.getElementById('formErr');

  function bubble(text, cls, tag){
    var d = document.createElement('div');
    d.className = 'bubble ' + (cls||'');
    if (tag){ var t=document.createElement('div'); t.className='tag '+(cls||''); t.textContent=tag; d.appendChild(t); }
    var s=document.createElement('div'); s.textContent=text; d.appendChild(s);
    thread.appendChild(d);
  }

  function post(payload){
    return fetch(EP, {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
      .then(function(r){return r.json();});
  }

  document.getElementById('submitBtn').addEventListener('click', function(){
    var order = document.getElementById('orderRef').value.trim();
    formErr.style.display='none';
    if (!order){ formErr.textContent='Order ID is required.'; formErr.style.display='block'; return; }
    var cat = document.getElementById('category').value;
    var payload = {
      action:'create', category:cat,
      subcategory: document.getElementById('subcategory').value,
      order_ref: order,
      message: document.getElementById('message').value.trim(),
      customer: document.getElementById('customer').value.trim()
    };
    var btn=this; btn.disabled=true; btn.textContent='Submitting...';
    post(payload).then(function(res){
      btn.disabled=false; btn.textContent='Submit ticket';
      if (!res || !res.ok){ formErr.textContent=(res&&res.message)||'Something went wrong.'; formErr.style.display='block'; return; }
      ticketId = res.ticket_id;
      thread.innerHTML='';
      var subSel=document.getElementById('subcategory');
      bubble('Order #'+order+' — '+subSel.options[subSel.selectedIndex].text,
             cat==='human'?'human':'ai', cat==='human'?'Human Support':'AI Support');
      if (res.ai_reply){ bubble(res.ai_reply,'ai','AI'); }
      else { bubble(res.message||'Ticket created. Our team will reply shortly.','sys','Ticket #'+ticketId); }
      formCard.style.display='none';
      resultCard.classList.add('show');
    }).catch(function(){
      btn.disabled=false; btn.textContent='Submit ticket';
      formErr.textContent='Network error. Please try again.'; formErr.style.display='block';
    });
  });

  function sendReply(){
    var inp=document.getElementById('reply'); var txt=inp.value.trim();
    if(!txt) return; bubble(txt,'', null); inp.value='';
    post({message:txt, ticket_id:ticketId, customer:document.getElementById('customer').value.trim()})
      .then(function(res){
        if (res && res.ticket_id) ticketId=res.ticket_id;
        bubble(res && res.ai_reply ? res.ai_reply : (res && res.message) || 'Our team will get back to you shortly.',
               'ai', res && res.ai_reply ? 'AI' : 'Ticket');
      }).catch(function(){ bubble('Network error. Please try again.','','') ; });
  }
  document.getElementById('replyBtn').addEventListener('click', sendReply);
  document.getElementById('reply').addEventListener('keydown', function(e){ if(e.key==='Enter') sendReply(); });

  document.getElementById('newBtn').addEventListener('click', function(){
    ticketId=null; thread.innerHTML=''; resultCard.classList.remove('show');
    formCard.style.display='block'; document.getElementById('orderRef').value='';
    document.getElementById('message').value='';
  });
})();
</script>
</body>
</html>
