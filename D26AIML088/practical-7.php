<?php
declare(strict_types=1);

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
session_start();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

const COURSES = ['BCA', 'BSc', 'BTech', 'MCA'];

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cleanInput(mixed $value): string
{
    if (!is_string($value)) {
        return '';
    }

    $value = trim(strip_tags($value));
    $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

    if ($cleaned === null) {
        return '';
    }

    return $cleaned;
}

function saveSubmission(array $submission): void
{
    $storageDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
    $storageFile = $storageDirectory . DIRECTORY_SEPARATOR . 'submissions.json';

    if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0700, true) && !is_dir($storageDirectory)) {
        throw new RuntimeException('The private storage directory could not be created.');
    }

    $handle = fopen($storageFile, 'c+');

    if ($handle === false) {
        throw new RuntimeException('The submissions file could not be opened.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('The submissions file could not be locked.');
        }

        $contents = stream_get_contents($handle);

        if ($contents === false) {
            throw new RuntimeException('The submissions file could not be read.');
        }

        if (trim($contents) === '') {
            $submissions = [];
        } else {
            try {
                $submissions = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('The submissions file contains invalid JSON.', 0, $exception);
            }
        }

        if (!is_array($submissions) || !array_is_list($submissions)) {
            throw new RuntimeException('The submissions file must contain a JSON array.');
        }

        $submissions[] = $submission;
        $json = json_encode(
            $submissions,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        if (!ftruncate($handle, 0) || rewind($handle) === false) {
            throw new RuntimeException('The submissions file could not be prepared for writing.');
        }

        $contentsToWrite = $json . PHP_EOL;
        $bytesWritten = 0;

        while ($bytesWritten < strlen($contentsToWrite)) {
            $written = fwrite($handle, substr($contentsToWrite, $bytesWritten));

            if ($written === false || $written === 0) {
                throw new RuntimeException('The submission could not be written to disk.');
            }

            $bytesWritten += $written;
        }

        if (!fflush($handle)) {
            throw new RuntimeException('The submission could not be written to disk.');
        }
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

$errors = [];
$old = [
    'name' => '',
    'email' => '',
    'enrollment' => '',
    'course' => '',
    'message' => '',
];
$submittedForm = 'contact';
$success = '';
$responseStatus = 200;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: GET, POST');
    $responseStatus = 405;
}

if (isset($_SESSION['practical7_success'])) {
    $success = $_SESSION['practical7_success'];
    unset($_SESSION['practical7_success']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $submittedForm = isset($_POST['form_type']) && is_string($_POST['form_type'])
        ? $_POST['form_type']
        : '';

    foreach (['name', 'email', 'enrollment', 'course', 'message'] as $field) {
        $old[$field] = cleanInput($_POST[$field] ?? null);
    }

    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!is_string($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $errors['form'] = 'Your session token is invalid or expired. Refresh the page and try again.';
        $responseStatus = 403;
    } elseif (!in_array($submittedForm, ['contact', 'registration'], true)) {
        $errors['form'] = 'Choose a valid form before submitting.';
        $responseStatus = 400;
    } elseif (isset($_POST['website']) && is_string($_POST['website']) && $_POST['website'] !== '') {
        $errors['form'] = 'The form could not be submitted.';
        $responseStatus = 400;
    } else {
        if (
            mb_strlen($old['name'], 'UTF-8') > 120
            || !preg_match('/\A[\p{L}][\p{L}\p{M} .\'’-]{1,119}\z/u', $old['name'])
        ) {
            $errors['name'] = 'Enter a name using at least two letters.';
        }

        if (
            strlen($old['email']) > 254
            || filter_var($old['email'], FILTER_VALIDATE_EMAIL) === false
        ) {
            $errors['email'] = 'Enter a valid email address (up to 254 characters).';
        }

        $submission = [
            'id' => bin2hex(random_bytes(8)),
            'submittedAt' => gmdate(DATE_ATOM),
            'type' => $submittedForm,
            'name' => $old['name'],
            'email' => $old['email'],
        ];

        if ($submittedForm === 'contact') {
            if (mb_strlen($old['message'], 'UTF-8') < 10) {
                $errors['message'] = 'Write a message of at least 10 characters.';
            }

            if (mb_strlen($old['message'], 'UTF-8') > 2000) {
                $errors['message'] = 'Keep your message under 2,000 characters.';
            }

            $submission['message'] = $old['message'];
        } else {
            if (!preg_match('/\A[A-Za-z0-9\/-]{3,32}\z/', $old['enrollment'])) {
                $errors['enrollment'] = 'Use 3-32 letters, numbers, hyphens, or slashes.';
            }

            if (!in_array($old['course'], COURSES, true)) {
                $errors['course'] = 'Choose one of the listed courses.';
            }

            $submission['enrollment'] = $old['enrollment'];
            $submission['course'] = $old['course'];
        }

        if ($errors === []) {
            try {
                saveSubmission($submission);
                $_SESSION['practical7_success'] = $submittedForm === 'contact'
                    ? 'Your message was validated and saved successfully.'
                    : 'Your registration was validated and saved successfully.';
                header('Location: practical-7.php', true, 303);
                exit;
            } catch (JsonException | RuntimeException $exception) {
                error_log('Practical 7 storage error: ' . $exception->getMessage());
                $errors['form'] = 'Your information passed validation but could not be saved. Please try again later.';
                $responseStatus = 500;
            }
        } else {
            $responseStatus = 422;
        }
    }
}

http_response_code($responseStatus);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Submit contact messages or student registration details using PHP server-side validation and private JSON storage.">
    <title>Student Hub | PHP Form Processing</title>
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
                <a href="practical-6.html">Data Explorer</a>
                <a href="contact.html">Contact</a>
                <a href="practical-7.php" aria-current="page">Practical 7</a>
            </nav>
            <button class="theme-toggle" type="button" aria-label="Switch to dark mode" data-theme-toggle>Dark mode</button>
        </div>
    </header>

    <main class="page-shell">
        <header class="page-intro">
            <p class="eyebrow">Practical 7 · PHP</p>
            <h1>Forms, checked on the server.</h1>
            <p class="lede">Submit a contact message or registration. PHP validates every field, applies a CSRF check, and saves successful submissions as JSON.</p>
        </header>

        <?php if ($success !== ''): ?>
            <p class="form-feedback form-feedback-success" role="status"><?= escapeHtml($success) ?></p>
        <?php endif; ?>

        <?php if (isset($errors['form'])): ?>
            <p class="form-feedback form-feedback-error" role="alert"><?= escapeHtml($errors['form']) ?></p>
        <?php endif; ?>

        <div class="form-grid practical-form-grid">
            <section class="registration-form" aria-labelledby="contact-form-title">
                <p class="eyebrow">Questions and support</p>
                <h2 id="contact-form-title">Send a message</h2>
                <form action="practical-7.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="form_type" value="contact">
                    <div class="honeypot" aria-hidden="true">
                        <label for="contact-website">Leave this field empty</label>
                        <input id="contact-website" name="website" type="text" tabindex="-1" autocomplete="off">
                    </div>
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="contact-name">Name</label>
                            <input id="contact-name" name="name" type="text" maxlength="120" autocomplete="name" value="<?= escapeHtml($submittedForm === 'contact' ? $old['name'] : '') ?>" required>
                            <?php if ($submittedForm === 'contact' && isset($errors['name'])): ?>
                                <small class="error-message" role="alert"><?= escapeHtml($errors['name']) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="form-field">
                            <label for="contact-email">Email address</label>
                            <input id="contact-email" name="email" type="email" maxlength="254" autocomplete="email" value="<?= escapeHtml($submittedForm === 'contact' ? $old['email'] : '') ?>" required>
                            <?php if ($submittedForm === 'contact' && isset($errors['email'])): ?>
                                <small class="error-message" role="alert"><?= escapeHtml($errors['email']) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="form-field form-field-wide">
                            <label for="contact-message">Message</label>
                            <textarea id="contact-message" name="message" minlength="10" maxlength="2000" rows="5" required><?= escapeHtml($submittedForm === 'contact' ? $old['message'] : '') ?></textarea>
                            <?php if ($submittedForm === 'contact' && isset($errors['message'])): ?>
                                <small class="error-message" role="alert"><?= escapeHtml($errors['message']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="button-primary" type="submit">Send message</button>
                    </div>
                </form>
            </section>

            <section class="registration-form" aria-labelledby="registration-form-title">
                <p class="eyebrow">Join the Student Hub</p>
                <h2 id="registration-form-title">Register your interest</h2>
                <form action="practical-7.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="form_type" value="registration">
                    <div class="honeypot" aria-hidden="true">
                        <label for="registration-website">Leave this field empty</label>
                        <input id="registration-website" name="website" type="text" tabindex="-1" autocomplete="off">
                    </div>
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="registration-name">Full name</label>
                            <input id="registration-name" name="name" type="text" maxlength="120" autocomplete="name" value="<?= escapeHtml($submittedForm === 'registration' ? $old['name'] : '') ?>" required>
                            <?php if ($submittedForm === 'registration' && isset($errors['name'])): ?>
                                <small class="error-message" role="alert"><?= escapeHtml($errors['name']) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="form-field">
                            <label for="registration-email">Email address</label>
                            <input id="registration-email" name="email" type="email" maxlength="254" autocomplete="email" value="<?= escapeHtml($submittedForm === 'registration' ? $old['email'] : '') ?>" required>
                            <?php if ($submittedForm === 'registration' && isset($errors['email'])): ?>
                                <small class="error-message" role="alert"><?= escapeHtml($errors['email']) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="form-field">
                            <label for="registration-enrollment">Enrollment number</label>
                            <input id="registration-enrollment" name="enrollment" type="text" maxlength="32" value="<?= escapeHtml($submittedForm === 'registration' ? $old['enrollment'] : '') ?>" required>
                            <?php if ($submittedForm === 'registration' && isset($errors['enrollment'])): ?>
                                <small class="error-message" role="alert"><?= escapeHtml($errors['enrollment']) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="form-field">
                            <label for="registration-course">Course</label>
                            <select id="registration-course" name="course" required>
                                <option value="">Select a course</option>
                                <?php foreach (COURSES as $course): ?>
                                    <option value="<?= escapeHtml($course) ?>" <?= $submittedForm === 'registration' && $old['course'] === $course ? 'selected' : '' ?>><?= escapeHtml($course) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($submittedForm === 'registration' && isset($errors['course'])): ?>
                                <small class="error-message" role="alert"><?= escapeHtml($errors['course']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="button-primary" type="submit">Submit registration</button>
                    </div>
                </form>
            </section>
        </div>

        <section class="content-panel practical-notes" aria-labelledby="storage-title">
            <p class="eyebrow">File-based storage</p>
            <h2 id="storage-title">Validated submissions stay private</h2>
            <p>Successful submissions are appended to <code>storage/submissions.json</code> with an exclusive file lock. The storage directory denies direct web requests; the form does not collect or save passwords.</p>
        </section>
    </main>

    <footer class="site-footer"><p>Student Hub · Practical 7: PHP Form Processing</p></footer>
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
