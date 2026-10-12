<?php

declare(strict_types=1);

final class Security
{
    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
            || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('awt_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        $savePath = BASE_PATH . '/storage/sessions';
        if (is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
        }
        session_start();
    }

    public static function sendHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https://yt3.ggpht.com https://yt3.googleusercontent.com https://media.licdn.com https://media-exp1.licdn.com https://media-exp2.licdn.com; font-src 'self'; connect-src 'self'; form-action 'self' https://accounts.google.com https://www.linkedin.com; frame-ancestors 'self'; base-uri 'self'; object-src 'none'");
        if (self::isHttps() && config('app.is_production')) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
    }

    private static function key(): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            if (config('app.is_production')) {
                throw new RuntimeException('APP_KEY must be set in production.');
            }
            $key = 'local-development-key-not-for-production';
        }
        return $key;
    }

    /** Encrypt a secret (such as an OAuth token) for storage. */
    public static function seal(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::sealKey()));
    }

    public static function unseal(?string $sealed): ?string
    {
        $raw = base64_decode((string) $sealed, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), self::sealKey());
        return $plain === false ? null : $plain;
    }

    private static function sealKey(): string
    {
        return sodium_crypto_generichash('atwebtech-token-seal', self::key(), SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    /** Field name browsers will not autofill. The old `website` honeypot was filled by password managers. */
    public const HONEYPOT = 'awt_hp';

    /** Signed render timestamp used to reject instant bot submissions. */
    public static function formTimestamp(): string
    {
        $time = (string) time();
        return $time . '.' . hash_hmac('sha256', $time, self::key());
    }

    /** Keep a still-valid timestamp so a retry after an error is not rejected as “too fast”. */
    public static function freshFormTimestamp(?string $existing): string
    {
        return self::formAgeSeconds($existing) !== null ? (string) $existing : self::formTimestamp();
    }

    public static function formAgeSeconds(?string $token): ?int
    {
        if (!is_string($token) || !str_contains($token, '.')) {
            return null;
        }
        [$time, $sig] = explode('.', $token, 2);
        if (!ctype_digit($time) || !hash_equals(hash_hmac('sha256', $time, self::key()), $sig)) {
            return null;
        }
        $age = time() - (int) $time;
        return $age < 0 || $age > 7200 ? null : $age;
    }

    public static function honeypotFilled(): bool
    {
        return trim((string) ($_POST[self::HONEYPOT] ?? '')) !== '';
    }

    public static function clientFingerprint(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return hash_hmac('sha256', $ip, self::key());
    }

    public static function keyedHash(string $value): string
    {
        return hash_hmac('sha256', $value, self::key());
    }
}
