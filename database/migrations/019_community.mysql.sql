-- Community inbox: comments on connected channels, with replies sent from atwebtech.
CREATE TABLE IF NOT EXISTS comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    channel_id BIGINT UNSIGNED NOT NULL,
    post_id BIGINT UNSIGNED NULL,
    external_id VARCHAR(191) NOT NULL,
    item_id VARCHAR(191) NOT NULL,
    item_title VARCHAR(300) NULL,
    item_url VARCHAR(500) NULL,
    author_name VARCHAR(191) NOT NULL,
    author_avatar VARCHAR(500) NULL,
    author_url VARCHAR(500) NULL,
    is_own TINYINT(1) NOT NULL DEFAULT 0,
    body TEXT NOT NULL,
    likes INT UNSIGNED NULL,
    replies INT UNSIGNED NULL,
    published_at DATETIME NOT NULL,
    state VARCHAR(10) NOT NULL DEFAULT 'open',
    reply_body TEXT NULL,
    replied_at DATETIME NULL,
    fetched_at DATETIME NOT NULL,
    UNIQUE KEY uq_comment (channel_id, external_id),
    KEY idx_comments_inbox (user_id, state, published_at),
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_channel FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE channels
    ADD COLUMN inbox_checked_at DATETIME NULL,
    ADD COLUMN inbox_error VARCHAR(300) NULL;
