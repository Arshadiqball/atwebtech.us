ALTER TABLE users ADD COLUMN stripe_customer_id TEXT NULL;

CREATE TABLE IF NOT EXISTS subscriptions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    stripe_subscription_id TEXT NOT NULL UNIQUE,
    stripe_customer_id TEXT NOT NULL,
    plan TEXT NOT NULL,
    billing TEXT NOT NULL,
    channels INTEGER NOT NULL DEFAULT 1,
    status TEXT NOT NULL,
    cancel_at_period_end INTEGER NOT NULL DEFAULT 0,
    current_period_end TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_subscriptions_user ON subscriptions (user_id, status);

CREATE TABLE IF NOT EXISTS billing_products (
    plan TEXT NOT NULL PRIMARY KEY,
    stripe_product_id TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS stripe_events (
    id TEXT NOT NULL PRIMARY KEY,
    type TEXT NOT NULL,
    received_at TEXT NOT NULL
);
