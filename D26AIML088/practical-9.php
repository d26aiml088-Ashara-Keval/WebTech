<?php
declare(strict_types=1);

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
session_start();

if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$values = [
    'display_name' => '',
    'username' => '',
    'email' => '',
];
$errors = [];
$success = '';
$responseStatus = 200;
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'POST') {
    foreach (array_keys($values) as $field) {
        $postedValue = $_POST[$field] ?? '';
        $values[$field] = is_string($postedValue) ? $postedValue : '';
    }

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrfToken)
        || !is_string($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $csrfToken)
    ) {
        $errors['form'] = 'Your session expired or the form could not be verified. Reload the page and try again.';
        $responseStatus = 400;
    } else {
        $nameCharacterCount = preg_match_all('/./us', $values['display_name']);
        if (
            $nameCharacterCount === false
            || $nameCharacterCount < 1
            || $nameCharacterCount > 100
            || trim($values['display_name']) === ''
        ) {
            $errors['display_name'] = 'Enter a name between 1 and 100 characters.';
        } else {
            $values['display_name'] = trim($values['display_name']);
        }

        if (!preg_match('/\A[A-Za-z0-9_]{3,32}\z/', $values['username'])) {
            $errors['username'] = 'Use 3–32 letters, numbers, or underscores.';
        } else {
            $values['username'] = strtolower($values['username']);
        }

        if (
            strlen($values['email']) > 254
            || filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false
        ) {
            $errors['email'] = 'Enter a valid email address (up to 254 characters).';
        } else {
            $values['email'] = strtolower($values['email']);
        }

        if (
            !is_string($password)
            || !preg_match('/\A[\x20-\x7E]{8,72}\z/', $password)
        ) {
            $errors['password'] = 'Use 8–72 printable ASCII characters for the password.';
        }

        if (!is_string($confirmPassword) || !is_string($password) || $password !== $confirmPassword) {
            $errors['confirm_password'] = 'The passwords do not match.';
        }

        if ($errors !== []) {
            $responseStatus = 422;
        }

        if ($errors === []) {
            $connection = null;

            try {
                $connection = require __DIR__ . '/db-mysqli.php';
                $duplicateCheck = $connection->prepare(
                    'SELECT user_id
                     FROM users
                     WHERE username = ? OR email = ?
                     LIMIT 1'
                );
                $duplicateCheck->bind_param('ss', $values['username'], $values['email']);
                $duplicateCheck->execute();
                $duplicateCheck->store_result();

                if ($duplicateCheck->num_rows > 0) {
                    $errors['form'] = 'That username or email address is already registered.';
                    $responseStatus = 409;
                } else {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $nextCsrfToken = bin2hex(random_bytes(32));
                    $connection->begin_transaction();

                    try {
                        $insertUser = $connection->prepare(
                            'INSERT INTO users (display_name, username, email, password_hash)
                             VALUES (?, ?, ?, ?)'
                        );
                        $insertUser->bind_param(
                            'ssss',
                            $values['display_name'],
                            $values['username'],
                            $values['email'],
                            $passwordHash
                        );
                        $insertUser->execute();
                        $userId = $connection->insert_id;

                        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
                        if (!is_string($ipAddress) || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
                            $ipAddress = null;
                        }
                        $audit = $connection->prepare(
                            'INSERT INTO registration_audit (user_id, action, ip_address)
                             VALUES (?, ?, ?)'
                        );
                        $action = 'user_registered';
                        $audit->bind_param('iss', $userId, $action, $ipAddress);
                        $audit->execute();

                        $connection->commit();
                        $success = 'Your account was created successfully.';
                        $values = ['display_name' => '', 'username' => '', 'email' => ''];
                        $_SESSION['csrf_token'] = $nextCsrfToken;
                    } catch (mysqli_sql_exception $exception) {
                        $connection->rollback();

                        if ($exception->getCode() === 1062) {
                            $errors['form'] = 'That username or email address is already registered.';
                            $responseStatus = 409;
                        } else {
                            throw $exception;
                        }
                    }
                }

                $duplicateCheck->close();
            } catch (mysqli_sql_exception | RuntimeException $exception) {
                error_log('Practical 9 registration error: ' . $exception->getMessage());
                $errors['form'] = 'Registration could not be completed. Check the database configuration and try again.';
                $responseStatus = 500;
            }

            if ($connection instanceof mysqli) {
                $connection->close();
            }

        }

        http_response_code($responseStatus);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Create a StudentHub account with server-side validation, MySQLi prepared statements, and password hashing.">
    <title>Student Hub | Practical 9: Secure Registration</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="index.html"><span class="brand-mark" aria-hidden="true">S</span> Student Hub</a>
            <nav class="site-nav" aria-label="Main navigation">
                <a href="index.html">Home</a>
                <a href="register.html">Register</a>
                <a href="events.html">Events</a>
                <a href="practical-7.php">Practical 7</a>
                <a href="practical-8.php">Practical 8</a>
                <a href="practical-9.php" aria-current="page">Practical 9</a>
            </nav>
            <button class="theme-toggle" type="button" aria-label="Switch to dark mode" data-theme-toggle>Dark mode</button>
        </div>
    </header>

    <main class="page-shell">
        <header class="page-intro">
            <p class="eyebrow">Practical 9 · Secure Registration</p>
            <h1>Create your StudentHub account.</h1>
            <p class="lede">Frontend and backend validation, duplicate username/email checks, MySQLi prepared statements, hashed passwords, and a CSRF-protected registration form.</p>
        </header>

        <?php if ($success !== ''): ?>
            <p class="form-feedback form-feedback-success" role="status"><?= escapeHtml($success) ?></p>
        <?php endif; ?>

        <?php if (isset($errors['form'])): ?>
            <p class="form-feedback form-feedback-error" role="alert"><?= escapeHtml($errors['form']) ?></p>
        <?php endif; ?>

        <section class="registration-form" aria-labelledby="registration-title">
            <p class="eyebrow">Account details</p>
            <h2 id="registration-title">Register securely</h2>
            <form action="practical-9.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml($_SESSION['csrf_token']) ?>">
                <div class="form-grid">
                    <div class="form-field">
                        <label for="display-name">Full name</label>
                        <input id="display-name" name="display_name" type="text" maxlength="100" pattern=".*\S.*" autocomplete="name" value="<?= escapeHtml($values['display_name']) ?>" required>
                        <?php if (isset($errors['display_name'])): ?>
                            <small class="error-message" role="alert"><?= escapeHtml($errors['display_name']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-field">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text" minlength="3" maxlength="32" pattern="[A-Za-z0-9_]{3,32}" autocomplete="username" value="<?= escapeHtml($values['username']) ?>" required aria-describedby="username-help">
                        <small id="username-help">3–32 letters, numbers, or underscores.</small>
                        <?php if (isset($errors['username'])): ?>
                            <small class="error-message" role="alert"><?= escapeHtml($errors['username']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-field form-field-wide">
                        <label for="email">Email address</label>
                        <input id="email" name="email" type="email" maxlength="254" autocomplete="email" value="<?= escapeHtml($values['email']) ?>" required>
                        <?php if (isset($errors['email'])): ?>
                            <small class="error-message" role="alert"><?= escapeHtml($errors['email']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-field">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" minlength="8" maxlength="72" pattern="[ -~]{8,72}" autocomplete="new-password" required aria-describedby="password-help">
                        <small id="password-help">8–72 printable ASCII characters.</small>
                        <?php if (isset($errors['password'])): ?>
                            <small class="error-message" role="alert"><?= escapeHtml($errors['password']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-field">
                        <label for="confirm-password">Confirm password</label>
                        <input id="confirm-password" name="confirm_password" type="password" minlength="8" maxlength="72" pattern="[ -~]{8,72}" autocomplete="new-password" required>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <small class="error-message" role="alert"><?= escapeHtml($errors['confirm_password']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="button-primary" type="submit">Create account</button>
                </div>
            </form>
        </section>

        <section class="content-panel" aria-labelledby="security-title">
            <p class="eyebrow">Security notes</p>
            <h2 id="security-title">How registration is protected</h2>
            <ul>
                <li>Client-side input constraints mirror the server-side validation rules; server validation remains authoritative.</li>
                <li>Username/email checks use a prepared query, with unique database indexes guarding concurrent submissions.</li>
                <li><code>password_hash()</code> stores a one-way password hash; the submitted password is never stored as plaintext.</li>
                <li>A CSRF token protects the form, and a successful registration plus its audit entry commit in one transaction.</li>
            </ul>
        </section>
    </main>

    <footer class="site-footer"><p>Student Hub · Practical 9: Secure User Registration</p></footer>
    <script>
        const toggle = document.querySelector('[data-theme-toggle]');
        const applyTheme = (theme) => {
            const isDark = theme === 'dark';
            document.body.classList.toggle('dark-mode', isDark);
            if (toggle) {
                toggle.textContent = isDark ? 'Light mode' : 'Dark mode';
                toggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
            }
        };

        const savedTheme = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        applyTheme(savedTheme || (prefersDark ? 'dark' : 'light'));

        toggle?.addEventListener('click', () => {
            const nextTheme = document.body.classList.contains('dark-mode') ? 'light' : 'dark';
            localStorage.setItem('theme', nextTheme);
            applyTheme(nextTheme);
        });
    </script>
</body>
</html>
