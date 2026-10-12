<?php

declare(strict_types=1);

// Publishes due posts to connected channels and tidies unused uploads.
// Run every minute from cron: * * * * * php /path/to/bin/publish.php >> storage/logs/publish.log 2>&1

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';

$lock = fopen(BASE_PATH . '/storage/logs/publish.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

$result = Publisher::run();
$cleaned = Media::cleanup();
if (array_sum($result) + $cleaned > 0) {
    printf("[%s] sent %d, retry %d, failed %d, cleaned %d uploads\n", gmdate('c'), $result['sent'], $result['retry'], $result['failed'], $cleaned);
}
flock($lock, LOCK_UN);
