<?php

declare(strict_types=1);

/**
 * Community inbox: reads comments from connected channels, and sends replies from atwebtech.
 * YouTube comments are read with the scope every YouTube channel already has. Facebook, Instagram and
 * Threads need comment permissions from Meta; until a channel has them it shows a reconnect note.
 * Runs from bin/publish.php in small batches.
 */
final class Community
{
    public const NETWORKS = ['youtube' => 'YouTube', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'threads' => 'Threads'];
    public const STATES = ['open' => 'Unanswered', 'replied' => 'Answered', 'done' => 'Done'];
    public const REPLY_MAX = 2000;
    /** How often the cron reads each channel, and the shortest gap for "Refresh now". */
    private const EVERY_MINUTES = 30;
    private const MANUAL_MINUTES = 3;
    private const ITEMS = 15;
    private const PAGE = 40;

    private const GRAPH = 'https://graph.facebook.com/v23.0';
    private const IG = 'https://graph.instagram.com/v23.0';
    private const THREADS = 'https://graph.threads.net/v1.0';
    private const YT = 'https://www.googleapis.com/youtube/v3';

    /** Read the channels that are due. Stops after $seconds so the minute cron stays short. */
    public static function run(int $seconds = 15): int
    {
        $started = time();
        $stmt = Database::connection()->prepare('SELECT id, user_id FROM channels WHERE provider IN (' . self::inList() . ') AND paused_at IS NULL
            AND (inbox_checked_at IS NULL OR inbox_checked_at < :due) ORDER BY inbox_checked_at IS NOT NULL, inbox_checked_at LIMIT 8');
        $stmt->execute(['due' => gmdate('Y-m-d H:i:s', time() - self::EVERY_MINUTES * 60)]);
        $done = 0;
        foreach ($stmt->fetchAll() as $row) {
            if (time() - $started > $seconds) {
                break;
            }
            $channel = Channels::find((int) $row['user_id'], (int) $row['id']);
            if ($channel !== null) {
                self::refreshChannel($channel);
                $done++;
            }
        }
        return $done;
    }

    /** Read one channel's recent comments now. Returns a user-facing problem, or null. */
    public static function refreshChannel(array $channel): ?string
    {
        $error = null;
        try {
            $token = Channels::accessToken($channel);
            $items = match ($channel['provider']) {
                'youtube' => self::youtube($channel, $token),
                'facebook' => self::facebook($channel, $token),
                'instagram' => self::instagram($channel, $token),
                'threads' => self::threads($channel, $token),
                default => [],
            };
            self::save($channel, $items);
        } catch (Throwable $e) {
            $error = self::explain((string) $channel['provider'], $e);
            error_log(sprintf('[%s] inbox channel %d: %s', gmdate('c'), (int) $channel['id'], $e->getMessage()));
        }
        Database::connection()->prepare('UPDATE channels SET inbox_checked_at = :now, inbox_error = :err WHERE id = :id')
            ->execute(['now' => Database::now(), 'err' => $error, 'id' => (int) $channel['id']]);
        return $error;
    }

    /** True when atwebtech can send replies for this channel. */
    public static function canReply(array $channel): bool
    {
        $scopes = (string) ($channel['scopes'] ?? '');
        return match ($channel['provider']) {
            'youtube' => str_contains($scopes, 'auth/youtube.force-ssl') || preg_match('~auth/youtube(\s|$)~', $scopes) === 1,
            'instagram' => str_contains($scopes, 'instagram_business_manage_comments'),
            'threads' => str_contains($scopes, 'threads_manage_replies'),
            'facebook' => ($channel['kind'] ?? '') === 'Page', // Set by the Facebook Login configuration; a missing permission shows on send.
            default => false,
        };
    }

    /**
     * The inbox for one user.
     * @return array{comments: array, items: array, counts: array, channels: array, more: bool}
     */
    public static function inbox(int $uid, ?int $channelId, string $state, ?string $item, int $page): array
    {
        $pdo = Database::connection();
        $channels = array_values(array_filter(Channels::forUser($uid), static fn (array $c): bool => isset(self::NETWORKS[$c['provider']])));
        $where = 'c.user_id = :uid';
        $params = ['uid' => $uid];
        if ($channelId !== null) {
            $where .= ' AND c.channel_id = :cid';
            $params['cid'] = $channelId;
        }
        $counts = array_fill_keys(array_keys(self::STATES), 0);
        $stmt = $pdo->prepare("SELECT c.state, COUNT(*) AS n FROM comments c WHERE $where GROUP BY c.state");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            $counts[$r['state']] = (int) $r['n'];
        }
        $counts['all'] = array_sum($counts);

        $filter = $where;
        if ($state !== 'all') {
            $filter .= ' AND c.state = :state';
            $params['state'] = $state;
        }
        $stmt = $pdo->prepare("SELECT c.item_id, c.channel_id, MAX(c.item_title) AS item_title, MAX(c.item_url) AS item_url, COUNT(*) AS n, MAX(c.published_at) AS latest
            FROM comments c WHERE $filter GROUP BY c.item_id, c.channel_id ORDER BY latest DESC LIMIT 60");
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        if ($item !== null) {
            $filter .= ' AND c.item_id = :item';
            $params['item'] = $item;
        }
        $offset = max(0, $page - 1) * self::PAGE;
        $stmt = $pdo->prepare("SELECT c.*, ch.provider, ch.name AS channel_name FROM comments c JOIN channels ch ON ch.id = c.channel_id
            WHERE $filter ORDER BY c.published_at DESC, c.id DESC LIMIT " . (self::PAGE + 1) . ' OFFSET ' . $offset);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $more = count($rows) > self::PAGE;
        return ['comments' => array_slice($rows, 0, self::PAGE), 'items' => $items, 'counts' => $counts, 'channels' => $channels, 'more' => $more];
    }

    /** Count for the sidebar badge. */
    public static function unanswered(int $uid): int
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM comments WHERE user_id = :uid AND state = 'open'");
        $stmt->execute(['uid' => $uid]);
        return (int) $stmt->fetchColumn();
    }

    /** POST /app/community */
    public static function handle(): never
    {
        $user = Auth::member();
        $uid = (int) $user['id'];
        $back = self::back();
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'refresh') {
            $ran = 0;
            $problems = [];
            foreach (Channels::forUser($uid) as $c) {
                if (!isset(self::NETWORKS[$c['provider']])) {
                    continue;
                }
                $last = self::checkedAt((int) $c['id']);
                if ($last !== null && strtotime($last . ' UTC') > time() - self::MANUAL_MINUTES * 60) {
                    continue;
                }
                $full = Channels::find($uid, (int) $c['id']);
                if ($full !== null && ($problem = self::refreshChannel($full)) !== null) {
                    $problems[] = $problem;
                }
                $ran++;
            }
            self::done($back, $ran === 0 ? 'Comments were read a moment ago. Try again in a few minutes.' : ($problems ? $problems[0] : 'Comments are up to date.'));
        }

        $comment = self::find($uid, (int) ($_POST['id'] ?? 0));
        if ($comment === null) {
            self::done($back, 'That comment is no longer in your inbox.');
        }
        $back .= '#cm-' . (int) $comment['id'];

        if ($action === 'done' || $action === 'reopen') {
            Database::connection()->prepare('UPDATE comments SET state = :s WHERE id = :id AND user_id = :uid')
                ->execute(['s' => $action === 'done' ? 'done' : 'open', 'id' => (int) $comment['id'], 'uid' => $uid]);
            self::done($back, $action === 'done' ? 'Marked as done.' : 'Moved back to Unanswered.');
        }
        if ($action === 'suggest') {
            self::suggest($user, $comment, $back);
        }
        if ($action !== 'reply') {
            self::done($back, 'Nothing changed.');
        }

        $text = trim(str_replace("\r\n", "\n", (string) ($_POST['reply'] ?? '')));
        if ($text === '') {
            self::done($back, 'Write a reply first.');
        }
        if (mb_strlen($text) > self::REPLY_MAX) {
            self::done($back, sprintf('Keep replies under %d characters.', self::REPLY_MAX));
        }
        $fp = 'user:' . $uid;
        if (RateLimiter::tooManyAttempts('inbox-reply', $fp, 30, 10)) {
            self::done($back, 'That’s a lot of replies at once. Wait a few minutes, then send the rest.');
        }
        RateLimiter::hit('inbox-reply', $fp);
        $channel = Channels::find($uid, (int) $comment['channel_id']);
        if ($channel === null) {
            self::done($back, 'That channel was disconnected.');
        }
        $name = self::NETWORKS[$channel['provider']] ?? 'the network';
        if (!self::canReply($channel)) {
            self::done($back, 'To reply from atwebtech, reconnect ' . $name . ' and allow managing comments. You can also reply on ' . $name . ' with the link on the comment.');
        }
        try {
            self::sendReply($channel, $comment, $text);
        } catch (Throwable $e) {
            error_log(sprintf('[%s] inbox reply %d: %s', gmdate('c'), (int) $comment['id'], $e->getMessage()));
            $_SESSION['_cm_draft'] = ['id' => (int) $comment['id'], 'text' => $text];
            self::done($back, match (true) {
                $e instanceof ChannelAuthException => $e->getMessage() . ' Your reply is still in the box.',
                self::isPermission($e) => 'Reconnect ' . $name . ' and allow managing comments to reply from atwebtech. Your reply is still in the box.',
                default => $name . ' didn’t accept the reply. Your reply is still in the box. Try again shortly.',
            });
        }
        Database::connection()->prepare("UPDATE comments SET state = 'replied', reply_body = :body, replied_at = :now WHERE id = :id AND user_id = :uid")
            ->execute(['body' => $text, 'now' => Database::now(), 'id' => (int) $comment['id'], 'uid' => $uid]);
        self::done($back, 'Reply sent to ' . $name . '.');
    }

