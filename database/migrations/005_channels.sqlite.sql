CREATE TABLE IF NOT EXISTS channels (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    provider TEXT NOT NULL,
    external_id TEXT NOT NULL,
    name TEXT NOT NULL,
    kind TEXT NOT NULL DEFAULT 'Channel',
    avatar_url TEXT NULL,
    access_token TEXT NULL,
    refresh_token TEXT NULL,
    token_expires_at TEXT NULL,
    scopes TEXT NULL,
    posting_goal INTEGER NULL,
    schedule TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE (user_id, provider, external_id)
);
