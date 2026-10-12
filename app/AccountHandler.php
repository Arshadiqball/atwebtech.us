<?php

declare(strict_types=1);

final class AccountHandler
{
    private const LOGIN_MAX_FAILURES = 10;
    private const LOGIN_WINDOW_MINUTES = 15;
    private const THEMES = ['light', 'dark'];

    public static function signup(): never
    {
        $input = ['email' => strtolower(trim((string) ($_POST['email'] ?? '')))];
        $password = (string) ($_POST['password'] ?? '');
        $back = '/signup';

        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            self::fail('signup', $back, 'Your session expired. Refresh the page and try again.', [], $input);
        }
        $age = Security::formAgeSeconds($_POST['_ts'] ?? null);
        if (Security::honeypotFilled() || $age === null || $age < (int) config('forms.min_seconds')) {
            self::fail('signup', $back, "We couldn't accept that submission. Wait a moment and try again.", [], $input + ['_ts' => (string) ($_POST['_ts'] ?? '')]);
        }

        $errors = [];
        if (!self::validEmail($input['email'])) {
            $errors['email'] = 'Enter a valid email address, like name@example.com.';
        }
        if (($problem = Auth::passwordProblem($password)) !== null) {
            $errors['password'] = $problem;
        }
        if ($errors) {
            self::fail('signup', $back, 'Please check the highlighted fields.', $errors, $input);
        }