    /** A reply draft written by the AI Assistant, kept in the session for the next page load. */
    private static function suggest(array $user, array $comment, string $back): never
    {
        if (!Assistant::enabled()) {
            self::done($back, 'The AI Assistant isn’t switched on for this workspace yet.');
        }
        if (($problem = Assistant::allowanceProblem((int) $user['id'], 1)) !== null) {
            self::done($back, $problem);
        }
        RateLimiter::hit('ai-minute', 'user:' . (int) $user['id']);
        RateLimiter::hit('ai-day', 'user:' . (int) $user['id']);
        $network = self::NETWORKS[$comment['provider'] ?? ''] ?? 'social media';
        try {
            $text = Assistant::complete([
                ['role' => 'system', 'content' => 'You help a small business reply to comments on ' . $network . '. Write one short, warm, specific reply in the language of the comment. '
                    . 'Reply with the text only: no quotes, no hashtags, no sign-off. Never promise refunds, discounts, prices or dates, and never invent facts; '
                    . 'if the comment needs details you don’t have, invite them to send a direct message. Under 280 characters.'],
                ['role' => 'user', 'content' => 'Post: ' . mb_substr((string) ($comment['item_title'] ?? ''), 0, 300) . "\nComment by " . $comment['author_name'] . ': ' . mb_substr((string) $comment['body'], 0, 1000)],
            ], ['max_tokens' => 200, 'timeout' => 25]);
        } catch (Throwable $e) {
            error_log(sprintf('[%s] inbox suggest %d: %s', gmdate('c'), (int) $comment['id'], $e->getMessage()));
            self::done($back, 'The AI Assistant couldn’t answer just now. Please try again.');
        }
        $_SESSION['_cm_draft'] = ['id' => (int) $comment['id'], 'text' => mb_substr(trim($text, " \"\n"), 0, self::REPLY_MAX)];
        self::done($back, 'Suggested reply added. Edit it, then send.');
    }

