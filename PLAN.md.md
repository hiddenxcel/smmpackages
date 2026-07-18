# SMM PACKAGES — MASTER PLAN KAMILI
### Website + System | Toleo la Mwisho
*HiddenXcel | Mwanza, Tanzania | Julai 2026*

---

# SEHEMU 0: MUHTASARI

**SMM Packages** ni multi-tenant SaaS platform inayowauzia wauzaji wengine wa SMM (B2B) miundombinu ya bot. Si SMM panel nyingine — ni "muuzaji wa majembe."

**Bidhaa nne zinazouzwa kivyake:**

| # | Bidhaa | Teknolojia | Kwa nani |
|---|---|---|---|
| 1 | **WhatsApp Order Bot** | Cloud API (PHP) | Panel owner anayetaka kuuza kwa WhatsApp |
| 2 | **WhatsApp Support Bot** | Cloud API (PHP) | Panel owner aliyechoka kujibu refill/status |
| 3 | **AI Ticket Support** | Web + DeepSeek (PHP) | Panel owner anayetaka tickets za AI |
| 4 | **Namba ya Kukodisha** | Cloud API (add-on) | Asiyetaka/asiyeweza Meta Business |

**Kanuni ya msingi:** kila huduma inauzwa peke yake. Mteja ananunua anachotaka.

**Uamuzi wa kiteknolojia:** Cloud API rasmi ya Meta kwa bot zote. **HAKUNA Baileys/QR** (Phase 6+ tu, kama option ya "Advanced" yenye onyo). Kila kitu ni **PHP** — hakuna Node.js.

---

# SEHEMU 1: HOJA ZA MAUZO (Positioning)

Dhidi ya mshindani mkuu (smmchatbot.com):

| Hoja | Yeye | Wewe |
|---|---|---|
| 🛡️ **Namba haitabanwa** | QR scan; anaonya mteja mwenyewe kuhusu ban | **Cloud API rasmi ya Meta** |
| 📱 **Lipa kwa mobile money** | Crypto pekee (Cryptomus/Binance/Tether) | **M-Pesa, Tigo Pesa, Airtel, Halopesa** |
| 🚀 **Uza NA saidia** | Support pekee | **Order Bot + Support Bot** |
| 😌 **Bila hofu** | Credits zinaisha; banner nyekundu kila page | **Subscription safi** |
| 🇹🇿 **Kiswahili** | English pekee | **SW + EN** |
| ☎️ **Namba ya kienyeji** | UK number $10/mo (QR) | **+255 Cloud API** |

**Slogan:** *"Bot ya WhatsApp kwa Panel Yako — Salama, kwa Kiswahili, kwa M-Pesa."*

---

# SEHEMU 2: WEBSITE (Landing / Public)

## 2.1 Kurasa za umma

| Ukurasa | Faili | Maudhui |
|---|---|---|
| Nyumbani | `public/home.php` | Landing kamili (chini) |
| Bei | `public/pricing.php` | Vifurushi + calculator |
| Jinsi Inavyofanya kazi | `public/how-it-works.php` | Hatua 4 + video |
| Maswali | `public/faq.php` | FAQ kamili |
| Kuhusu | `public/about.php` | HiddenXcel story |
| Wasiliana | `public/contact.php` | WhatsApp + fomu |
| Sheria | `public/terms.php`, `public/privacy.php` | Masharti |
| Jisajili | `public/register.php` | Signup |
| Ingia | `public/login.php` | Login |

## 2.2 Muundo wa Landing Page (home.php)

**1. Navbar** — Logo | Huduma | Bei | Jinsi | Maswali | [SW/EN] | Ingia | **Anza Bure**

**2. Hero**
- H1: *"Bot ya WhatsApp kwa Panel Yako ya SMM"*
- Sub: *"Weka order, refill, na status — kwa jina lako, kwa namba salama, kwa M-Pesa."*
- Badges: ✅ API Rasmi ya Meta · ✅ Namba Haitabanwa · ✅ Mobile Money
- CTA: **[Anza Bure]** [Ona Demo]
- Upande: phone mockup (screenshot ya bot yako halisi)

**3. Silaha Tatu** (kadi 3)
- 🛡️ **API Rasmi ya Meta** — "Wengine wanatumia QR scan — namba yako iko hatarini kubanwa. Sisi tunatumia WhatsApp Cloud API rasmi. Namba yako iko salama."
- 📱 **Lipa kwa M-Pesa** — "Si crypto. Si kadi ya kigeni. Mobile money unayoijua."
- 🚀 **Uza NA Saidia** — "Wengine wanasaidia tu. Bot zetu zinaweka order NA kushughulikia refill."

