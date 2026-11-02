<?php
declare(strict_types=1);

$localAddresses = ['127.0.0.1', '::1'];
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

if (!is_string($remoteAddress) || !in_array($remoteAddress, $localAddresses, true)) {
    http_response_code(403);
    exit('Run setup from this computer only.');
}

$setupError = '';
$setupComplete = false;
$tableCounts = [];
$connection = null;

try {
    require_once __DIR__ . '/db.php';
    $connection = createMysqliConnection();

    $connection->query(
        "CREATE TABLE IF NOT EXISTS users (
            user_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            display_name VARCHAR(100) NOT NULL,
            username VARCHAR(32) NOT NULL,
            email VARCHAR(254) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin', 'student') NOT NULL DEFAULT 'student',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            UNIQUE KEY uq_users_username (username),
            UNIQUE KEY uq_users_email (email)
        ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );

    $roleColumn = $connection->query("SHOW COLUMNS FROM users LIKE 'role'");
    $hasRole = $roleColumn->num_rows > 0;
    $roleColumn->free();

    if (!$hasRole) {
        $connection->query(
            "ALTER TABLE users
             ADD COLUMN role ENUM('admin', 'student') NOT NULL DEFAULT 'student'
             AFTER password_hash"
        );
    }

    $connection->query(
        "CREATE TABLE IF NOT EXISTS students (
            student_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            full_name VARCHAR(120) NOT NULL,
            email VARCHAR(254) NOT NULL,
            department VARCHAR(100) NOT NULL,
            study_year TINYINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (student_id),
            UNIQUE KEY uq_students_email (email)
        ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );

    $connection->query(
        "CREATE TABLE IF NOT EXISTS events (
            event_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(160) NOT NULL,
            category VARCHAR(60) NOT NULL,
            event_date DATE NOT NULL,
            location VARCHAR(160) NOT NULL,
            description TEXT NOT NULL,
            poster_path VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (event_id),
            KEY idx_events_event_date (event_date)
        ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );

    $posterColumn = $connection->query("SHOW COLUMNS FROM events LIKE 'poster_path'");
    $hasPosterPath = $posterColumn->num_rows > 0;
    $posterColumn->free();

    if (!$hasPosterPath) {
        $connection->query('ALTER TABLE events ADD COLUMN poster_path VARCHAR(255) NULL AFTER description');
    }

    $connection->query(
        "CREATE TABLE IF NOT EXISTS registrations (
            registration_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT UNSIGNED NOT NULL,
            event_id INT UNSIGNED NOT NULL,
            registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status ENUM('registered', 'attended', 'cancelled') NOT NULL DEFAULT 'registered',
            PRIMARY KEY (registration_id),
            UNIQUE KEY uq_registrations_student_event (student_id, event_id),
            KEY idx_registrations_event_id (event_id),
            CONSTRAINT fk_registrations_student
                FOREIGN KEY (student_id) REFERENCES students (student_id)
                ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT fk_registrations_event
                FOREIGN KEY (event_id) REFERENCES events (event_id)
                ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );

    $connection->query(
        "CREATE TABLE IF NOT EXISTS remember_tokens (
            token_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (token_id),
            UNIQUE KEY uq_remember_tokens_hash (token_hash),
            KEY idx_remember_tokens_user (user_id),
            CONSTRAINT fk_remember_tokens_user
                FOREIGN KEY (user_id) REFERENCES users (user_id)
                ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );

    $connection->query(
        "CREATE TABLE IF NOT EXISTS audit_log (
            audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            actor_user_id INT UNSIGNED NULL,
            action VARCHAR(80) NOT NULL,
            entity_type VARCHAR(40) NULL,
            entity_id BIGINT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            details JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (audit_id),
            KEY idx_audit_log_actor (actor_user_id),
            KEY idx_audit_log_created (created_at),
            CONSTRAINT fk_audit_log_actor
                FOREIGN KEY (actor_user_id) REFERENCES users (user_id)
                ON UPDATE CASCADE ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );

    $posterDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'posters';
    if (!is_dir($posterDirectory) && !mkdir($posterDirectory, 0750, true) && !is_dir($posterDirectory)) {
        throw new RuntimeException('The event poster directory could not be created.');
    }

    $setupComplete = true;

    foreach (['users', 'students', 'events', 'registrations', 'audit_log'] as $table) {
        $result = $connection->query('SELECT COUNT(*) AS record_count FROM ' . $table);
        $row = $result->fetch_assoc();
        $tableCounts[$table] = (int) $row['record_count'];
        $result->free();
    }
} catch (RuntimeException $exception) {
    error_log('StudentHub setup error: ' . $exception->getMessage());
    $setupError = 'Setup could not be completed. Confirm that MySQL is running and the configured database account can create and alter tables.';
}

if ($connection instanceof mysqli) {
    $connection->close();
}

function setupEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Hub | Local Setup</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="page-shell">
        <header class="page-intro">
            <p class="eyebrow">Practical 14 · Local setup</p>
            <h1>Prepare StudentHub</h1>
            <p class="lede">This local-only setup applies the role, audit, remember-me, and poster-upload schema needed by Practicals 10–15.</p>
        </header>

        <?php if ($setupComplete): ?>
            <section class="content-panel" aria-labelledby="setup-status">
                <h2 id="setup-status">Setup completed successfully</h2>
                <p class="form-feedback form-feedback-success" role="status">The StudentHub database is ready for login, management, and dashboard features.</p>
                <ul>
                    <?php foreach ($tableCounts as $table => $count): ?>
                        <li><?= setupEscape($table) ?>: <?= $count ?> records</li>
                    <?php endforeach; ?>
                </ul>
                <h3>Grant administrator access</h3>
                <p>Register your own account, then run this query in phpMyAdmin with your username:</p>
                <pre><code>UPDATE users SET role = 'admin' WHERE username = 'your_username';</code></pre>
                <p>Only grant this role to a trusted account. Keep this page available only on your local development server.</p>
                <p><a class="button-link" href="login.php">Open secure login</a></p>
            </section>
        <?php else: ?>
            <p class="form-feedback form-feedback-error" role="alert"><?= setupEscape($setupError) ?></p>
            <p>Import <code>database.sql</code> and <code>practical-8.sql</code>, configure the database environment variables, and run setup again.</p>
        <?php endif; ?>
    </main>
</body>
</html>
