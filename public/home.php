<?php
require_once __DIR__ . '/includes/bootstrap.php';

$plans = Plan::allActive();
$planMap = [];
foreach ($plans as $p) {
    $planMap[$p['service_key']] = $p;
}

$pageTitle = Lang::t('hero_h1');
require __DIR__ . '/includes/layout_header.php';

$svcMeta = [
    'order_bot'     => ['icon' => 'fa-solid fa-cart-shopping', 'title' => 'svc_order_title',   'body' => 'svc_order_body'],
    'support_bot'   => ['icon' => 'fa-solid fa-headset',       'title' => 'svc_support_title', 'body' => 'svc_support_body'],
    'ai_tickets'    => ['icon' => 'fa-solid fa-robot',         'title' => 'svc_ai_title',      'body' => 'svc_ai_body'],
    'number_rental' => ['icon' => 'fa-solid fa-phone',         'title' => 'svc_number_title',  'body' => 'svc_number_body'],
];
?>

<!-- HERO -->
<header class="hero">
  <div class="hero-orb"></div>
  <div class="container hero-grid">
    <div>
      <div class="hero-badges fade-up">
        <span class="badge"><i class="fa-solid fa-bolt"></i> v22.0 Cloud API</span>
        <span class="badge"><i class="fa-solid fa-shield-halved"></i> <?php e('hero_badge_api'); ?></span>
      </div>
      <h1 class="fade-up delay-1">
        <?php e('hero_h1'); ?><br>
        <span class="gradient-text"><?php e('hero_sub_short'); ?></span>
      </h1>
      <p class="sub fade-up delay-2"><?php e('hero_sub'); ?></p>
      <div class="hero-badges fade-up delay-2" style="margin-bottom:0">
        <span class="badge"><i class="fa-solid fa-lock"></i> <?php e('hero_badge_safe'); ?></span>
        <span class="badge"><i class="fa-solid fa-coins"></i> <?php e('hero_badge_pay'); ?></span>
      </div>
      <div class="hero-ctas fade-up delay-3" style="margin-top:28px">
        <a class="btn btn-primary btn-lg" href="register.php"><i class="fa-solid fa-rocket"></i> <?php e('cta_start_free'); ?></a>
        <a class="btn btn-outline btn-lg" href="#demo"><i class="fa-regular fa-circle-play"></i> <?php e('cta_see_demo'); ?></a>
      </div>
    </div>
    <div class="fade-up delay-2">
      <!-- Live phone: the chat below plays itself (typing → reply → order → loop). -->
      <div class="phone phone-live">
        <div class="phone-screen">
          <div class="statusbar">
            <span id="liveClock">09:41</span>
            <span><i class="fa-solid fa-signal"></i><i class="fa-solid fa-wifi"></i><i class="fa-solid fa-battery-three-quarters"></i></span>
          </div>
          <div class="chat-head">
            <span class="wa-ava"><i class="fa-brands fa-whatsapp"></i></span>
            <span>
              <span class="wa-name">YourPanel · Support Bot</span><br>
              <span class="wa-status">online</span>
            </span>
            <span class="wa-icons"><i class="fa-solid fa-video"></i><i class="fa-solid fa-phone"></i><i class="fa-solid fa-ellipsis-vertical"></i></span>
          </div>
          <div class="chat-body" id="liveChat"></div>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- TRUST BAR -->
<section class="trust-bar">
  <div class="container">
    <div class="trust-grid">
      <div class="trust-item fade-up">
        <div class="trust-num">500+</div>
        <div class="trust-label"><?php e('trust_panels'); ?></div>
      </div>
      <div class="trust-item fade-up delay-1">
        <div class="trust-num">10K+</div>
        <div class="trust-label"><?php e('trust_orders'); ?></div>
      </div>
      <div class="trust-item fade-up delay-2">
        <div class="trust-num">99.9%</div>
        <div class="trust-label"><?php e('trust_uptime'); ?></div>
      </div>
      <div class="trust-item fade-up delay-3">
        <div class="trust-num">24/7</div>
        <div class="trust-label"><?php e('trust_support'); ?></div>
      </div>
    </div>
  </div>