**4. Huduma Nne** (kadi 4, kila moja na bei + CTA)
- 🛒 **Order Bot** — mteja anaweka order kupitia WhatsApp
- 🎧 **Support Bot** — refill, status, cancel, speed-up automatiki
- 🤖 **AI Tickets** — tickets za AI ndani ya website yako
- ☎️ **Namba ya Kukodisha** — huna Meta Business? Kodisha yetu.

**5. Panel Zinazokubalika**
- PerfectPanel ✅ | Rental Panel ✅ | Custom (SMM API v2) ✅ | KuzaPanel ✅
- *"Auto-detect: weka URL + API key, mfumo unagundua wenyewe."*

**6. Ona Bot Ikifanya Kazi** (tabs + screenshots halisi)
- Tab 1: Order Placement — Karibu → Mtandao → Huduma → Kifurushi → Link → Order ✅
- Tab 2: Support — Refill / Status / Cancel
- Tab 3: AI Tickets

**7. Anza kwa Dakika 5** (hatua 4)
1. **Jisajili** — email + namba
2. **Unganisha Panel** — URL + Admin API key → Detect & Add
3. **Unganisha WhatsApp** — Cloud API (au kodisha namba yetu)
4. **Bot Inaanza** — wateja wanaanza kupata huduma

**8. Bei** — Starter | Pro | Business (toggle: mwezi/mwaka -20%)

**9. Malipo Tunayokubali** — M-Pesa, Tigo Pesa, Airtel Money, Halopesa (+ Crypto: coming soon)

**10. Ushuhuda** — testimonials (weka baada ya wateja wa kwanza)

**11. Maswali (FAQ)**
- Namba yangu itabanwa? → *Hapana, tunatumia API rasmi ya Meta.*
- Panel yangu inakubalika? → *Panel yoyote yenye SMM API v2.*
- Nahitaji Meta Business? → *Ndio, au kodisha namba yetu.*
- Nikiacha kulipa? → *Bot inasimama, data yako inabaki siku 30.*
- Naweza kuanza na huduma moja? → *Ndio, kila huduma inauzwa peke yake.*

**12. CTA ya Mwisho** — "Tayari Kuanza?" [Anza Bure] [Ongea Nasi WhatsApp]

**13. Footer** — Bidhaa | Kampuni | Sheria | Mawasiliano | © HiddenXcel

## 2.3 Design System

```
Rangi:
  --primary: #0EA472      (kijani WhatsApp-ish, brand yako)
  --primary-dark: #0B7D57
  --accent: #F59E0B       (dhahabu — CTA)
  --dark: #0F172A         (sidebar/footer)
  --gray-50..900          (neutral scale)
  --success/#10B981, --warning/#F59E0B, --danger/#EF4444

Font: Inter (Google Fonts) — kama bot yako
Icons: Font Awesome 6.5.1 (CDN)
CSS: custom properties (hand-written, si Tailwind)
JS: Vanilla
PWA: manifest + service worker
Lugha: SW/EN switch (Lang.php)
Dark mode: ndio (CSS variables)
```

**Kanuni ya UI:** safi, wazi, **BILA banner ya hofu**. Mshindani ana banner nyekundu "Low credit!" kila page — sisi hatuna.

---

# SEHEMU 3: DASHBOARD YA MTEJA (Tenant)

**Kanuni: chini ya 15 pages. Safi. Kila kitu kina kusudi.**

```
MUHTASARI
  📊 Dashboard          — status ya huduma, siku zilizobaki, takwimu, quick actions

PANEL
  🔌 Panel Connections  — auto-detect wizard, orodha ya panel (multi-panel)
  📦 Orders             — orders zote (Total/Pending/In Progress/Completed/Partial/Cancelled)

BOT
  📱 WhatsApp Setup     — Cloud API wizard AU kodisha namba
  🛒 Order Bot          — settings, huduma, bei, flow
  🎧 Support Bot        — settings, commands
  💬 Response Templates — badilisha ujumbe wa bot (~30 muhimu)
  ♻️ Guarantee Rules    — sheria za refill (keyword matching + Test)

TICKETS
  🎫 Tickets            — AI ticket support

FEDHA
  💳 Subscription       — plan zangu, kulipa, kuongeza muda
  🧾 Invoices           — historia ya malipo

MIPANGILIO
  💰 Payment Gateways   — keys za tenant (kwa wateja wake)
  🤖 AI Settings        — DeepSeek key
  👤 Profile            — jina, password, lugha, dark mode
```

