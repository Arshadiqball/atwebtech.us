<?php

declare(strict_types=1);

/**
 * Automations (paid plans) and other time savers.
 *  - feed:  new articles from a followed feed (your blog) become posts for chosen channels.
 *  - plan:  every week the AI Assistant writes next week's posts into free posting slots.
 *  - reply: a comment containing a keyword gets a set answer.
 * Each automation chooses what happens to the posts it makes: Drafts, sent for approval, or scheduled.
 * Also here: recurring posts (any plan), the empty-queue alert and the Monday summary.
 * Runs from bin/publish.php; nothing in here may break publishing, so every run is wrapped.
 */
final class Automations
{
    public const MAX = 20;
    public const MODES = ['draft' => 'Save to Drafts for me to check', 'review' => 'Send for approval', 'schedule' => 'Schedule automatically'];
    public const REPEATS = ['' => 'Doesn’t repeat', 'daily' => 'Repeats every day', 'weekly' => 'Repeats every week', 'biweekly' => 'Repeats every 2 weeks', 'monthly' => 'Repeats every month'];
    private const REPEAT_STEP = ['daily' => '+1 day', 'weekly' => '+1 week', 'biweekly' => '+2 weeks', 'monthly' => '+1 month'];
    private const FEED_EVERY_MINUTES = 30;
    private const FEED_ITEMS_PER_RUN = 3;
    private const PLAN_EVERY_HOURS = 156; // 6.5 days, so a weekly plan never drifts later each week
    private const AUTO_REPLIES_PER_DAY = 25;

    // Page and form handling