    private static function sendReply(array $channel, array $comment, string $text): void
    {
        $token = Channels::accessToken($channel);
        $id = (string) $comment['external_id'];
        switch ($channel['provider']) {
            case 'youtube':
                self::call('POST', self::YT . '/comments?part=snippet', ['snippet' => ['parentId' => $id, 'textOriginal' => $text]], $token, true);
                return;
            case 'facebook':
                self::call('POST', self::GRAPH . '/' . rawurlencode($id) . '/comments', ['message' => $text, 'access_token' => $token]);
                return;
            case 'instagram':
                self::call('POST', self::IG . '/' . rawurlencode($id) . '/replies', ['message' => $text, 'access_token' => $token]);
                return;
            case 'threads':
                $made = self::call('POST', self::THREADS . '/me/threads', ['media_type' => 'TEXT', 'text' => $text, 'reply_to_id' => $id, 'access_token' => $token]);
                if (empty($made['id'])) {
                    throw new ChannelApiException('Threads returned no container id', 0);
                }
                self::call('POST', self::THREADS . '/me/threads_publish', ['creation_id' => $made['id'], 'access_token' => $token]);
                return;
        }
        throw new ChannelApiException('Replies are not supported for this network', 0);
    }

    // Readers. Each returns a list of comments: external_id, item_id, item_title, item_url, author_*, is_own, body, likes, replies, published_at.

