<?php

declare(strict_types=1);

/**
 * Bluesky and Mastodon: connect, publish (text plus up to 4 photos, or a Mastodon video), stats,
 * and replies/mentions for the Community inbox.
 *
 * Bluesky connects with the handle and an app password: atwebtech exchanges them for a session and keeps
 * only the session tokens (sealed), never the password. Mastodon uses OAuth on the person's own server;
 * atwebtech registers itself on each server once (mastodon_apps). Server addresses typed by users are
 * checked to be public HTTPS hosts and pinned to the checked IP, so they can't reach internal networks.
 */
final class OpenSocial
{
    public const PROVIDERS = ['bluesky', 'mastodon'];
    private const BSKY = 'https://bsky.social';
    private const BSKY_APPVIEW = 'https://public.api.bsky.app';
    private const BSKY_IMAGE_MAX = 950000;
    private const MASTODON_SCOPES = 'read write:statuses write:media';

    // Connecting

    /** POST /app/channels/connect/{bluesky|mastodon}: without the form fields, show the form. */
    public static function start(string $provider, bool $reconnect): never
    {
        $uid = (int) Auth::user()['id'];
        if (!isset($_POST['handle']) && !isset($_POST['instance'])) {
            redirect('/app/channels/add/' . $provider . ($reconnect ? '?reconnect=1' : ''));
        }
        if ($provider === 'bluesky') {
            self::connectBluesky($uid, $reconnect);
        }
        self::startMastodon($reconnect);
    }

