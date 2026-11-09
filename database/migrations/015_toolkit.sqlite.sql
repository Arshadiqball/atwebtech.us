CREATE TABLE IF NOT EXISTS hashtag_groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    tags TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_hashtag_groups_user ON hashtag_groups (user_id);

CREATE TABLE IF NOT EXISTS feeds (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    url TEXT NOT NULL,
    title TEXT NOT NULL,
    site_url TEXT NULL,
    last_checked_at TEXT NULL,
    last_error TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_feeds_user ON feeds (user_id);

CREATE TABLE IF NOT EXISTS feed_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    feed_id INTEGER NOT NULL REFERENCES feeds (id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL,
    guid_hash TEXT NOT NULL,
    title TEXT NOT NULL,
    link TEXT NOT NULL,
    summary TEXT NULL,
    published_at TEXT NULL,
    status TEXT NOT NULL DEFAULT 'new',
    created_at TEXT NOT NULL,
    UNIQUE (feed_id, guid_hash)
);
CREATE INDEX IF NOT EXISTS idx_feed_items_user ON feed_items (user_id, status, published_at);

ALTER TABLE users ADD COLUMN utm_tracking_enabled INTEGER NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN utm_tracking_campaign TEXT NULL;

ALTER TABLE channels ADD COLUMN paused_at TEXT NULL;
