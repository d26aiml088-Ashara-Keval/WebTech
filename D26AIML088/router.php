<?php
declare(strict_types=1);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (!is_string($requestPath)) {
    http_response_code(400);
    exit;
}

$normalizedPath = str_replace('\\', '/', rawurldecode($requestPath));

if (
    preg_match('#(?:^|/)(?:storage|\.git)(?:/|$)#i', $normalizedPath)
    || preg_match('#(?:^|/)\.(?:htaccess|env|gitignore|gitmodules|user\.ini)$#i', $normalizedPath)
    || preg_match('#(?:^|/)partials(?:/|$)#i', $normalizedPath)
    || preg_match('#(?:^|/)(?:app|db|event-functions)\.php$#i', $normalizedPath)
) {
    http_response_code(404);
    exit;
}

if (
    preg_match('#(?:^|/)uploads/posters/[^/]+$#i', $normalizedPath)
    && !preg_match('#\.(?:jpg|png|webp)$#i', $normalizedPath)
) {
    http_response_code(404);
    exit;
}

$compatibilityRoutes = [
    '/index.html' => 'index.php',
    '/register.html' => 'register.php',
    '/login.html' => 'login.php',
    '/events.html' => 'events.php',
    '/Dashboard.html' => 'dashboard.php',
    '/dashboard.html' => 'dashboard.php',
    '/admin.html' => 'admin.php',
];

if (isset($compatibilityRoutes[$normalizedPath])) {
    header('Location: ' . $compatibilityRoutes[$normalizedPath], true, 302);
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
    require __DIR__ . '/index.php';
    exit;
}

http_response_code(404);
