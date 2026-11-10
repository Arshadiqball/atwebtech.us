<?php

declare(strict_types=1);

/** Saved hashtag groups, inserted from the composer's # button. */
final class Hashtags
{
    public const FREE_GROUPS = 3;
    public const MAX_GROUPS = 100;
    public const MAX_TAGS = 30;

    public static function groups(int $uid): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM hashtag_groups WHERE user_id = :uid ORDER BY name');
        $stmt->execute(['uid' => $uid]);
        return $stmt->fetchAll();
    }

    public static function limit(int $uid): int
    {
        return Channels::allowance($uid)['paid'] ? self::MAX_GROUPS : self::FREE_GROUPS;
    }

    /** "#a #b" from any mix of words, commas and hashes; letters, numbers and underscores only. */
    public static function normalize(string $raw): string
    {
        preg_match_all('/#?([\p{L}\p{N}_]{1,60})/u', $raw, $m);
        $seen = [];
        foreach ($m[1] as $tag) {
            $seen[mb_strtolower($tag)] ??= '#' . $tag;
        }
        return implode(' ', array_slice(array_values($seen), 0, self::MAX_TAGS));
    }

    /** POST /app/hashtags */
    public static function handle(): never
    {
        $uid = (int) Auth::member()['id'];
        $pdo = Database::connection();
        $back = '/app/create?tab=hashtags';
        $id = (int) ($_POST['id'] ?? 0);
        if (($_POST['action'] ?? '') === 'delete') {
            $pdo->prepare('DELETE FROM hashtag_groups WHERE id = :id AND user_id = :uid')->execute(['id' => $id, 'uid' => $uid]);
            Toolkit::done($back, 'Hashtag group deleted.');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $tags = self::normalize((string) ($_POST['tags'] ?? ''));
        $old = ['name' => $name, 'tags' => (string) ($_POST['tags'] ?? ''), 'id' => $id];
        if ($name === '' || mb_strlen($name) > 60) {
            Toolkit::fail('hashtags', $back, 'Give the group a name of up to 60 characters.', ['name' => 'Add a name.'], $old);
        }
        if ($tags === '') {
            Toolkit::fail('hashtags', $back, 'Add at least one hashtag.', ['tags' => 'Add hashtags, for example #smallbusiness #marketing.'], $old);
        }
        $now = Database::now();
        if ($id > 0) {
            $pdo->prepare('UPDATE hashtag_groups SET name = :name, tags = :tags, updated_at = :now WHERE id = :id AND user_id = :uid')
                ->execute(['name' => $name, 'tags' => $tags, 'now' => $now, 'id' => $id, 'uid' => $uid]);
            Toolkit::done($back, 'Hashtag group saved.');
        }
        if (count(self::groups($uid)) >= self::limit($uid)) {
            Toolkit::fail('hashtags', $back, sprintf('The Free plan holds %d hashtag groups. Upgrade for unlimited groups, or edit an existing one.', self::FREE_GROUPS), [], $old);
        }
        $pdo->prepare('INSERT INTO hashtag_groups (user_id, name, tags, created_at, updated_at) VALUES (:uid, :name, :tags, :now, :now2)')
            ->execute(['uid' => $uid, 'name' => $name, 'tags' => $tags, 'now' => $now, 'now2' => $now]);
        Toolkit::done($back, 'Hashtag group added. Insert it from the # button in the composer.');
    }
}

/** RSS and Atom feeds: new articles become ready-to-share post ideas. */
final class Feeds
{
    public const FREE_FEEDS = 3;
    public const MAX_FEEDS = 25;
    private const MAX_BYTES = 2_000_000;
    private const KEEP_ITEMS = 30;

