CREATE TABLE IF NOT EXISTS crm_contacts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    name TEXT NULL,
    status TEXT NOT NULL DEFAULT 'new',
    tags TEXT NULL,
    first_seen_at TEXT NOT NULL,
    last_contacted_at TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_crm_seen ON crm_contacts (first_seen_at);

CREATE TABLE IF NOT EXISTS crm_notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    contact_id INTEGER NOT NULL REFERENCES crm_contacts (id) ON DELETE CASCADE,
    kind TEXT NOT NULL DEFAULT 'note',
    body TEXT NOT NULL,
    author TEXT NOT NULL,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_crm_notes_contact ON crm_notes (contact_id, created_at);

CREATE TABLE IF NOT EXISTS crm_followups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    contact_id INTEGER NOT NULL REFERENCES crm_contacts (id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    due_at TEXT NOT NULL,
    done_at TEXT NULL,
    author TEXT NOT NULL,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_crm_followups_due ON crm_followups (done_at, due_at);
