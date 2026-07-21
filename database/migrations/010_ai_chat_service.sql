-- 010_ai_chat_service.sql
-- Adds the "ai_chat" a-la-carte service (AI Support inside the Order Bot, $5/mo).
-- The service_key ENUMs on plans + subscriptions must learn the new value, or
-- inserts silently store '' (MySQL's out-of-range enum fallback).
--
-- Safe to re-run: ALTER ... MODIFY is idempotent for the target definition.

ALTER TABLE plans
    MODIFY service_key ENUM('order_bot','support_bot','ai_tickets','ai_chat','number_rental') NOT NULL;

ALTER TABLE subscriptions
    MODIFY service_key ENUM('order_bot','support_bot','ai_tickets','ai_chat','number_rental') NOT NULL;

-- Repair any ai_chat plan row that was inserted before the enum knew the value
-- (its service_key landed as '' — match it by code and set it correctly).
UPDATE plans SET service_key = 'ai_chat' WHERE code = 'ai_chat' AND service_key = '';
