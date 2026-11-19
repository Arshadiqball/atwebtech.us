-- Push updates an admin sends to app users from /admin/push: what was sent, to whom, and how it went.
CREATE TABLE IF NOT EXISTS push_broadcasts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(120) NOT NULL,
    body VARCHAR(300) NOT NULL,
    url VARCHAR(120) NULL,
    audience VARCHAR(20) NOT NULL,
    in_inbox TINYINT(1) NOT NULL DEFAULT 1,
    people INT UNSIGNED NOT NULL DEFAULT 0,
    devices INT UNSIGNED NOT NULL DEFAULT 0,
    delivered INT UNSIGNED NOT NULL DEFAULT 0,
    failed INT UNSIGNED NOT NULL DEFAULT 0,
    sent_by VARCHAR(190) NULL,
    created_at DATETIME NOT NULL,
    KEY idx_push_broadcasts_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
