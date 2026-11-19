-- Mobile app: one sign-in token per device (only a hash is stored) with its push address,
-- and short-lived single-use codes (two-factor step, opening a web page already signed in).
CREATE TABLE IF NOT EXISTS mobile_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL UNIQUE,
    device TEXT NULL,
    platform TEXT NULL,
    push_token TEXT NULL,
    created_at TEXT NOT NULL,
    last_used_at TEXT NULL
);
CREATE INDEX IF NOT EXISTS idx_mobile_tokens_user ON mobile_tokens (user_id);
CREATE TABLE IF NOT EXISTS mobile_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    kind TEXT NOT NULL,
    code_hash TEXT NOT NULL UNIQUE,
    data TEXT NULL,
    expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL
);
