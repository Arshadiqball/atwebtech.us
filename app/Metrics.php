<?php

declare(strict_types=1);

/**
 * Collects follower counts and per-post stats from each network's API, and sums them for Insights.
 * Runs from bin/publish.php (every minute) in small batches. A null metric means the network didn't report it.
 */
final class Metrics
{
    public const FIELDS = ['impressions', 'reach', 'reactions', 'comments', 'shares', 'saves', 'video_views', 'unique_viewers', 'watch_seconds'];

    /** What each network can report. LinkedIn personal profiles have no stats API for apps. */
    public const SUPPORT = [
        'facebook' => ['followers', 'impressions', 'reach', 'reactions', 'comments', 'shares', 'video_views', 'unique_viewers', 'watch_seconds'],
        'instagram' => ['followers', 'impressions', 'reach', 'reactions', 'comments', 'shares', 'saves', 'video_views', 'watch_seconds'],
        'threads' => ['followers', 'impressions', 'reactions', 'comments', 'shares'],
        'youtube' => ['followers', 'reactions', 'comments', 'shares', 'video_views', 'watch_seconds'],
        'pinterest' => ['followers', 'impressions', 'reactions', 'comments', 'saves', 'video_views'],
        'bluesky' => ['followers', 'reactions', 'comments', 'shares'],
        'mastodon' => ['followers', 'reactions', 'comments', 'shares'],
        'linkedin' => [],
    ];

    private const GRAPH = 'https://graph.facebook.com/v23.0';
    private const IG = 'https://graph.instagram.com/v23.0';
    private const THREADS = 'https://graph.threads.net/v1.0';
    private const LOOKBACK_DAYS = 90;
    /** A soft note for the current channel, such as "only likes and comments until insights are allowed". */
    private static ?string $note = null;

    /**
     * Refresh the channels and posts that are due. Stops after $seconds so the minute cron stays short.
     * @return array{channels:int, posts:int, errors:int}
     */
    public static function run(int $seconds = 40): array
    {
        $started = time();
        $pdo = Database::connection();
        $out = ['channels' => 0, 'posts' => 0, 'errors' => 0];
        $channels = $pdo->prepare('SELECT id, user_id FROM channels WHERE provider IN (' . self::inList() . ') AND (stats_checked_at IS NULL OR stats_checked_at < :due) ORDER BY stats_checked_at IS NOT NULL, stats_checked_at LIMIT 10');
        $channels->execute(['due' => gmdate('Y-m-d H:i:s', time() - 3 * 3600)]);
        foreach ($channels->fetchAll() as $row) {
            if (time() - $started > $seconds) {
                return $out;
            }
            $channel = Channels::find((int) $row['user_id'], (int) $row['id']);
            if ($channel === null) {
                continue;
            }
            $result = self::refreshChannel($channel);
            $out['channels']++;
            $out['posts'] += $result['posts'];
            $out['errors'] += $result['error'] !== null ? 1 : 0;
        }
        return $out;
    }

