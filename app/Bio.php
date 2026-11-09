<?php

declare(strict_types=1);

/** Link in Bio: one public page per account at /l/{handle}, with ordered links and click counts. */
final class Bio
{
    public const MAX_LINKS = 20;
    public const ACCENTS = ['#16A34A', '#0EA5A4', '#2563EB', '#7C5CFF', '#DB2777', '#EA580C', '#CA8A04', '#111827'];
    private const RESERVED = ['admin', 'app', 'api', 'login', 'signup', 'pricing', 'help', 'support', 'atwebtech', 'about', 'www', 'mail', 'static', 'assets'];

    public static function page(int $uid): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM bio_pages WHERE user_id = :uid');
        $stmt->execute(['uid' => $uid]);
        return $stmt->fetch() ?: null;
    }

    public static function links(int $pageId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM bio_links WHERE page_id = :id ORDER BY position, id');
        $stmt->execute(['id' => $pageId]);
        return $stmt->fetchAll();
    }

    public static function publicUrl(string $handle): string
    {
        return public_origin() . '/l/' . $handle;
    }

    /** A free handle suggestion from the display name. */
    public static function suggestHandle(array $user): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $user['display_name'])), '-');
        $base = substr($base !== '' ? $base : 'me', 0, 24);
        if (strlen($base) < 3) {
            $base .= '-page';
        }
        $handle = $base;
        for ($i = 2; $i < 50 && (self::taken($handle, 0) || in_array($handle, self::RESERVED, true)); $i++) {
            $handle = $base . '-' . $i;
        }
        return $handle;
    }

    /** POST /app/link-in-bio: create or update the page. */
    public static function save(): never
    {
        $user = Auth::member();
        $uid = (int) $user['id'];
        $page = self::page($uid);
        $in = [
            'handle' => strtolower(trim((string) ($_POST['handle'] ?? ''))),
            'title' => trim((string) ($_POST['title'] ?? '')),
            'bio' => trim((string) ($_POST['bio'] ?? '')),
            'accent' => (string) ($_POST['accent'] ?? self::ACCENTS[0]),
            'published' => !empty($_POST['published']) ? 1 : 0,
        ];
        $errors = [];
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{1,28}[a-z0-9])$/', $in['handle'])) {
            $errors['handle'] = 'Use 3 to 30 lowercase letters, numbers, or dashes, starting and ending with a letter or number.';
        } elseif (in_array($in['handle'], self::RESERVED, true) || self::taken($in['handle'], (int) ($page['id'] ?? 0))) {
            $errors['handle'] = 'That address is taken. Try another.';
        }
        if ($in['title'] === '' || mb_strlen($in['title']) > 80) {
            $errors['title'] = 'Add a page title of up to 80 characters.';
        }
        if (mb_strlen($in['bio']) > 280) {
            $errors['bio'] = 'Keep the description to 280 characters.';
        }
        if (!in_array($in['accent'], self::ACCENTS, true)) {
            $in['accent'] = self::ACCENTS[0];
        }
        if ($errors) {
            self::fail('Please check the highlighted fields.', $errors, $in);
        }
        $pdo = Database::connection();
        $now = Database::now();
        if ($page) {
            $pdo->prepare('UPDATE bio_pages SET handle = :handle, title = :title, bio = :bio, accent = :accent, published = :published, updated_at = :now WHERE id = :id')
                ->execute($in + ['bio' => $in['bio'] !== '' ? $in['bio'] : null, 'now' => $now, 'id' => $page['id']]);
        } else {
            $pdo->prepare('INSERT INTO bio_pages (user_id, handle, title, bio, accent, published, created_at, updated_at) VALUES (:uid, :handle, :title, :bio, :accent, :published, :created, :now)')
                ->execute($in + ['uid' => $uid, 'bio' => $in['bio'] !== '' ? $in['bio'] : null, 'created' => $now, 'now' => $now]);
        }
        self::done($in['published'] ? 'Saved. Your page is live.' : 'Saved. Your page is hidden until you publish it.');
    }

    /** POST /app/link-in-bio/links: add, update, delete, or move a link. */
    public static function link(): never
    {
        $user = Auth::member();
        $page = self::page((int) $user['id']);
        if ($page === null) {
            self::done('Save your page first, then add links.');
        }
        $pdo = Database::connection();
        $action = (string) ($_POST['action'] ?? 'add');
        $id = (int) ($_POST['id'] ?? 0);
        $own = static function (int $id) use ($pdo, $page): ?array {
            $stmt = $pdo->prepare('SELECT * FROM bio_links WHERE id = :id AND page_id = :page');
            $stmt->execute(['id' => $id, 'page' => $page['id']]);
            return $stmt->fetch() ?: null;
        };

        if ($action === 'delete') {
            $pdo->prepare('DELETE FROM bio_links WHERE id = :id AND page_id = :page')->execute(['id' => $id, 'page' => $page['id']]);
            self::done('Link removed.');
        }
        if ($action === 'up' || $action === 'down') {
            $links = self::links((int) $page['id']);
            $ids = array_map(static fn ($l) => (int) $l['id'], $links);
            $i = array_search($id, $ids, true);
            $j = $action === 'up' ? $i - 1 : $i + 1;
            if ($i !== false && isset($ids[$j])) {
                [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
                $stmt = $pdo->prepare('UPDATE bio_links SET position = :pos WHERE id = :id AND page_id = :page');
                foreach ($ids as $pos => $linkId) {
                    $stmt->execute(['pos' => $pos, 'id' => $linkId, 'page' => $page['id']]);
                }
            }
            self::done('Order updated.');
        }

        $label = trim((string) ($_POST['label'] ?? ''));
        $url = self::cleanUrl((string) ($_POST['url'] ?? ''));
        $errors = [];
        if ($label === '' || mb_strlen($label) > 80) {
            $errors['label'] = 'Add a button label of up to 80 characters.';
        }
        if ($url === null) {
            $errors['url'] = 'Enter a full web address, such as https://example.com.';
        }
        if ($errors) {
            self::fail('Please check the link.', $errors, ['label' => $label, 'url' => (string) ($_POST['url'] ?? ''), 'id' => $id], 'bio-link');
        }
        $now = Database::now();
        if ($action === 'update' && $own($id)) {
            $pdo->prepare('UPDATE bio_links SET label = :label, url = :url, updated_at = :now WHERE id = :id AND page_id = :page')
                ->execute(['label' => $label, 'url' => $url, 'now' => $now, 'id' => $id, 'page' => $page['id']]);
            self::done('Link updated.');
        }
        if (count(self::links((int) $page['id'])) >= self::MAX_LINKS) {
            self::fail(sprintf('A page can hold %d links. Remove one to add another.', self::MAX_LINKS), [], [], 'bio-link');
        }
        $pdo->prepare('INSERT INTO bio_links (page_id, label, url, position, created_at, updated_at) VALUES (:page, :label, :url, :pos, :created, :now)')
            ->execute(['page' => $page['id'], 'label' => $label, 'url' => $url, 'pos' => count(self::links((int) $page['id'])), 'created' => $now, 'now' => $now]);
        self::done('Link added.');
    }

    /** GET /l/{handle}: the public page. */
    public static function show(string $handle): never
    {
        $page = self::byHandle($handle);
        if ($page === null) {
            not_found();
        }
        Database::connection()->prepare('UPDATE bio_pages SET views = views + 1 WHERE id = :id')->execute(['id' => $page['id']]);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        View::partial('bio/page', ['page' => $page, 'links' => self::links((int) $page['id'])]);
        exit;
    }

    /** GET /l/{handle}/{id}: count the click, then go to the link. */
    public static function go(string $handle, string $id): never
    {
        $page = self::byHandle($handle);
        if ($page === null || !ctype_digit($id)) {
            not_found();
        }
        $stmt = Database::connection()->prepare('SELECT * FROM bio_links WHERE id = :id AND page_id = :page');
        $stmt->execute(['id' => (int) $id, 'page' => $page['id']]);
        $link = $stmt->fetch();
        if (!$link || self::cleanUrl((string) $link['url']) === null) {
            not_found();
        }
        Database::connection()->prepare('UPDATE bio_links SET clicks = clicks + 1 WHERE id = :id')->execute(['id' => $link['id']]);
        header('Referrer-Policy: origin');
        redirect((string) $link['url'], 302);
    }

    private static function byHandle(string $handle): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM bio_pages WHERE handle = :h AND published = 1');
        $stmt->execute(['h' => strtolower($handle)]);
        return $stmt->fetch() ?: null;
    }

    private static function taken(string $handle, int $exceptId): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM bio_pages WHERE handle = :h AND id <> :id');
        $stmt->execute(['h' => $handle, 'id' => $exceptId]);
        return (bool) $stmt->fetchColumn();
    }

    /** Only http(s) addresses with a host. Adds https:// when the scheme is left out. */
    private static function cleanUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url !== '' && !preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            $url = 'https://' . $url;
        }
        $parts = parse_url($url);
        if ($url === '' || strlen($url) > 500 || !is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host']) || !str_contains($parts['host'], '.') || preg_match('/[\s<>"]/', $url)) {
            return null;
        }
        return $url;
    }

    private static function done(string $message): never
    {
        flash('notice', $message);
        redirect('/app/link-in-bio');
    }

    private static function fail(string $message, array $errors, array $old, string $form = 'bio'): never
    {
        flash('form', ['form' => $form, 'status' => 'error', 'message' => $message, 'errors' => $errors, 'old' => $old]);
        redirect('/app/link-in-bio');
    }
}
