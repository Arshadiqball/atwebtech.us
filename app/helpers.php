<?php

declare(strict_types=1);

function config(string $key, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require BASE_PATH . '/app/config.php';
    }
    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function data(string $name): array
{
    static $cache = [];
    return $cache[$name] ??= require BASE_PATH . "/app/data/{$name}.php";
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function public_origin(): string
{
    $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    if ($host === 'atwebtech.us' || $host === 'www.atwebtech.us') {
        return 'https://atwebtech.us';
    }
    return rtrim((string) config('app.url'), '/');
}

function is_public_site(): bool
{
    return config('app.is_production') || public_origin() === 'https://atwebtech.us';
}

function absolute_url(string $path = '/'): string
{
    return public_origin() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $path = '/assets/' . ltrim($path, '/');
    $file = BASE_PATH . '/public' . $path;
    return is_file($file) ? $path . '?v=' . filemtime($file) : $path;
}

function current_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = '/' . trim(rawurldecode($path), '/');
    return $path === '/' ? '/' : $path;
}

function is_current(string $path): bool
{
    return current_path() === $path;
}

function aria_current(string $path): string
{
    return is_current($path) ? ' aria-current="page"' : '';
}

function svg_file(string $file, string $class, ?string $label): string
{
    static $cache = [];
    $svg = $cache[$file] ??= (is_file($file) ? trim((string) file_get_contents($file)) : '');
    if ($svg === '') {
        return '';
    }
    $attrs = ' class="' . e($class) . '" focusable="false"';
    $attrs .= $label === null ? ' aria-hidden="true"' : ' role="img" aria-label="' . e($label) . '"';
    return preg_replace('/^<svg\b/', '<svg' . $attrs, $svg, 1) ?? '';
}

function icon(string $name, string $class = 'icon', ?string $label = null): string
{
    return svg_file(BASE_PATH . '/app/icons/ui/' . basename($name) . '.svg', $class, $label);
}

function brand_icon(string $slug, string $class = 'brand-glyph', ?string $label = null): string
{
    return svg_file(BASE_PATH . '/app/icons/brands/' . basename($slug) . '.svg', $class, $label);
}

/** Small round channel badge used inside product demos. */
function channel_icon(string $slug, string $extra = ''): string
{
    static $map = null;
    $map ??= array_column(data('integrations'), null, 'slug');
    $item = $map[$slug] ?? null;
    if (!$item) {
        return '';
    }
    return '<span class="ch ch--' . e($slug) . ($extra ? ' ' . e($extra) : '') . '" title="' . e($item['name']) . '">'
        . brand_mark($item['icon'], $item['name'], true) . '</span>';
}

/** Glyph for an integration: Simple Icons slug, "text:" lettermark, or "ui:" Phosphor icon. */
function brand_mark(string $icon, string $name, bool $decorative = false): string
{
    $label = $decorative ? null : $name;
    if (str_starts_with($icon, 'text:')) {
        $text = '<span class="brand-text"' . ($decorative ? ' aria-hidden="true"' : '') . '>' . e(substr($icon, 5)) . '</span>';
        return $decorative ? $text : $text . '<span class="sr-only">' . e($name) . '</span>';
    }
    if (str_starts_with($icon, 'ui:')) {
        return icon(substr($icon, 3), 'brand-glyph', $label);
    }
    return brand_icon($icon, 'brand-glyph', $label);
}

/** Connected channel picture (or initial) with a small platform badge. Decorative: name it in nearby text. */
function channel_avatar(array $channel, string $size = 'md'): string
{
    $ch = array_column(integrations(), null, 'slug')[$channel['provider']] ?? null;
    $img = $channel['avatar_url']
        ? '<img src="' . e($channel['avatar_url']) . '" alt="" width="64" height="64" loading="lazy" referrerpolicy="no-referrer">'
        : '<span class="ch-av-initial">' . e(mb_strtoupper(mb_substr($channel['name'], 0, 1))) . '</span>';
    $badge = $ch ? '<span class="ch-av-badge" style="--c: ' . e($ch['color']) . '">' . brand_mark($ch['icon'], $ch['name'], true) . '</span>' : '';
    return '<span class="ch-av ch-av--' . e($size) . '" aria-hidden="true">' . $img . $badge . '</span>';
}

/** Avatar of the connected channel a post belongs to, or the network mark for planning-only posts. */
function post_mark(array $post, array $accounts): string
{
    if ($post['channel_id'] && isset($accounts[(int) $post['channel_id']])) {
        return channel_avatar($accounts[(int) $post['channel_id']], 'sm');
    }
    $ch = array_column(integrations(), null, 'slug')[$post['channel']] ?? null;
    return $ch ? '<span class="ws-ch ws-ch--sm" style="--c: ' . e($ch['color']) . '">' . brand_mark($ch['icon'], $ch['name']) . '</span>' : '';
}

/** Short status label for a post in lists. */
function post_badge(array $post, array $accounts): string
{
    $label = match (true) {
        $post['status'] === 'publishing' => ['is-busy', 'Publishing…'],
        $post['status'] === 'failed' => ['is-bad', 'Failed'],
        $post['status'] === 'planned' && (int) $post['attempts'] > 0 => ['is-bad', 'Retrying'],
        $post['status'] === 'planned' && !empty($accounts[(int) $post['channel_id']]['can_publish']) => ['is-ok', 'Scheduled'],
        $post['status'] === 'planned' => ['', 'Plan only'],
        default => null,
    };
    return $label ? '<span class="q-badge ' . $label[0] . '">' . e($label[1]) . '</span>' : '';
}

function partial(string $name, array $vars = []): void
{
    View::partial($name, $vars);
}

function integrations(): array
{
    $available = array_filter(array_map('trim', explode(',', (string) config('integrations.available'))));
    return array_map(static function (array $item) use ($available): array {
        $item['status'] = in_array($item['slug'], $available, true) ? 'available' : 'planned';
        return $item;
    }, data('integrations'));
}

function redirect(string $location, int $status = 303): never
{
    header('Location: ' . $location, true, $status);
    exit;
}

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function flash(string $key, mixed $value = null): mixed
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $stored = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $stored;
}

function safe_return_path(?string $path, string $fallback = '/'): string
{
    $path = (string) $path;
    if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//') || preg_match('/[\r\n\\\\]/', $path)) {
        return $fallback;
    }
    return $path;
}
