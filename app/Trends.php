<?php

declare(strict_types=1);

/**
 * Weekly snapshot of what is trending, for the composer's Trending list.
 *
 * Sources (all public, real data):
 * - YouTube: most popular videos in the US, by category (YouTube Data API).
 * - Hacker News: top stories of the past week, by topic (Algolia HN API).
 * - Reddit: top posts of the week in topic communities (public RSS).
 * - Google Trends: what people in the US are searching for right now (RSS).
 *
 * Facebook, Instagram, TikTok, Threads and LinkedIn don't publish trending data through an API,
 * so the composer shows these cross-web trends for them. bin/trends.php refreshes the data weekly.
 */
final class Trends
{
    public const SOURCES = [
        'youtube' => 'YouTube',
        'hackernews' => 'Hacker News',
        'reddit' => 'Reddit',
        'google' => 'Google Search',
    ];

    /**
     * Per interest: YouTube category (null = all), Hacker News search query, Reddit communities.
     * Interest keys match app/data/templates.php. '' is the "All interests" set.
     */
    private const MAP = [
        '' => ['yt' => null, 'hn' => '', 'reddit' => ['technology']],
        'ai' => ['yt' => '28', 'hn' => 'AI', 'reddit' => ['artificial', 'OpenAI']],
        'software' => ['yt' => '28', 'hn' => 'programming', 'reddit' => ['programming']],
        'web' => ['yt' => '28', 'hn' => 'web', 'reddit' => ['webdev']],
        'security' => ['yt' => '28', 'hn' => 'security', 'reddit' => ['cybersecurity']],
        'gadgets' => ['yt' => '28', 'hn' => 'hardware', 'reddit' => ['gadgets']],
        'cloud' => ['yt' => '28', 'hn' => 'cloud', 'reddit' => ['cloudcomputing', 'devops']],
        'startups' => ['yt' => '28', 'hn' => 'startup', 'reddit' => ['startups', 'Entrepreneur']],
        'productivity' => ['yt' => '26', 'hn' => 'tools', 'reddit' => ['productivity']],
    ];
    private const PER_SET = 8;
    private const USER_AGENT = 'Mozilla/5.0 (compatible; atwebtech-trends/1.0; +https://atwebtech.us)';

    /** Items for the composer: [interest][] rows, newest snapshot. */
    public static function forComposer(): array
    {
        try {
            $rows = Database::connection()->query('SELECT source, interest, title, summary, url, image_url, metric_label, published_at, fetched_at FROM trending_items ORDER BY interest, rank_order, id')->fetchAll();
        } catch (Throwable) {
            return [];
        }
        return $rows;
    }

    /** Most recent refresh time, or null when nothing has been fetched yet. */
    public static function updatedAt(): ?string
    {
        try {
            $at = Database::connection()->query('SELECT MAX(fetched_at) FROM trending_items')->fetchColumn();
        } catch (Throwable) {
            return null;
        }
        return is_string($at) && $at !== '' ? $at : null;
    }

    /**
     * Fetches every source and replaces each set that succeeded. A set that fails keeps last week's items.
     *
     * @return array<string, int|string> set name => items saved, or the error message
     */
    public static function refresh(?callable $log = null): array
    {
        $log ??= static fn (string $m) => null;
        $report = [];
        $save = static function (string $source, string $interest, callable $fetch) use (&$report, $log): void {
            $key = $source . ':' . ($interest !== '' ? $interest : 'all');
            try {
                $items = array_slice($fetch(), 0, self::PER_SET);
                if ($items === []) {
                    throw new RuntimeException('no items');
                }
                self::replace($source, $interest, $items);
                $report[$key] = count($items);
            } catch (Throwable $e) {
                $report[$key] = 'skipped: ' . mb_substr($e->getMessage(), 0, 120);
            }
            $log(sprintf('%-24s %s', $key, (string) $report[$key]));
        };

        $save('google', '', static fn () => self::google());
        $ytAccess = self::youtubeAccess();
        $ytCache = [];
        foreach (self::MAP as $interest => $cfg) {
            if ($ytAccess !== null) {
                $save('youtube', $interest, static function () use ($cfg, $ytAccess, &$ytCache): array {
                    $k = (string) $cfg['yt'];
                    return $ytCache[$k] ??= self::youtube($ytAccess, $cfg['yt']);
                });
            }
            $save('hackernews', $interest, static fn () => self::hackerNews($cfg['hn']));
            $save('reddit', $interest, static function () use ($cfg): array {
                $all = [];
                $error = null;
                foreach ($cfg['reddit'] as $sub) {
                    try {
                        $all = array_merge($all, self::reddit($sub));
                    } catch (Throwable $e) {
                        $error = $e;
                    }
                    sleep(8);
                }
                if ($all === [] && $error !== null) {
                    throw $error;
                }
                return $all;
            });
        }
        if ($ytAccess === null) {
            $report['youtube'] = 'skipped: set YOUTUBE_API_KEY or connect a YouTube channel';
            $log('youtube                  ' . $report['youtube']);
        }
        return $report;
    }

