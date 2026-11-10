-- Community inbox: comments on connected channels, with replies sent from atwebtech.
CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    channel_id INTEGER NOT NULL REFERENCES channels (id) ON DELETE CASCADE,
    post_id INTEGER NULL REFERENCES posts (id) ON DELETE SET NULL,
    external_id TEXT NOT NULL,
    item_id TEXT NOT NULL,
    item_title TEXT NULL,
    item_url TEXT NULL,
    author_name TEXT NOT NULL,
    author_avatar TEXT NULL,
    author_url TEXT NULL,
    is_own INTEGER NOT NULL DEFAULT 0,
    body TEXT NOT NULL,
    likes INTEGER NULL,
    replies INTEGER NULL,
    published_at TEXT NOT NULL,
    state TEXT NOT NULL DEFAULT 'open',
    reply_body TEXT NULL,
    replied_at TEXT NULL,
    fetched_at TEXT NOT NULL,
    UNIQUE (channel_id, external_id)
);
CREATE INDEX IF NOT EXISTS idx_comments_inbox ON comments (user_id, state, published_at);
ALTER TABLE channels ADD COLUMN inbox_checked_at TEXT NULL;
ALTER TABLE channels ADD COLUMN inbox_error TEXT NULL;
