<?php

declare(strict_types=1);

/**
 * Admin CRM. A contact is one email address, gathered from waitlist signups, contact-form messages
 * and accounts. The stage (Lead → Customer) is worked out from what the person has done; the sales
 * status (New → Won/Lost) is set by the team. Notes and follow-ups belong to the contact.
 */
final class Crm
{
    public const STAGES = [
        'lead' => 'Lead',
        'signed_up' => 'Signed up',
        'activated' => 'Activated',
        'active' => 'Active',
        'customer' => 'Customer',
        'churned' => 'Churned',
    ];
    public const STATUSES = [
        'new' => 'New',
        'contacted' => 'Contacted',
        'qualified' => 'Qualified',
        'won' => 'Won',
        'lost' => 'Lost',
    ];
    public const NOTE_KINDS = ['note' => 'Note', 'call' => 'Call', 'email' => 'Email', 'meeting' => 'Meeting'];
    private const ACTIVE_DAYS = 7;

    /** Adds a CRM row for every email seen anywhere, keeping the earliest date it was seen. */
    public static function sync(): void
    {
        $pdo = Database::connection();
        $seen = [];
        $add = static function (string $email, ?string $name, string $at) use (&$seen): void {
            $email = strtolower(trim($email));
            if ($email === '' || !str_contains($email, '@')) {
                return;
            }
            if (!isset($seen[$email])) {
                $seen[$email] = ['name' => $name, 'at' => $at];
                return;
            }
            if ($at < $seen[$email]['at']) {
                $seen[$email]['at'] = $at;
            }
            if ($name && !$seen[$email]['name']) {
                $seen[$email]['name'] = $name;
            }
        };
        foreach ($pdo->query('SELECT email, display_name, created_at FROM users')->fetchAll() as $r) {
            $add((string) $r['email'], (string) $r['display_name'], (string) $r['created_at']);
        }
        foreach ($pdo->query('SELECT email, created_at FROM waitlist_signups')->fetchAll() as $r) {
            $add((string) $r['email'], null, (string) $r['created_at']);
        }
        foreach ($pdo->query('SELECT email, name, created_at FROM enquiries')->fetchAll() as $r) {
            $add((string) $r['email'], (string) $r['name'], (string) $r['created_at']);
        }
        $existing = [];
        foreach ($pdo->query('SELECT id, email, name, first_seen_at FROM crm_contacts')->fetchAll() as $r) {
            $existing[strtolower((string) $r['email'])] = $r;
        }
        $insert = $pdo->prepare('INSERT INTO crm_contacts (email, name, status, first_seen_at, created_at, updated_at) VALUES (:email, :name, :status, :seen, :now, :now2)');
        $update = $pdo->prepare('UPDATE crm_contacts SET name = COALESCE(name, :name), first_seen_at = :seen WHERE id = :id');
        $now = Database::now();
        foreach ($seen as $email => $info) {
            $row = $existing[$email] ?? null;
            if ($row === null) {
                $insert->execute(['email' => $email, 'name' => $info['name'] ? mb_substr($info['name'], 0, 120) : null, 'status' => 'new', 'seen' => $info['at'], 'now' => $now, 'now2' => $now]);
            } elseif ($info['at'] < (string) $row['first_seen_at'] || ($info['name'] && !$row['name'])) {
                $update->execute(['name' => $info['name'] ? mb_substr($info['name'], 0, 120) : null, 'seen' => min($info['at'], (string) $row['first_seen_at']), 'id' => $row['id']]);
            }
        }
    }

