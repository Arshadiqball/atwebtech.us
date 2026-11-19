<?php

declare(strict_types=1);

/**
 * Push notifications to the mobile app through Firebase Cloud Messaging (HTTP v1).
 * Needs a Firebase service account key: put the JSON file at storage/firebase-service-account.json, or set
 * FIREBASE_SERVICE_ACCOUNT to its path. Without it nothing is sent. Sending never throws.
 *
 * The app also needs the project's public client values to register a phone. They come from .env
 * (FIREBASE_API_KEY, FIREBASE_SENDER_ID, FIREBASE_IOS_APP_ID, FIREBASE_ANDROID_APP_ID) and are handed to the
 * app when it starts, so turning push on needs no new app build.
 */
final class Push
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public static function enabled(): bool
    {
        return self::account() !== null;
    }

    /**
     * What the app needs to register a phone with Firebase, or null while push is not set up.
     * These are public identifiers (the same ones in a google-services file), not secrets.
     * @return array{api_key:string, project_id:string, sender_id:string, ios_app_id:string, android_app_id:string}|null
     */
    public static function clientConfig(): ?array
    {
        $account = self::account();
        $config = [
            'api_key' => trim((string) Env::get('FIREBASE_API_KEY', '')),
            'project_id' => trim((string) Env::get('FIREBASE_PROJECT_ID', (string) ($account['project_id'] ?? ''))),
            'sender_id' => trim((string) Env::get('FIREBASE_SENDER_ID', '')),
            'ios_app_id' => trim((string) Env::get('FIREBASE_IOS_APP_ID', '')),
            'android_app_id' => trim((string) Env::get('FIREBASE_ANDROID_APP_ID', '')),
        ];
        if ($account === null || $config['api_key'] === '' || $config['project_id'] === '' || $config['sender_id'] === '' || ($config['ios_app_id'] === '' && $config['android_app_id'] === '')) {
            return null;
        }
        return $config;
    }

    /** What is still missing before push works, in plain words, for the admin page. Empty when ready. */
    public static function missing(): array
    {
        $missing = [];
        if (self::account() === null) {
            $missing[] = 'The Firebase service account key (storage/firebase-service-account.json on the server).';
        }
        foreach (['FIREBASE_API_KEY' => 'Web API key', 'FIREBASE_SENDER_ID' => 'Sender ID'] as $key => $label) {
            if (trim((string) Env::get($key, '')) === '') {
                $missing[] = $label . ' (' . $key . ' in .env).';
            }
        }
        if (trim((string) Env::get('FIREBASE_IOS_APP_ID', '')) === '' && trim((string) Env::get('FIREBASE_ANDROID_APP_ID', '')) === '') {
            $missing[] = 'The Firebase app ID of the iPhone app (FIREBASE_IOS_APP_ID) or the Android app (FIREBASE_ANDROID_APP_ID) in .env.';
        }
        return $missing;
    }

    /** Send to every device the person is signed in on. $data values must be short strings. */
    public static function toUser(int $userId, string $title, string $body, array $data = []): void
    {
        try {
            $stmt = Database::connection()->prepare('SELECT id, push_token FROM mobile_tokens WHERE user_id = :uid AND push_token IS NOT NULL');
            $stmt->execute(['uid' => $userId]);
            self::deliver($stmt->fetchAll(), $title, $body, $data);
        } catch (Throwable $e) {
            error_log(sprintf('[%s] push to user %d: %s', gmdate('c'), $userId, $e->getMessage()));
        }
    }

    /**
     * Send one message to many devices (an admin's update). $devices are mobile_tokens rows with id and push_token.
     * @return array{delivered:int, failed:int}
     */
    public static function toDevices(array $devices, string $title, string $body, array $data = []): array
    {
        try {
            return self::deliver($devices, $title, $body, $data);
        } catch (Throwable $e) {
            error_log(sprintf('[%s] push to %d devices: %s', gmdate('c'), count($devices), $e->getMessage()));
            return ['delivered' => 0, 'failed' => count($devices)];
        }
    }

    /** @return array{delivered:int, failed:int} */
    private static function deliver(array $devices, string $title, string $body, array $data): array
    {
        $result = ['delivered' => 0, 'failed' => 0];
        $account = self::account();
        if ($account === null || !$devices) {
            return $result;
        }
        $pdo = Database::connection();
        $access = self::accessToken($account);
        $url = self::host('https://fcm.googleapis.com') . '/v1/projects/' . rawurlencode((string) $account['project_id']) . '/messages:send';
        foreach ($devices as $d) {
            $status = self::post($url, [
                'message' => [
                    'token' => $d['push_token'],
                    'notification' => ['title' => mb_substr($title, 0, 120), 'body' => mb_substr($body, 0, 300)],
                    'data' => array_map('strval', $data),
                    'android' => ['priority' => 'HIGH'],
                    'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                ],
            ], $access);
            if ($status >= 200 && $status < 300) {
                $result['delivered']++;
                continue;
            }
            $result['failed']++;
            if (in_array($status, [400, 404], true)) {
                // The app was removed or the address changed: stop sending to it.
                $pdo->prepare('UPDATE mobile_tokens SET push_token = NULL WHERE id = :id')->execute(['id' => (int) $d['id']]);
            }
        }
        return $result;
    }

    /** Tests point Google's hosts at a local fake server. Ignored in production. */
    private static function host(string $real): string
    {
        $test = config('app.is_production') ? '' : rtrim((string) Env::get('PUSH_TEST_BASE', ''), '/');
        return $test !== '' ? $test . '/' . parse_url($real, PHP_URL_HOST) : $real;
    }

    private static function protocols(): int
    {
        return CURLPROTO_HTTPS | (!config('app.is_production') && Env::get('PUSH_TEST_BASE', '') !== '' ? CURLPROTO_HTTP : 0);
    }

    private static function account(): ?array
    {
        static $account = false;
        if ($account === false) {
            $path = (string) Env::get('FIREBASE_SERVICE_ACCOUNT', BASE_PATH . '/storage/firebase-service-account.json');
            $data = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
            $account = is_array($data) && !empty($data['client_email']) && !empty($data['private_key']) && !empty($data['project_id']) ? $data : null;
        }
        return $account;
    }

    /** A Google access token for the service account, cached on disk until shortly before it expires. */
    private static function accessToken(array $account): string
    {
        $cache = BASE_PATH . '/storage/logs/fcm-access.json';
        $saved = is_readable($cache) ? json_decode((string) file_get_contents($cache), true) : null;
        if (is_array($saved) && ($saved['email'] ?? '') === $account['client_email'] && (int) ($saved['expires'] ?? 0) > time() + 60) {
            return (string) $saved['token'];
        }
        $b64 = static fn (string $v): string => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $tokenUri = (string) ($account['token_uri'] ?? 'https://oauth2.googleapis.com/token');
        $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b64(json_encode(['iss' => $account['client_email'], 'scope' => self::SCOPE, 'aud' => $tokenUri, 'iat' => time(), 'exp' => time() + 3600]));
        if (!openssl_sign($unsigned, $signature, (string) $account['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign with the Firebase service account key.');
        }
        $ch = curl_init(self::host('https://oauth2.googleapis.com') === 'https://oauth2.googleapis.com' ? $tokenUri : self::host('https://oauth2.googleapis.com') . '/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_PROTOCOLS => self::protocols(), CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsigned . '.' . $b64($signature)]),
        ]);
        $data = json_decode((string) curl_exec($ch), true);
        if (empty($data['access_token'])) {
            throw new RuntimeException('Google did not return an access token for push.');
        }
        @file_put_contents($cache, json_encode(['email' => $account['client_email'], 'token' => $data['access_token'], 'expires' => time() + (int) ($data['expires_in'] ?? 3600)]), LOCK_EX);
        @chmod($cache, 0600);
        return (string) $data['access_token'];
    }

    private static function post(string $url, array $body, string $bearer): int
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_PROTOCOLS => self::protocols(), CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $bearer],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        curl_exec($ch);
        return (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    }
}

