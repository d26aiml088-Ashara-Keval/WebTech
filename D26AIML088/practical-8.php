<?php
declare(strict_types=1);

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$email = isset($_GET['email']) && is_string($_GET['email'])
    ? trim($_GET['email'])
    : '';
$connection = null;
$student = null;
$databaseError = '';
$studentError = '';
$counts = [];

try {
    $connection = require __DIR__ . '/db.php';

    foreach (['students', 'events', 'registrations'] as $table) {
        $statement = $connection->query('SELECT COUNT(*) FROM ' . $table);
        $counts[$table] = (int) $statement->fetchColumn();
    }
} catch (PDOException | RuntimeException $exception) {
    error_log('Practical 8 database connection error: ' . $exception->getMessage());
    $connection = null;
    $databaseError = 'The database connection or connection test failed. Check the local MySQL service and the database settings.';
}

if ($connection instanceof PDO && $email !== '') {
    try {
        $statement = $connection->prepare(
            'SELECT full_name, email, department, study_year
             FROM students
             WHERE email = :email'
        );
        $statement->execute(['email' => $email]);
        $student = $statement->fetch();

        if ($student === false) {
            $student = null;
            $studentError = 'No student was found for that email address.';
        }
    } catch (PDOException $exception) {
        error_log('Practical 8 prepared lookup error: ' . $exception->getMessage());
        $studentError = 'The student lookup could not be completed.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Test the StudentHub MySQL schema and secure PHP PDO prepared statements.">
    <title>Student Hub | Practical 8: MySQL and PDO</title>
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
                <a href="practical-7.php">Practical 7</a>
                <a href="practical-8.php" aria-current="page">Practical 8</a>
                <a href="practical-9.php">Practical 9</a>
            </nav>
            <button class="theme-toggle" type="button" aria-label="Switch to dark mode" data-theme-toggle>Dark mode</button>
        </div>
    </header>

    <main class="page-shell">
        <header class="page-intro">
            <p class="eyebrow">Practical 8 · MySQL and PDO</p>
            <h1>StudentHub, connected securely.</h1>
            <p class="lede">A normalized MySQL schema connects students to campus events through registrations. This page verifies PDO connectivity and tests a parameterized student lookup.</p>
        </header>

        <section class="content-panel" aria-labelledby="connection-title">
            <p class="eyebrow">Connection test</p>
            <h2 id="connection-title">Database status</h2>
            <?php if ($connection instanceof PDO): ?>
                <p class="form-feedback form-feedback-success" role="status">Connected to the StudentHub MySQL database using PDO.</p>
                <ul>
                    <?php foreach ($counts as $table => $count): ?>
                        <li><?= escapeHtml(ucfirst($table)) ?>: <?= $count ?> records</li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="form-feedback form-feedback-error" role="alert"><?= escapeHtml($databaseError) ?></p>
            <?php endif; ?>
        </section>

        <section class="registration-form" aria-labelledby="prepared-query-title">
            <p class="eyebrow">Prepared statement demonstration</p>
            <h2 id="prepared-query-title">Find a student by email</h2>
            <p>The email value is bound as a parameter; it is never concatenated into the SQL statement.</p>
            <form action="practical-8.php" method="get">
                <div class="form-grid">
                    <div class="form-field form-field-wide">
                        <label for="student-email">Student email</label>
                        <input id="student-email" name="email" type="email" maxlength="254" autocomplete="email" value="<?= escapeHtml($email) ?>" placeholder="aarav.mehta@studenthub.example" required>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="button-primary" type="submit">Run prepared lookup</button>
                </div>
            </form>

            <?php if ($studentError !== ''): ?>
                <p class="form-feedback form-feedback-error" role="status"><?= escapeHtml($studentError) ?></p>
            <?php elseif ($student !== null): ?>
                <div class="content-panel" aria-live="polite">
                    <h3><?= escapeHtml($student['full_name']) ?></h3>
                    <p><?= escapeHtml($student['email']) ?></p>
                    <p><?= escapeHtml($student['department']) ?> · Year <?= (int) $student['study_year'] ?></p>
                </div>
            <?php endif; ?>
        </section>

        <section class="content-panel" aria-labelledby="relationship-title">
            <p class="eyebrow">ER model</p>
            <h2 id="relationship-title">Entities and relationships</h2>
            <p><code>students (student_id PK) 1 ───&lt; registrations (registration_id PK, student_id FK, event_id FK) &gt;─── 1 events (event_id PK)</code></p>
            <ul>
                <li><strong>students:</strong> one row per student; email is unique.</li>
                <li><strong>events:</strong> one row per campus event.</li>
                <li><strong>registrations:</strong> junction table with foreign keys to both entities; a student can register for many events and an event can have many students.</li>
                <li>A unique student/event pair prevents duplicate registrations. The schema keeps repeating registration data out of student and event rows.</li>
            </ul>
        </section>

        <section class="content-panel" aria-labelledby="screenshot-title">
            <p class="eyebrow">Submission evidence</p>
            <h2 id="screenshot-title">Capture the live connection test</h2>
            <p>After importing <code>practical-8.sql</code> and setting the database environment variables, open this page through XAMPP and capture the green connected status and table record counts for your submission.</p>
        </section>
    </main>

    <footer class="site-footer"><p>Student Hub · Practical 8: MySQL Schema Design and PDO</p></footer>
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
