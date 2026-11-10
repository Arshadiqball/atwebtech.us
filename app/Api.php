<?php

declare(strict_types=1);

/**
 * Public REST API (/api/v1) for paid plans, with workspace API keys, plus outgoing webhooks.
 * Keys look like awt_<40 hex>; only a SHA-256 hash and a short prefix are stored.
 */
final class Api
{
    public const MAX_KEYS = 10;
    private const RATE_PER_MINUTE = 120;

    // Settings page (/app/developers)

    public static function page(int $uid): array
    {
        $pdo = Database::connection();
        $keys = $pdo->prepare('SELECT id, name, prefix, created_at, last_used_at FROM api_keys WHERE user_id = :uid ORDER BY created_at DESC');
        $keys->execute(['uid' => $uid]);
        $hooks = $pdo->prepare('SELECT id, url, events, last_status, last_sent_at, failures, created_at FROM webhooks WHERE user_id = :uid ORDER BY created_at DESC');
        $hooks->execute(['uid' => $uid]);
        $fresh = $_SESSION['_api_fresh'] ?? null;
        unset($_SESSION['_api_fresh']);
        return ['keys' => $keys->fetchAll(), 'hooks' => $hooks->fetchAll(), 'fresh' => is_array($fresh) ? $fresh : null, 'paid' => Channels::allowance($uid)['paid']];
    }

