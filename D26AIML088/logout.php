<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Use the sign-out form to log out.');
}

$csrfToken = $_POST['csrf_token'] ?? null;
if (!studentHubVerifyCsrf(is_string($csrfToken) ? $csrfToken : null)) {
    http_response_code(400);
    exit('The sign-out request could not be verified. Reload the page and try again.');
}

$user = studentHubUser();

try {
    if ($user !== null) {
        studentHubAudit('logout', (int) $user['user_id'], 'user', (int) $user['user_id']);
    }
} catch (RuntimeException $exception) {
    error_log('StudentHub logout audit error: ' . $exception->getMessage());
    http_response_code(500);
    exit('Sign out could not be recorded. Please try again.');
}

studentHubClearRememberCookie();
$_SESSION = [];
session_regenerate_id(true);
studentHubCsrfToken();
studentHubFlash('status', 'You have been signed out.');

header('Location: login.php', true, 303);
exit;
