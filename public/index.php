<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

Security::sendHeaders();

$routes = require BASE_PATH . '/app/routes.php';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$method = $method === 'HEAD' ? 'GET' : $method;
$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = current_path();

if ($method === 'GET' && $rawPath !== $path && $rawPath !== '/') {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    redirect($path . ($query !== '' ? '?' . $query : ''), 301);
}

foreach ($routes as [$routeMethod, $pattern, $handler]) {
    $regex = '#^' . preg_replace('#\{([a-z]+)\}#', '(?P<$1>[a-z0-9-]+)', $pattern) . '$#';
    if (!preg_match($regex, $path, $matches)) {
        continue;
    }
    if ($routeMethod !== $method) {
        $allowed[] = $routeMethod;
        continue;
    }
    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    $handler(...$params);
    exit;
}

if (!empty($allowed)) {
    http_response_code(405);
    header('Allow: ' . implode(', ', array_unique($allowed)));
    echo 'Method not allowed';
    exit;
}

not_found();
