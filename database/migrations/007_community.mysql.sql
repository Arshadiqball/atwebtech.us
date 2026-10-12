ALTER TABLE posts
    ADD COLUMN first_comment VARCHAR(1250) NULL AFTER tags,
    ADD COLUMN first_comment_error VARCHAR(400) NULL AFTER first_comment;
