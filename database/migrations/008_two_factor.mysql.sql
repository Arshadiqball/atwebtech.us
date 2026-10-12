ALTER TABLE users
    ADD COLUMN totp_secret VARCHAR(255) NULL AFTER last_login_at,
    ADD COLUMN totp_confirmed_at DATETIME NULL AFTER totp_secret,
    ADD COLUMN totp_last_step BIGINT NULL AFTER totp_confirmed_at,
    ADD COLUMN recovery_hashes TEXT NULL AFTER totp_last_step;
