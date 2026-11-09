<?php

declare(strict_types=1);

/**
 * Competitor analysis: follow public accounts on other networks and compare their followers,
 * posting rhythm and engagement with yours. Data comes from each network's public API and is
 * refreshed by bin/publish.php twice a day.
 */
final class Competitors
{
    public const NETWORKS = [
        'youtube' => ['name' => 'YouTube', 'placeholder' => '@channel or youtube.com/@channel', 'followers' => 'Subscribers'],
        'bluesky' => ['name' => 'Bluesky', 'placeholder' => 'handle.bsky.social', 'followers' => 'Followers'],
        'instagram' => ['name' => 'Instagram', 'placeholder' => '@username (professional account)', 'followers' => 'Followers'],
        'x' => ['name' => 'X', 'placeholder' => '@username', 'followers' => 'Followers'],
    ];
    public const FREE_LIMIT = 3;
    public const PAID_LIMIT = 25;
    private const POSTS = 30;
    private const REFRESH_HOURS = 12;
    private const GRAPH = 'https://graph.facebook.com/v23.0';
    private const AVATAR_HOSTS = ['yt3.ggpht.com', 'yt3.googleusercontent.com', 'cdn.bsky.app', 'pbs.twimg.com'];

    public static function limit(int $uid): int
    {
        return Channels::allowance($uid)['paid'] ? self::PAID_LIMIT : self::FREE_LIMIT;
    }