## 3.1 Dashboard Home (index.php)

```
┌─────────────────────────────────────────────┐
│ Karibu, [Business Name]                      │
├─────────────────────────────────────────────┤
│ HUDUMA ZANGU                                 │
│ ┌──────────┐ ┌──────────┐ ┌──────────┐      │
│ │🛒 Order  │ │🎧Support │ │🤖 AI     │      │
│ │  Bot     │ │  Bot     │ │ Tickets  │      │
│ │ ✅ Active│ │ 🔒Locked │ │ 🔒Locked │      │
│ │ Siku 23  │ │[Nunua]   │ │[Nunua]   │      │
│ └──────────┘ └──────────┘ └──────────┘      │
├─────────────────────────────────────────────┤
│ TAKWIMU (siku 30)                            │
│ Orders: 142 | Ujumbe: 1,203 | Refills: 18   │
├─────────────────────────────────────────────┤
│ HALI YA MFUMO                                │
│ ✅ Panel: KuzaPanel (connected)              │
│ ✅ WhatsApp: +255 xxx (Cloud API, active)    │
└─────────────────────────────────────────────┘
```

## 3.2 Panel Connections — Auto-Detect Wizard

**Hatua 3 (kama mshindani, lakini bora):**

**Hatua 1: Panel Type**
- 🟦 **Perfect Panel** — Admin API v2 (Header Auth)
- ⬜ **Rental Panel** — Admin API v1 (Key Param)
- ⬜ **Custom** — SMM API v2 standard

**Hatua 2: Vitambulisho**
- URL Panel: `https://yourpanel.com` *(bila /api/v2)*
- Admin API Key: `••••••` *(kutoka Admin Settings ya panel yako — si User API Key)*
- [🔍 Detect & Add →]

**Hatua 3: Uthibitisho**
- ✅ Panel imegunduliwa: [jina]
- ✅ Huduma: 247 zimepatikana
- ✅ Balance: TZS xxx
- [Hifadhi]

*Fallback: [⚙️ Manual Configuration] kwa panel zisizogunduliwa*

## 3.3 WhatsApp Setup — Njia Mbili

```
┌──────────────────────┐  ┌──────────────────────┐
│ 🔧 NAMBA YANGU       │  │ ☎️ KODISHA NAMBA     │
│                      │  │                      │
│ Nina Meta Business   │  │ Sina Meta Business   │
│ na namba yangu       │  │ Anza leo, dakika 2   │
│                      │  │                      │
│ ✅ Bure              │  │ TZS 30,000/mwezi     │
│ ⚠️ Setup: dakika 30  │  │ ✅ Setup: dakika 2   │
│                      │  │ ✅ +255 namba        │
│ [Anza Wizard]        │  │ [Kodisha Sasa]       │
└──────────────────────┘  └──────────────────────┘
```

**Wizard ya "Namba Yangu" (hatua 5, kwa Kiswahili + picha):**
1. Fungua Meta Business Suite → business.facebook.com
2. Ongeza WhatsApp Business Account (WABA)
3. Thibitisha namba yako (SMS/simu)
4. Chukua: Access Token (permanent), Phone Number ID, WABA ID
5. Bandika hapa → [Thibitisha] → ✅ Imeunganishwa

**"Kodisha Namba":**
- Chagua namba kutoka orodha yako (+255 7xx xxx xxx)
- Lipa TZS 30,000/mwezi
- Namba inatengwa kwako → bot inaanza mara moja
- Masharti: hakuna spam; ukiukaji = kufungiwa

## 3.4 Guarantee Rules (kutoka mshindani, kuboreshwa)

```
Scope: [All Panels ▾]
Test: [Instagram Followers | 30 Days ♻️] [⚡ Test]

🚫 NO GUARANTEE KEYWORDS (refill hairuhusiwi)
   [No Refill] [No Guarantee] [Non Refill] [Without Guarantee]  [+ Ongeza]

♻️ GUARANTEE KEYWORDS (refill inaruhusiwa)
   [7 Days → 7]   [15 Days → 15]  [20 Days → 20]  [30 Days → 30]
   [60 Days → 60] [90 Days → 90]  [365 Days → 365] [Lifetime → ∞]  [+ Ongeza]

🆕 UBORESHAJI WETU:
   [🔄 Auto-Import kutoka Provider API] — soma majina ya huduma zote,
   pendekeza rules kiotomatiki
```

## 3.5 Response Templates (~30 muhimu, si 100)

