<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

$currentUser = studentHubUser();
if ($currentUser !== null) {
    header('Location: dashboard.php');
    exit;
}

$username = '';
$error = '';
$responseStatus = 200;
$sessionStartedForLogin = false;
$timedOut = ($_SESSION['auth_timeout'] ?? false) === true;
unset($_SESSION['auth_timeout']);
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod !== 'GET' && $requestMethod !== 'POST') {
    header('Allow: GET, POST');
    $responseStatus = 405;
}

if ($requestMethod === 'POST') {
    $postedUsername = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? null;
    $username = is_string($postedUsername) ? trim($postedUsername) : '';

    if (!studentHubVerifyCsrf(is_string($csrfToken) ? $csrfToken : null)) {
        $error = 'Your session expired. Reload the page and try again.';
        $responseStatus = 400;
    } elseif (
        $username === ''
        || strlen($username) > 254
        || !is_string($password)
        || strlen($password) > 4096
    ) {
        $error = 'Enter a valid username or email address and password.';
        $responseStatus = 422;
    } else {
        try {
            $connection = studentHubDb();
            $statement = $connection->prepare(
                'SELECT user_id, display_name, username, email, password_hash, role
                 FROM users
                 WHERE username = ? OR email = ?
                 LIMIT 1'
            );
            $statement->bind_param('ss', $username, $username);
            $statement->execute();
            $result = $statement->get_result();
            $user = $result->fetch_assoc();
            $statement->close();

            if ($user === null || !password_verify($password, $user['password_hash'])) {
                studentHubAudit(
                    'login_failed',
                    $user === null ? null : (int) $user['user_id'],
                    'user'
                );
                $error = 'The username/email or password is incorrect.';
                $responseStatus = 401;
            } else {
                $userId = (int) $user['user_id'];
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    $updatedHash = password_hash($password, PASSWORD_DEFAULT);
                    $rehash = $connection->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
                    $rehash->bind_param('si', $updatedHash, $userId);
                    $rehash->execute();
                    $rehash->close();
                }

                $connection->query('DELETE FROM remember_tokens WHERE expires_at <= UTC_TIMESTAMP()');
                studentHubStartAuthenticatedSession($user);
                $sessionStartedForLogin = true;

                if (isset($_POST['remember_me']) && $_POST['remember_me'] === '1') {
                    $rememberToken = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $rememberToken);
                    $expiresAt = time() + STUDENTHUB_REMEMBER_SECONDS;
                    $expiresAtSql = gmdate('Y-m-d H:i:s', $expiresAt);
                    $remember = $connection->prepare(
                        'INSERT INTO remember_tokens (user_id, token_hash, expires_at)
                         VALUES (?, ?, ?)'
                    );
                    $remember->bind_param('iss', $userId, $tokenHash, $expiresAtSql);
                    $remember->execute();
                    $remember->close();
                    studentHubSetRememberCookie($rememberToken, $expiresAt);
                }

                studentHubAudit('login', (int) $user['user_id'], 'user', (int) $user['user_id']);
                header('Location: dashboard.php', true, 303);
                exit;
            }
        } catch (RuntimeException $exception) {
            if ($sessionStartedForLogin) {
                studentHubClearRememberCookie();
                $_SESSION = [];
                session_regenerate_id(true);
                studentHubCsrfToken();
            }
            error_log('StudentHub login error: ' . $exception->getMessage());
            $error = 'Login could not be completed. Check the database setup and try again.';
            $responseStatus = 500;
        }
    }

    http_response_code($responseStatus);
}

$pageTitle = 'Log in | Student Hub';
require __DIR__ . '/partials/header.php';
?>
<header class="page-intro">
    <p class="eyebrow">Practical 10 · Secure authentication</p>
    <h1>Welcome back.</h1>
    <p class="lede">Sign in to your StudentHub account. Your role determines which management features you can access.</p>
</header>

<?php if ($timedOut): ?>
    <p class="form-feedback form-feedback-error" role="status">Your session expired after five minutes of inactivity. Please sign in again.</p>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="form-feedback form-feedback-error" role="alert"><?= studentHubEscape($error) ?></p>
<?php endif; ?>

<section class="registration-form auth-card" aria-labelledby="login-title">
    <h2 id="login-title">Sign in</h2>
    <form action="login.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= studentHubEscape(studentHubCsrfToken()) ?>">
        <div class="form-grid">
            <div class="form-field">
                <label for="username">Username or email</label>
                <input id="username" name="username" type="text" maxlength="254" autocomplete="username" value="<?= studentHubEscape($username) ?>" required>
            </div>
            <div class="form-field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <label class="checkbox-field">
                <input type="checkbox" name="remember_me" value="1">
                <span>Remember me on this device for 30 days</span>
            </label>
        </div>
        <div class="form-actions">
            <button class="button-primary" type="submit">Log in</button>
            <a class="button-secondary" href="register.php">Create an account</a>
        </div>
    </form>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
