CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    display_name TEXT NOT NULL,
    theme TEXT NOT NULL DEFAULT 'light',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    last_login_at TEXT NULL
);

CREATE TABLE IF NOT EXISTS user_onboarding (
    user_id INTEGER NOT NULL PRIMARY KEY REFERENCES users (id) ON DELETE CASCADE,
    step INTEGER NOT NULL DEFAULT 0,
    describe_as TEXT NULL,
    tools TEXT NULL,
    accounts_range TEXT NULL,
    focus_channels TEXT NULL,
    completed_at TEXT NULL,
    referral TEXT NULL,
    referral_other TEXT NULL,
    survey_closed_at TEXT NULL,
    checklist_closed_at TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
