<?php
/**
 * Admin and form API for the LiteSpeed host. The local Python server implements the same paths.
 */
declare(strict_types=1);

const DATA_DIR = __DIR__ . '/data';
const CONTENT_PATH = DATA_DIR . '/content.json';
const AUTH_PATH = DATA_DIR . '/auth.json';
const MESSAGES_PATH = DATA_DIR . '/messages.json';
const MAIL_PATH = DATA_DIR . '/mail.json';
const SESSIONS_PATH = DATA_DIR . '/sessions.json';
const DEFAULT_PASSWORD = 'atwebtech';
const SESSION_SECONDS = 60 * 60 * 12;

header('X-Content-Type-Options: nosniff');

function send_json(int $code, array $data, array $cookies = []): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    foreach ($cookies as $cookie) {
        header('Set-Cookie: ' . $cookie, false);
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    if (strlen($raw) > 1000000) {
        throw new InvalidArgumentException('That request is too large.');
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new InvalidArgumentException('The form data was not valid.');
    }
    return $data;
}

function load_json(string $path, $fallback)
{
    if (!is_file($path)) {
        return $fallback;
    }
    $data = json_decode((string) file_get_contents($path), true);
    return $data === null ? $fallback : $data;
}

function save_json(string $path, $data): void
{
    if (!is_dir(DATA_DIR) && !mkdir(DATA_DIR, 0755, true)) {
        throw new RuntimeException('The data folder is not writable.');
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $path)) {
        throw new RuntimeException('The data folder is not writable.');
    }
}

function clean_text($value, int $limit): string
{
    $text = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $limit);
    }
    return substr($text, 0, $limit);
}

function email_ok(string $email): bool
{
    return (bool) preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email);
}

function slugify(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug === '' ? 'item' : substr($slug, 0, 80);
}

function unique_slug(array $items, string $slug, ?string $ignoreId = null): string
{
    $taken = [];
    foreach ($items as $item) {
        if (($item['id'] ?? null) !== $ignoreId) {
            $taken[$item['slug'] ?? ''] = true;
        }
    }
    $base = $slug;
    $n = 2;
    while (isset($taken[$slug])) {
        $slug = $base . '-' . $n;
        $n++;
    }
    return $slug;
}

function hash_password(string $password, ?string $salt = null): array
{
    if ($salt === null) {
        $salt = bin2hex(random_bytes(16));
    }
    $hash = hash_pbkdf2('sha256', $password, hex2bin($salt), 200000, 64, false);
    return ['salt' => $salt, 'hash' => $hash];
}

function password_ok(string $password, array $record): bool
{
    if (empty($record['salt']) || empty($record['hash'])) {
        return false;
    }
    $check = hash_password($password, $record['salt']);
    return hash_equals($record['hash'], $check['hash']);
}

function ensure_auth(): array
{
    $record = load_json(AUTH_PATH, null);
    if (!is_array($record) || empty($record['hash'])) {
        $record = hash_password(DEFAULT_PASSWORD);
        save_json(AUTH_PATH, $record);
    }
    return $record;
}

function request_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $uri = rawurldecode($uri);
    if (!empty($_GET['r'])) {
        return '/api/' . ltrim((string) $_GET['r'], '/');
    }
    return $uri;
}

function same_origin(): bool
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return true;
    }
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return $origin === 'https://' . $host || $origin === 'http://' . $host;
}

function cookie_token(): string
{
    $cookie = $_SERVER['HTTP_COOKIE'] ?? '';
    foreach (explode(';', $cookie) as $part) {
        $bits = explode('=', trim($part), 2);
        if (count($bits) === 2 && $bits[0] === 'at_session') {
            return $bits[1];
        }
    }
    return '';
}

function sessions(): array
{
    $now = time();
    $all = load_json(SESSIONS_PATH, []);
    if (!is_array($all)) {
        $all = [];
    }
    $kept = [];
    foreach ($all as $token => $expires) {
        if (is_string($token) && (int) $expires > $now) {
            $kept[$token] = (int) $expires;
        }
    }
    return $kept;
}

function session_ok(): bool
{
    $token = cookie_token();
    if ($token === '') {
        return false;
    }
    $all = sessions();
    return isset($all[$token]);
}

