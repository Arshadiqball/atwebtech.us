-- Evergreen recycling: sent posts marked evergreen re-fill empty posting slots after a gap.
ALTER TABLE posts
    ADD COLUMN evergreen TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN recycled_at DATETIME NULL,
    ADD COLUMN recycled_from BIGINT UNSIGNED NULL,
    ADD KEY idx_posts_evergreen (channel_id, evergreen, status);

ALTER TABLE channels
    ADD COLUMN recycle_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    ADD COLUMN recycle_checked_at DATETIME NULL;
