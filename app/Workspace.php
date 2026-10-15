<?php

declare(strict_types=1);

/** Reads for the workspace pages: ideas board, posts, templates and the queue. */
final class Workspace
{
    public const DEFAULT_CHANNELS = ['linkedin', 'facebook', 'tiktok'];
    public const DEFAULT_GROUPS = ['To Do', 'In Progress', 'Done'];

    /** Render a workspace page for a logged-in user who finished onboarding. */
    public static function render(string $section, string $body, string $title, callable $vars): void
    {
        $user = Auth::require();
        $onboarding = Auth::onboarding((int) $user['id']);
        if ($onboarding['completed_at'] === null) {
            redirect('/welcome');
        }
        self::seed($user);
        View::render('app-section', ['user' => $user, 'onboarding' => $onboarding, 'section' => $section, 'body' => $body] + $vars($user, $onboarding), [
            'title' => $title . ' | atwebtech', 'description' => 'Your atwebtech workspace.', 'layout' => 'app', 'noindex' => true,
        ]);
    }

    /** Whitelisted query value. */
    public static function pick(string $key, array $allowed): string
    {
        $value = (string) ($_GET[$key] ?? '');
        return in_array($value, $allowed, true) ? $value : $allowed[0];
    }

    public static function timezone(array $user): DateTimeZone
    {
        $name = (string) ($user['timezone'] ?? '');
        return in_array($name, DateTimeZone::listIdentifiers(), true) ? new DateTimeZone($name) : new DateTimeZone('UTC');
    }

    /** City part of a time zone name, for the queue's time zone button. */
    public static function timezoneLabel(DateTimeZone $tz): string
    {
        $name = $tz->getName();
        $city = str_contains($name, '/') ? substr($name, strrpos($name, '/') + 1) : $name;
        return str_replace('_', ' ', $city);
    }

    /** Channels the user picked during onboarding, or a sensible default. */
    public static function channels(array $onboarding): array
    {
        $known = array_column(integrations(), null, 'slug');
        $picked = array_values(array_filter(
            explode(',', (string) ($onboarding['focus_channels'] ?? '')),
            static fn (string $slug): bool => isset($known[$slug])
        ));
        return $picked ?: self::DEFAULT_CHANNELS;
    }

    /** First visit: create the default columns and one welcome card. */
    public static function seed(array $user): void
    {
        if (!empty($user['workspace_seeded_at'])) {
            return;
        }
        $pdo = Database::connection();
        $uid = (int) $user['id'];
        $now = Database::now();
        $pdo->beginTransaction();
        try {
            $claim = $pdo->prepare('UPDATE users SET workspace_seeded_at = :now WHERE id = :id AND workspace_seeded_at IS NULL');
            $claim->execute(['now' => $now, 'id' => $uid]);
            if ($claim->rowCount() === 1) {
                $group = $pdo->prepare('INSERT INTO idea_groups (user_id, name, position, created_at, updated_at) VALUES (:uid, :name, :pos, :created, :updated)');
                foreach (self::DEFAULT_GROUPS as $i => $name) {
                    $group->execute(['uid' => $uid, 'name' => $name, 'pos' => $i + 1, 'created' => $now, 'updated' => $now]);
                }
                $pdo->prepare('INSERT INTO ideas (user_id, group_id, title, body, tags, position, created_at, updated_at) VALUES (:uid, NULL, :title, :body, :tags, 0, :created, :updated)')
                    ->execute([
                        'uid' => $uid,
                        'title' => 'Plan your content here ✍️',
                        'body' => "Save ideas as soon as you have them, then move them across the board as they take shape. Open a card to edit it, or turn it into a post when it's ready.",
                        'tags' => 'welcome',
                        'created' => $now,
                        'updated' => $now,
                    ]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function groups(int $uid): array
    {
        $stmt = Database::connection()->prepare('SELECT id, name, position FROM idea_groups WHERE user_id = :uid ORDER BY position, id');
        $stmt->execute(['uid' => $uid]);
        return $stmt->fetchAll();
    }

    public static function group(int $uid, int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, name, position FROM idea_groups WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $id, 'uid' => $uid]);
        return $stmt->fetch() ?: null;
    }

    /** Board columns: Unassigned first, then the user's groups, each with its ideas. */
    public static function board(int $uid): array
    {
        $columns = [0 => ['id' => 0, 'name' => 'Unassigned', 'ideas' => []]];
        foreach (self::groups($uid) as $group) {
            $columns[(int) $group['id']] = ['id' => (int) $group['id'], 'name' => $group['name'], 'ideas' => []];
        }
        $tags = [];
        $stmt = Database::connection()->prepare('SELECT id, group_id, title, body, tags, updated_at FROM ideas WHERE user_id = :uid ORDER BY position, id DESC');
        $stmt->execute(['uid' => $uid]);
        foreach ($stmt->fetchAll() as $idea) {
            $idea['tag_list'] = self::tagList($idea['tags']);
            foreach ($idea['tag_list'] as $tag) {
                $tags[$tag] = true;
            }
            $columns[(int) ($idea['group_id'] ?? 0)]['ideas'][] = $idea;
        }
        $tags = array_keys($tags);
        sort($tags);
        return ['columns' => array_values($columns), 'tags' => $tags];
    }

    public static function idea(int $uid, int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, group_id, title, body, tags FROM ideas WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $id, 'uid' => $uid]);
        return $stmt->fetch() ?: null;
    }

