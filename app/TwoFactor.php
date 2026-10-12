<?php

declare(strict_types=1);

/** Authenticator-app two-factor authentication: setup, recovery codes, and the sign-in challenge. */
final class TwoFactor
{
    private const SETUP_TTL = 600;
    private const LOGIN_TTL = 300;
    private const RECOVERY_TTL = 900;
    private const RECOVERY_COUNT = 8;
    private const ATTEMPT_MAX = 8;
    private const ATTEMPT_WINDOW = 15;
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** @return array{enabled:bool,confirmedAt:?string,setup:?array,recovery:?list<string>} */
    public static function pageState(int $userId): array
    {
        $row = self::record($userId);
        $enabled = $row !== null && $row['totp_confirmed_at'] !== null;
        return [
            'enabled' => $enabled,
            'confirmedAt' => $enabled ? (string) $row['totp_confirmed_at'] : null,
            'setup' => $enabled ? null : self::pendingSetup($userId),
            'recovery' => self::pendingRecovery($userId),
        ];
    }

    public static function beginSetup(int $userId): void
    {
        $secret = random_bytes(20);
        $_SESSION['_2fa_setup'] = [
            'user' => $userId,
            'secret' => Security::seal($secret),
            'exp' => time() + self::SETUP_TTL,
        ];
        unset($_SESSION['_2fa_recovery']);
    }

    public static function cancelSetup(): void
    {
        unset($_SESSION['_2fa_setup']);
    }

    /** Confirms the pending authenticator secret and returns the one-time recovery codes. */
    public static function confirmSetup(int $userId, string $code): ?string
    {
        $pending = $_SESSION['_2fa_setup'] ?? null;
        if (!is_array($pending) || (int) ($pending['user'] ?? 0) !== $userId || (int) ($pending['exp'] ?? 0) < time()) {
            return 'That setup expired. Enter your password and start again.';
        }
        $secret = Security::unseal((string) ($pending['secret'] ?? ''));
        if (!is_string($secret) || strlen($secret) !== 20) {
            self::cancelSetup();
            return 'That setup expired. Enter your password and start again.';
        }
        $step = 0;
        if (!self::matches($secret, self::digits($code), 0, $step)) {
            return 'That code didn’t match the authenticator app. Wait for a new code and try again.';
        }

        $plain = [];
        $hashes = [];
        for ($i = 0; $i < self::RECOVERY_COUNT; $i++) {
            $plain[] = self::recoveryCode();
            $hashes[] = self::recoveryHash($plain[$i]);
        }
        $now = Database::now();
        $updated = Database::connection()->prepare(
            'UPDATE users SET totp_secret = :secret, totp_confirmed_at = :now, totp_last_step = :step, recovery_hashes = :hashes, updated_at = :updated
             WHERE id = :id AND totp_confirmed_at IS NULL'
        );
        $updated->execute([
            'secret' => Security::seal($secret),
            'now' => $now,
            'step' => $step,
            'hashes' => json_encode($hashes),
            'updated' => $now,
            'id' => $userId,
        ]);
        if ($updated->rowCount() !== 1) {
            self::cancelSetup();
            return 'Two-factor authentication is already on for this account.';
        }
        self::cancelSetup();
        $_SESSION['_2fa_recovery'] = ['user' => $userId, 'codes' => $plain, 'exp' => time() + self::RECOVERY_TTL];
        return null;
    }

    public static function dismissRecovery(int $userId): void
    {
        $pending = $_SESSION['_2fa_recovery'] ?? null;
        if (is_array($pending) && (int) ($pending['user'] ?? 0) === $userId) {
            unset($_SESSION['_2fa_recovery']);
        }
    }

    /** Replaces recovery codes after a valid password-side check. Returns an error, or null. */
    public static function replaceRecovery(int $userId, string $code): ?string
    {
        if (!self::accept($userId, $code)) {
            return 'That code didn’t match. Enter the current code from your authenticator app, or a recovery code.';
        }
        $plain = [];
        $hashes = [];
        for ($i = 0; $i < self::RECOVERY_COUNT; $i++) {
            $plain[] = self::recoveryCode();
            $hashes[] = self::recoveryHash($plain[$i]);
        }
        Database::connection()->prepare('UPDATE users SET recovery_hashes = :hashes, updated_at = :updated WHERE id = :id AND totp_confirmed_at IS NOT NULL')
            ->execute(['hashes' => json_encode($hashes), 'updated' => Database::now(), 'id' => $userId]);
        $_SESSION['_2fa_recovery'] = ['user' => $userId, 'codes' => $plain, 'exp' => time() + self::RECOVERY_TTL];
        return null;
    }

