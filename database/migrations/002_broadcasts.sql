-- Broadcast: a tenant sends one message to many of their WhatsApp customers.
-- Recipients are queued and delivered in rate-safe batches by a cron job.
-- NOTE: WhatsApp Cloud API only allows free-form messages inside the 24h
-- customer-service window; outside it, an approved template is required. This
-- feature targets customers seen within the window (default 24h).

CREATE TABLE IF NOT EXISTS broadcasts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    status ENUM('queued', 'sending', 'completed', 'cancelled') NOT NULL DEFAULT 'queued',
    total INT UNSIGNED NOT NULL DEFAULT 0,
    sent INT UNSIGNED NOT NULL DEFAULT 0,
    failed INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_broadcasts_tenant (tenant_id),
    INDEX idx_broadcasts_status (status),
    CONSTRAINT fk_broadcasts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS broadcast_recipients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    broadcast_id INT UNSIGNED NOT NULL,
    customer_phone VARCHAR(30) NOT NULL,
    status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bcr_broadcast_status (broadcast_id, status),
    CONSTRAINT fk_bcr_broadcast FOREIGN KEY (broadcast_id) REFERENCES broadcasts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
