<?php

declare(strict_types=1);

/** State-changing workspace requests: ideas, idea groups, posts, templates, time zone. */
final class WorkspaceHandler
{
    private const MAX_GROUPS = 12;
    private const MAX_IDEAS = 500;
    private const MAX_POSTS = 1000;
    private const MAX_TEMPLATES = 100;

    public static function idea(): never
    {
        $user = Auth::member();
        $uid = (int) $user['id'];
        $back = self::back('/app/create');
        $id = (int) ($_POST['id'] ?? 0);
        $existing = $id > 0 ? Workspace::idea($uid, $id) : null;
        if ($id > 0 && $existing === null) {
            self::fail('idea', $back, 'That idea no longer exists.');
        }

        $pdo = Database::connection();
        if (($_POST['action'] ?? '') === 'delete') {
            if ($existing) {
                $pdo->prepare('DELETE FROM ideas WHERE id = :id AND user_id = :uid')->execute(['id' => $id, 'uid' => $uid]);
            }
            self::done($back, 'Idea deleted.', ['id' => $id]);
        }

        $input = [
            'title' => self::clean((string) ($_POST['title'] ?? ''), false),
            'body' => self::clean((string) ($_POST['body'] ?? ''), true),
            'tags' => self::tags((string) ($_POST['tags'] ?? '')),
            'group' => (int) ($_POST['group'] ?? 0),
        ];
        $errors = [];
        if ($input['title'] === '') {
            $errors['title'] = 'Give your idea a title.';
        } elseif (mb_strlen($input['title']) > 140) {
            $errors['title'] = 'Keep the title under 140 characters.';
        }
        if (mb_strlen($input['body']) > 5000) {
            $errors['body'] = 'Keep the note under 5,000 characters.';
        }
        if ($input['group'] > 0 && Workspace::group($uid, $input['group']) === null) {
            $input['group'] = 0;
        }
        if ($errors) {
            self::fail('idea', $back, 'Please check the highlighted fields.', $errors, $input + ['id' => $id]);
        }

        $now = Database::now();
        $groupId = $input['group'] ?: null;
        if ($existing) {
            $moved = (int) ($existing['group_id'] ?? 0) !== $input['group'];
            $pdo->prepare('UPDATE ideas SET title = :title, body = :body, tags = :tags, group_id = :gid' . ($moved ? ', position = :pos' : '') . ', updated_at = :now WHERE id = :id AND user_id = :uid')
                ->execute(['title' => $input['title'], 'body' => $input['body'], 'tags' => $input['tags'], 'gid' => $groupId, 'now' => $now, 'id' => $id, 'uid' => $uid] + ($moved ? ['pos' => self::topPosition($uid, $groupId)] : []));
        } else {
            if (self::count('ideas', $uid) >= self::MAX_IDEAS) {
                self::fail('idea', $back, 'You have reached the limit of ' . self::MAX_IDEAS . ' ideas. Delete a few to add more.', [], $input);
            }
            $pdo->prepare('INSERT INTO ideas (user_id, group_id, title, body, tags, position, created_at, updated_at) VALUES (:uid, :gid, :title, :body, :tags, :pos, :created, :updated)')
                ->execute(['uid' => $uid, 'gid' => $groupId, 'title' => $input['title'], 'body' => $input['body'], 'tags' => $input['tags'], 'pos' => self::topPosition($uid, $groupId), 'created' => $now, 'updated' => $now]);
            $id = (int) $pdo->lastInsertId();
        }
        self::done($back, $existing ? 'Idea saved.' : 'Idea added.', ['idea' => Workspace::idea($uid, $id)]);
    }