    public static function list(int $uid): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM feeds WHERE user_id = :uid ORDER BY title');
        $stmt->execute(['uid' => $uid]);
        return $stmt->fetchAll();
    }

    public static function items(int $uid, int $feedId = 0): array
    {
        $sql = "SELECT i.*, f.title AS feed_title FROM feed_items i JOIN feeds f ON f.id = i.feed_id WHERE i.user_id = :uid AND i.status <> 'dismissed'";
        $params = ['uid' => $uid];
        if ($feedId > 0) {
            $sql .= ' AND i.feed_id = :fid';
            $params['fid'] = $feedId;
        }
        $stmt = Database::connection()->prepare($sql . ' ORDER BY COALESCE(i.published_at, i.created_at) DESC, i.id DESC LIMIT 60');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function limit(int $uid): int
    {
        return Channels::allowance($uid)['paid'] ? self::MAX_FEEDS : self::FREE_FEEDS;
    }

    /** POST /app/feeds */
    public static function handle(): never
    {
        $user = Auth::member();
        $uid = (int) $user['id'];
        $pdo = Database::connection();
        $back = '/app/create?tab=feeds';
        $action = (string) ($_POST['action'] ?? 'add');
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'remove') {
            $pdo->prepare('DELETE FROM feeds WHERE id = :id AND user_id = :uid')->execute(['id' => $id, 'uid' => $uid]);
            $pdo->prepare('DELETE FROM feed_items WHERE feed_id = :id AND user_id = :uid')->execute(['id' => $id, 'uid' => $uid]);
            Toolkit::done($back, 'Feed removed.');
        }
        if ($action === 'dismiss' || $action === 'used') {
            $pdo->prepare('UPDATE feed_items SET status = :s WHERE id = :id AND user_id = :uid')->execute(['s' => $action === 'used' ? 'used' : 'dismissed', 'id' => $id, 'uid' => $uid]);
            Toolkit::done($back, $action === 'used' ? 'Marked as shared.' : 'Article hidden.');
        }
        if ($action === 'idea') {
            $stmt = $pdo->prepare('SELECT * FROM feed_items WHERE id = :id AND user_id = :uid');
            $stmt->execute(['id' => $id, 'uid' => $uid]);
            $item = $stmt->fetch();
            if (!$item) {
                Toolkit::done($back, 'That article is no longer here.');
            }
            $now = Database::now();
            $pdo->prepare('INSERT INTO ideas (user_id, group_id, title, body, tags, position, created_at, updated_at) VALUES (:uid, NULL, :title, :body, :tags, 0, :c, :u)')
                ->execute(['uid' => $uid, 'title' => mb_substr((string) $item['title'], 0, 140), 'body' => trim(($item['summary'] ? $item['summary'] . "\n\n" : '') . $item['link']), 'tags' => 'feed', 'c' => $now, 'u' => $now]);
            $pdo->prepare("UPDATE feed_items SET status = 'used' WHERE id = :id")->execute(['id' => $id]);
            Toolkit::done($back, 'Saved to your ideas under Create → Ideas.');
        }
        if ($action === 'refresh') {
            $errors = 0;
            foreach (self::list($uid) as $feed) {
                $errors += self::refresh($feed) ? 0 : 1;
            }
            Toolkit::done($back, $errors ? 'Feeds refreshed. Some feeds could not be reached; see the note on each.' : 'Feeds refreshed.');
        }

        $url = trim((string) ($_POST['url'] ?? ''));
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url) || strlen($url) > 500) {
            Toolkit::fail('feeds', $back, 'Enter a website or feed address, such as https://blog.example.com.', ['url' => 'Enter a full web address.'], ['url' => $url]);
        }
        if (count(self::list($uid)) >= self::limit($uid)) {
            Toolkit::fail('feeds', $back, sprintf('The Free plan follows %d feeds. Upgrade to follow more, or remove one first.', self::FREE_FEEDS), [], ['url' => $url]);
        }
        try {
            [$feedUrl, $xml] = self::discover($url);
            $parsed = self::parse($xml);
        } catch (Throwable $e) {
            Toolkit::fail('feeds', $back, 'We could not find a feed there. Try the blog\'s home page or its RSS link.', ['url' => mb_substr($e->getMessage(), 0, 160)], ['url' => $url]);
        }
        $dupe = $pdo->prepare('SELECT 1 FROM feeds WHERE user_id = :uid AND url = :url');
        $dupe->execute(['uid' => $uid, 'url' => $feedUrl]);
        if ($dupe->fetchColumn()) {
            Toolkit::done($back, 'You already follow that feed.');
        }
        $now = Database::now();
        $pdo->prepare('INSERT INTO feeds (user_id, url, title, site_url, last_checked_at, created_at, updated_at) VALUES (:uid, :url, :title, :site, :now, :c, :u)')
            ->execute(['uid' => $uid, 'url' => $feedUrl, 'title' => mb_substr($parsed['title'] ?: (string) parse_url($feedUrl, PHP_URL_HOST), 0, 200), 'site' => $parsed['site'], 'now' => $now, 'c' => $now, 'u' => $now]);
        $feedId = (int) $pdo->lastInsertId();
        self::store($feedId, $uid, $parsed['items']);
        Toolkit::done($back, sprintf('Following %s. %d recent articles are ready to share.', $parsed['title'] ?: 'the feed', min(count($parsed['items']), self::KEEP_ITEMS)));
    }

    /** Fetch one feed and add new articles. Returns false on failure (the error is stored on the feed). */
    public static function refresh(array $feed): bool
    {
        $pdo = Database::connection();
        try {
            $parsed = self::parse(self::fetch((string) $feed['url']));
            self::store((int) $feed['id'], (int) $feed['user_id'], $parsed['items']);
            $pdo->prepare('UPDATE feeds SET last_checked_at = :now, last_error = NULL, updated_at = :u WHERE id = :id')->execute(['now' => Database::now(), 'u' => Database::now(), 'id' => $feed['id']]);
            return true;
        } catch (Throwable $e) {
            $pdo->prepare('UPDATE feeds SET last_checked_at = :now, last_error = :err WHERE id = :id')->execute(['now' => Database::now(), 'err' => mb_substr($e->getMessage(), 0, 300), 'id' => $feed['id']]);
            return false;
        }
    }

    /** All feeds, oldest check first. For bin/feeds.php. */
    public static function refreshAll(int $limit = 200): array
    {
        $rows = Database::connection()->query('SELECT * FROM feeds ORDER BY COALESCE(last_checked_at, created_at) LIMIT ' . max(1, $limit))->fetchAll();
        $ok = 0;
        foreach ($rows as $feed) {
            $ok += self::refresh($feed) ? 1 : 0;
        }
        return ['checked' => count($rows), 'ok' => $ok];
    }

    private static function store(int $feedId, int $uid, array $items): void
    {
        $pdo = Database::connection();
        $insert = $pdo->prepare('INSERT INTO feed_items (feed_id, user_id, guid_hash, title, link, summary, published_at, status, created_at) VALUES (:f, :u, :g, :t, :l, :s, :p, :st, :c)');
        $exists = $pdo->prepare('SELECT 1 FROM feed_items WHERE feed_id = :f AND guid_hash = :g');
        foreach (array_slice($items, 0, self::KEEP_ITEMS) as $it) {
            $hash = hash('sha256', $it['guid']);
            $exists->execute(['f' => $feedId, 'g' => $hash]);
            if ($exists->fetchColumn()) {
                continue;
            }
            $insert->execute(['f' => $feedId, 'u' => $uid, 'g' => $hash, 't' => $it['title'], 'l' => $it['link'], 's' => $it['summary'] ?: null, 'p' => $it['published'], 'st' => 'new', 'c' => Database::now()]);
        }
        // Keep the newest items per feed only.
        $old = $pdo->prepare('SELECT id FROM feed_items WHERE feed_id = :f ORDER BY COALESCE(published_at, created_at) DESC, id DESC');
        $old->execute(['f' => $feedId]);
        $ids = array_slice($old->fetchAll(PDO::FETCH_COLUMN), self::KEEP_ITEMS * 2);
        if ($ids) {
            $pdo->exec('DELETE FROM feed_items WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')');
        }
    }

    /** @return array{0: string, 1: string} feed URL and its XML; follows <link rel="alternate"> on a web page. */
    private static function discover(string $url): array
    {
        $body = self::fetch($url);
        if (preg_match('/<(rss|feed|rdf:RDF)\b/i', substr($body, 0, 3000))) {
            return [$url, $body];
        }
        if (preg_match_all('/<link\b[^>]*>/i', $body, $links)) {
            foreach ($links[0] as $tag) {
                if (preg_match('/type=["\']application\/(rss|atom)\+xml["\']/i', $tag) && preg_match('/href=["\']([^"\']+)["\']/i', $tag, $h)) {
                    $href = html_entity_decode($h[1], ENT_QUOTES);
                    if (str_starts_with($href, '//')) {
                        $href = (string) parse_url($url, PHP_URL_SCHEME) . ':' . $href;
                    } elseif (!preg_match('#^https?://#i', $href)) {
                        $p = parse_url($url);
                        $href = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '') . '/' . ltrim($href, '/');
                    }
                    return [$href, self::fetch($href)];
                }
            }
        }
        foreach (['/feed', '/rss', '/feed.xml', '/rss.xml', '/atom.xml'] as $guess) {
            $p = parse_url($url);
            $try = $p['scheme'] . '://' . $p['host'] . $guess;
            try {
                $xml = self::fetch($try);
                if (preg_match('/<(rss|feed|rdf:RDF)\b/i', substr($xml, 0, 3000))) {
                    return [$try, $xml];
                }
            } catch (Throwable) {
            }
        }
        throw new RuntimeException('No RSS or Atom feed found on that page.');
    }

    /** @return array{title: string, site: ?string, items: list<array{guid: string, title: string, link: string, summary: string, published: ?string}>} */
    private static function parse(string $xmlText): array
    {
        $xml = simplexml_load_string($xmlText, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOCDATA);
        if ($xml === false) {
            throw new RuntimeException('The feed is not valid XML.');
        }
        $clean = static fn (string $s, int $n): string => mb_substr(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? ''), 0, $n);
        $date = static function (string $s): ?string {
            $t = strtotime($s);
            return $t ? gmdate('Y-m-d H:i:s', $t) : null;
        };
        $safe = static fn (string $u): string => preg_match('#^https?://#i', $u) ? mb_substr($u, 0, 500) : '';
        $items = [];
        if (isset($xml->channel)) { // RSS 2.0
            foreach ($xml->channel->item as $it) {
                $link = $safe(trim((string) $it->link));
                if ($link === '') {
                    continue;
                }
                $items[] = ['guid' => (string) ($it->guid ?: $link), 'title' => $clean((string) $it->title, 300) ?: $link, 'link' => $link, 'summary' => $clean((string) $it->description, 280), 'published' => $date((string) $it->pubDate)];
            }
            return ['title' => $clean((string) $xml->channel->title, 200), 'site' => $safe((string) $xml->channel->link) ?: null, 'items' => $items];
        }
        $ns = $xml->getNamespaces(true);
        $entries = $xml->entry ?? [];
        foreach ($entries as $e) { // Atom
            $link = '';
            foreach ($e->link as $l) {
                $rel = (string) ($l['rel'] ?? 'alternate');
                if ($rel === 'alternate' || $link === '') {
                    $link = $safe((string) $l['href']);
                }
            }
            if ($link === '') {
                continue;
            }
            $items[] = ['guid' => (string) ($e->id ?: $link), 'title' => $clean((string) $e->title, 300) ?: $link, 'link' => $link, 'summary' => $clean((string) ($e->summary ?: $e->content), 280), 'published' => $date((string) ($e->published ?: $e->updated))];
        }
        if ($items || isset($xml->entry) || str_contains($xmlText, '<feed')) {
            $site = '';
            foreach ($xml->link as $l) {
                if ((string) ($l['rel'] ?? 'alternate') === 'alternate') {
                    $site = $safe((string) $l['href']);
                }
            }
            return ['title' => $clean((string) $xml->title, 200), 'site' => $site ?: null, 'items' => $items];
        }
        throw new RuntimeException('That is not an RSS or Atom feed.');
    }

    /** GET with SSRF protection: public hosts only, http(s), size and time limits, up to 3 checked redirects. */
    public static function fetch(string $url): string
    {
        for ($hop = 0; $hop < 4; $hop++) {
            $host = (string) parse_url($url, PHP_URL_HOST);
            if (!preg_match('#^https?://#i', $url) || $host === '') {
                throw new RuntimeException('Only http and https addresses are allowed.');
            }
            $ips = gethostbynamel($host) ?: [];
            if ($ips === []) {
                throw new RuntimeException('That website could not be found.');
            }
            foreach ($ips as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    throw new RuntimeException('That address is not allowed.');
                }
            }
            $body = '';
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_RESOLVE => [$host . ':443:' . $ips[0], $host . ':80:' . $ips[0]],
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; atwebtech-feeds/1.0; +https://atwebtech.us)',
                CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, application/atom+xml, application/xml, text/xml, text/html;q=0.8'],
                CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$body): int {
                    $body .= $chunk;
                    return strlen($body) > self::MAX_BYTES ? 0 : strlen($chunk);
                },
            ]);
            curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $next = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            if ($status >= 300 && $status < 400 && $next !== '') {
                $url = $next;
                continue;
            }
            if ($status !== 200 || $body === '') {
                throw new RuntimeException('The site answered with HTTP ' . $status . '.');
            }
            return $body;
        }
        throw new RuntimeException('Too many redirects.');
    }
}

