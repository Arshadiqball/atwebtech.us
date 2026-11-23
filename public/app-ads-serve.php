<?php
// TEMPORARY diagnostic (remove once AdMob verifies): serves app-ads.txt and logs
// who fetched it, so we can see what Google's app-ads.txt crawler actually does.
$log = __DIR__ . '/../storage/logs/app-ads-hits.log';
if (($_GET['log'] ?? '') === '0e5da20c9b3bfb6ade22511c3da807c1') {
    header('Content-Type: text/plain; charset=utf-8');
    echo is_file($log) ? file_get_contents($log) : "no hits logged\n";
    exit;
}
$line = sprintf("%s | %s | %s | %s %s | %s\n", gmdate('c'), $_SERVER['REMOTE_ADDR'] ?? '-',
    $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '-', $_SERVER['REQUEST_METHOD'] ?? '-',
    ($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http', $_SERVER['HTTP_USER_AGENT'] ?? '-');
@file_put_contents($log, $line, FILE_APPEND | LOCK_EX);
header('Content-Type: text/plain');
readfile(__DIR__ . '/app-ads.txt');
