<?php

declare(strict_types=1);

/**
 * Team accounts (Team plan). A teammate signs in with their own account and switches into the owner's
 * workspace; Auth::user() then returns the owner's row, so every workspace query stays scoped to the owner.
 * Roles limit what they can change (Team::guard, run before every route) and an optional channel list limits
 * which channels they see (Team::allowed, used by Channels, Workspace, Metrics and Community).
 */
final class Team
{
    public const ROLES = [
        'admin' => ['Admin', 'Everything except billing: channels, settings, the team, posts and approvals.'],
        'editor' => ['Editor', 'Create, schedule and publish posts, approve posts, reply to comments. No channel or account settings.'],
        'contributor' => ['Contributor', 'Write drafts and send them for approval. Can’t schedule, publish or reply.'],
    ];
    public const MAX_MEMBERS = 50;
    private const INVITE_DAYS = 7;
    private const RANK = ['contributor' => 1, 'editor' => 2, 'admin' => 3, 'owner' => 4];

    /** Paths that always act on the signed-in person's own account, never on a workspace they joined. */
    private const PERSONAL = '#^/(app/account(/(two-factor(/.*)?|password|profile|notifications))?|account/theme|logout|welcome|app/billing(/.*)?|app/workspaces)$#';
    public const MAX_WORKSPACES = 25;

    /** [methods, path regex, lowest role allowed]. Anything not listed is open to every role. */
    private const RULES = [
        ['POST', '#^/app/team$#', 'admin'],
        ['ANY', '#^/app/channels/(connect|callback|confirm|add)(/|$)#', 'admin'],
        ['ANY', '#^/app/channels/\d+/(settings|goal|schedule|disconnect|queue-tools|intro)$#', 'admin'],
        ['POST', '#^/account/timezone$#', 'admin'],
        ['ANY', '#^/app/developers$#', 'admin'],
        ['POST', '#^/app/automations$#', 'admin'],
        ['GET', '#^/app/automations$#', 'editor'],
        ['ANY', '#^/app/settings$#', 'admin'],
        ['POST', '#^/app/account/(utm|reports)$#', 'admin'],
        ['POST', '#^/app/link-in-bio(/.*)?$#', 'admin'],
        ['POST', '#^/app/channels/\d+/generate(/step)?$#', 'editor'],
        ['POST', '#^/app/(approvals|community|evergreen|competitors|feeds|hashtags|library|posts/move)$#', 'editor'],
    ];

