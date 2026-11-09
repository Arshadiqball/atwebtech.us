CREATE TABLE IF NOT EXISTS trending_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source TEXT NOT NULL,
    interest TEXT NOT NULL DEFAULT '',
    external_id TEXT NOT NULL,
    title TEXT NOT NULL,
    summary TEXT NULL,
    url TEXT NOT NULL,
    image_url TEXT NULL,
    metric INTEGER NOT NULL DEFAULT 0,
    metric_label TEXT NULL,
    rank_order INTEGER NOT NULL DEFAULT 0,
    published_at TEXT NULL,
    fetched_at TEXT NOT NULL,
    UNIQUE (source, interest, external_id)
);
CREATE INDEX IF NOT EXISTS idx_trending_pick ON trending_items (interest, source, rank_order);