function require_admin(): bool
{
    if (!same_origin()) {
        send_json(403, ['error' => 'Open the admin on this site.']);
    }
    if (!session_ok()) {
        send_json(401, ['error' => 'Sign in again.']);
    }
    return true;
}

function session_cookie(string $token, int $maxAge): string
{
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '; Secure' : '';
    return 'at_session=' . $token . '; HttpOnly; Path=/; SameSite=Lax; Max-Age=' . $maxAge . $secure;
}

function tucson_now(): string
{
    $now = new DateTime('now', new DateTimeZone('America/Phoenix'));
    $hour = ltrim($now->format('h'), '0') ?: '12';
    return $now->format('F') . ' ' . $now->format('j') . ', ' . $now->format('Y') . ', ' . $hour . ':' . $now->format('i A') . ' MST';
}

function public_content(array $content): array
{
    $posts = array_values(array_filter($content['posts'] ?? [], function ($post) {
        return !empty($post['published']);
    }));
    usort($posts, function ($a, $b) {
        return strcmp($b['date'] ?? '', $a['date'] ?? '');
    });
    $services = array_values(array_filter($content['services'] ?? [], function ($service) {
        return ($service['published'] ?? true) !== false;
    }));
    usort($services, function ($a, $b) {
        return [$a['order'] ?? 0, $a['title'] ?? ''] <=> [$b['order'] ?? 0, $b['title'] ?? ''];
    });
    return ['contact' => $content['contact'] ?? [], 'posts' => $posts, 'services' => $services];
}

function normalize_contact(array $raw): array
{
    $email = clean_text($raw['email'] ?? '', 120);
    $phone = clean_text($raw['phone'] ?? '', 40);
    $address = clean_text($raw['address'] ?? '', 160);
    if (!email_ok($email)) {
        throw new InvalidArgumentException('Enter a valid email address.');
    }
    $digits = preg_replace('/\D/', '', $phone) ?? '';
    if (strlen($digits) < 10) {
        throw new InvalidArgumentException('Enter a phone number with at least 10 digits.');
    }
    if (strlen($address) < 3) {
        throw new InvalidArgumentException('Enter an address.');
    }
    return [
        'email' => $email,
        'phone' => $phone,
        'phoneHref' => 'tel:+' . $digits,
        'address' => $address,
    ];
}

function normalize_post(array $raw, array $posts, ?array $existing = null): array
{
    $title = clean_text($raw['title'] ?? '', 140);
    if ($title === '') {
        throw new InvalidArgumentException('Add a title.');
    }
    $body = trim(str_replace("\r\n", "\n", (string) ($raw['body'] ?? '')));
    if (strlen($body) < 20) {
        throw new InvalidArgumentException('Write a little more in the body.');
    }
    if (strlen($body) > 20000) {
        throw new InvalidArgumentException('That note is too long.');
    }
    $excerpt = clean_text($raw['excerpt'] ?? '', 280);
    if ($excerpt === '') {
        $excerpt = clean_text($body, 180);
    }
    $date = trim((string) ($raw['date'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('Use a date like 2026-10-05.');
    }
    $slug = unique_slug($posts, slugify((string) ($raw['slug'] ?? $title)), $existing['id'] ?? null);
    return [
        'id' => $existing['id'] ?? bin2hex(random_bytes(8)),
        'slug' => $slug,
        'title' => $title,
        'excerpt' => $excerpt,
        'category' => clean_text($raw['category'] ?? '', 40) ?: 'Notes',
        'date' => $date,
        'body' => $body,
        'published' => (bool) ($raw['published'] ?? true),
    ];
}

function normalize_service(array $raw, array $services, ?array $existing = null): array
{
    $title = clean_text($raw['title'] ?? '', 80);
    if ($title === '') {
        throw new InvalidArgumentException('Add a service name.');
    }
    $summary = clean_text($raw['summary'] ?? '', 320);
    if (strlen($summary) < 12) {
        throw new InvalidArgumentException('Add a short summary.');
    }
    $body = trim(str_replace("\r\n", "\n", (string) ($raw['body'] ?? '')));
    if (strlen($body) < 20) {
        throw new InvalidArgumentException('Write a little more in the detail.');
    }
    $image = clean_text($raw['image'] ?? '', 180);
    if (!preg_match('#^/[A-Za-z0-9_./-]+$#', $image) || strpos($image, '..') !== false) {
        throw new InvalidArgumentException('Use an image path like /services/ai_chatbots.jpg.');
    }
    $accent = clean_text($raw['accent'] ?? '', 7) ?: '#67e8f9';
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $accent)) {
        throw new InvalidArgumentException('Use a color like #67e8f9.');
    }
    $order = $raw['order'] ?? ($existing['order'] ?? count($services) + 1);
    if (!is_numeric($order)) {
        throw new InvalidArgumentException('Order should be a number.');
    }
    $source = $raw['points'] ?? [];
    $lines = is_array($source) ? $source : preg_split('/\r\n|\n|\r/', (string) $source);
    $points = [];
    foreach ($lines as $line) {
        $point = clean_text($line, 160);
        if ($point !== '') {
            $points[] = $point;
        }
    }
    if (count($points) > 8) {
        throw new InvalidArgumentException('Keep the list to 8 points.');
    }
    $slug = unique_slug($services, slugify((string) ($raw['slug'] ?? $title)), $existing['id'] ?? null);
    return [
        'id' => $existing['id'] ?? bin2hex(random_bytes(8)),
        'slug' => $slug,
        'title' => $title,
        'summary' => $summary,
        'image' => $image,
        'accent' => strtolower($accent),
        'order' => (int) $order,
        'points' => $points,
        'body' => $body,
        'published' => (bool) ($raw['published'] ?? true),
    ];
}

