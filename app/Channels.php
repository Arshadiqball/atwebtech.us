<?php

declare(strict_types=1);

/** Connected social channels. YouTube (Google) and a personal LinkedIn Profile can connect; the rest are not built yet. */
final class Channels
{
    public const YOUTUBE_SCOPE = 'https://www.googleapis.com/auth/youtube.readonly';
    public const UPLOAD_SCOPE = 'https://www.googleapis.com/auth/youtube.upload';
    public const LINKEDIN_SIGNIN = 'openid profile email';
    public const LINKEDIN_SHARE = 'w_member_social';
    public const GOALS = [
        1 => ['Keep it steady', '1 time/week', 'purple'],
        3 => ['Build a presence', '3 times/week', 'orange'],
        5 => ['Reach new heights', '5 times/week', 'teal'],
    ];
    public const MAX_GOAL = 14;
    private const AVATAR_HOSTS = ['yt3.ggpht.com', 'yt3.googleusercontent.com', 'media.licdn.com', 'media-exp1.licdn.com', 'media-exp2.licdn.com'];
    private const DAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    public static function connectable(string $provider): bool
    {
        return match ($provider) {
            'youtube' => config('oauth.google.client_id') !== '' && config('oauth.google.client_secret') !== '',
            'linkedin' => config('oauth.linkedin.client_id') !== '' && config('oauth.linkedin.client_secret') !== '',
            default => false,
        };
    }

    public static function forUser(int $uid): array
    {
        $stmt = Database::connection()->prepare('SELECT id, provider, external_id, name, kind, avatar_url, posting_goal, schedule, scopes, queue_intro_closed_at, created_at FROM channels WHERE user_id = :uid ORDER BY id');
        $stmt->execute(['uid' => $uid]);
        return array_map([self::class, 'hydrate'], $stmt->fetchAll());
    }