    public static function tagList(?string $tags): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $tags)), 'strlen'));
    }

    private const POST_COLUMNS = 'id, channel, channel_id, body, title, meta, tags, status, planned_at, sent_at, external_url, error, attempts, idea_id, media_id, updated_at';

    public static function post(int $uid, int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT ' . self::POST_COLUMNS . ' FROM posts WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $id, 'uid' => $uid]);
        return $stmt->fetch() ?: null;
    }

    /** Posts in one or more statuses, optionally for a single connected channel. */
    private static function posts(int $uid, array $statuses, ?int $channelId, string $order, ?string $fromUtc = null, ?string $toUtc = null): array
    {
        $params = ['uid' => $uid];
        $in = [];
        foreach (array_values($statuses) as $i => $status) {
            $in[] = ':s' . $i;
            $params['s' . $i] = $status;
        }
        $sql = 'SELECT ' . self::POST_COLUMNS . ' FROM posts WHERE user_id = :uid AND status IN (' . implode(', ', $in) . ')';
        if ($channelId !== null) {
            $sql .= ' AND channel_id = :cid';
            $params['cid'] = $channelId;
        }
        if ($fromUtc !== null) {
            $sql .= ' AND planned_at >= :from';
            $params['from'] = $fromUtc;
        }
        if ($toUtc !== null) {
            $sql .= ' AND planned_at < :to';
            $params['to'] = $toUtc;
        }
        $stmt = Database::connection()->prepare($sql . ' ORDER BY ' . $order);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function drafts(int $uid, ?int $channelId = null): array
    {
        return self::posts($uid, ['draft'], $channelId, 'updated_at DESC, id DESC');
    }

    public static function sent(int $uid, ?int $channelId = null): array
    {
        return self::posts($uid, ['sent'], $channelId, 'sent_at DESC, id DESC');
    }

    /** Posts that need attention: publishing failed for good. */
    public static function failed(int $uid, ?int $channelId = null): array
    {
        return self::posts($uid, ['failed'], $channelId, 'planned_at, id');
    }

    /** Planned (or publishing) posts between two UTC datetimes (either may be null). */
    public static function planned(int $uid, ?string $fromUtc = null, ?string $toUtc = null, ?int $channelId = null): array
    {
        return self::posts($uid, ['planned', 'publishing'], $channelId, 'planned_at, id', $fromUtc, $toUtc);
    }

    public static function counts(int $uid, ?int $channelId = null): array
    {
        $sql = "SELECT
                SUM(CASE WHEN status IN ('planned', 'publishing', 'failed') THEN 1 ELSE 0 END) AS queue,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS drafts,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN status = 'sent' AND sent_at >= :week THEN 1 ELSE 0 END) AS sent_week
             FROM posts WHERE user_id = :uid";
        $params = ['uid' => $uid, 'week' => gmdate('Y-m-d H:i:s', strtotime('monday this week 00:00 UTC'))];
        if ($channelId !== null) {
            $sql .= ' AND channel_id = :cid';
            $params['cid'] = $channelId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];
        return ['queue' => (int) ($row['queue'] ?? 0), 'drafts' => (int) ($row['drafts'] ?? 0), 'sent' => (int) ($row['sent'] ?? 0), 'sent_week' => (int) ($row['sent_week'] ?? 0)];
    }

    /** YouTube settings stored with a post, with YouTube's own defaults filled in. */
    public static function youtubeMeta(array $post): array
    {
        $meta = json_decode((string) ($post['meta'] ?? ''), true) ?: [];
        $yt = data('youtube');
        return [
            'category' => isset($yt['categories'][$meta['category'] ?? '']) ? (string) $meta['category'] : $yt['default_category'],
            'visibility' => isset($yt['visibility'][$meta['visibility'] ?? '']) ? $meta['visibility'] : 'public',
            'license' => isset($yt['licenses'][$meta['license'] ?? '']) ? $meta['license'] : 'youtube',
            'notify' => (bool) ($meta['notify'] ?? true),
            'embeddable' => (bool) ($meta['embeddable'] ?? true),
            'kids' => (bool) ($meta['kids'] ?? false),
            'synthetic' => (bool) ($meta['synthetic'] ?? false),
        ];
    }

    /** Composer prefill for an existing post. */
    public static function composeData(array $post, DateTimeZone $tz, int $uid): array
    {
        $at = $post['planned_at'] ? self::local($post['planned_at'], $tz) : null;
        $media = $post['media_id'] ? Media::describe(Media::ready($uid, (int) $post['media_id'])) : null;
        return [
            'id' => (int) $post['id'],
            'targets' => [$post['channel_id'] ? 'c:' . (int) $post['channel_id'] : 'n:' . $post['channel']],
            'body' => (string) $post['body'],
            'title' => (string) ($post['title'] ?? ''),
            'tags' => (string) ($post['tags'] ?? ''),
            'meta' => self::youtubeMeta($post),
            'media' => $media,
            'first_comment' => (string) ($post['first_comment'] ?? ''),
            'date' => $at ? $at->format('Y-m-d') : '',
            'time' => $at ? $at->format('H:i') : '',
        ];
    }

    /**
     * Sent-post counts we actually have for Insights. Network analytics (followers, reactions, impressions) are not fetched.
     *
     * @return array{range:string,label:string,from:DateTimeImmutable,to:DateTimeImmutable,prev_from:DateTimeImmutable,prev_to:DateTimeImmutable,sent:int,prev_sent:int,drafts:int,posts:list<array>,series:list<array{day:string,n:int}>}
     */
    public static function insights(array $user, string $range, ?int $channelId = null): array
    {
        $tz = self::timezone($user);
        $now = new DateTimeImmutable('now', $tz);
        $labels = ['7d' => '7 Days', '30d' => '30 Days', 'mtd' => 'Month to Date', 'last-month' => 'Last Month', 'custom' => 'Custom'];
        [$from, $to] = match ($range) {
            '7d' => [$now->modify('-6 days')->setTime(0, 0), $now],
            'mtd' => [$now->modify('first day of this month')->setTime(0, 0), $now],
            'last-month' => [
                $now->modify('first day of last month')->setTime(0, 0),
                $now->modify('last day of last month')->setTime(23, 59),
            ],
            'custom' => self::customRange($tz, $now),
            default => [$now->modify('-29 days')->setTime(0, 0), $now],
        };
        $days = max(1, (int) $from->diff($to)->format('%a') + 1);
        $prevTo = $from->modify('-1 second');
        $prevFrom = $prevTo->modify('-' . ($days - 1) . ' days')->setTime(0, 0);
        $utc = new DateTimeZone('UTC');
        $fromUtc = $from->setTimezone($utc)->format('Y-m-d H:i:s');
        $toUtc = $to->setTimezone($utc)->format('Y-m-d H:i:s');
        $prevFromUtc = $prevFrom->setTimezone($utc)->format('Y-m-d H:i:s');
        $prevToUtc = $prevTo->setTimezone($utc)->format('Y-m-d H:i:s');

        $sql = 'SELECT * FROM posts WHERE user_id = :uid AND status = :sent AND sent_at BETWEEN :from AND :to';
        $params = ['uid' => (int) $user['id'], 'sent' => 'sent', 'from' => $fromUtc, 'to' => $toUtc];
        if ($channelId !== null) {
            $sql .= ' AND channel_id = :cid';
            $params['cid'] = $channelId;
        }
        $stmt = Database::connection()->prepare($sql . ' ORDER BY sent_at DESC, id DESC');
        $stmt->execute($params);
        $posts = $stmt->fetchAll();

        $prevSql = 'SELECT COUNT(*) FROM posts WHERE user_id = :uid AND status = :sent AND sent_at BETWEEN :from AND :to';
        $prevParams = ['uid' => (int) $user['id'], 'sent' => 'sent', 'from' => $prevFromUtc, 'to' => $prevToUtc];
        if ($channelId !== null) {
            $prevSql .= ' AND channel_id = :cid';
            $prevParams['cid'] = $channelId;
        }
        $prev = Database::connection()->prepare($prevSql);
        $prev->execute($prevParams);

        $series = [];
        $cursor = $from->setTime(0, 0);
        $end = $to->setTime(0, 0);
        while ($cursor <= $end) {
            $series[$cursor->format('Y-m-d')] = ['day' => $cursor->format('j M'), 'n' => 0];
            $cursor = $cursor->modify('+1 day');
        }
        foreach ($posts as $post) {
            $day = self::local((string) $post['sent_at'], $tz)->format('Y-m-d');
            if (isset($series[$day])) {
                $series[$day]['n']++;
            }
        }

        $counts = self::counts((int) $user['id'], $channelId);
        return [
            'range' => $range,
            'label' => $labels[$range] ?? '30 Days',
            'from' => $from,
            'to' => $to,
            'prev_from' => $prevFrom,
            'prev_to' => $prevTo,
            'sent' => count($posts),
            'prev_sent' => (int) $prev->fetchColumn(),
            'drafts' => $counts['drafts'],
            'posts' => $posts,
            'series' => array_values($series),
        ];
    }

    /** @return array{0:DateTimeImmutable,1:DateTimeImmutable} */
    private static function customRange(DateTimeZone $tz, DateTimeImmutable $now): array
    {
        $from = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_GET['from'] ?? ''), $tz);
        $to = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_GET['to'] ?? ''), $tz);
        if ($from === false || $to === false || $from > $to) {
            return [$now->modify('-29 days')->setTime(0, 0), $now];
        }
        if ($to > $now) {
            $to = $now;
        }
        if ($from < $now->modify('-2 years')) {
            $from = $now->modify('-2 years')->setTime(0, 0);
        }
        return [$from->setTime(0, 0), $to->setTime(23, 59)];
    }

    public static function personalTemplates(int $uid): array
    {
        $stmt = Database::connection()->prepare('SELECT id, title, prompt FROM post_templates WHERE user_id = :uid ORDER BY id DESC');
        $stmt->execute(['uid' => $uid]);
        return $stmt->fetchAll();
    }

    public static function local(string $utc, DateTimeZone $tz): DateTimeImmutable
    {
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone($tz);
    }

    public static function dayLabel(DateTimeImmutable $day, DateTimeImmutable $today): string
    {
        $diff = (int) $today->setTime(0, 0)->diff($day->setTime(0, 0))->format('%r%a');
        return match ($diff) {
            0 => 'Today, ' . $day->format('j F'),
            1 => 'Tomorrow, ' . $day->format('j F'),
            -1 => 'Yesterday, ' . $day->format('j F'),
            default => $day->format('l, j F'),
        };
    }

    /**
     * Queue list: suggested times for the next $days days plus every planned post,
     * grouped by local day. Posts whose time has passed are listed separately.
     */
    public static function queue(array $user, array $onboarding, int $days, ?int $channelId = null, ?DateTimeImmutable $from = null): array
    {
        $connected = Channels::forUser((int) $user['id']);
        if ($channelId !== null) {
            $connected = array_values(array_filter($connected, static fn (array $c): bool => (int) $c['id'] === $channelId));
        }
        $tz = self::timezone($user);
        $utc = new DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $tz);
        $channels = self::channels($onboarding);
        $times = data('workspace')['queue_times'];
        $start = $from ? $from->setTimezone($tz)->setTime(0, 0) : $now->setTime(0, 0);
        $end = $start->modify("+{$days} day");

        $groups = [];
        $add = static function (DateTimeImmutable $at, array $item) use (&$groups, $now): void {
            $key = $at->format('Y-m-d');
            $groups[$key] ??= ['label' => self::dayLabel($at, $now), 'date' => $key, 'items' => []];
            $groups[$key]['items'][] = $item + ['at' => $at];
        };

        $uid = (int) $user['id'];
        $nowUtc = $now->setTimezone($utc)->format('Y-m-d H:i:s');
        $overdue = [];
        if ($from === null) {
            Publisher::kick();
            $overdue = array_map(
                static fn (array $post): array => $post + ['at' => self::local($post['planned_at'], $tz)],
                array_merge(self::failed($uid, $channelId), self::planned($uid, null, $nowUtc, $channelId))
            );
        }
        $rangeFrom = $from === null ? $nowUtc : max($nowUtc, $start->setTimezone($utc)->format('Y-m-d H:i:s'));
        $rangeTo = $channelId === null && $from === null ? null : $end->setTimezone($utc)->format('Y-m-d H:i:s');
        $upcoming = self::planned($uid, $rangeFrom, $rangeTo, $channelId);

        $filled = [];
        foreach ($upcoming as $post) {
            $at = self::local($post['planned_at'], $tz);
            $filled[($post['channel_id'] ? 'c' . $post['channel_id'] : $post['channel']) . '@' . $at->format('Y-m-d H:i')] = true;
            $add($at, ['type' => 'post', 'post' => $post]);
        }
        $slot = static function (DateTimeImmutable $at, string $provider, ?array $account) use (&$filled, $now, $add): void {
            $key = ($account ? 'c' . $account['id'] : $provider) . '@' . $at->format('Y-m-d H:i');
            if ($at > $now && !isset($filled[$key])) {
                $add($at, ['type' => 'slot', 'channel' => $provider, 'account' => $account]);
            }
        };
        for ($d = 0; $d < $days; $d++) {
            $day = $start->modify("+{$d} day");
            if ($connected) {
                foreach ($connected as $account) {
                    foreach ($account['times'] as $t) {
                        if ((int) $t['day'] === (int) $day->format('N')) {
                            [$h, $m] = array_map('intval', explode(':', $t['time']));
                            $slot($day->setTime($h, $m), $account['provider'], $account);
                        }
                    }
                }
                continue;
            }
            if ($channelId !== null) {
                continue;
            }
            foreach ($times as $i => $time) {
                [$h, $m] = array_map('intval', explode(':', $time));
                $slot($day->setTime($h, $m), $channels[($i + $d) % count($channels)], null);
            }
        }
        ksort($groups);
        foreach ($groups as &$group) {
            usort($group['items'], static fn (array $a, array $b): int => [$a['at'], $a['type'] === 'slot'] <=> [$b['at'], $b['type'] === 'slot']);
        }
        unset($group);

        return ['days' => array_values($groups), 'overdue' => $overdue, 'timezone' => $tz, 'now' => $now, 'connected' => $connected, 'next' => $end];
    }

    /** Week grid (Monday start) of planned posts. */
    public static function week(array $user, int $offset, ?int $channelId = null): array
    {
        $tz = self::timezone($user);
        $now = new DateTimeImmutable('now', $tz);
        $start = $now->setTime(0, 0)->modify('monday this week')->modify(($offset >= 0 ? '+' : '') . ($offset * 7) . ' days');
        $end = $start->modify('+7 days');
        $utc = new DateTimeZone('UTC');

        $days = [];
        for ($d = 0; $d < 7; $d++) {
            $day = $start->modify("+{$d} day");
            $days[$day->format('Y-m-d')] = ['date' => $day, 'posts' => [], 'today' => $day->format('Y-m-d') === $now->format('Y-m-d'), 'past' => $day->setTime(23, 59) < $now];
        }
        $posts = self::planned((int) $user['id'], $start->setTimezone($utc)->format('Y-m-d H:i:s'), $end->setTimezone($utc)->format('Y-m-d H:i:s'), $channelId);
        foreach ($posts as $post) {
            $at = self::local($post['planned_at'], $tz);
            $days[$at->format('Y-m-d')]['posts'][] = $post + ['at' => $at];
        }
        return ['days' => array_values($days), 'start' => $start, 'end' => $end->modify('-1 day'), 'now' => $now, 'timezone' => $tz];
    }
}
