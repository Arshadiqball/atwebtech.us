<?php

declare(strict_types=1);

// Refreshes the composer's Trending list. Run weekly from cron, for example every Monday at 06:00:
// 0 6 * * 1 /usr/bin/php /path/to/atwebtech/bin/trends.php >> /path/to/atwebtech/storage/logs/trends.log 2>&1

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/app/bootstrap.php';

$lock = fopen(BASE_PATH . '/storage/logs/trends.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Another trends refresh is running.\n");
    exit(0);
}
set_time_limit(0);
echo '[' . gmdate('c') . "] refreshing trends\n";
$report = Trends::refresh(static function (string $line): void { echo '  ' . $line . "\n"; });
$saved = count(array_filter($report, 'is_int'));
echo '[' . gmdate('c') . "] done: {$saved} of " . count($report) . " sets updated\n";
flock($lock, LOCK_UN);
