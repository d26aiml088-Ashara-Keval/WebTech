<?php
declare(strict_types=1);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (!is_string($requestPath)) {
    http_response_code(400);
    exit;
}

$normalizedPath = str_replace('\\', '/', rawurldecode($requestPath));

if (preg_match('#(?:^|/)storage(?:/|$)#i', $normalizedPath)) {
    http_response_code(404);
    exit;
}

$requestedFile = realpath(__DIR__ . $normalizedPath);
$workspaceRoot = realpath(__DIR__);

if (
    $normalizedPath !== '/'
    && $requestedFile !== false
    && $workspaceRoot !== false
    && str_starts_with($requestedFile, $workspaceRoot . DIRECTORY_SEPARATOR)
    && is_file($requestedFile)
) {
    return false;
}

if ($normalizedPath === '/' || $normalizedPath === '/index.php') {
    require __DIR__ . '/index.html';
    exit;
}

http_response_code(404);