    public static function disable(int $userId, string $code): ?string
    {
        if (!self::accept($userId, $code)) {
            return 'That code didn’t match. Enter the current code from your authenticator app, or a recovery code.';
        }
        $now = Database::now();
        Database::connection()->prepare(
            'UPDATE users SET totp_secret = NULL, totp_confirmed_at = NULL, totp_last_step = NULL, recovery_hashes = NULL, updated_at = :updated WHERE id = :id'
        )->execute(['updated' => $now, 'id' => $userId]);
        self::cancelSetup();
        self::dismissRecovery($userId);
        return null;
    }

    /** @param array{id:int,email:string,next:?string,rehash:?string} $pending */
    public static function rememberLogin(array $pending): void
    {
        session_regenerate_id(true);
        $_SESSION['_2fa_login'] = $pending + ['exp' => time() + self::LOGIN_TTL, 'tries' => 0];
    }

    /** @return array{id:int,email:string,next:?string,rehash:?string}|null */
    public static function pendingLogin(): ?array
    {
        $pending = $_SESSION['_2fa_login'] ?? null;
        if (!is_array($pending) || (int) ($pending['exp'] ?? 0) < time() || (int) ($pending['id'] ?? 0) < 1) {
            unset($_SESSION['_2fa_login']);
            return null;
        }
        return [
            'id' => (int) $pending['id'],
            'email' => (string) ($pending['email'] ?? ''),
            'next' => isset($pending['next']) ? (string) $pending['next'] : null,
            'rehash' => isset($pending['rehash']) ? (string) $pending['rehash'] : null,
        ];
    }

    public static function clearLogin(): void
    {
        unset($_SESSION['_2fa_login']);
    }

    public static function tooManyAttempts(string $action, string $fingerprint): bool
    {
        return RateLimiter::tooManyAttempts($action, $fingerprint, self::ATTEMPT_MAX, self::ATTEMPT_WINDOW);
    }

    public static function hit(string $action, string $fingerprint): void
    {
        RateLimiter::hit($action, $fingerprint);
    }

    public static function passwordOk(int $userId, string $password): bool
    {
        $stmt = Database::connection()->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $hash = $stmt->fetchColumn();
        $dummy = is_string($hash) ? $hash : Auth::hash(bin2hex(random_bytes(8)));
        return password_verify($password, $dummy) && is_string($hash);
    }

    /** @return array{secret:string,grouped:string,svg:string}|null */
    private static function pendingSetup(int $userId): ?array
    {
        $pending = $_SESSION['_2fa_setup'] ?? null;
        if (!is_array($pending) || (int) ($pending['user'] ?? 0) !== $userId || (int) ($pending['exp'] ?? 0) < time()) {
            if (is_array($pending)) {
                unset($_SESSION['_2fa_setup']);
            }
            return null;
        }
        $secret = Security::unseal((string) ($pending['secret'] ?? ''));
        if (!is_string($secret) || strlen($secret) !== 20) {
            unset($_SESSION['_2fa_setup']);
            return null;
        }
        $user = Auth::user();
        $email = (string) ($user['email'] ?? 'account');
        $encoded = self::base32($secret);
        $uri = 'otpauth://totp/' . rawurlencode('atwebtech') . ':' . rawurlencode($email)
            . '?secret=' . $encoded . '&issuer=' . rawurlencode('atwebtech') . '&algorithm=SHA1&digits=6&period=30';
        try {
            $svg = QrCode::svg($uri);
        } catch (Throwable $e) {
            error_log(sprintf('[%s] qr failed: %s', gmdate('c'), $e->getMessage()));
            $svg = '';
        }
        return ['secret' => $encoded, 'grouped' => trim(chunk_split($encoded, 4, ' ')), 'svg' => $svg];
    }