    private static function youtube(array $channel, string $token): array
    {
        $own = rawurldecode((string) $channel['external_id']);
        $base = ['part' => 'snippet', 'maxResults' => 50, 'order' => 'time', 'textFormat' => 'plainText'];
        $threads = [];
        try {
            $data = self::call('GET', self::YT . '/commentThreads?' . http_build_query($base + ['allThreadsRelatedToChannelId' => $own]), null, $token);
            $threads = $data['items'] ?? [];
        } catch (ChannelApiException $e) {
            if (!in_array($e->status, [400, 404], true)) {
                throw $e;
            }
            foreach (array_slice(self::sentPosts($channel), 0, 10) as $post) {
                try {
                    $data = self::call('GET', self::YT . '/commentThreads?' . http_build_query(['maxResults' => 20, 'videoId' => $post['external_id']] + $base), null, $token);
                    array_push($threads, ...($data['items'] ?? []));
                } catch (ChannelApiException $inner) {
                    if ($inner->status !== 403) { // Comments turned off on this video.
                        throw $inner;
                    }
                }
            }
        }
        $titles = [];
        $videoIds = array_values(array_unique(array_filter(array_map(static fn (array $t): string => (string) ($t['snippet']['videoId'] ?? ''), $threads))));
        foreach (array_chunk($videoIds, 50) as $chunk) {
            $data = self::call('GET', self::YT . '/videos?' . http_build_query(['part' => 'snippet', 'id' => implode(',', $chunk)]), null, $token);
            foreach ($data['items'] ?? [] as $v) {
                $titles[$v['id']] = (string) ($v['snippet']['title'] ?? '');
            }
        }
        $out = [];
        foreach ($threads as $t) {
            $top = $t['snippet']['topLevelComment'] ?? null;
            $video = (string) ($t['snippet']['videoId'] ?? '');
            if (!is_array($top) || $video === '') {
                continue;
            }
            $s = $top['snippet'] ?? [];
            $out[] = [
                'external_id' => (string) $top['id'],
                'item_id' => $video,
                'item_title' => $titles[$video] ?? null,
                'item_url' => 'https://www.youtube.com/watch?v=' . rawurlencode($video) . '&lc=' . rawurlencode((string) $top['id']),
                'author_name' => (string) ($s['authorDisplayName'] ?? 'YouTube viewer'),
                'author_avatar' => $s['authorProfileImageUrl'] ?? null,
                'author_url' => $s['authorChannelUrl'] ?? null,
                'is_own' => ($s['authorChannelId']['value'] ?? '') === $own,
                'body' => (string) ($s['textOriginal'] ?? $s['textDisplay'] ?? ''),
                'likes' => $s['likeCount'] ?? null,
                'replies' => $t['snippet']['totalReplyCount'] ?? null,
                'published_at' => (string) ($s['publishedAt'] ?? ''),
            ];
        }
        return $out;
    }