    public static function forUser(int $uid): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM competitors WHERE user_id = :uid ORDER BY followers IS NULL, followers DESC, id');
        $stmt->execute(['uid' => $uid]);
        return $stmt->fetchAll();
    }

    /**
     * Rows for the comparison table over the last $days: follower change, posts, average likes,
     * comments and views per post, engagement rate and posts per week.
     */
    public static function report(int $uid, int $days): array
    {
        $pdo = Database::connection();
        $since = gmdate('Y-m-d H:i:s', time() - $days * 86400);
        $agg = $pdo->prepare('SELECT COUNT(*) AS n, AVG(likes) AS likes, AVG(comments) AS comments, AVG(shares) AS shares, AVG(views) AS views,
            SUM(COALESCE(likes, 0) + COALESCE(comments, 0) + COALESCE(shares, 0)) AS interactions
            FROM competitor_posts WHERE competitor_id = :id AND published_at >= :since');
        $start = $pdo->prepare('SELECT day, followers FROM competitor_stats WHERE competitor_id = :id AND day >= :day ORDER BY day ASC LIMIT 1');
        $series = $pdo->prepare('SELECT day, followers FROM competitor_stats WHERE competitor_id = :id AND day >= :day ORDER BY day');
        $top = $pdo->prepare('SELECT * FROM competitor_posts WHERE competitor_id = :id AND published_at >= :since ORDER BY (COALESCE(likes, 0) + COALESCE(comments, 0) + COALESCE(shares, 0)) DESC, views DESC LIMIT 5');
        $recent = $pdo->prepare('SELECT published_at, likes, comments, shares, views FROM competitor_posts WHERE competitor_id = :id AND published_at >= :since ORDER BY published_at');
        $rows = [];
        foreach (self::forUser($uid) as $c) {
            $id = (int) $c['id'];
            $agg->execute(['id' => $id, 'since' => $since]);
            $a = $agg->fetch() ?: [];
            $start->execute(['id' => $id, 'day' => gmdate('Y-m-d', time() - $days * 86400)]);
            $first = $start->fetch();
            $first = $first && $first['day'] < gmdate('Y-m-d') ? (int) $first['followers'] : false; // Growth needs two days of data.
            $series->execute(['id' => $id, 'day' => gmdate('Y-m-d', time() - $days * 86400)]);
            $top->execute(['id' => $id, 'since' => $since]);
            $recent->execute(['id' => $id, 'since' => $since]);
            $n = (int) ($a['n'] ?? 0);
            $followers = $c['followers'] !== null ? (int) $c['followers'] : null;
            $rows[] = $c + [
                'posts' => $n,
                'per_week' => round($n / max(1, $days) * 7, 1),
                'avg_likes' => $n && $a['likes'] !== null ? (float) $a['likes'] : null,
                'avg_comments' => $n && $a['comments'] !== null ? (float) $a['comments'] : null,
                'avg_views' => $n && $a['views'] !== null ? (float) $a['views'] : null,
                'engagement' => $n && $followers ? round((float) $a['interactions'] / $n / $followers * 100, 2) : null,
                'growth' => $followers !== null && $first !== false ? $followers - $first : null,
                'growth_since' => $first !== false,
                'series' => $series->fetchAll(),
                'top' => $top->fetchAll(),
                'timeline' => $recent->fetchAll(),
            ];
        }
        return $rows;
    }

    /** POST /app/competitors: add, refresh or remove. */
    public static function handle(): never
    {
        $user = Auth::member();
        $uid = (int) $user['id'];
        $back = '/app/competitors' . (isset($_POST['range']) && in_array($_POST['range'], ['7', '30', '90'], true) ? '?range=' . $_POST['range'] : '');
        $action = (string) ($_POST['action'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        if ($action === 'remove') {
            Database::connection()->prepare('DELETE FROM competitors WHERE id = :id AND user_id = :uid')->execute(['id' => $id, 'uid' => $uid]);
            self::done($back, 'Competitor removed.');
        }
        if ($action === 'refresh') {
            $c = self::find($uid, $id);
            if ($c === null) {
                self::done($back, 'That competitor was removed.');
            }
            if ($c['last_checked_at'] && strtotime($c['last_checked_at'] . ' UTC') > time() - 600) {
                self::done($back, $c['name'] . ' was updated a few minutes ago. Try again shortly.');
            }
            $error = self::refresh($c, $user);
            self::done($back, $error ?? $c['name'] . ' is up to date.');
        }
        if ($action !== 'add') {
            self::done($back, 'Nothing changed.');
        }
        $network = (string) ($_POST['network'] ?? '');
        $input = trim((string) ($_POST['handle'] ?? ''));
        if (!isset(self::NETWORKS[$network])) {
            self::done($back, 'Choose a network.');
        }
        if ($input === '' || mb_strlen($input) > 200) {
            self::done($back, 'Enter the account’s handle or profile link.');
        }
        if (count(self::forUser($uid)) >= self::limit($uid)) {
            self::done($back, Channels::allowance($uid)['paid']
                ? 'You’re following ' . self::PAID_LIMIT . ' competitors, the most for your plan. Remove one to add another.'
                : 'The Free plan follows up to ' . self::FREE_LIMIT . ' competitors. Upgrade under Plan & billing to follow up to ' . self::PAID_LIMIT . '.');
        }
        if (RateLimiter::tooManyAttempts('competitor', (string) $uid, 30, 60)) {
            self::done($back, 'That’s a lot of lookups in a short time. Please try again in an hour.');
        }
        RateLimiter::hit('competitor', (string) $uid);
        try {
            $profile = self::lookup($network, $input, $user);
        } catch (CompetitorError $e) {
            self::done($back, $e->getMessage());
        } catch (Throwable $e) {
            error_log(sprintf('[%s] competitor lookup %s: %s', gmdate('c'), $network, $e->getMessage()));
            self::done($back, self::NETWORKS[$network]['name'] . ' didn’t answer. Check the handle and try again in a minute.');
        }
        $pdo = Database::connection();
        $exists = $pdo->prepare('SELECT id FROM competitors WHERE user_id = :uid AND network = :n AND external_id = :e');
        $exists->execute(['uid' => $uid, 'n' => $network, 'e' => $profile['external_id']]);
        if ($exists->fetchColumn()) {
            self::done($back, 'You already follow ' . $profile['name'] . '.');
        }
        $pdo->prepare('INSERT INTO competitors (user_id, network, handle, external_id, name, avatar_url, url, created_at) VALUES (:uid, :n, :h, :e, :name, :a, :url, :now)')
            ->execute(['uid' => $uid, 'n' => $network, 'h' => mb_substr($profile['handle'], 0, 120), 'e' => $profile['external_id'], 'name' => mb_substr($profile['name'], 0, 190), 'a' => self::safeAvatar($profile['avatar']), 'url' => $profile['url'], 'now' => Database::now()]);
        $c = self::find($uid, (int) $pdo->lastInsertId());
        $error = self::save($c, $profile);
        self::done($back, $error ?? 'Now following ' . $profile['name'] . '. Their recent posts are below.');
    }

    /** Refresh competitors not checked for 12 hours. Called from bin/publish.php. */
    public static function run(int $seconds = 20): int
    {
        $started = time();
        $stmt = Database::connection()->prepare('SELECT * FROM competitors WHERE last_checked_at IS NULL OR last_checked_at < :due ORDER BY last_checked_at IS NOT NULL, last_checked_at LIMIT 5');
        $stmt->execute(['due' => gmdate('Y-m-d H:i:s', time() - self::REFRESH_HOURS * 3600)]);
        $n = 0;
        $owners = Database::connection()->prepare('SELECT * FROM users WHERE id = :id');
        foreach ($stmt->fetchAll() as $c) {
            if (time() - $started > $seconds) {
                break;
            }
            $owners->execute(['id' => (int) $c['user_id']]);
            $user = $owners->fetch();
            if ($user) {
                self::refresh($c, $user);
                $n++;
            }
        }
        return $n;
    }

    /** @return string|null an error to show, or null on success */
    public static function refresh(array $c, array $user): ?string
    {
        try {
            $profile = self::lookup((string) $c['network'], (string) ($c['network'] === 'youtube' ? $c['external_id'] : $c['handle']), $user, $c['network'] === 'youtube');
        } catch (Throwable $e) {
            $message = $e instanceof CompetitorError ? $e->getMessage() : self::NETWORKS[$c['network']]['name'] . ' didn’t answer this time. atwebtech will try again later.';
            if (!$e instanceof CompetitorError) {
                error_log(sprintf('[%s] competitor refresh %d: %s', gmdate('c'), (int) $c['id'], $e->getMessage()));
            }
            Database::connection()->prepare('UPDATE competitors SET last_checked_at = :now, error = :err WHERE id = :id')->execute(['now' => Database::now(), 'err' => mb_substr($message, 0, 300), 'id' => (int) $c['id']]);
            return $message;
        }
        return self::save($c, $profile);
    }

    private static function save(array $c, array $profile): ?string
    {
        $pdo = Database::connection();
        $id = (int) $c['id'];
        $pdo->prepare('UPDATE competitors SET name = :name, avatar_url = :a, url = :url, followers = :f, posts_total = :p, last_checked_at = :now, error = NULL WHERE id = :id')
            ->execute(['name' => mb_substr($profile['name'], 0, 190), 'a' => self::safeAvatar($profile['avatar']), 'url' => $profile['url'], 'f' => $profile['followers'], 'p' => $profile['posts_total'], 'now' => Database::now(), 'id' => $id]);
        if ($profile['followers'] !== null) {
            $day = gmdate('Y-m-d');
            $pdo->prepare('DELETE FROM competitor_stats WHERE competitor_id = :id AND day = :day')->execute(['id' => $id, 'day' => $day]);
            $pdo->prepare('INSERT INTO competitor_stats (competitor_id, day, followers, posts_total) VALUES (:id, :day, :f, :p)')->execute(['id' => $id, 'day' => $day, 'f' => $profile['followers'], 'p' => $profile['posts_total']]);
        }
        $del = $pdo->prepare('DELETE FROM competitor_posts WHERE competitor_id = :id AND external_id = :e');
        $ins = $pdo->prepare('INSERT INTO competitor_posts (competitor_id, external_id, published_at, text, url, kind, likes, comments, shares, views) VALUES (:id, :e, :at, :t, :url, :k, :l, :c, :s, :v)');
        foreach ($profile['posts'] as $p) {
            $del->execute(['id' => $id, 'e' => $p['id']]);
            $ins->execute(['id' => $id, 'e' => mb_substr($p['id'], 0, 191), 'at' => $p['at'], 't' => $p['text'] !== null ? mb_substr($p['text'], 0, 500) : null, 'url' => mb_substr($p['url'], 0, 500), 'k' => $p['kind'], 'l' => $p['likes'], 'c' => $p['comments'], 's' => $p['shares'], 'v' => $p['views']]);
        }
        return null;
    }

    private static function find(int $uid, int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM competitors WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $id, 'uid' => $uid]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Profile and recent posts for a public account.
     * @return array{external_id:string, handle:string, name:string, avatar:?string, url:string, followers:?int, posts_total:?int, posts:list<array>}
     */
    private static function lookup(string $network, string $input, array $user, bool $isId = false): array
    {
        return match ($network) {
            'youtube' => self::youtube($input, $isId),
            'bluesky' => self::bluesky($input),
            'x' => self::x($input, $user),
            'instagram' => self::instagram($input, $user),
        };
    }

    private static function youtube(string $input, bool $isId): array
    {
        $access = Trends::youtubeAccess();
        if ($access === null) {
            throw new CompetitorError('YouTube lookups need a connected YouTube channel or a YouTube API key on the server.');
        }
        $params = ['part' => 'snippet,statistics,contentDetails'];
        if ($isId || preg_match('~(?:youtube\.com/channel/)?(UC[A-Za-z0-9_-]{22})~', $input, $m)) {
            $params['id'] = $isId ? $input : $m[1];
        } elseif (preg_match('~(?:youtube\.com/)?@([A-Za-z0-9._-]{3,100})~', $input, $m) || preg_match('~^([A-Za-z0-9._-]{3,100})$~', $input, $m)) {
            $params['forHandle'] = '@' . $m[1];
        } else {
            throw new CompetitorError('Enter a YouTube handle like @channel, or a channel link.');
        }
        $data = self::get('https://www.googleapis.com/youtube/v3/channels?' . self::ytQuery($params, $access), $access);
        $ch = $data['items'][0] ?? null;
        if (!is_array($ch)) {
            throw new CompetitorError('No YouTube channel found for “' . $input . '”. Check the handle.');
        }
        $stats = $ch['statistics'] ?? [];
        $posts = [];
        $uploads = (string) ($ch['contentDetails']['relatedPlaylists']['uploads'] ?? '');
        if ($uploads !== '') {
            $list = self::get('https://www.googleapis.com/youtube/v3/playlistItems?' . self::ytQuery(['part' => 'contentDetails', 'playlistId' => $uploads, 'maxResults' => self::POSTS], $access), $access);
            $ids = array_values(array_filter(array_map(static fn (array $i): string => (string) ($i['contentDetails']['videoId'] ?? ''), $list['items'] ?? [])));
            if ($ids) {
                $videos = self::get('https://www.googleapis.com/youtube/v3/videos?' . self::ytQuery(['part' => 'snippet,statistics', 'id' => implode(',', $ids)], $access), $access);
                foreach ($videos['items'] ?? [] as $v) {
                    $s = $v['statistics'] ?? [];
                    $posts[] = self::post((string) $v['id'], (string) ($v['snippet']['publishedAt'] ?? ''), (string) ($v['snippet']['title'] ?? ''), 'https://www.youtube.com/watch?v=' . $v['id'], 'video', $s['likeCount'] ?? null, $s['commentCount'] ?? null, null, $s['viewCount'] ?? null);
                }
            }
        }
        $handle = (string) ($ch['snippet']['customUrl'] ?? '');
        return [
            'external_id' => (string) $ch['id'],
            'handle' => $handle !== '' ? $handle : (string) $ch['id'],
            'name' => (string) ($ch['snippet']['title'] ?? $handle),
            'avatar' => $ch['snippet']['thumbnails']['default']['url'] ?? null,
            'url' => 'https://www.youtube.com/' . ($handle !== '' ? $handle : 'channel/' . $ch['id']),
            'followers' => !empty($stats['hiddenSubscriberCount']) ? null : (isset($stats['subscriberCount']) ? (int) $stats['subscriberCount'] : null),
            'posts_total' => isset($stats['videoCount']) ? (int) $stats['videoCount'] : null,
            'posts' => array_values(array_filter($posts)),
        ];
    }

    private static function bluesky(string $input): array
    {
        $actor = preg_replace('~^(?:https?://)?bsky\.app/profile/~i', '', trim($input));
        $actor = ltrim(strtolower((string) strtok((string) $actor, '/?#')), '@');
        if (!preg_match('/^(did:plc:[a-z0-9]{10,64}|[a-z0-9][a-z0-9.-]{1,250}\.[a-z]{2,})$/', $actor)) {
            throw new CompetitorError('Enter a Bluesky handle like name.bsky.social, or a bsky.app profile link.');
        }
        $base = 'https://public.api.bsky.app/xrpc/';
        try {
            $p = self::get($base . 'app.bsky.actor.getProfile?' . http_build_query(['actor' => $actor]));
        } catch (ChannelApiException $e) {
            if ($e->status === 400) {
                throw new CompetitorError('No Bluesky account found for “' . $input . '”. Check the handle.');
            }
            throw $e;
        }
        $feed = self::get($base . 'app.bsky.feed.getAuthorFeed?' . http_build_query(['actor' => $p['did'], 'limit' => self::POSTS, 'filter' => 'posts_no_replies']));
        $posts = [];
        foreach ($feed['feed'] ?? [] as $item) {
            $post = $item['post'] ?? [];
            if (isset($item['reason']) || ($post['author']['did'] ?? '') !== $p['did']) {
                continue; // Reposts of other people's posts.
            }
            $rkey = substr((string) strrchr((string) ($post['uri'] ?? ''), '/'), 1);
            $posts[] = self::post((string) ($post['uri'] ?? ''), (string) ($post['record']['createdAt'] ?? $post['indexedAt'] ?? ''), (string) ($post['record']['text'] ?? ''), 'https://bsky.app/profile/' . $p['handle'] . '/post/' . $rkey, isset($post['embed']['video']) || str_contains((string) ($post['embed']['$type'] ?? ''), 'video') ? 'video' : 'post',
                $post['likeCount'] ?? null, $post['replyCount'] ?? null, (int) ($post['repostCount'] ?? 0) + (int) ($post['quoteCount'] ?? 0), null);
        }
        return [
            'external_id' => (string) $p['did'],
            'handle' => (string) $p['handle'],
            'name' => (string) (($p['displayName'] ?? '') !== '' ? $p['displayName'] : $p['handle']),
            'avatar' => $p['avatar'] ?? null,
            'url' => 'https://bsky.app/profile/' . $p['handle'],
            'followers' => isset($p['followersCount']) ? (int) $p['followersCount'] : null,
            'posts_total' => isset($p['postsCount']) ? (int) $p['postsCount'] : null,
            'posts' => array_values(array_filter($posts)),
        ];
    }

    private static function x(string $input, array $user): array
    {
        $username = ltrim((string) preg_replace('~^(?:https?://)?(?:www\.)?(?:x|twitter)\.com/~i', '', trim($input)), '@');
        $username = (string) strtok($username, '/?#');
        if (!preg_match('/^[A-Za-z0-9_]{1,15}$/', $username)) {
            throw new CompetitorError('Enter an X username like @name.');
        }
        $token = self::channelToken((int) $user['id'], 'x');
        if ($token === null) {
            throw new CompetitorError('Connect your X account first. atwebtech reads competitors on X through your connection.');
        }
        try {
            $u = self::get('https://api.x.com/2/users/by/username/' . $username . '?user.fields=public_metrics,profile_image_url,name', $token);
            $id = (string) ($u['data']['id'] ?? '');
            if ($id === '') {
                throw new CompetitorError('No X account found for @' . $username . '.');
            }
            $t = self::get('https://api.x.com/2/users/' . $id . '/tweets?' . http_build_query(['max_results' => 20, 'exclude' => 'retweets,replies', 'tweet.fields' => 'public_metrics,created_at']), $token);
        } catch (ChannelApiException $e) {
            if (in_array($e->status, [402, 403], true)) {
                throw new CompetitorError('X only shares other accounts’ data with apps that have X API credits. Add credits in the X developer portal, then try again.');
            }
            if ($e->status === 429) {
                throw new CompetitorError('X limited how often accounts can be looked up. Try again in 15 minutes.');
            }
            throw $e;
        }
        $posts = [];
        foreach ($t['data'] ?? [] as $tw) {
            $m = $tw['public_metrics'] ?? [];
            $posts[] = self::post((string) $tw['id'], (string) ($tw['created_at'] ?? ''), (string) ($tw['text'] ?? ''), 'https://x.com/' . $username . '/status/' . $tw['id'], 'post', $m['like_count'] ?? null, $m['reply_count'] ?? null, (int) ($m['retweet_count'] ?? 0) + (int) ($m['quote_count'] ?? 0), $m['impression_count'] ?? null);
        }
        $pm = $u['data']['public_metrics'] ?? [];
        return [
            'external_id' => $id,
            'handle' => '@' . $username,
            'name' => (string) ($u['data']['name'] ?? $username),
            'avatar' => $u['data']['profile_image_url'] ?? null,
            'url' => 'https://x.com/' . $username,
            'followers' => isset($pm['followers_count']) ? (int) $pm['followers_count'] : null,
            'posts_total' => isset($pm['tweet_count']) ? (int) $pm['tweet_count'] : null,
            'posts' => array_values(array_filter($posts)),
        ];
    }

    /** Instagram Business Discovery, through a connected Facebook Page that has an Instagram professional account. */
    private static function instagram(string $input, array $user): array
    {
        $username = ltrim((string) preg_replace('~^(?:https?://)?(?:www\.)?instagram\.com/~i', '', trim($input)), '@');
        $username = strtolower((string) strtok($username, '/?#'));
        if (!preg_match('/^[a-z0-9._]{1,30}$/', $username)) {
            throw new CompetitorError('Enter an Instagram username like @name.');
        }
        $fields = 'business_discovery.username(' . $username . '){id,username,name,profile_picture_url,followers_count,media_count,media.limit(' . self::POSTS . '){id,caption,like_count,comments_count,timestamp,permalink,media_type}}';
        foreach (self::pageTokens((int) $user['id']) as [$pageId, $token]) {
            try {
                $page = self::get(self::GRAPH . '/' . rawurlencode($pageId) . '?' . http_build_query(['fields' => 'instagram_business_account', 'access_token' => $token]));
                $ig = (string) ($page['instagram_business_account']['id'] ?? '');
                if ($ig === '') {
                    continue;
                }
                $d = self::get(self::GRAPH . '/' . $ig . '?' . http_build_query(['fields' => $fields, 'access_token' => $token]));
            } catch (ChannelApiException $e) {
                if (str_contains($e->getMessage(), 'Invalid user id') || str_contains($e->getMessage(), 'cannot be found')) {
                    throw new CompetitorError('No Instagram professional account named @' . $username . '. Personal accounts can’t be followed.');
                }
                continue;
            }
            $b = $d['business_discovery'] ?? null;
            if (!is_array($b)) {
                throw new CompetitorError('No Instagram professional account named @' . $username . '.');
            }
            $posts = [];
            foreach ($b['media']['data'] ?? [] as $m) {
                $posts[] = self::post((string) $m['id'], (string) ($m['timestamp'] ?? ''), (string) ($m['caption'] ?? ''), (string) ($m['permalink'] ?? 'https://www.instagram.com/' . $username . '/'), strtolower((string) ($m['media_type'] ?? 'post')), $m['like_count'] ?? null, $m['comments_count'] ?? null, null, null);
            }
            return [
                'external_id' => (string) $b['id'],
                'handle' => '@' . $b['username'],
                'name' => (string) (($b['name'] ?? '') !== '' ? $b['name'] : $b['username']),
                'avatar' => $b['profile_picture_url'] ?? null,
                'url' => 'https://www.instagram.com/' . $b['username'] . '/',
                'followers' => isset($b['followers_count']) ? (int) $b['followers_count'] : null,
                'posts_total' => isset($b['media_count']) ? (int) $b['media_count'] : null,
                'posts' => array_values(array_filter($posts)),
            ];
        }
        throw new CompetitorError('Instagram competitors need a connected Facebook Page that is linked to your Instagram professional account, with Instagram access allowed. Link them in Meta Business Suite, then reconnect the Page.');
    }

    private static function channelToken(int $uid, string $provider): ?string
    {
        $stmt = Database::connection()->prepare('SELECT id FROM channels WHERE user_id = :uid AND provider = :p ORDER BY id DESC');
        $stmt->execute(['uid' => $uid, 'p' => $provider]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $c = Channels::find($uid, (int) $id);
            try {
                return $c ? Channels::accessToken($c) : null;
            } catch (Throwable) {
                continue;
            }
        }
        return null;
    }

    /** @return list<array{0:string,1:string}> page id and page token for each connected Facebook Page */
    private static function pageTokens(int $uid): array
    {
        $out = [];
        foreach (Channels::forUser($uid) as $c) {
            if ($c['provider'] !== 'facebook') {
                continue;
            }
            $full = Channels::find($uid, (int) $c['id']);
            try {
                $out[] = [(string) $full['external_id'], Channels::accessToken($full)];
            } catch (Throwable) {
            }
        }
        return $out;
    }

    private static function post(string $id, string $at, string $text, string $url, string $kind, $likes, $comments, $shares, $views): ?array
    {
        $ts = strtotime($at);
        if ($id === '' || $ts === false || !str_starts_with($url, 'https://')) {
            return null;
        }
        $num = static fn ($v): ?int => is_numeric($v) ? max(0, (int) $v) : null;
        return ['id' => $id, 'at' => gmdate('Y-m-d H:i:s', $ts), 'text' => trim($text) !== '' ? trim($text) : null, 'url' => $url, 'kind' => mb_substr($kind, 0, 20),
            'likes' => $num($likes), 'comments' => $num($comments), 'shares' => $num($shares), 'views' => $num($views)];
    }

    private static function ytQuery(array $params, array $access): string
    {
        return http_build_query($params + (isset($access['key']) ? ['key' => $access['key']] : []));
    }

    private static function safeAvatar(?string $url): ?string
    {
        if (!is_string($url) || !str_starts_with($url, 'https://') || strlen($url) > 500) {
            return null;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        return in_array($host, self::AVATAR_HOSTS, true) || str_ends_with($host, '.cdninstagram.com') || str_ends_with($host, '.fbcdn.net') ? $url : null;
    }

    private static function get(string $url, array|string|null $auth = null): array
    {
        $headers = ['Accept: application/json', 'User-Agent: atwebtech/1.0 (+https://atwebtech.us)'];
        $bearer = is_string($auth) ? $auth : (is_array($auth) ? ($auth['token'] ?? null) : null);
        if ($bearer !== null) {
            $headers[] = 'Authorization: Bearer ' . $bearer;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => $headers, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($body === false) {
            throw new ChannelApiException('Request failed: ' . curl_error($ch), 0);
        }
        $data = json_decode((string) $body, true);
        if ($status < 200 || $status >= 300) {
            $reason = is_array($data) ? (string) ($data['error']['message'] ?? ($data['message'] ?? ($data['detail'] ?? ''))) : '';
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

final class CompetitorError extends RuntimeException
{
}
