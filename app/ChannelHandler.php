<?php

declare(strict_types=1);

/** Channel connection flow: start OAuth, handle the callback, confirm accounts, posting goal, disconnect. */
final class ChannelHandler
{
    private const FLOW_SECONDS = 600;
    public const QUEUE_DAYS = 730;
    private const SCHEDULE_MAX = 28;

    public static function start(string $provider): never
    {
        $uid = (int) Auth::member()['id'];
        if (!Channels::connectable($provider)) {
            self::notice('/app/channels', 'Connecting this channel isn’t available yet.');
        }
        $reconnect = ($_POST['reconnect'] ?? '') === '1';
        $allowance = Channels::allowance($uid);
        if (!$reconnect && count(Channels::forUser($uid)) >= $allowance['limit']) {
            self::notice('/app/channels', sprintf('The %s plan includes %d channels. Disconnect one to add another.', $allowance['name'], $allowance['limit']));
        }
        $fingerprint = Security::clientFingerprint();
        if (RateLimiter::tooManyAttempts('channel-connect', $fingerprint, 10, 10)) {
            self::notice('/app/channels', 'Too many connection attempts. Please wait a few minutes and try again.');
        }
        RateLimiter::hit('channel-connect', $fingerprint);

        if ($provider === 'pinterest' && !Channels::pinterestOAuthReady()) {
            self::connectPinterestSandbox();
        }

        $state = bin2hex(random_bytes(24));
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $redirectUri = Channels::redirectUri($provider);
        $_SESSION['_oauth'] = ['provider' => $provider, 'state' => $state, 'verifier' => $verifier, 'redirect' => $redirectUri, 'reconnect' => $reconnect, 'at' => time()];
        redirect(Channels::authorizeUrl($provider, $state, $verifier, ($_POST['switch'] ?? '') === '1', $redirectUri, $reconnect));
    }

