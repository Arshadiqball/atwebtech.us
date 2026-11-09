CREATE TABLE IF NOT EXISTS hashtag_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(60) NOT NULL,
    tags VARCHAR(1000) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_hashtag_groups_user (user_id),
    CONSTRAINT fk_hashtag_groups_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feeds (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    url VARCHAR(500) NOT NULL,
    title VARCHAR(200) NOT NULL,
    site_url VARCHAR(500) NULL,
    last_checked_at DATETIME NULL,
    last_error VARCHAR(300) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_feeds_user (user_id),
    CONSTRAINT fk_feeds_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    feed_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    guid_hash CHAR(64) NOT NULL,
    title VARCHAR(300) NOT NULL,
    link VARCHAR(500) NOT NULL,
    summary VARCHAR(600) NULL,
    published_at DATETIME NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_feed_item (feed_id, guid_hash),
    KEY idx_feed_items_user (user_id, status, published_at),
    CONSTRAINT fk_feed_items_feed FOREIGN KEY (feed_id) REFERENCES feeds (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
    ADD COLUMN utm_tracking_enabled TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN utm_tracking_campaign VARCHAR(80) NULL;

ALTER TABLE channels ADD COLUMN paused_at DATETIME NULL;