    /**
     * The owner row, extended with team_role, team_channels, person_id and person_name, when $personId may
     * work in $workspaceId; null otherwise. The owner must be on the Team plan.
     */
    public static function membership(int $workspaceId, array $person): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT m.role, m.channels, u.id, u.email, u.display_name, u.theme, u.timezone, u.workspace_seeded_at, u.created_at
            FROM team_members m JOIN users u ON u.id = m.workspace_id WHERE m.workspace_id = :ws AND m.user_id = :uid');
        $stmt->execute(['ws' => $workspaceId, 'uid' => (int) $person['id']]);
        $row = $stmt->fetch();
        if (!$row || (!isset(self::ROLES[$row['role']]) && $row['role'] !== 'owner') || !self::planAllows($workspaceId)) {
            return null;
        }
        $channels = $row['channels'] !== null ? array_values(array_map('intval', (array) json_decode((string) $row['channels'], true))) : null;
        $role = $row['role'];
        unset($row['role'], $row['channels']);
        return $row + ['team_role' => $role, 'team_channels' => $channels, 'person_id' => (int) $person['id'], 'person_name' => (string) $person['display_name'], 'person_email' => (string) $person['email']];
    }

    public static function roleName(string $role): string
    {
        return $role === 'owner' ? 'Owner' : (self::ROLES[$role][0] ?? ucfirst($role));
    }

    public static function planAllows(int $ownerId): bool
    {
        return Channels::allowance($ownerId)['slug'] === 'team';
    }

    /** Role of the signed-in person in the workspace they're in: owner, admin, editor or contributor. */
    public static function role(): string
    {
        return (string) (Auth::user()['team_role'] ?? 'owner');
    }

    public static function can(string $minimum): bool
    {
        return self::RANK[self::role()] >= self::RANK[$minimum];
    }

    /** Channel ids the signed-in teammate may use in workspace $uid; null means all of them. */
    public static function allowed(int $uid): ?array
    {
        $user = Auth::user();
        if ($user === null || (int) $user['id'] !== $uid || !isset($user['team_role'])) {
            return null;
        }
        return $user['team_channels'];
    }

    /** SQL limiting a posts/comments query to the teammate's channels (posts with no channel stay visible). */
    public static function channelSql(int $uid, string $column = 'channel_id', bool $allowNull = true): string
    {
        $ids = self::allowed($uid);
        if ($ids === null) {
            return '';
        }
        $list = $ids ? implode(',', array_map('intval', $ids)) : '0';
        return ' AND (' . ($allowNull ? $column . ' IS NULL OR ' : '') . $column . ' IN (' . $list . '))';
    }

    /** Runs before every route: personal pages use the person's own account; roles gate the rest. */
    public static function guard(string $method, string $path): void
    {
        if (preg_match(self::PERSONAL, $path)) {
            Auth::personal();
            return;
        }
        $user = Auth::user();
        if ($user === null || !isset($user['team_role'])) {
            return;
        }
        foreach (self::RULES as [$methods, $regex, $minimum]) {
            if (($methods === 'ANY' || $methods === $method) && preg_match($regex, $path) && !self::can($minimum)) {
                $message = 'Your role (' . self::roleName($user['team_role']) . ') can’t do that in ' . $user['display_name'] . '’s workspace. Ask an admin.';
                if (wants_json()) {
                    json_response(['status' => 'error', 'message' => $message], 403);
                }
                flash('notice', $message);
                redirect('/app');
            }
        }
        if ($method === 'GET' && str_starts_with($path, '/app')) {
            Database::connection()->prepare('UPDATE team_members SET last_seen_at = :now WHERE workspace_id = :ws AND user_id = :uid AND (last_seen_at IS NULL OR last_seen_at < :ago)')
                ->execute(['now' => Database::now(), 'ws' => (int) $user['id'], 'uid' => (int) $user['person_id'], 'ago' => gmdate('Y-m-d H:i:s', time() - 300)]);
        }
    }

    /** Workspaces this person can open: their own first, then the ones they joined. */
    public static function workspacesFor(array $person): array
    {
        $list = [['id' => (int) $person['id'], 'name' => 'My workspace', 'role' => 'owner']];
        $stmt = Database::connection()->prepare('SELECT u.id, u.display_name, u.parent_id, m.role FROM team_members m JOIN users u ON u.id = m.workspace_id WHERE m.user_id = :uid ORDER BY u.parent_id IS NULL, u.display_name');
        $stmt->execute(['uid' => (int) $person['id']]);
        foreach ($stmt->fetchAll() as $r) {
            if (self::planAllows((int) $r['id'])) {
                $client = $r['parent_id'] !== null;
                $list[] = ['id' => (int) $r['id'], 'name' => $client ? (string) $r['display_name'] : $r['display_name'] . '’s workspace', 'role' => (string) $r['role'], 'client' => $client];
            }
        }
        return $list;
    }

    /** After sign-in: open the joined workspace when the person has none of their own channels. */
    public static function defaultWorkspace(int $personId): void
    {
        $pdo = Database::connection();
        $own = $pdo->prepare('SELECT COUNT(*) FROM channels WHERE user_id = :uid');
        $own->execute(['uid' => $personId]);
        if ((int) $own->fetchColumn() > 0) {
            return;
        }
        $stmt = $pdo->prepare('SELECT workspace_id FROM team_members WHERE user_id = :uid ORDER BY last_seen_at DESC, id DESC LIMIT 1');
        $stmt->execute(['uid' => $personId]);
        $ws = $stmt->fetchColumn();
        if ($ws !== false) {
            $_SESSION['_ws'] = (int) $ws;
        }
    }

    /** POST /app/workspace: switch between your own workspace and ones you joined. */
    public static function switch(): never
    {
        Auth::member();
        $person = Auth::person();
        $ws = (int) ($_POST['ws'] ?? 0);
        if ($ws === (int) $person['id'] || $ws === 0) {
            unset($_SESSION['_ws']);
            flash('notice', 'You’re in your own workspace.');
            redirect('/app');
        }
        if (self::membership($ws, $person) === null) {
            flash('notice', 'You no longer have access to that workspace.');
            redirect('/app');
        }
        $_SESSION['_ws'] = $ws;
        Auth::reload();
        flash('notice', 'Switched to ' . Auth::user()['display_name'] . '’s workspace.');
        redirect('/app');
    }

    /** Data for /app/team. */
    public static function page(array $user): array
    {
        $uid = (int) $user['id'];
        $pdo = Database::connection();
        $owner = $pdo->prepare('SELECT id, email, display_name FROM users WHERE id = :id');
        $owner->execute(['id' => Channels::parentOf($uid) ?? $uid]);
        $members = $pdo->prepare("SELECT m.id, m.role, m.channels, m.created_at, m.last_seen_at, u.id AS user_id, u.email, u.display_name FROM team_members m JOIN users u ON u.id = m.user_id WHERE m.workspace_id = :ws AND m.role <> 'owner' ORDER BY m.created_at");
        $members->execute(['ws' => $uid]);
        $invites = $pdo->prepare('SELECT id, email, name, role, channels, expires_at, created_at FROM team_invites WHERE workspace_id = :ws AND expires_at > :now ORDER BY created_at DESC');
        $invites->execute(['ws' => $uid, 'now' => Database::now()]);
        $link = $_SESSION['_team_link'] ?? null;
        unset($_SESSION['_team_link']);
        return [
            'owner' => $owner->fetch(), 'members' => $members->fetchAll(), 'invites' => $invites->fetchAll(),
            'plan' => self::planAllows($uid), 'teamChannels' => Channels::forUser($uid), 'link' => is_array($link) ? $link : null,
        ];
    }

    /** POST /app/team: invite, update, remove, revoke, or leave. */
    public static function handle(): never
    {
        $user = Auth::member();
        $uid = (int) $user['id'];
        $back = '/app/team';
        $action = (string) ($_POST['action'] ?? '');
        $pdo = Database::connection();

        if ($action === 'leave' && isset($user['team_role'])) {
            $pdo->prepare('DELETE FROM team_members WHERE workspace_id = :ws AND user_id = :uid')->execute(['ws' => $uid, 'uid' => (int) $user['person_id']]);
            unset($_SESSION['_ws']);
            flash('notice', 'You left ' . $user['display_name'] . '’s workspace.');
            redirect('/app');
        }
        if (!self::can('admin')) {
            self::done($back, 'Only admins can change the team.');
        }
        if (!self::planAllows($uid)) {
            self::done($back, 'Team members are part of the Team plan. The owner can upgrade under Plan & billing.');
        }

        if ($action === 'invite') {
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80);
            $role = (string) ($_POST['role'] ?? 'editor');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
                self::done($back, 'Enter a valid email address.');
            }
            if (!isset(self::ROLES[$role])) {
                self::done($back, 'Choose a role.');
            }
            if ($role === 'admin' && self::role() !== 'owner') {
                self::done($back, 'Only the workspace owner can add admins.');
            }
            $ownerEmail = $pdo->prepare('SELECT email FROM users WHERE id = :id');
            $ownerEmail->execute(['id' => $uid]);
            if (strtolower((string) $ownerEmail->fetchColumn()) === $email) {
                self::done($back, 'That’s the owner’s own email.');
            }
            $exists = $pdo->prepare('SELECT 1 FROM team_members m JOIN users u ON u.id = m.user_id WHERE m.workspace_id = :ws AND LOWER(u.email) = :email');
            $exists->execute(['ws' => $uid, 'email' => $email]);
            if ($exists->fetchColumn()) {
                self::done($back, $email . ' is already on the team.');
            }
            if (self::count($uid) >= self::MAX_MEMBERS) {
                self::done($back, sprintf('A workspace can have up to %d team members and invites.', self::MAX_MEMBERS));
            }
            if (RateLimiter::tooManyAttempts('team-invite', 'user:' . $uid, 30, 60)) {
                self::done($back, 'That’s a lot of invites. Try again in an hour.');
            }
            RateLimiter::hit('team-invite', 'user:' . $uid);
            $token = bin2hex(random_bytes(24));
            $pdo->prepare('DELETE FROM team_invites WHERE workspace_id = :ws AND LOWER(email) = :email')->execute(['ws' => $uid, 'email' => $email]);
            $pdo->prepare('INSERT INTO team_invites (workspace_id, email, name, role, channels, token_hash, invited_by, expires_at, created_at) VALUES (:ws, :email, :name, :role, :ch, :hash, :by, :exp, :now)')
                ->execute([
                    'ws' => $uid, 'email' => $email, 'name' => $name !== '' ? $name : null, 'role' => $role, 'ch' => self::channelsFromPost($uid),
                    'hash' => hash('sha256', $token), 'by' => (int) ($user['person_id'] ?? $uid), 'exp' => gmdate('Y-m-d H:i:s', time() + self::INVITE_DAYS * 86400), 'now' => Database::now(),
                ]);
            $url = absolute_url('/join/' . $token);
            $mail = EmailTemplate::teamInvite($url, (string) $user['display_name'], $name, self::ROLES[$role][0], self::INVITE_DAYS);
            Mailer::send($email, $mail['subject'], $mail['html'], $mail['text']);
            $_SESSION['_team_link'] = ['email' => $email, 'url' => $url];
            self::done($back, config('mail.enabled') ? 'Invite emailed to ' . $email . '.' : 'Invite created. Copy the link below and send it to ' . $email . '.');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($action === 'revoke') {
            $pdo->prepare('DELETE FROM team_invites WHERE id = :id AND workspace_id = :ws')->execute(['id' => $id, 'ws' => $uid]);
            self::done($back, 'Invite cancelled. The link no longer works.');
        }
        $member = $pdo->prepare('SELECT * FROM team_members WHERE id = :id AND workspace_id = :ws');
        $member->execute(['id' => $id, 'ws' => $uid]);
        $member = $member->fetch();
        if (!$member || $member['role'] === 'owner') {
            self::done($back, 'That person is no longer on the team.');
        }
        if ($member['role'] === 'admin' && self::role() !== 'owner') {
            self::done($back, 'Only the workspace owner can change or remove admins.');
        }
        if ($action === 'remove') {
            $pdo->prepare('DELETE FROM team_members WHERE id = :id AND workspace_id = :ws')->execute(['id' => $id, 'ws' => $uid]);
            self::done($back, 'Removed from the team. They lose access right away.');
        }
        if ($action === 'update') {
            $role = (string) ($_POST['role'] ?? '');
            if (!isset(self::ROLES[$role]) || ($role === 'admin' && self::role() !== 'owner')) {
                self::done($back, 'Choose a role you’re allowed to give.');
            }
            $pdo->prepare('UPDATE team_members SET role = :role, channels = :ch WHERE id = :id AND workspace_id = :ws')
                ->execute(['role' => $role, 'ch' => self::channelsFromPost($uid), 'id' => $id, 'ws' => $uid]);
            self::done($back, 'Access updated.');
        }
        self::done($back, 'Nothing changed.');
    }

    /** Client workspaces this person owns. */
    public static function clientWorkspaces(int $personId): array
    {
        $stmt = Database::connection()->prepare('SELECT u.id, u.display_name, u.created_at, (SELECT COUNT(*) FROM channels c WHERE c.user_id = u.id) AS channels,
            (SELECT COUNT(*) FROM team_members m WHERE m.workspace_id = u.id AND m.role <> \'owner\') AS people FROM users u WHERE u.parent_id = :p ORDER BY u.display_name');
        $stmt->execute(['p' => $personId]);
        return $stmt->fetchAll();
    }

    /** POST /app/workspaces: create, rename or delete a client workspace (runs as the signed-in person). */
    public static function workspaces(): never
    {
        $person = Auth::member();
        $pid = (int) $person['id'];
        $back = '/app/workspaces';
        $pdo = Database::connection();
        if (Channels::parentOf($pid) !== null) {
            self::done($back, 'Client workspaces can’t have their own client workspaces.');
        }
        if (!self::planAllows($pid)) {
            self::done($back, 'Client workspaces are part of the Team plan. Upgrade under Plan & billing.');
        }
        $action = (string) ($_POST['action'] ?? '');
        $name = mb_substr(trim(preg_replace('/\s+/', ' ', (string) ($_POST['name'] ?? ''))), 0, 80);
        if ($action === 'create') {
            if ($name === '') {
                self::done($back, 'Give the workspace a name, like the client’s business.');
            }
            if (count(self::clientWorkspaces($pid)) >= self::MAX_WORKSPACES) {
                self::done($back, sprintf('You can have up to %d client workspaces.', self::MAX_WORKSPACES));
            }
            $now = Database::now();
            $tz = $pdo->prepare('SELECT timezone FROM users WHERE id = :id');
            $tz->execute(['id' => $pid]);
            // No login: an address that can't receive mail and a password hash nothing matches.
            $pdo->prepare('INSERT INTO users (email, password_hash, display_name, theme, timezone, workspace_seeded_at, parent_id, created_at, updated_at) VALUES (:email, :hash, :name, :theme, :tz, :seeded, :parent, :now, :now2)')
                ->execute(['email' => 'workspace-' . bin2hex(random_bytes(10)) . '@workspaces.invalid', 'hash' => '!no-login', 'name' => $name, 'theme' => 'light', 'tz' => $tz->fetchColumn() ?: null, 'seeded' => $now, 'parent' => $pid, 'now' => $now, 'now2' => $now]);
            $ws = (int) $pdo->lastInsertId();
            $onboarding = Auth::onboarding($ws);
            $pdo->prepare('UPDATE user_onboarding SET completed_at = :now, checklist_closed_at = :now2, updated_at = :now3 WHERE user_id = :id')->execute(['now' => $now, 'now2' => $now, 'now3' => $now, 'id' => $ws]);
            unset($onboarding);
            $pdo->prepare('INSERT INTO team_members (workspace_id, user_id, role, channels, invited_by, created_at) VALUES (:ws, :uid, :role, NULL, :by, :now)')
                ->execute(['ws' => $ws, 'uid' => $pid, 'role' => 'owner', 'by' => $pid, 'now' => $now]);
            $_SESSION['_ws'] = $ws;
            flash('notice', $name . ' is ready. Connect its channels to start.');
            redirect('/app/channels');
        }
        $ws = (int) ($_POST['id'] ?? 0);
        $own = $pdo->prepare('SELECT id, display_name FROM users WHERE id = :id AND parent_id = :p');
        $own->execute(['id' => $ws, 'p' => $pid]);
        $row = $own->fetch();
        if (!$row) {
            self::done($back, 'That workspace no longer exists.');
        }
        if ($action === 'rename') {
            if ($name === '') {
                self::done($back, 'Give the workspace a name.');
            }
            $pdo->prepare('UPDATE users SET display_name = :n, updated_at = :now WHERE id = :id AND parent_id = :p')->execute(['n' => $name, 'now' => Database::now(), 'id' => $ws, 'p' => $pid]);
            self::done($back, 'Renamed to ' . $name . '.');
        }
        if ($action === 'delete') {
            if (trim((string) ($_POST['confirm'] ?? '')) !== $row['display_name']) {
                self::done($back, 'Type the workspace name exactly to delete it.');
            }
            $pdo->prepare('DELETE FROM users WHERE id = :id AND parent_id = :p')->execute(['id' => $ws, 'p' => $pid]);
            if (($_SESSION['_ws'] ?? null) === $ws) {
                unset($_SESSION['_ws']);
            }
            self::done($back, $row['display_name'] . ' and everything in it was deleted.');
        }
        self::done($back, 'Nothing changed.');
    }

    /** GET /join/{token} */
    public static function join(string $token): never
    {
        $invite = self::invite($token);
        $person = Auth::person();
        $owner = null;
        if ($invite) {
            $stmt = Database::connection()->prepare('SELECT display_name FROM users WHERE id = :id');
            $stmt->execute(['id' => (int) $invite['workspace_id']]);
            $owner = (string) $stmt->fetchColumn();
        }
        header('X-Robots-Tag: noindex, nofollow');
        View::render('team-join', ['invite' => $invite, 'owner' => $owner, 'person' => $person, 'token' => $token], ['layout' => 'app', 
            'title' => 'Join a workspace | atwebtech', 'description' => 'Accept an invitation to an atwebtech workspace.', 'noindex' => true,
        ]);
        exit;
    }

    /** POST /join/{token}: the signed-in person accepts. */
    public static function accept(string $token): never
    {
        Auth::member();
        $person = Auth::person();
        $invite = self::invite($token);
        if ($invite === null) {
            flash('notice', 'That invite has expired or was cancelled. Ask for a new one.');
            redirect('/app');
        }
        if (strtolower((string) $person['email']) !== strtolower((string) $invite['email'])) {
            redirect('/join/' . $token);
        }
        if (!self::planAllows((int) $invite['workspace_id'])) {
            flash('notice', 'That workspace isn’t on the Team plan any more, so the invite can’t be used.');
            redirect('/app');
        }
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM team_members WHERE workspace_id = :ws AND user_id = :uid')->execute(['ws' => (int) $invite['workspace_id'], 'uid' => (int) $person['id']]);
        $pdo->prepare('INSERT INTO team_members (workspace_id, user_id, role, channels, invited_by, created_at) VALUES (:ws, :uid, :role, :ch, :by, :now)')
            ->execute(['ws' => (int) $invite['workspace_id'], 'uid' => (int) $person['id'], 'role' => $invite['role'], 'ch' => $invite['channels'], 'by' => $invite['invited_by'], 'now' => Database::now()]);
        $pdo->prepare('DELETE FROM team_invites WHERE id = :id')->execute(['id' => (int) $invite['id']]);
        // Teammates skip the new-account questions: they work in the owner's workspace.
        $onboarding = Auth::onboarding((int) $person['id']);
        if ($onboarding['completed_at'] === null) {
            $pdo->prepare('UPDATE user_onboarding SET completed_at = :now, updated_at = :now2 WHERE user_id = :id')->execute(['now' => Database::now(), 'now2' => Database::now(), 'id' => (int) $person['id']]);
        }
        $_SESSION['_ws'] = (int) $invite['workspace_id'];
        Auth::reload();
        flash('notice', 'Welcome to ' . Auth::user()['display_name'] . '’s workspace.');
        redirect('/app');
    }

    /** Display names of people who created posts in this workspace, by user id. */
    public static function names(int $uid): array
    {
        $stmt = Database::connection()->prepare('SELECT u.id, u.display_name FROM team_members m JOIN users u ON u.id = m.user_id WHERE m.workspace_id = :ws');
        $stmt->execute(['ws' => $uid]);
        return array_column($stmt->fetchAll(), 'display_name', 'id');
    }

    private static function invite(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }
        $stmt = Database::connection()->prepare('SELECT * FROM team_invites WHERE token_hash = :h AND expires_at > :now');
        $stmt->execute(['h' => hash('sha256', $token), 'now' => Database::now()]);
        return $stmt->fetch() ?: null;
    }

    /** Channel access from the form: null for all channels, else a JSON list of this workspace's channel ids. */
    private static function channelsFromPost(int $uid): ?string
    {
        if (($_POST['access'] ?? 'all') === 'all') {
            return null;
        }
        $owned = array_map(static fn (array $c): int => (int) $c['id'], Channels::forUser($uid));
        $picked = array_values(array_intersect($owned, array_map('intval', (array) ($_POST['channels'] ?? []))));
        return json_encode($picked);
    }

    private static function count(int $uid): int
    {
        $pdo = Database::connection();
        $a = $pdo->prepare('SELECT COUNT(*) FROM team_members WHERE workspace_id = :ws');
        $a->execute(['ws' => $uid]);
        $b = $pdo->prepare('SELECT COUNT(*) FROM team_invites WHERE workspace_id = :ws AND expires_at > :now');
        $b->execute(['ws' => $uid, 'now' => Database::now()]);
        return (int) $a->fetchColumn() + (int) $b->fetchColumn();
    }

    private static function done(string $location, string $message): never
    {
        flash('notice', $message);
        redirect($location);
    }
}
