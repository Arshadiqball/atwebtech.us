<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Env.php';
Env::load(BASE_PATH . '/.env');

require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/Database.php';
require BASE_PATH . '/app/Security.php';
require BASE_PATH . '/app/RateLimiter.php';
require BASE_PATH . '/app/Mailer.php';
require BASE_PATH . '/app/EmailTemplate.php';
require BASE_PATH . '/app/Campaign.php';
require BASE_PATH . '/app/FormHandler.php';
require BASE_PATH . '/app/Admin.php';
require BASE_PATH . '/app/View.php';
require BASE_PATH . '/app/Charts.php';
require BASE_PATH . '/app/Pricing.php';
require BASE_PATH . '/app/Auth.php';
require BASE_PATH . '/app/QrCode.php';
require BASE_PATH . '/app/TwoFactor.php';
require BASE_PATH . '/app/AccountHandler.php';
require BASE_PATH . '/app/Workspace.php';
require BASE_PATH . '/app/WorkspaceHandler.php';
require BASE_PATH . '/app/Channels.php';
require BASE_PATH . '/app/ChannelHandler.php';
require BASE_PATH . '/app/Media.php';
require BASE_PATH . '/app/Assistant.php';
require BASE_PATH . '/app/Publisher.php';
require BASE_PATH . '/app/Migrator.php';

$debug = config('app.debug');
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
date_default_timezone_set('UTC');

try {
    Migrator::run();
} catch (Throwable $e) {
    error_log('Migration failed: ' . $e->getMessage());
    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Database setup failed.\n";
        exit;
    }
    throw $e;
}

if (PHP_SAPI !== 'cli') {
    Security::startSession();
    Campaign::capture();
}