/** Link tracking: UTM parameters added to links when a post is published. */
final class Utm
{
    public static function settings(int $uid): array
    {
        $stmt = Database::connection()->prepare('SELECT utm_tracking_enabled, utm_tracking_campaign FROM users WHERE id = :id');
        $stmt->execute(['id' => $uid]);
        $row = $stmt->fetch() ?: [];
        return ['enabled' => !empty($row['utm_tracking_enabled']), 'campaign' => (string) ($row['utm_tracking_campaign'] ?? '')];
    }

    /** Adds utm_source/medium/campaign/content to every http(s) link that has no UTM tags yet. */
    public static function apply(string $text, string $network, string $campaign, int $postId): string
    {
        $campaign = trim($campaign) !== '' ? $campaign : 'atwebtech';
        return (string) preg_replace_callback('#https?://[^\s<>"\']+#i', static function (array $m) use ($network, $campaign, $postId): string {
            $url = rtrim($m[0], '.,;:!?)');
            $trail = substr($m[0], strlen($url));
            if (stripos($url, 'utm_') !== false) {
                return $m[0];
            }
            $frag = '';
            if (($p = strpos($url, '#')) !== false) {
                $frag = substr($url, $p);
                $url = substr($url, 0, $p);
            }
            $q = http_build_query(['utm_source' => $network, 'utm_medium' => 'social', 'utm_campaign' => $campaign, 'utm_content' => 'post-' . $postId]);
            return $url . (str_contains($url, '?') ? '&' : '?') . $q . $frag . $trail;
        }, $text);
    }