    public static function callback(string $provider): never
    {
        $user = Auth::require();
        $flow = $_SESSION['_oauth'] ?? null;
        unset($_SESSION['_oauth']);
        $who = match ($provider) {
            'linkedin' => 'LinkedIn',
            'tiktok' => 'TikTok',
            'pinterest' => 'Pinterest',
            'x' => 'X',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            default => 'Google',
        };

        $state = (string) ($_GET['state'] ?? '');
        if (!is_array($flow) || $flow['provider'] !== $provider || $state === '' || !hash_equals($flow['state'], $state) || time() - (int) $flow['at'] > self::FLOW_SECONDS) {
            self::notice('/app/channels', 'That connection link expired or didn’t come from this session. Please try again.');
        }
        if (isset($_GET['error'])) {
            self::notice('/app/channels', $_GET['error'] === 'access_denied' ? 'Connection cancelled. Nothing was connected.' : $who . ' didn’t complete the connection. Please try again.');
        }
        $code = (string) ($_GET['code'] ?? '');
        if ($code === '' || strlen($code) > 2048) {
            self::notice('/app/channels', $who . ' didn’t return an authorization code. Please try again.');
        }

        try {
            $redirectUri = is_string($flow['redirect'] ?? null) ? $flow['redirect'] : null;
            $tokens = Channels::exchange($provider, $code, (string) $flow['verifier'], $redirectUri);
            if ($provider === 'youtube' && !str_contains($tokens['scope'], Channels::YOUTUBE_SCOPE)) {
                Channels::revoke($tokens['access_token'], $provider);
                self::notice('/app/channels', 'YouTube access wasn’t granted. Tick the YouTube permission on the Google screen to connect.');
            }
            if ($provider === 'linkedin' && !str_contains($tokens['scope'], 'profile') && !str_contains($tokens['scope'], 'openid')) {
                Channels::revoke($tokens['access_token'], $provider);
                self::notice('/app/channels', 'LinkedIn profile access wasn’t granted. Tick the profile permission to connect.');
            }
            if ($provider === 'tiktok' && !str_contains($tokens['scope'], Channels::TIKTOK_BASIC)) {
                Channels::revoke($tokens['access_token'], $provider);
                self::notice('/app/channels', 'TikTok profile access wasn’t granted. Tick the profile permission to connect.');
            }
            if ($provider === 'x' && !str_contains($tokens['scope'], 'users.read')) {
                Channels::revoke($tokens['access_token'], $provider);
                self::notice('/app/channels', 'X profile access wasn’t granted. Tick the profile permission to connect.');
            }
            if ($provider === 'instagram' && !str_contains($tokens['scope'], 'instagram_business_basic')) {
                Channels::revoke($tokens['access_token'], $provider);
                self::notice('/app/channels', 'Instagram profile access wasn’t granted. Allow the profile permission to connect.');
            }
            if ($provider === 'facebook' && !str_contains($tokens['scope'], 'pages_show_list')) {
                Channels::revoke($tokens['access_token'], $provider);
                self::notice('/app/channels', 'Facebook Page access wasn’t granted. Tick the Page permissions to connect.');
            }
            $accounts = Channels::accounts($provider, $tokens['access_token']);
            if (!$accounts && $provider === 'linkedin') {
                $accounts = Channels::profileFromIdToken((string) ($tokens['id_token'] ?? ''));
            }
            unset($tokens['id_token']);
        } catch (Throwable $e) {
            error_log(sprintf('[%s] %s connect failed for user %d: %s', gmdate('c'), $provider, (int) $user['id'], $e->getMessage()));
            $detail = 'Please try again in a few minutes.';
            if ($e instanceof ChannelApiException && $e->status >= 400 && $e->status < 500) {
                $detail = self::connectRefusal($e->getMessage(), $provider);
            }
            self::notice('/app/channels', sprintf('We couldn’t reach %s to finish connecting. %s', $who, $detail));
        }
        if (!$accounts) {
            Channels::revoke($tokens['access_token'], $provider);
            self::notice('/app/channels', match ($provider) {
                'linkedin' => 'We couldn’t read this LinkedIn Profile. Try another account, or try again in a few minutes.',
                'tiktok' => 'We couldn’t read this TikTok account. Use a TikTok login that is added as a sandbox target user, then try again.',
                'pinterest' => 'We couldn’t read this Pinterest account. Try again in a few minutes.',
                'x' => 'We couldn’t read this X account. Try again in a few minutes.',
                'facebook' => 'This Facebook account doesn’t manage a Page. Create a Page, or sign in with an account that manages one, then try again.',
                'instagram' => 'This Instagram account can’t be connected. Switch it to a Professional account (Business or Creator), and make sure that account is an admin of the app, then try again.',
                default => 'This Google account doesn’t have a YouTube channel. Create one on YouTube or pick another account, then try again.',
            });
        }

        $_SESSION['_oauth_pending'] = [
            'provider' => $provider,
            'tokens' => Security::seal(json_encode($tokens, JSON_THROW_ON_ERROR)),
            'accounts' => $accounts,
            'reconnect' => !empty($flow['reconnect']),
            'at' => time(),
        ];
        redirect('/app/channels/confirm');
    }

    /** Pending accounts from the last callback, or null when there are none or they expired. */
    public static function pending(): ?array
    {
        $pending = $_SESSION['_oauth_pending'] ?? null;
        if (!is_array($pending) || time() - (int) $pending['at'] > self::FLOW_SECONDS) {
            unset($_SESSION['_oauth_pending']);
            return null;
        }
        return $pending;
    }