        $fingerprint = Security::clientFingerprint();
        try {
            if (RateLimiter::tooManyAttempts('signup', $fingerprint)) {
                self::fail('signup', $back, 'Too many attempts from this connection. Please wait a few minutes and try again.', [], $input);
            }
            RateLimiter::hit('signup', $fingerprint);

            $pdo = Database::connection();
            $exists = $pdo->prepare('SELECT 1 FROM users WHERE email = :email');
            $exists->execute(['email' => $input['email']]);
            if ($exists->fetchColumn()) {
                self::fail('signup', $back, 'An account with this email already exists.', ['email' => 'This email already has an account. Log in instead.'], $input);
            }

            $now = Database::now();
            $pdo->prepare(
                'INSERT INTO users (email, password_hash, display_name, theme, created_at, updated_at, last_login_at)
                 VALUES (:email, :hash, :name, :theme, :created, :updated, :login)'
            )->execute([
                'email' => $input['email'],
                'hash' => Auth::hash($password),
                'name' => mb_substr(strstr($input['email'], '@', true) ?: $input['email'], 0, 100),
                'theme' => 'light',
                'created' => $now,
                'updated' => $now,
                'login' => $now,
            ]);
            $userId = (int) $pdo->lastInsertId();
            Auth::onboarding($userId);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                self::fail('signup', $back, 'An account with this email already exists.', ['email' => 'This email already has an account. Log in instead.'], $input);
            }
            error_log(sprintf('[%s] signup failed: %s', gmdate('c'), $e->getMessage()));
            self::fail('signup', $back, "We couldn't create your account right now. Please try again in a few minutes.", [], $input);
        }

        Auth::login($userId);
        Mailer::notify('New account', ['Email' => $input['email']]);
        redirect('/welcome');
    }

    public static function login(): never
    {
        $input = ['email' => strtolower(trim((string) ($_POST['email'] ?? '')))];
        $password = (string) ($_POST['password'] ?? '');
        $next = Auth::safeNext($_POST['next'] ?? null);
        $back = '/login' . ($next ? '?next=' . rawurlencode($next) : '');

        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            self::fail('login', $back, 'Your session expired. Refresh the page and try again.', [], $input);
        }
        if ($input['email'] === '' || $password === '') {
            $errors = array_filter([
                'email' => $input['email'] === '' ? 'Enter your email address.' : null,
                'password' => $password === '' ? 'Enter your password.' : null,
            ]);
            self::fail('login', $back, 'Please check the highlighted fields.', $errors, $input);
        }

        $ipKey = Security::clientFingerprint();
        $emailKey = Security::keyedHash('login:' . $input['email']);
        try {
            if (RateLimiter::tooManyAttempts('login', $ipKey, self::LOGIN_MAX_FAILURES, self::LOGIN_WINDOW_MINUTES)
                || RateLimiter::tooManyAttempts('login-email', $emailKey, self::LOGIN_MAX_FAILURES, self::LOGIN_WINDOW_MINUTES)) {
                self::fail('login', $back, 'Too many failed attempts. Please wait 15 minutes and try again.', [], $input);
            }

            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = :email');
            $stmt->execute(['email' => $input['email']]);
            $user = $stmt->fetch();
            // Unknown emails still pay for one hash so response timing doesn't reveal which accounts exist.
            $hash = $user['password_hash'] ?? Auth::hash(bin2hex(random_bytes(8)));
            $valid = password_verify($password, $hash) && $user !== false;

            if (!$valid) {
                RateLimiter::hit('login', $ipKey);
                RateLimiter::hit('login-email', $emailKey);
                self::fail('login', $back, 'That email and password combination is incorrect.', [], $input);
            }

            $now = Database::now();
            $update = ['id' => $user['id'], 'login' => $now];
            $sql = 'UPDATE users SET last_login_at = :login';
            if (password_needs_rehash($user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
                $sql .= ', password_hash = :hash';
                $update['hash'] = Auth::hash($password);
            }
            $pdo->prepare($sql . ' WHERE id = :id')->execute($update);
        } catch (PDOException $e) {
            error_log(sprintf('[%s] login failed: %s', gmdate('c'), $e->getMessage()));
            self::fail('login', $back, "We couldn't log you in right now. Please try again in a few minutes.", [], $input);
        }

        Auth::login((int) $user['id']);
        redirect($next ?? '/app');
    }

    public static function logout(): never
    {
        if (Security::verifyCsrf($_POST['_token'] ?? null)) {
            Auth::logout();
        }
        redirect('/login');
    }

    public static function onboardingStep(): never
    {
        $user = self::member();
        $steps = data('onboarding')['steps'];
        $step = (int) ($_POST['step'] ?? 0);
        if (!isset($steps[$step])) {
            redirect('/welcome');
        }
        $config = $steps[$step];
        $allowed = !empty($config['channels']) ? array_column(integrations(), 'slug') : array_keys($config['options']);
        $skip = ($_POST['action'] ?? '') === 'skip';

        $value = null;
        if (!$skip) {
            $raw = $_POST['answer'] ?? ($config['multiple'] ? [] : '');
            $picked = array_values(array_unique(array_filter((array) $raw, static fn ($v) => is_string($v) && in_array($v, $allowed, true))));
            if (!$picked) {
                flash('form', ['form' => 'onboarding', 'status' => 'error', 'message' => 'Choose an option to continue, or skip this question.', 'errors' => [], 'old' => []]);
                redirect('/welcome?step=' . $step);
            }
            $value = $config['multiple'] ? implode(',', $picked) : $picked[0];
        }

        $last = $step === array_key_last($steps);
        $now = Database::now();
        Database::connection()->prepare(
            "UPDATE user_onboarding SET {$config['field']} = :value, step = :step, updated_at = :updated"
            . ($last ? ', completed_at = :completed' : '') . ' WHERE user_id = :id'
        )->execute(['value' => $value, 'step' => $step, 'updated' => $now, 'id' => $user['id']] + ($last ? ['completed' => $now] : []));

        redirect($last ? '/app' : '/welcome?step=' . ($step + 1));
    }

    public static function survey(): never
    {
        $user = self::member();
        $options = data('onboarding')['referral'];
        $now = Database::now();

        if (($_POST['action'] ?? '') === 'close') {
            Database::connection()->prepare('UPDATE user_onboarding SET survey_closed_at = :now, updated_at = :updated WHERE user_id = :id')
                ->execute(['now' => $now, 'updated' => $now, 'id' => $user['id']]);
            self::done('/app');
        }

        $choice = (string) ($_POST['referral'] ?? '');
        $other = trim((string) ($_POST['referral_other'] ?? ''));
        $error = null;
        if (!isset($options[$choice])) {
            $error = 'Choose one of the options.';
        } elseif ($choice === 'other' && (mb_strlen($other) < 2 || mb_strlen($other) > 200)) {
            $error = 'Tell us where you heard about atwebtech (2 to 200 characters).';
        }
        if ($error !== null) {
            if (wants_json()) {
                json_response(['status' => 'error', 'message' => $error], 422);
            }
            flash('form', ['form' => 'survey', 'status' => 'error', 'message' => $error, 'errors' => [], 'old' => ['referral' => $choice, 'referral_other' => $other]]);
            redirect('/app#survey');
        }

        Database::connection()->prepare(
            'UPDATE user_onboarding SET referral = :referral, referral_other = :other, survey_closed_at = :now, updated_at = :updated WHERE user_id = :id'
        )->execute(['referral' => $choice, 'other' => $choice === 'other' ? $other : null, 'now' => $now, 'updated' => $now, 'id' => $user['id']]);
        self::done('/app', 'Thanks for telling us.');
    }

    public static function closeChecklist(): never
    {
        $user = self::member();
        $now = Database::now();
        Database::connection()->prepare('UPDATE user_onboarding SET checklist_closed_at = :now, updated_at = :updated WHERE user_id = :id')
            ->execute(['now' => $now, 'updated' => $now, 'id' => $user['id']]);
        self::done('/app');
    }

    public static function theme(): never
    {
        $user = self::member();
        $theme = in_array($_POST['theme'] ?? '', self::THEMES, true) ? $_POST['theme'] : 'light';
        Database::connection()->prepare('UPDATE users SET theme = :theme, updated_at = :updated WHERE id = :id')
            ->execute(['theme' => $theme, 'updated' => Database::now(), 'id' => $user['id']]);
        self::done(safe_return_path($_POST['return'] ?? '/app', '/app'));
    }

    private static function member(): array
    {
        return Auth::member();
    }

    private static function done(string $location, string $message = 'Saved.'): never
    {
        if (wants_json()) {
            json_response(['status' => 'success', 'message' => $message]);
        }
        redirect($location);
    }

    private static function fail(string $formId, string $back, string $message, array $errors, array $old): never
    {
        flash('form', ['form' => $formId, 'status' => 'error', 'message' => $message, 'errors' => $errors, 'old' => $old]);
        redirect($back);
    }

    private static function validEmail(string $email): bool
    {
        return $email !== '' && mb_strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