    /** POST /app/account/utm */
    public static function save(): never
    {
        $user = Auth::member();
        $uid = (int) $user['id'];
        $enabled = !empty($_POST['utm_enabled']);
        if ($enabled && !Channels::allowance($uid)['paid']) {
            Toolkit::done('/app/account#utm', 'Link tracking is part of Essentials and Team. Upgrade under Plan & billing to turn it on.');
        }
        $campaign = mb_substr(trim((string) preg_replace('/[^\p{L}\p{N}_\- ]/u', '', (string) ($_POST['utm_campaign'] ?? ''))), 0, 80);
        Database::connection()->prepare('UPDATE users SET utm_tracking_enabled = :e, utm_tracking_campaign = :c WHERE id = :id')
            ->execute(['e' => $enabled ? 1 : 0, 'c' => $campaign !== '' ? $campaign : null, 'id' => $uid]);
        Toolkit::done('/app/account#utm', $enabled ? 'Link tracking is on. New posts get UTM tags on their links when they publish.' : 'Link tracking is off.');
    }
}

/** Shared redirects for the workspace tools. */
final class Toolkit
{
    public static function done(string $location, string $message): never
    {
        if (wants_json()) {
            json_response(['status' => 'success', 'message' => $message]);
        }
        flash('notice', $message);
        redirect($location);
    }

