# 📘 SMM Packages — Maelezo Kamili ya Website

> Mwongozo wa website nzima: ni nini, huduma zote, na jinsi kila kitu kinavyofanya kazi mwanzo hadi mwisho.
> _Toleo la mwisho: Julai 2026_

---

## SEHEMU 1 — Wazo Kuu (Ni Nini?)

**SMM Packages** ni jukwaa la **B2B SaaS**. Muhimu kuelewa: **hatuuzi huduma za mitandao (followers/likes) kwa watumiaji wa mwisho.** Badala yake, **tunauza miundombinu ya bot + AI kwa wafanyabiashara wa SMM (resellers).**

### Mfano wa "Muuza Jembe"
Wakati wa mbio ya dhahabu (gold rush), aliyefanikiwa zaidi si mchimba dhahabu — ni **muuza majembe na sepetu**. SMM Packages ni muuza majembe:

- Kuna maelfu ya "SMM resellers" wanaouza followers/likes/views kupitia panel zao.
- Wote wanahitaji njia ya kupokea oda, malipo, na kutoa msaada kwa wateja wao — **kiotomatiki, saa 24**.
- SMM Packages inawapa **bot ya WhatsApp/Telegram + AI**, chini ya **brand ya reseller mwenyewe**.

### Kwa Nini Sisi (dhidi ya washindani kama smmchatbot.com)
| Faida | Maelezo |
|---|---|
| ✅ **API rasmi ya Meta** | Tunatumia WhatsApp Cloud API rasmi — namba **haifungiwi** (washindani wanatumia QR scan yenye hatari ya ban) |
| ✅ **Lipa unavyotaka** | USDT/crypto NA mobile money (M-Pesa, Tigo, Airtel) |
| ✅ **Inauza NA inasaidia** | Bots zetu zinaweka oda NA zinashughulikia refills/status — washindani wengi wanasaidia tu |
| ✅ **Subscription safi** | Bila mabango ya kutisha ya kufungiwa |

---

## SEHEMU 2 — Website ya Umma (Anayoiona Mgeni)

Mgeni asiyeingia anaona kurasa hizi za umma:

| Ukurasa | Faili | Maudhui |
|---|---|---|
| **Home / Landing** | `home.php` | Ukurasa kuu wa mauzo |
| **Pricing** | `pricing.php` | Bei za huduma zote |
| **How it Works** | `how-it-works.php` | Hatua za kuanza |
| **FAQ** | `faq.php` | Maswali yanayoulizwa mara nyingi |
| **About** | `about.php` | Kuhusu jukwaa |
| **Contact** | `contact.php` | Mawasiliano |
| **Terms / Privacy** | `terms.php`, `privacy.php` | Sheria & faragha |
| **Register** | `register.php` | Kujisajili (email + phone, bila kadi) |
| **Login** | `login.php` | Kuingia |

### Landing Page (`home.php`) — Sehemu Zake
1. **Navbar** — floating pill yenye logo, menyu, lugha (EN/FR/SW), dark/light toggle
2. **Hero** — kichwa "WhatsApp Bots for Your SMM Panel — Built for Resellers" + **simu LIVE inayocheza Support Bot** (Quick Menu 1-8 → Refill → guarantee → matokeo)
3. **Trust Bar** — 500+ Panels, 10K+ Orders, 99.9% Uptime, 24/7 Support
4. **Why SMM Packages** — Official Meta API, Pay Your Way, Sell AND Support
5. **Services** — huduma 4 + bei + kitufe "Buy"
6. **Works With Your Panel** — PerfectPanel, Rental Panel, SMM API v2, KuzaPanel
7. **See Every Bot in Action** — **simu LIVE inayocheza Order Bot flow nzima** (menu → platform → category → package → link → confirm → wallet → oda)
8. **Support Bot demo** + **AI Tickets demo**
9. **One engine, every channel** — WhatsApp / Telegram / Website AI Widget
10. **Live in 5 Minutes** — hatua 4 za kuanza
11. **Payments We Accept** — USDT (NOWPayments/Binance), M-Pesa, Tigo, Airtel, Halopesa

### Lugha
Lugha 3: **Kiingereza (default), Kifaransa, Kiswahili**. Mgeni anachagua juu; chaguo linahifadhiwa.

### Muonekano (Design)
**Neumorphism** (soft UI) inayofanana na Voxopay — canvas laini, cards zinazoinuka kwa vivuli viwili (mwanga juu-kushoto, giza chini-kulia), inputs zilizo "carved in", rangi ya kijani `#0EA472`. Inafanya kazi kwa **light NA dark mode**. Font: Nunito (vichwa) + Inter (body).

---

## SEHEMU 3 — Huduma 4 (Zinauzwa À La Carte)

