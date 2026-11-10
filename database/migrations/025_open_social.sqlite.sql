-- Mastodon: atwebtech's app registration on each server people connect from.
CREATE TABLE IF NOT EXISTS mastodon_apps (
    instance TEXT NOT NULL PRIMARY KEY,
    client_id TEXT NOT NULL,
    client_secret TEXT NOT NULL,
    created_at TEXT NOT NULL
);
