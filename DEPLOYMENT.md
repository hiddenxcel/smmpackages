# SMM Packages — Deployment Guide

Everything needed to take the platform from the local XAMPP build to a live,
secure production server. Read top to bottom the first time.

---

## 0. What you're deploying

A plain-PHP (no Composer) multi-tenant SaaS. Requirements:

- **PHP 8.1+** with `pdo_mysql`, `openssl`, `curl`, `mbstring`, `json`, `gd`
- **MySQL 5.7+ / MariaDB 10.3+**
- **Apache** (or nginx) with the ability to set the document root
- **HTTPS** (mandatory — WhatsApp & Telegram webhooks require it; payment gateways too)

---

## 1. Web root — the single most important step

The project ships with `app/`, `config/`, `database/`, `cron/` **outside** the
public folder. In production, point the vhost **DocumentRoot at `public/` only**:

```apache
<VirtualHost *:443>
    ServerName smmpackages.com
    DocumentRoot /var/www/smmpackages/public

    <Directory /var/www/smmpackages/public>
        AllowOverride All
        Require all granted
    </Directory>

    SSLEngine on
    SSLCertificateFile      /etc/letsencrypt/live/smmpackages.com/fullchain.pem
    SSLCertificateKeyFile   /etc/letsencrypt/live/smmpackages.com/privkey.pem
</VirtualHost>
```

With DocumentRoot at `public/`, `app/config/database/cron` are physically
unreachable over the web. The bundled `.htaccess` files (root + per-directory
`Require all denied`) are a fallback for when the docroot is the project root
(e.g. local XAMPP) — keep them, but don't rely on them in production.

**Verify after go-live:**
```bash
curl -I https://smmpackages.com/../config/.env   # must NOT return the file
curl -I https://smmpackages.com/config/.env       # 403/404, never 200
```

---

## 2. Database

```bash
mysql -u root -p -e "CREATE DATABASE smmpackages CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u smm_user -p smmpackages < database/schema.sql
# schema.sql already includes the referral + telegram columns/tables.
# If upgrading an older DB, also run the migrations in database/migrations/*.sql in order.

# Seeds:
php database/seed_plans.php
php database/seed_templates.php
php database/seed_superadmin.php <username> '<strong-password>'
```

Create a **dedicated MySQL user** (not root) with rights on the `smmpackages`
DB only, and use it in `.env`.

---

## 3. config/.env

Copy `config/.env.example` → `config/.env` and fill it. **Never commit `.env`.**
File permissions: `chmod 600 config/.env`, owned by the web user.

```ini
APP_ENV=production                 # turns OFF display_errors, ON secure cookies
APP_NAME="SMM Packages"
APP_URL=https://smmpackages.com/public   # or https://smmpackages.com if docroot=public

# 32-byte base64 master key for AES-256-GCM. Generate ONCE, then never change it
# (changing it makes every stored secret undecryptable):
#   php -r "echo base64_encode(random_bytes(32));"
APP_KEY=base64:XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX=

DB_HOST=localhost
DB_NAME=smmpackages
DB_USER=smm_user
DB_PASS=<db-password>

# --- SaaS billing gateways (tenant -> HiddenXcel) ---
NOWPAYMENTS_API_KEY=
NOWPAYMENTS_IPN_SECRET=            # REQUIRED — without it webhook sig checks are skipped
BINANCE_PAY_API_KEY=
BINANCE_PAY_API_SECRET=
SNIPPE_API_KEY=
SNIPPE_WEBHOOK_SECRET=

# --- Meta WhatsApp Cloud API ---
META_APP_SECRET=                  # verifies X-Hub-Signature-256 on the WA webhook
META_VERIFY_TOKEN=                # any random string; you enter the SAME value in Meta

SUPPORT_WHATSAPP_URL=https://wa.me/255XXXXXXXXX
```

> ⚠️ While any gateway secret is blank, that gateway's webhook runs in
> **dev-allow** mode (signature check skipped). Fill every secret before taking
> real money.

---

## 4. Cron jobs

