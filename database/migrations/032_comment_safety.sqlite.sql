-- Reporting and blocking in the comment inbox. A reported comment is taken out of the inbox and kept here
-- for the atwebtech team to look at. A blocked person's comments no longer come into that workspace.
CREATE TABLE IF NOT EXISTS comment_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    channel_id INTEGER NOT NULL,
    external_id TEXT NOT NULL,
    network TEXT NOT NULL,
    author_name TEXT NOT NULL,
    body TEXT NOT NULL,
    item_url TEXT NULL,
    reason TEXT NOT NULL,
    reported_by INTEGER NULL,
    handled_at TEXT NULL,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_comment_reports_channel ON comment_reports (channel_id, external_id);
CREATE INDEX IF NOT EXISTS idx_comment_reports_created ON comment_reports (created_at);

CREATE TABLE IF NOT EXISTS blocked_authors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    provider TEXT NOT NULL,
    author_name TEXT NOT NULL,
    created_at TEXT NOT NULL,
    UNIQUE (user_id, provider, author_name)
);
