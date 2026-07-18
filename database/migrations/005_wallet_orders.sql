-- =====================================================================
-- Wallet-based Order Bot (ported from kuzapanel-bot, made multi-tenant).
--
-- kuzapanel-bot is a single-shop bot with a customer wallet: a customer
-- tops up a balance, and placing an order debits that balance. This brings
-- the SAME model to SMM Packages, but every row is scoped by tenant_id so
-- each reseller has an isolated shop (customers, services, payments).
--
-- Mapping from kuzapanel -> here:
--   customers        -> bot_customers   (+ tenant_id)
--   services         -> bot_services    (+ tenant_id; tenant sets my_price)
--   payments         -> bot_payments    (+ tenant_id)
--   orders.amount…   -> bot_orders      (columns added below)
--   providers        -> tenant_panels   (already exists)
--   payment_gateways -> tenant_payment_gateways (already exists)
--   sessions         -> bot_conversations (already exists)
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. bot_customers — a tenant's end-customer + their wallet balance.
--    UNIQUE (tenant_id, phone): the same phone is a different customer
--    for a different tenant (isolation).
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

-- ---------------------------------------------------------------------
-- 2. bot_services — the tenant's saleable services with THEIR OWN price.
--    Imported from the tenant's panel (provider_service_id links back),
--    but my_price is what the tenant charges their customer (their markup).
--    cost_price is optional bookkeeping (what the panel charges the tenant).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bot_services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    panel_id INT UNSIGNED NULL,
    provider_service_id VARCHAR(50) NOT NULL,
    platform VARCHAR(50) NOT NULL,
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
    CONSTRAINT fk_botsvc_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_botsvc_panel FOREIGN KEY (panel_id) REFERENCES tenant_panels(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 3. bot_payments — a customer's mobile-money/crypto payment (wallet top-up
--    or direct order payment). transaction_ref is the gateway reference used
--    by the webhook to mark it success exactly once (replay guard lives in
--    the model). gateway is free-form VARCHAR because each tenant picks their
--    own gateways (see tenant_payment_gateways).
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- 4. bot_orders — add wallet/pricing columns so an order carries what the
--    customer paid and how. `charge` already exists (kept). New columns:
--      amount          -> price charged to the customer (my_price-based)
--      payment_status  -> pending | paid | failed
--      paid_from       -> wallet | gateway
--      customer_id     -> link to bot_customers (nullable; phone is legacy key)
--    Existing rows get safe defaults.
-- ---------------------------------------------------------------------
ALTER TABLE bot_orders
    ADD COLUMN customer_id INT UNSIGNED NULL AFTER customer_phone,
    ADD COLUMN amount DECIMAL(12,2) NULL AFTER quantity,
    ADD COLUMN payment_status ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending' AFTER amount,
    ADD COLUMN paid_from ENUM('wallet', 'gateway') NULL AFTER payment_status,
    ADD COLUMN order_error VARCHAR(255) NULL AFTER paid_from,
    ADD CONSTRAINT fk_botorders_customer FOREIGN KEY (customer_id) REFERENCES bot_customers(id) ON DELETE SET NULL;
