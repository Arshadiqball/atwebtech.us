-- Reporting and blocking in the comment inbox. A reported comment is taken out of the inbox and kept here
-- for the atwebtech team to look at. A blocked person's comments no longer come into that workspace.
CREATE TABLE IF NOT EXISTS comment_reports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    channel_id BIGINT UNSIGNED NOT NULL,
    external_id VARCHAR(191) NOT NULL,
    network VARCHAR(30) NOT NULL,
    author_name VARCHAR(191) NOT NULL,
    body TEXT NOT NULL,
    item_url VARCHAR(600) NULL,
    reason VARCHAR(30) NOT NULL,
    reported_by BIGINT UNSIGNED NULL,
    handled_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    KEY idx_comment_reports_channel (channel_id, external_id),
    KEY idx_comment_reports_created (created_at),
    CONSTRAINT fk_comment_reports_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blocked_authors (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(30) NOT NULL,
    author_name VARCHAR(191) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_blocked_author (user_id, provider, author_name),
    CONSTRAINT fk_blocked_authors_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