    /** GET /app/channels/add/{provider} */
    public static function form(string $provider): void
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            not_found();
        }
        Workspace::render('channels', 'workspace/connect-open', 'Connect ' . ($provider === 'bluesky' ? 'Bluesky' : 'Mastodon'), static fn (): array => [
            'provider' => $provider, 'reconnect' => ($_GET['reconnect'] ?? '') === '1',
        ]);
    }

    private static function connectBluesky(int $uid, bool $reconnect): never
    {
        $back = '/app/channels/add/bluesky';
        $handle = strtolower(ltrim(trim((string) ($_POST['handle'] ?? '')), '@'));
        $password = trim((string) ($_POST['app_password'] ?? ''));
        if (!preg_match('/^([a-z0-9-]+\.)+[a-z]{2,}$|^did:plc:[a-z0-9]+$/', $handle)) {
            self::back($back, 'Enter your Bluesky handle, like name.bsky.social.');
        }
        if (!preg_match('/^[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}$/', $password)) {
            self::back($back, 'Use an app password (it looks like abcd-efgh-ijkl-mnop), not your main password. Create one in Bluesky → Settings → Privacy and security → App passwords.');
        }
        try {
            $session = self::json('POST', self::BSKY . '/xrpc/com.atproto.server.createSession', ['identifier' => $handle, 'password' => $password]);
        } catch (ChannelApiException $e) {
            self::back($back, $e->status === 401 ? 'Bluesky didn’t accept that handle and app password. Check them and try again.' : 'We couldn’t reach Bluesky. Please try again in a few minutes.');
        }
        $did = (string) ($session['did'] ?? '');
        if (!str_starts_with($did, 'did:') || empty($session['accessJwt']) || empty($session['refreshJwt'])) {
            self::back($back, 'Bluesky didn’t start a session. Please try again.');
        }
        $pds = self::BSKY;
        foreach ((array) ($session['didDoc']['service'] ?? []) as $service) {
            if (($service['id'] ?? '') === '#atproto_pds' && is_string($service['serviceEndpoint'] ?? null) && self::publicHost((string) parse_url($service['serviceEndpoint'], PHP_URL_HOST)) !== null) {
                $pds = rtrim($service['serviceEndpoint'], '/');
            }
        }
        $avatar = null;
        $name = '@' . (string) ($session['handle'] ?? $handle);
        try {
            $profile = self::json('GET', self::BSKY_APPVIEW . '/xrpc/app.bsky.actor.getProfile?' . http_build_query(['actor' => $did]));
            $avatar = is_string($profile['avatar'] ?? null) && str_starts_with($profile['avatar'], 'https://cdn.bsky.app/') ? $profile['avatar'] : null;
        } catch (Throwable) {
        }
        self::pending('bluesky', [
            'access_token' => (string) $session['accessJwt'],
            'refresh_token' => (string) $session['refreshJwt'],
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
            'scope' => 'pds:' . $pds,
        ], [['id' => $did, 'name' => $name, 'avatar' => $avatar, 'kind' => 'Profile']], $reconnect);
    }

    private static function startMastodon(bool $reconnect): never
    {
        $back = '/app/channels/add/mastodon';
        $instance = strtolower(trim((string) ($_POST['instance'] ?? '')));
        $instance = preg_replace('~^https?://~', '', rtrim($instance, '/')) ?? '';
        $instance = (string) strtok($instance, '/');
        if (!preg_match('/^([a-z0-9-]+\.)+[a-z]{2,}$/', $instance) || self::publicHost($instance) === null) {
            self::back($back, 'Enter your Mastodon server, like mastodon.social.');
        }
        try {
            $app = self::mastodonApp($instance);
        } catch (Throwable $e) {
            error_log(sprintf('[%s] mastodon app on %s: %s', gmdate('c'), $instance, $e->getMessage()));
            self::back($back, 'We couldn’t reach ' . $instance . ' as a Mastodon server. Check the address and try again.');
        }
        $state = bin2hex(random_bytes(24));
        $_SESSION['_oauth'] = ['provider' => 'mastodon', 'state' => $state, 'instance' => $instance, 'reconnect' => $reconnect, 'at' => time()];
        $url = 'https://' . $instance . '/oauth/authorize?' . http_build_query([
            'client_id' => $app['client_id'], 'redirect_uri' => self::mastodonRedirect(), 'response_type' => 'code',
            'scope' => self::MASTODON_SCOPES, 'state' => $state,
        ]);
        // A form can't redirect to a server the CSP form-action list doesn't name, so hand off with a page.
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        echo '<!doctype html><meta charset="utf-8"><meta name="robots" content="noindex"><meta http-equiv="refresh" content="0;url=' . e($url) . '">'
            . '<title>Opening Mastodon</title><p style="font:16px system-ui;margin:40px">Opening ' . e($instance) . '… <a href="' . e($url) . '">Continue</a></p>';
        exit;
    }

    /** GET /app/channels/callback/mastodon */
    public static function mastodonCallback(): never
    {
        Auth::require();
        $flow = $_SESSION['_oauth'] ?? null;
        unset($_SESSION['_oauth']);
        $state = (string) ($_GET['state'] ?? '');
        if (!is_array($flow) || ($flow['provider'] ?? '') !== 'mastodon' || $state === '' || !hash_equals((string) $flow['state'], $state) || time() - (int) $flow['at'] > 900) {
            self::back('/app/channels', 'That connection link expired or didn’t come from this session. Please try again.');
        }
        if (isset($_GET['error'])) {
            self::back('/app/channels', 'Connection cancelled. Nothing was connected.');
        }
        $code = (string) ($_GET['code'] ?? '');
        $instance = (string) $flow['instance'];
        try {
            $app = self::mastodonApp($instance);
            $token = self::json('POST', 'https://' . $instance . '/oauth/token', [
                'grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $app['client_id'],
                'client_secret' => $app['client_secret'], 'redirect_uri' => self::mastodonRedirect(), 'scope' => self::MASTODON_SCOPES,
            ], null, true);
            $access = (string) ($token['access_token'] ?? '');
            $me = self::json('GET', 'https://' . $instance . '/api/v1/accounts/verify_credentials', null, $access, true);
        } catch (Throwable $e) {
            error_log(sprintf('[%s] mastodon connect on %s: %s', gmdate('c'), $instance, $e->getMessage()));
            self::back('/app/channels', 'We couldn’t finish connecting to ' . $instance . '. Please try again.');
        }
        if ($access === '' || empty($me['id'])) {
            self::back('/app/channels', 'We couldn’t read this Mastodon account. Please try again.');
        }
        self::pending('mastodon', [
            'access_token' => $access, 'refresh_token' => null, 'expires_at' => '2099-12-31 00:00:00',
            'scope' => 'instance:' . $instance . ' ' . self::MASTODON_SCOPES,
        ], [['id' => (string) $me['id'], 'name' => '@' . (string) ($me['username'] ?? 'account') . '@' . $instance, 'avatar' => null, 'kind' => 'Profile']], !empty($flow['reconnect']));
    }

    /** Hand the account to the normal confirm step (/app/channels/confirm). */
    private static function pending(string $provider, array $tokens, array $accounts, bool $reconnect): never
    {
        $_SESSION['_oauth_pending'] = [
            'provider' => $provider,
            'tokens' => Security::seal(json_encode($tokens, JSON_THROW_ON_ERROR)),
            'accounts' => $accounts,
            'reconnect' => $reconnect,
            'at' => time(),
        ];
        redirect('/app/channels/confirm');
    }

    private static function mastodonApp(string $instance): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT client_id, client_secret FROM mastodon_apps WHERE instance = :i');
        $stmt->execute(['i' => $instance]);
        $row = $stmt->fetch();
        if ($row) {
            return ['client_id' => (string) $row['client_id'], 'client_secret' => (string) Security::unseal($row['client_secret'])];
        }
        $app = self::json('POST', 'https://' . $instance . '/api/v1/apps', [
            'client_name' => 'atwebtech', 'redirect_uris' => self::mastodonRedirect(), 'scopes' => self::MASTODON_SCOPES, 'website' => absolute_url('/'),
        ], null, true);
        if (empty($app['client_id']) || empty($app['client_secret'])) {
            throw new RuntimeException('No client id from the server.');
        }
        $pdo->prepare('INSERT INTO mastodon_apps (instance, client_id, client_secret, created_at) VALUES (:i, :id, :secret, :now)')
            ->execute(['i' => $instance, 'id' => (string) $app['client_id'], 'secret' => Security::seal((string) $app['client_secret']), 'now' => Database::now()]);
        return ['client_id' => (string) $app['client_id'], 'client_secret' => (string) $app['client_secret']];
    }

    private static function mastodonRedirect(): string
    {
        return absolute_url('/app/channels/callback/mastodon');
    }

    // Tokens

    public static function accessToken(array $channel): string
    {
        $access = Security::unseal($channel['access_token'] ?? null);
        if ($channel['provider'] === 'mastodon') {
            if ($access === null || $access === '') {
                throw new ChannelAuthException('Mastodon access expired. Reconnect the channel.');
            }
            return $access;
        }
        if ($access !== null && strtotime((string) $channel['token_expires_at'] . ' UTC') > time() + 120) {
            return $access;
        }
        $refresh = Security::unseal($channel['refresh_token'] ?? null);
        if ($refresh === null || $refresh === '') {
            throw new ChannelAuthException('Bluesky access expired. Reconnect the channel.');
        }
        try {
            $session = self::json('POST', self::pds($channel) . '/xrpc/com.atproto.server.refreshSession', null, $refresh);
        } catch (ChannelApiException $e) {
            if (in_array($e->status, [400, 401], true)) {
                throw new ChannelAuthException('Bluesky access expired. Reconnect the channel.');
            }
            throw $e;
        }
        if (empty($session['accessJwt']) || empty($session['refreshJwt'])) {
            throw new ChannelAuthException('Bluesky access expired. Reconnect the channel.');
        }
        Database::connection()->prepare('UPDATE channels SET access_token = :a, refresh_token = :r, token_expires_at = :e, updated_at = :now WHERE id = :id')->execute([
            'a' => Security::seal((string) $session['accessJwt']), 'r' => Security::seal((string) $session['refreshJwt']),
            'e' => gmdate('Y-m-d H:i:s', time() + 3600), 'now' => Database::now(), 'id' => (int) $channel['id'],
        ]);
        return (string) $session['accessJwt'];
    }

    // Publishing

    /** @param array[] $extras more ready image rows (Bluesky and Mastodon take up to 4 in all) */
    public static function publish(array $channel, string $text, ?array $media, ?string $file, array $extras): array
    {
        $text = trim($text);
        $photos = $media ? array_slice(array_merge([$media], $extras), 0, 4) : [];
        if ($text === '' && !$photos) {
            throw new PublishError('Write something or attach a photo.');
        }
        if ($media !== null && $file === null) {
            throw new PublishError('The attached file is missing. Attach it again, then retry.');
        }
        $token = self::accessToken($channel);
        if ($channel['provider'] === 'bluesky') {
            if ($media !== null && $media['kind'] !== 'image') {
                throw new PublishError('Bluesky posts from atwebtech can include photos, not videos.');
            }
            if (self::graphemes($text) > 300) {
                throw new PublishError('Bluesky posts can be up to 300 characters.');
            }
            $record = ['$type' => 'app.bsky.feed.post', 'text' => $text, 'createdAt' => gmdate('Y-m-d\TH:i:s.000\Z'), 'langs' => ['en']];
            if (($facets = self::facets($text)) !== []) {
                $record['facets'] = $facets;
            }
            if ($photos) {
                $images = [];
                foreach ($photos as $photo) {
                    [$bytes, $mime, $w, $h] = self::blueskyImage((string) Media::file($photo), (string) $photo['mime']);
                    $blob = self::raw('POST', self::pds($channel) . '/xrpc/com.atproto.repo.uploadBlob', $bytes, $mime, $token);
                    if (!isset($blob['blob'])) {
                        throw new PublishError('Bluesky didn’t accept one of the photos.');
                    }
                    $images[] = ['alt' => '', 'image' => $blob['blob'], 'aspectRatio' => ['width' => $w, 'height' => $h]];
                }
                $record['embed'] = ['$type' => 'app.bsky.embed.images', 'images' => $images];
            }
            $did = (string) $channel['external_id'];
            $made = self::json('POST', self::pds($channel) . '/xrpc/com.atproto.repo.createRecord', ['repo' => $did, 'collection' => 'app.bsky.feed.post', 'record' => $record], $token);
            $uri = (string) ($made['uri'] ?? '');
            if (!str_starts_with($uri, 'at://')) {
                throw new PublishError('Bluesky didn’t accept the post.');
            }
            $rkey = substr($uri, (int) strrpos($uri, '/') + 1);
            return ['id' => $uri, 'url' => 'https://bsky.app/profile/' . rawurlencode($did) . '/post/' . rawurlencode($rkey)];
        }
        // Mastodon
        if (mb_strlen($text) > 500) {
            throw new PublishError('Mastodon posts can be up to 500 characters.');
        }
        $base = self::instanceUrl($channel);
        $ids = [];
        foreach ($media && $media['kind'] === 'video' ? [$media] : $photos as $item) {
            $up = self::json('POST', $base . '/api/v2/media', ['file' => new CURLFile((string) Media::file($item), (string) $item['mime'], 'upload')], $token, true, 120);
            $mid = (string) ($up['id'] ?? '');
            if ($mid === '') {
                throw new PublishError('Mastodon didn’t accept the file.');
            }
            for ($i = 0; $i < 20 && empty($up['url']); $i++) {
                sleep(3);
                $up = self::json('GET', $base . '/api/v1/media/' . rawurlencode($mid), null, $token, true);
            }
            if (empty($up['url'])) {
                throw new ChannelApiException('Mastodon is still processing the file.', 0);
            }
            $ids[] = $mid;
        }
        $fields = ['status' => $text, 'visibility' => 'public'];
        foreach ($ids as $i => $mid) {
            $fields['media_ids[' . $i . ']'] = $mid;
        }
        $made = self::json('POST', $base . '/api/v1/statuses', $fields, $token, true);
        if (empty($made['id'])) {
            throw new PublishError('Mastodon didn’t accept the post.');
        }
        $url = is_string($made['url'] ?? null) && str_starts_with($made['url'], 'https://') ? $made['url'] : '';
        return ['id' => (string) $made['id'], 'url' => $url];
    }

    // Stats

    public static function followers(array $channel, string $token): ?int
    {
        if ($channel['provider'] === 'bluesky') {
            $p = self::json('GET', self::BSKY_APPVIEW . '/xrpc/app.bsky.actor.getProfile?' . http_build_query(['actor' => (string) $channel['external_id']]));
            return isset($p['followersCount']) ? (int) $p['followersCount'] : null;
        }
        $me = self::json('GET', self::instanceUrl($channel) . '/api/v1/accounts/verify_credentials', null, $token, true);
        return isset($me['followers_count']) ? (int) $me['followers_count'] : null;
    }

    /** @return array<int, array> post id => metrics */
    public static function postStats(array $channel, string $token, array $rows): array
    {
        $out = [];
        if ($channel['provider'] === 'bluesky') {
            foreach (array_chunk($rows, 25) as $chunk) {
                $byUri = array_column($chunk, 'id', 'external_id');
                $q = implode('&', array_map(static fn (string $u): string => 'uris=' . rawurlencode($u), array_keys($byUri)));
                $data = self::json('GET', self::BSKY_APPVIEW . '/xrpc/app.bsky.feed.getPosts?' . $q);
                foreach ($data['posts'] ?? [] as $p) {
                    if (isset($byUri[$p['uri'] ?? ''])) {
                        $out[(int) $byUri[$p['uri']]] = ['reactions' => $p['likeCount'] ?? null, 'comments' => $p['replyCount'] ?? null, 'shares' => (int) ($p['repostCount'] ?? 0) + (int) ($p['quoteCount'] ?? 0)];
                    }
                }
            }
            return $out;
        }
        $base = self::instanceUrl($channel);
        foreach ($rows as $post) {
            try {
                $s = self::json('GET', $base . '/api/v1/statuses/' . rawurlencode((string) $post['external_id']), null, $token, true);
                $out[(int) $post['id']] = ['reactions' => $s['favourites_count'] ?? null, 'comments' => $s['replies_count'] ?? null, 'shares' => $s['reblogs_count'] ?? null];
            } catch (ChannelApiException $e) {
                if ($e->status === 401) {
                    throw $e;
                }
                $out[(int) $post['id']] = ['error' => $e->status === 404 ? 'The post was removed from the network.' : 'The network didn’t return stats for this post.'];
            }
        }
        return $out;
    }

    // Community inbox: replies and mentions

    public static function comments(array $channel, string $token): array
    {
        $out = [];
        if ($channel['provider'] === 'bluesky') {
            $data = self::json('GET', self::pds($channel) . '/xrpc/app.bsky.notification.listNotifications?' . http_build_query(['limit' => 50]), null, $token);
            foreach ($data['notifications'] ?? [] as $n) {
                if (!in_array($n['reason'] ?? '', ['reply', 'mention', 'quote'], true)) {
                    continue;
                }
                $record = $n['record'] ?? [];
                $root = (string) ($record['reply']['root']['uri'] ?? $n['uri']);
                $handle = (string) ($n['author']['handle'] ?? '');
                $rkey = substr((string) $n['uri'], (int) strrpos((string) $n['uri'], '/') + 1);
                $out[] = [
                    'external_id' => (string) $n['uri'],
                    'item_id' => self::shortId($root),
                    'item_title' => $n['reason'] === 'mention' ? 'Mentioned you' : ($n['reason'] === 'quote' ? 'Quoted your post' : null),
                    'item_url' => 'https://bsky.app/profile/' . rawurlencode($handle) . '/post/' . rawurlencode($rkey),
                    'author_name' => '@' . $handle,
                    'author_avatar' => is_string($n['author']['avatar'] ?? null) ? $n['author']['avatar'] : null,
                    'author_url' => 'https://bsky.app/profile/' . rawurlencode($handle),
                    'is_own' => ($n['author']['did'] ?? '') === $channel['external_id'],
                    'body' => (string) ($record['text'] ?? ''),
                    'likes' => null, 'replies' => null,
                    'published_at' => (string) ($n['indexedAt'] ?? ''),
                ];
            }
            return $out;
        }
        $base = self::instanceUrl($channel);
        $data = self::json('GET', $base . '/api/v1/notifications?' . http_build_query(['types[]' => 'mention', 'limit' => 40]), null, $token, true);
        foreach (is_array($data) ? $data : [] as $n) {
            $s = $n['status'] ?? null;
            if (!is_array($s)) {
                continue;
            }
            $acct = (string) ($n['account']['acct'] ?? '');
            $out[] = [
                'external_id' => (string) $s['id'],
                'item_id' => (string) ($s['in_reply_to_id'] ?? $s['id']),
                'item_title' => $s['in_reply_to_id'] ? null : 'Mentioned you',
                'item_url' => is_string($s['url'] ?? null) ? $s['url'] : null,
                'author_name' => '@' . $acct,
                'author_avatar' => null,
                'author_url' => is_string($n['account']['url'] ?? null) ? $n['account']['url'] : null,
                'is_own' => (string) ($n['account']['id'] ?? '') === (string) $channel['external_id'],
                'body' => trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br />', '</p><p>'], "\n", (string) ($s['content'] ?? ''))), ENT_QUOTES | ENT_HTML5)),
                'likes' => $s['favourites_count'] ?? null, 'replies' => $s['replies_count'] ?? null,
                'published_at' => (string) ($s['created_at'] ?? ''),
            ];
        }
        return $out;
    }

    public static function reply(array $channel, array $comment, string $text): void
    {
        $token = self::accessToken($channel);
        if ($channel['provider'] === 'bluesky') {
            if (self::graphemes($text) > 300) {
                throw new PublishError('Bluesky replies can be up to 300 characters.');
            }
            $parent = self::json('GET', self::BSKY_APPVIEW . '/xrpc/app.bsky.feed.getPosts?uris=' . rawurlencode((string) $comment['external_id']));
            $p = $parent['posts'][0] ?? null;
            if (!is_array($p)) {
                throw new ChannelApiException('Parent post not found', 404);
            }
            $root = $p['record']['reply']['root'] ?? ['uri' => $p['uri'], 'cid' => $p['cid']];
            $record = ['$type' => 'app.bsky.feed.post', 'text' => $text, 'createdAt' => gmdate('Y-m-d\TH:i:s.000\Z'), 'reply' => ['root' => $root, 'parent' => ['uri' => $p['uri'], 'cid' => $p['cid']]]];
            if (($facets = self::facets($text)) !== []) {
                $record['facets'] = $facets;
            }
            self::json('POST', self::pds($channel) . '/xrpc/com.atproto.repo.createRecord', ['repo' => (string) $channel['external_id'], 'collection' => 'app.bsky.feed.post', 'record' => $record], $token);
            return;
        }
        $mention = ltrim((string) $comment['author_name'], '@');
        $body = str_contains($text, '@' . $mention) ? $text : '@' . $mention . ' ' . $text;
        self::json('POST', self::instanceUrl($channel) . '/api/v1/statuses', ['status' => mb_substr($body, 0, 500), 'in_reply_to_id' => (string) $comment['external_id'], 'visibility' => 'public'], $token, true);
    }

    // Helpers

    /** Link and hashtag facets, with UTF-8 byte offsets as Bluesky expects. */
    private static function facets(string $text): array
    {
        $facets = [];
        if (preg_match_all('~https?://[^\s<>"\]\)]+~u', $text, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as [$url, $offset]) {
                $url = rtrim($url, '.,;:!?');
                $facets[] = ['index' => ['byteStart' => $offset, 'byteEnd' => $offset + strlen($url)], 'features' => [['$type' => 'app.bsky.richtext.facet#link', 'uri' => $url]]];
            }
        }
        if (preg_match_all('/(?<=^|\s)#([\p{L}\p{N}_]{1,64})/u', $text, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $i => [$tag, $offset]) {
                $facets[] = ['index' => ['byteStart' => $offset, 'byteEnd' => $offset + strlen($tag)], 'features' => [['$type' => 'app.bsky.richtext.facet#tag', 'tag' => $m[1][$i][0]]]];
            }
        }
        return $facets;
    }

    /** Bluesky takes images under about 1 MB: shrink and re-encode as JPEG when needed. */
    private static function blueskyImage(string $path, string $mime): array
    {
        $size = @getimagesize($path);
        if ($size === false) {
            throw new PublishError('One of the photos couldn’t be read.');
        }
        [$w, $h] = $size;
        if (filesize($path) <= self::BSKY_IMAGE_MAX && in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return [(string) file_get_contents($path), $mime, $w, $h];
        }
        $src = @imagecreatefromstring((string) file_get_contents($path));
        if ($src === false) {
            throw new PublishError('One of the photos couldn’t be prepared for Bluesky.');
        }
        foreach ([2000, 1600, 1200, 1000] as $max) {
            $scale = min(1, $max / max($w, $h));
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            foreach ([85, 75, 65] as $quality) {
                ob_start();
                imagejpeg($dst, null, $quality);
                $bytes = (string) ob_get_clean();
                if (strlen($bytes) <= self::BSKY_IMAGE_MAX) {
                    return [$bytes, 'image/jpeg', $nw, $nh];
                }
            }
        }
        throw new PublishError('One of the photos is too detailed for Bluesky’s 1 MB limit. Use a smaller image.');
    }

    private static function graphemes(string $text): int
    {
        return function_exists('grapheme_strlen') ? (int) grapheme_strlen($text) : mb_strlen($text);
    }

    private static function shortId(string $uri): string
    {
        return substr(hash('sha256', $uri), 0, 32);
    }

    private static function pds(array $channel): string
    {
        $scopes = (string) ($channel['scopes'] ?? '');
        if (preg_match('~pds:(https://[^\s]+)~', $scopes, $m) && self::publicHost((string) parse_url($m[1], PHP_URL_HOST)) !== null) {
            return rtrim($m[1], '/');
        }
        return self::BSKY;
    }

    private static function instanceUrl(array $channel): string
    {
        if (!preg_match('~instance:([a-z0-9.-]+)~', (string) ($channel['scopes'] ?? ''), $m)) {
            throw new ChannelAuthException('Reconnect this Mastodon account.');
        }
        return 'https://' . $m[1];
    }

    /** The host's public IP, or null for hosts that resolve to private, loopback or reserved addresses. */
    private static function publicHost(string $host): ?string
    {
        if ($host === '' || !preg_match('/^([a-z0-9-]+\.)+[a-z]{2,}$/i', $host)) {
            return null;
        }
        $ip = gethostbyname($host);
        if ($ip === $host || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }
        return $ip;
    }

    /** JSON request. Form-encoded when $form is true (Mastodon), JSON body otherwise. Hosts are pinned to a checked public IP. */
    private static function json(string $method, string $url, ?array $body = null, ?string $bearer = null, bool $form = false, int $timeout = 20): array
    {
        $headers = ['Accept: application/json'];
        $payload = null;
        if ($body !== null) {
            if ($form) {
                $hasFile = (bool) array_filter($body, static fn ($v) => $v instanceof CURLFile);
                $payload = $hasFile ? $body : http_build_query($body);
            } else {
                $headers[] = 'Content-Type: application/json';
                $payload = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            }
        }
        return self::call($method, $url, $payload, $headers, $bearer, $timeout);
    }

    private static function raw(string $method, string $url, string $bytes, string $mime, string $bearer): array
    {
        return self::call($method, $url, $bytes, ['Accept: application/json', 'Content-Type: ' . $mime], $bearer, 60);
    }

    private static function call(string $method, string $url, mixed $payload, array $headers, ?string $bearer, int $timeout): array
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $ip = self::publicHost($host);
        if ($ip === null || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new ChannelApiException('Blocked host ' . $host, 0);
        }
        if ($bearer !== null) {
            $headers[] = 'Authorization: Bearer ' . $bearer;
        }
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RESOLVE => [$host . ':443:' . $ip], CURLOPT_USERAGENT => 'atwebtech (+' . absolute_url('/') . ')',
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $payload ?? '';
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($raw === false) {
            throw new ChannelApiException('Request failed: ' . curl_error($ch), 0);
        }
        $data = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300) {
            $reason = is_array($data) ? (string) ($data['message'] ?? $data['error_description'] ?? $data['error'] ?? '') : '';
            throw new ChannelApiException(sprintf('HTTP %d from %s %s', $status, $host, mb_substr(strip_tags($reason), 0, 200)), $status);
        }
        return is_array($data) ? $data : [];
    }

    private static function back(string $location, string $message): never
    {
        flash('notice', $message);
        redirect($location);
    }
}