Kila huduma ni **subscription yake tofauti**. Reseller ananunua anachohitaji tu — si lazima achukue zote. Kila huduma ina safu yake ya `service_key` na `ends_at`.

| # | Huduma | Bei/Mwezi | Bei/Mwaka (-20%) | Inafanya Nini |
|---|---|---|---|---|
| 1 | 🛒 **Order Bot** | $17 | $163.20 | Bot inayopokea oda za mteja kupitia WhatsApp/Telegram |
| 2 | 🎧 **Support Bot** | $17 | $163.20 | Menyu ya msaada baada ya mauzo (refill/status/cancel/AI) |
| 3 | 🤖 **AI Tickets** | $11 | $105.60 | Widget ya gumzo kwenye tovuti ya reseller (DeepSeek AI) |
| 4 | 📞 **Number Rental** | $11 | $105.60 | Kukodi namba ya WhatsApp kwa reseller asiye na ya Meta |

Bei ni USD. Malipo ya mwaka = punguzo la **-20%**.

---

## SEHEMU 4 — Njia ya Reseller Kuanza (Onboarding)

```
1. Jisajili (email + phone, hakuna kadi)
        │
2. Nunua huduma (mfano Order Bot $17/mo) — lipa kwa USDT au mobile money
        │
3. Unganisha PANEL yako ya SMM (URL + API key → mfumo unagundua kiotomatiki)
        │
4. Unganisha WhatsApp (namba yako ya Meta AU kodi namba kutoka kwetu)
        │
5. Import services + weka bei zako (markup)
        │
6. BOT INAANZA KUFANYA KAZI — wateja wanaagiza & wanapata msaada
```

---

## SEHEMU 5 — Dashboard ya Reseller (Baada ya Kuingia)

Sidebar imepangwa kwa makundi wazi (bila kuchanganya):

### OVERVIEW
- **Dashboard** (`index.php`) — hero card ya kijani (salamu), huduma zilizoamilishwa, Resource Insights (X/Y + % utilized), Live Metrics, chart ya oda 30 siku

### CHANNELS (Viunganishi)
- **WhatsApp Setup** (`whatsapp.php`) — unganisha namba ya Meta au kodi namba; onyesha webhook URL + verify token
- **Telegram Bot** (`telegram.php`) — bandika token ya @BotFather → validate → save → setWebhook

### PANEL
- **Panel Connections** (`panels.php`) — unganisha panel yako ya SMM. Weka URL + API key → **PanelDetector** inajaribu API (param/header) na kugundua balance + idadi ya huduma kiotomatiki. API key inahifadhiwa **encrypted**.

### ORDER BOT
- **Order Bot** (`order-bot.php`) — mipangilio ya bot ya kuuza + staff numbers
- **Services & Pricing** (`bot-services.php`) — huduma zako + **bei yako mwenyewe**. Import kutoka panel, au ongeza kwa mkono (dropdown ya mitandao + category). Category inaruhusu >10 services kwa platform.
- **Payment Gateways** (`bot-gateways.php`) — gateway 9 za wateja wako kulipa + sarafu yako
- **Customers** (`bot-customers.php`) — wateja + **wallet balance** zao; adjust kwa mkono
- **Orders** (`orders.php`) — oda zote (amount, payment status, paid_from)

### SUPPORT BOT
- **Support Bot** (`support-bot.php`) — mipangilio ya menyu ya msaada
- **Response Templates** (`templates.php`) — hariri majibu ya bot (EN/FR/SW)
- **Guarantee Rules** (`guarantee-rules.php`) — keywords za refill (mfano "30 days" → ruhusiwa, "No Refill" → zuiwa, "lifetime" → milele)

### GROWTH
- **Broadcast** (`broadcast.php`) — tuma ujumbe kwa wateja walioandika ndani ya saa 24 (sheria ya Meta)
- **Referrals** (`referrals.php`) — pata credit (20%, hadi $20) kwa kuwaalika resellers wapya

### TICKETS
- **Tickets** (`tickets.php`) — tiketi za AI + majibu ya staff
- **AI Settings** (`settings-ai.php`) — weka DeepSeek key + embed snippet ya widget

### BILLING
- **Subscription** (`subscription.php`) — nunua/ongeza huduma
- **Invoices** (`invoices.php`) — historia ya malipo

### SETTINGS
- **Profile** (`profile.php`) — wasifu wako

---

## SEHEMU 6 — Jinsi Kila Kitu Kinavyounganishwa (Mtiririko)