    public static function fail(string $form, string $location, string $message, array $errors = [], array $old = []): never
    {
        if (wants_json()) {
            json_response(['status' => 'error', 'message' => $message, 'errors' => $errors], 422);
        }
        flash('form', ['form' => $form, 'status' => 'error', 'message' => $message, 'errors' => $errors, 'old' => $old]);
        redirect($location);
    }
}

/** Insights exports (CSV and a printable report) and the Campaigns breakdown by post tag. */
final class Reports
{
    /** Posts per tag (campaign) in a date range, across all statuses. */
    public static function campaigns(int $uid, string $fromUtc, string $toUtc): array
    {
        $stmt = Database::connection()->prepare("SELECT channel, status, tags FROM posts WHERE user_id = :uid AND tags IS NOT NULL AND tags <> ''
            AND (COALESCE(sent_at, planned_at, updated_at) BETWEEN :f AND :t
                 OR (status IN ('planned', 'publishing', 'review') AND planned_at > :t2))");
        $stmt->execute(['uid' => $uid, 'f' => $fromUtc, 't' => $toUtc, 't2' => $toUtc]);
        $out = [];
        foreach ($stmt->fetchAll() as $p) {
            foreach (array_filter(array_map('trim', explode(',', (string) $p['tags']))) as $tag) {
                $key = mb_strtolower($tag);
                $out[$key] ??= ['name' => $tag, 'sent' => 0, 'scheduled' => 0, 'failed' => 0, 'drafts' => 0, 'networks' => []];
                $col = match ($p['status']) { 'sent' => 'sent', 'planned', 'publishing', 'review' => 'scheduled', 'failed' => 'failed', default => 'drafts' };
                $out[$key][$col]++;
                $out[$key]['networks'][$p['channel']] = true;
            }
        }
        uasort($out, static fn ($a, $b) => ($b['sent'] + $b['scheduled']) <=> ($a['sent'] + $a['scheduled']));
        return array_values($out);
    }

    /** GET /app/insights/export?range=…&format=csv|report */
    public static function export(): never
    {
        $user = Auth::require();
        $uid = (int) $user['id'];
        if (!Channels::allowance($uid)['paid']) {
            flash('notice', 'Exporting reports is part of Essentials and Team. See Plan & billing to upgrade.');
            redirect('/app/insights');
        }
        $range = Workspace::pick('range', ['30d', '7d', 'mtd', 'last-month', 'custom']);
        $channelId = (int) ($_GET['channel'] ?? 0);
        if ($channelId > 0 && Channels::find($uid, $channelId) === null) {
            $channelId = 0;
        }
        $in = Workspace::insights($user, $range, $channelId > 0 ? $channelId : null);
        $tz = Workspace::timezone($user);
        $accounts = array_column(Channels::forUser($uid), null, 'id');
        $names = array_column(integrations(), 'name', 'slug');
        $file = 'atwebtech-report-' . $in['from']->format('Y-m-d') . '-to-' . $in['to']->format('Y-m-d');
        if (($_GET['format'] ?? 'csv') === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $file . '.csv"');
            header('Cache-Control: no-store');
            $out = fopen('php://output', 'w');
            $row = static fn (array $r) => fputcsv($out, array_map(static fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $r), ',', '"', '');
            $row(array_merge(['published_' . $tz->getName(), 'network', 'channel', 'title', 'text', 'tags', 'link'], Metrics::FIELDS));
            foreach ($in['posts'] as $p) {
                $pm = $in['post_metrics'][$p['id']] ?? [];
                $row([
                    Workspace::local((string) $p['sent_at'], $tz)->format('Y-m-d H:i'),
                    $names[$p['channel']] ?? $p['channel'],
                    $accounts[(int) $p['channel_id']]['name'] ?? '',
                    (string) ($p['title'] ?? ''),
                    (string) $p['body'],
                    (string) ($p['tags'] ?? ''),
                    (string) ($p['external_url'] ?? ''),
                    ...array_map(static fn (string $f) => $pm[$f] ?? '', Metrics::FIELDS),
                ]);
            }
            fclose($out);
            exit;
        }
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, private');
        View::partial('reports/print', [
            'user' => $user, 'in' => $in, 'tz' => $tz, 'names' => $names, 'accounts' => $accounts, 'file' => $file,
            'campaigns' => self::campaigns($uid, $in['from']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'), $in['to']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')),
        ]);
        exit;
    }
}

