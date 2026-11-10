<?php

declare(strict_types=1);

/**
 * Branded reports (paid plans): the user's logo, name and color on the printable Insights report,
 * a private share link for clients (/r/{token}), and weekly or monthly email delivery from bin/publish.php.
 */
final class ClientReports
{
    public const FREQUENCIES = ['off' => 'Don’t email', 'weekly' => 'Every Monday (last 7 days)', 'monthly' => 'On the 1st (last month)'];
    public const MAX_RECIPIENTS = 5;
    private const LOGO_MAX = 512 * 1024;
    private const LOGO_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    private const SEND_HOUR = 8;

    /** @return array{brand_name:?string, brand_color:string, logo_file:?string, white_label:bool, frequency:string, recipients:array, share_token:?string, last_sent_at:?string} */
    public static function settings(int $uid): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM report_settings WHERE user_id = :uid');
        $stmt->execute(['uid' => $uid]);
        $row = $stmt->fetch() ?: [];
        return [
            'brand_name' => ($row['brand_name'] ?? '') !== '' ? (string) $row['brand_name'] : null,
            'brand_color' => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($row['brand_color'] ?? '')) ? (string) $row['brand_color'] : '#16A34A',
            'logo_file' => ($row['logo_file'] ?? '') !== '' ? (string) $row['logo_file'] : null,
            'white_label' => !empty($row['white_label']),
            'frequency' => isset(self::FREQUENCIES[$row['frequency'] ?? '']) ? (string) $row['frequency'] : 'off',
            'recipients' => array_values(array_filter(explode(',', (string) ($row['recipients'] ?? '')))),
            'share_token' => ($row['share_token'] ?? '') !== '' ? (string) $row['share_token'] : null,
            'last_sent_at' => $row['last_sent_at'] ?? null,
        ];
    }

    /** Branding for the report view. Free plans get the plain atwebtech report. */
    public static function brand(int $uid): array
    {
        $s = self::settings($uid);
        $paid = Channels::allowance($uid)['paid'];
        return [
            'name' => $paid ? $s['brand_name'] : null,
            'color' => $paid ? $s['brand_color'] : '#16A34A',
            'logo' => $paid && $s['logo_file'] && $s['share_token'] ? '/r/' . $s['share_token'] . '/logo' : null,
            'white_label' => $paid && $s['white_label'],
        ];
    }

    /** POST /app/account/reports */
    public static function save(): never
    {
        $user = Auth::member();
        $uid = (int) $user['id'];
        $back = '/app/settings#reports';
        if (!Channels::allowance($uid)['paid']) {
            Toolkit::done($back, 'Branded reports are part of Essentials and Team. See Plan & billing to upgrade.');
        }
        $s = self::settings($uid);
        $action = (string) ($_POST['action'] ?? 'save');

        if ($action === 'new_link') {
            self::store($uid, ['share_token' => bin2hex(random_bytes(16))] + self::row($s));
            Toolkit::done($back, 'New report link created. The old link no longer works.');
        }
        if ($action === 'remove_logo') {
            if ($s['logo_file']) {
                @unlink(self::dir() . '/' . $s['logo_file']);
            }
            self::store($uid, ['logo_file' => null] + self::row($s));
            Toolkit::done($back, 'Logo removed.');
        }
        if ($action === 'send_now') {
            if (!$s['recipients']) {
                Toolkit::done($back, 'Add at least one email address first.');
            }
            if (!config('mail.enabled')) {
                Toolkit::done($back, 'Email isn’t set up on this server yet, so reports can’t be sent.');
            }
            if (RateLimiter::tooManyAttempts('report-send', 'user:' . $uid, 3, 60)) {
                Toolkit::done($back, 'You’ve sent a few reports already. Try again in an hour.');
            }
            RateLimiter::hit('report-send', 'user:' . $uid);
            self::send($user, $s['frequency'] === 'monthly' ? 'last-month' : '7d');
            Toolkit::done($back, 'Report sent to ' . implode(', ', $s['recipients']) . '.');
        }

        $name = trim((string) ($_POST['brand_name'] ?? ''));
        $color = (string) ($_POST['brand_color'] ?? '');
        $frequency = (string) ($_POST['frequency'] ?? 'off');
        $emails = [];
        foreach (preg_split('/[\s,;]+/', (string) ($_POST['recipients'] ?? '')) ?: [] as $email) {
            if ($email === '') {
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 110) {
                Toolkit::done($back, '“' . mb_substr($email, 0, 60) . '” isn’t a valid email address. Nothing was saved.');
            }
            $emails[strtolower($email)] = strtolower($email);
        }
        if (count($emails) > self::MAX_RECIPIENTS) {
            Toolkit::done($back, sprintf('Add up to %d email addresses. Nothing was saved.', self::MAX_RECIPIENTS));
        }
        if ($frequency !== 'off' && !$emails) {
            Toolkit::done($back, 'Add an email address to send scheduled reports to. Nothing was saved.');
        }
        $row = [
            'brand_name' => $name !== '' ? mb_substr($name, 0, 80) : null,
            'brand_color' => preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? strtoupper($color) : '#16A34A',
            'logo_file' => $s['logo_file'],
            'white_label' => !empty($_POST['white_label']) ? 1 : 0,
            'frequency' => isset(self::FREQUENCIES[$frequency]) ? $frequency : 'off',
            'recipients' => $emails ? implode(',', $emails) : null,
            'share_token' => $s['share_token'] ?? bin2hex(random_bytes(16)),
        ];
        $upload = $_FILES['logo'] ?? null;
        if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $row['logo_file'] = self::storeLogo($uid, $upload, $back, $s['logo_file']);
        }
        self::store($uid, $row);
        Toolkit::done($back, 'Report branding saved.');
    }

    /** GET /r/{token}: the branded report for clients, without signing in. */
    public static function shared(string $token): never
    {
        $user = self::owner($token);
        $range = in_array($_GET['range'] ?? '', ['7d', '30d', 'mtd', 'last-month'], true) ? (string) $_GET['range'] : '30d';
        header('X-Robots-Tag: noindex, nofollow');
        self::render($user, $range, true);
    }

    /** GET /r/{token}/logo */
    public static function logo(string $token): never
    {
        $user = self::owner($token);
        $file = self::settings((int) $user['id'])['logo_file'];
        $path = $file ? self::dir() . '/' . basename($file) : '';
        if ($path === '' || !is_file($path)) {
            not_found();
        }
        $type = array_search(pathinfo($path, PATHINFO_EXTENSION), self::LOGO_TYPES, true) ?: 'application/octet-stream';
        header('Content-Type: ' . $type);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    /** Print view of Insights with the user's branding. Exits. */
    public static function render(array $user, string $range, bool $shared, ?int $channelId = null): never
    {
        $uid = (int) $user['id'];
        $in = Workspace::insights($user, $range, $channelId);
        $tz = Workspace::timezone($user);
        $utc = new DateTimeZone('UTC');
        $brand = self::brand($uid);
        $file = ($brand['name'] ? preg_replace('/[^a-z0-9]+/', '-', strtolower($brand['name'])) : 'atwebtech') . '-report-' . $in['from']->format('Y-m-d') . '-to-' . $in['to']->format('Y-m-d');
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, private');
        View::partial('reports/print', [
            'user' => $user, 'in' => $in, 'tz' => $tz, 'file' => $file, 'brand' => $brand, 'shared' => $shared,
            'names' => array_column(integrations(), 'name', 'slug'),
            'accounts' => array_column(Channels::forUser($uid), null, 'id'),
            'campaigns' => Reports::campaigns($uid, $in['from']->setTimezone($utc)->format('Y-m-d H:i:s'), $in['to']->setTimezone($utc)->format('Y-m-d H:i:s')),
        ]);
        exit;
    }

    /** Send due scheduled reports. Called from bin/publish.php. Returns how many were sent. */
    public static function run(int $limit = 10): int
    {
        if (!config('mail.enabled')) {
            return 0;
        }
        $pdo = Database::connection();
        $stmt = $pdo->query("SELECT r.user_id, r.frequency, r.last_sent_at, u.id, u.email, u.display_name, u.timezone FROM report_settings r JOIN users u ON u.id = r.user_id
            WHERE r.frequency <> 'off' AND r.recipients IS NOT NULL AND r.recipients <> '' AND (r.last_sent_at IS NULL OR r.last_sent_at < '" . gmdate('Y-m-d H:i:s', time() - 6 * 86400) . "') LIMIT 200");
        $sent = 0;
        foreach ($stmt->fetchAll() as $row) {
            if ($sent >= $limit) {
                break;
            }
            $now = new DateTimeImmutable('now', Workspace::timezone($row));
            $due = $row['frequency'] === 'weekly'
                ? (int) $now->format('N') === 1
                : (int) $now->format('j') === 1 && ($row['last_sent_at'] === null || strtotime($row['last_sent_at'] . ' UTC') < time() - 25 * 86400);
            if (!$due || (int) $now->format('G') < self::SEND_HOUR || !Channels::allowance((int) $row['id'])['paid']) {
                continue;
            }
            // Mark first so a mail failure never sends the same report every minute.
            $pdo->prepare('UPDATE report_settings SET last_sent_at = :now WHERE user_id = :uid')->execute(['now' => Database::now(), 'uid' => (int) $row['id']]);
            self::send($row, $row['frequency'] === 'monthly' ? 'last-month' : '7d');
            $sent++;
        }
        return $sent;
    }

    /** Email the report summary and link to the user's recipients. */
    private static function send(array $user, string $range): void
    {
        $uid = (int) $user['id'];
        $s = self::settings($uid);
        if (!$s['share_token']) {
            $s['share_token'] = bin2hex(random_bytes(16));
            self::store($uid, ['share_token' => $s['share_token']] + self::row($s));
        }
        $in = Workspace::insights($user, $range);
        $brand = self::brand($uid);
        $url = absolute_url('/r/' . $s['share_token'] . ($range !== '30d' ? '?range=' . $range : ''));
        $mail = EmailTemplate::report($in, $brand + ['owner' => (string) ($user['display_name'] ?? '')], $url, $brand['logo'] ? absolute_url($brand['logo']) : null);
        foreach ($s['recipients'] as $to) {
            Mailer::send($to, $mail['subject'], $mail['html'], $mail['text']);
        }
    }

    private static function owner(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            not_found();
        }
        $stmt = Database::connection()->prepare('SELECT u.id, u.email, u.display_name, u.timezone FROM report_settings r JOIN users u ON u.id = r.user_id WHERE r.share_token = :t');
        $stmt->execute(['t' => $token]);
        $user = $stmt->fetch();
        if (!$user || !Channels::allowance((int) $user['id'])['paid']) {
            not_found();
        }
        return $user;
    }

    private static function storeLogo(int $uid, array $upload, string $back, ?string $old): string
    {
        if (($upload['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $upload['tmp_name'])) {
            Toolkit::done($back, 'The logo didn’t upload. Try again with a PNG, JPG or WebP under 512 KB.');
        }
        if ((int) $upload['size'] > self::LOGO_MAX) {
            Toolkit::done($back, 'Use a logo under 512 KB.');
        }
        $type = (string) (new finfo(FILEINFO_MIME_TYPE))->file((string) $upload['tmp_name']);
        $size = @getimagesize((string) $upload['tmp_name']);
        if (!isset(self::LOGO_TYPES[$type]) || $size === false || $size[0] < 16 || $size[1] < 16 || $size[0] > 4000 || $size[1] > 4000) {
            Toolkit::done($back, 'Use a PNG, JPG or WebP image for the logo.');
        }
        $dir = self::dir();
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            Toolkit::done($back, 'The logo couldn’t be saved. Please try again.');
        }
        $name = $uid . '-' . bin2hex(random_bytes(8)) . '.' . self::LOGO_TYPES[$type];
        if (!move_uploaded_file((string) $upload['tmp_name'], $dir . '/' . $name)) {
            Toolkit::done($back, 'The logo couldn’t be saved. Please try again.');
        }
        if ($old) {
            @unlink($dir . '/' . basename($old));
        }
        return $name;
    }

    private static function row(array $s): array
    {
        return [
            'brand_name' => $s['brand_name'], 'brand_color' => $s['brand_color'], 'logo_file' => $s['logo_file'],
            'white_label' => $s['white_label'] ? 1 : 0, 'frequency' => $s['frequency'],
            'recipients' => $s['recipients'] ? implode(',', $s['recipients']) : null, 'share_token' => $s['share_token'],
        ];
    }

    private static function store(int $uid, array $row): void
    {
        $pdo = Database::connection();
        $row['updated_at'] = Database::now();
        $exists = $pdo->prepare('SELECT 1 FROM report_settings WHERE user_id = :uid');
        $exists->execute(['uid' => $uid]);
        if ($exists->fetchColumn()) {
            $set = implode(', ', array_map(static fn (string $k): string => "$k = :$k", array_keys($row)));
            $pdo->prepare("UPDATE report_settings SET $set WHERE user_id = :uid")->execute($row + ['uid' => $uid]);
            return;
        }
        $row = ['user_id' => $uid] + $row;
        $pdo->prepare('INSERT INTO report_settings (' . implode(', ', array_keys($row)) . ') VALUES (:' . implode(', :', array_keys($row)) . ')')->execute($row);
    }

    private static function dir(): string
    {
        return BASE_PATH . '/storage/branding';
    }
}