    /** Move a queued post onto another day or empty time slot. */
    public static function movePost(): never
    {
        $user = Auth::member();
        $uid = (int) $user['id'];
        $id = (int) ($_POST['id'] ?? 0);
        $post = $id > 0 ? Workspace::post($uid, $id) : null;
        if ($post === null || !in_array($post['status'], ['planned', 'failed'], true)) {
            self::fail('composer', '/app/publish', 'That post can’t be moved.');
        }
        $date = (string) ($_POST['date'] ?? '');
        $time = (string) ($_POST['time'] ?? '');
        $tz = Workspace::timezone($user);
        $at = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && preg_match('/^\d{2}:\d{2}$/', $time)
            ? DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time, $tz)
            : false;
        if ($at === false || $at->format('Y-m-d H:i') !== $date . ' ' . $time) {
            self::fail('composer', '/app/publish', 'Choose a date and time for this post.');
        }
        $now = new DateTimeImmutable('now', $tz);
        if ($at <= $now) {
            self::fail('composer', '/app/publish', 'Drop the post on a time that hasn’t passed.');
        }
        if ($at > $now->modify('+1 year')) {
            self::fail('composer', '/app/publish', 'Choose a time within the next year.');
        }
        $stmt = Database::connection()->prepare('UPDATE posts SET planned_at = :at, status = :planned, error = NULL, attempts = 0, updated_at = :now WHERE id = :id AND user_id = :uid AND status IN (\'planned\', \'failed\')');
        $stmt->execute([
            'at' => $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'planned' => 'planned',
            'now' => Database::now(),
            'id' => $id,
            'uid' => $uid,
        ]);
        if ($stmt->rowCount() < 1) {
            self::fail('composer', '/app/publish', 'That post can’t be moved.');
        }
        self::done('/app/publish', 'Moved to ' . $at->format('l, j F') . ' at ' . $at->format('g:i A') . '.');
    }

    /** Save a column's order after drag and drop: `group` plus ordered `order[]` ids. */
    public static function moveIdeas(): never
    {
        $uid = (int) Auth::member()['id'];
        $group = (int) ($_POST['group'] ?? 0);
        if ($group > 0 && Workspace::group($uid, $group) === null) {
            self::fail('idea', '/app/create', 'That column no longer exists.');
        }
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['order'] ?? []))))), 0, self::MAX_IDEAS);
        $stmt = Database::connection()->prepare('UPDATE ideas SET group_id = :gid, position = :pos WHERE id = :id AND user_id = :uid');
        foreach ($ids as $pos => $id) {
            $stmt->execute(['gid' => $group ?: null, 'pos' => $pos, 'id' => $id, 'uid' => $uid]);
        }
        self::done('/app/create', 'Board updated.');
    }

    public static function group(): never
    {
        $uid = (int) Auth::member()['id'];
        $back = '/app/create';
        $action = (string) ($_POST['action'] ?? 'create');
        $id = (int) ($_POST['id'] ?? 0);
        $pdo = Database::connection();

        if ($action !== 'create' && ($id < 1 || Workspace::group($uid, $id) === null)) {
            self::fail('group', $back, 'That column no longer exists.');
        }
        if ($action === 'delete') {
            $pdo->prepare('UPDATE ideas SET group_id = NULL WHERE group_id = :id AND user_id = :uid')->execute(['id' => $id, 'uid' => $uid]);
            $pdo->prepare('DELETE FROM idea_groups WHERE id = :id AND user_id = :uid')->execute(['id' => $id, 'uid' => $uid]);
            self::done($back, 'Group deleted. Its ideas moved to Unassigned.', ['id' => $id]);
        }

        $name = self::clean((string) ($_POST['name'] ?? ''), false);
        if ($name === '' || mb_strlen($name) > 60) {
            self::fail('group', $back, 'Group names need 1 to 60 characters.', ['name' => 'Group names need 1 to 60 characters.']);
        }
        $now = Database::now();
        if ($action === 'rename') {
            $pdo->prepare('UPDATE idea_groups SET name = :name, updated_at = :now WHERE id = :id AND user_id = :uid')->execute(['name' => $name, 'now' => $now, 'id' => $id, 'uid' => $uid]);
            self::done($back, 'Group renamed.', ['group' => ['id' => $id, 'name' => $name]]);
        }
        if (count(Workspace::groups($uid)) >= self::MAX_GROUPS) {
            self::fail('group', $back, 'You can have up to ' . self::MAX_GROUPS . ' groups.');
        }
        $pos = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM idea_groups WHERE user_id = :uid');
        $pos->execute(['uid' => $uid]);
        $pdo->prepare('INSERT INTO idea_groups (user_id, name, position, created_at, updated_at) VALUES (:uid, :name, :pos, :created, :updated)')
            ->execute(['uid' => $uid, 'name' => $name, 'pos' => (int) $pos->fetchColumn(), 'created' => $now, 'updated' => $now]);
        self::done($back, 'Group added.', ['group' => ['id' => (int) $pdo->lastInsertId(), 'name' => $name]]);
    }

    public static function post(): never
    {
        $user = Auth::member();
        $uid = (int) $user['id'];
        $back = self::back('/app/publish');
        $id = (int) ($_POST['id'] ?? 0);
        $existing = $id > 0 ? Workspace::post($uid, $id) : null;
        if ($id > 0 && $existing === null) {
            self::fail('composer', $back, 'That post no longer exists.');
        }
        $action = (string) ($_POST['action'] ?? 'draft');
        $pdo = Database::connection();
        if ($existing && $existing['status'] === 'sent' && $action !== 'delete') {
            self::fail('composer', $back, 'This post was already published.');
        }
        if ($existing && $existing['status'] === 'publishing') {
            self::fail('composer', $back, 'This post is being published right now.');
        }

        if ($action === 'delete') {
            if ($existing) {
                $pdo->prepare('DELETE FROM posts WHERE id = :id AND user_id = :uid')->execute(['id' => $id, 'uid' => $uid]);
            }
            self::done($back, 'Post deleted.', ['id' => $id]);
        }
        if ($action === 'retry') {
            if (!$existing || $existing['status'] !== 'failed') {
                self::fail('composer', $back, 'Only posts that failed to publish can be retried.');
            }
            $pdo->prepare('UPDATE posts SET status = :planned, planned_at = :now, error = NULL, attempts = 0, updated_at = :now2 WHERE id = :id AND user_id = :uid')
                ->execute(['planned' => 'planned', 'now' => Database::now(), 'now2' => Database::now(), 'id' => $id, 'uid' => $uid]);
            self::done($back, 'Retrying now. It will publish within a couple of minutes.');
        }

        $settings = data('workspace');
        $yt = data('youtube');
        $known = array_column(integrations(), 'name', 'slug');
        $connected = array_column(Channels::forUser($uid), null, 'id');
        $raw = array_map('strval', (array) ($_POST['targets'] ?? []));
        if (!$raw && isset($_POST['channel'])) {
            $raw = ['n:' . (string) $_POST['channel']];
        }
        $targets = [];
        foreach (array_unique($raw) as $key) {
            if (preg_match('/^c:(\d+)$/', $key, $m) && isset($connected[(int) $m[1]])) {
                $targets[$key] = ['provider' => $connected[(int) $m[1]]['provider'], 'channel' => $connected[(int) $m[1]]];
            } elseif (preg_match('/^n:([a-z0-9-]+)$/', $key, $m) && isset($known[$m[1]])) {
                $targets[$key] = ['provider' => $m[1], 'channel' => null];
            }
        }
        if ($existing) {
            $targets = array_slice($targets, 0, 1, true);
        }
        $input = [
            'targets' => array_keys($targets),
            'body' => self::clean((string) ($_POST['body'] ?? ''), true),
            'title' => self::clean((string) ($_POST['title'] ?? ''), false),
            'tags' => self::tags((string) ($_POST['tags'] ?? '')),
            'media' => (int) ($_POST['media_id'] ?? 0),
            'date' => (string) ($_POST['date'] ?? ''),
            'time' => (string) ($_POST['time'] ?? ''),
            'idea' => (int) ($_POST['idea'] ?? 0),
            'first_comment' => self::clean((string) ($_POST['first_comment'] ?? ''), true),
        ];
        $meta = [
            'category' => isset($yt['categories'][$_POST['yt_category'] ?? '']) ? (string) $_POST['yt_category'] : $yt['default_category'],
            'visibility' => isset($yt['visibility'][$_POST['yt_visibility'] ?? '']) ? (string) $_POST['yt_visibility'] : 'public',
            'license' => isset($yt['licenses'][$_POST['yt_license'] ?? '']) ? (string) $_POST['yt_license'] : 'youtube',
            'notify' => ($_POST['yt_notify'] ?? '') === '1',
            'embeddable' => ($_POST['yt_embeddable'] ?? '') === '1',
            'kids' => ($_POST['yt_kids'] ?? '') === '1',
            'synthetic' => ($_POST['yt_synthetic'] ?? '') === '1',
        ];
        $media = $input['media'] > 0 ? Media::ready($uid, $input['media']) : null;

        $errors = [];
        if (!$targets) {
            $errors['targets'] = 'Choose at least one channel for this post.';
        }
        if ($input['media'] > 0 && $media === null) {
            $errors['media'] = 'That file is no longer available. Please attach it again.';
        }
        $limit = (int) $settings['post_max'];
        $youtube = false;
        $linkedin = false;
        foreach ($targets as $target) {
            $limit = min($limit, (int) ($settings['limits'][$target['provider']] ?? $settings['post_max']));
            $youtube = $youtube || $target['provider'] === 'youtube';
            $linkedin = $linkedin || $target['provider'] === 'linkedin';
        }
        if ($input['body'] === '' && $media === null) {
            $errors['body'] = 'Write something or attach a file before saving.';
        } elseif (mb_strlen($input['body']) > $limit) {
            $errors['body'] = sprintf('This post can be up to %s characters for the channels you picked. It has %s.', number_format($limit), number_format(mb_strlen($input['body'])));
        }
        if ($youtube) {
            if (mb_strlen($input['title']) > $yt['title_max']) {
                $errors['title'] = sprintf('YouTube titles can be up to %d characters.', $yt['title_max']);
            } elseif (preg_match('/[<>]/', $input['title'])) {
                $errors['title'] = 'YouTube titles can’t contain < or >.';
            }
            if (preg_match('/[<>]/', $input['body'])) {
                $errors['body'] = 'YouTube descriptions can’t contain < or >.';
            }
            if ($media !== null && $media['kind'] !== 'video') {
                $errors['media'] = 'YouTube posts need a video, not an image.';
            }
        }
        if ($linkedin && $media !== null && $media['kind'] !== 'image') {
            $errors['media'] = 'LinkedIn posts from atwebtech can include an image, not a video.';
        }
        if ($youtube && $linkedin) {
            $errors['targets'] = 'YouTube needs a video and LinkedIn needs text (and an optional image). Schedule them as separate posts.';
        }
        if ($linkedin && mb_strlen($input['first_comment']) > 1250) {
            $errors['first_comment'] = 'A first comment can be up to 1,250 characters.';
        }
        if (!$linkedin) {
            $input['first_comment'] = '';
        }
        if ($action === 'plan') {
            foreach ($targets as $target) {
                $channel = $target['channel'];
                if ($channel === null) {
                    continue;
                }
                if ($channel['provider'] === 'youtube') {
                    if (!$channel['can_publish']) {
                        $errors['targets'] = sprintf('Reconnect %s and allow uploads before scheduling to it.', $channel['name']);
                    }
                    if ($input['title'] === '') {
                        $errors['title'] ??= 'Give your YouTube video a title.';
                    }
                    if ($media === null) {
                        $errors['media'] ??= 'Attach the video you want to publish.';
                    }
                }
                if ($channel['provider'] === 'linkedin') {
                    if (!$channel['can_publish']) {
                        $errors['targets'] = sprintf('Reconnect %s and allow atwebtech to create posts on LinkedIn.', $channel['name']);
                    }
                    if ($input['body'] === '') {
                        $errors['body'] ??= 'Write the post you want to publish on LinkedIn.';
                    }
                }
            }
        }

        $plannedUtc = null;
        if ($action === 'plan') {
            $tz = Workspace::timezone($user);
            $at = preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['date']) && preg_match('/^\d{2}:\d{2}$/', $input['time'])
                ? DateTimeImmutable::createFromFormat('!Y-m-d H:i', $input['date'] . ' ' . $input['time'], $tz)
                : false;
            if ($at === false || $at->format('Y-m-d H:i') !== $input['date'] . ' ' . $input['time']) {
                $errors['date'] = 'Choose a date and time for this post.';
            } elseif ($at <= new DateTimeImmutable('now', $tz)) {
                $errors['date'] = 'Choose a time in the future.';
            } elseif ($at > new DateTimeImmutable('+1 year', $tz)) {
                $errors['date'] = 'Choose a time within the next year.';
            } else {
                $plannedUtc = $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            }
        }
        if ($errors) {
            self::fail('composer', $back, 'Please check the highlighted fields.', $errors, $input + ['id' => $id, 'action' => $action]);
        }

        $ideaId = $input['idea'] > 0 && Workspace::idea($uid, $input['idea']) ? $input['idea'] : null;
        $status = $action === 'plan' ? 'planned' : 'draft';
        $now = Database::now();
        if (!$existing && self::count('posts', $uid) + count($targets) > self::MAX_POSTS) {
            self::fail('composer', $back, 'You have reached the limit of ' . self::MAX_POSTS . ' posts. Delete a few to add more.', [], $input);
        }
        $publishes = false;
        foreach ($targets as $target) {
            $isYoutube = $target['provider'] === 'youtube';
            $row = [
                'channel' => $target['provider'],
                'cid' => $target['channel'] ? (int) $target['channel']['id'] : null,
                'body' => $input['body'],
                'title' => $isYoutube && $input['title'] !== '' ? $input['title'] : null,
                'meta' => $isYoutube ? json_encode($meta) : null,
                'tags' => $input['tags'] !== '' ? $input['tags'] : null,
                'media' => $media ? (int) $media['id'] : null,
                'first' => $target['provider'] === 'linkedin' && $input['first_comment'] !== '' ? $input['first_comment'] : null,
                'status' => $status,
                'planned' => $plannedUtc,
                'now' => $now,
            ];
            $publishes = $publishes || ($status === 'planned' && $target['channel'] && $target['channel']['can_publish']);
            if ($existing) {
                $pdo->prepare('UPDATE posts SET channel = :channel, channel_id = :cid, body = :body, title = :title, meta = :meta, tags = :tags, media_id = :media, first_comment = :first, first_comment_error = NULL, status = :status, planned_at = :planned, error = NULL, attempts = 0, updated_at = :now WHERE id = :id AND user_id = :uid')
                    ->execute($row + ['id' => $id, 'uid' => $uid]);
                continue;
            }
            $pdo->prepare('INSERT INTO posts (user_id, channel, channel_id, body, title, meta, tags, media_id, first_comment, status, planned_at, idea_id, created_at, updated_at) VALUES (:uid, :channel, :cid, :body, :title, :meta, :tags, :media, :first, :status, :planned, :idea, :created, :now)')
                ->execute($row + ['uid' => $uid, 'idea' => $ideaId, 'created' => $now]);
        }
        $many = count($targets) > 1 ? count($targets) . ' posts' : 'Post';
        $message = match (true) {
            $status === 'draft' => count($targets) > 1 ? count($targets) . ' drafts saved.' : 'Saved to drafts.',
            $publishes => $many . ' scheduled. atwebtech publishes at the time you picked.',
            default => $many . ' added to your queue as a plan. Channels that aren’t connected aren’t published automatically.',
        };
        $location = $status === 'planned' ? '/app/publish' : '/app/publish?tab=drafts';
        self::done(str_starts_with($back, '/app/publish') ? $location : $back, $message, ['status' => $status]);
    }

    public static function template(): never
    {
        $uid = (int) Auth::member()['id'];
        $back = '/app/create?tab=templates&set=personal';
        $action = (string) ($_POST['action'] ?? 'create');
        $pdo = Database::connection();

        if ($action === 'delete') {
            $pdo->prepare('DELETE FROM post_templates WHERE id = :id AND user_id = :uid')->execute(['id' => (int) ($_POST['id'] ?? 0), 'uid' => $uid]);
            self::done($back, 'Template deleted.');
        }

        if ($action === 'idea') {
            $template = self::findTemplate($uid, (string) ($_POST['key'] ?? ''));
            if ($template === null) {
                self::fail('template', '/app/create?tab=templates', 'That template no longer exists.');
            }
            if (self::count('ideas', $uid) >= self::MAX_IDEAS) {
                self::fail('template', '/app/create?tab=templates', 'You have reached the limit of ' . self::MAX_IDEAS . ' ideas.');
            }
            $now = Database::now();
            $pdo->prepare('INSERT INTO ideas (user_id, group_id, title, body, tags, position, created_at, updated_at) VALUES (:uid, NULL, :title, :body, :tags, :pos, :created, :updated)')
                ->execute(['uid' => $uid, 'title' => mb_substr($template['title'], 0, 140), 'body' => $template['prompt'], 'tags' => 'template', 'pos' => self::topPosition($uid, null), 'created' => $now, 'updated' => $now]);
            self::done('/app/create', 'Saved to Unassigned on your ideas board.');
        }

        $input = [
            'title' => self::clean((string) ($_POST['title'] ?? ''), false),
            'prompt' => self::clean((string) ($_POST['prompt'] ?? ''), true),
        ];
        $errors = [];
        if ($input['title'] === '' || mb_strlen($input['title']) > 140) {
            $errors['title'] = 'Titles need 1 to 140 characters.';
        }
        if ($input['prompt'] === '' || mb_strlen($input['prompt']) > 2000) {
            $errors['prompt'] = 'Write a prompt of up to 2,000 characters.';
        }
        if (!$errors && self::count('post_templates', $uid) >= self::MAX_TEMPLATES) {
            $errors['title'] = 'You can save up to ' . self::MAX_TEMPLATES . ' templates.';
        }
        if ($errors) {
            self::fail('template', $back, 'Please check the highlighted fields.', $errors, $input);
        }
        $pdo->prepare('INSERT INTO post_templates (user_id, title, prompt, created_at) VALUES (:uid, :title, :prompt, :created)')
            ->execute(['uid' => $uid, 'title' => $input['title'], 'prompt' => $input['prompt'], 'created' => Database::now()]);
        self::done($back, 'Template saved.');
    }

    public static function timezone(): never
    {
        $uid = (int) Auth::member()['id'];
        $name = (string) ($_POST['timezone'] ?? '');
        if (!in_array($name, DateTimeZone::listIdentifiers(), true)) {
            self::fail('timezone', '/app/publish', 'Choose a valid time zone.');
        }
        Database::connection()->prepare('UPDATE users SET timezone = :tz, updated_at = :now WHERE id = :id')->execute(['tz' => $name, 'now' => Database::now(), 'id' => $uid]);
        self::done(self::back('/app/publish'), 'Time zone updated.');
    }

    /** Discover template by key, or a personal template as `p{id}`. */
    public static function findTemplate(int $uid, string $key): ?array
    {
        $items = data('templates')['items'];
        if (isset($items[$key])) {
            return ['title' => $items[$key][2], 'prompt' => $items[$key][3]];
        }
        if (preg_match('/^p(\d+)$/', $key, $m)) {
            $stmt = Database::connection()->prepare('SELECT title, prompt FROM post_templates WHERE id = :id AND user_id = :uid');
            $stmt->execute(['id' => (int) $m[1], 'uid' => $uid]);
            return $stmt->fetch() ?: null;
        }
        return null;
    }

    private static function clean(string $value, bool $multiline): string
    {
        $value = str_replace("\r\n", "\n", $value);
        $value = preg_replace($multiline ? '/[^\P{C}\n\t]/u' : '/\p{C}/u', '', $value) ?? '';
        return trim($multiline ? $value : preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private static function tags(string $raw): string
    {
        $tags = [];
        foreach (explode(',', $raw) as $tag) {
            $tag = mb_strtolower(mb_substr(self::clean($tag, false), 0, 24));
            if ($tag !== '') {
                $tags[$tag] = true;
            }
        }
        return implode(',', array_slice(array_keys($tags), 0, 6));
    }

    private static function topPosition(int $uid, ?int $groupId): int
    {
        $stmt = Database::connection()->prepare('SELECT COALESCE(MIN(position), 0) - 1 FROM ideas WHERE user_id = :uid AND ' . ($groupId ? 'group_id = :gid' : 'group_id IS NULL'));
        $stmt->execute(['uid' => $uid] + ($groupId ? ['gid' => $groupId] : []));
        return (int) $stmt->fetchColumn();
    }

    private static function count(string $table, int $uid): int
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = :uid");
        $stmt->execute(['uid' => $uid]);
        return (int) $stmt->fetchColumn();
    }

    private static function back(string $fallback): string
    {
        $path = safe_return_path($_POST['return'] ?? null, $fallback);
        return str_starts_with($path, '/app') ? $path : $fallback;
    }

    private static function done(string $location, string $message, array $extra = []): never
    {
        if (wants_json()) {
            json_response(['status' => 'success', 'message' => $message] + $extra);
        }
        flash('notice', $message);
        redirect($location);
    }

    private static function fail(string $form, string $location, string $message, array $errors = [], array $old = []): never
    {
        if (wants_json()) {
            json_response(['status' => 'error', 'message' => $message, 'errors' => $errors], 422);
        }
        flash('form', ['form' => $form, 'status' => 'error', 'message' => $message, 'errors' => $errors, 'old' => $old]);
        redirect($location);
    }
}
