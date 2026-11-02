<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const STUDENTHUB_SESSION_TIMEOUT = 300;
const STUDENTHUB_REMEMBER_COOKIE = 'studenthub_remember';
const STUDENTHUB_REMEMBER_SECONDS = 2592000;

function studentHubDb(): mysqli
{
    static $connection = null;

    if (!$connection instanceof mysqli) {
        $connection = createMysqliConnection();
    }

    return $connection;
}

function studentHubEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function studentHubCsrfToken(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function studentHubVerifyCsrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function studentHubSetRememberCookie(string $token, int $expiresAt): void
{
    setcookie(STUDENTHUB_REMEMBER_COOKIE, $token, [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function studentHubClearRememberCookie(): void
{
    if (isset($_COOKIE[STUDENTHUB_REMEMBER_COOKIE]) && is_string($_COOKIE[STUDENTHUB_REMEMBER_COOKIE])) {
        try {
            $tokenHash = hash('sha256', $_COOKIE[STUDENTHUB_REMEMBER_COOKIE]);
            $statement = studentHubDb()->prepare('DELETE FROM remember_tokens WHERE token_hash = ?');
            $statement->bind_param('s', $tokenHash);
            $statement->execute();
            $statement->close();
        } catch (RuntimeException $exception) {
            error_log('StudentHub remember-token cleanup error: ' . $exception->getMessage());
        }
    }

    setcookie(STUDENTHUB_REMEMBER_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[STUDENTHUB_REMEMBER_COOKIE]);
}

function studentHubStartAuthenticatedSession(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function studentHubAudit(string $action, ?int $userId = null, ?string $entityType = null, ?int $entityId = null, array $details = []): void
{
    $connection = studentHubDb();
    $detailsJson = $details === []
        ? null
        : json_encode($details, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    if (!is_string($ipAddress) || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
        $ipAddress = null;
    }

    $statement = $connection->prepare(
        'INSERT INTO audit_log (actor_user_id, action, entity_type, entity_id, ip_address, details)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $statement->bind_param('ississ', $userId, $action, $entityType, $entityId, $ipAddress, $detailsJson);
    $statement->execute();
    $statement->close();
}

function studentHubUser(): ?array
{
    static $loaded = false;
    static $user = null;

    if ($loaded) {
        return $user;
    }

    $loaded = true;

    if (isset($_SESSION['user_id']) && is_int($_SESSION['user_id'])) {
        $statement = studentHubDb()->prepare(
            'SELECT user_id, display_name, username, email, role
             FROM users
             WHERE user_id = ?
             LIMIT 1'
        );
        $userId = $_SESSION['user_id'];
        $statement->bind_param('i', $userId);
        $statement->execute();
        $result = $statement->get_result();
        $row = $result->fetch_assoc();
        $statement->close();

        if ($row !== null) {
            $user = $row;
            $_SESSION['last_activity'] = time();
            return $user;
        }

        $_SESSION = [];
        session_regenerate_id(true);
        studentHubCsrfToken();
        return null;
    }

    $rememberToken = $_COOKIE[STUDENTHUB_REMEMBER_COOKIE] ?? null;
    if (!is_string($rememberToken) || !preg_match('/\A[a-f0-9]{64}\z/', $rememberToken)) {
        return null;
    }

    $tokenHash = hash('sha256', $rememberToken);
    $statement = studentHubDb()->prepare(
        'SELECT rt.token_id, u.user_id, u.display_name, u.username, u.email, u.role
         FROM remember_tokens AS rt
         INNER JOIN users AS u ON u.user_id = rt.user_id
         WHERE rt.token_hash = ? AND rt.expires_at > UTC_TIMESTAMP()
         LIMIT 1'
    );
    $statement->bind_param('s', $tokenHash);
    $statement->execute();
    $result = $statement->get_result();
    $row = $result->fetch_assoc();
    $statement->close();

    if ($row === null) {
        studentHubClearRememberCookie();
        return null;
    }

    $newToken = bin2hex(random_bytes(32));
    $newTokenHash = hash('sha256', $newToken);
    $expiresAt = time() + STUDENTHUB_REMEMBER_SECONDS;
    $expiresAtSql = gmdate('Y-m-d H:i:s', $expiresAt);
    $tokenId = (int) $row['token_id'];
    $rotate = studentHubDb()->prepare(
        'UPDATE remember_tokens
         SET token_hash = ?, expires_at = ?
         WHERE token_id = ? AND token_hash = ?'
    );
    $rotate->bind_param('ssis', $newTokenHash, $expiresAtSql, $tokenId, $tokenHash);
    $rotate->execute();
    $rotated = $rotate->affected_rows === 1;
    $rotate->close();

    if (!$rotated) {
        studentHubClearRememberCookie();
        return null;
    }

    studentHubSetRememberCookie($newToken, $expiresAt);
    studentHubStartAuthenticatedSession($row);
    $user = $row;
    unset($user['token_id']);
    studentHubAudit('remembered_login', (int) $user['user_id'], 'user', (int) $user['user_id']);

    return $user;
}

function studentHubRequireLogin(): array
{
    $user = studentHubUser();

    if ($user === null) {
        header('Location: login.php');
        exit;
    }

    return $user;
}

function studentHubRequireRole(string $role): array
{
    $user = studentHubRequireLogin();

    if ($user['role'] !== $role) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }

    return $user;
}

function studentHubFlash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);

    return is_string($value) ? $value : null;
}

function studentHubStartSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    $cookie = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookie['path'] !== '' ? $cookie['path'] : '/',
        'domain' => $cookie['domain'],
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    if (
        isset($_SESSION['user_id'], $_SESSION['last_activity'])
        && is_int($_SESSION['last_activity'])
        && time() - $_SESSION['last_activity'] > STUDENTHUB_SESSION_TIMEOUT
    ) {
        $expiredUserId = is_int($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        studentHubClearRememberCookie();

        try {
            studentHubAudit('session_timeout', $expiredUserId, 'user', $expiredUserId);
        } catch (RuntimeException $exception) {
            error_log('StudentHub session-timeout audit error: ' . $exception->getMessage());
        }

        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['auth_timeout'] = true;
    }

    studentHubCsrfToken();
}

studentHubStartSession();