| Kundi | Templates | Mfano |
|---|---|---|
| Status (4) | SUCCESS, NOT_FOUND, ERROR, UPDATE | `✅ Order #{order_id}: {status}` |
| Refill (8) | SUCCESS, PENDING, INVALID, NO_GUARANTEE, EXPIRED, FORWARDED, ERROR, RESULT | `♻️ Refill ya #{order_id} imewasilishwa` |
| Cancel (3) | SUCCESS, INVALID, ERROR | |
| Speedup (2) | SUCCESS, ERROR | |
| Order Flow (6) | WELCOME, SELECT_NETWORK, SELECT_SERVICE, SELECT_PACKAGE, SEND_LINK, CONFIRMED | |
| General (4) | HELP, UNKNOWN, HUMAN_AGENT, GOODBYE | |
| System (3) | MAINTENANCE, SUBSCRIPTION_EXPIRED, RATE_LIMITED | |

*Zote SW + EN. Mteja anaweza kubadilisha.*

## 3.6 Bot Settings

```
⚙️ COMMAND CONTROLS
   [x] Refill Command    [x] Status Command
   [x] Cancel Command    [ ] Speed-Up Command

🛡️ SPAM PROTECTION
   [x] Enable Spam Protection
   Repeat Threshold: [3]  Time Window (min): [5]  Disable (min): [60]

💬 RESPONSE SETTINGS
   [ ] Show Provider Name    [x] Detailed Status
   Bulk Threshold: [5]       Max Bulk Orders: [100]

👥 STAFF
   Staff Numbers: [+255...]  (bypass limits)
```

---

# SEHEMU 4: SUPER-ADMIN (Wewe — HiddenXcel)

```
📊 Dashboard        — mapato, tenants active/expired, ukuaji
👥 Tenants          — orodha, search, view, suspend/activate
💳 Subscriptions    — zote, filter kwa plan/status
🧾 Payments         — malipo yote, verification status
📦 Plans            — panga bei, wezesha/zima huduma kwa plan
☎️ Numbers          — namba zako: pool, nani anakodisha, hali
📈 Reports          — MRR, churn, ukuaji
⚙️ Settings         — gateway keys za platform, APP_KEY, maintenance
📋 Activity Log     — audit trail
```

**Usalama wa Super-Admin:**
- URL isiyojulikana (mfano `/hx-control/`, si `/admin/`)
- Login imara + rate-limit
- 2FA (baadaye)
- IP allowlist (optional)

---

# SEHEMU 5: DATABASE SCHEMA

## 5.1 Core / Tenancy

```sql
tenants
  id, business_name, email UNIQUE, phone, password_hash,
  status ENUM('active','suspended'), lang ENUM('sw','en') DEFAULT 'sw',
  created_at, updated_at

plans
  id, code VARCHAR UNIQUE, name, description,
  price_monthly DECIMAL, price_yearly DECIMAL,
  has_order_bot TINYINT, has_support_bot TINYINT, has_ai_tickets TINYINT,
  max_panels INT DEFAULT 1, max_numbers INT DEFAULT 1,
  status, sort_order

subscriptions
  id, tenant_id, plan_id, service_key ENUM('order_bot','support_bot','ai_tickets','number_rental'),
  status ENUM('pending','active','expired','cancelled'),
  starts_at, ends_at, auto_renew, created_at, updated_at
  INDEX(tenant_id, service_key, status)
  -- MUHIMU: kila huduma ina subscription YAKE (zinauzwa kivyake)

subscription_payments
  id, tenant_id, plan_id, subscription_id NULL,
  gateway ENUM('zenopay','snippe','harakapay'),
  transaction_ref VARCHAR UNIQUE, amount, months,
  status ENUM('pending','success','failed'),
  raw_response TEXT, created_at, updated_at
```

## 5.2 Panel & Provider

```sql
tenant_panels
  id, tenant_id, name, panel_type ENUM('perfectpanel','rentalpanel','custom'),
  api_url, api_key_enc TEXT, api_version ENUM('v1','v2'),
  auth_method ENUM('header','param'),
  last_checked_at, last_balance DECIMAL, services_count INT,
  status ENUM('active','error','inactive'), created_at
  INDEX(tenant_id)
```

## 5.3 WhatsApp (Cloud API)