    /**
     * Every contact with what we know about them, keyed by contact id.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function contacts(): array
    {
        self::sync();
        $pdo = Database::connection();
        $users = [];
        foreach ($pdo->query('SELECT id, email, display_name, created_at, last_login_at, utm_source, utm_medium, utm_campaign, click_kind FROM users')->fetchAll() as $u) {
            $users[strtolower((string) $u['email'])] = $u;
        }
        $wait = [];
        foreach ($pdo->query('SELECT email, source, plan, utm_source, utm_medium, utm_campaign, click_kind, created_at FROM waitlist_signups ORDER BY created_at')->fetchAll() as $w) {
            $wait[strtolower((string) $w['email'])] ??= $w;
        }
        $enq = [];
        foreach ($pdo->query('SELECT email, COUNT(*) AS n, MAX(created_at) AS last_at FROM enquiries GROUP BY email')->fetchAll() as $q) {
            $enq[strtolower((string) $q['email'])] = $q;
        }
        $channels = [];
        foreach ($pdo->query('SELECT user_id, COUNT(*) AS n, MIN(created_at) AS first_at FROM channels GROUP BY user_id')->fetchAll() as $c) {
            $channels[(int) $c['user_id']] = $c;
        }
        $posts = [];
        foreach ($pdo->query("SELECT user_id, COUNT(*) AS total, SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent, MAX(sent_at) AS last_sent FROM posts GROUP BY user_id")->fetchAll() as $p) {
            $posts[(int) $p['user_id']] = $p;
        }
        $subs = [];
        foreach ($pdo->query('SELECT * FROM subscriptions ORDER BY updated_at')->fetchAll() as $s) {
            $uid = (int) $s['user_id'];
            if (!isset($subs[$uid]) || in_array($s['status'], Billing::LIVE_STATUSES, true)) {
                $subs[$uid] = $s;
            }
        }
        $open = [];
        foreach ($pdo->query('SELECT contact_id, COUNT(*) AS n, MIN(due_at) AS next_due FROM crm_followups WHERE done_at IS NULL GROUP BY contact_id')->fetchAll() as $f) {
            $open[(int) $f['contact_id']] = $f;
        }

        $out = [];
        $activeSince = gmdate('Y-m-d H:i:s', time() - 86400 * self::ACTIVE_DAYS);
        foreach ($pdo->query('SELECT * FROM crm_contacts')->fetchAll() as $c) {
            $email = strtolower((string) $c['email']);
            $user = $users[$email] ?? null;
            $uid = $user ? (int) $user['id'] : 0;
            $sub = $uid ? ($subs[$uid] ?? null) : null;
            $ch = $uid ? ($channels[$uid] ?? null) : null;
            $po = $uid ? ($posts[$uid] ?? null) : null;
            $live = $sub && in_array($sub['status'], Billing::LIVE_STATUSES, true);
            $recent = $user && (($user['last_login_at'] ?? '') >= $activeSince || ($po['last_sent'] ?? '') >= $activeSince);
            $stage = match (true) {
                $user === null => 'lead',
                $live => 'customer',
                $sub !== null => 'churned',
                $ch !== null && $recent => 'active',
                $ch !== null => 'activated',
                default => 'signed_up',
            };
            $attr = $user && ($user['utm_source'] || $user['click_kind']) ? $user : ($wait[$email] ?? $user ?? []);
            $out[(int) $c['id']] = $c + [
                'user' => $user,
                'waitlist' => $wait[$email] ?? null,
                'enquiries' => (int) ($enq[$email]['n'] ?? 0),
                'stage' => $stage,
                'source' => self::source($attr),
                'campaign' => (string) ($attr['utm_campaign'] ?? ''),
                'channels' => (int) ($ch['n'] ?? 0),
                'activated_at' => $ch['first_at'] ?? null,
                'posts' => (int) ($po['total'] ?? 0),
                'sent' => (int) ($po['sent'] ?? 0),
                'subscription' => $sub,
                'mrr' => $live ? self::mrr($sub) : 0.0,
                'last_login_at' => $user['last_login_at'] ?? null,
                'open_followups' => (int) ($open[(int) $c['id']]['n'] ?? 0),
                'next_due' => $open[(int) $c['id']]['next_due'] ?? null,
            ];
        }
        return $out;
    }

    /** Where a person came from, as a short label. Meta ads are told apart from organic Facebook/Instagram. */
    public static function source(array $a): string
    {
        $src = strtolower(trim((string) ($a['utm_source'] ?? '')));
        $medium = strtolower(trim((string) ($a['utm_medium'] ?? '')));
        $click = strtolower((string) ($a['click_kind'] ?? ''));
        $paid = $medium !== '' && preg_match('/cpc|ppc|paid|ads?|cpm|social[-_ ]?paid/', $medium);
        $meta = in_array($src, ['facebook', 'fb', 'instagram', 'ig', 'meta', 'messenger', 'audience_network', 'an'], true);
        return match (true) {
            $click === 'fbclid' && ($paid || $meta || $src === '') => 'Meta ads',
            $meta && $paid => 'Meta ads',
            $meta, $click === 'fbclid' => 'Facebook / Instagram',
            $click === 'gclid', $src === 'google' && $paid => 'Google Ads',
            $click === 'msclkid' => 'Microsoft Ads',
            $src === 'linkinbio' => 'Link in Bio pages',
            $src !== '' => ucfirst($src) . ($paid ? ' (paid)' : ''),
            default => 'Direct / organic',
        };
    }

