<?php

declare(strict_types=1);

/**
 * API for the mobile app (mobile-app-flutter).
 *
 * Sign-in gives each device a bearer token (amt_…, only its hash is stored). A request carrying a valid
 * token is treated as that person everywhere: Auth::user() resolves it, no PHP session is started, the CSRF
 * check is skipped (a browser can't forge the header), and redirect() answers with JSON instead. That lets
 * the app post to the same handlers as the website (/app/posts, /app/community, …), so behavior and
 * permissions are identical. This class adds what the website renders as HTML: sign-in and the read
 * endpoints under /api/mobile. The workspace comes from the X-Workspace header.
 */
final class Mobile
{
    private const LOGIN_MAX_FAILURES = 10;
    private const LOGIN_WINDOW_MINUTES = 15;
    private const LIST_LIMIT = 80;

    // ---- Sign-in ------------------------------------------------------------

    /** POST /api/mobile/login {email, password, device, platform} */
    public static function login(): never
    {
        $in = self::input();
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        $password = (string) ($in['password'] ?? '');
        if ($email === '' || $password === '') {
            self::fail(422, 'Enter your email and password.');
        }
        $ipKey = Security::clientFingerprint();
        $emailKey = Security::keyedHash('login:' . $email);
        if (RateLimiter::tooManyAttempts('login', $ipKey, self::LOGIN_MAX_FAILURES, self::LOGIN_WINDOW_MINUTES)
            || RateLimiter::tooManyAttempts('login-email', $emailKey, self::LOGIN_MAX_FAILURES, self::LOGIN_WINDOW_MINUTES)) {
            self::fail(429, 'Too many failed attempts. Please wait 15 minutes and try again.');
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, password_hash, totp_confirmed_at, disabled_at FROM users WHERE email = :email AND parent_id IS NULL');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        // Unknown emails still pay for one hash so timing doesn't reveal which accounts exist.
        $hash = $user['password_hash'] ?? Auth::hash(bin2hex(random_bytes(8)));
        if (!password_verify($password, (string) $hash) || $user === false) {
            RateLimiter::hit('login', $ipKey);
            RateLimiter::hit('login-email', $emailKey);
            self::fail(401, 'That email and password combination is incorrect.');
        }
        if ($user['disabled_at'] !== null) {
            self::fail(403, 'This account has been disabled. Contact ' . config('contact.email') . ' if you think this is a mistake.');
        }
        if ($user['totp_confirmed_at'] !== null) {
            json_response(['status' => 'two_factor', 'challenge' => self::code((int) $user['id'], '2fa', 600, json_encode(['device' => self::deviceName($in), 'platform' => self::platform($in)]))]);
        }
        self::issue((int) $user['id'], $in);
    }

    /** POST /api/mobile/two-factor {challenge, code} */
    public static function twoFactor(): never
    {
        $in = self::input();
        $challenge = (string) ($in['challenge'] ?? '');
        $row = self::findCode($challenge, '2fa');
        if ($row === null) {
            self::fail(401, 'That sign-in expired. Enter your email and password again.');
        }
        $key = 'challenge:' . (int) $row['id'];
        if (RateLimiter::tooManyAttempts('m2fa', $key, 6, 10)) {
            self::fail(429, 'Too many wrong codes. Sign in again in a few minutes.');
        }
        if (!TwoFactor::acceptLogin((int) $row['user_id'], (string) ($in['code'] ?? ''))) {
            RateLimiter::hit('m2fa', $key);
            self::fail(401, 'That code isn’t right. Enter the current code from your authenticator app, or a recovery code.');
        }
        Database::connection()->prepare('DELETE FROM mobile_codes WHERE id = :id')->execute(['id' => (int) $row['id']]);
        self::issue((int) $row['user_id'], (array) json_decode((string) $row['data'], true));
    }

    /** POST /api/mobile/signup {email, password, device, platform} */
    public static function signup(): never
    {
        $in = self::input();
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        $password = (string) ($in['password'] ?? '');
        if ($email === '' || mb_strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            self::fail(422, 'Enter a valid email address, like name@example.com.');
        }
        if (($problem = Auth::passwordProblem($password)) !== null) {
            self::fail(422, $problem);
        }
        $fingerprint = Security::clientFingerprint();
        if (RateLimiter::tooManyAttempts('signup', $fingerprint)) {
            self::fail(429, 'Too many attempts from this connection. Please wait a few minutes and try again.');
        }
        RateLimiter::hit('signup', $fingerprint);
        $pdo = Database::connection();
        $now = Database::now();
        try {
            $pdo->prepare('INSERT INTO users (email, password_hash, display_name, theme, created_at, updated_at, last_login_at, utm_source) VALUES (:email, :hash, :name, :theme, :c, :u, :l, :src)')
                ->execute(['email' => $email, 'hash' => Auth::hash($password), 'name' => mb_substr(strstr($email, '@', true) ?: $email, 0, 100), 'theme' => 'light', 'c' => $now, 'u' => $now, 'l' => $now, 'src' => 'mobile-app']);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                self::fail(409, 'This email already has an account. Log in instead.');
            }
            error_log(sprintf('[%s] mobile signup failed: %s', gmdate('c'), $e->getMessage()));
            self::fail(500, 'We couldn’t create your account right now. Please try again in a few minutes.');
        }
        $uid = (int) $pdo->lastInsertId();
        // The app has no survey screens, so the web onboarding is marked done.
        Auth::onboarding($uid);
        $pdo->prepare('UPDATE user_onboarding SET completed_at = :now, updated_at = :now2 WHERE user_id = :id')->execute(['now' => $now, 'now2' => $now, 'id' => $uid]);
        Mailer::notify('New account (mobile app)', ['Email' => $email]);
        self::issue($uid, $in);
    }