    public static function forUser(int $uid): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM automations WHERE user_id = :uid ORDER BY kind, id');
        $stmt->execute(['uid' => $uid]);
        return array_map(static function (array $a): array {
            $a['config'] = (array) json_decode((string) $a['config'], true);
            return $a;
        }, $stmt->fetchAll());
    }

    public static function page(array $user): array
    {
        $uid = (int) $user['id'];
        $channels = Channels::forUser($uid);
        return [
            'items' => self::forUser($uid),
            'feeds' => Feeds::list($uid),
            'textChannels' => array_values(array_filter($channels, static fn (array $c): bool => $c['can_publish'] && in_array($c['provider'], QueueTools::TEXT_NETWORKS, true))),
            'replyChannels' => array_values(array_filter($channels, static fn (array $c): bool => Community::canReply($c))),
            'allChannels' => array_column($channels, null, 'id'),
            'paid' => Channels::allowance($uid)['paid'],
            'ai' => Assistant::enabled(),
        ];
    }

    /** POST /app/automations */
    public static function handle(): never
    {
        $user = Auth::member();
        $uid = (int) $user['id'];
        $back = '/app/automations';
        $pdo = Database::connection();
        $action = (string) ($_POST['action'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'delete') {
            $pdo->prepare('DELETE FROM automations WHERE id = :id AND user_id = :uid')->execute(['id' => $id, 'uid' => $uid]);
            self::done($back, 'Automation deleted. Posts it already made stay where they are.');
        }
        if ($action === 'toggle') {
            $pdo->prepare('UPDATE automations SET enabled = 1 - enabled WHERE id = :id AND user_id = :uid')->execute(['id' => $id, 'uid' => $uid]);
            self::done($back, 'Saved.');
        }
        if (!Channels::allowance($uid)['paid']) {
            self::done($back, 'Automations are part of Essentials and Team. See Plan & billing to upgrade.');
        }
        if ($action === 'run') {
            $pdo->prepare("UPDATE automations SET last_run_at = NULL, last_result = 'Queued. It runs within a couple of minutes.' WHERE id = :id AND user_id = :uid AND kind IN ('feed', 'plan')")->execute(['id' => $id, 'uid' => $uid]);
            self::done($back, 'Queued. It runs within a couple of minutes.');
        }
        if (count(self::forUser($uid)) >= self::MAX) {
            self::done($back, sprintf('A workspace can have up to %d automations. Delete one first.', self::MAX));
        }
        $mode = isset(self::MODES[$_POST['mode'] ?? '']) ? (string) $_POST['mode'] : 'draft';
        $owned = array_column(Channels::forUser($uid), null, 'id');
        $picked = array_values(array_intersect(array_map('intval', (array) ($_POST['channels'] ?? [])), array_keys($owned)));

        if ($action === 'create_feed') {
            $feedId = (int) ($_POST['feed_id'] ?? 0);
            $url = trim((string) ($_POST['feed_url'] ?? ''));
            if ($url !== '') {
                try {
                    $feedId = Feeds::follow($uid, $url);
                } catch (Throwable $e) {
                    self::done($back, $e instanceof FeedProblem ? $e->getMessage() : 'We couldn’t find a feed at that address. Try your blog’s home page or its RSS link.');
                }
            }
            $feed = $pdo->prepare('SELECT id, title FROM feeds WHERE id = :id AND user_id = :uid');
            $feed->execute(['id' => $feedId, 'uid' => $uid]);
            $feed = $feed->fetch();
            $picked = array_values(array_filter($picked, static fn (int $c): bool => in_array($owned[$c]['provider'], QueueTools::TEXT_NETWORKS, true)));
            if (!$feed) {
                self::done($back, 'Choose a feed, or paste your blog’s address.');
            }
            if (!$picked) {
                self::done($back, 'Choose at least one channel that can post text with a link.');
            }
            // Start from now: articles already in the feed aren't posted.
            $last = $pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM feed_items WHERE feed_id = :f');
            $last->execute(['f' => (int) $feed['id']]);
            self::store($uid, 'feed', 'New articles from ' . $feed['title'], ['feed_id' => (int) $feed['id'], 'channel_ids' => $picked, 'mode' => $mode, 'ai' => !empty($_POST['ai'])], (int) $last->fetchColumn());
            self::done($back, 'Done. When ' . $feed['title'] . ' publishes something new, atwebtech writes the posts for you.');
        }
        if ($action === 'create_plan') {
            $topic = mb_substr(trim((string) ($_POST['topic'] ?? '')), 0, 120);
            $details = mb_substr(trim((string) ($_POST['details'] ?? '')), 0, 800);
            $perWeek = max(1, min(7, (int) ($_POST['per_week'] ?? 3)));
            $tone = isset(Assistant::TONES[$_POST['tone'] ?? '']) ? (string) $_POST['tone'] : 'friendly';
            $language = in_array($_POST['language'] ?? '', Assistant::LANGUAGES, true) ? (string) $_POST['language'] : 'English';
            $picked = array_values(array_filter($picked, static fn (int $c): bool => in_array($owned[$c]['provider'], QueueTools::TEXT_NETWORKS, true)));
            if (!Assistant::enabled()) {
                self::done($back, 'The AI Assistant isn’t switched on for this workspace yet.');
            }
            if ($topic === '' || !$picked) {
                self::done($back, 'Say what your business is about and choose at least one channel.');
            }
            self::store($uid, 'plan', 'Weekly plan: ' . $topic, ['channel_ids' => $picked, 'topic' => $topic, 'details' => $details, 'per_week' => $perWeek, 'tone' => $tone, 'language' => $language, 'mode' => $mode]);
            self::done($back, 'Done. Your first week of posts will be in ' . ($mode === 'draft' ? 'Drafts' : ($mode === 'review' ? 'Approvals' : 'the queue')) . ' in a few minutes, then every week.');
        }
        if ($action === 'create_reply') {
            $keywords = array_values(array_unique(array_filter(array_map(static fn (string $k): string => mb_strtolower(trim($k)), explode(',', (string) ($_POST['keywords'] ?? ''))), static fn (string $k): bool => mb_strlen($k) >= 2 && mb_strlen($k) <= 40)));
            $text = trim(str_replace("\r\n", "\n", (string) ($_POST['text'] ?? '')));
            if (!$keywords || count($keywords) > 15) {
                self::done($back, 'Add 1 to 15 words or phrases, separated by commas.');
            }
            if ($text === '' || mb_strlen($text) > 500) {
                self::done($back, 'Write the reply (up to 500 characters).');
            }
            self::store($uid, 'reply', 'Reply to “' . implode('”, “', array_slice($keywords, 0, 3)) . (count($keywords) > 3 ? '”…' : '”'), ['keywords' => $keywords, 'text' => $text, 'channel_ids' => $picked]);
            self::done($back, 'Done. New comments with those words get this reply automatically.');
        }
        self::done($back, 'Nothing changed.');
    }

    private static function store(int $uid, string $kind, string $name, array $config, ?int $lastItem = null): void
    {
        Database::connection()->prepare('INSERT INTO automations (user_id, kind, name, config, enabled, last_item_id, created_at) VALUES (:uid, :k, :n, :c, 1, :last, :now)')
            ->execute(['uid' => $uid, 'k' => $kind, 'n' => mb_substr($name, 0, 80), 'c' => json_encode($config, JSON_UNESCAPED_UNICODE), 'last' => $lastItem, 'now' => Database::now()]);
    }

    // Runner

    /** Run the feed and plan automations that are due. Stops after $seconds. Returns how many posts were made. */
    public static function run(int $seconds = 25): int
    {
        $started = time();
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT * FROM automations WHERE enabled = 1 AND ((kind = 'feed' AND (last_run_at IS NULL OR last_run_at < :feed)) OR (kind = 'plan' AND (last_run_at IS NULL OR last_run_at < :plan)))
            ORDER BY last_run_at IS NOT NULL, last_run_at LIMIT 20");
        $stmt->execute(['feed' => gmdate('Y-m-d H:i:s', time() - self::FEED_EVERY_MINUTES * 60), 'plan' => gmdate('Y-m-d H:i:s', time() - self::PLAN_EVERY_HOURS * 3600)]);
        $made = 0;
        foreach ($stmt->fetchAll() as $a) {
            if (time() - $started > $seconds) {
                break;
            }
            $a['config'] = (array) json_decode((string) $a['config'], true);
            $runAt = Database::now();
            try {
                if (!Channels::allowance((int) $a['user_id'])['paid']) {
                    [$n, $result] = [0, 'Paused: automations need the Essentials or Team plan.'];
                } else {
                    [$n, $result, $retryHours] = ($a['kind'] === 'feed' ? self::runFeed($a) : self::runPlan($a)) + [2 => 0];
                    if ($retryHours > 0) {
                        // Try again sooner than the normal weekly gap.
                        $runAt = gmdate('Y-m-d H:i:s', time() - (self::PLAN_EVERY_HOURS - $retryHours) * 3600);
                    }
                }
            } catch (Throwable $e) {
                error_log(sprintf('[%s] automation %d: %s', gmdate('c'), (int) $a['id'], $e->getMessage()));
                [$n, $result] = [0, 'It couldn’t run this time. atwebtech will try again.'];
            }
            $made += $n;
            $pdo->prepare('UPDATE automations SET last_run_at = :at, last_result = COALESCE(:r, last_result), made = made + :n WHERE id = :id')
                ->execute(['at' => $runAt, 'r' => $result, 'n' => $n, 'id' => (int) $a['id']]);
        }
        return $made;
    }

    /** @return array{0:int, 1:?string} posts made and a note for the page (null keeps the last note) */
    private static function runFeed(array $a): array
    {
        $pdo = Database::connection();
        $uid = (int) $a['user_id'];
        $cfg = $a['config'];
        $feed = $pdo->prepare('SELECT * FROM feeds WHERE id = :id AND user_id = :uid');
        $feed->execute(['id' => (int) ($cfg['feed_id'] ?? 0), 'uid' => $uid]);
        $feed = $feed->fetch();
        if (!$feed) {
            $pdo->prepare('UPDATE automations SET enabled = 0 WHERE id = :id')->execute(['id' => (int) $a['id']]);
            return [0, 'Turned off: the feed was removed.'];
        }
        Feeds::refresh($feed);
        $items = $pdo->prepare('SELECT * FROM feed_items WHERE feed_id = :f AND id > :last ORDER BY id ASC LIMIT ' . self::FEED_ITEMS_PER_RUN);
        $items->execute(['f' => (int) $feed['id'], 'last' => (int) $a['last_item_id']]);
        $items = $items->fetchAll();
        if (!$items) {
            return [0, null];
        }
        $tz = self::timezone($uid);
        $made = 0;
        $taken = [];
        foreach ($items as $item) {
            foreach ((array) ($cfg['channel_ids'] ?? []) as $cid) {
                $channel = Channels::find($uid, (int) $cid);
                if ($channel === null || !$channel['can_publish'] || !in_array($channel['provider'], QueueTools::TEXT_NETWORKS, true)) {
                    continue;
                }
                $limit = (int) (data('workspace')['limits'][$channel['provider']] ?? 500);
                $link = (string) $item['link'];
                $room = max(40, $limit - mb_strlen($link) - 2);
                $caption = !empty($cfg['ai']) ? self::aiCaption($uid, $item, $channel['provider'], $room) : null;
                $caption ??= mb_strimwidth(trim((string) $item['title']), 0, $room, '…');
                $slot = ($cfg['mode'] ?? 'draft') === 'draft' ? null : (self::nextSlots($channel, $tz, 1, 14, $taken)[0] ?? gmdate('Y-m-d H:i:s', time() + 3600));
                self::insertPost($uid, $channel, mb_strimwidth($caption, 0, $room, '…') . "\n\n" . $link, (string) ($cfg['mode'] ?? 'draft'), $slot, (int) $a['id']);
                $made++;
            }
            $pdo->prepare('UPDATE automations SET last_item_id = :i WHERE id = :id')->execute(['i' => (int) $item['id'], 'id' => (int) $a['id']]);
        }
        return [$made, sprintf('%s: %d %s from %d new %s.', gmdate('j M'), $made, $made === 1 ? 'post' : 'posts', count($items), count($items) === 1 ? 'article' : 'articles')];
    }

    /** @return array{0:int, 1:?string, 2?:int} posts made, a note, and hours until a retry when it couldn't finish */
    private static function runPlan(array $a): array
    {
        $uid = (int) $a['user_id'];
        $cfg = $a['config'];
        if (!Assistant::enabled()) {
            return [0, 'The AI Assistant isn’t switched on, so no posts were written.', 24];
        }
        $tz = self::timezone($uid);
        $made = 0;
        $taken = [];
        $short = false;
        foreach ((array) ($cfg['channel_ids'] ?? []) as $cid) {
            $channel = Channels::find($uid, (int) $cid);
            if ($channel === null || !$channel['can_publish'] || !in_array($channel['provider'], QueueTools::TEXT_NETWORKS, true)) {
                continue;
            }
            $slots = self::nextSlots($channel, $tz, (int) ($cfg['per_week'] ?? 3), 7, $taken);
            if (!$slots) {
                continue;
            }
            $limit = (int) (data('workspace')['limits'][$channel['provider']] ?? 500);
            $length = $limit <= 500 ? 'short' : 'medium';
            $avoid = [];
            foreach (array_chunk($slots, Assistant::CHUNK[$length]) as $chunk) {
                if (Assistant::allowanceProblem($uid, 1) !== null) {
                    $short = true;
                    break 2;
                }
                RateLimiter::hit('ai-minute', 'user:' . $uid);
                RateLimiter::hit('ai-day', 'user:' . $uid);
                $posts = Assistant::posts([
                    'mode' => 'random', 'topic' => (string) ($cfg['topic'] ?? ''), 'details' => (string) ($cfg['details'] ?? ''), 'provider' => $channel['provider'],
                    'tone' => (string) ($cfg['tone'] ?? 'friendly'), 'length' => $length, 'language' => (string) ($cfg['language'] ?? 'English'),
                    'hashtags' => true, 'emojis' => true, 'cta' => false, 'question' => false, 'rules' => '',
                    'dates' => array_map(static fn (string $utc): string => Workspace::local($utc, $tz)->format('l j F'), $chunk), 'avoid' => $avoid, 'trends' => [],
                ]);
                foreach ($posts as $i => $p) {
                    self::insertPost($uid, $channel, $p['body'], (string) ($cfg['mode'] ?? 'draft'), $chunk[$i], (int) $a['id']);
                    $avoid[] = mb_substr($p['body'], 0, 60);
                    $made++;
                }
            }
        }
        if ($made > 0 && ($cfg['mode'] ?? 'draft') !== 'schedule') {
            Notify::send($uid, 'approval_needed', 'Your weekly plan is ready: ' . $made . ' posts to check', 'The AI Assistant wrote next week’s posts. Read them, edit anything, then schedule.', ($cfg['mode'] ?? 'draft') === 'review' ? '/app/publish?tab=approvals' : '/app/publish?tab=drafts', Notify::team($uid, 'editor'));
        }
        if ($short) {
            return [$made, sprintf('%s: %d posts written. Today’s AI allowance ran out, so the rest come tomorrow.', gmdate('j M'), $made), $made > 0 ? 0 : 20];
        }
        return [$made, $made > 0 ? sprintf('%s: %d posts written for next week.', gmdate('j M'), $made) : 'No free posting slots next week, so nothing was written.'];
    }

    private static function aiCaption(int $uid, array $item, string $provider, int $room): ?string
    {
        if (!Assistant::enabled() || Assistant::allowanceProblem($uid, 1) !== null) {
            return null;
        }
        RateLimiter::hit('ai-minute', 'user:' . $uid);
        RateLimiter::hit('ai-day', 'user:' . $uid);
        $network = array_column(data('integrations'), 'name', 'slug')[$provider] ?? 'social media';
        try {
            $text = Assistant::complete([
                ['role' => 'system', 'content' => sprintf(
                    'You write a %s post for a small business sharing its new article. Reply with the post text only: no quotes, no markdown, and no link (it is added after your text). '
                    . 'Under %d characters. Use only what the title and summary say; never invent facts, numbers or quotes. One or two fitting hashtags at the end are fine.',
                    $network,
                    min($room, 400)
                )],
                ['role' => 'user', 'content' => 'Title: ' . $item['title'] . "\nSummary: " . ($item['summary'] ?? '')],
            ], ['max_tokens' => 220, 'timeout' => 25]);
            return trim($text, " \"\n") ?: null;
        } catch (Throwable $e) {
            error_log(sprintf('[%s] automation caption: %s', gmdate('c'), $e->getMessage()));
            return null;
        }
    }

    private static function insertPost(int $uid, array $channel, string $body, string $mode, ?string $plannedUtc, int $automationId): void
    {
        $now = Database::now();
        Database::connection()->prepare('INSERT INTO posts (user_id, channel, channel_id, body, status, planned_at, automation_id, created_at, updated_at) VALUES (:uid, :ch, :cid, :body, :status, :at, :auto, :now, :now2)')
            ->execute([
                'uid' => $uid, 'ch' => $channel['provider'], 'cid' => (int) $channel['id'], 'body' => $body,
                'status' => ['draft' => 'draft', 'review' => 'review', 'schedule' => 'planned'][$mode] ?? 'draft', 'at' => $plannedUtc, 'auto' => $automationId, 'now' => $now, 'now2' => $now,
            ]);
    }

    /**
     * The channel's next free posting slots as UTC datetimes. A slot is free when nothing is planned within
     * 45 minutes of it. Channels without a posting schedule use 10:00 every day. $taken carries slots given
     * out earlier in the same run.
     * @return list<string>
     */
    public static function nextSlots(array $channel, DateTimeZone $tz, int $count, int $days, array &$taken = []): array
    {
        $times = $channel['times'] ?: array_map(static fn (int $d): array => ['day' => $d, 'time' => '10:00'], range(1, 7));
        $busy = Database::connection()->prepare("SELECT COUNT(*) FROM posts WHERE channel_id = :cid AND status IN ('planned', 'review', 'publishing') AND planned_at BETWEEN :a AND :b");
        $utc = new DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $tz);
        $out = [];
        for ($d = 0; $d <= $days && count($out) < $count; $d++) {
            $day = $now->modify('+' . $d . ' days');
            $today = array_values(array_filter($times, static fn (array $s): bool => (int) $s['day'] === (int) $day->format('N')));
            usort($today, static fn (array $x, array $y): int => strcmp((string) $x['time'], (string) $y['time']));
            foreach ($today as $slot) {
                [$h, $m] = array_map('intval', explode(':', (string) $slot['time']));
                $at = $day->setTime($h, $m);
                $key = (int) $channel['id'] . ' ' . $at->format('Y-m-d H:i');
                if ($at <= $now->modify('+20 minutes') || isset($taken[$key])) {
                    continue;
                }
                $atUtc = $at->setTimezone($utc);
                $busy->execute(['cid' => (int) $channel['id'], 'a' => $atUtc->modify('-45 minutes')->format('Y-m-d H:i:s'), 'b' => $atUtc->modify('+45 minutes')->format('Y-m-d H:i:s')]);
                if ((int) $busy->fetchColumn() > 0) {
                    continue;
                }
                $taken[$key] = true;
                $out[] = $atUtc->format('Y-m-d H:i:s');
                if (count($out) >= $count) {
                    break;
                }
            }
        }
        return $out;
    }

    // Auto-replies

    /** Called for each new comment from someone else. Sends the first matching rule's reply. */
    public static function autoReply(array $channel, int $commentId, array $c, int $publishedTs): void
    {
        static $rules = [];
        $uid = (int) $channel['user_id'];
        try {
            if ($publishedTs < time() - 86400 || !Community::canReply($channel)) {
                return; // Never answer old comments, such as the backlog read when a channel is first connected.
            }
            $rules[$uid] ??= array_values(array_filter(self::forUser($uid), static fn (array $a): bool => $a['kind'] === 'reply' && (int) $a['enabled'] === 1));
            if (!$rules[$uid] || !Channels::allowance($uid)['paid']) {
                return;
            }
            $pdo = Database::connection();
            foreach ($rules[$uid] as $rule) {
                $only = (array) ($rule['config']['channel_ids'] ?? []);
                if ($only && !in_array((int) $channel['id'], array_map('intval', $only), true)) {
                    continue;
                }
                $hit = false;
                foreach ((array) ($rule['config']['keywords'] ?? []) as $word) {
                    if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote((string) $word, '/') . '(?![\p{L}\p{N}])/iu', (string) $c['body'])) {
                        $hit = true;
                        break;
                    }
                }
                if (!$hit) {
                    continue;
                }
                $sent = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE user_id = :uid AND auto_reply = 1 AND replied_at > :since');
                $sent->execute(['uid' => $uid, 'since' => gmdate('Y-m-d H:i:s', time() - 86400)]);
                if ((int) $sent->fetchColumn() >= self::AUTO_REPLIES_PER_DAY) {
                    return;
                }
                $text = (string) $rule['config']['text'];
                Community::deliver($channel, ['external_id' => $c['external_id'], 'author_name' => $c['author_name'], 'provider' => $channel['provider']], $text);
                $pdo->prepare("UPDATE comments SET state = 'replied', reply_body = :b, replied_at = :now, auto_reply = 1 WHERE id = :id")->execute(['b' => $text, 'now' => Database::now(), 'id' => $commentId]);
                $pdo->prepare('UPDATE automations SET made = made + 1, last_run_at = :now, last_result = :r WHERE id = :id')
                    ->execute(['now' => Database::now(), 'r' => gmdate('j M') . ': replied to ' . mb_substr((string) $c['author_name'], 0, 60) . '.', 'id' => (int) $rule['id']]);
                return;
            }
        } catch (Throwable $e) {
            error_log(sprintf('[%s] auto-reply comment %d: %s', gmdate('c'), $commentId, $e->getMessage()));
        }
    }

    // Recurring posts

    /** After a repeating post is published, schedule its next occurrence at the same local time. */
    public static function repeatNext(int $postId): void
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = :id');
            $stmt->execute(['id' => $postId]);
            $p = $stmt->fetch();
            $step = self::REPEAT_STEP[$p['repeat_rule'] ?? ''] ?? null;
            if (!$p || $step === null || !$p['planned_at']) {
                return;
            }
            $tz = self::timezone((int) $p['user_id']);
            $next = Workspace::local((string) $p['planned_at'], $tz)->modify($step);
            $now = new DateTimeImmutable('now', $tz);
            while ($next <= $now) {
                $next = $next->modify($step);
            }
            $stamp = Database::now();
            $pdo->prepare('INSERT INTO posts (user_id, channel, channel_id, body, title, meta, tags, media_id, extra_media, first_comment, status, planned_at, repeat_rule, created_by, created_at, updated_at)
                VALUES (:uid, :ch, :cid, :body, :title, :meta, :tags, :media, :extra, :first, :status, :at, :rule, :by, :now, :now2)')->execute([
                'uid' => (int) $p['user_id'], 'ch' => $p['channel'], 'cid' => $p['channel_id'], 'body' => $p['body'], 'title' => $p['title'], 'meta' => $p['meta'], 'tags' => $p['tags'],
                'media' => $p['media_id'], 'extra' => $p['extra_media'], 'first' => $p['first_comment'], 'status' => 'planned',
                'at' => $next->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'rule' => $p['repeat_rule'], 'by' => $p['created_by'], 'now' => $stamp, 'now2' => $stamp,
            ]);
            // Only the newest copy carries the rule, so one post never spawns two series.
            $pdo->prepare('UPDATE posts SET repeat_rule = NULL WHERE id = :id')->execute(['id' => $postId]);
        } catch (Throwable $e) {
            error_log(sprintf('[%s] repeat post %d: %s', gmdate('c'), $postId, $e->getMessage()));
        }
    }

    // Hourly housekeeping: empty-queue alerts and the Monday summary

    public static function housekeeping(): void
    {
        $stamp = BASE_PATH . '/storage/logs/automations.stamp';
        if (is_file($stamp) && filemtime($stamp) > time() - 55 * 60) {
            return;
        }
        @touch($stamp);
        try {
            self::emptyQueues();
            self::weeklySummaries();
        } catch (Throwable $e) {
            error_log(sprintf('[%s] automations housekeeping: %s', gmdate('c'), $e->getMessage()));
        }
    }

    /** Tell people when a channel that was posting has nothing left to send. Once a week per channel at most. */
    private static function emptyQueues(): void
    {
        $now = Database::now();
        $stmt = Database::connection()->prepare("SELECT c.id, c.user_id, c.name FROM channels c WHERE c.paused_at IS NULL
            AND EXISTS (SELECT 1 FROM posts p WHERE p.channel_id = c.id AND p.status = 'sent' AND p.sent_at > :recent)
            AND NOT EXISTS (SELECT 1 FROM posts q WHERE q.channel_id = c.id AND q.status IN ('planned', 'review', 'publishing') AND q.planned_at > :now)
            AND NOT EXISTS (SELECT 1 FROM posts e WHERE e.channel_id = c.id AND e.evergreen = 1 AND c.recycle_days > 0) LIMIT 200");
        $stmt->execute(['recent' => gmdate('Y-m-d H:i:s', time() - 14 * 86400), 'now' => $now]);
        foreach ($stmt->fetchAll() as $c) {
            Notify::send((int) $c['user_id'], 'queue_low', $c['name'] . ' has nothing scheduled', 'The queue is empty, so this channel goes quiet. Add posts, mark some as evergreen, or let an automation keep it filled.', '/app/channels/' . (int) $c['id'], Notify::team((int) $c['user_id'], 'editor', (int) $c['id']), 168);
        }
    }

    /** Monday morning (the person's own time): last week's numbers and what's coming, for their workspaces. */
    private static function weeklySummaries(): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT u.id, u.timezone FROM users u WHERE u.parent_id IS NULL AND (u.digest_sent_at IS NULL OR u.digest_sent_at < :ago)
            AND EXISTS (SELECT 1 FROM channels c JOIN users w ON w.id = c.user_id WHERE w.id = u.id OR w.parent_id = u.id) LIMIT 300');
        $stmt->execute(['ago' => gmdate('Y-m-d H:i:s', time() - 6 * 86400)]);
        $count = $pdo->prepare("SELECT
                SUM(CASE WHEN status = 'sent' AND sent_at > :week THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN status IN ('planned', 'publishing') AND planned_at BETWEEN :now AND :next THEN 1 ELSE 0 END) AS coming,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
                SUM(CASE WHEN status = 'review' THEN 1 ELSE 0 END) AS review
            FROM posts WHERE user_id = :uid");
        $open = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE user_id = :uid AND state = 'open'");
        $spaces = $pdo->prepare('SELECT id, display_name, parent_id FROM users WHERE id = :id OR parent_id = :id2 ORDER BY parent_id IS NOT NULL, display_name');
        foreach ($stmt->fetchAll() as $u) {
            $local = new DateTimeImmutable('now', Workspace::timezone($u));
            if ((int) $local->format('N') !== 1 || (int) $local->format('G') < 8) {
                continue;
            }
            $pdo->prepare('UPDATE users SET digest_sent_at = :now WHERE id = :id')->execute(['now' => Database::now(), 'id' => (int) $u['id']]);
            $spaces->execute(['id' => (int) $u['id'], 'id2' => (int) $u['id']]);
            $lines = [];
            foreach ($spaces->fetchAll() as $w) {
                $count->execute(['week' => gmdate('Y-m-d H:i:s', time() - 7 * 86400), 'now' => Database::now(), 'next' => gmdate('Y-m-d H:i:s', time() + 7 * 86400), 'uid' => (int) $w['id']]);
                $n = $count->fetch() ?: [];
                $open->execute(['uid' => (int) $w['id']]);
                $waiting = (int) $open->fetchColumn();
                if ((int) ($n['sent'] ?? 0) + (int) ($n['coming'] ?? 0) + (int) ($n['failed'] ?? 0) + (int) ($n['review'] ?? 0) + $waiting === 0) {
                    continue;
                }
                $parts = [(int) $n['sent'] . ' published last week', (int) $n['coming'] . ' scheduled this week'];
                foreach (['failed' => 'failed', 'review' => 'waiting for approval'] as $key => $label) {
                    if ((int) $n[$key] > 0) {
                        $parts[] = (int) $n[$key] . ' ' . $label;
                    }
                }
                if ($waiting > 0) {
                    $parts[] = $waiting . ' unanswered ' . ($waiting === 1 ? 'comment' : 'comments');
                }
                $lines[] = ($w['parent_id'] !== null ? $w['display_name'] . ': ' : '') . implode(', ', $parts) . '.';
            }
            if ($lines) {
                Notify::send((int) $u['id'], 'weekly_digest', 'Your week on atwebtech', implode(' ', $lines), '/app/insights', [(int) $u['id']]);
            }
        }
    }

    private static function timezone(int $uid): DateTimeZone
    {
        $stmt = Database::connection()->prepare('SELECT timezone FROM users WHERE id = :id');
        $stmt->execute(['id' => $uid]);
        return Workspace::timezone(['timezone' => (string) $stmt->fetchColumn()]);
    }

    private static function done(string $location, string $message): never
    {
        flash('notice', $message);
        redirect($location);
    }
}

final class FeedProblem extends RuntimeException
{
}