    public static function finish(): never
    {
        $uid = (int) Auth::member()['id'];
        $pending = self::pending();
        if ($pending === null) {
            self::notice('/app/channels', 'That connection expired. Please connect the channel again.');
        }
        if (($_POST['action'] ?? '') === 'cancel') {
            unset($_SESSION['_oauth_pending']);
            $tokens = json_decode((string) Security::unseal($pending['tokens']), true) ?: [];
            if (!self::hasProvider($uid, $pending['provider'])) {
                Channels::revoke($tokens['refresh_token'] ?? $tokens['access_token'] ?? null, $pending['provider']);
            }
            self::notice('/app/channels', 'Connection cancelled. Nothing was connected.');
        }

        $byId = array_column($pending['accounts'], null, 'id');
        $picked = array_values(array_intersect(array_map('strval', (array) ($_POST['accounts'] ?? [])), array_keys($byId)));
        if (!$picked) {
            flash('form', ['form' => 'confirm', 'status' => 'error', 'message' => 'Choose at least one channel to connect.', 'errors' => [], 'old' => []]);
            redirect('/app/channels/confirm');
        }

        $pdo = Database::connection();
        $existing = Channels::forUser($uid);
        $known = array_map(static fn (array $c): string => $c['provider'] . ':' . $c['external_id'], $existing);
        $new = array_filter($picked, static fn (string $id): bool => !in_array($pending['provider'] . ':' . $id, $known, true));
        $allowance = Channels::allowance($uid);
        if (count($existing) + count($new) > $allowance['limit']) {
            flash('form', ['form' => 'confirm', 'status' => 'error', 'message' => sprintf('The %s plan includes %d channels. You can add %d more.', $allowance['name'], $allowance['limit'], max(0, $allowance['limit'] - count($existing))), 'errors' => [], 'old' => []]);
            redirect('/app/channels/confirm');
        }

        $tokens = json_decode((string) Security::unseal($pending['tokens']), true);
        if (!is_array($tokens) || empty($tokens['access_token'])) {
            unset($_SESSION['_oauth_pending']);
            self::notice('/app/channels', 'That connection expired. Please connect the channel again.');
        }
        $refresh = $tokens['refresh_token'] ? Security::seal($tokens['refresh_token']) : null;
        $now = Database::now();
        $find = $pdo->prepare('SELECT id FROM channels WHERE user_id = :uid AND provider = :provider AND external_id = :ext');
        $update = $pdo->prepare('UPDATE channels SET name = :name, avatar_url = :avatar, access_token = :access, refresh_token = COALESCE(:refresh, refresh_token), token_expires_at = :expires, scopes = :scopes, updated_at = :now WHERE id = :id');
        $insert = $pdo->prepare('INSERT INTO channels (user_id, provider, external_id, name, kind, avatar_url, access_token, refresh_token, token_expires_at, scopes, created_at, updated_at) VALUES (:uid, :provider, :ext, :name, :kind, :avatar, :access, :refresh, :expires, :scopes, :created, :updated)');

        $firstId = 0;
        $wasNew = false;
        foreach ($picked as $ext) {
            $account = $byId[$ext];
            $tokenValue = is_string($account['token'] ?? null) && $account['token'] !== '' ? $account['token'] : $tokens['access_token'];
            $access = Security::seal($tokenValue);
            $find->execute(['uid' => $uid, 'provider' => $pending['provider'], 'ext' => $ext]);
            $id = (int) $find->fetchColumn();
            if ($id > 0) {
                $update->execute(['name' => $account['name'], 'avatar' => $account['avatar'], 'access' => $access, 'refresh' => $refresh, 'expires' => $tokens['expires_at'], 'scopes' => $tokens['scope'], 'now' => $now, 'id' => $id]);
            } else {
                $insert->execute(['uid' => $uid, 'provider' => $pending['provider'], 'ext' => $ext, 'name' => $account['name'], 'kind' => $account['kind'] ?? 'Channel', 'avatar' => $account['avatar'], 'access' => $access, 'refresh' => $refresh, 'expires' => $tokens['expires_at'], 'scopes' => $tokens['scope'], 'created' => $now, 'updated' => $now]);
                $id = (int) $pdo->lastInsertId();
                $wasNew = true;
            }
            $firstId = $firstId ?: $id;
        }
        unset($_SESSION['_oauth_pending']);
        Mailer::notify('Channel connected', ['Provider' => $pending['provider'], 'Channels' => (string) count($picked)]);
        if (!empty($pending['reconnect']) && $pending['provider'] === 'youtube' && !Channels::youtubeCanPublish((string) ($tokens['scope'] ?? ''))) {
            flash('notice', 'YouTube is connected, but video upload was not allowed. Reconnect again and tick “Manage your YouTube videos”. If that choice is not listed, add the youtube.upload scope on the Google Cloud consent screen first.');
        }
        redirect('/app/channels/' . $firstId . ($wasNew ? '?setup=goal' : ''));
    }

