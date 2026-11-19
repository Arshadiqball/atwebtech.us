-- Mobile app: one sign-in token per device (only a hash is stored) with its push address,
-- and short-lived single-use codes (two-factor step, opening a web page already signed in).
CREATE TABLE IF NOT EXISTS mobile_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    device VARCHAR(120) NULL,
    platform VARCHAR(10) NULL,
    push_token VARCHAR(400) NULL,
    created_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    UNIQUE KEY uq_mobile_token (token_hash),
    KEY idx_mobile_tokens_user (user_id),
    CONSTRAINT fk_mobile_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mobile_codes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    kind VARCHAR(10) NOT NULL,
    code_hash CHAR(64) NOT NULL,
    data VARCHAR(600) NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_mobile_code (code_hash),
    CONSTRAINT fk_mobile_codes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
