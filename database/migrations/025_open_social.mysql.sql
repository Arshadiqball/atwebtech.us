-- Mastodon: atwebtech's app registration on each server people connect from.
CREATE TABLE IF NOT EXISTS mastodon_apps (
    instance VARCHAR(191) NOT NULL PRIMARY KEY,
    client_id VARCHAR(191) NOT NULL,
    client_secret TEXT NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
