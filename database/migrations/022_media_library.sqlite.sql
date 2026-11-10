-- Media library: uploads kept for reuse even when no post uses them.
ALTER TABLE media ADD COLUMN in_library INTEGER NOT NULL DEFAULT 0;
CREATE INDEX IF NOT EXISTS idx_media_library ON media (user_id, status, in_library);
