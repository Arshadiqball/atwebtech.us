CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    theme VARCHAR(10) NOT NULL DEFAULT 'light',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    last_login_at DATETIME NULL,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_onboarding (
    user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    step TINYINT UNSIGNED NOT NULL DEFAULT 0,
    describe_as VARCHAR(40) NULL,
    tools VARCHAR(255) NULL,
    accounts_range VARCHAR(10) NULL,
    focus_channels VARCHAR(500) NULL,
    completed_at DATETIME NULL,
    referral VARCHAR(40) NULL,
    referral_other VARCHAR(200) NULL,
    survey_closed_at DATETIME NULL,
    checklist_closed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_onboarding_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
