<?php

declare(strict_types=1);

final class FormHandler
{
    public const WAITLIST_ROLES = [
        'creator' => 'Creator',
        'small-business' => 'Small business',
        'agency' => 'Agency',
        'marketing-team' => 'Marketing team',
        'other' => 'Something else',
    ];

    public const CONTACT_TOPICS = [
        'general' => 'General question',
        'product' => 'Product feedback',
        'partnership' => 'Partnership',
        'press' => 'Press',
        'other' => 'Something else',
    ];

    private const SOURCES = ['hero', 'final-cta', 'pricing', 'feature', 'solution', 'resources', 'demo', 'contact', 'page'];

    public static function waitlist(): never
    {
        $formId = self::formId();
        $input = [
            'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
            'role' => (string) ($_POST['role'] ?? ''),
            'plan' => (string) ($_POST['plan'] ?? ''),
        ];
        $plans = data('pricing')['plans'];
        $billing = isset($_POST['billing']) ? Pricing::billing($_POST['billing']) : null;
        $channels = isset($_POST['channels']) ? Pricing::clampChannels($_POST['channels']) : null;
        $source = in_array($_POST['source'] ?? '', self::SOURCES, true) ? $_POST['source'] : 'page';

        self::guard('waitlist', $formId, $input);

        $errors = [];
        if (!self::validEmail($input['email'])) {
            $errors['email'] = 'Enter a valid email address, like name@example.com.';
        }
        if ($input['role'] !== '' && !isset(self::WAITLIST_ROLES[$input['role']])) {
            $errors['role'] = 'Choose one of the listed options.';
        }
        if ($input['plan'] !== '' && !isset($plans[$input['plan']])) {
            $errors['plan'] = 'Choose one of the listed plans.';
        }
        if ($errors) {
            self::respond($formId, 'error', 'Please check the highlighted field.', 422, $errors, $input);
        }

        if ($input['plan'] === '') {
            $billing = $channels = null;
        } elseif ($input['plan'] === 'free') {
            $billing = null;
        }

        self::persist('waitlist', $formId, $input, static function (PDO $pdo) use ($input, $source, $billing, $channels): void {
            $now = Database::now();
            $params = [
                'email' => $input['email'],
                'role' => $input['role'] ?: null,
                'plan' => $input['plan'] ?: null,
                'billing' => $billing,
                'channels' => $channels,
                'source' => $source,
                'fp' => Security::clientFingerprint(),
                'ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'created' => $now,
                'updated' => $now,
            ];
            try {
                $pdo->prepare(
                    'INSERT INTO waitlist_signups (email, role, plan, billing, channels, source, fingerprint, user_agent, created_at, updated_at)
                     VALUES (:email, :role, :plan, :billing, :channels, :source, :fp, :ua, :created, :updated)'
                )->execute($params);
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                $update = 'UPDATE waitlist_signups SET updated_at = :updated, role = COALESCE(:role, role)';
                $values = ['updated' => $now, 'role' => $params['role'], 'email' => $input['email']];
                if ($params['plan'] !== null) {
                    $update .= ', plan = :plan, billing = :billing, channels = :channels';
                    $values += ['plan' => $params['plan'], 'billing' => $billing, 'channels' => $channels];
                }
                $pdo->prepare($update . ' WHERE email = :email')->execute($values);
            }
        });

        Mailer::notify('New waitlist signup', [
            'Email' => $input['email'],
            'Role' => self::WAITLIST_ROLES[$input['role']] ?? 'Not provided',
            'Plan' => $input['plan'] !== '' ? $plans[$input['plan']]['name'] . ' (' . ($channels ?? '?') . ' channels' . ($billing ? ", {$billing}" : '') . ')' : 'Not provided',
            'Source' => $source,
        ]);

        self::respond($formId, 'success', "You're on the list. We'll email you with atwebtech product and early-access news.");
    }

    public static function contact(): never
    {
        $formId = self::formId();
        $input = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
            'topic' => (string) ($_POST['topic'] ?? ''),
            'message' => trim((string) ($_POST['message'] ?? '')),
        ];

