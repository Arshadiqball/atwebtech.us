<?php

declare(strict_types=1);

/**
 * Competitor analysis (YouTube, Bluesky, Instagram): follow public accounts on other networks and compare their followers,
 * posting rhythm and engagement with yours. Data comes from each network's public API and is
 * refreshed by bin/publish.php twice a day.
 */
final class Competitors
{
    /** Networks with an official way to read other accounts. */
    public const NETWORKS = [
        'youtube' => ['name' => 'YouTube', 'followers' => 'Subscribers'],
        'instagram' => ['name' => 'Instagram', 'followers' => 'Followers'],
        'facebook' => ['name' => 'Facebook Page', 'followers' => 'Followers'],
        'threads' => ['name' => 'Threads', 'followers' => 'Followers'],
        'bluesky' => ['name' => 'Bluesky', 'followers' => 'Followers'],
        'mastodon' => ['name' => 'Mastodon', 'followers' => 'Followers'],
    ];
    /** Networks that give apps no official access to other accounts' stats. */
    public const UNAVAILABLE = [
        'tiktok' => 'TikTok',
        'linkedin' => 'LinkedIn',
        'pinterest' => 'Pinterest',
        'x' => 'X',
    ];
    public const FREE_LIMIT = 3;
    public const PAID_LIMIT = 25;
    private const POSTS = 30;
    private const REFRESH_HOURS = 12;
    private const GRAPH = 'https://graph.facebook.com/v23.0';
    private const AVATAR_HOSTS = ['yt3.ggpht.com', 'yt3.googleusercontent.com', 'cdn.bsky.app'];

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
        if ($action === 'add_found') {
            self::addFound($user, $back);
        }
        if ($action !== 'add') {
            self::done($back, 'Nothing changed.');
        }
        $network = (string) ($_POST['network'] ?? 'auto');
        $input = trim((string) ($_POST['handle'] ?? ''));
        if ($input === '' || mb_strlen($input) > 200) {
            self::done($back, 'Paste the account’s profile or page link, or enter its handle.');
        }
        $detected = self::detect($input);
        if (($network === 'auto' || $network === '') && $detected === null && preg_match('~^(https?://)?[a-z0-9.-]+\.[a-z]{2,}(/.*)?$~i', $input)) {
            self::scanWebsite($input, $back);
        }
        if ($network === 'auto' || $network === '') {
            $network = $detected ?? '';
            if ($network === '') {
                self::done($back, 'We couldn’t tell which network that is. Paste the full profile link, or choose the network.');
            }
        } elseif ($detected !== null && $detected !== $network) {
            $network = $detected; // The link wins over the dropdown.
        }
        if (isset(self::UNAVAILABLE[$network])) {
            self::done($back, self::UNAVAILABLE[$network] . ' doesn’t let apps read other accounts’ stats, so it can’t be followed yet.');
        }
        if (!isset(self::NETWORKS[$network])) {
            self::done($back, 'Choose a network.');
        }
        self::follow($user, $network, $input, $back);
    }

    /** Follow one account and redirect back with the result. */
    private static function follow(array $user, string $network, string $input, string $back, bool $quiet = false): ?string
    {
        $uid = (int) $user['id'];
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
            if ($quiet) {
                return $e->getMessage();
            }
            if ($e->connect !== null) {
                $_SESSION['_co_connect'] = $e->connect;
            }
            self::done($back, $e->getMessage());
        } catch (Throwable $e) {
            error_log(sprintf('[%s] competitor lookup %s: %s', gmdate('c'), $network, $e->getMessage()));
            $msg = self::NETWORKS[$network]['name'] . ' didn’t answer. Check the link and try again in a minute.';
            if ($quiet) {
                return $msg;
            }
            self::done($back, $msg);
        }
        $pdo = Database::connection();
        $exists = $pdo->prepare('SELECT id FROM competitors WHERE user_id = :uid AND network = :n AND external_id = :e');
        $exists->execute(['uid' => $uid, 'n' => $network, 'e' => $profile['external_id']]);
        if ($exists->fetchColumn()) {
            if ($quiet) {
                return 'already following';
            }
            self::done($back, 'You already follow ' . $profile['name'] . '.');
        }
        $pdo->prepare('INSERT INTO competitors (user_id, network, handle, external_id, name, avatar_url, url, created_at) VALUES (:uid, :n, :h, :e, :name, :a, :url, :now)')
            ->execute(['uid' => $uid, 'n' => $network, 'h' => mb_substr($profile['handle'], 0, 120), 'e' => $profile['external_id'], 'name' => mb_substr($profile['name'], 0, 190), 'a' => self::safeAvatar($profile['avatar']), 'url' => $profile['url'], 'now' => Database::now()]);
        $c = self::find($uid, (int) $pdo->lastInsertId());
        $error = self::save($c, $profile);
        if ($quiet) {
            return null;
        }
        self::done($back, $error ?? 'Now following ' . $profile['name'] . '. Their recent posts are below.');
    }

    /**
     * A website link: read its home page for links to its social profiles and offer them
     * (shown once on the page, where the user picks which to follow).
     */
    private static function scanWebsite(string $input, string $back): never
    {
        $url = preg_match('~^https?://~i', $input) ? $input : 'https://' . $input;
        $uid = (int) (Auth::user()['id'] ?? 0);
        if (RateLimiter::tooManyAttempts('competitor', (string) $uid, 30, 60)) {
            self::done($back, 'That’s a lot of lookups in a short time. Please try again in an hour.');
        }
        RateLimiter::hit('competitor', (string) $uid);
        try {
            $html = Feeds::fetch($url);
        } catch (Throwable $e) {
            self::done($back, 'We couldn’t open ' . (parse_url($url, PHP_URL_HOST) ?: $input) . '. Check the address, or paste a social profile link instead.');
        }
        $found = self::socialLinks($html);
        $site = (string) parse_url($url, PHP_URL_HOST);
        if (!$found) {
            self::done($back, 'We didn’t find social profile links on ' . $site . '. Paste their Instagram, YouTube or other profile link instead.');
        }
        $_SESSION['_co_found'] = ['site' => $site, 'profiles' => $found];
        self::done($back, 'Found ' . count($found) . ' social ' . (count($found) === 1 ? 'profile' : 'profiles') . ' on ' . $site . '. Choose which to follow.');
    }

    /** POST action=add_found: follow the profiles picked from a website scan. */
    private static function addFound(array $user, string $back): never
    {
        $picked = array_slice(array_values(array_filter((array) ($_POST['profiles'] ?? []), 'is_string')), 0, 10);
        $ok = 0;
        $problems = [];
        foreach ($picked as $link) {
            $network = self::detect($link);
            if ($network === null || !isset(self::NETWORKS[$network])) {
                continue;
            }
            if (count(self::forUser((int) $user['id'])) >= self::limit((int) $user['id'])) {
                $problems[] = 'your plan’s limit was reached';
                break;
            }
            $error = self::follow($user, $network, $link, $back, true);
            if ($error === null) {
                $ok++;
            } elseif ($error !== 'already following') {
                $problems[] = self::NETWORKS[$network]['name'] . ': ' . rtrim($error, '.');
            }
        }
        self::done($back, ($ok === 0 ? 'No new competitors added.' : 'Now following ' . $ok . ($ok === 1 ? ' profile.' : ' profiles.')) . ($problems ? ' Skipped: ' . implode('; ', array_unique($problems)) . '.' : ''));
    }

    /**
     * Profile links in a page: <a href>, JSON-LD sameAs and meta tags, one per network,
     * skipping share buttons, single posts and videos.
     * @return list<array{network:string, url:string, available:bool}>
     */
    public static function socialLinks(string $html): array
    {
        preg_match_all('~(?:href|content)\s*=\s*["\']([^"\'\s>]{8,300})["\']~i', $html, $m);
        preg_match_all('~"(https?:\\?/\\?/[^"\s]{8,300})"~', $html, $j);
        $candidates = array_merge($m[1], array_map(static fn (string $u): string => str_replace('\\/', '/', $u), $j[1]));
        $skip = '~/(sharer|share|intent|shareArticle|dialog|plugins|pin/create|p/|reel/|reels/|watch|shorts/|embed|hashtag|explore|status/|posts/|photo|video/|events/|groups/|login|signup|privacy|legal|help|about/|policies)~i';
        $out = [];
        foreach ($candidates as $raw) {
            $u = html_entity_decode(trim($raw), ENT_QUOTES);
            if (str_starts_with($u, '//')) {
                $u = 'https:' . $u;
            }
            if (!preg_match('~^https?://~i', $u)) {
                continue;
            }
            $network = self::detect($u);
            if ($network === null || isset($out[$network]) || $network === 'mastodon') {
                continue;
            }
            $path = (string) parse_url($u, PHP_URL_PATH);
            if (trim($path, '/') === '' || preg_match($skip, $path . '/')) {
                continue;
            }
            $out[$network] = ['network' => $network, 'url' => strtok($u, '?#') ?: $u, 'available' => isset(self::NETWORKS[$network])];
        }
        $order = array_flip(array_merge(array_keys(self::NETWORKS), array_keys(self::UNAVAILABLE)));
        uasort($out, static fn (array $a, array $b): int => ($order[$a['network']] ?? 99) <=> ($order[$b['network']] ?? 99));
        return array_values($out);
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
            'instagram' => self::instagram($input, $user),
            'facebook' => self::facebook($input, $user),
            'threads' => self::threads($input, $user),
            'mastodon' => self::mastodon($input),
            default => throw new CompetitorError('Competitors on this network aren’t supported.'),
        };
    }

    /** The network a pasted link or handle belongs to, or null when it can't be told. */
    public static function detect(string $input): ?string
    {
        $input = trim($input);
        if (preg_match('/^@?[a-z0-9_.]+@[a-z0-9.-]+\.[a-z]{2,}$/i', $input)) {
            return 'mastodon'; // user@instance
        }
        if (preg_match('~^(UC[A-Za-z0-9_-]{22})$~', $input)) {
            return 'youtube';
        }
        $url = preg_match('~^https?://~i', $input) ? $input : (str_contains($input, '/') && str_contains($input, '.') ? 'https://' . $input : '');
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^(www\.|m\.|mobile\.)/', '', $host) ?? $host;
        if ($host === '') {
            return str_ends_with(strtolower($input), '.bsky.social') ? 'bluesky' : null;
        }
        $map = [
            'youtube.com' => 'youtube', 'youtu.be' => 'youtube',
            'instagram.com' => 'instagram',
            'facebook.com' => 'facebook', 'fb.com' => 'facebook',
            'threads.net' => 'threads', 'threads.com' => 'threads',
            'bsky.app' => 'bluesky',
            'tiktok.com' => 'tiktok', 'linkedin.com' => 'linkedin',
            'x.com' => 'x', 'twitter.com' => 'x',
        ];
        if (isset($map[$host])) {
            return $map[$host];
        }
        if (preg_match('/(^|\.)pinterest\.[a-z.]+$/', $host) || $host === 'pin.it') {
            return 'pinterest';
        }
        if (preg_match('~^/@[A-Za-z0-9_.]+/?$~', (string) parse_url($url, PHP_URL_PATH))) {
            return 'mastodon'; // https://instance/@user
        }
        return null;
    }

    /** Mastodon (any server): public account lookup and recent posts, no sign-in needed. */
    private static function mastodon(string $input): array
    {
        $input = trim($input);
        if (preg_match('~^https?://([a-z0-9.-]+\.[a-z]{2,})/@([A-Za-z0-9_.]+)/?$~i', $input, $m)) {
            [$server, $user] = [strtolower($m[1]), $m[2]];
        } elseif (preg_match('/^@?([A-Za-z0-9_.]+)@([a-z0-9.-]+\.[a-z]{2,})$/i', $input, $m)) {
            [$server, $user] = [strtolower($m[2]), $m[1]];
        } else {
            throw new CompetitorError('Enter a Mastodon account like @name@mastodon.social, or its profile link.');
        }
        $base = 'https://' . $server . '/api/v1/';
        try {
            $a = self::get($base . 'accounts/lookup?' . http_build_query(['acct' => $user]));
        } catch (ChannelApiException $e) {
            if ($e->status === 404) {
                throw new CompetitorError('No Mastodon account @' . $user . ' on ' . $server . '.');
            }
            throw $e;
        }
        $id = (string) ($a['id'] ?? '');
        if ($id === '' || !preg_match('/^[0-9]+$/', $id)) {
            throw new CompetitorError('No Mastodon account @' . $user . ' on ' . $server . '.');
        }
        $posts = [];
        foreach (self::get($base . 'accounts/' . $id . '/statuses?' . http_build_query(['limit' => self::POSTS, 'exclude_replies' => 'true', 'exclude_reblogs' => 'true'])) as $st) {
            $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br />', '</p>'], "\n", (string) ($st['content'] ?? ''))), ENT_QUOTES, 'UTF-8');
            $posts[] = self::post((string) ($st['id'] ?? ''), (string) ($st['created_at'] ?? ''), $text, (string) ($st['url'] ?? ''), !empty($st['media_attachments']) && ($st['media_attachments'][0]['type'] ?? '') === 'video' ? 'video' : 'post',
                $st['favourites_count'] ?? null, $st['replies_count'] ?? null, $st['reblogs_count'] ?? null, null);
        }
        return [
            'external_id' => $server . ':' . $id,
            'handle' => '@' . $user . '@' . $server,
            'name' => (string) (trim((string) ($a['display_name'] ?? '')) !== '' ? $a['display_name'] : $user),
            'avatar' => null,
            'url' => (string) ($a['url'] ?? 'https://' . $server . '/@' . $user),
            'followers' => isset($a['followers_count']) ? (int) $a['followers_count'] : null,
            'posts_total' => isset($a['statuses_count']) ? (int) $a['statuses_count'] : null,
            'posts' => array_values(array_filter($posts)),
        ];
    }

    /**
     * Facebook Page by link, username or ID, read with a connected Page's token.
     * Reading Pages you don't manage needs Meta's Page Public Content Access feature on the app.
     */
    private static function facebook(string $input, array $user): array
    {
        $path = trim((string) parse_url(preg_match('~^https?://~i', $input) ? $input : 'https://facebook.com/' . ltrim($input, '@/'), PHP_URL_PATH), '/');
        parse_str((string) parse_url($input, PHP_URL_QUERY), $q);
        $page = isset($q['id']) && preg_match('/^[0-9]+$/', (string) $q['id']) ? (string) $q['id'] : (string) strtok($path, '/');
        if ($page === 'pages') { // facebook.com/pages/Name/123
            $page = (string) preg_replace('~^.*/([0-9]{6,})$~', '$1', $path);
        } elseif (preg_match('/-([0-9]{8,})$/', $page, $m)) { // facebook.com/Name-123456789
            $page = $m[1];
        }
        if (preg_match('/^profile\.php$/', $page) || !preg_match('/^[A-Za-z0-9.\-]{2,100}$/', $page)) {
            throw new CompetitorError('Enter a Facebook Page link like facebook.com/pagename.');
        }
        $tokens = self::pageTokens((int) $user['id']);
        $app = config('oauth.facebook');
        if (($app['client_id'] ?? '') !== '' && ($app['client_secret'] ?? '') !== '') {
            $tokens[] = ['app', $app['client_id'] . '|' . $app['client_secret']]; // App token: works for public Pages once Page Public Content Access is approved.
        }
        if (!$tokens) {
            throw new CompetitorError('Connect a Facebook Page so atwebtech can read other public Pages.', 'facebook');
        }
        $fields = 'id,name,username,link,picture{url},followers_count,fan_count,posts.limit(' . self::POSTS . '){id,created_time,message,permalink_url,shares,reactions.summary(total_count).limit(0),comments.summary(total_count).limit(0)}';
        $d = null;
        $last = null;
        foreach ($tokens as [, $token]) {
            try {
                $d = self::get(self::GRAPH . '/' . rawurlencode($page) . '?' . http_build_query(['fields' => $fields, 'access_token' => $token]));
                break;
            } catch (ChannelApiException $e) {
                $last = $e;
            }
        }
        if ($d === null && $last !== null) {
            $e = $last;
            $msg = $last->getMessage();
            if (str_contains($msg, 'Page Public Content Access') || str_contains($msg, 'pages_read_engagement') || str_contains($msg, 'missing permission')) {
                throw new CompetitorError('Facebook Pages can’t be followed yet: atwebtech is waiting for Meta to approve reading public Pages. Try Instagram, YouTube or another network for now.');
            }
            if ($e->status === 404 || (str_contains($msg, 'does not exist') && !str_contains($msg, 'missing permission'))) {
                throw new CompetitorError('No public Facebook Page found for “' . $input . '”.');
            }
            throw $e;
        }
        $posts = [];
        foreach ($d['posts']['data'] ?? [] as $p) {
            $posts[] = self::post((string) $p['id'], (string) ($p['created_time'] ?? ''), (string) ($p['message'] ?? ''), (string) ($p['permalink_url'] ?? ''), 'post',
                $p['reactions']['summary']['total_count'] ?? null, $p['comments']['summary']['total_count'] ?? null, $p['shares']['count'] ?? 0, null);
        }
        $followers = $d['followers_count'] ?? ($d['fan_count'] ?? null);
        return [
            'external_id' => (string) $d['id'],
            'handle' => (string) ($d['username'] ?? $d['id']),
            'name' => (string) ($d['name'] ?? $page),
            'avatar' => $d['picture']['data']['url'] ?? null,
            'url' => (string) ($d['link'] ?? 'https://www.facebook.com/' . $d['id']),
            'followers' => $followers !== null ? (int) $followers : null,
            'posts_total' => null,
            'posts' => array_values(array_filter($posts)),
        ];
    }

    /**
     * Threads Profile Discovery, read with the user's connected Threads profile.
     * Needs the threads_profile_discovery permission approved for the app.
     */
    private static function threads(string $input, array $user): array
    {
        $username = (string) preg_replace('~^(?:https?://)?(?:www\.)?threads\.(?:net|com)/@?~i', '', trim($input));
        $username = strtolower(ltrim((string) strtok($username, '/?#'), '@'));
        if (!preg_match('/^[a-z0-9._]{1,30}$/', $username)) {
            throw new CompetitorError('Enter a Threads username like @name, or a threads.com profile link.');
        }
        $token = self::tokens((int) $user['id'], 'threads')[0][1] ?? null;
        if ($token === null) {
            throw new CompetitorError('Connect your Threads profile so atwebtech can look up other Threads profiles.', 'threads');
        }
        $base = 'https://graph.threads.net/v1.0/';
        try {
            try {
                $p = self::get($base . 'profile_lookup?' . http_build_query(['username' => $username, 'fields' => 'id,username,name,threads_profile_picture_url,follower_count', 'access_token' => $token]));
            } catch (ChannelApiException $e) {
                if ($e->status !== 400 || !str_contains($e->getMessage(), 'field')) {
                    throw $e;
                }
                $p = self::get($base . 'profile_lookup?' . http_build_query(['username' => $username, 'fields' => 'id,username,name,threads_profile_picture_url', 'access_token' => $token]));
            }
            $feed = self::get($base . 'profile_posts?' . http_build_query(['username' => $username, 'fields' => 'id,text,timestamp,permalink,media_type', 'limit' => 25, 'access_token' => $token]));
        } catch (ChannelApiException $e) {
            if (in_array($e->status, [400, 403], true) && (str_contains($e->getMessage(), 'ermission') || str_contains($e->getMessage(), 'not authorized'))) {
                throw new CompetitorError('Threads profiles can’t be followed yet: atwebtech is waiting for Meta to approve Threads profile lookups. Try Instagram, YouTube or another network for now.');
            }
            if ($e->status === 400 || $e->status === 404) {
                throw new CompetitorError('No public Threads profile found for @' . $username . '.');
            }
            throw $e;
        }
        $posts = [];
        foreach ($feed['data'] ?? [] as $t) {
            $posts[] = self::post((string) $t['id'], (string) ($t['timestamp'] ?? ''), (string) ($t['text'] ?? ''), (string) ($t['permalink'] ?? 'https://www.threads.com/@' . $username), strtolower((string) ($t['media_type'] ?? 'post')), null, null, null, null);
        }
        return [
            'external_id' => (string) ($p['id'] ?? $username),
            'handle' => '@' . ($p['username'] ?? $username),
            'name' => (string) (($p['name'] ?? '') !== '' ? $p['name'] : $username),
            'avatar' => $p['threads_profile_picture_url'] ?? null,
            'url' => 'https://www.threads.com/@' . ($p['username'] ?? $username),
            'followers' => isset($p['follower_count']) ? (int) $p['follower_count'] : null,
            'posts_total' => null,
            'posts' => array_values(array_filter($posts)),
        ];
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
        } elseif (preg_match('~youtube\.com/user/([A-Za-z0-9._-]{2,100})~i', $input, $m)) {
            $params['forUsername'] = $m[1]; // Legacy /user/ links.
        } elseif (preg_match('~(?:youtube\.com/)?@([A-Za-z0-9._-]{3,100})~', $input, $m) || preg_match('~youtube\.com/(?:c/)?([A-Za-z0-9._-]{3,100})/?(?:[?#].*)?$~i', $input, $m) || preg_match('~^([A-Za-z0-9._-]{3,100})$~', $input, $m)) {
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
        throw new CompetitorError('Instagram lookups need a Facebook Page linked to an Instagram professional account. Link your Page and Instagram in Meta Business Suite, then connect the Page here.', 'facebook');
    }

    /** @return list<array{0:string,1:string}> page id and page token for each connected Facebook Page */
    private static function pageTokens(int $uid): array
    {
        $out = [];
        foreach (self::tokens($uid, 'facebook') as [$channel, $token]) {
            $out[] = [(string) $channel['external_id'], $token];
        }
        return $out;
    }

    /**
     * Usable tokens for a network: the user's own connections first, then the site admins'
     * (ADMIN_EMAILS), so public lookups work for people who haven't connected that network.
     * @return list<array{0:array,1:string}>
     */
    private static function tokens(int $uid, string $provider): array
    {
        $owners = [$uid];
        $emails = array_filter(array_map(static fn (string $e): string => strtolower(trim($e)), explode(',', (string) config('admin.emails'))));
        if ($emails) {
            $stmt = Database::connection()->prepare('SELECT id FROM users WHERE LOWER(email) IN (' . implode(',', array_fill(0, count($emails), '?')) . ')');
            $stmt->execute(array_values($emails));
            $owners = array_values(array_unique(array_merge($owners, array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)))));
        }
        $out = [];
        foreach ($owners as $owner) {
            foreach (Channels::forUser($owner) as $c) {
                if ($c['provider'] !== $provider) {
                    continue;
                }
                $full = Channels::find($owner, (int) $c['id']);
                try {
                    $out[] = [$full, Channels::accessToken($full)];
                } catch (Throwable) {
                }
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
    /** @param string|null $connect a network the user can connect to fix this */
    public function __construct(string $message, public readonly ?string $connect = null)
    {
        parent::__construct($message);
    }
}