    /** POST /api/mobile/forgot {email} */
    public static function forgot(): never
    {
        [$ok, $message] = Passwords::start(strtolower(trim((string) (self::input()['email'] ?? ''))));
        json_response(['status' => $ok ? 'ok' : 'error', 'message' => $message], $ok ? 200 : 422);
    }

    /** POST /api/mobile/logout: forget this device. */
    public static function logout(): never
    {
        self::person();
        Database::connection()->prepare('DELETE FROM mobile_tokens WHERE id = :id')->execute(['id' => (int) Auth::tokenId()]);
        json_response(['status' => 'ok']);
    }

    /** POST /api/mobile/device {push_token, platform}: where to send this device's push notifications. */
    public static function device(): never
    {
        self::person();
        $in = self::input();
        $push = trim((string) ($in['push_token'] ?? ''));
        $pdo = Database::connection();
        if ($push !== '') {
            // A phone hands the same address to whoever signs in on it: keep it on this sign-in only.
            $pdo->prepare('UPDATE mobile_tokens SET push_token = NULL WHERE push_token = :p AND id <> :id')->execute(['p' => $push, 'id' => (int) Auth::tokenId()]);
        }
        $pdo->prepare('UPDATE mobile_tokens SET push_token = :p, platform = :pl WHERE id = :id')
            ->execute(['p' => $push !== '' && strlen($push) <= 400 ? $push : null, 'pl' => self::platform($in), 'id' => (int) Auth::tokenId()]);
        json_response(['status' => 'ok', 'push_ready' => Push::enabled()]);
    }

    /**
     * POST /api/mobile/handoff {next}: a single-use link that opens a website page already signed in,
     * for the screens the app shows from the site (connecting channels, billing, team, …).
     */
    public static function handoff(): never
    {
        $user = self::person();
        $next = Auth::safeNext((string) (self::input()['next'] ?? '')) ?? '/app';
        $ws = (int) (Auth::user()['id'] ?? $user['id']);
        $platform = Database::connection()->prepare('SELECT platform FROM mobile_tokens WHERE id = :id');
        $platform->execute(['id' => (int) Auth::tokenId()]);
        $code = self::code((int) $user['id'], 'handoff', 90, json_encode(['next' => $next, 'ws' => $ws, 'app' => (string) $platform->fetchColumn()]));
        json_response(['status' => 'ok', 'url' => absolute_url('/m/open/' . $code)]);
    }