    public static function mrr(array $sub): float
    {
        $plan = (string) $sub['plan'];
        if (!isset(data('pricing')['plans'][$plan])) {
            return 0.0;
        }
        $monthly = Pricing::monthlyTotal($plan, (int) $sub['channels']);
        return $sub['billing'] === 'yearly' ? $monthly * data('pricing')['yearly_months'] / 12 : $monthly;
    }

    /** Dashboard numbers for the last $days days. */
    public static function dashboard(int $days): array
    {
        $all = self::contacts();
        $since = gmdate('Y-m-d H:i:s', time() - 86400 * $days);
        $prevSince = gmdate('Y-m-d H:i:s', time() - 86400 * $days * 2);
        $in = static fn (?string $at, string $from, ?string $to = null): bool => $at !== null && $at >= $from && ($to === null || $at < $to);
        $count = static fn (callable $fn): int => count(array_filter($all, $fn));

        $cohort = array_filter($all, static fn ($c) => $in((string) $c['first_seen_at'], $since));
        $reached = static function (array $set, array $stages): int {
            return count(array_filter($set, static fn ($c) => in_array($c['stage'], $stages, true)));
        };
        $signedStages = ['signed_up', 'activated', 'active', 'customer', 'churned'];
        $activatedStages = ['activated', 'active', 'customer', 'churned'];

        $kpi = [
            'leads' => [count($cohort), $count(fn ($c) => $in((string) $c['first_seen_at'], $prevSince, $since))],
            'signups' => [$count(fn ($c) => $in($c['user']['created_at'] ?? null, $since)), $count(fn ($c) => $in($c['user']['created_at'] ?? null, $prevSince, $since))],
            'activated' => [$count(fn ($c) => $in($c['activated_at'], $since)), $count(fn ($c) => $in($c['activated_at'], $prevSince, $since))],
            'customers' => [$count(fn ($c) => $c['stage'] === 'customer'), null],
            'active' => [$count(fn ($c) => $c['stage'] === 'active' || ($c['stage'] === 'customer' && ($c['last_login_at'] ?? '') >= gmdate('Y-m-d H:i:s', time() - 86400 * self::ACTIVE_DAYS))), null],
            'mrr' => [array_sum(array_column($all, 'mrr')), null],
        ];

        $sources = [];
        foreach ($cohort as $c) {
            $key = $c['source'];
            $sources[$key] ??= ['leads' => 0, 'signups' => 0, 'activated' => 0, 'customers' => 0, 'campaigns' => []];
            $sources[$key]['leads']++;
            $sources[$key]['signups'] += in_array($c['stage'], $signedStages, true) ? 1 : 0;
            $sources[$key]['activated'] += in_array($c['stage'], $activatedStages, true) ? 1 : 0;
            $sources[$key]['customers'] += $c['stage'] === 'customer' ? 1 : 0;
            if ($c['campaign'] !== '') {
                $sources[$key]['campaigns'][$c['campaign']] = ($sources[$key]['campaigns'][$c['campaign']] ?? 0) + 1;
            }
        }
        uasort($sources, static fn ($a, $b) => $b['leads'] <=> $a['leads']);

        $daily = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $daily[gmdate('Y-m-d', time() - 86400 * $i)] = ['leads' => 0, 'signups' => 0];
        }
        foreach ($all as $c) {
            $d = substr((string) $c['first_seen_at'], 0, 10);
            if (isset($daily[$d])) {
                $daily[$d]['leads']++;
            }
            $s = substr((string) ($c['user']['created_at'] ?? ''), 0, 10);
            if (isset($daily[$s])) {
                $daily[$s]['signups']++;
            }
        }

        $byStage = array_fill_keys(array_keys(self::STAGES), 0);
        foreach ($all as $c) {
            $byStage[$c['stage']]++;
        }

