CREATE TABLE IF NOT EXISTS post_metrics (
    post_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    channel_id BIGINT UNSIGNED NOT NULL,
    impressions BIGINT UNSIGNED NULL,
    reach BIGINT UNSIGNED NULL,
    reactions BIGINT UNSIGNED NULL,
    comments BIGINT UNSIGNED NULL,
    shares BIGINT UNSIGNED NULL,
    saves BIGINT UNSIGNED NULL,
    video_views BIGINT UNSIGNED NULL,
    unique_viewers BIGINT UNSIGNED NULL,
    watch_seconds BIGINT UNSIGNED NULL,
    fetched_at DATETIME NOT NULL,
    error VARCHAR(300) NULL,
    KEY idx_post_metrics_user (user_id, channel_id),
    CONSTRAINT fk_post_metrics_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS channel_followers (
    channel_id BIGINT UNSIGNED NOT NULL,
    day DATE NOT NULL,
    followers BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (channel_id, day),
    CONSTRAINT fk_channel_followers_channel FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE channels
    ADD COLUMN stats_checked_at DATETIME NULL,
    ADD COLUMN stats_error VARCHAR(300) NULL;
