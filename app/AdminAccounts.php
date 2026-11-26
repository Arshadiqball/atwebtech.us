<?php

declare(strict_types=1);

/**
 * Admin view of app accounts: every user, their channels and their posts.
 * Admins can pause or resume a channel, edit or reschedule a post, and delete a post.
 * Nothing here posts to or deletes from a social network; deleting a sent post only removes atwebtech's copy.
 */
final class AdminAccounts
{
    public const STATUSES = ['draft' => 'Draft', 'review' => 'Waiting for approval', 'planned' => 'Scheduled', 'publishing' => 'Sending', 'sent' => 'Sent', 'failed' => 'Failed'];
    private const PAGE = 100;

    /** @return list<array> */
    public static function users(string $q = ''): array
    {
        $sql = "SELECT u.id, u.email, u.display_name, u.timezone, u.created_at, u.last_login_at, u.parent_id, u.disabled_at,
                (SELECT COUNT(*) FROM channels c WHERE c.user_id = u.id) AS channels,
                (SELECT COUNT(*) FROM channels c WHERE c.user_id = u.id AND c.paused_at IS NOT NULL) AS paused,
                (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id) AS posts,
                (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id AND p.status = 'planned') AS scheduled,
                (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id AND p.status = 'failed') AS failed
            FROM users u";
        $params = [];
        if ($q !== '') {
            $sql .= ' WHERE u.email LIKE :q OR u.display_name LIKE :q2';
            $params = ['q' => '%' . $q . '%', 'q2' => '%' . $q . '%'];
        }
        $sql .= ' ORDER BY u.id DESC LIMIT 500';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function user(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, email, display_name, timezone, created_at, last_login_at, parent_id, disabled_at FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** @return list<array> */
    public static function channels(?int $userId = null, string $q = ''): array
    {
        [$where, $params] = self::channelWhere($userId, $q);
        $stmt = Database::connection()->prepare(
            'SELECT c.id, c.user_id, c.provider, c.name, c.kind, c.paused_at, c.created_at, c.stats_error, c.inbox_error, u.email,
                (SELECT COUNT(*) FROM posts p WHERE p.channel_id = c.id AND p.status = \'planned\') AS scheduled,
                (SELECT COUNT(*) FROM posts p WHERE p.channel_id = c.id AND p.status = \'sent\') AS sent
             FROM channels c JOIN users u ON u.id = c.user_id WHERE ' . $where
            . ' ORDER BY c.paused_at IS NULL, u.email, c.provider, c.name LIMIT 1000'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @param array{status?:string, q?:string, user?:int, channel?:int, page?:int} $filter
     * @return array{rows:list<array>, total:int, page:int, pages:int}
     */
    public static function posts(array $filter): array
    {
        [$where, $params] = self::postWhere($filter);
        $from = self::POST_FROM . $where;
        $count = Database::connection()->prepare('SELECT COUNT(*)' . $from);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PAGE));
        $page = min($pages, max(1, (int) ($filter['page'] ?? 1)));
        $stmt = Database::connection()->prepare(
            'SELECT p.id, p.user_id, p.channel, p.channel_id, p.body, p.title, p.status, p.planned_at, p.sent_at, p.external_url, p.error, p.updated_at,
                    u.email, u.timezone, c.provider, c.name AS channel_name, c.paused_at'
            . $from . ' ORDER BY CASE p.status WHEN \'failed\' THEN 0 WHEN \'publishing\' THEN 1 WHEN \'planned\' THEN 2 ELSE 3 END, p.planned_at DESC, p.id DESC'
            . ' LIMIT ' . self::PAGE . ' OFFSET ' . (($page - 1) * self::PAGE)
        );
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    private const POST_FROM = ' FROM posts p JOIN users u ON u.id = p.user_id LEFT JOIN channels c ON c.id = p.channel_id WHERE ';

    /** @return array{0:string, 1:array} SQL condition and parameters for a posts filter. */
    private static function postWhere(array $filter): array
    {
        $where = ['1 = 1'];
        $params = [];
        $status = (string) ($filter['status'] ?? '');
        if (isset(self::STATUSES[$status])) {
            $where[] = 'p.status = :status';
            $params['status'] = $status;
        }
        if (!empty($filter['user'])) {
            $where[] = 'p.user_id = :uid';
            $params['uid'] = (int) $filter['user'];
        }
        if (!empty($filter['channel'])) {
            $where[] = 'p.channel_id = :cid';
            $params['cid'] = (int) $filter['channel'];
        }
        $q = trim((string) ($filter['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.body LIKE :q OR u.email LIKE :q2)';
            $params += ['q' => '%' . $q . '%', 'q2' => '%' . $q . '%'];
        }
        return [implode(' AND ', $where), $params];
    }

    /** @return array{0:string, 1:array} SQL condition and parameters for a channels search. */
    private static function channelWhere(?int $userId, string $q): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($userId !== null) {
            $where[] = 'c.user_id = :uid';
            $params['uid'] = $userId;
        }
        if ($q !== '') {
            $where[] = '(c.name LIKE :q OR u.email LIKE :q2 OR c.provider LIKE :q3)';
            $params += ['q' => '%' . $q . '%', 'q2' => '%' . $q . '%', 'q3' => '%' . $q . '%'];
        }
        return [implode(' AND ', $where), $params];
    }

    public static function post(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*, u.email, u.timezone, c.provider, c.name AS channel_name, c.paused_at
             FROM posts p JOIN users u ON u.id = p.user_id LEFT JOIN channels c ON c.id = p.channel_id WHERE p.id = :id'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** The post owner's time zone, used to show and edit post times. */
    public static function zone(array $row): DateTimeZone
    {
        return Workspace::timezone(['timezone' => (string) ($row['timezone'] ?? '')]);
    }

    public static function local(?string $utc, DateTimeZone $tz): ?DateTimeImmutable
    {
        if ($utc === null || $utc === '') {
            return null;
        }
        return Admin::when($utc)->setTimezone($tz);
    }

    /** GET /admin/users */
    public static function usersPage(): void
    {
        $user = Auth::requireAdmin();
        $q = trim((string) ($_GET['q'] ?? ''));
        self::render('admin-users', 'Users', ['user' => $user, 'rows' => self::users($q), 'q' => $q]);
    }

    /** GET /admin/users/{id} */
    public static function userPage(string $id): void
    {
        $user = Auth::requireAdmin();
        $account = ctype_digit($id) ? self::user((int) $id) : null;
        if ($account === null) {
            not_found();
        }
        self::render('admin-user', (string) $account['email'], [
            'user' => $user,
            'account' => $account,
            'channels' => self::channels((int) $id),
            'posts' => self::posts(['user' => (int) $id]),
        ]);
    }

    /** GET /admin/channels */
    public static function channelsPage(): void
    {
        $user = Auth::requireAdmin();
        $q = trim((string) ($_GET['q'] ?? ''));
        self::render('admin-channels', 'Channels', ['user' => $user, 'channels' => self::channels(null, $q), 'q' => $q]);
    }

    /** GET /admin/posts */
    public static function postsPage(): void
    {
        $user = Auth::requireAdmin();
        $filter = [
            'status' => (string) ($_GET['status'] ?? ''),
            'q' => trim((string) ($_GET['q'] ?? '')),
            'user' => ctype_digit((string) ($_GET['user'] ?? '')) ? (int) $_GET['user'] : 0,
            'channel' => ctype_digit((string) ($_GET['channel'] ?? '')) ? (int) $_GET['channel'] : 0,
            'page' => (int) ($_GET['page'] ?? 1),
        ];
        $channel = null;
        if ($filter['channel'] > 0) {
            $stmt = Database::connection()->prepare('SELECT id, name FROM channels WHERE id = :id');
            $stmt->execute(['id' => $filter['channel']]);
            $channel = $stmt->fetch() ?: null;
        }
        self::render('admin-posts', 'Posts', [
            'user' => $user,
            'result' => self::posts($filter),
            'filter' => array_map(static fn ($v) => $v === 0 ? '' : (string) $v, array_diff_key($filter, ['page' => 1])),
            'owner' => $filter['user'] > 0 ? self::user($filter['user']) : null,
            'channel' => $channel,
        ]);
    }

    /** GET /admin/posts/{id} */
    public static function postPage(string $id): void
    {
        $user = Auth::requireAdmin();
        $post = ctype_digit($id) ? self::post((int) $id) : null;
        if ($post === null) {
            not_found();
        }
        self::render('admin-post', 'Post #' . $post['id'], ['user' => $user, 'post' => $post]);
    }

    private static function render(string $view, string $title, array $data): void
    {
        View::render($view, $data, [
            'title' => $title . ' | Admin | atwebtech',
            'description' => 'Admin view of app accounts.',
            'layout' => 'app',
            'noindex' => true,
            'admin' => true,
        ]);
    }

    /** POST /admin/channels/{id}/pause: pause or resume one channel. */
    public static function togglePause(string $id): never
    {
        Auth::requireAdmin();
        $back = self::back('/admin/channels');
        if (!ctype_digit($id) || !Security::verifyCsrf($_POST['_token'] ?? null)) {
            self::done($back, 'Your session expired. Refresh the page and try again.');
        }
        $pause = ($_POST['pause'] ?? '') === '1';
        $stmt = Database::connection()->prepare('UPDATE channels SET paused_at = :at, updated_at = :now WHERE id = :id');
        $stmt->execute(['at' => $pause ? Database::now() : null, 'now' => Database::now(), 'id' => (int) $id]);
        self::done($back, $stmt->rowCount() > 0
            ? ($pause ? 'Channel paused. Nothing is published to it until it is resumed.' : 'Channel resumed. Its due posts go out on the next run.')
            : 'That channel no longer exists.');
    }

    /** POST /admin/posts/{id}: save text, title, time and status. */
    public static function save(string $id): never
    {
        Auth::requireAdmin();
        if (!ctype_digit($id)) {
            not_found();
        }
        $back = '/admin/posts/' . $id;
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            self::done($back, 'Your session expired. Refresh the page and try again.');
        }
        $post = self::post((int) $id);
        if ($post === null) {
            self::done('/admin/posts', 'That post no longer exists.');
        }
        if (in_array($post['status'], ['sent', 'publishing'], true)) {
            self::done($back, $post['status'] === 'sent'
                ? 'This post is already published, so changing it here would not change it on the network.'
                : 'This post is being sent right now. Try again in a minute.');
        }
        $body = trim(str_replace("\r\n", "\n", (string) ($_POST['body'] ?? '')));
        $title = trim((string) ($_POST['title'] ?? ''));
        $status = (string) ($_POST['status'] ?? $post['status']);
        if (!in_array($status, ['draft', 'review', 'planned'], true)) {
            $status = 'draft';
        }
        $planned = $post['planned_at'];
        $date = (string) ($_POST['date'] ?? '');
        $time = (string) ($_POST['time'] ?? '');
        if ($date !== '' || $time !== '') {
            $at = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time, self::zone($post));
            if (!$at instanceof DateTimeImmutable) {
                self::done($back, 'Enter a valid date and time.');
            }
            $planned = $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }
        if ($status === 'planned') {
            if ($planned === null) {
                self::done($back, 'Pick a date and time to schedule this post.');
            }
            if ($post['channel_id'] === null) {
                self::done($back, 'This post has no connected channel, so it can only be a draft or a plan.');
            }
        }
        if ($body === '' && ($post['media_id'] ?? null) === null) {
            self::done($back, 'The post needs some text.');
        }
        $retry = $post['status'] === 'failed' || $status !== $post['status'];
        $sql = 'UPDATE posts SET body = :body, title = :title, planned_at = :at, status = :status, updated_at = :now'
            . ($retry ? ', error = NULL, attempts = 0' : '') . ' WHERE id = :id AND status NOT IN (\'sent\', \'publishing\')';
        Database::connection()->prepare($sql)->execute([
            'body' => $body, 'title' => $title !== '' ? $title : null, 'at' => $planned, 'status' => $status, 'now' => Database::now(), 'id' => (int) $id,
        ]);
        self::done($back, $status === 'planned' ? 'Saved and scheduled.' : 'Saved.');
    }

    /** POST /admin/posts/{id}/delete */
    public static function delete(string $id): never
    {
        Auth::requireAdmin();
        $back = self::back('/admin/posts');
        if (!ctype_digit($id) || !Security::verifyCsrf($_POST['_token'] ?? null)) {
            self::done($back, 'Your session expired. Refresh the page and try again.');
        }
        $stmt = Database::connection()->prepare('DELETE FROM posts WHERE id = :id AND status <> \'publishing\'');
        $stmt->execute(['id' => (int) $id]);
        if (str_starts_with($back, '/admin/posts/' . $id)) {
            $back = '/admin/posts';
        }
        self::done($back, $stmt->rowCount() > 0 ? 'Post deleted.' : 'That post could not be deleted. It may be sending right now, or already deleted.');
    }

    /**
     * POST /admin/posts/bulk: delete, disable (back to draft) or schedule the ticked posts,
     * or every post matching the current filter when "all matching" is ticked.
     */
    public static function bulkPosts(): never
    {
        Auth::requireAdmin();
        $back = self::back('/admin/posts');
        self::checkToken($back);
        $action = (string) ($_POST['action'] ?? '');
        if (self::scopeAll()) {
            [$where, $params] = self::postWhere([
                'status' => $_POST['f_status'] ?? '', 'q' => $_POST['f_q'] ?? '',
                'user' => (int) ($_POST['f_user'] ?? 0), 'channel' => (int) ($_POST['f_channel'] ?? 0),
            ]);
            $stmt = Database::connection()->prepare('SELECT p.id' . self::POST_FROM . $where);
            $stmt->execute($params);
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } else {
            $ids = self::ids();
        }
        if ($ids === []) {
            self::done($back, 'Tick at least one post first.');
        }
        $done = 0;
        foreach (array_chunk($ids, 500) as $chunk) {
            $in = implode(',', $chunk);
            $sql = match ($action) {
                'delete' => "DELETE FROM posts WHERE id IN ($in) AND status <> 'publishing'",
                'disable' => "UPDATE posts SET status = 'draft', updated_at = :now WHERE id IN ($in) AND status IN ('planned', 'review', 'failed')",
                'schedule' => "UPDATE posts SET status = 'planned', error = NULL, attempts = 0, updated_at = :now WHERE id IN ($in)
                               AND status IN ('draft', 'review', 'failed') AND planned_at IS NOT NULL AND channel_id IS NOT NULL",
                default => self::done($back, 'Pick an action.'),
            };
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($action === 'delete' ? [] : ['now' => Database::now()]);
            $done += $stmt->rowCount();
        }
        $skipped = count($ids) - $done;
        $verb = ['delete' => 'deleted', 'disable' => 'disabled (moved to drafts)', 'schedule' => 'scheduled'][$action];
        self::done($back, sprintf('%d %s %s.', $done, $done === 1 ? 'post' : 'posts', $verb)
            . ($skipped > 0 ? sprintf(' %d skipped: %s.', $skipped, match ($action) {
                'delete' => 'they were sending right now',
                'disable' => 'already drafts, sending or sent',
                'schedule' => 'already scheduled or sent, or no time or connected channel',
            }) : ''));
    }

    /** POST /admin/channels/bulk: pause, resume or delete the ticked channels, or all matching the search. */
    public static function bulkChannels(): never
    {
        Auth::requireAdmin();
        $back = self::back('/admin/channels');
        self::checkToken($back);
        $action = (string) ($_POST['action'] ?? '');
        $pdo = Database::connection();
        if (self::scopeAll()) {
            $uid = (int) ($_POST['f_user'] ?? 0);
            [$where, $params] = self::channelWhere($uid > 0 ? $uid : null, trim((string) ($_POST['f_q'] ?? '')));
            $stmt = $pdo->prepare('SELECT c.id FROM channels c JOIN users u ON u.id = c.user_id WHERE ' . $where);
            $stmt->execute($params);
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } else {
            $ids = self::ids();
        }
        if ($ids === []) {
            self::done($back, 'Tick at least one channel first.');
        }
        $done = 0;
        foreach (array_chunk($ids, 500) as $chunk) {
            $in = implode(',', $chunk);
            if ($action === 'pause' || $action === 'resume') {
                $stmt = $pdo->prepare("UPDATE channels SET paused_at = :at, updated_at = :now WHERE id IN ($in) AND paused_at IS " . ($action === 'pause' ? 'NULL' : 'NOT NULL'));
                $stmt->execute(['at' => $action === 'pause' ? Database::now() : null, 'now' => Database::now()]);
                $done += $stmt->rowCount();
            } elseif ($action === 'delete') {
                $rows = $pdo->query("SELECT id, user_id, provider, access_token, refresh_token FROM channels WHERE id IN ($in)")->fetchAll();
                $pdo->exec("DELETE FROM channels WHERE id IN ($in)");
                $done += count($rows);
                self::revokeOrphans($rows);
            } else {
                self::done($back, 'Pick an action.');
            }
        }
        $verb = ['pause' => 'paused', 'resume' => 'resumed', 'delete' => 'deleted'][$action];
        self::done($back, sprintf('%d %s %s.', $done, $done === 1 ? 'channel' : 'channels', $verb)
            . ($action === 'delete' && $done > 0 ? ' Their posts were kept as plans without a channel.' : ''));
    }

    /**
     * POST /admin/users/bulk: disable, enable or delete the ticked accounts, or all matching the search.
     * A disabled account is signed out everywhere, can't sign in, and its channels are paused.
     * Admin accounts (ADMIN_EMAILS) are never touched here.
     */
    public static function bulkUsers(): never
    {
        Auth::requireAdmin();
        $back = self::back('/admin/users');
        self::checkToken($back);
        $action = (string) ($_POST['action'] ?? '');
        $pdo = Database::connection();
        if (self::scopeAll()) {
            $q = trim((string) ($_POST['f_q'] ?? ''));
            $ids = array_map(static fn ($r) => (int) $r['id'], self::users($q));
        } else {
            $ids = self::ids();
        }
        $rows = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $rows = array_merge($rows, $pdo->query('SELECT id, email, parent_id, disabled_at FROM users WHERE id IN (' . implode(',', $chunk) . ')')->fetchAll());
        }
        $protected = 0;
        $rows = array_values(array_filter($rows, static function (array $r) use (&$protected): bool {
            if (self::isAdminEmail((string) $r['email'])) {
                $protected++;
                return false;
            }
            return true;
        }));
        if ($rows === []) {
            self::done($back, $protected > 0 ? 'Admin accounts can’t be disabled or deleted here.' : 'Tick at least one user first.');
        }
        $done = 0;
        $failed = 0;
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            if ($action === 'disable' && $r['disabled_at'] === null) {
                $at = Database::now();
                $pdo->prepare('UPDATE users SET disabled_at = :at, updated_at = :at2 WHERE (id = :id OR parent_id = :pid) AND disabled_at IS NULL')
                    ->execute(['at' => $at, 'at2' => $at, 'id' => $id, 'pid' => $id]);
                // Same timestamp, so enabling resumes exactly the channels this paused.
                $pdo->prepare('UPDATE channels SET paused_at = :at, updated_at = :at2 WHERE paused_at IS NULL AND user_id IN (SELECT id FROM users WHERE id = :id OR parent_id = :pid)')
                    ->execute(['at' => $at, 'at2' => $at, 'id' => $id, 'pid' => $id]);
                $pdo->prepare('DELETE FROM mobile_tokens WHERE user_id = :id')->execute(['id' => $id]);
                $done++;
            } elseif ($action === 'enable' && $r['disabled_at'] !== null) {
                $pdo->prepare('UPDATE channels SET paused_at = NULL, updated_at = :now WHERE paused_at = :at AND user_id IN (SELECT id FROM users WHERE id = :id OR parent_id = :pid)')
                    ->execute(['now' => Database::now(), 'at' => $r['disabled_at'], 'id' => $id, 'pid' => $id]);
                $pdo->prepare('UPDATE users SET disabled_at = NULL, updated_at = :now WHERE (id = :id OR parent_id = :pid) AND disabled_at = :at')
                    ->execute(['now' => Database::now(), 'id' => $id, 'pid' => $id, 'at' => $r['disabled_at']]);
                $done++;
            } elseif ($action === 'delete') {
                self::deleteAccount($id, (string) $r['email']) ? $done++ : $failed++;
            } elseif (!in_array($action, ['disable', 'enable', 'delete'], true)) {
                self::done($back, 'Pick an action.');
            }
        }
        $verb = ['disable' => 'disabled', 'enable' => 'enabled', 'delete' => 'deleted'][$action];
        if ($action === 'delete' && str_starts_with($back, '/admin/users/')) {
            $back = '/admin/users';
        }
        self::done($back, sprintf('%d %s %s.', $done, $done === 1 ? 'account' : 'accounts', $verb)
            . ($failed > 0 ? sprintf(' %d not deleted because their paid plan couldn’t be cancelled; try again.', $failed) : '')
            . ($protected > 0 ? sprintf(' %d admin %s skipped.', $protected, $protected === 1 ? 'account was' : 'accounts were') : ''));
    }

    /** Delete one account the same way its owner would from Account settings (Passwords::delete). */
    private static function deleteAccount(int $uid, string $email): bool
    {
        $pdo = Database::connection();
        if (($sub = Billing::current($uid)) !== null) {
            try {
                Billing::cancelNow($sub);
            } catch (Throwable $e) {
                error_log(sprintf('[%s] admin delete: could not cancel plan for user %d: %s', gmdate('c'), $uid, $e->getMessage()));
                return false;
            }
        }
        $kids = $pdo->prepare('SELECT id FROM users WHERE parent_id = :id');
        $kids->execute(['id' => $uid]);
        $in = implode(',', array_merge([$uid], array_map('intval', $kids->fetchAll(PDO::FETCH_COLUMN))));
        foreach ($pdo->query("SELECT * FROM media WHERE user_id IN ($in)")->fetchAll() as $media) {
            Media::discard($media);
        }
        foreach ($pdo->query("SELECT logo_file FROM report_settings WHERE user_id IN ($in) AND logo_file IS NOT NULL AND logo_file <> ''")->fetchAll(PDO::FETCH_COLUMN) as $logo) {
            @unlink(BASE_PATH . '/storage/branding/' . basename((string) $logo));
        }
        $pdo->exec("DELETE FROM users WHERE parent_id = $uid");
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $uid]);
        $pdo->prepare('DELETE FROM waitlist_signups WHERE email = :e')->execute(['e' => $email]);
        $pdo->prepare('DELETE FROM team_invites WHERE email = :e')->execute(['e' => $email]);
        error_log(sprintf('[%s] account %d deleted by an admin', gmdate('c'), $uid));
        return true;
    }

    /** Revoke atwebtech's access for deleted channels when the owner has no other channel on that network. */
    private static function revokeOrphans(array $rows): void
    {
        $left = Database::connection()->prepare('SELECT COUNT(*) FROM channels WHERE user_id = :uid AND provider = :p');
        $seen = [];
        foreach ($rows as $c) {
            $key = $c['user_id'] . '|' . $c['provider'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $left->execute(['uid' => (int) $c['user_id'], 'p' => $c['provider']]);
            if ((int) $left->fetchColumn() > 0) {
                continue;
            }
            try {
                Channels::revoke(Security::unseal($c['refresh_token']) ?? Security::unseal($c['access_token']), (string) $c['provider']);
            } catch (Throwable $e) {
                error_log(sprintf('[%s] admin channel revoke failed: %s', gmdate('c'), $e->getMessage()));
            }
        }
    }

    /** Accounts listed in ADMIN_EMAILS can't be disabled or deleted from the admin panel. */
    public static function isAdminEmail(string $email): bool
    {
        $admins = array_filter(array_map(static fn (string $e): string => strtolower(trim($e)), explode(',', (string) config('admin.emails'))));
        return in_array(strtolower(trim($email)), $admins, true);
    }

    private static function scopeAll(): bool
    {
        return ($_POST['scope'] ?? '') === 'all';
    }

    /** @return list<int> */
    private static function ids(): array
    {
        $ids = array_filter((array) ($_POST['ids'] ?? []), static fn ($v) => is_string($v) && ctype_digit($v));
        return array_values(array_unique(array_map('intval', $ids)));
    }

    private static function checkToken(string $back): void
    {
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            self::done($back, 'Your session expired. Refresh the page and try again.');
        }
    }

    /** Return to the admin page the form came from. */
    private static function back(string $fallback): string
    {
        $to = (string) ($_POST['back'] ?? '');
        return preg_match('#^/admin(/[A-Za-z0-9/_-]*)?(\?[A-Za-z0-9=&%_.+-]*)?$#', $to) ? $to : $fallback;
    }

    private static function done(string $to, string $message): never
    {
        flash('notice', $message);
        redirect($to);
    }
}
