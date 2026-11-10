<?php

declare(strict_types=1);

/**
 * Notifications for people: an in-app list (/app/notifications) plus an email, unless they turned that
 * kind off in Account. A notification belongs to a person and names the workspace it happened in, so
 * opening it switches to that workspace first. Sending never throws: publishing must not fail because of it.
 */
final class Notify
{
    public const KINDS = [
        'post_failed' => 'A post fails to publish',
        'channel_reconnect' => 'A channel needs to be reconnected',
        'approval_needed' => 'A teammate sends a post for approval',
        'approval_decided' => 'My post is approved or sent back with changes',
        'queue_low' => 'A channel’s queue runs empty',
        'weekly_digest' => 'A summary of my week, every Monday',
    ];

    /** A post failed for good (called from the publisher). */
    public static function postFailed(array $post): void
    {
        $ws = (int) $post['user_id'];
        $channel = self::channelName((int) $post['channel_id']);
        $error = (string) ($post['error'] ?? '');
        $people = self::team($ws, 'editor', (int) $post['channel_id']);
        if (!empty($post['created_by'])) {
            $people[] = (int) $post['created_by'];
        }
        if (stripos($error, 'reconnect') !== false) {
            self::send($ws, 'channel_reconnect', $channel . ' needs to be reconnected', $error . ' Posts for this channel wait until then.', '/app/channels', $people, 24);
            return;
        }
        $text = trim((string) $post['body']);
        self::send($ws, 'post_failed', 'A post to ' . $channel . ' didn’t publish', ($error !== '' ? $error : 'The network didn’t accept it.') . ($text !== '' ? ' Post: “' . mb_strimwidth($text, 0, 90, '…') . '”' : ''), '/app/publish?tab=failed', $people);
    }

    /** Someone sent posts for approval. */
    public static function approvalNeeded(int $ws, int $count, array $channelIds): void
    {
        $actor = Auth::person();
        $people = [];
        foreach ($channelIds ?: [0] as $cid) {
            $people = array_merge($people, self::team($ws, 'editor', $cid ?: null));
        }
        $people = array_diff($people, [(int) ($actor['id'] ?? 0)]);
        $who = (string) ($actor['display_name'] ?? 'A teammate');
        self::send($ws, 'approval_needed', $who . ' sent ' . ($count === 1 ? 'a post' : $count . ' posts') . ' for approval', 'Nothing is published until someone approves it.', '/app/publish?tab=approvals', $people);
    }

    /** A post was approved or sent back. Tells the person who wrote it. */
    public static function approvalDecided(int $ws, array $post, bool $approved, string $by, string $note = ''): void
    {
        $actor = Auth::person();
        $author = (int) ($post['created_by'] ?? 0) ?: (Channels::parentOf($ws) ?? $ws);
        if ($actor !== null && (int) $actor['id'] === $author) {
            return;
        }
        $by = $by === 'You' && $actor !== null ? (string) $actor['display_name'] : $by;
        $text = mb_strimwidth(trim((string) $post['body']), 0, 90, '…');
        self::send(
            $ws,
            'approval_decided',
            $approved ? $by . ' approved your post' : $by . ' asked for changes to your post',
            ($approved ? 'It’s scheduled now.' : 'It’s back in Drafts' . ($note !== '' ? ' with a note: “' . mb_strimwidth($note, 0, 200, '…') . '”' : '.')) . ($text !== '' ? ' Post: “' . $text . '”' : ''),
            $approved ? '/app/publish' : '/app/publish?tab=drafts',
            [$author]
        );
    }

    /**
     * Store a notification for each person and email it. $dedupeHours skips people who already got the
     * same kind and link in this workspace within that time.
     */
    public static function send(int $ws, string $kind, string $title, string $body, string $url, array $people, int $dedupeHours = 0): void
    {
        try {
            $people = array_values(array_unique(array_filter(array_map('intval', $people))));
            if (!$people) {
                return;
            }
            $pdo = Database::connection();
            $wsName = $pdo->prepare('SELECT display_name, parent_id FROM users WHERE id = :id');
            $wsName->execute(['id' => $ws]);
            $wsRow = $wsName->fetch() ?: ['display_name' => '', 'parent_id' => null];
            $find = $pdo->prepare('SELECT id, email, parent_id, notify_off FROM users WHERE id = :id');
            $seen = $pdo->prepare('SELECT 1 FROM notifications WHERE user_id = :uid AND workspace_id = :ws AND kind = :k AND url = :url AND created_at > :since LIMIT 1');
            $insert = $pdo->prepare('INSERT INTO notifications (user_id, workspace_id, kind, title, body, url, created_at) VALUES (:uid, :ws, :k, :t, :b, :url, :now)');
            foreach ($people as $pid) {
                $find->execute(['id' => $pid]);
                $person = $find->fetch();
                if (!$person || $person['parent_id'] !== null) {
                    continue;
                }
                if ($dedupeHours > 0) {
                    $seen->execute(['uid' => $pid, 'ws' => $ws, 'k' => $kind, 'url' => $url, 'since' => gmdate('Y-m-d H:i:s', time() - $dedupeHours * 3600)]);
                    if ($seen->fetchColumn()) {
                        continue;
                    }
                }
                $insert->execute(['uid' => $pid, 'ws' => $ws, 'k' => $kind, 't' => mb_substr($title, 0, 200), 'b' => mb_substr($body, 0, 500), 'url' => mb_substr($url, 0, 300), 'now' => Database::now()]);
                $id = (int) $pdo->lastInsertId();
                if (in_array($kind, explode(',', (string) $person['notify_off']), true)) {
                    continue;
                }
                $where = $pid === $ws ? '' : ($wsRow['parent_id'] !== null ? (string) $wsRow['display_name'] : $wsRow['display_name'] . '’s workspace');
                $mail = EmailTemplate::notice($title, $body, absolute_url('/app/notifications/open/' . $id), $where);
                Mailer::send((string) $person['email'], $mail['subject'], $mail['html'], $mail['text']);
            }
        } catch (Throwable $e) {
            error_log(sprintf('[%s] notify %s for workspace %d: %s', gmdate('c'), $kind, $ws, $e->getMessage()));
        }
    }