Add these to the server crontab (`crontab -e` as the web user). Adjust the PHP
path and project path.

```cron
# Expire subscriptions whose term ended (locks the service) — hourly
0 * * * *    php /var/www/smmpackages/cron/expire_subscriptions.php   >> /var/log/smm-cron.log 2>&1

# Fail abandoned pending payments + refund reserved referral credit — every 20 min
*/20 * * * * php /var/www/smmpackages/cron/expire_stale_payments.php  >> /var/log/smm-cron.log 2>&1

# Reset stale bot conversations — every 20 min
*/20 * * * * php /var/www/smmpackages/cron/cleanup_conversations.php  >> /var/log/smm-cron.log 2>&1

# Poll in-flight bot orders' status from panels — every 5 min
*/5 * * * *  php /var/www/smmpackages/cron/sync_orders.php            >> /var/log/smm-cron.log 2>&1

# Drain queued WhatsApp broadcasts in rate-safe batches — every 2 min
*/2 * * * *  php /var/www/smmpackages/cron/send_broadcasts.php        >> /var/log/smm-cron.log 2>&1
```

(On Windows/XAMPP use Task Scheduler with the same commands and intervals.)

---

## 5. Webhooks to register with each provider

All must be HTTPS. Paths assume docroot = `public/`.

| Provider | URL | Notes |
|---|---|---|
| **Meta WhatsApp** | `https://smmpackages.com/webhooks/whatsapp.php` | Set Verify Token = `META_VERIFY_TOKEN`. Subscribe to `messages`. |
| **NOWPayments** | `https://smmpackages.com/webhooks/nowpayments.php` | Set IPN secret = `NOWPAYMENTS_IPN_SECRET`. |
| **Binance Pay** | `https://smmpackages.com/webhooks/binance.php` | In merchant webhook settings. |
| **Snippe** | `https://smmpackages.com/webhooks/snippe.php` | Set webhook secret = `SNIPPE_WEBHOOK_SECRET`. |
| **Telegram** | set per-tenant automatically | Each tenant's `telegram.php` calls `setWebhook` on connect. |

Tenants set their own WhatsApp webhook to the same `whatsapp.php` URL from the
in-app **WhatsApp Setup** page (it shows them the URL + verify token). Routing to
the right tenant is by `phone_number_id` (WhatsApp) / `?s=secret` (Telegram).

---

## 6. Post-deploy checklist

- [ ] DocumentRoot = `public/`; `curl` confirms `.env` is not served
- [ ] `APP_ENV=production` (display_errors off, secure cookies on)
- [ ] `APP_KEY` generated and backed up somewhere safe (losing it = losing all stored secrets)
- [ ] `config/.env` is `chmod 600`, outside git
- [ ] Dedicated MySQL user (not root)
- [ ] Super-admin password changed from any seed placeholder (via `/hx-control/settings.php`)
- [ ] All 5 crons scheduled and logging
- [ ] Every gateway + Meta secret filled (no dev-allow left)
- [ ] hx-control reachable only by you (consider IP allowlist / basic-auth on `/hx-control/` at the web-server level for defence in depth)
- [ ] A real end-to-end test: register → buy a service (small amount) → confirm webhook activates it → connect a panel → send a WhatsApp test message

---

## 7. Security notes (already built in)

- Secrets at rest: **AES-256-GCM** (`Crypto.php`), key = `APP_KEY`.
- Passwords: **bcrypt**; session id regenerated on login.
- Sessions: **HttpOnly + SameSite=Lax**, Secure when HTTPS.
- CSRF token on every dashboard POST.
- Webhooks verify the provider signature and never trust the payload; billing
  activation is **replay-guarded** (`transaction_ref` UNIQUE + one-shot success).
- Tenant isolation: `tenant_id` always comes from the session, never from input.
- SQL: prepared statements only; `LIMIT` values are integer-clamped; `LIKE` escaped.

Known minor item: number-rental uses check-then-act (a theoretical race if two
tenants rent the same number in the same instant). Low risk on a single VPS; add
a transaction/`SELECT ... FOR UPDATE` if you scale out.