function normalize_message(array $raw): array
{
    $kind = $raw['kind'] ?? '';
    $email = clean_text($raw['email'] ?? '', 120);
    if (!in_array($kind, ['contact', 'newsletter'], true)) {
        throw new InvalidArgumentException('That form could not be read.');
    }
    if (!email_ok($email)) {
        throw new InvalidArgumentException('Enter a valid email address.');
    }
    $entry = [
        'id' => bin2hex(random_bytes(6)),
        'kind' => $kind,
        'email' => $email,
        'at' => tucson_now(),
    ];
    if ($kind === 'contact') {
        $first = clean_text($raw['firstName'] ?? '', 80);
        $last = clean_text($raw['lastName'] ?? '', 80);
        $phone = clean_text($raw['phone'] ?? '', 40);
        $message = trim(preg_replace('/\s+/', ' ', (string) ($raw['message'] ?? '')) ?? '');
        if ($first === '') {
            throw new InvalidArgumentException('Add your first name.');
        }
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if ($phone !== '' && !(strlen($digits) === 10 || (strlen($digits) === 11 && $digits[0] === '1'))) {
            throw new InvalidArgumentException('Enter a US phone number with the area code.');
        }
        if (array_key_exists('privacy', $raw) && $raw['privacy'] !== true) {
            throw new InvalidArgumentException('Agree to the privacy policy to continue.');
        }
        if (strlen($message) < 5) {
            throw new InvalidArgumentException('Describe the project in a sentence or two.');
        }
        if (strlen($message) > 4000) {
            throw new InvalidArgumentException('That message is too long.');
        }
        $entry['firstName'] = $first;
        $entry['lastName'] = $last;
        $entry['phone'] = $phone;
        $entry['message'] = $message;
    }
    return $entry;
}

function load_mail(): array
{
    $settings = [
        'host' => '',
        'port' => 587,
        'user' => '',
        'password' => '',
        'from' => 'arshad@atwebtechnologies.com',
        'fromName' => 'At Web Technologies',
        'to' => 'arshadiqbal.d@gmail.com, aisal@atwebtechnologies.com',
        'tls' => 'starttls',
        'tlsName' => '',
    ];
    $stored = load_json(MAIL_PATH, []);
    if (is_array($stored)) {
        $settings = array_merge($settings, $stored);
    }
    return $settings;
}

function public_mail(array $settings): array
{
    return [
        'host' => $settings['host'] ?? '',
        'port' => $settings['port'] ?? 587,
        'user' => $settings['user'] ?? '',
        'from' => $settings['from'] ?? '',
        'fromName' => $settings['fromName'] ?? '',
        'to' => $settings['to'] ?? '',
        'tls' => $settings['tls'] ?? 'starttls',
        'passwordSet' => !empty($settings['password']),
    ];
}

