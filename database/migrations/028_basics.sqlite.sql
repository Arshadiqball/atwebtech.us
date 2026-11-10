-- Everyday basics: password reset links, in-app notifications with email preferences, saved replies.
CREATE TABLE IF NOT EXISTS password_resets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL UNIQUE,
    expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL
);
ALTER TABLE users ADD COLUMN password_changed_at TEXT NULL;
ALTER TABLE users ADD COLUMN notify_off TEXT NULL;
CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    workspace_id INTEGER NULL,
    kind TEXT NOT NULL,
    title TEXT NOT NULL,
    body TEXT NULL,
    url TEXT NULL,
    read_at TEXT NULL,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications (user_id, read_at, id);
CREATE TABLE IF NOT EXISTS saved_replies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_saved_replies_user ON saved_replies (user_id);