/** Channel queue actions and bulk CSV scheduling (Channel settings → General). */
final class QueueTools
{
    public const MAX_ROWS = 200;
    /** Networks where a post can be text only. The others need a photo or video on every post. */
    public const TEXT_NETWORKS = ['linkedin', 'facebook', 'threads', 'tiktok'];

    /** POST /app/channels/{id}/queue-tools */
    public static function handle(string $id): never
    {
        $user = Auth::member();
        $uid = (int) $user['id'];
        $channel = ctype_digit($id) ? Channels::find($uid, (int) $id) : null;
        if ($channel === null) {
            not_found();
        }
        $cid = (int) $channel['id'];
        $back = '/app/channels/' . $cid . '/settings?tab=general';
        $pdo = Database::connection();
        $now = Database::now();
        switch ((string) ($_POST['action'] ?? '')) {
            case 'pause':
                $paused = !empty($_POST['paused']);
                $pdo->prepare('UPDATE channels SET paused_at = :p WHERE id = :id AND user_id = :uid')->execute(['p' => $paused ? $now : null, 'id' => $cid, 'uid' => $uid]);
                Toolkit::done($back, $paused ? 'Queue paused. Nothing on this channel will publish until you resume it.' : 'Queue resumed. Posts that came due while paused go out within a couple of minutes.');
            case 'shuffle':
                $stmt = $pdo->prepare("SELECT id, planned_at FROM posts WHERE user_id = :uid AND channel_id = :cid AND status = 'planned' AND planned_at > :now ORDER BY planned_at");
                $stmt->execute(['uid' => $uid, 'cid' => $cid, 'now' => $now]);
                $rows = $stmt->fetchAll();
                if (count($rows) < 2) {
                    Toolkit::done($back, 'There need to be at least 2 scheduled posts to shuffle.');
                }
                $times = array_column($rows, 'planned_at');
                $original = array_column($rows, 'id');
                $ids = $original;
                for ($try = 0; $try < 5 && $ids === $original; $try++) {
                    shuffle($ids);
                }
                if ($ids === $original) {
                    $ids[] = array_shift($ids); // always change the order
                }
                $upd = $pdo->prepare('UPDATE posts SET planned_at = :t, updated_at = :now WHERE id = :id AND user_id = :uid');
                foreach ($ids as $i => $postId) {
                    $upd->execute(['t' => $times[$i], 'now' => $now, 'id' => $postId, 'uid' => $uid]);
                }
                Toolkit::done($back, sprintf('Shuffled %d scheduled posts. The time slots stay the same.', count($ids)));
            case 'clean':
                $n = self::delete($uid, $cid, ['failed']);
                Toolkit::done($back, $n . ' failed ' . ($n === 1 ? 'post' : 'posts') . ' removed.');
            case 'drafts':
                $n = self::delete($uid, $cid, ['draft']);
                Toolkit::done($back, $n . ' ' . ($n === 1 ? 'draft' : 'drafts') . ' deleted.');
            case 'empty':
                $n = self::delete($uid, $cid, ['planned', 'review']);
                Toolkit::done($back, $n . ' scheduled ' . ($n === 1 ? 'post' : 'posts') . ' removed from the queue.');
            case 'bulk':
                self::bulk($user, $channel, $back);
        }
        Toolkit::done($back, 'Nothing changed.');
    }

