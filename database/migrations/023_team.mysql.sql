-- Team accounts: people with their own login who work inside another account's workspace.
CREATE TABLE IF NOT EXISTS team_members (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role VARCHAR(12) NOT NULL,
    channels TEXT NULL,
    invited_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    last_seen_at DATETIME NULL,
    UNIQUE KEY uq_team_member (workspace_id, user_id),
    KEY idx_team_user (user_id),
    CONSTRAINT fk_team_ws FOREIGN KEY (workspace_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_team_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_invites (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(254) NOT NULL,
    name VARCHAR(80) NULL,
    role VARCHAR(12) NOT NULL,
    channels TEXT NULL,
    token_hash CHAR(64) NOT NULL,
    invited_by BIGINT UNSIGNED NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_team_invite_token (token_hash),
    KEY idx_team_invite_ws (workspace_id),
    CONSTRAINT fk_team_invite_ws FOREIGN KEY (workspace_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE posts ADD COLUMN created_by BIGINT UNSIGNED NULL;