    /** @param list<array<string, mixed>> $items */
    private static function replace(string $source, string $interest, array $items): void
    {
        $pdo = Database::connection();
        $now = Database::now();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM trending_items WHERE source = :s AND interest = :i')->execute(['s' => $source, 'i' => $interest]);
            $insert = $pdo->prepare('INSERT INTO trending_items (source, interest, external_id, title, summary, url, image_url, metric, metric_label, rank_order, published_at, fetched_at)
                VALUES (:source, :interest, :ext, :title, :summary, :url, :image, :metric, :label, :rank, :published, :fetched)');
            $seen = [];
            foreach (array_values($items) as $rank => $item) {
                if (isset($seen[$item['id']])) {
                    continue;
                }
                $seen[$item['id']] = true;
                $insert->execute([
                    'source' => $source,
                    'interest' => $interest,
                    'ext' => mb_substr((string) $item['id'], 0, 120),
                    'title' => mb_substr((string) $item['title'], 0, 300),
                    'summary' => isset($item['summary']) && $item['summary'] !== '' ? mb_substr((string) $item['summary'], 0, 500) : null,
                    'url' => mb_substr((string) $item['url'], 0, 500),
                    'image' => $item['image'] ?? null,
                    'metric' => (int) ($item['metric'] ?? 0),
                    'label' => $item['label'] ?? null,
                    'rank' => $rank,
                    'published' => $item['published'] ?? null,
                    'fetched' => $now,
                ]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** An API key, or an OAuth token from any connected YouTube channel. */
    public static function youtubeAccess(): ?array
    {
        $key = (string) Env::get('YOUTUBE_API_KEY', '');
        if ($key !== '') {
            return ['key' => $key];
        }
        try {
            $ids = Database::connection()->query("SELECT id, user_id FROM channels WHERE provider = 'youtube' ORDER BY updated_at DESC LIMIT 5")->fetchAll();
            foreach ($ids as $row) {
                $channel = Channels::find((int) $row['user_id'], (int) $row['id']);
                if ($channel === null) {
                    continue;
                }
                try {
                    return ['token' => Channels::accessToken($channel)];
                } catch (Throwable) {
                    continue;
                }
            }
        } catch (Throwable) {
        }
        return null;
    }

    private static function youtube(array $access, ?string $category): array
    {
        $params = ['part' => 'snippet,statistics', 'chart' => 'mostPopular', 'regionCode' => 'US', 'maxResults' => 12];
        if ($category !== null) {
            $params['videoCategoryId'] = $category;
        }
        if (isset($access['key'])) {
            $params['key'] = $access['key'];
        }
        $data = self::getJson('https://www.googleapis.com/youtube/v3/videos?' . http_build_query($params), isset($access['token']) ? ['Authorization: Bearer ' . $access['token']] : []);
        $out = [];
        foreach ($data['items'] ?? [] as $v) {
            $id = (string) ($v['id'] ?? '');
            if (!preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id)) {
                continue;
            }
            $views = (int) ($v['statistics']['viewCount'] ?? 0);
            $out[] = [
                'id' => $id,
                'title' => html_entity_decode((string) ($v['snippet']['title'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'summary' => (string) ($v['snippet']['channelTitle'] ?? ''),
                'url' => 'https://www.youtube.com/watch?v=' . $id,
                'image' => 'https://i.ytimg.com/vi/' . $id . '/mqdefault.jpg',
                'metric' => $views,
                'label' => self::compact($views) . ' views',
                'published' => self::date((string) ($v['snippet']['publishedAt'] ?? '')),
            ];
        }
        return $out;
    }

    private static function hackerNews(string $query): array
    {
        $hits = [];
        foreach ([40, 10] as $minPoints) {
            $params = [
                'tags' => 'story',
                'numericFilters' => 'created_at_i>' . (time() - 7 * 86400) . ',points>' . $minPoints,
                'hitsPerPage' => 30,
            ];
            if ($query !== '') {
                $params['query'] = $query;
            }
            $data = self::getJson('https://hn.algolia.com/api/v1/search?' . http_build_query($params));
            $hits = $data['hits'] ?? [];
            if (count($hits) >= 5) {
                break;
            }
        }
        usort($hits, static fn ($a, $b) => ((int) ($b['points'] ?? 0)) <=> ((int) ($a['points'] ?? 0)));
        $out = [];
        foreach ($hits as $h) {
            $id = (string) ($h['objectID'] ?? '');
            $title = trim((string) ($h['title'] ?? ''));
            if (!ctype_digit($id) || $title === '') {
                continue;
            }
            $points = (int) ($h['points'] ?? 0);
            $link = (string) ($h['url'] ?? '');
            $out[] = [
                'id' => $id,
                'title' => $title,
                'summary' => $link !== '' ? (string) parse_url($link, PHP_URL_HOST) : 'news.ycombinator.com',
                'url' => str_starts_with($link, 'https://') || str_starts_with($link, 'http://') ? $link : 'https://news.ycombinator.com/item?id=' . $id,
                'metric' => $points,
                'label' => self::compact($points) . ' points · ' . self::compact((int) ($h['num_comments'] ?? 0)) . ' comments',
                'published' => isset($h['created_at_i']) ? gmdate('Y-m-d H:i:s', (int) $h['created_at_i']) : null,
            ];
        }
        return $out;
    }

    private static function reddit(string $sub): array
    {
        $xml = self::getXml('https://www.reddit.com/r/' . rawurlencode($sub) . '/top/.rss?t=week&limit=10');
        $xml->registerXPathNamespace('a', 'http://www.w3.org/2005/Atom');
        $out = [];
        foreach ($xml->xpath('//a:entry') ?: [] as $entry) {
            $entry->registerXPathNamespace('a', 'http://www.w3.org/2005/Atom');
            $title = trim((string) $entry->title);
            $link = (string) (($entry->xpath('a:link/@href') ?: [''])[0]);
            $id = (string) $entry->id;
            if ($title === '' || !str_starts_with($link, 'https://www.reddit.com/')) {
                continue;
            }
            $out[] = [
                'id' => mb_substr($id !== '' ? $id : $link, 0, 120),
                'title' => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
                'summary' => 'r/' . $sub,
                'url' => $link,
                'label' => 'Top this week in r/' . $sub,
                'published' => self::date((string) ($entry->published ?? $entry->updated)),
            ];
        }
        return $out;
    }

    private static function google(): array
    {
        $xml = self::getXml('https://trends.google.com/trending/rss?geo=US');
        $out = [];
        foreach ($xml->channel->item ?? [] as $item) {
            $topic = trim((string) $item->title);
            if (mb_strlen($topic) < 4 || preg_match('/^[\d\s]+$/', $topic)) {
                continue;
            }
            $topic = mb_convert_case($topic, MB_CASE_TITLE, 'UTF-8');
            $ht = $item->children('https://trends.google.com/trending/rss');
            $traffic = trim((string) ($ht->approx_traffic ?? ''));
            $news = $ht->news_item[0] ?? null;
            $newsUrl = $news ? (string) $news->news_item_url : '';
            $out[] = [
                'id' => mb_strtolower($topic),
                'title' => $topic,
                'summary' => $news ? html_entity_decode(trim((string) $news->news_item_title), ENT_QUOTES, 'UTF-8') : '',
                'url' => str_starts_with($newsUrl, 'https://') ? $newsUrl : 'https://www.google.com/search?q=' . rawurlencode($topic),
                'label' => $traffic !== '' ? $traffic . ' searches' : 'Trending search',
                'published' => self::date((string) $item->pubDate),
            ];
        }
        return $out;
    }

    private static function getJson(string $url, array $headers = []): array
    {
        $data = json_decode(self::get($url, array_merge(['Accept: application/json'], $headers)), true);
        if (!is_array($data)) {
            throw new RuntimeException('unreadable JSON');
        }
        return $data;
    }

    private static function getXml(string $url): SimpleXMLElement
    {
        $xml = simplexml_load_string(self::get($url, ['Accept: application/rss+xml, application/atom+xml, text/xml']), SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if ($xml === false) {
            throw new RuntimeException('unreadable XML');
        }
        return $xml;
    }

    private static function get(string $url, array $headers): string
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_USERAGENT => self::USER_AGENT,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if ($body !== false && $status === 200) {
                return (string) $body;
            }
            if ($status !== 429 && $status < 500) {
                throw new RuntimeException('HTTP ' . $status . ' ' . curl_error($ch));
            }
            sleep(20 * $attempt);
        }
        throw new RuntimeException('HTTP ' . $status . ' after retries');
    }

    private static function date(string $value): ?string
    {
        $t = strtotime($value);
        return $t ? gmdate('Y-m-d H:i:s', $t) : null;
    }

    private static function compact(int $n): string
    {
        return match (true) {
            $n >= 1_000_000 => rtrim(rtrim(number_format($n / 1_000_000, 1), '0'), '.') . 'M',
            $n >= 1_000 => rtrim(rtrim(number_format($n / 1_000, 1), '0'), '.') . 'K',
            default => (string) $n,
        };
    }
}
