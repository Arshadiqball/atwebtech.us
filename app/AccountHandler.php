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
            $campaign = Campaign::get();
            $pdo->prepare(
                'INSERT INTO users (email, password_hash, display_name, theme, created_at, updated_at, last_login_at, utm_source, utm_medium, utm_campaign, utm_content, utm_term, click_kind, click_id)
                 VALUES (:email, :hash, :name, :theme, :created, :updated, :login, :utm_source, :utm_medium, :utm_campaign, :utm_content, :utm_term, :click_kind, :click_id)'
            )->execute([
                'email' => $input['email'],
                'hash' => Auth::hash($password),
                'name' => mb_substr(strstr($input['email'], '@', true) ?: $input['email'], 0, 100),
                'theme' => 'light',
                'created' => $now,
                'updated' => $now,
                'login' => $now,
                'utm_source' => $campaign['utm_source'],
                'utm_medium' => $campaign['utm_medium'],
                'utm_campaign' => $campaign['utm_campaign'],
                'utm_content' => $campaign['utm_content'],
                'utm_term' => $campaign['utm_term'],
                'click_kind' => $campaign['click_kind'],
                'click_id' => $campaign['click_id'],
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
        Mailer::notify('New account', ['Email' => $input['email'], 'Campaign' => Campaign::label()]);
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
            $stmt = $pdo->prepare('SELECT id, password_hash, totp_confirmed_at FROM users WHERE email = :email');
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

            $rehash = password_needs_rehash($user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)
                ? Auth::hash($password) : null;
            if ($user['totp_confirmed_at'] !== null) {
                TwoFactor::rememberLogin([
                    'id' => (int) $user['id'],
                    'email' => $input['email'],
                    'next' => $next,
                    'rehash' => $rehash,
                ]);
                redirect('/login/two-factor');
            }
        } catch (PDOException $e) {
            error_log(sprintf('[%s] login failed: %s', gmdate('c'), $e->getMessage()));
            self::fail('login', $back, "We couldn't log you in right now. Please try again in a few minutes.", [], $input);
        }

        self::finishLogin((int) $user['id'], $next, $rehash);
    }

    public static function twoFactorLogin(): never
    {
        $pending = TwoFactor::pendingLogin();
        $back = '/login/two-factor';
        if ($pending === null) {
            flash('form', ['form' => 'login', 'status' => 'error', 'message' => 'That sign-in expired. Enter your email and password again.', 'errors' => [], 'old' => []]);
            redirect('/login');
        }
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            self::fail('two-factor', $back, 'Your session expired. Refresh the page and try again.', [], []);
        }
        $code = trim((string) ($_POST['code'] ?? ''));
        $fingerprint = Security::keyedHash('tfa-login:' . $pending['id']);
        $ipKey = Security::clientFingerprint();
        if (TwoFactor::tooManyAttempts('tfa-login', $fingerprint) || TwoFactor::tooManyAttempts('tfa-login', $ipKey)) {
            TwoFactor::clearLogin();
            flash('form', ['form' => 'login', 'status' => 'error', 'message' => 'Too many failed attempts. Please wait 15 minutes and try again.', 'errors' => [], 'old' => ['email' => $pending['email']]]);
            redirect('/login');
        }
        if ($code === '') {
            self::fail('two-factor', $back, 'Enter the code from your authenticator app, or a recovery code.', ['code' => 'Enter your authentication code.'], []);
        }
        try {
            $ok = TwoFactor::acceptLogin((int) $pending['id'], $code);
        } catch (PDOException $e) {
            error_log(sprintf('[%s] two-factor login failed: %s', gmdate('c'), $e->getMessage()));
            self::fail('two-factor', $back, "We couldn't check that code right now. Please try again in a few minutes.", [], []);
        }
        if (!$ok) {
            TwoFactor::hit('tfa-login', $fingerprint);
            TwoFactor::hit('tfa-login', $ipKey);
            self::fail('two-factor', $back, 'That code didn’t match. Try the next code from your app, or a recovery code.', ['code' => 'That code didn’t match.'], []);
        }
        TwoFactor::clearLogin();
        self::finishLogin((int) $pending['id'], $pending['next'], $pending['rehash']);
    }

    public static function twoFactorStart(): never
    {
        $user = self::member();
        $back = '/app/account';
        if (TwoFactor::pageState((int) $user['id'])['enabled']) {
            redirect($back);
        }
        $password = (string) ($_POST['password'] ?? '');
        $fingerprint = Security::keyedHash('tfa-setup:' . $user['id']);
        if (TwoFactor::tooManyAttempts('tfa-setup', $fingerprint)) {
            self::fail('tfa-start', $back, 'Too many attempts. Please wait 15 minutes and try again.', [], []);
        }
        if ($password === '') {
            self::fail('tfa-start', $back, 'Enter your password to set up two-factor authentication.', ['password' => 'Enter your password.'], []);
        }
        if (!TwoFactor::passwordOk((int) $user['id'], $password)) {
            TwoFactor::hit('tfa-setup', $fingerprint);
            self::fail('tfa-start', $back, 'That password is incorrect.', ['password' => 'That password is incorrect.'], []);
        }
        TwoFactor::beginSetup((int) $user['id']);
        redirect($back);
    }

    public static function twoFactorCancel(): never
    {
        self::member();
        TwoFactor::cancelSetup();
        redirect('/app/account');
    }

    public static function twoFactorConfirm(): never
    {
        $user = self::member();
        $back = '/app/account';
        $code = trim((string) ($_POST['code'] ?? ''));
        $fingerprint = Security::keyedHash('tfa-confirm:' . $user['id']);
        if (TwoFactor::tooManyAttempts('tfa-confirm', $fingerprint)) {
            self::fail('tfa-confirm', $back, 'Too many attempts. Please wait 15 minutes and try again.', [], []);
        }
        if ($code === '') {
            self::fail('tfa-confirm', $back, 'Enter the 6-digit code from your authenticator app.', ['code' => 'Enter the 6-digit code.'], []);
        }
        try {
            $error = TwoFactor::confirmSetup((int) $user['id'], $code);
        } catch (PDOException $e) {
            error_log(sprintf('[%s] two-factor confirm failed: %s', gmdate('c'), $e->getMessage()));
            self::fail('tfa-confirm', $back, "We couldn't turn on two-factor authentication right now. Please try again.", [], []);
        }
        if ($error !== null) {
            TwoFactor::hit('tfa-confirm', $fingerprint);
            self::fail('tfa-confirm', $back, $error, ['code' => $error], []);
        }
        redirect($back);
    }

    public static function twoFactorRecoveryDone(): never
    {
        $user = self::member();
        TwoFactor::dismissRecovery((int) $user['id']);
        redirect('/app/account');
    }

    public static function twoFactorRecovery(): never
    {
        $user = self::member();
        self::guardedChange((int) $user['id'], 'tfa-recovery', [TwoFactor::class, 'replaceRecovery']);
    }

    public static function twoFactorDisable(): never
    {
        $user = self::member();
        self::guardedChange((int) $user['id'], 'tfa-disable', [TwoFactor::class, 'disable']);
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

    private static function finishLogin(int $userId, ?string $next, ?string $rehash): never
    {
        try {
            $update = ['id' => $userId, 'login' => Database::now()];
            $sql = 'UPDATE users SET last_login_at = :login';
            if ($rehash !== null && $rehash !== '') {
                $sql .= ', password_hash = :hash';
                $update['hash'] = $rehash;
            }
            Database::connection()->prepare($sql . ' WHERE id = :id')->execute($update);
        } catch (PDOException $e) {
            error_log(sprintf('[%s] login finish failed: %s', gmdate('c'), $e->getMessage()));
            flash('form', ['form' => 'login', 'status' => 'error', 'message' => "We couldn't log you in right now. Please try again in a few minutes.", 'errors' => [], 'old' => []]);
            redirect('/login');
        }
        Auth::login($userId);
        redirect($next ?? '/app');
    }

    /** @param callable(int, string): ?string $action */
    private static function guardedChange(int $userId, string $form, callable $action): never
    {
        $back = '/app/account';
        $password = (string) ($_POST['password'] ?? '');
        $code = trim((string) ($_POST['code'] ?? ''));
        $fingerprint = Security::keyedHash($form . ':' . $userId);
        if (TwoFactor::tooManyAttempts($form, $fingerprint)) {
            self::fail($form, $back, 'Too many attempts. Please wait 15 minutes and try again.', [], []);
        }
        $errors = array_filter([
            'password' => $password === '' ? 'Enter your password.' : null,
            'code' => $code === '' ? 'Enter an authentication code.' : null,
        ]);
        if ($errors) {
            self::fail($form, $back, 'Enter your password and a current code.', $errors, []);
        }
        if (!TwoFactor::passwordOk($userId, $password)) {
            TwoFactor::hit($form, $fingerprint);
            self::fail($form, $back, 'That password is incorrect.', ['password' => 'That password is incorrect.'], []);
        }
        try {
            $error = $action($userId, $code);
        } catch (PDOException $e) {
            error_log(sprintf('[%s] two-factor update failed: %s', gmdate('c'), $e->getMessage()));
            self::fail($form, $back, "We couldn't update two-factor authentication right now. Please try again.", [], []);
        }
        if ($error !== null) {
            TwoFactor::hit($form, $fingerprint);
            self::fail($form, $back, $error, ['code' => $error], []);
        }
        redirect($back);
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
