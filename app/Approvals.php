<?php

declare(strict_types=1);

/**
 * Post approvals. A post sent for approval has status "review" and is never published until approved.
 * The account owner approves in the workspace, or shares a review link that a client or manager
 * can open without an account. Approving schedules the post; requesting changes moves it to Drafts.
 */
final class Approvals
{
    public const LINK_DAYS = 30;

    /** Posts waiting for approval, oldest time first. */
    public static function pending(int $uid): array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM posts WHERE user_id = :uid AND status = 'review' ORDER BY planned_at, id");
        $stmt->execute(['uid' => $uid]);
        return $stmt->fetchAll();
    }

    /** Approve: schedule the post. A time already in the past becomes "in two minutes". */
    public static function approve(int $uid, int $postId, string $by): bool
    {
        $post = self::reviewPost($uid, $postId);
        if ($post === null) {
            return false;
        }
        $soon = gmdate('Y-m-d H:i:s', time() + 120);
        $at = $post['planned_at'] && $post['planned_at'] > $soon ? $post['planned_at'] : $soon;
        Database::connection()->prepare("UPDATE posts SET status = 'planned', planned_at = :at, reviewed_at = :now, reviewed_by = :by, review_note = NULL, updated_at = :now2 WHERE id = :id AND user_id = :uid")
            ->execute(['at' => $at, 'now' => Database::now(), 'by' => mb_substr($by, 0, 120), 'now2' => Database::now(), 'id' => $postId, 'uid' => $uid]);
        return true;
    }

    /** Request changes: back to Drafts with the reviewer's note. */
    public static function requestChanges(int $uid, int $postId, string $note, string $by): bool
    {
        if (self::reviewPost($uid, $postId) === null) {
            return false;
        }
        Database::connection()->prepare("UPDATE posts SET status = 'draft', review_note = :note, reviewed_at = :now, reviewed_by = :by, updated_at = :now2 WHERE id = :id AND user_id = :uid")
            ->execute(['note' => mb_substr(trim($note) !== '' ? trim($note) : 'Changes requested.', 0, 1000), 'now' => Database::now(), 'by' => mb_substr($by, 0, 120), 'now2' => Database::now(), 'id' => $postId, 'uid' => $uid]);
        return true;
    }

    /** POST /app/approvals: approve, request changes, or manage the review link. */
    public static function handle(): never
    {
        $user = Auth::member();
        $uid = (int) $user['id'];
        $action = (string) ($_POST['action'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        $back = '/app/publish?tab=approvals';
        $by = 'You';
        if ($action === 'approve') {
            self::done($back, self::approve($uid, $id, $by) ? 'Approved and scheduled.' : 'That post is no longer waiting for approval.');
        }
        if ($action === 'changes') {
            self::done($back, self::requestChanges($uid, $id, (string) ($_POST['note'] ?? ''), $by) ? 'Moved to Drafts with your note.' : 'That post is no longer waiting for approval.');
        }
        if ($action === 'approve_all') {
            $n = 0;
            foreach (self::pending($uid) as $post) {
                $n += self::approve($uid, (int) $post['id'], $by) ? 1 : 0;
            }
            self::done($back, $n === 1 ? '1 post approved and scheduled.' : $n . ' posts approved and scheduled.');
        }
        if ($action === 'link') {
            $token = bin2hex(random_bytes(24));
            $pdo = Database::connection();
            $pdo->prepare('UPDATE approval_links SET revoked_at = :now WHERE user_id = :uid AND revoked_at IS NULL')->execute(['now' => Database::now(), 'uid' => $uid]);
            $pdo->prepare('INSERT INTO approval_links (user_id, token_hash, created_at, expires_at) VALUES (:uid, :hash, :now, :exp)')
                ->execute(['uid' => $uid, 'hash' => hash('sha256', $token), 'now' => Database::now(), 'exp' => gmdate('Y-m-d H:i:s', time() + 86400 * self::LINK_DAYS)]);
            $_SESSION['_review_link'] = $token;
            self::done($back, 'New review link created. Copy it now; for your security it is shown only once.');
        }
        if ($action === 'revoke') {
            Database::connection()->prepare('UPDATE approval_links SET revoked_at = :now WHERE user_id = :uid AND revoked_at IS NULL')->execute(['now' => Database::now(), 'uid' => $uid]);
            self::done($back, 'Review link turned off. Anyone who has it can no longer open it.');
        }
        self::done($back, 'Nothing changed.');
    }

    /** The active review link for an account, if any. */
    public static function activeLink(int $uid): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM approval_links WHERE user_id = :uid AND revoked_at IS NULL AND expires_at > :now ORDER BY id DESC LIMIT 1');
        $stmt->execute(['uid' => $uid, 'now' => Database::now()]);
        return $stmt->fetch() ?: null;
    }

    /** GET /review/{token}: the public review page. */
    public static function show(string $token): never
    {
        $link = self::link($token);
        if ($link === null) {
            not_found();
        }
        $owner = Database::connection()->prepare('SELECT id, display_name, timezone FROM users WHERE id = :id');
        $owner->execute(['id' => $link['user_id']]);
        $user = $owner->fetch();
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, private');
        header('X-Robots-Tag: noindex, nofollow');
        View::partial('review/page', ['token' => $token, 'user' => $user, 'posts' => self::pending((int) $link['user_id']), 'notice' => flash('review_notice')]);
        exit;
    }

    /** POST /review/{token}: a reviewer approves or requests changes. */
    public static function decide(string $token): never
    {
        $link = self::link($token);
        if ($link === null) {
            not_found();
        }
        $back = '/review/' . $token;
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            flash('review_notice', 'Your session expired. Please try again.');
            redirect($back);
        }
        $uid = (int) $link['user_id'];
        $name = trim((string) ($_POST['reviewer'] ?? ''));
        $by = 'Reviewer' . ($name !== '' ? ': ' . mb_substr($name, 0, 60) : '') . ' (review link)';
        $id = (int) ($_POST['id'] ?? 0);
        $ok = ($_POST['decision'] ?? '') === 'approve'
            ? self::approve($uid, $id, $by)
            : self::requestChanges($uid, $id, (string) ($_POST['note'] ?? ''), $by);
        flash('review_notice', $ok ? (($_POST['decision'] ?? '') === 'approve' ? 'Approved. Thank you!' : 'Thanks, your feedback was sent.') : 'That post was already reviewed.');
        redirect($back);
    }

    private static function link(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }
        $stmt = Database::connection()->prepare('SELECT * FROM approval_links WHERE token_hash = :h AND revoked_at IS NULL AND expires_at > :now');
        $stmt->execute(['h' => hash('sha256', $token), 'now' => Database::now()]);
        return $stmt->fetch() ?: null;
    }

    private static function reviewPost(int $uid, int $postId): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM posts WHERE id = :id AND user_id = :uid AND status = 'review'");
        $stmt->execute(['id' => $postId, 'uid' => $uid]);
        return $stmt->fetch() ?: null;
    }

    private static function done(string $location, string $message): never
    {
        flash('notice', $message);
        redirect($location);
    }
}
