-- Service categories for the WhatsApp Order Bot.
--
-- WhatsApp interactive lists allow at most 10 rows, so a tenant with many
-- services per platform couldn't show them all. A `category` groups services
-- within a platform (e.g. Instagram → Followers / Likes / Views), so the bot
-- can offer Platform → Category → Service and keep every list under 10.
--
-- category is imported from the panel's own service category and can be edited
-- by the tenant. NULL / '' = uncategorised (the bot treats these as one group).
ALTER TABLE bot_services
    ADD COLUMN category VARCHAR(80) NULL AFTER platform,
    ADD INDEX idx_botsvc_tenant_cat (tenant_id, platform, category);