</section>

<!-- THREE WEAPONS -->
<section class="section">
  <div class="container">
    <h2 class="section-title fade-up"><?php e('weapon_title'); ?></h2>
    <p class="section-sub fade-up"><?php e('weapon_sub'); ?></p>
    <div class="grid grid-3">
      <div class="card fade-up">
        <div class="card-icon"><i class="fa-solid fa-shield-halved"></i></div>
        <h3><?php e('weapon_api_title'); ?></h3>
        <p><?php e('weapon_api_body'); ?></p>
      </div>
      <div class="card fade-up delay-1">
        <div class="card-icon accent"><i class="fa-solid fa-coins"></i></div>
        <h3><?php e('weapon_pay_title'); ?></h3>
        <p><?php e('weapon_pay_body'); ?></p>
      </div>
      <div class="card fade-up delay-2">
        <div class="card-icon"><i class="fa-solid fa-rocket"></i></div>
        <h3><?php e('weapon_sell_title'); ?></h3>
        <p><?php e('weapon_sell_body'); ?></p>
      </div>
    </div>
  </div>
</section>

<!-- FOUR SERVICES (a la carte, each with price + CTA) -->
<section class="section section-alt" id="services">
  <div class="container">
    <h2 class="section-title fade-up"><?php e('nav_services'); ?></h2>
    <p class="section-sub fade-up"><?php e('faq_a5'); ?></p>
    <div class="grid grid-4">
      <?php foreach ($svcMeta as $key => $meta): $plan = $planMap[$key] ?? null; ?>
      <div class="card fade-up<?= $key === 'support_bot' ? ' delay-1' : ($key === 'ai_tickets' ? ' delay-2' : ($key === 'number_rental' ? ' delay-3' : '')) ?>">
        <div class="card-icon"><i class="<?= $meta['icon'] ?>"></i></div>
        <h3><?php e($meta['title']); ?></h3>
        <p><?php e($meta['body']); ?></p>
        <?php if ($plan): ?>
        <div class="price-tag">$<?= rtrim(rtrim(number_format((float) $plan['price_monthly'], 2), '0'), '.') ?><small> <?php e('per_month'); ?></small></div>
        <?php endif; ?>
        <a class="btn btn-primary btn-block" style="margin-top:16px" href="register.php"><?php e('buy_now'); ?></a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- PANELS SUPPORTED -->
<section class="section">
  <div class="container">
    <h2 class="section-title fade-up"><?php e('panels_title'); ?></h2>
    <p class="section-sub fade-up"><?php e('panels_sub'); ?></p>
    <div class="panel-logos fade-up">
      <span class="chip"><i class="fa-solid fa-circle-check"></i> PerfectPanel</span>
      <span class="chip"><i class="fa-solid fa-circle-check"></i> Rental Panel</span>
      <span class="chip"><i class="fa-solid fa-circle-check"></i> SMM API v2 (Custom)</span>
      <span class="chip"><i class="fa-solid fa-circle-check"></i> KuzaPanel</span>
    </div>
  </div>
</section>

<!-- DEMOS INTRO -->
<section class="section section-alt" id="demo" style="padding-bottom:40px">
  <div class="container">
    <div style="text-align:center">
      <span class="eyebrow fade-up"><i class="fa-solid fa-play"></i> Live demos</span>
    </div>
    <h2 class="section-title fade-up"><?php e('demo2_title'); ?></h2>
    <p class="section-sub fade-up"><?php e('demo2_sub'); ?></p>
  </div>
</section>

