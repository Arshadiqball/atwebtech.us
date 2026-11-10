-- Evergreen recycling: sent posts marked evergreen re-fill empty posting slots after a gap.
ALTER TABLE posts ADD COLUMN evergreen INTEGER NOT NULL DEFAULT 0;
ALTER TABLE posts ADD COLUMN recycled_at TEXT NULL;
ALTER TABLE posts ADD COLUMN recycled_from INTEGER NULL;
CREATE INDEX IF NOT EXISTS idx_posts_evergreen ON posts (channel_id, evergreen, status);
ALTER TABLE channels ADD COLUMN recycle_days INTEGER NOT NULL DEFAULT 30;
ALTER TABLE channels ADD COLUMN recycle_checked_at TEXT NULL;
