-- SMM Packages — multi-tenant SaaS platform schema
-- B2B: resells WhatsApp bot + AI infrastructure to other SMM resellers.
-- Run on the local XAMPP MySQL database (name set in config/.env, default: smmpackages).
--
-- Conventions mirror hiddenxcel/kuzapanel-bot: InnoDB, utf8mb4,
-- INT UNSIGNED AUTO_INCREMENT PKs, TIMESTAMP created_at/updated_at, named FK constraints.
--
-- Decisions baked in (international-first + USDT + a la carte):
--   - lang ENUM('en','fr','sw') DEFAULT 'en'   (EN default, FR + SW)
--   - SaaS billing gateways: nowpayments, binance, snippe
--   - Pricing in USD base; each service sold on its own (service_key)
--   - platform_numbers carry country + currency (numbers from many countries)

-- =====================================================================
-- 5.1 CORE / TENANCY
-- =====================================================================

CREATE TABLE IF NOT EXISTS tenants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(30) NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    lang ENUM('en', 'fr', 'sw') NOT NULL DEFAULT 'en',
    referral_code VARCHAR(12) NULL UNIQUE,
    referred_by INT UNSIGNED NULL,
    referral_credit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    first_payment_done TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tenant_referrer FOREIGN KEY (referred_by) REFERENCES tenants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS referral_rewards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT UNSIGNED NOT NULL,
    referred_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(5) NOT NULL DEFAULT 'USD',
    payment_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reward_referred (referred_id),
    INDEX idx_reward_referrer (referrer_id),
    CONSTRAINT fk_reward_referrer FOREIGN KEY (referrer_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_reward_referred FOREIGN KEY (referred_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Referral program: referrer earns credit when a referred tenant first pays.
-- UNIQUE(referred_id) = one reward per referred tenant, ever.

CREATE TABLE IF NOT EXISTS plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    service_key ENUM('order_bot', 'support_bot', 'ai_tickets', 'number_rental') NOT NULL,
    price_monthly DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    price_yearly DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(5) NOT NULL DEFAULT 'USD',
    max_panels INT NOT NULL DEFAULT 1,
    max_numbers INT NOT NULL DEFAULT 1,
    max_orders_monthly INT NOT NULL DEFAULT 1000,
    max_messages_monthly INT NOT NULL DEFAULT 5000,
    max_refills_monthly INT NOT NULL DEFAULT 100,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- A la carte pricing catalog: one plan per service. Bundles = future presets.

CREATE TABLE IF NOT EXISTS subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NULL,
    service_key ENUM('order_bot', 'support_bot', 'ai_tickets', 'number_rental') NOT NULL,
    status ENUM('pending', 'active', 'expired', 'cancelled') NOT NULL DEFAULT 'pending',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    auto_renew TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subs_tenant_service_status (tenant_id, service_key, status),
    CONSTRAINT fk_subs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_subs_plan FOREIGN KEY (plan_id) REFERENCES plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- MUHIMU: each service has its OWN subscription (sold a la carte).

CREATE TABLE IF NOT EXISTS subscription_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NULL,
    subscription_id INT UNSIGNED NULL,
    gateway ENUM('nowpayments', 'binance', 'snippe') NOT NULL,
    transaction_ref VARCHAR(100) NOT NULL UNIQUE,
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(5) NOT NULL DEFAULT 'USD',
    months INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',
    raw_response TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_subpay_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_subpay_plan FOREIGN KEY (plan_id) REFERENCES plans(id),
    CONSTRAINT fk_subpay_sub FOREIGN KEY (subscription_id) REFERENCES subscriptions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- SaaS billing (tenant -> HiddenXcel). transaction_ref UNIQUE = replay protection.

-- =====================================================================
-- 5.2 PANEL & PROVIDER
-- =====================================================================

CREATE TABLE IF NOT EXISTS tenant_panels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    panel_type ENUM('perfectpanel', 'rentalpanel', 'custom') NOT NULL DEFAULT 'custom',
    api_url VARCHAR(255) NOT NULL,
    api_key_enc TEXT NOT NULL,
    api_version ENUM('v1', 'v2') NOT NULL DEFAULT 'v2',
    auth_method ENUM('header', 'param') NOT NULL DEFAULT 'param',
    last_checked_at DATETIME NULL,
    last_balance DECIMAL(12,2) NULL,
    balance_currency VARCHAR(5) NULL,
    services_count INT NULL,
    status ENUM('active', 'error', 'inactive') NOT NULL DEFAULT 'inactive',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_panels_tenant (tenant_id),
    CONSTRAINT fk_panels_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 5.3 WHATSAPP (Cloud API)
-- =====================================================================

CREATE TABLE IF NOT EXISTS tenant_whatsapp (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    source ENUM('own', 'rented') NOT NULL DEFAULT 'own',
    cloud_api_token_enc TEXT NULL,
    phone_number_id VARCHAR(50) NOT NULL UNIQUE,
    waba_id VARCHAR(50) NULL,
    verify_token VARCHAR(100) NULL,
    display_number VARCHAR(30) NULL,
    status ENUM('active', 'error', 'inactive') NOT NULL DEFAULT 'inactive',
    bot_type ENUM('order', 'support', 'both') NOT NULL DEFAULT 'both',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_wa_tenant (tenant_id),
    CONSTRAINT fk_wa_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- phone_number_id UNIQUE = webhook router key (Meta payload -> tenant).

CREATE TABLE IF NOT EXISTS platform_numbers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    display_number VARCHAR(30) NOT NULL UNIQUE,
    phone_number_id VARCHAR(50) NOT NULL UNIQUE,
    cloud_api_token_enc TEXT NOT NULL,
    waba_id VARCHAR(50) NULL,
    country VARCHAR(60) NULL,
    country_code VARCHAR(5) NULL,
    currency VARCHAR(5) NOT NULL DEFAULT 'USD',
    monthly_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('available', 'rented', 'suspended') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Your own rentable numbers (many countries), each with its own currency + cost.

CREATE TABLE IF NOT EXISTS number_rentals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    platform_number_id INT UNSIGNED NOT NULL,
    subscription_id INT UNSIGNED NULL,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    status ENUM('active', 'expired', 'revoked') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rentals_tenant (tenant_id),
    CONSTRAINT fk_rentals_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_rentals_number FOREIGN KEY (platform_number_id) REFERENCES platform_numbers(id),
    CONSTRAINT fk_rentals_sub FOREIGN KEY (subscription_id) REFERENCES subscriptions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 5.4 BOT CONFIG & DATA
-- =====================================================================

CREATE TABLE IF NOT EXISTS tenant_bot_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    bot_type ENUM('order', 'support') NOT NULL,
    settings JSON NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_botsettings_tenant_type (tenant_id, bot_type),
    CONSTRAINT fk_botsettings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS response_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NULL,
    template_key VARCHAR(50) NOT NULL,
    lang ENUM('en', 'fr', 'sw') NOT NULL DEFAULT 'en',
    content TEXT NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_template_tenant_key_lang (tenant_id, template_key, lang),
    CONSTRAINT fk_template_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- tenant_id NULL = platform default template.

CREATE TABLE IF NOT EXISTS guarantee_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    panel_id INT UNSIGNED NULL,
    rule_type ENUM('no_guarantee', 'guarantee') NOT NULL,
    keyword VARCHAR(100) NOT NULL,
    refill_days INT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rules_tenant (tenant_id),
    CONSTRAINT fk_rules_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_rules_panel FOREIGN KEY (panel_id) REFERENCES tenant_panels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- panel_id NULL = tenant-wide rule.

CREATE TABLE IF NOT EXISTS bot_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    panel_id INT UNSIGNED NULL,
    provider_order_id VARCHAR(50) NULL,
    customer_phone VARCHAR(30) NOT NULL,
    customer_id INT UNSIGNED NULL,
    service_id VARCHAR(50) NULL,
    service_name VARCHAR(190) NULL,
    link VARCHAR(255) NULL,
    quantity INT UNSIGNED NULL,
    amount DECIMAL(12,2) NULL,
    payment_status ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending',
    paid_from ENUM('wallet', 'gateway') NULL,
    order_error VARCHAR(255) NULL,
    charge DECIMAL(12,4) NULL,
    status VARCHAR(30) NULL,
    refill_status VARCHAR(30) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_botorders_tenant_status (tenant_id, status),
    INDEX idx_botorders_provider (provider_order_id),
    INDEX idx_botorders_customer (customer_id),
    CONSTRAINT fk_botorders_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_botorders_panel FOREIGN KEY (panel_id) REFERENCES tenant_panels(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Wallet-based Order Bot tables (ported from kuzapanel-bot, multi-tenant).
-- Full rationale in database/migrations/005_wallet_orders.sql.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bot_customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    phone VARCHAR(30) NOT NULL,
    name VARCHAR(150) NULL,
    lang VARCHAR(5) NOT NULL DEFAULT 'en',
    balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_spent DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    referral_code VARCHAR(12) NULL,
    referred_by INT UNSIGNED NULL,
    referral_earnings DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    first_deposit_done TINYINT(1) NOT NULL DEFAULT 0,
    last_payment_phone VARCHAR(30) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_botcust_tenant_phone (tenant_id, phone),
    UNIQUE KEY uq_botcust_tenant_refcode (tenant_id, referral_code),
    INDEX idx_botcust_tenant (tenant_id),
    CONSTRAINT fk_botcust_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_botcust_referrer FOREIGN KEY (referred_by) REFERENCES bot_customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bot_services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    panel_id INT UNSIGNED NULL,
    provider_service_id VARCHAR(50) NOT NULL,
    platform VARCHAR(50) NOT NULL,
    category VARCHAR(80) NULL,
    name VARCHAR(190) NOT NULL,
    unit_label VARCHAR(50) NOT NULL DEFAULT 'Followers',
    cost_price DECIMAL(12,4) NULL,
    my_price DECIMAL(12,4) NOT NULL,
    min_quantity INT UNSIGNED NOT NULL DEFAULT 1,
    max_quantity INT UNSIGNED NOT NULL DEFAULT 100000,
    link_instructions TEXT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_botsvc_tenant_status (tenant_id, status),
    INDEX idx_botsvc_tenant_platform (tenant_id, platform),
    INDEX idx_botsvc_tenant_cat (tenant_id, platform, category),
    CONSTRAINT fk_botsvc_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_botsvc_panel FOREIGN KEY (panel_id) REFERENCES tenant_panels(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bot_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    type ENUM('wallet_topup', 'order_payment') NOT NULL DEFAULT 'wallet_topup',
    customer_id INT UNSIGNED NULL,
    order_id INT UNSIGNED NULL,
    gateway VARCHAR(50) NOT NULL,
    transaction_ref VARCHAR(120) NULL,
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_botpay_tenant_ref (tenant_id, transaction_ref),
    INDEX idx_botpay_tenant_status (tenant_id, status),
    INDEX idx_botpay_customer (customer_id),
    CONSTRAINT fk_botpay_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_botpay_customer FOREIGN KEY (customer_id) REFERENCES bot_customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_botpay_order FOREIGN KEY (order_id) REFERENCES bot_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- bot_orders.customer_id -> bot_customers (added after bot_customers exists).
ALTER TABLE bot_orders
    ADD CONSTRAINT fk_botorders_customer FOREIGN KEY (customer_id) REFERENCES bot_customers(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS bot_conversations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    customer_phone VARCHAR(30) NOT NULL,
    bot_type ENUM('order', 'support') NOT NULL,
    state VARCHAR(50) NOT NULL DEFAULT 'IDLE',
    context JSON NULL,
    expires_at DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_conv_tenant_phone_type (tenant_id, customer_phone, bot_type),
    CONSTRAINT fk_conv_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bot_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    customer_phone VARCHAR(30) NOT NULL,
    direction ENUM('in', 'out') NOT NULL,
    message TEXT NULL,
    template_key VARCHAR(50) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_botmsg_tenant_created (tenant_id, created_at),
    CONSTRAINT fk_botmsg_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 5.5 TICKETS & TENANT PAYMENTS
-- =====================================================================

CREATE TABLE IF NOT EXISTS tenant_payment_gateways (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    gateway VARCHAR(50) NOT NULL,
    api_key_enc TEXT NULL,
    webhook_secret_enc TEXT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tpg_tenant (tenant_id),
    CONSTRAINT fk_tpg_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Tenant's OWN gateway (their customers pay them). Kept wide: tenant picks any gateway.

CREATE TABLE IF NOT EXISTS tenant_ai (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    deepseek_api_key_enc TEXT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ai_tenant (tenant_id),
    CONSTRAINT fk_ai_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    customer_identifier VARCHAR(190) NULL,
    subject VARCHAR(255) NULL,
    status ENUM('open', 'pending', 'resolved', 'closed') NOT NULL DEFAULT 'open',
    priority ENUM('low', 'normal', 'high') NOT NULL DEFAULT 'normal',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tickets_tenant_status (tenant_id, status),
    CONSTRAINT fk_tickets_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ticket_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    sender ENUM('customer', 'ai', 'staff') NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticketmsg_ticket (ticket_id),
    CONSTRAINT fk_ticketmsg_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 5.6 PLATFORM
-- =====================================================================

CREATE TABLE IF NOT EXISTS superadmins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_type ENUM('tenant', 'superadmin', 'system') NOT NULL,
    actor_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    details JSON NULL,
    ip VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_actor (actor_type, actor_id),
    INDEX idx_activity_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(100) NOT NULL,
    action VARCHAR(50) NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    window_start DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ratelimit_id_action (identifier, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
