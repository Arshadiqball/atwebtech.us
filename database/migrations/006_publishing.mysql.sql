CREATE TABLE IF NOT EXISTS media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    kind VARCHAR(10) NOT NULL,
    mime VARCHAR(100) NULL,
    size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    expected_size BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(190) NOT NULL,
    path VARCHAR(255) NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'uploading',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_media_user (user_id, status),
    CONSTRAINT fk_media_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE posts
    ADD COLUMN channel_id BIGINT UNSIGNED NULL AFTER channel,
    ADD COLUMN media_id BIGINT UNSIGNED NULL AFTER idea_id,
    ADD COLUMN title VARCHAR(100) NULL AFTER body,
    ADD COLUMN meta VARCHAR(1000) NULL AFTER title,
    ADD COLUMN tags VARCHAR(255) NULL AFTER meta,
    ADD COLUMN sent_at DATETIME NULL AFTER planned_at,
    ADD COLUMN external_id VARCHAR(100) NULL AFTER sent_at,
    ADD COLUMN external_url VARCHAR(255) NULL AFTER external_id,
    ADD COLUMN error VARCHAR(500) NULL AFTER external_url,
    ADD COLUMN attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER error,
    ADD KEY idx_posts_due (status, planned_at),
    ADD CONSTRAINT fk_posts_channel FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_posts_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE SET NULL;

ALTER TABLE channels
    ADD COLUMN queue_intro_closed_at DATETIME NULL AFTER schedule;