    private static function facebook(array $channel, string $token): array
    {
        $page = (string) $channel['external_id'];
        $items = [];
        try {
            $data = self::call('GET', self::GRAPH . '/' . rawurlencode($page) . '/published_posts?' . http_build_query(['fields' => 'id,message,permalink_url', 'limit' => self::ITEMS, 'access_token' => $token]));
            foreach ($data['data'] ?? [] as $p) {
                $items[(string) $p['id']] = ['title' => $p['message'] ?? null, 'url' => $p['permalink_url'] ?? null];
            }
        } catch (ChannelApiException $e) {
            if (!self::isPermission($e)) {
                throw $e;
            }
        }
        $items += self::sentItems($channel);
        $out = [];
        foreach ($items as $id => $meta) {
            $data = self::call('GET', self::GRAPH . '/' . rawurlencode((string) $id) . '/comments?' . http_build_query([
                'fields' => 'id,message,created_time,from{id,name,picture},like_count,comment_count,permalink_url',
                'filter' => 'toplevel', 'order' => 'reverse_chronological', 'limit' => 25, 'access_token' => $token,
            ]));
            foreach ($data['data'] ?? [] as $c) {
                $out[] = [
                    'external_id' => (string) $c['id'],
                    'item_id' => (string) $id,
                    'item_title' => $meta['title'],
                    'item_url' => $c['permalink_url'] ?? $meta['url'],
                    'author_name' => (string) ($c['from']['name'] ?? 'Facebook user'),
                    'author_avatar' => $c['from']['picture']['data']['url'] ?? null,
                    'author_url' => null,
                    'is_own' => (string) ($c['from']['id'] ?? '') === $page,
                    'body' => (string) ($c['message'] ?? ''),
                    'likes' => $c['like_count'] ?? null,
                    'replies' => $c['comment_count'] ?? null,
                    'published_at' => (string) ($c['created_time'] ?? ''),
                ];
            }
        }
        return $out;
    }

    private static function instagram(array $channel, string $token): array
    {
        $items = [];
        $data = self::call('GET', self::IG . '/me/media?' . http_build_query(['fields' => 'id,caption,permalink', 'limit' => self::ITEMS, 'access_token' => $token]));
        foreach ($data['data'] ?? [] as $m) {
            $items[(string) $m['id']] = ['title' => $m['caption'] ?? null, 'url' => $m['permalink'] ?? null];
        }
        $items += self::sentItems($channel);
        $own = ltrim(strtolower((string) $channel['name']), '@');
        $out = [];
        foreach ($items as $id => $meta) {
            $data = self::call('GET', self::IG . '/' . rawurlencode((string) $id) . '/comments?' . http_build_query(['fields' => 'id,text,username,timestamp,like_count,replies{id}', 'limit' => 50, 'access_token' => $token]));
            foreach ($data['data'] ?? [] as $c) {
                $who = (string) ($c['username'] ?? '');
                $out[] = [
                    'external_id' => (string) $c['id'],
                    'item_id' => (string) $id,
                    'item_title' => $meta['title'],
                    'item_url' => $meta['url'],
                    'author_name' => $who !== '' ? '@' . $who : 'Instagram user',
                    'author_avatar' => null,
                    'author_url' => $who !== '' ? 'https://www.instagram.com/' . rawurlencode($who) . '/' : null,
                    'is_own' => $who !== '' && strtolower($who) === $own,
                    'body' => (string) ($c['text'] ?? ''),
                    'likes' => $c['like_count'] ?? null,
                    'replies' => isset($c['replies']['data']) ? count($c['replies']['data']) : null,
                    'published_at' => (string) ($c['timestamp'] ?? ''),
                ];
            }
        }
        return $out;
    }

    private static function threads(array $channel, string $token): array
    {
        $items = [];
        $data = self::call('GET', self::THREADS . '/me/threads?' . http_build_query(['fields' => 'id,text,permalink', 'limit' => self::ITEMS, 'access_token' => $token]));
        foreach ($data['data'] ?? [] as $m) {
            $items[(string) $m['id']] = ['title' => $m['text'] ?? null, 'url' => $m['permalink'] ?? null];
        }
        $items += self::sentItems($channel);
        $out = [];
        foreach ($items as $id => $meta) {
            $data = self::call('GET', self::THREADS . '/' . rawurlencode((string) $id) . '/replies?' . http_build_query(['fields' => 'id,text,username,timestamp,permalink,is_reply_owned_by_me', 'reverse' => 'true', 'access_token' => $token]));
            foreach ($data['data'] ?? [] as $c) {
                $who = (string) ($c['username'] ?? '');
                $out[] = [
                    'external_id' => (string) $c['id'],
                    'item_id' => (string) $id,
                    'item_title' => $meta['title'],
                    'item_url' => $c['permalink'] ?? $meta['url'],
                    'author_name' => $who !== '' ? '@' . $who : 'Threads user',
                    'author_avatar' => null,
                    'author_url' => $who !== '' ? 'https://www.threads.com/@' . rawurlencode($who) : null,
                    'is_own' => !empty($c['is_reply_owned_by_me']),
                    'body' => (string) ($c['text'] ?? ''),
                    'likes' => null,
                    'replies' => null,
                    'published_at' => (string) ($c['timestamp'] ?? ''),
                ];
            }
        }
        return $out;
    }