    /** GET /m/open/{code}: the browser side of a handoff. */
    public static function open(string $code): never
    {
        $row = self::findCode($code, 'handoff');
        if ($row === null) {
            redirect('/login');
        }
        Database::connection()->prepare('DELETE FROM mobile_codes WHERE id = :id')->execute(['id' => (int) $row['id']]);
        $data = (array) json_decode((string) $row['data'], true);
        Auth::login((int) $row['user_id']);
        $ws = (int) ($data['ws'] ?? 0);
        if ($ws > 0 && $ws !== (int) $row['user_id']) {
            $_SESSION['_ws'] = $ws; // Auth checks membership on the next request.
        } else {
            unset($_SESSION['_ws']);
        }
        // Pages opened from the iPhone or iPad app show no plans, prices or upgrade links (App Store rules).
        if (($data['app'] ?? '') === 'ios') {
            $_SESSION['_in_app'] = 'ios';
        } else {
            unset($_SESSION['_in_app']);
        }
        header('Referrer-Policy: no-referrer');
        redirect(Auth::safeNext((string) ($data['next'] ?? '')) ?? '/app');
    }

    // ---- Reads ---------------------------------------------------------------

    /** GET /api/mobile/bootstrap: everything the shell and Home need. */
    public static function bootstrap(): never
    {
        $person = self::person();
        $user = (array) Auth::user();
        $uid = (int) $user['id'];
        $tz = Workspace::timezone($user);
        $channels = Channels::forUser($uid);
        $counts = Workspace::counts($uid);
        $allowance = Channels::allowance($uid);
        $waiting = Community::unanswered($uid);
        $reconnect = count(array_filter($channels, static fn (array $c): bool => stripos((string) ($c['stats_error'] ?? '') . ' ' . (string) ($c['inbox_error'] ?? ''), 'reconnect') !== false));
        $todo = array_values(array_filter([
            $counts['failed'] > 0 ? ['style' => 'bad', 'title' => $counts['failed'] . ' ' . ($counts['failed'] === 1 ? 'post' : 'posts') . ' failed to publish', 'detail' => 'See why and retry', 'go' => 'failed'] : null,
            $reconnect > 0 ? ['style' => 'bad', 'title' => $reconnect . ' ' . ($reconnect === 1 ? 'channel needs' : 'channels need') . ' reconnecting', 'detail' => 'Posts and stats wait until then', 'go' => 'channels'] : null,
            $counts['review'] > 0 ? ['style' => 'warn', 'title' => $counts['review'] . ' ' . ($counts['review'] === 1 ? 'post is' : 'posts are') . ' waiting for approval', 'detail' => 'Approve or ask for changes', 'go' => 'approvals'] : null,
            $waiting > 0 ? ['style' => 'info', 'title' => $waiting . ' unanswered ' . ($waiting === 1 ? 'comment' : 'comments'), 'detail' => 'Reply from the inbox', 'go' => 'inbox'] : null,
            $channels && $counts['queue'] === 0 ? ['style' => 'warn', 'title' => 'Nothing is scheduled', 'detail' => 'Write a post or let an automation fill the queue', 'go' => 'compose'] : null,
            $counts['drafts'] > 0 ? ['style' => 'ok', 'title' => $counts['drafts'] . ' ' . ($counts['drafts'] === 1 ? 'draft' : 'drafts') . ' to finish', 'detail' => 'Pick up where you left off', 'go' => 'drafts'] : null,
        ]));
        $networks = [];
        foreach (integrations() as $n) {
            $networks[$n['slug']] = ['name' => $n['name'], 'color' => $n['color'], 'limit' => (int) (data('workspace')['limits'][$n['slug']] ?? data('workspace')['post_max']), 'text_only_ok' => in_array($n['slug'], QueueTools::TEXT_NETWORKS, true)];
        }
        $now = new DateTimeImmutable('now', $tz);
        json_response([
            'status' => 'ok',
            'me' => ['id' => (int) $person['id'], 'name' => $person['display_name'], 'email' => $person['email'], 'theme' => $person['theme'] ?? 'light'],
            'workspace' => [
                'id' => $uid,
                'name' => isset($user['team_role']) ? (Channels::parentOf($uid) !== null ? $user['display_name'] : $user['display_name'] . '’s workspace') : 'My workspace',
                'role' => Team::role(), 'plan' => $allowance['name'], 'plan_slug' => $allowance['slug'], 'paid' => $allowance['paid'], 'channel_limit' => $allowance['limit'],
                'timezone' => $tz->getName(), 'tz_offset_minutes' => intdiv($now->getOffset(), 60),
            ],
            'workspaces' => Team::workspacesFor($person),
            'channels' => array_map([self::class, 'channelOut'], $channels),
            'counts' => $counts + ['inbox' => $waiting, 'notifications' => Notify::unread((int) $person['id'])],
            'todo' => $todo,
            'networks' => $networks,
            'repeats' => Automations::REPEATS,
            'ai' => Assistant::enabled(),
            'push_ready' => Push::enabled(),
            // Public Firebase identifiers the app registers its phone with, or null while push is not set up.
            'push' => Push::clientConfig(),
        ]);
    }