        return [
            'kpi' => $kpi,
            'funnel' => [
                ['Leads', count($cohort)],
                ['Signed up', $reached($cohort, $signedStages)],
                ['Activated', $reached($cohort, $activatedStages)],
                ['Customers', $reached($cohort, ['customer'])],
            ],
            'sources' => $sources,
            'daily' => $daily,
            'stages' => $byStage,
            'total' => count($all),
            'due' => self::followups('due', 8),
            'recent' => array_slice(self::sortRecent($all), 0, 8),
        ];
    }

    /** Contacts list with filters: stage, status, source, q (search), sort. */
    public static function filter(array $q): array
    {
        $rows = self::contacts();
        $search = strtolower(trim((string) ($q['q'] ?? '')));
        $rows = array_filter($rows, static function (array $c) use ($q, $search): bool {
            if (($q['stage'] ?? '') !== '' && $c['stage'] !== $q['stage']) {
                return false;
            }
            if (($q['status'] ?? '') !== '' && $c['status'] !== $q['status']) {
                return false;
            }
            if (($q['source'] ?? '') !== '' && $c['source'] !== $q['source']) {
                return false;
            }
            if (($q['followup'] ?? '') === '1' && $c['open_followups'] === 0) {
                return false;
            }
            return $search === '' || str_contains(strtolower($c['email'] . ' ' . ($c['name'] ?? '') . ' ' . $c['campaign'] . ' ' . ($c['tags'] ?? '')), $search);
        });
        return self::sortRecent($rows);
    }

    /** @return list<array<string, mixed>> */
    private static function sortRecent(array $rows): array
    {
        usort($rows, static fn ($a, $b) => strcmp((string) max($b['first_seen_at'], $b['user']['created_at'] ?? '', $b['last_login_at'] ?? ''), (string) max($a['first_seen_at'], $a['user']['created_at'] ?? '', $a['last_login_at'] ?? '')));
        return array_values($rows);
    }

    public static function contact(int $id): ?array
    {
        $all = self::contacts();
        return $all[$id] ?? null;
    }

    /** Everything that happened with a contact, newest first. */
    public static function timeline(array $c): array
    {
        $pdo = Database::connection();
        $events = [];
        $email = strtolower((string) $c['email']);
        $stmt = $pdo->prepare('SELECT * FROM waitlist_signups WHERE LOWER(email) = :e');
        $stmt->execute(['e' => $email]);
        foreach ($stmt->fetchAll() as $w) {
            $events[] = [$w['created_at'], 'envelope-simple', 'Joined the waitlist' . ($w['plan'] ? ' (interested in ' . Admin::plan((string) $w['plan']) . ')' : ''), 'From ' . Admin::source((string) $w['source']) . ' · ' . self::source($w)];
        }
        $stmt = $pdo->prepare('SELECT * FROM enquiries WHERE LOWER(email) = :e');
        $stmt->execute(['e' => $email]);
        foreach ($stmt->fetchAll() as $q) {
            $events[] = [$q['created_at'], 'chat-circle-text', 'Sent a message: ' . Admin::topic((string) $q['topic']), mb_strimwidth((string) $q['message'], 0, 400, '…')];
        }
        if ($c['user']) {
            $uid = (int) $c['user']['id'];
            $events[] = [$c['user']['created_at'], 'user-plus', 'Created an account', 'Source: ' . self::source($c['user'])];
            if ($c['user']['last_login_at']) {
                $events[] = [$c['user']['last_login_at'], 'sign-out', 'Last signed in', ''];
            }
            $stmt = $pdo->prepare('SELECT provider, name, created_at FROM channels WHERE user_id = :u');
            $stmt->execute(['u' => $uid]);
            foreach ($stmt->fetchAll() as $ch) {
                $events[] = [$ch['created_at'], 'share-network', 'Connected ' . ucfirst((string) $ch['provider']) . ': ' . $ch['name'], ''];
            }
            $stmt = $pdo->prepare("SELECT MIN(created_at) AS first_at, COUNT(*) AS n FROM posts WHERE user_id = :u");
            $stmt->execute(['u' => $uid]);
            $p = $stmt->fetch();
            if ($p && (int) $p['n'] > 0) {
                $events[] = [$p['first_at'], 'pencil-simple', 'Created their first post', $c['posts'] . ' posts in total, ' . $c['sent'] . ' published'];
            }
            $stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE user_id = :u');
            $stmt->execute(['u' => $uid]);
            foreach ($stmt->fetchAll() as $s) {
                $events[] = [$s['created_at'], 'tag', 'Subscribed to ' . Admin::plan((string) $s['plan']) . ' (' . $s['channels'] . ' channels, ' . $s['billing'] . ')', 'Status now: ' . $s['status']];
            }
        }
        $stmt = $pdo->prepare('SELECT * FROM crm_notes WHERE contact_id = :id');
        $stmt->execute(['id' => $c['id']]);
        foreach ($stmt->fetchAll() as $n) {
            $events[] = [$n['created_at'], 'note-pencil', (self::NOTE_KINDS[$n['kind']] ?? 'Note') . ' by ' . $n['author'], (string) $n['body'], 'note'];
        }
        usort($events, static fn ($a, $b) => strcmp((string) $b[0], (string) $a[0]));
        return $events;
    }

    /** @param string $which due (overdue + today) | upcoming | done | open */
    public static function followups(string $which, int $limit = 200, ?int $contactId = null): array
    {
        $endToday = gmdate('Y-m-d 23:59:59');
        $where = match ($which) {
            'due' => 'f.done_at IS NULL AND f.due_at <= :edge',
            'upcoming' => 'f.done_at IS NULL AND f.due_at > :edge',
            'done' => 'f.done_at IS NOT NULL',
            default => 'f.done_at IS NULL',
        };
        $params = in_array($which, ['due', 'upcoming'], true) ? ['edge' => $endToday] : [];
        if ($contactId !== null) {
            $where .= ' AND f.contact_id = :cid';
            $params['cid'] = $contactId;
        }
        $order = $which === 'done' ? 'f.done_at DESC' : 'f.due_at ASC';
        $stmt = Database::connection()->prepare("SELECT f.*, c.email, c.name FROM crm_followups f JOIN crm_contacts c ON c.id = f.contact_id WHERE $where ORDER BY $order LIMIT " . max(1, $limit));
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** POST /admin/crm/action */
    public static function action(): never
    {
        $admin = Auth::requireAdmin();
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            self::back('/admin', 'Your session expired. Please try again.');
        }
        $by = (string) ($admin['email'] ?: 'admin');
        $pdo = Database::connection();
        $now = Database::now();
        $id = (int) ($_POST['contact'] ?? 0);
        $back = safe_return_path($_POST['return'] ?? null, '/admin/crm/contacts/' . $id);
        if (!str_starts_with($back, '/admin')) {
            $back = '/admin';
        }
        $exists = static function (int $id) use ($pdo): bool {
            $s = $pdo->prepare('SELECT 1 FROM crm_contacts WHERE id = :id');
            $s->execute(['id' => $id]);
            return (bool) $s->fetchColumn();
        };
        switch ((string) ($_POST['action'] ?? '')) {
            case 'status':
                $status = (string) ($_POST['status'] ?? '');
                if ($exists($id) && isset(self::STATUSES[$status])) {
                    $pdo->prepare('UPDATE crm_contacts SET status = :s, updated_at = :now WHERE id = :id')->execute(['s' => $status, 'now' => $now, 'id' => $id]);
                    $pdo->prepare('INSERT INTO crm_notes (contact_id, kind, body, author, created_at) VALUES (:id, :kind, :body, :by, :now)')
                        ->execute(['id' => $id, 'kind' => 'note', 'body' => 'Status changed to ' . self::STATUSES[$status] . '.', 'by' => $by, 'now' => $now]);
                }
                self::back($back, 'Status updated.');
            case 'tags':
                if ($exists($id)) {
                    $tags = implode(', ', array_slice(array_unique(array_filter(array_map(static fn ($t) => mb_substr(trim($t), 0, 30), explode(',', (string) ($_POST['tags'] ?? ''))))), 0, 10));
                    $pdo->prepare('UPDATE crm_contacts SET tags = :t, updated_at = :now WHERE id = :id')->execute(['t' => $tags !== '' ? $tags : null, 'now' => $now, 'id' => $id]);
                }
                self::back($back, 'Tags saved.');
            case 'note':
                $body = trim((string) ($_POST['body'] ?? ''));
                $kind = (string) ($_POST['kind'] ?? 'note');
                if ($exists($id) && $body !== '') {
                    $kind = isset(self::NOTE_KINDS[$kind]) ? $kind : 'note';
                    $pdo->prepare('INSERT INTO crm_notes (contact_id, kind, body, author, created_at) VALUES (:id, :kind, :body, :by, :now)')
                        ->execute(['id' => $id, 'kind' => $kind, 'body' => mb_substr($body, 0, 5000), 'by' => $by, 'now' => $now]);
                    if ($kind !== 'note') {
                        $pdo->prepare("UPDATE crm_contacts SET last_contacted_at = :now, status = CASE WHEN status = 'new' THEN 'contacted' ELSE status END, updated_at = :now2 WHERE id = :id")
                            ->execute(['now' => $now, 'now2' => $now, 'id' => $id]);
                    }
                }
                self::back($back, $body === '' ? 'Write something first.' : 'Saved to the timeline.');
            case 'followup':
                $title = trim((string) ($_POST['title'] ?? ''));
                $tzName = (string) ($_POST['tz'] ?? 'UTC');
                $tz = new DateTimeZone(in_array($tzName, DateTimeZone::listIdentifiers(), true) ? $tzName : 'UTC');
                $due = DateTimeImmutable::createFromFormat('!Y-m-d H:i', trim((string) ($_POST['date'] ?? '')) . ' ' . (trim((string) ($_POST['time'] ?? '')) ?: '09:00'), $tz);
                if (!$exists($id) || $title === '' || $due === false) {
                    self::back($back, 'Add a title and a date for the follow-up.');
                }
                $pdo->prepare('INSERT INTO crm_followups (contact_id, title, due_at, author, created_at) VALUES (:id, :title, :due, :by, :now)')
                    ->execute(['id' => $id, 'title' => mb_substr($title, 0, 200), 'due' => $due->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'by' => $by, 'now' => $now]);
                self::back($back, 'Follow-up scheduled.');
            case 'done':
                $f = (int) ($_POST['followup'] ?? 0);
                $pdo->prepare('UPDATE crm_followups SET done_at = :now WHERE id = :id AND done_at IS NULL')->execute(['now' => $now, 'id' => $f]);
                $stmt = $pdo->prepare('SELECT contact_id, title FROM crm_followups WHERE id = :id');
                $stmt->execute(['id' => $f]);
                if ($row = $stmt->fetch()) {
                    $pdo->prepare('INSERT INTO crm_notes (contact_id, kind, body, author, created_at) VALUES (:id, :kind, :body, :by, :now)')
                        ->execute(['id' => $row['contact_id'], 'kind' => 'note', 'body' => 'Follow-up done: ' . $row['title'], 'by' => $by, 'now' => $now]);
                }
                self::back($back, 'Follow-up marked done.');
        }
        self::back($back, 'Nothing changed.');
    }

    /** Contacts as CSV, for ads custom audiences or a spreadsheet. */
    public static function export(array $q): never
    {
        Auth::requireAdmin();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="atwebtech-contacts-' . gmdate('Y-m-d') . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['email', 'name', 'stage', 'status', 'source', 'campaign', 'first_seen_utc', 'signed_up_utc', 'last_login_utc', 'channels', 'posts', 'plan', 'mrr_usd', 'tags'], ',', '"', '');
        foreach (self::filter($q) as $c) {
            fputcsv($out, array_map(static fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, [
                $c['email'], $c['name'], self::STAGES[$c['stage']], self::STATUSES[$c['status']] ?? $c['status'], $c['source'], $c['campaign'],
                $c['first_seen_at'], $c['user']['created_at'] ?? '', $c['last_login_at'] ?? '', $c['channels'], $c['posts'],
                $c['subscription']['plan'] ?? '', number_format($c['mrr'], 2, '.', ''), $c['tags'] ?? '',
            ]), ',', '"', '');
        }
        fclose($out);
        exit;
    }

    /** Time zone for showing CRM dates: the signed-in admin's workspace zone, or UTC. */
    public static function tz(): DateTimeZone
    {
        return Workspace::timezone(Auth::user() ?? []);
    }

    public static function local(?string $utc, string $format = 'j M Y, g:i A'): string
    {
        return $utc ? (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(self::tz())->format($format) : '';
    }

    private static function back(string $location, string $message): never
    {
        flash('notice', $message);
        redirect($location);
    }
}
