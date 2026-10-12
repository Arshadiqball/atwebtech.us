CREATE TABLE IF NOT EXISTS channels (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(40) NOT NULL,
    external_id VARCHAR(191) NOT NULL,
    name VARCHAR(190) NOT NULL,
    kind VARCHAR(40) NOT NULL DEFAULT 'Channel',
    avatar_url VARCHAR(500) NULL,
    access_token TEXT NULL,
    refresh_token TEXT NULL,
    token_expires_at DATETIME NULL,
    scopes VARCHAR(500) NULL,
    posting_goal TINYINT UNSIGNED NULL,
    schedule VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_channels_account (user_id, provider, external_id),
    CONSTRAINT fk_channels_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