```sql
tenant_whatsapp
  id, tenant_id, source ENUM('own','rented'),
  cloud_api_token_enc TEXT, phone_number_id VARCHAR UNIQUE,
  waba_id, verify_token, display_number,
  status ENUM('active','error','inactive'),
  bot_type ENUM('order','support','both'), created_at
  -- phone_number_id UNIQUE = webhook router key

platform_numbers                    -- namba ZAKO za kukodisha
  id, display_number UNIQUE, phone_number_id UNIQUE,
  cloud_api_token_enc TEXT, waba_id,
  status ENUM('available','rented','suspended'),
  monthly_cost DECIMAL, created_at

number_rentals
  id, tenant_id, platform_number_id, subscription_id,
  starts_at, ends_at, status ENUM('active','expired','revoked'),
  created_at
```

## 5.4 Bot Config & Data

```sql
tenant_bot_settings
  id, tenant_id, bot_type ENUM('order','support'),
  settings JSON,   -- toggles, thresholds, spam config
  updated_at

response_templates
  id, tenant_id NULL, template_key VARCHAR, lang ENUM('sw','en'),
  content TEXT, is_default TINYINT, updated_at
  UNIQUE(tenant_id, template_key, lang)
  -- tenant_id NULL = default ya platform

guarantee_rules
  id, tenant_id, panel_id NULL, rule_type ENUM('no_guarantee','guarantee'),
  keyword VARCHAR, refill_days INT NULL, status, created_at
  -- panel_id NULL = global rule ya tenant

bot_orders                          -- orders zilizowekwa na bot (Phase 2)
  id, tenant_id, panel_id, provider_order_id,
  customer_phone, service_id, service_name, link, quantity,
  charge DECIMAL, status, refill_status, created_at, updated_at
  INDEX(tenant_id, status), INDEX(provider_order_id)

bot_conversations                   -- state machine
  id, tenant_id, customer_phone, bot_type,
  state VARCHAR, context JSON, expires_at, updated_at
  UNIQUE(tenant_id, customer_phone, bot_type)

bot_messages                        -- log
  id, tenant_id, customer_phone, direction ENUM('in','out'),
  message TEXT, template_key NULL, created_at
  INDEX(tenant_id, created_at)
```

## 5.5 Tickets & Payments za Tenant

```sql
tenant_payment_gateways
  id, tenant_id, gateway ENUM('zenopay','snippe','harakapay'),
  api_key_enc TEXT, webhook_secret_enc TEXT, status, created_at

tenant_ai
  id, tenant_id, deepseek_api_key_enc TEXT, status, created_at

tickets
  id, tenant_id, customer_identifier, subject, status ENUM('open','pending','resolved','closed'),
  priority, created_at, updated_at

ticket_messages
  id, ticket_id, sender ENUM('customer','ai','staff'),
  message TEXT, created_at
```

## 5.6 Platform

```sql
superadmins
  id, username UNIQUE, password_hash, created_at

activity_log
  id, actor_type ENUM('tenant','superadmin','system'), actor_id,
  action, details JSON, ip, created_at

rate_limits
  id, identifier VARCHAR, action VARCHAR, attempts INT,
  window_start, INDEX(identifier, action)
```

---

# SEHEMU 6: ARCHITECTURE