function normalize_mail(array $raw, array $previous): array
{
    $host = clean_text($raw['host'] ?? '', 200);
    $tls = clean_text($raw['tls'] ?? ($raw['encryption'] ?? ''), 20) ?: 'starttls';
    if (!in_array($tls, ['starttls', 'ssl'], true)) {
        throw new InvalidArgumentException('Choose STARTTLS or SSL.');
    }
    $port = (int) ($raw['port'] ?? 587);
    if ($port < 1 || $port > 65535) {
        throw new InvalidArgumentException('Enter a mail port.');
    }
    if ($host !== '' && !preg_match('/^[A-Za-z0-9.-]+$/', $host)) {
        throw new InvalidArgumentException('Enter the mail server host.');
    }
    $sender = clean_text($raw['from'] ?? ($raw['sender'] ?? ''), 200);
    if ($sender !== '' && !email_ok($sender)) {
        throw new InvalidArgumentException('Enter a valid From address.');
    }
    $recipients = array_values(array_filter(array_map('trim', explode(',', str_replace(';', ',', clean_text($raw['to'] ?? '', 400))))));
    foreach ($recipients as $email) {
        if (!email_ok($email)) {
            throw new InvalidArgumentException('Enter the addresses that should receive requests.');
        }
    }
    if (!$recipients) {
        throw new InvalidArgumentException('Enter the addresses that should receive requests.');
    }
    $password = (string) ($raw['password'] ?? ($raw['pass'] ?? ''));
    if ($password === '') {
        $password = (string) ($previous['password'] ?? '');
    }
    if (strlen($password) > 200) {
        throw new InvalidArgumentException('That mail password is too long.');
    }
    $saved = [
        'host' => $host,
        'port' => $port,
        'user' => clean_text($raw['user'] ?? '', 200),
        'password' => $password,
        'from' => $sender ?: 'arshad@atwebtechnologies.com',
        'fromName' => clean_text($raw['fromName'] ?? ($raw['from_name'] ?? ($previous['fromName'] ?? '')), 80) ?: 'At Web Technologies',
        'to' => implode(', ', $recipients),
        'tls' => $tls,
    ];
    $tlsName = clean_text($raw['tlsName'] ?? ($previous['tlsName'] ?? ''), 200);
    if ($tlsName !== '') {
        $saved['tlsName'] = $tlsName;
    }
    return $saved;
}

function smtp_expect($socket, array $codes): string
{
    $line = '';
    do {
        $chunk = fgets($socket, 515);
        if ($chunk === false) {
            throw new RuntimeException('The mail server closed the connection.');
        }
        $line = $chunk;
    } while (isset($line[3]) && $line[3] === '-');
    $code = (int) substr($line, 0, 3);
    if (!in_array($code, $codes, true)) {
        throw new RuntimeException(trim($line));
    }
    return $line;
}

function smtp_cmd($socket, string $command, array $codes): void
{
    fwrite($socket, $command . "\r\n");
    smtp_expect($socket, $codes);
}

