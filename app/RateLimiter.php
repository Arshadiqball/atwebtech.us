<?php

declare(strict_types=1);

final class RateLimiter
{
    public static function tooManyAttempts(string $action, string $fingerprint, ?int $max = null, ?int $windowMinutes = null): bool
    {
        return self::attempts($action, $fingerprint, $windowMinutes) >= ($max ?? (int) config('forms.rate_limit_max'));
    }

    public static function attempts(string $action, string $fingerprint, ?int $windowMinutes = null): int
    {
        $window = $windowMinutes ?? (int) config('forms.rate_limit_window');
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM form_attempts WHERE action = :action AND fingerprint = :fp AND created_at >= :since'
        );
        $stmt->execute(['action' => $action, 'fp' => $fingerprint, 'since' => gmdate('Y-m-d H:i:s', time() - $window * 60)]);
        return (int) $stmt->fetchColumn();
    }

    public static function hit(string $action, string $fingerprint): void
    {
        $pdo = Database::connection();
        $pdo->prepare('INSERT INTO form_attempts (action, fingerprint, created_at) VALUES (:action, :fp, :now)')
            ->execute(['action' => $action, 'fp' => $fingerprint, 'now' => Database::now()]);

        if (random_int(1, 50) === 1) {
            $pdo->prepare('DELETE FROM form_attempts WHERE created_at < :cutoff')
                ->execute(['cutoff' => gmdate('Y-m-d H:i:s', time() - 86400)]);
        }
    }
}