```
smmpackages/
├── app/
│   ├── lib/
│   │   ├── DB.php              (PDO singleton — kutoka bot)
│   │   ├── Env.php             (env loader — kutoka bot)
│   │   └── Crypto.php          (AES-256-GCM — MPYA, muhimu)
│   ├── models/
│   │   ├── BaseModel.php
│   │   ├── Tenant.php, Plan.php, Subscription.php, SubscriptionPayment.php
│   │   ├── TenantPanel.php, TenantWhatsApp.php
│   │   ├── PlatformNumber.php, NumberRental.php
│   │   ├── BotSettings.php, ResponseTemplate.php, GuaranteeRule.php
│   │   ├── BotOrder.php, BotConversation.php
│   │   ├── Ticket.php, TenantPaymentGateway.php, TenantAi.php
│   │   └── Superadmin.php, ActivityLog.php
│   ├── helpers/
│   │   ├── TenantAuth.php, SuperadminAuth.php
│   │   ├── Csrf.php, Lang.php, RateLimit.php
│   ├── services/
│   │   ├── WhatsAppCloudClient.php     (Graph API — kutoka bot)
│   │   ├── SmmProviderClient.php       (SMM API v2 adapter)
│   │   ├── PanelDetector.php           (auto-detect — MPYA)
│   │   ├── GuaranteeMatcher.php        (keyword matching — MPYA)
│   │   ├── DeepSeekClient.php
│   │   ├── SubscriptionBilling.php     (SaaS billing)
│   │   ├── payments/{ZenoPayClient,SnippeClient,HarakaPayClient}.php
│   │   └── bots/
│   │       ├── BotRouter.php           (phone_number_id → tenant)
│   │       ├── OrderBotHandler.php     (Phase 2)
│   │       └── SupportBotHandler.php   (Phase 3)
│   └── lang/{sw.php, en.php}
├── config/{config.php, .env, .env.example}
├── database/{schema.sql, seed_plans.php, seed_templates.php, seed_superadmin.php}
├── cron/
│   ├── expire_subscriptions.php
│   ├── expire_stale_payments.php
│   ├── sync_orders.php             (Phase 2 — cheki status)
│   └── cleanup_conversations.php
└── public/
    ├── home.php, pricing.php, how-it-works.php, faq.php, about.php,
    │   contact.php, terms.php, privacy.php
    ├── register.php, login.php, logout.php
    ├── index.php (dashboard), profile.php
    ├── panels.php, orders.php
    ├── whatsapp.php, order-bot.php, support-bot.php,
    │   templates.php, guarantee-rules.php
    ├── tickets.php
    ├── subscription.php, invoices.php
    ├── settings-payments.php, settings-ai.php
    ├── includes/{layout_header.php, layout_footer.php, nav.php}
    ├── webhooks/
    │   ├── whatsapp.php            (Meta → BotRouter)
    │   ├── zenopay.php, snippe.php, harakapay.php   (SaaS billing)
    │   └── tenant/{zenopay,snippe,harakapay}.php    (Phase 2 — wateja wa tenant)
    ├── hx-control/                 (super-admin — URL siri)
    │   ├── login.php, index.php, tenants.php, subscriptions.php,
    │   │   payments.php, plans.php, numbers.php, reports.php, settings.php
    └── assets/{css,js,img,pwa}
```

## 6.1 Webhook Routing (muhimu sana)

```
Meta → public/webhooks/whatsapp.php
         ↓
    Soma phone_number_id kutoka payload
         ↓
    BotRouter: phone_number_id → tenant_whatsapp → tenant_id + bot_type
         ↓
    Cheki: subscription ya tenant ni active?
         ├─ Hapana → jibu "Huduma imesimama" au kimya
         └─ Ndio → OrderBotHandler AU SupportBotHandler
                    ↓
              BotConversation (state) + SmmProviderClient (panel ya tenant)
                    ↓
              WhatsAppCloudClient::send(token ya tenant)
```

---

# SEHEMU 7: MODEL YA BIASHARA

## 7.1 Bei (pendekezo — thibitisha na soko)

**Huduma moja moja (a la carte):**
| Huduma | Bei/mwezi (TZS) | USD |
|---|---|---|
| Order Bot | 45,000 | ~$17 |
| Support Bot | 45,000 | ~$17 |
| AI Tickets | 30,000 | ~$11 |
| Namba ya kukodisha | 30,000 | ~$11 |

**Vifurushi (bundle — punguzo):**
| Plan | Ina nini | Bei/mwezi |
|---|---|---|
| **Starter** | Support Bot pekee | 45,000 |
| **Pro** | Order + Support Bot | 75,000 *(okoa 15,000)* |
| **Business** | Vyote 3 + namba + priority support | 120,000 *(okoa 30,000)* |

*Mwaka: -20%*

## 7.2 Uchumi wa Namba za Kukodisha

**Gharama zako kwa namba/mwezi:**
- Meta Cloud API: **$0** (bure)
- Service messages (bot inajibu ndani ya 24h): **$0** (bure!)
- Namba (SIM/virtual): ~$1–5
- Server share: ~$0.50
- Template messages (nadra): ~$0–2
- **Jumla: ~$2–7**

**Unauza: $11 (TZS 30,000) → Faida ~60–80%, recurring**

*Kumbuka: Meta hubadilisha bei Jan 1 / Apr 1 / Jul 1 / Oct 1. Angalia rate card ya "Rest of Africa" kabla ya kupanga bei rasmi.*

## 7.3 Mkakati wa Ukuaji

**Land & Expand:**
1. Vuta kwa **Support Bot** (maumivu makubwa: "order yangu iko wapi?" mara 100/siku)
2. Panda hadi **Pro** (ongeza Order Bot — sasa anauza pia)
3. Panda hadi **Business** (AI Tickets + namba)

**Free trial:** siku 7 (huduma moja) — bila kadi

---

# SEHEMU 8: USALAMA

