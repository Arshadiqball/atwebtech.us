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
            'threads' => 'Threads',
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
            if ($provider === 'threads' && !str_contains($tokens['scope'], 'threads_basic')) {
                Channels::revoke($tokens['access_token'], $provider);
                self::notice('/app/channels', 'Threads profile access wasn’t granted. Allow the profile permission to connect.');
            }
            if ($provider === 'instagram' && !str_contains($tokens['scope'], 'instagram_business_basic')) {
                Channels::revoke($tokens['access_token'], $provider);
                self::notice('/app/channels', 'Instagram profile access wasn’t granted. Allow the profile permission to connect.');
            }
            if ($provider === 'facebook' && !str_contains($tokens['scope'], 'pages_show_list')) {
                Channels::revoke($tokens['access_token'], $provider);
                self::notice('/app/channels', 'Facebook Page access wasn’t granted. Tick the Page permissions to connect.');
            }
            if ($provider === 'pinterest' && !str_contains($tokens['scope'], 'user_accounts:read')) {
                Channels::revoke($tokens['access_token'], $provider);
                self::notice('/app/channels', 'Pinterest account access wasn’t granted. Allow the account permission, then try again.');
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
                'threads' => 'We couldn’t read this Threads profile. Use the Threads account that is a tester on the app, then try again.',
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

    /** AI Generate: write a run of posts with OpenAI and put them on the chosen days and time. */
    public static function generate(string $id): never
    {
        $user = Auth::member();
        $uid = (int) $user['id'];
        if (!ctype_digit($id)) {
            not_found();
        }
        $channel = Channels::find($uid, (int) $id);
        if ($channel === null) {
            not_found();
        }
        $back = '/app/channels/' . (int) $channel['id'];
        $provider = (string) $channel['provider'];
        $tz = Workspace::timezone($user);
        $now = new DateTimeImmutable('now', $tz);

        $in = [
            'mode' => (string) ($_POST['mode'] ?? ''),
            'topic' => trim((string) ($_POST['topic'] ?? '')),
            'details' => trim((string) ($_POST['details'] ?? '')),
            'region' => (string) ($_POST['region'] ?? ''),
            'tone' => (string) ($_POST['tone'] ?? ''),
            'length' => (string) ($_POST['length'] ?? ''),
            'language' => (string) ($_POST['language'] ?? ''),
            'hashtags' => ($_POST['hashtags'] ?? '') === '1',
            'emojis' => ($_POST['emojis'] ?? '') === '1',
            'cta' => ($_POST['cta'] ?? '') === '1',
            'question' => ($_POST['question'] ?? '') === '1',
            'media' => ($_POST['media'] ?? '') === '1',
            'rules' => trim((string) ($_POST['rules'] ?? '')),
            'count' => (int) ($_POST['count'] ?? 0),
            'start' => (string) ($_POST['start'] ?? ''),
            'days' => array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['days'] ?? [])), static fn (int $d): bool => $d >= 1 && $d <= 7))),
            'time' => (string) ($_POST['time'] ?? ''),
            'skip' => ($_POST['skip'] ?? '') === '1',
            'save' => ($_POST['save'] ?? '') === 'draft' ? 'draft' : 'planned',
        ];
        $fail = static function (string $message, int $step, int $status = 422) use ($back, $in): never {
            if (wants_json()) {
                json_response(['status' => 'error', 'message' => $message, 'step' => $step], $status);
            }
            flash('form', ['form' => 'generate', 'status' => 'error', 'message' => $message, 'errors' => [], 'old' => $in + ['step' => $step]]);
            redirect($back . '?setup=generate');
        };

        if (!Assistant::enabled()) {
            $fail('The AI Assistant isn’t switched on for this workspace yet.', 1, 503);
        }
        if (!isset(Assistant::MODES[$in['mode']])) {
            $fail('Choose how the posts should be generated.', 1);
        }
        if (mb_strlen($in['topic']) < 2 || mb_strlen($in['topic']) > 120) {
            $fail('Give the topic a name between 2 and 120 characters.', 2);
        }
        if (mb_strlen($in['details']) > 1000) {
            $fail('Keep the details under 1,000 characters.', 2);
        }
        if ($in['mode'] === 'prompt' && mb_strlen($in['details']) < 10) {
            $fail('Write the prompt the posts should follow.', 2);
        }
        if ($in['mode'] === 'trending' && !isset(Assistant::TREND_REGIONS[$in['region']])) {
            $fail('Choose the region to take trends from.', 2);
        }
        if (!isset(Assistant::TONES[$in['tone']]) || !isset(Assistant::LENGTHS[$in['length']]) || !in_array($in['language'], Assistant::LANGUAGES, true)) {
            $fail('Choose a tone, length and language.', 3);
        }
        if (mb_strlen($in['rules']) > 500) {
            $fail('Keep your rules under 500 characters.', 3);
        }
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $in['start'], $tz);
        if ($in['count'] < 1 || $in['count'] > Assistant::RUN_MAX) {
            $fail(sprintf('Ask for between 1 and %d posts.', Assistant::RUN_MAX), 4);
        }
        if ($start === false || $start < $now->setTime(0, 0) || $start > $now->modify('+1 year')) {
            $fail('Choose a start date between today and a year from now.', 4);
        }
        if (!$in['days']) {
            $fail('Pick at least one day of the week.', 4);
        }
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $in['time'], $hm)) {
            $fail('Choose a posting time like 09:30 or 18:00.', 4);
        }

        $taken = [];
        if ($in['skip']) {
            $stmt = Database::connection()->prepare('SELECT planned_at FROM posts WHERE user_id = :uid AND channel_id = :cid AND status IN (\'planned\', \'publishing\') AND planned_at >= :from');
            $stmt->execute(['uid' => $uid, 'cid' => (int) $channel['id'], 'from' => $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $planned) {
                $taken[Workspace::local((string) $planned, $tz)->format('Y-m-d')] = true;
            }
        }
        $slots = [];
        $soon = $now->modify('+10 minutes');
        for ($d = 0; $d < 400 && count($slots) < $in['count']; $d++) {
            $at = $start->modify('+' . $d . ' days')->setTime((int) $hm[1], (int) $hm[2]);
            if ($at <= $soon || isset($taken[$at->format('Y-m-d')]) || !in_array((int) $at->format('N'), $in['days'], true)) {
                continue;
            }
            $slots[] = $at;
        }
        if (!$slots) {
            $fail('None of those days are open. Pick other days, a later time, or turn off “Skip days that already have a post”.', 4);
        }
        if (self::countPosts($uid) + count($slots) > 1000) {
            $fail('You have reached the limit of 1,000 posts. Delete a few to add more.', 4);
        }
        $problem = Assistant::allowanceProblem($uid, (int) ceil(count($slots) / 10));
        if ($problem !== null) {
            $fail($problem, 4, 429);
        }

        // Without JavaScript the whole run happens in this request, so keep it to one chunk.
        if (!wants_json()) {
            $slots = array_slice($slots, 0, Assistant::CHUNK[$in['length']]);
        }
        try {
            $trends = $in['mode'] === 'trending' ? Assistant::trends($in['region']) : [];
        } catch (Throwable $e) {
            error_log(sprintf('[%s] google trends %s failed: %s', gmdate('c'), $in['region'], $e->getMessage()));
            $fail('Google Trends didn’t answer just now. Try again, or pick Random or Prompt.', 1, 502);
        }
        RateLimiter::hit('ai-minute', 'user:' . $uid);
        for ($i = (int) ceil(count($slots) / Assistant::BATCH); $i > 0; $i--) {
            RateLimiter::hit('ai-day', 'user:' . $uid);
        }

        $pool = $in['media'] && in_array($provider, ['instagram', 'youtube', 'tiktok'], true) ? self::templatePool($provider) : [];
        $chosen = [];
        while ($pool && count($chosen) < count($slots)) {
            $bag = $pool;
            shuffle($bag);
            array_push($chosen, ...array_slice($bag, 0, count($slots) - count($chosen)));
        }
        $run = [
            'id' => bin2hex(random_bytes(12)),
            'channel' => (int) $channel['id'],
            'brief' => [
                'mode' => $in['mode'], 'topic' => $in['topic'], 'details' => $in['details'], 'provider' => $provider,
                'tone' => $in['tone'], 'length' => $in['length'], 'language' => $in['language'],
                'hashtags' => $in['hashtags'], 'emojis' => $in['emojis'], 'cta' => $in['cta'], 'question' => $in['question'],
                'rules' => $in['rules'], 'trends' => $trends,
            ],
            'tz' => $tz->getName(),
            'slots' => array_map(static fn (DateTimeImmutable $s): string => $s->format(DATE_ATOM), $slots),
            'media' => array_map(static fn (array $c): array => ['image' => (string) $c['image'], 'video' => (string) $c['video']], $chosen),
            'pool' => (bool) $pool,
            'asked' => $in['count'],
            'save' => $in['save'],
            'done' => 0,
            'avoid' => [],
            'busy' => 0,
            'expires' => time() + 3600,
        ];

        if (wants_json()) {
            $_SESSION['_gen'] = $run;
            json_response(['status' => 'run', 'run' => $run['id'], 'total' => count($slots), 'url' => $back . '/generate/step']);
        }
        try {
            self::writeChunk($uid, $channel, $run);
        } catch (Throwable $e) {
            error_log(sprintf('[%s] ai run failed for user %d: %s', gmdate('c'), $uid, $e->getMessage()));
            $fail(self::chunkError($e), 4, 502);
        }
        $done = self::runSummary($channel, $run, null);
        self::notice($done['redirect'], $done['message']);
    }

    /** One step of an AI Generate run: write and save the next small chunk, so no request runs long. */
    public static function generateStep(string $id): never
    {
        $user = Auth::member();
        $uid = (int) $user['id'];
        $run = $_SESSION['_gen'] ?? null;
        if (!is_array($run) || !ctype_digit($id) || (int) $id !== $run['channel'] || !hash_equals($run['id'], (string) ($_POST['run'] ?? '')) || $run['expires'] < time()) {
            json_response(['status' => 'error', 'message' => 'This generation is no longer running. Start it again.', 'step' => 4], 410);
        }
        $channel = Channels::find($uid, $run['channel']);
        if ($channel === null) {
            unset($_SESSION['_gen']);
            not_found();
        }
        if ($run['busy'] > time()) {
            json_response(['status' => 'error', 'message' => 'Still writing the previous posts. One moment.', 'step' => 4], 409);
        }
        $_SESSION['_gen']['busy'] = time() + 60;
        session_write_close();
        set_time_limit(60);

        $error = null;
        try {
            self::writeChunk($uid, $channel, $run);
        } catch (Throwable $e) {
            error_log(sprintf('[%s] ai run step failed for user %d: %s', gmdate('c'), $uid, $e->getMessage()));
            $error = self::chunkError($e);
        }
        Security::startSession();
        if ($error === null && $run['done'] < count($run['slots'])) {
            $run['busy'] = 0;
            $_SESSION['_gen'] = $run;
            json_response(['status' => 'progress', 'done' => $run['done'], 'total' => count($run['slots'])]);
        }
        unset($_SESSION['_gen']);
        if ($run['done'] === 0) {
            json_response(['status' => 'error', 'message' => $error, 'step' => 4], 502);
        }
        json_response(['status' => 'success'] + self::runSummary($channel, $run, $error));
    }

    private static function chunkError(Throwable $e): string
    {
        return $e->getMessage() === 'invalid_api_key'
            ? 'OpenAI rejected the API key on this server. Replace it, then try again.'
            : 'The AI couldn’t write those posts just now. Please try again.';
    }

    /** Write the run's next chunk with one short OpenAI call, save it, and advance the run. */
    private static function writeChunk(int $uid, array $channel, array &$run): void
    {
        $tz = new DateTimeZone($run['tz']);
        $slots = array_map(
            static fn (string $s): DateTimeImmutable => (new DateTimeImmutable($s))->setTimezone($tz),
            array_slice($run['slots'], $run['done'], Assistant::CHUNK[$run['brief']['length']])
        );
        $written = Assistant::posts($run['brief'] + [
            'dates' => array_map(static fn (DateTimeImmutable $s): string => $s->format('D j M Y'), $slots),
            'avoid' => $run['avoid'],
        ]);
        $slots = array_slice($slots, 0, count($written));
        self::saveGenerated($uid, $channel, $slots, $written, array_slice($run['media'], $run['done'], count($slots)), $run['save']);
        $run['done'] += count($written);
        foreach ($written as $p) {
            $run['avoid'][] = mb_substr((string) preg_replace('/\s+/', ' ', $p['body']), 0, 90);
        }
    }

    /**
     * @param list<DateTimeImmutable> $slots
     * @param list<array{title:string, body:string}> $written
     * @param list<array{image:string, video:string}> $chosen
     */
    private static function saveGenerated(int $uid, array $channel, array $slots, array $written, array $chosen, string $status): void
    {
        $provider = (string) $channel['provider'];
        $youtube = $provider === 'youtube';
        $yt = data('youtube');
        $titleMax = (int) $yt['title_max'];
        $meta = $youtube ? json_encode([
            'category' => $yt['default_category'],
            'visibility' => 'public',
            'license' => 'youtube',
            'notify' => true,
            'embeddable' => true,
            'kids' => false,
            'synthetic' => false,
        ]) : null;
        $imageRoot = BASE_PATH . '/public/assets/img/templates';
        $pdo = Database::connection();
        $insert = $pdo->prepare('INSERT INTO posts (user_id, channel, channel_id, body, title, meta, tags, media_id, first_comment, status, planned_at, idea_id, created_at, updated_at) VALUES (:uid, :channel, :cid, :body, :title, :meta, :tags, :media, :first, :status, :planned, :idea, :created, :now)');
        $copied = [];
        $pdo->beginTransaction();
        try {
            foreach ($slots as $i => $slot) {
                $item = $chosen[$i] ?? ['image' => '', 'video' => ''];
                $at = $slot->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                $mediaId = null;
                $videoNet = in_array($provider, ['youtube', 'tiktok'], true);
                if ($videoNet && preg_match('/^[a-z0-9-]+\.mp4$/', (string) ($item['video'] ?? ''))) {
                    $made = Media::copyReady($uid, BASE_PATH . '/public/assets/video/templates/' . $item['video'], $item['video']);
                    $mediaId = $made['id'];
                    $copied[] = $made['file'];
                } elseif (!$videoNet && $item['image'] !== '' && preg_match('/^[a-z0-9-]+\.webp$/', $item['image'])) {
                    $made = Media::copyReady($uid, $imageRoot . '/' . $item['image'], $item['image']);
                    $mediaId = $made['id'];
                    $copied[] = $made['file'];
                }
                $title = null;
                if ($youtube) {
                    $title = mb_substr(trim($written[$i]['title'] !== '' ? $written[$i]['title'] : strtok($written[$i]['body'], "\n")), 0, $titleMax);
                }
                $now = Database::now();
                $insert->execute([
                    'uid' => $uid,
                    'channel' => $provider,
                    'cid' => (int) $channel['id'],
                    'body' => $written[$i]['body'],
                    'title' => $title !== '' ? $title : null,
                    'meta' => $meta,
                    'tags' => null,
                    'media' => $mediaId,
                    'first' => null,
                    'status' => $status,
                    'planned' => $at,
                    'idea' => null,
                    'created' => $now,
                    'now' => $now,
                ]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($copied as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            throw $e;
        }
    }

    /**
     * Closing message for a finished (or stopped) run.
     *
     * @return array{message:string, redirect:string}
     */
    private static function runSummary(array $channel, array $run, ?string $error): array
    {
        $provider = (string) $channel['provider'];
        $tz = new DateTimeZone($run['tz']);
        $n = $run['done'];
        $first = (new DateTimeImmutable($run['slots'][0]))->setTimezone($tz);
        $last = (new DateTimeImmutable($run['slots'][$n - 1]))->setTimezone($tz);
        $range = $first->format('j M') . ($n > 1 ? ' to ' . $last->format('j M') : '');
        $short = match (true) {
            $error !== null => sprintf(' The AI stopped after %d of %d, so generate again for the rest.', $n, count($run['slots'])),
            $n < $run['asked'] => sprintf(' Only %d of the %d posts you asked for %s.', $n, $run['asked'], wants_json() ? 'fit the open days' : 'were written; turn on JavaScript for longer runs'),
            default => '',
        };
        if ($run['save'] === 'draft') {
            $message = sprintf('%d AI %s saved to Drafts, dated %s. Review and schedule them when ready.', $n, $n === 1 ? 'draft' : 'drafts', $range) . $short;
        } else {
            $publishes = $channel['can_publish'] && in_array($provider, ['linkedin', 'instagram', 'youtube'], true);
            $missingMedia = in_array($provider, ['instagram', 'youtube', 'tiktok'], true) && !$run['pool'];
            $message = sprintf('%d AI %s scheduled, %s at %s.', $n, $n === 1 ? 'post' : 'posts', $range, $first->format('g:i A'))
                . match (true) {
                    !$publishes => ' This channel doesn’t publish automatically yet, so they stay as plans you can edit.',
                    $missingMedia => ' Add a ' . ($provider === 'instagram' ? 'photo' : 'video') . ' to each before its time, or it can’t be published.',
                    default => '',
                } . $short;
        }
        return ['message' => $message, 'redirect' => '/app/channels/' . (int) $channel['id'] . ($run['save'] === 'draft' ? '?tab=drafts' : '')];
    }

    /** @return list<array{title:string, body:string, image:string, video:string, post_title:string}> */
    private static function templatePool(string $provider): array
    {
        $library = data('templates');
        $limit = (int) (data('workspace')['limits'][$provider] ?? data('workspace')['post_max']);
        $videoNet = $provider === 'youtube' || $provider === 'tiktok';
        $pool = [];
        $add = static function (string $title, string $body, string $imageKey, string $postTitle = '', string $video = '') use (&$pool, $library, $limit): void {
            $body = trim($body);
            if ($body === '' || mb_strlen($body) > $limit) {
                return;
            }
            $pool[] = [
                'title' => $title,
                'body' => $body,
                'image' => (string) ($library['images'][$imageKey] ?? ''),
                'video' => $video,
                'post_title' => $postTitle,
            ];
        };
        foreach ($library['networks'][$provider][1] ?? [] as $i => $post) {
            $clip = $videoNet ? (string) ($library['videos'][$provider][$i] ?? '') : '';
            $add($post[1], $post[2], $videoNet ? '' : $provider, $post[3] ?? '', $clip);
        }
        if ($provider === 'youtube' || $provider === 'tiktok') {
            return $pool;
        }
        foreach ($library['interests'] as $key => $group) {
            foreach ($group[1] as $post) {
                $add($post[0], $post[1], $key);
            }
        }
        foreach ($library['items'] as $key => $item) {
            $add($item[2], $item[3], $key);
        }
        return $pool;
    }

    private static function countPosts(int $uid): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM posts WHERE user_id = :uid');
        $stmt->execute(['uid' => $uid]);
        return (int) $stmt->fetchColumn();
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
        if ($provider === 'threads') {
            if (str_contains($text, 'redirect')) {
                return 'In the Threads app, the redirect URI must be exactly https://atwebtech.us/app/channels/callback/threads';
            }
            return 'Threads refused the sign-in. Add this Threads account as a tester on the app, accept the invite, then try again.';
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
        if ($provider === 'pinterest') {
            if (str_contains($text, 'redirect')) {
                return 'In the Pinterest app, the redirect URI must be exactly https://atwebtech.us/app/channels/callback/pinterest';
            }
            return 'Pinterest refused the sign-in. Set that redirect on the app, then try again.';
        }
        if ($provider !== 'linkedin') {
            return 'The sign-in was refused. Please try again.';
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