<!-- ORDER BOT DEMO (KuzaPanel-style guided menu) -->
<section class="section section-alt" style="padding-top:20px">
  <div class="container">
    <div class="demo-split">
      <div class="demo-visual fade-up">
        <!-- Live phone: plays the COMPLETE order flow (menu → platform →
             category → package → link → confirm → wallet → placed) on loop. -->
        <div class="phone phone-live">
          <div class="phone-screen">
            <div class="statusbar">
              <span class="liveClock2">09:41</span>
              <span><i class="fa-solid fa-signal"></i><i class="fa-solid fa-wifi"></i><i class="fa-solid fa-battery-three-quarters"></i></span>
            </div>
            <div class="chat-head">
              <span class="wa-ava"><i class="fa-brands fa-whatsapp"></i></span>
              <span>
                <span class="wa-name">YourPanel · Order Bot</span><br>
                <span class="wa-status">online</span>
              </span>
              <span class="wa-icons"><i class="fa-solid fa-video"></i><i class="fa-solid fa-phone"></i><i class="fa-solid fa-ellipsis-vertical"></i></span>
            </div>
            <div class="chat-body tall" id="liveOrder"></div>
          </div>
        </div>
      </div>
      <div class="fade-up delay-1">
        <span class="eyebrow"><i class="fa-solid fa-cart-shopping"></i> Order Bot</span>
        <h2 style="font-size:1.9rem;margin-bottom:14px"><?php e('demo_order_title'); ?></h2>
        <p style="color:var(--text-muted);font-size:1.05rem;margin-bottom:22px"><?php e('demo_order_sub'); ?></p>
        <div class="bene-list">
          <div class="bene-item"><span class="bene-check"><i class="fa-solid fa-check"></i></span> Interactive menus — no commands to memorise</div>
          <div class="bene-item"><span class="bene-check"><i class="fa-solid fa-check"></i></span> Your brand name and services, not ours</div>
          <div class="bene-item"><span class="bene-check"><i class="fa-solid fa-check"></i></span> Order lands on your panel the instant it's placed</div>
          <div class="bene-item"><span class="bene-check"><i class="fa-solid fa-check"></i></span> Works on WhatsApp <em>and</em> Telegram</div>
        </div>
        <div class="demo-cap"><i class="fa-solid fa-circle-info"></i> <?php e('demo_order_cap'); ?></div>
      </div>
    </div>
  </div>
</section>

