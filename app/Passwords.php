<?php

declare(strict_types=1);

/**
 * Password reset by email, and changing the password or name from Account.
 * Reset links work once, for 60 minutes; only a hash of the token is stored. Changing a password signs out
 * every other session of that account (Auth compares users.password_changed_at with the session's copy).
 */
final class Passwords
{
    private const LINK_MINUTES = 60;

    /** POST /forgot */
    public static function request(): never
    {
        $back = '/forgot';
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            self::fail('forgot', $back, 'Your session expired. Refresh the page and try again.', ['email' => $email]);
        }
        if ($email === '' || mb_strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            self::fail('forgot', $back, 'Enter the email address you signed up with.', ['email' => $email]);
        }
        if (!config('mail.enabled')) {
            error_log(sprintf('[%s] password reset asked but mail is not enabled', gmdate('c')));
            self::fail('forgot', $back, 'Password reset emails aren’t available right now. Contact the atwebtech team and we’ll help you get back in.', ['email' => $email]);
        }
        $ip = Security::clientFingerprint();
        $emailKey = Security::keyedHash('reset:' . $email);
        if (RateLimiter::tooManyAttempts('pw-reset', $ip, 5, 60) || RateLimiter::tooManyAttempts('pw-reset-email', $emailKey, 3, 60)) {
            self::fail('forgot', $back, 'Too many reset requests. Please wait an hour and try again.', ['email' => $email]);
        }
        RateLimiter::hit('pw-reset', $ip);
        RateLimiter::hit('pw-reset-email', $emailKey);

        $pdo = Database::connection();
        // Client workspaces have no login of their own.
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND parent_id IS NULL');
        $stmt->execute(['email' => $email]);
        $uid = (int) $stmt->fetchColumn();
        if ($uid > 0) {
            $token = bin2hex(random_bytes(24));
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = :uid OR expires_at < :now')->execute(['uid' => $uid, 'now' => Database::now()]);
            $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (:uid, :hash, :exp, :now)')
                ->execute(['uid' => $uid, 'hash' => hash('sha256', $token), 'exp' => gmdate('Y-m-d H:i:s', time() + self::LINK_MINUTES * 60), 'now' => Database::now()]);
            $mail = EmailTemplate::passwordReset(absolute_url('/reset/' . $token), self::LINK_MINUTES);
            Mailer::send($email, $mail['subject'], $mail['html'], $mail['text']);
        }
        // The same answer whether or not the address has an account.
        flash('form', ['form' => 'forgot', 'status' => 'success', 'message' => 'If an account uses that address, a reset link is on its way. It works for ' . self::LINK_MINUTES . ' minutes. Check your spam folder too.', 'errors' => [], 'old' => []]);
        redirect($back);
    }

    /** GET /reset/{token} */
    public static function form(string $token): never
    {
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow');
        View::render('password', ['mode' => self::find($token) ? 'reset' : 'expired', 'token' => $token], [
            'title' => 'Choose a new password | atwebtech', 'description' => 'Choose a new password for your atwebtech account.', 'layout' => 'app', 'noindex' => true,
        ]);
    }

    /** POST /reset/{token} */
    public static function reset(string $token): never
    {
        $back = '/reset/' . $token;
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            self::fail('reset', $back, 'Your session expired. Refresh the page and try again.');
        }
        $row = self::find($token);
        if ($row === null) {
            redirect($back);
        }
        $password = (string) ($_POST['password'] ?? '');
        if (($problem = Auth::passwordProblem($password)) !== null) {
            self::fail('reset', $back, $problem);
        }
        $pdo = Database::connection();
        $now = Database::now();
        $pdo->prepare('UPDATE users SET password_hash = :hash, password_changed_at = :at, updated_at = :now WHERE id = :id')
            ->execute(['hash' => Auth::hash($password), 'at' => $now, 'now' => $now, 'id' => (int) $row['user_id']]);
        $pdo->prepare('DELETE FROM password_resets WHERE user_id = :uid')->execute(['uid' => (int) $row['user_id']]);
        Auth::logout();
        flash('form', ['form' => 'login', 'status' => 'success', 'message' => 'Your password was changed. Log in with the new one.', 'errors' => [], 'old' => []]);
        redirect('/login');
    }

    /** POST /app/account/password */
    public static function change(): never
    {
        $person = Auth::member();
        $uid = (int) $person['id'];
        $back = '/app/account#password';
        if (RateLimiter::tooManyAttempts('pw-change', 'user:' . $uid, 8, 15)) {
            self::notice($back, 'Too many attempts. Please wait 15 minutes and try again.');
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute(['id' => $uid]);
        if (!password_verify((string) ($_POST['current'] ?? ''), (string) $stmt->fetchColumn())) {
            RateLimiter::hit('pw-change', 'user:' . $uid);
            self::notice($back, 'Your current password isn’t right. Nothing was changed.');
        }
        $password = (string) ($_POST['password'] ?? '');
        if (($problem = Auth::passwordProblem($password)) !== null) {
            self::notice($back, 'New password: ' . $problem);
        }
        $now = Database::now();
        $pdo->prepare('UPDATE users SET password_hash = :hash, password_changed_at = :at, updated_at = :now WHERE id = :id')
            ->execute(['hash' => Auth::hash($password), 'at' => $now, 'now' => $now, 'id' => $uid]);
        $pdo->prepare('DELETE FROM password_resets WHERE user_id = :uid')->execute(['uid' => $uid]);
        $_SESSION['_pwat'] = $now; // Keep this session; every other one is signed out.
        self::notice($back, 'Password changed. Other devices were signed out.');
    }

    /** POST /app/account/profile */
    public static function profile(): never
    {
        $person = Auth::member();
        $name = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['display_name'] ?? '')))), 0, 60);
        if ($name === '') {
            self::notice('/app/account', 'Enter a name.');
        }
        Database::connection()->prepare('UPDATE users SET display_name = :n, updated_at = :now WHERE id = :id')->execute(['n' => $name, 'now' => Database::now(), 'id' => (int) $person['id']]);
        self::notice('/app/account', 'Name saved.');
    }

    private static function find(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }
        $stmt = Database::connection()->prepare('SELECT * FROM password_resets WHERE token_hash = :h AND expires_at > :now');
        $stmt->execute(['h' => hash('sha256', $token), 'now' => Database::now()]);
        return $stmt->fetch() ?: null;
    }

    private static function fail(string $form, string $back, string $message, array $old = []): never
    {
        flash('form', ['form' => $form, 'status' => 'error', 'message' => $message, 'errors' => [], 'old' => $old]);
        redirect($back);
    }

    private static function notice(string $back, string $message): never
    {
        flash('notice', $message);
        redirect($back);
    }
}