    /** GET /api/mobile/posts?tab=queue|drafts|approvals|failed|sent&channel= */
    public static function posts(): never
    {
        self::person();
        $user = (array) Auth::user();
        $uid = (int) $user['id'];
        $tz = Workspace::timezone($user);
        $tab = Workspace::pick('tab', ['queue', 'drafts', 'approvals', 'failed', 'sent']);
        $cid = isset($_GET['channel']) && ctype_digit((string) $_GET['channel']) ? (int) $_GET['channel'] : null;
        $rows = match ($tab) {
            'drafts' => Workspace::drafts($uid, $cid),
            'approvals' => array_values(array_filter(Approvals::pending($uid), static fn (array $p): bool => $cid === null || (int) $p['channel_id'] === $cid)),
            'failed' => Workspace::failed($uid, $cid),
            'sent' => Workspace::sent($uid, $cid),
            default => Workspace::planned($uid, null, null, $cid),
        };
        $channels = array_column(Channels::forUser($uid), null, 'id');
        $rows = array_slice($rows, 0, self::LIST_LIMIT);
        $metrics = $tab === 'sent' ? Metrics::forPosts(array_column($rows, 'id')) : [];
        json_response(['status' => 'ok', 'tab' => $tab, 'posts' => array_map(static fn (array $p): array => self::postOut($p, $channels, $tz, $uid, $metrics[$p['id']] ?? null), $rows), 'counts' => Workspace::counts($uid)]);
    }

    /** GET /api/mobile/posts/{id}: one post, ready for the composer. */
    public static function post(string $id): never
    {
        self::person();
        $user = (array) Auth::user();
        $post = ctype_digit($id) ? Workspace::post((int) $user['id'], (int) $id) : null;
        if ($post === null) {
            self::fail(404, 'That post no longer exists.');
        }
        json_response(['status' => 'ok', 'post' => Workspace::composeData($post, Workspace::timezone($user), (int) $user['id']) + ['status' => $post['status']]]);
    }

