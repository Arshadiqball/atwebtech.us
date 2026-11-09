-- X was removed from atwebtech (its API charges for every post and read).
-- Posts that were still waiting go to Drafts with a note. Sent posts stay as history.
UPDATE posts
SET status = 'draft',
    review_note = 'X was removed from atwebtech. Copy this post to another network, or delete it.',
    error = NULL,
    updated_at = UTC_TIMESTAMP()
WHERE channel = 'x' AND status IN ('planned', 'review', 'failed', 'publishing');

DELETE FROM channels WHERE provider = 'x';
