ALTER TABLE waitlist_signups
    ADD COLUMN utm_source VARCHAR(80) NULL AFTER source,
    ADD COLUMN utm_medium VARCHAR(80) NULL AFTER utm_source,
    ADD COLUMN utm_campaign VARCHAR(120) NULL AFTER utm_medium,
    ADD COLUMN utm_content VARCHAR(120) NULL AFTER utm_campaign,
    ADD COLUMN utm_term VARCHAR(120) NULL AFTER utm_content,
    ADD COLUMN click_kind VARCHAR(12) NULL AFTER utm_term,
    ADD COLUMN click_id VARCHAR(180) NULL AFTER click_kind;

ALTER TABLE users
    ADD COLUMN utm_source VARCHAR(80) NULL AFTER last_login_at,
    ADD COLUMN utm_medium VARCHAR(80) NULL AFTER utm_source,
    ADD COLUMN utm_campaign VARCHAR(120) NULL AFTER utm_medium,
    ADD COLUMN utm_content VARCHAR(120) NULL AFTER utm_campaign,
    ADD COLUMN utm_term VARCHAR(120) NULL AFTER utm_content,
    ADD COLUMN click_kind VARCHAR(12) NULL AFTER utm_term,
    ADD COLUMN click_id VARCHAR(180) NULL AFTER click_kind;
