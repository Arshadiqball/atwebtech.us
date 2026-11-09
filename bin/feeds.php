<?php

declare(strict_types=1);

// Checks every followed RSS/Atom feed for new articles. Run hourly from cron:
// 15 * * * * /usr/bin/php /path/to/atwebtech/bin/feeds.php >> /path/to/atwebtech/storage/logs/feeds.log 2>&1

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/app/bootstrap.php';

$lock = fopen(BASE_PATH . '/storage/logs/feeds.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}
set_time_limit(0);
$r = Feeds::refreshAll();
echo '[' . gmdate('c') . "] feeds checked: {$r['checked']}, ok: {$r['ok']}\n";
flock($lock, LOCK_UN);