### Mnyororo Kamili
```
Mteja anatuma ujumbe wa WhatsApp
        │
        ▼
Meta → Webhook MMOJA (public/webhooks/whatsapp.php)
        │  huhakiki sahihi ya Meta (X-Hub-Signature-256)
        ▼
BotRouter (MOYO WA MFUMO — app/services/bots/BotRouter.php)
   1. Soma phone_number_id → tenant yupi? (findByPhoneNumberId)
   2. Tenant hai? (si suspended) — vinginevyo bot inasimama papo hapo
   3. GATE: isServiceActive(order_bot / support_bot)? — lango la usalama
   4. Anti-spam check (RateLimit; staff hupita)
   5. pickBot(): order au support?
        │
        ├──► ORDER BOT ──► weka oda kwenye PANEL ya reseller
        │
        └──► SUPPORT BOT ──► refill/status/cancel/AI
```

### Lango Moja la Ukweli: `isServiceActive()`
Kila kipengele huulizana swali moja kabla ya kufanya kazi: **"Je huduma hii ina subscription hai?"** Hili ndilo kizuizi cha usalama cha jukwaa lote. Reseller asipolipa Order Bot, bot yake ya kuuza inasimama.

---

## SEHEMU 7 — Order Bot (Jinsi Inavyofanya Kazi)

Mtiririko wa mteja kuagiza (state machine kwenye `bot_conversations`):

```
Mteja: "hi"  (au chochote)
   │
   ▼  👋 Welcome + Choose Platform (Instagram/TikTok/YouTube…)
   │
Mteja anachagua platform
   │
   ▼  ✨ Choose Category (Followers/Likes/Views)   ← inaruka ikiwa moja tu
   │
Mteja anachagua category
   │
   ▼  💵 Choose Service + BEI (kutoka bei ya reseller)
   │
Mteja anachagua huduma
   │
   ▼  🔗 Send the link
   │
Mteja anatuma link
   │
   ▼  🧾 Confirm — Total $X.XX  [✅ Confirm / ❌ Cancel]
   │
Mteja: Confirm
   │
   ▼  WALLET CHECK:
       • Ana pesa za kutosha → DEBIT + oda inawekwa kwenye panel
       • Hana → "Top up kwanza" → gateway → malipo → oda inajiwekwa
   │
   ▼  ✅ Order #48220 placed! Sent to your panel
```

**Muhimu:**
- Huduma & bei **zinatoka kwenye panel/catalogue ya RESELLER** (si zetu)
- Oda inawekwa moja kwa moja kwenye panel ya reseller (`SmmProviderClient::addOrder`)
- Ina-notify staff (arifa ya oda mpya)

---

## SEHEMU 8 — Wallet & Malipo (Mfumo wa Kuzapanel)

Kila mteja ana **balance (wallet)** ndani ya duka la reseller:

```
Mteja anataka kuagiza (oda $1.20)
   │
   ├─ Balance ≥ $1.20?  →  DEBIT + oda inawekwa mara moja
   │
   └─ Balance ndogo?  →  "Top up kwanza"
                          │
                          ▼  Chagua gateway → weka phone/pata link
                          │
                          ▼  Malipo yanathibitishwa na gateway (webhook)
                          │
                          ▼  Wallet inaongezeka → ODA INAJIWEKWA KIOTOMATIKI
```

### Gateway 9 za Malipo (Wateja wa Reseller Kulipa)
Kila reseller anachagua gateway zake:

| Live sasa (end-to-end) | Coming soon (UI tayari) |
|---|---|
| Snippe (Mobile Money TZ) | Flutterwave |
| NOWPayments (USDT/Crypto) | Stripe |
| Binance Pay (Crypto) | PayPal · Pesapal · ZenoPay · MoMoPay |

⚠️ **Miktadha miwili ya malipo (usiichanganye):**
- **Reseller → SISI** (SaaS billing) — reseller analipa subscription ($17/mo) kwetu
- **Mteja → RESELLER** (wallet) — mteja wa reseller analipa oda kupitia gateway za reseller

---

## SEHEMU 9 — Support Bot (Menyu ya Msaada)

Mteja anatuma chochote → anaona **Quick Menu ya namba 1-8**:

```
📋 Quick Menu (AI)
Welcome to [Duka] — AI & Human Support 🤖

1️⃣ Refill              5️⃣ 👤 Talk to a Human
2️⃣ Speed Up            6️⃣ 📦 Order Status
3️⃣ Cancel              7️⃣ 💸 Top-Up Issue
4️⃣ Partial/Fake Comp   8️⃣ ❓ AI FAQ

Reply 0 for menu, back, or cancel.
```

