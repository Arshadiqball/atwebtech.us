-- Branded reports: logo, name and color on reports, a private share link, and scheduled email delivery.
CREATE TABLE IF NOT EXISTS report_settings (
    user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    brand_name VARCHAR(80) NULL,
    brand_color CHAR(7) NULL,
    logo_file VARCHAR(120) NULL,
    white_label TINYINT(1) NOT NULL DEFAULT 0,
    frequency VARCHAR(10) NOT NULL DEFAULT 'off',
    recipients VARCHAR(600) NULL,
    share_token CHAR(32) NULL,
    last_sent_at DATETIME NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_report_share (share_token),
    CONSTRAINT fk_report_settings_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
