-- 009_binance_orderid.sql
-- Binance "internal transfer" (no-KYB) payments: the payer sends USDT to the
-- merchant's Binance ID and reports the Binance Order ID, which we verify via
-- the Spot API. Store that Order ID (UNIQUE) as the replay guard.

ALTER TABLE subscription_payments
    ADD COLUMN binance_order_id VARCHAR(64) NULL AFTER raw_response,
    ADD UNIQUE KEY uq_subpay_binance_order (binance_order_id);

ALTER TABLE bot_payments
    ADD COLUMN binance_order_id VARCHAR(64) NULL AFTER status,
    ADD UNIQUE KEY uq_botpay_binance_order (tenant_id, binance_order_id);
