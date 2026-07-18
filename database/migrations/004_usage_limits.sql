-- CHANGE-REQUEST-02: monthly usage limits per plan (for the dashboard "MATUMIZI YAKO" meters).
-- Idempotent-ish: guard with an existence check per column is not portable in plain SQL,
-- so run once. Safe defaults mirror the a-la-carte plan sizing.

ALTER TABLE plans
  ADD COLUMN max_orders_monthly   INT NOT NULL DEFAULT 1000 AFTER max_numbers,
  ADD COLUMN max_messages_monthly INT NOT NULL DEFAULT 5000 AFTER max_orders_monthly,
  ADD COLUMN max_refills_monthly  INT NOT NULL DEFAULT 100  AFTER max_messages_monthly;
