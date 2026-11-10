-- Media library: uploads kept for reuse even when no post uses them.
ALTER TABLE media
    ADD COLUMN in_library TINYINT(1) NOT NULL DEFAULT 0,
    ADD KEY idx_media_library (user_id, status, in_library);
