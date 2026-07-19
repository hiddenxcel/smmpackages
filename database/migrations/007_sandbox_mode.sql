-- Sandbox Mode: a reseller signs up FREE and gets every service in a "sandbox"
-- state. They can connect a panel, import services, set prices and TEST the bot
-- on their own number — but the bot never answers real customers until they hit
-- "Go Live" and pay, which flips the row to 'active'.
--
-- A new status value 'sandbox' sits alongside pending/active/expired/cancelled.
-- The live gate (isServiceActive) still only trusts 'active', so a sandbox
-- service is NOT live to the public — exactly the intended isolation.
ALTER TABLE subscriptions
    MODIFY COLUMN status ENUM('pending', 'sandbox', 'active', 'expired', 'cancelled') NOT NULL DEFAULT 'pending';

-- Per (tenant, service) test-mode allow-list: a phone number the reseller uses
-- to try their own bot while in sandbox. The webhook lets these through the gate
-- (as "test") so the reseller can experience the full flow before paying.
-- (Stored in bot settings JSON is also possible, but a column keeps the webhook
--  path a single cheap lookup.) We reuse tenant_bot_settings.settings JSON via
--  the model instead — no new table needed. This migration only widens the enum.
