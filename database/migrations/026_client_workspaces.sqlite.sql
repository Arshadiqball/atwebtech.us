-- Client workspaces: extra workspaces owned by an account (parent_id). They have no login of their own;
-- the owner reaches them through team_members with the role owner, and billing comes from the parent.
ALTER TABLE users ADD COLUMN parent_id INTEGER NULL;
CREATE INDEX IF NOT EXISTS idx_users_parent ON users (parent_id);