<!-- SUPPORT BOT DEMO (Quick Menu + refill/cancel/status response cards) -->
<section class="section">
  <div class="container">
    <div class="demo-split reverse">
      <div class="demo-visual fade-up">
        <div class="tabs" style="margin-bottom:20px">
          <button class="tab-btn active" data-tab="sup-refill"><i class="fa-solid fa-rotate"></i> <?php e('demo_tab_refill'); ?></button>
          <button class="tab-btn" data-tab="sup-cancel"><i class="fa-solid fa-xmark"></i> <?php e('demo_tab_cancel'); ?></button>
          <button class="tab-btn" data-tab="sup-status"><i class="fa-solid fa-chart-simple"></i> <?php e('demo_tab_status'); ?></button>
        </div>

        <!-- Refill -->
        <div class="tab-pane active" id="sup-refill">
          <div class="demo-chat">
            <div class="chat-label">Customer</div>
            <div class="msg cmd">481799696 refill</div>
            <div class="bot-card">
              <div class="bc-title">✅ Refill Request Submitted</div>
              <div class="bc-row"><span class="bc-ico">🆔</span><span><span class="bc-k">Order:</span> #481799696</span></div>
              <div class="bc-row"><span class="bc-ico">📊</span><span><span class="bc-k">Status:</span> ✅ Completed</span></div>
              <div class="bc-row"><span class="bc-ico">📋</span><span><span class="bc-k">Service:</span> Instagram Followers</span></div>
              <div class="bc-row"><span class="bc-ico">🔗</span><span><span class="bc-k">Link:</span> instagram.com/example</span></div>
              <div class="bc-note">✅ Refill has been forwarded to the provider.<br><span class="bc-k">📮 Reference:</span> REF-2847593</div>
              <div class="bc-foot">Bot • Instant</div>
            </div>
          </div>
        </div>

        <!-- Cancel -->
        <div class="tab-pane" id="sup-cancel">
          <div class="demo-chat">
            <div class="chat-label">Customer</div>
            <div class="msg cmd">485018699 cancel</div>
            <div class="bot-card">
              <div class="bc-title">🧾 Cancel Request Processed</div>
              <div class="bc-row"><span class="bc-ico">🆔</span><span><span class="bc-k">Order:</span> #485018699</span></div>
              <div class="bc-row"><span class="bc-ico">⏳</span><span><span class="bc-k">Status:</span> In Progress</span></div>
              <div class="bc-row"><span class="bc-ico">🏅</span><span><span class="bc-k">Charge:</span> $12.50</span></div>
              <div class="bc-row"><span class="bc-ico">📉</span><span><span class="bc-k">Remaining:</span> 2,340</span></div>
              <div class="bc-note">✅ This order has been added to the refund queue.<br>📤 Provider notified automatically.</div>
              <div class="bc-foot">Bot • Instant</div>
            </div>
          </div>
        </div>

        <!-- Status -->
        <div class="tab-pane" id="sup-status">
          <div class="demo-chat">
            <div class="chat-label">Customer</div>
            <div class="msg cmd">3463745263 status</div>
            <div class="bot-card">
              <div class="bc-title">📊 Order Status</div>
              <div class="bc-row"><span class="bc-ico">🆔</span><span><span class="bc-k">Order:</span> #3463745263</span></div>
              <div class="bc-row"><span class="bc-ico">⏳</span><span><span class="bc-k">Status:</span> In Progress</span></div>
              <div class="bc-row"><span class="bc-ico">📋</span><span><span class="bc-k">Service:</span> YouTube Views</span></div>
              <div class="bc-row"><span class="bc-ico">🔗</span><span><span class="bc-k">Link:</span> youtube.com/watch?v=…</span></div>
              <div class="bc-note"><span class="bc-k">📈 Progress:</span><br>• Start: 15,420 &nbsp; • Ordered: 10,000<br>• Delivered: 7,850 &nbsp; • Remaining: 2,150</div>
              <div class="bc-foot">Bot • Instant</div>
            </div>
          </div>
        </div>
      </div>

      <div class="fade-up delay-1">
        <span class="eyebrow"><i class="fa-solid fa-headset"></i> Support Bot</span>
        <h2 style="font-size:1.9rem;margin-bottom:14px"><?php e('demo_support_title'); ?></h2>
        <p style="color:var(--text-muted);font-size:1.05rem;margin-bottom:26px"><?php e('demo_support_sub'); ?></p>
        <div class="bene-title"><i class="fa-solid fa-bolt bene-bolt"></i> <?php e('bene_title'); ?></div>
        <div class="bene-list">
          <?php for ($i = 1; $i <= 6; $i++): ?>
          <div class="bene-item"><span class="bene-check"><i class="fa-solid fa-check"></i></span> <?php e("bene_{$i}"); ?></div>
          <?php endfor; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- AI TICKETS DEMO -->