    /** Posts atwebtech sent to this channel in the last 30 days. */
    private static function sentPosts(array $channel): array
    {
        $stmt = Database::connection()->prepare("SELECT id, title, body, external_id, external_url FROM posts WHERE channel_id = :cid AND status = 'sent'
            AND external_id IS NOT NULL AND sent_at >= :since ORDER BY sent_at DESC LIMIT " . self::ITEMS);
        $stmt->execute(['cid' => (int) $channel['id'], 'since' => gmdate('Y-m-d H:i:s', time() - 30 * 86400)]);
        return $stmt->fetchAll();
    }

    /** @return array<string, array{title:?string, url:?string}> */
    private static function sentItems(array $channel): array
    {
        $out = [];
        foreach (self::sentPosts($channel) as $p) {
            $out[(string) $p['external_id']] = ['title' => ($p['body'] ?? '') !== '' ? $p['body'] : ($p['title'] ?? null), 'url' => $p['external_url'] ?? null];
        }
        return $out;
    }

    private static function save(array $channel, array $comments): void
    {
        $pdo = Database::connection();
        $now = Database::now();
        $posts = [];
        foreach (self::sentPosts($channel) as $p) {
            $posts[(string) $p['external_id']] = (int) $p['id'];
        }
        $find = $pdo->prepare('SELECT id FROM comments WHERE channel_id = :cid AND external_id = :ext');
        $update = $pdo->prepare('UPDATE comments SET body = :body, likes = :likes, replies = :replies, item_title = COALESCE(:title, item_title), fetched_at = :now WHERE id = :id');
        $insert = $pdo->prepare('INSERT INTO comments (user_id, channel_id, post_id, external_id, item_id, item_title, item_url, author_name, author_avatar, author_url, is_own, body, likes, replies, published_at, state, fetched_at)
            VALUES (:uid, :cid, :pid, :ext, :item, :title, :url, :name, :avatar, :aurl, :own, :body, :likes, :replies, :at, :state, :now)');
        foreach ($comments as $c) {
            if ($c['external_id'] === '' || trim($c['body']) === '') {
                continue;
            }
            $at = strtotime($c['published_at']);
            $title = $c['item_title'] !== null ? mb_substr(trim(preg_replace('/\s+/', ' ', (string) $c['item_title'])), 0, 300) : null;
            $find->execute(['cid' => (int) $channel['id'], 'ext' => $c['external_id']]);
            $id = $find->fetchColumn();
            $counts = ['likes' => $c['likes'] !== null ? (int) $c['likes'] : null, 'replies' => $c['replies'] !== null ? (int) $c['replies'] : null];
            if ($id !== false) {
                $update->execute(['body' => mb_substr($c['body'], 0, 5000), 'title' => $title, 'now' => $now, 'id' => (int) $id] + $counts);
                continue;
            }
            $insert->execute([
                'uid' => (int) $channel['user_id'], 'cid' => (int) $channel['id'], 'pid' => $posts[$c['item_id']] ?? null,
                'ext' => mb_substr($c['external_id'], 0, 191), 'item' => mb_substr($c['item_id'], 0, 191), 'title' => $title,
                'url' => self::safeUrl($c['item_url']), 'name' => mb_substr($c['author_name'], 0, 191),
                'avatar' => self::safeUrl($c['author_avatar']), 'aurl' => self::safeUrl($c['author_url']), 'own' => $c['is_own'] ? 1 : 0,
                'body' => mb_substr($c['body'], 0, 5000), 'at' => gmdate('Y-m-d H:i:s', $at ?: time()),
                'state' => $c['is_own'] ? 'done' : 'open', 'now' => $now,
            ] + $counts);
        }
    }

    private static function safeUrl(mixed $url): ?string
    {
        $url = is_string($url) ? $url : '';
        return str_starts_with($url, 'https://') && strlen($url) <= 500 ? $url : null;
    }

    private static function find(int $uid, int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT c.*, ch.provider FROM comments c JOIN channels ch ON ch.id = c.channel_id WHERE c.id = :id AND c.user_id = :uid');
        $stmt->execute(['id' => $id, 'uid' => $uid]);
        return $stmt->fetch() ?: null;
    }

    private static function checkedAt(int $channelId): ?string
    {
        $stmt = Database::connection()->prepare('SELECT inbox_checked_at FROM channels WHERE id = :id');
        $stmt->execute(['id' => $channelId]);
        $v = $stmt->fetchColumn();
        return $v ? (string) $v : null;
    }

    /** Channel problems to show above the inbox: channel id => message. */
    public static function problems(int $uid): array
    {
        $stmt = Database::connection()->prepare('SELECT id, inbox_error FROM channels WHERE user_id = :uid AND inbox_error IS NOT NULL');
        $stmt->execute(['uid' => $uid]);
        return array_column($stmt->fetchAll(), 'inbox_error', 'id');
    }

    /** Where to go after a POST: back to the same inbox view. */
    private static function back(): string
    {
        $q = [];
        foreach (['state' => ['all', 'open', 'replied', 'done'], 'view' => ['post', 'list']] as $key => $allowed) {
            if (isset($_POST[$key]) && in_array($_POST[$key], $allowed, true)) {
                $q[$key] = $_POST[$key];
            }
        }
        foreach (['channel', 'page'] as $key) {
            if (isset($_POST[$key]) && ctype_digit((string) $_POST[$key])) {
                $q[$key] = (string) $_POST[$key];
            }
        }
        if (isset($_POST['item']) && is_string($_POST['item']) && preg_match('/^[A-Za-z0-9_.:-]{1,191}$/', $_POST['item'])) {
            $q['item'] = $_POST['item'];
        }
        $q = array_filter($q, static fn ($v, $k) => !($k === 'state' && $v === 'open') && !($k === 'view' && $v === 'post') && !($k === 'page' && $v === '1'), ARRAY_FILTER_USE_BOTH);
        return '/app/community' . ($q ? '?' . http_build_query($q) : '');
    }

    private static function explain(string $provider, Throwable $e): string
    {
        $name = self::NETWORKS[$provider] ?? 'The network';
        return match (true) {
            $e instanceof ChannelAuthException => $e->getMessage(),
            self::isPermission($e) => 'Reconnect ' . $name . ' and allow access to comments to see them here.',
            $e instanceof ChannelApiException && $e->status === 429 => $name . ' limited how often comments can be read. atwebtech will try again later.',
            default => $name . ' didn’t return comments this time. atwebtech will try again later.',
        };
    }

    private static function isPermission(Throwable $e): bool
    {
        return $e instanceof CommunityPermission || ($e instanceof ChannelApiException && in_array($e->status, [401, 403], true));
    }

    private static function inList(): string
    {
        return implode(', ', array_map(static fn (string $p): string => "'" . $p . "'", array_keys(self::NETWORKS)));
    }

    /** Call a network API. Graph "missing permission" errors become CommunityPermission. */
    private static function call(string $method, string $url, ?array $body = null, ?string $bearer = null, bool $json = false): array
    {
        $headers = ['Accept: application/json'];
        if ($bearer !== null) {
            $headers[] = 'Authorization: Bearer ' . $bearer;
        }
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            if ($json) {
                $headers[] = 'Content-Type: application/json';
                $opts[CURLOPT_POSTFIELDS] = json_encode($body ?? [], JSON_THROW_ON_ERROR);
            } else {
                $opts[CURLOPT_POSTFIELDS] = http_build_query($body ?? []);
            }
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($raw === false) {
            throw new ChannelApiException('Request failed: ' . curl_error($ch), 0);
        }
        $data = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300) {
            $code = is_array($data) ? (int) ($data['error']['code'] ?? 0) : 0;
            $reason = is_array($data) ? (string) ($data['error']['message'] ?? '') : '';
            if (in_array($code, [10, 200], true) || ($code === 100 && str_contains($reason, 'permission')) || str_contains($reason, 'insufficientPermissions')) {
                throw new CommunityPermission($reason);
            }
            throw new ChannelApiException(sprintf('HTTP %d from %s %s', $status, parse_url($url, PHP_URL_HOST), mb_substr(strip_tags($reason), 0, 200)), $status);
        }
        return is_array($data) ? $data : [];
    }

    private static function done(string $location, string $message): never
    {
        flash('notice', $message);
        redirect($location);
    }
}

final class CommunityPermission extends RuntimeException
{
}
