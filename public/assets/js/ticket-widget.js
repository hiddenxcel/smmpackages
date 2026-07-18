/* SMM Packages — embeddable AI ticket widget.
 * Reads window.SMMTicket.endpoint (set by the embed snippet), renders a chat
 * bubble, and posts customer messages to the widget API, showing AI replies. */
(function () {
  var cfg = window.SMMTicket || {};
  if (!cfg.endpoint) return;

  var ticketId = null;
  var GREEN = '#0EA472';

  var css = '\
  .smmw-btn{position:fixed;bottom:20px;right:20px;width:58px;height:58px;border-radius:50%;background:' + GREEN + ';color:#fff;border:none;font-size:24px;cursor:pointer;box-shadow:0 6px 20px rgba(0,0,0,.25);z-index:99999}\
  .smmw-box{position:fixed;bottom:88px;right:20px;width:340px;max-width:calc(100vw - 32px);height:460px;max-height:calc(100vh - 120px);background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,.25);display:none;flex-direction:column;overflow:hidden;z-index:99999;font-family:system-ui,sans-serif}\
  .smmw-box.open{display:flex}\
  .smmw-head{background:' + GREEN + ';color:#fff;padding:14px 16px;font-weight:600}\
  .smmw-body{flex:1;overflow-y:auto;padding:14px;background:#f6f7f9}\
  .smmw-msg{max-width:82%;padding:9px 13px;border-radius:12px;margin-bottom:8px;font-size:14px;line-height:1.4;white-space:pre-wrap}\
  .smmw-in{background:#fff;border:1px solid #eee;align-self:flex-start;margin-right:auto}\
  .smmw-out{background:#d9fdd3;align-self:flex-end;margin-left:auto}\
  .smmw-foot{display:flex;border-top:1px solid #eee;padding:8px}\
  .smmw-foot input{flex:1;border:none;padding:10px;font-size:14px;outline:none}\
  .smmw-foot button{background:' + GREEN + ';color:#fff;border:none;border-radius:8px;padding:0 16px;cursor:pointer}';

  var style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  var btn = document.createElement('button');
  btn.className = 'smmw-btn';
  btn.innerHTML = '&#128172;';
  document.body.appendChild(btn);

  var box = document.createElement('div');
  box.className = 'smmw-box';
  box.innerHTML =
    '<div class="smmw-head">Support</div>' +
    '<div class="smmw-body" id="smmwBody"><div class="smmw-msg smmw-in">Hi! How can we help you today?</div></div>' +
    '<div class="smmw-foot"><input id="smmwInput" placeholder="Type a message..."><button id="smmwSend">Send</button></div>';
  document.body.appendChild(box);

  var body = box.querySelector('#smmwBody');
  var input = box.querySelector('#smmwInput');

  btn.addEventListener('click', function () { box.classList.toggle('open'); input.focus(); });

  function addMsg(text, cls) {
    var d = document.createElement('div');
    d.className = 'smmw-msg ' + cls;
    d.textContent = text;
    body.appendChild(d);
    body.scrollTop = body.scrollHeight;
  }

  function send() {
    var text = input.value.trim();
    if (!text) return;
    addMsg(text, 'smmw-out');
    input.value = '';
    addMsg('…', 'smmw-in');
    var typing = body.lastChild;

    fetch(cfg.endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text, ticket_id: ticketId })
    }).then(function (r) { return r.json(); }).then(function (res) {
      typing.remove();
      if (res && res.ticket_id) ticketId = res.ticket_id;
      addMsg(res && res.ai_reply ? res.ai_reply : 'Thanks! Our team will get back to you shortly.', 'smmw-in');
    }).catch(function () {
      typing.remove();
      addMsg('Sorry, something went wrong. Please try again.', 'smmw-in');
    });
  }

  box.querySelector('#smmwSend').addEventListener('click', send);
  input.addEventListener('keydown', function (e) { if (e.key === 'Enter') send(); });
})();
