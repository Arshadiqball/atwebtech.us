ALTER TABLE posts ADD COLUMN review_note TEXT NULL;
ALTER TABLE posts ADD COLUMN reviewed_at TEXT NULL;
ALTER TABLE posts ADD COLUMN reviewed_by TEXT NULL;

CREATE TABLE IF NOT EXISTS approval_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL UNIQUE,
    label TEXT NULL,
    created_at TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    revoked_at TEXT NULL
);
CREATE INDEX IF NOT EXISTS idx_approval_user ON approval_links (user_id);
