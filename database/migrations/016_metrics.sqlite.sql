CREATE TABLE IF NOT EXISTS post_metrics (
    post_id INTEGER PRIMARY KEY REFERENCES posts (id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL,
    channel_id INTEGER NOT NULL,
    impressions INTEGER NULL,
    reach INTEGER NULL,
    reactions INTEGER NULL,
    comments INTEGER NULL,
    shares INTEGER NULL,
    saves INTEGER NULL,
    video_views INTEGER NULL,
    unique_viewers INTEGER NULL,
    watch_seconds INTEGER NULL,
    fetched_at TEXT NOT NULL,
    error TEXT NULL
);
CREATE INDEX IF NOT EXISTS idx_post_metrics_user ON post_metrics (user_id, channel_id);

CREATE TABLE IF NOT EXISTS channel_followers (
    channel_id INTEGER NOT NULL REFERENCES channels (id) ON DELETE CASCADE,
    day TEXT NOT NULL,
    followers INTEGER NOT NULL,
    PRIMARY KEY (channel_id, day)
);

ALTER TABLE channels ADD COLUMN stats_checked_at TEXT NULL;
ALTER TABLE channels ADD COLUMN stats_error TEXT NULL;
