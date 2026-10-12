ALTER TABLE waitlist_signups ADD COLUMN utm_source TEXT NULL;
ALTER TABLE waitlist_signups ADD COLUMN utm_medium TEXT NULL;
ALTER TABLE waitlist_signups ADD COLUMN utm_campaign TEXT NULL;
ALTER TABLE waitlist_signups ADD COLUMN utm_content TEXT NULL;
ALTER TABLE waitlist_signups ADD COLUMN utm_term TEXT NULL;
ALTER TABLE waitlist_signups ADD COLUMN click_kind TEXT NULL;
ALTER TABLE waitlist_signups ADD COLUMN click_id TEXT NULL;

ALTER TABLE users ADD COLUMN utm_source TEXT NULL;
ALTER TABLE users ADD COLUMN utm_medium TEXT NULL;
ALTER TABLE users ADD COLUMN utm_campaign TEXT NULL;
ALTER TABLE users ADD COLUMN utm_content TEXT NULL;
ALTER TABLE users ADD COLUMN utm_term TEXT NULL;
ALTER TABLE users ADD COLUMN click_kind TEXT NULL;
ALTER TABLE users ADD COLUMN click_id TEXT NULL;
