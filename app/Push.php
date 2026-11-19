<?php

declare(strict_types=1);

/**
 * Push notifications to the mobile app through Firebase Cloud Messaging (HTTP v1).
 * Needs a Firebase service account key: put the JSON file at storage/firebase-service-account.json, or set
 * FIREBASE_SERVICE_ACCOUNT to its path. Without it nothing is sent. Sending never throws.
 */
final class Push
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public static function enabled(): bool
    {
        return self::account() !== null;
    }

    /** Send to every device the person is signed in on. $data values must be short strings. */
    public static function toUser(int $userId, string $title, string $body, array $data = []): void
    {
        try {
            $account = self::account();
            if ($account === null) {
                return;
            }
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT id, push_token FROM mobile_tokens WHERE user_id = :uid AND push_token IS NOT NULL');
            $stmt->execute(['uid' => $userId]);
            $devices = $stmt->fetchAll();
            if (!$devices) {
                return;
            }
            $access = self::accessToken($account);
            foreach ($devices as $d) {
                $status = self::post('https://fcm.googleapis.com/v1/projects/' . rawurlencode((string) $account['project_id']) . '/messages:send', [
                    'message' => [
                        'token' => $d['push_token'],
                        'notification' => ['title' => mb_substr($title, 0, 120), 'body' => mb_substr($body, 0, 300)],
                        'data' => array_map('strval', $data),
                        'android' => ['priority' => 'HIGH'],
                        'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                    ],
                ], $access);
                if (in_array($status, [400, 404], true)) {
                    // The app was removed or the address changed: stop sending to it.
                    $pdo->prepare('UPDATE mobile_tokens SET push_token = NULL WHERE id = :id')->execute(['id' => (int) $d['id']]);
                }
            }
        } catch (Throwable $e) {
            error_log(sprintf('[%s] push to user %d: %s', gmdate('c'), $userId, $e->getMessage()));
        }
    }

    private static function account(): ?array
    {
        static $account = false;
        if ($account === false) {
            $path = (string) Env::get('FIREBASE_SERVICE_ACCOUNT', BASE_PATH . '/storage/firebase-service-account.json');
            $data = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
            $account = is_array($data) && !empty($data['client_email']) && !empty($data['private_key']) && !empty($data['project_id']) ? $data : null;
        }
        return $account;
    }

    /** A Google access token for the service account, cached on disk until shortly before it expires. */
    private static function accessToken(array $account): string
    {
        $cache = BASE_PATH . '/storage/logs/fcm-access.json';
        $saved = is_readable($cache) ? json_decode((string) file_get_contents($cache), true) : null;
        if (is_array($saved) && ($saved['email'] ?? '') === $account['client_email'] && (int) ($saved['expires'] ?? 0) > time() + 60) {
            return (string) $saved['token'];
        }
        $b64 = static fn (string $v): string => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $tokenUri = (string) ($account['token_uri'] ?? 'https://oauth2.googleapis.com/token');
        $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b64(json_encode(['iss' => $account['client_email'], 'scope' => self::SCOPE, 'aud' => $tokenUri, 'iat' => time(), 'exp' => time() + 3600]));
        if (!openssl_sign($unsigned, $signature, (string) $account['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign with the Firebase service account key.');
        }
        $ch = curl_init($tokenUri);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsigned . '.' . $b64($signature)]),
        ]);
        $data = json_decode((string) curl_exec($ch), true);
        if (empty($data['access_token'])) {
            throw new RuntimeException('Google did not return an access token for push.');
        }
        @file_put_contents($cache, json_encode(['email' => $account['client_email'], 'token' => $data['access_token'], 'expires' => time() + (int) ($data['expires_in'] ?? 3600)]), LOCK_EX);
        @chmod($cache, 0600);
        return (string) $data['access_token'];
    }

    private static function post(string $url, array $body, string $bearer): int
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $bearer],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        curl_exec($ch);
        return (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    }
}