**Jinsi kila chaguo linavyofanya kazi:**
| Chaguo | Kinachotokea |
|---|---|
| 1 Refill | Omba Order ID → **guarantee check** (ruhusiwa/zuiwa) → refill kwenye panel |
| 2 Speed Up | Order ID → ombi + arifa staff |
| 3 Cancel | Order ID → ombi + arifa staff |
| 4 Partial/Fake | Order ID → report + arifa staff |
| 5 Human | Link ya WhatsApp ya staff + arifa staff |
| 6 Order Status | Order ID → status kutoka panel |
| 7 Top-Up Issue | Maelekezo + arifa staff |
| 8 AI FAQ | Gumzo na DeepSeek AI |

**Refill Guarantee (muhimu):** `GuaranteeMatcher` huangalia jina la huduma dhidi ya keywords za reseller:
- "No Refill" **hushinda daima** → refill zuiwa
- Guarantee keyword ndefu zaidi hushinda (mfano "365 days" > "5 days")
- `refill_days = 0` = lifetime ♾️

---

## SEHEMU 10 — Vipengele Vingine

### AI Tickets
Widget ya gumzo kwenye tovuti ya reseller. Mteja anaandika → ujumbe unaenda API yetu → **DeepSeek AI** inajibu (branded kwa jina la reseller, EN/FR/SW). Staff wanaweza kuchukua tiketi na kujibu.

### Telegram Bot
Njia ya pili — inatumia **OrderBotHandler ile ile** ya WhatsApp kupitia kiolesura `BotMessenger`. Reseller anaunganisha bot yake ya @BotFather. Secret ndio uthibitisho.

### Broadcast
Reseller anatuma ujumbe kwa wateja walioandika **ndani ya saa 24** (sheria ya Meta ya free-form). Hutumwa kwa batches za 50 kupitia cron.

### Referrals
Kila reseller ana referral code. Akialika reseller mwingine anayefanya malipo ya kwanza, mualiko anapata **20% (hadi $20)** kama credit.

---

## SEHEMU 11 — Super-Admin (Wewe, Mmiliki wa Jukwaa)

Kupitia **`/hx-control`** (URL ya siri, session tofauti):
- **MRR / mapato** — jumla ya subscriptions hai
- **Tenants** — tafuta, **suspend/activate** (suspend = bot ya reseller inasimama papo hapo)
- **Plans** — hariri bei, max_panels
- **Numbers** — ongeza namba za kukodi (encrypted)
- **Reports** — MRR/ARR, churn-30d, mapato kwa gateway
- **Activity log** — audit trail

---

## SEHEMU 12 — Kazi za Nyuma (Crons)

Kazi zinazojiendesha bila mtu:

| Cron | Hufanya nini | Mara ngapi |
|---|---|---|
| `expire_subscriptions.php` | Subscriptions zilizopita → expired | Kila saa |
| `expire_stale_payments.php` | Malipo pending >60dk → failed (+refund) | Kila 15-30 dk |
| `cleanup_conversations.php` | Futa gumzo la bot lililokwisha muda | Kila 15-30 dk |
| `sync_orders.php` | Sasisha status ya oda kutoka panel | Kila 5-10 dk |
| `send_broadcasts.php` | Tuma batch moja kwa kila broadcast | Kila 1-2 dk |

---

## SEHEMU 13 — Muundo wa Kiufundi

| Kipengele | Teknolojia |
|---|---|
| **Lugha** | PHP tupu (hakuna Node/Composer/framework) |
| **Database** | MySQL — jedwali 28 |
| **Usalama** | AES-256-GCM encryption (api keys), CSRF, rate limiting, prepared statements, tenant isolation (kila kitu `tenant_id`) |
| **WhatsApp** | Meta Cloud API rasmi (Graph v22.0) |
| **AI** | DeepSeek (per-tenant key) |
| **Design** | Neumorphism CSS (light+dark), Nunito + Inter |
| **Muundo** | Multi-tenant SaaS — kila reseller data yake imetengwa kabisa |

### Njia za Ujumbe (Channels)
Injini moja (`OrderBotHandler`), njia tatu:
- 🟢 **WhatsApp** (Cloud API)
- 🔵 **Telegram** (Bot API)
- 🟠 **Website Widget** (AI Tickets)

---

## Muhtasari wa Haraka

> **SMM Packages** = jukwaa la B2B linalowauzia **resellers wa SMM** bot za WhatsApp/Telegram + AI, chini ya brand yao.
> Huduma 4 à la carte (Order Bot, Support Bot, AI Tickets, Number Rental).
> Reseller anaunganisha **panel** yake → bot inapokea oda za wateja → wateja wanalipa kwa **wallet** (mobile money/crypto) → oda zinawekwa kwenye panel kiotomatiki.
> Kila kitu kinapita **lango moja: `isServiceActive()`**. Wewe (super-admin) unadhibiti jukwaa lote kupitia `/hx-control`.