    /** POST /app/developers */
    public static function handle(): never
    {
        $user = Auth::member();
        $uid = (int) $user['id'];
        $back = '/app/developers';
        $pdo = Database::connection();
        $action = (string) ($_POST['action'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        if (!Channels::allowance($uid)['paid']) {
            Toolkit::done($back, 'The API and webhooks are part of Essentials and Team. See Plan & billing to upgrade.');
        }
        if ($action === 'key') {
            $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 60) ?: 'API key';
            $count = $pdo->prepare('SELECT COUNT(*) FROM api_keys WHERE user_id = :uid');
            $count->execute(['uid' => $uid]);
            if ((int) $count->fetchColumn() >= self::MAX_KEYS) {
                Toolkit::done($back, sprintf('You can have up to %d API keys. Revoke one first.', self::MAX_KEYS));
            }
            $key = 'awt_' . bin2hex(random_bytes(20));
            $pdo->prepare('INSERT INTO api_keys (user_id, name, prefix, key_hash, created_by, created_at) VALUES (:uid, :name, :prefix, :hash, :by, :now)')
                ->execute(['uid' => $uid, 'name' => $name, 'prefix' => substr($key, 0, 10), 'hash' => hash('sha256', $key), 'by' => (int) (Auth::person()['id'] ?? $uid), 'now' => Database::now()]);
            $_SESSION['_api_fresh'] = ['type' => 'key', 'value' => $key];
            Toolkit::done($back, 'API key created. Copy it now: it won’t be shown again.');
        }
        if ($action === 'revoke') {
            $pdo->prepare('DELETE FROM api_keys WHERE id = :id AND user_id = :uid')->execute(['id' => $id, 'uid' => $uid]);
            Toolkit::done($back, 'API key revoked. Apps using it stop working right away.');
        }
        if ($action === 'hook') {
            $url = trim((string) ($_POST['url'] ?? ''));
            $events = array_values(array_intersect((array) ($_POST['events'] ?? []), array_keys(Webhooks::EVENTS)));
            if (!preg_match('~^https://~i', $url) || strlen($url) > 500 || Webhooks::publicIp((string) parse_url($url, PHP_URL_HOST)) === null) {
                Toolkit::done($back, 'Use a public https:// address for the webhook.');
            }
            if (!$events) {
                Toolkit::done($back, 'Choose at least one event.');
            }
            $count = $pdo->prepare('SELECT COUNT(*) FROM webhooks WHERE user_id = :uid');
            $count->execute(['uid' => $uid]);
            if ((int) $count->fetchColumn() >= 10) {
                Toolkit::done($back, 'You can have up to 10 webhooks.');
            }
            $secret = 'whsec_' . bin2hex(random_bytes(20));
            $pdo->prepare('INSERT INTO webhooks (user_id, url, events, secret, failures, created_at) VALUES (:uid, :url, :events, :secret, 0, :now)')
                ->execute(['uid' => $uid, 'url' => $url, 'events' => implode(',', $events), 'secret' => Security::seal($secret), 'now' => Database::now()]);
            $_SESSION['_api_fresh'] = ['type' => 'secret', 'value' => $secret];
            Toolkit::done($back, 'Webhook added. Copy its signing secret now: it won’t be shown again.');
        }
        if ($action === 'unhook') {
            $pdo->prepare('DELETE FROM webhooks WHERE id = :id AND user_id = :uid')->execute(['id' => $id, 'uid' => $uid]);
            Toolkit::done($back, 'Webhook removed.');
        }
        if ($action === 'test') {
            $hook = $pdo->prepare('SELECT * FROM webhooks WHERE id = :id AND user_id = :uid');
            $hook->execute(['id' => $id, 'uid' => $uid]);
            $hook = $hook->fetch();
            if (!$hook) {
                Toolkit::done($back, 'That webhook was removed.');
            }
            $status = Webhooks::send($hook, 'test', ['message' => 'Hello from atwebtech']);
            Toolkit::done($back, $status >= 200 && $status < 300 ? 'Test sent: your endpoint answered ' . $status . '.' : 'Test failed: ' . ($status > 0 ? 'your endpoint answered ' . $status : 'it couldn’t be reached') . '.');
        }
        Toolkit::done($back, 'Nothing changed.');
    }

    // The API itself

    /** Every /api/v1 request: authenticate, rate-limit, then route. */
    public static function dispatch(string $resource, ?string $id = null): never
    {
        header('Access-Control-Allow-Origin: *');
        $key = self::authenticate();
        $uid = (int) $key['user_id'];
        if (!Channels::allowance($uid)['paid']) {
            self::error(402, 'plan_required', 'The API is part of the Essentials and Team plans.');
        }
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        match (true) {
            $resource === 'me' && $method === 'GET' => self::me($uid),
            $resource === 'channels' && $method === 'GET' => self::ok(['data' => array_map([self::class, 'channelOut'], Channels::forUser($uid))]),
            $resource === 'posts' && $id === null && $method === 'GET' => self::listPosts($uid),
            $resource === 'posts' && $id === null && $method === 'POST' => self::createPost($uid, (int) $key['created_by']),
            $resource === 'posts' && $id !== null && $method === 'GET' => self::getPost($uid, $id),
            $resource === 'posts' && $id !== null && $method === 'DELETE' => self::deletePost($uid, $id),
            default => self::error(404, 'not_found', 'Unknown endpoint. See ' . absolute_url('/developers') . '.'),
        };
    }

    private static function authenticate(): array
    {
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (!preg_match('/^Bearer\s+(awt_[a-f0-9]{40})$/', $header, $m)) {
            self::error(401, 'unauthorized', 'Send your API key as: Authorization: Bearer awt_…');
        }
        $stmt = Database::connection()->prepare('SELECT * FROM api_keys WHERE key_hash = :h');
        $stmt->execute(['h' => hash('sha256', $m[1])]);
        $key = $stmt->fetch();
        if (!$key) {
            self::error(401, 'unauthorized', 'That API key is not valid or was revoked.');
        }
        $fp = 'key:' . (int) $key['id'];
        if (RateLimiter::tooManyAttempts('api', $fp, self::RATE_PER_MINUTE, 1)) {
            header('Retry-After: 60');
            self::error(429, 'rate_limited', sprintf('Up to %d requests a minute per key.', self::RATE_PER_MINUTE));
        }
        RateLimiter::hit('api', $fp);
        if ($key['last_used_at'] === null || strtotime($key['last_used_at'] . ' UTC') < time() - 300) {
            Database::connection()->prepare('UPDATE api_keys SET last_used_at = :now WHERE id = :id')->execute(['now' => Database::now(), 'id' => (int) $key['id']]);
        }
        return $key;
    }

    private static function me(int $uid): never
    {
        $stmt = Database::connection()->prepare('SELECT id, display_name, timezone FROM users WHERE id = :id');
        $stmt->execute(['id' => $uid]);
        $u = $stmt->fetch();
        self::ok(['workspace' => ['id' => (int) $u['id'], 'name' => $u['display_name'], 'timezone' => $u['timezone'] ?: 'UTC', 'plan' => Channels::allowance($uid)['slug']]]);
    }

    private static function listPosts(int $uid): never
    {
        $status = (string) ($_GET['status'] ?? '');
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 50)));
        $sql = 'SELECT * FROM posts WHERE user_id = :uid';
        $params = ['uid' => $uid];
        if (in_array($status, ['draft', 'planned', 'review', 'sent', 'failed'], true)) {
            $sql .= ' AND status = :s';
            $params['s'] = $status;
        }
        if (isset($_GET['channel_id']) && ctype_digit((string) $_GET['channel_id'])) {
            $sql .= ' AND channel_id = :cid';
            $params['cid'] = (int) $_GET['channel_id'];
        }
        $stmt = Database::connection()->prepare($sql . ' ORDER BY id DESC LIMIT ' . $limit);
        $stmt->execute($params);
        self::ok(['data' => array_map([self::class, 'postOut'], $stmt->fetchAll())]);
    }

    private static function getPost(int $uid, string $id): never
    {
        $post = ctype_digit($id) ? Workspace::post($uid, (int) $id) : null;
        $post === null ? self::error(404, 'not_found', 'No post with that id.') : self::ok(['data' => self::postOut($post)]);
    }

    private static function deletePost(int $uid, string $id): never
    {
        $post = ctype_digit($id) ? Workspace::post($uid, (int) $id) : null;
        if ($post === null) {
            self::error(404, 'not_found', 'No post with that id.');
        }
        if (in_array($post['status'], ['sent', 'publishing'], true)) {
            self::error(409, 'already_published', 'Published posts can’t be deleted from atwebtech.');
        }
        Database::connection()->prepare('DELETE FROM posts WHERE id = :id AND user_id = :uid')->execute(['id' => (int) $id, 'uid' => $uid]);
        self::ok(['deleted' => true]);
    }

    /**
     * POST /api/v1/posts {channel_id, text, scheduled_at?, title?, media_ids?, draft?}
     * scheduled_at is ISO 8601; without it (or with draft:true) the post is saved as a draft.
     */
    private static function createPost(int $uid, int $by): never
    {
        $in = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($in)) {
            self::error(400, 'invalid_json', 'Send a JSON body.');
        }
        $channel = Channels::find($uid, (int) ($in['channel_id'] ?? 0));
        if ($channel === null) {
            self::error(422, 'invalid_channel', 'channel_id must be one of GET /api/v1/channels.');
        }
        $text = trim(str_replace("\r\n", "\n", (string) ($in['text'] ?? '')));
        $limit = (int) (data('workspace')['limits'][$channel['provider']] ?? data('workspace')['post_max']);
        if (mb_strlen($text) > $limit) {
            self::error(422, 'text_too_long', sprintf('%s posts can be up to %d characters.', $channel['provider'], $limit));
        }
        $mediaIds = array_slice(array_values(array_unique(array_map('intval', (array) ($in['media_ids'] ?? [])))), 0, 10);
        $media = [];
        foreach ($mediaIds as $mid) {
            $m = Media::ready($uid, $mid);
            if ($m === null) {
                self::error(422, 'invalid_media', 'media_ids must be ids of files in your Media Library.');
            }
            $media[] = $m;
        }
        if (count($media) > 1 && array_filter($media, static fn (array $m): bool => $m['kind'] !== 'image')) {
            self::error(422, 'invalid_media', 'More than one file makes a carousel, which can only contain images.');
        }
        $first = $media[0] ?? null;
        $problem = match ($channel['provider']) {
            'youtube' => $first === null || $first['kind'] !== 'video' || count($media) > 1 ? 'YouTube needs exactly one video.' : null,
            'instagram', 'pinterest' => $first === null || $first['kind'] !== 'image' ? 'This network needs a photo.' : null,
            default => $text === '' && $first === null ? 'Send text, media, or both.' : null,
        };
        if ($problem !== null) {
            self::error(422, 'invalid_post', $problem);
        }
        $status = 'draft';
        $planned = null;
        if (empty($in['draft']) && !empty($in['scheduled_at'])) {
            $ts = strtotime((string) $in['scheduled_at']);
            if ($ts === false || $ts < time() + 60) {
                self::error(422, 'invalid_time', 'scheduled_at must be an ISO 8601 time at least a minute from now.');
            }
            if (!$channel['can_publish']) {
                self::error(422, 'channel_cannot_publish', 'Reconnect this channel in atwebtech and allow publishing first.');
            }
            $cap = Channels::scheduledCap($uid);
            if ($cap !== null && Channels::scheduledCount($uid, (int) $channel['id']) >= $cap) {
                self::error(422, 'schedule_full', 'This channel has the most scheduled posts your plan allows.');
            }
            $status = 'planned';
            $planned = gmdate('Y-m-d H:i:s', $ts);
        }
        $title = $channel['provider'] === 'youtube' ? mb_substr(trim((string) ($in['title'] ?? '')), 0, 100) : null;
        $pdo = Database::connection();
        $now = Database::now();
        $pdo->prepare('INSERT INTO posts (user_id, channel, channel_id, body, title, media_id, extra_media, status, planned_at, created_by, created_at, updated_at) VALUES (:uid, :ch, :cid, :body, :title, :media, :extra, :status, :planned, :by, :now, :now2)')
            ->execute([
                'uid' => $uid, 'ch' => $channel['provider'], 'cid' => (int) $channel['id'], 'body' => $text, 'title' => $title ?: null,
                'media' => $first ? (int) $first['id'] : null, 'extra' => Media::storeExtra(array_map(static fn (array $m): int => (int) $m['id'], array_slice($media, 1))),
                'status' => $status, 'planned' => $planned, 'by' => $by ?: null, 'now' => $now, 'now2' => $now,
            ]);
        $post = Workspace::post($uid, (int) $pdo->lastInsertId());
        self::ok(['data' => self::postOut((array) $post)], 201);
    }

    private static function channelOut(array $c): array
    {
        return ['id' => (int) $c['id'], 'network' => $c['provider'], 'name' => $c['name'], 'can_publish' => (bool) $c['can_publish'], 'paused' => !empty($c['paused_at'])];
    }

    private static function postOut(array $p): array
    {
        $iso = static fn (?string $t): ?string => $t ? gmdate('c', (int) strtotime($t . ' UTC')) : null;
        $ids = $p['media_id'] ? array_merge([(int) $p['media_id']], Media::extraIds($p['extra_media'] ?? null)) : [];
        return [
            'id' => (int) $p['id'], 'channel_id' => $p['channel_id'] !== null ? (int) $p['channel_id'] : null, 'network' => $p['channel'],
            'status' => $p['status'], 'text' => (string) $p['body'], 'title' => $p['title'] ?? null, 'media_ids' => $ids,
            'scheduled_at' => $iso($p['planned_at'] ?? null), 'published_at' => $iso($p['sent_at'] ?? null),
            'url' => $p['external_url'] ?? null, 'error' => $p['error'] ?? null,
        ];
    }

    private static function ok(array $data, int $status = 200): never
    {
        json_response($data, $status);
    }

    private static function error(int $status, string $code, string $message): never
    {
        json_response(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}

/** Outgoing webhooks: events are queued in webhook_events and delivered from bin/publish.php. */
final class Webhooks
{
    public const EVENTS = [
        'post.published' => 'A post was published',
        'post.failed' => 'A post failed to publish',
        'comment.received' => 'A new comment or mention arrived in Community',
    ];
    private const MAX_FAILURES = 20;

    /** Queue an event for every webhook of the workspace that listens to it. Cheap: one insert when there are hooks. */
    public static function queue(int $uid, string $event, array $data): void
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM webhooks WHERE user_id = :uid AND events LIKE :e AND failures < :max');
        $stmt->execute(['uid' => $uid, 'e' => '%' . $event . '%', 'max' => self::MAX_FAILURES]);
        if ((int) $stmt->fetchColumn() === 0) {
            return;
        }
        Database::connection()->prepare('INSERT INTO webhook_events (user_id, event, payload, attempts, created_at) VALUES (:uid, :e, :p, 0, :now)')
            ->execute(['uid' => $uid, 'e' => $event, 'p' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'now' => Database::now()]);
    }

    /** Deliver queued events. Each event is tried up to 5 times, a few minutes apart. */
    public static function run(int $limit = 20): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM webhook_events WHERE delivered_at IS NULL AND attempts < 5 AND (next_at IS NULL OR next_at <= :now) ORDER BY id LIMIT ' . $limit);
        $stmt->execute(['now' => Database::now()]);
        $sent = 0;
        foreach ($stmt->fetchAll() as $ev) {
            $hooks = $pdo->prepare('SELECT * FROM webhooks WHERE user_id = :uid AND events LIKE :e AND failures < :max');
            $hooks->execute(['uid' => (int) $ev['user_id'], 'e' => '%' . $ev['event'] . '%', 'max' => self::MAX_FAILURES]);
            $allOk = true;
            foreach ($hooks->fetchAll() as $hook) {
                $status = self::send($hook, (string) $ev['event'], (array) json_decode((string) $ev['payload'], true), (int) $ev['id']);
                $allOk = $allOk && $status >= 200 && $status < 300;
                $sent++;
            }
            $attempts = (int) $ev['attempts'] + 1;
            $pdo->prepare('UPDATE webhook_events SET attempts = :a, delivered_at = :d, next_at = :n WHERE id = :id')->execute([
                'a' => $attempts, 'd' => $allOk || $attempts >= 5 ? Database::now() : null,
                'n' => gmdate('Y-m-d H:i:s', time() + 120 * $attempts), 'id' => (int) $ev['id'],
            ]);
        }
        $pdo->prepare('DELETE FROM webhook_events WHERE delivered_at IS NOT NULL AND delivered_at < :old')->execute(['old' => gmdate('Y-m-d H:i:s', time() - 7 * 86400)]);
        return $sent;
    }

    /** POST one signed event. Returns the HTTP status, or 0 when unreachable. */
    public static function send(array $hook, string $event, array $data, int $eventId = 0): int
    {
        $url = (string) $hook['url'];
        $host = (string) parse_url($url, PHP_URL_HOST);
        $ip = self::publicIp($host);
        $status = 0;
        if ($ip !== null && str_starts_with(strtolower($url), 'https://')) {
            $body = json_encode(['id' => $eventId ?: null, 'event' => $event, 'created_at' => gmdate('c'), 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $ts = (string) time();
            $sig = hash_hmac('sha256', $ts . '.' . $body, (string) Security::unseal($hook['secret']));
            $port = (int) (parse_url($url, PHP_URL_PORT) ?: 443);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
                CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ip],
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'User-Agent: atwebtech-webhooks', 'X-Atwebtech-Event: ' . $event, 'X-Atwebtech-Timestamp: ' . $ts, 'X-Atwebtech-Signature: sha256=' . $sig],
            ]);
            curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        }
        $ok = $status >= 200 && $status < 300;
        Database::connection()->prepare('UPDATE webhooks SET last_status = :s, last_sent_at = :now, failures = CASE WHEN :ok = 1 THEN 0 ELSE failures + 1 END WHERE id = :id')
            ->execute(['s' => $status, 'now' => Database::now(), 'ok' => $ok ? 1 : 0, 'id' => (int) $hook['id']]);
        return $status;
    }

    /** The host's public IP, or null for hosts on private, loopback or reserved addresses. */
    public static function publicIp(string $host): ?string
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
}
