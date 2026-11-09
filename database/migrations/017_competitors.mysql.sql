CREATE TABLE IF NOT EXISTS competitors (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    network VARCHAR(20) NOT NULL,
    handle VARCHAR(120) NOT NULL,
    external_id VARCHAR(120) NOT NULL,
    name VARCHAR(190) NOT NULL,
    avatar_url VARCHAR(500) NULL,
    url VARCHAR(255) NOT NULL,
    followers BIGINT UNSIGNED NULL,
    posts_total BIGINT UNSIGNED NULL,
    last_checked_at DATETIME NULL,
    error VARCHAR(300) NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_competitor (user_id, network, external_id),
    KEY idx_competitors_check (last_checked_at),
    CONSTRAINT fk_competitors_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS competitor_stats (
    competitor_id BIGINT UNSIGNED NOT NULL,
    day DATE NOT NULL,
    followers BIGINT UNSIGNED NOT NULL,
    posts_total BIGINT UNSIGNED NULL,
    PRIMARY KEY (competitor_id, day),
    CONSTRAINT fk_competitor_stats FOREIGN KEY (competitor_id) REFERENCES competitors (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS competitor_posts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    competitor_id BIGINT UNSIGNED NOT NULL,
    external_id VARCHAR(191) NOT NULL,
    published_at DATETIME NOT NULL,
    text VARCHAR(500) NULL,
    url VARCHAR(500) NOT NULL,
    kind VARCHAR(20) NULL,
    likes BIGINT UNSIGNED NULL,
    comments BIGINT UNSIGNED NULL,
    shares BIGINT UNSIGNED NULL,
    views BIGINT UNSIGNED NULL,
    UNIQUE KEY uq_competitor_post (competitor_id, external_id),
    KEY idx_competitor_posts_time (competitor_id, published_at),
    CONSTRAINT fk_competitor_posts FOREIGN KEY (competitor_id) REFERENCES competitors (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
