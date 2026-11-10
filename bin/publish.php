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

$recycled = Evergreen::run();
if ($recycled > 0) {
    printf("[%s] evergreen: %d posts recycled into empty slots\n", gmdate('c'), $recycled);
}
$result = Publisher::run();
$cleaned = Media::cleanup();
if (array_sum($result) + $cleaned > 0) {
    printf("[%s] sent %d, retry %d, failed %d, cleaned %d uploads\n", gmdate('c'), $result['sent'], $result['retry'], $result['failed'], $cleaned);
}
flock($lock, LOCK_UN);
fclose($lock);

// Network stats for Insights, in small batches with their own lock so publishing is never held up.
$statsLock = fopen(BASE_PATH . '/storage/logs/metrics.lock', 'c');
if ($statsLock && flock($statsLock, LOCK_EX | LOCK_NB)) {
    try {
        $stats = Metrics::run(30);
        $followed = Competitors::run(15);
        $inbox = Community::run(15);
        $reports = ClientReports::run();
        $hooks = Webhooks::run();
        if ($hooks > 0) {
            printf("[%s] webhooks: %d deliveries\n", gmdate('c'), $hooks);
        }
        if ($reports > 0) {
            printf("[%s] reports: %d emailed\n", gmdate('c'), $reports);
        }
        if ($inbox > 0) {
            printf("[%s] inbox: %d channels read\n", gmdate('c'), $inbox);
        }
        if ($followed > 0) {
            printf("[%s] competitors: %d refreshed\n", gmdate('c'), $followed);
        }
        if ($stats['channels'] > 0) {
            printf("[%s] stats: %d channels, %d posts, %d errors\n", gmdate('c'), $stats['channels'], $stats['posts'], $stats['errors']);
        }
    } catch (Throwable $e) {
        error_log(sprintf('[%s] metrics run failed: %s', gmdate('c'), $e->getMessage()));
    }
    flock($statsLock, LOCK_UN);
}

// Automations run under their own lock: writing posts with AI can take a while.
$autoLock = fopen(BASE_PATH . '/storage/logs/automations.lock', 'c');
if ($autoLock && flock($autoLock, LOCK_EX | LOCK_NB)) {
    try {
        $autoMade = Automations::run(25);
        if ($autoMade > 0) {
            printf("[%s] automations: %d posts made\n", gmdate('c'), $autoMade);
        }
        Automations::housekeeping();
    } catch (Throwable $e) {
        error_log(sprintf('[%s] automations run failed: %s', gmdate('c'), $e->getMessage()));
    }
    flock($autoLock, LOCK_UN);
}
