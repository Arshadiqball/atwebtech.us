CREATE TABLE IF NOT EXISTS trending_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(20) NOT NULL,
    interest VARCHAR(30) NOT NULL DEFAULT '',
    external_id VARCHAR(120) NOT NULL,
    title VARCHAR(300) NOT NULL,
    summary VARCHAR(500) NULL,
    url VARCHAR(500) NOT NULL,
    image_url VARCHAR(500) NULL,
    metric BIGINT NOT NULL DEFAULT 0,
    metric_label VARCHAR(60) NULL,
    rank_order INT NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    fetched_at DATETIME NOT NULL,
    UNIQUE KEY uq_trending (source, interest, external_id),
    KEY idx_trending_pick (interest, source, rank_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
