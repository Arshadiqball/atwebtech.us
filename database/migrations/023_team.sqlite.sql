-- Team accounts: people with their own login who work inside another account's workspace.
CREATE TABLE IF NOT EXISTS team_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    workspace_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    role TEXT NOT NULL,
    channels TEXT NULL,
    invited_by INTEGER NULL,
    created_at TEXT NOT NULL,
    last_seen_at TEXT NULL,
    UNIQUE (workspace_id, user_id)
);
CREATE INDEX IF NOT EXISTS idx_team_user ON team_members (user_id);
CREATE TABLE IF NOT EXISTS team_invites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    workspace_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    email TEXT NOT NULL,
    name TEXT NULL,
    role TEXT NOT NULL,
    channels TEXT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    invited_by INTEGER NULL,
    expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_team_invite_ws ON team_invites (workspace_id);
ALTER TABLE posts ADD COLUMN created_by INTEGER NULL;
