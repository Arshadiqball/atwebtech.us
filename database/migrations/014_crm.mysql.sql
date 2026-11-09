CREATE TABLE IF NOT EXISTS crm_contacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(254) NOT NULL,
    name VARCHAR(120) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    tags VARCHAR(255) NULL,
    first_seen_at DATETIME NOT NULL,
    last_contacted_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_crm_email (email),
    KEY idx_crm_seen (first_seen_at),
    KEY idx_crm_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_notes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    contact_id BIGINT UNSIGNED NOT NULL,
    kind VARCHAR(20) NOT NULL DEFAULT 'note',
    body TEXT NOT NULL,
    author VARCHAR(254) NOT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_crm_notes_contact (contact_id, created_at),
    CONSTRAINT fk_crm_notes_contact FOREIGN KEY (contact_id) REFERENCES crm_contacts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_followups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    contact_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    due_at DATETIME NOT NULL,
    done_at DATETIME NULL,
    author VARCHAR(254) NOT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_crm_followups_due (done_at, due_at),
    KEY idx_crm_followups_contact (contact_id),
    CONSTRAINT fk_crm_followups_contact FOREIGN KEY (contact_id) REFERENCES crm_contacts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
