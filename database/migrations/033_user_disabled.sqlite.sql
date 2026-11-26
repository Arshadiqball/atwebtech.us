-- An admin can disable an account from /admin/users: it can't sign in or use the app, and its channels are paused.
ALTER TABLE users ADD COLUMN disabled_at TEXT NULL;
