<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (preg_match('#^/api/#', $path)) {
    require __DIR__ . '/api.php';
    return true;
}
return false;
