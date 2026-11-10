-- Carousels: extra photos of a post, in order, stored as ,12,34, (the first photo stays in media_id).
ALTER TABLE posts ADD COLUMN extra_media VARCHAR(400) NULL;