    /** @return list<string>|null */
    private static function pendingRecovery(int $userId): ?array
    {
        $pending = $_SESSION['_2fa_recovery'] ?? null;
        if (!is_array($pending) || (int) ($pending['user'] ?? 0) !== $userId || (int) ($pending['exp'] ?? 0) < time()) {
            if (is_array($pending)) {
                unset($_SESSION['_2fa_recovery']);
            }
            return null;
        }
        $codes = array_values(array_filter((array) ($pending['codes'] ?? []), 'is_string'));
        return $codes === [] ? null : $codes;
    }

    /** @return array{totp_secret:?string,totp_confirmed_at:?string,totp_last_step:?string,recovery_hashes:?string}|null */
    private static function record(int $userId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT totp_secret, totp_confirmed_at, totp_last_step, recovery_hashes FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function acceptLogin(int $userId, string $code): bool
    {
        return self::accept($userId, $code);
    }

    private static function accept(int $userId, string $code): bool
    {
        $code = strtoupper((string) preg_replace('/[\s-]+/', '', $code));
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $lock = Database::driver() === 'mysql' ? ' FOR UPDATE' : '';
            $stmt = $pdo->prepare('SELECT totp_secret, totp_confirmed_at, totp_last_step, recovery_hashes FROM users WHERE id = :id' . $lock);
            $stmt->execute(['id' => $userId]);
            $row = $stmt->fetch();
            if (!$row || $row['totp_confirmed_at'] === null) {
                $pdo->rollBack();
                return false;
            }
            $secret = Security::unseal((string) $row['totp_secret']);
            $ok = false;
            if (preg_match('/^\d{6}$/', $code) && is_string($secret)) {
                $step = 0;
                if (self::matches($secret, $code, (int) $row['totp_last_step'], $step)) {
                    $upd = $pdo->prepare('UPDATE users SET totp_last_step = :step WHERE id = :id AND (totp_last_step IS NULL OR totp_last_step < :prior)');
                    $upd->execute(['step' => $step, 'prior' => $step, 'id' => $userId]);
                    $ok = $upd->rowCount() === 1;
                }
            } elseif (preg_match('/^[A-Z2-9]{10}$/', $code)) {
                $hashes = json_decode((string) $row['recovery_hashes'], true);
                $target = self::recoveryHash($code);
                $next = [];
                $found = false;
                foreach (is_array($hashes) ? $hashes : [] as $hash) {
                    if (!$found && is_string($hash) && hash_equals($hash, $target)) {
                        $found = true;
                        continue;
                    }
                    $next[] = $hash;
                }
                if ($found) {
                    $pdo->prepare('UPDATE users SET recovery_hashes = :hashes, updated_at = :updated WHERE id = :id')
                        ->execute(['hashes' => json_encode(array_values($next)), 'updated' => Database::now(), 'id' => $userId]);
                    $ok = true;
                }
            }
            if ($ok) {
                $pdo->commit();
            } else {
                $pdo->rollBack();
            }
            return $ok;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function matches(string $secret, string $code, int $lastStep, int &$usedStep): bool
    {
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $step = intdiv(time(), 30);
        for ($i = -1; $i <= 1; $i++) {
            $candidate = $step + $i;
            if ($candidate <= $lastStep) {
                continue;
            }
            if (hash_equals(self::codeAt($secret, $candidate), $code)) {
                $usedStep = $candidate;
                return true;
            }
        }
        return false;
    }

    public static function codeAt(string $secret, int $step): string
    {
        $counter = pack('N2', ($step >> 32) & 0xFFFFFFFF, $step & 0xFFFFFFFF);
        $hash = hash_hmac('sha1', $counter, $secret, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);
        return str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public static function base32(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $length = strlen($bytes);
        for ($i = 0; $i < $length; $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $out;
    }

    private static function digits(string $code): string
    {
        return (string) preg_replace('/\D+/', '', $code);
    }

    private static function recoveryCode(): string
    {
        $raw = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < 10; $i++) {
            $raw .= self::ALPHABET[random_int(0, $max)];
        }
        return substr($raw, 0, 5) . '-' . substr($raw, 5);
    }

    private static function recoveryHash(string $code): string
    {
        $normalized = strtoupper((string) preg_replace('/[\s-]+/', '', $code));
        return Security::keyedHash('recovery:' . $normalized);
    }
}
