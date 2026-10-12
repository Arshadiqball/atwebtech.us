<?php

declare(strict_types=1);

final class Auth
{
    public const PASSWORD_MIN = 8;
    public const PASSWORD_MAX_BYTES = 72;

    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        $id = $_SESSION['_uid'] ?? null;
        if (!is_int($id)) {
            return null;
        }
        try {
            $stmt = Database::connection()->prepare('SELECT id, email, display_name, theme, timezone, workspace_seeded_at, created_at FROM users WHERE id = :id');
            $stmt->execute(['id' => $id]);
            self::$user = $stmt->fetch() ?: null;
        } catch (Throwable $e) {
            error_log(sprintf('[%s] auth lookup failed: %s', gmdate('c'), $e->getMessage()));
            self::$user = null;
        }
        if (self::$user === null) {
            unset($_SESSION['_uid']);
        }
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['_uid'] = $userId;
        unset($_SESSION['_csrf']);
        self::$loaded = false;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        self::$user = null;
        self::$loaded = true;
    }

    /** Redirects guests to the login page, remembering where they were going. */
    public static function require(): array
    {
        $user = self::user();
        if ($user === null) {
            redirect('/login?next=' . rawurlencode(current_path()));
        }
        return $user;
    }

    /** Logged-in user for a state-changing request, after the CSRF check. */
    public static function member(): array
    {
        $user = self::user();
        if ($user === null) {
            wants_json() ? json_response(['status' => 'error', 'message' => 'Log in to continue.'], 401) : redirect('/login');
        }
        if (!Security::verifyCsrf($_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) {
            wants_json() ? json_response(['status' => 'error', 'message' => 'Your session expired. Refresh the page and try again.'], 419) : redirect(current_path() === '/welcome' ? '/welcome' : '/app');
        }
        return $user;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
    }

    /** @return string|null An error message, or null when the password meets every rule. */
    public static function passwordProblem(string $password): ?string
    {
        if (mb_strlen($password) < self::PASSWORD_MIN) {
            return 'Use at least 8 characters.';
        }
        if (strlen($password) > self::PASSWORD_MAX_BYTES) {
            return 'Use 72 characters or fewer.';
        }
        if (!preg_match('/\p{Lu}/u', $password)) {
            return 'Include at least 1 uppercase character.';
        }
        if (!preg_match('/[\p{N}\p{P}\p{S}]/u', $password)) {
            return 'Include at least 1 number or symbol.';
        }
        return null;
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }
        $allowed = array_filter(array_map(
            static fn (string $email): string => strtolower(trim($email)),
            explode(',', (string) config('admin.emails'))
        ));
        return in_array(strtolower((string) $user['email']), $allowed, true);
    }

    /** Logged-in admin, or a login redirect. Anyone else gets a not-found page. */
    public static function requireAdmin(): array
    {
        $user = self::require();
        if (!self::isAdmin()) {
            not_found();
        }
        return $user;
    }

    /** Only same-site paths inside the workspace or admin inbox are allowed as a post-login destination. */
    public static function safeNext(?string $next): ?string
    {
        $next = safe_return_path($next, '');
        return preg_match('#^/(app|welcome|admin)(/|$|\?)#', $next) ? $next : null;
    }

    public static function onboarding(int $userId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM user_onboarding WHERE user_id = :id');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
        $now = Database::now();
        $pdo->prepare('INSERT INTO user_onboarding (user_id, step, created_at, updated_at) VALUES (:id, 0, :created, :updated)')
            ->execute(['id' => $userId, 'created' => $now, 'updated' => $now]);
        return self::onboarding($userId);
    }
}
