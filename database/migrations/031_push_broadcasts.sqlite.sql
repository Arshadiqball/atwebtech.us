-- Push updates an admin sends to app users from /admin/push: what was sent, to whom, and how it went.
CREATE TABLE IF NOT EXISTS push_broadcasts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    url TEXT NULL,
    audience TEXT NOT NULL,
    in_inbox INTEGER NOT NULL DEFAULT 1,
    people INTEGER NOT NULL DEFAULT 0,
    devices INTEGER NOT NULL DEFAULT 0,
    delivered INTEGER NOT NULL DEFAULT 0,
    failed INTEGER NOT NULL DEFAULT 0,
    sent_by TEXT NULL,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_push_broadcasts_created ON push_broadcasts (created_at);