| # | Eneo | Utekelezaji |
|---|---|---|
| 1 | **Tenant isolation** | `tenant_id` kila query, **kutoka session PEKEE**, kamwe si kutoka input |
| 2 | **Secrets at rest** | AES-256-GCM (Crypto.php) kwa API keys, tokens, gateway keys. APP_KEY kwenye .env |
| 3 | **APP_KEY** | Hatari kuu — .env nje ya git, nje ya webroot, permissions 600 |
| 4 | **Webhook verification** | ⚠️ **MUHIMU:** thibitisha na gateway yenyewe (`verifyCompleted`) — usiamini payload |
| 5 | **Replay protection** | `transaction_ref` UNIQUE + cheki "je tayari ni success?" kabla ya kuwasha |
| 6 | **Meta webhook** | Thibitisha `X-Hub-Signature-256` (HMAC-SHA256 na app secret) |
| 7 | **CSRF** | Token kwenye POST forms zote |
| 8 | **Rate limiting** | Login, register, webhooks, bot messages |
| 9 | **Passwords** | `password_hash()` (bcrypt), `session_regenerate_id()` login |
| 10 | **SQL** | Prepared statements pekee (PDO) |
| 11 | **Super-admin** | URL siri, rate-limit, audit log |
| 12 | **Namba za kukodisha** | Masharti ya matumizi + uwezo wa kuzima spammer (sifa yako!) |

---

# SEHEMU 9: PHASES ZA UJENZI

## Phase 1 — Foundation + Website *(msingi)*
- [ ] DB.php, Env.php, **Crypto.php**, BaseModel, config, .env.example
- [ ] `schema.sql` kamili + seeds (plans, templates, superadmin)
- [ ] Models: Tenant, Plan, Subscription, SubscriptionPayment
- [ ] Helpers: TenantAuth, Csrf, Lang, RateLimit
- [ ] **Landing page + kurasa zote za umma** (SW/EN)
- [ ] Register / Login / Logout
- [ ] Dashboard home (huduma zote locked)
- [ ] Layout + PWA + design system

**Matokeo:** website nzuri inayoonekana, mtu anaweza kujisajili.

## Phase 2 — Billing + Panel *(mapato)*
- [ ] Payment clients (copy kutoka bot)
- [ ] SubscriptionBilling + `subscription.php` + webhooks
- [ ] **Kila huduma inanunuliwa peke yake** (service_key)
- [ ] PanelDetector + `panels.php` (auto-detect wizard)
- [ ] SmmProviderClient (SMM API v2 adapter)
- [ ] Cron: expire_subscriptions, expire_stale_payments

**Matokeo:** mtu analipa → huduma inawaka. Platform inauzwa.

## Phase 3 — Order Bot *(bidhaa ya kwanza)*
- [ ] WhatsAppCloudClient + BotRouter (phone_number_id → tenant)
- [ ] `whatsapp.php` — Cloud API wizard + kodisha namba
- [ ] PlatformNumber + NumberRental (namba za kukodisha)
- [ ] Geuza OrderBotHandler iwe multi-tenant
- [ ] BotConversation (state machine), BotOrder
- [ ] `orders.php`, `order-bot.php`
- [ ] Tenant payment gateways (wateja wa tenant wanalipa tenant)

**Matokeo:** bidhaa ya kwanza inauzwa. Mapato halisi.

## Phase 4 — Support Bot *(bidhaa ya pili)*
- [ ] SupportBotHandler (refill, status, cancel, speedup)
- [ ] GuaranteeMatcher + `guarantee-rules.php` (+ auto-import)
- [ ] `templates.php` (~30 templates, SW/EN)
- [ ] `support-bot.php` (bot settings, spam protection)
- [ ] Cron: sync_orders

**Matokeo:** bidhaa kamili. Sasa unamshinda mshindani.

## Phase 5 — AI Tickets *(bidhaa ya tatu)*
- [ ] DeepSeekClient + `tickets.php`
- [ ] Ticket, TicketMessage models
- [ ] `settings-ai.php`
- [ ] Widget ya kuweka kwenye website ya tenant

## Phase 6 — Super-Admin + Uboreshaji
- [ ] `hx-control/*` kamili
- [ ] Reports (MRR, churn, ukuaji)
- [ ] Activity log, audit trail
- [ ] Performance, caching, server scaling prep

## Phase 7 — Ukuaji *(baadaye)*
- [ ] Telegram Bot
- [ ] Broadcast
- [ ] Referrals
- [ ] My Staff
- [ ] **Baileys/QR** kama "Advanced" option (kwa onyo)

---

# SEHEMU 10: VERIFICATION (jinsi ya kupima)

