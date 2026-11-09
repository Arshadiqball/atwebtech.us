ALTER TABLE posts
    ADD COLUMN review_note VARCHAR(1000) NULL,
    ADD COLUMN reviewed_at DATETIME NULL,
    ADD COLUMN reviewed_by VARCHAR(120) NULL;

CREATE TABLE IF NOT EXISTS approval_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    label VARCHAR(80) NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    UNIQUE KEY uq_approval_token (token_hash),
    KEY idx_approval_user (user_id),
    CONSTRAINT fk_approval_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