/**
 * Updates an admin sends to people who use the mobile app (/admin/push): a push notification on their
 * phones and, unless switched off, an entry in their Notifications list so nobody misses it.
 */
final class Broadcasts
{
    public const AUDIENCES = ['all' => 'Everyone using the app', 'ios' => 'iPhone and iPad', 'android' => 'Android'];
    /** Where a tap takes the person in the app. */
    public const LINKS = ['/app/notifications' => 'Notifications', '/app' => 'Home', '/app/publish' => 'Publish', '/app/community' => 'Community', '/app/insights' => 'Insights'];
    public const TITLE_MAX = 65;
    public const BODY_MAX = 240;

    /** Data for the admin page. */
    public static function page(): array
    {
        $pdo = Database::connection();
        $reach = [];
        foreach (array_keys(self::AUDIENCES) as $audience) {
            $reach[$audience] = ['people' => count(self::people($audience)), 'devices' => count(self::devices($audience))];
        }
        return [
            'reach' => $reach,
            'missing' => Push::missing(),
            'history' => $pdo->query('SELECT * FROM push_broadcasts ORDER BY id DESC LIMIT 25')->fetchAll(),
        ];
    }

    /** POST /admin/push */
    public static function send(): never
    {
        $admin = Auth::requireAdmin();
        $back = '/admin/push';
        $title = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['title'] ?? '')))), 0, self::TITLE_MAX);
        $body = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['body'] ?? '')))), 0, self::BODY_MAX);
        $audience = array_key_exists((string) ($_POST['audience'] ?? ''), self::AUDIENCES) ? (string) $_POST['audience'] : 'all';
        $url = array_key_exists((string) ($_POST['url'] ?? ''), self::LINKS) ? (string) $_POST['url'] : '/app/notifications';
        $inbox = !empty($_POST['in_inbox']);
        $old = ['title' => $title, 'body' => $body, 'audience' => $audience, 'url' => $url, 'in_inbox' => $inbox];
        $fail = static function (string $message) use ($back, $old): never {
            flash('form', ['form' => 'admin-push', 'status' => 'error', 'message' => $message, 'errors' => [], 'old' => $old]);
            redirect($back);
        };
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $fail('Your session expired. Refresh the page and try again.');
        }
        if ($title === '' || $body === '') {
            $fail('Write a title and a message.');
        }
        $pdo = Database::connection();
        $same = $pdo->prepare('SELECT 1 FROM push_broadcasts WHERE title = :t AND body = :b AND audience = :a AND created_at > :since LIMIT 1');
        $same->execute(['t' => $title, 'b' => $body, 'a' => $audience, 'since' => gmdate('Y-m-d H:i:s', time() - 120)]);
        if ($same->fetchColumn()) {
            $fail('That exact update was sent a moment ago. It was not sent twice.');
        }
        $devices = self::devices($audience);
        $people = self::people($audience);
        if (!$devices && (!$inbox || !$people)) {
            $fail($people ? 'No phone in this group has notifications turned on yet. Tick “Also add to their Notifications list” to reach them in the app.' : 'Nobody in this group has signed in to the app yet.');
        }
        if ($devices && !Push::enabled()) {
            $fail('Push isn’t set up on the server yet (see the checklist on this page). Nothing was sent.');
        }
        @set_time_limit(300);
        $now = Database::now();
        if ($inbox) {
            $insert = $pdo->prepare('INSERT INTO notifications (user_id, workspace_id, kind, title, body, url, created_at) VALUES (:uid, :ws, :k, :t, :b, :url, :now)');
            foreach ($people as $uid) {
                $insert->execute(['uid' => $uid, 'ws' => $uid, 'k' => 'announcement', 't' => $title, 'b' => $body, 'url' => $url, 'now' => $now]);
            }
        }
        $result = Push::toDevices($devices, $title, $body, ['kind' => 'announcement', 'url' => $url]);
        $pdo->prepare('INSERT INTO push_broadcasts (title, body, url, audience, in_inbox, people, devices, delivered, failed, sent_by, created_at) VALUES (:t, :b, :url, :a, :inbox, :people, :devices, :ok, :bad, :by, :now)')
            ->execute(['t' => $title, 'b' => $body, 'url' => $url, 'a' => $audience, 'inbox' => $inbox ? 1 : 0, 'people' => $inbox ? count($people) : 0, 'devices' => count($devices), 'ok' => $result['delivered'], 'bad' => $result['failed'], 'by' => mb_substr((string) ($admin['email'] ?? ''), 0, 190) ?: null, 'now' => $now]);
        $parts = [];
        if ($devices) {
            $parts[] = sprintf('Push delivered to %d of %d %s', $result['delivered'], count($devices), count($devices) === 1 ? 'phone' : 'phones');
        }
        if ($inbox) {
            $parts[] = sprintf('added to the Notifications list of %d %s', count($people), count($people) === 1 ? 'person' : 'people');
        }
        flash('notice', ucfirst(implode(', and ', $parts)) . '.');
        redirect($back);
    }

    /** Phones in the audience that can receive a push. */
    private static function devices(string $audience): array
    {
        [$where, $params] = self::platform($audience);
        $stmt = Database::connection()->prepare('SELECT id, push_token FROM mobile_tokens WHERE push_token IS NOT NULL' . $where);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** People in the audience who are signed in to the app (with or without notifications allowed). */
    private static function people(string $audience): array
    {
        [$where, $params] = self::platform($audience);
        $stmt = Database::connection()->prepare('SELECT DISTINCT user_id FROM mobile_tokens WHERE 1 = 1' . $where);
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function platform(string $audience): array
    {
        return in_array($audience, ['ios', 'android'], true) ? [' AND platform = :p', ['p' => $audience]] : ['', []];
    }
}