**Phase 1:**
1. `mysql> source database/schema.sql` + seeds
2. Jaza `config/.env` (DB, APP_URL, APP_KEY, gateway keys)
3. Fungua `/home.php` → landing inaonekana, SW/EN inafanya kazi
4. Register → login → dashboard (huduma zote locked)

**Phase 2:**
5. `subscription.php` → chagua Support Bot → gateway → USSD push
6. Simulate webhook: `POST /webhooks/zenopay.php` `{order_id:<ref>, payment_status:"COMPLETED"}`
   → subscription inawaka → dashboard: "Support Bot ✅ Active, siku 30"
7. Panel: weka URL + API key → Detect → ✅ huduma 247 zimepatikana
8. Cron: weka `ends_at` iliyopita → `php cron/expire_subscriptions.php` → locked

**Phase 3:**
9. WhatsApp: weka token + phone_number_id → thibitisha
10. Tuma "Hi" kwa namba → bot inajibu menu
11. Weka order → inafika panel → `bot_orders` ina record

**Isolation (muhimu):**
12. Register tenant wa 2 → thibitisha **haoni** panel/orders/settings za tenant wa 1
13. Jaribu kubadilisha `tenant_id` kwenye form → **lazima ishindwe**

---

# SEHEMU 11: MAAMUZI YALIYOTHIBITISHWA

| # | Uamuzi | Sababu |
|---|---|---|
| 1 | **Cloud API pekee** (si Baileys) | Usalama = brand yako. Yeye anaonya wateja kuhusu ban; wewe hutaonya |
| 2 | **PHP pekee** (si Node.js) | Cloud API ni HTTP calls. Unaijua PHP. Stack moja |
| 3 | **Kila huduma peke yake** | Si kila mteja anataka zote. `service_key` kwenye subscriptions |
| 4 | **Namba tofauti kwa kila mteja** | Safi, inaaminika, hakuna mkanganyiko |
| 5 | **Subscription, si credits** | Banner ya hofu ya mshindani ni udhaifu |
| 6 | **Admin API key** (si User API) | Inaruhusu kuona orders zote za panel — muhimu kwa support bot |
| 7 | **Landing page Phase 1** | Bila mlango, nyumba nzuri haina maana |
| 8 | **Platform-first (fresh)** | Usivunje bot inayoingiza pesa sasa |
| 9 | **VPS moja kuanza** | Cloud API ni nyepesi. Server ya pili italipwa na wateja |
| 10 | **QR Phase 7** | Baada ya kuthibitisha Cloud API na wateja 20-30 |

---

# SEHEMU 12: HATARI NA TAHADHARI

| Hatari | Ukubwa | Kinga |
|---|---|---|
| **Webhook bandia** → subscription bure | 🔴 Kubwa | `verifyCompleted` na gateway, si payload |
| **APP_KEY ikivuja** → secrets zote | 🔴 Kubwa | .env nje ya webroot, permissions 600, si git |
| **Cloud API setup ngumu** → wateja wanaacha | 🟡 Kati | Wizard ya Kiswahili + namba za kukodisha + huduma ya setup |
| **Mteja anatumia namba yako kwa spam** | 🟡 Kati | Masharti + monitoring + kuzima haraka |
| **Meta inabadilisha bei** | 🟡 Kati | Angalia rate card kila robo; weka margin |
| **Panel API tofauti** | 🟡 Kati | PanelDetector + Manual Configuration fallback |
| **Tenant isolation ikivunjika** | 🔴 Kubwa | tenant_id kutoka session pekee + isolation tests |
| **Mshindani anaongeza mobile money** | 🟡 Kati | Kasi + Kiswahili + brand ya kienyeji |

---

# SEHEMU 13: JINSI YA KUANZA

Kwenye Claude Code / session mpya, sema:

> "Tunajenga **SMM Packages** — multi-tenant SaaS (PHP safi, MVC ya mkono, MySQL/PDO,
> hakuna Composer/framework). Tuko **Phase 1**.
>
> Msingi: iga conventions za `hiddenxcel/kuzapanel-bot` (DB.php PDO singleton, Env.php,
> BaseModel, Lang.php bilingual SW/EN, PWA, layout ya Inter + Font Awesome).
>
> Lengo la sasa: [foundation / schema / landing page].
>
> Sheria: kila kitu **tenant-aware** tangu mwanzo; secrets **encrypted** (Crypto.php AES-256-GCM);
> **usivunje** bot inayofanya kazi; maelezo kwa Kiswahili, code kwa Kiingereza.
>
> Onyesha unachofanya na kwa nini kabla ya kuandika code."

---

*Mwisho wa plan. Tumia pamoja na master prompt na competitive blueprint.*
