<?php

declare(strict_types=1);

/**
 * Meta (Facebook/Instagram) ads measurement: the Meta Pixel in the browser plus the Conversions API
 * from the server. Every conversion is sent both ways with the same event ID, so Meta counts it once
 * and still sees it when a browser blocks the Pixel.
 *
 * Personal data is hashed (SHA-256) before it leaves the server. Nothing is sent when META_PIXEL_ID is
 * empty, outside the live site, or when the visitor's browser sends the Global Privacy Control signal.
 */
final class Meta
{
    private const GRAPH = 'https://graph.facebook.com/v23.0';
    private const SESSION_KEY = '_meta_events';

    public static function pixelId(): string
    {
        $id = (string) config('meta.pixel_id');
        return preg_match('/^\d{5,20}$/', $id) ? $id : '';
    }

    /** True when tracking may run for this request. */
    public static function enabled(): bool
    {
        if (self::pixelId() === '' || self::optedOut()) {
            return false;
        }
        return is_public_site() || self::testBase() !== '';
    }

    /** Global Privacy Control (a "do not sell or share" signal some browsers send). */
    public static function optedOut(): bool
    {
        return ($_SERVER['HTTP_SEC_GPC'] ?? '') === '1';
    }

    /**
     * Records a conversion: queues it for the Pixel on the next page and sends it to the Conversions API.
     *
     * @param array{email?: ?string, user_id?: int|string|null} $who
     * @param array<string, mixed> $data custom_data, e.g. ['value' => 60, 'currency' => 'USD']
     */
    public static function track(string $event, array $who = [], array $data = [], ?string $eventId = null): ?string
    {
        if (!self::enabled()) {
            return null;
        }
        $eventId ??= strtolower($event) . '-' . bin2hex(random_bytes(8));
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::SESSION_KEY][] = ['event' => $event, 'id' => $eventId, 'data' => $data];
            $_SESSION[self::SESSION_KEY] = array_slice($_SESSION[self::SESSION_KEY], -10);
        }
        if ((string) config('meta.capi_token') !== '') {
            self::send($event, $eventId, $who, $data);
        }
        return $eventId;
    }

    /** Conversions waiting to be fired by the Pixel on this page (removes them from the queue). */
    public static function takeBrowserEvents(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION[self::SESSION_KEY])) {
            return [];
        }
        $events = $_SESSION[self::SESSION_KEY];
        unset($_SESSION[self::SESSION_KEY]);
        return array_values($events);
    }

    /** The money a plan charges at checkout: one month, or a year at the yearly rate. */
    public static function planValue(string $plan, int $channels, string $billing): float
    {
        if (!isset(data('pricing')['plans'][$plan])) {
            return 0.0;
        }
        $monthly = Pricing::monthlyTotal($plan, $channels);
        return round($billing === 'yearly' ? $monthly * data('pricing')['yearly_months'] : $monthly, 2);
    }

    private static function send(string $event, string $eventId, array $who, array $data): void
    {
        $user = array_filter([
            'em' => !empty($who['email']) ? [hash('sha256', strtolower(trim((string) $who['email'])))] : null,
            'external_id' => !empty($who['user_id']) ? [hash('sha256', 'atwebtech-user-' . $who['user_id'])] : null,
            'client_ip_address' => self::clientIp(),
            'client_user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 400) ?: null,
            'fbp' => self::cookie('_fbp'),
            'fbc' => self::fbc(),
        ]);
        $payload = [
            'event_name' => $event,
            'event_time' => time(),
            'event_id' => $eventId,
            'action_source' => 'website',
            'event_source_url' => self::sourceUrl(),
            'user_data' => $user,
        ];
        if ($data) {
            $payload['custom_data'] = $data;
        }
        $fields = [
            'data' => json_encode([$payload], JSON_UNESCAPED_SLASHES),
            'access_token' => (string) config('meta.capi_token'),
        ];
        $testCode = (string) config('meta.test_event_code');
        if ($testCode !== '') {
            $fields['test_event_code'] = $testCode;
        }
        $base = self::testBase() !== '' ? self::testBase() : self::GRAPH;
        $ch = curl_init($base . '/' . self::pixelId() . '/events');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_TIMEOUT => 4,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | (config('app.is_production') ? 0 : CURLPROTO_HTTP),
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($body === false || $status !== 200) {
            $reason = is_string($body) ? (string) (json_decode($body, true)['error']['message'] ?? '') : curl_error($ch);
            error_log(sprintf('[%s] meta capi %s failed: HTTP %d %s', gmdate('c'), $event, $status, mb_substr($reason, 0, 200)));
        }
    }

    /** _fbc cookie, or one built from the fbclid captured when the visitor arrived from an ad. */
    private static function fbc(): ?string
    {
        $cookie = self::cookie('_fbc');
        if ($cookie !== null) {
            return $cookie;
        }
        $c = Campaign::get();
        if (($c['click_kind'] ?? '') === 'fbclid' && !empty($c['click_id'])) {
            $at = is_numeric($_SESSION['_meta_click_at'] ?? null) ? (int) $_SESSION['_meta_click_at'] : (int) (microtime(true) * 1000);
            $_SESSION['_meta_click_at'] = $at;
            return 'fb.1.' . $at . '.' . $c['click_id'];
        }
        return null;
    }

    private static function cookie(string $name): ?string
    {
        $v = (string) ($_COOKIE[$name] ?? '');
        return preg_match('/^fb\.\d\.\d+\.[A-Za-z0-9_.-]{1,500}$/', $v) ? $v : null;
    }

    private static function clientIp(): ?string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    private static function sourceUrl(): string
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $origin = public_origin();
        return str_starts_with($ref, $origin . '/') ? mb_substr($ref, 0, 500) : $origin . current_path();
    }

    private static function testBase(): string
    {
        return config('app.is_production') ? '' : rtrim((string) Env::get('META_TEST_BASE', ''), '/');
    }
}