<section class="section section-alt">
  <div class="container">
    <div class="demo-split">
      <div class="demo-visual fade-up">
        <div class="demo-chat" style="background:#0b141a">
          <div class="chat-label" style="color:#8696a0">AI Support Widget · yoursite.com</div>
          <div class="msg out">My order is slow, can you help?</div>
          <div class="msg in" style="background:#1f2c33;color:#e9edef">🤖 I checked order <b>#48105</b> — it's <b>70% delivered</b> and on track to finish within 2 hours. Would you like a refill scheduled just in case?</div>
          <div class="msg out">No, that's fine, thanks!</div>
          <div class="msg in" style="background:#1f2c33;color:#e9edef">Glad to help! ✅ Ticket resolved. Anything else, just message here anytime.</div>
        </div>
      </div>
      <div class="fade-up delay-1">
        <span class="eyebrow"><i class="fa-solid fa-robot"></i> AI Tickets</span>
        <h2 style="font-size:1.9rem;margin-bottom:14px"><?php e('demo_ai_title'); ?></h2>
        <p style="color:var(--text-muted);font-size:1.05rem;margin-bottom:22px"><?php e('demo_ai_sub'); ?></p>
        <div class="bene-list">
          <div class="bene-item"><span class="bene-check"><i class="fa-solid fa-check"></i></span> DeepSeek-powered, answers in your brand voice</div>
          <div class="bene-item"><span class="bene-check"><i class="fa-solid fa-check"></i></span> One-line embed snippet for any website</div>
          <div class="bene-item"><span class="bene-check"><i class="fa-solid fa-check"></i></span> Hands off to your team when it's out of scope</div>
          <div class="bene-item"><span class="bene-check"><i class="fa-solid fa-check"></i></span> Full ticket history in your dashboard</div>
        </div>
        <div class="demo-cap"><i class="fa-solid fa-circle-info"></i> <?php e('demo_ai_cap'); ?></div>
      </div>
    </div>

    <!-- Multi-channel strip -->
    <div style="text-align:center;margin-top:64px">
      <h3 style="font-size:1.4rem;margin-bottom:8px"><?php e('channels_title'); ?></h3>
      <p style="color:var(--text-muted)"><?php e('channels_sub'); ?></p>
      <div class="channel-grid">
        <div class="channel-card"><span class="ch-ico ch-wa"><i class="fa-brands fa-whatsapp"></i></span> <?php e('channel_wa'); ?></div>
        <div class="channel-card"><span class="ch-ico ch-tg"><i class="fa-brands fa-telegram"></i></span> <?php e('channel_tg'); ?></div>
        <div class="channel-card"><span class="ch-ico ch-ai"><i class="fa-solid fa-robot"></i></span> <?php e('channel_ai'); ?></div>
      </div>
    </div>
  </div>
</section>

<!-- 4 STEPS -->
<section class="section">
  <div class="container">
    <h2 class="section-title fade-up"><?php e('steps_title'); ?></h2>
    <p class="section-sub fade-up"><?php e('steps_sub'); ?></p>
    <div class="grid grid-4" style="margin-top:20px">
      <div class="step fade-up">
        <div class="step-num">1</div>
        <h3><?php e('step1_title'); ?></h3>
        <p><?php e('step1_body'); ?></p>
      </div>
      <div class="step fade-up delay-1">
        <div class="step-num">2</div>
        <h3><?php e('step2_title'); ?></h3>
        <p><?php e('step2_body'); ?></p>
      </div>
      <div class="step fade-up delay-2">
        <div class="step-num">3</div>
        <h3><?php e('step3_title'); ?></h3>
        <p><?php e('step3_body'); ?></p>
      </div>
      <div class="step fade-up delay-3">
        <div class="step-num">4</div>
        <h3><?php e('step4_title'); ?></h3>
        <p><?php e('step4_body'); ?></p>
      </div>
    </div>
  </div>
</section>

<!-- PAYMENTS -->
<section class="section section-alt">
  <div class="container">
    <h2 class="section-title fade-up"><?php e('pay_title'); ?></h2>
    <p class="section-sub fade-up"><?php e('pay_sub'); ?></p>
    <div class="pay-methods fade-up">
      <span class="chip usdt"><i class="fa-brands fa-btc"></i> USDT - NOWPayments</span>
      <span class="chip usdt"><i class="fa-brands fa-btc"></i> USDT - Binance Pay</span>
      <span class="chip"><i class="fa-solid fa-mobile-screen"></i> M-Pesa</span>
      <span class="chip"><i class="fa-solid fa-mobile-screen"></i> Tigo Pesa</span>
      <span class="chip"><i class="fa-solid fa-mobile-screen"></i> Airtel Money</span>
      <span class="chip"><i class="fa-solid fa-mobile-screen"></i> Halopesa</span>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="section">
  <div class="container" style="max-width:760px">
    <h2 class="section-title fade-up"><?php e('faq_title'); ?></h2>
    <p class="section-sub fade-up"><?php e('faq_sub'); ?></p>
    <div style="margin-top:20px">
      <?php for ($i = 1; $i <= 5; $i++): ?>
      <details class="faq-item fade-up">
        <summary><?php e("faq_q{$i}"); ?></summary>
        <div class="faq-body"><?php e("faq_a{$i}"); ?></div>
      </details>
      <?php endfor; ?>
    </div>
  </div>
