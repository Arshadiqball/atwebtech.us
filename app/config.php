<?php

declare(strict_types=1);

$env = (string) Env::get('APP_ENV', 'production');

return [
    'app' => [
        'name' => 'atwebtech',
        'env' => $env,
        'debug' => (bool) Env::get('APP_DEBUG', false) && $env !== 'production',
        'url' => rtrim((string) Env::get('APP_URL', 'https://atwebtech.us'), '/'),
        'key' => (string) Env::get('APP_KEY', ''),
        'is_production' => $env === 'production',
    ],
    'db' => [
        'driver' => (string) Env::get('DB_DRIVER', 'mysql'),
        'host' => (string) Env::get('DB_HOST', '127.0.0.1'),
        'port' => (int) Env::get('DB_PORT', 3306),
        'name' => (string) Env::get('DB_NAME', 'atwebtec_us'),
        'user' => (string) Env::get('DB_USER', ''),
        'pass' => (string) Env::get('DB_PASS', ''),
        'sqlite_path' => (string) Env::get('DB_SQLITE_PATH', 'storage/atwebtech.sqlite'),
    ],
    'mail' => [
        'enabled' => (bool) Env::get('MAIL_ENABLED', false),
        'host' => (string) Env::get('MAIL_HOST', 'mail.atwebtech.us'),
        'port' => (int) Env::get('MAIL_PORT', 587),
        'encryption' => (string) Env::get('MAIL_ENCRYPTION', 'starttls'),
        'user' => (string) Env::get('MAIL_USER', ''),
        'pass' => (string) Env::get('MAIL_PASS', ''),
        'from' => (string) Env::get('MAIL_FROM', 'info@atwebtech.us'),
        'from_name' => (string) Env::get('MAIL_FROM_NAME', 'At Web Tech US'),
        'reply_to' => (string) Env::get('MAIL_REPLY_TO', 'info@atwebtech.us'),
        'reply_to_name' => (string) Env::get('MAIL_REPLY_TO_NAME', 'At Web Tech Query'),
        'notify_to' => (string) Env::get('MAIL_NOTIFY_TO', 'info@atwebtech.us'),
    ],
    'contact' => [
        'email' => (string) Env::get('CONTACT_EMAIL', 'info@atwebtech.us'),
        'phone' => (string) Env::get('CONTACT_PHONE', '+1 (480) 743-4017'),
        'address' => (string) Env::get('CONTACT_ADDRESS', '3672 S 16th Ave, Tucson, AZ 85713, US'),
    ],
    'admin' => [
        'emails' => (string) Env::get('ADMIN_EMAILS', ''),
    ],
    'integrations' => [
        'available' => (string) Env::get('INTEGRATIONS_AVAILABLE', ''),
    ],
    'openai' => [
        'key' => (string) Env::get('OPENAI_API_KEY', ''),
        'model' => (string) Env::get('OPENAI_MODEL', 'gpt-4o-mini'),
        'daily_limit' => max(1, (int) Env::get('AI_DAILY_LIMIT', 30)),
    ],
    'oauth' => [
        'google' => [
            'client_id' => (string) Env::get('GOOGLE_CLIENT_ID', ''),
            'client_secret' => (string) Env::get('GOOGLE_CLIENT_SECRET', ''),
        ],
        'linkedin' => [
            'client_id' => (string) Env::get('LINKEDIN_CLIENT_ID', ''),
            'client_secret' => (string) Env::get('LINKEDIN_CLIENT_SECRET', ''),
        ],
        'facebook' => [
            'client_id' => (string) Env::get('FACEBOOK_CLIENT_ID', ''),
            'client_secret' => (string) Env::get('FACEBOOK_CLIENT_SECRET', ''),
        ],
        'tiktok' => [
            'client_key' => (string) Env::get('TIKTOK_CLIENT_KEY', ''),
            'client_secret' => (string) Env::get('TIKTOK_CLIENT_SECRET', ''),
        ],
    ],
    'forms' => [
        'rate_limit_max' => max(1, (int) Env::get('RATE_LIMIT_MAX', 5)),
        'rate_limit_window' => max(1, (int) Env::get('RATE_LIMIT_WINDOW_MINUTES', 10)),
        'min_seconds' => 2,
    ],
];