function send_consultation(array $entry): array
{
    if (($entry['kind'] ?? '') !== 'contact') {
        return [false, ''];
    }
    $settings = load_mail();
    if (empty($settings['host']) || empty($settings['password'])) {
        return [false, 'Add the mailbox under Admin → Mail.'];
    }
    $recipients = array_values(array_filter(array_map('trim', explode(',', (string) $settings['to']))));
    if (!$recipients) {
        return [false, 'Add at least one address under Admin → Mail.'];
    }
    $name = trim(($entry['firstName'] ?? '') . ' ' . ($entry['lastName'] ?? ''));
    $fromName = $settings['fromName'] ?: 'At Web Technologies';
    $subject = 'New project inquiry from ' . ($name ?: $entry['email']);
    $body = implode("\n", [
        'New consultation request',
        'ATwebTech · Tucson, Arizona',
        '',
        'Name: ' . ($name ?: '—'),
        'Email: ' . $entry['email'],
        'Phone: ' . (($entry['phone'] ?? '') ?: '—'),
        'Received: ' . $entry['at'],
        '',
        'Project',
        $entry['message'] ?? '',
        '',
        'Reply to this email to reach the sender.',
    ]);
    $headers = [
        'From: ' . $fromName . ' <' . $settings['from'] . '>',
        'To: ' . implode(', ', $recipients),
        'Reply-To: ' . ($name !== '' ? $name . ' <' . $entry['email'] . '>' : $entry['email']),
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    $payload = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $body);
    try {
        $peer = $settings['tlsName'] ?? $settings['host'];
        $context = stream_context_create([
            'ssl' => [
                'peer_name' => $peer,
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $remote = ($settings['tls'] ?? '') === 'ssl'
            ? 'ssl://' . $settings['host'] . ':' . (int) $settings['port']
            : 'tcp://' . $settings['host'] . ':' . (int) $settings['port'];
        $socket = stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            throw new RuntimeException($errstr ?: 'Could not reach the mail server.');
        }
        stream_set_timeout($socket, 20);
        smtp_expect($socket, [220]);
        smtp_cmd($socket, 'EHLO atwebtech.us', [250]);
        if (($settings['tls'] ?? '') !== 'ssl') {
            smtp_cmd($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('The mail server certificate was rejected.');
            }
            smtp_cmd($socket, 'EHLO atwebtech.us', [250]);
        }
        smtp_cmd($socket, 'AUTH LOGIN', [334]);
        smtp_cmd($socket, base64_encode((string) $settings['user']), [334]);
        smtp_cmd($socket, base64_encode((string) $settings['password']), [235]);
        smtp_cmd($socket, 'MAIL FROM:<' . $settings['from'] . '>', [250]);
        foreach ($recipients as $recipient) {
            smtp_cmd($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
        }
        smtp_cmd($socket, 'DATA', [354]);
        fwrite($socket, $payload . "\r\n.\r\n");
        smtp_expect($socket, [250]);
        smtp_cmd($socket, 'QUIT', [221]);
        fclose($socket);
    } catch (Throwable $exc) {
        $detail = trim($exc->getMessage()) ?: 'The mail server refused the message.';
        return [false, substr($detail, 0, 180)];
    }
    return [true, ''];
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = request_path();
    if ($path === '/api/session' && $method === 'GET') {
        send_json(200, ['ok' => session_ok()]);
    }
    if ($path === '/api/content' && $method === 'GET') {
        $content = load_json(CONTENT_PATH, []);
        if (!is_array($content)) {
            throw new RuntimeException('The site content could not be read.');
        }
        send_json(200, public_content($content));
    }
    if ($path === '/api/login' && $method === 'POST') {
        if (!same_origin()) {
            send_json(403, ['error' => 'Open the admin on this site.']);
        }
        $raw = read_json_body();
        $record = ensure_auth();
        if (!password_ok((string) ($raw['password'] ?? ''), $record)) {
            send_json(401, ['error' => 'That password is not right.']);
        }
        $token = bin2hex(random_bytes(32));
        $all = sessions();
        $all[$token] = time() + SESSION_SECONDS;
        save_json(SESSIONS_PATH, $all);
        send_json(200, ['ok' => true], [session_cookie($token, SESSION_SECONDS)]);
    }
    if ($path === '/api/logout' && $method === 'POST') {
        $token = cookie_token();
        if ($token !== '') {
            $all = sessions();
            unset($all[$token]);
            save_json(SESSIONS_PATH, $all);
        }
        send_json(200, ['ok' => true], [session_cookie('', 0)]);
    }
    if ($path === '/api/messages' && $method === 'POST') {
        if (!same_origin()) {
            send_json(403, ['error' => 'Open the site on this computer.']);
        }
        $entry = normalize_message(read_json_body());
        [$emailed, $mailError] = send_consultation($entry);
        $entry['emailed'] = $emailed;
        if ($mailError !== '') {
            $entry['mailError'] = $mailError;
        }
        $messages = load_json(MESSAGES_PATH, []);
        if (!is_array($messages)) {
            $messages = [];
        }
        $messages[] = $entry;
        save_json(MESSAGES_PATH, array_slice($messages, -200));
        send_json(201, ['ok' => true, 'emailed' => $emailed, 'mailError' => $mailError]);
    }
    if ($path === '/api/admin/content' && $method === 'GET') {
        require_admin();
        $content = load_json(CONTENT_PATH, []);
        $posts = $content['posts'] ?? [];
        usort($posts, function ($a, $b) {
            return strcmp($b['date'] ?? '', $a['date'] ?? '');
        });
        $services = $content['services'] ?? [];
        usort($services, function ($a, $b) {
            return [$a['order'] ?? 0, $a['title'] ?? ''] <=> [$b['order'] ?? 0, $b['title'] ?? ''];
        });
        send_json(200, ['contact' => $content['contact'] ?? [], 'posts' => $posts, 'services' => $services]);
    }
    if ($path === '/api/admin/messages' && $method === 'GET') {
        require_admin();
        $messages = load_json(MESSAGES_PATH, []);
        send_json(200, ['messages' => array_reverse(is_array($messages) ? $messages : [])]);
    }
    if ($path === '/api/admin/mail' && $method === 'GET') {
        require_admin();
        send_json(200, ['mail' => public_mail(load_mail())]);
    }
    if ($path === '/api/admin/mail' && $method === 'PUT') {
        require_admin();
        $settings = normalize_mail(read_json_body(), load_mail());
        save_json(MAIL_PATH, $settings);
        send_json(200, ['mail' => public_mail($settings)]);
    }
    if ($path === '/api/admin/contact' && $method === 'PUT') {
        require_admin();
        $content = load_json(CONTENT_PATH, []);
        $content['contact'] = normalize_contact(read_json_body());
        save_json(CONTENT_PATH, $content);
        send_json(200, ['contact' => $content['contact']]);
    }
    if ($path === '/api/admin/password' && $method === 'POST') {
        require_admin();
        $raw = read_json_body();
        $record = ensure_auth();
        if (!password_ok((string) ($raw['current'] ?? ''), $record)) {
            send_json(400, ['error' => 'The current password does not match.']);
        }
        $next = (string) ($raw['next'] ?? '');
        if (strlen($next) < 8) {
            send_json(400, ['error' => 'Use at least 8 characters.']);
        }
        save_json(AUTH_PATH, hash_password($next));
        send_json(200, ['ok' => true]);
    }
    if ($path === '/api/admin/posts' && $method === 'POST') {
        require_admin();
        $content = load_json(CONTENT_PATH, []);
        $content['posts'] = $content['posts'] ?? [];
        $post = normalize_post(read_json_body(), $content['posts']);
        $content['posts'][] = $post;
        save_json(CONTENT_PATH, $content);
        send_json(201, ['post' => $post]);
    }
    if ($path === '/api/admin/services' && $method === 'POST') {
        require_admin();
        $content = load_json(CONTENT_PATH, []);
        $content['services'] = $content['services'] ?? [];
        $service = normalize_service(read_json_body(), $content['services']);
        $content['services'][] = $service;
        save_json(CONTENT_PATH, $content);
        send_json(201, ['service' => $service]);
    }
    if (preg_match('#^/api/admin/(posts|services)/([^/]+)$#', $path, $match)) {
        require_admin();
        $kind = $match[1];
        $id = $match[2];
        $key = $kind === 'posts' ? 'posts' : 'services';
        $content = load_json(CONTENT_PATH, []);
        $items = $content[$key] ?? [];
        $index = null;
        foreach ($items as $i => $item) {
            if (($item['id'] ?? '') === $id) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            send_json(404, ['error' => $kind === 'posts' ? 'That note is gone.' : 'That service is gone.']);
        }
        if ($method === 'DELETE') {
            array_splice($items, $index, 1);
            $content[$key] = array_values($items);
            save_json(CONTENT_PATH, $content);
            send_json(200, ['ok' => true]);
        }
        if ($method === 'PUT') {
            $updated = $kind === 'posts'
                ? normalize_post(read_json_body(), $items, $items[$index])
                : normalize_service(read_json_body(), $items, $items[$index]);
            $items[$index] = $updated;
            $content[$key] = $items;
            save_json(CONTENT_PATH, $content);
            send_json(200, [$kind === 'posts' ? 'post' : 'service' => $updated]);
        }
    }
    send_json(404, ['error' => 'Not found.']);
} catch (InvalidArgumentException $exc) {
    send_json(400, ['error' => $exc->getMessage()]);
} catch (Throwable $exc) {
    send_json(500, ['error' => $exc->getMessage() ?: 'Something went wrong.']);
}
