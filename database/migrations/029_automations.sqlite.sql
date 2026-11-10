-- Automations: blog-to-social, weekly AI plans and auto-replies. Recurring posts and the weekly summary email.
CREATE TABLE IF NOT EXISTS automations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    kind TEXT NOT NULL,
    name TEXT NOT NULL,
    config TEXT NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 1,
    last_item_id INTEGER NULL,
    last_run_at TEXT NULL,
    last_result TEXT NULL,
    made INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_automations_user ON automations (user_id);
ALTER TABLE posts ADD COLUMN repeat_rule TEXT NULL;
ALTER TABLE posts ADD COLUMN automation_id INTEGER NULL;
ALTER TABLE comments ADD COLUMN auto_reply INTEGER NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN digest_sent_at TEXT NULL;
