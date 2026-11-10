-- Automations: blog-to-social, weekly AI plans and auto-replies. Recurring posts and the weekly summary email.
CREATE TABLE IF NOT EXISTS automations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    kind VARCHAR(12) NOT NULL,
    name VARCHAR(80) NOT NULL,
    config TEXT NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_item_id BIGINT UNSIGNED NULL,
    last_run_at DATETIME NULL,
    last_result VARCHAR(300) NULL,
    made INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    KEY idx_automations_user (user_id),
    KEY idx_automations_due (kind, enabled, last_run_at),
    CONSTRAINT fk_automations_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE posts
    ADD COLUMN repeat_rule VARCHAR(10) NULL,
    ADD COLUMN automation_id BIGINT UNSIGNED NULL;

ALTER TABLE comments ADD COLUMN auto_reply TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE users ADD COLUMN digest_sent_at DATETIME NULL;
