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
    public const MAX_INVITES = 20;

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
            $pdo->prepare('UPDATE approval_links SET revoked_at = :now WHERE user_id = :uid AND revoked_at IS NULL AND label IS NULL')->execute(['now' => Database::now(), 'uid' => $uid]);
            $pdo->prepare('INSERT INTO approval_links (user_id, token_hash, created_at, expires_at) VALUES (:uid, :hash, :now, :exp)')
                ->execute(['uid' => $uid, 'hash' => hash('sha256', $token), 'now' => Database::now(), 'exp' => gmdate('Y-m-d H:i:s', time() + 86400 * self::LINK_DAYS)]);
            $_SESSION['_review_link'] = $token;
            self::done($back, 'New review link created. Copy it now; for your security it is shown only once.');
        }
        if ($action === 'revoke') {
            Database::connection()->prepare('UPDATE approval_links SET revoked_at = :now WHERE user_id = :uid AND revoked_at IS NULL AND label IS NULL')->execute(['now' => Database::now(), 'uid' => $uid]);
            self::done($back, 'Review link turned off. Anyone who has it can no longer open it.');
        }
        if ($action === 'invite') {
            self::invite($user);
        }
        if ($action === 'uninvite') {
            Database::connection()->prepare('UPDATE approval_links SET revoked_at = :now WHERE id = :id AND user_id = :uid AND label IS NOT NULL AND revoked_at IS NULL')
                ->execute(['now' => Database::now(), 'id' => $id, 'uid' => $uid]);
            self::done($back . '#invite', 'Teammate removed. Their review link no longer works.');
        }
        self::done($back, 'Nothing changed.');
    }

    /** Teammates invited by email, each with their own review link. */
    public static function invites(int $uid): array
    {
        $stmt = Database::connection()->prepare('SELECT id, label, created_at, expires_at FROM approval_links WHERE user_id = :uid AND label IS NOT NULL AND revoked_at IS NULL AND expires_at > :now ORDER BY id DESC');
        $stmt->execute(['uid' => $uid, 'now' => Database::now()]);
        return array_map(static function (array $row): array {
            [$name, $email] = array_pad(explode("\n", (string) $row['label'], 2), 2, '');
            return $row + ['name' => $name, 'email' => $email];
        }, $stmt->fetchAll());
    }

    /** Invite a teammate: a private review link of their own, sent by email and shown once here. */
    private static function invite(array $user): never
    {
        $uid = (int) $user['id'];
        $back = '/app/publish?tab=approvals#invite';
        $name = trim(preg_replace('/\s+/', ' ', (string) ($_POST['name'] ?? '')) ?? '');
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 60) {
            self::done($back, 'Enter your teammate’s email address.');
        }
        if (strcasecmp($email, (string) $user['email']) === 0) {
            self::done($back, 'That’s your own email. Invite a teammate, client or manager instead.');
        }
        $name = mb_substr($name, 0, 18);
        $current = self::invites($uid);
        foreach ($current as $inv) {
            if (strcasecmp($inv['email'], $email) === 0) {
                Database::connection()->prepare('UPDATE approval_links SET revoked_at = :now WHERE id = :id')->execute(['now' => Database::now(), 'id' => (int) $inv['id']]);
            }
        }
        if (count($current) >= self::MAX_INVITES) {
            self::done($back, 'You can have up to ' . self::MAX_INVITES . ' teammates. Remove one to invite someone new.');
        }
        if (RateLimiter::tooManyAttempts('invite', (string) $uid, 15, 60)) {
            self::done($back, 'That’s a lot of invites in a short time. Please try again in an hour.');
        }
        RateLimiter::hit('invite', (string) $uid);
        $token = bin2hex(random_bytes(24));
        Database::connection()->prepare('INSERT INTO approval_links (user_id, token_hash, label, created_at, expires_at) VALUES (:uid, :hash, :label, :now, :exp)')
            ->execute(['uid' => $uid, 'hash' => hash('sha256', $token), 'label' => $name . "\n" . $email, 'now' => Database::now(), 'exp' => gmdate('Y-m-d H:i:s', time() + 86400 * self::LINK_DAYS)]);
        $url = public_origin() . '/review/' . $token;
        $owner = trim((string) ($user['display_name'] ?? '')) !== '' ? (string) $user['display_name'] : (string) $user['email'];
        $mail = EmailTemplate::invite($url, $owner, $name, self::LINK_DAYS);
        $sent = false;
        try {
            if (config('mail.enabled') && (string) config('mail.host') !== '' && (string) config('mail.user') !== '' && (string) config('mail.pass') !== '') {
                Mailer::send($email, $mail['subject'], $mail['html'], $mail['text']);
                $sent = true;
            }
        } catch (Throwable $e) {
            error_log(sprintf('[%s] invite email failed: %s', gmdate('c'), $e->getMessage()));
        }
        $_SESSION['_invite_link'] = ['url' => $url, 'email' => $email];
        self::done($back, $sent ? 'Invite sent to ' . $email . '. You can also copy their link below.' : 'Invite created. Email isn’t set up, so copy the link below and send it to ' . $email . '.');
    }

    /** The shared review link for an account, if any (teammate invites have their own links). */
    public static function activeLink(int $uid): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM approval_links WHERE user_id = :uid AND label IS NULL AND revoked_at IS NULL AND expires_at > :now ORDER BY id DESC LIMIT 1');
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