    /** Refresh one channel now: followers plus its recent posts. */
    public static function refreshChannel(array $channel): array
    {
        $pdo = Database::connection();
        $error = null;
        $posts = 0;
        self::$note = null;
        try {
            $token = Channels::accessToken($channel);
            $followers = self::followers($channel, $token);
            if ($followers !== null) {
                self::saveFollowers((int) $channel['id'], $followers);
            }
            $stmt = $pdo->prepare("SELECT p.*, m.kind AS media_kind FROM posts p LEFT JOIN media m ON m.id = p.media_id
                WHERE p.channel_id = :cid AND p.status = 'sent' AND p.external_id IS NOT NULL AND p.sent_at >= :since
                ORDER BY p.sent_at DESC LIMIT 100");
            $stmt->execute(['cid' => (int) $channel['id'], 'since' => gmdate('Y-m-d H:i:s', time() - self::LOOKBACK_DAYS * 86400)]);
            $rows = $stmt->fetchAll();
            foreach (self::fetchPosts($channel, $token, $rows) as $postId => $metrics) {
                self::savePost($channel, (int) $postId, $metrics);
                $posts++;
            }
        } catch (Throwable $e) {
            $error = self::explain($channel['provider'], $e);
            error_log(sprintf('[%s] metrics channel %d: %s', gmdate('c'), (int) $channel['id'], $e->getMessage()));
        }
        $error ??= self::$note;
        $pdo->prepare('UPDATE channels SET stats_checked_at = :now, stats_error = :err WHERE id = :id')
            ->execute(['now' => Database::now(), 'err' => $error, 'id' => (int) $channel['id']]);
        return ['posts' => $posts, 'error' => $error];
    }

    /**
     * Totals for Insights. Post metrics cover posts sent in the window; followers are the latest count
     * and the change since the window started.
     */
    public static function summary(int $uid, ?int $channelId, string $fromUtc, string $toUtc, string $prevFromUtc, string $prevToUtc): array
    {
        $pdo = Database::connection();
        $channels = array_filter(Channels::forUser($uid), static fn (array $c): bool => $channelId === null || (int) $c['id'] === $channelId);
        $sum = static function (string $from, string $to) use ($pdo, $uid, $channelId): array {
            $cols = implode(', ', array_map(static fn (string $f): string => "SUM(m.$f) AS $f, COUNT(m.$f) AS n_$f", self::FIELDS));
            $sql = "SELECT $cols FROM post_metrics m JOIN posts p ON p.id = m.post_id WHERE p.user_id = :uid AND p.status = 'sent' AND p.sent_at BETWEEN :from AND :to" . Team::channelSql($uid, 'p.channel_id');
            $params = ['uid' => $uid, 'from' => $from, 'to' => $to];
            if ($channelId !== null) {
                $sql .= ' AND p.channel_id = :cid';
                $params['cid'] = $channelId;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch() ?: [];
            $out = [];
            foreach (self::FIELDS as $f) {
                $out[$f] = (int) ($row['n_' . $f] ?? 0) > 0 ? (int) $row[$f] : null;
            }
            return $out;
        };
        $now = $sum($fromUtc, $toUtc);
        $prev = $sum($prevFromUtc, $prevToUtc);

        $followers = null;
        $followersStart = null;
        $latest = $pdo->prepare('SELECT followers FROM channel_followers WHERE channel_id = :cid AND day <= :day ORDER BY day DESC LIMIT 1');
        $first = $pdo->prepare('SELECT followers FROM channel_followers WHERE channel_id = :cid AND day >= :day ORDER BY day ASC LIMIT 1');
        foreach ($channels as $c) {
            $latest->execute(['cid' => (int) $c['id'], 'day' => substr($toUtc, 0, 10)]);
            $value = $latest->fetchColumn();
            if ($value === false) {
                continue;
            }
            $followers = ($followers ?? 0) + (int) $value;
            $latest->execute(['cid' => (int) $c['id'], 'day' => substr($fromUtc, 0, 10)]);
            $start = $latest->fetchColumn();
            if ($start === false) {
                $first->execute(['cid' => (int) $c['id'], 'day' => substr($fromUtc, 0, 10)]);
                $start = $first->fetchColumn();
            }
            $followersStart = ($followersStart ?? 0) + (int) ($start === false ? $value : $start);
        }

        $engagement = static function (array $m): ?float {
            $acts = ($m['reactions'] ?? 0) + ($m['comments'] ?? 0) + ($m['shares'] ?? 0) + ($m['saves'] ?? 0);
            $seen = $m['impressions'] ?? $m['video_views'] ?? null;
            return $seen ? round($acts / $seen * 100, 2) : null;
        };

        $networks = [];
        $issues = [];
        foreach ($channels as $c) {
            $networks[$c['provider']] = true;
            if (!empty($c['stats_error'])) {
                $issues[] = ['channel' => $c, 'message' => (string) $c['stats_error']];
            }
        }
        return [
            'now' => $now + ['followers' => $followers, 'eng_rate' => $engagement($now)],
            'prev' => $prev + ['followers' => $followersStart, 'eng_rate' => $engagement($prev)],
            'networks' => array_keys($networks),
            'issues' => $issues,
            'checked' => self::lastChecked($channels),
        ];
    }

    /**
     * Hashtags used in posts with stats over the last 90 days, ranked by engagement against each channel's average.
     * @return list<array{tag:string, posts:int, lift:float, reactions:int, comments:int}>
     */
    public static function hashtags(int $uid, ?int $channelId): array
    {
        $sql = "SELECT p.channel_id, p.body, COALESCE(m.reactions, 0) AS r, COALESCE(m.comments, 0) AS c, COALESCE(m.shares, 0) + COALESCE(m.saves, 0) AS s
            FROM post_metrics m JOIN posts p ON p.id = m.post_id
            WHERE p.user_id = :uid AND p.status = 'sent' AND p.sent_at >= :since AND m.error IS NULL" . Team::channelSql($uid, 'p.channel_id');
        $params = ['uid' => $uid, 'since' => gmdate('Y-m-d H:i:s', time() - self::LOOKBACK_DAYS * 86400)];
        if ($channelId !== null) {
            $sql .= ' AND p.channel_id = :cid';
            $params['cid'] = $channelId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $avg = [];
        foreach ($rows as $r) {
            $avg[$r['channel_id']][] = (int) $r['r'] + (int) $r['c'] + (int) $r['s'];
        }
        $avg = array_map(static fn (array $v): float => max(1.0, array_sum($v) / count($v)), $avg);
        $tags = [];
        foreach ($rows as $r) {
            if (!preg_match_all('/(?<![\p{L}\p{N}_&])#([\p{L}\p{N}_]{2,50})/u', (string) $r['body'], $m)) {
                continue;
            }
            $acts = (int) $r['r'] + (int) $r['c'] + (int) $r['s'];
            foreach (array_unique(array_map('mb_strtolower', $m[1])) as $tag) {
                $t = &$tags[$tag];
                $t ??= ['tag' => '#' . $tag, 'posts' => 0, 'lift' => 0.0, 'reactions' => 0, 'comments' => 0];
                $t['posts']++;
                $t['lift'] += $acts / $avg[$r['channel_id']];
                $t['reactions'] += (int) $r['r'];
                $t['comments'] += (int) $r['c'];
                unset($t);
            }
        }
        $tags = array_values(array_filter($tags, static fn (array $t): bool => $t['posts'] >= 2));
        foreach ($tags as &$t) {
            $t['lift'] = $t['lift'] / $t['posts'];
        }
        unset($t);
        usort($tags, static fn (array $a, array $b): int => [$b['lift'], $b['posts']] <=> [$a['lift'], $a['posts']]);
        return array_slice($tags, 0, 10);
    }

    /** Posts with stats needed before best times are shown, and per time slot. */
    public const BEST_MIN_POSTS = 6;
    private const BEST_MIN_SLOT = 2;

    /**
     * When your posts get the most engagement, from your own stats over the last 90 days.
     * Each post scores its reactions, comments, shares and saves against its channel's average, so channels of
     * different sizes can be compared. Heatmap: day (1 = Monday) x 4-hour block, in the user's time zone.
     * @return array{posts:int, ready:bool, heat:array, max:float, top:array}
     */
    public static function bestTimes(int $uid, ?int $channelId, DateTimeZone $tz): array
    {
        $sql = "SELECT p.channel_id, p.sent_at, COALESCE(m.reactions, 0) + COALESCE(m.comments, 0) + COALESCE(m.shares, 0) + COALESCE(m.saves, 0) AS acts
            FROM post_metrics m JOIN posts p ON p.id = m.post_id
            WHERE p.user_id = :uid AND p.status = 'sent' AND p.sent_at >= :since AND m.error IS NULL
            AND (m.reactions IS NOT NULL OR m.comments IS NOT NULL OR m.shares IS NOT NULL OR m.saves IS NOT NULL)" . Team::channelSql($uid, 'p.channel_id');
        $params = ['uid' => $uid, 'since' => gmdate('Y-m-d H:i:s', time() - self::LOOKBACK_DAYS * 86400)];
        if ($channelId !== null) {
            $sql .= ' AND p.channel_id = :cid';
            $params['cid'] = $channelId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $avg = [];
        foreach ($rows as $r) {
            $avg[$r['channel_id']][] = (int) $r['acts'];
        }
        $avg = array_map(static fn (array $v): float => max(1.0, array_sum($v) / count($v)), $avg);

        $blocks = [];
        $hours = [];
        foreach ($rows as $r) {
            $at = (new DateTimeImmutable($r['sent_at'], new DateTimeZone('UTC')))->setTimezone($tz);
            $day = (int) $at->format('N');
            $hour = (int) $at->format('G');
            $lift = (int) $r['acts'] / $avg[$r['channel_id']];
            $blocks[$day][intdiv($hour, 4)][] = $lift;
            $hours[$day . ' ' . $hour][] = $lift;
        }
        $heat = [];
        $max = 0.0;
        foreach ($blocks as $day => $byBlock) {
            foreach ($byBlock as $block => $lifts) {
                $score = array_sum($lifts) / count($lifts);
                $heat[$day][$block] = ['score' => $score, 'n' => count($lifts)];
                if (count($lifts) >= self::BEST_MIN_SLOT) {
                    $max = max($max, $score);
                }
            }
        }
        $top = [];
        foreach ($hours as $key => $lifts) {
            if (count($lifts) < self::BEST_MIN_SLOT) {
                continue;
            }
            [$day, $hour] = array_map('intval', explode(' ', $key));
            $top[] = ['day' => $day, 'hour' => $hour, 'lift' => array_sum($lifts) / count($lifts), 'n' => count($lifts)];
        }
        usort($top, static fn (array $a, array $b): int => [$b['lift'], $b['n']] <=> [$a['lift'], $a['n']]);
        $top = array_values(array_filter($top, static fn (array $t): bool => $t['lift'] >= 1.0));
        return ['posts' => count($rows), 'ready' => count($rows) >= self::BEST_MIN_POSTS && $top !== [], 'heat' => $heat, 'max' => $max, 'top' => array_slice($top, 0, 5)];
    }

    /** Stats for these posts, keyed by post id. */
    public static function forPosts(array $postIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $postIds)));
        if (!$ids) {
            return [];
        }
        $stmt = Database::connection()->prepare('SELECT * FROM post_metrics WHERE post_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $stmt->execute($ids);
        return array_column($stmt->fetchAll(), null, 'post_id');
    }

    /** Networks among $providers that report $metric. */
    public static function reporters(string $metric, array $providers): array
    {
        return array_values(array_filter($providers, static fn (string $p): bool => in_array($metric, self::SUPPORT[$p] ?? [], true)));
    }

    private static function lastChecked(array $channels): ?string
    {
        $times = array_filter(array_map(static fn (array $c): ?string => $c['stats_checked_at'] ?? null, $channels));
        return $times ? max($times) : null;
    }

    private static function inList(): string
    {
        return implode(', ', array_map(static fn (string $p): string => "'" . $p . "'", array_keys(array_filter(self::SUPPORT))));
    }

    private static function saveFollowers(int $channelId, int $followers): void
    {
        $pdo = Database::connection();
        $day = gmdate('Y-m-d');
        $pdo->prepare('DELETE FROM channel_followers WHERE channel_id = :cid AND day = :day')->execute(['cid' => $channelId, 'day' => $day]);
        $pdo->prepare('INSERT INTO channel_followers (channel_id, day, followers) VALUES (:cid, :day, :n)')->execute(['cid' => $channelId, 'day' => $day, 'n' => max(0, $followers)]);
    }

    private static function savePost(array $channel, int $postId, array $metrics): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM post_metrics WHERE post_id = :id')->execute(['id' => $postId]);
        $row = ['post_id' => $postId, 'user_id' => (int) $channel['user_id'], 'channel_id' => (int) $channel['id']];
        foreach (self::FIELDS as $f) {
            $row[$f] = isset($metrics[$f]) ? max(0, (int) $metrics[$f]) : null;
        }
        $row['fetched_at'] = Database::now();
        $row['error'] = isset($metrics['error']) ? mb_substr((string) $metrics['error'], 0, 300) : null;
        $pdo->prepare('INSERT INTO post_metrics (' . implode(', ', array_keys($row)) . ') VALUES (:' . implode(', :', array_keys($row)) . ')')->execute($row);
    }

    private static function followers(array $channel, string $token): ?int
    {
        $id = rawurlencode((string) $channel['external_id']);
        return match ($channel['provider']) {
            'facebook' => self::int(self::get(self::GRAPH . '/' . $id . '?' . http_build_query(['fields' => 'followers_count,fan_count', 'access_token' => $token]), null, true), ['followers_count'], ['fan_count']),
            'instagram' => self::int(self::get(self::IG . '/me?' . http_build_query(['fields' => 'followers_count']), $token), ['followers_count']),
            'threads' => self::threadsFollowers($id, $token),
            'youtube' => self::youtubeSubscribers($id, $token),
            'pinterest' => self::int(self::get('https://api.pinterest.com/v5/user_account', $token), ['follower_count']),
            'bluesky', 'mastodon' => OpenSocial::followers($channel, $token),
            default => null,
        };
    }

    private static function threadsFollowers(string $id, string $token): ?int
    {
        try {
            $data = self::get(self::THREADS . '/' . $id . '/threads_insights?' . http_build_query(['metric' => 'followers_count', 'access_token' => $token]));
        } catch (ChannelApiException $e) {
            if (in_array($e->status, [400, 403], true)) {
                throw new MetricsPermission('Threads needs the insights permission.');
            }
            throw $e;
        }
        foreach ($data['data'] ?? [] as $m) {
            if (($m['name'] ?? '') === 'followers_count') {
                return (int) ($m['total_value']['value'] ?? 0);
            }
        }
        return null;
    }

    private static function youtubeSubscribers(string $id, string $token): ?int
    {
        $data = self::get('https://www.googleapis.com/youtube/v3/channels?' . http_build_query(['part' => 'statistics', 'id' => rawurldecode($id)]), $token);
        $stats = $data['items'][0]['statistics'] ?? null;
        if (!is_array($stats) || !empty($stats['hiddenSubscriberCount'])) {
            return null;
        }
        return (int) ($stats['subscriberCount'] ?? 0);
    }

    /** @return array<int, array> post id => metrics */
    private static function fetchPosts(array $channel, string $token, array $rows): array
    {
        if (!$rows) {
            return [];
        }
        $out = [];
        if (in_array($channel['provider'], OpenSocial::PROVIDERS, true)) {
            return OpenSocial::postStats($channel, $token, $rows);
        }
        if ($channel['provider'] === 'youtube') {
            $analytics = true;
            foreach (array_chunk($rows, 50) as $chunk) {
                $byExt = array_column($chunk, 'id', 'external_id');
                $data = self::get('https://www.googleapis.com/youtube/v3/videos?' . http_build_query(['part' => 'statistics', 'id' => implode(',', array_keys($byExt))]), $token);
                foreach ($data['items'] ?? [] as $v) {
                    $s = $v['statistics'] ?? [];
                    if (!isset($byExt[$v['id'] ?? ''])) {
                        continue;
                    }
                    $m = ['video_views' => $s['viewCount'] ?? null, 'reactions' => $s['likeCount'] ?? null, 'comments' => $s['commentCount'] ?? null];
                    if ($analytics) {
                        try {
                            $r = self::get('https://youtubeanalytics.googleapis.com/v2/reports?' . http_build_query([
                                'ids' => 'channel==MINE', 'startDate' => '2005-01-01', 'endDate' => gmdate('Y-m-d'),
                                'metrics' => 'estimatedMinutesWatched,shares', 'filters' => 'video==' . $v['id'],
                            ]), $token);
                            $row = $r['rows'][0] ?? null;
                            if (is_array($row)) {
                                $m['watch_seconds'] = (int) round((float) $row[0] * 60);
                                $m['shares'] = (int) $row[1];
                            }
                        } catch (ChannelApiException) {
                            $analytics = false;
                            self::$note = 'YouTube shares views, likes and comments. To see watch time and shares, reconnect YouTube and allow YouTube Analytics.';
                        }
                    }
                    $out[(int) $byExt[$v['id']]] = $m;
                }
            }
            return $out;
        }
        foreach ($rows as $post) {
            try {
                $out[(int) $post['id']] = match ($channel['provider']) {
                    'facebook' => self::facebookPost((string) $post['external_id'], $token, ($post['media_kind'] ?? '') === 'video'),
                    'instagram' => self::instagramPost((string) $post['external_id'], $token, ($post['media_kind'] ?? '') === 'video'),
                    'threads' => self::threadsPost((string) $post['external_id'], $token),
                    'pinterest' => self::pinterestPost((string) $post['external_id'], $token, (string) $post['sent_at']),
                    default => [],
                };
            } catch (MetricsPermission $e) {
                throw $e;
            } catch (ChannelApiException $e) {
                if (in_array($e->status, [401], true)) {
                    throw $e;
                }
                $out[(int) $post['id']] = ['error' => $e->status === 404 ? 'The post was removed from the network.' : 'The network didn’t return stats for this post.'];
            }
        }
        return $out;
    }

    private static function facebookPost(string $id, string $token, bool $video): array
    {
        $m = [];
        if ($video && !str_contains($id, '_')) {
            $data = self::get(self::GRAPH . '/' . rawurlencode($id) . '?' . http_build_query(['fields' => 'likes.summary(true).limit(0),comments.summary(true).limit(0)', 'access_token' => $token]), null, true);
            $m['reactions'] = $data['likes']['summary']['total_count'] ?? null;
            $m['comments'] = $data['comments']['summary']['total_count'] ?? null;
            $ins = self::graphInsights(self::GRAPH . '/' . rawurlencode($id) . '/video_insights', $token, ['total_video_views', 'total_video_views_unique', 'total_video_view_total_time', 'total_video_impressions']);
            $m['video_views'] = $ins['total_video_views'] ?? null;
            $m['unique_viewers'] = $ins['total_video_views_unique'] ?? null;
            $m['watch_seconds'] = isset($ins['total_video_view_total_time']) ? (int) round($ins['total_video_view_total_time'] / 1000) : null;
            $m['impressions'] = $ins['total_video_impressions'] ?? null;
            return $m;
        }
        $data = self::get(self::GRAPH . '/' . rawurlencode($id) . '?' . http_build_query(['fields' => 'reactions.summary(total_count).limit(0),comments.summary(total_count).limit(0),shares', 'access_token' => $token]), null, true);
        $m['reactions'] = $data['reactions']['summary']['total_count'] ?? null;
        $m['comments'] = $data['comments']['summary']['total_count'] ?? null;
        $m['shares'] = isset($data['reactions']) ? (int) ($data['shares']['count'] ?? 0) : null;
        $ins = self::graphInsights(self::GRAPH . '/' . rawurlencode($id) . '/insights', $token, ['post_media_view', 'post_total_media_view_unique']);
        if (!$ins) {
            $ins = self::graphInsights(self::GRAPH . '/' . rawurlencode($id) . '/insights', $token, ['post_impressions', 'post_impressions_unique']);
            $m['impressions'] = $ins['post_impressions'] ?? null;
            $m['reach'] = $ins['post_impressions_unique'] ?? null;
        } else {
            $m['impressions'] = $ins['post_media_view'] ?? null;
            $m['reach'] = $ins['post_total_media_view_unique'] ?? null;
        }
        return $m;
    }

    private static function instagramPost(string $id, string $token, bool $video): array
    {
        $data = self::get(self::IG . '/' . rawurlencode($id) . '?' . http_build_query(['fields' => 'like_count,comments_count']), $token);
        $m = ['reactions' => $data['like_count'] ?? null, 'comments' => $data['comments_count'] ?? null];
        $metrics = ['views', 'reach', 'shares', 'saved'];
        if ($video) {
            $metrics[] = 'ig_reels_video_view_total_time';
        }
        try {
            $ins = self::get(self::IG . '/' . rawurlencode($id) . '/insights?' . http_build_query(['metric' => implode(',', $metrics)]), $token);
        } catch (ChannelApiException $e) {
            if (in_array($e->status, [400, 403], true)) {
                self::$note = 'Instagram shares likes and comments. To see views, reach, shares and saves, reconnect Instagram and allow insights.';
                return $m;
            }
            throw $e;
        }
        $v = self::values($ins);
        $m['impressions'] = $v['views'] ?? null;
        $m['reach'] = $v['reach'] ?? null;
        $m['shares'] = $v['shares'] ?? null;
        $m['saves'] = $v['saved'] ?? null;
        if ($video) {
            $m['video_views'] = $v['views'] ?? null;
            $m['watch_seconds'] = isset($v['ig_reels_video_view_total_time']) ? (int) round($v['ig_reels_video_view_total_time'] / 1000) : null;
        }
        return $m;
    }

    private static function threadsPost(string $id, string $token): array
    {
        try {
            $ins = self::get(self::THREADS . '/' . rawurlencode($id) . '/insights?' . http_build_query(['metric' => 'views,likes,replies,reposts,quotes,shares', 'access_token' => $token]));
        } catch (ChannelApiException $e) {
            if ($e->status === 403 || ($e->status === 400 && str_contains($e->getMessage(), 'ermission'))) {
                throw new MetricsPermission('Threads needs the insights permission.');
            }
            throw $e;
        }
        $v = self::values($ins);
        return [
            'impressions' => $v['views'] ?? null,
            'reactions' => $v['likes'] ?? null,
            'comments' => $v['replies'] ?? null,
            'shares' => isset($v['reposts']) || isset($v['quotes']) || isset($v['shares']) ? (int) ($v['reposts'] ?? 0) + (int) ($v['quotes'] ?? 0) + (int) ($v['shares'] ?? 0) : null,
        ];
    }

    private static function pinterestPost(string $id, string $token, string $sentAt): array
    {
        $start = max(strtotime($sentAt . ' UTC') ?: time(), time() - 89 * 86400);
        $data = self::get('https://api.pinterest.com/v5/pins/' . rawurlencode($id) . '/analytics?' . http_build_query([
            'start_date' => gmdate('Y-m-d', $start),
            'end_date' => gmdate('Y-m-d'),
            'metric_types' => 'IMPRESSION,SAVE,TOTAL_REACTIONS,TOTAL_COMMENTS,VIDEO_MRC_VIEW',
        ]), $token);
        $s = $data['all']['summary_metrics'] ?? ($data['all']['lifetime_metrics'] ?? []);
        return [
            'impressions' => $s['IMPRESSION'] ?? null,
            'saves' => $s['SAVE'] ?? null,
            'reactions' => $s['TOTAL_REACTIONS'] ?? null,
            'comments' => $s['TOTAL_COMMENTS'] ?? null,
            'video_views' => !empty($s['VIDEO_MRC_VIEW']) ? $s['VIDEO_MRC_VIEW'] : null,
        ];
    }

    /** Graph insights as name => lifetime value. Empty when the metrics aren't available. */
    private static function graphInsights(string $url, string $token, array $metrics): array
    {
        try {
            return self::values(self::get($url . '?' . http_build_query(['metric' => implode(',', $metrics), 'access_token' => $token]), null, true));
        } catch (MetricsPermission $e) {
            throw $e;
        } catch (ChannelApiException) {
            return [];
        }
    }

    private static function values(array $data): array
    {
        $out = [];
        foreach ($data['data'] ?? [] as $m) {
            $name = (string) ($m['name'] ?? '');
            $value = $m['total_value']['value'] ?? ($m['values'][0]['value'] ?? null);
            if ($name !== '' && is_numeric($value)) {
                $out[$name] = (int) $value;
            }
        }
        return $out;
    }

    private static function int(array $data, array ...$paths): ?int
    {
        foreach ($paths as $path) {
            $v = $data;
            foreach ($path as $key) {
                $v = is_array($v) ? ($v[$key] ?? null) : null;
            }
            if (is_numeric($v)) {
                return (int) $v;
            }
        }
        return null;
    }

    /** User-facing reason a channel's stats couldn't be read. */
    private static function explain(string $provider, Throwable $e): string
    {
        $name = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'threads' => 'Threads', 'youtube' => 'YouTube', 'pinterest' => 'Pinterest'][$provider] ?? 'The network';
        return match (true) {
            $e instanceof ChannelAuthException => $e->getMessage(),
            $e instanceof MetricsPermission, $e instanceof ChannelApiException && in_array($e->status, [401, 403], true)
                => 'Reconnect ' . $name . ' and allow access to insights to see its stats.',
            $e instanceof ChannelApiException && $e->status === 429 => $name . ' limited how often stats can be read. atwebtech will try again later.',
            default => $name . ' didn’t return stats this time. atwebtech will try again later.',
        };
    }