    private static function delete(int $uid, int $cid, array $statuses): int
    {
        $in = implode(',', array_map(static fn ($s) => "'" . $s . "'", $statuses));
        $stmt = Database::connection()->prepare("DELETE FROM posts WHERE user_id = :uid AND channel_id = :cid AND status IN ($in)");
        $stmt->execute(['uid' => $uid, 'cid' => $cid]);
        return $stmt->rowCount();
    }

    /** CSV columns: date (YYYY-MM-DD), time (HH:MM, 24h), text, and optional title (YouTube) and tags. */
    private static function bulk(array $user, array $channel, string $back): never
    {
        if (!in_array($channel['provider'], self::TEXT_NETWORKS, true)) {
            Toolkit::fail('bulk', $back, 'Bulk upload works for text posts. This network needs a photo or video on every post, so add those from Create Post.');
        }
        $file = $_FILES['csv'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 1_000_000) {
            Toolkit::fail('bulk', $back, 'Choose a CSV file under 1 MB.');
        }
        $fh = fopen((string) $file['tmp_name'], 'r');
        $header = $fh ? fgetcsv($fh, 0, ',', '"', '') : false;
        if (!$header) {
            Toolkit::fail('bulk', $back, 'That file is empty or not a CSV.');
        }
        $header = array_map(static fn ($h) => strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        $col = array_flip($header);
        foreach (['date', 'time', 'text'] as $need) {
            if (!isset($col[$need])) {
                Toolkit::fail('bulk', $back, 'The first row must name the columns: date, time, text (and optionally title, tags).');
            }
        }
        $uid = (int) $user['id'];
        $tz = Workspace::timezone($user);
        $now = new DateTimeImmutable('now', $tz);
        $limit = (int) (data('workspace')['limits'][$channel['provider']] ?? 5000);
        $cap = Channels::scheduledCap($uid);
        $existing = Channels::scheduledCount($uid, (int) $channel['id']);
        $rows = [];
        $errors = [];
        $line = 1;
        while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $line++;
            if ($r === [null] || trim(implode('', $r)) === '') {
                continue;
            }
            $text = trim((string) ($r[$col['text']] ?? ''));
            $at = DateTimeImmutable::createFromFormat('!Y-m-d H:i', trim((string) ($r[$col['date']] ?? '')) . ' ' . trim((string) ($r[$col['time']] ?? '')), $tz);
            if ($text === '') {
                $errors[] = "Row $line: text is empty.";
            } elseif (mb_strlen($text) > $limit) {
                $errors[] = "Row $line: text is longer than $limit characters.";
            } elseif ($at === false) {
                $errors[] = "Row $line: use date as YYYY-MM-DD and time as HH:MM.";
            } elseif ($at <= $now) {
                $errors[] = "Row $line: the time is in the past.";
            } else {
                $rows[] = ['text' => $text, 'at' => $at, 'title' => isset($col['title']) ? mb_substr(trim((string) ($r[$col['title']] ?? '')), 0, 100) : '', 'tags' => isset($col['tags']) ? mb_substr(trim((string) ($r[$col['tags']] ?? '')), 0, 255) : ''];
            }
            if (count($rows) + count($errors) > self::MAX_ROWS) {
                $errors[] = 'Only the first ' . self::MAX_ROWS . ' rows are read.';
                break;
            }
        }
        if ($errors || !$rows) {
            Toolkit::fail('bulk', $back, $rows ? 'Nothing was scheduled. Fix these rows and upload again:' : 'No posts found in the file.', ['rows' => implode("\n", array_slice($errors, 0, 10))]);
        }
        if ($cap !== null && $existing + count($rows) > $cap) {
            Toolkit::fail('bulk', $back, sprintf('The Free plan allows %d scheduled posts per channel, and this channel has %d. Upload %d or fewer, or upgrade for unlimited scheduling.', $cap, $existing, max(0, $cap - $existing)));
        }
        $status = 'planned';
        $pdo = Database::connection();
        $ins = $pdo->prepare('INSERT INTO posts (user_id, channel, channel_id, body, title, tags, status, planned_at, created_at, updated_at) VALUES (:uid, :ch, :cid, :body, :title, :tags, :st, :at, :c, :u)');
        $pdo->beginTransaction();
        foreach ($rows as $r) {
            $n = Database::now();
            $ins->execute(['uid' => $uid, 'ch' => $channel['provider'], 'cid' => $channel['id'], 'body' => $r['text'], 'title' => $r['title'] !== '' ? $r['title'] : null, 'tags' => $r['tags'] !== '' ? $r['tags'] : null, 'st' => $status, 'at' => $r['at']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'c' => $n, 'u' => $n]);
        }
        $pdo->commit();
        Toolkit::done('/app/channels/' . (int) $channel['id'], sprintf('%d posts scheduled from your CSV.', count($rows)));
    }
}
