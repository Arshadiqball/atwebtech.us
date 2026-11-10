-- Branded reports: logo, name and color on reports, a private share link, and scheduled email delivery.
CREATE TABLE IF NOT EXISTS report_settings (
    user_id INTEGER NOT NULL PRIMARY KEY REFERENCES users (id) ON DELETE CASCADE,
    brand_name TEXT NULL,
    brand_color TEXT NULL,
    logo_file TEXT NULL,
    white_label INTEGER NOT NULL DEFAULT 0,
    frequency TEXT NOT NULL DEFAULT 'off',
    recipients TEXT NULL,
    share_token TEXT NULL UNIQUE,
    last_sent_at TEXT NULL,
    updated_at TEXT NOT NULL
);
