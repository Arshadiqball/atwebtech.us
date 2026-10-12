ALTER TABLE users ADD COLUMN totp_secret TEXT NULL;
ALTER TABLE users ADD COLUMN totp_confirmed_at TEXT NULL;
ALTER TABLE users ADD COLUMN totp_last_step INTEGER NULL;
ALTER TABLE users ADD COLUMN recovery_hashes TEXT NULL;