    public static function goal(string $id): never
    {
        $uid = (int) Auth::member()['id'];
        $channel = Channels::find($uid, (int) $id);
        if ($channel === null) {
            not_found();
        }
        $choice = (string) ($_POST['goal'] ?? '');
        $goal = $choice === 'custom' ? (int) ($_POST['custom'] ?? 0) : (int) $choice;
        $setup = ($_POST['setup'] ?? '') === '1';
        $back = self::channelPath($channel, $setup ? '?setup=goal' : '');
        if ($goal < 1 || $goal > Channels::MAX_GOAL) {
            flash('form', ['form' => 'goal', 'status' => 'error', 'message' => sprintf('Choose between 1 and %d posts a week.', Channels::MAX_GOAL), 'errors' => [], 'old' => ['goal' => $choice, 'custom' => $_POST['custom'] ?? '']]);
            redirect($back);
        }
        Database::connection()->prepare('UPDATE channels SET posting_goal = :goal, schedule = :schedule, updated_at = :now WHERE id = :id AND user_id = :uid')
            ->execute(['goal' => $goal, 'schedule' => json_encode(Channels::schedule($goal)), 'now' => Database::now(), 'id' => (int) $channel['id'], 'uid' => $uid]);
        flash('notice', 'Posting goal saved.');
        redirect($setup ? '/app/channels/' . (int) $channel['id'] . '?setup=times' : self::channelPath($channel));
    }

