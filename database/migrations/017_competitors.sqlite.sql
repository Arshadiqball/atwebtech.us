CREATE TABLE IF NOT EXISTS competitors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    network TEXT NOT NULL,
    handle TEXT NOT NULL,
    external_id TEXT NOT NULL,
    name TEXT NOT NULL,
    avatar_url TEXT NULL,
    url TEXT NOT NULL,
    followers INTEGER NULL,
    posts_total INTEGER NULL,
    last_checked_at TEXT NULL,
    error TEXT NULL,
    created_at TEXT NOT NULL,
    UNIQUE (user_id, network, external_id)
);
CREATE INDEX IF NOT EXISTS idx_competitors_check ON competitors (last_checked_at);

CREATE TABLE IF NOT EXISTS competitor_stats (
    competitor_id INTEGER NOT NULL REFERENCES competitors (id) ON DELETE CASCADE,
    day TEXT NOT NULL,
    followers INTEGER NOT NULL,
    posts_total INTEGER NULL,
    PRIMARY KEY (competitor_id, day)
);

CREATE TABLE IF NOT EXISTS competitor_posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    competitor_id INTEGER NOT NULL REFERENCES competitors (id) ON DELETE CASCADE,
    external_id TEXT NOT NULL,
    published_at TEXT NOT NULL,
    text TEXT NULL,
    url TEXT NOT NULL,
    kind TEXT NULL,
    likes INTEGER NULL,
    comments INTEGER NULL,
    shares INTEGER NULL,
    views INTEGER NULL,
    UNIQUE (competitor_id, external_id)
);
CREATE INDEX IF NOT EXISTS idx_competitor_posts_time ON competitor_posts (competitor_id, published_at);