    /** People to tell in a workspace: its owner, plus teammates at or above $minRole who can use the channel. */
    public static function team(int $ws, string $minRole, ?int $channelId = null): array
    {
        $rank = ['contributor' => 1, 'editor' => 2, 'admin' => 3, 'owner' => 4];
        $people = [Channels::parentOf($ws) ?? $ws];
        $stmt = Database::connection()->prepare('SELECT user_id, role, channels FROM team_members WHERE workspace_id = :ws');
        $stmt->execute(['ws' => $ws]);
        foreach ($stmt->fetchAll() as $m) {
            if (($rank[$m['role']] ?? 0) < $rank[$minRole]) {
                continue;
            }
            if ($channelId !== null && $m['channels'] !== null && !in_array($channelId, array_map('intval', (array) json_decode((string) $m['channels'], true)), true)) {
                continue;
            }
            $people[] = (int) $m['user_id'];
        }
        return array_values(array_unique($people));
    }

    public static function unread(int $personId): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND read_at IS NULL');
        $stmt->execute(['uid' => $personId]);
        return (int) $stmt->fetchColumn();
    }

    /** Data for /app/notifications: the latest 60, with each one's workspace name. */
    public static function page(array $person): array
    {
        $stmt = Database::connection()->prepare('SELECT n.*, w.display_name AS ws_name, w.parent_id AS ws_parent FROM notifications n LEFT JOIN users w ON w.id = n.workspace_id
            WHERE n.user_id = :uid ORDER BY n.id DESC LIMIT 60');
        $stmt->execute(['uid' => (int) $person['id']]);
        return ['items' => $stmt->fetchAll(), 'person' => $person];
    }

    /** POST /app/notifications: mark everything read. */
    public static function handle(): never
    {
        Auth::member();
        $person = Auth::person();
        Database::connection()->prepare('UPDATE notifications SET read_at = :now WHERE user_id = :uid AND read_at IS NULL')->execute(['now' => Database::now(), 'uid' => (int) $person['id']]);
        Database::connection()->prepare('DELETE FROM notifications WHERE user_id = :uid AND created_at < :old')->execute(['uid' => (int) $person['id'], 'old' => gmdate('Y-m-d H:i:s', time() - 90 * 86400)]);
        flash('notice', 'All caught up.');
        redirect('/app/notifications');
    }

    /** GET /app/notifications/open/{id}: mark it read, switch to its workspace, and go to its page. */
    public static function open(string $id): never
    {
        Auth::require();
        $person = Auth::person();
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM notifications WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => (int) $id, 'uid' => (int) $person['id']]);
        $n = $stmt->fetch();
        if (!$n) {
            redirect('/app/notifications');
        }
        $pdo->prepare('UPDATE notifications SET read_at = COALESCE(read_at, :now) WHERE id = :id')->execute(['now' => Database::now(), 'id' => (int) $n['id']]);
        $ws = (int) $n['workspace_id'];
        if ($ws === (int) $person['id'] || $ws === 0) {
            unset($_SESSION['_ws']);
        } elseif (Team::membership($ws, $person) !== null) {
            $_SESSION['_ws'] = $ws;
        } else {
            flash('notice', 'You no longer have access to that workspace.');
            redirect('/app/notifications');
        }
        $url = (string) $n['url'];
        redirect(preg_match('~^/app(/[A-Za-z0-9/_-]*)?(\?[A-Za-z0-9=&_-]*)?$~', $url) ? $url : '/app');
    }

    /** POST /app/account/notifications: which kinds to email. */
    public static function preferences(): never
    {
        $person = Auth::member();
        $on = array_intersect(array_keys(self::KINDS), array_map('strval', (array) ($_POST['email'] ?? [])));
        $off = array_values(array_diff(array_keys(self::KINDS), $on));
        Database::connection()->prepare('UPDATE users SET notify_off = :off WHERE id = :id')->execute(['off' => $off ? implode(',', $off) : null, 'id' => (int) $person['id']]);
        flash('notice', 'Email preferences saved.');
        redirect('/app/account#notifications');
    }

    private static function channelName(int $channelId): string
    {
        $stmt = Database::connection()->prepare('SELECT name, provider FROM channels WHERE id = :id');
        $stmt->execute(['id' => $channelId]);
        $c = $stmt->fetch();
        if (!$c) {
            return 'a channel';
        }
        $network = array_column(data('integrations'), 'name', 'slug')[$c['provider']] ?? ucfirst((string) $c['provider']);
        return $network . ' (' . $c['name'] . ')';
    }
}