        self::guard('contact', $formId, $input);

        $errors = [];
        $nameLength = mb_strlen($input['name']);
        if ($nameLength < 2 || $nameLength > 100) {
            $errors['name'] = 'Enter your name (2 to 100 characters).';
        }
        if (!self::validEmail($input['email'])) {
            $errors['email'] = 'Enter a valid email address, like name@example.com.';
        }
        if (!isset(self::CONTACT_TOPICS[$input['topic']])) {
            $errors['topic'] = 'Choose a topic.';
        }
        $messageLength = mb_strlen($input['message']);
        if ($messageLength < 10 || $messageLength > 4000) {
            $errors['message'] = 'Write a message between 10 and 4,000 characters.';
        }
        if ($errors) {
            self::respond($formId, 'error', 'Please check the highlighted fields.', 422, $errors, $input);
        }

        self::persist('contact', $formId, $input, static function (PDO $pdo) use ($input): void {
            $pdo->prepare(
                'INSERT INTO enquiries (name, email, topic, message, fingerprint, user_agent, created_at)
                 VALUES (:name, :email, :topic, :message, :fp, :ua, :now)'
            )->execute([
                'name' => $input['name'],
                'email' => $input['email'],
                'topic' => $input['topic'],
                'message' => $input['message'],
                'fp' => Security::clientFingerprint(),
                'ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'now' => Database::now(),
            ]);
        });

        Mailer::notify('New enquiry: ' . self::CONTACT_TOPICS[$input['topic']], [
            'Name' => $input['name'],
            'Email' => $input['email'],
            'Topic' => self::CONTACT_TOPICS[$input['topic']],
            'Message' => $input['message'],
        ]);

        self::respond($formId, 'success', 'Thanks, your message has been saved. The atwebtech team will reply by email.');
    }

    private static function guard(string $action, string $formId, array $input): void
    {
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            self::respond($formId, 'error', 'Your session expired. Refresh the page and try again.', 419, [], $input);
        }
        $age = Security::formAgeSeconds($_POST['_ts'] ?? null);
        if (Security::honeypotFilled() || $age === null || $age < (int) config('forms.min_seconds')) {
            self::respond($formId, 'error', "We couldn't accept that submission. Wait a moment and try again.", 400, [], $input + ['_ts' => (string) ($_POST['_ts'] ?? '')]);
        }
    }

    private static function persist(string $action, string $formId, array $input, callable $write): void
    {
        $fingerprint = Security::clientFingerprint();
        try {
            if (RateLimiter::tooManyAttempts($action, $fingerprint)) {
                self::respond($formId, 'error', 'Too many attempts from this connection. Please wait a few minutes and try again.', 429, [], $input);
            }
            RateLimiter::hit($action, $fingerprint);
            $write(Database::connection());
        } catch (Throwable $e) {
            error_log(sprintf('[%s] %s form failed: %s', gmdate('c'), $action, $e->getMessage()));
            self::respond($formId, 'error', "We couldn't save your details right now. Please try again in a few minutes.", 503, [], $input);
        }
    }

    private static function validEmail(string $email): bool
    {
        return $email !== '' && mb_strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private static function formId(): string
    {
        $id = (string) ($_POST['form_id'] ?? 'form');
        return preg_match('/^[a-z0-9-]{1,40}$/', $id) ? $id : 'form';
    }

    private static function respond(string $formId, string $status, string $message, int $code = 200, array $errors = [], array $old = []): never
    {
        if (wants_json()) {
            json_response(['status' => $status, 'message' => $message, 'errors' => (object) $errors], $code);
        }
        flash('form', [
            'form' => $formId,
            'status' => $status,
            'message' => $message,
            'errors' => $errors,
            'old' => $status === 'success' ? [] : $old,
        ]);
        $return = strtok(safe_return_path($_POST['return'] ?? '/'), '#');
        redirect($return . '#' . $formId);
    }
}
