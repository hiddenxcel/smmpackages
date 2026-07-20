-- 008_ticket_form.sql
-- SMMGen-style structured tickets: Category (AI/Human) -> Subcategory -> Order ID.
-- Adds the form fields to the tickets table.

ALTER TABLE tickets
    ADD COLUMN category ENUM('ai', 'human') NOT NULL DEFAULT 'ai' AFTER customer_identifier,
    ADD COLUMN subcategory VARCHAR(40) NULL AFTER category,
    ADD COLUMN order_ref VARCHAR(60) NULL AFTER subcategory;
