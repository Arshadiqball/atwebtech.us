ALTER TABLE users
    ADD COLUMN stripe_customer_id VARCHAR(64) NULL AFTER click_id;

CREATE TABLE IF NOT EXISTS subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    stripe_subscription_id VARCHAR(64) NOT NULL,
    stripe_customer_id VARCHAR(64) NOT NULL,
    plan VARCHAR(20) NOT NULL,
    billing VARCHAR(10) NOT NULL,
    channels INT NOT NULL DEFAULT 1,
    status VARCHAR(24) NOT NULL,
    cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,
    current_period_end DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_subscriptions_stripe (stripe_subscription_id),
    KEY idx_subscriptions_user (user_id, status),
    CONSTRAINT fk_subscriptions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_products (
    plan VARCHAR(20) NOT NULL PRIMARY KEY,
    stripe_product_id VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stripe_events (
    id VARCHAR(80) NOT NULL PRIMARY KEY,
    type VARCHAR(80) NOT NULL,
    received_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
