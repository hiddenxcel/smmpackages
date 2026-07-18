-- Referral program: tenants refer other tenants; the referrer earns account
-- credit (USD) when a referred tenant makes their first successful payment.
-- Credit is spent as a discount at checkout.

ALTER TABLE tenants
    ADD COLUMN referral_code VARCHAR(12) NULL UNIQUE AFTER lang,
    ADD COLUMN referred_by INT UNSIGNED NULL AFTER referral_code,
    ADD COLUMN referral_credit DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER referred_by,
    ADD COLUMN first_payment_done TINYINT(1) NOT NULL DEFAULT 0 AFTER referral_credit,
    ADD CONSTRAINT fk_tenant_referrer FOREIGN KEY (referred_by) REFERENCES tenants(id) ON DELETE SET NULL;

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
-- UNIQUE(referred_id): a referred tenant can only ever generate one reward (their first payment).