    /** GET /api/mobile/inbox?state=open|replied|done|all&channel=&page= */
    public static function inbox(): never
    {
        self::person();
        $user = (array) Auth::user();
        $uid = (int) $user['id'];
        $tz = Workspace::timezone($user);
        $state = Workspace::pick('state', ['open', 'replied', 'done', 'all']);
        $cid = isset($_GET['channel']) && ctype_digit((string) $_GET['channel']) ? (int) $_GET['channel'] : null;
        $page = isset($_GET['page']) && ctype_digit((string) $_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $inbox = Community::inbox($uid, $cid, $state, null, $page);
        $channels = array_column($inbox['channels'], null, 'id');
        $comments = array_map(static function (array $c) use ($channels, $tz): array {
            $channel = $channels[(int) $c['channel_id']] ?? null;
            return [
                'id' => (int) $c['id'], 'network' => $c['provider'], 'channel' => $c['channel_name'], 'author' => $c['author_name'], 'avatar' => $c['author_avatar'],
                'text' => (string) $c['body'], 'on' => $c['item_title'], 'url' => $c['item_url'], 'state' => $c['state'], 'likes' => $c['likes'] !== null ? (int) $c['likes'] : null,
                'when' => self::ago((string) $c['published_at'], $tz), 'reply' => $c['reply_body'], 'auto_reply' => !empty($c['auto_reply']),
                'can_reply' => $channel !== null && Community::canReply($channel),
            ];
        }, $inbox['comments']);
        json_response([
            'status' => 'ok', 'comments' => $comments, 'counts' => $inbox['counts'], 'more' => $inbox['more'],
            'saved' => Community::savedReplies($uid), 'problems' => array_values(Community::problems($uid)), 'has_channels' => (bool) $inbox['channels'], 'ai' => Assistant::enabled(),
            'report_reasons' => Community::REPORT_REASONS,
            'blocked' => array_map(static fn (array $b): array => ['id' => (int) $b['id'], 'network' => $b['provider'], 'author' => $b['author_name']], Community::blocked($uid)),
        ]);
    }

    /** GET /api/mobile/notifications */
    public static function notifications(): never
    {
        $person = self::person();
        $tz = Workspace::timezone($person);
        $items = array_map(static fn (array $n): array => [
            'id' => (int) $n['id'], 'kind' => $n['kind'], 'title' => $n['title'], 'body' => $n['body'], 'url' => $n['url'], 'workspace_id' => (int) $n['workspace_id'],
            'workspace' => (int) $n['workspace_id'] === (int) $person['id'] || $n['ws_name'] === null ? null : ($n['ws_parent'] !== null ? $n['ws_name'] : $n['ws_name'] . '’s workspace'),
            'unread' => $n['read_at'] === null, 'when' => self::ago((string) $n['created_at'], $tz),
        ], Notify::page($person)['items']);
        json_response(['status' => 'ok', 'items' => $items]);
    }

    /** POST /api/mobile/notifications/{id}/read */
    public static function readNotification(string $id): never
    {
        $person = self::person();
        Database::connection()->prepare('UPDATE notifications SET read_at = COALESCE(read_at, :now) WHERE id = :id AND user_id = :uid')->execute(['now' => Database::now(), 'id' => (int) $id, 'uid' => (int) $person['id']]);
        json_response(['status' => 'ok']);
    }

    /** GET /api/mobile/insights?range=7d|30d|mtd|last-month&channel= */
    public static function insights(): never
    {
        self::person();
        $user = (array) Auth::user();
        $uid = (int) $user['id'];
        $range = Workspace::pick('range', ['30d', '7d', 'mtd', 'last-month']);
        $cid = isset($_GET['channel']) && ctype_digit((string) $_GET['channel']) ? (int) $_GET['channel'] : null;
        if ($cid !== null && Channels::find($uid, $cid) === null) {
            $cid = null;
        }
        $in = Workspace::insights($user, $range, $cid);
        $now = $in['metrics']['now'];
        $prev = $in['metrics']['prev'];
        $labels = ['followers' => 'Followers', 'impressions' => 'Views', 'reactions' => 'Reactions', 'comments' => 'Comments', 'shares' => 'Shares', 'eng_rate' => 'Engagement rate', 'reach' => 'Reach', 'saves' => 'Saves', 'video_views' => 'Video views'];
        $cards = [['label' => 'Posts published', 'value' => (string) $in['sent'], 'delta' => self::delta($in['sent'], $in['prev_sent'])]];
        foreach ($labels as $key => $label) {
            $v = $now[$key] ?? null;
            if ($v === null) {
                continue;
            }
            $cards[] = [
                'label' => $label,
                'value' => $key === 'eng_rate' ? rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%' : self::short((int) $v),
                'delta' => $key === 'eng_rate' ? (isset($prev[$key]) ? sprintf('%+.2f pts', (float) $v - (float) $prev[$key]) : '') : ($key === 'followers' ? (isset($prev[$key]) ? sprintf('%+d', (int) $v - (int) $prev[$key]) : '') : self::delta($v, $prev[$key] ?? null)),
            ];
        }
        $names = array_column(integrations(), 'name', 'slug');
        $score = static fn (array $m): int => (int) ($m['reactions'] ?? 0) + (int) ($m['comments'] ?? 0) + (int) ($m['shares'] ?? 0) + (int) ($m['saves'] ?? 0);
        $top = array_values(array_filter($in['posts'], static fn (array $p): bool => isset($in['post_metrics'][$p['id']])));
        usort($top, static fn (array $a, array $b): int => $score($in['post_metrics'][$b['id']]) <=> $score($in['post_metrics'][$a['id']]));
        $day = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
        json_response([
            'status' => 'ok', 'range' => $range,
            'period' => $in['from']->format('j M') . ' – ' . $in['to']->format('j M Y'),
            'cards' => $cards,
            'series' => array_map(static fn (array $d): array => ['day' => $d['day'], 'n' => (int) $d['n']], array_values($in['series'])),
            'best' => ['ready' => $in['best']['ready'], 'posts' => $in['best']['posts'], 'top' => array_map(static fn (array $t): array => [
                'label' => $day[$t['day']] . ' ' . (new DateTimeImmutable(sprintf('2000-01-01 %02d:00', $t['hour'])))->format('g A'), 'lift' => number_format($t['lift'], 1) . '×', 'posts' => (int) $t['n'],
            ], $in['best']['top'])],
            'hashtags' => array_map(static fn (array $t): array => ['tag' => $t['tag'], 'posts' => (int) $t['posts'], 'lift' => number_format($t['lift'], 1) . '×', 'up' => $t['lift'] >= 1], $in['hashtags']),
            'top_posts' => array_map(static function (array $p) use ($in, $names, $score): array {
                $m = $in['post_metrics'][$p['id']];
                return ['text' => mb_strimwidth(trim((string) $p['body']), 0, 140, '…'), 'network' => $names[$p['channel']] ?? $p['channel'], 'slug' => $p['channel'], 'engagement' => $score($m), 'views' => $m['impressions'] ?? $m['video_views'], 'url' => $p['external_url']];
            }, array_slice($top, 0, 5)),
            'issues' => array_map(static fn (array $i): array => ['channel' => $i['channel']['name'], 'message' => $i['message']], $in['metrics']['issues']),
            'paid' => Channels::allowance($uid)['paid'],
        ]);
    }

    /** GET /api/mobile/automations */
    public static function automations(): never
    {
        self::person();
        $user = (array) Auth::user();
        $page = Automations::page($user);
        $names = array_column($page['allChannels'], 'name', 'id');
        $list = static fn (array $ids): string => implode(', ', array_map(static fn ($id): string => $names[(int) $id] ?? 'removed channel', $ids)) ?: 'all channels';
        $mode = ['draft' => 'saved to Drafts', 'review' => 'sent for approval', 'schedule' => 'scheduled automatically'];
        $items = array_map(static function (array $a) use ($list, $mode): array {
            $c = $a['config'];
            return [
                'id' => (int) $a['id'], 'kind' => $a['kind'], 'name' => $a['name'], 'enabled' => (int) $a['enabled'] === 1, 'made' => (int) $a['made'], 'result' => $a['last_result'],
                'summary' => match ($a['kind']) {
                    'feed' => 'Posts to ' . $list((array) ($c['channel_ids'] ?? [])) . ', ' . ($mode[$c['mode'] ?? 'draft'] ?? ''),
                    'plan' => (int) ($c['per_week'] ?? 3) . ' posts a week for ' . $list((array) ($c['channel_ids'] ?? [])) . ', ' . ($mode[$c['mode'] ?? 'draft'] ?? ''),
                    default => 'On ' . $list((array) ($c['channel_ids'] ?? [])) . ': “' . mb_strimwidth((string) ($c['text'] ?? ''), 0, 70, '…') . '”',
                },
            ];
        }, $page['items']);
        json_response(['status' => 'ok', 'items' => $items, 'paid' => $page['paid'], 'can_edit' => Team::can('admin')]);
    }

    /** GET /api/mobile/library?show=all|image|video|unused */
    public static function library(): never
    {
        self::person();
        $user = (array) Auth::user();
        $page = Library::page((int) $user['id']);
        $tz = Workspace::timezone($user);
        json_response(['status' => 'ok', 'used_bytes' => $page['used'], 'quota_bytes' => 2 * 1024 * 1024 * 1024, 'items' => array_map(static fn (array $m): array => [
            'id' => (int) $m['id'], 'kind' => $m['kind'], 'name' => $m['original_name'], 'size' => (int) $m['size'], 'url' => '/app/media/' . (int) $m['id'],
            'used' => (int) $m['used'], 'kept' => (int) $m['in_library'] === 1, 'date' => Workspace::local((string) $m['created_at'], $tz)->format('j M Y'),
        ], $page['items'])]);
    }

    /** GET /api/mobile/account: the signed-in person's own settings and plan. */
    public static function account(): never
    {
        $person = self::person();
        $pid = (int) $person['id'];
        $row = Database::connection()->prepare('SELECT notify_off, totp_confirmed_at FROM users WHERE id = :id');
        $row->execute(['id' => $pid]);
        $row = $row->fetch() ?: [];
        $off = explode(',', (string) ($row['notify_off'] ?? ''));
        $allowance = Channels::allowance($pid);
        $sub = Billing::enabled() ? Billing::current($pid) : null;
        $cfg = data('pricing');
        json_response([
            'status' => 'ok', 'name' => $person['display_name'], 'email' => $person['email'], 'two_factor' => ($row['totp_confirmed_at'] ?? null) !== null,
            'notifications' => array_map(static fn (string $kind, string $label): array => ['kind' => $kind, 'label' => $label, 'email' => !in_array($kind, $off, true)], array_keys(Notify::KINDS), Notify::KINDS),
            'plan' => [
                'name' => $allowance['name'], 'slug' => $allowance['slug'], 'paid' => $allowance['paid'], 'source' => $allowance['source'], 'channel_limit' => $allowance['limit'],
                'channels_used' => Channels::familyCount($pid), 'billing_enabled' => Billing::enabled(),
                'status' => $sub['status'] ?? null, 'renews' => !empty($sub['current_period_end']) ? Workspace::local((string) $sub['current_period_end'], Workspace::timezone($person))->format('j M Y') : null,
                'canceling' => !empty($sub['cancel_at_period_end']),
                'options' => array_values(array_map(static fn (string $slug, array $p): array => [
                    'slug' => $slug, 'name' => $p['name'], 'rate' => (float) $p['rate'], 'badge' => $p['badge'], 'features' => array_column($p['included'], 0),
                ], array_keys($cfg['plans']), $cfg['plans'])),
                'yearly_months' => (int) $cfg['yearly_months'],
            ],
        ]);
    }

    // ---- Used by helpers.php ---------------------------------------------------

    /** What redirect() sends when the request came from the app: the flashed result as JSON. */
    public static function answerRedirect(string $location): never
    {
        if (!Auth::check()) {
            json_response(['status' => 'error', 'code' => 'unauthorized', 'message' => 'Log in again to continue.'], 401);
        }
        $flash = (array) ($_SESSION['_flash'] ?? []);
        $form = $flash['form'] ?? null;
        if (is_array($form) && ($form['status'] ?? '') === 'error') {
            json_response(['status' => 'error', 'message' => (string) ($form['message'] ?? 'Please check and try again.'), 'errors' => (array) ($form['errors'] ?? [])], 422);
        }
        $extra = [];
        foreach (['_cm_draft' => 'draft', '_team_link' => 'invite', '_api_fresh' => 'fresh'] as $key => $name) {
            if (isset($_SESSION[$key])) {
                $extra[$name] = $_SESSION[$key];
            }
        }
        json_response(['status' => 'ok', 'message' => $flash['notice'] ?? (is_array($form) ? ($form['message'] ?? null) : null), 'location' => $location] + $extra);
    }

    // ---- Internals --------------------------------------------------------------

    /** The signed-in person for an /api/mobile request, or a 401. */
    private static function person(): array
    {
        $person = Auth::viaToken() ? Auth::person() : null;
        if ($person === null) {
            json_response(['status' => 'error', 'code' => 'unauthorized', 'message' => 'Log in again to continue.'], 401);
        }
        return $person;
    }

    private static function issue(int $uid, array $in): never
    {
        $token = 'amt_' . bin2hex(random_bytes(24));
        $pdo = Database::connection();
        $pdo->prepare('INSERT INTO mobile_tokens (user_id, token_hash, device, platform, created_at) VALUES (:uid, :h, :d, :p, :now)')
            ->execute(['uid' => $uid, 'h' => hash('sha256', $token), 'd' => self::deviceName($in), 'p' => self::platform($in), 'now' => Database::now()]);
        $pdo->prepare('UPDATE users SET last_login_at = :now WHERE id = :id')->execute(['now' => Database::now(), 'id' => $uid]);
        // Keep the 20 newest devices per person.
        $old = $pdo->prepare('SELECT id FROM mobile_tokens WHERE user_id = :uid ORDER BY id DESC LIMIT 100 OFFSET 20');
        $old->execute(['uid' => $uid]);
        foreach ($old->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $pdo->prepare('DELETE FROM mobile_tokens WHERE id = :id')->execute(['id' => (int) $id]);
        }
        json_response(['status' => 'ok', 'token' => $token]);
    }

    private static function code(int $uid, string $kind, int $seconds, ?string $data = null): string
    {
        $code = bin2hex(random_bytes(24));
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM mobile_codes WHERE expires_at < :now')->execute(['now' => Database::now()]);
        $pdo->prepare('INSERT INTO mobile_codes (user_id, kind, code_hash, data, expires_at, created_at) VALUES (:uid, :k, :h, :d, :exp, :now)')
            ->execute(['uid' => $uid, 'k' => $kind, 'h' => hash('sha256', $code), 'd' => $data, 'exp' => gmdate('Y-m-d H:i:s', time() + $seconds), 'now' => Database::now()]);
        return $code;
    }

    private static function findCode(string $code, string $kind): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $code)) {
            return null;
        }
        $stmt = Database::connection()->prepare('SELECT * FROM mobile_codes WHERE code_hash = :h AND kind = :k AND expires_at > :now');
        $stmt->execute(['h' => hash('sha256', $code), 'k' => $kind, 'now' => Database::now()]);
        return $stmt->fetch() ?: null;
    }

    /** Form fields or a JSON body. */
    private static function input(): array
    {
        static $in = null;
        if ($in === null) {
            $json = json_decode((string) file_get_contents('php://input'), true);
            $in = is_array($json) ? $json : $_POST;
        }
        return $in;
    }

    private static function deviceName(array $in): string
    {
        return mb_substr(trim(strip_tags((string) ($in['device'] ?? ''))), 0, 120) ?: 'Mobile app';
    }

    private static function platform(array $in): ?string
    {
        return in_array($in['platform'] ?? '', ['ios', 'android'], true) ? (string) $in['platform'] : null;
    }

    private static function fail(int $status, string $message): never
    {
        json_response(['status' => 'error', 'message' => $message], $status);
    }

    private static function channelOut(array $c): array
    {
        return [
            'id' => (int) $c['id'], 'network' => $c['provider'], 'name' => $c['name'], 'avatar' => $c['avatar_url'], 'can_publish' => (bool) $c['can_publish'], 'paused' => !empty($c['paused_at']),
            'problem' => ($c['stats_error'] ?? null) ?: (($c['inbox_error'] ?? null) ?: null), 'times' => count($c['times']),
        ];
    }

    private static function postOut(array $p, array $channels, DateTimeZone $tz, int $uid, ?array $metrics): array
    {
        $channel = $p['channel_id'] ? ($channels[(int) $p['channel_id']] ?? null) : null;
        $media = $p['media_id'] ? Media::describe(Media::ready($uid, (int) $p['media_id'])) : null;
        $at = $p['sent_at'] ?? $p['planned_at'] ?? null;
        $stats = null;
        if ($metrics !== null && empty($metrics['error'])) {
            $stats = array_filter(['views' => $metrics['impressions'] ?? $metrics['video_views'], 'reactions' => $metrics['reactions'], 'comments' => $metrics['comments'], 'shares' => $metrics['shares']], static fn ($v) => $v !== null);
        }
        return [
            'id' => (int) $p['id'], 'status' => $p['status'], 'network' => $p['channel'], 'channel_id' => $p['channel_id'] !== null ? (int) $p['channel_id'] : null,
            'channel' => $channel['name'] ?? null, 'text' => (string) $p['body'], 'title' => $p['title'] ?? null,
            'when' => $at ? Workspace::local((string) $at, $tz)->format('D j M, g:i A') : null, 'day' => $at ? Workspace::local((string) $at, $tz)->format('Y-m-d') : null,
            'url' => $p['external_url'] ?? null, 'error' => $p['error'] ?? null, 'attempts' => (int) ($p['attempts'] ?? 0),
            'media' => $media ? ['id' => $media['id'], 'kind' => $media['kind'], 'url' => $media['url']] : null,
            'photos' => $media ? 1 + count(Media::extraIds($p['extra_media'] ?? null)) : 0,
            'repeat' => $p['repeat_rule'] ?? null, 'evergreen' => !empty($p['evergreen']), 'note' => $p['review_note'] ?? null, 'stats' => $stats,
        ];
    }

    private static function ago(string $utc, DateTimeZone $tz): string
    {
        $at = Workspace::local($utc, $tz);
        $ago = time() - $at->getTimestamp();
        return match (true) {
            $ago < 3600 => max(1, intdiv($ago, 60)) . 'm ago',
            $ago < 86400 => intdiv($ago, 3600) . 'h ago',
            $ago < 7 * 86400 => intdiv($ago, 86400) . 'd ago',
            default => $at->format('j M Y'),
        };
    }

    private static function short(int $n): string
    {
        return match (true) {
            $n >= 1000000 => rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.') . 'M',
            $n >= 10000 => rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'K',
            default => number_format($n),
        };
    }

    private static function delta(mixed $now, mixed $before): string
    {
        if ($now === null || $before === null || (float) $before === 0.0) {
            return '';
        }
        return sprintf('%+d%%', (int) round(((float) $now - (float) $before) / (float) $before * 100));
    }
}