    public static function find(int $uid, int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, user_id, provider, external_id, name, kind, avatar_url, posting_goal, schedule, scopes, queue_intro_closed_at, access_token, refresh_token, token_expires_at, created_at FROM channels WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $id, 'uid' => $uid]);
        $row = $stmt->fetch();
        return $row ? self::hydrate($row) : null;
    }

    private static function hydrate(array $row): array
    {
        $row['times'] = json_decode((string) ($row['schedule'] ?? ''), true) ?: [];
        $scopes = (string) ($row['scopes'] ?? '');
        $row['can_publish'] = match ($row['provider']) {
            'youtube' => str_contains($scopes, self::UPLOAD_SCOPE),
            'linkedin' => str_contains($scopes, self::LINKEDIN_SHARE),
            default => false,
        };
        return $row;
    }

    /** A usable access token, refreshed with the sealed refresh token when it is about to expire. */
    public static function accessToken(array $channel): string
    {
        $access = Security::unseal($channel['access_token'] ?? null);
        if ($access !== null && strtotime((string) $channel['token_expires_at'] . ' UTC') > time() + 120) {
            return $access;
        }
        $refresh = Security::unseal($channel['refresh_token'] ?? null);
        $network = $channel['provider'] === 'linkedin' ? 'LinkedIn' : 'Google';
        if ($refresh === null) {
            throw new ChannelAuthException($network . ' access expired. Reconnect the channel to keep publishing.');
        }
        $creds = $channel['provider'] === 'linkedin' ? config('oauth.linkedin') : config('oauth.google');
        try {
            $data = self::request('POST', self::endpoint($channel['provider'], 'token'), [
                'client_id' => $creds['client_id'],
                'client_secret' => $creds['client_secret'],
                'refresh_token' => $refresh,
                'grant_type' => 'refresh_token',
            ]);
        } catch (ChannelApiException $e) {
            if ($e->status === 400 || $e->status === 401) {
                throw new ChannelAuthException($network . ' access was revoked. Reconnect the channel to keep publishing.');
            }
            throw $e;
        }
        if (empty($data['access_token']) || !is_string($data['access_token'])) {
            throw new RuntimeException('Refresh response had no access token.');
        }
        Database::connection()->prepare('UPDATE channels SET access_token = :access, token_expires_at = :expires, updated_at = :now WHERE id = :id')->execute([
            'access' => Security::seal($data['access_token']),
            'expires' => gmdate('Y-m-d H:i:s', time() + max(60, (int) ($data['expires_in'] ?? 3600))),
            'now' => Database::now(),
            'id' => (int) $channel['id'],
        ]);
        return $data['access_token'];
    }

    /**
     * Upload a video with YouTube's resumable protocol: one metadata request, then the file in a single PUT.
     * Returns the new video id.
     */
    public static function uploadVideo(string $token, string $file, string $mime, array $metadata, bool $notify): string
    {
        $size = filesize($file);
        if ($size === false || $size === 0) {
            throw new RuntimeException('The video file is missing on the server.');
        }
        $url = self::endpoint('youtube', 'upload') . '?' . http_build_query(['uploadType' => 'resumable', 'part' => 'snippet,status', 'notifySubscribers' => $notify ? 'true' : 'false']);
        $ch = self::curl($url, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json; charset=UTF-8',
            'X-Upload-Content-Length: ' . $size,
            'X-Upload-Content-Type: ' . $mime,
        ], 30);
        $location = null;
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($metadata, JSON_THROW_ON_ERROR),
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$location): int {
                if (stripos($line, 'location:') === 0) {
                    $location = trim(substr($line, 9));
                }
                return strlen($line);
            },
        ]);
        self::finish($ch, $url);
        $scheme = parse_url((string) $location, PHP_URL_SCHEME);
        if ($location === null || !in_array($scheme, config('app.is_production') ? ['https'] : ['https', 'http'], true)) {
            throw new RuntimeException('YouTube didn’t return an upload address.');
        }

        $handle = fopen($file, 'rb');
        $ch = self::curl($location, ['Authorization: Bearer ' . $token, 'Content-Type: ' . $mime], 0);
        curl_setopt_array($ch, [CURLOPT_UPLOAD => true, CURLOPT_INFILE => $handle, CURLOPT_INFILESIZE => $size]);
        try {
            $data = self::finish($ch, $location);
        } finally {
            fclose($handle);
        }
        $id = (string) ($data['id'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id)) {
            throw new RuntimeException('YouTube accepted the upload but returned no video id.');
        }
        return $id;
    }

    public static function redirectUri(string $provider): string
    {
        return self::baseUrl() . '/app/channels/callback/' . $provider;
    }

    /** The address LinkedIn and Google must send the browser back to. */
    private static function baseUrl(): string
    {
        $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        if ($host === 'atwebtech.us' || $host === 'www.atwebtech.us') {
            return 'https://atwebtech.us';
        }
        return rtrim((string) config('app.url'), '/');
    }

    /** API hosts. Outside production, GOOGLE_OAUTH_TEST_BASE / LINKEDIN_OAUTH_TEST_BASE point at a local fake. */
    private static function endpoint(string $provider, string $name): string
    {
        $env = $provider === 'linkedin' ? 'LINKEDIN_OAUTH_TEST_BASE' : 'GOOGLE_OAUTH_TEST_BASE';
        $test = config('app.is_production') ? '' : rtrim((string) Env::get($env, ''), '/');
        if ($provider === 'linkedin') {
            return match ($name) {
                'token' => $test !== '' ? $test . '/oauth/v2/accessToken' : 'https://www.linkedin.com/oauth/v2/accessToken',
                'revoke' => $test !== '' ? $test . '/oauth/v2/revoke' : 'https://www.linkedin.com/oauth/v2/revoke',
                'me' => $test !== '' ? $test . '/v2/me' : 'https://api.linkedin.com/v2/me',
                'userinfo' => $test !== '' ? $test . '/v2/userinfo' : 'https://api.linkedin.com/v2/userinfo',
                'assets' => $test !== '' ? $test . '/v2/assets' : 'https://api.linkedin.com/v2/assets',
                'ugc' => $test !== '' ? $test . '/v2/ugcPosts' : 'https://api.linkedin.com/v2/ugcPosts',
                'social' => $test !== '' ? $test . '/v2/socialActions' : 'https://api.linkedin.com/v2/socialActions',
            };
        }
        return match ($name) {
            'token' => $test !== '' ? $test . '/token' : 'https://oauth2.googleapis.com/token',
            'revoke' => $test !== '' ? $test . '/revoke' : 'https://oauth2.googleapis.com/revoke',
            'channels' => $test !== '' ? $test . '/youtube/v3/channels' : 'https://www.googleapis.com/youtube/v3/channels',
            'upload' => $test !== '' ? $test . '/upload/youtube/v3/videos' : 'https://www.googleapis.com/upload/youtube/v3/videos',
        };
    }

    public static function authorizeUrl(string $provider, string $state, string $verifier, bool $switchAccount = false): string
    {
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        if ($provider === 'linkedin') {
            return 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
                'response_type' => 'code',
                'client_id' => config('oauth.linkedin.client_id'),
                'redirect_uri' => self::redirectUri('linkedin'),
                'scope' => self::LINKEDIN_SIGNIN . ' ' . self::LINKEDIN_SHARE,
                'state' => $state,
                'code_challenge' => $challenge,
                'code_challenge_method' => 'S256',
            ], '', '&', PHP_QUERY_RFC3986);
        }
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => config('oauth.google.client_id'),
            'redirect_uri' => self::redirectUri('youtube'),
            'response_type' => 'code',
            'scope' => self::YOUTUBE_SCOPE . ' ' . self::UPLOAD_SCOPE,
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => $switchAccount ? 'select_account consent' : 'consent',
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** Swap an authorization code for tokens. */
    public static function exchange(string $provider, string $code, string $verifier): array
    {
        $creds = $provider === 'linkedin' ? config('oauth.linkedin') : config('oauth.google');
        $data = self::request('POST', self::endpoint($provider, 'token'), [
            'code' => $code,
            'client_id' => $creds['client_id'],
            'client_secret' => $creds['client_secret'],
            'redirect_uri' => self::redirectUri($provider),
            'grant_type' => 'authorization_code',
            'code_verifier' => $verifier,
        ]);
        if (empty($data['access_token']) || !is_string($data['access_token'])) {
            throw new RuntimeException('Token response had no access token.');
        }
        $fallback = $provider === 'linkedin' ? self::LINKEDIN_SIGNIN . ' ' . self::LINKEDIN_SHARE : self::YOUTUBE_SCOPE;
        return [
            'access_token' => $data['access_token'],
            'refresh_token' => is_string($data['refresh_token'] ?? null) ? $data['refresh_token'] : null,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + max(60, (int) ($data['expires_in'] ?? 3600))),
            'scope' => mb_substr((string) ($data['scope'] ?? $fallback), 0, 500),
        ];
    }

    /** Accounts the signed-in user can add: YouTube channels, or one LinkedIn Profile. */
    public static function accounts(string $provider, string $accessToken): array
    {
        return $provider === 'linkedin' ? self::linkedinProfile($accessToken) : self::youtubeChannels($accessToken);
    }

    /** YouTube channels the signed-in Google account (or brand account) can manage. */
    public static function youtubeChannels(string $accessToken): array
    {
        $data = self::request('GET', self::endpoint('youtube', 'channels') . '?' . http_build_query(['part' => 'snippet', 'mine' => 'true', 'maxResults' => 50]), null, $accessToken);
        $out = [];
        foreach (is_array($data['items'] ?? null) ? $data['items'] : [] as $item) {
            $id = (string) ($item['id'] ?? '');
            if ($id === '' || mb_strlen($id) > 191) {
                continue;
            }
            $thumb = (string) ($item['snippet']['thumbnails']['default']['url'] ?? '');
            $out[] = [
                'id' => $id,
                'name' => mb_substr(trim((string) ($item['snippet']['title'] ?? '')) ?: 'YouTube channel', 0, 190),
                'avatar' => self::safeAvatar($thumb),
                'kind' => 'Channel',
            ];
        }
        return $out;
    }

    /** The signed-in member's personal LinkedIn Profile. Company Pages are not available yet. */
    public static function linkedinProfile(string $accessToken): array
    {
        // OpenID sign-in does not include permission for /v2/me. Name and id come from userinfo.
        try {
            $info = self::request('GET', self::endpoint('linkedin', 'userinfo'), null, $accessToken);
        } catch (Throwable $e) {
            error_log(sprintf('[%s] linkedin userinfo failed: %s', gmdate('c'), $e->getMessage()));
            return [];
        }
        $id = (string) ($info['sub'] ?? '');
        if (str_starts_with($id, 'urn:li:person:')) {
            $id = substr($id, strlen('urn:li:person:'));
        }
        if ($id === '' || mb_strlen($id) > 191 || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
            return [];
        }
        $name = trim((string) ($info['name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($info['given_name'] ?? '') . ' ' . (string) ($info['family_name'] ?? ''));
        }
        return [[
            'id' => $id,
            'name' => mb_substr($name !== '' ? $name : 'LinkedIn Profile', 0, 190),
            'avatar' => self::safeAvatar((string) ($info['picture'] ?? '')),
            'kind' => 'Profile',
        ]];
    }

    /**
     * Publish a text or image post to a personal LinkedIn Profile.
     * Returns the post URN (used as the public feed URL).
     */
    public static function linkedinShare(string $token, string $personId, string $text, ?string $imagePath, ?string $imageMime): string
    {
        $author = 'urn:li:person:' . $personId;
        $content = [
            'com.linkedin.ugc.ShareContent' => [
                'shareCommentary' => ['text' => $text],
                'shareMediaCategory' => 'NONE',
            ],
        ];
        if ($imagePath !== null) {
            $asset = self::linkedinImage($token, $author, $imagePath, (string) $imageMime);
            $content['com.linkedin.ugc.ShareContent']['shareMediaCategory'] = 'IMAGE';
            $content['com.linkedin.ugc.ShareContent']['media'] = [[
                'status' => 'READY',
                'media' => $asset,
                'title' => ['text' => mb_substr($text, 0, 80)],
            ]];
        }
        $data = self::requestJson('POST', self::endpoint('linkedin', 'ugc'), [
            'author' => $author,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => $content,
            'visibility' => ['com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'],
        ], $token);
        $id = (string) ($data['id'] ?? '');
        if ($id === '' || !str_starts_with($id, 'urn:li:')) {
            throw new RuntimeException('LinkedIn accepted the post but returned no id.');
        }
        return $id;
    }

    /** Post a first comment on a LinkedIn share the member just published. */
    public static function linkedinComment(string $token, string $personId, string $shareUrn, string $text): void
    {
        if (!preg_match('/^urn:li:[a-zA-Z0-9:]+$/', $shareUrn)) {
            throw new RuntimeException('The LinkedIn post id was not valid.');
        }
        self::requestJson('POST', self::endpoint('linkedin', 'social') . '/' . rawurlencode($shareUrn) . '/comments', [
            'actor' => 'urn:li:person:' . $personId,
            'message' => ['text' => $text],
        ], $token);
    }

    private static function linkedinImage(string $token, string $owner, string $file, string $mime): string
    {
        $size = filesize($file);
        if ($size === false || $size === 0) {
            throw new RuntimeException('The image file is missing on the server.');
        }
        $registered = self::requestJson('POST', self::endpoint('linkedin', 'assets') . '?action=registerUpload', [
            'registerUploadRequest' => [
                'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                'owner' => $owner,
                'serviceRelationships' => [['relationshipType' => 'OWNER', 'identifier' => 'urn:li:userGeneratedContent']],
            ],
        ], $token);
        $uploadUrl = (string) ($registered['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'] ?? '');
        $asset = (string) ($registered['value']['asset'] ?? '');
        $scheme = parse_url($uploadUrl, PHP_URL_SCHEME);
        if ($asset === '' || !in_array($scheme, config('app.is_production') ? ['https'] : ['https', 'http'], true)) {
            throw new RuntimeException('LinkedIn didn’t return an image upload address.');
        }
        $handle = fopen($file, 'rb');
        $ch = self::curl($uploadUrl, ['Authorization: Bearer ' . $token, 'Content-Type: ' . $mime], 60);
        curl_setopt_array($ch, [CURLOPT_UPLOAD => true, CURLOPT_INFILE => $handle, CURLOPT_INFILESIZE => $size]);
        try {
            self::finish($ch, $uploadUrl);
        } finally {
            fclose($handle);
        }
        return $asset;
    }

    public static function revoke(?string $token, string $provider = 'youtube'): void
    {
        if ($token === null || $token === '') {
            return;
        }
        try {
            $form = ['token' => $token];
            if ($provider === 'linkedin') {
                $form['client_id'] = config('oauth.linkedin.client_id');
                $form['client_secret'] = config('oauth.linkedin.client_secret');
            }
            self::request('POST', self::endpoint($provider, 'revoke'), $form);
        } catch (Throwable $e) {
            error_log(sprintf('[%s] token revoke failed: %s', gmdate('c'), $e->getMessage()));
        }
    }

    private static function safeAvatar(string $url): ?string
    {
        $parts = parse_url($url);
        return ($parts['scheme'] ?? '') === 'https' && in_array($parts['host'] ?? '', self::AVATAR_HOSTS, true) && strlen($url) <= 500 ? $url : null;
    }

    private static function request(string $method, string $url, ?array $form = null, ?string $bearer = null): array
    {
        $ch = self::curl($url, $bearer !== null ? ['Authorization: Bearer ' . $bearer] : [], 12);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form ?? []));
        }
        return self::finish($ch, $url);
    }

    private static function requestJson(string $method, string $url, array $payload, string $bearer): array
    {
        $restli = '';
        $ch = self::curl($url, ['Authorization: Bearer ' . $bearer, 'Content-Type: application/json', 'X-Restli-Protocol-Version: 2.0.0'], 20);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($ch, string $line) use (&$restli): int {
            if (stripos($line, 'x-restli-id:') === 0) {
                $restli = trim(substr($line, 12));
            }
            return strlen($line);
        });
        $data = self::finish($ch, $url);
        if ($restli !== '' && empty($data['id'])) {
            $data['id'] = $restli;
        }
        return $data;
    }

    /** @param int $timeout seconds; 0 waits as long as the transfer takes (large uploads) */
    private static function curl(string $url, array $headers, int $timeout): CurlHandle
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | (config('app.is_production') ? 0 : CURLPROTO_HTTP),
        ]);
        return $ch;
    }

    private static function finish(CurlHandle $ch, string $url): array
    {
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        if ($body === false) {
            throw new ChannelApiException('Request failed: ' . $error, 0);
        }
        $data = json_decode((string) $body, true);
        if ($status < 200 || $status >= 300) {
            $reason = is_array($data) ? (string) ($data['error_description'] ?? ($data['error']['message'] ?? (is_string($data['error'] ?? null) ? $data['error'] : ''))) : '';
            throw new ChannelApiException(sprintf('HTTP %d from %s %s', $status, parse_url($url, PHP_URL_HOST), mb_substr(strip_tags($reason), 0, 200)), $status);
        }
        return is_array($data) ? $data : [];
    }

    /** Suggested weekly posting times for a goal: evenings first, a midday slot once every day is used. */
    public static function schedule(int $goal): array
    {
        $goal = max(1, min(self::MAX_GOAL, $goal));
        $order = [5, 3, 1, 2, 4, 6, 7];
        $patterns = [1 => [5], 2 => [2, 5], 3 => [1, 3, 5], 4 => [1, 2, 4, 5], 5 => [1, 2, 3, 4, 5], 6 => [1, 2, 3, 4, 5, 6], 7 => [1, 2, 3, 4, 5, 6, 7]];
        $times = [];
        foreach ($patterns[min(7, $goal)] as $day) {
            $times[] = ['day' => $day, 'time' => '18:00'];
        }
        for ($i = 0; $i < $goal - 7; $i++) {
            $times[] = ['day' => $order[$i], 'time' => '12:00'];
        }
        usort($times, static fn (array $a, array $b): int => [$a['day'], $a['time']] <=> [$b['day'], $b['time']]);
        return $times;
    }

    public static function timeLabel(array $slot): string
    {
        $at = DateTimeImmutable::createFromFormat('H:i', $slot['time']);
        return self::DAYS[$slot['day']] . ' at ' . ($at ? $at->format('g:i A') : $slot['time']);
    }

    public static function freeLimit(): int
    {
        return (int) data('pricing')['free_channels'];
    }
}

final class ChannelApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status)
    {
        parent::__construct($message);
    }

    public function transient(): bool
    {
        return $this->status === 0 || $this->status === 429 || $this->status >= 500;
    }
}

final class ChannelAuthException extends RuntimeException
{
}