</section>

<!-- FINAL CTA -->
<section class="section">
  <div class="container">
    <div class="cta-band fade-up">
      <h2><?php e('cta_final_title'); ?></h2>
      <p><?php e('cta_final_sub'); ?></p>
      <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;position:relative;z-index:1">
        <a class="btn btn-accent btn-lg" href="register.php"><i class="fa-solid fa-rocket"></i> <?php e('cta_start_free'); ?></a>
        <a class="btn btn-ghost btn-lg" href="<?= htmlspecialchars($config['links']['whatsapp_url'] ?? '#') ?>"><i class="fa-brands fa-whatsapp"></i> <?php e('cta_whatsapp'); ?></a>
      </div>
    </div>
  </div>
</section>

<script>
/* Live chat player — scripted conversations that type, reply and loop.
   Bubble kinds: out / in (plain msgs), menu (.bot-menu), card (.bot-card).
   Bot-side bubbles (in/menu/card) show a typing indicator first. */
(function () {
  function now() {
    var d = new Date(), h = d.getHours(), m = d.getMinutes();
    return (h < 10 ? '0' + h : h) + ':' + (m < 10 ? '0' + m : m);
  }
  document.querySelectorAll('#liveClock, .liveClock2').forEach(function (c) { c.textContent = now(); });

  function bubble(step) {
    var el = document.createElement('div');
    if (step.who === 'menu') { el.className = 'bot-menu'; el.innerHTML = step.t; return el; }
    if (step.who === 'card') { el.className = 'bot-card'; el.innerHTML = step.t; return el; }
    el.className = 'msg ' + step.who;
    el.innerHTML = step.t + '<div class="meta">' + now()
      + (step.who === 'out' ? ' <span class="ticks">✓✓</span>' : '') + '</div>';
    return el;
  }

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function player(boxId, steps, holdMs) {
    var box = document.getElementById(boxId);
    if (!box) return;

    if (reduced) { // no motion: render the finished conversation
      steps.forEach(function (s) { box.appendChild(bubble(s)); });
      box.scrollTop = box.scrollHeight;
      return;
    }

    var typingEl = null;
    function showTyping() {
      typingEl = document.createElement('div');
      typingEl.className = 'typing';
      typingEl.innerHTML = '<i></i><i></i><i></i>';
      box.appendChild(typingEl);
      box.scrollTop = box.scrollHeight;
    }
    function hideTyping() { if (typingEl) { typingEl.remove(); typingEl = null; } }

    function play(i) {
      if (i >= steps.length) {
        setTimeout(function () { box.innerHTML = ''; play(0); }, holdMs);
        return;
      }
      var step = steps[i];
      var botSide = step.who !== 'out';
      if (botSide) {
        showTyping();
        setTimeout(function () {
          hideTyping();
          box.appendChild(bubble(step));
          box.scrollTop = box.scrollHeight;
          play(i + 1);
        }, step.who === 'in' ? 950 : 1200);
      } else {
        setTimeout(function () {
          box.appendChild(bubble(step));
          box.scrollTop = box.scrollHeight;
          play(i + 1);
        }, 800);
      }
    }
    play(0);
  }

  /* HERO — the SUPPORT BOT, full journey: Quick Menu → Refill → Order ID →
     guarantee check → submitted. (The Order Bot plays in the demo section.) */
  player('liveChat', [
    { who: 'out',  t: 'Hi 👋' },
    { who: 'menu', t: '<div class="bm-title">📋 Quick Menu (AI)</div>Welcome to <b>YourPanel</b> — AI &amp; Human Support 🤖<div class="bm-list">'
      + '<div class="bm-item">1️⃣ Refill</div>'
      + '<div class="bm-item">2️⃣ Speed Up</div>'
      + '<div class="bm-item">3️⃣ Cancel</div>'
      + '<div class="bm-item">4️⃣ Partial / Fake Comp</div>'
      + '<div class="bm-item">5️⃣ 👤 Talk to a Human</div>'
      + '<div class="bm-item">6️⃣ 📦 Order Status</div>'
      + '<div class="bm-item">7️⃣ 💸 Top-Up Issue</div>'
      + '<div class="bm-item">8️⃣ ❓ AI FAQ</div>'
      + '</div>' },
    { who: 'out',  t: '1️⃣ Refill' },
    { who: 'in',   t: '🔢 Please send the <b>Order ID</b> for Refill' },
    { who: 'out',  t: '#48219' },
    { who: 'in',   t: '🛡️ Checking your refill guarantee…' },
    { who: 'card', t: '<div class="bc-title">♻️ Refill Submitted</div>'
      + '<div class="bc-row"><span class="bc-ico">🆔</span><span><span class="bc-k">Order:</span> #48219</span></div>'
      + '<div class="bc-row"><span class="bc-ico">📦</span><span><span class="bc-k">Service:</span> Instagram Followers</span></div>'
      + '<div class="bc-row"><span class="bc-ico">🛡️</span><span><span class="bc-k">Guarantee:</span> 30 days ✅</span></div>'
      + '<div class="bc-row"><span class="bc-ico">⚡</span><span><span class="bc-k">Status:</span> Sent to provider</span></div>'
      + '<div class="bc-foot">Bot • under 1 second</div>' },
    { who: 'in',   t: 'Reply <b>0</b> for the menu anytime 🙌' }
  ], 4200);

  /* ORDER BOT DEMO — the COMPLETE flow, every step a real customer takes. */
  player('liveOrder', [
    { who: 'out',  t: 'Hi' },
    { who: 'menu', t: '<div class="bm-title">👑 Welcome to YourPanel!</div>Grow your social media — fast, safe & affordable.<div class="bm-btn"><i class="fa-solid fa-list"></i> Open Menu</div>' },
    { who: 'out',  t: '🛒 Place New Order' },
    { who: 'menu', t: '<div class="bm-title">📱 Choose Platform</div><div class="bm-list"><div class="bm-item">📸 Instagram</div><div class="bm-item">🎵 TikTok</div><div class="bm-item">▶️ YouTube</div></div>' },
    { who: 'out',  t: '📸 Instagram' },
    { who: 'menu', t: '<div class="bm-title">✨ Choose Category</div><div class="bm-list"><div class="bm-item">👥 Followers</div><div class="bm-item">❤️ Likes</div><div class="bm-item">👁️ Views</div></div>' },
    { who: 'out',  t: '👥 Followers' },
    { who: 'menu', t: '<div class="bm-title">💵 Choose Package</div><div class="bm-list"><div class="bm-item">1,000 — $1.20</div><div class="bm-item">5,000 — $5.50</div><div class="bm-item">10,000 — $10.00</div></div>' },
    { who: 'out',  t: '1,000 Followers' },
    { who: 'in',   t: '🔗 Send the <b>link</b> for your profile' },
    { who: 'out',  t: 'instagram.com/mybrand' },
    { who: 'menu', t: '<div class="bm-title">🧾 Confirm Your Order</div>👥 Instagram Followers<br>🔢 Quantity: 1,000<br>💰 Total: <b>$1.20</b><div class="bm-btn">✅ Confirm &nbsp;·&nbsp; ❌ Cancel</div>' },
    { who: 'out',  t: '✅ Confirm' },
    { who: 'in',   t: '💳 Paid from your wallet — new balance <b>$48.80</b>' },
    { who: 'card', t: '<div class="bc-title">✅ Order Confirmed</div><div class="bc-row"><span class="bc-ico">🆔</span><span><span class="bc-k">Order:</span> #48220</span></div><div class="bc-row"><span class="bc-ico">📦</span><span><span class="bc-k">Service:</span> Instagram Followers</span></div><div class="bc-row"><span class="bc-ico">🔢</span><span><span class="bc-k">Quantity:</span> 1,000</span></div><div class="bc-row"><span class="bc-ico">⚡</span><span><span class="bc-k">Status:</span> Sent to your panel</span></div><div class="bc-foot">Bot • Instant</div></div>' }
  ], 4500);
})();
</script>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
