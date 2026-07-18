-- Telegram Order Bot: a second delivery channel alongside WhatsApp.
-- Each tenant connects their OWN Telegram bot (created via @BotFather) — the
-- bot token is the routing key. Gated on the same order_bot subscription.

CREATE TABLE IF NOT EXISTS tenant_telegram (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    bot_token_enc TEXT NOT NULL,
    bot_username VARCHAR(100) NULL,
    webhook_secret VARCHAR(64) NOT NULL,
    status ENUM('active', 'error', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tg_tenant (tenant_id),
    UNIQUE KEY uq_tg_secret (webhook_secret),
    CONSTRAINT fk_tg_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- webhook_secret is a random path token: Telegram posts to
-- /webhooks/telegram.php?s=<secret>, which maps to the tenant (never trust
-- the token in the payload).