    /**
     * GET a network API. Graph errors with code 10/200 (missing permission) become MetricsPermission
     * when $graph is true, so one channel-level message replaces a failure on every post.
     */
    private static function get(string $url, ?string $bearer = null, bool $graph = false): array
    {
        $headers = ['Accept: application/json'];
        if ($bearer !== null) {
            $headers[] = 'Authorization: Bearer ' . $bearer;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($body === false) {
            throw new ChannelApiException('Request failed: ' . curl_error($ch), 0);
        }
        $data = json_decode((string) $body, true);
        if ($status < 200 || $status >= 300) {
            $code = is_array($data) ? (int) ($data['error']['code'] ?? 0) : 0;
            $reason = is_array($data) ? (string) ($data['error']['message'] ?? ($data['detail'] ?? ($data['message'] ?? ''))) : '';
            if ($graph && (in_array($code, [10, 200], true) || ($code === 100 && str_contains($reason, 'missing permission')))) {
                throw new MetricsPermission($reason);
            }
            throw new ChannelApiException(sprintf('HTTP %d from %s %s', $status, parse_url($url, PHP_URL_HOST), mb_substr(strip_tags($reason), 0, 200)), $status);
        }
        return is_array($data) ? $data : [];
    }
}

final class MetricsPermission extends RuntimeException
{
}