    /** Custom posting schedule: `times[day][]` = 'HH:MM' for days 1 (Mon) to 7 (Sun). */
    public static function schedule(string $id): never
    {
        $uid = (int) Auth::member()['id'];
        $channel = Channels::find($uid, (int) $id);
        if ($channel === null) {
            not_found();
        }
        $back = self::channelPath($channel, '?tab=schedule');
        $slots = [];
        foreach ((array) ($_POST['times'] ?? []) as $day => $times) {
            $day = (int) $day;
            if ($day < 1 || $day > 7) {
                continue;
            }
            foreach ((array) $times as $time) {
                $time = trim((string) $time);
                if ($time === '') {
                    continue;
                }
                if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time)) {
                    flash('form', ['form' => 'schedule', 'status' => 'error', 'message' => 'Use times like 09:30 or 18:00.', 'errors' => [], 'old' => []]);
                    redirect($back);
                }
                $slots[$day . ' ' . $time] = ['day' => $day, 'time' => $time];
            }
        }
        if (!$slots || count($slots) > self::SCHEDULE_MAX) {
            flash('form', ['form' => 'schedule', 'status' => 'error', 'message' => sprintf('Add between 1 and %d posting times.', self::SCHEDULE_MAX), 'errors' => [], 'old' => []]);
            redirect($back);
        }
        ksort($slots);
        $slots = array_values($slots);
        Database::connection()->prepare('UPDATE channels SET posting_goal = :goal, schedule = :schedule, updated_at = :now WHERE id = :id AND user_id = :uid')
            ->execute(['goal' => min(Channels::MAX_GOAL, count($slots)), 'schedule' => json_encode($slots), 'now' => Database::now(), 'id' => (int) $channel['id'], 'uid' => $uid]);
        self::notice($back, 'Posting schedule saved.');
    }

    public static function closeIntro(string $id): never
    {
        $uid = (int) Auth::member()['id'];
        Database::connection()->prepare('UPDATE channels SET queue_intro_closed_at = :now WHERE id = :id AND user_id = :uid')
            ->execute(['now' => Database::now(), 'id' => (int) $id, 'uid' => $uid]);
        if (wants_json()) {
            json_response(['status' => 'success']);
        }
        redirect('/app/channels/' . (int) $id);
    }

    /** Later weeks of a channel queue, as HTML, for endless scrolling. */
    public static function queueFragment(string $id): never
    {
        $user = Auth::require();
        $onboarding = Auth::onboarding((int) $user['id']);
        $channel = ctype_digit($id) ? Channels::find((int) $user['id'], (int) $id) : null;
        $tz = Workspace::timezone($user);
        $from = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_GET['from'] ?? ''), $tz);
        $limit = (new DateTimeImmutable('today', $tz))->modify('+' . self::QUEUE_DAYS . ' day');
        if ($channel === null || $from === false || $from < new DateTimeImmutable('today', $tz) || $from >= $limit) {
            not_found();
        }
        $queue = Workspace::queue($user, $onboarding, 28, (int) $channel['id'], $from);
        $next = $queue['next'];
        $days = (int) (new DateTimeImmutable('today', $tz))->diff($next)->format('%a');
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        partial('workspace/queue-list', [
            'user' => $user,
            'queue' => $queue,
            'filter' => '',
            'fragment' => true,
            'endless' => $next < $limit ? [
                'src' => '/app/channels/' . (int) $channel['id'] . '/queue?from=' . $next->format('Y-m-d'),
                'href' => '/app/channels/' . (int) $channel['id'] . '?days=' . min(self::QUEUE_DAYS, $days + 28),
            ] : null,
        ]);
        exit;
    }

    /** Community (Coming soon inbox) and Insights (sent-post counts only) for one channel. */
    public static function subPage(string $id, string $section): void
    {
        if (!ctype_digit($id)) {
            not_found();
        }
        $view = $section === 'community' ? 'workspace/channel-community' : 'workspace/channel-insights';
        Workspace::render('channels', $view, $section === 'community' ? 'Community' : 'Insights', static function (array $user) use ($id, $section): array {
            $channel = Channels::find((int) $user['id'], (int) $id);
            if ($channel === null) {
                not_found();
            }
            $data = ['channel' => $channel];
            if ($section === 'community') {
                $data['tab'] = Workspace::pick('tab', ['comments', 'mentions']);
                $data['view'] = Workspace::pick('view', ['post', 'list']);
            } else {
                $data['insights'] = Workspace::insights($user, Workspace::pick('range', ['30d', '7d', 'mtd', 'last-month', 'custom']), (int) $channel['id']);
            }
            return $data;
        });
    }

    public static function disconnect(string $id): never
    {
        $uid = (int) Auth::member()['id'];
        $channel = Channels::find($uid, (int) $id);
        if ($channel === null) {
            not_found();
        }
        Database::connection()->prepare('DELETE FROM channels WHERE id = :id AND user_id = :uid')->execute(['id' => (int) $channel['id'], 'uid' => $uid]);
        if (!self::hasProvider($uid, $channel['provider'])) {
            Channels::revoke(Security::unseal($channel['refresh_token']) ?? Security::unseal($channel['access_token']), $channel['provider']);
            self::notice('/app/channels', sprintf('%s disconnected and atwebtech’s access to it was revoked.', $channel['name']));
        }
        self::notice('/app/channels', sprintf('%s removed from your workspace.', $channel['name']));
    }

    /** Channel Settings: posting schedule (working) and general / queue actions (Coming soon). */
    public static function settings(string $id): void
    {
        if (!ctype_digit($id)) {
            not_found();
        }
        Workspace::render('channels', 'workspace/channel-settings', 'Channel Settings', static function (array $user) use ($id): array {
            $channel = Channels::find((int) $user['id'], (int) $id);
            if ($channel === null) {
                not_found();
            }
            return [
                'channel' => $channel,
                'tab' => Workspace::pick('tab', ['schedule', 'general']),
            ];
        });
    }

    /** Stay on /app/channels/{id}… unless a same-channel return path was posted. */
    private static function channelPath(array $channel, string $fallbackQuery = ''): string
    {
        $base = '/app/channels/' . (int) $channel['id'];
        $path = safe_return_path($_POST['return'] ?? null, $base . $fallbackQuery);
        return str_starts_with($path, $base) ? $path : $base . $fallbackQuery;
    }

    private static function hasProvider(int $uid, string $provider): bool
    {
        foreach (Channels::forUser($uid) as $channel) {
            if ($channel['provider'] === $provider) {
                return true;
            }
        }
        return false;
    }

    /** Connect the Pinterest sandbox account for the token saved on this server. Trial access has no app secret yet. */
    private static function connectPinterestSandbox(): never
    {
        $token = (string) config('oauth.pinterest.sandbox_token');
        try {
            $accounts = Channels::pinterestAccount($token, true);
        } catch (Throwable $e) {
            error_log(sprintf('[%s] pinterest sandbox connect failed: %s', gmdate('c'), $e->getMessage()));
            self::notice('/app/channels', 'We couldn’t read the Pinterest sandbox account. Generate a new sandbox token and try again.');
        }
        if (!$accounts) {
            self::notice('/app/channels', 'Pinterest didn’t return an account for this sandbox token. Generate a new one and try again.');
        }
        $_SESSION['_oauth_pending'] = [
            'provider' => 'pinterest',
            'sandbox' => true,
            'tokens' => Security::seal(json_encode([
                'access_token' => $token,
                'refresh_token' => null,
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
                'scope' => 'user_accounts:read,boards:read,pins:read',
            ], JSON_THROW_ON_ERROR)),
            'accounts' => $accounts,
            'at' => time(),
        ];
        redirect('/app/channels/confirm');
    }

    private static function connectRefusal(string $message, string $provider = 'linkedin'): string
    {
        $text = strtolower($message);
        if ($provider === 'x') {
            if (str_contains($text, 'redirect')) {
                return 'In the X app, the callback URL must be exactly https://atwebtech.us/app/channels/callback/x';
            }
            return 'X refused the sign-in. Turn on OAuth 2.0 for a Web App with Read and write, then try again.';
        }
        if ($provider === 'instagram') {
            if (str_contains($text, 'redirect')) {
                return 'In the Instagram app, the redirect URI must be exactly https://atwebtech.us/app/channels/callback/instagram';
            }
            return 'Instagram refused the sign-in. Use a Professional account that is an admin of the app, then try again.';
        }
        if ($provider === 'facebook') {
            if (str_contains($text, 'redirect')) {
                return 'In the Facebook app, the redirect URI must be exactly https://atwebtech.us/app/channels/callback/facebook';
            }
            return 'Facebook refused the sign-in. Use an account that is an admin of the app and that manages a Page, then try again.';
        }
        if (str_contains($text, 'client authentication') || str_contains($text, 'invalid_client')) {
            return 'LinkedIn did not accept the app secret on this server. Save the full secret, including the characters at the end, then try again.';
        }
        if (str_contains($text, 'redirect') || str_contains($text, 'code verifier')) {
            return 'LinkedIn did not accept the return address. In the LinkedIn app, the redirect URL must be exactly https://atwebtech.us/app/channels/callback/linkedin';
        }
        return 'LinkedIn refused the sign-in. Please try again.';
    }

    private static function notice(string $location, string $message): never
    {
        flash('notice', $message);
        redirect($location);
    }
}
